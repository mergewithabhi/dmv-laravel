export function initializeAdminAuth() {
    document.querySelectorAll("[data-password-toggle]").forEach((button) => {
        if (button.dataset.passwordToggleReady === "true") {
            return;
        }

        const input = document.getElementById(button.getAttribute("aria-controls"));
        if (!input) {
            return;
        }

        button.dataset.passwordToggleReady = "true";
        button.addEventListener("click", () => {
            const showingPassword = input.type === "text";
            input.type = showingPassword ? "password" : "text";

            const label = showingPassword ? "Show password" : "Hide password";
            button.setAttribute("aria-label", label);
            button.setAttribute("title", label);
            button.querySelector("[data-eye-open]")?.toggleAttribute("hidden", !showingPassword);
            button.querySelector("[data-eye-closed]")?.toggleAttribute("hidden", showingPassword);
            input.focus({ preventScroll: true });
        });
    });
}
