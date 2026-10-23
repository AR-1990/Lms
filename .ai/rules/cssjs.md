---
paths:
  - 'public/{css,js}/**'
---

# Cssjs

## Animations never slow the app
The project handles large data, so every animation animates only transform, opacity or clip-path (GPU-composited), never width/height/top/left/box-shadow/filters, and no backdrop-filter blur behind big tables. Respect prefers-reduced-motion. Popups (.dash-modal) open and close with the shared scroll-unroll animation from dashboard.css + the close() hook in dashboard.js; do not add per-screen popup animations.
