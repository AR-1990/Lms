/**
 * Admin home: the money picture from /api/erp/admin/finance — headline figures with count-up, sparklines,
 * a collection-vs-spending chart (6 / 12 months, legend toggles, tooltips), pending-fee ageing donut, daily
 * collection area chart, bar lists and recent lists. Plain SVG; animations use transform, opacity and
 * clip-path only, and are skipped when the user prefers reduced motion.
 */
(function () {
    'use strict';

    const root = document.querySelector('[data-finance]');
    const ui = window.PinnacleDashboard;

    if (!root || !ui) {
        return;
    }

    const SVG = 'http://www.w3.org/2000/svg';
    const REDUCED = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const SERIES = [
        { key: 'collected', label: 'Fees collected', color: '#2e7d81' },
        { key: 'expenses', label: 'Expenses', color: '#d4962a' },
        { key: 'salaries', label: 'Salaries', color: '#b85c46' }
    ];
    const AGEING_COLORS = ['#c9a227', '#d4962a', '#b85c46', '#8b2f2f'];
    const dayFormat = new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'short' });
    let data = null;
    let hidden = {};
    let resizeTimer = null;

    /* ---------- Formatting ---------- */

    function esc(value) {
        const div = document.createElement('div');
        div.textContent = value === null || value === undefined ? '' : String(value);

        return div.innerHTML;
    }

    function rupees(paisa) {
        const value = Math.round((Number(paisa) || 0) / 100);

        return (value < 0 ? '−' : '') + 'Rs ' + Math.abs(value).toLocaleString('en-PK');
    }

    /* Short Pakistani form for axes and chips: 12.5K, 3.4 Lac, 1.2 Cr. */
    function short(paisa) {
        const value = (Number(paisa) || 0) / 100;
        const abs = Math.abs(value);
        const unit = abs >= 1e7 ? [1e7, ' Cr'] : abs >= 1e5 ? [1e5, ' Lac'] : abs >= 1e3 ? [1e3, 'K'] : [1, ''];

        return (value / unit[0]).toFixed(unit[0] === 1 || abs / unit[0] >= 100 ? 0 : 1).replace(/\.0$/, '') + unit[1];
    }

    function el(name, attributes, parent) {
        const node = document.createElementNS(SVG, name);
        Object.keys(attributes || {}).forEach(function (key) { node.setAttribute(key, attributes[key]); });

        if (parent) {
            parent.appendChild(node);
        }

        return node;
    }

    function empty(container, message, link) {
        container.innerHTML = '<div class="dash-fin-empty">' + esc(message) + (link ? ' <a href="' + esc(link[1]) + '">' + esc(link[0]) + '</a>' : '') + '</div>';
    }

    /* ---------- Count-up for headline numbers ---------- */

    function countUp(node, target, format) {
        if (REDUCED || !target) {
            node.textContent = format(target);

            return;
        }

        const start = performance.now();
        const step = function (now) {
            const progress = Math.min((now - start) / 900, 1);
            node.textContent = format(target * (1 - Math.pow(1 - progress, 3)));

            if (progress < 1) {
                requestAnimationFrame(step);
            }
        };

        requestAnimationFrame(step);
        // A hidden tab pauses animation frames; make sure the real figure still shows.
        setTimeout(function () { node.textContent = format(target); }, 1000);
    }

    function changeBadge(node, change, goodWhenUp) {
        if (change === null || change === undefined) {
            node.hidden = true;

            return;
        }

        const up = change >= 0;
        node.hidden = false;
        node.className = 'dash-fin-change ' + (up === goodWhenUp ? 'is-good' : 'is-bad');
        node.textContent = (up ? '▲ ' : '▼ ') + Math.abs(change) + '%';
        node.title = 'Compared with the same days last month';
    }

    function spark(svg, values, color) {
        svg.innerHTML = '';
        const max = Math.max.apply(null, values.concat([1]));
        const points = values.map(function (value, index) {
            return [values.length > 1 ? index * 100 / (values.length - 1) : 100, 28 - value / max * 26];
        });

        if (points.length < 2) {
            return;
        }

        const line = points.map(function (point, index) { return (index ? 'L' : 'M') + point[0].toFixed(2) + ' ' + point[1].toFixed(2); }).join(' ');
        el('path', { d: line + ' L100 30 L0 30 Z', fill: color, opacity: '0.14' }, svg);
        el('path', { d: line, fill: 'none', stroke: color, 'stroke-width': '1.6', 'vector-effect': 'non-scaling-stroke', 'stroke-linejoin': 'round' }, svg);
        svg.classList.add('is-drawn');
    }

    function kpis() {
        const k = data.kpis;
        const months = data.trend;
        const card = function (key) { return root.querySelector('[data-kpi="' + key + '"]'); };

        countUp(card('collected').querySelector('[data-kpi-value]'), k.collected_month, rupees);
        changeBadge(card('collected').querySelector('[data-kpi-change]'), k.collected_change, true);
        card('collected').querySelector('[data-kpi-foot]').textContent = k.recovery_month === null ? 'No challans issued this month yet' : k.recovery_month + '% of ' + rupees(k.billed_month) + ' billed this month';
        spark(card('collected').querySelector('[data-kpi-spark]'), months.map(function (month) { return month.collected; }), '#2e7d81');

        countUp(card('pending').querySelector('[data-kpi-value]'), k.outstanding, rupees);
        card('pending').querySelector('[data-kpi-foot]').textContent = k.overdue ? rupees(k.overdue) + ' overdue · ' + k.overdue_students + ' student' + (k.overdue_students === 1 ? '' : 's') : (k.open_challans ? k.open_challans + ' open challans, none overdue' : 'Nothing pending');
        card('pending').querySelector('[data-kpi-meter]').style.setProperty('--fill', k.outstanding ? Math.min(1, k.overdue / k.outstanding).toFixed(3) : 0);
        card('pending').querySelector('.dash-fin-meter').title = 'Share of pending fees that is overdue';

        countUp(card('expenses').querySelector('[data-kpi-value]'), k.expenses_month + k.salaries_month, rupees);
        changeBadge(card('expenses').querySelector('[data-kpi-change]'), k.expenses_change, false);
        card('expenses').querySelector('[data-kpi-foot]').textContent = rupees(k.expenses_month) + ' expenses · ' + rupees(k.salaries_month) + ' salaries' + (k.awaiting_payment ? ' · ' + rupees(k.awaiting_payment) + ' approved, unpaid' : '');
        spark(card('expenses').querySelector('[data-kpi-spark]'), months.map(function (month) { return month.expenses + month.salaries; }), '#b85c46');

        countUp(card('today').querySelector('[data-kpi-value]'), k.collected_today, rupees);
        card('today').querySelector('[data-kpi-foot]').textContent = k.receipts_today + ' receipt' + (k.receipts_today === 1 ? '' : 's') + ' today';
        const best = Math.max.apply(null, data.daily.map(function (day) { return day.collected; }).concat([1]));
        card('today').querySelector('[data-kpi-meter]').style.setProperty('--fill', Math.min(1, k.collected_today / best).toFixed(3));
        card('today').querySelector('.dash-fin-meter').title = 'Today against the best day this month';

        root.querySelector('[data-chips]').innerHTML = [
            ['Billed this month', rupees(k.billed_month)],
            ['Open challans', k.open_challans.toLocaleString('en-PK')],
            ['Net this month', rupees(k.collected_month - k.expenses_month - k.salaries_month)],
            ['Students', k.students.toLocaleString('en-PK')],
            ['Staff', k.staff.toLocaleString('en-PK')]
        ].map(function (chip, index) { return '<span class="dash-fin-chip" style="--i:' + index + '"><span>' + esc(chip[0]) + '</span><strong>' + esc(chip[1]) + '</strong></span>'; }).join('');
    }

    /* ---------- Collection vs spending ---------- */

    function trend() {
        const box = root.querySelector('[data-trend-chart]');
        const tooltip = document.createElement('div');
        const months = data.trend;
        const series = SERIES.filter(function (item) { return !hidden[item.key]; });
        const width = Math.max(box.clientWidth, 280);
        const height = width < 520 ? 220 : 280;
        const pad = { top: 16, right: 12, bottom: 30, left: 52 };
        const plotW = width - pad.left - pad.right;
        const plotH = height - pad.top - pad.bottom;
        const max = Math.max.apply(null, months.map(function (month) {
            return Math.max(month.collected * (hidden.collected ? 0 : 1), (hidden.expenses ? 0 : month.expenses) + (hidden.salaries ? 0 : month.salaries));
        }).concat([100]));
        const nice = Math.pow(10, Math.floor(Math.log10(max)));
        const top = Math.ceil(max / nice) * nice;
        const slot = plotW / months.length;
        const barW = Math.min(26, slot * 0.32);
        const y = function (value) { return pad.top + plotH - value / top * plotH; };

        box.innerHTML = '';
        const svg = el('svg', { viewBox: '0 0 ' + width + ' ' + height, width: '100%', height: height, role: 'img', 'aria-label': 'Fees collected against expenses and salaries per month' }, box);

        for (let tick = 0; tick <= 4; tick += 1) {
            const value = top * tick / 4;
            el('line', { x1: pad.left, x2: width - pad.right, y1: y(value), y2: y(value), class: 'dash-fin-gridline' }, svg);
            el('text', { x: pad.left - 8, y: y(value) + 4, 'text-anchor': 'end', class: 'dash-fin-axis' }, svg).textContent = short(value);
        }

        months.forEach(function (month, index) {
            const x = pad.left + slot * index + slot / 2;
            const group = el('g', { class: 'dash-fin-col', style: '--i:' + index }, svg);

            if (!hidden.collected && month.collected > 0) {
                el('rect', { x: x - barW - 2, y: y(month.collected), width: barW, height: Math.max(0, plotH + pad.top - y(month.collected)), rx: 3, fill: SERIES[0].color, class: 'dash-fin-bar' }, group);
            }

            let stackBase = 0;
            [SERIES[1], SERIES[2]].forEach(function (item) {
                const value = hidden[item.key] ? 0 : month[item.key];

                if (value > 0) {
                    el('rect', { x: x + 2, y: y(stackBase + value), width: barW, height: Math.max(0, y(stackBase) - y(stackBase + value)), rx: 3, fill: item.color, class: 'dash-fin-bar' }, group);
                    stackBase += value;
                }
            });

            if (width >= 520 || index % 2 === months.length % 2) {
                el('text', { x: x, y: height - 10, 'text-anchor': 'middle', class: 'dash-fin-axis' }, svg).textContent = month.label;
            }

            const hit = el('rect', { x: pad.left + slot * index, y: pad.top, width: slot, height: plotH, fill: 'transparent', class: 'dash-fin-hit', tabindex: '0', 'aria-label': month.label }, svg);
            const show = function () {
                const net = month.collected - month.expenses - month.salaries;
                tooltip.innerHTML = '<strong>' + esc(month.label) + '</strong>' + SERIES.map(function (item) {
                    return '<span><i style="background:' + item.color + '"></i>' + esc(item.label) + '<b>' + rupees(month[item.key]) + '</b></span>';
                }).join('') + '<span class="dash-fin-tip-net ' + (net >= 0 ? 'is-good' : 'is-bad') + '">Net<b>' + rupees(net) + '</b></span>';
                tooltip.hidden = false;
                const left = Math.min(Math.max(x * box.clientWidth / width - tooltip.offsetWidth / 2, 0), box.clientWidth - tooltip.offsetWidth);
                tooltip.style.transform = 'translate(' + left + 'px, 0)';
                group.classList.add('is-hover');
            };
            const hide = function () { tooltip.hidden = true; group.classList.remove('is-hover'); };

            hit.addEventListener('mouseenter', show);
            hit.addEventListener('focus', show);
            hit.addEventListener('mouseleave', hide);
            hit.addEventListener('blur', hide);
            hit.addEventListener('click', function () { window.location.href = root.dataset.reports + '?report=fee-collection&from=' + month.month + '-01&to=' + lastDay(month.month); });
        });

        const netPoints = months.map(function (month, index) {
            const net = (hidden.collected ? 0 : month.collected) - (hidden.expenses ? 0 : month.expenses) - (hidden.salaries ? 0 : month.salaries);

            return [pad.left + slot * index + slot / 2, y(Math.max(0, Math.min(top, net)))];
        });

        if (series.length > 1) {
            el('path', { d: netPoints.map(function (point, index) { return (index ? 'L' : 'M') + point[0] + ' ' + point[1]; }).join(' '), class: 'dash-fin-netline' }, svg);
        }

        tooltip.className = 'dash-fin-tip';
        tooltip.hidden = true;
        box.appendChild(tooltip);

        const totals = SERIES.reduce(function (sum, item) {
            sum[item.key] = months.reduce(function (total, month) { return total + month[item.key]; }, 0);

            return sum;
        }, {});
        const net = totals.collected - totals.expenses - totals.salaries;

        root.querySelector('[data-trend-net]').innerHTML = SERIES.map(function (item) {
            return '<span><i style="background:' + item.color + '"></i>' + esc(item.label) + ' <b>' + rupees(totals[item.key]) + '</b></span>';
        }).join('') + '<span class="' + (net >= 0 ? 'is-good' : 'is-bad') + '">' + (net >= 0 ? 'Surplus' : 'Shortfall') + ' <b>' + rupees(Math.abs(net)) + '</b></span>';

        root.querySelector('[data-trend-legend]').innerHTML = SERIES.map(function (item) {
            return '<button type="button" data-series="' + item.key + '" aria-pressed="' + (!hidden[item.key]) + '"><i style="background:' + item.color + '"></i>' + esc(item.label) + '</button>';
        }).join('') + '<span class="dash-fin-legend-net"><i></i>Net</span>';
    }

    function lastDay(month) {
        const parts = month.split('-');

        return month + '-' + String(new Date(Number(parts[0]), Number(parts[1]), 0).getDate()).padStart(2, '0');
    }

    /* ---------- Pending fees by age ---------- */

    function ageing() {
        const box = root.querySelector('[data-ageing]');
        const total = data.ageing.reduce(function (sum, bucket) { return sum + bucket.amount; }, 0);

        if (!total) {
            empty(box, 'No overdue fees. Every challan is within its due date.');

            return;
        }

        const size = 168;
        const radius = 64;
        const circumference = 2 * Math.PI * radius;
        let offset = 0;
        box.innerHTML = '';
        const wrap = document.createElement('div');
        wrap.className = 'dash-fin-ring-wrap';
        box.appendChild(wrap);
        const svg = el('svg', { viewBox: '0 0 ' + size + ' ' + size, class: 'dash-fin-ring', role: 'img', 'aria-label': 'Overdue fees by age' }, wrap);
        el('circle', { cx: size / 2, cy: size / 2, r: radius, class: 'dash-fin-ring-track' }, svg);

        data.ageing.forEach(function (bucket, index) {
            if (!bucket.amount) {
                return;
            }

            const length = bucket.amount / total * circumference;
            el('circle', {
                cx: size / 2, cy: size / 2, r: radius, stroke: AGEING_COLORS[index], class: 'dash-fin-ring-part', style: '--i:' + index,
                'stroke-dasharray': Math.max(0, length - 2) + ' ' + circumference, 'stroke-dashoffset': -offset
            }, svg);
            offset += length;
        });

        const center = document.createElement('div');
        center.className = 'dash-fin-ring-center';
        center.innerHTML = '<strong>' + esc(short(total)) + '</strong><span>overdue</span>';
        wrap.appendChild(center);

        const legend = document.createElement('ul');
        legend.className = 'dash-fin-ring-legend';
        legend.innerHTML = data.ageing.map(function (bucket, index) {
            return '<li style="--i:' + index + '"><i style="background:' + AGEING_COLORS[index] + '"></i><span>' + esc(bucket.label) + '</span><b>' + rupees(bucket.amount) + '</b><em>' + Math.round(bucket.amount * 100 / total) + '%</em></li>';
        }).join('');
        box.appendChild(legend);
    }

    /* ---------- Daily collection ---------- */

    function daily() {
        const box = root.querySelector('[data-daily]');
        const days = data.daily;

        if (!days.some(function (day) { return day.collected > 0; })) {
            empty(box, 'No fees received this month yet.', ['Open receipts', root.dataset.receipts]);

            return;
        }

        const width = Math.max(box.clientWidth, 260);
        const height = 210;
        const pad = { top: 14, right: 10, bottom: 26, left: 46 };
        const plotW = width - pad.left - pad.right;
        const plotH = height - pad.top - pad.bottom;
        const max = Math.max.apply(null, days.map(function (day) { return day.collected; }).concat([100]));
        const x = function (index) { return pad.left + (days.length > 1 ? index * plotW / (days.length - 1) : plotW / 2); };
        const y = function (value) { return pad.top + plotH - value / max * plotH; };
        const line = days.map(function (day, index) { return (index ? 'L' : 'M') + x(index).toFixed(1) + ' ' + y(day.collected).toFixed(1); }).join(' ');

        box.innerHTML = '';
        const svg = el('svg', { viewBox: '0 0 ' + width + ' ' + height, width: '100%', height: height, role: 'img', 'aria-label': 'Fees received each day this month' }, box);
        [0, 0.5, 1].forEach(function (share) {
            el('line', { x1: pad.left, x2: width - pad.right, y1: y(max * share), y2: y(max * share), class: 'dash-fin-gridline' }, svg);
            el('text', { x: pad.left - 6, y: y(max * share) + 4, 'text-anchor': 'end', class: 'dash-fin-axis' }, svg).textContent = short(max * share);
        });

        const drawn = el('g', { class: 'dash-fin-reveal' }, svg);
        el('path', { d: line + ' L' + x(days.length - 1) + ' ' + (pad.top + plotH) + ' L' + x(0) + ' ' + (pad.top + plotH) + ' Z', class: 'dash-fin-area-fill' }, drawn);
        el('path', { d: line, class: 'dash-fin-area-line' }, drawn);
        [0, Math.floor((days.length - 1) / 2), days.length - 1].forEach(function (index) {
            el('text', { x: x(index), y: height - 8, 'text-anchor': index === 0 ? 'start' : (index === days.length - 1 ? 'end' : 'middle'), class: 'dash-fin-axis' }, svg).textContent = dayFormat.format(new Date(days[index].day));
        });

        const guide = el('g', { class: 'dash-fin-guide', opacity: 0 }, svg);
        el('line', { x1: 0, x2: 0, y1: pad.top, y2: pad.top + plotH }, guide);
        const dot = el('circle', { r: 4.5, cx: 0, cy: 0 }, guide);
        const tooltip = document.createElement('div');
        tooltip.className = 'dash-fin-tip';
        tooltip.hidden = true;
        box.appendChild(tooltip);

        const overlay = el('rect', { x: pad.left, y: pad.top, width: plotW, height: plotH, fill: 'transparent' }, svg);
        const move = function (event) {
            const rect = svg.getBoundingClientRect();
            const px = (event.clientX - rect.left) * width / rect.width;
            const index = Math.max(0, Math.min(days.length - 1, Math.round((px - pad.left) / (plotW / Math.max(1, days.length - 1)))));
            const day = days[index];

            guide.setAttribute('opacity', 1);
            guide.setAttribute('transform', 'translate(' + x(index) + ' 0)');
            dot.setAttribute('cy', y(day.collected));
            tooltip.innerHTML = '<strong>' + esc(dayFormat.format(new Date(day.day))) + '</strong><span>Collected<b>' + rupees(day.collected) + '</b></span><span>Receipts<b>' + day.receipts + '</b></span>';
            tooltip.hidden = false;
            tooltip.style.transform = 'translate(' + Math.min(Math.max(x(index) * rect.width / width - tooltip.offsetWidth / 2, 0), rect.width - tooltip.offsetWidth) + 'px, 0)';
        };

        overlay.addEventListener('pointermove', move);
        overlay.addEventListener('pointerleave', function () { guide.setAttribute('opacity', 0); tooltip.hidden = true; });
    }

    /* ---------- Bar lists and recent lists ---------- */

    function bars() {
        const links = {
            pending_by_class: function (row) { return row.class_id ? root.dataset.reports + '?report=fee-defaulters&class_id=' + row.class_id : null; },
            methods: function () { return root.dataset.reports + '?report=fee-collection&group_by=method'; },
            expense_categories: function () { return root.dataset.expenses; }
        };
        const notes = {
            pending_by_class: function (row) { return row.students + ' student' + (row.students === 1 ? '' : 's'); },
            methods: function (row) { return row.receipts + ' receipt' + (row.receipts === 1 ? '' : 's'); },
            expense_categories: function () { return ''; }
        };
        const emptyText = { pending_by_class: 'No fees pending.', methods: 'No fees received this month yet.', expense_categories: 'No expenses this month yet.' };

        root.querySelectorAll('[data-bars]').forEach(function (list) {
            const key = list.dataset.bars;
            const rows = data[key];
            const max = Math.max.apply(null, rows.map(function (row) { return row.amount; }).concat([1]));

            list.innerHTML = rows.length ? rows.map(function (row, index) {
                const href = links[key](row);
                const inner = '<span class="dash-fin-bar-label"><span>' + esc(row.label) + '</span><b>' + rupees(row.amount) + '</b></span>'
                    + '<span class="dash-fin-bar-track"><span style="--bar:' + (row.amount / max).toFixed(3) + '"></span></span>'
                    + (notes[key](row) ? '<small>' + esc(notes[key](row)) + '</small>' : '');

                return '<li style="--i:' + index + '">' + (href ? '<a href="' + esc(href) + '">' + inner + '</a>' : '<div>' + inner + '</div>') + '</li>';
            }).join('') : '<li class="dash-fin-empty">' + esc(emptyText[key]) + '</li>';
        });

        const receipts = root.querySelector('[data-list="recent_receipts"]');
        receipts.innerHTML = data.recent_receipts.length ? data.recent_receipts.map(function (row, index) {
            return '<li style="--i:' + index + '"><span class="dash-fin-avatar">' + esc((row.student || '?').charAt(0)) + '</span><span><strong>' + esc(row.student || '—') + '</strong><small>' + esc([row.receipt_no, row.method, row.received_on ? dayFormat.format(new Date(row.received_on)) : null].filter(Boolean).join(' · ')) + '</small></span><b class="is-good">+ ' + rupees(row.amount) + '</b></li>';
        }).join('') : '<li class="dash-fin-empty">No receipts yet.</li>';

        const dues = root.querySelector('[data-list="top_defaulters"]');
        dues.innerHTML = data.top_defaulters.length ? data.top_defaulters.map(function (row, index) {
            return '<li style="--i:' + index + '"><span class="dash-fin-avatar is-warn">' + esc(row.student.charAt(0)) + '</span><span><strong>' + esc(row.student) + '</strong><small>' + esc(row.admission_no + ' · due since ' + dayFormat.format(new Date(row.oldest_due))) + '</small></span><b class="is-bad">' + rupees(row.amount) + '</b></li>';
        }).join('') : '<li class="dash-fin-empty">No overdue dues. 🎉</li>';
    }

    /* ---------- Loading ---------- */

    function render() {
        kpis();
        trend();
        ageing();
        daily();
        bars();
        root.setAttribute('aria-busy', 'false');
        root.classList.add('is-ready');
    }

    function load(months) {
        root.classList.add('is-loading');

        return ui.api('GET', root.dataset.url + '?months=' + months).then(function (envelope) {
            data = envelope.data;
            render();
        }).catch(function () {
            ui.toast('The money figures could not be loaded.', 'error');
        }).finally(function () {
            root.classList.remove('is-loading');
        });
    }

    root.querySelector('.dash-fin-toggle').addEventListener('click', function (event) {
        const button = event.target.closest('[data-months]');

        if (!button || button.classList.contains('is-active')) {
            return;
        }

        root.querySelectorAll('[data-months]').forEach(function (other) { other.classList.toggle('is-active', other === button); });
        load(Number(button.dataset.months));
    });

    root.querySelector('[data-trend-legend]').addEventListener('click', function (event) {
        const button = event.target.closest('[data-series]');

        if (button) {
            hidden[button.dataset.series] = !hidden[button.dataset.series];

            if (SERIES.every(function (item) { return hidden[item.key]; })) {
                hidden = {};
            }

            trend();
        }
    });

    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            if (data) {
                root.classList.add('is-static');
                trend();
                daily();
            }
        }, 200);
    });

    load(12);
})();
