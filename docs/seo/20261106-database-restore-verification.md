# BadAround — verifica del ripristino database, 6 novembre 2026

Esito: PASS. Dump fornito dal titolare e verificato direttamente dall'allegato, senza inviarne i contenuti a repository, log pubblici o artifact.

## Verifiche completate
- Integrità CRC dell'archivio ZIP: PASS.
- Dump SQL: 22.272.488 byte; SHA-256 `1ee92d07380d0a81b9835ac1ae0ee57a5203ff38d51caae0fb762b5b793a902c`.
- Schema WordPress e Identity presente: 59 tabelle.
- Importazione su MariaDB 10.11.14 in directory temporanea privata: exit code 0.
- Motore utilizzato senza rete, in modalità bootstrap offline. Nessun uso delle credenziali del database di produzione.
- Il lettore bootstrap ha limiti di dimensione per righe e istruzioni: è stata creata una copia di lavoro con INSERT suddivisi in batch e letterali lunghi ricomposti tramite variabili SQL. Il dump originale non è stato modificato.
- Controllo dei conteggi di ciascuna delle 59 tabelle rispetto alle righe presenti negli INSERT del dump: PASS, zero differenze.
- Totale ripristinato: 44.578 righe.
- Prova di equivalenza MD5 su un letterale sintetico con caratteri UTF-8, backslash, apici e caratteri di controllo ricomposto in parti: PASS.
- I conteggi dei componenti principali coincidono anche con le letture del sito online: 85 report, 5 media, 1 identità, 1 sessione Identity, 3 intenti Identity.
- Eventi ripristinati: 23 pubblicati e 62 in attesa, coerenti con l'inventario online.

## Perimetro e limiti
Questa è una prova di ripristino del database in un motore reale isolato, con controllo dello schema e di tutti i conteggi. Non è una certificazione di avvio dell'intero sito WordPress da backup né del collaudo browser.
Nessuna scrittura, cancellazione, attivazione o reset è stata eseguita sul database online.
La precedente indisponibilità del dump separato è risolta. Restano valide le evidenze del backup dei file e della copia integrativa privata Identity registrate in [verifica backup](20261106-backup-verification.md).
Il titolare deve conservare archivio del sito, dump DB e copia integrativa Identity.

## Gate ancora aperto prima del reset
Collaudo live dalla connessione QA autorizzata: invio con immagine e moderazione, nuova pubblicazione con contratto SEO v1.1, logout e modifica concorrente di un evento pubblicato. Il browser remoto rimane fuori dal perimetro autorizzato; nessuna protezione è stata ridotta.
Dopo il superamento del collaudo si potrà eseguire il reset controllato dei dati di prova e iniziare la nuova beta come da contratto.
