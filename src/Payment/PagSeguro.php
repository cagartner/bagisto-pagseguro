<?php

namespace Cagartner\Pagseguro\Payment;

use Cagartner\Pagseguro\Helper\Helper;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PagSeguro\Configuration\Configure;
use PagSeguro\Enum\PaymentMethod\Config\Keys;
use PagSeguro\Enum\PaymentMethod\Group;
use PagSeguro\Enum\Shipping\Type;
use PagSeguro\Library;
use PagSeguro\Services\Session;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Webkul\Checkout\Models\Cart;
use Webkul\Checkout\Models\CartAddress;
use Webkul\Payment\Payment\Payment;
use function core;

/**
 * Class PagSeguro
 * @package Cagartner\Pagseguro\Payment
 */
class PagSeguro extends Payment
{
    /**
     *
     */
    const CONFIG_EMAIL_ADDRES = 'sales.paymentmethods.pagseguro.email_address';
    /**
     *
     */
    const CONFIG_TOKEN = 'sales.paymentmethods.pagseguro.token';
    /**
     *
     */
    const CONFIG_SANDBOX = 'sales.paymentmethods.pagseguro.sandbox';
    /**
     *
     */
    const CONFIG_DEBUG = 'sales.paymentmethods.pagseguro.debug';
    /**
     *
     */
    const CONFIG_NO_INTEREST = 'sales.paymentmethods.pagseguro.no_interest';
    /**
     *
     */
    const CONFIG_TYPE = 'sales.paymentmethods.pagseguro.type';
    /**
     *
     */
    const CONFIG_MAX_INSTALLMENTS = 'sales.paymentmethods.pagseguro.max_installments';

    /**
     * Payment method code
     *
     * @var string
     */
    protected $code = 'pagseguro';
    /**
     * @var
     */
    protected $sessionCode;
    /**
     * @var \PagSeguro\Domains\Requests\Payment
     */
    protected $payment;
    /**
     * @var array
     */
    protected $urls = [];
    /**
     * @var bool
     */
    protected $sandbox = false;
    /**
     * @var string
     */
    protected $environment = 'production';
    /**
     * @var
     */
    protected $email;
    /**
     * @var
     */
    protected $token;

    /**
     * PagSeguro constructor.
     */
    public function __construct()
    {
        $this->email = core()->getConfigData(self::CONFIG_EMAIL_ADDRES);
        $this->token = core()->getConfigData(self::CONFIG_TOKEN);

        if (core()->getConfigData(self::CONFIG_SANDBOX)) {
            $this->sandbox = true;
            $this->environment = 'sandbox';
        }

        $this->setUrls();
    }

    /**
     * @throws Exception
     */
    public function init()
    {
        Library::initialize();
        Library::cmsVersion()->setName("Bagisto")->setRelease(Helper::MODULE_VERSION);
        Library::moduleVersion()->setName("Bagisto")->setRelease(Helper::MODULE_VERSION);

        if (!$this->email || !$this->token) {
            throw new Exception('Pagseguro: To use this payment method you need to inform the token and email account of Pagseguro account.');
        }

        Configure::setAccountCredentials($this->email, $this->token);
        Configure::setCharset('UTF-8');
        Configure::setEnvironment($this->environment);

        /** @var Cart $cart */
        $cart = $this->getCart();

        $this->payment = new \PagSeguro\Domains\Requests\Payment();
        $this->configurePayment($cart);
        $this->addItems();
        $this->addCustomer($cart);
        $this->addShipping($cart);

        try {
            $this->sessionCode = Session::create(Configure::getAccountCredentials());
        } catch (Exception $e) {
            throw new Exception('Pagseguro: ' . $e->getMessage());
        }
    }

    /**
     * @param Cart $cart
     */
    public function configurePayment(Cart $cart)
    {
        $this->payment->setCurrency('BRL');
        $this->payment->setReference($cart->id);
        $this->payment->setRedirectUrl(route('pagseguro.success'));
        $this->payment->setNotificationUrl(route('pagseguro.notify'));

//        //Add installments with no interest
//        if ($maxInstallments = core()->getConfigData(self::CONFIG_MAX_INSTALLMENTS)) {
//            $this->payment->addPaymentMethod()->withParameters(
//                Group::CREDIT_CARD,
//                Keys::MAX_INSTALLMENTS_LIMIT,
//                (int) $maxInstallments // (int) qty of installment
//            );
//        }
//
//        // Limit the max installments
//        if ($installmentsNoInterest = core()->getConfigData(self::CONFIG_NO_INTEREST)) {
//            $this->payment->addPaymentMethod()->withParameters(
//                Group::CREDIT_CARD,
//                Keys::MAX_INSTALLMENTS_NO_INTEREST,
//                (int) $installmentsNoInterest // (int) qty of installment
//            );
//        }
    }

    /**
     *
     */
    public function addItems()
    {
        /**
         * @var \Webkul\Checkout\Models\CartItem[] $items
         */
        $items = $this->getCartItems();

        foreach ($items as $cartItem) {
            $this->payment->addItems()->withParameters(
                $cartItem->product_id,
                $cartItem->name,
                $cartItem->quantity,
                $cartItem->price
            );
        }
    }

    /**
     * Add the customer to the payment request
     * @param Cart $cart
     */
    public function addCustomer(Cart $cart)
    {
        $fullname = $this->fullnameConversion($cart->customer_first_name . ' ' . $cart->customer_last_name);
        $this->payment->setSender()->setName($fullname);
        $this->payment->setSender()->setEmail($cart->customer_email);
    }

    /**
     *
     */
    public function addShipping(Cart $cart)
    {
        /**
         * @var CartAddress $billingAddress
         */
        $billingAddress = $cart->getBillingAddressAttribute();

        // Add telephone
        $telephone = Helper::phoneParser($billingAddress->phone);

        if ($telephone) {
            $this->payment->setSender()->setPhone()->withParameters(
                $telephone['ddd'],
                $telephone['number']
            );
        }

        // Add CPF
        if ($billingAddress->vat_id) {
            $this->payment->setSender()->setDocument()->withParameters(
                'CPF',
                Helper::justNumber($billingAddress->vat_id)
            );
        }

        if ($cart->selected_shipping_rate) {
            $addresses = explode(PHP_EOL, $billingAddress->address1);

            // Add address
            $this->payment->setShipping()->setAddress()->withParameters(
                isset($addresses[0]) ? $addresses[0] : null,
                isset($addresses[1]) ? $addresses[1] : null,
                isset($addresses[2]) ? $addresses[2] : null,
                $billingAddress->postcode,
                $billingAddress->city,
                $billingAddress->state,
                $billingAddress->country,
                isset($addresses[3]) ? $addresses[3] : null
            );

            // Add Shipping Method
            $this->payment->setShipping()->setCost()->withParameters($cart->selected_shipping_rate->price);
            if (Str::contains($cart->selected_shipping_rate->carrier, 'correio')) {
                if (Str::contains($cart->selected_shipping_rate->method, 'sedex')) {
                    $this->payment->setShipping()->setType()->withParameters(Type::SEDEX);
                }
                if (Str::contains($cart->selected_shipping_rate->method, 'pac')) {
                    $this->payment->setShipping()->setType()->withParameters(Type::PAC);
                }
            } else {
                $this->payment->setShipping()->setType()->withParameters(Type::NOT_SPECIFIED);
            }
        }
    }

    /**
     * @return string
     * @throws Exception
     */
    public function send()
    {
        return $this->payment->register(
            Configure::getAccountCredentials(),
            true
        );
    }

    /**
     * @return string
     */
    public function getRedirectUrl()
    {
        return route('pagseguro.redirect');
    }

    /**
     * @return string
     */
    public function getPagseguroUrl()
    {
        return $this->urls['redirect'];
    }

    /**
     * @param array $urls
     */
    public function setUrls(): void
    {
        $env = $this->sandbox ? $this->environment . '.' : '';
        $this->urls = [
            'preApprovalRequest' => 'https://ws.' . $env . 'pagseguro.uol.com.br/v2/pre-approvals/request',
            'preApproval' => 'https://ws.' . $env . 'pagseguro.uol.com.br/pre-approvals',
            'preApprovalCancel' => 'https://ws.' . $env . 'pagseguro.uol.com.br/v2/pre-approvals/cancel/',
            'cancelTransaction' => 'https://ws.' . $env . 'pagseguro.uol.com.br/v2/transactions/cancels',
            'preApprovalNotifications' => 'https://ws.' . $env . 'pagseguro.uol.com.br/v2/pre-approvals/notifications/',
            'session' => 'https://ws.' . $env . 'pagseguro.uol.com.br/v2/sessions',
            'transactions' => 'https://ws.' . $env . 'pagseguro.uol.com.br/v2/transactions',
            'notifications' => 'https://ws.' . $env . 'pagseguro.uol.com.br/v3/transactions/notifications/',
            'javascript' => 'https://stc.' . $env . 'pagseguro.uol.com.br/pagseguro/api/v2/checkout/pagseguro.directpayment.js',
            'lightbox' => 'https://stc.' . $env . 'pagseguro.uol.com.br/pagseguro/api/v2/checkout/pagseguro.lightbox.js',
            'boletos' => 'https://ws.pagseguro.uol.com.br/recurring-payment/boletos',
            'redirect' => 'https://' . $env . 'pagseguro.uol.com.br/v2/checkout/payment.html?code=',
        ];
    }

    /**
     * @return mixed
     */
    public function getJavascriptUrl()
    {
        return core()->getConfigData(self::CONFIG_TYPE) == 'lightbox' ? $this->urls['lightbox'] : $this->urls['javascript'];
    }

    /**
     * @param $notificationCode
     * @param string $notificationType
     * @return \SimpleXMLElement
     * @throws Exception
     */
    public function notification($notificationCode, $notificationType = 'transaction')
    {
        if ($notificationType == 'transaction') {
            return $this->sendTransaction([
                'email' => $this->email,
                'token' => $this->token,
            ], $this->urls['notifications'] . $notificationCode, false);
        } elseif ($notificationType == 'preApproval') {
            return $this->sendTransaction([
                'email' => $this->email,
                'token' => $this->token,
            ], $this->urls['preApprovalNotifications'] . $notificationCode, false);
        }
    }

    /**
     * @param $reference
     * @return \SimpleXMLElement
     * @throws Exception
     */
    public function transaction($reference)
    {
        return $this->sendTransaction([
            'reference' => $reference,
            'email' => $this->email,
            'token' => $this->token,
        ], $this->urls['transactions'], false);
    }

    /**
     * @param array $parameters
     * @param null $url
     * @param bool $post
     * @param array $headers
     * @return \SimpleXMLElement
     * @throws Exception
     */
    protected function sendTransaction(
        array $parameters,
        $url = null,
        $post = true,
        array $headers = ['Content-Type: application/x-www-form-urlencoded; charset=ISO-8859-1']
    )
    {
        if ($url === null) {
            $url = $this->url['transactions'];
        }

        $parameters = Helper::array_filter_recursive($parameters);

        $data = '';
        foreach ($parameters as $key => $value) {
            $data .= $key . '=' . $value . '&';
        }
        $parameters = rtrim($data, '&');

        $method = 'POST';

        if (!$post) {
            $url .= '?' . $parameters;
            $parameters = null;
            $method = 'GET';
        }

        $result = $this->executeCurl($parameters, $url, $headers, $method);

        return $this->formatResult($result);
    }

    /**
     * @param $result
     * @return \SimpleXMLElement
     * @throws Exception
     */
    private function formatResult($result)
    {
        if ($result === 'Unauthorized' || $result === 'Forbidden') {
            Log::error('Erro ao enviar a transação', ['Retorno:' => $result]);

            throw new Exception($result . ': Não foi possível estabelecer uma conexão com o PagSeguro.', 1001);
        }
        if ($result === 'Not Found') {
            Log::error('Notificação/Transação não encontrada', ['Retorno:' => $result]);

            throw new Exception($result . ': Não foi possível encontrar a notificação/transação no PagSeguro.', 1002);
        }

        try {
            $encoder = new XmlEncoder();
            $result = $encoder->decode($result, 'xml');
            $result = json_decode(json_encode($result), FALSE);
        } catch (Exception $exception) {
            Log::error($exception->getMessage());
        }

        if (isset($result->error) && isset($result->error->message)) {
            Log::error($result->error->message, ['Retorno:' => $result]);

            throw new Exception($result->error->message, (int)$result->error->code);
        }

        return $result;
    }

    /**
     * @param $parameters
     * @param $url
     * @param array $headers
     * @param $method
     * @return bool|string
     * @throws Exception
     */
    private function executeCurl($parameters, $url, array $headers, $method)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        if ($method == 'POST') {
            curl_setopt($curl, CURLOPT_POST, true);
        } elseif ($method == 'PUT') {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
        }

        if ($parameters !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $parameters);
        }

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, !$this->sandbox);

        $result = curl_exec($curl);

        $getInfo = curl_getinfo($curl);
        if (isset($getInfo['http_code']) && $getInfo['http_code'] == '503') {
            Log::error('Serviço em manutenção.', ['Retorno:' => $result]);

            throw new Exception('Serviço em manutenção.', 1000);
        }
        if ($result === false) {
            Log::error('Erro ao enviar a transação', ['Retorno:' => $result]);

            throw new Exception(curl_error($curl), curl_errno($curl));
        }

        curl_close($curl);

        return $result;
    }

    /**
     * @param $name
     * @return string
     */
    protected function fullnameConversion($name)
    {
        $name = preg_replace('/\d/', '', $name);
        $name = preg_replace('/[\n\t\r]/', ' ', $name);
        $name = preg_replace('/\s(?=\s)/', '', $name);
        $name = trim($name);
        $name = explode(' ', $name);
        if(count($name) == 1 ) {
            $name[] = 'dos Santos';
        }
        $name = implode(' ', $name);
        return $name;
    }
}