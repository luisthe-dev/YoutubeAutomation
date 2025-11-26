<?php

namespace App\Services;

use App\Interfaces\AudioGeneratorInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ElevenLabsVoiceService implements AudioGeneratorInterface
{
    protected $apiKey;
    protected $voiceId;

    public function __construct()
    {
        $this->apiKey = env('ELEVENLABS_API_KEY');
        // Default voice ID (e.g., "Rachel" or similar public voice)
        // You can find voice IDs in ElevenLabs dashboard
        $this->voiceId = env('ELEVENLABS_VOICE_ID', '21m00Tcm4TlvDq8ikWAM'); 
    }

    /**
     * Generate audio from text.
     *
     * @param string $text
     * @param string $outputFilename relative path in public disk
     * @return string Absolute path to the saved audio file
     */
    public function generate(string $text, string $outputFilename): string
    {
        if (!$this->apiKey) {
            throw new \Exception("ELEVENLABS_API_KEY is not set.");
        }

        $response = Http::withHeaders([
            'xi-api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post("https://api.elevenlabs.io/v1/text-to-speech/{$this->voiceId}", [
            'text' => $text,
            'model_id' => 'eleven_multilingual_v2',
            'voice_settings' => [
                'stability' => 0.5,
                'similarity_boost' => 0.5,
            ],
        ]);

        if ($response->failed()) {
            throw new \Exception("ElevenLabs API failed: " . $response->body());
        }

        Storage::disk('public')->put($outputFilename, $response->body());

        return Storage::disk('public')->path($outputFilename);
    }
}
