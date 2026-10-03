<?php
/**
 * Template Name: BadAround — Area personale
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

get_header();

$is_logged = is_user_logged_in();
$user = $is_logged ? wp_get_current_user() : null;
$display_name = $user ? $user->display_name : '';
$initial = $display_name ? mb_strtoupper( mb_substr( $display_name, 0, 1 ) ) : 'B';
?>
<main class="ba-account-page" id="main-content">
	<?php if ( ! $is_logged ) : ?>
		<section class="ba-account-auth">
			<div class="ba-container ba-account-auth__grid">
				<div class="ba-account-auth__intro">
					<span class="ba-eyebrow">Il tuo spazio BadAround</span>
					<h1>Segui ciò che conta. Tutto in un unico posto.</h1>
					<p>Accedi per gestire le tue segnalazioni, le Sentinelle, i contenuti salvati e le preferenze di notifica.</p>

					<div class="ba-account-auth__benefits">
						<div><span aria-hidden="true">◎</span><p><strong>Le tue Sentinelle</strong><small>Zone e categorie che vuoi seguire.</small></p></div>
						<div><span aria-hidden="true">↗</span><p><strong>Le tue segnalazioni</strong><small>Controlla stato e aggiornamenti.</small></p></div>
						<div><span aria-hidden="true">☆</span><p><strong>Eventi salvati</strong><small>Ritrova rapidamente ciò che ti interessa.</small></p></div>
					</div>
				</div>

				<div class="ba-card ba-account-login-card">
					<div class="ba-account-login-card__head">
						<span class="ba-account-login-card__mark" aria-hidden="true">◎</span>
						<div>
							<span>Area personale</span>
							<h2>Accedi a BadAround</h2>
						</div>
					</div>

					<?php
					wp_login_form(
						array(
							'echo'           => true,
							'redirect'       => get_permalink(),
							'label_username' => 'Email o nome utente',
							'label_password' => 'Password',
							'label_remember' => 'Resta connesso',
							'label_log_in'   => 'Accedi',
							'remember'       => true,
						)
					);
					?>

					<div class="ba-account-login-card__links">
						<a href="<?php echo esc_url( wp_lostpassword_url( get_permalink() ) ); ?>">Password dimenticata?</a>
						<?php if ( get_option( 'users_can_register' ) ) : ?>
							<a href="<?php echo esc_url( wp_registration_url() ); ?>">Crea un account</a>
						<?php endif; ?>
					</div>

					<p class="ba-account-login-card__privacy">Accedendo accetti le condizioni del servizio e l'informativa privacy applicabile.</p>
				</div>
			</div>
		</section>
	<?php else : ?>
		<section class="ba-account-shell">
			<div class="ba-container ba-account-shell__grid">
				<aside class="ba-account-sidebar">
					<div class="ba-account-profile-card">
						<div class="ba-account-avatar"><?php echo esc_html( $initial ); ?></div>
						<div>
							<span>Account</span>
							<strong><?php echo esc_html( $display_name ?: $user->user_login ); ?></strong>
							<small><?php echo esc_html( $user->user_email ); ?></small>
						</div>
					</div>

					<nav class="ba-account-nav" aria-label="Area personale">
						<button class="is-active" type="button" data-ba-account-tab="overview" aria-pressed="true">Panoramica</button>
						<button type="button" data-ba-account-tab="reports" aria-pressed="false">Le mie segnalazioni</button>
						<button type="button" data-ba-account-tab="sentinels" aria-pressed="false">Sentinelle</button>
						<button type="button" data-ba-account-tab="saved" aria-pressed="false">Salvati</button>
						<button type="button" data-ba-account-tab="profile" aria-pressed="false">Profilo e preferenze</button>
					</nav>

					<a class="ba-account-logout" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">Esci dall'account</a>
				</aside>

				<div class="ba-account-content">
					<section class="ba-account-panel is-active" data-ba-account-panel="overview">
						<div class="ba-account-panel__head">
							<div>
								<span class="ba-eyebrow">Area personale</span>
								<h1>Ciao, <?php echo esc_html( $display_name ?: $user->user_login ); ?></h1>
								<p>Qui trovi un riepilogo delle attività e delle zone che stai seguendo.</p>
							</div>
							<a class="ba-button ba-button--urgent" href="<?php echo esc_url( home_url( '/segnala-un-evento/' ) ); ?>">Nuova segnalazione</a>
						</div>

						<div class="ba-account-kpis">
							<div class="ba-card"><span>Segnalazioni</span><strong>—</strong><small>inviate da te</small></div>
							<div class="ba-card"><span>Sentinelle</span><strong>—</strong><small>zone seguite</small></div>
							<div class="ba-card"><span>Salvati</span><strong>—</strong><small>eventi conservati</small></div>
							<div class="ba-card"><span>Novità</span><strong>—</strong><small>da leggere</small></div>
						</div>

						<div class="ba-account-overview-grid">
							<section class="ba-card ba-account-widget">
								<div class="ba-account-widget__head"><div><span class="ba-eyebrow">Sentinelle</span><h2>Zone seguite</h2></div><button type="button" data-ba-account-jump="sentinels">Gestisci</button></div>
								<div class="ba-account-empty-inline"><span>◎</span><div><strong>Nessuna Sentinella collegata</strong><p>Le zone seguite appariranno qui.</p></div></div>
								<a class="ba-account-text-link" href="<?php echo esc_url( home_url( '/sentinelle/' ) ); ?>">Crea una Sentinella →</a>
							</section>

							<section class="ba-card ba-account-widget">
								<div class="ba-account-widget__head"><div><span class="ba-eyebrow">Attività</span><h2>Ultimi aggiornamenti</h2></div></div>
								<div class="ba-account-empty-inline"><span>◌</span><div><strong>Nessun aggiornamento recente</strong><p>Quando qualcosa cambia, lo vedrai qui.</p></div></div>
							</section>
						</div>
					</section>

					<section class="ba-account-panel" data-ba-account-panel="reports" hidden>
						<div class="ba-account-panel__head">
							<div><span class="ba-eyebrow">Segnalazioni</span><h1>Le mie segnalazioni</h1><p>Controlla lo stato dei contenuti che hai inviato.</p></div>
							<a class="ba-button ba-button--urgent" href="<?php echo esc_url( home_url( '/segnala-un-evento/' ) ); ?>">Nuova segnalazione</a>
						</div>
						<div class="ba-card ba-account-empty-state"><span>↗</span><h2>Nessuna segnalazione collegata all'account</h2><p>Quando il backend account verrà collegato al flusso di invio, qui compariranno stato, data e aggiornamenti.</p><a class="ba-button" href="<?php echo esc_url( home_url( '/segnala-un-evento/' ) ); ?>">Segnala un evento</a></div>
					</section>

					<section class="ba-account-panel" data-ba-account-panel="sentinels" hidden>
						<div class="ba-account-panel__head"><div><span class="ba-eyebrow">Sentinelle</span><h1>Le zone che segui</h1><p>Gestisci area, categorie e frequenza degli avvisi.</p></div><a class="ba-button" href="<?php echo esc_url( home_url( '/sentinelle/' ) ); ?>">Aggiungi zona</a></div>
						<div class="ba-card ba-account-empty-state"><span>◎</span><h2>Nessuna Sentinella attiva</h2><p>Crea la prima Sentinella per ricevere avvisi relativi a una zona.</p><a class="ba-button" href="<?php echo esc_url( home_url( '/sentinelle/' ) ); ?>">Crea Sentinella</a></div>
					</section>

					<section class="ba-account-panel" data-ba-account-panel="saved" hidden>
						<div class="ba-account-panel__head"><div><span class="ba-eyebrow">Salvati</span><h1>Eventi salvati</h1><p>Una raccolta personale delle segnalazioni che vuoi ritrovare rapidamente.</p></div><a class="ba-button ba-button--outline" href="<?php echo esc_url( home_url( '/mappa/' ) ); ?>">Esplora la mappa</a></div>
						<div class="ba-card ba-account-empty-state"><span>☆</span><h2>Non hai ancora salvato eventi</h2><p>Il pulsante Salva delle pagine evento verrà collegato a questa sezione.</p><a class="ba-button" href="<?php echo esc_url( home_url( '/mappa/' ) ); ?>">Trova segnalazioni</a></div>
					</section>

					<section class="ba-account-panel" data-ba-account-panel="profile" hidden>
						<div class="ba-account-panel__head"><div><span class="ba-eyebrow">Profilo</span><h1>Profilo e preferenze</h1><p>Impostazioni essenziali dell'account BadAround.</p></div></div>

						<div class="ba-account-settings-grid">
							<section class="ba-card ba-account-settings-card">
								<h2>Dati account</h2>
								<label><span>Nome visualizzato</span><input type="text" value="<?php echo esc_attr( $display_name ); ?>" disabled></label>
								<label><span>Email</span><input type="email" value="<?php echo esc_attr( $user->user_email ); ?>" disabled></label>
								<p>I dati sono mostrati in sola lettura in questa fase UI.</p>
							</section>

							<section class="ba-card ba-account-settings-card">
								<h2>Preferenze notifiche</h2>
								<label class="ba-account-switch"><input type="checkbox" checked disabled><span></span><p><strong>Avvisi Sentinelle</strong><small>Ricevi notifiche dalle zone seguite.</small></p></label>
								<label class="ba-account-switch"><input type="checkbox" disabled><span></span><p><strong>Riepilogo settimanale</strong><small>Una sintesi delle attività locali.</small></p></label>
								<p>Le preferenze saranno collegate al sistema notifiche.</p>
							</section>
						</div>
					</section>
				</div>
			</div>
		</section>
	<?php endif; ?>
</main>
<?php get_footer(); ?>
