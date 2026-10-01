<section class="dior-tab-panel" id="tab-doc-accounts-invoice" style="display: none; background: #f8fafc; padding: 20px; position: relative; overflow: hidden;">
    
    <!-- Scoped CSS to prevent watermark & match design -->
    <style>
    #tab-doc-accounts-invoice .dior-page-watermark,
    #tab-doc-accounts-invoice .page-watermark,
    #tab-doc-accounts-invoice .dior-big-title,
    .dior-doctor-wrap #tab-doc-accounts-invoice .dior-page-watermark,
    .dior-doctor-wrap #tab-doc-accounts-invoice .page-watermark,
    .dior-doctor-wrap #tab-doc-accounts-invoice .dior-big-title {
        display: none !important;
        opacity: 0 !important;
        visibility: hidden !important;
    }

    .dior-invoice-card {
        background: #ffffff;
        border-radius: 16px;
        box-shadow: 0 12px 40px rgba(15,23,42,.06);
        border: 1px solid #eef2f6;
        padding: 40px 50px;
        position: relative;
    }
    
    .dior-invoice-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 2px dashed #e2e8f0;
        padding-bottom: 24px;
        margin-bottom: 30px;
    }
    
    .dior-invoice-header h2 {
        font-size: 20px;
        font-weight: 800;
        color: #0f172a;
        margin: 0;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    
    .dior-status-paid {
        background: #ecfdf5;
        color: #059669;
        padding: 8px 18px;
        border-radius: 30px;
        font-size: 13px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 1px;
        border: 1px solid #a7f3d0;
        box-shadow: 0 4px 12px rgba(5,150,105,0.15);
    }
    
    .dior-invoice-info {
        display: flex;
        justify-content: space-between;
        margin-bottom: 40px;
        flex-wrap: wrap;
        gap: 20px;
    }
    
    .dior-company-info img {
        height: 42px;
        margin-bottom: 16px;
    }
    
    .dior-company-info h3 {
        font-size: 18px;
        font-weight: 800;
        color: #1e293b;
        margin: 0 0 10px 0;
    }
    
    .dior-address-text {
        font-size: 14px;
        color: #64748b;
        line-height: 1.8;
        margin: 0;
    }
    
    .dior-client-info {
        text-align: right;
    }
    
    .dior-client-info .bill-to {
        font-size: 13px;
        font-weight: 800;
        color: #94a3b8;
        text-transform: uppercase;
        margin: 0 0 8px 0;
        letter-spacing: 1px;
    }
    
    .dior-client-info h4 {
        font-size: 20px;
        font-weight: 800;
        color: #0f172a;
        margin: 0 0 10px 0;
    }
    
    .dior-invoice-meta {
        margin-top: 24px;
        font-size: 14px;
    }
    
    .dior-invoice-meta .meta-item {
        margin-bottom: 8px;
    }
    
    .dior-invoice-meta .meta-label {
        color: #64748b;
        margin-right: 8px;
        display: inline-block;
        width: 100px;
        text-align: right;
    }
    
    .dior-invoice-meta .meta-value {
        color: #1e293b;
        font-weight: 700;
    }
    
    .dior-invoice-table-section .section-title {
        font-size: 18px;
        font-weight: 800;
        color: #1e293b;
        margin-bottom: 20px;
    }
    
    .dior-invoice-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 40px;
    }
    
    .dior-invoice-table th,
    .dior-invoice-table td {
        border-left: none !important;
        border-right: none !important;
        border-top: none !important;
    }
    
    .dior-invoice-table th {
        background: #f8fafc !important;
        color: #475569;
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
        padding: 16px;
        text-align: left;
        border-bottom: 2px solid #e2e8f0 !important;
        letter-spacing: 1px;
    }
    
    .dior-invoice-table td {
        padding: 18px 16px;
        font-size: 14px;
        color: #475569;
        border-bottom: 1px solid #f1f5f9 !important;
        vertical-align: middle;
    }
    
    .dior-invoice-table th.text-end,
    .dior-invoice-table td.text-end { text-align: right; }
    
    .dior-invoice-table th.text-center,
    .dior-invoice-table td.text-center { text-align: center; }
    
    .dior-invoice-table tfoot td {
        padding: 24px 16px;
        font-size: 16px;
        border-bottom: none !important;
    }
    
    .dior-invoice-summary-section {
        display: flex;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 30px;
        margin-bottom: 40px;
    }
    
    .dior-payment-info {
        flex: 1;
        min-width: 300px;
        background: linear-gradient(145deg, #f8fafc, #f1f5f9);
        padding: 24px;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
    }
    
    .dior-payment-info .section-title {
        font-size: 16px;
        font-weight: 800;
        color: #1e293b;
        margin: 0 0 16px 0;
    }
    
    .dior-payment-details .detail-row {
        display: flex;
        margin-bottom: 12px;
        font-size: 14px;
    }
    
    .dior-payment-details .label {
        width: 140px;
        color: #64748b;
        font-weight: 500;
    }
    
    .dior-payment-details .value {
        color: #1e293b;
        font-weight: 700;
    }
    
    .dior-totals-box {
        flex: 0 0 350px;
    }
    
    .dior-totals-row {
        display: flex;
        justify-content: space-between;
        padding: 12px 0;
        font-size: 15px;
        color: #475569;
        border-bottom: 1px dashed #e2e8f0;
    }
    
    .dior-totals-row:last-child {
        border-bottom: none;
    }
    
    .dior-totals-row.total {
        border-top: 2px solid #e2e8f0;
        border-bottom: 2px solid #e2e8f0;
        padding: 20px 0;
        margin-top: 12px;
        font-size: 20px;
        font-weight: 900;
        color: #4f46e5;
    }
    
    .dior-invoice-actions {
        display: flex;
        justify-content: flex-end;
        gap: 16px;
        flex-wrap: wrap;
        border-top: 1px solid #f1f5f9;
        padding-top: 30px;
    }
    
    .dior-btn {
        padding: 12px 28px !important;
        border-radius: 8px !important;
        font-size: 14px !important;
        font-weight: 700 !important;
        border: none !important;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        line-height: normal !important;
        height: auto !important;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .dior-btn-primary { background: linear-gradient(135deg, #8b5cf6, #6366f1) !important; color: #ffffff !important; box-shadow: 0 4px 15px rgba(99,102,241,0.3) !important; }
    .dior-btn-info { background: linear-gradient(135deg, #0284c7, #0ea5e9) !important; color: #ffffff !important; box-shadow: 0 4px 15px rgba(14,165,233,0.3) !important; }
    .dior-btn-secondary { background: #64748b !important; color: #ffffff !important; box-shadow: 0 4px 12px rgba(100,116,139,0.2) !important; }
    
    .dior-btn:hover {
        transform: translateY(-2px);
        filter: brightness(1.1);
    }
    </style>

    <!-- Header Breadcrumb without .breadcrumb-main -->
    <div class="mb-4" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; position: relative; z-index: 5;">
        <div>
            <h4 class="mb-0" style="font-size: 20px; font-weight: 700; color: #1e293b;">Invoice</h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list" style="display: flex; gap: 8px; list-style: none; padding: 0; margin: 0; align-items: center; font-size: 12px;">
                <li><a href="javascript:void(0)"><i class="fa-solid fa-house" style="color: #2563eb;"></i></a></li>
                <li style="color: #cbd5e1;">/</li>
                <li><a href="javascript:void(0)" style="color: #64748b; text-decoration: none;">Accounts</a></li>
                <li style="color: #cbd5e1;">/</li>
                <li class="active"><span style="color: #1e293b; font-weight: 600;">Invoice</span></li>
            </ul>
        </div>
    </div>

    <!-- Main Card -->
    <div class="dior-invoice-card">
        
        <div id="invoice-pdf-content" style="background: #ffffff; padding-bottom: 20px;">
            <!-- Header -->
            <div class="dior-invoice-header">
                <h2>INVOICE #345766</h2>
                <span class="dior-status-paid">Paid</span>
            </div>
            
            <!-- Info Section -->
            <div class="dior-invoice-info">
                <div class="dior-company-info">
                    <img src="http://localhost/diormedical/wp-content/uploads/2026/08/logo.png" alt="MediDash Logo">
                    <h3>MediDash Hospital</h3>
                    <p class="dior-address-text">
                        D 103, MediDash Hospital<br>
                        Opp. Town Hall<br>
                        Sardar Patel Road<br>
                        Ahmedabad - 380015
                    </p>
                </div>
                <div class="dior-client-info">
                    <h3 class="bill-to">BILL TO</h3>
                    <h4>Jayesh Patel</h4>
                    <p class="dior-address-text">
                        207, Prem Sagar Appt.<br>
                        Near Income Tax Office<br>
                        Ashram Road<br>
                        Ahmedabad - 380057
                    </p>
                    <div class="dior-invoice-meta">
                        <div class="meta-item"><span class="meta-label">Invoice Date:</span> <span class="meta-value">14th July 2023</span></div>
                        <div class="meta-item"><span class="meta-label">Due Date:</span> <span class="meta-value">28th July 2023</span></div>
                    </div>
                </div>
            </div>
            
            <!-- Table Section -->
            <div class="dior-invoice-table-section">
                <div style="overflow-x: auto;">
                    <table class="dior-invoice-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Description</th>
                                <th class="text-center">Quantity</th>
                                <th class="text-center">Unit Price</th>
                                <th class="text-center">Charges</th>
                                <th class="text-center">Discount</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>1</td>
                                <td style="font-weight: 500; color: #1e293b;">Visiting Charges</td>
                                <td class="text-center">-</td>
                                <td class="text-center">-</td>
                                <td class="text-center">$100</td>
                                <td class="text-center">-</td>
                                <td class="text-end" style="font-weight: 600;">$100</td>
                            </tr>
                            <tr>
                                <td>2</td>
                                <td style="font-weight: 500; color: #1e293b;">Medicines</td>
                                <td class="text-center">10</td>
                                <td class="text-center">$15</td>
                                <td class="text-center">$150</td>
                                <td class="text-center">5%</td>
                                <td class="text-end" style="font-weight: 600;">$1000</td>
                            </tr>
                            <tr>
                                <td>3</td>
                                <td style="font-weight: 500; color: #1e293b;">X-ray Reports</td>
                                <td class="text-center">4</td>
                                <td class="text-center">$600</td>
                                <td class="text-center">$70</td>
                                <td class="text-center">5%</td>
                                <td class="text-end" style="font-weight: 600;">$1200</td>
                            </tr>
                            <tr>
                                <td>4</td>
                                <td style="font-weight: 500; color: #1e293b;">MRI</td>
                                <td class="text-center">2</td>
                                <td class="text-center">$245</td>
                                <td class="text-center">$125</td>
                                <td class="text-center">10%</td>
                                <td class="text-end" style="font-weight: 600;">$480</td>
                            </tr>
                            <tr>
                                <td>5</td>
                                <td style="font-weight: 500; color: #1e293b;">Other Charges</td>
                                <td class="text-center">-</td>
                                <td class="text-center">-</td>
                                <td class="text-center">-</td>
                                <td class="text-center">-</td>
                                <td class="text-end" style="font-weight: 600;">$300</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="6" class="text-end"><strong style="color: #1e293b;">Subtotal</strong></td>
                                <td class="text-end"><strong style="color: #1e293b;">$2600</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            
            <!-- Summary & Payment -->
            <div class="dior-invoice-summary-section">
                <div class="dior-payment-info">
                    <h3 class="section-title">Payment Information</h3>
                    <div class="dior-payment-details">
                        <div class="detail-row">
                            <span class="label">Payment Method:</span>
                            <span class="value">Credit Card</span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Payment Terms:</span>
                            <span class="value">Due on Receipt</span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Note:</span>
                            <span class="value">Thank you for your business!</span>
                        </div>
                    </div>
                </div>
                
                <div class="dior-totals-box">
                    <div class="dior-totals-row">
                        <span>Subtotal:</span>
                        <span style="font-weight: 600; color: #1e293b;">$2,600.00</span>
                    </div>
                    <div class="dior-totals-row">
                        <span>Discount:</span>
                        <span style="font-weight: 600; color: #ef4444;">-$100.00</span>
                    </div>
                    <div class="dior-totals-row">
                        <span>Tax (10%):</span>
                        <span style="font-weight: 600; color: #1e293b;">$160.00</span>
                    </div>
                    <div class="dior-totals-row total">
                        <span>Total:</span>
                        <span>$2,760.00</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Actions -->
        <div class="dior-invoice-actions">
            <button class="dior-btn dior-btn-primary" onclick="window.print()">Print</button>
            <button class="dior-btn dior-btn-info" id="download-invoice-pdf">Download PDF</button>
            <button class="dior-btn dior-btn-secondary">Email Invoice</button>
        </div>
        
    </div>

</section>

<!-- html2pdf.js library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const downloadBtn = document.getElementById('download-invoice-pdf');
    if(downloadBtn) {
        downloadBtn.addEventListener('click', function() {
            const invoiceElement = document.getElementById('invoice-pdf-content');
            
            // Generate PDF
            const opt = {
                margin:       [10, 10, 10, 10], // top, left, bottom, right
                filename:     'Invoice_345766.pdf',
                image:        { type: 'jpeg', quality: 1.0 },
                html2canvas:  { scale: 2, useCORS: true },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            
            html2pdf().set(opt).from(invoiceElement).save();
        });
    }
});
</script>
