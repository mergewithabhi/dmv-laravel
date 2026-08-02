let mediaController;

export function initializeAdminMediaPicker() {
    mediaController?.abort();
    mediaController = new AbortController();
    const { signal } = mediaController;

    document.addEventListener("click", (event) => {
        const open = event.target.closest("[data-media-dialog-open]");
        if (open) {
            document.getElementById(open.dataset.mediaDialogOpen)?.showModal();
            return;
        }

        const close = event.target.closest("[data-media-dialog-close]");
        close?.closest("dialog")?.close();

        if (event.target.closest("[data-media-choice]")) {
            event.target.closest("dialog")?.close();
        }
    }, { signal });

    document.addEventListener("input", (event) => {
        if (!event.target.matches("[data-media-search]")) return;
        const query = event.target.value.trim().toLowerCase();
        event.target.closest("dialog")?.querySelectorAll("[data-media-choice]").forEach((choice) => {
            choice.hidden = query !== "" && !choice.dataset.mediaTitle.includes(query);
        });
    }, { signal });
}
