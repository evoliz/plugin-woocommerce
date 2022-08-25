<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option('wc_evz_company_id');
delete_option('wc_evz_public_key');
delete_option('wc_evz_secret_key');
delete_option('wc_evz_enable_vat_number');
delete_option('wc_evz_eu_vat_number');
