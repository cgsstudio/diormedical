<link rel="stylesheet" href="<?php echo esc_url(DIOR_PORTAL_URL . 'assets/css/edit-patient.css?v=' . time()); ?>">

<section class="dior-tab-panel edit-patient-wrapper" id="tab-doc-patients-add" style="display: none;">
    <!-- Breadcrumb Header -->
    <div class="mb-4" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h4 class="mb-0 text-dark" style="font-size: 20px; font-weight: 700;">Add Patient</h4>
        </div>
        <div>
            <ul class="breadcrumb-list">
                <li><a href="#"><i class="fas fa-house" style="font-size: 14px; color: #4F46E5;"></i></a></li>
                <li><span style="color: #94A3B8;">/</span></li>
                <li><a href="javascript:void(0)">Patients</a></li>
                <li><span style="color: #94A3B8;">/</span></li>
                <li class="active"><span>Add Patient</span></li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="section-body">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4>Add New Patient</h4>
                    </div>
                    <div class="card-body">
                        <form novalidate="">
                            <h5 class="card-inside-title">Personal Information</h5>
                            <div class="row">
                                <div class="col-xl-4 col-lg-4 col-md-12 mb-3">
                                    <label>First Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" placeholder="Enter first name">
                                </div>
                                <div class="col-xl-4 col-lg-4 col-md-12 mb-3">
                                    <label>Last Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" placeholder="Enter last name">
                                </div>
                                <div class="col-xl-4 col-lg-4 col-md-12 mb-3">
                                    <label>Gender <span class="text-danger">*</span></label>
                                    <select class="form-select">
                                        <option value="">Select Gender</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-xl-4 col-lg-4 col-md-12 mb-3">
                                    <label>Date of Birth <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control">
                                </div>
                                <div class="col-xl-4 col-lg-4 col-md-12 mb-3">
                                    <label>Age</label>
                                    <input type="number" class="form-control" placeholder="Enter age">
                                </div>
                                <div class="col-xl-4 col-lg-4 col-md-12 mb-3">
                                    <label>Blood Group <span class="text-danger">*</span></label>
                                    <select class="form-select">
                                        <option value="">Select Blood Group</option>
                                        <option value="A+">A+</option>
                                        <option value="A-">A-</option>
                                        <option value="B+">B+</option>
                                        <option value="B-">B-</option>
                                        <option value="AB+">AB+</option>
                                        <option value="AB-">AB-</option>
                                        <option value="O+">O+</option>
                                        <option value="O-">O-</option>
                                    </select>
                                </div>
                            </div>

                            <h5 class="card-inside-title mt-4">Contact Information</h5>
                            <div class="row">
                                <div class="col-xl-4 col-lg-4 col-md-12 mb-3">
                                    <label>Mobile <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control" placeholder="Enter mobile number">
                                </div>
                                <div class="col-xl-4 col-lg-4 col-md-12 mb-3">
                                    <label>Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" placeholder="Enter email address">
                                </div>
                                <div class="col-xl-4 col-lg-4 col-md-12 mb-3">
                                    <label>City <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" placeholder="Enter city">
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary me-2">Add Patient</button>
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
