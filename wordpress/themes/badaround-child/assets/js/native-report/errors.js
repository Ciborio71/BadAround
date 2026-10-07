(function (root) {
  'use strict';
  const ns = root.BadAroundNative = root.BadAroundNative || {};
  const messages = {
    missing_required_field:'Completa questo campo obbligatorio.', conditional_field_required:'Completa questo campo per il tipo di evento scelto.',
    consent_required:'È necessaria questa conferma per procedere.', reward_confirmation_required:'Conferma l’impegno relativo alla ricompensa.',
    invalid_email:'Inserisci un indirizzo email valido.', invalid_phone:'Controlla il numero di telefono.', invalid_plate:'Usa lettere, numeri e ? per i caratteri sconosciuti.',
    invalid_enum:'Seleziona una delle opzioni disponibili.', invalid_category_subtype:'Seleziona un tipo di evento compatibile con la categoria.',
    invalid_date_time:'Controlla data e orari: non possono essere futuri e la fascia deve essere in ordine.',
    invalid_location:'Controlla la posizione: latitudine e longitudine devono essere indicate insieme.', invalid_number:'Inserisci un numero entro i limiti indicati.',
    value_too_long:'Il testo supera la lunghezza consentita.', too_many_items:'Hai selezionato troppi elementi.',
    duplicate_submission:'Questo invio risulta già associato a dati differenti. Non inviare una nuova segnalazione per gli stessi fatti: contatta BadAround per verificare l’esito.',
    submission_in_progress:'L’invio è ancora in elaborazione. Attendi e riprova con gli stessi dati.',
    rate_limited:'Hai raggiunto il limite temporaneo di invii. Attendi prima di riprovare.',
    network_error:'Non è stato possibile verificare l’esito. Riprova: verrà riutilizzato lo stesso invio.',
    invalid_response:'Non è stato possibile verificare la risposta. Riprova con lo stesso invio.',
    internal_error:'Il servizio è temporaneamente indisponibile. Riprova con lo stesso invio.',
    intake_failed:'L’invio non è stato completato. Riprova con gli stessi dati.', intake_persistence_failed:'L’invio non è stato completato. Riprova con gli stessi dati.',
    intake_state_update_failed:'L’invio non è stato completato. Riprova con gli stessi dati.', persistence_failed:'L’invio non è stato completato. Riprova con gli stessi dati.',
    ba_native_request_integrity:'Il modulo non può inviare la richiesta. Riprova più tardi.', ba_native_cross_site:'Il modulo deve essere aperto sul sito BadAround.',
    ba_native_cross_origin:'Il modulo deve essere aperto sul sito BadAround.', cross_origin_config:'Il modulo non è disponibile. Riprova più tardi.'
  };
  ns.errorMessage = error => messages[error.code] || 'Controlla i dati inseriti. Se il problema persiste, riprova più tardi.';
  ns.presentErrors = function (container, errors, config, focusField) {
    const summary = container.querySelector('[data-native-errors]');
    summary.replaceChildren();
    container.querySelectorAll('[data-native-field]').forEach(wrapper => {
      wrapper.querySelectorAll('[data-native-input], fieldset').forEach(input => input.setAttribute('aria-invalid','false'));
      const message = wrapper.querySelector('.ba-native-error'); message.hidden = true; message.textContent = '';
    });
    if (!errors.length) { summary.hidden = true; return; }
    const title = document.createElement('p'); title.textContent = 'Controlla la segnalazione'; summary.append(title);
    const list = document.createElement('ul'); summary.append(list);
    errors.forEach(error => {
      const field = config.fields[error.field];
      const item = document.createElement('li');
      const text = (field ? field.label + ': ' : '') + ns.errorMessage(error);
      const wrapper = Array.from(container.querySelectorAll('[data-native-field]')).find(w => w.dataset.nativeField === error.field);
      if (wrapper && !wrapper.hidden) {
        const message = wrapper.querySelector('.ba-native-error'); message.textContent = ns.errorMessage(error); message.hidden = false;
        wrapper.querySelectorAll('[data-native-input], fieldset').forEach(input => input.setAttribute('aria-invalid','true'));
        const button = document.createElement('button'); button.type = 'button'; button.textContent = text;
        button.addEventListener('click', () => {
          if (focusField) focusField(error.field);
          else wrapper.querySelector('input,select,textarea,fieldset').focus();
        }); item.append(button);
      } else item.textContent = text;
      list.append(item);
    });
    summary.hidden = false; summary.focus();
  };
  if (typeof module !== 'undefined' && module.exports) module.exports = {errorMessage:ns.errorMessage};
})(typeof window !== 'undefined' ? window : globalThis);
