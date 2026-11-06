/* BA-IDENTITY-02F: private QA comment intent, never public publish. */
(() => {
  "use strict";
  const base = "/wp-json/badaround/v1/";
  for (const root of document.querySelectorAll("[data-ba-comment-form]")) {
    const eventId = Number(root.getAttribute("data-event-id"));
    const form = root.querySelector("form");
    const status = root.querySelector("[data-ba-comment-status]");
    if (!Number.isSafeInteger(eventId) || eventId < 1 || !form || !status) continue;
    const mode = form.querySelector('[name="display_mode"]');
    const alias = form.querySelector('[name="alias"]');
    const aliasField = form.querySelector('[data-ba-alias-field]');
    const parent = form.querySelector('[name="parent_id"]');
    const replyStatus = form.querySelector('[data-ba-reply-status]');
    const cancelReply = form.querySelector('[data-ba-cancel-reply]');
    const syncAlias = () => {
      const active = mode?.value === 'pseudonym';
      if (aliasField) aliasField.hidden = !active;
      if (alias) { alias.required = active; alias.disabled = !active; }
    };
    mode?.addEventListener('change',syncAlias);syncAlias();
    const clearReply = () => {
      if (parent) parent.value='';
      if (replyStatus) replyStatus.textContent='';
      if (cancelReply) cancelReply.hidden=true;
    };
    cancelReply?.addEventListener('click',clearReply);
    for (const button of root.querySelectorAll('[data-ba-reply]')) button.addEventListener('click',() => {
      const id=button.getAttribute('data-ba-reply');
      if (!parent || !/^[a-f0-9]{32}$/.test(id || '')) return;
      parent.value=id;
      if (replyStatus) replyStatus.textContent='Stai rispondendo al commento selezionato.';
      if (cancelReply) cancelReply.hidden=false;
      form.querySelector('textarea')?.focus();
    });
    const revision = root.querySelector('[data-ba-revision-form]');
    const revisionStatus = root.querySelector('[data-ba-revision-status]');
    if (revision && revisionStatus) revision.addEventListener('submit', async e => {
      e.preventDefault();
      if (!revision.reportValidity()) return;
      const fields = new FormData(revision);
      const button = revision.querySelector('button[type="submit"]');
      if (button?.disabled) return;
      if (button) button.disabled = true;
      try {
        const response = await fetch(base + 'identity/session', {
          method:'POST', credentials:'same-origin', mode:'same-origin', cache:'no-store',
          headers:{'Content-Type':'application/json'}, body:'{}'
        });
        const session = await response.json();
        if (!response.ok || session.authenticated !== true || typeof session.csrf !== 'string') throw new Error();
        const result = await fetch(base + 'community-revisions', {
          method:'POST',credentials:'same-origin',mode:'same-origin',cache:'no-store',
          headers:{'Content-Type':'application/json','X-Badaround-CSRF':session.csrf},
          body:JSON.stringify({event_id:eventId,description:String(fields.get('description') || '').trim(),reason:String(fields.get('reason') || '').trim()})
        });
        const receipt = await result.json().catch(() => ({}));
        if (!result.ok || receipt.status !== 'pending_moderation') throw new Error();
        revision.reset();
        revisionStatus.textContent = 'Proposta ricevuta: la segnalazione pubblica rimane invariata fino alla moderazione.';
      } catch {
        revisionStatus.textContent = 'Modifica non confermata. Accedi con l’account che ha creato la segnalazione e riprova più tardi.';
      } finally { if (button) button.disabled = false; }
    });
    form.addEventListener("submit", async e => {
      e.preventDefault();
      if (!form.reportValidity()) return;
      const body = String(new FormData(form).get("body") || "").trim();
      if (body.length < 3 || body.length > 2500) return;
      const button = form.querySelector('button[type="submit"]');
      if (button?.disabled) return;
      if (button) button.disabled = true;
      try {
        const sessionResponse = await fetch(base + "identity/session", {
          method: "POST", credentials: "same-origin", mode: "same-origin",
          cache: "no-store", headers: {"Content-Type":"application/json"}, body: "{}"
        });
        if (!sessionResponse.ok) throw new Error("unauthenticated");
        const session = await sessionResponse.json();
        if (session.authenticated !== true || typeof session.csrf !== "string") {
          throw new Error("unauthenticated");
        }
        const request = await fetch(base + "community-comments", {
          method: "POST", credentials: "same-origin", mode: "same-origin",
          cache: "no-store",
          headers: {
            "Content-Type": "application/json",
            "X-Badaround-CSRF": session.csrf
          },
          body: JSON.stringify({
            event_id: eventId, body, display_mode: mode?.value || "anonymous",
            alias: mode?.value === 'pseudonym' ? String(alias?.value || '').trim() : '',
            ...(parent?.value ? {parent_id:parent.value} : {})
          })
        });
        const result = await request.json().catch(() => ({}));
        if (!request.ok || result.status !== "pending_moderation") {
          throw new Error("moderation_not_accepted");
        }
        form.reset();
        syncAlias();clearReply();
        status.textContent = "Commento ricevuto: sarà valutato prima della pubblicazione.";
      } catch {
        status.textContent = "Per commentare devi accedere al tuo account. Riprova dopo l'accesso.";
      } finally {
        if (button) button.disabled = false;
      }
    });
  }
})();
