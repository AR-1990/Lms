/**
 * Card designer: drag items from the palette onto the card, move them with the mouse or arrow
 * keys, resize from the corner handle, edit style on the right, then save the layout.
 * Positions are kept in millimetres; the canvas draws them at `scale` pixels per mm.
 */
(function () {
    'use strict';

    const ui = window.PinnacleDashboard;
    const root = document.querySelector('[data-designer]');

    if (!ui || !root) {
        return;
    }

    const canvas = root.querySelector('[data-canvas]');
    const propsForm = root.querySelector('[data-props-form]');
    const PT_TO_MM = 0.3528;
    const DEFAULTS = {
        photo: { width_mm: 20, height_mm: 24 },
        qr_code: { width_mm: 15, height_mm: 15 },
        school_logo: { width_mm: 9, height_mm: 9 },
        date_sheet: { width_mm: 80, height_mm: 25 },
        signature_line: { width_mm: 24, custom_text: 'Principal', align: 'center' },
        address: { width_mm: 40, height_mm: 10 },
        instructions: { width_mm: 80, height_mm: 30 }
    };
    const samples = {};
    let template = null;
    let fields = [];
    let side = 'front';
    let selected = null;
    let scale = 6;

    root.querySelectorAll('[data-palette-key]').forEach(function (chip) {
        samples[chip.dataset.paletteKey] = chip.dataset.sample;
    });

    function sampleText(field) {
        if (field.field_key === 'custom_text' || field.field_key === 'signature_line') {
            return field.custom_text || (field.field_key === 'signature_line' ? 'Principal' : 'Your text');
        }

        return samples[field.field_key] || field.field_key.replace(/_/g, ' ');
    }

    function clamp(value, min, max) {
        return Math.min(Math.max(value, min), max);
    }

    function round(value) {
        return Math.round(value * 2) / 2;
    }

    function drawFrame() {
        const front = side === 'front';
        const foot = template.height_mm * (front ? 0.07 : 0.045);

        canvas.style.width = template.width_mm * scale + 'px';
        canvas.style.height = template.height_mm * scale + 'px';
        canvas.style.color = template.text_color;
        canvas.innerHTML = '<img class="dash-designer-decor" src="' + (front ? template.decor_front : template.decor_back) + '" alt="">'
            + (!front && template.back_text ? '<div class="dash-designer-backtext" style="bottom:' + (foot * 1.7 + 1.2) * scale + 'px">' + escapeHtml(template.back_text) + '</div>' : '');
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value;

        return div.innerHTML;
    }

    function render() {
        drawFrame();

        fields.forEach(function (field, index) {
            if (field.side !== side) {
                return;
            }

            const item = document.createElement('div');
            const visual = ['photo', 'qr_code', 'school_logo', 'date_sheet'].indexOf(field.field_key) !== -1;

            item.className = 'dash-designer-item dash-designer-' + field.field_key + (selected === index ? ' is-selected' : '');
            item.dataset.index = index;
            item.style.left = field.x_mm * scale + 'px';
            item.style.top = field.y_mm * scale + 'px';
            item.style.width = field.width_mm ? field.width_mm * scale + 'px' : 'auto';
            item.style.height = field.height_mm ? field.height_mm * scale + 'px' : 'auto';
            item.style.fontSize = (field.font_size || 8) * PT_TO_MM * scale + 'px';
            item.style.fontWeight = field.font_weight || 'normal';
            item.style.textAlign = field.align || 'left';

            if (field.color) {
                item.style.color = field.color;
            }

            item.innerHTML = (visual ? '<span>' + escapeHtml({ photo: 'Photo', qr_code: 'QR', school_logo: 'Logo' }[field.field_key] || 'Date sheet') + '</span>' : escapeHtml(sampleText(field)))
                + '<span class="dash-designer-handle" data-handle aria-hidden="true"></span>';
            canvas.appendChild(item);
        });

        renderProps();
    }

    function renderProps() {
        const field = selected === null ? null : fields[selected];

        root.querySelector('[data-props-empty]').hidden = Boolean(field);
        propsForm.hidden = !field;

        if (!field) {
            return;
        }

        propsForm.querySelectorAll('[data-prop]').forEach(function (input) {
            const value = field[input.dataset.prop];

            input.value = value === null || value === undefined ? (input.dataset.prop === 'color' ? '#1f2937' : '') : value;
        });
        root.querySelector('[data-prop-text-wrap]').hidden = ['custom_text', 'signature_line'].indexOf(field.field_key) === -1;
    }

    /* ---------- Drop new items from the palette ---------- */

    root.querySelectorAll('[data-palette-key]').forEach(function (chip) {
        chip.addEventListener('dragstart', function (event) {
            event.dataTransfer.setData('text/plain', chip.dataset.paletteKey);
            event.dataTransfer.effectAllowed = 'copy';
        });

        // Keyboard / touch: clicking a chip drops the item near the top-left.
        chip.addEventListener('click', function () {
            addField(chip.dataset.paletteKey, 4, template.height_mm * 0.25);
        });
    });

    canvas.addEventListener('dragover', function (event) {
        event.preventDefault();
        canvas.classList.add('is-dropping');
    });

    canvas.addEventListener('dragleave', function () {
        canvas.classList.remove('is-dropping');
    });

    canvas.addEventListener('drop', function (event) {
        event.preventDefault();
        canvas.classList.remove('is-dropping');

        const rect = canvas.getBoundingClientRect();

        addField(event.dataTransfer.getData('text/plain'), (event.clientX - rect.left) / scale, (event.clientY - rect.top) / scale);
    });

    function addField(key, x, y) {
        if (!key) {
            return;
        }

        const field = Object.assign({ side: side, field_key: key, custom_text: key === 'custom_text' ? 'Your text' : null, x_mm: 0, y_mm: 0, width_mm: Math.min(30, template.width_mm - 4), height_mm: null, font_size: 7, font_weight: 'normal', color: null, align: 'left' }, DEFAULTS[key] || {});

        field.x_mm = round(clamp(x, 0, template.width_mm - (field.width_mm || 5)));
        field.y_mm = round(clamp(y, 0, template.height_mm - (field.height_mm || 4)));
        fields.push(field);
        selected = fields.length - 1;
        render();
    }

    /* ---------- Move and resize with the pointer ---------- */

    canvas.addEventListener('pointerdown', function (event) {
        const item = event.target.closest('.dash-designer-item');

        if (!item) {
            selected = null;
            render();

            return;
        }

        const index = Number(item.dataset.index);
        const field = fields[index];
        const resizing = Boolean(event.target.closest('[data-handle]'));
        const start = { x: event.clientX, y: event.clientY, fx: field.x_mm, fy: field.y_mm, fw: field.width_mm || item.offsetWidth / scale, fh: field.height_mm || item.offsetHeight / scale };

        selected = index;
        render();
        canvas.setPointerCapture(event.pointerId);
        event.preventDefault();

        const move = function (moveEvent) {
            const dx = (moveEvent.clientX - start.x) / scale;
            const dy = (moveEvent.clientY - start.y) / scale;

            if (resizing) {
                field.width_mm = round(clamp(start.fw + dx, 2, template.width_mm - field.x_mm));
                field.height_mm = ['photo', 'qr_code', 'school_logo', 'date_sheet', 'address', 'instructions'].indexOf(field.field_key) !== -1 ? round(clamp(start.fh + dy, 2, template.height_mm - field.y_mm)) : field.height_mm;
            } else {
                field.x_mm = round(clamp(start.fx + dx, 0, template.width_mm - (field.width_mm || 2)));
                field.y_mm = round(clamp(start.fy + dy, 0, template.height_mm - 2));
            }

            render();
        };

        const stop = function () {
            canvas.removeEventListener('pointermove', move);
            canvas.removeEventListener('pointerup', stop);
            canvas.removeEventListener('pointercancel', stop);
        };

        canvas.addEventListener('pointermove', move);
        canvas.addEventListener('pointerup', stop);
        canvas.addEventListener('pointercancel', stop);
    });

    canvas.addEventListener('keydown', function (event) {
        const steps = { ArrowLeft: [-0.5, 0], ArrowRight: [0.5, 0], ArrowUp: [0, -0.5], ArrowDown: [0, 0.5] };

        if (selected === null) {
            return;
        }

        if (event.key === 'Delete' || event.key === 'Backspace') {
            removeSelected();
            event.preventDefault();

            return;
        }

        if (steps[event.key]) {
            fields[selected].x_mm = round(clamp(fields[selected].x_mm + steps[event.key][0], 0, template.width_mm - 1));
            fields[selected].y_mm = round(clamp(fields[selected].y_mm + steps[event.key][1], 0, template.height_mm - 1));
            render();
            event.preventDefault();
        }
    });

    /* ---------- Properties panel ---------- */

    propsForm.addEventListener('input', function (event) {
        const input = event.target.closest('[data-prop]');

        if (!input || selected === null) {
            return;
        }

        const name = input.dataset.prop;
        const numeric = ['x_mm', 'y_mm', 'width_mm', 'height_mm', 'font_size'].indexOf(name) !== -1;

        fields[selected][name] = input.value === '' ? null : (numeric ? Number(input.value) : input.value);

        const keepFocus = document.activeElement === input;
        render();

        if (keepFocus) {
            propsForm.querySelector('[data-prop="' + name + '"]').focus();
        }
    });

    function removeSelected() {
        fields.splice(selected, 1);
        selected = null;
        render();
    }

    root.querySelector('[data-remove-item]').addEventListener('click', removeSelected);

    root.querySelectorAll('[data-side]').forEach(function (button) {
        button.addEventListener('click', function () {
            side = button.dataset.side;
            selected = null;
            root.querySelectorAll('[data-side]').forEach(function (other) {
                const active = other === button;

                other.classList.toggle('dash-btn-primary', active);
                other.classList.toggle('dash-btn-ghost', !active);
                other.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            render();
        });
    });

    /* ---------- Load, save, preview ---------- */

    function payload() {
        return {
            name: template.name,
            card_type: template.card_type,
            size_key: template.size_key,
            theme: template.theme,
            primary_color: template.primary_color,
            text_color: template.text_color,
            show_qr: template.show_qr,
            back_text: template.back_text,
            is_default: template.is_default,
            is_active: template.is_active,
            fields: fields.map(function (field) {
                return {
                    side: field.side, field_key: field.field_key, custom_text: field.custom_text, x_mm: field.x_mm, y_mm: field.y_mm,
                    width_mm: field.width_mm, height_mm: field.height_mm, font_size: field.font_size, font_weight: field.font_weight, color: field.color, align: field.align
                };
            })
        };
    }

    function save() {
        return ui.api('PUT', root.dataset.templateUrl, payload()).then(function (envelope) {
            ui.toast('Design saved.');
            template = envelope.data;

            return envelope;
        }).catch(function (envelope) {
            const fieldsErrors = envelope && envelope.errors && envelope.errors.fields;
            ui.toast(fieldsErrors ? fieldsErrors[Object.keys(fieldsErrors)[0]][0] : (envelope.message || 'Could not save.'), 'error');

            return Promise.reject(envelope);
        });
    }

    document.querySelector('[data-save-design]').addEventListener('click', save);

    document.querySelector('[data-preview]').addEventListener('click', function () {
        save().then(function () {
            ui.openFile(root.dataset.templateUrl + '/preview', 'Preparing the preview…').catch(function () {});
        }).catch(function () {});
    });

    const printCopies = document.querySelector('[data-print-copies]');

    if (printCopies) {
        printCopies.addEventListener('click', function () {
            const copies = Number(document.querySelector('[data-copies]').value);

            if (copies > 0) {
                save().then(function () {
                    ui.openFile(root.dataset.templateUrl + '/print?copies=' + Math.min(copies, 300), 'Generating ' + copies + ' cards…').catch(function () {});
                }).catch(function () {});
            }
        });
    }

    ui.api('GET', root.dataset.templateUrl).then(function (envelope) {
        template = envelope.data;
        fields = template.fields.map(function (field) { return Object.assign({}, field); });
        scale = Math.min(7, Math.max(2.4, 380 / Math.max(template.width_mm, template.height_mm * 0.75)));
        render();
    });
})();
