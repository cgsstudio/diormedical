<?php
$doc_ov_patients = is_array($patients ?? []) ? array_values($patients) : [];
$doc_ov_appts = is_array($appointments ?? []) ? array_values($appointments) : [];
$doc_ov_p = static function($index) use ($doc_ov_patients) { return $doc_ov_patients[$index] ?? []; };
$doc_ov_a = static function($index) use ($doc_ov_appts) { return $doc_ov_appts[$index] ?? []; };
?>
<section class="dior-tab-panel active" id="tab-doc-overview">
    <div class="dior-content-pad dior-ic-fa45432667">
        
        <!-- Top Stats Row -->
        <div class="dior-ic-0b79645d2d">
            <!-- 20 Appointments -->
            <div class="dior-ic-a7ca70ae37">
                <div>
                    <h3 class="dior-ic-8ae743ee9a">20</h3>
                    <p class="dior-ic-3e312440a1">Appointments</p>
                </div>
                <div class="dior-ic-f5fbf05210">
                    <i class="fa-solid fa-calendar-days"></i>
                </div>
            </div>
            
            <!-- 90 Upcoming Appointments -->
            <div class="dior-ic-a7ca70ae37">
                <div>
                    <h3 class="dior-ic-bfe1a2f97f">90</h3>
                    <p class="dior-ic-3e312440a1">Upcoming Appointments</p>
                </div>
                <div class="dior-ic-42e416bf09">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </div>
            </div>

            <!-- 23 New Patients -->
            <div class="dior-ic-a7ca70ae37">
                <div>
                    <h3 class="dior-ic-93a65a41bf">23</h3>
                    <p class="dior-ic-3e312440a1">New Patients</p>
                </div>
                <div class="dior-ic-bc1a95728b">
                    <i class="fa-solid fa-user-doctor"></i>
                </div>
            </div>

            <!-- $500.00 Total Earning -->
            <div class="dior-ic-a7ca70ae37">
                <div>
                    <h3 class="dior-ic-af94708f37">$500.00</h3>
                    <p class="dior-ic-3e312440a1">Total Earning</p>
                </div>
                <div class="dior-ic-561edb7729">
                    <i class="fa-solid fa-dollar-sign"></i>
                </div>
            </div>
        </div>

        <!-- Layout: Left Column (70%) & Right Column (30%) -->
        <div class="dior-ic-91d75bbe66">
            
            <!-- LEFT COLUMN -->
            <div class="dior-ic-718b77eef9">
                
                <!-- Today's Schedule -->
                <div class="dior-today-schedule-card dior-ic-392879226e">
                    <div class="dior-ic-04ad0a1db6">
                        <h3 class="dior-schedule-title dior-ic-d81814bb74">Today's Schedule</h3>
                        <div class="dior-ic-46943d0aea">
                            <span class="dior-status-badge completed dior-ic-7e23bf7d38">2 Completed</span>
                            <span class="dior-status-badge upcoming dior-ic-86b6249051">3 Upcoming</span>
                            <span class="dior-status-badge cancelled dior-ic-ff5bc1816f">1 Cancel</span>
                        </div>
                    </div>
                    
                    <div class="dior-ic-74faafe097"></div>
                    
                    <div class="dior-timeline-container dior-ic-6bef1b8a2f">
                        <!-- Horizontal Timeline Line -->
                        <div class="dior-ic-01dedfb553"></div>
                        
                        <!-- 2:00 PM <?php echo esc_html(($doc_ov_p(2)['full_name'] ?? 'Patient')); ?> -->
                        <div class="dior-timeline-item dior-ic-21fe9f905f">
                            <div class="dior-timeline-time dior-ic-342f518ee6">2:00 PM</div>
                            <div class="dior-timeline-dot dior-ic-bb9be6acb8"></div>
                            <div class="dior-patient-card dior-ic-60def3c08a">
                                <div class="dior-ic-0587d65489">
                                    <div class="dior-ic-7a475ce5e6">
                                        <img src="assets/images/users/user-1.png" onerror="this.src='https://ui-avatars.com/api/?name=Mia+Song&background=random';" class="dior-ic-e16079f983">
                                    </div>
                                    <div>
                                        <h4 class="dior-patient-name dior-ic-9499363e29"><?php echo esc_html(($doc_ov_p(2)['full_name'] ?? 'Patient')); ?></h4>
                                        <p class="dior-patient-id dior-ic-ba3204a92d"><?php echo esc_html(($doc_ov_p(2)['patient_id'] ?? '')); ?></p>
                                    </div>
                                </div>
                                <div class="dior-ic-0587d65489">
                                    <i class="fa-solid fa-wifi dior-ic-41a3b51c9b"></i>
                                    <span class="dior-status-badge in-progress dior-ic-883ee42752">In Progress</span>
                                </div>
                            </div>
                        </div>

                        <!-- 1:00 PM <?php echo esc_html(($doc_ov_p(0)['full_name'] ?? 'Patient')); ?> -->
                        <div class="dior-timeline-item dior-ic-21fe9f905f">
                            <div class="dior-timeline-time dior-ic-b70e165e15">1:00 PM</div>
                            <div class="dior-timeline-dot dior-ic-bb9be6acb8"></div>
                            <div class="dior-patient-card dior-ic-60def3c08a">
                                <div class="dior-ic-0587d65489">
                                    <div class="dior-ic-7a475ce5e6">
                                        <img src="assets/images/users/user-2.png" onerror="this.src='https://ui-avatars.com/api/?name=John+Johnson&background=random';" class="dior-ic-e16079f983">
                                    </div>
                                    <div>
                                        <h4 class="dior-patient-name dior-ic-9499363e29"><?php echo esc_html(($doc_ov_p(0)['full_name'] ?? 'Patient')); ?></h4>
                                        <p class="dior-patient-id dior-ic-ba3204a92d"><?php echo esc_html(($doc_ov_p(0)['patient_id'] ?? '')); ?></p>
                                    </div>
                                </div>
                                <span class="dior-status-badge completed dior-ic-e96ca64f18">Completed</span>
                            </div>
                        </div>

                        <!-- 12:00 PM <?php echo esc_html(($doc_ov_p(3)['full_name'] ?? 'Patient')); ?> -->
                        <div class="dior-timeline-item dior-ic-21fe9f905f">
                            <div class="dior-timeline-time dior-ic-b70e165e15">12:00 PM</div>
                            <div class="dior-timeline-dot dior-ic-bb9be6acb8"></div>
                            <div class="dior-patient-card dior-ic-60def3c08a">
                                <div class="dior-ic-0587d65489">
                                    <div class="dior-ic-7a475ce5e6">
                                        <img src="assets/images/users/user-3.png" onerror="this.src='https://ui-avatars.com/api/?name=Richard+Davis&background=random';" class="dior-ic-e16079f983">
                                    </div>
                                    <div>
                                        <h4 class="dior-patient-name dior-ic-9499363e29"><?php echo esc_html(($doc_ov_p(3)['full_name'] ?? 'Patient')); ?></h4>
                                        <p class="dior-patient-id dior-ic-ba3204a92d"><?php echo esc_html(($doc_ov_p(3)['patient_id'] ?? '')); ?></p>
                                    </div>
                                </div>
                                <span class="dior-status-badge cancelled dior-ic-74f7f8f689">Cancelled</span>
                            </div>
                        </div>

                        <!-- 3:00 PM <?php echo esc_html(($doc_ov_p(1)['full_name'] ?? 'Patient')); ?> -->
                        <div class="dior-timeline-item dior-ic-16ff2bd3ea">
                            <div class="dior-timeline-time dior-ic-342f518ee6">3:00 PM</div>
                            <div class="dior-timeline-dot dior-ic-c246c46a08"></div>
                            <div class="dior-patient-card dior-ic-60def3c08a">
                                <div class="dior-ic-0587d65489">
                                    <div class="dior-ic-7a475ce5e6">
                                        <img src="assets/images/users/user-4.png" onerror="this.src='https://ui-avatars.com/api/?name=Elizabeth+Brown&background=random';" class="dior-ic-e16079f983">
                                    </div>
                                    <div>
                                        <h4 class="dior-patient-name dior-ic-9499363e29"><?php echo esc_html(($doc_ov_p(1)['full_name'] ?? 'Patient')); ?></h4>
                                        <p class="dior-patient-id dior-ic-ba3204a92d"><?php echo esc_html(($doc_ov_p(1)['patient_id'] ?? '')); ?></p>
                                    </div>
                                </div>
                                <div class="dior-ic-c1dfa03f08">
                                    <button class="dior-ic-ced0b969a3"><i class="fa-solid fa-video"></i></button>
                                    <button class="dior-ic-6e4ea5fbfe"><i class="fa-solid fa-envelope"></i></button>
                                    <button class="dior-ic-d51e829b76"><i class="fa-solid fa-play"></i> Join Now</button>
                                    <span class="dior-status-badge upcoming dior-ic-6a3e42737d">Upcoming</span>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Latest Appointments -->
                <div class="dior-ic-381ba3482d">
                    <h3 class="dior-ic-3d6cad599a">Latest Appointments</h3>
                    <table class="dior-ic-0a1cff97a2">
                        <thead>
                            <tr>
                                <th class="dior-ic-adb35140e1">Appointments Id</th>
                                <th class="dior-ic-adb35140e1">Patient Name</th>
                                <th class="dior-ic-adb35140e1">Date</th>
                                <th class="dior-ic-adb35140e1">Disease</th>
                                <th class="dior-ic-adb35140e1">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="dior-ic-3dc7ed4ba9">APP00123</td>
                                <td class="dior-ic-3dc7ed4ba9"><?php echo esc_html(($doc_ov_p(2)['full_name'] ?? 'Patient')); ?></td>
                                <td class="dior-ic-3dc7ed4ba9"><?php echo esc_html(($doc_ov_a(0)['date'] ?? $doc_ov_a(0)['appt_date'] ?? "")); ?></td>
                                <td class="dior-ic-3dc7ed4ba9">Fever</td>
                                <td class="dior-ic-67d625653d">
                                    <button class="dior-ic-181ed7f5e2">Prescription</button>
                                    <button class="dior-ic-9c14b38677">Details</button>
                                </td>
                            </tr>
                            <tr>
                                <td class="dior-ic-75dbaf618e">APP00111</td>
                                <td class="dior-ic-3dc7ed4ba9"><?php echo esc_html(($doc_ov_p(0)['full_name'] ?? 'Patient')); ?></td>
                                <td class="dior-ic-3dc7ed4ba9"><?php echo esc_html(($doc_ov_a(1)['date'] ?? $doc_ov_a(1)['appt_date'] ?? "")); ?></td>
                                <td class="dior-ic-3dc7ed4ba9">Cholera</td>
                                <td class="dior-ic-67d625653d">
                                   <button class="dior-ic-181ed7f5e2">Prescription</button>
                                    <button class="dior-ic-9c14b38677">Details</button>
                                </td>
                            </tr>
                            <tr>
                                <td class="dior-ic-75dbaf618e">APP00112</td>
                                <td class="dior-ic-3dc7ed4ba9"><?php echo esc_html(($doc_ov_p(1)['full_name'] ?? 'Patient')); ?></td>
                                <td class="dior-ic-3dc7ed4ba9">Aug 26, 2026</td>
                                <td class="dior-ic-3dc7ed4ba9">Jaundice</td>
                                <td class="dior-ic-67d625653d">
                                    <button class="dior-ic-181ed7f5e2">Prescription</button>
                                    <button class="dior-ic-9c14b38677">Details</button>
                                 </td>
                            </tr>
                            <tr>
                                <td class="dior-ic-75dbaf618e">APP00112</td>
                                <td class="dior-ic-3dc7ed4ba9"><?php echo esc_html(($doc_ov_p(1)['full_name'] ?? 'Patient')); ?></td>
                                <td class="dior-ic-3dc7ed4ba9">Aug 26, 2026</td>
                                <td class="dior-ic-3dc7ed4ba9">Typhoid</td>
                                <td class="dior-ic-67d625653d">
                                   <button class="dior-ic-181ed7f5e2">Prescription</button>
                                    <button class="dior-ic-9c14b38677">Details</button>
                                </td>
                            </tr>
                            <tr>
                                <td class="dior-ic-75dbaf618e">APP00112</td>
                                <td class="dior-ic-3dc7ed4ba9"><?php echo esc_html(($doc_ov_p(1)['full_name'] ?? 'Patient')); ?></td>
                                <td class="dior-ic-3dc7ed4ba9">Aug 25, 2026</td>
                                <td class="dior-ic-3dc7ed4ba9">Malaria</td>
                                <td class="dior-ic-67d625653d">
                                   <button class="dior-ic-181ed7f5e2">Prescription</button>
                                    <button class="dior-ic-9c14b38677">Details</button>
                                 </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Prescriptions Overview -->
                <div class="dior-ic-c15a34d2ab">
                    <div class="dior-ic-04ad0a1db6">
                        <h3 class="dior-ic-6ab65d8aea">Prescriptions Overview</h3>
                        <div class="dior-ic-46943d0aea">
                            <span class="dior-ic-8d6aa882e7">45 Active</span>
                            <span class="dior-ic-5e27deef96">12 Inactive</span>
                            <span class="dior-ic-91079b9043">6 Expired</span>
                        </div>
                    </div>
                    <table class="dior-ic-0a1cff97a2">
                        <thead>
                            <tr>
                                <th class="dior-ic-62123a759b">Prescriptions Id</th>
                                <th class="dior-ic-62123a759b">Patient Name</th>
                                <th class="dior-ic-62123a759b">Medication</th>
                                <th class="dior-ic-62123a759b">Dosage</th>
                                <th class="dior-ic-ce3319dfdb">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="dior-ic-d8b8fd36b9">PRS00123</td>
                                <td class="dior-ic-16ae42b9ea"><?php echo esc_html(($doc_ov_p(2)['full_name'] ?? 'Patient')); ?></td>
                                <td class="dior-ic-16ae42b9ea">Amlodipine</td>
                                <td class="dior-ic-16ae42b9ea">5mg</td>
                                <td class="dior-ic-a7f74aa244"><span class="dior-ic-ae0e475731">Active</span></td>
                            </tr>
                            <tr>
                                <td class="dior-ic-d8b8fd36b9">PRS00111</td>
                                <td class="dior-ic-16ae42b9ea"><?php echo esc_html(($doc_ov_p(0)['full_name'] ?? 'Patient')); ?></td>
                                <td class="dior-ic-16ae42b9ea">Amoxicillin</td>
                                <td class="dior-ic-16ae42b9ea">500mg</td>
                                <td class="dior-ic-a7f74aa244"><span class="dior-ic-4dfda8fd3e">Inactive</span></td>
                            </tr>
                            <tr>
                                <td class="dior-ic-d8b8fd36b9">PRS00112</td>
                                <td class="dior-ic-16ae42b9ea"><?php echo esc_html(($doc_ov_p(1)['full_name'] ?? 'Patient')); ?></td>
                                <td class="dior-ic-16ae42b9ea">Atorvastatin</td>
                                <td class="dior-ic-16ae42b9ea">20mg</td>
                                <td class="dior-ic-a7f74aa244"><span class="dior-ic-ae0e475731">Active</span></td>
                            </tr>
                            <tr>
                                <td class="dior-ic-d8b8fd36b9">PRS00112</td>
                                <td class="dior-ic-16ae42b9ea"><?php echo esc_html(($doc_ov_p(1)['full_name'] ?? 'Patient')); ?></td>
                                <td class="dior-ic-16ae42b9ea">Ibuprofen</td>
                                <td class="dior-ic-16ae42b9ea">400mg</td>
                                <td class="dior-ic-a7f74aa244"><span class="dior-ic-ae0e475731">Active</span></td>
                            </tr>
                            <tr>
                                <td class="dior-ic-d8b8fd36b9">PRS00112</td>
                                <td class="dior-ic-16ae42b9ea"><?php echo esc_html(($doc_ov_p(1)['full_name'] ?? 'Patient')); ?></td>
                                <td class="dior-ic-16ae42b9ea">Lisinopril</td>
                                <td class="dior-ic-16ae42b9ea">10mg</td>
                                <td class="dior-ic-a7f74aa244"><span class="dior-ic-ae0e475731">Active</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Latest Lab Results -->
                <div class="dior-ic-c15a34d2ab">
                    <h3 class="dior-ic-7501cf3804">Latest Lab Results</h3>
                    <table class="dior-ic-0a1cff97a2">
                        <thead>
                            <tr>
                                <th class="dior-ic-6267fdef57">Patient Id</th>
                                <th class="dior-ic-6267fdef57">Patient Name</th>
                                <th class="dior-ic-6267fdef57">Test Name</th>
                                <th class="dior-ic-6267fdef57">Date</th>
                                <th class="dior-ic-6267fdef57">Status</th>
                                <th class="dior-ic-76ab799787">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="dior-ic-eeba22e539"><?php echo esc_html(($doc_ov_p(2)['patient_id'] ?? '')); ?></td>
                                <td class="dior-ic-1c29539dda"><?php echo esc_html(($doc_ov_p(2)['full_name'] ?? 'Patient')); ?></td>
                                <td class="dior-ic-1c29539dda">Blood Work</td>
                                <td class="dior-ic-1c29539dda"><?php echo esc_html(($doc_ov_a(0)['date'] ?? $doc_ov_a(0)['appt_date'] ?? "")); ?></td>
                                <td class="dior-ic-8ecb8ec0b8"><span class="dior-ic-ae0e475731">Ready</span></td>
                                <td class="dior-ic-4d9fcbd0d6">
                                    <button class="dior-ic-8d218c52f2"><i class="fa-solid fa-info-circle"></i></button>
                                    <button class="dior-ic-a17447911c"><i class="fa-solid fa-download"></i></button>
                                </td>
                            </tr>
                            <tr>
                                <td class="dior-ic-eeba22e539"><?php echo esc_html(($doc_ov_p(0)['patient_id'] ?? '')); ?></td>
                                <td class="dior-ic-1c29539dda"><?php echo esc_html(($doc_ov_p(0)['full_name'] ?? 'Patient')); ?></td>
                                <td class="dior-ic-1c29539dda">Renal Function Test</td>
                                <td class="dior-ic-1c29539dda"><?php echo esc_html(($doc_ov_a(0)['date'] ?? $doc_ov_a(0)['appt_date'] ?? "")); ?></td>
                                <td class="dior-ic-8ecb8ec0b8"><span class="dior-ic-4dfda8fd3e">Pending</span></td>
                                <td class="dior-ic-4d9fcbd0d6">
                                    <button class="dior-ic-8d218c52f2"><i class="fa-solid fa-info-circle"></i></button>
                                    <button class="dior-ic-a17447911c"><i class="fa-solid fa-download"></i></button>
                                </td>
                            </tr>
                            <tr>
                                <td class="dior-ic-eeba22e539"><?php echo esc_html(($doc_ov_p(1)['patient_id'] ?? '')); ?></td>
                                <td class="dior-ic-1c29539dda"><?php echo esc_html(($doc_ov_p(1)['full_name'] ?? 'Patient')); ?></td>
                                <td class="dior-ic-1c29539dda">Thyroid Function Test</td>
                                <td class="dior-ic-1c29539dda"><?php echo esc_html(($doc_ov_a(0)['date'] ?? $doc_ov_a(0)['appt_date'] ?? "")); ?></td>
                                <td class="dior-ic-8ecb8ec0b8"><span class="dior-ic-ae0e475731">In Progress</span></td>
                                <td class="dior-ic-4d9fcbd0d6">
                                    <button class="dior-ic-8d218c52f2"><i class="fa-solid fa-info-circle"></i></button>
                                    <button class="dior-ic-a17447911c"><i class="fa-solid fa-download"></i></button>
                                </td>
                            </tr>
                            <tr>
                                <td class="dior-ic-eeba22e539"><?php echo esc_html(($doc_ov_p(1)['patient_id'] ?? '')); ?></td>
                                <td class="dior-ic-1c29539dda"><?php echo esc_html(($doc_ov_p(1)['full_name'] ?? 'Patient')); ?></td>
                                <td class="dior-ic-1c29539dda">Vitamin D Test</td>
                                <td class="dior-ic-1c29539dda"><?php echo esc_html(($doc_ov_a(0)['date'] ?? $doc_ov_a(0)['appt_date'] ?? "")); ?></td>
                                <td class="dior-ic-8ecb8ec0b8"><span class="dior-ic-ae0e475731">Normal</span></td>
                                <td class="dior-ic-4d9fcbd0d6">
                                    <button class="dior-ic-8d218c52f2"><i class="fa-solid fa-info-circle"></i></button>
                                    <button class="dior-ic-a17447911c"><i class="fa-solid fa-download"></i></button>
                                </td>
                            </tr>
                            <tr>
                                <td class="dior-ic-eeba22e539"><?php echo esc_html(($doc_ov_p(1)['patient_id'] ?? '')); ?></td>
                                <td class="dior-ic-1c29539dda"><?php echo esc_html(($doc_ov_p(1)['full_name'] ?? 'Patient')); ?></td>
                                <td class="dior-ic-1c29539dda">Lisinopril</td>
                                <td class="dior-ic-1c29539dda"><?php echo esc_html(($doc_ov_a(0)['date'] ?? $doc_ov_a(0)['appt_date'] ?? "")); ?></td>
                                <td class="dior-ic-8ecb8ec0b8"><span class="dior-ic-ae0e475731">Normal</span></td>
                                <td class="dior-ic-4d9fcbd0d6">
                                    <button class="dior-ic-8d218c52f2"><i class="fa-solid fa-info-circle"></i></button>
                                    <button class="dior-ic-a17447911c"><i class="fa-solid fa-download"></i></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Bottom Row (Patient Review & Upcoming Sessions) -->
                <div class="dior-ic-6f4bdb8acc">
                    <!-- Patient Review -->
                    <div class="dior-ic-c15a34d2ab">
                        <h3 class="dior-ic-7501cf3804">Patient Review</h3>
                        
                        <div class="dior-ic-dc1691177f">
                            <div class="dior-ic-940a18d689">
                                <div class="dior-ic-5cb3678724">
                                    <div class="dior-ic-0587d65489">
                                        <img src="assets/images/users/user-1.png" onerror="this.src='https://ui-avatars.com/api/?name=Mia+Song&background=random';" class="dior-ic-dfaaa69152">
                                        <h4 class="dior-ic-b903d02ad7"><?php echo esc_html(($doc_ov_p(2)['full_name'] ?? 'Patient')); ?></h4>
                                    </div>
                                    <div class="dior-ic-7349337dd9">
                                        <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                                    </div>
                                </div>
                                <p class="dior-ic-a1afae8edb">Excellent doctor! Very professional and caring.</p>
                            </div>
                            
                            <div class="dior-ic-940a18d689">
                                <div class="dior-ic-5cb3678724">
                                    <div class="dior-ic-0587d65489">
                                        <img src="assets/images/users/user-2.png" onerror="this.src='https://ui-avatars.com/api/?name=John+Johnson&background=random';" class="dior-ic-dfaaa69152">
                                        <h4 class="dior-ic-b903d02ad7"><?php echo esc_html(($doc_ov_p(0)['full_name'] ?? 'Patient')); ?></h4>
                                    </div>
                                    <div class="dior-ic-7349337dd9">
                                        <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                                    </div>
                                </div>
                                <p class="dior-ic-a1afae8edb">Great experience. Highly recommended!</p>
                            </div>

                            <div class="dior-ic-940a18d689">
                                <div class="dior-ic-5cb3678724">
                                    <div class="dior-ic-0587d65489">
                                        <img src="assets/images/users/user-4.png" onerror="this.src='https://ui-avatars.com/api/?name=Elizabeth+Brown&background=random';" class="dior-ic-dfaaa69152">
                                        <h4 class="dior-ic-b903d02ad7"><?php echo esc_html(($doc_ov_p(1)['full_name'] ?? 'Patient')); ?></h4>
                                    </div>
                                    <div class="dior-ic-7349337dd9">
                                        <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                                    </div>
                                </div>
                                <p class="dior-ic-a1afae8edb">Very knowledgeable and patient.</p>
                            </div>

                            <div>
                                <div class="dior-ic-5cb3678724">
                                    <div class="dior-ic-0587d65489">
                                        <img src="assets/images/users/user-3.png" onerror="this.src='https://ui-avatars.com/api/?name=Richard+Davis&background=random';" class="dior-ic-dfaaa69152">
                                        <h4 class="dior-ic-b903d02ad7"><?php echo esc_html(($doc_ov_p(3)['full_name'] ?? 'Patient')); ?></h4>
                                    </div>
                                    <div class="dior-ic-7349337dd9">
                                        <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                                    </div>
                                </div>
                                <p class="dior-ic-a1afae8edb">Excellent doctor! Very professional and caring.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Upcoming Sessions -->
                    <div class="dior-ic-c15a34d2ab">
                        <h3 class="dior-ic-7501cf3804">Upcoming Sessions</h3>
                        
                        <div class="dior-ic-dc1691177f">
                            <div class="dior-ic-e9b09c21d6">
                                <div class="dior-ic-0587d65489">
                                    <img src="assets/images/users/user-4.png" onerror="this.src='https://ui-avatars.com/api/?name=Elizabeth+Brown&background=random';" class="dior-ic-898c7470cc">
                                    <div>
                                        <h4 class="dior-ic-c196f988d2">General Consultation</h4>
                                        <p class="dior-ic-7b6c3d446a"><?php echo esc_html(($doc_ov_p(1)['full_name'] ?? 'Patient')); ?> | <?php echo esc_html(($doc_ov_p(1)['patient_id'] ?? '')); ?></p>
                                        <p class="dior-ic-3be070defa"><i class="fa-regular fa-calendar dior-ic-6b696d6067"></i> <?php echo esc_html(($doc_ov_a(0)['date'] ?? $doc_ov_a(0)['appt_date'] ?? "")); ?> &nbsp;&nbsp; <i class="fa-regular fa-clock dior-ic-6b696d6067"></i> 05:30 PM</p>
                                    </div>
                                </div>
                                <div class="dior-ic-225f71451e">
                                    <button class="dior-ic-8c3cc0b132">Reschedule</button>
                                    <button class="dior-ic-ee96c3bad6">Join Now</button>
                                </div>
                            </div>
                            
                            <div class="dior-ic-e9b09c21d6">
                                <div class="dior-ic-0587d65489">
                                    <img src="assets/images/users/user-1.png" onerror="this.src='https://ui-avatars.com/api/?name=Mia+Song&background=random';" class="dior-ic-898c7470cc">
                                    <div>
                                        <h4 class="dior-ic-c196f988d2">Follow-up Appointment</h4>
                                        <p class="dior-ic-7b6c3d446a"><?php echo esc_html(($doc_ov_p(2)['full_name'] ?? 'Patient')); ?> | <?php echo esc_html(($doc_ov_p(2)['patient_id'] ?? '')); ?></p>
                                        <p class="dior-ic-3be070defa"><i class="fa-regular fa-calendar dior-ic-6b696d6067"></i> <?php echo esc_html(($doc_ov_a(2)['date'] ?? $doc_ov_a(2)['appt_date'] ?? "")); ?> &nbsp;&nbsp; <i class="fa-regular fa-clock dior-ic-6b696d6067"></i> 11:00 AM</p>
                                    </div>
                                </div>
                                <div class="dior-ic-225f71451e">
                                    <button class="dior-ic-8c3cc0b132">Reschedule</button>
                                    <button class="dior-ic-ee96c3bad6">Join Now</button>
                                </div>
                            </div>

                            <div class="dior-ic-4b7d98fa59">
                                <div class="dior-ic-0587d65489">
                                    <img src="assets/images/users/user-3.png" onerror="this.src='https://ui-avatars.com/api/?name=Richard+Davis&background=random';" class="dior-ic-898c7470cc">
                                    <div>
                                        <h4 class="dior-ic-c196f988d2">General Consultation</h4>
                                        <p class="dior-ic-7b6c3d446a"><?php echo esc_html(($doc_ov_p(3)['full_name'] ?? 'Patient')); ?> | <?php echo esc_html(($doc_ov_p(3)['patient_id'] ?? '')); ?></p>
                                        <p class="dior-ic-3be070defa"><i class="fa-regular fa-calendar dior-ic-6b696d6067"></i> <?php echo esc_html(($doc_ov_a(2)['date'] ?? $doc_ov_a(2)['appt_date'] ?? "")); ?> &nbsp;&nbsp; <i class="fa-regular fa-clock dior-ic-6b696d6067"></i> 11:00 AM</p>
                                    </div>
                                </div>
                                <div class="dior-ic-225f71451e">
                                    <button class="dior-ic-8c3cc0b132">Reschedule</button>
                                    <button class="dior-ic-ee96c3bad6">Join Now</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            
            <!-- RIGHT COLUMN -->
            <div class="dior-ic-718b77eef9">
                
                <!-- Profile Card -->
                <div class="dior-ic-8abbfc9d3a">
                    <div class="dior-ic-7a57080d91">
                        <span class="dior-ic-ec3e0d32b7">DC</span>
                    </div>
                    <h3 class="dior-ic-9f352fb66f">Dr. Diorca Aquino De La Cruz</h3>
                    <p class="dior-ic-a1995a9bd0">Cardiologist</p>
                    
                    <div class="dior-ic-eda19daac0">
                        <div><i class="fa-solid fa-envelope dior-ic-595ebcc376"></i> abc@gmail.com</div>
                        <div class="dior-ic-b90ff44e91">
                            <div><i class="fa-solid fa-phone dior-ic-595ebcc376"></i> +1 234 567 8900</div>
                            <div><i class="fa-solid fa-briefcase dior-ic-595ebcc376"></i> 12 years experience</div>
                        </div>
                    </div>
                    
                    <button class="dior-ic-8d382b8e14">Edit Profile</button>
                </div>

                <!-- Pending Tasks -->
                <div class="dior-ic-c15a34d2ab">
                    <h3 class="dior-ic-7501cf3804">Pending Tasks</h3>
                    
                    <div class="dior-ic-33a271e04b">
                        <div class="dior-ic-ac6f1a7410">
                            <input type="checkbox" class="dior-ic-745334ef00">
                            <div>
                                <h4 class="dior-ic-0a61be4834">Review lab reports</h4>
                                <p class="dior-ic-f3ad71cd22">Check blood test results for 3 patients</p>
                                <span class="dior-ic-d7e8195c45"><i class="fa-regular fa-calendar"></i> <?php echo esc_html(($doc_ov_a(2)['date'] ?? $doc_ov_a(2)['appt_date'] ?? "")); ?></span>
                            </div>
                        </div>
                        
                        <div class="dior-ic-ac6f1a7410">
                            <input type="checkbox" class="dior-ic-745334ef00">
                            <div>
                                <h4 class="dior-ic-0a61be4834">Sign prescriptions</h4>
                                <p class="dior-ic-f3ad71cd22">5 prescriptions pending signature</p>
                                <span class="dior-ic-d7e8195c45"><i class="fa-regular fa-calendar"></i> <?php echo esc_html(($doc_ov_a(2)['date'] ?? $doc_ov_a(2)['appt_date'] ?? "")); ?></span>
                            </div>
                        </div>

                        <div class="dior-ic-ac6f1a7410">
                            <input type="checkbox" class="dior-ic-745334ef00">
                            <div>
                                <h4 class="dior-ic-0a61be4834">Approve medical notes</h4>
                                <p class="dior-ic-f3ad71cd22">Review and approve consultation notes</p>
                                <span class="dior-ic-d7e8195c45"><i class="fa-regular fa-calendar"></i> <?php echo esc_html(($doc_ov_a(2)['date'] ?? $doc_ov_a(2)['appt_date'] ?? "")); ?></span>
                            </div>
                        </div>

                        <div class="dior-ic-af763f6223">
                            <input type="checkbox" class="dior-ic-745334ef00">
                            <div>
                                <h4 class="dior-ic-0a61be4834">Update patient records</h4>
                                <p class="dior-ic-f3ad71cd22">Complete EMR updates for recent visits</p>
                                <span class="dior-ic-d7e8195c45"><i class="fa-regular fa-calendar"></i> <?php echo esc_html(($doc_ov_a(2)['date'] ?? $doc_ov_a(2)['appt_date'] ?? "")); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Next Appointment -->
                <div class="dior-next-appointment-card dior-ic-6cf94f634b">
                    <h3 class="dior-next-appointment-title dior-ic-3d6cad599a">Next Appointment</h3>
                    
                    <div class="dior-ic-dc1691177f">
                        <div class="dior-ic-e9b09c21d6">
                            <div class="dior-ic-0587d65489">
                                <img src="assets/images/users/user-1.png" onerror="this.src='https://ui-avatars.com/api/?name=Mia+Song&background=random';" class="dior-ic-6705b98539">
                                <div>
                                    <h4 class="dior-ic-3a5c484249"><?php echo esc_html(($doc_ov_p(2)['full_name'] ?? 'Patient')); ?> | <?php echo esc_html(($doc_ov_p(2)['patient_id'] ?? '')); ?></h4>
                                    <p class="dior-ic-24f11d5255">Routine Checkup</p>
                                    <span class="dior-ic-d78f9ac356"><i class="fa-regular fa-calendar"></i> <?php echo esc_html(($doc_ov_a(2)['date'] ?? $doc_ov_a(2)['appt_date'] ?? "")); ?></span>
                                </div>
                            </div>
                            <div class="dior-ic-f8d5cd8301">
                                <button class="dior-ic-c734c3607d"><i class="fa-solid fa-envelope"></i></button>
                                <button class="dior-ic-c734c3607d"><i class="fa-solid fa-phone"></i></button>
                            </div>
                        </div>

                        <div class="dior-ic-e9b09c21d6">
                            <div class="dior-ic-0587d65489">
                                <img src="assets/images/users/user-2.png" onerror="this.src='https://ui-avatars.com/api/?name=John+Johnson&background=random';" class="dior-ic-6705b98539">
                                <div>
                                    <h4 class="dior-ic-3a5c484249"><?php echo esc_html(($doc_ov_p(0)['full_name'] ?? 'Patient')); ?> | <?php echo esc_html(($doc_ov_p(0)['patient_id'] ?? '')); ?></h4>
                                    <p class="dior-ic-24f11d5255">Routine Checkup</p>
                                    <span class="dior-ic-d78f9ac356"><i class="fa-regular fa-calendar"></i> <?php echo esc_html(($doc_ov_a(2)['date'] ?? $doc_ov_a(2)['appt_date'] ?? "")); ?></span>
                                </div>
                            </div>
                            <div class="dior-ic-f8d5cd8301">
                                <button class="dior-ic-c734c3607d"><i class="fa-solid fa-envelope"></i></button>
                                <button class="dior-ic-c734c3607d"><i class="fa-solid fa-phone"></i></button>
                            </div>
                        </div>

                        <div class="dior-ic-e9b09c21d6">
                            <div class="dior-ic-0587d65489">
                                <img src="assets/images/users/user-3.png" onerror="this.src='https://ui-avatars.com/api/?name=Richard+Davis&background=random';" class="dior-ic-6705b98539">
                                <div>
                                    <h4 class="dior-ic-3a5c484249"><?php echo esc_html(($doc_ov_p(3)['full_name'] ?? 'Patient')); ?> | <?php echo esc_html(($doc_ov_p(3)['patient_id'] ?? '')); ?></h4>
                                    <p class="dior-ic-24f11d5255">Routine Checkup</p>
                                    <span class="dior-ic-d78f9ac356"><i class="fa-regular fa-calendar"></i> <?php echo esc_html(($doc_ov_a(2)['date'] ?? $doc_ov_a(2)['appt_date'] ?? "")); ?></span>
                                </div>
                            </div>
                            <div class="dior-ic-f8d5cd8301">
                                <button class="dior-ic-c734c3607d"><i class="fa-solid fa-envelope"></i></button>
                                <button class="dior-ic-c734c3607d"><i class="fa-solid fa-phone"></i></button>
                            </div>
                        </div>

                        <div class="dior-ic-4b7d98fa59">
                            <div class="dior-ic-0587d65489">
                                <img src="assets/images/users/user-4.png" onerror="this.src='https://ui-avatars.com/api/?name=Elizabeth+Brown&background=random';" class="dior-ic-6705b98539">
                                <div>
                                    <h4 class="dior-ic-3a5c484249"><?php echo esc_html(($doc_ov_p(1)['full_name'] ?? 'Patient')); ?> | <?php echo esc_html(($doc_ov_p(1)['patient_id'] ?? '')); ?></h4>
                                    <p class="dior-ic-24f11d5255">Routine Checkup</p>
                                    <span class="dior-ic-d78f9ac356"><i class="fa-regular fa-calendar"></i> <?php echo esc_html(($doc_ov_a(2)['date'] ?? $doc_ov_a(2)['appt_date'] ?? "")); ?></span>
                                </div>
                            </div>
                            <div class="dior-ic-f8d5cd8301">
                                <button class="dior-ic-c734c3607d"><i class="fa-solid fa-envelope"></i></button>
                                <button class="dior-ic-c734c3607d"><i class="fa-solid fa-phone"></i></button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>


                <!-- -- ALL PATIENTS -- -->
