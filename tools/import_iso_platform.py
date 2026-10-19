"""Import the October source package as inert JSON. Never execute supplied HTML/JS."""
import hashlib
import html
import json
import pathlib
import re
import sys
import difflib
import openpyxl

root = pathlib.Path(__file__).resolve().parents[1]
source = pathlib.Path(sys.argv[1])
destination = root / 'resources/iso50001/platform-2026-10-18'
destination.mkdir(exist_ok=True)
decoder = json.JSONDecoder()


class LiteralReader:
    """Read data literals only: objects, arrays, strings, numbers and string joins."""
    def __init__(self, text):
        self.text, self.pos = text, 0

    def skip(self):
        while True:
            m = re.match(r'\s+|/\*.*?\*/|//[^\n]*', self.text[self.pos:], re.S)
            if not m:
                return
            self.pos += m.end()

    def token(self, pattern):
        self.skip()
        m = re.match(pattern, self.text[self.pos:])
        if not m:
            raise ValueError('Not a literal')
        self.pos += m.end()
        return m[0]

    def read(self):
        self.skip()
        c = self.text[self.pos:self.pos + 1]
        if c == '{':
            self.pos += 1
            out = {}
            self.skip()
            while self.text[self.pos] != '}':
                key = self.read() if self.text[self.pos] == '"' else self.token(r'[A-Za-z_$][\w$]*')
                self.token(':')
                out[key] = self.read()
                self.skip()
                if self.text[self.pos] != ',':
                    break
                self.pos += 1
                self.skip()
            self.token('}')
            return out
        if c == '[':
            self.pos += 1
            out = []
            self.skip()
            while self.text[self.pos] != ']':
                out.append(self.read())
                self.skip()
                if self.text[self.pos] != ',':
                    break
                self.pos += 1
                self.skip()
            self.token(']')
            return out
        if c == '"' or c in '-0123456789':
            value, length = decoder.raw_decode(self.text[self.pos:])
            self.pos += length
            self.skip()
            if self.text[self.pos:self.pos + 1] == '+':
                self.pos += 1
                value += self.read()
            return value
        name = self.token(r'[A-Za-z_$][\w$]*')
        if name in ['true', 'false', 'null']:
            return {'true': True, 'false': False, 'null': None}[name]
        if name == 'DZ':
            return 'Dyrektor Zakładu'
        if name == 'zob':
            self.token(r'\(')
            args = [self.read()]
            while True:
                self.skip()
                if self.text[self.pos] != ',':
                    break
                self.pos += 1
                args.append(self.read())
            self.token(r'\)')
            return dict(stan=args[0], dowod=args[1], kto=args[2] if len(args) > 2 else 'Dyrektor Zakładu', plan='')
        raise ValueError('Unsupported expression')
manifest = []
for path in sorted(source.glob('*.html')):
    raw = path.read_text(encoding='utf-8')
    match = re.search(r'ENESA_([\d.]+)_', path.name)
    section = match[1].replace('.', '-') if match else 'intro'
    block = re.search(r'<pre id=[\'"]js[\'"]>(.*?)</pre>', raw, re.S)
    data = json.loads(html.unescape(block[1]))
    variables = {}
    for assignment in re.finditer(r'\b(?:var|const|let)\s+([A-Z][A-Z_0-9]*)\s*=\s*', raw):
        try:
            value, _ = decoder.raw_decode(raw[assignment.end():])
        except ValueError:
            continue
        if isinstance(value, (dict, list)):
            variables[assignment[1]] = value
    example_literals = {}
    example_start = raw.find('function przyklad()')
    if example_start >= 0:
        example_body = raw[example_start:raw.find('function wyczysc', example_start)]
        for assignment in re.finditer(r'\b([A-Z][A-Z_0-9]*)\s*=\s*(?=[{\[])', example_body):
            try:
                example_literals[assignment[1]] = LiteralReader(example_body[assignment.end():]).read()
            except (ValueError, IndexError):
                pass
    output = {'source_file': path.name, 'source_sha256': hashlib.sha256(path.read_bytes()).hexdigest(), 'section': section, 'data': data, 'variables': variables, 'example_literals': example_literals}
    (destination / (section + '.json')).write_text(json.dumps(output, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    manifest.append({key: output[key] for key in ['section', 'source_file', 'source_sha256']})
    print(section, 'data:', ','.join(data), 'examples:', ','.join(k for k in variables if k.startswith('P_') or 'PRZYKLAD' in k))
(destination / 'manifest.json').write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')

# Update the existing inert libraries while retaining the platform form contracts.
def read(section):
    return json.loads((destination / (section + '.json')).read_text(encoding='utf-8'))


def write(name, value):
    (root / 'resources/iso50001' / name).write_text(json.dumps(value, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')


for section, name in [('4-1', 'factors-v2.json'), ('4-2', 'requirements-v2.json'), ('4-3', 'scope-v1.json'), ('4-4', 'system-v1.json')]:
    data = read(section)['data']
    if section == '4-2':
        previous = json.loads((root / 'resources/iso50001' / name).read_text(encoding='utf-8'))['wymagania']
        used = set()
        highest = max(int(row['id'].split('-')[1]) for row in previous)
        mapping = {}
        for row in data['wymagania']:
            candidates = [old for old in previous if old['strona_kod'] == row['strona_kod'] and old['id'] not in used]
            ranked = sorted(candidates, key=lambda old: difflib.SequenceMatcher(None, old['wymaganie'], row['wymaganie']).ratio(), reverse=True)
            old = ranked[0] if ranked and difflib.SequenceMatcher(None, ranked[0]['wymaganie'], row['wymaganie']).ratio() > .6 else None
            original = row['id']
            if old:
                row['id'] = old['id']
            else:
                highest += 1
                row['id'] = 'WYM-' + str(highest).zfill(2)
            row['source_id'] = original
            used.add(row['id'])
            mapping[original] = row['id']
        write('platform-2026-10-18/requirement-ids.json', mapping)
    write(name, {k: v for k, v in data.items() if not k.startswith('przyklad')})

for section, name, keys in [
    ('5-1', 'leadership-v1.json', ['PYTANIA_4', 'ZOBOWIAZANIA', 'PROCESY_BIZ', 'ZASOBY', 'NAWYKI', 'LISTA_51']),
    ('5-2', 'policy-v1.json', ['ELEMENTY', 'UTRZYMANIE', 'LISTA_52']),
    ('5-3', 'roles-v1.json', ['ZADANIA', 'ROLE_DOM', 'MAT_ZADANIA', 'LISTA_53']),
    ('6-1', 'risk-v1.json', ['DANE_WEJ', 'LISTA_61']),
    ('6-2', 'goals-v1.json', ['POLA_WER', 'LISTA_62']),
    ('6-3', 'energy-review-v1.json', ['METODY', 'LISTA_63']),
    ('6-4', 'indicators-v1.json', ['LISTA_64']),
    ('6-5', 'baseline-v1.json', ['LISTA_65']),
    ('6-6', 'data-plan-v1.json', ['KATEGORIE', 'LISTA_66', 'POLA_ZASAD']),
]:
    data = json.loads((root / 'resources/iso50001' / name).read_text(encoding='utf-8'))
    variables = read(section)['variables']
    for key in keys:
        if key not in variables:
            raise ValueError((section, key))
        data[key] = variables[key]
    write(name, data)
support = {section: read(section)['variables']['LISTA_' + section.replace('-', '')] for section in ['7-1', '7-2', '7-3']}
write('support-v1.json', support)

workbook_path = source / 'ENESA_Biblioteka_czynnikow_kontekstowych_4.1.xlsx'
workbook = openpyxl.load_workbook(workbook_path, read_only=True, data_only=True)
columns = ['kod', 'wymiar', 'tresc', 'wplyw', 'skutek', 'pokaz_gdy', 'tagi', 'rodzaj']
rows = [dict(zip(columns, row)) for row in workbook.worksheets[0].values if isinstance(row[0], str) and row[0].startswith('KTX-')]
lookup = {row['kod']: row for row in rows}
active = json.loads((root / 'resources/iso50001/factors-v2.json').read_text(encoding='utf-8'))['czynniki']
if set(lookup) != {row['kod'] for row in active}:
    raise ValueError('The spreadsheet and HTML factor identifiers differ; review before importing.')
differences = [{'kod': row['kod'], 'field': key, 'xlsx': lookup[row['kod']][key], 'html': row[key]} for row in active for key in columns[1:] if row[key] != lookup[row['kod']][key]]
write('platform-2026-10-18/factor-workbook.json', {'source_file': workbook_path.name, 'source_sha256': hashlib.sha256(workbook_path.read_bytes()).hexdigest(), 'rows': rows, 'differences': differences, 'resolution': 'Preserve reviewed corrections from the 4.1 HTML specification 2.1 and existing auditor verification; never infer a legal obligation from a threshold.'})
workbook.close()
