<?php
/**
 * Admin functionality for MO Shipping
 *
 * @package MO_Shipping_Integration
 * @since 2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * MO Shipping Admin Class
 */
class MO_Shipping_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_mo_shipping_print_all_label', array($this, 'ajax_print_all_label'));
        add_action('wp_ajax_mo_shipping_generate_label', array($this, 'ajax_generate_label'));
        add_action('wp_ajax_mo_shipping_delete_label', array($this, 'ajax_delete_label'));
        add_action('manage_posts_extra_tablenav', array($this, 'add_order_list_buttons'), 20, 1);
        add_filter('manage_edit-shop_order_columns', array($this, 'add_order_columns'));
        add_action('manage_shop_order_posts_custom_column', array($this, 'display_order_columns'), 10, 2);
        add_filter('woocommerce_shop_order_search_fields', array($this, 'add_search_fields'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Track Order', 'mo-shipping-integration'),
            __('MO Track Order', 'mo-shipping-integration'),
            'manage_options',
            plugin_dir_path(MO_SHIPPING_PLUGIN_FILE) . 'track_order.php',
            '',
            'dashicons-welcome-widgets-menus',
            90
        );
        
        add_menu_page(
            __('Create Shipment', 'mo-shipping-integration'),
            __('MO Create shipment', 'mo-shipping-integration'),
            'manage_options',
            plugin_dir_path(MO_SHIPPING_PLUGIN_FILE) . 'create_shipment.php',
            '',
            'dashicons-welcome-widgets-menus',
            90
        );
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts() {
        wp_register_style(
            'mo_shipping_admin_style',
            MO_SHIPPING_PLUGIN_URL . 'css/smsa.css',
            array(),
            MO_SHIPPING_VERSION,
            'all'
        );
        wp_enqueue_style('mo_shipping_admin_style');
    }

    /**
     * Add order list buttons
     *
     * @param string $which
     */
    public function add_order_list_buttons($which) {
        global $typenow;

        if ('shop_order' === $typenow && 'top' === $which) {
            ?>
            <div class="alignleft actions custom">
                <button id="print-all" type="button" style="height:32px;" class="button" value="">
                    <?php echo __('Print All Label', 'mo-shipping-integration'); ?>
                </button>
                <button id="create-all" type="button" style="height:32px;" class="button" value="">
                    <?php echo __('Create All Shipment', 'mo-shipping-integration'); ?>
                </button>
            </div>
            <?php
        }
    }

    /**
     * Add order columns
     *
     * @param array $columns
     * @return array
     */
    public function add_order_columns($columns) {
        $columns['mo_shipping'] = __('MO Shipping Action', 'mo-shipping-integration');
        $columns['mo_shipping_awb'] = __('MO Shipping Tracking Number', 'mo-shipping-integration');
        return $columns;
    }

    /**
     * Display order columns
     *
     * @param string $column
     * @param int $post_id
     */
    public function display_order_columns($column, $post_id) {
        if ('mo_shipping' === $column) {
            $awb = get_post_meta($post_id, 'mo_shipping_awb_no', true);
            
            if (!empty($awb)) {
                echo '<a href="javascript:void(0)" class="mo_shipping_action print_label" data-awb="' . esc_attr($awb) . '">' . __('Print Label', 'mo-shipping-integration') . '</a>';
                echo '&nbsp;&nbsp;&nbsp;<a href="' . admin_url('admin.php?page=mo-shipping-official/track_order.php&awb_no=' . $awb) . '" class="mo_shipping_action" target="_blank;">' . __('Track Order', 'mo-shipping-integration') . '</a>';
            } else {
                echo '<a href="' . admin_url('admin.php?page=mo-shipping-official/create_shipment.php&order_ids[]=' . $post_id) . '" class="mo_shipping_action" target="_blank;">' . __('Create Shipment', 'mo-shipping-integration') . '</a>';
            }
        }
        
        if ('mo_shipping_awb' === $column) {
            $awb = get_post_meta($post_id, 'mo_shipping_awb_no', true);
            echo !empty($awb) ? esc_html($awb) : '';
        }
    }

    /**
     * Add search fields
     *
     * @param array $search_fields
     * @return array
     */
    public function add_search_fields($search_fields) {
        $search_fields[] = 'mo_shipping_awb_no';
        return $search_fields;
    }

    /**
     * AJAX handler for printing all labels
     */
    public function ajax_print_all_label() {
        check_ajax_referer('mo_shipping_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'mo-shipping-integration'));
        }

        $post_ids = array_map('intval', $_POST['post_ids'] ?? array());
        
        if (empty($post_ids)) {
            wp_send_json_error(__('No orders selected.', 'mo-shipping-integration'));
        }

        $api = new MO_Shipping_API();
        $pdf_handler = new MO_Shipping_PDF();
        
        $all_files = array();
        $not_exist = 0;
        
        foreach ($post_ids as $id) {
            $awb = get_post_meta($id, 'mo_shipping_awb_no', true);
            
            if (empty($awb)) {
                $not_exist++;
                continue;
            }
            
            $label_data = $api->get_shipment_label($awb);
            
            if (isset($label_data['waybills'][0]['label'])) {
                $upload_dir = wp_upload_dir();
                $upload_path = $upload_dir['path'];
                $upload_url = $upload_dir['url'];
                
                if (count($label_data['waybills']) < 2) {
                    $filename = $awb . '.pdf';
                    $file_path = $pdf_handler->save_base64_pdf($label_data['waybills'][0]['label'], $filename);
                    
                    if ($file_path) {
                        $all_files[] = $file_path;
                    }
                } else {
                    $temp_files = array();
                    
                    foreach ($label_data['waybills'] as $i => $waybill) {
                        $temp_filename = $i . '_' . $waybill['awb'] . '.pdf';
                        $temp_file_path = $pdf_handler->save_base64_pdf($waybill['label'], $temp_filename);
                        
                        if ($temp_file_path) {
                            $temp_files[] = $temp_file_path;
                        }
                    }
                    
                    if (!empty($temp_files)) {
                        $filename = $awb . '.pdf';
                        $file_path = $upload_path . '/' . $filename;
                        
                        if ($pdf_handler->concatenate_pdfs($temp_files, $file_path)) {
                            $all_files[] = $file_path;
                        }
                        
                        $pdf_handler->cleanup_temp_files($temp_files);
                    }
                }
            }
        }
        
        if (!empty($all_files)) {
            $filename = $pdf_handler->generate_filename('all');
            $upload_dir = wp_upload_dir();
            $final_path = $upload_dir['path'] . '/' . $filename;
            $final_url = $upload_dir['url'] . '/' . $filename;
            
            if ($pdf_handler->concatenate_pdfs($all_files, $final_path)) {
                $pdf_handler->cleanup_temp_files($all_files);
                
                wp_send_json_success(array(
                    'url' => $final_url,
                    'path' => $final_path
                ));
            }
        }
        
        if ($not_exist === count($post_ids)) {
            $message = count($post_ids) === 1 
                ? __('This order was not shipped by MO Shipping.', 'mo-shipping-integration')
                : __('These orders were not shipped by MO Shipping.', 'mo-shipping-integration');
        } else {
            $message = __('Please check your MO Shipping account credentials.', 'mo-shipping-integration');
        }
        
        wp_send_json_error($message);
    }

    /**
     * AJAX handler for generating single label
     */
    public function ajax_generate_label() {
        check_ajax_referer('mo_shipping_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'mo-shipping-integration'));
        }

        $awb_no = sanitize_text_field($_POST['awb_no'] ?? '');
        
        if (empty($awb_no)) {
            wp_send_json_error(__('AWB number is required.', 'mo-shipping-integration'));
        }

        $api = new MO_Shipping_API();
        $pdf_handler = new MO_Shipping_PDF();
        
        $label_data = $api->get_shipment_label($awb_no);
        
        if (isset($label_data['waybills'][0]['label'])) {
            $upload_dir = wp_upload_dir();
            $filename = $awb_no . '.pdf';
            
            if (count($label_data['waybills']) < 2) {
                $file_path = $pdf_handler->save_base64_pdf($label_data['waybills'][0]['label'], $filename);
                $file_url = $pdf_handler->get_pdf_url($file_path);
            } else {
                $temp_files = array();
                
                foreach ($label_data['waybills'] as $i => $waybill) {
                    $temp_filename = $i . '_' . $waybill['awb'] . '.pdf';
                    $temp_file_path = $pdf_handler->save_base64_pdf($waybill['label'], $temp_filename);
                    
                    if ($temp_file_path) {
                        $temp_files[] = $temp_file_path;
                    }
                }
                
                if (!empty($temp_files)) {
                    $file_path = $upload_dir['path'] . '/' . $filename;
                    
                    if ($pdf_handler->concatenate_pdfs($temp_files, $file_path)) {
                        $file_url = $upload_dir['url'] . '/' . $filename;
                        $pdf_handler->cleanup_temp_files($temp_files);
                    }
                }
            }
            
            if (isset($file_url)) {
                wp_send_json_success(array(
                    'url' => $file_url,
                    'path' => $file_path ?? ''
                ));
            }
        }
        
        wp_send_json_error(__('Please try again in few minutes!', 'mo-shipping-integration'));
    }

    /**
     * AJAX handler for deleting label
     */
    public function ajax_delete_label() {
        check_ajax_referer('mo_shipping_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'mo-shipping-integration'));
        }

        $url = esc_url_raw($_POST['attach_url'] ?? '');
        $path = sanitize_text_field($_POST['attach_path'] ?? '');
        
        if (!empty($path) && file_exists($path) && is_writable($path)) {
            unlink($path);
        }
        
        wp_send_json_success();
    }
}
