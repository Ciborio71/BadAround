#!/usr/bin/env python3
"""Read Git objects only; emit the exact F1.7F future Core/Theme upload manifest."""
import hashlib,json,re,subprocess,sys
QA='d6156e236d35da062c7f444bc73442e2905db54e'
BACKEND='beb33ec3325480f77a266b3b6356f5860b00e16e'
RELEASE='bcc2c74ca3391f1d16f9b1eb9719650ea4d27952'
CORE='wordpress/plugins/badaround-core'
THEME='wordpress/themes/badaround-child'
def git(*args):return subprocess.check_output(['git',*args])
def tree(ref,path=None):return git('rev-parse',ref+(':'+path if path else '^{tree}')).decode().strip()
assert tree(QA)=='b1bd80f431a62c7492acb33887022e0e1013c09f','reviewed full tree drift'
assert tree(QA,THEME)=='f379b7acbd16956ad896824502533bf3ccbb0e53','reviewed theme drift'
assert tree(QA,CORE)==tree(BACKEND,CORE),'reviewed backend drift'
assert tree(QA,THEME)==tree('d6156e236d35da062c7f444bc73442e2905db54e',THEME),'reviewed theme drift'
for name in ['report-schema','report-normalizer','report-validator','native-report-intake-service','native-report-golden-path-service','report-persistence-service']:
 p=CORE+'/includes/class-badaround-'+name+'.php'
 assert git('rev-parse',QA+':'+p)==git('rev-parse',RELEASE+':'+p),p
for p in ['assets/js/native-report/model.js','template-parts/native-report/shell.php','page-segnala-evento.php','assets/js/report.js','assets/css/report.css']:
 assert git('rev-parse',QA+':'+THEME+'/'+p)==git('rev-parse',RELEASE+':'+THEME+'/'+p),p
core_source=git('show',QA+':'+CORE+'/badaround-core.php').decode()
installer_source=git('show',QA+':'+CORE+'/includes/class-badaround-installer.php').decode()
schema_source=git('show',QA+':'+CORE+'/includes/class-badaround-report-schema.php').decode()
assert "Version: 0.18.0" in core_source and "BADAROUND_CORE_VERSION', '0.18.0'" in core_source
assert "SCHEMA_VERSION = '1.9.0'" in installer_source
assert 'badaround-report/v1' in schema_source
files=[];native=[]
for record in git('ls-tree','-rz',QA,'--',CORE,THEME).split(b'\0'):
 if not record:continue
 meta,p=record.split(b'\t',1);mode,kind,blob=meta.decode().split();p=p.decode()
 assert kind=='blob' and mode in ['100644','100755'],p
 data=git('cat-file','blob',blob)
 component='core' if p.startswith(CORE+'/') else 'theme'
 prefix=CORE if component=='core' else THEME
 files.append({'component':component,'path':p,'deploy_relative_path':p[len(prefix)+1:],'mode':mode,'git_blob_sha':blob,'bytes':len(data),'sha256':hashlib.sha256(data).hexdigest()})
 if p.startswith(CORE+'/includes/') and ('class-badaround-native-media-' in p or p.endswith('/native-media-image-worker.php')):
  native.append(f'{mode} blob {blob}\t{p.rsplit("/",1)[1]}\n')
subset=subprocess.check_output(['git','mktree'],input=''.join(sorted(native,key=lambda x:x.split('\t')[1])).encode()).decode().strip()
manifest={'format':'badaround-f17f-deploy-manifest/v1','qa_branch':'recovery/native-media-f17-qa-rc','candidate_sha':QA,'reviewed_backend_sha':BACKEND,'reviewed_integrated_sha':QA,'release_baseline_sha':RELEASE,
 'trees':{'candidate':tree(QA),'core':tree(QA,CORE),'theme':tree(QA,THEME),'backend_includes':tree(QA,CORE+'/includes'),'native_backend_subset':subset,'native_frontend':tree(QA,THEME+'/assets/js/native-report')},
 'versions':{'core':'0.18.0','schema':'1.9.0','canonical_report':'badaround-report/v1'},
 'deployment':{'executed':False,'launcher_remediation':'required','hosting_media_enablement':'blocked','media_enabled':False,'migration_executed_on_staging':False,'source_roots':{'core':CORE,'theme':THEME},'server_dirs':{'core':'/staging.badaround.it/wp-content/plugins/badaround-core/','theme':'/staging.badaround.it/wp-content/themes/badaround-child/'},'exclusions':['tests/','docs/','.github/','private storage','wp-config.php','hosting configuration/secrets']},
 'file_counts':{c:sum(f['component']==c for f in files) for c in ['core','theme']},'files':files}
print(json.dumps(manifest,ensure_ascii=False,sort_keys=True,indent=2))
