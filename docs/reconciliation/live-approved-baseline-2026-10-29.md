# BadAround — manifest riconciliazione baseline live (29 ottobre 2026)

**Stato: PARZIALE / NON CERTIFICATO PER DEPLOY.**

Regola approvata: il sito live `https://badaround.it` prevale su qualsiasi vecchio ramo del repository. Nessun file applicativo può essere sovrascritto da una versione più vecchia.

## Origine dei componenti verificati

| Percorso relativo a `wordpress/` | Ramo GitHub candidato | Verifica |
| --- | --- | --- |
| `themes/badaround-child/front-page.php` | `release/production-mvp-01` | testo leggibile corrispondente, 213 righe |
| `themes/badaround-child/page-mappa.php` | `release/production-mvp-01` | testo leggibile corrispondente, 168 righe |
| `themes/badaround-child/functions.php` | `release/production-mvp-01` | testo leggibile corrispondente, 577 righe |
| `themes/badaround-child/assets/css/map.css` | `release/production-mvp-01` | testo leggibile corrispondente, 1115 righe |
| `themes/badaround-child/assets/css/home.css` | `release/production-mvp-01` | testo leggibile corrispondente, 364 righe |
| `themes/badaround-child/assets/js/native-report/wizard.js` | `release/production-mvp-01` | testo leggibile corrispondente, 684 righe |
| `themes/badaround-child/assets/js/map.js` | `release/production-mvp-01` | letto a blocchi, righe leggibili corrispondenti salvo mascheramenti token |
| `themes/badaround-child/page-radar-demo.php` | `release/production-mvp-01` | letto a blocchi, righe leggibili corrispondenti salvo mascheramenti token |
| `plugins/badaround-core/badaround-core.php` | `release/production-mvp-01` | testo leggibile corrispondente |
| `plugins/badaround-core/includes/class-badaround-discovery-query.php` | `release/production-mvp-01` | testo leggibile corrispondente, 437 righe |
| `plugins/badaround-core/includes/class-badaround-intelligence-read-model.php` | `release/production-mvp-01` | testo leggibile corrispondente |
| `plugins/badaround-core/includes/class-badaround-publication-service.php` | `feature/ext-intake-01-admin-manual` | testo leggibile corrispondente, 890 righe, **NON coincide con release** |
| `plugins/badaround-core/includes/class-badaround-moderation-admin.php` | `feature/ext-intake-01-admin-manual` | testo leggibile corrispondente, 827 righe |
| `plugins/badaround-core/includes/class-badaround-external-report-intake.php` | `feature/ext-intake-01-admin-manual` | 500 righe, 1 redazione automatica alla riga 416 |

## Gate prima di un deploy

1. Verificare gli SHA-256 direttamente sul filesystem live mediante modalità read-only; i contenuti WPVibe possono essere mascherati, pertanto non garantiscono confronto byte-exact.
2. Proseguire l'inventario di **tutti** i file del child theme e Core. Le verifiche qui documentate riguardano un sottoinsieme.
3. La versione live del Publication Service è differente dal ramo release: preservare la variante External Intake, verificarne ogni dipendenza, aggiungere soltanto con patch chirurgiche.
4. Nessun merge automatico delle PR #37, #39, #40, #42–#47 sul ramo release. Testare la lineage e il delta complessivo.
5. Test reali WordPress, pubblicazione/moderazione, GEO/privacy, Smart Address, sentinelle, mappe, responsive, SEO, rollback e QA post-deploy.
6. Autorizzazione esplicita prima di una pubblicazione del nuovo Radar.

**Questo documento non rappresenta una certificazione completa della produzione e non autorizza deploy.**
