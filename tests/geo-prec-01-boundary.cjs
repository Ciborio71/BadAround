'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),cp=require('node:child_process');
const baseline='a4ef16aa067afc94a96cacd55a46a62d1248ed93';
test('exactly seven GEO governance runtime files; Native/Home/contract/DB/taxonomy/consumers unchanged',()=>{
 const expected=['class-badaround-audit-log.php','class-badaround-geo-moderation-service.php','class-badaround-moderation-admin.php','class-badaround-public-geo-contract.php','class-badaround-public-geo-projection.php','class-badaround-publication-service.php','class-badaround-report-repository.php'].map(f=>'wordpress/plugins/badaround-core/includes/'+f);
 const changed=cp.execFileSync('git',['diff','--name-only',baseline,'HEAD','--','wordpress'],{encoding:'utf8'}).trim().split('\n').filter(Boolean);assert.deepEqual(changed,expected);
 const c=fs.readFileSync('wordpress/plugins/badaround-core/contracts/nf-conditional-v1.0.json');assert.equal(require('node:crypto').createHash('sha256').update(c).digest('hex'),'dc282153243ffb80f3bd4f2a222ea0f4c4f42806fba6f3a0bde0a76855bd8e3c');
 const contract=JSON.parse(c);assert.equal(contract.matrix.length,33);assert.equal(contract.matrix.reduce((n,r)=>n+Object.keys(r.fields).length,0),2805);assert.equal(contract.matrix.reduce((n,r)=>n+Object.values(r.fields).filter(f=>f.state==='CONDITIONAL').length,0),554);
});
