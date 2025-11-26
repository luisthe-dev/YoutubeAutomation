<?php

return [
    'min_length_minutes' => env('VIDEO_MIN_LENGTH_MINUTES', 1),
    'max_length_minutes' => env('VIDEO_MAX_LENGTH_MINUTES', 3),
    'scene_count' => env('SCENE_COUNT', 10),
    'scene_duration_ms' => env('SCENE_DURATION_MS', 10000),
];
