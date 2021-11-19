<?php
/*
Plugin Name: MyParcel Asia
Plugin URI: https://app.myparcelasia.com/secure/integration_store
Description: MyParcel Asia plugin to enable courier and shipping rate to display in checkout page. To get started, activate MyParcel Asia plugin and then go to WooCommerce > Settings > Shipping > MyParcel Asia Shipping to set up your Integration ID.
Version: 1.x.x
Author: MyParcel Asia
Author URI: https://app.myparcelasia.com
WC requires at least: 3.0.0
WC tested up to: 4.1.0
*/
if ( ! defined( 'ABSPATH' ) ) { 
    exit; // Exit if accessed directly
}
/*
 * Check if WooCommerce is active
 */
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {


    if ( ! class_exists( 'WC_Integration_MPA' ) ) :

        class WC_Integration_MPA {

            /**
            * Construct the plugin.
            */
            public function __construct() {
                 add_action( 'woocommerce_shipping_init', array( $this, 'init' ) );
            }

            /**
            * Initialize the plugin.
            */
            public function init() {
                // start a session


                // Checks if WooCommerce is installed.
                if ( class_exists( 'WC_Integration' ) ) {
                    // Include our integration class.
                    include_once 'include/mpa_shipping.php';

                   // Register the integration.
                    add_filter( 'woocommerce_shipping_methods', array( $this, 'add_integration' ) );
                } else {
                    // throw an admin error if you like
                }
            }

            /**
             * Add a new integration to WooCommerce.
             */
            public function add_integration( $integrations ) {
                $integrations[] = 'WC_MPA_Shipping_Method';
                return $integrations;
            }

        }

        $WC_Integration_MPA = new WC_Integration_MPA( __FILE__ );

     endif;

}


?>