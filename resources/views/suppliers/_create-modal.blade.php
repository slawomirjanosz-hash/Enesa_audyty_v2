<div class="supplier-modal" id="supplier-create-modal" aria-hidden="true">
    <div class="supplier-modal-card" role="dialog" aria-modal="true" aria-labelledby="supplier-create-title">
        <button class="supplier-modal-close" type="button" onclick="closeSupplierModal()" aria-label="Zamknij">&times;</button>
        <h2 id="supplier-create-title"><i class="ti ti-truck-delivery"></i> Dodaj dostawcę</h2>
        <p>Wypełnij dane firmy lub pobierz je automatycznie z GUS.</p>

        @if($errors->any())
            <div class="supplier-form-errors"><strong>Popraw błędy:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <form method="POST" action="{{ route('companies.store') }}">
            @csrf
            <label>Rodzaj firmy *</label>
            <select id="supplier-company-type" name="company_type" onchange="updateSupplierFields()" required>
                <option value="client" @selected(old('company_type', 'supplier') === 'client')>Klient</option>
                <option value="supplier" @selected(old('company_type', 'supplier') === 'supplier')>Dostawca</option>
            </select>

            <label>NIP firmy</label>
            <div class="supplier-nip-row">
                <input id="supplier-nip" name="nip" value="{{ old('nip') }}" placeholder="np. 527-000-11-22">
                <button type="button" onclick="fetchSupplierFromGus()">Pobierz z GUS</button>
            </div>
            <div id="supplier-gus-status" class="supplier-gus-status"></div>

            <label>Nazwa firmy *</label>
            <input id="supplier-company-name" name="name" value="{{ old('name') }}" required placeholder="Pobrana z GUS lub wpisz ręcznie">

            <div class="supplier-form-grid">
                <div><label>Adres</label><input id="supplier-company-address" name="address" value="{{ old('address') }}" placeholder="ul. Przykładowa 1"></div>
                <div><label>Miasto</label><input id="supplier-company-city" name="city" value="{{ old('city') }}" placeholder="Warszawa"></div>
                <div><label>Email</label><input type="email" name="email" value="{{ old('email') }}" placeholder="biuro@firma.pl"></div>
                <div><label>Telefon</label><input type="tel" name="phone" value="{{ old('phone') }}" placeholder="+48 000 000 000"></div>
            </div>

            <div id="supplier-profile-fields">
                <label>Co może dostarczać / jakie świadczy usługi</label>
                <textarea name="supplier_capabilities" rows="3" placeholder="np. dostawy armatury, montaż instalacji…">{{ old('supplier_capabilities') }}</textarea>
                <label>Materiały i asortyment</label>
                <textarea name="supplier_materials" rows="3" placeholder="np. pompy, przewody, zawory…">{{ old('supplier_materials') }}</textarea>
            </div>

            <div class="supplier-modal-actions">
                <button class="supplier-submit" type="submit"><i class="ti ti-plus"></i> <span>Dodaj dostawcę</span></button>
                <button class="supplier-cancel" type="button" onclick="closeSupplierModal()">Anuluj</button>
            </div>
        </form>
    </div>
</div>
