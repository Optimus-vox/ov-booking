var CURRENT_DATE = new Date();
var d = new Date();
var content = "January February March April May June July August September October November December".split(" ");
var daysOfMonth = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
let definedPriceTypes = { regular: 0, weekend: 0, discount: 0, custom: 0 };
let calendarData = {};

const showToast = (msg) =>
  Swal.fire({
    toast: true,
    position: "bottom",
    icon: "error",
    title: msg,
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    background: "#f8d7da",
    color: "#842029",
  });

const parseDMY = (s) => {
  // expects "DD/MM/YYYY"
  const [d, m, y] = s.split("/").map((n) => parseInt(n, 10));
  return new Date(y, m - 1, d);
};

// === Modal open/close helpers ===
function openOvModal(selector) {
  jQuery('body').addClass('ovb-modal-open');
  jQuery(selector).show();
}
function closeOvModal(selector) {
  jQuery(selector).hide();
  jQuery('body').removeClass('ovb-modal-open');
}

function jsDayToMondayFirst(day) {
  return (day + 6) % 7;
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
function parseDefinedPriceTypes(obj) {
  for (const key in obj) {
    obj[key] = parseFloat(obj[key]) || 0;
  }
}
function clearCalendar() {
  jQuery(".month-year").empty();
  jQuery(".admin-table tbody").empty();
}

// Lock flags 
let lock_client_save = false;
let lock_price_save = false;
let lock_delete_single = false;
let lock_delete_all = false;

jQuery(document).ready(function () {
  let PRODUCT_ID = "";
  if (jQuery("#ovb_product_id").length) {
    PRODUCT_ID = jQuery("#ovb_product_id").val();
  }
  if (typeof ovbAdminCalendar !== "undefined" && ovbAdminCalendar.calendarData) {
    calendarData = { ...ovbAdminCalendar.calendarData };
  }

  if (typeof ovbAdminCalendar !== "undefined" && ovbAdminCalendar.priceTypes) {
    definedPriceTypes = { ...ovbAdminCalendar.priceTypes };
  }
  parseDefinedPriceTypes(definedPriceTypes);

  // Helper funkcija za sigurno čuvanje
  function saveCalendarDataSafely(successMessage = "Data saved successfully") {
    const productId = jQuery("#ovb_product_id").val() || PRODUCT_ID;
       if (!productId) {
         showToast("Greška: nepoznat id! Probaj da osvežiš stranicu.");
         return jQuery.Deferred().reject("No product_id").promise();
       }
    // Proveri da svi datumi imaju clients array
    for (const key in calendarData) {
      if (!Array.isArray(calendarData[key].clients)) {
        calendarData[key].clients = [];
      }
    }

    return jQuery.ajax({
      url: ovbAdminCalendar.ajax_url,
      method: "POST",
      data: {
        action: "ovb_save_calendar_data",
        nonce: ovbAdminCalendar.nonce,
        product_id: productId,
        calendar_data: JSON.stringify(calendarData),
        price_types: JSON.stringify(definedPriceTypes),
      },
      dataType: "json",
      success: function (res) {
        if (successMessage) {
          Swal.fire("Success!", successMessage, "success");
        }
      },
      error: function (err) {
        console.error("Error saving calendar data:", err);
        Swal.fire("Error!", "Failed to save data. Please try again.", "error");
      },
    });
  }

  function renderCalendar(startDay, totalDays, currentDate, month, year) {
    const calendar = document.querySelector(".admin-table tbody");
    if (!calendar) return;
    calendar.innerHTML = "";

    let currentDay = startDay;
    let $week = getCalendarRow();

    for (let i = 1; i <= totalDays; i++) {
      let $day = $week.find("td").eq(currentDay);
      const formattedDate = `${year}-${String(month + 1).padStart(
        2,
        "0"
      )}-${String(i).padStart(2, "0")}`;

      const dayData = calendarData[formattedDate] || {};
      const clients = Array.isArray(dayData.clients) ? dayData.clients : [];
      const hasClients = clients.length > 0;

      const dayDate = new Date(year, month, i);
      const today = new Date();
      today.setHours(0, 0, 0, 0);

      const isPast = dayDate < today;

      let price = dayData.price;
      let status = dayData.status ?? "available";

      // Ako nemamo cenu i nemamo klijente, status je unavailable
      if (!price && price !== 0 && !hasClients) {
        status = "unavailable";
      }

      // Ako je prošao dan, status mora biti unavailable
      if (isPast) {
        status = "unavailable";
        $day.addClass("past-day");
      }

      const isLastDay = clients.some(
        (client) => client.rangeEnd === formattedDate
      );
      if (hasClients) {
        if (isLastDay) {
          status = "available";
        } else {
          status = "booked";
        }
      }

      calendarData[formattedDate] = {
        ...calendarData[formattedDate],
        status: status,
        isPast: isPast,
      };

      let dayHTML = `
      <div class="day-wrapper">
        <div class="day-header">
          <div class="day-number ${i === currentDate ? "today-number" : ""}">${i}</div>
          <div class="price-row">
            ${isPast ? `<span class="past-badge">Past</span>` : ""}
            <div class="day-price editable-price" data-date="${formattedDate}">
              ${
                !isPast
                  ? `<svg width="10" height="9" viewBox="0 0 10 9" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M6.98958 1.50002L8.55556 3.06599C8.62153 3.13196 8.62153 3.2396 8.55556 3.30558L4.76389 7.09724L3.15278 7.27606C2.9375 7.30037 2.75521 7.11808 2.77951 6.9028L2.95833 5.29169L6.75 1.50002C6.81597 1.43405 6.92361 1.43405 6.98958 1.50002ZM9.80208 1.10245L8.95486 0.255229C8.69097 -0.00866021 8.26215 -0.00866021 7.99653 0.255229L7.38195 0.869812C7.31597 0.935784 7.31597 1.04342 7.38195 1.1094L8.94792 2.67537C9.01389 2.74134 9.12153 2.74134 9.1875 2.67537L9.80208 2.06078C10.066 1.79516 10.066 1.36634 9.80208 1.10245ZM6.66667 6.06599V7.83335H1.11111V2.2778H5.10069C5.15625 2.2778 5.20833 2.25523 5.24826 2.21703L5.94271 1.52259C6.07465 1.39065 5.9809 1.16669 5.79514 1.16669H0.833333C0.373264 1.16669 0 1.53995 0 2.00002V8.11113C0 8.5712 0.373264 8.94446 0.833333 8.94446H6.94444C7.40451 8.94446 7.77778 8.5712 7.77778 8.11113V5.37155C7.77778 5.18578 7.55382 5.09377 7.42188 5.22398L6.72743 5.91842C6.68924 5.95835 6.66667 6.01044 6.66667 6.06599Z" fill="#111827"/>
                </svg>`
                  : ""
              }
              ${typeof price === "number" ? price + "€" : "Add price"}
              <span class="tooltip-text">
                Price: ${typeof price === "number" ? price + "€" : "Price not set"}<br>
                Status: ${status}
              </span>
            </div>
          </div>
        </div>
    `;

      // Always show clients without filtering end dates
      if (hasClients) {
        clients.forEach((client) => {
          let iconHtml = "";
          let hasIcon = false;
          if (client.isCheckin && client.isCheckout) {
            hasIcon = true;
            iconHtml = `<div class="ovb-booking-dates check-in-out" style="display:flex; align-items:center; width:32px">
            <svg xmlns="http://www.w3.org/2000/svg" title="Check-in-out" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-log-in"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><polyline points="10 17 15 12 10 7"></polyline><line x1="15" y1="12" x2="3" y2="12"></line></svg>
            <svg xmlns="http://www.w3.org/2000/svg" title="Check-in-out" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-log-out"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
             <div class="tooltip-text">Check-in & Check-out</div>
            </div>`;
          } else if (client.isCheckin) {
            hasIcon = true;
            iconHtml = `<div class="ovb-booking-dates check-in" style="display:flex; align-items:center; gap:5px; width:32px ">
            <svg xmlns="http://www.w3.org/2000/svg" title="Check-in" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-log-in"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><polyline points="10 17 15 12 10 7"></polyline><line x1="15" y1="12" x2="3" y2="12"></line></svg>
            <div class="tooltip-text">Check-in</div>
            </div>`;
          } else if (client.isCheckout) {
            hasIcon = true;
            iconHtml = `<div class="ovb-booking-dates check-out" style="display:flex; align-items:center; gap:5px; width:32px">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-log-out"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                <div class="tooltip-text">Check-out</div>
                </div>`;
          }
          dayHTML += `
            <div class="day-client clickable-client"
                data-date="${formattedDate}"
                data-email="${client.email}"
                data-guests="${client.guests}"
                data-phone="${client.phone}"
                data-firstname="${client.firstName}"
                data-lastname="${client.lastName}"
                data-bookingid="${client.bookingId}">
                <span class="ovb-client-info${
                  hasIcon ? " has-icon" : ""
                }">${iconHtml} ${client.firstName} ${client.lastName} ${
            hasIcon ? '<i class="icon-spacer"></i>' : ""
          }</span>
            </div>
        `;
        });
      }

      // Start actions container
      dayHTML += `<div class="day-actions">`;

      // Show "+" button only for non-booked, non-past days
      if (!hasClients && !isPast) {
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
        <select class="ov-status-select" data-date="${formattedDate}">
            <option value="available" ${
              status === "available" ? "selected" : ""
            }>Available</option>
            <option value="unavailable" ${
              status === "unavailable" ? "selected" : ""
            }>Unavailable</option>
            <option value="booked" ${
              status === "booked" ? "selected" : ""
            }>Booked</option>
        </select>
    `;
      }

      // Close actions container and day wrapper
      dayHTML += `</div></div>`;

      $day.html(dayHTML);

      const selectEl = $day.find(".ov-status-select")[0];
      if (selectEl) {
        function updateSelectBackground() {
          const val = selectEl.value;
          selectEl.classList.remove(
            "status-available",
            "status-unavailable",
            "status-booked"
          );
          if (!isPast) {
            if (val === "available") selectEl.classList.add("status-available");
            else if (val === "unavailable")
              selectEl.classList.add("status-unavailable");
            else if (val === "booked") selectEl.classList.add("status-booked");
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
        Cena: ${typeof price === "number" ? price + "€" : "-"}
        Tip: ${status}`
      );

      $day.addClass("current-month");
      if (i === currentDate) $day.addClass("today");

      $day.find(".ov-status-select").on("change", function () {
        const select = jQuery(this);
        const newStatus = select.val();
        const dateKey = select.data("date");
        const previousStatus = calendarData[dateKey]?.status || "available";

        Swal.fire({
          title: "Change status?",
          text: `Do you want to set status to "${newStatus}"?`,
          icon: "question",
          showCancelButton: true,
          confirmButtonText: "Yes",
          cancelButtonText: "No",
        }).then((result) => {
          if (!result.isConfirmed) {
            // Vrati na prethodni status bez dodatnog pitanja
            select.val(previousStatus);

            // Resetuj klase i dodaj pravu
            select.removeClass(
              "status-available status-unavailable status-booked"
            );
            if (previousStatus === "available") {
              select.addClass("status-available");
            } else if (previousStatus === "unavailable") {
              select.addClass("status-unavailable");
            } else if (previousStatus === "booked") {
              select.addClass("status-booked");
            }
            return;
          }

          // Ako je potvrđeno → ažuriraj status
          if (!calendarData[dateKey]) {
            calendarData[dateKey] = { clients: [] };
          }
          calendarData[dateKey].status = newStatus;

          saveCalendarDataSafely("Status updated successfully.").then(() => {
            myCalendar(); // Rerenderuj kalendar
          });
        });
      });

      currentDay = ++currentDay % 7;
      if (currentDay === 0 && i + 1 <= totalDays) {
        $week = getCalendarRow();
      }
    }
  }

  // Show/hide custom range polja
  // jQuery("#apply_rule").on("change", function () {
  //   if (jQuery(this).val() === "custom") {
  //     jQuery("#daterange_container").show();
  //   } else {
  //     jQuery("#daterange_container").hide();
  //   }
  // });

  // Init daterangepicker
initOvDateRangePicker({
  input: "#daterange",
  container: "#daterange_container",
  calendarData,
  onChange: (start, end) => {
    jQuery("#daterange").val(`${start.format("DD/MM/YYYY")} – ${end.format("DD/MM/YYYY")}`);
  },
});

  initOvDateRangePicker({
    input: "#status_daterange",
    container: "#status_daterange_container",
    calendarData,
    onChange: (start, end) => {
      jQuery("#status_daterange").val(`${start.format("DD/MM/YYYY")} – ${end.format("DD/MM/YYYY")}`);
    },
  });

  // SAVE PRICE TYPES
  jQuery("#save_price_types")
    .off("click")
    .on("click", function () {
      Swal.fire({
        title: "Save price types?",
        text: "Are you sure you want to save the updated prices (Regular, Weekend, Discount, and Custom)?",
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Yes, save",
        cancelButtonText: "Cancel",
      }).then((result) => {
        if (!result.isConfirmed) return;

        // Ažuriraj definedPriceTypes
        definedPriceTypes.regular = Number(jQuery("#regular_price").val()) || 0;
        definedPriceTypes.weekend = Number(jQuery("#weekend_price").val()) || 0;
        definedPriceTypes.discount = Number(jQuery("#discount_price").val()) || 0;
        definedPriceTypes.custom = Number(jQuery("#custom_price").val()) || 0;

        // Primeni bulk status ako je izabran
        const selectedStatus = jQuery("#bulk_status").val();
        const statusRule = jQuery("#status_apply_rule").val();

        if (selectedStatus && statusRule) {
          const month = d.getUTCMonth();
          const year = d.getUTCFullYear();
          const daysInMonth = new Date(year, month + 1, 0).getDate();

          for (let i = 1; i <= daysInMonth; i++) {
            const dateStr = `${year}-${String(month + 1).padStart(2, "0")}-${String(i).padStart(2, "0")}`;
            const dateObj = new Date(dateStr);
            const dayOfWeek = dateObj.getUTCDay();

            let shouldApply = false;
            if (statusRule === "weekdays" && dayOfWeek >= 1 && dayOfWeek <= 5) shouldApply = true;
            if (statusRule === "weekends" && (dayOfWeek === 0 || dayOfWeek === 6)) shouldApply = true;
            if (statusRule === "full_month") shouldApply = true;

            if (shouldApply) {
              if (!calendarData[dateStr]) {
                calendarData[dateStr] = {
                  status: "available",
                  isPast: false,
                  clients: [],
                };
              }

              calendarData[dateStr].status = selectedStatus;

              if (typeof calendarData[dateStr].price !== "number") {
                calendarData[dateStr].price = undefined;
              }
              if (!calendarData[dateStr].priceType) {
                calendarData[dateStr].priceType = undefined;
              }
              if (!Array.isArray(calendarData[dateStr].clients)) {
                calendarData[dateStr].clients = [];
              }
            }
          }

          if (statusRule === "custom") {
            const parts = jQuery("#status_daterange").val().split(" – ");
            if (parts.length !== 2) {
              return Swal.fire("Error", "Date range picker is not initialized.", "error");
            }
            // parse u moment objekte
            const start = moment(parts[0], "DD/MM/YYYY");
            const end = moment(parts[1], "DD/MM/YYYY");
            // iteriraj po danima
            let current = start.clone();
            while (current.isSameOrBefore(end, "day")) {
              const dateStr = current.format("YYYY-MM-DD");
              // inicijalizuj ako ne postoji
              if (!calendarData[dateStr]) {
                calendarData[dateStr] = { status: "available", isPast: false, clients: [] };
              }
              // postavi novi status
              calendarData[dateStr].status = selectedStatus;
              // očisti price/priceType/clients ako nedostaju
              if (typeof calendarData[dateStr].price !== "number") {
                calendarData[dateStr].price = undefined;
              }
              if (!calendarData[dateStr].priceType) {
                calendarData[dateStr].priceType = undefined;
              }
              if (!Array.isArray(calendarData[dateStr].clients)) {
                calendarData[dateStr].clients = [];
              }
              // sledeći dan
              current.add(1, "days");
            }
          }
        }

        // Sačuvaj sve promene
        saveCalendarDataSafely("Price types and calendar data saved successfully.").then(() => {
          myCalendar(); // Rerenderuj kalendar
        });
      });
    });

  // APPLY PRICE
  jQuery("#apply_price")
    .off("click")
    .on("click", function (e) {
      e.preventDefault();

      const priceType = jQuery("#price_type").val();
      const rule = jQuery("#apply_rule").val();
      const selectedPrice = definedPriceTypes[priceType];
      const selectedStatus = jQuery("#bulk_status").val();

      if (typeof selectedPrice !== "number" || isNaN(selectedPrice) || selectedPrice <= 0) {
        return Swal.fire("Error", "Please enter a valid price for the selected price type.", "error");
      }

      let ruleLabel = "";
      if (rule === "weekdays") ruleLabel = "weekdays (Monday–Friday)";
      else if (rule === "weekends") ruleLabel = "weekends (Saturday–Sunday)";
      else if (rule === "full_month") ruleLabel = "all days in the selected month";
      else if (rule === "custom") {
       const parts = jQuery("#daterange").val().split(" – ");
       if (parts.length !== 2) return Swal.fire("Error", "Date range picker is not initialized.", "error");
       const start = moment(parts[0], "DD/MM/YYYY");
       const end = moment(parts[1], "DD/MM/YYYY");
       ruleLabel = `dates in range: ${start.format("DD/MM/YYYY")} – ${end.format("DD/MM/YYYY")}`;
      }

      Swal.fire({
        title: "Confirm Price Application",
        html: `
      This change will apply to: <b>${ruleLabel}</b><br>
      Price type: <b>${priceType}</b><br>
      Price value: <b>${selectedPrice}€</b><br>
      ${selectedStatus ? `Status to apply: <b>${selectedStatus}</b><br>` : ""}
      <br>Do you want to continue?`,
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Yes, apply",
        cancelButtonText: "Cancel",
      }).then((result) => {
        if (!result.isConfirmed) return;

        const month = d.getUTCMonth();
        const year = d.getUTCFullYear();
        const daysInMonth = new Date(year, month + 1, 0).getDate();

        // Primeni promene na calendarData
        if (rule === "weekdays" || rule === "weekends" || rule === "full_month") {
          for (let i = 1; i <= daysInMonth; i++) {
            const dateStr = `${year}-${String(month + 1).padStart(2, "0")}-${String(i).padStart(2, "0")}`;
            const dayOfWeek = new Date(year, month, i).getDay();

            let shouldApply = false;
            if (rule === "weekdays" && dayOfWeek >= 1 && dayOfWeek <= 5) shouldApply = true;
            if (rule === "weekends" && (dayOfWeek === 0 || dayOfWeek === 6)) shouldApply = true;
            if (rule === "full_month") shouldApply = true;

            if (shouldApply) {
              // Inicijalizuj objekat ako ne postoji
              if (!calendarData[dateStr]) {
                calendarData[dateStr] = {
                  status: "available",
                  isPast: false,
                  clients: [],
                };
              }

              // Postavi cenu i tip
              calendarData[dateStr].price = selectedPrice;
              calendarData[dateStr].priceType = priceType;

              // Postavi status ako je izabran
              if (selectedStatus) {
                calendarData[dateStr].status = selectedStatus;
              } else if (selectedPrice > 0) {
                // Ako dan dobija cenu, učini ga dostupnim
                calendarData[dateStr].status = "available";
              }

              // Proveri da clients postoji
              if (!Array.isArray(calendarData[dateStr].clients)) {
                calendarData[dateStr].clients = [];
              }
            }
          }
        }
        // Sačuvaj promene i rerenderuj
        saveCalendarDataSafely("Prices were successfully applied.").then(() => {
          myCalendar(); // Rerenderuj kalendar
        });
      });
    });

  // APPLY STATUS
  jQuery("#apply_status")
    .off("click")
    .on("click", function (e) {
      e.preventDefault();

      const selectedStatus = jQuery("#bulk_status").val();
      const rule = jQuery("#status_apply_rule").val();

      if (!selectedStatus) {
        return Swal.fire("Error", "Please select a status to apply.", "error");
      }

      let ruleLabel = "";
      if (rule === "weekdays") ruleLabel = "weekdays (Mon–Fri)";
      else if (rule === "weekends") ruleLabel = "weekends (Sat–Sun)";
      else if (rule === "full_month") ruleLabel = "all days in the month";
      else if (rule === "custom") {
       const parts = jQuery("#status_daterange").val().split(" – ");
       if (parts.length !== 2) return Swal.fire("Error", "Date range picker is not initialized.", "error");
       const start = moment(parts[0], "DD/MM/YYYY");
       const end = moment(parts[1], "DD/MM/YYYY");
       ruleLabel = `custom range: ${start.format("DD/MM/YYYY")} – ${end.format("DD/MM/YYYY")}`;
      }

      Swal.fire({
        title: "Confirm status change",
        html: `This action will apply to: <b>${ruleLabel}</b><br>
           New status to apply: <b>${selectedStatus}</b><br><br>
           Do you want to proceed?`,
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Yes, apply",
        cancelButtonText: "Cancel",
      }).then((result) => {
        if (!result.isConfirmed) return;

        const month = d.getUTCMonth();
        const year = d.getUTCFullYear();
        const daysInMonth = new Date(year, month + 1, 0).getDate();

        // Primeni status promene
        if (rule === "weekdays" || rule === "weekends" || rule === "full_month") {
          for (let i = 1; i <= daysInMonth; i++) {
            const dateStr = `${year}-${String(month + 1).padStart(2, "0")}-${String(i).padStart(2, "0")}`;
            const dateObj = new Date(dateStr);
            const dayOfWeek = dateObj.getUTCDay();

            let shouldApply = false;
            if (rule === "weekdays" && dayOfWeek >= 1 && dayOfWeek <= 5) shouldApply = true;
            if (rule === "weekends" && (dayOfWeek === 0 || dayOfWeek === 6)) shouldApply = true;
            if (rule === "full_month") shouldApply = true;

            if (shouldApply) {
              if (!calendarData[dateStr]) {
                calendarData[dateStr] = {
                  status: "available",
                  isPast: false,
                  clients: [],
                };
              }

              calendarData[dateStr].status = selectedStatus;

              // Zadrži postojeću cenu i tip ako postoje
              if (typeof calendarData[dateStr].price !== "number") {
                calendarData[dateStr].price = undefined;
              }
              if (!calendarData[dateStr].priceType) {
                calendarData[dateStr].priceType = undefined;
              }
              if (!Array.isArray(calendarData[dateStr].clients)) {
                calendarData[dateStr].clients = [];
              }
            }
          }
        }

        if (rule === "custom") {
          const start = picker.startDate.startOf("day");
          const end = picker.endDate.startOf("day");
          let current = moment(start);

          while (current.isSameOrBefore(end)) {
            const dateStr = current.format("YYYY-MM-DD");

            if (!calendarData[dateStr]) {
              calendarData[dateStr] = {
                status: "available",
                isPast: false,
                clients: [],
              };
            }

            calendarData[dateStr].status = selectedStatus;

            if (typeof calendarData[dateStr].price !== "number") {
              calendarData[dateStr].price = undefined;
            }
            if (!calendarData[dateStr].priceType) {
              calendarData[dateStr].priceType = undefined;
            }
            if (!Array.isArray(calendarData[dateStr].clients)) {
              calendarData[dateStr].clients = [];
            }

            current.add(1, "days");
          }
        }

        // Sačuvaj promene i rerenderuj
        saveCalendarDataSafely("Status has been successfully applied.").then(() => {
          myCalendar(); // Rerenderuj kalendar
        });
      });
    });

  // SAVE CHECK-IN/CHECK-OUT
  jQuery("#save_checkin_checkout").on("click", function (e) {
    e.preventDefault();

    const checkinTime = jQuery("#checkin_time").val();
    const checkoutTime = jQuery("#checkout_time").val();
    const productId = jQuery("#ovb_product_id").val();

    if (!checkinTime || !checkoutTime) {
      Swal.fire(
        "Missing data",
        "Please enter both check-in and check-out times.",
        "warning"
      );
      return;
    }

    Swal.fire({
      title: "Confirm time update",
      html: `You're about to save:<br><b>Check-in:</b> ${checkinTime}<br><b>Check-out:</b> ${checkoutTime}<br><br>Proceed?`,
      icon: "question",
      showCancelButton: true,
      confirmButtonText: "Yes, save",
      cancelButtonText: "Cancel",
    }).then((result) => {
      if (!result.isConfirmed) return;

      jQuery.ajax({
        url: ovbAdminCalendar.ajax_url,
        method: "POST",
        data: {
          action: "ovb_save_checkin_checkout",
          product_id: productId,
          checkin_time: checkinTime,
          checkout_time: checkoutTime,
        },
        success: function (res) {
          Swal.fire(
            "Saved!",
            "Check-in and check-out times have been updated.",
            "success"
          );
        },
        error: function (err) {
          Swal.fire("Error", "There was an error saving the times.", "error");
          console.error("Error saving times:", err);
        },
      });
    });
  });

  // CLIENT MODAL LOGIC ─────────────────────────────────────────────────────────────
  const $modal = jQuery("#client_modal_wrapper");
  const $inputs = {
    firstName: jQuery("#client_first_name"),
    lastName: jQuery("#client_last_name"),
    email: jQuery("#client_email"),
    phone: jQuery("#client_phone"),
    guests: jQuery("#client_guests"),
    range: jQuery("#custom-daterange-input-admin"),
    productId: jQuery("#ovb_product_id"),
  };
  
jQuery("#client_modal_save")
  .off("click")
  .on("click", function () {
    if (lock_client_save) return;
    lock_client_save = true;

    // 1. VALIDACIJA INPUTA
    const data = {};
    // for (let key of ["firstName", "lastName", "email", "phone", "guests"]) {
    //   data[key] = $inputs[key].val().trim();
    // }
    for (let key of ["firstName", "lastName", "email", "phone", "guests"]) {
      const value = $inputs[key].val();
      data[key] = value ? value.trim() : "";
    }

    $modal.find(".input-error").removeClass("input-error");

    const rules = {
      firstName: (v) => v.length >= 2,
      lastName: (v) => v.length >= 2,
      email: (v) => /^\S+@\S+\.\S+$/.test(v),
      phone: (v) => /^\+?\d{6,}$/.test(v),
      guests: (v) => Number(v) > 0,
    };

    // sakupi sva neprolazna polja
    const invalid = [];
    for (let [key, fn] of Object.entries(rules)) {
      if (!fn(data[key])) invalid.push(key);
    }

    if (invalid.length > 0) {
      invalid.forEach((key) => $inputs[key].addClass("input-error"));
      showToast("Please fill in all fields correctly.");
      lock_client_save = false;
      return;
    }

    // VALIDACIJA DATUMA IZ TVOG PICKERA (koristi hidden inpute koje tvoj picker puni)
    const isoStart = jQuery("#start_date").val();
    const isoEnd = jQuery("#end_date").val();

    if (!isoStart || !isoEnd) {
      showToast("Please select a valid date range.");
      lock_client_save = false;
      return;
    }
    if (isoStart > isoEnd) {
      showToast("Start date cannot be after end date.");
      lock_client_save = false;
      return;
    }

    const bookingId = Date.now() + "_" + Math.floor(Math.random() * 10000);

    // 2. LOKALNI UPDATE
    let d = new Date(isoStart);
    const endDateObj = new Date(isoEnd);
    while (d <= endDateObj) {
      const key = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
      if (!calendarData[key]) calendarData[key] = { clients: [] };
      if (!Array.isArray(calendarData[key].clients)) calendarData[key].clients = [];
      calendarData[key].clients.push({
        bookingId,
        ...data,
        rangeStart: isoStart,
        rangeEnd: isoEnd,
        isCheckin: key === isoStart,
        isCheckout: key === isoEnd,
      });
      calendarData[key].status = "booked";
      d.setDate(d.getDate() + 1);
    }

    // 3. UI UPDATE – MODAL ZATVORI, RESETUJ POLJA, RERENDERUJ KALENDAR
    $modal.hide();
    Object.values($inputs).forEach(($el) => $el.val(""));
    // Reset date range picker input & hidden fields
    jQuery("#custom-daterange-input-admin").val("");
    jQuery("#start_date, #end_date").val("");
    jQuery("body").css("overflow", "auto");
    myCalendar();

    // 4. AJAX ORDER + CALENDAR – NE BLOKIRA UI!
    jQuery
      .post(ovbAdminCalendar.ajax_url, {
        action: "ovb_admin_create_manual_order",
        nonce: ovbAdminCalendar.nonce,
        product_id: $inputs.productId.val(),
        client_data: JSON.stringify({
          bookingId,
          ...data,
          rangeStart: isoStart,
          rangeEnd: isoEnd,
        }),
      })
      .done((res) => {
        if (!res.success) showToast(res.data || "Server error creating booking.");
      })
      .fail(() => showToast("AJAX request failed."))
      .always(() => {
        lock_client_save = false;
      });

    // 4b. Save calendar
    saveCalendarDataSafely("Booking created!")
      .then(() => {})
      .catch(() => {
        showToast("Failed to save calendar. Try again.");
      });
  });


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

  // jQuery(document).ready(function () {
  jQuery("#regular_price").val(definedPriceTypes.regular);
  jQuery("#weekend_price").val(definedPriceTypes.weekend);
  jQuery("#discount_price").val(definedPriceTypes.discount);
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

  function toggleDateRange() {
    if (jQuery("#apply_rule").val() === "custom") {
      jQuery("#daterange_container").show();
    } else {
      jQuery("#daterange_container").hide();
    }
  }
  toggleDateRange();
  jQuery("#apply_rule").on("change", toggleDateRange);

  // Navigacija kroz mesece
  jQuery(".prev-month").click(() => navigationHandler(-1));
  jQuery(".next-month").click(() => navigationHandler(1));

  // Inicijalni render kalendara
  myCalendar();

  // Edit single day price
  jQuery(document).on("click", ".editable-price", function (e) {
    e.preventDefault();

    const date = jQuery(this).data("date");
    const currentPrice = calendarData[date]?.price;
    jQuery("#price_modal_date").text(moment(date).format("DD/MM/YYYY"));
    jQuery("#price_modal_date_input").val(date);
    jQuery("#price_modal_input").val(typeof currentPrice === "number" ? currentPrice : "");
    // jQuery("#price_modal_wrapper").show();
    // jQuery("body").css("overflow", "hidden");
    openOvModal("#price_modal_wrapper");
  });

  // Čuvanje pojedinačne cene
  jQuery("#price_modal_save")
    .off("click")
    .on("click", function () {
      if (lock_price_save) return;
      lock_price_save = true;

      const date = jQuery("#price_modal_date_input").val();
      const newPrice = parseFloat(jQuery("#price_modal_input").val());

      if (!date || isNaN(newPrice) || newPrice < 0) {
        Swal.fire("Error", "Please enter a valid price", "error");
        lock_price_save = false;
        return;
      }

      const existingStatus = calendarData[date]?.status;
      const existingPrice = calendarData[date]?.price;

      let newStatus = existingStatus || "available";

      // Ako dan ranije nije imao cenu, a sada dodajemo validnu → status postaje "available"
      const hadNoPrice = existingPrice === undefined || existingPrice === null;
      if (hadNoPrice && !isNaN(newPrice) && existingStatus !== "booked") {
        newStatus = "available";
      }

      if (!calendarData[date]) {
        calendarData[date] = {
          status: "available",
          isPast: false,
          clients: [],
        };
      }

      calendarData[date].price = newPrice;
      calendarData[date].priceType = "custom";

      if (newPrice > 0 && calendarData[date].status === "unavailable") {
        calendarData[date].status = "available";
      }

      saveCalendarDataSafely("Price updated successfully.")
        .then(() => {
          myCalendar();
          // jQuery("#price_modal_wrapper").hide();
          // jQuery("body").css("overflow", "auto");
          closeOvModal("#price_modal_wrapper");
        })
        .always(() => {
          lock_price_save = false;
        });
    });


  // Add client modal hide
  jQuery(document).on("click", "#client_modal_wrapper", function (e) {
    if (e.target.id === "client_modal_wrapper") {
      jQuery("#client_first_name, #client_last_name, #client_email, #client_phone, #client_guests, #custom-daterange-input-admin").val("");
      // jQuery("#client_modal_wrapper").hide();
      // jQuery("body").css("overflow", "auto");
      closeOvModal("#client_modal_wrapper");
    }
  });

  // Add client modal + set date
  jQuery(document).on("click", ".add-client-button", function (e) {
    e.preventDefault();
    e.stopPropagation();

    const selectedDate = jQuery(this).data("date");
    const formattedDate = moment(selectedDate, "YYYY-MM-DD").format("DD/MM/YYYY");
    jQuery("#client_modal_date_input").val(selectedDate);
    jQuery("#client_modal_date").text(formattedDate);
    // automatski setujemo checkout na +1 dan
    const nextDay = moment(selectedDate).add(1, "days").format("DD/MM/YYYY");
    jQuery("#custom-daterange-input").val(`${formattedDate} – ${nextDay}`);
    openOvModal("#client_modal_wrapper");

    setTimeout(() => {
      // clean up previous picker
      document.querySelector("#date-range-picker .ov-picker-container")?.remove();
      window._ovb_client_picker = null;

      // 🔥 Init custom client-picker
      window._ovb_client_picker = initOvDateRangePicker({
        input: "#custom-daterange-input-admin",
        container: "#date-range-picker",
        calendarData,
        defaultStart: selectedDate,
        defaultEnd: moment(selectedDate).add(1, "days").format("YYYY-MM-DD"),
        onChange: (start, end) => {
          jQuery("#start_date").val(start?.format("YYYY-MM-DD") || "");
          jQuery("#end_date").val(end?.format("YYYY-MM-DD") || "");
        },
      });
    }, 100);
  });

  // Edit client
  jQuery(document).on("click", ".clickable-client", function () {
    const date = jQuery(this).data("date");
    const firstName = jQuery(this).data("firstname");
    const lastName = jQuery(this).data("lastname");
    const email = jQuery(this).data("email");
    const phone = jQuery(this).data("phone");
    const guests = jQuery(this).data("guests");
    const bookingId = jQuery(this).data("bookingid");

    const currentClient = (calendarData[date]?.clients || []).find((cl) => cl.bookingId == bookingId);
    const rangeStart = currentClient?.rangeStart;
    const rangeEnd = currentClient?.rangeEnd;

    jQuery("#client_action_name").text(`${firstName} ${lastName}`);
    jQuery("#client_action_email").text(`${email}`);
    jQuery("#client_action_phone").text(`${phone}`);
    jQuery("#client_action_number_of_guests").text(`${guests}`);

    jQuery("#client_action_bookingid_input").val(bookingId);

    if (rangeStart && rangeEnd) {
      const startFmt = moment(rangeStart, "YYYY-MM-DD").format("DD/MM/YYYY");
      const endFmt = moment(rangeEnd, "YYYY-MM-DD").format("DD/MM/YYYY");
      jQuery("#client_action_date_range").text(`${startFmt} – ${endFmt}`);
    } else {
      jQuery("#client_action_date_range").text("N/A");
    }


    jQuery("#client_action_date").text(moment(date).format("DD/MM/YYYY"));
    jQuery("#client_action_date_input").val(date);
    jQuery("#client_action_email_input").val(email);
    openOvModal("#client_action_modal_wrapper");
  });

  // Delete client single day
    jQuery("#delete_client_single")
      .off("click")
      .on("click", function () {
        if (lock_delete_single) return;
        lock_delete_single = true;

        const date = jQuery("#client_action_date_input").val();
        const bookingIdToDelete = jQuery("#client_action_bookingid_input").val();

        if (calendarData[date] && Array.isArray(calendarData[date].clients)) {
          calendarData[date].clients = calendarData[date].clients.filter((client) => client.bookingId !== bookingIdToDelete);

          if (calendarData[date].clients.length === 0) {
            calendarData[date].clients = [];
            if (calendarData[date].price > 0) {
              calendarData[date].status = "available";
            } else {
              calendarData[date].status = "unavailable";
            }
          }
        }

        saveCalendarAndRefresh().always(() => {
          lock_delete_single = false;
        });
    });


  // Delete client all days
jQuery("#delete_client_all")
  .off("click")
  .on("click", function () {
    if (lock_delete_all) return;
    lock_delete_all = true;
    const bookingId = jQuery("#client_action_bookingid_input").val();

    if (!bookingId) {
      console.error("Booking ID not found for this reservation.");
      lock_delete_all = false;
      return;
    }

    jQuery.ajax({
      url: ovbAdminCalendar.ajax_url,
      method: "POST",
      data: {
        action: "ovb_delete_booking_and_order",
        booking_id: bookingId,
      },
      success: function (res) {
        for (const day in calendarData) {
          if (calendarData[day]?.clients && Array.isArray(calendarData[day].clients)) {
            calendarData[day].clients = calendarData[day].clients.filter((client) => client.bookingId !== bookingId);
            if (calendarData[day].clients.length === 0) {
              calendarData[day].clients = [];
              if (calendarData[day].price > 0) {
                calendarData[day].status = "available";
              } else {
                calendarData[day].status = "unavailable";
              }
            }
          }
        }
        saveCalendarAndRefresh().always(() => {
          lock_delete_all = false;
        });
      },
      error: function (err) {
        console.error("Error deleting order: ", err);
        lock_delete_all = false;
      },
    });
  });


  // Helper function to save and refresh
function saveCalendarAndRefresh() {
  return saveCalendarDataSafely("Booking deleted successfully.").then(() => {
    myCalendar();
    // jQuery("#client_action_modal_wrapper").hide();
    // jQuery("body").css("overflow", "auto");
    closeOvModal("#client_action_modal_wrapper");
  });
}

  // ESC key handler
  jQuery(document).on("keydown", function (e) {
    if (e.key === "Escape" || e.key === "Esc") {
      // Close client modal
      if (jQuery("#client_modal_wrapper").is(":visible")) {
        jQuery("#client_first_name, #client_last_name, #client_email, #client_phone, #client_guests, #custom-daterange-input-admin").val(
          ""
        );
        // jQuery("#client_modal_wrapper").hide();
        // jQuery("body").css("overflow", "auto");
        closeOvModal("#client_modal_wrapper");
      }
      // Close price modal
      if (jQuery("#price_modal_wrapper").is(":visible")) {
        // jQuery("#price_modal_wrapper").hide();
        // jQuery("body").css("overflow", "auto");
        closeOvModal("#price_modal_wrapper");
      }
      // Close client action modal
      if (jQuery("#client_action_modal_wrapper").is(":visible")) {
        // jQuery("#client_action_modal_wrapper").hide();
        // jQuery("body").css("overflow", "auto");
        closeOvModal("#client_action_modal_wrapper");
      }
    }
  });

  // Tabs functionality
  jQuery(".tab-link").on("click", function (e) {
    e.preventDefault();

    const tabId = jQuery(this).data("tab");

    jQuery(".tab-link").removeClass("current");
    jQuery(this).addClass("current");

    jQuery(".tab-content").hide();
    jQuery("#" + tabId).show();
  });

  // Form submission handler
  jQuery("form#post").on("submit", function () {
    jQuery("#ovb_bulk_status_input").val(jQuery("#bulk_status").val());
    jQuery("#ovb_status_apply_rule_input").val(jQuery("#status_apply_rule").val());
    jQuery("#ovb_status_daterange_input").val(jQuery("#status_daterange").val());
  });
});
