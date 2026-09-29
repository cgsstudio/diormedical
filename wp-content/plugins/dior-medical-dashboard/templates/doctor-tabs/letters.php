<section class="dior-tab-panel" id="tab-doc-letters">
                    <div class="dior-content-pad">
                        <div class="dior-page-title-bar new-design">
                            <div class="title-left">
                                <div class="title-icon-box dior-ic-6ecb6cf88c"
                                   >
                                    <i class="fa-solid fa-file-signature"></i>
                                </div>
                                <div>
                                    <h1>Work Excuse & Medical Letter Generator</h1>
                                    <p class="title-sub">Issue certified medical absence notes, school excuses, and
                                        fit-to-work clearance certificates directly to patients.</p>
                                </div>
                            </div>
                        </div>

                        <div class="dior-ic-0d5e3b472c">

                            <!-- Generator Form Card -->
                            <div class="dior-dash-table-card dior-box-card">
                                <div class="box-title-row">
                                    <h3><i class="fa-solid fa-pen-nib dior-ic-7c92a7e454"></i> Letter Configuration
                                    </h3>
                                </div>
                                <div class="table-responsive dior-ic-7335224e9f">
                                    <form id="doc-standalone-letter-form"
                                        onsubmit="diorDocSubmitStandaloneLetter(event)">

                                        <div class="dior-ic-a188eaf399">
                                            <label
                                                class="dior-ic-ca20e79b11">Select
                                                Patient <span class="dior-ic-dc397e649c">*</span></label>
                                            <select id="doc_gen_patient_select" required
                                                onchange="diorDocUpdateStandaloneLetterPreview()"
                                                class="dior-ic-1dfc64a6a1">
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
                                            class="dior-ic-ee2f919aec">
                                            <div>
                                                <label
                                                    class="dior-ic-ca20e79b11">Letter
                                                    Type</label>
                                                <select id="doc_gen_letter_type"
                                                    onchange="diorDocUpdateStandaloneLetterPreview()"
                                                    class="dior-ic-7491978f1c">
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
                                                    class="dior-ic-ca20e79b11">Diagnosed
                                                    Condition / Reason</label>
                                                <input type="text" id="doc_gen_condition"
                                                    placeholder="e.g. Acute Upper Respiratory Illness" value=""
                                                    oninput="diorDocUpdateStandaloneLetterPreview()"
                                                    class="dior-ic-24bf34c8cd">
                                            </div>
                                        </div>

                                        <!-- Quick Clinical Condition Preset Chips -->
                                        <div class="dior-ic-a188eaf399">
                                            <span
                                                class="dior-ic-a3745fc346">Quick
                                                Presets:</span>
                                            <div class="dior-ic-67d659a807">
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
                                            class="dior-ic-ee2f919aec">
                                            <div>
                                                <label
                                                    class="dior-ic-ca20e79b11">Excused
                                                    From (Start Date)</label>
                                                <input type="date" id="doc_gen_start_date"
                                                    value="<?php echo date('Y-m-d'); ?>"
                                                    onchange="diorDocUpdateStandaloneLetterPreview()"
                                                    class="dior-ic-9eb8b8e43c">
                                            </div>
                                            <div>
                                                <label
                                                    class="dior-ic-ca20e79b11">Expected
                                                    Return Date</label>
                                                <input type="date" id="doc_gen_return_date"
                                                    value="<?php echo date('Y-m-d', strtotime('+3 days')); ?>"
                                                    onchange="diorDocUpdateStandaloneLetterPreview()"
                                                    class="dior-ic-9eb8b8e43c">
                                            </div>
                                        </div>

                                        <div class="dior-ic-a188eaf399">
                                            <label
                                                class="dior-ic-ca20e79b11">Activity
                                                / Duty Restrictions</label>
                                            <select id="doc_gen_restrictions"
                                                onchange="diorDocUpdateStandaloneLetterPreview()"
                                                class="dior-ic-7491978f1c">
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

                                        <div class="dior-ic-6ede89de7f">
                                            <label
                                                class="dior-ic-ca20e79b11">Physician
                                                Remarks & Clinical Instructions</label>
                                            <textarea id="doc_gen_remarks" rows="3"
                                                placeholder="Patient has been clinically assessed via telehealth. Patient is advised adequate rest and hydration..."
                                                oninput="diorDocUpdateStandaloneLetterPreview()"
                                                class="dior-ic-89a1401bf4"></textarea>
                                        </div>

                                        <div class="dior-ic-6ece0cb758">
                                            <button type="button" class="dior-btn-sm dior-btn-ghost dior-ic-d2e9533327"
                                                onclick="diorDocPrintStandalonePreview()"
                                               >
                                                <i class="fa-solid fa-print"></i> Print / Save PDF
                                            </button>
                                            <button type="submit" id="doc-gen-submit-btn"
                                                class="dior-btn-sm dior-btn-purple dior-ic-43c7234a13"
                                               >
                                                <i class="fa-solid fa-paper-plane"></i> Publish to Patient Portal
                                            </button>
                                        </div>

                                    </form>
                                </div>
                            </div>

                            <!-- Live Preview Card -->
                            <div class="dior-dash-table-card dior-box-card">
                                <div class="box-title-row">
                                    <h3><i class="fa-solid fa-certificate dior-ic-d97e7c74af"></i> Certified
                                        Letterhead Preview</h3>
                                </div>
                                <div class="table-responsive dior-ic-7335224e9f">
                                    <div id="doc-standalone-letter-preview-box"
                                        class="dior-ic-8064158358">
                                        <!-- Centered Caduceus Watermark -->
                                        <div class="dior-letter-watermark dior-ic-a9134745c4"
                                           >
                                            <svg viewBox="0 0 24 24" fill="#00A896" class="dior-ic-345ad109d2">
                                                <path
                                                    d="M12 2C11.45 2 11 2.45 11 3V4.07C8.5 4.3 6.64 6.22 6.64 8.65C6.64 10.42 7.6 11.95 9.04 12.78C8.38 13.56 8 14.57 8 15.68C8 17.5 9.21 19.06 10.89 19.57L10 21H8V22H16V21H14L13.11 19.57C14.79 19.06 16 17.5 16 15.68C16 14.57 15.62 13.56 14.96 12.78C16.4 11.95 17.36 10.42 17.36 8.65C17.36 6.22 15.5 4.3 13 4.07V3C13 2.45 12.55 2 12 2Z" />
                                            </svg>
                                        </div>

                                        <div class="dior-ic-c56b801a81">
                                            <!-- Top Header: Logo on Left, Contact Metadata on Right -->
                                            <div
                                                class="dior-ic-ea24e26d0e">
                                                <div class="dior-ic-1494dfa4bf">
                                                    <div
                                                        class="dior-ic-dd039a1d36">
                                                        <i class="fa-solid fa-staff-snake"></i>
                                                    </div>
                                                    <div>
                                                        <h2
                                                            class="dior-ic-04ddaa7335">
                                                            DIOR MEDICAL</h2>
                                                        <div
                                                            class="dior-ic-eb4d0c28f7">
                                                            TELEHEALTH &bull; VIRTUAL URGENT CARE</div>
                                                    </div>
                                                </div>
                                                <div
                                                    class="dior-ic-ec644a8292">
                                                    <div class="dior-ic-f349461641">+1 (800) 555-DIOR</div>
                                                    <div>www.diormedical.com</div>
                                                    <div>care@diormedical.com</div>
                                                    <div>100 Medical Plaza, Suite 400</div>
                                                </div>
                                            </div>

                                            <!-- Recipient & Date Bar -->
                                            <div
                                                class="dior-ic-ba26d03800">
                                                <div>
                                                    <span
                                                        class="dior-ic-fc2b96c7b7">TO:</span>
                                                    <strong id="doc-ltr-preview-to-name"
                                                        class="dior-ic-f5842b2ef6">[Select
                                                        Patient]</strong>
                                                    <span id="doc-ltr-preview-to-meta"
                                                        class="dior-ic-7182d0fd10">Patient
                                                        ID: &bull; DOB: </span>
                                                </div>
                                                <div class="dior-ic-a4963a5fa2">
                                                    <span id="doc-ltr-preview-date"
                                                        class="dior-ic-c7e5d1fa23"><?php echo date('jS M Y'); ?></span>
                                                </div>
                                            </div>

                                            <!-- Salutation -->
                                            <p id="doc-ltr-preview-salutation"
                                                class="dior-ic-34a603bb13">
                                                Dear Patient,</p>

                                            <!-- Letter Content Body -->
                                            <div id="doc-standalone-letter-preview-content"
                                                class="dior-ic-9464c90729">
                                                <p class="dior-ic-3ad814dabf">Please select
                                                    a patient from the dropdown on the left to generate the live
                                                    letterhead preview.</p>
                                            </div>

                                            <!-- Sign-off & Doctor Signature -->
                                            <div
                                                class="dior-ic-5b094ce043">
                                                <div class="dior-ic-b6d006a883">
                                                    <i class="fa-solid fa-shield-halved dior-ic-519cc03b27"></i> DEA
                                                    21 CFR & HIPAA Security Standard
                                                </div>
                                                <div class="dior-ic-a4963a5fa2">
                                                    <span
                                                        class="dior-ic-3e1ca09f83">Sincerely
                                                        yours,</span>
                                                    <div id="doc-ltr-preview-signature-wrap"
                                                        class="dior-ic-56e6c1afa6">
                                                        <?php if (!empty($doctor['signature_url'])): ?>
                                                            <img id="doc-ltr-preview-sig-img"
                                                                src="<?php echo esc_url($doctor['signature_url']); ?>"
                                                                alt="Doctor Signature"
                                                                class="dior-ic-b74f95b4ef">
                                                        <?php else: ?>
                                                            <span id="doc-ltr-preview-sig-text"
                                                                class="dior-ic-ee636aba33">/s/
                                                                <?php echo esc_html($doctor['full_name']); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div
                                                        id="doc-ltr-preview-doc-name" class="dior-ic-1fe226bd81">
                                                        <?php echo esc_html($doctor['full_name']); ?></div>
                                                    <div
                                                        id="doc-ltr-preview-doc-meta" class="dior-ic-ab82c359e7">
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
