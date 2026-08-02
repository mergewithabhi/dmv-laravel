const reduceMotionQuery = window.matchMedia("(prefers-reduced-motion: reduce)");
let formFeedbackController;
let formStatusObservers = [];

const revealDefinitions = [
  ["main > section, .page-content > section, .page-content > div, .about-page > section, .roster-page > section, .schedule-page > section, .sponsors-page > section", "up"],
  [".hero-copy, .about-hero-copy, .roster-hero-copy, .schedule-hero-copy, .sponsor-hero-copy, .contact-hero-copy", "left"],
  [".hero-art, .about-hero-media, .roster-hero-media, .schedule-hero-media, .sponsor-hero-media, .contact-hero-media", "scale"],
  [".about-story-media, .roster-intro-media, .home-venue, .contact-info-panel", "left"],
  [".about-story-copy, .roster-intro-copy, .game-day-cta, .contact-form-panel", "right"],
  [".newsletter, .sponsor-cta, .roster-cta, .schedule-sponsor-cta, .sponsor-final-cta, .family-cta, .stay-connected", "wipe"],
];

const staggerSelectors = [
  ".hero-actions",
  ".stats-strip",
  ".values",
  ".news-grid",
  ".players",
  ".partner-carousel-track",
  ".commitment-grid",
  ".gallery-grid",
  ".about-stat-bar",
  ".mission-vision-grid",
  ".about-values-grid",
  ".leadership-grid",
  ".community-impact-gallery",
  ".journey-line",
  ".about-why-grid",
  ".inside-gallery > div",
  ".roster-stat-bar",
  ".roster-principles",
  ".full-roster-grid",
  ".coaching-grid",
  ".schedule-stat-bar",
  ".schedule-countdown",
  ".season-table tbody",
  ".season-stats > div",
  ".sponsor-stat-bar",
  ".logo-grid",
  ".sponsor-benefits > div:last-child",
  ".sponsor-request-form",
  ".contact-info-panel",
  ".contact-form",
  ".stay-connected nav",
  ".footer-grid",
];

const cardSelectors = [
  ".news-card",
  ".player-card",
  ".about-values-grid article",
  ".leadership-grid article",
  ".about-why-grid article",
  ".roster-player",
  ".coaching-grid article",
  ".mission-vision-grid article",
  ".schedule-calendar",
  ".season-schedule",
  ".standings-panel",
  ".download-panel",
  ".home-venue",
  ".season-stats",
  ".schedule-partners",
  ".sponsor-tier",
  ".sponsor-benefits article",
  ".contact-detail",
];

const counterSelector = [
  ".stat strong",
  ".about-stat-bar strong",
  ".roster-stat-bar strong",
  ".schedule-stat-bar strong",
  ".sponsor-stat-bar strong",
  ".season-stats article strong",
].join(",");

export function initializeAnimations() {
  decorateMotionElements();
  const reduced = reduceMotionQuery.matches;
  document.documentElement.classList.add(reduced ? "motion-reduced" : "motion-enabled");

  if (reduced || !("IntersectionObserver" in window)) {
    revealEverything();
    return;
  }

  initializeReveals();
  initializeCounters();
  initializeFormFeedback();
  requestAnimationFrame(() => document.documentElement.classList.add("page-ready"));
}

function decorateMotionElements() {
  revealDefinitions.forEach(([selector, type]) => {
    document.querySelectorAll(selector).forEach((element) => {
      if (!element.dataset.reveal) element.dataset.reveal = type;
    });
  });

  document.querySelectorAll(["[data-stagger]", ...staggerSelectors].join(",")).forEach((container) => {
    container.dataset.stagger = "";
    [...container.children].forEach((child, index) => {
      if (!child.dataset.reveal) child.dataset.reveal = "up";
      child.style.setProperty(
        "--motion-delay",
        `calc(var(--motion-stagger) * ${Math.min(index, 10)})`,
      );
    });
  });

  document.querySelectorAll(".media-placeholder, .hero-art, .about-image").forEach((media) => {
    media.dataset.motionMedia = "";
    if (!media.dataset.reveal) media.dataset.reveal = "scale";
  });

  document.querySelectorAll(cardSelectors.join(",")).forEach((card) => card.classList.add("motion-card"));
  document.querySelectorAll(".logo-grid > div, .partner-carousel-slide")
    .forEach((logo) => logo.classList.add("motion-logo"));

  document.querySelectorAll(counterSelector).forEach((counter) => {
    if (isAnimatedCounter(counter.textContent.trim())) counter.dataset.counter = "";
  });

  const journey = document.querySelector(".journey-line");
  if (journey && !journey.dataset.reveal) journey.dataset.reveal = "up";
}

function initializeReveals() {
  const observer = new IntersectionObserver(
    (entries, currentObserver) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add("is-visible");
        currentObserver.unobserve(entry.target);
      });
    },
    { threshold: 0.12, rootMargin: "0px 0px -7%" },
  );

  document.querySelectorAll("[data-reveal]").forEach((element) => observer.observe(element));
}

function initializeCounters() {
  const observer = new IntersectionObserver(
    (entries, currentObserver) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        animateCounter(entry.target);
        currentObserver.unobserve(entry.target);
      });
    },
    { threshold: 0.45 },
  );

  document.querySelectorAll("[data-counter]").forEach((counter) => observer.observe(counter));
}

function animateCounter(element) {
  const original = element.textContent.trim();
  const match = original.match(/^([+-]?)(\d*\.?\d+)(.*)$/);
  if (!match) return;

  const [, prefix, numericText, suffix] = match;
  const target = Number(numericText);
  const decimals = numericText.includes(".") ? numericText.split(".")[1].length : 0;
  const omitLeadingZero = numericText.startsWith(".");
  const duration = 900;
  const startedAt = performance.now();

  const render = (now) => {
    const progress = Math.min((now - startedAt) / duration, 1);
    const eased = 1 - Math.pow(1 - progress, 3);
    let value = (target * eased).toFixed(decimals);
    if (omitLeadingZero) value = value.replace(/^0/, "");
    element.textContent = `${prefix}${value}${suffix}`;
    if (progress < 1) requestAnimationFrame(render);
    else element.textContent = original;
  };

  requestAnimationFrame(render);
}

function isAnimatedCounter(value) {
  const match = value.match(/^([+-]?)(\d*\.?\d+)(.*)$/);
  if (!match) return false;
  const number = Number(match[2]);
  const suffix = match[3];
  return !(number >= 2020 && number <= 2030 && suffix === "");
}

function initializeFormFeedback() {
  formFeedbackController?.abort();
  formStatusObservers.forEach((observer) => observer.disconnect());
  formStatusObservers = [];
  formFeedbackController = new AbortController();
  const { signal } = formFeedbackController;
  const statusSelector = [
    ".form-message",
    ".sponsor-form-status",
    ".contact-form-status",
    ".download-status",
  ].join(",");

  const setStatusState = (status, state) => {
    status.dataset.state = state;
    status.classList.toggle("is-error", state === "error");
    status.setAttribute("role", state === "error" ? "alert" : "status");
  };

  document.addEventListener(
    "invalid",
    (event) => {
      const control = event.target;
      const form = control.closest?.("form");
      const status = form?.querySelector(statusSelector);
      control.setAttribute?.("aria-invalid", "true");
      control.classList?.remove("motion-invalid");
      void control.offsetWidth;
      control.classList?.add("motion-invalid");

      if (status && control.validationMessage) {
        status.dataset.nativeError = "true";
        status.textContent = control.validationMessage;
        setStatusState(status, "error");
      }
    },
    { capture: true, signal },
  );

  document.addEventListener("input", (event) => {
    const control = event.target;
    if (!control.matches?.("input, select, textarea")) return;
    control.classList.remove("motion-invalid");
    if (control.validity?.valid) control.removeAttribute("aria-invalid");

    const form = control.closest("form");
    const status = form?.querySelector(statusSelector);
    if (
      status?.dataset.nativeError
      && [...form.elements].every((element) => !element.validity || element.validity.valid)
    ) {
      status.textContent = "";
      status.removeAttribute("data-native-error");
      status.removeAttribute("data-state");
      status.classList.remove("is-error");
      status.setAttribute("role", "status");
    }
  }, { signal });

  window.addEventListener("site-form-complete", () => {
    requestAnimationFrame(() => {
      document.querySelectorAll(statusSelector).forEach((status) => {
        if (!status.textContent.trim()) return;
        setStatusState(status, "success");
        status.removeAttribute("data-native-error");
        const panel = status.closest("form, .download-panel");
        panel?.querySelectorAll("[aria-invalid='true']")
          .forEach((control) => control.removeAttribute("aria-invalid"));
        if (!panel) return;
        panel.classList.remove("motion-success");
        void panel.offsetWidth;
        panel.classList.add("motion-success");
      });
    });
  }, { signal });

  const statuses = document.querySelectorAll(statusSelector);
  const observer = new MutationObserver((entries) => {
    entries.forEach(({ target }) => {
      if (!target.textContent.trim()) {
        target.removeAttribute("data-state");
        target.classList.remove("is-error");
        return;
      }

      if (target.dataset.state !== "success") {
        setStatusState(target, "error");
        const form = target.closest("form");
        const invalidControl = form
          ? [...form.elements].find((control) => control.validity && !control.validity.valid)
          : null;
        invalidControl?.setAttribute("aria-invalid", "true");
      }

      const panel = target.closest("form, .download-panel");
      if (!panel || target.dataset.state !== "success") return;
      panel.classList.remove("motion-success");
      void panel.offsetWidth;
      panel.classList.add("motion-success");
    });
  });
  statuses.forEach((status) => observer.observe(status, { childList: true, characterData: true, subtree: true }));
  formStatusObservers.push(observer);
  document.addEventListener("livewire:navigating", () => {
    formFeedbackController?.abort();
    formStatusObservers.forEach((currentObserver) => currentObserver.disconnect());
    formStatusObservers = [];
  }, { once: true, signal });
}

function revealEverything() {
  document.querySelectorAll("[data-reveal]").forEach((element) => element.classList.add("is-visible"));
}
