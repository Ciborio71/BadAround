<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$term = get_queried_object();
if ( ! $term || empty( $term->term_id ) ) { return; }

$taxonomy   = $term->taxonomy;
$report_url = home_url( '/segnala-un-evento/' );

$ancestors = array_reverse( get_ancestors( $term->term_id, $taxonomy, 'taxonomy' ) );
$breadcrumbs = array();
foreach ( $ancestors as $ancestor_id ) {
	$ancestor = get_term( $ancestor_id, $taxonomy );
	if ( $ancestor && ! is_wp_error( $ancestor ) ) {
		$breadcrumbs[] = $ancestor;
	}
}

$paged = max( 1, (int) get_query_var( 'paged' ) );
$events = new WP_Query(
	array(
		'post_type'           => 'ba_evento',
		'post_status'         => 'publish',
		'posts_per_page'      => 12,
		'paged'               => $paged,
		'ignore_sticky_posts' => true,
		'orderby'             => 'date',
		'order'               => 'DESC',
		'meta_query'          => array(
			array(
				'key'     => '_ba_moderation_status',
				'value'   => 'published',
				'compare' => '=',
			),
		),
		'tax_query'           => array(
			array(
				'taxonomy'         => $taxonomy,
				'field'            => 'term_id',
				'terms'            => array( (int) $term->term_id ),
				'include_children' => true,
			),
		),
	)
);

$total_events = (int) $events->found_posts;

$deepest_term = static function ( $terms, $tax ) {
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return null;
	}
	usort(
		$terms,
		static function ( $a, $b ) use ( $tax ) {
			return count( get_ancestors( $b->term_id, $tax, 'taxonomy' ) ) <=> count( get_ancestors( $a->term_id, $tax, 'taxonomy' ) );
		}
	);
	return $terms[0];
};
?>
<main class="ba-page ba-territory-page" id="main-content">
	<header class="ba-territory-hero">
		<div class="ba-container">
			<nav class="ba-breadcrumb" aria-label="Breadcrumb">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a>
				<span aria-hidden="true">›</span>
				<?php foreach ( $breadcrumbs as $ancestor ) : ?>
					<a href="<?php echo esc_url( get_term_link( $ancestor ) ); ?>"><?php echo esc_html( $ancestor->name ); ?></a>
					<span aria-hidden="true">›</span>
				<?php endforeach; ?>
				<span aria-current="page"><?php echo esc_html( $term->name ); ?></span>
			</nav>

			<div class="ba-territory-hero__grid">
				<div>
					<div class="ba-eyebrow">Territorio</div>
					<h1><?php echo esc_html( $term->name ); ?></h1>
					<p class="ba-lead">
						<?php
						if ( ! empty( $term->description ) ) {
							echo esc_html( $term->description );
						} else {
							printf(
								esc_html__( 'Eventi pubblicati e moderati relativi a %s.', 'badaround-child' ),
								esc_html( $term->name )
							);
						}
						?>
					</p>
					<div class="ba-territory-hero__actions">
						<a class="ba-button ba-button--urgent" href="<?php echo esc_url( $report_url ); ?>">Segnala un evento</a>
					</div>
				</div>

				<div class="ba-territory-radar" aria-hidden="true">
					<div class="ba-territory-radar__ring ba-territory-radar__ring--1"></div>
					<div class="ba-territory-radar__ring ba-territory-radar__ring--2"></div>
					<div class="ba-territory-radar__ring ba-territory-radar__ring--3"></div>
					<div class="ba-territory-radar__pulse"></div>
				</div>
			</div>
		</div>
	</header>

	<section class="ba-territory-events" aria-labelledby="ba-territory-events-title">
		<div class="ba-container">
			<div class="ba-territory-events__head">
				<div>
					<span class="ba-eyebrow">Segnalazioni pubbliche</span>
					<h2 id="ba-territory-events-title">Eventi a <?php echo esc_html( $term->name ); ?></h2>
				</div>
				<p>
					<?php
					printf(
						esc_html( _n( '%s evento pubblicato', '%s eventi pubblicati', $total_events, 'badaround-child' ) ),
						esc_html( number_format_i18n( $total_events ) )
					);
					?>
				</p>
			</div>

			<?php if ( $events->have_posts() ) : ?>
				<div class="ba-territory-eventgrid">
					<?php while ( $events->have_posts() ) : $events->the_post(); ?>
						<?php
						$event_id = get_the_ID();
						$type_term = $deepest_term( get_the_terms( $event_id, 'ba_tipo_evento' ), 'ba_tipo_evento' );
						$type_label = $type_term ? $type_term->name : 'Segnalazione';

						$occurred_date = sanitize_text_field( (string) get_post_meta( $event_id, '_ba_occurred_date', true ) );
						$occurred_time = sanitize_text_field( (string) get_post_meta( $event_id, '_ba_occurred_time', true ) );
						$when = trim( $occurred_date . ( $occurred_time ? ' · ' . $occurred_time : '' ) );
						if ( ! $when ) {
							$when = get_the_date();
						}

						$public_place = sanitize_text_field( (string) get_post_meta( $event_id, '_ba_public_place_name', true ) );
						$meta_parts = array_filter( array( $when, $public_place ?: $term->name ) );

						$excerpt = trim( wp_strip_all_tags( get_the_excerpt() ) );
						if ( ! $excerpt ) {
							$excerpt = trim( wp_strip_all_tags( get_post_field( 'post_content', $event_id ) ) );
						}
						$excerpt = wp_trim_words( $excerpt, 22, '…' );

						get_template_part(
							'template-parts/components/event-card',
							null,
							array(
								'title'        => get_the_title(),
								'url'          => get_permalink(),
								'status'       => 'info',
								'status_label' => $type_label,
								'meta'         => implode( ' · ', $meta_parts ),
								'image'        => get_the_post_thumbnail_url( $event_id, 'medium_large' ),
								'image_alt'    => get_the_title(),
								'excerpt'      => $excerpt,
							)
						);
						?>
					<?php endwhile; ?>
				</div>

				<?php if ( $events->max_num_pages > 1 ) : ?>
					<nav class="ba-territory-pagination" aria-label="Paginazione eventi">
						<?php
						echo wp_kses_post(
							paginate_links(
								array(
									'total'     => (int) $events->max_num_pages,
									'current'   => $paged,
									'mid_size'  => 1,
									'prev_text' => '←',
									'next_text' => '→',
								)
							)
						);
						?>
					</nav>
				<?php endif; ?>
			<?php else : ?>
				<div class="ba-card ba-territory-empty">
					<span class="ba-territory-empty__icon" aria-hidden="true">◎</span>
					<h2>Nessun evento pubblicato in questa zona</h2>
					<p>Quando una segnalazione relativa a <?php echo esc_html( $term->name ); ?> verrà moderata e pubblicata, comparirà qui.</p>
					<a class="ba-button ba-button--urgent" href="<?php echo esc_url( $report_url ); ?>">Segnala un evento</a>
				</div>
			<?php endif; ?>

			<?php wp_reset_postdata(); ?>
		</div>
	</section>
</main>
