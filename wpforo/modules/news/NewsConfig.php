<?php

namespace wpforo\modules\news;

use gVectors\News\Config;

class NewsConfig extends Config {
    public function get_proxy_server_url(): string {
        if( defined('GVECTORS_PROXY_URL') && GVECTORS_PROXY_URL ){
            return GVECTORS_PROXY_URL;
        }else{
            return parent::get_proxy_server_url();
        }
    }
}
