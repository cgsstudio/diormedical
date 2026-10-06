# Dior Medical UI Update — 2026-10-02

## Doctor Dashboard
- Rebuilt the Appointment Calendar presentation with a dedicated scoped stylesheet.
- Improved calendar toolbar, month/week/day/list controls, day cells, events, today state, spacing and responsive behavior.
- Calendar now initializes to the current WordPress date/month automatically.
- Changes are scoped to `#tab-doc-appointments` only.

## Patient Portal
- Patient Overview/Dashboard is intentionally excluded from the new styling.
- Added a dedicated non-overview patient UI stylesheet for tables, cards, headers, forms and responsive behavior.
- Added a non-persistent static design dataset after the Overview include so the remaining patient tabs have stable sample data during UI/design work.
- Static data is not saved to WordPress and does not replace the Overview data.
- Static dataset covers appointments, prescriptions, billing, documents, questionnaires, notifications, insurance, feedback, profile/emergency details and questionnaire/intake details.

## Safety / Scope
- No database writes were added.
- Existing APIs/AJAX functionality was not removed.
- New CSS is scoped to the intended dashboard/tab selectors.
- PHP syntax checks passed for all modified PHP files.
