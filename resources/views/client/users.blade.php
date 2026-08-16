@extends('layouts.client')

@section('title', 'Użytkownicy')
@section('page-title', 'Użytkownicy')

@push('styles')
<style>
    /* ── Card ─────────────────────────────── */
    .card {
        background: #fff;
        border-radius: 12px;
        border: 1px solid #E5E1D8;
        overflow: hidden;
    }
    .card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 20px 24px;
        border-bottom: 1px solid #F0EDE6;
    }
    .card-header-title {
        font-family: 'Manrope', sans-serif;
        font-size: 15px;
        font-weight: 700;
        color: #1A1A1A;
    }
    .card-header-sub {
        font-size: 13px;
        color: #888;
        margin-top: 2px;
    }

    /* ── Table ────────────────────────────── */
    .users-table { width: 100%; border-collapse: collapse; }
    .users-table th {
        padding: 11px 16px;
        text-align: left;
        font-family: 'Manrope', sans-serif;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #888;
        background: #FAFAF6;
        border-bottom: 1px solid #F0EDE6;
    }
    .users-table td {
        padding: 14px 16px;
        font-size: 14px;
        color: #1A1A1A;
        border-bottom: 1px solid #F7F5F0;
        vertical-align: middle;
    }
    .users-table tr:last-child td { border-bottom: none; }
    .users-table tr:hover td { background: #FAFAF6; }

    /* ── Avatar ───────────────────────────── */
    .u-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: #2E6B52;
        color: #F5F0E8;
        font-family: 'Manrope', sans-serif;
        font-size: 13px;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .user-cell  { display: flex; align-items: center; gap: 12px; }
    .user-name  { font-weight: 600; font-size: 14px; }
    .user-email { font-size: 12px; color: #888; }

    /* ── Badges ───────────────────────────── */
    .badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 10px;
        border-radius: 999px;
        font-family: 'Manrope', sans-serif;
        font-size: 11px;
        font-weight: 700;
        white-space: nowrap;
    }
    .badge-admin { background: #E8F5E9; color: #1B5E20; }
    .badge-user  { background: #F0F0F0; color: #555;    }

    /* ── Action button ────────────────────── */
    .btn-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: 7px;
        border: 1px solid #E5E1D8;
        background: transparent;
        color: #5a6a60;
        cursor: pointer;
        transition: background .15s, color .15s;
    }
    .btn-action:hover { background: #fef2f2; color: #b91c1c; border-color: #fca5a5; }
    .btn-action i { font-size: 16px; }

    /* ── Primary button ───────────────────── */
    .btn-primary {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 9px 18px;
        background: #2E6B52;
        color: #F5F0E8;
        border: none;
        border-radius: 8px;
        font-family: 'Manrope', sans-serif;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        transition: background .15s;
    }
    .btn-primary:hover { background: #265c46; }

    /* ── Alerts ───────────────────────────── */
    .alert {
        padding: 12px 16px;
        border-radius: 8px;
        font-size: 13px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .alert-success { background: #f0fdf4; border: 1px solid #86efac; color: #166534; }
    .alert-error   { background: #fef2f2; border: 1px solid #fca5a5; color: #b91c1c; }

    /* ── Modal ────────────────────────────── */
    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 9000; align-items: center; justify-content: center; }
    .modal-overlay.open { display: flex; }
    .modal-box { background: #fff; border-radius: 14px; padding: 36px; max-width: 500px; width: 95%; max-height: 90vh; overflow-y: auto; position: relative; }
    .modal-close-btn { position: absolute; top: 14px; right: 18px; background: none; border: none; font-size: 20px; color: #aaa; cursor: pointer; line-height: 1; }
    .modal-close-btn:hover { color: #333; }
    .modal-title    { font-family: 'Manrope', sans-serif; font-size: 18px; font-weight: 700; color: #2E6B52; margin-bottom: 6px; }
    .modal-subtitle { font-size: 13px; color: #888; margin-bottom: 24px; }
    .mf-group  { margin-bottom: 14px; }
    .mf-row    { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .mf-label  { display: block; font-size: 12px; font-weight: 700; color: #3a3a3a; margin-bottom: 4px; }
    .mf-input  { width: 100%; background: #FAFAF6; border: 1px solid #D0CCC0; border-radius: 6px; padding: 9px 12px; font-size: 14px; font-family: 'Lato', sans-serif; outline: none; transition: border-color .15s; }
    .mf-input:focus { border-color: #2E6B52; background: #fff; }
    .mf-input-error { border-color: #fca5a5 !important; background: #fff5f5 !important; }
    .mf-error { font-size: 11px; color: #b91c1c; margin-top: 4px; }

    /* ── Role pill selector ───────────────── */
    .role-options { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px; }
    .role-option {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 6px;
        padding: 14px 12px;
        border: 2px solid #E5E1D8;
        border-radius: 10px;
        cursor: pointer;
        text-align: center;
        transition: border-color .15s, background .15s;
        background: #FAFAF6;
    }
    .role-option input[type="radio"] { display: none; }
    .role-option i { font-size: 24px; color: #888; transition: color .15s; }
    .role-option strong { font-family: 'Manrope', sans-serif; font-size: 13px; font-weight: 700; color: #1A1A1A; }
    .role-option span   { font-size: 11px; color: #888; line-height: 1.3; }
    .role-option.selected, .role-option:has(input:checked) {
        border-color: #2E6B52;
        background: #F0F7F3;
    }
    .role-option.selected i, .role-option:has(input:checked) i { color: #2E6B52; }

    .btn-modal-submit { width: 100%; background: #2E6B52; color: #F5F0E8; border: none; border-radius: 8px; padding: 12px; font-family: 'Manrope', sans-serif; font-size: 15px; font-weight: 700; cursor: pointer; transition: background .15s; }
    .btn-modal-submit:hover { background: #265c46; }

    /* ── Archived section ─────────────────── */
    .archived-card {
        background: #fff;
        border-radius: 12px;
        border: 1px solid #E5E1D8;
        overflow: hidden;
        margin-top: 24px;
    }
    .archived-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 24px;
        background: #FAFAF6;
        border-bottom: 1px solid #F0EDE6;
        cursor: pointer;
        user-select: none;
    }
    .archived-header-title {
        font-family: 'Manrope', sans-serif;
        font-size: 13px;
        font-weight: 700;
        color: #888;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .archived-header-toggle { color: #aaa; font-size: 16px; transition: transform .2s; }
    .archived-header-toggle.open { transform: rotate(180deg); }
    .archived-body { display: none; }
    .archived-body.open { display: block; }
    .btn-danger {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 5px 12px;
        background: #fef2f2;
        color: #b91c1c;
        border: 1px solid #fca5a5;
        border-radius: 6px;
        font-family: 'Manrope', sans-serif;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
        transition: background .15s;
    }
    .btn-danger:hover { background: #fee2e2; }
</style>
@endpush

@section('content')

{{-- Flash messages --}}
@if(session('success'))
    <div class="alert alert-success"><i class="ti ti-circle-check"></i> {{ session('success') }}</div>
@endif
@if(session('temporary_password'))
    <div class="alert alert-success"><i class="ti ti-key"></i> Hasło tymczasowe nowego użytkownika: <strong style="font-family:monospace;font-size:15px;user-select:all;">{{ session('temporary_password') }}</strong>. Zapisz je teraz — później nie będzie ponownie widoczne.</div>
@endif
@if(session('error'))
    <div class="alert alert-error"><i class="ti ti-alert-circle"></i> {{ session('error') }}</div>
@endif

{{-- Card --}}
<div class="card">
    <div class="card-header">
        <div>
            <div class="card-header-title">Użytkownicy firmy</div>
            <div class="card-header-sub">{{ $company->name }} &mdash; {{ $users->count() }} {{ $users->count() === 1 ? 'użytkownik' : 'użytkowników' }}</div>
        </div>
        <button class="btn-primary" onclick="openAddModal()">
            <i class="ti ti-user-plus"></i> Dodaj użytkownika
        </button>
    </div>

    @if($users->isEmpty())
        <div style="text-align:center;padding:48px 24px;color:#888;">
            <i class="ti ti-users" style="font-size:40px;color:#C8DDD4;display:block;margin-bottom:12px;"></i>
            <p style="font-family:'Manrope',sans-serif;font-size:14px;margin:0;">Brak użytkowników w firmie</p>
        </div>
    @else
        <table class="users-table">
            <thead>
                <tr>
                    <th>Użytkownik</th>
                    <th>Rola</th>
                    <th>E-mail</th>
                    <th style="text-align:right;width:60px;">Akcje</th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $u)
                    @php
                        $isAdmin = (bool) $u->pivot->is_admin;
                    @endphp
                    <tr>
                        <td>
                            <div class="user-cell">
                                <div class="u-avatar"><x-user-avatar :user="$u" /></div>
                                <div>
                                    <div class="user-name">{{ $u->name }}</div>
                                    @if($u->id === auth()->id())
                                        <div style="font-size:11px;color:#2E6B52;font-weight:600;">To Ty</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td>
                            @if($isAdmin)
                                <span class="badge badge-admin"><i class="ti ti-shield-check"></i> Główny kontakt</span>
                            @else
                                <span class="badge badge-user"><i class="ti ti-user"></i> Użytkownik</span>
                            @endif
                        </td>
                        <td style="color:#555;">{{ $u->email }}</td>
                        <td style="text-align:right;">
                            @if($u->id !== auth()->id())
                                <form method="POST"
                                      action="{{ route('client.users.destroy', $u) }}"
                                      onsubmit="return confirm('Na pewno usunąć użytkownika {{ addslashes($u->name) }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-action" title="Odepnij użytkownika">
                                        <i class="ti ti-user-minus"></i>
                                    </button>
                                </form>
                            @else
                                <span style="font-size:11px;color:#aaa;">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

{{-- ══════ SEKCJA ZARCHIWIZOWANYCH ══════ --}}
@if($archivedUsers->isNotEmpty())
<div class="archived-card">
    <div class="archived-header" onclick="toggleArchived()">
        <div class="archived-header-title">
            <i class="ti ti-archive"></i>
            Zarchiwizowani użytkownicy ({{ $archivedUsers->count() }})
        </div>
        <i class="ti ti-chevron-down archived-header-toggle" id="archivedToggleIcon"></i>
    </div>
    <div class="archived-body" id="archivedBody">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Użytkownik</th>
                    <th>E-mail</th>
                    <th>Zarchiwizowany</th>
                    <th style="text-align:right;width:120px;">Akcje</th>
                </tr>
            </thead>
            <tbody>
                @foreach($archivedUsers as $u)
                    <tr>
                        <td>
                            <div class="user-cell">
                                <div class="u-avatar" style="opacity:.5;"><x-user-avatar :user="$u" /></div>
                                <div class="user-name" style="color:#888;">{{ $u->name }}</div>
                            </div>
                        </td>
                        <td style="color:#aaa;">{{ $u->email }}</td>
                        <td style="color:#aaa;font-size:12px;">
                            {{ $u->pivot->deleted_at ? \Carbon\Carbon::parse($u->pivot->deleted_at)->format('d.m.Y H:i') : '—' }}
                        </td>
                        <td style="text-align:right;">
                            <form method="POST"
                                  action="{{ route('client.users.permanent-delete', $u) }}"
                                  onsubmit="return confirm('Trwale usunąć użytkownika {{ addslashes($u->name) }} z firmy? Tej operacji nie można cofnąć.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-danger">
                                    <i class="ti ti-trash"></i> Usuń trwale
                                </button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- ══════ MODAL: DODAJ UŻYTKOWNIKA ══════ --}}
<div id="addModal" class="modal-overlay" onclick="closeModalOutside(event)">
    <div class="modal-box" onclick="event.stopPropagation()">
        <button class="modal-close-btn" onclick="closeAddModal()">&times;</button>
        <div class="modal-title"><i class="ti ti-user-plus" style="margin-right:8px;"></i>Nowy użytkownik</div>
        <div class="modal-subtitle">Wypełnij dane i zdecyduj, czy wysłać klientowi dane dostępowe.</div>

        <form id="addUserForm" method="POST" action="{{ route('client.users.store') }}" onsubmit="return !this.elements.send_email.checked || confirm('Wysłać klientowi dane dostępowe mailem?');">
            @csrf
            <div class="mf-row">
                <div class="mf-group">
                    <label class="mf-label" for="add_first_name">Imię</label>
                    <input id="add_first_name" type="text" name="first_name" class="mf-input @error('first_name') mf-input-error @enderror" placeholder="Jan" required value="{{ old('first_name') }}">
                    @error('first_name')<div class="mf-error">{{ $message }}</div>@enderror
                </div>
                <div class="mf-group">
                    <label class="mf-label" for="add_last_name">Nazwisko</label>
                    <input id="add_last_name" type="text" name="last_name" class="mf-input @error('last_name') mf-input-error @enderror" placeholder="Kowalski" required value="{{ old('last_name') }}">
                    @error('last_name')<div class="mf-error">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="mf-row">
                <div class="mf-group">
                    <label class="mf-label" for="add_email">Adres e-mail</label>
                    <input id="add_email" type="email" name="email" class="mf-input @error('email') mf-input-error @enderror" placeholder="jan@firma.pl" required value="{{ old('email') }}">
                    @error('email')<div class="mf-error">{{ $message }}</div>@enderror
                </div>
                <div class="mf-group">
                    <label class="mf-label" for="add_phone">Telefon</label>
                    <input id="add_phone" type="tel" name="phone" class="mf-input @error('phone') mf-input-error @enderror" placeholder="+48 000 000 000" value="{{ old('phone') }}">
                    @error('phone')<div class="mf-error">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="mf-group">
                <div class="mf-label" style="margin-bottom:10px;">Rola w firmie</div>
                <div class="role-options">
                    <label class="role-option" id="roleOptAdmin" onclick="selectRole(this, '1')">
                        <input type="radio" name="is_admin" value="1" required checked>
                        <i class="ti ti-shield-check"></i>
                        <strong>Główny kontakt</strong>
                        <span>Zarządza użytkownikami i ustawieniami firmy</span>
                    </label>
                    <label class="role-option" id="roleOptUser" onclick="selectRole(this, '0')">
                        <input type="radio" name="is_admin" value="0" required>
                        <i class="ti ti-user"></i>
                        <strong>Użytkownik</strong>
                        <span>Dostęp do audytów, ofert i dokumentów</span>
                    </label>
                </div>
            </div>

            <label style="display:flex;align-items:center;gap:8px;margin:14px 0;font-size:13px;cursor:pointer;">
                <input type="checkbox" name="send_email" value="1">
                Wyślij klientowi mail z hasłem tymczasowym
            </label>

            <button type="submit" class="btn-modal-submit">
                <i class="ti ti-user-plus" style="margin-right:6px;"></i>Dodaj użytkownika
            </button>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
    function openAddModal() {
        document.getElementById('addModal').classList.add('open');
        // Update selected class based on checked radio
        document.querySelectorAll('.role-option').forEach(o => {
            if (o.querySelector('input[type="radio"]:checked')) {
                o.classList.add('selected');
            }
        });
    }

    function closeAddModal() {
        document.getElementById('addModal').classList.remove('open');
        document.getElementById('addUserForm').reset();
    }

    function closeModalOutside(e) {
        if (e.currentTarget === e.target) closeAddModal();
    }

    function selectRole(el, val) {
        document.querySelectorAll('.role-option').forEach(o => o.classList.remove('selected'));
        el.classList.add('selected');
        el.querySelector('input[type="radio"]').checked = true;
    }

    function toggleArchived() {
        const body = document.getElementById('archivedBody');
        const icon = document.getElementById('archivedToggleIcon');
        if (body) body.classList.toggle('open');
        if (icon) icon.classList.toggle('open');
    }

    // Re-open modal on validation error
    @if($errors->any())
        openAddModal();
    @endif
</script>
@endpush
