<section class="dior-tab-panel dior-ic-44a70a0420" id="tab-emergency">
    <script>
    window.dior_ajax_url = "<?php echo esc_url(admin_url('admin-ajax.php')); ?>";
    window.dior_portal_nonce = "<?php echo esc_js(wp_create_nonce('dior_portal_nonce')); ?>";
    </script>
    <?php
    $emerg_user_id = get_current_user_id();
    $emergency_contacts_list = get_user_meta($emerg_user_id, 'dior_emergency_contacts_list', true);
    if (!is_array($emergency_contacts_list) || empty($emergency_contacts_list)) {
        $emergency_contacts_list = [];
        if (!empty($profile['emergency_name']) || !empty($profile['emergency_phone'])) {
            $emergency_contacts_list[] = [
                'name' => $profile['emergency_name'] ?? '',
                'relation' => $profile['emergency_relation'] ?? '—',
                'phone' => $profile['emergency_phone'] ?? '',
                'priority' => get_user_meta($emerg_user_id, 'emergency_priority', true) ?: '—',
            ];
        }
    }
    ?>
    <!-- Breadcrumb Header -->
    <div class="mb-4" style="display:flex;justify-content:space-between;align-items:center;padding:0 24px;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size:20px;font-weight:700;">Emergency Contacts</h4>
        </div>
        <div>
            <ul class="va-breadcrumb-list">
                <li><a href="#"><i class="fas fa-house" style="font-size:14px;color:#4F46E5;"></i></a></li>
                <li><span style="color:#94A3B8;">/</span></li>
                <li class="active"><span>Emergency Contacts</span></li>
            </ul>
        </div>
    </div>


    <!-- Top Action Cards -->
    <div class="dior-ic-597dc7a00c">
        <!-- Request Ambulance -->
        <div class="dior-ic-f5e657b8f6">
            <div class="dior-ic-050317ce29">
                <i class="fas fa-truck-medical"></i>
            </div>
            <h4 class="dior-ic-4f9a9fe05d">Request Ambulance</h4>
            <p class="dior-ic-b4344ed8fa">Immediate emergency assistance</p>
            <button type="button" onmouseover="this.style.background='#EF4444'; this.style.color='#fff';"
                onmouseout="this.style.background='transparent'; this.style.color='#EF4444';"
                class="dior-ic-7f1daa7fb4 btn">Call Now</button>
        </div>

        <!-- Emergency Doctor -->
        <div class="dior-ic-f5e657b8f6">
            <div class="dior-ic-d1eca3c91b">
                <i class="fas fa-user-doctor"></i>
            </div>
            <h4 class="dior-ic-4f9a9fe05d">Emergency Doctor</h4>
            <p class="dior-ic-b4344ed8fa">Connect with available doctors</p>
            <button type="button" onmouseover="this.style.background='#F59E0B'; this.style.color='#fff';"
                onmouseout="this.style.background='transparent'; this.style.color='#F59E0B';"
                class="dior-ic-a0664572fd btn">Contact</button>
        </div>

        <!-- Digital Health Card -->
        <div class="dior-ic-f5e657b8f6">
            <div class="dior-ic-12cb12d607">
                <i class="fas fa-id-card"></i>
            </div>
            <h4 class="dior-ic-4f9a9fe05d">Digital Health Card</h4>
            <p class="dior-ic-b4344ed8fa">Access your medical records</p>
            <button type="button" onmouseover="this.style.background='#14B8A6'; this.style.color='#fff';"
                onmouseout="this.style.background='transparent'; this.style.color='#14B8A6';"
                class="dior-ic-6713853875 btn">View Card</button>
        </div>
    </div>

    <div class="master-table-wrapper">
        <div class="master-table-container">
            <div class="master-table-card">
                <div class="master-table-header">
                    <div class="header-content">
                        <div class="table-title-section">
                            <h2 class="table-title">Emergency Contacts</h2>
                            <div class="title-accent"></div>
                        </div>
                        <div class="header-actions-group">
                            <div class="input-group input-group-sm dior-bs-search-group" style="width: 240px;">

                                <input type="text" id="dior-emerg-search-input" class="form-control border-start-0 ps-0"
                                    placeholder="Search records..." aria-label="Search box" onkeyup="diorFilterEmerg()">
                            </div>
                            <div class="action-buttons d-flex align-items-center gap-1">
                                <button type="button" aria-label="Delete selected items"
                                    class="docs-icon-btn docs-btn-danger btn" title="Delete Selected"
                                    onclick="diorBulkDeleteStaticRows(this)">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <button type="button" aria-label="Add new record"
                                    class="docs-icon-btn docs-btn-primary btn" title="Add Emergency Contact"
                                    onclick="diorOpenEmergencyContactModal()">
                                    <i class="fas fa-plus"></i>
                                </button>
                                <button type="button" aria-label="Export to CSV"
                                    class="docs-icon-btn docs-btn-success btn" title="Export to CSV"
                                    onclick="diorDownloadEmergCSV()">
                                    <i class="fas fa-file-arrow-down"></i>
                                </button>
                                <button type="button" aria-label="Refresh data"
                                    class="docs-icon-btn docs-btn-info btn" title="Refresh Page"
                                    onclick="window.location.reload()">
                                    <i class="fas fa-rotate-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="table-content table-responsive">
                    <table class="va-table table table-hover align-middle mb-0" id="dior-emerg-table">
                        <thead>
                            <tr>
                                <th class="dior-ic-3fa4d8d717" style="width: 40px;">
                                    <input type="checkbox" class="dior-ic-52ff4d551f form-check-input">
                                </th>
                                <th>CONTACT NAME <i class="fas fa-sort"></i></th>
                                <th>RELATION <i class="fas fa-sort"></i></th>
                                <th>PHONE NUMBER <i class="fas fa-sort"></i></th>
                                <th>PRIORITY <i class="fas fa-sort"></i></th>
                                <th style="width: 100px;">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody id="dior-emerg-tbody">
                            <?php if (!empty($emergency_contacts_list)): ?>
                                <?php foreach ($emergency_contacts_list as $idx => $contact): ?>
                                    <tr data-contact-index="<?php echo esc_attr($idx); ?>">
                                        <td><input type="checkbox" class="dior-ic-52ff4d551f form-check-input"></td>
                                        <td><span
                                                class="cell-text fw-semibold text-dark"><?php echo esc_html($contact['name'] ?? 'Emergency Contact'); ?></span>
                                        </td>
                                        <td><span
                                                class="cell-text text-secondary"><?php echo esc_html($contact['relation'] ?? '—'); ?></span>
                                        </td>
                                        <td>
                                            <div class="cell-content cell-icon-text d-flex align-items-center">
                                                <i class="fas fa-phone cell-icon text-primary me-2"
                                                    style="font-size: 13px;"></i>
                                                <a href="tel:<?php echo esc_attr(preg_replace('/[^\d+]/', '', $contact['phone'] ?? '')); ?>"
                                                    class="cell-text text-decoration-none text-dark">
                                                    <?php echo esc_html($contact['phone'] ?? '—'); ?>
                                                </a>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="cell-content">
                                                <?php
                                                $prio = $contact['priority'] ?? 'Emergency';
                                                $badge_class = ($prio === 'Primary')
                                                    ? 'badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-1 small'
                                                    : (($prio === 'Secondary')
                                                        ? 'badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-1 small'
                                                        : 'badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1 small');
                                                ?>
                                                <span
                                                    class="<?php echo esc_attr($badge_class); ?>"><?php echo esc_html($prio); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="cell-actions d-flex align-items-center gap-1">
                                                <button type="button" class="action-icon-btn edit-btn btn" title="Edit Contact"
                                                    onclick="diorEditEmergencyContact(this)">
                                                    <i class="fas fa-pen"></i>
                                                </button>
                                                <button type="button" class="action-icon-btn delete-btn btn"
                                                    title="Delete Contact" onclick="diorDeleteEmergencyContact(this)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr class="dior-emerg-empty-row">
                                    <td colspan="6" class="dior-empty-state text-center py-4 text-muted">No emergency
                                        contact has been added.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>

    <!-- Emergency Contact Modal (Strict Bootstrap 5 Standard) -->
    <div class="modal fade" id="dior-emergency-contact-modal" tabindex="-1" aria-labelledby="diorEmergModalTitle"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div
                    class="modal-header border-bottom py-3 px-4 bg-white d-flex align-items-center justify-content-between">
                    <h5 class="modal-title fw-bold text-dark fs-5 mb-0" id="diorEmergModalTitle">
                        <i class="fas fa-truck-medical text-danger me-2"></i> Add Emergency Contact
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
                        onclick="diorCloseEmergencyContactModal()"></button>
                </div>
                <form id="dior-emerg-contact-form">
                    <input type="hidden" id="dior-emerg-contact-index" name="contact_index" value="">
                    <div class="modal-body p-4 bg-white">
                        <p class="text-secondary small mb-3">Add a trusted emergency contact to be reached immediately
                            in case of urgent medical alerts or clinical decisions.</p>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="dior-emerg-name"
                                    class="form-label small text-uppercase text-secondary fw-semibold mb-1">
                                    Full Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" id="dior-emerg-name" name="emergency_name"
                                    class="form-control rounded-3 py-2" placeholder="e.g. Michael Johnson" required>
                            </div>
                            <div class="col-md-6">
                                <label for="dior-emerg-relation"
                                    class="form-label small text-uppercase text-secondary fw-semibold mb-1">
                                    Relationship <span class="text-danger">*</span>
                                </label>
                                <select id="dior-emerg-relation" name="emergency_relation"
                                    class="form-select rounded-3 py-2" required>
                                    <option value="Spouse">Spouse</option>
                                    <option value="Parent">Parent</option>
                                    <option value="Child">Child</option>
                                    <option value="Sibling">Sibling</option>
                                    <option value="Partner">Partner</option>
                                    <option value="Relative">Relative</option>
                                    <option value="Friend">Friend</option>
                                    <option value="Caregiver">Caregiver</option>
                                    <option value="Guardian">Guardian</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="dior-emerg-phone"
                                    class="form-label small text-uppercase text-secondary fw-semibold mb-1">
                                    Phone Number <span class="text-danger">*</span>
                                </label>
                                <input type="tel" id="dior-emerg-phone" name="emergency_phone"
                                    class="form-control rounded-3 py-2" placeholder="e.g. +1 (555) 014-2099" required>
                            </div>
                            <div class="col-md-6">
                                <label for="dior-emerg-priority"
                                    class="form-label small text-uppercase text-secondary fw-semibold mb-1">
                                    Priority Level
                                </label>
                                <select id="dior-emerg-priority" name="emergency_priority"
                                    class="form-select rounded-3 py-2">
                                    <option value="Emergency">Emergency (Immediate 24/7)</option>
                                    <option value="Primary">Primary (First Contact)</option>
                                    <option value="Secondary">Secondary (Alternate)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top py-3 px-4 bg-white d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-outline-secondary rounded-3 px-4 fw-semibold"
                            data-bs-dismiss="modal" onclick="diorCloseEmergencyContactModal()">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary rounded-3 px-4 fw-semibold shadow-sm"
                            id="dior-emerg-save-btn">
                            <i class="fas fa-check me-1"></i> Save Contact
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</section>