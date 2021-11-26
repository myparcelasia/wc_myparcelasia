<?php
if ( ! defined( 'ABSPATH' ) ) { 
    exit; // Exit if accessed directly
}
/**
 * Check if WooCommerce is active
 */

    if ( ! class_exists( 'WC_MPA_Shipping_Method' ) ) {
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
            function init_form_fields() {
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
                ),'sender_postcode' => array(
                    'title'             => __( '<font color="red">*</font>Sender Postcode', 'myparcelasia' ),
                    'type'              => 'text',
                    'required'          => true
                ),'cust_rate'           => array(
                    'title'             => __( 'Display Shipping Rate', 'myparcelasia' ),
                    'type'              => 'select',
                    'description'       => __( "You may display different types of shipping rates on your checkout page:<br/><br/>
                                              1) Fixed rate: A fixed amount based on product weight.<br/>
                                              2) MyParcel Asia Member/Promo Rate: The rate you're enjoying right now. Eg: RM6 from 1kg<br/>
                                              3) MyParcel Asia Public Rate: Non-member rate. For eg: RM10.30 from 1 kg", 'myparcelasia' ),
                    'desc_tip'          => true,
                    'default'           => 'normal', 
                    'options'           => array( 'fix_rate'=>'Fixed Rate','member_rate'=>'MyParcel Asia Member Rate','normal_rate'=>'MyParcel Asia Public Rate'),
                ),'fix_rate'           => array(
                    'title'             => __( 'Fixed Rate (RM)', 'myparcelasia' ),
                    'type'              => 'text',
                    'description'       => __( 'Shipping rate (RM) for first 1KG', 'myparcelasia' ),
                    'desc_tip'          => false,
                    'default'           => '', 
                    'placeholder'       => 'RM 0.00'
                ),'fix_rate_above_1kg'  => array(
                    'type'              => 'text',
                    'description'       => __( 'Shipping rate (RM) for every additional KG', 'myparcelasia' ),
                    'desc_tip'          => false,
                    'default'           => '', 
                    'placeholder'       => 'RM 0.00'
                ),'courier_option'  => array(
                    'title'             => __( 'Display Courier Option', 'myparcelasia' ),
                    'type'              => 'multiselect',
                    'default'           => 'cheaper',
                    'options'           => array(
                        'cheaper' => 'Cheapest Courier(s)',
                        'all' => 'All Couriers',                        
                        'poslaju' => 'Poslaju National Courier',
                        'nationwide' => 'Nationwide Express Courier Service Berhad',
                        'dhle' => 'DHL eCommerce',
                        'jnt' => 'J&T Express',
                        'ninjavan' => 'Ninja Van',
                      )
                  ),
                
              
             );
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
                "height" => $this->defaultDimension( $this->dimensionToCm( $product->get_height() ) ),
                "width" => $this->defaultDimension( $this->dimensionToCm( $product->get_width() ) ),
                "length" => $this->defaultDimension( $this->dimensionToCm( $product->get_length() ) )
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
            foreach ($items as $item) {
              $weight += is_numeric($items[$i]['weight'])? $items[$i]['weight'] : 0;
              $i++;  
            }

            $WC_Country = new WC_Countries();
              
            $rates = MPA_Shipping_API::getShippingRate($destination, $items,$weight);
              
            $weight=ceil($weight);
            if($this->get_option( "courier_option" ) == 'cheaper') {
              $rates = $this->get_cheaper_rate($rates);
            }

            $groupped = array();
            foreach ($rates as $rate) {
              $groupped[$rate->provider_code][] = $rate;
            }

            foreach ($groupped as $cid => $services) {
              foreach ( $services as $rate ) {
                $courier_service_label = $rate->provider_label;

                $shipping_rate = array(
                  'id'      =>  $rate->provider_code,
                  'label'   =>  $courier_service_label." (".$weight."kg)",
                  'cost'    =>  $rate->exclusive_price
                );
                if($this->get_option( "courier_option" ) == 'all') {
                  // dd($this->get_option("cust_rate"));
                  if($this->get_option("cust_rate") == 'fix_rate') {
                    if($this->settings['fix_rate'] != '' || $this->settings['fix_rate_above_1kg'] != '') {
                      if($weight <= 1 ) {
                        $shipping_rate['cost'] = $this->settings['fix_rate'];
                      } elseif($weight > 1 ) {
                        $shipping_rate['cost'] = $this->settings['fix_rate_above_1kg'];

                        $fkg = $this->settings['fix_rate'];
                        $mkg = $this->settings['fix_rate_above_1kg'];
                        $mweight = $weight - 1 ;
                        $mPrice= $mkg * $mweight;
                        $shipping_rate['cost'] = $fkg + $mPrice;
                      }
                    }
                  }
                  $this->add_rate( $shipping_rate );
                }
                
                if($this->get_option( "courier_option" ) != 'cheaper') {
                  $providers = json_encode($this->settings["courier_option"]);
                  foreach($providers as $provider) {
                    dd($provider);
                  }

                  if($rate->provider_code == $this->get_option("courier_option")) {
                    if($this->get_option( "cust_rate" ) == 'fix_rate') { 
                      if($this->settings['fix_rate'] != '' || $this->settings['fix_rate_above_1kg'] != '') {
                        if($weight <= 1 ) {
                          $shipping_rate['cost'] = $this->settings['fix_rate'];
                        } elseif($weight > 1 ) {
                          $shipping_rate['cost'] = $this->settings['fix_rate_above_1kg'];

                          $fkg = $this->settings['fix_rate'];
                          $mkg = $this->settings['fix_rate_above_1kg'];
                          $mweight = $weight - 1 ;
                          $mPrice= $mkg * $mweight;
                          $shipping_rate['cost'] = $fkg + $mPrice;
                        }
                      }
                    }
                    // Register the rate
                    $this->add_rate( $shipping_rate );
                  }
                }elseif($this->get_option( "courier_option" ) == 'cheaper') {
                  if($this->get_option( "cust_rate" ) == 'fix_rate') { 
                    if($this->settings['fix_rate'] != '' || $this->settings['fix_rate_above_1kg'] != '') {
                      if($weight <= 1 ) {
                          $shipping_rate['cost'] = $this->settings['fix_rate'];
                      }elseif($weight > 1 ){
                        $shipping_rate['cost'] = $this->settings['fix_rate_above_1kg'];

                        $fkg = $this->settings['fix_rate'];
                        $mkg = $this->settings['fix_rate_above_1kg'];
                        $mweight = $weight - 1 ;
                        $mPrice= $mkg * $mweight;
                        $shipping_rate['cost'] = $fkg + $mPrice;
                      }
                    }
                  }
                  // Register the rate
                  $this->add_rate( $shipping_rate );
                } else {
                  $this->add_rate( $shipping_rate );
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


        /**
        * This function return default value for length
        *
        * @access protected
        * @param number
        * @return number
        */
        protected function defaultDimension( $length ) {
             // default dimension to 1 if it is 0
            // $length = double($length);
            return $length > 0 ? $length : 0.1;
        }

        /**** Price Display*************************/
       /**
         * This function is found the cheapeast Courier from MPA
         *
         * @access protected
         * @param array
         * @return array
         */
        protected function get_cheaper_rate($rates) {
          $prefer_rates = array();
          $lowest = 0;
          $index = 0;
          if ( empty( $rates ) ) {
              return $prefer_rates;
          }
          foreach ( $rates as $rate ) {
            $nowRate=$rates[$index]->exclusive_price;
            $bef4Rate=$rates[$lowest]->exclusive_price;

            if ($nowRate == $bef4Rate ) {
                  $lowest = $index;
                  $prefer_rates[$rates[$lowest]->provider_code] = $rates;
            } elseif ($nowRate < $bef4Rate){
                  $prefer_rates = array();
                  $lowest = $index;
                  $prefer_rates[$rates[$lowest]->provider_code] = $rates;
            }
            $index++;
            $prefer_rates[$rates[$lowest]->provider_code] = $rates[$lowest];
          }

          return $prefer_rates;
        }
      }
    }
  

  




