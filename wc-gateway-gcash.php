<?php
/**
 * Plugin Name: WooCommerce GCash Gateway
 * Plugin URI: shemuls.com
 * Description: Offline payment method for Globe GCash.
 * Author: Shemul
 * Author URI: shemuls.com
 * Version: 1.0.0
 * Text Domain: wc-gateway-gcash
 * Domain Path: /i18n/languages/
 *
 * Copyright: (c) 2021 shemuls.com and WooCommerce
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package   WC-Gateway-GCash
 * @author    Shemul
 * @category  Admin
 * @copyright Copyright: (c) 2021 shemuls.com and WooCommerce
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 *
 * This offline gateway forks the WooCommerce core "Cheque" payment gateway to create GCash payment method.
 */
 
defined( 'ABSPATH' ) or exit;


// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}


/**
 * Add the gateway to WC Available Gateways
 * 
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + offline gateway
 */
function wc_gcash_add_to_gateways( $gateways ) {
	$gateways[] = 'WC_Gateway_GCash';
	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'wc_gcash_add_to_gateways' );


/**
 * Adds plugin page links
 * 
 * @since 1.0.0
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */
function wc_gcash_gateway_plugin_links( $links ) {

	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=gcash_gateway' ) . '">' . __( 'Configure', 'wc-gateway-gcash' ) . '</a>'
	);

	return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_gcash_gateway_plugin_links' );


/**
 * GCash Payment Gateway
 *
 * Provides an GCash Payment Gateway.
 * We load it later to ensure WC is loaded first since we're extending it.
 *
 * @class 		WC_Gateway_GCash
 * @extends		WC_Payment_Gateway
 * @version		1.0.0
 * @package		WooCommerce/Classes/Payment
 * @author 		Shemul
 */
add_action( 'plugins_loaded', 'wc_gcash_gateway_init', 11 );

function wc_gcash_gateway_init() {

	class WC_Gateway_GCash extends WC_Payment_Gateway {

		/**
		 * Constructor for the gateway.
		 */
		public function __construct() {
	  
			$this->id                 = 'gcash_gateway';
			$this->icon               = apply_filters('woocommerce_gcash_icon', '');
			$this->has_fields         = false;
			$this->method_title       = __( 'GCash', 'wc-gateway-gcash' );
			$this->method_description = __( 'Allows GCash payments. Orders are marked as "on-hold" when received.', 'wc-gateway-gcash' );
		  
			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();
		  
			// Define user set variables
			$this->title        = $this->get_option( 'title' );
			$this->description  = $this->get_option( 'description' );
			$this->instructions = $this->get_option( 'instructions', $this->description );
		  
			// Actions
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		  
			// Customer Emails
			add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
		}
	
	
		/**
		 * Initialize Gateway Settings Form Fields
		 */
		public function init_form_fields() {
	  
			$this->form_fields = apply_filters( 'wc_gcash_form_fields', array(
		  
				'enabled' => array(
					'title'   => __( 'Enable/Disable', 'wc-gateway-gcash' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable GCash Payment', 'wc-gateway-gcash' ),
					'default' => 'yes'
				),
				
				'title' => array(
					'title'       => __( 'Title', 'wc-gateway-gcash' ),
					'type'        => 'text',
					'description' => __( 'This controls the title for the payment method the customer sees during checkout.', 'wc-gateway-gcash' ),
					'default'     => __( 'GCash Payment', 'wc-gateway-gcash' ),
					'desc_tip'    => true,
				),
				
				'description' => array(
					'title'       => __( 'Description', 'wc-gateway-gcash' ),
					'type'        => 'textarea',
					'description' => __( 'Payment method description that the customer will see on your checkout.', 'wc-gateway-gcash' ),
					'default'     => __( 'You may pay with GCash. Our GCash details will be shown to you after you checkout.', 'wc-gateway-gcash' ),
					'desc_tip'    => true,
				),
				
				'instructions' => array(
					'title'       => __( 'Instructions', 'wc-gateway-gcash' ),
					'type'        => 'textarea',
					'description' => __( 'Instructions that will be added to the thank you page and emails. Make sure you add your GCash account number', 'wc-gateway-gcash' ),
					'default'     => 'You may pay with GCash to mobile phone number 09xx-xxx-xxxx. Please mention your name and the order you are paying for during payment, especially if you are using another person\'s GCash account.',
					'desc_tip'    => true,
				),
			) );
		}
	
	
		/**
		 * Output for the order received page.
		 */
		public function thankyou_page() {
			if ( $this->instructions ) {
				echo wpautop( wptexturize( $this->instructions ) );
			}
		}
	
	
		/**
		 * Add content to the WC emails.
		 *
		 * @access public
		 * @param WC_Order $order
		 * @param bool $sent_to_admin
		 * @param bool $plain_text
		 */
		public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		
			if ( $this->instructions && ! $sent_to_admin && $this->id === $order->payment_method && $order->has_status( 'on-hold' ) ) {
				echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
			}
		}
	
	
		/**
		 * Process the payment and return the result
		 *
		 * @param int $order_id
		 * @return array
		 */
		public function process_payment( $order_id ) {
	
			$order = wc_get_order( $order_id );
			
			// Mark as on-hold (we're awaiting the payment)
			$order->update_status( 'on-hold', __( 'Awaiting GCash payment', 'wc-gateway-gcash' ) );
			
			// Reduce stock levels
			$order->reduce_order_stock();
			
			// Remove cart
			WC()->cart->empty_cart();
			
			// Return thankyou redirect
			return array(
				'result' 	=> 'success',
				'redirect'	=> $this->get_return_url( $order )
			);
		}
	
  } // end \WC_Gateway_GCash class
}
