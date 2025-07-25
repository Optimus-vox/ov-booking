jQuery(function ($) {
  // Otvori login modal
  $(document).on("click", ".js-open-login-modal", function (e) {
    e.preventDefault();
    $("#ov-login-modal").fadeIn(200);
  });

  // Zatvori login modal (klik na pozadinu ili dugme za zatvaranje)
  $(document).on("click", ".ov-modal-close, .ov-modal-backdrop", function (e) {
    e.preventDefault();
    $("#ov-login-modal").fadeOut(200);
  });

  // Toggle show/hide lozinke u modal formi
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
    // console.log(ovCartVars);
    e.preventDefault();
    if (ovCartVars.checkoutUrl) {
      // console.log(ovCartVars.checkoutUrl);
      window.location.href = ovCartVars.checkoutUrl;
    }else{
      alert("Checkout URL is not set.");
    }
  });

  // Empty cart via AJAX
  $(document).on("click", ".js-empty-cart", function (e) {
    e.preventDefault();
    var confirmMsg = ovCartVars.emptyCartConfirmMsg || "Are you sure you want to empty your cart?";
    if (!confirm(confirmMsg)) {
      return;
    }

    $.post(ovCartVars.ajax_url, { action: "ovb_empty_cart" })
      .done(function (response) {
        if (response.success) {
          window.location.reload();
        } else {
          alert("Could not empty cart.");
        }
      })
      .fail(function () {
        alert("AJAX request failed.");
      });
  });  
});
