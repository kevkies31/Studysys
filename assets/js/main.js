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
