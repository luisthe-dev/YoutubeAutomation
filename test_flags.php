<?php

use App\Services\TextService;
use App\Services\VoiceService;
use App\Services\ImageService;
use Illuminate\Support\Facades\Config;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Testing Provider Flags...\n";

// Test TextService
$textService = new TextService('pollinations', 'gemini');
$reflection = new ReflectionClass($textService);
$defaultDriver = $reflection->getProperty('defaultDriver');
$defaultDriver->setAccessible(true);
$fallbackDriver = $reflection->getProperty('fallbackDriver');
$fallbackDriver->setAccessible(true);

echo "Text Default: " . $defaultDriver->getValue($textService) . " (Expected: pollinations)\n";
echo "Text Fallback: " . $fallbackDriver->getValue($textService) . " (Expected: gemini)\n";

// Test VoiceService
$voiceService = new VoiceService('groq', 'elevenlabs');
$reflection = new ReflectionClass($voiceService);
$defaultDriver = $reflection->getProperty('defaultDriver');
$defaultDriver->setAccessible(true);
$fallbackDriver = $reflection->getProperty('fallbackDriver');
$fallbackDriver->setAccessible(true);

echo "Voice Default: " . $defaultDriver->getValue($voiceService) . " (Expected: groq)\n";
echo "Voice Fallback: " . $fallbackDriver->getValue($voiceService) . " (Expected: elevenlabs)\n";

// Test ImageService
$imageService = new ImageService(true, 'pollinations', 'replicate');
$reflection = new ReflectionClass($imageService);
$defaultDriver = $reflection->getProperty('defaultDriver');
$defaultDriver->setAccessible(true);
$fallbackDriver = $reflection->getProperty('fallbackDriver');
$fallbackDriver->setAccessible(true);

echo "Image Default: " . $defaultDriver->getValue($imageService) . " (Expected: pollinations)\n";
echo "Image Fallback: " . $fallbackDriver->getValue($imageService) . " (Expected: replicate)\n";

if (
    $defaultDriver->getValue($textService) === 'pollinations' &&
    $fallbackDriver->getValue($textService) === 'gemini' &&
    $defaultDriver->getValue($voiceService) === 'groq' &&
    $fallbackDriver->getValue($voiceService) === 'elevenlabs' &&
    $defaultDriver->getValue($imageService) === 'pollinations' &&
    $fallbackDriver->getValue($imageService) === 'replicate'
) {
    echo "SUCCESS: All services accepted preferences correctly.\n";
} else {
    echo "FAILURE: Preferences mismatch.\n";
}
