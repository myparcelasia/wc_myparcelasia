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

            $i = 0;
            $length = "";
            $width = "";
            $height = "";
            
            $WC_MPA_Config = new MPA_Shipping_Config();
            $url = $WC_MPA_Config->sethost().'/check_price';
            $WC_MPA_Shipping_Method = new WC_MPA_Shipping_Method();

            foreach ($items as $item) {
                if (is_numeric($item['width']) && is_numeric($item['length']) && is_numeric($item['height'])) {
                    $length += $items[$i]['length'];
                    $width += $items[$i]['width'];
                    $height += $items[$i]['height'];
                }else{
                    $_POST['effective_weight'] = $_POST['declared_weight'];
                }                 
                $i++;
            }
                
            $f = '{
                    "api_key": "'.self::$integration_id.'",
                    "sender_postcode": "'.self::$sender_postcode.'",
                    "receiver_postcode": "'.$destination["postcode"].'",
                    "declared_weight": '.$weight.'
                }';

            if($WC_Country->get_base_country()=='MY' && $destination["country"] == 'MY'){
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $f);
                curl_setopt($ch, CURLOPT_HEADER, 0);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                ob_start();
                $r = curl_exec($ch);
                ob_end_clean();
                curl_close ($ch);
                $json = json_decode($r);
                // dd($url);
                if(sizeof($json->data->rates) > 0){
                
                    return $json->data->rates;
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