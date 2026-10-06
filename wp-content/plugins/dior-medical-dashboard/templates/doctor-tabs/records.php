<section class="dior-tab-panel" id="tab-doc-records">
                    <div class="dior-content-pad">
                        <div class="dior-page-title-bar new-design">
                            <div class="title-left">
                                <div class="title-icon-box dior-ic-9564331faa"
                                   >
                                    <i class="fas fa-folder-open"></i>
                                </div>
                                <div>
                                    <h1>Medical Records & Clinical Files</h1>
                                    <p class="title-sub">Secure HIPAA-compliant vault of all patient lab reports,
                                        imaging, excuses, and signed consent records.</p>
                                </div>
                            </div>
                            <div class="title-right dior-ic-a310a40907">
                                <button type="button" class="dior-btn-sm dior-btn-ghost dior-ic-87c1296b6e"
                                    onclick="diorDocOpenUploadModal()"
                                   >
                                    <i class="fas fa-cloud-arrow-up dior-ic-d97e7c74af"></i>
                                    <span>Upload Document</span>
                                </button>
                                <button type="button" class="dior-btn-sm dior-btn-purple dior-ic-87c1296b6e"
                                    onclick="diorDocSwitchTab('doc-letters')"
                                   >
                                    <i class="fas fa-file-signature"></i>
                                    <span>Issue Work Excuse</span>
                                </button>
                            </div>
                        </div>

                        <!-- Records Table Card -->
                        <div class="dior-dash-table-card dior-box-card">
                            <div class="box-title-row dior-ic-359cca9cac"
                               >
                                <div class="dior-filter-pill-group" id="doc-records-filter-pills">
                                    <button type="button" class="dior-filter-pill active"
                                        onclick="diorDocFilterCategory('all', this)">
                                        All Documents <span class="pill-count"><?php echo count($documents); ?></span>
                                    </button>
                                    <button type="button" class="dior-filter-pill"
                                        onclick="diorDocFilterCategory('consent', this)">
                                        <i class="fas fa-shield-halved dior-ic-d97e7c74af"></i> Consents
                                    </button>
                                    <button type="button" class="dior-filter-pill"
                                        onclick="diorDocFilterCategory('excuse', this)">
                                        <i class="fas fa-file-signature dior-ic-7c92a7e454"></i> Excuses &
                                        Letters
                                    </button>
                                    <button type="button" class="dior-filter-pill"
                                        onclick="diorDocFilterCategory('lab', this)">
                                        <i class="fas fa-flask-vial dior-ic-dd152f8f76"></i> Lab Reports
                                    </button>
                                    <button type="button" class="dior-filter-pill"
                                        onclick="diorDocFilterCategory('clinical', this)">
                                        <i class="fas fa-notes-medical dior-ic-2582fe98a9"></i> Clinical Notes
                                    </button>
                                </div>
                                <div class="dior-ic-daa0da3537">
                                    <input type="text" class="dior-search-input dior-ic-7ecf1d52aa"
                                        placeholder="Search records or patients�"
                                        oninput="diorDocFilterRecordsTable(this.value)"
                                       >
                                </div>
                            </div>
                            <div class="dior-card-body p-0 dior-ic-2c96cff628">
                                <table class="dior-clean-table dior-ic-6777496b4d" id="dior-doc-records-table">
                                    <thead>
                                        <tr>
                                            <th>Patient</th>
                                            <th>Document Title</th>
                                            <th>Category</th>
                                            <th>Date Issued</th>
                                            <th>Author</th>
                                            <th>Format</th>
                                            <th class="dior-ic-a4963a5fa2">Actions</th>
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
                                                        <div class="dior-ic-6000f797e8">
                                                            <?php echo esc_html($doc['patient_name']); ?></div>
                                                        <code
                                                            class="dior-ic-9bcf9a5e34"><?php echo esc_html($doc['patient_id_num']); ?></code>
                                                    </td>
                                                    <td>
                                                        <div class="dior-doc-cell">
                                                            <div class="dior-doc-icon-wrap <?php echo $doc_icon_color; ?>">
                                                                <i class="far fa-file-pdf"></i>
                                                            </div>
                                                            <div>
                                                                <strong
                                                                    class="dior-ic-314fb45eb9"><?php echo esc_html($doc['title']); ?></strong>
                                                                <span class="dior-ic-b6d006a883">ID:
                                                                    <?php echo esc_html($doc['id']); ?></span>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="dior-badge-pill <?php echo $badge_class; ?>">
                                                            <i class="fas <?php echo $cat_icon; ?>"></i>
                                                            <?php echo esc_html($cat_raw); ?>
                                                        </span>
                                                    </td>
                                                    <td class="dior-ic-1e00065e7a">
                                                        <?php echo esc_html($doc['date']); ?></td>
                                                    <td class="dior-ic-ff010970cf">
                                                        <?php echo esc_html($doc['author']); ?></td>
                                                    <td><strong
                                                            class="dior-ic-1c3bfa790b"><?php echo esc_html($doc['file_type'] ?? 'PDF'); ?></strong>
                                                    </td>
                                                    <td class="dior-ic-a4963a5fa2">
                                                        <div
                                                            class="dior-ic-58d552b49d">
                                                            <a href="<?php echo esc_url($stream_url); ?>" target="_blank"
                                                                class="dior-btn-sm dior-btn-primary"
                                                                title="View Document in Viewer">
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#3b82f6" class="bi bi-eye" viewBox="0 0 16 16" style="background:transparent;"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/><path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/></svg> View
                                                            </a>
                                                            <button type="button" class="dior-btn-sm dior-btn-ghost"
                                                                onclick="diorDocViewPatient(<?php echo $pid_val; ?>)"
                                                                title="Open Patient Chart">
                                                                <i class="fas fa-folder-open"></i> Chart
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" class="dior-ic-dd81873a1f">
                                                    <i class="fas fa-folder-open fa-3x dior-ic-14661aa516"
                                                       ></i>
                                                    <strong
                                                        class="dior-ic-9b25bc6510">No
                                                        Medical Records on File</strong>
                                                    <span class="dior-ic-76a7d6331f">Upload lab reports, clinical encounter
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
