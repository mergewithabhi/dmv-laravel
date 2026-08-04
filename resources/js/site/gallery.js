let galleryController;

export function initializeGalleryLightboxes() {
    galleryController?.abort();
    galleryController = new AbortController();
    const { signal } = galleryController;
    document.body.classList.remove("gallery-lightbox-open");

    document.querySelectorAll("[data-gallery-root]").forEach((root) => {
        const triggers = [...root.querySelectorAll("[data-gallery-open]")];
        const dialog = root.querySelector("[data-gallery-lightbox]");
        const media = dialog?.querySelector(".gallery-lightbox-media");
        const image = dialog?.querySelector("[data-gallery-lightbox-image]");
        const video = dialog?.querySelector("[data-gallery-lightbox-video]");
        const title = dialog?.querySelector("[data-gallery-lightbox-title]");
        const caption = dialog?.querySelector("[data-gallery-lightbox-caption]");
        const previous = dialog?.querySelector("[data-gallery-lightbox-previous]");
        const next = dialog?.querySelector("[data-gallery-lightbox-next]");
        const close = dialog?.querySelector("[data-gallery-lightbox-close]");
        if (!dialog || !media || !image || !video || !title || !caption || !previous || !next || !close || !triggers.length) return;

        let activeIndex = 0;
        let returnFocus = null;

        const fitImage = () => {
            if (image.hidden || !image.naturalWidth || !image.naturalHeight) return;

            const availableWidth = media.clientWidth;
            const availableHeight = media.clientHeight;
            if (!availableWidth || !availableHeight) return;

            const scale = Math.min(
                availableWidth / image.naturalWidth,
                availableHeight / image.naturalHeight,
            );
            image.style.width = `${Math.floor(image.naturalWidth * scale)}px`;
            image.style.height = `${Math.floor(image.naturalHeight * scale)}px`;
        };

        const showImage = (index) => {
            activeIndex = (index + triggers.length) % triggers.length;
            const trigger = triggers[activeIndex];
            const isVideo = trigger.dataset.galleryType === "video";

            video.pause();
            video.removeAttribute("src");
            video.load();
            image.removeAttribute("src");
            image.hidden = isVideo;
            video.hidden = !isVideo;
            if (isVideo) {
                video.src = trigger.dataset.gallerySrc;
                video.setAttribute("aria-label", trigger.dataset.galleryAlt || trigger.dataset.galleryTitle || "Gallery video");
                video.load();
            } else {
                image.src = trigger.dataset.gallerySrc;
                image.alt = trigger.dataset.galleryAlt || "";
                if (image.complete) requestAnimationFrame(fitImage);
            }
            title.textContent = trigger.dataset.galleryTitle || "";
            caption.textContent = trigger.dataset.galleryCaption || "";
            title.hidden = title.textContent === "";
            caption.hidden = caption.textContent === "";
            previous.disabled = triggers.length < 2;
            next.disabled = triggers.length < 2;
        };

        const closeDialog = () => {
            if (dialog.open) dialog.close();
        };

        triggers.forEach((trigger, index) => {
            trigger.addEventListener("click", () => {
                returnFocus = trigger;
                showImage(index);
                dialog.showModal();
                document.body.classList.add("gallery-lightbox-open");
                requestAnimationFrame(fitImage);
            }, { signal });
        });

        image.addEventListener("load", fitImage, { signal });
        window.addEventListener("resize", fitImage, { signal });
        previous.addEventListener("click", () => showImage(activeIndex - 1), { signal });
        next.addEventListener("click", () => showImage(activeIndex + 1), { signal });
        close.addEventListener("click", closeDialog, { signal });
        dialog.addEventListener("click", (event) => {
            if (event.target === dialog) closeDialog();
        }, { signal });
        dialog.addEventListener("keydown", (event) => {
            if (event.key === "ArrowLeft") {
                event.preventDefault();
                showImage(activeIndex - 1);
            } else if (event.key === "ArrowRight") {
                event.preventDefault();
                showImage(activeIndex + 1);
            }
        }, { signal });
        dialog.addEventListener("close", () => {
            document.body.classList.remove("gallery-lightbox-open");
            image.removeAttribute("src");
            video.pause();
            video.removeAttribute("src");
            video.load();
            returnFocus?.focus();
        }, { signal });
        document.addEventListener("livewire:navigating", () => {
            closeDialog();
            galleryController?.abort();
        }, { once: true, signal });

        const requestedItem = new URL(window.location.href).searchParams.get("item");
        const requestedTrigger = requestedItem
            ? triggers.find((trigger) => trigger.dataset.galleryId === requestedItem)
            : null;
        if (requestedTrigger) {
            requestAnimationFrame(() => requestedTrigger.click());
        }
    });
}
