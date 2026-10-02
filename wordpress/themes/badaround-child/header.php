<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$report_url = home_url( '/segnala-un-evento/' );
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="ba-skip-link" href="#main-content"><?php esc_html_e( 'Vai al contenuto', 'badaround-child' ); ?></a>

<header class="ba-site-header" data-ba-header>
	<div class="ba-site-header__inner ba-container--wide">
		<a class="ba-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php esc_attr_e( 'BadAround — Home', 'badaround-child' ); ?>">
			<span class="ba-brand__mark" aria-hidden="true">
				<svg viewBox="0 0 44 44" focusable="false">
					<circle cx="22" cy="22" r="17" fill="none" stroke="currentColor" stroke-width="2.4"/>
					<circle cx="22" cy="22" r="8.5" fill="none" stroke="currentColor" stroke-width="2.4" opacity=".72"/>
					<circle cx="22" cy="22" r="3.3" fill="currentColor"/>
					<path d="M22 1.8v5.1M22 37.1v5.1M1.8 22h5.1M37.1 22h5.1" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
				</svg>
			</span>
			<span class="ba-brand__wordmark">Bad<span>Around</span></span>
		</a>

		<nav class="ba-primary-nav" id="ba-primary-nav" aria-label="<?php esc_attr_e( 'Navigazione principale', 'badaround-child' ); ?>">
			<a href="<?php echo esc_url( home_url( '/mappa/' ) ); ?>">Mappa</a>
			<a href="<?php echo esc_url( home_url( '/#segnalazioni' ) ); ?>">Segnalazioni</a>
			<a href="<?php echo esc_url( home_url( '/#come-funziona' ) ); ?>">Come funziona</a>
			<a href="<?php echo esc_url( home_url( '/#sentinelle' ) ); ?>">Sentinelle</a>
		</nav>

		<div class="ba-header-actions">
			<a class="ba-header-login" href="<?php echo esc_url( wp_login_url() ); ?>">Accedi</a>
			<a class="ba-button ba-button--urgent ba-header-report" href="<?php echo esc_url( $report_url ); ?>">
				<span class="ba-header-report__plus" aria-hidden="true">+</span>
				Segnala un evento
			</a>
			<button class="ba-menu-toggle" type="button" aria-expanded="false" aria-controls="ba-mobile-menu" aria-label="<?php esc_attr_e( 'Apri menu', 'badaround-child' ); ?>">
				<span></span><span></span><span></span>
			</button>
		</div>
	</div>

	<div class="ba-mobile-menu" id="ba-mobile-menu" hidden>
		<nav aria-label="<?php esc_attr_e( 'Navigazione mobile', 'badaround-child' ); ?>">
			<a href="<?php echo esc_url( home_url( '/mappa/' ) ); ?>">Mappa</a>
			<a href="<?php echo esc_url( home_url( '/#segnalazioni' ) ); ?>">Segnalazioni</a>
			<a href="<?php echo esc_url( home_url( '/#come-funziona' ) ); ?>">Come funziona</a>
			<a href="<?php echo esc_url( home_url( '/#sentinelle' ) ); ?>">Sentinelle</a>
			<a href="<?php echo esc_url( wp_login_url() ); ?>">Accedi</a>
			<a class="ba-button ba-button--urgent" href="<?php echo esc_url( $report_url ); ?>">Segnala un evento</a>
		</nav>
	</div>
</header>
