<?php

declare(strict_types=1);

namespace ProWay\Infrastructure\Email;

/**
 * Mailjet transactional email via REST API v3.1.
 *
 * Uses cURL + Basic Auth (API_KEY:SECRET_KEY) — no SMTP required.
 * Docs: https://dev.mailjet.com/email/guides/send-api-v31/
 */
class MailjetService
{
    private const API_URL = 'https://api.mailjet.com/v3.1/send';

    private string $apiKey;
    private string $secretKey;
    private string $fromEmail;
    private string $fromName;

    public function __construct()
    {
        $this->apiKey    = $_ENV['MAILJET_API_KEY']    ?? '';
        $this->secretKey = $_ENV['MAILJET_SECRET_KEY'] ?? '';
        $this->fromEmail = $_ENV['MAIL_FROM']          ?? 'info@prowaylab.com';
        $this->fromName  = $_ENV['MAIL_FROM_NAME']     ?? 'ProWay Lab';
    }

    /**
     * Send a transactional email via Mailjet API v3.1.
     */
    public function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlContent,
        ?string $textContent = null
    ): bool {
        if ($this->apiKey === '' || $this->secretKey === '') {
            return false;
        }

        $payload = [
            'Messages' => [
                [
                    'From'     => ['Email' => $this->fromEmail, 'Name' => $this->fromName],
                    'To'       => [['Email' => $toEmail, 'Name' => $toName]],
                    'Subject'  => $subject,
                    'HTMLPart' => $htmlContent,
                    'TextPart' => $textContent ?? strip_tags($htmlContent),
                ],
            ],
        ];

        $ch = curl_init(self::API_URL);
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

    /**
     * Send a payment confirmation email after a successful Wompi transaction.
     */
    public function sendPaymentConfirmation(array $invoice, array $client): bool
    {
        $subject = "Pago confirmado — Factura #{$invoice['invoice_number']}";
        $amount  = number_format((float) $invoice['amount'], 0, ',', '.');

        $html = $this->buildPaymentConfirmationHtml($invoice, $client, $amount);

        return $this->send($client['email'], $client['name'], $subject, $html);
    }

    /**
     * Send a welcome email when a new client is registered.
     */
    public function sendWelcome(array $client): bool
    {
        $subject = 'Bienvenido a ProWay Lab';
        $html    = $this->buildWelcomeHtml($client);

        return $this->send($client['email'], $client['name'], $subject, $html);
    }

    private function buildPaymentConfirmationHtml(array $invoice, array $client, string $amount): string
    {
        $name        = htmlspecialchars($client['name'], ENT_QUOTES, 'UTF-8');
        $number      = htmlspecialchars($invoice['invoice_number'], ENT_QUOTES, 'UTF-8');
        $description = htmlspecialchars($invoice['description'], ENT_QUOTES, 'UTF-8');

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
        $name   = htmlspecialchars($client['name'], ENT_QUOTES, 'UTF-8');
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
