<?php

namespace App\Services;

use App\Interfaces\AudioGeneratorInterface;

class VoiceService implements AudioGeneratorInterface
{
    protected $elevenLabsService;
    protected $pollinationsService;
    protected $groqService;
    protected $defaultDriver;
    protected $fallbackDriver;

    public function __construct(?string $preferredDriver = null, ?string $fallbackDriver = null)
    {
        $this->elevenLabsService = new ElevenLabsVoiceService();
        $this->pollinationsService = new PollinationsVoiceService();
        $this->groqService = new GroqVoiceService();
        
        $this->defaultDriver = $preferredDriver ?? env('VOICE_DRIVER', 'elevenlabs');
        $this->fallbackDriver = $fallbackDriver;
    }

    public function generate(string $text, string $outputFilename): string
    {
        try {
            return $this->driver($this->defaultDriver)->generate($text, $outputFilename);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Primary voice driver ({$this->defaultDriver}) failed: " . $e->getMessage());
            return $this->fallback($text, $outputFilename);
        }
    }

    protected function driver($name)
    {
        switch ($name) {
            case 'groq':
                return $this->groqService;
            case 'pollinations':
                return $this->pollinationsService;
            case 'elevenlabs':
            default:
                return $this->elevenLabsService;
        }
    }

    protected function fallback($text, $outputFilename)
    {
        $drivers = ['elevenlabs', 'pollinations', 'groq'];
        
        if ($this->fallbackDriver) {
             array_unshift($drivers, $this->fallbackDriver);
             $drivers = array_unique($drivers);
        }
        
        $drivers = array_diff($drivers, [$this->defaultDriver]);

        foreach ($drivers as $driver) {
            try {
                \Illuminate\Support\Facades\Log::info("Falling back to voice driver: {$driver}");
                return $this->driver($driver)->generate($text, $outputFilename);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning("Fallback voice driver ({$driver}) failed: " . $e->getMessage());
            }
        }

        throw new \Exception("All voice generation drivers failed.");
    }
}
