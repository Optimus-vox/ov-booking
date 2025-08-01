// jQuery(document).ready(function ($) {
//   $('.ov-testimonials-carousel').owlCarousel({
//     items: 1,
//     center: true,
//     loop: true,
//     margin: 30,
//     nav: false,
//     dots: true,
//     autoHeight: true,
//     autoplay: false,
//     autoplayTimeout: 10000,
//     autoplayHoverPause: true,
//     smartSpeed: 600,  // ubaci glatku animaciju
//   });
// });

// // File: assets/js/ov-single.js
// document.addEventListener("DOMContentLoaded", function () {
//   // --- Helpers -------------------------------------------------------------
//   function getQueryParam(name) {
//     const url = new URL(window.location.href);
//     return url.searchParams.get(name);
//   }

//   // Kloniraj calendarData i izbriši flag “booked” za trenutnu rezervaciju
//   const rawCalendarData = window.ovbProductVars?.calendar_data ?? window.ovbAdminCalendar?.calendarData ?? {};
//   const calendarData = JSON.parse(JSON.stringify(rawCalendarData));

//   const ovStartDate = getQueryParam("ovb_start_date");
//   const ovEndDate = getQueryParam("ovb_end_date");
//   const ovGuests = getQueryParam("ovb_guests");

//   if (ovGuests) {
//     const sel = document.querySelector("#ov-guests");
//     if (sel) sel.value = ovGuests;
//   }

//   if (ovStartDate && ovEndDate) {
//     let d = new Date(ovStartDate);
//     const end = new Date(ovEndDate);
//     while (d <= end) {
//       const key = d.toISOString().slice(0, 10);
//       if (calendarData[key]?.status === "booked") {
//         calendarData[key].status = "available";
//       }
//       d.setDate(d.getDate() + 1);
//     }
//   }

//   // --------------------------------------------------
//   // Popuni inpute i summary (#ovb_total_container)
//   // --------------------------------------------------

//   function populateFieldsFromStrings(startStr, endStr) {
//     if (!window.moment) return;

//     const start = moment(startStr, "YYYY-MM-DD");
//     const endMoment = moment(endStr, "YYYY-MM-DD");

//     // prevedeni placeholder za "Select end date"
//     const placeholderEnd = typeof ovBookingI18n !== "undefined" && ovBookingI18n.selectEndDate ? ovBookingI18n.selectEndDate : "";

//     // 1) Popuni vidljivi input
//     const input = document.querySelector("#custom-daterange-input");
//     if (input) {
//       const formattedStart = start.format("DD/MM/YYYY");
//       const formattedEnd = endMoment.isValid() ? endMoment.format("DD/MM/YYYY") : placeholderEnd;
//       input.value = `${formattedStart} – ${formattedEnd}`;
//     }

//     // 2) Skrivena WC polja
//     const startInput = document.querySelector("#start_date");
//     const endInput = document.querySelector("#end_date");
//     const allInput = document.querySelector("#all_dates");
//     if (startInput) startInput.value = startStr;

//     let dates = [];
//     if (endMoment.isValid()) {
//       // ako je end valid, popuni end_date
//       if (endInput) endInput.value = endStr;

//       // napravi niz datuma između start i end (uključivo oba)
//       let dt = new Date(startStr);
//       const dtEnd = new Date(endStr);
//       while (dt <= dtEnd) {
//         dates.push(dt.toISOString().slice(0, 10));
//         dt.setDate(dt.getDate() + 1);
//       }
//     } else {
//       // ako nije validan, izbriši end i all_dates
//       if (endInput) endInput.value = "";
//       dates = [];
//     }

//     if (allInput) {
//       allInput.value = dates.join(",");
//     }

//     // 3) Izračunaj noći i cenu (samo ako imamo validan end)
//     const totalNights = endMoment.isValid() ? Math.max(0, dates.length - 1) : 0;

//     let totalPrice = 0;
//     if (endMoment.isValid()) {
//       for (let i = 0; i < dates.length - 1; i++) {
//         totalPrice += parseFloat(calendarData[dates[i]]?.price || 0);
//       }
//     }

//     // 4) Ažuriraj summary
//     const container = document.querySelector("#ovb_total_container");
//     if (container) {
//       container.innerHTML = `
//       <div class="ov-price-summary-wrapper">
//         <span class="ov-summary-price"><b>${totalPrice.toFixed(2)} €</b></span>
//         <span class="ov-summary-nights">
//           za ${totalNights} ${totalNights === 1 ? "noć" : "noći"}
//         </span>
//       </div>`;
//     }
//   }

//   // --- Initialize date-range pickers --------------------------------------
//   if (typeof window.initOvDateRangePicker === "function") {
//     // Editable
//     window.initOvDateRangePicker({
//       input: "#custom-daterange-input",
//       container: "#date-range-picker",
//       readonly: false,
//       alwaysOpen: false,
//       locale: "sr-RS",
//       calendarData,
//       defaultStart: ovStartDate || null,
//       defaultEnd: ovEndDate || null,
//       onChange(startMoment, endMoment) {
//         populateFieldsFromStrings(startMoment.format("YYYY-MM-DD"), endMoment.format("YYYY-MM-DD"));
//       },
//     });

//     // Read-only
//     window.initOvDateRangePicker({
//       container: "#ov-booking_readonly_calendar",
//       readonly: true,
//       alwaysOpen: true,
//       locale: "sr-RS",
//       calendarData,
//       defaultStart: ovStartDate || null,
//       defaultEnd: ovEndDate || null,
//     });
//   }

//   // --- On load, popuni i highlight ----------------------------------------
//   if (ovStartDate && ovEndDate) {
//     populateFieldsFromStrings(ovStartDate, ovEndDate);

//     const pickContainer = document.querySelector("#date-range-picker");
//     if (pickContainer) {
//       // start
//       pickContainer.querySelectorAll(`[data-date="${ovStartDate}"]`).forEach((el) => el.classList.add("selected", "start-date"));
//       // end
//       pickContainer.querySelectorAll(`[data-date="${ovEndDate}"]`).forEach((el) => el.classList.add("selected", "end-date"));
//       // between
//       let d = new Date(ovStartDate);
//       d.setDate(d.getDate() + 1);
//       const end = new Date(ovEndDate);
//       while (d < end) {
//         const key = d.toISOString().slice(0, 10);
//         pickContainer.querySelectorAll(`[data-date="${key}"]`).forEach((el) => el.classList.add("in-range"));
//         d.setDate(d.getDate() + 1);
//       }
//     }
//   }

//   // --- Form validacija ----------------------------------------------------
//   // const form = document.querySelector("form.ov-booking-form");
//   // if (form) {
//   //   form.addEventListener("submit", function (e) {
//   //     const s = form.querySelector('input[name="start_date"]')?.value.trim();
//   //     const en = form.querySelector('input[name="end_date"]')?.value.trim();
//   //     const all = form.querySelector('input[name="all_dates"]')?.value.trim();
//   //     if (!s || !en || !all) {
//   //       e.preventDefault();
//   //       alert("Molimo izaberite datume pre nego što nastavite.");
//   //       return;
//   //     }
//   //     const btn = form.querySelector('button[type="submit"]');
//   //     if (btn) {
//   //       btn.disabled = true;
//   //       btn.textContent = "Processing...";
//   //     }
//   //   });
//   // }

//   const form = document.querySelector("form.ov-booking-form");
//   if (form) {
//     form.addEventListener("submit", function (e) {
//       // Pronađi polja koristeći različite selektore
//       const startField = form.querySelector('input[name="start_date"]') || form.querySelector("#start_date");
//       const endField = form.querySelector('input[name="end_date"]') || form.querySelector("#end_date");
//       const allField = form.querySelector('input[name="all_dates"]') || form.querySelector("#all_dates");

//       const s = startField?.value?.trim();
//       const en = endField?.value?.trim();
//       const all = allField?.value?.trim();

//       // Debug logging
//       console.log("Form submission validation:", {
//         startField: startField,
//         endField: endField,
//         allField: allField,
//         startValue: s,
//         endValue: en,
//         allValue: all,
//       });

//       // Ako polja nisu pronađena, pokušaj ponovo da ih populišeš
//       if ((!s || !en || !all) && ovStartDate && ovEndDate) {
//         console.log("Fields empty, trying to repopulate...");
//         populateFieldsFromStrings(ovStartDate, ovEndDate);

//         // Provjeri ponovo nakon repopulate
//         const sRetry = startField?.value?.trim();
//         const enRetry = endField?.value?.trim();
//         const allRetry = allField?.value?.trim();

//         if (!sRetry || !enRetry || !allRetry) {
//           e.preventDefault();
//           alert("Molimo izaberite datume pre nego što nastavite.");
//           return;
//         }
//       } else if (!s || !en || !all) {
//         e.preventDefault();
//         alert("Molimo izaberite datume pre nego što nastavite.");
//         return;
//       }

//       const btn = form.querySelector('button[type="submit"]');
//       if (btn) {
//         btn.disabled = true;
//         btn.textContent = "Processing...";
//       }
//     });
//   }

// });

jQuery(document).ready(function ($) {
  $(".ov-testimonials-carousel").owlCarousel({
    items: 1,
    center: true,
    loop: true,
    margin: 30,
    nav: false,
    dots: true,
    autoHeight: true,
    autoplay: false,
    autoplayTimeout: 10000,
    autoplayHoverPause: true,
    smartSpeed: 600,
  });
});

document.addEventListener("DOMContentLoaded", function () {
  // --- Helpers -------------------------------------------------------------
  function getQueryParam(name) {
    const url = new URL(window.location.href);
    return url.searchParams.get(name);
  }

  // Proveri URL parametre odmah
  const ovStartDate = getQueryParam("ovb_start_date");
  const ovEndDate = getQueryParam("ovb_end_date");
  const ovGuests = getQueryParam("ovb_guests");

  // Kloniraj calendarData i izbriši flag "booked" za trenutnu rezervaciju
  const rawCalendarData = window.ovbProductVars?.calendar_data ?? window.ovbAdminCalendar?.calendarData ?? {};
  const calendarData = JSON.parse(JSON.stringify(rawCalendarData));

  if (ovStartDate && ovEndDate) {
    let d = new Date(ovStartDate);
    const end = new Date(ovEndDate);
    while (d <= end) {
      const key = d.toISOString().slice(0, 10);
      if (calendarData[key]?.status === "booked") {
        calendarData[key].status = "available";
      }
      d.setDate(d.getDate() + 1);
    }
  }

  // --- Glavna funkcija za popunjavanje polja -------------------------------
  function populateFieldsFromStrings(startStr, endStr) {
    if (!window.moment) return;

    const start = moment(startStr, "YYYY-MM-DD");
    const endMoment = moment(endStr, "YYYY-MM-DD");
    const placeholderEnd = typeof ovBookingI18n !== "undefined" && ovBookingI18n.selectEndDate ? ovBookingI18n.selectEndDate : "";

    // 1) Popuni vidljivi input
    const input = document.querySelector("#custom-daterange-input");
    if (input) {
      const formattedStart = start.format("DD/MM/YYYY");
      const formattedEnd = endMoment.isValid() ? endMoment.format("DD/MM/YYYY") : placeholderEnd;
      input.value = `${formattedStart} – ${formattedEnd}`;
    }

    // 2) Popuni skrivena polja
    const startInput = document.querySelector("#start_date");
    const endInput = document.querySelector("#end_date");
    const allInput = document.querySelector("#all_dates");

    if (startInput) startInput.value = startStr;
    if (endInput) endInput.value = endMoment.isValid() ? endStr : "";

    let dates = [];
    if (endMoment.isValid()) {
      let dt = new Date(startStr);
      const dtEnd = new Date(endStr);
      while (dt <= dtEnd) {
        dates.push(dt.toISOString().slice(0, 10));
        dt.setDate(dt.getDate() + 1);
      }
    }

    if (allInput) {
      allInput.value = dates.join(",");
    }

    // 3) Izračunaj noći i cenu
    const totalNights = endMoment.isValid() ? Math.max(0, dates.length - 1) : 0;
    let totalPrice = 0;

    if (endMoment.isValid()) {
      for (let i = 0; i < dates.length - 1; i++) {
        totalPrice += parseFloat(calendarData[dates[i]]?.price || 0);
      }
    }

    // 4) Ažuriraj summary
    const container = document.querySelector("#ovb_total_container");
    if (container) {
      container.innerHTML = `
      <div class="ov-price-summary-wrapper">
        <span class="ov-summary-price"><b>${totalPrice.toFixed(2)} €</b></span>
        <span class="ov-summary-nights">
          za ${totalNights} ${totalNights === 1 ? "noć" : "noći"}
        </span>
      </div>`;
    }
  }

  // --- Inicijalizacija i automatsko popunjavanje --------------------------
  function initCalendarAndPopulate() {
    // Inicijalizuj kalendar
    if (typeof window.initOvDateRangePicker === "function") {
      // Editable
      window.initOvDateRangePicker({
        input: "#custom-daterange-input",
        container: "#date-range-picker",
        readonly: false,
        alwaysOpen: false,
        locale: "sr-RS",
        calendarData,
        defaultStart: ovStartDate || null,
        defaultEnd: ovEndDate || null,
        onChange(startMoment, endMoment) {
          populateFieldsFromStrings(startMoment.format("YYYY-MM-DD"), endMoment.format("YYYY-MM-DD"));
        },
      });

      // Read-only
      window.initOvDateRangePicker({
        container: "#ov-booking_readonly_calendar",
        readonly: true,
        alwaysOpen: true,
        locale: "sr-RS",
        calendarData,
        defaultStart: ovStartDate || null,
        defaultEnd: ovEndDate || null,
      });
    }

    // Automatski popuni polja ako postoje URL parametri
    if (ovStartDate && ovEndDate) {
      populateFieldsFromStrings(ovStartDate, ovEndDate);

      // Sačekaj da kalendar bude spreman pre obeležavanja
      setTimeout(() => {
        const pickContainer = document.querySelector("#date-range-picker");
        if (pickContainer) {
          // Obeleži početni datum
          const startCell = pickContainer.querySelector(`[data-date="${ovStartDate}"]`);
          if (startCell) {
            startCell.classList.add("selected", "start-date");
          }

          // Obeleži krajnji datum
          const endCell = pickContainer.querySelector(`[data-date="${ovEndDate}"]`);
          if (endCell) {
            endCell.classList.add("selected", "end-date");
          }

          // Obeleži datume između
          let d = new Date(ovStartDate);
          d.setDate(d.getDate() + 1);
          const end = new Date(ovEndDate);
          while (d < end) {
            const key = d.toISOString().slice(0, 10);
            const betweenCell = pickContainer.querySelector(`[data-date="${key}"]`);
            if (betweenCell) {
              betweenCell.classList.add("in-range");
            }
            d.setDate(d.getDate() + 1);
          }
        }
      }, 500);
    }
  }

  // --- Popuni goste -------------------------------------------------------
  if (ovGuests) {
    const sel = document.querySelector("#ov-guests");
    if (sel) sel.value = ovGuests;
  }

  // --- Pokreni inicijalizaciju --------------------------------------------
  initCalendarAndPopulate();

  // --- Poboljšana form validacija -----------------------------------------
  const form = document.querySelector("form.ov-booking-form");
  if (form) {
    form.addEventListener("submit", function (e) {
      // Prvo proveri URL parametre
      let s = ovStartDate;
      let en = ovEndDate;
      let all = "";

      // Ako imamo datume iz URL-a, generiši all_dates
      if (s && en) {
        let dt = new Date(s);
        const end = new Date(en);
        while (dt <= end) {
          all += dt.toISOString().slice(0, 10) + ",";
          dt.setDate(dt.getDate() + 1);
        }
        all = all.slice(0, -1); // Ukloni zadnji zarez
      }

      // Ako nema URL parametara, uzmi iz forme
      if (!s) s = form.querySelector('input[name="start_date"]')?.value?.trim();
      if (!en) en = form.querySelector('input[name="end_date"]')?.value?.trim();
      if (!all) all = form.querySelector('input[name="all_dates"]')?.value?.trim();

      // Proveri da li su sva polja popunjena
      if (!s || !en || !all) {
        e.preventDefault();
        alert("Molimo izaberite datume pre nego što nastavite.");
        return;
      }

      // Ako su polja bila prazna, popuni ih za sledeći put
      if (ovStartDate && ovEndDate) {
        const startInput = form.querySelector('input[name="start_date"]');
        const endInput = form.querySelector('input[name="end_date"]');
        const allInput = form.querySelector('input[name="all_dates"]');

        if (startInput) startInput.value = s;
        if (endInput) endInput.value = en;
        if (allInput) allInput.value = all;
      }

      // Onemogući dugme tokom procesiranja
      const btn = form.querySelector('button[type="submit"]');
      if (btn) {
        btn.disabled = true;
        btn.textContent = "Processing...";
      }
    });
  }
});