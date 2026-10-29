'use strict';
const fs=require('node:fs');
const assert=require('node:assert/strict');
const html=fs.readFileSync('tests/fixtures/radar-02b-static-preview.html','utf8');
const checks=[
 ['Marked internal prototype',html.includes('data-radar-preview-only="true"')],
 ['Explicit synthetic data',html.includes('DATI DIMOSTRATIVI')],
 ['No public integration',html.includes('Non collegata al sito')],
 ['No public alerts',html.includes('nessun indicatore pubblico o allarme automatico')],
 ['Report-not-incidents disclaimer',html.includes('non episodi distinti né livelli di rischio')],
 ['Statistics disclaimer',html.includes('non dimostrano significatività statistica')],
 ['Quality suppression copy',html.includes('Dati insufficienti per un confronto affidabile')],
 ['7-day period',html.includes('data-window="7"')],
 ['30-day period',html.includes('data-window="30"')],
 ['Accessible controls',html.includes('aria-pressed="true"')],
 ['Responsive viewport',html.includes('name="viewport"')],
 ['No network calls',!/(?:fetch\s*\(|XMLHttpRequest|https?:\/\/|wp-json|localStorage)/.test(html)],
];
for(const [name,result] of checks){assert.ok(result,name);console.log('PASS',name)}
console.log('RADAR-02B: PASS',checks.length,'static checks');
