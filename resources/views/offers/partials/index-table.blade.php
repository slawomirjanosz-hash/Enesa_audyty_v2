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
                                <span style="font-family:'Lato',sans-serif;font-weight:700;">
                                    {{ number_format($offer->kwota_netto, 2, ',', ' ') }} zł
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
                        <td style="text-align:center;white-space:nowrap;">
                            <a href="{{ route('offers.show', $offer) }}" class="btn-icon btn-icon-view" title="Podgląd">
                                <i class="ti ti-eye"></i>
                            </a>
                            <button type="button" onclick="openPdfModal('{{ route('offers.pdf', $offer) }}', 'Oferta {{ $offer->offer_full_number }}')" class="btn-icon btn-icon-pdf" title="Podgląd PDF" style="margin-left:4px;border:none;cursor:pointer;">
                                <i class="ti ti-file-type-pdf"></i>
                            </button>
                            <form method="POST" action="{{ route('offers.save-to-storage', $offer) }}" style="display:inline;margin-left:4px;">
                                @csrf
                                <button type="submit" class="btn-icon" style="background:#EFF6FF;color:#2563EB;border:none;cursor:pointer;" title="Zapisz na dysku">
                                    <i class="ti ti-device-floppy"></i>
                                </button>
                            </form>
                            <a href="{{ route('offers.edit', $offer) }}" class="btn-icon btn-icon-edit" title="Edytuj" style="margin-left:4px;">
                                <i class="ti ti-pencil"></i>
                            </a>
                            <form method="POST" action="{{ route('offers.clone', $offer) }}" style="display:inline;margin-left:4px;">
                                @csrf
                                <input type="hidden" name="mode" value="offer">
                                <button type="submit"
                                        style="background:#F0F7F3;border:none;cursor:pointer;color:var(--green);padding:6px 7px;border-radius:6px;display:inline-flex;align-items:center;"
                                        title="Kopiuj ofertę">
                                    <i class="ti ti-copy"></i>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('offers.destroy', $offer) }}"
                                  onsubmit="return confirm('Czy na pewno chcesz usunąć ofertę {{ $offer->offer_full_number }}?')"
                                  style="display:inline;margin-left:4px;">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        style="background:none;border:none;cursor:pointer;color:#DC2626;padding:4px 6px;border-radius:6px;display:inline-flex;align-items:center;"
                                        title="Usuń ofertę">
                                    <i class="ti ti-trash"></i>
                                </button>
                            </form>
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
