# BadAround — preflight database per QA privata su badaround.it

Data controllo: 4 novembre 2026.

## Evidenza verificata
- Deploy additivo di 18 moduli Identity dormienti eseguito con successo: https://github.com/Ciborio71/BadAround/actions/runs/41928202879.
- Verifica FTPS dei backup eseguita in sola lettura: https://github.com/Ciborio71/BadAround/actions/runs/41929137741.
- Directory di backup accessibile; 2 archivi candidati (83.436.228 e 83.433.794 byte).
- Date degli archivi: 13 ottobre 2026, 14 ottobre 2026. Non sono backup recenti al 4 novembre.
- Integrità del contenuto SQL, corrispondenza al database attuale e ripristino **non verificati**.
- Verifica database in sola lettura: la tabella cixe6qoXf_ba_identity_recovery esiste e usa InnoDB; la tabella cixe6qoXf_ba_identity_pre_intake è assente.
- Core e endpoint pubblico originali rimasti invariati.

## Gate per modifica SQL in produzione
1. Generare una copia **attuale** del database e dei file del sito tramite il servizio di backup hosting, senza inserirla nel repository o negli artefatti pubblici.
2. Verificare che la copia abbia contenuto coerente, data corrente, accesso controllato e piano di ripristino praticabile; preferibile test di restore isolato.
3. Confermare l'assenza della tabella pre-intake e la baseline SQL.
4. Eseguire solo CREATE TABLE additivo dallo schema PHP pre-intake-schema-candidate.php, senza toccare tabelle esistenti.
5. Verificare esattamente motore, colonne, chiavi univoche, indici e stato vuoto con informazioni di schema.
6. Non attivare nuovi endpoint o flag Identity; eventuale rollback elimina solo una tabella appena creata e **vuota**, previa verifica.

## Sicurezza
Questa ricognizione non autorizza a considerare i due archivi più vecchi come restore-verified. Nessuna tabella è stata creata da questo controllo. Non copiare dati personali del DB nei log o su GitHub.
