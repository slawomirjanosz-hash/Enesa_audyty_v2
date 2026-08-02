<?php

namespace App\Http\Controllers;

use App\Models\CompanySettings;

class LandingPageController extends Controller
{
    public function show()
    {
        $settings = CompanySettings::query()->first();
        $mode = $settings?->welcome_page_mode ?? 'audit';

        if ($mode === 'login_only') {
            return redirect()->route('login');
        }

        return view($mode === 'general' ? 'welcome-general' : 'welcome');
    }
}
