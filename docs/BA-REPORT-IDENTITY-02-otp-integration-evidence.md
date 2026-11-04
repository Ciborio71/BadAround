# BA-REPORT-IDENTITY-02 — OTP report specifico, contratti per integrazione

Data: 04/11/2026. Stato: **modulo PHP isolato + test PASS; nessuna attivazione sul sito**.

## Risultato
Il servizio `report-otp-service.php` prepara un flusso per l'invio di una segnalazione **senza account**:
1. raccolta di nome, cognome, email obbligatori e identificativo stabile di invio;
2. normalizzazione e HMAC dell'email con chiave dedicata allo scopo `report_submission`;
3. quota persistente obbligatoria per email/IP, da implementare con adapter server-side;
4. codice casuale di 8 cifre, TTL 10 minuti, digest (non OTP in chiaro) nel repository atomico futuro;
5. invio email mediante trasporto iniettato e invalidazione del challenge se il provider rifiuta;
6. verifica via repository atomico: massimo cinque tentativi, una verifica valida, no replay;
7. consumo della prova legato esattamente a email e identificativo dell'invio; un solo claim;
8. **separazione totale da login e registrazione**; nessuna creazione automatica di account o ownership.

## Integrazione necessaria, non ancora effettuata
- Il Core live `BadAround_Native_Report_REST_Controller` riceve oggi `/badaround/v1/reports` anonimamente; lasciare invariato fino a rilascio.
- L'adapter persistente `BadAround_Report_Otp_Proof_Store` non esiste in produzione: predisporre schema specifico con vincoli di purpose/email/submission, row-lock, numero tentativi e claimed_at. `verify_once` e `claim_once` devono essere **atomici** contro concorrenza e replay.
- La verifica finale deve far parte di una **transazione consistente con il report canonico**; il claim anticipato rischia di consumare OTP senza creare il report. Se la transazione non è condivisibile, implementare un outbox con stati duraturi e idempotenza. Non trattare un semplice array JSON del browser come ricevuta verificata.
- Prima di inviare OTP reali: informativa privacy sostitutiva approvata, segreto root server-side, provider email autorizzato, server-proxy e rate limiter verificati.
- Integrare il passo OTP nella UI preesistente solo dopo aver verificato i campi esatti del wizard live e il payload accettato dal Publication Service. Non copiare dalla branch Identity sorgenti Core potenzialmente obsoleti.
- Verificare caso media F1.7, retry, bozza sospesa, duplice submission, scadenza, outage SMTP e accessibilità mobile; mantenere una via di rollback.
- Nessuna ownership automatica per i report preesistenti basata sull'email.

## Evidenza di test
- `test-report-otp-service.php`: account-free, nessun codice/email in chiaro nello store, codice errato, replay, email errata, submission diversa, scadenza, provider rifiutato, quota negata.
- `test-report-submission-verification-policy.php`: rifiuta proof con scopo login/enrollment, expiry, duplicate o dati mancanti.
- GitHub Actions PHP/MySQL `41807631093` PASS, MariaDB 10.3 `41807631171` PASS, safety gate `41807631208` PASS.
- Non sono stati inviati OTP reali, creati record report nuovi, né modificate pagine o route WordPress.

## Go/no-go
**NO-GO per l'attivazione**. La regola di prodotto è approvata; è completata la fondazione del servizio, ma non le integrazioni persistenti, browser e operative.
