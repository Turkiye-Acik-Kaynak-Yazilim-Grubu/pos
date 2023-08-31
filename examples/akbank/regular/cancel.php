<?php

use Mews\Pos\PosInterface;

$templateTitle = 'Cancel Order';
require '_config.php';
require '../../_templates/_header.php';

$ord = $session->get('order') ?: getNewOrder($baseUrl, $ip, $request->get('currency', 'TRY'), $session);

if (isset($ord['recurringFrequency'])) {
    //tekrarlanan odemenin durumunu sorgulamak icin:
    $order = [
        // tekrarlanan odeme sonucunda banktan donen deger: $response['Extra']['RECURRINGID']
        'id' => $ord['id'],
        //hangi taksidi iptal etmek istiyoruz:
        'recurringOrderInstallmentNumber' => $ord['recurringInstallmentCount'],
    ];
    // Not: bu islem sadece bekleyen odemeyi iptal eder
} else {
    $order = [
        'id' => $ord['id'],
    ];
}

$transaction = PosInterface::TX_CANCEL;

$pos->cancel($order);

$response = $pos->getResponse();
require '../../_templates/_simple_response_dump.php';
require '../../_templates/_footer.php';
