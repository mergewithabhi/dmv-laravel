let galleryUploadController;

export function initializeAdminGalleryUpload() {
    galleryUploadController?.abort();
    galleryUploadController = new AbortController();
    const { signal } = galleryUploadController;

    document.querySelectorAll("[data-gallery-upload-input]").forEach((input) => {
        const form = input.closest("form");
        const progress = form?.querySelector("[data-gallery-upload-progress]");
        const bar = progress?.querySelector("[data-gallery-upload-bar]");
        const label = progress?.querySelector("[data-gallery-upload-label]");
        if (!progress || !bar || !label) return;

        const update = (value, text, state = "") => {
            const percent = Math.max(0, Math.min(100, Number(value) || 0));
            progress.hidden = false;
            progress.dataset.state = state;
            progress.setAttribute("aria-valuenow", String(percent));
            bar.value = percent;
            bar.textContent = `${percent}%`;
            label.textContent = text;
        };

        input.addEventListener("livewire-upload-start", () => {
            update(0, "Uploading 0%");
        }, { signal });
        input.addEventListener("livewire-upload-progress", (event) => {
            const percent = event.detail.progress;
            update(percent, `Uploading ${percent}%`);
        }, { signal });
        input.addEventListener("livewire-upload-finish", () => {
            update(100, "Upload complete", "complete");
        }, { signal });
        input.addEventListener("livewire-upload-error", () => {
            update(0, "Upload failed. Check file size and format.", "error");
        }, { signal });
        input.addEventListener("livewire-upload-cancel", () => {
            progress.hidden = true;
        }, { signal });
    });
}
