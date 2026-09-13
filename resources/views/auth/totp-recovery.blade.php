@extends('auth.totp-layout')
@section('content')
<h1>Authenticator jest aktywny</h1>
@if($codes)
    <p><strong>Zapisz teraz kody ratunkowe</strong> w menedżerze haseł lub bezpiecznym miejscu poza telefonem.</p>
    <p>Każdy kod umożliwia jedno logowanie, gdy nie masz dostępu do aplikacji Authenticator.</p>
    <div class="codes">@foreach($codes as $code)<code>{{$code}}</code>@endforeach</div>
    <p class="help">Pokazujemy je tylko teraz. Odświeżenie lub opuszczenie tej strony ukryje kody. Nie wysyłaj ich nikomu.</p>
@else
    <p>Kody ratunkowe były już wyświetlone. Ze względów bezpieczeństwa nie pokazujemy ich ponownie.</p>
@endif
<a class="button" href="{{route(\App\Models\CompanySettings::staffLandingRoute())}}">Przejdź do aplikacji</a>
@endsection
