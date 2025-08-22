(function ($) {
  $(document).on('submit', '.ovb-apartment-filter', function (e) {
    e.preventDefault();
    var $form = $(this);
    var data = $form.serializeArray();
    data.push({ name: 'action', value: 'ovb_filter_apartments' });
    data.push({ name: 'nonce', value: ovbCatalog.nonce });

    var $target = $('.ovb-apartments-grid');
    $target.addClass('ovb-loading');

    $.post(ovbCatalog.ajax_url, $.param(data))
      .done(function (resp) {
        if (resp && resp.success && resp.data && resp.data.html) {
          // Zameni ceo listing
          var $html = $(resp.data.html);
          var $new = $html.find('.ovb-apartments-grid');
          if ($new.length) {
            $target.replaceWith($new);
          } else {
            $target.html(resp.data.html);
          }
        }
      })
      .always(function () {
        $('.ovb-apartments-grid').removeClass('ovb-loading');
      });
  });
})(jQuery);
