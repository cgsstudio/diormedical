<link rel="stylesheet" href="<?php echo esc_url(DIOR_PORTAL_URL . 'assets/css/book-appointment.css?v=' . time()); ?>">

<section class="dior-tab-panel appointment-form-wrapper" id="tab-doc-appointments-edit" style="display: none;">
    <!-- Breadcrumb Header -->
    <div class="mb-4" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size: 20px; font-weight: 700;">Edit Appointment</h4>
        </div>
        <div>
            <ul class="breadcrumb-list">
                <li><a href="#"><i class="fas fa-house" style="font-size: 14px; color: #4F46E5;"></i></a></li>
                <li><span style="color: #94A3B8;">/</span></li>
                <li><a href="javascript:void(0)">Appointments</a></li>
                <li><span style="color: #94A3B8;">/</span></li>
                <li class="active">Edit Appointment</li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="section-body">
        <div class="row">
            <div class="col-12 col-md-12 col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h4>Edit Appointment</h4>
                    </div>
                    <div class="card-body">
                        <form novalidate="">
                            <h5 class="card-inside-title">Patient Information</h5>
                            <div class="row">
                                <div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 mb-3">
                                    <label>First name <span class="text-danger">*</span></label>
                                    <input type="text" placeholder="First name" class="form-control" value="Pooja">
                                </div>
                                <div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 mb-3">
                                    <label>Middle name</label>
                                    <input type="text" placeholder="Middle name" class="form-control">
                                </div>
                                <div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 mb-3">
                                    <label>Last name</label>
                                    <input type="text" placeholder="Last name" class="form-control" value="Sarma">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 mb-3">
                                    <label>Gender <span class="text-danger">*</span></label>
                                    <select class="form-select">
                                        <option value="" disabled="">Select Gender</option>
                                        <option value="male">Male</option>
                                        <option value="female" selected>Female</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 mb-3">
                                    <label>Date Of Birth <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" value="1987-02-17">
                                </div>
                                <div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 mb-3">
                                    <label>Blood Group</label>
                                    <select class="form-select">
                                        <option value="" disabled="">Select Blood Group</option>
                                        <option value="A+">A+</option>
                                        <option value="A-">A-</option>
                                        <option value="B+" selected>B+</option>
                                        <option value="B-">B-</option>
                                        <option value="AB+">AB+</option>
                                        <option value="AB-">AB-</option>
                                        <option value="O+">O+</option>
                                        <option value="O-">O-</option>
                                        <option value="unknown">Unknown</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 mb-3">
                                    <label>Mobile <span class="text-danger">*</span></label>
                                    <input type="tel" placeholder="Mobile" class="form-control" value="9876543210">
                                </div>
                                <div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 mb-3">
                                    <label>Email <span class="text-danger">*</span></label>
                                    <input type="email" placeholder="Email" class="form-control" value="test@example.com">
                                </div>
                                <div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 mb-3">
                                    <label>Patient ID (if existing)</label>
                                    <input type="text" placeholder="Patient ID" class="form-control" value="PAT-001">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 mb-3">
                                    <label>Address</label>
                                    <textarea rows="2" placeholder="Address" class="form-control">101, Elerxa, New York</textarea>
                                </div>
                            </div>

                            <h5 class="card-inside-title mt-4">Insurance Information</h5>
                            <div class="row">
                                <div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 mb-3">
                                    <label>Insurance Provider</label>
                                    <input type="text" placeholder="Provider" class="form-control" value="Blue Cross">
                                </div>
                                <div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 mb-3">
                                    <label>Policy Number</label>
                                    <input type="text" placeholder="Policy Number" class="form-control" value="POL12345">
                                </div>
                                <div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 mb-3">
                                    <label>Group Number</label>
                                    <input type="text" placeholder="Group Number" class="form-control" value="GRP56789">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 mb-3">
                                    <label>Insurance Holder Name (if not patient)</label>
                                    <input type="text" placeholder="Holder Name" class="form-control" value="Rajesh Sharma">
                                </div>
                                <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 mb-3">
                                    <label>Relationship to Patient</label>
                                    <select class="form-select">
                                        <option value="self"> Self</option>
                                        <option value="spouse" selected> Spouse</option>
                                        <option value="child"> Child</option>
                                        <option value="parent"> Parent</option>
                                        <option value="other"> Other</option>
                                    </select>
                                </div>
                            </div>

                            <h5 class="card-inside-title mt-4">Medical History</h5>
                            <div class="row">
                                <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 mb-3">
                                    <label>Existing Medical Conditions</label>
                                    <textarea rows="2" placeholder="Diabetes, Hypertension, etc." class="form-control">Diabetes</textarea>
                                </div>
                                <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 mb-3">
                                    <label>Current Medications</label>
                                    <textarea rows="2" placeholder="List all current medications" class="form-control">Metformin</textarea>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 mb-3">
                                    <label>Allergies</label>
                                    <textarea rows="2" placeholder="Medications, food, etc." class="form-control">Peanuts</textarea>
                                </div>
                                <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 mb-3">
                                    <label>Previous Surgeries</label>
                                    <textarea rows="2" placeholder="Type and date of surgery" class="form-control">Appendectomy - 2010</textarea>
                                </div>
                            </div>

                            <h5 class="card-inside-title mt-4">Emergency Contact</h5>
                            <div class="row">
                                <div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 mb-3">
                                    <label>Contact Name</label>
                                    <input type="text" placeholder="Contact Name" class="form-control" value="Ramesh Sharma">
                                </div>
                                <div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 mb-3">
                                    <label>Relationship</label>
                                    <select class="form-select">
                                        <option value="self"> Self</option>
                                        <option value="spouse"> Spouse</option>
                                        <option value="child"> Child</option>
                                        <option value="parent" selected> Parent</option>
                                        <option value="other"> Other</option>
                                    </select>
                                </div>
                                <div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 mb-3">
                                    <label>Contact Phone</label>
                                    <input type="tel" placeholder="Contact Phone" class="form-control" value="9876543211">
                                </div>
                            </div>

                            <h5 class="card-inside-title mt-4">Appointment Details</h5>
                            <div class="row">
                                <div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 mb-3">
                                    <label>Department <span class="text-danger">*</span></label>
                                    <select class="form-select">
                                        <option value="" disabled="">Select Department</option>
                                        <option value="cardiology" selected>Cardiology </option>
                                        <option value="orthopedics">Orthopedics </option>
                                        <option value="gynecology">Gynecology </option>
                                        <option value="neurology">Neurology </option>
                                        <option value="pediatrics">Pediatrics </option>
                                        <option value="psychiatry">Psychiatry </option>
                                        <option value="oncology">Oncology </option>
                                        <option value="dermatology">Dermatology </option>
                                        <option value="ophthalmology">Ophthalmology </option>
                                        <option value="general surgery">General Surgery </option>
                                    </select>
                                </div>
                                <div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 mb-3">
                                    <label>Consulting Doctor <span class="text-danger">*</span></label>
                                    <select class="form-select">
                                        <option value="" disabled="">Select Doctor</option>
                                        <option value="Dr.Rajesh" selected>Dr.Rajesh</option>
                                        <option value="Dr.Sarah Smith">Dr.Sarah Smith</option>
                                        <option value="Dr.Jay Soni">Dr.Jay Soni</option>
                                        <option value="Dr.Pooja Patel">Dr.Pooja Patel</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 mb-3">
                                    <label>Appointment Type <span class="text-danger">*</span></label>
                                    <select class="form-select">
                                        <option value="new"> New Patient</option>
                                        <option value="followup" selected> Follow-up</option>
                                        <option value="emergency"> Emergency</option>
                                        <option value="consultation"> Consultation</option>
                                        <option value="procedure"> Procedure/Surgery</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 mb-3">
                                    <label>Date Of Appointment <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" value="2025-08-05">
                                </div>
                                <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 mb-3">
                                    <label>Reason for Visit <span class="text-danger">*</span></label>
                                    <textarea rows="1" placeholder="Reason" class="form-control">Chest Pain</textarea>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 mb-3">
                                    <label class="mb-2">Time Of Appointment <span class="text-danger">*</span></label>
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <h6>Morning</h6>
                                            <div role="group" class="btn-group">
                                                <input type="radio" name="timeSlot" class="btn-check" id="morning_edit_0">
                                                <label class="btn btn-outline-primary" for="morning_edit_0">09:00 AM</label>
                                                <input type="radio" name="timeSlot" class="btn-check" id="morning_edit_1" checked>
                                                <label class="btn btn-outline-primary" for="morning_edit_1">10:00 AM</label>
                                                <input type="radio" name="timeSlot" class="btn-check" id="morning_edit_2">
                                                <label class="btn btn-outline-primary" for="morning_edit_2">11:00 AM</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <h6>Afternoon</h6>
                                            <div role="group" class="btn-group">
                                                <input type="radio" name="timeSlot" class="btn-check" id="afternoon_edit_0">
                                                <label class="btn btn-outline-primary" for="afternoon_edit_0">01:00 PM</label>
                                                <input type="radio" name="timeSlot" class="btn-check" id="afternoon_edit_1">
                                                <label class="btn btn-outline-primary" for="afternoon_edit_1">02:00 PM</label>
                                                <input type="radio" name="timeSlot" class="btn-check" id="afternoon_edit_2">
                                                <label class="btn btn-outline-primary" for="afternoon_edit_2">03:00 PM</label>
                                                <input type="radio" name="timeSlot" class="btn-check" id="afternoon_edit_3">
                                                <label class="btn btn-outline-primary" for="afternoon_edit_3">04:00 PM</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <h6>Evening</h6>
                                            <div role="group" class="btn-group">
                                                <input type="radio" name="timeSlot" class="btn-check" id="evening_edit_0">
                                                <label class="btn btn-outline-primary" for="evening_edit_0">05:00 PM</label>
                                                <input type="radio" name="timeSlot" class="btn-check" id="evening_edit_1">
                                                <label class="btn btn-outline-primary" for="evening_edit_1">06:00 PM</label>
                                                <input type="radio" name="timeSlot" class="btn-check" id="evening_edit_2">
                                                <label class="btn btn-outline-primary" for="evening_edit_2">07:00 PM</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 mb-3">
                                    <label>Symptoms/Condition</label>
                                    <textarea rows="2" placeholder="Symptoms" class="form-control">Mild pain in chest</textarea>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 mb-3">
                                    <label>Additional Notes</label>
                                    <textarea rows="2" placeholder="Notes" class="form-control">Review last ECG</textarea>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 mb-5">
                                    <label class="mb-2">Upload Previous Medical Reports</label>
                                    <div class="file-upload-wrapper">
                                        <input type="file" class="file-input" accept="*">
                                        <div class="file-upload-area">
                                            <div class="upload-icon"><i class="fas fa-cloud-arrow-up"></i></div>
                                            <div class="upload-text">
                                                <p class="mb-1">Drag &amp; drop files here or <span class="browse-link">browse</span></p>
                                                <small class="text-muted">Choose file</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 mb-3">
                                    <button type="submit" class="btn btn-primary me-2">Update Appointment</button>
                                    <button type="button" class="btn btn-light">Cancel</button>
                                </div>
                            </div>

                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
