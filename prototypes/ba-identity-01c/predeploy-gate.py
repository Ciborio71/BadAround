#!/usr/bin/env python3
"""Fail-closed pre-deployment gate for BA-IDENTITY-01.

This script intentionally does NOT deploy, migrate, or use secrets.
It checks the proposed GitHub diff against the production-sensitive
baseline and refuses declaring a prototype branch release-ready.
"""
import os
import pathlib
import subprocess
import sys

BASE = os.environ.get("BA_PREFLIGHT_BASE", "origin/main")
ROOT = pathlib.Path(__file__).resolve().parents[2]
REQUIRED = (
    "prototypes/ba-identity-01c/community-gateway.php",
    "prototypes/ba-identity-01c/wordpress-authority.php",
    "prototypes/ba-identity-01c/mysql-wp-actor-binding.php",
    "prototypes/ba-identity-01c/mysql-moderation-inbox.php",
    "docs/BA-IDENTITY-01E-live-schema-migration-release-gate.md",
)
SAFE_PREFIXES = ("prototypes/ba-identity-01c/", "docs/BA-IDENTITY-")
SAFE_EXACT = (".github/workflows/ba-identity-01c-tests.yml",
              ".github/workflows/ba-identity-predeploy-gate.yml")

def main():
    missing = [str(path) for path in REQUIRED if not (ROOT / path).is_file()]
    if missing:
        print("FAIL: Required inputs absent:", ", ".join(missing))
        return 2
    try:
        out = subprocess.check_output(
            ["git", "diff", "--name-only", f"{BASE}...HEAD"],
            cwd=ROOT, text=True, stderr=subprocess.STDOUT
        )
    except subprocess.CalledProcessError as exc:
        print("FAIL: Cannot establish Git diff baseline:", exc.output.strip())
        return 2
    changed = [line.strip() for line in out.splitlines() if line.strip()]
    unsafe = [path for path in changed
              if not path.startswith(SAFE_PREFIXES) and path not in SAFE_EXACT]
    if unsafe:
        print("FAIL: Changes touch runtime or unrelated files:", ", ".join(unsafe))
        return 2
    if not changed:
        print("FAIL: No change set identified")
        return 2
    print(f"PASS: {len(changed)} changed files limited to dormant prototype/docs/CI.")
    print("HOLD: Not a deployable WordPress Core package.")
    print("HOLD: Live Core byte parity and production rollback not certified. Staging bypass approved.")
    print("HOLD: Schema migration, outbox, API integration, audit, browser QA and rollback not certified.")
    print("HOLD: Full-plugin FTPS workflow MUST NOT be used for Identity; use scoped production patch only after runtime gates.")
    return 0

if __name__ == "__main__":
    sys.exit(main())
