/**
 * Dashboard shell interactions
 */
document.addEventListener('DOMContentLoaded', function () {
    initDashboardShell();
});

function initDashboardShell() {
    const sidebar = document.getElementById('dashSidebar');
    const nav = document.getElementById('dashNav');

    renderDashboardSidebar(nav);
    initSidebarToggle(sidebar);
}

function initSidebarToggle(sidebar) {
    const toggle = document.getElementById('dashMenuToggle');

    if (!toggle || !sidebar) {
        return;
    }

    toggle.addEventListener('click', function () {
        sidebar.classList.toggle('is-open');
    });
}

function renderDashboardSidebar(nav) {
    if (!nav) {
        return;
    }

    const sidebarMarkup = getSidebarMarkup(nav);

    if (!sidebarMarkup) {
        return;
    }

    nav.innerHTML = sidebarMarkup;
}

function getSidebarMarkup(nav) {
    const sidebarMarkupJson = nav.getAttribute('data-sidebar-markup');

    if (!sidebarMarkupJson) {
        return '';
    }

    return JSON.parse(sidebarMarkupJson);
}
