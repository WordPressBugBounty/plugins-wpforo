<?php

namespace wpforo\modules\license;

use gVectors\License\Config;

class LicenseConfig extends Config {
    public function get_proxy_server_url(): string {
        if( defined('GVECTORS_PROXY_URL') && GVECTORS_PROXY_URL ){
            return GVECTORS_PROXY_URL;
        }else{
            return parent::get_proxy_server_url();
        }
    }
    
    public function get_proxy_checkout_base_url(): string {
        if( defined('GVECTORS_PROXY_CHECKOUT_BASE_URL') && GVECTORS_PROXY_CHECKOUT_BASE_URL ){
            return GVECTORS_PROXY_CHECKOUT_BASE_URL;
        }else{
            return parent::get_proxy_checkout_base_url();
        }
    }
}
