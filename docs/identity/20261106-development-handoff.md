# Identity — sviluppo e consegna al collaudo

Questa consegna aggiorna il precedente stato del 6 novembre: sono stati implementati i componenti amministrativi e la proiezione delle proposte che risultavano ancora aperti. Il perimetro rimane il QA riservato su badaround.it. Il collaudo browser e l'apertura pubblica sono fasi successive.

## Funzioni sviluppate

- Registrazione senza password, verifica email, accesso, logout e sessioni con cookie protetto.
- Invio dal wizard con sessione Identity e associazione persistente del creatore; il primo invio reale #85 / evento #392 è già stato verificato separatamente.
- Area personale con le proprie segnalazioni, stato di moderazione e proposte di modifica della descrizione, anche per gli invii ancora in moderazione.
- Commenti anonimi o con pseudonimo esplicito e risposte a un commento approvato dello stesso evento. Un solo livello di risposta; nessuna inferenza del nome pubblico dal profilo privato.
- Schermata amministrativa **Proposte Identity**, con testo pubblico revisionabile, nota di moderazione, approvazione e rifiuto.
- Associazione esplicita del proprio account WordPress alla propria sessione Identity verificata. Non vengono associati account confrontando le email.
- Commenti visibili soltanto dopo approvazione. La vista mostra al massimo gli ultimi 50 commenti approvati dell'evento; tutti gli originali rimangono nel registro privato cifrato.
- Applicazione delle modifiche attraverso il Publication Service esistente: controllo versione, proprietà ancora valida, permessi, validazione preventiva del testo pubblico, audit e decisione nella stessa transazione. L'originale di intake, gli slug e i dati GEO non vengono riscritti.
- Una correzione approvata su un invio ancora in moderazione non pubblica l'evento e non cambia il suo stato di moderazione.

## Verifiche automatiche

- 87 script PHP della suite e delle regressioni di pubblicazione/GEO, su database effimero locale.
- 41 test JavaScript di wizard/media/trasporto Identity.
- 2 test DOM per area personale, invio modifica, pseudonimo, risposta, doppio submit e rendering sicuro dei titoli/link.
- 30 controlli del nuovo percorso amministrativo, eseguiti su SQLite e anche nel percorso MySQL della CI: associazione amministrativa, permessi, nonce, privacy, audit, rollback, risposta appartenente all'evento, proprietà revocata, versione obsoleta e separazione fra testo originale e testo approvato.
- 3 test del nuovo driver di rilascio: drift prima dell'upload, rollback dopo errore a metà deploy e verifica dell'intero candidato. Restano attive le suite precedenti di release/configurazione.
- La CI remota è il gate obbligatorio del deploy. L'esito locale non sostituisce il successivo esito CI e la verifica remota dei file.

## Percorso per il collaudo dell'utente

1. Mantieni la connessione QA autorizzata. Nello stesso browser accedi a WordPress come amministratore e a [Collaudo Identity](https://badaround.it/identity-qa/) con l'account già verificato.
2. Apri [Proposte Identity](https://badaround.it/wp-admin/edit.php?post_type=ba_evento&page=ba-identity-review). Se richiesto, premi **Associa i miei due accessi verificati**: questa operazione collega soltanto le due sessioni attualmente autenticate, senza inserire identificativi o credenziali nella chat.
3. In **Le mie segnalazioni** verifica l'evento #392. Proponi una descrizione aggiornata; controlla l'arrivo nel backend. Prova il rifiuto con motivazione e una nuova proposta approvata. L'evento deve rimanere in moderazione.
4. Per i commenti usa un evento di prova pubblicato con il normale flusso BadAround di moderazione e pubblicazione. Invia un commento anonimo e uno con pseudonimo. Prima dell'approvazione non devono apparire nella vista pubblicabile.
5. Approva il testo nel backend e ricarica la pagina evento. Prova **Rispondi** su un commento approvato; la risposta deve attraversare a sua volta la moderazione. Una proposta su una segnalazione altrui deve essere negata.
6. Prova una modifica a un evento pubblicato. Se il contenuto è stato modificato dopo la proposta, l'approvazione deve fallire senza sovrascriverlo. L'approvazione valida deve preservare URL, GEO e stato pubblico.
7. Invia un nuovo report sintetico con immagine e verifica associazione, moderazione, originali privati e assenza di duplicati dopo un retry dello stesso invio.
8. Esegui logout: area personale nascosta e nuove operazioni protette rifiutate. Completa la verifica visiva e responsive su Home, Mappe, Radar, pagina evento e wizard.

## Limiti e gate di apertura

- Lo sviluppo resta disponibile soltanto nel perimetro QA autorizzato; nessuna apertura pubblica viene dichiarata in questa consegna.
- Il browser automatizzato non è stato usato per certificare questa versione: il collaudo browser è stato rinviato esplicitamente all'utente.
- La policy adottata resta quella per il collaudo. Prima dell'apertura al pubblico occorre l'informativa definitiva con la revisione già prevista dal titolare.
- Nessuna cancellazione o reset dei dati reali, nessuna nuova tabella e nessuna attivazione pubblica sono richiesti dal deploy di sviluppo.

## Esito del rilascio di sviluppo

- Codice: `d2d451376f495d2e770fc7476f24674f491d15c3`.
- [CI e deploy 42143621658](https://github.com/Ciborio71/BadAround/actions/runs/42143621658): PASS, inclusi i job PHP, MySQL e deploy protetto.
- Confrontata la baseline di 85 moduli, installato il candidato con 86 moduli e l'estensione del Publication Service. Entrypoint Core, configurazione di attivazione e schema DB invariati; backup verificati.
- Verifica dopo il deploy: report #85 ancora `validated`, evento #392 ancora `pending`, associazione `linked` e un solo creatore attivo.
- Smoke esterno: Home, wizard e pagina QA restano in manutenzione (503); discovery risponde 200; il nuovo endpoint personale rimane bloccato dall'esterno. Nessun errore PHP fatale rilevato nelle risposte controllate.
- Nessun collaudo browser di questa versione viene dichiarato eseguito.
