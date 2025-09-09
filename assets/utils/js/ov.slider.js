document.addEventListener("DOMContentLoaded", function () {
  const modal = document.getElementById("custom-slider-modal");
  if (!modal) return;

  const modalImage = document.getElementById("custom-slider-image");
  const thumbnailsContainer = document.querySelector(".custom-slider-thumbnails");
  const closeModal = document.querySelector(".custom-modal-close");
  const leftArrow = document.querySelector(".left-arrow");
  const rightArrow = document.querySelector(".right-arrow");

  // 1) Izvori slika iz #lightgallery-all (kanonska lista)
  const modalLinks = Array.from(document.querySelectorAll("#lightgallery-all a"));
  const allImages = modalLinks.map((a) => ({
    full: a.getAttribute("href"),
    thumb: (a.querySelector("img") && a.querySelector("img").getAttribute("src")) || ""
  }));

  // Helper da normalizujemo URL (ukloni trailing slash / relative vs absolute)
  function norm(u) {
    try { return new URL(u, window.location.origin).href; }
    catch { return u; }
  }
  const allMap = new Map(allImages.map((img, i) => [norm(img.full), i]));

  let currentIndex = 0;
  let manualScroll = false;
  let thumbnails = [];

  function openModal(index) {
    currentIndex = Math.max(0, Math.min(index, allImages.length - 1));
    modal.classList.remove("hidden");
    document.body.style.overflow = "hidden";
    if (thumbnails.length === 0) createThumbnails();
    showImage(currentIndex, true);
  }

  function closeModalHandler() {
    modal.classList.add("hidden");
    document.body.style.overflow = "";
  }

  function createThumbnails() {
    thumbnailsContainer.innerHTML = "";
    allImages.forEach((img, i) => {
      const thumb = document.createElement("img");
      thumb.src = img.thumb || img.full;
      thumb.addEventListener("click", () => {
        currentIndex = i;
        showImage(i, true);
      });
      thumbnailsContainer.appendChild(thumb);
      thumbnails.push(thumb);
    });
    const totalWidth = allImages.length * 160;
    thumbnailsContainer.style.justifyContent = totalWidth > window.innerWidth ? "flex-start" : "center";
  }

  function showImage(index, forceScroll = false) {
    const image = allImages[index];
    if (!image) return;
    modalImage.classList.add("fade-out");
    setTimeout(() => {
      modalImage.src = image.full;
      modalImage.classList.remove("fade-out");
    }, 200);

    thumbnails.forEach((t, i) => t.classList.toggle("active", i === index));

    if (!manualScroll || forceScroll) {
      const activeThumb = thumbnailsContainer.querySelector("img.active");
      activeThumb && activeThumb.scrollIntoView({ behavior: "smooth", inline: "center", block: "nearest" });
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

  // 2) GLOBALNI DELEGIRANI KLIK – hvata i glavnu i grid galeriju, bez obzira na wrapper
  document.addEventListener("click", function (e) {
    const link = e.target.closest('a.hreff-wrap, .lightgallery a, .product-gallery a');
    if (!link) return;

    const href = link.getAttribute("href");
    if (!href) return;

    // Spreči default samo ako ga mi obrađujemo
    const idx = allMap.has(norm(href)) ? allMap.get(norm(href)) : -1;
    if (idx >= 0) {
      e.preventDefault();
      openModal(idx);
    }
  });

  // Navigacija i izlaz
  leftArrow && leftArrow.addEventListener("click", prevImage);
  rightArrow && rightArrow.addEventListener("click", nextImage);
  closeModal && closeModal.addEventListener("click", closeModalHandler);
  modal.addEventListener("click", (e) => { if (e.target === modal) closeModalHandler(); });

  document.addEventListener("keydown", (e) => {
    if (modal.classList.contains("hidden")) return;
    if (e.key === "ArrowRight") nextImage();
    if (e.key === "ArrowLeft")  prevImage();
    if (e.key === "Escape")     closeModalHandler();
  });

  // Touch swipe
  let touchStartX = 0;
  modal.addEventListener("touchstart", (e) => { touchStartX = e.changedTouches[0].screenX; });
  modal.addEventListener("touchend",   (e) => {
    const x = e.changedTouches[0].screenX;
    if (x < touchStartX - 50) nextImage();
    else if (x > touchStartX + 50) prevImage();
  });

  // Thumbs UX – wheel/drag/idle
  let scrollTimer;
  thumbnailsContainer.addEventListener("scroll", () => {
    manualScroll = true;
    clearTimeout(scrollTimer);
    scrollTimer = setTimeout(() => { manualScroll = false; }, 400);
  });

  let isDown = false, startX, scrollLeft;
  thumbnailsContainer.addEventListener("mousedown", (e) => {
    isDown = true;
    thumbnailsContainer.classList.add("dragging");
    startX = e.pageX - thumbnailsContainer.offsetLeft;
    scrollLeft = thumbnailsContainer.scrollLeft;
  });
  thumbnailsContainer.addEventListener("mouseleave", () => { isDown = false; thumbnailsContainer.classList.remove("dragging"); });
  thumbnailsContainer.addEventListener("mouseup",    () => { isDown = false; thumbnailsContainer.classList.remove("dragging"); });
  thumbnailsContainer.addEventListener("mousemove", (e) => {
    if (!isDown) return;
    e.preventDefault();
    manualScroll = true;
    const x = e.pageX - thumbnailsContainer.offsetLeft;
    thumbnailsContainer.scrollLeft = scrollLeft - (x - startX) * 2;
  });
  thumbnailsContainer.addEventListener("wheel", (e) => {
    if (e.deltaY === 0) return;
    e.preventDefault();
    manualScroll = true;
    thumbnailsContainer.scrollLeft += e.deltaY;
    clearTimeout(scrollTimer);
    scrollTimer = setTimeout(() => { manualScroll = false; }, 400);
  });
});
