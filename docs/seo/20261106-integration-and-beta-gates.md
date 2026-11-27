# BadAround — integrazione SEO e stato prima della beta

Contratto approvato: [SEO-EVENTS v1.1](../contracts/SEO-EVENTS-v1.1.md).

## Implementazione

- Generatore comune nel Core, con template per tutte le 33 sottotipologie canoniche attuali.
- Legge esclusivamente tassonomie pubbliche e attributi veicolo già destinati alla scheda pubblica. Non legge report, profili, indirizzi o coordinate private. Non inventa razza, quartiere, dinamica, stato smarrito/trovato o date mancanti.
- Località SEO limitata al Comune canonico: nessuna interpretazione del nome della Provincia come Comune e nessuna espansione automatica della precisione geografica.
- I nuovi eventi vengono preparati nel Publication Service esistente; i testi SEO rientrano nel controllo di corrispondenza con i dati riservati prima della pubblicazione. Restano applicati capacità, moderazione, fence media e controlli GEO.
- Slug descrittivo; identificativo pubblico casuale persistente. Omonimie risolte con suffisso stabile, evitando il semplice progressivo numerico. Il congelamento definitivo avviene dopo il successo della pubblicazione. Lo slug rimane stabile alle modifiche successive.
- Title, description, H1 e introduzione coerenti. Lo stato risolto/chiuso può aggiornare i metadati di pagina senza modificare l'URL.
- Heading specifici per i dettagli disponibili. Nessuna sezione immagini per il solo placeholder; nessuna sezione commenti approvati vuota sulle nuove pagine del contratto.
- Anteprima per moderatori nel backend e endpoint GET con doppio controllo di capacità (moderazione ed edit del singolo evento), senza scritture e con no-store.
- Nessuna migrazione automatica dei contenuti o degli URL esistenti. L'evento di prova precedente al contratto mantiene il proprio URL. Il canonical WordPress e la sitemap continuano a usare il permalink effettivo: non viene aggiunto un secondo emettitore di canonical.
- Non è disponibile una migrazione deliberata degli URL congelati. Prima di introdurla serviranno i redirect 301 diretti e la verifica di sitemap e collegamenti; questo rilascio non cambia URL pubblicati.

## Evidenze

Primo codice: `45bd99735420b4a70f85f90c573dab52562c2a39`.
CI e deploy: [42228234838](https://github.com/Ciborio71/BadAround/actions/runs/42228234838), PASS (PHP, MySQL e deploy).
Log del driver: EVENT_SEO_RELEASE_PASS; confrontata la baseline live e preservato l'intero runtime Identity, backup delle sorgenti verificati, permessi conservati e lettura successiva dei file coincidente.
I backup delle sorgenti non equivalgono a un backup completo e ripristinabile dei dati per il reset.

Secondo codice (anteprima e gerarchia commenti): `848e4c88c5ca33b764a12f083412df0add425fdf`.
CI e deploy: [42228849961](https://github.com/Ciborio71/BadAround/actions/runs/42228849961), PASS (PHP, MySQL, verifica sorgenti e rilascio).
Lettura amministrativa online GET /badaround-ops/v1/event-seo/392: contratto 1.1; active=false per questo evento storico, come previsto; current_url invariato; anteprima slug veicolo-rubato-roma-fiat-panda, title e H1 Fiat Panda — veicolo segnalato rubato a Roma, heading Caratteristiche del veicolo. L'anteprima è calcolata dal codice effettivamente installato, non salvata o applicata all'evento precedente.
Plugin attivi verificati: nessun Yoast o Rank Math, quindi nessun secondo emettitore SEO di tali plugin.
Smoke anonimo dopo il rilascio: Home, wizard, QA, Mappe e Radar 503 con manutenzione; discovery 200; endpoint SEO non esposto (503 fuori perimetro). Nessun errore PHP fatale nelle risposte controllate. Il 503 delle pagine protette è atteso e non certifica il layout interno.

Test locali: generatore e privacy nel vero harness del Publication Service; tre regressioni pubblicazione/GEO; autorizzazione modifiche; moderazione con database SQLite; fence QA e informativa; 41 test wizard/media/trasporto e 2 test DOM area personale/commenti. Tre prove del deploy: successo, drift prima delle scritture, rollback a metà installazione. Esiti PASS dopo le correzioni degli stub di test.
La CI remota ripete anche la suite MySQL. Questi test non sono una certificazione browser completa del sito online.

## Inventario reset (letture del 6 novembre 2026)

| Oggetto | Conteggio verificato |
| --- | ---: |
| Eventi pubblicati | 23 |
| Eventi in attesa | 62 |
| Report attivi | 85 |
| Media dei report attivi | 5 |
| Allegati WordPress complessivi | 7 |
| Intenti Identity approvati | 3 |
| Iscrizioni Sentinelle | 3 |
| Abbinamenti Sentinelle/evento | 4 |
| Report senza evento associato | 4 |
| Eventi in attesa senza report collegato | 4 |
| Media attivi senza report | 0 |

I quattro report non associati hanno event_id NULL: non vengono qualificati come corruzione senza ulteriore analisi. I quattro eventi senza report sono in attesa e rimangono soggetti al gate di pubblicazione.
Non assumere che tutti e sette gli allegati siano media di eventi: possono esserci risorse grafiche del sito. Non cancellare indiscriminatamente la libreria media, gli iscritti o gli utenti.
Tutte le tabelle Core/Identity rilevate usano InnoDB; table_rows di information_schema è indicativo ed è stato sostituito da COUNT(*) per i conteggi riportati sopra.

## Sequenza operativa e condizioni ancora aperte

1. Completare il collaudo autorizzato con invio di immagine, verifica moderazione/proiezione, logout, proprietà altrui negata e modifica concorrente di un evento pubblicato.
2. Verificare una nuova pubblicazione effettiva con SEO v1.1 e assenza di duplicati di title, description, canonical e H1 nella pagina autenticata, oltre alla regressione visiva sulle pagine principali.
3. Acquisire un backup recente e completo di DB, media pubblici e privati e sorgenti. Conservare in area privata fuori dalla webroot, verificare integrità e provare il ripristino in un database isolato. Registrare il manifest dei record e delle dipendenze e la procedura inversa.
4. Solo dopo i gate precedenti, applicare il reset dei dati di prova con procedura ripristinabile. Preservare configurazione, tassonomie, amministratori, credenziali, baseline e audit di custodia; verificare con attenzione iscrizioni e identità prima di includerle nel perimetro. Nessun TRUNCATE o cancellazione irreversibile è autorizzato.
5. Ripartire da nuove fixture beta; provare tutte le categorie, GEO esatto/area/territorio, media, collisioni URL, Identity, modifiche e commenti; correggere e ripetere le regressioni pertinenti.
6. Informativa definitiva con la revisione prevista dal titolare prima dell'apertura pubblica. Nessuna attivazione pubblica in questo rilascio.

## Limite osservato nel browser

Il browser automatizzato ora si avvia, ma la connessione non appartiene al perimetro QA autorizzato: /identity-qa/ mostra la manutenzione. Anche il renderer esterno restituisce la stessa pagina. Non sono state modificate allowlist, sessioni o protezioni per aggirare questo limite.
Il collaudo autenticato completo, il reset e l'avvio della nuova beta non vengono dichiarati conclusi. Nessun dato è stato cancellato e nessun backup completo del DB è stato dichiarato eseguito in questa lavorazione.
