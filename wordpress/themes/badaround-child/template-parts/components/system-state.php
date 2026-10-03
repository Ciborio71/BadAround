<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$defaults = array(
	'variant'       => 'info',
	'eyebrow'       => '',
	'title'         => __( 'Stato del sistema', 'badaround-child' ),
	'message'       => '',
	'primary_label' => '',
	'primary_url'   => '',
	'secondary_label' => '',
	'secondary_url' => '',
	'meta'          => '',
	'icon'          => 'info',
);
$state = wp_parse_args( $args ?? array(), $defaults );

$icons = array(
	'success' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4 10-10" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
	'pending' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M12 7v5l3 2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
	'warning' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4 3 20h18L12 4Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M12 9v5M12 17h.01" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
	'error'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="m9 9 6 6m0-6-6 6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
	'empty'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M8.5 12h7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
	'info'    => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M12 11v5M12 8h.01" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
);
?>
<section class="ba-system-state ba-system-state--<?php echo esc_attr( $state['variant'] ); ?>">
	<div class="ba-system-state__icon" aria-hidden="true"><?php echo $icons[ $state['icon'] ] ?? $icons['info']; ?></div>
	<div class="ba-system-state__content">
		<?php if ( $state['eyebrow'] ) : ?><span class="ba-system-state__eyebrow"><?php echo esc_html( $state['eyebrow'] ); ?></span><?php endif; ?>
		<h2><?php echo esc_html( $state['title'] ); ?></h2>
		<?php if ( $state['message'] ) : ?><p><?php echo esc_html( $state['message'] ); ?></p><?php endif; ?>
		<?php if ( $state['meta'] ) : ?><small><?php echo esc_html( $state['meta'] ); ?></small><?php endif; ?>

		<?php if ( $state['primary_label'] || $state['secondary_label'] ) : ?>
			<div class="ba-system-state__actions">
				<?php if ( $state['primary_label'] ) : ?><a class="ba-button" href="<?php echo esc_url( $state['primary_url'] ?: '#' ); ?>"><?php echo esc_html( $state['primary_label'] ); ?></a><?php endif; ?>
				<?php if ( $state['secondary_label'] ) : ?><a class="ba-button ba-button--outline" href="<?php echo esc_url( $state['secondary_url'] ?: '#' ); ?>"><?php echo esc_html( $state['secondary_label'] ); ?></a><?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
