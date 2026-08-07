<?php
/*
Plugin Name: Evoliz
description: Evoliz integration for Woocommerce
Version: 0.21.0
Author: Evoliz
Author URI: https://www.evoliz.com/
Requires PHP: 8.0
*/

use Evoliz\Client\Config;

// Dependencies are namespace-prefixed into vendor-prefixed/ so that the third-party
// classes we bundle (Guzzle, PSR-7/17/18) cannot collide with another plugin's copy.
require_once __DIR__ . '/vendor-prefixed/autoload.php';

require_once 'EvolizSettings.php';
require_once 'Webhooks.php';
require_once 'includes/log.php';
require_once 'includes/update-manager.php';

EvolizSettings::init();
//throw
$options = get_option('evoliz_settings_credentials');
if (is_array($options) && $options['wc_evz_company_id'] != '' && $options['wc_evz_public_key'] != '' && $options['wc_evz_secret_key'] != '') {
    Webhooks::init(new Config((int) $options['wc_evz_company_id'], $options['wc_evz_public_key'], $options['wc_evz_secret_key']));
}
