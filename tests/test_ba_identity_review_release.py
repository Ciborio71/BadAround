import importlib.util
import hashlib
import tempfile
from pathlib import Path
from unittest import TestCase, main
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('review_release', ROOT/'tools/ba_identity_review_release.py')
release = importlib.util.module_from_spec(spec)
spec.loader.exec_module(release)
fixture_spec = importlib.util.spec_from_file_location('release_fixture', ROOT/'tests/test_ba_identity_creator_grant_release.py')
fixture = importlib.util.module_from_spec(fixture_spec)
fixture_spec.loader.exec_module(fixture)

class FTP(fixture.FakeFTP):
    def connect(self,*args): pass
    def login(self,*args): pass
    def prot_p(self): pass
    def quit(self): pass
    def close(self): pass
    def sendcmd(self,cmd): return '250 unix.mode=0644; source'
    def rename(self,source,target):
        if target == self.fail_target:
            self.fail_target = None
            raise RuntimeError('Injected atomic rename failure')
        self.files[target] = self.files.pop(source)

class ReviewReleaseTests(TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.root = Path(self.temp.name)
        prior_names = [p.name for p in (ROOT/release.guard.MODULES).iterdir()
                       if p.suffix in ('.php','.js') and p.name not in {'wordpress-community-review.php','unified-access.php','wordpress-access-rest.php','identity-access.js'}]
        self.assertEqual(len(prior_names),85)
        self.prior = {name: b'old-module-'+name.encode() for name in prior_names}
        self.candidate = {name:b'new-module-'+name.encode() for name in release.guard.FILES}
        for name,data in self.prior.items():
            path = self.root/'prior-source'/release.guard.MODULES/name
            path.parent.mkdir(parents=True,exist_ok=True);path.write_bytes(data)
        for name,data in self.candidate.items():
            path = self.root/release.guard.MODULES/name
            path.parent.mkdir(parents=True,exist_ok=True);path.write_bytes(data)
        self.old = b'previous-publication-service';self.new = b'candidate-publication-service'
        for prefix,data in [('prior-source',self.old),('',self.new)]:
            path = self.root/prefix/release.PUBLICATION
            path.parent.mkdir(parents=True,exist_ok=True);path.write_bytes(data)
        self.core = b'frozen-core'
        self.ftp = FTP({release.guard.CORE:self.core,release.LIVE_PUBLICATION:self.old,
                        **{release.guard.BASE+n:v for n,v in self.prior.items()}})
        self.initial = dict(self.ftp.files)
    def tearDown(self): self.temp.cleanup()
    def run_release(self):
        with patch.object(release,'ROOT',self.root), patch.object(release.guard,'CORE_SHA',hashlib.sha256(self.core).hexdigest()), \
             patch.object(release.ssl,'create_default_context',return_value=object()), \
             patch.object(release.ftplib,'FTP_TLS',return_value=self.ftp), \
             patch.dict(release.os.environ,{'GITHUB_RUN_ID':'123','FTP_TLS_CA_FILE':'fixture','FTP_USER':'fixture','FTP_PASSWORD':'fixture'}):
            release.main()
    def test_publication_drift_stops_before_any_write(self):
        self.ftp.files[release.LIVE_PUBLICATION] = b'concurrent-change'
        before = dict(self.ftp.files)
        with self.assertRaises(RuntimeError):self.run_release()
        self.assertEqual(self.ftp.files,before)
    def test_failed_module_swap_restores_publication_and_every_module(self):
        self.ftp.fail_target = release.guard.BASE+'community-gateway.php'
        with self.assertRaises(RuntimeError):self.run_release()
        for path,data in self.initial.items():self.assertEqual(self.ftp.files[path],data)
        self.assertNotIn(release.guard.BASE+'wordpress-community-review.php',self.ftp.files)
    def test_complete_candidate_retains_backups_and_frozen_core(self):
        self.run_release()
        self.assertEqual(self.ftp.files[release.guard.CORE],self.core)
        self.assertEqual(self.ftp.files[release.LIVE_PUBLICATION],self.new)
        self.assertEqual(self.ftp.files['/wordpress-backups/ba-identity-review-123.prior'],self.old)
        for name,data in self.candidate.items():self.assertEqual(self.ftp.files[release.guard.BASE+name],data)

if __name__=='__main__':main()
