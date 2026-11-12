<div class="table-card" @if($isArchive) id="zarchiwizowane" style="margin-top:20px;" @endif>
    <div class="table-card-header">
        <div class="table-card-title">
            <i class="ti {{ $isArchive ? 'ti-archive' : 'ti-list' }}"></i>
            {{ $isArchive ? 'Zarchiwizowane' : ($activeStatus ? ($statusLabels[$activeStatus] ?? $activeStatus) : 'Wszystkie oferty') }}
            <span style="font-weight:400;color:#888;">({{ $rows->total() }})</span>
        </div>
    </div>

    @if($rows->isEmpty())
        <div class="empty-state">
            <div><i class="ti ti-file-off"></i></div>
            <p>{{ $isArchive ? 'Brak zarchiwizowanych ofert.' : ($activeStatus ? 'Brak ofert o tym statusie.' : 'Brak aktywnych ofert.') }}</p>
            @if(!$activeStatus && !$isArchive)
                <a href="{{ route('offers.create') }}" class="btn-primary" style="display:inline-flex;">
                    <i class="ti ti-plus"></i> Utwórz pierwszą ofertę
                </a>
            @endif
        </div>
    @else
        <div style="overflow-x:auto;">
            <table class="offers-table" @if($isArchive) data-sort-prefix="archive" data-page-parameter="archive_page" @endif data-server-sort="number,title,company,owner,amount,status,date">
                <thead>
                    <tr>
                        <th>Numer oferty</th>
                        <th>Tytuł</th>
                        <th>Klient</th>
                        <th>Prowadzi</th>
                        <th>Kwota netto</th>
                        <th>Status</th>
                        <th>Data</th>
                        <th style="text-align:center;">Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $offer)
                    <tr>
                        <td>
                            <a href="{{ route('offers.show', $offer) }}"
                               style="color:var(--green);font-weight:700;text-decoration:none;font-family:'Manrope',sans-serif;font-size:13px;">
                                {{ $offer->fullNumber() }}
                            </a>
                        </td>
                        <td style="color:#555;">{{ $offer->offer_title ?? '—' }}</td>
                        <td style="color:#333;">{{ $offer->company?->name ?? '—' }}</td>
                        <td style="color:#555;">{{ $offer->assignedUser?->name ?? '—' }}</td>
                        <td>
                            @if($offer->kwota_netto && $offer->kwota_netto > 0)
                                <span class="offer-amount">
                                    {{ number_format($offer->kwota_netto, 2, ',', ' ') }}&nbsp;zł
                                </span>
                            @else
                                <em style="color:#aaa;font-size:12px;">— brak —</em>
                            @endif
                        </td>
                        <td>
                            @include('offers.partials.index-status')
                        </td>
                        <td style="color:#666;font-size:13px;white-space:nowrap;">
                            {{ $offer->created_at->format('d.m.Y') }}
                        </td>
                        <td class="offer-actions-cell">
                            @include('offers.partials.index-actions')
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($rows->hasPages())
            <div style="padding:16px 20px;border-top:1px solid #F0EDE6;">
                {{ $rows->links() }}
            </div>
        @endif
    @endif
</div>
