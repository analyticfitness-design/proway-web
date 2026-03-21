<?php
declare(strict_types=1);

namespace ProWay\Infrastructure\SocialApi;

/**
 * Social media API adapter with multi-provider support and mock fallback.
 *
 * Provider selection via SOCIAL_API_PROVIDER env var:
 *   - 'mock'       → always return mock data (default, safe for dev/demo)
 *   - 'scrape'     → attempt public endpoint scraping, fall back to mock
 *   - 'rapidapi'   → use RapidAPI social endpoints (requires SOCIAL_API_KEY)
 *
 * All real API results are cached for 1 hour per username to respect rate limits.
 * On any failure the client degrades gracefully to mock data — the dashboard never breaks.
 */
class SocialApiClient
{
    /** Cache TTL in seconds (1 hour). */
    private const CACHE_TTL = 3600;

    /** HTTP request timeout in seconds. */
    private const HTTP_TIMEOUT = 15;

    /** User-Agent sent with public scraping requests. */
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

    private string $provider;
    private string $apiKey;
    private string $cacheDir;

    public function __construct()
    {
        $this->provider = defined('SOCIAL_API_PROVIDER') ? SOCIAL_API_PROVIDER : 'mock';
        $this->apiKey   = defined('SOCIAL_API_KEY') ? SOCIAL_API_KEY : '';
        $this->cacheDir = sys_get_temp_dir() . '/proway_social_cache';

        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
    }

    // ── Public API ──────────────────────────────────────────────────────────────

    /**
     * Fetch profile data for a given platform + username.
     *
     * @return array|null Profile data or null on failure
     */
    public function fetchProfile(string $platform, string $username): ?array
    {
        // Always mock when provider is explicitly set to 'mock'
        if ($this->provider === 'mock') {
            return $this->mockProfile($platform, $username);
        }

        // Check cache first
        $cacheKey = "profile_{$platform}_{$username}";
        $cached   = $this->cacheGet($cacheKey);
        if ($cached !== null) {
            $this->log('INFO', "Cache HIT for profile {$platform}/{$username}");
            return $cached;
        }

        // Try real API
        $data = null;
        try {
            if ($this->provider === 'rapidapi' && $this->apiKey !== '') {
                $data = $this->fetchProfileRapidApi($platform, $username);
            } elseif ($this->provider === 'scrape') {
                $data = $this->fetchProfileScrape($platform, $username);
            }
        } catch (\Throwable $e) {
            $this->log('ERROR', "fetchProfile {$platform}/{$username} exception: " . $e->getMessage());
        }

        if ($data !== null) {
            $this->cacheSet($cacheKey, $data);
            $this->log('INFO', "Fetched LIVE profile for {$platform}/{$username}");
            return $data;
        }

        // Fallback to mock — never break the dashboard
        $this->log('WARN', "Falling back to MOCK profile for {$platform}/{$username}");
        return $this->mockProfile($platform, $username);
    }

    /**
     * Fetch recent posts for a given platform + username.
     *
     * @return array[] Array of post data
     */
    public function fetchRecentPosts(string $platform, string $username, int $limit = 12): array
    {
        if ($this->provider === 'mock') {
            return $this->mockRecentPosts($platform, $username, $limit);
        }

        $cacheKey = "posts_{$platform}_{$username}_{$limit}";
        $cached   = $this->cacheGet($cacheKey);
        if ($cached !== null) {
            $this->log('INFO', "Cache HIT for posts {$platform}/{$username}");
            return $cached;
        }

        $data = null;
        try {
            if ($this->provider === 'rapidapi' && $this->apiKey !== '') {
                $data = $this->fetchPostsRapidApi($platform, $username, $limit);
            } elseif ($this->provider === 'scrape') {
                $data = $this->fetchPostsScrape($platform, $username, $limit);
            }
        } catch (\Throwable $e) {
            $this->log('ERROR', "fetchRecentPosts {$platform}/{$username} exception: " . $e->getMessage());
        }

        if ($data !== null && count($data) > 0) {
            $this->cacheSet($cacheKey, $data);
            $this->log('INFO', "Fetched LIVE posts for {$platform}/{$username}");
            return $data;
        }

        $this->log('WARN', "Falling back to MOCK posts for {$platform}/{$username}");
        return $this->mockRecentPosts($platform, $username, $limit);
    }

    // ── RapidAPI provider ───────────────────────────────────────────────────────

    private function fetchProfileRapidApi(string $platform, string $username): ?array
    {
        if ($platform === 'instagram') {
            $url  = "https://instagram-scraper-api2.p.rapidapi.com/v1/info?username_or_id_or_url={$username}";
            $body = $this->httpGet($url, [
                'x-rapidapi-host: instagram-scraper-api2.p.rapidapi.com',
                'x-rapidapi-key: ' . $this->apiKey,
            ]);
            if ($body === null) return null;
            $json = json_decode($body, true);
            $d    = $json['data'] ?? null;
            if ($d === null) return null;

            return $this->normalizeProfile($platform, $username, [
                'display_name'    => $d['full_name'] ?? $username,
                'profile_pic_url' => $d['profile_pic_url_hd'] ?? $d['profile_pic_url'] ?? '',
                'followers'       => (int) ($d['follower_count'] ?? 0),
                'following'       => (int) ($d['following_count'] ?? 0),
                'posts_count'     => (int) ($d['media_count'] ?? 0),
                'bio'             => $d['biography'] ?? '',
            ]);
        }

        if ($platform === 'tiktok') {
            $url  = "https://tiktok-scraper7.p.rapidapi.com/user/info?unique_id={$username}";
            $body = $this->httpGet($url, [
                'x-rapidapi-host: tiktok-scraper7.p.rapidapi.com',
                'x-rapidapi-key: ' . $this->apiKey,
            ]);
            if ($body === null) return null;
            $json = json_decode($body, true);
            $d    = $json['data'] ?? $json['user'] ?? null;
            if ($d === null) return null;

            $stats = $d['stats'] ?? $d;
            return $this->normalizeProfile($platform, $username, [
                'display_name'    => $d['nickname'] ?? $d['user']['nickname'] ?? $username,
                'profile_pic_url' => $d['avatarLarger'] ?? $d['user']['avatarLarger'] ?? '',
                'followers'       => (int) ($stats['followerCount'] ?? $stats['fans'] ?? 0),
                'following'       => (int) ($stats['followingCount'] ?? $stats['following'] ?? 0),
                'posts_count'     => (int) ($stats['videoCount'] ?? $stats['video'] ?? 0),
                'bio'             => $d['signature'] ?? $d['user']['signature'] ?? '',
            ]);
        }

        return null;
    }

    private function fetchPostsRapidApi(string $platform, string $username, int $limit): ?array
    {
        if ($platform === 'instagram') {
            $url  = "https://instagram-scraper-api2.p.rapidapi.com/v1.2/posts?username_or_id_or_url={$username}";
            $body = $this->httpGet($url, [
                'x-rapidapi-host: instagram-scraper-api2.p.rapidapi.com',
                'x-rapidapi-key: ' . $this->apiKey,
            ]);
            if ($body === null) return null;
            $json  = json_decode($body, true);
            $items = $json['data']['items'] ?? $json['data'] ?? [];

            return $this->normalizePostList($platform, $username, array_slice($items, 0, $limit), 'rapidapi');
        }

        if ($platform === 'tiktok') {
            $url  = "https://tiktok-scraper7.p.rapidapi.com/user/posts?unique_id={$username}&count={$limit}";
            $body = $this->httpGet($url, [
                'x-rapidapi-host: tiktok-scraper7.p.rapidapi.com',
                'x-rapidapi-key: ' . $this->apiKey,
            ]);
            if ($body === null) return null;
            $json  = json_decode($body, true);
            $items = $json['data']['videos'] ?? $json['data'] ?? [];

            return $this->normalizePostList($platform, $username, array_slice($items, 0, $limit), 'rapidapi');
        }

        return null;
    }

    // ── Scrape provider (public endpoints, no API key) ──────────────────────────

    private function fetchProfileScrape(string $platform, string $username): ?array
    {
        if ($platform === 'instagram') {
            // Public web profile endpoint
            $url  = "https://www.instagram.com/api/v1/users/web_profile_info/?username={$username}";
            $body = $this->httpGet($url, [
                'User-Agent: ' . self::USER_AGENT,
                'x-ig-app-id: 936619743392459',
            ]);
            if ($body === null) return null;
            $json = json_decode($body, true);
            $d    = $json['data']['user'] ?? null;
            if ($d === null) return null;

            return $this->normalizeProfile($platform, $username, [
                'display_name'    => $d['full_name'] ?? $username,
                'profile_pic_url' => $d['profile_pic_url_hd'] ?? $d['profile_pic_url'] ?? '',
                'followers'       => (int) ($d['edge_followed_by']['count'] ?? 0),
                'following'       => (int) ($d['edge_follow']['count'] ?? 0),
                'posts_count'     => (int) ($d['edge_owner_to_timeline_media']['count'] ?? 0),
                'bio'             => $d['biography'] ?? '',
            ]);
        }

        if ($platform === 'tiktok') {
            // TikTok public API for user info
            $url  = "https://www.tiktok.com/@{$username}?_t=8";
            $body = $this->httpGet($url, [
                'User-Agent: ' . self::USER_AGENT,
            ]);
            if ($body === null) return null;

            // Try to extract JSON-LD or __UNIVERSAL_DATA_FOR_REHYDRATION__ from the HTML
            $data = $this->parseTikTokProfile($body, $username);
            if ($data !== null) {
                return $this->normalizeProfile($platform, $username, $data);
            }
        }

        return null;
    }

    private function fetchPostsScrape(string $platform, string $username, int $limit): ?array
    {
        if ($platform === 'instagram') {
            $url  = "https://www.instagram.com/api/v1/users/web_profile_info/?username={$username}";
            $body = $this->httpGet($url, [
                'User-Agent: ' . self::USER_AGENT,
                'x-ig-app-id: 936619743392459',
            ]);
            if ($body === null) return null;
            $json  = json_decode($body, true);
            $edges = $json['data']['user']['edge_owner_to_timeline_media']['edges'] ?? [];
            $items = array_map(fn($e) => $e['node'], $edges);

            return $this->normalizePostList($platform, $username, array_slice($items, 0, $limit), 'scrape');
        }

        // TikTok post scraping from HTML is unreliable — return null to trigger mock fallback
        return null;
    }

    // ── Data normalizers ────────────────────────────────────────────────────────

    /**
     * Ensure profile data matches the expected output format regardless of provider.
     */
    private function normalizeProfile(string $platform, string $username, array $raw): array
    {
        $followers = (int) ($raw['followers'] ?? 0);
        return [
            'username'        => $username,
            'display_name'    => $raw['display_name'] ?? ucfirst($username),
            'profile_pic_url' => $raw['profile_pic_url'] ?? "https://ui-avatars.com/api/?name={$username}&background=0D8ABC&color=fff&size=200",
            'followers'       => $followers,
            'following'       => (int) ($raw['following'] ?? 0),
            'posts_count'     => (int) ($raw['posts_count'] ?? 0),
            'bio'             => $raw['bio'] ?? '',
            'reach'           => (int) ($followers * (mt_rand(15, 45) / 100)),
            'engagement_rate' => round(mt_rand(150, 650) / 100, 2),
        ];
    }

    /**
     * Normalize a list of raw post items into the standard post format.
     */
    private function normalizePostList(string $platform, string $username, array $items, string $source): array
    {
        $posts = [];
        foreach ($items as $i => $item) {
            $post = $this->normalizePost($platform, $username, $item, $i, $source);
            if ($post !== null) {
                $posts[] = $post;
            }
        }
        return $posts;
    }

    private function normalizePost(string $platform, string $username, array $item, int $index, string $source): ?array
    {
        // --- Instagram (RapidAPI) ---
        if ($platform === 'instagram' && $source === 'rapidapi') {
            $likes    = (int) ($item['like_count'] ?? 0);
            $comments = (int) ($item['comment_count'] ?? 0);
            $views    = (int) ($item['play_count'] ?? $item['view_count'] ?? max($likes * 10, 1));
            $type     = isset($item['video_url']) ? 'reel' : (($item['carousel_media_count'] ?? 0) > 0 ? 'carousel' : 'post');
            $thumb    = $item['thumbnail_url'] ?? $item['image_versions2']['candidates'][0]['url'] ?? '';

            return [
                'external_id'     => 'ig_' . ($item['pk'] ?? $item['id'] ?? md5($username . $index)),
                'post_type'       => $type,
                'caption'         => $this->truncate($item['caption']['text'] ?? '', 200),
                'thumbnail_url'   => $thumb,
                'permalink'       => $item['link'] ?? "https://www.instagram.com/p/" . ($item['code'] ?? ''),
                'posted_at'       => isset($item['taken_at']) ? date('Y-m-d H:i:s', (int) $item['taken_at']) : date('Y-m-d H:i:s'),
                'likes'           => $likes,
                'comments'        => $comments,
                'shares'          => (int) ($item['reshare_count'] ?? 0),
                'views'           => $views,
                'reach'           => (int) ($views * mt_rand(60, 95) / 100),
                'engagement_rate' => round(($likes + $comments) / max($views, 1) * 100, 2),
            ];
        }

        // --- Instagram (scrape — graph edges) ---
        if ($platform === 'instagram' && $source === 'scrape') {
            $likes    = (int) ($item['edge_media_preview_like']['count'] ?? 0);
            $comments = (int) ($item['edge_media_to_comment']['count'] ?? 0);
            $views    = (int) ($item['video_view_count'] ?? max($likes * 10, 1));
            $isVideo  = ($item['is_video'] ?? false);
            $type     = $isVideo ? 'reel' : (($item['__typename'] ?? '') === 'GraphSidecar' ? 'carousel' : 'post');

            return [
                'external_id'     => 'ig_' . ($item['id'] ?? md5($username . $index)),
                'post_type'       => $type,
                'caption'         => $this->truncate($item['edge_media_to_caption']['edges'][0]['node']['text'] ?? '', 200),
                'thumbnail_url'   => $item['thumbnail_src'] ?? $item['display_url'] ?? '',
                'permalink'       => "https://www.instagram.com/p/" . ($item['shortcode'] ?? ''),
                'posted_at'       => isset($item['taken_at_timestamp']) ? date('Y-m-d H:i:s', (int) $item['taken_at_timestamp']) : date('Y-m-d H:i:s'),
                'likes'           => $likes,
                'comments'        => $comments,
                'shares'          => 0,
                'views'           => $views,
                'reach'           => (int) ($views * mt_rand(60, 95) / 100),
                'engagement_rate' => round(($likes + $comments) / max($views, 1) * 100, 2),
            ];
        }

        // --- TikTok (RapidAPI) ---
        if ($platform === 'tiktok' && $source === 'rapidapi') {
            $stats    = $item['stats'] ?? $item;
            $likes    = (int) ($stats['diggCount'] ?? $stats['likes'] ?? 0);
            $comments = (int) ($stats['commentCount'] ?? $stats['comments'] ?? 0);
            $views    = (int) ($stats['playCount'] ?? $stats['views'] ?? max($likes * 10, 1));
            $shares   = (int) ($stats['shareCount'] ?? $stats['shares'] ?? 0);

            return [
                'external_id'     => 'tt_' . ($item['id'] ?? md5($username . $index)),
                'post_type'       => 'video',
                'caption'         => $this->truncate($item['desc'] ?? $item['title'] ?? '', 200),
                'thumbnail_url'   => $item['cover'] ?? $item['video']['cover'] ?? '',
                'permalink'       => "https://www.tiktok.com/@{$username}/video/" . ($item['id'] ?? ''),
                'posted_at'       => isset($item['createTime']) ? date('Y-m-d H:i:s', (int) $item['createTime']) : date('Y-m-d H:i:s'),
                'likes'           => $likes,
                'comments'        => $comments,
                'shares'          => $shares,
                'views'           => $views,
                'reach'           => (int) ($views * mt_rand(60, 95) / 100),
                'engagement_rate' => round(($likes + $comments) / max($views, 1) * 100, 2),
            ];
        }

        return null;
    }

    // ── TikTok HTML parser ──────────────────────────────────────────────────────

    private function parseTikTokProfile(string $html, string $username): ?array
    {
        // Try __UNIVERSAL_DATA_FOR_REHYDRATION__
        if (preg_match('/<script[^>]*id="__UNIVERSAL_DATA_FOR_REHYDRATION__"[^>]*>(.+?)<\/script>/s', $html, $m)) {
            $json = json_decode($m[1], true);
            $user = $json['__DEFAULT_SCOPE__']['webapp.user-detail']['userInfo'] ?? null;
            if ($user !== null) {
                $u = $user['user'] ?? [];
                $s = $user['stats'] ?? [];
                return [
                    'display_name'    => $u['nickname'] ?? $username,
                    'profile_pic_url' => $u['avatarLarger'] ?? '',
                    'followers'       => (int) ($s['followerCount'] ?? 0),
                    'following'       => (int) ($s['followingCount'] ?? 0),
                    'posts_count'     => (int) ($s['videoCount'] ?? 0),
                    'bio'             => $u['signature'] ?? '',
                ];
            }
        }

        // Try SIGI_STATE
        if (preg_match('/<script[^>]*id="SIGI_STATE"[^>]*>(.+?)<\/script>/s', $html, $m)) {
            $json = json_decode($m[1], true);
            $users = $json['UserModule']['users'] ?? [];
            $first = reset($users);
            $stats = $json['UserModule']['stats'] ?? [];
            $firstStats = reset($stats);
            if ($first) {
                return [
                    'display_name'    => $first['nickname'] ?? $username,
                    'profile_pic_url' => $first['avatarLarger'] ?? '',
                    'followers'       => (int) ($firstStats['followerCount'] ?? 0),
                    'following'       => (int) ($firstStats['followingCount'] ?? 0),
                    'posts_count'     => (int) ($firstStats['videoCount'] ?? 0),
                    'bio'             => $first['signature'] ?? '',
                ];
            }
        }

        return null;
    }

    // ── HTTP helper ─────────────────────────────────────────────────────────────

    private function httpGet(string $url, array $headers = []): ?string
    {
        $this->log('DEBUG', "HTTP GET {$url}");

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_TIMEOUT        => self::HTTP_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER     => array_merge(['Accept: application/json'], $headers),
            CURLOPT_ENCODING       => '',          // Accept gzip/deflate
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($response === false || $error !== '') {
            $this->log('ERROR', "cURL error: {$error}");
            return null;
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $this->log('WARN', "HTTP {$httpCode} for {$url}");
            return null;
        }

        return (string) $response;
    }

    // ── Cache helpers ───────────────────────────────────────────────────────────

    private function cacheGet(string $key): mixed
    {
        $file = $this->cacheDir . '/' . md5($key) . '.json';
        if (!file_exists($file)) {
            return null;
        }

        $mtime = filemtime($file);
        if ($mtime === false || (time() - $mtime) > self::CACHE_TTL) {
            @unlink($file);
            return null;
        }

        $data = @file_get_contents($file);
        if ($data === false) {
            return null;
        }

        $decoded = json_decode($data, true);
        return $decoded !== null ? $decoded : null;
    }

    private function cacheSet(string $key, mixed $data): void
    {
        $file = $this->cacheDir . '/' . md5($key) . '.json';
        @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    // ── Logging ─────────────────────────────────────────────────────────────────

    private function log(string $level, string $message): void
    {
        error_log("[SocialApi][{$level}] {$message}");
    }

    // ── Utilities ───────────────────────────────────────────────────────────────

    private function truncate(string $text, int $maxLen): string
    {
        if (mb_strlen($text) <= $maxLen) {
            return $text;
        }
        return mb_substr($text, 0, $maxLen - 3) . '...';
    }

    // ── Mock data generators (fallback) ─────────────────────────────────────────

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
