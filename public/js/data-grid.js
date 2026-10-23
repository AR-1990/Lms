/**
 * Shared server-side grid (DataTables client for Yajra endpoints).
 * Every endpoint returns the standard envelope; the Yajra payload is in envelope.data.
 * Every grid gets: a search box above each searchable column, a date range (when the table has a date,
 * see App\Support\GridDataTable) and "Export CSV" of all filtered rows, fetched page by page.
 * Usage: PinnacleGrid.create(tableElement, { url, columns, order, params, searchPlaceholder, emptyText, exportName })
 */
(function () {
    'use strict';

    const EXPORT_PAGE = 500;
    const EXPORT_LIMIT = 50000;

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function plainText(html) {
        const box = document.createElement('div');
        // A space between tags keeps 'Name' and its code apart ('Playgroup PG', not 'PlaygroupPG').
        box.innerHTML = String(html === null || html === undefined ? '' : html).replace(/></g, '> <');

        return (box.textContent || '').replace(/\s+/g, ' ').trim();
    }

    function csvCell(value) {
        const text = String(value === null || value === undefined ? '' : value);

        // Leading = + - @ would run as a formula in Excel.
        const safe = /^[=+\-@]/.test(text) ? "'" + text : text;

        return /[",\n]/.test(safe) ? '"' + safe.replace(/"/g, '""') + '"' : safe;
    }

    function debounce(callback, wait) {
        let timer = null;

        return function () {
            const args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function () { callback.apply(null, args); }, wait);
        };
    }

    function create(table, options) {
        let turnPage = false;
        let lastParams = null;
        let filtersBuilt = false;
        const columnSearch = {};
        const toolbar = document.createElement('div');

        toolbar.className = 'dash-grid-tools';
        toolbar.innerHTML = '<span class="dash-grid-dates" data-grid-dates hidden>'
            + '<label>From <input type="date" data-grid-from aria-label="From date"></label>'
            + '<label>To <input type="date" data-grid-to aria-label="To date"></label>'
            + '<button type="button" class="dash-link-btn" data-grid-dates-clear hidden>Clear dates</button></span>'
            + '<button type="button" class="dash-btn dash-btn-ghost dash-btn-sm" data-grid-export>Export CSV</button>';

        const fromInput = toolbar.querySelector('[data-grid-from]');
        const toInput = toolbar.querySelector('[data-grid-to]');
        const clearDates = toolbar.querySelector('[data-grid-dates-clear]');

        const grid = new window.DataTable(table, {
            serverSide: true,
            processing: true,
            searchDelay: 350,
            autoWidth: false,
            pageLength: options.pageLength || 15,
            lengthMenu: [10, 15, 25, 50, 100],
            order: options.order || [],
            columns: options.columns,
            layout: {
                topStart: 'search',
                topEnd: ['pageLength', function () { return toolbar; }],
                bottomStart: 'info',
                bottomEnd: 'paging'
            },
            language: {
                search: '',
                searchPlaceholder: options.searchPlaceholder || 'Search…',
                lengthMenu: '_MENU_ per page',
                info: 'Showing _START_–_END_ of _TOTAL_',
                infoEmpty: 'Nothing to show',
                infoFiltered: '(filtered from _MAX_)',
                emptyTable: options.emptyText || 'Nothing here yet.',
                zeroRecords: 'No matching records found.',
                processing: '<span class="dash-grid-loader" aria-label="Loading"></span>',
                paginate: { first: '«', previous: '‹', next: '›', last: '»' }
            },
            ajax: function (data, callback) {
                const params = Object.assign(data, options.params ? options.params() : {}, {
                    date_from: fromInput.value || '',
                    date_to: toInput.value || '',
                    col_search: columnSearch
                });

                lastParams = params;
                window.PinnacleDashboard.api('GET', options.url + '?' + window.jQuery.param(params))
                    .then(function (envelope) {
                        toolbar.querySelector('[data-grid-dates]').hidden = !envelope.data.date_column;
                        buildFilters(envelope.data.column_search || []);
                        callback(envelope.data);
                    })
                    .catch(function (envelope) {
                        callback({ draw: data.draw, recordsTotal: 0, recordsFiltered: 0, data: [] });
                        window.PinnacleDashboard.toast((envelope && envelope.message) || 'Could not load the list.', 'error');
                    });
            }
        });

        /* ---------- Date range ---------- */

        [fromInput, toInput].forEach(function (input) {
            input.addEventListener('change', function () {
                clearDates.hidden = !fromInput.value && !toInput.value;
                grid.draw();
            });
        });

        clearDates.addEventListener('click', function () {
            fromInput.value = '';
            toInput.value = '';
            clearDates.hidden = true;
            grid.draw();
        });

        /* ---------- A search box above each column the server can search ---------- */

        const head = table.tHead;

        function columnKey(column) {
            return column.name || (typeof column.data === 'string' ? column.data : null);
        }

        function buildFilters(keys) {
            if (filtersBuilt || !head) {
                return;
            }

            filtersBuilt = true;

            if (!keys.length) {
                return;
            }

            const filterRow = document.createElement('tr');

            filterRow.className = 'dash-grid-filters';
            options.columns.forEach(function (column, index) {
                const cell = document.createElement('th');
                const key = columnKey(column);

                if (key && keys.indexOf(key) !== -1) {
                    const input = document.createElement('input');
                    const title = head.rows[0] && head.rows[0].cells[index] ? head.rows[0].cells[index].textContent.trim() : '';

                    input.type = 'search';
                    input.placeholder = title ? 'Search ' + title.toLowerCase() : 'Search';
                    input.setAttribute('aria-label', input.placeholder);
                    ['click', 'mousedown', 'keydown'].forEach(function (name) {
                        input.addEventListener(name, function (event) { event.stopPropagation(); });
                    });
                    input.addEventListener('input', debounce(function () {
                        const value = input.value.trim();

                        if (value) {
                            columnSearch[key] = value;
                        } else {
                            delete columnSearch[key];
                        }

                        grid.draw();
                    }, 400));
                    cell.appendChild(input);
                }

                filterRow.appendChild(cell);
            });

            head.appendChild(filterRow);
        }

        /* ---------- Export CSV: every filtered row, a page at a time ---------- */

        const exportButton = toolbar.querySelector('[data-grid-export]');

        exportButton.addEventListener('click', function () {
            if (!lastParams) {
                return;
            }

            const columns = options.columns.map(function (column, index) {
                const title = head && head.rows[0] && head.rows[0].cells[index] ? head.rows[0].cells[index].textContent.trim() : '';

                return { column: column, title: title || (typeof column.data === 'string' ? column.data : '') };
            }).filter(function (entry) {
                return entry.column.data !== null && entry.column.exportable !== false && entry.title !== '';
            });
            const rows = [];

            exportButton.disabled = true;
            exportButton.textContent = 'Exporting…';

            function page(start) {
                const params = Object.assign({}, lastParams, { start: start, length: EXPORT_PAGE, draw: 0 });

                return window.PinnacleDashboard.api('GET', options.url + '?' + window.jQuery.param(params)).then(function (envelope) {
                    const payload = envelope.data;

                    payload.data.forEach(function (row) {
                        rows.push(columns.map(function (entry) {
                            const value = typeof entry.column.data === 'string'
                                ? entry.column.data.split('.').reduce(function (carry, key) { return carry === null || carry === undefined ? carry : carry[key]; }, row)
                                : null;
                            const shown = entry.column.render ? entry.column.render(value, 'display', row, {}) : value;

                            return plainText(shown);
                        }));
                    });

                    if (payload.data.length === EXPORT_PAGE && start + EXPORT_PAGE < Math.min(payload.recordsFiltered, EXPORT_LIMIT)) {
                        return page(start + EXPORT_PAGE);
                    }

                    return null;
                });
            }

            page(0).then(function () {
                const lines = [columns.map(function (entry) { return csvCell(entry.title); }).join(',')]
                    .concat(rows.map(function (row) { return row.map(csvCell).join(','); }));
                const blob = new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' });
                const link = document.createElement('a');
                const name = (options.exportName || document.title.split('|')[0].trim() || 'export').replace(/[^\w-]+/g, '-').toLowerCase();

                link.href = URL.createObjectURL(blob);
                link.download = name + '-' + new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10) + '.csv';
                document.body.appendChild(link);
                link.click();
                link.remove();
                setTimeout(function () { URL.revokeObjectURL(link.href); }, 1000);
                window.PinnacleDashboard.toast(rows.length + ' row(s) exported.');
            }).catch(function (envelope) {
                window.PinnacleDashboard.toast((envelope && envelope.message) || 'The export failed.', 'error');
            }).finally(function () {
                exportButton.disabled = false;
                exportButton.textContent = 'Export CSV';
            });
        });

        grid.on('page', function () {
            turnPage = true;
        });

        grid.on('draw', function () {
            if (!turnPage) {
                return;
            }

            const body = table.tBodies[0];
            body.classList.remove('is-turning');
            void body.offsetWidth;
            body.classList.add('is-turning');
            turnPage = false;
        });

        return grid;
    }

    const rupees = new Intl.NumberFormat('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    /**
     * Amounts travel in paisa (integers); show them as "Rs 1,500.50".
     */
    function money(paisa) {
        return 'Rs ' + rupees.format((Number(paisa) || 0) / 100);
    }

    window.PinnacleGrid = { create: create, escape: escapeHtml, money: money };
})();
