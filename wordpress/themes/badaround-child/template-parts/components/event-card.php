<?php
/**
 * Reusable public event card.
 * Accepts $args: title, url, status, status_label, meta, image, image_alt, excerpt.
 */
$defaults = array(
	'title'        => __( 'Segnalazione nella zona', 'badaround-child' ),
	'url'          => '#',
	'status'       => 'info',
	'status_label' => __( 'Segnalazione', 'badaround-child' ),
	'meta'         => '',
	'image'        => '',
	'image_alt'    => '',
	'excerpt'      => '',
	'link_label'   => __( 'Vedi dettaglio', 'badaround-child' ),
	'class'        => '',
	'category'     => '',
	'category_icon'=> '',
);
$card = wp_parse_args( $args ?? array(), $defaults );
if ( ! $card['image'] && function_exists( 'badaround_child_placeholder_url' ) ) {
	$card['image'] = badaround_child_placeholder_url();
	$card['image_alt'] = __( 'BadAround — immagine segnalazione non disponibile', 'badaround-child' );
}
$category_class = $card['category'] ? ' ba-event-card--category-' . sanitize_html_class( $card['category'] ) : '';
$category_icons = array(
	'veicoli' => '<svg viewBox="0 0 24 24" focusable="false"><path d="M5 14h14l-1.5-5h-11L5 14Zm1 0v3m12-3v3M8 17h8M8 9l1-3h6l1 3"/></svg>',
	'case-e-attivita' => '<svg viewBox="0 0 24 24" focusable="false"><path d="M4 11 12 4l8 7v9h-6v-6h-4v6H4v-9Z"/></svg>',
	'pericoli' => '<svg viewBox="0 0 24 24" focusable="false"><path d="M12 4 3.5 20h17L12 4Z"/><path d="M12 9v5m0 3h.01"/></svg>',
	'spazi-pubblici' => '<svg viewBox="0 0 24 24" focusable="false"><path d="M4 18h16M6 18V9h12v9M8 9V6h8v3M9 13h2m2 0h2"/></svg>',
	'animali' => '<svg viewBox="0 0 24 24" focusable="false"><circle cx="8" cy="9" r="1.5"/><circle cx="12" cy="7" r="1.6"/><circle cx="16" cy="9" r="1.5"/><path d="M8 17c0-2.3 1.8-4.2 4-4.2s4 1.9 4 4.2c0 1.6-1.2 2.3-2.5 2-.9-.2-2.1-.2-3 0C9.2 19.3 8 18.6 8 17Z"/></svg>',
	'oggetti-e-documenti' => '<svg viewBox="0 0 24 24" focusable="false"><path d="M5 7h14v12H5V7Zm3-3h8v3H8V4Zm1 7h6m-6 4h4"/></svg>',
);
$category_icon = $category_icons[ $card['category'] ] ?? '';
?>
<article class="ba-card ba-event-card<?php echo $card['image'] ? '' : ' ba-event-card--no-media'; ?><?php echo $card['class'] ? ' ' . esc_attr( $card['class'] ) : ''; ?><?php echo esc_attr( $category_class ); ?>">
	<?php if ( $card['image'] ) : ?>
		<a class="ba-event-card__media" href="<?php echo esc_url( $card['url'] ); ?>" tabindex="-1" aria-hidden="true">
			<img src="<?php echo esc_url( $card['image'] ); ?>" alt="<?php echo esc_attr( $card['image_alt'] ); ?>" loading="lazy" decoding="async">
		</a>
	<?php endif; ?>

	<div class="ba-event-card__body">
		<?php if ( $card['status_label'] ) : ?>
			<span class="ba-badge ba-badge--<?php echo esc_attr( $card['status'] ); ?>">
				<?php if ( $category_icon ) : ?><span class="ba-event-card__category-icon" aria-hidden="true"><?php echo $category_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed internal SVG map. ?></span><?php endif; ?>
				<?php echo esc_html( $card['status_label'] ); ?>
			</span>
		<?php endif; ?>

		<h3 class="ba-event-card__title">
			<a href="<?php echo esc_url( $card['url'] ); ?>"><?php echo esc_html( $card['title'] ); ?></a>
		</h3>

		<?php if ( $card['meta'] ) : ?>
			<div class="ba-event-card__meta"><?php echo esc_html( $card['meta'] ); ?></div>
		<?php endif; ?>

		<?php if ( $card['excerpt'] ) : ?>
			<p class="ba-event-card__excerpt"><?php echo esc_html( $card['excerpt'] ); ?></p>
		<?php endif; ?>

		<a class="ba-event-card__link" href="<?php echo esc_url( $card['url'] ); ?>"><?php echo esc_html( $card['link_label'] ); ?> <span aria-hidden="true">→</span></a>
	</div>
</article>
