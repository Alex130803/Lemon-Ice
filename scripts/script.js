(function () {
  // Footer year
  const y = document.getElementById("year");
  if (y) y.textContent = new Date().getFullYear();

  // Smooth scroll for internal links
  document.querySelectorAll('[data-scroll]').forEach((el) => {
    el.addEventListener("click", (e) => {
      const href = el.getAttribute("href");
      if (!href || !href.startsWith("#")) return;

      const target = document.querySelector(href);
      if (!target) return;

      e.preventDefault();
      target.scrollIntoView({ behavior: "smooth", block: "start" });
      history.replaceState(null, "", href);
    });
  });

  // FAQ accordion (single open at a time)
  const acc = document.querySelector("[data-accordion]");
  if (acc) {
    acc.querySelectorAll(".acc-item").forEach((item) => {
      const btn = item.querySelector(".acc-btn");
      if (!btn) return;

      btn.addEventListener("click", () => {
        // close others
        acc.querySelectorAll(".acc-item.is-open").forEach((openItem) => {
          if (openItem !== item) {
            openItem.classList.remove("is-open");
            const b = openItem.querySelector(".acc-btn");
            if (b) b.setAttribute("aria-expanded", "false");
          }
        });

        const isOpen = item.classList.toggle("is-open");
        btn.setAttribute("aria-expanded", String(isOpen));
      });
    });
  }

  // Buy buttons placeholder behavior
  document.querySelectorAll("[data-buy]").forEach((btn) => {
    btn.addEventListener("click", () => {
      const pack = btn.closest("[data-pack]")?.getAttribute("data-pack");
      // Replace this with your real checkout link or cart logic
      alert(`Selected ${pack} Pack. Connect this button to your checkout.`);
    });
  });
})();
