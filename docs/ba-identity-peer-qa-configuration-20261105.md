# BadAround Identity — configurazione QA per il peer del tester

Stato: rilascio della configurazione privata completato; il primo collaudo dal browser del tester deve ancora avvenire. Nessun OTP è stato inviato da questa operazione.

## Configurazione installata

L’IP comunicato dall’utente è registrato nell’opzione privata `badaround_identity_qa_ip_allowlist`, con un solo elemento. L’indirizzo personale non è incluso nei sorgenti pubblici.

È stata predisposta la pagina WordPress privata **389**, slug `identity-qa`, contenente il portale Identity. Richiede l’accesso WordPress per la consultazione della pagina privata. L’informativa QA rimane la pagina privata **388** con la versione adottata e il relativo hash.

La configurazione PHP riservata è conservata fuori dalla webroot, con permessi 0600. Un’inclusione controllata prima di `wp-settings.php` conserva il resto di wp-config e i permessi originali. Il rilascio ha verificato il supporto della sostituzione atomica e preservato una copia privata dell’originale. La chiave dedicata è generata senza registrarla nei log o negli artifact; un’eventuale chiave già definita viene preservata.

Il file legge esclusivamente le opzioni amministrative private, verifica una singola casella e un singolo IP e confronta `REMOTE_ADDR`. Per le altre connessioni non definisce le costanti di attivazione QA. Non considera attendibili gli header inoltrati. L’informativa pubblica non viene approvata né modificata da questa configurazione.

Prima della configurazione sono risultati vuoti identità, profili in attesa, sessioni e ownership. Non sono state cancellate righe o eseguite migrazioni.

## Correzioni di integrazione

L’endpoint privato delle segnalazioni usa ora la stessa versione QA dell’informativa adottata, mantenendo distinto il controllo pubblico.

Le chiamate community hanno una sessione e una protezione CSRF proprie. Un filtro permette di superare esclusivamente l’errore di manutenzione 503 per le route QA elencate esattamente, con metodo POST, IP autorizzato e informativa QA coerente. Errori di autorizzazione, route diverse, altri metodi, altri peer o un documento alterato mantengono il blocco. Non viene richiesto o esposto un nonce amministrativo nel portale community; il tema e il gate generale di manutenzione non sono modificati.

## Verifiche

- 82 script PHP locali: PASS, compresi i nuovi test del template con database reale e processi separati.
- 12 test Python della configurazione: PASS, inclusi mantenimento delle impostazioni esistenti, sostituzione rifiutata e ripristino atomico dopo errore.
- CI PHP e MySQL: PASS.
- Confronto degli 85 moduli prima e dopo la modifica dei due file: PASS.
- Installazione della configurazione privata e verifica della copia di ripristino: PASS.
- Site info amministrativo dopo il rilascio: funzionante, WordPress 7.1.4 e PHP 8.3.36. Il database dichiara MariaDB 10.3.39.
- `POST /badaround/v1/identity/session` dalla connessione amministrativa esterna al peer autorizzato: `rest_no_route` 404, come previsto.

Commit applicativo `6074ee43185eb6a61ef7352fe01ee0d49924aeb4`. [CI e rilascio 42126009350](https://github.com/Ciborio71/BadAround/actions/runs/42126009350).

## Collaudo da eseguire

Il tester deve aprire `https://badaround.it/identity-qa/` dalla connessione autorizzata, dopo l’accesso WordPress. Deve verificare il modulo, leggere l’informativa, richiedere il codice di registrazione e inserirlo direttamente nel sito. Dopo la registrazione il portale richiede un distinto codice di accesso.

La consegna delle email, l’effettiva corrispondenza del peer visto dal server, il cookie nel browser e i successivi flussi di segnalazione, modifica, commento e moderazione non sono certificati dai soli test automatici. L’apertura generale al pubblico e le verifiche operative dell’informativa rimangono fuori da questa attivazione privata.
