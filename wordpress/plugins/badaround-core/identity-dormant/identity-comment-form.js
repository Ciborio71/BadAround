/* BA-IDENTITY-02F: private QA comment intent, never public publish. */
(() => {
  "use strict";
  const base = "/wp-json/badaround/v1/";
  for (const root of document.querySelectorAll("[data-ba-comment-form]")) {
    const eventId = Number(root.getAttribute("data-event-id"));
    const form = root.querySelector("form");
    const status = root.querySelector("[data-ba-comment-status]");
    if (!Number.isSafeInteger(eventId) || eventId < 1 || !form || !status) continue;
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
            event_id: eventId, body, display_mode: "anonymous"
          })
        });
        const result = await request.json().catch(() => ({}));
        if (!request.ok || result.status !== "pending_moderation") {
          throw new Error("moderation_not_accepted");
        }
        form.reset();
        status.textContent = "Commento ricevuto: sarà valutato prima della pubblicazione.";
      } catch {
        status.textContent = "Per commentare devi accedere al tuo account. Riprova dopo l'accesso.";
      } finally {
        if (button) button.disabled = false;
      }
    });
  }
})();
