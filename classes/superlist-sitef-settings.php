<?php


require_once('superlist-sitef-setup.php');

class SuperlistSitefSettings {

	//account data for sandbox testing
	const SUPERLIST_SITEF_SANDBOX_MERCHANT_ID="recommerce";
	const SUPERLIST_SITEF_SANDBOX_MERCHANT_KEY="29A3826E6A096F48766CC1989A0EFB1A660BA8203CEC1FCDCC20D72BA9828B9B";
	
	//production endpoints
	const SUPERLIST_SITEF_PAYMENTS_CUSTOM_URL="https://esitef-ec.softwareexpress.com.br/e-sitef/Payment2";
	const SUPERLIST_SITEF_RECURRENT_CUSTOM_URL="https://esitef-ec.softwareexpress.com.br/e-sitef/Recurrent";
	
	//sandbox endpoints
	const SUPERLIST_SITEF_SANDBOX_PAYMENTS_CUSTOM_URL="https://esitef-homologacao.softwareexpress.com.br/e-sitef-hml/Payment2";	
	const SUPERLIST_SITEF_SANDBOX_RECURRENT_CUSTOM_URL="https://esitef-homologacao.softwareexpress.com.br/e-sitef-hml/Recurrent";	
	

	
	public function register() {
		add_filter(
			'woocommerce_settings_tabs_array',
			array( $this, 'add_settings_tab' ),
			50,
			1
		);
		
		add_action( 
			'woocommerce_settings_tabs_superlist_sitef', 
			array( $this, 'settings_tab' )
		);
		
		add_action( 
			'woocommerce_update_options_superlist_sitef', 
			array( $this, 'update_options' )
		);
	}
	
	public function add_settings_tab( $tabs ) {
		$tabs['superlist_sitef'] = __( 'Superlist E-SITEF', 'superlist-sitef' );
		return $tabs;
	}
	
	public function get_settings() {
		$superlist_sitef_settings = array(
			array(
				'name' => __( 'Superlist Sitef Payment Gateway Settings', 'superlist-sitef' ),
				'type' => 'title',
				'desc' => __( 'Enter general system settings for Superlist Sitef.', 'superlist-sitef' ),
				'id' => 'superlist_sitef_settings'
			),
			array(
				'name' => __( 'Merchant ID', 'superlist-sitef' ),
				'desc' => __( 'Type your Sitef\'s Merchant ID.', 'superlist-sitef' ),
				'desc_tip' => false,
				'type' => 'text',
				'id' => 'superlist_sitef_merchant_id'
			),
			array(
				'name' => __( 'Merchant Key', 'superlist-sitef' ),
				'desc' => __( 'Type your Sitef\'s Merchant Key.', 'superlist-sitef' ),
				'desc_tip' => false,
				'type' => 'text',
				'id' => 'superlist_sitef_merchant_key'
			),
			array(
				'name' => __( 'Enable Sandbox Mode', 'superlist-sitef' ),
				'desc' => __( 'Select to enable Sitef sanbox.', 'superlist-sitef' ),
				'desc_tip' => false,
				'type' => 'checkbox',
				'id' => 'superlist_sitef_sandbox_mode'
			),
			array(
				'name' => __( 'Enable Test Mode on Production', 'superlist-sitef' ),
				'desc' => __( 'Select to enable Sitef production test mode.', 'superlist-sitef' ),
				'desc_tip' => false,
				'type' => 'checkbox',
				'id' => 'superlist_sitef_test_mode'
			),
			array(
				'name' => __( 'Checkout Page', 'superlist-sitef' ),
				'desc' => __( 'The page for make payments for an autoship order', 'superlist-sitef' ),
				'desc_tip' => true,
				'type' => 'single_select_page',
				'id' => 'superlist_sitef_checkout_page_id'
			),
			array(
				'name' => __( 'Update Payment Method Page', 'superlist-sitef' ),
				'desc' => __( 'The page for payment Method updates.', 'superlist-sitef' ),
				'desc_tip' => true,
				'type' => 'single_select_page',
				'id' => 'superlist_sitef_edit_method_page_id'
			),
			array(
				'type' => 'sectionend',
				'id' => 'superlist_sitef_section_end'
			)
		);
		$settings = apply_filters( 'superlist_sitef_settings', $superlist_sitef_settings );
		return $settings;
	}
	
	public function settings_tab() {
		woocommerce_admin_fields( $this->get_settings() );
	}
	
	public function update_options() {
		woocommerce_update_options( $this->get_settings() );
	}

	public function __get($name)
	{
		return get_option("superlist_sitef_".$name);
	}

	public function getEndpoints($is_sandbox="no")
	{
		if ($is_sandbox=="yes")
		{
			return [
				'payments_custom_url'=>self::SUPERLIST_SITEF_SANDBOX_PAYMENTS_CUSTOM_URL,
				'recurrent_custom_url'=>self::SUPERLIST_SITEF_SANDBOX_RECURRENT_CUSTOM_URL,				
			];
		}
		else
		{
			return [
				'payments_custom_url'=>self::SUPERLIST_SITEF_PAYMENTS_CUSTOM_URL,
				'recurrent_custom_url'=>self::SUPERLIST_SITEF_RECURRENT_CUSTOM_URL,				
			];	
		}
	}

	public function getSitefCredentials($is_sandbox="no")
	{
		if ($is_sandbox=="yes")
		{
			return [
				'merchant_key'=>self::SUPERLIST_SITEF_SANDBOX_MERCHANT_KEY,
				'merchant_id'=>self::SUPERLIST_SITEF_SANDBOX_MERCHANT_ID				
			];
		}
		else
		{
			return [
				'merchant_key'=>$this->merchant_key,
				'merchant_id'=>$this->merchant_id				
			];	
		}
	}

	public function getPluginSettings()
	{
		return [
			'sandbox_mode'=>$this->sandbox_mode,
			'test_mode'=>$this->test_mode,
			'checkout_page_id'=>$this->checkout_page_id,
			'edit_method_page_id'=>$this->edit_method_page_id,
		];
	}
	
}