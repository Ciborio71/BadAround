<?php
declare(strict_types=1);
$src=file_get_contents(dirname(__DIR__).'/wordpress/plugins/badaround-core/includes/class-badaround-radar-private-qa.php');
$checks=array(
  'Coverage count visible'=>str_contains($src,"date_coverage_count"),
  'Missing dates visible'=>str_contains($src,"undated_or_invalid_count"),
  'Insufficient coverage warning'=>str_contains($src,"Copertura temporale insufficiente"),
  'No substitution of publication date'=>str_contains($src,"Le date mancanti non vengono sostituite"),
  'Empty group fallback'=>str_contains($src,"Nessuna segnalazione datata nel periodo selezionato"),
  'Admin period form'=>str_contains($src,'name="ba_radar_days"'),
  'Valid period options'=>str_contains($src,'array(7,30)'),
  'Admin tools destination'=>str_contains($src,"admin_url('tools.php')"),
  'Administrator guard'=>str_contains($src,"current_user_can('manage_options')"),
  'Default disabled feature gate'=>str_contains($src,"BADAROUND_RADAR_PRIVATE_QA_ENABLED"),
);
foreach($checks as $name=>$yes) {if(!$yes)throw new RuntimeException($name);echo "PASS $name\n";}
echo 'RADAR-RC-COVERAGE: PASS '.count($checks).' source checks'.PHP_EOL;
