<?php
session_start();

define('SITE_NAME', 'Boost Social Haiti 🇭🇹');
define('ADMIN_EMAIL', 'chrisleineinfluencer@gmail.com');
define('WHATSAPP_URL', 'https://wa.me/50934186164');
define('PROVIDER_API_URL', 'https://www.like4like.org/api/v1/');
define('DEFAULT_PROVIDER_KEY', '');
define('SUPABASE_URL', 'https://zynblmmdtpyhdeabwxnh.supabase.co');
define('SUPABASE_ANON_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Inp5bmJsbW1kdHB5aGRlYWJ3eG5oIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzE3MDc5NzYsImV4cCI6MjA4NzI4Mzk3Nn0.a2PhqQ4AQDEijcfWjP_lzfTkHXZCSd3G6IGyJZHjsY4');

function pdo(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dbPath = __DIR__ . '/panel.sqlite';
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    bootstrapSchema($pdo);
    return $pdo;
}

function bootstrapSchema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        full_name TEXT NOT NULL,
        email TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        balance REAL NOT NULL DEFAULT 0,
        role TEXT NOT NULL DEFAULT 'user',
        language TEXT NOT NULL DEFAULT 'fr',
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        id INTEGER PRIMARY KEY CHECK (id = 1),
        provider_api_key TEXT DEFAULT '',
        site_name TEXT DEFAULT 'Boost Social Haiti 🇭🇹'
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS services (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        provider_service_id TEXT NOT NULL,
        name TEXT NOT NULL,
        category TEXT NOT NULL,
        rate REAL NOT NULL,
        min_qty INTEGER NOT NULL,
        max_qty INTEGER NOT NULL,
        active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        service_id INTEGER NOT NULL,
        link TEXT NOT NULL,
        quantity INTEGER NOT NULL,
        total REAL NOT NULL,
        status TEXT NOT NULL DEFAULT 'Pending',
        provider_order_id TEXT,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(user_id) REFERENCES users(id),
        FOREIGN KEY(service_id) REFERENCES services(id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS transactions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        method TEXT NOT NULL,
        amount REAL NOT NULL,
        status TEXT NOT NULL DEFAULT 'Pending',
        note TEXT,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(user_id) REFERENCES users(id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        title TEXT NOT NULL,
        message TEXT NOT NULL,
        is_read INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(user_id) REFERENCES users(id)
    )");

    $pdo->exec("INSERT OR IGNORE INTO settings(id, provider_api_key, site_name) VALUES (1, '', 'Boost Social Haiti 🇭🇹')");

    $adminExists = $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
    if ((int)$adminExists === 0) {
        $stmt = $pdo->prepare("INSERT INTO users(full_name, email, password_hash, balance, role, language) VALUES (?, ?, ?, ?, 'admin', 'fr')");
        $stmt->execute(['Admin Boost', ADMIN_EMAIL, password_hash('Admin@12345', PASSWORD_DEFAULT), 0]);
    }

    $serviceExists = $pdo->query("SELECT COUNT(*) FROM services")->fetchColumn();
    if ((int)$serviceExists === 0) {
        $seed = [
            ['101', 'Instagram Followers - Fast delivery ⚡', 'Followers', 1.20, 50, 10000],
            ['203', 'TikTok Likes - Trusted services 🔥', 'Likes', 0.90, 50, 20000],
            ['334', 'YouTube Views - Fast delivery ⚡', 'Views', 0.75, 100, 100000],
            ['411', 'YouTube Subscribers - Trusted services 🔥', 'Subscribers', 10.00, 10, 5000]
        ];
        $stmt = $pdo->prepare("INSERT INTO services(provider_service_id, name, category, rate, min_qty, max_qty, active) VALUES (?, ?, ?, ?, ?, ?, 1)");
        foreach ($seed as $s) {
            $stmt->execute($s);
        }
    }
}

function t(string $key): string {
    $lang = $_SESSION['lang'] ?? 'fr';
    $dict = [
        'fr' => [
            'tagline' => 'Fast delivery ⚡ • Trusted services 🔥',
            'welcome' => 'Panel SMM professionnel pour développer vos réseaux.',
            'login' => 'Connexion', 'register' => 'Inscription', 'logout' => 'Déconnexion',
            'dashboard' => 'Tableau de bord', 'balance' => 'Solde', 'orders' => 'Commandes',
            'history' => 'Historique', 'services' => 'Services', 'add_funds' => 'Ajouter des fonds',
            'notifications' => 'Notifications', 'admin' => 'Admin', 'place_order' => 'Passer une commande'
        ],
        'ht' => [
            'tagline' => 'Livrez vit ⚡ • Sèvis fyab 🔥',
            'welcome' => 'Panel SMM pwofesyonèl pou fè rezo sosyal ou grandi.',
            'login' => 'Konekte', 'register' => 'Enskri', 'logout' => 'Dekonekte',
            'dashboard' => 'Tablo', 'balance' => 'Balans', 'orders' => 'Kòmand',
            'history' => 'Istwa', 'services' => 'Sèvis', 'add_funds' => 'Ajoute lajan',
            'notifications' => 'Notifikasyon', 'admin' => 'Admin', 'place_order' => 'Fè yon kòmand'
        ]
    ];
    return $dict[$lang][$key] ?? $key;
}

function setLanguageFromRequest(): void {
    if (!empty($_GET['lang']) && in_array($_GET['lang'], ['fr', 'ht'], true)) {
        $_SESSION['lang'] = $_GET['lang'];
    }
}

function currentUser(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    $stmt = pdo()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function requireLogin(): array {
    $user = currentUser();
    if (!$user) {
        header('Location: index.php?view=login');
        exit;
    }
    return $user;
}

function requireAdmin(): array {
    $user = requireLogin();
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        exit('Access denied');
    }
    return $user;
}

function providerRequest(string $action, array $payload = []): array {
    $key = pdo()->query('SELECT provider_api_key FROM settings WHERE id = 1')->fetchColumn() ?: DEFAULT_PROVIDER_KEY;
    $data = array_merge(['key' => $key, 'action' => $action], $payload);

    $ch = curl_init(PROVIDER_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_TIMEOUT => 20,
    ]);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) return ['error' => $error ?: 'Provider unavailable'];
    $json = json_decode($response, true);
    return is_array($json) ? $json : ['raw' => $response];
}

function normalizeProviderServices(array $response): array {
    $candidates = [];

    if (isset($response[0]) && is_array($response[0])) {
        $candidates = $response;
    } elseif (isset($response['services']) && is_array($response['services'])) {
        $candidates = $response['services'];
    } elseif (isset($response['data']['services']) && is_array($response['data']['services'])) {
        $candidates = $response['data']['services'];
    } elseif (isset($response['data']) && is_array($response['data']) && isset($response['data'][0])) {
        $candidates = $response['data'];
    }

    $normalized = [];
    foreach ($candidates as $item) {
        if (!is_array($item)) {
            continue;
        }

        $providerId = $item['service'] ?? $item['id'] ?? $item['service_id'] ?? null;
        $name = $item['name'] ?? $item['title'] ?? null;
        if ($providerId === null || $name === null) {
            continue;
        }

        $rawRate = $item['rate']
            ?? $item['price']
            ?? $item['cost']
            ?? $item['price_per_1000']
            ?? $item['rate_per_1000']
            ?? 0;
        $rate = (float)$rawRate;
        if ($rate <= 0) {
            $rate = 0.01;
        }

        $normalized[] = [
            'provider_service_id' => (string)$providerId,
            'name' => trim((string)$name),
            'category' => trim((string)($item['category'] ?? $item['group'] ?? 'General')),
            'rate' => $rate,
            'min_qty' => max(1, (int)($item['min'] ?? $item['min_qty'] ?? 1)),
            'max_qty' => max(1, (int)($item['max'] ?? $item['max_qty'] ?? 100000)),
            'active' => isset($item['active']) ? (int)((bool)$item['active']) : 1,
        ];
    }

    return $normalized;
}

function money(float $value): string {
    return '$' . number_format($value, 2);
}

function expectedLinkType(array $service): string {
    $haystack = strtolower(($service['name'] ?? '') . ' ' . ($service['category'] ?? ''));
    if (
        str_contains($haystack, 'view') ||
        str_contains($haystack, 'like') ||
        str_contains($haystack, 'watch') ||
        str_contains($haystack, 'video') ||
        str_contains($haystack, 'reel') ||
        str_contains($haystack, 'short')
    ) {
        return 'video';
    }
    if (
        str_contains($haystack, 'subscriber') ||
        str_contains($haystack, 'follower') ||
        str_contains($haystack, 'abonn')
    ) {
        return 'account';
    }
    return 'generic';
}

function isValidServiceLink(string $link, string $type): bool {
    if (!filter_var($link, FILTER_VALIDATE_URL)) {
        return false;
    }
    $path = strtolower((string)parse_url($link, PHP_URL_PATH));
    return match ($type) {
        'video' => (
            str_contains($path, '/video') ||
            str_contains($path, '/reel') ||
            str_contains($path, '/watch') ||
            str_contains($path, '/shorts') ||
            str_contains($path, '/status')
        ),
        'account' => !(
            str_contains($path, '/video') ||
            str_contains($path, '/reel') ||
            str_contains($path, '/watch') ||
            str_contains($path, '/shorts') ||
            str_contains($path, '/status')
        ),
        default => true,
    };
}
