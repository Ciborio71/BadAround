'use strict';
const {test}=require('node:test');
const assert=require('node:assert/strict');
const {resolveTerritory}=require('../wordpress/themes/badaround-child/assets/js/native-report/smart-address.js');
// Canonical term names, parents and UTS codes read-only from staging
// 2026-10-16. Sigle checked against Italy 2026 UTS/vehicle-acronym
// public dataset: guglielmo/geojson-italy@ba3347df3a53352891109f8198c597a0273684c0.
const CASES=[
  {
    "reg": "Sardegna",
    "rid": 7683,
    "prov": "Sassari",
    "pid": 7684,
    "code": "312",
    "sigla": "SS",
    "com": "Alghero",
    "mid": 7685
  },
  {
    "reg": "Marche",
    "rid": 4047,
    "prov": "Ancona",
    "pid": 4099,
    "code": "042",
    "sigla": "AN",
    "com": "Ancona",
    "mid": 4101
  },
  {
    "reg": "Valle d'Aosta/Vallée d'Aoste",
    "rid": 1087,
    "prov": "Valle d'Aosta/Vallée d'Aoste",
    "pid": 1088,
    "code": "007",
    "sigla": "AO",
    "com": "Aosta",
    "mid": 1091
  },
  {
    "reg": "Umbria",
    "rid": 4513,
    "prov": "Perugia",
    "pid": 4514,
    "code": "054",
    "sigla": "PG",
    "com": "Assisi",
    "mid": 4515
  },
  {
    "reg": "Puglia",
    "rid": 5941,
    "prov": "Bari",
    "pid": 6004,
    "code": "272",
    "sigla": "BA",
    "com": "Bari",
    "mid": 6009
  },
  {
    "reg": "Veneto",
    "rid": 2999,
    "prov": "Belluno",
    "pid": 3212,
    "code": "025",
    "sigla": "BL",
    "com": "Belluno",
    "mid": 3217
  },
  {
    "reg": "Trentino-Alto Adige/Südtirol",
    "rid": 2714,
    "prov": "Bolzano/Bozen",
    "pid": 2715,
    "code": "021",
    "sigla": "BZ",
    "com": "Bolzano",
    "mid": 2723
  },
  {
    "reg": "Sardegna",
    "rid": 7683,
    "prov": "Cagliari",
    "pid": 7973,
    "code": "318",
    "sigla": "CA",
    "com": "Cagliari",
    "mid": 7979
  },
  {
    "reg": "Sicilia",
    "rid": 6659,
    "prov": "Caltanissetta",
    "pid": 6922,
    "code": "085",
    "sigla": "CL",
    "com": "Caltanissetta",
    "mid": 6926
  },
  {
    "reg": "Molise",
    "rid": 5855,
    "prov": "Campobasso",
    "pid": 5856,
    "code": "070",
    "sigla": "CB",
    "com": "Campobasso",
    "mid": 5862
  },
  {
    "reg": "Trentino-Alto Adige/Südtirol",
    "rid": 2714,
    "prov": "Bolzano/Bozen",
    "pid": 2715,
    "code": "021",
    "sigla": "BZ",
    "com": "Castelrotto",
    "mid": 2734
  },
  {
    "reg": "Sicilia",
    "rid": 6659,
    "prov": "Catania",
    "pid": 6966,
    "code": "287",
    "sigla": "CT",
    "com": "Catania",
    "mid": 6981
  },
  {
    "reg": "Calabria",
    "rid": 6328,
    "prov": "Catanzaro",
    "pid": 6480,
    "code": "079",
    "sigla": "CZ",
    "com": "Catanzaro",
    "mid": 6493
  },
  {
    "reg": "Emilia-Romagna",
    "rid": 3735,
    "prov": "Forlì-Cesena",
    "pid": 4016,
    "code": "040",
    "sigla": "FC",
    "com": "Cesena",
    "mid": 4021
  },
  {
    "reg": "Calabria",
    "rid": 6328,
    "prov": "Cosenza",
    "pid": 6329,
    "code": "078",
    "sigla": "CS",
    "com": "Cosenza",
    "mid": 6372
  },
  {
    "reg": "Valle d'Aosta/Vallée d'Aoste",
    "rid": 1087,
    "prov": "Valle d'Aosta/Vallée d'Aoste",
    "pid": 1088,
    "code": "007",
    "sigla": "AO",
    "com": "Courmayeur",
    "mid": 1110
  },
  {
    "reg": "Piemonte",
    "rid": 48,
    "prov": "Cuneo",
    "pid": 533,
    "code": "004",
    "sigla": "CN",
    "com": "Cuneo",
    "mid": 609
  },
  {
    "reg": "Toscana",
    "rid": 4237,
    "prov": "Firenze",
    "pid": 4311,
    "code": "248",
    "sigla": "FI",
    "com": "Firenze",
    "mid": 4324
  },
  {
    "reg": "Liguria",
    "rid": 1163,
    "prov": "Genova",
    "pid": 1301,
    "code": "210",
    "sigla": "GE",
    "com": "Genova",
    "mid": 1326
  },
  {
    "reg": "Friuli-Venezia Giulia",
    "rid": 3566,
    "prov": "Gorizia",
    "pid": 3702,
    "code": "031",
    "sigla": "GO",
    "com": "Gorizia",
    "mid": 3709
  },
  {
    "reg": "Molise",
    "rid": 5855,
    "prov": "Isernia",
    "pid": 7111,
    "code": "094",
    "sigla": "IS",
    "com": "Isernia",
    "mid": 7134
  },
  {
    "reg": "Abruzzo",
    "rid": 5545,
    "prov": "L'Aquila",
    "pid": 5546,
    "code": "066",
    "sigla": "AQ",
    "com": "L'Aquila",
    "mid": 5595
  },
  {
    "reg": "Puglia",
    "rid": 5941,
    "prov": "Lecce",
    "pid": 6097,
    "code": "075",
    "sigla": "LE",
    "com": "Lecce",
    "mid": 6131
  },
  {
    "reg": "Marche",
    "rid": 4047,
    "prov": "Macerata",
    "pid": 4147,
    "code": "043",
    "sigla": "MC",
    "com": "Macerata",
    "mid": 4168
  },
  {
    "reg": "Basilicata",
    "rid": 6194,
    "prov": "Matera",
    "pid": 6296,
    "code": "077",
    "sigla": "MT",
    "com": "Matera",
    "mid": 6310
  },
  {
    "reg": "Trentino-Alto Adige/Südtirol",
    "rid": 2714,
    "prov": "Bolzano/Bozen",
    "pid": 2715,
    "code": "021",
    "sigla": "BZ",
    "com": "Merano",
    "mid": 2766
  },
  {
    "reg": "Sicilia",
    "rid": 6659,
    "prov": "Messina",
    "pid": 6769,
    "code": "283",
    "sigla": "ME",
    "com": "Messina",
    "mid": 6817
  },
  {
    "reg": "Lombardia",
    "rid": 1402,
    "prov": "Milano",
    "pid": 1766,
    "code": "215",
    "sigla": "MI",
    "com": "Milano",
    "mid": 1838
  },
  {
    "reg": "Lombardia",
    "rid": 1402,
    "prov": "Monza e della Brianza",
    "pid": 7575,
    "code": "108",
    "sigla": "MB",
    "com": "Monza",
    "mid": 7608
  },
  {
    "reg": "Campania",
    "rid": 4989,
    "prov": "Napoli",
    "pid": 5174,
    "code": "263",
    "sigla": "NA",
    "com": "Napoli",
    "mid": 5223
  },
  {
    "reg": "Sardegna",
    "rid": 7683,
    "prov": "Nuoro",
    "pid": 7778,
    "code": "114",
    "sigla": "NU",
    "com": "Nuoro",
    "mid": 7805
  },
  {
    "reg": "Sardegna",
    "rid": 7683,
    "prov": "Gallura Nord-Est Sardegna",
    "pid": 7751,
    "code": "113",
    "sigla": "OT",
    "com": "Olbia",
    "mid": 7768
  },
  {
    "reg": "Sardegna",
    "rid": 7683,
    "prov": "Oristano",
    "pid": 7832,
    "code": "115",
    "sigla": "OR",
    "com": "Oristano",
    "mid": 7877
  },
  {
    "reg": "Trentino-Alto Adige/Südtirol",
    "rid": 2714,
    "prov": "Bolzano/Bozen",
    "pid": 2715,
    "code": "021",
    "sigla": "BZ",
    "com": "Ortisei",
    "mid": 2776
  },
  {
    "reg": "Veneto",
    "rid": 2999,
    "prov": "Padova",
    "pid": 3413,
    "code": "028",
    "sigla": "PD",
    "com": "Padova",
    "mid": 3469
  },
  {
    "reg": "Sicilia",
    "rid": 6659,
    "prov": "Palermo",
    "pid": 6686,
    "code": "282",
    "sigla": "PA",
    "com": "Palermo",
    "mid": 6739
  },
  {
    "reg": "Emilia-Romagna",
    "rid": 3735,
    "prov": "Parma",
    "pid": 3783,
    "code": "034",
    "sigla": "PR",
    "com": "Parma",
    "mid": 3809
  },
  {
    "reg": "Umbria",
    "rid": 4513,
    "prov": "Perugia",
    "pid": 4514,
    "code": "054",
    "sigla": "PG",
    "com": "Perugia",
    "mid": 4553
  },
  {
    "reg": "Emilia-Romagna",
    "rid": 3735,
    "prov": "Piacenza",
    "pid": 3736,
    "code": "033",
    "sigla": "PC",
    "com": "Piacenza",
    "mid": 3765
  },
  {
    "reg": "Toscana",
    "rid": 4237,
    "prov": "Pisa",
    "pid": 4373,
    "code": "050",
    "sigla": "PI",
    "com": "Pisa",
    "mid": 4395
  },
  {
    "reg": "Lazio",
    "rid": 2,
    "prov": "Roma",
    "pid": 3,
    "code": "258",
    "sigla": "RM",
    "com": "Pomezia",
    "mid": 4
  },
  {
    "reg": "Friuli-Venezia Giulia",
    "rid": 3566,
    "prov": "Pordenone",
    "pid": 7060,
    "code": "093",
    "sigla": "PN",
    "com": "Pordenone",
    "mid": 7091
  },
  {
    "reg": "Basilicata",
    "rid": 6194,
    "prov": "Potenza",
    "pid": 6195,
    "code": "076",
    "sigla": "PZ",
    "com": "Potenza",
    "mid": 6258
  },
  {
    "reg": "Calabria",
    "rid": 6328,
    "prov": "Reggio Calabria",
    "pid": 6561,
    "code": "280",
    "sigla": "RC",
    "com": "Reggio di Calabria",
    "mid": 6624
  },
  {
    "reg": "Emilia-Romagna",
    "rid": 3735,
    "prov": "Rimini",
    "pid": 7385,
    "code": "099",
    "sigla": "RN",
    "com": "Rimini",
    "mid": 7396
  },
  {
    "reg": "Abruzzo",
    "rid": 5545,
    "prov": "L'Aquila",
    "pid": 5546,
    "code": "066",
    "sigla": "AQ",
    "com": "Rocca di Cambio",
    "mid": 5627
  },
  {
    "reg": "Abruzzo",
    "rid": 5545,
    "prov": "L'Aquila",
    "pid": 5546,
    "code": "066",
    "sigla": "AQ",
    "com": "Rocca di Mezzo",
    "mid": 5628
  },
  {
    "reg": "Lazio",
    "rid": 2,
    "prov": "Roma",
    "pid": 3,
    "code": "258",
    "sigla": "RM",
    "com": "Roma",
    "mid": 4832
  },
  {
    "reg": "Liguria",
    "rid": 1163,
    "prov": "Imperia",
    "pid": 1164,
    "code": "008",
    "sigla": "IM",
    "com": "Sanremo",
    "mid": 1217
  },
  {
    "reg": "Sardegna",
    "rid": 7683,
    "prov": "Sassari",
    "pid": 7684,
    "code": "312",
    "sigla": "SS",
    "com": "Sassari",
    "mid": 7734
  },
  {
    "reg": "Sicilia",
    "rid": 6659,
    "prov": "Siracusa",
    "pid": 7038,
    "code": "089",
    "sigla": "SR",
    "com": "Siracusa",
    "mid": 7055
  },
  {
    "reg": "Lombardia",
    "rid": 1402,
    "prov": "Sondrio",
    "pid": 1688,
    "code": "014",
    "sigla": "SO",
    "com": "Sondrio",
    "mid": 1748
  },
  {
    "reg": "Puglia",
    "rid": 5941,
    "prov": "Taranto",
    "pid": 6046,
    "code": "073",
    "sigla": "TA",
    "com": "Taranto",
    "mid": 6073
  },
  {
    "reg": "Piemonte",
    "rid": 48,
    "prov": "Torino",
    "pid": 49,
    "code": "201",
    "sigla": "TO",
    "com": "Torino",
    "mid": 317
  },
  {
    "reg": "Sicilia",
    "rid": 6659,
    "prov": "Trapani",
    "pid": 6660,
    "code": "081",
    "sigla": "TP",
    "com": "Trapani",
    "mid": 6681
  },
  {
    "reg": "Trentino-Alto Adige/Südtirol",
    "rid": 2714,
    "prov": "Trento",
    "pid": 2832,
    "code": "022",
    "sigla": "TN",
    "com": "Trento",
    "mid": 2964
  },
  {
    "reg": "Friuli-Venezia Giulia",
    "rid": 3566,
    "prov": "Trieste",
    "pid": 3728,
    "code": "032",
    "sigla": "TS",
    "com": "Trieste",
    "mid": 3734
  },
  {
    "reg": "Friuli-Venezia Giulia",
    "rid": 3566,
    "prov": "Udine",
    "pid": 3567,
    "code": "030",
    "sigla": "UD",
    "com": "Udine",
    "mid": 3689
  },
  {
    "reg": "Veneto",
    "rid": 2999,
    "prov": "Venezia",
    "pid": 3368,
    "code": "227",
    "sigla": "VE",
    "com": "Venezia",
    "mid": 3410
  },
  {
    "reg": "Veneto",
    "rid": 2999,
    "prov": "Verona",
    "pid": 3000,
    "code": "023",
    "sigla": "VR",
    "com": "Verona",
    "mid": 3091
  },
  {
    "reg": "Calabria",
    "rid": 6328,
    "prov": "Vibo Valentia",
    "pid": 7449,
    "code": "102",
    "sigla": "VV",
    "com": "Vibo Valentia",
    "mid": 7496
  }
];
const a=(type,longText,shortText='')=>({types:[type],longText,shortText});
const registry=c=>[
  {id:c.rid,parent:0,name:c.reg},
  {id:c.pid,parent:c.rid,name:c.prov,code:c.code},
  {id:c.mid,parent:c.pid,name:c.com}
];
const google=(c,provinceLong=c.prov,provinceShort=c.sigla)=>[
 a('country','Italia','IT'),
 a('administrative_area_level_1',c.reg,c.reg),
 a('administrative_area_level_2',provinceLong,provinceShort),
 a('locality',c.com,c.com)
];
test('SF8 fixture matrix covers 20 regions and nationally diverse canonical units',()=>{
 assert(CASES.length>=30,CASES.length);
 assert.equal(new Set(CASES.map(c=>c.reg)).size,20);
 assert(new Set(CASES.map(c=>c.code)).size>=26);
});
for(const c of CASES){
 test('SF8 national: '+c.com+' / '+c.prov+' / '+c.reg,()=>{
  const v=resolveTerritory(google(c),registry(c));
  assert.deepEqual(v.location,{region:c.reg,province:c.prov,municipality:c.com,locality:''});
  assert.equal(v.termIds.municipality,c.mid);
  assert.equal(v.country,'Italy');
 });
}
test('SF8 Piazza del Plebiscito / Napoli: Google can report province NA and no street route',()=>{
 const c=CASES.find(c=>c.com==='Napoli');
 assert(c);
 const v=resolveTerritory(google(c,'Città metropolitana di Napoli','NA'),registry(c));
 assert.equal(v.location?.municipality,'Napoli');
});
test('SF8 Rocca di Mezzo / AQ sigla: permanent regression',()=>{
 const c=CASES.find(c=>c.com==='Rocca di Mezzo');
 assert(c);
 const v=resolveTerritory(google(c,'Provincia dell’Aquila','AQ'),registry(c));
 assert.equal(v.location?.municipality,'Rocca di Mezzo');
});
test('SF8 municipality cannot resolve with incompatible REGION',()=>{
 const c=CASES.find(c=>c.com==='Rocca di Mezzo');
 const g=google(c);g[1]=a('administrative_area_level_1','Lazio');
 assert.equal(resolveTerritory(g,registry(c)).error,'unresolved_region');
});
test('SF8 municipality cannot resolve with incompatible PROVINCE',()=>{
 const c=CASES.find(c=>c.com==='Rocca di Mezzo'),r=registry(c);
 r.push({id:999999,parent:c.rid,name:'Teramo',code:'067'});
 const g=google(c,'Provincia di Teramo','TE');
 assert.equal(resolveTerritory(g,r).error,'unresolved_municipality');
});
test('SF8 missing administrative_area_level_2 refuses municipality-first inference',()=>{
 const c=CASES.find(c=>c.com==='Rocca di Mezzo');
 const g=google(c).filter(p=>!p.types.includes('administrative_area_level_2'));
 assert.equal(resolveTerritory(g,registry(c)).error,'unresolved_province');
});
test('SF8 municipality absent from canonical registry fails closed',()=>{
 const c=CASES.find(c=>c.com==='Rocca di Mezzo');
 const g=google(c);g[3]=a('locality','Comune inesistente');
 assert.equal(resolveTerritory(g,registry(c)).error,'unresolved_municipality');
});
test('SF8 homonym under SAME canonical parent is ambiguous; no first match',()=>{
 const c=CASES.find(c=>c.com==='Rocca di Mezzo'),r=registry(c);
 r.push({id:999998,parent:c.pid,name:c.com});
 assert.equal(resolveTerritory(google(c),r).error,'unresolved_municipality');
});
test('SF8 locality is not promoted to municipality',()=>{
 const c=CASES.find(c=>c.com==='Pomezia'),r=registry(c);
 r.push({id:5,parent:c.mid,name:'Torvaianica'});
 const g=google(c);g[3]=a('locality','Torvaianica');
 assert.equal(resolveTerritory(g,r).error,'unresolved_municipality');
});
test('SF8 locality recognized only under its canonical municipality',()=>{
 const c=CASES.find(c=>c.com==='Pomezia'),r=registry(c);
 r.push({id:5,parent:c.mid,name:'Torvaianica'});
 const g=google(c);
 g.push(a('sublocality','Torvaianica'));
 assert.equal(resolveTerritory(g,r).location?.locality,'Torvaianica');
});
test('SF8 inconsistent Google locality vs level_3 municipalities fails closed',()=>{
 const c=CASES.find(c=>c.com==='Rocca di Mezzo'),r=registry(c);
 r.push({id:98765,parent:c.pid,name:'Rocca di Cambio'});
 const g=google(c);
 g.push(a('administrative_area_level_3','Rocca di Cambio'));
 assert.equal(resolveTerritory(g,r).error,'unresolved_municipality');
});
test('SF8 no global name matching with same name under DIFFERENT region',()=>{
 const c=CASES.find(c=>c.com==='Roma'),r=registry(c);
 r.push({id:444444,parent:0,name:'Regione falsa'});
 r.push({id:444445,parent:444444,name:'Roma',code:'999'});
 r.push({id:444446,parent:444445,name:'Roma'});
 assert.equal(resolveTerritory(google(c),r).termIds.municipality,c.mid);
});
test('SF8 foreign country returns outside_italy',()=>{
 const c=CASES.find(c=>c.com==='Milano'),g=google(c);
 g[0]=a('country','Francia','FR');
 assert.equal(resolveTerritory(g,registry(c)).error,'outside_italy');
});
test('SF8 unknown abbreviation cannot override missing province name',()=>{
 const c=CASES.find(c=>c.com==='Milano'),g=google(c,'Unknown','ZZ');
 assert.equal(resolveTerritory(g,registry(c)).error,'unresolved_province');
});
test('SF8 exact canonical ISTAT names remain authoritative after normalization',()=>{
 const c=CASES.find(c=>c.com==='Bolzano'),g=google(c,'Provincia autonoma di Bolzano','BZ');
 assert.equal(resolveTerritory(g,registry(c)).location?.municipality,'Bolzano');
});

test('SF8 full province label Provincia dell’Aquila without sigla maps L\'Aquila',()=>{
 const c=CASES.find(c=>c.com==='Rocca di Mezzo'),g=google(c,'Provincia dell’Aquila','');
 assert.equal(resolveTerritory(g,registry(c)).location?.municipality,'Rocca di Mezzo');
});
test('SF8 Città metropolitana di Roma Capitale is a verified institutional province alias',()=>{
 const c=CASES.find(c=>c.com==='Pomezia'),g=google(c,'Città metropolitana di Roma Capitale','');
 assert.equal(resolveTerritory(g,registry(c)).location?.municipality,'Pomezia');
});
test('SF8 bilingual canonical region Valle d\'Aosta accepts Italian Google label',()=>{
 const c=CASES.find(c=>c.com==='Aosta'),g=google(c);
 g[1]=a('administrative_area_level_1',"Valle d'Aosta",'');
 g[2]=a('administrative_area_level_2','Aosta','');
 assert.equal(resolveTerritory(g,registry(c)).location?.municipality,'Aosta');
});
test('SF8 bilingual autonomous province Bolzano/Bozen accepts Bozen',()=>{
 const c=CASES.find(c=>c.com==='Bolzano'),g=google(c,'Provincia autonoma di Bozen','');
 g[1]=a('administrative_area_level_1','Trentino Alto Adige','');
 assert.equal(resolveTerritory(g,registry(c)).location?.municipality,'Bolzano');
});
test('SF8 municipality from administrative_area_level_3 and locality as child fraction',()=>{
 const c=CASES.find(c=>c.com==='Pomezia'),r=registry(c);
 r.push({id:5,parent:c.mid,name:'Torvaianica'});
 const g=google(c);g[3]=a('locality','Torvaianica');
 g.push(a('administrative_area_level_3','Pomezia'));
 assert.deepEqual(resolveTerritory(g,r).location,{region:c.reg,province:c.prov,municipality:'Pomezia',locality:'Torvaianica'});
});
test('SF8 false province long+short combination rejects conflicting canonical names',()=>{
 const c=CASES.find(c=>c.com==='Milano'),r=registry(c);
 r.push({id:99972,parent:c.rid,name:'Monza e della Brianza',code:'108'});
 const g=google(c,'Monza e della Brianza','MI');
 assert.equal(resolveTerritory(g,r).error,'unresolved_province');
});
