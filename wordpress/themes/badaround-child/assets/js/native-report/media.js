(function (root) {
  'use strict';
  const ns = root.BadAroundNative = root.BadAroundNative || {};
  const MAX_FILES = 5, MAX_BYTES = 5242880;
  const TYPES = {jpg:'image/jpeg', jpeg:'image/jpeg', png:'image/png', webp:'image/webp'};
  const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;
  const prePin = new Set(['media_reference_invalid','media_descriptor_mismatch','media_manifest_invalid']);
  const rejectedBytes = new Set(['media_file_too_large','media_type_unsupported','media_image_invalid']);
  const fault = code => Object.assign(new Error('Media operation failed'), {code});
  function uuid(crypto) {
    if (crypto.randomUUID) return crypto.randomUUID();
    const b = crypto.getRandomValues(new Uint8Array(16)); b[6]=(b[6]&15)|64; b[8]=(b[8]&63)|128;
    const h=Array.from(b,x=>x.toString(16).padStart(2,'0')).join('');
    return `${h.slice(0,8)}-${h.slice(8,12)}-${h.slice(12,16)}-${h.slice(16,20)}-${h.slice(20)}`;
  }
  function freeze(value) { if (value && typeof value === 'object') { Object.values(value).forEach(freeze); Object.freeze(value); } return value; }
  function descriptor(d) {
    return d && Object.keys(d).sort().join(',') === 'extension,file_size,media_id,mime_type'
      && UUID.test(d.media_id) && TYPES[d.extension] === d.mime_type && Number.isSafeInteger(d.file_size) && d.file_size>0 && d.file_size<=MAX_BYTES;
  }
  // All URLs are built from a fixed same-origin route and server-issued UUIDs.
  class MediaTransport {
    constructor(config, deps = {}) {
      this.origin=deps.origin || root.location.origin;
      this.base=new URL(config.endpoint,this.origin);
      if (this.base.origin!==this.origin || this.base.protocol!=='https:' || this.base.search || this.base.hash) throw fault('cross_origin_config');
      this.fetch=deps.fetch || root.fetch.bind(root); this.xhr=deps.xhr || (()=>new root.XMLHttpRequest());
      this.form=deps.form || (()=>new root.FormData());
    }
    url(session, media) {
      if (session && !UUID.test(session) || media && !UUID.test(media)) throw fault('media_reference_invalid');
      return this.base.href.replace(/\/$/,'') + (session ? '/'+session : '') + (media ? '/items/'+media : '');
    }
    headers(capability) { return {'X-BadAround-Media':'badaround-report-media/v1', ...(capability ? {'X-BadAround-Media-Capability':capability} : {})}; }
    async json(method, url, capability, body) {
      const controller=new AbortController(), timer=setTimeout(()=>controller.abort(),30000);
      try {
        const response=await this.fetch(url,{method,mode:'same-origin',credentials:'omit',redirect:'error',cache:'no-store',signal:controller.signal,
          headers:{...this.headers(capability), ...(body ? {'Content-Type':'application/json'} : {})}, ...(body ? {body:JSON.stringify(body)} : {})});
        let data; try { data=await response.json(); } catch (_) { throw fault('invalid_response'); }
        if (!response.ok || data.status!=='success') throw fault(typeof data.error?.code==='string' ? data.error.code : 'invalid_response');
        return data;
      } catch (error) { throw fault(error.code || 'network_error'); } finally { clearTimeout(timer); }
    }
    create(submission, nonce) { return this.json('POST',this.url(),null,{submission_id:submission,creation_nonce:nonce}); }
    status(session, capability, media) { return this.json('GET',this.url(session,media),capability); }
    remove(session, capability, media) { return this.json('DELETE',this.url(session,media),capability); }
    upload(session, capability, key, file, progress) {
      return new Promise((resolve,reject)=>{
        const xhr=this.xhr(), url=this.url(session)+'/items', form=this.form();
        form.append('file',file); form.append('client_upload_id',key);
        xhr.open('POST',url,true); xhr.withCredentials=false; xhr.timeout=60000;
        Object.entries(this.headers(capability)).forEach(([name,value])=>xhr.setRequestHeader(name,value));
        xhr.upload.onprogress=event=>progress(event.lengthComputable && event.total>0 ? Math.min(100,Math.floor(event.loaded/event.total*100)) : null);
        xhr.onload=()=>{
          let data; try { data=JSON.parse(xhr.responseText); } catch (_) { return reject(fault('invalid_response')); }
          if (xhr.responseURL && xhr.responseURL!==url) return reject(fault('invalid_response'));
          if (xhr.status<200 || xhr.status>=300 || data.status!=='success') return reject(fault(typeof data.error?.code==='string' ? data.error.code : 'invalid_response'));
          resolve(data);
        };
        xhr.onerror=xhr.ontimeout=xhr.onabort=()=>reject(fault('network_error'));
        xhr.send(form);
      });
    }
  }
  class Media {
    #nonce=null; #session=null; #capability=null; #creating=null; #rows=[]; #locked=false; #disposed=false; #uncertain=false;
    constructor(config, submission, deps = {}) {
      this.submission=submission; this.crypto=deps.crypto || root.crypto; this.urls=deps.urls || root.URL;
      this.transport=deps.transport || new MediaTransport(config,deps); this.changed=deps.changed || (()=>{}); this.supported=null;
    }
    get items() { return this.#rows.filter(r=>r.state!=='removed').map(r=>({id:r.id,name:r.file.name,size:r.file.size,preview:r.preview,state:r.state,progress:r.progress,error:r.error,descriptor:r.descriptor ? {...r.descriptor} : null})); }
    get locked() { return this.#locked; }
    get count() { return this.items.length; }
    descriptors() {
      const seen=new Set(); return this.#rows.filter(r=>r.state==='accepted').flatMap(r=>{
        if (seen.has(r.descriptor.media_id)) return []; seen.add(r.descriptor.media_id); return [{...r.descriptor}];
      });
    }
    errors() { return this.items.filter(r=>r.state!=='accepted' || r.error).map(r=>({field:'media.availability',file:r.id,code:r.error || 'media_upload_pending'})); }
    notify(action) { if (!this.#disposed) this.changed(action); }
    check(file) {
      const ext=file.name.split('.').pop().toLowerCase(), mime=TYPES[ext];
      if (file.size<=0) return 'media_image_invalid';
      if (file.size>MAX_BYTES) return 'media_file_too_large';
      if (!mime || file.type && file.type!==mime || this.supported && !this.supported.includes(mime.slice(6))) return 'media_type_unsupported';
      return null;
    }
    select(files) {
      if (this.#locked || this.#disposed) return [];
      const errors=[];
      for (const file of files) {
        const code=this.count>=MAX_FILES ? 'media_count_limit' : this.check(file);
        if (code) { errors.push({field:'media.availability',code,name:file.name}); continue; }
        this.#rows.push({id:uuid(this.crypto),file,preview:this.urls.createObjectURL(file),state:'selected',progress:null,error:null,descriptor:null,attempted:false,unknown:false,working:false});
      }
      this.notify('selected'); this.pump(); return errors;
    }
    async session() {
      if (this.#session) return;
      if (this.#creating) return this.#creating;
      if (!this.#nonce) this.#nonce=root.btoa(String.fromCharCode(...this.crypto.getRandomValues(new Uint8Array(32)))).replace(/\+/g,'-').replace(/\//g,'_').replace(/=+$/,'');
      this.#creating=(async()=>{
        const s=await this.transport.create(this.submission,this.#nonce);
        const codecs=Array.isArray(s.supported_codecs) ? s.supported_codecs.filter(c=>['jpeg','png','webp'].includes(c)) : [];
        if (!UUID.test(s.media_session_id) || typeof s.capability!=='string' || !/^[A-Za-z0-9_-]{43}$/.test(s.capability)
          || !Number.isSafeInteger(s.upload_expires_at) || !Number.isSafeInteger(s.commit_expires_at) || s.commit_expires_at<s.upload_expires_at
          || s.limits?.max_items!==MAX_FILES || s.limits?.max_bytes_per_item!==MAX_BYTES || !codecs.length) throw fault('invalid_response');
        if (this.#disposed) throw fault('network_error');
        this.#session=s.media_session_id; this.#capability=s.capability; this.supported=codecs; this.notify('session');
      })();
      try { await this.#creating; } finally { this.#creating=null; }
    }
    async receive(row) {
      await this.session();
      if (this.#disposed) throw fault('network_error');
      const invalid=this.check(row.file); if (invalid) throw fault(invalid);
      row.attempted=true; row.unknown=true;
      const reply=await this.transport.upload(this.#session,this.#capability,row.id,row.file,p=>{row.progress=p;this.notify('progress');});
      if (!descriptor(reply.descriptor) || reply.state!=='accepted_quarantined' || !this.supported.includes(reply.descriptor.mime_type.slice(6))
        || reply.descriptor.file_size!==row.file.size || reply.descriptor.mime_type!==TYPES[row.file.name.split('.').pop().toLowerCase()]) throw fault('media_descriptor_mismatch');
      row.descriptor=freeze({...reply.descriptor}); row.unknown=false; return reply;
    }
    async pump() {
      if (this.#locked || this.#disposed || this.#rows.some(r=>r.working)) return;
      const row=this.#rows.find(r=>r.state==='selected'); if (!row) return;
      const uncertainBefore=row.unknown;
      row.working=true; row.state='uploading'; row.error=null; row.progress=null; this.notify('uploading');
      try { await this.receive(row); row.state='accepted'; }
      catch (error) { row.state='failed'; row.error=error.code || 'network_error'; if (rejectedBytes.has(row.error) && !uncertainBefore) row.unknown=false; }
      finally { row.working=false; this.notify(row.state); if (!this.#disposed) this.pump(); }
    }
    retry(id) {
      const row=this.#rows.find(r=>r.id===id);
      if (this.#locked || this.#disposed || !row || row.state!=='failed' || row.working) return;
      row.state='selected'; row.error=null; this.notify('selected'); this.pump();
    }
    async remove(id) {
      const row=this.#rows.find(r=>r.id===id);
      if (this.#locked || this.#disposed || !row || row.state==='removed' || row.working || this.#rows.some(r=>r.working)) return;
      const previous=row.state, group=row.descriptor ? this.#rows.filter(r=>r.state!=='removed' && r.descriptor?.media_id===row.descriptor.media_id) : [row];
      group.forEach(r=>{r.state='removing';r.working=true;r.error=null;}); this.notify('removing');
      try {
        // A lost upload response may already have accepted bytes. Replay the same key before deletion.
        if (!row.descriptor && row.unknown) await this.receive(row);
        if (row.descriptor) {
          this.#rows.filter(r=>r.state!=='removed' && r.descriptor?.media_id===row.descriptor.media_id && !group.includes(r)).forEach(r=>{
            group.push(r);r.state='removing';r.working=true;r.error=null;
          });this.notify('removing');
          const reply=await this.transport.remove(this.#session,this.#capability,row.descriptor.media_id);
          if (reply.state!=='removed' || reply.media_id!==row.descriptor.media_id) throw fault('invalid_response');
        }
        group.forEach(r=>{r.state='removed';r.descriptor=null;this.urls.revokeObjectURL(r.preview);r.preview=null;});
      } catch (error) { group.forEach(r=>{r.state=r.descriptor ? 'accepted' : previous;r.error=error.code || 'network_error';}); }
      finally { group.forEach(r=>{r.working=false;}); this.notify(row.state==='removed' ? 'removed' : 'remove_failed'); this.pump(); }
    }
    freeze() { if (this.errors().length) throw fault('media_upload_pending'); this.#locked=true; this.notify('locked'); return freeze(this.descriptors()); }
    reportCapability() { return this.descriptors().length ? this.#capability : null; }
    markUncertain() { this.#uncertain=true; }
    async editable(error, frozenValidation=false) {
      if (this.#uncertain || !(prePin.has(error.code) || frozenValidation)) return false;
      try { const status=await this.transport.status(this.#session,this.#capability); return status.state==='open'; } catch (_) { return false; }
    }
    unlock() { if (!this.#uncertain) { this.#locked=false; this.notify('unlocked'); } }
    dispose() {
      this.#disposed=true; this.#rows.forEach(r=>{if(r.preview)this.urls.revokeObjectURL(r.preview);r.preview=null;});
      this.#capability=null; this.#session=null; this.#nonce=null; this.#rows=[];
    }
  }
  ns.Media=Media; ns.MediaTransport=MediaTransport; ns.freezeMediaPayload=freeze;
  if (typeof module!=='undefined' && module.exports) module.exports={Media,MediaTransport,descriptor,MAX_BYTES};
})(typeof window!=='undefined' ? window : globalThis);
