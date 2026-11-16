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

  const toolbar = document.querySelector("[data-admin-crud-toolbar]");
  const listForm = document.querySelector("[data-admin-list]");
  if (!toolbar || !listForm) {
    return;
  }

  const editBtn = toolbar.querySelector('[data-admin-crud="edit"]');
  const deleteBtn = toolbar.querySelector('[data-admin-crud="delete"]');
  const bulkRun = toolbar.querySelector("[data-admin-bulk-run]");
  const bulkPicker = toolbar.querySelector("[data-admin-bulk-picker]");
  const actionField = listForm.querySelector("[data-admin-bulk-action-field]");
  const reasonField = listForm.querySelector("[data-admin-bulk-reason-field]");

  const selectedBoxes = () =>
    Array.from(listForm.querySelectorAll("[data-admin-row-select]:checked"));

  const syncTopButtons = () => {
    const picked = selectedBoxes();
    const one = picked.length === 1 ? picked[0] : null;
    if (editBtn) {
      editBtn.disabled = !one;
      if (one) {
        editBtn.dataset.editHref = one.dataset.editHref || "";
      }
    }
    if (deleteBtn) {
      deleteBtn.disabled = picked.length === 0;
    }
  };

  listForm.addEventListener("change", (event) => {
    const target = event.target;
    if (!(target instanceof HTMLInputElement)) {
      return;
    }
    if (target.matches("[data-admin-select-all]")) {
      const on = target.checked;
      listForm.querySelectorAll("[data-admin-row-select]").forEach((box) => {
        box.checked = on;
      });
    }
    syncTopButtons();
  });

  if (editBtn) {
    editBtn.addEventListener("click", () => {
      const href = editBtn.dataset.editHref;
      if (href) {
        window.location.href = href;
      }
    });
  }

  const confirmReason = () => {
    const reason = window.prompt(
      document.documentElement.lang === "ar"
        ? "سبب التغيير (٣ أحرف على الأقل):"
        : "Reason for change (3+ chars):",
      "bulk"
    );
    if (reason === null) {
      return null;
    }
  if (reason.trim().length < 3) {
      window.alert(
        document.documentElement.lang === "ar" ? "السبب قصير جدًا." : "Reason is too short."
      );
      return null;
    }
    return reason.trim();
  };

  const submitBulk = (action) => {
    if (!actionField) {
      return;
    }
    const picked = selectedBoxes();
    if (picked.length === 0) {
      window.alert(
        document.documentElement.lang === "ar" ? "حدّد صفًا واحدًا على الأقل." : "Select at least one row."
      );
      return;
    }
    const reason = confirmReason();
    if (!reason) {
      return;
    }
    if (
      !window.confirm(
        document.documentElement.lang === "ar"
          ? "تأكيد الإجراء الجماعي؟"
          : "Confirm bulk action?"
      )
    ) {
      return;
    }
    actionField.value = action;
    if (reasonField) {
      reasonField.value = reason;
    }
    listForm.submit();
  };

  if (deleteBtn) {
    deleteBtn.addEventListener("click", () => {
      const action = bulkPicker && bulkPicker.value ? bulkPicker.value : "delete";
      const deleteOption = listForm.closest("main")?.querySelector('option[value="delete"]');
      const useDelete = deleteOption ? "delete" : action;
      if (bulkPicker && !bulkPicker.value && deleteOption) {
        bulkPicker.value = "delete";
      }
      submitBulk(useDelete || "delete");
    });
  }

  if (bulkRun && bulkPicker) {
    bulkRun.addEventListener("click", () => {
      const action = bulkPicker.value;
      if (!action) {
        window.alert(
          document.documentElement.lang === "ar" ? "اختر إجراءً جماعيًا." : "Pick a bulk action."
        );
        return;
      }
      submitBulk(action);
    });
  }

  listForm.addEventListener("click", (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) {
      return;
    }
    const del = target.closest("[data-admin-row-delete]");
    if (!del) {
      return;
    }
    const rowId = del.dataset.rowId;
    const box = listForm.querySelector(`[data-admin-row-select][value="${rowId}"]`);
    if (box) {
      listForm.querySelectorAll("[data-admin-row-select]").forEach((node) => {
        node.checked = false;
      });
      box.checked = true;
      syncTopButtons();
    }
    const action = bulkPicker && listForm.querySelector('option[value="delete"]') ? "delete" : bulkPicker?.value;
    if (!action) {
      window.alert(
        document.documentElement.lang === "ar"
          ? "الحذف غير متاح لهذه القائمة."
          : "Delete is not available for this list."
      );
      return;
    }
    if (bulkPicker) {
      bulkPicker.value = action;
    }
    submitBulk(action);
  });

  syncTopButtons();
})();