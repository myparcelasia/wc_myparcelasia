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

        require __DIR__.'/vendor/autoload.php';
        include 'include/mpa_config.php';
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
        $dotenv->load();

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
                $actions['generate_connote_order'] = __( 'Generate Connote Order', 'woocommerce' );
                return $actions;
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
                $user_data = $data->get_user();
                $product_data = $data->get_items();
                $sender_details = WC()->countries;

                foreach( $data->get_items( 'shipping' ) as $item_id => $item ){
                    $weight = (int) filter_var($item['name'], FILTER_SANITIZE_NUMBER_INT);

                    switch (true) {
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
                    //if already create connote
                    //should popup message connote already exist
                } else {
                    $extract = array(
                        array(
                        "integration_order_id"=> $order_data['order_key'],
                        "send_method"=> $send_method,
                        "size"=>"not box",
                        "declared_weight"=> $weight>0 ? $weight : 0.1,
                        "provider_code"=> $provider_code,
                        "declared_send_at"=> $order_data['date_created']->date('Y-m-d H:i:s'),
                        "type"=>"parcel",
                        "sender_company_name"=>get_bloginfo( 'name' ),
                        "sender_name"=> get_bloginfo( 'name' ),
                        "sender_phone"=> $phone_number,
                        "sender_email"=> get_bloginfo('admin_email'),
                        "sender_address_line_1"=>$sender_details->get_base_address(),
                        "sender_address_line_2"=>$sender_details->get_base_address_2(),
                        "sender_address_line_3"=>$sender_details->get_base_city(),
                        "sender_address_line_4"=>$sender_details->get_base_state(),
                        "sender_postcode"=> $sender_details->get_base_postcode(),
                        "receiver_company_name"=> $order_data['billing']['company'],
                        "receiver_name"=> $order_data['billing']['first_name'].' '.$order_data['billing']['last_name'],
                        "receiver_phone"=> $order_data['billing']['phone'],
                        "receiver_email"=> $order_data['billing']['email'],
                        "receiver_address_line_1"=> $order_data['billing']['address_1'],
                        "receiver_address_line_2"=> $order_data['billing']['address_1'],
                        "receiver_address_line_3"=> $order_data['billing']['city'],
                        "receiver_address_line_4"=> $order_data['billing']['state'],
                        "receiver_postcode"=> strtolower($order_data['billing']['postcode']),
                        "receiver_country_code"=> strtolower($order_data['billing']['country']),
                        "content_type"=> "others",
                        "send_date"=> $order_data['date_created']->date('Y-m-d H:i:s')
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
                    
                    if ( is_wp_error( $response ) ) {
                        $error_message = $response->get_error_message();
                        echo "Something went wrong: $error_message";
                    } else {
                        $result = json_decode($response['body'])->data;
                        $print_track = $result->tracking_no;
                        if($print_track){
                                update_post_meta($order->id,'track_no',$print_track[0]->tracking_no);
                        }
                    }
                }
                // Note the event.
                $order->add_order_note( __( 'Connote has been generated successfully.', 'woocommerce' ), false, true );
            
                do_action( 'woocommerce_after_resend_order_email', $order, 'new_order' );
            
                // Change the post saved message.
                // add_filter( 'redirect_post_location', array( 'WC_Meta_Box_Order_Actions', 'set_email_sent_message' ) );
            }
            
            public function bulk_generate_tracking_order( $redirect_to, $action, $post_ids ) {
                $WC_MPA_Config = new MPA_Shipping_Config();
                global $woocommerce,$wpdb;
                include 'include/mpa_shipping.php';
                $WC_MPA_Shipping_Method = new WC_MPA_Shipping_Method();
                self::$integration_id = $WC_MPA_Shipping_Method->settings['api_key'];
                $print_setting = $WC_MPA_Shipping_Method->settings['print_type'];
                $phone_number = $WC_MPA_Shipping_Method->settings['phone_number'];
                $send_method = $WC_MPA_Shipping_Method->settings['send_method'];

                foreach ( $post_ids as $key=>$post_id ) {
                    $data = wc_get_order( $post_id );
                    $order_data = $data->get_data();
                    $user_data = $data->get_user();
                    $product_data = $data->get_items();
                    $sender_details = WC()->countries;

                    foreach( $data->get_items( 'shipping' ) as $item_id => $item ){
                        $weight = (int) filter_var($item['name'], FILTER_SANITIZE_NUMBER_INT);
                        switch (true) {
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
                    $postmeta_track_no = get_post_meta($post_id,'track_no', true);

                    if($postmeta_track_no) {
                        //if already create connote
                        $list_track_no[] = $postmeta_track_no;
                    } else {
                        //if not yet create order
                        $extract = array(
                            array(
                                "integration_order_id"=> $order_data['order_key'],
                                "send_method"=> $send_method,
                                "size"=>"not box",
                                "declared_weight"=> $weight>0 ? $weight : 0.1,
                                "provider_code"=> $provider_code,
                                "declared_send_at"=> $order_data['date_created']->date('Y-m-d H:i:s'),
                                "type"=>"parcel",
                                "sender_company_name"=>get_bloginfo( 'name' ),
                                "sender_name"=> get_bloginfo( 'name' ),
                                "sender_phone"=> $phone_number,
                                "sender_email"=> get_bloginfo('admin_email'),
                                "sender_address_line_1"=> $sender_details->get_base_address(),
                                "sender_address_line_2"=> $sender_details->get_base_address_2(),
                                "sender_address_line_3"=> $sender_details->get_base_city(),
                                "sender_address_line_4"=> $sender_details->get_base_state(),
                                "sender_postcode"=> $sender_details->get_base_postcode(),
                                "receiver_company_name"=> $order_data['billing']['company'],
                                "receiver_name"=> $order_data['billing']['first_name'].' '.$order_data['billing']['last_name'],
                                "receiver_phone"=> $order_data['billing']['phone'],
                                "receiver_email"=> $order_data['billing']['email'],
                                "receiver_address_line_1"=> $order_data['billing']['address_1'],
                                "receiver_address_line_2"=> $order_data['billing']['address_2'],
                                "receiver_address_line_3"=> $order_data['billing']['city'],
                                "receiver_address_line_4"=> $order_data['billing']['state'],
                                "receiver_postcode"=> strtolower($order_data['billing']['postcode']),
                                "receiver_country_code"=> strtolower($order_data['billing']['country']),
                                "content_type"=> "others",
                                "send_date"=> $order_data['date_created']->date('Y-m-d H:i:s')
                            )
                        );
                        
                        // function _custom_order_action_process( $extract ) {               
                        //     if ( ! $extract ) {                
                        //         add_filter( 'redirect_post_location', 'redirect_post_location', 99 );                    }
                        //     if ( ! $extract ) {                
                        //         add_filter( 'redirect_post_location', 'redirect_post_location', 99 );                
                        //     }
                        
                        //     //here we go...
                        
                        // }
                        // add_action( 'woocommerce_order_action_custom_order_action','_custom_order_action_process' );
                        
                        // function redirect_post_location( $location ) {
                        //     remove_filter( 'redirect_post_location', __FUNCTION__, 99 ); // remove this filter so it will only work with your validations.
                        //     $location = add_query_arg('message', 99, $location); // 99 is empty message, it will not show. Or if by any chance it has a message, you change to higher number.
                        //     return $location;
                        // }

                        // dd('fail exit 212');
                        
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
                            dd($error_msg);
                            return $redirect_to;
                        } else if(strpos($error_msg, "already exist") !== false) {                            
                            dd($error_msg);
                            return $redirect_to;
                        } else {
                            $result = json_decode($response['body']);
                            $message = $result->message;
                            if($message == "success") {
                                $success = $result->data;
            
                                $print_track = $success->tracking_no;
                                if($print_track){
                                    // foreach($print_track as $key=>$value) {
                                    update_post_meta($post_id,'track_no',$print_track[0]->tracking_no);
                                    $list_track_no[] = $print_track[0]->tracking_no;
                                    // }
                                    // return $success->awb_url;
                                } else {
                                    dd('print track not found -'.$result);
                                    return $redirect_to;
                                }
                            } else {
                                dd('message not success -'.$result);
                                return $redirect_to;
                            }

                        }
                        
                    }
                }
                $WC_MPA_Config = new MPA_Shipping_Config();
                if($print_setting == 'thermal') {
                    return $WC_MPA_Config->sethost().'/print_thermal/'.implode('-', $list_track_no); //later need to change to site_url
                } else if($print_setting == 'a4_size') {
                    return $WC_MPA_Config->sethost().'/print/'.implode('-', $list_track_no); //later need to change to site_url
                }
            }
        }
        $WC_Integration_MPA = new WC_Integration_MPA( __FILE__ );
     endif;
}
?>