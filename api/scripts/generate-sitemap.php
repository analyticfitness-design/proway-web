<?php
/**
 * Sitemap Generator — ProWay Lab
 *
 * Scans public pages and blog articles to generate sitemap.xml
 *
 * Usage:
 *   php api/scripts/generate-sitemap.php
 *
 * Output:
 *   sitemap.xml in project root
 */
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$baseUrl = 'https://prowaylab.com';
$today = date('Y-m-d');

// ── Public pages ────────────────────────────────────────────────────────────
$publicPages = [
    ['loc' => '/',           'priority' => '1.0',  'changefreq' => 'monthly'],
    ['loc' => '/servicios',  'priority' => '0.9',  'changefreq' => 'monthly'],
    ['loc' => '/portafolio', 'priority' => '0.8',  'changefreq' => 'monthly'],
    ['loc' => '/metodo',     'priority' => '0.8',  'changefreq' => 'monthly'],
    ['loc' => '/contacto',   'priority' => '0.8',  'changefreq' => 'monthly'],
    ['loc' => '/blog',       'priority' => '0.8',  'changefreq' => 'weekly'],
];

// ── Scan blog articles ──────────────────────────────────────────────────────
$blogDir = $root . '/blog';
$blogArticles = [];

if (is_dir($blogDir)) {
    $files = glob($blogDir . '/*.html');
    foreach ($files as $file) {
        $basename = basename($file, '.html');
        // Skip the index page (already in publicPages as /blog)
        if ($basename === 'index') {
            continue;
        }

        $lastmod = date('Y-m-d', filemtime($file));
        $blogArticles[] = [
            'loc'        => '/blog/' . $basename,
            'lastmod'    => $lastmod,
            'priority'   => '0.6',
            'changefreq' => 'monthly',
        ];
    }
}

// ── Legal pages ─────────────────────────────────────────────────────────────
$legalDir = $root . '/legal';
$legalPages = [];

if (is_dir($legalDir)) {
    $files = glob($legalDir . '/*.html');
    foreach ($files as $file) {
        $basename = basename($file, '.html');
        $lastmod = date('Y-m-d', filemtime($file));
        $legalPages[] = [
            'loc'        => '/legal/' . $basename,
            'lastmod'    => $lastmod,
            'priority'   => '0.3',
            'changefreq' => 'yearly',
        ];
    }
}

// ── Build XML ───────────────────────────────────────────────────────────────
$xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;

// Public pages
foreach ($publicPages as $page) {
    $loc = $baseUrl . $page['loc'];
    // Use file modification date for public pages
    $htmlFile = match ($page['loc']) {
        '/'           => $root . '/public/index.html',
        '/servicios'  => $root . '/public/servicios.html',
        '/portafolio' => $root . '/public/portafolio.html',
        '/metodo'     => $root . '/public/metodo.html',
        '/contacto'   => $root . '/public/contacto.html',
        '/blog'       => $root . '/blog/index.html',
        default       => null,
    };
    $lastmod = $htmlFile && file_exists($htmlFile) ? date('Y-m-d', filemtime($htmlFile)) : $today;

    $xml .= "  <url>" . PHP_EOL;
    $xml .= "    <loc>{$loc}</loc>" . PHP_EOL;
    $xml .= "    <lastmod>{$lastmod}</lastmod>" . PHP_EOL;
    $xml .= "    <changefreq>{$page['changefreq']}</changefreq>" . PHP_EOL;
    $xml .= "    <priority>{$page['priority']}</priority>" . PHP_EOL;
    $xml .= "  </url>" . PHP_EOL;
}

// Blog articles
foreach ($blogArticles as $article) {
    $loc = $baseUrl . $article['loc'];
    $xml .= "  <url>" . PHP_EOL;
    $xml .= "    <loc>{$loc}</loc>" . PHP_EOL;
    $xml .= "    <lastmod>{$article['lastmod']}</lastmod>" . PHP_EOL;
    $xml .= "    <changefreq>{$article['changefreq']}</changefreq>" . PHP_EOL;
    $xml .= "    <priority>{$article['priority']}</priority>" . PHP_EOL;
    $xml .= "  </url>" . PHP_EOL;
}

// Legal pages
foreach ($legalPages as $page) {
    $loc = $baseUrl . $page['loc'];
    $xml .= "  <url>" . PHP_EOL;
    $xml .= "    <loc>{$loc}</loc>" . PHP_EOL;
    $xml .= "    <lastmod>{$page['lastmod']}</lastmod>" . PHP_EOL;
    $xml .= "    <changefreq>{$page['changefreq']}</changefreq>" . PHP_EOL;
    $xml .= "    <priority>{$page['priority']}</priority>" . PHP_EOL;
    $xml .= "  </url>" . PHP_EOL;
}

$xml .= '</urlset>' . PHP_EOL;

// ── Write file ──────────────────────────────────────────────────────────────
$outputPath = $root . '/sitemap.xml';
file_put_contents($outputPath, $xml);

$totalUrls = count($publicPages) + count($blogArticles) + count($legalPages);
echo "Sitemap generated: {$outputPath}" . PHP_EOL;
echo "Total URLs: {$totalUrls}" . PHP_EOL;
echo "  Public pages: " . count($publicPages) . PHP_EOL;
echo "  Blog articles: " . count($blogArticles) . PHP_EOL;
echo "  Legal pages: " . count($legalPages) . PHP_EOL;
