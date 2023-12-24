<?php
/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestDataMapper;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\PayFlexAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\Before3DFormHashCalculatedEvent;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\PosInterface;

/**
 * Creates request data for PayFlex Common Payment V4 Gateway requests
 */
class PayFlexCPV4PosRequestDataMapper extends AbstractRequestDataMapper
{
    /** @var string */
    public const CREDIT_CARD_EXP_DATE_LONG_FORMAT = 'Ym';

    /**
     * {@inheritDoc}
     */
    protected array $txTypeMappings = [
        PosInterface::TX_TYPE_PAY_AUTH      => 'Sale',
        PosInterface::TX_TYPE_PAY_PRE_AUTH  => 'Auth',
        PosInterface::TX_TYPE_PAY_POST_AUTH => 'Capture',
        PosInterface::TX_TYPE_CANCEL        => 'Cancel',
        PosInterface::TX_TYPE_REFUND        => 'Refund',
        PosInterface::TX_TYPE_HISTORY       => 'TxnHistory',
        PosInterface::TX_TYPE_STATUS        => 'OrderInquiry',
    ];

    /**
     * {@inheritdoc}
     */
    protected array $cardTypeMapping = [
        CreditCardInterface::CARD_TYPE_VISA       => '100',
        CreditCardInterface::CARD_TYPE_MASTERCARD => '200',
        CreditCardInterface::CARD_TYPE_TROY       => '300',
        CreditCardInterface::CARD_TYPE_AMEX       => '400',
    ];

    /**
     * {@inheritdoc}
     */
    protected array $langMappings = [
        PosInterface::LANG_TR => 'tr-TR',
        PosInterface::LANG_EN => 'en-US',
    ];

    /**
     * {@inheritdoc}
     */
    protected array $recurringOrderFrequencyMapping = [
        'DAY'   => 'Day',
        'MONTH' => 'Month',
        'YEAR'  => 'Year',
    ];

    /**
     * todo implement
     * {@inheritDoc}
     *
     * @param PayFlexAccount $account
     */
    public function create3DPaymentRequestData(AbstractPosAccount $account, array $order, string $txType, array $responseData, ?CreditCardInterface $card = null): array
    {
        throw new NotImplementedException();
    }


    /**
     * @param PayFlexAccount                                     $account
     * @param array{TransactionId: string, PaymentToken: string} $responseData
     *
     * @return array{HostMerchantId: string, Password: string, TransactionId: string, PaymentToken: string}
     */
    public function create3DPaymentStatusRequestData(AbstractPosAccount $account, array $responseData): array
    {
        return $this->getRequestAccountData($account) + [
                'HostMerchantId' => $account->getClientId(),
                'Password'       => $account->getPassword(),
                'TransactionId'  => $responseData['TransactionId'],
                'PaymentToken'   => $responseData['PaymentToken'],
            ];
    }

    /**
     * @phpstan-param PosInterface::TX_TYPE_PAY_AUTH|PosInterface::TX_TYPE_PAY_PRE_AUTH $txType
     * @phpstan-param PosInterface::MODEL_3D_*                                          $paymentModel
     *
     * @param PayFlexAccount                       $account
     * @param array<string, int|string|float|null> $order
     * @param string                               $txType
     * @param string                               $paymentModel
     * @param CreditCardInterface|null             $card
     *
     * @return array<string, string>
     */
    public function create3DEnrollmentCheckRequestData(AbstractPosAccount $account, array $order, string $txType, string $paymentModel, ?CreditCardInterface $card = null): array
    {
        $order = $this->preparePaymentOrder($order);

        $requestData = [
            'HostMerchantId'       => $account->getClientId(),
            'MerchantPassword'     => $account->getPassword(),
            'HostTerminalId'       => $account->getTerminalId(),
            'TransactionType'      => $this->mapTxType($txType),
            'AmountCode'           => $this->mapCurrency($order['currency']),
            'Amount'               => $this->formatAmount($order['amount']),
            'OrderID'              => (string) $order['id'],
            'IsSecure'             => 'true', // Işlemin 3D yapılıp yapılmayacağına dair flag, alabileceği değerler: 'true', 'false'
            /**
             * 3D Programına Dahil Olmayan Kartlar ile İşlem Yapma Flagi: "3D İşlem Flagi" (IsSecure) "true" gönderilmiş
             * işlemler için bir alt seçenektir. Kart sahibi "3D Secure" programına dahil değilse Ortak Ödemenin işlemi
             * Sanal Pos'a gönderip göndermeyeceğini belirtir. "true" gönderilmesi durumunda kart sahibi
             * 3D Secure programına dahil olmasa bile işlemi Sanal Pos'a gönderecektir.
             * Bu tür işlemler "Half Secure" olarak işaretlenecektir.
             */
            'AllowNotEnrolledCard' => 'false',
            'SuccessUrl'           => (string) $order['success_url'],
            'FailUrl'              => (string) $order['fail_url'],
            'RequestLanguage'      => $this->getLang($account, $order),
            /**
             * Bu alanda gönderilecek değer kart hamili
             * ektresinde işlem açıklamasında çıkacaktır.
             * (Abone no vb. bilgiler gönderilebilir)
             */
            'Extract'              => '',
            /**
             * Uye işyeri tarafından işleme ait ek bilgiler varsa CustomItems alanında gönderilir.
             * İçeriğinde "name" ve "value" attirbutelarını barındırır.
             * Örnek: İsim1:Değer1 İsim2:Değer2 İsim3:Değer3
             */
            'CustomItems'          => '',
        ];

        if ($card instanceof CreditCardInterface) {
            $requestData += [
                'BrandNumber'     => $this->cardTypeMapping[$card->getType()],
                'CVV'             => $card->getCvv(),
                'PAN'             => $card->getNumber(),
                'ExpireMonth'     => $card->getExpireMonth(),
                'ExpireYear'      => $card->getExpireYear(),
                'CardHoldersName' => (string) $card->getHolderName(),
            ];
        }

        if ($order['installment']) {
            $requestData['InstallmentCount'] = $this->mapInstallment($order['installment']);
        }

        $event = new Before3DFormHashCalculatedEvent($requestData, $account->getBank(), $txType, $paymentModel);
        $this->eventDispatcher->dispatch($event);
        $requestData = $event->getRequestData();

        $requestData['HashedData'] = $this->crypt->create3DHash($account, $requestData);

        return $requestData;
    }

    /**
     * TODO implement
     * {@inheritDoc}
     *
     * @param PayFlexAccount $account
     *
     * @return array<string, string>
     */
    public function createNonSecurePaymentRequestData(AbstractPosAccount $account, array $order, string $txType, CreditCardInterface $card): array
    {
        $order = $this->preparePaymentOrder($order);

        return $this->getRequestAccountData($account) + [
                'TransactionType'         => $this->mapTxType($txType),
                'OrderId'                 => (string) $order['id'],
                'CurrencyAmount'          => $this->formatAmount($order['amount']),
                'CurrencyCode'            => $this->mapCurrency($order['currency']),
                'ClientIp'                => (string) $order['ip'],
                'TransactionDeviceSource' => '0',
                'Pan'                     => $card->getNumber(),
                'Expiry'                  => $card->getExpirationDate(self::CREDIT_CARD_EXP_DATE_LONG_FORMAT),
                'Cvv'                     => $card->getCvv(),
            ];
    }

    /**
     * @param PayFlexAccount                       $account
     * @param array<string, int|string|float|null> $order
     *
     * @return array{TransactionType: string, ReferenceTransactionId: string,
     *     CurrencyAmount: string, CurrencyCode: string, ClientIp: string,
     *     MerchantId: string, Password: string}
     */
    public function createNonSecurePostAuthPaymentRequestData(AbstractPosAccount $account, array $order): array
    {
        $order = $this->preparePostPaymentOrder($order);

        return $this->getRequestAccountData($account) + [
                'TransactionType'        => $this->mapTxType(PosInterface::TX_TYPE_PAY_POST_AUTH),
                'ReferenceTransactionId' => (string) $order['id'],
                'CurrencyAmount'         => $this->formatAmount($order['amount']),
                'CurrencyCode'           => $this->mapCurrency($order['currency']),
                'ClientIp'               => (string) $order['ip'],
            ];
    }

    /**
     * {@inheritDoc}
     */
    public function createStatusRequestData(AbstractPosAccount $account, array $order): array
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritDoc}
     *
     * @param PayFlexAccount $account
     *
     * @return array{MerchantId: string, Password: string, TransactionType: string, ReferenceTransactionId: string,
     *     ClientIp: string}
     */
    public function createCancelRequestData(AbstractPosAccount $account, array $order): array
    {
        $order = $this->prepareCancelOrder($order);

        return $this->getRequestAccountData($account) + [
                'TransactionType'        => $this->mapTxType(PosInterface::TX_TYPE_CANCEL),
                'ReferenceTransactionId' => (string) $order['trans_id'],
                'ClientIp'               => (string) $order['ip'],
            ];
    }

    /**
     * {@inheritDoc}
     *
     * @param PayFlexAccount $account
     *
     * @return array{MerchantId: string, Password: string, TransactionType: string, ReferenceTransactionId: string,
     *     ClientIp: string, CurrencyAmount: string}
     */
    public function createRefundRequestData(AbstractPosAccount $account, array $order): array
    {
        $order = $this->prepareRefundOrder($order);

        return $this->getRequestAccountData($account) + [
                'TransactionType'        => $this->mapTxType(PosInterface::TX_TYPE_REFUND),
                'ReferenceTransactionId' => (string) $order['trans_id'],
                'ClientIp'               => (string) $order['ip'],
                'CurrencyAmount'         => $this->formatAmount($order['amount']),
            ];
    }

    /**
     * {@inheritDoc}
     */
    public function createHistoryRequestData(AbstractPosAccount $account, array $order, array $extraData = []): array
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritDoc}
     *
     * @param array<string, int|string|float|null>|null             $order kullanilmiyor
     * @param array{CommonPaymentUrl: string, PaymentToken: string} $extraData
     *
     * @return array{gateway: string, method: 'GET', inputs: array{Ptkn: string}}
     */
    public function create3DFormData(
        ?AbstractPosAccount  $account,
        ?array               $order,
        ?string              $paymentModel,
        ?string              $txType,
        ?string              $gatewayURL,
        ?CreditCardInterface $card = null,
        array                $extraData = []): array
    {
        return [
            'gateway' => $extraData['CommonPaymentUrl'],
            'method'  => 'GET',
            'inputs'  => [
                'Ptkn' => $extraData['PaymentToken'],
            ],
        ];
    }

    /**
     * Amount Formatter
     *
     * @param float $amount
     *
     * @return string ex: 10.1 => 10.10
     */
    protected function formatAmount(float $amount): string
    {
        return \number_format($amount, 2, '.', '');
    }

    /**
     * 0 => '0'
     * 1 => '0'
     * 2 => '2'
     * @inheritDoc
     */
    protected function mapInstallment(int $installment): string
    {
        return $installment > 1 ? (string) $installment : '0';
    }

    /**
     * @inheritDoc
     */
    protected function preparePaymentOrder(array $order): array
    {
        return array_merge($order, [
            'installment' => $order['installment'] ?? 0,
            'currency'    => $order['currency'] ?? PosInterface::CURRENCY_TRY,
            'amount'      => $order['amount'],
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function preparePostPaymentOrder(array $order): array
    {
        return [
            'id'       => $order['id'],
            'amount'   => $order['amount'],
            'currency' => $order['currency'] ?? PosInterface::CURRENCY_TRY,
            'ip'       => $order['ip'],
        ];
    }

    /**
     * @inheritDoc
     */
    protected function prepareRefundOrder(array $order): array
    {
        return [
            'trans_id' => $order['trans_id'],
            'ip'       => $order['ip'],
            'amount'   => $order['amount'],
        ];
    }

    /**
     * @inheritDoc
     */
    protected function prepareCancelOrder(array $order): array
    {
        return [
            'trans_id' => $order['trans_id'],
            'ip'       => $order['ip'],
        ];
    }

    /**
     * @inheritDoc
     */
    protected function prepareHistoryOrder(array $order): array
    {
        return [
            'id' => $order['id'] ?? null,
        ];
    }

    /**
     * @param PayFlexAccount $account
     *
     * @return array{MerchantId: string, Password: string}
     */
    private function getRequestAccountData(AbstractPosAccount $account): array
    {
        return [
            'MerchantId' => $account->getClientId(),
            'Password'   => $account->getPassword(),
        ];
    }
}
