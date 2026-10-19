import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
const context = {}; vm.createContext(context); vm.runInContext(readFileSync(new URL('../../public/js/iso-plant-energy.js', import.meta.url), 'utf8'), context);
const {calculate, number, fossil} = context.EnesaPlantEnergy;
const factors = {LPG: {kWh: .0036, MWh: 3.6, GJ: 1, l: .0246, t: 46}, węgiel: {kWh: .0036, MWh: 3.6, GJ: 1, t: 23}};
const row = (c1, c2 = 'MWh', c0 = 'LPG') => ({c0, c1, c2});
test('LPG energy units and thresholds match backend precision', () => {
 for (const [quantity, unit, expected] of [[20000,'MWh',72],[1000000,'MWh',3600],[10000,'GJ',10],[85000,'GJ',85],['85000,001','GJ',85.000001],['85000,0001','GJ',85.0000001]]) assert.equal(calculate([row(quantity,unit)],factors).value,expected);
});
test('unknown and incomplete rows never become zero or partial totals', () => {
 for (const value of [null,undefined,'','nie wiem','12abc',-1,NaN,Infinity,'1,2,3']) {assert.equal(number(value),null);assert.equal(calculate([row(20),row(value)],factors).value,null);}
 assert.equal(calculate([row(0)],factors).value,0);
 assert.equal(calculate([row(20)],factors,true).value,null);
 assert.equal(calculate([row(20),{}],factors).value,.072);
 const bad=calculate([row(20),row(1,'l','węgiel')],factors);
 assert.equal(bad.value,null);assert.match(bad.detail,/Wiersz 2 — węgiel \/ l/);
 assert.equal(calculate([row(1,'MWh','nieznany')],factors).value,null);
});
test('decimal commas grouping mixed fuels zero and scientific notation are consistent', () => {
 assert.equal(calculate([row('20 000,5'),row('0','GJ','węgiel'),row(1000,'kWh','węgiel')],factors).value,72.0054);
 assert.equal(number('1e3'),1000); assert.equal(number('0,0'),0); assert.equal(number('1\u00a0000,5'),1000.5);
});
test('hidden stale fuel choices cannot override a current district heat answer', () => {
 assert.equal(fossil('siec',['gaz ziemny']),'do potwierdzenia');
 assert.equal(fossil('brak',['gaz ziemny']),'nie');
 assert.equal(fossil('wlasne',['gaz ziemny'],true),null);
 assert.equal(fossil('oba',['gaz ziemny']),'tak');
 assert.equal(fossil('wlasne',['biomasa']),'nie');
});
