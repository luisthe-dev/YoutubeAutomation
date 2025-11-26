<?php

namespace App\Services;

use App\Interfaces\ImageGeneratorInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ChatGptImageService implements ImageGeneratorInterface
{
    protected $apiKey;

    public function __construct()
    {
        $this->apiKey = env('OPENAI_API_KEY');
    }

    public function generate(string $prompt, string $outputFilename): string
    {
        if (!$this->apiKey) {
            throw new \Exception("OPENAI_API_KEY not set");
        }

        $url = "https://api.openai.com/v1/images/generations";

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
        ])->post($url, [
            'model' => 'dall-e-3',
            'prompt' => $prompt,
            'n' => 1,
            'size' => '1024x1024', // DALL-E 3 standard
            'response_format' => 'b64_json',
            'quality' => 'hd',
            'style' => 'vivid'
        ]);

        if ($response->failed()) {
            throw new \Exception("OpenAI DALL-E API failed: " . $response->body());
        }

        $result = $response->json();

        if (!isset($result['data'][0]['b64_json'])) {
            throw new \Exception("No image returned from OpenAI.");
        }

        $base64Image = $result['data'][0]['b64_json'];
        $imageContent = base64_decode($base64Image);
        
        Storage::disk('public')->put($outputFilename, $imageContent);

        return Storage::disk('public')->path($outputFilename);
    }
}
