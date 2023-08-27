<?php

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/3d-pay/';

$hostMerchantId = '000100000013506';
$hostTerminalId = 'VP000579';
$merchantPassword  = '123456';
//account bilgileri kendi account bilgilerinizle degistiriniz
$account = \Mews\Pos\Factory\AccountFactory::createPayFlexAccount(
    'vakifbank-cp',
    $hostMerchantId,
    $merchantPassword,
    $hostTerminalId,
    \Mews\Pos\Gateways\AbstractGateway::MODEL_3D_PAY
);

$pos = getGateway($account);

$transaction = \Mews\Pos\Gateways\AbstractGateway::TX_PAY;

$templateTitle = '3D Pay Model Payment';
$paymentModel = \Mews\Pos\Gateways\AbstractGateway::MODEL_3D_PAY;
