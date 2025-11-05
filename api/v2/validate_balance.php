<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/api_bootstrap.php';

api_require_role(['member', 'planer', 'kassenaufsicht', 'admin']);

api_require_method('POST');
$data = api_json_input();
api_enforce_csrf($data);

$amount = isset($data['amount']) ? (float) $data['amount'] : 0.0;
if ($amount <= 0) {
    api_send_response('success', ['status' => 'ok', 'available' => api_get_cash_overview($mysqli)]);
}

$overview = api_get_cash_overview($mysqli);

$availableAfter = $overview['available'] - $amount;
$totalAfter = $overview['balance'] - $amount;

$status = 'ok';
if ($availableAfter < 0 && $totalAfter >= 0) {
    $status = 'warn';
} elseif ($totalAfter < 0) {
    $status = 'block';
}

api_send_response('success', [
    'status' => $status,
    'available' => $overview,
    'amount' => round($amount, 2),
    'availableAfter' => round($availableAfter, 2),
]);
