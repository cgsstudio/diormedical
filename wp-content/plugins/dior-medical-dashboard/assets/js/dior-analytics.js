/**
 * Dior Medical - Analytics Dashboard
 * Real-time analytics and charts
 */

(function ($) {
    'use strict';

    const DiorAnalytics = {
        ajaxUrl: diorAnalyticsData.ajaxUrl,
        nonce: diorAnalyticsData.nonce,
        charts: {},
        autoRefreshInterval: null,

        /**
         * Initialize analytics dashboard
         */
        init: function () {
            console.log('🎯 Dior Analytics Dashboard Initializing...');

            // Event listeners
            $('#dior-period-select').on('change', () => this.refreshAllData());
            $('#dior-refresh-btn').on('click', () => this.refreshAllData());

            // Initial data load
            this.refreshAllData();

            // Auto-refresh every 5 minutes
            this.autoRefreshInterval = setInterval(() => this.refreshAllData(), 300000);
        },

        /**
         * Refresh all analytics data
         */
        refreshAllData: function () {
            console.log('🔄 Refreshing all analytics data...');

            this.loadDashboardStats();
            this.loadRevenueChart();
            this.loadAppointmentsChart();
            this.loadPatientGrowthChart();
            this.loadTopServicesChart();
        },

        /**
         * Load dashboard statistics
         */
        loadDashboardStats: function () {
            const period = $('#dior-period-select').val() || '30';

            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dior_get_dashboard_stats',
                    nonce: this.nonce,
                    period: period,
                },
                success: (response) => {
                    if (response.success) {
                        const data = response.data;
                        console.log('📊 Stats loaded:', data);

                        // Update stat cards
                        this.updateStatCard('total-patients', data.total_patients);
                        this.updateStatCard('active-patients', data.active_patients);
                        this.updateStatCard('appointments-today', data.appointments_today);
                        this.updateStatCard('total-revenue', '$' + this.formatCurrency(data.total_revenue));
                    }
                },
                error: (error) => {
                    console.error('❌ Error loading stats:', error);
                }
            });
        },

        /**
         * Load revenue chart
         */
        loadRevenueChart: function () {
            const period = $('#dior-period-select').val() || '30';

            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dior_get_revenue_chart',
                    nonce: this.nonce,
                    period: period,
                },
                success: (response) => {
                    if (response.success) {
                        this.renderRevenueChart(response.data);
                    }
                },
                error: (error) => {
                    console.error('❌ Error loading revenue chart:', error);
                }
            });
        },

        /**
         * Load appointments chart
         */
        loadAppointmentsChart: function () {
            const period = $('#dior-period-select').val() || '30';

            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dior_get_appointments_chart',
                    nonce: this.nonce,
                    period: period,
                },
                success: (response) => {
                    if (response.success) {
                        this.renderAppointmentsChart(response.data);
                    }
                },
                error: (error) => {
                    console.error('❌ Error loading appointments chart:', error);
                }
            });
        },

        /**
         * Load patient growth chart
         */
        loadPatientGrowthChart: function () {
            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dior_get_patient_growth',
                    nonce: this.nonce,
                },
                success: (response) => {
                    if (response.success) {
                        this.renderPatientGrowthChart(response.data);
                    }
                },
                error: (error) => {
                    console.error('❌ Error loading patient growth chart:', error);
                }
            });
        },

        /**
         * Load top services chart
         */
        loadTopServicesChart: function () {
            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dior_get_top_services',
                    nonce: this.nonce,
                },
                success: (response) => {
                    if (response.success) {
                        this.renderServicesChart(response.data);
                    }
                },
                error: (error) => {
                    console.error('❌ Error loading services chart:', error);
                }
            });
        },

        /**
         * Render revenue chart
         */
        renderRevenueChart: function (data) {
            const ctx = document.getElementById('revenueChart');
            if (!ctx) return;

            // Destroy existing chart if it exists
            if (this.charts.revenue) {
                this.charts.revenue.destroy();
            }

            this.charts.revenue = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Revenue ($)',
                        data: data.data,
                        borderColor: '#0B1030',
                        backgroundColor: 'rgba(11, 16, 48, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#D9B96A',
                        pointBorderColor: '#0B1030',
                        pointRadius: 4,
                        pointHoverRadius: 6,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: true,
                            labels: {
                                font: { family: "'DM Sans', sans-serif" }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: (value) => '$' + this.formatCurrency(value)
                            }
                        }
                    }
                }
            });

            console.log('📈 Revenue chart rendered');
        },

        /**
         * Render appointments chart
         */
        renderAppointmentsChart: function (data) {
            const ctx = document.getElementById('appointmentsChart');
            if (!ctx) return;

            if (this.charts.appointments) {
                this.charts.appointments.destroy();
            }

            this.charts.appointments = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [
                        {
                            label: 'Scheduled',
                            data: data.scheduled,
                            backgroundColor: '#2563EB',
                        },
                        {
                            label: 'Completed',
                            data: data.completed,
                            backgroundColor: '#10B981',
                        },
                        {
                            label: 'Cancelled',
                            data: data.cancelled,
                            backgroundColor: '#E11D48',
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: true,
                            labels: {
                                font: { family: "'DM Sans', sans-serif" }
                            }
                        }
                    },
                    scales: {
                        x: {
                            stacked: false,
                        },
                        y: {
                            stacked: false,
                            beginAtZero: true,
                        }
                    }
                }
            });

            console.log('📅 Appointments chart rendered');
        },

        /**
         * Render patient growth chart
         */
        renderPatientGrowthChart: function (data) {
            const ctx = document.getElementById('patientGrowthChart');
            if (!ctx) return;

            if (this.charts.patientGrowth) {
                this.charts.patientGrowth.destroy();
            }

            this.charts.patientGrowth = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'New Patients',
                        data: data.new_patients,
                        borderColor: '#D9B96A',
                        backgroundColor: 'rgba(217, 185, 106, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#D9B96A',
                        pointBorderColor: '#0B1030',
                        pointRadius: 4,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: true,
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });

            console.log('👥 Patient growth chart rendered');
        },

        /**
         * Render top services chart
         */
        renderServicesChart: function (data) {
            const ctx = document.getElementById('servicesChart');
            if (!ctx) return;

            if (this.charts.services) {
                this.charts.services.destroy();
            }

            this.charts.services = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.labels,
                    datasets: [{
                        data: data.data,
                        backgroundColor: [
                            '#0B1030',
                            '#2563EB',
                            '#D9B96A',
                            '#10B981',
                            '#E11D48',
                            '#F59E0B',
                            '#8B5CF6',
                            '#06B6D4',
                        ],
                        borderColor: '#ffffff',
                        borderWidth: 2,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                font: { family: "'DM Sans', sans-serif" }
                            }
                        }
                    }
                }
            });

            console.log('🍕 Services chart rendered');
        },

        /**
         * Update stat card value
         */
        updateStatCard: function (cardId, value) {
            const element = $('#stat-' + cardId);
            if (element.length) {
                element.fadeOut(200, function () {
                    $(this).text(value).fadeIn(200);
                });
            }
        },

        /**
         * Format currency
         */
        formatCurrency: function (amount) {
            return new Intl.NumberFormat('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            }).format(amount);
        },

        /**
         * Generate report
         */
        generateReport: function (type) {
            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dior_generate_report',
                    nonce: this.nonce,
                    type: type,
                    start_date: $('#dior-start-date').val(),
                    end_date: $('#dior-end-date').val(),
                },
                success: (response) => {
                    if (response.success) {
                        console.log('📋 Report generated:', response.data);
                        // Download or display report
                        this.displayReportModal(response.data);
                    }
                },
                error: (error) => {
                    console.error('❌ Error generating report:', error);
                }
            });
        },

        /**
         * Display report in modal
         */
        displayReportModal: function (report) {
            alert('Report generated: ' + JSON.stringify(report, null, 2));
            // TODO: Create fancy modal for report display
        },

        /**
         * Cleanup
         */
        destroy: function () {
            if (this.autoRefreshInterval) {
                clearInterval(this.autoRefreshInterval);
            }
            Object.keys(this.charts).forEach(key => {
                if (this.charts[key]) {
                    this.charts[key].destroy();
                }
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function () {
        DiorAnalytics.init();
    });

    // Cleanup on page unload
    $(window).on('unload', function () {
        DiorAnalytics.destroy();
    });

    // Expose to global scope if needed
    window.DiorAnalytics = DiorAnalytics;

})(jQuery);
