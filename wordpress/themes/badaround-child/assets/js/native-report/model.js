(function (root) {
  'use strict';
  const ns = root.BadAroundNative = root.BadAroundNative || {};
  function uuid(crypto) {
    if (crypto.randomUUID) return crypto.randomUUID();
    const bytes = crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 15) | 64; bytes[8] = (bytes[8] & 63) | 128;
    const hex = Array.from(bytes, x => x.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0,8)}-${hex.slice(8,12)}-${hex.slice(12,16)}-${hex.slice(16,20)}-${hex.slice(20)}`;
  }
  function setPath(target, path, value) {
    const keys = path.split('.'); let current = target;
    keys.slice(0, -1).forEach(key => { current = current[key] || (current[key] = {}); });
    current[keys[keys.length - 1]] = value;
  }
  class Model {
    constructor(config, crypto = root.crypto) {
      this.config = config; this.crypto = crypto; this.reset();
    }
    reset() {
      this.submissionId = uuid(this.crypto);
      this.publicLocationRadius = null;
      this.values = {}; this.publicLocationRequest = this.config.publicLocationRequest?.default === 'approximate_area' ? 'approximate_area' : 'exact_point'; this.step = 0; this.busy = false; this.completed = false; this.snapshot = null;
      const defaults = {'location.public_precision':'area', 'reporter.public_identity_mode':'anonymous', 'reward.status':'none'};
      Object.entries(this.config.conditional ? {} : defaults).forEach(([path, value]) => {
        if (this.config.fields[path]?.constraints.enum?.includes(value)) this.values[path] = value;
      });
    }
    profile() {
      const row = this.config.conditional?.profiles[this.effective('event.subtype')];
      return row?.category === this.values['event.category'] ? row : null;
    }
    rule(path) {
      const profile=this.profile();
      if(profile)return profile.fields[path];
      // During category selection retain only rules shared by every possible profile.
      const candidates=Object.values(this.config.conditional?.profiles||{}).filter(row=>!this.values['event.category']||row.category===this.values['event.category']);
      const rule=candidates[0]?.fields[path];
      return rule&&candidates.every(row=>JSON.stringify(row.fields[path])===JSON.stringify(rule))?rule:null;
    }
    required(path) { return this.config.conditional ? this.active(path) && !!this.rule(path)?.required_when_active : !!this.config.fields[path]?.required; }
    constraints(path) { return {...this.config.fields[path]?.constraints, ...this.rule(path)?.constraints_override}; }
    matches(expr, seen) {
      if (expr.all) return expr.all.every(item => this.matches(item, seen));
      if (expr.any) return expr.any.some(item => this.matches(item, seen));
      if (expr.op === 'active') return this.active(expr.field, seen);
      if (!this.active(expr.field, seen)) return false;
      const value = this.effective(expr.field);
      if (value === undefined || value === null) return false;
      if (expr.op === 'eq') return value === expr.value;
      if (expr.op === 'in') return expr.values.includes(value);
      if (expr.op === 'contains') return Array.isArray(value) && value.includes(expr.value);
      throw new Error('Unsupported frozen condition');
    }
    pruneInactive() {
      if (!this.config.conditional || this.busy || this.snapshot || this.completed) return;
      // Fixed point also covers transitive descendants after a category/parent edit.
      let changed;
      do {
        changed = false;
        for (const path of Object.keys(this.values)) {
          if (!this.active(path) || (path === 'event.subtype' && this.effective(path) === undefined)) {
            delete this.values[path]; changed = true;
          }
        }
      } while (changed);
      // Existing private/public GEO adapter uses the frozen technical precision default.
      if(this.active('location.public_precision')&&this.values['location.public_precision']===undefined)this.values['location.public_precision']='area';
    }
    active(path, seen = new Set()) {
      if (this.config.conditional) {
        // Selection controls must be reachable before a valid profile exists.
        if (['schema_version','submission_id','event.category','event.subtype'].includes(path)) return true;
        const rule = this.rule(path);
        if (!rule || seen.has(path) || rule.state === 'HIDDEN') return false;
        const chain = new Set(seen); chain.add(path);
        return rule.state !== 'CONDITIONAL' || this.matches(rule.condition, chain);
      }
      const field = this.config.fields[path];
      if (!field || seen.has(path)) return false;
      const chain = new Set(seen); chain.add(path);
      if (!field.conditions.length) return true;
      return field.conditions.some(group => group.every(rule => {
        // A hidden parent never activates its descendants through stale values.
        if (!this.active(rule.path, chain)) return false;
        const actual = this.effective(rule.path);
        return rule.op === 'eq' ? actual === rule.value : rule.op === 'in' && rule.value.includes(actual);
      }));
    }
    effective(path) {
      const value = this.values[path];
      if (path === 'event.subtype' && !this.subtypes().includes(value)) return undefined;
      return value;
    }
    subtypes() { return Object.keys(this.config.categories[this.values['event.category']]?.subtypes || {}); }
    set(path, value) {
      if (!this.busy && !this.snapshot && !this.completed && this.config.fields[path]) {
        this.values[path] = value;
        this.pruneInactive();
      }
    }
    setPublicLocationRequest(value) {
      if (!this.busy && !this.snapshot && !this.completed && this.config.publicLocationRequest && ['exact_point', 'approximate_area'].includes(value)) {
        this.publicLocationRequest = value;
        this.publicLocationRadius = value === 'approximate_area' ? (this.publicLocationRadius ?? this.config.publicLocationRequest.defaultRadius ?? 500) : null;
      }
    }
    setPublicLocationRadius(value) {
      if (!this.busy && !this.snapshot && !this.completed && this.publicLocationRequest==='approximate_area') this.publicLocationRadius=value;
    }
    publicLocationLabel() {
      if(this.publicLocationRequest!=='approximate_area')return 'Punto esatto';
      return 'Area approssimativa — circa '+(this.publicLocationRadius===1000?'1 km':this.publicLocationRadius+' m');
    }
    payload() {
      this.pruneInactive();
      const out = {schema_version:this.config.schemaVersion, submission_id:this.submissionId};
      Object.entries(this.config.fields).forEach(([path, field]) => {
        if (!this.active(path)) return;
        let value = this.effective(path);
        if (typeof value === 'string') value = value.trim();
        if (value === undefined || value === '' || (Array.isArray(value) && !value.length)) return;
        if (field.type === 'number' || field.type === 'decimal') value = Number(value);
        if (this.config.conditional && ['email','enum','uuid'].includes(field.type) && typeof value==='string') value=value.toLowerCase();
        if (field.type === 'plate') value = String(value).toUpperCase().replace(/\s/g, '');
        setPath(out, path, value);
      });
      setPath(out, 'consents.version', this.config.consentVersion);
      // Versioned request only. Approval and public coordinates remain server-owned.
      if (this.config.publicLocationRequest) {
        const radiusAware=this.config.publicLocationRequest.version==='public-location-request-v2';
        const request=radiusAware ? {mode:this.publicLocationRequest,...(this.publicLocationRequest==='approximate_area'?{radius_m:this.publicLocationRadius}:{})} : this.publicLocationRequest;
        setPath(out, 'location.public_location_request', request);
        setPath(out, 'location.public_location_request_version', this.config.publicLocationRequest.version);
      }
      // Optional request, independent of the mandatory declaration and contact choices.
      if (['requested','declined'].includes(out.reporter?.social_visibility)) {
        setPath(out, 'consents.social_visibility_version', this.config.socialPreferenceVersion);
      }
      return out;
    }
    validate(step = null) {
      const errors = [];
      const push = (path, code) => {
        if ((!step || this.config.fields[path]?.step === step) && !errors.some(e => e.field === path)) errors.push({field:path, code});
      };
      Object.entries(this.config.fields).forEach(([path, field]) => {
        if (!this.active(path) || (step && step !== field.step)) return;
        const value = this.effective(path), c = this.constraints(path);
        const empty = value === undefined || (typeof value === 'string' && value.trim() === '') || (Array.isArray(value) && !value.length);
        if (this.required(path) && (empty || (field.type === 'bool' && value !== true))) { push(path, 'missing_required_field'); return; }
        if (empty) return;
        if (typeof value === 'string' && c.single_line && (/[\r\n\u0085\u2028\u2029]/u.test(value) || !/[^\p{Z}\p{C}\s]/u.test(value))) push(path, 'invalid_type');
        if (typeof value === 'string' && c.plain_text && (/<[^>]*>/.test(value) || /[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/u.test(value))) push(path, 'invalid_type');
        if (typeof value === 'string' && c.min_length && Array.from(value.trim()).length < c.min_length) push(path, 'value_too_short');
        if (c.enum && !c.enum.includes(value)) push(path, 'invalid_enum');
        if (c.item_enum && (!Array.isArray(value) || value.some(v => !c.item_enum.includes(v)))) push(path, 'invalid_enum');
        if (c.max_items && value.length > c.max_items) push(path, 'too_many_items');
        if (typeof value === 'string' && c.max_length && Array.from(value.trim()).length > c.max_length) push(path, 'value_too_long');
        if (field.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim())) push(path, 'invalid_email');
        if (field.type === 'phone' && !/^[0-9+().\s-]{6,64}$/.test(value.trim())) push(path, 'invalid_phone');
        if (field.type === 'plate' && !/^[A-Z0-9?]{1,32}$/.test(value.toUpperCase().replace(/\s/g,''))) push(path, 'invalid_plate');
        if (field.type === 'time' && !/^(?:[01]\d|2[0-3]):[0-5]\d$/.test(value)) push(path, 'invalid_date_time');
        if (field.type === 'date' && (!/^\d{4}-\d{2}-\d{2}$/.test(value) || Number.isNaN(Date.parse(value + 'T00:00:00Z')) || new Date(value + 'T00:00:00Z').toISOString().slice(0,10) !== value)) push(path, 'invalid_date_time');
        if (['number','decimal'].includes(field.type) && (!Number.isFinite(Number(value)) || (c.min !== undefined && Number(value) < c.min) || (c.max !== undefined && Number(value) > c.max))) push(path, 'invalid_number');
      });
      if ((!step || step==='location') && this.config.publicLocationRequest?.version==='public-location-request-v2' && this.publicLocationRequest==='approximate_area' && (!Number.isInteger(this.publicLocationRadius)||!this.config.publicLocationRequest.radii.includes(this.publicLocationRadius))) errors.push({field:'location.public_location_request.radius_m',code:'invalid_public_radius'});
      const present = path => this.active(path) && this.effective(path) !== undefined && this.effective(path) !== '';
      if (present('location.exact_lat') !== present('location.exact_lng')) push('location.exact_lat', 'invalid_location');
      if (this.active('time.range_end') && this.values['time.range_start'] && this.values['time.range_end'] < this.values['time.range_start']) push('time.range_end', 'invalid_date_time');
      // Timezone and future-time rules remain authoritative on the server.
      return errors;
    }
  }
  ns.Model = Model;
  if (typeof module !== 'undefined' && module.exports) module.exports = {Model, uuid};
})(typeof window !== 'undefined' ? window : globalThis);
