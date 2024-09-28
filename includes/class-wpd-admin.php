<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @package WooCommerce_Product_Discounts
 */

class WPD_Admin
{
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_plugin_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_wpd_save_discount_rules', array($this, 'save_discount_rules'));
        add_action('wp_ajax_wpd_get_discount_rules', array($this, 'get_discount_rules'));
        add_action('wp_ajax_wpd_get_attribute_values', array($this, 'get_attribute_values'));
    }

    public function add_plugin_page()
    {
        add_submenu_page(
            'woocommerce',
            __('Product Discounts', 'woocommerce-product-discounts'),
            __('Product Discounts', 'woocommerce-product-discounts'),
            'manage_woocommerce',
            'wpd-product-discounts',
            array($this, 'create_admin_page')
        );
    }

    public function create_admin_page()
    {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <div id="wpd-admin-notices"></div>
            <div class="wpd-admin-container">
                <div class="wpd-add-rule-form">
                    <h2><?php _e('Add New Discount Rule', 'woocommerce-product-discounts'); ?></h2>
                    <form id="wpd-discount-rules-form">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="wpd-attribute"><?php _e('Attribute', 'woocommerce-product-discounts'); ?></label>
                                </th>
                                <td>
                                    <select name="attribute" id="wpd-attribute" class="wpd-select" required>
                                        <option value=""><?php _e('Select an attribute', 'woocommerce-product-discounts'); ?></option>
                                        <?php
                                        $attributes = wc_get_attribute_taxonomies();
                                        foreach ($attributes as $attribute) {
                                            echo '<option value="' . esc_attr($attribute->attribute_name) . '">' . esc_html($attribute->attribute_label) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="wpd-value"><?php _e('Value', 'woocommerce-product-discounts'); ?></label>
                                </th>
                                <td>
                                    <select name="value" id="wpd-value" class="wpd-select" required>
                                        <option value=""><?php _e('Select an attribute first', 'woocommerce-product-discounts'); ?>
                                        </option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="wpd-discount"><?php _e('Discount Amount ($)', 'woocommerce-product-discounts'); ?></label>
                                </th>
                                <td>
                                    <input type="number" name="discount" id="wpd-discount" class="wpd-input" step="0.01" min="0" required>
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <input type="submit" name="submit" id="wpd-submit" class="button button-primary"
                                value="<?php esc_attr_e('Add Rule', 'woocommerce-product-discounts'); ?>">
                        </p>
                    </form>
                </div>
                <div class="wpd-rules-list">
                    <h2><?php _e('Current Discount Rules', 'woocommerce-product-discounts'); ?></h2>
                    <div id="wpd-rules-table-container"></div>
                </div>
            </div>
        </div>
        <?php
    }

    public function enqueue_scripts($hook)
    {
        if ('woocommerce_page_wpd-product-discounts' !== $hook) {
            return;
        }

        wp_enqueue_style('wpd-admin-css', WPD_PLUGIN_URL . 'assets/css/admin.css', array(), WPD_VERSION);
        wp_enqueue_script('wpd-admin-js', WPD_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), WPD_VERSION, true);
        wp_localize_script('wpd-admin-js', 'wpd_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpd_nonce'),
        ));
    }

    public function get_attribute_values()
    {
        check_ajax_referer('wpd_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }

        $attribute = isset($_POST['attribute']) ? sanitize_text_field($_POST['attribute']) : '';

        if (empty($attribute)) {
            wp_send_json_error('No attribute specified');
        }

        $taxonomy = wc_attribute_taxonomy_name($attribute);
        $terms = get_terms(array(
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
        ));

        if (is_wp_error($terms)) {
            wp_send_json_error($terms->get_error_message());
        }

        $options = '<option value="">' . __('Select a value', 'woocommerce-product-discounts') . '</option>';
        foreach ($terms as $term) {
            $options .= sprintf('<option value="%s">%s</option>', esc_attr($term->slug), esc_html($term->name));
        }

        wp_send_json_success($options);
    }

    public function save_discount_rules()
    {
        check_ajax_referer('wpd_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('You do not have sufficient permissions to access this page.', 'woocommerce-product-discounts'));
        }

        $rules = isset($_POST['rules']) ? json_decode(stripslashes($_POST['rules']), true) : array();
        $sanitized_rules = array();

        foreach ($rules as $rule) {
            $sanitized_rules[] = array(
                'attribute' => sanitize_text_field($rule['attribute']),
                'value' => array(
                    'slug' => sanitize_text_field($rule['value']['slug']),
                    'label' => sanitize_text_field($rule['value']['label'])
                ),
                'discount' => floatval($rule['discount']),
            );
        }

        update_option('wpd_discount_rules', $sanitized_rules);

        wp_send_json_success(__('Discount rules saved successfully.', 'woocommerce-product-discounts'));
    }

    public function get_discount_rules()
    {
        check_ajax_referer('wpd_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('You do not have sufficient permissions to access this page.', 'woocommerce-product-discounts'));
        }

        $rules = get_option('wpd_discount_rules', array());

        wp_send_json_success($rules);
    }
}