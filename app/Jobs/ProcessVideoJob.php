<?php

namespace App\Jobs;

use App\Services\DirectorService;
use App\Services\ImageService;
use App\Services\VoiceService;
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
    protected $jobId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $topic, ?string $title = null, bool $useBackups = true, array $preferences = [], ?string $jobId = null)
    {
        $this->topic = $topic;
        $this->title = $title;
        $this->useBackups = $useBackups;
        $this->preferences = $preferences;
        $this->jobId = $jobId ?? uniqid();
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
        // Apply preferences if set
        if (!empty($this->preferences['voice_driver'])) {
            config(['voice.driver' => $this->preferences['voice_driver']]);
            // Also set env for services that might read env directly (though they should use config)
            // But Config::set is safer for runtime.
            // Note: Services need to read from config or we need to set env.
            // Most services read env() directly in their constructors. 
            // We should update them to use config() or inject dependencies.
            // For now, let's try to set the env variable dynamically for this process.
            putenv("VOICE_DRIVER={$this->preferences['voice_driver']}");
        }
        if (!empty($this->preferences['image_driver'])) {
            putenv("IMAGE_DRIVER={$this->preferences['image_driver']}");
        }
        if (!empty($this->preferences['text_driver'])) {
            putenv("TEXT_DRIVER={$this->preferences['text_driver']}");
        }
        
        // Fallbacks (Secondary) - This requires Service logic update to respect a secondary preference
        // For now, we'll just log them or if possible, set a specific config for fallback.
        // The current Manager implementations (TextService, VoiceService) usually have a hardcoded fallback list or read from config.
        // We might need to update the Managers to accept a custom fallback list.
        
        $textDriver = $this->preferences['text_driver'] ?? null;
        $textFallback = $this->preferences['text_fallback'] ?? null;
        
        $director = new DirectorService($textDriver, $textFallback);
        $this->log("Starting video processing for topic: {$this->topic}");

        // 1. Get Blueprint from Gemini
        $blueprint = $director->createBlueprint($this->topic, $this->title);
        $jobId = $this->jobId;
        $basePath = "videos/{$jobId}";
        
        Storage::disk('public')->makeDirectory($basePath);
        
        // 2. Generate Audio and Images per Scene
        $voiceDriver = $this->preferences['voice_driver'] ?? null;
        $voiceFallback = $this->preferences['voice_fallback'] ?? null;
        $voiceService = new VoiceService($voiceDriver, $voiceFallback);
        
        $imageDriver = $this->preferences['image_driver'] ?? null;
        $imageFallback = $this->preferences['image_fallback'] ?? null;
        $imageService = new ImageService($this->useBackups, $imageDriver, $imageFallback);
        
        if (isset($blueprint['scenes']) && is_array($blueprint['scenes'])) {
            foreach ($blueprint['scenes'] as $index => &$scene) {
                // Audio
                if (!empty($scene['narration'])) {
                    try {
                        $this->log("Generating audio for scene {$index}...");
                        
                        $audioFilename = "{$basePath}/scene_{$index}.mp3";
                        $audioPath = $voiceService->generate($scene['narration'], $audioFilename);
                        $scene['audio_path'] = $audioPath;
                        $this->log("Audio for scene {$index} saved to {$audioPath}");
                    } catch (Exception $e) {
                        $this->log("Audio generation failed for scene {$index}: " . $e->getMessage(), 'error');
                    }
                }

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
        }

        // Save updated blueprint with audio paths
        $blueprintFilename = "{$basePath}/blueprint.json";
        Storage::disk('public')->put($blueprintFilename, json_encode($blueprint, JSON_PRETTY_PRINT));
        $blueprintPath = Storage::disk('public')->path($blueprintFilename);
        $this->log("Blueprint saved to {$blueprintPath}");

        // 3. Generate Thumbnail/Image (Flux)
        $imageService = new ImageService($this->useBackups, $imageDriver, $imageFallback);
        try {
            $this->log("Generating thumbnail...");
            $imageFilename = "{$basePath}/thumbnail.png";
            $prompt = "A cinematic youtube thumbnail for a video about: " . ($blueprint['title'] ?? $this->topic);
            $imagePath = $imageService->generate($prompt, $imageFilename);
            $this->log("Thumbnail saved to {$imagePath}");
        } catch (Exception $e) {
            $this->log("Image generation failed: " . $e->getMessage(), 'error');
        }

        // 4. Call Python Worker (Manim)
        // Assuming python_scripts/render_manim.py exists in root or specific path
        $scriptPath = base_path('python_scripts/render_manim.py');
        $command = ['python', $scriptPath, $blueprintPath];

        $process = new Process($command);
        $process->setTimeout(7200); // 2 hour timeout for rendering
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $this->log("Manim rendering completed: " . $process->getOutput());

        // 5. Finalize Video (Embed Thumbnail)
        $this->log("Finalizing video...");
        
        // Find the generated video file
        $videoFiles = Storage::disk('public')->allFiles($basePath);
        $generatedVideo = null;
        foreach ($videoFiles as $file) {
            if (str_ends_with($file, '.mp4') && str_contains($file, 'GeneratedScene')) {
                $generatedVideo = $file;
                break;
            }
        }

        if ($generatedVideo) {
            $inputVideoPath = Storage::disk('public')->path($generatedVideo);
            $thumbnailPath = Storage::disk('public')->path($imageFilename); // "{$basePath}/thumbnail.png"
            $finalVideoPath = Storage::disk('public')->path("{$basePath}/final_video.mp4");

            if (file_exists($thumbnailPath)) {
                $this->log("Embedding thumbnail into video...");
                // FFmpeg command to embed thumbnail
                // -i video -i image -map 0 -map 1 -c copy -disposition:v:1 attached_pic
                $ffmpegCmd = [
                    'ffmpeg',
                    '-y', // Overwrite output
                    '-i', $inputVideoPath,
                    '-i', $thumbnailPath,
                    '-map', '0',
                    '-map', '1',
                    '-c', 'copy',
                    '-disposition:v:1', 'attached_pic',
                    $finalVideoPath
                ];

                $process = new Process($ffmpegCmd);
                $process->run();

                if ($process->isSuccessful()) {
                    $this->log("Thumbnail embedded successfully. Final video at: {$finalVideoPath}");
                } else {
                    $this->log("Failed to embed thumbnail: " . $process->getErrorOutput(), 'error');
                    // Fallback: just copy the video
                    copy($inputVideoPath, $finalVideoPath);
                    $this->log("Copied video without thumbnail to: {$finalVideoPath}", 'warning');
                }
            } else {
                $this->log("Thumbnail not found. Copying video as is.", 'warning');
                copy($inputVideoPath, $finalVideoPath);
            }
        } else {
            $this->log("Could not find generated video file in {$basePath}", 'error');
        }
    }
}
