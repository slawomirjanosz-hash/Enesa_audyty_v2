<?php

namespace App\Http\Controllers;

use App\Models\CompanySettings;
use Illuminate\Http\Response;

class BrandingController extends Controller
{
    public function logo(): Response
    {
        $settings = CompanySettings::query()->first();

        abort_unless($settings?->logo_data, 404);

        $binary = base64_decode($settings->logo_data, true);
        abort_if($binary === false, 404);

        return response($binary, 200, [
            'Content-Type' => $settings->logo_mime ?: 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
