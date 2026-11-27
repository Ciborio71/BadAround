# BadAround — verifica backup del 6 novembre 2026

## Risultati verificati
- Archivio utente: directory-completa-bak.zip.
- Individuato sul server tramite gli accessi FTPS esistenti; nessun contenuto del backup pubblicato nei log o negli artifact.
- Dimensione: 1130358471 byte. Elementi ZIP: 38826.
- Integrità CRC di tutti gli elementi: PASS.
- SHA-256: 772a49275f767413011c3925f51b8b1b33d43800c695d44c90dc536f897fda5f.
- Presenti wp-config.php e la cartella dei media privati.
- Assente nell'archivio la configurazione privata Identity fuori dalla webroot.

## Integrazione privata eseguita
È stata creata e verificata la copia /wordpress-backups/ba-pre-beta-private-config-42233985424.zip.
Contiene esclusivamente la configurazione Identity originale, senza rigenerazione delle chiavi.
Confronto byte per byte, integrità ZIP e controllo dei permessi 0600: PASS.
SHA-256 della copia integrativa: 563ce4dfc4a26752a9e52f945094af2f97ca4edc079565e93ddc26e7f8f17b3a.
La configurazione in uso è invariata. Il titolare deve conservare anche questa copia insieme al backup scaricato, in posizione privata.
Non sono stati modificati dati applicativi, URL, impostazioni di attivazione o controlli di sicurezza.

## Database: blocco effettivo
Il singolo file SQL presente nell'archivio ha 796 byte e una sola definizione di tabella.
Non contiene lo schema completo WordPress/Identity e non è stato qualificato come dump completo.
Nessun altro .sql o .sql.gz è stato trovato nelle quattro directory esaminate; questo non prova l'assenza del dump in altre cartelle o sul computer del titolare.
Il dump separato dichiarato eseguito dal titolare non è ancora disponibile per la verifica.
Ripristino DB: NON ESEGUITO. Nessuna scrittura o cancellazione sul database di produzione.

## Evidenza e prosecuzione
[Verifica e integrazione 42233985424](https://github.com/Ciborio71/BadAround/actions/runs/42233985424): esecuzione completata; risultati applicativi riportati sopra. La riuscita del job non equivale alla verifica completa del backup DB.
Le prove locali dell'ispettore verificano CRC, riconoscimento di SQL parziale, riconoscimento dello schema completo e preservazione del file privato.

Prima del reset occorrono:
1. Disponibilità del dump DB separato (file caricato o percorso privato raggiungibile con gli accessi esistenti).
2. Verifica e prova di ripristino del dump in ambiente isolato.
3. Completamento del collaudo live dalla connessione QA autorizzata: immagini, logout e modifica concorrente; verifica di una nuova pubblicazione SEO.
4. Solo dopo questi gate, reset controllato dei dati di prova e nuova beta come da contratto.

Stato live riconfermato: branch e rilascio SEO precedenti coerenti; 62 eventi pending e 23 pubblicati; zero invii media nel nuovo flusso Identity e zero eventi già marcati con il nuovo contratto SEO.
Nessun reset è stato eseguito.


## Aggiornamento — dump separato ricevuto e ripristino verificato
Il blocco relativo al dump separato è risolto: prova di ripristino isolata PASS, 59 tabelle e 44.578 righe con zero differenze nei conteggi. Vedere [rapporto di ripristino](20261106-database-restore-verification.md). Il collaudo live e il reset restano fasi successive.
