// Provides shared navigation, table search, modal, and password-toggle behavior.
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
}

document.addEventListener("click", (event) => {
  if (event.target.classList.contains("modal")) {
    event.target.classList.remove("show");
  }
});

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
