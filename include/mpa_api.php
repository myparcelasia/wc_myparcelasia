<?php 
if ( ! class_exists( 'MPA_Shipping_API' ) ) {
    include 'mpa_config.php';
    class MPA_Shipping_API {

        private static $apikey = '';
        private static $apiSecret = '';
        private static $integration_id = '';
        private static $sender_postcode = '';
        private static $sender_state = '';
        private static $sender_country = '';

         /**
         * init
         *
         * @access public
         * @return void
         */
        public static function init() {

            $WC_MPA_Shipping_Method = new WC_MPA_Shipping_Method();
            self::$integration_id = $WC_MPA_Shipping_Method->settings['api_key'] ;
            self::$sender_postcode = $WC_MPA_Shipping_Method->settings['sender_postcode'] ;
            self::$sender_state = "" ;

        }

        public static function getShippingRate($destination,$items,$weight)
        {
          $WC_Country = new WC_Countries();
          if($WC_Country->get_base_country() == 'MY'){
            if($weight == 0 || $weight ==''){$weight=0.1;}
            
            $WC_MPA_Config = new MPA_Shipping_Config();
            $url = $WC_MPA_Config->sethost().'/check_price';
            $WC_MPA_Shipping_Method = new WC_MPA_Shipping_Method();
                
            $f = '{
                    "api_key": "'.self::$integration_id.'",
                    "sender_postcode": "'.self::$sender_postcode.'",
                    "receiver_postcode": "'.$destination["postcode"].'",
                    "declared_weight": '.$weight.'
                }';

            if($WC_Country->get_base_country()=='MY' && $destination["country"] == 'MY'){
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

                $rates_collection = json_decode($response['body'])->data->rates;
                $account = json_decode($response['body'])->topup;
                $r_rates = json_decode($response['body'])->data->rates;
                $r_rates['account'] = $account;
                if(sizeof($r_rates) > 0){                
                    return $r_rates;
                }

            } else {
                return array();
            }
        }

            // should never reach here
            return array(); // return empty array
        }
    }
}