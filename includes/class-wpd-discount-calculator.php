<?php
/**
 * Handles the calculation and application of discounts.
 *
 * @package WooCommerce_Product_Discounts
 */

class WPD_Discount_Calculator
{
    private $debug_messages = array();
    private $user_messages = array();

    /**
     * Initialize the class and set its properties.
     */
    public function __construct()
    {
        add_action('woocommerce_before_calculate_totals', array($this, 'apply_discounts'), 10, 1);
        add_action('woocommerce_before_cart', array($this, 'display_debug_info'));
        add_action('woocommerce_before_checkout_form', array($this, 'display_debug_info'));

        add_action('woocommerce_before_cart', array($this, 'display_user_messages'));
        add_action('woocommerce_before_checkout_form', array($this, 'display_user_messages'));
    }

    /**
     * Apply discounts to cart items.
     *
     * @param WC_Cart $cart The cart object.
     */
    public function apply_discounts($cart)
    {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        if (did_action('woocommerce_before_calculate_totals') >= 2) {
            return;
        }

        $discount_rules = get_option('wpd_discount_rules', array());
        $this->debug_messages[] = 'Discount rules: ' . print_r($discount_rules, true);

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            $original_price = $product->get_price();
            $this->debug_messages[] = '-----------------------------------';
            $this->debug_messages[] = 'Processing product: ' . $product->get_name() . ' (ID: ' . $product->get_id() . ')';
            $this->debug_messages[] = 'Product type: ' . $product->get_type();
            $this->debug_messages[] = 'Original price: ' . $original_price;

            if ($product->is_type('variation')) {
                $parent_product = wc_get_product($product->get_parent_id());
                $this->debug_messages[] = 'Parent product: ' . $parent_product->get_name() . ' (ID: ' . $parent_product->get_id() . ')';
                $this->debug_messages[] = 'Variation attributes: ' . print_r($product->get_variation_attributes(), true);
            }

            $this->debug_messages[] = 'Product attributes: ' . print_r($product->get_attributes(), true);

            $discount_data = $this->calculate_discount($product, $discount_rules, $cart_item);

            $discount = $discount_data['total_discount'];
            $reasons = $discount_data['reasons'];

            $this->debug_messages[] = 'Calculated discount: ' . $discount;

            if ($discount > 0) {
                $new_price = $original_price - $discount;
                $product->set_price(max($new_price, 0));
                $this->debug_messages[] = 'New price set: ' . $product->get_price();

                $reason_text = implode(' and ', $reasons);
                $message = sprintf(
                    __('A discount of %s has been applied to %s because %s.', 'woocommerce'),
                    wc_price($discount),
                    $product->get_name(),
                    $reason_text
                );

                $this->user_messages[] = $message;
            } else {
                $this->debug_messages[] = 'No discount applied';
            }
            $this->debug_messages[] = '-----------------------------------';
        }
    }

    /**
     * Calculate the discount for a product based on the discount rules.
     *
     * @param WC_Product $product The product object.
     * @param array $discount_rules The discount rules.
     * @param array $cart_item The cart item data.
     * @return array An array containing the total discount amount and reasons.
     */
    private function calculate_discount($product, $discount_rules, $cart_item)
    {
        $total_discount = 0;
        $reasons = array();

        foreach ($discount_rules as $rule) {
            $attribute = $rule['attribute'];
            $value_slug = $rule['value']['slug'];
            $value_label = $rule['value']['label'];
            $discount = $rule['discount'];

            $this->debug_messages[] = "Checking rule - Attribute: $attribute, Value Slug: $value_slug, Value Label: $value_label, Discount: $discount";

            $attribute_key = 'attribute_pa_' . $attribute;
            $attribute_value = '';

            if ($product->is_type('variation')) {
                // Get attribute value from cart item variation data
                if (isset($cart_item['variation'][$attribute_key])) {
                    $attribute_value = $cart_item['variation'][$attribute_key];
                    $this->debug_messages[] = "Cart item variation attribute value for $attribute_key: $attribute_value";
                } else {
                    $this->debug_messages[] = "Attribute $attribute_key not found in cart item variation data";
                }
            } else {
                // For simple products, get attribute value from product attributes
                if ($product->has_attribute('pa_' . $attribute)) {
                    $attribute_value = $product->get_attribute('pa_' . $attribute);
                    $this->debug_messages[] = "Product attribute value for pa_$attribute: $attribute_value";
                } else {
                    $this->debug_messages[] = "Attribute pa_$attribute not found on product";
                }
            }

            if (!empty($attribute_value)) {
                if ($this->attribute_value_matches($attribute_value, $value_slug, $value_label)) {
                    $total_discount += $discount;
                    $attribute_label = $this->get_attribute_label($attribute);
                    $reasons[] = "the $attribute_label selected is $value_label";
                    $this->debug_messages[] = "Discount applied: $discount";
                } else {
                    $this->debug_messages[] = "No match found for attribute value";
                }
            } else {
                $this->debug_messages[] = "No attribute value found for $attribute_key";
            }
        }

        $this->debug_messages[] = "Total discount calculated: $total_discount";
        return array(
            'total_discount' => $total_discount,
            'reasons' => $reasons,
        );
    }

    /**
     * Get the label (human-readable name) of an attribute from its slug.
     *
     * @param string $attribute_slug The slug of the attribute.
     * @return string The label of the attribute.
     */
    private function get_attribute_label($attribute_slug)
    {
        global $wpdb;

        $taxonomy = 'pa_' . $attribute_slug;
        $attribute_label = ucfirst($attribute_slug); // Default to capitalized slug

        $attribute = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_name = %s",
            $attribute_slug
        ));

        if ($attribute) {
            $attribute_label = $attribute->attribute_label;
        }

        return $attribute_label;
    }

    private function attribute_value_matches($attribute_value, $rule_value_slug, $rule_value_label)
    {
        $attribute_values = explode(', ', $attribute_value);
        foreach ($attribute_values as $value) {
            if (
                strtolower($value) === strtolower($rule_value_label) ||
                strtolower($value) === strtolower($rule_value_slug)
            ) {
                $this->debug_messages[] = "Match found: '$value' matches either '$rule_value_slug' or '$rule_value_label'";
                return true;
            }
        }
        $this->debug_messages[] = "No match found between '$attribute_value' and '$rule_value_slug' or '$rule_value_label'";
        return false;
    }

    /**
     * Display debug information on the cart and checkout pages.
     */
    public function display_debug_info()
    {
        if (current_user_can('manage_options')) {
            echo '<div class="wpd-debug-info" style="background: #f0f0f0; border: 1px solid #ccc; padding: 10px; margin-bottom: 20px;">';
            echo '<h3>WooCommerce Product Discounts Debug Information</h3>';
            echo '<pre>' . esc_html(implode("\n", $this->debug_messages)) . '</pre>';
            echo '</div>';
        }
    }

    /**
     * Display user messages on the cart and checkout pages.
     */
    public function display_user_messages()
    {
        if (!empty($this->user_messages)) {
            foreach ($this->user_messages as $message) {
                wc_print_notice($message, 'notice');
            }
        }
    }
}
