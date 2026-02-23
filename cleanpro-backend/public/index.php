<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Chargement des variables d'environnement
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

// Configuration des headers CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
header('Content-Type: application/json; charset=utf-8');

// Gestion des requêtes OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Récupération de la route
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = trim($uri, '/');
$method = $_SERVER['REQUEST_METHOD'];

// Router simple
$routes = [
    // Auth
    'POST /api/auth/login' => ['AuthController', 'login'],
    'POST /api/auth/register' => ['AuthController', 'register'],
    'POST /api/auth/refresh' => ['AuthController', 'refresh'],
    'POST /api/auth/logout' => ['AuthController', 'logout'],
    'GET /api/auth/profile' => ['AuthController', 'profile'],
    'PUT /api/auth/profile' => ['AuthController', 'updateProfile'],
    'POST /api/auth/change-password' => ['AuthController', 'changePassword'],
    'GET /api/auth/agents' => ['AuthController', 'getAgents'],
    'GET /api/auth/users' => ['AuthController', 'listUsers'],

    // Dashboard
    'GET /api/dashboard' => ['DashboardController', 'index'],
    'GET /api/dashboard/profitability' => ['DashboardController', 'profitabilityReport'],

    // Clients
    'GET /api/clients' => ['ClientController', 'index'],
    'POST /api/clients' => ['ClientController', 'create'],
    'GET /api/clients/statistics' => ['ClientController', 'statistics'],
    'GET /api/clients/{id}' => ['ClientController', 'show'],
    'PUT /api/clients/{id}' => ['ClientController', 'update'],
    'DELETE /api/clients/{id}' => ['ClientController', 'delete'],

    // Espace Client
    'GET /api/client/profile' => ['ClientController', 'myProfile'],
    'GET /api/client/interventions' => ['ClientController', 'myInterventions'],
    'GET /api/client/invoices' => ['ClientController', 'myInvoices'],
    'POST /api/client/quote-request' => ['ClientController', 'requestQuote'],

    // Interventions
    'GET /api/interventions' => ['InterventionController', 'index'],
    'POST /api/interventions' => ['InterventionController', 'create'],
    'GET /api/interventions/statistics' => ['InterventionController', 'statistics'],
    'GET /api/interventions/upcoming' => ['InterventionController', 'upcoming'],
    'GET /api/interventions/schedule' => ['InterventionController', 'schedule'],
    'POST /api/interventions/check-conflicts' => ['InterventionController', 'checkConflicts'],
    'GET /api/interventions/{id}' => ['InterventionController', 'show'],
    'PUT /api/interventions/{id}' => ['InterventionController', 'update'],
    'POST /api/interventions/{id}/start' => ['InterventionController', 'start'],
    'POST /api/interventions/{id}/complete' => ['InterventionController', 'complete'],
    'POST /api/interventions/{id}/validate' => ['InterventionController', 'validate'],
    'POST /api/interventions/{id}/cancel' => ['InterventionController', 'cancel'],
    'GET /api/interventions/{id}/pdf' => ['InterventionController', 'generatePdf'],

    // Invoices
    'GET /api/invoices' => ['InvoiceController', 'index'],
    'POST /api/invoices' => ['InvoiceController', 'create'],
    'POST /api/invoices/from-intervention' => ['InvoiceController', 'createFromIntervention'],
    'GET /api/invoices/statistics' => ['InvoiceController', 'statistics'],
    'GET /api/invoices/overdue' => ['InvoiceController', 'overdue'],
    'GET /api/invoices/{id}' => ['InvoiceController', 'show'],
    'PUT /api/invoices/{id}' => ['InvoiceController', 'update'],
    'POST /api/invoices/{id}/send' => ['InvoiceController', 'send'],
    'POST /api/invoices/{id}/pay' => ['InvoiceController', 'markAsPaid'],
    'POST /api/invoices/{id}/cancel' => ['InvoiceController', 'cancel'],
    'GET /api/invoices/{id}/pdf' => ['InvoiceController', 'generatePdf'],
    'GET /api/invoices/{id}/download' => ['InvoiceController', 'downloadPdf'],
];

// Recherche de la route
$matchedRoute = null;
$params = [];

foreach ($routes as $route => $handler) {
    list($routeMethod, $routePath) = explode(' ', $route, 2);
    
    // Conversion du pattern en regex
    $pattern = preg_replace('/\{([^}]+)\}/', '([^/]+)', $routePath);
    $pattern = '#^' . $pattern . '$#';
    
    if ($method === $routeMethod && preg_match($pattern, '/api/' . $uri, $matches)) {
        $matchedRoute = $handler;
        array_shift($matches); // Supprime le match complet
        $params = $matches;
        break;
    }
}

// Exécution de la route
if ($matchedRoute) {
    try {
        $controllerName = 'CleanPro\\Controllers\\' . $matchedRoute[0];
        $methodName = $matchedRoute[1];
        
        $controller = new $controllerName();
        
        // Appel avec ou sans paramètres
        if (!empty($params)) {
            $controller->$methodName(...$params);
        } else {
            $controller->$methodName();
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erreur serveur',
            'error' => $_ENV['APP_ENV'] === 'development' ? $e->getMessage() : null
        ]);
    }
} else {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Route non trouvée'
    ]);
}
