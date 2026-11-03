# BA-IDENTITY-01I — Custodia dei moduli dormienti su produzione
Data: 2026-11-03

## Trasferimento completato
- Deployment GitHub Actions run 41708653155: SUCCESS.
- FTPS path: /httpdocs/wp-content/plugins/badaround-core/identity-dormant/.
- 31 moduli PHP (senza file di test o DDL), più DEPLOY-MANIFEST.txt.
- Ogni modulo caricato è stato letto nuovamente e verificato con SHA-256.
- Baseline Core FTPS preflight e post-upload: SHA-256 eb5d6d24800f2150adb400ec410626ab5982264d3f36a0763b5c38cead90f7d0.
- Nessun file esistente del Core è stato sovrascritto. I moduli non vengono inclusi da badaround-core.php.

## Controlli live successivi
- WPVibe read-only directory listing: 32 file presenti (31 PHP + manifesto).
- Versione Core: 0.18.0; opzione ba_db_schema_version: 1.9.0.
- ba_evento: 84 record, invariati. Nessuna migrazione Identity eseguita.

## Limitazioni
- Il deploy NON abilita login/OTP, registrazione, commenti, revisioni o Area personale.
- Nessun hook di produzione, endpoint REST o cron aggiunto.
- Serve completare il bootstrap controllato, provisioning e storage sicuro delle chiavi, migrazione, accesso privato, audit, consensi, test e rollback prima dell'abilitazione.
- La directory di custodia deve essere trattata come immutabile: ogni update richiede riconciliazione e nuovi controlli sull'eventuale stato live; non copiare snapshot storici del Core.
- Reset degli eventi di test solo dopo approvazione della versione stabile e inventario/backup separato.