// Apply the saved theme as soon as this shared script loads.
(function () {
  let saved = 'dark';
  try {
    saved = localStorage.getItem('studysys_theme') === 'light' ? 'light' : 'dark';
  } catch (e) {}
  document.documentElement.dataset.theme = saved;
})();

// Ripple effect on nav links and app buttons.
document.addEventListener('click', function (e) {
  const target = e.target.closest ? e.target.closest('.sidebar nav a, .btn') : null;
  if (!target || target.id === 'themeToggleBtn') return;

  const rect = target.getBoundingClientRect();
  const ripple = document.createElement('span');
  const size = Math.max(rect.width, rect.height);

  ripple.className = 'ripple';
  ripple.style.width = ripple.style.height = size + 'px';
  ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
  ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';

  target.appendChild(ripple);
  setTimeout(() => ripple.remove(), 500);
});

// Show a loading spinner on the submit button when a form is submitted
document.querySelectorAll('form').forEach(function (form) {
  form.addEventListener('submit', function () {
    const btn = form.querySelector('.btn[type="submit"]');
    if (!btn || btn.classList.contains('loading')) return;

    btn.classList.add('loading');
    btn.disabled = true;
  });
});

// ---------- Dark / Light theme ----------
(function () {
  const btn = document.getElementById('themeToggleBtn');
  const root = document.documentElement;

  window.updateThemeLabel = function () {
    const isLight = root.dataset.theme === 'light';
    const icon = document.getElementById('themeIcon');
    const label = document.getElementById('themeLabel');
    if (icon) icon.textContent = isLight ? '☀️' : '🌙';
    if (label) label.textContent = isLight ? 'Light Mode' : 'Dark Mode';
  };

  window.toggleTheme = function () {
    const theme = root.dataset.theme === 'light' ? 'dark' : 'light';
    root.dataset.theme = theme;
    try { localStorage.setItem('studysys_theme', theme); } catch (e) {}
    window.updateThemeLabel();
  };

  if (btn) window.updateThemeLabel();
})();
