'use strict';
const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const root = path.resolve(__dirname, '..');
const read = (p) => fs.readFileSync(path.join(root, p), 'utf8');
const exists = (p) => fs.existsSync(path.join(root, p));
const theme = 'wordpress/themes/badaround-child/';
const core = 'wordpress/plugins/badaround-core/';
const baseline = '9c45f22eaa2a15d96cd225f077651bfdff344f6a';

test('report page renders only Native; no WPForms or fallback routing', () => {
  const page = read(theme + 'page-segnala-evento.php');
  const native = read(theme + 'inc/native-report.php');
  assert.match(page, /get_template_part\(\s*'template-parts\/native-report\/shell'\s*\)/);
  assert.doesNotMatch(page, /wpforms|\$use_native|\$form_id/i);
  assert.doesNotMatch(native, /BADAROUND_NATIVE_REPORT_DEFAULT|badaround_report_entry_mode|badaround_native_report_use_native/);
});
test('only Native report assets are enqueued', () => {
  const source = read(theme + 'functions.php');
  assert.match(source, /'badaround-native-report'/);
  assert.match(source, /'smart-address'/);
  assert.doesNotMatch(source, /badaround-wpforms|badaround-report-ui|badaround_report_form_id|wpforms_geolocation_/);
  assert.equal(exists(theme + 'assets/css/wpforms.css'), false);
  assert.equal(exists(theme + 'assets/js/report.js'), false);
});
test('Legacy intake hook is not installed and retired adapters are absent', () => {
  const bootstrap = read(core + 'badaround-core.php');
  const runner = read(core + 'includes/class-badaround-core.php');
  assert.doesNotMatch(bootstrap, /class-badaround-wpforms-(event-intake|field-mapper|report-adapter|contract-map)/);
  assert.doesNotMatch(runner, /WPForms_Event_Intake|wpforms_process_complete/);
  for (const file of ['event-intake','field-mapper','report-adapter','contract-map']) {
    assert.equal(exists(core + 'includes/class-badaround-wpforms-' + file + '.php'), false);
  }
});
test('preserves Google settings dependency until independently migrated', () => {
  assert.match(read(theme + 'functions.php'), /wpforms_settings/);
  assert.match(read(theme + 'inc/native-report.php'), /wpforms_settings/);
});
test('Legacy reusable card aesthetics live in native-scoped CSS, without legacy selectors', () => {
  const css = read(theme + 'assets/css/native-report.css');
  const shared = read(theme + 'assets/css/report.css');
  for (const required of ['.ba-native-choice','.ba-native-progress','.ba-native-help','.ba-native-actions','.ba-native-review-section','prefers-reduced-motion','location.exact_address']) {
    assert.ok(css.includes(required), 'Missing Native design pattern: '+required);
  }
  assert.doesNotMatch(css, /wpforms-\d+|\.wpforms-|#wpforms-/);
  assert.doesNotMatch(shared, /wpforms-\d+|\.wpforms-|#wpforms-/);
});
test('Smart Address script and frozen contracts remain identical to certified candidate', () => {
  for (const relative of [
    theme + 'assets/js/native-report/smart-address.js',
    theme + 'assets/js/native-report/wizard.js',
    core + 'includes/class-badaround-report-schema.php',
    core + 'includes/class-badaround-publication-service.php',
    core + 'includes/class-badaround-discovery-query.php',
    core + 'includes/class-badaround-native-media-service.php'
  ]) {
    const original = execFileSync('git',['show',baseline+':'+relative],{cwd:root,encoding:'utf8'});
    assert.equal(read(relative), original, 'Unexpected frozen change: '+relative);
  }
});
