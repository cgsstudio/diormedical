<?php
/**
 * Register all actions and filters for the plugin.
 *
 */
class HIPAAtizer_Loader {

	protected $actions;

	protected $filters;

	public function __construct() {

		$this->actions = array();
		$this->filters = array();

	}


	public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->actions = $this->add( $this->actions, $hook, $component, $callback, $priority, $accepted_args );
		    
		    add_action( 'init', array( $this, 'register_gutenberg_block' ) );
			add_action( 'enqueue_block_editor_assets', array( $this, 'hipaa_block_editor_assets') );
            add_action( 'wp_enqueue_scripts', array( $this, 'hipaa_block_frontend') );
	}
   
	public static function is_version_less_than( $version, $version_to_compare ) {
		return version_compare( $version, $version_to_compare, '<' );
	}

	public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		global $wp_version;
		$is_block_categories_supported = $this->is_version_less_than( $wp_version, '5.8' );
		$block_categories_hook         = $is_block_categories_supported ? 'block_categories' : 'block_categories_all';
		
		add_filter( $block_categories_hook,  array( $this, 'hipaa_add_block_category' ) );
	}

	
	private function add( $hooks, $hook, $component, $callback, $priority, $accepted_args ) {

		$hooks[] = array(
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args
		);

		return $hooks;

	}

	/**
	 * Register the filters and actions with WordPress.
	 *
	 */
	public function run() {

		foreach ( $this->filters as $hook ) {
			add_filter( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
		}

		foreach ( $this->actions as $hook ) {
			add_action( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
		}

	}
	
	public function hipaa_add_block_category( $categories ) {
		return array_merge(
			$categories,
			array(
				array(
					'slug'  => 'hipaa-blockblock',
					'title' => __( 'Hipaatizer', 'hipaatizer' ),
				),
			)
		);
	}
	
    public function register_gutenberg_block() {
             if ( ! function_exists( 'register_block_type' ) ) {
                     return;
                 }
            register_block_type(
                'hipaatizer/hipaa-form',
                array(
                    'editor_script' => 'hipaa-form-block',
                )
            );
        }

    public function hipaa_block_editor_assets() {
        global $hipaaID;
        wp_enqueue_script(
            'hipaa-form-block',
            plugins_url( 'js/hipaa-form.js', __FILE__ ),
            array( 'wp-blocks', 'wp-element', 'wp-block-editor', )
        );

         wp_enqueue_style(
            'hipaa-form-block-editor',
            plugins_url( 'css/editor.css', __FILE__ ),
            array()
        );

	if( !empty( $hipaaID) ) :
        $curl = HIPAATIZER_APP.'/api/v1/account/'.$hipaaID.'/published_workflows';

        $url_connect = HIPAATIZER_APP.'/workflow/';

        $response  = wp_remote_get( $curl, array( 'timeout' => 15 ) );
        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return;
        }
        $body      = wp_remote_retrieve_body( $response );
        $res       = json_decode($body, true);
        $formsList = isset( $res['workflows'] ) && is_array( $res['workflows'] ) ? $res['workflows'] : array();

		// Gutenberg block selector should only see Published, non-HipaaSign workflows.
		// Match the dedicated fields directly instead of array_search over the whole record.
		$forms     = array();
		  foreach ($formsList as $item) :
				if ( ! is_array( $item ) ) continue;
				$item_status = isset( $item['status'] ) ? $item['status'] : '';
				$item_type   = isset( $item['type'] )   ? $item['type']   : '';
				if ( $item_status === 'Published' && $item_type !== 'HipaaSign' ) {
					$forms[] = $item;
				}
            endforeach;
      
        wp_localize_script( 'hipaa-form-block', 'hipaa_params', array( 
            'forms' => $forms,
            'url_connect' => $url_connect,
        ) );
		
	endif;
   }

 // Load assets for frontend
public function hipaa_block_frontend() {

   wp_enqueue_style(
        'hipaa-form-block-frontend',
        plugins_url( 'css/style.css', __FILE__ ),
        array()
    );
}

}



