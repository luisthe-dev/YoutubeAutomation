<?php

namespace App\Http\Services\TextProvider;

use App\Http\Interfaces\TextGeneratorInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GroqTextService implements TextGeneratorInterface
{
    protected $apiKey;
    protected $model;
    protected $baseUrl = 'https://api.groq.com/openai/v1/chat/completions';

    public function __construct()
    {
        $this->apiKey = env('GROQ_API_KEY');
        $this->model = env('GROQ_TEXT_MODEL', 'llama3-70b-8192');
    }

    public function generate(string $prompt): string
    {
        if (!$this->apiKey) {
            throw new \Exception("GROQ_API_KEY is not set.");
        }

        Log::info("Generating text with Groq model: {$this->model}");

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl, [
            'model' => $this->model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        if ($response->failed()) {
            throw new \Exception("Groq API failed: " . $response->body());
        }

        return $response->json('choices.0.message.content');
    }

    public function generateJson(string $prompt): array
    {
        if (!$this->apiKey) {
            throw new \Exception("GROQ_API_KEY is not set.");
        }

        Log::info("Generating JSON with Groq model: {$this->model}");

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl, [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant designed to output JSON.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'response_format' => ['type' => 'json_object'],
        ]);

        if ($response->failed()) {
            throw new \Exception("Groq API failed: " . $response->body());
        }

        $content = $response->json('choices.0.message.content');
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error("Failed to decode JSON from Groq: " . json_last_error_msg());
            throw new \Exception("Failed to decode JSON from Groq response.");
        }

        return $data;
    }
}
