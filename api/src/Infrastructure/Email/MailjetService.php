<?php

declare(strict_types=1);

namespace ProWay\Infrastructure\Email;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

/**
 * Transactional email service.
 *
 * Primary:  Mailjet REST API v3.1 (when MAILJET_API_KEY is set).
 * Fallback: SMTP via PHPMailer (smtp.privateemail.com or any host in MAIL_SMTP_HOST).
 *
 * Docs: https://dev.mailjet.com/email/guides/send-api-v31/
 */
class MailjetService
{
    private const MJ_API_URL = 'https://api.mailjet.com/v3.1/send';

    private string $apiKey;
    private string $secretKey;
    private string $fromEmail;
    private string $fromName;

    // SMTP fallback settings
    private string $smtpHost;
    private int    $smtpPort;
    private string $smtpUser;
    private string $smtpPass;

    public function __construct()
    {
        $this->apiKey    = $_ENV['MAILJET_API_KEY']    ?? '';
        $this->secretKey = $_ENV['MAILJET_SECRET_KEY'] ?? '';
        $this->fromEmail = $_ENV['MAIL_FROM']          ?? 'info@prowaylab.com';
        $this->fromName  = $_ENV['MAIL_FROM_NAME']     ?? 'ProWay Lab';

        $this->smtpHost = $_ENV['MAIL_SMTP_HOST'] ?? 'smtp.privateemail.com';
        $this->smtpPort = (int) ($_ENV['MAIL_SMTP_PORT'] ?? 465);
        $this->smtpUser = $_ENV['MAIL_SMTP_USER'] ?? $this->fromEmail;
        $this->smtpPass = $_ENV['MAIL_SMTP_PASS'] ?? '';
    }

    /**
     * Send a transactional email. Uses Mailjet if keys are set, otherwise SMTP.
     */
    public function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlContent,
        ?string $textContent = null
    ): bool {
        if ($this->apiKey !== '' && $this->secretKey !== '') {
            return $this->sendViaMailjet($toEmail, $toName, $subject, $htmlContent, $textContent);
        }

        if ($this->smtpPass !== '') {
            return $this->sendViaSmtp($toEmail, $toName, $subject, $htmlContent, $textContent);
        }

        return false;
    }

    public function sendPaymentConfirmation(array $invoice, array $client): bool
    {
        $subject = "Pago confirmado — Factura #{$invoice['invoice_number']}";
        $amount  = number_format((float) ($invoice['total_cop'] ?? $invoice['amount_cop'] ?? 0), 0, ',', '.');
        $html    = $this->buildPaymentConfirmationHtml($invoice, $client, $amount);
        $name    = $client['nombre'] ?? $client['name'] ?? 'Cliente';
        $email   = $client['email'] ?? '';

        return $this->send($email, $name, $subject, $html);
    }

    public function sendWelcome(array $client): bool
    {
        $subject = 'Bienvenido a ProWay Lab';
        $html    = $this->buildWelcomeHtml($client);
        $name    = $client['nombre'] ?? $client['name'] ?? 'Cliente';
        $email   = $client['email'] ?? '';

        return $this->send($email, $name, $subject, $html);
    }

    // ── Private transport methods ──────────────────────────────────────────────

    private function sendViaMailjet(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlContent,
        ?string $textContent
    ): bool {
        $payload = [
            'Messages' => [[
                'From'     => ['Email' => $this->fromEmail, 'Name' => $this->fromName],
                'To'       => [['Email' => $toEmail, 'Name' => $toName]],
                'Subject'  => $subject,
                'HTMLPart' => $htmlContent,
                'TextPart' => $textContent ?? strip_tags($htmlContent),
            ]],
        ];

        $ch = curl_init(self::MJ_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_USERPWD        => $this->apiKey . ':' . $this->secretKey,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            return false;
        }

        $body = json_decode((string) $response, true);
        return $httpCode === 200
            && isset($body['Messages'][0]['Status'])
            && $body['Messages'][0]['Status'] === 'success';
    }

    private function sendViaSmtp(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlContent,
        ?string $textContent
    ): bool {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = $this->smtpHost;
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->smtpUser;
            $mail->Password   = $this->smtpPass;
            $mail->SMTPSecure = $this->smtpPort === 587 ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = $this->smtpPort;
            $mail->CharSet    = 'UTF-8';
            $mail->Timeout    = 10;

            $mail->setFrom($this->fromEmail, $this->fromName);
            $mail->addAddress($toEmail, $toName);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlContent;
            $mail->AltBody = $textContent ?? strip_tags($htmlContent);

            $mail->send();
            return true;
        } catch (\Exception) {
            return false;
        }
    }

    // ── HTML builders ──────────────────────────────────────────────────────────

    private function buildPaymentConfirmationHtml(array $invoice, array $client, string $amount): string
    {
        $name        = htmlspecialchars($client['nombre'] ?? $client['name'] ?? 'Cliente', ENT_QUOTES, 'UTF-8');
        $number      = htmlspecialchars($invoice['invoice_number'] ?? '—', ENT_QUOTES, 'UTF-8');
        $description = htmlspecialchars($invoice['notes'] ?? $invoice['description'] ?? '—', ENT_QUOTES, 'UTF-8');

        return <<<HTML
        <!DOCTYPE html>
        <html lang="es">
        <head><meta charset="UTF-8"></head>
        <body style="font-family: Arial, sans-serif; color: #1a1a1a; max-width: 600px; margin: 0 auto; padding: 24px;">
          <div style="border-bottom: 3px solid #4F8EFF; padding-bottom: 16px; margin-bottom: 24px;">
            <h1 style="color: #4F8EFF; margin: 0; font-size: 22px;">ProWay Lab</h1>
          </div>
          <h2 style="font-size: 18px;">&#x2705; Pago recibido</h2>
          <p>Hola <strong>{$name}</strong>,</p>
          <p>Hemos recibido tu pago. Aqui estan los detalles:</p>
          <table style="width: 100%; border-collapse: collapse; margin: 16px 0;">
            <tr style="background: #f5f7ff;">
              <td style="padding: 8px 12px; border: 1px solid #e0e4f0;"><strong>Factura</strong></td>
              <td style="padding: 8px 12px; border: 1px solid #e0e4f0;">#{$number}</td>
            </tr>
            <tr>
              <td style="padding: 8px 12px; border: 1px solid #e0e4f0;"><strong>Concepto</strong></td>
              <td style="padding: 8px 12px; border: 1px solid #e0e4f0;">{$description}</td>
            </tr>
            <tr style="background: #f5f7ff;">
              <td style="padding: 8px 12px; border: 1px solid #e0e4f0;"><strong>Monto</strong></td>
              <td style="padding: 8px 12px; border: 1px solid #e0e4f0;">\${$amount} COP</td>
            </tr>
            <tr>
              <td style="padding: 8px 12px; border: 1px solid #e0e4f0;"><strong>Estado</strong></td>
              <td style="padding: 8px 12px; border: 1px solid #e0e4f0; color: #16a34a;"><strong>Pagado</strong></td>
            </tr>
          </table>
          <p>Puedes ver el detalle completo de tu proyecto en el <a href="https://prowaylab.com/portal" style="color: #4F8EFF;">portal de clientes</a>.</p>
          <p style="margin-top: 32px; color: #666; font-size: 13px;">ProWay Lab — Soluciones digitales para marcas fitness</p>
        </body>
        </html>
        HTML;
    }

    private function buildWelcomeHtml(array $client): string
    {
        $name   = htmlspecialchars($client['nombre'] ?? $client['name'] ?? 'Cliente', ENT_QUOTES, 'UTF-8');
        $portal = 'https://prowaylab.com/portal';

        return <<<HTML
        <!DOCTYPE html>
        <html lang="es">
        <head><meta charset="UTF-8"></head>
        <body style="font-family: Arial, sans-serif; color: #1a1a1a; max-width: 600px; margin: 0 auto; padding: 24px;">
          <div style="border-bottom: 3px solid #4F8EFF; padding-bottom: 16px; margin-bottom: 24px;">
            <h1 style="color: #4F8EFF; margin: 0; font-size: 22px;">ProWay Lab</h1>
          </div>
          <h2 style="font-size: 18px;">Bienvenido a ProWay Lab!</h2>
          <p>Hola <strong>{$name}</strong>,</p>
          <p>Tu cuenta ha sido creada exitosamente. Desde el portal puedes:</p>
          <ul style="line-height: 2;">
            <li>Ver el estado de tus proyectos en tiempo real</li>
            <li>Descargar y pagar tus facturas</li>
            <li>Comunicarte con tu equipo asignado</li>
          </ul>
          <div style="text-align: center; margin: 32px 0;">
            <a href="{$portal}" style="background: #4F8EFF; color: white; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-weight: bold;">
              Acceder al portal
            </a>
          </div>
          <p style="margin-top: 32px; color: #666; font-size: 13px;">ProWay Lab — Soluciones digitales para marcas fitness</p>
        </body>
        </html>
        HTML;
    }
}
