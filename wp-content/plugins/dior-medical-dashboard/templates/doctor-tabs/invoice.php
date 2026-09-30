<section class="dior-tab-panel" id="tab-doc-accounts-invoice" style="display: none;">

    <!-- Breadcrumb Header -->
    <div class="mb-4" style="display: flex; justify-content: space-between; align-items: center; padding: 0 5px;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size: 20px; font-weight: 700;">Invoice</h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list">
                <li><a href="#"><i class="fa-solid fa-house"></i></a></li>
                <li>/</li>
                <li><a href="#">Accounts</a></li>
                <li>/</li>
                <li class="active"><span>Invoice</span></li>
            </ul>
        </div>
    </div>

    <div class="view-appointment-card">
        <div class="inv-wrapper">

            <!-- ── Invoice Top Bar ── -->
            <div class="inv-topbar">
                <h2 class="inv-number">INVOICE #345766</h2>
                <span class="inv-status-badge inv-paid">PAID</span>
            </div>

            <!-- ── From / Bill To ── -->
            <div class="inv-parties">
                <!-- Hospital Info (Left) -->
                <div class="inv-from">
                    <div class="inv-logo-wrap">
                        <img src="<?php echo esc_url(DIOR_PORTAL_URL . 'assets/images/logo.png'); ?>" alt="Hospital Logo" class="inv-logo-img">
                    </div>
                    <h3 class="inv-hospital-name">MediDash Hospital</h3>
                    <address class="inv-address">
                        D 103, MediDash Hospital<br>
                        Opp. Town Hall<br>
                        Sardar Patel Road<br>
                        Ahmedabad – 380015
                    </address>
                </div>

                <!-- Bill To (Right) -->
                <div class="inv-to">
                    <p class="inv-to-label">BILL TO</p>
                    <h3 class="inv-patient-name">Jayesh Patel</h3>
                    <address class="inv-address inv-address-right">
                        207, Prem Sagar Appt.<br>
                        Near Income Tax Office<br>
                        Ashram Road<br>
                        Ahmedabad – 380057
                    </address>
                    <div class="inv-dates">
                        <p><span class="inv-date-label">Invoice Date:</span> 14th July 2023</p>
                        <p><span class="inv-date-label">Due Date:</span> 28th July 2023</p>
                    </div>
                </div>
            </div>

            <!-- ── Invoice Items Table ── -->
            <h4 class="inv-section-title">Invoice Items</h4>
            <div class="va-table-wrapper inv-table-wrap">
                <table class="va-table inv-items-table">
                    <thead>
                        <tr>
                            <th style="width:50px; text-align:center;">#</th>
                            <th>DESCRIPTION</th>
                            <th style="text-align:center;">QUANTITY</th>
                            <th style="text-align:center;">UNIT PRICE</th>
                            <th style="text-align:center;">CHARGES</th>
                            <th style="text-align:center;">DISCOUNT</th>
                            <th style="text-align:right; padding-right:24px;">TOTAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="text-align:center;">1</td>
                            <td class="inv-item-desc">Visiting Charges</td>
                            <td style="text-align:center; color:#94A3B8;">–</td>
                            <td style="text-align:center; color:#94A3B8;">–</td>
                            <td style="text-align:center;">$100</td>
                            <td style="text-align:center; color:#94A3B8;">–</td>
                            <td style="text-align:right; padding-right:24px; font-weight:600;">$100</td>
                        </tr>
                        <tr>
                            <td style="text-align:center;">2</td>
                            <td class="inv-item-desc">Medicines</td>
                            <td style="text-align:center; color:#4F46E5; font-weight:600;">10</td>
                            <td style="text-align:center;">$15</td>
                            <td style="text-align:center;">$150</td>
                            <td style="text-align:center;">5%</td>
                            <td style="text-align:right; padding-right:24px; font-weight:600;">$1000</td>
                        </tr>
                        <tr>
                            <td style="text-align:center;">3</td>
                            <td class="inv-item-desc">X-ray Reports</td>
                            <td style="text-align:center;">4</td>
                            <td style="text-align:center;">$600</td>
                            <td style="text-align:center;">$70</td>
                            <td style="text-align:center;">5%</td>
                            <td style="text-align:right; padding-right:24px; font-weight:600;">$1200</td>
                        </tr>
                        <tr>
                            <td style="text-align:center;">4</td>
                            <td class="inv-item-desc">MRI</td>
                            <td style="text-align:center;">2</td>
                            <td style="text-align:center;">$245</td>
                            <td style="text-align:center; color:#D97706; font-weight:600;">$125</td>
                            <td style="text-align:center; color:#EF4444; font-weight:600;">10%</td>
                            <td style="text-align:right; padding-right:24px; font-weight:600;">$480</td>
                        </tr>
                        <tr>
                            <td style="text-align:center;">5</td>
                            <td class="inv-item-desc">Other Charges</td>
                            <td style="text-align:center; color:#94A3B8;">–</td>
                            <td style="text-align:center; color:#94A3B8;">–</td>
                            <td style="text-align:center; color:#94A3B8;">–</td>
                            <td style="text-align:center; color:#94A3B8;">–</td>
                            <td style="text-align:right; padding-right:24px; font-weight:600;">$300</td>
                        </tr>
                        <!-- Subtotal Row -->
                        <tr class="inv-subtotal-row">
                            <td colspan="6" style="text-align:right; padding-right:20px; font-weight:700; color:#1E293B; border-top: 1px solid #EEF2F6;">Subtotal</td>
                            <td style="text-align:right; padding-right:24px; font-weight:700; color:#4F46E5; border-top: 1px solid #EEF2F6;">$2600</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- ── Bottom: Payment Info + Summary ── -->
            <div class="inv-bottom">
                <!-- Payment Info (Left) -->
                <div class="inv-payment-info">
                    <h5 class="inv-payment-title">Payment Information</h5>
                    <table class="inv-payment-table">
                        <tr>
                            <td class="inv-pay-label">Payment Method:</td>
                            <td class="inv-pay-value">Credit Card</td>
                        </tr>
                        <tr>
                            <td class="inv-pay-label">Payment Terms:</td>
                            <td class="inv-pay-value">Due on Receipt</td>
                        </tr>
                        <tr>
                            <td class="inv-pay-label">Note:</td>
                            <td class="inv-pay-value" style="color:#4F46E5;">Thank you for your business!</td>
                        </tr>
                    </table>
                </div>

                <!-- Totals Summary (Right) -->
                <div class="inv-summary">
                    <table class="inv-summary-table">
                        <tr>
                            <td class="inv-sum-label">Subtotal:</td>
                            <td class="inv-sum-value">$2,600.00</td>
                        </tr>
                        <tr>
                            <td class="inv-sum-label">Discount:</td>
                            <td class="inv-sum-value" style="color:#EF4444;">-$100.00</td>
                        </tr>
                        <tr>
                            <td class="inv-sum-label">Tax (10%):</td>
                            <td class="inv-sum-value">$160.00</td>
                        </tr>
                        <tr class="inv-total-row">
                            <td class="inv-total-label">Total:</td>
                            <td class="inv-total-value">$2,760.00</td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- ── Action Buttons ── -->
            <div class="inv-actions">
                <button type="button" class="inv-btn inv-btn-print" onclick="window.print()">
                    <i class="fa-solid fa-print"></i> Print
                </button>
                <button type="button" class="inv-btn inv-btn-pdf">
                    <i class="fa-solid fa-file-pdf"></i> Download PDF
                </button>
                <button type="button" class="inv-btn inv-btn-email">
                    <i class="fa-solid fa-envelope"></i> Email Invoice
                </button>
            </div>

        </div><!-- /inv-wrapper -->
    </div><!-- /view-appointment-card -->

</section>
