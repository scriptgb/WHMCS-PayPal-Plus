<?php
/**
 * Módulo PayPal Plus para WHMCS
 * @author		Mauricio Gofas | gofas.net
 * @see			https://gofas.net/?p=8294
 * @copyright	2017 https://gofas.net
 * @license		https://gofas.net/?p=9340
 * @support		https://gofas.net/?p=7858
 * @version		1.1.1
 */

// Report simple running errors
error_reporting( E_ERROR | E_WARNING | E_PARSE );

use Illuminate\Database\Capsule\Manager as Capsule;

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

//Function to check if the request is an AJAX request
function is_ajax() {
	return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

if ( is_ajax() ) {
	// Puxa parâmetros de configuração do gateway
	$params	= getGatewayVariables('gofaspaypalplus');
	if (!$params['type']) { die("Module Not Activated"); } // Encerra se o módulo está inativo.
	
	$sandbox			= $params['sandboxmode'];
	if ($sandbox) {
		$pp_host			= 'https://api.sandbox.paypal.com';
		$client_id			= $params['clientidsandbox'];
	}
	elseif(!$sandbox) {
		$pp_host			= 'https://api.paypal.com';
		$client_id			= $params['clientid'];
	}
	if($params['admin']) {
		$whmcsAdmin			= $params['admin'];
	}elseif(!$params['admin']){
		$whmcsAdmin 		= 1;
	}
	$debug				= $params['debugmode'];

	session_start();
	$access_token				= $_SESSION['access_token'];
	$payment_id					= $_SESSION['payment_id'];
	$wh_remembered_cards		= $_SESSION['wh_remembered_cards'];
	$payer_id					= $_POST["payer_id"];
	$user_id					= $_POST['user_id'];
	$invoice_id					= $_POST['invoice_id'];
	$pp_remembered_cards		= $_POST['pp_remembered_cards'];
		
	$efetuePayment				= '{ "payer_id" : "'.$payer_id.'" }';
		
	$EPcurl = curl_init($pp_host.'/v1/payments/payment/'.$payment_id.'/execute/'); 
	curl_setopt($EPcurl, CURLOPT_POST, true);
	curl_setopt($EPcurl, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($EPcurl, CURLOPT_HEADER, false);
	curl_setopt($EPcurl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($EPcurl, CURLOPT_HTTPHEADER, array(
		'Authorization: Bearer '.$access_token,
		'Accept: application/json',
		'Content-Type: application/json',
		'PayPal-Partner-Attribution-Id: WHMCS_Ecom_PPPlus'
	));
	
	curl_setopt($EPcurl, CURLOPT_POSTFIELDS, $efetuePayment ); 
		
	$EPresponse			= curl_exec( $EPcurl );
	$EPerror			= curl_error( $EPcurl );
	$EPinfo				= curl_getinfo( $EPcurl );
		
	curl_close( $EPcurl ); // close cURL handler
	    
	$EParrayResponse	= json_decode( $EPresponse, true ); // Convert the result from JSON format to a PHP array
	$EPpaytId			= $EParrayResponse['id'];
	$paymentState		= $EParrayResponse['state'];
	$invoice_number		= $EParrayResponse['transactions']['0']['invoice_number'];
	$EPamount			= $EParrayResponse['transactions']['0']['item_list']['items']['0']['price'];
	$EPpaymentFee		= $EParrayResponse['transactions']['0']['related_resources']['0']['sale']['transaction_fee']['value'];
	$EPpayState			= $EParrayResponse['transactions']['0']['related_resources']['0']['sale']['state'];
		
	// completa a confirmação de pagamento na visualização da fatura
	if ( is_string( $paymentState ) ) {
		echo $paymentState;
		if ( $debug ) { echo '<br>'; print_r($EPresponse); }
	}
	elseif ( is_array( $paymentState ) ) {
		print_r($paymentState);
		if ( $debug ) { echo '<br>'; print_r($EPresponse); }
	}
	
	if( $paymentState == "approved" ) {
			
		// Registra transação no log do whmcs
		logTransaction($params['paymentmethod'], $EPresponse, $paymentState.'->'.$EPpayState);

		// Adiciona o pagamento a fatura =)
		addInvoicePayment(
			$invoice_number, // Invoice ID
			$EPpaytId, // Transaction ID
			$EPamount, // Payment Amount
			$EPpaymentFee, // Payment Fee
			$params['paymentmethod']
		);

	}
		
	if( $paymentState == "pending" ) {
		
		// Verifica transações associadas à fatura						
		$getinvoiceid['invoiceid']	= $invoice_id;
		$GetInvoiceResults			= localAPI( 'getinvoice', $getinvoiceid, $whmcsAdmin );

		$transIDendA					= $GetInvoiceResults['transactions'];
		if($transIDendA) {
			$transIDend					= $transIDendA['transaction'];
		}
		if ($transIDend) {
			$transIDp					= end($transIDend);
			$trans_id					= (string)$transIDp['id'];
		} 

		// Atualiza transação
		$update_trans_['transactionid'] = $trans_id;
		$update_trans_['description'] = 'Pagamento pendente / em análise.';
 
 		$update_trans = localAPI( 'updatetransaction', $update_trans_, $whmcsAdmin );
			
		if ( $debug and $update_trans['result'] === 'success') {
			echo'<pre class="debug"><p class="ok">Transação atualizada com sucesso - API WHMCS.</p>';
			print_r($update_trans);
			echo'<br/></pre>';
		} elseif ($debug and $update_trans['result'] !== 'success'){
			echo'<pre class="debug"><p class="erro">Erro ao atualizar a transação - API WHMCS.</p>', $user_id;
			print_r($update_trans);
			echo'<br/></pre>';
		}

	}
	
	// Salva remembered_cards ID
	
	$rememberedCards_data	= Capsule::table('gofaspaypalplus')
		->where('user_id', $userID )
		->select('user_id','remembered_cards', 'api_clientid')
		->get();
	
	$api_clientid = end($rememberedCards_data)->api_clientid;
	
	if ( $pp_remembered_cards and !$wh_remembered_cards ) {
			
		try {
			$insert_wh_remembered_cards = Capsule::table('gofaspaypalplus')
				->insert([
				'user_id' => $user_id,
				'payer_id' => $payer_id,
				'remembered_cards' => $pp_remembered_cards,
				'api_clientid' => $client_id,
				 ]);
				
			if ( $debug ) { echo '$add_wh_remembered_cards: ' , $insert_wh_remembered_cards; } 

		} catch (\Exception $e) {
    			
			if ( $debug ) { echo "Não foi possível gravar os dados do cartão do cliente. {$e->getMessage()}"; }
		} 
	} 
		
	if ( $pp_remembered_cards and $wh_remembered_cards and $pp_remembered_cards !== $wh_remembered_cardsand /*and $api_clientid !== $client_id*/ ) {
		try {
			$update_wh_remembered_cards = Capsule::table('gofaspaypalplus')
				->where('user_id', $user_id)
				->update([ 'remembered_cards' => $pp_remembered_cards ]);
				//->update([ 'payer_id' => $payer_id, 'remembered_cards' => $pp_remembered_cards, 'api_clientid' => $client_id ]);
				if ( $debug ) { 
					echo '$update_wh_remembered_cards: ' , $update_wh_remembered_cards, '<br>';  // Debug
					echo '$wh_remembered_cards: ', $wh_remembered_cards;
				}
					
		} catch (\Exception $e) {
    		if ( $debug ) { echo "Não foi possível atualizar os dados do cartão do cliente. {$e->getMessage()}"; }
		}
	} // End Salva remembered_cards ID
	
	if ($EPerror) {
		echo 'Erro: ';
		print_r($EPerror);
	}
}
?>