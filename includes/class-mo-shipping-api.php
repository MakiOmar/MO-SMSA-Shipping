<?php
/**
 * SMSA API Handler
 *
 * @package MO_Shipping_Integration
 * @since 2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * MO Shipping API Handler Class
 */
class MO_Shipping_API {

    /**
     * API Base URL
     *
     * @var string
     */
    private $api_base_url = 'https://smsaopenapis.azurewebsites.net/api';

    /**
     * Settings
     *
     * @var array
     */
    private $settings;

    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = get_option('woocommerce_mo-shipping-integration_settings', array());
    }

    /**
     * Get authentication token
     *
     * @return string|false
     */
    public function get_token() {
        if (empty($this->settings['mo_shipping_account_no']) || 
            empty($this->settings['mo_shipping_username']) || 
            empty($this->settings['mo_shipping_password'])) {
            return false;
        }

        $body = array(
            'accountNumber' => $this->settings['mo_shipping_account_no'],
            'username' => $this->settings['mo_shipping_username'],
            'password' => $this->settings['mo_shipping_password'],
        );

        $args = array(
            'body' => wp_json_encode($body),
            'timeout' => 30,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => array(
                'Content-Type' => 'application/json; charset=utf-8'
            ),
            'cookies' => array(),
        );

        $response = wp_remote_post($this->api_base_url . '/Token', $args);

        if (is_wp_error($response)) {
            error_log('MO Shipping: API request failed - ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            error_log('MO Shipping: API returned error code ' . $response_code . ' - ' . $body);
            return false;
        }

        $data = json_decode($body);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('MO Shipping: Failed to decode JSON response - ' . json_last_error_msg());
            return false;
        }

        if (!isset($data->token) || empty($data->token)) {
            error_log('MO Shipping: No token in API response - ' . $body);
            return false;
        }

        return $data->token;
    }

    /**
     * Create shipment
     *
     * @param array $shipment_data
     * @return array|false
     */
    public function create_shipment($shipment_data) {
        $token = $this->get_token();
        if (!$token) {
            return false;
        }

        $args = array(
            'body' => wp_json_encode($shipment_data),
            'timeout' => 30,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => array(
                'Content-Type' => 'application/json; charset=utf-8',
                'Authorization' => 'Bearer ' . $token
            ),
            'cookies' => array(),
        );

        $response = wp_remote_post($this->api_base_url . '/Shipment/B2CNewShipment', $args);

        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }

    /**
     * Track shipment
     *
     * @param string $awb_number
     * @param string $language
     * @return array|false
     */
    public function track_shipment($awb_number, $language = 'EN') {
        $token = $this->get_token();
        if (!$token) {
            return false;
        }

        $url = $this->api_base_url . '/Shipment/Track?AWB=' . urlencode($awb_number) . '&Language=' . $language;
        
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token
            ),
            'timeout' => 30,
        );

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }

    /**
     * Get shipment label
     *
     * @param string $awb_number
     * @return array|false
     */
    public function get_shipment_label($awb_number) {
        $token = $this->get_token();
        if (!$token) {
            return false;
        }

        $url = $this->api_base_url . '/Shipment/QueryB2CByAwb?awb=' . urlencode($awb_number);
        
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token
            ),
            'timeout' => 30,
        );

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }

    /**
     * Validate credentials
     *
     * @return bool
     */
    public function validate_credentials() {
        $token = $this->get_token();
        
        if ($token === false) {
            // Log the error for debugging
            error_log('MO Shipping: Credentials validation failed - unable to get token');
            return false;
        }
        
        // Additional validation: check if token is not empty
        if (empty($token)) {
            error_log('MO Shipping: Credentials validation failed - empty token received');
            return false;
        }
        
        return true;
    }
}
