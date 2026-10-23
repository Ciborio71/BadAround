<?php
/** Isolated hook execution of the actual child-theme bootstrap; no WordPress writes. */
define( 'ABSPATH', __DIR__ );
$GLOBALS['qa_filters'] = array();
function add_action() {}
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
    $GLOBALS['qa_filters'][$hook][$priority][] = $callback;
}
function __return_false() { return false; }
require dirname(__DIR__) . '/wordpress/themes/badaround-child/functions.php';
foreach (array('use_block_editor_for_post_type', 'use_block_editor_for_post') as $hook) {
    $entries = $GLOBALS['qa_filters'][$hook] ?? array();
    if (array_keys($entries) !== array(100) || $entries[100] !== array('__return_false')) {
        throw new RuntimeException('Duplicate/missing/scoped Classic Editor filter: ' . $hook);
    }
    foreach (array('ba_evento', 'post', 'page') as $type) {
        $value = true;
        foreach ($entries[100] as $callback) {
            $value = call_user_func($callback, $value, 'use_block_editor_for_post' === $hook ? (object)array('post_type'=>$type) : $type);
        }
        if (false !== $value) { throw new RuntimeException('Block editor still enabled: ' . $type); }
        echo "PASS: $hook $type disabled at priority 100\n";
    }
}
$path = 'wordpress/themes/badaround-child/functions.php';
$base = shell_exec('git show dc5ca5be7314e841f2b70e6d43fb6cda5bc12f7a:' . $path);
$current = file_get_contents(dirname(__DIR__) . '/' . $path);
$block = "/**\n * BadAround admin: prefer the classic WordPress editor on every post type.\n * This is UI-only; moderation/publication permissions and hooks stay intact.\n */\nadd_filter( 'use_block_editor_for_post_type', '__return_false', 100 );\nadd_filter( 'use_block_editor_for_post', '__return_false', 100 );\n\n";
if (substr_count($current, $block) !== 1 || str_replace($block, '', $current) !== $base) {
    throw new RuntimeException('Unexpected functions.php change beyond approved Classic Editor block');
}
foreach (array('assets/js/map-navigation.js','assets/css/map-navigation.css','assets/js/map.js','assets/js/native-report/location-map.js') as $file) {
    $path = 'wordpress/themes/badaround-child/' . $file;
    $before = shell_exec('git show dc5ca5be7314e841f2b70e6d43fb6cda5bc12f7a:' . $path);
    if ($before !== file_get_contents(dirname(__DIR__) . '/' . $path)) { throw new RuntimeException('Changed map runtime: ' . $file); }
}
echo "PASS: exact additive reconciliation; all map runtime bytes preserved\n";
