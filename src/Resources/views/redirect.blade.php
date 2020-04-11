<?php
use Cagartner\Pagseguro\Payment\PagSeguro;

/** @var PagSeguro $pagseguro */
$pagseguro = app(PagSeguro::class);
$pagseguro->init();
$response = $pagseguro->send();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Pagamento PagSeguro</title>
    <script type="text/javascript" src="{{ $pagseguro->getJavascriptUrl() }}"></script>
</head>
<body>
<script type="text/javascript">
    var code = '<?= $response->getCode() ?>'
    @if(core()->getConfigData(PagSeguro::CONFIG_TYPE) === 'lightbox')
        var callback = {
            success : function(transactionCode) {
                window.location.href = '<?= route('pagseguro.success') ?>?transactionCode=' + transactionCode;
            },
            abort : function() {
                window.location.href = '<?= route('pagseguro.cancel') ?>';
            }
        };
        var isOpenLightbox = PagSeguroLightbox(code, callback);
        if (!isOpenLightbox){
            location.href= '<?= $pagseguro->getPagseguroUrl() ?>' + code;
        }
    @else
        location.href= '<?= $pagseguro->getPagseguroUrl() ?>' + code;
    @endif
</script>
</body>
</html>