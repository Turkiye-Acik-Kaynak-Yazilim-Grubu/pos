<?php

use Mews\Pos\PosInterface;

$templateTitle = 'History Order';

require '_config.php';
require '../../_templates/_header.php';

$ord = $session->get('order');

$order = [
    'order_id' => $ord ? $ord['id'] : '973009309',
];

$transaction = PosInterface::TX_TYPE_HISTORY;
// History Order
$query = $pos->history($order);

$response = $query->getResponse();
require '../../_templates/_simple_response_dump.php';
require '../../_templates/_footer.php';
