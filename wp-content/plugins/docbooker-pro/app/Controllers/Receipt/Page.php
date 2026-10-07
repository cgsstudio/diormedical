<?php
/**
 * Receipt print page. Listens for `?wpddb_receipt={code}&t={token}` on any
 * front-end URL, validates the HMAC, and renders a print-optimized HTML
 * snapshot of the order with a QR pointing at the patient portal.
 *
 * No PDF dependency — the page auto-fires window.print() and lets the browser
 * save-as-PDF if needed.
 */

namespace WpDreamers\WPDDBP\Controllers\Receipt;

use WpDreamers\WPDDBP\Controllers\Receptionist\Model\Patient;
use WpDreamers\WPDDBP\Controllers\Receptionist\RestApi as ReceptionRestApi;
use WpDreamers\WPDDBP\Controllers\TestOrder\Model\TestOrder;
use WpDreamers\WPDDBP\Controllers\TestOrder\Model\TestOrderItem;
use WpDreamers\WPDDBP\Controllers\PatientPortal\Page as PortalPage;
use WpDreamers\WPDDBP\Traits\SingletonTrait;

defined( 'ABSPATH' ) || exit;

class Page {
	use SingletonTrait;

	public function __construct() {
		add_action( 'init', [ $this, 'register_query_var' ] );
		add_action( 'template_redirect', [ $this, 'maybe_render' ] );
	}

	public function register_query_var() {
		global $wp;
		$wp->add_query_var( 'wpddb_receipt' );
	}

	public function maybe_render() {
		$code = isset( $_GET['wpddb_receipt'] ) ? sanitize_text_field( (string) $_GET['wpddb_receipt'] ) : '';
		if ( ! $code ) return;
		$token = isset( $_GET['t'] ) ? sanitize_text_field( (string) $_GET['t'] ) : '';
		if ( ! hash_equals( ReceptionRestApi::build_receipt_token( $code ), $token ) ) {
			status_header( 403 );
			exit( 'Invalid receipt token.' );
		}
		$order = TestOrder::find_by_code( $code );
		if ( ! $order ) {
			status_header( 404 );
			exit( 'Receipt not found.' );
		}
		$this->render( $order );
		exit;
	}

	private function render( $order ) {
		$items   = TestOrderItem::list_for_order( (int) $order['id'] );
		$patient = Patient::enrich( Patient::find( (int) $order['patient_id'] ) );
		$totals  = TestOrder::compute_totals( $order );
		$clinic  = get_post( (int) $order['clinic_id'] );
		$clinic_address = $clinic ? get_post_meta( $clinic->ID, 'wpddb_clinic_address', true ) : '';
		$clinic_hotline = $clinic ? get_post_meta( $clinic->ID, 'wpddb_clinic_hotline', true ) : '';

		$portal_page_id = PortalPage::get_page_id();
		$portal_url     = $portal_page_id ? add_query_arg( [ 'ref' => $order['order_code'] ], get_permalink( $portal_page_id ) ) : '';

		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		?>
<!doctype html>
<html lang="<?php echo esc_attr( get_locale() ); ?>">
<head>
<meta charset="utf-8">
<title><?php echo esc_html( sprintf( __( 'Receipt %s', 'doc-booker-pro' ), $order['order_code'] ) ); ?></title>
<link rel="stylesheet" href="<?php echo esc_url( wpddbp()->get_assets_uri( 'public/css/wpd-receipt.css' ) ); ?>?v=<?php echo esc_attr( WPDDBP_VERSION ); ?>">
</head>
<body class="wpddb-receipt-body">
<div class="wpddb-receipt">
	<header class="r-head">
		<div class="r-brand">
			<h1><?php echo esc_html( $clinic ? $clinic->post_title : get_bloginfo( 'name' ) ); ?></h1>
			<?php if ( $clinic_address ) : ?><p class="r-addr"><?php echo esc_html( $clinic_address ); ?></p><?php endif; ?>
			<?php if ( $clinic_hotline ) : ?><p class="r-addr">Hotline: <?php echo esc_html( $clinic_hotline ); ?></p><?php endif; ?>
		</div>
	</header>

	<section class="r-meta">
		<div><strong>ID No.</strong> <?php echo esc_html( $patient['patient_code'] ?? '' ); ?></div>
		<div><strong>Name</strong> <?php echo esc_html( $patient['full_name'] ?? '' ); ?></div>
		<div><strong>Age</strong> <?php echo isset( $patient['age'] ) ? esc_html( (string) $patient['age'] . ' year' ) : '—'; ?></div>
		<div><strong>Sex</strong> <?php echo esc_html( ucfirst( $patient['gender'] ?? '—' ) ); ?>, M: <?php echo esc_html( $patient['phone'] ?? '' ); ?></div>
		<?php if ( ! empty( $order['refd_by'] ) ) : ?><div><strong>Refd by</strong> <?php echo esc_html( $order['refd_by'] ); ?></div><?php endif; ?>
		<div><strong>Order</strong> <?php echo esc_html( $order['order_code'] ); ?></div>
		<div><strong>Date</strong> <?php echo esc_html( mysql2date( 'j M y g:iA', $order['created_at'] ) ); ?></div>
	</section>

	<table class="r-items">
		<thead>
			<tr><th>SL</th><th>Test/Service</th><th>Qty</th><th>Amount</th><th>Disc %</th><th>Delivery</th></tr>
		</thead>
		<tbody>
			<?php $i=1; foreach ( $items as $it ): $line_total = TestOrderItem::line_total( $it ); ?>
				<tr>
					<td><?php echo (int) $i++; ?></td>
					<td><?php echo esc_html( $it['name_snapshot'] ); ?></td>
					<td><?php echo (int) $it['qty']; ?></td>
					<td><?php echo number_format( (float) $it['price_snapshot'] * (int) $it['qty'], 0 ); ?></td>
					<td><?php echo number_format( (float) $it['disc_percent'], 0 ); ?></td>
					<td><?php echo $order['delivery_at'] ? esc_html( mysql2date( 'j/n/y g:iA', $order['delivery_at'] ) ) : ''; ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<section class="r-totals">
		<div class="r-totals-inner">
			<div><span>Total amount</span><strong><?php echo number_format( (float) $order['subtotal'], 2 ); ?></strong></div>
			<div><span>Discount</span><strong><?php echo number_format( (float) $order['discount_amount'], 2 ); ?></strong></div>
			<div><span>Extra discount</span><strong><?php echo number_format( (float) $order['extra_discount'], 2 ); ?></strong></div>
			<div class="r-total-row"><span>Payable</span><strong><?php echo number_format( (float) $totals['payable'], 2 ); ?></strong></div>
			<div><span>Received</span><strong><?php echo number_format( (float) $order['received'], 2 ); ?></strong></div>
			<div class="r-total-row"><span>Due</span><strong><?php echo number_format( (float) $totals['due'], 2 ); ?></strong></div>
		</div>
		<?php if ( $order['payment_status'] === 'paid' ): ?>
			<div class="r-paid-stamp">PAID</div>
		<?php endif; ?>
		<?php if ( $portal_url ): ?>
			<div class="r-qr">
				<img alt="QR" src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=<?php echo rawurlencode( $portal_url ); ?>">
				<p>Scan for online report</p>
			</div>
		<?php endif; ?>
	</section>

	<?php if ( $portal_url ): ?>
		<p class="r-footer-line">For online report visit here: <?php echo esc_html( $portal_url ); ?></p>
	<?php endif; ?>
</div>
<script>window.addEventListener('load', function(){ setTimeout(function(){ window.print(); }, 250); });</script>
</body>
</html>
		<?php
	}
}
