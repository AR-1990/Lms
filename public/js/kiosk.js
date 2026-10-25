/**
 * Gate kiosk: reads ID-card QR codes from the camera (the browser's own detector, else jsQR) or a handheld
 * scanner and posts each to the kiosk API. The same card is not sent twice within a few seconds.
 */
(function () {
    'use strict';

    const body = document.querySelector('[data-kiosk]');

    if (!body) {
        return;
    }

    const url = body.dataset.url;
    const video = body.querySelector('[data-video]');
    const result = body.querySelector('[data-result]');
    const log = body.querySelector('[data-log]');
    const manual = body.querySelector('[data-manual]');
    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d', { willReadFrequently: true });
    const recent = {};
    let busy = false;
    let audio = null;

    function request(method, path, payload) {
        return window.fetch(url + path, {
            method: method,
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Platform': 'kiosk' },
            body: payload ? JSON.stringify(payload) : undefined
        }).then(function (response) {
            return response.json().catch(function () { return { success: false, message: 'Unexpected response (' + response.status + ').' }; });
        });
    }

    function beep(ok) {
        try {
            audio = audio || new (window.AudioContext || window.webkitAudioContext)();
            const tone = audio.createOscillator();
            const gain = audio.createGain();
            tone.frequency.value = ok ? 880 : 220;
            gain.gain.value = 0.08;
            tone.connect(gain).connect(audio.destination);
            tone.start();
            tone.stop(audio.currentTime + (ok ? 0.12 : 0.35));
        } catch (error) {
            // Sound is optional.
        }
    }

    function show(ok, title, text) {
        result.classList.remove('ok', 'bad', 'flash');
        void result.offsetWidth;
        result.classList.add(ok ? 'ok' : 'bad', 'flash');
        body.querySelector('[data-result-icon]').textContent = ok ? '✓' : '!';
        body.querySelector('[data-result-title]').textContent = title;
        body.querySelector('[data-result-text]').textContent = text;

        const row = document.createElement('li');
        row.className = ok ? '' : 'bad';
        row.innerHTML = '<span></span><span></span>';
        row.firstChild.textContent = title;
        row.lastChild.textContent = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        log.prepend(row);

        while (log.children.length > 12) {
            log.lastChild.remove();
        }

        beep(ok);
    }

    function mark(code) {
        const now = Date.now();

        if (!code || busy || (recent[code] && now - recent[code] < 5000)) {
            return;
        }

        busy = true;
        recent[code] = now;

        request('POST', '/scan', { code: code }).then(function (envelope) {
            if (envelope.success) {
                show(true, envelope.data.name || 'Marked', envelope.message);
            } else {
                show(false, 'Not marked', (envelope.errors && envelope.errors.summary) || envelope.message || 'This card could not be read.');
            }
        }).catch(function () {
            show(false, 'No connection', 'Check the internet connection and scan again.');
        }).finally(function () {
            busy = false;
        });
    }

    function scanFrame(detector) {
        if (video.readyState < 2) {
            return Promise.resolve(null);
        }

        if (detector) {
            return detector.detect(video).then(function (codes) { return codes.length ? codes[0].rawValue : null; });
        }

        if (!window.jsQR) {
            return Promise.resolve(null);
        }

        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        context.drawImage(video, 0, 0, canvas.width, canvas.height);
        const image = context.getImageData(0, 0, canvas.width, canvas.height);
        const found = window.jsQR(image.data, image.width, image.height, { inversionAttempts: 'dontInvert' });

        return Promise.resolve(found ? found.data : null);
    }

    function startCamera() {
        const detector = 'BarcodeDetector' in window ? new window.BarcodeDetector({ formats: ['qr_code'] }) : null;

        navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false }).then(function (stream) {
            video.srcObject = stream;
            body.querySelector('[data-start]').hidden = true;

            return video.play();
        }).then(function () {
            const tick = function () {
                scanFrame(detector).then(function (code) {
                    if (code) {
                        mark(code);
                    }
                }).catch(function () {}).finally(function () {
                    window.setTimeout(tick, 300);
                });
            };

            tick();
        }).catch(function () {
            show(false, 'Camera blocked', 'Allow camera access for this page, or use a handheld scanner.');
        });
    }

    manual.addEventListener('submit', function (event) {
        event.preventDefault();
        mark(manual.elements.code.value.trim());
        manual.elements.code.value = '';
    });

    body.querySelector('[data-start-button]').addEventListener('click', startCamera);

    window.setInterval(function () {
        body.querySelector('[data-clock]').textContent = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }, 1000);

    request('GET', '').then(function (envelope) {
        if (!envelope.success) {
            body.querySelector('[data-main]').innerHTML = '<div class="closed"><h1>Kiosk not active</h1><p>Ask the school office for a new kiosk link.</p></div>';
            body.querySelector('[data-kiosk-name]').textContent = '';

            return;
        }

        body.querySelector('[data-school]').textContent = envelope.data.school_name || 'Attendance';
        body.querySelector('[data-kiosk-name]').textContent = envelope.data.name + ' · Attendance';

        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            startCamera();
        }
    });
})();
