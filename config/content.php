<?php

$mediaMaxKilobytes = (int) env('CONTENT_MEDIA_MAX_KB', 262144);

return [
    'media' => [
        'max_kb' => $mediaMaxKilobytes,
        'max_bytes' => $mediaMaxKilobytes * 1024,
    ],
];
