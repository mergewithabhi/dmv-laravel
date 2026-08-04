export function initializeConsentControls() {
    document.querySelectorAll("[data-livewire-form]").forEach((form) => {
        const consent = form.querySelector('input[name="consent"]');
        const submit = form.querySelector('button[type="submit"], input[type="submit"]');
        if (!consent || !submit) return;

        const sync = () => {
            submit.disabled = !consent.checked;
            submit.setAttribute("aria-disabled", String(!consent.checked));
        };

        sync();
        if (consent.dataset.consentReady === "true") return;

        consent.dataset.consentReady = "true";
        consent.addEventListener("change", sync);
        consent.addEventListener("input", sync);
    });
}
