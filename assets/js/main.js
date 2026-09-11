// Ripple effect on any nav link or button click
document.addEventListener('click', function (e) {
  const target = e.target.closest('.sidebar nav a, .btn');
  if (!target) return;

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
  const saved = localStorage.getItem('studysys_theme') || 'dark';
  if (saved === 'light') document.documentElement.setAttribute('data-theme', 'light');

  const btn = document.getElementById('themeToggleBtn');
  if (!btn) return; // login/signup pages don't have the sidebar toggle

  function updateLabel() {
    const isLight = document.documentElement.getAttribute('data-theme') === 'light';
    document.getElementById('themeIcon').textContent = isLight ? '☀️' : '🌙';
    document.getElementById('themeLabel').textContent = isLight ? 'Light Mode' : 'Dark Mode';
  }
  updateLabel();

  btn.addEventListener('click', function () {
    const isLight = document.documentElement.getAttribute('data-theme') === 'light';
    if (isLight) {
      document.documentElement.removeAttribute('data-theme');
      localStorage.setItem('studysys_theme', 'dark');
    } else {
      document.documentElement.setAttribute('data-theme', 'light');
      localStorage.setItem('studysys_theme', 'light');
    }
    updateLabel();
  });
})();
