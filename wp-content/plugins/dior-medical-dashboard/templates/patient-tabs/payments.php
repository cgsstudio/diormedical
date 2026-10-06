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

    <div class="docs-card">
        <div class="docs-header-container">
            <div class="docs-title-box">
                <h2 class="table-title">Billing</h2>
                <div class="docs-title-line"></div>
            </div>
            <div class="docs-actions-wrapper">
                <div class="docs-search-box">
                    <i class="fas fa-magnifying-glass"></i>
                    <input type="text" id="dior-bill-search-input" placeholder="Search records..."
                        aria-label="Search box" onkeyup="diorFilterBill()">
                </div>
                <div class="docs-actions-group">
                    <button type="button" aria-label="Export to CSV" class="docs-icon-btn docs-btn-success"
                        title="Export to CSV" onclick="diorDownloadBillCSV()">
                        <i class="fas fa-file-arrow-down"></i>
                    </button>
                    <button type="button" aria-label="Refresh data" class="docs-icon-btn docs-btn-info"
                        title="Refresh Page" onclick="window.location.reload()">
                        <i class="fas fa-rotate-right"></i>
                    </button>
                </div>
            </div>
        </div>
        <div class="docs-table-wrapper">
            <table class="docs-table" id="dior-bill-table">
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
                    $billing_mock = [
                        ['#A348', 'Dr.Jacob Ryan', 'Mar 4, 2016', '$40', '10%', '$5', '$39'],
                        ['#A645', 'Dr.Rajesh', 'Apr 11, 2016', '$25', '10%', '$5', '$22'],
                        ['#A873', 'Dr.Jay Soni', 'Apr 18, 2016', '$50', '10%', '$5', '$47'],
                        ['#A927', 'Dr.John Deo', 'May 22, 2016', '$45', '10%', '$5', '$42'],
                        ['#A228', 'Dr.Megha Trivedi', 'Jul 9, 2016', '$62', '10%', '$5', '$57'],
                        ['#A345', 'Dr.Sarah Smith', 'Jul 14, 2016', '$60', '10%', '$5', '$56'],
                        ['#A765', 'Dr.Jacob Ryan', 'Jun 22, 2016', '$40', '10%', '$5', '$39'],
                        ['#A125', 'Dr.Rajesh', 'Jun 23, 2016', '$30', '10%', '$5', '$29']
                        ,
                        ['#A905', 'Dr.Sarah Smith', 'Aug 2, 2016', '$72', '8%', '$4', '$74']
                        ,
                        ['#A981', 'Dr.James Chen', 'Aug 16, 2016', '$95', '8%', '$0', '$103']
                    ];
                    foreach ($billing_mock as $bm):
                        ?>
                        <tr onclick="diorOpenBillingModal('<?php echo esc_js($bm[0]); ?>', '<?php echo esc_js($bm[1]); ?>', '<?php echo esc_js($bm[2]); ?>', '<?php echo esc_js($bm[3]); ?>', '<?php echo esc_js($bm[4]); ?>', '<?php echo esc_js($bm[5]); ?>', '<?php echo esc_js($bm[6]); ?>')"
                            class="dior-ic-8e7e606bc6">
                            <td><span class="cell-text"><?php echo esc_html($bm[0]); ?></span></td>
                            <td><span class="cell-text"><?php echo esc_html($bm[1]); ?></span></td>
                            <td>
                                <div class="cell-content cell-icon-text">
                                    <i class="far fa-calendar cell-icon dior-ic-d53ea48df0"></i>
                                    <span class="cell-text"><?php echo esc_html($bm[2]); ?></span>
                                </div>
                            </td>
                            <td><span class="cell-text"><?php echo esc_html($bm[3]); ?></span></td>
                            <td><span class="cell-text"><?php echo esc_html($bm[4]); ?></span></td>
                            <td><span class="cell-text"><?php echo esc_html($bm[5]); ?></span></td>
                            <td><span class="cell-text"><?php echo esc_html($bm[6]); ?></span></td>
                            <td>
                                <div class="cell-content action-cell dior-ic-b568c61cf9">
                                    <button type="button" class="action-btn action-btn-success dior-ic-49777d7482"
                                        title="Download Bill">
                                        <i class="fas fa-download dior-ic-d0ad57ff03"></i>
                                    </button>
                                    <button type="button" class="action-btn action-btn-info dior-ic-43535b6eba"
                                        title="View Bill">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#3b82f6" class="bi bi-eye" viewBox="0 0 16 16" style="background:transparent;"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/><path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/></svg>
                                    </button>
                                    <button type="button" class="action-btn dior-ic-1d598d91fe" title="Print Bill">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-printer" viewBox="0 0 16 16" style="background:transparent;"><path d="M2.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1"/><path d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4zm1 5a2 2 0 0 0-2 2v1H2a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v-1a2 2 0 0 0-2-2zm7 2v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="docs-pagination-container">
            <div class="docs-showing-text" id="dior-bill-page-count">0 selected / <?php echo count($billing_mock); ?>
                total</div>
            <div id="dior-bill-pagination"></div>
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
                    <button type="button" onclick="diorCloseBillingModal()" class="dior-ic-7a752f6c0e">
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