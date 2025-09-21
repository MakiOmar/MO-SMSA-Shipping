<?php
/**
 * Plugin Name: MO Shipping Integration
 * Plugin URI: https://github.com/maki3omar
 * Description: Professional shipping integration for WooCommerce with SMSA Express support
 * Author: Mohammad Omar
 * Author URI: mailto:maki3omar@gmail.com
 * Version: 2.4
 * Text Domain: mo-shipping-integration
 * Domain Path: /languages
 * Requires at least: 5.3
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

use setasign\Fpdi\Fpdi;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MO_SHIPPING_VERSION', '2.4');
define('MO_SHIPPING_PLUGIN_FILE', __FILE__);
define('MO_SHIPPING_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MO_SHIPPING_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MO_SHIPPING_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Initialize the plugin
 */
function mo_shipping_init() {
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'mo_shipping_woocommerce_missing_notice');
        return;
    }

    // Include the main plugin class
    require_once MO_SHIPPING_PLUGIN_DIR . 'includes/class-mo-shipping-plugin.php';
    
    // Initialize the plugin
    MO_Shipping_Plugin::instance();
}
add_action('plugins_loaded', 'mo_shipping_init');


/**
 * WooCommerce missing notice
 */
function mo_shipping_woocommerce_missing_notice() {
    ?>
    <div class="notice notice-error">
        <p><?php _e('MO Shipping Integration requires WooCommerce to be installed and active.', 'mo-shipping-integration'); ?></p>
    </div>
    <?php
}

/**
 * Add admin JavaScript
 */
function mo_shipping_admin_javascript() { ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Create nonce for AJAX requests
        var moShippingNonce = '<?php echo wp_create_nonce('mo_shipping_nonce'); ?>';

        // Fastlo Dropdown functionality
        $('#fastlo-main-btn').click(function(e) {
            e.stopPropagation();
            $('#fastlo-dropdown-menu').toggle();
        });

        // Close dropdown when clicking outside
        $(document).click(function(e) {
            if (!$(e.target).closest('.fastlo-dropdown').length) {
                $('#fastlo-dropdown-menu').hide();
            }
        });

        // Hover effects for dropdown buttons
        $('#fastlo-dropdown-menu button').hover(
            function() { $(this).css('background', '#f8f9fa'); },
            function() { $(this).css('background', '#fff'); }
        );

        $('#create-all').click(function(){
            $('#fastlo-dropdown-menu').hide(); // Close dropdown
            var list1 = new Array();
            $("input[name='post[]']:checked").each(function (index, obj) {
                list1.push('&order_ids[]='+$(this).val());
            });
            if(list1.length<1) {
                alert("<?php _e('Please select any order first.', 'mo-shipping-integration'); ?>");
            } else {
                var url="<?php echo admin_url();?>admin.php?page=mo-shipping-official/create_shipment.php"+list1.join("");
                window.open(url,"_blank");
            }
        });

        $('#print-all').click(function(){
            $('#fastlo-dropdown-menu').hide(); // Close dropdown
            var list = new Array();
            $("input[name='post[]']:checked").each(function (index, obj) {
                list.push($(this).val());
            });
            if(list.length<1) {
                alert("<?php _e('Please select any order first.', 'mo-shipping-integration'); ?>");
            } else {
                $(this).html('<?php _e('Processing...', 'mo-shipping-integration'); ?>');
                var data = {
                    'action': 'mo_shipping_print_all_label',
                    'post_ids': list,
                    'nonce': moShippingNonce
                };
                jQuery.post(ajaxurl, data, function(response) {
                    if(response.success) {
                        var win = window.open(response.data.url, '_blank');
                        if (win) {
                            win.focus();
                            setTimeout(function () {
                                win.print();
                            }, 2000);
                        } else {
                            alert('<?php _e('Please allow popups for this website', 'mo-shipping-integration'); ?>');
                        }
                        setTimeout(function () {
                            var deleteData = {
                                'action': 'mo_shipping_delete_label',
                                'attach_url': response.data.url,
                                'attach_path': response.data.path,
                                'nonce': moShippingNonce
                            };
                            jQuery.post(ajaxurl, deleteData, function(data) {});
                        }, 5000);
                    } else {
                        alert(response.data);
                    }  
                    $('#print-all').html('<?php _e('Print All Label', 'mo-shipping-integration'); ?>');    
                });
            }
        });

        $('.print_label').click(function(){
            $(this).html('<?php _e('Processing...', 'mo-shipping-integration'); ?>');
            var data = {
                'action': 'mo_shipping_generate_label',
                'awb_no': jQuery(this).attr('data-awb'),
                'nonce': moShippingNonce
            };

            jQuery.post(ajaxurl, data, function(response) {
                if(response.success) {
                    var win = window.open(response.data.url, '_blank');
                    if (win) {
                        win.focus();
                        setTimeout(function () {
                            win.print();
                        }, 2000);
                    } else {
                        alert('<?php _e('Please allow popups for this website', 'mo-shipping-integration'); ?>');
                    }
                    setTimeout(function () {
                        var deleteData = {
                            'action': 'mo_shipping_delete_label',
                            'attach_url': response.data.url,
                            'attach_path': response.data.path,
                            'nonce': moShippingNonce
                        };
                        jQuery.post(ajaxurl, deleteData, function(data) {});
                    }, 5000);
                } else {
                    alert(response.data);
                }      
                $('.print_label').html('<?php _e('Print Label', 'mo-shipping-integration'); ?>');
            });
        });
    });
    </script> <?php
}
add_action('admin_footer', 'mo_shipping_admin_javascript');

/**
 * Activation redirect
 */
function mo_shipping_activation_redirect($plugin) {
    if ($plugin == plugin_basename(__FILE__)) {
        exit(wp_redirect(admin_url('admin.php?page=wc-settings&tab=shipping&section=mo-shipping-integration')));
    }
}
add_action('activated_plugin', 'mo_shipping_activation_redirect');

/**
 * Utility function for multibyte string padding
 */
function mo_shipping_mb_str_pad($input, $pad_length, $pad_string = ' ', $pad_type = STR_PAD_RIGHT, $encoding = null) {
    if (!$encoding) {
        $diff = strlen($input) - mb_strlen($input);
    } else {
        $diff = strlen($input) - mb_strlen($input, $encoding);
    }
    return str_pad($input, $pad_length + $diff, $pad_string, $pad_type);
}
