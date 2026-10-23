/**
 * Reports centre: pick a report from the list, set its filters, and the table, totals, summary and a bar chart
 * load from /api/erp/reports/{key}. CSV and Print open the export links with the same filters. The chosen
 * report and filters stay in the address bar so a report can be bookmarked or shared.
 */
(function () {
    'use strict';

    const root = document.querySelector('[data-reports]');
    const ui = window.PinnacleDashboard;

    if (!root || !ui) {
        return;
    }

    const api = root.dataset.api;
    const body = root.querySelector('[data-report-body]');
    const form = root.querySelector('[data-report-filters]');
    const table = root.querySelector('[data-report-table]');
    const pager = root.querySelector('[data-report-pager]');
    const money = new Intl.NumberFormat('en-PK', { maximumFractionDigits: 0 });
    const dateFormat = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    const local = function (date) { return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 10); };
    let current = null;
    let page = 1;
    let timer = null;

    function esc(value) {
        const div = document.createElement('div');
        div.textContent = value === null || value === undefined ? '' : String(value);

        return div.innerHTML;
    }

    function cell(value, type) {
        if (value === null || value === undefined || value === '') {
            return type === 'text' ? '—' : '';
        }

        switch (type) {
        case 'money':
            return (value < 0 ? '−' : '') + 'Rs ' + money.format(Math.abs(value) / 100);
        case 'percent':
            return value + '%';
        case 'number':
            return Number(value).toLocaleString('en-PK', { maximumFractionDigits: 1 });
        case 'date':
            return dateFormat.format(new Date(value));
        default:
            return value;
        }
    }

    function isNumeric(type) {
        return type === 'money' || type === 'number' || type === 'percent';
    }

    function params() {
        const values = new URLSearchParams();

        current.filters.forEach(function (name) {
            const field = form.elements[name];

            if (field && field.value) {
                values.set(name, field.value);
            }
        });

        return values;
    }

    function setRange(range) {
        const now = new Date();
        const ranges = {
            month: [new Date(now.getFullYear(), now.getMonth(), 1), now],
            'last-month': [new Date(now.getFullYear(), now.getMonth() - 1, 1), new Date(now.getFullYear(), now.getMonth(), 0)],
            year: [new Date(now.getFullYear(), 0, 1), now]
        };

        form.elements.from.value = local(ranges[range][0]);
        form.elements.to.value = local(ranges[range][1]);
    }

    function render(report) {
        root.querySelector('[data-report-period]').textContent = report.period + (report.meta ? ' · ' + report.meta.total + ' rows' : '');
        root.querySelector('[data-report-summary]').innerHTML = report.summary.map(function (item) {
            return '<article class="dash-stat"><span class="dash-stat-label">' + esc(item[0]) + '</span><strong class="dash-stat-value dash-stat-value-sm">' + esc(cell(item[1], item[2])) + '</strong></article>';
        }).join('');

        const chart = root.querySelector('[data-report-chart]');
        const chartRows = report.chart ? report.rows.filter(function (row) { return Number(row[report.chart.value]) > 0; }).slice(0, 12) : [];
        const valueType = report.chart ? (report.columns.find(function (column) { return column.key === report.chart.value; }) || {}).type : null;
        const max = Math.max.apply(null, chartRows.map(function (row) { return Number(row[report.chart.value]); }).concat([1]));

        chart.hidden = chartRows.length < 2;
        chart.innerHTML = chartRows.map(function (row) {
            return '<li><div class="dash-bar-row"><span>' + esc(cell(row[report.chart.label], 'text')) + '</span><strong>' + esc(cell(row[report.chart.value], valueType)) + '</strong></div>'
                + '<div class="dash-bar-track"><div class="dash-bar-fill" style="--bar: ' + (Number(row[report.chart.value]) / max).toFixed(3) + '"></div></div></li>';
        }).join('');

        const head = '<thead><tr>' + report.columns.map(function (column) { return '<th class="' + (isNumeric(column.type) ? 'dash-col-num' : '') + '">' + esc(column.label) + '</th>'; }).join('') + '</tr></thead>';
        const line = function (row, className) {
            return '<tr class="' + (className || '') + '">' + report.columns.map(function (column) {
                return '<td class="' + (isNumeric(column.type) ? 'dash-col-num' : '') + '">' + esc(cell(row[column.key], column.type)) + '</td>';
            }).join('') + '</tr>';
        };

        table.innerHTML = head + '<tbody>' + (report.rows.length ? report.rows.map(function (row) { return line(row); }).join('') : '<tr><td colspan="' + report.columns.length + '" class="dash-muted">Nothing to show for this period.</td></tr>')
            + (report.totals && report.rows.length ? line(report.totals, 'dash-report-total') : '') + '</tbody>';

        pager.hidden = !report.meta || report.meta.last_page < 2;

        if (report.meta) {
            pager.querySelector('[data-page-info]').textContent = 'Page ' + report.meta.current_page + ' of ' + report.meta.last_page;
            pager.querySelector('[data-page="prev"]').disabled = report.meta.current_page <= 1;
            pager.querySelector('[data-page="next"]').disabled = report.meta.current_page >= report.meta.last_page;
        }
    }

    function load() {
        const query = params();
        query.set('page', page);
        body.classList.add('is-loading');
        history.replaceState(null, '', '?report=' + current.key + '&' + params().toString());

        return ui.api('GET', api + '/' + current.key + '?' + query.toString()).then(function (envelope) {
            render(envelope.data);
        }).catch(function (envelope) {
            ui.toast((envelope && envelope.message) || 'The report could not be loaded.', 'error');
        }).finally(function () {
            body.classList.remove('is-loading');
        });
    }

    function open(button, initial) {
        root.querySelectorAll('[data-report]').forEach(function (other) { other.classList.toggle('is-active', other === button); });
        current = { key: button.dataset.report, filters: button.dataset.filters ? button.dataset.filters.split(' ') : [] };
        page = 1;

        form.querySelectorAll('[data-filter-for]').forEach(function (field) {
            field.hidden = current.filters.indexOf(field.dataset.filterFor) === -1;
        });

        if (!form.elements.from.value) {
            setRange('month');
        }

        if (!form.elements.as_of.value) {
            form.elements.as_of.value = local(new Date());
        }

        Object.keys(initial || {}).forEach(function (name) {
            if (form.elements[name]) {
                form.elements[name].value = initial[name];
            }
        });

        root.querySelector('[data-report-empty]').hidden = true;
        body.hidden = false;
        root.querySelector('[data-report-title]').textContent = button.querySelector('strong').textContent;
        root.querySelector('[data-report-description]').textContent = button.querySelector('span').textContent;

        if (window.matchMedia('(max-width: 900px)').matches) {
            root.querySelector('[data-report-view]').scrollIntoView({ block: 'start' });
        }

        load();
    }

    root.querySelectorAll('[data-report]').forEach(function (button) {
        button.addEventListener('click', function () { open(button); });
    });

    form.addEventListener('change', function () {
        page = 1;
        clearTimeout(timer);
        timer = setTimeout(load, 150);
    });

    form.querySelectorAll('[data-range]').forEach(function (chip) {
        chip.addEventListener('click', function () {
            setRange(chip.dataset.range);
            page = 1;
            load();
        });
    });

    pager.addEventListener('click', function (event) {
        const button = event.target.closest('[data-page]');

        if (button) {
            page += button.dataset.page === 'next' ? 1 : -1;
            load();
        }
    });

    root.querySelectorAll('[data-report-export]').forEach(function (button) {
        button.addEventListener('click', function () {
            const kind = button.dataset.reportExport;

            ui.openFile(api + '/' + current.key + '/' + kind + '?' + params().toString(), kind === 'csv' ? 'Preparing the CSV…' : 'Preparing the report…').catch(function () {});
        });
    });

    root.querySelector('[data-report-search]').addEventListener('input', function (event) {
        const term = event.target.value.trim().toLowerCase();
        let shown = 0;

        root.querySelectorAll('[data-report-group]').forEach(function (group) {
            let visible = 0;

            group.querySelectorAll('[data-report]').forEach(function (button) {
                const match = !term || button.dataset.search.indexOf(term) !== -1;
                button.parentElement.hidden = !match;
                visible += match ? 1 : 0;
            });

            group.hidden = visible === 0;
            shown += visible;
        });

        root.querySelector('[data-report-none]').hidden = shown > 0;
    });

    // Open the report named in the address bar, with its filters.
    const query = new URLSearchParams(location.search);
    const start = query.get('report') && root.querySelector('[data-report="' + query.get('report').replace(/[^a-z-]/g, '') + '"]');

    if (start) {
        const initial = {};
        ['from', 'to', 'as_of', 'class_id', 'group_by'].forEach(function (name) { if (query.get(name)) { initial[name] = query.get(name); } });
        open(start, initial);
    }
})();
