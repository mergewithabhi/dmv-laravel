let editorController;

export function initializeAdminEditor() {
    editorController?.abort();
    const editor = document.querySelector("[data-cms-editor]");
    if (!editor) return;

    editorController = new AbortController();
    const { signal } = editorController;

    window.addEventListener("beforeunload", (event) => {
        if (editor.dataset.unsaved !== "true") return;
        event.preventDefault();
        event.returnValue = "";
    }, { signal });
}
