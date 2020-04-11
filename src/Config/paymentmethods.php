<?php
return [
    'pagseguro'  => [
        'code'              => 'pagseguro',
        'title'             => 'Pagseguro',
        'description'       => 'Pague sua compra com PagSeguro',
        'class'             => \Cagartner\Pagseguro\Payment\PagSeguro::class,
        'active'            => true,
//        'no_interest'       => 5,
        'type'              => 'redirect',
//        'max_installments'  => 10,
        'debug'              => false,
        'sort'              => 100,
    ],
];