<section class="dior-tab-panel" id="tab-doc-accounts-add-bill" style="display: none;">
    <!-- Breadcrumb Header -->
    <div class="mb-4" style="display: flex; justify-content: space-between; align-items: center; padding: 0 5px;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size: 20px; font-weight: 700;">Add Bill</h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list">
                <li><a href="#"><i class="fas fa-house"></i></a></li>
                <li>/</li>
                <li><a href="#">Accounts</a></li>
                <li>/</li>
                <li class="active"><span>Add Bill</span></li>
            </ul>
        </div>
    </div>

    <!-- Subtab Navigation Bar -->
    <div class="dior-subtab-nav-bar px-1 mb-4">
        <button type="button" class="dior-subtab-pill btn" data-doc-tab="doc-accounts-bill-list" onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-accounts-bill-list');">
            <i class="fas fa-receipt"></i> Bill List
        </button>
        <button type="button" class="dior-subtab-pill btn active" data-doc-tab="doc-accounts-add-bill" onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-accounts-add-bill');">
            <i class="fas fa-file-invoice-dollar"></i> Add Bill
        </button>
        <button type="button" class="dior-subtab-pill btn" data-doc-tab="doc-accounts-income" onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-accounts-income');">
            <i class="fas fa-arrow-trend-up"></i> Income
        </button>
        <button type="button" class="dior-subtab-pill btn" data-doc-tab="doc-accounts-expenses" onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-accounts-expenses');">
            <i class="fas fa-arrow-trend-down"></i> Expenses
        </button>
        <button type="button" class="dior-subtab-pill btn" data-doc-tab="doc-accounts-income-report" onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-accounts-income-report');">
            <i class="fas fa-chart-pie"></i> Income Report
        </button>
        <button type="button" class="dior-subtab-pill btn" data-doc-tab="doc-accounts-invoice" onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-accounts-invoice');">
            <i class="fas fa-file-invoice"></i> Invoice
        </button>
    </div>

    <div class="view-appointment-card">
        <div class="va-header-container" style="border-bottom: 0;">
            <div class="va-title-box">
                <h2><i class="fas fa-file-invoice-dollar text-primary me-2"></i>Add Bill</h2>
            </div>
        </div>

        <div style="padding: 20px;">
            <form>
                <div class="row">
                    <div class="col-xl-4 col-lg-4 col-md-12 mb-3">
                        <label style="font-size:14px; font-weight: 600; color: #4b5563; margin-bottom:8px;">Bill Number <span class="text-danger">*</span></label>
                        <input type="text" placeholder="Bill Number" class="va-form-control form-control">
                    </div>
                    <div class="col-xl-4 col-lg-4 col-md-12 mb-3">
                        <label style="font-size:14px; font-weight: 600; color: #4b5563; margin-bottom:8px;">Patient Name <span class="text-danger">*</span></label>
                        <input type="text" placeholder="Patient Name" class="va-form-control form-control">
                    </div>
                    <div class="col-xl-4 col-lg-4 col-md-12 mb-3">
                        <label style="font-size:14px; font-weight: 600; color: #4b5563; margin-bottom:8px;">Patient ID <span class="text-danger">*</span></label>
                        <input type="text" placeholder="Patient ID" class="va-form-control form-control">
                    </div>
                </div>

                <div class="row">
                    <div class="col-xl-4 col-lg-4 col-md-12 mb-3">
                        <label style="font-size:14px; font-weight: 600; color: #4b5563; margin-bottom:8px;">Bill Date <span class="text-danger">*</span></label>
                        <input type="date" class="va-form-control form-control">
                    </div>
                    <div class="col-xl-4 col-lg-4 col-md-12 mb-3">
                        <label style="font-size:14px; font-weight: 600; color: #4b5563; margin-bottom:8px;">Due Date <span class="text-danger">*</span></label>
                        <input type="date" class="va-form-control form-control">
                    </div>
                    <div class="col-xl-4 col-lg-4 col-md-12 mb-3">
                        <label style="font-size:14px; font-weight: 600; color: #4b5563; margin-bottom:8px;">Service <span class="text-danger">*</span></label>
                        <input type="text" placeholder="Service" class="va-form-control form-control">
                    </div>
                </div>

                <div class="row">
                    <div class="col-xl-3 col-lg-3 col-md-12 mb-3">
                        <label style="font-size:14px; font-weight: 600; color: #4b5563; margin-bottom:8px;">Amount <span class="text-danger">*</span></label>
                        <input type="number" placeholder="Amount" class="va-form-control form-control">
                    </div>
                    <div class="col-xl-3 col-lg-3 col-md-12 mb-3">
                        <label style="font-size:14px; font-weight: 600; color: #4b5563; margin-bottom:8px;">Tax</label>
                        <input type="number" placeholder="Tax" class="va-form-control form-control">
                    </div>
                    <div class="col-xl-3 col-lg-3 col-md-12 mb-3">
                        <label style="font-size:14px; font-weight: 600; color: #4b5563; margin-bottom:8px;">Discount</label>
                        <input type="number" placeholder="Discount" class="va-form-control form-control">
                    </div>
                    <div class="col-xl-3 col-lg-3 col-md-12 mb-3">
                        <label style="font-size:14px; font-weight: 600; color: #4b5563; margin-bottom:8px;">Total Amount <span class="text-danger">*</span></label>
                        <input type="number" placeholder="Total" class="va-form-control form-control">
                    </div>
                </div>

                <div class="row">
                    <div class="col-xl-6 col-lg-6 col-md-12 mb-3">
                        <label style="font-size:14px; font-weight: 600; color: #4b5563; margin-bottom:8px;">Payment Method <span class="text-danger">*</span></label>
                        <select class="va-form-control form-select">
                            <option value="">Select Method</option>
                            <option value="Cash">Cash</option>
                            <option value="Card">Card</option>
                            <option value="Insurance">Insurance</option>
                            <option value="Online">Online</option>
                        </select>
                    </div>
                    <div class="col-xl-6 col-lg-6 col-md-12 mb-3">
                        <label style="font-size:14px; font-weight: 600; color: #4b5563; margin-bottom:8px;">Status <span class="text-danger">*</span></label>
                        <select class="va-form-control form-select">
                            <option value="Pending">Pending</option>
                            <option value="Paid">Paid</option>
                            <option value="Overdue">Overdue</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12 mb-4">
                        <label style="font-size:14px; font-weight: 600; color: #4b5563; margin-bottom:8px;">Notes</label>
                        <textarea rows="3" placeholder="Additional notes" class="va-form-control form-control"></textarea>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12" style="display: flex; gap: 10px;">
                        <button type="submit" class="va-btn-primary btn" style="padding: 10px 24px; font-weight: 600; border:none; border-radius: 8px; color: #fff;">Submit</button>
                        <button type="button" style="padding: 10px 24px; font-weight: 600; border:none; border-radius: 8px; background-color: #E2E8F0; color: #475569;">Cancel</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</section>
