<?php
get_header();
the_post();

$event       = badaround_get_public_event_data();
$status      = $event['event_status'] ?? '';
$status_name = badaround_public_event_status_label( $status );
$type_name   = ! empty( $event['types'] ) ? $event['types'][0]->name : __( 'Segnalazione', 'badaround-child' );
$territory   = ! empty( $event['territories'] ) ? end( $event['territories'] ) : null;
$place_name  = $event['place_name'] ?: ( $territory ? $territory->name : __( 'Posizione approssimativa', 'badaround-child' ) );
$occurred    = $event['occurred_at'] ?: $event['occurred_date'];
?>
<main class="ba-page" id="main-content">
	<header class="ba-territory-head">
		<div class="ba-container">
			<div class="ba-breadcrumb"><?php esc_html_e( 'Home › Segnalazioni › Evento', 'badaround-child' ); ?></div>
			<span class="ba-badge ba-badge--urgent"><?php echo esc_html( $status_name ); ?></span>
			<h1><?php the_title(); ?></h1>
			<p class="ba-muted">
				<?php echo esc_html( implode( ' · ', array_filter( array( $occurred, $place_name ) ) ) ); ?>
			</p>
		</div>
	</header>
	<div class="ba-container ba-detail">
		<div class="ba-detail__main">
			<?php if ( has_post_thumbnail() ) : ?>
				<figure class="ba-detail__gallery"><?php the_post_thumbnail( 'large', array( 'loading' => 'eager' ) ); ?></figure>
			<?php endif; ?>
			<section class="ba-card ba-panel">
				<h2><?php esc_html_e( 'Descrizione', 'badaround-child' ); ?></h2>
				<?php the_content(); ?>
			</section>
			<section class="ba-card ba-panel">
				<h2><?php esc_html_e( 'Informazioni disponibili', 'badaround-child' ); ?></h2>
				<dl class="ba-data">
					<div><dt><?php esc_html_e( 'Categoria', 'badaround-child' ); ?></dt><dd><?php echo esc_html( $type_name ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Zona', 'badaround-child' ); ?></dt><dd><?php echo esc_html( $place_name ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Stato', 'badaround-child' ); ?></dt><dd><?php echo esc_html( $status_name ); ?></dd></div>
					<?php if ( ! empty( $event['plate_masked'] ) ) : ?>
						<div><dt><?php esc_html_e( 'Targa', 'badaround-child' ); ?></dt><dd><?php echo esc_html( $event['plate_masked'] ); ?></dd></div>
					<?php endif; ?>
					<?php if ( is_numeric( $event['reward_amount'] ) && (float) $event['reward_amount'] > 0 ) : ?>
						<div><dt><?php esc_html_e( 'Ricompensa', 'badaround-child' ); ?></dt><dd><?php echo esc_html( number_format_i18n( (float) $event['reward_amount'], 2 ) ); ?> €</dd></div>
					<?php endif; ?>
				</dl>
			</section>
			<section class="ba-card ba-map"><div class="ba-map__notice"><?php esc_html_e( 'Il civico non viene mai pubblicato.', 'badaround-child' ); ?></div></section>
		</div>
		<aside class="ba-detail__aside">
			<section class="ba-card ba-panel"><h2><?php esc_html_e( 'Hai informazioni?', 'badaround-child' ); ?></h2><p><?php esc_html_e( 'Invia una segnalazione utile senza pubblicare dati personali.', 'badaround-child' ); ?></p><a class="ba-button" href="<?php echo esc_url( home_url( '/segnala-un-evento/' ) ); ?>"><?php esc_html_e( 'Invia una segnalazione', 'badaround-child' ); ?></a></section>
			<section class="ba-card ba-sponsor"><small><?php esc_html_e( 'Contenuto sponsorizzato', 'badaround-child' ); ?></small><h3><?php esc_html_e( 'Attività della zona', 'badaround-child' ); ?></h3><p><?php esc_html_e( 'Spazio pubblicitario geolocalizzato.', 'badaround-child' ); ?></p><a href="#"><?php esc_html_e( 'Scopri di più', 'badaround-child' ); ?></a></section>
		</aside>
	</div>
</main>
<?php get_footer(); ?>
