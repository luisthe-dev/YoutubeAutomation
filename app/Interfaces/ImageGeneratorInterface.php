<?php

namespace App\Interfaces;

interface ImageGeneratorInterface
{
    /**
     * Generate an image from a prompt.
     *
     * @param string $prompt
     * @param string $outputFilename relative path in public disk
     * @return string Absolute path to the saved image file
     */
    public function generate(string $prompt, string $outputFilename): string;
}
