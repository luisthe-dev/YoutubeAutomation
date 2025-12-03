<?php

namespace App\Http\Services\VoiceProvider;

use App\Http\Interfaces\AudioGeneratorInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GroqVoiceService implements AudioGeneratorInterface
{
    protected $apiKey;
    protected $model;
    protected $voice;
    protected $baseUrl = 'https://api.groq.com/openai/v1/audio/speech';

    public function __construct()
    {
        $this->apiKey = env('GROQ_API_KEY');
        $this->model = env('GROQ_AUDIO_MODEL', 'playai-tts'); 
        $this->voice = env('GROQ_VOICE_ID', 'Briggs-PlayAI');
    }

    public function generate(string $text, string $outputFilename): string
    {
        if (!$this->apiKey) {
            throw new \Exception("GROQ_API_KEY is not set.");
        }

        Log::info("Generating audio with Groq model: {$this->model}");

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl, [
            'model' => $this->model,
            'input' => $text,
            'voice' => $this->voice,
        ]);

        if ($response->failed()) {
            throw new \Exception("Groq Audio API failed: " . $response->body());
        }

        $audioContent = $response->body();
        file_put_contents($outputFilename, $audioContent);

        Log::info("Groq audio saved to: {$outputFilename}");

        return $outputFilename;
    }
}
