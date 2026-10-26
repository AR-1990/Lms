/**
 * User guide: search filters the topics (and the steps inside them) as you type; the topic list follows.
 */
(function () {
    'use strict';

    const root = document.querySelector('[data-guide]');

    if (!root) {
        return;
    }

    const search = root.querySelector('[data-guide-search]');
    const topics = Array.prototype.slice.call(root.querySelectorAll('[data-guide-topic]'));
    const empty = root.querySelector('[data-guide-empty]');

    search.addEventListener('input', function () {
        const words = search.value.toLowerCase().split(/\s+/).filter(Boolean);
        let shown = 0;

        topics.forEach(function (topic) {
            const text = topic.textContent.toLowerCase();
            const match = words.every(function (word) { return text.indexOf(word) !== -1; });
            topic.hidden = !match;
            root.querySelector('[data-guide-link="' + topic.dataset.guideTopic + '"]').hidden = !match;
            shown += match ? 1 : 0;

            // Inside a matching topic, open the parts that match.
            topic.querySelectorAll('details').forEach(function (part) {
                part.open = words.length > 0 && words.every(function (word) { return part.textContent.toLowerCase().indexOf(word) !== -1; });
            });
        });

        empty.hidden = shown > 0;
    });
})();
