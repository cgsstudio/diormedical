<?php defined('ABSPATH') || exit; ?>
<section class="dior-tab-panel dior-source-panel" id="source-all-patients">
    <div class="dior-source-inner">
        <section class="main-content">
            <div>
                <div class="breadcrumb-main">
                    <div class="row">
                        <div class="col-6">
                            <div class="breadcrumb-title">
                                <h4 class="page-title d-flex align-items-center flex-wrap gap-2"><span>All
                                        Patients</span></h4>
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
                                <li class="breadcrumb-item"><a href="#">Patients</a></li>
                                <li class="breadcrumb-item active">All Patients</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <div class="section-body">
                <div>
                    <div class="master-table-wrapper">
                        <div class="master-table-container">
                            <div class="master-table-card">
                                <div class="master-table-header">
                                    <div class="header-content">
                                        <div class="table-title-section">
                                            <h2 class="table-title">All Patients</h2>
                                            <div class="title-accent"></div>
                                        </div>
                                        <div class="header-actions-group">
                                            <div class="search-container"><i
                                                    class="material-icons search-icon">search</i><input
                                                    aria-label="Search box" class="search-input"
                                                    placeholder="Search records..." type="text" /></div>
                                            <div class="action-buttons"><button aria-label="Delete 0 selected items"
                                                    class="action-btn action-btn-danger" hidden=""><i
                                                        class="material-icons">delete</i><span
                                                        class="btn-ripple"></span></button><button
                                                    aria-label="Add new record" class="action-btn action-btn-primary"><i
                                                        class="material-icons">add</i><span
                                                        class="btn-ripple"></span></button><button
                                                    aria-label="Export to Excel"
                                                    class="action-btn action-btn-success"><i
                                                        class="material-icons">file_download</i><span
                                                        class="btn-ripple"></span></button><button
                                                    aria-label="Refresh data" class="action-btn action-btn-info"><i
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
                                                                <div class="datatable-header-cell-template-wrap"><label
                                                                        class="datatable-checkbox"><input
                                                                            type="checkbox" /></label><span
                                                                        class="datatable-header-cell-wrapper"><span
                                                                            class="datatable-header-cell-label draggable">
                                                                        </span></span><span></span></div><span
                                                                    class="resize-handle--not-resizable"></span>
                                                            </div>
                                                            <div class="datatable-header-cell resizeable sortable"
                                                                draggable="" long-press="" resizeable=""
                                                                role="columnheader" tabindex="0">
                                                                <div class="datatable-header-cell-template-wrap"><span
                                                                        aria-sort="none"
                                                                        class="datatable-header-cell-wrapper"
                                                                        role="button" tabindex="0"> Name </span><span
                                                                        class="datatable-icon-sort-unset sort-btn"></span>
                                                                </div><span class="resize-handle"></span>
                                                            </div>
                                                            <div class="datatable-header-cell resizeable sortable"
                                                                draggable="" long-press="" resizeable=""
                                                                role="columnheader" tabindex="0">
                                                                <div class="datatable-header-cell-template-wrap"><span
                                                                        aria-sort="none"
                                                                        class="datatable-header-cell-wrapper"
                                                                        role="button" tabindex="0"> Treatment
                                                                    </span><span
                                                                        class="datatable-icon-sort-unset sort-btn"></span>
                                                                </div><span class="resize-handle"></span>
                                                            </div>
                                                            <div class="datatable-header-cell resizeable sortable"
                                                                draggable="" long-press="" resizeable=""
                                                                role="columnheader" tabindex="0">
                                                                <div class="datatable-header-cell-template-wrap"><span
                                                                        aria-sort="none"
                                                                        class="datatable-header-cell-wrapper"
                                                                        role="button" tabindex="0"> Gender </span><span
                                                                        class="datatable-icon-sort-unset sort-btn"></span>
                                                                </div><span class="resize-handle"></span>
                                                            </div>
                                                            <div class="datatable-header-cell resizeable sortable"
                                                                draggable="" long-press="" resizeable=""
                                                                role="columnheader" tabindex="0">
                                                                <div class="datatable-header-cell-template-wrap"><span
                                                                        aria-sort="none"
                                                                        class="datatable-header-cell-wrapper"
                                                                        role="button" tabindex="0"> Phone </span><span
                                                                        class="datatable-icon-sort-unset sort-btn"></span>
                                                                </div><span class="resize-handle"></span>
                                                            </div>
                                                            <div class="datatable-header-cell resizeable sortable"
                                                                draggable="" long-press="" resizeable=""
                                                                role="columnheader" tabindex="0">
                                                                <div class="datatable-header-cell-template-wrap"><span
                                                                        aria-sort="none"
                                                                        class="datatable-header-cell-wrapper"
                                                                        role="button" tabindex="0"> Admission Date
                                                                    </span><span
                                                                        class="datatable-icon-sort-unset sort-btn"></span>
                                                                </div><span class="resize-handle"></span>
                                                            </div>
                                                            <div class="datatable-header-cell resizeable sortable"
                                                                draggable="" long-press="" resizeable=""
                                                                role="columnheader" tabindex="0">
                                                                <div class="datatable-header-cell-template-wrap"><span
                                                                        aria-sort="none"
                                                                        class="datatable-header-cell-wrapper"
                                                                        role="button" tabindex="0"> Blood Group
                                                                    </span><span
                                                                        class="datatable-icon-sort-unset sort-btn"></span>
                                                                </div><span class="resize-handle"></span>
                                                            </div>
                                                            <div class="datatable-header-cell resizeable sortable"
                                                                draggable="" long-press="" resizeable=""
                                                                role="columnheader" tabindex="0">
                                                                <div class="datatable-header-cell-template-wrap"><span
                                                                        aria-sort="none"
                                                                        class="datatable-header-cell-wrapper"
                                                                        role="button" tabindex="0"> Doctor </span><span
                                                                        class="datatable-icon-sort-unset sort-btn"></span>
                                                                </div><span class="resize-handle"></span>
                                                            </div>
                                                            <div class="datatable-header-cell resizeable sortable"
                                                                draggable="" long-press="" resizeable=""
                                                                role="columnheader" tabindex="0">
                                                                <div class="datatable-header-cell-template-wrap"><span
                                                                        aria-sort="none"
                                                                        class="datatable-header-cell-wrapper"
                                                                        role="button" tabindex="0"> Address </span><span
                                                                        class="datatable-icon-sort-unset sort-btn"></span>
                                                                </div><span class="resize-handle"></span>
                                                            </div>
                                                            <div class="datatable-header-cell resizeable sortable"
                                                                draggable="" long-press="" resizeable=""
                                                                role="columnheader" tabindex="0">
                                                                <div class="datatable-header-cell-template-wrap"><span
                                                                        aria-sort="none"
                                                                        class="datatable-header-cell-wrapper"
                                                                        role="button" tabindex="0"> Status </span><span
                                                                        class="datatable-icon-sort-unset sort-btn"></span>
                                                                </div><span class="resize-handle"></span>
                                                            </div>
                                                            <div class="datatable-header-cell resizeable" draggable=""
                                                                long-press="" resizeable="" role="columnheader"
                                                                tabindex="-1">
                                                                <div class="datatable-header-cell-template-wrap"><span
                                                                        aria-sort="none"
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
                                                                    <div class="datatable-row-group datatable-row-left">
                                                                    </div>
                                                                    <div
                                                                        class="datatable-row-center datatable-row-group">
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <label class="datatable-checkbox"><input
                                                                                        type="checkbox" /></label><span
                                                                                    title=""></span>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-image-name">
                                                                                    <img alt="User avatar"
                                                                                        class="cell-avatar"
                                                                                        src="<?php echo esc_url(!empty($doctor['avatar_url']) ? $doctor['avatar_url'] : DIOR_PORTAL_URL . 'assets/images/logo-q.png'); ?>" />
                                                                                    <div class="cell-text-wrapper">
                                                                                        <div class="cell-text">Ashton
                                                                                            Cox</div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-content"><span> Malaria
                                                                                    </span></div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-icon-text">
                                                                                    <i
                                                                                        class="material-icons-outlined cell-icon">male</i><span
                                                                                        class="cell-text"> male </span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-icon-text">
                                                                                    <i
                                                                                        class="cell-icon material-icons-outlined">
                                                                                        phone </i><span
                                                                                        class="cell-text">1234567890</span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-icon-text">
                                                                                    <i
                                                                                        class="material-icons-outlined cell-icon"></i><span
                                                                                        class="cell-text">Jan 15,
                                                                                        2024</span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-content"><span> B+
                                                                                    </span></div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-content"><span> Dr.
                                                                                        John Doe </span></div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-icon-text">
                                                                                    <i
                                                                                        class="cell-icon material-icons-outlined">
                                                                                        location_on </i><span
                                                                                        class="cell-text">11, Shyam
                                                                                        Appt., Rajkot</span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-content">
                                                                                    <div class="badge-solid col-green">
                                                                                        Recovered </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-actions"><button
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
                                                                    <div class="datatable-row-group datatable-row-left">
                                                                    </div>
                                                                    <div
                                                                        class="datatable-row-center datatable-row-group">
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <label class="datatable-checkbox"><input
                                                                                        type="checkbox" /></label><span
                                                                                    title=""></span>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-image-name">
                                                                                    <img alt="User avatar"
                                                                                        class="cell-avatar"
                                                                                        src="<?php echo esc_url(!empty($doctor['avatar_url']) ? $doctor['avatar_url'] : DIOR_PORTAL_URL . 'assets/images/logo-q.png'); ?>" />
                                                                                    <div class="cell-text-wrapper">
                                                                                        <div class="cell-text">Jessica
                                                                                            Williams</div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-content"><span> Dengue
                                                                                    </span></div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-icon-text">
                                                                                    <i
                                                                                        class="material-icons-outlined cell-icon">female</i><span
                                                                                        class="cell-text"> female
                                                                                    </span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-icon-text">
                                                                                    <i
                                                                                        class="cell-icon material-icons-outlined">
                                                                                        phone </i><span
                                                                                        class="cell-text">9876543210</span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-icon-text">
                                                                                    <i
                                                                                        class="material-icons-outlined cell-icon"></i><span
                                                                                        class="cell-text">Feb 10,
                                                                                        2024</span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-content"><span> O+
                                                                                    </span></div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-content"><span> Dr.
                                                                                        Sarah Smith </span></div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-icon-text">
                                                                                    <i
                                                                                        class="cell-icon material-icons-outlined">
                                                                                        location_on </i><span
                                                                                        class="cell-text">23, Green
                                                                                        Park, Surat</span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-content">
                                                                                    <div class="badge-solid col-green">
                                                                                        Recovered </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-actions"><button
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
                                                                    <div class="datatable-row-group datatable-row-left">
                                                                    </div>
                                                                    <div
                                                                        class="datatable-row-center datatable-row-group">
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <label class="datatable-checkbox"><input
                                                                                        type="checkbox" /></label><span
                                                                                    title=""></span>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-image-name">
                                                                                    <img alt="User avatar"
                                                                                        class="cell-avatar"
                                                                                        src="<?php echo esc_url(!empty($doctor['avatar_url']) ? $doctor['avatar_url'] : DIOR_PORTAL_URL . 'assets/images/logo-q.png'); ?>" />
                                                                                    <div class="cell-text-wrapper">
                                                                                        <div class="cell-text">Oliver
                                                                                            Jones</div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-content"><span> Flu
                                                                                    </span></div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-icon-text">
                                                                                    <i
                                                                                        class="material-icons-outlined cell-icon">male</i><span
                                                                                        class="cell-text"> male </span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-icon-text">
                                                                                    <i
                                                                                        class="cell-icon material-icons-outlined">
                                                                                        phone </i><span
                                                                                        class="cell-text">1122334455</span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-icon-text">
                                                                                    <i
                                                                                        class="material-icons-outlined cell-icon"></i><span
                                                                                        class="cell-text">Mar 5,
                                                                                        2024</span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-content"><span> A+
                                                                                    </span></div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-content"><span> Dr.
                                                                                        Rajesh </span></div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-icon-text">
                                                                                    <i
                                                                                        class="cell-icon material-icons-outlined">
                                                                                        location_on </i><span
                                                                                        class="cell-text">45, Sunrise
                                                                                        Villa, Ahmedabad</span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-content">
                                                                                    <div class="badge-solid col-indigo">
                                                                                        Under Treatment </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-actions"><button
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
                                                                    <div class="datatable-row-group datatable-row-left">
                                                                    </div>
                                                                    <div
                                                                        class="datatable-row-center datatable-row-group">
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <label class="datatable-checkbox"><input
                                                                                        type="checkbox" /></label><span
                                                                                    title=""></span>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-image-name">
                                                                                    <img alt="User avatar"
                                                                                        class="cell-avatar"
                                                                                        src="<?php echo esc_url(!empty($doctor['avatar_url']) ? $doctor['avatar_url'] : DIOR_PORTAL_URL . 'assets/images/logo-q.png'); ?>" />
                                                                                    <div class="cell-text-wrapper">
                                                                                        <div class="cell-text">Emily
                                                                                            Davis</div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-content"><span>
                                                                                        Appendicitis </span></div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-icon-text">
                                                                                    <i
                                                                                        class="material-icons-outlined cell-icon">female</i><span
                                                                                        class="cell-text"> female
                                                                                    </span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-icon-text">
                                                                                    <i
                                                                                        class="cell-icon material-icons-outlined">
                                                                                        phone </i><span
                                                                                        class="cell-text">2233445566</span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-icon-text">
                                                                                    <i
                                                                                        class="material-icons-outlined cell-icon"></i><span
                                                                                        class="cell-text">Mar 15,
                                                                                        2024</span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-content"><span> B-
                                                                                    </span></div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-content"><span> Dr. Jay
                                                                                        Soni </span></div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-icon-text">
                                                                                    <i
                                                                                        class="cell-icon material-icons-outlined">
                                                                                        location_on </i><span
                                                                                        class="cell-text">78, River
                                                                                        Side, Baroda</span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-content">
                                                                                    <div class="badge-solid col-green">
                                                                                        Recovered </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-actions"><button
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
                                                                    <div class="datatable-row-group datatable-row-left">
                                                                    </div>
                                                                    <div
                                                                        class="datatable-row-center datatable-row-group">
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <label class="datatable-checkbox"><input
                                                                                        type="checkbox" /></label><span
                                                                                    title=""></span>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-image-name">
                                                                                    <img alt="User avatar"
                                                                                        class="cell-avatar"
                                                                                        src="<?php echo esc_url(!empty($doctor['avatar_url']) ? $doctor['avatar_url'] : DIOR_PORTAL_URL . 'assets/images/logo-q.png'); ?>" />
                                                                                    <div class="cell-text-wrapper">
                                                                                        <div class="cell-text">Michael
                                                                                            Brown</div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-content"><span>
                                                                                        Pneumonia </span></div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-icon-text">
                                                                                    <i
                                                                                        class="material-icons-outlined cell-icon">male</i><span
                                                                                        class="cell-text"> male </span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-icon-text">
                                                                                    <i
                                                                                        class="cell-icon material-icons-outlined">
                                                                                        phone </i><span
                                                                                        class="cell-text">3344556677</span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-icon-text">
                                                                                    <i
                                                                                        class="material-icons-outlined cell-icon"></i><span
                                                                                        class="cell-text">Apr 1,
                                                                                        2024</span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-content"><span> AB+
                                                                                    </span></div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-content"><span> Dr.
                                                                                        Emma Watson </span></div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-icon-text">
                                                                                    <i
                                                                                        class="cell-icon material-icons-outlined">
                                                                                        location_on </i><span
                                                                                        class="cell-text">90, Sunlight
                                                                                        Plaza, Surat</span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-content">
                                                                                    <div class="badge-solid col-indigo">
                                                                                        Under Treatment </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-actions"><button
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
                                                                    <div class="datatable-row-group datatable-row-left">
                                                                    </div>
                                                                    <div
                                                                        class="datatable-row-center datatable-row-group">
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <label class="datatable-checkbox"><input
                                                                                        type="checkbox" /></label><span
                                                                                    title=""></span>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-image-name">
                                                                                    <img alt="User avatar"
                                                                                        class="cell-avatar"
                                                                                        src="<?php echo esc_url(!empty($doctor['avatar_url']) ? $doctor['avatar_url'] : DIOR_PORTAL_URL . 'assets/images/logo-q.png'); ?>" />
                                                                                    <div class="cell-text-wrapper">
                                                                                        <div class="cell-text">Sophia
                                                                                            Miller</div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-content"><span> Chronic
                                                                                        Cough </span></div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-icon-text">
                                                                                    <i
                                                                                        class="material-icons-outlined cell-icon">female</i><span
                                                                                        class="cell-text"> female
                                                                                    </span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-icon-text">
                                                                                    <i
                                                                                        class="cell-icon material-icons-outlined">
                                                                                        phone </i><span
                                                                                        class="cell-text">4455667788</span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-icon-text">
                                                                                    <i
                                                                                        class="material-icons-outlined cell-icon"></i><span
                                                                                        class="cell-text">Apr 10,
                                                                                        2024</span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-content"><span> O-
                                                                                    </span></div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-content"><span> Dr.
                                                                                        James Moore </span></div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-icon-text">
                                                                                    <i
                                                                                        class="cell-icon material-icons-outlined">
                                                                                        location_on </i><span
                                                                                        class="cell-text">32, Hill View,
                                                                                        Rajkot</span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-content">
                                                                                    <div class="badge-solid col-orange">
                                                                                        Under Observation </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-actions"><button
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
                                                                    <div class="datatable-row-group datatable-row-left">
                                                                    </div>
                                                                    <div
                                                                        class="datatable-row-center datatable-row-group">
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <label class="datatable-checkbox"><input
                                                                                        type="checkbox" /></label><span
                                                                                    title=""></span>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-image-name">
                                                                                    <img alt="User avatar"
                                                                                        class="cell-avatar"
                                                                                        src="<?php echo esc_url(!empty($doctor['avatar_url']) ? $doctor['avatar_url'] : DIOR_PORTAL_URL . 'assets/images/logo-q.png'); ?>" />
                                                                                    <div class="cell-text-wrapper">
                                                                                        <div class="cell-text">Liam
                                                                                            Wilson</div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-content"><span>
                                                                                        Fracture </span></div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-icon-text">
                                                                                    <i
                                                                                        class="material-icons-outlined cell-icon">male</i><span
                                                                                        class="cell-text"> male </span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-icon-text">
                                                                                    <i
                                                                                        class="cell-icon material-icons-outlined">
                                                                                        phone </i><span
                                                                                        class="cell-text">5566778899</span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-icon-text">
                                                                                    <i
                                                                                        class="material-icons-outlined cell-icon"></i><span
                                                                                        class="cell-text">Apr 15,
                                                                                        2024</span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-content"><span> A-
                                                                                    </span></div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-content"><span> Dr.
                                                                                        Rajesh </span></div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-icon-text">
                                                                                    <i
                                                                                        class="cell-icon material-icons-outlined">
                                                                                        location_on </i><span
                                                                                        class="cell-text">56, Park
                                                                                        Avenue, Surat</span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-content">
                                                                                    <div class="badge-solid col-green">
                                                                                        Recovered </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-actions"><button
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
                                                                    <div class="datatable-row-group datatable-row-left">
                                                                    </div>
                                                                    <div
                                                                        class="datatable-row-center datatable-row-group">
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <label class="datatable-checkbox"><input
                                                                                        type="checkbox" /></label><span
                                                                                    title=""></span>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-image-name">
                                                                                    <img alt="User avatar"
                                                                                        class="cell-avatar"
                                                                                        src="<?php echo esc_url(!empty($doctor['avatar_url']) ? $doctor['avatar_url'] : DIOR_PORTAL_URL . 'assets/images/logo-q.png'); ?>" />
                                                                                    <div class="cell-text-wrapper">
                                                                                        <div class="cell-text">Mia
                                                                                            Thompson</div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-content"><span>
                                                                                        Cholesterol </span></div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-icon-text">
                                                                                    <i
                                                                                        class="material-icons-outlined cell-icon">female</i><span
                                                                                        class="cell-text"> female
                                                                                    </span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-icon-text">
                                                                                    <i
                                                                                        class="cell-icon material-icons-outlined">
                                                                                        phone </i><span
                                                                                        class="cell-text">6677889900</span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-icon-text">
                                                                                    <i
                                                                                        class="material-icons-outlined cell-icon"></i><span
                                                                                        class="cell-text">Apr 20,
                                                                                        2024</span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-content"><span> B+
                                                                                    </span></div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-content"><span> Dr.
                                                                                        Sarah Smith </span></div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-icon-text">
                                                                                    <i
                                                                                        class="cell-icon material-icons-outlined">
                                                                                        location_on </i><span
                                                                                        class="cell-text">14, Central
                                                                                        Plaza, Ahmedabad</span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-content">
                                                                                    <div class="badge-solid col-indigo">
                                                                                        Under Treatment </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-actions"><button
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
                                                                    <div class="datatable-row-group datatable-row-left">
                                                                    </div>
                                                                    <div
                                                                        class="datatable-row-center datatable-row-group">
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <label class="datatable-checkbox"><input
                                                                                        type="checkbox" /></label><span
                                                                                    title=""></span>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-image-name">
                                                                                    <img alt="User avatar"
                                                                                        class="cell-avatar"
                                                                                        src="<?php echo esc_url(!empty($doctor['avatar_url']) ? $doctor['avatar_url'] : DIOR_PORTAL_URL . 'assets/images/logo-q.png'); ?>" />
                                                                                    <div class="cell-text-wrapper">
                                                                                        <div class="cell-text">Noah
                                                                                            Harris</div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-content"><span>
                                                                                        Headache </span></div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-icon-text">
                                                                                    <i
                                                                                        class="material-icons-outlined cell-icon">male</i><span
                                                                                        class="cell-text"> male </span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-icon-text">
                                                                                    <i
                                                                                        class="cell-icon material-icons-outlined">
                                                                                        phone </i><span
                                                                                        class="cell-text">7788990011</span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-icon-text">
                                                                                    <i
                                                                                        class="material-icons-outlined cell-icon"></i><span
                                                                                        class="cell-text">Apr 25,
                                                                                        2024</span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-content"><span> AB+
                                                                                    </span></div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-content"><span> Dr. Jay
                                                                                        Soni </span></div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-icon-text">
                                                                                    <i
                                                                                        class="cell-icon material-icons-outlined">
                                                                                        location_on </i><span
                                                                                        class="cell-text">29, Pearl
                                                                                        City, Baroda</span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-content">
                                                                                    <div class="badge-solid col-green">
                                                                                        Recovered </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-actions"><button
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
                                                                    <div class="datatable-row-group datatable-row-left">
                                                                    </div>
                                                                    <div
                                                                        class="datatable-row-center datatable-row-group">
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <label class="datatable-checkbox"><input
                                                                                        type="checkbox" /></label><span
                                                                                    title=""></span>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-image-name">
                                                                                    <img alt="User avatar"
                                                                                        class="cell-avatar"
                                                                                        src="<?php echo esc_url(!empty($doctor['avatar_url']) ? $doctor['avatar_url'] : DIOR_PORTAL_URL . 'assets/images/logo-q.png'); ?>" />
                                                                                    <div class="cell-text-wrapper">
                                                                                        <div class="cell-text">Isabella
                                                                                            White</div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-content"><span> Anemia
                                                                                    </span></div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-icon-text">
                                                                                    <i
                                                                                        class="material-icons-outlined cell-icon">female</i><span
                                                                                        class="cell-text"> female
                                                                                    </span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-icon-text">
                                                                                    <i
                                                                                        class="cell-icon material-icons-outlined">
                                                                                        phone </i><span
                                                                                        class="cell-text">8899001122</span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-icon-text">
                                                                                    <i
                                                                                        class="material-icons-outlined cell-icon"></i><span
                                                                                        class="cell-text">May 1,
                                                                                        2024</span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-content"><span> O+
                                                                                    </span></div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-content"><span> Dr.
                                                                                        Emma Watson </span></div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div
                                                                                    class="cell-content cell-icon-text">
                                                                                    <i
                                                                                        class="cell-icon material-icons-outlined">
                                                                                        location_on </i><span
                                                                                        class="cell-text">77, Blue Moon,
                                                                                        Ahmedabad</span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-content">
                                                                                    <div class="badge-solid col-indigo">
                                                                                        Under Treatment </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="datatable-body-cell sort-active"
                                                                            role="cell" tabindex="-1">
                                                                            <div class="datatable-body-cell-label">
                                                                                <div class="cell-actions"><button
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
                                                    <div class="page-count"><span> 0 selected / </span> 16 total </div>
                                                    <div class="datatable-pager">
                                                        <ul class="pager">
                                                            <li class="disabled"><a aria-label="go to first page"
                                                                    role="button"><i
                                                                        class="datatable-icon-prev"></i></a></li>
                                                            <li class="disabled"><a aria-label="go to previous page"
                                                                    role="button"><i
                                                                        class="datatable-icon-left"></i></a></li>
                                                            <li aria-label="page 1" class="pages active" role="button">
                                                                <a> 1 </a>
                                                            </li>
                                                            <li aria-label="page 2" class="pages" role="button"><a> 2
                                                                </a></li>
                                                            <li><a aria-label="go to next page" role="button"><i
                                                                        class="datatable-icon-right"></i></a></li>
                                                            <li><a aria-label="go to last page" role="button"><i
                                                                        class="datatable-icon-skip"></i></a></li>
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
        </section>
    </div>
</section>