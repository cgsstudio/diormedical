<?php
namespace WpDreamers\WPDDB\Controllers\Admin\Setup;

use WpDreamers\WPDDB\Traits\SingletonTrait;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

/**
 * "Finish Setting Up DocBooker" card at the top of the Doctors list.
 *
 * Every item is worked out from the site itself (not from what the wizard
 * ticked), so it stays true when the owner adds or deletes things by hand. The
 * card goes away once everything is done, or when the owner hides it. It is
 * shown to new installs and to any site that has no doctors yet.
 */
class SetupChecklist {
	use SingletonTrait;

	const HIDDEN = 'wpddb_setup_checklist_hidden';
	const NONCE  = 'wpddb_setup_checklist';

	public function __construct() {
		add_action( 'admin_notices', [ $this, 'removed_notice' ] );
		// Printed inside the list, under the page title (admin_notices output that
		// is not a .notice would sit above the title).
		add_filter( 'views_edit-' . wpddb()->post_type_doctor, [ $this, 'render_in_list' ] );
		add_action( 'admin_init', [ $this, 'handle_hide' ] );
		add_action( 'admin_post_wpddb_remove_samples', [ $this, 'handle_remove_samples' ] );
	}

	/**
	 * @return array List of [ key, label, done, link url, link text ].
	 */
	public static function items() {
		global $wpdb;
		$doctor = wpddb()->post_type_doctor;

		$clinics = (int) ( wp_count_posts( wpddb()->post_type_clinic )->publish ?? 0 );
		$doctors = (int) ( wp_count_posts( $doctor )->publish ?? 0 );
		$hours   = $doctors && (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT 1 FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
			 WHERE p.post_type = %s AND p.post_status = 'publish' AND m.meta_key = 'wpddb_doctor_schedule' AND m.meta_value NOT IN ( '', 'a:0:{}' ) LIMIT 1",
			$doctor
		) );
		$page    = (int) $wpdb->get_var(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' AND post_content LIKE '%[wpddb\_doctor\_booking\_form%' ORDER BY ID DESC LIMIT 1"
		);
		$no_photo = $doctors ? (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_thumbnail_id'
			 WHERE p.post_type = %s AND p.post_status = 'publish' AND ( m.meta_value IS NULL OR m.meta_value IN ( '', '0' ) )",
			$doctor
		) ) : 1;
		$table    = $wpdb->prefix . 'wpddb_bookings';
		$booked   = $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) )
			&& (bool) $wpdb->get_var( "SELECT EXISTS( SELECT 1 FROM `{$table}` )" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix.

		$items = [
			[ 'clinic', __( 'Add a clinic', 'doc-booker' ), $clinics > 0, admin_url( 'post-new.php?post_type=' . wpddb()->post_type_clinic ), __( 'Add Clinic', 'doc-booker' ) ],
			[ 'hours', __( 'Add a doctor with working hours', 'doc-booker' ), $hours, admin_url( 'post-new.php?post_type=' . $doctor ), __( 'Add Doctor', 'doc-booker' ) ],
			[ 'page', __( 'Create your booking page', 'doc-booker' ), $page > 0, SetupWizard::url(), __( 'Run the Setup', 'doc-booker' ) ],
			[ 'photos', __( 'Add a photo to each doctor', 'doc-booker' ), 0 === $no_photo, admin_url( 'edit.php?post_type=' . $doctor ), __( 'Edit Doctors', 'doc-booker' ) ],
			[ 'booking', __( 'Get your first booking', 'doc-booker' ), $booked, $page ? get_permalink( $page ) : '', __( 'Open Your Booking Page', 'doc-booker' ) ],
		];
		if ( SetupInstaller::has_samples() ) {
			$items[] = [ 'samples', __( 'Replace the sample data with your own', 'doc-booker' ), false, '', '' ];
		}
		return $items;
	}

	private function visible() {
		if ( ! current_user_can( 'manage_options' ) || get_option( self::HIDDEN ) ) {
			return false;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit-' . wpddb()->post_type_doctor !== $screen->id ) {
			return false;
		}
		$state = SetupInstaller::state();
		return ! empty( $state['offered'] ) || ! empty( $state['started'] )
			|| 0 === (int) ( wp_count_posts( wpddb()->post_type_doctor )->publish ?? 0 );
	}

	public function removed_notice() {
		if ( ! empty( $_GET['wpddb_samples_removed'] ) && current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'The sample doctors and clinics were removed. Your own data was not touched.', 'doc-booker' ) . '</p></div>';
		}
	}

	/**
	 * @param array $views The list's status links, passed through unchanged.
	 *
	 * @return array
	 */
	public function render_in_list( $views ) {
		$this->render();
		return $views;
	}

	public function render() {
		if ( ! $this->visible() ) {
			return;
		}
		$items = self::items();
		$done  = count( array_filter( $items, function ( $i ) {
			return $i[2];
		} ) );
		if ( $done === count( $items ) ) {
			return;
		}
		$state   = SetupInstaller::state();
		$hide    = wp_nonce_url( add_query_arg( 'wpddb_checklist', 'hide' ), self::NONCE );
		$percent = (int) round( $done / count( $items ) * 100 );
		?>
		<div class="wpddb-checklist" role="region" aria-label="<?php esc_attr_e( 'Finish Setting Up DocBooker', 'doc-booker' ); ?>">
			<div class="wpddb-checklist__head">
				<div>
					<h2><?php esc_html_e( 'Finish Setting Up DocBooker', 'doc-booker' ); ?></h2>
					<p>
						<?php
						/* translators: 1: items done, 2: all items. */
						echo esc_html( sprintf( __( '%1$d of %2$d done. Shown here until everything is ticked, or until you hide it.', 'doc-booker' ), $done, count( $items ) ) );
						?>
					</p>
				</div>
				<?php if ( empty( $state['completed'] ) ) : ?>
					<a class="wpddb-checklist__btn" href="<?php echo esc_url( SetupWizard::url() ); ?>"><?php esc_html_e( 'Run the Setup', 'doc-booker' ); ?></a>
				<?php endif; ?>
			</div>
			<div class="wpddb-checklist__bar" role="progressbar" aria-valuenow="<?php echo esc_attr( $percent ); ?>" aria-valuemin="0" aria-valuemax="100"><i style="width:<?php echo esc_attr( $percent ); ?>%"></i></div>
			<ul>
				<?php foreach ( $items as $item ) : ?>
					<li class="<?php echo $item[2] ? 'is-done' : ''; ?>">
						<span class="wpddb-checklist__mark" aria-hidden="true"><?php echo $item[2] ? '✓' : ''; ?></span>
						<span><?php echo esc_html( $item[1] ); ?><?php echo $item[2] ? '<span class="screen-reader-text"> ' . esc_html__( '(done)', 'doc-booker' ) . '</span>' : ''; ?></span>
						<?php if ( ! $item[2] && 'samples' === $item[0] ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm(<?php echo esc_attr( wp_json_encode( __( 'Delete all sample doctors and clinics? Your own doctors, clinics and bookings stay.', 'doc-booker' ) ) ); ?>);">
								<input type="hidden" name="action" value="wpddb_remove_samples">
								<?php wp_nonce_field( self::NONCE ); ?>
								<button type="submit" class="button-link wpddb-checklist__danger"><?php esc_html_e( 'Remove Sample Data', 'doc-booker' ); ?></button>
							</form>
						<?php elseif ( ! $item[2] && $item[3] ) : ?>
							— <a href="<?php echo esc_url( $item[3] ); ?>"<?php echo 'booking' === $item[0] ? ' target="_blank" rel="noopener"' : ''; ?>><?php echo esc_html( $item[4] ); ?></a>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="wpddb-checklist__foot">
				<a href="<?php echo esc_url( $hide ); ?>"><?php esc_html_e( 'Hide This', 'doc-booker' ); ?></a>
				<?php if ( ! empty( $state['completed'] ) ) : ?>
					· <a href="<?php echo esc_url( SetupWizard::url() ); ?>"><?php esc_html_e( 'Run the Setup Again', 'doc-booker' ); ?></a>
				<?php endif; ?>
			</p>
		</div>
		<style>
			.wpddb-checklist{--p:#635bff;background:#fff;border:1px solid #eef0f4;border-radius:16px;box-shadow:0 1px 2px rgba(15,23,42,.04),0 8px 24px rgba(99,91,255,.06);padding:20px 24px;margin:16px 0 20px;max-width:960px;color:#0a2540;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
			.wpddb-checklist__head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap}
			.wpddb-checklist h2{margin:0 0 2px;font-size:16px;font-weight:700;padding:0}
			.wpddb-checklist p{margin:0;color:#64748b;font-size:13px}
			.wpddb-checklist__btn{background:linear-gradient(135deg,#635bff,#9333ea);color:#fff!important;text-decoration:none;border-radius:10px;padding:9px 18px;font-weight:600;box-shadow:0 4px 14px rgba(99,91,255,.4);white-space:nowrap}
			.wpddb-checklist__bar{height:6px;background:#eef0f4;border-radius:999px;margin:14px 0}
			.wpddb-checklist__bar i{display:block;height:100%;background:linear-gradient(135deg,#635bff,#9333ea);border-radius:999px}
			.wpddb-checklist ul{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:1fr 1fr;gap:8px 28px;font-size:14px}
			.wpddb-checklist li{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin:0}
			.wpddb-checklist li.is-done{color:#64748b}
			.wpddb-checklist__mark{width:18px;height:18px;border-radius:50%;border:2px solid #d6e0eb;display:inline-grid;place-items:center;font-size:11px;flex:none}
			.wpddb-checklist li.is-done .wpddb-checklist__mark{background:#16a34a;border-color:#16a34a;color:#fff}
			.wpddb-checklist a{color:var(--p)}
			.wpddb-checklist form{display:inline;margin:0}
			.wpddb-checklist .wpddb-checklist__danger{color:#b91c1c;text-decoration:underline}
			.wpddb-checklist__foot{margin-top:14px!important}
			@media (max-width:782px){.wpddb-checklist ul{grid-template-columns:1fr}.wpddb-checklist{padding:16px}}
		</style>
		<?php
	}

	public function handle_hide() {
		if ( empty( $_GET['wpddb_checklist'] ) || 'hide' !== $_GET['wpddb_checklist'] || empty( $_GET['_wpnonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), self::NONCE ) ) {
			return;
		}
		update_option( self::HIDDEN, 1, false );
		wp_safe_redirect( remove_query_arg( [ 'wpddb_checklist', '_wpnonce' ] ) );
		exit;
	}

	public function handle_remove_samples() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'doc-booker' ), 403 );
		}
		check_admin_referer( self::NONCE );
		SetupInstaller::remove_samples();
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . wpddb()->post_type_doctor . '&wpddb_samples_removed=1' ) );
		exit;
	}
}
