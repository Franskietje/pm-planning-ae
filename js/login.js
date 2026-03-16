(function () {
  const form = document.getElementById('loginForm');
  const error = document.getElementById('error');

  if (!form) return;

  const apiBase = (window.APP_CONFIG && window.APP_CONFIG.API_BASE_URL) || '';

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    error.textContent = '';

    const data = new FormData(form);

    try {
      const res = await fetch(`${apiBase}/auth/login`, {
        method: 'POST',
        body: data,
        credentials: 'include'
      });

      const json = await res.json();

      if (!json.ok) {
        error.textContent = 'Login mislukt';
        return;
      }

      window.location.href = 'planning.html';
    } catch (err) {
      error.textContent = 'Server fout';
    }
  });
})();
