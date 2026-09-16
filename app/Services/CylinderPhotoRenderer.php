<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class CylinderPhotoRenderer
{
    public function render(UploadedFile $file): array
    {
        $info = @getimagesize($file->getRealPath());
        if (! $info || $info[0] * $info[1] > 12000000) {
            throw ValidationException::withMessages(['photo' => 'Zdjęcie może mieć maksymalnie 12 megapikseli.']);
        }
        $image = @imagecreatefromstring(file_get_contents($file->getRealPath()));
        if (! $image) {
            throw ValidationException::withMessages(['photo' => 'Nie można odczytać zdjęcia.']);
        }
        try {
            if ($file->getMimeType() === 'image/jpeg') {
                $exif = @exif_read_data($file->getRealPath());
                $orientation = (int) ($exif['Orientation'] ?? 1);
                if (in_array($orientation, [2, 4, 5, 7], true)) {
                    imageflip($image, IMG_FLIP_HORIZONTAL);
                }
                $angle = match ($orientation) {
                    3, 4 => 180, 5, 6 => -90, 7, 8 => 90, default => 0
                };
                if ($angle) {
                    $rotated = imagerotate($image, $angle, 0);
                    if ($rotated) {
                        imagedestroy($image);
                        $image = $rotated;
                    }
                }
            }

            return ['image' => $this->jpeg($image, 1600), 'thumbnail' => $this->jpeg($image, 96)];
        } finally {
            imagedestroy($image);
        }
    }

    private function jpeg(\GdImage $source, int $max): string
    {
        $ratio = min(1, $max / max(imagesx($source), imagesy($source)));
        $width = max(1, (int) round(imagesx($source) * $ratio));
        $height = max(1, (int) round(imagesy($source) * $ratio));
        $target = imagecreatetruecolor($width, $height);
        imagefill($target, 0, 0, imagecolorallocate($target, 255, 255, 255));
        imagecopyresampled($target, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));
        ob_start();
        try {
            imagejpeg($target, null, 85);

            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
            imagedestroy($target);
        }
    }
}
