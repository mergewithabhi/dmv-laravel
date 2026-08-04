let headerController;

export function initializeHeader() {
  headerController?.abort();
  headerController = new AbortController();

  const { signal } = headerController;
  const toggle = document.querySelector(".nav-toggle");
  const nav = document.querySelector(".primary-nav");
  const mobileNavigation = window.matchMedia("(max-width: 860px)");

  if (toggle && nav) {
    const isOpen = () => toggle.getAttribute("aria-expanded") === "true";
    const focusableElements = () => [
      toggle,
      ...nav.querySelectorAll(
        'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
      ),
    ].filter((element) => !element.hasAttribute("hidden"));

    const closeNavigation = (restoreFocus = false) => {
      const wasOpen = isOpen();
      toggle.setAttribute("aria-expanded", "false");
      toggle.setAttribute("aria-label", "Open navigation");
      nav.classList.remove("open");
      document.body.classList.remove("nav-open");

      if (mobileNavigation.matches) {
        nav.setAttribute("aria-hidden", "true");
        nav.setAttribute("inert", "");
      } else {
        nav.removeAttribute("aria-hidden");
        nav.removeAttribute("inert");
      }

      if (restoreFocus && wasOpen) toggle.focus();
    };

    const openNavigation = () => {
      if (!mobileNavigation.matches) return;

      toggle.setAttribute("aria-expanded", "true");
      toggle.setAttribute("aria-label", "Close navigation");
      nav.classList.add("open");
      nav.removeAttribute("aria-hidden");
      nav.removeAttribute("inert");
      document.body.classList.add("nav-open");

      requestAnimationFrame(() => nav.querySelector("a[href]")?.focus());
    };

    toggle.addEventListener("click", () => {
      if (isOpen()) closeNavigation();
      else openNavigation();
    }, { signal });

    nav.addEventListener("click", (event) => {
      if (event.target.closest("a")) closeNavigation();
    }, { signal });

    document.addEventListener("click", (event) => {
      if (!isOpen() || nav.contains(event.target) || toggle.contains(event.target)) return;
      closeNavigation();
    }, { signal });

    document.addEventListener("keydown", (event) => {
      if (!mobileNavigation.matches || !isOpen()) return;

      if (event.key === "Escape") {
        event.preventDefault();
        closeNavigation(true);
        return;
      }

      if (event.key !== "Tab") return;
      const focusable = focusableElements();
      if (!focusable.length) return;

      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (!focusable.includes(document.activeElement)) {
        event.preventDefault();
        (event.shiftKey ? last : first).focus();
      } else if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    }, { signal });

    const handleBreakpointChange = () => closeNavigation();
    mobileNavigation.addEventListener?.("change", handleBreakpointChange, { signal });
    closeNavigation();

    document.addEventListener("livewire:navigating", () => {
      closeNavigation();
    }, { once: true, signal });
  }

  initializeSkipLink(signal);
  initializeActiveLinks();
}

function initializeSkipLink(signal) {
  const skipLink = document.querySelector(".skip-link");
  const main = document.querySelector("#site-main");
  if (!skipLink || !main) return;

  main.setAttribute("tabindex", "-1");
  skipLink.addEventListener("click", () => {
    requestAnimationFrame(() => main.focus({ preventScroll: true }));
  }, { signal });
}

function initializeActiveLinks() {
  const allNavLinks = [...document.querySelectorAll(".primary-nav a")];
  const navLinks = [...document.querySelectorAll(".primary-nav a[href^='#']")];
  const currentPage = document.body.dataset.page;

  allNavLinks.forEach((link) => {
    const isActive = link.dataset.pageLink === currentPage;
    link.classList.toggle("active", isActive);
    if (isActive) link.setAttribute("aria-current", "page");
    else link.removeAttribute("aria-current");
  });

  const sections = navLinks
    .map((link) => document.querySelector(link.hash))
    .filter(Boolean);

  if (!sections.length || !("IntersectionObserver" in window)) return;

  const observer = new IntersectionObserver(
    (entries) => {
      const current = entries.find((entry) => entry.isIntersecting);
      if (!current) return;

      allNavLinks.forEach((link) => {
        const isActive = link.getAttribute("href") === `#${current.target.id}`;
        link.classList.toggle("active", isActive);
        if (isActive) link.setAttribute("aria-current", "location");
        else link.removeAttribute("aria-current");
      });
    },
    { rootMargin: "-25% 0px -65%", threshold: 0 },
  );

  sections.forEach((section) => observer.observe(section));
}
