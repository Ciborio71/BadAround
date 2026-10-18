# SOCIAL-PREF-01 — acquisizione della richiesta social

Autorizzazione: «autorizzo!» del 18 ottobre 2026, dopo la proposta di estendere il contratto per acquisire e conservare la preferenza. Solo staging; nessun post social o modifica al Publication Service.

## Contratto additivo e compatibilità

Il formato `badaround-report/v1` resta compatibile con gli invii certificati: due nuovi percorsi opzionali nel loro insieme, nessuna modifica ai campi esistenti o alle fixture. `reporter.social_visibility` ammette `requested` e `declined`; l'assenza non è una richiesta. Se la scelta è presente, `consents.social_visibility_version` deve essere `social-preference-v1`. La versione identifica il testo e l'ambito immutabili descritti qui. Versioni o valori sconosciuti vengono rifiutati; metadati senza scelta vengono eliminati dal normalizzatore.

Ambito v1: richiesta che la moderazione valuti contenuti pubblici approvati per Facebook, Instagram, X, Google Business Profile e TikTok, secondo i canali effettivamente attivati. Non comprende canali ulteriori, dati riservati, contenuti non approvati o una promessa di pubblicazione. Il campo è una preferenza, non un'autorizzazione esecutiva né una decisione di moderazione. Account e integrazioni non vengono attivati da questa patch.

Testo: «Vuoi dare visibilità a questo evento sui canali social di BadAround?»

- «Sì, desidero che BadAround valuti la condivisione sui propri canali social.»
- «No, preferisco la pubblicazione sul sito BadAround.»

Aiuto: «La tua scelta sarà valutata dalla moderazione. Potranno essere condivisi solo contenuti approvati e destinati alla pubblicazione; i tuoi recapiti e le informazioni riservate resteranno esclusi. Se non rispondi, non richiedi la diffusione social. La richiesta riguarda Facebook, Instagram, X, Google Business Profile (Google Maps) e TikTok, secondo i canali attivati da BadAround.»

## Persistenza e confine pubblico

Scelta e versione restano nel JSON canonico privato di `ba_reports.content_original`; `ba_reports.consented_at` fornisce l'istante UTC del server. Hash e idempotenza esistenti includono i nuovi valori: una ripetizione identica non duplica il report e lo stesso UUID con scelta differente non sovrascrive l'evidenza. Nessuna migrazione DB, nuova tabella o cancellazione di dati storici.

La vista riservata della moderazione mostra preferenza, ambito, versione e data, sotto la capability `ba_view_private_reports`. Gli invii precedenti, senza evidenza, indicano nessuna richiesta. I nuovi campi hanno proiezione `never`; nessun inserimento in meta pubblici, notifiche, job o chiamate social. La conferma obbligatoria e le preferenze di contatto restano distinte.

## Modifica e revoca

Prima dell'invio la scelta può essere modificata; una nuova segnalazione riparte senza scelta. Questa patch non introduce modifica del report già inviato o endpoint di revoca. Il prossimo servizio Social dovrà prevedere una preferenza corrente revocabile e verificarla al momento dell'esecuzione: il solo snapshot storico qui acquisito non basta per autorizzare un post. Omissione, rifiuto, revoca o versione non riconosciuta devono bloccare il futuro consumer. Non è stato introdotto alcun consumer in questo task.

## QA

Test dedicati: sì/no/omesso, indipendenza dalla conferma e dal contatto, versione riconosciuta, tipi/enum errati, rilettura tramite repository reale su DB simulato, timestamp server, retry identico e conflitto su modifica, riepilogo e reset. Browser Chromium/WebKit: veicoli con 112 e richiesta, pseudonimo con rifiuto, altre categorie con omissione; mobile e desktop, tastiera, numerazione e submission 201 simulate. Nessuna segnalazione di prova nel database staging.
