import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const view = readFileSync(new URL('../../resources/views/hr/index.blade.php', import.meta.url), 'utf8');
const routeCode = view.slice(view.indexOf('let tripRouteRequest'), view.indexOf('function setupPlaceSuggestions'))
    .replace("@js(route('hr.route.calculate'))", "'/hr/route/calculate'");
const helperNames = ['localDateValue', 'tripFields', 'showDateTimeParts', 'syncDurationParts', 'hoursToArrival'];
const helpers = view.split(/\r?\n/).filter(line => helperNames.some(name => line.startsWith('function ' + name + '(')) || line.startsWith('const tripDateParts=')).join('\n');

function harness(response) {
    const fields = new Map();
    const field = id => {
        if (!fields.has(id)) fields.set(id, { value: '', disabled: false });
        return fields.get(id);
    };
    const values = {
        'trip-origin': 'Cieszyn', 'trip-destination': 'Kraków',
        'trip-departure': '2026-09-29T08:00', 'trip-return-departure': '2026-09-29T16:00',
        'trip-distance': '111', 'trip-outbound-hours': '2.00', 'trip-return-hours': '2.00'
    };
    Object.entries(values).forEach(([id, value]) => { field(id).value = value; });
    let sent;
    const context = vm.createContext({
        document: { getElementById: field, querySelector: () => ({ content: 'csrf' }) },
        fetch: async (url, options) => { sent = { url, options }; return typeof response === 'function' ? response() : response; },
        showTripRouteDetails() {}, showTripRouteMap() {}, updateTripSummary() {}
    });
    vm.runInContext(helpers + '\n' + routeCode, context);
    return { context, field, sent: () => sent };
}

test('route fills total kilometers, both durations and arrival dates', async () => {
    const h = harness({ ok: true, status: 200, json: async () => ({ distance_km: 123.5, hours: 1.5 }) });
    await h.context.calculateGoogleRoute();
    assert.equal(h.sent().options.headers.Accept, 'application/json');
    assert.equal(h.field('trip-distance').value, '247.0');
    assert.equal(h.field('trip-outbound-hours').value, '1.50');
    assert.equal(h.field('trip-return-hours').value, '1.50');
    assert.equal(h.field('trip-outbound-arrival').value, '2026-09-29T09:30');
    assert.equal(h.field('trip-return').value, '2026-09-29T17:30');
    assert.equal(h.field('trip-route-calculate').disabled, false);
});

test('provider failure leaves manually entered values intact and shows useful error', async () => {
    const h = harness({ ok: false, status: 422, json: async () => ({ errors: { route: ['Sprawdź Routes API w Google Cloud.'] } }) });
    await h.context.calculateGoogleRoute();
    assert.equal(h.field('trip-distance').value, '111');
    assert.equal(h.field('trip-outbound-hours').value, '2.00');
    assert.match(h.field('trip-route-status').textContent, /Google Cloud/);
    assert.equal(h.field('trip-route-calculate').disabled, false);
});

test('invalid payload or expired session never produces NaN or overwrites form', async () => {
    for (const response of [
        { ok: true, status: 200, json: async () => ({ distance_km: 100, hours: null }) },
        { ok: false, status: 419, json: async () => { throw Error('not json'); } }
    ]) {
        const h = harness(response);
        await h.context.calculateGoogleRoute();
        assert.equal(h.field('trip-distance').value, '111');
        assert.equal(h.field('trip-route-calculate').disabled, false);
        assert.match(h.field('trip-route-status').textContent, /ręcznie/);
    }
});

test('late response cannot overwrite a different route or a reopened delegation', async () => {
    for (const reopen of [false, true]) {
        let resolve;
        const pending = new Promise(done => { resolve = done; });
        const h = harness(() => pending);
        const calculation = h.context.calculateGoogleRoute();
        if (reopen) vm.runInContext('tripRouteRequest++', h.context);
        else h.field('trip-destination').value = 'Warszawa';
        resolve({ ok: true, status: 200, json: async () => ({ distance_km: 123.5, hours: 1.5 }) });
        await calculation;
        assert.equal(h.field('trip-distance').value, '111');
    }
});
