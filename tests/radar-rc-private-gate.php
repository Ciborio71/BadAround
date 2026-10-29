<?php
declare(strict_types=1);
define('ABSPATH',__DIR__);
require_once dirname(__DIR__).'/wordpress/plugins/badaround-core/includes/class-badaround-radar-private-qa.php';
if(BadAround_Radar_Private_QA::enabled())throw new RuntimeException('Default must be OFF');
define('BADAROUND_RADAR_PRIVATE_QA_ENABLED',true);
if(!BadAround_Radar_Private_QA::enabled())throw new RuntimeException('Explicit opt-in missing');
echo "RADAR-RC: PASS default OFF and opt-in semantics\n";
