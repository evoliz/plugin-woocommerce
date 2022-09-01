<?php
/*
Plugin Name: Evoliz
description: Evoliz integration for Woocommerce
Version: 0.5.0
Author: Evoliz
Author URI: https://www.evoliz.com/
*/

use Evoliz\Client\Config;

require 'vendor/autoload.php';

require_once 'EvolizSettings.php';
require_once 'Webhooks.php';
require_once 'includes/log.php';
require_once 'includes/update-manager.php';

EvolizSettings::init();

if (get_option('wc_evz_company_id') != '' && get_option('wc_evz_public_key') != '' && get_option('wc_evz_secret_key') != '') {
    Webhooks::init(new Config(get_option('wc_evz_company_id'), get_option('wc_evz_public_key'), get_option('wc_evz_secret_key')));
}
