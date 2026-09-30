<link rel="stylesheet" href="<?php echo esc_url(DIOR_PORTAL_URL . 'assets/css/book-appointment.css?v=' . time()); ?>">

<section class="dior-tab-panel appointment-form-wrapper" id="tab-doc-appointments-book" style="display: none;">
    <!-- Breadcrumb Header -->
    <div class="mb-4" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size: 20px; font-weight: 700;">Book Appointment</h4>
        </div>
        <div>
            <ul class="breadcrumb-list">
                <li><a href="#"><i class="fa-solid fa-house" style="font-size: 14px; color: #4F46E5;"></i></a></li>
                <li><span style="color: #94A3B8;">/</span></li>
                <li><a href="javascript:void(0)">Appointments</a></li>
                <li><span style="color: #94A3B8;">/</span></li>
                <li class="active">Book Appointment</li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="section-body">
        <div class="row">
            <div class="col-12 col-md-12 col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h4>Book Appointment</h4>
                    </div>
                    <div class="card-body">
                        <form novalidate="">
                            <h5 class="card-inside-title">Patient Information</h5>
                            <div class="row">
                                <div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 mb-3">
                                    <label>First name <span class="text-danger">*</span></label>
                                    <input type="text" placeholder="First name" class="form-control">
                                </div>
                                <div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 mb-3">
                                    <label>Middle name</label>
                                    <input type="text" placeholder="Middle name" class="form-control">
                                </div>
                                <div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 mb-3">
                                    <label>Last name</label>
                                    <input type="text" placeholder="Last name" class="form-control">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 mb-3">
                                    <label>Gender <span class="text-danger">*</span></label>
                                    <select class="form-select">
                                        <option value="" disabled="" selected="">Select Gender</option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 mb-3">
                                    <label>Date Of Birth <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control">
                                </div>
                                <div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 mb-3">
                                    <label>Blood Group</label>
                                    <select class="form-select">
                                        <option value="" disabled="" selected="">Select Blood Group</option>
                                        <option value="A+">A+</option>
                                        <option value="A-">A-</option>
                                        <option value="B+">B+</option>
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
                                    <input type="tel" placeholder="Mobile" class="form-control">
                                </div>
                                <div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 mb-3">
                                    <label>Email <span class="text-danger">*</span></label>
                                    <input type="email" placeholder="Email" class="form-control">
                                </div>
                                <div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 mb-3">
                                    <label>Patient ID (if existing)</label>
                                    <input type="text" placeholder="Patient ID" class="form-control">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 mb-3">
                                    <label>Address</label>
                                    <textarea rows="2" placeholder="Address" class="form-control"></textarea>
                                </div>
                            </div>

                            <h5 class="card-inside-title mt-4">Insurance Information</h5>
                            <div class="row">
                                <div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 mb-3">
                                    <label>Insurance Provider</label>
                                    <input type="text" placeholder="Provider" class="form-control">
                                </div>
                                <div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 mb-3">
                                    <label>Policy Number</label>
                                    <input type="text" placeholder="Policy Number" class="form-control">
                                </div>
                                <div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 mb-3">
                                    <label>Group Number</label>
                                    <input type="text" placeholder="Group Number" class="form-control">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 mb-3">
                                    <label>Insurance Holder Name (if not patient)</label>
                                    <input type="text" placeholder="Holder Name" class="form-control">
                                </div>
                                <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 mb-3">
                                    <label>Relationship to Patient</label>
                                    <select class="form-select">
                                        <option value="self"> Self</option>
                                        <option value="spouse"> Spouse</option>
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
                                    <textarea rows="2" placeholder="Diabetes, Hypertension, etc." class="form-control"></textarea>
                                </div>
                                <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 mb-3">
                                    <label>Current Medications</label>
                                    <textarea rows="2" placeholder="List all current medications" class="form-control"></textarea>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 mb-3">
                                    <label>Allergies</label>
                                    <textarea rows="2" placeholder="Medications, food, etc." class="form-control"></textarea>
                                </div>
                                <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 mb-3">
                                    <label>Previous Surgeries</label>
                                    <textarea rows="2" placeholder="Type and date of surgery" class="form-control"></textarea>
                                </div>
                            </div>

                            <h5 class="card-inside-title mt-4">Emergency Contact</h5>
                            <div class="row">
                                <div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 mb-3">
                                    <label>Contact Name</label>
                                    <input type="text" placeholder="Contact Name" class="form-control">
                                </div>
                                <div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 mb-3">
                                    <label>Relationship</label>
                                    <select class="form-select">
                                        <option value="self"> Self</option>
                                        <option value="spouse"> Spouse</option>
                                        <option value="child"> Child</option>
                                        <option value="parent"> Parent</option>
                                        <option value="other"> Other</option>
                                    </select>
                                </div>
                                <div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 mb-3">
                                    <label>Contact Phone</label>
                                    <input type="tel" placeholder="Contact Phone" class="form-control">
                                </div>
                            </div>

                            <h5 class="card-inside-title mt-4">Appointment Details</h5>
                            <div class="row">
                                <div class="col-xl-4 col-lg-4 col-md-12 col-sm-12 mb-3">
                                    <label>Department <span class="text-danger">*</span></label>
                                    <select class="form-select">
                                        <option value="" disabled="" selected="">Select Department</option>
                                        <option value="cardiology">Cardiology </option>
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
                                        <option value="" disabled="" selected="">Select Doctor</option>
                                        <option value="Dr.Rajesh">Dr.Rajesh</option>
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
                                        <option value="followup"> Follow-up</option>
                                        <option value="emergency"> Emergency</option>
                                        <option value="consultation"> Consultation</option>
                                        <option value="procedure"> Procedure/Surgery</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 mb-3">
                                    <label>Date Of Appointment <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control">
                                </div>
                                <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <label class="mb-0">Reason for Visit <span class="text-danger">*</span></label>
                                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-2 py-0 font-11">
                                            <i class="material-icons font-14 align-middle">auto_awesome</i> AI Auto-Match Department
                                        </button>
                                    </div>
                                    <textarea rows="2" placeholder="Describe symptoms or primary reason for consultation..." class="form-control"></textarea>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 mb-3">
                                    <label class="mb-2">Time Of Appointment <span class="text-danger">*</span></label>
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <h6>Morning</h6>
                                            <div role="group" class="btn-group">
                                                <input type="radio" name="timeSlot" class="btn-check" id="morning_0">
                                                <label class="btn btn-outline-primary" for="morning_0">09:00 AM</label>
                                                <input type="radio" name="timeSlot" class="btn-check" id="morning_1">
                                                <label class="btn btn-outline-primary" for="morning_1">10:00 AM</label>
                                                <input type="radio" name="timeSlot" class="btn-check" id="morning_2">
                                                <label class="btn btn-outline-primary" for="morning_2">11:00 AM</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <h6>Afternoon</h6>
                                            <div role="group" class="btn-group">
                                                <input type="radio" name="timeSlot" class="btn-check" id="afternoon_0">
                                                <label class="btn btn-outline-primary" for="afternoon_0">01:00 PM</label>
                                                <input type="radio" name="timeSlot" class="btn-check" id="afternoon_1">
                                                <label class="btn btn-outline-primary" for="afternoon_1">02:00 PM</label>
                                                <input type="radio" name="timeSlot" class="btn-check" id="afternoon_2">
                                                <label class="btn btn-outline-primary" for="afternoon_2">03:00 PM</label>
                                                <input type="radio" name="timeSlot" class="btn-check" id="afternoon_3">
                                                <label class="btn btn-outline-primary" for="afternoon_3">04:00 PM</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <h6>Evening</h6>
                                            <div role="group" class="btn-group">
                                                <input type="radio" name="timeSlot" class="btn-check" id="evening_0">
                                                <label class="btn btn-outline-primary" for="evening_0">05:00 PM</label>
                                                <input type="radio" name="timeSlot" class="btn-check" id="evening_1">
                                                <label class="btn btn-outline-primary" for="evening_1">06:00 PM</label>
                                                <input type="radio" name="timeSlot" class="btn-check" id="evening_2">
                                                <label class="btn btn-outline-primary" for="evening_2">07:00 PM</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 mb-3">
                                    <label>Symptoms/Condition</label>
                                    <textarea rows="2" placeholder="Symptoms" class="form-control"></textarea>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 mb-3">
                                    <label>Additional Notes</label>
                                    <textarea rows="2" placeholder="Notes" class="form-control"></textarea>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-xl-12 col-lg-12 col-md-12 col-sm-12 mb-5">
                                    <label class="mb-2">Upload Previous Medical Reports</label>
                                    <div class="file-upload-wrapper">
                                        <input type="file" class="file-input" accept="*">
                                        <div class="file-upload-area">
                                            <div class="upload-icon"><i class="fas fa-cloud-upload-alt"></i></div>
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
                                    <button type="submit" class="btn btn-primary me-2">Submit Appointment</button>
                                    <button type="button" class="btn btn-light">Reset Form</button>
                                </div>
                            </div>

                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
