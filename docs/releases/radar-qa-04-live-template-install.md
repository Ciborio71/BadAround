# RADAR-QA-04 — Installazione selettiva dell'anteprima

Riferimento: feature/radar-public-06-period-renderer -> qa/radar-public-07-integration-guard.

## Stato live verificato
- Il template attivo `wp-content/themes/badaround-child/page-radar-demo.php` contiene `<div id="rad-reports" ...></div>` seguito **sulla stessa riga** dalla nota `ba-radar-final-note`.
- Il bootstrap Core e i nove moduli Radar di RC08 sono già stati installati e la schermata amministrativa Radar QA a 7/30 giorni è stata testata dall'utente.
- Le PR #67/#68 richiedono **due ulteriori classi**, attualmente non presenti sul server; non caricare tutto il tema dal repository.
- Il controllo CI non ha un workflow run verificato per #67/#68/#69: non dichiarare PASS finché non ci sono test eseguiti.

## Sequenza prima del primo test visivo
1. Preparare e salvare una copia del **template Radar live**, non uno snapshot storico.
2. Verificare che i due nuovi file NON esistano già: `class-badaround-radar-window-counts.php`, `class-badaround-radar-window-renderer.php`.
3. Prelevare dal ramo `qa/radar-public-07-integration-guard` soltanto i due file nuovi da `wordpress/plugins/badaround-core/includes/`. Installarli in `httpdocs/wp-content/plugins/badaround-core/includes/` con Plesk. Bloccare qualsiasi overwrite.
4. Inserire **solo** il blocco PHP `RADAR-PUBLIC-03` aggiornato in `page-radar-demo.php` subito dopo il `div#rad-reports`, senza toccare la nota sulla stessa riga, il resto del template, Javascript o CSS. Mai sostituire `page-radar-demo.php` con il file GitHub.
5. La funzionalità resta OFF per il pubblico. Per il test solo admin aggiungere `define('BADAROUND_RADAR_PUBLIC_COUNTS_PREVIEW_ENABLED',true);` in `wp-config.php`, **prima** di `require_once ABSPATH . 'wp-settings.php';` (errore già verificato in RC08).
6. Verificare con admin Radar 30gg: totali archivio 22, datati 5, senza data 17, periodo 4 nel dataset osservato (valori soggetti a nuovi eventi). Verificare anonimo non vede la sezione. Nessuna notifica, endpoint pubblico o marker aggiuntivo.
7. In caso di errore o sovrapposizione visiva ripristinare il template live salvato; rimuovere o disabilitare la costante di preview. Non intervenire su Core o database.

Non fare deploy automatico tramite GitHub, né includere file estranei al perimetro.
