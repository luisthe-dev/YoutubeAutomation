<?php

namespace App\Interfaces;

interface AudioGeneratorInterface
{
    /**
     * Generate audio from text.
     *
     * @param string $text
     * @param string $outputFilename relative path in public disk
     * @return string Absolute path to the saved audio file
     */
    public function generate(string $text, string $outputFilename): string;
}
