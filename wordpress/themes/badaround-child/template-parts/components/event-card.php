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
);
$card = wp_parse_args( $args ?? array(), $defaults );
?>
<article class="ba-card ba-event-card<?php echo $card['image'] ? '' : ' ba-event-card--no-media'; ?>">
	<?php if ( $card['image'] ) : ?>
		<a class="ba-event-card__media" href="<?php echo esc_url( $card['url'] ); ?>" tabindex="-1" aria-hidden="true">
			<img src="<?php echo esc_url( $card['image'] ); ?>" alt="<?php echo esc_attr( $card['image_alt'] ); ?>" loading="lazy" decoding="async">
		</a>
	<?php endif; ?>

	<div class="ba-event-card__body">
		<?php if ( $card['status_label'] ) : ?>
			<span class="ba-badge ba-badge--<?php echo esc_attr( $card['status'] ); ?>"><?php echo esc_html( $card['status_label'] ); ?></span>
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
