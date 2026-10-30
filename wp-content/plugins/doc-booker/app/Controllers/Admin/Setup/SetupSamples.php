<?php
namespace WpDreamers\WPDDB\Controllers\Admin\Setup;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

/**
 * The practice types the setup wizard offers and the sample clinic, departments
 * and doctors for each. The wizard's preview and the posts it creates both come
 * from here, so what the owner is shown is what gets made.
 */
class SetupSamples {

	/**
	 * Working hours of the four sample doctors, in minutes from midnight.
	 * Days: 0 = Monday … 6 = Sunday. Doctor n works at clinic n % 2.
	 */
	const SCHEDULES = [
		[ 'days' => [ 0, 2, 4 ], 'from' => 540, 'to' => 780 ],
		[ 'days' => [ 1, 3 ], 'from' => 840, 'to' => 1080 ],
		[ 'days' => [ 0, 1, 2, 3, 4 ], 'from' => 600, 'to' => 840 ],
		[ 'days' => [ 5 ], 'from' => 540, 'to' => 900 ],
	];

	const SLOT_MINUTES = 30;

	/**
	 * @return array<string, array> Keyed by practice type; 'general' first.
	 */
	public static function practices() {
		$practices = [
			'general'     => [
				'label'   => __( 'General Hospital', 'doc-booker' ),
				'icon'    => '🏥',
				'image'   => 'general-hospital',
				'clinic'  => __( 'Riverside General Hospital', 'doc-booker' ),
				'clinics' => [
					[ __( 'Riverside General Hospital', 'doc-booker' ), '24 River Road', '+1 555 0142' ],
					[ __( 'Riverside Outpatient Centre', 'doc-booker' ), '3 Mill Lane', '+1 555 0177' ],
				],
				'depts'   => [ __( 'General Medicine', 'doc-booker' ), __( 'Cardiology', 'doc-booker' ), __( 'Pediatrics', 'doc-booker' ), __( 'Orthopedics', 'doc-booker' ) ],
				'more'    => [ __( 'Dental', 'doc-booker' ), __( 'Dermatology', 'doc-booker' ), __( 'Eye Care', 'doc-booker' ), __( "Women's Health", 'doc-booker' ), __( 'Mental Health', 'doc-booker' ), __( 'Emergency Care', 'doc-booker' ) ],
				'doctors' => [
					[ 'Dr. Emily Morgan', 0, __( 'Internal medicine', 'doc-booker' ) ],
					[ 'Dr. James Lee', 1, __( 'Heart specialist', 'doc-booker' ) ],
					[ 'Dr. Aisha Patel', 2, __( 'Child specialist', 'doc-booker' ) ],
					[ 'Dr. Carlos Rivera', 3, __( 'Bone and joint surgeon', 'doc-booker' ) ],
				],
			],
			'dental'      => [
				'label'   => __( 'Dental', 'doc-booker' ),
				'icon'    => '🦷',
				'image'   => 'dental-clinic',
				'clinic'  => __( 'Bright Smile Dental Clinic', 'doc-booker' ),
				'clinics' => [
					[ __( 'Bright Smile Dental – Downtown', 'doc-booker' ), '15 Market Street', '+1 555 0110' ],
					[ __( 'Bright Smile Dental – Northside', 'doc-booker' ), '92 North Avenue', '+1 555 0111' ],
				],
				'depts'   => [ __( 'General Dentistry', 'doc-booker' ), __( 'Orthodontics', 'doc-booker' ), __( 'Cosmetic Dentistry', 'doc-booker' ) ],
				'more'    => [ __( "Children's Dentistry", 'doc-booker' ), __( 'Oral Surgery', 'doc-booker' ), __( 'Implants', 'doc-booker' ) ],
				'doctors' => [
					[ 'Dr. Olivia Grant', 0, __( 'Family dentist', 'doc-booker' ) ],
					[ 'Dr. Ravi Menon', 1, __( 'Braces and aligners', 'doc-booker' ) ],
					[ 'Dr. Hannah Brooks', 2, __( 'Whitening and veneers', 'doc-booker' ) ],
					[ 'Dr. Lucas Ford', 0, __( 'Root canal treatment', 'doc-booker' ) ],
				],
			],
			'pediatrics'  => [
				'label'   => __( 'Pediatrics', 'doc-booker' ),
				'icon'    => '🧸',
				'image'   => 'pediatrics',
				'clinic'  => __( "Little Steps Children's Clinic", 'doc-booker' ),
				'clinics' => [
					[ __( 'Little Steps – Main Clinic', 'doc-booker' ), '7 Park Road', '+1 555 0120' ],
					[ __( 'Little Steps – Westside', 'doc-booker' ), '41 West Street', '+1 555 0121' ],
				],
				'depts'   => [ __( 'General Pediatrics', 'doc-booker' ), __( 'Newborn Care', 'doc-booker' ), __( 'Child Nutrition', 'doc-booker' ) ],
				'more'    => [ __( 'Vaccinations', 'doc-booker' ), __( 'Child Development', 'doc-booker' ), __( 'Teen Health', 'doc-booker' ) ],
				'doctors' => [
					[ 'Dr. Aisha Patel', 0, __( 'Child specialist', 'doc-booker' ) ],
					[ 'Dr. Ben Carter', 1, __( 'Newborn specialist', 'doc-booker' ) ],
					[ 'Dr. Maria Lopez', 2, __( 'Child nutrition', 'doc-booker' ) ],
					[ 'Dr. Tom Hughes', 0, __( 'Vaccinations and check-ups', 'doc-booker' ) ],
				],
			],
			'dermatology' => [
				'label'   => __( 'Dermatology', 'doc-booker' ),
				'icon'    => '🧴',
				'image'   => 'dermatology',
				'clinic'  => __( 'Clear Skin Dermatology', 'doc-booker' ),
				'clinics' => [
					[ __( 'Clear Skin – City Centre', 'doc-booker' ), '10 Queen Street', '+1 555 0130' ],
					[ __( 'Clear Skin – Lakeside', 'doc-booker' ), '5 Lake View', '+1 555 0131' ],
				],
				'depts'   => [ __( 'Medical Dermatology', 'doc-booker' ), __( 'Cosmetic Dermatology', 'doc-booker' ), __( 'Hair and Scalp', 'doc-booker' ) ],
				'more'    => [ __( 'Skin Surgery', 'doc-booker' ), __( 'Laser Treatment', 'doc-booker' ), __( 'Allergy and Eczema', 'doc-booker' ) ],
				'doctors' => [
					[ 'Dr. Nina Shah', 0, __( 'Acne and eczema', 'doc-booker' ) ],
					[ 'Dr. Adam Cole', 1, __( 'Botox and fillers', 'doc-booker' ) ],
					[ 'Dr. Priya Rao', 2, __( 'Hair loss', 'doc-booker' ) ],
					[ 'Dr. Mark Evans', 0, __( 'Mole and skin checks', 'doc-booker' ) ],
				],
			],
			'eye-care'    => [
				'label'   => __( 'Eye Care', 'doc-booker' ),
				'icon'    => '👁️',
				'image'   => 'eye-care',
				'clinic'  => __( 'Clear View Eye Centre', 'doc-booker' ),
				'clinics' => [
					[ __( 'Clear View – Main Centre', 'doc-booker' ), '22 High Street', '+1 555 0140' ],
					[ __( 'Clear View – Eastgate', 'doc-booker' ), '8 Eastgate Road', '+1 555 0141' ],
				],
				'depts'   => [ __( 'General Eye Care', 'doc-booker' ), __( 'Cataract', 'doc-booker' ), __( 'Retina', 'doc-booker' ) ],
				'more'    => [ __( 'Glaucoma', 'doc-booker' ), __( "Children's Eyes", 'doc-booker' ), __( 'LASIK', 'doc-booker' ) ],
				'doctors' => [
					[ 'Dr. Laura King', 0, __( 'Eye exams and glasses', 'doc-booker' ) ],
					[ 'Dr. Omar Haddad', 1, __( 'Cataract surgeon', 'doc-booker' ) ],
					[ 'Dr. Grace Liu', 2, __( 'Retina specialist', 'doc-booker' ) ],
					[ 'Dr. Sam Wilson', 0, __( 'Contact lenses', 'doc-booker' ) ],
				],
			],
			'cardiology'  => [
				'label'   => __( 'Cardiology', 'doc-booker' ),
				'icon'    => '❤️',
				'image'   => 'cardiology',
				'clinic'  => __( 'Heartline Cardiology Institute', 'doc-booker' ),
				'clinics' => [
					[ __( 'Heartline – Main Institute', 'doc-booker' ), '1 Harbour Way', '+1 555 0150' ],
					[ __( 'Heartline – Riverside', 'doc-booker' ), '60 River Road', '+1 555 0151' ],
				],
				'depts'   => [ __( 'General Cardiology', 'doc-booker' ), __( 'Heart Rhythm', 'doc-booker' ), __( 'Cardiac Imaging', 'doc-booker' ) ],
				'more'    => [ __( 'Heart Surgery', 'doc-booker' ), __( 'Blood Pressure', 'doc-booker' ), __( 'Cardiac Rehabilitation', 'doc-booker' ) ],
				'doctors' => [
					[ 'Dr. James Lee', 0, __( 'Heart specialist', 'doc-booker' ) ],
					[ 'Dr. Sofia Romano', 1, __( 'Heart rhythm', 'doc-booker' ) ],
					[ 'Dr. David Kim', 2, __( 'Echo and ECG', 'doc-booker' ) ],
					[ 'Dr. Rachel Adams', 0, __( 'Blood pressure clinic', 'doc-booker' ) ],
				],
			],
			'orthopedics' => [
				'label'   => __( 'Orthopedics', 'doc-booker' ),
				'icon'    => '🦴',
				'image'   => 'orthopedics',
				'clinic'  => __( 'Motion Orthopedic Clinic', 'doc-booker' ),
				'clinics' => [
					[ __( 'Motion Orthopedic – Main', 'doc-booker' ), '30 Station Road', '+1 555 0160' ],
					[ __( 'Motion Orthopedic – Sports Centre', 'doc-booker' ), '2 Stadium Way', '+1 555 0161' ],
				],
				'depts'   => [ __( 'Joint Care', 'doc-booker' ), __( 'Sports Injuries', 'doc-booker' ), __( 'Spine', 'doc-booker' ) ],
				'more'    => [ __( 'Physiotherapy', 'doc-booker' ), __( 'Hand and Wrist', 'doc-booker' ), __( 'Foot and Ankle', 'doc-booker' ) ],
				'doctors' => [
					[ 'Dr. Carlos Rivera', 0, __( 'Knee and hip', 'doc-booker' ) ],
					[ 'Dr. Emma Scott', 1, __( 'Sports injuries', 'doc-booker' ) ],
					[ 'Dr. Henry Park', 2, __( 'Back and neck', 'doc-booker' ) ],
					[ 'Dr. Zoe Turner', 1, __( 'Physiotherapy', 'doc-booker' ) ],
				],
			],
			'mental-health' => [
				'label'   => __( 'Mental Health', 'doc-booker' ),
				'icon'    => '🧠',
				'image'   => 'mental-health',
				'clinic'  => __( 'Calm Mind Wellness Centre', 'doc-booker' ),
				'clinics' => [
					[ __( 'Calm Mind – Wellness Centre', 'doc-booker' ), '18 Garden Street', '+1 555 0170' ],
					[ __( 'Calm Mind – Family Practice', 'doc-booker' ), '4 Elm Close', '+1 555 0171' ],
				],
				'depts'   => [ __( 'Psychiatry', 'doc-booker' ), __( 'Counselling', 'doc-booker' ), __( 'Child and Teen', 'doc-booker' ) ],
				'more'    => [ __( 'Couples Therapy', 'doc-booker' ), __( 'Addiction Support', 'doc-booker' ), __( 'Sleep Clinic', 'doc-booker' ) ],
				'doctors' => [
					[ 'Dr. Julia Bennett', 0, __( 'Psychiatrist', 'doc-booker' ) ],
					[ 'Dr. Noah Reed', 1, __( 'Counsellor', 'doc-booker' ) ],
					[ 'Dr. Mia Clark', 2, __( 'Child and teen therapist', 'doc-booker' ) ],
					[ 'Dr. Ethan Price', 1, __( 'Anxiety and stress', 'doc-booker' ) ],
				],
			],
			'womens-health' => [
				'label'   => __( "Women's Health", 'doc-booker' ),
				'icon'    => '🌸',
				'image'   => 'womens-health',
				'clinic'  => __( "Bloom Women's Health", 'doc-booker' ),
				'clinics' => [
					[ __( 'Bloom – Main Clinic', 'doc-booker' ), '12 Rose Avenue', '+1 555 0180' ],
					[ __( 'Bloom – Family Centre', 'doc-booker' ), '77 Orchard Road', '+1 555 0181' ],
				],
				'depts'   => [ __( 'Obstetrics', 'doc-booker' ), __( 'Gynecology', 'doc-booker' ), __( 'Fertility', 'doc-booker' ) ],
				'more'    => [ __( 'Breast Health', 'doc-booker' ), __( 'Menopause Care', 'doc-booker' ), __( 'Prenatal Classes', 'doc-booker' ) ],
				'doctors' => [
					[ 'Dr. Sarah Collins', 0, __( 'Pregnancy care', 'doc-booker' ) ],
					[ 'Dr. Leila Haddad', 1, __( 'Gynecologist', 'doc-booker' ) ],
					[ 'Dr. Anna White', 2, __( 'Fertility specialist', 'doc-booker' ) ],
					[ 'Dr. Kate Moore', 0, __( 'Pregnancy scans', 'doc-booker' ) ],
				],
			],
			'imaging'     => [
				'label'   => __( 'Diagnostic Imaging', 'doc-booker' ),
				'icon'    => '🩻',
				'image'   => 'diagnostic-imaging',
				'clinic'  => __( 'ClearScan Diagnostic Imaging', 'doc-booker' ),
				'clinics' => [
					[ __( 'ClearScan – Main Centre', 'doc-booker' ), '9 Science Park', '+1 555 0190' ],
					[ __( 'ClearScan – North Branch', 'doc-booker' ), '51 North Road', '+1 555 0191' ],
				],
				'depts'   => [ __( 'X-Ray', 'doc-booker' ), __( 'Ultrasound', 'doc-booker' ), __( 'MRI and CT', 'doc-booker' ) ],
				'more'    => [ __( 'Mammography', 'doc-booker' ), __( 'Bone Density', 'doc-booker' ), __( 'Lab Tests', 'doc-booker' ) ],
				'doctors' => [
					[ 'Dr. Peter Hall', 0, __( 'Radiologist', 'doc-booker' ) ],
					[ 'Dr. Fatima Ali', 1, __( 'Ultrasound', 'doc-booker' ) ],
					[ 'Dr. George Young', 2, __( 'MRI and CT', 'doc-booker' ) ],
					[ 'Dr. Lily Evans', 1, __( "Women's imaging", 'doc-booker' ) ],
				],
			],
		];

		/**
		 * Filters the practice types and sample data the setup wizard offers.
		 *
		 * @param array $practices Keyed by practice type.
		 */
		return apply_filters( 'wpddb_setup_practices', $practices );
	}

	/**
	 * One practice type, falling back to the general hospital.
	 *
	 * @param string $key Practice type.
	 *
	 * @return array
	 */
	public static function get( $key ) {
		$practices = self::practices();
		return $practices[ $key ] ?? $practices['general'];
	}
}
