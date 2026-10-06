<section class="dior-tab-panel" id="tab-doc-intake">
                    <div class="dior-content-pad">
                        <div class="dior-page-title-bar new-design">
                            <div class="title-left">
                                <div class="title-icon-box blue-tint dior-ic-cc5b134d4e">
                                    <i class="fas fa-file-shield"></i>
                                </div>
                                <div>
                                    <h1>Medical Intake Forms</h1>
                                    <p class="title-sub">Review all HIPAA-compliant patient submissions from HIPAAtizer.
                                    </p>
                                </div>
                            </div>
                            <div class="title-right">
                                <span class="dior-ic-0b3e6c015e dior-ic-46acf2e558"><i class="fas fa-file-shield"></i>
                                    <?php echo count($intake_patients); ?> Submissions</span>
                            </div>
                        </div>
                        <?php if (empty($intake_patients)): ?>
                            <div class="dior-dash-table-card dior-box-card">
                                <div class="dior-empty-state dior-ic-139ad45bc3"><i class="fas fa-file-shield dior-ic-5b77fe99d4"
                                       ></i>
                                    <p>No intake forms submitted yet.</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="dior-ic-82fb1b9674">
                                <?php foreach ($patients as $p):
                                    if (!$p['has_intake'])
                                        continue;
                                    $intake = get_user_meta($p['user_id'], 'dior_hipaa_intake', true);
                                    if (empty($intake))
                                        continue;
                                    ?>
                                    <div class="dior-dash-table-card dior-box-card dior-ic-9e3385c59a">
                                        <div class="box-title-row dior-ic-c218adf5ad">
                                            <div class="dior-ic-1494dfa4bf">
                                                <div
                                                    class="dior-ic-26845f938e">
                                                    <?php echo esc_html($p['initials']); ?>
                                                </div>
                                                <div>
                                                    <strong
                                                        class="dior-ic-b6fcb4e119"><?php echo esc_html($p['full_name']); ?></strong><br>
                                                    <small
                                                        class="dior-ic-dd152f8f76"><?php echo esc_html($p['patient_id']); ?></small>
                                                </div>
                                            </div>
                                            <span class="dior-ic-0b3e6c015e"><i class="fas fa-circle-check"></i>
                                                <?php echo esc_html(date('M j, Y', strtotime($intake['submitted_at'] ?? ''))); ?></span>
                                        </div>
                                        <div class="table-responsive">
                                            <div class="dior-form-row-2 dior-ic-dcad534252">
                                                <div><small class="dior-ic-eecd330b8e">DOB</small>
                                                    <div><?php echo esc_html($intake['dob'] ?: '�'); ?></div>
                                                </div>
                                                <div><small class="dior-ic-eecd330b8e">Phone</small>
                                                    <div><?php echo esc_html($intake['phone'] ?: '�'); ?></div>
                                                </div>
                                            </div>
                                            <?php if (!empty($intake['symptoms'])): ?>
                                                <div class="dior-ic-dcad534252">
                                                    <small class="dior-ic-eecd330b8e">Symptoms</small>
                                                    <div class="dior-ic-fb0b2ebc07">
                                                        <?php echo esc_html($intake['symptoms']); ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($intake['allergies'])): ?>
                                                <div class="dior-form-row-2 dior-ic-dcad534252">
                                                    <div><small class="dior-ic-eecd330b8e">Allergies</small>
                                                        <div><?php echo esc_html($intake['allergies']); ?></div>
                                                    </div>
                                                    <div><small class="dior-ic-eecd330b8e">Medications</small>
                                                        <div><?php echo esc_html($intake['medications'] ?: '�'); ?></div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($intake['medical_history'])): ?>
                                                <div class="dior-ic-dcad534252">
                                                    <small class="dior-ic-eecd330b8e">Medical History</small>
                                                    <div class="dior-ic-fb0b2ebc07">
                                                        <?php echo nl2br(esc_html($intake['medical_history'])); ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            <div class="dior-ic-66553b4f65">
                                                <button class="dior-btn-gold-primary dior-ic-135a40e644 dior-inline-intake-btn"
                                                    onclick="diorDocViewPatient(<?php echo (int) $p['user_id']; ?>)">
                                                    <i class="fas fa-user-doctor"></i> Full Profile
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
