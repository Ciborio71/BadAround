'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict'),cp=require('node:child_process'),fs=require('node:fs'),crypto=require('node:crypto');
test('only moderation display changes from certified radius candidate; frozen conditional contract unchanged',()=>{
 assert.deepEqual(cp.execFileSync('git',['diff','--name-only','7398815f3a946f194a75c28fcaf48683d1441832','HEAD','--','wordpress'],{encoding:'utf8'}).trim().split('\n'),['wordpress/plugins/badaround-core/includes/class-badaround-moderation-admin.php']);
 assert.equal(crypto.createHash('sha256').update(fs.readFileSync('wordpress/plugins/badaround-core/contracts/nf-conditional-v1.0.json')).digest('hex'),'dc282153243ffb80f3bd4f2a222ea0f4c4f42806fba6f3a0bde0a76855bd8e3c');
});
