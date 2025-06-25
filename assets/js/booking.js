jQuery(function ($) {
  function getDatesInRange(startDate, endDate) {
    let dateArray = [];
    let currentDate = startDate.clone();
    while (currentDate.isSameOrBefore(endDate)) {
      dateArray.push(currentDate.format("DD/MM/YYYY"));
      currentDate.add(1, "days");
    }
    return dateArray;
  }

  $("#daterange").daterangepicker(
    {
      locale: {
        format: "YYYY-MM-DD",
      },
    },
    function (start, end) {
      $("#daterange").val(start.format("YYYY-MM-DD") + " - " + end.format("YYYY-MM-DD"));
      $("#start_date").val(start.format("YYYY-MM-DD"));
      $("#end_date").val(end.format("YYYY-MM-DD"));
    }
  );

  $("#dateForm").on("submit", function (e) {
    e.preventDefault();
    const picker = $("#daterange").data("daterangepicker");
    const start = picker.startDate;
    const end = picker.endDate;
    const allDates = getDatesInRange(start, end);
    $("#all_dates").val(allDates.join(","));
  });

  const buttons = document.querySelectorAll(".read-more-toggle");
  buttons.forEach((button) => {
    button.addEventListener("click", function (e) {
      e.preventDefault();
      const excerpt = this.previousElementSibling.previousElementSibling;
      const fullText = this.previousElementSibling;
      if (fullText.style.display === "none" || fullText.style.display === "") {
        excerpt.style.display = "none";
        fullText.style.display = "block";
        this.textContent = "Read less";
      } else {
        excerpt.style.display = "block";
        fullText.style.display = "none";
        this.textContent = "Read more";
      }
    });
  });
});
