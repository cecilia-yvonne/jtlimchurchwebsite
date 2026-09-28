<?php
/**
 * mail.php
 * Saves contact form messages into MySQL (XAMPP) — church_db.contact_messages
 * Sends notification email via PHP's mail() using XAMPP's sendmail + Gmail SMTP
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

// ---------- DB CONFIG ----------
$DB_HOST = "127.0.0.1";
$DB_NAME = "church_db";
$DB_USER = "root";
$DB_PASS = "";

// ---------- EMAIL CONFIG ----------
$TO_EMAIL   = "ceciliayvonne06@gmail.com";   // where you want to receive messages
$FROM_EMAIL = "ceciliayvonne06@gmail.com";   // same Gmail used in sendmail.ini

header("Content-Type: application/json");

// ---------- INPUT (matches your form's field names: cf-name, cf-email, etc.) ----------
$name    = trim($_POST['cf-name'] ?? '');
$email   = trim($_POST['cf-email'] ?? '');
$phone   = trim($_POST['cf-phone'] ?? '');
$subject = trim($_POST['cf-subject'] ?? '');
$message = trim($_POST['cf-message'] ?? '');

if (!$name || !$email || !$message) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Name, email, and message are required"]);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Invalid email address"]);
    exit;
}

// ---------- DB CONNECT ----------
try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "DB connection failed: " . $e->getMessage()]);
    exit;
}

// ---------- SAVE TO DATABASE ----------
try {
    $stmt = $pdo->prepare(
        "INSERT INTO contact_messages (name, email, phone, subject, message, created_at)
         VALUES (?, ?, ?, ?, ?, NOW())"
    );
    $stmt->execute([$name, $email, $phone, $subject, $message]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Failed to save message: " . $e->getMessage()]);
    exit;
}

$result = ["success" => true, "message" => "Message saved"];

// ---------- SEND EMAIL ----------
$emailSubject = "New contact form message: " . ($subject ?: "No subject");
$emailBody = "Name: $name\n" .
             "Email: $email\n" .
             "Phone: $phone\n" .
             "Subject: $subject\n\n" .
             "Message:\n$message";

$headers = "From: $FROM_EMAIL\r\n" .
           "Reply-To: $email\r\n" .
           "Content-Type: text/plain; charset=UTF-8";

$sent = @mail($TO_EMAIL, $emailSubject, $emailBody, $headers);

if ($sent) {
    $result["email"] = "sent";
} else {
    $result["email"] = "failed";
    $result["email_error"] = error_get_last()["message"] ?? "Unknown mail error";
}

echo json_encode($result);