---
paths:
  - 'routes/**'
---

# Routes

## Three route files with fixed jobs
routes/web.php = Blade pages only (plus session login/logout form). routes/api.php = every API route (ERP routes inside the `erp` group: /api/erp, erp.* names, ForceJsonResponse + auth:sanctum). routes/export.php = every PDF, print sheet, report, export and file-download link (registered before api.php so fixed paths beat wildcards). Never put API or export routes in web.php and never add other route files.
