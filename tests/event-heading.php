<?php
define('ABSPATH',__DIR__);
require dirname(__DIR__).'/wordpress/themes/badaround-child/inc/event-heading.php';
$cases = [
 'Honda SH, colore bianco — veicolo segnalato rubato a Roma'=>'Honda SH, Colore Bianco | Veicolo Segnalato Rubato a Roma',
 'Furto di parti e accessori del veicolo a Roma'=>'Furto di Parti e Accessori del Veicolo a Roma',
 'Mazda CX-5 – furto a L’Aquila'=>'Mazda CX-5 | Furto a L’Aquila',
 'Animale ferito nell’area di Pomezia'=>'Animale Ferito nell’Area di Pomezia',
 'iPhone 15 - oggetto smarrito a Roma'=>'iPhone 15 | Oggetto Smarrito a Roma',
 'Pericolo né confermato né escluso'=>'Pericolo né Confermato né Escluso',
];
foreach($cases as $input=>$expected){if(badaround_event_heading($input)!==$expected){fwrite(STDERR,'FAIL '.$input.' => '.badaround_event_heading($input).PHP_EOL);exit(1);}}
echo "EVENT_HEADING_SUITE_PASS\n";
