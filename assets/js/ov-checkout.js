(function ($) {
  function ovbDeselectPaymentMethods() {
    // Deselect all payment radios
    $(".wc_payment_method input[type=radio]").prop("checked", false);
    // Hide all payment boxes
    $(".payment_box").hide();
  }

  // Prvi load
  $(document).ready(function () {
    ovbDeselectPaymentMethods();
  });

  // Svaki put kad Woo osveži checkout (AJAX event), opet primeni hack
  $(document.body).on("updated_checkout payment_method_selected", function () {
    ovbDeselectPaymentMethods();
  });

  // Prikaži samo selektovani box na izboru
  $(document.body).on("change", ".wc_payment_method input[type=radio]", function () {
    $(".payment_box").hide();
    $(this).closest("li").find(".payment_box").slideDown(150);
  });
})(jQuery);
