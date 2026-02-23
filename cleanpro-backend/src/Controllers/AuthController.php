<?php

declare(strict_types=1);

namespace CleanPro\Controllers;

use CleanPro\Models\User;
use CleanPro\Utils\Security;
use CleanPro\Utils\JWTAuth;
use CleanPro\Utils\Response;

class AuthController extends Controller
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    /**
     * Connexion
     */
    public function login(): void
    {
        $data = $this->getJsonInput();

        // Validation
        $errors = $this->validateRequired($data, ['email', 'password']);
        if ($errors) {
            Response::validationError($errors);
        }

        $email = Security::sanitize($data['email']);
        $password = $data['password'];

        // Vérification du rate limiting
        if (!Security::checkRateLimit($email, 5, 300)) {
            Response::error('Trop de tentatives. Veuillez réessayer dans 5 minutes.', 429);
        }

        // Authentification
        $user = $this->userModel->authenticate($email, $password);

        if (!$user) {
            Response::error('Email ou mot de passe incorrect', 401);
        }

        if (!$user['is_active']) {
            Response::error('Compte désactivé. Contactez l\'administrateur.', 403);
        }

        // Génération du token JWT
        $token = JWTAuth::generateToken([
            'user_id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name']
        ]);

        // Génération du token CSRF pour les actions sensibles
        $csrfToken = Security::generateCsrfToken();

        Response::success([
            'user' => $user,
            'token' => $token,
            'csrf_token' => $csrfToken
        ], 'Connexion réussie');
    }

    /**
     * Inscription (admin uniquement)
     */
    public function register(): void
    {
        $authUser = $this->requireAuth();
        $this->requireRole($authUser, 'admin');

        $data = $this->getJsonInput();

        // Validation
        $errors = $this->validateRequired($data, ['email', 'password', 'first_name', 'last_name', 'role']);
        if ($errors) {
            Response::validationError($errors);
        }

        // Validation email
        if (!Security::validateEmail($data['email'])) {
            Response::validationError(['email' => 'Email invalide']);
        }

        // Vérification si l'email existe déjà
        if ($this->userModel->emailExists($data['email'])) {
            Response::error('Cet email est déjà utilisé', 409);
        }

        // Validation du mot de passe
        if (strlen($data['password']) < 8) {
            Response::validationError(['password' => 'Le mot de passe doit contenir au moins 8 caractères']);
        }

        // Création de l'utilisateur
        $userData = [
            'email' => Security::sanitize($data['email']),
            'password' => $data['password'],
            'first_name' => Security::sanitize($data['first_name']),
            'last_name' => Security::sanitize($data['last_name']),
            'role' => $data['role'],
            'phone' => isset($data['phone']) ? Security::sanitize($data['phone']) : null,
            'is_active' => $data['is_active'] ?? true
        ];

        $userId = $this->userModel->createUser($userData);

        Response::success(['user_id' => $userId], 'Utilisateur créé avec succès', 201);
    }

    /**
     * Rafraîchissement du token
     */
    public function refresh(): void
    {
        $data = $this->getJsonInput();

        if (empty($data['token'])) {
            Response::error('Token manquant', 400);
        }

        $newToken = JWTAuth::refreshToken($data['token']);

        if (!$newToken) {
            Response::unauthorized('Token invalide ou expiré');
        }

        Response::success(['token' => $newToken], 'Token rafraîchi');
    }

    /**
     * Récupération du profil
     */
    public function profile(): void
    {
        $user = $this->requireAuth();
        
        $userData = $this->userModel->find($user['user_id']);
        
        if (!$userData) {
            Response::notFound('Utilisateur non trouvé');
        }

        unset($userData['password_hash']);

        Response::success(['user' => $userData]);
    }

    /**
     * Mise à jour du profil
     */
    public function updateProfile(): void
    {
        $user = $this->requireAuth();
        $data = $this->getJsonInput();

        $updateData = [];

        if (isset($data['first_name'])) {
            $updateData['first_name'] = Security::sanitize($data['first_name']);
        }

        if (isset($data['last_name'])) {
            $updateData['last_name'] = Security::sanitize($data['last_name']);
        }

        if (isset($data['phone'])) {
            $updateData['phone'] = Security::sanitize($data['phone']);
        }

        if (empty($updateData)) {
            Response::error('Aucune donnée à mettre à jour', 400);
        }

        $updateData['updated_at'] = date('Y-m-d H:i:s');

        $this->userModel->update($user['user_id'], $updateData);

        Response::success([], 'Profil mis à jour');
    }

    /**
     * Changement de mot de passe
     */
    public function changePassword(): void
    {
        $user = $this->requireAuth();
        $data = $this->getJsonInput();

        $errors = $this->validateRequired($data, ['current_password', 'new_password']);
        if ($errors) {
            Response::validationError($errors);
        }

        // Vérification du mot de passe actuel
        $userData = $this->userModel->find($user['user_id']);
        if (!Security::verifyPassword($data['current_password'], $userData['password_hash'])) {
            Response::error('Mot de passe actuel incorrect', 400);
        }

        // Validation du nouveau mot de passe
        if (strlen($data['new_password']) < 8) {
            Response::validationError(['new_password' => 'Le mot de passe doit contenir au moins 8 caractères']);
        }

        $this->userModel->changePassword($user['user_id'], $data['new_password']);

        Response::success([], 'Mot de passe changé avec succès');
    }

    /**
     * Déconnexion
     */
    public function logout(): void
    {
        // Invalider le token côté client
        // Côté serveur, on peut ajouter le token à une liste noire si nécessaire
        Response::success([], 'Déconnexion réussie');
    }

    /**
     * Liste des utilisateurs (admin uniquement)
     */
    public function listUsers(): void
    {
        $authUser = $this->requireAuth();
        $this->requireRole($authUser, 'admin');

        $users = $this->userModel->all(['created_at' => 'DESC']);
        
        // Supprimer les hashes de mot de passe
        foreach ($users as &$user) {
            unset($user['password_hash']);
        }

        Response::success(['users' => $users]);
    }

    /**
     * Récupération des agents
     */
    public function getAgents(): void
    {
        $this->requireAuth();

        $agents = $this->userModel->getActiveAgents();
        
        foreach ($agents as &$agent) {
            unset($agent['password_hash']);
        }

        Response::success(['agents' => $agents]);
    }
}
