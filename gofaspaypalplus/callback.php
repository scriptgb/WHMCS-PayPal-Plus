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
 
// Report errors
error_reporting( E_ERROR | E_WARNING | E_PARSE );

 // Require libraries needed for gateway module functions.
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';
use Illuminate\Database\Capsule\Manager as Capsule;

// Puxa parâmetros de configuração do gateway
$params			= getGatewayVariables('gofaspaypalplus');
$systemUrl		= $params['systemurl'];

if ( $customadminpath ) {
	$systemAdminUrl	= $systemUrl.$customadminpath;
}
else {
	$systemAdminUrl	= $systemUrl.'/admin/';
}

$sandbox		= $params['sandboxmode'];
$logcalls		= $params['logcallbackmode'];

if ($sandbox) {
	$client_id			= $params['clientidsandbox'];
	$client_secret		= $params['clientsecretsandbox'];
	$pp_host			= 'https://api.sandbox.paypal.com';
	$pp_mode			= 'sandbox';
}

elseif(!$sandbox) {
	$client_id			= $params['clientid'];
	$client_secret		= $params['clientsecret'];
	$pp_host			= 'https://api.paypal.com';
	$pp_mode			= 'live';
}

if($params['admin']) {
	$whmcsAdmin			= $params['admin'];
}

elseif(!$params['admin']){
	$whmcsAdmin 		= 1;
}
$systemUrl			= $params['systemurl'];

// Morre se o módulo está inativo.
if (!$params['type']) {
	die("Module Not Activated");
}

/**
 *
 * Receive data
 *
 */

$raw_post_data = file_get_contents('php://input'); // Post
$raw_post_array = json_decode( $raw_post_data, TRUE ); // json to php array

if ($logcalls) {
		$log_description	.= '[Gofas Paypal Plus] Evento recebido: ' . $raw_post_data;
}

if ( !empty( $raw_post_array['id'] ) and !empty( $raw_post_array['links']['0']['href'] ) ) {
	
	$event_id 			= (string)$raw_post_array['id'];
	$event_url 			= $raw_post_array['links']['0']['href'];
	$parent_payment		= $raw_post_array['resource']['parent_payment'];
	$parent_payment_url	= $raw_post_array['resource']['links']['2']['href'];

	/**
	*
	* Obtem o access_token
	*
	**/
		
	$TKNcurl = curl_init( $pp_host.'/v1/oauth2/token' ); 
	curl_setopt($TKNcurl, CURLOPT_POST, true); 
	curl_setopt($TKNcurl, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($TKNcurl, CURLOPT_USERPWD, $client_id .':'. $client_secret);
	curl_setopt($TKNcurl, CURLOPT_HEADER, false); 
	curl_setopt($TKNcurl, CURLOPT_RETURNTRANSFER, true); 
	curl_setopt($TKNcurl, CURLOPT_POSTFIELDS, "grant_type=client_credentials"); 
		
	$TKNresponse = curl_exec( $TKNcurl );
	$TKNerror = curl_error( $TKNcurl );
	$TKNinfo = curl_getinfo( $TKNcurl );
	curl_close( $TKNcurl ); // close cURL handler
	
	$TKNarrayResponse = json_decode( $TKNresponse ); // JSON to PHP array 
	$access_token = $TKNarrayResponse->access_token; // Access Token
		
	if ($TKNerror) {
		$error .= $TKNerror;
	} // Erro
		
	if ($TKNarrayResponse->error) {
		$error .= $TKNarrayResponse->error_description;
	}
		
	/**
	*
	* Verifica evento
	*
	*/
	if( $access_token and !$error ) {
		
		$EVENTcurl = curl_init( $event_url ); //$pp_host.'/v1/notifications/webhooks-events/'.$event_id); 
		curl_setopt($EVENTcurl, CURLOPT_POST, false);
		curl_setopt($EVENTcurl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($EVENTcurl, CURLOPT_HEADER, false);
		curl_setopt($EVENTcurl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($EVENTcurl, CURLOPT_HTTPHEADER, array(
				'Authorization: Bearer '.$access_token,
				'Accept: application/json',
				'Content-Type: application/json',
				'PayPal-Partner-Attribution-Id: WHMCS_Ecom_PPPlus'
				));
		
		$EVENTresponse = curl_exec( $EVENTcurl );
		$EVENTerror = curl_error( $EVENTcurl );
	    $EVENTinfo = curl_getinfo( $EVENTcurl );
		curl_close( $EVENTcurl ); // close cURL handler
	    
		$EVENTarrayResponse = json_decode( $EVENTresponse, TRUE ); // JSON to PHP array
			
		$v_event_id				= (string)$EVENTarrayResponse['id'];
		$v_event_url 			= $EVENTarrayResponse['links']['0']['href'];
		$v_parent_payment		= $EVENTarrayResponse['resource']['parent_payment'];
		$v_parent_payment_url	= $EVENTarrayResponse['resource']['links']['2']['href'];
					
		// Erros
		if ( $EVENTerror ) {
			$error .= $EVENTerror;
		} 
			
		if ( $EVENTarrayResponse->error ) {
			$error .= $EVENTarrayResponse->error_description;
		}
		if ($logcalls) {
			$log_description	.=  ' #### Evento verificado: ' .$EVENTresponse;
		}
	}
	
	/**
	*
	* Verifica transação
	*
	*/
	
	if ( $event_id === $v_event_id ) {
				
		$PAYMENTcurl = curl_init( $v_parent_payment_url );
		curl_setopt($PAYMENTcurl, CURLOPT_POST, false);
		curl_setopt($PAYMENTcurl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($PAYMENTcurl, CURLOPT_HEADER, false);
		curl_setopt($PAYMENTcurl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($PAYMENTcurl, CURLOPT_HTTPHEADER, array(
				'Authorization: Bearer '.$access_token,
				'Accept: application/json',
				'Content-Type: application/json',
				'PayPal-Partner-Attribution-Id: WHMCS_Ecom_PPPlus'
				));
		
		$PAYMENTresponse = curl_exec( $PAYMENTcurl );
		$PAYMENTerror = curl_error( $PAYMENTcurl );
	   	$PAYMENTinfo = curl_getinfo( $PAYMENTcurl );
		curl_close( $PAYMENTcurl ); // close cURL handler
	   
		$PAYMENTarrayResponse = json_decode( $PAYMENTresponse, TRUE ); // JSON to PHP array
			
		$v_parent_payment_id			= $PAYMENTarrayResponse['id'];
		$v_parent_payment_state			= $PAYMENTarrayResponse['state'];
		$invoice_number					= $PAYMENTarrayResponse['transactions']['0']['invoice_number'];
		$v_parent_payment_saleid		= $PAYMENTarrayResponse['transactions']['0']['related_resources']['0']['sale']['id'];
		$v_parent_payment_sale_state	= (string)$PAYMENTarrayResponse['transactions']['0']['related_resources']['0']['sale']['state'];
		$v_parent_payment_price			= $PAYMENTarrayResponse['transactions']['0']['related_resources']['0']['sale']['amount']['total'];
		$v_parent_payment_fee			= $PAYMENTarrayResponse['transactions']['0']['related_resources']['0']['sale']['transaction_fee']['value'];
				
		if ($logcalls) {
			$log_description	.=
			' #### Variáveis da transação: '
			. $v_parent_payment_id . '|' 
			. $v_parent_payment_state . '|'
			. $invoice_number . '|' 
			. $v_parent_payment_saleid . '|' 
			. $v_parent_payment_id . '|' 
			. $v_parent_payment_sale_state . '|' 
			. $v_parent_payment_price . '|' 
			. $v_parent_payment_fee;
		}
						
		// Erros
		if ( $PAYMENTerror ) {
			$error .= $PAYMENTerror;
		} 
			
		if ( $PAYMENTarrayResponse->error ) {
				$error .= $PAYMENTarrayResponse->error_description;
		}
				
		if ($logcalls) {
			$log_description	.=  ' #### Dados da transação: ' . $PAYMENTresponse;
		}
	}
			
	/**
	*
	* Verifica fatura
	*
	*/
	if ( $invoice_number ) {
							
		$getinvoiceid['invoiceid']	= $invoice_number;
		$GetInvoiceResults			= localAPI( 'getinvoice', $getinvoiceid, $whmcsAdmin);
			
		$invoice_status	= $GetInvoiceResults['status'];
		$invoice_total	= $GetInvoiceResults['total'];
		$invoice_user	= $GetInvoiceResults['userid'];
				
		// Verifica transações associadas à fatura
		$transIDendA					= $GetInvoiceResults['transactions'];
		if($transIDendA) {
			$transIDend					= $transIDendA['transaction'];
		}
		if ($transIDend) {
			$transIDp					= end($transIDend);
			$trans_id					= (string)$transIDp['id'];
			$transID					= (string)$transIDp['transid']; // Id personalizado da transação
			$transDescription			= (string)$transIDp['description'];
			$transAmountin				= (int)$transIDp['amountin'];
	
		} 
			
		if ($logcalls) {
			$log_description	.=  ' #### Dados da fatura: ' . json_encode($GetInvoiceResults);
		}
				
	}
	
	/**
	*
	* Adiciona pagamento
	*
	*/
			
	if ( 
		$v_parent_payment_state === 'approved' and 
		$v_parent_payment_sale_state === 'completed' and
		$invoice_status === 'Unpaid' and
		$invoice_total === $v_parent_payment_price and
		$transID === $v_parent_payment_id and
		$transAmountin === 0 
		) {
		
		// Adiciona o pagamento a fatura e Registra transação =)
		addInvoicePayment(
			$invoice_number, // Invoice ID
			$v_parent_payment_id, // Transaction ID
			$v_parent_payment_price, // Payment Amount
			$v_parent_payment_fee, // Payment Fee
			$params['paymentmethod']
		);
				
	}
	
	else {
		if ($logcalls) {
			$log_description	.= '[Gofas PayPal Plus] Post válido recebido mas nenhuma fatura teve seu status alterado.';
		}
					
	}
			
	// Pagamento negado
	if (
		$v_parent_payment_state === 'denied' and
		$invoice_status === 'Unpaid' and
		$invoice_total === $v_parent_payment_price and
		$transID === $v_parent_payment_id and
		$transAmountin === 0
		) {
			
		// Atualiza transação
		$update_trans_['transactionid'] = $trans_id;
		$update_trans_['description'] = 'Pagamento recusado pela operadora do cartão.';
 
 		$update_trans = localAPI( 'updatetransaction', $update_trans_, $whmcsAdmin );
				
		// Verifica dados do cliente
 		$clientsdetails_['clientid'] = $invoice_user;
 		$clientsdetails_['stats'] = false;
 		$clientsdetails_['responsetype'] = "json";
 
 		$clientsdetails = localAPI( 'getclientsdetails', $clientsdetails_, $whmcsAdmin);
				
		$invoice_user_fname	= $clientsdetails['firstname'];

		// Envia email para o cliente
 		$send_email_['customtype'] = 'invoice';
 		$send_email_['customsubject'] = 'Falha no pagamento da fatura #'.$invoice_number;
 		$send_email_['custommessage'] = '<p>Olá, '.$invoice_user_fname.'!<br/>Não foi possível completar sua tentativa recente de pagamento da fatura #'.$invoice_number.' via cartão de crédito.<br/>Acesse a fatura <a href="'.$systemUrl.'/viewinvoice.php?id='.$invoice_number.'">neste link</a> e tente realizar o pagamento novamente, talvez com outro cartão de crédito ou uma forma de pagamento diferente.</p>';
 		$send_email_['id'] = $invoice_number;
 		$send_email = localAPI( 'sendemail', $send_email_, $whmcsAdmin );

		}
		
		// Pagamento revertido (fraude)
	if (
		$v_parent_payment_state === 'reversed' and
		$invoice_status === 'Paid' and
		$invoice_total === $v_parent_payment_price and
		$transID === $v_parent_payment_id //and
		//$transAmountin === $invoice_total
		) {
			
		// Atualiza transação
		$update_trans_['transactionid'] = $trans_id;
		$update_trans_['description'] = 'Pagamento REVERTIDO pelo PayPal (possível fraude).';
 		$update_trans_['amountin'] = '0.00';
 		$update_trans_['fees'] = '0.00';
 
 		$update_trans = localAPI( 'updatetransaction', $update_trans_, $whmcsAdmin );
		
		// Atualiza Fatura
		$update_invoice_ = array(
			'invoiceid' => $invoice_number,
    		'status' => 'Unpaid',
			);

		$update_invoice = localAPI( 'updateinvoice', $update_invoice_, $whmcsAdmin ); // https://developers.whmcs.com/api-reference/updateinvoice/
				
		// Verifica dados do cliente
 		$clientsdetails_['clientid'] = $invoice_user;
 		$clientsdetails_['stats'] = false;
 		$clientsdetails_['responsetype'] = "json";
 
 		$clientsdetails = localAPI( 'getclientsdetails', $clientsdetails_, $whmcsAdmin);
				
		$invoice_user_fname	= $clientsdetails['firstname'];

		// Envia email para o cliente
 		$send_email_['customtype'] = 'invoice';
 		$send_email_['customsubject'] = 'Pagamento estornado da fatura #'.$invoice_number;
 		$send_email_['custommessage'] = '<p>Olá '.$invoice_user_fname.',<br/>O pagamento da fatura #'.$invoice_number.' foi estornado pelo PayPal por motivos desconhecidos, isso significa que a confirmação de pagamento anterior não tem mais valor.</p>Acesse a fatura <a href="'.$systemUrl.'/viewinvoice.php?id='.$invoice_number.'">neste link</a> e tente realizar o pagamento novamente, talvez com outro cartão de crédito ou uma forma de pagamento diferente.</p>';
 		$send_email_['id'] = $invoice_number;
 		
		$send_email = localAPI( 'sendemail', $send_email_, $whmcsAdmin );

		// Envia email para o admin
		$send_admin_email_ = array(
    		'customsubject' => '[Alerta!] Pagamento estornado da fatura #'.$invoice_number.' (possível fraude)',
			'custommessage' => '<p>O pagamento da fatura <a href="'.$systemAdminUrl.'/invoices.php?action=edit&id='.$invoice_number.'">#'.$invoice_number.'</a> foi estornado pelo PayPal devido a análise pós compra retornar o status <b><i>Reversed<i></b>, isso significa que o pagamento anterior foi cancelado.</p>
			<p>Recomendamos cancelar o pedido e bloquear a conta cliente imediatamente.</p>
			<p>Se o pagamento estornado é referente a produto(s) físico(s) já encaminhado(s) para entrega, entre em contato com o suporte PayPal informando o código de rastreamento da encomenda.</p>
			<b>Detalhes:</b><br>
			Fatura: <a href="'.$systemAdminUrl.'/invoices.php?action=edit&id='.$invoice_number.'">#'.$invoice_number.'</a><br>
			Nome do cliente: <a href="'.$systemAdminUrl.'/clientssummary.php?userid='.$invoice_user.'">'.$clientsdetails['fullname'].'</a><br>
			Email do cliente: '.$clientsdetails['email'].'<br><br>
			
			<span style="font-size:92%;">Email automático gerado pelo módulo <b>Gofas PayPal Plus para WHMCS</b></span><br>
			
			',
    		'mergefields[client_id]' => $invoice_user,
		);

		$send_admin_email = localAPI( 'sendadminemail', $send_admin_email_, $whmcsAdmin ); // https://developers.whmcs.com/api-reference/sendadminemail/
		
		}		
		
	if ($logcalls) {
		$log_description	.= '#### $parent_payment_url: ' . $parent_payment_url . '#### $v_parent_payment: ' . $v_parent_payment . '#### $v_payment_url: ' . $v_parent_payment_url;
		$addlog_values['description']	= $log_description;
		$addlog_results					= localAPI( 'logactivity', $addlog_values, $whmcsAdmin );
	}
}  // end if post ok

// Post inválido
elseif ( empty( $raw_post_array['id'] ) or empty( $raw_post_array['links']['0']['href'] ) or $error) {
	// Retorna erro
	
	if ($logcalls) {
		$addlog_values['description']	= '[Gofas Paypal Plus] Callback acionado via $_POST mas nenhum dado válido foi recebido.';
		$addlog_results					= localAPI( 'logactivity', $addlog_values, $whmcsAdmin );
	}
}

if ($error and $logcalls) {
		$addlog_values['description']	= '[Gofas Paypal Plus] Erro: ' . $error;
		$addlog_results					= localAPI( 'logactivity', $addlog_values, $whmcsAdmin );
}