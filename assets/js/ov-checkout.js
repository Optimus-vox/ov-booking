// OVB: sakrij WooCommerce spinner (ostavi overlay da blokira klikove)
(function () {
  const style = document.createElement("style");
  style.id = "ovb-hide-wc-spinner";
  style.textContent = `
    /* Woo blockUI spiner je :before na .blockOverlay */
    .woocommerce .blockUI.blockOverlay::before,
    .woocommerce .blockUI.blockOverlay:before,
    .woocommerce .wc-ajax-loader,
    .woocommerce .loader,
    .woocommerce .processing:after {
      content: none !important;
      display: none !important;
    }
  `;
  document.head.appendChild(style);
})();


(function ($) {
  function ovbUncheckPayments() {
    $('input[name="payment_method"]').prop("checked", false);
    $("#payment .payment_box").hide(); // zatvori eventualno otvorene boxeve
  }
  $(document).ready(ovbUncheckPayments);
  $(document.body).on("updated_checkout", ovbUncheckPayments);
})(jQuery);