<?php
require __DIR__.'/nf-ux-02b-v2.php';
$p=v2fixture();$p['location']+=array('neighborhood_label'=>' Quarto Oggiaro ','neighborhood_source'=>'google_neighborhood','neighborhood_confirmed'=>true);
$n=BadAround_Report_Version_Registry::prepare($p);
v2check(!is_wp_error($n)&&$n['location']['neighborhood_label']==='Quarto Oggiaro','confirmed proposal normalized');
$created=v2gold()->submit($p);v2check(v2code($created)==='success','optional neighborhood persists through real golden path');
$stored=json_decode($db->get_var($db->prepare('SELECT content_original FROM test_ba_reports WHERE id=%d',$created['report_id'])),true);
v2check($stored['canonical']['location']['neighborhood_label']==='Quarto Oggiaro','private canonical retains confirmed zone');
foreach(array('neighborhood_label','neighborhood_source','neighborhood_confirmed') as $key){$bad=$p;unset($bad['location'][$key]);v2check(is_wp_error(BadAround_Report_Version_Registry::prepare($bad)),'partial tuple rejected '.$key);}
foreach(array(false,'',array(),str_repeat('é',101),"Zona\nsegreta",'<b>Roma</b>') as $value){$bad=$p;$bad['location']['neighborhood_label']=$value;v2check(is_wp_error(BadAround_Report_Version_Registry::prepare($bad)),'invalid label rejected');}
$bad=$p;$bad['location']['neighborhood_confirmed']=false;v2check(is_wp_error(BadAround_Report_Version_Registry::prepare($bad)),'unconfirmed proposal rejected');
$bad=$p;$bad['location']['neighborhood_source']='poi';v2check(is_wp_error(BadAround_Report_Version_Registry::prepare($bad)),'POI source rejected');
$bad=$p;$bad['schema_version']='badaround-report/v1';v2check(is_wp_error(BadAround_Report_Version_Registry::prepare($bad)),'closed V1 does not accept V2 extension');
echo "NEIGHBORHOOD_CONTRACT_SUITE_PASS\n";
