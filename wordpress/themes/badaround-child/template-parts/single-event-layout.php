<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

the_post();

$post_id = get_the_ID();
require_once dirname(__DIR__).'/inc/event-heading.php';
$title   = get_the_title();
$public_details=class_exists('BadAround_Event_Public_Details')?BadAround_Event_Public_Details::view($post_id):array();
$event_seo = class_exists('BadAround_Event_SEO') && BadAround_Event_SEO::active($post_id) ? BadAround_Event_SEO::project($post_id) : null;
if (is_wp_error($event_seo)) { $event_seo=null; }
if ($event_seo) { $title=$event_seo['h1']; }
$section_headings=BadAround_Event_SEO::section_headings($post_id);

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

$event_when = BadAround_Event_Dates::occurrence_label($post_id, $public_details);
$report_receipt = BadAround_Event_Dates::receipt_label(BadAround_Event_Dates::view($post_id));
$seeking_witnesses=!empty($public_details['seeking_witnesses'])&&!in_array($event_status,array('resolved','closed','archived','expired'),true);
$public_address=$public_details['address']??'';
$public_neighborhood=$public_details['neighborhood']??'';
if($public_address)$public_location=$public_address;
if($public_neighborhood&&stripos($public_location,$public_neighborhood)===false)$public_location.=($public_location!==''?', zona ':'').$public_neighborhood;

$content_text = trim( wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ) );
$has_content  = '' !== $content_text;
$gallery_ids = function_exists('badaround_event_gallery_ids') ? badaround_event_gallery_ids($post_id) : array();
$public_lat   = get_post_meta( $post_id, '_ba_public_lat', true );
$public_lng   = get_post_meta( $post_id, '_ba_public_lng', true );
$public_mode = '';
$public_radius = get_post_meta( $post_id, '_ba_public_radius_m', true );
// A sealed projection does not require a historical moderator decision.
// Retained proof also fences missing/corrupt projections from legacy mirrors.
$has_versioned_geo = '' !== get_post_meta( $post_id, '_ba_public_geo', true )
	|| '' !== get_post_meta( $post_id, '_ba_public_geo_proof', true )
	|| '' !== get_post_meta( $post_id, '_ba_public_location_decision', true );
// Classification only: the existing predicate returns no report content/coordinates.
// This also fences a versioned record whose public metadata was entirely lost.
if ( ! $has_versioned_geo && class_exists( 'BadAround_Publication_Service' ) ) {
	$has_versioned_geo = BadAround_Publication_Service::uses_versioned_geo( $post_id );
}
if ( $has_versioned_geo ) {
	$approved_geo = class_exists( 'BadAround_Public_Geo_Projection' )
		? BadAround_Public_Geo_Projection::public_view( $post_id ) : null;
	$public_mode = $approved_geo['mode'] ?? '';
	$public_lat = $approved_geo['lat'] ?? '';
	$public_lng = $approved_geo['lng'] ?? '';
	$public_radius = $approved_geo['radius_meters'] ?? '';
}

$location_label = 'exact_point' === $public_mode ? 'Posizione esatta' : 'Posizione approssimativa';
if ( ! is_numeric( $public_lat ) || ! is_numeric( $public_lng ) ) {
	$location_label = 'Zona territoriale';
}

$public_specific = array();
$vehicle_make    = sanitize_text_field( (string) get_post_meta( $post_id, '_ba_vehicle_make', true ) );
$vehicle_model   = sanitize_text_field( (string) get_post_meta( $post_id, '_ba_vehicle_model', true ) );
$vehicle_color   = sanitize_text_field( (string) get_post_meta( $post_id, '_ba_vehicle_color', true ) );
$plate_masked    = sanitize_text_field( (string) ( $public_details['plate'] ?? get_post_meta( $post_id, '_ba_vehicle_plate_masked', true ) ) );

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

foreach(($public_details['facts']??array()) as $path=>$fact){
    $value=$fact['value'];
    if(function_exists('badaround_native_report_option_label'))$value=badaround_native_report_option_label($path,$value);
    $public_specific[$fact['label']]=$value;
}

$summary_facts = array();
if($public_neighborhood)$summary_facts['Quartiere / zona']=$public_neighborhood;
if ( $category ) {
	$summary_facts['Categoria'] = $category;
}
if ( $subcategory ) {
	$summary_facts['Tipologia'] = $subcategory;
}
if ( $event_when ) {
	$summary_facts['Data/periodo dell’evento'] = $event_when;
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
                    <?php foreach($territory_path as $level_index=>$public_territory): ?>
                        <span class="ba-event-territory-chip"><span class="ba-event-territory-chip__level"><?php echo esc_html(array('Regione','Provincia','Comune','Zona')[$level_index]??'Zona'); ?>:</span> <?php echo esc_html($public_territory->name); ?></span>
                    <?php endforeach; ?>
				</div>

				<h1><?php echo esc_html( badaround_event_heading($title) ); ?></h1>
                <?php if($seeking_witnesses): ?><p class="ba-event-witness-badge"><a href="#contribuisci"><?php echo badaround_event_witness_icon(); ?><span><strong>Si cercano testimoni</strong> · Hai visto qualcosa o hai informazioni utili?</span></a></p><?php endif; ?>
                <?php if ($event_seo) : ?><p class="ba-event-intro"><?php echo esc_html($event_seo['intro']); ?></p><?php endif; ?>

				<?php if ( $public_location || $report_receipt ) : ?>
					<div class="ba-event-meta">
						<?php if ( $public_location ) : ?>
							<span>
								<?php echo badaround_pin_svg('neutral'); ?>
								<?php echo esc_html( $public_location ); ?>
							</span>
						<?php endif; ?>
						<?php if ( $report_receipt ) : ?>
							<span>
								<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M12 7v5l3 2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
								<?php echo esc_html($report_receipt['prefix']); ?> <time datetime="<?php echo esc_attr($report_receipt['datetime']); ?>"><?php echo esc_html($report_receipt['text']); ?></time>
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
                <div class="ba-event-overview<?php echo !$gallery_ids?' ba-event-overview--empty':''; ?>">
                    <?php if(function_exists('badaround_render_event_gallery'))badaround_render_event_gallery($gallery_ids,$title,$section_headings['photos'],badaround_child_placeholder_url($category_term?$category_term->slug:'')); ?>
				<?php if ( ! empty( $public_specific ) ) : ?>
					<section class="ba-card ba-event-section">
						<div class="ba-event-section__head">
							<h2><?php echo esc_html($section_headings['facts']); ?></h2>
							<span class="ba-event-section__eyebrow">Dati pubblicabili</span>
						</div>
						<dl class="ba-event-facts">
							<?php foreach ( $public_specific as $label => $value ) : ?>
								<div><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( $value ); ?></dd></div>
							<?php endforeach; ?>
						</dl>
					</section>
				<?php endif; ?>
                </div>

				<?php if ( $has_content ) : ?>
					<section class="ba-card ba-event-section">
						<div class="ba-event-section__head">
							<h2><?php echo esc_html($section_headings['prose']); ?></h2>
							<span class="ba-event-section__eyebrow">Informazione pubblica moderata</span>
						</div>
						<div class="ba-event-prose"><?php the_content(); ?></div>
					</section>
				<?php endif; ?>

				<?php if ( ! empty( $territory_path ) || $public_location ) : ?>
					<section class="ba-card ba-event-section ba-event-territory">
						<div class="ba-event-section__head">
							<div>
								<h2><?php echo esc_html($section_headings['location']); ?></h2>
								<p><?php echo $public_address?'Indirizzo e posizione destinati alla pubblicazione.':'BadAround mostra solo la posizione destinata alla pubblicazione. L’indirizzo preciso, quando riservato, non viene esposto.'; ?></p>
							</div>
						</div>

						<?php if ($public_address) : ?><div class="ba-event-public-address ba-event-subsection"><h3 class="ba-event-subheading"><?php echo esc_html($section_headings['address']); ?>:</h3> <?php echo esc_html($public_address); ?></div><?php endif; ?>
                        <?php if ($event_when) : ?><div class="ba-event-subsection"><h3 class="ba-event-subheading"><?php echo esc_html($section_headings['occurrence']); ?>:</h3> <?php echo esc_html($event_when); ?></div><?php endif; ?>
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
<h3 class="ba-event-subheading ba-event-map-heading"><?php echo esc_html($section_headings['map']); ?></h3><div class="ba-event-map-tools"><button class="ba-button ba-button--outline" type="button" data-ba-detail-map-load>Carica la mappa</button><span data-ba-detail-map-status role="status" aria-live="polite">La mappa si carica quando è vicina all’area visibile.</span></div>
							<div class="ba-event-location-map" id="ba-event-google-map" data-ba-map data-ba-map-context="detail" data-ba-event-lat="<?php echo esc_attr( $public_lat ); ?>" data-ba-event-lng="<?php echo esc_attr( $public_lng ); ?>" data-ba-event-radius="<?php echo esc_attr( $public_radius ); ?>" data-ba-event-id="<?php echo esc_attr( $post_id ); ?>" data-ba-event-mode="<?php echo esc_attr( $public_mode ); ?>" data-ba-event-category="<?php echo esc_attr( $category_term ? $category_term->slug : '' ); ?>" data-ba-event-status="<?php echo esc_attr( $event_status ); ?>" aria-label="<?php echo esc_attr( 'Mappa: ' . $location_label ); ?>"></div>
						<?php endif; ?>

						<?php if ( $public_location ) : ?>
							<div class="ba-public-location">
								<span aria-hidden="true">◎</span>
								<div>
									<small><?php echo esc_html( $location_label ); ?></small>
									<strong><?php echo esc_html( $public_location ); ?></strong>
								</div>
							</div>
						<?php endif; ?>
					</section>
				<?php endif; ?>


                <?php if ( class_exists( 'BadAround_Contribution_Service' ) ) { BadAround_Contribution_Service::render_event_contribution_block( $post_id ); } ?>
                <?php if (class_exists('BadAround_Identity_WordPress_Comment_Form', false) && BadAround_Identity_WordPress_Comment_Form::enabled()) : ?>
                    <?php echo BadAround_Identity_WordPress_Comment_Form::render(); // Escaped by the Identity presenter. ?>
                <?php else : ?>
                    <section class="ba-card ba-event-section ba-event-community-unavailable" id="commenti"><h2><?php echo esc_html($section_headings['comments']); ?></h2><p>I commenti sono disponibili nell’area di collaudo riservata, dalla connessione autorizzata.</p></section>
                <?php endif; ?>

			</div>

			<aside class="ba-event-aside" aria-label="Riepilogo evento">
				<?php if ( ! empty( $summary_facts ) ) : ?>
					<section class="ba-card ba-event-summary">
						<h2><?php echo esc_html($section_headings['summary']); ?></h2>
						<dl>
							<?php foreach ( $summary_facts as $label => $value ) : ?>
								<div><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( $value ); ?></dd></div>
							<?php endforeach; ?>
						</dl>
					</section>
				<?php endif; ?>

                <?php if(function_exists('badaround_event_social_render'))badaround_event_social_render($post_id); ?>
				<section class="ba-card ba-event-safety">
					<h2><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h4l2 5-3 2a15 15 0 0 0 5 5l2-3 5 2v4c0 2-2 3-4 2C10 18 6 14 4 7 3 5 4 3 6 3Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>Serve aiuto immediato?</h2>
					<p>BadAround non sostituisce i servizi di emergenza. In caso di pericolo immediato chiama il <a href="tel:112">112</a> o contatta l'autorità competente.</p>
				</section>
                <section class="ba-card ba-event-section ba-event-responsibility" aria-labelledby="ba-event-responsibility-title">
                    <h2 id="ba-event-responsibility-title">Responsabilità della segnalazione</h2>
                    <p>Chi invia una segnalazione si assume la responsabilità dei contenuti trasmessi e dichiara di averli forniti in buona fede. Le segnalazioni sono soggette a moderazione; la pubblicazione non costituisce una verifica indipendente dei fatti da parte di BadAround. Quando i contenuti provengono da fonti esterne, la relativa provenienza è indicata nella scheda.</p>
                    <p>Per segnalare inesattezze, contenuti inappropriati o richiedere una rettifica, <a href="<?php echo esc_url( 'mailto:info@badaround.it?subject=' . rawurlencode( 'Rettifica segnalazione #' . $post_id ) . '&body=' . rawurlencode( 'Segnalazione: ' . get_permalink( $post_id ) . "\n\nDescrivi il problema o la rettifica richiesta:\n" ) ); ?>">segnala un problema o richiedi una rettifica via email</a>.</p>
                </section>
			</aside>
		</div>
	</section>
</main>
