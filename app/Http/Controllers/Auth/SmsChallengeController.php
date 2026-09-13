<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\CompanySettings;
use App\Services\ActivityLogService;
use App\Services\SuperadminSmsService;
use Illuminate\Http\Request;

class SmsChallengeController extends Controller
{
    public function show(Request $request, SuperadminSmsService $sms)
    {
        abort_unless($sms->required($request->user()), 403);

        return view('auth.sms');
    }

    public function send(Request $request, SuperadminSmsService $sms)
    {
        abort_unless($sms->required($request->user()), 403);
        $sms->send($request);

        return redirect()->route('auth.sms')->with('status', 'Kod wysłany SMS-em.');
    }

    public function verify(Request $request, SuperadminSmsService $sms)
    {
        abort_unless($sms->required($request->user()), 403);
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        if (! $sms->verify($request, $data['code'])) {
            return back()->withErrors(['code' => 'Kod jest nieprawidłowy, wygasł lub przekroczono limit prób.']);
        }

        app(ActivityLogService::class)->recordAuthentication($request->user(), 'login');

        return redirect()->intended(route(CompanySettings::staffLandingRoute()));
    }
}
