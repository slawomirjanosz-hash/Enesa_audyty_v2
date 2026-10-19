"""Archive the supplied presentation contract byte-for-byte; never execute source code.

Run after reviewing every file's 'Specyfikacja dla Sławka', not instead of that review.
The live renderer is isolated by IsoHtmlQuestionnaire's sandbox and CSP.
"""
import hashlib
import html
import json
import pathlib
import re
import sys

root = pathlib.Path(__file__).resolve().parents[1]
source = pathlib.Path(sys.argv[1])
destination = root / 'resources/iso50001/html-2026-10-18'
expected = {'intro', '4-1', '4-2', '4-3', '4-4', '5-1', '5-2', '5-3',
            '6-1', '6-2', '6-3', '6-4', '6-5', '6-6', '7-1', '7-2', '7-3', '7-4', '7-5',
            '8-1', '8-2', '8-3', '9-1-1', '9-1-2', '9-2', '9-3', '10-1', '10-2'}
documents = []
for path in sorted(source.glob('ENESA_*.html')):
    match = re.search(r'^ENESA_([\d.]+)_', path.name)
    section = match[1].replace('.', '-') if match else 'intro'
    if section not in expected or any(item['section'] == section for item in documents):
        raise ValueError(f'Unexpected or duplicate source section: {path.name}')
    raw = path.read_bytes()
    text = raw.decode('utf-8')
    tabs = []
    tab_block = re.search(r'var ZAKLADKI\s*=\s*\[(.*?)\];', text, re.S)
    if tab_block:
        tabs = re.findall(r'\{\s*id:\s*"([^"]+)",\s*t:\s*"([^"]+)"', tab_block[1])
    if section == '4-2':
        group_start = re.search(r'var GRUPY\s*=\s*', text).end()
        groups, _ = json.JSONDecoder().raw_decode(text[group_start:])
        tabs = [('klimat', 'Klimat i przegląd')] + [(group['id'], group['krotka']) for group in groups] + [('wlasne', 'Własne wymagania'), ('zatw', 'Zatwierdzenia')]
    if section == 'intro':
        block = re.search(r'<pre id=[\'"]js[\'"]>(.*?)</pre>', text, re.S)
        schema = json.loads(html.unescape(block[1]))
        tabs = [(group['id'], group['nazwa']) for group in schema['sekcje']]
    start = text.rfind('function zapisz()')
    end = text.find('function wczytaj()', start)
    state = list(dict.fromkeys(re.findall(r'\b([A-Z][A-Z_0-9]*):\s*\1\b', text[start:end])))
    if section == 'intro':
        state = ['ODP', 'STAN']
    documents.append({'section': section, 'file': path.name, 'sha256': hashlib.sha256(raw).hexdigest(),
                      'tabs': [{'id': key, 'title': title} for key, title in tabs], 'state': state, '_bytes': raw})
if {item['section'] for item in documents} != expected:
    raise ValueError('The package is incomplete; do not replace the active presentation contract.')
destination.mkdir(exist_ok=True)
for item in documents:
    (destination / (item['section'] + '.html')).write_bytes(item.pop('_bytes'))
(destination / 'manifest.json').write_text(json.dumps(documents, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
print(f'Archived {len(documents)} source HTMLs without executing or changing their contents.')
