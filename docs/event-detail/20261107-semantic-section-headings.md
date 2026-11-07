# Descriptive event section headings — 7 November 2026

## Scope

Added a separate read-only section-heading projection based on existing public canonical event subtype, public make/model and canonical municipality. Existing flat SEO DTO, H1, metadata, frozen URL, publication logic and private reports are unchanged. Legacy pages receive descriptive headings when the public canonical fields suffice, otherwise safe generic fallbacks. No new private-data reads, inferred dates, lost/found states or unpublished localities.

Dynamic H2 labels cover photos, facts, description, location/period, event summary and comments. The existing witness CTA remains conditional on the approved seeking flag. Address, occurrence and map have H3 under the location H2; existing community H3/H4 hierarchy remains. Field labels retain definition-list semantics; form legends retain accessibility semantics.

Typography: original stylesheet bytes preserved with additive scoped H3 rules only. New H3 labels inherit family and size from their previous inline-bold context; existing H1/H2 font-size/font-family declarations untouched. Font visual equality across devices is not newly screenshot-certified.

The SEO-EVENTS-v1.1 contract now contains the rule. Query combinations are plausible content-intent hypotheses, not measured keyword volume or ranking promises. Google Search Central reference: https://developers.google.com/search/docs/fundamentals/seo-starter-guide .

## Verified live headings

Event395:
- H2 Foto della segnalazione di furto Honda SH
- H2 Caratteristiche del veicolo Honda SH
- H2 Dettagli del furto segnalato: Honda SH a Roma
- H2 Luogo e periodo del furto segnalato a Roma
- H3 Indirizzo della segnalazione a Roma
- H3 Data o periodo dell’evento segnalato a Roma
- H3 Mappa della segnalazione a Roma
- H2 Commenti sulla segnalazione di furto Honda SH a Roma
- H2 Informazioni evento: Honda SH segnalato rubato a Roma

Event381 receives corresponding Hyundai Tucson labels while its legacy H1 remains unchanged. Both verified through authenticated admin server rendering; restricted comment-form browser access remains gated.

Initial isolated tests exposed missing WordPress escaping stubs. Gallery fixture received its stub; the comment presenter uses equivalent native HTML escaping so existing standalone Identity fixtures remain valid. The first stopped release made no upload. Main release run42395409201 PASS; final comment-presenter compatibility release and Identity CI tracked separately below.

Final source658cdc7d15b3231f37be3fae4f91a72de4f52307: compatibility release run42395567324 PASS; Identity functional CI run42395567362 PASS (primitives and MySQL). Targeted heading fixtures cover all 33 canonical subtypes, missing public fields, non-inference, unchanged flat metadata contract, no mutations and dynamic empty-gallery titles. Existing public-details/GEO/community, gallery and 15 interaction/Home tests pass before release. Guarded release byte-readbacks and private prior copies verified.

Live event395 comparison: one H1, same H1 and breadcrumbs, same signed public-details snapshot. H2/H3 inventory checked on event395 and381. No reset, publication, image-service activation, email or security-gate change.
