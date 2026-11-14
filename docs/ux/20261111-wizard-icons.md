# Icone del wizard — 11 novembre 2026

Aggiornamento esclusivamente UI sulla baseline online approvata.

## Canali social

Cinque icone SVG locali con etichette leggibili: Facebook, Instagram, TikTok, X, Google Business Profile. Elenco informativo associato al campo social esistente. Nessuna nuova selezione per canale, campo, versione di preferenza o chiamata verso piattaforme esterne.

## Titoli delle fasi

Il chip a sinistra del titolo mostra un’icona contestuale: compilazione evento, posizione e tempo, documento/dettagli, immagini, contatti, riepilogo e conferma finale. Il renderer aggiorna la visibilità dei gruppi SVG insieme al titolo esistente, anche nel ritorno ai passaggi precedenti e nel reset dopo l’invio. Le icone sono decorative per gli screen reader: il titolo testuale resta l’etichetta accessibile della fase.

Font, dimensioni dei titoli, timeline, domande, validazione e payload rimangono invariati. Gli stili sono limitati al workspace del wizard.

## Integrazione social

La connessione PostPickr disponibile non restituisce il progetto BadAround. Per proseguire con le bozze sui canali corretti occorre rendere disponibile tale progetto nella connessione. Non sono state create bozze, programmazioni o pubblicazioni su altri account.

## Esiti del rilascio

- Canali social: commit `828046a9dd8b9a7c0e27bf22086d659c0576e499`; [run 42989050749](https://github.com/Ciborio71/BadAround/actions/runs/42989050749) PASS; due file installati con backup e verifica di corrispondenza.
- Icone titoli: commit `7c6509432ce31f0e2bea80ceeacb2176791cf4b9`; [run 42989344328](https://github.com/Ciborio71/BadAround/actions/runs/42989344328) PASS; tre file installati con backup e verifica di corrispondenza.
- Entrambi i rilasci: 29/29 test JavaScript e suite PHP native-report/social-preference PASS; guardia FTPS e verifiche sintattiche PASS.
- [Identity CI 42989344223](https://github.com/Ciborio71/BadAround/actions/runs/42989344223) PASS.
- Letture native autenticate dopo deploy confermano i cinque canali, i sette gruppi SVG e la sincronizzazione del renderer e dello stile nel codice effettivamente online.
- Nessuna modifica a dati, URL, attivazione o autorizzazioni. Nessuna pubblicazione social eseguita.

Il controllo della resa visuale nel browser autorizzato resta nel collaudo finale responsive: i test automatici e la lettura del runtime non equivalgono a una verifica visuale su tutti i dispositivi.
