<?php
/**
 * Template Name: BadAround — Collaudo Identity
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$ba_uri = get_stylesheet_directory_uri();
$ba_dir = get_stylesheet_directory();
foreach ( array(
    'base' => array( 'astra-theme-css' ),
    'components' => array( 'badaround-base' ),
    'shell' => array( 'badaround-components' ),
    'identity-qa' => array( 'badaround-shell' ),
) as $ba_asset => $ba_dependencies ) {
    wp_enqueue_style( 'badaround-' . $ba_asset,
        $ba_uri . '/assets/css/' . $ba_asset . '.css', $ba_dependencies,
        (string) filemtime( $ba_dir . '/assets/css/' . $ba_asset . '.css' )
    );
}
wp_enqueue_script( 'badaround-navigation', $ba_uri . '/assets/js/navigation.js',
    array(), (string) filemtime( $ba_dir . '/assets/js/navigation.js' ), true );
wp_script_add_data( 'badaround-navigation', 'strategy', 'defer' );

get_header();
?>
<main id="main-content" class="ba-identity-qa">
    <div class="ba-container">
        <article class="ba-card ba-identity-qa__content">
            <?php
            while ( have_posts() ) {
                the_post();
                if ( shortcode_exists( 'badaround_identity_portal' ) ) {
                    the_content();
                } else {
                    ?>
                    <h1>Collaudo Identity</h1>
                    <p role="status">Il collaudo non è disponibile per questa connessione. Utilizza l’accesso riservato e la connessione autorizzata.</p>
                    <?php
                }
            }
            ?>
        </article>
    </div>
</main>
<?php get_footer(); ?>
