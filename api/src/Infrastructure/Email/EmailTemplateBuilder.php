<?php

declare(strict_types=1);

namespace ProWay\Infrastructure\Email;

/**
 * Branded HTML email template builder for ProWay Lab.
 *
 * Generates responsive, table-based HTML emails with inline CSS
 * compatible with Gmail, Outlook, and Apple Mail.
 *
 * Usage:
 *   $html = EmailTemplateBuilder::make()
 *       ->subject('Tu proyecto está listo')
 *       ->greeting('Carlos')
 *       ->line('Hemos terminado la edición de tu video.')
 *       ->action('Ver proyecto', 'https://prowaylab.com/portal')
 *       ->build();
 *
 * Presets:
 *   $html = EmailTemplateBuilder::welcome('Carlos', 'https://…/login', 'tmp123');
 */
class EmailTemplateBuilder
{
    // ── Design tokens ───────────────────────────────────────────────────────
    private const BG        = '#0C0C0F';
    private const PANEL     = '#191920';
    private const BORDER    = '#252528';
    private const ACCENT    = '#00D9FF';
    private const ACCENT2   = '#00FF87';
    private const TEXT       = '#FFFFFF';
    private const TEXT_MUTED = '#A1A1AA';
    private const FONT_BODY = "'Inter', Arial, Helvetica, sans-serif";
    private const FONT_HEAD = "'Montserrat', 'Inter', Arial, Helvetica, sans-serif";

    private string  $subjectText   = '';
    private string  $greetingText  = '';
    /** @var list<array{type: string, content: string, url?: string}> */
    private array   $blocks        = [];
    private string  $footerText    = '';

    // ── Factory ─────────────────────────────────────────────────────────────

    public static function make(): self
    {
        return new self();
    }

    // ── Chainable setters ───────────────────────────────────────────────────

    public function subject(string $subject): self
    {
        $this->subjectText = $subject;
        return $this;
    }

    public function greeting(string $name): self
    {
        $this->greetingText = 'Hola ' . self::esc($name) . ',';
        return $this;
    }

    public function line(string $text): self
    {
        $this->blocks[] = ['type' => 'line', 'content' => $text];
        return $this;
    }

    public function action(string $label, string $url): self
    {
        $this->blocks[] = ['type' => 'action', 'content' => $label, 'url' => $url];
        return $this;
    }

    public function footer(string $text): self
    {
        $this->footerText = $text;
        return $this;
    }

    // ── Build ───────────────────────────────────────────────────────────────

    public function build(): string
    {
        $footerLine = $this->footerText !== ''
            ? self::esc($this->footerText)
            : '&copy; 2026 ProWay Lab &mdash; Producci&oacute;n de Video para Fitness';

        $innerRows  = $this->renderGreeting() . $this->renderBlocks();
        $titleEsc   = self::esc($this->subjectText);

        $bg        = self::BG;
        $panel     = self::PANEL;
        $border    = self::BORDER;
        $accent    = self::ACCENT;
        $accent2   = self::ACCENT2;
        $text      = self::TEXT;
        $textMuted = self::TEXT_MUTED;
        $fontBody  = self::FONT_BODY;
        $fontHead  = self::FONT_HEAD;

        return <<<HTML
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <title>{$titleEsc}</title>
  <!--[if mso]>
  <style type="text/css">
    body, table, td { font-family: Arial, Helvetica, sans-serif !important; }
  </style>
  <![endif]-->
</head>
<body style="margin:0;padding:0;background-color:{$bg};-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">

  <!-- Wrapper table -->
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:{$bg};">
    <tr>
      <td align="center" style="padding:24px 16px;">

        <!-- Container 600px -->
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;border-collapse:collapse;">

          <!-- Header -->
          <tr>
            <td align="center" style="padding:32px 0 24px 0;">
              <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td style="font-family:{$fontHead};font-size:28px;font-weight:800;letter-spacing:3px;color:{$text};">
                    PROWAY<span style="color:{$accent};">.</span><span style="color:{$accent2};">LAB</span>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Body card -->
          <tr>
            <td style="background-color:{$panel};border:1px solid {$border};border-radius:12px;padding:40px 36px;">
              <!--[if mso]>
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding:40px 36px;">
              <![endif]-->

              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
{$innerRows}
              </table>

              <!--[if mso]>
              </td></tr></table>
              <![endif]-->
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td align="center" style="padding:28px 16px 8px 16px;">
              <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td style="font-family:{$fontBody};font-size:12px;line-height:18px;color:{$textMuted};text-align:center;">
                    {$footerLine}
                  </td>
                </tr>
              </table>
            </td>
          </tr>

        </table>
        <!-- /Container -->

      </td>
    </tr>
  </table>
  <!-- /Wrapper -->

</body>
</html>
HTML;
    }

    // ── Preset templates ────────────────────────────────────────────────────

    /**
     * Welcome email sent when a new client account is created.
     */
    public static function welcome(string $name, string $loginUrl, string $tempPassword): string
    {
        return self::make()
            ->subject('Bienvenido a ProWay Lab')
            ->greeting($name)
            ->line('Tu cuenta ha sido creada exitosamente. Desde el portal de clientes puedes ver el estado de tus proyectos en tiempo real, descargar y pagar facturas, y comunicarte con tu equipo asignado.')
            ->line('Tu contrase&ntilde;a temporal es: <strong>' . self::esc($tempPassword) . '</strong>')
            ->line('Te recomendamos cambiarla despu&eacute;s de tu primer inicio de sesi&oacute;n.')
            ->action('Acceder al portal', $loginUrl)
            ->build();
    }

    /**
     * Notification when a project changes status.
     */
    public static function projectUpdate(string $name, string $projectName, string $newStatus, string $portalUrl): string
    {
        return self::make()
            ->subject('Actualización de proyecto — ' . $projectName)
            ->greeting($name)
            ->line('Tu proyecto <strong>' . self::esc($projectName) . '</strong> ha sido actualizado.')
            ->line('Nuevo estado: <strong style="color:' . self::ACCENT2 . ';">' . self::esc($newStatus) . '</strong>')
            ->line('Ingresa al portal para ver los detalles completos y los entregables disponibles.')
            ->action('Ver proyecto', $portalUrl)
            ->build();
    }

    /**
     * Notification when a new invoice is created.
     */
    public static function invoiceCreated(string $name, string $invoiceNumber, string $amount, string $payUrl): string
    {
        return self::make()
            ->subject('Nueva factura — #' . $invoiceNumber)
            ->greeting($name)
            ->line('Se ha generado una nueva factura para tu cuenta:')
            ->line('Factura: <strong>#' . self::esc($invoiceNumber) . '</strong><br/>Monto: <strong>$' . self::esc($amount) . ' COP</strong>')
            ->line('Puedes pagar de forma segura desde el portal de clientes.')
            ->action('Pagar factura', $payUrl)
            ->build();
    }

    /**
     * Password-reset email with a time-limited link.
     */
    public static function passwordReset(string $name, string $resetUrl): string
    {
        return self::make()
            ->subject('Restablecer contraseña — ProWay Lab')
            ->greeting($name)
            ->line('Recibimos una solicitud para restablecer la contrase&ntilde;a de tu cuenta en ProWay Lab.')
            ->line('Haz clic en el bot&oacute;n de abajo para crear una nueva contrase&ntilde;a. Este enlace expira en 60 minutos.')
            ->action('Restablecer contraseña', $resetUrl)
            ->line('Si no solicitaste este cambio, puedes ignorar este correo de forma segura. Tu contrase&ntilde;a actual no se ver&aacute; afectada.')
            ->build();
    }

    // ── Private rendering helpers ───────────────────────────────────────────

    private function renderGreeting(): string
    {
        if ($this->greetingText === '') {
            return '';
        }

        $fontBody = self::FONT_BODY;
        $text     = self::TEXT;

        return <<<HTML
                <tr>
                  <td style="font-family:{$fontBody};font-size:18px;line-height:26px;color:{$text};padding-bottom:20px;font-weight:600;">
                    {$this->greetingText}
                  </td>
                </tr>

HTML;
    }

    private function renderBlocks(): string
    {
        $html = '';

        foreach ($this->blocks as $block) {
            if ($block['type'] === 'line') {
                $html .= $this->renderLine($block['content']);
            } elseif ($block['type'] === 'action') {
                $html .= $this->renderAction($block['content'], $block['url'] ?? '#');
            }
        }

        return $html;
    }

    private function renderLine(string $text): string
    {
        $fontBody  = self::FONT_BODY;
        $textMuted = self::TEXT_MUTED;

        return <<<HTML
                <tr>
                  <td style="font-family:{$fontBody};font-size:15px;line-height:24px;color:{$textMuted};padding-bottom:16px;">
                    {$text}
                  </td>
                </tr>

HTML;
    }

    private function renderAction(string $label, string $url): string
    {
        $safeUrl   = self::esc($url);
        $safeLabel = self::esc($label);
        $accent    = self::ACCENT;
        $bg        = self::BG;
        $fontBody  = self::FONT_BODY;

        return <<<HTML
                <tr>
                  <td align="center" style="padding:12px 0 28px 0;">
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                      <tr>
                        <td align="center" style="border-radius:8px;background-color:{$accent};">
                          <!--[if mso]>
                          <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" href="{$safeUrl}" style="height:48px;v-text-anchor:middle;width:220px;" arcsize="17%" fillcolor="{$accent}">
                            <w:anchorlock/>
                            <center style="font-family:Arial,sans-serif;font-size:15px;font-weight:bold;color:{$bg};">{$safeLabel}</center>
                          </v:roundrect>
                          <![endif]-->
                          <!--[if !mso]><!-->
                          <a href="{$safeUrl}" target="_blank" style="display:inline-block;padding:14px 32px;font-family:{$fontBody};font-size:15px;font-weight:700;color:{$bg};text-decoration:none;border-radius:8px;background-color:{$accent};min-width:160px;text-align:center;">
                            {$safeLabel}
                          </a>
                          <!--<![endif]-->
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>

HTML;
    }

    private static function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
