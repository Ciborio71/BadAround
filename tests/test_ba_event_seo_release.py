import importlib.util,hashlib,io,ftplib
from pathlib import Path
from unittest import TestCase,main
from unittest.mock import patch
ROOT=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('seo',ROOT/'tools/ba_event_seo_release.py');seo=importlib.util.module_from_spec(spec);spec.loader.exec_module(seo)
class FTP:
    def __init__(self,files,fail=None):self.files=files.copy();self.fail=fail
    def retrbinary(self,cmd,write):
        path=cmd[5:]
        if path not in self.files:raise ftplib.error_perm('550 missing')
        write(self.files[path])
    def storbinary(self,cmd,buf):self.files[cmd[5:]]=buf.read()
    def sendcmd(self,cmd):return '250 unix.mode=0644;'
    def rename(self,src,dst):
        if dst==self.fail:self.fail=None;raise RuntimeError('Injected failure')
        self.files[dst]=self.files.pop(src)
    def delete(self,path):del self.files[path]
class ReleaseTests(TestCase):
    def setUp(self):
        self.base='/httpdocs/wp-content/'
        self.prior={n:(None if i==0 else b'prior'+str(i).encode()) for i,n in enumerate(seo.FILES)}
        self.new={n:b'new'+str(i).encode() for i,n in enumerate(seo.FILES)}
        self.files={self.base+n:v for n,v in self.prior.items() if v is not None};self.files[self.base+seo.CORE]=b'core'
        self.hashpatch=patch.object(seo.hashlib,'sha256',side_effect=lambda data: type('Digest',(),{'hexdigest':lambda _: '18cb9ad21a63e08449eb85b7cb9233846bb02e2c472d05f118cd7d5764b7d175'})())
    def test_success(self):
        ftp=FTP(self.files)
        with self.hashpatch:self.assertEqual(seo.release(ftp,self.prior,self.new,'123')['result'],'PASS')
        for n,v in self.new.items():self.assertEqual(ftp.files[self.base+n],v)
    def test_drift_stops_before_write(self):
        ftp=FTP(self.files);ftp.files[self.base+seo.FILES[2]]=b'drift';before=ftp.files.copy()
        with self.hashpatch,self.assertRaises(RuntimeError):seo.release(ftp,self.prior,self.new,'123')
        self.assertEqual(before,ftp.files)
    def test_failed_swap_rolls_back_dependencies(self):
        ftp=FTP(self.files,self.base+seo.FILES[2])
        with self.hashpatch,self.assertRaises(RuntimeError):seo.release(ftp,self.prior,self.new,'123')
        for n,v in self.prior.items():self.assertEqual(ftp.files.get(self.base+n),v)
if __name__=='__main__':main()
