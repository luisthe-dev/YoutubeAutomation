<?php

namespace App\Http\Services;

use App\Http\Interfaces\ImageGeneratorInterface;
use App\Http\Services\ImageProvider\ChatGptImageService;
use App\Http\Services\ImageProvider\GeminiImageService;
use App\Http\Services\ImageProvider\PollinationsImageService;
use App\Http\Services\ImageProvider\ReplicateImageService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImageService implements ImageGeneratorInterface
{
    protected $geminiService;
    protected $chatGptService;
    protected $replicateService;
    protected $pollinationsService;
    protected $defaultDriver;
    protected $fallbackDriver;
    protected $useBackups;

    public function __construct(bool $useBackups = true, ?string $preferredDriver = null, ?string $fallbackDriver = null)
    {
        $this->geminiService = new GeminiImageService();
        $this->chatGptService = new ChatGptImageService();
        $this->replicateService = new ReplicateImageService();
        $this->pollinationsService = new PollinationsImageService();
        
        $this->defaultDriver = $preferredDriver ?? env('IMAGE_DRIVER', 'replicate');
        $this->fallbackDriver = $fallbackDriver;
        $this->useBackups = $useBackups;
    }

    public function generate(string $prompt, string $outputFilename): string
    {
        $maxRetries = env('IMAGE_GENERATION_RETRIES', 1); // Default 1 retry (total 2 attempts)
        $attempt = 0;

        while ($attempt <= $maxRetries) {
            $attempt++;
            
            // Try default driver first
            try {
                return $this->driver($this->defaultDriver)->generate($prompt, $outputFilename);
            } catch (\Exception $e) {
                Log::warning("Primary image driver ({$this->defaultDriver}) failed on attempt {$attempt}: " . $e->getMessage());
                
                // Fallback Chain
                $fallbacks = ['replicate', 'pollinations', 'gemini', 'openai'];
                
                // If a specific fallback is provided, try it first
                if ($this->fallbackDriver) {
                     array_unshift($fallbacks, $this->fallbackDriver);
                     $fallbacks = array_unique($fallbacks);
                }
                
                $fallbacks = array_diff($fallbacks, [$this->defaultDriver]);
                
                if (!$this->useBackups) {
                    $fallbacks = [];
                    Log::info("Backups disabled. Skipping fallback drivers.");
                }

                foreach ($fallbacks as $driver) {
                    try {
                        Log::info("Falling back to {$driver} (attempt {$attempt})...");
                        return $this->driver($driver)->generate($prompt, $outputFilename);
                    } catch (\Exception $e2) {
                        Log::warning("Fallback driver ({$driver}) failed on attempt {$attempt}: " . $e2->getMessage());
                        continue;
                    }
                }
            }

            // If we are here, all drivers failed for this attempt
            if ($attempt <= $maxRetries) {
                $retryMs = env('IMAGE_GENERATION_RETRY_MS', 120000); // Default 2 minutes
                $retrySeconds = $retryMs / 1000;
                
                Log::warning("All image drivers failed on attempt {$attempt}. Waiting {$retrySeconds} seconds ({$retryMs}ms) before retrying...");
                usleep($retryMs * 1000); // usleep takes microseconds
            } else {
                Log::error("All image drivers failed after {$attempt} attempts.");
                return $this->generatePlaceholder($outputFilename);
            }
        }
        
        return $this->generatePlaceholder($outputFilename);
    }

    protected function driver($name)
    {
        switch ($name) {
            case 'openai':
                return $this->chatGptService;
            case 'replicate':
                return $this->replicateService;
            case 'pollinations':
                return $this->pollinationsService;
            case 'gemini':
            default:
                return $this->geminiService;
        }
    }

    protected function generatePlaceholder($outputFilename)
    {
        // Create a simple blank image or copy a default
        $content = "Placeholder Image Content";
        Storage::disk('public')->put($outputFilename, $content);
        return Storage::disk('public')->path($outputFilename);
    }
}
