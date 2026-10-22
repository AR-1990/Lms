/**
 * Shared server-side grid (DataTables client for Yajra endpoints).
 * Every endpoint returns the standard envelope; the Yajra payload is in envelope.data.
 * Usage: PinnacleGrid.create(tableElement, { url, columns, order, params, searchPlaceholder, emptyText })
 */
(function () {
    'use strict';

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function create(table, options) {
        let turnPage = false;

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
                topEnd: 'pageLength',
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
                const params = Object.assign(data, options.params ? options.params() : {});

                window.PinnacleDashboard.api('GET', options.url + '?' + window.jQuery.param(params))
                    .then(function (envelope) {
                        callback(envelope.data);
                    })
                    .catch(function (envelope) {
                        callback({ draw: data.draw, recordsTotal: 0, recordsFiltered: 0, data: [] });
                        window.PinnacleDashboard.toast((envelope && envelope.message) || 'Could not load the list.', 'error');
                    });
            }
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
