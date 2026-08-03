</div><!-- end content-body -->
</main><!-- end main-content -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// =========================================================
// DairyBox – Mobile JS
// =========================================================

// ---- Sidebar toggle ----
function toggleSidebar() {
    const s = document.getElementById('sidebar');
    const b = document.getElementById('sidebarBackdrop');
    if (s.classList.contains('open')) closeSidebar();
    else openSidebar();
}
function openSidebar() {
    document.getElementById('sidebar').classList.add('open');
    document.getElementById('sidebarBackdrop').classList.add('show');
    document.body.style.overflow = 'hidden';
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarBackdrop').classList.remove('show');
    document.body.style.overflow = '';
}

// Close sidebar when clicking a nav link on mobile
document.querySelectorAll('.sidebar nav a').forEach(a => {
    a.addEventListener('click', () => {
        if (window.innerWidth <= 768) closeSidebar();
    });
});

// ---- Active nav highlight ----
(function () {
    const path = window.location.pathname;
    // Sidebar links
    document.querySelectorAll('.sidebar nav a').forEach(a => {
        const href = a.getAttribute('href') || '';
        const clean = href.replace(/^(\.\.\/)+/, '/');
        if (path === clean || path.endsWith(clean.replace(/^\//, '/'))) {
            a.classList.add('active');
        }
    });
    // Bottom nav links
    document.querySelectorAll('.mobile-bottom-nav .nav-item').forEach(a => {
        const href = a.getAttribute('href') || '';
        const clean = href.replace(/^(\.\.\/)+/, '/');
        if (path === clean || path.endsWith(clean.replace(/^\//, '/'))) {
            a.classList.add('active');
        }
    });
})();

// ---- Auto-dismiss success alerts ----
document.querySelectorAll('.alert-success.alert-dismissible').forEach(el => {
    setTimeout(() => { el.querySelector('.btn-close')?.click(); }, 4000);
});

// ---- Swipe to close sidebar on mobile ----
(function () {
    let startX = 0, startY = 0;
    document.addEventListener('touchstart', e => {
        startX = e.touches[0].clientX;
        startY = e.touches[0].clientY;
    }, { passive: true });
    document.addEventListener('touchend', e => {
        const dx = e.changedTouches[0].clientX - startX;
        const dy = Math.abs(e.changedTouches[0].clientY - startY);
        if (dx < -60 && dy < 60) {
            // Swiped left – close sidebar
            closeSidebar();
        }
        if (dx > 60 && dy < 60 && startX < 30) {
            // Swiped right from edge – open sidebar
            openSidebar();
        }
    }, { passive: true });
})();

// ---- Make tables responsive: add data-label for small screens ----
document.querySelectorAll('.table').forEach(table => {
    const headers = [...table.querySelectorAll('thead th')].map(th => th.innerText.trim());
    table.querySelectorAll('tbody tr').forEach(row => {
        row.querySelectorAll('td').forEach((td, i) => {
            if (headers[i]) td.setAttribute('data-label', headers[i]);
        });
    });
});
</script>
</body>
</html>
