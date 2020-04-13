<?php
/**
 * Helper
 *
 * @copyright Copyright © 2020 Redstage. All rights reserved.
 * @author    Carlos Gartner <contato@carlosgartner.com.br>
 */

namespace Cagartner\Pagseguro\Helper;

use Cagartner\Pagseguro\Payment\PagSeguro;
use Illuminate\Support\Facades\Log;
use Webkul\Sales\Contracts\Order;
use Webkul\Sales\Repositories\InvoiceRepository;
use Webkul\Sales\Repositories\OrderRepository;
use Webkul\Sales\Repositories\RefundRepository;
use function core;

/**
 * Class Helper
 * @package Cagartner\Pagseguro\Helper
 */
class Helper
{
    /**
     *
     */
    const MODULE_VERSION = '1.0.1';

    /**
     *
     */
    const STATUS_PAYED = 3;

    /**
     *
     */
    const STATUS_AVALAIBLE = 4;

    /**
     *
     */
    const STATUS_REFUNDED = 6;

    /**
     *
     */
    const STATUS_CANCELED = 7;

    /**
     *
     */
    const PAYMENT_STATUS = [
        1 => 'pending_payment',
        2 => 'pending_payment',
        3 => 'processing',
        4 => 'processing',
        5 => 'fraud',
        6 => 'closed', // refunded
        7 => 'canceled',
        8 => 'closed',
        9 => 'fraud',
    ];

    /**
     *
     */
    const PAYMENT_TYPE = [
        1 => 'Cartão de crédito',
        2 => 'Boleto',
        3 => 'Débito online (TEF)',
        4 => 'Saldo PagSeguro',
        5 => 'Oi Paggo',
        7 => 'Depósito em conta',
    ];

    /**
     *
     */
    const PAYMENT_METHOD = [
        101 => 'Cartão de crédito Visa',
        102 => 'Cartão de crédito MasterCard',
        103 => 'Cartão de crédito American Express',
        104 => 'Cartão de crédito Diners',
        105 => 'Cartão de crédito Hipercard',
        106 => 'Cartão de crédito Aura',
        107 => 'Cartão de crédito Elo',
        108 => 'Cartão de crédito PLENOCard',
        109 => 'Cartão de crédito PersonalCard',
        110 => 'Cartão de crédito JCB',
        111 => 'Cartão de crédito Discover',
        112 => 'Cartão de crédito BrasilCard',
        113 => 'Cartão de crédito FORTBRASIL',
        114 => 'Cartão de crédito CARDBAN',
        115 => 'Cartão de crédito VALECARD',
        116 => 'Cartão de crédito Cabal',
        117 => 'Cartão de crédito Mais!',
        118 => 'Cartão de crédito Avista',
        119 => 'Cartão de crédito GRANDCARD',
        120 => 'Cartão de crédito Sorocred',
        122 => 'Cartão de crédito Up Policard',
        123 => 'Cartão de crédito Banese Card',
        201 => 'Boleto Bradesco',
        202 => 'Boleto Santander',
        301 => 'Débito online Bradesco',
        302 => 'Débito online Itaú',
        303 => 'Débito online Unibanco',
        304 => 'Débito online Banco do Brasil',
        305 => 'Débito online Banco Real',
        306 => 'Débito online Banrisul',
        307 => 'Débito online HSBC',
        401 => 'Saldo PagSeguro',
        501 => 'Oi Paggo',
        701 => 'Depósito em conta - Banco do Brasil',
    ];

    /**
     * OrderRepository object
     *
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * InvoiceRepository object
     *
     * @var InvoiceRepository
     */
    protected $invoiceRepository;

    /**
     * @var RefundRepository
     */
    protected $refundRepository;

    /**
     * Helper constructor.
     * @param OrderRepository $orderRepository
     * @param InvoiceRepository $invoiceRepository
     * @param RefundRepository $refundRepository
     */
    public function __construct(
        OrderRepository $orderRepository,
        InvoiceRepository $invoiceRepository,
        RefundRepository $refundRepository
    )
    {
        $this->orderRepository = $orderRepository;
        $this->invoiceRepository = $invoiceRepository;
        $this->refundRepository = $refundRepository;

    }

    /**
     * @param $response
     * @throws \Prettus\Validator\Exceptions\ValidatorException
     */
    public function updateOrder($response)
    {
        if (core()->getConfigData(PagSeguro::CONFIG_DEBUG)) {
            Log::debug($response->reference);
            Log::debug($response->status);
        }

        /** @var \Webkul\Sales\Models\Order $order */
        if ($order = $this->orderRepository->findOneByField(['cart_id' => $response->reference])) {
            $this->orderRepository->update(['status' => self::PAYMENT_STATUS[$response->status]], $order->id);

            // If order is paid or available create the invoice
            if ($response->status === self::STATUS_PAYED || $response->status === self::STATUS_AVALAIBLE) {
                if ($order->canInvoice() && !$order->invoices->count()) {
                    $this->invoiceRepository->create($this->prepareInvoiceData($order));
                }
            }

            // Create refunds
            if ($response->status === self::STATUS_REFUNDED) {
                if ($order->canRefund()) {
                    $this->refundRepository->create($this->prepareRefundData($order));
                }
            }

            if ($response->status === self::STATUS_CANCELED) {
                if ($order->canCancel()) {
                    $this->orderRepository->cancel($order->id);
                }
            }
        }
    }

    /**
     * @return array
     */
    protected function prepareInvoiceData(Order $order)
    {
        $invoiceData = [
            "order_id" => $order->id,
        ];

        foreach ($order->items as $item) {
            $invoiceData['invoice']['items'][$item->id] = $item->qty_to_invoice;
        }

        return $invoiceData;
    }

    /**
     * @param \Webkul\Sales\Models\Order $order
     * @return array
     */
    protected function prepareRefundData(\Webkul\Sales\Models\Order $order)
    {
        $refundData = [
            "order_id" => $order->id,
            'adjustment_refund'      => $order->sub_tota,
            'base_adjustment_refund' => $order->base_sub_total,
            'adjustment_fee'         => 0,
            'base_adjustment_fee'    => 0,
            'shipping_amount'        => $order->shipping_invoiced,
            'base_shipping_amount'   => $order->base_shipping_invoiced,
        ];

        foreach ($order->items as $item) {
            $refundData['invoice']['items'][$item->id] = $item->qty_to_invoice;
        }

        return $refundData;
    }

    /**
     * @param $number
     * @return string|string[]|null
     */
    static public function justNumber($number)
    {
        return preg_replace('/\D/', '', $number);
    }

    /**
     * @param string $phoneString
     * @param bool $forceOnlyNumber
     * @return array|null
     */
    static function phoneParser(string $phoneString, bool $forceOnlyNumber = true): ?array
    {
        $phoneString = preg_replace('/[()]/', '', $phoneString);
        if (preg_match('/^(?:(?:\+|00)?(55)\s?)?(?:\(?([0-0]?[0-9]{1}[0-9]{1})\)?\s?)??(?:((?:9\d|[2-9])\d{3}\-?\d{4}))$/', $phoneString, $matches) === false) {
            return null;
        }

        $ddi = $matches[1] ?? '';
        $ddd = preg_replace('/^0/', '', $matches[2] ?? '');
        $number = $matches[3] ?? '';
        if ($forceOnlyNumber === true) {
            $number = preg_replace('/-/', '', $number);
        }

        return ['ddi' => $ddi, 'ddd' => $ddd, 'number' => $number];
    }

    /**
     * Aplica um array_filter recursivamente em um array.
     *
     * @param array $input
     *
     * @return array
     */
    static function array_filter_recursive(array $input)
    {
        foreach ($input as &$value) {
            if (is_array($value)) {
                $value = self::array_filter_recursive($value);
            }
        }
        return array_filter($input);
    }
}