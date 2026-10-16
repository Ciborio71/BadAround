'use strict';
const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const data=require('./fixtures/sf8-italy-territories-2026.json');
const {resolveTerritory}=require('../wordpress/themes/badaround-child/assets/js/native-report/smart-address.js');
const source=fs.readFileSync(path.join(__dirname,'../wordpress/themes/badaround-child/assets/js/native-report/smart-address.js'),'utf8');
const alias=JSON.parse(source.split('const PROVINCE_SIGLE = ')[1].split(';\n')[0]);
const entries=[...data.regions,...data.provinces,...data.municipalities].map(({id,name,parent})=>({id,name,parent}));
const codeByRegion=new Map();
for(const [region,str] of Object.entries(alias))for(const pair of str.split(';')){
  const split=pair.lastIndexOf(':');
  codeByRegion.set(region+'|'+pair.slice(0,split),pair.slice(split+1));
}
const byId=new Map(entries.map(t=>[t.id,t]));
const component=(type,longText,shortText)=>({types:[type],longText,shortText});
const input=(region,province,municipality,shortProvince,options={})=>[
  component('country','Italia','IT'),
  component('administrative_area_level_1',options.regionAlias||region,options.regionAlias||region),
  component('administrative_area_level_2',options.provinceLabel||province,shortProvince),
  component(options.municipalityType||'locality',options.municipalityLabel||municipality,options.municipalityLabel||municipality),
  ...(options.sublocality?[component('sublocality',options.sublocality,'')]:[])
];
const regionName=id=>{const province=byId.get(id);return byId.get(province.parent).name;};
test('SF8 crosswalk: exactly 110, all canonical provinces, no stale/unknown entries or duplicate codes per region',()=>{
  assert.equal(data.regions.length,20);
  assert.equal(data.provinces.length,110);
  assert.equal(data.municipalities.filter(t=>t.code).length,64);
  assert.equal(codeByRegion.size,110);
  for(const p of data.provinces){
    const region=byId.get(p.parent).name, code=codeByRegion.get(region+'|'+p.name);
    assert.match(code||'',/^[A-Z]{2}$/,region+' '+p.name);
    assert.equal([...codeByRegion.entries()].filter(([k,v])=>k.startsWith(region+'|')&&v===code).length,1);
  }
  for(const key of codeByRegion.keys()){
    const [r,...rest]=key.split('|'),p=rest.join('|');
    assert(data.provinces.some(t=>t.name===p&&byId.get(t.parent).name===r),'stale sigla: '+key);
  }
  assert.equal(codeByRegion.get('Sardegna|Gallura Nord-Est Sardegna'),'OT');
  assert.equal(codeByRegion.get('Sardegna|Medio Campidano'),'VS');
  assert.equal(codeByRegion.get('Sardegna|Ogliastra'),'OG');
  assert.equal(codeByRegion.get('Sardegna|Sulcis Iglesiente'),'SU');
});
test('SF8: 64 live ISTAT municipalities (20 regions) reconcile through sigla and parent hierarchy',()=>{
  let count=0;
  for(const m of data.municipalities.filter(t=>t.code)){
    const p=byId.get(m.parent),region=byId.get(p.parent).name;
    const short=codeByRegion.get(region+'|'+p.name);
    const result=resolveTerritory(input(region,p.name,m.name,short),entries);
    assert.deepEqual(result.location,{region,province:p.name,municipality:m.name,locality:''},[region,p.name,m.name,result.error].join('/'));
    assert.equal(result.country,'IT');
    assert.equal(result.termIds.municipality,m.id);
    count++;
  }
  assert.equal(count,64);
});
test('SF8: 110 provincial sigle across all 20 regions resolve only as canonical children',()=>{
  for(const p of data.provinces){
    const r=byId.get(p.parent),sigla=codeByRegion.get(r.name+'|'+p.name);
    // Fixture-only child; never a proposed WordPress taxonomy mutation.
    const id=900000+p.id, fixture=[...entries,{id,parent:p.id,name:'Municipio SF8 test'}];
    const result=resolveTerritory(input(r.name,p.name,'Municipio SF8 test',sigla,{municipalityType:'administrative_area_level_3'}),fixture);
    assert.equal(result.termIds?.province,p.id,[r.name,p.name,sigla,result.error].join(' / '));
    assert.equal(result.termIds?.municipality,id);
  }
});
test('SF8: plain labels and prefixed metropolitan/provincial names',()=>{
  for(const m of data.municipalities.filter(t=>t.code).slice(0,35)){
    const p=byId.get(m.parent),r=byId.get(p.parent);
    const res=resolveTerritory(input(r.name,p.name,m.name,p.name,{provinceLabel:'Provincia di '+p.name}),entries);
    assert.equal(res.termIds?.municipality,m.id,JSON.stringify({m:m.name,p:p.name,r:r.name,error:res.error}));
  }
  for(const [region,province,municipality,sigla] of [
    ['Lazio','Roma','Pomezia','RM'],
    ['Campania','Napoli','Napoli','NA'],
    ['Lombardia','Milano','Milano','MI'],
    ['Piemonte','Torino','Torino','TO'],
    ['Sicilia','Catania','Catania','CT']
  ]){
    const x=resolveTerritory(input(region,province,municipality,sigla,{provinceLabel:'Città Metropolitana di '+province}),entries);
    assert.equal(x.location?.municipality,municipality);
  }
});
test('SF8: preserved SF6 Piazza del Plebiscito / Napoli canonical territorial identity',()=>{
  const result=resolveTerritory(input('Campania','Napoli','Napoli','NA'),entries);
  assert.equal(result.location?.municipality,'Napoli');
  assert.equal(result.location?.province,'Napoli');
});
test('SF8: preserved SF7 Rocca di Mezzo AQ canonical territorial identity',()=>{
  const result=resolveTerritory(input('Abruzzo',"L'Aquila",'Rocca di Mezzo','AQ',{provinceLabel:'Provincia dell’Aquila'}),entries);
  assert.equal(result.location?.municipality,'Rocca di Mezzo');
  assert.equal(result.termIds?.municipality,5628);
});
test('SF8: locality different from municipality is retained only when canonical child exists',()=>{
  const result=resolveTerritory(input('Lazio','Roma','Pomezia','RM',{sublocality:'Torvaianica'}),entries);
  assert.equal(result.location?.municipality,'Pomezia');
  assert.equal(result.location?.locality,'Torvaianica');
  assert.equal(result.termIds.locality,5);
});
test('SF8: mixed punctuation, accents, case, whitespace and hyphens remain deterministic',()=>{
  for(const [name,variant] of [['Forlì','FORLI'],['Reggio nell\'Emilia','REGGIO NELL’EMILIA'],['Pont-Saint-Martin','PONT SAINT MARTIN']]){
    const m=data.municipalities.find(x=>x.name===name),p=byId.get(m.parent),r=byId.get(p.parent);
    const result=resolveTerritory(input(r.name,p.name,m.name,codeByRegion.get(r.name+'|'+p.name),{municipalityLabel:'  '+variant+'  '}),entries);
    assert.equal(result.termIds?.municipality,m.id,name);
  }
});
const negative=[
 ['region/municipality contradiction',input('Lazio','Roma','Rocca di Mezzo','RM')],
 ['province/municipality contradiction',input('Abruzzo','Pescara','Rocca di Mezzo','PE')],
 ['no canonical municipality',input('Abruzzo',"L'Aquila",'Non esiste questo Comune','AQ')],
 ['missing province',input('Lazio','','Pomezia',''), 'unresolved_province'],
 ['missing municipality',[component('country','Italia','IT'),component('administrative_area_level_1','Lazio','Lazio'),component('administrative_area_level_2','Roma','RM')]],
 ['locality wrongly used as municipality',input('Lazio','Roma','Torvaianica','RM')],
 ['foreign place',input('France','Paris','Paris','75').map(x=>x.types.includes('country')?component('country','France','FR'):x)],
 ['wrong region same province code',input('Lombardia','Napoli','Napoli','NA')],
 ['unknown region',input('Atlantide','Roma','Pomezia','RM')]
];
for(const [name,components,expected] of negative)test('SF8 FAIL CLOSED: '+name,()=>{
  const x=resolveTerritory(components,entries);
  assert(x.error,name+' must never produce a fabricated identity');
  if(expected)assert.equal(x.error,expected);
});
test('SF8 FAIL CLOSED: duplicate homonymous municipality under same parent',()=>{
  const p=data.provinces.find(p=>p.name==='Roma'&&byId.get(p.parent).name==='Lazio');
  const conflict=[...entries,{id:999999,parent:p.id,name:'Pomezia'}];
  assert.equal(resolveTerritory(input('Lazio','Roma','Pomezia','RM'),conflict).error,'unresolved_municipality');
});
test('SF8 FAIL CLOSED: conflicting locality-vs-administrative_area_level_3 municipality',()=>{
  const fake=[...entries,{id:999998,parent:3,name:'Pomezia Terza'}];
  const google=input('Lazio','Roma','Pomezia','RM');
  google.push(component('administrative_area_level_3','Pomezia Terza',''));
  assert.equal(resolveTerritory(google,fake).error,'conflicting_municipality');
});
test('SF8: no global substring heuristics, geolocation or public coordinate changes',()=>{
  assert(!source.includes('google.maps.Map('));
  assert(!source.includes('register_term'));
  assert(!source.includes('fuzzy'));
  assert(!source.includes('get_ancestors'));
});

test('SF8: province full long name and contradictory short code fail closed',()=>{
  const x=resolveTerritory(input('Lazio','Milano','Roma','RM',{provinceLabel:'Milano'}),entries);
  assert.equal(x.error,'unresolved_province');
  const y=resolveTerritory(input('Lazio','Roma','Roma','MI'),entries);
  assert.equal(y.error,'unresolved_province');
});
test('SF8: historical or irrelevant province alias does not invent missing canonical parent',()=>{
  const x=resolveTerritory(input('Sardegna','Sud Sardegna','Cagliari','SU',{provinceLabel:'Sud Sardegna'}),entries);
  assert(x.error);
});
test('SF8: full provincial designation with genitive article is resolved by canonical parent',()=>{
  const x=resolveTerritory(input('Abruzzo',"L'Aquila",'Rocca di Mezzo','',{provinceLabel:'Provincia dell’Aquila'}),entries);
  assert.equal(x.location?.municipality,'Rocca di Mezzo');
});
