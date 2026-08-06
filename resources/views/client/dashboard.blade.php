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
        color: var(--green);
        margin-bottom: 4px;
    }
    .welcome-block p {
        font-family: 'Manrope', sans-serif;
        font-size: 14px;
        color: #5a6a60;
        margin: 0;
    }

    /* Stats */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
        margin-bottom: 32px;
    }
    .stat-card {
        background: #fff;
        border: 0.5px solid #E5E1D8;
        border-radius: 10px;
        padding: 16px 18px;
        display: flex;
        align-items: center;
        gap: 14px;
    }
    .stat-icon {
        width: 44px;
        height: 44px;
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
    .stat-icon-purple { background: #F3E5F5; color: #7B1FA2; }
    .stat-value {
        font-family: 'Lato', sans-serif;
        font-size: 28px;
        font-weight: 900;
        color: #1A1A1A;
        line-height: 1;
        margin-bottom: 3px;
    }
    .stat-label {
        font-family: 'Manrope', sans-serif;
        font-size: 12px;
        font-weight: 600;
        color: #555;
    }

    /* Tabs */
    .tabs-header {
        display: flex;
        gap: 0;
        border-bottom: 1px solid #E5E1D8;
        background: #fff;
        border-radius: 10px 10px 0 0;
        overflow: hidden;
        margin-bottom: 20px;
    }
    .tab-btn {
        flex: 1;
        padding: 14px 16px;
        font-family: 'Manrope', sans-serif;
        font-size: 13px;
        font-weight: 600;
        color: #666;
        background: #fff;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
        border-bottom: 2px solid transparent;
        position: relative;
        top: 1px;
    }
    .tab-btn:hover { color: var(--green); background: #F4F1EA; }
    .tab-btn.active {
        color: var(--green);
        border-bottom-color: var(--green);
        background: #fff;
    }
    
    .tabs-content {
        background: #fff;
        border: 0.5px solid #E5E1D8;
        border-radius: 0 10px 10px 10px;
        padding: 20px;
    }
    
    .tab-pane {
        display: none;
    }
    .tab-pane.active {
        display: block;
    }

    /* Lists */
    .empty-state {
        text-align: center;
        padding: 40px 24px;
        background: #F4F1EA;
        border: 1px dashed #D0CCC0;
        border-radius: 12px;
    }
    .empty-state i {
        font-size: 36px;
        color: #C8DDD4;
        display: block;
        margin-bottom: 10px;
    }
    .empty-state p {
        font-family: 'Manrope', sans-serif;
        font-size: 14px;
        color: #888;
        margin: 0 0 14px;
    }
    .empty-state a {
        display: inline-block;
        background: var(--green);
        color: #F4F1EA;
        font-family: 'Manrope', sans-serif;
        font-size: 13px;
        font-weight: 700;
        padding: 8px 18px;
        border-radius: 8px;
        text-decoration: none;
    }
    .empty-state a:hover { background: #15402f; }

    /* Item rows */
    .item-row {
        background: #F4F1EA;
        border: 0.5px solid #E5E1D8;
        border-radius: 8px;
        padding: 14px 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 10px;
    }
    .item-row:last-child { margin-bottom: 0; }
    .item-row:hover { background: #F9F7F3; border-color: #C8DDD4; }
    
    .item-left {
        flex: 1;
    }
    .item-title {
        font-family: 'Manrope', sans-serif;
        font-size: 13px;
        font-weight: 600;
        color: #1A1A1A;
        margin-bottom: 3px;
    }
    .item-meta {
        font-family: 'Manrope', sans-serif;
        font-size: 11px;
        color: #888;
    }
    
    .item-right {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-shrink: 0;
    }
    .item-amount {
        font-family: 'Lato', sans-serif;
        font-size: 14px;
        font-weight: 700;
        color: #1A1A1A;
        text-align: right;
        min-width: 80px;
    }

    /* Badge */
    .badge {
        display: inline-block;
        padding: 3px 12px;
        border-radius: 20px;
        font-family: 'Manrope', sans-serif;
        font-size: 10px;
        font-weight: 700;
        flex-shrink: 0;
        white-space: nowrap;
    }
    .badge-w-toku         { background: #DBEAFE; color: #1D4ED8; }
    .badge-wygrana        { background: #DCFCE7; color: #166534; }
    .badge-przegrana      { background: #FEE2E2; color: #B91C1C; }
    .badge-w-negocjacji   { background: #FEF3C7; color: #92400E; }
    .badge-zarchiwizowana { background: #F3F4F6; color: #4B5563; }
    .badge-nowe           { background: #DBEAFE; color: #1D4ED8; }
    .badge-zamkniete      { background: #DCFCE7; color: #166534; }

    @media (max-width: 1200px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 640px) { 
        .stats-grid { grid-template-columns: 1fr; }
        .tabs-header { flex-wrap: wrap; }
        .tab-btn { padding: 12px 14px; font-size: 12px; }
    }
</style>
@endpush

@section('content')

@php
    $firstDashboardModule = collect(['offers', 'offer_requests', 'audits', 'documents', 'chat'])
        ->first(fn (string $module) => $company->moduleEnabled($module));
@endphp

{{-- Welcome --}}
<div class="welcome-block">
    <h1>Witaj, {{ auth()->user()->name }}</h1>
    <p>{{ $company->name }}</p>
</div>

{{-- Stats --}}
<div class="stats-grid">
    @if($company->moduleEnabled('offers'))
    <div class="stat-card">
        <div class="stat-icon stat-icon-green"><i class="ti ti-file-invoice"></i></div>
        <div>
            <div class="stat-value">{{ $offers->count() }}</div>
            <div class="stat-label">Oferty</div>
        </div>
    </div>
    @endif
    @if($company->moduleEnabled('offer_requests'))
    <div class="stat-card">
        <div class="stat-icon stat-icon-blue"><i class="ti ti-inbox"></i></div>
        <div>
            <div class="stat-value">{{ $offerRequests->count() }}</div>
            <div class="stat-label">Zapytania</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon-orange"><i class="ti ti-bell"></i></div>
        <div>
            <div class="stat-value">{{ $offerRequests->where('status', 'nowe')->count() }}</div>
            <div class="stat-label">Nowe zapytania</div>
        </div>
    </div>
    @endif
    @if($company->moduleEnabled('audits'))
    <div class="stat-card">
        <div class="stat-icon stat-icon-purple"><i class="ti ti-clipboard-check"></i></div>
        <div>
            <div class="stat-value">{{ $audits->count() }}</div>
            <div class="stat-label">Audyty</div>
        </div>
    </div>
    @endif
</div>

{{-- Tabs --}}
<div class="tabs-header">
    @if($company->moduleEnabled('offers'))
    <button class="tab-btn {{ $firstDashboardModule === 'offers' ? 'active' : '' }}" onclick="switchTab('offers')">
        <i class="ti ti-file-invoice"></i> Oferty
    </button>
    @endif
    @if($company->moduleEnabled('offer_requests'))
    <button class="tab-btn {{ $firstDashboardModule === 'offer_requests' ? 'active' : '' }}" onclick="switchTab('requests')">
        <i class="ti ti-send"></i> Zapytania
    </button>
    @endif
    @if($company->moduleEnabled('audits'))
    <button class="tab-btn {{ $firstDashboardModule === 'audits' ? 'active' : '' }}" onclick="switchTab('audits')">
        <i class="ti ti-clipboard-check"></i> Audyty
    </button>
    @endif
    @if($company->moduleEnabled('documents'))
    <button class="tab-btn {{ $firstDashboardModule === 'documents' ? 'active' : '' }}" onclick="switchTab('documents')">
        <i class="ti ti-files"></i> Dokumenty
    </button>
    @endif
    @if($company->moduleEnabled('chat'))
    <button class="tab-btn {{ $firstDashboardModule === 'chat' ? 'active' : '' }}" onclick="switchTab('chat')">
        <i class="ti ti-message-2"></i> Chat
    </button>
    @endif
</div>

<div class="tabs-content">
    {{-- OFFERS TAB --}}
    <div id="offers" class="tab-pane {{ $firstDashboardModule === 'offers' ? 'active' : '' }}">
        @if($offers->isEmpty())
            <div class="empty-state">
                <i class="ti ti-file-off"></i>
                <p>Nie masz jeszcze żadnych ofert.</p>
                <a href="{{ route('client.request-offer') }}">Złóż zapytanie ofertowe</a>
            </div>
        @else
            @foreach($offers as $offer)
            @php
                $badgeClass = match($offer->status) {
                    'w_toku'         => 'badge-w-toku',
                    'wygrana'        => 'badge-wygrana',
                    'przegrana'      => 'badge-przegrana',
                    'w_negocjacji'   => 'badge-w-negocjacji',
                    'zarchiwizowana' => 'badge-zarchiwizowana',
                    default          => 'badge-zarchiwizowana',
                };
                $statusLabel = match($offer->status) {
                    'w_toku'         => 'W toku',
                    'wygrana'        => 'Zaakceptowana',
                    'przegrana'      => 'Odrzucona',
                    'w_negocjacji'   => 'W negocjacji',
                    'zarchiwizowana' => 'Archiwalna',
                    default          => $offer->status,
                };
            @endphp
            <a href="{{ route('client.offers.show', $offer) }}" style="text-decoration: none; color: inherit;">
                <div class="item-row">
                    <div class="item-left">
                        <div class="item-title">{{ $offer->offer_full_number ?? $offer->offer_number }}</div>
                        @if($offer->offer_title)
                            <div class="item-meta">{{ $offer->offer_title }}</div>
                        @endif
                        <div class="item-meta">{{ $offer->created_at->format('d.m.Y H:i') }}</div>
                    </div>
                    <div class="item-right">
                        @if($offer->kwota_netto !== null)
                            <div class="item-amount">{{ number_format($offer->kwota_netto, 2, ',', ' ') }} zł</div>
                        @endif
                        <span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span>
                    </div>
                </div>
            </a>
            @endforeach
        @endif
    </div>

    {{-- REQUESTS TAB --}}
    <div id="requests" class="tab-pane {{ $firstDashboardModule === 'offer_requests' ? 'active' : '' }}">
        @if($offerRequests->isEmpty())
            <div class="empty-state">
                <i class="ti ti-inbox-off"></i>
                <p>Brak zapytań ofertowych.</p>
                <a href="{{ route('client.request-offer') }}">Utwórz nowe zapytanie</a>
            </div>
        @else
            @foreach($offerRequests as $req)
            @php
                $badgeClass = match($req->status) {
                    'nowe'      => 'badge-nowe',
                    'w_toku'    => 'badge-w-toku',
                    'zamknięte' => 'badge-zamkniete',
                    default     => 'badge-nowe',
                };
                $statusLabel = match($req->status) {
                    'nowe'      => 'Nowe',
                    'w_toku'    => 'W toku',
                    'zamknięte' => 'Zamknięte',
                    default     => $req->status,
                };
            @endphp
            <a href="{{ route('client.request-offer.show', $req) }}" style="text-decoration: none; color: inherit;">
                <div class="item-row">
                    <div class="item-left">
                        <div class="item-title">{{ $req->offerFormTemplate?->name ?? 'Zapytanie #' . $req->id }}</div>
                        <div class="item-meta">{{ $req->created_at->format('d.m.Y H:i') }}</div>
                    </div>
                    <div class="item-right">
                        <span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span>
                    </div>
                </div>
            </a>
            @endforeach
        @endif
    </div>

    {{-- AUDITS TAB --}}
    <div id="audits" class="tab-pane {{ $firstDashboardModule === 'audits' ? 'active' : '' }}">
        @if($audits->isEmpty())
            <div class="empty-state">
                <i class="ti ti-clipboard-x"></i>
                <p>Brak audytów.</p>
            </div>
        @else
            @foreach($audits as $audit)
            <div class="item-row">
                <div class="item-left">
                    <div class="item-title">{{ $audit->title ?? 'Audyt #' . $audit->id }}</div>
                    <div class="item-meta">{{ $audit->created_at->format('d.m.Y H:i') }}</div>
                </div>
                <div class="item-right">
                    <span class="badge badge-w-toku">{{ $audit->status ?? 'Aktywny' }}</span>
                </div>
            </div>
            @endforeach
        @endif
    </div>

    {{-- DOCUMENTS TAB --}}
    <div id="documents" class="tab-pane {{ $firstDashboardModule === 'documents' ? 'active' : '' }}">
        <div class="empty-state">
            <i class="ti ti-file-off"></i>
            <p>Brak dokumentów.</p>
        </div>
    </div>

    {{-- CHAT TAB --}}
    <div id="chat" class="tab-pane {{ $firstDashboardModule === 'chat' ? 'active' : '' }}">
        <div class="empty-state">
            <i class="ti ti-message-off"></i>
            <p>Brak wiadomości.</p>
        </div>
    </div>
</div>

<script>
function switchTab(tabName) {
    // Hide all panes
    document.querySelectorAll('.tab-pane').forEach(pane => {
        pane.classList.remove('active');
    });
    
    // Deactivate all buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected pane
    document.getElementById(tabName).classList.add('active');
    
    // Activate clicked button
    event.target.closest('.tab-btn').classList.add('active');
}
</script>

@endsection

