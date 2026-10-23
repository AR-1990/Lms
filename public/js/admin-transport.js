/**
 * Transport screens: routes with their stops (and the printable route sheet), riders (student → stop) and
 * vehicles with paper expiry badges. Fees travel in paisa; forms take rupees.
 */
(function () {
    'use strict';

    const root = document.querySelector('[data-transport]');
    const ui = window.PinnacleDashboard;
    const kit = window.PinnacleResource;

    if (!root || !ui || !kit || !window.PinnacleGrid) {
        return;
    }

    const esc = window.PinnacleGrid.escape;
    const money = window.PinnacleGrid.money;
    const dateFormat = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    const today = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);

    function display(render) {
        return function (value, type, row) {
            return type === 'display' ? render(value, row) : value;
        };
    }

    function day(value) {
        return value ? dateFormat.format(new Date(value)) : '—';
    }

    function titleCell(title, sub) {
        return '<strong>' + esc(title || '—') + '</strong>' + (sub ? '<span class="dash-muted dash-cell-sub">' + esc(sub) + '</span>' : '');
    }

    function active(value) {
        return value ? '<span class="dash-badge dash-badge-ok">Active</span>' : '<span class="dash-badge dash-badge-system">Inactive</span>';
    }

    function rupees(paisa) {
        return paisa === null || paisa === undefined ? '' : (Number(paisa) / 100).toFixed(2);
    }

    function filterParams() {
        const params = {};

        root.querySelectorAll('[data-filter]').forEach(function (select) {
            params[select.dataset.filter] = select.value;
        });

        return params;
    }

    /* ---------- Routes and stops ---------- */

    function routes() {
        const SPEC = { name: 'text', code: 'text', vehicle_id: 'int', monthly_fee: 'money', driver_employee_id: 'int', attendant_employee_id: 'int', fee_head_id: 'int', is_active: 'bool' };
        const modal = document.getElementById('routeModal');
        const stopRows = modal.querySelector('[data-stop-rows]');
        const areaOptions = modal.querySelector('[data-area-options]').innerHTML;
        const view = document.getElementById('routeView');

        function addStop(stop) {
            stopRows.insertAdjacentHTML('beforeend', '<tr data-stop="' + esc(stop.id || '') + '">'
                + '<td><input type="text" data-stop-name value="' + esc(stop.name || '') + '" placeholder="Stop name" aria-label="Stop name"></td>'
                + '<td><select data-stop-area aria-label="Area">' + areaOptions + '</select></td>'
                + '<td><input type="time" data-stop-pickup value="' + esc(stop.pickup_time || '') + '" aria-label="Pickup"></td>'
                + '<td><input type="time" data-stop-drop value="' + esc(stop.drop_time || '') + '" aria-label="Drop"></td>'
                + '<td><input type="number" step="0.01" min="0" data-stop-fee value="' + esc(rupees(stop.fee_override)) + '" aria-label="Own fee"></td>'
                + '<td><button type="button" class="dash-icon-btn" data-stop-remove aria-label="Remove stop">×</button></td></tr>');

            if (stop.area_id) {
                stopRows.lastElementChild.querySelector('[data-stop-area]').value = String(stop.area_id);
            }
        }

        modal.querySelector('[data-add-stop]').addEventListener('click', function () {
            addStop({});
            stopRows.lastElementChild.querySelector('input').focus();
        });

        stopRows.addEventListener('click', function (event) {
            if (event.target.closest('[data-stop-remove]')) {
                event.target.closest('tr').remove();
            }
        });

        function readStops() {
            return Array.prototype.map.call(stopRows.querySelectorAll('tr'), function (row) {
                const fee = row.querySelector('[data-stop-fee]').value;

                return {
                    id: row.dataset.stop ? Number(row.dataset.stop) : null,
                    name: row.querySelector('[data-stop-name]').value.trim(),
                    area_id: row.querySelector('[data-stop-area]').value ? Number(row.querySelector('[data-stop-area]').value) : null,
                    pickup_time: row.querySelector('[data-stop-pickup]').value || null,
                    drop_time: row.querySelector('[data-stop-drop]').value || null,
                    fee_override: fee === '' ? null : Math.round(parseFloat(fee) * 100)
                };
            }).filter(function (stop) { return stop.name !== ''; });
        }

        kit.mount({
            root: root,
            modal: modal,
            order: [[0, 'asc']],
            titles: { create: 'Add route', edit: 'Edit route' },
            emptyText: 'No routes yet.',
            nameOf: function (row) { return row.name; },
            canDelete: function (row) { return row.riders_count > 0 ? 'Students ride this route' : true; },
            onView: function (row) {
                ui.api('GET', root.dataset.resourceUrl + '/' + row.id).then(function (envelope) {
                    const route = envelope.data;

                    view.querySelector('[data-view-title]').textContent = route.name;
                    view.querySelector('[data-view-sub]').textContent = [route.vehicle_label, route.driver_name ? 'Driver ' + route.driver_name : null, route.attendant_name ? 'Attendant ' + route.attendant_name : null, route.riders + (route.capacity ? ' of ' + route.capacity : '') + ' riders'].filter(Boolean).join(' · ');
                    view.querySelector('[data-view-body]').innerHTML = '<table class="dash-table"><thead><tr><th>#</th><th>Stop</th><th>Pickup</th><th>Drop</th><th>Fee</th><th>Riders</th></tr></thead><tbody>'
                        + route.stops.map(function (stop, index) {
                            return '<tr><td>' + (index + 1) + '</td><td>' + titleCell(stop.name, stop.area_name) + '</td><td>' + esc(stop.pickup_time || '—') + '</td><td>' + esc(stop.drop_time || '—') + '</td><td>' + esc(money(stop.fee_override === null ? route.monthly_fee : stop.fee_override)) + '</td><td>' + esc(String(stop.riders)) + '</td></tr>';
                        }).join('') + '</tbody></table>';
                    view.querySelector('[data-view-sheet]').href = root.dataset.sheetUrl + '/' + route.id + '/sheet';
                    view.querySelectorAll('[data-modal-close]').forEach(function (button) { button.onclick = function () { view.close(); }; });
                    view.showModal();
                }).catch(function (envelope) { ui.toast(kit.messageOf(envelope, 'The route could not be opened.'), 'error'); });
            },
            loadRow: function (row) { return ui.api('GET', root.dataset.resourceUrl + '/' + row.id).then(function (envelope) { return envelope.data; }); },
            columns: [
                { data: 'name', render: display(function (value, row) { return titleCell(value, row.code); }) },
                { data: 'vehicle_label', orderable: false, defaultContent: '—' },
                { data: 'driver_name', orderable: false, render: display(function (value, row) { return titleCell(value || '—', row.driver_phone); }) },
                { data: 'stops_count', searchable: false },
                { data: 'riders_count', searchable: false, render: display(function (value, row) { return '<span class="dash-badge ' + (row.capacity && value >= row.capacity ? 'dash-badge-danger' : 'dash-badge-ok') + '">' + esc(value + (row.capacity ? ' / ' + row.capacity : '')) + '</span>'; }) },
                { data: 'monthly_fee', searchable: false, render: display(function (value) { return esc(money(value)); }) },
                { data: 'is_active', render: display(active) }
            ],
            fill: function (form, row) {
                kit.fillForm(form, row, SPEC, { is_active: true });
                stopRows.innerHTML = '';
                (row ? row.stops : [{}]).forEach(addStop);
            },
            payload: function (form) { return Object.assign(kit.readForm(form, SPEC), { stops: readStops() }); }
        });
    }

    /* ---------- Riders ---------- */

    function riders() {
        const modal = document.getElementById('riderModal');
        const form = modal.querySelector('form');
        const search = form.querySelector('[data-student-search]');
        const results = form.querySelector('[data-student-results]');
        const stopSelect = form.querySelector('[data-stop-select]');
        let timer = null;

        ui.api('GET', root.dataset.stopsUrl).then(function (envelope) {
            stopSelect.innerHTML = '<option value="">Choose a stop…</option>' + envelope.data.map(function (stop) {
                return '<option value="' + stop.id + '">' + esc(stop.name + ' · ' + money(stop.fee) + '/month') + '</option>';
            }).join('');
        }).catch(function () {});

        search.addEventListener('input', function () {
            form.elements.student_id.value = '';
            clearTimeout(timer);
            timer = setTimeout(function () {
                ui.api('GET', root.dataset.studentsUrl + '?term=' + encodeURIComponent(search.value.trim())).then(function (envelope) {
                    results.innerHTML = envelope.data.map(function (student) {
                        return '<li><button type="button" data-student="' + student.id + '" data-name="' + esc(student.name) + '">' + esc(student.name) + ' <span class="dash-muted">' + esc(student.detail || '') + '</span></button></li>';
                    }).join('') || '<li class="dash-muted">No student found.</li>';
                }).catch(function () {});
            }, 300);
        });

        results.addEventListener('click', function (event) {
            const pick = event.target.closest('[data-student]');

            if (pick) {
                form.elements.student_id.value = pick.dataset.student;
                search.value = pick.dataset.name;
                results.innerHTML = '';
            }
        });

        const mounted = kit.mount({
            root: root,
            modal: modal,
            order: [[4, 'desc']],
            titles: { create: 'Add rider', edit: 'Add rider' },
            emptyText: 'Nobody rides yet.',
            searchPlaceholder: 'Search student…',
            editable: false,
            deletable: false,
            params: filterParams,
            nameOf: function (row) { return row.student_name; },
            onView: function (row) {
                if (row.status !== 'active') {
                    ui.toast('This ride has ended.');

                    return;
                }

                const toDate = window.prompt('Last day ' + row.student_name + ' rides (YYYY-MM-DD):', today);

                if (toDate) {
                    ui.api('POST', root.dataset.resourceUrl + '/' + row.id + '/end', { to_date: toDate }).then(function (envelope) { ui.toast(envelope.message); mounted.refresh(); })
                        .catch(function (envelope) { ui.toast(kit.messageOf(envelope, 'Could not end the ride.'), 'error'); });
                }
            },
            columns: [
                { data: 'student_name', orderable: false, render: display(function (value, row) { return titleCell(value, row.admission_no); }) },
                { data: 'class_label', orderable: false, searchable: false, defaultContent: '—' },
                { data: 'route_name', orderable: false },
                { data: 'stop_name', orderable: false },
                { data: 'from_date', render: display(function (value, row) { return titleCell(day(value), row.to_date ? 'until ' + day(row.to_date) : null); }) },
                { data: 'fee', orderable: false, searchable: false, render: display(function (value) { return esc(money(value)); }) },
                { data: 'status', render: display(function (value) { return '<span class="dash-badge ' + (value === 'active' ? 'dash-badge-ok' : 'dash-badge-system') + '">' + (value === 'active' ? 'Riding' : 'Ended') + '</span>'; }) }
            ],
            fill: function (formElement) {
                kit.fillForm(formElement, null, { from_date: 'text', remarks: 'text' }, { from_date: today });
                formElement.elements.student_id.value = '';
                stopSelect.value = '';
                search.value = '';
                results.innerHTML = '';
            },
            payload: function (formElement) { return Object.assign(kit.readForm(formElement, { student_id: 'int', transport_stop_id: 'int', from_date: 'text', remarks: 'text' })); }
        });

        root.querySelectorAll('[data-filter]').forEach(function (select) {
            select.addEventListener('change', function () { mounted.grid.ajax.reload(); });
        });
    }

    /* ---------- Vehicles ---------- */

    function vehicles() {
        const SPEC = { registration_no: 'text', type_id: 'int', make_model: 'text', capacity: 'int', insurance_expiry: 'text', fitness_expiry: 'text', route_permit_expiry: 'text', is_active: 'bool' };

        kit.mount({
            root: root,
            modal: document.getElementById('vehicleModal'),
            order: [[0, 'asc']],
            titles: { create: 'Add vehicle', edit: 'Edit vehicle' },
            emptyText: 'No vehicles yet.',
            nameOf: function (row) { return row.registration_no; },
            canDelete: function (row) { return row.routes_count > 0 ? 'Runs a route' : true; },
            columns: [
                { data: 'registration_no', render: display(function (value, row) { return titleCell(value, row.make_model); }) },
                { data: 'type_name', orderable: false, defaultContent: '—' },
                { data: 'capacity', searchable: false },
                { data: 'insurance_expiry', searchable: false, orderable: false, render: display(function (value, row) {
                    return row.expiring.length
                        ? row.expiring.map(function (line) { return '<span class="dash-badge dash-badge-danger">' + esc(line) + '</span>'; }).join(' ')
                        : '<span class="dash-muted">Insurance ' + esc(day(row.insurance_expiry)) + ' · fitness ' + esc(day(row.fitness_expiry)) + '</span>';
                }) },
                { data: 'routes_count', searchable: false },
                { data: 'is_active', render: display(active) }
            ],
            fill: function (form, row) { kit.fillForm(form, row, SPEC, { is_active: true }); },
            payload: function (form) { return kit.readForm(form, SPEC); }
        });
    }

    ({ routes: routes, riders: riders, vehicles: vehicles }[root.dataset.transport] || function () {})();
})();
