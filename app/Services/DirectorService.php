<?php

namespace App\Services;

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
     * Request a video script blueprint.
     *
     * @param string $topic
     * @param string|null $title
     * @return array
     */
    public function createBlueprint(string $topic, string $title): array
    {
        $minLength = config('video.min_length_minutes', 1);
        $maxLength = config('video.max_length_minutes', 3);
        $sceneCount = config('video.scene_count', 10);
        $sceneDuration = config('video.scene_duration_ms', 10000);

        $prompt = "Create a JSON blueprint for a short educational video about '{$topic}' with the title '{$title}'. 
        
        CONSTRAINTS:
        - Total Video Length: Between {$minLength} and {$maxLength} minutes.
        - Scene Count: Approximately {$sceneCount} scenes.
        - Scene Duration: Target approximately {$sceneDuration} milliseconds per scene (adjust narration length accordingly).

        The JSON MUST follow this exact structure:
        {
            \"title\": \"Video Title\",
            \"scenes\": [
                {
                    \"narration\": \"Narration text for this specific scene\",
                    \"image_prompt\": \"A detailed description of the visual image for this scene\",
                    \"description\": \"Explanation of the visual\"
                }
            ]
        }
        IMPORTANT: 
        1. Visual Style: Choose a visual style that BEST fits the topic and mood. 
           - For history/art topics: Use 'Digital Oil Painting' or 'Classic Art' style.
           - For tech/future topics: Use 'Hyper-realistic Sci-Fi', 'Cyberpunk', or 'Modern High-Tech' style.
           - For general topics: Use 'Cinematic Photorealism' or 'High-End Editorial Photography'.
           - MAINTAIN CONSISTENCY: Use the SAME art style for all scenes in the video.
        2. Atmosphere: Cinematic, dramatic lighting, high contrast.
        3. Image Prompts: Write highly detailed prompts for an AI image generator (Flux/Midjourney style). 
           - Explicitly state the chosen style at the start of every prompt (e.g., \"A hyper-realistic sci-fi render of...\").
           - Include quality keywords: \"4k\", \"8k\", \"highly detailed\", \"cinematic lighting\", \"masterpiece\".
        4. Content: The images should represent the narration metaphorically or literally.
        5. Do NOT include \"manim_code\". We will generate images and animate them later.";

        return $this->textService->generateJson($prompt);
    }
}
