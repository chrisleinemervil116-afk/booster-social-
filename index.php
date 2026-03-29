<?php
require __DIR__ . '/config.php';
setLanguageFromRequest();
$pdo = pdo();
$user = currentUser();
$view = $_GET['view'] ?? ($user ? 'dashboard' : 'home');
$message = '';
$error = '';

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['register'])) {
        $name = trim($_POST['full_name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
            $error = 'Informations invalides.';
        } else {
            try {
                $stmt = $pdo->prepare('INSERT INTO users(full_name, email, password_hash, balance, role, language) VALUES (?, ?, ?, 0, "user", ?)');
                $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $_SESSION['lang'] ?? 'fr']);
                $message = 'Compte créé, connectez-vous.';
                $view = 'login';
            } catch (PDOException $e) {
                $error = 'Email déjà utilisé.';
            }
        }
    }

    if (isset($_POST['login'])) {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $found = $stmt->fetch();
        if ($found && password_verify($password, $found['password_hash'])) {
            $_SESSION['user_id'] = $found['id'];
            $_SESSION['lang'] = $found['language'] ?: ($_SESSION['lang'] ?? 'fr');
            header('Location: ' . ($found['role'] === 'admin' ? 'admin.php' : 'index.php?view=dashboard'));
            exit;
        }
        $error = 'Identifiants invalides.';
    }

    if (isset($_POST['add_funds'])) {
        $u = requireLogin();
        $method = $_POST['method'] ?? 'MonCash';
        $amount = (float)($_POST['amount'] ?? 0);
        if ($amount <= 0) {
            $error = 'Montant invalide.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO transactions(user_id, method, amount, status, note) VALUES (?, ?, ?, "Pending", ?)');
            $stmt->execute([$u['id'], $method, $amount, 'Demande de dépôt']);
            $pdo->prepare('INSERT INTO notifications(user_id,title,message) VALUES (?, ?, ?)')->execute([$u['id'], 'Demande de dépôt', 'Votre dépôt est en attente de validation admin.']);
            $message = 'Demande de dépôt envoyée.';
        }
    }

    if (isset($_POST['place_order'])) {
        $u = requireLogin();
        $serviceId = (int)($_POST['service_id'] ?? 0);
        $link = trim($_POST['link'] ?? '');
        $quantity = (int)($_POST['quantity'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM services WHERE id = ? AND active = 1');
        $stmt->execute([$serviceId]);
        $service = $stmt->fetch();
        if (!$service) {
            $error = 'Service introuvable.';
        } elseif ($quantity < $service['min_qty'] || $quantity > $service['max_qty']) {
            $error = 'Quantité hors limites.';
        } else {
            $total = ($quantity / 1000) * (float)$service['rate'];
            if ((float)$u['balance'] < $total) {
                $error = 'Solde insuffisant.';
            } elseif (!isValidServiceLink($link, expectedLinkType($service))) {
                $error = expectedLinkType($service) === 'video'
                    ? 'Ce service demande un lien de vidéo (post/reel/short/watch).'
                    : 'Ce service demande un lien de compte/profil, pas un lien vidéo.';
            } else {
                $provider = providerRequest('add', ['service' => $service['provider_service_id'], 'link' => $link, 'quantity' => $quantity]);
                $providerOrderId = $provider['order'] ?? null;
                $status = isset($provider['error']) ? 'Pending Provider' : 'Processing';
                $pdo->prepare('INSERT INTO orders(user_id, service_id, link, quantity, total, status, provider_order_id) VALUES (?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$u['id'], $serviceId, $link, $quantity, $total, $status, $providerOrderId]);
                $pdo->prepare('UPDATE users SET balance = balance - ? WHERE id = ?')->execute([$total, $u['id']]);
                $pdo->prepare('INSERT INTO notifications(user_id,title,message) VALUES (?, ?, ?)')->execute([$u['id'], 'Nouvelle commande', 'Commande créée avec succès.']);
                $message = 'Commande créée avec succès.';
            }
        }
    }
}

$user = currentUser();
$services = $pdo->query('SELECT * FROM services WHERE active = 1 ORDER BY category, name')->fetchAll();
$orders = $user ? $pdo->prepare('SELECT o.*, s.name service_name FROM orders o JOIN services s ON s.id=o.service_id WHERE o.user_id=? ORDER BY o.id DESC LIMIT 20') : null;
if ($orders) { $orders->execute([$user['id']]); $orders = $orders->fetchAll(); }
$transactions = $user ? $pdo->prepare('SELECT * FROM transactions WHERE user_id=? ORDER BY id DESC LIMIT 20') : null;
if ($transactions) { $transactions->execute([$user['id']]); $transactions = $transactions->fetchAll(); }
$notifications = $user ? $pdo->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 10') : null;
if ($notifications) { $notifications->execute([$user['id']]); $notifications = $notifications->fetchAll(); }
?>
<!doctype html>
<html lang="fr" class="dark">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title><?= SITE_NAME ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<style>body{background:#050505}.card{background:#111;border:1px solid #2a2a2a}.accent{color:#ff8a00}.btn{background:#ff8a00;color:#000;font-weight:700}</style>
</head>
<body class="text-white min-h-screen">
<header class="sticky top-0 z-30 bg-black/80 border-b border-orange-500/20 backdrop-blur">
  <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
    <div>
      <h1 class="font-extrabold text-xl"><?= SITE_NAME ?></h1>
      <p class="text-xs text-orange-300"><?= t('tagline') ?></p>
    </div>
    <div class="flex items-center gap-2 text-sm">
      <a class="px-2 py-1 border rounded border-orange-500/50" href="?lang=fr">FR</a>
      <a class="px-2 py-1 border rounded border-orange-500/50" href="?lang=ht">HT</a>
      <?php if($user): ?>
        <a class="px-3 py-1 border rounded border-orange-500/50" href="?view=dashboard"><?= t('dashboard') ?></a>
        <?php if($user['role']==='admin'): ?><a class="px-3 py-1 border rounded border-orange-500/50" href="admin.php">Admin</a><?php endif; ?>
        <a class="px-3 py-1 btn rounded" href="?logout=1"><?= t('logout') ?></a>
      <?php else: ?>
        <a class="px-3 py-1 border rounded border-orange-500/50" href="?view=login"><?= t('login') ?></a>
        <a class="px-3 py-1 btn rounded" href="?view=register"><?= t('register') ?></a>
      <?php endif; ?>
    </div>
  </div>
</header>

<main class="max-w-6xl mx-auto p-4 grid gap-4">
  <?php if($message): ?><div class="bg-emerald-900/40 border border-emerald-500 p-3 rounded"><?= htmlspecialchars($message) ?></div><?php endif; ?>
  <?php if($error): ?><div class="bg-red-900/40 border border-red-500 p-3 rounded"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <?php if(!$user && $view === 'home'): ?>
    <section class="card rounded-2xl p-8 text-center">
      <h2 class="text-4xl font-black mb-2"><?= SITE_NAME ?></h2>
      <p class="text-slate-300 mb-4"><?= t('welcome') ?></p>
      <div class="flex justify-center gap-3"><a href="?view=register" class="btn px-4 py-2 rounded-lg"><?= t('register') ?></a><a href="?view=login" class="px-4 py-2 rounded-lg border border-orange-500/50"><?= t('login') ?></a></div>
      <a href="<?= WHATSAPP_URL ?>" class="inline-block mt-6 text-orange-400 underline">WhatsApp Support</a>
    </section>
  <?php endif; ?>

  <?php if(!$user && $view === 'login'): ?>
    <form method="post" class="card max-w-lg mx-auto rounded-2xl p-6 grid gap-3">
      <h3 class="text-2xl font-bold"><?= t('login') ?></h3>
      <input class="bg-black border border-zinc-700 p-3 rounded" name="email" type="email" placeholder="Email" required />
      <input class="bg-black border border-zinc-700 p-3 rounded" name="password" type="password" placeholder="Password" required />
      <button class="btn rounded p-3" name="login"><?= t('login') ?></button>
    </form>
  <?php endif; ?>

  <?php if(!$user && $view === 'register'): ?>
    <form method="post" class="card max-w-lg mx-auto rounded-2xl p-6 grid gap-3">
      <h3 class="text-2xl font-bold"><?= t('register') ?></h3>
      <input class="bg-black border border-zinc-700 p-3 rounded" name="full_name" placeholder="Nom complet" required />
      <input class="bg-black border border-zinc-700 p-3 rounded" name="email" type="email" placeholder="Email" required />
      <input class="bg-black border border-zinc-700 p-3 rounded" name="password" type="password" placeholder="Mot de passe (min 6)" required />
      <button class="btn rounded p-3" name="register"><?= t('register') ?></button>
    </form>
  <?php endif; ?>

  <?php if($user && $view === 'dashboard'): ?>
    <section class="grid md:grid-cols-3 gap-4">
      <div class="card rounded-xl p-4"><p class="text-slate-400 text-sm"><?= t('balance') ?></p><p class="text-3xl font-black accent"><?= money((float)$user['balance']) ?></p></div>
      <div class="card rounded-xl p-4"><p class="text-slate-400 text-sm"><?= t('orders') ?></p><p class="text-3xl font-black accent"><?= count($orders) ?></p></div>
      <div class="card rounded-xl p-4"><p class="text-slate-400 text-sm"><?= t('notifications') ?></p><p class="text-3xl font-black accent"><?= count($notifications) ?></p></div>
    </section>

    <section class="card rounded-2xl p-5">
      <h3 class="text-xl font-bold mb-3"><?= t('place_order') ?></h3>
      <form method="post" class="grid md:grid-cols-4 gap-3 items-end">
        <div class="md:col-span-2"><label class="text-xs">Service</label><select id="serviceSelect" name="service_id" class="w-full bg-black border border-zinc-700 rounded p-3"><?php foreach($services as $s): ?><option data-link-type="<?= expectedLinkType($s) ?>" value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?> (<?= money((float)$s['rate']) ?>/1k)</option><?php endforeach; ?></select></div>
        <div><label class="text-xs">Quantité</label><input name="quantity" type="number" min="1" class="w-full bg-black border border-zinc-700 rounded p-3" required /></div>
        <div><label class="text-xs">Lien</label><input id="linkInput" name="link" type="url" class="w-full bg-black border border-zinc-700 rounded p-3" placeholder="https://..." required /><p id="linkHint" class="text-[11px] text-orange-300 mt-1">Entrez le lien demandé par le service.</p></div>
        <button class="btn rounded p-3 md:col-span-4" name="place_order"><?= t('place_order') ?></button>
      </form>
    </section>

    <section class="grid md:grid-cols-2 gap-4">
      <div class="card rounded-2xl p-5">
        <h3 class="font-bold mb-2"><?= t('add_funds') ?></h3>
        <form method="post" class="grid gap-2">
          <select name="method" class="bg-black border border-zinc-700 p-3 rounded"><option>MonCash</option><option>NatCash</option><option>Bitcoin</option><option>USDT</option></select>
          <input name="amount" type="number" step="0.01" placeholder="Montant" class="bg-black border border-zinc-700 p-3 rounded" required />
          <button class="btn p-3 rounded" name="add_funds"><?= t('add_funds') ?></button>
        </form>
      </div>
      <div class="card rounded-2xl p-5">
        <h3 class="font-bold mb-2"><?= t('notifications') ?></h3>
        <ul class="space-y-2 text-sm max-h-56 overflow-auto"><?php foreach($notifications as $n): ?><li class="border border-zinc-800 rounded p-2"><b><?= htmlspecialchars($n['title']) ?></b><br><?= htmlspecialchars($n['message']) ?></li><?php endforeach; ?></ul>
      </div>
    </section>

    <section class="card rounded-2xl p-5 overflow-auto">
      <h3 class="font-bold mb-2"><?= t('history') ?></h3>
      <table class="w-full text-sm"><tr class="text-left text-orange-300"><th>#</th><th>Service</th><th>Qté</th><th>Total</th><th>Status</th></tr>
      <?php foreach($orders as $o): ?><tr><td><?= $o['id'] ?></td><td><?= htmlspecialchars($o['service_name']) ?></td><td><?= $o['quantity'] ?></td><td><?= money((float)$o['total']) ?></td><td><?= htmlspecialchars($o['status']) ?></td></tr><?php endforeach; ?>
      </table>
    </section>
  <?php endif; ?>
</main>
<script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
<script>
const serviceSelect = document.getElementById('serviceSelect');
const linkInput = document.getElementById('linkInput');
const linkHint = document.getElementById('linkHint');
if (serviceSelect && linkInput && linkHint) {
  const syncLinkRequirement = () => {
    const linkType = serviceSelect.options[serviceSelect.selectedIndex]?.dataset?.linkType || 'generic';
    if (linkType === 'video') {
      linkInput.placeholder = 'https://.../video | reel | watch | shorts';
      linkHint.textContent = 'Service vidéo: mettez le lien de la vidéo/post.';
    } else if (linkType === 'account') {
      linkInput.placeholder = 'https://.../@compte ou /channel';
      linkHint.textContent = 'Service abonnés/followers: mettez le lien du compte/profil.';
    } else {
      linkInput.placeholder = 'https://...';
      linkHint.textContent = 'Entrez le lien demandé par le service.';
    }
  };
  syncLinkRequirement();
  serviceSelect.addEventListener('change', syncLinkRequirement);
}

<?php if ($user): ?>
const supabase = window.supabase.createClient('<?= SUPABASE_URL ?>', '<?= SUPABASE_ANON_KEY ?>');
const currentUserId = <?= (int)$user['id'] ?>;

supabase
  .channel('user-notifications-' + currentUserId)
  .on('postgres_changes', {
    event: '*',
    schema: 'public',
    table: 'notifications',
    filter: 'user_id=eq.' + currentUserId
  }, () => window.location.reload())
  .subscribe();

supabase
  .channel('user-orders-' + currentUserId)
  .on('postgres_changes', {
    event: '*',
    schema: 'public',
    table: 'orders',
    filter: 'user_id=eq.' + currentUserId
  }, () => window.location.reload())
  .subscribe();
<?php endif; ?>
</script>
</body>
</html>
