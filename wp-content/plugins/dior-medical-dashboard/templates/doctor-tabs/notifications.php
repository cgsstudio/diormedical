<section class="dior-tab-panel" id="tab-doc-notifications">
                    <div class="dior-content-pad">
                        <div class="dior-page-header-box">
                            <div>
                                <h2>Provider Notification Inbox</h2>
                                <p>Stay updated with new patient registrations, appointments, intake submissions, and
                                    system alerts.</p>
                            </div>
                            <button type="button" class="dior-btn-gold-secondary" onclick="diorDocMarkAllRead()">
                                <i class="fas fa-check-double"></i> Mark All as Read
                            </button>
                        </div>

                        <!-- Provider Reminder Preferences Banner -->
                        <div class="dior-dash-table-card dior-box-card dior-ic-2b8e1bae08"
                           >
                            <div class="table-responsive dior-ic-05e567f7a6"
                               >
                                <div class="dior-ic-4e40d07727">
                                    <div
                                        class="dior-ic-bd2f861642">
                                        <i class="fas fa-bell"></i>
                                    </div>
                                    <div>
                                        <strong class="dior-ic-fa0cb41baa">
                                            Appointment Reminders:
                                            <?php if ($doctor['optin_appointment_reminders'] === '1'): ?>
                                                <span class="dior-ic-deded86577"><i
                                                        class="fas fa-circle-check"></i> Active &amp; Opted-In</span>
                                            <?php else: ?>
                                                <span class="dior-ic-73a2e081c0"><i
                                                        class="fas fa-circle-xmark"></i> Paused</span>
                                            <?php endif; ?>
                                        </strong>
                                        <span
                                            class="dior-ic-a855df8772">
                                            Dual delivery active: Reminders are dispatched to both provider (email &amp;
                                            inbox) and patient before each scheduled visit.
                                        </span>
                                    </div>
                                </div>
                                <div>
                                    <button type="button" class="dior-btn-gold-secondary dior-ic-4b78ef9c9d"
                                        onclick="diorDocSwitchTab('tab-doc-settings');"
                                       >
                                        <i class="fas fa-sliders"></i> Provider Settings
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="dior-dash-table-card dior-box-card">
                            <div class="dior-card-body p-0">
                                <div class="dior-notifs-full-list" id="dior-doc-notif-full">
                                    <?php if (empty($notifications)): ?>
                                        <div
                                            class="dior-ic-0a2e00189a">
                                            <div
                                                class="dior-ic-14b8b903a5">
                                                <i class="far fa-bell-slash dior-ic-af2d77531e"
                                                   ></i>
                                            </div>
                                            <p
                                                class="dior-ic-ea192cc1f5">
                                                No notifications yet</p>
                                            <p class="dior-ic-9d2951a5b3">You'll see new alerts
                                                and updates here.</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($notifications as $n): ?>
                                            <div class="dior-full-notif-item <?php echo !$n['is_read'] ? 'unread' : ''; ?>"
                                                data-notif-id="<?php echo esc_attr($n['id']); ?>">
                                                <div class="notif-type-icon <?php echo esc_attr($n['type'] ?? 'system'); ?>">
                                                    <i class="fas <?php echo esc_attr($n['icon'] ?? 'fa-bell'); ?>"></i>
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
                                                            <i class="fas fa-check"></i>
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
