<?php
get_header();
$term = get_queried_object();
?>
<main class="ba-page" id="main-content">
	<header class="ba-territory-head">
		<div class="ba-container">
			<div class="ba-eyebrow"><?php esc_html_e( 'Tipologia di evento', 'badaround-child' ); ?></div>
			<h1><?php echo esc_html( $term->name ?? __( 'Segnalazioni', 'badaround-child' ) ); ?></h1>
			<?php if ( ! empty( $term->description ) ) : ?><p class="ba-lead"><?php echo esc_html( $term->description ); ?></p><?php endif; ?>
		</div>
	</header>
	<section class="ba-section">
		<div class="ba-container ba-grid ba-grid--3">
			<?php if ( have_posts() ) : ?>
				<?php while ( have_posts() ) : the_post(); ?>
					<?php
					$event = badaround_get_public_event_data();
					get_template_part(
						'template-parts/components/event-card',
						null,
						array(
							'title'        => get_the_title(),
							'url'          => get_permalink(),
							'status'       => $event['event_status'] ?: 'info',
							'status_label' => badaround_public_event_status_label( $event['event_status'] ),
							'meta'         => implode( ' · ', array_filter( array( $event['occurred_date'], $event['place_name'] ) ) ),
							'image'        => get_the_post_thumbnail_url( get_the_ID(), 'medium' ),
						)
					);
					?>
				<?php endwhile; ?>
			<?php else : ?>
				<div class="ba-card ba-panel"><h2><?php esc_html_e( 'Nessun evento pubblicato', 'badaround-child' ); ?></h2></div>
			<?php endif; ?>
		</div>
	</section>
</main>
<?php get_footer(); ?>
