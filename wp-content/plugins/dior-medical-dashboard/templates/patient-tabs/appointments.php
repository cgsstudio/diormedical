<section class="dior-tab-panel py-3 py-md-4 px-2 px-md-3 pb-5" id="tab-appointments">
    <style>
    /* Keep appointment tab labels consistently bold and black when inactive, and crisp white when active. */
    #tab-appointments .dior-subtab-pill .dior-subtab-label {
        color: #000000 !important;
        font-weight: 600 !important;
    }
    #tab-appointments .dior-subtab-pill.active,
    #tab-appointments .dior-subtab-pill.active .dior-subtab-label,
    #tab-appointments .dior-subtab-pill.active i {
        color: #FFFFFF !important;
    }

    /* Shared typography and spacing for Today, Upcoming and Past appointment tables. */
    #tab-appointments .dior-appointment-list-table {
        width: 100%;
        margin-bottom: 0;
        border-color: #e5ebf3;
        table-layout: auto;
        font-size: 12px;
        color: #334155;
    }
    #tab-appointments .dior-appointment-list-table th {
        padding: 12px 14px;
        color: #64748b;
        background: #fff;
        border-bottom: 1px solid #dfe7f1;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .045em;
        line-height: 1.35;
        white-space: nowrap;
        vertical-align: middle;
    }
    #tab-appointments .dior-appointment-list-table td {
        padding: 12px 14px;
        color: #334155;
        background: transparent;
        border-color: #edf1f6;
        font-size: 12px;
        line-height: 1.4;
        vertical-align: middle;
    }
    #tab-appointments .dior-appointment-list-table .dior-appointment-doctor-name {
        display: inline-block;
        color: #172b49;
        font-size: 12px;
        font-weight: 700;
        line-height: 1.4;
        white-space: nowrap;
    }
    /* Keep table surfaces neutral; emphasize text with text color only. */
    #tab-appointments .dior-appointment-list-table tbody tr,
    #tab-appointments .dior-appointment-list-table tbody tr:hover,
    #tab-appointments .dior-appointment-list-table tbody tr:focus-within {
        background: transparent !important;
    }
    #tab-appointments .dior-appointment-list-table tbody tr > *,
    #tab-appointments .dior-appointment-list-table tbody tr:hover > * {
        background: transparent !important;
    }
    #tab-appointments .dior-appointment-list-table .badge,
    #tab-appointments .dior-appointment-list-table .rounded-pill {
        background: transparent !important;
        border-color: transparent !important;
        box-shadow: none !important;
    }
    /* Keep dates and time ranges on one line where space permits. */
    #tab-appointments #dior-today-appointments-table td:nth-child(4),
    #tab-appointments #dior-today-appointments-table td:nth-child(5),
    #tab-appointments #dior-upcoming-appointments-table td:nth-child(3),
    #tab-appointments #dior-upcoming-appointments-table td:nth-child(4),
    #tab-appointments #dior-past-appointments-table td:nth-child(3),
    #tab-appointments #dior-past-appointments-table td:nth-child(4) {
        white-space: nowrap;
    }
    @media (max-width: 767.98px) {
        #tab-appointments .dior-appointment-list-table th,
        #tab-appointments .dior-appointment-list-table td {
            padding: 10px 12px;
        }
    }

    /* Step 1: Bootstrap-based Today's Appointments visual refresh.
       Scoped to the Today subtab so booking/upcoming/past functionality is untouched. */
    #tab-appointments #dior-subtab-today > .card {
        border: 1px solid #e7edf5 !important;
        border-radius: 18px !important;
        padding: 22px !important;
        box-shadow: 0 8px 28px rgba(22, 52, 91, .06) !important;
        background: #fff !important;
    }
    #tab-appointments #dior-subtab-today > .card > .d-flex:first-child {
        align-items: center !important;
        gap: 14px !important;
        padding-bottom: 18px !important;
        margin-bottom: 18px !important;
        border-bottom: 1px solid #edf1f7 !important;
    }
    #tab-appointments #dior-subtab-today h5 {
        font-size: 20px !important;
        line-height: 1.35 !important;
        letter-spacing: -.025em;
        color: #142b4a !important;
    }
    #tab-appointments #dior-subtab-today .badge {
        font-weight: 600 !important;
        letter-spacing: 0 !important;
    }
    #tab-appointments #dior-subtab-today .input-group {
        min-height: 38px;
        width: min(100%, 260px) !important;
        background: #f8fafc;
        border-color: #e2e8f0 !important;
        border-radius: 10px !important;
    }
    #tab-appointments #dior-subtab-today .input-group:focus-within {
        border-color: #86b7fe !important;
        box-shadow: 0 0 0 .2rem rgba(13,110,253,.10);
    }
    #tab-appointments #dior-subtab-today .input-group-text,
    #tab-appointments #dior-subtab-today .input-group .form-control {
        background: transparent !important;
        font-size: 13px;
    }
    #tab-appointments #dior-subtab-today .btn {
        border-radius: 9px !important;
        font-weight: 600;
        transition: box-shadow .18s ease, transform .18s ease;
    }
    #tab-appointments #dior-subtab-today .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(22, 52, 91, .10);
    }
    #tab-appointments #dior-today-appointments-table {
        border-collapse: separate !important;
        border-spacing: 0;
        min-width: 900px;
        font-size: 13px !important;
    }
    #tab-appointments #dior-today-appointments-table thead th {
        background: #f6f9fd !important;
        color: #63748b !important;
        border-top: 1px solid #e7edf5 !important;
        border-bottom: 1px solid #e7edf5 !important;
        padding: 14px 13px !important;
        font-size: 10px !important;
        font-weight: 700 !important;
        letter-spacing: .055em;
        text-transform: uppercase;
    }
    #tab-appointments #dior-today-appointments-table thead th:first-child {
        border-radius: 10px 0 0 0;
    }
    #tab-appointments #dior-today-appointments-table thead th:last-child {
        border-radius: 0 10px 0 0;
    }
    #tab-appointments #dior-today-appointments-table tbody td {
        padding: 15px 13px !important;
        border-bottom: 1px solid #edf1f6 !important;
        color: #4a5c73 !important;
        vertical-align: middle !important;
        background: #fff !important;
        font-size: 12px !important;
    }
    #tab-appointments #dior-today-appointments-table tbody tr:hover td {
        background: #f8fbff !important;
    }
    #tab-appointments #dior-today-appointments-table .dior-appointment-doctor-name {
        color: #183657 !important;
        font-size: 13px !important;
        font-weight: 700 !important;
    }
    #tab-appointments #dior-today-appointments-table .badge {
        display: inline-flex;
        align-items: center;
        max-width: 180px;
        padding: 6px 9px !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 7px !important;
        background: #f8fafc !important;
        color: #475569 !important;
        white-space: normal;
    }
    #tab-appointments #dior-today-appointments-table .cell-actions {
        gap: 6px !important;
    }
    #tab-appointments #dior-today-appointments-table .action-icon-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        padding: 0 !important;
        border: 1px solid #e3eaf3 !important;
        border-radius: 8px !important;
        background: #fff !important;
    }
    #tab-appointments #dior-today-appointments-table .action-icon-btn:hover {
        background: #eff6ff !important;
        border-color: #bfd7fb !important;
    }
    #tab-appointments #dior-subtab-today .table-responsive {
        border-radius: 10px;
        -webkit-overflow-scrolling: touch;
    }
    @media (max-width: 767.98px) {
        #tab-appointments #dior-subtab-today > .card {
            padding: 14px !important;
            margin-bottom: 22px !important;
            border-radius: 14px !important;
        }
        #tab-appointments #dior-subtab-today > .card > .d-flex:first-child {
            align-items: stretch !important;
            flex-direction: column !important;
            gap: 12px !important;
            padding-bottom: 14px !important;
        }
        #tab-appointments #dior-subtab-today h5 {
            font-size: 18px !important;
        }
        #tab-appointments #dior-subtab-today > .card > .d-flex:first-child > div:last-child {
            display: grid !important;
            grid-template-columns: minmax(0, 1fr) repeat(3, 38px);
            gap: 7px !important;
            width: 100%;
        }
        #tab-appointments #dior-subtab-today .input-group {
            width: 100% !important;
            min-width: 0;
        }
        #tab-appointments #dior-subtab-today > .card > .d-flex:first-child > div:last-child > .btn {
            min-width: 36px;
            min-height: 36px;
            padding: 7px !important;
        }
        #tab-appointments #dior-subtab-today > .card > .d-flex:first-child > div:last-child > .btn[title="Book Appointment"] {
            grid-column: 1 / -1;
            grid-row: 2;
            justify-self: stretch;
        }
        #tab-appointments #dior-today-appointments-table thead th,
        #tab-appointments #dior-today-appointments-table tbody td {
            padding: 12px 10px !important;
        }
    }


    /* Step 2: Match Upcoming and Past appointment screens to the refreshed Today UI. */
    #tab-appointments #dior-subtab-upcoming > .card,
    #tab-appointments #dior-subtab-past > .card {
        border: 1px solid #e7edf5 !important;
        border-radius: 18px !important;
        padding: 22px !important;
        box-shadow: 0 8px 28px rgba(22, 52, 91, .06) !important;
        background: #fff !important;
    }
    #tab-appointments #dior-subtab-upcoming > .card > .d-flex:first-child,
    #tab-appointments #dior-subtab-past > .card > .d-flex:first-child {
        align-items: center !important;
        gap: 14px !important;
        padding-bottom: 18px !important;
        margin-bottom: 18px !important;
        border-bottom: 1px solid #edf1f7 !important;
    }
    #tab-appointments #dior-subtab-upcoming h5,
    #tab-appointments #dior-subtab-past h5 {
        color: #142b4a !important;
        font-size: 20px !important;
        line-height: 1.35 !important;
        letter-spacing: -.025em;
    }
    #tab-appointments #dior-subtab-upcoming .input-group,
    #tab-appointments #dior-subtab-past .input-group {
        min-height: 38px;
        width: min(100%, 260px) !important;
        background: #f8fafc;
        border-color: #e2e8f0 !important;
        border-radius: 10px !important;
    }
    #tab-appointments #dior-subtab-upcoming .input-group-text,
    #tab-appointments #dior-subtab-upcoming .input-group .form-control,
    #tab-appointments #dior-subtab-past .input-group-text,
    #tab-appointments #dior-subtab-past .input-group .form-control {
        background: transparent !important;
        font-size: 13px;
    }
    #tab-appointments #dior-subtab-upcoming .btn,
    #tab-appointments #dior-subtab-past .btn {
        border-radius: 9px !important;
        font-weight: 600;
        transition: box-shadow .18s ease, transform .18s ease;
    }
    #tab-appointments #dior-subtab-upcoming .btn:hover,
    #tab-appointments #dior-subtab-past .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(22, 52, 91, .10);
    }
    #tab-appointments #dior-upcoming-appointments-table,
    #tab-appointments #dior-past-appointments-table {
        border-collapse: separate !important;
        border-spacing: 0;
        min-width: 900px;
        font-size: 13px !important;
    }
    #tab-appointments #dior-upcoming-appointments-table thead th,
    #tab-appointments #dior-past-appointments-table thead th {
        background: #f6f9fd !important;
        color: #63748b !important;
        border-top: 1px solid #e7edf5 !important;
        border-bottom: 1px solid #e7edf5 !important;
        padding: 14px 13px !important;
        font-size: 10px !important;
        font-weight: 700 !important;
        letter-spacing: .055em;
        text-transform: uppercase;
    }
    #tab-appointments #dior-upcoming-appointments-table tbody td,
    #tab-appointments #dior-past-appointments-table tbody td {
        padding: 15px 13px !important;
        border-bottom: 1px solid #edf1f6 !important;
        color: #4a5c73 !important;
        vertical-align: middle !important;
        background: #fff !important;
        font-size: 12px !important;
    }
    #tab-appointments #dior-upcoming-appointments-table tbody tr:hover td,
    #tab-appointments #dior-past-appointments-table tbody tr:hover td {
        background: #f8fbff !important;
    }
    #tab-appointments #dior-upcoming-appointments-table .dior-appointment-doctor-name,
    #tab-appointments #dior-past-appointments-table .dior-appointment-doctor-name {
        color: #183657 !important;
        font-size: 13px !important;
        font-weight: 700 !important;
    }
    #tab-appointments #dior-upcoming-appointments-table .badge,
    #tab-appointments #dior-past-appointments-table .badge {
        display: inline-flex;
        align-items: center;
        max-width: 180px;
        padding: 6px 9px !important;
        border-radius: 7px !important;
        white-space: normal;
    }
    #tab-appointments #dior-upcoming-appointments-table .cell-actions,
    #tab-appointments #dior-past-appointments-table .cell-actions {
        gap: 6px !important;
    }
    #tab-appointments #dior-upcoming-appointments-table .action-icon-btn,
    #tab-appointments #dior-past-appointments-table .action-icon-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        padding: 0 !important;
        border: 1px solid #e3eaf3 !important;
        border-radius: 8px !important;
        background: #fff !important;
    }
    #tab-appointments #dior-subtab-upcoming .table-responsive,
    #tab-appointments #dior-subtab-past .table-responsive {
        border-radius: 10px;
        -webkit-overflow-scrolling: touch;
    }
    @media (max-width: 767.98px) {
        #tab-appointments #dior-subtab-upcoming > .card,
        #tab-appointments #dior-subtab-past > .card {
            padding: 14px !important;
            margin-bottom: 22px !important;
            border-radius: 14px !important;
        }
        #tab-appointments #dior-subtab-upcoming > .card > .d-flex:first-child,
        #tab-appointments #dior-subtab-past > .card > .d-flex:first-child {
            align-items: stretch !important;
            flex-direction: column !important;
            gap: 12px !important;
            padding-bottom: 14px !important;
        }
        #tab-appointments #dior-subtab-upcoming h5,
        #tab-appointments #dior-subtab-past h5 {
            font-size: 18px !important;
        }
        #tab-appointments #dior-subtab-upcoming > .card > .d-flex:first-child > div:last-child,
        #tab-appointments #dior-subtab-past > .card > .d-flex:first-child > div:last-child {
            display: flex !important;
            flex-wrap: wrap !important;
            width: 100%;
            gap: 7px !important;
        }
        #tab-appointments #dior-subtab-upcoming .input-group,
        #tab-appointments #dior-subtab-past .input-group {
            width: 100% !important;
            flex: 1 1 100%;
        }
        #tab-appointments #dior-upcoming-appointments-table thead th,
        #tab-appointments #dior-upcoming-appointments-table tbody td,
        #tab-appointments #dior-past-appointments-table thead th,
        #tab-appointments #dior-past-appointments-table tbody td {
            padding: 12px 10px !important;
        }

        /* Mobile Subtab Pill Bar: clean 2x2 grid without clipping icons or labels */
        #tab-appointments .dior-subtab-nav-bar {
            display: grid !important;
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            gap: 8px !important;
            padding: 6px !important;
            margin-left: 8px !important;
            margin-right: 8px !important;
            width: calc(100% - 16px) !important;
            overflow: visible !important;
        }
        #tab-appointments .dior-subtab-pill {
            width: 100% !important;
            min-width: 0 !important;
            min-height: 42px !important;
            padding: 8px 10px !important;
            gap: 6px !important;
            font-size: 11.5px !important;
            line-height: 1.2 !important;
            justify-content: flex-start !important;
            text-align: left !important;
            white-space: normal !important;
            border-radius: 10px !important;
        }
        #tab-appointments .dior-subtab-pill i {
            flex-shrink: 0 !important;
            font-size: 12px !important;
        }
        #tab-appointments .dior-subtab-pill .dior-subtab-label {
            min-width: 0 !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            display: -webkit-box !important;
            -webkit-line-clamp: 2 !important;
            -webkit-box-orient: vertical !important;
            line-height: 1.2 !important;
        }

        /* Mobile Booking Stepper Card: remove stray left border when stacked */
        #tab-appointments #dior-booking-stepper-card {
            padding: 12px 14px !important;
            margin-bottom: 16px !important;
            border-radius: 14px !important;
        }
        #tab-appointments #dior-stepper-steps-row > [class*="col-"] {
            border-left: none !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }
        #tab-appointments #dior-stepper-steps-row > [class*="col-"] + [class*="col-"] {
            border-top: 1px solid #edf2f7 !important;
            margin-top: 6px !important;
            padding-top: 6px !important;
        }
        #tab-appointments #dior-step-item-1,
        #tab-appointments #dior-step-item-2,
        #tab-appointments #dior-step-item-3 {
            padding: 6px 8px !important;
            gap: 10px !important;
        }
        #tab-appointments #dior-step-icon-1,
        #tab-appointments #dior-step-icon-2,
        #tab-appointments #dior-step-icon-3 {
            width: 32px !important;
            height: 32px !important;
            font-size: 13px !important;
        }

        /* Mobile Step 2 Time Slots & Action Buttons */
        #tab-appointments #dior-step2-slots-grid {
            display: grid !important;
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            gap: 8px !important;
        }
        #tab-appointments #dior-step2-slots-grid .dior-step2-slot-btn {
            width: 100% !important;
            font-size: 12px !important;
            padding: 8px 6px !important;
            text-align: center !important;
            justify-content: center !important;
        }
        #tab-appointments #dior-step2-docbooker-form > .d-flex.border-top,
        #tab-appointments #dior-step-pane-3 .d-flex.justify-content-center {
            flex-direction: column !important;
            align-items: stretch !important;
        }
        #tab-appointments #dior-step2-docbooker-form > .d-flex.border-top > .btn,
        #tab-appointments #dior-step-pane-3 .d-flex.justify-content-center > .btn {
            width: 100% !important;
            justify-content: center !important;
        }

        /* Keep input-group icon and search input on a single row on mobile */
        #tab-appointments .input-group {
            display: flex !important;
            flex-wrap: nowrap !important;
            align-items: center !important;
            width: 100% !important;
        }
        #tab-appointments .input-group > .input-group-text {
            flex: 0 0 auto !important;
            width: auto !important;
            min-height: 38px !important;
        }
        #tab-appointments .input-group > .form-control {
            flex: 1 1 auto !important;
            width: 1% !important;
            min-width: 0 !important;
            min-height: 38px !important;
            height: 38px !important;
            font-size: 14px !important;
        }

        /* Center empty state rows inside mobile viewport without horizontal scroll */
        #tab-appointments #dior-today-appointments-table,
        #tab-appointments #dior-upcoming-appointments-table,
        #tab-appointments #dior-past-appointments-table {
            min-width: 640px;
        }
        #tab-appointments #dior-today-appointments-table:has(tr.dior-empty-state),
        #tab-appointments #dior-upcoming-appointments-table:has(tr.dior-empty-state),
        #tab-appointments #dior-past-appointments-table:has(tr.dior-empty-state) {
            min-width: 100% !important;
        }

        #tab-appointments tr.dior-empty-state td {
            white-space: normal !important;
            text-align: center !important;
        }

        /* Mobile Today Appointments: convert the existing table rows into compact cards.
           This block only runs on small screens; desktop table remains unchanged. */
        #tab-appointments #dior-subtab-today > .card {
            padding: 14px !important;
            border: 1px solid #d6e5f6 !important;
            border-radius: 18px !important;
            box-shadow: 0 5px 18px rgba(20, 55, 100, .06) !important;
        }
        #tab-appointments #dior-subtab-today .table-responsive {
            overflow: visible !important;
            border: 0 !important;
        }
        #tab-appointments #dior-today-appointments-table,
        #tab-appointments #dior-today-appointments-table tbody {
            display: block !important;
            width: 100% !important;
            min-width: 0 !important;
        }
        #tab-appointments #dior-today-appointments-table thead {
            display: none !important;
        }
        #tab-appointments #dior-today-appointments-table tbody tr:not(.dior-empty-state) {
            position: relative;
            display: grid !important;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: 12px 10px;
            width: 100%;
            min-width: 0 !important;
            margin: 0 0 13px !important;
            padding: 14px !important;
            border: 1px solid #d8e6f6 !important;
            border-radius: 15px !important;
            background: #fff !important;
            box-shadow: 0 3px 12px rgba(12, 36, 78, .045);
        }
        #tab-appointments #dior-today-appointments-table tbody tr:not(.dior-empty-state):last-child {
            margin-bottom: 0 !important;
        }
        #tab-appointments #dior-today-appointments-table tbody td {
            display: block !important;
            width: auto !important;
            min-width: 0 !important;
            padding: 0 !important;
            border: 0 !important;
            text-align: left !important;
            white-space: normal !important;
            overflow-wrap: anywhere;
            font-size: 12px !important;
            line-height: 1.45;
        }
        #tab-appointments #dior-today-appointments-table tbody td:first-child {
            position: absolute;
            top: 12px;
            right: 12px;
            z-index: 2;
            width: 24px !important;
        }
        #tab-appointments #dior-today-appointments-table tbody td:nth-child(2) {
            grid-column: 1 / -1;
            padding-right: 28px !important;
            color: #10264b;
            font-size: 15px !important;
            font-weight: 700;
            line-height: 1.35;
        }
        #tab-appointments #dior-today-appointments-table tbody td:nth-child(2)::before {
            content: "DOCTOR";
            display: block;
            margin-bottom: 5px;
            color: #7a8ba4;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .55px;
        }
        #tab-appointments #dior-today-appointments-table tbody td:nth-child(3)::before,
        #tab-appointments #dior-today-appointments-table tbody td:nth-child(4)::before,
        #tab-appointments #dior-today-appointments-table tbody td:nth-child(5)::before,
        #tab-appointments #dior-today-appointments-table tbody td:nth-child(6)::before,
        #tab-appointments #dior-today-appointments-table tbody td:nth-child(7)::before,
        #tab-appointments #dior-today-appointments-table tbody td:nth-child(8)::before {
            display: block;
            margin-bottom: 4px;
            color: #7a8ba4;
            font-size: 10px;
            font-weight: 700;
            line-height: 1.3;
            letter-spacing: .45px;
            text-transform: uppercase;
        }
        #tab-appointments #dior-today-appointments-table tbody td:nth-child(3)::before { content: "Specialization"; }
        #tab-appointments #dior-today-appointments-table tbody td:nth-child(4)::before { content: "Date"; }
        #tab-appointments #dior-today-appointments-table tbody td:nth-child(5)::before { content: "Time"; }
        #tab-appointments #dior-today-appointments-table tbody td:nth-child(6)::before { content: "Treatment"; }
        #tab-appointments #dior-today-appointments-table tbody td:nth-child(7)::before { content: "Contact"; }
        #tab-appointments #dior-today-appointments-table tbody td:nth-child(8)::before { content: "Status"; }
        #tab-appointments #dior-today-appointments-table tbody td:nth-child(6) .badge,
        #tab-appointments #dior-today-appointments-table tbody td:nth-child(8) .badge {
            display: inline-flex;
            max-width: 100%;
            white-space: normal !important;
            line-height: 1.35;
        }
        #tab-appointments #dior-today-appointments-table tbody td:nth-child(9) {
            grid-column: 1 / -1;
            padding-top: 11px !important;
            border-top: 1px solid #e7eef8 !important;
        }
        #tab-appointments #dior-today-appointments-table tbody td:nth-child(9) .cell-actions {
            display: grid !important;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px !important;
            width: 100%;
        }
        #tab-appointments #dior-today-appointments-table tbody td:nth-child(9) .action-icon-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100% !important;
            min-width: 0 !important;
            min-height: 40px;
            padding: 8px !important;
            border: 1px solid #dce7f5 !important;
            border-radius: 10px !important;
            background: #f8fbff !important;
        }
        #tab-appointments #dior-today-appointments-table tbody td:nth-child(9) .action-icon-btn .fas {
            font-size: 15px;
        }
        #tab-appointments #dior-subtab-today #dior-today-page-count {
            font-size: 11px;
            line-height: 1.5;
        }
        #tab-appointments #dior-subtab-today #dior-today-pagination .dior-page-btn {
            min-width: 34px;
            min-height: 34px;
            border-radius: 9px;
        }
    }

    @media (max-width: 420px) {
        #tab-appointments #dior-subtab-today > .card {
            padding: 10px !important;
        }
        #tab-appointments #dior-today-appointments-table tbody tr:not(.dior-empty-state) {
            padding: 12px !important;
            gap: 10px 8px;
        }
        #tab-appointments #dior-today-appointments-table tbody td {
            font-size: 11px !important;
        }
        #tab-appointments #dior-today-appointments-table tbody td:nth-child(2) {
            font-size: 14px !important;
        }
    }


    /* Final mobile correction: toolbar controls and empty-state row must never collapse vertically. */
    @media (max-width: 767.98px) {
        #tab-appointments #dior-subtab-today > .card > .d-flex:first-child > div:last-child {
            display: grid !important;
            grid-template-columns: 42px minmax(64px, 1fr) 42px 42px !important;
            grid-auto-flow: row;
            align-items: center !important;
            gap: 7px !important;
            width: 100% !important;
            min-width: 0 !important;
        }
        #tab-appointments #dior-subtab-today > .card > .d-flex:first-child > div:last-child > .input-group {
            grid-column: 1 / -1 !important;
            grid-row: 1 !important;
            width: 100% !important;
            min-width: 0 !important;
        }
        #tab-appointments #dior-subtab-today > .card > .d-flex:first-child > div:last-child > button {
            grid-row: 2 !important;
            grid-column: auto !important;
            justify-self: stretch !important;
            width: 100% !important;
            min-width: 0 !important;
            min-height: 42px !important;
            padding: 8px 5px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            white-space: nowrap !important;
            overflow: visible !important;
            line-height: 1.2 !important;
        }
        #tab-appointments #dior-subtab-today > .card > .d-flex:first-child > div:last-child > button[title="Book Appointment"] {
            min-width: 64px !important;
            gap: 5px !important;
            font-size: 12px !important;
        }
        #tab-appointments #dior-today-appointments-table {
            min-width: 0 !important;
            width: 100% !important;
            table-layout: fixed !important;
        }
        #tab-appointments #dior-today-appointments-table tbody tr.dior-empty-state {
            display: block !important;
            width: 100% !important;
            min-width: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
            border: 0 !important;
            background: transparent !important;
        }
        #tab-appointments #dior-today-appointments-table tbody tr.dior-empty-state td {
            display: block !important;
            width: 100% !important;
            min-width: 0 !important;
            max-width: 100% !important;
            padding: 22px 12px !important;
            border: 1px solid #d8e6f6 !important;
            border-radius: 14px !important;
            background: #f8fbff !important;
            color: #64748b !important;
            text-align: center !important;
            white-space: normal !important;
            word-break: normal !important;
            overflow-wrap: anywhere !important;
            writing-mode: horizontal-tb !important;
            text-orientation: mixed !important;
            line-height: 1.5 !important;
            box-sizing: border-box !important;
        }
        #tab-appointments #dior-subtab-today .table-responsive > .small,
        #tab-appointments #dior-subtab-today .table-responsive > p {
            display: none !important;
        }
        #tab-appointments #dior-today-appointments-table tbody tr:not(.dior-empty-state) td:nth-child(2) .dior-appointment-doctor-name {
            white-space: normal !important;
            overflow-wrap: anywhere !important;
        }
    }

</style>


    <!-- Breadcrumb Header -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 px-3 px-md-4 pt-2">
        <div>
            <h4 class="mb-0 text-dark" id="dior-appt-main-title" style="font-size:20px;font-weight:700;">Telehealth Appointments</h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list">
                <li><a href="#"><i class="fas fa-house" style="font-size:14px;color:#4F46E5;"></i></a></li>
                <li><span style="color:#94A3B8;">/</span></li>
                <li class="active"><span id="dior-appt-breadcrumb-title">Telehealth Appointments</span></li>
            </ul>
        </div>
    </div>

    <!-- Subtab Navigation Bar -->
    <div class="dior-subtab-nav-bar px-3 px-md-4 mb-4">
        <button type="button" class="dior-subtab-pill btn" data-appt-subtab="book" onclick="diorSwitchApptSubTab('book')">
            <i class="fas fa-calendar-plus"></i> <span class="dior-subtab-label">Book Appointment</span>
        </button>
        <button type="button" class="dior-subtab-pill btn active" data-appt-subtab="today" onclick="diorSwitchApptSubTab('today')">
            <i class="fas fa-calendar-check"></i> <span class="dior-subtab-label">Today Appointments</span>
        </button>
        <button type="button" class="dior-subtab-pill btn" data-appt-subtab="upcoming" onclick="diorSwitchApptSubTab('upcoming')">
            <i class="fas fa-clock"></i> <span class="dior-subtab-label">Upcoming Appointments</span>
        </button>
        <button type="button" class="dior-subtab-pill btn" data-appt-subtab="past" onclick="diorSwitchApptSubTab('past')">
            <i class="fas fa-history"></i> <span class="dior-subtab-label">Past Appointments</span>
        </button>
    </div>

    <?php
    // Robust date normalizer: handles Y-m-d, d-m-Y, m-d-Y, d/m/Y etc.
    if (!function_exists('dior_normalize_date')) {
        function dior_normalize_date($raw)
        {
            if (empty($raw))
                return '';
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw))
                return $raw;
            foreach (['d-m-Y', 'd/m/Y', 'd.m.Y', 'm-d-Y', 'm/d/Y'] as $fmt) {
                $dt = DateTime::createFromFormat($fmt, $raw);
                if ($dt)
                    return $dt->format('Y-m-d');
            }
            $ts = strtotime($raw);
            return $ts ? date('Y-m-d', $ts) : '';
        }
    }


    // Prepare appointment data as JSON for JS calendar
    $cal_apt_dates = [];
    $apt_total = count($appointments);
    $apt_upcoming = 0;
    $apt_completed = 0;
    $apt_video = 0;
    $today_ymd = current_time('Y-m-d');
    foreach ($appointments as $idx => $apt) {
        $a_date = !empty($apt['date']) ? $apt['date'] : (!empty($apt['appt_date']) ? $apt['appt_date'] : '');
        $a_status = !empty($apt['status']) ? $apt['status'] : 'Status unavailable';
        $a_type = !empty($apt['type']) ? $apt['type'] : (!empty($apt['visit_type']) ? $apt['visit_type'] : '');
        $status_lc = strtolower($a_status);
        $type_lc = strtolower($a_type);
        if (strpos($type_lc, 'video') !== false || strpos($type_lc, 'telehealth') !== false) {
            $apt_video++;
        }
        if (in_array($status_lc, ['confirmed', 'scheduled', 'in-queue', 'pending', 'active']))
            $apt_upcoming++;
        if (in_array($status_lc, ['completed', 'done', 'past']))
            $apt_completed++;
        if ($a_date) {
            $ymd = dior_normalize_date($a_date);
            if ($ymd)
                $cal_apt_dates[$ymd] = true;
        }
    }
    $cal_dates_json = json_encode(array_keys($cal_apt_dates));

    // Build appointment cards JSON for JS filtering
    $apt_cards = [];

    foreach ($appointments as $idx => $apt) {
        $a_id = !empty($apt['id']) ? $apt['id'] : (!empty($apt['appt_uid']) ? $apt['appt_uid'] : '');
        $a_doc = !empty($apt['provider']) ? $apt['provider'] : (!empty($apt['doctor_name']) ? $apt['doctor_name'] : '');
        $a_spec = !empty($apt['provider_spec']) ? $apt['provider_spec'] : '';
        $a_date_raw = !empty($apt['date']) ? $apt['date'] : (!empty($apt['appt_date']) ? $apt['appt_date'] : '');
        $a_time = !empty($apt['time']) ? $apt['time'] : (!empty($apt['appt_time']) ? $apt['appt_time'] : '');
        $a_status = !empty($apt['status']) ? $apt['status'] : 'Scheduled';
        $a_type = !empty($apt['type']) ? $apt['type'] : (!empty($apt['visit_type']) ? $apt['visit_type'] : 'Video Visit');
        $a_join = !empty($apt['join_url']) ? $apt['join_url'] : '';
        $a_notes = !empty($apt['notes']) ? $apt['notes'] : '';
        $a_condition = !empty($apt['condition']) ? $apt['condition'] : (!empty($apt['condition_name']) ? $apt['condition_name'] : '');
        $a_ymd = dior_normalize_date($a_date_raw);
        $apt_cards[] = [
            'id' => $a_id,
            'doc' => $a_doc,
            'spec' => $a_spec,
            'date' => $a_date_raw,
            'ymd' => $a_ymd,
            'time' => $a_time,
            'status' => $a_status,
            'type' => $a_type,
            'join_url' => $a_join,
            'notes' => $a_notes,
            'condition' => $a_condition,
        ];
    }
    $apt_cards_json = json_encode($apt_cards, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

    ?>

    <!-- Appointment Content Area -->
    <div class="medidash-appt-wrap">

        <!-- 1. Book Appointment -->
        <div class="dior-subtab-panel" id="dior-subtab-book" style="display: none;">
            <?php
            $appt_user_id = !empty($user_id) ? (int)$user_id : get_current_user_id();
            $appt_hipaa = (!empty($hipaa_intake) && is_array($hipaa_intake)) ? $hipaa_intake : Dior_Patient_Portal_Data::get_normalized_hipaa_intake($appt_user_id);
            $appt_has_intake = !empty($appt_hipaa) && (!empty($appt_hipaa['is_submitted']) || !empty($appt_hipaa['submitted_at']));

            // Resolve an actual published provider instead of assuming a fixed post/user ID.
            $appt_doctor_id = 0;
            $appt_doctor_post = null;
            $published_docs = get_posts([
                'post_type' => 'wpddb_doctor',
                'post_status' => 'publish',
                'posts_per_page' => 1,
                'orderby' => 'title',
                'order' => 'ASC',
            ]);
            if (!empty($published_docs)) {
                $appt_doctor_post = $published_docs[0];
                $appt_doctor_id = (int) $appt_doctor_post->ID;
            }

            $appt_doctor_name = $appt_doctor_post ? $appt_doctor_post->post_title : '';
            $appt_doctor_spec = $appt_doctor_post
                ? (get_post_meta($appt_doctor_id, 'wpddb_doctor_speciality', true) ?: '')
                : '';
            $appt_doctor_user_id = ($appt_doctor_id && class_exists('Dior_Doctor_Resolver'))
                ? (int) Dior_Doctor_Resolver::resolve_doctor_user_id($appt_doctor_id)
                : 0;
            $appt_doctor_avatar = '';
            if ($appt_doctor_user_id) {
                $appt_doctor_avatar = get_user_meta($appt_doctor_user_id, 'dior_profile_image', true)
                    ?: get_user_meta($appt_doctor_user_id, 'doctor_avatar', true)
                    ?: get_avatar_url($appt_doctor_user_id, ['size' => 120]);
            }

            $appt_patient_profile = ($appt_user_id && class_exists('Dior_Patient_Portal_Data')) ? Dior_Patient_Portal_Data::get_patient_profile($appt_user_id) : [];
            $appt_patient_name = !empty($appt_patient_profile['full_name']) ? $appt_patient_profile['full_name'] : '';
            $appt_patient_email = !empty($appt_patient_profile['email']) ? $appt_patient_profile['email'] : '';
            $appt_patient_phone = !empty($appt_patient_profile['phone']) ? $appt_patient_profile['phone'] : (!empty($appt_hipaa['phone']) ? $appt_hipaa['phone'] : '');
            $appt_patient_reason = !empty($appt_hipaa['service_condition']) ? $appt_hipaa['service_condition'] : (!empty($appt_hipaa['symptoms']) ? $appt_hipaa['symptoms'] : '');
            $appt_today_ymd = current_time('Y-m-d');
            ?>

            <!-- ── ELEGANT FULL-WIDTH 3-STEP TIMELINE ── -->
            <div class="card border-0 shadow-sm rounded-4 px-3 px-md-4 py-3 py-md-4 mb-4 mb-md-5 bg-white" id="dior-booking-stepper-card">
                <!-- Hidden DOM elements to maintain full JS backward compatibility -->
                <span id="dior-stepper-step-heading" class="d-none">STEP 1 OF 3</span>
                <span id="dior-stepper-est-time" class="d-none">Est. 4 mins remaining</span>
                <span id="dior-stepper-circle-text" class="d-none">1/3</span>
                <svg class="d-none"><circle id="dior-stepper-circle-svg"></circle></svg>

                <div class="row g-0 align-items-center my-0" id="dior-stepper-steps-row">
                    <!-- Step 1: Clinical Intake -->
                    <div class="col-12 col-md-4 pe-md-3">
                        <div id="dior-step-item-1" class="d-flex align-items-center gap-3 p-2 rounded-3" onclick="diorNavigateBookingStep(1)" style="cursor: pointer;">
                            <div id="dior-step-icon-1" class="rounded-circle d-flex align-items-center justify-content-center bg-primary text-white fw-bold shadow-sm flex-shrink-0" style="width: 38px; height: 38px; font-size: 14px; transition: all 0.25s ease;">
                                1
                            </div>
                            <div class="min-w-0 flex-grow-1">
                                <div class="d-flex align-items-center gap-2 mb-0.5">
                                    <span id="dior-step-tag-1" class="text-uppercase fw-bold text-primary" style="font-size: 10px; letter-spacing: 0.5px;">STEP 1</span>
                                    <span id="dior-step-badge-1" class="badge bg-primary-subtle text-primary rounded-pill px-2 py-0.5" style="font-size: 9px; display: inline-block !important;">Current</span>
                                </div>
                                <div id="dior-step-title-1" class="fw-bold text-dark text-truncate" style="font-size: 14px; line-height: 1.3;">Clinical Intake</div>
                                <div class="text-secondary text-truncate" style="font-size: 11px !important; line-height: 1.3; font-weight: 500;">Health Questionnaire &amp; History</div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Doctor & Time Slot -->
                    <div class="col-12 col-md-4 px-md-3 border-start-md" style="border-left: 1px solid #E2E8F0;">
                        <div id="dior-step-item-2" class="d-flex align-items-center gap-3 p-2 rounded-3" onclick="diorNavigateBookingStep(2)" style="cursor: pointer;">
                            <div id="dior-step-icon-2" class="rounded-circle d-flex align-items-center justify-content-center fw-bold flex-shrink-0" style="width: 38px; height: 38px; font-size: 14px; background-color: #E2E8F0; color: #64748B; transition: all 0.25s ease;">
                                2
                            </div>
                            <div class="min-w-0 flex-grow-1">
                                <div class="d-flex align-items-center gap-2 mb-0.5">
                                    <span id="dior-step-tag-2" class="text-uppercase fw-semibold text-secondary" style="font-size: 10px; letter-spacing: 0.5px;">STEP 2</span>
                                    <span id="dior-step-badge-2" class="badge bg-primary-subtle text-primary rounded-pill px-2 py-0.5" style="font-size: 9px; display: none !important;">Current</span>
                                </div>
                                <div id="dior-step-title-2" class="fw-bold text-secondary text-truncate" style="font-size: 14px; line-height: 1.3;">Provider &amp; Time Slot</div>
                                <div class="text-secondary text-truncate" style="font-size: 11px !important; line-height: 1.3; font-weight: 500;">Doctor &amp; Availability</div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Confirmation -->
                    <div class="col-12 col-md-4 ps-md-3 border-start-md" style="border-left: 1px solid #E2E8F0;">
                        <div id="dior-step-item-3" class="d-flex align-items-center gap-3 p-2 rounded-3" onclick="diorNavigateBookingStep(3)" style="cursor: pointer;">
                            <div id="dior-step-icon-3" class="rounded-circle d-flex align-items-center justify-content-center fw-bold flex-shrink-0" style="width: 38px; height: 38px; font-size: 14px; background-color: #E2E8F0; color: #64748B; transition: all 0.25s ease;">
                                3
                            </div>
                            <div class="min-w-0 flex-grow-1">
                                <div class="d-flex align-items-center gap-2 mb-0.5">
                                    <span id="dior-step-tag-3" class="text-uppercase fw-semibold text-secondary" style="font-size: 10px; letter-spacing: 0.5px;">STEP 3</span>
                                    <span id="dior-step-badge-3" class="badge bg-primary-subtle text-primary rounded-pill px-2 py-0.5" style="font-size: 9px; display: none !important;">Current</span>
                                </div>
                                <div id="dior-step-title-3" class="fw-bold text-secondary text-truncate" style="font-size: 14px; line-height: 1.3;">Confirmation</div>
                                <div class="text-secondary text-truncate" style="font-size: 11px !important; line-height: 1.3; font-weight: 500;">Summary &amp; Video Link</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Hidden line elements for JS backward compatibility -->
                <div class="d-none">
                    <div id="dior-step-line-1"></div>
                    <div id="dior-step-line-2"></div>
                </div>
            </div>

            <!-- ── STEP 1: CLINICAL INTAKE (100% Bootstrap 5 - Clean, Seamless, No Background Card) ── -->
            <div id="dior-step-pane-1" class="dior-step-pane">
                <!-- Mandatory Intake Reminder Alert (Appears only if user attempts to skip) -->
                <div id="dior-intake-mandatory-alert" class="alert alert-warning border-0 rounded-3 py-2 px-3 small d-none mb-3">
                    <i class="fas fa-circle-exclamation me-2"></i><strong>Clinical Intake Required:</strong> Please complete and submit the medical questionnaire below before selecting your provider and time slot.
                </div>

                <!-- Clinical Intake Form (BAA warning notice cropped out cleanly) -->
                <div class="w-100 mb-4 overflow-hidden position-relative" style="border-radius: 16px;">
                    <div style="margin-top: -105px;">
                        <?php if (shortcode_exists('hipaatizer')): ?>
                            <?php echo do_shortcode('[hipaatizer id="01a12050-38e5-74ba-967b-60dacc2bd7f7"]'); ?>
                        <?php else: ?>
                            <iframe id="dior-hipaatizer-tab-iframe"
                                src="https://app.hipaatizer.com/workflow/01a12050-38e5-74ba-967b-60dacc2bd7f7"
                                allow="microphone; camera; payment" title="Clinical Intake &amp; Booking Form"
                                style="width: 100%; max-width: 100%; min-width: 100%; height: 850px; min-height: 750px; border: none; display: block; background: transparent;"
                                loading="lazy"></iframe>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ── STEP 2: DOCBOOKER APPOINTMENT SCHEDULING ── -->
            <div id="dior-step-pane-2" class="dior-step-pane d-none">
                <!-- Verified Status Strip -->
                <div class="card border-0 shadow-sm rounded-4 p-3 px-4 mb-4 bg-white d-flex flex-row align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1 small fw-semibold">
                            <i class="fas fa-check-circle me-1"></i> Intake Form Verified
                        </span>
                        <span class="text-secondary small fw-medium">Clinical questionnaire on file. Now choose your provider, format, and preferred time slot.</span>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-1 small fw-semibold" onclick="diorGoToBookingStep(1);">
                        <i class="fas fa-pen me-1"></i> Edit Intake
                    </button>
                </div>

                <!-- Attending Physician Card -->
                <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 mb-4 bg-white">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="position-relative">
                                <img src="<?php echo esc_url($appt_doctor_avatar); ?>" alt="<?php echo esc_attr($appt_doctor_name); ?>" class="rounded-circle border shadow-sm" style="width: 58px; height: 58px; object-fit: cover; flex-shrink: 0;">
                                <span class="position-absolute bottom-0 end-0 p-1 bg-success border border-white border-2 rounded-circle" title="Available for Telehealth"></span>
                            </div>
                            <div class="min-w-0">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-0.5 small fw-semibold">Attending Physician</span>
                                    <span class="badge bg-light text-secondary border rounded-pill px-2 py-0.5 small"><i class="fas fa-certificate text-primary me-1"></i> Board Certified</span>
                                </div>
                                <h5 class="fw-bold text-dark mb-0 text-truncate"><?php echo esc_html($appt_doctor_name); ?></h5>
                                <div class="text-secondary small text-truncate mt-0.5"><?php echo esc_html($appt_doctor_spec); ?> &bull; Dior Medical Urgent Care</div>
                            </div>
                        </div>
                        <div class="text-end d-none d-md-block">
                            <span class="badge bg-light text-dark border rounded-pill px-3 py-1.5 small fw-semibold">
                                <i class="fas fa-video text-primary me-1"></i> Telehealth Verified
                            </span>
                        </div>
                    </div>
                </div>

                <!-- DocBooker Inline Form -->
                <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 mb-4 bg-white">
                    <?php
                    $step2_initial_slots = class_exists('Dior_Medical_Dashboard')
                        ? Dior_Medical_Dashboard::get_doctor_slots_for_date($appt_doctor_id, $appt_today_ymd)
                        : [];
                    $step2_first_slot_time = '';
                    foreach ($step2_initial_slots as $st) {
                        if (!empty($st['is_available'])) {
                            $step2_first_slot_time = $st['time'];
                            break;
                        }
                    }
                    $step2_default_time = $step2_first_slot_time ?: '09:00 AM - 10:00 AM';
                    ?>
                    <form id="dior-step2-docbooker-form" onsubmit="diorSubmitInlineBooking(event)">
                        <!-- Hidden meta -->
                        <input type="hidden" name="action" value="dior_patient_book_appointment">
                        <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('dior_portal_nonce')); ?>">
                        <input type="hidden" name="doctor_id" id="dior-step2-doctor-id" value="<?php echo esc_attr($appt_doctor_id); ?>">
                        <input type="hidden" name="doctorId" value="<?php echo esc_attr($appt_doctor_id); ?>">
                        <input type="hidden" name="doctor_name" id="dior-step2-doctor-name" value="<?php echo esc_attr($appt_doctor_name); ?>">
                        <input type="hidden" name="provider" value="<?php echo esc_attr($appt_doctor_name); ?>">
                        <input type="hidden" name="selected_time" id="dior-step2-selected-time" value="<?php echo esc_attr($step2_default_time); ?>">
                        <input type="hidden" name="time" id="dior-step2-final-time" value="<?php echo esc_attr($step2_default_time); ?>">
                        <input type="hidden" name="service_type" id="dior-step2-service-type" value="Video Visit (HD)">
                        <input type="hidden" name="type" id="dior-step2-final-type" value="Video Visit (HD)">
                        <input type="hidden" name="condition" id="dior-step2-final-condition" value="<?php echo esc_attr($appt_patient_reason); ?>">

                        <!-- 1. Consultation Format -->
                        <div class="mb-4">
                            <label class="small text-uppercase text-secondary fw-bold mb-2 d-block">1. Select Consultation Format</label>
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <div class="p-3 rounded-4 border border-2 border-primary bg-white shadow-sm d-flex align-items-center justify-content-between cursor-pointer dior-service-card-item active" id="dior-fmt-video" onclick="diorSelectInlineFormat('Video Visit (HD)', this)" style="cursor: pointer; transition: all 0.2s ease;">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; flex-shrink: 0;">
                                                <i class="fas fa-video"></i>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark fs-6">Telehealth Video Visit (HD)</div>
                                                <div class="small text-secondary">Secure encrypted video consultation</div>
                                            </div>
                                        </div>
                                        <i class="fas fa-circle-check text-primary fs-5 dior-fmt-check"></i>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="p-3 rounded-4 border bg-white d-flex align-items-center justify-content-between cursor-pointer dior-service-card-item opacity-75" id="dior-fmt-phone" onclick="diorSelectInlineFormat('Phone Consultation', this)" style="cursor: pointer; transition: all 0.2s ease;">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="rounded-circle bg-light text-secondary border d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; flex-shrink: 0;">
                                                <i class="fas fa-phone"></i>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark fs-6">Phone Consultation</div>
                                                <div class="small text-secondary">Direct call to your registered phone</div>
                                            </div>
                                        </div>
                                        <i class="far fa-circle text-secondary fs-5 dior-fmt-check"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Date & Time Selection -->
                        <div class="mb-4">
                            <label class="small text-uppercase text-secondary fw-bold mb-2 d-block">2. Consultation Date &amp; Available Slot</label>
                            <div class="row g-3 mb-3">
                                <div class="col-12 col-md-4">
                                    <label for="dior-step2-date-input" class="small fw-semibold text-dark mb-1">Consultation Date *</label>
                                    <input type="date" id="dior-step2-date-input" name="date" class="form-control rounded-3 py-2 px-3"
                                           min="<?php echo esc_attr($appt_today_ymd); ?>"
                                           value="<?php echo esc_attr($appt_today_ymd); ?>"
                                           onchange="diorOnStep2DateChanged(this.value)" required>
                                </div>
                                <div class="col-12 col-md-8">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="small fw-semibold text-dark">Available Time Slots *</span>
                                        <span id="dior-step2-slots-spinner" class="small text-muted d-none">
                                            <i class="fas fa-circle-notch fa-spin text-primary me-1"></i> Checking live slots...
                                        </span>
                                    </div>
                                    <div id="dior-step2-slots-grid" class="d-flex flex-wrap gap-2">
                                        <?php
                                        $step2_has_available = false;
                                        $step2_first_selected = false;
                                        foreach ($step2_initial_slots as $s_item):
                                            if (!empty($s_item['is_available'])):
                                                $step2_has_available = true;
                                                $is_active = !$step2_first_selected;
                                                if ($is_active) {
                                                    $step2_first_selected = true;
                                                }
                                        ?>
                                            <button type="button"
                                                    class="btn <?php echo $is_active ? 'btn-primary active' : 'btn-outline-primary'; ?> rounded-3 btn-sm py-2 px-3 dior-step2-slot-btn"
                                                    data-time="<?php echo esc_attr($s_item['time']); ?>"
                                                    onclick="diorSelectStep2Slot('<?php echo esc_js($s_item['time']); ?>', this)">
                                                <?php echo esc_html($s_item['time']); ?>
                                            </button>
                                        <?php
                                            endif;
                                        endforeach;
                                        if (!$step2_has_available):
                                        ?>
                                            <div class="w-100 text-center py-3 px-2 text-muted small">
                                                <i class="fas fa-clock me-1 text-warning"></i> No further consultation slots are available for today. Please select a future date to book an appointment.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 3. Patient Details -->
                        <div class="mb-4">
                            <label class="small text-uppercase text-secondary fw-bold mb-2 d-block">3. Patient Contact &amp; Reason</label>
                            <div class="row g-3">
                                <div class="col-12 col-md-4">
                                    <label for="dior-step2-patient-name" class="small fw-semibold text-dark mb-1">Full Name *</label>
                                    <input type="text" name="name" id="dior-step2-patient-name" class="form-control rounded-3 py-2 px-3" value="<?php echo esc_attr($appt_patient_name); ?>" placeholder="Your Full Name" required>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label for="dior-step2-patient-email" class="small fw-semibold text-dark mb-1">Email Address *</label>
                                    <input type="email" name="email" id="dior-step2-patient-email" class="form-control rounded-3 py-2 px-3" value="<?php echo esc_attr($appt_patient_email); ?>" placeholder="you@example.com" required>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label for="dior-step2-patient-phone" class="small fw-semibold text-dark mb-1">Phone Number *</label>
                                    <input type="tel" name="phone" id="dior-step2-patient-phone" class="form-control rounded-3 py-2 px-3" value="<?php echo esc_attr($appt_patient_phone); ?>" placeholder="(555) 000-0000" required>
                                </div>
                                <div class="col-12">
                                    <label for="dior-step2-patient-notes" class="small fw-semibold text-dark mb-1">Chief Complaint / Symptoms</label>
                                    <input type="text" name="notes" id="dior-step2-patient-notes" class="form-control rounded-3 py-2 px-3" value="<?php echo esc_attr($appt_patient_reason); ?>" placeholder="e.g. Follow-up consultation or symptoms">
                                </div>
                            </div>
                        </div>

                        <!-- Feedback Box -->
                        <div id="dior-step2-status-box" class="alert alert-danger border-0 rounded-4 p-3 mb-3 d-none"></div>

                        <!-- Actions -->
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 pt-2 border-top">
                            <button type="button" class="btn btn-outline-secondary rounded-3 fw-semibold py-2 px-4" onclick="diorGoToBookingStep(1);">
                                <i class="fas fa-arrow-left me-1"></i> Back to Step 1
                            </button>
                            <button type="submit" id="dior-step2-submit-btn" class="btn btn-primary rounded-3 fw-semibold py-2 px-4 shadow-sm">
                                <span class="dior-step2-btn-text"><i class="fas fa-calendar-check me-2"></i> Confirm &amp; Schedule Appointment</span>
                                <span class="dior-step2-btn-spinner d-none"><i class="fas fa-circle-notch fa-spin me-2"></i> Scheduling Appointment...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ── STEP 3: THANK YOU MESSAGE ── -->
            <div id="dior-step-pane-3" class="dior-step-pane d-none py-3 py-md-4 mb-5">
                <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 my-2 mb-5 bg-white text-center">
                    <div class="mx-auto mb-3 rounded-circle bg-success-subtle text-success d-flex align-items-center justify-content-center shadow-sm" style="width: 76px; height: 76px;">
                        <i class="fas fa-circle-check fs-1"></i>
                    </div>
                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1.5 small fw-semibold mb-3 mx-auto">
                        Step 3 Complete &bull; Appointment Confirmed
                    </span>
                    <h3 class="fw-bold text-dark mb-3">Thank You! Your Appointment is Confirmed</h3>
                    <p class="text-secondary small mb-4 mx-auto" style="max-width: 600px; line-height: 1.6;">
                        Your clinical intake form has been recorded, and your appointment has been successfully scheduled with your attending physician. A confirmation email and calendar invitation have been sent.
                    </p>

                    <!-- Appointment Summary Box (Clean Bootstrap 5 Card) -->
                    <div class="card border border-light-subtle rounded-4 p-4 p-md-4 mb-4 mx-auto bg-light text-start shadow-sm" style="max-width: 680px;">
                        <div class="row g-3">
                            <div class="col-12 col-sm-6">
                                <div class="small text-uppercase text-secondary fw-semibold">Attending Physician</div>
                                <div class="fw-bold text-dark fs-6" id="dior-ty-doctor"><?php echo esc_html($appt_doctor_name); ?></div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <div class="small text-uppercase text-secondary fw-semibold">Consultation Format</div>
                                <div class="fw-bold text-dark fs-6" id="dior-ty-type">Video Visit (HD)</div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <div class="small text-uppercase text-secondary fw-semibold">Date &amp; Time</div>
                                <div class="fw-bold text-dark fs-6" id="dior-ty-datetime"><?php echo esc_html(date('M j, Y')); ?> at 09:00 AM</div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <div class="small text-uppercase text-secondary fw-semibold">Confirmation ID</div>
                                <div class="fw-bold text-primary fs-6" id="dior-ty-uid">APT-0000</div>
                            </div>
                            <div class="col-12 border-top pt-2">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <div class="small text-uppercase text-secondary fw-semibold">Intake Status</div>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1.5 small fw-semibold">
                                        <i class="fas fa-check-circle me-1"></i> HIPAAtizer Intake Verified &amp; Attached
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-flex align-items-center justify-content-center gap-3 flex-wrap pt-2 pb-2">
                        <button type="button" class="btn btn-primary rounded-3 fw-semibold py-2.5 px-4 shadow-sm" onclick="diorSwitchApptSubTab('today');">
                            <i class="fas fa-calendar-days me-1"></i> View My Appointments
                        </button>
                        <button type="button" class="btn btn-outline-primary rounded-3 fw-semibold py-2.5 px-4" onclick="diorResetBookingFlow();">
                            <i class="fas fa-plus me-1"></i> Book Another Appointment
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Today Appointments -->
        <?php
        $today_mock = [];
        $today_ymd = current_time('Y-m-d');
        foreach ((array) $appointments as $apt_row) {
            $date_raw = $apt_row['date'] ?? ($apt_row['appt_date'] ?? '');
            $date_ymd = function_exists('dior_normalize_date') ? dior_normalize_date($date_raw) : $date_raw;
            $status = sanitize_text_field($apt_row['status'] ?? '');
            if ($date_ymd !== $today_ymd || in_array(strtolower($status), ['cancelled', 'canceled'], true))
                continue;
            // Prefer the appointment-specific meeting URL; fall back to the linked doctor's configured Zoom link.
            $today_join_url = trim((string) ($apt_row['join_url'] ?? ''));
            if ($today_join_url === '') {
                $today_doctor_user_id = absint($apt_row['doctor_user_id'] ?? 0);
                if (!$today_doctor_user_id && !empty($apt_row['doctor_id']) && function_exists('dior_find_doctor_user_id')) {
                    $today_doctor_user_id = absint(dior_find_doctor_user_id(
                        absint($apt_row['doctor_id']),
                        (string) ($apt_row['provider'] ?? ($apt_row['doctor_name'] ?? ''))
                    ));
                }
                if ($today_doctor_user_id) {
                    $configured_zoom_url = trim((string) get_user_meta($today_doctor_user_id, 'doctor_zoom_link', true));
                    if ($configured_zoom_url !== '' && wp_http_validate_url($configured_zoom_url)) {
                        $today_join_url = $configured_zoom_url;
                    }
                }
            }

            $today_mock[] = [
                $apt_row['provider'] ?? ($apt_row['doctor_name'] ?? '—'),
                $apt_row['provider_spec'] ?? '—',
                $date_ymd ? wp_date('M j, Y', strtotime($date_ymd)) : '—',
                $apt_row['time'] ?? ($apt_row['appt_time'] ?? '—'),
                $apt_row['condition'] ?? ($apt_row['type'] ?? ($apt_row['visit_type'] ?? '—')),
                $apt_row['phone'] ?? ($apt_row['contact'] ?? '—'),
                $status ?: '—',
                $apt_row['avatar'] ?? '',
                $apt_row['id'] ?? ($apt_row['appt_uid'] ?? ''),
                $today_join_url,
                $apt_row['status'] ?? '',
                $apt_row['type'] ?? ($apt_row['visit_type'] ?? 'Video Visit')
            ];
        }
        ?>
        <div class="dior-subtab-panel" id="dior-subtab-today" style="display: block;">
            <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 bg-white mb-5">
                <!-- Header & Actions -->
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 pb-3 mb-3 border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <h5 class="fw-bold text-dark mb-0">Today's Appointments</h5>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1 small fw-semibold">
                            <?php echo count($today_mock); ?> Total
                        </span>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <div class="input-group input-group-sm rounded-3 overflow-hidden border" style="width: 240px;">
                            <span class="input-group-text bg-white border-0 text-muted ps-2.5"><i class="fas fa-search"></i></span>
                            <input type="text" id="dior-today-search-input"
                                class="form-control bg-white border-0 ps-1 shadow-none" placeholder="Search records..."
                                aria-label="Search box" onkeyup="diorFilterTodayAppointments()">
                        </div>
                        <button type="button" class="btn btn-sm btn-danger rounded-3 px-2.5 shadow-sm" title="Delete Selected"
                            onclick="diorBulkDeleteStaticRows(this)">
                            <i class="fas fa-trash"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-primary rounded-3 px-3 shadow-sm" title="Book Appointment"
                            onclick="diorSwitchApptSubTab('book')">
                            <i class="fas fa-plus me-1"></i> New
                        </button>
                        <button type="button" class="btn btn-sm btn-success rounded-3 px-2.5 shadow-sm" title="Export to CSV"
                            onclick="diorDownloadTodayAppointmentsCSV()">
                            <i class="fas fa-file-arrow-down"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-3 px-2.5" title="Refresh Page"
                            onclick="window.location.reload()">
                            <i class="fas fa-rotate-right"></i>
                        </button>
                    </div>
                </div>

                <!-- Responsive Table Wrapper -->
                <div class="table-responsive mb-0 dior-today-desktop-view">
                    <table class="table table-hover align-middle mb-0 dior-appointment-list-table" id="dior-today-appointments-table">
                        <thead class="table-light border-bottom text-uppercase small text-secondary fw-semibold">
                            <tr>
                                <th class="ps-3 py-3" style="width: 40px;"><input type="checkbox" class="form-check-input" onclick="diorBulkDeleteStaticRows(this)"></th>
                                <th class="py-3">Doctor <i class="fas fa-sort text-muted ms-1"></i></th>
                                <th class="py-3">Specialization <i class="fas fa-sort text-muted ms-1"></i></th>
                                <th class="py-3">Date <i class="fas fa-sort text-muted ms-1"></i></th>
                                <th class="py-3">Time <i class="fas fa-sort text-muted ms-1"></i></th>
                                <th class="py-3">Treatment <i class="fas fa-sort text-muted ms-1"></i></th>
                                <th class="py-3">Contact <i class="fas fa-sort text-muted ms-1"></i></th>
                                <th class="py-3">Status <i class="fas fa-sort text-muted ms-1"></i></th>
                                <th class="text-end pe-3 py-3" style="min-width: 140px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="dior-today-appointments-tbody" class="border-top-0">
                            <?php foreach ($today_mock as $tm): ?>
                            <tr>
                                <td class="ps-3 py-3"><input type="checkbox" class="form-check-input"></td>
                                <td class="py-3">
<span class="dior-appointment-doctor-name"><?php echo esc_html($tm[0]); ?></span>
                                </td>
                                <td class="py-3"><span class="text-secondary small fw-medium"><?php echo esc_html($tm[1]); ?></span></td>
                                <td class="py-3">
                                    <div class="d-flex align-items-center gap-1.5 text-secondary">
                                        <i class="far fa-calendar text-primary me-1"></i>
                                        <span class="text-dark fw-medium"><?php echo esc_html($tm[2]); ?></span>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <div class="d-flex align-items-center gap-1.5 text-secondary">
                                        <i class="far fa-clock text-primary me-1"></i>
                                        <span class="text-dark fw-medium"><?php echo esc_html($tm[3]); ?></span>
                                    </div>
                                </td>
                                <td class="py-3"><span class="badge bg-light text-dark border rounded-pill px-2.5 py-1 small fw-medium"><?php echo esc_html($tm[4]); ?></span></td>
                                <td class="py-3"><span class="text-secondary small"><?php echo esc_html($tm[5]); ?></span></td>
                                <td class="py-3">
                                    <?php
                                    $st_cls = 'badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1 small fw-semibold';
                                    if (strtolower($tm[6]) === 'cancelled')
                                        $st_cls = 'badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2.5 py-1 small fw-semibold';
                                    if (strtolower($tm[6]) === 'pending')
                                        $st_cls = 'badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2.5 py-1 small fw-semibold';
                                    if (strtolower($tm[6]) === 'scheduled')
                                        $st_cls = 'badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1 small fw-semibold';
                                    ?>
                                    <span class="<?php echo $st_cls; ?>"><?php echo esc_html($tm[6]); ?></span>
                                </td>
                                <td class="text-end pe-3 py-3">
                                    <div class="cell-actions d-inline-flex align-items-center justify-content-end gap-1.5 flex-nowrap">
                                        <?php
                                        $today_join_url = trim((string) ($tm[9] ?? ''));
                                        $today_status = strtolower(trim((string) ($tm[10] ?? '')));
                                        $today_visit_type = strtolower(trim((string) ($tm[11] ?? 'video visit')));
                                        $today_can_join = in_array($today_status, ['confirmed', 'scheduled', 'success', 'in progress', 'in-queue'], true);
                                        $today_is_video = strpos($today_visit_type, 'video') !== false || strpos($today_visit_type, 'tele') !== false || strpos($today_visit_type, 'zoom') !== false || strpos($today_visit_type, 'virtual') !== false;
                                        $today_has_join_url = $today_join_url !== '' && wp_http_validate_url($today_join_url);
                                        ?>
                                        <?php if ($today_can_join && $today_is_video && $today_has_join_url): ?>
                                            <a class="action-icon-btn edit-btn btn dior-patient-join-consultation" href="<?php echo esc_url($today_join_url); ?>"
                                                target="_blank" rel="noopener noreferrer" title="Join Zoom Consultation" aria-label="Join Zoom Consultation">
                                                <i class="fas fa-video text-success"></i>
                                            </a>
                                        <?php else: ?>
                                            <button type="button" class="action-icon-btn edit-btn btn" disabled
                                                title="<?php echo esc_attr(!$today_can_join ? 'Available after appointment confirmation' : 'Waiting for your doctor to start the Zoom meeting'); ?>"
                                                aria-label="Zoom meeting not ready">
                                                <i class="fas fa-video text-secondary"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button type="button" class="action-icon-btn edit-btn btn" title="View Details" onclick="if(typeof diorViewAppointmentDetails==='function') diorViewAppointmentDetails('<?php echo esc_js($tm[8] ?? ''); ?>');">
                                            <i class="fas fa-eye text-secondary"></i>
                                        </button>
                                        <button type="button" class="action-icon-btn delete-btn btn" title="Cancel Appointment" onclick="if(typeof diorCancelPatientAppointment==='function') diorCancelPatientAppointment('<?php echo esc_js($tm[8] ?? ''); ?>', this);">
                                            <i class="fas fa-trash text-danger"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($today_mock)): ?>
                                <tr class="dior-empty-state"><td colspan="9" class="text-center py-4 text-secondary">No appointments are scheduled for today. <button type="button" class="btn btn-link" data-appt-subtab="book">Book an appointment</button></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Dedicated mobile UI: rendered from the same live appointment records as the desktop table. -->
                <!-- Mobile Cards View (< 768px) — Improved Bootstrap 5 UI -->
                <div class="d-md-none" id="dior-today-mobile-view">
                    <!-- Mobile Toolbar -->
                    <div class="d-flex flex-column gap-2 mb-3">
                        <label class="visually-hidden" for="dior-today-mobile-search">Search today's appointments</label>
                        <div class="input-group rounded-3 overflow-hidden border shadow-sm">
                            <span class="input-group-text bg-white border-0 text-muted"><i class="fas fa-search fa-sm" aria-hidden="true"></i></span>
                            <input type="search" id="dior-today-mobile-search" class="form-control bg-white border-0 shadow-none"
                                placeholder="Search appointments..." oninput="diorFilterTodayMobileAppointments()"
                                autocomplete="off">
                        </div>
                        <button type="button" class="btn btn-primary rounded-3 fw-semibold py-2 shadow-sm w-100"
                            onclick="diorSwitchApptSubTab('book')">
                            <i class="fas fa-plus me-2" aria-hidden="true"></i>Book Appointment
                        </button>
                    </div>

                    <!-- Mobile Appointment Cards -->
                    <div id="dior-today-mobile-list" aria-live="polite">
                        <?php foreach ($today_mock as $tm):
                            $mobile_status    = strtolower(trim((string) ($tm[6] ?? '')));
                            $mobile_join_url  = trim((string) ($tm[9] ?? ''));
                            $mobile_can_join  = in_array($mobile_status, ['confirmed', 'scheduled', 'success', 'in progress', 'in-queue'], true);
                            $mobile_visit_type = strtolower(trim((string) ($tm[11] ?? 'video visit')));
                            $mobile_is_video  = strpos($mobile_visit_type, 'video') !== false || strpos($mobile_visit_type, 'tele') !== false || strpos($mobile_visit_type, 'zoom') !== false || strpos($mobile_visit_type, 'virtual') !== false;
                            $mobile_has_join_url = $mobile_join_url !== '' && wp_http_validate_url($mobile_join_url);
                            $mobile_search_text  = implode(' ', array_map('strval', [$tm[0] ?? '', $tm[1] ?? '', $tm[2] ?? '', $tm[3] ?? '', $tm[4] ?? '', $tm[5] ?? '', $tm[6] ?? '']));

                            // Status badge classes
                            $mb_badge = 'badge bg-success-subtle text-success border border-success-subtle';
                            if (in_array($mobile_status, ['pending', 'requested'], true))
                                $mb_badge = 'badge bg-warning-subtle text-warning border border-warning-subtle';
                            elseif (in_array($mobile_status, ['cancelled', 'canceled'], true))
                                $mb_badge = 'badge bg-danger-subtle text-danger border border-danger-subtle';
                            elseif ($mobile_status === 'in progress')
                                $mb_badge = 'badge bg-info-subtle text-info border border-info-subtle';
                            elseif (in_array($mobile_status, ['completed', 'done'], true))
                                $mb_badge = 'badge bg-primary-subtle text-primary border border-primary-subtle';
                        ?>
                        <article class="card border-0 shadow-sm rounded-4 mb-3"
                            data-search="<?php echo esc_attr(strtolower($mobile_search_text)); ?>"
                            data-appointment-id="<?php echo esc_attr($tm[8] ?? ''); ?>">
                            <div class="card-body p-3">

                                <!-- Doctor Info Row -->
                                <div class="d-flex align-items-center gap-3 mb-3">
                                    <div class="rounded-circle bg-primary-subtle d-flex align-items-center justify-content-center flex-shrink-0" style="width:46px;height:46px;">
                                        <i class="fas fa-user-doctor text-primary fs-5"></i>
                                    </div>
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="fw-bold text-dark small text-truncate"><?php echo esc_html($tm[0] ?: 'Provider not recorded'); ?></div>
                                        <div class="text-secondary" style="font-size:11px;"><?php echo esc_html($tm[1] ?: 'General Practice'); ?></div>
                                    </div>
                                    <span class="<?php echo $mb_badge; ?> rounded-pill px-2 py-1 flex-shrink-0" style="font-size:10px;font-weight:700;letter-spacing:.02em;">
                                        <?php echo esc_html(ucfirst($tm[6] ?: '—')); ?>
                                    </span>
                                </div>

                                <!-- Details Grid -->
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <div class="bg-light rounded-3 p-2">
                                            <div class="text-secondary mb-1" style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;"><i class="far fa-calendar me-1 text-primary"></i>Date</div>
                                            <div class="fw-semibold text-dark" style="font-size:12px;"><?php echo esc_html($tm[2] ?: '—'); ?></div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="bg-light rounded-3 p-2">
                                            <div class="text-secondary mb-1" style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;"><i class="far fa-clock me-1 text-primary"></i>Time</div>
                                            <div class="fw-semibold text-dark" style="font-size:12px;"><?php echo esc_html($tm[3] ?: '—'); ?></div>
                                        </div>
                                    </div>
                                    <div class="col-7">
                                        <div class="bg-light rounded-3 p-2">
                                            <div class="text-secondary mb-1" style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;"><i class="fas fa-stethoscope me-1 text-primary"></i>Treatment</div>
                                            <div class="fw-semibold text-dark" style="font-size:12px;"><?php echo esc_html($tm[4] ?: '—'); ?></div>
                                        </div>
                                    </div>
                                    <div class="col-5">
                                        <div class="bg-light rounded-3 p-2">
                                            <div class="text-secondary mb-1" style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;"><i class="fas fa-phone me-1 text-primary"></i>Contact</div>
                                            <div class="fw-semibold text-dark" style="font-size:12px;"><?php echo esc_html($tm[5] ?: '—'); ?></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="d-flex gap-2">
                                    <?php if ($mobile_can_join && $mobile_is_video && $mobile_has_join_url): ?>
                                        <a class="btn btn-success rounded-3 fw-semibold flex-shrink-0 d-inline-flex align-items-center gap-1 shadow-sm"
                                            href="<?php echo esc_url($mobile_join_url); ?>" target="_blank"
                                            rel="noopener noreferrer"
                                            style="font-size:13px;padding:8px 14px;"
                                            aria-label="Join video consultation">
                                            <i class="fas fa-video" aria-hidden="true"></i>
                                            <span>Join</span>
                                        </a>
                                    <?php else: ?>
                                        <button type="button"
                                            class="btn btn-outline-secondary rounded-3 fw-semibold flex-shrink-0 d-inline-flex align-items-center gap-1"
                                            style="font-size:13px;padding:8px 14px;"
                                            disabled
                                            title="<?php echo esc_attr($mobile_can_join ? 'Waiting for doctor to start the meeting' : 'Available after confirmation'); ?>"
                                            aria-label="Video call not ready">
                                            <i class="fas fa-video" aria-hidden="true"></i>
                                            <span>Join</span>
                                        </button>
                                    <?php endif; ?>
                                    <button type="button"
                                        class="btn btn-primary rounded-3 fw-semibold flex-grow-1 d-inline-flex align-items-center justify-content-center gap-1 shadow-sm"
                                        style="font-size:13px;padding:8px 14px;"
                                        onclick="if(typeof diorViewAppointmentDetails==='function') diorViewAppointmentDetails('<?php echo esc_js($tm[8] ?? ''); ?>');">
                                        <i class="fas fa-circle-info" aria-hidden="true"></i>
                                        <span>View details</span>
                                    </button>
                                    <button type="button"
                                        class="btn btn-outline-danger rounded-3 fw-semibold flex-shrink-0 d-inline-flex align-items-center gap-1"
                                        style="font-size:13px;padding:8px 12px;"
                                        onclick="if(typeof diorCancelPatientAppointment==='function') diorCancelPatientAppointment('<?php echo esc_js($tm[8] ?? ''); ?>', this);"
                                        title="Cancel Appointment">
                                        <i class="fas fa-xmark" aria-hidden="true"></i>
                                        <span class="d-none">Cancel</span>
                                    </button>
                                </div>

                            </div>
                        </article>
                        <?php endforeach; ?>

                        <?php if (empty($today_mock)): ?>
                        <div class="text-center p-4">
                            <div class="rounded-circle bg-primary-subtle d-inline-flex align-items-center justify-content-center mb-3" style="width:64px;height:64px;">
                                <i class="far fa-calendar-check text-primary fs-3"></i>
                            </div>
                            <h6 class="fw-bold text-dark mb-1">No appointments today</h6>
                            <p class="text-secondary small mb-3">Your today's appointments will appear here.</p>
                            <button type="button" class="btn btn-primary rounded-3 fw-semibold px-4 shadow-sm" onclick="diorSwitchApptSubTab('book')">
                                <i class="fas fa-plus me-2" aria-hidden="true"></i>Book an appointment
                            </button>
                        </div>
                        <?php endif; ?>

                        <p id="dior-today-mobile-no-results" class="small text-secondary text-center py-3" hidden>No appointments match your search.</p>
                    </div>
                    <div id="dior-today-mobile-pagination" class="dior-mobile-card-pagination" aria-label="Today's appointments pages"></div>
                </div>

                <!-- Footer Summary with Pagination -->
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 pt-3 pb-2 px-1 border-top mt-3">
                    <span class="text-secondary small fw-medium" id="dior-today-page-count">0 selected / <?php echo count($today_mock); ?> total</span>
                    <div class="master-pagination d-flex align-items-center gap-1" id="dior-today-pagination">
                        <button type="button" class="dior-page-btn btn" disabled aria-label="Previous page"><i class="fas fa-chevron-left fa-xs"></i></button>
                        <button type="button" class="dior-page-btn active btn">1</button>
                        <button type="button" class="dior-page-btn btn" disabled aria-label="Next page"><i class="fas fa-chevron-right fa-xs"></i></button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Upcoming Appointments -->
        <div class="dior-subtab-panel" id="dior-subtab-upcoming" style="display: none;">
            <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 bg-white mb-5">
                <!-- Header & Actions -->
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 pb-3 mb-3 border-bottom dior-appt-list-header">
                    <div class="d-flex align-items-center gap-2">
                        <h5 class="fw-bold text-dark mb-0">Upcoming Appointments</h5>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1 small fw-semibold">
                            Scheduled
                        </span>
                    </div>
                    <div class="dior-appt-list-toolbar">
                        <div class="input-group input-group-sm rounded-3 overflow-hidden border dior-appt-list-search">
                            <span class="input-group-text bg-white border-0 text-muted ps-2.5"><i class="fas fa-search"></i></span>
                            <input type="text" id="dior-upcoming-search-input"
                                class="form-control bg-white border-0 ps-1 shadow-none" placeholder="Search records..."
                                aria-label="Search box" onkeyup="diorFilterUpcomingAppointments()">
                        </div>
                        <button type="button" class="btn btn-sm btn-danger rounded-3 px-2.5 shadow-sm"
                            id="dior-upcoming-bulk-delete-btn" title="Delete Selected"
                            onclick="diorBulkDeleteUpcomingRows()">
                            <i class="fas fa-trash"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-primary rounded-3 px-3 shadow-sm"
                            title="Book Appointment" onclick="diorSwitchApptSubTab('book')">
                            <i class="fas fa-plus me-1"></i> New
                        </button>
                        <button type="button" class="btn btn-sm btn-success rounded-3 px-2.5 shadow-sm"
                            title="Export to CSV" onclick="diorDownloadUpcomingAppointmentsCSV()">
                            <i class="fas fa-file-arrow-down"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-3 px-2.5"
                            title="Refresh Page" onclick="window.location.reload()">
                            <i class="fas fa-rotate-right"></i>
                        </button>
                    </div>
                </div>

                <!-- Responsive Table Wrapper -->
                <div class="table-responsive mb-0">
                    <table class="table table-hover align-middle mb-0 dior-appointment-list-table" id="dior-upcoming-appointments-table">
                        <thead class="table-light border-bottom text-uppercase small text-secondary fw-semibold">
                            <tr>
                                <th class="ps-3 py-3" style="width: 40px;">
                                    <input type="checkbox" id="dior-select-all-upcoming"
                                        class="form-check-input"
                                        onclick="diorToggleSelectAllUpcoming(this)">
                                </th>
                                <th class="py-3">Doctor <i class="fas fa-sort text-muted ms-1"></i></th>
                                <th class="py-3">Date <i class="fas fa-sort text-muted ms-1"></i></th>
                                <th class="py-3">Time <i class="fas fa-sort text-muted ms-1"></i></th>
                                <th class="py-3">Condition <i class="fas fa-sort text-muted ms-1"></i></th>
                                <th class="py-3">Status <i class="fas fa-sort text-muted ms-1"></i></th>
                                <th class="py-3">Notes <i class="fas fa-sort text-muted ms-1"></i></th>
                                <th class="text-end pe-3 py-3" style="min-width: 140px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="dior-upcoming-appointments-tbody" class="border-top-0">
                            <?php
                            $upcoming_mock = [];
                            $today_ymd = current_time('Y-m-d');
                            foreach ((array) $appointments as $apt_row) {
                                $date_raw = $apt_row['date'] ?? ($apt_row['appt_date'] ?? '');
                                $date_ymd = function_exists('dior_normalize_date') ? dior_normalize_date($date_raw) : $date_raw;
                                $status = sanitize_text_field($apt_row['status'] ?? '');
                                if (!$date_ymd || $date_ymd < $today_ymd || in_array(strtolower($status), ['cancelled', 'canceled', 'completed', 'done'], true))
                                    continue;
                                $upcoming_mock[] = [
                                    $apt_row['provider'] ?? ($apt_row['doctor_name'] ?? '—'),
                                    wp_date('M j, Y', strtotime($date_ymd)),
                                    $apt_row['time'] ?? ($apt_row['appt_time'] ?? '—'),
                                    $apt_row['condition'] ?? ($apt_row['type'] ?? ($apt_row['visit_type'] ?? '—')),
                                    $status,
                                    $apt_row['notes'] ?? '—',
                                    $apt_row['avatar'] ?? '',
                                    $apt_row['id'] ?? ($apt_row['appt_uid'] ?? ''),
                                    $date_raw,
                                    $apt_row['join_url'] ?? '',
                                    $apt_row['type'] ?? ($apt_row['visit_type'] ?? 'Video Visit')
                                ];
                            }
                            ?>
                            <?php foreach ($upcoming_mock as $um): ?>
                            <tr>
                                <td class="ps-3">
                                    <input type="checkbox"
                                        class="upcoming-row-checkbox form-check-input"
                                        onclick="diorUpdateUpcomingSelectedCount()">
                                </td>
                                <td>
<span class="dior-appointment-doctor-name"><?php echo esc_html($um[0]); ?></span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-1.5 text-secondary">
                                        <i class="far fa-calendar text-primary me-1"></i>
                                        <span class="text-dark fw-medium"><?php echo esc_html($um[1]); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-1.5 text-secondary">
                                        <i class="far fa-clock text-primary me-1"></i>
                                        <span class="text-dark fw-medium"><?php echo esc_html($um[2]); ?></span>
                                    </div>
                                </td>
                                <td><span class="badge bg-light text-dark border rounded-pill px-2.5 py-1 small fw-medium"><?php echo esc_html($um[3]); ?></span></td>
                                <td>
                                    <?php
                                    $st_cls = 'badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1 small fw-semibold';
                                    if (strtolower($um[4]) === 'cancelled')
                                        $st_cls = 'badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2.5 py-1 small fw-semibold';
                                    if (strtolower($um[4]) === 'pending')
                                        $st_cls = 'badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2.5 py-1 small fw-semibold';
                                    if (strtolower($um[4]) === 'scheduled')
                                        $st_cls = 'badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1 small fw-semibold';
                                    ?>
                                    <span class="<?php echo $st_cls; ?>"><?php echo esc_html($um[4]); ?></span>
                                </td>
                                <td><span class="text-secondary small text-truncate d-inline-block" style="max-width: 250px;"><?php echo esc_html($um[5]); ?></span></td>
                                <td class="text-end pe-3">
                                    <div class="cell-actions d-inline-flex align-items-center justify-content-end gap-1.5 flex-nowrap">
                                        <?php
                                        $patient_join_url = trim((string) ($um[9] ?? ''));
                                        $patient_appt_status = strtolower(trim((string) ($um[4] ?? '')));
                                        $patient_visit_type = strtolower(trim((string) ($um[10] ?? 'video visit')));
                                        $patient_can_join_status = in_array($patient_appt_status, ['confirmed', 'scheduled', 'success', 'in progress', 'in-queue'], true);
                                        $patient_is_video_visit = (strpos($patient_visit_type, 'video') !== false || strpos($patient_visit_type, 'tele') !== false || strpos($patient_visit_type, 'zoom') !== false || strpos($patient_visit_type, 'virtual') !== false);
                                        $patient_has_real_join_url = $patient_join_url !== ''
                                            && wp_http_validate_url($patient_join_url)
                                            && rtrim($patient_join_url, '/') !== 'https://zoom.us/join';
                                        ?>
                                        <?php if ($patient_can_join_status && $patient_is_video_visit && $patient_has_real_join_url): ?>
                                            <a class="action-icon-btn edit-btn btn" href="<?php echo esc_url($patient_join_url); ?>"
                                                target="_blank" rel="noopener noreferrer" title="Join Video Consultation">
                                                <i class="fas fa-video text-success"></i>
                                            </a>
                                        <?php else: ?>
                                            <button type="button" class="action-icon-btn edit-btn btn" disabled
                                                title="<?php echo esc_attr(!$patient_can_join_status ? 'Available after the appointment is confirmed' : 'Waiting for the doctor to start the meeting'); ?>"
                                                aria-label="Consultation link not ready">
                                                <i class="fas fa-video text-secondary"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button type="button" class="action-icon-btn edit-btn btn"
                                            title="Reschedule Appointment" onclick="if(typeof diorOpenRescheduleModal==='function') diorOpenRescheduleModal('<?php echo esc_js($um[7] ?? ''); ?>', '<?php echo esc_js($um[8] ?? ''); ?>');">
                                            <i class="fas fa-calendar-days text-info"></i>
                                        </button>
                                        <button type="button" class="action-icon-btn edit-btn btn"
                                            title="View Details" onclick="if(typeof diorViewAppointmentDetails==='function') diorViewAppointmentDetails('<?php echo esc_js($um[7] ?? ''); ?>');">
                                            <i class="fas fa-eye text-secondary"></i>
                                        </button>
                                        <button type="button" class="action-icon-btn delete-btn btn"
                                            title="Cancel Appointment" onclick="if(typeof diorCancelPatientAppointment==='function') diorCancelPatientAppointment('<?php echo esc_js($um[7] ?? ''); ?>', this);">
                                            <i class="fas fa-trash text-danger"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($upcoming_mock)): ?>
                                <tr class="dior-empty-state"><td colspan="8" class="text-center py-4 text-secondary">No upcoming appointments found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div id="dior-upcoming-mobile-empty" class="dior-appt-list-empty" role="status">No upcoming appointments found.</div>

                <!-- Footer Summary with Pagination -->
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 pt-3 px-1 border-top mt-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-secondary small fw-medium" id="dior-upcoming-page-count">0 selected / <?php echo count($upcoming_mock); ?> total</span>
                        <span id="dior-upcoming-selected-counter" class="d-none">0 selected / <?php echo count($upcoming_mock); ?> total</span>
                    </div>
                    <div class="master-pagination dior-responsive-pagination" id="dior-upcoming-pagination" aria-label="Upcoming appointment pages"></div>
                </div>
            </div>
        </div>

        <!-- 4. Past Appointments -->
        <div class="dior-subtab-panel" id="dior-subtab-past" style="display: none;">
            <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 bg-white mb-5">
                <!-- Header & Actions -->
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 pb-3 mb-3 border-bottom dior-appt-list-header">
                    <div class="d-flex align-items-center gap-2">
                        <h5 class="fw-bold text-dark mb-0">Past Appointments</h5>
                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2.5 py-1 small fw-semibold">
                            History
                        </span>
                    </div>
                    <div class="dior-appt-list-toolbar">
                        <div class="input-group input-group-sm rounded-3 overflow-hidden border dior-appt-list-search">
                            <span class="input-group-text bg-white border-0 text-muted ps-2.5"><i class="fas fa-search"></i></span>
                            <input type="text" id="dior-past-search-input"
                                class="form-control bg-white border-0 ps-1 shadow-none" placeholder="Search records..."
                                aria-label="Search box" onkeyup="diorFilterPastAppointments()">
                        </div>
                        <button type="button" class="btn btn-sm btn-danger rounded-3 px-2.5 shadow-sm" title="Delete Selected"
                            onclick="diorBulkDeleteStaticRows(this)">
                            <i class="fas fa-trash"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-success rounded-3 px-2.5 shadow-sm"
                            title="Export to CSV" onclick="diorDownloadPastAppointmentsCSV()">
                            <i class="fas fa-file-arrow-down"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-3 px-2.5"
                            title="Refresh Page" onclick="window.location.reload()">
                            <i class="fas fa-rotate-right"></i>
                        </button>
                    </div>
                </div>

                <!-- Responsive Table Wrapper -->
                <div class="table-responsive mb-0">
                    <table class="table table-hover align-middle mb-0 dior-appointment-list-table" id="dior-past-appointments-table">
                        <thead class="table-light border-bottom text-uppercase small text-secondary fw-semibold">
                            <tr>
                                <th class="ps-3 py-3" style="width: 40px;"><input type="checkbox" class="form-check-input" onclick="diorBulkDeleteStaticRows(this)"></th>
                                <th>Doctor <i class="fas fa-sort text-muted ms-1"></i></th>
                                <th>Date <i class="fas fa-sort text-muted ms-1"></i></th>
                                <th>Time <i class="fas fa-sort text-muted ms-1"></i></th>
                                <th>Email <i class="fas fa-sort text-muted ms-1"></i></th>
                                <th>Mobile <i class="fas fa-sort text-muted ms-1"></i></th>
                                <th>Condition <i class="fas fa-sort text-muted ms-1"></i></th>
                                <th>Format <i class="fas fa-sort text-muted ms-1"></i></th>
                                <th>Follow-up <i class="fas fa-sort text-muted ms-1"></i></th>
                                <th class="text-end pe-3" style="min-width: 100px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="dior-past-appointments-tbody" class="border-top-0">
                            <?php
                            $past_mock = [];
                            $today_ymd = current_time('Y-m-d');
                            foreach ((array) $appointments as $apt_row) {
                                $date_raw = $apt_row['date'] ?? ($apt_row['appt_date'] ?? '');
                                $date_ymd = function_exists('dior_normalize_date') ? dior_normalize_date($date_raw) : $date_raw;
                                $status = sanitize_text_field($apt_row['status'] ?? '');
                                if (!$date_ymd || ($date_ymd >= $today_ymd && !in_array(strtolower($status), ['completed', 'done', 'past'], true)))
                                    continue;
                                $past_mock[] = [
                                    $apt_row['provider'] ?? ($apt_row['doctor_name'] ?? '—'),
                                    wp_date('M j, Y', strtotime($date_ymd)),
                                    $apt_row['time'] ?? ($apt_row['appt_time'] ?? '—'),
                                    $apt_row['email'] ?? '—',
                                    $apt_row['phone'] ?? '—',
                                    $apt_row['condition'] ?? '—',
                                    $apt_row['type'] ?? ($apt_row['visit_type'] ?? '—'),
                                    '—',
                                    $apt_row['avatar'] ?? '',
                                    $apt_row['id'] ?? ($apt_row['appt_uid'] ?? '')
                                ];
                            }
                            ?>
                            <?php foreach ($past_mock as $pm): ?>
                            <tr>
                                <td class="ps-3"><input type="checkbox" class="form-check-input"></td>
                                <td>
<span class="dior-appointment-doctor-name"><?php echo esc_html($pm[0]); ?></span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-1.5 text-secondary">
                                        <i class="far fa-calendar text-primary me-1"></i>
                                        <span class="text-dark fw-medium"><?php echo esc_html($pm[1]); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-1.5 text-secondary">
                                        <i class="far fa-clock text-primary me-1"></i>
                                        <span class="text-dark fw-medium"><?php echo esc_html($pm[2]); ?></span>
                                    </div>
                                </td>
                                <td><span class="text-secondary small"><?php echo esc_html($pm[3]); ?></span></td>
                                <td><span class="text-secondary small"><?php echo esc_html($pm[4]); ?></span></td>
                                <td><span class="badge bg-light text-dark border rounded-pill px-2.5 py-1 small fw-medium"><?php echo esc_html($pm[5]); ?></span></td>
                                <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1 small fw-semibold"><?php echo esc_html($pm[6]); ?></span></td>
                                <td>
                                    <span class="badge bg-light text-secondary border rounded-pill px-2.5 py-1 small"><?php echo esc_html($pm[7]); ?></span>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="cell-actions d-inline-flex align-items-center justify-content-end gap-1.5 flex-nowrap">
                                        <button type="button" class="action-icon-btn edit-btn btn" title="View Consultation Summary" onclick="if(typeof diorViewAppointmentDetails==='function') diorViewAppointmentDetails('<?php echo esc_js($pm[9] ?? ''); ?>');">
                                            <i class="fas fa-file-medical text-secondary"></i>
                                        </button>
                                        <button type="button" class="action-icon-btn edit-btn btn" title="Book Again" onclick="if(typeof diorSwitchApptSubTab==='function') diorSwitchApptSubTab('book');">
                                            <i class="fas fa-calendar-plus text-primary"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($past_mock)): ?>
                                <tr class="dior-empty-state"><td colspan="10" class="text-center py-4 text-secondary">No past appointments found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Footer Summary with Pagination -->
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 pt-3 pb-2 px-1 border-top mt-3">
                    <span class="text-secondary small fw-medium" id="dior-past-page-count">0 selected / <?php echo count($past_mock); ?> total</span>
                    <div class="master-pagination dior-responsive-pagination" id="dior-past-pagination" aria-label="Past appointment pages"></div>
                </div>
            </div>
        </div>

    </div>

    <script>
        window.dior_inline_appts_data = <?php echo $apt_cards_json; ?>;
        if (typeof window.diorInitBootstrapCalendar === 'function') {
            window.diorInitBootstrapCalendar();
        }
    </script>
    <script>
    /* Mobile Today cards: paginate live PHP-rendered appointments in groups of five. */
    (function () {
        var list = document.getElementById('dior-today-mobile-list');
        var search = document.getElementById('dior-today-mobile-search');
        var pager = document.getElementById('dior-today-mobile-pagination');
        if (!list || !pager) return;
        var currentPage = 1;
        var perPage = 5;
        function render() {
            var cards = Array.prototype.slice.call(list.querySelectorAll('.card[data-appointment-id]'));
            var query = search ? String(search.value || '').trim().toLowerCase() : '';
            var filtered = cards.filter(function (card) {
                var haystack = String(card.getAttribute('data-search') || card.textContent).toLowerCase();
                return !query || haystack.indexOf(query) !== -1;
            });
            var pages = Math.max(1, Math.ceil(filtered.length / perPage));
            currentPage = Math.min(Math.max(1, currentPage), pages);
            var start = (currentPage - 1) * perPage;
            cards.forEach(function (card) { card.hidden = true; card.style.display = 'none'; });
            filtered.slice(start, start + perPage).forEach(function (card) {
                card.hidden = false;
                card.style.display = '';
            });
            var empty = document.getElementById('dior-today-mobile-no-results');
            if (empty) empty.hidden = filtered.length > 0 || cards.length === 0;
            pager.innerHTML = filtered.length > perPage
                ? '<span class="dior-mobile-page-count">Showing ' + (start + 1) + '–' + Math.min(start + perPage, filtered.length) + ' of ' + filtered.length + '</span>' +
                  '<div class="dior-mobile-page-controls"><button type="button" data-page="' + (currentPage - 1) + '" ' + (currentPage === 1 ? 'disabled' : '') + ' aria-label="Previous page"><i class="fas fa-chevron-left"></i></button><span>' + currentPage + ' / ' + pages + '</span><button type="button" data-page="' + (currentPage + 1) + '" ' + (currentPage === pages ? 'disabled' : '') + ' aria-label="Next page"><i class="fas fa-chevron-right"></i></button></div>'
                : '';
        }
        if (search) search.addEventListener('input', function () { currentPage = 1; render(); });
        pager.addEventListener('click', function (event) {
            var button = event.target.closest('button[data-page]');
            if (!button || button.disabled) return;
            currentPage = parseInt(button.getAttribute('data-page'), 10) || 1;
            render();
        });
        render();
    })();
    </script>

</section>
<!-- ============================================================== -->
<!-- 3. PAYMENTS & INVOICES TAB -->
<!-- ============================================================== -->