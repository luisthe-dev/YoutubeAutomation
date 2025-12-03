<?php

namespace App\Jobs;

use App\Http\Services\DirectorService;
use App\Http\Services\ImageService;
use App\Http\Services\VoiceService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ProcessVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $topic;
    protected $title;
    protected $useBackups;
    protected $preferences;
    protected $duration;
    protected $manualRender;
    protected $jobId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $topic, ?string $title = null, bool $useBackups = true, array $preferences = [], ?string $jobId = null, ?int $duration = null, bool $manualRender = false)
    {
        $this->topic = $topic;
        $this->title = $title;
        $this->useBackups = $useBackups;
        $this->preferences = $preferences;
        $this->jobId = $jobId ?? uniqid();
        $this->duration = $duration;
        $this->manualRender = $manualRender;
    }

    protected function log($message, $level = 'info')
    {
        // Standard logging
        if ($level === 'error') {
            Log::error($message);
        } else {
            Log::info($message);
        }

        // Cache logging for frontend
        $key = "video_logs_{$this->jobId}";
        $logs = \Illuminate\Support\Facades\Cache::get($key, []);
        $logs[] = [
            'timestamp' => now()->toIso8601String(),
            'level' => $level,
            'message' => $message
        ];
        // Keep only last 100 logs to avoid size issues
        if (count($logs) > 100) {
            $logs = array_slice($logs, -100);
        }
        \Illuminate\Support\Facades\Cache::put($key, $logs, 3600); // Expire in 1 hour
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->log("Job Preferences: " . json_encode($this->preferences));

        // Apply preferences if set
        if (!empty($this->preferences['voice_driver'])) {
            config(['voice.driver' => $this->preferences['voice_driver']]);
            putenv("VOICE_DRIVER={$this->preferences['voice_driver']}");
        }
        if (!empty($this->preferences['image_driver'])) {
            putenv("IMAGE_DRIVER={$this->preferences['image_driver']}");
        }
        if (!empty($this->preferences['text_driver'])) {
            putenv("TEXT_DRIVER={$this->preferences['text_driver']}");
        }

        $textDriver = $this->preferences['text_driver'] ?? null;
        $textFallback = $this->preferences['text_fallback'] ?? null;
        
        $director = new DirectorService($textDriver, $textFallback);

        $voiceDriver = $this->preferences['voice_driver'] ?? null;
        $voiceFallback = $this->preferences['voice_fallback'] ?? null;
        $voiceService = new VoiceService($voiceDriver, $voiceFallback);

        $this->log("Starting video processing for topic: {$this->topic}");

        $jobId = $this->jobId;
        $basePath = "videos/{$jobId}";
        Storage::disk('public')->makeDirectory($basePath);

        // 1. Generate Script Config (Audio Script, Description)
        $this->log("Generating script configuration...");
        $scriptConfig = $director->generateScriptConfig($this->title ?? $this->topic, $this->duration);

        Storage::disk('public')->put("{$basePath}/script_config.json", json_encode($scriptConfig, JSON_PRETTY_PRINT));
        $this->log("Script configuration generated. {$basePath}");

        // 2. Generate Audio (We do this BEFORE scenes now, or parallel, but we need the script)
        $this->log("Generating narration audio...");
        $audioFilename = "{$basePath}/narration.mp3";
        try {
            $audioScript = $scriptConfig['audio_script'];
            $audioPath = $voiceService->generate($audioScript, $audioFilename);
            $this->log("Narration audio saved to {$audioPath}");
        } catch (Exception $e) {
            $this->log("Audio generation failed: " . $e->getMessage(), 'error');
            // Continue, but scenes might not match perfectly if we relied on audio timing (which we don't yet fully)
        }

        // 3. Generate Scenes (Structured list of images and durations, based on AUDIO SCRIPT)
        $this->log("Breaking script into scenes...");
        // We pass the audio script to generate scenes that match the narration flow
        $scenes = $director->generateScenes($scriptConfig['audio_script']);

        // Save scenes
        $blueprint = [
            'title' => $this->title ?? $this->topic,
            'description' => $scriptConfig['youtube_description'],
            'scenes' => $scenes
        ];
        Storage::disk('public')->put("{$basePath}/scenes.json", json_encode($blueprint, JSON_PRETTY_PRINT));
        $this->log("Scenes generated: " . count($scenes));

        // 4. Generate Images
        $imageDriver = $this->preferences['image_driver'] ?? null;
        $imageFallback = $this->preferences['image_fallback'] ?? null;
        $imageService = new ImageService($this->useBackups, $imageDriver, $imageFallback);

        foreach ($scenes as $index => &$scene) {
            // Image
            if (!empty($scene['image_prompt'])) {
                try {
                    $this->log("Generating image for scene {$index}...");
                    $imageFilename = "{$basePath}/scene_{$index}.png";
                    $imagePath = $imageService->generate($scene['image_prompt'], $imageFilename);
                    $scene['image_path'] = $imagePath;
                    $this->log("Image for scene {$index} saved to {$imagePath}");
                } catch (Exception $e) {
                    $this->log("Image generation failed for scene {$index}: " . $e->getMessage(), 'error');
                }
            }
        }

        // Update blueprint with image paths
        $blueprint['scenes'] = $scenes;
        Storage::disk('public')->put("{$basePath}/scenes.json", json_encode($blueprint, JSON_PRETTY_PRINT));
        $blueprintPath = Storage::disk('public')->path("{$basePath}/scenes.json");

        // 5. Generate Thumbnail
        try {
            $this->log("Generating thumbnail...");
            $thumbnailFilename = "{$basePath}/thumbnail.png";
            $prompt = "A captivating and engaging youtube thumbnail for a video about: " . ($this->title ?? $this->topic) . ". Style: 2D illustration, flat design, high quality, vibrant colors.";
            $imagePath = $imageService->generate($prompt, $thumbnailFilename);
            $this->log("Thumbnail saved to {$imagePath}");
        } catch (Exception $e) {
            $this->log("Thumbnail generation failed: " . $e->getMessage(), 'error');
        }

        // 6. Call Python Worker (Manim)
        $scriptPath = base_path('python_scripts/render_manim.py');
        if (!file_exists($scriptPath)) {
            throw new \Exception("Python script not found at: {$scriptPath}");
        }

        $finalVideoPath = Storage::disk('public')->path("{$basePath}/final_video.mp4");
        $audioPathAbs = Storage::disk('public')->path($audioFilename);
        $thumbnailPathAbs = Storage::disk('public')->path($thumbnailFilename);

        // Normalize paths for the OS
        $scriptPath = str_replace('/', DIRECTORY_SEPARATOR, $scriptPath);
        $blueprintPath = str_replace('/', DIRECTORY_SEPARATOR, $blueprintPath);
        $finalVideoPath = str_replace('/', DIRECTORY_SEPARATOR, $finalVideoPath);
        $audioPathAbs = str_replace('/', DIRECTORY_SEPARATOR, $audioPathAbs);
        $thumbnailPathAbs = str_replace('/', DIRECTORY_SEPARATOR, $thumbnailPathAbs);

        // Arguments: blueprint_path output_path audio_path thumbnail_path
        $command = ['python', $scriptPath, $blueprintPath, $finalVideoPath, $audioPathAbs, $thumbnailPathAbs];
        $commandStr = implode(' ', $command);

        if ($this->manualRender) {
            $this->log("MANUAL RENDER MODE: Skipping Python execution.");
            $this->log("Run the following command manually:");
            $this->log($commandStr);

            // Print to console if running in CLI
            if (app()->runningInConsole()) {
                echo "\n\nMANUAL RENDER COMMAND:\n";
                echo $commandStr . "\n\n";
            }
            return;
        }

        $this->log("Starting video rendering with Python script...");
        $this->log("Command: " . $commandStr); // Log command before running
        $process = new Process($command);
        $process->setTimeout(18000); // 5 hours
        $process->run();

        if (!$process->isSuccessful()) {
            $this->log("Video rendering failed: " . $process->getErrorOutput(), 'error');
            throw new ProcessFailedException($process);
        }

        $this->log("Video rendering completed: " . $process->getOutput());
        $this->log("Final video saved to: {$finalVideoPath}");
    }
}
