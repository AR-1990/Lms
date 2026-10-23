/**
 * Website page editor: pick a page, edit its banner and SEO text, the text and picture of each block,
 * and the cards inside a block (with their bullet points). Data: /api/erp/website/pages|sections|items.
 */
(function () {
    'use strict';

    const root = document.querySelector('[data-cms]');
    const ui = window.PinnacleDashboard;
    const kit = window.PinnacleResource;

    if (!root || !ui || !kit) {
        return;
    }

    const api = root.dataset.api;
    const pageForm = root.querySelector('[data-page-form]');
    const sectionsBox = root.querySelector('[data-cms-sections]');
    const template = document.querySelector('[data-section-template]');
    const itemModal = document.getElementById('cmsItemModal');
    const itemForm = itemModal.querySelector('form');
    const childrenList = itemForm.querySelector('[data-children]');
    const PAGE_SPEC = { name: 'text', meta_title: 'text', meta_description: 'text', hero_pill: 'text', hero_title: 'text', hero_text: 'text', is_published: 'bool' };
    const SECTION_SPEC = { subtitle: 'text', title: 'text', description: 'text', body: 'text', badge: 'text', image_caption: 'text', image_note: 'text', cta_label: 'text', cta_url: 'text', cta2_label: 'text', cta2_url: 'text', is_active: 'bool' };
    const ITEM_SPEC = { title: 'text', subtitle: 'text', body: 'text', badge: 'text', value: 'text', icon: 'text', tone: 'text', link_label: 'text', link_url: 'text', link2_label: 'text', link2_url: 'text', sort_order: 'int', is_active: 'bool' };
    let current = null;
    let editing = { section: null, item: null, removed: [] };

    function esc(value) {
        const div = document.createElement('div');
        div.textContent = value === null || value === undefined ? '' : String(value);

        return div.innerHTML;
    }

    function label(key) {
        return key.replace(/_/g, ' ').replace(/\b\w/g, function (letter) { return letter.toUpperCase(); }).replace('Cta', 'Call to action');
    }

    function showImage(box, url) {
        const preview = box.querySelector('[data-image-preview]');

        preview.hidden = !url;

        if (url) {
            preview.src = url;
        }
    }

    function wireImage(box, idOf) {
        box.querySelector('[data-image-input]').addEventListener('change', function (event) {
            const file = event.target.files[0];
            const id = idOf();

            if (!file || !id) {
                return;
            }

            const body = new FormData();
            body.append('image', file);
            ui.api('POST', api + '/images/' + box.dataset.imageKind + '/' + id, body).then(function (envelope) {
                ui.toast(envelope.message);
                showImage(box, envelope.data.url);
            }).catch(function (envelope) { ui.toast(kit.messageOf(envelope, 'The picture was not uploaded.'), 'error'); });
            event.target.value = '';
        });
    }

    /* ---------- Pages ---------- */

    function loadPages() {
        return ui.api('GET', api + '/pages').then(function (envelope) {
            root.querySelector('[data-cms-pages]').innerHTML = envelope.data.map(function (page) {
                return '<button type="button" class="dash-cms-page-btn" data-page="' + page.id + '">' + esc(page.name) + (page.is_published ? '' : ' <span class="dash-muted">(hidden)</span>') + '<span class="dash-muted">' + esc(page.slug) + '</span></button>';
            }).join('');

            if (envelope.data.length) {
                openPage(envelope.data[0].id);
            }
        });
    }

    function openPage(id) {
        root.querySelectorAll('[data-page]').forEach(function (button) { button.classList.toggle('is-active', Number(button.dataset.page) === id); });

        return ui.api('GET', api + '/pages/' + id).then(function (envelope) {
            current = envelope.data;
            kit.clearErrors(pageForm);
            kit.fillForm(pageForm, current, PAGE_SPEC, {});
            root.querySelector('[data-page-name]').textContent = current.name;
            root.querySelector('[data-cms-page]').hidden = false;
            pageForm.querySelector('[data-image-kind="page"]').hidden = current.slug === 'site' || current.slug === 'home';
            showImage(pageForm.querySelector('[data-image-kind="page"]'), current.hero_image_url);
            renderSections();
        }).catch(function (envelope) { ui.toast(kit.messageOf(envelope, 'The page could not be opened.'), 'error'); });
    }

    root.querySelector('[data-cms-pages]').addEventListener('click', function (event) {
        const button = event.target.closest('[data-page]');

        if (button) {
            openPage(Number(button.dataset.page));
        }
    });

    pageForm.addEventListener('submit', function (event) {
        event.preventDefault();
        kit.submitForm(pageForm, 'PUT', api + '/pages/' + current.id, kit.readForm(pageForm, PAGE_SPEC)).then(loadPagesQuietly).catch(function () {});
    });

    wireImage(pageForm.querySelector('[data-image-kind="page"]'), function () { return current && current.id; });

    function loadPagesQuietly() {
        return ui.api('GET', api + '/pages').then(function (envelope) {
            envelope.data.forEach(function (page) {
                const button = root.querySelector('[data-page="' + page.id + '"]');

                if (button) {
                    button.firstChild.textContent = page.name;
                }
            });
        }).catch(function () {});
    }

    /* ---------- Blocks ---------- */

    function itemRow(item) {
        return '<li class="dash-cms-item' + (item.is_active ? '' : ' is-off') + '">'
            + (item.image_url ? '<img src="' + esc(item.image_url) + '" alt="">' : '<span class="dash-cms-item-icon">' + esc((item.icon || item.value || '•').slice(0, 3)) + '</span>')
            + '<div><strong>' + esc(item.title || item.subtitle || item.badge || 'Card') + '</strong><span class="dash-muted">'
            + esc([item.badge, item.children.length ? item.children.length + ' point(s)' : null, item.is_active ? null : 'hidden'].filter(Boolean).join(' · ')) + '</span></div>'
            + '<span class="dash-cms-item-actions"><button type="button" class="dash-link-btn" data-edit-item="' + item.id + '">Edit</button>'
            + '<button type="button" class="dash-link-btn" data-delete-item="' + item.id + '">Remove</button></span></li>';
    }

    function renderSections() {
        sectionsBox.innerHTML = '';

        current.sections.forEach(function (section) {
            const node = template.content.firstElementChild.cloneNode(true);
            const form = node.querySelector('form');

            node.dataset.section = section.id;
            node.querySelector('[data-section-title]').textContent = section.title || label(section.section_key);
            node.querySelector('[data-section-key]').textContent = label(section.section_key) + ' block';
            kit.fillForm(form, section, SECTION_SPEC, {});
            showImage(node.querySelector('[data-image-kind="section"]'), section.image_url);
            node.querySelector('[data-item-list]').innerHTML = section.items.length ? section.items.map(itemRow).join('') : '<li class="dash-muted">No cards in this block.</li>';

            form.addEventListener('submit', function (event) {
                event.preventDefault();
                kit.submitForm(form, 'PUT', api + '/sections/' + section.id, kit.readForm(form, SECTION_SPEC)).catch(function () {});
            });
            wireImage(node.querySelector('[data-image-kind="section"]'), function () { return section.id; });
            sectionsBox.appendChild(node);
        });
    }

    function findItem(id) {
        let found = null;

        current.sections.forEach(function (section) {
            section.items.forEach(function (item) {
                if (item.id === id) {
                    found = { section: section, item: item };
                }
            });
        });

        return found;
    }

    sectionsBox.addEventListener('click', function (event) {
        const add = event.target.closest('[data-add-item]');
        const edit = event.target.closest('[data-edit-item]');
        const remove = event.target.closest('[data-delete-item]');

        if (add) {
            const section = current.sections.find(function (entry) { return entry.id === Number(add.closest('[data-section]').dataset.section); });

            openItem(section, null);
        }

        if (edit) {
            const found = findItem(Number(edit.dataset.editItem));

            openItem(found.section, found.item);
        }

        if (remove) {
            const found = findItem(Number(remove.dataset.deleteItem));

            ui.eraseRecord(found.item.title || 'this card', remove.closest('li'), api + '/items/' + found.item.id).then(function (result) {
                if (result) {
                    openPage(current.id);
                }
            });
        }
    });

    /* ---------- Card popup (with bullet points) ---------- */

    function childRow(child) {
        return '<li data-child="' + (child.id || '') + '"><input type="text" data-child-title placeholder="Point" value="' + esc(child.title) + '" aria-label="Point">'
            + '<input type="text" data-child-body placeholder="Detail (optional)" value="' + esc(child.body) + '" aria-label="Detail">'
            + '<button type="button" class="dash-icon-btn" data-remove-child aria-label="Remove point">×</button></li>';
    }

    function openItem(section, item) {
        editing = { section: section, item: item, removed: [] };
        itemForm.reset();
        kit.clearErrors(itemForm);
        kit.fillForm(itemForm, item, ITEM_SPEC, { is_active: true });
        itemModal.querySelector('[data-item-title]').textContent = (item ? 'Edit card' : 'New card') + ' · ' + (section.title || label(section.section_key));
        itemForm.querySelector('[data-children-box]').hidden = false;
        childrenList.innerHTML = (item ? item.children : []).map(childRow).join('');
        itemForm.querySelector('[data-image-kind="item"]').hidden = !item;
        showImage(itemForm.querySelector('[data-image-kind="item"]'), item ? item.image_url : null);
        itemModal.showModal();
    }

    itemForm.querySelector('[data-add-child]').addEventListener('click', function () {
        childrenList.insertAdjacentHTML('beforeend', childRow({ title: '', body: '' }));
        childrenList.lastElementChild.querySelector('input').focus();
    });

    childrenList.addEventListener('click', function (event) {
        const remove = event.target.closest('[data-remove-child]');

        if (remove) {
            const row = remove.closest('li');

            if (row.dataset.child) {
                editing.removed.push(Number(row.dataset.child));
            }

            row.remove();
        }
    });

    wireImage(itemForm.querySelector('[data-image-kind="item"]'), function () { return editing.item && editing.item.id; });

    itemForm.addEventListener('submit', function (event) {
        event.preventDefault();

        const body = kit.readForm(itemForm, ITEM_SPEC);
        const request = editing.item
            ? ui.api('PUT', api + '/items/' + editing.item.id, body)
            : ui.api('POST', api + '/sections/' + editing.section.id + '/items', body);

        request.then(function (envelope) {
            const parentId = envelope.data.id;
            const saves = Array.prototype.map.call(childrenList.querySelectorAll('li'), function (row, index) {
                const child = { title: row.querySelector('[data-child-title]').value.trim() || null, body: row.querySelector('[data-child-body]').value.trim() || null, parent_id: parentId, sort_order: index, is_active: true };

                if (!child.title && !child.body) {
                    return Promise.resolve();
                }

                return row.dataset.child ? ui.api('PUT', api + '/items/' + row.dataset.child, child) : ui.api('POST', api + '/sections/' + editing.section.id + '/items', child);
            }).concat(editing.removed.map(function (id) { return ui.api('DELETE', api + '/items/' + id, { reason: 'Removed from a website card' }); }));

            return Promise.all(saves).then(function () {
                ui.toast(envelope.message);
                itemModal.close();
                openPage(current.id);
            });
        }).catch(function (envelope) { kit.showErrors(itemForm, envelope); });
    });

    itemModal.querySelectorAll('[data-modal-close]').forEach(function (button) {
        button.addEventListener('click', function () { itemModal.close(); });
    });

    loadPages().catch(function () { ui.toast('Pages could not be loaded.', 'error'); });
})();
