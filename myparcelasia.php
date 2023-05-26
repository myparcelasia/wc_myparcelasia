<?php
/*
Plugin Name: MyParcel Asia
Plugin URI: https://app.myparcelasia.com/secure/integration_store
Description: MyParcel Asia plugin to enable courier and shipping rate to display in checkout page. To get started, activate MyParcel Asia plugin and then go to WooCommerce > Settings > Shipping > MyParcel Asia Shipping to set up your Integration ID.
Version: 1.4.0
Author: MyParcel Asia
Author URI: https://app.myparcelasia.com
Requires at least: at least 5.6
Wordpress tested up to: 6.2
Requires PHP: at least 7.4
PHP tested up to: 8.1.0
Requires WC: 7.3.0
WC tested up to: 7.5.1
*/
if ( ! defined( 'ABSPATH' ) ) { 
    exit; // Exit if accessed directly
}
/*
 * Check if WooCommerce is active
 */
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    if ( ! class_exists( 'WC_Integration_MPA' ) ) :

        require __DIR__.'/vendor/autoload.php';
        include 'include/mpa_config.php';
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
        if (getenv('WP_ENV') == 'development') {
            $dotenv->load();
        }

        class WC_Integration_MPA {
            private static $integration_id = '';

            /**
            * Construct the plugin.
            */
            public function __construct() {
                add_action( 'woocommerce_shipping_init', array( $this, 'init' ) );
                add_action( 'woocommerce_order_action_generate_connote_order', array( $this, 'generate_tracking_order'), 10, 1 );
                add_action( 'admin_head', 'add_custom_order_actions_button_css' );
                function add_custom_order_actions_button_css() {
                    echo '
                    <style>
                        .wc-action-button-connote::after {
                            font-family: woocommerce !important;
                        }
                        
                        .wc-action-button-thermal::after {
                            font-family: woocommerce !important;
                            content: "\e00a" !important;
                        }
                        
                        .wc-action-button-a4size::after {
                            font-family: woocommerce !important;
                            content: "\e02e" !important;
                        }
                    </style>
                    ';
                }
                
                add_action( 'admin_notices', array( $this, 'admin_notices' ) );
                
                add_action('admin_notices', function() {
                    if (!empty($_REQUEST['insufficient'])) {
                        $num_changed = (int) $_REQUEST['insufficient'];
                        printf('<div id="message" class="notice notice-error is-dismissible"><p>' . __('Insufficient balance for %d order.', 'txtdomain') . '</p></div>', $num_changed);
                    } else if (!empty($_REQUEST['missing_param'])) {
                        $num_changed = (int) $_REQUEST['missing_param'];
                        $link = $_REQUEST['missing_param'];
                        printf('<div id="message" class="notice notice-error is-dismissible"><p>' . __('Missing value to create shipment.', 'txtdomain') . '</p></div>', $num_changed);
                    } else if (!empty($_REQUEST['can_generate'])) {
                        $num_changed = (int) $_REQUEST['can_generate'];
                        $link = $_REQUEST['link'];
                        printf('<div id="message" class="notice notice-success is-dismissible"><p>' . __('Download %d connote PDF <a href="'.esc_url($link).'" target="_blank">here</a>.', 'txtdomain') . '</p></div>', $num_changed);
                    } else if (!empty($_REQUEST['general_msg'])) {
                        $num_changed = (int) $_REQUEST['general_msg'];
                        $link = $_REQUEST['general_msg'];
                        printf('<div id="message" class="notice notice-error is-dismissible"><p>' . __($link, 'txtdomain') . '</p></div>', $num_changed);
                    } else if (!empty($_REQUEST['no_track_list'])) {
                        $num_changed = (int) $_REQUEST['no_track_list'];
                        printf('<div id="message" class="notice notice-error is-dismissible"><p>' . __('No tracking number for %d order.', 'txtdomain') . '</p></div>', $num_changed);
                    }
                });
                
                $this->add_component();
            }

            /**
            * Initialize the plugin.
            */
            public function init() {
                // start a session
                // Checks if WooCommerce is installed.
                if ( class_exists( 'WC_Integration' ) ) {
                    // Include our integration class.
                    include 'include/mpa_shipping.php';
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

            public function add_component() {
                add_filter( 'woocommerce_order_actions', array( $this, 'add_generate_connote_order_action' ), 10, 1 );
                add_filter( 'bulk_actions-edit-shop_order', array( $this, 'add_generate_connote_order_action'), 20, 1 );
                add_filter( 'handle_bulk_actions-edit-shop_order', array( $this, 'bulk_generate_tracking_order'), 10, 3);
                add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'add_print_button') );
                add_action( 'woocommerce_admin_order_actions_end',  array( $this, 'add_print_bulk_button') );
            }
            
            public function add_print_button($order_id){
                $WC_MPA_Config = new MPA_Shipping_Config();
                $url    = esc_url($WC_MPA_Config->sethost().'/checkout');
                $print_connote   = esc_attr( __('Print Connote', 'woocommerce' ) );
                $class_connote  = esc_attr( 'connote' );
                $meta_value = get_post_custom_values('track_no');
                
                include 'include/mpa_shipping.php';
                $WC_MPA_Shipping_Method = new WC_MPA_Shipping_Method();
                $print_setting = $WC_MPA_Shipping_Method->settings['print_type'];
                if($meta_value) {
                    if($print_setting == 'thermal') {
                        printf( '<a class="button wc-action-button wc-action-button-%s %s" href="'.$WC_MPA_Config->sethost().'/print_thermal/'.$meta_value[0].'" title="%s" target="_blank">%s</a>', $class_connote, $class_connote, $url, $print_connote, $print_connote );
                    } else if($print_setting == 'a4_size') {
                        printf( '<a class="button wc-action-button wc-action-button-%s %s" href="'.$WC_MPA_Config->sethost().'/print/'.$meta_value[0].'" title="%s" target="_blank">%s</a>', $class_connote, $class_connote, $url, $print_connote, $print_connote );
                    }
                }
            }
            public function add_print_bulk_button($order_id){
                $WC_MPA_Config = new MPA_Shipping_Config();
                $url    = esc_url($WC_MPA_Config->sethost().'/checkout');
                $print_thermal   = esc_attr( __('Print Thermal', 'woocommerce' ) );
                $print_a4size   = esc_attr( __('Print A4 size', 'woocommerce' ) );
                $class_thermal  = esc_attr( 'thermal' );
                $class_a4size  = esc_attr( 'a4size' );
                $meta_value = get_post_custom_values('track_no');
                
                if($meta_value) {
                    printf( '<a class="button wc-action-button wc-action-button-%s %s" href="'.$WC_MPA_Config->sethost().'/print_thermal/'.$meta_value[0].'" data-tip="%s" title="%s" target="_blank">%s</a>', $class_thermal, $class_thermal, $url, $print_thermal, $print_thermal );
                    printf( '<a class="button wc-action-button wc-action-button-%s %s" href="'.$WC_MPA_Config->sethost().'/print/'.$meta_value[0].'" data-tip="%s" title="%s" target="_blank">%s</a>', $class_a4size, $class_a4size, $url, $print_a4size, $print_a4size );
                }
            }
            
            public function add_generate_connote_order_action( $actions ) {
                $actions['generate_connote_order'] = __( 'Download Connote PDF', 'woocommerce' );
                return $actions;
            }
            
            public function add_notice_already_generate( $location ) {
                remove_filter( 'redirect_post_location', array( $this, 'add_notice_already_generate' ), 99 );
                return add_query_arg( array( 'custom_msg' => 'already_generate' ), $location );
            }
            
            public function add_notice_new_generate( $location ) {
                remove_filter( 'redirect_post_location', array( $this, 'add_notice_new_generate' ), 99 );
                return add_query_arg( array( 'custom_msg' => 'new_generate' ), $location );
            }

            public function add_notice_insufficient_balance( $location ) {
                remove_filter( 'redirect_post_location', array( $this, 'add_notice_insufficient_balance' ), 99 );
                return add_query_arg( array( 'custom_msg' => 'insufficient_balance' ), $location );
            }

            public function add_notice_missing_param( $location ) {
                remove_filter( 'redirect_post_location', array( $this, 'add_notice_missing_param' ), 99 );
                return add_query_arg( array( 'custom_msg' => 'missing_param' ), $location );
            }
            
            public function add_notice_track_not_found( $location ) {
                remove_filter( 'redirect_post_location', array( $this, 'add_notice_track_not_found' ), 99 );
                return add_query_arg( array( 'custom_msg' => 'track_not_found' ), $location );
            }
            
            public function add_notice_not_success( $location ) {
                remove_filter( 'redirect_post_location', array( $this, 'add_notice_not_success' ), 99 );
                return add_query_arg( array( 'custom_msg' => 'not_success' ), $location );
            }

            public function admin_notices() {
                if(!empty($_GET)) {
                    if ( $_GET['custom_msg'] == 'already_generate' ) {
                        ?>
                        <div class="notice notice-info is-dismissible">
                        <p><?php esc_html_e( 'Connote already generate!', 'text-domain' ); ?></p>
                        </div>
                        <?php
                    } else if ( $_GET['custom_msg'] == 'new_generate' ) {
                        ?>
                        <div class="notice notice-success is-dismissible">
                        <p><?php esc_html_e( 'Connote successfully generate!', 'text-domain' ); ?></p>
                        </div>
                        <?php
                    } else if ( $_GET['custom_msg'] == 'insufficient_balance' ) {
                        ?>
                        <div class="notice notice-error is-dismissible">
                        <p><?php esc_html_e( 'Insufficient balance! Please topup.', 'text-domain' ); ?></p>
                        </div>
                        <?php
                    } else if ( $_GET['custom_msg'] == 'track_not_found' ) {
                        ?>
                        <div class="notice notice-error is-dismissible">
                        <p><?php esc_html_e( 'Tracking number not found! Please try again', 'text-domain' ); ?></p>
                        </div>
                        <?php
                    } else if ( $_GET['custom_msg'] == 'not_success' ) {
                        ?>
                        <div class="notice notice-error is-dismissible">
                        <p><?php esc_html_e( 'Something wrong happen. Please try again.', 'text-domain' ); ?></p>
                        </div>
                        <?php
                    }
                }
            }
            
            public function generate_tracking_order( $order ) {
                $WC_MPA_Config = new MPA_Shipping_Config();
                global $woocommerce,$wpdb;

                include 'include/mpa_shipping.php';
                $WC_MPA_Shipping_Method = new WC_MPA_Shipping_Method();
                self::$integration_id = $WC_MPA_Shipping_Method->settings['api_key'];
                $print_setting = $WC_MPA_Shipping_Method->settings['print_type'];
                $phone_number = $WC_MPA_Shipping_Method->settings['phone_number'];
                $send_method = $WC_MPA_Shipping_Method->settings['send_method'];

                $data = wc_get_order($order);
                $order_data = $data->get_data();
                $product_data = $data->get_items();
                $sender_details = WC()->countries;
                $sender_state = WC()->countries->get_states(WC()->countries->get_base_country())[WC()->countries->get_base_state()];

                foreach($order_data['line_items'] as $line_items) {
                    $description[] = $line_items['name'].' x'.$line_items['quantity'];
                }
                $content_description = implode(', ', $description);

                date_default_timezone_set("Asia/Kuala_Lumpur");
                if(date("H:i")>="11:45") {
                    $pickup_date = date("Y-m-d", strtotime('tomorrow'));
                } else {
                    $pickup_date = date("Y-m-d");
                }                

                foreach( $data->get_items( 'shipping' ) as $item_id => $item ){
                    preg_match('!\d+\.*\d*!', $item['name'], $matches);
                    $weight = (float)$matches[0];

                    switch (true) {
                        case strpos($item['name'], 'Citylink') !== false:
                            $provider_code = 'citylink';
                            break;
                        case strpos($item['name'], 'Flash') !== false:
                            $provider_code = 'flash';
                            break;
                        case strpos($item['name'], 'POSLaju') !== false:
                            $provider_code = 'poslaju';
                            break;
                        case strpos($item['name'], 'Nationwide') !== false:
                            $provider_code = 'nationwide';
                            break;
                        case strpos($item['name'], 'JnT') !== false:
                            $provider_code = 'jnt';
                            break;
                        case strpos($item['name'], 'DHL') !== false:
                            $provider_code = 'dhle';
                            break;
                        case strpos($item['name'], 'Ninjavan') !== false:
                            $provider_code = 'ninjavan';
                            break;
                        }
                }
                $postmeta_track_no = get_post_meta($order->id,'track_no', true);
                
                if($postmeta_track_no) {
                    if($_POST['wc_order_action'] == 'generate_connote_order' ){
                        add_filter( 'redirect_post_location', array( $this, 'add_notice_already_generate' ), 99 );
                    }
                } else {
                    $receiver_company_name = $order_data['billing']['company'];
                    $receiver_name = $order_data['billing']['first_name'].' '.$order_data['billing']['last_name'];
                    $receiver_phone = $order_data['billing']['phone'];
                    $receiver_email = $order_data['billing']['email'];
                    $receiver_address_line_1 = $order_data['billing']['address_1'];
                    $receiver_address_line_2 = $order_data['billing']['address_2'];
                    $receiver_city = $order_data['billing']['city'];
                    $receiver_state_code = $order_data['billing']['state'];
                    $receiver_postcode = $order_data['billing']['postcode'];
                    $receiver_country_code = $order_data['billing']['country'];

                    if($order_data['shipping']['first_name']) {
                        $receiver_company_name = $order_data['shipping']['company'];
                        $receiver_name = $order_data['shipping']['first_name'].' '.$order_data['shipping']['last_name'];
                        $receiver_address_line_1 = $order_data['shipping']['address_1'];
                        $receiver_address_line_2 = $order_data['shipping']['address_2'];
                        $receiver_city = $order_data['shipping']['city'];
                        $receiver_state_code = $order_data['shipping']['state'];
                        $receiver_postcode = $order_data['shipping']['postcode'];
                        $receiver_country_code = $order_data['shipping']['country'];
                        if($order_data['shipping']['phone']) {
                            $receiver_phone = $order_data['shipping']['phone'];
                        }
                    }
                    $receiver_state = WC()->countries->get_states($receiver_country_code)[$receiver_state_code];
                    $extract = array(
                        array(
                        "origin_channel"=> 'integration',
                        "integration_order_id"=> $order->id.'('.str_replace("wc_order_","",$order_data['order_key']).')',
                        "integration_order_label"=> $order_data['order_key'],
                        "integration_vendor"=> 'wc_plugin_single',
                        "send_method"=> $send_method,
                        "size"=>"flyers_s",
                        "declared_weight"=> $weight>0 ? $weight : 0.1,
                        "provider_code"=> $provider_code,
                        "type"=>"parcel",
                        "sender_company_name"=>get_bloginfo( 'name' ),
                        "sender_name"=> get_bloginfo( 'name' ),
                        "sender_phone"=> $phone_number,
                        "sender_email"=> get_bloginfo('admin_email'),
                        "sender_address_line_1"=>$sender_details->get_base_address(),
                        "sender_address_line_2"=>$sender_details->get_base_address_2(),
                        "sender_address_line_3"=> "",
                        "sender_address_line_4"=> "",
                        "sender_postcode"=> $sender_details->get_base_postcode(),
                        "sender_city"=> $sender_details->get_base_city(),
                        "sender_state_code"=> $sender_details->get_base_state(),
                        "sender_state"=> $sender_state,
                        "receiver_company_name"=> $receiver_company_name,
                        "receiver_name"=> $receiver_name,
                        "receiver_phone"=> $receiver_phone,
                        "receiver_email"=> $receiver_email,
                        "receiver_address_line_1"=> $receiver_address_line_1,
                        "receiver_address_line_2"=> $receiver_address_line_2,
                        "receiver_address_line_3"=> "",
                        "receiver_address_line_4"=> "",
                        "receiver_city"=> $receiver_city,
                        "receiver_state_code"=> $receiver_state_code,
                        "receiver_state"=> $receiver_state,
                        "receiver_postcode"=> $receiver_postcode,
                        "receiver_country_code"=> $receiver_country_code,
                        "content_description"=> $content_description,
                        "content_type"=> "others",
                        "declared_send_at"=> $pickup_date,
                        "send_date"=> $pickup_date,
                        "log"=> json_encode($order_data)
                        )
                    );
                    $body = array(
                        "api_key"=> self::$integration_id,
                        "shipments"=> $extract
                    );

                    $url    = esc_url($WC_MPA_Config->sethost().'/checkout');
                    $name   = esc_attr( __('Connote', 'woocommerce' ) );
                    $class  = esc_attr( 'connote' );

                    $response = wp_remote_post( $url, array(
                        'method'      => 'POST',
                        'timeout'     => 45,
                        'redirection' => 5,
                        'blocking'    => true,
                        'headers'     => array(),
                        'body'        => json_encode($body),
                        'cookies'     => array()
                        )
                    );
                    $error_msg = json_decode($response['body'])->message[0]->message;
                    if(strpos($error_msg, "Insufficient balance") !== false) {
                        add_filter( 'redirect_post_location', array( $this, 'add_notice_insufficient_balance' ), 99 );
                    } else if(strpos($error_msg, "already exist") !== false) {
                        $exist_tracking = json_decode($response['body'])->message[0]->tracking_no;
                        update_post_meta($order->id,'track_no', $exist_tracking);
                        add_filter( 'redirect_post_location', array( $this, 'add_notice_new_generate' ), 99 );
                    } else {
                        $result = json_decode($response['body']);
                        $message = $result->message;
                        if($message == "success") {
                            $success = $result->data;
        
                            $print_tracks = $success->tracking_no;
                            if($print_tracks){
                                update_post_meta($order->id,'track_no',$print_tracks[0]->tracking_no);
                                $order->add_order_note( __( 'Connote has been generated successfully.', 'woocommerce' ), false, true );
                                add_filter( 'redirect_post_location', array( $this, 'add_notice_new_generate' ), 99 );
                            } else {
                                add_filter( 'redirect_post_location', array( $this, 'add_notice_track_not_found' ), 99 );
                            }
                        } else {
                            add_filter( 'redirect_post_location', array( $this, 'add_notice_not_success' ), 99 );
                        }
                    }
                }
            }

            public function check_post_id($post_ids,$phone_number,$send_method) {                
                date_default_timezone_set("Asia/Kuala_Lumpur");
                if(date("H:i")>="11:45") {
                    $pickup_date = date("Y-m-d", strtotime('tomorrow'));
                } else {
                    $pickup_date = date("Y-m-d");
                }

                foreach ( $post_ids as $key=>$post_id ) {
                    //START: check condition tracking no exist
                    $postmeta_track_no = get_post_meta($post_id,'track_no', true);
                    if($postmeta_track_no) {
                        $list_track_no[] = $postmeta_track_no;
                    } else {
                        //for new order and order that missing track
                        $data = wc_get_order( $post_id );
                        $order_data = $data->get_data();
                        $product_data = $data->get_items();
                        $sender_details = WC()->countries;
                        $sender_state = WC()->countries->get_states(WC()->countries->get_base_country())[WC()->countries->get_base_state()];
                        foreach($order_data['line_items'] as $line_items) {
                            $description[] = $line_items['name'].' x'.$line_items['quantity'];
                        }
                        $content_description = implode(', ', $description);
                        foreach( $data->get_items( 'shipping' ) as $item_id => $item ){
                            preg_match('!\d+\.*\d*!', $item['name'], $matches);
                            $weight = (float)$matches[0];
                            switch (true) {
                                case str_contains($item['name'], 'Citylink'):
                                    $provider_code = 'citylink';
                                    break;
                                case str_contains($item['name'], 'Flash'):
                                    $provider_code = 'flash';
                                    break;
                                case str_contains($item['name'], 'POSLaju'):
                                    $provider_code = 'poslaju';
                                    break;
                                case str_contains($item['name'], 'Nationwide'):
                                    $provider_code = 'nationwide';
                                    break;
                                case str_contains($item['name'], 'JnT'):
                                    $provider_code = 'jnt';
                                    break;
                                case str_contains($item['name'], 'DHL'):
                                    $provider_code = 'dhle';
                                    break;
                                case str_contains($item['name'], 'Ninjavan'):
                                    $provider_code = 'ninjavan';
                                    break;
                                }
                        }
                        $receiver_company_name = $order_data['billing']['company'];
                        $receiver_name = $order_data['billing']['first_name'].' '.$order_data['billing']['last_name'];
                        $receiver_phone = $order_data['billing']['phone'];
                        $receiver_email = $order_data['billing']['email'];
                        $receiver_address_line_1 = $order_data['billing']['address_1'];
                        $receiver_address_line_2 = $order_data['billing']['address_2'];
                        $receiver_city = $order_data['billing']['city'];
                        $receiver_state_code = $order_data['billing']['state'];
                        $receiver_postcode = $order_data['billing']['postcode'];
                        $receiver_country_code = $order_data['billing']['country'];
                        if($order_data['shipping']['first_name']) {                            
                            $receiver_company_name = $order_data['shipping']['company'];
                            $receiver_name = $order_data['shipping']['first_name'].' '.$order_data['shipping']['last_name'];
                            $receiver_address_line_1 = $order_data['shipping']['address_1'];
                            $receiver_address_line_2 = $order_data['shipping']['address_2'];
                            $receiver_city = $order_data['shipping']['city'];
                            $receiver_state_code = $order_data['shipping']['state'];
                            $receiver_postcode = $order_data['shipping']['postcode'];
                            $receiver_country_code = $order_data['shipping']['country'];
                            if($order_data['shipping']['phone']) {
                                $receiver_phone = $order_data['shipping']['phone'];
                            }
                        }
                        $receiver_state = WC()->countries->get_states($receiver_country_code)[$receiver_state_code];
                        //if not yet create order
                        $extract[] =  array(
                                "origin_channel"=> 'integration',
                                "integration_order_id"=> $post_id.'('.str_replace("wc_order_","",$order_data['order_key']).')',
                                "integration_order_label"=> $order_data['order_key'],
                                "integration_vendor"=> 'wc_plugin_bulk',
                                "send_method"=> $send_method,
                                "size"=>"flyers_s",
                                "declared_weight"=> $weight>0 ? $weight : 0.1,
                                "provider_code"=> $provider_code,
                                "type"=>"parcel",
                                "sender_company_name"=>get_bloginfo( 'name' ),
                                "sender_name"=> get_bloginfo( 'name' ),
                                "sender_phone"=> $phone_number,
                                "sender_email"=> get_bloginfo('admin_email'),
                                "sender_address_line_1"=> $sender_details->get_base_address(),
                                "sender_address_line_2"=> $sender_details->get_base_address_2(),
                                "sender_address_line_3"=> "",
                                "sender_address_line_4"=> "",
                                "sender_postcode"=> $sender_details->get_base_postcode(),
                                "sender_city"=> $sender_details->get_base_city(),
                                "sender_state_code"=> $sender_details->get_base_state(),
                                "sender_state"=> $sender_state,
                                "receiver_company_name"=> $receiver_company_name,
                                "receiver_name"=> $receiver_name,
                                "receiver_phone"=> $receiver_phone,
                                "receiver_email"=> $receiver_email,
                                "receiver_address_line_1"=> $receiver_address_line_1,
                                "receiver_address_line_2"=> $receiver_address_line_2,
                                "receiver_address_line_3"=> "",
                                "receiver_address_line_4"=> "",
                                "receiver_city"=> $receiver_city,
                                "receiver_state_code"=> $receiver_state_code,
                                "receiver_state"=> $receiver_state,
                                "receiver_postcode"=> $receiver_postcode,
                                "receiver_country_code"=> $receiver_country_code,
                                "content_type"=> "others",
                                "content_description"=> $content_description,
                                "declared_send_at"=> $pickup_date,
                                "send_date"=> $pickup_date,
                                "log"=> json_encode($order_data)
                        );
                    }
                    //END: check condition tracking no exist
                }
                return array($list_track_no,$extract);
            }
            
            public function bulk_generate_tracking_order( $redirect_to, $action, $post_ids ) {
                // will add to make sure user install latest version
                // $plugin_data = get_plugin_data( __FILE__ );
                // if($plugin_data['Version'] == '1.3.0') {
                //     return;
                // }
                // dd('correct version');
                if ($action !== 'generate_connote_order') {
                    return $redirect_to;
                }
                $WC_MPA_Config = new MPA_Shipping_Config();
                global $woocommerce,$wpdb;
                include 'include/mpa_shipping.php';
                $WC_MPA_Shipping_Method = new WC_MPA_Shipping_Method();
                self::$integration_id = $WC_MPA_Shipping_Method->settings['api_key'];
                $print_setting = $WC_MPA_Shipping_Method->settings['print_type'];
                $phone_number = $WC_MPA_Shipping_Method->settings['phone_number'];
                $send_method = $WC_MPA_Shipping_Method->settings['send_method'];

                $check_post_id = $this->check_post_id($post_ids,$phone_number,$send_method);
                $list_track_no = $check_post_id[0];
                $extract = $check_post_id[1];
                if($extract) {
                    $body = array(
                        "api_key"=> self::$integration_id,
                        "shipments"=> $extract
                    );
                    $url    = esc_url($WC_MPA_Config->sethost().'/checkout');
                    $name   = esc_attr( __('Connote', 'woocommerce' ) );
                    $class  = esc_attr( 'connote' );
                    
                    $response = wp_remote_post( $url, array(
                        'method'      => 'POST',
                        'timeout'     => 1000,
                        'redirection' => 5,
                        'blocking'    => true,
                        'headers'     => array(),
                        'body'        => json_encode($body),
                        'cookies'     => array()
                        )
                    );
                    
                    $result = json_decode($response['body']);
                    $message = $result->message;
                    if($message == "success") { //for new cons only
                        $success = $result->data;
                        $print_tracks = $success->tracking_no;
                        if($print_tracks){
                            $count_track = count($list_track_no);
                            foreach( $print_tracks as $key=>$print_track ){
                                update_post_meta($post_ids[$key+$count_track],'track_no',$print_track->tracking_no);
                                $list_track_no[] = $print_track->tracking_no;
                            }
                        }
                        //cont to print
                    } else { //not success message
                        $error_msg = json_decode($response['body'])->message[0]->message;
                        if(strpos($error_msg, "Insufficient balance") !== false) {
                            $redirect_from= remove_query_arg(array('can_generate','link'), $redirect_to);
                            $redirect_url = add_query_arg('insufficient', count($post_ids), $redirect_from);
                            return $redirect_url; //exit if insufficient
                        }

                        $bulk_errors = json_decode($response['body'])->message;
                        foreach($bulk_errors as $key=>$bulk_error){
                            if(strpos($bulk_error, "already exist") !== false) {
                                update_post_meta($post_ids[$key],'track_no', $bulk_error->tracking_no);
                                $list_track_no[] = $bulk_error->tracking_no;
                                //if old and new cons together - only exist order return message - need workaround
                            } else {
                                if($error_msg) {
                                    $redirect_url = add_query_arg(array('general_msg'=>$error_msg), $redirect_to);
                                    return $redirect_url;
                                }
                            }
                        }
                    }
                }
                
                unset($extract);

                if(empty($list_track_no)){
                    $redirect_url = add_query_arg('no_track_list', count($post_ids), $redirect_from);                    
                    return $redirect_url;
                } else {
                    $WC_MPA_Config = new MPA_Shipping_Config();
                    if($print_setting == 'thermal') {
                        $link_to_print = $WC_MPA_Config->sethost().'/print_thermal/'.implode('-', $list_track_no); //later need to change to site_url
                    } else if($print_setting == 'a4_size') {
                        $link_to_print = $WC_MPA_Config->sethost().'/print/'.implode('-', $list_track_no); //later need to change to site_url
                    }

                    $redirect_from= remove_query_arg('insufficient', $redirect_to);
                    $redirect_url = add_query_arg(array('can_generate'=>count($post_ids),'link'=>$link_to_print), $redirect_from);
                    return $redirect_url;
                }
            }
        }
        $WC_Integration_MPA = new WC_Integration_MPA( __FILE__ );
     endif;
}
?>
