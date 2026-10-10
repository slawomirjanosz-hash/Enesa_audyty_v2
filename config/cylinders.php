<?php

return [
    'video_max_kb' => (int) env('CYLINDER_VIDEO_MAX_KB', 51200),
    'photo_max_kb' => (int) env('CYLINDER_PHOTO_MAX_KB', 8192),
    'photos_per_request' => 5,
];
