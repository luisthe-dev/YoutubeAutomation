<?php

namespace App\Http\Services;

use App\Http\Interfaces\TextGeneratorInterface;
use App\Http\Services\TextProvider\ChatGptTextService;
use App\Http\Services\TextProvider\GeminiTextService;
use App\Http\Services\TextProvider\GroqTextService;
use App\Http\Services\TextProvider\PollinationsTextService;
use Illuminate\Support\Facades\Log;

class TextService implements TextGeneratorInterface
{
    protected $geminiService;
    protected $pollinationsService;
    protected $chatGptService;
    protected $groqService;
    protected $defaultDriver;
    protected $fallbackDriver;

    public function __construct(?string $preferredDriver = null, ?string $fallbackDriver = null)
    {
        $this->geminiService = new GeminiTextService();
        $this->pollinationsService = new PollinationsTextService();
        $this->chatGptService = new ChatGptTextService();
        $this->groqService = new GroqTextService();
        
        $this->defaultDriver = $preferredDriver ?? env('TEXT_DRIVER', 'gemini');
        $this->fallbackDriver = $fallbackDriver;
    }

    public function generate(string $prompt): string
    {
        try {
            return $this->driver($this->defaultDriver)->generate($prompt);
        } catch (\Exception $e) {
            Log::warning("Primary text driver ({$this->defaultDriver}) failed: " . $e->getMessage());
            return $this->fallback($prompt, 'generate');
        }
    }

    public function generateJson(string $prompt): array
    {
        try {
            return $this->driver($this->defaultDriver)->generateJson($prompt);
        } catch (\Exception $e) {
            Log::warning("Primary text driver ({$this->defaultDriver}) failed: " . $e->getMessage());
            return $this->fallback($prompt, 'generateJson');
        }
    }

    protected function driver($name)
    {
        switch ($name) {
            case 'groq':
                return $this->groqService;
            case 'chatgpt':
            case 'openai':
                return $this->chatGptService;
            case 'pollinations':
                return $this->pollinationsService;
            case 'gemini':
            default:
                return $this->geminiService;
        }
    }

    protected function fallback($prompt, $method)
    {
        $drivers = ['gemini', 'pollinations', 'chatgpt', 'groq'];
        
        // If a specific fallback is provided, try it first
        if ($this->fallbackDriver) {
             array_unshift($drivers, $this->fallbackDriver);
             $drivers = array_unique($drivers);
        }
        
        $drivers = array_diff($drivers, [$this->defaultDriver]);

        foreach ($drivers as $driver) {
            try {
                Log::info("Falling back to text driver: {$driver}");
                return $this->driver($driver)->$method($prompt);
            } catch (\Exception $e) {
                Log::warning("Fallback text driver ({$driver}) failed: " . $e->getMessage());
            }
        }

        throw new \Exception("All text generation drivers failed.");
    }
}
