/**
 * Website content screens (news & notices, stats, testimonials, FAQs) through /api/erp/website/{type}.
 */
(function () {
    'use strict';

    const ui = window.PinnacleDashboard;
    const kit = window.PinnacleResource;
    const root = document.querySelector('[data-website]');

    if (!ui || !kit || !window.PinnacleGrid || !root) {
        return;
    }

    const esc = window.PinnacleGrid.escape;
    const type = root.dataset.website;
    const modal = document.getElementById('websiteModal');
    const imageInput = modal.querySelector('[data-post-image]');
    const dateFormat = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });

    function display(render) {
        return function (value, columnType, row) {
            return columnType === 'display' ? render(value, row) : value;
        };
    }

    function titleCell(title, subtitle) {
        return '<span class="dash-cell-title">' + esc(title) + '</span>' + (subtitle ? '<span class="dash-muted">' + esc(subtitle) + '</span>' : '');
    }

    function shown(value) {
        return value ? '<span class="dash-badge dash-badge-ok">On website</span>' : '<span class="dash-badge dash-badge-system">Hidden</span>';
    }

    function clip(text) {
        const value = String(text || '');

        return value.length > 90 ? value.slice(0, 90) + '…' : value;
    }

    const SCREENS = {
        posts: {
            spec: { title: 'text', category_id: 'int', published_on: 'text', excerpt: 'text', body: 'text', read_minutes: 'int', is_published: 'bool', is_featured: 'bool', show_in_ticker: 'bool' },
            defaults: { is_published: true },
            nameOf: function (row) { return row.title; },
            order: [[1, 'desc']],
            columns: [
                { data: 'title', render: display(function (value, row) { return titleCell(value, [row.category_name, row.show_in_ticker ? 'in ticker' : null, row.is_featured ? 'featured' : null].filter(Boolean).join(' · ')); }) },
                { data: 'published_on', searchable: false, className: 'dash-hide-md', render: display(function (value) { return value ? esc(dateFormat.format(new Date(value))) : '—'; }) },
                { data: 'is_published', searchable: false, render: display(shown) }
            ]
        },
        stats: {
            spec: { label: 'text', value: 'int', suffix: 'text', sort_order: 'int', is_active: 'bool' },
            defaults: { is_active: true },
            nameOf: function (row) { return row.label; },
            order: [[0, 'asc']],
            columns: [
                { data: 'label' },
                { data: 'value', searchable: false, render: display(function (value, row) { return esc(Number(value).toLocaleString() + (row.suffix || '')); }) },
                { data: 'is_active', searchable: false, render: display(shown) }
            ]
        },
        testimonials: {
            spec: { name: 'text', role_title: 'text', quote: 'text', sort_order: 'int', is_active: 'bool' },
            defaults: { is_active: true },
            nameOf: function (row) { return row.name; },
            order: [[0, 'asc']],
            columns: [
                { data: 'name', render: display(function (value, row) { return titleCell(value, row.role_title); }) },
                { data: 'quote', className: 'dash-hide-md', render: display(function (value) { return esc(clip(value)); }) },
                { data: 'is_active', searchable: false, render: display(shown) }
            ]
        },
        faqs: {
            spec: { question: 'text', answer: 'text', sort_order: 'int', is_active: 'bool' },
            defaults: { is_active: true },
            nameOf: function (row) { return row.question; },
            order: [[0, 'asc']],
            columns: [
                { data: 'question' },
                { data: 'answer', className: 'dash-hide-md', render: display(function (value) { return esc(clip(value)); }) },
                { data: 'is_active', searchable: false, render: display(shown) }
            ]
        }
    };

    const screen = SCREENS[type];

    const mounted = kit.mount({
        root: root,
        modal: modal,
        order: screen.order,
        titles: { create: 'Add', edit: 'Edit' },
        emptyText: 'Nothing on the website yet.',
        nameOf: screen.nameOf,
        columns: screen.columns,
        fill: function (form, row) {
            kit.fillForm(form, row, screen.spec, screen.defaults);
        },
        payload: function (form) {
            return kit.readForm(form, screen.spec);
        },
        afterSave: function (record) {
            if (!imageInput || !imageInput.files.length || !record) {
                return;
            }

            const body = new FormData();
            body.append('image', imageInput.files[0]);

            ui.api('POST', root.dataset.resourceUrl + '/' + record.id + '/image', body)
                .then(function (envelope) {
                    ui.toast(envelope.message);
                    mounted.refresh();
                })
                .catch(function (envelope) {
                    ui.toast(kit.messageOf(envelope, 'The picture could not be uploaded.'), 'error');
                });
        }
    });
})();
