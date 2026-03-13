<?php
// config.php - MODE HYBRIDE (SQL + SESSION)
// Ce fichier détecte automatiquement si une base de données est disponible.
// Sinon, il active le mode démonstration pour que le site fonctionne immédiatement.

session_start();

// Configuration BDD (À modifier par vos clients pour leur vrai site)
$host = 'localhost';
$db   = 'nom_de_la_base';
$user = 'root';
$pass = '';

$pdo = null;
$demoMode = false;

try {
    // Tentative de connexion (Timeout court pour ne pas ralentir la démo)
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 1
    ];
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass, $options);
} catch (PDOException $e) {
    // ÉCHEC = MODE DÉMO ACTIVÉ
    $demoMode = true;
    
    // On initialise des fausses données pour que la démo ne soit pas vide
    if (!isset($_SESSION['demo_services'])) {
        $_SESSION['demo_services'] = [
            [
                'id' => 1, 
                'provider_service_id' => 10, 
                'name' => 'Instagram Followers [HQ] - Garantie 30j', 
                'category' => 'Instagram', 
                'rate' => 1.50, // Prix de vente
                'provider_rate' => 0.80, // Prix d'achat (pour calcul profit)
                'min' => 100, 
                'max' => 5000,
                'description' => 'Démarrage instantané. Profils réels avec photo.'
            ],
            [
                'id' => 2, 
                'provider_service_id' => 25, 
                'name' => 'TikTok Likes [Instant]', 
                'category' => 'TikTok', 
                'rate' => 0.80, 
                'provider_rate' => 0.40,
                'min' => 50, 
                'max' => 10000,
                'description' => 'Likes mondiaux. Pas de perte.'
            ]
        ];
    }
    
    // Faux solde revendeur pour l'admin
    if (!isset($_SESSION['demo_balance'])) {
        $_SESSION['demo_balance'] = 50.00;
    }
}

// Fonction API unifiée (Simulée ou Réelle)
function callBlessPanel($action, $data = []) {
    global $pdo, $demoMode;
    
    // Récupération de la clé API
    $apiKey = '';
    if ($demoMode) {
        $apiKey = $_SESSION['demo_api_key'] ?? '';
    } else {
        $stmt = $pdo->query("SELECT provider_api_key FROM settings LIMIT 1");
        $apiKey = $stmt->fetchColumn();
    }

    // Appel réel vers BlessPanel
    $ch = curl_init('https://blesspanel.store/api/v2.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array_merge(['key' => $apiKey, 'action' => $action], $data)));
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}
?>