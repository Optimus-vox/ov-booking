// File: assets/js/ov-single.js
jQuery(function ($) {
  // ---------------------------------------------------------------
  // 1) Provera da li su moment i DateRangePicker učitani
  // ---------------------------------------------------------------
  if (typeof moment === "undefined") {
    console.error("[OV] Moment.js nije učitan");
    return;
  }
  if (typeof $.fn.daterangepicker !== "function") {
    console.error("[OV] DateRangePicker nije učitan");
    return;
  }

  // ---------------------------------------------------------------
  // 2) Pročitaj raw vrednosti iz skrivenih polja (YYYY-MM-DD)
  // ---------------------------------------------------------------
  var rawStart = $("#start_date").val();
  var rawEnd = $("#end_date").val();

  // ---------------------------------------------------------------
  // 3) Definiši zajedničke opcije za picker (UI oblik DD/MM/YYYY)
  // ---------------------------------------------------------------
  var defaultStart = moment().startOf("day");
  var defaultEnd = moment().add(1, "day").startOf("day");
  var localeLabels = {
    format: "DD/MM/YYYY",
    separator: " - ",
    firstDay: 1,
    daysOfWeek: ["Pon", "Uto", "Sre", "Čet", "Pet", "Sub", "Ned"],
    monthNames: [
      "Januar",
      "Februar",
      "Mart",
      "April",
      "Maj",
      "Jun",
      "Jul",
      "Avgust",
      "Septembar",
      "Oktobar",
      "Novembar",
      "Decembar",
    ],
  };

  // ---------------------------------------------------------------
  // 4) Monkey-patch updateCalendars da uvek redraw-uje cene/status
  // ---------------------------------------------------------------
  (function () {
    var DRP = $.fn.daterangepicker && $.fn.daterangepicker.constructor;
    if (DRP && !DRP.prototype.__patchedUpdateCalendars) {
      var orig = DRP.prototype.updateCalendars;
      DRP.prototype.updateCalendars = function () {
        orig.apply(this, arguments);
        try {
          renderCalendar($(this.element));
        } catch (err) {
          console.warn("[OV] updateCalendars render failed", err);
        }
      };
      DRP.prototype.__patchedUpdateCalendars = true;
      console.log("[OV] updateCalendars patched");
    }
  })();

  // ---------------------------------------------------------------
  // 5) INTERAKTIVNI INPUT kalendar inicijalizacija
  // ---------------------------------------------------------------
  const $input = $("#daterange");

  if ($input.length) {
    // Osveži prikaz svaki put kad se prikazuje ili menja mesec
    $input.on("showCalendar.daterangepicker", function () {
      renderCalendar($input);
    });

    // Kada se picker zaista otvori (show.daterangepicker), vežemo handler
    $input.on("show.daterangepicker", function () {
      // Prvo odmah re-renderujemo kalendar (da bismo prikazali cene/status)
      renderCalendar($input);

      // Sada je drp.container prisutan i vidljiv—vežemo handler
      var drp = $input.data("daterangepicker");
      if (drp && drp.container) {
        // Uklonimo prethodne da ne bismo imali duple binding-e
        drp.container.off("mousedown.bindRender");

        // Vežemo se baš na isti event (mousedown.daterangepicker) koji plugin koristi
        drp.container.on("mousedown.bindRender", "td.available", function () {
          renderCalendar($input);
        });
      }
    });

    // Callback nakon što korisnik odabere range i klikne Apply
    $input.on("apply.daterangepicker", function (ev, picker) {
      renderCalendar($input);
    });

    // ---------------------------------------------------------------
    // 6) Pripremi opcije, ubaci startDate/endDate ako postoje
    // ---------------------------------------------------------------
    var pickerOptions = {
      startDate: defaultStart,
      endDate: defaultEnd,
      autoUpdateInput: false,
      autoApply: true,
      linkedCalendars: true,
      opens: "center",
      minDate: defaultStart,
      // Obavezan minimalni razmak od 1 dana
      minSpan: { days: 1 },
      // Blokiraj dan pre danas
      isInvalidDate: function (date) {
        return date.isBefore(defaultStart, "day");
      },
      locale: localeLabels,
    };
    if (rawStart && rawEnd) {
      pickerOptions.startDate = moment(rawStart, "YYYY-MM-DD");
      pickerOptions.endDate = moment(rawEnd, "YYYY-MM-DD");
    }

    // ---------------------------------------------------------------
    // 7) Inicijalizuj DateRangePicker
    // ---------------------------------------------------------------
    $input.daterangepicker(pickerOptions, function (start, end) {
      // Callback pri prvoj inicijalizaciji i svakoj promeni

      // Ako su start i end isti dan, pomeri end na start + 1 dan
      if (start.isSame(end, "day")) {
        end = start.clone().add(1, "day");
        var drp = $input.data("daterangepicker");
        drp.setEndDate(end);
      }

      // Raw formati za skrivena polja
      var newRawStart = start.format("YYYY-MM-DD");
      var newRawEnd = end.format("YYYY-MM-DD");
      $input.val(start.format("DD/MM/YYYY") + " – " + end.format("DD/MM/YYYY"));
      $("#start_date").val(newRawStart);
      $("#end_date").val(newRawEnd);

      // Kreiraj all_dates (CSV sa svim datumima)
      var allDatesArr = [];
      var curr = start.clone();
      while (curr.isSameOrBefore(end, "day")) {
        allDatesArr.push(curr.format("YYYY-MM-DD"));
        curr.add(1, "days");
      }

      // Izračunaj broj noćenja i cenu
      var nights = end.diff(start, "days");
      var dates = [];
      var totalPrice = 0;
      if (nights === 0) {
        nights = 1;
        var key = start.format("YYYY-MM-DD");
        dates.push(key);
        totalPrice = parseFloat(
          ov_calendar_vars.calendarData?.[key]?.price || 0
        );
      } else {
        for (var d = start.clone(); d.isBefore(end, "day"); d.add(1, "day")) {
          var k = d.format("YYYY-MM-DD");
          dates.push(k);
          var info = ov_calendar_vars.calendarData?.[k] || {};
          if (info.price) {
            totalPrice += parseFloat(info.price);
          }
        }
      }
      $("#all_dates").val(dates.join(","));

      // Ažuriraj UI (total price box)
      $("#ov_total_price_box").show();
      $("#ov_total_price").text("€" + totalPrice.toFixed(2));
      $("#ov_total_nights").text(
        "for " + nights + (nights === 1 ? " night" : " nights")
      );

      // Redraw kalendar
      renderCalendar($input);

      // Enable Book Now dugme
      $input
        .closest("form")
        .find(".single_add_to_cart_button")
        .prop("disabled", false);

      // ————————————————
      //  Dodato: Update booking section sa imenom proizvoda
      // ————————————————
      if (typeof ovProductName !== "undefined") {
        var titleText =
          nights +
          (nights === 1 ? " night" : " nights") +
          " in " +
          ovProductName;
        $(".ov-booking-calendar-section h3").text(titleText);
        var startFormatted = start.format("MMM D, YYYY");
        var endFormatted = end.format("MMM D, YYYY");
        $(".ov-booking-calendar-section span").text(
          startFormatted + " – " + endFormatted
        );
      }
    });

    // ---------------------------------------------------------------
    // 8) Ako smo prosledili rawStart & rawEnd, odmah prikaži u inputu
    // ---------------------------------------------------------------
    if (rawStart && rawEnd) {
      var mS = moment(rawStart, "YYYY-MM-DD");
      var mE = moment(rawEnd, "YYYY-MM-DD");
      $input.val(mS.format("DD/MM/YYYY") + " – " + mE.format("DD/MM/YYYY"));
    } else {
      // Nema prosleđenih datuma → postavi danas–sutra
      var today = defaultStart;
      var tomorrow = defaultEnd;
      $input.val(
        today.format("DD/MM/YYYY") + " – " + tomorrow.format("DD/MM/YYYY")
      );
      $("#start_date").val(today.format("YYYY-MM-DD"));
      $("#end_date").val(tomorrow.format("YYYY-MM-DD"));
      $("#all_dates").val(today.format("YYYY-MM-DD"));
    }
  } // kraj if ($input.length)

  // ---------------------------------------------------------------
  // 9) READ-ONLY kalendar inicijalizacija (ako postoji element)
  // ---------------------------------------------------------------
  const $readonly = $("#ov-booking_readonly_calendar");
  if ($readonly.length) {
    $readonly.daterangepicker({
      startDate: defaultStart,
      endDate: defaultEnd,
      autoUpdateInput: false,
      linkedCalendars: true,
      opens: "center",
      alwaysShowCalendars: true,
      isInvalidDate: () => false,
      locale: localeLabels,
    });
    setTimeout(function () {
      var drp = $readonly.data("daterangepicker");
      if (drp) {
        drp.show();
        renderCalendar($readonly);
      }
    }, 10);
    $readonly.on("showCalendar.daterangepicker", function () {
      renderCalendar($readonly);
    });
    $(document)
      .off("mousedown.daterangepicker")
      .on("mousedown.daterangepicker", function (e) {
        var t = $(e.target);
        if (
          !t.closest(".daterangepicker, #ov-booking_readonly_calendar").length
        ) {
          e.stopImmediatePropagation();
        }
      });
  }


  
}); // kraj jQuery(function($){ … })
