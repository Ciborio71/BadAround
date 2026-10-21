'use strict';
const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { execFileSync } = require('node:child_process');
const file = 'wordpress/themes/badaround-child/front-page.php';
const baseline = '2a5fc54811b08acc644e7cde2c327a2afe3d6f99';
const source = fs.readFileSync(file, 'utf8');
const before = execFileSync('git', ['show', `${baseline}:${file}`], { encoding: 'utf8' });
const oldCopy = 'La posizione pubblica degli eventi è sempre approssimata.';
const copy = 'La posizione pubblica degli eventi può essere approssimata in base alla richiesta dell’utente e alla moderazione.';
test('Home explains the request and moderation without universal approximation', () => {
  assert.doesNotMatch(source, /sempre approssimat/i);
  assert.ok(source.includes(copy));
  assert.ok(source.includes('La tua posizione non viene salvata.'));
});
test('Only the authorized Home sentence changes; rendering and data paths are identical', () => {
  assert.equal(source, before.replace(oldCopy, copy));
  const files = execFileSync('git', ['diff', '--name-only', baseline, '--', 'wordpress'], { encoding: 'utf8' }).trim().split('\n');
  assert.deepEqual(files, [file]);
});
