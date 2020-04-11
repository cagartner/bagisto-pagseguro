<?php

namespace Cagartner\Pagseguro\Http\Controllers;

use Cagartner\Pagseguro\Helper\Helper;
use Cagartner\Pagseguro\Payment\PagSeguro;
use Exception;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use InvalidArgumentException;
use PagSeguro\Configuration\Configure;
use PagSeguro\Helpers\Xhr;
use PagSeguro\Library;
use PagSeguro\Services\Transactions\Notification;
use Webkul\Checkout\Facades\Cart;
use Webkul\Sales\Repositories\OrderRepository;

/**
 * Class PagseguroController
 * @package Cagartner\Pagseguro\Http\Controllers
 */
class PagseguroController extends Controller
{
    /**
     * OrderRepository object
     *
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * @var Helper
     */
    protected $helper;

    /**
     * Create a new controller instance.
     *
     * @param OrderRepository $orderRepository
     * @param Helper $helper
     */
    public function __construct(
        OrderRepository $orderRepository,
        Helper $helper
    )
    {
        $this->orderRepository = $orderRepository;
        $this->helper = $helper;
    }

    /**
     * @return Factory|View
     */
    public function redirect()
    {
        return view('pagseguro::redirect');
    }

    /**
     * Cancel payment from pagseguro.
     *
     * @return Response
     */
    public function cancel()
    {
        session()->flash('error', 'Você cancelou o pagamento, pedido não finalizado');

        return redirect()->route('shop.checkout.cart.index');
    }

    /**
     * @return RedirectResponse
     * @throws Exception
     */
    public function success(Request $request, PagSeguro $pagSeguro)
    {
        // @todo: Analisar request do retorno do success
        /**
         * @var \Webkul\Sales\Models\Order $order
         */
        $order = $this->orderRepository->create(Cart::prepareDataForOrder());

        try {
            $response = $pagSeguro->transaction($order->cart_id);
            if (isset($response->transactions)) {
                foreach ($response->transactions as $transaction) {
                    // Update order with transaction info
                    $this->helper->updateOrder($transaction);
                }
            }
        } catch (Exception $exception) {
            Log::error($exception);
        }

        Cart::deActivateCart();

        session()->flash('order', $order);

        return redirect()->route('shop.checkout.success');
    }

    /**
     * @param Request $request
     * @param PagSeguro $pagSeguro
     */
    public function notify(Request $request, PagSeguro $pagSeguro)
    {
        try {
            $response = $pagSeguro->notification($request->get('notificationCode'), $request->get('notificationType'));
            if ($response) {
                $this->helper->updateOrder($response);
            }
        } catch (Exception $exception) {
            Log::error($exception);
        }
    }
}