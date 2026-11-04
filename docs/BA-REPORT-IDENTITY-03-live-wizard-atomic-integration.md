# BA-REPORT-IDENTITY-03 — Integrazione OTP atomica nel wizard reale

Data: 2026-11-04. Stato: **collaudo transazionale PASS; integrazione pubblica NON attivata**.

## Ricognizione produzione (read-only, 04/11)
Il wizard live è nel tema child, non nel ramo Identity di GitHub:
- `themes/badaround-child/assets/js/native-report/model.js`, righe 20–23: `submissionId = uuid(crypto)` su reset; righe 115–140: `payload()` include `schema_version`, `submission_id` e `reporter.*` valorizzati.
- `themes/badaround-child/inc/native-report.php`, righe 44–50: campi `reporter.first_name`, `reporter.last_name`, `reporter.email`, nome pubblico/pseudonimo e privacy.
- `themes/badaround-child/assets/js/native-report/wizard.js`, righe 610–657: submit viene congelato in `model.snapshot` e inviato tramite `ns.send(config,model.snapshot,...)` con idempotenza/retry e capability media. **Non alterare questo meccanismo con patch premature**.
- `plugins/badaround-core/includes/class-badaround-native-report-rest-controller.php`, righe 47–68: `permission_check` consente l'intake anonimo con header di integrità, senza login obbligatorio. La rotta è `POST /badaround/v1/reports`.

## Risultato del task
- Creato `report-otp-atomic-intake.php`, un coordinatore **isolato** che usa un'unica connessione PDO per il claim OTP e la persistenza di report/evento canonico.
- Creato `test-report-otp-atomic-intake-mysql.php`: test su tabelle esclusivamente CI in database `ba_identity_ci`; dimostra che una proof non verificata non crea report, un errore canonico rollbacka la proof, il retry può riuscire, il replay non crea duplicati, l'email diversa non può usare il codice.
- CI: PHP+MySQL run `41809116045` PASS; MariaDB 10.3 run `41809115992` PASS; gate perimetro `41809116098` PASS.
- Nessun deploy di questi nuovi sorgenti sul WordPress live, nessuna tabella aggiunta e nessun OTP reale inviato.

## Piano d'integrazione finale (ordine vincolante)
1. **Privacy**: validare l'informativa pubblica prima di nuova raccolta di dati OTP. Oggi è ancora bozza pre-pubblicazione.
2. **Chiavi e trasporto**: root secret isolato e mailer TurboSMTP, quota server-side, gateway anti-abuso, IP/proxy accurati e test di recapito autorizzati.
3. **Persistenza**: validare il nuovo schema OTP report in produzione solo con backup, rollback e approvazione separata. Il prototipo `report-otp-store.php` richiede transazione condivisa con la persistenza canonica.
4. **Compatibilità DB**: il Core attuale usa WP `$wpdb`; dimostrare come coordinare PDO con il *medesimo* connection/transaction/lock della persistenza report esistente oppure implementare un durable outbox con CAS e reconciliation. **Non si deve solo chiamare `claim_once` prima di `ns.send`, perché un errore lascerebbe OTP consumato senza report**.
5. **UI**: aggiungere uno step di verifica email dopo la raccolta di `reporter.first_name`, `reporter.last_name`, `reporter.email` e prima di invio canonico. Chiamare endpoint dedicati gated per `report_submission`. Non creare account, non caricare OTP nei log né localStorage.
6. **Congelamento e retry**: associare la prova al `model.submissionId` già generato; in caso di correzione email/nome o nuova segnalazione, invalidare la prova precedente. Conservare i media descriptor e il comportamento frozen snapshot/duplicate inalterati.
7. **Backend**: validare email+submission ID dal payload canonico e dalla prova server-side; invio alla moderazione attraverso i servizi esistenti senza bypass. Non fidarsi di flag JSON client `email_verified=true`.
8. **QA**: test desktop/mobile, email corretta/errata, codice scaduto, quota, errore SMTP, replay, doppio click, stesso submission ID con payload differente, errori media, reboot browser, cancellazione e accessibilità.
9. **Go/no-go e rollback**: attivazione con flag server-side OFF per default; rilascio su versione tema/Core **identica alla baseline live**, senza merge del vecchio Core della branch Identity.

## Blocco esplicito
Una demo grafica o un test isolato non attestano che la mail venga realmente inviata, né che la creazione del report in WordPress sia transazionale con OTP. Il task rimane **non rilasciabile agli utenti** finché questi gate non sono soddisfatti.
