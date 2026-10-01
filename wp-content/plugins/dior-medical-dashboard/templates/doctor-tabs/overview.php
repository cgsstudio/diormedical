<?php
// PHP logic here
// Using static images and exact data as provided in the reference HTML, but replaced unicode with FontAwesome icons
?>

<section class="dior-tab-panel active" id="tab-doc-overview">
    <section class="stats">
        <div class="card stat">
            <div class="num">20</div>
            <div class="stat-icon"><i class="fa-regular fa-calendar-days"></i></div>
            <div class="label">Appointments</div>
        </div>
        <div class="card stat">
            <div class="num">90</div>
            <div class="stat-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
            <div class="label">Upcoming Appointments</div>
        </div>
        <div class="card stat">
            <div class="num">23</div>
            <div class="stat-icon"><i class="fa-solid fa-user-doctor"></i></div>
            <div class="label">New Patients</div>
        </div>
        <div class="card stat">
            <div class="num">$500.00</div>
            <div class="stat-icon"><i class="fa-solid fa-dollar-sign"></i></div>
            <div class="label">Total Earning</div>
        </div>
    </section>

    <div class="columns">
        <div class="left">
            <div class="schedule-card">
                <div class="schedule-header">
                    <h2 class="schedule-title">Today's Schedule</h2>
                    <div class="schedule-badges">
                        <span class="s-badge b-completed">2 Completed</span>
                        <span class="s-badge b-upcoming">3 Upcoming</span>
                        <span class="s-badge b-cancel">1 Cancel</span>
                    </div>
                </div>
                <div class="schedule-body">
                    <div class="schedule-slot">
                        <div class="schedule-time">2:00 PM</div>
                        <div class="schedule-timeline">
                            <div class="schedule-dot"></div>
                        </div>
                        <div class="schedule-appt">
                            <div class="s-appt-left">
                                <img class="s-patient-img"
                                    src="https://ui-avatars.com/api/?name=Mia+Song&background=eef2ff&color=4f46e5"
                                    alt="Mia Song">
                                <div>
                                    <h3 class="s-patient-name">Mia Song</h3>
                                    <p class="s-patient-id">PAT00123</p>
                                </div>
                            </div>
                            <div class="s-appt-right">
                                <span class="s-appt-status s-progress">In Progress</span>
                            </div>
                        </div>
                    </div>

                    <div class="schedule-slot">
                        <div class="schedule-time">1:00 PM</div>
                        <div class="schedule-timeline">
                            <div class="schedule-dot"></div>
                        </div>
                        <div class="schedule-appt">
                            <div class="s-appt-left">
                                <img class="s-patient-img"
                                    src="https://ui-avatars.com/api/?name=John+Johnson&background=f0fdf4&color=166534"
                                    alt="John Johnson">
                                <div>
                                    <h3 class="s-patient-name">John Johnson</h3>
                                    <p class="s-patient-id">PAT00111</p>
                                </div>
                            </div>
                            <div class="s-appt-right">
                                <span class="s-appt-status s-completed">Completed</span>
                            </div>
                        </div>
                    </div>

                    <div class="schedule-slot">
                        <div class="schedule-time">12:00 PM</div>
                        <div class="schedule-timeline">
                            <div class="schedule-dot"></div>
                        </div>
                        <div class="schedule-appt">
                            <div class="s-appt-left">
                                <img class="s-patient-img"
                                    src="https://ui-avatars.com/api/?name=Richard+Davis&background=fff1f2&color=be123c"
                                    alt="Richard Davis">
                                <div>
                                    <h3 class="s-patient-name">Richard Davis</h3>
                                    <p class="s-patient-id">PAT00238</p>
                                </div>
                            </div>
                            <div class="s-appt-right">
                                <span class="s-appt-status s-cancelled">Cancelled</span>
                            </div>
                        </div>
                    </div>

                    <div class="schedule-slot">
                        <div class="schedule-time">3:00 PM</div>
                        <div class="schedule-timeline">
                            <div class="schedule-dot"></div>
                        </div>
                        <div class="schedule-appt">
                            <div class="s-appt-left">
                                <img class="s-patient-img"
                                    src="https://ui-avatars.com/api/?name=Elizabeth+Brown&background=fef3c7&color=b45309"
                                    alt="Elizabeth Brown">
                                <div>
                                    <h3 class="s-patient-name">Elizabeth Brown</h3>
                                    <p class="s-patient-id">PAT00112</p>
                                </div>
                            </div>
                            <div class="s-appt-right">
                                <div class="s-appt-actions">
                                    <button class="s-btn-icon"><i class="fa-regular fa-calendar-days"></i></button>
                                    <button class="s-btn-icon mail-icon"><i class="fa-regular fa-envelope"></i></button>
                                    <button class="s-btn-join"><i class="fa-solid fa-video"></i> Join Now</button>
                                </div>
                                <span class="s-appt-status s-upcoming">Upcoming</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

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
                            <tr>
                                <td>APP00123</td>
                                <td>Mia Song</td>
                                <td>Sep 10, 2026</td>
                                <td>Fever</td>
                                <td><button class="action-btn">Prescription</button><button
                                        class="action-btn outline">Details</button></td>
                            </tr>
                            <tr>
                                <td>APP00111</td>
                                <td>John Johnson</td>
                                <td>Sep 05, 2026</td>
                                <td>Cholera</td>
                                <td><button class="action-btn">Prescription</button><button
                                        class="action-btn outline">Details</button></td>
                            </tr>
                            <tr>
                                <td>APP00112</td>
                                <td>Elizabeth Brown</td>
                                <td>Aug 26, 2026</td>
                                <td>Jaundice</td>
                                <td><button class="action-btn">Prescription</button><button
                                        class="action-btn outline">Details</button></td>
                            </tr>
                            <tr>
                                <td>APP00112</td>
                                <td>Elizabeth Brown</td>
                                <td>Aug 26, 2026</td>
                                <td>Typhoid</td>
                                <td><button class="action-btn">Prescription</button><button
                                        class="action-btn outline">Details</button></td>
                            </tr>
                            <tr>
                                <td>APP00112</td>
                                <td>Elizabeth Brown</td>
                                <td>Aug 26, 2026</td>
                                <td>Malaria</td>
                                <td><button class="action-btn">Prescription</button><button
                                        class="action-btn outline">Details</button></td>
                            </tr>
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
                            <i class="fa-solid fa-envelope"></i> Abc@gmail.com<br>
                            <i class="fa-solid fa-phone"></i> +1 234 567 8900 &nbsp;&nbsp; <i
                                class="fa-solid fa-briefcase"></i> 12 years experience
                        </div>
                    </div>
                </div>
                <button class="edit">Edit Profile</button>
            </section>
            <section class="card tasks">
                <div class="panel-title">Pending Tasks</div>
                <div class="task"><span class="check"></span>
                    <div><b>Review lab reports</b>
                        <p>Check blood test results for 3 patients</p><small><i class="fa-regular fa-calendar"></i> Oct
                            2, 2026</small>
                    </div>
                </div>
                <div class="task"><span class="check"></span>
                    <div><b>Sign prescriptions</b>
                        <p>5 prescriptions pending signature</p><small><i class="fa-regular fa-calendar"></i> Oct 2,
                            2026</small>
                    </div>
                </div>
                <div class="task"><span class="check"></span>
                    <div><b>Approve medical notes</b>
                        <p>Review and approve consultation notes</p><small><i class="fa-regular fa-calendar"></i> Oct 2,
                            2026</small>
                    </div>
                </div>
                <div class="task"><span class="check"></span>
                    <div><b>Update patient records</b>
                        <p>Complete EMR updates for recent visits</p><small><i class="fa-regular fa-calendar"></i> Oct
                            2, 2026</small>
                    </div>
                </div>
            </section>
            <section class="card follow">
                <div class="panel-title">Follow-up Reminders</div>
                <div class="follow-item"><img class="avatar"
                        src="https://ui-avatars.com/api/?name=Mia+Song&background=eef2ff&color=4f46e5" alt="Avatar">
                    <div class="follow-info"><b>Mia Song | PAT00123</b><small>Routine Checkup</small><small><i
                                class="fa-regular fa-calendar"></i> Oct 2, 2026</small></div>
                    <div class="follow-buttons"><button class="square"><i
                                class="fa-regular fa-envelope"></i></button><button class="square"><i
                                class="fa-solid fa-phone"></i></button></div>
                </div>
                <div class="follow-item"><img class="avatar"
                        src="https://ui-avatars.com/api/?name=John+Johnson&background=f0fdf4&color=166534" alt="Avatar">
                    <div class="follow-info"><b>John Johnson | PAT00111</b><small>Routine Checkup</small><small><i
                                class="fa-regular fa-calendar"></i> Oct 2, 2026</small></div>
                    <div class="follow-buttons"><button class="square"><i
                                class="fa-regular fa-envelope"></i></button><button class="square"><i
                                class="fa-solid fa-phone"></i></button></div>
                </div>
                <div class="follow-item"><img class="avatar"
                        src="https://ui-avatars.com/api/?name=Richard+Davis&background=fff1f2&color=be123c"
                        alt="Avatar">
                    <div class="follow-info"><b>Richard Davis | PAT00238</b><small>Routine Checkup</small><small><i
                                class="fa-regular fa-calendar"></i> Oct 2, 2026</small></div>
                    <div class="follow-buttons"><button class="square"><i
                                class="fa-regular fa-envelope"></i></button><button class="square"><i
                                class="fa-solid fa-phone"></i></button></div>
                </div>
                <div class="follow-item"><img class="avatar"
                        src="https://ui-avatars.com/api/?name=Elizabeth+Brown&background=fef3c7&color=b45309"
                        alt="Avatar">
                    <div class="follow-info"><b>Elizabeth Brown | PAT00112</b><small>Routine Checkup</small><small><i
                                class="fa-regular fa-calendar"></i> Oct 2, 2026</small></div>
                    <div class="follow-buttons"><button class="square"><i
                                class="fa-regular fa-envelope"></i></button><button class="square"><i
                                class="fa-solid fa-phone"></i></button></div>
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
                            <div class="lab-actions"><button class="round-action"><i
                                        class="fa-solid fa-info"></i></button><button class="round-action"><i
                                        class="fa-solid fa-download"></i></button></div>
                        </td>
                    </tr>
                    <tr>
                        <td>PAT00111</td>
                        <td>John Johnson</td>
                        <td>Renal Function Test</td>
                        <td>Sep 10, 2026</td>
                        <td><span class="pill inactive">Pending</span></td>
                        <td>
                            <div class="lab-actions"><button class="round-action"><i
                                        class="fa-solid fa-info"></i></button><button class="round-action"><i
                                        class="fa-solid fa-download"></i></button></div>
                        </td>
                    </tr>
                    <tr>
                        <td>PAT00112</td>
                        <td>Elizabeth Brown</td>
                        <td>Thyroid Function Test</td>
                        <td>Sep 10, 2026</td>
                        <td><span class="pill active">In Progress</span></td>
                        <td>
                            <div class="lab-actions"><button class="round-action"><i
                                        class="fa-solid fa-info"></i></button><button class="round-action"><i
                                        class="fa-solid fa-download"></i></button></div>
                        </td>
                    </tr>
                    <tr>
                        <td>PAT00112</td>
                        <td>Elizabeth Brown</td>
                        <td>Vitamin D Test</td>
                        <td>Sep 10, 2026</td>
                        <td><span class="pill active">Normal</span></td>
                        <td>
                            <div class="lab-actions"><button class="round-action"><i
                                        class="fa-solid fa-info"></i></button><button class="round-action"><i
                                        class="fa-solid fa-download"></i></button></div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <div class="bottom">
        <section class="card reviews">
            <div class="panel-title">Patient Review</div>
            <div class="reviews-scroll">
                <div class="review">
                    <img class="avatar" src="https://ui-avatars.com/api/?name=Mia+Song&background=eef2ff&color=4f46e5"
                        alt="Mia Song">
                    <div class="review-body">
                        <div class="review-row1">
                            <span class="review-name">Mia Song</span>
                            <span class="stars"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i
                                    class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i
                                    class="fa-solid fa-star"></i></span>
                        </div>
                        <div class="review-text">Excellent doctor! Very professional and caring.</div>
                    </div>
                </div>
                <div class="review">
                    <img class="avatar"
                        src="https://ui-avatars.com/api/?name=John+Johnson&background=f0fdf4&color=166534"
                        alt="John Johnson">
                    <div class="review-body">
                        <div class="review-row1">
                            <span class="review-name">John Johnson</span>
                            <span class="stars"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i
                                    class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i
                                    class="fa-solid fa-star"></i></span>
                        </div>
                        <div class="review-text">Great experience. Highly recommended!</div>
                    </div>
                </div>
                <div class="review">
                    <img class="avatar"
                        src="https://ui-avatars.com/api/?name=Elizabeth+Brown&background=fef3c7&color=b45309"
                        alt="Elizabeth Brown">
                    <div class="review-body">
                        <div class="review-row1">
                            <span class="review-name">Elizabeth Brown</span>
                            <span class="stars"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i
                                    class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i
                                    class="fa-solid fa-star"></i></span>
                        </div>
                        <div class="review-text">Very knowledgeable and patient.</div>
                    </div>
                </div>
                <div class="review">
                    <img class="avatar"
                        src="https://ui-avatars.com/api/?name=Richard+Davis&background=fff1f2&color=be123c"
                        alt="Richard Davis">
                    <div class="review-body">
                        <div class="review-row1">
                            <span class="review-name">Richard Davis</span>
                            <span class="stars"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i
                                    class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i
                                    class="fa-solid fa-star"></i></span>
                        </div>
                        <div class="review-text">Excellent doctor! Very professional and caring.</div>
                    </div>
                </div>
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
                        <span class="session-time"><i class="fa-regular fa-calendar"></i> Sep 29, 2026 &nbsp;<i
                                class="fa-regular fa-clock"></i> 05:30 PM</span>
                    </div>
                    <div class="session-actions">
                        <button class="reschedule">Reschedule</button>
                        <button class="join">Join Now</button>
                    </div>
                </div>
                <div class="session">
                    <img class="avatar" src="https://ui-avatars.com/api/?name=Mia+Song&background=eef2ff&color=4f46e5"
                        alt="Mia Song">
                    <div class="session-info">
                        <b>Follow-up Appointment</b>
                        <span class="session-patient">Mia Song | PAT00123</span>
                        <span class="session-time"><i class="fa-regular fa-calendar"></i> Oct 2, 2026 &nbsp;<i
                                class="fa-regular fa-clock"></i> 11:00 AM</span>
                    </div>
                    <div class="session-actions">
                        <button class="reschedule">Reschedule</button>
                        <button class="join">Join Now</button>
                    </div>
                </div>
                <div class="session">
                    <img class="avatar"
                        src="https://ui-avatars.com/api/?name=Richard+Davis&background=fff1f2&color=be123c"
                        alt="Richard Davis">
                    <div class="session-info">
                        <b>General Consultation</b>
                        <span class="session-patient">Richard Davis | PAT00238</span>
                        <span class="session-time"><i class="fa-regular fa-calendar"></i> Oct 2, 2026 &nbsp;<i
                                class="fa-regular fa-clock"></i> 11:00 AM</span>
                    </div>
                    <div class="session-actions">
                        <button class="reschedule">Reschedule</button>
                        <button class="join">Join Now</button>
                    </div>
                </div>
            </div>
        </section>
    </div>
    <button class="float"><i class="fa-solid fa-plus"></i></button>
</section>