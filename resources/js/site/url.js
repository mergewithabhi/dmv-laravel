export function applicationUrl(path = "") {
  const root = document.body.dataset.appUrl || window.location.origin;
  const normalizedRoot = root.replace(/\/+$/, "");
  const normalizedPath = String(path).replace(/^\/+/, "");

  return normalizedPath ? `${normalizedRoot}/${normalizedPath}` : normalizedRoot;
}

export function normalizeInternalLinks() {
  document.querySelectorAll('a[href^="/"]:not([href^="//"])').forEach((link) => {
    const href = link.getAttribute("href");
    if (!href) return;

    link.setAttribute("href", applicationUrl(href));
  });
}
