// // File: assets/js/ov-single.js
// jQuery(function ($) {
//   // 1) Provera da li su moment i DateRangePicker učitani
//   if (typeof moment === "undefined") {
//     console.error("[OV] Moment.js nije učitan");
//     return;
//   }
//   if (typeof $.fn.daterangepicker !== "function") {
//     console.error("[OV] DateRangePicker nije učitan");
//     return;
//   }

//   // 2) Monkey-patch updateCalendars da uvek redraw-uje cene/status
//   (function () {
//     const DRP = $.fn.daterangepicker && $.fn.daterangepicker.constructor;
//     if (DRP && !DRP.prototype.__patchedUpdateCalendars) {
//       const orig = DRP.prototype.updateCalendars;
//       DRP.prototype.updateCalendars = function () {
//         orig.apply(this, arguments);
//         try {
//           renderCalendar($(this.element));
//         } catch (err) {
//           console.warn("[OV] updateCalendars render failed", err);
//         }
//       };
//       DRP.prototype.__patchedUpdateCalendars = true;
//       console.log("[OV] updateCalendars patched");
//     }
//   })();

//   // 3) Monkey-patch clickDate da nikad ne baca undefined.substr
//   (function () {
//     const DRP = $.fn.daterangepicker && $.fn.daterangepicker.constructor;
//     if (DRP && !DRP.prototype.__patchedClickDate) {
//       const origClickDate = DRP.prototype.clickDate;
//       DRP.prototype.clickDate = function (e) {
//         try {
//           const sep = this.locale.separator || " - ";
//           const $el = $(this.element);
//           const val = $el.val() || "";
//           if (val.split(sep).length < 2) {
//             const fmt = this.locale.format;
//             const sd = this.startDate.format(fmt);
//             $el.val(sd + sep + sd);
//           }
//         } catch (prepErr) {
//           console.warn("[OV] DRP prepErr", prepErr);
//         }
//         let result;
//         try {
//           result = origClickDate.call(this, e);
//         } catch (clickErr) {
//           console.warn("[OV] DRP clickDate swallowed", clickErr);
//         }
//         return result;
//       };
//       DRP.prototype.__patchedClickDate = true;
//     }
//   })();

//   // 4) Konstante i default vrednosti
//   const today = moment().startOf("day");
//   const defaultStart = today.clone();
//   const defaultEnd = today.clone();
//   const localeLabels = {
//     format: "DD/MM/YYYY",
//     separator: " - ",
//     firstDay: 1,
//     daysOfWeek: ["Pon", "Uto", "Sre", "Čet", "Pet", "Sub", "Ned"],
//     monthNames: ["Januar", "Februar", "Mart", "April", "Maj", "Jun", "Jul", "Avgust", "Septembar", "Oktobar", "Novembar", "Decembar"],
//   };

//   // 5) Render funkcija za cene/status
//   function renderCalendar($el) {
//     const drp = $el.data("daterangepicker");
//     if (!drp || !drp.container) return;
//     ["leftCalendar", "rightCalendar"].forEach((side) => {
//       const cal = drp[side].calendar;
//       const dir = side === "leftCalendar" ? "left" : "right";
//       drp.container.find(`.drp-calendar.${dir} td`).each((i, cell) => {
//         const $td = $(cell);
//         if ($td.hasClass("off")) {
//           $td.removeClass("available-day booked-day unavailable-day past-day").empty();
//           return;
//         }
//         const row = Math.floor(i / 7),
//           col = i % 7;
//         const m = cal[row]?.[col];
//         if (!m) {
//           $td.empty();
//           return;
//         }
//         const key = m.format("YYYY-MM-DD");
//         const info = ov_calendar_vars.calendarData?.[key] || {};
//         const isPast = m.isBefore(today, "day");
//         $td.find(".ov-label").remove();
//         $td.removeClass("available-day booked-day unavailable-day past-day");
//         let label = "-",
//           cls = "ov-label unavailable";
//         if (isPast) {
//           $td.addClass("past-day");
//         } else if (info.status === "booked") {
//           $td.addClass("booked-day");
//         } else if (info.status === "unavailable") {
//           $td.addClass("unavailable-day");
//         } else if (info.status === "available" && info.price) {
//           label = `€${info.price}`;
//           cls = "ov-label available";
//           $td.addClass("available-day");
//         }
//         $td.append(`<div class="${cls}">${label}</div>`);
//       });
//     });
//   }

//   // 6) READ-ONLY kalendar inicijalizacija
//   const $readonly = $("#ov-booking_readonly_calendar");
//   if ($readonly.length) {
//     $readonly.daterangepicker({
//       startDate: defaultStart,
//       endDate: defaultEnd,
//       autoUpdateInput: false,
//       linkedCalendars: true,
//       opens: "center",
//       alwaysShowCalendars: true,
//       isInvalidDate: () => false,
//       locale: localeLabels,
//     });
//     setTimeout(() => {
//       const drp = $readonly.data("daterangepicker");
//       if (drp) {
//         drp.show();
//         renderCalendar($readonly);
//       }
//     }, 10);
//     $readonly.on("showCalendar.daterangepicker", () => renderCalendar($readonly));
//     $(document)
//       .off("mousedown.daterangepicker")
//       .on("mousedown.daterangepicker", (e) => {
//         const t = $(e.target);
//         if (!t.closest(".daterangepicker, #ov-booking_readonly_calendar").length) {
//           e.stopImmediatePropagation();
//         }
//       });
//   }

//   // 7) INTERAKTIVNI INPUT kalendar inicijalizacija
//   const $input = $("#daterange");
//   if ($input.length) {
//     $input.daterangepicker(
//       {
//         startDate: defaultStart,
//         endDate: defaultEnd,
//         autoUpdateInput: true,
//         autoApply: true,
//         linkedCalendars: true,
//         opens: "center",
//         isInvalidDate: () => false,
//         locale: localeLabels,
//       },
//       (start, end) => {
//         // update input + skrivena polja
//         $input.val(`${start.format("DD/MM/YYYY")} - ${end.format("DD/MM/YYYY")}`);
//         $("#start_date").val(start.format("YYYY-MM-DD"));
//         $("#end_date").val(end.format("YYYY-MM-DD"));

//         // izračunaj broj noćenja i cenu
//         let nights = end.diff(start, "days");
//         let dates = [];
//         let totalPrice = 0;
//         if (nights === 0) {
//           nights = 1;
//           const key = start.format("YYYY-MM-DD");
//           dates.push(key);
//           totalPrice = parseFloat(ov_calendar_vars.calendarData?.[key]?.price || 0);
//         } else {
//           for (let d = start.clone(); d.isBefore(end, "day"); d.add(1, "day")) {
//             const key = d.format("YYYY-MM-DD");
//             dates.push(key);
//             const info = ov_calendar_vars.calendarData?.[key] || {};
//             if (info.price) {
//               totalPrice += parseFloat(info.price);
//             }
//           }
//         }
//         $("#all_dates").val(dates.join(","));

//         // update UI
//         $("#ov_total_price_box").show();
//         $("#ov_total_price").text(`€${totalPrice.toFixed(2)}`);
//         $("#ov_total_nights").text(`for ${nights} ${nights === 1 ? "night" : "nights"}`);

//         // redraw cene/status u pickeru
//         renderCalendar($input);

//         // Enable Book Now dugme
//         $input.closest("form").find(".single_add_to_cart_button").prop("disabled", false);
//       }
//     );

//     // odmah inicijalizuj sa default datumima
//     const drp = $input.data("daterangepicker");
//     if (drp && typeof drp.callback === "function") {
//       drp.callback(defaultStart, defaultEnd);
//     }

//     // osveži prikaz kad se otvori ili primeni izbor
//     // $input.on("showCalendar.daterangepicker apply.daterangepicker", () => renderCalendar($input));
//     $input.on("show.daterangepicker apply.daterangepicker", () => renderCalendar($input));

//   }
// });


// File: assets/js/ov-single.js
// jQuery(function ($) {
//   // 1) Provera da li su moment i DateRangePicker učitani
//   if (typeof moment === "undefined") {
//     console.error("[OV] Moment.js nije učitan");
//     return;
//   }
//   if (typeof $.fn.daterangepicker !== "function") {
//     console.error("[OV] DateRangePicker nije učitan");
//     return;
//   }

//   // 2) Monkey-patch updateCalendars da uvek redraw-uje cene/status
//   (function () {
//     const DRP = $.fn.daterangepicker && $.fn.daterangepicker.constructor;
//     if (DRP && !DRP.prototype.__patchedUpdateCalendars) {
//       const orig = DRP.prototype.updateCalendars;
//       DRP.prototype.updateCalendars = function () {
//         orig.apply(this, arguments);
//         try {
//           renderCalendar($(this.element));
//         } catch (err) {
//           console.warn("[OV] updateCalendars render failed", err);
//         }
//       };
//       DRP.prototype.__patchedUpdateCalendars = true;
//       console.log("[OV] updateCalendars patched");
//     }
//   })();

//   // 3) Monkey-patch clickDate da nikad ne baca undefined.substr
//   (function () {
//     const DRP = $.fn.daterangepicker && $.fn.daterangepicker.constructor;
//     if (DRP && !DRP.prototype.__patchedClickDate) {
//       const origClickDate = DRP.prototype.clickDate;
//       DRP.prototype.clickDate = function (e) {
//         try {
//           const sep = this.locale.separator || " - ";
//           const $el = $(this.element);
//           const val = $el.val() || "";
//           if (val.split(sep).length < 2) {
//             const fmt = this.locale.format;
//             const sd = this.startDate.format(fmt);
//             $el.val(sd + sep + sd);
//           }
//         } catch (prepErr) {
//           console.warn("[OV] DRP prepErr", prepErr);
//         }
//         let result;
//         try {
//           result = origClickDate.call(this, e);
//         } catch (clickErr) {
//           console.warn("[OV] DRP clickDate swallowed", clickErr);
//         }
//         return result;
//       };
//       DRP.prototype.__patchedClickDate = true;
//     }
//   })();



//   if ($(".go-to-cart-button").length) {
//     $(".stay-duration").hide();
//     $(".custom-price").addClass("added-to-cart");
//   }
// });



// document.addEventListener("DOMContentLoaded", function () {
//   initOvDateRangePicker({
//     input: "#custom-daterange-input",
//     container: "#date-range-picker",
//     readonly: false,
//     alwaysOpen: false,
//     locale: "sr-RS",
//     calendarData: window.ov_calendar_vars?.calendarData || {},
//     totalsContainer: "#ov_total_container",

//   });
//   initOvDateRangePicker({
//     container: "#ov-booking_readonly_calendar",
//     readonly: true,
//     alwaysOpen: true,
//     locale: "sr-RS",
//     calendarData: window.ov_calendar_vars?.calendarData || {},
//   });
// });


// jQuery(function ($) {
//   const $form = $("form.ov-booking-form");

//   if (!$form.length) return;

//   $form.on("submit", function (e) {
//     e.preventDefault();

//     const $btn = $form.find('button[type="submit"]');
//     $btn.prop("disabled", true).text("Processing...");

//     const formData = $form.serialize();
//     const ajaxUrl = `${window.location.origin}/?wc-ajax=add_to_cart`;

//     $.post(ajaxUrl, formData)
//       .done(function (response) {
//         if (response && typeof response === "object" && response.fragments) {
//           const redirectUrl = typeof ovCartVars !== "undefined" && ovCartVars.checkoutUrl ? ovCartVars.checkoutUrl : "/checkout/";
//           window.location.href = redirectUrl;
//         } else {
//           alert("❌ Greška: nevažeći odgovor sa servera.");
//           console.warn("📦 Invalid response (expected JSON, got HTML):", response);
//           $btn.prop("disabled", false).text("Book Now");
//         }
//       })
//       .fail(function (err) {
//         alert("❌ Server nije odgovorio. Pokušaj ponovo.");
//         console.error("🔥 AJAX fail:", err);
//         $btn.prop("disabled", false).text("Book Now");
//       });
//   });
// });




// jQuery(document).ready(function ($) {
//   $("form.cart").on("submit", function (e) {
//     if (typeof window.selectedDatesArr === "undefined" || window.selectedDatesArr.length === 0) {
//       alert("Molimo izaberite datume pre nego što nastavite.");
//       e.preventDefault();
//       return false;
//     }

//     const startDate = window.selectedDatesArr[0];
//     const endDate = window.selectedDatesArr[window.selectedDatesArr.length - 1];
//     const allDates = window.selectedDatesArr;

//     $("#start_date_input").val(startDate);
//     $("#end_date_input").val(endDate);
//     $("#all_dates_input").val(allDates.join(","));
//     $("#guest_input").val($("#guest_selector").val() || 1); // ako koristiš dropdown, ili izmeni po potrebi
//   });
// });





// document.addEventListener("DOMContentLoaded", function () {
//   initOvDateRangePicker({
//     input: "#custom-daterange-input",
//     container: "#date-range-picker",
//     readonly: false,
//     alwaysOpen: false,
//     locale: "sr-RS",
//     calendarData: window.ov_calendar_vars?.calendarData || {},
//     totalsContainer: "#ov_total_container",
//     initialStartDate: window.ov_calendar_vars?.startDate || null,
//     initialEndDate: window.ov_calendar_vars?.endDate || null,
//   });

//   initOvDateRangePicker({
//     container: "#ov-booking_readonly_calendar",
//     readonly: true,
//     alwaysOpen: true,
//     locale: "sr-RS",
//     calendarData: window.ov_calendar_vars?.calendarData || {},
//   });
// });

jQuery(document).ready(function ($) {
  console.log(ov_calendar_vars.calendarData);

  $('.ov-testimonials-carousel').owlCarousel({
    items: 1,
    center: true,
    loop: true,
    margin: 30,
    nav: false,
    dots: true,
    autoHeight: true,
    autoplay: true,
    autoplayTimeout: 5000,
    autoplayHoverPause: true,
    smartSpeed: 600,  // ubaci glatku animaciju
  });

});

document.addEventListener("DOMContentLoaded", () => {

  // 1) Inicijalizuj picker za unos datuma
  initOvDateRangePicker({
    input: "#custom-daterange-input",
    container: "#date-range-picker",
    readonly: false,
    alwaysOpen: false,
    locale: "sr-RS",
    calendarData: window.ov_calendar_vars?.calendarData || {},
    totalsContainer: "#ov_total_container",
    initialStartDate: window.ov_calendar_vars?.startDate || null,
    initialEndDate: window.ov_calendar_vars?.endDate || null,
  });

  // 2) Inicijalni readonly prikaz kalendara
  initOvDateRangePicker({
    container: "#ov-booking_readonly_calendar",
    readonly: true,
    alwaysOpen: true,
    locale: "sr-RS",
    calendarData: window.ov_calendar_vars?.calendarData || {},
  });

  // 3) Validacija pre slanja forme
  const form = document.querySelector("form.ov-booking-form");
  if (!form) return;

  form.addEventListener("submit", (e) => {
    const startDate = form.querySelector('input[name="start_date"]')?.value.trim();
    const endDate = form.querySelector('input[name="end_date"]')?.value.trim();
    const allDates = form.querySelector('input[name="all_dates"]')?.value.trim();

    console.log("Submitting booking with dates:", startDate, endDate, allDates);

    if (!startDate || !endDate || !allDates) {
      e.preventDefault();
      alert("Molimo izaberite datume pre nego što nastavite.");
      return;
    }

    const btn = form.querySelector('button[type="submit"]');
    if (btn) {
      btn.disabled = true;
      btn.textContent = "Processing...";
    }
  });
});
