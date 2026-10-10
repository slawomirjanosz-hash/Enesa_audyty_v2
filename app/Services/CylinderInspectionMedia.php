<?php

namespace App\Services;

use App\Models\CylinderInspection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CylinderInspectionMedia
{
    public function validate(Request $request): void
    {
        $request->validate([
            'inspection_video' => ['nullable', 'file', 'max:'.config('cylinders.video_max_kb'), 'mimetypes:video/mp4,video/quicktime', 'extensions:mp4,mov'],
            'inspection_photos' => ['nullable', 'array', 'max:'.config('cylinders.photos_per_request')],
            'inspection_photos.*' => ['required', 'image', 'mimes:jpg,jpeg,png', 'extensions:jpg,jpeg,png', 'max:'.config('cylinders.photo_max_kb'), 'dimensions:max_width=6000,max_height=6000'],
        ]);
    }

    // Called inside the cylinder lock and database transaction; caller removes paths on rollback.
    public function store(Request $request, CylinderInspection $inspection, array &$paths): void
    {
        if ($file = $request->file('inspection_video')) {
            if ($inspection->videos()->exists()) {
                throw ValidationException::withMessages(['inspection_video' => 'Ten przegląd ma już film. Poprzedni plik nie został zastąpiony.']);
            }
            $path = $file->store('cylinder-videos/'.$inspection->cylinder_id, 'local');
            abort_unless($path, 500, 'Nie udało się zapisać filmu.');
            $paths[] = $path;
            $inspection->videos()->create(['cylinder_id' => $inspection->cylinder_id, 'title' => 'Przegląd #'.$inspection->id,
                'stored_path' => $path, 'mime_type' => $file->getMimeType(), 'size' => $file->getSize(), 'storage_owner_id' => $request->user()->id]);
        }
        foreach ($request->file('inspection_photos', []) as $index => $file) {
            try {
                $images = app(CylinderPhotoRenderer::class)->render($file);
            } catch (ValidationException $exception) {
                throw ValidationException::withMessages(['inspection_photos.'.$index => $exception->validator->errors()->first()]);
            }
            $base = 'cylinder-photos/'.$inspection->cylinder_id.'/'.Str::uuid();
            foreach (['image' => '.jpg', 'thumbnail' => '-thumb.jpg'] as $key => $suffix) {
                $paths[] = $base.$suffix;
                abort_unless(Storage::disk('local')->put($base.$suffix, $images[$key]), 500, 'Nie udało się zapisać zdjęcia.');
            }
            $inspection->photos()->create(['cylinder_id' => $inspection->cylinder_id, 'stored_path' => $base.'.jpg',
                'thumbnail_path' => $base.'-thumb.jpg', 'size' => strlen($images['image']) + strlen($images['thumbnail']), 'storage_owner_id' => $request->user()->id]);
        }
    }
}
