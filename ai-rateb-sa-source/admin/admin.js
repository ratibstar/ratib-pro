(() => {
  const navCheck = document.getElementById("nav-check");
  if (navCheck) {
    document.querySelectorAll(".side a").forEach((link) => {
      link.addEventListener("click", () => {
        navCheck.checked = false;
      });
    });
  }
  document.querySelectorAll(".chart .bar").forEach((bar) => {
    bar.addEventListener("click", () => {
      const on = bar.classList.contains("is-tip");
      document.querySelectorAll(".chart .bar.is-tip").forEach((node) => node.classList.remove("is-tip"));
      if (!on) {
        bar.classList.add("is-tip");
      }
    });
  });
})();