<?php
define('ABSPATH',__DIR__);require __DIR__.'/../wordpress/themes/badaround-child/inc/pins.php';
function pin_check($ok,$message){if(!$ok)throw new RuntimeException($message);echo "PASS $message\n";}
$c=badaround_pin_catalog();pin_check(count($c)===8,'six categories plus neutral and resolved');
foreach($c as $key=>$entry){
 $file=file_get_contents(__DIR__.'/../wordpress/themes/badaround-child/assets/pin-'.$key.'.svg');
 pin_check(trim($file)===$entry['svg'],'static and inline SVG match '.$key);
 pin_check(strlen($file)<1400,'bounded SVG '.$key);
 pin_check(str_contains($file,'fill="#fff"')&&str_contains($entry['glyph'],'#111'),'white medallion and black pictogram '.$key);
 pin_check(!preg_match('/<script|<image|<foreignObject|https?:\\/\\/(?!www.w3.org)/i',$file),'passive local SVG '.$key);
}
pin_check(badaround_pin_svg('vehicle')===badaround_pin_svg('veicoli'),'wizard vehicle alias');
pin_check(badaround_pin_svg('public_space')===badaround_pin_svg('spazi-pubblici'),'wizard public space alias');
pin_check(badaround_pin_svg('<script>')===badaround_pin_svg('neutral'),'unknown category is neutral, never HTML');
echo "PIN_SYSTEM_PHP_PASS\n";
