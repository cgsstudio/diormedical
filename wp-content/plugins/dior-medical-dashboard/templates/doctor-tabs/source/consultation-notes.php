<?php defined('ABSPATH') || exit; ?>
<section class="dior-tab-panel dior-source-panel" id="source-consultation-notes">
    <div class="dior-source-inner">
        <section class="main-content">
            <div>
                <div class="breadcrumb-main">
                    <div class="row">
                        <div class="col-6">
                            <div class="breadcrumb-title">
                                <h4 class="page-title d-flex align-items-center flex-wrap gap-2"><span>Consultations
                                        Notes</span></h4>
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
                                <li class="breadcrumb-item active">Consultations Notes</li>
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
                                                    <h2 class="table-title">Consultations Notes</h2>
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
                                                                                role="button" tabindex="0"> Consultation
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
                                                                                role="button" tabindex="0"> Chief
                                                                                Complaint </span><span
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
                                                                                role="button" tabindex="0"> Diagnosis
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
                                                                                                CONS001 </span></div>
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
                                                                                                Persistent headache and
                                                                                                dizziness </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Migraine with aura
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
                                                                                                class="action-icon-btn edit-btn"><i
                                                                                                    class="fas fa-pen"></i></button><button
                                                                                                aria-label="Delete record"
                                                                                                class="action-icon-btn delete-btn"><i
                                                                                                    class="far fa-trash-alt"></i></button>
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
                                                                                                CONS002 </span></div>
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
                                                                                                class="cell-text">11:30
                                                                                                AM</span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Chest pain and shortness
                                                                                                of breath </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Angina pectoris </span>
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
                                                                                                class="action-icon-btn edit-btn"><i
                                                                                                    class="fas fa-pen"></i></button><button
                                                                                                aria-label="Delete record"
                                                                                                class="action-icon-btn delete-btn"><i
                                                                                                    class="far fa-trash-alt"></i></button>
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
                                                                                                CONS003 </span></div>
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
                                                                                                Fever and body aches
                                                                                            </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Viral infection </span>
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
                                                                                                class="action-icon-btn edit-btn"><i
                                                                                                    class="fas fa-pen"></i></button><button
                                                                                                aria-label="Delete record"
                                                                                                class="action-icon-btn delete-btn"><i
                                                                                                    class="far fa-trash-alt"></i></button>
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
                                                                                                CONS004 </span></div>
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
                                                                                                Joint pain and stiffness
                                                                                            </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Osteoarthritis </span>
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
                                                                                                In Progress </div>
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
                                                                                                class="action-icon-btn edit-btn"><i
                                                                                                    class="fas fa-pen"></i></button><button
                                                                                                aria-label="Delete record"
                                                                                                class="action-icon-btn delete-btn"><i
                                                                                                    class="far fa-trash-alt"></i></button>
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
                                                                                                CONS005 </span></div>
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
                                                                                                class="cell-text">03:30
                                                                                                PM</span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Skin rash and itching
                                                                                            </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Allergic dermatitis
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
                                                                                                class="badge-solid col-orange">
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
                                                                                                class="action-icon-btn edit-btn"><i
                                                                                                    class="fas fa-pen"></i></button><button
                                                                                                aria-label="Delete record"
                                                                                                class="action-icon-btn delete-btn"><i
                                                                                                    class="far fa-trash-alt"></i></button>
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
                                                                                                CONS006 </span></div>
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
                                                                                                Abdominal pain and
                                                                                                nausea </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Gastroenteritis </span>
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
                                                                                                class="action-icon-btn edit-btn"><i
                                                                                                    class="fas fa-pen"></i></button><button
                                                                                                aria-label="Delete record"
                                                                                                class="action-icon-btn delete-btn"><i
                                                                                                    class="far fa-trash-alt"></i></button>
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
                                                                                                CONS007 </span></div>
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
                                                                                                class="cell-text">Nov
                                                                                                18, 2024</span></div>
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
                                                                                                Cough and sore throat
                                                                                            </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Upper respiratory
                                                                                                infection </span></div>
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
                                                                                                class="action-icon-btn edit-btn"><i
                                                                                                    class="fas fa-pen"></i></button><button
                                                                                                aria-label="Delete record"
                                                                                                class="action-icon-btn delete-btn"><i
                                                                                                    class="far fa-trash-alt"></i></button>
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
                                                                                                CONS008 </span></div>
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
                                                                                                17, 2024</span></div>
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
                                                                                                Back pain radiating to
                                                                                                leg </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Sciatica </span></div>
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
                                                                                                class="action-icon-btn edit-btn"><i
                                                                                                    class="fas fa-pen"></i></button><button
                                                                                                aria-label="Delete record"
                                                                                                class="action-icon-btn delete-btn"><i
                                                                                                    class="far fa-trash-alt"></i></button>
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
                                                                                                CONS009 </span></div>
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
                                                                                                class="cell-text">11:00
                                                                                                AM</span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Anxiety and insomnia
                                                                                            </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Generalized anxiety
                                                                                                disorder </span></div>
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
                                                                                                class="badge-solid col-orange">
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
                                                                                                class="action-icon-btn edit-btn"><i
                                                                                                    class="fas fa-pen"></i></button><button
                                                                                                aria-label="Delete record"
                                                                                                class="action-icon-btn delete-btn"><i
                                                                                                    class="far fa-trash-alt"></i></button>
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
                                                                                                CONS010 </span></div>
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
                                                                                        <div
                                                                                            class="cell-content cell-icon-text">
                                                                                            <i
                                                                                                class="cell-icon material-icons-outlined">
                                                                                                schedule </i><span
                                                                                                class="cell-text">02:30
                                                                                                PM</span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Fatigue and weight loss
                                                                                            </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Hypothyroidism </span>
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
                                                                                                class="action-icon-btn edit-btn"><i
                                                                                                    class="fas fa-pen"></i></button><button
                                                                                                aria-label="Delete record"
                                                                                                class="action-icon-btn delete-btn"><i
                                                                                                    class="far fa-trash-alt"></i></button>
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