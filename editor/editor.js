/* Website editor — rich-text fields and small conveniences. No dependencies. */

// Prove to the server that this script actually ran: without this marker a
// save is refused with a "please refresh" message instead of silently
// discarding edits (which is what happened when an old cached copy of this
// file was still in play).
document.querySelectorAll('input[name="js"]').forEach(function (input) {
  input.value = '1';
});

// Keep every rich-text box's hidden input up to date continuously, so the
// posted form always carries the current content even if something
// interferes with the submit event.
function syncRte(rte) {
  var input = document.querySelector('input[data-rte-for="' + rte.id + '"]');
  if (input) input.value = rte.innerHTML;
}
document.querySelectorAll('.rte').forEach(function (rte) {
  syncRte(rte); // initial value = the unedited content
  rte.addEventListener('input', function () { syncRte(rte); });
  rte.addEventListener('blur', function () { syncRte(rte); });
});

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
      } else if (cmd === 'formatH2') {
        document.execCommand('formatBlock', false, '<h2>');
      } else if (cmd === 'formatP') {
        document.execCommand('formatBlock', false, '<p>');
      } else {
        document.execCommand(cmd, false, null);
      }
    });
  });
});

// Locked chips ([button], [icon], …) stand in for parts of the page that
// can't be edited. Guard against accidentally deleting one with Backspace/
// Delete right next to it. (If one is removed anyway — e.g. deleting a
// selection around it — the server puts the real element back on save.)
document.querySelectorAll('.rte').forEach(function (rte) {
  rte.addEventListener('keydown', function (ev) {
    if (ev.key !== 'Backspace' && ev.key !== 'Delete') return;
    var sel = window.getSelection();
    if (!sel || !sel.isCollapsed || sel.rangeCount === 0) return;
    var range = sel.getRangeAt(0);
    var node = range.startContainer;
    var offset = range.startOffset;
    var neighbor = null;
    if (node.nodeType === Node.TEXT_NODE) {
      if (ev.key === 'Backspace' && offset === 0) neighbor = node.previousSibling;
      if (ev.key === 'Delete' && offset === node.textContent.length) neighbor = node.nextSibling;
    } else if (node.childNodes.length) {
      neighbor = ev.key === 'Backspace' ? node.childNodes[offset - 1] : node.childNodes[offset];
    }
    if (neighbor && neighbor.nodeType === Node.ELEMENT_NODE && neighbor.classList.contains('edit-locked')) {
      ev.preventDefault();
    }
  });
});

// Copy every rich-text box into its hidden input once more at submit
// (belt and braces — covers toolbar-only changes with no input event).
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
