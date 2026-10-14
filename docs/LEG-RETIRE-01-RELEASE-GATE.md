# LEG-RETIRE-01 — Native-only report release gate

## Scope and baseline
- Candidate: `feature/leg-retire-01-native-only` / Draft PR #19.
- Reporting entry template: `page-segnala-evento.php` (the installed WordPress page must retain this template).
- Legacy reporting route and WPForms #6 intake hooks are retired **in this candidate**, not yet deployed.
- Do not delete WPForms form #6 data, WPForms plugin, or `wpforms_settings`; the map and Smart Address still read the Google key from those settings.
- No changes to F1.2, publication, taxonomy, discovery, or Smart Address code are permitted here.

## Deployment prerequisites — must be confirmed, not assumed
1. CI passing for PHP syntax, JS syntax, Native DOM, Smart Address, territory, and frozen-contract guards.
2. Review two-component WordPress deployment (child theme AND badaround-core). Deploy both consistently; avoid a partial rollout.
3. Verify no unexpected WordPress shortcode, menu, page, or connected integration still expects WPForms #6. WPForms plugin itself is **not** to be removed.
4. Verify Plesk/PHP `upload_max_filesize` and `post_max_size` allow the intended media policy (up to five files, 5 MiB each); prior H1 reported 2M/8M and is not a readiness sign-off.
5. Confirm Native media service readiness: `BADAROUND_NATIVE_MEDIA_CONFIG` enabled only after real private storage, key configuration, backup/retention/budget verification. No invented secrets or paths.
6. Verify filesystem deletions: FTPS upload workflows may leave removed `wpforms.css`, `report.js`, and PHP adapter files on disk. If they remain, no code must include/enqueue/execute them; physical cleanup requires a separately reviewed plan.
7. Confirm staging can be restored to its pre-deployment versions of both child theme and core, including removal or neutralization of newly uploaded files if needed.

## Staging QA after explicit release authorization
- Open `/segnala-un-evento/` and confirm **only Native** renders, with no WPForms #6 or QA-only copy.
- Check 7-step navigation, required-field validation, error summary, keyboard, 375px/mobile and desktop cards.
- Step 2: verify manual canonical territory entry and optional Smart Address selection. Verify no Google requests before activation; verify Google failure fallback and successful retry.
- Check private address, plates, exact coords, and media data never appear in public projection.
- Check Step 5 media is either (A) genuinely operational and tested against the hosting policy, or (B) visibly and consistently disabled with correct explanatory copy, without broken upload controls.
- Perform **one explicitly authorized synthetic** Native end-to-end submission, no real personal information, and verify report/event creation once, `Da moderare` state, no automatic publication or duplicate.
- Confirm Home/Mappa and published event detail still work.
- Review browser Console/Network and compare relevant desktop/mobile rendering with previously approved UI.
- After validation, record deployed SHA(s), CI run, browser evidence and rollback instructions.

## Release gate
**NO FINAL FUNCTIONAL CERTIFICATION** until hosting/media readiness and a real staging Native golden-path submission are proven. Passing unit/DOM tests or previously captured browser Smart Address evidence alone is insufficient. NO production deployment is authorized by this document.
