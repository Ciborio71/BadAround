<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

status_header( 404 );
nocache_headers();

get_header();
?>
<main class="ba-state-focus-page" id="main-content">
	<div class="ba-container ba-state-focus-page__inner">
		<?php
		get_template_part(
			'template-parts/components/system-state',
			null,
			array(
				'variant'         => 'empty',
				'icon'            => 'empty',
				'eyebrow'         => '404',
				'title'           => 'Pagina non trovata',
				'message'         => 'La pagina che stai cercando non esiste, è stata spostata oppure non è più disponibile.',
				'primary_label'   => 'Torna alla home',
				'primary_url'     => home_url( '/' ),
				'secondary_label' => 'Vedi le segnalazioni',
				'secondary_url'   => home_url( '/segnalazioni/' ),
			)
		);
		?>
		<p class="ba-state-focus-page__extra">
			<a href="<?php echo esc_url( home_url( '/mappa/' ) ); ?>">Oppure esplora la mappa</a>
		</p>
	</div>
</main>
<?php get_footer(); ?>
