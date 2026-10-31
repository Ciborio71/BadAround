<?php
declare(strict_types=1);
$root=dirname(__DIR__).'/wordpress/themes/badaround-child/';
$php=file_get_contents($root.'front-page.php');
$css=file_get_contents($root.'assets/css/home.css');
foreach(['Cosa ','Ultime ','Diventa una '] as $label)
 if(!str_contains($php,$label))throw new RuntimeException('Missing section heading: '.$label);
if(substr_count($php,'class="ba-home-display-heading')!==3)
 throw new RuntimeException('Expected three headings');
foreach(['ba-home-categories','ba-home-events','ba-home-sentinel','ba-home-google-map'] as $part)
 if(!str_contains($php,$part))throw new RuntimeException('Approved section missing: '.$part);
if(!str_contains($css,'font-size:clamp(3rem,5vw,5rem)')||!str_contains($css,'var(--ba-teal-600)'))
 throw new RuntimeException('Typography/color contract');
echo "HOME-HEADINGS-01: PASS\n";
