<?php
/**
 * Main plugin class
 *
 * @package MO_Shipping_Integration
 * @since 2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main MO Shipping Integration Plugin Class
 */
class MO_Shipping_Plugin {

    /**
     * Plugin version
     *
     * @var string
     */
    public $version = '2.0';

    /**
     * Single instance of the class
     *
     * @var MO_Shipping_Plugin
     */
    protected static $_instance = null;

    /**
     * Main MO_Shipping_Plugin Instance
     *
     * Ensures only one instance of MO_Shipping_Plugin is loaded or can be loaded.
     *
     * @return MO_Shipping_Plugin - Main instance
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        $this->define_constants();
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Define plugin constants
     */
    private function define_constants() {
        $this->define('MO_SHIPPING_VERSION', $this->version);
        $this->define('MO_SHIPPING_PLUGIN_FILE', MO_SHIPPING_PLUGIN_FILE);
        $this->define('MO_SHIPPING_PLUGIN_DIR', MO_SHIPPING_PLUGIN_DIR);
        $this->define('MO_SHIPPING_PLUGIN_URL', MO_SHIPPING_PLUGIN_URL);
        $this->define('MO_SHIPPING_PLUGIN_BASENAME', MO_SHIPPING_PLUGIN_BASENAME);
    }

    /**
     * Define constant if not already set
     *
     * @param string $name
     * @param string|bool $value
     */
    private function define($name, $value) {
        if (!defined($name)) {
            define($name, $value);
        }
    }

    /**
     * Include required core files
     */
    public function includes() {
        // Include API handler
        require_once MO_SHIPPING_PLUGIN_DIR . 'includes/class-mo-shipping-api.php';
        
        // Include PDF handler
        require_once MO_SHIPPING_PLUGIN_DIR . 'includes/class-mo-shipping-pdf.php';
        
        // Include shipping method class
        require_once MO_SHIPPING_PLUGIN_DIR . 'includes/class-mo-shipping-method.php';
        
        // Include admin class
        if (is_admin()) {
            require_once MO_SHIPPING_PLUGIN_DIR . 'includes/class-mo-shipping-admin.php';
        }
    }

    /**
     * Hook into actions and filters
     */
    private function init_hooks() {
        add_action('init', array($this, 'init'), 0);
        add_action('plugins_loaded', array($this, 'plugins_loaded'));
    }

    /**
     * Init plugin when WordPress Initialises
     */
    public function init() {
        // Before init action
        do_action('mo_shipping_before_init');

        // Set up localisation
        $this->load_plugin_textdomain();

        // Initialize shipping method
        add_action('woocommerce_shipping_init', array($this, 'init_shipping_method'));
        add_filter('woocommerce_shipping_methods', array($this, 'add_shipping_method'));

        // Initialize admin functionality
        if (is_admin()) {
            new MO_Shipping_Admin();
        }

        // Initialize frontend functionality
        $this->init_frontend();

        // After init action
        do_action('mo_shipping_init');
    }

    /**
     * Initialize shipping method
     */
    public function init_shipping_method() {
        if (!class_exists('MO_Shipping_Method')) {
            require_once MO_SHIPPING_PLUGIN_DIR . 'includes/class-mo-shipping-method.php';
        }
    }

    /**
     * Add shipping method to WooCommerce
     *
     * @param array $methods
     * @return array
     */
    public function add_shipping_method($methods) {
        $methods['mo-shipping-integration'] = 'MO_Shipping_Method';
        return $methods;
    }

    /**
     * Initialize frontend functionality
     */
    private function init_frontend() {
        // Add shipping phone field to checkout
        add_filter('woocommerce_checkout_fields', array($this, 'add_shipping_phone_field'));
        
        // Add order actions for customers
        add_filter('woocommerce_my_account_my_orders_actions', array($this, 'add_order_actions'), 10, 2);
        add_action('woocommerce_after_account_orders', array($this, 'add_order_actions_script'));
    }

    /**
     * Add shipping phone field to checkout
     *
     * @param array $fields
     * @return array
     */
    public function add_shipping_phone_field($fields) {
        $fields['shipping']['shipping_phone'] = array(
            'label' => __('Phone', 'mo-shipping-integration'),
            'required' => true,
            'class' => array('form-row-wide'),
            'priority' => 25,
        );
        return $fields;
    }

    /**
     * Add order actions for customers
     *
     * @param array $actions
     * @param WC_Order $order
     * @return array
     */
    public function add_order_actions($actions, $order) {
        $awb = get_post_meta($order->get_order_number(), 'mo_shipping_awb_no', true);
        
        if (!empty($awb)) {
            $actions['mo_shipping_track_link'] = array(
                'url' => 'https://smsaexpress.com/trackingdetails?tracknumbers=' . $awb,
                'name' => __('Track Order', 'mo-shipping-integration'),
            );
        }
        
        return $actions;
    }

    /**
     * Add order actions script
     */
    public function add_order_actions_script() {
        ?>
        <script>
        jQuery(function($){
            $('a.mo_shipping_track_link').each(function(){
                $(this).attr('target','_blank');
            });
        });
        </script>
        <?php
    }

    /**
     * Load plugin text domain
     */
    public function load_plugin_textdomain() {
        $locale = is_admin() && function_exists('get_user_locale') ? get_user_locale() : get_locale();
        $locale = apply_filters('plugin_locale', $locale, 'mo-shipping-integration');

        unload_textdomain('mo-shipping-integration');
        load_textdomain('mo-shipping-integration', WP_LANG_DIR . '/mo-shipping-integration/mo-shipping-integration-' . $locale . '.mo');
        load_plugin_textdomain('mo-shipping-integration', false, plugin_basename(dirname(MO_SHIPPING_PLUGIN_FILE)) . '/languages');
    }

    /**
     * When WP has loaded all plugins, trigger the `mo_shipping_loaded` hook
     */
    public function plugins_loaded() {
        do_action('mo_shipping_loaded');
    }

    /**
     * Get the plugin url
     *
     * @return string
     */
    public function plugin_url() {
        return untrailingslashit(plugins_url('/', MO_SHIPPING_PLUGIN_FILE));
    }

    /**
     * Get the plugin path
     *
     * @return string
     */
    public function plugin_path() {
        return untrailingslashit(plugin_dir_path(MO_SHIPPING_PLUGIN_FILE));
    }

    /**
     * Get the template path
     *
     * @return string
     */
    public function template_path() {
        return apply_filters('mo_shipping_template_path', 'mo-shipping/');
    }

    /**
     * Get Ajax URL
     *
     * @return string
     */
    public function ajax_url() {
        return admin_url('admin-ajax.php', 'relative');
    }
}
