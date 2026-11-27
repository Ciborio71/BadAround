# BadAround Identity — stato verificato del rilascio, 6 novembre 2026

Il runtime è in produzione su badaround.it, esclusivamente nel perimetro QA riservato. Questo documento non certifica l'apertura pubblica né la conclusione di tutti i collaudi.

## Risultati verificati

| Componente | Evidenza | Limite |
| --- | --- | --- |
| Registrazione e accesso | Identità verificata e sessione attiva rilevate dopo la prova dell'utente | Logout/scadenza nel browser non ancora ricollaudati |
| Protezione QA | IP server autorizzato, destinatario email limitato, pagina privata e hash informativa | Nessuna apertura pubblica |
| Avvio WordPress | Tre definizioni email spostate prima di wp-settings, valori invariati | Backup e permessi verificati |
| Wizard | Endpoint QA selezionato solo dal server nel contesto autorizzato; cookie same-origin; successo solo dopo owner_confirmed | Nuovo invio reale dal browser da eseguire |
| Commenti | Modulo sulla pagina evento nel QA; intenti privati in moderazione | Pubblicazione e thread non certificati |
| Richieste di modifica | Evento e report collegati sul server, digest canonico letto sul server, controllo proprietà e CSRF | Nessuna modifica automatica del contenuto pubblico |
| Media | Eccezione QA limitata ai percorsi e metodi esatti, marker necessario; errori di autorizzazione intatti | Upload end-to-end dal browser da eseguire |
| Baseline | Deploy mirati con confronto byte per byte, backup e rollback; Core principale invariato | QA visiva completa di Home/Mappe/Radar ancora aperta |

## Test

- Suite Identity locale: 82 script PHP superati usando soltanto un database locale effimero, più il nuovo script revisioni (12 controlli).
- Wizard/media/trasporto QA: 41 test JavaScript superati.
- Controllo manutenzione privata: 26 controlli superati, inclusi marker, percorsi, metodi, IP e conservazione degli errori di sicurezza.
- Quattro test frontend obsoleti sono stati riprodotti come fallimenti sulla baseline precedente, quindi aggiornati ai contratti già vigenti: identità pubblica obbligatoria, rimozione dei valori inattivi, array facoltativo per il profilo, validatore native selezionato per versione. Nessuna regola di prodotto è stata cambiata per farli passare.
- CI completa e deploy mirati community/media: PASS. Verificati 85 moduli prima e dopo ogni rilascio; Core principale, flag e schema invariati.
- Smoke anonimo: Home, Mappa, Radar e pagina QA restituiscono la manutenzione prevista; discovery è disponibile; Identity e report QA non risultano utilizzabili dall'esterno del perimetro.
- Prove esterne con intestazioni X-Forwarded-For e X-Real-IP falsificate: il percorso privato resta bloccato (503 manutenzione) in entrambi i casi.
- Discovery: nessuna occorrenza dei campi privati Identity cercati nella risposta pubblica; questo controllo mirato non sostituisce un audit completo di tutti i dati pubblici.
- Nessun test ha letto o esportato codici OTP, cookie di sessione o credenziali dell'utente.

## Lavoro ancora aperto e sequenza

1. Collaudo browser sulla connessione autorizzata: nuovo invio sintetico, senza pubblicazione automatica, verifica del report e dell'associazione al creatore; poi prova immagini e retry senza duplicati.
2. Prova reale di commento e richiesta modifica con sessione corretta; rifiuto di richieste su segnalazioni altrui.
3. Completare e collaudare l'amministrazione delle proposte con associazione verificata tra account WordPress e Identity; revisione del digest corrente prima di applicare una modifica. Non dedurre l'associazione dalla sola uguaglianza delle email.
4. Completare la proiezione pubblica dei commenti approvati, le risposte nel thread e le modalità di visibilità concordate. Gli intenti privati non equivalgono a commenti pubblicati.
5. Collaudo browser di logout, scadenza/revoca, codice errato/riutilizzato, area attività, responsive e regressione visiva/funzionale dei flussi esistenti.
6. Informativa definitiva con la revisione prevista dal titolare; pianificare l'apertura pubblica solo dopo i gate precedenti. Il testo QA adottato non certifica la revisione legale della policy pubblica.
7. Allineamento finale del branch di produzione, backup e verifica dopo il rilascio. Nessun reset o cancellazione di dati reali rientra in questa esecuzione.

La prima dipendenza esterna è l'esecuzione del flusso dal browser sulla connessione autorizzata: la sessione dell'utente non è disponibile all'esecutore e il filtro IP non viene allargato per aggirare questo limite.

## Riferimenti

- Frontend QA: commit `6816f289d35df6cdc5f8c5417e9905fc96149c41`, [run 42135287389](https://github.com/Ciborio71/BadAround/actions/runs/42135287389).
- Revisioni private: commit `99e2b1a0aed176c964521591a35a31d56d0ed5c3`, [run 42135724097](https://github.com/Ciborio71/BadAround/actions/runs/42135724097).
- Media QA e regressione: commit `dd6a883991e40df45a10849a310d47efa70cc9ea`, [run 42136262873](https://github.com/Ciborio71/BadAround/actions/runs/42136262873).
