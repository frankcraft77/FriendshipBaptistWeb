/* Website editor — rich-text fields and small conveniences. No dependencies. */

// ── Rich text areas ──
// Each .rte is a contenteditable box; its HTML is copied into a hidden input
// when the form is submitted. Toolbar buttons use the browser's built-in
// editing commands (bold, italic, lists, links).

document.querySelectorAll('.rte-toolbar').forEach(function (bar) {
  var rte = document.getElementById(bar.getAttribute('data-for'));
  if (!rte) return;
  bar.querySelectorAll('button[data-cmd]').forEach(function (btn) {
    btn.addEventListener('mousedown', function (ev) {
      ev.preventDefault(); // keep the text selection inside the box
    });
    btn.addEventListener('click', function () {
      var cmd = btn.getAttribute('data-cmd');
      rte.focus();
      if (cmd === 'createLink') {
        var url = window.prompt('Type the web address for the link (e.g. https://example.org):');
        if (url) document.execCommand('createLink', false, url);
      } else {
        document.execCommand(cmd, false, null);
      }
    });
  });
});

// Copy every rich-text box into its hidden input on submit.
var editForm = document.getElementById('edit-form');
if (editForm) {
  editForm.addEventListener('submit', function () {
    editForm.querySelectorAll('input[data-rte-for]').forEach(function (input) {
      var rte = document.getElementById(input.getAttribute('data-rte-for'));
      if (rte) input.value = rte.innerHTML;
    });
  });
}

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
  if (!editForm) return;
  var dirty = false;
  editForm.addEventListener('input', function () { dirty = true; });
  editForm.addEventListener('submit', function () { dirty = false; });
  window.addEventListener('beforeunload', function (ev) {
    if (dirty) {
      ev.preventDefault();
      ev.returnValue = '';
    }
  });
})();
