<?php defined('ABSPATH') || exit; ?>
<section class="dior-tab-panel dior-source-panel" id="source-e-prescriptions">
    <div class="dior-source-inner">
        <section class="main-content">
            <div>
                <div class="breadcrumb-main">
                    <div class="row">
                        <div class="col-6">
                            <div class="breadcrumb-title">
                                <h4 class="page-title d-flex align-items-center flex-wrap gap-2"><span>Digital
                                        Prescriptions</span></h4>
                            </div>
                        </div>
                        <div class="col-6">
                            <ul class="breadcrumb-list">
                                <li class="breadcrumb-item bcrumb-1"><a>
                                        <div class="breadcrumb-icon" name="home"><svg class="feather feather-home"
                                                viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                                <polyline points="9 22 9 12 15 12 15 22"></polyline>
                                            </svg></div>
                                    </a></li>
                                <li class="breadcrumb-item active">Digital Prescriptions</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <div class="section-body">
                <div class="row">
                    <div class="col-12">
                        <div>
                            <div class="master-table-wrapper">
                                <div class="master-table-container">
                                    <div class="master-table-card">
                                        <div class="master-table-header">
                                            <div class="header-content">
                                                <div class="table-title-section">
                                                    <h2 class="table-title">Digital Prescriptions</h2>
                                                    <div class="title-accent"></div>
                                                </div>
                                                <div class="header-actions-group">
                                                    <div class="search-container"><i
                                                            class="material-icons search-icon">search</i><input
                                                            aria-label="Search box" class="search-input"
                                                            placeholder="Search records..." type="text" /></div>
                                                    <div class="action-buttons"><button
                                                            aria-label="Delete 0 selected items"
                                                            class="action-btn action-btn-danger" hidden=""><i
                                                                class="material-icons">delete</i><span
                                                                class="btn-ripple"></span></button><button
                                                            aria-label="Add new record"
                                                            class="action-btn action-btn-primary"><i
                                                                class="material-icons">add</i><span
                                                                class="btn-ripple"></span></button><button
                                                            aria-label="Export to Excel"
                                                            class="action-btn action-btn-success"><i
                                                                class="material-icons">file_download</i><span
                                                                class="btn-ripple"></span></button><button
                                                            aria-label="Refresh data"
                                                            class="action-btn action-btn-info"><i
                                                                class="material-icons">refresh</i><span
                                                                class="btn-ripple"></span></button></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="table-content">
                                            <div class="ngx-datatable material modern-datatable fixed-header fixed-row virtualized scroll-horz selectable checkbox-selection"
                                                columnmode="force">
                                                <div class="visible" visibilityobserver="">
                                                    <div role="table">
                                                        <div class="datatable-header" role="rowgroup">
                                                            <div class="datatable-header-inner" orderable="" role="row">
                                                                <div class="datatable-row-left"></div>
                                                                <div class="datatable-row-center">
                                                                    <div class="datatable-header-cell" draggable=""
                                                                        long-press="" resizeable="" role="columnheader"
                                                                        tabindex="-1" title="">
                                                                        <div
                                                                            class="datatable-header-cell-template-wrap">
                                                                            <label class="datatable-checkbox"><input
                                                                                    type="checkbox" /></label><span
                                                                                class="datatable-header-cell-wrapper"><span
                                                                                    class="datatable-header-cell-label draggable">
                                                                                </span></span><span></span></div><span
                                                                            class="resize-handle--not-resizable"></span>
                                                                    </div>
                                                                    <div class="datatable-header-cell resizeable sortable"
                                                                        draggable="" long-press="" resizeable=""
                                                                        role="columnheader" tabindex="0">
                                                                        <div
                                                                            class="datatable-header-cell-template-wrap">
                                                                            <span aria-sort="none"
                                                                                class="datatable-header-cell-wrapper"
                                                                                role="button" tabindex="0"> Prescription
                                                                                ID </span><span
                                                                                class="datatable-icon-sort-unset sort-btn"></span>
                                                                        </div><span class="resize-handle"></span>
                                                                    </div>
                                                                    <div class="datatable-header-cell resizeable sortable"
                                                                        draggable="" long-press="" resizeable=""
                                                                        role="columnheader" tabindex="0">
                                                                        <div
                                                                            class="datatable-header-cell-template-wrap">
                                                                            <span aria-sort="none"
                                                                                class="datatable-header-cell-wrapper"
                                                                                role="button" tabindex="0"> Patient Name
                                                                            </span><span
                                                                                class="datatable-icon-sort-unset sort-btn"></span>
                                                                        </div><span class="resize-handle"></span>
                                                                    </div>
                                                                    <div class="datatable-header-cell resizeable sortable"
                                                                        draggable="" long-press="" resizeable=""
                                                                        role="columnheader" tabindex="0">
                                                                        <div
                                                                            class="datatable-header-cell-template-wrap">
                                                                            <span aria-sort="none"
                                                                                class="datatable-header-cell-wrapper"
                                                                                role="button" tabindex="0"> Date
                                                                            </span><span
                                                                                class="datatable-icon-sort-unset sort-btn"></span>
                                                                        </div><span class="resize-handle"></span>
                                                                    </div>
                                                                    <div class="datatable-header-cell resizeable sortable"
                                                                        draggable="" long-press="" resizeable=""
                                                                        role="columnheader" tabindex="0">
                                                                        <div
                                                                            class="datatable-header-cell-template-wrap">
                                                                            <span aria-sort="none"
                                                                                class="datatable-header-cell-wrapper"
                                                                                role="button" tabindex="0"> Medications
                                                                            </span><span
                                                                                class="datatable-icon-sort-unset sort-btn"></span>
                                                                        </div><span class="resize-handle"></span>
                                                                    </div>
                                                                    <div class="datatable-header-cell resizeable sortable"
                                                                        draggable="" long-press="" resizeable=""
                                                                        role="columnheader" tabindex="0">
                                                                        <div
                                                                            class="datatable-header-cell-template-wrap">
                                                                            <span aria-sort="none"
                                                                                class="datatable-header-cell-wrapper"
                                                                                role="button" tabindex="0"> Dosage
                                                                            </span><span
                                                                                class="datatable-icon-sort-unset sort-btn"></span>
                                                                        </div><span class="resize-handle"></span>
                                                                    </div>
                                                                    <div class="datatable-header-cell resizeable sortable"
                                                                        draggable="" long-press="" resizeable=""
                                                                        role="columnheader" tabindex="0">
                                                                        <div
                                                                            class="datatable-header-cell-template-wrap">
                                                                            <span aria-sort="none"
                                                                                class="datatable-header-cell-wrapper"
                                                                                role="button" tabindex="0"> Frequency
                                                                            </span><span
                                                                                class="datatable-icon-sort-unset sort-btn"></span>
                                                                        </div><span class="resize-handle"></span>
                                                                    </div>
                                                                    <div class="datatable-header-cell resizeable sortable"
                                                                        draggable="" long-press="" resizeable=""
                                                                        role="columnheader" tabindex="0">
                                                                        <div
                                                                            class="datatable-header-cell-template-wrap">
                                                                            <span aria-sort="none"
                                                                                class="datatable-header-cell-wrapper"
                                                                                role="button" tabindex="0"> Duration
                                                                            </span><span
                                                                                class="datatable-icon-sort-unset sort-btn"></span>
                                                                        </div><span class="resize-handle"></span>
                                                                    </div>
                                                                    <div class="datatable-header-cell resizeable sortable"
                                                                        draggable="" long-press="" resizeable=""
                                                                        role="columnheader" tabindex="0">
                                                                        <div
                                                                            class="datatable-header-cell-template-wrap">
                                                                            <span aria-sort="none"
                                                                                class="datatable-header-cell-wrapper"
                                                                                role="button" tabindex="0"> Doctor
                                                                            </span><span
                                                                                class="datatable-icon-sort-unset sort-btn"></span>
                                                                        </div><span class="resize-handle"></span>
                                                                    </div>
                                                                    <div class="datatable-header-cell resizeable sortable"
                                                                        draggable="" long-press="" resizeable=""
                                                                        role="columnheader" tabindex="0">
                                                                        <div
                                                                            class="datatable-header-cell-template-wrap">
                                                                            <span aria-sort="none"
                                                                                class="datatable-header-cell-wrapper"
                                                                                role="button" tabindex="0"> Status
                                                                            </span><span
                                                                                class="datatable-icon-sort-unset sort-btn"></span>
                                                                        </div><span class="resize-handle"></span>
                                                                    </div>
                                                                    <div class="datatable-header-cell resizeable"
                                                                        draggable="" long-press="" resizeable=""
                                                                        role="columnheader" tabindex="-1">
                                                                        <div
                                                                            class="datatable-header-cell-template-wrap">
                                                                            <span aria-sort="none"
                                                                                class="datatable-header-cell-wrapper"
                                                                                role="button" tabindex="0"> Actions
                                                                            </span><span></span></div><span
                                                                            class="resize-handle"></span>
                                                                    </div>
                                                                </div>
                                                                <div class="datatable-row-right"></div>
                                                            </div>
                                                        </div>
                                                        <div class="datatable-body" role="rowgroup" tabindex="0">
                                                            <div>
                                                                <div class="datatable-scroll">
                                                                    <div class="datatable-row-wrapper">
                                                                        <div class="datatable-body-row datatable-row-even"
                                                                            draggable="false" role="row" tabindex="-1">
                                                                            <div
                                                                                class="datatable-row-group datatable-row-left">
                                                                            </div>
                                                                            <div
                                                                                class="datatable-row-center datatable-row-group">
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <label
                                                                                            class="datatable-checkbox"><input
                                                                                                type="checkbox" /></label><span
                                                                                            title=""></span></div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                RX001 </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div
                                                                                            class="cell-content cell-image-name">
                                                                                            <img alt="User avatar"
                                                                                                class="cell-avatar"
                                                                                                src="<?php echo esc_url(!empty($doctor['avatar_url']) ? $doctor['avatar_url'] : DIOR_PORTAL_URL . 'assets/images/logo-q.png'); ?>" />
                                                                                            <div
                                                                                                class="cell-text-wrapper">
                                                                                                <div class="cell-text">
                                                                                                    Sarah Johnson</div>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div
                                                                                            class="cell-content cell-icon-text">
                                                                                            <i
                                                                                                class="material-icons-outlined cell-icon">
                                                                                            </i><span
                                                                                                class="cell-text">Nov
                                                                                                20, 2024</span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Lisinopril, Aspirin
                                                                                            </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                10mg, 81mg </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Once daily, Once daily
                                                                                            </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                30 days </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Dr. Smith </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content">
                                                                                            <div
                                                                                                class="badge-solid col-green">
                                                                                                Active </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-actions">
                                                                                            <button
                                                                                                aria-label="Edit record"
                                                                                                class="action-icon-btn edit-btn"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-vector-pen" viewBox="0 0 16 16" style="background:transparent;"><path fill-rule="evenodd" d="M10.646.646a.5.5 0 0 1 .708 0l4 4a.5.5 0 0 1 0 .708l-1.902 1.902-.829 3.313a1.5 1.5 0 0 1-1.024 1.073L1.254 14.746 4.358 4.4A1.5 1.5 0 0 1 5.43 3.377l3.313-.828zm-1.8 2.908-3.173.793a.5.5 0 0 0-.358.342l-2.57 8.565 8.567-2.57a.5.5 0 0 0 .34-.357l.794-3.174-3.6-3.6z"/><path fill-rule="evenodd" d="M2.832 13.228 8 9a1 1 0 1 0-1-1l-4.228 5.168-.026.086z"/></svg></button><button
                                                                                                aria-label="Delete record"
                                                                                                class="action-icon-btn delete-btn"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#ef4444" class="bi bi-trash" viewBox="0 0 16 16" style="background:transparent;"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/></svg></button>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div
                                                                                class="datatable-row-group datatable-row-right">
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="datatable-row-wrapper">
                                                                        <div class="datatable-body-row datatable-row-odd"
                                                                            draggable="false" role="row" tabindex="-1">
                                                                            <div
                                                                                class="datatable-row-group datatable-row-left">
                                                                            </div>
                                                                            <div
                                                                                class="datatable-row-center datatable-row-group">
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <label
                                                                                            class="datatable-checkbox"><input
                                                                                                type="checkbox" /></label><span
                                                                                            title=""></span></div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                RX002 </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div
                                                                                            class="cell-content cell-image-name">
                                                                                            <img alt="User avatar"
                                                                                                class="cell-avatar"
                                                                                                src="<?php echo esc_url(!empty($doctor['avatar_url']) ? $doctor['avatar_url'] : DIOR_PORTAL_URL . 'assets/images/logo-q.png'); ?>" />
                                                                                            <div
                                                                                                class="cell-text-wrapper">
                                                                                                <div class="cell-text">
                                                                                                    Michael Chen</div>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div
                                                                                            class="cell-content cell-icon-text">
                                                                                            <i
                                                                                                class="material-icons-outlined cell-icon">
                                                                                            </i><span
                                                                                                class="cell-text">Nov
                                                                                                18, 2024</span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Metformin </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                500mg </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Twice daily </span>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                90 days </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Dr. Johnson </span>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content">
                                                                                            <div
                                                                                                class="badge-solid col-green">
                                                                                                Active </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-actions">
                                                                                            <button
                                                                                                aria-label="Edit record"
                                                                                                class="action-icon-btn edit-btn"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-vector-pen" viewBox="0 0 16 16" style="background:transparent;"><path fill-rule="evenodd" d="M10.646.646a.5.5 0 0 1 .708 0l4 4a.5.5 0 0 1 0 .708l-1.902 1.902-.829 3.313a1.5 1.5 0 0 1-1.024 1.073L1.254 14.746 4.358 4.4A1.5 1.5 0 0 1 5.43 3.377l3.313-.828zm-1.8 2.908-3.173.793a.5.5 0 0 0-.358.342l-2.57 8.565 8.567-2.57a.5.5 0 0 0 .34-.357l.794-3.174-3.6-3.6z"/><path fill-rule="evenodd" d="M2.832 13.228 8 9a1 1 0 1 0-1-1l-4.228 5.168-.026.086z"/></svg></button><button
                                                                                                aria-label="Delete record"
                                                                                                class="action-icon-btn delete-btn"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#ef4444" class="bi bi-trash" viewBox="0 0 16 16" style="background:transparent;"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/></svg></button>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div
                                                                                class="datatable-row-group datatable-row-right">
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="datatable-row-wrapper">
                                                                        <div class="datatable-body-row datatable-row-even"
                                                                            draggable="false" role="row" tabindex="-1">
                                                                            <div
                                                                                class="datatable-row-group datatable-row-left">
                                                                            </div>
                                                                            <div
                                                                                class="datatable-row-center datatable-row-group">
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <label
                                                                                            class="datatable-checkbox"><input
                                                                                                type="checkbox" /></label><span
                                                                                            title=""></span></div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                RX003 </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div
                                                                                            class="cell-content cell-image-name">
                                                                                            <img alt="User avatar"
                                                                                                class="cell-avatar"
                                                                                                src="<?php echo esc_url(!empty($doctor['avatar_url']) ? $doctor['avatar_url'] : DIOR_PORTAL_URL . 'assets/images/logo-q.png'); ?>" />
                                                                                            <div
                                                                                                class="cell-text-wrapper">
                                                                                                <div class="cell-text">
                                                                                                    Emily Rodriguez
                                                                                                </div>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div
                                                                                            class="cell-content cell-icon-text">
                                                                                            <i
                                                                                                class="material-icons-outlined cell-icon">
                                                                                            </i><span
                                                                                                class="cell-text">Nov
                                                                                                22, 2024</span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Amoxicillin </span>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                500mg </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Three times daily
                                                                                            </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                7 days </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Dr. Smith </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content">
                                                                                            <div
                                                                                                class="badge-solid col-green">
                                                                                                Active </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-actions">
                                                                                            <button
                                                                                                aria-label="Edit record"
                                                                                                class="action-icon-btn edit-btn"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-vector-pen" viewBox="0 0 16 16" style="background:transparent;"><path fill-rule="evenodd" d="M10.646.646a.5.5 0 0 1 .708 0l4 4a.5.5 0 0 1 0 .708l-1.902 1.902-.829 3.313a1.5 1.5 0 0 1-1.024 1.073L1.254 14.746 4.358 4.4A1.5 1.5 0 0 1 5.43 3.377l3.313-.828zm-1.8 2.908-3.173.793a.5.5 0 0 0-.358.342l-2.57 8.565 8.567-2.57a.5.5 0 0 0 .34-.357l.794-3.174-3.6-3.6z"/><path fill-rule="evenodd" d="M2.832 13.228 8 9a1 1 0 1 0-1-1l-4.228 5.168-.026.086z"/></svg></button><button
                                                                                                aria-label="Delete record"
                                                                                                class="action-icon-btn delete-btn"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#ef4444" class="bi bi-trash" viewBox="0 0 16 16" style="background:transparent;"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/></svg></button>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div
                                                                                class="datatable-row-group datatable-row-right">
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="datatable-row-wrapper">
                                                                        <div class="datatable-body-row datatable-row-odd"
                                                                            draggable="false" role="row" tabindex="-1">
                                                                            <div
                                                                                class="datatable-row-group datatable-row-left">
                                                                            </div>
                                                                            <div
                                                                                class="datatable-row-center datatable-row-group">
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <label
                                                                                            class="datatable-checkbox"><input
                                                                                                type="checkbox" /></label><span
                                                                                            title=""></span></div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                RX004 </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div
                                                                                            class="cell-content cell-image-name">
                                                                                            <img alt="User avatar"
                                                                                                class="cell-avatar"
                                                                                                src="<?php echo esc_url(!empty($doctor['avatar_url']) ? $doctor['avatar_url'] : DIOR_PORTAL_URL . 'assets/images/logo-q.png'); ?>" />
                                                                                            <div
                                                                                                class="cell-text-wrapper">
                                                                                                <div class="cell-text">
                                                                                                    David Williams</div>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div
                                                                                            class="cell-content cell-icon-text">
                                                                                            <i
                                                                                                class="material-icons-outlined cell-icon">
                                                                                            </i><span
                                                                                                class="cell-text">Nov
                                                                                                15, 2024</span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Ibuprofen </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                400mg </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                As needed </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                14 days </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Dr. Smith </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content">
                                                                                            <div
                                                                                                class="badge-solid col-blue">
                                                                                                Completed </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-actions">
                                                                                            <button
                                                                                                aria-label="Edit record"
                                                                                                class="action-icon-btn edit-btn"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-vector-pen" viewBox="0 0 16 16" style="background:transparent;"><path fill-rule="evenodd" d="M10.646.646a.5.5 0 0 1 .708 0l4 4a.5.5 0 0 1 0 .708l-1.902 1.902-.829 3.313a1.5 1.5 0 0 1-1.024 1.073L1.254 14.746 4.358 4.4A1.5 1.5 0 0 1 5.43 3.377l3.313-.828zm-1.8 2.908-3.173.793a.5.5 0 0 0-.358.342l-2.57 8.565 8.567-2.57a.5.5 0 0 0 .34-.357l.794-3.174-3.6-3.6z"/><path fill-rule="evenodd" d="M2.832 13.228 8 9a1 1 0 1 0-1-1l-4.228 5.168-.026.086z"/></svg></button><button
                                                                                                aria-label="Delete record"
                                                                                                class="action-icon-btn delete-btn"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#ef4444" class="bi bi-trash" viewBox="0 0 16 16" style="background:transparent;"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/></svg></button>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div
                                                                                class="datatable-row-group datatable-row-right">
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="datatable-row-wrapper">
                                                                        <div class="datatable-body-row datatable-row-even"
                                                                            draggable="false" role="row" tabindex="-1">
                                                                            <div
                                                                                class="datatable-row-group datatable-row-left">
                                                                            </div>
                                                                            <div
                                                                                class="datatable-row-center datatable-row-group">
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <label
                                                                                            class="datatable-checkbox"><input
                                                                                                type="checkbox" /></label><span
                                                                                            title=""></span></div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                RX005 </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div
                                                                                            class="cell-content cell-image-name">
                                                                                            <img alt="User avatar"
                                                                                                class="cell-avatar"
                                                                                                src="<?php echo esc_url(!empty($doctor['avatar_url']) ? $doctor['avatar_url'] : DIOR_PORTAL_URL . 'assets/images/logo-q.png'); ?>" />
                                                                                            <div
                                                                                                class="cell-text-wrapper">
                                                                                                <div class="cell-text">
                                                                                                    Lisa Anderson</div>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div
                                                                                            class="cell-content cell-icon-text">
                                                                                            <i
                                                                                                class="material-icons-outlined cell-icon">
                                                                                            </i><span
                                                                                                class="cell-text">Oct
                                                                                                30, 2024</span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Loratadine </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                10mg </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Once daily </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                14 days </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Dr. Smith </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content">
                                                                                            <div
                                                                                                class="badge-solid col-blue">
                                                                                                Completed </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-actions">
                                                                                            <button
                                                                                                aria-label="Edit record"
                                                                                                class="action-icon-btn edit-btn"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-vector-pen" viewBox="0 0 16 16" style="background:transparent;"><path fill-rule="evenodd" d="M10.646.646a.5.5 0 0 1 .708 0l4 4a.5.5 0 0 1 0 .708l-1.902 1.902-.829 3.313a1.5 1.5 0 0 1-1.024 1.073L1.254 14.746 4.358 4.4A1.5 1.5 0 0 1 5.43 3.377l3.313-.828zm-1.8 2.908-3.173.793a.5.5 0 0 0-.358.342l-2.57 8.565 8.567-2.57a.5.5 0 0 0 .34-.357l.794-3.174-3.6-3.6z"/><path fill-rule="evenodd" d="M2.832 13.228 8 9a1 1 0 1 0-1-1l-4.228 5.168-.026.086z"/></svg></button><button
                                                                                                aria-label="Delete record"
                                                                                                class="action-icon-btn delete-btn"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#ef4444" class="bi bi-trash" viewBox="0 0 16 16" style="background:transparent;"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/></svg></button>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div
                                                                                class="datatable-row-group datatable-row-right">
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="datatable-row-wrapper">
                                                                        <div class="datatable-body-row datatable-row-odd"
                                                                            draggable="false" role="row" tabindex="-1">
                                                                            <div
                                                                                class="datatable-row-group datatable-row-left">
                                                                            </div>
                                                                            <div
                                                                                class="datatable-row-center datatable-row-group">
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <label
                                                                                            class="datatable-checkbox"><input
                                                                                                type="checkbox" /></label><span
                                                                                            title=""></span></div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                RX006 </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div
                                                                                            class="cell-content cell-image-name">
                                                                                            <img alt="User avatar"
                                                                                                class="cell-avatar"
                                                                                                src="<?php echo esc_url(!empty($doctor['avatar_url']) ? $doctor['avatar_url'] : DIOR_PORTAL_URL . 'assets/images/logo-q.png'); ?>" />
                                                                                            <div
                                                                                                class="cell-text-wrapper">
                                                                                                <div class="cell-text">
                                                                                                    James Taylor</div>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div
                                                                                            class="cell-content cell-icon-text">
                                                                                            <i
                                                                                                class="material-icons-outlined cell-icon">
                                                                                            </i><span
                                                                                                class="cell-text">Nov
                                                                                                19, 2024</span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Omeprazole </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                20mg </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Once daily before
                                                                                                breakfast </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                30 days </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Dr. Smith </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content">
                                                                                            <div
                                                                                                class="badge-solid col-green">
                                                                                                Active </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-actions">
                                                                                            <button
                                                                                                aria-label="Edit record"
                                                                                                class="action-icon-btn edit-btn"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-vector-pen" viewBox="0 0 16 16" style="background:transparent;"><path fill-rule="evenodd" d="M10.646.646a.5.5 0 0 1 .708 0l4 4a.5.5 0 0 1 0 .708l-1.902 1.902-.829 3.313a1.5 1.5 0 0 1-1.024 1.073L1.254 14.746 4.358 4.4A1.5 1.5 0 0 1 5.43 3.377l3.313-.828zm-1.8 2.908-3.173.793a.5.5 0 0 0-.358.342l-2.57 8.565 8.567-2.57a.5.5 0 0 0 .34-.357l.794-3.174-3.6-3.6z"/><path fill-rule="evenodd" d="M2.832 13.228 8 9a1 1 0 1 0-1-1l-4.228 5.168-.026.086z"/></svg></button><button
                                                                                                aria-label="Delete record"
                                                                                                class="action-icon-btn delete-btn"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#ef4444" class="bi bi-trash" viewBox="0 0 16 16" style="background:transparent;"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/></svg></button>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div
                                                                                class="datatable-row-group datatable-row-right">
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="datatable-row-wrapper">
                                                                        <div class="datatable-body-row datatable-row-even"
                                                                            draggable="false" role="row" tabindex="-1">
                                                                            <div
                                                                                class="datatable-row-group datatable-row-left">
                                                                            </div>
                                                                            <div
                                                                                class="datatable-row-center datatable-row-group">
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <label
                                                                                            class="datatable-checkbox"><input
                                                                                                type="checkbox" /></label><span
                                                                                            title=""></span></div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                RX007 </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div
                                                                                            class="cell-content cell-image-name">
                                                                                            <img alt="User avatar"
                                                                                                class="cell-avatar"
                                                                                                src="<?php echo esc_url(!empty($doctor['avatar_url']) ? $doctor['avatar_url'] : DIOR_PORTAL_URL . 'assets/images/logo-q.png'); ?>" />
                                                                                            <div
                                                                                                class="cell-text-wrapper">
                                                                                                <div class="cell-text">
                                                                                                    Maria Garcia</div>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div
                                                                                            class="cell-content cell-icon-text">
                                                                                            <i
                                                                                                class="material-icons-outlined cell-icon">
                                                                                            </i><span
                                                                                                class="cell-text">Sep
                                                                                                25, 2024</span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Levothyroxine </span>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                50mcg </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Once daily on empty
                                                                                                stomach </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                90 days </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Dr. Smith </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content">
                                                                                            <div
                                                                                                class="badge-solid col-red">
                                                                                                Cancelled </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-actions">
                                                                                            <button
                                                                                                aria-label="Edit record"
                                                                                                class="action-icon-btn edit-btn"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-vector-pen" viewBox="0 0 16 16" style="background:transparent;"><path fill-rule="evenodd" d="M10.646.646a.5.5 0 0 1 .708 0l4 4a.5.5 0 0 1 0 .708l-1.902 1.902-.829 3.313a1.5 1.5 0 0 1-1.024 1.073L1.254 14.746 4.358 4.4A1.5 1.5 0 0 1 5.43 3.377l3.313-.828zm-1.8 2.908-3.173.793a.5.5 0 0 0-.358.342l-2.57 8.565 8.567-2.57a.5.5 0 0 0 .34-.357l.794-3.174-3.6-3.6z"/><path fill-rule="evenodd" d="M2.832 13.228 8 9a1 1 0 1 0-1-1l-4.228 5.168-.026.086z"/></svg></button><button
                                                                                                aria-label="Delete record"
                                                                                                class="action-icon-btn delete-btn"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#ef4444" class="bi bi-trash" viewBox="0 0 16 16" style="background:transparent;"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/></svg></button>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div
                                                                                class="datatable-row-group datatable-row-right">
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="datatable-row-wrapper">
                                                                        <div class="datatable-body-row datatable-row-odd"
                                                                            draggable="false" role="row" tabindex="-1">
                                                                            <div
                                                                                class="datatable-row-group datatable-row-left">
                                                                            </div>
                                                                            <div
                                                                                class="datatable-row-center datatable-row-group">
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <label
                                                                                            class="datatable-checkbox"><input
                                                                                                type="checkbox" /></label><span
                                                                                            title=""></span></div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                RX008 </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div
                                                                                            class="cell-content cell-image-name">
                                                                                            <img alt="User avatar"
                                                                                                class="cell-avatar"
                                                                                                src="<?php echo esc_url(!empty($doctor['avatar_url']) ? $doctor['avatar_url'] : DIOR_PORTAL_URL . 'assets/images/logo-q.png'); ?>" />
                                                                                            <div
                                                                                                class="cell-text-wrapper">
                                                                                                <div class="cell-text">
                                                                                                    Robert Brown</div>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div
                                                                                            class="cell-content cell-icon-text">
                                                                                            <i
                                                                                                class="material-icons-outlined cell-icon">
                                                                                            </i><span
                                                                                                class="cell-text">Nov
                                                                                                21, 2024</span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Atorvastatin </span>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                20mg </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Once daily at bedtime
                                                                                            </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                90 days </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Dr. Smith </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content">
                                                                                            <div
                                                                                                class="badge-solid col-green">
                                                                                                Active </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-actions">
                                                                                            <button
                                                                                                aria-label="Edit record"
                                                                                                class="action-icon-btn edit-btn"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-vector-pen" viewBox="0 0 16 16" style="background:transparent;"><path fill-rule="evenodd" d="M10.646.646a.5.5 0 0 1 .708 0l4 4a.5.5 0 0 1 0 .708l-1.902 1.902-.829 3.313a1.5 1.5 0 0 1-1.024 1.073L1.254 14.746 4.358 4.4A1.5 1.5 0 0 1 5.43 3.377l3.313-.828zm-1.8 2.908-3.173.793a.5.5 0 0 0-.358.342l-2.57 8.565 8.567-2.57a.5.5 0 0 0 .34-.357l.794-3.174-3.6-3.6z"/><path fill-rule="evenodd" d="M2.832 13.228 8 9a1 1 0 1 0-1-1l-4.228 5.168-.026.086z"/></svg></button><button
                                                                                                aria-label="Delete record"
                                                                                                class="action-icon-btn delete-btn"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#ef4444" class="bi bi-trash" viewBox="0 0 16 16" style="background:transparent;"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/></svg></button>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div
                                                                                class="datatable-row-group datatable-row-right">
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="datatable-row-wrapper">
                                                                        <div class="datatable-body-row datatable-row-even"
                                                                            draggable="false" role="row" tabindex="-1">
                                                                            <div
                                                                                class="datatable-row-group datatable-row-left">
                                                                            </div>
                                                                            <div
                                                                                class="datatable-row-center datatable-row-group">
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <label
                                                                                            class="datatable-checkbox"><input
                                                                                                type="checkbox" /></label><span
                                                                                            title=""></span></div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                RX009 </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div
                                                                                            class="cell-content cell-image-name">
                                                                                            <img alt="User avatar"
                                                                                                class="cell-avatar"
                                                                                                src="<?php echo esc_url(!empty($doctor['avatar_url']) ? $doctor['avatar_url'] : DIOR_PORTAL_URL . 'assets/images/logo-q.png'); ?>" />
                                                                                            <div
                                                                                                class="cell-text-wrapper">
                                                                                                <div class="cell-text">
                                                                                                    Jennifer Martinez
                                                                                                </div>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div
                                                                                            class="cell-content cell-icon-text">
                                                                                            <i
                                                                                                class="material-icons-outlined cell-icon">
                                                                                            </i><span
                                                                                                class="cell-text">Nov
                                                                                                17, 2024</span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Sertraline </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                50mg </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Once daily </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                30 days </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Dr. Smith </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content">
                                                                                            <div
                                                                                                class="badge-solid col-green">
                                                                                                Active </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-actions">
                                                                                            <button
                                                                                                aria-label="Edit record"
                                                                                                class="action-icon-btn edit-btn"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-vector-pen" viewBox="0 0 16 16" style="background:transparent;"><path fill-rule="evenodd" d="M10.646.646a.5.5 0 0 1 .708 0l4 4a.5.5 0 0 1 0 .708l-1.902 1.902-.829 3.313a1.5 1.5 0 0 1-1.024 1.073L1.254 14.746 4.358 4.4A1.5 1.5 0 0 1 5.43 3.377l3.313-.828zm-1.8 2.908-3.173.793a.5.5 0 0 0-.358.342l-2.57 8.565 8.567-2.57a.5.5 0 0 0 .34-.357l.794-3.174-3.6-3.6z"/><path fill-rule="evenodd" d="M2.832 13.228 8 9a1 1 0 1 0-1-1l-4.228 5.168-.026.086z"/></svg></button><button
                                                                                                aria-label="Delete record"
                                                                                                class="action-icon-btn delete-btn"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#ef4444" class="bi bi-trash" viewBox="0 0 16 16" style="background:transparent;"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/></svg></button>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div
                                                                                class="datatable-row-group datatable-row-right">
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="datatable-row-wrapper">
                                                                        <div class="datatable-body-row datatable-row-odd"
                                                                            draggable="false" role="row" tabindex="-1">
                                                                            <div
                                                                                class="datatable-row-group datatable-row-left">
                                                                            </div>
                                                                            <div
                                                                                class="datatable-row-center datatable-row-group">
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <label
                                                                                            class="datatable-checkbox"><input
                                                                                                type="checkbox" /></label><span
                                                                                            title=""></span></div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                RX010 </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div
                                                                                            class="cell-content cell-image-name">
                                                                                            <img alt="User avatar"
                                                                                                class="cell-avatar"
                                                                                                src="<?php echo esc_url(!empty($doctor['avatar_url']) ? $doctor['avatar_url'] : DIOR_PORTAL_URL . 'assets/images/logo-q.png'); ?>" />
                                                                                            <div
                                                                                                class="cell-text-wrapper">
                                                                                                <div class="cell-text">
                                                                                                    Christopher Lee
                                                                                                </div>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div
                                                                                            class="cell-content cell-icon-text">
                                                                                            <i
                                                                                                class="material-icons-outlined cell-icon">
                                                                                            </i><span
                                                                                                class="cell-text">Nov
                                                                                                16, 2024</span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Albuterol Inhaler
                                                                                            </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                90mcg </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                As needed for wheezing
                                                                                            </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                90 days </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Dr. Smith </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content">
                                                                                            <div
                                                                                                class="badge-solid col-green">
                                                                                                Active </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-actions">
                                                                                            <button
                                                                                                aria-label="Edit record"
                                                                                                class="action-icon-btn edit-btn"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-vector-pen" viewBox="0 0 16 16" style="background:transparent;"><path fill-rule="evenodd" d="M10.646.646a.5.5 0 0 1 .708 0l4 4a.5.5 0 0 1 0 .708l-1.902 1.902-.829 3.313a1.5 1.5 0 0 1-1.024 1.073L1.254 14.746 4.358 4.4A1.5 1.5 0 0 1 5.43 3.377l3.313-.828zm-1.8 2.908-3.173.793a.5.5 0 0 0-.358.342l-2.57 8.565 8.567-2.57a.5.5 0 0 0 .34-.357l.794-3.174-3.6-3.6z"/><path fill-rule="evenodd" d="M2.832 13.228 8 9a1 1 0 1 0-1-1l-4.228 5.168-.026.086z"/></svg></button><button
                                                                                                aria-label="Delete record"
                                                                                                class="action-icon-btn delete-btn"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#ef4444" class="bi bi-trash" viewBox="0 0 16 16" style="background:transparent;"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/><path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/></svg></button>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div
                                                                                class="datatable-row-group datatable-row-right">
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="datatable-footer">
                                                        <div class="datatable-footer-inner selected-count">
                                                            <div class="page-count"><span> 0 selected / </span> 10 total
                                                            </div>
                                                            <div class="datatable-pager" hidden="">
                                                                <ul class="pager">
                                                                    <li class="disabled"><a
                                                                            aria-label="go to first page"
                                                                            role="button"><i
                                                                                class="datatable-icon-prev"></i></a>
                                                                    </li>
                                                                    <li class="disabled"><a
                                                                            aria-label="go to previous page"
                                                                            role="button"><i
                                                                                class="datatable-icon-left"></i></a>
                                                                    </li>
                                                                    <li aria-label="page 1" class="pages active"
                                                                        role="button"><a> 1 </a></li>
                                                                    <li class="disabled"><a aria-label="go to next page"
                                                                            role="button"><i
                                                                                class="datatable-icon-right"></i></a>
                                                                    </li>
                                                                    <li class="disabled"><a aria-label="go to last page"
                                                                            role="button"><i
                                                                                class="datatable-icon-skip"></i></a>
                                                                    </li>
                                                                </ul>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</section>