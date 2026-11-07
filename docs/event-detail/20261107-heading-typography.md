# Event H1 typography — 7 November 2026

Display-only rule for event H1: spaced dash separators become ` | `; meaningful words start with capitals while Italian articles, conjunctions and prepositions stay lowercase. Acronyms, model codes, mixed-case brand names and established L’Aquila spelling are preserved. Model-internal hyphens are unchanged.

Expected Honda example: `Honda SH, Colore Bianco | Veicolo Segnalato Rubato a Roma`.

The formatter operates only on H1 rendering. Stored post titles, breadcrumbs, SEO metadata, structured data, original reports and frozen URLs are not rewritten. Existing witness anchor, dates, comment card, gallery and GEO remain unchanged.

Six PHP typography cases cover the exact user example, minor words, model hyphens, apostrophes, accents and mixed-case names. PHP lint, typography fixtures, public-details/GEO regression fixtures and three guarded-release unit tests must pass before upload.

Source candidate: 0b21875b2388e7b9b0ca8eda7d1018610a92efb3. Release run42371317869, job128399856717. Guarded deployment compares the prior source, preserves the frozen Core entrypoint, backs up files privately and verifies remote bytes.

Verified: release PASS; Identity functional run42371317694 PASS. Live authenticated event395 H1 exactly matches the requested example. Breadcrumbs and public details snapshot compare unchanged against the pre-release render; witness anchor and comment card remain present. This is server-render verification, not responsive screenshot certification.
