'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),os=require('node:os'),path=require('node:path'),net=require('node:net');
const {spawn,execFileSync}=require('node:child_process');
const {Media,MediaTransport}=require('../wordpress/themes/badaround-child/assets/js/native-report/media.js'),send=require('../wordpress/themes/badaround-child/assets/js/native-report/api.js');
const tick=()=>new Promise(r=>setTimeout(r,20));
test('production frontend -> real PHP multipart -> frozen reports/binding -> exact completed retry',async()=>{
 const php=process.env.BA_PHP || 'php',repo=path.dirname(__dirname),root=fs.mkdtempSync(path.join(os.tmpdir(),'ba-f17d-http-'));
 for(const name of ['private','public','php-temp'])fs.mkdirSync(path.join(root,name),{mode:0o700});
 const runtime=JSON.parse(execFileSync(php,['-r','echo json_encode([PHP_BINARY,ini_get("extension_dir")]);'],{encoding:'utf8'}));
 const env={...process.env,BA_TEST_DSN:'sqlite:'+path.join(root,'fixture.sqlite'),BA_TEST_HTTP_REUSE:'1',BA_TEST_PRIVATE_ROOT:path.join(root,'private'),BA_TEST_PUBLIC_ROOT:path.join(root,'public'),BA_TEST_HTTP_STATE:path.join(root,'synthetic-state.json'),BA_DECODER_PHP:runtime[0],BA_DECODER_GD:runtime[1]+'/gd.so',BA_DECODER_POSIX:runtime[1]+'/posix.so'};
 const port=await new Promise(resolve=>{const socket=net.createServer();socket.listen(0,'127.0.0.1',()=>{const port=socket.address().port;socket.close(()=>resolve(port));});});
 const server=spawn(php,['-d','upload_max_filesize=5M','-d','post_max_size=6M','-d','upload_tmp_dir='+path.join(root,'php-temp'),'-S',`127.0.0.1:${port}`,'tests/native-media-f17d-http-router.php'],{cwd:repo,env,stdio:'ignore'});
 const origin='https://staging.badaround.it',records=[];
 // Loopback bridge models HTTPS/origin termination using the existing synthetic PHP harness.
 // It is test-only and does not certify TLS, hosting routing or browser cookies.
 async function bridge(url,opts){records.push({url,opts});return fetch(url.replace(origin+'/wp-json',`http://127.0.0.1:${port}`),{...opts,headers:{...opts.headers,Origin:origin,'Sec-Fetch-Site':'same-origin'}});}
 class XHR {
  constructor(){this.upload={};this.headers={};}
  open(method,url){this.method=method;this.url=url;}
  setRequestHeader(name,value){this.headers[name]=value;}
  async send(body){try{const response=await bridge(this.url,{method:this.method,headers:this.headers,body});this.status=response.status;this.responseURL=this.url;this.responseText=await response.text();this.onload();}catch(_){this.onerror();}}
 }
 let media;
 try {
  for(let i=0;i<100;i++){try{await fetch(`http://127.0.0.1:${port}/ready`);break;}catch(_){await tick();}}
  const payload=JSON.parse(execFileSync(php,['-r','ob_start();require "tests/native-report-f13.php";ob_end_clean();echo json_encode(f13_fixture());'],{cwd:repo,encoding:'utf8'}));
  const png=execFileSync(php,['-r','$im=imagecreatetruecolor(2,2);imagepng($im);']);
  const file=new File([png],'synthetic.png',{type:'image/png'});
  const transport=new MediaTransport({endpoint:origin+'/wp-json/badaround/v1/report-media-sessions'},{origin,fetch:bridge,xhr:()=>new XHR()});
  media=new Media({},payload.submission_id,{transport,urls:{createObjectURL:()=> 'blob:synthetic-only',revokeObjectURL:()=>{}}});
  assert.equal(records.length,0);media.select([file]);
  for(let i=0;i<150&&media.items[0].state!=='accepted'&&media.items[0].state!=='failed';i++)await tick();
  assert.equal(media.items[0].state,'accepted');assert.equal(records[1].opts.body.get('client_upload_id'),media.items[0].id);
  assert(!records[1].opts.headers['Content-Type']); // Browser/FormData supplies its actual multipart boundary.
  payload.media={availability:'yes',items:media.freeze()};
  const config={endpoint:origin+'/wp-json/badaround/v1/reports',markerHeader:'X-BadAround-Intake',markerValue:'badaround-report/v1',schemaVersion:payload.schema_version};
  const capability=media.reportCapability();const first=await send(config,payload,bridge,origin,capability);assert(first.ok);assert(!first.duplicate);
  const again=await send(config,payload,bridge,origin,capability);assert(again.ok&&again.duplicate);
  const reports=records.filter(r=>r.url.endsWith('/reports'));assert.equal(reports[0].opts.body,reports[1].opts.body);assert(!reports[0].opts.body.includes(capability));assert.equal(reports[0].opts.headers['X-BadAround-Media-Capability'],capability);
  const status=await transport.status(records[1].url.split('/').at(-2),capability);assert.equal(status.state,'committed');assert.equal(status.items.length,1);assert.equal(status.items[0].state,'bound_pending_review');
  const posts=JSON.parse(fs.readFileSync(env.BA_TEST_HTTP_STATE,'utf8'));assert.equal(Object.keys(posts).length,1);assert.equal(Object.values(posts)[0].post_status,'pending');
  const stats=JSON.parse(execFileSync(php,['-r','$d=new PDO(getenv("BA_TEST_DSN"));echo json_encode([$d->query("SELECT COUNT(*) FROM test_ba_reports")->fetchColumn(),$d->query("SELECT COUNT(*) FROM test_ba_report_media")->fetchColumn(),$d->query("SELECT SUM(public_attachment_id IS NOT NULL) FROM test_ba_report_media")->fetchColumn()]);'],{env,encoding:'utf8'}));
  assert.deepEqual(stats.map(Number),[1,1,0]);assert.deepEqual(fs.readdirSync(path.join(root,'php-temp')),[]);
 } finally {media?.dispose();server.kill('SIGTERM');await new Promise(r=>server.exitCode!==null?r():server.once('exit',r));fs.rmSync(root,{recursive:true,force:true});}
});
