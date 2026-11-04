#!/usr/bin/env python3
"""Offline smoke tests for the read-only Identity SHA256 inventory."""
from __future__ import annotations
import importlib.util
import json
import os
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location(
    "inventory", ROOT / "tools/ba_identity_readonly_inventory.py"
)
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)

class FakeFTP:
    last = None
    missing = set()
    changed = {}
    def __init__(self, timeout):
        self.read_paths = []
        self.connected = False
        self.closed = False
        FakeFTP.last = self
    def connect(self, host, port):
        self.connected = True
    def login(self, user, password):
        pass
    def prot_p(self):
        pass
    def retrbinary(self, command, writer):
        assert command.startswith("RETR "), "Only read commands permitted"
        path = command[len("RETR "):]
        self.read_paths.append(path)
        if path in self.missing:
            import ftplib
            raise ftplib.error_perm("550 No such file")
        if path.endswith("/badaround-core.php"):
            data = b"<?php /* Plugin Name: BadAround Core */"
        elif "/identity-dormant/" in path and path.endswith(".php"):
            data = b"<?php // fixture"
        else:
            raise AssertionError("Unexpected remote read: " + path)
        writer(self.changed.get(path, data))
    def quit(self):
        self.closed = True

class InventoryTests(unittest.TestCase):
    def test_fetch_digest(self):
        self.assertEqual(module.digest(b"abc"), __import__("hashlib").sha256(b"abc").hexdigest())
        self.assertEqual(module.fetch(FakeFTP(5), "/httpdocs/wp-content/plugins/badaround-core/identity-dormant/demo.php"), b"<?php // fixture")

    def test_sandboxed_inventory(self):
        with tempfile.TemporaryDirectory() as td:
            root = Path(td)
            local_plugin = root / "wordpress/plugins/badaround-core"
            proto = root / "prototype-source/prototypes/ba-identity-01c"
            local_plugin.mkdir(parents=True)
            proto.mkdir(parents=True)
            (local_plugin / "badaround-core.php").write_bytes(b"<?php /* Plugin Name: BadAround Core */")
            (proto / "demo.php").write_bytes(b"<?php // fixture")
            # Provide sufficient fixtures to satisfy minimum candidate count
            for i in range(44):
                (proto / f"extra-{i}.php").write_bytes(b"<?php // fixture")
            with patch.object(module, "ROOT", root), patch.object(module, "LOCAL_PLUGIN", local_plugin), \
                 patch.object(module, "PROTOTYPE", proto), patch.object(module.ftplib, "FTP_TLS", FakeFTP), \
                 patch.dict(os.environ, {"FTP_HOST":"unit.test", "FTP_USER":"fake", "FTP_PASSWORD":"fake"}):
                module.main()
                self.assertTrue(FakeFTP.last.closed)
                self.assertIn(module.LIVE_PLUGIN + "/badaround-core.php", FakeFTP.last.read_paths)
                self.assertEqual(len(FakeFTP.last.read_paths), 47)
                result = json.loads((root / "identity-reconciliation-inventory.json").read_text())
                self.assertEqual(result["matches"], 46)
                self.assertEqual(result["mismatches"], [])
                self.assertEqual(result["mode"], "read-only")

    def test_missing_remote_file_does_not_count_as_match(self):
        target = module.LIVE_PLUGIN + "/identity-dormant/missing.php"
        FakeFTP.missing = {target}
        try:
            self.assertIsNone(module.fetch(FakeFTP(5), target))
        finally:
            FakeFTP.missing = set()

    def test_missing_prototype_fails_closed(self):
        with tempfile.TemporaryDirectory() as td:
            root = Path(td)
            local_plugin = root / "wordpress/plugins/badaround-core"
            local_plugin.mkdir(parents=True)
            (local_plugin / "badaround-core.php").write_bytes(b"<?php")
            with patch.object(module, "ROOT", root), patch.object(module, "LOCAL_PLUGIN", local_plugin), \
                 patch.object(module, "PROTOTYPE", root / "absent"), \
                 patch.object(module.ftplib, "FTP_TLS", FakeFTP), \
                 patch.dict(os.environ, {"FTP_HOST":"unit.test", "FTP_USER":"fake", "FTP_PASSWORD":"fake"}):
                with self.assertRaisesRegex(RuntimeError, "prototype checkout missing"):
                    module.main()
                self.assertTrue(FakeFTP.last.closed)

if __name__ == "__main__":
    unittest.main()
