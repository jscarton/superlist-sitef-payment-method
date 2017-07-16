<?php

class SuperlistSitefShortcodes{

	public function register()
	{
		add_shortcode( 'superlist-sitef-test', [$this,"do_test"] );
		add_shortcode( 'superlist-sitef-hash-test', [$this,"do_hash_test"] );			
	}

	public function do_test($attr)
	{
		//try to create a credit card
		$cc=null;
		$response1=false;
		$response2=false;
		try{
			$cc= new SuperlistSitefCreditCard();
			$response1= $cc->beginTransaction(100,'order-7865');
			var_dump($response1->transactionResponse->nit);
			$response2=$cc->doPayment (1,"true","0217","4111111111111111","123",297,$extraField="",$response1->transactionResponse->nit);
			var_dump($response2);
		}
		catch (Exception $e)
		{
			var_dump($e);
		}
	}
	public function do_hash_test($attr)
	{
		//try to create a credit card
		$cc=null;
		$response1=false;
		$response2=false;
		$response3=false;
		$response4=false;
		try{
			$cc= new SuperlistSitefCreditCard();
			//store creditcard
			
			$response1= $cc->storeCard(2,'0217','5000000000000001','96355072403','297');
			echo "<strong>Calling Store WS...</strong><br/>";
			echo "cardHash:<br/>";
			var_dump($response1->storeResponse->cardHash);
			echo "<br/>nita:<br/>";
			var_dump($response1->storeResponse->nita);
			echo "<br/>message:<br/>";
			var_dump($response1->storeResponse->message);
			echo "<br/>status:<br/>";
			var_dump($response1->storeResponse->status);
			//check status
			if ($response1->storeResponse->status=='CON' || $response1->storeResponse->status=='DUP'){			
				$response2= $cc->callStatus($response1->storeResponse->nita);
				echo "<br/><strong>Calling callStatus WS...</strong><br/>";
				echo "status:<br/>";
				var_dump($response2->status);
				if ($response2->status=='OK'){
					$response3= $cc->beginTransaction(100,'order-7866');
					echo "<br/><strong>Calling beginTransaction WS...</strong><br/>";
					echo "nit:<br/>";
					var_dump($response3->transactionResponse->nit);
					$response4=$cc->doHashPayment (2,"true",$response1->storeResponse->cardHash,"123",$response3->transactionResponse->nit);
					echo "<br/><strong>Calling doHashPayment WS...</strong><br/>";
				    echo "status:<br/>";
					var_dump($response4->paymentResponse->transactionStatus);
					echo "<br/>message:<br/>";
					var_dump($response4->paymentResponse->message);
					echo "<br/>customerReceipt:<br/><pre>";
					echo $response4->paymentResponse->customerReceipt;
					echo "</pre><br/>merchantReceipt:<br/><pre>";
					echo $response4->paymentResponse->merchantReceipt."</pre>";
				}
			}
		}
		catch (Exception $e)
		{
			var_dump($e);
		}
	}  	
}