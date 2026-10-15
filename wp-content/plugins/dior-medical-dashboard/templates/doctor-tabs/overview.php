<?php
// Dashboard overview uses only live records passed by the dashboard controller.
$today = current_time('Y-m-d');
$today_appointments = [];
$upcoming_appointments = [];
foreach ((array)$appointments as $appointment) {
    $date = (string)($appointment['appt_date'] ?? $appointment['date'] ?? '');
    $status = strtolower((string)($appointment['status'] ?? ''));
    if ($date === $today) $today_appointments[] = $appointment;
    if ($date >= $today && in_array($status, ['scheduled', 'confirmed', 'in-queue', 'in_queue'], true)) $upcoming_appointments[] = $appointment;
}
usort($today_appointments, static function($a, $b) {
    return strcmp((string)($a['appt_time'] ?? $a['time'] ?? ''), (string)($b['appt_time'] ?? $b['time'] ?? ''));
});
$completed_today = count(array_filter($today_appointments, static fn($a) => in_array(strtolower((string)($a['status'] ?? '')), ['completed','done'], true)));
$upcoming_today = count(array_filter($today_appointments, static fn($a) => in_array(strtolower((string)($a['status'] ?? '')), ['scheduled','confirmed','in-queue','in_queue'], true)));
$cancelled_today = count(array_filter($today_appointments, static fn($a) => in_array(strtolower((string)($a['status'] ?? '')), ['cancelled','canceled','cancel'], true)));
$current_month = current_time('Y-m');
$new_patients_this_month = 0;
foreach ((array)$patients as $patient) {
    $registered = strtotime((string)($patient['registered'] ?? ''));
    if ($registered && wp_date('Y-m', $registered) === $current_month) $new_patients_this_month++;
}
$recent_appointments = array_slice(array_reverse((array)$appointments), 0, 5);

// Dynamic patient reviews for the doctor overview panel
global $wpdb;
$current_doc_user_id = get_current_user_id();
$reviews_query = "SELECT r.*, 
    COALESCE(NULLIF(TRIM(CONCAT(COALESCE(p.first_name, ''), ' ', COALESCE(p.last_name, ''))), ''), NULLIF(u.display_name, ''), u.user_login, 'Patient') AS patient_display_name,
    COALESCE(p.avatar_url, '') AS avatar_url
    FROM {$wpdb->prefix}dior_reviews r
    LEFT JOIN {$wpdb->prefix}dior_patients p ON p.user_id = r.patient_id
    LEFT JOIN {$wpdb->users} u ON u.ID = r.patient_id";

if (!current_user_can('manage_options')) {
    $scoped_doctor_ids = class_exists('Dior_Doctor_Resolver') 
        ? Dior_Doctor_Resolver::get_accessible_doctor_ids($current_doc_user_id) 
        : [$current_doc_user_id];
    if (!empty($scoped_doctor_ids)) {
        $scoped_placeholders = implode(',', array_fill(0, count($scoped_doctor_ids), '%d'));
        $reviews_query .= $wpdb->prepare(" WHERE (r.doctor_id IN ($scoped_placeholders) OR r.doctor_id = 0)", ...$scoped_doctor_ids);
    } else {
        $reviews_query .= $wpdb->prepare(" WHERE (r.doctor_id = %d OR r.doctor_id = 0)", $current_doc_user_id);
    }
}
$overview_reviews = $wpdb->get_results($reviews_query . " ORDER BY r.created_at DESC LIMIT 15", ARRAY_A);
if (!is_array($overview_reviews)) {
    $overview_reviews = [];
}
?>


<section class="dior-tab-panel active" id="tab-doc-overview">
    <section class="stats">
        <div class="card stat">
            <div class="num"><?php echo esc_html(number_format_i18n(count((array)$appointments))); ?></div>
            <div class="stat-icon"><i class="far fa-calendar-days"></i></div>
            <div class="label">Appointments</div>
        </div>
        <div class="card stat">
            <div class="num"><?php echo esc_html(number_format_i18n(count($upcoming_appointments))); ?></div>
            <div class="stat-icon"><i class="fas fa-clock-rotate-left"></i></div>
            <div class="label">Upcoming Appointments</div>
        </div>
        <div class="card stat">
            <div class="num"><?php echo esc_html(number_format_i18n($new_patients_this_month)); ?></div>
            <div class="stat-icon"><i class="fas fa-user-doctor"></i></div>
            <div class="label">New Patients</div>
        </div>
        <div class="card stat">
            <div class="num">—</div>
            <div class="stat-icon"><i class="fas fa-dollar-sign"></i></div>
            <div class="label">Total Earning</div>
        </div>
    </section>

    <div class="columns">
        <div class="left">
            <div class="schedule-card">
                <div class="schedule-header">
                    <h2 class="schedule-title">Today's Schedule</h2>
                    <div class="schedule-badges">
                        <span class="s-badge b-completed"><?php echo esc_html($completed_today); ?> Completed</span>
                        <span class="s-badge b-upcoming"><?php echo esc_html($upcoming_today); ?> Upcoming</span>
                        <span class="s-badge b-cancel"><?php echo esc_html($cancelled_today); ?> Cancel</span>
                    </div>
                </div>
                <div class="schedule-body">
                    <?php if (empty($today_appointments)): ?>
                        <div class="schedule-slot"><div class="schedule-appt"><div class="s-appt-left"><div><h3 class="s-patient-name">No appointments scheduled for today.</h3></div></div></div></div>
                    <?php else: foreach ($today_appointments as $appointment):
                        $patient_name = (string)($appointment['patient_name'] ?? 'Patient');
                        $patient_id = (string)($appointment['patient_id_num'] ?? '');
                        $time = (string)($appointment['appt_time'] ?? $appointment['time'] ?? '—');
                        $status = (string)($appointment['status'] ?? 'Scheduled');
                        $status_key = strtolower($status);
                        $status_class = in_array($status_key, ['completed','done'], true) ? 's-completed' : (in_array($status_key, ['cancelled','canceled','cancel'], true) ? 's-cancelled' : (in_array($status_key, ['in progress','in-progress','in-queue'], true) ? 's-progress' : 's-upcoming'));
                        $avatar = 'https://ui-avatars.com/api/?name=' . rawurlencode($patient_name) . '&background=eef2ff&color=4f46e5';
                    ?>
                    <div class="schedule-slot">
                        <div class="schedule-time"><?php echo esc_html($time); ?></div>
                        <div class="schedule-timeline"><div class="schedule-dot"></div></div>
                        <div class="schedule-appt">
                            <div class="s-appt-left"><img class="s-patient-img" src="<?php echo esc_url($avatar); ?>" alt="<?php echo esc_attr($patient_name); ?>"><div><h3 class="s-patient-name"><?php echo esc_html($patient_name); ?></h3><p class="s-patient-id"><?php echo esc_html($patient_id); ?></p></div></div>
                            <div class="s-appt-right"><span class="s-appt-status <?php echo esc_attr($status_class); ?>"><?php echo esc_html(ucwords($status)); ?></span></div>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>            </div>

            <section class="card table-card">
                <div class="panel-title">Latest Appointments</div>
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Appointments Id</th>
                                <th>Patient Name</th>
                                <th>Date</th>
                                <th>Disease</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_appointments)): ?>
                                <tr><td colspan="5">No appointment records found.</td></tr>
                            <?php else: foreach ($recent_appointments as $appointment):
                                $appointment_id = (string)($appointment['appt_uid'] ?? $appointment['id'] ?? '');
                                $patient_name = (string)($appointment['patient_name'] ?? 'Patient');
                                $date = (string)($appointment['appt_date'] ?? $appointment['date'] ?? '');
                                $condition = (string)($appointment['condition'] ?? $appointment['reason'] ?? $appointment['visit_type'] ?? '—');
                            ?>
                                <tr>
                                    <td><?php echo esc_html($appointment_id ?: '—'); ?></td>
                                    <td><?php echo esc_html($patient_name); ?></td>
                                    <td><?php echo esc_html($date ? wp_date('M j, Y', strtotime($date)) : '—'); ?></td>
                                    <td><?php echo esc_html($condition); ?></td>
                                    <td><button class="action-btn btn" type="button">Prescription</button><button class="action-btn outline btn" type="button">Details</button></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="card table-card prescriptions">
                <div class="panel-title"><span>Prescriptions Overview</span>
                    <div class="chips"><span class="chip green">45 Active</span><span class="chip gold">12
                            Inactive</span><span class="chip red">5 Expired</span></div>
                </div>
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Prescriptions Id</th>
                                <th>Patient Name</th>
                                <th>Medication</th>
                                <th>Dosage</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>PRS00123</td>
                                <td>Mia Song</td>
                                <td>Amlodipine</td>
                                <td>5mg</td>
                                <td><span class="pill active">Active</span></td>
                            </tr>
                            <tr>
                                <td>PRS00111</td>
                                <td>John Johnson</td>
                                <td>Amoxicillin</td>
                                <td>500mg</td>
                                <td><span class="pill inactive">Inactive</span></td>
                            </tr>
                            <tr>
                                <td>PRS00112</td>
                                <td>Elizabeth Brown</td>
                                <td>Atorvastatin</td>
                                <td>20mg</td>
                                <td><span class="pill active">Active</span></td>
                            </tr>
                            <tr>
                                <td>PRS00112</td>
                                <td>Elizabeth Brown</td>
                                <td>Ibuprofen</td>
                                <td>400mg</td>
                                <td><span class="pill active">Active</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="right">
            <section class="card profile">
                <div class="profile-main">
                    <img class="profile-photo" src="https://randomuser.me/api/portraits/women/68.jpg"
                        alt="Dr. Diorca Aquino De La Cruz">
                    <div>
                        <div class="profile-name">Dr. Diorca Aquino De La Cruz</div>
                        <div class="profile-role">Cardiologist</div>
                        <div class="profile-meta">
                            <i class="fas fa-envelope"></i> Abc@gmail.com<br>
                            <i class="fas fa-phone"></i> +1 234 567 8900 &nbsp;&nbsp; <i
                                class="fas fa-briefcase"></i> 12 years experience
                        </div>
                    </div>
                </div>
                <button class="edit btn">Edit Profile</button>
            </section>
            <section class="card tasks">
                <div class="panel-title">Pending Tasks</div>
                <div class="task"><span class="check"></span>
                    <div><b>Review lab reports</b>
                        <p>Check blood test results for 3 patients</p><small><i class="far fa-calendar"></i> Oct
                            2, 2026</small>
                    </div>
                </div>
                <div class="task"><span class="check"></span>
                    <div><b>Sign prescriptions</b>
                        <p>5 prescriptions pending signature</p><small><i class="far fa-calendar"></i> Oct 2,
                            2026</small>
                    </div>
                </div>
                <div class="task"><span class="check"></span>
                    <div><b>Approve medical notes</b>
                        <p>Review and approve consultation notes</p><small><i class="far fa-calendar"></i> Oct 2,
                            2026</small>
                    </div>
                </div>
                <div class="task"><span class="check"></span>
                    <div><b>Update patient records</b>
                        <p>Complete EMR updates for recent visits</p><small><i class="far fa-calendar"></i> Oct
                            2, 2026</small>
                    </div>
                </div>
            </section>
            <section class="card follow">
                <div class="panel-title">Follow-up Reminders</div>
                <div class="follow-item"><img class="avatar"
                        src="https://ui-avatars.com/api/?name=Mia+Song&background=eef2ff&color=4f46e5" alt="Avatar">
                    <div class="follow-info"><b>Mia Song | PAT00123</b><small>Routine Checkup</small><small><i
                                class="far fa-calendar"></i> Oct 2, 2026</small></div>
                    <div class="follow-buttons"><button class="square btn"><i
                                class="far fa-envelope"></i></button><button class="square btn"><i
                                class="fas fa-phone"></i></button></div>
                </div>
                <div class="follow-item"><img class="avatar"
                        src="https://ui-avatars.com/api/?name=John+Johnson&background=f0fdf4&color=166534" alt="Avatar">
                    <div class="follow-info"><b>John Johnson | PAT00111</b><small>Routine Checkup</small><small><i
                                class="far fa-calendar"></i> Oct 2, 2026</small></div>
                    <div class="follow-buttons"><button class="square btn"><i
                                class="far fa-envelope"></i></button><button class="square btn"><i
                                class="fas fa-phone"></i></button></div>
                </div>
                <div class="follow-item"><img class="avatar"
                        src="https://ui-avatars.com/api/?name=Richard+Davis&background=fff1f2&color=be123c"
                        alt="Avatar">
                    <div class="follow-info"><b>Richard Davis | PAT00238</b><small>Routine Checkup</small><small><i
                                class="far fa-calendar"></i> Oct 2, 2026</small></div>
                    <div class="follow-buttons"><button class="square btn"><i
                                class="far fa-envelope"></i></button><button class="square btn"><i
                                class="fas fa-phone"></i></button></div>
                </div>
                <div class="follow-item"><img class="avatar"
                        src="https://ui-avatars.com/api/?name=Elizabeth+Brown&background=fef3c7&color=b45309"
                        alt="Avatar">
                    <div class="follow-info"><b>Elizabeth Brown | PAT00112</b><small>Routine Checkup</small><small><i
                                class="far fa-calendar"></i> Oct 2, 2026</small></div>
                    <div class="follow-buttons"><button class="square btn"><i
                                class="far fa-envelope"></i></button><button class="square btn"><i
                                class="fas fa-phone"></i></button></div>
                </div>
            </section>
        </div>
    </div>

    <section class="card table-card lab">
        <div class="panel-title">Latest Lab Results</div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Patient Id</th>
                        <th>Patient Name</th>
                        <th>Test Name</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>PAT00123</td>
                        <td>Mia Song</td>
                        <td>Blood Work</td>
                        <td>Sep 10, 2026</td>
                        <td><span class="pill active">Ready</span></td>
                        <td>
                            <div class="lab-actions"><button class="round-action btn"><i
                                        class="fas fa-info"></i></button><button class="round-action btn"><i
                                        class="fas fa-download"></i></button></div>
                        </td>
                    </tr>
                    <tr>
                        <td>PAT00111</td>
                        <td>John Johnson</td>
                        <td>Renal Function Test</td>
                        <td>Sep 10, 2026</td>
                        <td><span class="pill inactive">Pending</span></td>
                        <td>
                            <div class="lab-actions"><button class="round-action btn"><i
                                        class="fas fa-info"></i></button><button class="round-action btn"><i
                                        class="fas fa-download"></i></button></div>
                        </td>
                    </tr>
                    <tr>
                        <td>PAT00112</td>
                        <td>Elizabeth Brown</td>
                        <td>Thyroid Function Test</td>
                        <td>Sep 10, 2026</td>
                        <td><span class="pill active">In Progress</span></td>
                        <td>
                            <div class="lab-actions"><button class="round-action btn"><i
                                        class="fas fa-info"></i></button><button class="round-action btn"><i
                                        class="fas fa-download"></i></button></div>
                        </td>
                    </tr>
                    <tr>
                        <td>PAT00112</td>
                        <td>Elizabeth Brown</td>
                        <td>Vitamin D Test</td>
                        <td>Sep 10, 2026</td>
                        <td><span class="pill active">Normal</span></td>
                        <td>
                            <div class="lab-actions"><button class="round-action btn"><i
                                        class="fas fa-info"></i></button><button class="round-action btn"><i
                                        class="fas fa-download"></i></button></div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <div class="bottom">
        <section class="card reviews" id="dior-doc-overview-reviews-card">
            <div class="panel-title">
                <span>Patient Review</span>
                <a href="#tab=doc-patient-review" onclick="if(typeof diorDocSwitchTab === 'function'){ diorDocSwitchTab('doc-patient-review'); } return false;" class="dior-panel-view-all" title="View all reviews and feedback" style="font-family:'Montserrat',sans-serif;font-size:13px;font-weight:600;color:#4F46E5;text-decoration:none;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:all 0.2s ease;">
                    <span>View All</span>
                    <i class="fas fa-arrow-right" style="font-size:11px;"></i>
                </a>
            </div>
            <div class="reviews-scroll">
                <?php if (!empty($overview_reviews)): 
                    $palette_list = [
                        ['bg' => 'eef2ff', 'color' => '4f46e5'],
                        ['bg' => 'f0fdf4', 'color' => '166534'],
                        ['bg' => 'fef3c7', 'color' => 'b45309'],
                        ['bg' => 'fff1f2', 'color' => 'be123c'],
                        ['bg' => 'f3e8ff', 'color' => '7e22ce'],
                        ['bg' => 'ecfeff', 'color' => '0e7490'],
                    ];
                    foreach ($overview_reviews as $idx => $rev):
                        $p_name = !empty($rev['patient_display_name']) ? trim($rev['patient_display_name']) : 'Patient';
                        $p_id   = (int)($rev['patient_id'] ?? 0);
                        $rating = max(1, min(5, (int)($rev['rating'] ?? 5)));
                        $comment = !empty($rev['comment']) ? trim($rev['comment']) : (!empty($rev['subject']) ? trim($rev['subject']) : 'Great service and professional care.');
                        $subject = !empty($rev['subject']) ? trim($rev['subject']) : '';
                        
                        $palette = $palette_list[$idx % count($palette_list)];
                        $fallback_avatar = 'https://ui-avatars.com/api/?name=' . rawurlencode($p_name) . '&background=' . $palette['bg'] . '&color=' . $palette['color'];
                        
                        $avatar = !empty($rev['avatar_url']) ? $rev['avatar_url'] : '';
                        if (!$avatar && $p_id > 0) {
                            $avatar = get_user_meta($p_id, 'dior_profile_image', true) ?: get_user_meta($p_id, 'profile_image', true) ?: '';
                        }
                        if (!$avatar) {
                            $avatar = $fallback_avatar;
                        }

                        $date_label = '';
                        if (!empty($rev['created_at']) && $rev['created_at'] !== '0000-00-00 00:00:00') {
                            $ts = strtotime($rev['created_at']);
                            if ($ts) {
                                $date_label = wp_date('M j, Y', $ts);
                            }
                        }
                ?>
                    <div class="review" style="cursor:pointer;" onclick="if(typeof diorDocSwitchTab === 'function'){ diorDocSwitchTab('doc-patient-review'); }" title="Click to view details in Feedback & Reviews">
                        <img class="avatar" src="<?php echo esc_url($avatar); ?>"
                            alt="<?php echo esc_attr($p_name); ?>"
                            onerror="this.src='<?php echo esc_url($fallback_avatar); ?>'">
                        <div class="review-main review-body">
                            <div class="review-top review-row1">
                                <span class="review-name" title="<?php echo esc_attr($p_name); ?>">
                                    <?php echo esc_html($p_name); ?>
                                    <?php if (!empty($date_label)): ?>
                                        <span class="review-date-badge" style="font-size:11px;font-weight:400;color:#94A3B8;margin-left:6px;">&bull; <?php echo esc_html($date_label); ?></span>
                                    <?php endif; ?>
                                </span>
                                <span class="stars" title="<?php echo esc_attr($rating); ?> out of 5 stars">
                                    <?php for ($s = 1; $s <= 5; $s++): ?>
                                        <i class="<?php echo $s <= $rating ? 'fas' : 'far'; ?> fa-star"<?php if ($s > $rating) echo ' style="color:#CBD5E1;"'; ?>></i>
                                    <?php endfor; ?>
                                </span>
                            </div>
                            <div class="review-text" title="<?php echo esc_attr($comment); ?>" style="word-break:break-word;overflow-wrap:anywhere;">
                                <?php if (!empty($subject) && strtolower($subject) !== 'patient review' && strtolower($subject) !== strtolower($comment)): ?>
                                    <strong style="color:#334155;"><?php echo esc_html($subject); ?>:</strong>
                                <?php endif; ?>
                                <?php echo esc_html($comment); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; else: ?>
                    <div class="dior-empty-reviews" style="padding:48px 24px;text-align:center;color:#64748B;">
                        <div style="width:48px;height:48px;margin:0 auto 12px;background:#F1F5F9;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#94A3B8;font-size:20px;">
                            <i class="far fa-comment-dots"></i>
                        </div>
                        <div style="font-family:'DM Sans',sans-serif;font-size:15px;font-weight:600;color:#334155;margin-bottom:4px;">No Patient Reviews Yet</div>
                        <div style="font-size:13px;color:#94A3B8;max-width:260px;margin:0 auto;line-height:1.5;">When patients submit feedback and reviews, they will appear here dynamically.</div>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="card sessions">
            <div class="panel-title">Upcoming Sessions</div>
            <div class="sessions-scroll">
                <div class="session">
                    <img class="avatar"
                        src="https://ui-avatars.com/api/?name=Elizabeth+Brown&background=fef3c7&color=b45309"
                        alt="Elizabeth Brown">
                    <div class="session-info">
                        <b>General Consultation</b>
                        <span class="session-patient">Elizabeth Brown | PAT00112</span>
                        <span class="session-time"><i class="far fa-calendar"></i> Sep 29, 2026 &nbsp;<i
                                class="far fa-clock"></i> 05:30 PM</span>
                    </div>
                    <div class="session-actions">
                        <button class="reschedule btn">Reschedule</button>
                        <button class="join btn">Join Now</button>
                    </div>
                </div>
                <div class="session">
                    <img class="avatar" src="https://ui-avatars.com/api/?name=Mia+Song&background=eef2ff&color=4f46e5"
                        alt="Mia Song">
                    <div class="session-info">
                        <b>Follow-up Appointment</b>
                        <span class="session-patient">Mia Song | PAT00123</span>
                        <span class="session-time"><i class="far fa-calendar"></i> Oct 2, 2026 &nbsp;<i
                                class="far fa-clock"></i> 11:00 AM</span>
                    </div>
                    <div class="session-actions">
                        <button class="reschedule btn">Reschedule</button>
                        <button class="join btn">Join Now</button>
                    </div>
                </div>
                <div class="session">
                    <img class="avatar"
                        src="https://ui-avatars.com/api/?name=Richard+Davis&background=fff1f2&color=be123c"
                        alt="Richard Davis">
                    <div class="session-info">
                        <b>General Consultation</b>
                        <span class="session-patient">Richard Davis | PAT00238</span>
                        <span class="session-time"><i class="far fa-calendar"></i> Oct 2, 2026 &nbsp;<i
                                class="far fa-clock"></i> 11:00 AM</span>
                    </div>
                    <div class="session-actions">
                        <button class="reschedule btn">Reschedule</button>
                        <button class="join btn">Join Now</button>
                    </div>
                </div>
            </div>
        </section>
    </div>
    <button class="float btn"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-plus-fill" viewBox="0 0 16 16" style="background:transparent;"><path d="M12 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2M8.5 6v1.5H10a.5.5 0 0 1 0 1H8.5V10a.5.5 0 0 1-1 0V8.5H6a.5.5 0 0 1 0-1h1.5V6a.5.5 0 0 1 1 0"/></svg></button>
</section>
