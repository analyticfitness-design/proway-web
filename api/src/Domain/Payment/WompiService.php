<?php

declare(strict_types=1);

namespace ProWay\Domain\Payment;

/**
 * Wompi payment gateway integration.
 *
 * Wompi uses an embedded JS widget (no server-to-server checkout creation).
 * This service generates the integrity checksum required by the widget and
 * verifies incoming webhook signatures.
 *
 * Docs: https://docs.wompi.co/docs/colombia/referencia/
 */
class WompiService
{
    private string $publicKey;
    private string $privateKey;
    private string $eventsKey;
    private string $integrityKey;

    public function __construct()
    {
        $this->publicKey    = $_ENV['WOMPI_PUBLIC_KEY']    ?? '';
        $this->privateKey   = $_ENV['WOMPI_PRIVATE_KEY']   ?? '';
        $this->eventsKey    = $_ENV['WOMPI_EVENTS_KEY']    ?? '';
        $this->integrityKey = $_ENV['WOMPI_INTEGRITY_KEY'] ?? '';
    }

    /**
     * Generate the integrity checksum for the Wompi widget.
     *
     * Algorithm: SHA256(reference + amount_in_cents + currency + integrity_key)
     */
    public function generateChecksum(string $reference, int $amountInCents, string $currency = 'COP'): string
    {
        $raw = $reference . $amountInCents . $currency . $this->integrityKey;
        return hash('sha256', $raw);
    }

    /**
     * Build the checkout payload to pass to the Wompi JS widget.
     *
     * @param  string $reference  Unique payment reference (e.g. invoice ID)
     * @param  int    $amountCOP  Amount in Colombian Pesos (widget expects centavos)
     * @param  string $email      Payer email (pre-fills the widget form)
     * @param  string $currency
     * @return array
     */
    public function buildCheckoutData(
        string $reference,
        int $amountCOP,
        string $email = '',
        string $currency = 'COP'
    ): array {
        $amountInCents = $amountCOP * 100;

        return [
            'public_key'      => $this->publicKey,
            'reference'       => $reference,
            'amount_in_cents' => $amountInCents,
            'currency'        => $currency,
            'customer_email'  => $email,
            'signature'       => [
                'integrity' => $this->generateChecksum($reference, $amountInCents, $currency),
            ],
        ];
    }

    /**
     * Verify a Wompi webhook event signature.
     *
     * Wompi sends: X-Event-Checksum = SHA256(event.transaction.id + event.timestamp + events_key)
     *
     * @param  array  $event     Decoded JSON body of the webhook
     * @param  string $checksum  Value of the X-Event-Checksum header
     */
    public function verifyWebhookSignature(array $event, string $checksum): bool
    {
        $transactionId = $event['data']['transaction']['id'] ?? '';
        $timestamp     = $event['timestamp'] ?? '';
        $raw           = $transactionId . $timestamp . $this->eventsKey;

        return hash_equals(hash('sha256', $raw), $checksum);
    }

    /**
     * Extract a normalised payment result from a Wompi webhook payload.
     *
     * Returns an array with keys: reference, status, amount_in_cents, currency, id
     */
    public function parseWebhookEvent(array $event): array
    {
        $tx = $event['data']['transaction'] ?? [];

        return [
            'id'              => $tx['id']              ?? '',
            'reference'       => $tx['reference']       ?? '',
            'status'          => $tx['status']          ?? '',
            'amount_in_cents' => $tx['amount_in_cents'] ?? 0,
            'currency'        => $tx['currency']        ?? 'COP',
            'payment_method'  => $tx['payment_method_type'] ?? '',
        ];
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }
}
