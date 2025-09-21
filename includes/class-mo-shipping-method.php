<?php
/**
 * MO Shipping Method Class
 *
 * @package MO_Shipping_Integration
 * @since 2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * MO Shipping Method Class
 */
class MO_Shipping_Method extends WC_Shipping_Method {

    /**
     * Constructor
     */
    public function __construct() {
        $this->id = 'mo-shipping-integration';
        $this->method_title = __('MO Shipping Integration', 'mo-shipping-integration');
        $this->method_description = $this->get_method_description();
        
        $this->init();
        
        $this->enabled = isset($this->settings['enabled']) ? $this->settings['enabled'] : 'yes';
    }

    /**
     * Get method description
     *
     * @return string
     */
    public function get_method_description() {
        return sprintf(
            '<h3>%s</h3><br>%s<br>%s<br>%s<br><h3>%s</h3>%s<br>%s<br>%s',
            __('MO SHIPPING PLUGIN INSTALLATION GUIDE', 'mo-shipping-integration'),
            __('MO Shipping Integration Plugin requires a valid SMSA account number, username, and password.', 'mo-shipping-integration'),
            sprintf(__('Please send us an email at %s to have these credentials created and sent to you.', 'mo-shipping-integration'), 'maki3omar@gmail.com'),
            sprintf(__('If you don\'t have an account number, please send us an email at %s to have your account number created.', 'mo-shipping-integration'), 'maki3omar@gmail.com'),
            __('تعليمات التحميل لتطبيق شحن MO', 'mo-shipping-integration'),
            __('يتطلب هذا التطبيق إدخال رقم حساب فعًال, إسم المستخدم و الرقم السري الخاص بحسابكم.', 'mo-shipping-integration'),
            sprintf(__('يرجى مراسلتنا على الإيميل أدناه ليتم تزويدكم ببيانات الحساب والرقم السري. %s', 'mo-shipping-integration'), 'maki3omar@gmail.com'),
            sprintf(__('إذا لا يوجد لديكم رقم حساب, يرجى مراسلتنا عبر الإيميل أدناه ليتم إنشاء رقم حسابكم لدى سمسا إكسبريس. %s', 'mo-shipping-integration'), 'maki3omar@gmail.com')
        );
    }

    /**
     * Initialize settings
     */
    public function init() {
        $this->init_form_fields();
        $this->init_settings();

        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    }

    /**
     * Initialize form fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'mo_shipping_account_no' => array(
                'title' => __('SMSA Account Number', 'mo-shipping-integration'),
                'type' => 'password',
                'description' => __('Enter SMSA Account Number', 'mo-shipping-integration'),
                'desc_tip' => true,
                'default' => '',
                'css' => 'width:170px;',
            ),
            'mo_shipping_username' => array(
                'title' => __('SMSA Username', 'mo-shipping-integration'),
                'type' => 'password',
                'description' => __('Enter SMSA Username', 'mo-shipping-integration'),
                'desc_tip' => true,
                'default' => '',
                'css' => 'width:170px;',
            ),
            'mo_shipping_password' => array(
                'title' => __('SMSA Password', 'mo-shipping-integration'),
                'type' => 'password',
                'description' => __('Enter SMSA password', 'mo-shipping-integration'),
                'desc_tip' => true,
                'default' => '',
                'css' => 'width:170px;',
            ),
            'store_phone' => array(
                'title' => __('Store Phone Number', 'mo-shipping-integration'),
                'type' => 'number',
                'description' => __('Enter Phone number', 'mo-shipping-integration'),
                'desc_tip' => true,
                'default' => '',
                'css' => 'width:170px;',
            ),
        );
    }

    /**
     * Process admin options with validation
     *
     * @return bool
     */
    public function process_admin_options() {
        if (!current_user_can('manage_woocommerce')) {
            return false;
        }

        $account = sanitize_text_field($_POST['woocommerce_mo-shipping-integration_mo_shipping_account_no'] ?? '');
        $username = sanitize_text_field($_POST['woocommerce_mo-shipping-integration_mo_shipping_username'] ?? '');
        $password = sanitize_text_field($_POST['woocommerce_mo-shipping-integration_mo_shipping_password'] ?? '');
        $phone = sanitize_text_field($_POST['woocommerce_mo-shipping-integration_store_phone'] ?? '');

        if (empty($account) || empty($username) || empty($password) || empty($phone)) {
            WC_Admin_Settings::add_error(__('Please fill all the fields and try again.', 'mo-shipping-integration'));
            return false;
        }

        // Validate credentials with SMSA API
        $api = new MO_Shipping_API();
        if (!$api->validate_credentials()) {
            WC_Admin_Settings::add_error(__('Please check your credentials and try again.', 'mo-shipping-integration'));
            return false;
        }

        return parent::process_admin_options();
    }

    /**
     * Calculate shipping
     *
     * @param array $package
     */
    public function calculate_shipping($package = array()) {
        // This method can be extended to calculate shipping rates
        // For now, it's a placeholder for future functionality
    }
}
