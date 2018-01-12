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

if(!defined('WHMCS')) { die('Esse arquivo não pode ser acessado diretamente'); }

/**
 *
 * Gravar transação no WHMCS
 * @ggnb_add_trans
 *
 */
function gppp_add_trans( $USERID, $INVOICEID, $paymentId, $whmcsAdmin, $debug) {
	$addtransaction = "addtransaction";
 	$addtransvalues['userid'] = $USERID;
 	$addtransvalues['invoiceid'] = $INVOICEID;
 	$addtransvalues['description'] = 'Cliente acessou a fatura.';
 	$addtransvalues['amountin'] = '0.00';
 	$addtransvalues['fees'] = '0.00';
 	$addtransvalues['paymentmethod'] = 'gofaspaypalplus';
 	$addtransvalues['transid'] = $paymentId;
 	$addtransvalues['date'] = date('d/m/Y');
	$addtransresults = localAPI($addtransaction,$addtransvalues,$whmcsAdmin);
	
		if ( $debug and $addtransresults['result'] === 'success') {
			echo'<pre class="debug"><b>Transação temporária gravada com sucesso - API WHMCS.</b>';
			//print_r($addtransresults);
			echo'<br/></pre>';
		} elseif ($debug and $addtransresults['result'] !== 'success'){
			echo'<pre class="debug"><p class="erro">Erro ao gravar a transação - API WHMCS.</p>';
			//print_r($addtransresults);
			echo'<br/></pre>';
		}
	if ( $addtransresults['result'] === 'success' ) {
		return 'success';
		
	} elseif ( $debug and $addtransresults['result'] !== 'success') {
		return '<b>Não foi possível gravar a transação no WHMCS.</b>';
	}
}
/**
 *
 * Envia email ao admin em caso de erro
 * ggnb_send_error_email
 *
 */
 
function gppp_send_error_email( $INVOICEID, $USERID, $FNAME, $LNAME, $SYSTEMURL, $ADMIN, $EOE, $ERROR, $debug ) {
	$sendEmailonError = "sendadminemail";
 	$sendEOEvalues['customsubject'] = 'Erro ao gerar boleto - fatura #'.$INVOICEID;
	$sendEOEvalues['custommessage'] = '<br/>Olá administrador,<br/>
		Ocorreu uma falha ao gerar um Boleto para a <a href="'.$SYSTEMURL.'/admin/invoices.php?action=edit&id='.$INVOICEID.'">Fatura #'.$INVOICEID.'</a>.<br/><br/>
		Detalhes do erro:<br/>
		<b>Cliente:</b> <a href="'.$SYSTEMURL.'/admin/clientssummary.php?userid='.$USERID.'">'.$FNAME.' '.$LNAME.'</a><br/><br/>
		<b>Erro exibido na Fatura:</b><br/><i>"'.$ERROR.'"</i><br/><br/>
		Email gerado de acordo com às configurações do gateway <a title="Ir para as configurações do módulo ↗" href="'.$SYSTEMURL.'/admin/configgateways.php?updated=gofasgerencianetboleto#m_gofasgerencianetboleto">Gofas Gerencianet Boleto</a>.<br/><br/>';
 	$sendEOEvalues['type'] = 'system';
 	$sendEOEvalues['deptid'] = $EOE;
 	$sendEOEresults = @localAPI($sendEmailonError,$sendEOEvalues,$ADMIN);
		
	if ($debug and $sendEOEresults['result'] === 'success'){
		echo'<pre class="debug"><p class="ok">Email envido ao admin notificando o erro - API WHMCS.</p>';
		print_r($sendEOEresults);
		echo'<br/></pre>';
	} elseif($debug and $sendEOEresults['result'] !== 'success') {
		echo'<pre class="debug"><p class="error">Falha ao enviar email notificando o erro - API WHMCS.</p>';
		print_r($sendEOEresults);
		echo'<br/></pre>';
	}
	return $sendEOEresults;
}