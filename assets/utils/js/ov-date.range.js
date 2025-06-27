function initOvDateRangePicker(config) {
  const {
    input,
    container,
    readonly = false,
    alwaysOpen = false,
    defaultStart = null,
    defaultEnd = null,
    locale = "sr-RS",
    calendarData = {},
  } = config;

  const containerEl = document.querySelector(container);
  const inputEl = input ? document.querySelector(input) : null;

  if (!containerEl) return;

  if (readonly && inputEl) inputEl.style.display = "none";
  if (!readonly && inputEl) inputEl.setAttribute("readonly", "true");

  containerEl.classList.add("ov-daterange-picker");

  let picker = containerEl.querySelector(".ov-picker-container");
  if (!picker) {
    picker = document.createElement("div");
    picker.className = "ov-picker-container";
    if (!alwaysOpen) picker.classList.add("hidden");
    if (alwaysOpen) picker.classList.add("always-open");
    containerEl.appendChild(picker);
  }

  const ensureHiddenInput = (id, name) => {
    if (!containerEl.querySelector(`#${id}`)) {
      const hidden = document.createElement("input");
      hidden.type = "hidden";
      hidden.id = id;
      hidden.name = name;
      containerEl.appendChild(hidden);
    }
  };
  ensureHiddenInput("start_date", "start_date");
  ensureHiddenInput("end_date", "end_date");
  ensureHiddenInput("all_dates", "all_dates");

  if (readonly) {
    containerEl.querySelector("#start_date").value = "";
    containerEl.querySelector("#end_date").value = "";
    containerEl.querySelector("#all_dates").value = "";
  }

  const today = new Date();
  let startDate = defaultStart ? new Date(defaultStart) : null;
  let endDate = defaultEnd ? new Date(defaultEnd) : null;
  let isPickerOpen = alwaysOpen || readonly;

  let currentMonth = today.getMonth();
  let currentYear = today.getFullYear();

  function formatDate(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, "0");
    const d = String(date.getDate()).padStart(2, "0");
    return `${y}-${m}-${d}`;
  }

  function formatForInput(date) {
    return date.toLocaleDateString(locale);
  }

  function isBlocked(date) {
    const key = formatDate(date);
    const info = calendarData[key];
    if (startDate) {
      const dayAfterStart = new Date(startDate);
      dayAfterStart.setDate(dayAfterStart.getDate() + 1);
      if (
        date.getTime() === dayAfterStart.getTime() &&
        (info?.status === "booked" || info?.status === "unavailable")
      ) {
        return false;
      }
    }
    return info?.status === "booked" || info?.status === "unavailable";
  }

  function isRangeAvailable(start, end) {
    let d = new Date(start);
    while (d <= end) {
      if (isBlocked(d)) return false;
      d.setDate(d.getDate() + 1);
    }
    return true;
  }

  function createDayHeaders() {
    const wrapper = document.createElement("div");
    wrapper.className = "ov-day-headers";
    const days = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];
    days.forEach((day) => {
      const div = document.createElement("div");
      div.className = "ov-day-name";
      div.textContent = day;
      wrapper.appendChild(div);
    });
    return wrapper;
  }
  function createDayElement(date) {
    const day = document.createElement("div");
    day.classList.add("ov-day");
    day.textContent = date.getDate();

    // Precizan datum za poređenje u hover funkciji
    day.dataset.date = formatDate(date);

    const key = formatDate(date);
    const info = calendarData[key] || {};

    const label = document.createElement("div");
    label.className = "ov-label";

    if (date < today) {
      day.classList.add("past-day");
      label.textContent = `-`;
      day.appendChild(label);
    } else if (info.status === "booked") {
      day.classList.add("booked-day");
      label.textContent = `-`;
      day.appendChild(label);
    } else if (info.status === "unavailable") {
      day.classList.add("unavailable-day", "disabled");
      label.textContent = `-`;
      day.appendChild(label);
    } else if (info.status === "available") {
      day.classList.add("available-day");
      if (info.price) {
        label.textContent = `€${info.price}`;
      }
      day.appendChild(label);
    }


    // Posebne klase za start i end date
    if (startDate && date.toDateString() === startDate.toDateString()) {
      day.classList.add("selected", "start-date");
    }

    if (endDate && date.toDateString() === endDate.toDateString()) {
      day.classList.add("selected", "end-date");
    }

    if (
      startDate && endDate && date > startDate && date < endDate &&
      !day.classList.contains("disabled") && !day.classList.contains("booked-day")
    ) {
      day.classList.add("in-range");
    }

    if (
      !readonly &&
      !day.classList.contains("disabled") &&
      !day.classList.contains("booked-day") &&
      date >= today &&
      info?.price
    ) {
      day.style.cursor = "pointer";

      day.addEventListener("click", () => handleDateClick(date));

      // Hover efekat
      day.addEventListener("mouseenter", () => {
        if (startDate && !endDate && date > startDate) {
          highlightHoverRange(startDate, date);
        } else if (startDate && endDate) {
          highlightHoverRange(startDate, endDate);
        } else {
          highlightHoverRange(date, date);
        }
      });


      day.addEventListener("mouseleave", () => {
        clearHoverRange();
      });
    }

    return day;
  }

  function highlightHoverRange(start, end) {
    const allDays = containerEl.querySelectorAll(".ov-day");

    allDays.forEach(d => {
      d.classList.remove("hover-range", "end-date");
    });

    let rangeStart = new Date(start);
    let rangeEnd = new Date(end);

    if (rangeEnd < rangeStart) {
      [rangeStart, rangeEnd] = [rangeEnd, rangeStart];
    }

    const endDateStr = formatDate(rangeEnd);


    allDays.forEach(d => {
      const dateStr = d.dataset.date;
      if (!dateStr) return;

      const elementDate = new Date(dateStr);

      if (
        elementDate > rangeStart &&
        elementDate < rangeEnd &&
        !d.classList.contains("start-date")
      ) {
        d.classList.add("hover-range");
      }

      if (dateStr === endDateStr) {
        d.classList.add("end-date");
      }
    });

    hoverEndDate = rangeEnd;
  }




  function clearHoverRange() {
    const days = containerEl.querySelectorAll(".ov-day.hover-range");
    days.forEach(day => {
      day.classList.remove("hover-range");
    });
  }


  function renderSingleCalendar(year, month) {
    const cal = document.createElement("div");
    cal.className = "ov-calendar";

    const header = document.createElement("div");
    header.className = "ov-calendar-header";
    const title = document.createElement("span");
    title.className = "month-title";
    title.textContent = new Date(year, month).toLocaleString("default", {
      month: "long",
      year: "numeric",
    });
    header.appendChild(title);
    cal.appendChild(header);
    cal.appendChild(createDayHeaders());

    const grid = document.createElement("div");
    grid.className = "ov-days";

    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const startDay = (firstDay.getDay() + 6) % 7;

    const prevMonthLastDay = new Date(year, month, 0).getDate();
    const prevMonth = month === 0 ? 11 : month - 1;
    const prevYear = month === 0 ? year - 1 : year;

    for (let i = startDay - 1; i >= 0; i--) {
      const date = new Date(prevYear, prevMonth, prevMonthLastDay - i);
      const day = createDayElement(date);
      day.classList.add("differentDay");
      grid.appendChild(day);
    }
    for (let d = 1; d <= lastDay.getDate(); d++) {
      const date = new Date(year, month, d);
      grid.appendChild(createDayElement(date));
    }
    const totalCells = startDay + lastDay.getDate();
    const remainingCells = 42 - totalCells;
    const nextMonth = (month + 1) % 12;
    const nextYear = month === 11 ? year + 1 : year;

    for (let i = 1; i <= remainingCells; i++) {
      const date = new Date(nextYear, nextMonth, i);
      const day = createDayElement(date);
      day.classList.add("differentDay");
      grid.appendChild(day);
    }
    cal.appendChild(grid);
    return cal;
  }

  function applyResponsiveClass() {
    if (picker.offsetWidth < 650) {
      picker.classList.add("small");
    } else {
      picker.classList.remove("small");
    }
  }


  function renderPickers() {
    picker.innerHTML = "";

    const nav = document.createElement("div");
    nav.className = "ov-navigation";

    const prev = document.createElement("span");
    prev.textContent = "←";
    prev.className = "month-arrow";
    prev.addEventListener("click", () => {
      currentMonth--;
      if (currentMonth < 0) {
        currentMonth = 11;
        currentYear--;
      }
      renderPickers();
    });

    const next = document.createElement("span");
    next.textContent = "→";
    next.className = "month-arrow";
    next.addEventListener("click", () => {
      currentMonth++;
      if (currentMonth > 11) {
        currentMonth = 0;
        currentYear++;
      }
      renderPickers();
    });

    if (!readonly) {
      nav.appendChild(prev);
      nav.appendChild(next);
    }

    picker.appendChild(nav);

    const wrapper = document.createElement("div");
    wrapper.className = "ov-calendars-wrapper";

    const cal1 = renderSingleCalendar(currentYear, currentMonth);
    const nextMonth = (currentMonth + 1) % 12;
    const nextYear = currentMonth === 11 ? currentYear + 1 : currentYear;
    const cal2 = renderSingleCalendar(nextYear, nextMonth);

    wrapper.appendChild(cal1);
    wrapper.appendChild(cal2);
    picker.appendChild(wrapper);

    if (alwaysOpen || isPickerOpen) {
      picker.classList.remove("hidden");
    }

    applyResponsiveClass();
  }

  function closePickerWithDelay() {
    setTimeout(() => {
      if (!alwaysOpen) {
        picker.classList.add("hidden");
        isPickerOpen = false;
      }
    }, 100);
  }

  function handleDateClick(date) {
    if (!startDate || (startDate && endDate)) {
      startDate = date;
      endDate = null;
    } else if (date < startDate) {
      startDate = date;
      endDate = null;
    } else if (date.getTime() === startDate.getTime()) {
      const tempEnd = new Date(date);
      tempEnd.setDate(tempEnd.getDate() + 1);
      const tempKey = formatDate(tempEnd);
      const tempInfo = calendarData[tempKey];
      if (tempInfo?.status === "booked" || tempInfo?.status === "unavailable") {
        endDate = tempEnd;
      } else if (isRangeAvailable(startDate, tempEnd)) {
        endDate = tempEnd;
      } else {
        endDate = null;
      }
    } else {
      if (isRangeAvailable(startDate, date)) {
        endDate = date;
      } else {
        endDate = null;
        return;
      }
    }

    if (inputEl) {
      if (startDate && endDate) {
        inputEl.value = `${formatForInput(startDate)} – ${formatForInput(endDate)}`;
      } else {
        inputEl.value = startDate ? formatForInput(startDate) : "";
      }
    }

    const allDates = [];
    if (startDate && endDate) {
      let d = new Date(startDate);
      while (d <= endDate) {
        allDates.push(formatDate(d));
        d.setDate(d.getDate() + 1);
      }
    }

    containerEl.querySelector('#start_date').value = startDate ? formatDate(startDate) : '';
    containerEl.querySelector('#end_date').value = endDate ? formatDate(endDate) : '';
    containerEl.querySelector('#all_dates').value = allDates.join(",");

    let totalNights = Math.max(0, allDates.length - 1);
    let totalPrice = 0;

    for (let i = 0; i < allDates.length - 1; i++) {
      const dateKey = allDates[i];
      const info = calendarData[dateKey];
      if (info?.price) {
        totalPrice += parseFloat(info.price);
      }
    }

    const priceEl = document.getElementById("ov_total_price");
    const nightsEl = document.getElementById("ov_total_nights");
    const boxEl = document.getElementById("ov_total_price_box");

    if (priceEl) priceEl.textContent = `€${totalPrice.toFixed(2)}`;
    if (nightsEl) {
      nightsEl.textContent = totalNights === 1
        ? `for ${totalNights} night`
        : `for ${totalNights} nights`;
    }
    if (boxEl && totalNights > 0) {
      boxEl.style.display = "block";
    } else if (boxEl) {
      boxEl.style.display = "none";
    }

    renderPickers();

    if (!alwaysOpen && startDate && endDate) {
      closePickerWithDelay();
    }
  }

  if (!readonly && !alwaysOpen && inputEl) {
    inputEl.addEventListener("click", (e) => {
      e.stopPropagation();
      if (!isPickerOpen) {
        isPickerOpen = true;
        renderPickers();
      }
    });
    picker.addEventListener("click", (e) => e.stopPropagation());

    document.addEventListener("click", (e) => {
      if (!alwaysOpen && isPickerOpen && !picker.contains(e.target) && e.target !== inputEl) {
        closePickerWithDelay();
      }
    });
  }

  window.addEventListener("resize", applyResponsiveClass);

  // Brisanje hover efekata kada se izđe iz pickera
  picker.addEventListener("mouseleave", () => {
    const allDays = containerEl.querySelectorAll(".ov-day");
    allDays.forEach(d => d.classList.remove("hover-range"));
  });

  if (alwaysOpen || readonly) {
    renderPickers();
  }
}