<?php 
if ( ! class_exists( 'MPA_Shipping_Config' ) ) {
    class MPA_Shipping_Config {


        /**
         * set environment
         */
        public function sethost(){
            if ($_ENV['APP_URL'] && getenv('WP_ENV') == 'development') {
                return $_ENV['APP_URL'].'/api/v3';
            } else {
                return 'https://app.myparcelasia.com/api/v3';
            }
        }
    }
}