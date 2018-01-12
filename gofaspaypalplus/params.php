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
 
// Parametros do sistema
$companyName		= $params['companyname'];
$systemUrl			= $params['systemurl'];
$langPayNow			= $params['langpaynow'];
$moduleDisplayName	= $params['name'];
$moduleName			= $params['paymentmethod'];

// Web Experience Profile / perfil de experiência
$profile_name 		= 'Gofas PayPal Plus';
$experience_profile = '{
	"name":"'.$profile_name.'",
	"presentation":{
		"brand_name":"'.$companyName.'",
		"logo_image":"'.$systemUrl.'/assets/img/logo.png",
		"locale_code":"BR"
		},
	"input_fields":{
		"allow_note":false,
		"no_shipping":1,
		"address_override":1
		},
	"flow_config":{
		"landing_page_type":"billing",
		"bank_txn_pending_url":"'.$systemUrl.'"
		}
	}';
	
// Parametros da configuração do Gateway
$moduleVersion		= '0.1.5'; // Releases: https://github.com/gofas/whmcs-paypalplus/releases
$sandbox			= $params['sandboxmode'];
if ($sandbox) {
	$client_id			= $params['clientidsandbox'];
	$client_secret		= $params['clientsecretsandbox'];
	$pp_host			= 'https://api.sandbox.paypal.com';
	$pp_mode			= 'sandbox';
}elseif(!$sandbox) {
	$client_id			= $params['clientid'];
	$client_secret		= $params['clientsecret'];
	$pp_host			= 'https://api.paypal.com';
	$pp_mode			= 'live';
}

if ($params['customfieldcpf']) {
	$customfCPF			= $params['customfieldcpf'];
} elseif (!$params['customfieldcpf']) {
	$customfCPF			= "0";
}
if ($params['customfieldcnpj']) {
	$customfCNPJ		= $params['customfieldcnpj'];
} elseif (!$params['customfieldcnpj']) {
	$customfCNPJ		= "1";
}

$callback_url	= $systemUrl.'modules/gateways/gofaspaypalplus/callback.php';

if (stripos($_SERVER['REQUEST_URI'], 'viewinvoice.php')){
	$isInvoice 			= true;
} else {
	$isInvoice 			= false;
}

if ($isInvoice){
	$debug				= $params['debugmode'];
} else {
	$debug				= false;
}

$buttonLocation = 'outside';

if ($params['paybuttonimage']){
	$payButtonCss		= '
	.payment-btn-container button.continueButton {
    background: url('.$params['paybuttonimage'].') no-repeat center;
    border: none;
    padding: 20px;
    width: 100%;
	display: none;
}
.payment-btn-container button.continueButton:hover {
	text-decoration: none;
	cursor: pointer;
}
';
	$payButton			= '<button type="submit" class="continueButton" id="continueButton" onclick="ppp.doContinue(); return true;">  </button>';
	
}elseif(!$params['paybuttonimage']){
	$payButtonCss		= '
	.payment-btn-container button.continueButton {
    background: #009cde;
    color: #fff;
    border: none;
    padding: 10px 20px;
    font-size: 22px;
    width: 100%;
	display: none;
}
.payment-btn-container button.continueButton:hover {
	background: #017aad;
	text-decoration: none;
	cursor: pointer;
}
';
	$payButton			= '<button type="submit" class="continueButton" id="continueButton" onclick="ppp.doContinue(); return true;">Finalizar Pagamento</button>';
	
}
// CSS da fatura

$css				.= '
<style type="text/css">
'.$payButtonCss.'
a, a:hover {cursor: pointer;}
#lightbox {
	z-index: 999999;
	width: 100%;
	height:100%;
	position: absolute;
	top: 0;
	left:0;
	background: rgba(255, 255, 255, 0.9);
	display: none;
}
#lightboxspan {
	position: absolute;
    top: 50%;
    left: 50%;
    width: 280px;
    height: 68px;
    margin-top: -25%;
    margin-left: -140px;
	font-weight: bold;
	background: url('.$systemUrl.'/assets/img/loading.gif) no-repeat center;
}
@media (min-width: 768px) { .col-sm-7 { width: 48.333333%; } .col-sm-5 { width: 50%; } }
</style>';

if($params['admin']) {
	$whmcsAdmin			= $params['admin'];
}elseif(!$params['admin']){
	$whmcsAdmin 		= 1;
}
// Parametros da Fatura
$invoiceID				= $params['invoiceid'];
$invoiceDescription 	= $params["description"];
$invoiceAmount			= $params['amount'];

$getinvoiceid['invoiceid']	= $invoiceID;
$GetInvoiceResults			= localAPI( 'getinvoice', $getinvoiceid, $whmcsAdmin );

if ($debug) {
	echo '<pre style="height:150px;" class="debug"><b>Resultado da consulta por informações da fatura (API interna - WHMCS).</b><br/>';
	print_r($GetInvoiceResults);
	echo '<br/></pre>';
}

// Parâmetros das transações associadas à Fatura
$transIDendA					= $GetInvoiceResults['transactions'];
if($transIDendA) {
	$transIDend					= $transIDendA['transaction'];
}
if ($transIDend) {
	$transIDp					= end($transIDend);
	$transID					= (string)$transIDp['transid'];
	$trans_desc					= (string)$transIDp['description'];
	$trans_gateway				= (string)$transIDp['gateway'];
	
} else {
	$transID					= false;
}
if ($debug) {
	echo '<pre class="debug"><b class="ok">Transações registradas por esta fatura - API WHMCS.</b><br/>';
	if ( $transID ) {
		echo 'Transação existente: '.$transID;
	} else {
		echo 'Nenhuma transação registrada.';
	}
	echo '</pre>';
}

// Parametros do Cliente
$userID 			= $params['clientdetails']['id'];
$firstname 			= $params['clientdetails']['firstname'];
$lastname 			= $params['clientdetails']['lastname'];
$email				= $params['clientdetails']['email'];
$CCompanyName		= $params['clientdetails']['companyname'];
$address1 			= $params['clientdetails']['address1'];
$address2 			= $params['clientdetails']['address2'];
$city 				= $params['clientdetails']['city'];
$state				= $params['clientdetails']['state'];
$postcode			= preg_replace("/[^\da-z]/i", "",$params['clientdetails']['postcode']);
$country			= $params['clientdetails']['country'];
$phone				= preg_replace('/[^\da-z]/i', '', $params['clientdetails']['phonenumber']);

/************************  CPF & CNPJ ************************/
$cpfStr = preg_replace("/[^\da-z]/i", "", $params["clientdetails"]["customfields"]["$customfCPF"]["value"]); // Primeiro campo personalizado
$cnpjStr = preg_replace("/[^\da-z]/i", "", $params["clientdetails"]["customfields"]["$customfCNPJ"]["value"]); // Segundo campo personalizado

if (strlen($cpfStr) === 10) { // Adiciona um dígido 0 (zero) ao início do CPF se esse possui apenas 10 caracteres
	$cpf = '0'.$cpfStr;
	
	if (strlen($cnpjStr) === 13) {
		$cnpj = '0'.$cnpjStr; // Adiciona um dígido 0 (zero) ao início do CNPJ se esse possui apenas 13 caracteres
		$payerTaxId_1 = $cpf;
		$payerTaxIdType_1 = 'BR_CPF';
		$payerTaxId_2 = $cnpj;
		$payerTaxIdType_2 = 'BR_CNPJ';
		
	} elseif (strlen($cnpjStr) === 14) {
		$cnpj = $cnpjStr;
		$payerTaxId_1 = $cpf;
		$payerTaxIdType_1 = 'BR_CPF';
		$payerTaxId_2 = $cnpj;
		$payerTaxIdType_2 = 'BR_CNPJ';
		
	} elseif (strlen($cnpjStr) !== 14 || strlen($cnpjStr) !== 13) {
		$cnpj = false;
		$payerTaxId_1 = $cpf;
		$payerTaxIdType_1 = 'BR_CPF';
		$payerTaxId_2 = false;
		$payerTaxIdType_2 = false;
	}
}
elseif (strlen($cpfStr) === 11) { // Adiciona um dígido 0 (zero) ao início do CPF e interpreta CPF como CNPJ se esse possui 13 caracteres
	$cpf = $cpfStr;
	
	if (strlen($cnpjStr) === 13) {
		$cnpj = '0'.$cnpjStr; // Adiciona um dígido 0 (zero) ao início do CNPJ se esse possui apenas 13 caracteres
		$payerTaxId_1 = $cpf;
		$payerTaxIdType_1 = 'BR_CPF';
		$payerTaxId_2 = $cnpj;
		$payerTaxIdType_2 = 'BR_CNPJ';
		
	} elseif (strlen($cnpjStr) === 14) {
		$cnpj = $cnpjStr;
		$payerTaxId_1 = $cpf;
		$payerTaxIdType_1 = 'BR_CPF';
		$payerTaxId_2 = $cnpj;
		$payerTaxIdType_2 = 'BR_CNPJ';
		
	} elseif (strlen($cnpjStr) !== 14 || strlen($cnpjStr) !== 13) {
		$cnpj = $cpf;
		$payerTaxId_1 = $cpf;
		$payerTaxIdType_1 = 'BR_CPF';
		$payerTaxId_2 = false;
		$payerTaxIdType_2 = false;
	}
}
elseif (strlen($cpfStr) === 13) { // Adiciona um dígido 0 (zero) ao início do CPF e interpreta CPF como CNPJ se esse possui 13 caracteres
	$cpf = false; 
	$cnpj = '0'.$cpfStr;
	$payerTaxId_1 = false;
	$payerTaxIdType_1 = false;
	$payerTaxId_2 = $cnpj;
	$payerTaxIdType_2 = 'BR_CNPJ';
	
}
elseif (strlen($cpfStr) === 14) { // Interpreta CPF como CNPJ se esse possui 14 caracteres
	$cpf 				= false;
	$cnpj				= $cpfStr;
	$payerTaxId_1 		= false;
	$payerTaxIdType_1	= false;
	$payerTaxId_2		= $cnpj;
	$payerTaxIdType_2	= 'BR_CNPJ';
	
}
else {
	$cpf 				= $cpfStr;
	if (strlen($cnpjStr) === 13) {
		$cnpj = '0'.$cnpjStr; // Adiciona um dígido 0 (zero) ao início do CNPJ se esse possui apenas 13 caracteres
		$payerTaxId_1 = $cpf;
		$payerTaxIdType_1 = 'BR_CPF';
		$payerTaxId_2 = $cnpj;
		$payerTaxIdType_2 = 'BR_CNPJ';
		
	} elseif (strlen($cnpjStr) === 14) {
		$cnpj = $cnpjStr;
		$payerTaxId_1 = $cpf;
		$payerTaxIdType_1 = 'BR_CPF';
		$payerTaxId_2 = $cnpj;
		$payerTaxIdType_2 = 'BR_CNPJ';
		
	} elseif (strlen($cnpjStr) !== 14 || strlen($cnpjStr) !== 13) {
		$cnpj = false;
		$payerTaxId_1 = $cpf;
		$payerTaxIdType_1 = 'BR_CPF';
		$payerTaxId_2 = false;
		$payerTaxIdType_2 = false;
	}
}