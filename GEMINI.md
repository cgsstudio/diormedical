# Dior Medical – Design & Development Guidelines

## 1. Core Rule: 100% Bootstrap 5 Only (Strictly No Custom CSS)
- **Zero Custom CSS**: Never write new custom CSS files, custom `<style>` blocks, or ad-hoc CSS classes for layout and UI components.
- **Native Bootstrap 5 Utilities**: All future UI and design changes across the Dior Medical dashboard and portal MUST be implemented exclusively using Bootstrap 5 utility classes and standard components.
- **No Inline Styles for Layout/Design**: Avoid inline `style="..."` attributes for layout, display, width, margins, paddings, and flex behavior. Use Bootstrap classes (`d-flex`, `flex-wrap`, `justify-content-*`, `align-items-*`, `gap-*`, `m-*`, `p-*`, `w-100`, `h-100`, `rounded-*`, `border-*`, `shadow-sm`, etc.).

---

## 2. Responsive Grid Standards
Always use Bootstrap 5's responsive grid system to ensure perfect layout across desktop (1536px), laptop (1280px & 1200px), tablet, and mobile screens:

| Breakpoint | Screen Size | Bootstrap Grid Prefix | Dashboard Column Behavior |
|------------|-------------|----------------------|---------------------------|
| **xxl** | $\ge 1400\text{px}$ (e.g. 1536px, 1920px) | `col-xxl-*` | Left col: `col-xxl-8`, Right col: `col-xxl-4`. Mid-grid cards: `col-xxl-6` side-by-side. |
| **xl** | $1200\text{px} - 1399\text{px}$ (e.g. 1200px, 1280px) | `col-xl-*` | Left col: `col-xl-7`, Right col: `col-xl-5`. Mid-grid cards: `col-12` full width stacked. |
| **lg** | $992\text{px} - 1199\text{px}$ (Small desktops / landscape tablets) | `col-lg-*` | Left & Right cols stack: `col-12`. |
| **md** | $768\text{px} - 991\text{px}$ (Tablets) | `col-md-*` | Stacked main layout: `col-12`. Cards inside right-col: `col-md-6`. |
| **sm** / Mobile | $< 768\text{px}$ (Phones) | `col-sm-*` / `col-12` | Single column vertical stack with clean touch targets. |

---

## 3. UI Component Standards

### A. Layout & Containers
- Outer layout: `<div class="row g-3 g-xl-4 align-items-start">`
- Inner card rows: `<div class="row g-3">`

### B. Cards
- Standard Card: `<div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 h-100">`
- Stat Cards: `<div class="card stat-card border-0 shadow-sm rounded-4 p-3 p-md-4 h-100 d-flex flex-column justify-content-between">`
  - Number: `<div class="stat-num fs-1 fw-bold text-primary mb-1">`
  - Label: `<div class="stat-label text-dark fw-semibold small">`

### C. Buttons
- Primary Button: `<button class="btn btn-primary rounded-3 fw-semibold py-2 px-3 shadow-sm">`
- Outline Button: `<button class="btn btn-outline-primary rounded-3 fw-semibold py-2 px-3">`
- Small Icon Button: `<button class="btn btn-primary btn-sm rounded-circle d-inline-flex align-items-center justify-content-center p-0 shadow-sm" style="width: 32px; height: 32px;">`

### D. Badges & Pills
- Status Active / Confirmed: `<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 small">`
- Status Pending / In Progress: `<span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-1 small">`
- ID / Code Badge: `<span class="badge bg-light text-secondary border rounded-pill px-2 py-1 small">`

### E. Tables
- Always wrap every table in a responsive wrapper:
  ```html
  <div class="table-responsive mb-0">
    <table class="table align-middle text-nowrap mb-0">
      <thead class="sticky-top bg-white">...</thead>
      <tbody>...</tbody>
    </table>
  </div>
  ```

### F. Modals
- Strictly use Bootstrap 5 native modal structure:
  ```html
  <div class="modal fade" id="modalId" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
        <div class="modal-header border-bottom py-3 px-4 bg-white">...</div>
        <div class="modal-body p-4 bg-light-subtle">...</div>
        <div class="modal-footer border-top py-3 px-4 bg-white">...</div>
      </div>
    </div>
  </div>
  ```
- JS interaction: `bootstrap.Modal.getOrCreateInstance(document.getElementById('modalId'))`.

### G. Progress Bars
- Standard Bootstrap progress:
  ```html
  <div class="progress" style="height: 8px;">
    <div class="progress-bar bg-warning" role="progressbar" style="width: 65%;" aria-valuenow="65" aria-valuemin="0" aria-valuemax="100"></div>
  </div>
  ```

---

## 4. Stability & Quality Rules
1. **Preserve Element IDs and JS Selectors**:
   - Never remove or alter existing IDs (e.g., `#dior-patient-goals-card`, `#dior-overview-doctor-card`, `#dior-overview-upcoming-card`, `#diorHealthGoalsModal`, etc.) or data attributes that existing JavaScript relies on.
2. **Clear Caches After Changes**:
   - Always flush WP Rocket domain and minify cache whenever template or asset modifications are made:
     ```php
     rocket_clean_domain();
     rocket_clean_minify();
     ```
