@extends('layouts.app')

@section('page-title', 'Strefa klienta')

@section('content')

@push('styles')
<style>
    .cz-header {
        margin-bottom: 28px;
    }
    .cz-header h1 {
        font-size: 22px;
        font-weight: 700;
        color: var(--green);
        font-family: 'Manrope', sans-serif;
    }
    .cz-header p {
        margin-top: 4px;
        font-size: 13px;
        color: #6b7a72;
    }

    .cz-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 20px;
    }

    .cz-card {
        background: #fff;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 2px 8px rgba(0,0,0,.07);
        display: flex;
        flex-direction: column;
        gap: 12px;
        transition: box-shadow .15s;
    }

    .cz-card:hover {
        box-shadow: 0 4px 16px rgba(0,0,0,.12);
    }

    .cz-card-name {
        font-size: 16px;
        font-weight: 700;
        color: var(--green);
        font-family: 'Manrope', sans-serif;
    }

    .cz-card-meta {
        font-size: 12px;
        color: #6b7a72;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .cz-card-meta i {
        font-size: 14px;
        color: #aab8b2;
    }

    .cz-card-btn {
        margin-top: auto;
        width: 100%;
        background: var(--green);
        color: #fff;
        border: none;
        border-radius: 8px;
        padding: 10px;
        font-family: 'Manrope', sans-serif;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: background .15s;
    }

    .cz-card-btn:hover {
        background: #143d2d;
    }

    .cz-empty {
        grid-column: 1 / -1;
        text-align: center;
        padding: 60px 20px;
        color: #6b7a72;
    }

    .cz-empty i {
        font-size: 56px;
        color: #c8d5cf;
        display: block;
        margin-bottom: 16px;
    }

    .cz-empty p {
        font-size: 15px;
        font-weight: 500;
    }

    .alert-error {
        background: #FEF2F2;
        border: 1px solid #FECACA;
        color: #B91C1C;
        border-radius: 8px;
        padding: 12px 16px;
        margin-bottom: 20px;
        font-size: 13px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
</style>
@endpush

@if(session('error'))
    <div class="alert-error">
        <i class="ti ti-alert-circle"></i>
        {{ session('error') }}
    </div>
@endif

<div class="cz-header">
    <h1><i class="ti ti-eye" style="vertical-align:middle;margin-right:6px;"></i>Wybierz firmę klienta</h1>
    <p>Wybierz firmę, aby przeglądać jej strefę klienta jako audytor.</p>
</div>

<div class="cz-grid">
    @forelse($companies as $company)
        <div class="cz-card">
            <div class="cz-card-name">{{ $company->name }}</div>
            <div class="cz-card-meta">
                <i class="ti ti-users"></i>
                {{ $company->users_count }} {{ $company->users_count === 1 ? 'użytkownik' : ($company->users_count < 5 ? 'użytkowników' : 'użytkowników') }}
            </div>
            <form method="POST" action="{{ route('client-zone.impersonate', $company) }}" style="margin-top:auto;">
                @csrf
                <button type="submit" class="cz-card-btn">
                    <i class="ti ti-eye" style="margin-right:4px;"></i> Przejdź do widoku
                </button>
            </form>
        </div>
    @empty
        <div class="cz-empty">
            <i class="ti ti-building-off"></i>
            <p>Brak firm z użytkownikami klienckimi</p>
        </div>
    @endforelse
</div>

@endsection
