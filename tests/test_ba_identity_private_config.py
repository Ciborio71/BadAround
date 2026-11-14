import importlib.util
from pathlib import Path
import unittest
from unittest.mock import patch
import tempfile
import shutil
import json
import os
from test_ba_identity_creator_grant_release import FakeFTP

ROOT=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('config_release',ROOT/'tools/ba_identity_private_config_release.py')
config=importlib.util.module_from_spec(spec);spec.loader.exec_module(config)

class ConfigTests(unittest.TestCase):
    def setUp(self):
        self.original=b"<?php\ndefine('DB_NAME', 'synthetic');\n$table_prefix='ci_';\ndefine('ABSPATH', __DIR__ . '/');\nrequire_once ABSPATH . 'wp-settings.php';\n"
        self.template=(ROOT/'tools/templates/identity-private-config.php.in').read_text()
    def test_insert_before_wordpress_and_preserve_all_original_bytes(self):
        new=config.patch_config(self.original)
        self.assertEqual(new.replace(config.INCLUDE.encode(),b''),self.original)
        self.assertLess(new.index(config.MARKER.encode()),new.index(b'require_once ABSPATH'))
    def test_existing_include_not_duplicated(self):
        with self.assertRaises(RuntimeError):config.patch_config(config.patch_config(self.original))
    def test_existing_activation_not_overridden(self):
        old=self.original.replace(b"$table_prefix",b"define('BADAROUND_IDENTITY_PRIVATE_QA_ENABLED', false);\n$table_prefix")
        with self.assertRaises(RuntimeError):config.patch_config(old)
    def test_ambiguous_bootstrap_rejected(self):
        with self.assertRaises(RuntimeError):config.patch_config(self.original+b"require ABSPATH.'wp-settings.php';")
    def test_unrecognized_bootstrap_rejected(self):
        with self.assertRaises(RuntimeError):config.patch_config(b'<?php echo 1;')
    def test_template_accepts_only_strong_hex_key(self):
        source=config.private_source(self.template,'a'*96)
        self.assertNotIn(b'__GENERATED_ROOT_SECRET__',source)
        self.assertIn(b"$_SERVER['REMOTE_ADDR']",source)
        self.assertNotIn(b'HTTP_X_FORWARDED_FOR',source)
        self.assertNotIn(b'BADAROUND_IDENTITY_PRIVACY_APPROVED_VERSION',source)
    def test_weak_or_injected_key_rejected(self):
        for key in ['a'*32,"x'; echo 1;",'g'*96]:
            with self.assertRaises(RuntimeError):config.private_source(self.template,key)
    def test_template_preserves_existing_key(self):
        source=config.private_source(self.template,'a'*96).decode()
        self.assertIn("if (defined('BADAROUND_IDENTITY_ROOT_SECRET'))",source)
        self.assertIn("if (!is_string($peer) || !in_array($peer,$ips,true))return false;",source)

class ConfigFTP(FakeFTP):
    def __init__(self,files):
        super().__init__(files)
        self.commands=[]
        self.config_swaps=0
        self.fail_verification=False
    def connect(self,*args):pass
    def login(self,*args):pass
    def prot_p(self):pass
    def quit(self):pass
    def close(self):pass
    def sendcmd(self,cmd):
        self.commands.append(cmd)
        if cmd.startswith('MLST '):return '250-Listing\n type=file;unix.mode=0644; wp-config.php\n250 End'
        return '200 OK'
    def rename(self,source,target):
        if target==self.fail_target:
            self.fail_target=None
            raise RuntimeError('Injected atomic rename refusal')
        self.files[target]=self.files.pop(source)
        if target==config.CONFIG:self.config_swaps+=1
    def retrbinary(self,cmd,callback):
        if cmd[5:]==config.CONFIG and self.config_swaps==1 and self.fail_verification:
            self.fail_verification=False
            raise RuntimeError('Injected post-install verification failure')
        return super().retrbinary(cmd,callback)

class ConfigInstallationTests(unittest.TestCase):
    def setUp(self):
        self.original=b"<?php\ndefine('DB_NAME','synthetic');\n$table_prefix='ci_';\ndefine('ABSPATH', __DIR__.'/');\nrequire_once ABSPATH.'wp-settings.php';\n"
        self.ftp=ConfigFTP({config.CONFIG:self.original,
            '/httpdocs/wp-content/plugins/badaround-core/badaround-core.php':
                (ROOT/'wordpress/plugins/badaround-core/badaround-core.php').read_bytes()})
        self.temp=tempfile.TemporaryDirectory()
        self.root=Path(self.temp.name)
        (self.root/'tools/templates').mkdir(parents=True)
        for name in ['ba_identity_creator_grant_release.py','templates/identity-private-config.php.in']:
            shutil.copyfile(ROOT/'tools'/name,self.root/'tools'/name)
    def tearDown(self):self.temp.cleanup()
    def invoke(self):
        with patch.object(config,'ROOT',self.root),patch.object(config,'lint'),\
            patch.object(config.ssl,'create_default_context',return_value=None),\
            patch.object(config.ftplib,'FTP_TLS',return_value=self.ftp),\
            patch.dict(os.environ,{'GITHUB_RUN_ID':'123','FTP_HOST':'test','FTP_USER':'test',
                'FTP_PASSWORD':'synthetic','FTP_TLS_CA_FILE':'synthetic'}):
            config.main()
    def test_install_has_private_backup_and_original_config_permissions(self):
        self.invoke()
        self.assertEqual(self.ftp.files['/wordpress-backups/ba-identity-config-123.prior'],self.original)
        self.assertIn(config.MARKER.encode(),self.ftp.files[config.CONFIG])
        self.assertIn('SITE CHMOD 600 /wordpress-backups/ba-identity-private-123.part',self.ftp.commands)
        self.assertIn('SITE CHMOD 0644 /wordpress-backups/ba-identity-config-123.part',self.ftp.commands)
        evidence=json.loads((self.root/'private-config-evidence.json').read_text())
        self.assertFalse(evidence['keys_logged_or_exported'])
    def test_refused_atomic_swap_keeps_original_config(self):
        self.ftp.fail_target=config.CONFIG
        with self.assertRaises(RuntimeError):self.invoke()
        self.assertEqual(self.ftp.files[config.CONFIG],self.original)
        self.assertNotIn(config.MARKER.encode(),self.ftp.files[config.CONFIG])
    def test_post_install_failure_atomically_restores_original_and_permissions(self):
        self.ftp.fail_verification=True
        with self.assertRaises(RuntimeError):self.invoke()
        self.assertEqual(self.ftp.files[config.CONFIG],self.original)
        self.assertIn('SITE CHMOD 0644 /wordpress-backups/ba-identity-config-123.prior',self.ftp.commands)
        self.assertEqual(self.ftp.config_swaps,2)
    def test_existing_private_configuration_is_never_overwritten(self):
        self.ftp.files[config.PRIVATE]=b'<?php existing private config'
        before=dict(self.ftp.files)
        with self.assertRaises(RuntimeError):self.invoke()
        self.assertEqual(self.ftp.files,before)

if __name__=='__main__':unittest.main()
