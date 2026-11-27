# BadAround Identity — adozione privata dell’informativa QA

Stato: integrazione distribuita in produzione, runtime ancora disattivato. Nessun OTP inviato e nessun collaudo browser certificato in questa fase.

## Adozione e configurazione

L’utente ha approvato di procedere dopo la consegna dell’informativa del collaudo. È stata creata la pagina WordPress **388**, slug `identity-qa-informativa`, con stato **private**. La pagina pubblica privacy **3** non è stata modificata.

La configurazione server `badaround_identity_qa_notice_adoption` registra:

- versione `qa-privacy-21865f47e19464df`;
- SHA-256 del contenuto `21865f47e19464dfca3ffc72fd51b0e4efb0e3f1301bf49dcf94200fb587c8f5`;
- ambito `private_identity_qa` e stato `owner_adopted`;
- `legal_review_verified=false`, senza attestare una revisione legale;
- data di registrazione `2026-11-05T22:33:18` GMT.

La casella OTP indicata dall’utente è stata salvata nell’opzione privata `badaround_identity_qa_email_allowlist`. L’indirizzo non è incluso nei sorgenti pubblici.

## Comportamento implementato

Il collegamento QA richiede il flag dedicato `BADAROUND_IDENTITY_QA_NOTICE_ENABLED`, il runtime privato, un peer autorizzato e un’adozione coerente con il contenuto di una pagina privata. Un testo cambiato, un hash errato, uno stato pubblico o una configurazione incoerente bloccano la verifica. Il documento viene mostrato nel portale prima della registrazione, all’interno dello stesso perimetro di accesso.

Il controllo `verified_version()` dell’informativa pubblica rimane distinto e invariato. Un’adozione QA non attiva le funzioni pubbliche e non diventa una revisione legale. La registrazione continua a richiedere la presa visione effettiva; nessun consenso utente è stato creato da questa operazione.

Il filtro email supporta l’opzione privata amministrativa, mantenendo la precedenza della costante server se definita. Rifiuta destinatari non autorizzati, liste malformate o duplicate; la revoca della configurazione ha effetto anche su un filtro già istanziato.

## Verifiche e rilascio

| Verifica | Esito |
| --- | --- |
| 80 script locali complessivi | PASS |
| 18 controlli specifici sul documento QA | PASS, inclusi negli 80 script |
| 12 controlli sulla configurazione email privata | PASS, inclusi negli 80 script |
| 7 test del rilascio e del ripristino | PASS |
| CI PHP e MySQL | PASS |
| Confronto degli 85 moduli precedenti prima e dopo il rilascio | PASS |
| Sei moduli aggiornati con originali preservati fuori dalla webroot | PASS |
| Core, tema e wp-config | Nessuna modifica da questo rilascio |

Commit applicativo: `8aae3508dfc7c1761d864fcbeaff2418295285d7`. [Esecuzione CI e deploy 42123153739](https://github.com/Ciborio71/BadAround/actions/runs/42123153739).

Dopo il rilascio la costante del runtime non risulta definita e `POST /badaround/v1/identity/session` restituisce `rest_no_route` nell’accesso amministrativo. La creazione della pagina e delle due opzioni è separata dal rilascio dei file; modifica contenuti/configurazione WordPress, senza migrazioni delle tabelle Identity o assegnazioni di ownership.

## Passo successivo

Occorre conoscere l’IP pubblico della connessione usata dal tester, necessario per la lista di accesso esistente. Seguono configurazione sicura dei prerequisiti QA, verifica della disponibilità del trasporto email e collaudi reali con OTP. Non sostituire l’IP con wildcard, non usare intestazioni inoltrate non verificate e non attivare percorsi pubblici per evitare questo requisito.

I punti operativi indicati nell’informativa, inclusi fornitori, trasferimenti e tempi di conservazione proposti, restano distinti dai test automatici e dall’adozione del testo. Questo rilascio non li certifica come completati.
