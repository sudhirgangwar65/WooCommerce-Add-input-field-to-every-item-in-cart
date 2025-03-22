// 1. Add input fields to cart items

add_action('woocommerce_after_cart_item_name', 'append_vin_input_fields', 10, 2);
function append_vin_input_fields($cart_item, $cart_item_key) {
    // Get the quantity of the item
    $quantity = isset($cart_item['quantity']) ? $cart_item['quantity'] : 1;
    ?>
    <div class="vin-input-container">
        <?php 
        // Loop through the quantity and display an input field for each instance
        for ($i = 1; $i <= $quantity; $i++) {
            // Check if there's a value saved for the VIN field and display it
            $vin_value = isset($cart_item['vin_field'][$i]) ? esc_attr($cart_item['vin_field'][$i]) : ''; 
            ?>
            <p>
                <input class="vin-input" type="text" name="vin_field[<?php echo esc_attr($cart_item_key); ?>][<?php echo $i; ?>]" 
                       value="<?php echo $vin_value; ?>" placeholder="VIN - Numéro d'identification du véhicule" maxlength="17" data-cart-key="<?= $cart_item_key; ?>" data-item-number="<?= $i; ?>">
            </p>
        <?php } ?>
    </div>
    <?php
}
// 2. Register AJAX handler
add_action('wp_footer', 'validate_vin_on_cart_page');
function validate_vin_on_cart_page() {
    if (is_cart()) { ?>
    <script>    
      jQuery(document).ready(function($) {
       $(document).on('keyup', '.vin-input', function() {
            var cart_key = $(this).data('cart-key');
            var item_number = $(this).data('item-number');
            var value = $(this).val();
            console.log("working");
            
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'save_custom_cart_input',
                    cart_key: cart_key,
                    item_number: item_number,
                    value: value,
                    security: '<?php echo wp_create_nonce('save_cart_input'); ?>'
                },
                success: function(response) {
                    console.log('Input saved:', response);
                },
                error: function() {
                    console.log('Error saving input');
                }
            });
        });
    });
  </script>
<?php  
    }
     }
// 3. Handle AJAX request

add_action('wp_ajax_save_custom_cart_input', 'save_custom_cart_input');
add_action('wp_ajax_nopriv_save_custom_cart_input', 'save_custom_cart_input');

function save_custom_cart_input() {

// Verify nonce
    check_ajax_referer('save_cart_input', 'security');

// Validate input
    if (!isset($_POST['cart_key'], $_POST['item_number'], $_POST['value'])) {
        wp_send_json_error(['message' => 'Invalid data']);
        wp_die();
    }

    $cart_key = sanitize_text_field($_POST['cart_key']);
    $item_number = intval($_POST['item_number']);
    $value = sanitize_text_field($_POST['value']);

// Get WooCommerce cart

    $cart = WC()->cart->get_cart();

   if (!isset($cart[$cart_key]['vin_field'])) {
        $cart[$cart_key]['vin_field'] = [];
    }

    // Save custom input data to cart item
    $cart[$cart_key]['vin_field'][$item_number] = $value;

    // Set updated cart item
    WC()->cart->cart_contents[$cart_key] = $cart[$cart_key];
    WC()->cart->set_session(); // Save the cart session
    WC()->cart->calculate_totals(); // Ensure cart updates properly

    wp_send_json_success(['message' => 'Saved successfully']);
    wp_die();

}


add_filter('woocommerce_get_cart_item_from_session', 'load_vin_from_session', 10, 2);
function load_vin_from_session($cart_item, $values) {
    if (isset($values['vin_field'])) {
        $cart_item['vin_field'] = $values['vin_field'];
    }
    return $cart_item;
}


add_action('woocommerce_checkout_create_order_line_item', 'save_vin_to_order_items', 10, 4);
function save_vin_to_order_items($item, $cart_item_key, $values, $order) {
    if (isset($values['vin_field']) && is_array($values['vin_field'])) {
        $vin_data = array_filter($values['vin_field']); // Remove empty values
        if (!empty($vin_data)) {
            $vin_string = implode(', ', $vin_data); // Convert array to a comma-separated string
            $item->add_meta_data('VIN #', $vin_string, true);
        }
    }
}
