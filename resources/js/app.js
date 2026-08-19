import { initializeHeader } from "./site/header.js";
import { initializeMain, initializePartnerCarousels } from "./site/main.js";
import { initializeFooter } from "./site/footer.js";
import { initializeSchedule } from "./site/schedule.js";
import { initializeGalleryLightboxes } from "./site/gallery.js";
import { initializeConsentControls } from "./site/forms.js";
import { initializeAnimations } from "./site/animations.js";
import { initializeAdminActions } from "./admin-actions.js";
import { initializeAdminEditor } from "./admin-editor.js";
import { initializeAdminMediaPicker } from "./admin-media-picker.js";
import { initializeAdminAuth } from "./admin-auth.js";
import { initializeAdminNavigation } from "./admin-navigation.js";
import { initializeAdminGalleryUpload } from "./admin-gallery-upload.js";
import { initializeConfirmDialog } from "./confirm-dialog.js";
import { applicationUrl, normalizeInternalLinks } from "./site/url.js";

let initializedDocument = null;

window.addEventListener("pageshow", (event) => {
    if (!document.body.classList.contains("admin-body")) return;

    const navigation = performance.getEntriesByType("navigation")[0];
    if (event.persisted || navigation?.type === "back_forward") {
        window.location.reload();
    }
});

function initializeSite() {
    if (initializedDocument === document.body.dataset.navigationKey) {
        return;
    }

    initializedDocument = document.body.dataset.navigationKey;
    normalizeInternalLinks();
    initializeHeader();
    initializeMain();
    initializeSchedule();
    initializeGalleryLightboxes();
    initializeConsentControls();
    initializeFooter();
    initializeAnimations();
    initializeConfirmDialog();
    initializeAdminActions();
    initializeAdminEditor();
    initializeAdminMediaPicker();
    initializeAdminAuth();
    initializeAdminNavigation();
    initializeAdminGalleryUpload();
    initializeTurnstile();
}

function initializeTurnstile() {
    if (!window.turnstile) return;

    document.querySelectorAll(".cf-turnstile:not([data-rendered])").forEach((widget) => {
        widget.dataset.rendered = "true";
        window.turnstile.render(widget, {
            sitekey: widget.dataset.sitekey,
            callback(token) {
                const input = widget.closest("form")?.querySelector("[data-turnstile-token]");
                if (!input) return;
                input.value = token;
                input.dispatchEvent(new Event("input", { bubbles: true }));
            },
            "expired-callback"() {
                const input = widget.closest("form")?.querySelector("[data-turnstile-token]");
                if (!input) return;
                input.value = "";
                input.dispatchEvent(new Event("input", { bubbles: true }));
            },
        });
    });
}

window.DMVTurnstileLoaded = initializeTurnstile;

document.addEventListener("DOMContentLoaded", initializeSite);
document.addEventListener("livewire:navigated", () => {
    initializedDocument = null;
    initializeSite();
});

document.addEventListener("livewire:init", () => {
    window.Livewire.hook("commit", ({ succeed }) => {
        succeed(() => requestAnimationFrame(() => {
            initializeGalleryLightboxes();
            initializeConsentControls();
        }));
    });

    window.Livewire.on("site-form-complete", () => {
        requestAnimationFrame(() => {
            document.querySelector("[data-livewire-form] [aria-live='polite']")?.focus?.();
            document.querySelectorAll(".cf-turnstile[data-rendered]").forEach((widget) => {
                window.turnstile?.reset(widget);
            });
            initializePartnerCarousels();
        });
    });
});

document.addEventListener("click", (event) => {
    if (event.target.closest("[data-download-sponsor]")) {
        window.location.assign(applicationUrl("sponsor-pack"));
    }
});
