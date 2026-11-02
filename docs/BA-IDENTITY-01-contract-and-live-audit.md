# BA-IDENTITY-01 — Contratto di integrazione passwordless (bozza tecnica)

**Stato:** audit read-only completato; contratto proposto, non implementato. **Data:** 2026-11-02. **Baseline:** produzione badaround.it; non sostituire gli attuali file live con snapshot storici GitHub.

## Decisioni funzionali approvate

- Nome, cognome ed email **obbligatori** per inviare una segnalazione e per commentare. Il telefono è facoltativo.
- Nessun account WordPress obbligatorio per gli utenti della community.
- Modalità pubblica scelta per contributo: anonimo, pseudonimo, solo nome, nome e iniziale del cognome.
- Verifica email mediante codice/link monouso, sessione breve revocabile, accesso a contributi successivi e proposte di modifica.
- Ogni modifica di evento pubblicato, nuovo commento e risposta passa dalla moderazione; i commenti supportano risposte a un livello, ordinati per commento principale più recente.
- Non attribuire automaticamente i vecchi report all'identità basandosi soltanto sulla email.
- Tutti gli eventi attuali sono dati dimostrativi da eliminare in un reset separato **dopo** i test del nuovo flusso.

## Audit della produzione (source read-only)

1. `themes/badaround-child/page-area-personale.php`: accesso con `is_user_logged_in()` + `wp_login_form()`; tab «Le mie segnalazioni» e altre sezioni attualmente con viste segnaposto.
2. `themes/badaround-child/assets/js/account.js`: solo navigazione a tab, non gestisce autenticazione o sessioni della community.
3. `plugins/badaround-core/includes/class-badaround-contribution-service.php`: endpoint `/badaround/v1/contributions`, verifica email per singolo contributo, rate limiting e moderazione; UI contributi su evento pubblicato.
4. `plugins/badaround-core/includes/class-badaround-contribution-repository.php`: usa tabella `ba_contributions`, `email_hash`, scadenza token, `email_verified_at` e stati separati; attuale identificazione pubblica dei contributi diversa dal modello a quattro modalità.
5. `plugins/badaround-core/includes/class-badaround-sentinel-service.php`: verifica email, unsubscribe e protezioni per singola Sentinella.
6. `plugins/badaround-core/includes/class-badaround-transactional-mailer.php`: trasporto email applicativo già presente; riusarlo, senza introdurre credenziali duplicative.
7. `plugins/badaround-core/includes/class-badaround-report-schema.php` e frontend nativo: dati identificativi e preferenze di visualizzazione pubblica già modellati; il nuovo sistema non deve degradare privacy o validazione.

## Architettura proposta

- **Identity service** centralizzato nel Core, con account applicativo distinto da utenti e cookie WordPress amministrativi.
- Record identità privato: identificativo opaco, email normalizzata e verificata, nome/cognome riservati, stato, creazione, ultimo accesso, revoca; niente esposizione pubblica degli identificativi.
- Richiesta accesso con risposte indistinguibili per email esistente/non esistente, throttling per indirizzo/IP/dispositivo e token casuali monouso salvati solo come hash.
- Sessione con cookie Secure, HttpOnly, SameSite, scadenza e rotazione; verifica server-side delle autorizzazioni e protezione CSRF delle operazioni di scrittura.
- Ownership esplicita tra identity_id e nuovo report/contributo; mai inferita automaticamente dall'email, dall'alias pubblico, da un ID evento o da un codice ricevuta.
- Un singolo profilo privato, ma **display pubblico impostato per singola segnalazione/commento**.
- Per le modifiche: richiesta di revisione separata dalla versione pubblicata, audit/diff, approvazione o rifiuto da parte dei moderatori, pubblicazione esclusivamente via Publication Service.
- Per commenti/risposte: conservarne struttura genitore a profondità massima uno, moderazione preventiva, segnalazione abusi; non pubblicare automaticamente dati di contatto e informazioni riservate.
- Per Sentinelle: riusare le autorizzazioni già confermate dal relativo flusso e collegare a identità soltanto mediante verifica; niente auto-associazione da email prima di un controllo del contratto privacy.
- Area personale: non sostituire subito `wp_login_form`; predisporre una sezione community separata e migrare la UX dopo test di regressione.

## Fasi di esecuzione

1. **ID-01A**, read-only baseline e mappatura endpoint, tabelle, stati, consensi e autorizzazioni esistenti (in corso).
2. **ID-01B**, specifica dati/token/sessioni con threat model, minimizzazione privacy e test anti-enumerazione.
3. **ID-01C**, motore passwordless in feature flag, non abilitato al pubblico, con test unitari/integrati.
4. **ID-01D**, adattamento wizard: nome/cognome/email obbligatori, verifica e ownership del report; mantenere moderazione.
5. **ID-01E**, commenti e risposte con quattro modalità pubbliche; riuso verifica per sessioni valide.
6. **ID-01F**, area personale, richieste di modifica, notifiche/Sentinelle e QA mobile/desktop.
7. **ID-01G**, QA completo, verifica backup/rollback, release selettiva; reset dei dati dimostrativi soltanto dopo collaudo.

## Criteri di accettazione

- Nessun accesso ai report altrui conoscendo soltanto la loro email.
- Una sessione verificata non autorizza operazioni su record privi di ownership.
- Un codice scaduto/usato/revocato non funziona; anti-bruteforce e anti-enumerazione.
- Gli utenti WordPress amministrativi non sono confusi con le identità community.
- Campi identificativi completi obbligatori ma mai pubblicati senza esplicita modalità autorizzata e moderazione.
- Report, contributi, media, Smart Address, SEO URL, geoprivacy, Radar e audit non regrediscono.
- Nessuna modifica live sulla base di vecchie copie GitHub; prima patch sui file live, backup, dry-run, verifica post deploy.
