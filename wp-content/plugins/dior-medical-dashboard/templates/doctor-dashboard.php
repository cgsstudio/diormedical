<style>
    /* DIOR DOCTOR DASHBOARD TABLE PAGINATION */
    .dior-table-pagination {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        padding: 14px 24px !important;
        background: #FFFFFF !important;
        border-top: 1px solid #EEF2F6 !important;
        flex-wrap: wrap !important;
        gap: 12px !important;
        font-family: 'DMSans', 'DM Sans', -apple-system, sans-serif !important;
    }

    .dior-pagination-info {
        font-size: 13px !important;
        color: #64748B !important;
        font-weight: 500 !important;
    }

    .dior-pagination-info strong {
        color: #0F172A !important;
        font-weight: 700 !important;
    }

    .dior-pagination-nav {
        display: flex !important;
        align-items: center !important;
        gap: 6px !important;
    }

    .dior-page-btn {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        min-width: 32px !important;
        height: 32px !important;
        padding: 0 10px !important;
        border-radius: 8px !important;
        font-size: 13px !important;
        font-weight: 600 !important;
        border: 1px solid #E2E8F0 !important;
        background: #FFFFFF !important;
        color: #475569 !important;
        cursor: pointer !important;
        transition: all 0.15s ease !important;
        font-family: inherit !important;
    }

    .dior-page-btn:hover:not(:disabled) {
        background: #F8FAFC !important;
        border-color: #CBD5E1 !important;
        color: #0F172A !important;
    }

    .dior-page-btn.active {
        background: linear-gradient(135deg, #2C6CB1 0%, #1A528E 100%) !important;
        color: #FFFFFF !important;
        border-color: transparent !important;
        box-shadow: 0 2px 6px rgba(44, 108, 177, 0.25) !important;
    }

    .dior-page-btn:disabled {
        opacity: 0.4 !important;
        cursor: not-allowed !important;
        background: #F8FAFC !important;
    }
</style>
<div class="dior-wrap dior-doctor-wrap" id="dior-doctor-app">
    <script>
        // Force remove WP Admin bar and its inline margin-top gap
        document.addEventListener('DOMContentLoaded', function() {
            document.documentElement.style.setProperty('margin-top', '0px', 'important');
            const adminBar = document.getElementById('wpadminbar');
            if (adminBar) adminBar.style.display = 'none';
        });
    </script>
    <div class="dior-drawer-backdrop" id="dior-doc-backdrop" onclick="diorDocCloseMobile()"></div>

    <div class="dior-app">

        <!-- ============================================================ -->
        <!-- DOCTOR SIDEBAR                                               -->
        <!-- ============================================================ -->
        <aside class="dior-side" id="dior-doc-sidebar">
            <div class="dior-side-header">
                <div class="brand">
                    <a href="<?php echo esc_url(home_url('/')); ?>" class="brand-link">
                        <?php if (!empty($logo_url)): ?>
                            <img src="<?php echo esc_url($logo_url); ?>" alt="Dior Medical" class="brand-logo">
                        <?php else: ?>
                            <span
                                style="font-size:18px;font-weight:800;color:#2C6CB1;font-family:'Montserrat',sans-serif;">Dior
                                Medical</span>
                        <?php endif; ?>
                    </a>
                </div>
                <button type="button" class="dior-side-close" onclick="diorDocCloseMobile()">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <!-- Doctor Identity Card Removed -->

            <!-- Nav -->
            <nav class="dior-side-nav">
                <div class="grp">
                    <span class="grp-title">PROVIDER MENU</span>

                    <button type="button" class="dior-nav-btn active" data-tab="doc-overview">
                        <i class="fa-solid fa-gauge-high"></i>
                        <span class="nav-label">Overview</span>
                    </button>
                    <button type="button" class="dior-nav-btn" data-tab="doc-patients">
                        <i class="fa-solid fa-users"></i>
                        <span class="nav-label">All Patients</span>
                    </button>
                    <button type="button" class="dior-nav-btn" data-tab="doc-appointments">
                        <i class="fa-solid fa-calendar-check"></i>
                        <span class="nav-label">Appointments</span>
                    </button>
                    <button type="button" class="dior-nav-btn" data-tab="doc-intake">
                        <i class="fa-solid fa-file-shield"></i>
                        <span class="nav-label">Medical Intakes</span>
                    </button>
                    <button type="button" class="dior-nav-btn" data-tab="doc-prescriptions">
                        <i class="fa-solid fa-prescription-bottle-medical"></i>
                        <span class="nav-label">Prescriptions</span>
                    </button>
                    <button type="button" class="dior-nav-btn" data-tab="doc-records">
                        <i class="fa-solid fa-folder-open"></i>
                        <span class="nav-label">Medical Records</span>
                        <?php if (!empty($documents)): ?>
                            <span class="nav-count-badge success"
                                style="background:#ECFDF5;color:#059669;"><?php echo count($documents); ?></span>
                        <?php endif; ?>
                    </button>
                    <button type="button" class="dior-nav-btn" data-tab="doc-letters">
                        <i class="fa-solid fa-file-signature"></i>
                        <span class="nav-label">Letters & Certificates</span>
                    </button>
                    <button type="button" class="dior-nav-btn" data-tab="doc-payments">
                        <i class="fa-solid fa-credit-card"></i>
                        <span class="nav-label">Payments</span>
                    </button>
                    <button type="button" class="dior-nav-btn" data-tab="doc-notifications">
                        <i class="fa-solid fa-bell"></i>
                        <span class="nav-label">Notifications</span>
                        <?php if ($unread_count > 0): ?>
                            <span class="nav-count-badge warning doc-unread-badge"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </button>
                    <button type="button" class="dior-nav-btn" data-tab="doc-settings">
                        <i class="fa-solid fa-gear"></i>
                        <span class="nav-label">Settings</span>
                    </button>
                </div>
            </nav>
        </aside>

        <!-- ============================================================ -->
        <!-- MAIN CONTENT                                                  -->
        <!-- ============================================================ -->
        <main class="dior-main-content">

            <!-- Topbar -->
            <header class="dior-topbar">
                <div class="dior-topbar-left">
                    <button type="button" class="dior-mobile-menu-toggle" onclick="diorDocOpenMobile()">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    <div class="dior-top-search">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" id="dior-doc-search" placeholder="Search patients, appointments..."
                            oninput="diorDocSearch(this.value)">

                    </div>
                </div>
                <div class="dior-topbar-right">
                    <button type="button" class="dior-msg-btn" style="background: none; border: none; font-size: 20px; color: #64748B; cursor: pointer; margin-right: 15px;">
                        <i class="fa-regular fa-envelope"></i>
                    </button>
                    <div class="dior-notif-wrap">
                        <button type="button" class="dior-notif-btn" onclick="diorDocToggleNotif(event)">
                            <i class="fa-regular fa-bell"></i>
                            <?php if ($unread_count > 0): ?>
                                <span class="dior-notif-indicator doc-unread-badge"><?php echo $unread_count; ?></span>
                            <?php endif; ?>
                        </button>
                        <div class="dior-notif-dropdown" id="dior-doc-notif-dd">
                            <div class="dior-notif-header">
                                <h4>Notifications</h4>
                                <button type="button" onclick="diorDocMarkAllRead()">Mark all read</button>
                            </div>
                            <div class="dior-notif-list">
                                <?php foreach (array_slice($notifications, 0, 5) as $n): ?>
                                    <div class="dior-notif-item <?php echo !$n['is_read'] ? 'unread' : ''; ?>"
                                        data-notif-id="<?php echo esc_attr($n['id']); ?>">
                                        <div class="notif-icon"><i class="fa-solid <?php echo esc_attr($n['icon']); ?>"></i>
                                        </div>
                                        <div class="notif-body">
                                            <strong><?php echo esc_html($n['title']); ?></strong>
                                            <p><?php echo esc_html(mb_substr($n['message'], 0, 30) . (mb_strlen($n['message']) > 30 ? '...' : '')); ?>
                                            </p>
                                            <span
                                                class="notif-time"><?php echo esc_html(dior_format_notification_time($n)); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="dior-notif-footer">
                                <button type="button" onclick="diorDocSwitchTab('doc-notifications')">View All
                                    &rarr;</button>
                            </div>
                        </div>
                    </div>
                    <!-- User Quick Profile Pill & Dropdown -->
                    <div class="dior-user-pill-wrap">
                        <button type="button" class="dior-user-pill-btn" onclick="diorToggleProfileDropdown(event)"
                            aria-label="Provider Profile">
                            <div class="dior-pill-avatar" id="dior-doctor-top-avatar"
                                style="background:linear-gradient(135deg,#2C6CB1,#1A528E);color:#FFFFFF;overflow:hidden;display:flex;align-items:center;justify-content:center;">
                                <?php if (!empty($doctor['avatar_url'])): ?>
                                    <img src="<?php echo esc_url($doctor['avatar_url']); ?>"
                                        alt="<?php echo esc_attr($doctor['full_name']); ?>"
                                        style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                                <?php else: ?>
                                    <i class="fa-solid fa-user-doctor" style="color:#FFFFFF;font-size:14px;"></i>
                                <?php endif; ?>
                            </div>
                        </button>

                        <div class="dior-profile-dropdown" id="dior-profile-dropdown">
                            <div class="dior-profile-dropdown-header">
                                HELLO <?php echo esc_html(strtoupper($doctor['full_name'])); ?>..
                            </div>
                            <ul class="dior-profile-dropdown-menu">
                                <li>
                                    <button type="button" onclick="diorDocSwitchTab('doc-settings')">
                                        <i class="fa-solid fa-gear"></i> Settings
                                    </button>
                                </li>
                                <li>
                                    <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>"
                                        class="dior-logout-text-btn">
                                        <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </header>

            <!-- ======================================================== -->
            <!-- TAB PANELS                                                -->
            <!-- ======================================================== -->
            <div class="dior-tab-content" id="dior-doc-content">

                <!-- -- OVERVIEW -->
<!-- ── OVERVIEW ── -->
<section class="dior-tab-panel active" id="tab-doc-overview">
    <div class="dior-content-pad" style="background: #F4F7FB;">
        
        <!-- Top Stats Row -->
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 24px;">
            <!-- 20 Appointments -->
            <div style="background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); display: flex; justify-content: space-between; align-items: flex-start; border: 1px solid #E2E8F0;">
                <div>
                    <h3 style="font-size: 32px; font-weight: 700; color: #2C6CB1; margin: 0 0 8px 0;">20</h3>
                    <p style="color: #1E293B; font-size: 12px; margin: 0; font-weight: 600;">Appointments</p>
                </div>
                <div style="width: 42px; height: 42px; border-radius: 10px; background: #EEF2FF; color: #6366F1; display: flex; align-items: center; justify-content: center; font-size: 18px;">
                    <i class="fa-solid fa-calendar-days"></i>
                </div>
            </div>
            
            <!-- 90 Upcoming Appointments -->
            <div style="background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); display: flex; justify-content: space-between; align-items: flex-start; border: 1px solid #E2E8F0;">
                <div>
                    <h3 style="font-size: 32px; font-weight: 700; color: #B45309; margin: 0 0 8px 0;">90</h3>
                    <p style="color: #1E293B; font-size: 12px; margin: 0; font-weight: 600;">Upcoming Appointments</p>
                </div>
                <div style="width: 42px; height: 42px; border-radius: 10px; background: #FEF3C7; color: #D97706; display: flex; align-items: center; justify-content: center; font-size: 18px;">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </div>
            </div>

            <!-- 23 New Patients -->
            <div style="background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); display: flex; justify-content: space-between; align-items: flex-start; border: 1px solid #E2E8F0;">
                <div>
                    <h3 style="font-size: 32px; font-weight: 700; color: #059669; margin: 0 0 8px 0;">23</h3>
                    <p style="color: #1E293B; font-size: 12px; margin: 0; font-weight: 600;">New Patients</p>
                </div>
                <div style="width: 42px; height: 42px; border-radius: 10px; background: #D1FAE5; color: #10B981; display: flex; align-items: center; justify-content: center; font-size: 18px;">
                    <i class="fa-solid fa-user-doctor"></i>
                </div>
            </div>

            <!-- $500.00 Total Earning -->
            <div style="background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); display: flex; justify-content: space-between; align-items: flex-start; border: 1px solid #E2E8F0;">
                <div>
                    <h3 style="font-size: 32px; font-weight: 700; color: #0284C7; margin: 0 0 8px 0;">$500.00</h3>
                    <p style="color: #1E293B; font-size: 12px; margin: 0; font-weight: 600;">Total Earning</p>
                </div>
                <div style="width: 42px; height: 42px; border-radius: 10px; background: #E0F2FE; color: #0EA5E9; display: flex; align-items: center; justify-content: center; font-size: 18px;">
                    <i class="fa-solid fa-dollar-sign"></i>
                </div>
            </div>
        </div>

        <!-- Layout: Left Column (70%) & Right Column (30%) -->
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; align-items: start;">
            
            <!-- LEFT COLUMN -->
            <div style="display: flex; flex-direction: column; gap: 24px;">
                
                <!-- Today's Schedule -->
                <div class="dior-today-schedule-card" style="background: #fff; border-radius: 12px; padding: 24px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3 class="dior-schedule-title" style="font-family: 'DM Sans', sans-serif; font-weight: 700; font-style: Bold; font-size: 22.14px; line-height: 32.58px; letter-spacing: 0%; vertical-align: middle; color: #2C6CB1; margin: 0;">Today's Schedule</h3>
                        <div style="display: flex; gap: 8px;">
                            <span class="dior-status-badge completed" style="background: #FEEFD9; color: #2D8A3E; padding: 6px 12px; border-radius: 20px; font-family: 'Montserrat', sans-serif; font-weight: 600; font-style: SemiBold; font-size: 11.44px; line-height: 20.07px; letter-spacing: 0%; vertical-align: middle;">2 Completed</span>
                            <span class="dior-status-badge upcoming" style="background: #FAE1E4; color: #9B6100; padding: 6px 12px; border-radius: 20px; font-family: 'Montserrat', sans-serif; font-weight: 600; font-style: SemiBold; font-size: 11.44px; line-height: 20.07px; letter-spacing: 0%; vertical-align: middle;">3 Upcoming</span>
                            <span class="dior-status-badge cancelled" style="background: #FAE1E4; color: #EB001B; padding: 6px 12px; border-radius: 20px; font-family: 'Montserrat', sans-serif; font-weight: 600; font-style: SemiBold; font-size: 11.44px; line-height: 20.07px; letter-spacing: 0%; vertical-align: middle;">1 Cancel</span>
                        </div>
                    </div>
                    
                    <div style="border-bottom: 0.5px solid #A4C0DF; margin-bottom: 20px;"></div>
                    
                    <div class="dior-timeline-container" style="position: relative; padding-left: 0;">
                        <!-- Horizontal Timeline Line -->
                        <div style="position: absolute; left: 70px; top: 0; bottom: 0; width: 1px; background: #00346C;"></div>
                        
                        <!-- 2:00 PM Mia Song -->
                        <div class="dior-timeline-item" style="display: flex; gap: 24px; margin-bottom: 20px; position: relative; align-items: center;">
                            <div class="dior-timeline-time" style="width: 60px; font-family: 'Montserrat', sans-serif; font-weight: 400; font-style: Regular; font-size: 14px; line-height: 19.48px; letter-spacing: 0%; color: #2C6CB1; text-align: right;">2:00 PM</div>
                            <div class="dior-timeline-dot" style="width: 12px; height: 12px; border-radius: 50%; background: #00346C; border: 2px solid #fff; position: absolute; left: 64px; top: 50%; transform: translateY(-50%); z-index: 2;"></div>
                            <div class="dior-patient-card" style="flex: 1; background: #F4F9FD; border: 1px solid #E2E8F0; border-radius: 12px; padding: 16px; display: flex; justify-content: space-between; align-items: center;">
                                <div style="display: flex; gap: 12px; align-items: center;">
                                    <div style="width: 48px; height: 48px; border-radius: 50%; overflow: hidden; border: 1.42px solid #A4C0DF; display: flex; align-items: center; justify-content: center; background: #fff;">
                                        <img src="assets/images/users/user-1.png" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.src='https://ui-avatars.com/api/?name=Mia+Song&background=random';">
                                    </div>
                                    <div>
                                        <h4 class="dior-patient-name" style="margin: 0; font-family: 'Montserrat', sans-serif; font-weight: 600; font-style: SemiBold; font-size: 17.37px; line-height: 27.22px; letter-spacing: 0%; color: #000000;">Mia Song</h4>
                                        <p class="dior-patient-id" style="margin: 0; font-family: 'Montserrat', sans-serif; font-weight: 400; font-style: Regular; font-size: 12.41px; line-height: 21.78px; letter-spacing: 0%; color: #000000;">PAT00123</p>
                                    </div>
                                </div>
                                <div style="display: flex; gap: 12px; align-items: center;">
                                    <i class="fa-solid fa-wifi" style="color: #3B82F6;"></i>
                                    <span class="dior-status-badge in-progress" style="background: #FFEDD5; color: #EA580C; padding: 6px 16px; border-radius: 20px; font-family: 'Montserrat', sans-serif; font-weight: 600; font-size: 11px;">In Progress</span>
                                </div>
                            </div>
                        </div>

                        <!-- 1:00 PM John Johnson -->
                        <div class="dior-timeline-item" style="display: flex; gap: 24px; margin-bottom: 20px; position: relative; align-items: center;">
                            <div class="dior-timeline-time" style="width: 60px; font-family: 'Montserrat', sans-serif; font-weight: 400; font-style: Regular; font-size: 14px; line-height: 19.48px; letter-spacing: 0%; color: #94A3B8; text-align: right;">1:00 PM</div>
                            <div class="dior-timeline-dot" style="width: 12px; height: 12px; border-radius: 50%; background: #00346C; border: 2px solid #fff; position: absolute; left: 64px; top: 50%; transform: translateY(-50%); z-index: 2;"></div>
                            <div class="dior-patient-card" style="flex: 1; background: #F4F9FD; border: 1px solid #E2E8F0; border-radius: 12px; padding: 16px; display: flex; justify-content: space-between; align-items: center;">
                                <div style="display: flex; gap: 12px; align-items: center;">
                                    <div style="width: 48px; height: 48px; border-radius: 50%; overflow: hidden; border: 1.42px solid #A4C0DF; display: flex; align-items: center; justify-content: center; background: #fff;">
                                        <img src="assets/images/users/user-2.png" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.src='https://ui-avatars.com/api/?name=John+Johnson&background=random';">
                                    </div>
                                    <div>
                                        <h4 class="dior-patient-name" style="margin: 0; font-family: 'Montserrat', sans-serif; font-weight: 600; font-style: SemiBold; font-size: 17.37px; line-height: 27.22px; letter-spacing: 0%; color: #000000;">John Johnson</h4>
                                        <p class="dior-patient-id" style="margin: 0; font-family: 'Montserrat', sans-serif; font-weight: 400; font-style: Regular; font-size: 12.41px; line-height: 21.78px; letter-spacing: 0%; color: #000000;">PAT00111</p>
                                    </div>
                                </div>
                                <span class="dior-status-badge completed" style="background: #FEEFD9; color: #2D8A3E; padding: 6px 16px; border-radius: 20px; font-family: 'Montserrat', sans-serif; font-weight: 600; font-size: 11px;">Completed</span>
                            </div>
                        </div>

                        <!-- 12:00 PM Richard Davis -->
                        <div class="dior-timeline-item" style="display: flex; gap: 24px; margin-bottom: 20px; position: relative; align-items: center;">
                            <div class="dior-timeline-time" style="width: 60px; font-family: 'Montserrat', sans-serif; font-weight: 400; font-style: Regular; font-size: 14px; line-height: 19.48px; letter-spacing: 0%; color: #94A3B8; text-align: right;">12:00 PM</div>
                            <div class="dior-timeline-dot" style="width: 12px; height: 12px; border-radius: 50%; background: #00346C; border: 2px solid #fff; position: absolute; left: 64px; top: 50%; transform: translateY(-50%); z-index: 2;"></div>
                            <div class="dior-patient-card" style="flex: 1; background: #F4F9FD; border: 1px solid #E2E8F0; border-radius: 12px; padding: 16px; display: flex; justify-content: space-between; align-items: center;">
                                <div style="display: flex; gap: 12px; align-items: center;">
                                    <div style="width: 48px; height: 48px; border-radius: 50%; overflow: hidden; border: 1.42px solid #A4C0DF; display: flex; align-items: center; justify-content: center; background: #fff;">
                                        <img src="assets/images/users/user-3.png" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.src='https://ui-avatars.com/api/?name=Richard+Davis&background=random';">
                                    </div>
                                    <div>
                                        <h4 class="dior-patient-name" style="margin: 0; font-family: 'Montserrat', sans-serif; font-weight: 600; font-style: SemiBold; font-size: 17.37px; line-height: 27.22px; letter-spacing: 0%; color: #000000;">Richard Davis</h4>
                                        <p class="dior-patient-id" style="margin: 0; font-family: 'Montserrat', sans-serif; font-weight: 400; font-style: Regular; font-size: 12.41px; line-height: 21.78px; letter-spacing: 0%; color: #000000;">PAT00238</p>
                                    </div>
                                </div>
                                <span class="dior-status-badge cancelled" style="background: #FAE1E4; color: #EB001B; padding: 6px 16px; border-radius: 20px; font-family: 'Montserrat', sans-serif; font-weight: 600; font-size: 11px;">Cancelled</span>
                            </div>
                        </div>

                        <!-- 3:00 PM Elizabeth Brown -->
                        <div class="dior-timeline-item" style="display: flex; gap: 24px; position: relative; align-items: center;">
                            <div class="dior-timeline-time" style="width: 60px; font-family: 'Montserrat', sans-serif; font-weight: 400; font-style: Regular; font-size: 14px; line-height: 19.48px; letter-spacing: 0%; color: #2C6CB1; text-align: right;">3:00 PM</div>
                            <div class="dior-timeline-dot" style="width: 12px; height: 12px; border-radius: 50%; background: #00346C; border: 2px solid #fff; position: absolute; left: 64px; top: 50%; transform: translateY(-50%); z-index: 2; box-shadow: 0 0 0 3px rgba(44,108,177,0.2);"></div>
                            <div class="dior-patient-card" style="flex: 1; background: #F4F9FD; border: 1px solid #E2E8F0; border-radius: 12px; padding: 16px; display: flex; justify-content: space-between; align-items: center;">
                                <div style="display: flex; gap: 12px; align-items: center;">
                                    <div style="width: 48px; height: 48px; border-radius: 50%; overflow: hidden; border: 1.42px solid #A4C0DF; display: flex; align-items: center; justify-content: center; background: #fff;">
                                        <img src="assets/images/users/user-4.png" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.src='https://ui-avatars.com/api/?name=Elizabeth+Brown&background=random';">
                                    </div>
                                    <div>
                                        <h4 class="dior-patient-name" style="margin: 0; font-family: 'Montserrat', sans-serif; font-weight: 600; font-style: SemiBold; font-size: 17.37px; line-height: 27.22px; letter-spacing: 0%; color: #000000;">Elizabeth Brown</h4>
                                        <p class="dior-patient-id" style="margin: 0; font-family: 'Montserrat', sans-serif; font-weight: 400; font-style: Regular; font-size: 12.41px; line-height: 21.78px; letter-spacing: 0%; color: #000000;">PAT00112</p>
                                    </div>
                                </div>
                                <div style="display: flex; gap: 8px; align-items: center;">
                                    <button style="width: 32px; height: 32px; border-radius: 6px; background: #8B5CF6; color: #fff; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center;"><i class="fa-solid fa-video"></i></button>
                                    <button style="width: 32px; height: 32px; border-radius: 6px; background: #0EA5E9; color: #fff; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center;"><i class="fa-solid fa-envelope"></i></button>
                                    <button style="background: #2C6CB1; color: #fff; padding: 8px 16px; border-radius: 6px; border: none; font-size: 12px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px;"><i class="fa-solid fa-play"></i> Join Now</button>
                                    <span class="dior-status-badge upcoming" style="background: #FAE1E4; color: #9B6100; padding: 6px 16px; border-radius: 20px; font-family: 'Montserrat', sans-serif; font-weight: 600; font-size: 11px; margin-left: 8px;">Upcoming</span>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Latest Appointments -->
                <div style="background: #FFFFFF; border-radius: 12px; border: 1.06px solid rgba(44, 108, 177, 0.4); padding: 24px;">
                    <h3 style="font-family: 'DM Sans', sans-serif; font-weight: 700; font-style: Bold; font-size: 22.14px; line-height: 32.58px; letter-spacing: 0%; vertical-align: middle; color: #2C6CB1; margin: 0 0 20px 0;">Latest Appointments</h3>
                    <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 12px;">
                        <thead>
                            <tr>
                                <th style="padding: 12px 0; border:none; background: #FFFFFF; font-family: 'Montserrat', sans-serif; font-weight: 600; font-style: SemiBold; font-size: 18.12px; line-height: 30.22px; letter-spacing: 0%; color: #2C6CB1;">Appointments Id</th>
                                <th style="padding: 12px 0; border:none; background: #FFFFFF; font-family: 'Montserrat', sans-serif; font-weight: 600; font-style: SemiBold; font-size: 18.12px; line-height: 30.22px; letter-spacing: 0%; color: #2C6CB1;">Patient Name</th>
                                <th style="padding: 12px 0; border:none; background: #FFFFFF; font-family: 'Montserrat', sans-serif; font-weight: 600; font-style: SemiBold; font-size: 18.12px; line-height: 30.22px; letter-spacing: 0%; color: #2C6CB1;">Date</th>
                                <th style="padding: 12px 0; border:none; background: #FFFFFF; font-family: 'Montserrat', sans-serif; font-weight: 600; font-style: SemiBold; font-size: 18.12px; line-height: 30.22px; letter-spacing: 0%; color: #2C6CB1;">Disease</th>
                                <th style="padding: 12px 0; border:none; background: #FFFFFF; font-family: 'Montserrat', sans-serif; font-weight: 600; font-style: SemiBold; font-size: 18.12px; line-height: 30.22px; letter-spacing: 0%; color: #2C6CB1;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF;font-family: 'Montserrat', sans-serif; font-weight: 500; font-style: Medium; font-size: 16.1px; line-height: 30.22px; letter-spacing: 0%; color: #000000;">APP00123</td>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF;font-family: 'Montserrat', sans-serif; font-weight: 500; font-style: Medium; font-size: 16.1px; line-height: 30.22px; letter-spacing: 0%; color: #000000;">Mia Song</td>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF;font-family: 'Montserrat', sans-serif; font-weight: 500; font-style: Medium; font-size: 16.1px; line-height: 30.22px; letter-spacing: 0%; color: #000000;">Sep 10, 2026</td>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF;font-family: 'Montserrat', sans-serif; font-weight: 500; font-style: Medium; font-size: 16.1px; line-height: 30.22px; letter-spacing: 0%; color: #000000;">Fever</td>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF; text-align: right; display: flex; gap: 8px; justify-content: flex-end;">
                                    <button style="background: #2C6CB1; width:50%; color: #FFFFFF; border: none; padding: 6px 6px; border-radius: 20px; font-family: 'Montserrat', sans-serif; font-weight: 600; font-style: SemiBold; font-size: 12px; line-height: 24px; letter-spacing: 0%; text-align: center; vertical-align: middle;">Prescription</button>
                                    <button style="background: #fff; width:50%; color: #2C6CB1; border: 0.48px solid #2C6CB1; padding: 6px 12px; border-radius: 20px; font-family: 'Montserrat', sans-serif; font-weight: 600; font-style: SemiBold; font-size: 12px; line-height: 24px; letter-spacing: 0%; text-align: center; vertical-align: middle;">Details</button>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF; font-family: 'Montserrat', sans-serif; font-weight: 500; font-style: Medium; font-size: 16.1px; line-height: 30.22px; letter-spacing: 0%; color: #000000;">APP00111</td>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF;font-family: 'Montserrat', sans-serif; font-weight: 500; font-style: Medium; font-size: 16.1px; line-height: 30.22px; letter-spacing: 0%; color: #000000;">John Johnson</td>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF;font-family: 'Montserrat', sans-serif; font-weight: 500; font-style: Medium; font-size: 16.1px; line-height: 30.22px; letter-spacing: 0%; color: #000000;">Sep 05, 2026</td>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF;font-family: 'Montserrat', sans-serif; font-weight: 500; font-style: Medium; font-size: 16.1px; line-height: 30.22px; letter-spacing: 0%; color: #000000;">Cholera</td>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF; text-align: right; display: flex; gap: 8px; justify-content: flex-end;">
                                   <button style="background: #2C6CB1; width:50%; color: #FFFFFF; border: none; padding: 6px 6px; border-radius: 20px; font-family: 'Montserrat', sans-serif; font-weight: 600; font-style: SemiBold; font-size: 12px; line-height: 24px; letter-spacing: 0%; text-align: center; vertical-align: middle;">Prescription</button>
                                    <button style="background: #fff; width:50%; color: #2C6CB1; border: 0.48px solid #2C6CB1; padding: 6px 12px; border-radius: 20px; font-family: 'Montserrat', sans-serif; font-weight: 600; font-style: SemiBold; font-size: 12px; line-height: 24px; letter-spacing: 0%; text-align: center; vertical-align: middle;">Details</button>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF; font-family: 'Montserrat', sans-serif; font-weight: 500; font-style: Medium; font-size: 16.1px; line-height: 30.22px; letter-spacing: 0%; color: #000000;">APP00112</td>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF;font-family: 'Montserrat', sans-serif; font-weight: 500; font-style: Medium; font-size: 16.1px; line-height: 30.22px; letter-spacing: 0%; color: #000000;">Elizabeth Brown</td>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF;font-family: 'Montserrat', sans-serif; font-weight: 500; font-style: Medium; font-size: 16.1px; line-height: 30.22px; letter-spacing: 0%; color: #000000;">Aug 26, 2026</td>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF;font-family: 'Montserrat', sans-serif; font-weight: 500; font-style: Medium; font-size: 16.1px; line-height: 30.22px; letter-spacing: 0%; color: #000000;">Jaundice</td>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF; text-align: right; display: flex; gap: 8px; justify-content: flex-end;">
                                    <button style="background: #2C6CB1; width:50%; color: #FFFFFF; border: none; padding: 6px 6px; border-radius: 20px; font-family: 'Montserrat', sans-serif; font-weight: 600; font-style: SemiBold; font-size: 12px; line-height: 24px; letter-spacing: 0%; text-align: center; vertical-align: middle;">Prescription</button>
                                    <button style="background: #fff; width:50%; color: #2C6CB1; border: 0.48px solid #2C6CB1; padding: 6px 12px; border-radius: 20px; font-family: 'Montserrat', sans-serif; font-weight: 600; font-style: SemiBold; font-size: 12px; line-height: 24px; letter-spacing: 0%; text-align: center; vertical-align: middle;">Details</button>
                                 </td>
                            </tr>
                            <tr>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF; font-family: 'Montserrat', sans-serif; font-weight: 500; font-style: Medium; font-size: 16.1px; line-height: 30.22px; letter-spacing: 0%; color: #000000;">APP00112</td>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF;font-family: 'Montserrat', sans-serif; font-weight: 500; font-style: Medium; font-size: 16.1px; line-height: 30.22px; letter-spacing: 0%; color: #000000;">Elizabeth Brown</td>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF;font-family: 'Montserrat', sans-serif; font-weight: 500; font-style: Medium; font-size: 16.1px; line-height: 30.22px; letter-spacing: 0%; color: #000000;">Aug 26, 2026</td>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF;font-family: 'Montserrat', sans-serif; font-weight: 500; font-style: Medium; font-size: 16.1px; line-height: 30.22px; letter-spacing: 0%; color: #000000;">Typhoid</td>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF; text-align: right; display: flex; gap: 8px; justify-content: flex-end;">
                                   <button style="background: #2C6CB1; width:50%; color: #FFFFFF; border: none; padding: 6px 6px; border-radius: 20px; font-family: 'Montserrat', sans-serif; font-weight: 600; font-style: SemiBold; font-size: 12px; line-height: 24px; letter-spacing: 0%; text-align: center; vertical-align: middle;">Prescription</button>
                                    <button style="background: #fff; width:50%; color: #2C6CB1; border: 0.48px solid #2C6CB1; padding: 6px 12px; border-radius: 20px; font-family: 'Montserrat', sans-serif; font-weight: 600; font-style: SemiBold; font-size: 12px; line-height: 24px; letter-spacing: 0%; text-align: center; vertical-align: middle;">Details</button>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF; font-family: 'Montserrat', sans-serif; font-weight: 500; font-style: Medium; font-size: 16.1px; line-height: 30.22px; letter-spacing: 0%; color: #000000;">APP00112</td>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF;font-family: 'Montserrat', sans-serif; font-weight: 500; font-style: Medium; font-size: 16.1px; line-height: 30.22px; letter-spacing: 0%; color: #000000;">Elizabeth Brown</td>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF;font-family: 'Montserrat', sans-serif; font-weight: 500; font-style: Medium; font-size: 16.1px; line-height: 30.22px; letter-spacing: 0%; color: #000000;">Aug 25, 2026</td>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF;font-family: 'Montserrat', sans-serif; font-weight: 500; font-style: Medium; font-size: 16.1px; line-height: 30.22px; letter-spacing: 0%; color: #000000;">Malaria</td>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF; text-align: right; display: flex; gap: 8px; justify-content: flex-end;">
                                   <button style="background: #2C6CB1; width:50%; color: #FFFFFF; border: none; padding: 6px 6px; border-radius: 20px; font-family: 'Montserrat', sans-serif; font-weight: 600; font-style: SemiBold; font-size: 12px; line-height: 24px; letter-spacing: 0%; text-align: center; vertical-align: middle;">Prescription</button>
                                    <button style="background: #fff; width:50%; color: #2C6CB1; border: 0.48px solid #2C6CB1; padding: 6px 12px; border-radius: 20px; font-family: 'Montserrat', sans-serif; font-weight: 600; font-style: SemiBold; font-size: 12px; line-height: 24px; letter-spacing: 0%; text-align: center; vertical-align: middle;">Details</button>
                                 </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Prescriptions Overview -->
                <div style="background: #fff; border-radius: 12px; border: 1px solid #E2E8F0; padding: 24px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3 style="font-size: 16px; font-weight: 700; color: #2C6CB1; margin: 0;">Prescriptions Overview</h3>
                        <div style="display: flex; gap: 8px;">
                            <span style="background: #D1FAE5; color: #059669; padding: 4px 10px; border-radius: 20px; font-size: 10px; font-weight: 700;">45 Active</span>
                            <span style="background: #FEF3C7; color: #D97706; padding: 4px 10px; border-radius: 20px; font-size: 10px; font-weight: 700;">12 Inactive</span>
                            <span style="background: #FEE2E2; color: #DC2626; padding: 4px 10px; border-radius: 20px; font-size: 10px; font-weight: 700;">6 Expired</span>
                        </div>
                    </div>
                    <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 12px;">
                        <thead>
                            <tr>
                                <th style="padding: 12px 0; border:none; background: #FFFFFF; color: #2C6CB1; font-weight: 700;">Prescriptions Id</th>
                                <th style="padding: 12px 0; border:none; background: #FFFFFF; color: #2C6CB1; font-weight: 700;">Patient Name</th>
                                <th style="padding: 12px 0; border:none; background: #FFFFFF; color: #2C6CB1; font-weight: 700;">Medication</th>
                                <th style="padding: 12px 0; border:none; background: #FFFFFF; color: #2C6CB1; font-weight: 700;">Dosage</th>
                                <th style="padding: 12px 0; border:none; background: #FFFFFF; color: #2C6CB1; font-weight: 700; text-align: right;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF; font-weight: 700; color: #1E293B;">PRS00123</td>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF; color: #475569;">Mia Song</td>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF; color: #475569;">Amlodipine</td>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF; color: #475569;">5mg</td>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF; text-align: right;"><span style="background: #10B981; color: #fff; padding: 4px 16px; border-radius: 20px; font-size: 10px; font-weight: 600;">Active</span></td>
                            </tr>
                            <tr>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF; font-weight: 700; color: #1E293B;">PRS00111</td>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF; color: #475569;">John Johnson</td>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF; color: #475569;">Amoxicillin</td>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF; color: #475569;">500mg</td>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF; text-align: right;"><span style="background: #D97706; color: #fff; padding: 4px 16px; border-radius: 20px; font-size: 10px; font-weight: 600;">Inactive</span></td>
                            </tr>
                            <tr>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF; font-weight: 700; color: #1E293B;">PRS00112</td>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF; color: #475569;">Elizabeth Brown</td>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF; color: #475569;">Atorvastatin</td>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF; color: #475569;">20mg</td>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF; text-align: right;"><span style="background: #10B981; color: #fff; padding: 4px 16px; border-radius: 20px; font-size: 10px; font-weight: 600;">Active</span></td>
                            </tr>
                            <tr>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF; font-weight: 700; color: #1E293B;">PRS00112</td>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF; color: #475569;">Elizabeth Brown</td>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF; color: #475569;">Ibuprofen</td>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF; color: #475569;">400mg</td>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF; text-align: right;"><span style="background: #10B981; color: #fff; padding: 4px 16px; border-radius: 20px; font-size: 10px; font-weight: 600;">Active</span></td>
                            </tr>
                            <tr>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF; font-weight: 700; color: #1E293B;">PRS00112</td>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF; color: #475569;">Elizabeth Brown</td>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF; color: #475569;">Lisinopril</td>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF; color: #475569;">10mg</td>
                                <td style="padding: 16px 0; border:none; background: #FFFFFF; text-align: right;"><span style="background: #10B981; color: #fff; padding: 4px 16px; border-radius: 20px; font-size: 10px; font-weight: 600;">Active</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Latest Lab Results -->
                <div style="background: #fff; border-radius: 12px; border: 1px solid #E2E8F0; padding: 24px;">
                    <h3 style="font-size: 16px; font-weight: 700; color: #2C6CB1; margin: 0 0 20px 0;">Latest Lab Results</h3>
                    <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 12px;">
                        <thead>
                            <tr>
                                <th style="padding: 12px 0; color: #2C6CB1; font-weight: 700;">Patient Id</th>
                                <th style="padding: 12px 0; color: #2C6CB1; font-weight: 700;">Patient Name</th>
                                <th style="padding: 12px 0; color: #2C6CB1; font-weight: 700;">Test Name</th>
                                <th style="padding: 12px 0; color: #2C6CB1; font-weight: 700;">Date</th>
                                <th style="padding: 12px 0; color: #2C6CB1; font-weight: 700;">Status</th>
                                <th style="padding: 12px 0; color: #2C6CB1; font-weight: 700; text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="padding: 16px 0; font-weight: 700; color: #1E293B;">PAT00123</td>
                                <td style="padding: 16px 0; color: #475569;">Mia Song</td>
                                <td style="padding: 16px 0; color: #475569;">Blood Work</td>
                                <td style="padding: 16px 0; color: #475569;">Sep 10, 2026</td>
                                <td style="padding: 16px 0;"><span style="background: #10B981; color: #fff; padding: 4px 16px; border-radius: 20px; font-size: 10px; font-weight: 600;">Ready</span></td>
                                <td style="padding: 16px 0; text-align: right;">
                                    <button style="background: none; border: none; color: #2C6CB1; cursor: pointer; padding: 0 4px;"><i class="fa-solid fa-info-circle"></i></button>
                                    <button style="background: #10B981; border: none; color: #fff; border-radius: 4px; padding: 4px 6px; cursor: pointer;"><i class="fa-solid fa-download"></i></button>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 16px 0; font-weight: 700; color: #1E293B;">PAT00111</td>
                                <td style="padding: 16px 0; color: #475569;">John Johnson</td>
                                <td style="padding: 16px 0; color: #475569;">Renal Function Test</td>
                                <td style="padding: 16px 0; color: #475569;">Sep 10, 2026</td>
                                <td style="padding: 16px 0;"><span style="background: #D97706; color: #fff; padding: 4px 16px; border-radius: 20px; font-size: 10px; font-weight: 600;">Pending</span></td>
                                <td style="padding: 16px 0; text-align: right;">
                                    <button style="background: none; border: none; color: #2C6CB1; cursor: pointer; padding: 0 4px;"><i class="fa-solid fa-info-circle"></i></button>
                                    <button style="background: #10B981; border: none; color: #fff; border-radius: 4px; padding: 4px 6px; cursor: pointer;"><i class="fa-solid fa-download"></i></button>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 16px 0; font-weight: 700; color: #1E293B;">PAT00112</td>
                                <td style="padding: 16px 0; color: #475569;">Elizabeth Brown</td>
                                <td style="padding: 16px 0; color: #475569;">Thyroid Function Test</td>
                                <td style="padding: 16px 0; color: #475569;">Sep 10, 2026</td>
                                <td style="padding: 16px 0;"><span style="background: #10B981; color: #fff; padding: 4px 16px; border-radius: 20px; font-size: 10px; font-weight: 600;">In Progress</span></td>
                                <td style="padding: 16px 0; text-align: right;">
                                    <button style="background: none; border: none; color: #2C6CB1; cursor: pointer; padding: 0 4px;"><i class="fa-solid fa-info-circle"></i></button>
                                    <button style="background: #10B981; border: none; color: #fff; border-radius: 4px; padding: 4px 6px; cursor: pointer;"><i class="fa-solid fa-download"></i></button>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 16px 0; font-weight: 700; color: #1E293B;">PAT00112</td>
                                <td style="padding: 16px 0; color: #475569;">Elizabeth Brown</td>
                                <td style="padding: 16px 0; color: #475569;">Vitamin D Test</td>
                                <td style="padding: 16px 0; color: #475569;">Sep 10, 2026</td>
                                <td style="padding: 16px 0;"><span style="background: #10B981; color: #fff; padding: 4px 16px; border-radius: 20px; font-size: 10px; font-weight: 600;">Normal</span></td>
                                <td style="padding: 16px 0; text-align: right;">
                                    <button style="background: none; border: none; color: #2C6CB1; cursor: pointer; padding: 0 4px;"><i class="fa-solid fa-info-circle"></i></button>
                                    <button style="background: #10B981; border: none; color: #fff; border-radius: 4px; padding: 4px 6px; cursor: pointer;"><i class="fa-solid fa-download"></i></button>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 16px 0; font-weight: 700; color: #1E293B;">PAT00112</td>
                                <td style="padding: 16px 0; color: #475569;">Elizabeth Brown</td>
                                <td style="padding: 16px 0; color: #475569;">Lisinopril</td>
                                <td style="padding: 16px 0; color: #475569;">Sep 10, 2026</td>
                                <td style="padding: 16px 0;"><span style="background: #10B981; color: #fff; padding: 4px 16px; border-radius: 20px; font-size: 10px; font-weight: 600;">Normal</span></td>
                                <td style="padding: 16px 0; text-align: right;">
                                    <button style="background: none; border: none; color: #2C6CB1; cursor: pointer; padding: 0 4px;"><i class="fa-solid fa-info-circle"></i></button>
                                    <button style="background: #10B981; border: none; color: #fff; border-radius: 4px; padding: 4px 6px; cursor: pointer;"><i class="fa-solid fa-download"></i></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Bottom Row (Patient Review & Upcoming Sessions) -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                    <!-- Patient Review -->
                    <div style="background: #fff; border-radius: 12px; border: 1px solid #E2E8F0; padding: 24px;">
                        <h3 style="font-size: 16px; font-weight: 700; color: #2C6CB1; margin: 0 0 20px 0;">Patient Review</h3>
                        
                        <div style="display: flex; flex-direction: column; gap: 20px;">
                            <div style="padding-bottom: 16px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                    <div style="display: flex; gap: 12px; align-items: center;">
                                        <img src="assets/images/users/user-1.png" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;" onerror="this.src='https://ui-avatars.com/api/?name=Mia+Song&background=random';">
                                        <h4 style="margin: 0; font-size: 13px; font-weight: 600; color: #1E293B;">Mia Song</h4>
                                    </div>
                                    <div style="color: #F59E0B; font-size: 10px;">
                                        <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                                    </div>
                                </div>
                                <p style="margin: 0; font-size: 12px; color: #475569; line-height: 1.5;">Excellent doctor! Very professional and caring.</p>
                            </div>
                            
                            <div style="padding-bottom: 16px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                    <div style="display: flex; gap: 12px; align-items: center;">
                                        <img src="assets/images/users/user-2.png" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;" onerror="this.src='https://ui-avatars.com/api/?name=John+Johnson&background=random';">
                                        <h4 style="margin: 0; font-size: 13px; font-weight: 600; color: #1E293B;">John Johnson</h4>
                                    </div>
                                    <div style="color: #F59E0B; font-size: 10px;">
                                        <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                                    </div>
                                </div>
                                <p style="margin: 0; font-size: 12px; color: #475569; line-height: 1.5;">Great experience. Highly recommended!</p>
                            </div>

                            <div style="padding-bottom: 16px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                    <div style="display: flex; gap: 12px; align-items: center;">
                                        <img src="assets/images/users/user-4.png" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;" onerror="this.src='https://ui-avatars.com/api/?name=Elizabeth+Brown&background=random';">
                                        <h4 style="margin: 0; font-size: 13px; font-weight: 600; color: #1E293B;">Elizabeth Brown</h4>
                                    </div>
                                    <div style="color: #F59E0B; font-size: 10px;">
                                        <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                                    </div>
                                </div>
                                <p style="margin: 0; font-size: 12px; color: #475569; line-height: 1.5;">Very knowledgeable and patient.</p>
                            </div>

                            <div>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                    <div style="display: flex; gap: 12px; align-items: center;">
                                        <img src="assets/images/users/user-3.png" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;" onerror="this.src='https://ui-avatars.com/api/?name=Richard+Davis&background=random';">
                                        <h4 style="margin: 0; font-size: 13px; font-weight: 600; color: #1E293B;">Richard Davis</h4>
                                    </div>
                                    <div style="color: #F59E0B; font-size: 10px;">
                                        <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                                    </div>
                                </div>
                                <p style="margin: 0; font-size: 12px; color: #475569; line-height: 1.5;">Excellent doctor! Very professional and caring.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Upcoming Sessions -->
                    <div style="background: #fff; border-radius: 12px; border: 1px solid #E2E8F0; padding: 24px;">
                        <h3 style="font-size: 16px; font-weight: 700; color: #2C6CB1; margin: 0 0 20px 0;">Upcoming Sessions</h3>
                        
                        <div style="display: flex; flex-direction: column; gap: 20px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 16px;">
                                <div style="display: flex; gap: 12px; align-items: center;">
                                    <img src="assets/images/users/user-4.png" style="width: 42px; height: 42px; border-radius: 50%; object-fit: cover;" onerror="this.src='https://ui-avatars.com/api/?name=Elizabeth+Brown&background=random';">
                                    <div>
                                        <h4 style="margin: 0; font-size: 14px; font-weight: 700; color: #1E293B;">General Consultation</h4>
                                        <p style="margin: 2px 0; font-size: 11px; color: #64748B;">Elizabeth Brown | PAT00112</p>
                                        <p style="margin: 0; font-size: 10px; color: #94A3B8;"><i class="fa-regular fa-calendar" style="margin-right: 4px;"></i> Sep 29, 2026 &nbsp;&nbsp; <i class="fa-regular fa-clock" style="margin-right: 4px;"></i> 05:30 PM</p>
                                    </div>
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 6px;">
                                    <button style="background: #fff; color: #2C6CB1; border: 1px solid #2C6CB1; border-radius: 20px; padding: 4px 16px; font-size: 10px; font-weight: 600; cursor: pointer;">Reschedule</button>
                                    <button style="background: #2C6CB1; color: #fff; border: none; border-radius: 20px; padding: 4px 16px; font-size: 10px; font-weight: 600; cursor: pointer;">Join Now</button>
                                </div>
                            </div>
                            
                            <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 16px;">
                                <div style="display: flex; gap: 12px; align-items: center;">
                                    <img src="assets/images/users/user-1.png" style="width: 42px; height: 42px; border-radius: 50%; object-fit: cover;" onerror="this.src='https://ui-avatars.com/api/?name=Mia+Song&background=random';">
                                    <div>
                                        <h4 style="margin: 0; font-size: 14px; font-weight: 700; color: #1E293B;">Follow-up Appointment</h4>
                                        <p style="margin: 2px 0; font-size: 11px; color: #64748B;">Mia Song | PAT00123</p>
                                        <p style="margin: 0; font-size: 10px; color: #94A3B8;"><i class="fa-regular fa-calendar" style="margin-right: 4px;"></i> Oct 2, 2026 &nbsp;&nbsp; <i class="fa-regular fa-clock" style="margin-right: 4px;"></i> 11:00 AM</p>
                                    </div>
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 6px;">
                                    <button style="background: #fff; color: #2C6CB1; border: 1px solid #2C6CB1; border-radius: 20px; padding: 4px 16px; font-size: 10px; font-weight: 600; cursor: pointer;">Reschedule</button>
                                    <button style="background: #2C6CB1; color: #fff; border: none; border-radius: 20px; padding: 4px 16px; font-size: 10px; font-weight: 600; cursor: pointer;">Join Now</button>
                                </div>
                            </div>

                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div style="display: flex; gap: 12px; align-items: center;">
                                    <img src="assets/images/users/user-3.png" style="width: 42px; height: 42px; border-radius: 50%; object-fit: cover;" onerror="this.src='https://ui-avatars.com/api/?name=Richard+Davis&background=random';">
                                    <div>
                                        <h4 style="margin: 0; font-size: 14px; font-weight: 700; color: #1E293B;">General Consultation</h4>
                                        <p style="margin: 2px 0; font-size: 11px; color: #64748B;">Richard Davis | PAT00238</p>
                                        <p style="margin: 0; font-size: 10px; color: #94A3B8;"><i class="fa-regular fa-calendar" style="margin-right: 4px;"></i> Oct 2, 2026 &nbsp;&nbsp; <i class="fa-regular fa-clock" style="margin-right: 4px;"></i> 11:00 AM</p>
                                    </div>
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 6px;">
                                    <button style="background: #fff; color: #2C6CB1; border: 1px solid #2C6CB1; border-radius: 20px; padding: 4px 16px; font-size: 10px; font-weight: 600; cursor: pointer;">Reschedule</button>
                                    <button style="background: #2C6CB1; color: #fff; border: none; border-radius: 20px; padding: 4px 16px; font-size: 10px; font-weight: 600; cursor: pointer;">Join Now</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            
            <!-- RIGHT COLUMN -->
            <div style="display: flex; flex-direction: column; gap: 24px;">
                
                <!-- Profile Card -->
                <div style="background: #fff; border-radius: 12px; border: 1px solid #ABC4E0; padding: 24px; text-align: center;">
                    <div style="width: 80px; height: 80px; border-radius: 50%; background-color: #8B735C; display: flex; justify-content: center; align-items: center; margin: 0 auto 16px auto;">
                        <span style="font-size: 32px; font-weight: 700; color: #fff;">DC</span>
                    </div>
                    <h3 style="margin: 0 0 4px 0; font-family: 'DM Sans', sans-serif; font-weight: 700; font-style: Bold; font-size: 20px; line-height: 23.37px; letter-spacing: 0%; vertical-align: middle; text-transform: capitalize; color: #1A528E;">Dr. Diorca Aquino De La Cruz</h3>
                    <p style="margin: 0 0 16px 0; font-size: 12px; color: #64748B;">Cardiologist</p>
                    
                    <div style="display: flex; flex-direction: column; gap: 8px; font-size: 12px; color: #475569; text-align: left; margin-bottom: 20px;">
                        <div><i class="fa-solid fa-envelope" style="color: #2C6CB1; width: 20px;"></i> abc@gmail.com</div>
                        <div style="display: flex; justify-content: space-between;">
                            <div><i class="fa-solid fa-phone" style="color: #2C6CB1; width: 20px;"></i> +1 234 567 8900</div>
                            <div><i class="fa-solid fa-briefcase" style="color: #2C6CB1; width: 20px;"></i> 12 years experience</div>
                        </div>
                    </div>
                    
                    <button style="width: 100%; background: #2C6CB1; color: #fff; border: none; border-radius: 20px; padding: 10px; font-size: 12px; font-weight: 600; cursor: pointer;">Edit Profile</button>
                </div>

                <!-- Pending Tasks -->
                <div style="background: #fff; border-radius: 12px; border: 1px solid #E2E8F0; padding: 24px;">
                    <h3 style="font-size: 16px; font-weight: 700; color: #2C6CB1; margin: 0 0 20px 0;">Pending Tasks</h3>
                    
                    <div style="display: flex; flex-direction: column; gap: 16px;">
                        <div style="display: flex; gap: 12px; align-items: flex-start; padding-bottom: 16px;">
                            <input type="checkbox" style="margin-top: 4px; width: 14px; height: 14px; accent-color: #2C6CB1; cursor: pointer;">
                            <div>
                                <h4 style="margin: 0 0 4px 0; font-size: 13px; font-weight: 600; color: #1E293B;">Review lab reports</h4>
                                <p style="margin: 0 0 6px 0; font-size: 11px; color: #64748B;">Check blood test results for 3 patients</p>
                                <span style="font-size: 10px; color: #94A3B8;"><i class="fa-regular fa-calendar"></i> Oct 2, 2026</span>
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 12px; align-items: flex-start; padding-bottom: 16px;">
                            <input type="checkbox" style="margin-top: 4px; width: 14px; height: 14px; accent-color: #2C6CB1; cursor: pointer;">
                            <div>
                                <h4 style="margin: 0 0 4px 0; font-size: 13px; font-weight: 600; color: #1E293B;">Sign prescriptions</h4>
                                <p style="margin: 0 0 6px 0; font-size: 11px; color: #64748B;">5 prescriptions pending signature</p>
                                <span style="font-size: 10px; color: #94A3B8;"><i class="fa-regular fa-calendar"></i> Oct 2, 2026</span>
                            </div>
                        </div>

                        <div style="display: flex; gap: 12px; align-items: flex-start; padding-bottom: 16px;">
                            <input type="checkbox" style="margin-top: 4px; width: 14px; height: 14px; accent-color: #2C6CB1; cursor: pointer;">
                            <div>
                                <h4 style="margin: 0 0 4px 0; font-size: 13px; font-weight: 600; color: #1E293B;">Approve medical notes</h4>
                                <p style="margin: 0 0 6px 0; font-size: 11px; color: #64748B;">Review and approve consultation notes</p>
                                <span style="font-size: 10px; color: #94A3B8;"><i class="fa-regular fa-calendar"></i> Oct 2, 2026</span>
                            </div>
                        </div>

                        <div style="display: flex; gap: 12px; align-items: flex-start;">
                            <input type="checkbox" style="margin-top: 4px; width: 14px; height: 14px; accent-color: #2C6CB1; cursor: pointer;">
                            <div>
                                <h4 style="margin: 0 0 4px 0; font-size: 13px; font-weight: 600; color: #1E293B;">Update patient records</h4>
                                <p style="margin: 0 0 6px 0; font-size: 11px; color: #64748B;">Complete EMR updates for recent visits</p>
                                <span style="font-size: 10px; color: #94A3B8;"><i class="fa-regular fa-calendar"></i> Oct 2, 2026</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Next Appointment -->
                <div class="dior-next-appointment-card" style="background: #fff; border: 1.06px solid rgba(44, 108, 177, 0.4); border-radius: 12px; padding: 24px;">
                    <h3 class="dior-next-appointment-title" style="font-family: 'DM Sans', sans-serif; font-weight: 700; font-style: Bold; font-size: 22.14px; line-height: 32.58px; letter-spacing: 0%; vertical-align: middle; color: #2C6CB1; margin: 0 0 20px 0;">Next Appointment</h3>
                    
                    <div style="display: flex; flex-direction: column; gap: 20px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 16px;">
                            <div style="display: flex; gap: 12px; align-items: center;">
                                <img src="assets/images/users/user-1.png" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover;" onerror="this.src='https://ui-avatars.com/api/?name=Mia+Song&background=random';">
                                <div>
                                    <h4 style="margin: 0 0 2px 0; font-size: 12px; font-weight: 700; color: #1E293B;">Mia Song | PAT00123</h4>
                                    <p style="margin: 0 0 2px 0; font-size: 10px; color: #64748B;">Routine Checkup</p>
                                    <span style="font-size: 9px; color: #94A3B8;"><i class="fa-regular fa-calendar"></i> Oct 2, 2026</span>
                                </div>
                            </div>
                            <div style="display: flex; gap: 6px;">
                                <button style="width: 24px; height: 24px; border-radius: 4px; background: #fff; border: 1px solid #CBD5E1; color: #2C6CB1; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 10px;"><i class="fa-solid fa-envelope"></i></button>
                                <button style="width: 24px; height: 24px; border-radius: 4px; background: #fff; border: 1px solid #CBD5E1; color: #2C6CB1; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 10px;"><i class="fa-solid fa-phone"></i></button>
                            </div>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 16px;">
                            <div style="display: flex; gap: 12px; align-items: center;">
                                <img src="assets/images/users/user-2.png" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover;" onerror="this.src='https://ui-avatars.com/api/?name=John+Johnson&background=random';">
                                <div>
                                    <h4 style="margin: 0 0 2px 0; font-size: 12px; font-weight: 700; color: #1E293B;">John Johnson | PAT00111</h4>
                                    <p style="margin: 0 0 2px 0; font-size: 10px; color: #64748B;">Routine Checkup</p>
                                    <span style="font-size: 9px; color: #94A3B8;"><i class="fa-regular fa-calendar"></i> Oct 2, 2026</span>
                                </div>
                            </div>
                            <div style="display: flex; gap: 6px;">
                                <button style="width: 24px; height: 24px; border-radius: 4px; background: #fff; border: 1px solid #CBD5E1; color: #2C6CB1; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 10px;"><i class="fa-solid fa-envelope"></i></button>
                                <button style="width: 24px; height: 24px; border-radius: 4px; background: #fff; border: 1px solid #CBD5E1; color: #2C6CB1; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 10px;"><i class="fa-solid fa-phone"></i></button>
                            </div>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 16px;">
                            <div style="display: flex; gap: 12px; align-items: center;">
                                <img src="assets/images/users/user-3.png" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover;" onerror="this.src='https://ui-avatars.com/api/?name=Richard+Davis&background=random';">
                                <div>
                                    <h4 style="margin: 0 0 2px 0; font-size: 12px; font-weight: 700; color: #1E293B;">Richard Davis | PAT00238</h4>
                                    <p style="margin: 0 0 2px 0; font-size: 10px; color: #64748B;">Routine Checkup</p>
                                    <span style="font-size: 9px; color: #94A3B8;"><i class="fa-regular fa-calendar"></i> Oct 2, 2026</span>
                                </div>
                            </div>
                            <div style="display: flex; gap: 6px;">
                                <button style="width: 24px; height: 24px; border-radius: 4px; background: #fff; border: 1px solid #CBD5E1; color: #2C6CB1; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 10px;"><i class="fa-solid fa-envelope"></i></button>
                                <button style="width: 24px; height: 24px; border-radius: 4px; background: #fff; border: 1px solid #CBD5E1; color: #2C6CB1; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 10px;"><i class="fa-solid fa-phone"></i></button>
                            </div>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div style="display: flex; gap: 12px; align-items: center;">
                                <img src="assets/images/users/user-4.png" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover;" onerror="this.src='https://ui-avatars.com/api/?name=Elizabeth+Brown&background=random';">
                                <div>
                                    <h4 style="margin: 0 0 2px 0; font-size: 12px; font-weight: 700; color: #1E293B;">Elizabeth Brown | PAT00112</h4>
                                    <p style="margin: 0 0 2px 0; font-size: 10px; color: #64748B;">Routine Checkup</p>
                                    <span style="font-size: 9px; color: #94A3B8;"><i class="fa-regular fa-calendar"></i> Oct 2, 2026</span>
                                </div>
                            </div>
                            <div style="display: flex; gap: 6px;">
                                <button style="width: 24px; height: 24px; border-radius: 4px; background: #fff; border: 1px solid #CBD5E1; color: #2C6CB1; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 10px;"><i class="fa-solid fa-envelope"></i></button>
                                <button style="width: 24px; height: 24px; border-radius: 4px; background: #fff; border: 1px solid #CBD5E1; color: #2C6CB1; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 10px;"><i class="fa-solid fa-phone"></i></button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>


                <!-- -- ALL PATIENTS -- -->
                <section class="dior-tab-panel" id="tab-doc-patients">
                    <div class="dior-content-pad">
                        <div class="dior-page-title-bar new-design">
                            <div class="title-left">
                                <div class="title-icon-box blue-tint" style="background:#E0F2FE; color:#0284C7; width:45px; height:45px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:20px;">
                                    <i class="fa-solid fa-users"></i>
                                </div>
                                <div>
                                    <h1>Patient Management</h1>
                                    <p class="title-sub">All registered patients, intake forms and clinical data.</p>
                                </div>
                            </div>
                            <div class="title-right">
                                <span style="background: #D1FAE5; color: #10B981; padding: 4px 10px; border-radius: 20px; font-size: 11.5px; font-weight: 700;" style="font-size:14px;"><i class="fa-solid fa-users"></i>
                                    <?php echo $total_patients; ?> Patients</span>
                            </div>
                        </div>

                        <div class="dior-dash-table-card dior-box-card">
                            <div class="box-title-row">
                                <h3><i class="fa-solid fa-users"></i> All Patients</h3>
                                <input type="text" class="dior-search-input" placeholder="Search patients�"
                                    oninput="diorDocFilterPatients(this.value)">
                            </div>
                            <div class="table-responsive" style="padding:16px 24px 24px 24px;overflow-x:auto;">
                                <table class="dior-clean-table" id="dior-patients-table">
                                    <thead>
                                        <tr>
                                            <th>Patient ID</th>
                                            <th>Patient Name</th>
                                            <th>Phone</th>
                                            <th>DOB</th>
                                            <th>Joined</th>
                                            <th>Intake</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($patients as $p): ?>
                                            <tr class="patient-row"
                                                data-search="<?php echo esc_attr(strtolower($p['full_name'] . ' ' . $p['patient_id'] . ' ' . $p['email'])); ?>">
                                                <td><code
                                                        style="background:#F1F5F9;padding:4px 8px;border-radius:6px;font-size:13px;color:#1A528E;font-weight:600;border:1px solid #E2E8F0;"><?php echo esc_html($p['patient_id']); ?></code>
                                                </td>
                                                <td>
                                                    <div style="display:flex;align-items:center;gap:12px;">
                                                        <div
                                                            style="background:#F5F3FF;color:#7C3AED;width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0;">
                                                            <?php echo esc_html($p['initials']); ?>
                                                        </div>
                                                        <div>
                                                            <strong
                                                                style="color:#1E293B;font-size:14px;"><?php echo esc_html($p['full_name']); ?></strong><br>
                                                            <small
                                                                style="color:#64748B;"><?php echo esc_html($p['email']); ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo esc_html($p['phone']); ?></td>
                                                <td><?php echo esc_html($p['dob']); ?></td>
                                                <td><?php echo esc_html($p['registered']); ?></td>
                                                <td>
                                                    <?php if ($p['has_intake']): ?>
                                                        <span style="background: #D1FAE5; color: #10B981; padding: 4px 10px; border-radius: 20px; font-size: 11.5px; font-weight: 700;"><i class="fa-solid fa-file-shield"></i>
                                                            Submitted</span>
                                                    <?php else: ?>
                                                        <span style="background: #FEF3C7; color: #D97706; padding: 4px 10px; border-radius: 20px; font-size: 11.5px; font-weight: 700;">Pending</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div style="display:flex;align-items:center;gap:6px;flex-wrap:nowrap;">
                                                        <button type="button" class="dior-btn-sm"
                                                            style="display:inline-flex;align-items:center;gap:5px;padding:6px 11px;height:28px;box-sizing:border-box;border-radius:6px;font-size:11.5px;font-weight:600;line-height:1;"
                                                            onclick="diorDocViewPatient(<?php echo (int) $p['user_id']; ?>)">
                                                            <i class="fa-solid fa-eye"></i> View
                                                        </button>
                                                        <?php if (!empty($p['has_active_appt'])): ?>
                                                            <?php
                                                            $p_zoom = !empty($doctor['zoom_link']) ? $doctor['zoom_link'] : 'https://zoom.us/join';
                                                            $p_apt_date = $p['next_appt_date'] ?? '';
                                                            $p_apt_time = $p['next_appt_time'] ?? '';
                                                            $p_apt_status = $p['next_appt_status'] ?? 'Confirmed';
                                                            ?>
                                                            <a href="<?php echo esc_url($p_zoom); ?>" target="_blank"
                                                                rel="noopener noreferrer" class="dior-call-btn"
                                                                onclick="return diorDocStartCall(event, '<?php echo esc_js(esc_url($p_zoom)); ?>', '<?php echo esc_js($p_apt_date); ?>', '<?php echo esc_js($p_apt_time); ?>', '<?php echo esc_js($p_apt_status); ?>')"
                                                                style="display:inline-flex;align-items:center;gap:5px;padding:6px 11px;height:28px;box-sizing:border-box;background:linear-gradient(135deg,#059669,#047857);color:#FFFFFF;border-radius:6px;font-size:11.5px;font-weight:700;text-decoration:none;box-shadow:0 2px 6px rgba(5,150,105,0.25);white-space:nowrap;line-height:1;transition:all 0.2s ease;"
                                                                title="Start Video Call in New Tab">
                                                                <i class="fa-solid fa-video"></i> Call
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- -- APPOINTMENTS -- -->
                <section class="dior-tab-panel" id="tab-doc-appointments">
                    <div class="dior-content-pad" style="background: #F4F7FB; padding: 24px 24px 40px 24px; border-radius: 16px;">
                        
                        <!-- Breadcrumb / Header -->
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                            <h2 style="font-size: 22px; font-weight: 600; color: #1E293B; margin: 0;">Appointments</h2>
                            <div style="font-size: 13px; color: #64748B; display: flex; align-items: center; gap: 8px; font-weight: 500;">
                                <i class="fa-solid fa-house" style="color: #6366F1;"></i> / Appointments
                            </div>
                        </div>

                        <!-- Main Card -->
                        <div class="dior-box-card" style="background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); overflow: hidden; padding: 0 !important; border: 1px solid #E2E8F0;">
                            
                            <!-- Top Controls -->
                            <div style="padding: 20px 24px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #E2E8F0;">
                                <h3 style="font-size: 18px; font-weight: 700; color: #1E293B; margin: 0; position: relative; padding-bottom: 20px; top: 10px;">
                                    Appointments
                                    <div style="position: absolute; bottom: 0; left: 0; width: 40px; height: 3px; background: #6366F1; border-radius: 3px;"></div>
                                </h3>
                                
                                <div style="display: flex; gap: 12px; align-items: center;">
                                    <div style="position: relative;">
                                        <i class="fa-solid fa-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94A3B8; font-size: 13px;"></i>
                                        <input type="text" id="dior-doc-search-appts" placeholder="Search records.." onkeyup="diorDocFilterAppts(this.value)" style="padding: 8px 12px 8px 32px; border: 1px solid #E2E8F0; border-radius: 8px; font-size: 13px; width: 220px; color: #475569; background: #fff; outline: none; transition: all 0.2s;">
                                    </div>
                                    <button style="width: 36px; height: 36px; border-radius: 8px; background: #6366F1; color: #fff; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 5px rgba(99,102,241,0.3); transition: all 0.2s;"><i class="fa-solid fa-plus"></i></button>
                                    <button style="width: 36px; height: 36px; border-radius: 8px; background: #10B981; color: #fff; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 5px rgba(16,185,129,0.3); transition: all 0.2s;"><i class="fa-solid fa-download"></i></button>
                                    <button style="width: 36px; height: 36px; border-radius: 8px; background: #3B82F6; color: #fff; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 5px rgba(59,130,246,0.3); transition: all 0.2s;"><i class="fa-solid fa-rotate-right"></i></button>
                                </div>
                            </div>

                            <!-- Table -->
                            <div class="table-responsive" style="padding: 0;">
                                <table style="width: 100%; border-collapse: collapse; text-align: left; min-width: 1200px;">
                                    <thead>
                                        <tr style="border-bottom: 1px solid #E2E8F0; background: #FAFAFA;">
                                            <th style="padding: 16px 24px; width: 40px;"><input type="checkbox" style="width: 16px; height: 16px; border: 1px solid #CBD5E1; border-radius: 4px; accent-color: #6366F1; cursor: pointer;"></th>
                                            <th style="padding: 16px 12px; font-size: 11px; font-weight: 700; color: #64748B; text-transform: uppercase;">Patient Name <i class="fa-solid fa-sort" style="color: #CBD5E1; margin-left: 4px; cursor: pointer;"></i></th>
                                            <th style="padding: 16px 12px; font-size: 11px; font-weight: 700; color: #64748B; text-transform: uppercase;">Appointment Date <i class="fa-solid fa-sort" style="color: #CBD5E1; margin-left: 4px; cursor: pointer;"></i></th>
                                            <th style="padding: 16px 12px; font-size: 11px; font-weight: 700; color: #64748B; text-transform: uppercase;">Time <i class="fa-solid fa-sort" style="color: #CBD5E1; margin-left: 4px; cursor: pointer;"></i></th>
                                            <th style="padding: 16px 12px; font-size: 11px; font-weight: 700; color: #64748B; text-transform: uppercase;">Email <i class="fa-solid fa-sort" style="color: #CBD5E1; margin-left: 4px; cursor: pointer;"></i></th>
                                            <th style="padding: 16px 12px; font-size: 11px; font-weight: 700; color: #64748B; text-transform: uppercase;">Mobile <i class="fa-solid fa-sort" style="color: #CBD5E1; margin-left: 4px; cursor: pointer;"></i></th>
                                            <th style="padding: 16px 12px; font-size: 11px; font-weight: 700; color: #64748B; text-transform: uppercase;">Gender <i class="fa-solid fa-sort" style="color: #CBD5E1; margin-left: 4px; cursor: pointer;"></i></th>
                                            <th style="padding: 16px 12px; font-size: 11px; font-weight: 700; color: #64748B; text-transform: uppercase;">Status <i class="fa-solid fa-sort" style="color: #CBD5E1; margin-left: 4px; cursor: pointer;"></i></th>
                                            <th style="padding: 16px 12px; font-size: 11px; font-weight: 700; color: #64748B; text-transform: uppercase;">Address <i class="fa-solid fa-sort" style="color: #CBD5E1; margin-left: 4px; cursor: pointer;"></i></th>
                                            <th style="padding: 16px 12px; font-size: 11px; font-weight: 700; color: #64748B; text-transform: uppercase;">Disease <i class="fa-solid fa-sort" style="color: #CBD5E1; margin-left: 4px; cursor: pointer;"></i></th>
                                            <th style="padding: 16px 12px; font-size: 11px; font-weight: 700; color: #64748B; text-transform: uppercase;">Last Visit <i class="fa-solid fa-sort" style="color: #CBD5E1; margin-left: 4px; cursor: pointer;"></i></th>
                                            <th style="padding: 16px 24px; font-size: 11px; font-weight: 700; color: #64748B; text-transform: uppercase; text-align: center;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($appointments)): ?>
                                            <tr>
                                                <td colspan="12" style="padding: 32px; text-align: center; color: #64748B; font-size: 14px;">
                                                    <i class="fa-regular fa-calendar" style="font-size: 24px; margin-bottom: 12px; color: #CBD5E1; display: block;"></i>
                                                    No appointments found.
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($appointments as $apt): 
                                                // Dynamic data mapping
                                                $patient_name = esc_html($apt['patient_name'] ?? 'Unknown');
                                                $initials = strtoupper(substr($patient_name, 0, 1));
                                                $date = esc_html($apt['date'] ?? 'N/A');
                                                $time = esc_html($apt['time'] ?? 'N/A');
                                                $email = strtolower(str_replace(' ', '.', $patient_name)) . '@example.com';
                                                $mobile = '+1 234 567 890';
                                                $gender = rand(0,1) ? 'male' : 'female';
                                                $address = '123 Main St, NY';
                                                $disease = esc_html($apt['condition'] ?? 'Checkup');
                                                $last_visit = date('M d, Y', strtotime('-1 month'));
                                                $status = strtolower($apt['status'] ?? 'upcoming');
                                                
                                                if($status == 'completed' || $status == 'done') {
                                                    $st_bg = '#D1FAE5'; $st_color = '#059669'; $st_text = 'Completed';
                                                } elseif($status == 'cancelled' || $status == 'cancel') {
                                                    $st_bg = '#FEE2E2'; $st_color = '#DC2626'; $st_text = 'Cancelled';
                                                } else {
                                                    $st_bg = '#DBEAFE'; $st_color = '#2563EB'; $st_text = 'Upcoming';
                                                }
                                            ?>
                                                <tr class="appt-row" data-status="<?php echo esc_attr($apt['status'] ?? ''); ?>" style="border-bottom: 1px solid #F1F5F9; transition: background 0.2s;" onmouseover="this.style.background='#F8FAFC'" onmouseout="this.style.background='transparent'">
                                                    <td style="padding: 16px 24px;"><input type="checkbox" style="width: 16px; height: 16px; border: 1px solid #CBD5E1; border-radius: 4px; accent-color: #6366F1; cursor: pointer;"></td>
                                                    <td style="padding: 16px 12px;">
                                                        <div style="display: flex; align-items: center; gap: 12px;">
                                                            <div style="width: 32px; height: 32px; border-radius: 50%; background: #E2E8F0; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #475569; font-size: 12px; overflow: hidden;">
                                                                <img src="assets/images/users/user-<?php echo rand(1,5); ?>.png" alt="User" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.style.display='none'; this.nextSibling.style.display='block';">
                                                                <span style="display:none;"><?php echo $initials; ?></span>
                                                            </div>
                                                            <span style="font-size: 13px; font-weight: 600; color: #1E293B;"><?php echo $patient_name; ?></span>
                                                        </div>
                                                    </td>
                                                    <td style="padding: 16px 12px; font-size: 13px; color: #475569; white-space: nowrap;"><i class="fa-regular fa-calendar" style="color: #3B82F6; margin-right: 6px;"></i> <?php echo $date; ?></td>
                                                    <td style="padding: 16px 12px; font-size: 13px; color: #475569; white-space: nowrap;"><i class="fa-regular fa-clock" style="color: #6366F1; margin-right: 6px;"></i> <?php echo $time; ?></td>
                                                    <td style="padding: 16px 12px; font-size: 13px; color: #475569; white-space: nowrap;"><i class="fa-regular fa-envelope" style="color: #EF4444; margin-right: 6px;"></i> <?php echo (strlen($email) > 15 ? substr($email, 0, 15).'..' : $email); ?></td>
                                                    <td style="padding: 16px 12px; font-size: 13px; color: #475569; white-space: nowrap;"><i class="fa-solid fa-phone" style="color: #10B981; margin-right: 6px;"></i> <?php echo substr($mobile, 0, 12).'..'; ?></td>
                                                    <td style="padding: 16px 12px; font-size: 13px; color: #475569; white-space: nowrap;"><i class="fa-solid <?php echo $gender == 'male' ? 'fa-mars' : 'fa-venus'; ?>" style="color: <?php echo $gender == 'male' ? '#3B82F6' : '#EC4899'; ?>; margin-right: 6px;"></i> <?php echo ucfirst($gender); ?></td>
                                                    <td style="padding: 16px 12px; white-space: nowrap;">
                                                        <span style="background: <?php echo $st_bg; ?>; color: <?php echo $st_color; ?>; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600;"><?php echo $st_text; ?></span>
                                                    </td>
                                                    <td style="padding: 16px 12px; font-size: 13px; color: #475569; white-space: nowrap;"><i class="fa-solid fa-location-dot" style="color: #0EA5E9; margin-right: 6px;"></i> <?php echo substr($address, 0, 15).'..'; ?></td>
                                                    <td style="padding: 16px 12px; font-size: 13px; color: #475569;"><?php echo $disease; ?></td>
                                                    <td style="padding: 16px 12px; font-size: 13px; color: #475569; white-space: nowrap;"><i class="fa-regular fa-calendar-check" style="color: #3B82F6; margin-right: 6px;"></i> <?php echo $last_visit; ?></td>
                                                    <td style="padding: 16px 24px; text-align: center; white-space: nowrap;">
                                                        <div style="display: flex; gap: 8px; justify-content: center;">
                                                            <button onclick="diorDocViewPatient(<?php echo (int) ($apt['user_id'] ?? 0); ?>)" style="width: 28px; height: 28px; border-radius: 6px; background: #EEF2FF; color: #6366F1; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s;" title="Edit"><i class="fa-solid fa-pen" style="font-size: 11px;"></i></button>
                                                            <button style="width: 28px; height: 28px; border-radius: 6px; background: #FEF2F2; color: #EF4444; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s;" title="Delete"><i class="fa-regular fa-trash-can" style="font-size: 11px;"></i></button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination / Summary Footer -->
                            <div style="padding: 16px 24px; border-top: 1px solid #E2E8F0; font-size: 13px; color: #64748B; font-weight: 500;">
                                0 selected / <?php echo count($appointments); ?> total
                            </div>
                        </div>

                    </div>
                </section>

                <!-- -- MEDICAL INTAKES -- -->
<section class="dior-tab-panel" id="tab-doc-consultation" style="display:none;">
    <div style="background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="width: 50px; height: 50px; border-radius: 12px; background: #DBEAFE; color: #3B82F6; display: flex; align-items: center; justify-content: center; font-size: 20px;">
                <i class="fa-solid fa-video"></i>
            </div>
            <div>
                <h2 style="margin: 0 0 4px 0; font-size: 20px; font-weight: 700; color: #1E293B;">HD Telehealth Consultation Room</h2>
                <span style="font-size: 13px; color: #64748B;">
                    Patient: <strong style="color: #475569;">Sarah Jenkins (#PAT-1082)</strong> &bull; Attending: <strong style="color: #475569;"><?php echo esc_html($doctor['full_name'] ?? 'Doctor'); ?></strong>
                </span>
            </div>
        </div>
        <div style="display: flex; gap: 12px; align-items: center;">
            <span style="background: #D1FAE5; color: #10B981; padding: 8px 16px; border-radius: 20px; font-size: 12px; font-weight: 700;">
                <i class="fa-solid fa-wifi" style="margin-right: 4px;"></i> Signal: Strong (5G)
            </span>
            <span style="background: #EF4444; color: #fff; padding: 8px 16px; border-radius: 20px; font-size: 12px; font-weight: 700; display: flex; align-items: center;">
                <div style="width: 8px; height: 8px; background: #fff; border-radius: 50%; margin-right: 6px; animation: pulse 1.5s infinite;"></div> REC HD 1080p
            </span>
        </div>
    </div>

    <div style="display: flex; gap: 24px; flex-wrap: wrap;">
        
        <!-- Video Feed Section -->
        <div style="flex: 2; min-width: 600px; position: relative; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.1); background: #1E293B; height: 600px;">
            <img src="https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&q=80&w=1200" style="width: 100%; height: 100%; object-fit: cover;" alt="Patient Video">
            
            <div style="position: absolute; top: 20px; left: 20px; background: rgba(30, 41, 59, 0.8); backdrop-filter: blur(4px); padding: 8px 16px; border-radius: 20px; display: flex; align-items: center; gap: 8px; color: #fff; font-size: 13px; font-weight: 600;">
                <i class="fa-regular fa-clock" style="color: #10B981;"></i> 01:19
            </div>

            <!-- PIP Window -->
            <div style="position: absolute; top: 20px; right: 20px; width: 180px; height: 120px; border-radius: 12px; overflow: hidden; border: 3px solid #fff; box-shadow: 0 10px 15px rgba(0,0,0,0.2);">
                <img src="https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=crop&q=80&w=400" style="width: 100%; height: 100%; object-fit: cover;" alt="Doctor View">
            </div>

            <!-- Patient Name Tag -->
            <div style="position: absolute; bottom: 100px; left: 20px; background: rgba(30, 41, 59, 0.8); backdrop-filter: blur(4px); padding: 12px 16px; border-radius: 12px; display: flex; align-items: center; gap: 12px;">
                <div style="width: 10px; height: 10px; background: #10B981; border-radius: 50%;"></div>
                <div>
                    <span style="display: block; color: #fff; font-size: 14px; font-weight: 700;">Sarah Jenkins</span>
                    <span style="display: block; color: #94A3B8; font-size: 11px;">Patient &bull; DOB: 1982</span>
                </div>
            </div>

            <!-- Controls Dock -->
            <div style="position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%); background: rgba(30, 41, 59, 0.8); backdrop-filter: blur(8px); padding: 12px 24px; border-radius: 30px; display: flex; gap: 16px;">
                <button type="button" style="width: 44px; height: 44px; border-radius: 50%; border: none; background: rgba(255,255,255,0.1); color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'">
                    <i class="fa-solid fa-microphone"></i>
                </button>
                <button type="button" style="width: 44px; height: 44px; border-radius: 50%; border: none; background: rgba(255,255,255,0.1); color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'">
                    <i class="fa-solid fa-video"></i>
                </button>
                <button type="button" style="width: 44px; height: 44px; border-radius: 50%; border: none; background: rgba(255,255,255,0.1); color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'">
                    <i class="fa-solid fa-desktop"></i>
                </button>
                <button type="button" style="width: 44px; height: 44px; border-radius: 50%; border: none; background: rgba(255,255,255,0.1); color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'">
                    <i class="fa-regular fa-comment-dots"></i>
                </button>
                <button type="button" style="width: 44px; height: 44px; border-radius: 50%; border: none; background: #EF4444; color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s; margin-left: 8px;" onmouseover="this.style.background='#DC2626'" onmouseout="this.style.background='#EF4444'">
                    <i class="fa-solid fa-phone-slash"></i>
                </button>
            </div>
        </div>

        <!-- Chat Panel -->
        <div style="flex: 1; min-width: 350px; background: #fff; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); display: flex; flex-direction: column; height: 600px;">
            <div style="display: flex; border-bottom: 1px solid #E2E8F0; padding: 12px; gap: 8px;">
                <button type="button" style="flex: 1; background: #3B82F6; color: #fff; border: none; padding: 10px; border-radius: 8px; font-weight: 600; font-size: 13px; cursor: pointer;">
                    <i class="fa-regular fa-comments" style="margin-right: 6px;"></i> Live Chat
                </button>
                <button type="button" style="flex: 1; background: transparent; color: #64748B; border: none; padding: 10px; border-radius: 8px; font-weight: 600; font-size: 13px; cursor: pointer;">
                    <i class="fa-regular fa-clipboard" style="margin-right: 6px;"></i> Notes
                </button>
                <button type="button" style="flex: 1; background: transparent; color: #64748B; border: none; padding: 10px; border-radius: 8px; font-weight: 600; font-size: 13px; cursor: pointer;">
                    <i class="fa-solid fa-heart-pulse" style="margin-right: 6px;"></i> Vitals
                </button>
            </div>

            <div id="dior-doc-chat-container" style="flex: 1; padding: 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 20px;">
                
                <!-- Doctor Message (Self) -->
                <div style="display: flex; gap: 12px; max-width: 85%; align-self: flex-end; flex-direction: row-reverse;">
                    <img src="https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=crop&q=80&w=100" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 4px; flex-direction: row-reverse;">
                            <span style="font-size: 13px; font-weight: 700; color: #1E293B;">Dr. Helen Miller</span>
                            <span style="font-size: 11px; color: #94A3B8;">09:30 AM</span>
                        </div>
                        <div style="background: #3B82F6; padding: 12px 16px; border-radius: 16px 0 16px 16px; font-size: 13px; color: #fff; line-height: 1.5;">
                            Good morning Sarah! How are you feeling after taking the prescribed beta-blockers?
                        </div>
                    </div>
                </div>

                <!-- Patient Message -->
                <div style="display: flex; gap: 12px; max-width: 85%;">
                    <img src="https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&q=80&w=100" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 4px;">
                            <span style="font-size: 13px; font-weight: 700; color: #1E293B;">Sarah Jenkins</span>
                            <span style="font-size: 11px; color: #94A3B8;">09:31 AM</span>
                        </div>
                        <div style="background: #F1F5F9; padding: 12px 16px; border-radius: 0 16px 16px 16px; font-size: 13px; color: #475569; line-height: 1.5;">
                            Hello Doctor! The chest tightness has reduced significantly, but I noticed mild dizziness in the morning.
                        </div>
                    </div>
                </div>

                <!-- Doctor Message (Self) -->
                <div style="display: flex; gap: 12px; max-width: 85%; align-self: flex-end; flex-direction: row-reverse;">
                    <img src="https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=crop&q=80&w=100" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 4px; flex-direction: row-reverse;">
                            <span style="font-size: 13px; font-weight: 700; color: #1E293B;">Dr. Helen Miller</span>
                            <span style="font-size: 11px; color: #94A3B8;">09:32 AM</span>
                        </div>
                        <div style="background: #3B82F6; padding: 12px 16px; border-radius: 16px 0 16px 16px; font-size: 13px; color: #fff; line-height: 1.5;">
                            That can happen initially. Let us review your daily blood pressure readings.
                        </div>
                    </div>
                </div>

            </div>

            <div style="padding: 16px; border-top: 1px solid #E2E8F0;">
                <div style="display: flex; gap: 12px;">
                    <input type="text" id="dior-doc-chat-input" placeholder="Type a message to the patient..." style="flex: 1; padding: 12px 16px; border: 1px solid #E2E8F0; border-radius: 8px; font-size: 13px; outline: none;" onkeypress="if(event.key === 'Enter') diorDocSendChatMessage()">
                    <button type="button" id="dior-doc-chat-send-btn" onclick="diorDocSendChatMessage()" style="background: #3B82F6; color: #fff; border: none; width: 44px; height: 44px; border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer;">
                        <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <script>
        function diorDocSendChatMessage() {
            var input = document.getElementById('dior-doc-chat-input');
            var container = document.getElementById('dior-doc-chat-container');
            if(!input || !container) return;
            var text = input.value.trim();
            if(!text) return;
            
            var now = new Date();
            var hours = now.getHours();
            var minutes = now.getMinutes();
            var ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12;
            minutes = minutes < 10 ? '0'+minutes : minutes;
            var timeString = hours + ':' + minutes + ' ' + ampm;
            
            var msgHtml = `
                <div style="display: flex; gap: 12px; max-width: 85%; align-self: flex-end; flex-direction: row-reverse; animation: fadeIn 0.3s ease;">
                    <img src="https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=crop&q=80&w=100" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 4px; flex-direction: row-reverse;">
                            <span style="font-size: 13px; font-weight: 700; color: #1E293B;"><?php echo esc_js($doctor['full_name'] ?? 'Doctor'); ?></span>
                            <span style="font-size: 11px; color: #94A3B8;">${timeString}</span>
                        </div>
                        <div style="background: #3B82F6; padding: 12px 16px; border-radius: 16px 0 16px 16px; font-size: 13px; color: #fff; line-height: 1.5;">
                            ${text.replace(/</g, "&lt;").replace(/>/g, "&gt;")}
                        </div>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', msgHtml);
            input.value = '';
            container.scrollTop = container.scrollHeight;
        }
    </script>
</section>
                <section class="dior-tab-panel" id="tab-doc-intake">
                    <div class="dior-content-pad">
                        <div class="dior-page-title-bar new-design">
                            <div class="title-left">
                                <div class="title-icon-box blue-tint" style="background:#E0F2FE; color:#0284C7; width:45px; height:45px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:20px;">
                                    <i class="fa-solid fa-file-shield"></i>
                                </div>
                                <div>
                                    <h1>Medical Intake Forms</h1>
                                    <p class="title-sub">Review all HIPAA-compliant patient submissions from HIPAAtizer.
                                    </p>
                                </div>
                            </div>
                            <div class="title-right">
                                <span style="background: #D1FAE5; color: #10B981; padding: 4px 10px; border-radius: 20px; font-size: 11.5px; font-weight: 700;" style="font-size:14px;"><i class="fa-solid fa-file-shield"></i>
                                    <?php echo count($intake_patients); ?> Submissions</span>
                            </div>
                        </div>
                        <?php if (empty($intake_patients)): ?>
                            <div class="dior-dash-table-card dior-box-card">
                                <div class="dior-empty-state" style="padding:48px;"><i class="fa-solid fa-file-shield"
                                        style="color:#CBD5E1;font-size:40px;margin-bottom:12px;"></i>
                                    <p>No intake forms submitted yet.</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(380px,1fr));gap:16px;">
                                <?php foreach ($patients as $p):
                                    if (!$p['has_intake'])
                                        continue;
                                    $intake = get_user_meta($p['user_id'], 'dior_hipaa_intake', true);
                                    if (empty($intake))
                                        continue;
                                    ?>
                                    <div class="dior-dash-table-card dior-box-card" style="border-left:4px solid #059669;">
                                        <div class="box-title-row" style="background:#ECFDF5;border-radius:12px 12px 0 0;">
                                            <div style="display:flex;align-items:center;gap:12px;">
                                                <div
                                                    style="background:linear-gradient(135deg,#D1FAE5,#A7F3D0);color:#059669;width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0;">
                                                    <?php echo esc_html($p['initials']); ?>
                                                </div>
                                                <div>
                                                    <strong
                                                        style="color:#1E293B;font-size:14px;"><?php echo esc_html($p['full_name']); ?></strong><br>
                                                    <small
                                                        style="color:#059669;"><?php echo esc_html($p['patient_id']); ?></small>
                                                </div>
                                            </div>
                                            <span style="background: #D1FAE5; color: #10B981; padding: 4px 10px; border-radius: 20px; font-size: 11.5px; font-weight: 700;"><i class="fa-solid fa-circle-check"></i>
                                                <?php echo esc_html(date('M j, Y', strtotime($intake['submitted_at'] ?? ''))); ?></span>
                                        </div>
                                        <div class="table-responsive">
                                            <div class="dior-form-row-2" style="margin-bottom:10px;">
                                                <div><small style="color:#64748B;font-weight:600;">DOB</small>
                                                    <div><?php echo esc_html($intake['dob'] ?: '�'); ?></div>
                                                </div>
                                                <div><small style="color:#64748B;font-weight:600;">Phone</small>
                                                    <div><?php echo esc_html($intake['phone'] ?: '�'); ?></div>
                                                </div>
                                            </div>
                                            <?php if (!empty($intake['symptoms'])): ?>
                                                <div style="margin-bottom:10px;">
                                                    <small style="color:#64748B;font-weight:600;">Symptoms</small>
                                                    <div style="background:#F8FAFC;padding:8px;border-radius:8px;font-size:13px;">
                                                        <?php echo esc_html($intake['symptoms']); ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($intake['allergies'])): ?>
                                                <div class="dior-form-row-2" style="margin-bottom:10px;">
                                                    <div><small style="color:#64748B;font-weight:600;">Allergies</small>
                                                        <div><?php echo esc_html($intake['allergies']); ?></div>
                                                    </div>
                                                    <div><small style="color:#64748B;font-weight:600;">Medications</small>
                                                        <div><?php echo esc_html($intake['medications'] ?: '�'); ?></div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($intake['medical_history'])): ?>
                                                <div style="margin-bottom:10px;">
                                                    <small style="color:#64748B;font-weight:600;">Medical History</small>
                                                    <div style="background:#F8FAFC;padding:8px;border-radius:8px;font-size:13px;">
                                                        <?php echo nl2br(esc_html($intake['medical_history'])); ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            <div style="margin-top:14px;">
                                                <button class="dior-btn-gold-primary" style="font-size:12px;padding:8px 16px;"
                                                    onclick="diorDocViewPatient(<?php echo (int) $p['user_id']; ?>)">
                                                    <i class="fa-solid fa-user-doctor"></i> Full Profile
                                                </button>
                                            </div>
                                        </div><!-- /card-body -->
                                    </div><!-- /card -->
                                <?php endforeach; ?>
                            </div><!-- /grid -->
                        <?php endif; ?>
                    </div><!-- /content-pad -->
                </section>




                <!-- -- PRESCRIPTIONS -- -->
                <section class="dior-tab-panel" id="tab-doc-prescriptions">
                    <div class="dior-content-pad">
                        <!-- Page Title Bar -->
                        <div class="dior-page-title-bar new-design">
                            <div class="title-left">
                                <div class="title-icon-box blue-tint" style="background:#E0F2FE; color:#0284C7; width:45px; height:45px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:20px;">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </div>
                                <div>
                                    <h1>Prescriptions</h1>
                                    <p class="title-sub">Issue and manage patient prescriptions and pharmacy routing.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Write New Prescription Card -->
                        <div
                            style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:16px;overflow:hidden;box-shadow:0 2px 12px rgba(11,16,48,0.04);margin-bottom:24px;">
                            <!-- Card Header -->
                            <div
                                style="padding:18px 24px;border-bottom:1px solid #F1F5F9;display:flex;align-items:center;gap:12px;background:#FAFBFC;">
                                <div
                                    style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#EBF3FA,#D0E1F9);display:flex;align-items:center;justify-content:center;color:#2C6CB1;flex-shrink:0;">
                                    <i class="fa-solid fa-pen-to-square" style="font-size:15px;line-height:1;"></i>
                                </div>
                                <div>
                                    <h3
                                        style="margin:0;font-size:15px;font-weight:700;color:#2C6CB1;font-family:'DMSans','DM Sans',sans-serif;">
                                        Write New Prescription</h3>
                                    <p style="margin:2px 0 0;font-size:12px;color:#64748B;">Issue an e-prescription
                                        using FDA/RxNorm approved medications.</p>
                                </div>
                            </div>
                            <!-- Card Body -->
                            <div style="padding:24px;">
                                <form id="dior-doc-rx-form" onsubmit="return false;">
                                    <!-- Patient Selector -->
                                    <div style="margin-bottom:20px;">
                                        <label
                                            style="display:block;font-size:12px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:7px;font-family:'Montserrat',sans-serif;">Patient
                                            <span style="color:#DC2626;">*</span></label>
                                        <select id="rx-patient-select" required
                                            style="width:100%;padding:11px 14px;border:1.5px solid #E2E8F0;border-radius:10px;font-size:13.5px;font-family:'DMSans','DM Sans',sans-serif;color:#0F172A;background:#FFFFFF;outline:none;cursor:pointer;box-sizing:border-box;">
                                            <option value="">� Select Patient �</option>
                                            <?php foreach ($patients as $p): ?>
                                                <option value="<?php echo (int) $p['user_id']; ?>">
                                                    <?php echo esc_html($p['full_name'] . ' (' . $p['patient_id'] . ')'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <!-- Add Medication Section -->
                                    <div
                                        style="background:#F8FAFC;border:1.5px solid #E2E8F0;border-radius:12px;padding:20px;margin-bottom:20px;">
                                        <div
                                            style="display:flex;align-items:center;gap:10px;margin-bottom:18px;padding-bottom:14px;border-bottom:1px solid #E9EFF6;">
                                            <div
                                                style="width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,#EBF3FA,#D0E1F9);display:flex;align-items:center;justify-content:center;color:#2C6CB1;">
                                                <i class="fa-solid fa-pills" style="font-size:14px;line-height:1;"></i>
                                            </div>
                                            <h4
                                                style="margin:0;font-size:14px;font-weight:700;color:#0F172A;font-family:'DMSans','DM Sans',sans-serif;">
                                                Add Medication</h4>
                                        </div>

                                        <!-- Drug Search -->
                                        <div style="position:relative;margin-bottom:16px;">
                                            <div
                                                style="display:flex;align-items:center;justify-content:space-between;margin-bottom:7px;">
                                                <label
                                                    style="display:block;font-size:12px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:0;font-family:'Montserrat',sans-serif;">Search
                                                    FDA / RxNorm Approved Medications <span
                                                        style="color:#DC2626;">*</span></label>
                                                <span
                                                    style="display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:700;color:#059669;background:#ECFDF5;border:1px solid #A7F3D0;padding:2px 8px;border-radius:12px;">
                                                    <i class="fa-solid fa-shield-halved"></i> Official U.S. Govt API
                                                </span>
                                            </div>
                                            <div style="position:relative;">
                                                <input type="text" id="rx-med-search"
                                                    placeholder="Type drug name (e.g. Amoxicillin, Metformin, Lisinopril)..."
                                                    autocomplete="off" oninput="diorDocSearchMedication(this.value)"
                                                    style="width:100%;padding:11px 38px 11px 14px !important;border:1.5px solid #E2E8F0;border-radius:10px;font-size:13.5px;font-family:'DMSans','DM Sans',sans-serif;color:#0F172A;background:#FFFFFF;outline:none;box-sizing:border-box;">
                                                <i class="fa-solid fa-magnifying-glass"
                                                    style="position:absolute;right:14px;left:auto;top:50%;transform:translateY(-50%);color:#94A3B8;font-size:13px;pointer-events:none;"></i>
                                            </div>
                                            <div id="rx-med-autocomplete"
                                                style="display:none;position:absolute;z-index:100;background:#fff;width:100%;border:1.5px solid #BFDBFE;border-radius:10px;max-height:260px;overflow-y:auto;box-shadow:0 8px 24px rgba(44,108,177,0.12);margin-top:4px;">
                                            </div>
                                            <div id="rx-fda-badge" style="display:none;margin-top:8px;"></div>
                                        </div>

                                        <!-- Dosage + Refills -->
                                        <div
                                            style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                                            <div>
                                                <label
                                                    style="display:block;font-size:12px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:7px;font-family:'Montserrat',sans-serif;">FDA
                                                    Dosage
                                                    &amp; Frequency <span style="color:#DC2626;">*</span></label>
                                                <input type="text" id="rx-dosage" list="rx-dosage-list"
                                                    placeholder="e.g. 500 mg Tab � 1 tab daily"
                                                    style="width:100%;padding:11px 14px;border:1.5px solid #E2E8F0;border-radius:10px;font-size:13.5px;font-family:'DMSans','DM Sans',sans-serif;color:#0F172A;background:#FFFFFF;outline:none;box-sizing:border-box;">
                                                <datalist id="rx-dosage-list"></datalist>
                                                <small
                                                    style="display:block;color:#64748B;font-size:11px;margin-top:4px;">Official
                                                    FDA strengths auto-populated on drug selection</small>
                                            </div>
                                            <div>
                                                <label
                                                    style="display:block;font-size:12px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:7px;font-family:'Montserrat',sans-serif;">Refills</label>
                                                <input type="text" id="rx-refills" placeholder="e.g. 0 refills"
                                                    style="width:100%;padding:11px 14px;border:1.5px solid #E2E8F0;border-radius:10px;font-size:13.5px;font-family:'DMSans','DM Sans',sans-serif;color:#0F172A;background:#FFFFFF;outline:none;box-sizing:border-box;">
                                            </div>
                                        </div>

                                        <!-- Clinical Notes -->
                                        <div style="margin-bottom:16px;">
                                            <label
                                                style="display:block;font-size:12px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:7px;font-family:'Montserrat',sans-serif;">Clinical
                                                Notes</label>
                                            <textarea id="rx-notes" rows="3"
                                                placeholder="Take with food. Avoid alcohol. Complete full course..."
                                                style="width:100%;padding:11px 14px;border:1.5px solid #E2E8F0;border-radius:10px;font-size:13.5px;font-family:'DMSans','DM Sans',sans-serif;color:#0F172A;background:#FFFFFF;outline:none;box-sizing:border-box;resize:vertical;"></textarea>
                                        </div>

                                        <button type="button" onclick="diorDocAddMedToList()"
                                            style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;border-radius:10px;font-size:13px;font-weight:700;font-family:'Montserrat',sans-serif;background:#FFFFFF;color:#2C6CB1;border:1.5px solid #BFDBFE;cursor:pointer;">
                                            <i class="fa-solid fa-plus"></i> Add to Prescription
                                        </button>
                                    </div>

                                    <!-- Medication List Output -->
                                    <div id="rx-med-list" style="margin-bottom:20px;"></div>

                                    <!-- Review & Issue Prescription Button (Opens Confirmation Preview Modal) -->
                                    <button type="button" id="btn-send-rx"
                                        onclick="diorDocPreviewPrescriptionConfirmation()"
                                        style="display:inline-flex;align-items:center;gap:8px;padding:12px 26px;border-radius:10px;font-size:14px;font-weight:700;font-family:'Montserrat',sans-serif;background:linear-gradient(135deg,#00A896,#0284C7);color:#FFFFFF;border:none;cursor:pointer;box-shadow:0 4px 16px rgba(0,168,150,0.28);transition:all 0.2s ease;">
                                        <i class="fa-solid fa-file-prescription"></i> Review & Issue Prescription
                                    </button>
                                </form>
                                <div id="dior-rx-msg"
                                    style="display:none;margin-top:16px;padding:14px 18px;border-radius:10px;font-size:13.5px;font-weight:600;font-family:'DMSans','DM Sans',sans-serif;">
                                </div>
                            </div>
                        </div>

                        <!-- All Issued Prescriptions Card -->
                        <div
                            style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:16px;overflow:hidden;box-shadow:0 2px 12px rgba(11,16,48,0.04);">
                            <div
                                style="padding:18px 24px;border-bottom:1px solid #F1F5F9;display:flex;align-items:center;gap:12px;background:#FAFBFC;">
                                <div
                                    style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#EBF3FA,#D0E1F9);display:flex;align-items:center;justify-content:center;color:#2C6CB1;flex-shrink:0;">
                                    <i class="fa-solid fa-prescription-bottle-medical"
                                        style="font-size:15px;line-height:1;"></i>
                                </div>
                                <h3
                                    style="margin:0;font-size:15px;font-weight:700;color:#2C6CB1;font-family:'DMSans','DM Sans',sans-serif;">
                                    All Issued Prescriptions</h3>
                            </div>
                            <div class="table-responsive" style="padding:16px 24px 24px 24px;overflow-x:auto;">
                                <?php
                                $all_rx = [];
                                foreach ($patients as $p) {
                                    $raw_rxs = get_user_meta($p['user_id'], 'dior_prescriptions', true);
                                    if (!is_array($raw_rxs))
                                        continue;
                                    $grouped_rxs = class_exists('Dior_Patient_Portal_Data') ? Dior_Patient_Portal_Data::group_prescriptions($raw_rxs) : $raw_rxs;
                                    foreach ($grouped_rxs as $r) {
                                        $r['patient_name'] = $p['full_name'];
                                        $all_rx[] = $r;
                                    }
                                }
                                ?>
                                <table class="dior-clean-table" id="dior-all-rx-table">
                                    <thead>
                                        <tr>
                                            <th>Patient</th>
                                            <th>Medication</th>
                                            <th>Dosage</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="dior-all-rx-tbody">
                                        <?php if (empty($all_rx)): ?>
                                            <tr>
                                                <td colspan="5"
                                                    style="text-align:center;padding:32px;color:#94A3B8;font-size:13.5px;">
                                                    No prescriptions issued yet.</td>
                                            </tr>
                                        <?php else:
                                            foreach ($all_rx as $r): ?>
                                                <tr>
                                                    <td><strong><?php echo esc_html($r['patient_name'] ?? '�'); ?></strong></td>
                                                    <td>
                                                        <?php if (!empty($r['items']) && count($r['items']) > 1): ?>
                                                            <div style="display:flex;flex-direction:column;gap:5px;">
                                                                <span style="font-size:11px;color:#2C6CB1;font-weight:700;">Rx
                                                                    #<?php echo esc_html($r['id']); ?>
                                                                    (<?php echo count($r['items']); ?> Meds)</span>
                                                                <?php foreach ($r['items'] as $it): ?>
                                                                    <div
                                                                        style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                                                                        <span
                                                                            style="font-size:12.5px;color:#0F172A;font-weight:600;"><?php echo esc_html($it['medication']); ?></span>
                                                                        <span
                                                                            style="display:inline-flex;align-items:center;font-size:9.5px;font-weight:700;color:#059669;background:#ECFDF5;border:1px solid #A7F3D0;padding:1px 6px;border-radius:4px;letter-spacing:0.3px;text-transform:uppercase;">
                                                                            FDA Approved
                                                                        </span>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                                                                <strong><?php echo esc_html($r['medication'] ?? $r['name'] ?? '�'); ?></strong>
                                                                <span
                                                                    style="display:inline-flex;align-items:center;font-size:9.5px;font-weight:700;color:#059669;background:#ECFDF5;border:1px solid #A7F3D0;padding:1px 6px;border-radius:4px;letter-spacing:0.3px;text-transform:uppercase;">
                                                                    FDA Approved
                                                                </span>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($r['items']) && count($r['items']) > 1): ?>
                                                            <div style="display:flex;flex-direction:column;gap:3px;">
                                                                <?php foreach ($r['items'] as $it): ?>
                                                                    <span
                                                                        style="font-size:12px;color:#475569;"><?php echo esc_html($it['dosage']); ?></span>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <?php echo esc_html($r['dosage'] ?? $r['dose'] ?? '�'); ?>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo esc_html($r['date'] ?? '�'); ?></td>
                                                    <td><span
                                                            style="background: #D1FAE5; color: #10B981; padding: 4px 10px; border-radius: 20px; font-size: 11.5px; font-weight: 700;"><?php echo esc_html($r['status'] ?? 'Active'); ?></span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- -- PAYMENTS -- -->
                <section class="dior-tab-panel" id="tab-doc-payments">
                    <div class="dior-content-pad">
                        <div class="dior-page-title-bar new-design">
                            <div class="title-left">
                                <div class="title-icon-box blue-tint" style="background:#E0F2FE; color:#0284C7; width:45px; height:45px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:20px;">
                                    <i class="fa-solid fa-credit-card"></i>
                                </div>
                                <div>
                                    <h1>Payment Records</h1>
                                    <p class="title-sub">All patient payment transactions via Stripe.</p>
                                </div>
                            </div>
                        </div>
                        <div class="dior-dash-table-card dior-box-card">
                            <div class="box-title-row">
                                <h3><i class="fa-solid fa-credit-card"></i> All Payments</h3>
                            </div>
                            <div class="table-responsive" style="padding:16px 24px 24px 24px;overflow-x:auto;">
                                <?php
                                $all_pays = [];
                                foreach ($patients as $p) {
                                    $pays = get_user_meta($p['user_id'], 'dior_payments', true);
                                    if (!is_array($pays))
                                        continue;
                                    foreach ($pays as $pay) {
                                        $pay['patient_name'] = $p['full_name'];
                                        $all_pays[] = $pay;
                                    }
                                }
                                ?>
                                <table class="dior-clean-table" id="dior-payments-table">
                                    <thead>
                                        <tr>
                                            <th>Patient</th>
                                            <th>Invoice</th>
                                            <th>Amount</th>
                                            <th>Date</th>
                                            <th>Method</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($all_pays)): ?>
                                            <tr>
                                                <td colspan="6" style="text-align:center;padding:24px;color:#94A3B8;">No
                                                    payments found.</td>
                                            </tr>
                                        <?php else:
                                            foreach ($all_pays as $pay): ?>
                                                <tr>
                                                    <td><strong><?php echo esc_html($pay['patient_name'] ?? '�'); ?></strong>
                                                    </td>
                                                    <td><code
                                                            style="font-size:12px;"><?php echo esc_html($pay['id'] ?? '�'); ?></code>
                                                    </td>
                                                    <td style="font-weight:700;color:#059669;">
                                                        <?php echo esc_html($pay['amount'] ?? '�'); ?>
                                                    </td>
                                                    <td><?php echo esc_html($pay['date'] ?? '�'); ?></td>
                                                    <td><?php echo esc_html($pay['method'] ?? 'Stripe'); ?></td>
                                                    <td><span
                                                            class="dior-st <?php echo strtolower($pay['status'] ?? '') === 'paid' ? 'ok' : 'pending'; ?>"><?php echo esc_html($pay['status'] ?? '�'); ?></span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- -- NOTIFICATIONS -- -->
                <section class="dior-tab-panel" id="tab-doc-notifications">
                    <div class="dior-content-pad">
                        <div class="dior-page-header-box">
                            <div>
                                <h2>Provider Notification Inbox</h2>
                                <p>Stay updated with new patient registrations, appointments, intake submissions, and
                                    system alerts.</p>
                            </div>
                            <button type="button" class="dior-btn-gold-secondary" onclick="diorDocMarkAllRead()">
                                <i class="fa-solid fa-check-double"></i> Mark All as Read
                            </button>
                        </div>

                        <!-- Provider Reminder Preferences Banner -->
                        <div class="dior-dash-table-card dior-box-card"
                            style="margin-bottom: 20px; border-left: 4px solid #2C6CB1; border-radius: 12px;">
                            <div class="table-responsive"
                                style="padding: 16px 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
                                <div style="display: flex; align-items: center; gap: 14px;">
                                    <div
                                        style="width: 44px; height: 44px; border-radius: 12px; background: #EBF3FA; color: #2C6CB1; display: flex; align-items: center; justify-content: center; font-size: 19px; flex-shrink: 0;">
                                        <i class="fa-solid fa-bell"></i>
                                    </div>
                                    <div>
                                        <strong style="font-size: 14px; color: #0F172A; display: block;">
                                            Appointment Reminders:
                                            <?php if ($doctor['optin_appointment_reminders'] === '1'): ?>
                                                <span style="color:#059669; font-weight:700;"><i
                                                        class="fa-solid fa-circle-check"></i> Active &amp; Opted-In</span>
                                            <?php else: ?>
                                                <span style="color:#DC2626; font-weight:700;"><i
                                                        class="fa-solid fa-circle-xmark"></i> Paused</span>
                                            <?php endif; ?>
                                        </strong>
                                        <span
                                            style="font-size: 12.5px; color: #64748B; line-height: 1.4; display: block; margin-top: 2px;">
                                            Dual delivery active: Reminders are dispatched to both provider (email &amp;
                                            inbox) and patient before each scheduled visit.
                                        </span>
                                    </div>
                                </div>
                                <div>
                                    <button type="button" class="dior-btn-gold-secondary"
                                        onclick="diorDocSwitchTab('tab-doc-settings');"
                                        style="padding: 8px 16px; font-size: 13px;">
                                        <i class="fa-solid fa-sliders"></i> Provider Settings
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="dior-dash-table-card dior-box-card">
                            <div class="dior-card-body p-0">
                                <div class="dior-notifs-full-list" id="dior-doc-notif-full">
                                    <?php if (empty($notifications)): ?>
                                        <div
                                            style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:64px 24px;text-align:center;">
                                            <div
                                                style="width:64px;height:64px;border-radius:16px;background:#F1F5F9;display:flex;align-items:center;justify-content:center;margin-bottom:16px;">
                                                <i class="fa-regular fa-bell-slash"
                                                    style="font-size:26px;color:#CBD5E1;"></i>
                                            </div>
                                            <p
                                                style="font-size:15px;color:#94A3B8;margin:0;font-weight:600;font-family:'DMSans','DM Sans',sans-serif;">
                                                No notifications yet</p>
                                            <p style="font-size:13px;color:#CBD5E1;margin:6px 0 0;">You'll see new alerts
                                                and updates here.</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($notifications as $n): ?>
                                            <div class="dior-full-notif-item <?php echo !$n['is_read'] ? 'unread' : ''; ?>"
                                                data-notif-id="<?php echo esc_attr($n['id']); ?>">
                                                <div class="notif-type-icon <?php echo esc_attr($n['type'] ?? 'system'); ?>">
                                                    <i class="fa-solid <?php echo esc_attr($n['icon'] ?? 'fa-bell'); ?>"></i>
                                                </div>
                                                <div class="notif-full-body">
                                                    <div class="notif-full-top">
                                                        <h4><?php echo esc_html($n['title']); ?></h4>
                                                        <span
                                                            class="notif-full-time"><?php echo esc_html(dior_format_notification_time($n)); ?></span>
                                                    </div>
                                                    <p><?php echo esc_html($n['message']); ?></p>
                                                </div>
                                                <div class="notif-full-actions">
                                                    <?php if (!$n['is_read']): ?>
                                                        <button type="button" class="dior-btn-table-icon dior-mark-single-read"
                                                            title="Mark as Read"
                                                            onclick="diorDocMarkSingleRead('<?php echo esc_js($n['id']); ?>', this)">
                                                            <i class="fa-solid fa-check"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>


                <!-- -- MEDICAL RECORDS & CLINICAL FILES -- -->
                <section class="dior-tab-panel" id="tab-doc-records">
                    <div class="dior-content-pad">
                        <div class="dior-page-title-bar new-design">
                            <div class="title-left">
                                <div class="title-icon-box"
                                    style="background:linear-gradient(135deg,#2C6CB1,#1A528E);color:#FFFFFF;">
                                    <i class="fa-solid fa-folder-open"></i>
                                </div>
                                <div>
                                    <h1>Medical Records & Clinical Files</h1>
                                    <p class="title-sub">Secure HIPAA-compliant vault of all patient lab reports,
                                        imaging, excuses, and signed consent records.</p>
                                </div>
                            </div>
                            <div class="title-right" style="display:flex;gap:10px;align-items:center;">
                                <button type="button" class="dior-btn-sm dior-btn-ghost"
                                    onclick="diorDocOpenUploadModal()"
                                    style="height:36px;padding:0 16px;font-size:13px;">
                                    <i class="fa-solid fa-cloud-arrow-up" style="color:#2C6CB1;"></i>
                                    <span>Upload Document</span>
                                </button>
                                <button type="button" class="dior-btn-sm dior-btn-purple"
                                    onclick="diorDocSwitchTab('doc-letters')"
                                    style="height:36px;padding:0 16px;font-size:13px;">
                                    <i class="fa-solid fa-file-signature"></i>
                                    <span>Issue Work Excuse</span>
                                </button>
                            </div>
                        </div>

                        <!-- Records Table Card -->
                        <div class="dior-dash-table-card dior-box-card">
                            <div class="box-title-row"
                                style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;">
                                <div class="dior-filter-pill-group" id="doc-records-filter-pills">
                                    <button type="button" class="dior-filter-pill active"
                                        onclick="diorDocFilterCategory('all', this)">
                                        All Documents <span class="pill-count"><?php echo count($documents); ?></span>
                                    </button>
                                    <button type="button" class="dior-filter-pill"
                                        onclick="diorDocFilterCategory('consent', this)">
                                        <i class="fa-solid fa-shield-halved" style="color:#2C6CB1;"></i> Consents
                                    </button>
                                    <button type="button" class="dior-filter-pill"
                                        onclick="diorDocFilterCategory('excuse', this)">
                                        <i class="fa-solid fa-file-signature" style="color:#7C3AED;"></i> Excuses &
                                        Letters
                                    </button>
                                    <button type="button" class="dior-filter-pill"
                                        onclick="diorDocFilterCategory('lab', this)">
                                        <i class="fa-solid fa-flask-vial" style="color:#059669;"></i> Lab Reports
                                    </button>
                                    <button type="button" class="dior-filter-pill"
                                        onclick="diorDocFilterCategory('clinical', this)">
                                        <i class="fa-solid fa-notes-medical" style="color:#64748B;"></i> Clinical Notes
                                    </button>
                                </div>
                                <div style="display:flex;gap:8px;align-items:center;">
                                    <input type="text" class="dior-search-input"
                                        placeholder="Search records or patients�"
                                        oninput="diorDocFilterRecordsTable(this.value)"
                                        style="width:240px;height:34px;font-size:12.5px;">
                                </div>
                            </div>
                            <div class="dior-card-body p-0" style="overflow-x:auto;">
                                <table class="dior-clean-table" id="dior-doc-records-table" style="width:100%;">
                                    <thead>
                                        <tr>
                                            <th>Patient</th>
                                            <th>Document Title</th>
                                            <th>Category</th>
                                            <th>Date Issued</th>
                                            <th>Author</th>
                                            <th>Format</th>
                                            <th style="text-align:right;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($documents)): ?>
                                            <?php foreach ($documents as $doc):
                                                $cat_raw = $doc['category'] ?? 'Record';
                                                $cat_lower = strtolower($cat_raw);
                                                $badge_class = 'dior-badge-blue';
                                                $cat_icon = 'fa-file-lines';
                                                $data_cat = 'general';
                                                $doc_icon_color = 'blue';

                                                if (strpos($cat_lower, 'lab') !== false || strpos($cat_lower, 'result') !== false) {
                                                    $badge_class = 'dior-badge-emerald';
                                                    $cat_icon = 'fa-flask-vial';
                                                    $data_cat = 'lab';
                                                    $doc_icon_color = 'emerald';
                                                } elseif (strpos($cat_lower, 'letter') !== false || strpos($cat_lower, 'excuse') !== false || strpos($cat_lower, 'certificate') !== false) {
                                                    $badge_class = 'dior-badge-purple';
                                                    $cat_icon = 'fa-file-signature';
                                                    $data_cat = 'excuse';
                                                    $doc_icon_color = 'purple';
                                                } elseif (strpos($cat_lower, 'consent') !== false || strpos($cat_lower, 'hipaa') !== false) {
                                                    $badge_class = 'dior-badge-blue';
                                                    $cat_icon = 'fa-shield-halved';
                                                    $data_cat = 'consent';
                                                    $doc_icon_color = 'blue';
                                                } elseif (strpos($cat_lower, 'imaging') !== false || strpos($cat_lower, 'x-ray') !== false) {
                                                    $badge_class = 'dior-badge-amber';
                                                    $cat_icon = 'fa-x-ray';
                                                    $data_cat = 'imaging';
                                                    $doc_icon_color = 'amber';
                                                } else {
                                                    $badge_class = 'dior-badge-slate';
                                                    $cat_icon = 'fa-notes-medical';
                                                    $data_cat = 'clinical';
                                                    $doc_icon_color = 'slate';
                                                }

                                                $pid_val = (int) ($doc['patient_user_id'] ?? 0);
                                                $doc_id_val = $doc['id'] ?? '';
                                                $stream_url = add_query_arg(['dior_action' => 'download_doc', 'patient_id' => $pid_val, 'doc_id' => $doc_id_val], home_url('/'));
                                                ?>
                                                <tr data-cat="<?php echo esc_attr($data_cat); ?>"
                                                    data-search="<?php echo esc_attr(strtolower(($doc['patient_name'] ?? '') . ' ' . ($doc['title'] ?? '') . ' ' . ($doc['patient_id_num'] ?? '') . ' ' . $cat_raw)); ?>">
                                                    <td>
                                                        <div style="font-weight:700;color:#0F172A;font-size:13.5px;">
                                                            <?php echo esc_html($doc['patient_name']); ?></div>
                                                        <code
                                                            style="background:#F1F5F9;padding:2px 6px;border-radius:4px;font-size:11px;color:#2C6CB1;font-weight:600;"><?php echo esc_html($doc['patient_id_num']); ?></code>
                                                    </td>
                                                    <td>
                                                        <div class="dior-doc-cell">
                                                            <div class="dior-doc-icon-wrap <?php echo $doc_icon_color; ?>">
                                                                <i class="fa-regular fa-file-pdf"></i>
                                                            </div>
                                                            <div>
                                                                <strong
                                                                    style="color:#0F172A;font-size:13.5px;display:block;"><?php echo esc_html($doc['title']); ?></strong>
                                                                <span style="font-size:11px;color:#94A3B8;">ID:
                                                                    <?php echo esc_html($doc['id']); ?></span>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="dior-badge-pill <?php echo $badge_class; ?>">
                                                            <i class="fa-solid <?php echo $cat_icon; ?>"></i>
                                                            <?php echo esc_html($cat_raw); ?>
                                                        </span>
                                                    </td>
                                                    <td style="color:#64748B;font-size:12.5px;">
                                                        <?php echo esc_html($doc['date']); ?></td>
                                                    <td style="color:#475569;font-size:12.5px;font-weight:500;">
                                                        <?php echo esc_html($doc['author']); ?></td>
                                                    <td><strong
                                                            style="color:#0F172A;font-size:12px;"><?php echo esc_html($doc['file_type'] ?? 'PDF'); ?></strong>
                                                    </td>
                                                    <td style="text-align:right;">
                                                        <div
                                                            style="display:flex;align-items:center;justify-content:flex-end;gap:6px;">
                                                            <a href="<?php echo esc_url($stream_url); ?>" target="_blank"
                                                                class="dior-btn-sm dior-btn-primary"
                                                                title="View Document in Viewer">
                                                                <i class="fa-solid fa-eye"></i> View
                                                            </a>
                                                            <button type="button" class="dior-btn-sm dior-btn-ghost"
                                                                onclick="diorDocViewPatient(<?php echo $pid_val; ?>)"
                                                                title="Open Patient Chart">
                                                                <i class="fa-solid fa-folder-open"></i> Chart
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" style="text-align:center;padding:50px 20px;color:#94A3B8;">
                                                    <i class="fa-solid fa-folder-open fa-3x"
                                                        style="display:block;margin-bottom:12px;opacity:0.3;color:#2C6CB1;"></i>
                                                    <strong
                                                        style="font-size:15px;color:#334155;display:block;margin-bottom:4px;">No
                                                        Medical Records on File</strong>
                                                    <span style="font-size:13px;">Upload lab reports, clinical encounter
                                                        summaries, or issue medical excuse letters.</span>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- -- WORK EXCUSE & MEDICAL LETTERS STUDIO -- -->
                <section class="dior-tab-panel" id="tab-doc-letters">
                    <div class="dior-content-pad">
                        <div class="dior-page-title-bar new-design">
                            <div class="title-left">
                                <div class="title-icon-box"
                                    style="background:linear-gradient(135deg,#7C3AED,#6D28D9);color:#FFFFFF;">
                                    <i class="fa-solid fa-file-signature"></i>
                                </div>
                                <div>
                                    <h1>Work Excuse & Medical Letter Generator</h1>
                                    <p class="title-sub">Issue certified medical absence notes, school excuses, and
                                        fit-to-work clearance certificates directly to patients.</p>
                                </div>
                            </div>
                        </div>

                        <div style="display:grid;grid-template-columns: 1.15fr 1fr;gap:24px;align-items:start;">

                            <!-- Generator Form Card -->
                            <div class="dior-dash-table-card dior-box-card">
                                <div class="box-title-row">
                                    <h3><i class="fa-solid fa-pen-nib" style="color:#7C3AED;"></i> Letter Configuration
                                    </h3>
                                </div>
                                <div class="table-responsive" style="padding:22px;">
                                    <form id="doc-standalone-letter-form"
                                        onsubmit="diorDocSubmitStandaloneLetter(event)">

                                        <div style="margin-bottom:16px;">
                                            <label
                                                style="display:block;font-size:11.5px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:6px;">Select
                                                Patient <span style="color:#DC2626;">*</span></label>
                                            <select id="doc_gen_patient_select" required
                                                onchange="diorDocUpdateStandaloneLetterPreview()"
                                                style="width:100%;padding:10px 14px;border:1.5px solid #CBD5E1;border-radius:8px;font-size:13.5px;background:#FFF;color:#0F172A;font-weight:600;outline:none;">
                                                <option value="">-- Choose a patient --</option>
                                                <?php foreach ($patients as $p): ?>
                                                    <option value="<?php echo (int) $p['user_id']; ?>"
                                                        data-name="<?php echo esc_attr($p['full_name']); ?>"
                                                        data-dob="<?php echo esc_attr($p['dob']); ?>"
                                                        data-pid="<?php echo esc_attr($p['patient_id']); ?>">
                                                        <?php echo esc_html($p['full_name']); ?>
                                                        (<?php echo esc_html($p['patient_id']); ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div
                                            style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px;">
                                            <div>
                                                <label
                                                    style="display:block;font-size:11.5px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:6px;">Letter
                                                    Type</label>
                                                <select id="doc_gen_letter_type"
                                                    onchange="diorDocUpdateStandaloneLetterPreview()"
                                                    style="width:100%;padding:10px 12px;border:1.5px solid #CBD5E1;border-radius:8px;font-size:13px;background:#FFF;color:#0F172A;outline:none;">
                                                    <option value="Work Excuse">Work Absence Excuse Note</option>
                                                    <option value="School / College Excuse">School / University Excuse
                                                        Note</option>
                                                    <option value="Fit-to-Work Clearance">Fit-to-Work / Medical
                                                        Clearance</option>
                                                    <option value="Specialist Referral Letter">Specialist Referral
                                                        Attestation</option>
                                                    <option value="Custom Medical Letter">Custom Medical Attestation
                                                    </option>
                                                </select>
                                            </div>
                                            <div>
                                                <label
                                                    style="display:block;font-size:11.5px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:6px;">Diagnosed
                                                    Condition / Reason</label>
                                                <input type="text" id="doc_gen_condition"
                                                    placeholder="e.g. Acute Upper Respiratory Illness" value=""
                                                    oninput="diorDocUpdateStandaloneLetterPreview()"
                                                    style="width:100%;padding:10px 12px;border:1.5px solid #CBD5E1;border-radius:8px;font-size:13px;box-sizing:border-box;outline:none;">
                                            </div>
                                        </div>

                                        <!-- Quick Clinical Condition Preset Chips -->
                                        <div style="margin-bottom:16px;">
                                            <span
                                                style="font-size:11px;font-weight:700;color:#64748B;display:block;margin-bottom:6px;text-transform:uppercase;">Quick
                                                Presets:</span>
                                            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                                <button type="button" class="dior-filter-pill"
                                                    onclick="diorDocApplyLetterPreset('Acute Upper Respiratory Infection', 'Full Rest / Excused from all duties', 'Patient has been clinically evaluated via telehealth. Patient is advised adequate rest and hydration.')">Acute
                                                    URI</button>
                                                <button type="button" class="dior-filter-pill"
                                                    onclick="diorDocApplyLetterPreset('Acute Sinusitis & Cephalea', 'Full Rest / Excused from all duties', 'Patient was treated for acute sinusitis symptoms. Excused from active workplace duties.')">Sinusitis</button>
                                                <button type="button" class="dior-filter-pill"
                                                    onclick="diorDocApplyLetterPreset('Acute Gastroenteritis', 'Full Rest / Excused from all duties', 'Patient presented with acute gastrointestinal symptoms. Advised bed rest and oral rehydration.')">Gastroenteritis</button>
                                                <button type="button" class="dior-filter-pill"
                                                    onclick="diorDocApplyLetterPreset('Medical Clearance / Follow-up Complete', 'Full clearance / Return without restrictions', 'Patient has satisfactorily recovered from acute condition and is cleared to resume full normal duties.')">Fit
                                                    for Work</button>
                                            </div>
                                        </div>

                                        <div
                                            style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px;">
                                            <div>
                                                <label
                                                    style="display:block;font-size:11.5px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:6px;">Excused
                                                    From (Start Date)</label>
                                                <input type="date" id="doc_gen_start_date"
                                                    value="<?php echo date('Y-m-d'); ?>"
                                                    onchange="diorDocUpdateStandaloneLetterPreview()"
                                                    style="width:100%;padding:9px 12px;border:1.5px solid #CBD5E1;border-radius:8px;font-size:13px;box-sizing:border-box;outline:none;">
                                            </div>
                                            <div>
                                                <label
                                                    style="display:block;font-size:11.5px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:6px;">Expected
                                                    Return Date</label>
                                                <input type="date" id="doc_gen_return_date"
                                                    value="<?php echo date('Y-m-d', strtotime('+3 days')); ?>"
                                                    onchange="diorDocUpdateStandaloneLetterPreview()"
                                                    style="width:100%;padding:9px 12px;border:1.5px solid #CBD5E1;border-radius:8px;font-size:13px;box-sizing:border-box;outline:none;">
                                            </div>
                                        </div>

                                        <div style="margin-bottom:16px;">
                                            <label
                                                style="display:block;font-size:11.5px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:6px;">Activity
                                                / Duty Restrictions</label>
                                            <select id="doc_gen_restrictions"
                                                onchange="diorDocUpdateStandaloneLetterPreview()"
                                                style="width:100%;padding:10px 12px;border:1.5px solid #CBD5E1;border-radius:8px;font-size:13px;background:#FFF;color:#0F172A;outline:none;">
                                                <option value="Full Rest / Excused from all duties">Full Rest / Excused
                                                    from all duties</option>
                                                <option value="Light duty only (no heavy lifting > 10 lbs)">Light duty
                                                    only (no heavy lifting > 10 lbs)</option>
                                                <option value="Modified hours / Remote work permitted">Modified hours /
                                                    Remote work permitted</option>
                                                <option value="Full clearance / Return without restrictions">Full
                                                    clearance / Return without restrictions</option>
                                                <option value="Exempt from physical education / sports">Exempt from
                                                    physical education / sports</option>
                                            </select>
                                        </div>

                                        <div style="margin-bottom:20px;">
                                            <label
                                                style="display:block;font-size:11.5px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:6px;">Physician
                                                Remarks & Clinical Instructions</label>
                                            <textarea id="doc_gen_remarks" rows="3"
                                                placeholder="Patient has been clinically assessed via telehealth. Patient is advised adequate rest and hydration..."
                                                oninput="diorDocUpdateStandaloneLetterPreview()"
                                                style="width:100%;padding:10px 12px;border:1.5px solid #CBD5E1;border-radius:8px;font-size:13px;font-family:inherit;box-sizing:border-box;outline:none;"></textarea>
                                        </div>

                                        <div style="display:flex;gap:10px;justify-content:flex-end;">
                                            <button type="button" class="dior-btn-sm dior-btn-ghost"
                                                onclick="diorDocPrintStandalonePreview()"
                                                style="height:38px;padding:0 18px;font-size:13px;">
                                                <i class="fa-solid fa-print"></i> Print / Save PDF
                                            </button>
                                            <button type="submit" id="doc-gen-submit-btn"
                                                class="dior-btn-sm dior-btn-purple"
                                                style="height:38px;padding:0 22px;font-size:13px;">
                                                <i class="fa-solid fa-paper-plane"></i> Publish to Patient Portal
                                            </button>
                                        </div>

                                    </form>
                                </div>
                            </div>

                            <!-- Live Preview Card -->
                            <div class="dior-dash-table-card dior-box-card">
                                <div class="box-title-row">
                                    <h3><i class="fa-solid fa-certificate" style="color:#2C6CB1;"></i> Certified
                                        Letterhead Preview</h3>
                                </div>
                                <div class="table-responsive" style="padding:22px;">
                                    <div id="doc-standalone-letter-preview-box"
                                        style="background:#FFFFFF;border:2.5px solid #00A896;border-radius:12px;padding:32px;box-shadow:0 4px 20px rgba(0,168,150,0.08);position:relative;overflow:hidden;">
                                        <!-- Centered Caduceus Watermark -->
                                        <div class="dior-letter-watermark"
                                            style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:280px;height:280px;opacity:0.045;pointer-events:none;z-index:0;display:flex;align-items:center;justify-content:center;">
                                            <svg viewBox="0 0 24 24" fill="#00A896" style="width:100%;height:100%;">
                                                <path
                                                    d="M12 2C11.45 2 11 2.45 11 3V4.07C8.5 4.3 6.64 6.22 6.64 8.65C6.64 10.42 7.6 11.95 9.04 12.78C8.38 13.56 8 14.57 8 15.68C8 17.5 9.21 19.06 10.89 19.57L10 21H8V22H16V21H14L13.11 19.57C14.79 19.06 16 17.5 16 15.68C16 14.57 15.62 13.56 14.96 12.78C16.4 11.95 17.36 10.42 17.36 8.65C17.36 6.22 15.5 4.3 13 4.07V3C13 2.45 12.55 2 12 2Z" />
                                            </svg>
                                        </div>

                                        <div style="position:relative;z-index:1;">
                                            <!-- Top Header: Logo on Left, Contact Metadata on Right -->
                                            <div
                                                style="display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #00A896;padding-bottom:18px;margin-bottom:20px;">
                                                <div style="display:flex;align-items:center;gap:12px;">
                                                    <div
                                                        style="width:42px;height:42px;border-radius:10px;background:linear-gradient(135deg,#E0F2FE,#CCFBF1);color:#00A896;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;">
                                                        <i class="fa-solid fa-staff-snake"></i>
                                                    </div>
                                                    <div>
                                                        <h2
                                                            style="margin:0;font-family:'Montserrat',sans-serif;font-size:18px;font-weight:800;color:#0F172A;letter-spacing:0.5px;line-height:1.2;">
                                                            DIOR MEDICAL</h2>
                                                        <div
                                                            style="font-size:11.5px;font-weight:700;color:#00A896;letter-spacing:0.04em;">
                                                            TELEHEALTH &bull; VIRTUAL URGENT CARE</div>
                                                    </div>
                                                </div>
                                                <div
                                                    style="text-align:right;font-size:11px;color:#475569;line-height:1.5;">
                                                    <div style="font-weight:700;color:#0F172A;">+1 (800) 555-DIOR</div>
                                                    <div>www.diormedical.com</div>
                                                    <div>care@diormedical.com</div>
                                                    <div>100 Medical Plaza, Suite 400</div>
                                                </div>
                                            </div>

                                            <!-- Recipient & Date Bar -->
                                            <div
                                                style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:18px;">
                                                <div>
                                                    <span
                                                        style="display:block;font-size:11px;font-weight:700;color:#64748B;text-transform:uppercase;letter-spacing:0.04em;">TO:</span>
                                                    <strong id="doc-ltr-preview-to-name"
                                                        style="font-size:14.5px;color:#0F172A;display:block;">[Select
                                                        Patient]</strong>
                                                    <span id="doc-ltr-preview-to-meta"
                                                        style="font-size:11.5px;color:#64748B;display:block;">Patient
                                                        ID: &bull; DOB: </span>
                                                </div>
                                                <div style="text-align:right;">
                                                    <span id="doc-ltr-preview-date"
                                                        style="font-size:13px;font-weight:700;color:#0F172A;"><?php echo date('jS M Y'); ?></span>
                                                </div>
                                            </div>

                                            <!-- Salutation -->
                                            <p id="doc-ltr-preview-salutation"
                                                style="font-size:13.5px;font-weight:600;color:#0F172A;margin-bottom:14px;">
                                                Dear Patient,</p>

                                            <!-- Letter Content Body -->
                                            <div id="doc-standalone-letter-preview-content"
                                                style="font-size:13.5px;line-height:1.75;color:#334155;min-height:180px;">
                                                <p style="color:#94A3B8;text-align:center;padding:40px 0;">Please select
                                                    a patient from the dropdown on the left to generate the live
                                                    letterhead preview.</p>
                                            </div>

                                            <!-- Sign-off & Doctor Signature -->
                                            <div
                                                style="margin-top:28px;padding-top:16px;border-top:1px solid #E2E8F0;display:flex;justify-content:space-between;align-items:flex-end;">
                                                <div style="font-size:11px;color:#94A3B8;">
                                                    <i class="fa-solid fa-shield-halved" style="color:#00A896;"></i> DEA
                                                    21 CFR & HIPAA Security Standard
                                                </div>
                                                <div style="text-align:right;">
                                                    <span
                                                        style="font-size:12.5px;color:#475569;display:block;margin-bottom:4px;">Sincerely
                                                        yours,</span>
                                                    <div id="doc-ltr-preview-signature-wrap"
                                                        style="min-height:48px;display:flex;align-items:center;justify-content:flex-end;">
                                                        <?php if (!empty($doctor['signature_url'])): ?>
                                                            <img id="doc-ltr-preview-sig-img"
                                                                src="<?php echo esc_url($doctor['signature_url']); ?>"
                                                                alt="Doctor Signature"
                                                                style="max-height:50px;max-width:180px;object-fit:contain;background:transparent;">
                                                        <?php else: ?>
                                                            <span id="doc-ltr-preview-sig-text"
                                                                style="font-family:'Playfair Display',serif;font-style:italic;font-size:20px;color:#0F172A;">/s/
                                                                <?php echo esc_html($doctor['full_name']); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div style="font-weight:700;color:#0F172A;font-size:13.5px;margin-top:4px;"
                                                        id="doc-ltr-preview-doc-name">
                                                        <?php echo esc_html($doctor['full_name']); ?></div>
                                                    <div style="font-size:11px;color:#64748B;"
                                                        id="doc-ltr-preview-doc-meta">
                                                        <?php echo esc_html(!empty($doctor['specialty']) ? $doctor['specialty'] : 'Telehealth Physician'); ?>
                                                        &bull; License
                                                        #<?php echo esc_html(!empty($doctor['license_no']) ? $doctor['license_no'] : 'CA-MD-99201'); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>

                    </div>
                </section>



                <!-- -- SETTINGS -- -->
<section class="dior-tab-panel" id="tab-doc-settings" style="display:none;">
    <div style="background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%); border-radius: 16px 16px 0 0; height: 120px; position: relative;"></div>
    <div style="background: #fff; border-radius: 0 0 16px 16px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); margin-bottom: 24px; position: relative; margin-top: -60px;">
        <div style="display: flex; gap: 20px; align-items: flex-end;">
            <div style="position: relative;" class="dior-avatar-img-circle" onclick="diorDocOpenMediaLibrary()">
                <?php if (!empty($doctor['avatar_url'])): ?>
                    <img id="dior-doctor-avatar-display-img" src="<?php echo esc_url($doctor['avatar_url']); ?>" alt="Profile" style="width: 100px; height: 100px; border-radius: 50%; border: 4px solid #fff; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); object-fit: cover;">
                <?php else: ?>
                    <div id="dior-doctor-avatar-initials" style="width: 100px; height: 100px; border-radius: 50%; border: 4px solid #fff; background: #E2E8F0; display: flex; align-items: center; justify-content: center; font-size: 28px; font-weight: 700; color: #64748B; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
                        <?php echo esc_html(!empty($doctor['initials']) ? $doctor['initials'] : 'DR'); ?>
                    </div>
                <?php endif; ?>
                <button type="button" style="position: absolute; bottom: 0; right: 0; background: #3B82F6; color: #fff; border: none; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 2px 4px rgba(0,0,0,0.1);" onclick="diorDocOpenMediaLibrary(); event.stopPropagation();"><i class="fa-solid fa-camera"></i></button>
            </div>
            <div style="flex: 1; padding-bottom: 10px;">
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 6px;">
                    <h2 style="margin: 0; font-size: 24px; font-weight: 700; color: #1E293B;"><?php echo esc_html($doctor['full_name']); ?></h2>
                    <span style="background: #DBEAFE; color: #3B82F6; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700;">Provider</span>
                    <?php if (isset($is_profile_complete) && $is_profile_complete): ?>
                        <span style="background: #D1FAE5; color: #10B981; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700;"><i class="fa-solid fa-circle-check"></i> Verified Profile</span>
                    <?php else: ?>
                        <span style="background: #FEF3C7; color: #D97706; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700;"><i class="fa-solid fa-circle-exclamation"></i> Incomplete Profile</span>
                    <?php endif; ?>
                </div>
                <div style="color: #64748B; font-size: 13px;">
                    <i class="fa-regular fa-envelope" style="color: #3B82F6; margin-right: 4px;"></i> <?php echo esc_html($doctor['email'] ?? ''); ?> &bull; <i class="fa-solid fa-stethoscope" style="color: #059669; margin-right: 4px;"></i> Specialty: <strong style="color: #475569;"><?php echo esc_html($doctor['specialty'] ?? 'N/A'); ?></strong>
                </div>
            </div>
        </div>
    </div>

    <div style="display: flex; gap: 24px; flex-wrap: wrap;">
        <!-- Settings Nav -->
        <div style="flex: 1; min-width: 250px;">
            <div style="background: #fff; border-radius: 16px; padding: 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
                <a href="#" style="text-decoration: none; width: 100%; text-align: left; background: #3B82F6; color: #fff; border: none; padding: 16px; border-radius: 12px; margin-bottom: 8px; display: flex; align-items: center; gap: 16px; cursor: pointer;" onclick="return false;">
                    <i class="fa-solid fa-circle-user" style="font-size: 24px;"></i>
                    <div>
                        <span style="display: block; font-weight: 700; font-size: 14px;">Provider Profile</span>
                        <span style="display: block; font-size: 12px; opacity: 0.8;">Credentials &amp; Bio</span>
                    </div>
                </a>
                <a href="#" style="text-decoration: none; width: 100%; text-align: left; background: transparent; color: #475569; border: none; padding: 16px; border-radius: 12px; margin-bottom: 8px; display: flex; align-items: center; gap: 16px; cursor: pointer; transition: 0.2s;" onmouseover="this.style.background='#F1F5F9'" onmouseout="this.style.background='transparent'" onclick="return false;">
                    <i class="fa-solid fa-shield-halved" style="font-size: 24px; color: #64748B;"></i>
                    <div>
                        <span style="display: block; font-weight: 700; font-size: 14px; color: #1E293B;">Security &amp; Password</span>
                        <span style="display: block; font-size: 12px; color: #94A3B8;">Login credentials</span>
                    </div>
                </a>
                <a href="#" style="text-decoration: none; width: 100%; text-align: left; background: transparent; color: #475569; border: none; padding: 16px; border-radius: 12px; margin-bottom: 8px; display: flex; align-items: center; gap: 16px; cursor: pointer; transition: 0.2s;" onmouseover="this.style.background='#F1F5F9'" onmouseout="this.style.background='transparent'" onclick="return false;">
                    <i class="fa-solid fa-bell" style="font-size: 24px; color: #64748B;"></i>
                    <div>
                        <span style="display: block; font-weight: 700; font-size: 14px; color: #1E293B;">Notifications</span>
                        <span style="display: block; font-size: 12px; color: #94A3B8;">Appointment alerts</span>
                    </div>
                </a>
                <a href="#" style="text-decoration: none; width: 100%; text-align: left; background: transparent; color: #475569; border: none; padding: 16px; border-radius: 12px; display: flex; align-items: center; gap: 16px; cursor: pointer; transition: 0.2s;" onmouseover="this.style.background='#F1F5F9'" onmouseout="this.style.background='transparent'" onclick="return false;">
                    <i class="fa-solid fa-lock" style="font-size: 24px; color: #64748B;"></i>
                    <div>
                        <span style="display: block; font-weight: 700; font-size: 14px; color: #1E293B;">Privacy</span>
                        <span style="display: block; font-size: 12px; color: #94A3B8;">Compliance settings</span>
                    </div>
                </a>
            </div>
        </div>

        <!-- Form Area -->
        <div style="flex: 3; min-width: 500px;">
            <div style="background: #fff; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); overflow: hidden;">
                <form id="dior-doc-profile-form" novalidate onsubmit="diorDocSaveProfile(event)">
                    <div style="padding: 20px 24px; border-bottom: 1px solid #E2E8F0;">
                        <h3 style="margin: 0 0 4px 0; font-size: 18px; font-weight: 700; color: #1E293B;"><i class="fa-solid fa-stethoscope" style="color: #3B82F6; margin-right: 8px;"></i> Professional Details</h3>
                        <p style="margin: 0; font-size: 13px; color: #64748B;">Update your credentials, license information, and provider biography</p>
                    </div>
                    <div style="padding: 24px;">
                        
                        <div style="display: flex; gap: 20px; margin-bottom: 20px;">
                            <div style="flex: 1;">
                                <label style="display: block; font-size: 12px; font-weight: 600; color: #1E293B; margin-bottom: 8px;">First Name <span style="color: #EF4444;">*</span></label>
                                <div style="display: flex; align-items: center; border: 1px solid #E2E8F0; border-radius: 8px; padding: 0 12px; overflow: hidden; background: #fff;">
                                    <i class="fa-solid fa-user" style="color: #94A3B8; font-size: 14px;"></i>
                                    <input type="text" name="first_name" value="<?php echo esc_attr($doctor['first_name']); ?>" required style="border: none !important; box-shadow: none !important; background: transparent !important; outline: none; padding: 12px; width: 100%; font-size: 14px; color: #475569;">
                                </div>
                            </div>
                            <div style="flex: 1;">
                                <label style="display: block; font-size: 12px; font-weight: 600; color: #1E293B; margin-bottom: 8px;">Last Name <span style="color: #EF4444;">*</span></label>
                                <div style="display: flex; align-items: center; border: 1px solid #E2E8F0; border-radius: 8px; padding: 0 12px; overflow: hidden; background: #fff;">
                                    <i class="fa-solid fa-user" style="color: #94A3B8; font-size: 14px;"></i>
                                    <input type="text" name="last_name" value="<?php echo esc_attr($doctor['last_name']); ?>" required style="border: none !important; box-shadow: none !important; background: transparent !important; outline: none; padding: 12px; width: 100%; font-size: 14px; color: #475569;">
                                </div>
                            </div>
                        </div>

                        <div style="display: flex; gap: 20px; margin-bottom: 20px;">
                            <div style="flex: 1;">
                                <label style="display: block; font-size: 12px; font-weight: 600; color: #1E293B; margin-bottom: 8px;">Medical Specialty <span style="color: #EF4444;">*</span></label>
                                <div style="display: flex; align-items: center; border: 1px solid #E2E8F0; border-radius: 8px; padding: 0 12px; overflow: hidden; background: #fff;">
                                    <i class="fa-solid fa-user-doctor" style="color: #94A3B8; font-size: 14px;"></i>
                                    <input type="text" name="doctor_specialty" value="<?php echo esc_attr($doctor['specialty']); ?>" placeholder="e.g. Internal Medicine" required style="border: none !important; box-shadow: none !important; background: transparent !important; outline: none; padding: 12px; width: 100%; font-size: 14px; color: #475569;">
                                </div>
                            </div>
                            <div style="flex: 1;">
                                <label style="display: block; font-size: 12px; font-weight: 600; color: #1E293B; margin-bottom: 8px;">License Number <span style="color: #EF4444;">*</span></label>
                                <div style="display: flex; align-items: center; border: 1px solid #E2E8F0; border-radius: 8px; padding: 0 12px; overflow: hidden; background: #fff;">
                                    <i class="fa-solid fa-id-badge" style="color: #94A3B8; font-size: 14px;"></i>
                                    <input type="text" name="doctor_license" value="<?php echo esc_attr($doctor['license_no']); ?>" placeholder="MD123456" required style="border: none !important; box-shadow: none !important; background: transparent !important; outline: none; padding: 12px; width: 100%; font-size: 14px; color: #475569;">
                                </div>
                            </div>
                        </div>

                        <div style="display: flex; gap: 20px; margin-bottom: 20px;">
                            <div style="flex: 1;">
                                <label style="display: block; font-size: 12px; font-weight: 600; color: #1E293B; margin-bottom: 8px;">NPI Number <span style="color: #EF4444;">*</span></label>
                                <div style="display: flex; align-items: center; border: 1px solid #E2E8F0; border-radius: 8px; padding: 0 12px; overflow: hidden; background: #fff;">
                                    <i class="fa-solid fa-hashtag" style="color: #94A3B8; font-size: 14px;"></i>
                                    <input type="text" name="doctor_npi" value="<?php echo esc_attr($doctor['npi']); ?>" placeholder="10-digit NPI" required style="border: none !important; box-shadow: none !important; background: transparent !important; outline: none; padding: 12px; width: 100%; font-size: 14px; color: #475569; font-family: inherit;">
                                </div>
                            </div>
                            <div style="flex: 1;">
                                <label style="display: block; font-size: 12px; font-weight: 600; color: #1E293B; margin-bottom: 8px;">Phone Number <span style="color: #EF4444;">*</span></label>
                                <div style="display: flex; align-items: center; border: 1px solid #E2E8F0; border-radius: 8px; padding: 0 12px; overflow: hidden; background: #fff;">
                                    <i class="fa-solid fa-phone" style="color: #94A3B8; font-size: 14px;"></i>
                                    <input type="tel" name="phone" value="<?php echo esc_attr($doctor['phone']); ?>" required style="border: none !important; box-shadow: none !important; background: transparent !important; outline: none; padding: 12px; width: 100%; font-size: 14px; color: #475569;">
                                </div>
                            </div>
                        </div>

                        <div style="margin-bottom: 24px;">
                            <label style="display: block; font-size: 12px; font-weight: 600; color: #1E293B; margin-bottom: 8px;">Telehealth Meeting Link (Zoom / Doxy.me)</label>
                            <div style="display: flex; align-items: center; border: 1px solid #E2E8F0; border-radius: 8px; padding: 0 12px; overflow: hidden; background: #fff;">
                                <i class="fa-solid fa-video" style="color: #3B82F6; font-size: 14px;"></i>
                                <input type="url" name="doctor_zoom_link" value="<?php echo esc_attr($doctor['zoom_link'] ?? ''); ?>" placeholder="https://zoom.us/j/your-meeting-id" style="border: none !important; box-shadow: none !important; background: transparent !important; outline: none; padding: 12px; width: 100%; font-size: 14px; color: #475569;">
                            </div>
                        </div>

                        <div style="margin-bottom: 24px;">
                            <label style="display: block; font-size: 12px; font-weight: 600; color: #1E293B; margin-bottom: 8px;">Professional Bio</label>
                            <textarea name="doctor_bio" rows="4" style="border: 1px solid #E2E8F0 !important; box-shadow: none !important; background: #fff !important; border-radius: 8px; padding: 12px 16px; width: 100%; font-size: 14px; color: #475569; outline: none; box-sizing: border-box; resize: vertical;"><?php echo esc_textarea($doctor['bio']); ?></textarea>
                        </div>
                        
                        <div id="dior-doc-profile-msg" style="display:none;margin-top:16px;padding:14px 18px;border-radius:10px;font-size:13.5px;font-weight:600;font-family:'DMSans','DM Sans',sans-serif;"></div>
                    </div>
                    
                    <div style="padding: 16px 24px; border-top: 1px solid #E2E8F0; display: flex; justify-content: flex-end; gap: 12px; background: #F8FAFC;">
                        <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce); ?>">
                        <a href="#" style="text-decoration: none; padding: 10px 20px; border-radius: 8px; border: 1px solid #CBD5E1; background: #fff; color: #475569; font-size: 13px; font-weight: 600; cursor: pointer;" onclick="return false;">Cancel</a>
                        <button type="submit" style="text-decoration: none; padding: 10px 20px; border-radius: 8px; border: none; background: #3B82F6; color: #fff; font-size: 13px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px;"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

            </div><!-- /tab-content -->
        </main>
    </div><!-- /dior-app -->
</div><!-- /dior-doctor-wrap -->

<!-- Patient Detail Modal -->
<div class="dior-modal-overlay" id="modal-doc-patient">
    <div class="dior-modal-dialog dior-modal-lg" style="border-radius:16px; overflow:hidden; border:none; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);">
        <div class="dior-modal-header" style="background:#F8FAFC; border-bottom:1px solid #E2E8F0; padding:20px 24px;">
            <h3 id="modal-doc-patient-title" style="margin:0;font-size:18px;font-weight:700;color:#1E293B;display:flex;align-items:center;gap:8px;">Patient Detail</h3>
            <button type="button" class="dior-modal-close" onclick="diorDocCloseModal()">&times;</button>
        </div>
        <div class="dior-modal-body" id="modal-doc-patient-body">
            <div style="text-align:center;padding:32px;">
                <i class="fa-solid fa-spinner fa-spin fa-2x" style="color:#2C6CB1;"></i>
            </div>
        </div>
    </div>
</div>

<!-- Secure Document Upload Modal -->
<div class="dior-modal-overlay" id="modal-doc-upload">
    <div class="dior-modal-dialog" style="border-radius:16px; overflow:hidden; border:none; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);"
        style="max-width: 540px; border-radius: 14px; overflow: hidden; background: #FFFFFF; box-shadow: 0 20px 50px rgba(15,23,42,0.25); padding: 0;">
        <div class="dior-modal-header"
            style="background: linear-gradient(135deg, #0B1030 0%, #1A2552 100%); color: #FFFFFF; padding: 18px 24px;">
            <h3
                style="margin: 0; font-size: 16px; font-weight: 700; color: #FFFFFF; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-cloud-arrow-up" style="color: #60A5FA;"></i> Upload Clinical Document
            </h3>
            <button type="button" class="dior-modal-close" onclick="diorDocCloseUploadModal()"
                style="color: #94A3B8; font-size: 22px;">&times;</button>
        </div>
        <div class="dior-modal-body" style="padding: 24px;">
            <form id="dior-doc-upload-form" onsubmit="diorDocSubmitUpload(event)">
                <div style="margin-bottom: 16px;">
                    <label
                        style="display:block; font-size: 11.5px; font-weight: 700; color: #374151; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 6px;">Select
                        Patient <span style="color:#EF4444;">*</span></label>
                    <select name="patient_id" required
                        style="width: 100%; padding: 10px 12px; border: 1.5px solid #CBD5E1; border-radius: 8px; font-size: 13.5px; background: #FFF; color: #0F172A; outline: none;">
                        <option value="">-- Select Patient --</option>
                        <?php if (!empty($patients)): ?>
                            <?php foreach ($patients as $p): ?>
                                <option value="<?php echo (int) $p['user_id']; ?>">
                                    <?php echo esc_html($p['full_name']); ?> (<?php echo esc_html($p['patient_id']); ?>)
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px;">
                    <div>
                        <label
                            style="display:block; font-size: 11.5px; font-weight: 700; color: #374151; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 6px;">Category
                            <span style="color:#EF4444;">*</span></label>
                        <select name="doc_category" required
                            style="width: 100%; padding: 10px 12px; border: 1.5px solid #CBD5E1; border-radius: 8px; font-size: 13.5px; background: #FFF; color: #0F172A; outline: none;">
                            <option value="Lab Report">Lab Report</option>
                            <option value="Imaging / Radiology">Imaging / Radiology</option>
                            <option value="Medical Record">Medical Record</option>
                            <option value="Doctor Excuse Note">Doctor Excuse Note</option>
                            <option value="Signed Consent (HIPAAtizer)">Signed Consent (HIPAA)</option>
                            <option value="Prescription / Rx">Prescription / Rx</option>
                            <option value="Referral Letter">Referral Letter</option>
                            <option value="Other">Other Clinical Document</option>
                        </select>
                    </div>
                    <div>
                        <label
                            style="display:block; font-size: 11.5px; font-weight: 700; color: #374151; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 6px;">Document
                            Title <span style="color:#EF4444;">*</span></label>
                        <input type="text" name="doc_title" required placeholder="e.g. CBC & Metabolic Lab Panel"
                            style="width: 100%; padding: 10px 12px; border: 1.5px solid #CBD5E1; border-radius: 8px; font-size: 13.5px; box-sizing: border-box; outline: none;">
                    </div>
                </div>

                <div style="margin-bottom: 16px;">
                    <label
                        style="display:block; font-size: 11.5px; font-weight: 700; color: #374151; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 6px;">Select
                        File (PDF, PNG, JPG, DOCX) <span style="color:#EF4444;">*</span></label>
                    <div
                        style="border: 2px dashed #CBD5E1; border-radius: 10px; padding: 20px; text-align: center; background: #F8FAFC; transition: all 0.2s ease;">
                        <input type="file" name="doc_file" id="doc_file_input" required
                            accept=".pdf,.png,.jpg,.jpeg,.doc,.docx"
                            style="display: block; width: 100%; font-size: 13px; color: #475569;">
                        <span style="font-size: 11.5px; color: #94A3B8; display: block; margin-top: 8px;">Max file size:
                            25MB. Files are automatically stored in the secure protected vault.</span>
                    </div>
                </div>

                <div style="margin-bottom: 20px;">
                    <label
                        style="display:block; font-size: 11.5px; font-weight: 700; color: #374151; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 6px;">Clinical
                        Notes / Remarks (Optional)</label>
                    <textarea name="doc_notes" rows="2"
                        placeholder="Add confidential clinical context or review notes..."
                        style="width: 100%; padding: 10px 12px; border: 1.5px solid #CBD5E1; border-radius: 8px; font-size: 13px; box-sizing: border-box; outline: none;"></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="dior-btn-sm dior-btn-ghost" onclick="diorDocCloseUploadModal()"
                        style="height: 38px; padding: 0 18px; font-size: 13px;">
                        Cancel
                    </button>
                    <button type="submit" id="doc-upload-submit-btn" class="dior-btn-sm dior-btn-primary"
                        style="height: 38px; padding: 0 20px; font-size: 13px;">
                        <i class="fa-solid fa-cloud-arrow-up"></i> Upload & Secure File
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Video Call Date/Status Alert Modal -->
<div class="dior-modal-overlay" id="dior-call-alert-modal" style="z-index: 999999;">
    <div class="dior-modal-dialog" style="border-radius:16px; overflow:hidden; border:none; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);"
        style="max-width: 460px; border-radius: 14px; overflow: hidden; box-shadow: 0 20px 50px rgba(15,23,42,0.35); padding: 0; background: #FFFFFF; margin: auto;">
        <div
            style="background: #0F172A; color: #FFFFFF; padding: 16px 20px; display: flex; align-items: center; justify-content: space-between;">
            <h3 id="dior-call-alert-title" style="margin: 0; font-size: 15px; font-weight: 700; color: #FFFFFF;"><i
                    class="fa-solid fa-video"></i> Video Consultation</h3>
            <button type="button" onclick="diorDocCloseCallAlert()"
                style="background:none; border:none; color:#94A3B8; font-size:22px; cursor:pointer; line-height:1;">&times;</button>
        </div>
        <div style="padding: 24px; text-align: center;">
            <div id="dior-call-alert-icon-wrap" style="margin-bottom: 16px;">
                <i class="fa-solid fa-calendar-check" style="font-size: 36px; color: #0284C7;"></i>
            </div>
            <div id="dior-call-alert-msg"
                style="font-size: 14px; color: #334155; line-height: 1.6; margin-bottom: 24px;"></div>
            <div style="display: flex; align-items: center; justify-content: center; gap: 10px; flex-wrap: wrap;">
                <button type="button" onclick="diorDocCloseCallAlert()"
                    style="padding: 9px 20px; border-radius: 8px; font-size: 13px; font-weight: 600; border: 1px solid #CBD5E1; background: #F8FAFC; color: #475569; cursor: pointer; transition: all 0.2s ease;">
                    Close
                </button>
                <button type="button" id="dior-call-alert-override"
                    style="display: none; padding: 9px 20px; border-radius: 8px; font-size: 13px; font-weight: 700; border: none; background: linear-gradient(135deg, #059669 0%, #047857 100%); color: #FFFFFF; cursor: pointer; box-shadow: 0 2px 8px rgba(5,150,105,0.3); transition: all 0.2s ease;">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Launch Room Anyway
                </button>
            </div>
        </div>
    </div>
    <!-- Modal: Doctor Prescription Review & Confirmation Preview Before Transmitting -->
    <div class="dior-modal-overlay" id="modal-doc-rx-confirm-preview"
        style="z-index: 99999; display: none; align-items: center; justify-content: center; padding: 16px; background: rgba(15,23,42,0.78); backdrop-filter: blur(4px);">
        <div class="dior-modal-dialog" style="border-radius:16px; overflow:hidden; border:none; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);"
            style="max-width: 840px; width: 100%; max-height: 92vh; background: #FFFFFF; border-radius: 16px; box-shadow: 0 25px 60px rgba(0,0,0,0.35); display: flex; flex-direction: column; overflow: hidden; margin: auto;">

            <!-- Modal Header -->
            <div
                style="background: #0F172A; color: #FFFFFF; padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; border-bottom: 3px solid #00A896;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div
                        style="width: 32px; height: 32px; border-radius: 8px; background: linear-gradient(135deg,#00A896,#0284C7); color: #FFF; display: flex; align-items: center; justify-content: center; font-size: 15px;">
                        <i class="fa-solid fa-prescription"></i>
                    </div>
                    <div>
                        <h3
                            style="margin: 0; font-size: 15.5px; font-weight: 700; color: #FFFFFF; font-family: 'Montserrat', sans-serif;">
                            Review &amp; Confirm e-Prescription</h3>
                        <p style="margin: 2px 0 0 0; font-size: 11.5px; color: #94A3B8;">One-time physician confirmation
                            before transmitting to patient portal and pharmacy network.</p>
                    </div>
                </div>
                <button type="button" onclick="diorDocCloseRxConfirmModal()"
                    style="background: none; border: none; color: #94A3B8; font-size: 24px; cursor: pointer; line-height: 1; padding: 0 4px;"
                    title="Close Preview">&times;</button>
            </div>

            <!-- Verification Banner -->
            <div
                style="background: #F0FDFA; border-bottom: 1px solid #CCFBF1; padding: 10px 24px; display: flex; align-items: center; gap: 8px; font-size: 12px; color: #0F766E;">
                <i class="fa-solid fa-shield-halved" style="color: #00A896; font-size: 13px;"></i>
                <span><strong>Clinical Review Check:</strong> Please verify all medications, dosage frequencies, and SIG
                    directions below. Click <em>Confirm &amp; Transmit e-Rx</em> to issue.</span>
            </div>

            <!-- Scrollable Letterhead Preview Body -->
            <div style="padding: 24px; overflow-y: auto; flex: 1; background: #F8FAFC;">

                <div
                    style="background: #FFFFFF; border: 2px solid #00A896; border-radius: 12px; padding: 28px; box-shadow: 0 4px 20px rgba(0,168,150,0.06); position: relative; overflow: hidden;">

                    <!-- Caduceus Background Watermark -->
                    <div
                        style="position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%); width: 280px; height: 280px; opacity: 0.04; pointer-events: none; z-index: 0; display: flex; align-items: center; justify-content: center;">
                        <svg viewBox="0 0 24 24" fill="#00A896" style="width: 100%; height: 100%;">
                            <path
                                d="M12 2C11.45 2 11 2.45 11 3V4.07C8.5 4.3 6.64 6.22 6.64 8.65C6.64 10.42 7.6 11.95 9.04 12.78C8.38 13.56 8 14.57 8 15.68C8 17.5 9.21 19.06 10.89 19.57L10 21H8V22H16V21H14L13.11 19.57C14.79 19.06 16 17.5 16 15.68C16 14.57 15.62 13.56 14.96 12.78C16.4 11.95 17.36 10.42 17.36 8.65C17.36 6.22 15.5 4.3 13 4.07V3C13 2.45 12.55 2 12 2Z" />
                        </svg>
                    </div>

                    <div style="position: relative; z-index: 1;">

                        <!-- Clinic & Doctor Header Line -->
                        <div
                            style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #00A896; padding-bottom: 14px; margin-bottom: 14px;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <img src="<?php echo esc_url(!empty($logo_url) ? $logo_url : DIOR_PORTAL_URL . 'assets/images/logo.png'); ?>"
                                    alt="Dior Medical"
                                    style="height: 52px; max-height: 56px; width: auto; object-fit: contain; display: block;">
                                <div>
                                    <h2
                                        style="margin: 0; font-family: 'Montserrat', sans-serif; font-size: 15.5px; font-weight: 700; color: #1E293B; letter-spacing: 0.2px;">
                                        DIOR MEDICAL TELEHEALTH</h2>
                                    <p style="margin: 2px 0 0 0; font-size: 11px; color: #64748B;">
                                        Prescribing Provider: <strong
                                            style="color: #0F172A;"><?php echo esc_html($doctor['full_name']); ?></strong>
                                        &bull; NPI:
                                        <?php echo esc_html(!empty($doctor['npi']) ? $doctor['npi'] : 'Verified'); ?>
                                    </p>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <span
                                    style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0; border-radius: 10px; font-weight: 700; font-size: 10px;">
                                    <i class="fa-solid fa-circle-check"></i> Surescripts Real-Time EDI
                                </span>
                                <div style="font-size: 11px; color: #64748B; margin-top: 3px;">
                                    Date: <strong id="doc-confirm-rx-date"
                                        style="color: #1E293B;"><?php echo date('M j, Y'); ?></strong> &bull; UID:
                                    <strong id="doc-confirm-rx-uid"
                                        style="color: #00A896; font-family: monospace;">RX-PENDING</strong>
                                </div>
                            </div>
                        </div>

                        <!-- Patient Demographics: Classical Prescription Pad Underline Layout (Exact Match to User's Image 2) -->
                        <div class="dior-rx-patient-pad-box"
                            style="margin: 14px 0 16px 0; padding: 12px 16px; background: #FAFCFE; border: 1px solid #E2E8F0; border-radius: 8px;">
                            <!-- Row 1: Patient Name & Date -->
                            <div
                                style="display: flex; justify-content: space-between; align-items: flex-end; gap: 20px; margin-bottom: 10px; flex-wrap: wrap;">
                                <div style="flex: 1 1 56%; display: flex; align-items: flex-end; min-width: 240px;">
                                    <span
                                        style="font-size: 12px; font-weight: 700; color: #1E3A5F; white-space: nowrap; margin-right: 8px;">Patient
                                        Name:</span>
                                    <span id="doc-confirm-rx-patient-name"
                                        style="flex: 1; border-bottom: 1.5px solid #64748B; padding: 0 6px 2px 6px; font-size: 12.5px; font-weight: 600; color: #0F172A; min-height: 18px;">�</span>
                                </div>
                                <div style="flex: 1 1 34%; display: flex; align-items: flex-end; min-width: 160px;">
                                    <span
                                        style="font-size: 12px; font-weight: 700; color: #1E3A5F; white-space: nowrap; margin-right: 8px;">Date:</span>
                                    <span id="doc-confirm-rx-pad-date"
                                        style="flex: 1; border-bottom: 1.5px solid #64748B; padding: 0 6px 2px 6px; font-size: 12.5px; font-weight: 600; color: #0F172A; min-height: 18px;"><?php echo date('M j, Y'); ?></span>
                                </div>
                            </div>

                            <!-- Row 2: Age, Gender, Weight -->
                            <div
                                style="display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; margin-bottom: 10px; flex-wrap: wrap;">
                                <div style="flex: 1 1 28%; display: flex; align-items: flex-end; min-width: 110px;">
                                    <span
                                        style="font-size: 12px; font-weight: 700; color: #1E3A5F; white-space: nowrap; margin-right: 8px;">Age:</span>
                                    <span id="doc-confirm-rx-patient-dob"
                                        style="flex: 1; border-bottom: 1.5px solid #64748B; padding: 0 6px 2px 6px; font-size: 12.5px; font-weight: 600; color: #0F172A; min-height: 18px;">�</span>
                                </div>
                                <div style="flex: 1 1 32%; display: flex; align-items: flex-end; min-width: 120px;">
                                    <span
                                        style="font-size: 12px; font-weight: 700; color: #1E3A5F; white-space: nowrap; margin-right: 8px;">Gender:</span>
                                    <span id="doc-confirm-rx-patient-gender"
                                        style="flex: 1; border-bottom: 1.5px solid #64748B; padding: 0 6px 2px 6px; font-size: 12.5px; font-weight: 600; color: #0F172A; min-height: 18px;">Female</span>
                                </div>
                                <div style="flex: 1 1 32%; display: flex; align-items: flex-end; min-width: 120px;">
                                    <span
                                        style="font-size: 12px; font-weight: 700; color: #1E3A5F; white-space: nowrap; margin-right: 8px;">Weight:</span>
                                    <span id="doc-confirm-rx-patient-weight"
                                        style="flex: 1; border-bottom: 1.5px solid #64748B; padding: 0 6px 2px 6px; font-size: 12.5px; font-weight: 600; color: #0F172A; min-height: 18px;">NKDA
                                        (Verified)</span>
                                </div>
                            </div>

                            <!-- Row 3: Diagnosis -->
                            <div style="display: flex; align-items: flex-end; flex-wrap: wrap;">
                                <span
                                    style="font-size: 12px; font-weight: 700; color: #1E3A5F; white-space: nowrap; margin-right: 8px;">Diagnosis:</span>
                                <span id="doc-confirm-rx-diagnosis"
                                    style="flex: 1; border-bottom: 1.5px solid #64748B; padding: 0 6px 2px 6px; font-size: 12.5px; font-weight: 600; color: #0F172A; min-width: 220px; min-height: 18px;">Clinical
                                    Telehealth Consultation &bull; General Wellness</span>
                            </div>
                        </div>

                        <!-- Prescribed Medications Heading (?) -->
                        <div
                            style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <span
                                    style="font-family: 'Playfair Display', serif; font-size: 24px; font-weight: 800; color: #00A896; line-height: 1;">?</span>
                                <span
                                    style="font-size: 12px; font-weight: 700; color: #374151; text-transform: uppercase; letter-spacing: 0.05em;">Medication
                                    Schedule</span>
                            </div>
                            <span id="doc-confirm-rx-med-count"
                                style="font-size: 11.5px; font-weight: 700; color: #00A896; background: #F0FDFA; border: 1px solid #CCFBF1; padding: 2px 8px; border-radius: 10px;">0
                                Medications</span>
                        </div>

                        <!-- Medications Clinical Table -->
                        <div
                            style="border: 1px solid #E2E8F0; border-radius: 8px; overflow: hidden; margin-bottom: 16px;">
                            <table style="width: 100%; border-collapse: collapse; font-size: 12.5px; text-align: left;">
                                <thead>
                                    <tr
                                        style="background: #F1F5F9; border-bottom: 1.5px solid #CBD5E1; color: #475569; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;">
                                        <th style="padding: 10px 12px; width: 32px; text-align: center;">#</th>
                                        <th style="padding: 10px 14px;">Medication Name &amp; Formulation</th>
                                        <th style="padding: 10px 14px;">Dosage &amp; Usage Instructions (SIG)</th>
                                        <th style="padding: 10px 14px; width: 140px; text-align: right;">Refills /
                                            Dispense</th>
                                    </tr>
                                </thead>
                                <tbody id="doc-confirm-rx-tbody">
                                    <!-- Populated dynamically by JS -->
                                </tbody>
                            </table>
                        </div>

                        <!-- Clinical Advice & Remarks Block -->
                        <div id="doc-confirm-rx-notes-wrap"
                            style="display: none; background: #EFF6FF; border-left: 3.5px solid #3B82F6; border-radius: 0 8px 8px 0; padding: 10px 14px; margin-bottom: 16px; font-size: 12.5px; color: #1E3A8A;">
                            <strong>Clinical Instructions / Advice:</strong>
                            <div id="doc-confirm-rx-notes-text"
                                style="margin-top: 4px; color: #1E40AF; line-height: 1.5;"></div>
                        </div>

                        <!-- Sign-off & Electronic Signature Block -->
                        <div
                            style="margin-top: 22px; padding-top: 14px; border-top: 1px dashed #CBD5E1; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 12px;">
                            <div>
                                <span
                                    style="font-size: 10.5px; color: #64748B; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; display: block; margin-bottom: 2px;">Prescription
                                    Security Protocol</span>
                                <span style="font-size: 11.5px; color: #334155;">DEA 21 CFR Part 1311 &bull; Electronic
                                    Prescriptions for Controlled &amp; Legend Substances</span>
                            </div>
                            <div style="text-align: right;">
                                <div id="doc-confirm-rx-sig-wrap"
                                    style="min-height: 48px; display: flex; align-items: center; justify-content: flex-end;">
                                    <?php if (!empty($doctor['signature_url'])): ?>
                                        <img id="doc-confirm-rx-sig-img"
                                            src="<?php echo esc_url($doctor['signature_url']); ?>"
                                            alt="Doctor Digital Signature"
                                            style="max-height: 50px; max-width: 180px; object-fit: contain; background: transparent;">
                                    <?php else: ?>
                                        <span id="doc-confirm-rx-sig-text"
                                            style="font-family: 'Playfair Display', serif; font-style: italic; font-size: 19px; color: #0F172A;">/s/
                                            <?php echo esc_html($doctor['full_name']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div style="font-weight: 700; color: #0F172A; font-size: 13.5px; margin-top: 2px;">
                                    <?php echo esc_html($doctor['full_name']); ?></div>
                                <div style="font-size: 11px; color: #64748B;">
                                    <?php echo esc_html(!empty($doctor['specialty']) ? $doctor['specialty'] : 'Licensed Healthcare Provider'); ?>
                                    &bull; License
                                    #<?php echo esc_html(!empty($doctor['license_no']) ? $doctor['license_no'] : 'Verified'); ?>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

            </div>

            <!-- Modal Action Footer -->
            <div
                style="padding: 14px 24px; background: #FFFFFF; border-top: 1px solid #E2E8F0; display: flex; justify-content: space-between; align-items: center; gap: 10px;">
                <button type="button" class="dior-btn-sm dior-btn-ghost" onclick="diorDocCloseRxConfirmModal()"
                    style="height: 38px; padding: 0 18px; font-size: 13px; font-weight: 600;">
                    <i class="fa-solid fa-arrow-left"></i> Back to Edit
                </button>
                <button type="button" id="btn-doc-confirm-transmit-rx" onclick="diorDocExecutePrescriptionTransmit()"
                    style="height: 40px; padding: 0 26px; border-radius: 8px; font-size: 13.5px; font-weight: 700; font-family: 'Montserrat', sans-serif; background: linear-gradient(135deg,#00A896,#059669); color: #FFFFFF; border: none; cursor: pointer; box-shadow: 0 4px 14px rgba(0,168,150,0.3); display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s ease;">
                    <i class="fa-solid fa-paper-plane"></i> Confirm &amp; Transmit e-Rx
                </button>
            </div>

        </div>
    </div>

    <script>
        window.diorDocNonce = '<?php echo esc_js($nonce); ?>';
        window.diorDocAjax = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';
        var diorDocNonce = window.diorDocNonce;
        var diorDocAjax = window.diorDocAjax;
        if (!window.pdmTab) {
            window.pdmTab = function (btn, targetId) {
                if (!btn && !targetId) return;
                var button = (btn && btn.closest) ? (btn.closest('.dior-pdm-tab-btn') || btn) : document.querySelector('.dior-pdm-tab-btn[data-tab="' + targetId + '"]');
                var wrap = (button && button.closest('.dior-pdm')) || document.getElementById('modal-doc-patient-body') || document;
                wrap.querySelectorAll('.dior-pdm-tab-btn').forEach(function (b) { b.classList.remove('active'); });
                if (button) button.classList.add('active');
                wrap.querySelectorAll('.dior-pdm-tab-pane').forEach(function (p) { p.classList.remove('active'); p.style.display = 'none'; });
                var target = wrap.querySelector('#' + targetId) || document.getElementById(targetId);
                if (target) { target.classList.add('active'); target.style.display = 'block'; }
            };
        }
    </script>


