/**
 * Library: Books (catalogue grid, add / edit with new copies, copies popup, loan rules) and Issue & Return
 * (lend by accession number, loans grid with return / lost). Runs on /api/erp/library.
 */
(function () {
    'use strict';

    const ui = window.PinnacleDashboard;
    const kit = window.PinnacleResource;

    if (!ui || !kit) {
        return;
    }

    const esc = window.PinnacleGrid.escape;
    const dateFormat = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });

    function display(render) {
        return function (value, type, row) { return type === 'display' ? render(value, row) : value; };
    }

    function shortDate(value) {
        return value ? esc(dateFormat.format(new Date(value + 'T00:00:00'))) : '—';
    }

    function todayIso() {
        return new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);
    }

    /* ---------- Loan rules ---------- */

    const rules = document.querySelector('[data-library-rules]');

    if (rules) {
        ui.api('GET', rules.dataset.url).then(function (envelope) {
            rules.elements.library_loan_days.value = envelope.data.library_loan_days;
            rules.elements.library_max_books.value = envelope.data.library_max_books;
            rules.elements.library_fine_per_day.value = Math.round(envelope.data.library_fine_per_day / 100);
        }).catch(function () {});

        rules.addEventListener('submit', function (event) {
            event.preventDefault();
            kit.submitForm(rules, 'PUT', rules.dataset.url, {
                library_loan_days: Number(rules.elements.library_loan_days.value),
                library_max_books: Number(rules.elements.library_max_books.value),
                library_fine_per_day: Math.round(Number(rules.elements.library_fine_per_day.value || 0) * 100)
            }).catch(function () {});
        });
    }

    /* ---------- Books ---------- */

    const booksRoot = document.querySelector('[data-library-books]');

    if (booksRoot) {
        const BOOK_FIELDS = { title: 'text', author: 'text', category_id: 'int', isbn: 'text', publisher: 'text', edition: 'text', shelf: 'text', copies: 'int', is_active: 'bool', description: 'text' };
        const copiesDialog = document.getElementById('copiesModal');

        const openCopies = function (row) {
            ui.api('GET', booksRoot.dataset.resourceUrl + '/' + row.id).then(function (envelope) {
                copiesDialog.querySelector('[data-copies-title]').textContent = 'Copies of ' + envelope.data.title;
                copiesDialog.querySelector('[data-copies-rows]').innerHTML = envelope.data.copies.map(function (copy) {
                    const status = copy.status === 'issued'
                        ? '<span class="dash-badge dash-badge-warn">Issued</span>'
                        : '<select data-copy="' + copy.id + '" aria-label="Status of ' + esc(copy.accession_no) + '">' + ['available', 'damaged', 'lost', 'withdrawn'].map(function (value) { return '<option value="' + value + '"' + (copy.status === value ? ' selected' : '') + '>' + value.charAt(0).toUpperCase() + value.slice(1) + '</option>'; }).join('') + '</select>';

                    return '<tr><td><span class="dash-cell-title">' + esc(copy.accession_no) + '</span></td><td>' + (copy.borrower ? esc(copy.borrower) + '<span class="dash-cell-sub">Due ' + shortDate(copy.due_on) + '</span>' : '<span class="dash-muted">—</span>') + '</td><td>' + status + '</td></tr>';
                }).join('') || '<tr><td colspan="3" class="dash-muted">No copies yet.</td></tr>';
                copiesDialog.showModal();
            }).catch(function (envelope) { ui.toast((envelope && envelope.message) || 'Could not load the copies.', 'error'); });
        };

        const mounted = kit.mount({
            root: booksRoot,
            modal: document.getElementById('bookModal'),
            order: [[0, 'asc']],
            titles: { create: 'Add book', edit: 'Edit book' },
            searchPlaceholder: 'Search title, author or ISBN…',
            emptyText: 'No books yet.',
            nameOf: function (row) { return row.title; },
            onView: openCopies,
            columns: [
                { data: 'title', render: display(function (value, row) { return '<span class="dash-cell-title">' + esc(value) + '</span><span class="dash-cell-sub">' + esc([row.author, row.isbn].filter(Boolean).join(' · ')) + '</span>'; }) },
                { data: 'category_name', name: 'category_id', searchable: false, className: 'dash-hide-md', defaultContent: '' },
                { data: 'shelf', className: 'dash-hide-md', defaultContent: '' },
                { data: 'available_count', searchable: false, orderable: false, className: 'dash-col-num', render: display(function (value, row) { return value + ' / ' + row.copies_count; }) },
                { data: 'author', visible: false },
                { data: 'isbn', visible: false }
            ],
            fill: function (form, row) {
                kit.fillForm(form, row ? Object.assign({}, row, { copies: '' }) : null, BOOK_FIELDS, { is_active: true, copies: 1 });
            },
            payload: function (form) {
                const body = kit.readForm(form, BOOK_FIELDS);
                body.copies = body.copies || 0;

                return body;
            }
        });

        kit.wireDialog(copiesDialog);

        copiesDialog.addEventListener('change', function (event) {
            const select = event.target.closest('[data-copy]');

            if (select) {
                ui.api('PUT', booksRoot.dataset.copyUrl + '/' + select.dataset.copy, { status: select.value })
                    .then(function (envelope) { ui.toast(envelope.message); mounted.refresh(); })
                    .catch(function (envelope) { ui.toast((envelope && envelope.message) || 'Could not update the copy.', 'error'); });
            }
        });
    }

    /* ---------- Issue & Return ---------- */

    const issueForm = document.querySelector('[data-issue-form]');
    const issuesRoot = document.querySelector('[data-library-issues]');

    if (issueForm && issuesRoot) {
        const search = issueForm.querySelector('[data-borrower-search]');
        const options = issueForm.querySelector('[data-borrower-list]');
        const statusFilter = issuesRoot.querySelector('[data-filter="status"]');
        let found = [];
        let timer = null;

        search.addEventListener('input', function () {
            const picked = found.find(function (person) { return person.label === search.value; });
            issueForm.elements.borrower_id.value = picked ? picked.id : '';

            if (picked || search.value.trim().length < 2) {
                return;
            }

            window.clearTimeout(timer);
            timer = window.setTimeout(function () {
                ui.api('GET', issueForm.dataset.borrowersUrl + '?type=' + issueForm.elements.borrower_type.value + '&q=' + encodeURIComponent(search.value.trim())).then(function (envelope) {
                    found = envelope.data.map(function (person) { return { id: person.id, label: person.name + (person.code ? ' — ' + person.code : '') }; });
                    options.innerHTML = found.map(function (person) { return '<option value="' + esc(person.label) + '"></option>'; }).join('');
                }).catch(function () {});
            }, 250);
        });

        issueForm.elements.borrower_type.addEventListener('change', function () {
            search.value = '';
            issueForm.elements.borrower_id.value = '';
            options.innerHTML = '';
        });

        const mounted = kit.mount({
            root: issuesRoot,
            order: [],
            searchPlaceholder: 'Search book, accession no or borrower…',
            emptyText: 'No loans here.',
            editable: false,
            deletable: false,
            nameOf: function (row) { return row.title; },
            params: function () { return { status: statusFilter.value }; },
            columns: [
                { data: 'title', name: 'title', render: display(function (value, row) { return '<span class="dash-cell-title">' + esc(value) + '</span><span class="dash-cell-sub">' + esc(row.accession_no) + '</span>'; }) },
                { data: 'borrower_name', name: 'borrower_name', orderable: false, render: display(function (value, row) { return esc(value) + '<span class="dash-cell-sub">' + esc((row.borrower_type === 'student' ? 'Student' : 'Staff') + (row.borrower_code ? ' · ' + row.borrower_code : '')) + '</span>'; }) },
                { data: 'issued_on', name: 'library_issues.issued_on', searchable: false, className: 'dash-hide-md', render: display(shortDate) },
                { data: 'due_on', name: 'library_issues.due_on', searchable: false, render: display(function (value, row) { return shortDate(value) + (row.status === 'issued' && value < todayIso() ? ' <span class="dash-badge dash-badge-danger">Overdue</span>' : ''); }) },
                { data: 'status', name: 'library_issues.status', searchable: false, render: display(function (value, row) {
                    if (value === 'issued') {
                        return '<button type="button" class="dash-btn dash-btn-ghost" data-return="' + row.id + '">Return</button> <button type="button" class="dash-link-btn" data-lost="' + row.id + '">Lost</button>';
                    }

                    return '<span class="dash-badge ' + (value === 'lost' ? 'dash-badge-danger' : 'dash-badge-ok') + '">' + (value === 'lost' ? 'Lost' : 'Returned ' + shortDate(row.returned_on)) + '</span>' + (row.fine_amount > 0 ? '<span class="dash-cell-sub">Fine Rs ' + Math.round(row.fine_amount / 100).toLocaleString('en-PK') + '</span>' : '');
                }) },
                { data: 'accession_no', name: 'accession_no', visible: false }
            ]
        });

        statusFilter.addEventListener('change', mounted.refresh);

        issueForm.addEventListener('submit', function (event) {
            event.preventDefault();

            if (!issueForm.elements.borrower_id.value) {
                ui.toast('Pick the borrower from the list.', 'error');
                search.focus();

                return;
            }

            kit.submitForm(issueForm, 'POST', issueForm.dataset.url, {
                accession_no: issueForm.elements.accession_no.value.trim(),
                borrower_type: issueForm.elements.borrower_type.value,
                borrower_id: Number(issueForm.elements.borrower_id.value),
                due_on: issueForm.elements.due_on.value || null
            }).then(function () {
                issueForm.elements.accession_no.value = '';
                issueForm.elements.accession_no.focus();
                mounted.refresh();
            }).catch(function () {});
        });

        issuesRoot.addEventListener('click', function (event) {
            const back = event.target.closest('[data-return]');
            const lost = event.target.closest('[data-lost]');
            const id = back ? back.dataset.return : (lost ? lost.dataset.lost : null);

            if (!id) {
                return;
            }

            ui.api('POST', issuesRoot.dataset.resourceUrl + '/' + id + '/return', { lost: Boolean(lost) })
                .then(function (envelope) { ui.toast(envelope.message); mounted.refresh(); })
                .catch(function (envelope) { ui.toast((envelope && envelope.message) || 'Could not save.', 'error'); });
        });
    }
})();
