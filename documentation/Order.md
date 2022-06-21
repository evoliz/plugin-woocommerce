# You have access to `$order` variable

### Get Order ID and Key
```
$order->get_id();
$order->get_order_key();
```

### Get Order Totals `$0.00`
```
$order->get_formatted_order_total();
$order->get_cart_tax();
$order->get_currency();
$order->get_discount_tax();
$order->get_discount_to_display();
$order->get_discount_total();
$order->get_fees();
$order->get_formatted_line_subtotal();
$order->get_shipping_tax();
$order->get_shipping_total();
$order->get_subtotal();
$order->get_subtotal_to_display();
$order->get_tax_location();
$order->get_tax_totals();
$order->get_taxes();
$order->get_total();
$order->get_total_discount();
$order->get_total_tax();
$order->get_total_refunded();
$order->get_total_tax_refunded();
$order->get_total_shipping_refunded();
$order->get_item_count_refunded();
$order->get_total_qty_refunded();
$order->get_qty_refunded_for_item();
$order->get_total_refunded_for_item();
$order->get_tax_refunded_for_item();
$order->get_total_tax_refunded_by_rate_id();
$order->get_remaining_refund_amount();
```

### Get and Loop Over Order Items
```
foreach ( $order->get_items() as $item_id => $item ) {
$product_id = $item->get_product_id();
$variation_id = $item->get_variation_id();
$product = $item->get_product();
$product_name = $item->get_name();
$quantity = $item->get_quantity();
$subtotal = $item->get_subtotal();
$total = $item->get_total();
$tax = $item->get_subtotal_tax();
$taxclass = $item->get_tax_class();
$taxstat = $item->get_tax_status();
$allmeta = $item->get_meta_data();
$somemeta = $item->get_meta( '_whatever', true );
$product_type = $item->get_type();
}
```

### Other Secondary Items Stuff
```
$order->get_items_key();
$order->get_items_tax_classes();
$order->get_item_count();
$order->get_item_total();
$order->get_downloadable_items();
$order->get_coupon_codes();
```

### Get Order Lines
```
$order->get_line_subtotal();
$order->get_line_tax();
$order->get_line_total();
```

### Get Order Shipping
```
$order->get_shipping_method();
$order->get_shipping_methods();
$order->get_shipping_to_display();
```

### Get Order Dates
```
$order->get_date_created();
$order->get_date_modified();
$order->get_date_completed();
$order->get_date_paid();
```

### Get Order User, Billing & Shipping Addresses
```
$order->get_customer_id();
$order->get_user_id();
$order->get_user();
$order->get_customer_ip_address();
$order->get_customer_user_agent();
$order->get_created_via();
$order->get_customer_note();
$order->get_address_prop();
$order->get_billing_first_name();
$order->get_billing_last_name();
$order->get_billing_company();
$order->get_billing_address_1();
$order->get_billing_address_2();
$order->get_billing_city();
$order->get_billing_state();
$order->get_billing_postcode();
$order->get_billing_country();
$order->get_billing_email();
$order->get_billing_phone();
$order->get_shipping_first_name();
$order->get_shipping_last_name();
$order->get_shipping_company();
$order->get_shipping_address_1();
$order->get_shipping_address_2();
$order->get_shipping_city();
$order->get_shipping_state();
$order->get_shipping_postcode();
$order->get_shipping_country();
$order->get_address();
$order->get_shipping_address_map_url();
$order->get_formatted_billing_full_name();
$order->get_formatted_shipping_full_name();
$order->get_formatted_billing_address();
$order->get_formatted_shipping_address();
```

### Get Order Payment Details
```
$order->get_payment_method();
$order->get_payment_method_title();
$order->get_transaction_id();
```

### Get Order URLs
```
$order->get_checkout_payment_url();
$order->get_checkout_order_received_url();
$order->get_cancel_order_url();
$order->get_cancel_order_url_raw();
$order->get_cancel_endpoint();
$order->get_view_order_url();
$order->get_edit_order_url();
```

### Get Order Status
```
$order->get_status();
```

### Get Thank You Page URL
```
$order->get_checkout_order_received_url();
```

# You have access to `$order_id` variable

### Get `$order` object from order ID
```
$order = wc_get_order( $order_id );
```

### Now you have access to (see above)...
```
if ( $order ) {
$order->get_formatted_order_total( );
// etc.
// etc.
}
```

# You have access to `$email` variable

### Get `$order` object from `$email`
```
$order = $email->object;
```

### Now you have access to (see above)...
```
if ( $order ) {
$order->get_id();
$order->get_formatted_order_total( );
// etc.
// etc.
}
```
