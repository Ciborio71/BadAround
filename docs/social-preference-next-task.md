# SOCIAL-PREF-01 — Proposta pronta per il prossimo task Social

Stato: proposta da approvare; nessuna modifica a F1.2, al backend o alla Native Form social in questa patch.

Il contratto corrente non contiene un campo dedicato alla richiesta di diffusione social. Mostrare una scelta senza salvarla produrrebbe una promessa non verificabile. La precedente baseline richiede F1.2 frozen: questo ampliamento va gestito e certificato nel task Social dedicato.

## Domanda proposta

«Vuoi dare visibilità a questo evento sui canali social di BadAround?»

- «Sì, desidero che BadAround valuti la condivisione sui propri canali social.»
- «No, preferisco la pubblicazione sul sito BadAround.»

Nessuna risposta preselezionata. Preferenza facoltativa: l'assenza di risposta non autorizza la condivisione.

Microcopy: «La tua scelta sarà valutata dalla moderazione. Potranno essere condivisi solo contenuti approvati e destinati alla pubblicazione; i tuoi recapiti e le informazioni riservate resteranno esclusi.»

I canali candidati indicati dal prodotto sono Facebook, Instagram, X, Google Business Profile (GMB) e TikTok. Nel task dedicato verificare account ufficiali disponibili, supporto tecnico, idoneità dei contenuti e policy prima di presentarli come canali operativi. Definire se la scelta comprende tutti i canali abilitati o richiede selezione per canale.

## Persistenza necessaria

Leggere prima SOCIAL-01A, PRIV-GOV-02 e gli esiti approvati nelle chat di competenza. Concordare il nome del campo canonico e una nuova versione del contratto, senza riutilizzare consents.publication_rules o reporter.contact_preference. Registrare preferenza, ambito/canali autorizzati, versione del testo e riferimento temporale; prevedere modifica/revoca. La richiesta non è una decisione di moderazione e non attiva automaticamente pubblicazioni o job.

Il servizio Social consumerà esclusivamente la proiezione pubblica approvata. Vietato leggere direttamente dati riservati, coordinate esatte, targhe non autorizzate o media originali. Trattare eventuali consensi necessari come distinti dalla checkbox obbligatoria della segnalazione.

QA di accettazione: risposta sì/no/omessa persistita e riletta; assenza di preselezioni; idempotenza; revoca; blocco per segnalazione non pubblicata; zero dati riservati nella proiezione social; nessun post reale durante il task di sola acquisizione preferenza.
