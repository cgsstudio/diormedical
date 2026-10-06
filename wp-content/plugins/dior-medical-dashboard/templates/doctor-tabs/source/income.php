<?php defined('ABSPATH') || exit; ?>
<section class="dior-tab-panel dior-source-panel" id="source-income">
    <div class="dior-source-inner">
        <section class="main-content">
            <div>
                <div class="breadcrumb-main">
                    <div class="row">
                        <div class="col-6">
                            <div class="breadcrumb-title">
                                <h4 class="page-title d-flex align-items-center flex-wrap gap-2"><span>Income</span>
                                </h4>
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
                                <li class="breadcrumb-item"><a href="#">Accounts</a></li>
                                <li class="breadcrumb-item active">Income</li>
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
                                                    <h2 class="table-title">Income</h2>
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
                                                                                role="button" tabindex="0"> Income ID
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
                                                                                role="button" tabindex="0"> Service Type
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
                                                                                role="button" tabindex="0"> Billed
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
                                                                                role="button" tabindex="0"> Paid
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
                                                                                role="button" tabindex="0"> Method
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
                                                                                role="button" tabindex="0"> Invoice #
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
                                                                                                1001 </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                John Doe </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Consultation </span>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                150 </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                120 </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Credit Card </span>
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
                                                                                                class="cell-text">Nov 2,
                                                                                                2024</span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                INV-20241101-1001
                                                                                            </span></div>
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
                                                                                                1002 </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Alice Smith </span>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Surgery </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                1200 </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                1000 </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Cash </span></div>
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
                                                                                                26, 2024</span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                INV-20241025-1002
                                                                                            </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content">
                                                                                            <div
                                                                                                class="badge-solid col-orange">
                                                                                                Pending </div>
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
                                                                                                1003 </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                David Lee </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Consultation </span>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                180 </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                180 </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Insurance </span></div>
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
                                                                                                INV-20241030-1003
                                                                                            </span></div>
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
                                                                                                1004 </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Eve Williams </span>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                X-Ray </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                200 </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                200 </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Credit Card </span>
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
                                                                                                class="cell-text">Nov 2,
                                                                                                2024</span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                INV-20241102-1004
                                                                                            </span></div>
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
                                                                                                1005 </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                George Brown </span>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Laboratory Test </span>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                100 </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                90 </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Debit Card </span></div>
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
                                                                                                class="cell-text">Nov 3,
                                                                                                2024</span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                INV-20241103-1005
                                                                                            </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content">
                                                                                            <div
                                                                                                class="badge-solid col-orange">
                                                                                                Pending </div>
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
                                                                                                1006 </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Isabella Taylor </span>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Consultation </span>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                250 </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                250 </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Cash </span></div>
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
                                                                                                class="cell-text">Nov 5,
                                                                                                2024</span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                INV-20241105-1006
                                                                                            </span></div>
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
                                                                                                1007 </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                James Scott </span>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Surgery </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                3000 </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                1500 </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Bank Transfer </span>
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
                                                                                                23, 2024</span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                INV-20241022-1007
                                                                                            </span></div>
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
                                                                                                1008 </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Oliver Harris </span>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                CT Scan </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                600 </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                500 </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Credit Card </span>
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
                                                                                                class="cell-text">Nov 6,
                                                                                                2024</span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                INV-20241106-1008
                                                                                            </span></div>
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
                                                                                                1009 </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Sophia King </span>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Consultation </span>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                130 </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                130 </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Cash </span></div>
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
                                                                                                class="cell-text">Nov 8,
                                                                                                2024</span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                INV-20241108-1009
                                                                                            </span></div>
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
                                                                                                1010 </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Mason Clark </span>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                MRI </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                1000 </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                1000 </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                Debit Card </span></div>
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
                                                                                                class="cell-text">Nov 9,
                                                                                                2024</span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content"><span>
                                                                                                INV-20241109-1010
                                                                                            </span></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="datatable-body-cell sort-active"
                                                                                    role="cell" tabindex="-1">
                                                                                    <div
                                                                                        class="datatable-body-cell-label">
                                                                                        <div class="cell-content">
                                                                                            <div
                                                                                                class="badge-solid col-orange">
                                                                                                Pending </div>
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
                                                            <div class="page-count"><span> 0 selected / </span> 14 total
                                                            </div>
                                                            <div class="datatable-pager">
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
                                                                    <li aria-label="page 2" class="pages" role="button">
                                                                        <a> 2 </a></li>
                                                                    <li><a aria-label="go to next page" role="button"><i
                                                                                class="datatable-icon-right"></i></a>
                                                                    </li>
                                                                    <li><a aria-label="go to last page" role="button"><i
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