let actionController;
let livewireHookRegistered = false;
const pendingTargets = new Map();

function focusActionArea(selector) {
    let target;

    try {
        target = document.querySelector(selector);
    } catch {
        return;
    }

    if (!target?.matches("[data-admin-action-area]")) return;

    const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    target.focus({ preventScroll: true });
    target.scrollIntoView({
        behavior: reducedMotion ? "auto" : "smooth",
        block: "start",
        inline: "nearest",
    });
}

function registerLivewireHook() {
    if (livewireHookRegistered || !window.Livewire) return;

    window.Livewire.hook("commit", ({ component, succeed, fail }) => {
        const componentId = component.id;

        succeed(() => {
            const selector = pendingTargets.get(componentId);
            if (!selector) return;

            pendingTargets.delete(componentId);
            requestAnimationFrame(() => focusActionArea(selector));
        });

        fail(() => pendingTargets.delete(componentId));
    });

    livewireHookRegistered = true;
}

export function initializeAdminActions() {
    actionController?.abort();
    pendingTargets.clear();

    if (!document.body.classList.contains("admin-body")) return;

    actionController = new AbortController();
    const { signal } = actionController;

    document.addEventListener("click", (event) => {
        const trigger = event.target.closest("[data-admin-focus-target]");
        const component = trigger?.closest("[wire\\:id]");
        const selector = trigger?.dataset.adminFocusTarget;
        const componentId = component?.getAttribute("wire:id");

        if (!selector || !componentId) return;

        pendingTargets.set(componentId, selector);
    }, { capture: true, signal });

    registerLivewireHook();
}
