<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option('evoliz_settings_credentials');
delete_option('wc_evz_enable_vat_number');
delete_option('wc_evz_eu_vat_number');
