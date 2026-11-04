# BA-REPORT-IDENTITY-01 — Contratto vincolante: segnalazione senza account

Data: 04/11/2026. Stato: **approvato come decisione di prodotto; integrazione OTP sull'intake non ancora rilasciata**.

## Regola UX non negoziabile
**L'invio di una segnalazione non richiede un account Identity o un login WordPress.** Nome, cognome ed email restano obbligatori e riservati. L'utente può scegliere come apparire pubblicamente secondo il contratto di moderazione (anonimo/pseudonimo/modalità previste). Per la nuova esperienza l'indirizzo email viene confermato con OTP **specifico per l'invio della segnalazione**, senza creare automaticamente un account.

| Azione | Account | Prova richiesta |
|---|---|---|
| Consultazione mappe, Radar, dettagli | No | Nessuna |
| Inviare segnalazione | **No** | Nome/cognome/email e prova OTP dello stesso invio; moderazione prima della pubblicazione |
| Inserire commenti e risposte | Sì | Identità attiva + sessione/CSRF + moderazione |
| Consultare Area personale | Sì | Identità attiva + sessione |
| Modificare propria segnalazione | Sì / accesso verificato | **Prova esplicita della proprietà della specifica segnalazione**, non mera uguaglianza di email o nome |
| Moderare | Sì, ruolo autorizzato | Identità verificata, binding attendibile WP e capability specifica |

## Contratto di integrazione con il flusso attuale
- La rotta live `POST /wp-json/badaround/v1/reports` è attualmente anonima intenzionalmente (codice WordPress Core v0.18.0). **Non aggiungere un controllo login** a permission_check, intake wizard o Publication Service.
- La regola OTP è **futura** e va resa effettiva solo dopo che sono operativi un challenge specifico per `report_submission`, consegna email, prova server-side single-use, binding all'ID di invio canonico e percorso di recupero.
- **I codici OTP di registrazione e login non valgono per un report**. Non usare una sessione, un codice del browser, un `email_lookup` o un header autocertificato come prova dell'invio.
- Il server deve associare `challenge_id`, email HMAC, ID invio, scadenza, esito e contatore tentativi in modo atomico. Qualsiasi prova usata deve essere consumata una sola volta, senza pubblicazione automatica.
- Il codice OTP va richiesto dopo la compilazione dei dati necessari, con interfaccia di verifica accessibile. Se l'utente non verifica, la segnalazione non può ottenere lo stato di invio verificato nel nuovo contratto.
- La responsabilità del workflow rimane: **da moderare → approvato → pubblicato**, nessun bypass tramite OTP.
- L'eventuale diritto di gestire una segnalazione inviata senza account è recuperabile solo con una **procedura separata di verifica/autorizzazione** e audit. Email corrispondente ≠ proprietà acquisita.
- I report preesistenti (attualmente di test) non vengono retroattivamente associati a identità. Il reset dei test è un task di rilascio separato e successivo a backup/QA.
- Privacy: l'informativa attualmente live è ancora marcata `BOZZA PRE-PUBBLICAZIONE`; non inviare OTP reali né trattare nuovi profili Identity prima di versione validata, retention e segreto sicuro.

## Regola di sicurezza / release
La prima attivazione OTP dell'intake richiede uno switch dedicato **OFF per default**, una suite browser e DB che copra corretta email, scadenza, replay, doppio invio, codice di altri scopi, rate-limit, errore provider, privacy e recupero. Fino al go/no-go esplicito il percorso di segnalazione approvato in produzione deve restare invariato.

## Done
- [x] Decisione di prodotto formalizzata.
- [x] Contract e test regressione separazione Identity/Report.
- [ ] Implementazione OTP report submission con storage/prova specifica.
- [ ] Revisione privacy, root key e SMTP, QA end-to-end e rilascio.
