<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$badaround_map_legend = array(
	'vehicle'  => 'Veicoli',
	'property' => 'Case e attività',
	'hazard'   => 'Pericoli',
	'public'   => 'Spazi pubblici',
	'animal'   => 'Animali',
	'object'   => 'Oggetti e documenti',
);
?>
<div class="ba-map-legend" aria-label="Legenda categorie BadAround">
	<strong class="ba-map-legend__title">Legenda</strong>
	<ul>
		<?php foreach ( $badaround_map_legend as $key => $label ) : ?>
			<li>
				<span class="ba-map-legend__pin ba-map-legend__pin--<?php echo esc_attr( $key ); ?>" aria-hidden="true"></span>
				<span><?php echo esc_html( $label ); ?></span>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
