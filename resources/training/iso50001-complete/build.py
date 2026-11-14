"""Build editable, offline HTML decks and the immutable deployment catalogue.

Run from the repository root. Rendering PNGs is a separate developer-only step.
"""
import hashlib
import html
import json
import subprocess
from pathlib import Path

from content import DECKS

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[2]
STAMP = '2026-11-14'


def escape(value):
    return html.escape(str(value), quote=True)


def cards(items, style='three'):
    return '<div class="grid '+style+' content">'+''.join(
        '<div class="card accent"><span class="label">'+str(i+1).zfill(2)+'</span><h3>'+escape(p['title'])+'</h3><p>'+escape(p['text'])+'</p></div>'
        for i, p in enumerate(items))+'</div>'


def rows(items):
    return '<div class="stack content">'+''.join(
        '<div class="qa"><span class="number">'+str(i+1)+'</span><h3>'+escape(p['title'])+'</h3><p class="answer">'+escape(p['text'])+'</p></div>'
        for i, p in enumerate(items))+'</div>'


def build():
    config = json.loads(subprocess.check_output(['php', '-r', "echo json_encode(require 'config/iso50001.php', JSON_UNESCAPED_UNICODE);"], cwd=ROOT))
    nodes = {}
    for chapter in config['chapters']:
        nodes[chapter['id']] = chapter
        for item in chapter.get('items', []):
            nodes[item['id']] = item
    expected = set(nodes) - {'4-1', '4-2', '4-3', '4-4'}
    if set(DECKS) != expected:
        raise ValueError(f'Missing: {expected-set(DECKS)}; extra: {set(DECKS)-expected}')
    catalogue = []
    css = (HERE / 'master.css').read_text(encoding='utf-8')
    for section, node in nodes.items():
        if section not in DECKS:
            continue
        data = DECKS[section]
        for field in ['principles', 'steps', 'dependencies', 'questions']:
            if len(data[field]) != 3:
                raise ValueError(f'{section}: expected three {field}')
        number, title = node['number'], node['title']
        slides = []

        def slide(heading, body, dark=False, label=None):
            slides.append('<section class="slide'+(' dark' if dark else '')+'" data-slide="'+str(len(slides)+1)+'">'
                '<div class="logo"><img src="../../../../../public/logo1.png" alt="Logo ENESA"><b>ENESA</b></div>'
                '<div class="eyebrow">'+escape(label or f'ISO 50001 · {number} · materiał szkoleniowy')+'</div>'
                '<h2>'+escape(heading)+'</h2>'+body+'</section>')

        slide(f'{number} {title}', '<p class="lead cover-purpose">'+escape(data['purpose'])+'</p>'
            '<div class="cover-path"><span>CEL</span><b>→</b><span>POWIĄZANIA</span><b>→</b><span>DOWODY</span></div>'
            '<p class="cover-note">Na podstawie książki Bronisława Pytla<br>„Energia pod kontrolą” oraz materiałów ENESA.</p>',
            True, 'ENESA · szkolenie do audytu ISO 50001')
        slide('Co jest istotą tego punktu?' if data['kind']=='clause' else 'Co obejmuje ten etap?', cards(data['principles']))
        if data['kind'] == 'clause':
            slide('Jak wykonać tę pracę?', rows(data['steps']))
        slide('Jak łączy się z pozostałymi punktami?', cards(data['dependencies'], 'three dependency'))
        if data['kind'] == 'clause':
            slide('Przykład do przeanalizowania', cards(data['example'], 'two')+'<div class="example-note">Przykład dydaktyczny — odnieś go do rzeczywistej sytuacji własnego zakładu.</div>')
            slide('Jakie informacje i dowody przygotować?', cards(data['evidence']))
            slide('Na co uważać?', rows(data['pitfalls']))
        else:
            slide('Ćwiczenie w Twojej organizacji', rows(data['steps']))
        slide('Sprawdź przed dalszą pracą', '<div class="checklist content">'+''.join(
            '<div><span class="number">'+str(i+1)+'</span><p>'+escape(q.strip())+'</p></div>' for i,q in enumerate(data['questions']))
            +'</div><p class="closing-note">Wskaż rzeczywisty przykład i źródło odpowiedzi.<br>Brak informacji zapisz jako zadanie do wyjaśnienia.</p>', True)
        count = len(slides)
        for i in range(count):
            footer = '<div class="ref">'+escape(data['source'])+'</div><footer><span>ENESA · ISO 50001 · '+escape(number)+' · Omówienie dydaktyczne</span><span>'+str(i+1)+' / '+str(count)+'</span></footer>'
            slides[i] = slides[i].replace('</section>', footer+'</section>')
        directory = HERE / 'sections' / section
        directory.mkdir(parents=True, exist_ok=True)
        document = '<!doctype html><html lang="pl"><head><meta charset="utf-8"><title>'+escape(f'{number} {title} — ENESA')+'</title><style>'+css+'</style></head><body>'+''.join(slides)+'</body></html>'
        (directory / 'presentation.html').write_text(document, encoding='utf-8', newline='\n')
        catalogue.append(dict(section_id=section, title=f'{number} {title}',
            description=data['purpose']+' Cel, powiązania, praktyka i pytania kontrolne. Materiał na podstawie książki Bronisława Pytla „Energia pod kontrolą”.',
            original_filename=f'ENESA_ISO50001_{section}_presentation_{STAMP}.html',
            slide_count=count, source=data['source'], source_sha256=hashlib.sha256(document.encode()).hexdigest()))
    (HERE / 'catalogue.json').write_text(json.dumps(catalogue, ensure_ascii=False, indent=2)+'\n', encoding='utf-8', newline='\n')
    print(f'{len(catalogue)} presentations; {sum(d["slide_count"] for d in catalogue)} slides; {len(nodes)} sections including existing 4.1–4.4.')


if __name__ == '__main__':
    build()
