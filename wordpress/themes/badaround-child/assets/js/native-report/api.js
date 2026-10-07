(function (root) {
  'use strict';
  const ns = root.BadAroundNative = root.BadAroundNative || {};
  ns.send = async function (config, payload, fetcher = root.fetch.bind(root), origin = root.location.origin) {
    const url = new URL(config.endpoint, origin);
    if (url.origin !== origin) return {ok:false, error:{code:'cross_origin_config', retryable:false}};
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 30000);
    try {
      const response = await fetcher(url.href, {
        method:'POST', mode:'same-origin', credentials:'omit', cache:'no-store', signal:controller.signal,
        headers:{'Content-Type':'application/json', [config.markerHeader]:config.markerValue},
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
  if (typeof module !== 'undefined' && module.exports) module.exports = ns.send;
})(typeof window !== 'undefined' ? window : globalThis);
