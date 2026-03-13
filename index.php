<?php
require 'config.php';

$msg = "";
$orderId = "";

// Helper pour les icônes (Logique visuelle)
function getServiceIcon($cat) {
    $cat = strtolower($cat);
    if (strpos($cat, 'instagram') !== false) return ['fab fa-instagram', 'text-pink-500', 'bg-pink-500/10'];
    if (strpos($cat, 'facebook') !== false) return ['fab fa-facebook-f', 'text-blue-600', 'bg-blue-600/10'];
    if (strpos($cat, 'tiktok') !== false) return ['fab fa-tiktok', 'text-white', 'bg-white/10'];
    if (strpos($cat, 'youtube') !== false) return ['fab fa-youtube', 'text-red-500', 'bg-red-500/10'];
    if (strpos($cat, 'twitter') !== false || strpos($cat, 'x ') !== false) return ['fab fa-x-twitter', 'text-slate-300', 'bg-slate-500/10'];
    if (strpos($cat, 'telegram') !== false) return ['fab fa-telegram-plane', 'text-blue-400', 'bg-blue-400/10'];
    return ['fas fa-bolt', 'text-amber-400', 'bg-amber-400/10'];
}

// Récupération des services
if ($demoMode) {
    $services = $_SESSION['demo_services'] ?? [];
} else {
    try {
        $services = $pdo->query("SELECT * FROM services WHERE active = 1 ORDER BY category")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $services = [];
    }
}

// Grouper les services par catégorie pour le Modal
$groupedServices = [];
foreach ($services as $s) {
    $groupedServices[$s['category']][] = $s;
}

// --- TRAITEMENT COMMANDE ---
if (isset($_POST['order'])) {
    $serviceId = $_POST['service_id'];
    $link = $_POST['link'];
    $qty = $_POST['quantity'];

    // Recherche du service pour obtenir les infos
    $service = null;
    foreach ($services as $s) { if ($s['id'] == $serviceId) { $service = $s; break; } }

    if ($service) {
        if ($demoMode) {
            // --- SCÉNARIO DÉMO : ON SIMULE ---
            $msg = "demo_success";
            $orderId = rand(10000, 99999);
        } else {
            // --- SCÉNARIO PRODUCTION ---
            $apiResult = callBlessPanel('add', [
                'service' => $service['provider_service_id'],
                'link' => $link,
                'quantity' => $qty
            ]);

            if (isset($apiResult['order'])) {
                $price = ($qty/1000) * $service['rate'];
                $stmtInsert = $pdo->prepare("INSERT INTO orders (service_id, link, quantity, price, status, provider_order_id) VALUES (?, ?, ?, ?, ?, ?)");
                $stmtInsert->execute([$service['id'], $link, $qty, $price, 'Pending', $apiResult['order']]);
                
                $msg = "real_success";
                $orderId = $apiResult['order'];
            } else {
                $msg = "error";
                $errorText = $apiResult['error'] ?? "Erreur inconnue";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>SocialBooster - Boutique</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class', theme: { extend: { colors: { dark: '#020617', panel: '#0f172a', primary: '#6366f1' } } } }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .service-card:hover { transform: translateY(-2px); border-color: #6366f1; }
        .modal-enter { animation: slideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        @keyframes slideUp { from { transform: translateY(100%); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    </style>
</head>
<body class="bg-dark text-slate-200 font-sans min-h-screen flex flex-col relative overflow-x-hidden selection:bg-primary/30">

    <nav class="border-b border-white/5 bg-panel/80 backdrop-blur-md sticky top-0 z-40">
        <div class="max-w-md mx-auto px-6 h-16 flex items-center justify-between">
            <div class="font-bold text-lg tracking-tight text-white flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-primary to-purple-500 flex items-center justify-center shadow-lg shadow-primary/20">
                    <i class="fas fa-fire text-white text-sm"></i>
                </div>
                SocialBooster
            </div>
            <?php if($demoMode): ?>
                <span class="text-[10px] font-bold bg-yellow-500/10 text-yellow-500 px-2 py-1 rounded border border-yellow-500/20">
                    DÉMO
                </span>
            <?php endif; ?>
        </div>
    </nav>

    <main class="flex-grow px-4 py-6 w-full max-w-md mx-auto">
        
        <div class="bg-gradient-to-r from-blue-600/10 to-purple-600/10 border border-blue-500/20 rounded-2xl p-5 mb-8 text-center relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-3 opacity-10 text-5xl text-blue-500"><i class="fas fa-code-branch"></i></div>
            
            <div class="relative z-10">
                <span class="inline-block py-1 px-3 rounded-full bg-blue-500/20 text-blue-400 text-[10px] font-bold uppercase tracking-wider mb-2 border border-blue-500/30">
                    Note pour les développeurs
                </span>
                
                <h2 class="text-lg font-bold text-white mb-2">
                    Ceci est le site de vos clients !
                </h2>
                
                <p class="text-xs text-slate-300 leading-relaxed mb-4">
                    Les prix affichés ici incluent une marge d'exemple (ex: <b>+20%</b> ou <b>+50%</b>).
                    <br>En tant qu'admin, vous définissez librement ce pourcentage.
                </p>

                <a href="admin.php" class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold rounded-xl transition shadow-lg shadow-blue-600/20">
                    <i class="fas fa-user-shield"></i> Voir le Panel Admin
                </a>
            </div>
        </div>

        <div class="text-center mb-8">
            <h1 class="text-3xl font-extrabold text-white mb-2">Passez votre <br><span class="text-transparent bg-clip-text bg-gradient-to-r from-primary to-purple-400">Commande</span></h1>
            <p class="text-sm text-slate-400">Sélectionnez un service pour commencer.</p>
        </div>

        <?php if($msg == 'demo_success'): ?>
            <div class="bg-blue-500/10 border border-blue-500/20 rounded-2xl p-5 mb-6 text-center animate-pulse">
                <div class="w-12 h-12 bg-blue-500/20 text-blue-400 rounded-full flex items-center justify-center mx-auto mb-3 text-xl">
                    <i class="fas fa-info"></i>
                </div>
                <h3 class="text-white font-bold text-lg mb-1">Simulation Réussie</h3>
                <p class="text-sm text-blue-300 mb-2">Cette commande n'a pas été exécutée car le site est en <b>Mode Démo</b>.</p>
                <p class="text-xs text-slate-500">En production, la commande serait envoyée instantanément via l'API.</p>
            </div>
        <?php elseif($msg == 'real_success'): ?>
            <div class="bg-emerald-500/10 border border-emerald-500/20 rounded-2xl p-5 mb-6 text-center">
                <div class="w-12 h-12 bg-emerald-500/20 text-emerald-400 rounded-full flex items-center justify-center mx-auto mb-3 text-xl"><i class="fas fa-check"></i></div>
                <h3 class="text-white font-bold text-lg">Commande #<?php echo $orderId; ?> Reçue !</h3>
                <p class="text-sm text-slate-400">Livraison en cours.</p>
            </div>
        <?php elseif($msg == 'error'): ?>
            <div class="bg-red-500/10 border border-red-500/20 rounded-2xl p-5 mb-6 text-center">
                <h3 class="text-red-400 font-bold">Erreur</h3>
                <p class="text-sm text-slate-400"><?php echo $errorText; ?></p>
            </div>
        <?php endif; ?>

        <div class="bg-panel border border-white/10 rounded-3xl p-6 shadow-2xl relative">
            <form method="POST" class="space-y-6">
                <input type="hidden" name="service_id" id="input_service_id" required>
                
                <div class="space-y-2">
                    <label class="text-xs font-bold text-slate-400 uppercase ml-1">Service</label>
                    <div onclick="openServicesModal()" class="w-full bg-dark border border-slate-600 hover:border-primary text-white rounded-xl px-4 py-4 flex justify-between items-center cursor-pointer transition group">
                        <span id="selected_service_name" class="text-sm text-slate-400 truncate">Cliquez pour choisir...</span>
                        <i class="fas fa-chevron-right text-slate-600 group-hover:text-primary transition"></i>
                    </div>
                    
                    <div id="service_info_box" class="hidden mt-3 space-y-3">
                        <div class="flex gap-3 text-xs">
                            <div class="bg-white/5 border border-white/5 px-3 py-1.5 rounded-lg text-slate-300">
                                Min: <span id="info_min" class="font-bold text-white">100</span>
                            </div>
                            <div class="bg-white/5 border border-white/5 px-3 py-1.5 rounded-lg text-slate-300">
                                Max: <span id="info_max" class="font-bold text-white">10000</span>
                            </div>
                        </div>
                        <div class="text-xs text-slate-400 bg-primary/5 p-3 rounded-xl border border-primary/10 leading-relaxed" id="info_desc"></div>
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="text-xs font-bold text-slate-400 uppercase ml-1">Lien</label>
                    <div class="relative">
                        <input type="url" name="link" placeholder="https://..." class="w-full bg-dark border border-slate-600 text-white rounded-xl pl-10 pr-4 py-4 focus:border-primary outline-none transition text-sm">
                        <i class="fas fa-link absolute left-4 top-1/2 -translate-y-1/2 text-slate-500"></i>
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="text-xs font-bold text-slate-400 uppercase ml-1">Quantité</label>
                    <div class="relative">
                        <input type="number" name="quantity" id="quantity" placeholder="Ex: 1000" class="w-full bg-dark border border-slate-600 text-white rounded-xl pl-10 pr-4 py-4 focus:border-primary outline-none transition text-sm" oninput="calcPrice()">
                        <i class="fas fa-sort-numeric-up absolute left-4 top-1/2 -translate-y-1/2 text-slate-500"></i>
                    </div>
                    <div class="text-right text-xs text-slate-400 font-mono mt-1">
                        Coût total : <span id="totalPrice" class="text-white font-bold text-base">$0.00</span>
                    </div>
                </div>

                <button type="submit" name="order" class="w-full bg-primary hover:bg-indigo-600 text-white font-bold py-4 rounded-xl transition-all shadow-lg shadow-primary/25 flex items-center justify-center gap-2 mt-4 group">
                    <span><?php echo $demoMode ? 'Simuler la Commande' : 'Commander'; ?></span> 
                    <i class="fas fa-arrow-right transform group-hover:translate-x-1 transition-transform"></i>
                </button>
            </form>
        </div>
    </main>

    <div id="servicesModal" class="fixed inset-0 z-50 bg-black/90 backdrop-blur-sm hidden flex-col">
        <div class="flex items-center justify-between p-4 border-b border-white/10 bg-panel/90 backdrop-blur">
            <h2 class="text-white font-bold text-lg">Services</h2>
            <button onclick="closeServicesModal()" class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center text-white hover:bg-white/20 transition"><i class="fas fa-times"></i></button>
        </div>

        <div class="p-4 bg-panel border-b border-white/5">
            <div class="relative">
                <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-500"></i>
                <input type="text" id="serviceSearch" onkeyup="filterServices()" placeholder="Rechercher (ex: Instagram)..." class="w-full bg-dark border border-slate-700 text-white rounded-xl pl-10 pr-4 py-3 text-sm focus:border-primary outline-none">
            </div>
        </div>

        <div class="flex-grow overflow-y-auto p-4 space-y-6" id="servicesList">
            <?php foreach($groupedServices as $catName => $catServices): 
                $iconData = getServiceIcon($catName);
            ?>
            <div class="service-category">
                <div class="flex items-center gap-2 mb-3 text-white font-bold text-sm uppercase tracking-wide opacity-80 sticky top-0 bg-black/50 backdrop-blur py-2 z-10">
                    <i class="<?php echo $iconData[0] . ' ' . $iconData[1]; ?>"></i> <?php echo $catName; ?>
                </div>
                <div class="space-y-2">
                    <?php foreach($catServices as $s): ?>
                    <div onclick='selectService(<?php echo json_encode($s); ?>)' class="service-item bg-white/5 border border-white/5 rounded-xl p-4 cursor-pointer hover:bg-white/10 transition active:scale-95" data-search="<?php echo strtolower($s['name'] . ' ' . $s['category']); ?>">
                        <div class="flex justify-between items-start gap-3">
                            <div class="flex-1">
                                <h4 class="text-sm font-semibold text-white leading-tight mb-1"><?php echo $s['name']; ?></h4>
                                <div class="flex items-center gap-2 text-[10px] text-slate-400">
                                    <span class="bg-black/30 px-2 py-0.5 rounded">ID: <?php echo $s['provider_service_id']; ?></span>
                                    <span>Min: <?php echo $s['min']; ?></span>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-emerald-400 font-mono font-bold text-sm">$<?php echo $s['rate']; ?></div>
                                <div class="text-[10px] text-slate-500">/1000</div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
            
            <div id="noResults" class="hidden text-center py-10 text-slate-500">
                <i class="fas fa-ghost text-3xl mb-2"></i><br>Aucun service trouvé.
            </div>
        </div>
    </div>

    <script>
        let currentRate = 0;

        function openServicesModal() {
            document.getElementById('servicesModal').classList.remove('hidden');
            document.getElementById('servicesModal').classList.add('flex', 'modal-enter');
            document.body.style.overflow = 'hidden'; 
        }

        function closeServicesModal() {
            document.getElementById('servicesModal').classList.add('hidden');
            document.getElementById('servicesModal').classList.remove('flex', 'modal-enter');
            document.body.style.overflow = '';
        }

        function selectService(service) {
            document.getElementById('input_service_id').value = service.id;
            document.getElementById('selected_service_name').innerText = service.name;
            document.getElementById('selected_service_name').classList.remove('text-slate-400');
            document.getElementById('selected_service_name').classList.add('text-white', 'font-bold');
            
            document.getElementById('service_info_box').classList.remove('hidden');
            document.getElementById('info_min').innerText = service.min;
            document.getElementById('info_max').innerText = service.max;
            document.getElementById('info_desc').innerText = service.description || "Aucune description disponible.";
            
            currentRate = parseFloat(service.rate);
            calcPrice();
            closeServicesModal();
        }

        function calcPrice() {
            const qty = parseInt(document.getElementById('quantity').value) || 0;
            if(qty > 0 && currentRate > 0) {
                const total = (qty / 1000) * currentRate;
                document.getElementById('totalPrice').innerText = "$" + total.toFixed(4);
                document.getElementById('totalPrice').classList.add('text-emerald-400');
            } else {
                document.getElementById('totalPrice').innerText = "$0.00";
                document.getElementById('totalPrice').classList.remove('text-emerald-400');
            }
        }

        function filterServices() {
            const query = document.getElementById('serviceSearch').value.toLowerCase();
            const items = document.querySelectorAll('.service-item');
            let visibleCount = 0;

            items.forEach(item => {
                if(item.getAttribute('data-search').includes(query)) {
                    item.style.display = 'block';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });

            document.querySelectorAll('.service-category').forEach(cat => {
                const visibleItems = cat.querySelectorAll('.service-item[style="display: block;"]');
                cat.style.display = (visibleItems.length > 0 || query === "") ? 'block' : 'none';
            });

            document.getElementById('noResults').style.display = (visibleCount === 0) ? 'block' : 'none';
        }
    </script>
</body>
</html>