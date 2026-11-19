<?php
/** Homepage description only. Robots, canonical and event metadata keep their own owners. */
if (!defined('ABSPATH')) { exit; }
function badaround_home_meta_description() {
    if (!is_front_page() || defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION')) { return; }
    echo '<meta name="description" content="'.esc_attr('Segnalazioni di furti, pericoli, animali smarriti e problemi di quartiere. Esplora la mappa di BadAround e contribuisci con informazioni utili nella tua zona.').'">'."\n";
}
add_action('wp_head','badaround_home_meta_description',2);
