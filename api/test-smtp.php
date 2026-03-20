<?php
/**
 * Temporary SMTP test endpoint.
 * DELETE THIS FILE after confirming email works.
 *
 * Usage: curl https://prowaylab.com/api/test-smtp.php
 */
declare(strict_types=1);

require __DIR__ . '/config/database.php';
require __DIR__ . '/vendor/autoload.php';

header('Content-Type: application/json');

// Debug: show what parse_ini_file loaded for MAIL vars
$debug = [];
foreach ($_ENV as $k => $v) {
    if (str_starts_with($k, 'MAIL')) {
        $debug[$k] = $k === 'MAIL_SMTP_PASS' ? '***(' . strlen($v) . ' chars)' : $v;
    }
}

use PHPMailer\PHPMailer\PHPMailer;

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = $_ENV['MAIL_SMTP_HOST'] ?? 'smtp.privateemail.com';
    $mail->Port       = (int) ($_ENV['MAIL_SMTP_PORT'] ?? 465);
    $mail->SMTPAuth   = true;
    $mail->Username   = $_ENV['MAIL_SMTP_USER'] ?? '';
    $mail->Password   = $_ENV['MAIL_SMTP_PASS'] ?? '';
    $mail->SMTPSecure = $mail->Port === 587 ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
    $mail->CharSet    = 'UTF-8';
    $mail->Timeout    = 15;

    $mail->setFrom(
        $_ENV['MAIL_FROM'] ?? $mail->Username,
        $_ENV['MAIL_FROM_NAME'] ?? 'ProWay Lab'
    );
    $mail->addAddress($_ENV['MAIL_SMTP_USER'] ?? 'info@wellcorefitness.com');
    $mail->Subject = 'Test SMTP — ProWay Lab ' . date('H:i:s');
    $mail->Body    = 'Si recibes esto, el SMTP de ProWay Lab funciona correctamente.';

    $mail->send();
    echo json_encode(['status' => 'ok', 'message' => 'Email sent', 'env' => $debug], JSON_PRETTY_PRINT);
} catch (\Exception $e) {
    echo json_encode([
        'status'  => 'error',
        'message' => $mail->ErrorInfo,
        'env'     => $debug,
    ], JSON_PRETTY_PRINT);
}
