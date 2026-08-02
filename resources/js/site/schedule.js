import { applicationUrl } from "./url.js";

const fallbackCalendarEvents = {
  "2026-5": {
    7: "away",
    11: "home",
    14: "away",
    18: "home",
    21: "away",
    25: "home",
    28: "home",
  },
  "2026-6": { 2: "home", 5: "away", 9: "away" },
  "2026-7": { 22: "home" },
};

const monthFormatter = new Intl.DateTimeFormat("en-US", {
  month: "long",
  year: "numeric",
});
const fullDateFormatter = new Intl.DateTimeFormat("en-US", {
  weekday: "long",
  month: "long",
  day: "numeric",
  year: "numeric",
});

export function initializeSchedule() {
  if (document.body.dataset.page !== "schedule") return;

  synchronizeNextGameDate();
  const showCalendarMonth = initializeCalendar();
  initializeFilters(showCalendarMonth);
  initializeDownload();
}

function parseCalendarDate(value) {
  if (typeof value !== "string") return null;
  const match = value.match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (!match) return null;

  const date = new Date(
    Number.parseInt(match[1], 10),
    Number.parseInt(match[2], 10) - 1,
    Number.parseInt(match[3], 10),
  );
  return Number.isNaN(date.getTime()) ? null : date;
}

function synchronizeNextGameDate() {
  const countdown = document.querySelector(".schedule-countdown[data-game-date]");
  const dateOutput = document.querySelector(".schedule-game-meta dl > div:first-child dd");
  const gameDate = parseCalendarDate(countdown?.dataset.gameDate);
  if (!dateOutput || !gameDate) return;

  dateOutput.textContent = new Intl.DateTimeFormat("en-US", {
    month: "long",
    day: "numeric",
    year: "numeric",
  }).format(gameDate);
}

function initializeCalendar() {
  const grid = document.querySelector("[data-calendar-grid]");
  const label = document.querySelector("[data-calendar-label]");
  const previous = document.querySelector(".calendar-prev");
  const next = document.querySelector(".calendar-next");
  if (!grid || !label || !previous || !next) return () => {};

  const siteData = window.DMVCms || {};
  const selectedDate = parseCalendarDate(siteData.selectedDate || "2026-08-22");
  const initialDate = parseCalendarDate(siteData.calendarStart)
    || selectedDate
    || new Date();
  let year = initialDate.getFullYear();
  let month = initialDate.getMonth();
  let calendarTimer;
  const panel = grid.closest(".schedule-calendar");

  const render = () => {
    const displayedMonth = new Date(year, month, 1);
    const monthName = monthFormatter.format(displayedMonth);
    const firstWeekday = displayedMonth.getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const daysInPreviousMonth = new Date(year, month, 0).getDate();
    const calendarEvents = siteData.calendarEvents || fallbackCalendarEvents;
    const events = calendarEvents[`${year}-${month}`] || {};

    label.textContent = monthName;
    grid.setAttribute("aria-label", `${monthName} schedule calendar`);
    previous.setAttribute(
      "aria-label",
      `Show ${monthFormatter.format(new Date(year, month - 1, 1))}`,
    );
    next.setAttribute(
      "aria-label",
      `Show ${monthFormatter.format(new Date(year, month + 1, 1))}`,
    );
    grid.replaceChildren();

    for (let index = 0; index < 42; index += 1) {
      const cell = document.createElement("span");
      cell.className = "calendar-day";
      cell.setAttribute("role", "gridcell");

      let day;
      let isCurrentMonth = true;
      if (index < firstWeekday) {
        day = daysInPreviousMonth - firstWeekday + index + 1;
        isCurrentMonth = false;
      } else if (index >= firstWeekday + daysInMonth) {
        day = index - firstWeekday - daysInMonth + 1;
        isCurrentMonth = false;
      } else {
        day = index - firstWeekday + 1;
      }

      cell.textContent = String(day);
      if (!isCurrentMonth) {
        cell.classList.add("muted");
        cell.setAttribute("aria-hidden", "true");
        grid.append(cell);
        continue;
      }

      const currentDate = new Date(year, month, day);
      const currentDateKey = [
        year,
        String(month + 1).padStart(2, "0"),
        String(day).padStart(2, "0"),
      ].join("-");
      const eventType = events[day];
      const eventLabel = {
        home: "home game",
        away: "away game",
        event: "team event",
      }[eventType];

      cell.setAttribute(
        "aria-label",
        `${fullDateFormatter.format(currentDate)}${eventLabel ? `, ${eventLabel}` : ""}`,
      );

      if (currentDateKey === siteData.selectedDate || (
        !siteData.selectedDate
        && selectedDate
        && currentDateKey === [
          selectedDate.getFullYear(),
          String(selectedDate.getMonth() + 1).padStart(2, "0"),
          String(selectedDate.getDate()).padStart(2, "0"),
        ].join("-")
      )) {
        cell.classList.add("selected");
        cell.setAttribute("aria-current", "date");
      }

      if (eventType) {
        const marker = document.createElement("i");
        marker.className = eventType;
        marker.setAttribute("aria-hidden", "true");
        cell.append(marker);
      }

      grid.append(cell);
    }
  };

  const commitMonth = (nextYear, nextMonth) => {
    year = nextYear;
    month = nextMonth;
    render();
    requestAnimationFrame(() => panel?.classList.remove("is-updating"));
  };

  const showMonth = (monthValue) => {
    const match = monthValue?.match(/^(\d{4})-(\d{2})$/);
    if (!match) return;
    commitMonth(
      Number.parseInt(match[1], 10),
      Number.parseInt(match[2], 10) - 1,
    );
  };

  const changeMonth = (direction) => {
    const target = new Date(year, month + direction, 1);
    const commit = () => commitMonth(target.getFullYear(), target.getMonth());

    window.clearTimeout(calendarTimer);
    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
      commit();
      return;
    }
    panel?.classList.add("is-updating");
    calendarTimer = window.setTimeout(commit, 140);
  };

  previous.addEventListener("click", () => changeMonth(-1));
  next.addEventListener("click", () => changeMonth(1));
  document.addEventListener(
    "livewire:navigating",
    () => window.clearTimeout(calendarTimer),
    { once: true },
  );

  render();
  return showMonth;
}

function initializeFilters(showCalendarMonth) {
  const buttons = [...document.querySelectorAll("[data-schedule-filter]")];
  const rows = [...document.querySelectorAll(".season-table tbody tr")];
  const monthSelect = document.querySelector("[data-schedule-month]");
  const reset = document.querySelector("[data-schedule-reset]");
  const tableWrap = document.querySelector(".season-table-wrap");
  const panel = tableWrap?.closest(".season-schedule");
  let filterTimer;
  let locationFilter = "all";
  let monthFilter = "all";

  if (!buttons.length || !monthSelect || !tableWrap) return;

  if (!rows.length) {
    monthSelect.replaceChildren();
    const emptyOption = document.createElement("option");
    emptyOption.value = "all";
    emptyOption.textContent = "No scheduled games";
    monthSelect.append(emptyOption);
    monthSelect.disabled = true;
    reset?.setAttribute("disabled", "");
    return;
  }

  rows.forEach((row) => {
    const date = parseCalendarDate(row.dataset.gameDate);
    const dateCell = row.querySelector("td:first-child");
    if (!date || !dateCell) return;
    dateCell.textContent = new Intl.DateTimeFormat("en-US", {
      weekday: "short",
      month: "short",
      day: "2-digit",
      year: "numeric",
    }).format(date);
  });

  const availableMonths = [...new Set(
    rows
      .map((row) => row.dataset.gameDate?.slice(0, 7))
      .filter((value) => /^\d{4}-\d{2}$/.test(value)),
  )].sort();

  monthSelect.replaceChildren();
  const allMonths = document.createElement("option");
  allMonths.value = "all";
  allMonths.textContent = "All months";
  monthSelect.append(allMonths);
  availableMonths.forEach((value) => {
    const [optionYear, optionMonth] = value.split("-").map(Number);
    const option = document.createElement("option");
    option.value = value;
    option.textContent = monthFormatter.format(new Date(optionYear, optionMonth - 1, 1));
    monthSelect.append(option);
  });

  const resultsStatus = document.createElement("p");
  resultsStatus.className = "sr-only";
  resultsStatus.setAttribute("aria-live", "polite");
  resultsStatus.setAttribute("aria-atomic", "true");
  panel?.append(resultsStatus);

  const applyFilter = (announce = true) => {
    const commit = () => {
      buttons.forEach((button) => {
        const active = button.dataset.scheduleFilter === locationFilter;
        button.classList.toggle("active", active);
        button.setAttribute("aria-pressed", String(active));
      });

      let visibleRows = 0;
      rows.forEach((row) => {
        const matchesLocation = locationFilter === "all"
          || row.dataset.location === locationFilter;
        const matchesMonth = monthFilter === "all"
          || row.dataset.gameDate?.startsWith(monthFilter);
        row.hidden = !(matchesLocation && matchesMonth);
        if (!row.hidden) visibleRows += 1;
      });

      if (announce) {
        resultsStatus.textContent = `${visibleRows} game${visibleRows === 1 ? "" : "s"} shown.`;
      }
      requestAnimationFrame(() => tableWrap.classList.remove("is-filtering"));
    };

    window.clearTimeout(filterTimer);
    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
      commit();
      return;
    }
    tableWrap.classList.add("is-filtering");
    filterTimer = window.setTimeout(commit, 140);
  };

  buttons.forEach((button) => {
    button.addEventListener("click", () => {
      locationFilter = button.dataset.scheduleFilter;
      applyFilter();
    });
  });

  monthSelect.addEventListener("change", () => {
    monthFilter = monthSelect.value;
    if (monthFilter !== "all") showCalendarMonth(monthFilter);
    applyFilter();
  });

  reset?.addEventListener("click", () => {
    locationFilter = "all";
    monthFilter = "all";
    monthSelect.value = "all";
    applyFilter();
  });

  document.addEventListener(
    "livewire:navigating",
    () => window.clearTimeout(filterTimer),
    { once: true },
  );
  applyFilter(false);
}

function initializeDownload() {
  const button = document.querySelector("[data-download-schedule]");
  if (!button) return;

  button.addEventListener("click", () => {
    window.location.assign(
      window.DMVCms?.calendarDownload || applicationUrl("schedule/calendar.ics"),
    );
  });
}
