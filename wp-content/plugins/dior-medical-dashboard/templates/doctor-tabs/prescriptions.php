<section class="dior-tab-panel" id="tab-doc-prescriptions">
                    <div class="dior-content-pad">
                        <!-- Page Title Bar -->
                        <div class="dior-page-title-bar new-design">
                            <div class="title-left">
                                <div class="title-icon-box blue-tint dior-ic-cc5b134d4e">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-vector-pen" viewBox="0 0 16 16" style="background:transparent;"><path fill-rule="evenodd" d="M10.646.646a.5.5 0 0 1 .708 0l4 4a.5.5 0 0 1 0 .708l-1.902 1.902-.829 3.313a1.5 1.5 0 0 1-1.024 1.073L1.254 14.746 4.358 4.4A1.5 1.5 0 0 1 5.43 3.377l3.313-.828zm-1.8 2.908-3.173.793a.5.5 0 0 0-.358.342l-2.57 8.565 8.567-2.57a.5.5 0 0 0 .34-.357l.794-3.174-3.6-3.6z"/><path fill-rule="evenodd" d="M2.832 13.228 8 9a1 1 0 1 0-1-1l-4.228 5.168-.026.086z"/></svg>
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
                            class="dior-ic-1fedd7b755">
                            <!-- Card Header -->
                            <div
                                class="dior-ic-5408a35faa">
                                <div
                                    class="dior-ic-3003a67a7f">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-vector-pen" viewBox="0 0 16 16" style="background:transparent;"><path fill-rule="evenodd" d="M10.646.646a.5.5 0 0 1 .708 0l4 4a.5.5 0 0 1 0 .708l-1.902 1.902-.829 3.313a1.5 1.5 0 0 1-1.024 1.073L1.254 14.746 4.358 4.4A1.5 1.5 0 0 1 5.43 3.377l3.313-.828zm-1.8 2.908-3.173.793a.5.5 0 0 0-.358.342l-2.57 8.565 8.567-2.57a.5.5 0 0 0 .34-.357l.794-3.174-3.6-3.6z"/><path fill-rule="evenodd" d="M2.832 13.228 8 9a1 1 0 1 0-1-1l-4.228 5.168-.026.086z"/></svg>
                                </div>
                                <div>
                                    <h3
                                        class="dior-ic-6719eaf923">
                                        Write New Prescription</h3>
                                    <p class="dior-ic-c6ae9353d8">Issue an e-prescription
                                        using FDA/RxNorm approved medications.</p>
                                </div>
                            </div>
                            <!-- Card Body -->
                            <div class="dior-ic-5b1699851f">
                                <form id="dior-doc-rx-form" onsubmit="return false;">
                                    <!-- Patient Selector -->
                                    <div class="dior-ic-6ede89de7f">
                                        <label
                                            class="dior-ic-6d4696b68a">Patient
                                            <span class="dior-ic-dc397e649c">*</span></label>
                                        <select id="rx-patient-select" required
                                            class="dior-ic-b726f124df">
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
                                        class="dior-ic-d183f4597c">
                                        <div
                                            class="dior-ic-0eecc193ae">
                                            <div
                                                class="dior-ic-1657007906">
                                                <i class="fas fa-pills dior-ic-23de06f74e"></i>
                                            </div>
                                            <h4
                                                class="dior-ic-338f3700c0">
                                                Add Medication</h4>
                                        </div>

                                        <!-- Drug Search -->
                                        <div class="dior-ic-0156f8c1f5">
                                            <div
                                                class="dior-ic-cde54b1c1d">
                                                <label
                                                    class="dior-ic-47ad7a8e45">Search
                                                    FDA / RxNorm Approved Medications <span
                                                        class="dior-ic-dc397e649c">*</span></label>
                                                <span
                                                    class="dior-ic-cb222e6f98">
                                                    <i class="fas fa-shield-halved"></i> Official U.S. Govt API
                                                </span>
                                            </div>
                                            <div class="dior-ic-f21edd2770">
                                                <input type="text" id="rx-med-search"
                                                    placeholder="Type drug name (e.g. Amoxicillin, Metformin, Lisinopril)..."
                                                    autocomplete="off" oninput="diorDocSearchMedication(this.value)"
                                                    class="dior-ic-e3d6219556">
                                                <i class="fas fa-magnifying-glass dior-ic-3dd803ff58"
                                                   ></i>
                                            </div>
                                            <div id="rx-med-autocomplete"
                                                class="dior-ic-0543e6dd6c">
                                            </div>
                                            <div id="rx-fda-badge" class="dior-ic-39442e49ca"></div>
                                        </div>

                                        <!-- Dosage + Refills -->
                                        <div
                                            class="dior-ic-666f13630a">
                                            <div>
                                                <label
                                                    class="dior-ic-6d4696b68a">FDA
                                                    Dosage
                                                    &amp; Frequency <span class="dior-ic-dc397e649c">*</span></label>
                                                <input type="text" id="rx-dosage" list="rx-dosage-list"
                                                    placeholder="e.g. 500 mg Tab � 1 tab daily"
                                                    class="dior-ic-3473e8859c">
                                                <datalist id="rx-dosage-list"></datalist>
                                                <small
                                                    class="dior-ic-53db73a32c">Official
                                                    FDA strengths auto-populated on drug selection</small>
                                            </div>
                                            <div>
                                                <label
                                                    class="dior-ic-6d4696b68a">Refills</label>
                                                <input type="text" id="rx-refills" placeholder="e.g. 0 refills"
                                                    class="dior-ic-3473e8859c">
                                            </div>
                                        </div>

                                        <!-- Clinical Notes -->
                                        <div class="dior-ic-a188eaf399">
                                            <label
                                                class="dior-ic-6d4696b68a">Clinical
                                                Notes</label>
                                            <textarea id="rx-notes" rows="3"
                                                placeholder="Take with food. Avoid alcohol. Complete full course..."
                                                class="dior-ic-d134d6f851"></textarea>
                                        </div>

                                        <button type="button" onclick="diorDocAddMedToList()"
                                            class="dior-ic-2686c138d3">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-plus-fill" viewBox="0 0 16 16" style="background:transparent;"><path d="M12 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2M8.5 6v1.5H10a.5.5 0 0 1 0 1H8.5V10a.5.5 0 0 1-1 0V8.5H6a.5.5 0 0 1 0-1h1.5V6a.5.5 0 0 1 1 0"/></svg> Add to Prescription
                                        </button>
                                    </div>

                                    <!-- Medication List Output -->
                                    <div id="rx-med-list" class="dior-ic-6ede89de7f"></div>

                                    <!-- Review & Issue Prescription Button (Opens Confirmation Preview Modal) -->
                                    <button type="button" id="btn-send-rx"
                                        onclick="diorDocPreviewPrescriptionConfirmation()"
                                        class="dior-ic-95961ab95a">
                                        <i class="fas fa-file-prescription"></i> Review & Issue Prescription
                                    </button>
                                </form>
                                <div id="dior-rx-msg"
                                    class="dior-ic-b66315dd69">
                                </div>
                            </div>
                        </div>

                        <!-- All Issued Prescriptions Card -->
                        <div
                            class="dior-ic-6aeb67da43">
                            <div
                                class="dior-ic-5408a35faa">
                                <div
                                    class="dior-ic-3003a67a7f">
                                    <i class="fas fa-prescription-bottle-medical dior-ic-107087ebf8"
                                       ></i>
                                </div>
                                <h3
                                    class="dior-ic-6719eaf923">
                                    All Issued Prescriptions</h3>
                            </div>
                            <div class="table-responsive dior-ic-6599399370">
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
                                                    class="dior-ic-ed042f2324">
                                                    No prescriptions issued yet.</td>
                                            </tr>
                                        <?php else:
                                            foreach ($all_rx as $r): ?>
                                                <tr>
                                                    <td><strong><?php echo esc_html($r['patient_name'] ?? '�'); ?></strong></td>
                                                    <td>
                                                        <?php if (!empty($r['items']) && count($r['items']) > 1): ?>
                                                            <div class="dior-ic-dd21eff28a">
                                                                <span class="dior-ic-5f8cfd0d9f">Rx
                                                                    #<?php echo esc_html($r['id']); ?>
                                                                    (<?php echo count($r['items']); ?> Meds)</span>
                                                                <?php foreach ($r['items'] as $it): ?>
                                                                    <div
                                                                        class="dior-ic-1815812fab">
                                                                        <span
                                                                            class="dior-ic-39d27a65f1"><?php echo esc_html($it['medication']); ?></span>
                                                                        <span
                                                                            class="dior-ic-a608ae0cf1">
                                                                            FDA Approved
                                                                        </span>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="dior-ic-1815812fab">
                                                                <strong><?php echo esc_html($r['medication'] ?? $r['name'] ?? '�'); ?></strong>
                                                                <span
                                                                    class="dior-ic-a608ae0cf1">
                                                                    FDA Approved
                                                                </span>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($r['items']) && count($r['items']) > 1): ?>
                                                            <div class="dior-ic-11931a19b1">
                                                                <?php foreach ($r['items'] as $it): ?>
                                                                    <span
                                                                        class="dior-ic-23c52895b0"><?php echo esc_html($it['dosage']); ?></span>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <?php echo esc_html($r['dosage'] ?? $r['dose'] ?? '�'); ?>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo esc_html($r['date'] ?? '�'); ?></td>
                                                    <td><span
                                                            class="dior-ic-0b3e6c015e"><?php echo esc_html($r['status'] ?? 'Active'); ?></span>
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
