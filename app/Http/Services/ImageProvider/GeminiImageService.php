<?php

namespace App\Http\Services\ImageProvider;

use App\Http\Interfaces\ImageGeneratorInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class GeminiImageService implements ImageGeneratorInterface
{
    protected $apiKey;

    public function __construct()
    {
        $this->apiKey = env('GEMINI_API_KEY');
    }

    public function generate(string $prompt, string $outputFilename): string
    {
        if (!$this->apiKey) {
            throw new \Exception("GEMINI_API_KEY not set");
        }

        // Using Gemini API (Imagen 3)
        $url = "https://generativelanguage.googleapis.com/v1beta/models/imagen-3.0-generate-001:predict?key={$this->apiKey}";

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post($url, [
            'instances' => [
                [
                    'prompt' => $prompt,
                ]
            ],
            'parameters' => [
                'sampleCount' => 1,
                'aspectRatio' => '16:9',
            ],
        ]);

        if ($response->failed()) {
            throw new \Exception("Gemini Imagen API failed: " . $response->body());
        }

        $result = $response->json();

        // Gemini 2.5 Flash Image returns inline data in candidates
        // Structure: candidates[0].content.parts[0].inlineData.data
        // OR sometimes it might be different for this specific model, let's handle the standard generateContent image response.
        // Actually, for gemini-2.5-flash-image, it returns base64 in inlineData.
        
        $base64Image = null;
        if (isset($result['candidates'][0]['content']['parts'][0]['inline_data']['data'])) {
             $base64Image = $result['candidates'][0]['content']['parts'][0]['inline_data']['data'];
        } elseif (isset($result['candidates'][0]['content']['parts'][0]['inlineData']['data'])) {
             $base64Image = $result['candidates'][0]['content']['parts'][0]['inlineData']['data'];
        }

        if (!$base64Image) {
             // Fallback to check for predictions if it was using the predict endpoint structure (unlikely for generateContent but good to be safe)
             if (isset($result['predictions'][0]['bytesBase64Encoded'])) {
                 $base64Image = $result['predictions'][0]['bytesBase64Encoded'];
             } else {
                 throw new \Exception("No image returned from Gemini. Response: " . json_encode($result));
             }
        }

        $imageContent = base64_decode($base64Image);
        Storage::disk('public')->put($outputFilename, $imageContent);

        return Storage::disk('public')->path($outputFilename);
    }
}
