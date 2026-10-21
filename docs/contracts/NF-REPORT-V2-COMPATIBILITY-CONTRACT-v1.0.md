# NF-REPORT-V2-COMPATIBILITY-CONTRACT v1.0

**NF-UX-02A — Architecture / contract only — 21 ottobre 2026.** Stato: READY. Nessuna implementazione, deploy, merge o mutazione WordPress/DB/tassonomia. Nessun cambiamento GEO, media, Social, SEO. Non avvia NF-UX-03/04.

## Fonte normativa e ambito della verifica

Dipendenza immutabile: NF-CONDITIONAL-CONTRACT v1.0 APPROVED/FROZEN, 21 ottobre 2026. SHA-256 del JSON verificato prima della redazione:

`dc282153243ffb80f3bd4f2a222ea0f4c4f42806fba6f3a0bde0a76855bd8e3c`

Rimangono invariati: 33 sottotipi, 85 percorsi di campo nel contratto espanso, 2.805 combinazioni, 33 regression oracle, 8 Specifica, danni 2 REQUIRED/14 OPTIONAL/17 HIDDEN, perimetro ricompense, stale policy e trigger emergenze. Questo documento disciplina trasporto/versione, dispatch, persistenza e compatibilità, non modifica la matrice semantica congelata.

Baseline sorgente verificata: [Ciborio71/BadAround, SHA 08f96d6f4dbd8ce8294e219857002f975351acf4](https://github.com/Ciborio71/BadAround/tree/08f96d6f4dbd8ce8294e219857002f975351acf4). Analisi statica di schema, validator, normalizer, intake/Golden Path/REST, repository, idempotency, result, installer, moderation admin/service, persistence, Publication, GEO moderation, media service/adapter/persistence/fence/controller/validator/capability, Native config/model/API/error adapter. È una verifica della baseline fissata, non un'attestazione di test runtime v2 o dello stato di tutti i branch successivi.

Abbreviazioni percorsi: CORE=`wordpress/plugins/badaround-core/includes/`; THEME=`wordpress/themes/badaround-child/`. Le evidenze puntuali sono elencate in I. Fixture #35/#312 già verificata nel task precedente; qui non riletta né modificata: resta evidenza storica v1, non viene normalizzata secondo v2.

### Confine tra documento semantico e versione tecnica

L'inventario `fields.schema_version.constraints.enum` del JSON congelato contiene ancora `badaround-report/v1`, perché acquisito dalla baseline v1. Il medesimo artifact approva esplicitamente v2. **Non è corretto importare letteralmente quel vincolo v1 nella definizione dello schema v2.** Questo documento rende esplicita la regola tecnica: schema_version è REQUIRED, stringa, vincolata alla versione selezionata dal dispatch. Nel ramo v1 l'enum resta solo v1; nel ramo v2 è solo v2. Nessuna modifica al JSON congelato o al suo hash; nessuna rinomina o inferenza di versione dai nuovi campi. Questa regola tecnica ha precedenza per il discriminator, mentre condizioni e campi semantici restano quelli congelati. Non è un'autorizzazione a sovrascrivere altri vincoli della matrice.

## A — Version marker

Il campo canonico esistente è **`schema_version`**, alla radice del payload, non `schema`, `version` o una nuova key. Forma v2:

```json
{
  "schema_version": "badaround-report/v2",
  "submission_id": "<UUID valido>",
  "event": {"category": "animal", "subtype": "animal_missing"}
}
```

Esempio parziale di marker, non payload completo inviabile. Marker stringa esplicita e stabile; nessun campo v2 può sostituirlo. Presenza di *_other_detail con marker v1 produce unknown_field, non upgrade a v2. Nessun marker mancante viene assunto v1 o v2. Il trim esterno previsto per la rappresentazione stringa è ammesso; confronto versione case-sensitive, nessun alias o riconoscimento per prefisso.

Separazioni obbligatorie:

| Identità | Valore/decisione |
|---|---|
| Payload canonico | schema_version=v1 oppure v2, autorità del dispatch |
| REST namespace/endpoint | Conservare POST `/wp-json/badaround/v1/reports`; il v1 del namespace è versione di trasporto, non discriminator canonico |
| Header integrità Native | Conservare `X-BadAround-Intake: badaround-report/v1` anche per payload v2; è il token di trasporto esistente, non viene usato per scegliere lo schema |
| Config frontend | schemaVersion diventa v2 al cut-over; markerHeader/markerValue restano quelli di trasporto |
| Versione richiesta GEO | public-location-request-v1 invariata |
| Preferenza Social | social-preference-v1 invariata |
| Versioni media/consensi/DB | Nessun incremento implicito collegato a schema_version |

Conservare l'header esistente evita di rompere v1 o introdurre una seconda fonte di versione. Non accettare header arbitrari e non rimuovere i controlli same-origin, sec-fetch-site, MIME JSON, limite 128 KiB, rate limit o header di risposta. Un eventuale futuro nuovo token di trasporto sarebbe un task distinto.

## B — Request acceptance policy

**Modello C: Native pubblico → v2 soltanto dopo cut-over; API/backend → v1 + v2.** Nessuna scadenza automatica o finestra implicita per v1. La dismissione richiede una decisione futura esplicita e non è parte di questo contratto.

| Richiesta | Accettazione dopo implementazione |
|---|---|
| v1 canonico valido | Ramo certificato v1; stessi significati, normalizzazione, errori, risposta e retry |
| v1 con key v2-only | Rifiuto unknown_field; nessuna inferenza/upgrade |
| v2 valido secondo matrice | Ramo v2; stesso Golden Path e moderazione pendente |
| Versione assente/vuota | Errore sul discriminator; nessun default |
| Versione non supportata | invalid_schema_version; nessun downgrade |
| Versione mal tipata | invalid_type, field=schema_version |
| Vecchio client con snapshot v1 già aperto al cut-over | Resta v1 e conserva UUID/payload; l'API continua ad accettarlo |
| Nuovo form dopo cut-over | Nuovo snapshot esplicitamente v2; nessuna conversione del form in corso o retry con altro marker |

UI non modifica schema_version di uno snapshot già preparato/inviato o associato a una sessione media pinned. Per ricominciare con v2 occorre una nuova compilazione e un nuovo submission UUID, non una riscrittura del vecchio tentativo.

## C — Validator dispatch

Architettura raccomandata: **registry esplicito di versioni + ruleset/normalizer separati per v1 e v2**, riusando solo primitive senza semantica specifica di versione. V1 resta il comportamento certificato; v2 non si ottiene cambiando una costante globale da v1 a v2. Possibili nomi di classi sono scelte implementative; la separazione e il dispatch sono obbligatori.

Ordine normativo per un nuovo tentativo:

1. Controlli trasporto/security esistenti, decode radice JSON object, limite dimensioni.
2. Validazione del discriminator e selezione esplicita registry. Marker mancante/errato non raggiunge un validator “di default”.
3. Dispatch raw preflight versione-specifico. V1 conserva l'ordine e la tolleranza già certificati; v2 controlla allowlist e struttura, quindi genitori necessari a determinare l'attività.
4. Normalizzazione/validazione della versione scelta. V2 applica il grafo semantico approvato; v1 non lo conosce.
5. Risoluzione territoriale esistente prima di qualsiasi nuova riserva/persistenza, senza geocoding nuovo o cambio GEO.
6. Lock identità UUID condiviso tra versioni; verificare binding/versione/snapshot esistente **prima di riutilizzare o aggiornare la riserva**. Poi intake/Golden Path esistenti con envelope coerente.

Unknown/raw request invalidi sono respinti senza mutare prenotazioni. Il conflitto di versione riguarda richieste valide della propria versione che arrivano al controllo identità; non sostituisce gli errori di trasporto/validazione precedenti. È ammesso anticipare un controllo read-only di binding per evitare lavoro, ma il controllo atomico sotto lock resta obbligatorio e non deve cambiare il comportamento same-version v1.

Risultati interni (`Report_Result`) e mapping errori devono conoscere il ruleset selezionato: oggi interrogano la costante/schema singolo anche per distinguere conditional_field_required. Non usare le condizioni v2 per rimappare errori v1.

## D — Normalizzazione

### V1: identità di comportamento

Mantenere esattamente normalizer, ordine campi, coercizioni ammesse, validazione raw, strip delle sole condizioni v1, rendering storico ed equality v1 già certificati. Nessuna nuova Specifica obbligatoria, nessuna nuova rimozione danni e nessuna nuova policy reward. Unknown key rimane unknown. Il fallback legacy/WPForms `validate_and_normalize` resta nel suo percorso, non diventa un ingresso permissivo a v2.

### V2: pipeline attiva e senza cambi di significato

| Fase | Regola |
|---|---|
| Allowlist | 77 percorsi correnti più 8 Specifica, con discriminator v2; niente key generiche aggiunte dal client |
| Struttura | Contenitori dominio object; un dominio attivo non può essere array/scalare. Campi array sono validati dal loro contratto dedicato |
| Genitori | Validare categoria/sottotipo e i valori di controllo prima di usarli per il grafo; un padre sconosciuto/invalido genera errore, non autorizza strip di dati potenzialmente attivi |
| Attività | Usare stato/condizioni della matrice per il sottotipo; active(parent) è semantico e ricorsivo, non visibilità dello step |
| Campi noti inattivi | Strip, anche se avevano un tipo/valore stale errato; non convertire in not_applicable, no, none o stringa vuota |
| Campi attivi | Validare tipo e limiti; normalizzare soltanto rappresentazioni previste senza modificare il valore semantico |
| Omissioni | OPTIONAL vuoto omesso; REQUIRED/CONDITIONAL-required vuoto invalido. false/0 non equivalgono a “vuoto”: applicare il relativo vincolo |
| Snapshot | Deterministico per v2; solo campi attivi normalizzati, envelope esplicito e invarianti degli adattatori certificati |

Per le primitive ereditate, sono consentite solo equivalenze lessicali già definite dal rispettivo contratto: trim, rappresentazione numerica/data/ora, UUID in minuscolo, formattazione targa prevista, bool nei formati accettati dalla primitive. Non sanificare un enum invalido fino a farlo diventare valido (es. eliminare punteggiatura o spazi interni); non eliminare parole, HTML o simboli da un testo attivo per trasformarlo in un altro contenuto accettabile. Se la rappresentazione non può essere normalizzata senza perdita di significato, validazione negativa. Non dedurre categoria/risposta dal testo. Per stringhe canoniche v2 non applicare Unicode NFC/NFKC implicita né troncamenti. Per nuove Specifica: sole stringhe, trim esterno e controlli descritti in E; nessuna coercizione numerica.

Questa policy v2 non cambia le primitive GEO/media/Social o le loro unità: conservare i risultati certificati e rifiutare input invalido anziché tentare una correzione geografica. Non convertire unknown in exact, lost in found, other in testo, omissione reward in none, danni nascosti in not_applicable. Nessun nuovo campo canonico per l'avviso emergenze: location.immediate_danger conserva yes/no/unsure; i nove trigger e il notice sono derivati UI dalla matrice, non nuove prove o richieste di soccorso.

### Determinismo e uguaglianza dello snapshot

Il backend è autorità del normalizzato. Per v2 stabilire un ordine di emissione dei percorsi coerente con l'ordine `fields` del contratto congelato (esclusi campi assenti), costruendo gli oggetti padre in ordine della prima occorrenza. Ordinamento delle key del JSON grezzo inviato non influisce sull'output normalizzato. Preservare l'ordine degli array: non riordinare media.items o manifest. Le multiselezioni mantengono l'ordine canonico prodotto dalla normalizzazione prevista, senza inventare un'uguaglianza per insiemi. Non ordinare globalmente gli snapshot v1.

Encoding per confronto server: quello già impiegato dal repository e media service, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES e primitive canoniche coerenti col backend. Il contenuto confrontato è l'oggetto **canonical normalizzato comprensivo di schema_version**, non il body HTTP, non l'envelope storage con campi di compatibilità, non l'ordine delle risposte UI. Non introdurre un secondo algoritmo di canonicalizzazione che diverga dal media canonical_hash.

V2 prima/dopo strip di figli sicuramente inattivi deve produrre lo stesso snapshot; cambi a un campo attivo rimangono cambi anche quando il campo è facoltativo. Campi opzionali vuoti omessi e oggetti vuoti lasciati dallo strip vengono rimossi. Eccezione di rappresentazione già certificata media: media.items assente e array vuoto restano entrambi percorsi no-media, ma non vengono dichiarati automaticamente uguali per retry se la primitive corrente conserva la differenza. Non aggiungere/rimuovere media.items per far coincidere due hash.

## E — Otto field key v2 ratificate

I percorsi sono quelli approvati, senza rinomina. Tutti sono tipo **string**, single-line, max in caratteri Unicode, min1 significativo dopo trim; non HTML, non controlli, non soli spazi. Label “Specifica” con nome accessibile contestuale. Attivazione: padre semanticamente attivo e =other, oppure contiene other nel solo array stolen_item_categories. Required solo quando attivo. Altrimenti assenza dal payload, clear frontend e strip backend.

| Percorso canonico v2 | Padre / trigger | Max | Classificazione | Visibilità downstream |
|---|---|---:|---|---|
| location.place_type_other_detail | location.place_type=other | 160 | PRIVATE ONLY | Solo report/admin autorizzato; nessun luogo pubblico aggiunto |
| vehicle.type_other_detail | vehicle.type=other | 120 | PUBLIC CANDIDATE AFTER MODERATION | Input privato del moderatore; eventuale testo pubblico approvato |
| vehicle.color_other_detail | vehicle.color=other | 80 | PUBLIC CANDIDATE AFTER MODERATION | Input privato del moderatore; eventuale testo pubblico approvato |
| animal.type_other_detail | animal.type=other | 120 | PUBLIC CANDIDATE AFTER MODERATION | Input privato del moderatore; eventuale testo pubblico approvato |
| property.tampered_entry_point_other_detail | property.tampered_entry_point=other | 160 | PRIVATE ONLY | Solo report/admin autorizzato |
| property.stolen_item_categories_other_detail | property.stolen_item_categories contains other | 300 | PUBLIC CANDIDATE AFTER MODERATION | Input privato del moderatore; eventuale testo pubblico approvato |
| property.access_method_other_detail | property.access_method=other | 300 | PRIVATE ONLY | Solo report/admin autorizzato |
| authority.type_other_detail | authority.type=other AND authority.status=yes | 160 | PRIVATE ONLY | Solo report/admin autorizzato |

Normalizzazione: trim esterno senza riscrittura semantica; reject non-stringhe, markup e controlli (incluse nuove righe); lunghezza su codepoint Unicode, non byte. Errori di limite non producono troncamento. Persistenza per ciascuno: `ba_reports.content_original` → `canonical` → percorso sopra; niente meta pubblico nuovo o colonna dedicata. Esempio animal: `content_original.canonical.animal.type_other_detail`. Non usare il path completo come key letterale con punti nel JSON: rappresenta annidamento.

Riepilogo autore: padre reso “Altro: [specifica]”, nessuna riga generica duplicata; altri valori di multiselezione restano presenti. Per luogo usare l'adattatore di riepilogo senza alterare richiesta pubblica/coordinate. I quattro campi PRIVATE ONLY non sono candidabili alla pubblicazione raw. I quattro candidati possono informare soltanto un testo rielaborato e approvato dalla moderazione; la loro presenza non autorizza export in scheda, Discovery, JSON-LD, Social o log pubblici. event.other_type resta distinto e invariato nella sua funzione di specifica del sottotipo.

## F — Unknown/inactive policy

| Classe | V1 | V2 |
|---|---|---|
| Key non definita dalla versione | Rifiuto certificato unknown_field; nessuna key v2 aggiunta a v1 | Rifiuto unknown_field anche sotto dominio inattivo; strip non è allowlist permissiva |
| Key nota ma inattiva | Esatto preflight e strip v1, inclusa verifica raw-type già esistente | Strip difensivo prima della validazione del valore figlio; non errore e non persistenza |
| Attiva OPTIONAL assente/vuota | Comportamento v1 invariato | Assenza lecita; niente placeholder serializzato; se fornita non vuota, validazione completa |
| Attiva REQUIRED assente/vuota | Errore v1 invariato | missing_required_field sul percorso |
| CONDITIONAL attiva e required | Errore v1 secondo condizioni v1 | conditional_field_required sul percorso |
| CONDITIONAL non attiva | Condizioni v1 | HIDDEN effettivo, strip/riepilogo assente |
| Padre attivo invalido | Errore v1 invariato | Errore sul padre; vietato dichiarare tutti i figli inattivi per sopprimere l'errore |

Unknown controllato prima dello strip: `{animal:{type_other_detail:'stale',injected:'x'}}` fuori categoria → injected rifiutato; non “buttare via tutto animal” e accettare. Un contenitore vuoto con solo percorsi ammessi può essere eliminato dal normalizzato v2. Se un dominio intero è HIDDEN e il contenitore è stale scalare anziché object, può essere rimosso come gruppo noto inattivo solo quando categoria/sottotipo validi provano che nessun suo campo è attivo; se almeno un campo è attivo, invalid_type. Non usare questa regola per ignorare figli unknown di un object.

Le array-leaf (media.items e multiselect) non sono buchi nell'allowlist: restano validate dalla definizione dedicata. V1 conserva esattamente i controlli esistenti (incluse le tolleranze raw); v2 non può accettare attributi media aggiuntivi, descrittori arbitrari o key di workflow client-side. Non promuovere `payload_hash`, flag di moderazione, proiezioni o `_reservation` a key canoniche accettate.

## G — Idempotency/retry e binding di versione

### Evidenza della baseline

`Report_Idempotency_Service::reserve` usa lock MySQL per `native:<UUID>`; repository proietta l'UUID nella UNIQUE source_entry tramite digest (source_type=native e form_id/entry_id deterministici). **La chiave non include la versione.** Il full UUID resta nel canonical e lock. Stati: NEW, IN_PROGRESS, COMPLETED, FAILED_RETRYABLE. La baseline può creare una riga received prima di avere content_original/payload_hash; stored_payload_matches restituisce null se non c'è hash. Un retry di una riserva v1 fallita senza snapshot non ha oggi un confronto canonical definitivo: non pretendere che esista un binding versione che il codice non salva ancora.

`payload_hash` corrente è SHA-256 dell'envelope storage, mentre il confronto duplicati usa SHA-256 del JSON `canonical` decodificato. Sono identità distinte: non sostituire l'una all'altra e non riscrivere gli hash v1 storici.

### Regole definitive

| Identità/tentativo | Risultato normativo |
|---|---|
| Stesso UUID, stessa versione, stesso canonical normalizzato, già completato | Stessi report_id/event_id; successo idempotente, duplicate=true; HTTP 200 pubblico |
| Stesso UUID, stessa versione, snapshot diverso e già vincolato | duplicate_submission, HTTP409, retryable=false; nessuna sovrascrittura |
| Stesso UUID, versione diversa dopo binding/riserva riconoscibile | **PAYLOAD CONFLICT**: duplicate_submission, HTTP409, retryable=false; ragione interna version_mismatch; nessuna mutazione della versione prenotata |
| Lock occupato e binding non ispezionabile in sicurezza | submission_in_progress, HTTP409, retryable=true, come baseline; nessuna nuova riserva. Al retry con lock disponibile, confronto versione obbligatorio |
| Stesso UUID v2 con sole differenze di valori noti inattivi | Dopo strip, snapshot identico → stesso risultato idempotente; presenza unknown resta errore |
| Stesso UUID v2 con campo attivo cambiato | Snapshot diverso → conflitto; anche se campo facoltativo |
| Nuovo UUID | Nuova richiesta secondo versione esplicita; mai il meccanismo per modificare un report precedente automaticamente |
| UUID diverso che collide sulla proiezione digest dello storage | Verificare full UUID e rifiutare conflitto, mai collegare all'altro report o “riparare” con una versione diversa |

La riserva v2 deve acquisire **atomicamente un binding durabile di UUID + schema_version + canonical snapshot normalizzato** prima di diventare riutilizzabile. Deve usare le colonne esistenti content_original/payload_hash sotto lock: inserimento della riga con envelope v2 già vincolato (o transazione che renda visibili riga e binding insieme). Non creare una riserva v2 nuda e affidarsi a un secondo update non atomico; nessun crash deve consentire di riprenderla come v1. Se il binding non può essere scritto, non riservare e non produrre evento. Non cambiare source_type in native_v2 o salare il digest con versione.

Per nuove riserve v1 dopo il dual-stack, è lecito fissare atomicamente il solo marker v1 nell'envelope di prenotazione tecnico, senza cambiare canonical normalizzato, accettazione o comportamento di recupero same-version certificato. Lo snapshot finale v1 e il confronto hash rimangono quelli correnti. Questo requisito è infrastruttura di protezione cross-version, non nuova validazione semantica v1.

**Riserve legacy preesistenti senza marker/canonical:** non inventare un discriminator né migrarle. Restano nel ramo legacy v1 di recovery già esistente; un tentativo v2 con quell'UUID riceve conflitto e non può appropriarsene. Admin deve distinguerle come riserva legacy incompleta, non come record v2. Se una riga ha canonical/envelope corrotto o marker incoerenti, bloccare recovery cross-version e segnalare integrità; non scegliere una versione dai campi. Nessuna modifica retroattiva per risolvere un retry. V1 con snapshot completo mantiene equality e stati certificati, anche FAILED_RETRYABLE; v2 FAILED_RETRYABLE conserva binding/snapshot e può riprendere solo la stessa versione e lo stesso normalizzato.

Quando esiste intent media pinned/binding/committed, anche intent_json/canonical_hash vincolano la versione prima dell'eventuale riga report: controllare la compatibilità **prima di mutare** sessione/report. Un retry v1 non può passare nel no-media branch e riservare lo stesso UUID già pinned v2, e viceversa. Per intent leggibile con altra versione restituire duplicate_submission; errori autonomi di capability/manifest mantengono i loro codici certificati. Non resettare né ripinnare il ledger per cambiare versione.

### Coordinamento lock senza introdurre deadlock

No-media deve restare senza costruzione/readiness/lookup al servizio media e senza lettura del ledger media. Il controllo identità/versione legge esclusivamente il registry report condiviso, come il controllo UUID già esistente. Non usare un fallback al media service o al ledger nel ramo no-media: altrimenti la dipendenza no-media sarebbe cambiata.

Modello definitivo: al pin media che rende irrevocabile l'intent, la versione e l'identità vengono vincolate nello stesso registry report/UUID condiviso; usare il medesimo canonical snapshot, senza sostituire il ledger come autorità di capability/ownership. Prima di creare la riserva v2 devono essere concluse validazione e risoluzione territoriale, come per il non-media; niente riserva v2 basata su un intent territorialmente invalido. Il no-media branch legge solo registry report come prima. L'implementazione deve rispettare i lock media esistenti e acquisire il lock UUID condiviso per il binding: ordine media admission/submission/session → native UUID, mai l'inverso. Il Golden Path successivo riacquisisce il native UUID solo dopo il rilascio dei lock media. Nessuna attesa su media lock mentre si detiene native UUID. Per v1 restano immutati canonical, hash e policy di pin/receipt; il binding report anticipato è un presidio di identità, non una nuova interpretazione del payload.

Il binding report anticipato dal pin richiede atomicità/compensazione testata: se il pin non può completarsi, il binding già durabile non viene cancellato o convertito a un'altra versione; il recupero usa lo snapshot originario con la policy retry/expiry media esistente. Nessun evento viene creato dal solo binding. Il version guard deve funzionare anche per il passaggio v2 media→v1 no-media: la risposta è conflitto, senza costruire il servizio media nel ramo no-media.

**Gate di cut-over per intent storici:** prima di ammettere v2, un inventario read-only deve dimostrare che ogni intent legacy pinned/binding/committed con binding durabile ha una corrispondente identità report nel registry. Se esistono intent senza report, lo switch v2 resta sospeso finché recuperati nel loro percorso v1 originale oppure coperti da un intervento tecnico di binding esplicitamente autorizzato, senza cambiare intent, hash, versione o significati storici. Non dichiarare risolto il caso per sola scadenza della sessione, non assegnare v2, non introdurre auto-migrazioni. Sessioni open non ancora pinned non hanno ancora un canonical/version binding e sono vincolate dalla prima richiesta valida, come nel contratto media. Questo gate è una precondizione verificabile della futura implementazione, non una modifica dati eseguita qui.

## H — Persistenza/version storage e lettura storica

**DB SCHEMA CHANGE REQUIRED: NO. DB MIGRATION: NONE.** Evidenza: installer `ba_reports` ha già content_original LONGTEXT nullable, payload_hash CHAR(64), UNIQUE source_entry e colonne private/link esistenti. Gli otto campi stanno nel JSON canonical; la versione sta nell'envelope e nel canonical. Non serve una colonna schema_version, un indice nuovo o un meta evento per dedurre la versione.

Forma storage finale v2 (esempio parziale):

```json
{
  "schema_version": "badaround-report/v2",
  "submission_id": "<UUID>",
  "canonical": {
    "schema_version": "badaround-report/v2",
    "submission_id": "<UUID>",
    "animal": {"type": "other", "type_other_detail": "Furetto"}
  },
  "private_fields": {"55": "<descrizione riservata di compatibilità>"}
}
```

Il mirror private_fields[55] è già usato come storage compatibility dalla baseline; non è una key del contratto canonico, non va aggiunto all'allowlist richiesta. Mantenerlo se richiesto dai consumer esistenti, senza farne la fonte di versione o di nuovi campi. L'envelope finale v1 resta quello certificato. Su v2 outer schema_version deve essere uguale al canonical.schema_version; outer/canonical submission_id uguali e coerenti col full UUID dell'identità riservata. Writer, result e response non devono derivare il marker dalla costante globale v1.

Repository read: selezionare parser/view esplicitamente dalla versione memorizzata e dal formato storico riconosciuto; verificare concordanza outer/canonical quando entrambi presenti. Non applicare il normalizer di intake attuale a un record in sola lettura: normalizzare per validare nuovi input è diverso da leggere uno storico. Legacy WPForms senza marker mantiene il suo reader storico; non diventa v2. Marker assente in una prenotazione incompleta resta unknown/legacy reservation, non canonical v1 inventato. Marker sconosciuto/incoerente: accesso diagnostico riservato possibile con escaping, ma niente avanzamento automatico workflow/riscrittura/downgrade.

Assenza v2-only su v1 è normale e non genera campi obbligatori mancanti. Nessun backfill, trasformazione other→Specifica, default damage, risalvataggio massivo o aggiornamento hash. #35/#312 conserva damage=not_applicable. Un nuovo cane smarrito v2 salva invece damage assente; i due record coesistono con significati di versione distinti.

## I — Downstream impact matrix ed evidenze

Le classi indicano il ruolo dopo l'adeguamento: V1 UNAFFECTED = ramo certificato immutabile; V2 AWARENESS REQUIRED = deve distinguere versioni/forme; VERSION-AGNOSTIC = può usare campi/proiezioni invarianti. Compatibilità per design non significa codice attuale già pronto.

| Consumer / file CORE | Classe | Evidenza baseline e requisito futuro |
|---|---|---|
| report-schema / report-validator / report-normalizer | V2 AWARENESS REQUIRED | Un'unica VERSION e fields; creare dispatch/ruleset senza alterare v1 |
| native-report-rest-controller | V2 AWARENESS REQUIRED | Token header e namespace v1, preflight media con validator unico; response schema_version da costante. Separare discriminator dal marker e rispondere con versione accettata |
| native-report-intake-service | V2 AWARENESS REQUIRED | Raw→normalize→validate→territory→reserve; istanzia normalizer/validator unici. Selezionare versione e vincolare UUID senza cambiare flusso v1 |
| report-idempotency-service / report-repository | V2 AWARENESS REQUIRED | reserve crea riga senza versione; canonical_storage_envelope usa costante. Binding atomico, writer/read consapevoli e comparison per versione; chiave UUID unica invariata |
| report-result | V2 AWARENESS REQUIRED | schema_version e privacy_map/conditional errors da schema globale; selezionare definizione del report |
| native-report-golden-path-service | V2 AWARENESS REQUIRED | Passa normalized_payload al persistence e riacquisisce lock; propagare versione accettata, non renormalizzare con v1 |
| report-persistence-service / event creation | V2 AWARENESS REQUIRED | event_meta tollera reward assente con flag false; non richiede damage. Conservare JSON v2 completo e assenza semantica, non reiniettare reward.status=none nel canonical; nessun metadato pubblico nuovo dalle specifiche |
| event-taxonomy-map / territory resolver | VERSION-AGNOSTIC | Chiavi evento e location invariate; nessuna tassonomia/territorio nuovo o geocoding |
| moderation-admin | V2 AWARENESS REQUIRED | Legge description canonica o private_fields[55], colonne PII; non è già un renderer completo dei campi v2. Aggiungere vista privata versione-specifica/Specifica, senza matrice v2 sugli storici |
| moderation-service | VERSION-AGNOSTIC | Decisioni/stati evento restano invariati; deve ricevere dati pubblici approvati, non oggetti canonical non filtrati |
| publication-service | VERSION-AGNOSTIC | Usa meta/evento approvato, controlli privacy, location canonical per B3; nessuna scansione automatica dei nuovi campi per pubblicarli |
| geo-moderation-service / public-geo-contract / public-geo-projection | VERSION-AGNOSTIC | Leggono canonical.location e versioni GEO indipendenti; medesimi dati/decisione/proiezione/sigillo |
| moderation-admin::social_preference_description | VERSION-AGNOSTIC | Usa canonical.reporter.social_visibility + consents.social_visibility_version; mantenere costante Social e significati |
| native-media-report-adapter (in native-media-persistence) | V2 AWARENESS REQUIRED | Istanzia validator e normalizer unici prima del pin; dispatch coerente col non-media, stesso snapshot |
| native-media-persistence / native-media-service | V2 AWARENESS REQUIRED | Ledger confronta canonical_hash e intent_json completi; preservare shape/algoritmo media, vincolare versione anche prima del report e impedire cross-version replay |
| native-media-fence | V2 AWARENESS REQUIRED | Riconosce nativeEnvelope tramite confronto con unica VERSION; accettare il set esplicito v1/v2 per riconoscimento protettivo, fail-closed su incoerenze. Vietato indebolire il fence |
| native-media-capability / validator / rest-controller | VERSION-AGNOSTIC per policy | Formati/ownership/rate/size/receipt/fence immutati. Eventuali chiamanti che ricevono canonical usano il dispatch superiore; non nuovo protocollo capability |
| report-intake-service legacy/WPForms | V1 UNAFFECTED | Percorso storico indipendente, non invia Specifica v2 e non usa matrice v2 |
| Discovery / mappa / dettaglio pubblico / SEO consumer | VERSION-AGNOSTIC | Consumano dati pubblici/proiezione verificata, non il canonical privato; nessuna nuova esposizione |

Rischi concreti da verificare nell'implementazione: una sostituzione globale di VERSION può marcare falsamente storici v1 come v2 o far sparire il riconoscimento nel fence; il raw preflight v1 corrente valuta i tipi prima dello strip e non può essere riusato identico per i figli stale v2; il media adapter valida separatamente, quindi cambiare solo l'intake no-media è insufficiente. Questi sono requisiti di modifica futura documentati, non modifiche eseguite.

### GEO-PUBLIC invariant

**GEO-PUBLIC CONTRACT CHANGE: NO.** Nessuna variazione di private location, public_location_request/version, decisione geografica, B3, exact_point/approximate_area/street/municipality/territory_only/legacy_generalized_point o public_geo. Nessun nuovo radius; nessuna scelta pubblica derivata da altri campi. Schema_version v2 può cambiare l'hash dell'intero report, non i valori geografici o i sigilli/proof della proiezione certificata. I test devono dimostrare equivalenza dei risultati GEO con location/moderazione identici, senza riscrivere fixture certificate.

### Media invariant e no-media

**MEDIA CONTRACT CHANGE: NO.** media.items conserva esattamente descrittori correnti media_id/mime_type/file_size/extension, max5, 5 MiB ciascuno, policy originali privati/quarantena, capability/ownership/sessione, ricevute, manifest e fence. Non accettare file nel JSON e non aggiungere versione ai descrittori. Passare il canonical v2 al ledger implica naturalmente hash diverso da v1: non è consentito riusare una pin v1 sostituendo solo versione o ricalcolare una ricevuta storica.

No-media assente/[] mantiene delega al Golden Path senza media factory/service, decoder, storage privato o capability. Il binding UUID condiviso definito in G permette il controllo cross-version senza lookup media nel percorso no-media. Input media malformato mantiene i controlli di tipo del contratto corrente: non farlo diventare assente per aggirare il fence. Nessuna attivazione di pubblicazione immagini native o derivati in questo task.

## J — API response ed error compatibility

### Successo

Conservare l'envelope pubblico esistente: status, submission_id, schema_version, intake_state=received_for_moderation, event_id, duplicate, next_state=moderation_pending. Prima creazione HTTP201; duplicate completato HTTP200. **schema_version risponde con la versione effettivamente accettata/memorizzata**, non con il “latest”. V1 restituisce v1; v2 restituisce v2; nessun accepted_version aggiuntivo necessario. Non esporre report privato, canonical snapshot, hash, specifiche o PII nella response pubblica. `normalized_payload` resta risultato interno e non viene inoltrato dal controller al browser. Header no-store/private, nosniff, request-ID e security/rate policy rimangono.

### Errori

Formato pubblico corrente invariato: `status:error`, `error:{code,field,message_key,retryable}`. source_code/details restano interni se già presenti; non aggiungere un secondo formato. I codici vengono riusati con il ruleset esplicito; le mappe di label frontend si adeguano ai nuovi field path senza cambiare il trasporto.

| Caso | code | field | HTTP | retryable | Nota |
|---|---|---|---:|---|---|
| Marker assente/vuoto | missing_required_field | schema_version | 422 | false | Nessun default |
| Marker non supportato | invalid_schema_version | schema_version | 422 | false | Anche v3 o alias |
| Marker non scalare/tipo invalido | invalid_type | schema_version | 422 | false | Nessun guess |
| Campo v2 attivo con tipo errato | invalid_type | percorso | 422 | false | Correggibile dall'autore |
| Enum/condizione genitore invalida | invalid_enum | percorso padre | 422 | false | Non sopprimere figli per “far passare” |
| Specifica attiva mancante | conditional_field_required | percorso *_other_detail | 422 | false | Padre other attivo |
| Required v2 non condizionale mancante | missing_required_field | percorso | 422 | false | Secondo matrice |
| Specifica attiva oltre limite | value_too_long | percorso | 422 | false | Nessun truncate |
| Specifica attiva markup/controlli | invalid_type | percorso | 422 | false | Stringa non conforme al tipo plain-text single-line definito; details interno reason=invalid_plain_text |
| Key v2 sconosciuta / key v2-only inviata a v1 | unknown_field | primo percorso sconosciuto | 422 | false | Stesso codice, allowlist diversa |
| Campo noto inattivo | NESSUN ERRORE | — | normale esito intake | — | Strip; non introdurre inactive_field nel pubblico |
| Retry stessa versione con snapshot diverso | duplicate_submission | submission_id | 409 | false | Come conflitto payload esistente |
| Retry versione cambiata dopo binding | duplicate_submission | submission_id | 409 | false | Ragione interna version_mismatch, nessuna info report privato |
| Lock occupato | submission_in_progress | submission_id | 409 | true | Identico modello di concorrenza |
| Binding storage corrotto/incoerente | intake_failed | null | 500 | true | Nessuna scrittura; audit tecnico senza payload; serve recovery operativa, non downgrade |

Gli errori MIME/JSON malformed/size/rate/security/media mantengono codici e HTTP esistenti. Per limiti e tipi non modificati si conserva la mappatura corrente; nessun “conditional error object” nuovo. Gli errori v1 restano quelli certificati nel rispettivo ordine di validazione.

## K — Cut-over futuro, non eseguito

1. Pin del contratto semantico per hash e di questo documento; eseguire inventory completo dei riferimenti a VERSION e schema_version sul branch candidato.
2. Implementare registry/dispatch v1+v2 e writer/reader/result consapevoli, binding UUID e media compatibilità. V1 ruleset/normalizer congelati; UI ancora v1. Nessun schema DB nuovo.
3. Test v1 differenziali PASS: raw/normalized/result/error/persistenza/equality equivalenti; Golden Path e media/no-media certificati invariati.
4. Test v2/oracle e cross-version PASS, inclusi concorrenza/crash/version binding prima del report media, assenza di leaked fields e determinismo snapshot.
5. Deploy minimo backend/readers dual-stack su staging **solo in un futuro task che lo autorizzi**; UI ancora v1. Verificare lettura degli storici senza salvarli e soddisfare il gate degli intent media legacy pre-report definito in G. Non abilitare v2 finché il registry UUID non copre tutti i binding durabili.
6. Switch nuova Native config a schemaVersion=v2; mantenere marker header, endpoint e sessioni/snapshot v1 già aperti. Invalidare cache config se necessario, senza URL migration.
7. Invio reale sintetico v2 su staging e verifica risposta, canonical/versione, admin/moderazione e downstream. B3 soltanto per fixture e pubblicazione espressamente autorizzate dal task futuro, non su #35/#312.
8. Regressione v1 e GEO/media/Publication dopo lo switch; no-media isolato senza dipendenze media; controllare coexistence e rollback.
9. Nessuna produzione o merge senza autorizzazione esplicita separata. V1 resta supportato; questo piano non ne autorizza la dismissione.

## L — Rollback

Rollback UI a v1 significa nuove compilazioni v1 con backend/readers ancora dual-stack. Tutti i v2 già creati, reserved o pinned restano v2 e devono essere leggibili/recuperabili dalla stessa versione. Un tab/snapshot v2 già aperto deve poter completare/retry v2; non cambiare marker o UUID per il solo rollback. Se si sospendono nuove creazioni v2 operativamente, non perdere i retry delle riserve già vincolate; tale sospensione richiede una policy operativa esplicita, non il fallback silenzioso.

**Minimo rollback deployabile:** mantenere dispatch/readers/storage/result/fence dual-stack e il binding comune. Vietato tornare a un backend soltanto-v1 se esistono v2: non sarebbe un rollback compatibile. Ripristinare soltanto il frontend/config oppure una build backend dichiaratamente compatibile con entrambi. Nessuna conversione v2→v1, riscrittura hash, re-pin, reset consensi o migrazione DB inversa. In caso di errore su un record, diagnostica riservata/fail-closed: non rendere pubblici i campi privati per “recuperarlo”.

## M — Test matrix futura

PASS qui è requisito atteso, non risultato runtime. Il set NF-UX-05 congelato rimane oracolo indipendente, non va rigenerato dal codice sotto test. Nessun test di questo documento autorizza invii o mutazioni reali.

| ID | Scenario | Risultato atteso |
|---|---|---|
| V1-01 | Corpus v1 valido, inclusi other senza nuove specifiche | Identici normalizzato, accettazione e significato v1 |
| V1-02 | Corpus v1 invalido; unknown, tipi, date, consensi | Stessi errori/field/HTTP/priorità certificati |
| V1-03 | Stesso UUID/snapshot v1 completato | Stessi report/evento, duplicate200; nessun aggiornamento versione |
| V1-04 | V1 failed retry con snapshot e senza snapshot legacy | Comportamento same-version certificato; nessun backfill retroattivo |
| V1-05 | Golden Path v1 | Moderazione/stati e privacy invariati |
| V1-06 | Lettura #35/#312 | pending, animal_missing, dog, danni not_applicable conservati; nessun salvataggio |
| V1-07 | Media v1 e retry pinned/committed | Capability/ownership/hash/fence invariati |
| V1-08 | No-media v1 assente/[]; factory mock che fallisce se chiamata | Golden Path senza costruzione/readiness/lookup media; stesso comportamento baseline |
| V1-09 | Nuova key v2 con marker v1 | unknown_field; non v2 inferred |
| V2-01..33 | Tutti i 33 casi dell'oracolo congelato | Ciascuno verifica tutti gli 85 field states, requiredness, condizioni, summary e payload; schema marker selezionato v2 secondo A |
| V2-34 | Otto Specifica, ciascuna: other→testo→altra risposta | Obbligo soltanto attivo, summary Altro:testo, clear/strip quando inattivo |
| V2-35 | Otto Specifica: vuoto/spazi/1/max/max+1, non-stringa, markup, controlli | Boundary Unicode corretto, nessun truncate/coercizione, error path esatto |
| V2-36 | Beni rubati multiselezione con/senza other | Una specifica pertinente; altri valori preservati; nessun riordino inventato |
| V2-37 | Danni 2/14/17 e relative enum override | Required/optional/hidden come matrice; not_applicable non ammesso nei nuovi campi attivi v2; niente risposta fittizia |
| V2-38 | Cambio subtype/category, reward lost→found, autorità yes→no, tempo exact→unknown | Figli e nipoti inattivi rimossi da UI state A/summary/payload/storage; invarianti adattatori B rispettate |
| V2-39 | Campo noto inattivo errato + unknown nello stesso dominio | Unknown rifiutato prima dello strip; noto inattivo da solo stripped |
| V2-40 | Padre attivo invalido/mancante | Errore padre/required; impossibile usare inattività per accettarlo |
| V2-41 | Payload con key order diverso e stessi attivi | Stesso snapshot deterministico/hash; nessuna differenza per ordine object grezzo |
| V2-42 | Prima/dopo stale strip; poi modifica campo attivo | Primo retry identico; seconda variazione conflitto |
| V2-43 | Retry v2 in COMPLETED/FAILED_RETRYABLE/IN_PROGRESS | Idempotenza, recupero same-snapshot o in-progress, nessun duplicato |
| V2-44 | No-media v2 assente/[] e media factory indisponibile | Nessuna dipendenza dal servizio media; distinzione di encoding non falsificata per hash |
| V2-45 | Media v2 pin/commit/receipt/ownership | Stessi descrittori/limiti; snapshot versione coerente dall'adapter al ledger/repository; fence attivo |
| V2-46 | Serializzazione privata e response pubblica | Otto nuovi campi nel canonical autorizzato; nessuna esposizione raw in API pubblica/evento/Discovery |
| V2-47 | Avvisi E1 nove sottotipi ed E0 yes/unsure | Rendering contestuale senza nuove key canoniche o promessa di soccorso |
| X-01 | V1 e v2 con UUID differenti | Coesistenza senza migrazione/reinterpretazione |
| X-02 | Stesso UUID v1→v2 e v2→v1 dopo riserva/completamento | 409 duplicate_submission; versione/dati originari immutati |
| X-03 | Due richieste concorrenti di versioni diverse | Una sola riserva vincolata; l'altra busy409 poi conflict409; nessun doppio evento |
| X-04 | Crash prima/dopo riserva atomica e prima/dopo persistence | Nessuna riserva v2 senza marker/snapshot; recupero same-version; nessuna appropriazione cross-version |
| X-05 | Legacy native reservation vuota + tentativo v2 | Conflitto; recovery legacy v1 invariato; nessuna versione inventata |
| X-06 | Pin media senza report, poi retry cross-version no-media e inverso | Binding registry condiviso impedisce collisione; nessun lookup media nel ramo no-media e nessun re-pin |
| X-07 | Outer/canonical version mismatch, marker unknown, snapshot corrotto | Fail-closed workflow, diagnosi privata; nessun downgrade |
| X-08 | Admin e moderazione v1/v2/legacy | Reader corretto, assenze normali, controlli permessi/escaping e nessun cambiamento storico |
| X-09 | Location e decisione GEO identiche v1/v2 su exact/area/territory-only | Proiezione/public_geo equivalenti e certificati, nessun cambio contratto |
| X-10 | B3 e fence media con marker v2, v1 e provenienza corrotta | Pubblica solo approvato quando consentito; non bypassa fence o privacy |
| X-11 | Preferenza Social presente/assente v1/v2 | Stesso significato/versione Social; nessuna pubblicazione |
| X-12 | Rollback UI, snapshot v2 aperto e record v2 persistito | Backend dual-stack continua a leggerli/servirne i retry; nuovi form v1 leciti |
| X-13 | Collisione digest con UUID completo diverso (harness) | Rifiuto conflitto, non stesso report per falsa equality |
| X-14 | Golden Path v2 normalizzato serializzato e riletto | Versione preservata su envelope/canonical, nessun default semantico reiniettato |

## Implementation impact — elenco operativo futuro

CORE sicuramente interessati: class-badaround-report-schema.php, class-badaround-report-validator.php, class-badaround-report-normalizer.php, class-badaround-native-report-intake-service.php, class-badaround-native-report-rest-controller.php, class-badaround-report-result.php, class-badaround-report-idempotency-service.php, class-badaround-report-repository.php, class-badaround-native-report-golden-path-service.php, class-badaround-moderation-admin.php. Nuovo registry/ruleset v2 e relative registrazioni bootstrap saranno necessari; nomi file da scegliere nell'implementazione, senza rinominare gli otto percorsi.

CORE da adeguare/verificare per compatibilità senza nuova policy: class-badaround-report-persistence-service.php, class-badaround-native-media-persistence.php (include adapter), class-badaround-native-media-service.php per coordinazione binding al pin, class-badaround-native-media-fence.php. Audit di tutte le chiamate a VERSION/normalizer/validator necessario prima del cut-over. Nessuna modifica prevista a installer/schema DB, public-geo policy, tassonomia, Social o SEO.

THEME: inc/native-report.php (schemaVersion v2/ruleset), assets/js/native-report/model.js (snapshot/versione e matrice), wizard.js (UI semantica/summary), errors.js (nuovi path), template-parts/native-report/step.php e shell.php solo dove necessari ai campi/notice. api.js conserva endpoint/header/envelope; verificare che lo snapshot locked non cambi versione. Nessun intervento Home o responsive generale. Questi sono soltanto componenti attesi, non autorizzazione a implementarli.

## Required final report / decisione

VERSIONING: V1 IMMUTABLE=YES; V2 EXPLICIT=YES; NATIVE FUTURE VERSION=badaround-report/v2; API ACCEPTS=v1+v2.

PERSISTENCE: DB SCHEMA CHANGE=NO; VERSION STORED=content_original.schema_version e content_original.canonical.schema_version, coerenti; AUTO MIGRATION=NO.

COMPATIBILITY: V1 HISTORICAL READ=PASS BY DESIGN; V1 INTAKE COMPATIBILITY=PRESERVED; V2 INTAKE=DEFINED; CROSS-VERSION UUID POLICY=PAYLOAD CONFLICT dopo binding, nessuna riscrittura/version switch; transient busy mantiene submission_in_progress.

CONTRACT IMPACT: GEO-PUBLIC CHANGE=NO; MEDIA CHANGE=NO; SOCIAL CHANGE=NO; SEO CHANGE=NO; TAXONOMY CHANGE=NO. Le dipendenze di riconoscimento/dispatch/binding media sono documentate e dovranno essere testate, non sono modifiche di policy o formati media.

DB MIGRATION=NONE. NEXT IMPLEMENTATION SEQUENCE=backend/readers dual-stack → v1 regression → v2/cross-version tests → staging backend autorizzato → UI v2 → QA reale v2/B3 solo autorizzata → v1 e consumer regression → verifica rollback. Nessuna produzione senza autorizzazione.

**NF-UX-02A COMPLETE — REPORT V2 COMPATIBILITY CONTRACT READY**

Nessun engine o codice applicativo scritto. Nessun deploy, DB/tassonomia/GEO modificato. NF-UX-03/NF-UX-04 non avviati. Il contratto semantico frozen e la fixture storica restano intatti.
