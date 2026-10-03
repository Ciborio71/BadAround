<?php
/**
 * Template Name: BadAround — Cerca
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$query      = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
$category   = isset( $_GET['categoria'] ) ? sanitize_title( wp_unslash( $_GET['categoria'] ) ) : '';
$event_type = isset( $_GET['tipo'] ) ? sanitize_title( wp_unslash( $_GET['tipo'] ) ) : '';
$period     = isset( $_GET['periodo'] ) ? absint( $_GET['periodo'] ) : 0;
$territory  = isset( $_GET['territorio'] ) ? sanitize_title( wp_unslash( $_GET['territorio'] ) ) : '';
$page       = isset( $_GET['pagina'] ) ? max( 1, absint( $_GET['pagina'] ) ) : 1;

if ( ! in_array( $period, array( 7, 30, 90 ), true ) ) {
	$period = 0;
}

$has_request = '' !== trim( $query ) || '' !== $category || '' !== $event_type || 0 !== $period || '' !== $territory;
$result      = null;

if ( $has_request && class_exists( 'BadAround_Discovery_Query' ) ) {
	$discovery = new BadAround_Discovery_Query();
	$result = $discovery->discover(
		array(
			'search'     => $query,
			'territory'  => $territory,
			'category'   => $category,
			'event_type' => $event_type,
			'period'     => $period,
			'page'       => $page,
			'per_page'   => 12,
		)
	);
}

$total = is_array( $result ) ? (int) $result['total'] : 0;

$categories = get_terms(
	array(
		'taxonomy'   => BadAround_Event_Post_Type::EVENT_TYPE_TAX,
		'hide_empty' => false,
		'parent'     => 0,
		'orderby'    => 'name',
		'order'      => 'ASC',
	)
);
if ( is_wp_error( $categories ) ) {
	$categories = array();
}

$types = get_terms(
	array(
		'taxonomy'   => BadAround_Event_Post_Type::EVENT_TYPE_TAX,
		'hide_empty' => false,
		'orderby'    => 'name',
		'order'      => 'ASC',
	)
);
if ( is_wp_error( $types ) ) {
	$types = array();
}

$types_by_parent = array();
foreach ( $types as $type_term ) {
	if ( empty( $type_term->parent ) ) {
		continue;
	}
	$types_by_parent[ (int) $type_term->parent ][] = $type_term;
}

$current_args = array_filter(
	array(
		'q'          => $query,
		'categoria'  => $category,
		'tipo'       => $event_type,
		'periodo'    => $period ?: '',
		'territorio' => $territory,
	),
	static function ( $value ) {
		return '' !== $value && 0 !== $value;
	}
);

$remove_url = static function ( $key ) use ( $current_args ) {
	$args = $current_args;
	unset( $args[ $key ] );
	return add_query_arg( $args, home_url( '/cerca/' ) );
};

$category_label = '';
if ( $category ) {
	$term = get_term_by( 'slug', $category, BadAround_Event_Post_Type::EVENT_TYPE_TAX );
	if ( $term instanceof WP_Term ) {
		$category_label = $term->name;
	}
}

$type_label = '';
if ( $event_type ) {
	$term = get_term_by( 'slug', $event_type, BadAround_Event_Post_Type::EVENT_TYPE_TAX );
	if ( $term instanceof WP_Term ) {
		$type_label = $term->name;
	}
}

$territory_label = '';
if ( $territory ) {
	$term = get_term_by( 'slug', $territory, BadAround_Event_Post_Type::TERRITORY_TAX );
	if ( $term instanceof WP_Term ) {
		$territory_label = $term->name;
	}
}

$period_label = $period ? sprintf( 'Ultimi %d giorni', $period ) : '';
?>
<main class="ba-search-page" id="main-content">
	<header class="ba-search-hero">
		<div class="ba-container">
			<p class="ba-eyebrow">Ricerca BadAround</p>
			<h1>Cerca eventi pubblicati</h1>
			<p class="ba-lead">Cerca un territorio o cosa è successo, poi restringi i risultati con pochi filtri essenziali.</p>

			<form class="ba-search-form" role="search" action="<?php echo esc_url( home_url( '/cerca/' ) ); ?>" method="get">
				<label for="ba-search-query">Territorio o cosa è successo</label>
				<div class="ba-search-form__row">
					<input id="ba-search-query" name="q" type="search" value="<?php echo esc_attr( $query ); ?>" placeholder="Es. Torvaianica, Pomezia, veicolo rubato" autocomplete="off">
					<button class="ba-button" type="submit">Cerca</button>
				</div>

				<details class="ba-filter-panel" open>
					<summary>Filtri</summary>
					<div class="ba-filter-panel__body">
						<div class="ba-filter-grid">
							<div class="ba-filter-field">
								<label for="ba-filter-category">Categoria</label>
								<select id="ba-filter-category" name="categoria">
									<option value="">Tutte le categorie</option>
									<?php foreach ( $categories as $term ) : ?>
										<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $category, $term->slug ); ?>><?php echo esc_html( $term->name ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>

							<div class="ba-filter-field">
								<label for="ba-filter-type">Tipologia</label>
								<select id="ba-filter-type" name="tipo">
									<option value="">Tutte le tipologie</option>
									<?php foreach ( $categories as $parent_term ) : ?>
										<?php if ( empty( $types_by_parent[ (int) $parent_term->term_id ] ) ) { continue; } ?>
										<optgroup label="<?php echo esc_attr( $parent_term->name ); ?>">
											<?php foreach ( $types_by_parent[ (int) $parent_term->term_id ] as $type_term ) : ?>
												<option value="<?php echo esc_attr( $type_term->slug ); ?>" <?php selected( $event_type, $type_term->slug ); ?>><?php echo esc_html( $type_term->name ); ?></option>
											<?php endforeach; ?>
										</optgroup>
									<?php endforeach; ?>
								</select>
							</div>

							<div class="ba-filter-field">
								<label for="ba-filter-period">Periodo</label>
								<select id="ba-filter-period" name="periodo">
									<option value="">Qualsiasi periodo</option>
									<option value="7" <?php selected( $period, 7 ); ?>>Ultimi 7 giorni</option>
									<option value="30" <?php selected( $period, 30 ); ?>>Ultimi 30 giorni</option>
									<option value="90" <?php selected( $period, 90 ); ?>>Ultimi 90 giorni</option>
								</select>
							</div>
						</div>

						<?php if ( $territory ) : ?>
							<input type="hidden" name="territorio" value="<?php echo esc_attr( $territory ); ?>">
						<?php endif; ?>

						<div class="ba-filter-actions">
							<button class="ba-button ba-button--outline" type="submit">Applica filtri</button>
							<?php if ( $category || $event_type || $period || $territory ) : ?>
								<a class="ba-filter-reset" href="<?php echo esc_url( add_query_arg( array_filter( array( 'q' => $query ) ), home_url( '/cerca/' ) ) ); ?>">Azzera filtri</a>
							<?php endif; ?>
						</div>
					</div>
				</details>
			</form>
		</div>
	</header>

	<section class="ba-search-results" aria-labelledby="ba-search-results-title">
		<div class="ba-container">
			<?php if ( ! $has_request ) : ?>
				<div class="ba-search-empty ba-card">
					<h2 id="ba-search-results-title">Inizia una ricerca</h2>
					<p>Inserisci un territorio o parole che descrivono l'evento, oppure applica un filtro.</p>
				</div>
			<?php elseif ( ! is_array( $result ) ) : ?>
				<div class="ba-search-empty ba-card">
					<h2 id="ba-search-results-title">Ricerca temporaneamente non disponibile</h2>
					<p>Il servizio Discovery non è disponibile.</p>
				</div>
			<?php else : ?>
				<div class="ba-search-results__head">
					<div>
						<span class="ba-eyebrow">Risultati</span>
						<h2 id="ba-search-results-title"><?php echo esc_html( $total . ( 1 === $total ? ' evento trovato' : ' eventi trovati' ) ); ?></h2>
						<p><?php echo $query ? 'Ricerca per “' . esc_html( $query ) . '”' : 'Eventi pubblicati che corrispondono ai filtri selezionati.'; ?></p>
					</div>
				</div>

				<?php if ( $query || $category_label || $type_label || $period_label || $territory_label ) : ?>
					<div class="ba-active-filters" aria-label="Ricerca e filtri attivi">
						<?php if ( $query ) : ?><span class="ba-filter-chip">Ricerca: <?php echo esc_html( $query ); ?></span><?php endif; ?>
						<?php if ( $category_label ) : ?><a class="ba-filter-chip" href="<?php echo esc_url( $remove_url( 'categoria' ) ); ?>">Categoria: <?php echo esc_html( $category_label ); ?> ×</a><?php endif; ?>
						<?php if ( $type_label ) : ?><a class="ba-filter-chip" href="<?php echo esc_url( $remove_url( 'tipo' ) ); ?>">Tipologia: <?php echo esc_html( $type_label ); ?> ×</a><?php endif; ?>
						<?php if ( $period_label ) : ?><a class="ba-filter-chip" href="<?php echo esc_url( $remove_url( 'periodo' ) ); ?>"><?php echo esc_html( $period_label ); ?> ×</a><?php endif; ?>
						<?php if ( $territory_label ) : ?><a class="ba-filter-chip" href="<?php echo esc_url( $remove_url( 'territorio' ) ); ?>">Territorio: <?php echo esc_html( $territory_label ); ?> ×</a><?php endif; ?>
						<?php if ( $category || $event_type || $period || $territory ) : ?><a class="ba-filter-clear" href="<?php echo esc_url( add_query_arg( array_filter( array( 'q' => $query ) ), home_url( '/cerca/' ) ) ); ?>">Azzera filtri</a><?php endif; ?>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $result['territory_matches'] ) ) : ?>
					<div class="ba-search-territories" aria-label="Territori riconosciuti">
						<strong><?php echo count( $result['territory_matches'] ) > 1 ? 'Territori riconosciuti' : 'Territorio riconosciuto'; ?></strong>
						<ul>
							<?php foreach ( $result['territory_matches'] as $matched ) : ?>
								<li>
									<a href="<?php echo esc_url( add_query_arg( array_merge( $current_args, array( 'territorio' => $matched['slug'] ) ), home_url( '/cerca/' ) ) ); ?>">
										<?php echo esc_html( implode( ' › ', $matched['path'] ) ); ?>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $result['items'] ) ) : ?>
					<div class="ba-search-grid">
						<?php foreach ( $result['items'] as $item ) : ?>
							<?php
							$meta = array_filter(
								array(
									$item['occurred_date'] ?? '',
									$item['occurred_time'] ?? '',
									$item['public_place_name'] ?? ( $item['territory']['name'] ?? '' ),
								)
							);
							get_template_part(
								'template-parts/components/event-card',
								null,
								array(
									'title'        => $item['title'],
									'url'          => $item['permalink'],
									'status'       => 'info',
									'status_label' => $item['event_type']['name'] ?? 'Segnalazione',
									'meta'         => implode( ' · ', $meta ),
									'image'        => $item['thumbnail'] ?? '',
									'image_alt'    => $item['title'],
									'excerpt'      => $item['excerpt'] ?? '',
								)
							);
							?>
						<?php endforeach; ?>
					</div>

					<?php if ( (int) $result['pages'] > 1 ) : ?>
						<nav class="ba-search-pagination" aria-label="Pagine dei risultati">
							<?php if ( $page > 1 ) : ?><a class="ba-button ba-button--outline" href="<?php echo esc_url( add_query_arg( array_merge( $current_args, array( 'pagina' => $page - 1 ) ), home_url( '/cerca/' ) ) ); ?>">← Precedente</a><?php endif; ?>
							<span>Pagina <?php echo esc_html( (string) $page ); ?> di <?php echo esc_html( (string) $result['pages'] ); ?></span>
							<?php if ( $page < (int) $result['pages'] ) : ?><a class="ba-button ba-button--outline" href="<?php echo esc_url( add_query_arg( array_merge( $current_args, array( 'pagina' => $page + 1 ) ), home_url( '/cerca/' ) ) ); ?>">Successiva →</a><?php endif; ?>
						</nav>
					<?php endif; ?>
				<?php else : ?>
					<div class="ba-search-empty ba-card">
						<h3>Nessun evento trovato</h3>
						<p>Non risultano eventi pubblicati con questa combinazione. Rimuovi un filtro o prova una ricerca diversa.</p>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	</section>
</main>
<?php get_footer(); ?>
