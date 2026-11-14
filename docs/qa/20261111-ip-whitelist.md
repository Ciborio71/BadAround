# Whitelist IP e manutenzione privata — 11 novembre 2026

La richiesta di aggiungere una seconda connessione autorizzata è stata applicata conservando quella già funzionante. I valori effettivi rimangono nell’opzione amministrativa `badaround_identity_qa_ip_allowlist`, senza inserirli nel codice.

Il bootstrap privato supporta ora una lista da 1 a 16 IP esatti e distinti. Tutti gli elementi devono essere indirizzi validi; liste vuote, jolly, duplicati, valori non validi e liste eccessive vengono rifiutati integralmente. Il controllo usa esclusivamente REMOTE_ADDR. Email di collaudo, informativa, segreto e controlli applicativi restano invariati.

Il tema ammette alle pagine pubbliche durante la manutenzione una richiesta con il flusso QA Identity interamente abilitato e il filtro REST privato registrato. Un bootstrap parziale non basta. Le pagine di collaudo ricevono no-store/private e noindex. Non sono stati allargati i percorsi REST: restano i controlli già esistenti su origine, autenticazione, OTP, capacità e moderazione.

## Rilascio

- Runtime: `e3d3c30bee71ae64c5470a635c35a7fc42ae3e5d`.
- [Run 43012571571](https://github.com/Ciborio71/BadAround/actions/runs/43012571571): PASS; verifica esatta della configurazione privata prima della modifica, conservazione byte per byte del segreto, backup protetto fuori webroot, permessi 0600 e verifica dopo upload. Functions.php verificato rispetto alla baseline e dopo rilascio.
- Suite MySQL del template reale: IP singolo e multiplo, header falsificati, peer mancante, policy invalide, chiavi preesistenti e feature disabilitate PASS.
- Suite manutenzione: guest esterno, bootstrap parziale, QA autorizzata, gestione WordPress, no-store/noindex PASS. Regressioni portale e SEO PASS.
- [Identity CI 43012571226](https://github.com/Ciborio71/BadAround/actions/runs/43012571226): PASS.
- Solo dopo il rilascio verificato, whitelist aggiornata con il nuovo IP esplicitamente richiesto e riletta tramite API amministrativa: due elementi esatti, connessione precedente preservata.
- Verifica online da connessione esterna: pagina di manutenzione ancora restituita. Diagnostica amministrativa: file privato leggibile con permessi 0600, credenziali mail presenti, informativa integra, nessuna tabella InnoDB mancante; il peer amministrativo del bridge rimane escluso dalla QA.

Il vecchio campo diagnostico `ip_option_is_single_list=false` indica ora correttamente che la lista contiene due elementi: non è un requisito del nuovo bootstrap. Non è stata eseguita una visita reale da ciascuno dei due IP durante questa verifica. Nessun dato evento modificato o eliminato, nessuna chiave ruotata, nessuna email inviata.
