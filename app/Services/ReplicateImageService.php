<?php

namespace App\Services;

use App\Interfaces\ImageGeneratorInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ReplicateImageService implements ImageGeneratorInterface
{
    protected $apiToken;
    // Flux 1.1 Pro (SOTA)
    protected $model = "black-forest-labs/flux-1.1-pro"; 

    public function __construct()
    {
        $this->apiToken = env('REPLICATE_API_TOKEN');
    }

    public function generate(string $prompt, string $outputFilename): string
    {
        if (!$this->apiToken) {
            throw new \Exception("REPLICATE_API_TOKEN not set");
        }

        $url = "https://api.replicate.com/v1/models/{$this->model}/predictions";

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiToken}",
            'Content-Type' => 'application/json',
            'Prefer' => 'wait' // Request to wait for the prediction
        ])->post($url, [
            'input' => [
                'prompt' => $prompt,
                'aspect_ratio' => '16:9',
                'output_format' => 'png',
                'safety_tolerance' => 5 // Allow more creative freedom
            ],
        ]);

        if ($response->failed()) {
            throw new \Exception("Replicate API failed: " . $response->body());
        }

        $result = $response->json();

        // Check if completed immediately (due to Prefer: wait)
        if ($result['status'] === 'succeeded') {
            $imageUrl = $result['output']; // Flux Pro returns a string URL, not array
        } else {
            // Need to poll
            $getUrl = $result['urls']['get'];
            $imageUrl = $this->pollPrediction($getUrl);
        }

        if (!$imageUrl) {
             throw new \Exception("No image URL returned from Replicate.");
        }

        // Download the image
        $imageContent = file_get_contents($imageUrl);
        if ($imageContent === false) {
            throw new \Exception("Failed to download image from Replicate: $imageUrl");
        }

        Storage::disk('public')->put($outputFilename, $imageContent);

        return Storage::disk('public')->path($outputFilename);
    }

    protected function pollPrediction($url)
    {
        $maxAttempts = 60; // Wait up to 60 seconds
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            sleep(1);
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiToken}",
            ])->get($url);

            if ($response->failed()) {
                throw new \Exception("Replicate Polling failed: " . $response->body());
            }

            $data = $response->json();

            if ($data['status'] === 'succeeded') {
                return $data['output'];
            }

            if ($data['status'] === 'failed' || $data['status'] === 'canceled') {
                throw new \Exception("Replicate prediction failed: " . ($data['error'] ?? 'Unknown error'));
            }

            $attempt++;
        }

        throw new \Exception("Replicate prediction timed out.");
    }
}
