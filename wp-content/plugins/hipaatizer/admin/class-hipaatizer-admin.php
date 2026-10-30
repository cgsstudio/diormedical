<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    HIPAAtizer
 * @subpackage HIPAAtizer/admin
 * @author     HIPAAtizer
 */
class HIPAAtizer_Admin {
	private $hipaatizer;
	private $version;

	public function __construct( $hipaatizer, $version ) {

		$this->hipaatizer = $hipaatizer;
		$this->version = $version;
        add_action( 'admin_menu', array( $this, 'hipaa_admin_menu' )  );
		add_action( 'init', array( $this, 'hipaatizer_id' )  );
		add_action( 'init', array( $this, 'hipaa_whiteLabet' )  );
		add_action( 'admin_init', array( $this, 'hipaa_transfer_cf7_uregistered' )  );
		add_action( 'admin_init', array( $this, 'hipaa_transfer_wpf_uregistered' )  );
		add_action( 'admin_init', array( $this, 'hipaa_transfer_gf_uregistered' )  );
		add_filter( 'script_loader_tag', array( $this,'hipaa_script_tags'), 10, 2);
		add_action( 'wp_ajax_refresh_hipaa_forms',  array( $this, 'hipaa_refresh_hipaa_forms' ) );
		add_action( 'wp_ajax_tabs_hipaa_forms',  array( $this, 'hipaa_tabs_hipaa_forms' ) );
	}

    public function hipaatizer_id() {
		global $wpdb, $hipaaID, $hipaa_message, $cf7key, $message, $site_id;

		$site_id  = ( is_multisite() ) ? get_current_blog_id() : '';
		$dbprefix = is_multisite() ? $wpdb->get_blog_prefix( get_current_blog_id() ) : $wpdb->prefix;

		// Always read $hipaaID — needed by the public shortcode on frontend requests too.
		if( !empty( $site_id )):
		$hipaaID = $wpdb->get_var( $wpdb->prepare( "SELECT hipaatizer_id FROM `{$dbprefix}hipaatizer` WHERE site_id=%s", $site_id) );
		$rowID   = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$dbprefix}hipaatizer` WHERE site_id=%s", $site_id) );

		else:
			$hipaaID = $wpdb->get_var(
				$wpdb->prepare("SELECT hipaatizer_id FROM {$dbprefix}hipaatizer WHERE 1 = %s", 1)
			);			$rowID = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM `{$dbprefix}hipaatizer` WHERE 1 = %d",
					1
				)
			);		endif;

		// Write operations (OAuth, cookie link, logout) are admin-only.
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$cf7key = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(function_exists('random_bytes') ? random_bytes(16) : openssl_random_pseudo_bytes(16)), 4));

		// Handle ?accountId= redirect back from cloud app after login/account-switch.
		// Protected by a short-lived transient set when the user was sent to the cloud login
		// page, so a crafted URL sent to an admin cannot link an arbitrary account.
		if ( ! empty( $_GET['accountId'] ) ) {
			$pending_key = 'hipaatizer_auth_pending_' . get_current_user_id();
			$clean_path  = admin_url( 'admin.php' ) . '?page=hipaatizer';
			if ( get_transient( $pending_key ) ) {
				delete_transient( $pending_key );
				$new_hipaaID = sanitize_key( wp_unslash( $_GET['accountId'] ) );
				if ( ! empty( $new_hipaaID ) ) {
					if ( ! empty( $hipaaID ) ) {
						if ( ! empty( $site_id ) ) :
							$wpdb->query( $wpdb->prepare( "UPDATE `{$dbprefix}hipaatizer` SET hipaatizer_id = %s WHERE id = %s AND site_id = %s", $new_hipaaID, $rowID, $site_id ) );
						else :
							$wpdb->query( $wpdb->prepare( "UPDATE `{$dbprefix}hipaatizer` SET hipaatizer_id = %s WHERE id = %s", $new_hipaaID, $rowID ) );
						endif;
					} else {
						if ( ! empty( $site_id ) ) :
							$wpdb->query( $wpdb->prepare( "INSERT INTO `{$dbprefix}hipaatizer` (hipaatizer_id, site_id) VALUES (%s, %s)", $new_hipaaID, $site_id ) );
						else :
							$wpdb->query( $wpdb->prepare( "INSERT INTO `{$dbprefix}hipaatizer` (hipaatizer_id) VALUES (%s)", $new_hipaaID ) );
						endif;
					}
					delete_transient( 'hipaatizer_public_info_' . get_current_blog_id() . '_' . $new_hipaaID );
					delete_transient( 'hipaatizer_workflows_' . get_current_blog_id() . '_' . $new_hipaaID );
				}
			}
			// Always redirect to a clean URL — removes accountId from the address bar
			// regardless of whether the transient was valid.
			wp_safe_redirect( esc_url( $clean_path ) );
			exit;
		}

			if( !empty($_GET['code']) ){
				// Authorize the code exchange one of two ways:
				//  (a) Manual activation-code form — carries a wp_nonce (robust on cached
				//      hosts; no cross-request server state needed), OR
				//  (b) OAuth redirect back from the cloud login — carries no nonce, so we
				//      fall back to the short-lived pending transient set when the admin
				//      was sent to the cloud login page.
				// Either path prevents an attacker-supplied ?code= from being accepted.
				$activate_nonce = isset( $_GET['hipaa_activate_nonce'] )
					? sanitize_text_field( wp_unslash( $_GET['hipaa_activate_nonce'] ) )
					: '';
				$nonce_ok    = $activate_nonce && wp_verify_nonce( $activate_nonce, 'hipaa_activate_account' );
				$pending_key = 'hipaatizer_auth_pending_' . get_current_user_id();
				$pending_ok  = (bool) get_transient( $pending_key );

				if ( ! $nonce_ok && ! $pending_ok ) {
					wp_safe_redirect( esc_url( admin_url( 'admin.php' ) . '?page=hipaatizer' ) );
					exit;
				}
				if ( $pending_ok ) {
					delete_transient( $pending_key );
				}

				// Make sure this blog's table exists before we try to write to it.
				// On multisite, a subsite whose table creation never ran (or failed)
				// would otherwise silently drop the INSERT and the account would
				// never persist — looping the admin back to the activation screen.
				require_once plugin_dir_path( HIPAATIZER_BASE_PATH ) . 'includes/class-hipaatizer-activator.php';
				HIPAAtizer_Activator::create_table_for_blog( $site_id ? get_current_blog_id() : null, false );

				$code = sanitize_text_field( wp_unslash( $_GET['code'] ) );
				if( !empty($_GET['cf7']) ){

					$this->hipaa_transfer_cf7();

					if( $message == "Successful operation."){
						$curl =  HIPAATIZER_APP.'/api/v1/account/activate?code='.$code.'&contactForm7Id='.$cf7key;
					} else {
						$curl =  HIPAATIZER_APP.'/api/v1/account/activate?code='.$code;
					}

				} else {
					$curl =  HIPAATIZER_APP.'/api/v1/account/activate?code='.$code;
				}
				$response      = wp_remote_get( $curl, array( "timeout" => 15 ) );
				$body          = wp_remote_retrieve_body( $response );
				$res      	   = json_decode($body, true);
				$hipaa_message = ( !empty($res['message']) ) ? $res['message'] : '';

				if( $hipaa_message == '' ){
					$new_hipaaID  = sanitize_key($body);
					if( !empty($new_hipaaID)){

						if (isset($hipaaID)){
							if( !empty($site_id)) :
							$wpdb->query( $wpdb->prepare( "UPDATE `{$dbprefix}hipaatizer` SET hipaatizer_id = %s WHERE id = %s  AND site_id = %s", $new_hipaaID, $rowID, $site_id )  );
							else:
							$wpdb->query( $wpdb->prepare( "UPDATE `{$dbprefix}hipaatizer` SET hipaatizer_id = %s WHERE id = %s", $new_hipaaID, $rowID )  );
							endif;
						} else {
							if( !empty($site_id)) :
								$wpdb->query( $wpdb->prepare( "INSERT INTO `{$dbprefix}hipaatizer`  (hipaatizer_id, site_id)  VALUES ( %s, %s)", $new_hipaaID, $site_id )  );
							else:
								$wpdb->query( $wpdb->prepare( "INSERT INTO `{$dbprefix}hipaatizer`  (hipaatizer_id)  VALUES ( %s )", $new_hipaaID )  );
							endif;
						}
						delete_transient( 'hipaatizer_public_info_' . get_current_blog_id() . '_' . $new_hipaaID );
						delete_transient( 'hipaatizer_workflows_' . get_current_blog_id() . '_' . $new_hipaaID );
						unset($_COOKIE['hipaaID']);
						setcookie('hipaaID', '', time() - 3600);
						$path = admin_url( 'admin.php' ).'?page=hipaatizer';
						wp_safe_redirect( esc_url($path) );
						exit;
					}

			}
		}
		if( !empty($_COOKIE['hipaaID'])){
			// Verify the per-user nonce set by JS alongside the cookie to prevent
			// CSRF / URL-parameter account-takeover.
			$activate_nonce = isset( $_COOKIE['hipaaActivateNonce'] )
				? sanitize_text_field( wp_unslash( $_COOKIE['hipaaActivateNonce'] ) )
				: '';
			if ( ! wp_verify_nonce( $activate_nonce, 'hipaa_activate_account' ) ) {
				setcookie( 'hipaaID', '', time() - 3600, '/' );
				setcookie( 'hipaaActivateNonce', '', time() - 3600, '/' );
				return;
			}
			$new_hipaaID = sanitize_key($_COOKIE['hipaaID']);

			if( !empty($hipaaID)){
				if( !empty($site_id)) :
				$wpdb->query( $wpdb->prepare( "UPDATE `{$dbprefix}hipaatizer` SET hipaatizer_id = %s WHERE id = %s  AND site_id = %s", $new_hipaaID, $rowID, $site_id )  );
				else:
				$wpdb->query( $wpdb->prepare( "UPDATE `{$dbprefix}hipaatizer` SET hipaatizer_id = %s WHERE id = %s", $new_hipaaID, $rowID )  );
				endif;
			} else {
				if( !empty($site_id)) :
					$wpdb->query( $wpdb->prepare( "INSERT INTO `{$dbprefix}hipaatizer`  (hipaatizer_id, site_id)  VALUES ( %s, %s)", $new_hipaaID, $site_id )  );
				else:
					$wpdb->query( $wpdb->prepare( "INSERT INTO `{$dbprefix}hipaatizer`  (hipaatizer_id)  VALUES ( %s )", $new_hipaaID )  );
				endif;
			}
	        delete_transient( 'hipaatizer_public_info_' . get_current_blog_id() . '_' . $new_hipaaID );
			delete_transient( 'hipaatizer_workflows_' . get_current_blog_id() . '_' . $new_hipaaID );
			unset($_COOKIE['hipaaID']);
			setcookie( 'hipaaID', '', time() - 3600, '/' );
			setcookie( 'hipaaActivateNonce', '', time() - 3600, '/' );
			$path = admin_url( 'admin.php' ).'?page=hipaatizer';
			wp_safe_redirect( esc_url($path) );
            exit;
		}


		if( isset($_GET['hipaa_logout']) && $_GET['hipaa_logout'] == 1){
			if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
				return;
			}

			if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'hipaa_logout' ) ) {
				return;
			}			
			unset($_COOKIE['hipaaID']);
			if ( ! empty( $hipaaID ) ) {
				delete_transient( 'hipaatizer_public_info_' . get_current_blog_id() . '_' . $hipaaID );
				delete_transient( 'hipaatizer_workflows_' . get_current_blog_id() . '_' . $hipaaID );
			}
			setcookie('hipaaID', '', time() - 3600);
			if( !empty($site_id)) :
			$wpdb->query( $wpdb->prepare( "DELETE FROM `{$dbprefix}hipaatizer` WHERE  hipaatizer_id=%s AND site_id=%s", $hipaaID, $site_id  )  );
			else:
			$wpdb->query( $wpdb->prepare( "DELETE FROM `{$dbprefix}hipaatizer` WHERE  hipaatizer_id=%s", $hipaaID )  );
			endif;
		}
	}
	public function enqueue_styles() {

		wp_enqueue_style( $this->hipaatizer, plugin_dir_url( __FILE__ ) . 'css/hipaatizer-admin.css', array(), $this->version, 'all' );

	}


	public function enqueue_scripts() {
		// Subscriber-role users can reach admin_enqueue_scripts when visiting
		// their own profile. Bail early for anyone without manage_options so
		// downstream side effects (transient set, CF7 transfer, etc.) only run
		// for site administrators.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		global $wpdb, $hipaaID, $cf7key, $message, $site_id;
		$iframe = '';
		$screen = get_current_screen();
		$dbprefix = is_multisite() ? $wpdb->get_blog_prefix( get_current_blog_id() ) : $wpdb->prefix;
		$login = '/login';

		if( !empty($site_id ) && !isset($hipaaID) ) {
			$siteID = $wpdb->get_var( 
				$wpdb->prepare(
					"SELECT site_id FROM `{$dbprefix}hipaatizer` WHERE 1 = %d", 
					1 
				) 
			);			if( $site_id != $siteID ) {
				$login = '/login/logout-callback';

			}
		}

		if( !empty($_GET['hipaa_account']) && $_GET['hipaa_account'] == 'signup'  ) {


			if( !empty($_GET['cf7']) ){

				$this->hipaa_transfer_cf7();

				if( $message == "Successful operation."){
					$iframe = HIPAATIZER_APP.'/sign-up/register?utm_source=wppl&source='.get_site_url().'&contactForm7Id='.$cf7key;

				} else {
					$iframe = HIPAATIZER_APP.'/sign-up/register?utm_source=wppl&source='.get_site_url();

				}

			} else {
				$iframe = HIPAATIZER_APP.'/sign-up/register?utm_source=wppl&source='.get_site_url();

			}

		} elseif( !empty($_GET['hipaa_account']) && $_GET['hipaa_account'] == 'login' ) {
			if( !empty($_GET['cf7']) ){
				$this->hipaa_transfer_cf7();

				if( $message == "Successful operation."){
					$iframe = HIPAATIZER_APP.$login.'?utm_source=wppl&source='.get_site_url().'&contactForm7Id='.$cf7key.'&ignoreAuthed=true';
				} else {
					$iframe = HIPAATIZER_APP.$login.'?utm_source=wppl&source='.get_site_url().'&ignoreAuthed=true';
				}
			}  else {
				$iframe = HIPAATIZER_APP.$login.'?utm_source=wppl&source='.get_site_url().'&ignoreAuthed=true';
			}
		}
		// Set a short-lived transient whenever the user is about to send a ?code=
		// back to this plugin — covers both the cloud login/signup iframe flows and
		// the manual activation code form (which submits ?code= directly).
		$showing_activation_form = ! empty( $_GET['hipaa_account'] ) && $_GET['hipaa_account'] === 'activation_code';
		if ( ! empty( $iframe ) || $showing_activation_form ) {
			set_transient( 'hipaatizer_auth_pending_' . get_current_user_id(), 1, 10 * MINUTE_IN_SECONDS );
		}

		$urlc = HIPAATIZER_APP.'/workflow/';
		if($screen->id == 'toplevel_page_hipaatizer'){
		wp_enqueue_script( $this->hipaatizer, plugin_dir_url( __FILE__ ) . 'js/hipaatizer-admin.js', array( 'jquery' ), $this->version, false );

		if( !isset($_GET['hipaa_account'])) {
		wp_enqueue_script( 'freshworks', 'https://widget.freshworks.com/widgets/72000002319.js', array( 'jquery' ), $this->version, false );

		}
		}

		$allowed_origin = ! empty( $whiteLabelUrl ) ? rtrim( $whiteLabelUrl, '/' ) : HIPAATIZER_APP;
		wp_localize_script( $this->hipaatizer, 'hipaa_params', array(
		'ajax_url'       => admin_url( 'admin-ajax.php' ),
		'admin_url'      => admin_url( 'admin.php' ),
		'url'            => $iframe,
		'pluginUrl'      => admin_url(sprintf('admin.php?page=hipaatizer')),
		'curl'           => $urlc,
		'nonce'          => wp_create_nonce( 'hipaa_refresh_hipaa_forms_nonce' ),
		'allowed_origin' => $allowed_origin,
		'activate_nonce' => wp_create_nonce( 'hipaa_activate_account' ),
	) );


	}

	public function hipaa_script_tags ( $tag, $handle ) {
        if ( 'freshworks' !== $handle ) {
            return $tag;
        }
        return str_replace( ' src', ' async defer src', $tag );
	}

public function hipaa_whiteLabet() {
    global $hipaaID, $whiteLabelUrl;

    if ( empty( $hipaaID ) ) {
        return $whiteLabelUrl;
    }

    $cache_key = 'hipaatizer_public_info_' . get_current_blog_id() . '_' . $hipaaID;

    $whiteLabelUrl = get_transient( $cache_key );
    if ( false !== $whiteLabelUrl ) {
        return $whiteLabelUrl;
    }
    $curl     = HIPAATIZER_APP . '/api/v1/account/' . $hipaaID . '/public_info';
    $response = wp_remote_get( $curl, array( "timeout" => 15 ) );

    if ( is_wp_error( $response ) ) {
        return $whiteLabelUrl;
    }

    $body = wp_remote_retrieve_body( $response );
    $res  = json_decode( $body, true );

    $whiteLabelUrl = ! empty( $res['whiteLabelUrl'] )
        ? $this->hipaa_validate_white_label_url( $res['whiteLabelUrl'] )
        : '';

    set_transient( $cache_key, $whiteLabelUrl, DAY_IN_SECONDS );

    return $whiteLabelUrl;
}

private function hipaa_validate_white_label_url( $url ) {
    if ( empty( $url ) ) {
        return '';
    }
    $parsed = parse_url( $url );
    if ( ! $parsed
        || empty( $parsed['scheme'] )
        || empty( $parsed['host'] )
        || $parsed['scheme'] !== 'https'
    ) {
        return '';
    }
    $host = strtolower( $parsed['host'] );
    // Reject IP addresses and localhost — white label domains must be real hostnames.
    if ( $host === 'localhost'
        || filter_var( $host, FILTER_VALIDATE_IP ) !== false
    ) {
        return '';
    }
    return rtrim( $url, '/' );
}

/**
 * True when $id is a plain form GUID (8-4-4-4-12 hex), as returned by
 * /published_workflows.
 *
 * Form ids are cloud-supplied data. Most uses escape them at output time, but the
 * CF7 migration in hipaa_get_forms() writes the id verbatim into post_content, and
 * that content is rendered as HTML on the front end — escaping at read time cannot
 * undo a bad value once it has been stored. Gate that write on this check so only a
 * GUID can ever be persisted.
 *
 * @param mixed $id Candidate form id.
 * @return bool
 */
private function hipaa_is_valid_form_id( $id ) {
    if ( ! is_string( $id ) || $id === '' ) {
        return false;
    }
    // The D modifier matters: without it PHP's $ also matches before a trailing
    // newline, so "<guid>\n" would be accepted as a GUID.
    return (bool) preg_match(
        '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD',
        $id
    );
}

    public function hipaa_admin_menu() {
        global $hipaaID;

		$domain = ( !empty( $this->hipaa_whiteLabet() ) ) ? $this->hipaa_whiteLabet() : HIPAATIZER_APP;
        add_menu_page(
            __( 'HIPAAtizer', 'hipaatizer' ),
            __( 'HIPAAtizer', 'hipaatizer' ),
            'manage_options',
            'hipaatizer',
            array( $this, 'hipaa_admin_content' ),
            plugin_dir_url( __FILE__ ).'img/icon.png',
            20
        );


		if ( $hipaaID != '') {

		add_submenu_page(
		'hipaatizer',
		__( 'HIPAAtizer Dashboard', 'hipaatizer' ),
		'<span class="item_target_blank">'.__( 'HIPAAtizer Dashboard', 'hipaatizer' ).'</span>',
		'manage_options',
        esc_url($domain.'/my-forms?utm_source=wppl')
			);

		if( is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ) ):

			add_submenu_page(
			'hipaatizer',
			__( 'Import CF7 Forms', 'hipaatizer' ),
			'<span id="item_import_cf7">'.__( 'Import CF7 Forms', 'hipaatizer' ).'</span>',
            'manage_options',
            '?page=hipaatizer&import_cf7=1'
			);

		endif;

		/* if( is_plugin_active( 'wpforms-lite/wpforms.php' ) || is_plugin_active( 'wpforms/wpforms.php' )):

			add_submenu_page(
				'hipaatizer',
				__( 'Import WPForms', 'hipaatizer' ),
				'<span id="item_import_wpf">'.__( 'Import WPForms', 'hipaatizer' ).'</span>',
				'manage_options',
				'?page=hipaatizer&import_wpf=1'
			);

		endif; 

		if( is_plugin_active( 'gravityforms/gravityforms.php' ) ):

			add_submenu_page(
				'hipaatizer',
				__( 'Import WPForms', 'hipaatizer' ),
				'<span id="item_import_gf">'.__( 'Import Gravity Forms', 'hipaatizer' ).'</span>',
				'manage_options',
				'?page=hipaatizer&import_gf=1'
			);

		endif;  */

		add_submenu_page(
			'hipaatizer',
			__( 'Create Form', 'hipaatizer' ),
			'<span class="item_target_blank">'.__( 'Create Form', 'hipaatizer' ).'</span>',
		'manage_options',
        esc_url($domain.'/create-workflow-wizard?utm_source=wppl&isAdminCreateOwnForm=false&isHipaasignForm=false&step=create-form')
			);

		add_submenu_page(
			'hipaatizer',
			__( 'Create HIPAAsign form', 'hipaatizer' ),
			'<span class="item_target_blank">'.__( 'Create HIPAAsign form', 'hipaatizer' ).'</span>',
		'manage_options',
        esc_url($domain.'/create-workflow-wizard?utm_source=wppl&isAdminCreateOwnForm=false&isHipaasignForm=true&step=create-form')
			);

		add_submenu_page(
			'hipaatizer',
			__( 'Create Form Packet', 'hipaatizer' ),
			'<span class="item_target_blank">'.__( 'Create Form Packet', 'hipaatizer' ).'</span>',
		'manage_options',
        esc_url($domain.'/workflows?utm_source=wppl&isDisplayCreateWorkflowModal=true')
			);

		add_submenu_page(
			'hipaatizer',
			__( 'Link another HIPAAtizer account', 'hipaatizer' ),
			'<span id="item_change_account">'.__( 'Link another HIPAAtizer account', 'hipaatizer' ).'</span>',
		'manage_options',
		'?page=hipaatizer&change_account=1'
			);

		add_submenu_page(
			'hipaatizer',
			__( 'Documentation', 'hipaatizer' ),
			'<span class="item_target_blank">' . __( 'Documentation', 'hipaatizer' ) . '</span>',
			'manage_options',
			'https://www.hipaatizer.com/docs/getting-started/'
		);

		}


    }
	public function hipaa_refresh_hipaa_forms(){
		global $hipaaID;
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
		}
		check_ajax_referer( 'hipaa_refresh_hipaa_forms_nonce', 'nonce' );
		if ( ! empty( $hipaaID ) ) {
			delete_transient( 'hipaatizer_workflows_' . get_current_blog_id() . '_' . $hipaaID );
		}
		echo $this->hipaa_get_forms();
		wp_die();
	}

	public function hipaa_tabs_hipaa_forms(){
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
		}

		check_ajax_referer( 'hipaa_refresh_hipaa_forms_nonce', 'nonce' );

		echo $this->hipaa_get_forms();
    wp_die();
	}


	public function hipaa_transfer_cf7(){
		global $cf7key, $message;
		$arr = array();

		foreach($_GET['cf7'] as $val) {
		$id = absint( $val );
		if ( ! $id ) {
			continue;
		}		
		$form            = array();
		$cf7_post        = get_post($id);
		$form['id']      = $id;
		$form['title']   = $cf7_post->post_title;
		$form['content'] = $cf7_post->post_content;
		array_push($arr, $form);
		}

		$data = json_encode($arr);
		$url  = HIPAATIZER_APP.'/api/v1/sign_up/prepare_cf7_forms_for_import?utm_source=wppl&contactForm7Id='.$cf7key;

		$response = wp_remote_post( $url, array(
			'headers' => array(  'content-type' => 'application/json' ),
			'body'    => $data,
			'timeout' => 15,
		) );
		$body    = wp_remote_retrieve_body( $response );
		$res     = json_decode($body, true);
		$message = $res['message'];

	}

	public function hipaa_transfer_cf7_uregistered(){
		global $cf7key, $message;
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( empty( $_GET['cf7'] ) || empty( $_GET['hipaaID'] ) ) {
			return;
		}

		if ( ! isset( $_GET['hipaa_import_cf7_nonce'] ) ||
			! wp_verify_nonce( $_GET['hipaa_import_cf7_nonce'], 'hipaa_import_cf7' ) ) {
			return;
		}		
		$hipaaID = sanitize_key( wp_unslash( $_GET['hipaaID'] ) );
		$_GET['cf7'] = array_map(
			'absint',
			(array) wp_unslash( $_GET['cf7'] )
		);
		if( !empty($_GET['cf7']) &&  !empty($_GET['hipaaID']) ){
			$this->hipaa_transfer_cf7();

			if( $message == "Successful operation."){
				if(!empty($_GET['hipaaID'])) {
					$url  = HIPAATIZER_APP.'/api/v1/account/import/contact_from_7?utm_source=wppl&contactForm7Id='.$cf7key.'&accountId='.$hipaaID;
					$response = wp_remote_get( $url, array( "timeout" => 15 ) );
					$body     = wp_remote_retrieve_body( $response );
					$res      = json_decode($body, true);
					$message_import = $res['message'];
					if( $message_import == "Successful operation."){
						$url = admin_url( 'admin.php' )."?page=hipaatizer&import_cf7=success";
					} else {
					$url = admin_url( 'admin.php' )."?page=hipaatizer&import_cf7=error";
				}

				} else {
					$url = admin_url( 'admin.php' )."?page=hipaatizer&import_cf7=error";
				}
					wp_safe_redirect( esc_url_raw( $url ) );
					exit;
			}
		}
	}

	public function hipaa_transfer_wpf(){
			global $cf7key, $message;
			$arr = array();

			foreach($_GET['wpf'] as $val) {
			    $id = absint( $val );
				if ( ! $id ) {
					continue;
				}				
				$form            = array();
				$wpf_post        = get_post($id);
				$form['id']      = $id ;
				$form['title']   = $wpf_post->post_title;
				$form['content'] = $wpf_post->post_content;
					array_push($arr, $form);
			}

			$data = wp_json_encode( $arr );
			$url  = HIPAATIZER_APP.'/api/v1/sign_up/prepare_cf7_forms_for_import?wpformsId='.$cf7key;


		$response = wp_remote_post( $url, array(
				'headers' => array(  'content-type' => 'application/json' ),
				'body'    => $data,
				'timeout' => 15,
			) );
			$body    = wp_remote_retrieve_body( $response );
			$res     = json_decode($body, true);
			$message = $res['message'];


	}

	public function hipaa_transfer_wpf_uregistered(){
		global $cf7key, $message;

		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( empty( $_GET['wpf'] ) || empty( $_GET['hipaaID'] ) ) {
			return;
		}

		if ( ! isset( $_GET['hipaa_import_wpf_nonce'] ) ||
			! wp_verify_nonce( $_GET['hipaa_import_wpf_nonce'], 'hipaa_import_wpf' ) ) {
			return;
		}

		$hipaaID = sanitize_key( wp_unslash( $_GET['hipaaID'] ) );
		$_GET['wpf'] = array_map(
			'absint',
			(array) wp_unslash( $_GET['wpf'] )
		);

		if ( ! empty( $_GET['wpf'] ) && ! empty( $hipaaID ) ) {
			$this->hipaa_transfer_wpf();

			if ( $message === 'Successful operation.' ) {
				$url  = HIPAATIZER_APP . '/api/v1/account/import/contact_from_7?wpformsId=' . $cf7key . '&accountId=' . $hipaaID;
				$response = wp_remote_get( $url, array( "timeout" => 15 ) );
				$body     = wp_remote_retrieve_body( $response );
				$res      = json_decode( $body, true );
				$message_import = isset( $res['message'] ) ? $res['message'] : '';

				if ( $message_import === 'Successful operation.' ) {
					$url = admin_url( 'admin.php?page=hipaatizer&import_wpf=success' );
				} else {
					$url = admin_url( 'admin.php?page=hipaatizer&import_wpf=error' );
				}

				wp_safe_redirect( esc_url_raw( $url ) );
				exit;
			}
		}
	}
	public function hipaa_transfer_gf(){
		global $cf7key, $message;
		$arr = array();

		foreach($_GET['gf'] as $val) {
			$id = absint( $val );
			if ( ! $id ) { continue; }
			$gf_post = get_post( $id );
			if ( ! $gf_post ) { continue; }
			$form            = array();
			$form['id']      = $id;
			$form['title']   = $gf_post->post_title;
			$form['content'] = $gf_post->post_content;
			array_push($arr, $form);
		}

		$data = json_encode($arr);
		$url  = HIPAATIZER_APP.'/api/v1/sign_up/prepare_cf7_forms_for_import?gravityformsId='.$cf7key;


	$response = wp_remote_post( $url, array(
			'headers' => array(  'content-type' => 'application/json' ),
			'body'    => $data,
			'timeout' => 15,
		) );
		$body    = wp_remote_retrieve_body( $response );
		$res     = json_decode($body, true);
		$message = $res['message'];


}

public function hipaa_transfer_gf_uregistered(){
global $cf7key, $message;
	if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( ! isset( $_GET['hipaa_import_gf_nonce'] ) ||
		! wp_verify_nonce( $_GET['hipaa_import_gf_nonce'], 'hipaa_import_gf' ) ) {
		return;
	}
	if( !empty($_GET['gf']) &&  !empty($_GET['hipaaID']) ){
		$this->hipaa_transfer_gf();

		if( $message == "Successful operation."){
			if(!empty($_GET['hipaaID'])) {
			$url  = HIPAATIZER_APP.'/api/v1/account/import/contact_from_7?gravityformsId='.$cf7key.'&accountId='.sanitize_key($_GET['hipaaID']);
			$response = wp_remote_get( $url, array( "timeout" => 15 ) );
			$body    = wp_remote_retrieve_body( $response );
			$res     = json_decode($body, true);
			$message_import = $res['message'];
		if( $message_import == "Successful operation."){
			$url = admin_url( 'admin.php' )."?page=hipaatizer&import_gf=success";

			} else {
				$url = admin_url( 'admin.php' )."?page=hipaatizer&import_gf=error";
			}

			} else {
		$url = admin_url( 'admin.php' )."?page=hipaatizer&import_gf=error";
			}
			wp_safe_redirect( esc_url($url) );
			exit;
		}
	}
}

	public function hipaa_get_forms(){
		global $wpdb, $hipaaID, $domain;
		$dbprefix = is_multisite() ? $wpdb->get_blog_prefix( get_current_blog_id() ) : $wpdb->prefix;

		// Resolve $domain here so AJAX calls (which bypass hipaa_admin_content) still
		// produce absolute URLs pointing at the cloud app rather than the WP site.
		if ( empty( $domain ) ) {
			$wl     = $this->hipaa_whiteLabet();
			$domain = ! empty( $wl ) ? rtrim( $wl, '/' ) : HIPAATIZER_APP;
		}

		if ( $hipaaID != '' && !isset($_GET['hipaa_account']) && !isset($_GET['change_account']) ){

			$curl     = HIPAATIZER_APP.'/api/v1/account/'.$hipaaID.'/published_workflows';
			$response = wp_remote_get( $curl, array( "timeout" => 15 ) );
			if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
				return '<p>' . esc_html__( 'Could not load forms. Please try again.', 'hipaatizer' ) . '</p>';
			}
			$body    = wp_remote_retrieve_body( $response );
			$res     = json_decode( $body, true );
			$results = isset( $res['workflows'] ) && is_array( $res['workflows'] ) ? $res['workflows'] : array();

			$type_form   = isset( $_GET['type'] )      ? sanitize_text_field( wp_unslash( $_GET['type'] ) )      : 'SimpleForm';
			$folder_form = isset( $_GET['folderId'] )  ? sanitize_text_field( wp_unslash( $_GET['folderId'] ) )  : '';
			$status_form = isset( $_GET['status'] )    ? sanitize_text_field( wp_unslash( $_GET['status'] ) )    : '';
			$search_term = isset( $_GET['s'] )         ? sanitize_text_field( wp_unslash( $_GET['s'] ) )         : '';
			$forms       = array();
			foreach ($results as $item) :
				// API can return error shapes — each element must be a workflow object.
				if ( ! is_array( $item ) ) continue;

				// The /published_workflows API tags each record with a 'type' field:
				//   - 'Workflow'   → form packet
				//   - 'SimpleForm' → regular form
				//   - 'HipaaSign'  → HIPAA e-signature form
				// Use the type field directly; do NOT infer from the link URL.
				$item_type = isset( $item['type'] ) ? $item['type'] : '';

				// Route to the correct tab.
				if ( $type_form === 'FormPacket' ) {
					if ( $item_type !== 'Workflow' ) continue;
				} else {
					// SimpleForm / HipaaSign tabs never show form packets.
					if ( $item_type === 'Workflow' ) continue;
					if ( $item_type !== $type_form ) continue;
				}

				// Apply folder / status filters on top — match the dedicated fields only.
				if ( $folder_form !== '' && $folder_form !== '-1' ) {
					$item_folder = isset( $item['folderId'] ) ? (string) $item['folderId'] : '';
					if ( $item_folder !== $folder_form ) continue;
				} elseif ( $status_form !== '' ) {
					$item_status = isset( $item['status'] ) ? $item['status'] : '';
					if ( $item_status !== $status_form ) continue;
				}

				$forms[] = $item;
			endforeach;

			if ( $search_term !== '' ) {
				$forms = array_values( array_filter( $forms, function( $item ) use ( $search_term ) {
					return stripos( $item['name'], $search_term ) !== false;
				} ) );
			}

			$sort_by    = isset( $_GET['sort'] )  && in_array( $_GET['sort'],  array( 'name', 'status' ), true ) ? $_GET['sort']  : '';
			$sort_order = isset( $_GET['order'] ) && $_GET['order'] === 'desc'                                    ? 'desc'         : 'asc';

			if ( $sort_by === 'name' ) {
				usort( $forms, function( $a, $b ) use ( $sort_order ) {
					$cmp = strcasecmp( $a['name'], $b['name'] );
					return $sort_order === 'desc' ? -$cmp : $cmp;
				} );
			} elseif ( $sort_by === 'status' ) {
				$status_order = array( 'Published' => 0, 'Draft' => 1, 'Pending' => 2, 'Archived' => 3 );
				usort( $forms, function( $a, $b ) use ( $sort_order, $status_order ) {
					$av = isset( $status_order[ $a['status'] ] ) ? $status_order[ $a['status'] ] : 99;
					$bv = isset( $status_order[ $b['status'] ] ) ? $status_order[ $b['status'] ] : 99;
					$cmp = $av - $bv;
					return $sort_order === 'desc' ? -$cmp : $cmp;
				} );
			}
			?>

		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="page" value="hipaatizer">
			<?php if ( $type_form !== 'SimpleForm' ) : ?>
			<input type="hidden" name="type" value="<?php echo esc_attr( $type_form ); ?>">
			<?php endif; ?>
			<div class="tablenav top">
				<div class="alignleft actions"></div>
				<p class="search-box">
					<label class="screen-reader-text" for="hipaa-search-input"><?php esc_html_e( 'Search Forms', 'hipaatizer' ); ?></label>
					<input type="search" id="hipaa-search-input" name="s" value="<?php echo esc_attr( $search_term ); ?>">
					<input type="submit" id="search-submit" class="button" value="<?php echo esc_attr( $type_form === 'FormPacket' ? __( 'Search Form Packets', 'hipaatizer' ) : __( 'Search Forms', 'hipaatizer' ) ); ?>">
				</p>
				<br class="clear">
			</div>
		</form>
		<div class="hipaa-loader"></div>
		<?php
		// $filter_url: filters only — used as the base for sort link hrefs (sort/order appended by hipaa_sort_url)
		// $base_url:   filters + current sort — used for pagination links so sort state survives page changes
		$filter_url    = esc_url( admin_url( 'admin.php' ) ) . '?page=hipaatizer';
		$filter_params = http_build_query( array_filter( array(
			'type'     => $type_form !== 'SimpleForm' ? $type_form : '',
			'folderId' => $folder_form,
			'status'   => $status_form,
			's'        => $search_term,
		) ) );
		if ( $filter_params ) $filter_url .= '&' . $filter_params;

		$base_url = $filter_url;
		if ( $sort_by ) $base_url .= '&sort=' . rawurlencode( $sort_by );
		if ( $sort_order === 'desc' ) $base_url .= '&order=desc';

		function hipaa_sort_url( $filter_base, $col, $current_sort, $current_order ) {
			$next_order = ( $current_sort === $col && $current_order === 'asc' ) ? 'desc' : 'asc';
			return $filter_base . '&sort=' . $col . '&order=' . $next_order;
		}
		?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
			<tr>
			<th scope="col" class="manage-column column-title sortable <?php echo $sort_by === 'name' ? 'sorted ' . $sort_order : ''; ?>">
				<a href="<?php echo esc_url( hipaa_sort_url( $filter_url, 'name', $sort_by, $sort_order ) ); ?>"><span><?php esc_html_e( 'Title', 'hipaatizer' ); ?></span><span class="sorting-indicators"><span class="sorting-indicator asc" aria-hidden="true"></span><span class="sorting-indicator desc" aria-hidden="true"></span></span></a>
			</th>
			<th scope="col" class="manage-column column-status sortable <?php echo $sort_by === 'status' ? 'sorted ' . $sort_order : ''; ?>">
				<a href="<?php echo esc_url( hipaa_sort_url( $filter_url, 'status', $sort_by, $sort_order ) ); ?>"><span><?php esc_html_e( 'Status', 'hipaatizer' ); ?></span><span class="sorting-indicators"><span class="sorting-indicator asc" aria-hidden="true"></span><span class="sorting-indicator desc" aria-hidden="true"></span></span></a>
			</th>
			<th scope="col" class="manage-column column-shortcode"><?php esc_html_e( 'Shortcode', 'hipaatizer' ); ?></th>
			<th scope="col" class="manage-column column-submissions"><?php esc_html_e( 'Submissions', 'hipaatizer' ); ?></th>
			<th scope="col" class="manage-column column-actions"><?php esc_html_e( 'Actions', 'hipaatizer' ); ?></th>
			</tr>
			</thead>
			<tbody>
			<?php
			if( $forms ):
			$nb_elem_per_page = 10;
			$page = isset( $_GET['paged'] ) ? max( 0, intval( $_GET['paged'] ) - 1 ) : 0;
			$number_of_pages = (int) ceil( count($forms) / $nb_elem_per_page );

			foreach(array_slice($forms, $page*$nb_elem_per_page, $nb_elem_per_page)  as $row ){
				$form_id    = $row['id'];
				$form_title = $row['name'];
				$form_status = $row['status'];
				$form_type = ( array_key_exists('type', $row)) ? $row['type'] : 'SimpleForm';
				// Only migrate CF7 embeds when the form id is a plain GUID. $form_id comes
				// from the cloud API and is written verbatim into post_content below, where
				// it is later rendered as HTML on the front end — so anything that is not a
				// GUID must never reach that path.
				if( array_key_exists('contactFrom7Id', $row) && $this->hipaa_is_valid_form_id( $form_id ) ):
					$cf7id  = $row['contactFrom7Id'];
					if ( function_exists('has_blocks') ) {
						$str1 = strval('<!-- wp:contact-form-7/contact-form-selector {"id":'.$cf7id.',"title":"'.$form_title.'"} -->');
						$str2 = strval('<!-- wp:hipaatizer/hipaa-form {"formID":"'.$form_id.'"} -->');
						$str3 = '<div class="wp-block-contact-form-7-contact-form-selector">[contact-form-7 id="'.$cf7id.'" title="'.$form_title.'"]</div>';
						$str4 = '<div class="hipaa-form" id="'.$form_id.'">[hipaatizer id="'.$form_id.'"]</div>';
						$update1 = "UPDATE `{$dbprefix}posts` SET post_content = REPLACE(post_content, %s, %s)";
						$wpdb->query( $wpdb->prepare( $update1, $str1, $str2) );
						$update2 = "UPDATE `{$dbprefix}posts` SET post_content = REPLACE(post_content, %s, %s)";
						$wpdb->query( $wpdb->prepare( $update2, $str3, $str4) );
					} else {
						$str1 = '[contact-form-7 id="'.$cf7id.'" title="'.$form_title.'"]';
						$str2 = '[hipaatizer id="'.$form_id.'"]';
						$update3 = "UPDATE `{$dbprefix}posts` SET post_content = REPLACE(post_content, %s, %s)";
						$wpdb->query( $wpdb->prepare( $update3, $str1, $str2 ) );
					}
				endif;
				echo '<tr>';
				echo '<td>'.esc_html($form_title).'</td>';
				echo '<td>';
				switch ( $form_status ) {
					case 'Draft':    $formStatus = '<span class="hipaa-status hipaa-status-draft">'    . esc_html__( 'Draft',     'hipaatizer' ) . '</span>'; break;
					case 'Archived': $formStatus = '<span class="hipaa-status hipaa-status-archived">' . esc_html__( 'Archived',  'hipaatizer' ) . '</span>'; break;
					case 'Pending':  $formStatus = '<span class="hipaa-status hipaa-status-pending">'  . esc_html__( 'Pending',   'hipaatizer' ) . '</span>'; break;
					default:         $formStatus = '<span class="hipaa-status hipaa-status-published">' . esc_html__( 'Published', 'hipaatizer' ) . '</span>'; break;
				}
				echo wp_kses( $formStatus, array( 'span' => array( 'class' => array() ) ) );
				echo '</td>';
				echo '<td class="column-shortcode">';
				if ( $form_status == 'Published' ) {
					echo '<div class="hipaa-shortcode-wrap">';
					echo '<code>[hipaatizer id="' . esc_attr( $form_id ) . '"]</code>';
					echo '<a href="#" class="copyShortcode button button-small" title="' . esc_attr__( 'Copy shortcode', 'hipaatizer' ) . '">' . esc_html__( 'Copy', 'hipaatizer' ) . '</a>';
					echo '<span class="textShortcode">[hipaatizer id="' . esc_attr( $form_id ) . '"]</span>';
					echo '</div>';
				} else {
					echo '&mdash;';
				}
				echo '</td>';
				switch($type_form){
					case 'SimpleForm': $query_sub = 'submissions'; break;
					case 'HipaaSign': $query_sub = 'envelopes'; break;
					case 'Workflow':
					case 'FormPacket': $query_sub = 'submission-group'; break;
				}
				echo '<td class="column-submissions"><a href="' . esc_url( $domain . '/' . esc_attr( $query_sub ) . '/' . esc_attr( $form_id ) . '?utm_source=wppl' ) . '" target="_blank">' . esc_html__( 'View Submissions', 'hipaatizer' ) . '</a></td>';
				echo '<td>';
				if ( $form_type == 'Workflow' ) {
					echo '<a href="' . esc_url( $domain . '/workflows?utm_source=wppl&search=' . esc_attr( $form_title ) ) . '" class="button button-small" target="_blank">' . esc_html__( 'View', 'hipaatizer' ) . '</a>';
				} else {
					echo '<a href="' . esc_url( $domain . '/form-builder/edit-workflow/' . esc_attr( $form_id ) . '?utm_source=wppl' ) . '" class="button button-small" target="_blank">' . esc_html__( 'Edit', 'hipaatizer' ) . '</a>';
				}

				echo '</td>';
				echo '</tr>';
			}

		else:
			echo '<tr><td colspan="5">' . esc_html__( 'There are no forms in this folder', 'hipaatizer' ) . '</td></tr>';
		endif; ?>
			</tbody>
			</table>
			<?php if ( $forms ) :
				$current_page = $page + 1;
			?>
			<div class="tablenav bottom">
				<div class="tablenav-pages">
					<span class="displaying-num"><?php echo esc_html( count( $forms ) . ' ' . _n( 'item', 'items', count( $forms ), 'hipaatizer' ) ); ?></span>
					<?php if ( $number_of_pages > 1 ) : ?>
					<span class="pagination-links">
						<?php if ( $page > 0 ) : ?>
						<a class="first-page button" href="<?php echo esc_url( $base_url . '&paged=1' ); ?>"><span aria-hidden="true">«</span></a>
						<a class="prev-page button" href="<?php echo esc_url( $base_url . '&paged=' . $page ); ?>"><span aria-hidden="true">‹</span></a>
						<?php else : ?>
						<span class="first-page button disabled" aria-disabled="true"><span aria-hidden="true">«</span></span>
						<span class="prev-page button disabled" aria-disabled="true"><span aria-hidden="true">‹</span></span>
						<?php endif; ?>
						<span class="paging-input"><?php echo esc_html( $current_page . ' ' . __( 'of', 'hipaatizer' ) . ' ' . $number_of_pages ); ?></span>
						<?php if ( $page < $number_of_pages - 1 ) : ?>
						<a class="next-page button" href="<?php echo esc_url( $base_url . '&paged=' . ( $page + 2 ) ); ?>"><span aria-hidden="true">›</span></a>
						<a class="last-page button" href="<?php echo esc_url( $base_url . '&paged=' . $number_of_pages ); ?>"><span aria-hidden="true">»</span></a>
						<?php else : ?>
						<span class="next-page button disabled" aria-disabled="true"><span aria-hidden="true">›</span></span>
						<span class="last-page button disabled" aria-disabled="true"><span aria-hidden="true">»</span></span>
						<?php endif; ?>
					</span>
					<?php endif; ?>
				</div>
				<br class="clear">
			</div>
			<?php
			endif;
			}
}

	public function hipaa_existing_forms() { ?>
		<form id="export-wpcf7" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>?page=hipaatizer" method="post" class="export-forms">
			<?php
			$args = array(
            'numberposts' => -1,
            'post_type'   => 'wpcf7_contact_form',
            );
            $forms = get_posts( $args );
			if( !empty( $forms ) ): ?>

			<div class="hipaa-title">
				<h2 class="has-text-align-center c-black"><?php esc_html_e('Hello! Let\'s start HIPAAtizing your forms!', 'hipaatizer'); ?></h2>
				<p class="has-text-align-center"><?php esc_html_e('We found the following Contact Form 7 forms.  Please select the form(s) you want to work with in HIPAAtizer.', 'hipaatizer'); ?></p>
			</div>

			<label><input id="select_all" type="checkbox"> <strong>Select All</strong></label>

                <?php foreach ( $forms as $form ){
							echo '<label><input type="checkbox" name="cf7[]" value="'.sanitize_key($form->ID).'"> '.esc_html($form->post_title).'</label>';
				} ?>
				<p class="has-text-align-center mt-20"><input type="submit" value="<?php esc_attr_e('Continue', 'hipaatizer'); ?>" class="btn"></p>

			<?php endif; ?>


        </form>
        <p class="has-text-align-center"><a href="?page=hipaatizer&hipaa_account=signup"><?php esc_attr_e('Go to Sign Up without import', 'hipaatizer'); ?></a><br>
        <?php esc_html_e('Already have an account?', 'hipaatizer'); ?> <a href="?page=hipaatizer&hipaa_account=login"><?php esc_html_e('Log in', 'hipaatizer'); ?></a></p>
	<?php }

	public function hipaa_import_cf7() {
			global $hipaaID;
	?>
		<form id="export-wpcf7" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" method="get" class="export-forms">
			<input type="hidden" name="page" value="hipaatizer">
			<input type="hidden" name="hipaaID" value="<?php echo sanitize_key($hipaaID); ?>">
			<?php wp_nonce_field( 'hipaa_import_cf7', 'hipaa_import_cf7_nonce' ); ?>			
			<?php
			$args = array(
            'numberposts' => -1,
            'post_type'   => 'wpcf7_contact_form',
            );
            $forms = get_posts( $args );

			if( !empty( $forms ) ): ?>

			<div class="hipaa-title">
				<h2 class="has-text-align-center c-black"><?php esc_html_e('Select forms you want to import into HIPAAtizer:', 'hipaatizer'); ?></h2>
			</div>
			<label><input id="select_all" type="checkbox"> <strong>Select All</strong></label>
                <?php foreach ( $forms as $form ){
					echo '<label><input type="checkbox" name="cf7[]" value="'.sanitize_key($form->ID).'"> '.esc_html($form->post_title).'</label>';
				} ?>
				<p class="has-text-align-center mt-20"><a href="?page=hipaatizer" class="btnrev mr-20"><?php esc_attr_e('Cancel', 'hipaatizer'); ?></a> <input type="submit" value="<?php esc_attr_e('Continue', 'hipaatizer'); ?>" class="btn"></p>
		<?php else: ?>
			<p class="has-text-align-center mt-20"><?php echo esc_html('The list of the forms is empty', 'hipaatizer'); ?></p>
		<?php endif; ?>

        </form>
	<?php }

	public function hipaa_import_wpf() {
		global $hipaaID;
	?>
	<form id="export-wpf" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" method="get" class="export-forms">
		<input type="hidden" name="page" value="hipaatizer">
		<input type="hidden" name="hipaaID" value="<?php echo sanitize_key($hipaaID); ?>">
		<?php
		$args = array(
		'numberposts' => -1,
		'post_type'   => 'wpforms',
		);
		$forms = get_posts( $args );
		if( !empty( $forms ) ): ?>
			<div class="hipaa-title">
				<h2 class="has-text-align-center c-black"><?php esc_html_e('Select forms you want to import into HIPAAtizer:', 'hipaatizer'); ?></h2>
			</div>

			<label><input id="select_all" type="checkbox"> <strong>Select All</strong></label>

			<?php foreach ( $forms as $form ){
				echo '<label><input type="checkbox" name="wpf[]" value="'.sanitize_key($form->ID).'"> '.esc_html($form->post_title).'</label>';
			} ?>
			<p class="has-text-align-center mt-20"><a href="?page=hipaatizer" class="btnrev mr-20"><?php esc_attr_e('Cancel', 'hipaatizer'); ?></a> <input type="submit" value="<?php esc_attr_e('Continue', 'hipaatizer'); ?>" class="btn"></p>

		<?php else: ?>
			<p class="has-text-align-center mt-20"><?php echo esc_html('The list of the forms is empty', 'hipaatizer'); ?></p>
		<?php endif; ?>

	</form>
<?php }

	public function hipaa_import_gf() {
		global $wpdb, $hipaaID;
		$dbprefix = is_multisite() ? $wpdb->get_blog_prefix( get_current_blog_id() ) : $wpdb->prefix;
	?>
	<form id="export-gf" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" method="get" class="export-forms">
		<input type="hidden" name="page" value="hipaatizer">
		<input type="hidden" name="hipaaID" value="<?php echo sanitize_key($hipaaID); ?>">
		<?php wp_nonce_field( 'hipaa_import_gf', 'hipaa_import_gf_nonce' ); ?>
		<?php
		$forms = $wpdb->get_results( "SELECT id, title FROM `{$dbprefix}gf_form` WHERE is_active = 1 AND is_trash = 0", OBJECT );
		if( !empty( $forms ) ): ?>
			<div class="hipaa-title">
				<h2 class="has-text-align-center c-black"><?php esc_html_e('Select forms you want to import into HIPAAtizer:', 'hipaatizer'); ?></h2>
			</div>

			<label><input id="select_all" type="checkbox"> <strong>Select All</strong></label>

			<?php foreach ( $forms as $form ){
				echo '<label><input type="checkbox" name="gf[]" value="'.sanitize_key($form->id).'"> '.esc_html($form->title).'</label>';
			} ?>
			<p class="has-text-align-center mt-20"><a href="?page=hipaatizer" class="btnrev mr-20"><?php esc_attr_e('Cancel', 'hipaatizer'); ?></a> <input type="submit" value="<?php esc_attr_e('Continue', 'hipaatizer'); ?>" class="btn"></p>

		<?php else: ?>
			<p class="has-text-align-center mt-20"><?php echo esc_html('The list of the forms is empty', 'hipaatizer'); ?></p>
		<?php endif; ?>

	</form>
	<?php }

	public function hipaa_signup_header(){ ?>
		<div class="hipaa-wrapper full-width">
		<div class="hipaa-header d-flex justify-content-between">
			<img src="<?php echo esc_url(HIPAATIZER_PATH.'/admin/img/logo.svg'); ?>" alt="HIPAAtizer">
			<a href="<?php echo esc_url(HIPAATIZER_APP.'/my-forms?utm_source=wppl'); ?>" class="d-flex" target="_blank"><img src="<?php echo esc_url(HIPAATIZER_PATH.'/admin/img/login-icon.png'); ?>" alt=""> <?php esc_html_e('My Account', 'hipaatizer'); ?></a>
		</div>
		<div class="hipaa-loader"></div>
		<div class="hipaa-fcontent d-flex">
			<div class="hipaa-form-code d-flex">
	<?php
	}

	public function hipaa_signup_footer(){ ?>
		</div>
				<div class="hipaa-img">
					<img src="<?php echo esc_url(HIPAATIZER_PATH.'/admin/img/features-image.jpg'); ?>" alt="">
				</div>

			</div>

		</div>
	<?php
	}


    public function hipaa_admin_content() {
		global $hipaaID, $hipaa_message, $domain;

		if ( $hipaaID != '' && !isset($_GET['hipaa_account']) && !isset($_GET['change_account'])  ):

			$curl     = HIPAATIZER_APP.'/api/v1/account/'.$hipaaID.'/public_info';
			$response = wp_remote_get( $curl, array( "timeout" => 15 ) );
			$body     = wp_remote_retrieve_body( $response );
			$res      = json_decode($body, true);

			$hipaa_email 	 = ( isset($res['email']) ) ? $res['email'] : '';
			$businessName  = ( isset($res['businessName']) ) ? $res['businessName'] : '';
			$type_form 	 = ( isset($_GET['type'])) ? trim($_GET['type'] ) : 'SimpleForm';

			// Route the cloud-supplied whiteLabelUrl through the same validator used
			// elsewhere (enforces https, rejects IPs/localhost) instead of accepting it raw.
			$validated_wl = ! empty( $res['whiteLabelUrl'] )
				? $this->hipaa_validate_white_label_url( $res['whiteLabelUrl'] )
				: '';
			$domain = ! empty( $validated_wl ) ? $validated_wl : HIPAATIZER_APP;

		switch($type_form){
			case 'HipaaSign':   $titleForm = esc_html__('HIPAAsign forms', 'hipaatizer'); break;
			case 'Workflow':    $titleForm = esc_html__('Workflows', 'hipaatizer'); break;
			case 'FormPacket':  $titleForm = esc_html__('Form Packets', 'hipaatizer'); break;
			default:            $titleForm = esc_html__('My Forms', 'hipaatizer');
		}
	?>

	<div class="wrap">
		<h1 class="wp-heading-inline"><?php echo esc_html( $titleForm ); ?></h1>
		<button type="button" class="page-title-action hipaa-refresh" data-type="<?php echo esc_attr( $type_form ); ?>"><?php esc_html_e( 'Refresh', 'hipaatizer' ); ?></button>
		<?php if ( is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ) ) : ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=hipaatizer&import_cf7=1' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Import CF7', 'hipaatizer' ); ?></a>
		<?php endif; ?>
		<a href="<?php echo esc_url( $domain . '/create-workflow-wizard?utm_source=wppl&isAdminCreateOwnForm=false&step=create-form' ); ?>" class="page-title-action hipaa-ext-link" target="_blank" rel="noopener"><?php esc_html_e( 'Create Form', 'hipaatizer' ); ?> <span class="hipaa-ext-icon" aria-label="<?php esc_attr_e( 'opens in new tab', 'hipaatizer' ); ?>">&#8599;</span></a>
		<a href="<?php echo esc_url( $domain . '?utm_source=wppl' ); ?>" class="page-title-action hipaa-ext-link" target="_blank" rel="noopener"><?php esc_html_e( 'Dashboard', 'hipaatizer' ); ?> <span class="hipaa-ext-icon" aria-label="<?php esc_attr_e( 'opens in new tab', 'hipaatizer' ); ?>">&#8599;</span></a>
		<a href="https://www.hipaatizer.com/docs/getting-started/" class="page-title-action hipaa-ext-link" target="_blank" rel="noopener"><?php esc_html_e( 'Documentation', 'hipaatizer' ); ?> <span class="hipaa-ext-icon" aria-label="<?php esc_attr_e( 'opens in new tab', 'hipaatizer' ); ?>">&#8599;</span></a>
		<hr class="wp-header-end">

		<p class="hipaa-account-meta">
			<?php if ( ! empty( $businessName ) ) : ?>
			<?php echo sprintf( esc_html__( 'Viewing forms for %s', 'hipaatizer' ), '<strong>' . esc_html( $businessName ) . '</strong>' ); ?> &bull;
			<?php endif; ?>
			<?php echo esc_html( $hipaa_email ); ?> &mdash; <a href="<?php echo esc_url( admin_url( 'admin.php?page=hipaatizer&change_account=1' ) ); ?>"><?php esc_html_e( 'Switch Account', 'hipaatizer' ); ?></a>
		</p>

		<?php if ( ! empty( $_GET['import_cf7'] ) && $_GET['import_cf7'] == 1 ) : ?>
			<?php echo $this->hipaa_import_cf7(); ?>
		<?php elseif ( ! empty( $_GET['import_wpf'] ) && $_GET['import_wpf'] == 1 ) : ?>
			<?php echo $this->hipaa_import_wpf(); ?>
		<?php elseif ( ! empty( $_GET['import_gf'] ) && $_GET['import_gf'] == 1 ) : ?>
			<?php echo $this->hipaa_import_gf(); ?>
		<?php else : ?>

		<nav class="nav-tab-wrapper">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=hipaatizer' ) ); ?>" class="nav-tab<?php echo ( ! isset( $_GET['type'] ) ) ? ' nav-tab-active' : ''; ?>"><?php esc_html_e( 'My Forms', 'hipaatizer' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=hipaatizer&type=FormPacket' ) ); ?>" class="nav-tab<?php echo ( ! empty( $_GET['type'] ) && $_GET['type'] === 'FormPacket' ) ? ' nav-tab-active' : ''; ?>"><?php esc_html_e( 'Form Packets', 'hipaatizer' ); ?></a>
		</nav>

		<?php if ( $type_form !== 'FormPacket' ) : ?>
		<?php
		$cur_folder      = isset( $_GET['folderId'] ) ? sanitize_text_field( wp_unslash( $_GET['folderId'] ) ) : '';
		$cur_status      = isset( $_GET['status'] )   ? sanitize_text_field( wp_unslash( $_GET['status'] ) )   : '';
		$base_filter_url = admin_url( 'admin.php?page=hipaatizer' );
		$curl_f          = HIPAATIZER_APP . '/api/v1/account/' . $hipaaID . '/folders';
		$response_f      = wp_remote_get( $curl_f, array( "timeout" => 15 ) );
		$folders_f       = array();
		if ( ! is_wp_error( $response_f ) && wp_remote_retrieve_response_code( $response_f ) === 200 ) {
			$decoded_f = json_decode( wp_remote_retrieve_body( $response_f ), true );
			if ( is_array( $decoded_f ) ) {
				$folders_f = $decoded_f;
			}
		}
		?>
		<ul class="subsubsub">
			<li><a href="<?php echo esc_url( $base_filter_url ); ?>"<?php echo ( $cur_folder === '' && $cur_status === '' ) ? ' class="current" aria-current="page"' : ''; ?>><?php esc_html_e( 'All', 'hipaatizer' ); ?></a> |</li>
			<?php foreach ( $folders_f as $folder ) :
				// API can return error shapes like ['error' => '...'] — each element must be a folder object.
				if ( ! is_array( $folder ) || ! isset( $folder['id'], $folder['name'] ) ) continue;
				$fId   = $folder['id'];
				$fName = $folder['name'];
				$fSign = isset( $folder['isHipaaSignForm'] ) ? $folder['isHipaaSignForm'] : 0;
				if ( $type_form === 'SimpleForm' && $fSign == 1 ) continue;
				if ( $type_form === 'HipaaSign'  && $fSign != 1 ) continue;
				$fUrl  = $base_filter_url . '&folderId=' . rawurlencode( $fId );
			?>
			<li><a href="<?php echo esc_url( $fUrl ); ?>"<?php echo ( $cur_folder === (string) $fId ) ? ' class="current" aria-current="page"' : ''; ?>><?php echo esc_html( $fName ); ?></a> |</li>
			<?php endforeach; ?>
			<li><a href="<?php echo esc_url( $base_filter_url . '&status=Archived' ); ?>"<?php echo ( $cur_status === 'Archived' ) ? ' class="current" aria-current="page"' : ''; ?>><?php esc_html_e( 'Archived', 'hipaatizer' ); ?></a></li>
		</ul>
		<?php endif; ?>

		<div id="hipaa-list">
			<?php echo $this->hipaa_get_forms(); ?>
		</div>

		<?php endif; ?>
	</div>
        <?php  elseif ( $hipaaID == '' && !isset($_GET['hipaa_account']) && !isset($_GET['change_account']) && is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ) && !isset($_POST['cf7'])  ):  ?>


		<div class="hipaa-wrapper">
			<div class="hipaa-header d-flex justify-content-between">
			<img src="<?php echo esc_url(HIPAATIZER_PATH.'/admin/img/logo.svg'); ?>" alt="HIPAAtizer">
			<a href="?page=hipaatizer&hipaa_account=login"><img src="<?php echo esc_url(HIPAATIZER_PATH.'/admin/img/logout-icon.svg'); ?>" alt=""> <?php esc_html_e('Log in', 'hipaatizer'); ?></a>
		</ul>
	</div>

	<div class="maxw-762 mx-auto"><?php echo $this->hipaa_existing_forms(); ?></div>

	</div>

	<?php  elseif (  !empty($_GET['hipaa_account']) && $_GET['hipaa_account'] != 'activation_code' ):
		echo '<div class="hipaa-iframe-container"></div>';
	elseif ( !empty($_GET['hipaa_account']) && $_GET['hipaa_account'] == 'activation_code' ):
			echo $this->hipaa_signup_header(); ?>
			<h2><?php esc_html_e('Activation Code', 'hipaatizer'); ?></h2>
			<form action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
				<input type="hidden" name="page" value="hipaatizer">
				<?php
				// Nonce travels with the form submission, so the ?code= handler can
				// authorize the manual activation without depending on a server-side
				// transient surviving across requests (which breaks on cached / object-cache hosts).
				wp_nonce_field( 'hipaa_activate_account', 'hipaa_activate_nonce', false );
				?>
				<?php if( !empty($_GET['cf7'])): ?>
					<?php foreach($_GET['cf7'] as $val ): ?>
						<input type="hidden" name="cf7[]" value="<?php echo sanitize_key($val); ?>">
					<?php endforeach; ?>
				<?php endif; ?>

				<label>
					<span>*</span> <?php esc_html_e('Code', 'hipaatizer'); ?>
					<input type="text" name="code" required="required">
					<span class="error"><?php echo esc_html($hipaa_message); ?></span>
				</label>

				<button class="btn"><?php esc_html_e('Continue', 'hipaatizer'); ?></button>
			</form>
			<div class="hipaa-activation-help">
				<p><?php esc_html_e( 'If you were invited as a Developer by a Covered Entity account, the activation code is included in your invitation email.', 'hipaatizer' ); ?></p>
				<p><?php esc_html_e( 'To access your forms, locate your Activation Code:', 'hipaatizer' ); ?></p>
				<ul>
					<li><?php echo wp_kses(
						sprintf(
							/* translators: %s: link to HIPAAtizer dashboard */
							__( 'In the <a href="%s" target="_blank" rel="noopener">HIPAAtizer Dashboard</a> under <strong>My Forms &rarr; Form Settings &rarr; Integrations &rarr; WordPress</strong>', 'hipaatizer' ),
							'https://app.hipaatizer.com/'
						),
						array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ), 'strong' => array() )
					); ?></li>
					<li><?php esc_html_e( 'Or in the Team menu under the Activation Code tab.', 'hipaatizer' ); ?></li>
				</ul>
				<p><?php echo wp_kses(
					sprintf(
						/* translators: %s: link to switching accounts documentation */
						__( 'If you are a Developer, make sure you have impersonated the Covered Entity account before activating the plugin and grab the activation code from there. <a href="%s" target="_blank" rel="noopener">Learn how to switch accounts &rarr;</a>', 'hipaatizer' ),
						'https://www.hipaatizer.com/docs/account/switching-between-accounts-in-hipaatizer'
					),
					array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) )
				); ?></p>
			</div>
			<p><?php esc_html_e("Don't have an activation code?", 'hipaatizer'); ?> <a href="?page=hipaatizer&hipaa_account=signup"><?php esc_html_e('Sign Up', 'hipaatizer'); ?></a> <?php esc_html_e('or', 'hipaatizer'); ?> <a href="?page=hipaatizer&hipaa_account=login"><?php esc_html_e('Log in', 'hipaatizer'); ?></a></p>
			<?php echo $this->hipaa_signup_footer();
		else:
			echo $this->hipaa_signup_header(); ?>
				<h2 class="mb-0"><?php esc_html_e('Welcome to HIPAAtizer!', 'hipaatizer'); ?></h2>
				<h5><?php esc_html_e('Make Any Website HIPAA Compliant', 'hipaatizer'); ?></h5>
				<p class="hipaa-welcome-docs-link"><a href="https://www.hipaatizer.com/docs/getting-started/" target="_blank" rel="noopener"><?php esc_html_e( 'Read the documentation to get started', 'hipaatizer' ); ?> &#8599;</a></p>
				<form action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
					<input type="hidden" name="page" value="hipaatizer">
					<?php if( !empty($_POST['cf7'])): ?>
						<?php foreach($_POST['cf7'] as $val ): ?>
							<input type="hidden" name="cf7[]" value="<?php echo sanitize_key($val); ?>">
						<?php endforeach; ?>
					<?php endif; ?>
					<label><input type="radio" name="hipaa_account" value="signup" required="required"><?php esc_html_e("I want to Create an account", 'hipaatizer'); ?></label>
					<label><input type="radio" name="hipaa_account" value="login" required="required"><?php esc_html_e('I already have an account', 'hipaatizer'); ?></label>
					<label><input type="radio" name="hipaa_account" value="activation_code" required="required"><?php esc_html_e('I have an activation code', 'hipaatizer'); ?></label>

					<button class="btn"><?php esc_html_e('Continue', 'hipaatizer'); ?></button>
				</form>

				<?php echo $this->hipaa_signup_footer();
		endif;

	if ( ! empty( $_GET['import_cf7'] ) && $_GET['import_cf7'] === 'success' ) :
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Forms have been successfully imported', 'hipaatizer' ) . '</p></div>';
	elseif ( ! empty( $_GET['import_cf7'] ) && $_GET['import_cf7'] === 'error' ) :
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Forms have not imported. Please try again.', 'hipaatizer' ) . '</p></div>';
	endif;
    }

}
