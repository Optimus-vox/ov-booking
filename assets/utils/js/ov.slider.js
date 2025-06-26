document.addEventListener("DOMContentLoaded", function () {
  const modal = document.getElementById("custom-slider-modal");
  if (!modal) return;
  const modalImage = document.getElementById("custom-slider-image");
  const thumbnailsContainer = document.querySelector(".custom-slider-thumbnails");
  const closeModal = document.querySelector(".custom-modal-close");
  const leftArrow = document.querySelector(".left-arrow");
  const rightArrow = document.querySelector(".right-arrow");

  // Prikupi sve <a> iz skrivenog lightgallery-all za modal galeriju
  const modalLinkNodes = document.querySelectorAll("#lightgallery-all a");
  const modalLinks = Array.from(modalLinkNodes);
  const allImages = modalLinks.map((a) => ({
    full: a.href,
    thumb: a.querySelector("img")?.src || "",
  }));

  // Dodaj listener za klik na vidljive linkove galerije
  const visibleLinks = document.querySelectorAll(".lightgallery .hreff-wrap");
  visibleLinks.forEach((link) => {
    link.addEventListener("click", (e) => {
      e.preventDefault();
      const href = link.getAttribute("href");
      const idx = modalLinks.findIndex((a) => a.href === href);
      if (idx !== -1) openModal(idx);
    });
  });

  let currentIndex = 0;
  let manualScroll = false;

  function openModal(index) {
    currentIndex = index;
    showImage(index, true);
    modal.classList.remove("hidden");
    document.body.style.overflow = "hidden";
  }

  function closeModalHandler() {
    modal.classList.add("hidden");
    document.body.style.overflow = "";
  }

  function showImage(index, forceScroll = false) {
    const image = allImages[index];
    if (!image) return;

    modalImage.classList.add("fade-out");
    setTimeout(() => {
      modalImage.src = image.full;
      modalImage.classList.remove("fade-out");
    }, 200);

    thumbnailsContainer.innerHTML = "";
    allImages.forEach((img, i) => {
      const thumb = document.createElement("img");
      thumb.src = img.thumb;
      if (i === index) thumb.classList.add("active");
      thumb.addEventListener("click", () => {
        currentIndex = i;
        showImage(i, true);
      });
      thumbnailsContainer.appendChild(thumb);
    });

    const totalWidth = allImages.length * 160;
    thumbnailsContainer.style.justifyContent = totalWidth > window.innerWidth ? "flex-start" : "center";

    if (!manualScroll || forceScroll) {
      const activeThumb = thumbnailsContainer.querySelector("img.active");
      activeThumb?.scrollIntoView({ behavior: "smooth", inline: "center", block: "nearest" });
    }
  }

  function nextImage() {
    currentIndex = (currentIndex + 1) % allImages.length;
    showImage(currentIndex, true);
  }

  function prevImage() {
    currentIndex = (currentIndex - 1 + allImages.length) % allImages.length;
    showImage(currentIndex, true);
  }

  leftArrow?.addEventListener("click", prevImage);
  rightArrow?.addEventListener("click", nextImage);
  closeModal?.addEventListener("click", closeModalHandler);
  modal.addEventListener("click", (e) => {
    if (e.target === modal) closeModalHandler();
  });

  document.addEventListener("keydown", (e) => {
    if (modal.classList.contains("hidden")) return;
    if (e.key === "ArrowRight") nextImage();
    if (e.key === "ArrowLeft") prevImage();
    if (e.key === "Escape") closeModalHandler();
  });

  let touchStartX = 0;
  modal.addEventListener("touchstart", (e) => {
    touchStartX = e.changedTouches[0].screenX;
  });
  modal.addEventListener("touchend", (e) => {
    const touchEndX = e.changedTouches[0].screenX;
    if (touchEndX < touchStartX - 50) nextImage();
    else if (touchEndX > touchStartX + 50) prevImage();
  });

  // Manual scroll detection
  let scrollTimer;
  thumbnailsContainer.addEventListener("scroll", () => {
    manualScroll = true;
    clearTimeout(scrollTimer);
    scrollTimer = setTimeout(() => {
      manualScroll = false;
    }, 400);
  });

  // Drag-to-scroll thumbnails
  let isDown = false;
  let startX, scrollLeft;
  thumbnailsContainer.addEventListener("mousedown", (e) => {
    isDown = true;
    thumbnailsContainer.classList.add("dragging");
    startX = e.pageX - thumbnailsContainer.offsetLeft;
    scrollLeft = thumbnailsContainer.scrollLeft;
  });
  thumbnailsContainer.addEventListener("mouseleave", () => {
    isDown = false;
    thumbnailsContainer.classList.remove("dragging");
  });
  thumbnailsContainer.addEventListener("mouseup", () => {
    isDown = false;
    thumbnailsContainer.classList.remove("dragging");
  });
  thumbnailsContainer.addEventListener("mousemove", (e) => {
    if (!isDown) return;
    e.preventDefault();
    manualScroll = true;
    const x = e.pageX - thumbnailsContainer.offsetLeft;
    const walk = (x - startX) * 2;
    thumbnailsContainer.scrollLeft = scrollLeft - walk;
  });
  thumbnailsContainer.addEventListener("wheel", (e) => {
    if (e.deltaY === 0) return;
    e.preventDefault();
    manualScroll = true;
    thumbnailsContainer.scrollLeft += e.deltaY;
    clearTimeout(scrollTimer);
    scrollTimer = setTimeout(() => {
      manualScroll = false;
    }, 400);
  });
});
