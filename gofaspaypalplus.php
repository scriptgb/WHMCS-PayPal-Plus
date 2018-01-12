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

use Illuminate\Database\Capsule\Manager as Capsule;
include __DIR__.'/gofaspaypalplus/configuration.php';

// Report simple running errors
error_reporting(E_ERROR | E_WARNING | E_PARSE);

function gofaspaypalplus_link($params){
	
	if ( stripos($_SERVER['REQUEST_URI'], 'viewinvoice.php') ) { // if is invoice
	
	// Parametros da configuração do Gateway
	include __DIR__.'/gofaspaypalplus/params.php';
	include __DIR__.'/gofaspaypalplus/functions.php';
	
	if ($debug and !$GATerror){
			echo'<pre style="height:200px;"><b>Todas a sconfigurações do módulo.</b><br/>';
			print_r($params);
			echo "<br/></pre>";
		}
	
	// Verifica instalação
	if ( !Capsule::schema()->hasTable('gofaspaypalplus') ) {
    	try {
		Capsule::schema()->create('gofaspaypalplus', function($table) {
			// incremented id
        	$table->increments('id');
       		// unique column
        	$table->integer('user_id');
        	$table->string('payer_id');
        	$table->string('remembered_cards');
			$table->string('api_clientid');
    	});
	
		} catch (\Exception $e) {
    		$error .= "Não foi possível criar a tabela do módulo no banco de dados: {$e->getMessage()}";
		}
	}
	
	// update 0.1.5 -> 0.1.6
	if( !Capsule::schema()->hasColumn('gofaspaypalplus', 'api_clientid') ) {
		try {
			Capsule::schema()->table('gofaspaypalplus', function($table) {
				$table->string('api_clientid');
				});
		} catch (\Exception $e) {
    		$error .= "Não foi possível criar a coluna 'apiclientid' na tabela do módulo: {$e->getMessage()}";
		}
	}
	
	/**
	*
	* Obtem o access_token
	*
	**/
	
		$GATcurl = curl_init($pp_host.'/v1/oauth2/token'); 
		curl_setopt($GATcurl, CURLOPT_POST, true); 
		curl_setopt($GATcurl, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($GATcurl, CURLOPT_USERPWD, $client_id .':'. $client_secret);
		curl_setopt($GATcurl, CURLOPT_HEADER, false); 
		curl_setopt($GATcurl, CURLOPT_RETURNTRANSFER, true); 
		curl_setopt($GATcurl, CURLOPT_POSTFIELDS, "grant_type=client_credentials"); 
		
		$GATresponse = curl_exec( $GATcurl );
		$GATerror = curl_error( $GATcurl );
	    $GATinfo = curl_getinfo( $GATcurl );
		curl_close( $GATcurl ); // close cURL handler
	    
		$GATarrayResponse = json_decode( $GATresponse ); // Convert the result from JSON format to a PHP array 
		$access_token = $GATarrayResponse->access_token; // Access Token
		
		session_start();
		$_SESSION['access_token'] = $access_token;
		
		if ($GATerror) {$error .= $GATerror;} // Erro
		if ($GATarrayResponse->error) {$error .= $GATarrayResponse->error_description;}
		
		if ($debug and !$GATerror){
			echo'<br/><pre><b>Resultado da solicitação do Token (API PayPal).</b><br/>';
			echo 'Código de resposta: '.$GATinfo['http_code'];
			echo '<br/>Resposta crua: '.$GATresponse;
			echo '<br/>Token: '.$access_token;
			echo '<br/>Tempo levado: ' . $GATinfo['total_time']*1000 . 'ms';
			echo "<br/></pre>";
		} elseif ($debug and $GATerror){
			echo'<pre><b>ERRO na solicitação do Token (API PayPal).</b><br/>';
			echo 'Código de resposta: '.$GATinfo['http_code'];
			echo '<br/>Resposta crua: '.$GATresponse;
			//echo '<br/>Resposta decodificada: '.print_r($GATarrayResponse);
			echo '<br/>Erro: '.$GATerror;
			echo "<br/></pre>";
		}
		/** 
		*
		* Lista perfis de pagamento existentes
		*
		*/
		if ($access_token) {
			$LWEPcurl = curl_init($pp_host.'/v1/payment-experience/web-profiles'); 
			curl_setopt($LWEPcurl, CURLOPT_POST, false);
			curl_setopt($LWEPcurl, CURLOPT_SSL_VERIFYPEER, true);
			curl_setopt($LWEPcurl, CURLOPT_HEADER, false);
			curl_setopt($LWEPcurl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($LWEPcurl, CURLOPT_HTTPHEADER, array(
					'Authorization: Bearer '.$access_token,
					'Accept: application/json',
					'Content-Type: application/json',
					'PayPal-Partner-Attribution-Id: WHMCS_Ecom_PPPlus'
					)); 
		
			$LWEPresponse = curl_exec( $LWEPcurl );
			$LWEPerror = curl_error( $LWEPcurl );
	    	$LWEPinfo = curl_getinfo( $LWEPcurl );
			curl_close( $LWEPcurl ); // close cURL handler
	    
			$LWEParrayResponse = json_decode( $LWEPresponse, TRUE ); // Convert the result from JSON format to a PHP array
			
			function search_key($LWEParrayResponse, $key, $value){
				$results = array();
				if (is_array($LWEParrayResponse)) {
					if (isset($LWEParrayResponse[$key]) && $LWEParrayResponse[$key] == $value) {
						$results[] = $LWEParrayResponse;
					}
				foreach ($LWEParrayResponse as $subarray) {
					$results = array_merge($results, search_key($subarray, $key, $value));
					}
				}
				return $results;
			}
			$LWEParrayResponseClean = search_key($LWEParrayResponse, 'name', $profile_name);
			
			$experience_profile_name = $LWEParrayResponseClean['0']['name']; // Experience Profile Name
			$experience_profile_id = $LWEParrayResponseClean['0']['id']; // Experience Profile ID
			
			if ( $LWEPerror ) { $error .= $LWEPerror; } // Erro
			if ($LWEParrayResponse->error) {$error .= $LWEParrayResponse->error_description;}
			
			if ($debug and !$LWEPerror){
				echo'<pre><b>Resultado da listagem de perfis de experiência (API PayPal).</b><br/>';
				echo 'Código de resposta: '.$LWEPinfo['http_code'];
				echo '<br/>ID do Perfil de Experiência: '.$experience_profile_id;
				echo '<br/>Nome do Perfil de Experiência: '.$experience_profile_name;
				echo '<br/> KEY: '.$key ;
				echo '<br/>Resposta crua: '.$LWEPresponse;
				//echo '<br/>Resposta decodificada: '; print_r($LWEParrayResponse);
				echo "<br/></pre>";
			
			} elseif ($debug and $LWEPerror){
				echo'<pre><b>ERRO na listagem de perfis de experiência (API PayPal).</b><br/>';
				echo 'Código de resposta: '.$LWEPinfo['http_code'];
				echo '<br/>Resposta crua: '.$LWEPresponse;
				echo '<br/>Erro: '.$LWEPerror;
				echo "<br/></pre>";
			}
		}
		/** 
		*
		* Cria perfil de pagamento, se não existe nenhum ou o existente é diferente do padrão
		*
		*/
		if(!$experience_profile_name and $access_token and !$error) {
			$CWEPcurl = curl_init($pp_host.'/v1/payment-experience/web-profiles'); 
			curl_setopt($CWEPcurl, CURLOPT_POST, true);
			curl_setopt($CWEPcurl, CURLOPT_SSL_VERIFYPEER, true);
			curl_setopt($CWEPcurl, CURLOPT_HEADER, false);
			curl_setopt($CWEPcurl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($CWEPcurl, CURLOPT_HTTPHEADER, array(
					'Authorization: Bearer '.$access_token,
					'Accept: application/json',
					'Content-Type: application/json',
					'PayPal-Partner-Attribution-Id: WHMCS_Ecom_PPPlus'
					));
	
			curl_setopt($CWEPcurl, CURLOPT_POSTFIELDS, $experience_profile); 
		
			$CWEPresponse = curl_exec( $CWEPcurl );
			$CWEPerror = curl_error( $CWEPcurl );
	    	$CWEPinfo = curl_getinfo( $CWEPcurl );
			curl_close( $CWEPcurl ); // close cURL handler
	    
			$CWEParrayResponse = json_decode( $CWEPresponse, TRUE ); // Convert the result from JSON format to a PHP array
			$experience_profile_id = $CWEParrayResponse['id']; // Experience Profile ID
			$experience_profile_name = $CWEParrayResponse['name']; // Experience Profile Name
			
			if ( $CWEPerror ) { $error .= $CWEPerror; } // Erro
			if ($CWEParrayResponse->error) {$error .= $CWEParrayResponse->error_description;}
		
			if ($debug and !$CWEPerror){
				echo'<pre><b>Resultado da criação do perfil de experiência (API PayPal).</b><br/>';
				echo 'Código de resposta: '.$CWEPinfo['http_code'];
				echo '<br/>Perfil de Experiência: '.$experience_profile_id;
				echo '<br/>Resposta crua: '.$CWEPresponse;
				//echo '<br/>Resposta decodificada: '; print_r($CWEParrayResponse);
				echo "<br/></pre>";
			
			} elseif ($debug and $CWEPerror){
				echo'<pre><b>ERRO na criação do perfil de experiência (API PayPal).</b><br/>';
				echo 'Código de resposta: '.$CWEPinfo['http_code'];
				echo '<br/>Resposta crua: '.$CWEPresponse;
				echo '<br/>Erro: '.$CWEPerror;
				echo "<br/></pre>";
			}
		}
		/**
		*
		* Verifica transações associadas à fatura
		*
		*/
		//$transID = 'PAY-4VU26865HE813411LLDIKHBI'; // Remover
		if( $transID and $trans_desc === 'Cliente acessou a fatura.' and $trans_gateway === 'gofaspaypalplus' and !$error ) {
		
		$VTRANScurl = curl_init( $pp_host.'/v1/payments/payment/'.$transID ); // Get payment
		curl_setopt($VTRANScurl, CURLOPT_POST, false);
		curl_setopt($VTRANScurl, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($VTRANScurl, CURLOPT_HEADER, false);
		curl_setopt($VTRANScurl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($VTRANScurl, CURLOPT_HTTPHEADER, array(
				'Authorization: Bearer '.$access_token,
				'Accept: application/json',
				'Content-Type: application/json',
				'PayPal-Partner-Attribution-Id: WHMCS_Ecom_PPPlus'
				));
		
		$VTRANSresponse = curl_exec( $VTRANScurl );
		$VTRANSerror = curl_error( $VTRANScurl );
	    $VTRANSinfo = curl_getinfo( $VTRANScurl );
		curl_close( $VTRANScurl ); // close cURL handler
	    
		$VTRANSarrayResponse = json_decode( $VTRANSresponse, TRUE ); // JSON to PHP array
			
		$payment_state				= $VTRANSarrayResponse['state'];
		$invoice_number				= $VTRANSarrayResponse['transactions']['0']['invoice_number'];
		$invoice_amount				= $VTRANSarrayResponse['transactions']['0']['amount']['total'];
		
		if ( $payment_state === "created" and (string)$invoice_number === (string)$invoiceID and (string)$invoice_amount === (string)$invoiceAmount ) {
			$paymentId					= (string)$VTRANSarrayResponse['id'];
			$_SESSION['payment_id'] 	= $paymentId;
			$approval_url				= $VTRANSarrayResponse['links']['2']['href'];
		}
		
		elseif ( ( $payment_state === "pending" or $payment_state === "approved" ) and (string)$invoice_number === (string)$invoiceID and (string)$invoice_amount === (string)$invoiceAmount ) {
			$result_1 .= '
			<p style="color:green; font-weight: bold;">Pagamento em análise, aguarde a confirmação por email.</p>';
			$stop = true;
		}
					
		// Erros
		if ( $VTRANSerror ) {
			$error .= $VTRANSerror;
		} 
			
		if ( $VTRANSarrayResponse->error ) {
			$error .= $VTRANSarrayResponse->error_description;
		}
		
		if ($debug and !$VTRANSerror){
				echo'<pre><b>Resultado da verificação da transação (API PayPal).</b><br/>';
				echo 'Código de resposta: '.$VTRANSinfo['http_code'];
				//echo '<br/>Perfil de Experiência: '.$experience_profile_id;
				echo '<br/>Resposta crua: '.$VTRANSresponse;
				//echo '<br/>Resposta decodificada: '; print_r($CWEParrayResponse);
				echo "<br/></pre>";
			
			} elseif ($debug and $VTRANSerror){
				echo'<pre><b>ERRO na verificação da transação (API PayPal).</b><br/>';
				echo 'Código de resposta: '.$VTRANSinfo['http_code'];
				echo '<br/>Resposta crua: '.$VTRANSresponse;
				echo '<br/>Erro: '.$VTRANSerror;
				echo "<br/></pre>";
			}
	}
		
		/**
		*
		* Verifica webhook
		*
		*/
		if( $access_token and !$error ) {
			$VWHKcurl = curl_init($pp_host.'/v1/notifications/webhooks'); 
			curl_setopt($VWHKcurl, CURLOPT_POST, false);
			curl_setopt($VWHKcurl, CURLOPT_SSL_VERIFYPEER, true);
			curl_setopt($VWHKcurl, CURLOPT_HEADER, false);
			curl_setopt($VWHKcurl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($VWHKcurl, CURLOPT_HTTPHEADER, array(
					'Authorization: Bearer '.$access_token,
					'Accept: application/json',
					'Content-Type: application/json',
					'PayPal-Partner-Attribution-Id: WHMCS_Ecom_PPPlus'
					));
		
			$VWHKresponse = curl_exec( $VWHKcurl );
			$VWHKerror = curl_error( $VWHKcurl );
	    	$VWHKinfo = curl_getinfo( $VWHKcurl );
			curl_close( $VWHKcurl ); // close cURL handler
	    
			$VWHKarrayResponse = json_decode( $VWHKresponse, TRUE ); // JSON to PHP array
			$webhook_id = $VWHKarrayResponse['webhooks']['0']['id']; // webhook ID
			$webhook_url = $VWHKarrayResponse['webhooks']['0']['url']; // webhook url
			
			// Erros
			if ( $VWHKerror ) { $error .= $VWHKerror; } 
			if ( $VWHKarrayResponse->error ) { $error .= $VWHKarrayResponse->error_description;}
		
			if ($debug and !$VWHKerror){
				echo'<pre style="height:150px;"><b>Verificação por webhooks existentes - API PayPal.</b><br/>';
				echo 'Código de resposta: '.$VWHKinfo['http_code'];
				echo '<br/>Webhook ID: '.$webhook_id;
				echo '<br/>Webhook URL: '.$webhook_url;
				echo '<br/>Resposta crua: '.$VWHKresponse;
				echo '<br/>Resposta decodificada: '; print_r( $VWHKarrayResponse );
				if (empty($webhook_id)) { echo 'Webhook ID vazia'; }
				else { echo 'Webhook ID Não é vazia'; }
				echo "<br/></pre>";
			
			} elseif ($debug and $VWHKerror){
				echo'<pre><b>ERRO na Verificação por webhooks existentes - API PayPal.</b><br/>';
				echo 'Código de resposta: '.$VWHKinfo['http_code'];
				echo '<br/>Resposta crua: '.$VWHKresponse;
				echo '<br/>Erro: '.$VWHKerror;
				echo "<br/></pre>";
			}
		}
		
		/**
		*
		* Cria webhook
		*
		*/
		
		if( ( empty( $webhook_url ) or $webhook_url !== $callback_url ) and $access_token and !$error ) {
			
			$webhook_data = '{
				"url": "'.$callback_url.'",
  				"event_types": [
									
					{
      					"name": "PAYMENT.SALE.COMPLETED"
    				},
					{
      					"name": "PAYMENT.SALE.DENIED"
    				},
					{
      					"name": "PAYMENT.SALE.REFUNDED"
    				},
					{
      					"name": "PAYMENT.SALE.REVERSED"
    				}
  				]
			}';
			// all event types: https://gist.github.com/mauriciogofas/fb7dd0e27c0fd89944d64a01bea3eb4f
			
			$CWHKcurl = curl_init($pp_host.'/v1/notifications/webhooks'); 
			curl_setopt($CWHKcurl, CURLOPT_POST, true);
			curl_setopt($CWHKcurl, CURLOPT_SSL_VERIFYPEER, true);
			curl_setopt($CWHKcurl, CURLOPT_HEADER, false);
			curl_setopt($CWHKcurl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($CWHKcurl, CURLOPT_HTTPHEADER, array(
					'Authorization: Bearer '.$access_token,
					'Accept: application/json',
					'Content-Type: application/json',
					'PayPal-Partner-Attribution-Id: WHMCS_Ecom_PPPlus'
					));
	
			curl_setopt( $CWHKcurl, CURLOPT_POSTFIELDS, $webhook_data); 
		
			$CWHKresponse = curl_exec( $CWHKcurl );
			$CWHKerror = curl_error( $CWHKcurl );
	    	$CWHKinfo = curl_getinfo( $CWHKcurl );
			curl_close( $CWHKcurl ); // close cURL handler
	    
			$CWHKarrayResponse = json_decode( $CWHKresponse, TRUE ); // JSON to PHP array
			$webhook_id = $CWHKarrayResponse['id']; // webhook ID
			$webhook_url = $CWHKarrayResponse['url']; // webhook url
			
			if ( $CWHKerror ) { $error .= $CWHKerror; } // Erro
			if ($CWHKarrayResponse->error) {$error .= $CWHKarrayResponse->error_description;}
		
			if ($debug and !$CWHKerror){
				echo'<pre><b>Resultado da criação de novo webhook - API PayPal.</b><br/>';
				echo 'Código de resposta: '.$CWHKinfo['http_code'];
				echo '<br/>Webhook ID: '.$webhook_id;
				echo '<br/>Webhook URL: '.$webhook_url;
				echo '<br/>Resposta crua: '.$CWHKresponse;
				echo '<br/>Resposta decodificada: '; print_r( $CWHKarrayResponse );
				echo '<br>', $callback_url;
				echo "<br/></pre>";
			
			} elseif ($debug and $CWHKerror){
				echo'<pre><b>ERRO na criação de novo webhook - API PayPal.</b><br/>';
				echo 'Código de resposta: '.$CWHKinfo['http_code'];
				echo '<br/>Resposta crua: '.$CWHKresponse;
				echo '<br/>Erro: '.$CWHKerror;
				echo "<br/></pre>";
			}
		}
		
		
		/**
		*
		* Criar pagamento
		*
		*/
		// Json para gerar o pagamento
		$payment = '{
			"intent": "sale",
			"experience_profile_id": "'.$experience_profile_id.'",
			"payer":{
				"payment_method": "paypal"
				},
				"transactions":[
				{
					"amount":{
						"currency": "BRL",
						"total": "'.$invoiceAmount.'",
						"details":{
							"shipping": "0",
							"subtotal": "'.$invoiceAmount.'",
							"shipping_discount": "0.00",
							"insurance": "0.00",
							"handling_fee": "0.00",
							"tax": "0.00"
							}
						},
					"description": "'.$invoiceDescription.'",
					"payment_options":{
						"allowed_payment_method": "IMMEDIATE_PAY"
						},
						"invoice_number": "'.$invoiceID.'",
						"item_list":{
							"shipping_address":{
								"recipient_name": "'.$firstname.' '.$lastname.'",
								"line1": "'.$address1.'",
								"line2": "'.$address2.'",
								"city": "'.$city.'",
								"country_code": "BR",
								"postal_code": "'.$postcode.'",
								"state": "'.$state.'",
								"phone": "'.(string)$phone.'"
								},
							"items":[
								{
								"name": "'.$companyName.'",
								"description": "'.$invoiceDescription.'",
								"quantity": "1",
								"price": "'.$invoiceAmount.'",
								"tax": "0.00",
								"currency": "BRL"
								}
							]
						}
					}
				],
				"redirect_urls":{
					"return_url": "'.$systemUrl.'",
      				"cancel_url": "'.$systemUrl.'"
					}
			}';
   
   			/*
			*
			* envia solicitação para criar um pagamento
			*
			*/
			if( $access_token and $experience_profile_id and !$approval_url and !$paymentId and !$stop and !$error) {
				$CPcurl = curl_init($pp_host.'/v1/payments/payment/'); 
				curl_setopt($CPcurl, CURLOPT_POST, true);
				curl_setopt($CPcurl, CURLOPT_SSL_VERIFYPEER, true);
				curl_setopt($CPcurl, CURLOPT_HEADER, false);
				curl_setopt($CPcurl, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($CPcurl, CURLOPT_HTTPHEADER, array(
						'Authorization: Bearer '.$access_token,
						'Accept: application/json',
						'Content-Type: application/json',
						'PayPal-Partner-Attribution-Id: WHMCS_Ecom_PPPlus'
						));
	
				curl_setopt($CPcurl, CURLOPT_POSTFIELDS, $payment); 
		
				$CPresponse = curl_exec( $CPcurl );
				$CPerror = curl_error( $CPcurl );
	    		$CPinfo = curl_getinfo( $CPcurl );
				curl_close( $CPcurl ); // close cURL handler
	    
				$CParrayResponse = json_decode( $CPresponse, TRUE ); // Convert the result from JSON format to a PHP array 
				$paymentId = $CParrayResponse['id']; // ID do pagamento, usado para efetuar o pagamento
				$_SESSION['payment_id'] 	= $paymentId;
				$approval_url = $CParrayResponse['links']['1']['href']; // URL do pagamento, usado para montar o iframe
				
				// Grava transação no WHMCS
				if ( stripos($_SERVER['REQUEST_URI'], 'viewinvoice.php') ) {
					gppp_add_trans( $userID, $invoiceID, $paymentId, $whmcsAdmin, $debug );
				}
				if($CPerror) {$error .= $CPerror;} // Erro
				if ($CParrayResponse->error) {$error .= $CParrayResponse->error_description;}
		
				if ($debug and !$CPerror){
					echo'<pre><b>Resultado da solicitação de criação de pagamento (API PayPal).</b><br/>';
					echo 'Código de resposta: '.$CPinfo['http_code'];
					echo '<br/> Approval URL: '.$approval_url;
					echo '<br/>Resposta crua: '.$CPresponse;
					//echo '<br/>Resposta decodificada: '; print_r($CParrayResponse);
					echo "<br/></pre>";
				} elseif ($debug and $CPerror){
					echo'<pre><b>ERRO na solicitação de criação de pagamento (API PayPal).</b><br/>';
					echo 'Código de resposta: '.$CPinfo['http_code'];
					echo '<br/>Resposta crua: '.$CPresponse;
					echo '<br/>Erro: '.$CPerror;
					echo "<br/></pre>";
				}
			}
			/**
			*
			* JavaScript debug
			* 
			*/
			
			$rememberedCards_data = Capsule::table('gofaspaypalplus')
                        ->where('user_id', $userID )
						->select('user_id','remembered_cards', 'api_clientid')
                        ->get();
			$rememberedCardsApiClientID = end($rememberedCards_data)->api_clientid;
			
			if ( $rememberedCardsApiClientID === $client_id ) {		
			
				$rememberedCards					= end($rememberedCards_data)->remembered_cards;			
				$_SESSION['wh_remembered_cards']	= $rememberedCards;
			}
			elseif ( $rememberedCardsApiClientID !== $client_id ) {
				$rememberedCards					= null;			
				$_SESSION['wh_remembered_cards']	= null;
			}
			
			if ($debug) {
				echo'<pre><b>Resultados da execução do pagamento via AJAX / JavaScript.</b><br/>';
				echo '<span id="gpppjsdebug"></span><br/>';
				echo 'Resposta Crua: <span id="gpppjsdebug2"></span><br/>';
				echo 'Payer ID: <span id="gpppjsdebugPayerId"></span><br/>';
				echo 'Remembered Cards: <span id="gpppjsdebugrememberedCards"></span><br/>';
				echo 'WH Remeb Cards: # '.$rememberedCards.' # '.end($rememberedCards_data)->user_id.' # '.$rememberedCardsApiClientID.'<br/>';
				echo 'Retorno da execução de pagamento: <span id="executeReturn"></span></pre>';
			}
			/*
			*
			* Resultado impresso na área Visível na fatura/checkout
			*
			*/
			// payerTaxId & payerTaxIdType

			if (!$payerTaxId_2) {
				$payerTaxId = $payerTaxId_1;
				$payerTaxIdType = $payerTaxIdType_1;
				
			} elseif ($payerTaxId_2) {
				$payerTaxId = $payerTaxId_2;
				$payerTaxIdType = $payerTaxIdType_2;
			}
			$result .= $css;
			$result .= '<script type="text/javascript" src="'.$systemUrl.'/assets/js/jquery.min.js"></script>';
			$result .= '<script type="text/javascript" src="'.$systemUrl.'/modules/gateways/gofaspaypalplus/gppp.js?v='.time().'"></script>';
			$result .= '<script src="https://www.paypalobjects.com/webstatic/ppplusdcc/ppplusdcc.min.js" type="text/javascript"></script>';
			$result .= '<div id="ppplus"> </div>';
			$result .= '<script type="text/javascript">var ppp = PAYPAL.apps.PPP({
					"placeholder": "ppplus",
      				"approvalUrl": "'.$approval_url.'",
      				"mode": "'.$pp_mode.'",
      				"buttonLocation": "'.$buttonLocation.'",
					"enableContinue":"continueButton",
					"disableContinue":"continueButton",
      				"preselection": "paypal",
     				"language": "pt_BR",
      				"country": "BR",
     				"disallowRememberedCards":false,
      				"payerEmail": "'.$email.'",
					"rememberedCards": "'.$rememberedCards.'",
      				"payerPhone": "'.$phone.'",
      				"payerFirstName": "'.$firstname.'",
     				"payerLastName": "'.$lastname.'",
     				"payerTaxId": "'.$payerTaxId.'",
      				"payerTaxIdType": "'.$payerTaxIdType.'",
      				"iframeHeight": "450",
      				"useraction": "continue",
   				});</script>';
				//
				$result .= '<input type="hidden" style="dysplay:none;" id="system_url" value="'.$systemUrl.'"></input>';
				$result .= '<input type="hidden" style="dysplay:none;" id="user_id" value="'.$userID.'"></input>';
				$result .= '<input type="hidden" style="dysplay:none;" id="invoice_id" value="'.$invoiceID.'"></input>';
				$result .= '<input type="hidden" style="dysplay:none;" id="approval_url" value="'.$approval_url.'"></input>';
				$result .= '<input type="hidden" style="dysplay:none;" id="debug" value="'.$debug.'"></input>';
				
				
			$result .= $payButton;			
			$result .= '<div id="lightbox"><span id="lightboxspan"></span> </div>';
	}
	
	//
	if ( !$error and !$stop ) {
		return $result;
		
	}
	elseif ( !$error and $stop ) {
		return $result_1;
		
	}
	elseif ( $error and !$emailonError) {
		return $error;
		
	}
}