<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Gateways;

use Mews\Pos\DataMapper\PayForPosRequestDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\PayForPosResponseDataMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\PayForAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Exceptions\HashMismatchException;
use Mews\Pos\PosInterface;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;

/**
 * Class PayForPos
 */
class PayForPos extends AbstractGateway
{
    /** @var string */
    public const NAME = 'PayForPOS';

    /** @var PayForAccount */
    protected $account;

    /** @var PayForPosRequestDataMapper */
    protected $requestDataMapper;

    /** @var PayForPosResponseDataMapper */
    protected $responseDataMapper;

    /** @return PayForAccount */
    public function getAccount(): AbstractPosAccount
    {
        return $this->account;
    }

    /**
     * @inheritDoc
     */
    public function make3DPayment(Request $request, array $order, string $txType, AbstractCreditCard $card = null)
    {
        $request      = $request->request;
        $bankResponse = null;
        if (!$this->requestDataMapper->getCrypt()->check3DHash($this->account, $request->all())) {
            throw new HashMismatchException();
        }

        //if customer 3d verification passed finish payment
        if ('1' === $request->get('3DStatus')) {
            //valid ProcReturnCode is V033 in case of success 3D Authentication
            $contents     = $this->create3DPaymentXML($request->all(), $order, $txType);
            $bankResponse = $this->send($contents);
        } else {
            $this->logger->log(LogLevel::ERROR, '3d auth fail', ['md_status' => $request->get('3DStatus')]);
        }

        $this->response = $this->responseDataMapper->map3DPaymentData($request->all(), $bankResponse);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function make3DPayPayment(Request $request)
    {
        $this->response = $this->responseDataMapper->map3DPayResponseData($request->request->all());

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function make3DHostPayment(Request $request)
    {
        return $this->make3DPayPayment($request);
    }

    /**
     * Refund Order
     * refund amount should be exactly the same with order amount.
     * otherwise operation will be rejected
     *
     * Warning: You can not use refund for purchases made at the same date.
     * Instead, you need to use cancel.
     *
     * @inheritDoc
     */
    public function refund(array $order)
    {
        return parent::refund($order);
    }

    /**
     * Fetches All Transaction/Action/Order history, both failed and successful, for the given date ReqDate
     * or transactions related to the queried order if orderId is given
     * Note: history request to gateway returns JSON response
     * If both reqDate and orderId provided then finansbank will take into account only orderId
     *
     * returns list array or items for the given date,
     * if orderId specified in request then return array of transactions (refund|pre|post|cancel)
     * both successful and failed, for the related orderId
     * @inheritDoc
     */
    public function history(array $meta)
    {
        return parent::history($meta);
    }


    /**
     * {@inheritDoc}
     */
    public function get3DFormData(array $order, string $paymentModel, string $txType, AbstractCreditCard $card = null): array
    {
        $this->logger->log(LogLevel::DEBUG, 'preparing 3D form data');

        $gatewayURL = $this->get3DGatewayURL();
        if (PosInterface::MODEL_3D_HOST === $paymentModel) {
            $gatewayURL = $this->get3DHostGatewayURL();
        }

        return $this->requestDataMapper->create3DFormData($this->account, $order, $paymentModel, $txType, $gatewayURL, $card);
    }


    /**
     * @inheritDoc
     */
    public function send($contents, string $txType = null, ?string $url = null): array
    {
        $url = $this->getApiURL();
        $this->logger->log(LogLevel::DEBUG, 'sending request', ['url' => $url]);
        $response = $this->client->post($url, [
            'headers' => [
                'Content-Type' => 'text/xml; charset=UTF-8',
            ],
            'body'    => $contents,
        ]);
        $this->logger->log(LogLevel::DEBUG, 'request completed', ['status_code' => $response->getStatusCode()]);

        $response = $response->getBody()->getContents();

        /**
         * Finansbank XML Response some times are in following format:
         * <MbrId>5</MbrId>\r\n
         * <MD>\r\n
         * </MD>\r\n
         * <Hash>\r\n
         * </Hash>\r\n
         * redundant whitespaces causes non-empty value for response properties
         */
        $response = preg_replace('/\\r\\n\s*/', '', $response);

        try {
            $this->data = $this->XMLStringToArray($response);
        } catch (NotEncodableValueException $notEncodableValueException) {
            //Finansbank's history request response is in JSON format
            $this->data = json_decode($response, true);
        }

        return $this->data;
    }

    /**
     * @inheritDoc
     */
    public function createXML(array $nodes, string $encoding = 'UTF-8', bool $ignorePiNode = false): string
    {
        return parent::createXML(['PayforRequest' => $nodes], $encoding, $ignorePiNode);
    }

    /**
     * @inheritDoc
     */
    public function createRegularPaymentXML(array $order, AbstractCreditCard $card, string $txType): string
    {
        $requestData = $this->requestDataMapper->createNonSecurePaymentRequestData($this->account, $order, $txType, $card);

        return $this->createXML($requestData);
    }

    /**
     * @inheritDoc
     */
    public function createRegularPostXML(array $order): string
    {
        $requestData = $this->requestDataMapper->createNonSecurePostAuthPaymentRequestData($this->account, $order);

        return $this->createXML($requestData);
    }

    /**
     * @inheritDoc
     *
     * @param PosInterface::TX_* $txType kullanilmiyor
     */
    public function create3DPaymentXML(array $responseData, array $order, string $txType, AbstractCreditCard $card = null): string
    {
        $requestData = $this->requestDataMapper->create3DPaymentRequestData($this->account, $order, $txType, $responseData);

        return $this->createXML($requestData);
    }

    /**
     * @inheritDoc
     */
    public function createStatusXML(array $order): string
    {
        $requestData = $this->requestDataMapper->createStatusRequestData($this->account, $order);

        return $this->createXML($requestData);
    }

    /**
     * @inheritDoc
     */
    public function createHistoryXML(array $customQueryData): string
    {
        $requestData = $this->requestDataMapper->createHistoryRequestData($this->account, $customQueryData, $customQueryData);

        return $this->createXML($requestData);
    }

    /**
     * @inheritDoc
     */
    public function createRefundXML(array $order): string
    {
        $requestData = $this->requestDataMapper->createRefundRequestData($this->account, $order);

        return $this->createXML($requestData);
    }

    /**
     * @inheritDoc
     */
    public function createCancelXML(array $order): string
    {
        $requestData = $this->requestDataMapper->createCancelRequestData($this->account, $order);

        return $this->createXML($requestData);
    }
}
