<?php

header('Content-Type: application/json');

$payload = file_get_contents('php://input');

if ($payload === false || $payload === '') {
    http_response_code(400);
    echo json_encode(array('ResultCode' => 1, 'ResultDesc' => 'Empty callback payload'));
    exit;
}

http_response_code(200);
echo json_encode(array('ResultCode' => 0, 'ResultDesc' => 'Accepted'));

?>