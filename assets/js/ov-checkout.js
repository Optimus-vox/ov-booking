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
