# BadAround — contratto SEO degli eventi v1.1
Stato: APPROVATO dal titolare il 6 novembre 2026. Vincolante per ogni nuovo evento e sviluppo futuro.
Integra SEO-URL-01 v1.0 senza modificarne privacy, stabilità degli URL e separazione tra dati privati e pubblici.
Questo documento formalizza le regole; non certifica che il generatore sia già implementato.

## Fonte dei contenuti
Slug, title, meta description, H1 e contenuto derivano dalla stessa proiezione pubblica approvata dal moderatore, con template specifici per sottotipologia. Mai inferire fatti mancanti o usare direttamente dati privati di intake.
Niente identità del segnalante, email, telefono, targa completa, civico o coordinate private. Località e dettagli solo nella precisione autorizzata per la pubblicazione. Distinguere segnalazione, fatti confermati e aggiornamenti.

## URL
Pattern: /eventi/{tipo-evento}-{localita}-{dettaglio-distintivo}/
Comune obbligatorio quando disponibile pubblicamente; quartiere facoltativo e solo approvato. Dettaglio utile: marca/modello, oggetto, razza o caratteristica effettivamente disponibile. Nessun dettaglio inventato.
Minuscole, parole separate da trattini, niente keyword ripetute o data di default.
Collisioni: prima un ulteriore dettaglio pubblico utile; in mancanza un identificativo pubblico breve e stabile, senza esporre identificatori privati. Preservare l'invariante public_url_id stabile.
Congelare alla prima pubblicazione. Cambi di stato o descrizione non cambiano URL. Ogni modifica deliberata successiva richiede redirect 301 diretto, canonical, sitemap e collegamenti interni coerenti; evitare catene.
Esempio con dati pubblici disponibili: veicolo-rubato-roma-fiat-panda.

## Title e H1
Title: {soggetto e accadimento} a {località} | BadAround.
H1: stesso nucleo descrittivo senza brand; un H1 principale per pagina come regola di progetto.
Adattare genere e preposizioni. Titoli unici, naturali e coerenti con la sottotipologia; niente formule ripetute o affermazioni non verificate.
Obiettivo editoriale indicativo 50–60 caratteri per title, senza tagliare informazioni essenziali. Non è un limite imposto da Google.
Esempio: Fiat Panda segnalata rubata a Roma | BadAround.
H1: Fiat Panda segnalata rubata a Roma.

## Meta description e introduzione
Riassumere soggetto, episodio, località e data soltanto se disponibile e pubblicabile. Inserire un dettaglio distintivo utile e un invito pertinente.
Obiettivo editoriale 140–160 caratteri; non un limite tecnico. Google può scegliere uno snippet diverso.
Non promettere immagini, testimoni o aggiornamenti assenti.
Esempio: Segnalazione di una Fiat Panda rubata a Roma. Consulta i dettagli pubblici disponibili e gli eventuali aggiornamenti sull’episodio su BadAround.
Introduzione visibile di 2–3 frasi concrete, senza ripetizioni artificiali o contenuti riempitivi.

## Heading e sezioni
- H2 Cosa è stato segnalato.
- H2 Dove e quando.
- H2 specifico per sottotipologia.
- H2 Immagini della segnalazione, solo con immagini pubbliche approvate.
- H2 Aggiornamenti sull’evento, solo con aggiornamenti effettivi.
- H2 Commenti della community, con contenuti approvati.
- H2 Segnalazioni correlate nella zona, solo con collegamenti pertinenti.
Omettere sezioni vuote; sottosezioni H3 soltanto quando necessarie.

| Sottotipologia | Esempio H1 illustrativo | H2 specifico |
| --- | --- | --- |
| Veicolo rubato | Fiat Panda segnalata rubata a Roma | Caratteristiche del veicolo |
| Danno in sosta | Auto danneggiata durante la sosta a Pomezia | Danni segnalati |
| Furto in abitazione | Furto in abitazione segnalato a Roma | Dinamica e dettagli disponibili |
| Ostacolo stradale | Albero caduto sulla carreggiata a Torvaianica | Ostacolo e tratto interessato |
| Rifiuti abbandonati | Rifiuti abbandonati segnalati a Pomezia | Tipologia dei rifiuti segnalati |
| Animale smarrito | Labrador smarrito a Pomezia | Come riconoscere l’animale |
| Oggetto smarrito | Mazzo di chiavi smarrito a Torvaianica | Caratteristiche dell’oggetto |

Gli esempi non autorizzano a inventare attributi. Per sottotipi ulteriori usare lo stesso criterio semantico, con nomenclatura canonica della tassonomia.

## Aggiornamenti
Title, description e contenuto possono riflettere uno stato moderato successivo (ritrovato, recuperato, risolto). URL stabile. Un solo emettitore coerente di title, description e canonical, senza duplicazioni tra tema e plugin.
Il generatore automatico e la sua integrazione nel Publication Service restano task aperti finché verificati su nuovi eventi, collisioni, privacy e regressioni.

## Reset, beta e apertura
Sequenza approvata: completamento sviluppo e integrazione SEO → collaudi tecnici → reset controllato dei dati di prova → nuova beta da zero → correzioni e regressioni → apertura pubblica.
Il reset è una fase futura: questo contratto non esegue cancellazioni né autorizza perdita di dati reali. Prima del reset: backup verificato e ripristinabile, inventario e classificazione dei record e delle dipendenze (report, eventi, commenti, proposte, media, associazioni). Definire esplicitamente cosa preservare: utenti amministrativi, configurazione, tassonomie, baseline, audit e dati reali.
Mantenere protezioni QA durante beta e reset. Informativa definitiva e revisione prevista prima dell'apertura pubblica.

## Riferimenti metodologici
- https://developers.google.com/search/docs/crawling-indexing/url-structure
- https://developers.google.com/search/docs/appearance/title-link
- https://developers.google.com/search/docs/appearance/snippet
Le regole sono una scelta progettuale approvata, non una garanzia di posizionamento.


## Addendum 7 novembre 2026 — titoli delle sezioni evento

La pagina pubblica usa un solo H1 e H2 descrittivi per foto, caratteristiche, descrizione, luogo/periodo, riepilogo e commenti. I titoli combinano tipologia canonica, marca/modello pubblico e comune; quartiere/località solo se canonici e già pubblici. I campi del wizard alimentano il contenuto dopo approvazione, senza esporre dati riservati o dedurre fatti mancanti. Colore e segni distintivi restano nei dati pubblici e nell’H1 quando previsto: non si ripetono artificialmente tutti gli attributi in ogni titolo.

H3 organizzano indirizzo, data/periodo e mappa sotto il relativo H2. Nella community H3 identificano discussione/composizione e H4 gli autori dei commenti approvati, quando presenti. Le label dei singoli attributi e le legend dei form conservano la loro semantica. Non si aggiungono heading vuoti, salti di livello o sezioni inventate solo per parole chiave.

Esempio Honda SH: “Foto della segnalazione di furto Honda SH”, “Caratteristiche del veicolo Honda SH”, “Informazioni evento: Honda SH segnalato rubato a Roma”. Queste combinazioni rispondono a intenti plausibili; non rappresentano volumi di ricerca misurati o garanzie di posizionamento.

La proiezione è in sola lettura e comprende anche eventi precedenti senza attivarne o riscriverne il contratto SEO. H1, title/description, URL congelati, dati originali, GEO e stile tipografico esistente non cambiano. I nuovi H3 ereditano dimensione e famiglia dei precedenti testi/label.

## Implementazione 8 novembre 2026 — dati strutturati

Le pagine evento pubblicate, moderate e aderenti al contratto1.1 emettono WebPage e BreadcrumbList in JSON-LD, con canonical invariato e gerarchia visibile Home → Segnalazioni → scheda. Nessun tipo Event per i fatti segnalati, nessuna identità, posizione precisa, data dedotta o media non approvato. Gli eventi storici non vengono migrati automaticamente. Con Yoast/Rank Math attivi l’emettitore si ritira; manutenzione e noindex non vengono modificati. Validazione Google e crawl pubblico restano un gate di apertura.

## Addendum vincolante 10 novembre 2026 — generatore editoriale dinamico v1.2

Questo addendum aggiorna la composizione editoriale, mantenendo il contratto tecnico URL/attivazione v1.1. La richiesta del titolare è differenziare Title, H1 e heading per funzione, senza duplicare la frase principale in ogni sezione. Le regole valgono per le nuove pubblicazioni e per il rendering degli eventi già aderenti al contratto; gli eventi storici senza attivazione SEO non vengono migrati automaticamente nei metadati.

- Title: sintesi dell’accadimento, soggetto disponibile e Comune; via o zona e colore sono dettagli facoltativi, inseriti quando lo spazio lo permette. Si sceglie una formulazione completa e più breve prima di troncare; il budget è editoriale, non un limite imposto dal motore di ricerca.
- H1: identità del soggetto e caratteristiche disponibili, separatore `|`, tipologia segnalata e posizione pubblica. Il formatter esistente conserva iniziali maiuscole, articoli/preposizioni minuscoli, acronimi e modelli. Nessun cambiamento di font, dimensioni o impaginazione.
- Description: riassunto distinto dal Title e dall’H1, costruito da fatti pubblici. Ricerca testimoni soltanto se esplicita e ancora pertinente allo stato; data soltanto quando valida e già pubblica. Metadati di lunghezza controllata e taglio Unicode sicuro; nessun riempitivo per raggiungere un numero di caratteri.
- H2: scopi differenti per immagini, riconoscimento/caratteristiche, racconto, luogo/periodo, riepilogo e contributi della community. Variare i titoli in base alla sottotipologia, distribuendo soggetto e territorio nei punti utili. Nessuna rotazione casuale di sinonimi.
- H3: indirizzo pubblico, data/periodo e mappa sotto l’H2 geografico; altre sottosezioni soltanto quando corrispondono a contenuti reali. Nessuna aggiunta di H4 per finalità SEO. La gerarchia della discussione già presente resta distinta.
- Foto: H2 descrittivo soltanto con immagini pubbliche effettive. In assenza di immagini resta il box compatto “Nessuna foto disponibile”, accessibile e senza H2 che suggerisca la presenza di fotografie.
- Animali: specie dalle enumerazioni pubbliche approvate. Oggetti: smarrimento/ritrovamento solo dalla relativa scelta approvata; non dedurlo dal nome del sottotipo o da testo privato.

### Precisione geografica e targa

L’indirizzo completo e il civico sono ora pubblicabili nella scheda con Punto esatto e dichiarazione registrata, secondo la policy `public-details-v1` implementata il 10 novembre. Con Area approssimata restano riservati. La targa dichiarata è pubblicata nel campo dedicato della scheda, dopo moderazione.

Questa autorizzazione non impone di ripetere civico o targa nei metadati SEO: il generatore continua a escluderli da Title, description, H1 e heading. Può usare la sola via da un indirizzo già pubblico e firmato, con estrazione conservativa; in caso di formato ambiguo ricorre a Comune/località pubblica. Non legge direttamente i report riservati e non introduce località o date dedotte.

### Esempio di distribuzione

Per Fiat Panda, colore bianco, furto a Roma e via pubblica Viale Marconi:

| Elemento | Composizione illustrativa |
| --- | --- |
| Title | Furto Fiat Panda, colore bianco a Roma, in Viale Marconi \| BadAround |
| H1 visualizzato | Fiat Panda, Colore Bianco \| Veicolo Segnalato Rubato a Roma, in Viale Marconi |
| H2 foto, se presenti | Foto della segnalazione: Fiat Panda |
| H2 caratteristiche | Caratteristiche e segni distintivi: Fiat Panda |
| H2 descrizione | Come è avvenuto il furto segnalato |
| H2 posizione | Luogo e periodo del furto a Roma |
| H2 riepilogo | Informazioni utili su Fiat Panda a Roma |
| H2 community | Commenti e aggiornamenti sul furto a Roma |

Gli esempi rappresentano regole editoriali, non keyword con volumi misurati o promesse di posizionamento. La via compare solo dopo l’approvazione della proiezione pubblica; non è ricavata dal campo privato per popolare un’anteprima.

### Invarianti tecniche

Slug e `public_url_id` invariati; nessuna riscrittura di titoli/URL di eventi storici già pubblicati; nessuna migrazione DB. `project()` restituisce ancora un DTO piatto di stringhe per il validator del Publication Service. La revisione editoriale è esposta separatamente come `copy_version=1.2` nell’anteprima riservata. JSON-LD resta WebPage + BreadcrumbList; non si aggiungono Event, identità, coordinate precise o date dedotte. Protezioni QA, noindex, moderazione, Identity e reset non vengono modificati.
