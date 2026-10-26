---
paths:
  - 'app/**'
---

# App

## One API for web and mobile; no extra functions
Every feature is one API in routes/api.php that BOTH the web screens and the mobile apps call; the only exception is sign-in (web session form vs token login). Web pages are shells that load and save through those /api/erp endpoints; never put data shaping or business logic in a web-only controller or Blade — move it into the Service the API uses. Lists: grids call the datatable endpoint with `draw`; mobile calls the same endpoint without `draw` and gets {items, meta} (App\Support\GridDataTable). Do not add extra functions or layers; only add helpers that support the logic (response envelope, formatting, pagination) and are reused.

## Every notification always reaches the portal
Every notification must be delivered to the portal (in-app bell, plus push when Firebase is set). WhatsApp, SMS and email are optional extras that never replace it. Send only through NotificationService::notify()/notifyEvent(), which force in_app; never add a path, setting or template switch that sends only SMS/WhatsApp/email.
