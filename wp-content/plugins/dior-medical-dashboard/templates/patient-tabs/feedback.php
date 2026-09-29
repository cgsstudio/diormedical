<section class="dior-tab-panel dior-ic-44a70a0420" id="tab-feedback">
    <div class="master-table-wrapper">
        <div class="master-table-container">
            <div class="master-table-card">
                
                <!-- Header -->
                <div class="master-table-header">
                    <div class="header-content dior-ic-6a2286ff56">
                        <div class="table-title-section">
                            <h2 class="table-title">Feedback & Support</h2>
                            <div class="title-accent"></div>
                        </div>
                        <div class="header-actions-group dior-ic-c047e12f84">
                            <div class="search-container dior-ic-7e521c6e89">
                                <i class="fa-solid fa-magnifying-glass search-icon dior-ic-bc24164497"></i>
                                <input type="text" placeholder="Search records..." class="search-input dior-ic-fc89dcce8b">
                            </div>
                            <div class="action-buttons dior-ic-6c10502845">
                                <button class="action-btn action-btn-primary dior-ic-e654e3916a"><i class="fa-solid fa-plus"></i></button>
                                <button class="action-btn action-btn-success dior-ic-cf7c00244f"><i class="fa-solid fa-file-arrow-down"></i></button>
                                <button class="action-btn action-btn-info dior-ic-85fdbe7fee"><i class="fa-solid fa-rotate-right"></i></button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Table Content -->
                <div class="table-content dior-ic-cd60c2926b">
                    <table class="dior-ic-80816b495c">
                        <thead>
                            <tr class="dior-ic-2e693db070">
                                <th class="dior-ic-eae16ff263"><input type="checkbox"></th>
                                <th class="dior-ic-da19ee3bab">Ticket ID <i class="fa-solid fa-sort dior-ic-c3c6ca2a51"></i></th>
                                <th class="dior-ic-da19ee3bab">Subject <i class="fa-solid fa-sort dior-ic-c3c6ca2a51"></i></th>
                                <th class="dior-ic-da19ee3bab">Category <i class="fa-solid fa-sort dior-ic-c3c6ca2a51"></i></th>
                                <th class="dior-ic-da19ee3bab">Rating <i class="fa-solid fa-sort dior-ic-c3c6ca2a51"></i></th>
                                <th class="dior-ic-da19ee3bab">Date <i class="fa-solid fa-sort dior-ic-c3c6ca2a51"></i></th>
                                <th class="dior-ic-da19ee3bab">Status <i class="fa-solid fa-sort dior-ic-c3c6ca2a51"></i></th>
                                <th class="dior-ic-da19ee3bab">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $patient_feedback = get_user_meta($user_id, 'dior_feedback', true);
                            $patient_feedback = is_array($patient_feedback) ? $patient_feedback : [];
                            if (empty($patient_feedback) && !empty($dior_demo_mode)) {
                                $patient_feedback = [['doctor_name'=>'Dr. Sarah Smith','rating'=>'5/5','comment'=>'Demo feedback for dashboard UI testing.','date'=>current_time('Y-m-d'),'status'=>'Published']];
                            }
                            ?>
                            <?php if (!empty($patient_feedback)): ?>
                                <?php foreach ($patient_feedback as $feedback): ?>
                                    <tr>
                                        <td><span class="cell-text"><?php echo esc_html($feedback['subject'] ?? ($feedback['title'] ?? 'Feedback')); ?></span></td>
                                        <td><span class="cell-text"><?php echo esc_html($feedback['doctor'] ?? 'Attending Physician'); ?></span></td>
                                        <td><span class="cell-text"><?php echo esc_html($feedback['rating'] ?? '—'); ?></span></td>
                                        <td><span class="cell-text"><?php echo esc_html($feedback['message'] ?? ($feedback['comment'] ?? '')); ?></span></td>
                                        <td><div class="cell-content cell-icon-text"><i class="fa-regular fa-calendar cell-icon"></i><span class="cell-text"><?php echo esc_html($feedback['date'] ?? '—'); ?></span></div></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="dior-empty-state">No feedback submitted yet.</td></tr>
                            <?php endif; ?>

                    </table>
                </div>

                <!-- Footer -->
                <div class="dior-ic-332750da74">
                    0 selected / 5 total
                </div>
            </div>
        </div>
    </div>
</section>
