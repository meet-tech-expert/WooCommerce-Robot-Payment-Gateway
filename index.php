<?php
/*
  Plugin Name: WooCommerce Robot Gateway
  Plugin URI: https://github.com/meet-tech-expert/WooCommerce-Robot-Payment-Gateway
  Description: Extends WooCommerce with a <a href="https://www.robotpayment.co.jp/" target="_blank">Robot Payment</a> gateway.
  Version: 1.0
  Author: Rinkesh Gupta
  Author URI:
  License: GNU General Public License v3.0
  License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */
 
define('ROBOT_DIR', WP_PLUGIN_DIR . "/" . plugin_basename(dirname(__FILE__)) . '/');
define('ROBOT_URL', WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/'); 

add_action('plugins_loaded', 'woocommerce_robot_init', 0);

function woocommerce_robot_init() {

    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    //Add the gateway to woocommerce

    add_filter('woocommerce_payment_gateways', 'add_robot_gateway');

    function add_robot_gateway($methods) {
        $methods[] = 'WC_Gateway_robot';
        return $methods;
    }

    class WC_Gateway_robot extends WC_Payment_Gateway {

        const ROBOT_PAYMENT_URL = 'https://credit.j-payment.co.jp/link/creditcard';

        public function __construct() {

            $this->id = 'woo_robot_payment';

            $this->icon = ROBOT_URL . 'cards.png';

            $this->has_fields = false;

            $this->method_title = 'Robot Payment';

            $this->method_description = 'Robot Payment Incは、オンライン決済ゲートウェイサービスおよび請求関連業務を最適化または自動化するクラウドサービスの提供を行っています。';

            $this->icon_url = 'https://www.robotpayment.co.jp/';

            // Load the form fields

            $this->init_form_fields();

            $this->init_settings();

            // Get setting values

            $this->enabled = $this->get_option('enabled');

            $this->title = $this->get_option('title');

            $this->description = $this->get_option('description');

            $this->storeID = $this->get_option('storeID');

           
            // Hooks

            add_action('woocommerce_receipt_' . $this->id, array(
                $this,
                'receipt_page'
            ));

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
                $this,
                'process_admin_options'
            ));
            

            if (!$this->is_valid_for_use()) {

                $this->enabled = false;
            }
        }

        public function admin_options() {
            ?>

            <h3>Robot Payment Options</h3>

            <p>Pay with your credit card via Robot Payment.</p>

            <?php
            if ($this->is_valid_for_use()):
                ?>

                <table class="form-table"><?php
                    $this->generate_settings_html();
                    ?></table>

                <?php
            else:
                ?>

                <div class="inline error"><p><strong><?php
                            _e('Gateway Disabled', 'woocommerce');
                            ?></strong>: Can't enable - Error</p></div>

            <?php
            endif;
        }

        //Check if this gateway is enabled and available in the user's country

        function is_valid_for_use() {

            if (!in_array(get_woocommerce_currency(), array(
                        'ISK',
                        'USD',
                        'EUR',
                        'JPY'
                    ))) {

                return false;
            }

            return true;
        }

        //Initialize Gateway Settings Form Fields

        function init_form_fields() {

            $this->form_fields = array(
                'enabled' => array(
                    'title' => 'Enable/Disable',
                    'label' => 'Enable Robot Payment',
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no'
                ),
                'title' => array(
                    'title' => 'Title',
                    'type' => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default' => 'ロボット決済ゲートウェイ'
                ),
                'description' => array(
                    'title' => 'Description',
                    'type' => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default' => 'Pay with your credit card via Robot.'
                ),
               
                'storeID' => array(
                    'title' => 'Store ID',
                    'type' => 'text',
                    'description' => 'This is the ID supplied by Robot.',
                    'default' => '1'
                ),
            );
        }

        function get_robot_args($order_id,$order_total) {

            //$ReferenceNumber = 'WC-' . ltrim($order->get_order_number(), '#') . '_a' . rand();
            $robot_args = array(
                            'aid'       => $this->storeID,
                            'am'        => $order_total,
                            'tx'        => 0,
                            'sf'        => 0,
                            'jb'        => 'CAPTURE',
                            'order_id'  => $order_id
            );
            $message = date("Y-m-d H:i:s", time()) . ' robot_args: ' . PHP_EOL;
            file_put_contents('robot-posted.txt', $message, FILE_APPEND);
            file_put_contents('robot-posted.txt', print_r($robot_args, true), FILE_APPEND);
            return $robot_args;
        }

        function generate_robot_form($order_id) {

            global $woocommerce;
            $order = new WC_Order($order_id);
            $order_total = $order->get_total();

            $robot_args = $this->get_robot_args($order_id,$order_total);

            $robot_args_array = array();

            foreach ($robot_args as $key => $value) {

                $robot_args_array[] = '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" />';
            }
            $url = 'https://credit.j-payment.co.jp/link/creditcard';
            
            
            wc_enqueue_js('

                $.blockUI({

                    message: "ご注文ありがとうございます。注文を完了するために、お支払いステップにお進みください。電子マネーちょコムのウェブサイトに転送致します。",

                    baseZ: 99999,

                    overlayCSS: { background: "#fff", opacity: 0.6 },

                    css: {

                        padding:        "20px",

                        zindex:         "9999999",

                        textAlign:      "center",

                        color:          "#555",

                        border:         "3px solid #aaa",

                        backgroundColor:"#fff",

                        cursor:         "wait",

                        lineHeight:     "24px",
                        
                    }

                });

                jQuery("#wc_submit_robot_payment_form").click();

            ');
           

            $html_form = '<form action="' . esc_url($url) . '" method="post" id="robot_payment_form">' . implode('', $robot_args_array) . '<input type="submit" class="button" id="wc_submit_robot_payment_form" value="' . __('電子マネーちょコムで支払う', 'tech') . '" /> <a class="button cancel" href="' . $order->get_cancel_order_url() . '">' . __('注文をキャンセルする &amp; カート内容を復元する', 'tech') . '</a>' . '</form>';
  
             return $html_form;
           
        }
        function receipt_page($order) {
            echo '<p>ご注文ありがとうございます。注文を完了するために、お支払いステップにお進み下さい。Robot のウェブサイトに転送いたします。</p>';
            echo "<script type='text/javascript' src='/wp-content/plugins/woocommerce/assets/js/jquery-blockui/jquery.blockUI.min.js'></script>";
            echo '<div class="nttemoney-form">' . $this->generate_robot_form($order) . '</div>';
        }
        function process_payment($order_id) {
            $order = new WC_Order($order_id);
            return array(
                'result' => 'success',
                'redirect' => $order->get_checkout_payment_url(true)
            );
        }
        
    }

}
