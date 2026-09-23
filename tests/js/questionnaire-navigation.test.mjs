import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../public/js/questionnaire-navigation.js', import.meta.url), 'utf8');
function screen({width = 900, initialScroll = 0} = {}) {
    const events = {}, frames = [];
    const window = {scrollY: initialScroll, innerHeight: 800, addEventListener: (name, fn) => { events[name] = fn; }};
    const nav = {scrollLeft: 0, getBoundingClientRect: () => ({top: 0, bottom: 60, height: 60, left: 0, right: width})};
    const sections = [200, 1200, 2350].map((top, index) => ({
        id: `section-${index}`, style: {}, hidden: false,
        getClientRects() { return this.hidden ? [] : [{}]; },
        getBoundingClientRect: () => ({top: top - window.scrollY}),
    }));
    const links = sections.map((section, index) => ({
        hash: `#${section.id}`, attributes: {}, selected: false,
        classList: {toggle: (name, selected) => { links[index].selected = selected; }},
        setAttribute(name, value) { this.attributes[name] = value; },
        removeAttribute(name) { delete this.attributes[name]; },
        getBoundingClientRect: () => ({left: index * 140 - nav.scrollLeft, right: (index + 1) * 140 - nav.scrollLeft}),
    }));
    nav.querySelectorAll = () => links;
    const document = {querySelectorAll: () => [nav], getElementById: id => sections.find(s => s.id === id), documentElement: {scrollHeight: 2900}};
    vm.runInNewContext(source, {window, document, getComputedStyle: () => ({top: '0px'}), requestAnimationFrame: fn => frames.push(fn)});
    return {nav, links, sections, scroll(y) { window.scrollY = y; events.scroll(); while (frames.length) frames.shift()(); }, event(name) { events[name](); while (frames.length) frames.shift()(); }};
}

test('exactly one current section follows downward and upward scrolling', () => {
    const page = screen();
    assert.equal(page.links[0].attributes['aria-current'], 'location');
    page.scroll(1150);
    assert.deepEqual(page.links.map(l => l.selected), [false, true, false]);
    assert.equal(page.links[0].attributes['aria-current'], undefined);
    page.scroll(0);
    assert.deepEqual(page.links.map(l => l.selected), [true, false, false]);
    assert.equal(page.sections[0].style.scrollMarginTop, '76px');
});

test('short last section is selected at page bottom and mobile menu follows it', () => {
    const page = screen({width: 220});
    page.scroll(2100);
    assert.equal(page.links[2].selected, true);
    assert.ok(page.nav.scrollLeft > 0);
    page.scroll(0);
    assert.equal(page.links[0].selected, true);
});

test('restored scroll position, hash changes and hidden sections are handled', () => {
    const page = screen({initialScroll: 1300});
    assert.equal(page.links[1].selected, true);
    page.sections[1].hidden = true;
    page.event('hashchange');
    assert.equal(page.links[0].selected, true);
    page.event('resize');
    assert.equal(page.links.filter(l => l.selected).length, 1);
});
