<?php

declare(strict_types=1);

namespace CleanPro\Controllers;

use CleanPro\Models\Intervention;
use CleanPro\Models\Client;
use CleanPro\Utils\Security;
use CleanPro\Utils\Response;

class InterventionController extends Controller
{
    private Intervention $interventionModel;
    private Client $clientModel;

    public function __construct()
    {
        $this->interventionModel = new Intervention();
        $this->clientModel = new Client();
    }

    /**
     * Liste des interventions
     */
    public function index(): void
    {
        $user = $this->requireAuth();

        $filters = [];

        // Filtres
        if (isset($_GET['client_id'])) {
            $filters['client_id'] = (int) $_GET['client_id'];
        }

        if (isset($_GET['agent_id'])) {
            $filters['agent_id'] = (int) $_GET['agent_id'];
        }

        if (isset($_GET['status'])) {
            $filters['status'] = $_GET['status'];
        }

        if (isset($_GET['date_from'])) {
            $filters['date_from'] = $_GET['date_from'];
        }

        if (isset($_GET['date_to'])) {
            $filters['date_to'] = $_GET['date_to'];
        }

        // Si agent, ne voir que ses interventions
        if ($user['role'] === 'agent') {
            $filters['agent_id'] = $user['user_id'];
        }

        $interventions = $this->interventionModel->getInterventionsWithDetails($filters);

        Response::success(['interventions' => $interventions]);
    }

    /**
     * Détail d'une intervention
     */
    public function show(int $id): void
    {
        $user = $this->requireAuth();

        $intervention = $this->interventionModel->getInterventionWithDetails($id);

        if (!$intervention) {
            Response::notFound('Intervention non trouvée');
        }

        // Vérification des permissions
        if ($user['role'] === 'agent' && $intervention['agent_id'] !== $user['user_id']) {
            Response::forbidden('Vous ne pouvez pas voir cette intervention');
        }

        if ($user['role'] === 'client') {
            $clients = $this->clientModel->where(['user_id' => $user['user_id']], [], 1);
            if (empty($clients) || $intervention['client_id'] !== $clients[0]['id']) {
                Response::forbidden('Vous ne pouvez pas voir cette intervention');
            }
        }

        Response::success(['intervention' => $intervention]);
    }

    /**
     * Création d'une intervention
     */
    public function create(): void
    {
        $authUser = $this->requireAuth();
        $this->requireRole($authUser, 'admin');

        $data = $this->getJsonInput();

        // Validation
        $errors = $this->validateRequired($data, ['client_id', 'agent_id', 'scheduled_date', 'scheduled_time']);
        if ($errors) {
            Response::validationError($errors);
        }

        // Vérification des conflits
        $conflicts = $this->interventionModel->checkConflicts(
            $data['agent_id'],
            $data['scheduled_date'],
            $data['scheduled_time'],
            $data['estimated_end_time'] ?? date('H:i', strtotime($data['scheduled_time'] . ' +2 hours'))
        );

        if (!empty($conflicts)) {
            Response::error('Conflit de planning détecté avec une autre intervention', 409);
        }

        // Création
        $interventionData = [
            'client_id' => $data['client_id'],
            'agent_id' => $data['agent_id'],
            'scheduled_date' => $data['scheduled_date'],
            'scheduled_time' => $data['scheduled_time'],
            'estimated_end_time' => $data['estimated_end_time'] ?? null,
            'type' => $data['type'] ?? Intervention::TYPE_ONE_TIME,
            'notes' => isset($data['notes']) ? Security::sanitize($data['notes']) : null,
            'priority' => $data['priority'] ?? 'normal'
        ];

        $interventionId = $this->interventionModel->createIntervention($interventionData);

        // Ajout des services
        if (!empty($data['services'])) {
            foreach ($data['services'] as $service) {
                $this->interventionModel->addService(
                    $interventionId,
                    $service['service_id'],
                    $service['quantity'] ?? 1,
                    $service['unit_price'] ?? 0,
                    $service['notes'] ?? null
                );
            }
        }

        // Mise à jour du quota client si facturation récurrente
        $client = $this->clientModel->find($data['client_id']);
        if ($client && in_array($client['billing_type'], [Client::BILLING_TYPE_MONTHLY, Client::BILLING_TYPE_ANNUAL])) {
            $this->clientModel->updateQuotaUsed($data['client_id']);
        }

        Response::success(['intervention_id' => $interventionId], 'Intervention créée avec succès', 201);
    }

    /**
     * Mise à jour d'une intervention
     */
    public function update(int $id): void
    {
        $authUser = $this->requireAuth();
        $this->requireRole($authUser, 'admin');

        $intervention = $this->interventionModel->find($id);
        if (!$intervention) {
            Response::notFound('Intervention non trouvée');
        }

        $data = $this->getJsonInput();
        $updateData = [];

        $fields = ['agent_id', 'scheduled_date', 'scheduled_time', 'estimated_end_time', 'notes', 'priority'];

        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        // Vérification des conflits si changement de date/heure/agent
        if (isset($data['agent_id']) || isset($data['scheduled_date']) || isset($data['scheduled_time'])) {
            $agentId = $data['agent_id'] ?? $intervention['agent_id'];
            $date = $data['scheduled_date'] ?? $intervention['scheduled_date'];
            $startTime = $data['scheduled_time'] ?? $intervention['scheduled_time'];
            $endTime = $data['estimated_end_time'] ?? $intervention['estimated_end_time'] ?? date('H:i', strtotime($startTime . ' +2 hours'));

            $conflicts = $this->interventionModel->checkConflicts($agentId, $date, $startTime, $endTime, $id);

            if (!empty($conflicts)) {
                Response::error('Conflit de planning détecté', 409);
            }
        }

        if (empty($updateData)) {
            Response::error('Aucune donnée à mettre à jour', 400);
        }

        $updateData['updated_at'] = date('Y-m-d H:i:s');

        $this->interventionModel->update($id, $updateData);

        Response::success([], 'Intervention mise à jour');
    }

    /**
     * Démarrage d'une intervention (agent)
     */
    public function start(int $id): void
    {
        $user = $this->requireAuth();

        $intervention = $this->interventionModel->find($id);
        if (!$intervention) {
            Response::notFound('Intervention non trouvée');
        }

        // Vérification des permissions
        if ($user['role'] === 'agent' && $intervention['agent_id'] !== $user['user_id']) {
            Response::forbidden('Vous ne pouvez pas démarrer cette intervention');
        }

        if ($intervention['status'] !== Intervention::STATUS_SCHEDULED) {
            Response::error('Cette intervention ne peut pas être démarrée', 400);
        }

        $this->interventionModel->startIntervention($id);

        Response::success([], 'Intervention démarrée');
    }

    /**
     * Terminaison d'une intervention (agent)
     */
    public function complete(int $id): void
    {
        $user = $this->requireAuth();

        $intervention = $this->interventionModel->find($id);
        if (!$intervention) {
            Response::notFound('Intervention non trouvée');
        }

        // Vérification des permissions
        if ($user['role'] === 'agent' && $intervention['agent_id'] !== $user['user_id']) {
            Response::forbidden('Vous ne pouvez pas terminer cette intervention');
        }

        if ($intervention['status'] !== Intervention::STATUS_IN_PROGRESS) {
            Response::error('Cette intervention ne peut pas être terminée', 400);
        }

        $data = $this->getJsonInput();

        $this->interventionModel->completeIntervention(
            $id,
            isset($data['notes']) ? Security::sanitize($data['notes']) : null,
            $data['agent_signature'] ?? null
        );

        Response::success([], 'Intervention terminée');
    }

    /**
     * Validation d'une intervention (client)
     */
    public function validate(int $id): void
    {
        $user = $this->requireAuth();

        if ($user['role'] !== 'client') {
            Response::forbidden('Accès réservé aux clients');
        }

        $intervention = $this->interventionModel->find($id);
        if (!$intervention) {
            Response::notFound('Intervention non trouvée');
        }

        // Vérification que l'intervention appartient au client
        $clients = $this->clientModel->where(['user_id' => $user['user_id']], [], 1);
        if (empty($clients) || $intervention['client_id'] !== $clients[0]['id']) {
            Response::forbidden('Vous ne pouvez pas valider cette intervention');
        }

        if ($intervention['status'] !== Intervention::STATUS_COMPLETED) {
            Response::error('Cette intervention ne peut pas encore être validée', 400);
        }

        $data = $this->getJsonInput();

        $this->interventionModel->validateIntervention(
            $id,
            $data['client_signature'] ?? null,
            isset($data['feedback']) ? Security::sanitize($data['feedback']) : null,
            $data['rating'] ?? null
        );

        Response::success([], 'Intervention validée avec succès');
    }

    /**
     * Annulation d'une intervention
     */
    public function cancel(int $id): void
    {
        $authUser = $this->requireAuth();
        $this->requireRole($authUser, 'admin');

        $intervention = $this->interventionModel->find($id);
        if (!$intervention) {
            Response::notFound('Intervention non trouvée');
        }

        if (in_array($intervention['status'], [Intervention::STATUS_COMPLETED, Intervention::STATUS_VALIDATED])) {
            Response::error('Cette intervention ne peut pas être annulée', 400);
        }

        $this->interventionModel->update($id, [
            'status' => Intervention::STATUS_CANCELLED,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        Response::success([], 'Intervention annulée');
    }

    /**
     * Planning d'un agent
     */
    public function schedule(): void
    {
        $user = $this->requireAuth();

        $agentId = isset($_GET['agent_id']) ? (int) $_GET['agent_id'] : $user['user_id'];
        $dateFrom = $_GET['date_from'] ?? date('Y-m-d');
        $dateTo = $_GET['date_to'] ?? date('Y-m-d', strtotime('+7 days'));

        // Vérification des permissions
        if ($user['role'] === 'agent' && $agentId !== $user['user_id']) {
            Response::forbidden('Vous ne pouvez pas voir le planning d\'un autre agent');
        }

        $schedule = $this->interventionModel->getAgentSchedule($agentId, $dateFrom, $dateTo);

        Response::success(['schedule' => $schedule]);
    }

    /**
     * Vérification des conflits
     */
    public function checkConflicts(): void
    {
        $this->requireAuth();

        $data = $this->getJsonInput();

        $errors = $this->validateRequired($data, ['agent_id', 'date', 'start_time', 'end_time']);
        if ($errors) {
            Response::validationError($errors);
        }

        $conflicts = $this->interventionModel->checkConflicts(
            $data['agent_id'],
            $data['date'],
            $data['start_time'],
            $data['end_time'],
            $data['exclude_id'] ?? null
        );

        Response::success([
            'has_conflicts' => !empty($conflicts),
            'conflicts' => $conflicts
        ]);
    }

    /**
     * Statistiques des interventions
     */
    public function statistics(): void
    {
        $this->requireAuth();

        $dateFrom = $_GET['date_from'] ?? null;
        $dateTo = $_GET['date_to'] ?? null;

        $stats = $this->interventionModel->getStatistics($dateFrom, $dateTo);

        Response::success(['statistics' => $stats]);
    }

    /**
     * Interventions à venir
     */
    public function upcoming(): void
    {
        $user = $this->requireAuth();

        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;

        $upcoming = $this->interventionModel->getUpcoming($limit);

        Response::success(['interventions' => $upcoming]);
    }

    /**
     * Génération de la fiche d'intervention PDF
     */
    public function generatePdf(int $id): void
    {
        $user = $this->requireAuth();

        $intervention = $this->interventionModel->getInterventionWithDetails($id);
        if (!$intervention) {
            Response::notFound('Intervention non trouvée');
        }

        // Vérification des permissions
        if ($user['role'] === 'agent' && $intervention['agent_id'] !== $user['user_id']) {
            Response::forbidden();
        }

        if ($user['role'] === 'client') {
            $clients = $this->clientModel->where(['user_id' => $user['user_id']], [], 1);
            if (empty($clients) || $intervention['client_id'] !== $clients[0]['id']) {
                Response::forbidden();
            }
        }

        // Génération du PDF
        $pdfService = new \CleanPro\Services\PDFService();
        $pdfPath = $pdfService->generateInterventionSheet($intervention);

        if (!$pdfPath) {
            Response::serverError('Erreur lors de la génération du PDF');
        }

        // Retourner l'URL du PDF
        $pdfUrl = '/storage/pdf/' . basename($pdfPath);

        Response::success(['pdf_url' => $pdfUrl]);
    }
}
