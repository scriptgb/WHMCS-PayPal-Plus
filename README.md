# WHMCS-PayPal-Plus
Módulo do PayPal Plus para WHMCS 7.x


Instalação

Descompacte, mova o arquivo gofaspaypalplus.php e a pasta gofaspaypalplus para a pasta /whmcs/modules/gateways/ da instalação do WHMCS;
Ative o módulo;
Acesse developer.paypal.com > Dashboard > My Apps & Credentials > REST API apps e crie um aplicativo para ser utilizado apenas com o módulo de integração. Salve as credenciais Client_ID e Client_Secret ou mantenha essa página aberta.
Configurações
Live Client ID: Insira o Client ID do modo "produção" de acesso à REST API do seu aplicativo;
Live Client Secret: Insira o Client Secret do modo "produção" do seu aplicativo;
Sandbox Client ID: Insira o Client ID do modo "desenvolvimento" do seu aplicativo;
Sandbox Client Secret: Insira o Client Secret do modo "desenvolvimento" do seu aplicativo;
Sandbox: Marque essa opção se você estiver utilizando o par de chaves "Client_Id" e "Client_Secret" do modo Sandbox (Desenvolvimento);
Debug: Marque essa opção para exibir resultados e erros retornados pela API PayPal e API interna do WHMCS. Por segurança, NÃO use isso em produção, apenas para testes ou se precisar diagnosticar erros;
Administrador atribuído: Insira o nome de usuário ou ID do administrador que será atribuído as transações. Necessário para usar a API interna do WHMCS;
Ordem do campo CPF ou CNPJ: Insira a ordem de exibição do campo personalizado criado para coletar o CPF ou CNPJ do cliente;
Ordem do campo CNPJ: Insira a ordem de exibição do campo personalizado criado para coletar o CNPJ do cliente. Deixe em branco se você usa apenas um campo para CPF e CNPJ;
Imagem do botão "Finalizar Pagamento": Insira o URL da imagem que será usada como botão "Finalizar Pagamento" (tamanho recomendado: entre 160x40px e 339x40px);



Requisitos do sistema

Versão do PHP: 5.4.0 a 7.1
Versão do WHMCS: 6.0.4 a 7.1.2
Os requisitos do sistema foram definidos de acordo com os nosso testes, se o seu sistema não se encaixa nos requisitos, não significa que o módulo não vai funcionar para no seu whmcs, significa apenas que não testamos no mesmo ambiente.

Requisitos do PayPal

Conta PayPal Empresarial;
CNPJ;
SSL;
Com o módulo já instalado e testado em sandbox (API de testes), solicitar avaliação aqui.



Histórico de atualizações
	
v1.0.2
Removido o link experimental para página de contribuição;
Adicionado link para notas da versão no rodapé das configurações do módulo;
Alterada a extensão do arquivo CHANGELOG de .md para .txt;
v1.0.1
Adequação: Os IDs das faturas agora são identificadas pelo parâmetro invoice_number na PayPal REST API, o parâmetro sku introduzido anteriormente para essa finalidade não é mais utilizado;
Adequação: Removidos os parâmetro desnecessários do Javascript e do PHP;
Adequação: Alterado o parâmetro return_url do URL da fatura para o URL da página inicial do whmcs;
Melhoria: Código Javascript que executa as funções agora é carregado em arquivo separado, com versão aleatória no final do URL do arquivo para evitar erros quando o usuário esquece de esvaziar o cache do navegador após a atualização;
Melhoria: Adicionadas novas mensagens de erro, mais precisas e com caixas de diálogo;
Segurança: Agora em caso de erros relacionados a recusa do pagamento por risco de fraude, o comprador é redirecionado para o Express Checkout (página de pagamento externa), onde são oferecidas mais opções de pagamento e o usuário pode logar na sua conta PayPal para realizar o pagamento;
Segurança: Incluída ação de resposta para o status reversed (pagamento revertido/cancelado após a confirmação), explicação: Agora, quando um comprador realiza uma compra online com cartão via PayPal, mesmo após a confirmação de pagamento (geralmente instantânea), o PayPal continua acompanhando os acessos e próximas compras do usuário, no caso do algoritmo anti fraude detectar possíveis fraudes, compras realizadas anteriormente no mesmo dia serão canceladas e o PayPal irá notificar o vendedor por email e (quem usa o nosso módulo) receberá a notificação no seu WHMCS, que por sua vez irá:
Remover o pagamento adicionado à fatura e marca-la novamente como “Não paga”;
Disparar um email para o admin avisando sobre a possível fraude;
Disparar um email para o comprador a fim de traze-lo de volta ao site e realizar o pagamento novamente.
0.1.7
Melhorias na estéticas na tela de configurações.
0.1.6
Agora transações que retornarem o status pending no momento da execução do pagamento, adicionam uma mensagem temporária à fatura instruindo o cliente a aguardar a confirmação de pagamento por email. Uma transação com valor de R$0.00 é gravada no WHMCS para identificar as faturas que aguardam a confirmação de pagamento;
Adicionado callback.php que recebe e processa notificações de pagamentos recebidas via webhooks, no caso de transações que não são aprovadas instantâneamente. Notificações de pagamentos aprovados, depois de verificados adicionam o pagamento à fatura associada enviando a confirmação de pagamento ao cliente e liberando o pedido, pagamentos rejeitados disparam um email ao cliente instruindo ele a acessar a fatura para tentar realizar o pagamento novamente;
Removidos parâmetros desnecessários;
Segurança: Agora o parâmetro remembered_cards só é invocado quando a chave client_id pertence ao aplicativo Rest Api que memorizou o cartão;
Segurança: Agora o token de acesso é enviado via back end para o arquivo execute.php, responsável por executar o pagamento;
0.1.5
Corrigido o erro "Warning: end() expects parameter 1 to be array";
Adicionada a capacidade de reportar todos os erros e avisos do php, independente da configuração do servidor;
0.1.4
Removida a opção "Notificar admin por e-mail em caso de erro";
Removida a opção onde era possível escolher usar o botão do PayPal, ao inés do botão do módulo ou uma imagem;
Melhorias nos efeitos hover do botão "Finalizar pagamento";
0.1.3
Remoção de linhas obsoletas
0.1.0
Lançamento
