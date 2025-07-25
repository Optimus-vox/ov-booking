jQuery(document).ready(function ($) {
  $('.ov-testimonials-carousel').owlCarousel({
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
    smartSpeed: 600,  // ubaci glatku animaciju
  });
});

// File: assets/js/ov-single.js
document.addEventListener("DOMContentLoaded", function () {
  // --- Helpers -------------------------------------------------------------
  function getQueryParam(name) {
    const url = new URL(window.location.href);
    return url.searchParams.get(name);
  }

  // Kloniraj calendarData i izbriši flag “booked” za trenutnu rezervaciju
  const rawCalendarData = window.ov_calendar_vars?.calendarData || {};
  const calendarData = JSON.parse(JSON.stringify(rawCalendarData));

  const ovStartDate = getQueryParam("ov_start_date");
  const ovEndDate = getQueryParam("ov_end_date");
  const ovGuests = getQueryParam("ov_guests");

  if (ovGuests) {
    const sel = document.querySelector("#ov-guests");
    if (sel) sel.value = ovGuests;
  }

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

  // --------------------------------------------------
  // Popuni inpute i summary (#ov_total_container)
  // --------------------------------------------------
  // function populateFieldsFromStrings(startStr, endStr) {
  //   if (!window.moment) return;
  //   const start = moment(startStr, "YYYY-MM-DD");
  //   const end = moment(endStr, "YYYY-MM-DD");

  //   // Popuni vidljivi input
  //   const input = document.querySelector("#custom-daterange-input");
  //   if (input) {
  //     input.value = `${start.format("DD/MM/YYYY")} - ${end.format("DD/MM/YYYY")}`;
  //   }

  //   // Hidden inputs za WC
  //   document.querySelector("#start_date").value = startStr;
  //   document.querySelector("#end_date").value = endStr;

  //   // Sredi niz datuma
  //   const dates = [];
  //   let dt = new Date(startStr);
  //   const dtEnd = new Date(endStr);
  //   while (dt <= dtEnd) {
  //     dates.push(dt.toISOString().slice(0, 10));
  //     dt.setDate(dt.getDate() + 1);
  //   }
  //   document.querySelector("#all_dates").value = dates.join(",");

  //   // Izračunaj noći i cenu
  //   const totalNights = Math.max(0, dates.length - 1);
  //   let totalPrice = 0;
  //   for (let i = 0; i < dates.length - 1; i++) {
  //     totalPrice += parseFloat(calendarData[dates[i]]?.price || 0);
  //   }

  //   // Ažuriraj i renderuj cene za ukupan broj noci #ov_total_container
  //   const container = document.querySelector("#ov_total_container");
  //   if (container) {
  //     container.innerHTML = `
  //       <div class="ov-price-summary-wrapper">
  //         <span class="ov-summary-price"><b>€${totalPrice.toFixed(2)}</b></span>
  //         <span class="ov-summary-nights">
  //           za ${totalNights} ${totalNights === 1 ? "noć" : "noći"}
  //         </span>
  //       </div>`;
  //   }
  // }

  function populateFieldsFromStrings(startStr, endStr) {
    if (!window.moment) return;

    const start = moment(startStr, "YYYY-MM-DD");
    const endMoment = moment(endStr, "YYYY-MM-DD");

    // prevedeni placeholder za "Select end date"
    const placeholderEnd = typeof ovBookingI18n !== "undefined" && ovBookingI18n.selectEndDate ? ovBookingI18n.selectEndDate : "";

    // 1) Popuni vidljivi input
    const input = document.querySelector("#custom-daterange-input");
    if (input) {
      const formattedStart = start.format("DD/MM/YYYY");
      const formattedEnd = endMoment.isValid() ? endMoment.format("DD/MM/YYYY") : placeholderEnd;
      input.value = `${formattedStart} – ${formattedEnd}`;
    }

    // 2) Skrivena WC polja
    const startInput = document.querySelector("#start_date");
    const endInput = document.querySelector("#end_date");
    const allInput = document.querySelector("#all_dates");
    if (startInput) startInput.value = startStr;

    let dates = [];
    if (endMoment.isValid()) {
      // ako je end valid, popuni end_date
      if (endInput) endInput.value = endStr;

      // napravi niz datuma između start i end (uključivo oba)
      let dt = new Date(startStr);
      const dtEnd = new Date(endStr);
      while (dt <= dtEnd) {
        dates.push(dt.toISOString().slice(0, 10));
        dt.setDate(dt.getDate() + 1);
      }
    } else {
      // ako nije validan, izbriši end i all_dates
      if (endInput) endInput.value = "";
      dates = [];
    }

    if (allInput) {
      allInput.value = dates.join(",");
    }

    // 3) Izračunaj noći i cenu (samo ako imamo validan end)
    const totalNights = endMoment.isValid() ? Math.max(0, dates.length - 1) : 0;

    let totalPrice = 0;
    if (endMoment.isValid()) {
      for (let i = 0; i < dates.length - 1; i++) {
        totalPrice += parseFloat(calendarData[dates[i]]?.price || 0);
      }
    }

    // 4) Ažuriraj summary
    const container = document.querySelector("#ov_total_container");
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

  // --- Initialize date-range pickers --------------------------------------
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

  // --- On load, popuni i highlight ----------------------------------------
  if (ovStartDate && ovEndDate) {
    populateFieldsFromStrings(ovStartDate, ovEndDate);

    const pickContainer = document.querySelector("#date-range-picker");
    if (pickContainer) {
      // start
      pickContainer.querySelectorAll(`[data-date="${ovStartDate}"]`).forEach((el) => el.classList.add("selected", "start-date"));
      // end
      pickContainer.querySelectorAll(`[data-date="${ovEndDate}"]`).forEach((el) => el.classList.add("selected", "end-date"));
      // between
      let d = new Date(ovStartDate);
      d.setDate(d.getDate() + 1);
      const end = new Date(ovEndDate);
      while (d < end) {
        const key = d.toISOString().slice(0, 10);
        pickContainer.querySelectorAll(`[data-date="${key}"]`).forEach((el) => el.classList.add("in-range"));
        d.setDate(d.getDate() + 1);
      }
    }
  }

  // --- Form validacija ----------------------------------------------------
  const form = document.querySelector("form.ov-booking-form");
  if (form) {
    form.addEventListener("submit", function (e) {
      const s = form.querySelector('input[name="start_date"]')?.value.trim();
      const en = form.querySelector('input[name="end_date"]')?.value.trim();
      const all = form.querySelector('input[name="all_dates"]')?.value.trim();
      if (!s || !en || !all) {
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
  }

  // --- Form validacija (samo ako nema GET parametara) ----------------------
  // const form = document.querySelector("form.ov-booking-form");
  // if (form) {
  //   form.addEventListener("submit", function (e) {
  //     const s = form.querySelector('input[name="start_date"]')?.value.trim();
  //     const en = form.querySelector('input[name="end_date"]')?.value.trim();
  //     const all = form.querySelector('input[name="all_dates"]')?.value.trim();
  //     // ovStartDate i ovEndDate dolaze iz GET-a, postavljeni gore u skripti
  //     if ((!s || !en || !all) && !(ovStartDate && ovEndDate)) {
  //       e.preventDefault();
  //       alert("Molimo izaberite datume pre nego što nastavite.");
  //       return;
  //     }
  //     const btn = form.querySelector('button[type="submit"]');
  //     if (btn) {
  //       btn.disabled = true;
  //       btn.textContent = "Processing...";
  //     }
  //   });
  // }
});
