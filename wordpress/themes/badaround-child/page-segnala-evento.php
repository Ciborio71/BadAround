<?php
/**
 * Template Name: Segnala un evento
 * Template Post Type: page
 *
 * Presentation-only template for the BadAround event submission form.
 * Submission processing and Event creation belong in badaround-core.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>

<main id="primary" class="ba-report-page">
    <div class="ba-report-page__inner">
        <header class="ba-report-page__header">
            <p class="ba-report-page__eyebrow"><?php esc_html_e( 'BadAround', 'badaround-child' ); ?></p>
            <h1 class="ba-report-page__title"><?php esc_html_e( 'Segnala un evento', 'badaround-child' ); ?></h1>
            <p class="ba-report-page__intro">
                <?php esc_html_e( 'Compila il modulo per inviare la tua segnalazione. Prima della pubblicazione, i contenuti saranno sottoposti a moderazione.', 'badaround-child' ); ?>
            </p>
        </header>

        <section class="ba-report-page__form" aria-label="<?php esc_attr_e( 'Modulo di segnalazione evento', 'badaround-child' ); ?>">
            <?php
            if ( shortcode_exists( 'wpforms' ) ) {
                echo do_shortcode( '[wpforms id="6" title="false" description="false"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            } elseif ( current_user_can( 'manage_options' ) ) {
                echo '<p class="ba-report-page__notice">' . esc_html__( 'WPForms non è disponibile. Verifica che il plugin sia attivo.', 'badaround-child' ) . '</p>';
            }
            ?>
        </section>
    </div>
</main>

<?php
get_footer();
