/* PGCEAP Portal — App JavaScript */
'use strict';

document.addEventListener('DOMContentLoaded', function () {

    // ── Sidebar Toggle ──────────────────────────────────────
    const sidebar = document.getElementById('sidebar');
    const toggle  = document.getElementById('sidebarToggle');
    if (toggle && sidebar) {
        toggle.addEventListener('click', () => sidebar.classList.toggle('open'));
        document.addEventListener('click', (e) => {
            if (window.innerWidth < 992 && sidebar.classList.contains('open') &&
                !sidebar.contains(e.target) && !toggle.contains(e.target)) {
                sidebar.classList.remove('open');
            }
        });
    }

    // ── Auto-dismiss Alerts ─────────────────────────────────
    document.querySelectorAll('.alert-auto-dismiss').forEach(el => {
        setTimeout(() => {
            el.style.transition = 'opacity .5s';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 500);
        }, 4000);
    });

    // ── Confirm Delete ──────────────────────────────────────
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', function (e) {
            if (!confirm(this.dataset.confirm || 'Are you sure?')) {
                e.preventDefault();
                e.stopPropagation();
            }
        });
    });

    // ── Live Search Filter ──────────────────────────────────
    const searchInput = document.getElementById('liveSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const q = this.value.toLowerCase();
            document.querySelectorAll('tbody tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    }

    // ── Upload Zone Drag & Drop ─────────────────────────────
    document.querySelectorAll('.upload-zone').forEach(zone => {
        const input = zone.querySelector('input[type=file]');
        zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('dragover'); });
        zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
        zone.addEventListener('drop', e => {
            e.preventDefault();
            zone.classList.remove('dragover');
            if (input && e.dataTransfer.files.length) {
                const dt = new DataTransfer();
                dt.items.add(e.dataTransfer.files[0]);
                input.files = dt.files;
                zone.querySelector('.upload-filename').textContent = e.dataTransfer.files[0].name;
            }
        });
        zone.addEventListener('click', () => input && input.click());
        if (input) {
            input.addEventListener('change', () => {
                const fn = zone.querySelector('.upload-filename');
                if (fn && input.files[0]) fn.textContent = input.files[0].name;
            });
        }
    });

    // ── Dual Table Picker (Billing/Payroll) ─────────────────
    initDualPicker();

    // ── Tooltips ────────────────────────────────────────────
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
        new bootstrap.Tooltip(el, { trigger: 'hover' });
    });

    // ── Toast Helper (global) ───────────────────────────────
    window.showToast = function (msg, type = 'success') {
        const container = document.querySelector('.toast-container') || (() => {
            const c = document.createElement('div');
            c.className = 'toast-container';
            document.body.appendChild(c);
            return c;
        })();
        const icons = { success: 'check-circle-fill', danger: 'x-circle-fill', warning: 'exclamation-triangle-fill', info: 'info-circle-fill' };
        const toast = document.createElement('div');
        toast.className = 'toast show align-items-center mb-2';
        toast.innerHTML = `<div class="d-flex align-items-center p-3 gap-2">
            <i class="bi bi-${icons[type] || 'info-circle-fill'} text-${type}"></i>
            <span class="flex-grow-1">${msg}</span>
            <button type="button" class="btn-close ms-2" onclick="this.closest('.toast').remove()"></button>
        </div>`;
        container.appendChild(toast);
        setTimeout(() => toast.remove(), 4000);
    };
});

/* ── Dual Table Picker ──────────────────────────────────────── */
function initDualPicker() {
    const available = document.getElementById('availableList');
    const selected  = document.getElementById('selectedList');
    if (!available || !selected) return;

    function moveRow(row, from, to) {
        from.querySelector('tbody').removeChild(row);
        to.querySelector('tbody').appendChild(row);
        updatePickerCounts();
    }

    function updatePickerCounts() {
        const avail = available.querySelectorAll('tbody tr').length;
        const sel   = selected.querySelectorAll('tbody tr').length;
        const ac = document.getElementById('availableCount');
        const sc = document.getElementById('selectedCount');
        const totalEl = document.getElementById('totalAmount');
        if (ac) ac.textContent = avail;
        if (sc) sc.textContent = sel;
        if (totalEl) {
            let total = 0;
            selected.querySelectorAll('tbody tr .amount-input').forEach(inp => {
                total += parseFloat(inp.value) || 0;
            });
            totalEl.textContent = '₱' + total.toLocaleString('en-PH', { minimumFractionDigits: 2 });
        }
    }

    // Move single row to selected
    document.addEventListener('click', function (e) {
        if (e.target.classList.contains('btn-move-right')) {
            const row = e.target.closest('tr');
            if (row && row.closest('table') === available) moveRow(row, available, selected);
        }
        if (e.target.classList.contains('btn-move-left')) {
            const row = e.target.closest('tr');
            if (row && row.closest('table') === selected) moveRow(row, selected, available);
        }
    });

    // Move all
    document.getElementById('btnMoveAll')?.addEventListener('click', () => {
        [...available.querySelectorAll('tbody tr')].forEach(r => {
            selected.querySelector('tbody').appendChild(r);
        });
        updatePickerCounts();
    });

    document.getElementById('btnRemoveAll')?.addEventListener('click', () => {
        [...selected.querySelectorAll('tbody tr')].forEach(r => {
            available.querySelector('tbody').appendChild(r);
        });
        updatePickerCounts();
    });

    // Update total on amount change
    document.addEventListener('input', function (e) {
        if (e.target.classList.contains('amount-input')) updatePickerCounts();
    });

    // Submit: collect selected rows into hidden form
    document.getElementById('exportForm')?.addEventListener('submit', function (e) {
        const rows = selected.querySelectorAll('tbody tr');
        if (!rows.length) {
            e.preventDefault();
            window.showToast('Please add at least one scholar to the list.', 'warning');
            return;
        }
        const data = [];
        rows.forEach(row => {
            data.push({
                scholar_id: row.dataset.scholarId,
                amount:     row.querySelector('.amount-input')?.value || 0
            });
        });
        document.getElementById('pickerData').value = JSON.stringify(data);
    });

    updatePickerCounts();
}

/* ── Mobile Scholar Nav: show labels on tap ──────────────────── */
(function () {
  function isMobile() { return window.innerWidth < 768; }

  // Add aria-labels and title attributes to nav links for accessibility
  document.querySelectorAll('.scholar-nav-link').forEach(link => {
    const text = link.textContent.trim();
    if (text) {
      link.setAttribute('title', text);
      link.setAttribute('aria-label', text);
    }
  });

  // Swipe-to-scroll support is native via overflow-x: auto

  // Prevent double-tap zoom on nav links and buttons
  let lastTap = 0;
  document.addEventListener('touchend', function(e) {
    const now = Date.now();
    if (now - lastTap < 300) {
      if (e.target.closest('.btn, .scholar-nav-link, .nav-item')) {
        e.preventDefault();
      }
    }
    lastTap = now;
  }, { passive: false });
})();
