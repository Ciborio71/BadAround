# BadAround Core

`badaround-core` contains the application logic of BadAround.

## Responsibilities

- geography and territory management;
- event lifecycle;
- sentinel users;
- territorial alerts;
- moderation;
- maps;
- social sharing integrations;
- geolocated advertising;
- external plugin/service integrations.

## Current implementation (`0.3.0`)

- registers the approved `ba_evento` custom post type;
- registers `ba_tipo_evento` and the on-demand hierarchical `ba_territorio` taxonomy;
- replaces the native territory checklist with an asynchronous cascading selector;
- registers private-by-default `_ba_*` event and territory metadata;
- installs the versioned `ba_reports`, `ba_report_media`, and `ba_audit_log` tables using the site's charset and collation;
- supplies WPForms form ID `6` to the report page template;
- listens only to completed submissions from that form;
- creates one private report and one event with WordPress' native `pending` status, labelled **Da moderare** in wp-admin;
- prevents duplicates with a database-level unique source-entry key;
- records event creation in the audit log.

The submitted field values are deliberately not copied yet. The approved
field-ID mapper will be introduced as a separate module so no unclassified form
payload can leak into the event or WordPress REST API.

Original media will use a configurable secure storage path outside the public
Media Library. No media intake is enabled until that path and the server deny
rules have been verified on staging.
