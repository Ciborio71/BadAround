# Native media live preflight — 2026-11-06
User acceptance found `media_service_unavailable` before any accepted image. Admin-only live readiness observations:
- configuration exists; dedicated active key valid; private root valid and matches legacy inspector archive.
- enabled=false and hosting_verified=false. Existing admission safety gates correctly deny upload.
- upload_max_filesize=2M, post_max_size=8M; declared per-image limit is 5 MiB.
- proc_open and proc_terminate available.
- decoder paths unconfigured; supported_codecs=[].
- PHP FPM 8.3 uses /opt/plesk/php/8.3; extension_dir=/opt/plesk/php/8.3/lib64/php/modules.
- open_basedir restricts filesystem to subscription root and /tmp. is_file probes outside allowed roots return false; this does NOT prove system binaries are absent.
No configuration activation, secret replacement, image upload, data reset, or public opening performed.

Administrative observation endpoint added at /badaround-ops/v1/media-readiness, manage_options only, no-store, no key values or image content. The first release raced the historical diagnostics workflow; concurrent drift gate stopped it. The same candidate had been installed by historical workflow. Follow-up disabled its automatic overlapping deployment and installed the next diagnostic candidate through guarded release run 42256519081, job128025050313: PHP syntax and guarded backup/swap/readback PASS.

Hosting intervention required: set upload_max_filesize >=5 MiB (8M recommended), post_max_size with multipart overhead (16M recommended); make the trusted Plesk PHP CLI/GD/POSIX runtime inspectable by the constrained worker without removing existing directory restrictions. Then verify exact non-writable runtime paths, codec round trips, storage HTTP denial, table constraints, capacity and scheduler before changing hosting_verified/enabled. End-to-end upload remains BLOCKED, not certified. User should keep current wizard open; retry after actual service readiness.
