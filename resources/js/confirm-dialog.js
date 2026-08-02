let dialogController;
let activeTrigger = null;
const approvedTriggers = new WeakSet();

function initializeDialog(dialog, signal) {
    const title = dialog.querySelector("[data-confirm-title]");
    const message = dialog.querySelector("[data-confirm-message]");
    const promptArea = dialog.querySelector("[data-confirm-prompt]");
    const phraseLabel = dialog.querySelector("[data-confirm-phrase]");
    const input = dialog.querySelector("[data-confirm-input]");
    const validation = dialog.querySelector("[data-confirm-validation]");
    const accept = dialog.querySelector("[data-confirm-accept]");
    const cancel = dialog.querySelector(".app-confirm-actions [data-confirm-cancel]");

    const close = () => {
        if (dialog.open) dialog.close("cancel");
    };

    const validatePrompt = () => {
        const phrase = activeTrigger?.dataset.confirmPhrase || "";
        const valid = phrase === "" || input.value === phrase;

        accept.disabled = !valid;
        validation.textContent = input.value && !valid
            ? `Enter ${phrase} exactly as shown.`
            : "";

        return valid;
    };

    dialog.querySelectorAll("[data-confirm-cancel]").forEach((button) => {
        button.addEventListener("click", close, { signal });
    });

    input.addEventListener("input", validatePrompt, { signal });
    input.addEventListener("keydown", (event) => {
        if (event.key !== "Enter" || !validatePrompt()) return;

        event.preventDefault();
        accept.click();
    }, { signal });

    accept.addEventListener("click", () => {
        if (!activeTrigger || !validatePrompt()) {
            input.focus();
            return;
        }

        const trigger = activeTrigger;
        activeTrigger = null;
        approvedTriggers.add(trigger);
        dialog.close("confirmed");

        if (trigger.isConnected) trigger.click();
    }, { signal });

    dialog.addEventListener("click", (event) => {
        if (event.target === dialog) close();
    }, { signal });

    dialog.addEventListener("close", () => {
        const trigger = activeTrigger;
        activeTrigger = null;
        input.value = "";
        validation.textContent = "";
        promptArea.hidden = true;
        accept.disabled = false;
        dialog.dataset.variant = "danger";

        if (trigger?.isConnected) trigger.focus({ preventScroll: true });
    }, { signal });

    return (trigger) => {
        activeTrigger = trigger;

        const phrase = trigger.dataset.confirmPhrase || "";
        title.textContent = trigger.dataset.confirmTitle || "Confirm action";
        message.textContent = trigger.dataset.confirmMessage || "Are you sure you want to continue?";
        phraseLabel.textContent = phrase;
        promptArea.hidden = phrase === "";
        accept.textContent = trigger.dataset.confirmButton || "Confirm";
        dialog.dataset.variant = trigger.dataset.confirmVariant || "danger";
        input.value = "";
        validation.textContent = "";
        accept.disabled = phrase !== "";

        dialog.showModal();
        requestAnimationFrame(() => {
            if (phrase) {
                input.focus();
            } else {
                cancel.focus();
            }
        });
    };
}

export function initializeConfirmDialog() {
    dialogController?.abort();

    const dialog = document.querySelector("[data-confirm-dialog]");
    if (!dialog) return;

    dialogController = new AbortController();
    const { signal } = dialogController;
    const open = initializeDialog(dialog, signal);

    document.addEventListener("click", (event) => {
        const trigger = event.target.closest("[data-confirm-message][data-confirm-button]");
        if (!trigger) return;

        if (approvedTriggers.has(trigger)) {
            approvedTriggers.delete(trigger);
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        open(trigger);
    }, { capture: true, signal });
}
