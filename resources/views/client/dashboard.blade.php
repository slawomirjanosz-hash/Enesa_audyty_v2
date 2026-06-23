@extends('layouts.client')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@push('styles')
<style>
    .welcome-block { margin-bottom: 28px; }
    .welcome-block h1 {
        font-family: 'Manrope', sans-serif;
        font-size: 22px;
        font-weight: 700;
        color: #1A4D3A;
        margin-bottom: 4px;
    }
    .welcome-block p {
        font-size: 14px;
        color: #5a6a60;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
        margin-bottom: 28px;
    }
    .stat-card {
        background: #fff;
        border: 0.5px solid #E5E1D8;
        border-radius: 10px;
        padding: 14px 16px;
        display: flex;
        align-items: center;
        gap: 14px;
    }
    .stat-icon {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 20px;
    }
    .stat-icon-green  { background: #E8F5E9; color: #2E7D32; }
    .stat-icon-blue   { background: #E3F2FD; color: #1565C0; }
    .stat-icon-orange { background: #FFF3E0; color: #E65100; }
    .stat-icon-purple { background: #F3E5F5; color: #6A1B9A; }
    .stat-value {
        font-family: 'Lato', sans-serif;
        font-size: 26px;
        font-weight: 900;
        color: #1A1A1A;
        line-height: 1;
        margin-bottom: 2px;
    }
    .stat-label {
        font-family: 'Manrope', sans-serif;
        font-size: 12px;
        font-weight: 600;
        color: #555;
    }

    .section-title {
        font-family: 'Manrope', sans-serif;
        font-size: 15px;
        font-weight: 700;
        color: #1A4D3A;
        margin-bottom: 14px;
    }

    .empty-state {
        text-align: center;
        padding: 60px 24px;
        background: #fff;
        border: 1px dashed #D0CCC0;
        border-radius: 12px;
    }
    .empty-state i {
        font-size: 40px;
        color: #C8DDD4;
        display: block;
        margin-bottom: 12px;
    }
    .empty-state p {
        font-family: 'Manrope', sans-serif;
        font-size: 14px;
        color: #888;
        margin: 0;
    }

    @media (max-width: 1100px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 640px)  { .stats-grid { grid-template-columns: 1fr; } }
</style>
@endpush

@section('content')

@php
    $clientCompany = auth()->user()->companies->first();
@endphp

<div class="welcome-block">
    <h1>Witaj, {{ auth()->user()->name }}</h1>
    <p>{{ $clientCompany?->name ?? 'Brak przypisanej firmy' }}</p>
</div>

{{-- Stats --}}
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon stat-icon-green"><i class="ti ti-clipboard-check"></i></div>
        <div>
            <div class="stat-value">0</div>
            <div class="stat-label">Aktywne audyty</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon-blue"><i class="ti ti-file-invoice"></i></div>
        <div>
            <div class="stat-value">0</div>
            <div class="stat-label">Oferty oczekujące</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon-orange"><i class="ti ti-message-2"></i></div>
        <div>
            <div class="stat-value">0</div>
            <div class="stat-label">Nowe wiadomości</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon-purple"><i class="ti ti-files"></i></div>
        <div>
            <div class="stat-value">0</div>
            <div class="stat-label">Dokumenty</div>
        </div>
    </div>
</div>

{{-- Last activity --}}
<div class="section-title">Ostatnia aktywność</div>
<div class="empty-state">
    <i class="ti ti-activity"></i>
    <p>Brak aktywności</p>
</div>

@endsection
