<?php

namespace App\Http\Interfaces;

interface TextGeneratorInterface
{
    /**
     * Generate text from a prompt.
     *
     * @param string $prompt
     * @return string
     */
    public function generate(string $prompt): string;

    /**
     * Generate structured JSON data from a prompt.
     *
     * @param string $prompt
     * @return array
     */
    public function generateJson(string $prompt): array;
}
