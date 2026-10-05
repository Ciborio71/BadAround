<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

the_post();

$post_id = get_the_ID();
$title   = get_the_title();

$deepest_term = static function ( $terms, $taxonomy ) {
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return null;
	}
	usort(
		$terms,
		static function ( $a, $b ) use ( $taxonomy ) {
			$a_depth = count( get_ancestors( $a->term_id, $taxonomy, 'taxonomy' ) );
			$b_depth = count( get_ancestors( $b->term_id, $taxonomy, 'taxonomy' ) );
			return $b_depth <=> $a_depth;
		}
	);
	return $terms[0];
};

$term_path = static function ( $term, $taxonomy ) {
	if ( ! $term || is_wp_error( $term ) ) {
		return array();
	}
	$ids = array_reverse( get_ancestors( $term->term_id, $taxonomy, 'taxonomy' ) );
	$ids[] = $term->term_id;
	$path = array();
	foreach ( $ids as $term_id ) {
		$item = get_term( $term_id, $taxonomy );
		if ( $item && ! is_wp_error( $item ) ) {
			$path[] = $item;
		}
	}
	return $path;
};

$type_term      = $deepest_term( get_the_terms( $post_id, 'ba_tipo_evento' ), 'ba_tipo_evento' );
$type_path      = $term_path( $type_term, 'ba_tipo_evento' );
$category_term  = ! empty( $type_path ) ? $type_path[0] : null;
$category       = $category_term ? $category_term->name : '';
$subcategory    = ( $type_term && ( ! $category_term || $type_term->term_id !== $category_term->term_id ) ) ? $type_term->name : '';

$territory_term = $deepest_term( get_the_terms( $post_id, 'ba_territorio' ), 'ba_territorio' );
$territory_path = $term_path( $territory_term, 'ba_territorio' );
$territory      = $territory_term ? $territory_term->name : '';

$public_location = sanitize_text_field( (string) get_post_meta( $post_id, '_ba_public_place_name', true ) );
if ( ! $public_location ) {
	$public_location = $territory;
}

$event_status = sanitize_key( (string) get_post_meta( $post_id, '_ba_event_status', true ) );
$status_labels = array(
	'open'     => 'Evento attivo',
	'updated'  => 'Aggiornato',
	'resolved' => 'Risolto',
	'closed'   => 'Chiuso',
	'expired'  => 'Scaduto',
	'archived' => 'Archiviato',
);
$status = isset( $status_labels[ $event_status ] ) ? $status_labels[ $event_status ] : '';

$event_date = sanitize_text_field( (string) get_post_meta( $post_id, '_ba_occurred_date', true ) );
$event_time = sanitize_text_field( (string) get_post_meta( $post_id, '_ba_occurred_time', true ) );
$event_when = trim( $event_date . ( $event_time ? ' · ' . $event_time : '' ) );

$content_text = trim( wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ) );
$has_content  = '' !== $content_text;
$has_thumb    = has_post_thumbnail( $post_id );
$detail_image = function_exists( 'badaround_child_event_image_url' ) ? badaround_child_event_image_url( $post_id, 'detail' ) : ( $has_thumb ? get_the_post_thumbnail_url( $post_id, 'large' ) : '' );
$public_lat   = get_post_meta( $post_id, '_ba_public_lat', true );
$public_lng   = get_post_meta( $post_id, '_ba_public_lng', true );
$public_radius = get_post_meta( $post_id, '_ba_public_radius_m', true );

$public_specific = array();
$vehicle_make    = sanitize_text_field( (string) get_post_meta( $post_id, '_ba_vehicle_make', true ) );
$vehicle_model   = sanitize_text_field( (string) get_post_meta( $post_id, '_ba_vehicle_model', true ) );
$vehicle_color   = sanitize_text_field( (string) get_post_meta( $post_id, '_ba_vehicle_color', true ) );
$plate_masked    = sanitize_text_field( (string) get_post_meta( $post_id, '_ba_vehicle_plate_masked', true ) );

if ( $vehicle_make ) {
	$public_specific['Marca'] = ucfirst( $vehicle_make );
}
if ( $vehicle_model ) {
	$public_specific['Modello'] = ucfirst( $vehicle_model );
}
if ( $vehicle_color ) {
	$public_specific['Colore'] = $vehicle_color;
}
if ( $plate_masked ) {
	$public_specific['Targa'] = $plate_masked;
}

$summary_facts = array();
if ( $category ) {
	$summary_facts['Categoria'] = $category;
}
if ( $subcategory ) {
	$summary_facts['Tipologia'] = $subcategory;
}
if ( $event_when ) {
	$summary_facts['Quando'] = $event_when;
}
if ( $public_location ) {
	$summary_facts['Zona pubblica'] = $public_location;
}
if ( $status ) {
	$summary_facts['Stato'] = $status;
}
?>
<main class="ba-page ba-event-detail" id="main-content">
	<header class="ba-event-hero">
		<div class="ba-container">
			<nav class="ba-breadcrumb" aria-label="Breadcrumb">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a>
				<span aria-hidden="true">›</span>
				<a href="<?php echo esc_url( home_url( '/segnalazioni/' ) ); ?>">Segnalazioni</a>
				<span aria-hidden="true">›</span>
				<span aria-current="page"><?php echo esc_html( wp_trim_words( $title, 6, '…' ) ); ?></span>
			</nav>

			<div class="ba-event-hero__copy">
				<div class="ba-event-kickers">
					<?php if ( $status ) : ?>
						<span class="ba-event-status"><?php echo esc_html( $status ); ?></span>
					<?php endif; ?>
					<?php if ( $category ) : ?>
						<span class="ba-event-category"><?php echo esc_html( $category ); ?></span>
					<?php endif; ?>
					<?php if ( $subcategory ) : ?>
						<span class="ba-event-subcategory"><?php echo esc_html( $subcategory ); ?></span>
					<?php endif; ?>
				</div>

				<h1><?php echo esc_html( $title ); ?></h1>

				<?php if ( $public_location || $event_when ) : ?>
					<div class="ba-event-meta">
						<?php if ( $public_location ) : ?>
							<span>
								<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s6-5.2 6-11a6 6 0 1 0-12 0c0 5.8 6 11 6 11Z" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="10" r="2.2" fill="currentColor"/></svg>
								<?php echo esc_html( $public_location ); ?>
							</span>
						<?php endif; ?>
						<?php if ( $event_when ) : ?>
							<span>
								<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M12 7v5l3 2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
								<?php echo esc_html( $event_when ); ?>
							</span>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</header>

	<section class="ba-event-body">
		<div class="ba-container ba-event-layout">
			<div class="ba-event-main">
				<?php if ( $detail_image ) : ?>
					<figure class="ba-event-media">
						<img src="<?php echo esc_url( $detail_image ); ?>" alt="<?php echo esc_attr( $has_thumb ? $title : 'BadAround — immagine segnalazione non disponibile' ); ?>" loading="eager" decoding="async">
					</figure>
				<?php endif; ?>

				<?php if ( $has_content ) : ?>
					<section class="ba-card ba-event-section">
						<div class="ba-event-section__head">
							<h2>Che cosa è successo</h2>
							<span class="ba-event-section__eyebrow">Informazione pubblica moderata</span>
						</div>
						<div class="ba-event-prose"><?php the_content(); ?></div>
					</section>
				<?php endif; ?>

				<?php if ( ! empty( $territory_path ) || $public_location ) : ?>
					<section class="ba-card ba-event-section ba-event-territory">
						<div class="ba-event-section__head">
							<div>
								<h2>Dove è successo</h2>
								<p>BadAround mostra solo la posizione destinata alla pubblicazione. L'indirizzo preciso, quando riservato, non viene esposto.</p>
							</div>
						</div>

						<?php if ( ! empty( $territory_path ) ) : ?>
							<ol class="ba-territory-path" aria-label="Gerarchia territoriale">
								<?php foreach ( $territory_path as $index => $territory_item ) : ?>
									<li>
										<span><?php echo esc_html( $territory_item->name ); ?></span>
										<?php if ( $index < count( $territory_path ) - 1 ) : ?><b aria-hidden="true">›</b><?php endif; ?>
									</li>
								<?php endforeach; ?>
							</ol>
						<?php endif; ?>

						<?php if ( is_numeric( $public_lat ) && is_numeric( $public_lng ) ) : ?>
							<div class="ba-event-location-map" id="ba-event-google-map" data-ba-map data-ba-map-context="detail" data-ba-event-lat="<?php echo esc_attr( $public_lat ); ?>" data-ba-event-lng="<?php echo esc_attr( $public_lng ); ?>" data-ba-event-radius="<?php echo esc_attr( $public_radius ); ?>" data-ba-event-id="<?php echo esc_attr( $post_id ); ?>" aria-label="Mappa della posizione pubblica approssimata"></div>
						<?php endif; ?>

						<?php if ( $public_location ) : ?>
							<div class="ba-public-location">
								<span aria-hidden="true">◎</span>
								<div>
									<small>Posizione pubblica approssimata</small>
									<strong><?php echo esc_html( $public_location ); ?></strong>
								</div>
							</div>
						<?php endif; ?>
					</section>
				<?php endif; ?>

				<?php if ( ! empty( $public_specific ) ) : ?>
					<section class="ba-card ba-event-section">
						<div class="ba-event-section__head">
							<h2>Dettagli utili</h2>
							<span class="ba-event-section__eyebrow">Dati pubblicabili</span>
						</div>
						<dl class="ba-event-facts">
							<?php foreach ( $public_specific as $label => $value ) : ?>
								<div><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( $value ); ?></dd></div>
							<?php endforeach; ?>
						</dl>
					</section>
				<?php endif; ?>
				<?php if ( class_exists( 'BadAround_Contribution_Service' ) ) { BadAround_Contribution_Service::render_event_contribution_block( $post_id ); } ?>
			</div>

			<aside class="ba-event-aside" aria-label="Riepilogo evento">
				<?php if ( ! empty( $summary_facts ) ) : ?>
					<section class="ba-card ba-event-summary">
						<h2>Informazioni evento</h2>
						<dl>
							<?php foreach ( $summary_facts as $label => $value ) : ?>
								<div><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( $value ); ?></dd></div>
							<?php endforeach; ?>
						</dl>
					</section>
				<?php endif; ?>

				<section class="ba-card ba-event-safety">
					<h2>Serve aiuto immediato?</h2>
					<p>BadAround non sostituisce i servizi di emergenza. In caso di pericolo immediato contatta il 112 o l'autorità competente.</p>
				</section>
			</aside>
		</div>
	</section>
</main>
