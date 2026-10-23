<?php
/** Isolated enqueue matrix; no WordPress installation or database writes. */
define('ABSPATH', __DIR__);
define('BADAROUND_CORE_PATH', __DIR__ . '/../wordpress/plugins/badaround-core/');
define('BADAROUND_CORE_FILE', BADAROUND_CORE_PATH . 'badaround-core.php');
class BadAround_Event_Post_Type { const POST_TYPE = 'ba_evento'; }
function get_current_screen() { return $GLOBALS['screen']; }
function plugins_url($path, $file) { return 'https://example.invalid/plugin/' . $path; }
function wp_enqueue_style(...$args) { $GLOBALS['enqueued'][] = $args; }
require_once BADAROUND_CORE_PATH . 'includes/class-badaround-moderation-admin.php';
$admin = new BadAround_Moderation_Admin();
foreach (['ba_evento', 'post', 'page'] as $type) {
    foreach (['post.php', 'post-new.php', 'edit.php', 'index.php'] as $hook) {
        foreach ([false, true] as $block) {
            $GLOBALS['screen'] = new class($type, $block) {
                public $base = 'post'; public $post_type; private $block;
                function __construct($type, $block) { $this->post_type=$type; $this->block=$block; }
                function is_block_editor() { return $this->block; }
            };
            $GLOBALS['enqueued'] = [];
            $admin->enqueue_classic_editor_styles($hook);
            $expected = $type === 'ba_evento' && in_array($hook, ['post.php','post-new.php'], true) && !$block;
            if ((bool)$GLOBALS['enqueued'] !== $expected) { throw new RuntimeException("Scope failed: $type $hook $block"); }
            if ($expected && $GLOBALS['enqueued'][0][2] !== ['edit','buttons']) { throw new RuntimeException('Admin dependencies missing'); }
        }
    }
}
$GLOBALS['screen'] = null; $GLOBALS['enqueued'] = [];
$admin->enqueue_classic_editor_styles('post.php');
if ($GLOBALS['enqueued']) { throw new RuntimeException('Missing screen must not enqueue'); }
$base = 'eacfdc2e77cce877c8b1b6961c28abf2b8f61d1d';
$path = 'wordpress/plugins/badaround-core/includes/class-badaround-moderation-admin.php';
$before = shell_exec('git show ' . $base . ':' . $path);
$after = file_get_contents(dirname(__DIR__) . '/' . $path);
$after = str_replace("\t\tadd_action( 'admin_enqueue_scripts', array( \$this, 'enqueue_classic_editor_styles' ) );\n", '', $after);
$after = preg_replace('/\t\/\*\* Presentation only:.*?\n\tpublic function add_moderation_queue_view/s', "\tpublic function add_moderation_queue_view", $after);
if ($after !== $before) { throw new RuntimeException('Existing methods changed'); }
$functions = file_get_contents(dirname(__DIR__) . '/wordpress/themes/badaround-child/functions.php');
if (hash('sha256', $functions) !== '1f8111ee63ef31f2227421e51c8077f6015eeaad03c1e69bca415651c82d6e7d') { throw new RuntimeException('Classic Editor byte custody failed'); }
foreach (["add_filter('use_block_editor_for_post_type', '__return_false', 100);", "add_filter('use_block_editor_for_post', '__return_false', 100);"] as $filter) {
    if (!str_contains(preg_replace('/\s+/', '', $functions), preg_replace('/\s+/', '', $filter))) { throw new RuntimeException('Classic Editor filter missing'); }
}
echo "PASS enqueue matrix 49 cases; existing methods byte-identical; Classic Editor filters and functions.php hash preserved.\n";
