<?php

namespace App\Http\Services\TextProvider;

use App\Http\Interfaces\TextGeneratorInterface;
use Gemini\Laravel\Facades\Gemini;
use Illuminate\Support\Facades\Log;

class GeminiTextService implements TextGeneratorInterface
{
    protected $model;

    public function __construct()
    {
        // Use the requested Gemini model from config
        $this->model = config('gemini.model'); 
    }

    public function generate(string $prompt): string
    {
        Log::info("Generating text with Gemini model: {$this->model}");
        
        $apiKey = config('gemini.api_key');
        $client = \Gemini::factory()
            ->withApiKey($apiKey)
            ->withHttpClient(new \GuzzleHttp\Client(['timeout' => 120]))
            ->make();

        $result = $client->generativeModel(model: $this->model)->generateContent($prompt);
        return $result->text();
    }

    public function generateJson(string $prompt): array
    {
        Log::info("Generating JSON with Gemini model: {$this->model}");

        // For JSON, we can append an instruction or use specific generation config if supported
        // Gemini Pro often handles "Return JSON" prompts well.
        $jsonPrompt = $prompt . "\n\nReturn the result as a valid JSON object.";
        
        $apiKey = config('gemini.api_key');
        $client = \Gemini::factory()
            ->withApiKey($apiKey)
            ->withHttpClient(new \GuzzleHttp\Client(['timeout' => 120]))
            ->make();
        
        $result = $client->generativeModel(model: $this->model)->generateContent($jsonPrompt);
        $text = $result->text();

        // Extract JSON from markdown code blocks if present
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $text, $matches)) {
            $text = $matches[1];
        }
        
        $data = json_decode($text, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error("Failed to decode JSON from Gemini: " . json_last_error_msg());
            Log::debug("Raw response: " . $text);
            throw new \Exception("Failed to decode JSON from Gemini response.");
        }

        return $data;
    }
}
