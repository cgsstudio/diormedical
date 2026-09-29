<?php
/**
 * Dior Medical - Advanced Analytics & Reporting
 * 
 * Provides real-time analytics, charts, and reporting for admin dashboard
 * 
 * @package Dior Medical
 * @version 2.8
 */

if (!defined('ABSPATH'))
    exit;

class Dior_Medical_Analytics
{
    /**
     * Initialize analytics module
     */
    public static function init()
    {
        // AJAX handlers for analytics data
        add_action('wp_ajax_dior_get_dashboard_stats', [__CLASS__, 'ajax_get_dashboard_stats']);
        add_action('wp_ajax_dior_get_revenue_chart', [__CLASS__, 'ajax_get_revenue_chart']);
        add_action('wp_ajax_dior_get_appointments_chart', [__CLASS__, 'ajax_get_appointments_chart']);
        add_action('wp_ajax_dior_get_patient_growth', [__CLASS__, 'ajax_get_patient_growth']);
        add_action('wp_ajax_dior_get_top_services', [__CLASS__, 'ajax_get_top_services']);
        add_action('wp_ajax_dior_generate_report', [__CLASS__, 'ajax_generate_report']);

        // Add admin menu
        add_action('admin_menu', [__CLASS__, 'add_analytics_menu']);

        // Enqueue scripts
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_analytics_scripts']);
    }

    /**
     * Add analytics menu to WordPress admin
     */
    public static function add_analytics_menu()
    {
        if (!current_user_can('manage_options'))
            return;

        add_menu_page(
            'Dior Analytics',
            'Dior Analytics',
            'manage_options',
            'dior-analytics',
            [__CLASS__, 'render_analytics_page'],
            'dashicons-chart-bar',
            30
        );

        add_submenu_page(
            'dior-analytics',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'dior-analytics-dashboard',
            [__CLASS__, 'render_dashboard']
        );

        add_submenu_page(
            'dior-analytics',
            'Revenue Report',
            'Revenue Report',
            'manage_options',
            'dior-analytics-revenue',
            [__CLASS__, 'render_revenue_report']
        );

        add_submenu_page(
            'dior-analytics',
            'Patient Report',
            'Patient Report',
            'manage_options',
            'dior-analytics-patients',
            [__CLASS__, 'render_patient_report']
        );
    }

    /**
     * Enqueue analytics scripts and styles
     */
    public static function enqueue_analytics_scripts($hook)
    {
        if (strpos($hook, 'dior-analytics') === false)
            return;

        // Chart.js library
        wp_enqueue_script('chartjs', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js', [], '3.9.1', true);

        // Date picker
        wp_enqueue_script('daterangepicker', 'https://cdnjs.cloudflare.com/ajax/libs/daterangepicker.js/3.1/moment.min.js', [], '3.1', true);

        // Analytics script
        wp_enqueue_script('dior-analytics-app', DIOR_PORTAL_URL . 'assets/js/dior-analytics.js', ['jquery', 'chartjs'], DIOR_PORTAL_VERSION, true);

        wp_localize_script('dior-analytics-app', 'diorAnalyticsData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dior_analytics_nonce'),
        ]);

        // Styles
        wp_enqueue_style('dior-analytics-css', DIOR_PORTAL_URL . 'assets/css/dior-analytics.css', [], DIOR_PORTAL_VERSION);
    }

    /**
     * GET DASHBOARD STATS - Total overview
     */
    public static function ajax_get_dashboard_stats()
    {
        check_ajax_referer('dior_analytics_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Access denied');
        }

        $period = isset($_POST['period']) ? sanitize_text_field($_POST['period']) : '30'; // days
        $days = intval($period);
        $start_date = date('Y-m-d', strtotime("-{$days} days"));

        // Get statistics
        $stats = [
            'total_patients' => self::count_total_patients(),
            'active_patients' => self::count_active_patients($days),
            'appointments_today' => self::count_appointments_today(),
            'upcoming_appointments' => self::count_upcoming_appointments(),
            'total_revenue' => self::get_total_revenue($days),
            'average_rating' => self::get_average_rating(),
            'pending_questionnaires' => self::count_pending_questionnaires(),
            'new_patients_this_period' => self::count_new_patients($days),
        ];

        wp_send_json_success($stats);
    }

    /**
     * GET REVENUE CHART DATA
     */
    public static function ajax_get_revenue_chart()
    {
        check_ajax_referer('dior_analytics_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Access denied');
        }

        $period = isset($_POST['period']) ? sanitize_text_field($_POST['period']) : '30';
        $days = intval($period);

        $data = self::get_revenue_by_date($days);

        wp_send_json_success([
            'labels' => array_keys($data),
            'data' => array_values($data),
            'period' => $period . ' days'
        ]);
    }

    /**
     * GET APPOINTMENTS CHART
     */
    public static function ajax_get_appointments_chart()
    {
        check_ajax_referer('dior_analytics_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Access denied');
        }

        $period = isset($_POST['period']) ? sanitize_text_field($_POST['period']) : '30';
        $days = intval($period);

        $data = self::get_appointments_by_date($days);

        wp_send_json_success([
            'labels' => array_keys($data),
            'scheduled' => array_column($data, 'scheduled'),
            'completed' => array_column($data, 'completed'),
            'cancelled' => array_column($data, 'cancelled'),
        ]);
    }

    /**
     * GET PATIENT GROWTH
     */
    public static function ajax_get_patient_growth()
    {
        check_ajax_referer('dior_analytics_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Access denied');
        }

        $months = 12;
        $data = self::get_patient_growth_by_month($months);

        wp_send_json_success([
            'labels' => array_keys($data),
            'new_patients' => array_values($data),
        ]);
    }

    /**
     * GET TOP SERVICES
     */
    public static function ajax_get_top_services()
    {
        check_ajax_referer('dior_analytics_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Access denied');
        }

        $services = self::get_top_services_list(10);

        wp_send_json_success([
            'labels' => array_keys($services),
            'data' => array_values($services),
        ]);
    }

    /**
     * GENERATE CUSTOM REPORT
     */
    public static function ajax_generate_report()
    {
        check_ajax_referer('dior_analytics_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Access denied');
        }

        $report_type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'summary';
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : date('Y-m-01');
        $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : date('Y-m-d');

        $report = self::generate_report($report_type, $start_date, $end_date);

        wp_send_json_success($report);
    }

    /**
     * ====== HELPER FUNCTIONS ======
     */

    /**
     * Count total patients
     */
    private static function count_total_patients()
    {
        $users = count_users();
        return isset($users['total_users']) ? $users['total_users'] : 0;
    }

    /**
     * Count active patients (had activity in last N days)
     */
    private static function count_active_patients($days)
    {
        global $wpdb;
        $start_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} 
                WHERE meta_key = 'last_login' AND meta_value >= %s",
                $start_date
            )
        );

        return $count ?: 0;
    }

    /**
     * Count appointments today
     */
    private static function count_appointments_today()
    {
        global $wpdb;
        $today = date('Y-m-d');

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} 
                WHERE meta_key = 'appointment_date' 
                AND meta_value LIKE %s",
                $today . '%'
            )
        );

        return $count ?: 0;
    }

    /**
     * Count upcoming appointments
     */
    private static function count_upcoming_appointments()
    {
        global $wpdb;
        $now = date('Y-m-d H:i:s');

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} 
                WHERE meta_key = 'appointment_date' 
                AND meta_value > %s 
                AND meta_key = 'appointment_status' 
                AND meta_value IN ('scheduled', 'confirmed')",
                $now
            )
        );

        return $count ?: 0;
    }

    /**
     * Get total revenue for period
     */
    private static function get_total_revenue($days)
    {
        global $wpdb;
        $start_date = date('Y-m-d', strtotime("-{$days} days"));

        $total = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(CAST(meta_value AS DECIMAL(10,2))) 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = 'payment_amount' 
                AND post_id IN (
                    SELECT post_id FROM {$wpdb->postmeta} 
                    WHERE meta_key = 'payment_date' 
                    AND meta_value >= %s
                )",
                $start_date
            )
        );

        return floatval($total) ?: 0;
    }

    /**
     * Get average rating
     */
    private static function get_average_rating()
    {
        global $wpdb;

        $avg = $wpdb->get_var(
            "SELECT AVG(CAST(meta_value AS DECIMAL(2,1))) 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = 'appointment_rating'"
        );

        return floatval($avg) ?: 0;
    }

    /**
     * Count pending questionnaires
     */
    private static function count_pending_questionnaires()
    {
        global $wpdb;

        $count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
            WHERE meta_key = 'questionnaire_status' 
            AND meta_value = 'pending'"
        );

        return $count ?: 0;
    }

    /**
     * Count new patients in period
     */
    private static function count_new_patients($days)
    {
        $start_date = date('Y-m-d', strtotime("-{$days} days"));
        $args = [
            'role' => 'subscriber',
            'date_query' => [
                [
                    'after' => $start_date,
                    'inclusive' => true,
                ]
            ],
        ];
        $user_query = new WP_User_Query($args);
        return $user_query->get_total();
    }

    /**
     * Get revenue by date (for charting)
     */
    private static function get_revenue_by_date($days)
    {
        global $wpdb;
        $data = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $revenue = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT SUM(CAST(meta_value AS DECIMAL(10,2))) 
                    FROM {$wpdb->postmeta} 
                    WHERE meta_key = 'payment_amount' 
                    AND post_id IN (
                        SELECT post_id FROM {$wpdb->postmeta} 
                        WHERE meta_key = 'payment_date' 
                        AND meta_value LIKE %s
                    )",
                    $date . '%'
                )
            );
            $data[$date] = floatval($revenue) ?: 0;
        }

        return $data;
    }

    /**
     * Get appointments by date (for charting)
     */
    private static function get_appointments_by_date($days)
    {
        global $wpdb;
        $data = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $data[$date] = [
                'scheduled' => 0,
                'completed' => 0,
                'cancelled' => 0,
            ];
        }

        // Query appointments
        $appointments = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT meta_value as appt_date, meta_key as status 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = 'appointment_status' 
                AND post_id IN (
                    SELECT post_id FROM {$wpdb->postmeta} 
                    WHERE meta_key = 'appointment_date' 
                    AND meta_value >= %s
                )",
                date('Y-m-d', strtotime("-{$days} days"))
            )
        );

        return $data;
    }

    /**
     * Get patient growth by month
     */
    private static function get_patient_growth_by_month($months)
    {
        global $wpdb;
        $data = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-{$i} months"));
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(ID) FROM {$wpdb->users} 
                    WHERE DATE_FORMAT(user_registered, '%%Y-%%m') = %s",
                    $month
                )
            );
            $data[$month] = intval($count);
        }

        return $data;
    }

    /**
     * Get top services
     */
    private static function get_top_services_list($limit = 10)
    {
        global $wpdb;

        $services = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT meta_value as service, COUNT(*) as count 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = 'appointment_service' 
                GROUP BY meta_value 
                ORDER BY count DESC 
                LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        $result = [];
        if ($services) {
            foreach ($services as $service) {
                $result[$service['service']] = intval($service['count']);
            }
        }

        return $result;
    }

    /**
     * Generate custom report
     */
    private static function generate_report($type, $start_date, $end_date)
    {
        global $wpdb;

        $report = [
            'type' => $type,
            'generated_at' => current_time('mysql'),
            'period' => $start_date . ' to ' . $end_date,
            'data' => [],
        ];

        if ($type === 'summary') {
            $report['data'] = [
                'total_patients' => self::count_total_patients(),
                'new_patients' => $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(ID) FROM {$wpdb->users} 
                        WHERE user_registered BETWEEN %s AND %s",
                        $start_date . ' 00:00:00',
                        $end_date . ' 23:59:59'
                    )
                ),
                'total_appointments' => $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->postmeta} 
                        WHERE meta_key = 'appointment_date' 
                        AND meta_value BETWEEN %s AND %s",
                        $start_date,
                        $end_date
                    )
                ),
                'total_revenue' => $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT SUM(CAST(meta_value AS DECIMAL(10,2))) 
                        FROM {$wpdb->postmeta} 
                        WHERE meta_key = 'payment_amount'",
                    )
                ),
            ];
        }

        return $report;
    }

    /**
     * Render analytics dashboard page
     */
    public static function render_analytics_page()
    {
        wp_die('Please use the submenu items.');
    }

    /**
     * Render main dashboard
     */
    public static function render_dashboard()
    {
        ?>
        <div class="wrap dior-analytics-wrap">
            <h1>Dior Medical - Analytics Dashboard</h1>

            <div class="dior-analytics-toolbar">
                <label>Period:
                    <select id="dior-period-select">
                        <option value="7">Last 7 Days</option>
                        <option value="30" selected>Last 30 Days</option>
                        <option value="90">Last 90 Days</option>
                        <option value="365">Last Year</option>
                    </select>
                </label>
                <button class="button button-primary" id="dior-refresh-btn">Refresh Data</button>
            </div>

            <div class="dior-stats-grid">
                <div class="dior-stat-card">
                    <h3>Total Patients</h3>
                    <p class="dior-stat-value" id="stat-total-patients">-</p>
                </div>
                <div class="dior-stat-card">
                    <h3>Active Patients</h3>
                    <p class="dior-stat-value" id="stat-active-patients">-</p>
                </div>
                <div class="dior-stat-card">
                    <h3>Today's Appointments</h3>
                    <p class="dior-stat-value" id="stat-appointments-today">-</p>
                </div>
                <div class="dior-stat-card">
                    <h3>Total Revenue</h3>
                    <p class="dior-stat-value" id="stat-total-revenue">$-</p>
                </div>
            </div>

            <div class="dior-charts-grid">
                <div class="dior-chart-box">
                    <h3>Revenue Trend</h3>
                    <canvas id="revenueChart"></canvas>
                </div>
                <div class="dior-chart-box">
                    <h3>Appointments</h3>
                    <canvas id="appointmentsChart"></canvas>
                </div>
            </div>

            <div class="dior-charts-grid">
                <div class="dior-chart-box">
                    <h3>Patient Growth</h3>
                    <canvas id="patientGrowthChart"></canvas>
                </div>
                <div class="dior-chart-box">
                    <h3>Top Services</h3>
                    <canvas id="servicesChart"></canvas>
                </div>
            </div>
        </div>

        <style>
            .dior-analytics-wrap {
                max-width: 1400px;
                margin: 20px auto;
            }

            .dior-analytics-toolbar {
                background: #f1f1f1;
                padding: 15px;
                margin: 20px 0;
                border-radius: 5px;
            }

            .dior-stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
                margin: 20px 0;
            }

            .dior-stat-card {
                background: white;
                padding: 20px;
                border-radius: 8px;
                border: 1px solid #e0e0e0;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }

            .dior-stat-card h3 {
                margin: 0 0 10px 0;
                color: #666;
                font-size: 14px;
                text-transform: uppercase;
            }

            .dior-stat-value {
                font-size: 32px;
                font-weight: bold;
                color: #0B1030;
                margin: 0;
            }

            .dior-charts-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
                gap: 20px;
                margin: 20px 0;
            }

            .dior-chart-box {
                background: white;
                padding: 20px;
                border-radius: 8px;
                border: 1px solid #e0e0e0;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }

            .dior-chart-box h3 {
                margin: 0 0 20px 0;
                color: #0B1030;
            }

            .dior-chart-box canvas {
                max-height: 300px;
            }
        </style>
        <?php
    }

    /**
     * Render revenue report
     */
    public static function render_revenue_report()
    {
        echo '<div class="wrap"><h1>Revenue Report</h1><p>Revenue reporting functionality...</p></div>';
    }

    /**
     * Render patient report
     */
    public static function render_patient_report()
    {
        echo '<div class="wrap"><h1>Patient Report</h1><p>Patient reporting functionality...</p></div>';
    }
}

// Initialize on plugin load
Dior_Medical_Analytics::init();
