import { debounce } from "../utils/debounce";
import {
  isStatusPage,
  isGatewaySettingsPage,
  urlParams,
} from "../utils/screen";

jQuery(function ($) {
  const gateway = urlParams.get("section");
  const ajaxUrl = window.ajaxurl;
  const ajaxNonce = window._wooAsaasAdminSettings.nonce;

  const $anticipationCheckbox = $(`#woocommerce_${gateway}_anticipation`);
  const $enviromentSelect = $(`#woocommerce_${gateway}_endpoint`);
  const $apiKeyField = $(`#woocommerce_${gateway}_api_key`);
  const $emailField = $(`#woocommerce_${gateway}_email_notification`);
  const $reenableButton = $(".reenable-queue");
  const $saveButton = $(".woocommerce-save-button");

  const $statusConnectionRow = $("#webhook-status-connection");
  const $statusQueueRow = $("#webhook-status-queue");

  const $loaderElement = $('<span class="preloader"></span>');
  const $messageElement = $('<span class="apikey-message"></span>');

  if (isStatusPage()) {
    checkWebhookStatus();
  }

  if (isGatewaySettingsPage()) {
    checkWebhookSettings();
  }

  $emailField.prop("required", true);

  $reenableButton.on("click", function (e) {
    e.preventDefault();

    disableQueueButton(false);
    updateExistingWebhookQueue();
  });

  function checkWebhookStatus() {
    $.ajax({
      url: ajaxUrl,
      data: { action: "check_webhook_status" },
      success: function (response) {
        $statusConnectionRow.find(".loader").hide();
        $statusQueueRow.find(".loader").hide();

        if (response.success) {
          const isEnabled = response.data[0].enabled;
          const isInterrupted = response.data[0].interrupted;
          const authToken = response.data[0].authToken;

          $statusConnectionRow.find(".connection-yes").show();

          if (authToken && isEnabled && !isInterrupted) {
            $statusQueueRow.find(".queue-yes").show();
          } else {
            $statusQueueRow.find(".queue-error").show();
          }

          disableQueueButton(!authToken || !isEnabled || isInterrupted);
        } else {
          $statusConnectionRow.find(".connection-error").show();
          $statusQueueRow.find(".queue-error").show();
        }
      },
      error: function () {
        $statusConnectionRow.find(".connection-error").show();
        $statusQueueRow.find(".queue-error").show();
      },
    });
  }

  function checkWebhookSettings() {
    const apiKey = $apiKeyField.val();
    let enviromentUrl = $enviromentSelect.val();

    $enviromentSelect.on("change", function () {
      enviromentUrl = $(this).val();
      const apiKey = $apiKeyField.val();
      if (apiKey === "") {
        return;
      }

      $messageElement.remove();

      validateApiKey({ enviromentUrl, apiKey, ajaxNonce });
    });

    $apiKeyField.on(
      "input",
      debounce(function () {
        const apiKey = $(this).val();

        $messageElement.remove();

        if (apiKey === "") {
          disableQueueButton(false);

          $loaderElement.remove();
          $messageElement.remove();

          return;
        }

        validateApiKey({ enviromentUrl, apiKey, ajaxNonce });
      }, 1200)
    );

    if (apiKey === "") {
      return;
    }

    validateApiKey({ enviromentUrl, apiKey, ajaxNonce });
  }

  function validateApiKey(params) {
    $loaderElement.remove();
    $messageElement.remove();

    $apiKeyField.after($loaderElement);

    $.ajax({
      url: ajaxUrl,
      type: "POST",
      data: {
        action: "check_webhook_setting",
        url: params.enviromentUrl,
        api_key: params.apiKey,
        _nonce: params.ajaxNonce,
      },
      beforeSend: function () {
        disableSaveButton();
      },
      success: function (response) {
        $loaderElement.remove();

        if (response.success && !response.data) {
          disableSaveButton(false);
          return;
        }

        $apiKeyField.after($messageElement);
        $messageElement.html("✅ Chave de API válida.");

        disableSaveButton(false);

        if (response.success) {
          $anticipationCheckbox.prop("disabled", false);

          const isEnabled = response.data[0].enabled;
          const isInterrupted = response.data[0].interrupted;
          const authToken = response.data[0].authToken;

          disableQueueButton(!authToken || !isEnabled || isInterrupted);
          updateExistingWebhookEmail(response.data[0].email);
        } else {
          $anticipationCheckbox.prop("disabled", true);

          disableSaveButton(false);
          disableQueueButton(false);

          $loaderElement.remove();

          $apiKeyField.after($messageElement);
          $messageElement.html("❌ Chave de API inválida.");
        }
      },
    });
  }

  function updateExistingWebhookQueue() {
    $.ajax({
      url: ajaxUrl,
      type: "POST",
      data: { action: "update_existing_webhook_queue" },
      beforeSend: function () {
        $reenableButton.html('<span class="preloader"></span> Aguarde...');
      },
      success: function (response) {
        if (response.success) {
          const { enabled, authToken, interrupted } = response.data;
          const settingsState = !enabled || !authToken || interrupted;

          disableQueueButton(settingsState);

          $statusQueueRow.find(".error").removeClass("error").addClass("yes");
          $statusQueueRow
            .find(".dashicons-no")
            .removeClass("dashicons-no")
            .addClass("dashicons-yes");

          let messageContainer = $('<span class="reenable-message"></span>');
          messageContainer.insertAfter($reenableButton);
          messageContainer.html("✅ Fila reativada com sucesso");

          $reenableButton.text("Reabilitar fila de webhooks");

          setTimeout(function () {
            messageContainer.remove();
          }, 5000);
        }
      },
      error: function () {},
    });
  }

  function updateExistingWebhookEmail(email) {
    const emailField = $emailField.val();

    if (emailField === email) {
      return;
    }

    $.ajax({
      url: ajaxUrl,
      type: "POST",
      data: { action: "update_existing_webhook_email" },
    });
  }

  function disableSaveButton(state = true) {
    $saveButton.prop("disabled", state);
  }

  function disableQueueButton(state = true) {
    if (state) {
      $reenableButton.removeAttr("disabled");
    } else {
      $reenableButton.attr("disabled", true);
    }
  }
});