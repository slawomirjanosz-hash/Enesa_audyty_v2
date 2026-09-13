<?php

return [
    'office_binary' => env('PRESENTATION_OFFICE_BINARY', '/usr/bin/libreoffice'),
    'raster_binary' => env('PRESENTATION_RASTER_BINARY', '/usr/bin/pdftoppm'),
    'max_slides' => 60,
];
