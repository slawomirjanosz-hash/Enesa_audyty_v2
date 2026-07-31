@extends('layouts.app')

@section('page-title', 'Typy audytów')

@push('styles')
<style>
    .page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 28px;
    }
    .page-header-title {
        font-family: 'Manrope', sans-serif;
        font-size: 22px;
        font-weight: 700;
        color: #1A1A1A;
    }
    .page-header-sub {
        font-size: 13px;
        color: #888;
        margin-top: 3px;
    }

    .audit-types-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 20px;
    }

    .audit-type-card {
        background: #fff;
        border-radius: 12px;
        border: 1px solid #E5E1D8;
        padding: 24px;
        display: flex;
        flex-direction: column;
        gap: 14px;
        transition: box-shadow .15s, border-color .15s;
    }
    .audit-type-card:hover {
        box-shadow: 0 4px 16px rgba(0,0,0,.08);
        border-color: #C5C0B5;
    }

    .audit-type-icon {
        width: 44px;
        height: 44px;
        background: #E8F5E9;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        color: var(--green);
        flex-shrink: 0;
    }

    .audit-type-name {
        font-family: 'Manrope', sans-serif;
        font-size: 16px;
        font-weight: 700;
        color: #1A1A1A;
    }
    .audit-type-slug {
        font-size: 12px;
        color: #888;
        font-family: 'Lato', monospace;
        background: #F4F1EA;
        display: inline-block;
        padding: 2px 8px;
        border-radius: 4px;
        margin-top: 2px;
    }

    .audit-type-meta {
        display: flex;
        gap: 16px;
        font-size: 13px;
        color: #555;
    }
    .audit-type-meta span {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    .audit-type-meta i { color: var(--green); }

    .badge-current {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: #E8F5E9;
        color: var(--green);
        font-size: 12px;
        font-weight: 600;
        padding: 3px 10px;
        border-radius: 20px;
        border: 1px solid #A5D6A7;
    }
    .badge-no-version {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: #F4F1EA;
        color: #888;
        font-size: 12px;
        padding: 3px 10px;
        border-radius: 20px;
    }

    .btn-manage {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: var(--green);
        color: #fff;
        padding: 9px 18px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        transition: background .15s;
        align-self: flex-start;
    }
    .btn-manage:hover { background: #143d2d; color: #fff; }

    .alert-success {
        background: #E8F5E9;
        border: 1px solid #A5D6A7;
        color: #1B5E20;
        border-radius: 8px;
        padding: 12px 16px;
        font-size: 14px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .alert-error {
        background: #FFEBEE;
        border: 1px solid #FFCDD2;
        color: #B71C1C;
        border-radius: 8px;
        padding: 12px 16px;
        font-size: 14px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #888;
    }
    .empty-state i { font-size: 48px; color: #C5C0B5; display: block; margin-bottom: 14px; }
    .empty-state p { font-size: 15px; }
</style>
@endpush

@section('content')
<div class="page-header">
    <div>
        <div class="page-header-title">Typy audytów</div>
        <div class="page-header-sub">Zarządzaj typami audytów i wersjami formularzy HTML</div>
    </div>
</div>

@if(session('success'))
    <div class="alert-success"><i class="ti ti-circle-check"></i> {{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert-error"><i class="ti ti-alert-circle"></i> {{ session('error') }}</div>
@endif

@if($auditTypes->isEmpty())
    <div class="empty-state">
        <i class="ti ti-clipboard-list"></i>
        <p>Brak typów audytów. Dodaj je przez seeder lub panel administracyjny.</p>
    </div>
@else
    <div class="audit-types-grid">
        @foreach($auditTypes as $auditType)
            @php
                $current = $auditType->versions->first();
            @endphp
            <div class="audit-type-card">
                <div style="display:flex;align-items:flex-start;gap:14px;">
                    <div class="audit-type-icon">
                        <i class="ti ti-clipboard-check"></i>
                    </div>
                    <div>
                        <div class="audit-type-name">{{ $auditType->name }}</div>
                        <span class="audit-type-slug">{{ $auditType->slug }}</span>
                    </div>
                </div>

                <div class="audit-type-meta">
                    <span>
                        <i class="ti ti-versions"></i>
                        {{ $auditType->versions_count }} {{ $auditType->versions_count === 1 ? 'wersja' : ($auditType->versions_count < 5 ? 'wersje' : 'wersji') }}
                    </span>
                    <span>
                        @if($current)
                            <div class="badge-current">
                                <i class="ti ti-star-filled" style="font-size:11px;"></i> {{ $current->version_number }}
                            </div>
                        @else
                            <div class="badge-no-version"><i class="ti ti-minus"></i> Brak wersji</div>
                        @endif
                    </span>
                </div>

                <a href="{{ route('audit-types.show', $auditType) }}" class="btn-manage">
                    <i class="ti ti-settings"></i> Zarządzaj
                </a>
            </div>
        @endforeach
    </div>
@endif
@endsection
