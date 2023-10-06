<?php
/*
Plugin Name: Evoliz
description: Evoliz integration for Woocommerce
Version: 0.16.0
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
//throw
$options = get_option('evoliz_settings_credentials');
if (is_array($options) && $options['wc_evz_company_id'] != '' && $options['wc_evz_public_key'] != '' && $options['wc_evz_secret_key'] != '') {
    Webhooks::init(new Config((int) $options['wc_evz_company_id'], $options['wc_evz_public_key'], $options['wc_evz_secret_key']));
}
