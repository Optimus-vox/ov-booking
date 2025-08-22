jQuery(function ($) {
  // Otvori/Zatvori login modal (ostavljeno kao što si imao)
  $(document).on("click", ".js-open-login-modal", function (e) {
    e.preventDefault();
    $("#ov-login-modal").fadeIn(200);
  });

  $(document).on("click", ".ov-modal-close, .ov-modal-backdrop", function (e) {
    e.preventDefault();
    $("#ov-login-modal").fadeOut(200);
  });

  $(document).on("click", ".ov-toggle-password", function (e) {
    e.preventDefault();

    var $btn = $(this),
      $input = $btn.siblings('input[name="pwd"]'),
      isPwd = $input.attr("type") === "password",
      newType = isPwd ? "text" : "password";

    $input.attr("type", newType);
    $btn
      .removeClass(isPwd ? "dashicons-visibility" : "dashicons-hidden")
      .addClass(isPwd ? "dashicons-hidden" : "dashicons-visibility")
      .attr("aria-label", isPwd ? "Hide password" : "Show password");
  });

  // Continue → checkout
  $(document).on("click", ".js-continue", function (e) {
    e.preventDefault();
    if (window.ovCartVars && ovCartVars.checkoutUrl) {
      window.location.href = ovCartVars.checkoutUrl;
    } else {
      alert("Checkout URL is not set.");
    }
  });

  // ---- SweetAlert2 loader (CDN, bez menjanja enqueue-a) ----
  function loadSwal(callback) {
    if (window.Swal) return callback();
    // CSS
    if (!document.getElementById("swal2-css")) {
      var l = document.createElement("link");
      l.id = "swal2-css";
      l.rel = "stylesheet";
      l.href = "https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css";
      document.head.appendChild(l);
    }
    // JS
    var s = document.getElementById("swal2-js");
    if (!s) {
      s = document.createElement("script");
      s.id = "swal2-js";
      s.src = "https://cdn.jsdelivr.net/npm/sweetalert2@11";
      s.async = true;
      s.onload = callback;
      document.head.appendChild(s);
    } else if (s && s.onload) {
      s.onload = callback;
    }
  }

  function fireToast(icon, title) {
    loadSwal(function () {
      const Toast = Swal.mixin({
        toast: true,
        position: "top-end",
        showConfirmButton: false,
        timer: 3500,
        timerProgressBar: true,
      });
      Toast.fire({ icon: icon || "info", title: title || "" });
    });
  }

  // Empty cart via AJAX sa Swal confirm + toast
  window.ovCartVars = window.ovCartVars || {};
  $(document).on("click", ".js-empty-cart", function (e) {
    e.preventDefault();

    loadSwal(function () {
      Swal.fire({
        title: (ovCartVars && ovCartVars.emptyCartTitle) || "Empty cart?",
        text: (ovCartVars && ovCartVars.emptyCartConfirmMsg) || "This will remove all items from your cart.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: (ovCartVars && ovCartVars.confirmText) || "Yes, empty it",
        cancelButtonText: (ovCartVars && ovCartVars.cancelText) || "Cancel",
        focusCancel: true,
      }).then(function (res) {
        if (!res.isConfirmed) return;

        $.ajax({
          url: ovCartVars.ajax_url,
          method: "POST",
          data: {
            action: "ovb_empty_cart",
            nonce: ovCartVars.nonce,
          },
        })
          .done(function (response) {
            if (response && response.success) {
              fireToast("success", (ovCartVars && ovCartVars.emptySuccess) || "Cart emptied.");
              // redirect (zadržao tvoje ponašanje)
              var to = (ovCartVars && ovCartVars.checkoutUrl) || window.location.href;
              setTimeout(function () {
                window.location.href = to;
              }, 600);
            } else {
              console.error("Empty cart failed:", response);
              fireToast("error", (response && response.data) || "Empty cart failed.");
            }
          })
          .fail(function (jqXHR, textStatus) {
            console.error("AJAX error:", textStatus);
            fireToast("error", "AJAX request failed: " + textStatus);
          });
      });
    });
  });
});
