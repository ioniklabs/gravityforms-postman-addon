<?php

GFForms::include_feed_addon_framework();

class GFPostmanAddOn extends GFFeedAddOn {

	protected $_version = GF_POSTMAN_ADDON_VERSION;
	protected $_min_gravityforms_version = '1.9';
	protected $_slug = 'gravityforms-postman-addon';
	protected $_path = 'gravityforms-postman-addon/gravityforms-postman-addon.php';
	protected $_full_path = __FILE__;
	protected $_title = 'Gravity Forms Postman Add-On';
	protected $_short_title = 'Postman Add-On';

	private static $_instance = null;
	protected $_postdata = array();
	protected $_url = null;

	/**
	 * Get an instance of this class.
	 *
	 * @return GFPostmanAddOn
	 */
	public static function get_instance() {
		if ( self::$_instance == null ) {
			self::$_instance = new GFPostmanAddOn();
		}

		return self::$_instance;
	}

	protected function get_postdata() {
        return $this->_postdata;
    }

    protected function set_postdata( $key, $value ) {
        $this->_postdata[$key] = (string)$value;
    }

	protected function get_url() {
        return $this->_url;
    }

    protected function set_url( $value ) {
        $this->_url = (string)$value;
    }

	/**
	 * Handles hooks and loading of language files.
	 */
	public function init() {
		parent::init();
	}

	/**
	 * Configures which columns should be displayed on the feed list page.
	 *
	 * @return array
	 */
	public function feed_list_columns() {
		return array(
			'feedName'  => esc_html__( 'Name', 'gravityformspostman' ),
		);
	}

	public function process_feed( $feed, $entry, $form ) {
		$this->set_url( $feed['meta']['postman_target_url'] );
		$data = array(
				"_IPADDR " => $entry['ip'],
				"_REFERRER" => $entry['source_url']
			);

		$field_map = $this->get_dynamic_field_map_fields( $feed, 'postman_map' );
		
		$flat_map = array();
		foreach( $field_map as $key => $value ) {
			$flat_map[$value] = $key;
		}

		foreach ( $entry as $key => $value ) {
			if ( array_key_exists( $key, $flat_map ) ) {
				$data[$flat_map[$key]] = $value;
			}
		}

		foreach ( $data as $key => $value ) {
			$this->set_postdata( $key, $value );
		}

		$this->postman();

	}

	// # ADMIN FUNCTIONS -----------------------------------------------------------------------------------------------

	/**
	 * Creates a custom page for this add-on.
	 */
	public function plugin_page() {
		echo 'This page appears in the Forms menu';
	}

	/**
	 * Configures the settings which should be rendered on the Form Settings > Postman Add-On tab.
	 *
	 * @return array
	 */
	public function feed_settings_fields() {
		return array(
			array(
				'title'  => esc_html__( 'Postman Form Settings', 'gravityformspostman' ),
				'fields' => array(
					array(
						'label'   => esc_html__( 'Feed name', 'gravityformspostman' ),
						'type'    => 'text',
						'name'    => 'feedName',
						'tooltip' => esc_html__( 'A readable name to help you identify this rule', 'gravityformspostman' ),
						'class'   => 'small',
					),
					array(
						'label'             => esc_html__( 'Form Post URL', 'gravityformspostman' ),
						'type'              => 'text',
						'name'              => 'postman_target_url',
						'tooltip'           => esc_html__( 'Generate an External Form Post URLs in Postman. required', 'gravityformspostman' ),
						'class'             => 'large',
						'feedback_callback' => array( $this, 'is_valid_url' ),
					),
			        array(
			            'name'                => 'postman_map',
			            'label'               => esc_html__( 'Map Fields', 'gravityformspostman' ),
			            'type'                => 'dynamic_field_map',
			            'limit'               => 20,
			            'tooltip'             => esc_html__( 'Map Gravity Form fields to the respective Postman form field.', 'gravityformspostman' ),
			            'validation_callback' => array( $this, 'validate_custom_meta' ),
			        ),
					array(
						'name'       => 'condition',
						'label'      => esc_html__( 'Conditional Logic', 'gravityformspostman' ),
						'type'       => 'feed_condition',
						'tooltip'    => '<h6>' . esc_html__( 'Conditional Logic', 'gravityformspostman' ) . '</h6>' . esc_html__( 'When conditional logic is enabled, form submissions will only be sent when the condition is met. When disabled all form submissions will be sent.', 'gravityformspostman' ),
					),
				),
			),
		);

	}

	// # HELPERS -------------------------------------------------------------------------------------------------------

	/**
	 * validate url used for form post url
	 *
	 * @param string $value The setting value.
	 *
	 * @return bool
	 */
	public function is_valid_url( $value ) {
		return filter_var( $value, FILTER_VALIDATE_URL ) !== false;
	}

    protected function get_domain( $address ) {

        $pieces = parse_url($address);
        $domain = isset($pieces['host']) ? $pieces['host'] : '';

        if (preg_match('/(?P<domain>[a-z0-9][a-z0-9\-]{1,63}\.[a-z\.]{2,6})$/i', $domain, $regs)) {
            return $regs['domain'];
        }

        return false;

    }

	public function validate_custom_meta( $field ) {

		$settings = $this->get_posted_settings();
		$postman_map = $settings['postman_map'];

		if ( empty( $postman_map ) ) {
			return;
		}

		$map_count = count( $postman_map );
		if ( $map_count > 99 ) {
			$this->set_field_error( array( esc_html__( 'You may only have 99 custom keys.' ), 'gravityformspostman' ) );
			return;
		}

		foreach ( $postman_map as $meta ) {
			if ( empty( $meta['custom_key'] ) && ! empty( $meta['value'] ) ) {
				$this->set_field_error( array( 'name' => 'postman_map' ), esc_html__( "A field has been mapped to a custom key without a name. Please enter a name for the custom key, remove the metadata item, or return the corresponding drop down to 'Select a Field'.", 'gravityformspostman' ) );
				break;
			}
		}

	}

	public function postman() {

		$form_post_url = $this->get_url();
		$form_data = $this->get_postdata();
		$form_data['sp_exp'] = 'yes';

		if ( !$form_post_url || count( $form_data ) == 0 ) return false;

		$fields = http_build_query( $form_data );
		$handle = curl_init();

		curl_setopt($handle, CURLOPT_POST, 1);
		curl_setopt($handle, CURLOPT_URL, "$form_post_url");
		curl_setopt($handle, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
		curl_setopt($handle, CURLOPT_HEADER, 1);
		curl_setopt($handle, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($handle, CURLOPT_RETURNTRANSFER, 1);
    	curl_setopt($handle, CURLOPT_POST, 1);
		curl_setopt($handle, CURLOPT_POSTFIELDS, $fields);

		$response = curl_exec($handle);

		$success = true;

		if ($response === FALSE) {

			$success = false;

		} else {

			/**
			 * validation stuff to come
			 */

		}

		curl_close($handle);

		return $success;

    }

}