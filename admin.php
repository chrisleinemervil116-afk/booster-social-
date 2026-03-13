<?php
require 'config.php';

$msg = "";
$error = "";

// --- 1. SAUVEGARDE CLÉ API ---
if (isset($_POST['save_key'])) {
    if ($demoMode) {
        $_SESSION['demo_api_key'] = $_POST['api_key'];
    } else {
        $stmt = $pdo->prepare("UPDATE settings SET provider_api_key = ? WHERE id = 1");
        $stmt->execute([$_POST['api_key']]);
    }
    $msg = "Clé API sauvegardée ! Le solde va s'actualiser.";
}

// --- 2. IMPORTATION DES SERVICES (CORRIGÉ POUR DESCRIPTION) ---
if (isset($_POST['import_services'])) {
    $apiServices = callBlessPanel('services');
    
    if (is_array($apiServices) && !isset($apiServices['error'])) {
        if ($demoMode) {
            $_SESSION['demo_services'] = [];
            $i = 1;
            foreach ($apiServices as $s) {
                // TENTATIVE DE RÉCUPÉRATION DE LA VRAIE DESCRIPTION
                $realDesc = $s['description'] ?? "Démarrage : Instantané\nQualité : Réelle\nGarantie : 30 Jours";
                
                $_SESSION['demo_services'][] = [
                    'id' => $i++,
                    'provider_service_id' => $s['service'],
                    'name' => $s['name'],
                    'category' => $s['category'],
                    'rate' => $s['rate'] * 1.20, // Marge auto 20%
                    'provider_rate' => $s['rate'],
                    'min' => $s['min'],
                    'max' => $s['max'],
                    'description' => $realDesc // On utilise la vraie description ici
                ];
            }
        } else {
            // Mode SQL : On vide et on remplit proprement
            $pdo->exec("TRUNCATE TABLE services");
            
            $stmt = $pdo->prepare("INSERT INTO services (provider_service_id, name, category, rate, provider_rate, min, max, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            
            foreach ($apiServices as $s) {
                // LOGIQUE INTELLIGENTE :
                // On regarde si l'API nous donne une description. Sinon, on met un texte par défaut.
                if (!empty($s['description'])) {
                    $desc = $s['description'];
                } else {
                    $desc = "🚀 Démarrage : Rapide\n💎 Qualité : " . $s['category'] . "\n⚡ Vitesse : Haute\n♻️ Garantie : Incluse";
                }
                
                $stmt->execute([
                    $s['service'], 
                    $s['name'], 
                    $s['category'], 
                    $s['rate'] * 1.20, // Marge 20%
                    $s['rate'],        // Coût d'achat
                    $s['min'], 
                    $s['max'],
                    $desc              // La description correcte est insérée
                ]);
            }
        }
        $msg = "Catalogue synchronisé ! Descriptions mises à jour.";
    } else {
        $error = "Erreur API : Vérifiez votre clé sur BlessPanel.";
    }
}

// --- 3. MISE À JOUR SERVICE ---
if (isset($_POST['update_service'])) {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $rate = $_POST['rate'];
    $desc = $_POST['description'];
    
    if ($demoMode) {
        foreach ($_SESSION['demo_services'] as &$s) {
            if ($s['id'] == $id) {
                $s['name'] = $name;
                $s['rate'] = $rate;
                $s['description'] = $desc;
                break;
            }
        }
    } else {
        $stmt = $pdo->prepare("UPDATE services SET name = ?, rate = ?, description = ? WHERE id = ?");
        $stmt->execute([$name, $rate, $desc, $id]);
    }
    $msg = "Service mis à jour !";
}

// --- DONNÉES ---
$balance = 0.00;
$currency = 'USD';
$services = [];
$currentKey = '';

if ($demoMode) {
    $currentKey = $_SESSION['demo_api_key'] ?? '';
    $services = $_SESSION['demo_services'] ?? [];
    $balance = 50.00;
    if (!empty($currentKey)) {
        $apiBal = callBlessPanel('balance');
        if (isset($apiBal['balance'])) {
            $balance = (float)$apiBal['balance'];
            $currency = $apiBal['currency'];
        }
    }
} else {
    try {
        $currentKey = $pdo->query("SELECT provider_api_key FROM settings LIMIT 1")->fetchColumn();
        $services = $pdo->query("SELECT * FROM services")->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($currentKey)) {
            $apiBal = callBlessPanel('balance');
            if (isset($apiBal['balance'])) {
                $balance = (float)$apiBal['balance'];
                $currency = $apiBal['currency'];
            }
        }
    } catch (Exception $e) {
        $error = "Erreur BDD : " . $e->getMessage();
    }
}

function getCatIcon($cat) {
    $cat = strtolower($cat);
    if (strpos($cat, 'instagram') !== false) return ['fab fa-instagram', 'text-pink-500'];
    if (strpos($cat, 'facebook') !== false) return ['fab fa-facebook', 'text-blue-600'];
    if (strpos($cat, 'tiktok') !== false) return ['fab fa-tiktok', 'text-white'];
    if (strpos($cat, 'youtube') !== false) return ['fab fa-youtube', 'text-red-500'];
    if (strpos($cat, 'twitter') !== false || strpos($cat, 'x ') !== false) return ['fab fa-x-twitter', 'text-slate-300'];
    if (strpos($cat, 'telegram') !== false) return ['fab fa-telegram', 'text-blue-400'];
    return ['fas fa-bolt', 'text-amber-400'];
}
?>
<!DOCTYPE html>
<html lang="fr" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>Admin - Revendeur Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class', theme: { extend: { colors: { dark: '#020617', panel: '#0f172a', primary: '#6366f1' } } } }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .modal { transition: opacity 0.2s ease-in-out; pointer-events: none; opacity: 0; }
        .modal.active { pointer-events: auto; opacity: 1; }
        .modal-content { transform: scale(0.95); transition: transform 0.2s ease-in-out; }
        .modal.active .modal-content { transform: scale(1); }
    </style>
</head>
<body class="bg-dark text-slate-200 font-sans min-h-screen p-4 sm:p-6 pb-20">

<div class="max-w-7xl mx-auto space-y-6">
    
    <div class="flex flex-col md:flex-row justify-between items-center gap-4 bg-panel border border-slate-700 p-4 rounded-2xl shadow-lg">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-primary/20 text-primary rounded-xl flex items-center justify-center text-xl">
                <i class="fas fa-user-shield"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold text-white">Panel Administrateur</h1>
                <p class="text-xs text-slate-400">Kit de démarrage SMM</p>
            </div>
        </div>
        
        <div class="flex items-center gap-3 w-full md:w-auto">
            <a href="index.php" class="flex-1 md:flex-none bg-white/5 hover:bg-white/10 text-white px-4 py-2.5 rounded-xl text-sm font-bold border border-white/10 transition text-center">
                <i class="fas fa-store mr-2"></i> Voir le Site Client
            </a>
            
            <div class="flex items-center gap-3 bg-dark border border-slate-600 px-4 py-2 rounded-xl">
                <div class="text-right leading-tight">
                    <div class="text-[10px] uppercase font-bold text-slate-500">Solde API</div>
                    <div class="text-emerald-400 font-mono font-bold text-lg">$<?php echo number_format($balance, 4); ?></div>
                </div>
                <i class="fas fa-wallet text-emerald-500 text-xl"></i>
            </div>
        </div>
    </div>

    <div class="bg-gradient-to-r from-slate-800 to-slate-900 border border-slate-700 rounded-2xl p-6 relative overflow-hidden">
        <div class="absolute top-0 right-0 p-6 opacity-5 text-6xl text-white"><i class="fas fa-code"></i></div>
        <div class="relative z-10">
            <h2 class="text-lg font-bold text-white mb-2 flex items-center gap-2">
                <i class="fas fa-info-circle text-primary"></i> À propos de ce Panel Admin
            </h2>
            <p class="text-sm text-slate-300 leading-relaxed mb-4">
                Ceci est votre espace de gestion. En tant qu'administrateur, vous avez le contrôle total sur votre business :
            </p>
            <ul class="text-sm text-slate-400 space-y-2 mb-4 grid grid-cols-1 md:grid-cols-2">
                <li class="flex items-center gap-2"><i class="fas fa-check text-emerald-400"></i> Vous définissez vos propres prix et marges.</li>
                <li class="flex items-center gap-2"><i class="fas fa-check text-emerald-400"></i> Vous modifiez les noms et descriptions des services.</li>
                <li class="flex items-center gap-2"><i class="fas fa-check text-emerald-400"></i> Tout est connecté automatiquement à nos serveurs.</li>
                <li class="flex items-center gap-2"><i class="fas fa-check text-emerald-400"></i> <b>Nous sommes uniquement votre fournisseur technique.</b></li>
            </ul>
            <div class="text-xs text-slate-500 italic bg-black/20 p-3 rounded-lg border border-white/5">
                💡 <strong>Pour les développeurs :</strong> Ce kit est une base. Vous pouvez ajouter la gestion des utilisateurs, un système de dépôt automatique (Stripe, Crypto...), ou modifier le design selon vos envies. Le code est 100% ouvert.
            </div>
        </div>
    </div>

    <?php if($msg): ?><div class="p-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-xl flex items-center gap-3 animate-pulse"><i class="fas fa-check-circle text-xl"></i> <?php echo $msg; ?></div><?php endif; ?>
    <?php if($error): ?><div class="p-4 bg-red-500/10 border border-red-500/20 text-red-400 rounded-xl flex items-center gap-3"><i class="fas fa-exclamation-triangle text-xl"></i> <?php echo $error; ?></div><?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <div class="bg-panel p-6 rounded-2xl border border-slate-700 lg:col-span-1 shadow-lg relative overflow-hidden group">
            <h2 class="text-lg font-bold text-white mb-4">Connexion API (BlessPanel)</h2>
            <p class="text-xs text-slate-400 mb-4">Liez ce site à votre compte fournisseur pour activer les commandes.</p>
            
            <form method="POST" class="flex flex-col gap-3">
                <div class="relative">
                    <i class="fas fa-key absolute left-3 top-3.5 text-slate-500"></i>
                    <input type="text" name="api_key" value="<?php echo htmlspecialchars($currentKey); ?>" placeholder="Votre Clé API..." class="w-full bg-dark border border-slate-600 rounded-xl pl-10 pr-4 py-3 text-sm text-white focus:border-primary outline-none transition">
                </div>
                <button name="save_key" class="bg-primary hover:bg-indigo-600 px-4 py-3 rounded-xl text-white text-sm font-bold transition shadow-lg shadow-primary/20">
                    <i class="fas fa-link mr-1"></i> Connecter
                </button>
            </form>
            
            <div class="mt-6 pt-4 border-t border-slate-700">
                <form method="POST">
                    <button type="submit" name="import_services" class="w-full text-xs bg-slate-700 hover:bg-slate-600 text-white px-3 py-3 rounded-xl transition flex items-center justify-center gap-2">
                        <i class="fas fa-cloud-download-alt"></i> Synchroniser le Catalogue
                    </button>
                </form>
            </div>
        </div>

        <div class="bg-panel rounded-2xl border border-slate-700 overflow-hidden shadow-lg lg:col-span-2 flex flex-col">
            <div class="p-5 border-b border-slate-700 flex justify-between items-center bg-slate-800/50">
                <h2 class="text-lg font-bold text-white flex items-center gap-2"><i class="fas fa-cubes text-primary"></i> Vos Services</h2>
                <span class="text-xs bg-primary/20 text-primary px-3 py-1 rounded-full font-bold"><?php echo count($services); ?> services</span>
            </div>
            
            <div class="overflow-y-auto max-h-[600px]">
                <table class="w-full text-left border-collapse">
                    <thead class="sticky top-0 bg-panel z-10 shadow-sm">
                        <tr class="text-slate-400 text-xs uppercase font-bold tracking-wider">
                            <th class="p-4">Service</th>
                            <th class="p-4 text-center">Achat (Coût)</th>
                            <th class="p-4 text-center">Vente (Client)</th>
                            <th class="p-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700 text-sm">
                        <?php foreach($services as $s): 
                            $buy = $s['provider_rate'] ?? ($s['rate'] * 0.66);
                            $sell = $s['rate'];
                            $profit = $sell - $buy;
                            $iconData = getCatIcon($s['category']);
                        ?>
                        <tr class="hover:bg-white/5 transition group">
                            <td class="p-4">
                                <div class="flex items-start gap-3">
                                    <div class="mt-1 <?php echo $iconData[1]; ?> text-lg"><i class="<?php echo $iconData[0]; ?>"></i></div>
                                    <div>
                                        <div class="font-bold text-white line-clamp-1" title="<?php echo htmlspecialchars($s['name']); ?>"><?php echo htmlspecialchars($s['name']); ?></div>
                                        <div class="text-[10px] text-slate-500 mt-0.5 uppercase font-bold"><?php echo htmlspecialchars($s['category']); ?> • ID: <?php echo $s['provider_service_id']; ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="p-4 text-center font-mono text-slate-500 text-xs">$<?php echo number_format($buy, 3); ?></td>
                            <td class="p-4 text-center">
                                <div class="font-mono text-emerald-400 font-bold">$<?php echo number_format($sell, 3); ?></div>
                                <div class="text-[10px] text-slate-500">+<?php echo number_format($profit, 3); ?>$</div>
                            </td>
                            <td class="p-4 text-right">
                                <button onclick='openEdit(<?php echo json_encode($s); ?>)' class="w-8 h-8 rounded-lg bg-white/5 hover:bg-primary hover:text-white text-slate-400 transition flex items-center justify-center">
                                    <i class="fas fa-pen"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($services)): ?>
                            <tr><td colspan="4" class="p-8 text-center text-slate-500">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-box-open text-4xl mb-2 opacity-50"></i>
                                    Aucun service. Configurez votre API et cliquez sur "Synchroniser".
                                </div>
                            </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<div id="editModal" class="modal fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="modal-content bg-panel border border-slate-600 w-full max-w-lg rounded-2xl shadow-2xl relative overflow-hidden">
        
        <div class="bg-slate-800/50 p-6 border-b border-slate-700 flex justify-between items-center">
            <h3 class="text-lg font-bold text-white flex items-center gap-2"><i class="fas fa-edit text-primary"></i> Modifier le Service</h3>
            <button onclick="closeModal()" class="text-slate-400 hover:text-white transition"><i class="fas fa-times text-xl"></i></button>
        </div>

        <form method="POST" class="p-6 space-y-5">
            <input type="hidden" name="update_service" value="1">
            <input type="hidden" name="id" id="edit_id">
            
            <div>
                <label class="text-xs font-bold text-slate-400 uppercase mb-1 ml-1 block">Titre (Affiché au client)</label>
                <input type="text" name="name" id="edit_name" class="w-full bg-dark border border-slate-600 rounded-xl px-4 py-3 text-white focus:border-primary outline-none transition text-sm">
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                
                <div class="relative group">
                    <label class="text-xs font-bold text-emerald-400 uppercase mb-1 ml-1 block flex justify-between">
                        Votre Prix
                        <i class="fas fa-info-circle cursor-help" title="Prix affiché sur votre boutique"></i>
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-emerald-500 font-bold">$</span>
                        <input type="number" step="0.0001" name="rate" id="edit_rate" oninput="calcProfit()" class="w-full bg-dark border border-emerald-500/50 rounded-xl pl-7 pr-3 py-3 text-emerald-400 font-mono font-bold focus:border-emerald-400 outline-none transition">
                    </div>
                    <p class="text-[10px] text-slate-500 mt-1 ml-1">Ce que vos clients paient.</p>
                </div>

                <div>
                    <label class="text-xs font-bold text-slate-500 uppercase mb-1 ml-1 block flex justify-between">
                        Coût (BlessPanel)
                        <i class="fas fa-info-circle cursor-help" title="Ce que nous vous débitons"></i>
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-600 font-bold">$</span>
                        <input type="text" id="edit_cost" disabled class="w-full bg-slate-800 border border-slate-700 rounded-xl pl-7 pr-3 py-3 text-slate-500 font-mono cursor-not-allowed">
                    </div>
                    <p class="text-[10px] text-slate-500 mt-1 ml-1">Coût fixe d'achat.</p>
                </div>
            </div>

            <div class="bg-primary/10 border border-primary/20 rounded-xl p-3 flex justify-between items-center">
                <span class="text-xs text-primary font-bold uppercase">Votre Marge Nette :</span>
                <span id="profit_display" class="font-mono font-bold text-white text-lg">Calcul...</span>
            </div>

            <div>
                <label class="text-xs font-bold text-slate-400 uppercase mb-1 ml-1 block">Description du Service</label>
                <textarea name="description" id="edit_desc" rows="4" class="w-full bg-dark border border-slate-600 rounded-xl px-4 py-3 text-slate-300 text-sm focus:border-primary outline-none transition placeholder-slate-600 resize-none" placeholder="Ex: Qualité réelle, garantie 30 jours, pas de perte..."></textarea>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="button" onclick="closeModal()" class="flex-1 py-3 rounded-xl bg-white/5 hover:bg-white/10 text-slate-300 font-bold text-sm transition">Annuler</button>
                <button type="submit" class="flex-1 py-3 rounded-xl bg-primary hover:bg-indigo-600 text-white font-bold text-sm transition shadow-lg shadow-primary/20 flex items-center justify-center gap-2">
                    <i class="fas fa-save"></i> Enregistrer
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openEdit(service) {
        document.getElementById('edit_id').value = service.id;
        document.getElementById('edit_name').value = service.name;
        document.getElementById('edit_rate').value = service.rate;
        
        // Coût fournisseur
        let cost = service.provider_rate ? parseFloat(service.provider_rate) : (service.rate * 0.66);
        document.getElementById('edit_cost').value = cost.toFixed(4);
        
        // Description
        document.getElementById('edit_desc').value = service.description || '';
        
        calcProfit(); // Lance le calcul immédiatement
        document.getElementById('editModal').classList.add('active');
    }

    function closeModal() {
        document.getElementById('editModal').classList.remove('active');
    }

    function calcProfit() {
        let sellPrice = parseFloat(document.getElementById('edit_rate').value) || 0;
        let buyPrice = parseFloat(document.getElementById('edit_cost').value) || 0;
        let profit = sellPrice - buyPrice;
        let percent = buyPrice > 0 ? ((profit / buyPrice) * 100).toFixed(0) : 0;
        
        let el = document.getElementById('profit_display');
        if(profit >= 0) {
            el.innerHTML = `+$${profit.toFixed(4)} <span class="text-xs bg-emerald-500/20 px-2 py-0.5 rounded ml-2">+${percent}%</span>`;
            el.className = "font-mono font-bold text-emerald-400 text-lg flex items-center";
        } else {
            el.innerHTML = `-$${Math.abs(profit).toFixed(4)} <span class="text-xs bg-red-500/20 px-2 py-0.5 rounded ml-2">Perte</span>`;
            el.className = "font-mono font-bold text-red-400 text-lg flex items-center";
        }
    }
</script>

</body>
</html>