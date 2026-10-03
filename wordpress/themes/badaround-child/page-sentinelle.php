<?php
/**
 * Template Name: BadAround — Sentinelle
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

get_header();

$report_url = home_url( '/segnala-un-evento/' );
?>
<main class="ba-sentinel-page" id="main-content">
	<section class="ba-sentinel-hero">
		<div class="ba-container">
			<div class="ba-sentinel-hero__grid">
				<div class="ba-sentinel-hero__copy">
					<span class="ba-eyebrow">Sentinelle BadAround</span>
					<h1>Segui le zone che ti interessano.</h1>
					<p>Ricevi avvisi utili quando vengono pubblicate nuove segnalazioni rilevanti vicino casa, al lavoro o nei luoghi che vuoi tenere sotto controllo.</p>
					<div class="ba-sentinel-hero__actions">
						<a class="ba-button" href="#attiva-sentinella">Attiva una Sentinella</a>
						<a class="ba-button ba-button--outline" href="<?php echo esc_url( home_url( '/mappa/' ) ); ?>">Esplora la mappa</a>
					</div>
				</div>

				<div class="ba-sentinel-radar" aria-hidden="true">
					<div class="ba-sentinel-radar__ring ba-sentinel-radar__ring--1"></div>
					<div class="ba-sentinel-radar__ring ba-sentinel-radar__ring--2"></div>
					<div class="ba-sentinel-radar__ring ba-sentinel-radar__ring--3"></div>
					<span class="ba-sentinel-radar__dot ba-sentinel-radar__dot--1"></span>
					<span class="ba-sentinel-radar__dot ba-sentinel-radar__dot--2"></span>
					<span class="ba-sentinel-radar__dot ba-sentinel-radar__dot--3"></span>
					<span class="ba-sentinel-radar__center"></span>
				</div>
			</div>
		</div>
	</section>

	<section class="ba-sentinel-benefits">
		<div class="ba-container">
			<div class="ba-sentinel-benefits__grid">
				<article class="ba-card ba-sentinel-benefit">
					<span class="ba-sentinel-benefit__icon" aria-hidden="true">◎</span>
					<h2>Scegli una zona</h2>
					<p>Comune, località, quartiere o area che vuoi seguire.</p>
				</article>
				<article class="ba-card ba-sentinel-benefit">
					<span class="ba-sentinel-benefit__icon" aria-hidden="true">≋</span>
					<h2>Filtra ciò che conta</h2>
					<p>Puoi limitare gli avvisi alle categorie davvero rilevanti per te.</p>
				</article>
				<article class="ba-card ba-sentinel-benefit">
					<span class="ba-sentinel-benefit__icon" aria-hidden="true">◌</span>
					<h2>Scegli la frequenza</h2>
					<p>Immediata, riepilogo giornaliero oppure solo aggiornamenti importanti.</p>
				</article>
			</div>
		</div>
	</section>

	<section class="ba-sentinel-builder" id="attiva-sentinella">
		<div class="ba-container ba-sentinel-builder__grid">
			<div class="ba-sentinel-builder__intro">
				<span class="ba-eyebrow">Crea una Sentinella</span>
				<h2>Configura gli avvisi in pochi passaggi</h2>
				<p>Questa è la prima interfaccia del configuratore. Il salvataggio definitivo verrà collegato allo strato account/notifiche.</p>

				<div class="ba-sentinel-preview ba-card">
					<div class="ba-sentinel-preview__head">
						<div class="ba-sentinel-preview__icon">◎</div>
						<div>
							<span>Anteprima</span>
							<strong data-ba-sentinel-preview-area>La tua zona</strong>
						</div>
					</div>
					<div class="ba-sentinel-preview__meta">
						<span><strong data-ba-sentinel-preview-categories>Tutte le categorie</strong><small>Eventi seguiti</small></span>
						<span><strong data-ba-sentinel-preview-radius>3 km</strong><small>Raggio</small></span>
						<span><strong data-ba-sentinel-preview-frequency>Immediata</strong><small>Frequenza</small></span>
					</div>
				</div>
			</div>

			<form class="ba-card ba-sentinel-form" data-ba-sentinel-form>
				<div class="ba-sentinel-form__step">
					<div class="ba-sentinel-form__step-head">
						<span>1</span>
						<div>
							<h3>Quale zona vuoi seguire?</h3>
							<p>Cerca un Comune, una località, una frazione o un quartiere.</p>
						</div>
					</div>
					<label class="ba-sentinel-field">
						<span>Zona</span>
						<input type="text" name="area" placeholder="Es. Torvaianica" data-ba-sentinel-area>
					</label>
				</div>

				<div class="ba-sentinel-form__step">
					<div class="ba-sentinel-form__step-head">
						<span>2</span>
						<div>
							<h3>Quanto deve essere ampia l'area?</h3>
							<p>Il raggio potrà essere raffinato in base alla struttura geografica della zona.</p>
						</div>
					</div>
					<div class="ba-sentinel-choice-grid" data-ba-sentinel-radius>
						<label><input type="radio" name="radius" value="1 km"><span>1 km</span></label>
						<label><input type="radio" name="radius" value="3 km" checked><span>3 km</span></label>
						<label><input type="radio" name="radius" value="5 km"><span>5 km</span></label>
						<label><input type="radio" name="radius" value="10 km"><span>10 km</span></label>
					</div>
				</div>

				<div class="ba-sentinel-form__step">
					<div class="ba-sentinel-form__step-head">
						<span>3</span>
						<div>
							<h3>Quali eventi vuoi seguire?</h3>
							<p>Puoi selezionare una o più categorie.</p>
						</div>
					</div>
					<div class="ba-sentinel-category-grid" data-ba-sentinel-categories>
						<label><input type="checkbox" value="Furti"><span>Furti</span></label>
						<label><input type="checkbox" value="Sicurezza"><span>Sicurezza</span></label>
						<label><input type="checkbox" value="Veicoli"><span>Veicoli</span></label>
						<label><input type="checkbox" value="Degrado"><span>Degrado</span></label>
						<label><input type="checkbox" value="Pericoli"><span>Pericoli</span></label>
						<label><input type="checkbox" value="Animali"><span>Animali</span></label>
					</div>
				</div>

				<div class="ba-sentinel-form__step">
					<div class="ba-sentinel-form__step-head">
						<span>4</span>
						<div>
							<h3>Quando vuoi essere avvisato?</h3>
							<p>Scegli la frequenza più adatta.</p>
						</div>
					</div>
					<div class="ba-sentinel-frequency" data-ba-sentinel-frequency>
						<label><input type="radio" name="frequency" value="Immediata" checked><span><strong>Immediata</strong><small>Quando viene pubblicato un evento rilevante</small></span></label>
						<label><input type="radio" name="frequency" value="Giornaliera"><span><strong>Riepilogo giornaliero</strong><small>Un solo aggiornamento con le novità della zona</small></span></label>
						<label><input type="radio" name="frequency" value="Importanti"><span><strong>Solo eventi importanti</strong><small>Riduce al minimo le notifiche</small></span></label>
					</div>
				</div>

				<div class="ba-sentinel-form__footer">
					<p>Potrai modificare o disattivare questa Sentinella in qualsiasi momento.</p>
					<button class="ba-button" type="button" data-ba-sentinel-submit>Continua</button>
				</div>
			</form>
		</div>
	</section>

	<section class="ba-sentinel-dashboard">
		<div class="ba-container">
			<div class="ba-sentinel-section-head">
				<div>
					<span class="ba-eyebrow">Le tue Sentinelle</span>
					<h2>Un'unica vista per le zone che segui</h2>
				</div>
				<span class="ba-sentinel-section-head__note">Anteprima area account</span>
			</div>

			<div class="ba-sentinel-dashboard__grid">
				<article class="ba-card ba-sentinel-zone-card">
					<div class="ba-sentinel-zone-card__top">
						<div><span class="ba-sentinel-zone-card__dot"></span><strong>Torvaianica</strong></div>
						<span class="ba-badge ba-badge--resolved">Attiva</span>
					</div>
					<p>3 km · Furti, Sicurezza, Veicoli</p>
					<div class="ba-sentinel-zone-card__stats"><span><strong>3</strong><small>nuove oggi</small></span><span><strong>18</strong><small>attive</small></span></div>
					<div class="ba-sentinel-zone-card__actions"><button type="button">Modifica</button><button type="button">Pausa</button></div>
				</article>

				<article class="ba-card ba-sentinel-zone-card ba-sentinel-zone-card--muted">
					<div class="ba-sentinel-zone-card__top">
						<div><span class="ba-sentinel-zone-card__dot"></span><strong>Roma centro</strong></div>
						<span class="ba-badge ba-badge--info">Demo</span>
					</div>
					<p>5 km · Tutte le categorie</p>
					<div class="ba-sentinel-zone-card__stats"><span><strong>—</strong><small>nuove oggi</small></span><span><strong>—</strong><small>attive</small></span></div>
					<div class="ba-sentinel-zone-card__actions"><button type="button">Modifica</button><button type="button">Pausa</button></div>
				</article>

				<article class="ba-sentinel-add-card">
					<div>
						<span aria-hidden="true">+</span>
						<h3>Aggiungi una nuova zona</h3>
						<p>Puoi creare più Sentinelle per luoghi diversi.</p>
						<a href="#attiva-sentinella">Crea Sentinella</a>
					</div>
				</article>
			</div>
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
