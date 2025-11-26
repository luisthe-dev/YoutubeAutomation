<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\VoiceService;
use Illuminate\Support\Facades\Log;

try {
    echo "Testing VoiceService...\n";
    $service = new VoiceService();
    $path = $service->generate("This is a test audio generation.", "test_audio.mp3");
    echo "Success! Audio saved to: " . $path . "\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
