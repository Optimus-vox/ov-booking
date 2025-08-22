(function ($) {
  var pending = 0;
  var waitingForUpdated = false;
  var shownAt = 0;
  var hideTimer = null;

  // koliko najmanje da ostane vidljiv + cooldown da upije brze uzastopne updejte
  var MIN_SHOW = (window.ovbI18n && parseInt(ovbI18n.min_show_ms, 10)) || 700;
  var COOLDOWN = 220;

  function $shell() {
    return $("#ovb-payment-shell");
  }
  function show() {
    var $sh = $shell();
    if (!$sh.length) return;
    if (pending === 0) {
      shownAt = Date.now();
      $sh.addClass("is-loading");
    }
    pending++;
  }
  function scheduleHide() {
    clearTimeout(hideTimer);
    var wait = Math.max(0, MIN_SHOW - (Date.now() - shownAt)) + COOLDOWN;
    hideTimer = setTimeout(function () {
      pending = 0;
      $shell().removeClass("is-loading");
    }, wait);
  }
  function maybeHide() {
    pending = Math.max(0, pending - 1);
    if (pending === 0 && !waitingForUpdated) {
      scheduleHide();
    }
  }

  // Pali loader samo za wc-ajax=update_order_review pozive
  $(document).on("ajaxSend", function (_, __, settings) {
    if (!settings || !settings.url) return;
    if (settings.url.indexOf("wc-ajax=update_order_review") !== -1) {
      waitingForUpdated = true;
      show();
    }
  });

  // Woo završi zamenu fragmenata → 'updated_checkout'
  $(document.body).on("updated_checkout", function () {
    if (waitingForUpdated) {
      waitingForUpdated = false;
      if (pending === 0) scheduleHide();
    }
  });

  // Svaki relevantan AJAX kompletiran → smanji brojač
  $(document).on("ajaxComplete", function (_, __, settings) {
    if (!settings || !settings.url) return;
    if (settings.url.indexOf("wc-ajax=update_order_review") !== -1) {
      maybeHide();
    }
  });

  // safety fallback (ako nešto ne ispali updated_checkout)
  setTimeout(function () {
    if (pending > 0) scheduleHide();
  }, 10000);
})(jQuery);


(function ($) {
  function ensureValidPaymentSelected() {
    var $methods = $('input[name="payment_method"]');
    if (!$methods.length) return;

    var $checked = $methods.filter(":checked");
    if (!$checked.length || !$checked.is(":visible") || $checked.is(":disabled")) {
      var $first = $methods.filter(":visible:enabled").first();
      if ($first.length) {
        $first.prop("checked", true).trigger("change");
        $(document.body).trigger("payment_method_selected", [$first.val()]);
      }
    }
    togglePlaceOrder();
  }

  function togglePlaceOrder() {
    var ok = $('input[name="payment_method"]:checked').length > 0;
    $("#place_order").prop("disabled", !ok).toggleClass("disabled-button", !ok);
  }

  $(function () {
    ensureValidPaymentSelected();
    $(document.body).on("updated_checkout", ensureValidPaymentSelected);
    $(document).on("change", 'input[name="payment_method"]', togglePlaceOrder);

    // Zaključaj dugme tokom update_order_review AJAX-a
    $(document).on("ajaxSend", function (_, __, s) {
      if (s && s.url && s.url.indexOf("wc-ajax=update_order_review") !== -1) {
        $("#place_order").prop("disabled", true).addClass("disabled-button");
      }
    });
    $(document.body).on("updated_checkout", function () {
      togglePlaceOrder();
    });
  });
})(jQuery);

(function ($) {
  // Fail-safe toggle za .payment_box ako neka tema ne poveže Woo skriptu
  $(document).on("change", 'input[name="payment_method"]', function () {
    var val = $(this).val();
    $(".payment_box").hide();
    $(".payment_box.payment_method_" + val).show();
  });
})(jQuery);

