(function () {
  function setLoadingState(button) {
    if (!button || button.dataset.loading === "1") {
      return;
    }
    button.dataset.loading = "1";
    button.dataset.originalHtml = button.innerHTML;
    button.classList.add("is-loading");
    button.innerHTML = '<span class="action-btn-spinner" aria-hidden="true"></span>Processing...';
    window.setTimeout(function () {
      button.classList.remove("is-loading");
      button.innerHTML = button.dataset.originalHtml || button.textContent || "";
      button.dataset.loading = "0";
    }, 1600);
  }

  document.querySelectorAll("[data-loading-btn]").forEach(function (button) {
    button.addEventListener("click", function () {
      setLoadingState(button);
    });
  });
})();
