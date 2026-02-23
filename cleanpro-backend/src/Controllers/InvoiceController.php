<?php

declare(strict_types=1);

namespace CleanPro\Controllers;

use CleanPro\Models\Invoice;
use CleanPro\Models\Client;
use CleanPro\Utils\Response;

class InvoiceController extends Controller
{
    private Invoice $invoiceModel;
    private Client $clientModel;

    public function __construct()
    {
        $this->invoiceModel = new Invoice();
        $this->clientModel = new Client();
    }

    /**
     * Liste des factures
     */
    public function index(): void
    {
        $user = $this->requireAuth();

        $filters = [];

        if (isset($_GET['client_id'])) {
            $filters['client_id'] = (int) $_GET['client_id'];
        }

        if (isset($_GET['status'])) {
            $filters['status'] = $_GET['status'];
        }

        if (isset($_GET['type'])) {
            $filters['type'] = $_GET['type'];
        }

        if (isset($_GET['date_from'])) {
            $filters['date_from'] = $_GET['date_from'];
        }

        if (isset($_GET['date_to'])) {
            $filters['date_to'] = $_GET['date_to'];
        }

        if (isset($_GET['unpaid_only']) && $_GET['unpaid_only'] === 'true') {
            $filters['unpaid_only'] = true;
        }

        // Si client, ne voir que ses factures
        if ($user['role'] === 'client') {
            $clients = $this->clientModel->where(['user_id' => $user['user_id']], [], 1);
            if (!empty($clients)) {
                $filters['client_id'] = $clients[0]['id'];
            }
        }

        $invoices = $this->invoiceModel->getInvoicesWithDetails($filters);

        Response::success(['invoices' => $invoices]);
    }

    /**
     * Détail d'une facture
     */
    public function show(int $id): void
    {
        $user = $this->requireAuth();

        $invoice = $this->invoiceModel->getInvoiceWithDetails($id);

        if (!$invoice) {
            Response::notFound('Facture non trouvée');
        }

        // Vérification des permissions pour les clients
        if ($user['role'] === 'client') {
            $clients = $this->clientModel->where(['user_id' => $user['user_id']], [], 1);
            if (empty($clients) || $invoice['client_id'] !== $clients[0]['id']) {
                Response::forbidden('Vous ne pouvez pas voir cette facture');
            }
        }

        Response::success(['invoice' => $invoice]);
    }

    /**
     * Création d'une facture manuelle
     */
    public function create(): void
    {
        $authUser = $this->requireAuth();
        $this->requireRole($authUser, 'admin');

        $data = $this->getJsonInput();

        $errors = $this->validateRequired($data, ['client_id', 'items']);
        if ($errors) {
            Response::validationError($errors);
        }

        if (empty($data['items'])) {
            Response::validationError(['items' => 'Au moins un article est requis']);
        }

        $this->invoiceModel->beginTransaction();

        try {
            // Création de la facture
            $invoiceData = [
                'client_id' => $data['client_id'],
                'type' => $data['type'] ?? Invoice::TYPE_ONE_TIME,
                'notes' => $data['notes'] ?? null,
                'due_date' => $data['due_date'] ?? date('Y-m-d', strtotime('+30 days'))
            ];

            $invoiceId = $this->invoiceModel->createInvoice($invoiceData);

            // Ajout des lignes
            foreach ($data['items'] as $item) {
                $this->invoiceModel->addItem($invoiceId, $item);
            }

            // Application du code promo si fourni
            if (!empty($data['promo_code_id'])) {
                $promoModel = new \CleanPro\Models\PromoCode();
                $promoCode = $promoModel->find($data['promo_code_id']);
                if ($promoCode) {
                    $this->invoiceModel->applyPromoCode($invoiceId, $promoCode);
                }
            }

            // Application d'une remise manuelle
            if (!empty($data['discount_amount'])) {
                $this->invoiceModel->applyDiscount($invoiceId, $data['discount_amount'], $data['discount_reason'] ?? null);
            }

            $this->invoiceModel->commit();

            Response::success(['invoice_id' => $invoiceId], 'Facture créée avec succès', 201);
        } catch (\Exception $e) {
            $this->invoiceModel->rollback();
            Response::serverError('Erreur lors de la création de la facture');
        }
    }

    /**
     * Création d'une facture depuis une intervention
     */
    public function createFromIntervention(): void
    {
        $authUser = $this->requireAuth();
        $this->requireRole($authUser, 'admin');

        $data = $this->getJsonInput();

        $errors = $this->validateRequired($data, ['intervention_id']);
        if ($errors) {
            Response::validationError($errors);
        }

        $promoCode = null;
        if (!empty($data['promo_code_id'])) {
            $promoModel = new \CleanPro\Models\PromoCode();
            $promoCode = $promoModel->find($data['promo_code_id']);
        }

        $invoiceId = $this->invoiceModel->createFromIntervention($data['intervention_id'], $promoCode);

        if (!$invoiceId) {
            Response::error('Impossible de créer la facture. Vérifiez que l\'intervention est terminée.', 400);
        }

        Response::success(['invoice_id' => $invoiceId], 'Facture créée avec succès');
    }

    /**
     * Mise à jour d'une facture
     */
    public function update(int $id): void
    {
        $authUser = $this->requireAuth();
        $this->requireRole($authUser, 'admin');

        $invoice = $this->invoiceModel->find($id);
        if (!$invoice) {
            Response::notFound('Facture non trouvée');
        }

        // Ne pas modifier une facture payée
        if ($invoice['status'] === Invoice::STATUS_PAID) {
            Response::error('Impossible de modifier une facture payée', 400);
        }

        $data = $this->getJsonInput();
        $updateData = [];

        if (isset($data['notes'])) {
            $updateData['notes'] = $data['notes'];
        }

        if (isset($data['due_date'])) {
            $updateData['due_date'] = $data['due_date'];
        }

        if (empty($updateData)) {
            Response::error('Aucune donnée à mettre à jour', 400);
        }

        $updateData['updated_at'] = date('Y-m-d H:i:s');

        $this->invoiceModel->update($id, $updateData);

        Response::success([], 'Facture mise à jour');
    }

    /**
     * Envoi d'une facture
     */
    public function send(int $id): void
    {
        $authUser = $this->requireAuth();
        $this->requireRole($authUser, 'admin');

        $invoice = $this->invoiceModel->find($id);
        if (!$invoice) {
            Response::notFound('Facture non trouvée');
        }

        if ($invoice['status'] !== Invoice::STATUS_DRAFT) {
            Response::error('Cette facture a déjà été envoyée', 400);
        }

        $this->invoiceModel->markAsSent($id);

        // TODO: Envoyer l'email avec le PDF

        Response::success([], 'Facture envoyée avec succès');
    }

    /**
     * Marquer comme payée
     */
    public function markAsPaid(int $id): void
    {
        $authUser = $this->requireAuth();
        $this->requireRole($authUser, 'admin');

        $invoice = $this->invoiceModel->find($id);
        if (!$invoice) {
            Response::notFound('Facture non trouvée');
        }

        $data = $this->getJsonInput();

        $paymentMethod = $data['payment_method'] ?? 'transfer';
        $transactionId = $data['transaction_id'] ?? null;

        $this->invoiceModel->markAsPaid($id, $paymentMethod, $transactionId);

        Response::success([], 'Facture marquée comme payée');
    }

    /**
     * Annulation d'une facture
     */
    public function cancel(int $id): void
    {
        $authUser = $this->requireAuth();
        $this->requireRole($authUser, 'admin');

        $invoice = $this->invoiceModel->find($id);
        if (!$invoice) {
            Response::notFound('Facture non trouvée');
        }

        if ($invoice['status'] === Invoice::STATUS_PAID) {
            Response::error('Impossible d\'annuler une facture payée', 400);
        }

        $this->invoiceModel->update($id, [
            'status' => Invoice::STATUS_CANCELLED,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        Response::success([], 'Facture annulée');
    }

    /**
     * Factures en retard
     */
    public function overdue(): void
    {
        $authUser = $this->requireAuth();
        $this->requireRole($authUser, 'admin');

        // Mise à jour des statuts
        $this->invoiceModel->updateOverdueStatus();

        $overdue = $this->invoiceModel->getOverdue();

        Response::success(['invoices' => $overdue]);
    }

    /**
     * Statistiques de facturation
     */
    public function statistics(): void
    {
        $this->requireAuth();

        $dateFrom = $_GET['date_from'] ?? null;
        $dateTo = $_GET['date_to'] ?? null;

        $stats = $this->invoiceModel->getStatistics($dateFrom, $dateTo);

        Response::success(['statistics' => $stats]);
    }

    /**
     * Génération du PDF
     */
    public function generatePdf(int $id): void
    {
        $user = $this->requireAuth();

        $invoice = $this->invoiceModel->getInvoiceWithDetails($id);
        if (!$invoice) {
            Response::notFound('Facture non trouvée');
        }

        // Vérification des permissions pour les clients
        if ($user['role'] === 'client') {
            $clients = $this->clientModel->where(['user_id' => $user['user_id']], [], 1);
            if (empty($clients) || $invoice['client_id'] !== $clients[0]['id']) {
                Response::forbidden('Vous ne pouvez pas télécharger cette facture');
            }
        }

        // Génération du PDF
        $pdfService = new \CleanPro\Services\PDFService();
        $pdfPath = $pdfService->generateInvoice($invoice);

        if (!$pdfPath) {
            Response::serverError('Erreur lors de la génération du PDF');
        }

        $pdfUrl = '/storage/pdf/' . basename($pdfPath);

        Response::success(['pdf_url' => $pdfUrl]);
    }

    /**
     * Téléchargement direct du PDF
     */
    public function downloadPdf(int $id): void
    {
        $user = $this->requireAuth();

        $invoice = $this->invoiceModel->getInvoiceWithDetails($id);
        if (!$invoice) {
            Response::notFound('Facture non trouvée');
        }

        // Vérification des permissions pour les clients
        if ($user['role'] === 'client') {
            $clients = $this->clientModel->where(['user_id' => $user['user_id']], [], 1);
            if (empty($clients) || $invoice['client_id'] !== $clients[0]['id']) {
                Response::forbidden('Vous ne pouvez pas télécharger cette facture');
            }
        }

        // Génération du PDF
        $pdfService = new \CleanPro\Services\PDFService();
        $pdfPath = $pdfService->generateInvoice($invoice);

        if (!$pdfPath || !file_exists($pdfPath)) {
            Response::serverError('Erreur lors de la génération du PDF');
        }

        // Envoi du fichier
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $invoice['invoice_number'] . '.pdf"');
        header('Content-Length: ' . filesize($pdfPath));
        readfile($pdfPath);
        exit;
    }
}
