<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Ankieta &mdash; {{ $broker->name }}</title>
<link rel="icon" href="data:,">
<style>
  * { box-sizing: border-box; }
  body { margin:0; background:#F4F1EA; font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif; color:#1A1A1A; }
  .wrap { max-width:720px; margin:0 auto; padding:24px 16px 60px; }
  .brand { display:flex; align-items:center; gap:14px; padding:18px 4px 22px; }
  .brand img { max-height:52px; max-width:220px; }
  .brand .bname { font-size:20px; font-weight:800; }
  .card { background:#fff; border:1px solid #E5E1D8; border-radius:14px; padding:24px; box-shadow:0 2px 14px rgba(0,0,0,.04); }
  h1 { font-size:19px; margin:0 0 6px; }
  .lead { color:#666; font-size:14px; margin:0 0 20px; }
  .rq-section-title { font-size:14px; font-weight:800; color:#1A1A1A; margin:22px 0 10px; padding-bottom:6px; border-bottom:1px solid #E5E1D8; }
  .field-group { margin-bottom:16px; }
  .field-label { display:block; font-size:13px; font-weight:700; margin-bottom:5px; }
  .field-label .required { color:#DC2626; }
  .field-input { width:100%; background:#FAFAF6; border:1px solid #D0CCC0; border-radius:8px; padding:10px 12px; font-size:14px; outline:none; }
  .field-input:focus { border-color:#1A1A1A; background:#fff; }
  .btn { display:inline-flex; align-items:center; gap:8px; background:#1A1A1A; color:#fff; border:none; border-radius:9px; padding:12px 24px; font-size:15px; font-weight:700; cursor:pointer; }
  .btn-ghost { background:#fff; color:#1A1A1A; border:1px solid #C9C4B8; }
  .note { background:#F0FDF4; border:1px solid #86EFAC; color:#166534; border-radius:8px; padding:10px 14px; margin-bottom:16px; font-size:13px; }
  .foot { text-align:center; color:#9a958a; font-size:11px; margin-top:26px; line-height:1.6; }
  .done { text-align:center; padding:24px 8px; }
  .done .tick { width:56px; height:56px; border-radius:50%; background:#E8F5E9; color:#1B5E20; display:flex; align-items:center; justify-content:center; font-size:30px; margin:0 auto 14px; }
</style>
<script>
  (g=>{var h,a,k,p="The Google Maps JavaScript API",c="google",l="importLibrary",q="__ib__",m=document,b=window;b=b[c]||(b[c]={});var d=b.maps||(b.maps={}),r=new Set,e=new URLSearchParams,u=()=>h||(h=new Promise(async(f,n)=>{await (a=m.createElement("script"));e.set("libraries",[...r]+"");for(k in g)e.set(k.replace(/[A-Z]/g,t=>"_"+t[0].toLowerCase()),g[k]);e.set("callback",c+".maps."+q);a.src=`https://maps.${c}apis.com/maps/api/js?`+e;d[q]=f;a.onerror=()=>h=n(Error(p+" could not load."));a.nonce=m.querySelector("script[nonce]")?.nonce||"";m.head.append(a)}));d[l]?console.warn(p+" only loads once. Ignoring:",g):d[l]=(f,...n)=>r.add(f)&&u().then(()=>d[l](f,...n))})({    
    key: "{{ config('services.google.maps_key') }}",
    v: "weekly"
  });
</script>
<link rel="icon" type="image/png" sizes="114x114" href="{{ asset('logo1.png') }}">
<link rel="apple-touch-icon" href="{{ asset('logo1.png') }}">
</head>
<body>
<div class="wrap">
  <div class="brand">
    @if(!empty($broker->logo_path))
      <img src="{{ $broker->logo_path }}" alt="{{ $broker->name }}">
    @else
      <span class="bname">{{ $broker->name }}</span>
    @endif
  </div>

  @if($offerRequest->end_client_company || $offerRequest->end_client_name)
  <div style="padding:0 4px 14px;font-size:14px;color:#444;">
    Ankieta dla:
    <strong>{{ $offerRequest->end_client_company ?: $offerRequest->end_client_name }}</strong>
    @if($offerRequest->end_client_company && $offerRequest->end_client_name)
      <span style="color:#777;">({{ $offerRequest->end_client_name }})</span>
    @endif
  </div>
  @endif

  <div class="card">
    @if(!empty($submitted))
      <div class="done">
        <div class="tick">&#10003;</div>
        <h1>Dzi&#281;kujemy &mdash; ankieta zosta&#322;a wys&#322;ana.</h1>
        <p class="lead">Twoje odpowiedzi zosta&#322;y przekazane do przygotowania oferty.</p>
      </div>
    @else
      @if(!empty($draftSaved))
        <div class="note">Zapisano roboczo &mdash; mo&#380;esz wr&#243;ci&#263; do tego linku w dowolnej chwili i doko&#324;czy&#263;.</div>
      @endif

      <h1>{{ $template->name }}</h1>
      @if($template->description)<p class="lead">{{ $template->description }}</p>@endif

      <form method="POST" action="{{ route('public.survey.submit', $offerRequest->public_token) }}" id="survey-form">
        @csrf
        <div id="dynamic-fields"></div>
        <div style="margin-top:22px;display:flex;flex-wrap:wrap;gap:10px;">
          <button type="submit" name="mode" value="submit" class="btn">Wy&#347;lij ankiet&#281;</button>
          <button type="submit" name="mode" value="draft" formnovalidate class="btn btn-ghost">Zapisz roboczo</button>
          <button type="submit" formaction="{{ route('public.survey.pdf', $offerRequest->public_token) }}" formtarget="_blank" formnovalidate class="btn btn-ghost">Pobierz PDF</button>
        </div>
      </form>
    @endif
  </div>

  <div class="foot">
    Formularz przygotowany przez {{ $broker->name }}.
    {{-- TODO RODO: uzupelnij administratora danych osobowych zgodnie z ustaleniami prawnymi --}}
  </div>
</div>

@unless(!empty($submitted))
<script>
const TEMPLATE_FIELDS = @json($template->fields);
const RESPONSES = @json($responses ?? []);

function renderRequestFields(fields, container) {
    container.innerHTML = '';
    const nodes = Array.isArray(fields) ? fields : [];
    const hasSections = nodes.some(f => f && f.type === 'section');

    if (hasSections) {
        nodes.forEach(function(sec) {
            if (!sec || sec.type !== 'section') return;
            if (sec.title) {
                const h = document.createElement('div');
                h.className = 'rq-section-title';
                h.textContent = sec.title;
                container.appendChild(h);
            }
            (sec.fields || []).forEach(function(f) { renderRespField(container, f); });
        });
    } else {
        nodes.forEach(function(f) { renderRespField(container, f); });
    }
}

function renderRespField(parent, field) {
    const group = document.createElement('div');
    group.className = 'field-group';

    const label = document.createElement('label');
    label.className = 'field-label';
    label.innerHTML = field.label + (field.required ? ' <span class="required">*</span>' : '');
    group.appendChild(label);

    const saved = RESPONSES[field.key];

    if (field.type === 'address') {
        renderAddressField(parent, group, field, saved);
        return;
    }

    let input;
    if (field.type === 'select') {
        input = document.createElement('select');
        input.className = 'field-input';
        let html = '<option value="">&#8212; wybierz &#8212;</option>';
        (field.options || []).forEach(function(o) {
            const v = String(o).replace(/"/g, '&quot;');
            html += '<option value="' + v + '">' + v + '</option>';
        });
        input.innerHTML = html;
    } else if (field.type === 'textarea') {
        input = document.createElement('textarea');
        input.rows = 3;
        input.style.resize = 'vertical';
        input.className = 'field-input';
    } else {
        input = document.createElement('input');
        input.type = field.type === 'number' ? 'number' : (field.type === 'date' ? 'date' : 'text');
        input.className = 'field-input';
    }
    input.name = 'form_responses[' + field.key + ']';
    if (field.required) input.required = true;
    group.appendChild(input);
    parent.appendChild(group);

    if (field.type !== 'select' && saved != null) {
        input.value = saved;
    }

    if (field.type === 'select') {
        const host = document.createElement('div');
        host.style.cssText = 'margin-left:4px;';
        parent.appendChild(host);

        const wraps = {};
        Object.keys(field.branches || {}).forEach(function(opt) {
            const wrap = document.createElement('div');
            wrap.style.cssText = 'display:none;border-left:3px solid #FCD34D;padding-left:12px;margin:4px 0 8px;';
            (field.branches[opt] || []).forEach(function(cf) { renderRespField(wrap, cf); });
            setBranchDisabled(wrap, true);
            host.appendChild(wrap);
            wraps[opt] = wrap;
        });

        input.addEventListener('change', function() {
            Object.keys(wraps).forEach(function(opt) {
                const show = (opt === input.value);
                wraps[opt].style.display = show ? 'block' : 'none';
                setBranchDisabled(wraps[opt], !show);
            });
        });

        if (saved != null && saved !== '') {
            input.value = saved;
            input.dispatchEvent(new Event('change'));
        }
    }
}

async function renderAddressField(parent, group, field, saved) {
    const v = (saved && typeof saved === 'object') ? saved : {};

    const box = document.createElement('div');
    box.style.cssText = 'border:1px solid #E5E1D8;border-radius:10px;padding:12px;background:#FCFBF8;';

    const acHost = document.createElement('div');
    acHost.style.marginBottom = '10px';
    box.appendChild(acHost);

    const grid = document.createElement('div');
    grid.style.cssText = 'display:grid;grid-template-columns:1fr 2fr;gap:8px;';

    const inputs = {};
    [['zip', 'Kod pocztowy'], ['city', 'Miejscowość'], ['street', 'Ulica'], ['no', 'Nr']].forEach(function(p) {
        const wrap = document.createElement('div');
        const lab = document.createElement('div');
        lab.textContent = p[1];
        lab.style.cssText = 'font-size:11px;font-weight:700;color:#777;margin-bottom:3px;';
        const inp = document.createElement('input');
        inp.type = 'text';
        inp.className = 'field-input';
        inp.name = 'form_responses[' + field.key + '][' + p[0] + ']';
        inp.value = v[p[0]] || '';
        if (field.required && (p[0] === 'city' || p[0] === 'street')) inp.required = true;
        wrap.appendChild(lab);
        wrap.appendChild(inp);
        grid.appendChild(wrap);
        inputs[p[0]] = inp;
    });

    box.appendChild(grid);
    group.appendChild(box);
    parent.appendChild(group);

    if (typeof google === 'undefined' || !google.maps || !google.maps.importLibrary) return;

    try {
        const { PlaceAutocompleteElement } = await google.maps.importLibrary('places');

        const pac = new PlaceAutocompleteElement({ includedRegionCodes: ['pl'] });
        pac.style.width = '100%';
        acHost.appendChild(pac);

        pac.addEventListener('gmp-select', async function(event) {
            const place = event.placePrediction.toPlace();
            await place.fetchFields({ fields: ['addressComponents'] });

            const comps = place.addressComponents || [];
            const get = function(type) {
                const c = comps.find(function(x) { return (x.types || []).indexOf(type) !== -1; });
                return c ? (c.longText || c.shortText || '') : '';
            };

            inputs.zip.value    = get('postal_code');
            inputs.city.value   = get('locality') || get('postal_town') || get('administrative_area_level_3');
            inputs.street.value = get('route');
            inputs.no.value     = get('street_number');
        });
    } catch (e) {
        // Jeśli biblioteka niedostępna — zostają cztery pola do ręcznego wpisania.
    }
}

function setBranchDisabled(wrap, disabled) {
    wrap.querySelectorAll('input, select, textarea').forEach(function(elm) { elm.disabled = disabled; });
}

document.addEventListener('DOMContentLoaded', function() {
    renderRequestFields(TEMPLATE_FIELDS, document.getElementById('dynamic-fields'));
});
</script>
@endunless
</body>
</html>