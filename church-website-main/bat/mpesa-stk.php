<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

function respond(bool $success, string $message, int $status = 200): void {
    http_response_code($status);
    echo json_encode(array('success' => $success, 'message' => $message));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request.', 405);
}

$phone = preg_replace('/\D+/', '', isset($_POST['phone']) ? $_POST['phone'] : '');
$amount = filter_var(isset($_POST['amount']) ? $_POST['amount'] : null, FILTER_VALIDATE_INT);

if (substr($phone, 0, 1) === '0') {
    $phone = '254' . substr($phone, 1);
}

if (!preg_match('/^254[17]\d{8}$/', $phone)) {
    respond(false, 'Enter a valid Kenyan M-Pesa phone number.', 400);
}

if ($amount === false || $amount < 1) {
    respond(false, 'Enter a valid whole-number amount in KES.', 400);
}

$accountReference = 'JTLIM Church';
$environment = 'sandbox';

$apiBase = $environment === 'sandbox'
    ? 'https://sandbox.safaricom.co.ke'
    : 'https://api.safaricom.co.ke';

function request_json(string $url, array $headers, ?string $body = null): array {
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_TIMEOUT, 30);
    if ($body !== null) {
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
    }
    $response = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($response === false || $status < 200 || $status >= 300) {
        return array(null, $status);
    }

    return array(json_decode($response, true), $status);
}

$authorization = base64_encode($consumerKey . ':' . $consumerSecret);
list($tokenResponse, $tokenStatus) = request_json(
    $apiBase . '/oauth/v1/generate?grant_type=client_credentials',
    array('Authorization: Basic ' . $authorization)
);

if (!$tokenResponse || empty($tokenResponse['access_token'])) {
    respond(false, 'Unable to connect to M-Pesa. Please try again later.', 502);
}

$timestamp = date('YmdHis');
$password = base64_encode($shortcode . $passkey . $timestamp);
$stkRequest = array(
    'BusinessShortCode' => $shortcode,
    'Password' => $password,
    'Timestamp' => $timestamp,
    'TransactionType' => 'CustomerPayBillOnline',
    'Amount' => $amount,
    'PartyA' => $phone,
    'PartyB' => $shortcode,
    'PhoneNumber' => $phone,
    'CallBackURL' => $callbackUrl,
    'AccountReference' => $accountReference,
    'TransactionDesc' => 'JTLIM Church giving'
);

list($stkResponse, $stkStatus) = request_json(
    $apiBase . '/mpesa/stkpush/v1/processrequest',
    array(
        'Authorization: Bearer ' . $tokenResponse['access_token'],
        'Content-Type: application/json'
    ),
    json_encode($stkRequest)
);

if (!$stkResponse || !empty($stkResponse['errorCode'])) {
    respond(false, 'M-Pesa could not start the payment. Please try again.', 502);
}

respond(true, 'Payment prompt sent. Check your phone and enter your M-Pesa PIN.');

?>