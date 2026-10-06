<section class="dior-tab-panel" id="tab-doc-accounts-income-report" style="display: none;">
    <!-- Breadcrumb Header -->
    <div class="mb-4" style="display: flex; justify-content: space-between; align-items: center; padding: 0 5px;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size: 20px; font-weight: 700;">Income Report</h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list">
                <li><a href="#"><i class="fas fa-house"></i></a></li>
                <li>/</li>
                <li><a href="#">Accounts</a></li>
                <li>/</li>
                <li class="active"><span>Income Report</span></li>
            </ul>
        </div>
    </div>

    <!-- Income Chart Card -->
    <div class="view-appointment-card mb-4">
        <div class="va-header-container" style="border-bottom: 0;">
            <div class="va-title-box">
                <h2>Income Chart</h2>
            </div>
            <div class="va-actions-box">
                <select class="va-form-control" style="width: auto;">
                    <option value="Daily">Daily</option>
                    <option value="Monthly">Monthly</option>
                    <option value="Yearly">Yearly</option>
                </select>
            </div>
        </div>
        <div style="padding: 20px;">
            <!-- Dummy chart image or SVG representation -->
            <img src="<?php echo esc_url(DIOR_PORTAL_URL . 'assets/images/chart-dummy.png'); ?>" alt="Chart" style="width:100%; height: auto; max-height: 350px; object-fit: contain; background: #fafafa; border-radius: 8px;">
        </div>
    </div>

    <!-- Income Table Card -->
    <div class="view-appointment-card">
        <div class="va-header-container" style="border-bottom: 0;">
            <div class="va-title-box">
                <h2>Income Table</h2>
            </div>
            <div class="va-actions-box">
                <select class="va-form-control" style="width: auto;">
                    <option value="2024">2024</option>
                    <option value="2023">2023</option>
                    <option value="2022">2022</option>
                    <option value="2021">2021</option>
                </select>
            </div>
        </div>

        <div class="va-table-wrapper" style="border-top: 1px solid #EEF2F6;">
            <table class="va-table">
                <thead>
                    <tr>
                        <th>Department</th>
                        <th>Jan</th>
                        <th>Feb</th>
                        <th>Mar</th>
                        <th>Apr</th>
                        <th>May</th>
                        <th>Jun</th>
                        <th>Jul</th>
                        <th>Aug</th>
                        <th>Sep</th>
                        <th>Oct</th>
                        <th>Nov</th>
                        <th>Dec</th>
                        <th>Yearly</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Anatomy</td>
                        <td>$2,980.00</td>
                        <td>$3,480.00</td>
                        <td>$1,980.00</td>
                        <td>$2,080.00</td>
                        <td>$2,480.00</td>
                        <td>$7,680.00</td>
                        <td>$8,480.00</td>
                        <td>$6,680.00</td>
                        <td>$1,080.00</td>
                        <td>$3,280.00</td>
                        <td>$2,080.00</td>
                        <td>$1,680.00</td>
                        <td><strong>$43,960.00</strong></td>
                    </tr>
                    <tr>
                        <td>Cardiology</td>
                        <td>$5,230.00</td>
                        <td>$6,150.00</td>
                        <td>$7,340.00</td>
                        <td>$6,890.00</td>
                        <td>$8,120.00</td>
                        <td>$7,560.00</td>
                        <td>$9,240.00</td>
                        <td>$8,450.00</td>
                        <td>$7,890.00</td>
                        <td>$8,760.00</td>
                        <td>$7,890.00</td>
                        <td>$8,430.00</td>
                        <td><strong>$91,950.00</strong></td>
                    </tr>
                    <tr>
                        <td>Neurology</td>
                        <td>$4,560.00</td>
                        <td>$5,230.00</td>
                        <td>$6,780.00</td>
                        <td>$5,890.00</td>
                        <td>$7,120.00</td>
                        <td>$6,780.00</td>
                        <td>$8,230.00</td>
                        <td>$7,560.00</td>
                        <td>$6,890.00</td>
                        <td>$7,340.00</td>
                        <td>$6,780.00</td>
                        <td>$7,230.00</td>
                        <td><strong>$80,390.00</strong></td>
                    </tr>
                    <tr>
                        <td>Orthopedics</td>
                        <td>$3,890.00</td>
                        <td>$4,560.00</td>
                        <td>$5,230.00</td>
                        <td>$4,890.00</td>
                        <td>$6,120.00</td>
                        <td>$5,780.00</td>
                        <td>$7,230.00</td>
                        <td>$6,560.00</td>
                        <td>$5,890.00</td>
                        <td>$6,340.00</td>
                        <td>$5,780.00</td>
                        <td>$6,230.00</td>
                        <td><strong>$68,500.00</strong></td>
                    </tr>
                    <tr>
                        <td>Pediatrics</td>
                        <td>$3,120.00</td>
                        <td>$3,780.00</td>
                        <td>$4,230.00</td>
                        <td>$3,890.00</td>
                        <td>$5,120.00</td>
                        <td>$4,780.00</td>
                        <td>$6,230.00</td>
                        <td>$5,560.00</td>
                        <td>$4,890.00</td>
                        <td>$5,340.00</td>
                        <td>$4,780.00</td>
                        <td>$5,230.00</td>
                        <td><strong>$56,950.00</strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>
