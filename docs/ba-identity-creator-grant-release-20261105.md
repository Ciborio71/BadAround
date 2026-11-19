# BadAround Identity — rilascio della protezione ownership

Data: 5 novembre 2026. Stato: correzione distribuita in produzione con Identity disattivato; mandato complessivo ancora aperto.

## Risultato verificato

Il commit `c67a1794f7c86ca8026b76d46973636ae6e38ac5` introduce un unico adattatore PDO per l'assegnazione del creatore nei percorsi WordPress di invio QA e recupero. L'adattatore mantiene il lock della submission durante la transazione, blocca la riga dell'identità, rilegge prenotazione e journal, verifica ricevuta canonica e cronologia, distingue il proprietario già confermato dai conflitti e rifiuta stati revocati o ambigui. Verifica inoltre InnoDB e il vincolo univoco sulla risorsa. Una transazione del chiamante viene rifiutata senza modificarla.

L’inventario dei moduli passa da 84 a 85. Sono stati distribuiti esattamente tre file sotto `wordpress/plugins/badaround-core/identity-dormant/`:

| File | SHA-256 |
| --- | --- |
| `mysql-report-creator-grant.php` | `d77ea3cafd3c50fee13f3eb4812f36e0a1926ba795f384f1fb6d709b4f266978` |
| `wordpress-pre-intake-recovery-composition.php` | `8fe861cf3a4048fe9470234c67f1ffc4d2a6708c212846beedca771a7d2c396d` |
| `wordpress-qa-report-composition.php` | `6a0bb1f2f3ba42d0ac607751206491278470c5e67de67e5fb1c4476eb65fbd58` |

## Validazione

| Verifica | Esito |
| --- | --- |
| 78 script locali, con i moduli aggiornati sovrapposti ai test del prototipo | PASS |
| 30 controlli dell'adattatore PDO, inclusa revoca concorrente da processo indipendente e lock nativo annidato | PASS |
| Sintassi di 83 moduli PHP | PASS |
| 5 test del rilascio, inclusi arresto per drift e ripristino dopo errore parziale | PASS |
| CI PHP 8.3 e MySQL 8.0.46 | PASS |
| Confronto byte per byte degli 84 moduli precedenti prima della scrittura | PASS |
| Confronto finale dei moduli precedenti, con i due aggiornamenti previsti | PASS |
| Presenza del nuovo modulo e lettura dei due collegamenti WordPress in produzione | PASS |
| Core con SHA-256 `18cb9ad21a63e08449eb85b7cb9233846bb02e2c472d05f118cd7d5764b7d175` | Invariato |
| Copie di ripristino dei due file precedenti fuori dalla webroot | Originali preservati tramite rinomina |

Evidenza CI e deploy: [run 42113034420](https://github.com/Ciborio71/BadAround/actions/runs/42113034420). Tutti e tre i job sono conclusi con successo. Il runtime locale utilizzato è PHP 8.3.6 con MariaDB 10.11.14; il server dichiara WordPress 7.1.4 e PHP 8.3.36.

Il rilascio non modifica Core, tema, database o impostazioni. Dopo il deploy `BADAROUND_IDENTITY_RUNTIME_INTEGRATION_ENABLED` risulta ancora non definita e `POST /badaround/v1/identity/session` restituisce `rest_no_route` nell'accesso amministrativo. Non sono state inviate email OTP né creati consensi o legami di proprietà su dati di produzione.

## Attività ancora aperte

Le verifiche automatiche non costituiscono il collaudo browser end-to-end del sito. Restano configurazione dell'accesso QA, casella di test autorizzata e ricezione OTP, prove reali di registrazione/sessione/invio/modifica/commenti, verifiche di moderazione e regressione UI, quindi attivazione pubblica.

La protezione attuale rifiuta l'informativa pubblicata perché contiene marcatori di bozza. Non è stata modificata né aggirata. Il testo validato e l'indicazione della casella OTP sono necessari per proseguire con i collaudi che dipendono da questi prerequisiti.

La schermata pubblica di manutenzione è gestita dal tema e rispondeva HTTP 503 già prima del deploy; il controllo nativo di manutenzione WordPress non la rileva. Prima e dopo il deploy l'API discovery risponde HTTP 200; la schermata di manutenzione mantiene HTTP 503. Nessuna certificazione visiva delle pagine protette è stata eseguita in questa sessione.
