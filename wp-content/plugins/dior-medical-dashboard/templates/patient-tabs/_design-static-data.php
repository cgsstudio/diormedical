<?php
/**
 * Dior Medical — Patient portal design-review static dataset.
 *
 * IMPORTANT:
 * This file is included only after the Patient Overview/Dashboard has already
 * rendered. It gives the remaining patient tabs stable sample data for UI work.
 * It does not write anything to WordPress/database and does not affect Overview.
 */

$dior_patient_design_static = true;
$design_today = current_time('Y-m-d');

$appointments = [
    [
        'id' => 'APT-2048', 'appt_uid' => 'APT-2048', 'patient_id' => $user_id,
        'patient_user_id' => $user_id, 'doctor_id' => 101,
        'doctor_name' => 'Dr. Sarah Smith', 'provider' => 'Dr. Sarah Smith',
        'provider_spec' => 'Cardiology', 'date' => $design_today, 'appt_date' => $design_today,
        'time' => '10:30 AM', 'appt_time' => '10:30 AM', 'status' => 'Confirmed',
        'type' => 'Video Consultation', 'condition' => 'Routine Follow-up',
        'visit_type' => 'Telehealth', 'duration' => '30 minutes',
        'notes' => 'Follow-up consultation.'
    ],
    [
        'id' => 'APT-2049', 'appt_uid' => 'APT-2049', 'patient_id' => $user_id,
        'patient_user_id' => $user_id, 'doctor_id' => 102,
        'doctor_name' => 'Dr. James Chen', 'provider' => 'Dr. James Chen',
        'provider_spec' => 'Primary Care', 'date' => wp_date('Y-m-d', current_time('timestamp') + DAY_IN_SECONDS),
        'appt_date' => wp_date('Y-m-d', current_time('timestamp') + DAY_IN_SECONDS),
        'time' => '02:00 PM', 'appt_time' => '02:00 PM', 'status' => 'Scheduled',
        'type' => 'Clinic Visit', 'condition' => 'General Checkup',
        'visit_type' => 'In Person', 'duration' => '45 minutes',
        'notes' => 'General health review.'
    ],
    [
        'id' => 'APT-2047', 'appt_uid' => 'APT-2047', 'patient_id' => $user_id,
        'patient_user_id' => $user_id, 'doctor_id' => 103,
        'doctor_name' => 'Dr. Helen Miller', 'provider' => 'Dr. Helen Miller',
        'provider_spec' => 'Internal Medicine',
        'date' => wp_date('Y-m-d', current_time('timestamp') - DAY_IN_SECONDS),
        'appt_date' => wp_date('Y-m-d', current_time('timestamp') - DAY_IN_SECONDS),
        'time' => '09:30 AM', 'appt_time' => '09:30 AM', 'status' => 'Completed',
        'type' => 'Video Consultation', 'condition' => 'Medication Review',
        'visit_type' => 'Telehealth', 'duration' => '30 minutes',
        'notes' => 'Medication review completed.'
    ],
];

$prescriptions = [
    ['id'=>'RX-1001','order_id'=>'RX-1001','medication'=>'Cetirizine 10 mg','name'=>'Cetirizine 10 mg','dosage'=>'10 mg','quantity'=>'30 tablets','refills'=>'2','date_prescribed'=>$design_today,'prescribed_by'=>'Dr. Sarah Smith','condition'=>'Seasonal Allergy','status'=>'Active','instructions'=>'Take once daily.'],
    ['id'=>'RX-1002','order_id'=>'RX-1002','medication'=>'Vitamin D3 1000 IU','name'=>'Vitamin D3 1000 IU','dosage'=>'1000 IU','quantity'=>'60 capsules','refills'=>'1','date_prescribed'=>$design_today,'prescribed_by'=>'Dr. James Chen','condition'=>'Vitamin Supplement','status'=>'Active','instructions'=>'Take with food.'],
];

$patient_rx_rows = [
    ['prescription_id'=>'RX-1001','medications'=>'Cetirizine 10 mg','doctor_name'=>'Dr. Sarah Smith','prescription_date'=>$design_today,'dosage'=>'10 mg — Once daily'],
    ['prescription_id'=>'RX-1002','medications'=>'Vitamin D3 1000 IU','doctor_name'=>'Dr. James Chen','prescription_date'=>$design_today,'dosage'=>'1000 IU — Once daily'],
    ['prescription_id'=>'RX-0998','medications'=>'Lisinopril 10 mg','doctor_name'=>'Dr. Helen Miller','prescription_date'=>wp_date('Y-m-d', current_time('timestamp') - 7 * DAY_IN_SECONDS),'dosage'=>'10 mg — Morning'],
];

$payments = [
    ['id'=>'PAY-1001','invoice_id'=>'#INV-2048','doctor_name'=>'Dr. Sarah Smith','date'=>wp_date('M j, Y', current_time('timestamp')),'amount'=>'$120','discount'=>'10%','tax'=>'$11','total'=>'$119','status'=>'Paid'],
    ['id'=>'PAY-1002','invoice_id'=>'#INV-2047','doctor_name'=>'Dr. James Chen','date'=>wp_date('M j, Y', current_time('timestamp') - DAY_IN_SECONDS),'amount'=>'$80','discount'=>'0%','tax'=>'$8','total'=>'$88','status'=>'Paid'],
    ['id'=>'PAY-1003','invoice_id'=>'#INV-2041','doctor_name'=>'Dr. Helen Miller','date'=>wp_date('M j, Y', current_time('timestamp') - 7 * DAY_IN_SECONDS),'amount'=>'$60','discount'=>'5%','tax'=>'$6','total'=>'$63','status'=>'Paid'],
];

$documents = [
    ['id'=>'DOC-1001','title'=>'Blood Test Report','category'=>'Laboratory','date'=>$design_today,'size'=>'1.2 MB','author'=>'Dr. Sarah Smith','type'=>'PDF','file_type'=>'PDF'],
    ['id'=>'DOC-1002','title'=>'Consultation Summary','category'=>'Clinical','date'=>$design_today,'size'=>'840 KB','author'=>'Dr. James Chen','type'=>'PDF','file_type'=>'PDF'],
    ['id'=>'DOC-1003','title'=>'Medication Report','category'=>'Prescription','date'=>wp_date('Y-m-d', current_time('timestamp') - 3 * DAY_IN_SECONDS),'size'=>'620 KB','author'=>'Dr. Helen Miller','type'=>'PDF','file_type'=>'PDF'],
];

$questionnaires = [
    ['id'=>'Q-1001','title'=>'General Health Intake','status'=>'Submitted','updated_at'=>$design_today],
    ['id'=>'Q-1002','title'=>'Pre-Consultation Questionnaire','status'=>'Pending','updated_at'=>$design_today],
];

$notifications = [
    ['id'=>'N-1001','title'=>'Appointment Confirmed','message'=>'Your appointment with Dr. Sarah Smith is confirmed.','type'=>'Appointment','created_at'=>current_time('mysql'),'timestamp'=>current_time('timestamp'),'is_read'=>false,'action_url'=>'#tab=appointments'],
    ['id'=>'N-1002','title'=>'Prescription Available','message'=>'A new prescription is available in your dashboard.','type'=>'Prescription','created_at'=>current_time('mysql'),'timestamp'=>current_time('timestamp'),'is_read'=>false,'action_url'=>'#tab=docs_meds'],
    ['id'=>'N-1003','title'=>'Report Uploaded','message'=>'Your latest blood test report is available.','type'=>'Report','created_at'=>current_time('mysql'),'timestamp'=>current_time('timestamp'),'is_read'=>true,'action_url'=>'#tab=documents'],
];

$insurance_claims = [
    ['claim_id'=>'CLM-2048','policy_id'=>'POL-8891','claim_date'=>$design_today,'claim_type'=>'Consultation','claim_amount'=>'$120','approved_amount'=>'$108','submitted'=>'Submitted','status'=>'In Review'],
    ['claim_id'=>'CLM-2042','policy_id'=>'POL-8891','claim_date'=>wp_date('Y-m-d', current_time('timestamp') - 7 * DAY_IN_SECONDS),'claim_type'=>'Laboratory','claim_amount'=>'$85','approved_amount'=>'$85','submitted'=>'Processed','status'=>'Approved'],
];

/* Static profile is limited to non-overview tabs and is never persisted. */
$profile = array_merge((array)($profile ?? []), [
    'full_name' => 'Sarah Johnson',
    'patient_id' => 'PT-20481',
    'email' => 'sarah.johnson@example.com',
    'phone' => '+1 (555) 014-2081',
    'gender' => 'Female',
    'dob' => '1992-05-18',
    'blood_group' => 'O+',
    'address' => '245 Medical Center Drive, Austin, TX',
    'avatar_url' => !empty($profile['avatar_url']) ? $profile['avatar_url'] : 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&q=80&w=300',
    'emergency_name' => 'Michael Johnson',
    'emergency_relation' => 'Spouse',
    'emergency_phone' => '+1 (555) 014-2099',
    'emergency_priority' => 'Primary',
]);

$hipaa_intake = [
    'submitted_at' => current_time('mysql'),
    'dob' => '1992-05-18',
    'phone' => '+1 (555) 014-2081',
    'email' => 'sarah.johnson@example.com',
    'address_line1' => '245 Medical Center Drive',
    'city' => 'Austin',
    'state' => 'TX',
    'zip' => '78701',
    'symptoms' => 'Mild dizziness and seasonal allergy symptoms',
    'allergies' => 'Penicillin',
    'medications' => 'Cetirizine 10 mg',
    'medical_history' => 'Seasonal allergies; routine blood pressure monitoring',
    'service_condition' => 'Primary Care & General Medicine',
    'is_18_plus' => 'Yes',
    'is_pregnant' => 'No',
    'symptoms_description' => 'Mild symptoms during the last few days.',
    'symptom_duration' => '3 days',
    'symptom_severity' => 'Mild',
    'taking_medications' => 'Yes',
    'medications_list' => 'Cetirizine 10 mg once daily',
    'drug_allergies' => 'Yes',
    'drug_allergies_list' => 'Penicillin',
    'appointment_type' => 'Telehealth Video Visit',
    'preferred_date' => $design_today,
    'preferred_time' => '10:30 AM',
    'telemedicine_consent' => 'Yes',
    'legal_acknowledgement' => 'Yes',
    'full_legal_name' => 'Sarah Johnson',
    'signature_date' => $design_today,
];

/* ----------------------------------------------------------------------
 * Design-review expansion: exactly 10 static rows per major patient table.
 * These are UI-only records and are never persisted.
 * ---------------------------------------------------------------------- */
$design_doctors = [
    ['Dr. Sarah Smith','Cardiology'], ['Dr. James Chen','Primary Care'],
    ['Dr. Helen Miller','Internal Medicine'], ['Dr. Marcus Sterling','Family Medicine'],
    ['Dr. Olivia Carter','Dermatology'], ['Dr. Daniel Brooks','Neurology'],
    ['Dr. Emily Wilson','Endocrinology'], ['Dr. Noah Bennett','Orthopedics'],
    ['Dr. Sophia Davis','Pediatrics'], ['Dr. Ethan Walker','Gastroenterology'],
];

/* Keep the original examples and expand them to 10 rows. */
for ($i = count($appointments); $i < 10; $i++) {
    $d = $design_doctors[$i];
    $offset = $i - 1;
    $date = wp_date('Y-m-d', current_time('timestamp') + ($offset * DAY_IN_SECONDS));
    $appointments[] = [
        'id' => 'APT-' . (2050 + $i), 'appt_uid' => 'APT-' . (2050 + $i),
        'patient_id' => $user_id, 'patient_user_id' => $user_id, 'doctor_id' => 110 + $i,
        'doctor_name' => $d[0], 'provider' => $d[0], 'provider_spec' => $d[1],
        'date' => $date, 'appt_date' => $date,
        'time' => sprintf('%02d:%02d %s', 9 + ($i % 7), ($i % 2) ? 30 : 0, ($i % 3 === 0 ? 'AM' : 'PM')),
        'appt_time' => sprintf('%02d:%02d %s', 9 + ($i % 7), ($i % 2) ? 30 : 0, ($i % 3 === 0 ? 'AM' : 'PM')),
        'status' => ($i % 3 === 0 ? 'Completed' : ($i % 2 === 0 ? 'Confirmed' : 'Scheduled')),
        'type' => ($i % 2 ? 'Clinic Visit' : 'Video Consultation'),
        'condition' => ['Routine Follow-up','Blood Pressure Review','Annual Wellness','Medication Review'][$i % 4],
        'visit_type' => ($i % 2 ? 'In Person' : 'Telehealth'), 'duration' => '30 minutes',
        'notes' => 'Static design-review appointment record.'
    ];
}

for ($i = count($prescriptions); $i < 10; $i++) {
    $meds = ['Loratadine 10 mg','Metformin 500 mg','Atorvastatin 20 mg','Omeprazole 20 mg','Amlodipine 5 mg','Ibuprofen 200 mg','Levothyroxine 50 mcg','Amoxicillin 500 mg'];
    $med = $meds[$i - 2] ?? 'Daily Multivitamin';
    $prescriptions[] = [
        'id'=>'RX-'.(1003+$i),'order_id'=>'RX-'.(1003+$i),'medication'=>$med,'name'=>$med,
        'dosage'=>preg_replace('/[^0-9.]+/','',$med).' mg','quantity'=>'30 tablets','refills'=>(string)($i % 3),
        'date_prescribed'=>wp_date('Y-m-d', current_time('timestamp') - ($i * DAY_IN_SECONDS)),
        'prescribed_by'=>$design_doctors[$i][0],'condition'=>'Routine Medication','status'=>($i % 4 === 0 ? 'Completed' : 'Active'),
        'instructions'=>'Take as directed by your physician.'
    ];
}

for ($i = count($patient_rx_rows); $i < 10; $i++) {
    $rx = $prescriptions[$i];
    $patient_rx_rows[] = [
        'prescription_id'=>$rx['id'],'medications'=>$rx['medication'],'doctor_name'=>$rx['prescribed_by'],
        'prescription_date'=>$rx['date_prescribed'],'dosage'=>$rx['dosage'].' — Once daily'
    ];
}

for ($i = count($payments); $i < 10; $i++) {
    $subtotal = 65 + ($i * 10); $tax = round($subtotal * .08); $total = $subtotal + $tax;
    $payments[] = [
        'id'=>'PAY-'.(1004+$i),'invoice_id'=>'#INV-'.(2050+$i),'doctor_name'=>$design_doctors[$i][0],
        'date'=>wp_date('M j, Y', current_time('timestamp') - ($i * DAY_IN_SECONDS)),
        'amount'=>'$'.$subtotal,'discount'=>($i % 3 ? '0%' : '10%'),'tax'=>'$'.$tax,'total'=>'$'.$total,
        'status'=>($i % 4 === 0 ? 'Pending' : 'Paid')
    ];
}

for ($i = count($documents); $i < 10; $i++) {
    $types = ['Lab Report','Imaging Report','Visit Summary','Prescription Report','Discharge Summary','Insurance Report','Clinical Note'];
    $type = $types[$i - 3] ?? 'Medical Report';
    $documents[] = [
        'id'=>'DOC-'.(1004+$i),'title'=>$type.' '.($i-2),'category'=>'Medical Record',
        'date'=>wp_date('Y-m-d', current_time('timestamp') - ($i * DAY_IN_SECONDS)),
        'size'=>(500+$i*70).' KB','author'=>$design_doctors[$i][0],'type'=>'PDF','file_type'=>'PDF'
    ];
}

for ($i = count($questionnaires); $i < 10; $i++) {
    $questionnaires[] = ['id'=>'Q-'.(1003+$i),'title'=>['Medication History','Allergy Assessment','Lifestyle Intake','Travel Health Form','Consent Form','Wellness Review','Follow-up Assessment','Telehealth Consent'][$i-2] ?? 'Health Questionnaire','status'=>($i % 3 === 0 ? 'Pending' : 'Submitted'),'updated_at'=>wp_date('Y-m-d', current_time('timestamp') - $i * DAY_IN_SECONDS)];
}

for ($i = count($notifications); $i < 10; $i++) {
    $types = ['Appointment','Prescription','Report','Billing','Telemedicine','Reminder','Message'];
    $type = $types[$i % count($types)];
    $notifications[] = [
        'id'=>'N-'.(1004+$i),'title'=>$type.' Update','message'=>'This is a static notification for the patient portal design review.',
        'type'=>$type,'created_at'=>wp_date('Y-m-d H:i:s', current_time('timestamp') - $i * DAY_IN_SECONDS),
        'timestamp'=>current_time('timestamp') - $i * DAY_IN_SECONDS,'is_read'=>($i % 3 === 0),'action_url'=>'#tab=notifications'
    ];
}

for ($i = count($insurance_claims); $i < 10; $i++) {
    $insurance_claims[] = [
        'claim_id'=>'CLM-'.(2050+$i),'policy_id'=>'POL-8891','claim_date'=>wp_date('Y-m-d', current_time('timestamp') - $i * DAY_IN_SECONDS),
        'claim_type'=>['Consultation','Laboratory','Imaging','Medication'][$i % 4],'claim_amount'=>'$'.(70+$i*9),
        'approved_amount'=>'$'.(60+$i*8),'submitted'=>($i % 2 ? 'Submitted' : 'Processed'),'status'=>($i % 3 ? 'Approved' : 'In Review')
    ];
}

// Removed static feedback sample and synthetic row generator for feedback history.
