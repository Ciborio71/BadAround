'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),cp=require('node:child_process'),crypto=require('node:crypto');
const base='48bab2229c00fb28f01d54117dd1278074f10d69';
const expected=['wordpress/plugins/badaround-core/includes/class-badaround-discovery-query.php','wordpress/themes/badaround-child/template-parts/single-event-layout.php'];
test('only two proven public consumers change; governance, publication, Native, DB and taxonomy remain identical',()=>{
 const changed=cp.execFileSync('git',['diff','--name-only',base,'HEAD','--','wordpress'],{encoding:'utf8'}).trim().split('\n').filter(Boolean);assert.deepEqual(changed,expected);
 const c=fs.readFileSync('wordpress/plugins/badaround-core/contracts/nf-conditional-v1.0.json');assert.equal(crypto.createHash('sha256').update(c).digest('hex'),'dc282153243ffb80f3bd4f2a222ea0f4c4f42806fba6f3a0bde0a76855bd8e3c');
});
test('consumer audit: all moderator-decision references are fenced by projection/proof and integrity reader',()=>{
 for(const path of expected){const s=fs.readFileSync(path,'utf8');assert.match(s,/PROOF_META|_ba_public_geo_proof/);assert.match(s,/BadAround_Public_Geo_Projection::public_view/);assert.match(s,/BadAround_Publication_Service::uses_versioned_geo/);}
 const paths=cp.execFileSync('rg',['-l','_ba_public_location_decision|DECISION_META','wordpress'],{encoding:'utf8'}).trim().split('\n');
 const reviewed=[...expected,'wordpress/plugins/badaround-core/includes/class-badaround-public-geo-projection.php','wordpress/plugins/badaround-core/includes/class-badaround-public-geo-contract.php','wordpress/plugins/badaround-core/includes/class-badaround-publication-service.php','wordpress/plugins/badaround-core/includes/class-badaround-geo-moderation-service.php','wordpress/plugins/badaround-core/includes/class-badaround-moderation-admin.php'];
 for(const path of paths)assert(reviewed.includes(path),'unreviewed decision dependency '+path);
});
