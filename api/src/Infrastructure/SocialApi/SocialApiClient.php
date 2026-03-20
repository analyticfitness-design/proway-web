<?php
declare(strict_types=1);

namespace ProWay\Infrastructure\SocialApi;

/**
 * Social media API adapter.
 *
 * Currently returns MOCK data for development & demo purposes.
 * When ready to integrate a real provider, replace the mock methods with
 * actual HTTP calls to the chosen API.
 *
 * TODO: Integrate with SociaVault / ScrapeCreators API.
 *       Example real implementation for fetchProfile():
 *
 *       $url = "https://api.sociavault.com/v1/{$platform}/profile/{$username}";
 *       $ch = curl_init($url);
 *       curl_setopt_array($ch, [
 *           CURLOPT_RETURNTRANSFER => true,
 *           CURLOPT_HTTPHEADER     => [
 *               'Authorization: Bearer ' . getenv('SOCIAVAULT_API_KEY'),
 *               'Accept: application/json',
 *           ],
 *           CURLOPT_TIMEOUT => 15,
 *       ]);
 *       $response = curl_exec($ch);
 *       $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
 *       curl_close($ch);
 *       if ($httpCode !== 200) return null;
 *       return json_decode($response, true);
 */
class SocialApiClient
{
    /**
     * Fetch profile data for a given platform + username.
     *
     * @return array|null Profile data or null on failure
     */
    public function fetchProfile(string $platform, string $username): ?array
    {
        // TODO: Replace with real API call (see class docblock)
        return $this->mockProfile($platform, $username);
    }

    /**
     * Fetch recent posts for a given platform + username.
     *
     * @return array[] Array of post data
     */
    public function fetchRecentPosts(string $platform, string $username, int $limit = 12): array
    {
        // TODO: Replace with real API call (see class docblock)
        return $this->mockRecentPosts($platform, $username, $limit);
    }

    // ── Mock data generators ────────────────────────────────────────────────────

    private function mockProfile(string $platform, string $username): array
    {
        // Seed based on username for consistent mock data
        $seed = crc32($username);
        mt_srand($seed);

        $baseFollowers = $platform === 'instagram'
            ? mt_rand(2000, 85000)
            : mt_rand(5000, 200000);

        return [
            'username'        => $username,
            'display_name'    => ucfirst(str_replace(['.', '_'], ' ', $username)),
            'profile_pic_url' => "https://ui-avatars.com/api/?name={$username}&background=0D8ABC&color=fff&size=200",
            'followers'       => $baseFollowers,
            'following'       => mt_rand(200, 1500),
            'posts_count'     => mt_rand(50, 600),
            'bio'             => $platform === 'instagram'
                ? "Productora audiovisual | Creamos contenido que conecta | Barranquilla, CO"
                : "Contenido que engancha | Reels & TikToks | Contacto: DM",
            'reach'           => (int) ($baseFollowers * mt_rand(15, 45) / 100),
            'engagement_rate' => round(mt_rand(150, 650) / 100, 2),
        ];
    }

    private function mockRecentPosts(string $platform, string $username, int $limit): array
    {
        $seed = crc32($username . '_posts');
        mt_srand($seed);

        $posts = [];
        $postTypes = $platform === 'instagram'
            ? ['reel', 'post', 'carousel', 'reel', 'reel', 'post']
            : ['video', 'video', 'video', 'video'];

        $captions = [
            'Nuevo reel para @%s — contenido que conecta con tu audiencia',
            'BTS de nuestra sesion de grabacion | #contenido #reels',
            'El poder del storytelling en redes sociales',
            'Resultados hablan por si solos | Estrategia + Creatividad',
            'Detras de camaras de nuestro ultimo proyecto audiovisual',
            'Tips para mejorar tu engagement en %s',
            'Nuevo contenido listo | Produccion ProWay Lab',
            'Transformamos ideas en contenido viral',
            'El secreto de los reels que funcionan',
            'Antes vs Despues de trabajar con ProWay Lab',
            'Contenido organico vs contenido profesional',
            'Tu marca merece contenido premium',
        ];

        for ($i = 0; $i < $limit; $i++) {
            $daysAgo  = $i * mt_rand(2, 5);
            $postType = $postTypes[$i % count($postTypes)];
            $views    = mt_rand(800, 50000);
            $likes    = (int) ($views * mt_rand(3, 12) / 100);
            $comments = (int) ($likes * mt_rand(2, 15) / 100);

            $caption = sprintf($captions[$i % count($captions)], $username);

            $posts[] = [
                'external_id'   => $platform . '_' . md5($username . $i),
                'post_type'     => $postType,
                'caption'       => $caption,
                'thumbnail_url' => "https://picsum.photos/seed/{$username}{$i}/400/400",
                'permalink'     => $platform === 'instagram'
                    ? "https://www.instagram.com/p/" . substr(md5($username . $i), 0, 11) . "/"
                    : "https://www.tiktok.com/@{$username}/video/" . mt_rand(7000000000, 7999999999),
                'posted_at'     => date('Y-m-d H:i:s', strtotime("-{$daysAgo} days")),
                'likes'         => $likes,
                'comments'      => $comments,
                'shares'        => (int) ($likes * mt_rand(1, 8) / 100),
                'views'         => $views,
                'reach'         => (int) ($views * mt_rand(60, 95) / 100),
                'engagement_rate' => round(($likes + $comments) / max($views, 1) * 100, 2),
            ];
        }

        return $posts;
    }
}
