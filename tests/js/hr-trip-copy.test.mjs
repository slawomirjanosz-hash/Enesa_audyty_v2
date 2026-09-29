import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const view = readFileSync(new URL('../../resources/views/hr/index.blade.php', import.meta.url), 'utf8');
const functions = view.split(/\r?\n/).filter(line => /^function (editTrip|copyTrip)\(/.test(line)).join('\n');

test('copy prefills data but retains create action and disabled PUT override', () => {
    const fields = new Map();
    const field = id => {
        if (!fields.has(id)) fields.set(id, {});
        return fields.get(id);
    };
    const context = vm.createContext({
        document: { getElementById: field },
        tripExtraCosts: { 42: { accommodation_cost: 180, other_cost: 25 } },
        tripDateParts: {},
        openNewTrip() {
            field('trip-form').action = '/hr/delegations';
            field('trip-method').disabled = true;
            field('trip-copy-notice').hidden = true;
        },
        showDateTimeParts() {}, syncDurationParts() {}, chooseHrVehicle() {},
        showTripRouteDetails() {}, showTripRouteMap() {}, updateTripSummary() {},
    });
    vm.runInContext(functions, context);
    const source = { id: 42, purpose: 'Spotkanie', user_id: 7, vehicle_type: 'private', notes: 'Notatka', departure_at: '2026-09-29T08:00' };
    context.editTrip(source);
    assert.equal(field('trip-method').disabled, false);
    context.copyTrip(source);
    assert.equal(field('trip-form').action, '/hr/delegations');
    assert.equal(field('trip-method').disabled, true);
    assert.equal(field('trip-modal-title').textContent, 'Kopiuj delegację');
    assert.equal(field('trip-copy-notice').hidden, false);
    assert.equal(field('trip-purpose').value, source.purpose);
    assert.equal(field('trip-departure').value, source.departure_at);
    assert.equal(field('trip-user-id').value, 7);
    assert.equal(field('trip-accommodation').value, 180);
    assert.equal(field('trip-other-cost').value, 25);
    assert.equal(field('trip-notes').value, 'Notatka');
    assert.equal(field('trip-vehicle-id').value, 'manual');
    assert.equal(field('trip-private-vehicle').checked, true);
    assert.equal(source.accommodation_cost, undefined);
    context.editTrip(source);
    assert.equal(field('trip-method').disabled, false);
    assert.equal(field('trip-copy-notice').hidden, true);
});
