<?php 




    function smsa_shipping_method()
    {

        if (!class_exists('SMSA_Shipping_Method'))
        {

            class SMSA_Shipping_Method extends WC_Shipping_Method
            {
                /**
                 * Constructor for your shipping class
                 *
                 * @access public
                 * @return void
                 */

                public function __construct()
                {
                   
                        wp_register_style( 'smsa_style', 'css/smsa.css' );
                        wp_enqueue_style( 'smsa_style' );

                    global $woocommerce;
                    $this->id = 'smsa-express-integration';
                    $this->method_title = __('SMSA Shipping Integration');
                    $this->method_description = __('<h3>SMSA PLUGIN INSTALLATION GUIDE</h3><br>
SMSA Express Shipping (Official) Plugin requires a valid SMSA account number, username, and password.
Please send us an email at fsaid@smsaexpress.com to have these credentials created and sent to you.
If you don’t have an account number, please send us an email at info@smsaexpress.com to have your account number created.<br><h3>تعليمات التحميل لتطبيق سمسا إكسبريس الرسمي</h3>
يتطلب هذا التطبيق إدخال رقم حساب فعًال, إسم المستخدم و الرقم السري الخاص بحسابكم.
يرجى مراسلتنا على الإيميل أدناه ليتم تزويدكم ببيانات الحساب والرقم السري.
fsaid@smsaexpress.com
إذا لا يوجد لديكم رقم حساب, يرجى مراسلتنا عبر الإيميل أدناه ليتم إنشاء رقم حسابكم لدى سمسا إكسبريس.
info@smsaexpress.com');

                    
                    $this->init();

                    $this->enabled = isset($this->settings['enabled']) ? $this->settings['enabled'] : 'yes';



                }



                /**
                 * Init your settings
                 *
                 * @access public
                 * @return void
                 */
                function init()
                {
                    // Load the settings API
                    $this->init_form_fields();
                    $this->init_settings();

                    // Save settings in admin if you have any defined
                    add_action('woocommerce_update_options_shipping_' . $this->id, array(
                        $this,
                        'process_admin_options'
                    ));
                }

                /**
                 * Define settings field for this shipping
                 * @return void
                 */
                function init_form_fields()
                {

                    $this->form_fields = array(

                       
                        'smsa_account_no' => array(
                            'title' => __('SMSA Account Number') ,
                            'type' => 'password',
                            'description' => __('Enter SMSA Account Number') ,
                            'desc_tip' => true,
                            'default' => '',
                            'css' => 'width:170px;',
                        ) ,
                        'smsa_username' => array(
                            'title' => __('SMSA Username') ,
                            'type' => 'password',
                            'description' => __('Enter SMSA Username') ,
                            'desc_tip' => true,
                            'default' => '',
                            'css' => 'width:170px;',
                        ) ,
                        'smsa_password' => array(
                            'title' => __('SMSA Password') ,
                            'type' => 'password',
                            'description' => __('Enter SMSA password') ,
                            'desc_tip' => true,
                            'default' => '',
                            'css' => 'width:170px;',
                        ) ,
                        'store_phone' => array(
                            'title' => __('Store Phone Number') ,
                            'type' => 'number',
                            'description' => __('Enter Phone number') ,
                            'desc_tip' => true,
                            'default' => '',
                            'css' => 'width:170px;',
                        ) ,
                    );

                }

               
                //*********************Check SMSA details is valid or not***************//
                public function process_admin_options()
                {


                    $st_account = sanitize_text_field($_POST['woocommerce_smsa-express-integration_smsa_account_no']);
                    $st_username = sanitize_text_field($_POST['woocommerce_smsa-express-integration_smsa_username']);
                    $st_password = sanitize_text_field($_POST['woocommerce_smsa-express-integration_smsa_password']);
                    $st_phone = sanitize_text_field($_POST['woocommerce_smsa-express-integration_store_phone']);
                     
                    if ($st_account != "" && $st_username != "" && $st_password != "" && $st_phone != "")
                    {
                        $body = array(
                            'accountNumber' => $st_account,
                            'username' => $st_username,
                            'password' => $st_password
                        );

                      $args = array(
                        'body' => json_encode($body) ,
                        'timeout' => '5',
                        'redirection' => '5',
                        'httpversion' => '1.0',
                        'blocking' => true,
                        'headers' => array(
                        'Content-Type' => 'application/json; charset=utf-8'
                        ) ,
                        'cookies' => array() ,
                        );
                        $re = wp_remote_post('https://smsaopenapis.azurewebsites.net/api/Token', $args);

                        $resp = json_decode($re['body']);

                        if (isset($resp->token))
                        {
                            return parent::process_admin_options();
                        }
                        else
                        {
                            $settings = new WC_Admin_Settings();
                            $settings->add_error('Please check your credentials and try again.');
                        }
                    }
                    else
                    {
                        $settings = new WC_Admin_Settings();
                        $settings->add_error('Please fill all the fields and try again.');
                    }

                }
                
            }
        }
        }
    
   //*********************Add SMSA EXpress shipping option***************//
    add_action('woocommerce_shipping_init', 'smsa_shipping_method');
    
    

    function smsa_add_smsa_shipping_method($methods)
    {
         
         $methods['smsa-express-integration'] = 'SMSA_Shipping_Method';
        
        return $methods;
    }

    add_filter('woocommerce_shipping_methods', 'smsa_add_smsa_shipping_method');


   

       //*********************Add shipping phone number field***************//
    add_filter('woocommerce_checkout_fields', 'smsa_bbloomer_shipping_phone_checkout');

    function smsa_bbloomer_shipping_phone_checkout($fields)
    {
        $fields['shipping']['shipping_phone'] = array(
            'label' => 'Phone',
            'required' => true,
            'class' => array(
                'form-row-wide'
            ) ,
            'priority' => 25,
        );
        return $fields;
    }

       //*********************Add SMSA action column on order page admin end***************//
    function smsa_wc_new_order_column($columns)
    {
        $columns['smsa'] = 'SMSA Action';
         $columns['smsa_awb'] = 'SMSA Tracking Number';
        return $columns;
    }
    add_filter('manage_edit-shop_order_columns', 'smsa_wc_new_order_column');

function smsa_woocommerce_shop_order_search_order_awb_no($search_fields) { 
    $search_fields[] = 'smsa_awb_no'; 
    return $search_fields;
}
add_filter('woocommerce_shop_order_search_fields', 'smsa_woocommerce_shop_order_search_order_awb_no');
 



add_action( 'manage_posts_extra_tablenav', 'smsa_admin_order_list_top_bar_button', 20, 1 );
function smsa_admin_order_list_top_bar_button( $which ) {
    global $typenow;

    if ( 'shop_order' === $typenow && 'top' === $which ) {
        ?>
        <div class="alignleft actions custom">
            <button id="print-all" type="button" style="height:32px;" class="button" value=""><?php
                echo __( 'Print All Label', 'woocommerce' ); ?></button>
                <button id="create-all" type="button" style="height:32px;" class="button" value=""><?php
                echo __( 'Create All Shipment', 'woocommerce' ); ?></button>
        </div>
        <?php
    }

      
}




  //*********************Display SMSA action button on order page on admin end***************//

    function smsa_add_smsa_action_column($column)
    {
        global $post;

        if ('smsa' === $column)
        {

            $num = get_post_meta($post->ID, 'smsa_awb_no');
            if (count($num) > 0)
            {

                if($num[0]!="")
                {
                echo '<a href="javascript:void(0)" class="smsa_action print_label" data-awb="'.$num['0'].'">Print Label</a>';
                echo '&nbsp;&nbsp;&nbsp;<a href="'.admin_url().'admin.php?page=smsa-shipping-official/track_order.php&awb_no='.$num['0'].'" class="smsa_action" target="_blank;">Track Order</a>';
                }
            }
            else
            {
                echo '<a href="'.admin_url().'admin.php?page=smsa-shipping-official/create_shipment.php&order_ids[]='.$post->ID.'" class="smsa_action" target="_blank;">Create Shipment</a>';

            }
        }
        if ('smsa_awb' === $column)
        {

             $num = get_post_meta($post->ID, 'smsa_awb_no');
              if (count($num) > 0)
            {
                echo $num[0];
            }
            else
            {
                echo "";
            }
        }
    }
    add_action('manage_shop_order_posts_custom_column', 'smsa_add_smsa_action_column');

  //  add_action('woocommerce_thankyou', 'new_order', 10, 1);