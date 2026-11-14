(function (root) {
  'use strict';
  const ns = root.BadAroundNative = root.BadAroundNative || {};
  ns.send = async function (config, payload, fetcher = root.fetch.bind(root), origin = root.location.origin, mediaCapability = null) {
    const url = new URL(config.endpoint, origin);
    if (url.origin !== origin) return {ok:false, error:{code:'cross_origin_config', retryable:false}};
    if (config.identityQa === true) {
      if (url.pathname !== '/wp-json/badaround-identity-qa/v1/reports' || url.search || url.hash) {
        return {ok:false, error:{code:'cross_origin_config', retryable:false}};
      }
      return ns.sendIdentityQa(config, payload, fetcher, origin, mediaCapability);
    }
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 30000);
    try {
      const response = await fetcher(url.href, {
        method:'POST', mode:'same-origin', credentials:'omit', cache:'no-store', signal:controller.signal,
        headers:{'Content-Type':'application/json', [config.markerHeader]:config.markerValue,
          ...(payload.media?.items?.length && mediaCapability ? {'X-BadAround-Media-Capability':mediaCapability} : {})},
        body:JSON.stringify(payload)
      });
      let body;
      try { body = await response.json(); } catch (_) { return {ok:false, error:{code:'invalid_response', retryable:true}}; }
      if (response.ok && body.status === 'success' && body.submission_id === payload.submission_id && body.schema_version === config.schemaVersion && body.next_state === 'moderation_pending') {
        return {ok:true, duplicate:body.duplicate === true};
      }
      const error = body.error || {};
      return {ok:false, error:{
        code:error.code || body.code || 'invalid_response', field:typeof error.field === 'string' ? error.field : null,
        retryable:error.retryable === true || response.status >= 500 || response.status === 429 || (response.ok && body.status === 'success'),
        httpStatus:response.status
      }};
    } catch (_) {
      return {ok:false, error:{code:'network_error', retryable:true}};
    } finally { clearTimeout(timeout); }
  };
  // Same-origin cookie only. Never fall back to anonymous native submission.
  ns.sendIdentityQa = async function (config, payload, fetcher, origin, mediaCapability) {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 30000);
    try {
      const sessionResponse = await fetcher(origin + '/wp-json/badaround/v1/identity/session', {
        method:'POST', mode:'same-origin', credentials:'same-origin', cache:'no-store',
        signal:controller.signal, headers:{'Content-Type':'application/json'}, body:'{}'
      });
      const session = await sessionResponse.json().catch(() => ({}));
      if (!sessionResponse.ok || session.authenticated !== true) {
        return {ok:false,error:{code:'identity_required',retryable:true,httpStatus:401}};
      }
      const response = await fetcher(new URL(config.endpoint,origin).href, {
        method:'POST',mode:'same-origin',credentials:'same-origin',cache:'no-store',
        signal:controller.signal,
        headers:{'Content-Type':'application/json',[config.markerHeader]:config.markerValue,
          ...(payload.media?.items?.length && mediaCapability ? {'X-BadAround-Media-Capability':mediaCapability} : {})},
        body:JSON.stringify(payload)
      });
      const body = await response.json().catch(() => ({}));
      if (response.status === 201 && body.state === 'owner_confirmed') return {ok:true,duplicate:false};
      const pending = response.status === 202 && body.state === 'pending';
      return {ok:false,error:{code:pending ? 'identity_pending' : 'identity_submission_failed',
        retryable:pending || response.status >= 500 || response.status === 429 || response.status === 401,
        httpStatus:response.status}};
    } catch (_) {
      return {ok:false,error:{code:'network_error',retryable:true}};
    } finally { clearTimeout(timeout); }
  };
  if (typeof module !== 'undefined' && module.exports) module.exports = ns.send;
})(typeof window !== 'undefined' ? window : globalThis);
