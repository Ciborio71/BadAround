<?php
/**
 * Template Name: BadAround — Cerca
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$query = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
$page  = isset( $_GET['pagina'] ) ? max( 1, absint( $_GET['pagina'] ) ) : 1;
$result = null;

if ( '' !== trim( $query ) && class_exists( 'BadAround_Discovery_Query' ) ) {
	$discovery = new BadAround_Discovery_Query();
	$result = $discovery->discover(
		array(
			'search'   => $query,
			'page'     => $page,
			'per_page' => 12,
		)
	);
}

$total = is_array( $result ) ? (int) $result['total'] : 0;
?>
<main class="ba-search-page" id="main-content">
	<header class="ba-search-hero">
		<div class="ba-container">
			<p class="ba-eyebrow">Ricerca BadAround</p>
			<h1>Cerca eventi pubblicati</h1>
			<p class="ba-lead">Puoi cercare un territorio — per esempio Torvaianica o Pomezia — oppure parole presenti nelle informazioni pubbliche dell'evento.</p>

			<form class="ba-search-form" role="search" action="<?php echo esc_url( home_url( '/cerca/' ) ); ?>" method="get">
				<label for="ba-search-query">Territorio o cosa è successo</label>
				<div class="ba-search-form__row">
					<input
						id="ba-search-query"
						name="q"
						type="search"
						value="<?php echo esc_attr( $query ); ?>"
						placeholder="Es. Torvaianica, Pomezia, veicolo rubato"
						autocomplete="off"
					>
					<button class="ba-button" type="submit">Cerca</button>
				</div>
			</form>
		</div>
	</header>

	<section class="ba-search-results" aria-labelledby="ba-search-results-title">
		<div class="ba-container">
			<?php if ( '' === trim( $query ) ) : ?>
				<div class="ba-search-empty ba-card">
					<h2 id="ba-search-results-title">Inizia una ricerca</h2>
					<p>Inserisci una località, un Comune oppure parole che descrivono l'evento che stai cercando.</p>
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
						<p>Ricerca per “<?php echo esc_html( $query ); ?>”</p>
					</div>
				</div>

				<?php if ( ! empty( $result['territory_matches'] ) ) : ?>
					<div class="ba-search-territories" aria-label="Territori riconosciuti">
						<strong><?php echo count( $result['territory_matches'] ) > 1 ? 'Territori riconosciuti' : 'Territorio riconosciuto'; ?></strong>
						<ul>
							<?php foreach ( $result['territory_matches'] as $territory ) : ?>
								<li>
									<?php if ( ! empty( $territory['url'] ) ) : ?>
										<a href="<?php echo esc_url( $territory['url'] ); ?>">
									<?php endif; ?>
									<?php echo esc_html( implode( ' › ', $territory['path'] ) ); ?>
									<?php if ( ! empty( $territory['url'] ) ) : ?>
										</a>
									<?php endif; ?>
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
							<?php if ( $page > 1 ) : ?>
								<a class="ba-button ba-button--outline" href="<?php echo esc_url( add_query_arg( array( 'q' => $query, 'pagina' => $page - 1 ), home_url( '/cerca/' ) ) ); ?>">← Precedente</a>
							<?php endif; ?>
							<span>Pagina <?php echo esc_html( (string) $page ); ?> di <?php echo esc_html( (string) $result['pages'] ); ?></span>
							<?php if ( $page < (int) $result['pages'] ) : ?>
								<a class="ba-button ba-button--outline" href="<?php echo esc_url( add_query_arg( array( 'q' => $query, 'pagina' => $page + 1 ), home_url( '/cerca/' ) ) ); ?>">Successiva →</a>
							<?php endif; ?>
						</nav>
					<?php endif; ?>
				<?php else : ?>
					<div class="ba-search-empty ba-card">
						<h3>Nessun evento trovato</h3>
						<p>Non risultano eventi pubblicati corrispondenti. Prova con un altro territorio o con parole diverse.</p>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	</section>
</main>
<?php get_footer(); ?>
