<section class="dior-tab-panel dior-ic-44a70a0420" id="tab-payments">
    <!-- Breadcrumb Header -->
    <div class="mb-4" style="display:flex;justify-content:space-between;align-items:center;padding:0 24px;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size:20px;font-weight:700;">Billing</h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list">
                <li><a href="#"><i class="fa-solid fa-house" style="font-size:14px;color:#4F46E5;"></i></a></li>
                <li><span style="color:#94A3B8;">/</span></li>
                <li class="active"><span>Billing</span></li>
            </ul>
        </div>
    </div>

    <div class="master-table-wrapper">
        <div class="master-table-container">
            <div class="master-table-card">
                <div class="master-table-header">
                    <div class="header-content">
                        <div class="table-title-section">
                            <h2 class="table-title">Billing</h2>
                            <div class="title-accent"></div>
                        </div>
                        <div class="header-actions-group">
                            <div class="search-container">
                                <i class="fa-solid fa-magnifying-glass search-icon"></i>
                                <input type="text" id="dior-bill-search-input" placeholder="Search records..." aria-label="Search box" class="search-input" onkeyup="diorFilterBill()">
                            </div>
                            <div class="action-buttons">
                                <button type="button" aria-label="Export to CSV" class="action-btn action-btn-success" title="Export to CSV" onclick="diorDownloadBillCSV()">
                                    <i class="fa-solid fa-file-arrow-down"></i>
                                </button>
                                <button type="button" aria-label="Refresh data" class="action-btn action-btn-info" title="Refresh Page" onclick="window.location.reload()">
                                    <i class="fa-solid fa-rotate-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="table-content">
                    <table class="va-table" id="dior-bill-table">
                        <thead>
                            <tr>
                                <th>INVOICE NO <i class="fa-solid fa-sort"></i></th>
                                <th>DOCTOR NAME <i class="fa-solid fa-sort"></i></th>
                                <th>DATE <i class="fa-solid fa-sort"></i></th>
                                <th>AMOUNT <i class="fa-solid fa-sort"></i></th>
                                <th>TAX <i class="fa-solid fa-sort"></i></th>
                                <th>DISCOUNT <i class="fa-solid fa-sort"></i></th>
                                <th>TOTAL <i class="fa-solid fa-sort"></i></th>
                                <th>ACTIONS <i class="fa-solid fa-sort"></i></th>
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
                            ];
                            foreach ($billing_mock as $bm):
                            ?>
                            <tr onclick="diorOpenBillingModal('<?php echo esc_js($bm[0]); ?>', '<?php echo esc_js($bm[1]); ?>', '<?php echo esc_js($bm[2]); ?>', '<?php echo esc_js($bm[3]); ?>', '<?php echo esc_js($bm[4]); ?>', '<?php echo esc_js($bm[5]); ?>', '<?php echo esc_js($bm[6]); ?>')" class="dior-ic-8e7e606bc6">
                                <td><span class="cell-text"><?php echo esc_html($bm[0]); ?></span></td>
                                <td><span class="cell-text"><?php echo esc_html($bm[1]); ?></span></td>
                                <td>
                                    <div class="cell-content cell-icon-text">
                                        <i class="fa-regular fa-calendar cell-icon dior-ic-d53ea48df0"></i>
                                        <span class="cell-text"><?php echo esc_html($bm[2]); ?></span>
                                    </div>
                                </td>
                                <td><span class="cell-text"><?php echo esc_html($bm[3]); ?></span></td>
                                <td><span class="cell-text"><?php echo esc_html($bm[4]); ?></span></td>
                                <td><span class="cell-text"><?php echo esc_html($bm[5]); ?></span></td>
                                <td><span class="cell-text"><?php echo esc_html($bm[6]); ?></span></td>
                                <td>
                                    <div class="cell-content action-cell dior-ic-b568c61cf9">
                                        <button type="button" class="action-btn action-btn-success dior-ic-49777d7482" title="Download Bill">
                                            <i class="fa-solid fa-download dior-ic-d0ad57ff03"></i>
                                        </button>
                                        <button type="button" class="action-btn action-btn-info dior-ic-43535b6eba" title="View Bill">
                                            <i class="fa-solid fa-eye dior-ic-d0ad57ff03"></i>
                                        </button>
                                        <button type="button" class="action-btn dior-ic-1d598d91fe" title="Print Bill">
                                            <i class="fa-solid fa-print dior-ic-d0ad57ff03"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="master-table-footer">
                    <span class="page-count" id="dior-bill-page-count">0 selected / <?php echo count($billing_mock); ?> total</span>
                    
                    <div class="master-pagination" id="dior-bill-pagination">
                    </div>
                </div>
            </div>
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
                                <div class="field-value-wrapper"><span class="field-value dior-ic-3b2d8e2576" id="billModalInv"></span></div>
                            </div>
                        </div>
                        
                        <div class="col-lg-6 col-md-6 col-sm-12 col-xl-6 dior-ic-f8f25bc60b">
                            <div class="detail-field-card dior-ic-57d219ccba">
                                <div class="field-label-group dior-ic-b85bb26d7d">
                                   <span class="field-label">Doctor Name</span>
                                </div>
                                <div class="field-value-wrapper"><span class="field-value dior-ic-3b2d8e2576" id="billModalDocName2"></span></div>
                            </div>
                        </div>

                        <div class="col-lg-6 col-md-6 col-sm-12 col-xl-6 dior-ic-f8f25bc60b">
                            <div class="detail-field-card dior-ic-57d219ccba">
                                <div class="field-label-group dior-ic-b85bb26d7d">
                                    <span class="field-label">Date</span>
                                </div>
                                <div class="field-value-wrapper"><span class="field-value dior-ic-3b2d8e2576" id="billModalDate"></span></div>
                            </div>
                        </div>

                        <div class="col-lg-6 col-md-6 col-sm-12 col-xl-6 dior-ic-f8f25bc60b">
                            <div class="detail-field-card dior-ic-57d219ccba">
                                <div class="field-label-group dior-ic-b85bb26d7d">
                                    <span class="field-label">Amount</span>
                                </div>
                                <div class="field-value-wrapper"><span class="field-value dior-ic-3b2d8e2576" id="billModalAmount"></span></div>
                            </div>
                        </div>
                        
                        <div class="col-lg-6 col-md-6 col-sm-12 col-xl-6 dior-ic-f8f25bc60b">
                            <div class="detail-field-card dior-ic-57d219ccba">
                                <div class="field-label-group dior-ic-b85bb26d7d">
                                    <span class="field-label">Tax</span>
                                </div>
                                <div class="field-value-wrapper"><span class="field-value dior-ic-3b2d8e2576" id="billModalTax"></span></div>
                            </div>
                        </div>
                        
                        <div class="col-lg-6 col-md-6 col-sm-12 col-xl-6 dior-ic-f8f25bc60b">
                            <div class="detail-field-card dior-ic-57d219ccba">
                                <div class="field-label-group dior-ic-b85bb26d7d">
                                    <span class="field-label">Discount</span>
                                </div>
                                <div class="field-value-wrapper"><span class="field-value dior-ic-3b2d8e2576" id="billModalDisc"></span></div>
                            </div>
                        </div>
                        
                        <div class="col-lg-6 col-md-6 col-sm-12 col-xl-6 dior-ic-f8f25bc60b">
                            <div class="detail-field-card dior-ic-57d219ccba">
                                <div class="field-label-group dior-ic-b85bb26d7d">
                                    <span class="field-label">Total</span>
                                </div>
                                <div class="field-value-wrapper"><span class="field-value dior-ic-3b2d8e2576" id="billModalTotal"></span></div>
                            </div>
                        </div>

                        <div class="col-lg-6 col-md-6 col-sm-12 col-xl-6 dior-ic-f8f25bc60b">
                            <div class="detail-field-card dior-ic-57d219ccba">
                                <div class="field-label-group dior-ic-b85bb26d7d">
                                    <span class="field-label">Actions</span>
                                </div>
                                <div class="field-value-wrapper"><span class="field-value dior-ic-e304798bad">--</span></div>
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
