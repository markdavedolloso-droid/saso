// Provides shared navigation, table search, modal, and password-toggle behavior.
let pendingConfirmation = null;

document.addEventListener("DOMContentLoaded", () => {
  const menu = document.getElementById("menuBtn");
  const sidebar = document.getElementById("sidebar");
  if (menu) {
    menu.addEventListener("click", () => sidebar.classList.toggle("open"));
  }

  const search = document.getElementById("tableSearch");
  const table = document.getElementById("dataTable");
  if (search && table) {
    search.addEventListener("input", () => {
      const query = search.value.toLowerCase();
      table.querySelectorAll("tbody tr").forEach((row) => {
        row.style.display = row.innerText.toLowerCase().includes(query) ? "" : "none";
      });
    });
  }

});

function openModal(id) {
  const modal = document.getElementById(id);
  if (modal) modal.classList.add("show");
}

function closeModal(id) {
  const modal = document.getElementById(id);
  if (modal) modal.classList.remove("show");
  const previewFrame = modal?.querySelector("[data-modal-preview]");
  if (previewFrame) previewFrame.removeAttribute("src");
  if (id === "confirmationModal") pendingConfirmation = null;
}

document.addEventListener("click", (event) => {
  const cancelButton = event.target.closest("[data-confirm-cancel]");
  if (cancelButton) {
    closeModal("confirmationModal");
    return;
  }

  if (event.target.closest("#confirmActionButton")) {
    const action = pendingConfirmation;
    closeModal("confirmationModal");
    if (action) action();
    return;
  }

  const navigationLink = event.target.closest("a[data-confirm-navigation]");
  if (navigationLink && event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey) {
    event.preventDefault();
    requestConfirmation(navigationLink.dataset.confirmation, () => {
      window.location.href = navigationLink.href;
    });
    return;
  }

  if (event.target.classList.contains("modal")) {
    if (event.target.id === "confirmationModal") {
      closeModal("confirmationModal");
    } else {
      closeModal(event.target.id);
    }
  }
});

document.addEventListener("submit", (event) => {
  const form = event.target;
  if (!(form instanceof HTMLFormElement)) return;
  if (form.dataset.confirmed === "true") {
    delete form.dataset.confirmed;
    return;
  }

  let message = form.dataset.confirmation;
  if (form.dataset.confirmCreate) {
    const action = form.querySelector('[name="action"]')?.value || "create";
    if (action !== "create") return;
    message = form.dataset.confirmCreate;
  }
  if (!message) return;

  event.preventDefault();
  const submitter = event.submitter;
  requestConfirmation(message, () => {
    form.dataset.confirmed = "true";
    if (submitter) {
      form.requestSubmit(submitter);
    } else {
      form.requestSubmit();
    }
  });
});

function requestConfirmation(message, action) {
  const confirmationMessage = document.getElementById("confirmationMessage");
  const confirmButton = document.getElementById("confirmActionButton");
  if (!confirmationMessage || !confirmButton) return;
  pendingConfirmation = action;
  confirmationMessage.textContent = message;
  openModal("confirmationModal");
  confirmButton.focus();
}

document.addEventListener("click", (event) => {
  const toggle = event.target.closest(".password-toggle, .pw-toggle-btn");
  if (!toggle) return;
  const input = document.getElementById(toggle.dataset.target);
  if (!input) return;
  const showing = input.type === "text";
  input.type = showing ? "password" : "text";
  toggle.setAttribute("aria-pressed", String(!showing));
  toggle.setAttribute("aria-label", showing ? "Show password" : "Hide password");
  input.focus({ preventScroll: true });
  const val = input.value;
  input.setSelectionRange(val.length, val.length);
});
