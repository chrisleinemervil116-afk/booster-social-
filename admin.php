<?php
require __DIR__ . '/config.php';
$pdo = pdo();
$admin = requireAdmin();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_provider_key'])) {
        $key = trim($_POST['provider_api_key'] ?? '');
        $pdo->prepare('UPDATE settings SET provider_api_key = ? WHERE id = 1')->execute([$key]);
        $message = 'Clé API enregistrée.';
    }

    if (isset($_POST['import_services'])) {
        $response = providerRequest('services');
        if (isset($response['error'])) {
            $error = 'Import API échoué: ' . $response['error'];
        } else {
            $servicesToImport = is_array($response) ? normalizeProviderServices($response) : [];
            if (!$servicesToImport) {
                $error = 'Aucun service importable trouvé dans la réponse API.';
            } else {
            $pdo->exec('DELETE FROM services');
            $stmt = $pdo->prepare('INSERT INTO services(provider_service_id,name,category,rate,min_qty,max_qty,active) VALUES (?,?,?,?,?,?,?)');
            foreach ($servicesToImport as $item) {
                $stmt->execute([
                    $item['provider_service_id'],
                    $item['name'],
                    $item['category'],
                    $item['rate'],
                    $item['min_qty'],
                    $item['max_qty'],
                    $item['active']
                ]);
            }
            $message = count($servicesToImport) . ' services importés depuis le provider Like4Like.';
            }
        }
    }

    if (isset($_POST['update_order_status'])) {
        $orderId = (int)$_POST['order_id'];
        $status = $_POST['status'];
        $pdo->prepare('UPDATE orders SET status=? WHERE id=?')->execute([$status, $orderId]);
        $owner = $pdo->prepare('SELECT user_id FROM orders WHERE id=?');
        $owner->execute([$orderId]);
        $uid = $owner->fetchColumn();
        if ($uid) {
            $pdo->prepare('INSERT INTO notifications(user_id,title,message) VALUES (?,?,?)')->execute([$uid, 'Commande mise à jour', "Votre commande #$orderId est maintenant $status"]);
        }
        $message = 'Statut de commande mis à jour.';
    }

    if (isset($_POST['approve_tx'])) {
        $txId = (int)$_POST['tx_id'];
        $tx = $pdo->prepare('SELECT * FROM transactions WHERE id=?');
        $tx->execute([$txId]);
        $tx = $tx->fetch();
        if ($tx && $tx['status'] === 'Pending') {
            $pdo->prepare('UPDATE transactions SET status="Approved" WHERE id=?')->execute([$txId]);
            $pdo->prepare('UPDATE users SET balance = balance + ? WHERE id=?')->execute([(float)$tx['amount'], $tx['user_id']]);
            $pdo->prepare('INSERT INTO notifications(user_id,title,message) VALUES (?,?,?)')->execute([$tx['user_id'], 'Dépôt validé', 'Votre dépôt a été validé et crédité.']);
            $message = 'Transaction approuvée et solde crédité.';
        }
    }
}

$providerKey = $pdo->query('SELECT provider_api_key FROM settings WHERE id=1')->fetchColumn();
$users = $pdo->query('SELECT id,full_name,email,balance,role,created_at FROM users ORDER BY id DESC')->fetchAll();
$orders = $pdo->query('SELECT o.*, u.full_name, s.name service_name FROM orders o JOIN users u ON u.id=o.user_id JOIN services s ON s.id=o.service_id ORDER BY o.id DESC LIMIT 50')->fetchAll();
$transactions = $pdo->query('SELECT t.*, u.full_name FROM transactions t JOIN users u ON u.id=t.user_id ORDER BY t.id DESC LIMIT 50')->fetchAll();
$stats = [
    'users' => (int)$pdo->query('SELECT COUNT(*) FROM users WHERE role="user"')->fetchColumn(),
    'orders' => (int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
    'pending_tx' => (int)$pdo->query('SELECT COUNT(*) FROM transactions WHERE status="Pending"')->fetchColumn(),
    'revenue' => (float)$pdo->query('SELECT COALESCE(SUM(total),0) FROM orders')->fetchColumn(),
];
?>
<!doctype html>
<html lang="fr" class="dark">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin - <?= SITE_NAME ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<style>body{background:#050505}.card{background:#111;border:1px solid #2a2a2a}.btn{background:#ff8a00;color:#000;font-weight:700}</style>
</head>
<body class="text-white p-4">
<div class="max-w-7xl mx-auto space-y-4">
  <div class="flex items-center justify-between card p-4 rounded-xl">
    <div><h1 class="text-2xl font-black">Admin Dashboard</h1><p class="text-orange-400"><?= ADMIN_EMAIL ?></p></div>
    <div class="flex gap-2"><a class="px-3 py-2 border border-orange-500/50 rounded" href="index.php?view=dashboard">User Panel</a><a class="px-3 py-2 btn rounded" href="index.php?logout=1">Logout</a></div>
  </div>
  <?php if($message): ?><div class="p-3 rounded border border-emerald-500 bg-emerald-950/40"><?= htmlspecialchars($message) ?></div><?php endif; ?>
  <?php if($error): ?><div class="p-3 rounded border border-red-500 bg-red-950/40"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <section class="grid md:grid-cols-4 gap-3">
    <div class="card p-4 rounded"><p>Users</p><p class="text-3xl text-orange-400 font-black"><?= $stats['users'] ?></p></div>
    <div class="card p-4 rounded"><p>Orders</p><p class="text-3xl text-orange-400 font-black"><?= $stats['orders'] ?></p></div>
    <div class="card p-4 rounded"><p>Pending deposits</p><p class="text-3xl text-orange-400 font-black"><?= $stats['pending_tx'] ?></p></div>
    <div class="card p-4 rounded"><p>Revenue</p><p class="text-3xl text-orange-400 font-black"><?= money($stats['revenue']) ?></p></div>
  </section>

  <section class="card p-5 rounded-xl grid md:grid-cols-2 gap-4">
    <form method="post" class="grid gap-2">
      <h2 class="font-bold text-lg">Provider API Integration</h2>
      <p class="text-sm text-slate-400">Endpoint: <?= PROVIDER_API_URL ?></p>
      <input class="bg-black border border-zinc-700 rounded p-3" name="provider_api_key" value="<?= htmlspecialchars($providerKey) ?>" placeholder="API key">
      <button class="btn p-3 rounded" name="save_provider_key">Save API Key</button>
    </form>
    <form method="post" class="grid gap-2 content-end">
      <h2 class="font-bold text-lg">Import products</h2>
      <p class="text-sm text-slate-400">Synchronise followers, likes, views, subscribers from provider.</p>
      <button class="btn p-3 rounded" name="import_services">Import services</button>
    </form>
  </section>

  <section class="card p-5 rounded-xl overflow-auto">
    <h2 class="font-bold text-lg mb-2">Users management</h2>
    <table class="w-full text-sm"><tr class="text-orange-300 text-left"><th>ID</th><th>Name</th><th>Email</th><th>Balance</th><th>Role</th></tr>
      <?php foreach($users as $u): ?><tr><td><?= $u['id'] ?></td><td><?= htmlspecialchars($u['full_name']) ?></td><td><?= htmlspecialchars($u['email']) ?></td><td><?= money((float)$u['balance']) ?></td><td><?= $u['role'] ?></td></tr><?php endforeach; ?>
    </table>
  </section>

  <section class="card p-5 rounded-xl overflow-auto">
    <h2 class="font-bold text-lg mb-2">Orders management</h2>
    <table class="w-full text-sm"><tr class="text-orange-300 text-left"><th>#</th><th>User</th><th>Service</th><th>Total</th><th>Status</th><th>Action</th></tr>
      <?php foreach($orders as $o): ?><tr><td><?= $o['id'] ?></td><td><?= htmlspecialchars($o['full_name']) ?></td><td><?= htmlspecialchars($o['service_name']) ?></td><td><?= money((float)$o['total']) ?></td><td><?= htmlspecialchars($o['status']) ?></td><td><form method="post" class="flex gap-1"><input type="hidden" name="order_id" value="<?= $o['id'] ?>"><select name="status" class="bg-black border border-zinc-700 rounded"><option>Pending</option><option>Processing</option><option>Completed</option><option>Cancelled</option></select><button class="px-2 rounded border border-orange-500/50" name="update_order_status">Update</button></form></td></tr><?php endforeach; ?>
    </table>
  </section>

  <section class="card p-5 rounded-xl overflow-auto">
    <h2 class="font-bold text-lg mb-2">Payments / Transactions</h2>
    <table class="w-full text-sm"><tr class="text-orange-300 text-left"><th>#</th><th>User</th><th>Method</th><th>Amount</th><th>Status</th><th>Action</th></tr>
      <?php foreach($transactions as $tx): ?><tr><td><?= $tx['id'] ?></td><td><?= htmlspecialchars($tx['full_name']) ?></td><td><?= htmlspecialchars($tx['method']) ?></td><td><?= money((float)$tx['amount']) ?></td><td><?= htmlspecialchars($tx['status']) ?></td><td><?php if($tx['status']==='Pending'): ?><form method="post"><input type="hidden" name="tx_id" value="<?= $tx['id'] ?>"><button class="px-2 rounded border border-emerald-500/50" name="approve_tx">Approve</button></form><?php endif; ?></td></tr><?php endforeach; ?>
    </table>
  </section>
</div>
<script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
<script>
const adminSupabase = window.supabase.createClient('<?= SUPABASE_URL ?>', '<?= SUPABASE_ANON_KEY ?>');
const reloadTables = () => window.location.reload();

['users', 'orders', 'transactions', 'services', 'notifications'].forEach((tableName) => {
  adminSupabase
    .channel('admin-watch-' + tableName)
    .on('postgres_changes', { event: '*', schema: 'public', table: tableName }, reloadTables)
    .subscribe();
});
</script>
</body>
</html>
