import importlib.util
import ftplib
import hashlib
from pathlib import Path
import unittest

spec = importlib.util.spec_from_file_location('release', Path(__file__).resolve().parents[1] /
    'tools/ba_identity_creator_grant_release.py')
release = importlib.util.module_from_spec(spec)
spec.loader.exec_module(release)

class FakeFTP:
    def __init__(self, files):
        self.files = dict(files)
        self.fail_target = None
    def nlst(self, base):
        return [p for p in self.files if p.startswith(base)]
    def retrbinary(self, cmd, callback):
        path = cmd[5:]
        if path not in self.files:
            raise ftplib.error_perm('550 Missing')
        callback(self.files[path])
    def storbinary(self, cmd, data):
        self.files[cmd[5:]] = data.read()
    def rename(self, source, target):
        if target == self.fail_target:
            self.fail_target = None
            raise RuntimeError('Injected rename failure')
        if target in self.files:
            raise RuntimeError('Occupied destination')
        self.files[target] = self.files.pop(source)
    def delete(self, path):
        del self.files[path]

class ReleaseTests(unittest.TestCase):
    def setUp(self):
        self.core = b'<?php baseline'
        release.CORE_SHA = hashlib.sha256(self.core).hexdigest()
        self.prior = {name: b'<?php old ' + name.encode() for name in release.FILES[1:]}
        self.candidate = {name: b'<?php new ' + name.encode() for name in release.FILES}
        self.ftp = FakeFTP({release.CORE: self.core,
            **{release.BASE+n: b for n, b in self.prior.items()}})
        self.initial = dict(self.ftp.files)
    def test_preflight_never_writes(self):
        result = release.release(self.ftp, self.prior, self.candidate, '123', False)
        self.assertEqual(result['modified'], [])
        self.assertEqual(self.ftp.files, self.initial)
    def test_byte_drift_stops_before_write(self):
        self.ftp.files[release.BASE+release.FILES[1]] = b'changed'
        before = dict(self.ftp.files)
        with self.assertRaises(RuntimeError):
            release.release(self.ftp, self.prior, self.candidate, '123', True)
        self.assertEqual(self.ftp.files, before)
    def test_unknown_module_stops_before_write(self):
        self.ftp.files[release.BASE+'unexpected.php'] = b'<?php'
        before = dict(self.ftp.files)
        with self.assertRaises(RuntimeError):
            release.release(self.ftp, self.prior, self.candidate, '123', True)
        self.assertEqual(self.ftp.files, before)
    def test_success_retains_private_originals(self):
        result = release.release(self.ftp, self.prior, self.candidate, '123', True)
        self.assertEqual(result['modified'], release.FILES)
        self.assertEqual(self.ftp.files[release.CORE], self.core)
        for name, data in self.candidate.items():
            self.assertEqual(self.ftp.files[release.BASE+name], data)
        for name, data in self.prior.items():
            self.assertEqual(self.ftp.files[release.PRIVATE+'ba-creator-123-'+name+'.prior'], data)
    def test_partial_release_restores_all_runtime_bytes(self):
        self.ftp.fail_target = release.BASE+release.FILES[2]
        with self.assertRaises(RuntimeError):
            release.release(self.ftp, self.prior, self.candidate, '123', True)
        runtime = {p:b for p,b in self.ftp.files.items() if p.startswith(release.BASE)}
        expected = {p:b for p,b in self.initial.items() if p.startswith(release.BASE)}
        self.assertEqual(runtime, expected)
        self.assertEqual(self.ftp.files[release.CORE], self.core)

if __name__ == '__main__':
    unittest.main()
