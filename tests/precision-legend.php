<?php
define('ABSPATH',__DIR__);ob_start();include __DIR__.'/../wordpress/themes/badaround-child/template-parts/components/precision-legend.php';$html=ob_get_clean();
if(in_array('--html',$argv,true)){echo $html;exit;}
if(substr_count($html,'<svg')!==2||!str_contains($html,'Punto esatto')||!str_contains($html,'Area approssimata')||preg_match('/<(a|button|input|select)\b/',$html))throw new RuntimeException('Two labelled, passive precision symbols required');
echo "PRECISION_LEGEND_PHP_PASS\n";
