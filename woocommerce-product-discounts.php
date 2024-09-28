<?php
/**
 * Plugin Name: WooCommerce Product Discounts
 * Plugin URI: https://spectaclesoftware.com/woocommerce-product-discounts
 * Description: Apply discount rules on WooCommerce products based on specific attributes and variations.
 * Version: 1.0.0
 * Author: Spectacle Software
 * Author URI: https://spectaclesoftware.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: woocommerce-product-discounts
 * Domain Path: /languages
 *
 * WC requires at least: 3.0.0
 * WC tested up to: 8.0.0
 *
 * @package WooCommerce_Product_Discounts
 */

if (!defined('WPINC')) {
    die;
}

define('WPD_VERSION', '1.0.0');
define('WPD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPD_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once WPD_PLUGIN_DIR . 'includes/class-wpd-admin.php';
require_once WPD_PLUGIN_DIR . 'includes/class-wpd-discount-calculator.php';

/**
 * Main class for WooCommerce Product Discounts plugin.
 */
class WooCommerce_Product_Discounts
{
    /**
     * Single instance of the class.
     *
     * @var WooCommerce_Product_Discounts
     */
    protected static $_instance = null;

    /**
     * Ensures only one instance of the class is loaded.
     *
     * @return WooCommerce_Product_Discounts Singleton instance.
     */
    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor to initialize hooks and dependencies.
     */
    public function __construct()
    {
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks.
     */
    private function init_hooks()
    {
        add_action('plugins_loaded', [$this, 'check_dependencies']);
        add_action('init', [$this, 'load_plugin_textdomain']);

        if (is_admin()) {
            new WPD_Admin();
        }
        new WPD_Discount_Calculator();

        add_action('wp_footer', [$this, 'add_plugin_indicator']);
    }

    /**
     * Adds a visual indicator for administrators when the plugin is active.
     */
    public function add_plugin_indicator()
    {
        if (current_user_can('manage_options')) {
            echo '<div style="position: fixed; bottom: 10px; right: 10px; background: #007cba; color: white; padding: 5px 10px; border-radius: 5px; font-size: 12px;">WooCommerce Product Discounts Active</div>';
        }
    }

    /**
     * Verifies that WooCommerce is active. Shows admin notice if not.
     */
    public function check_dependencies()
    {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', [$this, 'woocommerce_missing_notice']);
        }
    }

    /**
     * Displays a notice when WooCommerce is not active.
     */
    public function woocommerce_missing_notice()
    {
        ?>
        <div class="error">
            <p><?php esc_html_e('WooCommerce Product Discounts requires WooCommerce to be installed and active.', 'woocommerce-product-discounts'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Loads the plugin text domain for translations.
     */
    public function load_plugin_textdomain()
    {
        load_plugin_textdomain(
            'woocommerce-product-discounts',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages/'
        );
    }
}

/**
 * Retrieves the main instance of the plugin.
 *
 * @return WooCommerce_Product_Discounts Main instance of the plugin.
 */
function WPD()
{
    return WooCommerce_Product_Discounts::instance();
}

// Global instance for backward compatibility.
$GLOBALS['woocommerce_product_discounts'] = WPD();