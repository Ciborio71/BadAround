<?php
/**
 * Template Name: BadAround — Sentinelle
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

get_header();

$report_url = home_url( '/segnala-un-evento/' );
$status = isset( $_GET['ba_sentinel_status'] ) ? sanitize_key( wp_unslash( $_GET['ba_sentinel_status'] ) ) : '';

$territories = get_terms(
	array(
		'taxonomy'   => 'ba_territorio',
		'hide_empty' => false,
		'orderby'    => 'name',
		'order'      => 'ASC',
	)
);
if ( is_wp_error( $territories ) ) {
	$territories = array();
}

$event_terms = get_terms(
	array(
		'taxonomy'   => 'ba_tipo_evento',
		'hide_empty' => false,
		'orderby'    => 'name',
		'order'      => 'ASC',
	)
);
if ( is_wp_error( $event_terms ) ) {
	$event_terms = array();
}

$categories = array();
$event_types = array();
foreach ( $event_terms as $term ) {
	if ( 0 === (int) $term->parent ) {
		$categories[] = $term;
	} else {
		$event_types[] = $term;
	}
}

$requested_territory = isset( $_GET['territory'] ) ? sanitize_title( wp_unslash( $_GET['territory'] ) ) : '';
$requested_category  = isset( $_GET['category'] ) ? sanitize_title( wp_unslash( $_GET['category'] ) ) : '';
$requested_event_type = isset( $_GET['event_type'] ) ? sanitize_title( wp_unslash( $_GET['event_type'] ) ) : '';

function badaround_sentinel_territory_label( WP_Term $term ) {
	$names = array();
	foreach ( array_reverse( get_ancestors( $term->term_id, 'ba_territorio', 'taxonomy' ) ) as $ancestor_id ) {
		$ancestor = get_term( $ancestor_id, 'ba_territorio' );
		if ( $ancestor instanceof WP_Term ) {
			$names[] = $ancestor->name;
		}
	}
	$names[] = $term->name;
	return implode( ' → ', $names );
}
?>
<main class="ba-sentinel-page" id="main-content">
	<section class="ba-sentinel-hero">
		<div class="ba-container">
			<div class="ba-sentinel-hero__grid">
				<div class="ba-sentinel-hero__copy">
					<span class="ba-eyebrow">Sentinelle BadAround · gratuite</span>
					<h1>Quello che succede vicino a te,<br><em>senza doverlo cercare.</em></h1>
					<p>Attiva una Sentinella sulla zona che ti interessa. Quando BadAround pubblica una segnalazione compatibile, ricevi un avviso via email.</p>
					<div class="ba-sentinel-trust" aria-label="Vantaggi della Sentinella">
						<span>✓ Nessun account</span><span>✓ Identità non pubblica</span><span>✓ Disattivabile quando vuoi</span>
					</div>
					<div class="ba-sentinel-hero__actions">
						<a class="ba-button" href="#attiva-sentinella">Attiva gratis la tua Sentinella</a>
						<a class="ba-button ba-button--outline" href="<?php echo esc_url( home_url( '/mappa/' ) ); ?>">Esplora la mappa</a>
					</div>
				</div>

				<div class="ba-sentinel-hero__visual">
					<img src="<?php echo esc_url( content_url( '/uploads/2026/10/BadAround-—-Hero-Sentinelle-visual.png' ) ); ?>" alt="Persona che osserva la propria zona con smartphone e segnalazioni BadAround" width="1536" height="1024" fetchpriority="high" decoding="async">
				</div>
			</div>
		</div>
	</section>

	<?php if ( $status ) : ?>
		<section class="ba-sentinel-status-section">
			<div class="ba-container">
				<?php if ( 'unsubscribed' === $status ) : ?>
					<div class="ba-card ba-sentinel-status ba-sentinel-status--success" role="status">
						<strong>Sentinella disattivata.</strong>
						<p>Non riceverai più notifiche relative ai criteri di questa Sentinella.</p>
					</div>
				<?php elseif ( 'already-unsubscribed' === $status ) : ?>
					<div class="ba-card ba-sentinel-status" role="status">
						<strong>Sentinella già disattivata.</strong>
						<p>Non sono necessarie altre operazioni.</p>
					</div>
				<?php elseif ( 'unsubscribe-invalid' === $status ) : ?>
					<div class="ba-card ba-sentinel-status ba-sentinel-status--error" role="alert">
						<strong>Link non valido.</strong>
						<p>Il collegamento di disattivazione non è valido o non può essere utilizzato.</p>
					</div>
				<?php elseif ( 'confirmed' === $status ) : ?>
					<div class="ba-card ba-sentinel-status ba-sentinel-status--success" role="status">
						<strong>Sentinella attivata.</strong>
						<p>La verifica email è completata. La Sentinella è ora attiva.</p>
					</div>
				<?php elseif ( 'already-active' === $status ) : ?>
					<div class="ba-card ba-sentinel-status" role="status">
						<strong>Sentinella già attiva.</strong>
						<p>Questo link era già stato utilizzato correttamente.</p>
					</div>
				<?php elseif ( 'expired' === $status ) : ?>
					<div class="ba-card ba-sentinel-status ba-sentinel-status--warning" role="alert">
						<strong>Link di verifica scaduto.</strong>
						<p>Ricrea la stessa Sentinella per ricevere un nuovo link di conferma.</p>
					</div>
				<?php else : ?>
					<div class="ba-card ba-sentinel-status ba-sentinel-status--error" role="alert">
						<strong>Link non valido.</strong>
						<p>Il collegamento di verifica non è valido o non può più essere utilizzato.</p>
					</div>
				<?php endif; ?>
			</div>
		</section>
	<?php endif; ?>

	<section class="ba-sentinel-benefits">
		<div class="ba-container">
			<div class="ba-sentinel-benefits__grid">
				<article class="ba-card ba-sentinel-benefit">
					<span class="ba-sentinel-benefit__icon" aria-hidden="true">⌖</span>
					<h2>La tua zona, sotto osservazione</h2>
					<p>Scegli il territorio che conta per te: dal comune fino alla località o al quartiere.</p>
				</article>
				<article class="ba-card ba-sentinel-benefit">
					<span class="ba-sentinel-benefit__icon" aria-hidden="true">◉</span>
					<h2>Solo ciò che ti interessa</h2>
					<p>Segui tutto oppure concentrati su veicoli, animali, pericoli e le altre categorie BadAround.</p>
				</article>
				<article class="ba-card ba-sentinel-benefit">
					<span class="ba-sentinel-benefit__icon" aria-hidden="true">✉</span>
					<h2>Ti avvisiamo noi</h2>
					<p>Conferma l'email una sola volta. Quando c'è una nuova segnalazione compatibile, BadAround può avvisarti.</p>
				</article>
			</div>
		</div>
	</section>

	<section class="ba-sentinel-builder" id="attiva-sentinella">
		<div class="ba-container ba-sentinel-builder__grid">
			<div class="ba-sentinel-builder__intro">
				<span class="ba-eyebrow">Crea una Sentinella</span>
				<h2>La tua Sentinella in meno di un minuto.</h2>
				<p>Scegli una zona, indica cosa vuoi tenere d'occhio e inserisci la tua email. Nessun profilo pubblico da creare.</p>
				<div class="ba-sentinel-builder__promise"><strong>È gratis.</strong> Tre scelte e hai finito.</div>

				<div class="ba-sentinel-preview ba-card">
					<div class="ba-sentinel-preview__head">
						<div class="ba-sentinel-preview__icon">◎</div>
						<div>
							<span>La tua Sentinella</span>
							<strong data-ba-sentinel-preview-area>Seleziona un territorio</strong>
						</div>
					</div>
					<div class="ba-sentinel-preview__meta">
						<span><strong data-ba-sentinel-preview-category>Tutte le categorie</strong><small>Categoria</small></span>
						<span><strong data-ba-sentinel-preview-event-type>Tutte le tipologie</strong><small>Tipologia</small></span>
						<span><strong>Via email</strong><small>Notifica</small></span>
					</div>
				</div>
			</div>

			<form class="ba-card ba-sentinel-form" data-ba-sentinel-form novalidate>
				<div class="ba-sentinel-form__step">
					<div class="ba-sentinel-form__step-head">
						<span>1</span>
						<div>
							<h3>Quale territorio vuoi seguire?</h3>
							<p>Dove vuoi che BadAround tenga gli occhi aperti per te?</p>
						</div>
					</div>
					<label class="ba-sentinel-field">
						<span>Territorio</span>
						<select name="territory_term_id" required data-ba-sentinel-territory>
							<option value="">Seleziona un territorio</option>
							<?php foreach ( $territories as $term ) : ?>
								<option value="<?php echo esc_attr( $term->term_id ); ?>" data-slug="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $requested_territory, $term->slug ); ?>>
									<?php echo esc_html( badaround_sentinel_territory_label( $term ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</label>
				</div>

				<div class="ba-sentinel-form__step">
					<div class="ba-sentinel-form__step-head">
						<span>2</span>
						<div>
							<h3>Quali eventi ti interessano?</h3>
							<p>Scegli tutto oppure filtra le segnalazioni che per te contano di più.</p>
						</div>
					</div>
					<div class="ba-sentinel-fields-grid">
						<label class="ba-sentinel-field">
							<span>Categoria</span>
							<select name="category_term_id" data-ba-sentinel-category>
								<option value="">Tutte le categorie</option>
								<?php foreach ( $categories as $term ) : ?>
									<option value="<?php echo esc_attr( $term->term_id ); ?>" data-slug="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $requested_category, $term->slug ); ?>>
										<?php echo esc_html( $term->name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</label>
						<label class="ba-sentinel-field">
							<span>Tipologia</span>
							<select name="event_type_term_id" data-ba-sentinel-event-type>
								<option value="">Tutte le tipologie</option>
								<?php foreach ( $event_types as $term ) : ?>
									<option value="<?php echo esc_attr( $term->term_id ); ?>" data-parent="<?php echo esc_attr( $term->parent ); ?>" data-slug="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $requested_event_type, $term->slug ); ?>>
										<?php echo esc_html( $term->name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</label>
					</div>
				</div>

				<div class="ba-sentinel-form__step">
					<div class="ba-sentinel-form__step-head">
						<span>3</span>
						<div>
							<h3>Dove vuoi ricevere gli avvisi?</h3>
							<p>Nessun account: ti inviamo solo il link per confermare e attivare la Sentinella.</p>
						</div>
					</div>
					<label class="ba-sentinel-field">
						<span>Email</span>
						<input type="email" name="email" autocomplete="email" inputmode="email" placeholder="nome@esempio.it" required data-ba-sentinel-email>
					</label>
				</div>

				<div class="ba-sentinel-form__footer">
					<p>Dopo la verifica, BadAround può inviarti una notifica quando viene pubblicato un evento compatibile. Puoi disattivare in qualsiasi momento quella Sentinella dal link presente nelle comunicazioni.</p>
					<button class="ba-button" type="submit" data-ba-sentinel-submit>Attiva gratis la Sentinella</button>
				</div>
				<div class="ba-sentinel-form__message" data-ba-sentinel-message role="status" aria-live="polite" hidden></div>
			</form>
		</div>
	</section>

	<section class="ba-sentinel-final-cta">
		<div class="ba-container">
			<div class="ba-sentinel-final-cta__box">
				<div>
					<span class="ba-eyebrow">Partecipa alla rete locale</span>
					<h2>Vedi qualcosa di utile per la zona?</h2>
					<p>Contribuisci con una segnalazione chiara e localizzata.</p>
				</div>
				<a class="ba-button ba-button--urgent" href="<?php echo esc_url( $report_url ); ?>">Segnala un evento</a>
			</div>
		</div>
	</section>
</main>
<?php get_footer(); ?>
