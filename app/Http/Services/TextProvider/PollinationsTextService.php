<?php

namespace App\Http\Services\TextProvider;

use App\Http\Interfaces\TextGeneratorInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PollinationsTextService implements TextGeneratorInterface
{
    protected $baseUrl = 'https://text.pollinations.ai/openai'; // OpenAI-compatible endpoint

    public function generate(string $prompt): string
    {
        Log::info("Generating text with Pollinations...");

        $apiKey = env('POLLINATIONS_API_KEY');
        $headers = [
            'Content-Type' => 'application/json',
        ];

        if ($apiKey) {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
        }

        $response = Http::withHeaders($headers)->post($this->baseUrl . '/chat/completions', [
            'model' => 'openai', // Standard model alias
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        if ($response->failed()) {
            throw new \Exception("Pollinations Text API failed: " . $response->body());
        }

        return $response->json('choices.0.message.content');
    }

    public function generateJson(string $prompt): array
    {
        Log::info("Generating JSON with Pollinations...");

        $jsonPrompt = $prompt . "\n\nReturn the result as a valid JSON object. Do not include any markdown formatting.";

        $apiKey = env('POLLINATIONS_API_KEY');
        $headers = [
            'Content-Type' => 'application/json',
        ];

        if ($apiKey) {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
        }

        $response = Http::withHeaders($headers)->post($this->baseUrl . '/chat/completions', [
            'model' => 'openai',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant designed to output JSON.'],
                ['role' => 'user', 'content' => $jsonPrompt],
            ],
            'response_format' => ['type' => 'json_object'],
        ]);

        if ($response->failed()) {
            throw new \Exception("Pollinations Text API failed: " . $response->body());
        }

        $text = $response->json('choices.0.message.content');
        
        // Clean up markdown code blocks if present
        $text = preg_replace('/^```json\s*|\s*```$/', '', $text);

        $data = json_decode($text, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error("Failed to decode JSON from Pollinations: " . json_last_error_msg());
            Log::debug("Raw response: " . $text);
            throw new \Exception("Failed to decode JSON from Pollinations response.");
        }

        return $data;
    }
}
