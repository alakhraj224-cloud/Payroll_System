// ============================================================
// assets/js/script.js  –  Vanilla JS enhancements
// ============================================================

document.addEventListener('DOMContentLoaded', () => {

    // ── Sidebar mobile toggle ──────────────────────────────
    const menuBtn = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');

    if (menuBtn && sidebar) {
        menuBtn.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 768 &&
                sidebar.classList.contains('open') &&
                !sidebar.contains(e.target) &&
                e.target !== menuBtn)
            {
                sidebar.classList.remove('open');
            }
        });
    }

    // ── Auto-dismiss flash messages ────────────────────────
    const flash = document.getElementById('flashMsg');
    if (flash) {
        setTimeout(() => {
            flash.style.opacity = '0';
            flash.style.transition = 'opacity .5s ease';
            setTimeout(() => flash.remove(), 500);
        }, 4000);
    }

    // ── Animate stat card values (count-up) ───────────────
    document.querySelectorAll('.stat-value').forEach(el => {
        const raw  = el.textContent.trim();
        const num  = parseFloat(raw.replace(/[^0-9.]/g, ''));
        const prefix = raw.includes('₹') ? '₹' : '';
        const suffix = raw.includes('%') ? '%' : (raw.includes('h') ? 'h' : '');

        if (isNaN(num) || num === 0) return;

        let start = 0;
        const duration = 800;
        const startTime = performance.now();

        function update(time) {
            const elapsed = time - startTime;
            const progress = Math.min(elapsed / duration, 1);
            // ease-out cubic
            const eased = 1 - Math.pow(1 - progress, 3);
            const current = num * eased;

            if (prefix === '₹') {
                el.textContent = '₹' + current.toLocaleString('en-IN', {minimumFractionDigits: 0, maximumFractionDigits: 0});
            } else {
                el.textContent = prefix + current.toFixed(raw.includes('.') ? 1 : 0) + suffix;
            }

            if (progress < 1) requestAnimationFrame(update);
            else el.textContent = raw; // restore exact original
        }

        requestAnimationFrame(update);
    });

    // ── Contribution progress bar animation ───────────────
    document.querySelectorAll('.contrib-progress, .score-fill').forEach(bar => {
        const target = bar.style.width;
        bar.style.width = '0';
        setTimeout(() => {
            bar.style.transition = 'width 1s cubic-bezier(0.4,0,0.2,1)';
            bar.style.width = target;
        }, 100);
    });

    // ── Client-side form validation helpers ───────────────
    // (Server-side is authoritative; this is just UX polish)
    document.querySelectorAll('form[novalidate]').forEach(form => {
        form.addEventListener('submit', (e) => {
            let valid = true;
            form.querySelectorAll('[required]').forEach(field => {
                if (!field.value.trim()) {
                    field.style.borderColor = 'var(--danger)';
                    field.style.boxShadow   = '0 0 0 3px rgba(255,82,82,0.15)';
                    valid = false;
                } else {
                    field.style.borderColor = '';
                    field.style.boxShadow   = '';
                }
            });

            // Hours validation
            const hoursField = form.querySelector('[name="hours_worked"]');
            if (hoursField) {
                const val = parseFloat(hoursField.value);
                if (val <= 0 || val > 12) {
                    hoursField.style.borderColor = 'var(--danger)';
                    hoursField.style.boxShadow   = '0 0 0 3px rgba(255,82,82,0.15)';
                    valid = false;
                }
            }

            if (!valid) {
                e.preventDefault();
                // Scroll to first error
                const first = form.querySelector('[style*="danger"]');
                if (first) first.focus();
            }
        });

        // Clear error style on input
        form.querySelectorAll('input, select, textarea').forEach(field => {
            field.addEventListener('input', () => {
                field.style.borderColor = '';
                field.style.boxShadow   = '';
            });
        });
    });

    // ── Payroll table: highlight top contributor ───────────
    const payrollTable = document.querySelector('.payroll-table tbody');
    if (payrollTable) {
        const firstRow = payrollTable.querySelector('tr:first-child');
        if (firstRow) {
            firstRow.style.background = 'rgba(240,165,0,0.05)';
            firstRow.style.borderLeft = '3px solid var(--accent)';
        }
    }

    // ── Tooltips on truncated cells ────────────────────────
    document.querySelectorAll('.desc-cell').forEach(cell => {
        if (cell.scrollWidth > cell.clientWidth) {
            cell.title = cell.textContent.trim();
        }
    });

    // ── Table sort (click header) ──────────────────────────
    document.querySelectorAll('.data-table th').forEach((th, colIndex) => {
        th.style.cursor = 'pointer';
        th.addEventListener('click', () => {
            const table   = th.closest('table');
            const tbody   = table.querySelector('tbody');
            const rows    = Array.from(tbody.querySelectorAll('tr'));
            const dir     = th.dataset.sort === 'asc' ? -1 : 1;

            rows.sort((a, b) => {
                const aText = a.cells[colIndex]?.textContent.trim() ?? '';
                const bText = b.cells[colIndex]?.textContent.trim() ?? '';
                const aNum  = parseFloat(aText.replace(/[^0-9.-]/g, ''));
                const bNum  = parseFloat(bText.replace(/[^0-9.-]/g, ''));

                if (!isNaN(aNum) && !isNaN(bNum)) return (aNum - bNum) * dir;
                return aText.localeCompare(bText) * dir;
            });

            // Clear all sort indicators
            table.querySelectorAll('th').forEach(t => {
                t.dataset.sort = '';
                t.textContent = t.textContent.replace(/ [▲▼]$/, '');
            });

            th.dataset.sort = dir === 1 ? 'asc' : 'desc';
            th.textContent += dir === 1 ? ' ▲' : ' ▼';
            rows.forEach(r => tbody.appendChild(r));
        });
    });

});