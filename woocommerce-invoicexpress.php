<?php
/*
 Plugin Name: WooCommerce InvoiceXpress (Community)
Plugin URI: http://woothemes.com/woocommerce
Description:  Allows you to invoice your clients using InvoiceXpress
Version: 0.11
Author: WidgiLabs
Author URI: http://woocommerce-invoicexpress.com
License: GPLv2
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class woocommerce_invoicexpress
 */

/**
 * Required functions
 **/
if ( ! function_exists( 'wc_ie_is_woocommerce_active' ) ) require_once( 'woo-includes/woo-functions.php' );

if (wc_ie_is_woocommerce_active()) {
	
	add_action('plugins_loaded', 'woocommerce_invoicexpress_init', 0);
	function woocommerce_invoicexpress_init() {
		$woocommerce_invoicexpress = new woocommerce_invoicexpress;
	}

	add_action('init', 'localization_init', 0);
	function localization_init() {
		$path = dirname(plugin_basename( __FILE__ )) . '/languages/';
		$loaded = load_plugin_textdomain( 'wc_invoicexpress', false, $path);
		if ( isset( $_GET['page'] ) && $_GET['page'] == basename(__FILE__) && !$loaded) {
			return;
		} 
	}

	class woocommerce_invoicexpress {
		function __construct() {
			require_once('InvoiceXpressRequest-PHP-API/lib/InvoiceXpressRequest.php');

			$this->subdomain 	= get_option('wc_ie_subdomain');
			$this->token 		= get_option('wc_ie_api_token');
	
			add_action('admin_init',array(&$this,'settings_init'));
			add_action('admin_menu',array(&$this,'menu'));

			add_action('woocommerce_order_actions', array(&$this,'my_woocommerce_order_actions'), 10, 1);
			add_action('woocommerce_order_action_my_action', array(&$this,'do_my_action'), 10, 1);

			/* add NIF field enabled */
			if ( get_option('wc_ie_add_nif_field') == 1 ) {
				add_filter('woocommerce_checkout_fields' , array(&$this,'wc_ie_nif_checkout'));
				add_filter('woocommerce_address_to_edit', array(&$this,'wc_ie_nif_my_account'));
				add_action('woocommerce_customer_save_address', array(&$this,'wc_ie_my_account_save'), 10, 2);
				add_action('woocommerce_admin_order_data_after_billing_address', array(&$this,'wc_ie_nif_admin'), 10, 1);
				add_action('woocommerce_checkout_process', array(&$this, 'wc_ie_nif_validation'));
			}

		}

		function my_woocommerce_order_actions($actions) {
			$actions['my_action'] = "Create Invoice (InvoiceXpress)";
			return $actions;
		}
		

		function do_my_action($order) {
			// Do something here with the WooCommerce $order object
			$this->process($order->id);
		
		}

		function menu() {
			add_submenu_page('woocommerce', __('InvoiceXpress', 'wc_invoicexpress'),  __('InvoiceXpress', 'wc_invoicexpress') , 'manage_woocommerce', 'woocommerce_invoicexpress', array(&$this,'options_page'));
		}


		function settings_init() {
			global $woocommerce;

			wp_enqueue_style('woocommerce_admin_styles', $woocommerce->plugin_url().'/assets/css/admin.css');

			$general_settings = array(
				array(
					'name'		=> 'wc_ie_settings',
					'title' 	=> __('General Settings','wc_invoicexpress'),
					'page'		=> 'woocommerce_invoicexpress_general',
					'settings'	=> array(
							array(
									'name'		=> 'wc_ie_subdomain',
									'title'		=> __('Subdomain','wc_invoicexpress'),
							),
							array(
									'name'		=> 'wc_ie_api_token',
									'title'		=> __('API Token','wc_invoicexpress'),
							),
							array(
									'name'		=> 'wc_ie_invoice_draft',
									'title'		=> __('Invoice as Draft','wc_invoicexpress'),
							),
							array(
									'name'		=> 'wc_ie_send_invoice',
									'title'		=> __('Send Invoice','wc_invoicexpress'),
							),
							array(
									'name'		=> 'wc_ie_create_simplified_invoice',
									'title'		=> __('Create Simplified Invoice','wc_invoicexpress'),
							),
							array(
									'name'		=> 'wc_ie_add_nif_field',
									'title'		=> __('Add NIF field','wc_invoicexpress'),
							)
						),
					),
				);

			foreach($general_settings as $sections=>$section) {
				add_settings_section($section['name'],$section['title'],array(&$this,$section['name']),$section['page']);
				foreach($section['settings'] as $setting=>$option) {
					add_settings_field($option['name'],$option['title'],array(&$this,$option['name']),$section['page'],$section['name']);
					register_setting($section['page'],$option['name']);
					$this->$option['name'] = get_option($option['name']);
				}
			}

		}

		function wc_ie_tabs( $current = 'general' ){

			$tabs = array(  
				'general'   => __( 'General', 'wc_invoicexpress' ),
				'upgrade'   => __( 'Upgrade', 'wc_invoicexpress' )
			);

			echo '<div id="icon-themes" class="icon32"><br></div>';
			echo '<h2 class="nav-tab-wrapper">';

			foreach ( $tabs as $tab => $name ) {
				$class = ( $tab == $current ) ? ' nav-tab-active' : '';

				echo '<a class="nav-tab'.$class.'" href="?page=woocommerce_invoicexpress&tab='.$tab.'">'.$name.'</a>';
			}

			echo '</h2>';
		}


		function options_page() { 
			global $pagenow;

			if( $pagenow == 'admin.php' && $_GET["page"] == 'woocommerce_invoicexpress' ){
			?>
				<div class="wrap woocommerce">
				<form method="post" id="mainform" action="options.php">
			<?php
				if ( isset ( $_GET['tab'] ) ) $this->wc_ie_tabs($_GET['tab']); else $this->wc_ie_tabs('general');

				$tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'general';

				switch ( $tab ) {
					case 'general':
					?>
						<div class="icon32 icon32-woocommerce-settings" id="icon-woocommerce"><br /></div>
						<h2><?php _e('Plugin WooCommerce InvoiceXpress','wc_invoicexpress'); ?></h2>
						<?php settings_fields('woocommerce_invoicexpress_general'); ?>
						<?php do_settings_sections('woocommerce_invoicexpress_general'); ?>
						<p class="submit"><input type="submit" class="button-primary" value="<?php _e( 'Save Changes', 'wc_invoicexpress' ) ?>" /></p>
						</form>
						</div>
					<?php
						break;
					case 'email':
					?>
						<div class="icon32 icon32-woocommerce-settings" id="icon-woocommerce"><br /></div>
						<h2><?php _e('Plugin WooCommerce InvoiceXpress','wc_invoicexpress'); ?></h2>
						<?php settings_fields('woocommerce_invoicexpress_email'); ?>
						<?php do_settings_sections('woocommerce_invoicexpress_email'); ?>
						<p class="submit"><input type="submit" class="button-primary" value="<?php _e( 'Save Changes', 'wc_invoicexpress' ) ?>" /></p>
						</form>
						</div>
					<?php
						break;
				}

				if ( $tab == 'upgrade' ){ $this->wc_ie_upgrade_tab(); }

			}

		}
		//wc_invoicexpress
		function wc_ie_settings() {
			echo '<p>'.__('Please fill in the necessary settings below. Then create an invoice and go into an order and choose "Create Invoice (InvoiceXpress)".','wc_invoicexpress').'</p>';
		}
		function wc_ie_subdomain() {
			echo '<input type="text" name="wc_ie_subdomain" id="wc_ie_subdomain" value="'.get_option('wc_ie_subdomain').'" />';
			echo ' <label for="wc_ie_subdomain">'.__( 'Enter <b>subdomain.app</b> ( <a href="http://widgilabs.bitbucket.org/static/invoicexpress_subdomain_faq.html" target="_blank">Help me find my subdomain</a> )', 'wc_invoicexpress' ).'</label>';
		}
		function wc_ie_api_token() {
			echo '<input type="password" name="wc_ie_api_token" id="wc_ie_api_token" value="'.get_option('wc_ie_api_token').'" />';
			echo ' <label for="wc_ie_api_token">'.__( 'Go to Settings >> API in InvoiceXpress to get one.', 'wc_invoicexpress' ).'</label>';
		}
		function wc_ie_send_invoice() {
			$checked = (get_option('wc_ie_send_invoice')==1) ? 'checked="checked"' : '';
			echo '<input type="hidden" name="wc_ie_send_invoice" value="0" />';
			echo '<input type="checkbox" name="wc_ie_send_invoice" id="wc_ie_send_invoice" value="1" '.$checked.' />';
			echo ' <label for="wc_ie_send_invoice">'.__( 'Send the client an e-mail with the order invoice attached (<i>recommended</i>).', 'wc_invoicexpress' ).'</label>';
		}
		function wc_ie_add_nif_field() {
			$checked = (get_option('wc_ie_add_nif_field')==1) ? 'checked="checked"' : '';
			echo '<input type="hidden" name="wc_ie_add_nif_field" value="0" />';
			echo '<input type="checkbox" name="wc_ie_add_nif_field" id="wc_ie_add_nif_field" value="1" '.$checked.' />';
			echo ' <label for="wc_ie_add_nif_field">'.__( 'Add a client NIF field to the checkout form (<i>recommended</i>).', 'wc_invoicexpress' ).'</label>';
		}

		function wc_ie_create_simplified_invoice() {
			$checked = (get_option('wc_ie_create_simplified_invoice')==1) ? 'checked="checked"' : '';
			echo '<input type="hidden" name="wc_ie_create_simplified_invoice" value="0" />';
			echo '<input type="checkbox" name="wc_ie_create_simplified_invoice" id="wc_ie_create_simplified_invoice" value="1" '.$checked.' />';
			echo ' <label for="wc_ie_create_simplified_invoice">'.__( 'Create simplified invoices. Only available for Portuguese accounts.', 'wc_invoicexpress' ).'</label>';
		}

		function wc_ie_invoice_draft(){
			$checked = (get_option('wc_ie_invoice_draft')==1) ? 'checked="checked"' : '';
			echo '<input type="hidden" name="wc_ie_invoice_draft" value="0" />';
			echo '<input type="checkbox" name="wc_ie_invoice_draft" id="wc_ie_invoice_draft" value="1" '.$checked.' />';
			echo ' <label for="wc_ie_invoice_draft">'.__( 'Create invoice as draft.', 'wc_invoicexpress' ).'</label>';
		}

		/**
		* Return the shipping tax status for an order (props @aaires)
		* 
		* @param  WC_Order
		* @return string|bool - status if exists, false otherwise
		*/
		function wc_ie_get_order_shipping_tax_status( $order ) 
		{
			WC()->shipping->load_shipping_methods();
		
			$shipping_tax_status = false;
			$active_methods      = array();
			$shipping_methods    = WC()->shipping->get_shipping_methods();

			foreach ( $shipping_methods as $id => $shipping_method ) {

				if ( isset( $shipping_method->enabled ) && $shipping_method->enabled == 'yes' ) {
					$active_methods[ $shipping_method->title ] = $shipping_method->tax_status ;
				}
			}

			$shipping_method     = $order->get_shipping_method();
			$shipping_tax_status = $active_methods[ $shipping_method ];
		
			return $shipping_tax_status;
		}

		// upgrade tab
		function wc_ie_upgrade_tab(){
		?>
			<div class="wrap woocommerce">
				<div class="icon32 icon32-woocommerce-settings" id="icon-woocommerce"><br /></div>
				<h2><?php _e("Plugin WooCommerce InvoiceXpress Pro e Premium","wc_invoicexpress") ?></h2>
				<h3><?php _e('Apenas algumas razões para fazer o upgrade:','wc_invoicexpress') ?></h3>
				<ul>
					<li><?php _e('- Faturação automática.','wc_invoicexpress') ?></li>
					<li><?php _e('- Escolha de série de faturação.','wc_invoicexpress') ?></li>
					<li><?php _e('- Customização do e-mail enviado ao cliente.','wc_invoicexpress') ?></li>
					<li><?php _e('- Campo para observações na fatura.','wc_invoicexpress') ?></li>
					<li><?php _e('- Tempo de resposta reduzidos para pedidos de suporte.','wc_invoicexpress') ?></li>
					<li>E muito mais.. <a href="" target="_blank">veja todos os benefícios!</a></li>
				</ul>
				<h2><?php _e("Quer saber mais?", "wc_invoicexpress") ?></h2>
				Visite o site do plugin em <a href="http://woocommerce-invoicexpress.com/" target="_blank">http://woocommerce-invoicexpress.com</a>
			</div>
		<?php
		}


		function process($order_id) {

			InvoiceXpressRequest::init($this->subdomain, $this->token);

			$order = new WC_Order($order_id);

			$client_name = $order->billing_first_name." ".$order->billing_last_name;

			$country = wc_ie_get_correct_country( $order->billing_country );

			$vat='';
			$vat_text = '';
			$client_email = $order->billing_email;

			if(isset($order->billing_nif)){
				$vat = $order->billing_nif;
			}

			$vat = apply_filters( 'wc_ie_change_billing_nif', $vat );
			if ( $vat ){
				$vat_text = ' - NIF: '.$vat;
			}

			$invoice_name = $client_name;
			if ( $order->billing_company_name ){
				$invoice_name = $order->billing_company_name;
			}

			//date from form
			$client_data = array(
				'name'         => $invoice_name,
				'code'         => $client_email,
				'email'        => $client_email,
				'phone'        => $order->billing_phone,
				'address'      => $order->billing_address_1 . "\n" . $order->billing_address_2 . "\n",
				'postal_code'  => $order->billing_postcode . " - " . $order->billing_city,
				'country'      => $country,
				'fiscal_id'    => $vat
			);


			// check if client exists
			$client = new InvoiceXpressRequest('clients.find-by-code');
			$client->request($client_email);
			if($client->success()) {
				// client exists let's get the data
				$response = $client->getResponse();
				$client_id = $response['id'];

				//update client
				$client = new InvoiceXpressRequest('clients.update');
				$client_data_to_update = array(
					'client' => array(
						'name'         => $invoice_name,
						'code'         => $client_email,
						'email'        => $client_email,
						'phone'        => $order->billing_phone,
						'address'      => $order->billing_address_1 . "\n" . $order->billing_address_2 . "\n",
						'postal_code'  => $order->billing_postcode . " - " . $order->billing_city,
						'country'      => $country,
						'fiscal_id'    => $vat,
						'send_options' => $send_options
					)
				);
				//error_log("client_data = ".print_r($client_data_to_update, true));
				$client->post( $client_data_to_update );
				$client->request($client_id);

			}

			$iva_name = 'IVA23';
			if ( $this->wc_ie_is_tax_exempt() ){
				$iva_name = 'IVA0';
			}

			foreach($order->get_items() as $item) {

				$pid = $item['item_meta']['_product_id'][0];

				$prod = get_product($pid);

				$original_price = floatval( $item['line_subtotal'] );
				$discount_price = floatval( $item['line_total'] );

				$discount_value = floatval( ( ( $original_price - $discount_price ) / $original_price ) * 100 );
				$discount_value = number_format( $discount_value, 2 );

				$items[] = array(
						'name'			=> "#".$pid,
						'description'	=> $item['qty']. "x ".get_the_title($pid),
						'unit_price'	=> $discount_price, // subtract tax 23%
						'quantity'		=> 1,
						'discount'		=> $discount_value,
						'unit'			=> 'unit',
						'tax'			=> array(
							'name'	=> $iva_name
						)
				);
			}

			/*
			 FEES
			 */
			foreach($order->get_fees() as $item) {

				$fee_name = $item['name'];

				$original_price = floatval( $item['line_subtotal'] );
				$discount_price = floatval( $item['line_total'] );

				$discount_value = floatval( ( ( $original_price - $discount_price ) / $original_price ) * 100 );
				$discount_value = number_format( $discount_value, 2 );

				$items[] = array(
						'name'			=> $fee_name,
						'description'	=> $fee_name,
						'unit_price'	=> $discount_price, // subtract tax 23%
						'quantity'		=> 1,
						'discount'		=> $discount_value,
						'unit'			=> 'unit',
						'tax'			=> array(
							'name'	=> $iva_name
						)
				);
			}

			/*
			 SHIPPING
			 */
			$shipping_unit_price =  $order->get_total_shipping();
			$shipping_tax_name   = "IVA23";
			$shipping_tax_status = $this->wc_ie_get_order_shipping_tax_status( $order ) ; 

			if( "none" == $shipping_tax_status ) {
				$shipping_tax_name = 'IVA0';
			}

			if ( $shipping_unit_price > 0 ) {
				$items[] = array(
					'name'			=> 'Envio',
					'description'	=> 'Custos de Envio',
					'unit_price'	=> $shipping_unit_price,
					'quantity'		=> 1,
					'tax'			=> array(
						'name'	=> $shipping_tax_name
					)
				);
			}

			/*
			Create Simplified Invoice
			 */
			if(get_option('wc_ie_create_simplified_invoice')==1) {
				$data = array(
						'simplified_invoice' => array(
								'date'	=> $order->completed_date,
								'due_date' => $order->completed_date,
								'client' => $client_data,
								'reference' => $order_id,
								'items'		=> array(
										'item'	=> $items
								)
						)
				);

				if( "none" == $shipping_tax_status ) {
					$data['simplified_invoice']['tax_exemption'] = 'M99';
				}

			} else {

				/*
				Create Normal Invoice
				 */
				$data = array(
						'invoice' => array(
								'date'	=> $order->completed_date,
								'due_date' => $order->completed_date,
								'client' => $client_data,
								'reference' => $order_id,
								'items'		=> array(
										'item'	=> $items
								)
						)
				);

				if( "none" == $shipping_tax_status ) {
					$data['invoice']['tax_exemption'] = 'M99';
				}

			}

			if(get_option('wc_ie_create_simplified_invoice')==1) {
				$invoice = new InvoiceXpressRequest('simplified_invoices.create');
			} else {
				$invoice = new InvoiceXpressRequest('invoices.create');
			}

			$invoice->post($data);
			$invoice->request();
			if($invoice->success()) {
				$response = $invoice->getResponse();
				$invoice_id = $response['id'];
				$order->add_order_note(__('Client invoice in InvoiceXpress','wc_invoicexpress').' #'.$invoice_id);
				add_post_meta($order_id, 'wc_ie_inv_num', $invoice_id, true);
				
				// extra request to change status to final
				if ( get_option( 'wc_ie_invoice_draft' ) == 0 ){
					if(get_option('wc_ie_create_simplified_invoice')==1) {
						$invoice = new InvoiceXpressRequest('simplified_invoices.change-state');
					} else {
						$invoice = new InvoiceXpressRequest('invoices.change-state');
					}
					$data = array('invoice' => array('state'	=> 'finalized'));
					$invoice->post($data);
					$invoice->request($invoice_id);
					
					if($invoice->success()) { // keep the invoice sequence number in a meta
						$response = $invoice->getResponse();
						$inv_seq_number = $response['sequence_number'];
						add_post_meta($order_id, 'wc_ie_inv_seq_num', $inv_seq_number, true);
					}
					
					$data = array('invoice' => array('state'	=> 'settled'));
					$invoice->post($data);
					$invoice->request($invoice_id);
				}

			} else {
				$error = $invoice->getError();

				if (is_array($error)) {
					$order->add_order_note(__('InvoiceXpress Invoice API Error:', 'wc_invoicexpress').': '.print_r($error, true));
				} else {
					$order->add_order_note(__('InvoiceXpress Invoice API Error:', 'wc_invoicexpress').': '.$error);
				}

			}
			
			
			/*
			Send Invoice via e-mail to client
			 */
			if(get_option('wc_ie_send_invoice')==1 && isset($invoice_id)) {

				$subject = get_option('wc_ie_email_subject') ? get_option('wc_ie_email_subject') : __('Order Invoice','wc_invoicexpress');
				$body = get_option('wc_ie_email_body') ? get_option('wc_ie_email_body') : __('Please find your invoice in attach. Archive this e-mail as proof of payment.','wc_invoicexpress');

				$data = array(
						'message' => array(
								'client' => array(
										'email' => $order->billing_email,
										'save' => 1
										),
								'subject' => $subject,
								'body' => $body
								)
						);

				if(get_option('wc_ie_create_simplified_invoice')==1) {
					$send_invoice = new InvoiceXpressRequest('simplified_invoices.email-invoice');
				} else {
					$send_invoice = new InvoiceXpressRequest('invoices.email-invoice');
				}
				$send_invoice->post($data);
				$send_invoice->request($invoice_id);
				
				if($send_invoice->success()) {
					$response = $send_invoice->getResponse();
					$order->add_order_note(__('Client invoice sent from InvoiceXpress','wc_invoicexpress'));
				} else {
					$order->add_order_note(__('InvoiceXpress Send Invoice API Error','wc_invoicexpress').': '.$send_invoice->getError());
				}
			}
			
		}

		function wc_ie_is_tax_exempt() {

			$tax_exemption = get_option( 'wc_ie_tax_exemption_reason_options');
			
			if ( $tax_exemption && 'M00' != $tax_exemption ){
				return $tax_exemption;
			}

			return false;
		}

		//Add field to checkout
		function wc_ie_nif_checkout( $fields ) {

			$current_user=wp_get_current_user();
			$fields['billing']['billing_nif'] = array(
				'type'			=>	'text',
				'label'			=> __('VAT', 'wc_invoicexpress'),
				'placeholder'	=> _x('VAT identification number', 'placeholder', 'wc_invoicexpress'),
				'class'			=> array('form-row-last'),
				'required'		=> false,
				'default'		=> ($current_user->billing_nif ? trim($current_user->billing_nif) : ''),
			);

			return $fields;
		}

		//Add NIF to My Account / Billing Address form
		function wc_ie_nif_my_account( $fields ) {
			global $wp_query;
			if (isset($wp_query->query_vars['edit-address']) && $wp_query->query_vars['edit-address']!='billing') {
				return $fields;
			} else {
				$current_user=wp_get_current_user();
				if ($current_user->billing_country=='PT') {
					$fields['billing_nif']=array(
						'type'			=>	'text',
						'label'			=> __('NIF / NIPC', 'wc_invoicexpress'),
						'placeholder'	=> _x('Portuguese VAT identification number', 'placeholder', 'wc_invoicexpress'),
						'class'			=> array('form-row-last'),
						'required'		=> false,
						//'clear'			=> true,
						'default'		=> ($current_user->billing_nif ? trim($current_user->billing_nif) : ''),
					);
				}
				return $fields;
			}
		}

		//Save NIF to customer Billing Address
		function wc_ie_my_account_save($user_id, $load_address) {
			if ($load_address=='billing') {
				if (isset($_POST['billing_nif'])) {
					update_user_meta( $user_id, 'billing_nif', trim($_POST['billing_nif']) );
				}
			}
		}

		//Add field to order admin panel
		function wc_ie_nif_admin($order){
			if (@is_array($order->order_custom_fields['_billing_country'])) {
				//Old WooCommerce versions
				if(@in_array('PT', $order->order_custom_fields['_billing_country']) ) {
					echo "<p><strong>".__('NIF / NIPC', 'wc_invoicexpress').":</strong> " . $order->order_custom_fields['_billing_nif'][0] . "</p>";
		  		}
			} else {
				//New WooCommerce versions
				if ($order->billing_country=='PT') {
					$order_custom_fields=get_post_custom($order->ID);
					echo "<p><strong>".__('NIF / NIPC', 'wc_invoicexpress').":</strong> " . $order_custom_fields['_billing_nif'][0] . "</p>";
				}
			}
		}

		function wc_ie_nif_validation() {
			// Check if set, if its not set add an error.
			if(isset($_POST['billing_nif']) && !empty($_POST['billing_nif']) && isset($_POST['billing_country']) && $_POST['billing_country'] == 'PT'){
				if(! $this->wc_ie_validate_portuguese_vat($_POST['billing_nif'])){
					wc_add_notice( __( 'Invalid NIF / NIPC', 'wc_invoicexpress' ), 'error' );
				}
			}
		}

		function wc_ie_validate_portuguese_vat($vat) {

			$valid_first_digits = array(1, 2, 3, 5, 6, 8 );
			$valid_first_two_digits = array(45, 70, 71, 72, 77, 79, 90, 91, 98, 99);

			// if first digit is valid
			$first_digit = (int) substr($vat, 0, 1);
			$first_two_digits = (int) substr($vat, 0, 2);

			if ( ! in_array($first_digit, $valid_first_digits) &&
				 ! in_array($first_two_digits, $valid_first_two_digits) )
			{
				return false;
			}

			$check1 = substr($vat, 0,1)*9;
			$check2 = substr($vat, 1,1)*8;
			$check3 = substr($vat, 2,1)*7;
			$check4 = substr($vat, 3,1)*6;
			$check5 = substr($vat, 4,1)*5;
			$check6 = substr($vat, 5,1)*4;
			$check7 = substr($vat, 6,1)*3;
			$check8 = substr($vat, 7,1)*2;

			$total= $check1 + $check2 + $check3 + $check4 + $check5 + $check6 + $check7 + $check8;

			$totalDiv11 = $total / 11;
			$modulusOf11 = $total - intval($totalDiv11) * 11;
			if ( $modulusOf11 == 1 || $modulusOf11 == 0)
			{
				$check = 0;
			}
			else
			{
				$check = 11 - $modulusOf11;
			}


			$lastDigit = substr($vat, 8,1)*1;
			if ( $lastDigit != $check ) {
				return false;
			}

			return true;
		}

	}
}