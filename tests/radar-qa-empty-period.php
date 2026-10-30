<?php
declare(strict_types=1);
$src=file_get_contents(dirname(__DIR__).'/wordpress/plugins/badaround-core/includes/class-badaround-radar-private-qa.php');
foreach ([
 'Empty state explanation'=>'Nessuna segnalazione datata nel periodo selezionato',
 'No empty table'=>'if (!$view[\'cards\'])',
 'Dated-count distinction'=>'Segnalazioni con data nel periodo',
 'No artificial date attribution'=>'non sono attribuite al periodo',
 'Private permission check'=>"current_user_can('manage_options')",
 'Explicit enablement'=>"BADAROUND_RADAR_PRIVATE_QA_ENABLED === true",
] as $label=>$needle){if(!str_contains($src,$needle))throw new RuntimeException($label);echo "PASS $label\n";}
echo "RADAR-QA-EMPTY: PASS 6 source invariants\n";
