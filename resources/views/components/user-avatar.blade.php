@props(['user'])

@php
    $avatarDataUri = $user?->avatarDataUri();
@endphp

@if($avatarDataUri)
    <img src="{{ $avatarDataUri }}" alt="Zdjęcie użytkownika {{ $user->name }}" style="display:block;width:100%;height:100%;object-fit:cover;border-radius:50%;">
@else
    {{ $user?->initials() ?? '?' }}
@endif
