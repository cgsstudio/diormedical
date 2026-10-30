<?php
/**
 * Printable visit (IPD/OPD) bill. Listens for `?wpddb_visit_bill={id}&t={token}`
 * on any front-end URL, validates the HMAC, and renders a print-optimised HTML
 * invoice: itemised charges + every dated payment + total billable / paid /
 * balance due.
 *
 * Shows an INTERIM STATEMENT while the patient is still admitted, and a FINAL
 * DISCHARGE BILL once the visit is released. No PDF dependency — the page
 * auto-fires window.print().
 */

namespace WpDreamers\WPDDBP\Controllers\Visits;

use WpDreamers\WPDDBP\Controllers\Receptionist\Model\Patient;
use WpDreamers\WPDDBP\Controllers\Receptionist\RestApi as ReceptionRestApi;
use WpDreamers\WPDDBP\Controllers\Visits\Model\Visit;
use WpDreamers\WPDDBP\Controllers\Visits\Model\VisitItem;
use WpDreamers\WPDDBP\Controllers\Visits\Model\VisitPayment;
use WpDreamers\WPDDBP\Traits\SingletonTrait;

defined( 'ABSPATH' ) || exit;

class BillPage {
	use SingletonTrait;

	public function __construct() {
		add_action( 'init', [ $this, 'register_query_var' ] );
		add_action( 'template_redirect', [ $this, 'maybe_render' ] );
	}

	public function register_query_var() {
		global $wp;
		$wp->add_query_var( 'wpddb_visit_bill' );
	}

	public function maybe_render() {
		$id = isset( $_GET['wpddb_visit_bill'] ) ? (int) $_GET['wpddb_visit_bill'] : 0;
		if ( ! $id ) return;
		$token = isset( $_GET['t'] ) ? sanitize_text_field( (string) $_GET['t'] ) : '';
		if ( ! hash_equals( ReceptionRestApi::build_visit_bill_token( $id ), $token ) ) {
			status_header( 403 );
			exit( 'Invalid bill token.' );
		}
		$visit = Visit::find( $id );
		if ( ! $visit ) {
			status_header( 404 );
			exit( 'Visit not found.' );
		}
		$this->render( $visit );
		exit;
	}

	private function line_total( $it ) {
		$qty   = max( 1, (int) ( $it['qty'] ?? 1 ) );
		$price = (float) ( $it['unit_price'] ?? 0 );
		$disc  = (float) ( $it['disc_percent'] ?? 0 );
		return round( $qty * $price * ( 1 - $disc / 100 ), 2 );
	}

	private function render( $visit ) {
		$id       = (int) $visit['id'];
		$items    = VisitItem::list_for_visit( $id );
		$payments = VisitPayment::list_for_visit( $id );
		$patient  = Patient::enrich( Patient::find( (int) $visit['patient_id'] ) );
		$clinic   = get_post( (int) $visit['clinic_id'] );
		$clinic_address = $clinic ? get_post_meta( $clinic->ID, 'wpddb_clinic_address', true ) : '';
		$clinic_hotline = $clinic ? get_post_meta( $clinic->ID, 'wpddb_clinic_hotline', true ) : '';

		$is_ipd   = ( $visit['visit_type'] ?? 'opd' ) === 'ipd';
		$released = ( $visit['status'] ?? '' ) === 'released';
		$total    = (float) $visit['total_charge'];
		$received = (float) $visit['received'];
		$due      = max( 0, $total - $received );
		$credit   = max( 0, round( $received - $total, 2 ) );

		// Relevant doctor: admitting for IPD, consulting for OPD.
		$doctor_id = $is_ipd
			? (int) ( $visit['admit_doctor_id'] ?: $visit['consult_doctor_id'] )
			: (int) ( $visit['consult_doctor_id'] ?: $visit['admit_doctor_id'] );
		$doctor    = $doctor_id ? get_the_title( $doctor_id ) : '';

		// Stay length (IPD): admitted_at|visit_date → released_at|now.
		$admit_raw = $visit['admitted_at'] ?: $visit['visit_date'];
		$end_raw   = $visit['released_at'] ?: current_time( 'mysql' );
		$days      = '';
		if ( $is_ipd && $admit_raw ) {
			$a = strtotime( $admit_raw );
			$b = strtotime( $end_raw );
			if ( $a && $b ) {
				$days = max( 1, (int) ceil( ( $b - $a ) / DAY_IN_SECONDS ) );
			}
		}

		// An outpatient visit is billed in one go; only an admission has an
		// interim statement while the patient is still in.
		if ( ! $is_ipd ) {
			$doc_title = __( 'Bill / Invoice', 'doc-booker-pro' );
		} else {
			$doc_title = $released
				? __( 'Discharge Bill / Final Invoice', 'doc-booker-pro' )
				: __( 'Interim Statement', 'doc-booker-pro' );
		}

		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		?>
<!doctype html>
<html lang="<?php echo esc_attr( get_locale() ); ?>">
<head>
<meta charset="utf-8">
<title><?php echo esc_html( sprintf( __( 'Bill — Visit #%d', 'doc-booker-pro' ), $id ) ); ?></title>
<link rel="stylesheet" href="<?php echo esc_url( wpddbp()->get_assets_uri( 'public/css/wpd-receipt.css' ) ); ?>?v=<?php echo esc_attr( WPDDBP_VERSION ); ?>">
</head>
<body class="wpddb-receipt-body">
<div class="wpddb-receipt">
	<header class="r-head">
		<div class="r-brand">
			<h1><?php echo esc_html( $clinic ? $clinic->post_title : get_bloginfo( 'name' ) ); ?></h1>
			<?php if ( $clinic_address ) : ?><p class="r-addr"><?php echo esc_html( $clinic_address ); ?></p><?php endif; ?>
			<?php if ( $clinic_hotline ) : ?><p class="r-addr"><?php esc_html_e( 'Hotline:', 'doc-booker-pro' ); ?> <?php echo esc_html( $clinic_hotline ); ?></p><?php endif; ?>
		</div>
	</header>

	<p class="r-doc-title"><?php echo esc_html( $doc_title ); ?> &middot; <?php echo esc_html( strtoupper( $visit['visit_type'] ?? '' ) ); ?></p>

	<section class="r-meta">
		<div><strong><?php esc_html_e( 'ID No.', 'doc-booker-pro' ); ?></strong> <?php echo esc_html( $patient['patient_code'] ?? '' ); ?></div>
		<div><strong><?php esc_html_e( 'Name', 'doc-booker-pro' ); ?></strong> <?php echo esc_html( $patient['full_name'] ?? '' ); ?></div>
		<div><strong><?php esc_html_e( 'Age/Sex', 'doc-booker-pro' ); ?></strong> <?php echo esc_html( ( isset( $patient['age'] ) ? sprintf( /* translators: %d: patient age in years, shortened (e.g. 42y) */ __( '%dy', 'doc-booker-pro' ), (int) $patient['age'] ) : '—' ) . ', ' . ucfirst( $patient['gender'] ?? '—' ) ); ?></div>
		<div><strong><?php esc_html_e( 'Phone', 'doc-booker-pro' ); ?></strong> <?php echo esc_html( $patient['phone'] ?? '' ); ?></div>
		<?php if ( $doctor ) : ?><div><strong><?php esc_html_e( 'Doctor', 'doc-booker-pro' ); ?></strong> <?php echo esc_html( $doctor ); ?></div><?php endif; ?>
		<?php if ( ! empty( $visit['bed_no'] ) ) : ?><div><strong><?php esc_html_e( 'Bed', 'doc-booker-pro' ); ?></strong> <?php echo esc_html( $visit['bed_no'] ); ?></div><?php endif; ?>
		<?php if ( $is_ipd ) : ?>
			<div><strong><?php esc_html_e( 'Admitted', 'doc-booker-pro' ); ?></strong> <?php echo esc_html( mysql2date( 'j M y', $admit_raw ) ); ?></div>
		<?php else : ?>
			<div><strong><?php esc_html_e( 'Visit date', 'doc-booker-pro' ); ?></strong> <?php echo esc_html( mysql2date( 'j M y', $visit['visit_date'] ?: $admit_raw ) ); ?></div>
		<?php endif; ?>
		<?php if ( $is_ipd && $released && ! empty( $visit['released_at'] ) ) : ?>
			<div><strong><?php esc_html_e( 'Released', 'doc-booker-pro' ); ?></strong> <?php echo esc_html( mysql2date( 'j M y', $visit['released_at'] ) ); ?></div>
		<?php endif; ?>
		<?php if ( $is_ipd && $days ) : ?><div><strong><?php esc_html_e( 'Days', 'doc-booker-pro' ); ?></strong> <?php echo (int) $days; ?></div><?php endif; ?>
		<div><strong><?php esc_html_e( 'Bill #', 'doc-booker-pro' ); ?></strong> V<?php echo (int) $id; ?></div>
	</section>

	<h3 class="r-sub"><?php esc_html_e( 'Charges', 'doc-booker-pro' ); ?></h3>
	<table class="r-items">
		<thead>
			<tr><th><?php esc_html_e( 'SL', 'doc-booker-pro' ); ?></th><th><?php esc_html_e( 'Type', 'doc-booker-pro' ); ?></th><th><?php esc_html_e( 'Item', 'doc-booker-pro' ); ?></th><th><?php esc_html_e( 'Qty', 'doc-booker-pro' ); ?></th><th><?php esc_html_e( 'Rate', 'doc-booker-pro' ); ?></th><th><?php esc_html_e( 'Disc%', 'doc-booker-pro' ); ?></th><th class="ta-r"><?php esc_html_e( 'Amount', 'doc-booker-pro' ); ?></th></tr>
		</thead>
		<tbody>
			<?php if ( ! $items ) : ?>
				<tr><td colspan="7" class="r-empty"><?php esc_html_e( 'No charges yet.', 'doc-booker-pro' ); ?></td></tr>
			<?php endif; $i = 1; foreach ( $items as $it ) : ?>
				<tr>
					<td><?php echo (int) $i++; ?></td>
					<td><?php echo esc_html( ucfirst( $it['item_type'] ) ); ?></td>
					<td><?php echo esc_html( $it['name'] ); ?></td>
					<td><?php echo (int) $it['qty']; ?></td>
					<td><?php echo number_format( (float) $it['unit_price'], 2 ); ?></td>
					<td><?php echo number_format( (float) $it['disc_percent'], 0 ); ?></td>
					<td class="ta-r"><?php echo number_format( $this->line_total( $it ), 2 ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<h3 class="r-sub"><?php esc_html_e( 'Payments', 'doc-booker-pro' ); ?></h3>
	<table class="r-items">
		<thead>
			<tr><th><?php esc_html_e( 'Date', 'doc-booker-pro' ); ?></th><th><?php esc_html_e( 'Method', 'doc-booker-pro' ); ?></th><th><?php esc_html_e( 'Ref', 'doc-booker-pro' ); ?></th><th class="ta-r"><?php esc_html_e( 'Amount', 'doc-booker-pro' ); ?></th></tr>
		</thead>
		<tbody>
			<?php if ( ! $payments ) : ?>
				<tr><td colspan="4" class="r-empty"><?php esc_html_e( 'No payments yet.', 'doc-booker-pro' ); ?></td></tr>
			<?php endif; foreach ( $payments as $p ) : ?>
				<tr>
					<td><?php echo esc_html( mysql2date( 'j M y g:iA', $p['received_at'] ) ); ?></td>
					<td><?php echo esc_html( ucfirst( $p['method'] ) ); ?></td>
					<td><?php echo esc_html( $p['reference'] ?? '' ); ?></td>
					<td class="ta-r"><?php echo number_format( (float) $p['amount'], 2 ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<section class="r-totals">
		<div class="r-totals-inner">
			<div><span><?php esc_html_e( 'Total billable', 'doc-booker-pro' ); ?></span><strong><?php echo number_format( $total, 2 ); ?></strong></div>
			<div><span><?php esc_html_e( 'Total paid', 'doc-booker-pro' ); ?></span><strong><?php echo number_format( $received, 2 ); ?></strong></div>
			<div class="r-total-row"><span><?php echo $due > 0 ? esc_html__( 'Balance due', 'doc-booker-pro' ) : esc_html__( 'Balance', 'doc-booker-pro' ); ?></span><strong><?php echo number_format( $due, 2 ); ?></strong></div>
			<?php if ( $credit > 0 ) : ?>
				<div class="r-total-row"><span><?php echo ( $is_ipd && ! $released ) ? esc_html__( 'Advance paid', 'doc-booker-pro' ) : esc_html__( 'Credit (to refund)', 'doc-booker-pro' ); ?></span><strong><?php echo number_format( $credit, 2 ); ?></strong></div>
			<?php endif; ?>
		</div>
		<?php if ( $due <= 0 && $received > 0 ) : ?>
			<div class="r-paid-stamp"><?php esc_html_e( 'PAID', 'doc-booker-pro' ); ?></div>
		<?php endif; ?>
	</section>

	<?php if ( $is_ipd && ! $released ) : ?>
		<p class="r-footer-line"><?php esc_html_e( 'Interim statement — the patient is still admitted; charges may continue to accrue until discharge.', 'doc-booker-pro' ); ?></p>
	<?php endif; ?>
</div>
<script>window.addEventListener('load', function(){ setTimeout(function(){ window.print(); }, 250); });</script>
</body>
</html>
		<?php
	}
}
