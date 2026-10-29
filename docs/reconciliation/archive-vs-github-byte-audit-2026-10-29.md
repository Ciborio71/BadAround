# Audit byte-level ZIP → GitHub, 29 ottobre 2026

Sorgenti: archivi forniti dall'utente `badaround-child (1).zip`, `badaround-core (1).zip`. Gli identificativi Git Blob SHA-1 sono stati calcolati sui byte estratti, poi confrontati con i valori restituiti dall'API GitHub sul ramo `reconcile/live-approved-2026-10-29`.

## Esito
- Child theme: **56/56** file inventariati e identificativi Git Blob corrispondenti.
- Core: **64/65** identificativi Git Blob corrispondenti.
- Eccezione: `wordpress/plugins/badaround-core/includes/class-badaround-publication-service-OLD.php` presente nello ZIP (blob `b92224d39e6860578e1993db9a57d296800728dc`) ma **assente** dal ramo GitHub. File storico; non inserirlo automaticamente nel runtime o nel deployment. Va conservato nell'archivio di backup e valutato separatamente.
- Modifiche applicative rispetto al vecchio ramo release: tre file selettivi già ricostruiti (Core bootstrap class, Publication Service, External Intake).
- Verifica file: equivalenza **byte-per-byte per i 120 file corrispondenti** rispetto alle copie ZIP, mediante identità del digest Git Blob. Lo SHA-256 è nel manifest JSON dell'archivio, non duplicato in repository.

## Limiti residui
Questo confronto stabilisce l'identità tra *gli archivi caricati* e GitHub. Non dimostra che i file sul server non siano cambiati dopo la creazione degli ZIP. Non certifica DB, impostazioni WordPress, chiavi API, media privati, cron, cache o comportamento runtime. L'integrazione RADAR e Intelligence resta non pubblicata e richiede QA WordPress, regressioni, controllo privacy, rollback e approvazione al deploy.

## Decisione
**Baseline GitHub ricostruita per i 120 file versionati dei pacchetti ricevuti; eccezione storica isolata. Nessun deploy automatico.**
