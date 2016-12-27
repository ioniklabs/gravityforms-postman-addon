<?php
/*
Plugin Name: GravityForms Postman
Plugin URI: http://www.github.com/ioniklabs/gravityforms-postman-addon.git
Description: Map and post form data to a 3rd party after submission
Version: 1.0.0
Author: Ionik Labs
Author URI: http://www.ioniklabs.com
Text Domain: gravityformspostman
*/

define( 'GF_POSTMAN_ADDON_VERSION', '1.0.0' );

add_action( 'gform_loaded', array( 'GF_Postman_AddOn_Bootstrap', 'load' ), 5 );

class GF_Postman_AddOn_Bootstrap {

    public static function load() {

        if ( ! method_exists( 'GFForms', 'include_addon_framework' ) ) {
            return;
        }

        require_once( 'class-gf-postman-addon.php' );

        GFAddOn::register( 'GFPostmanAddOn' );
    }

}

function gf_acton_addon() {
    return GFPostmanAddOn::get_instance();
}