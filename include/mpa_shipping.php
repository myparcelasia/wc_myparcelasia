<?php
if ( ! defined( 'ABSPATH' ) ) { 
    exit; // Exit if accessed directly
}
/**
 * Check if WooCommerce is active
 */

if ( ! class_exists( 'WC_MPA_Shipping_Method' ) ) {
  include 'mpa_config.php';
  class WC_MPA_Shipping_Method extends WC_Shipping_Method {

    /**
     * Constructor for your shipping class
     *
     * @access public
     * @return void
     */
    public function __construct() {
      $this->id                 = 'mpa'; // Id for your shipping method. Should be unique.
      $this->method_title       = __( 'MyParcel Asia Shipping ' );  // Title shown in admin
      $this->method_description = __( 'Allows buyer to choose for their favourite shipping method.' ); // Description shown in admin
      $this->title              = "MyParcel Asia Shipping"; // This can be added as an setting but for this example its forced.
      $this->init();
      
    }

    /**
     * Init your settings
     *
     * @access public
     * @return void
     */
    function init() {
      // Load the settings API
      $this->init_form_fields(); // This is part of the settings API. Override the method to add your own settings
      $this->init_settings(); // This is part of the settings API. Loads settings you previously init.
      // Save settings in admin if you have any defined
      add_action( 'admin_notices', array( $this, 'mpa_admin_notice' ) );
      add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
      }

    /**
     * Notification when api key and secret is not set
     *
     * @access public
     * @return void
     */
    public function mpa_admin_notice() {
    
        if ( !class_exists( 'MPA_Shipping_API' ) ){
                // Include MyParcel Asia API
                include_once 'mpa_api.php';
            }
    }
    /**
     * Initialise Gateway Settings Form Fields
     */
    //loading $this->init_form_fields();
    public function init_form_fields() {
      $WC_MPA_Config = new MPA_Shipping_Config();
      $url = $WC_MPA_Config->sethost()."/index";
      $f = '{
          "api_key": "'.$this->get_option("api_key").'"
      }';
      
      $response = wp_remote_post( $url, array(
        'method'      => 'POST',
        'timeout'     => 45,
        'redirection' => 5,
        'blocking'    => true,
        'headers'     => array(),
        'body'        => $f,
        'cookies'     => array()
        )
      );

      if(!is_wp_error($response)) {
        $meta = json_decode($response['body'])->meta;
        $currency = $meta->currency_label;
        $balance = $meta->topup_balance;
  
        $this->form_fields = array(
          'api_key' => array(
              'title'             => __( '<font color="red">*</font>API Key', 'myparcelasia' ),
              'type'              => 'text',
              'description'       => __( 'Here’s how to get api Key:<br/>
                                        1. Login your MyParcel Asia Account<br/>
                                        2. Click "Integration v1.2" - "API Docs" <br/>
                                        3. Choose " WooCommerce" <br/>
                                        4. Fill in required details <br/>
                                        5. Copy the API Key and paste it here.', 'myparcelasia' ),
              'desc_tip'          => true,
              'required'          => true
          ),
          'sender_postcode' => array(
              'title'             => __( '<font color="red">*</font>Sender Postcode', 'myparcelasia' ),
              'type'              => 'text',
              'required'          => true
          ),
          'citylink' => array(
              'title' => __( '<font color="red">*</font>Display Courier Option', 'myparcelasia' ),
              'label' => 'Citylink',
              'type' => 'checkbox',
              'default' => 'yes',
              'checkboxgroup'   => 'start',
          ),
          'flash' => array(
              'label' => 'Flash',
              'type' => 'checkbox',
              'default' => 'yes',
              'checkboxgroup'   => '',
          ),
          'poslaju' => array(
              'label' => 'Poslaju',
              'type' => 'checkbox',
              'default' => 'yes',
              'checkboxgroup'   => '',
          ),
          'nationwide' => array(
              'label' => 'Nationwide',
              'type' => 'checkbox',
              'default' => 'yes',
              'checkboxgroup'   => '',
          ),
          'dhle' => array(
              'label' => 'DHL',
              'type' => 'checkbox',
              'default' => 'yes',
              'checkboxgroup'   => '',
          ),
          'jnt' => array(
              'label' => 'J&T',
              'type' => 'checkbox',
              'default' => 'yes',
              'checkboxgroup'   => '',
          ),
          'ninjavan' => array(
              'label' => 'Ninjavan',
              'type' => 'checkbox',
              'default' => 'yes',
              'checkboxgroup'   => 'end',
          ),
          'phone_number'          => array(
              'title'             => __( '<font color="red">*</font>Phone Number', 'myparcelasia' ),
              'id'                => 'phone_number',
              'type'              => 'text',
              'required'          => true
          ),
          'send_method'           => array(
              'title'             => __( '<font color="red">*</font>Send Method', 'myparcelasia' ),
              'id'              => 'send_method',
              'type'              => 'select',
              'description'       => __( 'By choosing Drop Off, all providers send method will be Drop Off.', 'myparcelasia' ),
              'desc_tip'          => true,
              'default'           => 'dropoff',
              'options'           => array( 'dropoff'=>'Drop Off','pickup'=>'Pickup'),
              'required'          => true
          ),
          'print_type'           => array(
              'title'             => __( '<font color="red">*</font>Print Type', 'myparcelasia' ),
              'id'              => 'print_type',
              'type'              => 'select',
              'desc_tip'          => true,
              'default'           => 'a4_size',
              'options'           => array( 'a4_size'=>'A4 size','thermal'=>'Thermal size'),
              'required'          => true
          ),
          'topup_balance' => array(
            'title'       => __( 'Topup Balance (RM)', 'myparcelasia' ),
            'type'        => 'text',
            'placeholder' => __( $balance, 'woocommerce' ),
            'desc_tip'    => true,
            'custom_attributes' => array('readonly' => 'readonly'), // Enabling read only
          ),
        );
      } else {
        echo $response->get_error_message();
      }
    } // End init_form_fields()

    /**
     * calculate_shipping function.
     *
     * @access public
     * @param mixed $package
     * @return void
     */
    public function calculate_shipping( $package=array() ) {

      $destination = $package["destination"];

      $items = array();

      $product_factory = new WC_Product_Factory();
      
      foreach ( $package["contents"] as $key => $item ) {
        // default product - assume it is simple product
        $product = $product_factory->get_product( $item["product_id"] );
        $product_data = $product_factory->get_product( $item["data"] );
        $product_status=$item["data"]->get_type();
        // if this item is variation, get variation product instead
        if ($product_status == "variation" ) {
          $product = $product_factory->get_product( $item["variation_id"] );
        }
        for ( $i=0; $i < $item["quantity"]; $i++ ) {
          $items[] = array(
            "weight" => $this->weightToKg( $product->get_weight() ),
            "height" => $this->dimensionToCm( $product->get_height() ),
            "width" => $this->dimensionToCm( $product->get_width() ),
            "length" => $this->dimensionToCm( $product->get_length() )
          );
        }
      }

      if ( !class_exists( 'MPA_Shipping_API' ) ){
        // Include MPA API
        include_once 'mpa_api.php';
      }

      try {
        MPA_Shipping_API::init();
        $i=0;
        $weight=0;
        $volumetric=0;

        foreach ($items as $item) {      
          if (is_numeric($items[$i]['width']) && is_numeric($items[$i]['length']) && is_numeric($items[$i]['height'])) {
            $vol = $items[$i]['height'] * $items[$i]['width'] * $items[$i]['length'];
            $volumetric = number_format($vol / 5000, 2, '.', '');
          }
          if($volumetric > $items[$i]['weight']) {
            $weight += $volumetric;
          } else {
            $weight += $items[$i]['weight'];
          }
          $i++;
        }
        $WC_Country = new WC_Countries();
          
        // $weight=ceil($weight); //this feature can be enable if user want to round up the number
        $rates = MPA_Shipping_API::getShippingRate($destination, $items,$weight);
        $groupped = array();
        foreach ($rates as $rate) {
          $groupped[$rate->provider_code][] = $rate;
        }
        foreach ($groupped as $cid => $services) {
          foreach ( $services as $rate ) {
            $courier_service_label = $rate->provider_label;
            $price = $rates['account'] == 'exclusive' ? $rate->exclusive_price : $rate->normal_price;
            $shipping_rate = array(
              'id'      =>  $rate->provider_code,
              'label'   =>  $courier_service_label." (".$weight."kg)",
              'cost'    =>  $price
            );

            $couriers = $this->get_option("citylink") == 'yes'? ['citylink']: [];
            $couriers[] .= $this->get_option("flash") == 'yes'? 'flash': '';
            $couriers[] .= $this->get_option("poslaju") == 'yes'? 'poslaju': '';
            $couriers[] .= $this->get_option("nationwide") == 'yes'? 'nationwide': '';
            $couriers[] .= $this->get_option("dhle") == 'yes'? 'dhle': '';
            $couriers[] .= $this->get_option("jnt") == 'yes'? 'jnt': '';
            $couriers[] .= $this->get_option("ninjavan") == 'yes'? 'ninjavan': '';
            foreach($couriers as $courier) {
              if($rate->provider_code == $courier) {
                // Register the rate
                $this->add_rate( $shipping_rate );
              }
            }
          }
        }
      }
      catch( Exception $e ) {
        $message = sprintf( __( 'MPA Shipping Method is not set properly! Error: %s', 'myparcelasia' ),$e->getMessage() );

        $messageType = "error";
        wc_add_notice( $message, $messageType );
      }
    }

    /**
    * This function is convert dimension to cm
    *
    * @access protected
    * @param number
    * @return number
    */
    protected function dimensionToCm( $length ) {
        $dimension_unit = get_option('woocommerce_dimension_unit');
        // convert other units into cm
        // $length = double($length);
        if ( $dimension_unit != 'cm' ) {
            if ( $dimension_unit == 'm' ) {
                return $length * 100;
            }
            else if ( $dimension_unit == 'mm' ) {
                return $length * 0.1;
            }
            else if ( $dimension_unit == 'in' ) {
                return $length * 2.54;
            }
              else if ( $dimension_unit == 'yd' ) {
                return $length * 91.44;
            }
        }

        // already in cm
        return $length;
    }

    /**
      * This function is convert weight to kg
      *
      * @access protected
      * @param number
      * @return number
      */
    protected function weightToKg( $weight ) {
          $weight_unit = get_option( 'woocommerce_weight_unit' );
          // convert other unit into kg
          // $weight = double($weight);
            if ( $weight_unit != 'kg' ) {
              if ( $weight_unit == 'g')  {
                  return $weight * 0.001;
              }
              else if ( $weight_unit == 'lbs' ) {
                  return $weight * 0.453592;
              }
              else if ( $weight_unit == 'oz' ) {
                  return $weight * 0.0283495;
              }
            }

            // already kg
            return $weight;
    }
  }
}