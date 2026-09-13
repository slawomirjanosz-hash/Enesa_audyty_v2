<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\CompanySettings;
use App\Models\User;
use App\Services\AccountSecurityService;
use App\Services\ActivityLogService;
use App\Services\SuperadminTotpService;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use OTPHP\TOTP;

class TotpChallengeController extends Controller
{
    public function show(Request $request, SuperadminTotpService $totp)
    {
        abort_unless($totp->required($request->user()), 403);
        if ($totp->verified($request)) {
            return redirect()->route(CompanySettings::staffLandingRoute());
        }
        $pending = $request->session()->get('totp_pending');
        $secret = null;
        $qr = null;
        if (! $request->user()->two_factor_confirmed_at && $pending && $pending['expires'] > now()->timestamp
            && $pending['user'] === $request->user()->id && $pending['version'] === (int) $request->user()->session_version) {
            $secret = decrypt($pending['secret']);
            $otp = TOTP::createFromSecret($secret);
            $otp->setLabel($request->user()->email);
            $otp->setIssuer('Superadmin '.(parse_url(config('app.url'), PHP_URL_HOST) ?: 'Aplikacja'));
            $writer = new Writer(new ImageRenderer(new RendererStyle(280), new SvgImageBackEnd));
            $qr = 'data:image/svg+xml;base64,'.base64_encode($writer->writeString($otp->getProvisioningUri()));
        }

        return response()->view('auth.totp', compact('secret', 'qr'))->header('Cache-Control', 'no-store, private');
    }

    public function begin(Request $request, SuperadminTotpService $totp)
    {
        abort_unless($totp->required($request->user()) && ! $request->user()->two_factor_confirmed_at, 403);
        $data = $request->validate(['password' => ['required', 'string', 'max:1024']]);
        if (! $totp->attempt($request->user()) || ! Hash::check($data['password'], $request->user()->password)) {
            throw ValidationException::withMessages(['password' => 'Nieprawidłowe hasło lub przekroczono limit prób. Odczekaj 5 minut, jeśli prób było zbyt wiele.']);
        }
        $request->session()->put('totp_pending', [
            'secret' => encrypt(TOTP::create()->getSecret()), 'expires' => now()->addMinutes(10)->timestamp,
            'user' => $request->user()->id, 'version' => (int) $request->user()->session_version,
        ]);

        return redirect()->route('auth.totp');
    }

    public function confirm(Request $request, SuperadminTotpService $totp)
    {
        abort_unless($totp->required($request->user()) && ! $request->user()->two_factor_confirmed_at, 403);
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        if (! $totp->attempt($request->user())) {
            throw ValidationException::withMessages(['code' => 'Zbyt wiele prób. Odczekaj 5 minut.']);
        }
        $pending = $request->session()->get('totp_pending');
        if (! $pending || $pending['expires'] <= now()->timestamp || $pending['user'] !== $request->user()->id
            || $pending['version'] !== (int) $request->user()->session_version) {
            $request->session()->forget('totp_pending');

            return redirect()->route('auth.totp')->withErrors(['code' => 'Konfiguracja wygasła. Potwierdź ponownie hasło.']);
        }
        $secret = decrypt($pending['secret']);
        $step = $totp->matchingStep($secret, $data['code'], null);
        if ($step === null) {
            throw ValidationException::withMessages(['code' => 'Nieprawidłowy kod. Sprawdź automatyczne ustawienie czasu w telefonie.']);
        }
        $codes = DB::transaction(function () use ($request, $secret, $step, $totp, $pending) {
            $user = User::lockForUpdate()->findOrFail($request->user()->id);
            abort_if($user->two_factor_confirmed_at || (int) $user->session_version !== $pending['version'], 409);
            $codes = array_map(fn () => implode('-', str_split(bin2hex(random_bytes(16)), 8)), range(1, 8));
            $user->forceFill([
                'two_factor_secret' => $secret, 'two_factor_confirmed_at' => now(), 'two_factor_last_step' => $step,
                'two_factor_recovery_codes' => array_map(fn ($code) => hash('sha256', $code), $codes),
            ])->save();
            app(AccountSecurityService::class)->revoke($user);
            $totp->markVerified($request, $user);
            app(ActivityLogService::class)->recordAuthentication($user, 'login');

            return $codes;
        });
        $request->session()->forget('totp_pending');
        RateLimiter::clear('totp-attempt:'.$request->user()->id);

        return redirect()->route('auth.totp.recovery')->with('totp_recovery_display', encrypt($codes));
    }

    public function verify(Request $request, SuperadminTotpService $totp)
    {
        abort_unless($totp->required($request->user()) && $request->user()->two_factor_confirmed_at, 403);
        $data = $request->validate(['code' => ['required', 'string', 'max:64']]);
        if (! $totp->verify($request, trim($data['code']))) {
            throw ValidationException::withMessages(['code' => 'Kod jest nieprawidłowy, został już użyty lub przekroczono limit 5 prób. W razie blokady odczekaj 5 minut.']);
        }

        return redirect()->intended(route(CompanySettings::staffLandingRoute()));
    }

    public function recovery(Request $request, SuperadminTotpService $totp)
    {
        abort_unless($totp->required($request->user()) && $totp->verified($request), 403);
        $encrypted = $request->session()->pull('totp_recovery_display');
        $codes = $encrypted ? decrypt($encrypted) : [];

        return response()->view('auth.totp-recovery', compact('codes'))->header('Cache-Control', 'no-store, private');
    }
}
