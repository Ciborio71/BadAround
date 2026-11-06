# Identity — accesso differito e navbar, contratto approvato il 6 novembre 2026

## Flusso richiesto
- Segnala un evento apre il wizard senza registrazione preliminare e senza la dashboard personale sopra al modulo.
- Nome, cognome, email, visibilità anonimo/pseudonimo e consensi restano nel wizard.
- Conferma e invia verifica la sessione e la corrispondenza con l'email della segnalazione. Una sessione verificata con la stessa email procede senza nuovo codice.
- Altrimenti viene richiesto un codice numerico di sei cifre. La conferma di invio del codice compare soltanto dopo l'accettazione del trasporto email.
- La stessa verifica completa il primo accesso e mantiene la sessione: niente secondo codice di login dopo la registrazione.
- Dalla navbar, Accedi apre l'area di accesso; Il mio profilo indica la sessione autenticata. Al primo accesso da navbar si raccolgono nome e cognome dopo la prova del possesso dell'email, senza inviare un secondo codice. I dati già verificati di un account esistente non vengono sovrascritti.
- La dashboard resta nell'area personale. Nel QA si usa l'URL riservato già esistente; il rilascio non apre una nuova pagina pubblica né modifica l'allowlist.

## Sicurezza e preservazione
- CSPRNG numerico, inclusi zeri iniziali; TTL 600 secondi, cinque tentativi e quote persistenti per email/IP. Reinvio UI dopo almeno un minuto; le quote server rimangono obbligatorie.
- Nuovo dominio crittografico access distinto da enrollment/login/verification: il vecchio endpoint di registrazione non può consumare un codice del nuovo flusso.
- Un codice corretto può richiedere i nomi mancanti, ma la sessione viene emessa solo con profilo completo e consumo atomico del codice. Identità, consumo OTP, completamento e sessione condividono la transazione.
- Sessioni, chiavi, cifratura e cookie Secure/HttpOnly/SameSite precedenti conservati. Fallimento del cookie revoca la sessione appena creata.
- Nessun token, OTP o profilo salvato in localStorage/sessionStorage. Nessuna risposta contiene l'identificativo privato o il bearer della sessione.
- Backend intake verifica che l'email canonica corrisponda alla sessione verificata prima di invocare il percorso nativo. Proprietà mai ricostruita confrontando email di vecchi report.
- Wizard e media vengono congelati solo dopo l'autenticazione; annullamento ed email da correggere preservano i dati. Dopo un invio incerto il retry conserva UUID e snapshot e può riautenticare la stessa email.
- I nuovi codici sono a sei cifre. I verificatori precedenti accettano anche gli otto caratteri dei challenge già emessi, che scadono entro il TTL previsto; il nuovo accesso accetta solo sei cifre.
- Nessun cambio di schema, reset, apertura pubblica o modifica delle protezioni QA in questo rilascio.

## Verifiche previste
Suite del servizio su SQLite e MySQL: nuovi utenti, utenti esistenti, nomi obbligatori, email diversa, scadenza, cinque tentativi, riuso, quota, fallimento email/cookie, revoca e separazione dei purpose.
DOM: accesso unico, completamento nomi senza secondo codice, tastiera numerica, incolla/autocomplete, annullamento, retry e cooldown; dati e snapshot del wizard preservati.
Regressioni: suite Identity, wizard/media, pubblicazione/GEO/SEO e driver di deploy con drift-stop e rollback.
Il collaudo browser effettivo sulla connessione QA autorizzata resta necessario; i test automatici non lo sostituiscono.
