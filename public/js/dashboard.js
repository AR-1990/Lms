/**
 * Dashboard shell interactions
 */
document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.getElementById('dashMenuToggle');
    const sidebar = document.getElementById('dashSidebar');

    if (toggle && sidebar) {
        toggle.addEventListener('click', function () {
            sidebar.classList.toggle('is-open');
        });
    }
});
