@extends('layouts.app')

@section('page-title', 'Brak dostępu')

@section('content')
<style>
    .access-denied-shell{min-height:calc(100vh - 124px);display:flex;align-items:center;justify-content:center;padding:24px}.access-denied-card{width:min(680px,100%);background:#fff;border:1px solid #e5e1d8;border-radius:16px;padding:38px;box-shadow:0 12px 34px rgba(26,77,58,.08);text-align:center}.access-denied-icon{width:72px;height:72px;margin:0 auto 20px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#fef3c7;color:#92400e;font-size:34px}.access-denied-code{font-size:11px;font-weight:800;letter-spacing:.12em;color:#8a948d;text-transform:uppercase}.access-denied-card h1{margin:8px 0 12px;color:var(--green);font:800 27px 'Manrope',sans-serif}.access-denied-card p{max-width:540px;margin:0 auto;color:#66736b;font-size:14px;line-height:1.7}.access-denied-actions{display:flex;justify-content:center;gap:10px;flex-wrap:wrap;margin-top:26px}.access-denied-btn{display:inline-flex;align-items:center;gap:7px;border:0;border-radius:8px;padding:11px 16px;font:700 13px 'Manrope',sans-serif;cursor:pointer;text-decoration:none}.access-denied-btn.primary{background:var(--green);color:#fff}.access-denied-btn.secondary{background:#edf4ef;color:var(--green)}.access-denied-message{margin:18px auto 0!important;padding:10px 12px;border-radius:8px;font-weight:700}.access-denied-message.success{background:#ecfdf5;color:#166534}.access-denied-message.error{background:#fef2f2;color:#991b1b}@media(max-width:650px){.access-denied-shell{padding:8px}.access-denied-card{padding:28px 18px}.access-denied-card h1{font-size:22px}.access-denied-actions{flex-direction:column}.access-denied-btn{justify-content:center;width:100%}}
</style>

<div class="access-denied-shell">
    <div class="access-denied-card">
        <div class="access-denied-icon"><i class="ti ti-lock-access"></i></div>
        <div class="access-denied-code">Błąd 403 · ograniczony dostęp</div>
        <h1>Nie masz dostępu do tego zasobu</h1>
        <p>Twoja rola nie obejmuje dostępu do tej części aplikacji. Jeśli jest on potrzebny do wykonywania Twojej pracy, poproś administratora o sprawdzenie uprawnień.</p>

        @if(session('access_request_success'))
            <p class="access-denied-message success"><i class="ti ti-circle-check"></i> {{session('access_request_success')}}</p>
        @endif
        @if(session('access_request_error'))
            <p class="access-denied-message error"><i class="ti ti-alert-circle"></i> {{session('access_request_error')}}</p>
        @endif

        <div class="access-denied-actions">
            <a class="access-denied-btn secondary" href="{{route('home')}}"><i class="ti ti-arrow-left"></i> Wróć do aplikacji</a>
            <form method="POST" action="{{route('access-support.store')}}">
                @csrf
                <input type="hidden" name="requested_url" value="{{request()->fullUrl()}}">
                <button class="access-denied-btn primary" type="submit"><i class="ti ti-user-question"></i> Poproś administratora o sprawdzenie</button>
            </form>
        </div>
    </div>
</div>
@endsection
