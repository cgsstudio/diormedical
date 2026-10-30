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

    <!-- Subtab Navigation Bar -->
    <div class="dior-subtab-nav-bar px-1 mb-4">
        <button type="button" class="dior-subtab-pill btn" data-doc-tab="doc-accounts-bill-list" onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-accounts-bill-list');">
            <i class="fas fa-receipt"></i> Bill List
        </button>
        <button type="button" class="dior-subtab-pill btn" data-doc-tab="doc-accounts-add-bill" onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-accounts-add-bill');">
            <i class="fas fa-file-invoice-dollar"></i> Add Bill
        </button>
        <button type="button" class="dior-subtab-pill btn" data-doc-tab="doc-accounts-income" onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-accounts-income');">
            <i class="fas fa-arrow-trend-up"></i> Income
        </button>
        <button type="button" class="dior-subtab-pill btn" data-doc-tab="doc-accounts-expenses" onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-accounts-expenses');">
            <i class="fas fa-arrow-trend-down"></i> Expenses
        </button>
        <button type="button" class="dior-subtab-pill btn active" data-doc-tab="doc-accounts-income-report" onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-accounts-income-report');">
            <i class="fas fa-chart-pie"></i> Income Report
        </button>
        <button type="button" class="dior-subtab-pill btn" data-doc-tab="doc-accounts-invoice" onclick="if(typeof diorDocSwitchTab==='function') diorDocSwitchTab('doc-accounts-invoice');">
            <i class="fas fa-file-invoice"></i> Invoice
        </button>
    </div>

    <!-- 4 KPI Stat Cards for Income Highlights (100% Native Bootstrap 5) -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-sm-6 col-12">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100 bg-white d-flex flex-column justify-content-between">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <span class="text-secondary small fw-semibold text-uppercase" style="letter-spacing: 0.5px; font-size: 11px;">Total Annual Revenue</span>
                        <div class="fs-2 fw-bold text-dark mt-1">$341,750</div>
                    </div>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-3 p-2.5">
                        <i class="fas fa-wallet fs-5"></i>
                    </span>
                </div>
                <div class="mt-2 text-secondary small">
                    <span class="text-success fw-semibold"><i class="fas fa-arrow-trend-up me-1"></i>+16.8%</span> vs last fiscal year
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6 col-12">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100 bg-white d-flex flex-column justify-content-between">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <span class="text-secondary small fw-semibold text-uppercase" style="letter-spacing: 0.5px; font-size: 11px;">Monthly Average</span>
                        <div class="fs-2 fw-bold text-dark mt-1">$28,479</div>
                    </div>
                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-3 p-2.5">
                        <i class="fas fa-chart-line fs-5"></i>
                    </span>
                </div>
                <div class="mt-2 text-secondary small">
                    <span class="text-primary fw-semibold"><i class="fas fa-calendar-check me-1"></i>12 Months</span> steady inflow
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6 col-12">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100 bg-white d-flex flex-column justify-content-between">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <span class="text-secondary small fw-semibold text-uppercase" style="letter-spacing: 0.5px; font-size: 11px;">Top Department</span>
                        <div class="fs-2 fw-bold text-dark mt-1">Cardiology</div>
                    </div>
                    <span class="badge bg-info-subtle text-info border border-info-subtle rounded-3 p-2.5">
                        <i class="fas fa-heart-pulse fs-5"></i>
                    </span>
                </div>
                <div class="mt-2 text-secondary small">
                    <span class="text-info fw-semibold">$91,950</span> total collection
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6 col-12">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100 bg-white d-flex flex-column justify-content-between">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <span class="text-secondary small fw-semibold text-uppercase" style="letter-spacing: 0.5px; font-size: 11px;">Average Growth</span>
                        <div class="fs-2 fw-bold text-success mt-1">+18.4%</div>
                    </div>
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-3 p-2.5">
                        <i class="fas fa-arrow-trend-up fs-5"></i>
                    </span>
                </div>
                <div class="mt-2 text-secondary small">
                    <span class="text-success fw-semibold"><i class="fas fa-circle-check me-1"></i>High Efficacy</span> clinical billing
                </div>
            </div>
        </div>
    </div>

    <!-- Income Chart Card (Interactive ApexCharts) -->
    <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4 bg-white mb-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3 border-bottom pb-3">
            <div>
                <h5 class="fw-bold text-dark mb-1 fs-5 d-flex align-items-center gap-2">
                    <i class="fas fa-chart-line text-primary"></i> Income &amp; Revenue Trends
                </h5>
                <p class="text-secondary small mb-0">Track clinical collections, departmental distributions, and periodic growth rates.</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="text-secondary small fw-semibold d-none d-sm-inline">Period:</span>
                <select id="dior-income-chart-filter" class="form-select form-select-sm rounded-3 py-1.5 fw-semibold" style="width: auto;">
                    <option value="Monthly" selected>Monthly (Jan - Dec)</option>
                    <option value="Daily">Daily (Last 14 Days)</option>
                    <option value="Yearly">Yearly (5 Years)</option>
                </select>
                <button type="button" class="btn btn-outline-secondary btn-sm rounded-3 px-2.5 shadow-none" id="dior-income-chart-refresh" title="Reset Chart Zoom">
                    <i class="fas fa-rotate-right"></i>
                </button>
            </div>
        </div>
        <div class="p-0">
            <div id="dior-income-report-apex-chart" style="min-height: 360px; width: 100%;"></div>
        </div>
    </div>

    <!-- Income Table Card -->
    <div class="view-appointment-card">
        <div class="va-header-container">
            <div class="va-title-box">
                <h2><i class="fas fa-table-list text-primary me-2"></i>Departmental Income Breakdown</h2>
                <div class="va-title-line"></div>
            </div>
            <div class="va-actions-wrapper">
                <span class="text-secondary small fw-semibold d-none d-sm-inline">Fiscal Year:</span>
                <select id="dior-income-table-year" class="form-select form-select-sm rounded-3 py-1.5 fw-semibold" style="width: auto;">
                    <option value="2024" selected>2024</option>
                    <option value="2023">2023</option>
                    <option value="2022">2022</option>
                    <option value="2021">2021</option>
                </select>
                <div class="va-actions-group">
                    <button type="button" class="va-icon-btn va-btn-info btn" title="Refresh" onclick="window.location.reload()"><i class="fas fa-rotate-right"></i></button>
                </div>
            </div>
        </div>

        <div class="va-table-wrapper">
            <table class="va-table table table-hover" id="dior-income-report-table">
                <thead>
                    <tr>
                        <th class="ps-3">DEPARTMENT <i class="fas fa-sort"></i></th>
                        <th style="text-align: right;">JAN</th>
                        <th style="text-align: right;">FEB</th>
                        <th style="text-align: right;">MAR</th>
                        <th style="text-align: right;">APR</th>
                        <th style="text-align: right;">MAY</th>
                        <th style="text-align: right;">JUN</th>
                        <th style="text-align: right;">JUL</th>
                        <th style="text-align: right;">AUG</th>
                        <th style="text-align: right;">SEP</th>
                        <th style="text-align: right;">OCT</th>
                        <th style="text-align: right;">NOV</th>
                        <th style="text-align: right;">DEC</th>
                        <th style="text-align: right;" class="pe-3">YEARLY TOTAL</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="ps-3 fw-bold text-dark"><i class="fas fa-brain text-primary me-2"></i>Anatomy</td>
                        <td class="text-end">$2,980.00</td>
                        <td class="text-end">$3,480.00</td>
                        <td class="text-end">$1,980.00</td>
                        <td class="text-end">$2,080.00</td>
                        <td class="text-end">$2,480.00</td>
                        <td class="text-end">$7,680.00</td>
                        <td class="text-end">$8,480.00</td>
                        <td class="text-end">$6,680.00</td>
                        <td class="text-end">$1,080.00</td>
                        <td class="text-end">$3,280.00</td>
                        <td class="text-end">$2,080.00</td>
                        <td class="text-end">$1,680.00</td>
                        <td class="text-end pe-3 fw-bold text-dark">$43,960.00</td>
                    </tr>
                    <tr>
                        <td class="ps-3 fw-bold text-dark"><i class="fas fa-heart-pulse text-danger me-2"></i>Cardiology</td>
                        <td class="text-end">$5,230.00</td>
                        <td class="text-end">$6,150.00</td>
                        <td class="text-end">$7,340.00</td>
                        <td class="text-end">$6,890.00</td>
                        <td class="text-end">$8,120.00</td>
                        <td class="text-end">$7,560.00</td>
                        <td class="text-end">$9,240.00</td>
                        <td class="text-end">$8,450.00</td>
                        <td class="text-end">$7,890.00</td>
                        <td class="text-end">$8,760.00</td>
                        <td class="text-end">$7,890.00</td>
                        <td class="text-end">$8,430.00</td>
                        <td class="text-end pe-3 fw-bold text-dark">$91,950.00</td>
                    </tr>
                    <tr>
                        <td class="ps-3 fw-bold text-dark"><i class="fas fa-dna text-info me-2"></i>Neurology</td>
                        <td class="text-end">$4,560.00</td>
                        <td class="text-end">$5,230.00</td>
                        <td class="text-end">$6,780.00</td>
                        <td class="text-end">$5,890.00</td>
                        <td class="text-end">$7,120.00</td>
                        <td class="text-end">$6,780.00</td>
                        <td class="text-end">$8,230.00</td>
                        <td class="text-end">$7,560.00</td>
                        <td class="text-end">$6,890.00</td>
                        <td class="text-end">$7,340.00</td>
                        <td class="text-end">$6,780.00</td>
                        <td class="text-end">$7,230.00</td>
                        <td class="text-end pe-3 fw-bold text-dark">$80,390.00</td>
                    </tr>
                    <tr>
                        <td class="ps-3 fw-bold text-dark"><i class="fas fa-bone text-warning me-2"></i>Orthopedics</td>
                        <td class="text-end">$3,890.00</td>
                        <td class="text-end">$4,560.00</td>
                        <td class="text-end">$5,230.00</td>
                        <td class="text-end">$4,890.00</td>
                        <td class="text-end">$6,120.00</td>
                        <td class="text-end">$5,780.00</td>
                        <td class="text-end">$7,230.00</td>
                        <td class="text-end">$6,560.00</td>
                        <td class="text-end">$5,890.00</td>
                        <td class="text-end">$6,340.00</td>
                        <td class="text-end">$5,780.00</td>
                        <td class="text-end">$6,230.00</td>
                        <td class="text-end pe-3 fw-bold text-dark">$68,500.00</td>
                    </tr>
                    <tr>
                        <td class="ps-3 fw-bold text-dark"><i class="fas fa-child text-success me-2"></i>Pediatrics</td>
                        <td class="text-end">$3,120.00</td>
                        <td class="text-end">$3,780.00</td>
                        <td class="text-end">$4,230.00</td>
                        <td class="text-end">$3,890.00</td>
                        <td class="text-end">$5,120.00</td>
                        <td class="text-end">$4,780.00</td>
                        <td class="text-end">$6,230.00</td>
                        <td class="text-end">$5,560.00</td>
                        <td class="text-end">$4,890.00</td>
                        <td class="text-end">$5,340.00</td>
                        <td class="text-end">$4,780.00</td>
                        <td class="text-end">$5,230.00</td>
                        <td class="text-end pe-3 fw-bold text-dark">$56,950.00</td>
                    </tr>
                </tbody>
                <tfoot class="table-light border-top">
                    <tr class="fw-bold">
                        <td class="ps-3 text-dark">Combined Clinic Revenue</td>
                        <td class="text-end text-primary">$19,780</td>
                        <td class="text-end text-primary">$23,200</td>
                        <td class="text-end text-primary">$25,560</td>
                        <td class="text-end text-primary">$23,540</td>
                        <td class="text-end text-primary">$29,020</td>
                        <td class="text-end text-primary">$32,580</td>
                        <td class="text-end text-primary">$39,410</td>
                        <td class="text-end text-primary">$34,810</td>
                        <td class="text-end text-primary">$26,540</td>
                        <td class="text-end text-primary">$31,060</td>
                        <td class="text-end text-primary">$27,320</td>
                        <td class="text-end text-primary">$28,930</td>
                        <td class="text-end pe-3 text-success fs-6">$341,750.00</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</section>

<script>
(function() {
    var incomeChartInstance = null;

    var incomeChartData = {
        Monthly: {
            categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            series: [
                { name: 'Cardiology', data: [5230, 6150, 7340, 6890, 8120, 7560, 9240, 8450, 7890, 8760, 7890, 8430] },
                { name: 'Neurology', data: [4560, 5230, 6780, 5890, 7120, 6780, 8230, 7560, 6890, 7340, 6780, 7230] },
                { name: 'Orthopedics', data: [3890, 4560, 5230, 4890, 6120, 5780, 7230, 6560, 5890, 6340, 5780, 6230] },
                { name: 'Pediatrics', data: [3120, 3780, 4230, 3890, 5120, 4780, 6230, 5560, 4890, 5340, 4780, 5230] },
                { name: 'Anatomy', data: [2980, 3480, 1980, 2080, 2480, 7680, 8480, 6680, 1080, 3280, 2080, 1680] }
            ]
        },
        Daily: {
            categories: ['Oct 6', 'Oct 7', 'Oct 8', 'Oct 9', 'Oct 10', 'Oct 11', 'Oct 12', 'Oct 13', 'Oct 14', 'Oct 15', 'Oct 16', 'Oct 17', 'Oct 18', 'Oct 19'],
            series: [
                { name: 'Cardiology', data: [850, 920, 1100, 780, 1250, 1400, 950, 820, 1150, 1300, 1450, 980, 1200, 1350] },
                { name: 'Neurology', data: [620, 710, 850, 900, 740, 890, 1020, 680, 790, 910, 840, 720, 930, 880] },
                { name: 'Orthopedics', data: [510, 580, 640, 720, 690, 810, 750, 600, 680, 740, 790, 630, 710, 770] },
                { name: 'Pediatrics', data: [420, 480, 510, 590, 620, 650, 710, 490, 550, 610, 640, 530, 590, 620] },
                { name: 'Anatomy', data: [210, 250, 300, 280, 310, 340, 400, 260, 290, 320, 350, 270, 310, 330] }
            ]
        },
        Yearly: {
            categories: ['2020', '2021', '2022', '2023', '2024'],
            series: [
                { name: 'Cardiology', data: [62000, 68400, 75200, 82900, 91950] },
                { name: 'Neurology', data: [53000, 59200, 64800, 71500, 80390] },
                { name: 'Orthopedics', data: [45000, 51000, 56400, 61900, 68500] },
                { name: 'Pediatrics', data: [38000, 42100, 46500, 51200, 56950] },
                { name: 'Anatomy', data: [31000, 34000, 37200, 40500, 43960] }
            ]
        }
    };

    function renderIncomeChart() {
        var el = document.getElementById('dior-income-report-apex-chart');
        var panel = document.getElementById('tab-doc-accounts-income-report');
        if (!el || !panel) return;

        if (typeof ApexCharts === 'undefined') {
            setTimeout(renderIncomeChart, 100);
            return;
        }

        if (window.getComputedStyle(panel).display === 'none') {
            return;
        }

        if (incomeChartInstance) {
            incomeChartInstance.destroy();
            incomeChartInstance = null;
        }

        var filterEl = document.getElementById('dior-income-chart-filter');
        var period = (filterEl ? filterEl.value : 'Monthly') || 'Monthly';
        var d = incomeChartData[period] || incomeChartData.Monthly;

        var options = {
            series: d.series,
            chart: {
                type: 'area',
                height: 360,
                fontFamily: 'inherit',
                toolbar: {
                    show: true,
                    tools: {
                        download: true,
                        selection: true,
                        zoom: true,
                        zoomin: true,
                        zoomout: true,
                        pan: true,
                        reset: true
                    }
                },
                animations: {
                    enabled: true,
                    easing: 'easeinout',
                    speed: 650
                }
            },
            colors: ['#4f46e5', '#06b6d4', '#10b981', '#f59e0b', '#ec4899'],
            dataLabels: { enabled: false },
            stroke: {
                curve: 'smooth',
                width: 2.5
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.45,
                    opacityTo: 0.05,
                    stops: [0, 90, 100]
                }
            },
            xaxis: {
                categories: d.categories,
                labels: {
                    style: { colors: '#64748b', fontSize: '12px', fontWeight: 500 }
                },
                axisBorder: { color: '#e2e8f0' },
                axisTicks: { color: '#e2e8f0' }
            },
            yaxis: {
                labels: {
                    formatter: function(val) {
                        return '$' + Number(val).toLocaleString();
                    },
                    style: { colors: '#64748b', fontSize: '12px' }
                }
            },
            tooltip: {
                shared: true,
                intersect: false,
                y: {
                    formatter: function(val) {
                        return '$' + Number(val).toLocaleString() + '.00';
                    }
                }
            },
            grid: {
                borderColor: '#f1f5f9',
                strokeDashArray: 4
            },
            legend: {
                position: 'top',
                horizontalAlign: 'right',
                fontSize: '13px',
                fontWeight: 600,
                labels: { colors: '#334155' },
                markers: { width: 10, height: 10, radius: 10 }
            }
        };

        incomeChartInstance = new ApexCharts(el, options);
        incomeChartInstance.render();
    }

    window.diorRenderIncomeChart = renderIncomeChart;

    document.addEventListener('change', function(e) {
        if (e.target && e.target.id === 'dior-income-chart-filter') {
            renderIncomeChart();
        }
    });

    document.addEventListener('click', function(e) {
        if (e.target && e.target.closest('#dior-income-chart-refresh')) {
            renderIncomeChart();
        }
    });

    var panel = document.getElementById('tab-doc-accounts-income-report');
    if (panel) {
        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.attributeName === 'style' && panel.style.display !== 'none') {
                    setTimeout(renderIncomeChart, 50);
                }
            });
        });
        observer.observe(panel, { attributes: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', renderIncomeChart);
    } else {
        renderIncomeChart();
    }
})();
</script>
