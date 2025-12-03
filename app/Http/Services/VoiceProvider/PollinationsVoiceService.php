<?php

namespace App\Http\Services\VoiceProvider;

use App\Http\Interfaces\AudioGeneratorInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PollinationsVoiceService implements AudioGeneratorInterface
{
    /**
     * Generate audio from text.
     *
     * @param string $text
     * @param string $outputFilename relative path in public disk
     * @return string Absolute path to the saved audio file
     */
    public function generate(string $text, string $outputFilename): string
    {
        Log::info("Generating audio using Pollinations.ai (Chat Completions) for text: " . substr($text, 0, 50) . "...");

        $apiKey = env('POLLINATIONS_API_KEY');
        $voice = env('POLLINATIONS_VOICE', 'alloy');
        $url = "https://enter.pollinations.ai/api/generate/v1/chat/completions";

        $payload = [
            "model" => "openai-audio",
            "modalities" => ["text", "audio"],
            "audio" => [
                "voice" => $voice,
                "format" => "mp3"
            ],
            "messages" => [
                ["role" => "user", "content" => $text]
            ]
        ];

        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3',
        ];

        if ($apiKey) {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
        }

        Log::info("Pollinations Audio URL: {$url}");

        $response = Http::withHeaders($headers)->post($url, $payload);

        if ($response->successful()) {
            $data = $response->json();
            
            if (isset($data['choices'][0]['message']['audio']['data'])) {
                $audioContent = base64_decode($data['choices'][0]['message']['audio']['data']);
                Storage::disk('public')->put($outputFilename, $audioContent);
                
                $path = Storage::disk('public')->path($outputFilename);
                Log::info("Pollinations.ai audio saved to: {$path}");
                
                return $path;
            }
            
            Log::error("Pollinations response missing audio data: " . json_encode($data));
            throw new \Exception("Pollinations.ai response missing audio data.");
        }

        Log::error("Pollinations.ai failed to generate audio: " . $response->status() . " Body: " . $response->body());
        throw new \Exception("Pollinations.ai audio request failed with status: " . $response->status());
    }
}
