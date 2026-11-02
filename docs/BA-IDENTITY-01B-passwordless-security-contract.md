# BA-IDENTITY-01B — Contratto tecnico di identità passwordless

**Stato:** specifica tecnica approvabile, non implementata. **Data:** 2026-11-02. **Repository:** Ciborio71/BadAround. **Produzione:** sola lettura; nessuna migrazione, modifica schema, attivazione route o deploy.

## 1. Invarianti

1. Nome, cognome, email sono obbligatori per ogni nuova segnalazione/commento; telefono facoltativo. Email verificata prima che un'operazione ottenga un legame di ownership riutilizzabile.
2. Nessun account WordPress richiesto; cookie community distinto dal cookie admin.
3. Nome pubblico scelto **per contributo**: anonymous, pseudonym, first_name, name_initial. Email e dati privati non sono inclusi nelle API pubbliche.
4. La verifica email prova esclusivamente il possesso della casella, non l'identità anagrafica.
5. Il solo indirizzo email non trasferisce proprietà né accesso a report preesistenti; eventi attuali = dati di test.
6. Modifiche e commenti pubblici sono revisioni soggette a moderazione; nessun bypass di Core e Publication Service.

## 2. Modello persistente proposto (non DDL eseguibile)

- `ba_identities`: id interno non pubblico; public_id casuale; email normalizzata, digest HMAC per lookup, email cifrata se necessario; nome e cognome riservati; verified_at; state; created_at; updated_at; revoked_at. Nessuna password.
- `ba_identity_challenges`: id, identity candidato, purpose (login, verify_initial, sensitive_action), token_hash, created_at, expires_at, consumed_at, attempts_count, throttling metadata a retention limitata. Vietato salvare token in chiaro.
- `ba_identity_sessions`: id, identity_id, session_secret_hash, created_at, expires_at, last_seen_at, revoked_at, device label non identificativo opzionale. Cookie opaco sicuro.
- `ba_identity_ownership`: identity_id, resource_kind (report, contribution, comment, sentinel), resource_id, relation (creator, authorized_editor), verified_binding_at, created_at. Chiave univoca e audit immutabile.
- `ba_identity_revision_requests`: id, identity_id, target report/event, base_version, proposed_delta privato, status (pending_review, approved, rejected, cancelled), moderation reference, audit times. Non sovrascrivere mai direttamente il record pubblicato.

Nessuna tabella va creata finché non sono stati verificati schema e installer del Core e la compatibilità con `ba_contributions` e `ba_sentinels`.

## 3. Passwordless protocol

- `POST /badaround/v1/identity/challenges`: email e contesto; risposta indistinguibile per email esistente/non esistente, senza indicare account o risorse collegate; rate limit IP + HMAC(email) + backoff. Non rivelare token nei log.
- Codice OTP **6–8 cifre** generato con CSPRNG, scadenza consigliata **10 min**, monouso, massimo **5 tentativi**; hash con protezione contro brute force offline (HMAC segreto server + storage protetto). Resend limitato e invalidazione del codice precedente.
- `POST /badaround/v1/identity/challenges/verify`: consumo atomico del challenge; emette sessione soltanto a verifica positiva. Token e cookie mai in URL o analytics.
- Cookie `__Host-ba_identity` Secure, HttpOnly, SameSite=Lax, Path=/, nessun Domain; durata indicativa 7 giorni, idle timeout indicativo 24 h (da validare con UX/privacy). Secret di sessione casuale e hash server-side; rotazione alla verifica/accesso sensibile.
- `POST /badaround/v1/identity/logout` revoca sessione. `GET /badaround/v1/identity/me` restituisce il minimo profilo privato solo al titolare; Cache-Control: no-store.
- Per mutazioni autenticare sia sessione sia token CSRF associato; Origin/Sec-Fetch checks aggiuntivi, non sostitutivi. No autenticazione tramite email, codice ricevuta, alias o ID evento.

## 4. Flussi

**Prima segnalazione:** campi identificativi obbligatori → richiedi verifica → associa report appena creato a identity_id verificato nel punto transazionale controllato; invio a moderazione. Non alterare lo stato pubblico.

**Commento iniziale:** nome/cognome/email obbligatori, public_identity_mode indipendente → verifica email o riuso sessione valida → contributo in pending moderation.

**Visita successiva:** sessione valida precompila i dati riservati e le preferenze; ciascun contributo richiede nuova scelta/visualizzazione della modalità pubblica (può avere default non vincolante).

**Modifica segnalazione:** autorizzazione per ownership verificata → delta in revision_requests, con confronto della versione base; moderatore applica tramite flusso previsto, con audit e conservazione della versione precedente.

**Discussioni:** commenti ordinati per genitore più recente; risposte profondità massima 1; contenuti pubblici soltanto moderati, dati riservati attraverso l'attuale `ba_contributions` o integrazione contrattuale separata.

**Sentinelle:** mantenere verify/unsubscribe già attivi; collegare le preferenze all'identità soltanto dopo nuova verifica/consenso e senza import automatico dei record vecchi.

## 5. Threat model / controlli

- Account enumeration: messaggi e comportamento indistinguibili, no introspezione pubblica delle email.
- Brute force OTP: tentativi, scadenza, rate limit, blocco temporaneo e audit privo di PII.
- Session theft/fixation: cookie sicuro, rotazione, revoca; no token in localStorage.
- CSRF: token anti-CSRF e verifica della provenienza per scritture.
- IDOR: controllo ownership server-side per ogni risorsa, non per sola email.
- XSS/pseudonym impersonation: escaping coerente e moderazione, alias non certificato come identità.
- Abuse/privacy: segreti crittografici fuori dal repository, TTL/retention definiti, logging minimo, niente PII nelle metriche Radar.
- Race condition: consumazione atomica OTP; idempotenza delle richieste; double-submit e collisioni.
- Mail failure: nessuna sessione concessa se l'email non è stata verificata; retry limitato; preservare transazioni e consensi.

## 6. Integrazione con baseline live

Componenti confermati in produzione, sola lettura:
- `class-badaround-transactional-mailer.php`: trasporto email da riutilizzare.
- `class-badaround-contribution-service.php` e repository: verifica email e rate limiting per contributi; non sostituire in-place.
- `class-badaround-sentinel-service.php`: verify/unsubscribe già presenti.
- `page-area-personale.php`: usa login WordPress; non sostituire prima di un flusso community validato.
- `class-badaround-report-schema.php`: identificativi privati e scelta nome pubblico già disponibili.

## 7. Gate e verifiche ID-01C

1. Test unitari OTP (TTL, riuso, resend, limiti tentativi), sessione e anti-CSRF.
2. Test proprietà: identità A non accede a risorsa B; conoscenza email di B non permette takeover.
3. Test sessioni revocate/scadute, logout, browser multipli, cache e mancata consegna.
4. Test nome pubblico anonimo/pseudonimo/solo nome/nome+iniziale; nessuna fuga di PII su API e pagina.
5. Test compatibilità con invio guest attuale, moderazione, Publication Service, contributi, Sentinelle, Smart Address e Radar.
6. Prima dell'abilitazione: audit schema/installer, feature flag default OFF, smoke e rollback. Nessun deploy automatico da vecchio snapshot GitHub.

**Gate finale:** lo sviluppo ID-01C potrà iniziare come codice dormiente in branch isolato dopo verifica dell'installer e definizione delle migration additive; nessuna attivazione sul sito online in questa fase.
