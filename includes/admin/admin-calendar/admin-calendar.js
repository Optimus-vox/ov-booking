document.addEventListener("DOMContentLoaded", function () {
  var CURRENT_DATE = new Date();
  var d = new Date();
  var content =
    "Januar Februar Mart April Maj Jun Jul Avgust Septembar Oktobar Novembar Decembar".split(
      " "
    );
  var daysOfMonth = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
  // let calendarData =
  //   typeof ov_calendar_vars !== "undefined" && ov_calendar_vars.calendarData
  //     ? ov_calendar_vars.calendarData
  //     : {};

  let calendarData = {};
  if (typeof ov_calendar_vars !== "undefined" && ov_calendar_vars.calendarData) {
    // Preslikaj sve ključeve iz ov_calendar_vars.calendarData u novi JS-objekt
    for (const key in ov_calendar_vars.calendarData) {
      if (Object.prototype.hasOwnProperty.call(ov_calendar_vars.calendarData, key)) {
        calendarData[key] = ov_calendar_vars.calendarData[key];
      }
    }
  }

  let definedPriceTypes =
    typeof ov_calendar_vars.priceTypes !== "undefined"
      ? ov_calendar_vars.priceTypes
      : {
        regular: "",
        weekend: "",
        discount: "",
        custom: "",
      };

  function jsDayToMondayFirst(day) {
    return (day + 6) % 7;
  }

  function renderCalendar(startDay, totalDays, currentDate, month, year) {
    const calendar = document.querySelector(".admin-table tbody");
    if (!calendar) return;
    calendar.innerHTML = "";

    let currentDay = startDay;
    let $week = getCalendarRow();

    for (let i = 1; i <= totalDays; i++) {
      let $day = $week.find("td").eq(currentDay);
      const formattedDate = `${year}-${String(month + 1).padStart(2, "0")}-${String(i).padStart(2, "0")}`;
      const data = calendarData[formattedDate] || {};
      const hasClient = !!data.client;

      const dayDate = new Date(year, month, i);
      const today = new Date();
      today.setHours(0, 0, 0, 0);

      const isPast = dayDate < today;

      // 🔥 Izmena: Ako nema cene i nema klijenta, automatski status postaje "unavailable"
      if (!data.price && !hasClient) {
        data.status = "unavailable";
      }

      if (isPast) {
        $day.addClass("past-day");
      }

      calendarData[formattedDate] = {
        ...calendarData[formattedDate],
        isPast: isPast,
      };

      // Ako postoji klijent, automatski setuj status na "booked"
      if (hasClient) {
        calendarData[formattedDate].status = "booked";
      }

      let dayHTML = `
      <div class="day-wrapper">
        <div class="day-header">
          <div class="day-number ${i === currentDate ? "today-number" : ""}">${i}</div>
          <div class="price-row">
            ${isPast ? `<span class="past-badge">Past</span>` : ""}
            <div class="day-price editable-price" data-date="${formattedDate}">
            ${!isPast
          ? `<svg width="10" height="9" viewBox="0 0 10 9" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <path d="M6.98958 1.50002L8.55556 3.06599C8.62153 3.13196 8.62153 3.2396 8.55556 3.30558L4.76389 7.09724L3.15278 7.27606C2.9375 7.30037 2.75521 7.11808 2.77951 6.9028L2.95833 5.29169L6.75 1.50002C6.81597 1.43405 6.92361 1.43405 6.98958 1.50002ZM9.80208 1.10245L8.95486 0.255229C8.69097 -0.00866021 8.26215 -0.00866021 7.99653 0.255229L7.38195 0.869812C7.31597 0.935784 7.31597 1.04342 7.38195 1.1094L8.94792 2.67537C9.01389 2.74134 9.12153 2.74134 9.1875 2.67537L9.80208 2.06078C10.066 1.79516 10.066 1.36634 9.80208 1.10245ZM6.66667 6.06599V7.83335H1.11111V2.2778H5.10069C5.15625 2.2778 5.20833 2.25523 5.24826 2.21703L5.94271 1.52259C6.07465 1.39065 5.9809 1.16669 5.79514 1.16669H0.833333C0.373264 1.16669 0 1.53995 0 2.00002V8.11113C0 8.5712 0.373264 8.94446 0.833333 8.94446H6.94444C7.40451 8.94446 7.77778 8.5712 7.77778 8.11113V5.37155C7.77778 5.18578 7.55382 5.09377 7.42188 5.22398L6.72743 5.91842C6.68924 5.95835 6.66667 6.01044 6.66667 6.06599Z" fill="#111827"/>
                    </svg>` : ""}
            ${data.price ? data.price + "€" : "Dodaj cenu"} 
              <span class="tooltip-text">
                Cena: ${data.price ? data.price + "€" : "Nije postavljeno"}</br>
                Tip cene: ${data.priceType ? data.priceType : "Nema"}
              </span>
            </div>
          </div>
        </div>
    `;

      if (hasClient) {
        const client = data.client;
        dayHTML += `
        <div class="day-client clickable-client"
             data-date="${formattedDate}"
             data-email="${client.email}"
             data-guests="${client.guests}"
             data-phone="${client.phone}"
             data-firstname="${client.firstName}"
             data-lastname="${client.lastName}">
          ${client.firstName} ${client.lastName}
        </div>
      `;
      } else {
        dayHTML += `<div class="day-actions">`;

        if (!hasClient && data.status !== "booked" && !isPast) {
          dayHTML += `
            <button class="add-client-button" data-date="${formattedDate}" title="Dodaj korisnika">
              <svg width="15" height="15" viewBox="0 0 15 15" fill="none" xmlns="http://www.w3.org/2000/svg">
                <g clip-path="url(#clip0_419_1770)">
                  <path d="M13.125 6.09375H8.90625V1.875C8.90625 1.35732 8.48643 0.9375 7.96875 0.9375H7.03125C6.51357 0.9375 6.09375 1.35732 6.09375 1.875V6.09375H1.875C1.35732 6.09375 0.9375 6.51357 0.9375 7.03125V7.96875C0.9375 8.48643 1.35732 8.90625 1.875 8.90625H6.09375V13.125C6.09375 13.6427 6.51357 14.0625 7.03125 14.0625H7.96875C8.48643 14.0625 8.90625 13.6427 8.90625 13.125V8.90625H13.125C13.6427 8.90625 14.0625 8.48643 14.0625 7.96875V7.03125C14.0625 6.51357 13.6427 6.09375 13.125 6.09375Z" fill="#7C3AED"/>
                </g>
                <defs>
                  <clipPath id="clip0_419_1770">
                    <rect width="15" height="15" fill="white"/>
                  </clipPath>
                </defs>
              </svg>
            </button>
        `;
        }

        if (!hasClient || data.status !== "booked") {
          dayHTML += `
            <select class="ov-status-select" data-date="${formattedDate}">
              <option value="available" ${data.status === "available" ? "selected" : ""}>Available</option>
              <option value="unavailable" ${data.status === "unavailable" ? "selected" : ""}>Unavailable</option>
              <option value="booked" ${data.status === "booked" ? "selected" : ""}>Booked</option>
            </select>
        `;
        }
      }

      dayHTML += `</div>`;

      $day.html(dayHTML);
      const selectEl = $day.find(".ov-status-select")[0];
      if (selectEl) {
        function updateSelectBackground() {
          const value = selectEl.value;
          selectEl.classList.remove("status-available", "status-unavailable", "status-booked");
          if (!isPast) {
            if (value === "available") {
              selectEl.classList.add("status-available");
            } else if (value === "unavailable") {
              selectEl.classList.add("status-unavailable");
            } else if (value === "booked") {
              selectEl.classList.add("status-booked");
            }
          } else {
            selectEl.value = "unavailable";
            selectEl.classList.add("status-unavailable");
          }
        }
        updateSelectBackground();
        selectEl.addEventListener("change", updateSelectBackground);
      }

      $day.attr(
        "data-title",
        `Datum: ${formattedDate}
    Cena: ${data.price || "-"}
    Tip: ${data.priceType || "-"}
    Status: ${calendarData[formattedDate]?.status || "-"}`
      );
      $day.addClass("current-month");
      if (i === currentDate) $day.addClass("today");

      $day.find(".ov-status-select").on("change", function () {
        const newStatus = jQuery(this).val();
        const dateKey = jQuery(this).data("date");

        calendarData[dateKey] = {
          ...calendarData[dateKey],
          status: newStatus,
        };
        const productId = jQuery("#ov_product_id").val();
        jQuery.ajax({
          url: ov_calendar_vars.ajax_url,
          method: "POST",
          data: {
            action: "ov_save_calendar_data",
            product_id: productId,
            calendar_data: JSON.stringify(calendarData),
            price_types: ov_calendar_vars.priceTypes,
          },
          dataType: "json",
          success: function (res) {
            console.log("Status dan sačuvan:", res);
          },
          error: function (err) {
            console.error("Greška pri čuvanju statusa dana:", err);
          },
        });
      });

      currentDay = ++currentDay % 7;
      if (currentDay === 0 && i + 1 <= totalDays) {
        $week = getCalendarRow();
      }
    }
  }


  function clearCalendar() {
    jQuery(".month-year").empty();
    jQuery(".admin-table tbody").empty();
  }

  function getCalendarRow() {
    var $tbody = jQuery(".admin-table tbody");
    var $tr = jQuery("<tr/>");
    for (var i = 0; i < 7; i++) {
      $tr.append(jQuery("<td/>"));
    }
    $tbody.append($tr);
    return $tr;
  }
  // 12
  // 🔧 NOVO – Show/hide custom range polja
  jQuery("#apply_rule").on("change", function () {
    if (jQuery(this).val() === "custom") {
      jQuery("#daterange_container").show();
    } else {
      jQuery("#daterange_container").hide();
    }
  });

  // 🔧 NOVO – Init daterangepicker
  jQuery(function () {
    jQuery("#daterange").daterangepicker({
      locale: {
        format: "DD/MM/YYYY",
      },
    });
  });

  // Init status daterange
  jQuery("#status_daterange").daterangepicker({
    locale: { format: "DD/MM/YYYY" },
  });

  jQuery("#status_apply_rule").on("change", function () {
    if (jQuery(this).val() === "custom") {
      jQuery("#status_daterange_container").show();
    } else {
      jQuery("#status_daterange_container").hide();
    }
  });

  // Sačuvaj definisane cene
  jQuery("#save_price_types").on("click", function () {

    Swal.fire({
      title: "Save price types?",
      text: "Are you sure you want to save the updated prices (Regular, Weekend, Discount, and Custom)?",
      icon: "question",
      showCancelButton: true,
      confirmButtonText: "Yes, save",
      cancelButtonText: "Cancel"
    }).then((result) => {
      if (!result.isConfirmed) {
        return; // User clicked "Cancel" → Do nothing
      }

      // ✅ User confirmed → Continue
      definedPriceTypes.regular = Number(jQuery("#regular_price").val());
      definedPriceTypes.weekend = Number(jQuery("#weekend_price").val());
      definedPriceTypes.discount = Number(jQuery("#discount_price").val());
      definedPriceTypes.custom = Number(jQuery("#custom_price").val());

      const selectedStatus = jQuery("#bulk_status").val();
      const statusRule = jQuery("#status_apply_rule").val();
      const month = d.getUTCMonth();
      const year = d.getUTCFullYear();
      const daysInMonth = new Date(year, month + 1, 0).getDate();

      if (selectedStatus && statusRule) {
        for (let i = 1; i <= daysInMonth; i++) {
          const dateStr = `${year}-${String(month + 1).padStart(2, "0")}-${String(
            i
          ).padStart(2, "0")}`;
          const dateObj = new Date(dateStr);
          const dayOfWeek = dateObj.getUTCDay();

          if (
            (statusRule === "weekdays" && dayOfWeek >= 1 && dayOfWeek <= 5) ||
            (statusRule === "weekends" && (dayOfWeek === 0 || dayOfWeek === 6)) ||
            statusRule === "full_month"
          ) {
            calendarData[dateStr] = {
              ...calendarData[dateStr],
              status: selectedStatus,
              price: calendarData[dateStr]?.price || null,
              priceType: calendarData[dateStr]?.priceType || null,
              client: calendarData[dateStr]?.client || null,
            };
          }

          if (statusRule === "custom") {
            const picker = jQuery("#status_daterange").data("daterangepicker");
            const start = picker.startDate;
            const end = picker.endDate;
            const target = new Date(dateStr);
            if (target >= start.toDate() && target <= end.toDate()) {
              calendarData[dateStr] = {
                ...calendarData[dateStr],
                status: selectedStatus,
                price: calendarData[dateStr]?.price || null,
                priceType: calendarData[dateStr]?.priceType || null,
                client: calendarData[dateStr]?.client || null,
              };
            }
          }
        }

        myCalendar();
      }

      const productId = jQuery("#ov_product_id").val();
      jQuery.ajax({
        url: ov_calendar_vars.ajax_url,
        method: "POST",
        data: {
          action: "ov_save_calendar_data",
          product_id: productId,
          calendar_data: JSON.stringify(calendarData),
          price_types: definedPriceTypes,
        },
        success: function (res) {
          Swal.fire("Success!", "Data successfully saved.", "success");
        },
        error: function (err) {
          Swal.fire("Error!", "Error occurred while saving.", "error");
          console.error("Error occurred:", err);
        },
      });
    });
  });


  // Sačuvaj definisane cene
  jQuery("#client_date_range").daterangepicker({
    locale: { format: "DD/MM/YYYY" },
    autoUpdateInput: true,
    opens: "right",
  });

  jQuery("#apply_price").on("click", function () {
    const priceType = jQuery("#price_type").val();
    const rule = jQuery("#apply_rule").val();
    const selectedPrice = definedPriceTypes[priceType];
    const selectedStatus = jQuery("#bulk_status").val(); // može biti prazan string

    if (selectedPrice === null || isNaN(selectedPrice)) {
      return Swal.fire("Error", "Unesi validnu cenu za izabrani tip.", "error");
    }

    Swal.fire({
      title: 'Apply price?',
      text: `Price type: ${priceType}, Rule: ${rule}. Are you sure?`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Yes, apply',
      cancelButtonText: 'Cancel'
    }).then((result) => {
      if (!result.isConfirmed) return;

      // Dobavi trenutni datum (nedostajao ti je d u originalu)
      const d = new Date();
      const month = d.getUTCMonth();
      const year = d.getUTCFullYear();
      const daysInMonth = new Date(year, month + 1, 0).getDate();

      for (let i = 1; i <= daysInMonth; i++) {
        const dateStr = `${year}-${String(month + 1).padStart(2, "0")}-${String(i).padStart(2, "0")}`;
        const dayOfWeek = new Date(year, month, i).getDay();

        if (
          (rule === "weekdays" && dayOfWeek >= 1 && dayOfWeek <= 5) ||
          (rule === "weekends" && (dayOfWeek === 0 || dayOfWeek === 6)) ||
          rule === "full_month"
        ) {
          calendarData[dateStr] = {
            ...calendarData[dateStr],
            price: selectedPrice,
            priceType: priceType,
            status: selectedStatus || calendarData[dateStr]?.status || "available",
            client: calendarData[dateStr]?.client || null,
          };
        }

        // Primeni za custom interval
        if (rule === "custom") {
          const picker = jQuery("#daterange").data("daterangepicker");
          if (!picker) {
            return Swal.fire("Error", "Date range picker nije inicijalizovan.", "error");
          }
          const start = picker.startDate.startOf('day');
          const end = picker.endDate.startOf('day');
          let current = moment(start);

          while (current.isSameOrBefore(end)) {
            const dateStr = current.format("YYYY-MM-DD");
            calendarData[dateStr] = {
              ...calendarData[dateStr],
              price: selectedPrice,
              priceType: priceType,
              status: selectedStatus || calendarData[dateStr]?.status || "available",
              client: calendarData[dateStr]?.client || null,
            };
            current.add(1, "days");
          }
        }
      }

      myCalendar(); // osveži prikaz

      const productId = jQuery("#ov_product_id").val();

      jQuery.ajax({
        url: ov_calendar_vars.ajax_url,
        method: "POST",
        data: {
          action: "ov_save_calendar_data",
          product_id: productId,
          calendar_data: JSON.stringify(calendarData),
          price_types: ov_calendar_vars.priceTypes,
        },
        dataType: "json",
        success: function (res) {
          Swal.fire("Success!", "Cene su uspešno sačuvane.", "success");
          console.log("Sačuvano:", res);
        },
        error: function (err) {
          Swal.fire("Error", "Greška prilikom čuvanja podataka.", "error");
          console.error("Greška prilikom čuvanja podataka", err);
        },
      });

      console.log(calendarData, "apply data");
    });
  });


  jQuery("#apply_status").on("click", function () {
    const selectedStatus = jQuery("#bulk_status").val();
    const rule = jQuery("#status_apply_rule").val();
    const month = d.getUTCMonth();
    const year = d.getUTCFullYear();
    const daysInMonth = new Date(year, month + 1, 0).getDate();

    for (let i = 1; i <= daysInMonth; i++) {
      const dateStr = `${year}-${String(month + 1).padStart(2, "0")}-${String(
        i
      ).padStart(2, "0")}`;
      const dateObj = new Date(dateStr);
      const dayOfWeek = dateObj.getUTCDay();

      if (
        (rule === "weekdays" && dayOfWeek >= 1 && dayOfWeek <= 5) ||
        (rule === "weekends" && (dayOfWeek === 0 || dayOfWeek === 6)) ||
        rule === "full_month"
      ) {
        calendarData[dateStr] = {
          ...calendarData[dateStr],
          status: selectedStatus,
          price: calendarData[dateStr]?.price || null,
          priceType: calendarData[dateStr]?.priceType || null,
          client: calendarData[dateStr]?.client || null,
        };
      }

      if (rule === "custom") {
        const picker = jQuery("#status_daterange").data("daterangepicker");
        const start = picker.startDate.startOf('day');
        const end = picker.endDate.startOf('day');
        let current = moment(start);

        while (current.isSameOrBefore(end)) {
          const dateStr = current.format("YYYY-MM-DD");
          calendarData[dateStr] = {
            ...calendarData[dateStr],
            status: selectedStatus,
            price: calendarData[dateStr]?.price || null,
            priceType: calendarData[dateStr]?.priceType || null,
            client: calendarData[dateStr]?.client || null,
          };
          current.add(1, "days");
        }
      }

    }

    myCalendar();

    const productId = jQuery("#ov_product_id").val();
    jQuery.ajax({
      url: ov_calendar_vars.ajax_url,
      method: "POST",
      data: {
        action: "ov_save_calendar_data",
        product_id: productId,
        calendar_data: JSON.stringify(calendarData),
        price_types: definedPriceTypes,
      },
      success: function (res) {
        console.log("Status sačuvan:", res);
      },
      error: function (err) {
        console.error("Greška pri čuvanju statusa:", err);
      },
    });
  });

  //client modal and render in calendar
  jQuery("#client_modal_save").on("click", function () {
    const firstName = jQuery("#client_first_name").val();
    const lastName = jQuery("#client_last_name").val();
    const email = jQuery("#client_email").val();
    const phone = jQuery("#client_phone").val();
    const guests = jQuery("#client_guests").val();
    const range = jQuery("#client_date_range").data("daterangepicker");
    const start = range.startDate;
    const end = range.endDate;
    const bookingId = Date.now() + "_" + Math.floor(Math.random() * 10000);

    const startDate = new Date(start);
    const endDate = new Date(end);

    while (startDate <= endDate) {
      const dateStr = `${startDate.getFullYear()}-${String(
        startDate.getMonth() + 1
      ).padStart(2, "0")}-${String(startDate.getDate()).padStart(2, "0")}`;

      calendarData[dateStr] = {
        ...calendarData[dateStr],
        client: {
          bookingId,
          firstName,
          lastName,
          email,
          phone,
          guests,
          rangeStart: range.startDate.format("YYYY-MM-DD"),
          rangeEnd: range.endDate.format("YYYY-MM-DD"),
        },
        status: "booked",
      };
      startDate.setDate(startDate.getDate() + 1);
    }

    myCalendar();
    jQuery("#client_modal").hide();

    const productId = jQuery("#ov_product_id").val();
    jQuery.ajax({
      url: ov_calendar_vars.ajax_url,
      method: "POST",
      data: {
        action: "ov_save_calendar_data",
        product_id: productId,
        calendar_data: JSON.stringify(calendarData),
        price_types: definedPriceTypes,
      },
      success: function (res) {
        console.log("Klijent sačuvan:", res);
      },
      error: function (err) {
        console.error("Greška pri čuvanju klijenta:", err);
      },
    });
  });

  // 12

  function myCalendar() {
    var month = d.getUTCMonth();
    var year = d.getUTCFullYear();
    var date = d.getUTCDate();
    var totalDaysOfMonth = daysOfMonth[month];

    var $h3 = jQuery("<h3>").text(content[month] + " " + year);
    jQuery(".month-year").html($h3);

    if (
      month === 1 &&
      ((year % 4 === 0 && year % 100 !== 0) || year % 400 === 0)
    ) {
      totalDaysOfMonth = 29;
    }

    var startDay = jsDayToMondayFirst(
      new Date(Date.UTC(year, month, 1)).getUTCDay()
    );

    renderCalendar(
      startDay,
      totalDaysOfMonth,
      CURRENT_DATE.getUTCMonth() === month &&
        CURRENT_DATE.getUTCFullYear() === year
        ? date
        : 0,
      month,
      year
    );
  }

  function navigationHandler(dir) {
    d.setUTCMonth(d.getUTCMonth() + dir);
    clearCalendar();
    myCalendar();
  }

  jQuery(document).ready(function () {
    jQuery("#regular_price").val(definedPriceTypes.regular);
    jQuery("#weekend_price").val(definedPriceTypes.weekend);
    jQuery("#discount_price").val(definedPriceTypes.discount);
    // Postavi vrednosti iz definisanih cena
    jQuery("#custom_price").val(definedPriceTypes.custom);

    // Funkcija za ažuriranje opcije "custom" u selektu tipa cene
    function updateCustomPriceOption() {
      const customValue = jQuery("#custom_price").val();
      const $select = jQuery("#price_type");

      if (customValue && $select.find("option[value='custom']").length === 0) {
        $select.append(`<option value="custom">Custom</option>`);
      }

      if (!customValue && $select.find("option[value='custom']").length > 0) {
        $select.find("option[value='custom']").remove();
        if ($select.val() === "custom") {
          $select.val("regular");
        }
      }
    }

    // Poziv odmah i kada se menja custom cena
    updateCustomPriceOption();
    jQuery("#custom_price").on("input", updateCustomPriceOption);

    // Funkcija za prikaz datuma kada se izabere "Izbor datuma"
    function toggleDateRangeVisibility() {
      const rule = jQuery("#apply_rule").val();
      if (rule === "custom") {
        jQuery("#daterange_row").show();
      } else {
        jQuery("#daterange_row").hide();
      }
    }

    // Pozovi odmah i kada se menja "Primeni na"
    toggleDateRangeVisibility();
    jQuery("#apply_rule").on("change", toggleDateRangeVisibility);

    // Inicijalizuj date range picker
    jQuery("#daterange").daterangepicker({
      locale: { format: "DD/MM/YYYY" }
    });
    // Navigacija kroz mesece
    jQuery(".prev-month").click(() => navigationHandler(-1));
    jQuery(".next-month").click(() => navigationHandler(1));

    // Inicijalni render kalendara
    myCalendar();

    // edit single day price
    // jQuery(document).on("click", ".editable-price", function (e) {
    //   e.preventDefault();

    //   const date = jQuery(this).data("date");
    //   jQuery("#price_modal_date").text(moment(date).format("DD/MM/YYYY"));
    //   jQuery("#price_modal_date_input").val(date);

    //   // popuni vrednost ako postoji
    //   const currentPrice = calendarData[date]?.price ?? "";
    //   jQuery("#price_modal_input").val(currentPrice);

    //   jQuery("#price_modal_wrapper").show();
    // });
    jQuery(document).on("click", ".editable-price", function (e) {
      e.preventDefault();

      const date = jQuery(this).data("date"); // npr. "2025-06-05"
      jQuery("#price_modal_date").text(moment(date).format("DD/MM/YYYY"));
      jQuery("#price_modal_date_input").val(date);
      jQuery("#price_modal_value").val(calendarData[date]?.price || "");
      jQuery("#price_modal_wrapper").show();
    });

    //save single day price
    jQuery(document).on("click", ".editable-price", function (e) {
      e.preventDefault();

      const date = jQuery(this).data("date"); // npr. "2025-06-05"
      jQuery("#price_modal_date").text(moment(date).format("DD/MM/YYYY"));
      jQuery("#price_modal_date_input").val(date);
      jQuery("#price_modal_input").val(calendarData[date]?.price || "");
      jQuery("#price_modal_wrapper").show();
    });

    // Čuvanje pojedinačne cene
    jQuery("#price_modal_save").on("click", function () {
      const date = jQuery("#price_modal_date_input").val();
      const newPrice = parseFloat(jQuery("#price_modal_input").val());

      if (!date || isNaN(newPrice)) {
        alert("Unesi ispravnu cenu");
        return;
      }

      // Ažuriranje lokalnog objekta calendarData
      calendarData[date] = {
        ...calendarData[date],
        price: newPrice,
        priceType: "custom",
        status: calendarData[date]?.status || "available",
        client: calendarData[date]?.client || null,
      };

      const productId = jQuery("#ov_product_id").val();

      // Slanje na server
      jQuery.ajax({
        url: ov_calendar_vars.ajax_url,
        method: "POST",
        data: {
          action: "ov_save_calendar_data",
          product_id: productId,
          calendar_data: JSON.stringify(calendarData),
          price_types: ov_calendar_vars.priceTypes,
        },
        dataType: "json",
        success: function (res) {
          console.log("Cena sačuvana:", res);
          myCalendar(); // ponovni render
          jQuery("#price_modal_wrapper").hide(); // zatvori modal
        },
        error: function (err) {
          console.error("Greška pri čuvanju cene:", err);
        },
      });
    });

    // add client modal hide
    jQuery(document).on("click", "#client_modal_wrapper", function (e) {
      if (e.target.id === "client_modal_wrapper") {
        jQuery(
          "#client_first_name, #client_last_name, #client_email, #client_phone, #client_guests, #client_date_range, #client_modal_date_input"
        ).val("");
        jQuery("#client_modal_wrapper").hide();
      }
    });

    // add client modal + set date
    jQuery(document).on("click", ".add-client-button", function (e) {
      e.preventDefault();
      e.stopPropagation();

      const selectedDate = jQuery(this).data("date");
      const formattedDate = moment(selectedDate, "YYYY-MM-DD").format("DD/MM/YYYY");

      jQuery("#client_modal_date").text(formattedDate);
      jQuery("#client_modal_date_input").val(selectedDate);

      const picker = jQuery("#client_date_range").data("daterangepicker");
      if (!picker) {
        console.error("daterangepicker nije inicijalizovan na #client_date_range");
        return;
      }
      const selectedMoment = moment(selectedDate, "YYYY-MM-DD");
      picker.setStartDate(selectedMoment);
      picker.setEndDate(selectedMoment);

      jQuery("#client_modal_wrapper").show();
    });


    // edit client
    jQuery(document).on("click", ".clickable-client", function () {
      const date = jQuery(this).data("date");
      const firstName = jQuery(this).data("firstname");
      const lastName = jQuery(this).data("lastname");
      const email = jQuery(this).data("email");
      const phone = jQuery(this).data("phone");
      const guests = jQuery(this).data("guests");

      const clientData = calendarData[date]?.client || {};
      const rangeStart = clientData.rangeStart;
      const rangeEnd = clientData.rangeEnd;

      jQuery("#client_action_name").text(`${firstName} ${lastName}`);
      jQuery("#client_action_email").text(`${email}`);
      jQuery("#client_action_phone").text(`${phone}`);
      jQuery("#client_action_number_of_guests").text(`${guests}`);

      if (rangeStart && rangeEnd) {
        jQuery("#client_action_date_range").text(
          `${moment(rangeStart).format("DD/MM/YYYY")} - ${moment(
            rangeEnd
          ).format("DD/MM/YYYY")}`
        );
      } else {
        jQuery("#client_action_date_range").text("N/A");
      }

      jQuery("#client_action_date").text(moment(date).format("DD/MM/YYYY"));
      jQuery("#client_action_date_input").val(date);
      jQuery("#client_action_email_input").val(email);

      jQuery("#client_action_modal_wrapper").show();
    });

    // edit client

    // edit client delete single day
    jQuery("#delete_client_single").on("click", function () {
      const date = jQuery("#client_action_date_input").val();

      if (calendarData[date]) {
        calendarData[date].client = null;
        calendarData[date].status = "available";
      }

      saveCalendarAndRefresh();
    });
    // edit client delete single day

    //edit client delete reservation range
    jQuery("#delete_client_all").on("click", function () {
      const date = jQuery("#client_action_date_input").val();
      const bookingId = calendarData[date]?.client?.bookingId;

      if (!bookingId) {
        console.error("Nije pronađen bookingId za ovu rezervaciju.");
        return;
      }

      for (const day in calendarData) {
        if (calendarData[day]?.client?.bookingId === bookingId) {
          calendarData[day].client = null;
          calendarData[day].status = "available";
        }
      }

      saveCalendarAndRefresh();
    });

    //edit client delete reservation range

    //save edit client delete
    function saveCalendarAndRefresh() {
      const productId = jQuery("#ov_product_id").val();

      jQuery.ajax({
        url: ov_calendar_vars.ajax_url,
        method: "POST",
        data: {
          action: "ov_save_calendar_data",
          product_id: productId,
          calendar_data: JSON.stringify(calendarData),
          price_types: definedPriceTypes,
        },
        success: function () {
          myCalendar();
          jQuery("#client_action_modal_wrapper").hide();
        },
        error: function (err) {
          console.error("Greška pri brisanju:", err);
        },
      });
    }
    //save edit client delete
    //tabs
    jQuery(document).ready(function ($) {
      $(".tab-link").on("click", function (e) {
        e.preventDefault();

        const tabId = $(this).data("tab");

        $(".tab-link").removeClass("current");
        $(this).addClass("current");

        $(".tab-content").hide();
        $("#" + tabId).show();
      });
    });


    jQuery("form#post").on("submit", function () {
      jQuery("#ov_bulk_status_input").val(jQuery("#bulk_status").val());
      jQuery("#ov_status_apply_rule_input").val(
        jQuery("#status_apply_rule").val()
      );
      jQuery("#ov_status_daterange_input").val(
        jQuery("#status_daterange").val()
      );
    });
  });
});