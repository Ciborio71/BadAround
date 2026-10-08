(function (root) {
  'use strict';
  const ns = root.BadAroundNative = root.BadAroundNative || {};
  const messages = {
    media_count_limit:'Puoi selezionare al massimo 5 immagini.', media_file_too_large:'Ogni immagine può pesare al massimo 5 MiB (5.242.880 byte).',
    media_type_unsupported:'Formato non supportato. Usa uno dei formati disponibili indicati nel modulo.', media_image_invalid:'L’immagine è vuota, danneggiata, animata o supera i limiti consentiti. Scegli un’altra immagine.',
    media_decoder_unavailable:'La verifica delle immagini non è disponibile. Riprova più tardi.', media_service_unavailable:'Il servizio immagini non è disponibile. Riprova più tardi o rimuovi le immagini non caricate.',
    media_rate_limited:'Hai raggiunto il limite temporaneo per le immagini. Attendi prima di riprovare.',
    media_session_expired:'La sessione immagini è scaduta. Non modificare un invio già avviato; per un nuovo invio occorre iniziare una nuova segnalazione.',
    media_capability_invalid:'Non è possibile verificare la sessione immagini. Conserva questo invio e riprova più tardi.',
    media_reference_invalid:'Una delle immagini non è disponibile. Controlla le immagini prima di inviare.', media_descriptor_mismatch:'La conferma dell’immagine non corrisponde al file. Riprova o rimuovi il file prima dell’invio.',
    media_upload_incomplete:'Il caricamento non è ancora completo. Attendi e riprova con lo stesso file.', media_upload_pending:'Completa o rimuovi questa immagine prima di inviare.',
    media_commit_in_progress:'Le immagini sono ancora in elaborazione. Riprova con lo stesso invio.', media_binding_failed:'L’associazione delle immagini non è completa. Riprova con lo stesso invio.',
    media_manifest_invalid:'Controlla le immagini selezionate prima di inviare.', media_manifest_conflict:'Le immagini dell’invio sono già fissate. Non modificarle: verifica l’esito riprovando lo stesso invio quando consentito.',
    media_binding_invariant_failed:'Non è possibile confermare l’associazione delle immagini. Non creare un duplicato: contatta BadAround per verificare l’esito.',
    media_remove_first:'Rimuovi esplicitamente tutte le immagini prima di scegliere un’altra disponibilità.',
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
          if (focusField) focusField(error.field,error.file);
          else wrapper.querySelector('input,select,textarea,fieldset').focus();
        }); item.append(button);
      } else item.textContent = text;
      list.append(item);
    });
    summary.hidden = false; summary.focus();
  };
  if (typeof module !== 'undefined' && module.exports) module.exports = {errorMessage:ns.errorMessage};
})(typeof window !== 'undefined' ? window : globalThis);
