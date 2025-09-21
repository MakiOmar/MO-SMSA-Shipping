<?php
/**
 * MO Shipping Update Checker
 *
 * @package MO_Shipping_Integration
 * @since 2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * MO Shipping Update Checker Class
 */
class MO_Shipping_Updater {

    /**
     * Update checker instance
     *
     * @var object
     */
    private $update_checker;

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_update_checker();
    }

    /**
     * Initialize the update checker
     */
    private function init_update_checker() {
        // Include the update checker
        require_once MO_SHIPPING_PLUGIN_DIR . 'plugin-update-checker/plugin-update-checker.php';
        
        // Option 1: GitHub Integration
        $this->init_github_updater();
        
        // Option 2: Custom Update Server (uncomment to use)
        // $this->init_custom_updater();
    }

    /**
     * Initialize GitHub updater
     */
    private function init_github_updater() {
        $this->update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            'https://github.com/maki3omar/mo-shipping-integration', // Replace with your GitHub repo
            MO_SHIPPING_PLUGIN_FILE,
            'mo-shipping-integration'
        );
        
        // Set branch (optional)
        $this->update_checker->setBranch('master');
        
        // Add license validation (optional)
        $this->update_checker->addQueryArgFilter(array($this, 'add_license_to_request'));
        
        // Add custom headers (optional)
        $this->update_checker->addHttpRequestFilter(array($this, 'add_custom_headers'));
    }

    /**
     * Initialize custom update server
     */
    private function init_custom_updater() {
        $this->update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            'https://your-domain.com/mo-shipping-update-info.json', // Your custom update server
            MO_SHIPPING_PLUGIN_FILE,
            'mo-shipping-integration'
        );
        
        // Add license validation
        $this->update_checker->addQueryArgFilter(array($this, 'add_license_to_request'));
    }

    /**
     * Add license key to update requests
     *
     * @param array $queryArgs
     * @return array
     */
    public function add_license_to_request($queryArgs) {
        $license_key = get_option('mo_shipping_license_key', '');
        if (!empty($license_key)) {
            $queryArgs['license_key'] = $license_key;
        }
        
        // Add site URL for validation
        $queryArgs['site_url'] = home_url();
        
        return $queryArgs;
    }

    /**
     * Add custom headers to requests
     *
     * @param array $options
     * @return array
     */
    public function add_custom_headers($options) {
        if (!isset($options['headers'])) {
            $options['headers'] = array();
        }
        
        $options['headers']['X-MO-Shipping-Plugin'] = 'MO Shipping Integration';
        $options['headers']['X-Plugin-Version'] = MO_SHIPPING_VERSION;
        
        return $options;
    }

    /**
     * Get update checker instance
     *
     * @return object
     */
    public function get_update_checker() {
        return $this->update_checker;
    }
}
