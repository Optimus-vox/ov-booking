document.addEventListener("DOMContentLoaded", function () {
    const modal = document.getElementById("custom-slider-modal");
    if (!modal) return; 
    const modalImage = document.getElementById("custom-slider-image");
    const thumbnailsContainer = document.querySelector(".custom-slider-thumbnails");
    const closeModal = document.querySelector(".custom-modal-close");
    const leftArrow = document.querySelector(".left-arrow");
    const rightArrow = document.querySelector(".right-arrow");

    const imageLinks = document.querySelectorAll(".lightgallery .hreff-wrap");
    const allImages = Array.from(imageLinks).map((a, index) => {
        a.dataset.index = index;
        a.addEventListener("click", (e) => {
            e.preventDefault();
            openModal(index);
        });
        return {
            full: a.getAttribute("href"),
            thumb: a.querySelector("img")?.getAttribute("src") || ''
        };
    });

    let currentIndex = 0;
    let manualScroll = false; // za detekciju ručnog scrollanja

    function openModal(index) {
        currentIndex = index;
        showImage(index, true);
        modal.classList.remove("hidden");
        document.body.style.overflow = 'hidden';
    }

    function closeModalHandler() {
        modal.classList.add("hidden");
        document.body.style.overflow = '';
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

        // === DINAMIČKA PROMENA STILA BASED ON SCREEN WIDTH ===
        const totalWidth = allImages.length * 160;
        if (totalWidth > window.innerWidth) {
            thumbnailsContainer.style.justifyContent = "flex-start";
        } else {
            thumbnailsContainer.style.justifyContent = "center";
        }

        // === SCROLL TO ACTIVE THUMB ===
        if (!manualScroll || forceScroll) {
            const activeThumb = thumbnailsContainer.querySelector("img.active");
            if (activeThumb) {
                activeThumb.scrollIntoView({
                    behavior: "smooth",
                    inline: "center",
                    block: "nearest"
                });
            }
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
    modal?.addEventListener("click", (e) => {
        if (e.target === modal) closeModalHandler();
    });

    document.addEventListener("keydown", function (e) {
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
        }, 400); // reset posle 400ms
    });

    // Drag scroll mouse
    let isDown = false;
    let startX;
    let scrollLeft;

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
