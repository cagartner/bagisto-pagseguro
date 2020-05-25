# Laravel eCommerce Pagseguro Payment

Módulo criado para adicionar a opção de meio de pagamento Pagseguro na ferramenta de e-Commerce Bagisto

Para maiores informações acesse a página da extenção oficial [clicando aqui](https://bagisto.com/en/extensions/laravel-ecommerce-pagseguro-payment-gateway/)

For futher informations [click here](https://bagisto.com/en/extensions/laravel-ecommerce-pagseguro-payment-gateway/)

## Instalação

1- Run `composer require cagartner/bagisto-pagseguro` in your bagisto project

2- Não esqueça de colocar as rotas do pagseguro no exceptions do `app/Http/Middleware/VerifyCsrfToken.php`:

```php
/**
 * The URIs that should be excluded from CSRF verification.
 *
 * @var array
 */
protected $except = [
    'pagseguro/*'
];
```
 
3- Rodar `php artisan config:clear` para limpar as configurações cacheadas.

4- **Para o módulo funcionar corretamente, você deve ativar o serviço de [Pagamentos Via API](https://pagseguro.uol.com.br/integracao/pagamentos-via-api.jhtml), se não for ativado o cliente não irá retornar para a loja e o pagamento não será criado no Bagisto.**

## Configurações

Para configurar seu módulo acesse: Admin > Configurar > Vendas > Métodos de Pagamento > Pagseguro.

Configurações disponíveis:

* **Título**: Nome do método de pagamento.
* **Descrição**: Opcional
* **Tipo de Checkout**: Tipo de checkout, redirect (A venda é finalizada no site do pagseguro), ou lightbox (A venda é finalizada em um popuo na própria loja).
* **Pagseguro Email**: E-mail da conta criada no Pagseguro que irá receber os pagamentos.
* **Token de Integração**: Token de integração que você deve pegar na sua conta do Pagseguro, [veja mais informações aqui](https://faq.pagseguro.uol.com.br/duvida/como-gerar-token-para-integracao-com-o-site/841#rmcl).
* **Sandbox**: Permite testar sua loja em modo de testes, quando você tiver pronto para começar a vender de verdade essa opção precisa ser deselecionada.
* **Status**: Ativa ou desativa o método de pagamento
<!-- * **Quantidade de Parcelas sem Juros**: Quantidade de parcelas que seu cliente poderá comprar sem ter que pagar juros (Você assumirá essas taxas).
* **Quantidade Máxima de Parcelas**: Quantidade máxima de parcelas que seus clientes poderão parcelar -->

## Me pague uma cerveja:

Se gostou do trabalho e quiser me pagar uma cerveja, pode me fazer uma doação pelo PicPay: [@cagartner](https://picpay.me/cagartner/20.0)

Tenho também a opção de checkout transparente, esse método é vendido separadamente, caso tenha interesse entre em contato: contato@carlosgartner.com.br

## Conheça outros Packages para Bagisto

* [Bagisto - PicPay](https://github.com/cagartner/bagisto-picpay)
* [Bagisto - Correios](https://github.com/cagartner/bagisto-correios)
* [Bagisto - Campos Brasileiros](https://github.com/cagartner/bagisto-brazilcustomer)

## Conheça a comunidade Brasileira de Bagisto
- [Portal Oficial](https://bagisto.com.br)
- [Grupo do WhatsApp](https://chat.whatsapp.com/HpMKEoxf5neIfnpUlHGmaO)
- [Grupo do Facebook](https://www.facebook.com/groups/2552301808420521)
- [Grupo do Telegram](https://t.me/bagistobrasil)
- [Twitter](http://twitter.com/bagistobr)
 
