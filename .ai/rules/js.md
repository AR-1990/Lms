---
paths:
  - 'public/js/**'
---

# Js

## Today's date in JS must be local, not UTC
Never use `new Date().toISOString().slice(0, 10)` for today's date: it is UTC, so in Pakistan (UTC+5) forms default to yesterday between midnight and 5 am. Use `new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10)`.
