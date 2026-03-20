<?php
declare(strict_types=1);

namespace ProWay\Api\V1\Controller;

use PDO;
use ProWay\Api\V1\Middleware\AuthMiddleware;
use ProWay\Domain\Client\ClientService;
use ProWay\Infrastructure\Email\EmailTemplateBuilder;
use ProWay\Infrastructure\Email\MailjetService;
use ProWay\Infrastructure\Http\Request;
use ProWay\Infrastructure\Http\Response;

class OnboardingController
{
    public function __construct(
        private readonly PDO             $db,
        private readonly ClientService   $clients,
        private readonly AuthMiddleware  $middleware,
        private readonly MailjetService  $mailer,
    ) {}

    /**
     * GET /api/v1/clients/me/profile
     */
    public function getProfile(Request $request, array $vars): never
    {
        $user   = $this->middleware->requireAuth($request);
        $client = $this->clients->getByCode($user->code);

        if ($client === null) {
            Response::error('NOT_FOUND', 'Client not found', 404);
        }

        $stmt = $this->db->prepare(
            'SELECT brand_name, brand_colors, logo_url, social_accounts,
                    content_prefs, goals, onboarding_done
             FROM client_profiles WHERE client_id = ?'
        );
        $stmt->execute([$client['id']]);
        $profile = $stmt->fetch();

        if ($profile === false) {
            // Return empty profile — onboarding not started
            Response::success(['profile' => [
                'brand_name'      => null,
                'brand_colors'    => [],
                'logo_url'        => null,
                'social_accounts' => [],
                'content_prefs'   => [],
                'goals'           => null,
                'onboarding_done' => 0,
            ]]);
        }

        // Decode JSON columns
        $profile['brand_colors']    = json_decode($profile['brand_colors'] ?? '[]', true) ?: [];
        $profile['social_accounts'] = json_decode($profile['social_accounts'] ?? '{}', true) ?: [];
        $profile['content_prefs']   = json_decode($profile['content_prefs'] ?? '{}', true) ?: [];
        $profile['onboarding_done'] = (int) $profile['onboarding_done'];

        Response::success(['profile' => $profile]);
    }

    /**
     * PUT /api/v1/clients/me/profile
     * Upsert onboarding profile data.
     */
    public function updateProfile(Request $request, array $vars): never
    {
        $user   = $this->middleware->requireAuth($request);
        $client = $this->clients->getByCode($user->code);

        if ($client === null) {
            Response::error('NOT_FOUND', 'Client not found', 404);
        }

        $body = $request->getBody();

        // Build update fields
        $fields = [];
        $params = [];

        if (array_key_exists('brand_name', $body)) {
            $fields[] = 'brand_name = ?';
            $params[] = mb_substr(trim((string) ($body['brand_name'] ?? '')), 0, 100) ?: null;
        }

        if (array_key_exists('brand_colors', $body)) {
            $fields[] = 'brand_colors = ?';
            $params[] = json_encode($body['brand_colors'] ?? [], JSON_UNESCAPED_UNICODE);
        }

        if (array_key_exists('logo_url', $body)) {
            $fields[] = 'logo_url = ?';
            $params[] = mb_substr(trim((string) ($body['logo_url'] ?? '')), 0, 500) ?: null;
        }

        if (array_key_exists('social_accounts', $body)) {
            $fields[] = 'social_accounts = ?';
            $params[] = json_encode($body['social_accounts'] ?? [], JSON_UNESCAPED_UNICODE);
        }

        if (array_key_exists('content_prefs', $body)) {
            $fields[] = 'content_prefs = ?';
            $params[] = json_encode($body['content_prefs'] ?? [], JSON_UNESCAPED_UNICODE);
        }

        if (array_key_exists('goals', $body)) {
            $fields[] = 'goals = ?';
            $params[] = trim((string) ($body['goals'] ?? '')) ?: null;
        }

        if (empty($fields)) {
            Response::error('VALIDATION', 'No valid fields provided', 422);
        }

        $params[] = $client['id'];
        $sql = 'UPDATE client_profiles SET ' . implode(', ', $fields) . ' WHERE client_id = ?';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        Response::success(['updated' => true]);
    }

    /**
     * POST /api/v1/clients/me/onboarding-complete
     * Mark onboarding as done and trigger welcome email.
     */
    public function completeOnboarding(Request $request, array $vars): never
    {
        $user   = $this->middleware->requireAuth($request);
        $client = $this->clients->getByCode($user->code);

        if ($client === null) {
            Response::error('NOT_FOUND', 'Client not found', 404);
        }

        // Mark onboarding as done
        $stmt = $this->db->prepare(
            'UPDATE client_profiles SET onboarding_done = 1 WHERE client_id = ?'
        );
        $stmt->execute([$client['id']]);

        // Send onboarding-complete welcome email
        $clientName  = $client['nombre'] ?? $client['name'] ?? 'Cliente';
        $clientEmail = $client['email'] ?? '';

        if ($clientEmail !== '') {
            $html = EmailTemplateBuilder::make()
                ->subject('Bienvenido a ProWay Lab')
                ->greeting($clientName)
                ->line('Has completado tu perfil de marca exitosamente. Nuestro equipo ya tiene toda la informaci&oacute;n para comenzar a crear contenido incre&iacute;ble para tu marca.')
                ->line('Desde el portal puedes ver el estado de tus proyectos, descargar entregables, pagar facturas y comunicarte con tu equipo asignado.')
                ->action('Ir al portal', 'https://prowaylab.com/portal')
                ->build();

            $this->mailer->send($clientEmail, $clientName, 'Bienvenido a ProWay Lab', $html);
        }

        Response::success(['onboarding_done' => true]);
    }
}
