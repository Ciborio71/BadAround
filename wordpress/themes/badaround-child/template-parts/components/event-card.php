<?php
/**
 * Reusable event card. Accepts $args: title, url, status, status_label, meta, image.
 */
$defaults = array(
	'title'        => __( 'Segnalazione nella zona', 'badaround-child' ),
	'url'          => '#',
	'status'       => 'info',
	'status_label' => __( 'Segnalazione', 'badaround-child' ),
	'meta'         => __( 'Posizione approssimativa', 'badaround-child' ),
	'image'        => '',
);
$card = wp_parse_args( $args ?? array(), $defaults );
?>
<article class="ba-card ba-event-card">
	<div class="ba-event-card__media">
		<?php if ( $card['image'] ) : ?>
			<img src="<?php echo esc_url( $card['image'] ); ?>" alt="" loading="lazy" decoding="async">
		<?php endif; ?>
	</div>
	<div class="ba-event-card__body">
		<span class="ba-badge ba-badge--<?php echo esc_attr( $card['status'] ); ?>"><?php echo esc_html( $card['status_label'] ); ?></span>
		<h3 class="ba-event-card__title"><a href="<?php echo esc_url( $card['url'] ); ?>"><?php echo esc_html( $card['title'] ); ?></a></h3>
		<div class="ba-event-card__meta"><?php echo esc_html( $card['meta'] ); ?></div>
	</div>
</article>

