# You have access to `$product` variable

### Get Product ID
```
$product->get_id();
```

### Get Product General Info
```
$product->get_type();
$product->get_name();
$product->get_slug();
$product->get_date_created();
$product->get_date_modified();
$product->get_status();
$product->get_featured();
$product->get_catalog_visibility();
$product->get_description();
$product->get_short_description();
$product->get_sku();
$product->get_menu_order();
$product->get_virtual();
get_permalink($product->get_id());
```

### Get Product Prices
```
$product->get_price();
$product->get_regular_price();
$product->get_sale_price();
$product->get_date_on_sale_from();
$product->get_date_on_sale_to();
$product->get_total_sales();
```

### Get Product Tax, Shipping & Stock
```
$product->get_tax_status();
$product->get_tax_class();
$product->get_manage_stock();
$product->get_stock_quantity();
$product->get_stock_status();
$product->get_backorders();
$product->get_sold_individually();
$product->get_purchase_note();
$product->get_shipping_class_id();
```

### Get Product Dimensions
```
$product->get_weight();
$product->get_length();
$product->get_width();
$product->get_height();
$product->get_dimensions();
```

### Get Linked Products
```
$product->get_upsell_ids();
$product->get_cross_sell_ids();
$product->get_parent_id();
```

### Get Product Variations and Attributes
```
$product->get_children(); // get variations
$product->get_attributes();
$product->get_default_attributes();
$product->get_attribute('attributeid'); //get specific attribute value
```

### Get Product Taxonomies
```
$product->get_categories();
$product->get_category_ids();
$product->get_tag_ids();
```

### Get Product Downloads
```
$product->get_downloads();
$product->get_download_expiry();
$product->get_downloadable();
$product->get_download_limit();
```

### Get Product Images
```
$product->get_image_id();
$product->get_image();
$product->get_gallery_image_ids();
```

### Get Product Reviews
```
$product->get_reviews_allowed();
$product->get_rating_counts();
$product->get_average_rating();
$product->get_review_count();
```

# You have access to `$product_id`

### Get `$product` object from product ID
```
$product = wc_get_product( $product_id );
```

### Now you have access to (see above)...
```
$product->get_type();
$product->get_name();
// etc.
// etc.
```

# You have access to the Order object or Order ID

### Get `$product` object from `$order` / `$order_id`
```
$order = wc_get_order($order_id);
$items = $order->get_items();

foreach ( $items as $item ) {
    $product = $item->get_product();
  
    // Now you have access to (see above)...
    
    $product->get_type();
    $product->get_name();
    // etc.
    // etc.
}
```

# You have access to the Cart object

### Get `$product` object from Cart object

```
$cart = WC()->cart->get_cart();

foreach($cart as $cart_item_key => $cart_item) {
    $product = $cart_item['data'];
  
    // Now you have access to (see above)...
    
    $product->get_type();
    $product->get_name();
    // etc.
    // etc.
}
```

# You have access to `$post` object

### Get `$product` object from `$post` object
```
$product = wc_get_product( $post );

// Now you have access to (see above)...

$product->get_type();
$product->get_name();
// etc.
// etc.
```
