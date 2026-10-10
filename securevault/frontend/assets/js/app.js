'use strict';
document.querySelectorAll('[data-toggle-password]').forEach(button => {
  button.addEventListener('click', () => {
    const input = document.getElementById(button.dataset.togglePassword);
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    button.textContent = show ? 'Hide' : 'Show';
    button.setAttribute('aria-pressed', String(show));
  });
});
const description = document.getElementById('description');
if (description) description.addEventListener('input', () => {
  document.getElementById('description-count').textContent = Array.from(description.value).length;
});
