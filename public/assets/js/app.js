/* =========================================================
   UniSync — front-end behaviour (vanilla ES6, no frameworks)
   ========================================================= */
(function () {
  'use strict';

  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const meta = (name) => (document.querySelector(`meta[name="${name}"]`) || {}).content || '';

  // ---------- AJAX helper (sends CSRF token in a header) ----------
  window.api = async function (path, data = {}) {
    const res = await fetch(meta('base-url') + '/' + path, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': meta('csrf-token') },
      body: JSON.stringify(data),
      credentials: 'same-origin',
    });
    let json = {};
    try { json = await res.json(); } catch (e) { /* not json */ }
    if (!res.ok || json.ok === false) throw new Error(json.error || 'Request failed (' + res.status + ')');
    return json;
  };

  // ---------- Toast ----------
  window.toast = function (message, type = 'success') {
    let wrap = $('#toast-wrap');
    if (!wrap) {
      wrap = document.createElement('div');
      wrap.id = 'toast-wrap';
      wrap.style.cssText = 'position:fixed;right:16px;bottom:16px;z-index:100;display:grid;gap:8px;max-width:calc(100vw - 32px)';
      document.body.appendChild(wrap);
    }
    const el = document.createElement('div');
    el.className = 'alert alert-' + type;
    el.style.cssText = 'margin:0;box-shadow:0 10px 30px rgba(0,0,0,.15);min-width:240px';
    el.textContent = message;
    wrap.appendChild(el);
    setTimeout(() => { el.style.transition = 'opacity .3s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 300); }, 2600);
  };

  // ---------- Theme ----------
  $$('[data-toggle-theme]').forEach((btn) => btn.addEventListener('click', () => {
    const root = document.documentElement;
    const isDark = root.dataset.theme === 'dark' ||
      (!root.dataset.theme && window.matchMedia('(prefers-color-scheme: dark)').matches && false);
    root.dataset.theme = isDark ? 'light' : 'dark';
    try { localStorage.setItem('unisync-theme', root.dataset.theme); } catch (e) {}
    document.dispatchEvent(new CustomEvent('themechange'));
  }));

  // ---------- Sidebar (mobile) ----------
  const sidebar = $('#sidebar');
  const backdrop = $('.sidebar-backdrop');
  $$('[data-toggle-sidebar]').forEach((b) => b.addEventListener('click', () => {
    sidebar && sidebar.classList.toggle('open');
    backdrop && backdrop.classList.toggle('show');
  }));
  $$('[data-close-sidebar]').forEach((b) => b.addEventListener('click', () => {
    sidebar && sidebar.classList.remove('open');
    backdrop && backdrop.classList.remove('show');
  }));

  // ---------- Dropdowns ----------
  $$('[data-dropdown]').forEach((dd) => {
    const t = $('[data-dropdown-toggle]', dd);
    t && t.addEventListener('click', (e) => { e.stopPropagation(); dd.classList.toggle('open'); });
  });
  document.addEventListener('click', (e) => {
    $$('[data-dropdown].open').forEach((dd) => { if (!dd.contains(e.target)) dd.classList.remove('open'); });
  });

  // ---------- Alerts ----------
  $$('[data-dismiss]').forEach((b) => b.addEventListener('click', () => b.closest('.alert').remove()));
  $$('[data-autohide]').forEach((a) => setTimeout(() => {
    a.style.transition = 'opacity .4s'; a.style.opacity = '0'; setTimeout(() => a.remove(), 400);
  }, 6000));

  // ---------- Confirm before destructive POST forms ----------
  $$('form[data-confirm]').forEach((f) => f.addEventListener('submit', (e) => {
    if (!window.confirm(f.dataset.confirm)) e.preventDefault();
  }));

  // ---------- Modals (<dialog>) ----------
  $$('[data-open-modal]').forEach((b) => b.addEventListener('click', () => {
    const d = document.getElementById(b.dataset.openModal);
    d && d.showModal();
  }));
  $$('dialog.modal').forEach((d) => {
    $$('[data-close-modal]', d).forEach((b) => b.addEventListener('click', () => d.close()));
    d.addEventListener('click', (e) => { if (e.target === d) d.close(); });
  });
  const autoOpen = $('dialog.modal[data-auto-open]');
  if (autoOpen) autoOpen.showModal();

  // ---------- Notifications ----------
  $$('[data-notif-id]').forEach((a) => a.addEventListener('click', () => {
    api('api/notifications_read.php', { id: Number(a.dataset.notifId) }).catch(() => {});
  }));
  $$('[data-mark-all-read]').forEach((b) => b.addEventListener('click', async (e) => {
    e.stopPropagation();
    try {
      await api('api/notifications_read.php', { all: true });
      $$('.notif.unread').forEach((n) => n.classList.remove('unread'));
      const badge = $('[data-unread-badge]'); badge && badge.remove();
      b.remove();
    } catch (err) { toast(err.message, 'danger'); }
  }));

  // ---------- One-click task completion (AJAX) ----------
  $$('[data-task-toggle]').forEach((btn) => btn.addEventListener('click', async () => {
    const id = Number(btn.dataset.taskToggle);
    btn.disabled = true;
    try {
      const r = await api('api/task_toggle.php', { id });
      const row = btn.closest('[data-task-row]');
      btn.classList.toggle('checked', r.status === 'done');
      if (row) {
        row.classList.toggle('is-done', r.status === 'done');
        if (r.status === 'done') row.classList.remove('overdue');
        const st = $('[data-status-label]', row);
        if (st) st.innerHTML = r.status === 'done'
          ? '<span class="badge badge-success"><i class="bi bi-check2"></i> Done</span>'
          : '<span class="badge badge-muted">Pending</span>';
      }
      toast(r.status === 'done' ? 'Nice work! Task completed 🎉' : 'Task marked as pending');
    } catch (err) { toast(err.message, 'danger'); }
    btn.disabled = false;
  }));

  // ---------- Password strength + match ----------
  const pw = $('[data-pw-meter]');
  if (pw) {
    const bar = $('.pw-meter span');
    pw.addEventListener('input', () => {
      const v = pw.value; let s = 0;
      if (v.length >= 8) s++; if (/[A-Z]/.test(v) && /[a-z]/.test(v)) s++; if (/\d/.test(v)) s++; if (/[^A-Za-z0-9]/.test(v)) s++; if (v.length >= 12) s++;
      const colors = ['#ef4444', '#ef4444', '#f59e0b', '#eab308', '#10b981', '#10b981'];
      bar.style.width = (s / 5 * 100) + '%'; bar.style.background = colors[s];
    });
  }
  $$('form[data-validate]').forEach((f) => f.addEventListener('submit', (e) => {
    let ok = true;
    $$('.field-error.js', f).forEach((x) => x.remove());
    $$('.input', f).forEach((i) => i.classList.remove('is-invalid'));
    const fail = (input, msg) => {
      ok = false; input.classList.add('is-invalid');
      const s = document.createElement('div'); s.className = 'field-error js'; s.textContent = msg;
      input.closest('.field').appendChild(s);
    };
    $$('[required]', f).forEach((i) => { if (!i.value.trim()) fail(i, 'This field is required.'); });
    const email = $('input[type=email]', f);
    if (email && email.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) fail(email, 'Enter a valid email address.');
    const p1 = $('[name=password]', f), p2 = $('[name=password_confirm]', f);
    if (p1 && p2) {
      if (p1.value.length < 8 || !/[A-Za-z]/.test(p1.value) || !/\d/.test(p1.value)) fail(p1, 'At least 8 characters with letters and numbers.');
      if (p1.value !== p2.value) fail(p2, 'Passwords do not match.');
    }
    if (!ok) e.preventDefault();
  }));

  // ---------- Kanban drag & drop ----------
  const board = $('[data-kanban]');
  if (board) {
    let dragged = null;
    $$('.kcard', board).forEach((c) => {
      c.addEventListener('dragstart', () => { dragged = c; c.classList.add('dragging'); });
      c.addEventListener('dragend', () => { c.classList.remove('dragging'); dragged = null; });
    });
    $$('.kcol', board).forEach((col) => {
      col.addEventListener('dragover', (e) => { e.preventDefault(); col.classList.add('drag-over'); });
      col.addEventListener('dragleave', () => col.classList.remove('drag-over'));
      col.addEventListener('drop', async (e) => {
        e.preventDefault(); col.classList.remove('drag-over');
        if (!dragged) return;
        const card = dragged, from = card.parentElement.closest('.kcol');
        if (from === col) return;
        $('.kcol-body', col).appendChild(card);
        try {
          const r = await api('api/kanban_move.php', { id: Number(card.dataset.id), status: col.dataset.status });
          updateBoard(r.progress);
        } catch (err) {
          $('.kcol-body', from).appendChild(card);
          toast(err.message, 'danger');
        }
      });
    });
    function updateBoard(progress) {
      $$('.kcol', board).forEach((col) => { $('.count', col).textContent = $$('.kcard', col).length; });
      const bar = $('[data-progress-bar]'), lbl = $('[data-progress-label]');
      if (bar) bar.style.width = progress + '%';
      if (lbl) lbl.textContent = progress + '%';
      toast('Task moved');
    }
  }

  // ---------- Highlight a task from a notification link ----------
  const hl = new URLSearchParams(location.search).get('highlight');
  if (hl) {
    const row = document.querySelector(`[data-task-row="${CSS.escape(hl)}"]`);
    if (row) { row.classList.add('highlight'); row.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
  }

  // ---------- Chart.js defaults that follow the theme ----------
  window.chartColors = function () {
    const cs = getComputedStyle(document.documentElement);
    return { text: cs.getPropertyValue('--muted').trim(), grid: cs.getPropertyValue('--border').trim(), primary: cs.getPropertyValue('--primary').trim() };
  };
  if (window.Chart) {
    Chart.defaults.font.family = '"Plus Jakarta Sans", system-ui, sans-serif';
    Chart.defaults.color = chartColors().text;
    Chart.defaults.borderColor = chartColors().grid;
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    document.addEventListener('themechange', () => {
      Chart.defaults.color = chartColors().text; Chart.defaults.borderColor = chartColors().grid;
      Object.values(Chart.instances).forEach((c) => {
        Object.values(c.options.scales || {}).forEach((s) => { if (s.grid) s.grid.color = chartColors().grid; if (s.ticks) s.ticks.color = chartColors().text; });
        if (c.options.plugins && c.options.plugins.legend) c.options.plugins.legend.labels.color = chartColors().text;
        c.update();
      });
    });
  }
})();
