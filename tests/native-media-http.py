"""Real multipart/PHP upload verification on synthetic loopback fixtures only."""
import base64,json,os,pathlib,secrets,socket,subprocess,tempfile,time,urllib.request,urllib.error,uuid
repo=pathlib.Path(__file__).resolve().parent.parent
checks=0

def check(ok,label):
 global checks
 checks+=1
 if not ok:raise AssertionError(label)
 print('PASS:',label)

with tempfile.TemporaryDirectory(prefix='ba-media-http-') as temp:
 root=pathlib.Path(temp)
 for name in ('private','public','php-temp'):(root/name).mkdir(mode=0o700)
 runtime=json.loads(subprocess.check_output(['php','-r',"echo json_encode([PHP_BINARY,ini_get('extension_dir')]);"],text=True))
 env=dict(os.environ,BA_TEST_DSN='sqlite:'+str(root/'db.sqlite'),BA_TEST_HTTP_REUSE='1',BA_TEST_PRIVATE_ROOT=str(root/'private'),BA_TEST_PUBLIC_ROOT=str(root/'public'),BA_DECODER_PHP=runtime[0],BA_DECODER_GD=runtime[1]+'/gd.so',BA_DECODER_POSIX=runtime[1]+'/posix.so')
 with socket.socket() as s:s.bind(('127.0.0.1',0));port=s.getsockname()[1]
 process=subprocess.Popen(['php','-d','upload_max_filesize=5M','-d','post_max_size=6M','-d','max_file_uploads=2','-d','upload_tmp_dir='+str(root/'php-temp'),'-S',f'127.0.0.1:{port}',str(repo/'tests/native-media-http-router.php')],cwd=repo,env=env,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
 origin='http://127.0.0.1:'+str(port)
 marker={'X-BadAround-Media':'badaround-report-media/v1','Origin':'https://staging.badaround.it','Sec-Fetch-Site':'same-origin'}
 def call(path,body=None,headers=None,method='GET'):
  req=urllib.request.Request(origin+'/badaround/v1'+path,data=body,headers={**marker,**(headers or {})},method=method)
  try:r=urllib.request.urlopen(req,timeout=15)
  except urllib.error.HTTPError as e:r=e
  data=r.read();return r.status,json.loads(data),dict(r.headers)
 try:
  for attempt in range(100):
   try:
    with socket.create_connection(('127.0.0.1',port),timeout=.1):break
   except OSError:
    if process.poll() is not None:raise RuntimeError('PHP fixture server failed')
    time.sleep(.02)
  submission=str(uuid.uuid4());nonce=base64.urlsafe_b64encode(secrets.token_bytes(32)).decode().rstrip('=')
  body=json.dumps({'submission_id':submission,'creation_nonce':nonce}).encode()
  status,data,headers=call('/report-media-sessions',body,{'Content-Type':'application/json'},'POST')
  check(status==201,'HTTP session create on anonymous synthetic request')
  session=data['media_session_id'];cap=data['capability']
  check(not any(k.lower().startswith('access-control-allow') for k in headers),'native HTTP response removes wildcard/reflected CORS')
  check(headers['Cache-Control']=='no-store, private' and headers['X-Content-Type-Options']=='nosniff','private HTTP transport headers')
  png=subprocess.check_output(['php','-r',"$i=imagecreatetruecolor(2,2);imagepng($i);"],env=env)
  def upload(blob,key,name='fixture.png',capability=cap,extras=False):
   boundary='ba'+secrets.token_hex(10)
   part=lambda x:x.encode()
   body=part('--'+boundary+'\r\nContent-Disposition: form-data; name="client_upload_id"\r\n\r\n'+key+'\r\n')
   body+=part('--'+boundary+'\r\nContent-Disposition: form-data; name="file"; filename="'+name+'"\r\nContent-Type: application/octet-stream\r\n\r\n')+blob+b'\r\n'
   if extras:body+=part('--'+boundary+'\r\nContent-Disposition: form-data; name="url"\r\n\r\nhttps://example.invalid/image.png\r\n')
   body+=part('--'+boundary+'--\r\n')
   return call('/report-media-sessions/'+session+'/items',body,{'Content-Type':'multipart/form-data; boundary='+boundary,'X-BadAround-Media-Capability':capability},'POST')
  key=str(uuid.uuid4());status,item,_=upload(png,key)
  check(status==201 and item['descriptor']['mime_type']=='image/png' and item['descriptor']['file_size']==len(png),'real PHP multipart accepts sniffed image despite spoofed browser MIME')
  descriptor=item['descriptor'];media=descriptor['media_id']
  check(len(list((root/'private').rglob('*.bin')))==1 and not list((root/'public').rglob('*.bin')),'one physically private accepted upload, zero public original')
  status,replay,_=upload(png,key);check(status==200 and replay['duplicate'] and replay['descriptor']==descriptor,'HTTP upload retry retains one UUID and original')
  status,changed,_=upload(png+b'changed',key);check(status==409 and changed['error']['code']=='media_manifest_conflict','HTTP same-key changed bytes conflict')
  status,_,_=upload(png,str(uuid.uuid4()),capability='wrong');check(status==401,'HTTP upload rejects wrong ownership capability')
  status,_,_=upload(png,str(uuid.uuid4()),extras=True);check(status==422,'HTTP upload rejects client remote URL field')
  status,_,_=upload(b'',str(uuid.uuid4()));check(status==422,'HTTP zero-byte image rejected')
  status,_,_=upload(b'x'*(5242881),str(uuid.uuid4()));check(status==413,'PHP and application reject 5 MiB plus one')
  item_path='/report-media-sessions/'+session+'/items/'+media
  status,state,_=call(item_path,headers={'X-BadAround-Media-Capability':cap});check(status==200 and state['items'][0]['descriptor']==descriptor,'HTTP per-item owner status')
  encoded=json.dumps(state);check(str(root) not in encoded and cap not in encoded and 'http' not in encoded,'HTTP status exposes no path URL bearer or original bytes')
  status,_,_=call(item_path,headers={'X-BadAround-Media-Capability':cap},method='DELETE');check(status==200 and not list((root/'private').rglob('*.bin')),'HTTP removal deletes original before quota release')
  status,_,_=call(item_path,headers={'X-BadAround-Media-Capability':cap},method='DELETE');check(status==200,'HTTP removal replay is idempotent')
  check(not list((root/'php-temp').iterdir()),'PHP multipart temporary files cleaned after requests')
 finally:
  process.terminate()
  try:process.wait(timeout=5)
  except subprocess.TimeoutExpired:process.kill();process.wait()
print('F1.7B HTTP multipart:',checks,'assertions PASS')
