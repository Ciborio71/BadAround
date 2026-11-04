# BadAround — proposta di aggiornamento informativa privacy per Identity & Community

**Bozza di lavoro per revisione legale/editoriale. Non pubblicare senza verifica del titolare/consulente privacy.**
Preparazione: 4 novembre 2026. Fonte interna: funzionalità effettivamente sviluppate, schema Identity e pagina WordPress `/privacy-policy/` (#3).

> **ATTENZIONE:** La pagina Privacy Policy attualmente pubblicata è marcata «BOZZA PRE-PUBBLICAZIONE». Questo documento non ne costituisce approvazione né sostituzione automatica. Le parentesi **[DA CONFERMARE]** sono blocchi espliciti al go-live.

## 1. Titolare, contatti e ambito

Titolare: **Max Advisor S.r.l.s.** [DA CONFERMARE denominazione sociale, indirizzo, P.IVA, recapiti e ruoli], Via Vincenzo Arangio Ruiz 8, 00165 Roma (RM). Contatto privacy: **privacy@badaround.it** [VERIFICARE operatività]. Sito: https://badaround.it.

L'informativa si applica alla consultazione, alle segnalazioni del territorio, alla moderazione, alla registrazione degli utenti, all'Area personale, ai commenti, alle richieste di modifica e, dove disponibile, alle notifiche Sentinelle.

## 2. Categorie di dati

- **Identità riservata:** nome, cognome e indirizzo email obbligatori per registrazione e operazioni autenticate; eventuali attributi della richiesta di assistenza all'account.
- **Dati di autenticazione:** codice OTP temporaneo non conservato in chiaro; hash/HMAC dei riferimenti email; sessione basata su token segreto conservato dal browser come cookie sicuro, mentre il database memorizza il relativo digest; timestamp di verifica, revoca e scadenza.
- **Dati Community:** testo di commenti, risposte, richieste di aggiornamento e moderazione; evento associato; data e stato del controllo; identificatore tecnico interno dell'autore e del moderatore. I contenuti in attesa di moderazione rimangono riservati.
- **Dati territoriali:** posizione e indirizzi comunicati dall'utente; la loro rappresentazione pubblica (punto esatto/area approssimata) dipende dal contratto di pubblicazione e dalla moderazione; civici e coordinate precise private non devono essere rivelati automaticamente.
- **Media e segnalazioni:** immagini originali e versioni pubbliche separate; eventuali targhe e altri identificativi riservati; log di workflow e audit.
- **Dati tecnici e sicurezza:** indirizzo IP derivato dalla connessione server, bucket antiabuso derivati crittograficamente, date dei tentativi OTP, messaggi di errore tecnici senza PII ove possibile.
- **Sentinelle:** email, territorio/categorie scelti e stato degli avvisi [VERIFICARE dati effettivi].

## 3. Finalità e basi giuridiche

**Da validare per singola finalità.** Non presentare la semplice presa visione dell'informativa come consenso generale al trattamento. La casella di presa visione serve a documentare la comunicazione dell'informativa; l'eventuale consenso per attività facoltative deve essere separato, specifico e revocabile.

Matrice da completare con il consulente:

| Finalità | Ipotesi da validare | Condizione |
|---|---|---|
| Apertura account, login OTP e gestione sessione | Esecuzione del servizio richiesto | [CONFERMARE base giuridica] |
| Invio, verifica e moderazione segnalazioni/commenti | Erogazione del servizio e controllo dei contenuti | [CONFERMARE] |
| Difesa da spam/abusi, rate limit, audit | Sicurezza e tutela del servizio | [CONFERMARE test del bilanciamento se necessario] |
| Comunicazioni tecniche sul proprio account | Funzionamento richiesto del servizio | [CONFERMARE] |
| Notifiche Sentinelle scelte dall'utente | Iscrizione facoltativa con disiscrizione | [CONFERMARE meccanismo e base] |
| Marketing o ulteriori finalità eventuali | Nessuna attivazione senza disciplina separata | [CONFERMARE se previste] |

## 4. Come funziona la visibilità pubblica

La pubblicazione non avviene automaticamente. Un commento o una richiesta di modifica viene conservato privatamente in attesa di moderazione. L'utente può scegliere modalità di visualizzazione consentite (anonimo, pseudonimo o nome parziale) **solo quando la relativa funzionalità è stata effettivamente collaudata e attivata**. La scelta di visibilità non elimina l'obbligo di conferire i dati identificativi richiesti privatamente.

Una modifica richiesta dal segnalatore non cambia direttamente il contenuto già pubblicato: passa attraverso autorizzazione sul diritto di modifica, verifica e processo di moderazione.

## 5. Conservazione e cancellazione — BLOCCO OBBLIGATORIO

**[DA APPROVARE: periodi precisi e calendario tecnico di cancellazione]**

Definire separatamente e motivare:
1. OTP non utilizzati, scaduti e già consumati;
2. registrazioni in sospeso e account non verificati;
3. account verificati, richiesta di cancellazione e cronologia essenziale;
4. token/digest di sessione e revoche;
5. rate-limit e bucket antiabuso;
6. commenti in attesa, pubblicati, rifiutati e relativi audit;
7. revisioni di segnalazione e decisioni di moderazione;
8. segnalazioni riservate e media originali.

La regola esistente di **12 mesi** per report rifiutati/non pubblicati non estende automaticamente lo stesso periodo a tutte le categorie Identity.

Definire e collaudare job di pulizia, esportazione e cancellazione, eccezioni documentate per esigenze legali e accesso ai backup.

## 6. Fornitori, responsabili, trasferimenti

**[DA VERIFICARE contratti e configurazioni reali]** Descrivere gli effettivi fornitori di hosting/cloud, CDN/sicurezza, servizi cartografici e geolocalizzazione (Google Places/Maps, se effettivamente utilizzati), recapito email transazionale (TurboSMTP), eventuali servizi AI e relativi ruoli, luoghi di trattamento, trasferimenti internazionali e garanzie applicabili. Non inferire un ruolo GDPR dalla sola presenza di una partnership o un'API.

## 7. Diritti dell'interessato

Descrivere canali operativi per accesso, rettifica, esportazione, cancellazione, limitazione, opposizione e reclamo all'autorità competente, nella misura applicabile alla relativa base giuridica. Specificare la verifica dell'identità e i tempi di risposta [CONFERMARE], nonché le limitazioni giustificate per sicurezza, contenuti di terzi e conservazione legalmente necessaria.

## 8. Cookie e sicurezza

Il cookie di sessione Identity previsto è `__Host-ba_identity`, con `Secure`, `HttpOnly`, `SameSite=Lax`, `Path=/` e nessun `Domain`. La sessione ha limiti di durata assoluta e inattività; i token non devono finire in log, URL o archivi browser JavaScript.

**Verificare** che Cookie Policy e configurazioni CDN/caching riflettano il comportamento effettivo dopo la futura attivazione.

## 9. Minori, segnalazioni di terzi e uso responsabile

[DA CONFERMARE: età minima/consenso genitoriale e politica per segnalazioni relative a minori o soggetti terzi]. Vietare la pubblicazione impropria di dati identificativi, targhe complete, numeri civici privati o contenuti che possano esporre persone a rischio; rinviare alle Regole di pubblicazione approvate.

## 10. Versionamento e approvazione

La versione tecnica corrente `privacy-20261024T162603Z` è associata alla vecchia pagina bozza e **non deve essere approvata**. Dopo la revisione:
1. il titolare approva la nuova informativa;
2. il testo definitivo viene pubblicato dalla funzione editoriale autorizzata;
3. si verifica data/versione/immutabilità della pagina;
4. si modifica la regola di versionamento nel codice tramite PR collaudata;
5. si configura il flag `BADAROUND_IDENTITY_PRIVACY_APPROVED_VERSION` **soltanto** nella configurazione protetta;
6. si avviano collaudi privati autorizzati senza esporre i servizi al pubblico;
7. il go-live si autorizza con decisione separata, registrata e reversibile.

**Esito del documento:** proposta pronta per revisione. **Non idonea alla pubblicazione automatica e non autorizza il trattamento di dati reali nella nuova Identity.**
