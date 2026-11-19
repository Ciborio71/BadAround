const {test}=require('node:test'),assert=require('node:assert/strict');
const {create}=require('./native-media-dom-harness.cjs');
test('place choices follow event, preserve compatible values and clear incompatible values',()=>{
 const h=create();h.set('event.category','property');h.set('event.subtype','property_attempted_breakin');
 const select=h.doc.querySelector('[name="location.place_type"]');
 assert([...select.options].some(o=>o.value==='private_home'));assert(![...select.options].some(o=>o.value==='park_green'));
 h.set('location.place_type','private_home');h.set('event.subtype','property_theft');assert.equal(h.model.effective('location.place_type'),'private_home');
 h.set('event.category','hazard');h.set('event.subtype','hazard_road_obstruction');assert.equal(h.model.effective('location.place_type'),undefined);
 assert(![...select.options].some(o=>o.value==='private_home'));assert([...select.options].some(o=>o.value==='other'));
 h.set('location.place_type','road_sidewalk');h.set('event.subtype','hazard_falling_element');assert.equal(h.model.effective('location.place_type'),'road_sidewalk');
 assert(h.doc.querySelector('[data-native-field="location.place_type"] label').textContent.includes('Quale tipo di luogo'));h.close();
});
