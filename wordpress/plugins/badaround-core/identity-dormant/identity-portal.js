/* BA-IDENTITY-02C — dormant private registration/login portal.
 * Never retain OTP or bearer tokens in storage. Uses HttpOnly cookies only.
 */
(() => {
  "use strict";
  const api = "/wp-json/badaround/v1/";
  const portals = document.querySelectorAll("[data-ba-identity-portal]");
  const msg = "Operazione non disponibile. Riprova più tardi.";
  async function send(path, payload = {}) {
    const response = await fetch(api + path, {
      method: "POST",
      mode: "same-origin",
      credentials: "same-origin",
      cache: "no-store",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload)
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok || !data || data.ok === false) {
      throw new Error("request_failed");
    }
    return data;
  }
  for (const portal of portals) {
    const status = portal.querySelector("[data-ba-result]");
    const entry=portal.querySelector('[data-ba-access-entry]');
    const logout = portal.querySelector("[data-ba-logout]");
    const activity = portal.querySelector("[data-ba-activity]");
    const activityList = portal.querySelector("[data-ba-activity-list]");
    const activityRefresh = portal.querySelector("[data-ba-refresh-activity]");
    const reportsList = portal.querySelector('[data-ba-reports-list]');
    if (!status || !entry || !window.BadAroundAccess || !logout || !activity || !activityList || !activityRefresh) continue;
    let csrf = null;
    function notice(text) { status.textContent = text; }
    function showSignedIn(value) {
      entry.hidden=value;
      portal.querySelector('[data-ba-portal-title]').textContent=value?'Il mio profilo':'Accedi a BadAround';
      portal.querySelector('[data-ba-portal-intro]').hidden=value;
      window.BadAroundAccess.updateSession(value);
      logout.hidden = !value;
      activity.hidden = !value;
      if (!value) activityList.replaceChildren();
      if (!value) reportsList?.replaceChildren();
    }
    async function busy(form, task) {
      const buttons = [...form.querySelectorAll("button")];
      buttons.forEach(button => { button.disabled = true; });
      try { await task(); } catch {
        notice(msg);
      } finally {
        buttons.forEach(button => { button.disabled = false; });
      }
    }
    async function loadActivity() {
      if (!csrf) return;
      const response = await fetch(api + "identity/my-activity", {
        method: "POST",
        mode: "same-origin",
        credentials: "same-origin",
        cache: "no-store",
        headers: {
          "Content-Type": "application/json",
          "X-Badaround-CSRF": csrf
        },
        body: "{}"
      });
      if (!response.ok) throw new Error("activity_unavailable");
      const result = await response.json();
      if (!Array.isArray(result.items)) throw new Error("invalid_activity");
      const fragment = document.createDocumentFragment();
      for (const item of result.items.slice(0, 20)) {
        if (!item || !["comment", "revision"].includes(item.kind)
            || !["pending", "approved", "rejected"].includes(item.status)) continue;
        const li = document.createElement("li");
        const kind = item.kind === "comment" ? "Commento" : "Richiesta di modifica";
        const state = {pending: "In moderazione", approved: "Approvata", rejected: "Non approvata"}[item.status];
        li.textContent = kind + " — " + state;
        fragment.append(li);
      }
      activityList.replaceChildren(fragment);
      if (!fragment.childNodes.length && !activityList.childNodes.length) {
        const li = document.createElement("li");
        li.textContent = "Non ci sono richieste da mostrare.";
        activityList.append(li);
      }
      if (reportsList) await loadReports();
    }
    async function loadReports() {
      const response = await fetch(api + 'identity/my-reports',{
        method:'POST',mode:'same-origin',credentials:'same-origin',cache:'no-store',
        headers:{'Content-Type':'application/json','X-Badaround-CSRF':csrf},body:'{}'
      });
      if (!response.ok) throw new Error('reports_unavailable');
      const data=await response.json();
      if (!Array.isArray(data.items)) throw new Error('invalid_reports');
      reportsList.replaceChildren();
      for (const item of data.items.slice(0,50)) {
        if (!Number.isSafeInteger(item.event_id) || item.event_id<=0 || !['published','pending_moderation'].includes(item.status)) continue;
        const li=document.createElement('li');
        const title=document.createElement('p');
        title.textContent=String(item.title || 'Segnalazione')+' — '+(item.status==='published'?'Pubblicata':'In moderazione');
        li.append(title);
        if (item.status==='published' && typeof item.url==='string') {
          try {
            const url=new URL(item.url,location.origin);
            if (url.origin===location.origin) {const link=document.createElement('a');link.href=url.href;link.textContent='Apri segnalazione';li.append(link);}
          } catch {}
        }
        if (item.can_propose_revision===true) {
          const details=document.createElement('details'),summary=document.createElement('summary');summary.textContent='Proponi una modifica';details.append(summary);
          const form=document.createElement('form'),description=document.createElement('textarea'),reason=document.createElement('textarea'),button=document.createElement('button'),message=document.createElement('p');
          const descriptionLabel=document.createElement('label');descriptionLabel.textContent='Descrizione aggiornata';
          description.name='description';description.minLength=10;description.maxLength=5000;description.required=true;description.rows=5;descriptionLabel.append(description);
          const reasonLabel=document.createElement('label');reasonLabel.textContent='Motivo della modifica';reason.name='reason';reason.maxLength=500;reason.rows=2;reasonLabel.append(reason);
          button.type='submit';button.textContent='Invia per moderazione';message.setAttribute('role','status');
          form.append(descriptionLabel,reasonLabel,button,message);details.append(form);li.append(details);
          form.addEventListener('submit',async event=>{
            event.preventDefault();if(button.disabled||!form.reportValidity())return;
            button.disabled=true;
            try {
              const result=await fetch(api+'community-revisions',{
                method:'POST',mode:'same-origin',credentials:'same-origin',cache:'no-store',
                headers:{'Content-Type':'application/json','X-Badaround-CSRF':csrf},
                body:JSON.stringify({event_id:item.event_id,description:description.value.trim(),reason:reason.value.trim()})
              });
              const receipt=await result.json().catch(()=>({}));
              if(!result.ok||receipt.status!=='pending_moderation')throw new Error();
              form.reset();message.textContent='Proposta ricevuta e in attesa di moderazione.';
            } catch {message.textContent='Proposta non confermata. Verifica l’accesso e riprova più tardi.';}
            finally {button.disabled=false;}
          });
        }
        reportsList.append(li);
      }
      if(!reportsList.childNodes.length){const li=document.createElement('li');li.textContent='Non ci sono segnalazioni associate al tuo account.';reportsList.append(li);}
    }
    activityRefresh.addEventListener("click", () => {
      busy(activity, async () => {
        await loadActivity();
        notice("Elenco delle attività aggiornato.");
      });
    });
    async function refresh() {
      try {
        const data = await send("identity/session");
        csrf = data.authenticated === true && typeof data.csrf === "string"
          ? data.csrf : null;
        showSignedIn(!!csrf);
        if (csrf) await loadActivity().catch(() => { activityList.replaceChildren(); });
      } catch {
        csrf = null;
        showSignedIn(false);
      }
    }
    window.BadAroundAccess.mount(entry,{onVerified:async()=>{await refresh();if(!csrf)throw new Error();notice('Accesso effettuato.');}});
    logout.addEventListener("click", () => {
      busy(portal, async () => {
        if (!csrf) throw new Error();
        const response = await fetch(api + "identity/logout", {
          method: "POST",
          mode: "same-origin",
          credentials: "same-origin",
          cache: "no-store",
          headers: { "Content-Type": "application/json", "X-Badaround-CSRF": csrf },
          body: "{}"
        });
        if (!response.ok) throw new Error();
        csrf = null;
        showSignedIn(false);
        notice("Sei uscito dal tuo account.");
      });
    });
    refresh();
  }
})();
