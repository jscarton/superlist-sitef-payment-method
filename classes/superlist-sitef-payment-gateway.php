<?php
$wc_autoship_path = dirname( dirname( dirname( __FILE__ ) ) ) . '/woocommerce-autoship/';
require_once( $wc_autoship_path . 'classes/wc-autoship.php' );
require_once( $wc_autoship_path . 'classes/payment-gateway/wc-autoship-payment-gateway.php' );
require_once( $wc_autoship_path . 'classes/payment-gateway/wc-autoship-payment-response.php' );
require_once( $wc_autoship_path . 'classes/wc-autoship-customer.php' );

class SuperlistSitefPaymentGateway extends WC_Autoship_Payment_Gateway{

	private $generated_token=false;

	public function __construct() {
		// WooCommerce fields
		$this->id = 'superlist_sitef';
		$this->icon = '';
		$this->order_button_text = 'Vale de Alimentaçao';
		$this->has_fields = true;
		$this->method_title = __( "Superlist + Sitef Payment Gateway ", 'superlist-sitef' );
		$this->method_description = __( 
			"Sitef payment method supporting creditcard tokenization for safe store of payment information",
			'superlist-sitef'
		);
		$this->description = $this->method_description;
		//$this->notify_url = admin_url( '/admin-ajax.php?action=wc_autoship_paypal_payments_ipn_callback' );
		// WooCommerce settings
		$this->init_form_fields();
		$this->init_settings();
		// Assign settings
		$this->title='Vale de Alimentaçao';
		$settingObj=new SuperlistSitefSettings();
		$this->plugin_settings = new SuperlistSitefBase($settingObj->getPluginSettings());
		// Supports
		$this->supports = array(
			'refunds'
		);
		// Payment gateway hooks
		add_action( 
			'woocommerce_update_options_payment_gateways_' . $this->id, 
			array( $this, 'process_admin_options' )
		);
//		add_action(
//			'woocommerce_api_wc_autoship_paypal_gateway',
//			array( $this, 'api_callback' )
//		);
	}

	public function init_form_fields()
	{
		$this->form_fields = array(
			'enabled' => array(
				'title' => __( 'Enable/Disable', 'wc-autoship' ),
				'type' => 'checkbox',
				'label' => __( 'Enable ' . $this->method_title, 'superlist-sitef' ),
				'default' => 'yes',
				'id' => 'superlist_sitef_method_enabled'
			),
			'title' => array(
				'title' => __( 'Checkout Title', 'superlist-sitef' ),
				'type' => 'text',
				'description' => __( 
					'This controls the title which the user sees during checkout.', 'superlist-sitef'
				),
				'default' => __( 'Sitef', 'superlist-sitef' ),
				'desc_tip' => true,
				'id' => 'superlist_sitef_checkout_title'
			)
			);
	}

	public function payment_fields() {
		$current_user=wp_get_current_user();
		$token=null;
		if ($current_user)
		{
			$token=$this->retrieveTokenFromDB($current_user->ID);
		}
		include dirname( dirname( __FILE__ ) ) . '/templates/frontend/payment-fields.php';
	}
	/**
	* Get the field names posted by the payment_fields form
	* @return string[]
	*/
	public function get_payment_field_names(){
		$field_names=[
			"superlist-sitef-use-this",
			"superlist-sitef-card-name",
			"superlist-sitef-number",
			"superlist-sitef-expiry",
			"superlist-sitef-cvc",
			"is_sodexo_alimentacao_available"
		];
	}

	public function process_payment( $order_id ) {
		global $wpdb;
		$woocommerce = WC();
		
		// Get order
		$order = new WC_Order( $order_id );


		if ($this->plugin_settings->local_test_mode=='yes')
		{
			$order->add_order_note( __( 'Superlist+Sitef: local test mode ON.', 'superlist-sitef' ) );
			$order->payment_complete(time());
			return array(
							'result'   => 'success',
							'redirect' => $this->get_return_url( $order ),
						);
		}
		// Get totals
		$total = $order->get_total();
		$total_shipping = $order->get_total_shipping();
		$total_tax = $order->get_total_tax();
		$total=intval(($total-$total_shipping)*100);			
		

		$precision = get_option( 'woocommerce_price_num_decimals' );

		//initialize the Payu Integration
		$CreditCardAPI= new SuperlistSitefCreditCard($order);
		//create and store the card on payu
		$customer= $order->get_user();
		if (!$customer)
		{
			wc_add_notice(
				__( 'Error: guest checkout is not currently supported', 'superlist-sitef' ),
				'error'
			);
			return;
		}
		else
		{
			try{				

				$getTransaction= $CreditCardAPI->beginTransaction($total,"SLO_".$order->id."_".time());
				if (is_object($getTransaction) && isset($getTransaction->transactionResponse->message) && $getTransaction->transactionResponse->message=='OK')
				{
					$CUSTOMER_ID=$customer->ID;
					$customer_billing_dni=($customer->billing_persontype==1)?$customer->billing_cpf:$customer->billing_cnpj;
					$customer_billing_dni=str_replace([".","-"],["",""], $customer_billing_dni);
					if (isset($_POST['superlist-sitef-use-this']) && $_POST['superlist-sitef-use-this']=='new'){
						//remove previously stored token
						//$removeStoredCard=$CreditCardAPI->removeStoredCard($customer->ID);
						//if ($removeStoredCard===FALSE)
						//	throw new Exception("erro de conexão com gateway de pagamento, por favor tente novamente (504)", 500);						
						//if (is_object($removeStoredCard) && isset($removeStoredCard->error))
						//	throw new Exception($removeStoredCard->message, 500);		
						//add_post_meta( $order->id, 'por_aqui_paso',"pa vete");						

						$PAYER_NAME=trim($_POST['superlist-sitef-card-name']);
						$CREDIT_CARD_NUMBER=trim(str_replace(" ","",$_POST['superlist-sitef-number']));
						$expiration_date=trim(str_replace([" ","/"],["","-"],$_POST['superlist-sitef-expiry']));
						$CREDIT_CARD_EXPIRATION_DATE=date ("my",strtotime("28-".$expiration_date));
						//get the authorizer ID
						$PAYMENT_METHOD=$CreditCardAPI->getCardInfo($CREDIT_CARD_NUMBER);
						add_post_meta( $order->id, 'sitef_authorizer_id',$PAYMENT_METHOD);
						$cardQuery=$CreditCardAPI->doCardQuery($PAYMENT_METHOD,$CREDIT_CARD_NUMBER,$getTransaction->transactionResponse->nit);
						add_post_meta( $order->id, 'sitef_cardquery',$CreditCardAPI->getDebugTrace()['response']);						
						if (is_object($cardQuery) && isset($cardQuery->cardQueryResponse->responseCode) && $cardQuery->cardQueryResponse->responseCode==0)
							$PAYMENT_METHOD=$cardQuery->cardQueryResponse->authorizerId;						
						
						if ($PAYMENT_METHOD==0)
							throw new Exception("Erro de conexão ao entrar em contato com o gateway de pagamento (503)", 500);	
						if ($PAYMENT_METHOD==280 && $_POST['is_sodexo_alimentacao_available']!=1)
							throw new Exception("Sodexo Alimentaçao não está disponível como forma de pagamento para compras contendo bebidas alcoólicas, produtos de escritório ou produtos para pets.", 500);	

						
						if($customer->billing_persontype!=1){

							if( $_POST['superlist-sitef-cpf']!=='')
								$PAYER_DNI=trim(strtoupper($_POST['superlist-sitef-cpf']));
							else
								throw new Exception("O campo cpf não pode estar em branco", 500);
						}
						else
						{
							$PAYER_DNI=trim(strtoupper($customer_billing_dni));
						}										
						//$response=$CreditCardAPI->storeCard($PAYMENT_METHOD,$CREDIT_CARD_EXPIRATION_DATE,$CREDIT_CARD_NUMBER,$PAYER_DNI,$CUSTOMER_ID);
						////store the token for future processing
						//if ($response->storeResponse->status=='CON' || $response->storeResponse->status=='DUP')  
						//{
						//	$this->generated_token=$response->storeResponse->cardHash;
						//	$nita_id=$response->storeResponse->nita;
						//	$order->add_order_note( __( 'Superlist+Sitef: new payment method saved.', 'superlist-sitef' ) );
						//	//ensure only one token by customer
						//	$this->deleteTokenFromDB($CUSTOMER_ID);
						//	//store token on table
						//	$payment_method_id=$this->storeTokenOnDB($response);
						//}
						//else
						//	throw new Exception($response->storeResponse->message, 500);

					}
					else
					{
						$token= $this->retrieveTokenFromDB($customer->ID);
						if (!$token)
						{
							throw new Exception("Erro: leyendo cartão armazenado", 500);
						}
						$this->generated_token=$token->data->credit_card_token_id;
						$PAYER_NAME=$customer->billing_first_name." ".$customer->billing_last_name;
						$PAYMENT_METHOD=$token->data->payment_method;
						$payment_method_id=$token->data->id;
						$nita_id=$token->data->nita;
					}
					//get the security number
					$PAYMENT_CCV=trim($_POST['superlist-sitef-cvc']);
					//now make the charge to the stored card
					add_post_meta( $order->id, 'sitef_dni_'.time(),$customer_billing_dni);
					//$checkStatus= $CreditCardAPI->callStatus($nita_id);
					//if (is_object($checkStatus) && isset($checkStatus->status) && $checkStatus->status=='OK'){					
							//$payment=$CreditCardAPI->doHashPayment ($PAYMENT_METHOD,"true",$this->generated_token,$PAYMENT_CCV,$getTransaction->transactionResponse->nit,$customer_billing_dni);						
							$payment=$CreditCardAPI->doPayment ($PAYMENT_METHOD,"true",$CREDIT_CARD_EXPIRATION_DATE,$CREDIT_CARD_NUMBER,$PAYMENT_CCV,$customer_billing_dni,"",$getTransaction->transactionResponse->nit);						
							$order->add_order_note( __( 'Superlist+Sitef: using this payment method.'.$PAYER_NAME, 'superlist-sitef' ) );
							add_post_meta( $order->id, 'sitef_responsex_'.time(), json_encode($payment) );
							add_post_meta( $order->id, 'sitef_config_'.time(), json_encode($CreditCardAPI->dumpIt()) );
							add_post_meta( $order->id, 'sitef_request_'.time(), json_encode($CreditCardAPI->getDebugTrace()['request']) );
							add_post_meta( $order->id, 'sitef_response_'.time(), json_encode($CreditCardAPI->getDebugTrace()['response']) );
							if ($payment)							
							{
								if (is_object($payment) && $payment->paymentResponse->transactionStatus=="CON")
								{
									// Payment has been successful
									$order->add_order_note( 'Superlist+Sitef payment completed.');
									$order->add_order_note( 'transactionId:'+$payment->paymentResponse->hostUSN);
									$order->add_order_note( 'transactionStatus:'+$payment->paymentResponse->transactionStatus);
									add_post_meta( $order->id, 'sitef_customer_receipt_'.time(), json_encode($payment->paymentResponse->customerReceipt) );
							        add_post_meta( $order->id, 'sitef_superlist_receipt_'.time(), json_encode($payment->paymentResponse->merchantReceipt) );
									// Mark order as Paid
									$order->payment_complete( $payment->paymentResponse->esitefUSN);
									// Empty the cart (Very important step)
									$woocommerce->cart->empty_cart();
									if(isset($_POST['superlist-sitef-use-this']) && $_POST['superlist-sitef-use-this']=='new'){										
										//create the autoship customer 
										$wc_autoship_customer = new WC_Autoship_Customer( $customer->ID );
										$payment_method_data = [];
										$wc_autoship_customer->store_payment_method($this->id, $payment_method_id, $payment_method_data);
									}
									add_post_meta( $order->id, 'superlist_sitef_token_id', $payment_method_id );
									add_post_meta( $order->id, 'superlist_payment_method', $this->id);
									add_post_meta( $order->id, 'superlist_payment_flag'  , "SODEXO");
									// Redirect to thank you page
									return array(
										'result'   => 'success',
										'redirect' => $this->get_return_url( $order ),
									);
								}
								else
								{
									if (is_object($payment))
									{
										if ($payment->paymentResponse->transactionStatus=="DUP")
										{
											$order->update_status("wc-on-hold");
											wp_mail("developers@superlist.com", "[URGENT] MANUAL CHECK REQUIRED FOR CUSTOMER ORDER(".$order->id.")", "error message".json_encode($payment),[],[]);
											return array(
											'result'   => 'success',
											'redirect' => $this->get_return_url( $order ),
											);
										}
										else
											throw new Exception($payment->paymentResponse->message, 500);
									}
									else
									{
										//mark as on hold
										$order->update_status("wc-on-hold");
										wp_mail("developers@superlist.com", "[URGENT] MANUAL CHECK REQUIRED FOR CUSTOMER ORDER(".$order->id.")", "error message".json_encode($payment),[],[]);
										return array(
										'result'   => 'success',
										'redirect' => $this->get_return_url( $order ),
										);
									}
								}
								
							}
							else
								throw new Exception( "Erro: erro en transação:",500);					
					//}
					//else
					//	throw new Exception( $checkStatus->message,500);
				}
				else
					throw new Exception( $getTransaction->message,500);

			}
			catch(Exception $e)
			{
				throw new Exception( "Erro:".$e->getMessage() );
			}
		}
		return;
	}
	
	/**
	 * Process an order using a stored payment method
	 * @param WC_Order $order
	 * @param WC_Autoship_Customer $customer
	 * @return WC_Autoship_Payment_Response
	 */
	public function process_stored_payment( WC_Order $order, WC_Autoship_Customer $customer ) {

		// Create payment response
		$payment_response = new WC_Autoship_Payment_Response();
		$payment_response->success = false;
		$payment_response->status = "SUPERLIST: SITEF DOES NOT SUPPORT RECURRING PAYMENTS RIGHT NOW";		
		return $payment_response;
	}

	public function store_payment_method( WC_Autoship_Customer $customer, $payment_fields = array() ) {
		try{
			
			if (isset($_POST['superlist-sitef-use-this']) && $_POST['superlist-sitef-use-this']=='new')
			{
				//initialize the Payu Integration
				$CreditCardAPI= new SuperlistPayuCreditCard();
				//store the new credit card
				$user=$customer->get_user();
				$CUSTOMER_ID=$user->ID;
				//Set new credit card data
				$PAYER_NAME=trim($_POST['superlist-sitef-card-name']);
				$CREDIT_CARD_NUMBER=trim(str_replace(" ","",$_POST['superlist-sitef-number']));
				if(empty($_POST['superlist-sitef-expiry'])){
					$expiration_date = trim($_POST['validade_1'].'-'.$_POST['validade_2']);
				}else{
					$expiration_date=trim(str_replace([" ","/"],["","-"],$_POST['superlist-sitef-expiry']));
				}
				$CREDIT_CARD_EXPIRATION_DATE=date ("Y/m",strtotime("28-".$expiration_date));
				$PAYMENT_METHOD=trim(strtoupper($_POST['superlist-sitef-card-type']));
				$PAYER_DNI=trim(strtoupper($_POST['superlist-sitef-dni']));
				$response=$CreditCardAPI->createCreditCard($CUSTOMER_ID,$PAYER_NAME,$CREDIT_CARD_NUMBER,$CREDIT_CARD_EXPIRATION_DATE,$PAYMENT_METHOD,$PAYER_DNI);	
				//check if there is a previous card
				$token= $this->retrieveTokenFromDB($CUSTOMER_ID);
				if ($response->code=="SUCCESS" && isset($response->creditCardToken))
				{
					//remove previous card from db
					if (!is_null($token))
					{
						$y=$this->deleteTokenFromDB($CUSTOMER_ID);
					
					}
					//store token on table
					$payment_method_id=$this->storeTokenOnDB($response);
					//create the autoship customer 
					$payment_method_data = [];
					$customer->store_payment_method($this->id, $payment_method_id, $payment_method_data);
					
					return true;
				}
			}
			else{
				//return get_permalink($this->plugin_settings->edit_method_page_id);
			}
		}
		catch (Exception $e){
			//return false;
			//exit;
		}
		//return false;
	}
	
	public function validate_fields() {
		return true;
	}
	
	/**
	 * Get the payment method description for a customer in HTML format
	 * @param WC_Autoship_Customer $customer
	 * @return string
	 */
	public function get_payment_method_description( WC_Autoship_Customer $customer ) {
		$payment_method_data = $customer->get_payment_method_data();
		if ( empty( $payment_method_data ) ) {
			return '';
		}
		$description = array( '<div class="paypal-description">' );
		$description[] = '<img src="https://www.paypalobjects.com/webstatic/mktg/logo/pp_cc_mark_37x23.jpg" '
				. 'alt="PayPal" /><br />';
		if ( isset( $payment_method_data['email'] ) ) {
			$description[] = ' <span>' . esc_html( $payment_method_data['email'] ) . '</span>';
		}
		$description[] = '</div>';
		return implode( '', $description );
	}

	public function process_admin_options() {
		parent::process_admin_options();
	}

	private function retrieveTokenFromDB($user_id)
	{
		global $wpdb;
		$table_name= $wpdb->prefix . SuperlistSitefSetup::PREFIX."creditcards";
		$rs=$wpdb->get_row( "SELECT * FROM $table_name WHERE payer_id = $user_id");
		if ($rs)
			return new SuperlistSitefBase(['data'=>$rs]);
		return NULL;
	}

	private function deleteTokenFromDB($user_id)
	{
		global $wpdb;
		$table_name= $wpdb->prefix . SuperlistSitefSetup::PREFIX."creditcards";
		$response=$wpdb->delete( $table_name, array( 'payer_id' => $user_id ) );
		return $response;
	}

	private function storeTokenOnDB($response)
	{		
		global $wpdb;
		$table_name= $wpdb->prefix . SuperlistSitefSetup::PREFIX."creditcards";
			$data=[
				"credit_card_token_id"=>$response->storeResponse->cardHash,				
				"payer_id"=>$response->storeResponse->merchantUSN,
				"payer_dni"=>$response->storeResponse->customerId,
				"payment_method"=>$response->storeResponse->authorizerId,
				"payment_maskednumber"=>"**********".$response->storeResponse->cardSuffix,
				"nita"=>$response->storeResponse->nita
			];
			$sql=$wpdb->prepare("insert into $table_name (credit_card_token_id,payer_id,payer_dni,payment_method,payment_maskednumber,nita) values ('%s','%d','%s','%s','%s','%s')",
								$response->storeResponse->cardHash,				
								$response->storeResponse->merchantUSN,
								$response->storeResponse->customerId,
								$response->storeResponse->authorizerId,
								"**********".$response->storeResponse->cardSuffix,
								$response->storeResponse->nita);
			$insert_ret=$wpdb->query($sql);		
			return $wpdb->insert_id;
	}

	private function getResponseCodeMessage($response_code)
	{
		$response_messages=[
			"ERROR"=>"Ocorreu um erro geral.",
			"APPROVED"=>"A transação foi aprovada.",
			"ANTIFRAUD_REJECTED"=>"A transação foi rejeitada pelo sistema anti fraude.",
			"PAYMENT_NETWORK_REJECTED"=>"A rede financeira rejeitou a transação.",
			"ENTITY_DECLINED"=>"A transação foi rejeitada pela rede financeira. Por favor, informe-se no seu banco ou na sua operadora de cartão de crédito.",
			"INTERNAL_PAYMENT_PROVIDER_ERROR"=>"Ocorreu um erro no sistema tentando processar o pagamento.",
			"INACTIVE_PAYMENT_PROVIDER"=>"O fornecedor de pagamentos não estava ativo.",
			"DIGITAL_CERTIFICATE_NOT_FOUND"=>"A rede financeira relatou um erro na autenticação.",
			"INVALID_EXPIRATION_DATE_OR_SECURITY_CODE"=>"O código de segurança ou a data de expiração estava inválido.",
			"INVALID_RESPONSE_PARTIAL_APPROVAL"=>"Tipo de resposta inválida. A entidade financeira aprovou parcialmente a transação e deve ser cancelado automaticamente pelo sistema.",
			"INSUFFICIENT_FUNDS"=>"A conta não tinha crédito suficiente.",
			"CREDIT_CARD_NOT_AUTHORIZED_FOR_INTERNET_TRANSACTIONS"=>"O cartão de crédito não estava autorizado para transações pela Internet.",
			"INVALID_TRANSACTION"=>"A rede financeira relatou que a transação foi inválida.",
			"INVALID_CARD"=>"O cartão é inválido.",
			"EXPIRED_CARD"=>"O cartão já expirou.",
			"RESTRICTED_CARD"=>"O cartão apresenta uma restrição.",
			"CONTACT_THE_ENTITY"=>"Você deve entrar em contato com o banco.",
			"REPEAT_TRANSACTION"=>"Deve-se repetir a transação.",
			"ENTITY_MESSAGING_ERROR"=>"A rede financeira relatou um erro de comunicações com o banco.",
			"BANK_UNREACHABLE"=>"O banco não se encontrava disponível.",
			"EXCEEDED_AMOUNT"=>"A transação excede um montante estabelecido pelo banco.",
			"NOT_ACCEPTED_TRANSACTION"=>"A transação não foi aceita pelo banco por algum motivo.",
			"ERROR_CONVERTING_TRANSACTION_AMOUNTS"=>"Ocorreu um erro convertendo os montantes para a moeda de pagamento.",
			"EXPIRED_TRANSACTION"=>"A transação expirou.",
			"PENDING_TRANSACTION_REVIEW"=>"A transação foi parada e deve ser revista, isto pode ocorrer por filtros de segurança.",
			"PENDING_TRANSACTION_CONFIRMATION"=>"A transação está pendente de confirmação.",
			"PENDING_TRANSACTION_TRANSMISSION"=>"A transação está pendente para ser transmitida para a rede financeira. Normalmente isto se aplica para transações com formas de pagamento em dinheiro.",
			"PAYMENT_NETWORK_BAD_RESPONSE"=>"A mensagem retornada pela rede financeira é inconsistente.",
			"PAYMENT_NETWORK_NO_CONNECTION"=>"Não foi possível realizar a conexão com a rede financeira.",
			"PAYMENT_NETWORK_NO_RESPONSE"=>"A rede financeira não respondeu.",
		];
		if (isset($response_messages[$response_code]))
			return $response_messages[$response_code];
		else
			return $response_messages["ERROR"];
	}
}
