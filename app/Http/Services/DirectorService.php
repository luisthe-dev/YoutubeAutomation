<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Log;

class DirectorService
{
    protected $textService;

    public function __construct(?string $textDriver = null, ?string $textFallback = null)
    {
        $this->textService = new TextService($textDriver, $textFallback);
    }

    /**
     * Generate 5 catchy titles for a topic.
     *
     * @param string $topic
     * @return array
     */
    public function generateTitles(string $topic): array
    {
        $prompt = "Generate 5 viral, click-worthy YouTube video titles for a video about \"{$topic}\".
        The titles should be catchy, use strong hooks, and appeal to a broad audience.
        Return ONLY a JSON array of strings, like this:
        [\"Title 1\", \"Title 2\", \"Title 3\", \"Title 4\", \"Title 5\"]";

        $titles = $this->textService->generateJson($prompt);

        if (empty($titles)) {
            throw new \Exception("Failed to generate titles.");
        }

        // Handle case where titles might be wrapped in a key like "titles"
        if (isset($titles['titles']) && is_array($titles['titles'])) {
            $titles = $titles['titles'];
        }

        // Ensure we have a flat array of strings
        $titles = array_filter($titles, 'is_string');

        if (empty($titles)) {
            throw new \Exception("No valid titles found in response.");
        }

        return $titles;
    }

    /**
     * Generate the script configuration (audio script, description).
     *
     * @param string $title
     * @param int|null $targetDuration
     * @return array
     */
    public function generateScriptConfig(string $title, ?int $targetDuration = null): array
    {
        $durationPrompt = $targetDuration 
            ? "The video should be approximately {$targetDuration} seconds long. At 150 words per minute, the script should be around " . ceil(($targetDuration / 60) * 150) . " words."
            : "The video should be concise and engaging.";

        $prompt = <<<EOT
You are a YouTube content strategist. Create a configuration for a video about: "{$title}".
{$durationPrompt}

Return a JSON object with the following keys:
- "youtube_description": A catchy description for the video.
- "audio_script": The full, engaging narration script for the video. It should be written for a single narrator.
- "keywords": An array of 5-10 relevant keywords.

Ensure the "audio_script" is engaging, conversational, and fits the target duration.
EOT;

        $result = $this->textService->generateJson($prompt);
        Log::info("generateScriptConfig result: " . json_encode($result));
        return $result;
    }

    /**
     * Generate the detailed video script based on the prompt.
     *
     * @param string $prompt
     * @return string
     */
    public function generateVideoScript(string $prompt): string
    {
        // This method might be deprecated or unused now, but keeping it for compatibility if needed.
        return $this->textService->generate($prompt);
    }

    /**
     * Generate scenes from the audio script.
     *
     * @param string $audioScript
     * @return array
     */
    public function generateScenes(string $audioScript): array
    {
        $prompt = <<<EOT
You are a visual director. Based on the following audio script, break it down into a sequence of visual scenes.

Audio Script:
"{$audioScript}"

Return a JSON array of objects, where each object represents a scene and has:
- "image_prompt": A detailed image generation prompt for this scene. 
  - **STYLE CONSTRAINT**: All images MUST be 2D or 2.5D vector art, flat design, or cel-shaded illustrations. NO photorealistic images. NO text in images.
  - Describe the visual content clearly (characters, setting, action).
- "duration": The duration of this scene in seconds (maximum 5 seconds).
- "narration_segment": The part of the audio script that corresponds to this scene (optional, for reference).

Ensure the scenes cover the entire script flow. If a segment is long, break it into multiple scenes.
EOT;
        
        $response = $this->textService->generateJson($prompt);
        // Handle if response is wrapped in 'scenes' key or is the array directly
        return isset($response['scenes']) ? $response['scenes'] : $response;
    }
}
