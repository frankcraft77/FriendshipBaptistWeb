/* Website editor — small conveniences, no dependencies. */

// Textareas grow to fit their content as the user types.
function autogrow(el) {
  el.style.height = 'auto';
  el.style.height = Math.min(el.scrollHeight + 4, 600) + 'px';
}
document.querySelectorAll('textarea').forEach(function (el) {
  autogrow(el);
  el.addEventListener('input', function () { autogrow(el); });
});

// Confirm step for the Undo button.
document.querySelectorAll('form[data-confirm]').forEach(function (form) {
  form.addEventListener('submit', function (ev) {
    if (!window.confirm(form.getAttribute('data-confirm'))) {
      ev.preventDefault();
    }
  });
});

// Gentle guard: warn before leaving the edit form with unsaved changes.
(function () {
  var form = document.getElementById('edit-form');
  if (!form) return;
  var dirty = false;
  form.addEventListener('input', function () { dirty = true; });
  form.addEventListener('submit', function () { dirty = false; });
  window.addEventListener('beforeunload', function (ev) {
    if (dirty) {
      ev.preventDefault();
      ev.returnValue = '';
    }
  });
})();
