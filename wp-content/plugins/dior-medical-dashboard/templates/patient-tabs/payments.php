<section class="dior-tab-panel dior-ic-44a70a0420" id="tab-payments">
    <!-- Breadcrumb Header -->
    <div class="mb-4" style="display:flex;justify-content:space-between;align-items:center;padding:0 24px;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size:20px;font-weight:700;">Billing</h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list">
                <li><a href="#"><i class="fas fa-house" style="font-size:14px;color:#4F46E5;"></i></a></li>
                <li><span style="color:#94A3B8;">/</span></li>
                <li class="active"><span>Billing</span></li>
            </ul>
        </div>
    </div>

    <!-- Subtab Navigation Bar -->
    <div class="dior-subtab-nav-bar px-3 px-md-4 mb-4">
        <button type="button" class="dior-subtab-pill btn active" data-patient-tab="payments" onclick="if(typeof diorSwitchTab==='function') diorSwitchTab('payments');">
            <i class="fas fa-wallet"></i> Billing & Payments
        </button>
        <button type="button" class="dior-subtab-pill btn" data-patient-tab="insurance" onclick="if(typeof diorSwitchTab==='function') diorSwitchTab('insurance');">
            <i class="fas fa-shield-heart"></i> Insurance Claims
        </button>
    </div>

    <div class="docs-card">
        <div class="docs-header-container">
            <div class="docs-title-box">
                <h2 class="table-title">Billing</h2>
                <div class="docs-title-line"></div>
            </div>
            <div class="docs-actions-wrapper">
                <div class="input-group input-group-sm dior-bs-search-group" style="width: 240px;">

                    <input type="text" id="dior-bill-search-input" class="form-control border-start-0 ps-0"
                        placeholder="Search records..." aria-label="Search box" onkeyup="diorFilterBill()">
                </div>
                <div class="docs-actions-group">
                    <button type="button" aria-label="Delete selected items" class="docs-icon-btn docs-btn-danger btn"
                        title="Delete Selected" onclick="diorBulkDeleteStaticRows(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                    <button type="button" aria-label="Export to CSV" class="docs-icon-btn docs-btn-success btn"
                        title="Export to CSV" onclick="diorDownloadBillCSV()">
                        <i class="fas fa-file-arrow-down"></i>
                    </button>
                    <button type="button" aria-label="Refresh data" class="docs-icon-btn docs-btn-info btn" title="Refresh Page"
                        onclick="window.location.reload()">
                        <i class="fas fa-rotate-right"></i>
                    </button>
                </div>
            </div>
        </div>
        <div class="docs-table-wrapper">
            <table class="docs-table table table-hover" id="dior-bill-table">
                <thead>
                    <tr>
                        <th>Invoice No</th>
                        <th>Doctor</th>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Tax</th>
                        <th>Discount</th>
                        <th>Total</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="dior-bill-tbody">
                    <?php
                    $payments = is_array($payments ?? null) ? $payments : Dior_Patient_Portal_Data::get_patient_payments(get_current_user_id());
                    $payments = is_array($payments) ? $payments : [];
                    if (empty($payments)):
                    ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted small">
                                <i class="fas fa-receipt fs-4 text-secondary opacity-50 mb-2 d-block"></i>
                                No billing records found.
                            </td>
                        </tr>
                    <?php
                    else:
                        foreach ($payments as $p):
                            $p_inv = $p['invoice_id'] ?? ($p['id'] ?? '—');
                            $p_doc = $p['doctor_name'] ?? ($p['provider'] ?? 'Provider not recorded');
                            $p_date = !empty($p['date']) ? $p['date'] : (!empty($p['created_at']) ? wp_date('M j, Y', strtotime($p['created_at'])) : '—');
                            $p_amt = !empty($p['amount']) ? ((strpos($p['amount'], '$') === false ? '$' : '') . $p['amount']) : '—';
                            $p_tax = !empty($p['tax']) ? ((strpos($p['tax'], '$') === false && strpos($p['tax'], '%') === false ? '$' : '') . $p['tax']) : '—';
                            $p_disc = !empty($p['discount']) ? ((strpos($p['discount'], '$') === false && strpos($p['discount'], '%') === false ? '$' : '') . $p['discount']) : '—';
                            $p_tot = !empty($p['total']) ? ((strpos($p['total'], '$') === false ? '$' : '') . $p['total']) : $p_amt;
                        ?>
                        <tr onclick="diorOpenBillingModal('<?php echo esc_js($p_inv); ?>', '<?php echo esc_js($p_doc); ?>', '<?php echo esc_js($p_date); ?>', '<?php echo esc_js($p_amt); ?>', '<?php echo esc_js($p_tax); ?>', '<?php echo esc_js($p_disc); ?>', '<?php echo esc_js($p_tot); ?>')"
                            class="dior-ic-8e7e606bc6">
                            <td><span class="cell-text"><?php echo esc_html($p_inv); ?></span></td>
                            <td><span class="cell-text"><?php echo esc_html($p_doc); ?></span></td>
                            <td>
                                <div class="cell-content cell-icon-text">
                                    <i class="far fa-calendar cell-icon dior-ic-d53ea48df0"></i>
                                    <span class="cell-text"><?php echo esc_html($p_date); ?></span>
                                </div>
                            </td>
                            <td><span class="cell-text"><?php echo esc_html($p_amt); ?></span></td>
                            <td><span class="cell-text"><?php echo esc_html($p_tax); ?></span></td>
                            <td><span class="cell-text"><?php echo esc_html($p_disc); ?></span></td>
                            <td><span class="cell-text"><?php echo esc_html($p_tot); ?></span></td>
                            <td>
                                <div class="cell-content action-cell" style="gap:8px;">
                                    <button type="button" class="btn btn-sm btn-success" title="Download Bill" onclick="event.stopPropagation(); if(typeof diorDownloadInvoicePdf==='function') diorDownloadInvoicePdf('<?php echo esc_js($p_inv); ?>');">
                                        <i class="fas fa-download"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-info" title="View Bill" onclick="event.stopPropagation(); if(typeof diorViewInvoicePdf==='function') diorViewInvoicePdf('<?php echo esc_js($p_inv); ?>');">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-secondary" title="Print Bill" onclick="event.stopPropagation(); if(typeof diorPrintInvoicePdf==='function') diorPrintInvoicePdf('<?php echo esc_js($p_inv); ?>');">
                                        <i class="fas fa-print"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>


    <div class="dior-billing-modal-backdrop dior-ic-b546859a82" id="diorBillingModal">
        <div class="modal-content dior-ic-7903a9dded">
            <div class="modal-header details-modal-header dior-ic-feb0429751">
                <div class="header-left dior-ic-fbd29a13bb">
                    <div class="header-icon-wrapper dior-ic-01e9887431">
                        info
                    </div>
                    <div class="header-title-wrapper">
                        <h4 class="modal-title dior-ic-515659bf18">Billing Details</h4>
                        <span class="modal-subtitle dior-ic-55709c3d5d" id="billModalSubtitle"></span>
                    </div>
                </div>
                <div class="header-actions dior-ic-fbd29a13bb">
                    <button type="button" class="btn btn-light dior-ic-7a752f6c0e">
                        <span>Edit</span>
                    </button>
                    <button type="button" onclick="diorCloseBillingModal()" class="dior-ic-7a752f6c0e btn">
                        close
                    </button>
                </div>
            </div>
            <div class="modal-body details-modal-body dior-ic-602bae732d">
                <div class="details-hero-card dior-ic-49c0d4651a">
                    <div class="hero-avatar-wrapper">
                        <img alt="avatar" class="hero-avatar dior-ic-02045d41a0" id="billModalAvatar" src="">
                    </div>
                    <div class="hero-content">
                        <h3 class="hero-title dior-ic-6bc196bb21" id="billModalDocName"></h3>
                    </div>
                </div>
                <div class="details-grid-container">
                    <div class="row g-3 dior-ic-bd41a014a7">

                        <div class="col-lg-6 col-md-6 col-sm-12 col-xl-6 dior-ic-f8f25bc60b">
                            <div class="detail-field-card dior-ic-57d219ccba">
                                <div class="field-label-group dior-ic-b85bb26d7d">
                                    <span class="field-label">Invoice No</span>
                                </div>
                                <div class="field-value-wrapper"><span class="field-value dior-ic-3b2d8e2576"
                                        id="billModalInv"></span></div>
                            </div>
                        </div>

                        <div class="col-lg-6 col-md-6 col-sm-12 col-xl-6 dior-ic-f8f25bc60b">
                            <div class="detail-field-card dior-ic-57d219ccba">
                                <div class="field-label-group dior-ic-b85bb26d7d">
                                    <span class="field-label">Doctor Name</span>
                                </div>
                                <div class="field-value-wrapper"><span class="field-value dior-ic-3b2d8e2576"
                                        id="billModalDocName2"></span></div>
                            </div>
                        </div>

                        <div class="col-lg-6 col-md-6 col-sm-12 col-xl-6 dior-ic-f8f25bc60b">
                            <div class="detail-field-card dior-ic-57d219ccba">
                                <div class="field-label-group dior-ic-b85bb26d7d">
                                    <span class="field-label">Date</span>
                                </div>
                                <div class="field-value-wrapper"><span class="field-value dior-ic-3b2d8e2576"
                                        id="billModalDate"></span></div>
                            </div>
                        </div>

                        <div class="col-lg-6 col-md-6 col-sm-12 col-xl-6 dior-ic-f8f25bc60b">
                            <div class="detail-field-card dior-ic-57d219ccba">
                                <div class="field-label-group dior-ic-b85bb26d7d">
                                    <span class="field-label">Amount</span>
                                </div>
                                <div class="field-value-wrapper"><span class="field-value dior-ic-3b2d8e2576"
                                        id="billModalAmount"></span></div>
                            </div>
                        </div>

                        <div class="col-lg-6 col-md-6 col-sm-12 col-xl-6 dior-ic-f8f25bc60b">
                            <div class="detail-field-card dior-ic-57d219ccba">
                                <div class="field-label-group dior-ic-b85bb26d7d">
                                    <span class="field-label">Tax</span>
                                </div>
                                <div class="field-value-wrapper"><span class="field-value dior-ic-3b2d8e2576"
                                        id="billModalTax"></span></div>
                            </div>
                        </div>

                        <div class="col-lg-6 col-md-6 col-sm-12 col-xl-6 dior-ic-f8f25bc60b">
                            <div class="detail-field-card dior-ic-57d219ccba">
                                <div class="field-label-group dior-ic-b85bb26d7d">
                                    <span class="field-label">Discount</span>
                                </div>
                                <div class="field-value-wrapper"><span class="field-value dior-ic-3b2d8e2576"
                                        id="billModalDisc"></span></div>
                            </div>
                        </div>

                        <div class="col-lg-6 col-md-6 col-sm-12 col-xl-6 dior-ic-f8f25bc60b">
                            <div class="detail-field-card dior-ic-57d219ccba">
                                <div class="field-label-group dior-ic-b85bb26d7d">
                                    <span class="field-label">Total</span>
                                </div>
                                <div class="field-value-wrapper"><span class="field-value dior-ic-3b2d8e2576"
                                        id="billModalTotal"></span></div>
                            </div>
                        </div>

                        <div class="col-lg-6 col-md-6 col-sm-12 col-xl-6 dior-ic-f8f25bc60b">
                            <div class="detail-field-card dior-ic-57d219ccba">
                                <div class="field-label-group dior-ic-b85bb26d7d">
                                    <span class="field-label">Actions</span>
                                </div>
                                <div class="field-value-wrapper"><span class="field-value dior-ic-e304798bad">--</span>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
            <div class="modal-footer details-modal-footer dior-ic-a11f69ed01">
                <button type="button" class="btn btn-light dior-ic-ce5cec6499" onclick="diorCloseBillingModal()">
                    Close
                </button>
            </div>
        </div>
    </div>
</section>