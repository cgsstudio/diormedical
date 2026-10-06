<?php defined('ABSPATH') || exit; ?>
<section class="dior-tab-panel dior-source-panel" id="source-telemedicine">
    <div class="dior-source-inner">
        <section class="main-content">
            <div>
                <div class="breadcrumb-main">
                    <div class="row">
                        <div class="col-6">
                            <div class="breadcrumb-title">
                                <h4 class="page-title d-flex align-items-center flex-wrap gap-2">
                                    <span>Telemedicine</span></h4>
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
                                <li class="breadcrumb-item active">Telemedicine</li>
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
                                                    <h2 class="table-title">Telemedicine Sessions</h2>
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
                                                                                role="button" tabindex="0"> Session ID
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
                                                                                role="button" tabindex="0"> Time
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
                                                                                role="button" tabindex="0"> Type
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
                                                                                                TM001 </span></div>
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
                                                                                                25, 2024</span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div
                                                                                            class="cell-content cell-icon-text">
                                                                                            <i
                                                                                                class="cell-icon material-icons-outlined">
                                                                                                schedule </i><span
                                                                                                class="cell-text">10:00
                                                                                                AM</span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                30 minutes </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Follow-up </span></div>
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
                                                                                                Scheduled </div>
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
                                                                                                TM002 </span></div>
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
                                                                                                24, 2024</span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div
                                                                                            class="cell-content cell-icon-text">
                                                                                            <i
                                                                                                class="cell-icon material-icons-outlined">
                                                                                                schedule </i><span
                                                                                                class="cell-text">02:00
                                                                                                PM</span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                45 minutes </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Initial Consultation
                                                                                            </span></div>
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
                                                                                                TM003 </span></div>
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
                                                                                                26, 2024</span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div
                                                                                            class="cell-content cell-icon-text">
                                                                                            <i
                                                                                                class="cell-icon material-icons-outlined">
                                                                                                schedule </i><span
                                                                                                class="cell-text">11:00
                                                                                                AM</span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                30 minutes </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Prescription Renewal
                                                                                            </span></div>
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
                                                                                                Scheduled </div>
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
                                                                                                TM004 </span></div>
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
                                                                                                class="cell-text">Nov
                                                                                                23, 2024</span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div
                                                                                            class="cell-content cell-icon-text">
                                                                                            <i
                                                                                                class="cell-icon material-icons-outlined">
                                                                                                schedule </i><span
                                                                                                class="cell-text">09:00
                                                                                                AM</span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                30 minutes </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Follow-up </span></div>
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
                                                                                                TM005 </span></div>
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
                                                                                                27, 2024</span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div
                                                                                            class="cell-content cell-icon-text">
                                                                                            <i
                                                                                                class="cell-icon material-icons-outlined">
                                                                                                schedule </i><span
                                                                                                class="cell-text">03:30
                                                                                                PM</span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                30 minutes </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Routine Check-up </span>
                                                                                        </div>
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
                                                                                                Scheduled </div>
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
                                                                                                TM006 </span></div>
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
                                                                                                    James Wilson</div>
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
                                                                                        <div
                                                                                            class="cell-content cell-icon-text">
                                                                                            <i
                                                                                                class="cell-icon material-icons-outlined">
                                                                                                schedule </i><span
                                                                                                class="cell-text">01:00
                                                                                                PM</span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                45 minutes </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Initial Consultation
                                                                                            </span></div>
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
                                                                                                TM007 </span></div>
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
                                                                                                    Sophia Martinez
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
                                                                                                28, 2024</span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div
                                                                                            class="cell-content cell-icon-text">
                                                                                            <i
                                                                                                class="cell-icon material-icons-outlined">
                                                                                                schedule </i><span
                                                                                                class="cell-text">10:30
                                                                                                AM</span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                30 minutes </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Diagnostic Review
                                                                                            </span></div>
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
                                                                                                Scheduled </div>
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
                                                                                                TM008 </span></div>
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
                                                                                                    Daniel Thompson
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
                                                                                                21, 2024</span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div
                                                                                            class="cell-content cell-icon-text">
                                                                                            <i
                                                                                                class="cell-icon material-icons-outlined">
                                                                                                schedule </i><span
                                                                                                class="cell-text">04:00
                                                                                                PM</span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                30 minutes </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Follow-up </span></div>
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
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="datatable-footer">
                                                        <div class="datatable-footer-inner selected-count">
                                                            <div class="page-count"><span> 0 selected / </span> 8 total
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