/**
 * F17-D: the Address & Postal add-on's mapping of an OpenStreetMap (Nominatim) address to the parts of each format
 * (vendor/address/assets/js/address-lookup-efb.js, EFBAddrLookup.mapOsm), on fixed samples. Runs only where the
 * add-on's files are (vendor/address); without them it says so and passes nothing.
 *
 * Run: node tests/test-osm-fill-mapping.js
 */
'use strict';
const path = require('path');
const fs = require('fs');
const file = path.join(__dirname, '../vendor/address/assets/js/address-lookup-efb.js');
let pass = 0, fail = 0;
const t = (name, ok, info) => { if (ok) { pass++; console.log('[PASS] ' + name); } else { fail++; console.log('[FAIL] ' + name + (info ? ': ' + JSON.stringify(info) : '')); } };
if (!fs.existsSync(file)) { console.log('[SKIP] the Address & Postal add-on is not in vendor/address'); console.log('\nRESULTS: 0 passed, 0 failed'); process.exit(0); }
const L = require(file);
const tehran = { house_number: '12', road: 'خیابان ولیعصر', suburb: 'یوسف‌آباد', city: 'Tehran', state: 'Tehran', postcode: '1234567890', country: 'Iran' };
let m = L.mapOsm('ir', tehran);
t('ir: province, city, "neighbourhood، street", plaque, postal code', m.state === 'Tehran' && m.city === 'Tehran' && m.street === 'یوسف‌آباد، خیابان ولیعصر' && m.plaque === '12' && m.postal === '1234567890', m);
m = L.mapOsm('sa', { house_number: '2929', road: 'King Fahd Rd', neighbourhood: 'Al Malaz', city: 'Riyadh', state: 'Riyadh Region', postcode: '12345' });
t('sa: building number, street, district, region, city, postal code', m.building_no === '2929' && m.street === 'King Fahd Rd' && m.district === 'Al Malaz' && m.state === 'Riyadh Region' && m.city === 'Riyadh' && m.postal === '12345', m);
m = L.mapOsm('ae', { road: 'Hessa St', city_district: 'Al Barsha', city: 'Dubai', state: 'Dubai', house_number: '12' });
t('ae: area from the city district, street, building', m.area === 'Al Barsha' && m.street === 'Hessa St' && m.building === '12' && m.state === 'Dubai', m);
m = L.mapOsm('eg', { road: 'Makram Ebeid', suburb: 'Nasr City', city: 'Cairo', state: 'Cairo', house_number: '5' });
t('eg: street, district, building number', m.street === 'Makram Ebeid' && m.district === 'Nasr City' && m.building_no === '5', m);
m = L.mapOsm('us', { house_number: '1600', road: 'Pennsylvania Avenue NW', city: 'Washington', state: 'District of Columbia', postcode: '20500' });
t('us: "number road" on line 1, city, state, ZIP', m.line1 === '1600 Pennsylvania Avenue NW' && m.city_text === 'Washington' && m.state === 'District of Columbia' && m.postal === '20500', m);
m = L.mapOsm('uk', { house_number: '10', road: 'Downing Street', town: 'London', county: 'Greater London', postcode: 'SW1A 2AA' });
t('uk: line 1, town, county, postcode', m.line1 === '10 Downing Street' && m.city_text === 'London' && m.county === 'Greater London' && m.postal === 'SW1A 2AA', m);
m = L.mapOsm('de', { house_number: '77', road: 'Unter den Linden', city: 'Berlin', postcode: '10117' });
t('de: "road number" (German order), city', m.line1 === 'Unter den Linden 77' && m.city_text === 'Berlin', m);
m = L.mapOsm('jp', { quarter: '丸の内', road: '', house_number: '1-1', city: '千代田区', state: '東京都', postcode: '100-0001' });
t('jp: prefecture, city, line 1 from the quarter and number', m.state === '東京都' && m.city_text === '千代田区' && m.line1 === '丸の内 1-1', m);
m = L.mapOsm('tr', { suburb: 'Caferağa', road: 'Moda Cd.', house_number: '12', town: 'Kadıköy', city: 'İstanbul', state: 'İstanbul', postcode: '34710' });
t('tr: province, district (the town) as the city, neighbourhood, street, building', m.state === 'İstanbul' && m.city === 'Kadıköy' && m.neighbourhood === 'Caferağa' && m.street === 'Moda Cd.' && m.building_no === '12', m);
m = L.mapOsm('intl', { road: 'Main St', city: 'Springfield', country: 'United States' });
t('intl: line 1, city, country; empty parts left out', m.line1 === 'Main St' && m.city_text === 'Springfield' && m.country === 'United States' && !('postal' in m) && !('state' in m), m);
t('no address: an empty map', Object.keys(L.mapOsm('ir', {})).length === 0);
console.log(`\nRESULTS: ${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);
