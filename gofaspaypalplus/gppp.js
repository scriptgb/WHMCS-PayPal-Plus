/**
 * Módulo PayPal Plus para WHMCS
 * @author		Mauricio Gofas | gofas.net
 * @see			https://gofas.net/?p=8294
 * @copyright	2017 https://gofas.net
 * @license		https://gofas.net/?p=9340
 * @support		https://gofas.net/?p=7858
 * @version		1.1.1
 */
 
// IE and others compatible event handler
var eventMethod = window.addEventListener ? "addEventListener" : "attachEvent";
var eventer = window[eventMethod];
var messageEvent = eventMethod == "attachEvent" ? "onmessage" : "message";
		
// Ouça a message da janela filho
eventer( messageEvent, function(e) {
	
	if ( $("#debug").val() === "on" ) {
		console.log("Debug:" , "on"); // Debug
	}
	
	var message		= JSON.parse( e.data );
	var action		= message["action"];
	
	// Mostra confirmação de recebimento no console e debug
	if ( $("#debug").val() === "on" ) {
		console.log("Mensagem recebida do iframe:  " , message); // Debug
		document.getElementById("gpppjsdebug2").innerHTML = typeof e.data + ": " + e.data; // Debug
	}
	
	// Erro de renderização do iframe no debug
	if ( message["result"] == "error" ) {
	
		if ( $("#debug").val() === "on" ) {
			document.getElementById("gpppjsdebug").innerHTML = "<br/>Erro ao carregar iframe.<br/>"; // Debug
		}
	
	document.getElementById("continueButton").style.display = "none";

	} else if ( action == "loaded" ) {
		
		if ( $("#debug").val() === "on" ) {
			document.getElementById("gpppjsdebug").innerHTML = "<br/>Iframe carregado com sucesso.<br/>"; // Debug
		}
		
		document.getElementById("continueButton").style.display = "block";
		document.getElementById("continueButton").disabled = false;
		document.getElementById("continueButton").style.cursor = "pointer";
		
	// enableContinueButton
	} else if ( action == "enableContinueButton") {
		
		document.getElementById("continueButton").disabled = false;
		document.getElementById("continueButton").style.cursor = "pointer";
	
	// disableContinueButton
	} else if ( action == "disableContinueButton" || message["result"] == "error" ) {
		
		document.getElementById("continueButton").disabled = true;
		document.getElementById("continueButton").style.cursor = "not-allowed";
		
	} else if ( action == "checkout") { // OK
		
		var paymentApproved		= message.result["payment_approved"];
		var payerId				= message.result["payer"]["payer_info"]["payer_id"];
		var PPrememberedCards	= message.result["rememberedCards"];
		
		if ( $("#debug").val() === "on" ) {
			var cardOk				= "Dados prontos para execução do pagamento, aguarde..."; // Debug
		}
		
		// disableContinueButton
		document.getElementById("continueButton").disabled = true;
		
		// Mostra confirmação de recebimento de Mensagem no console
		if ( $("#debug").val() === "on" ) {
			console.log("Mensagem recebida do iframe - 2 :  " , message); // Debug
		}
		
		// Mostra confirmação de recebimento no debug, quando ativo
		if ( $("#debug").val() === "on" ) {
			document.getElementById("gpppjsdebug").innerHTML = cardOk; // Debug
			document.getElementById("gpppjsdebug2").innerHTML = typeof e.data + ": " + e.data; // Debug
			document.getElementById("gpppjsdebugPayerId").innerHTML = payerId; // Debug
			document.getElementById("gpppjsdebugrememberedCards").innerHTML = PPrememberedCards; // Debug
		}
		
		// Ativa lightbox
		document.getElementById("lightboxspan").innerHTML = "Processando pagamento, aguarde...";
		document.getElementById("lightbox").style.display = "block";
		
		// Envia post para criação do pagamento
		$.post( $("#system_url").val() + "/modules/gateways/gofaspaypalplus/execute.php",
		
		// Dados enviados
		"payer_id=" + payerId + "&pp_remembered_cards=" + PPrememberedCards + "&user_id=" + $("#user_id").val() + "&invoice_id=" + $("#invoice_id").val(),
		
		// Resposta
		function( rdata ) {
			
			// Executa resposta
			if ( rdata == "approved" ) {
				
				if ( $("#debug").val() === "on" ) {
					var EPresponse		= "Pagamento realizado com sucesso!"; // Debug
					var responseColor	= "green"; // Debug
				}
				
				// Exibe confirmação antes de recarregar a página
				document.getElementById("lightboxspan").innerHTML = "Pagamento aprovado!";
				document.getElementById("lightbox").style.display = "block";
				document.getElementById("lightboxspan").style.background = "none";
				document.getElementById("lightboxspan").style.color = "green";
				
				// Recarrega a página ao confirmar o pagamento
				//location.reload();	
			}
			
			else if ( rdata == "pending" ) {
				
				if ( $("#debug").val() === "on" ) {
					var EPresponse		= "Pagamento em análise, aguarde a confirmação por email."; // Debug
					var responseColor	= "green"; // Debug
				}
				
				// Exibe confirmação antes de recarregar a página
				document.getElementById("lightboxspan").innerHTML = "Pagamento em análise, aguarde a confirmação por email.";
				document.getElementById("lightbox").style.display = "block";
				document.getElementById("lightboxspan").style.background = "none";
				document.getElementById("lightboxspan").style.color = "green";
				
				// Recarrega a página ao confirmar o pagamento
				//location.reload();
				
			}
			
			else {
				
				if ( $("#debug").val() === "on" ) {
					var EPresponse	= "Falha ao realizar o pagamento!" + rdata; // Debug
					var responseColor	= "red"; // Debug
				}
				
				// Exibe erro
				document.getElementById("lightboxspan").innerHTML = "Falha ao realizar o pagamento,<br/> atualize a página e tente novamente.";
				document.getElementById("lightboxspan").style.background = "none";
				document.getElementById("lightboxspan").style.color = "red";
				document.getElementById("lightbox").style.display = "block";
				
			}
			
			if ( $("#debug").val() === "on" ) {
				
				console.log("Resposta da execução do pagamento: ", EPresponse + rdata); // Debug
				document.getElementById("gpppjsdebug").style.color = responseColor; // Debug
				document.getElementById("gpppjsdebug").innerHTML = EPresponse; // Debug
				document.getElementById("executeReturn").style.color = responseColor; // Debug
				document.getElementById("executeReturn").innerHTML = rdata; // Debug
			}
		}
	);
	
} // end of "if action == "checkout""

if (typeof message['cause'] !== 'undefined') { //iFrame error handling

            ppplusError = message['cause'].replace (/['"]+/g,""); //log & attach this error into the order if possible
			
			// Action on error
			console.log("ppplusError: " , ppplusError); // Debug
			
            
            switch (ppplusError)

                {

                    case "INTERNAL_SERVICE_ERROR": 
                    case "SOCKET_HANG_UP": 
                    case "socket hang up": 
                    case "connect ECONNREFUSED": 
                    case "connect ETIMEDOUT": 
                    case "UNKNOWN_INTERNAL_ERROR": 
                    case "fiWalletLifecycle_unknown_error": 
                    case "Failed to decrypt term info": 
                    case "INTERNAL_SERVER_ERROR":
					
                    // Action on error
					alert("Erro interno do servidor.\nTente novamente e se o erro persistir, entre em contato com o suporte.");
					location.reload(true);					
					
                    break;
					
					case "TRY_ANOTHER_CARD":
                    case "RISK_N_DECLINE": 
                    case "NO_VALID_FUNDING_SOURCE_OR_RISK_REFUSED": 
                    case "NO_VALID_FUNDING_INSTRUMENT": 
                    // Payment declined by risk
                    
					// Action on error
					alert("Verifique se os dados do cartão estão corretos e tente novamente.\nSe você já realizou compras via PayPal com esse cartão ou email, verifique a sua conta PayPal."); // inform the customer to contact PayPal
					window.location.assign( $("#approval_url").val() ); // offer Express Checkout payment solution
										
                    break;

                    case "CHECK_ENTRY": //Missing or invalid credit card information
                    
					// Action on error
					alert("Corrija os dados do cartão e tente novamente."); // inform your customer to check the inputs.
					
                    break;
					
					 case "COUNTERPARTY_LOCKED_OR_INACTIVE": // Merchant is locked/close/restricted
                    
					// Action on error
					alert("O PayPal retornou um erro relacionado à conta do vendedor. Se o erro persistir, entre em contato com o suporte."); // inform your customer to contact support.
					
                    break;
                    
                    default: //unknown error & reload payment flow
                    
					// Action on error
					alert("O sistema apresentou um erro desconhecido. Clique no botão 'OK' para tentar novamente.");
					location.reload(true);
					
					
                    
                }

        }

},false); // end of "eventer(messageEvent,function(e)"