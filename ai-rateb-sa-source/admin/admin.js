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

  /** Row / toolbar "Delete" must not fall back to disable on customer lists. */
  const resolveDeleteAction = (listForm, bulkPicker, permanentOnly = false) => {
    if (bulkPicker && bulkPicker.value) {
      return bulkPicker.value;
    }
    const pick = (value) => listForm.querySelector(`option[value="${value}"]`);
    if (pick("delete")) {
      return "delete";
    }
    if (permanentOnly) {
      return "";
    }
    if (pick("disable")) {
      return "disable";
    }
    if (pick("deactivate")) {
      return "deactivate";
    }
    return "";
  };

  const bindCrudList = (toolbar, listForm) => {
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
        const href = one ? one.dataset.editHref || "" : "";
        if (href) {
          editBtn.disabled = false;
          editBtn.dataset.editHref = href;
        } else {
          editBtn.disabled = true;
          delete editBtn.dataset.editHref;
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
      const permanent = action === "delete" && listForm.querySelector('option[value="delete"]');
      if (permanent) {
        const okPermanent = window.confirm(
          document.documentElement.lang === "ar"
            ? "حذف نهائي — يُزال الحساب وجميع بياناته ولا يمكن استرجاعه. متأكد؟"
            : "Permanent delete — the account and its data will be removed. Continue?"
        );
        if (!okPermanent) {
          return;
        }
      } else if (
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
        const action = resolveDeleteAction(listForm, bulkPicker, true);
        if (!action) {
          window.alert(
            document.documentElement.lang === "ar"
              ? "الحذف النهائي متاح للمالك فقط أو غير مفعّل لهذه القائمة."
              : "Permanent delete is owner-only or not available here."
          );
          return;
        }
        if (bulkPicker && !bulkPicker.value) {
          bulkPicker.value = action;
        }
        submitBulk(action);
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
      event.preventDefault();
      const rowId = del.dataset.rowId || "";
      const esc =
        typeof CSS !== "undefined" && typeof CSS.escape === "function"
          ? CSS.escape(rowId)
          : rowId.replace(/\\/g, "\\\\").replace(/"/g, '\\"');
      const box = listForm.querySelector(`[data-admin-row-select][value="${esc}"]`);
      if (box instanceof HTMLInputElement) {
        listForm.querySelectorAll("[data-admin-row-select]").forEach((node) => {
          node.checked = false;
        });
        box.checked = true;
        syncTopButtons();
      }
      const action = resolveDeleteAction(listForm, bulkPicker, true);
      if (!action) {
        window.alert(
          document.documentElement.lang === "ar"
            ? "الحذف النهائي متاح للمالك فقط أو غير مفعّل لهذه القائمة."
            : "Permanent delete is owner-only or not available here."
        );
        return;
      }
      if (bulkPicker && !bulkPicker.value) {
        bulkPicker.value = action;
      }
      submitBulk(action);
    });

    syncTopButtons();
  };

  document.querySelectorAll("form[data-admin-list]").forEach((listForm) => {
    const toolbar =
      listForm.querySelector("[data-admin-crud-toolbar]") ||
      listForm.closest("main")?.querySelector("[data-admin-crud-toolbar]");
    if (toolbar instanceof HTMLElement) {
      bindCrudList(toolbar, listForm);
    }
  });
})();