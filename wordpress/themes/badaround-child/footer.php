<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<footer class="ba-site-footer">
	<div class="ba-container--wide">
		<div class="ba-footer-main">
			<div class="ba-footer-brand">
				<a class="ba-brand ba-brand--footer" href="<?php echo esc_url( home_url( '/' ) ); ?>">
					<span class="ba-brand__mark" aria-hidden="true">
						<svg viewBox="0 0 44 44" focusable="false">
							<circle cx="22" cy="22" r="17" fill="none" stroke="currentColor" stroke-width="2.4"/>
							<circle cx="22" cy="22" r="8.5" fill="none" stroke="currentColor" stroke-width="2.4" opacity=".72"/>
							<circle cx="22" cy="22" r="3.3" fill="currentColor"/>
						</svg>
					</span>
					<span class="ba-brand__wordmark">Bad<span>Around</span></span>
				</a>
				<p>La rete locale che raccoglie segnalazioni utili, richieste di aiuto e informazioni dal territorio.</p>
				<p class="ba-footer-note">BadAround non è un servizio di emergenza. In caso di pericolo immediato contatta il 112 o l'autorità competente.</p>
			</div>

			<div class="ba-footer-nav">
				<div>
					<h2>Esplora</h2>
					<a href="<?php echo esc_url( home_url( '/#ba-map' ) ); ?>">Mappa</a>
					<a href="<?php echo esc_url( home_url( '/#segnalazioni' ) ); ?>">Segnalazioni</a>
					<a href="<?php echo esc_url( home_url( '/#come-funziona' ) ); ?>">Come funziona</a>
				</div>
				<div>
					<h2>Community</h2>
					<a href="<?php echo esc_url( home_url( '/#sentinelle' ) ); ?>">Sentinelle</a>
					<a href="<?php echo esc_url( home_url( '/segnala-un-evento/' ) ); ?>">Segnala un evento</a>
					<a href="#">Regole della community</a>
				</div>
				<div>
					<h2>Supporto</h2>
					<a href="#">Centro assistenza</a>
					<a href="#">Contatti</a>
					<a href="#">Segnala un problema</a>
				</div>
				<div>
					<h2>Legale</h2>
					<a href="#">Privacy</a>
					<a href="#">Termini di utilizzo</a>
					<a href="#">Cookie</a>
				</div>
			</div>
		</div>

		<div class="ba-footer-bottom">
			<p>© <?php echo esc_html( wp_date( 'Y' ) ); ?> BadAround. Tutti i diritti riservati.</p>
			<p>Informazioni locali generate dalla community e soggette a moderazione.</p>
		</div>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
