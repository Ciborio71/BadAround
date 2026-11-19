# Quartiere / zona — implementazione del 11 novembre 2026

## Comportamento

Dopo la scelta di un indirizzo verificato, il wizard può proporre un quartiere dai componenti Google neighborhood/sublocality. Se i componenti mancano, viene tentata una geocodifica inversa con controllo della stessa gerarchia territoriale italiana e timeout di sette secondi. Nessun POI viene trasformato in quartiere. Le ambiguità restano irrisolte.

Il campo è facoltativo e consente correzione, conferma esplicita e omissione. La ricerca mostra un indicatore di attività senza bloccare la compilazione. Una modifica del luogo annulla la proposta; una risposta tardiva non può sovrascrivere il testo modificato dall’utente. Riepilogo e snapshot di invio conservano la proposta confermata nel report privato.

La prima pubblicazione non rende pubblica la zona. Nel box amministrativo «Dettagli pubblici del wizard» il moderatore deve verificare sia i dettagli descrittivi sia la zona. Per un’area approssimata deve verificare la coerenza con l’intera area pubblica, senza aumentarne la precisione. Il valore approvato è firmato e vincolato alla geometria pubblica: cambi di geometria o prove non valide lo nascondono immediatamente.

Scheda, testi SEO e feed pubblico consumano esclusivamente il valore approvato. La SEO copy 1.3 usa la zona in H1, description, title quando compatibile con il budget e heading geografici H2/H3; gli altri titoli conservano la variazione semantica per tipologia. Gli URL congelati non cambiano. Non vengono creati termini territoriali, landing automatiche o confini ufficiali.

## Verifiche

- Nuovi test di componenti Google, scelta/correzione/conferma, blocco invio e risposte tardive.
- Persistenza reale nel harness V2, limiti Unicode, tuple parziali, fonte non valida, HTML e campi V1 non autorizzati.
- Pubblicazione dichiarata e legacy, revisione separata, protezione testo privato, geometria alterata, SEO da valore approvato.
- Regressioni sui 33 sottotipi, Identity, wizard, GEO, privacy, moderazione, galleria, metadati Home e URL.

Nessun evento reale creato, modificato o cancellato per il collaudo automatico; nessuna email inviata. Nessun reset, migrazione DB o apertura al pubblico.

## Limiti della certificazione

I test Google utilizzano risposte simulate: non certificano che Google restituisca il quartiere per ogni indirizzo reale. L’assenza del dato lascia il campo facoltativo e modificabile. Il collaudo visuale e la richiesta Google reale dalla connessione autorizzata restano da effettuare; la manutenzione pubblica e i controlli QA non vengono aggirati. I confini ufficiali richiederebbero un dataset geografico distinto.

## Rilascio verificato

- Runtime commit: `dd9b511376ad34d2fa27f981d8a25f7b482a060f`.
- [Release 42985848709](https://github.com/Ciborio71/BadAround/actions/runs/42985848709): PASS; 12 file verificati prima e dopo l’installazione, backup individuali fuori dalla webroot, nessuna variazione di attivazione o reset dati. Il primo tentativo si è fermato al gate dei test senza upload; nel secondo è stata corretta l’aspettativa del test sullo snapshot di retry.
- 133/133 test JavaScript PASS, suite PHP V2 con 3411 asserzioni e 33 oracle, nuove suite neighborhood-contract / neighborhood-publication PASS, regressioni SEO/GEO/Identity e guardia FTPS PASS.
- [Identity CI 42985848555](https://github.com/Ciborio71/BadAround/actions/runs/42985848555): PASS.
- Verifica nativa su produzione: flag neighborhoodProposal attivo; anteprima SEO 395 copy_version=1.3; URL congelato Honda SH, preview editoriale e proiezione pubblica precedenti invariati; stesso source_hash del report. Nessuna assegnazione retroattiva di quartiere.
- Feed Home verificato tramite endpoint applicativo con contesto di prova Pomezia; risposta con nove card e nessuna proposta privata esposta.

I controlli online sopra sono diagnostiche native autenticate e feed pubblico, non una certificazione visuale del browser né un test Google reale su indirizzi specifici.
