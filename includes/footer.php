</div><!-- end content-body -->
</main><!-- end main-content -->

<!-- Mobile sidebar backdrop -->
<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="closeSidebar()"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ---- Active nav link highlight ----
(function() {
    const currentPath = window.location.pathname;
    document.querySelectorAll('.sidebar nav a').forEach(a => {
        const href = a.getAttribute('href');
        if (!href) return;
        // Normalize: strip leading ../
        const normalHref = href.replace(/^(\.\.\/)+/, '/');
        if (currentPath.endsWith(normalHref) || currentPath.includes(normalHref.replace(/^\//, ''))) {
            a.classList.add('active');
        }
    });
})();

// ---- Mobile sidebar ----
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

// Patch the existing toggle button to use openSidebar/closeSidebar
const menuBtn = document.querySelector('.d-md-none[onclick]');
if (menuBtn) {
    menuBtn.setAttribute('onclick', '');
    menuBtn.addEventListener('click', function() {
        const sidebar = document.getElementById('sidebar');
        if (sidebar.classList.contains('open')) closeSidebar();
        else openSidebar();
    });
}

// ---- Auto-dismiss alerts after 5s ----
document.querySelectorAll('.alert.alert-success.alert-dismissible').forEach(el => {
    setTimeout(() => {
        const btn = el.querySelector('.btn-close');
        if (btn) btn.click();
    }, 5000);
});
</script>
</body>
</html>
