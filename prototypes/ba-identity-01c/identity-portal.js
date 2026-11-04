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
    const register = portal.querySelector('[data-ba-form="register"]');
    const login = portal.querySelector('[data-ba-form="login"]');
    const otp = portal.querySelector('[data-ba-form="otp"]');
    const logout = portal.querySelector("[data-ba-logout]");
    const activity = portal.querySelector("[data-ba-activity]");
    const activityList = portal.querySelector("[data-ba-activity-list]");
    const activityRefresh = portal.querySelector("[data-ba-refresh-activity]");
    if (!status || !register || !login || !otp || !logout || !activity || !activityList || !activityRefresh) continue;
    let challenge = null;
    let stage = null;
    let csrf = null;
    function notice(text) { status.textContent = text; }
    function showSignedIn(value) {
      register.hidden = value;
      login.hidden = value;
      otp.hidden = true;
      logout.hidden = !value;
      activity.hidden = !value;
      if (!value) activityList.replaceChildren();
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
    register.addEventListener("submit", event => {
      event.preventDefault();
      if (!register.reportValidity()) return;
      busy(register, async () => {
        const f = new FormData(register);
        const data = await send("identity/register/start", {
          first_name: String(f.get("first_name") || ""),
          last_name: String(f.get("last_name") || ""),
          email: String(f.get("email") || ""),
          privacy_acknowledged: f.get("privacy_acknowledged") === "on"
        });
        if (!/^[a-f0-9]{32}$/.test(data.challenge_id || "")) throw new Error();
        challenge = data.challenge_id;
        stage = "register";
        otp.hidden = false;
        notice("Se i dati sono validi, riceverai un codice di verifica via email.");
        otp.querySelector('[name="code"]')?.focus();
      });
    });
    login.addEventListener("submit", event => {
      event.preventDefault();
      if (!login.reportValidity()) return;
      busy(login, async () => {
        const f = new FormData(login);
        const data = await send("identity/login/start", {
          email: String(f.get("email") || "")
        });
        if (!/^[a-f0-9]{32}$/.test(data.challenge_id || "")) throw new Error();
        challenge = data.challenge_id;
        stage = "login";
        otp.hidden = false;
        notice("Se il recapito è valido, riceverai un codice temporaneo via email.");
        otp.querySelector('[name="code"]')?.focus();
      });
    });
    otp.addEventListener("submit", event => {
      event.preventDefault();
      if (!otp.reportValidity() || !challenge || !stage) return;
      busy(otp, async () => {
        const code = String(new FormData(otp).get("code") || "");
        if (!/^[0-9]{8}$/.test(code)) throw new Error();
        const kind = stage;
        const result = await send("identity/" + kind + "/verify", {
          challenge_id: challenge,
          code
        });
        otp.reset();
        challenge = null;
        stage = null;
        if (kind === "register" && result.status === "verified") {
          otp.hidden = true;
          notice("Registrazione verificata. Ora richiedi il codice di accesso.");
          login.querySelector('[name="email"]')?.focus();
          return;
        }
        if (kind === "login" && result.ok === true) {
          await refresh();
          if (csrf) {
            notice("Accesso effettuato.");
            return;
          }
        }
        throw new Error("verification_failed");
      });
    });
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
