<?php

use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/3d-pay/';
//account bilgileri kendi account bilgilerinizle degistiriniz
$account = AccountFactory::createGarantiPosAccount(
    'garanti',
    '7000679',
    'PROVAUT',
    '123qweASD/',
    '30691298',
    PosInterface::MODEL_3D_PAY,
    '12345678'
);

$pos = getGateway($account, $eventDispatcher);

$transaction = PosInterface::TX_TYPE_PAY_AUTH;

$templateTitle = '3D Pay Model Payment';
$paymentModel = PosInterface::MODEL_3D_PAY;
