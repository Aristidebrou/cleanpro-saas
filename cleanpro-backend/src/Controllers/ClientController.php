<?php

declare(strict_types=1);

namespace CleanPro\Controllers;

use CleanPro\Models\Client;
use CleanPro\Models\User;
use CleanPro\Utils\Security;
use CleanPro\Utils\Response;

class ClientController extends Controller
{
    private Client $clientModel;
    private User $userModel;

    public function __construct()
    {
        $this->clientModel = new Client();
        $this->userModel = new User();
    }

    /**
     * Liste des clients
     */
    public function index(): void
    {
        $this->requireAuth();

        $filters = [];
        
        if (isset($_GET['billing_type'])) {
            $filters['billing_type'] = $_GET['billing_type'];
        }
        
        if (isset($_GET['status'])) {
            $filters['status'] = $_GET['status'];
        }

        $clients = $this->clientModel->getClientsWithDetails();

        Response::success(['clients' => $clients]);
    }

    /**
     * Détail d'un client
     */
    public function show(int $id): void
    {
        $this->requireAuth();

        $client = $this->clientModel->getClientWithDetails($id);

        if (!$client) {
            Response::notFound('Client non trouvé');
        }

        Response::success(['client' => $client]);
    }

    /**
     * Création d'un client
     */
    public function create(): void
    {
        $authUser = $this->requireAuth();
        $this->requireRole($authUser, 'admin');

        $data = $this->getJsonInput();

        // Validation
        $errors = $this->validateRequired($data, ['company_name', 'email', 'contact_name']);
        if ($errors) {
            Response::validationError($errors);
        }

        if (!Security::validateEmail($data['email'])) {
            Response::validationError(['email' => 'Email invalide']);
        }

        // Création de l'utilisateur client si mot de passe fourni
        $userId = null;
        if (!empty($data['password'])) {
            if ($this->userModel->emailExists($data['email'])) {
                Response::error('Cet email est déjà utilisé', 409);
            }

            $userId = $this->userModel->createUser([
                'email' => Security::sanitize($data['email']),
                'password' => $data['password'],
                'first_name' => Security::sanitize($data['contact_first_name'] ?? $data['contact_name']),
                'last_name' => Security::sanitize($data['contact_last_name'] ?? ''),
                'role' => User::ROLE_CLIENT,
                'phone' => $data['contact_phone'] ?? null
            ]);
        }

        // Création du client
        $clientData = [
            'user_id' => $userId,
            'company_name' => Security::sanitize($data['company_name']),
            'contact_name' => Security::sanitize($data['contact_name']),
            'contact_email' => Security::sanitize($data['email']),
            'contact_phone' => $data['contact_phone'] ?? null,
            'address' => Security::sanitize($data['address'] ?? ''),
            'postal_code' => $data['postal_code'] ?? null,
            'city' => Security::sanitize($data['city'] ?? ''),
            'siret' => $data['siret'] ?? null,
            'vat_number' => $data['vat_number'] ?? null,
            'billing_type' => $data['billing_type'] ?? Client::BILLING_TYPE_ONE_TIME,
            'monthly_quota' => $data['monthly_quota'] ?? null,
            'monthly_amount' => $data['monthly_amount'] ?? null,
            'notes' => $data['notes'] ?? null
        ];

        $clientId = $this->clientModel->createClient($clientData);

        Response::success(['client_id' => $clientId], 'Client créé avec succès', 201);
    }

    /**
     * Mise à jour d'un client
     */
    public function update(int $id): void
    {
        $authUser = $this->requireAuth();
        $this->requireRole($authUser, 'admin');

        $client = $this->clientModel->find($id);
        if (!$client) {
            Response::notFound('Client non trouvé');
        }

        $data = $this->getJsonInput();
        $updateData = [];

        $fields = [
            'company_name', 'contact_name', 'contact_email', 'contact_phone',
            'address', 'postal_code', 'city', 'siret', 'vat_number',
            'billing_type', 'monthly_quota', 'monthly_amount', 'notes', 'status'
        ];

        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = is_string($data[$field]) 
                    ? Security::sanitize($data[$field]) 
                    : $data[$field];
            }
        }

        if (empty($updateData)) {
            Response::error('Aucune donnée à mettre à jour', 400);
        }

        $updateData['updated_at'] = date('Y-m-d H:i:s');

        $this->clientModel->update($id, $updateData);

        Response::success([], 'Client mis à jour');
    }

    /**
     * Suppression d'un client
     */
    public function delete(int $id): void
    {
        $authUser = $this->requireAuth();
        $this->requireRole($authUser, 'admin');

        $client = $this->clientModel->find($id);
        if (!$client) {
            Response::notFound('Client non trouvé');
        }

        $this->clientModel->delete($id);

        Response::success([], 'Client supprimé');
    }

    /**
     * Statistiques clients
     */
    public function statistics(): void
    {
        $this->requireAuth();

        $stats = $this->clientModel->getStatistics();

        Response::success(['statistics' => $stats]);
    }

    /**
     * Espace client - Profil
     */
    public function myProfile(): void
    {
        $user = $this->requireAuth();

        if ($user['role'] !== 'client') {
            Response::forbidden('Accès réservé aux clients');
        }

        // Récupération du client associé à l'utilisateur
        $clients = $this->clientModel->where(['user_id' => $user['user_id']], [], 1);
        
        if (empty($clients)) {
            Response::notFound('Profil client non trouvé');
        }

        $client = $this->clientModel->getClientWithDetails($clients[0]['id']);

        Response::success(['client' => $client]);
    }

    /**
     * Espace client - Interventions
     */
    public function myInterventions(): void
    {
        $user = $this->requireAuth();

        if ($user['role'] !== 'client') {
            Response::forbidden('Accès réservé aux clients');
        }

        $clients = $this->clientModel->where(['user_id' => $user['user_id']], [], 1);
        
        if (empty($clients)) {
            Response::notFound('Profil client non trouvé');
        }

        $interventionModel = new \CleanPro\Models\Intervention();
        $interventions = $interventionModel->getInterventionsWithDetails([
            'client_id' => $clients[0]['id']
        ]);

        Response::success(['interventions' => $interventions]);
    }

    /**
     * Espace client - Factures
     */
    public function myInvoices(): void
    {
        $user = $this->requireAuth();

        if ($user['role'] !== 'client') {
            Response::forbidden('Accès réservé aux clients');
        }

        $clients = $this->clientModel->where(['user_id' => $user['user_id']], [], 1);
        
        if (empty($clients)) {
            Response::notFound('Profil client non trouvé');
        }

        $invoiceModel = new \CleanPro\Models\Invoice();
        $invoices = $invoiceModel->getInvoicesWithDetails([
            'client_id' => $clients[0]['id']
        ]);

        Response::success(['invoices' => $invoices]);
    }

    /**
     * Espace client - Demande de devis
     */
    public function requestQuote(): void
    {
        $user = $this->requireAuth();

        if ($user['role'] !== 'client') {
            Response::forbidden('Accès réservé aux clients');
        }

        $data = $this->getJsonInput();

        $errors = $this->validateRequired($data, ['service_type', 'description']);
        if ($errors) {
            Response::validationError($errors);
        }

        $clients = $this->clientModel->where(['user_id' => $user['user_id']], [], 1);
        
        if (empty($clients)) {
            Response::notFound('Profil client non trouvé');
        }

        // Création de la demande de devis
        $quoteRequest = [
            'client_id' => $clients[0]['id'],
            'service_type' => Security::sanitize($data['service_type']),
            'description' => Security::sanitize($data['description']),
            'preferred_date' => $data['preferred_date'] ?? null,
            'estimated_budget' => $data['estimated_budget'] ?? null,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ];

        // TODO: Sauvegarder dans une table quote_requests

        Response::success([], 'Demande de devis envoyée avec succès');
    }
}
