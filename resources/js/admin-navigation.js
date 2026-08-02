let navigationController;

export function initializeAdminNavigation() {
    navigationController?.abort();

    const sidebar = document.querySelector(".admin-sidebar");
    const toggle = document.querySelector(".admin-nav-toggle");
    const navigation = document.querySelector("#admin-navigation");
    if (!sidebar || !toggle || !navigation) return;

    navigationController = new AbortController();
    const { signal } = navigationController;
    sidebar.classList.add("nav-enhanced");

    const setOpen = (open) => {
        sidebar.classList.toggle("is-open", open);
        toggle.setAttribute("aria-expanded", String(open));
        toggle.setAttribute("aria-label", open ? "Close CMS navigation" : "Open CMS navigation");
    };

    toggle.addEventListener("click", () => {
        setOpen(toggle.getAttribute("aria-expanded") !== "true");
    }, { signal });

    navigation.addEventListener("click", (event) => {
        if (event.target.closest("a")) setOpen(false);
    }, { signal });

    document.addEventListener("click", (event) => {
        if (window.matchMedia("(max-width: 960px)").matches && !sidebar.contains(event.target)) {
            setOpen(false);
        }
    }, { signal });

    document.addEventListener("keydown", (event) => {
        if (event.key !== "Escape" || toggle.getAttribute("aria-expanded") !== "true") return;

        setOpen(false);
        toggle.focus();
    }, { signal });
}
