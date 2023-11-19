<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Tests\DataMapper\RequestDataMapper;

use Mews\Pos\DataMapper\RequestDataMapper\EstV3PosRequestDataMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Factory\CryptFactory;
use Mews\Pos\Factory\PosFactory;
use Mews\Pos\Gateways\EstV3Pos;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;

class EstV3PosRequestDataMapperTest extends TestCase
{
    /** @var AbstractPosAccount */
    private $account;

    /** @var CreditCardInterface */
    private $card;

    /** @var EstV3PosRequestDataMapper */
    private $requestDataMapper;

    private $order;

    private $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = require __DIR__.'/../../../config/pos_test.php';

        $this->account = AccountFactory::createEstPosAccount(
            'akbankv3',
            '190100000',
            'ZIRAATAPI',
            'ZIRAAT19',
            PosInterface::MODEL_3D_SECURE,
            '123456'
        );

        $this->order = [
            'id'          => 'order222',
            'ip'          => '127.0.0.1',
            'amount'      => '100.25',
            'installment' => 0,
            'currency'    => PosInterface::CURRENCY_TRY,
            'success_url' => 'https://domain.com/success',
            'fail_url'    => 'https://domain.com/fail_url',
            'lang'        => 'tr',
            'rand'        => 'rand-21212',
        ];

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $pos        = PosFactory::createPosGateway($this->account, $this->config, $dispatcher);

        $crypt                   = CryptFactory::createGatewayCrypt(EstV3Pos::class, new NullLogger());
        $this->requestDataMapper = new EstV3PosRequestDataMapper($dispatcher, $crypt);
        $this->card              = CreditCardFactory::create($pos, '5555444433332222', '22', '01', '123', 'ahmet', CreditCardInterface::CARD_TYPE_VISA);
    }

    /**
     * @return void
     */
    public function testGet3DFormData()
    {
        $account = $this->account;
        $txType  = PosInterface::TX_PAY;
        $card       = $this->card;
        $gatewayURL = $this->config['banks'][$this->account->getBank()]['gateway_endpoints']['gateway_3d'];

        $inputs = [
            'clientid'      => $account->getClientId(),
            'storetype'     => PosInterface::MODEL_3D_SECURE,
            'amount'        => $this->order['amount'],
            'oid'           => $this->order['id'],
            'okUrl'         => $this->order['success_url'],
            'failUrl'       => $this->order['fail_url'],
            'callbackUrl'   => $this->order['fail_url'],
            'rnd'           => $this->order['rand'],
            'hashAlgorithm' => 'ver3',
            'lang'          => 'tr',
            'currency'      => 949,
            'TranType'      => 'Auth',
            'taksit'        => '',
        ];

        $hash           = '7MGmDH2CY9jlFd4kVHwdvlyt07hIqkiBWVR8bkjAiaaRhK2XUHyvrDLqv0vlG6YY8bXChDkLfnDwVLi3Pvg5lQ==';
        $inputs['hash'] = $hash;
        $form           = [
            'gateway' => $gatewayURL,
            'method'  => 'POST',
            'inputs'  => $inputs,
        ];
        //test without card
        $this->assertEquals($form, $this->requestDataMapper->create3DFormData(
            $this->account,
            $this->order,
            PosInterface::MODEL_3D_SECURE,
            $txType,
            $gatewayURL
        ));

        //test with card
        $form['inputs']['cardType']                        = '1';
        $form['inputs']['pan']                             = $card->getNumber();
        $form['inputs']['Ecom_Payment_Card_ExpDate_Month'] = '01';
        $form['inputs']['Ecom_Payment_Card_ExpDate_Year']  = '22';
        $form['inputs']['cv2']                             = $card->getCvv();

        unset($form['inputs']['hash']);
        $form['inputs']['hash'] = '7EgK2aMhS848ZMEkl2d0s1dFXlMhCy4LS5FFk+k/FbEmmVqBqx6TtQ1Yg7aW0KQa/5hrQODwBVT3SCUwfXHEsg==';

        $this->assertEquals($form, $this->requestDataMapper->create3DFormData(
            $this->account,
            $this->order,
            PosInterface::MODEL_3D_SECURE,
            $txType,
            $gatewayURL,
            $card
        ));
    }

    /**
     * @return void
     */
    public function testGet3DHostFormData()
    {
        $gatewayURL = $this->config['banks'][$this->account->getBank()]['gateway_endpoints']['gateway_3d'];

        $inputs     = [
            'clientid'  => $this->account->getClientId(),
            'storetype' => '3d_host',
            'amount'    => $this->order['amount'],
            'oid'       => $this->order['id'],
            'okUrl'     => $this->order['success_url'],
            'failUrl'   => $this->order['fail_url'],
            'callbackUrl' => $this->order['fail_url'],
            'rnd'       => $this->order['rand'],
            'hashAlgorithm' => 'ver3',
            'lang'      => 'tr',
            'currency'  => '949',
            'TranType'  => 'Auth',
            'taksit'    => '',
        ];
        $form       = [
            'gateway' => $gatewayURL,
            'method'  => 'POST',
            'inputs'  => $inputs,
        ];

        $form['inputs']['hash']       = 'pxvr9oG9G6v2AU/Lci3qs7OiBAwcvAaLotG5rorJVe31DJN/wlVDReWpqFSJojTLPs6pPiS1L1U+QkE0dJJBKw==';

        $this->assertEquals($form, $this->requestDataMapper->create3DFormData(
            $this->account,
            $this->order,
            PosInterface::MODEL_3D_HOST,
            PosInterface::TX_PAY,
            $gatewayURL
        ));
    }
}
