<?php

use Mews\Pos\PosInterface;

require '_config.php';

$templateTitle = 'Post Auth Order (ön provizyonu tamamlama)';

$order = $session->get('order') ?: getNewOrder($baseUrl, $ip, $request->get('currency', PosInterface::CURRENCY_TRY));

$order = [
    'id'          => $order['id'],
    'amount'      => $order['amount'],
    'currency'    => $order['currency'],
    'ip'          => $order['ip'],
    'ref_ret_num' => '829603332856',
];

$session->set('post_order', $order);
$transaction = PosInterface::TX_POST_PAY;
$card = null;

require '../../_templates/_payment_response.php';
