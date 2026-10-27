<?php
/**
 * Full-screen setup wizard. Included by SetupWizard::maybe_render() with $config.
 *
 * @var array $config
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wpddb_css = 'assets/admin/css/setup-wizard' . ( is_rtl() ? '-rtl' : '' ) . '.css';
$wpddb_js  = 'assets/admin/js/setup-wizard.js';
$wpddb_ver = function ( $file ) {
	$path = WPDDB_PATH . $file;
	return file_exists( $path ) ? (string) filemtime( $path ) : WPDDB_VERSION;
};
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php esc_html_e( 'Set Up DocBooker', 'doc-booker' ); ?></title>
<link rel="stylesheet" href="<?php echo esc_url( WPDDB_URL . '/' . $wpddb_css . '?ver=' . $wpddb_ver( $wpddb_css ) ); ?>">
</head>
<body class="wpddb-setup<?php echo is_rtl() ? ' rtl' : ''; ?>">
<canvas id="confetti" hidden></canvas>

<header class="top">
	<div class="brand"><img src="<?php echo esc_url( WPDDB_URL . '/assets/admin/img/doctime-logo-96x96.png' ); ?>" alt=""><span>DocBooker</span></div>
	<div class="steps" aria-live="polite"><span id="steplabel"></span><div class="bars" id="bars"></div></div>
	<button type="button" class="skip" id="skip"></button>
</header>

<main>
	<section class="scene" data-s="practice">
		<h1><span id="hello"></span><br><?php esc_html_e( "Let's Set Up Your Booking Page", 'doc-booker' ); ?></h1>
		<p class="lead"><?php esc_html_e( 'About two minutes. First, tell us what kind of practice this is, so we can suggest the right departments and doctors.', 'doc-booker' ); ?></p>
		<div class="choices">
			<button type="button" class="choice sel" data-type="general"><span class="ic">🏥</span><b><?php esc_html_e( 'General Hospital', 'doc-booker' ); ?></b><small><?php esc_html_e( 'Many departments under one roof: medicine, children, heart, bones and more.', 'doc-booker' ); ?></small></button>
			<button type="button" class="choice" data-type="specialty"><span class="ic">🩺</span><b><?php esc_html_e( 'Specialty Clinic', 'doc-booker' ); ?></b><small><?php esc_html_e( 'One field of care, such as a dental, eye or skin clinic.', 'doc-booker' ); ?></small></button>
		</div>
		<div id="nichebox" hidden>
			<h2 class="sub"><?php esc_html_e( 'Which Specialty?', 'doc-booker' ); ?></h2>
			<p class="lead tight"><?php esc_html_e( 'Pick the closest one. You can change departments later.', 'doc-booker' ); ?></p>
			<div class="niches" id="niches"></div>
		</div>
		<div class="actions"><button type="button" class="btn primary" data-next><?php esc_html_e( "Let's Go", 'doc-booker' ); ?></button><button type="button" class="link" data-skipall><?php esc_html_e( 'Skip Setup', 'doc-booker' ); ?></button></div>
	</section>

	<section class="scene" data-s="start">
		<h1><?php esc_html_e( 'How Do You Want to Start?', 'doc-booker' ); ?></h1>
		<p class="lead" id="startlead"></p>
		<div class="choices">
			<button type="button" class="choice sel" data-mode="own"><span class="ic">✍️</span><b><?php esc_html_e( 'Add My Own Clinic and Doctor', 'doc-booker' ); ?></b><small><?php esc_html_e( "I'll type in my real details now.", 'doc-booker' ); ?></small></button>
			<button type="button" class="choice" data-mode="sample"><span class="ic">✨</span><b id="samplecardtitle"></b><small id="samplecardtext"></small></button>
		</div>
		<div class="actions"><button type="button" class="btn ghost" data-back><?php esc_html_e( 'Back', 'doc-booker' ); ?></button><button type="button" class="btn primary" data-next><?php esc_html_e( 'Next', 'doc-booker' ); ?></button></div>
	</section>

	<section class="scene" data-s="clinic">
		<h1><?php esc_html_e( 'Your Clinic', 'doc-booker' ); ?></h1>
		<p class="lead"><?php esc_html_e( 'Where patients come for their appointment. You can add more clinics later.', 'doc-booker' ); ?></p>
		<div class="panel"><div class="grid2">
			<div><label for="cn"><?php esc_html_e( 'Clinic Name', 'doc-booker' ); ?></label><input id="cn" type="text" maxlength="100"></div>
			<div><label for="cp"><?php esc_html_e( 'Phone Number', 'doc-booker' ); ?> <i><?php esc_html_e( '(optional)', 'doc-booker' ); ?></i></label><input id="cp" type="text" maxlength="40" placeholder="<?php esc_attr_e( 'e.g. +1 555 0100', 'doc-booker' ); ?>"></div>
			<div class="full"><label for="ca"><?php esc_html_e( 'Address', 'doc-booker' ); ?> <i><?php esc_html_e( '(optional)', 'doc-booker' ); ?></i></label><input id="ca" type="text" maxlength="200" placeholder="<?php esc_attr_e( 'e.g. 12 High Street, Springfield', 'doc-booker' ); ?>"></div>
		</div><p class="hint"><?php esc_html_e( 'Shown on the clinic page and in booking emails. Change it any time under DocBooker → Clinics.', 'doc-booker' ); ?></p></div>
		<div class="actions"><button type="button" class="btn ghost" data-back><?php esc_html_e( 'Back', 'doc-booker' ); ?></button><button type="button" class="btn primary" data-next><?php esc_html_e( 'Next', 'doc-booker' ); ?></button></div>
	</section>

	<section class="scene" data-s="depts">
		<h1><?php esc_html_e( 'What Does Your Clinic Treat?', 'doc-booker' ); ?></h1>
		<p class="lead" id="deptlead"></p>
		<div class="split">
			<div class="panel">
				<div class="chips" id="deptchips"></div>
				<div class="mt16"><label for="dx"><?php esc_html_e( 'Something Else?', 'doc-booker' ); ?> <i><?php esc_html_e( '(optional)', 'doc-booker' ); ?></i></label><input id="dx" type="text" maxlength="60" placeholder="<?php esc_attr_e( 'e.g. Physiotherapy', 'doc-booker' ); ?>"></div>
				<p class="err" id="depterr" role="alert" hidden></p>
			</div>
			<div class="panel"><p class="pv-label"><?php esc_html_e( 'What Patients See', 'doc-booker' ); ?></p><div class="depts two" id="pvdepts"></div>
				<p class="hint"><?php esc_html_e( 'The first step of your booking form.', 'doc-booker' ); ?></p></div>
		</div>
		<div class="actions"><button type="button" class="btn ghost" data-back><?php esc_html_e( 'Back', 'doc-booker' ); ?></button><button type="button" class="btn primary" data-next><?php esc_html_e( 'Next', 'doc-booker' ); ?></button></div>
	</section>

	<section class="scene" data-s="doctor">
		<h1><?php esc_html_e( 'Your First Doctor', 'doc-booker' ); ?></h1>
		<p class="lead"><?php esc_html_e( 'Add one now. Photos, degrees and more doctors can wait until later.', 'doc-booker' ); ?></p>
		<div class="split">
			<div>
				<div class="panel"><div class="grid2">
					<div><label for="dn"><?php esc_html_e( "Doctor's Name", 'doc-booker' ); ?></label><input id="dn" type="text" maxlength="100" placeholder="<?php esc_attr_e( 'e.g. Dr. Sarah Khan', 'doc-booker' ); ?>"></div>
					<div><label for="dd"><?php esc_html_e( 'Department', 'doc-booker' ); ?></label><select id="dd"></select></div>
					<div class="full"><label for="ds"><?php esc_html_e( 'Speciality', 'doc-booker' ); ?> <i><?php esc_html_e( '(optional)', 'doc-booker' ); ?></i></label><input id="ds" type="text" maxlength="100" placeholder="<?php esc_attr_e( 'e.g. Family physician', 'doc-booker' ); ?>"></div>
				</div><p class="err" id="docerr" role="alert" hidden></p></div>
				<div class="panel">
					<span class="lbl" id="dayslabel"></span>
					<div class="days" id="days" role="group" aria-labelledby="dayslabel"></div>
					<div class="grid3">
						<div><label for="t1"><?php esc_html_e( 'From', 'doc-booker' ); ?></label><select id="t1"></select></div>
						<div><label for="t2"><?php esc_html_e( 'Until', 'doc-booker' ); ?></label><select id="t2"></select></div>
						<div><label for="t3"><?php esc_html_e( 'Each Appointment', 'doc-booker' ); ?></label><select id="t3">
							<option value="15"><?php esc_html_e( '15 minutes', 'doc-booker' ); ?></option>
							<option value="20"><?php esc_html_e( '20 minutes', 'doc-booker' ); ?></option>
							<option value="30" selected><?php esc_html_e( '30 minutes', 'doc-booker' ); ?></option>
							<option value="60"><?php esc_html_e( '1 hour', 'doc-booker' ); ?></option>
						</select></div>
					</div>
					<p class="hint" id="slotsum"></p>
				</div>
			</div>
			<div class="panel">
				<p class="pv-label"><?php esc_html_e( 'What Patients See', 'doc-booker' ); ?></p>
				<div id="pvdoc"></div>
				<div class="cal" id="pvcal"></div>
			</div>
		</div>
		<div class="actions"><button type="button" class="btn ghost" data-back><?php esc_html_e( 'Back', 'doc-booker' ); ?></button><button type="button" class="btn primary" data-next><?php esc_html_e( 'Next', 'doc-booker' ); ?></button></div>
	</section>

	<section class="scene" data-s="sclinic">
		<h1 id="sclinic-h"></h1>
		<p class="lead"><?php esc_html_e( 'Everything below is filled in for you. Nothing to type.', 'doc-booker' ); ?></p>
		<p class="center badge-row"><span class="badge" id="sbadge"></span></p>
		<div class="panel" id="sclinics"></div>
		<div class="panel"><p class="pv-label"><?php esc_html_e( 'What Patients See', 'doc-booker' ); ?></p><div class="depts" id="sdepts"></div><p class="hint"><?php esc_html_e( 'The first step of your booking form: patients pick a department.', 'doc-booker' ); ?></p></div>
		<div class="actions"><button type="button" class="btn ghost" data-back><?php esc_html_e( 'Back', 'doc-booker' ); ?></button><button type="button" class="btn primary" data-next><?php esc_html_e( 'Next', 'doc-booker' ); ?></button></div>
	</section>

	<section class="scene" data-s="sdoctors">
		<h1><?php esc_html_e( 'Your Sample Doctors', 'doc-booker' ); ?></h1>
		<p class="lead"><?php esc_html_e( 'Each one has working hours, so patients can book straight away. Click a doctor to see their calendar.', 'doc-booker' ); ?></p>
		<div class="split">
			<div class="panel"><p class="pv-label"><?php esc_html_e( 'What Patients See', 'doc-booker' ); ?></p><div class="dlist" id="sdocs"></div></div>
			<div class="panel"><p class="pv-label" id="scalname"></p><div class="cal flush" id="scal"></div></div>
		</div>
		<p class="hint center"><?php esc_html_e( 'Want to change them? Do it any time under DocBooker → All Doctors.', 'doc-booker' ); ?></p>
		<div class="actions"><button type="button" class="btn ghost" data-back><?php esc_html_e( 'Back', 'doc-booker' ); ?></button><button type="button" class="btn primary" data-next><?php esc_html_e( 'Next', 'doc-booker' ); ?></button></div>
	</section>

	<section class="scene" data-s="page">
		<h1><?php esc_html_e( 'Your Booking Page', 'doc-booker' ); ?></h1>
		<p class="lead"><?php esc_html_e( 'Patients book here: department → doctor → day and time → their details.', 'doc-booker' ); ?></p>
		<div class="panel">
			<label for="bp"><?php esc_html_e( 'Page Title', 'doc-booker' ); ?></label><input id="bp" type="text" maxlength="100" value="<?php esc_attr_e( 'Book an Appointment', 'doc-booker' ); ?>">
			<label class="check"><input id="bm" type="checkbox" checked> <?php esc_html_e( "Add it to my site's main menu", 'doc-booker' ); ?></label>
			<p class="hint">
				<?php
				printf(
					/* translators: %s: the site's admin email address. */
					esc_html__( 'New booking emails go to %s. Change it under Settings → General.', 'doc-booker' ),
					'<b>' . esc_html( $config['email'] ) . '</b>'
				);
				?>
			</p>
		</div>
		<div class="actions"><button type="button" class="btn ghost" data-back><?php esc_html_e( 'Back', 'doc-booker' ); ?></button><button type="button" class="btn primary" data-next><?php esc_html_e( 'Create My Booking Page', 'doc-booker' ); ?></button></div>
	</section>

	<section class="scene" data-s="install">
		<h1><?php esc_html_e( 'Setting Things Up…', 'doc-booker' ); ?></h1>
		<p class="lead"><?php esc_html_e( 'This takes a few seconds.', 'doc-booker' ); ?></p>
		<div class="tasks" id="tasks"></div>
		<div class="actions" id="taskerr" hidden></div>
	</section>

	<section class="scene" data-s="done">
		<div class="success" aria-hidden="true"><span class="ring"></span><svg viewBox="0 0 110 110"><circle cx="55" cy="55" r="50"/><path d="M33 57 L48 71 L78 41"/></svg></div>
		<div class="done-h">
			<h1 id="doneh"><?php esc_html_e( 'Congratulations! Your Booking Page Is Live 🎉', 'doc-booker' ); ?></h1>
			<p class="lead" id="donelead"><?php esc_html_e( "Open it and book an appointment the way a patient would. It's the quickest way to check everything.", 'doc-booker' ); ?></p>
			<div class="actions top0"><a class="btn primary" id="openpage" target="_blank" rel="noopener"><?php esc_html_e( 'Open My Booking Page ↗', 'doc-booker' ); ?></a><a class="btn ghost" id="godoctors"><?php esc_html_e( 'Go to My Doctors', 'doc-booker' ); ?></a></div>
			<p class="hint center" id="menunote"></p>
		</div>
		<div class="panel next"><b><?php esc_html_e( 'When You Have a Minute', 'doc-booker' ); ?></b><ul id="nextlist"></ul></div>
		<div id="probox">
			<hr>
			<h2 class="sub"><?php esc_html_e( 'Do More With DocBooker Pro', 'doc-booker' ); ?></h2>
			<p class="lead tight"><?php esc_html_e( 'Everything plugs into the doctors and clinics you just set up.', 'doc-booker' ); ?></p>
			<div class="pro" id="pro"></div>
		</div>
	</section>
</main>

<dialog id="skipdlg">
	<h2 id="skiptitle"></h2>
	<p id="skiptext"></p>
	<div class="actions top0"><button type="button" class="btn ghost" id="skipno"></button><button type="button" class="btn primary" id="skipyes"></button></div>
</dialog>

<script>window.wpddbSetup = <?php echo wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?>;</script>
<script src="<?php echo esc_url( WPDDB_URL . '/' . $wpddb_js . '?ver=' . $wpddb_ver( $wpddb_js ) ); ?>"></script>
</body>
</html>
