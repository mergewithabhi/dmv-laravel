export function initializeMain() {
  initializeTemplateFallbacks();
  initializeCountdown();
  initializePartnerCarousels();
  initializeInstagramCarousels();
}

function initializeTemplateFallbacks() {
  document.querySelectorAll("a[data-safe-href]").forEach((link) => {
    const currentHref = link.getAttribute("href")?.trim();
    const fallbackHref = link.dataset.safeHref?.trim();
    if (!fallbackHref || (currentHref && currentHref !== "#")) return;

    link.setAttribute("href", fallbackHref);
    if (/^https?:\/\//i.test(fallbackHref)) {
      link.setAttribute("target", "_blank");
      link.setAttribute("rel", "noopener noreferrer");
    }
  });
}

export function initializePartnerCarousels() {
  document.querySelectorAll("[data-partner-carousel]:not([data-carousel-ready])").forEach((carousel) => {
    carousel.dataset.carouselReady = "true";

    const viewport = carousel.querySelector("[data-partner-viewport]");
    const track = carousel.querySelector("[data-partner-track]");
    const previous = carousel.querySelector("[data-partner-previous]");
    const next = carousel.querySelector("[data-partner-next]");
    const status = carousel.querySelector("[data-partner-status]");
    const slides = [...carousel.querySelectorAll(".partner-carousel-slide")];
    if (!viewport || !track || !previous || !next || !slides.length) return;

    slides.forEach((slide) => {
      const clone = slide.cloneNode(true);
      clone.dataset.partnerClone = "";
      clone.setAttribute("aria-hidden", "true");
      clone.removeAttribute("role");
      clone.removeAttribute("aria-label");
      clone.querySelectorAll("a, button").forEach((control) => control.setAttribute("tabindex", "-1"));
      track.append(clone);
    });

    const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");
    const configuredSpeed = Number.parseFloat(
      getComputedStyle(carousel).getPropertyValue("--partner-scroll-speed"),
    );
    const scrollSpeed = Number.isFinite(configuredSpeed) ? configuredSpeed : 30;
    let animationFrame;
    let previousFrameTime;
    let cycleWidth = 0;
    let isStatic = false;
    let paused = false;
    let statusTimer;

    const visibleSlides = () => {
      const value = Number.parseInt(
        getComputedStyle(carousel).getPropertyValue("--partner-columns"),
        10,
      );

      return Number.isFinite(value) ? Math.max(1, value) : 1;
    };

    const stepSize = () => {
      const gap = Number.parseFloat(getComputedStyle(track).columnGap) || 0;
      return (slides[0]?.getBoundingClientRect().width || viewport.clientWidth) + gap;
    };

    const normalizePosition = () => {
      if (cycleWidth <= 0) return;
      if (viewport.scrollLeft >= cycleWidth) {
        viewport.scrollLeft -= cycleWidth;
      } else if (viewport.scrollLeft < 0) {
        viewport.scrollLeft += cycleWidth;
      }
    };

    const statusMessage = () => {
      const visible = Math.min(visibleSlides(), slides.length);
      const index = cycleWidth > 0
        ? Math.round((viewport.scrollLeft % cycleWidth) / stepSize()) % slides.length
        : 0;
      const first = Math.min(index + 1, slides.length);
      const last = Math.min(first + visible - 1, slides.length);
      return `Showing partners ${first} through ${last} of ${slides.length}`;
    };

    const updateStatus = (announce = false) => {
      if (!status) return;

      const message = statusMessage();
      window.clearTimeout(statusTimer);
      if (!announce) {
        status.setAttribute("aria-live", "off");
        status.textContent = message;
        return;
      }

      status.setAttribute("aria-live", "polite");
      status.textContent = "";
      requestAnimationFrame(() => {
        status.textContent = message;
      });
      statusTimer = window.setTimeout(() => {
        status.setAttribute("aria-live", "off");
      }, 1200);
    };

    const updateLayout = () => {
      const currentIndex = cycleWidth > 0
        ? Math.round((viewport.scrollLeft % cycleWidth) / stepSize())
        : 0;
      cycleWidth = slides.length * stepSize();
      isStatic = slides.length <= visibleSlides();
      carousel.classList.toggle("is-static", isStatic);
      carousel.classList.toggle(
        "is-auto-scrolling",
        !isStatic && !reducedMotion.matches,
      );
      previous.disabled = isStatic;
      next.disabled = isStatic;
      viewport.scrollLeft = Math.min(currentIndex, slides.length - 1) * stepSize();
      updateStatus(false);
    };

    const moveBy = (direction) => {
      if (isStatic) return;
      if (direction < 0 && viewport.scrollLeft < stepSize() * .5) {
        viewport.scrollLeft += cycleWidth;
      }
      viewport.scrollBy({
        left: direction * stepSize(),
        behavior: reducedMotion.matches ? "auto" : "smooth",
      });
      window.setTimeout(
        () => updateStatus(true),
        reducedMotion.matches ? 0 : 420,
      );
    };

    const animate = (time) => {
      if (
        !reducedMotion.matches
        && !paused
        && !document.hidden
        && !isStatic
        && previousFrameTime !== undefined
      ) {
        const elapsed = Math.min(time - previousFrameTime, 50);
        // Increasing scrollLeft moves the logo track visually from right to left.
        viewport.scrollLeft += (scrollSpeed * elapsed) / 1000;
        normalizePosition();
      }
      previousFrameTime = time;
      animationFrame = window.requestAnimationFrame(animate);
    };

    previous.addEventListener("click", () => moveBy(-1));
    next.addEventListener("click", () => moveBy(1));
    viewport.addEventListener("scroll", () => {
      normalizePosition();
    }, { passive: true });
    carousel.addEventListener("pointerenter", () => {
      paused = true;
    });
    carousel.addEventListener("pointerleave", () => {
      paused = false;
    });
    carousel.addEventListener("focusin", () => {
      paused = true;
    });
    carousel.addEventListener("focusout", (event) => {
      if (carousel.contains(event.relatedTarget)) return;
      paused = false;
    });

    const resizeObserver = "ResizeObserver" in window
      ? new ResizeObserver(updateLayout)
      : null;
    resizeObserver?.observe(viewport);
    window.addEventListener("resize", updateLayout);
    reducedMotion.addEventListener?.("change", updateLayout);
    document.addEventListener("livewire:navigating", () => {
      window.cancelAnimationFrame(animationFrame);
      window.clearTimeout(statusTimer);
      resizeObserver?.disconnect();
      window.removeEventListener("resize", updateLayout);
    }, { once: true });

    updateLayout();
    animationFrame = window.requestAnimationFrame(animate);
  });
}

export function initializeInstagramCarousels() {
  document.querySelectorAll("[data-instagram-carousel]:not([data-carousel-ready])").forEach((carousel) => {
    carousel.dataset.carouselReady = "true";

    const viewport = carousel.querySelector("[data-instagram-viewport]");
    const track = carousel.querySelector("[data-instagram-track]");
    const previous = carousel.querySelector("[data-instagram-previous]");
    const next = carousel.querySelector("[data-instagram-next]");
    const status = carousel.querySelector("[data-instagram-status]");
    const slides = [...carousel.querySelectorAll(".instagram-carousel-slide")];
    if (!viewport || !track || !previous || !next || !slides.length) return;

    slides.forEach((slide) => {
      const clone = slide.cloneNode(true);
      clone.dataset.instagramClone = "";
      clone.setAttribute("aria-hidden", "true");
      clone.removeAttribute("role");
      clone.removeAttribute("aria-roledescription");
      clone.removeAttribute("aria-label");
      clone.setAttribute("tabindex", "-1");
      track.append(clone);
    });

    const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");
    const configuredSpeed = Number.parseFloat(
      getComputedStyle(carousel).getPropertyValue("--instagram-scroll-speed"),
    );
    const scrollSpeed = Number.isFinite(configuredSpeed) ? configuredSpeed : 24;
    let animationFrame;
    let previousFrameTime;
    let cycleWidth = 0;
    let isStatic = false;
    let paused = false;
    let manualPauseUntil = 0;
    let statusTimer;

    const visibleSlides = () => {
      const value = Number.parseInt(
        getComputedStyle(carousel).getPropertyValue("--instagram-columns"),
        10,
      );

      return Number.isFinite(value) ? Math.max(1, value) : 1;
    };

    const stepSize = () => {
      const gap = Number.parseFloat(getComputedStyle(track).columnGap) || 0;
      return (slides[0]?.getBoundingClientRect().width || viewport.clientWidth) + gap;
    };

    const normalizePosition = () => {
      if (cycleWidth <= 0) return;
      if (viewport.scrollLeft >= cycleWidth) {
        viewport.scrollLeft -= cycleWidth;
      } else if (viewport.scrollLeft < 0) {
        viewport.scrollLeft += cycleWidth;
      }
    };

    const updateStatus = (announce = false) => {
      if (!status) return;

      const visible = Math.min(visibleSlides(), slides.length);
      const index = cycleWidth > 0
        ? Math.round((viewport.scrollLeft % cycleWidth) / stepSize()) % slides.length
        : 0;
      const first = Math.min(index + 1, slides.length);
      const last = Math.min(first + visible - 1, slides.length);
      const message = `Showing Instagram posts ${first} through ${last} of ${slides.length}`;

      window.clearTimeout(statusTimer);
      status.setAttribute("aria-live", announce ? "polite" : "off");
      status.textContent = announce ? "" : message;
      if (announce) {
        requestAnimationFrame(() => {
          status.textContent = message;
        });
        statusTimer = window.setTimeout(() => {
          status.setAttribute("aria-live", "off");
        }, 1200);
      }
    };

    const updateLayout = () => {
      const currentIndex = cycleWidth > 0
        ? Math.round((viewport.scrollLeft % cycleWidth) / stepSize())
        : 0;
      cycleWidth = slides.length * stepSize();
      isStatic = slides.length <= visibleSlides();
      carousel.classList.toggle("is-static", isStatic);
      carousel.classList.toggle("is-auto-scrolling", !isStatic && !reducedMotion.matches);
      previous.disabled = isStatic;
      next.disabled = isStatic;
      viewport.scrollLeft = Math.min(currentIndex, slides.length - 1) * stepSize();
      updateStatus(false);
    };

    const moveBy = (direction) => {
      if (isStatic) return;
      if (direction < 0 && viewport.scrollLeft < stepSize() * .5) {
        viewport.scrollLeft += cycleWidth;
      }
      manualPauseUntil = performance.now() + 700;
      viewport.scrollBy({
        left: direction * stepSize(),
        behavior: reducedMotion.matches ? "auto" : "smooth",
      });
      window.setTimeout(() => updateStatus(true), reducedMotion.matches ? 0 : 420);
    };

    const animate = (time) => {
      if (
        !reducedMotion.matches
        && !paused
        && time >= manualPauseUntil
        && !document.hidden
        && !isStatic
        && previousFrameTime !== undefined
      ) {
        const elapsed = Math.min(time - previousFrameTime, 50);
        // Increasing scrollLeft moves the post track visually from right to left.
        viewport.scrollLeft += (scrollSpeed * elapsed) / 1000;
        normalizePosition();
      }
      previousFrameTime = time;
      animationFrame = window.requestAnimationFrame(animate);
    };

    previous.addEventListener("click", () => moveBy(-1));
    next.addEventListener("click", () => moveBy(1));
    viewport.addEventListener("scroll", normalizePosition, { passive: true });
    carousel.addEventListener("pointerenter", () => {
      paused = true;
    });
    carousel.addEventListener("pointerleave", () => {
      paused = false;
    });
    carousel.addEventListener("focusin", () => {
      paused = true;
    });
    carousel.addEventListener("focusout", (event) => {
      if (carousel.contains(event.relatedTarget)) return;
      paused = false;
    });

    const resizeObserver = "ResizeObserver" in window
      ? new ResizeObserver(updateLayout)
      : null;
    resizeObserver?.observe(viewport);
    window.addEventListener("resize", updateLayout);
    reducedMotion.addEventListener?.("change", updateLayout);
    document.addEventListener("livewire:navigating", () => {
      window.cancelAnimationFrame(animationFrame);
      window.clearTimeout(statusTimer);
      resizeObserver?.disconnect();
      window.removeEventListener("resize", updateLayout);
    }, { once: true });

    updateLayout();
    animationFrame = window.requestAnimationFrame(animate);
  });
}

function initializeCountdown() {
  document.querySelectorAll(".countdown, .schedule-countdown").forEach((countdown) => {
    const targetTime = new Date(countdown.dataset.gameDate).getTime();
    if (!Number.isFinite(targetTime)) return;

    const fields = {
      days: countdown.querySelector('[data-count="days"]'),
      hours: countdown.querySelector('[data-count="hours"]'),
      minutes: countdown.querySelector('[data-count="minutes"]'),
      seconds: countdown.querySelector('[data-count="seconds"]'),
    };

    const update = () => {
      const remaining = Math.max(0, targetTime - Date.now());
      const seconds = Math.floor(remaining / 1000);
      const values = {
        days: Math.floor(seconds / 86400),
        hours: Math.floor((seconds % 86400) / 3600),
        minutes: Math.floor((seconds % 3600) / 60),
        seconds: seconds % 60,
      };

      Object.entries(values).forEach(([key, value]) => {
        if (fields[key]) fields[key].textContent = String(value).padStart(2, "0");
      });
    };

    update();
    const timer = window.setInterval(update, 1000);
    document.addEventListener(
      "livewire:navigating",
      () => window.clearInterval(timer),
      { once: true },
    );
  });
}
