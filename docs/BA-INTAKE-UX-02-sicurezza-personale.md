# BA-INTAKE-UX-02 — Estensione Pericoli: sicurezza personale

Stato: proposta funzionale consolidata, **non ancora implementata**.
Data: 2026-11-02.
Contesto: BadAround WordPress; 6 categorie, attualmente 35 sottotipi in produzione. Obiettivo concordato: rimuovere il duplicato «Furto del veicolo» (ID 7) a favore di «Veicolo rubato» (ID 11), senza migrare eventi dimostrativi destinati a reset.

## Regola tassonomica

Categoria padre esistente: **Pericoli** (term ID 13, slug `pericoli`). Nessuna settima categoria.
Quattro nuovi sottotipi proposti, ciascuno distinto per modalità di episodio, non per esito:

1. **Borseggio o furto con destrezza** — sottrazione senza violenza diretta, ad esempio su mezzi pubblici, in stazione o luogo affollato.
2. **Scippo** — sottrazione improvvisa di un oggetto dalla persona.
3. **Rapina o tentata rapina** — episodio con minaccia o violenza finalizzato alla sottrazione.
4. **Aggressione o minaccia** — aggressione fisica, minacce o intimidazioni osservate, senza presupporre un furto.

**Tentativo vs episodio consumato** è un campo strutturato, NON un sottotipo duplicato. Per tutte le voci: `esito_episodio = consumato | tentato | non_so`. Per aggressione/minaccia usare `tipo_condotta = aggressione_fisica | minaccia_intimidazione | altro` e `esito_episodio` solo se semanticamente appropriato.

## Matrice minima per il wizard

| Sottotipo | Core richiesto oltre ai dati comuni | Arricchimento facoltativo |
|---|---|---|
| Borseggio | contesto (strada/stazione/mezzo/altro), esito, oggetto coinvolto o non noto, descrizione concreta | linea e direzione mezzo, affollamento, ulteriori circostanze |
| Scippo | esito, oggetto coinvolto o non noto, contesto, descrizione concreta | eventuali danni, dinamica, testimoni |
| Rapina | esito, presenza di minaccia/violenza riferita, contesto, descrizione concreta | informazioni verificabili non identificative |
| Aggressione/minaccia | condotta osservata, contesto, descrizione concreta | conseguenze riferite senza dati sanitari di terzi, testimoni |

**Campi comuni**: categoria, sottotipo, localizzazione (punto/strada/zona/territorio), tempo (istante/intervallo/data/periodo), descrizione, titolo suggerito modificabile. Ruolo contestuale: `coinvolto | testimone | altre_informazioni`, senza identificazione automatica di vittima o sospettati. Fotografie facoltative incentivate, con protezione dei dati personali.

L'uso di valori «non so» deve essere possibile per gli elementi non conosciuti da un testimone. Non inventare coordinate, data/ora né dettagli su persone.

## Esperienza e microcopy

- Primo passaggio: sei categorie esistenti; in Pericoli, scelta del sottotipo.
- Mostrare domanda su esito tentato/consumato solo quando applicabile; evitare doppia richiesta del ruolo.
- Contesto proposto: strada/piazza, metro/stazione, autobus/tram, negozio/mercato, altro.
- Avviso contestuale per pericolo attuale: «Se c'è un pericolo immediato, chiama il 112. BadAround non attiva i soccorsi.»
- Evitare etichette «zona pericolosa», «pickpocket presenti» e attribuzioni a persone o gruppi.
- Le informazioni inviate entrano in moderazione; pubblicazione mai automatica.

## Privacy, moderazione e Radar

- Conservare il modello attuale `ba_reports` / `ba_evento`, validazione, Smart Address, media privati, audit e Publication Service.
- Nessuna pubblicazione di nomi/volti identificativi, contatti, documenti, o accuse riferite a individui. Il moderatore verifica la pubblicabilità e distingue segnalazione moderata da fatti supportati da riscontri.
- Posizione pubblica soggetta alla decisione del moderatore; per episodi personali, evitare la rivelazione di vittime e percorsi.
- Radar raggruppa per sottotipo ed esito, finestra temporale e granularità appropriata, senza trasformare frequenza di segnalazione in probabilità o indice oggettivo di criminalità. Non fissare soglie H3/KDE senza validazione.

## Compatibilità e numeri

- Produzione verificata: 6 categorie e 35 sottotipi.
- Dopo pulizia dell'ID 7: 34 sottotipi.
- Con **4** sottotipi nuovi: **38** sottotipi, **44** termini inclusi i 6 genitori (previsti; non ancora creati).
- Non cambiare gli ID o gli slug attuali; nuovi termini con nuovi ID solo dopo approvazione della matrice.
- Tutti gli eventi attualmente presenti sono dati dimostrativi da resettare prima del lancio; nessuna migrazione degli eventi pending è necessaria, ma le dipendenze codice/tassonomia restano da verificare.

## Criteri di accettazione

1. Borseggio tentato e consumato condividono lo stesso sottotipo ma sono distinguibili nel payload.
2. Scippo, rapina e aggressione restano distinguibili; niente categorie spurie.
3. Ruolo e informazioni sulla persona non sono raccolti oltre il necessario.
4. Un luogo impreciso o un intervallo temporale non è convertito in falsa precisione.
5. Tutto l'invio passa per Core e moderazione; nessun nuovo modello SQL concorrente.
6. Radar usa i nuovi dati in modo aggregato, senza inferenze allarmistiche.
7. Nessuna modifica a produzione fino all'audit degli schemi di validazione e al preflight del deploy.
