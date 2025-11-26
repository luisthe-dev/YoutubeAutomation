<?php

namespace App\Services;

use App\Interfaces\ImageGeneratorInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PollinationsImageService implements ImageGeneratorInterface
{
    public function generate(string $prompt, string $outputFilename): string
    {
        Log::info("Generating image using Pollinations.ai for prompt: " . substr($prompt, 0, 50) . "...");

        // Pollinations.ai uses a simple GET request with the prompt in the URL
        // URL encode the prompt to ensure it's safe for the URL
        $encodedPrompt = rawurlencode($prompt);
        
        $width = env('POLLINATIONS_WIDTH', 1280);
        $height = env('POLLINATIONS_HEIGHT', 720);
        $model = env('POLLINATIONS_MODEL', 'flux'); // Default to 'flux' for best quality

        $url = "https://image.pollinations.ai/prompt/{$encodedPrompt}?model={$model}&width={$width}&height={$height}";
        
        Log::info("Pollinations URL: {$url}");

        $apiKey = env('POLLINATIONS_API_KEY');
        $headers = [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3',
        ];

        if ($apiKey) {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
            // Also append key to URL as some endpoints might prefer it, but header is standard
            // $url .= "&key={$apiKey}"; 
        }

        $response = Http::timeout(60)->withHeaders($headers)->get($url);

        if ($response->successful()) {
            $contentType = $response->header('Content-Type');
            if (strpos($contentType, 'image') === false) {
                Log::error("Pollinations returned non-image content: " . $contentType);
                throw new \Exception("Pollinations returned invalid content type: " . $contentType);
            }

            $imageContent = $response->body();
            Storage::disk('public')->put($outputFilename, $imageContent);
            
            $path = Storage::disk('public')->path($outputFilename);
            Log::info("Pollinations.ai image saved to: {$path}");
            
            return $path;
        }

        Log::error("Pollinations.ai failed to generate image: " . $response->status());
        throw new \Exception("Pollinations.ai request failed with status: " . $response->status());
    }
}
