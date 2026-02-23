<?php

declare(strict_types=1);

namespace CleanPro\Services;

use Dompdf\Dompdf;
use Dompdf\Options;

class PDFService
{
    private string $storagePath;
    private array $companyInfo;

    public function __construct()
    {
        $this->storagePath = __DIR__ . '/../../storage/pdf/';
        
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }

        $this->companyInfo = [
            'name' => $_ENV['COMPANY_NAME'] ?? 'CleanPro Services',
            'address' => $_ENV['COMPANY_ADDRESS'] ?? '123 Rue des Services',
            'city' => $_ENV['COMPANY_CITY'] ?? '75000 Paris',
            'phone' => $_ENV['COMPANY_PHONE'] ?? '01 23 45 67 89',
            'email' => $_ENV['COMPANY_EMAIL'] ?? 'contact@cleanpro.fr',
            'siret' => $_ENV['COMPANY_SIRET'] ?? '123 456 789 00012',
            'vat' => $_ENV['COMPANY_VAT'] ?? 'FR12345678900',
            'logo' => $_ENV['COMPANY_LOGO'] ?? null
        ];
    }

    /**
     * Configuration de Dompdf
     */
    private function getDompdf(): Dompdf
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', false);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Helvetica');
        $options->set('chroot', __DIR__ . '/../../');

        return new Dompdf($options);
    }

    /**
     * Génération d'une facture PDF
     */
    public function generateInvoice(array $invoice): ?string
    {
        $dompdf = $this->getDompdf();

        $html = $this->getInvoiceTemplate($invoice);
        
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'FACTURE_' . $invoice['invoice_number'] . '_' . date('Ymd') . '.pdf';
        $filepath = $this->storagePath . $filename;

        file_put_contents($filepath, $dompdf->output());

        return $filepath;
    }

    /**
     * Génération d'une fiche d'intervention PDF
     */
    public function generateInterventionSheet(array $intervention): ?string
    {
        $dompdf = $this->getDompdf();

        $html = $this->getInterventionTemplate($intervention);
        
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'FICHE_' . $intervention['reference'] . '_' . date('Ymd') . '.pdf';
        $filepath = $this->storagePath . $filename;

        file_put_contents($filepath, $dompdf->output());

        return $filepath;
    }

    /**
     * Template HTML pour les factures
     */
    private function getInvoiceTemplate(array $invoice): string
    {
        $items = $invoice['items'] ?? [];
        $itemsHtml = '';
        
        foreach ($items as $item) {
            $itemsHtml .= '
                <tr>
                    <td>' . htmlspecialchars($item['description']) . '</td>
                    <td style="text-align: center;">' . $item['quantity'] . '</td>
                    <td style="text-align: right;">' . number_format($item['unit_price'], 2, ',', ' ') . ' €</td>
                    <td style="text-align: right;">' . number_format($item['total_price'], 2, ',', ' ') . ' €</td>
                </tr>
            ';
        }

        $statusLabels = [
            'draft' => 'Brouillon',
            'sent' => 'Envoyée',
            'paid' => 'Payée',
            'overdue' => 'En retard',
            'cancelled' => 'Annulée'
        ];

        $statusColor = match($invoice['status']) {
            'paid' => '#22c55e',
            'overdue' => '#ef4444',
            'sent' => '#3b82f6',
            default => '#6b7280'
        };

        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; line-height: 1.5; color: #333; }
                .header { display: flex; justify-content: space-between; margin-bottom: 40px; }
                .company-info { width: 50%; }
                .company-name { font-size: 20px; font-weight: bold; color: #0d9488; margin-bottom: 10px; }
                .invoice-title { text-align: right; }
                .invoice-title h1 { font-size: 28px; color: #0d9488; margin-bottom: 10px; }
                .status-badge { display: inline-block; padding: 5px 15px; border-radius: 20px; color: white; font-weight: bold; font-size: 11px; }
                .client-section { margin-bottom: 30px; }
                .client-section h3 { font-size: 14px; color: #666; margin-bottom: 10px; text-transform: uppercase; }
                .client-name { font-size: 16px; font-weight: bold; margin-bottom: 5px; }
                .invoice-details { display: flex; justify-content: space-between; margin-bottom: 30px; background: #f8fafc; padding: 15px; border-radius: 8px; }
                .detail-item { text-align: center; }
                .detail-label { font-size: 11px; color: #666; text-transform: uppercase; margin-bottom: 5px; }
                .detail-value { font-size: 14px; font-weight: bold; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
                th { background: #0d9488; color: white; padding: 12px; text-align: left; font-size: 11px; text-transform: uppercase; }
                td { padding: 12px; border-bottom: 1px solid #e5e7eb; }
                tr:nth-child(even) { background: #f8fafc; }
                .totals { width: 300px; margin-left: auto; }
                .total-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e5e7eb; }
                .total-row.final { font-size: 16px; font-weight: bold; color: #0d9488; border-top: 2px solid #0d9488; border-bottom: none; margin-top: 10px; padding-top: 10px; }
                .footer { margin-top: 50px; padding-top: 20px; border-top: 1px solid #e5e7eb; font-size: 10px; color: #666; text-align: center; }
                .notes { margin-top: 30px; padding: 15px; background: #f8fafc; border-radius: 8px; }
                .notes h4 { margin-bottom: 10px; color: #666; }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="company-info">
                    <div class="company-name">' . htmlspecialchars($this->companyInfo['name']) . '</div>
                    <div>' . htmlspecialchars($this->companyInfo['address']) . '</div>
                    <div>' . htmlspecialchars($this->companyInfo['city']) . '</div>
                    <div>Tél: ' . htmlspecialchars($this->companyInfo['phone']) . '</div>
                    <div>Email: ' . htmlspecialchars($this->companyInfo['email']) . '</div>
                    <div style="margin-top: 10px; font-size: 10px;">
                        SIRET: ' . htmlspecialchars($this->companyInfo['siret']) . '<br>
                        TVA: ' . htmlspecialchars($this->companyInfo['vat']) . '
                    </div>
                </div>
                <div class="invoice-title">
                    <h1>FACTURE</h1>
                    <div style="font-size: 18px; margin-bottom: 10px;">' . $invoice['invoice_number'] . '</div>
                    <span class="status-badge" style="background: ' . $statusColor . ';">
                        ' . ($statusLabels[$invoice['status']] ?? $invoice['status']) . '
                    </span>
                </div>
            </div>

            <div class="client-section">
                <h3>Facturer à</h3>
                <div class="client-name">' . htmlspecialchars($invoice['client_company']) . '</div>
                <div>' . htmlspecialchars($invoice['client_address']) . '</div>
                <div>' . htmlspecialchars($invoice['client_postal_code'] . ' ' . $invoice['client_city']) . '</div>
                <div style="margin-top: 10px;">Contact: ' . htmlspecialchars($invoice['client_contact']) . '</div>
                <div>Email: ' . htmlspecialchars($invoice['client_email']) . '</div>
            </div>

            <div class="invoice-details">
                <div class="detail-item">
                    <div class="detail-label">Date d\'émission</div>
                    <div class="detail-value">' . date('d/m/Y', strtotime($invoice['issue_date'])) . '</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Date d\'échéance</div>
                    <div class="detail-value">' . date('d/m/Y', strtotime($invoice['due_date'])) . '</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Mode de paiement</div>
                    <div class="detail-value">Virement bancaire</div>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Description</th>
                        <th style="text-align: center;">Qté</th>
                        <th style="text-align: right;">Prix unitaire</th>
                        <th style="text-align: right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    ' . $itemsHtml . '
                </tbody>
            </table>

            <div class="totals">
                <div class="total-row">
                    <span>Sous-total HT</span>
                    <span>' . number_format($invoice['subtotal'], 2, ',', ' ') . ' €</span>
                </div>
                ' . ($invoice['discount_amount'] > 0 ? '
                <div class="total-row" style="color: #ef4444;">
                    <span>Remise' . ($invoice['discount_reason'] ? ' (' . htmlspecialchars($invoice['discount_reason']) . ')' : '') . '</span>
                    <span>-' . number_format($invoice['discount_amount'], 2, ',', ' ') . ' €</span>
                </div>
                ' : '') . '
                <div class="total-row final">
                    <span>Total TTC</span>
                    <span>' . number_format($invoice['total_amount'], 2, ',', ' ') . ' €</span>
                </div>
            </div>

            ' . ($invoice['notes'] ? '
            <div class="notes">
                <h4>Notes</h4>
                <p>' . nl2br(htmlspecialchars($invoice['notes'])) . '</p>
            </div>
            ' : '') . '

            <div class="footer">
                <p>' . htmlspecialchars($this->companyInfo['name']) . ' - SIRET: ' . htmlspecialchars($this->companyInfo['siret']) . '</p>
                <p>Les factures sont payables à 30 jours. Passé ce délai, des pénalités de retard seront appliquées.</p>
            </div>
        </body>
        </html>
        ';
    }

    /**
     * Template HTML pour les fiches d'intervention
     */
    private function getInterventionTemplate(array $intervention): string
    {
        $services = $intervention['services'] ?? [];
        $servicesHtml = '';
        
        foreach ($services as $service) {
            $servicesHtml .= '
                <tr>
                    <td>' . htmlspecialchars($service['name']) . '</td>
                    <td style="text-align: center;">' . $service['quantity'] . '</td>
                    <td style="text-align: right;">' . number_format($service['unit_price'], 2, ',', ' ') . ' €</td>
                    <td style="text-align: right;">' . number_format($service['total_price'], 2, ',', ' ') . ' €</td>
                </tr>
            ';
        }

        $statusLabels = [
            'scheduled' => 'Planifiée',
            'in_progress' => 'En cours',
            'completed' => 'Terminée',
            'validated' => 'Validée',
            'cancelled' => 'Annulée'
        ];

        $statusColor = match($intervention['status']) {
            'validated' => '#22c55e',
            'completed' => '#3b82f6',
            'in_progress' => '#f59e0b',
            'cancelled' => '#ef4444',
            default => '#6b7280'
        };

        $signatureAgent = $intervention['agent_signature'] 
            ? '<img src="' . $intervention['agent_signature'] . '" style="max-width: 200px; max-height: 80px;">' 
            : '<div style="color: #999; font-style: italic;">Non signée</div>';

        $signatureClient = $intervention['client_signature'] 
            ? '<img src="' . $intervention['client_signature'] . '" style="max-width: 200px; max-height: 80px;">' 
            : '<div style="color: #999; font-style: italic;">Non signée</div>';

        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; line-height: 1.5; color: #333; }
                .header { display: flex; justify-content: space-between; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 3px solid #0d9488; }
                .company-info { width: 50%; }
                .company-name { font-size: 18px; font-weight: bold; color: #0d9488; margin-bottom: 8px; }
                .doc-title { text-align: right; }
                .doc-title h1 { font-size: 24px; color: #0d9488; margin-bottom: 8px; }
                .status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; color: white; font-weight: bold; font-size: 10px; }
                .info-grid { display: flex; gap: 20px; margin-bottom: 25px; }
                .info-box { flex: 1; padding: 15px; background: #f8fafc; border-radius: 8px; }
                .info-box h3 { font-size: 11px; color: #666; margin-bottom: 10px; text-transform: uppercase; }
                .info-value { font-size: 13px; font-weight: 500; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
                th { background: #0d9488; color: white; padding: 10px; text-align: left; font-size: 10px; text-transform: uppercase; }
                td { padding: 10px; border-bottom: 1px solid #e5e7eb; }
                .section { margin-bottom: 25px; }
                .section h3 { font-size: 12px; color: #0d9488; margin-bottom: 10px; text-transform: uppercase; border-bottom: 1px solid #e5e7eb; padding-bottom: 5px; }
                .signatures { display: flex; gap: 40px; margin-top: 40px; }
                .signature-box { flex: 1; text-align: center; }
                .signature-box h4 { font-size: 11px; color: #666; margin-bottom: 15px; }
                .signature-line { border: 2px dashed #e5e7eb; height: 100px; display: flex; align-items: center; justify-content: center; border-radius: 8px; }
                .footer { margin-top: 40px; padding-top: 15px; border-top: 1px solid #e5e7eb; font-size: 9px; color: #666; text-align: center; }
                .rating { color: #f59e0b; font-size: 16px; }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="company-info">
                    <div class="company-name">' . htmlspecialchars($this->companyInfo['name']) . '</div>
                    <div style="font-size: 10px;">
                        ' . htmlspecialchars($this->companyInfo['address']) . ', ' . htmlspecialchars($this->companyInfo['city']) . '<br>
                        Tél: ' . htmlspecialchars($this->companyInfo['phone']) . '
                    </div>
                </div>
                <div class="doc-title">
                    <h1>FICHE D\'INTERVENTION</h1>
                    <div style="font-size: 14px; margin-bottom: 8px;">' . $intervention['reference'] . '</div>
                    <span class="status-badge" style="background: ' . $statusColor . ';">
                        ' . ($statusLabels[$intervention['status']] ?? $intervention['status']) . '
                    </span>
                </div>
            </div>

            <div class="info-grid">
                <div class="info-box">
                    <h3>Date et heure prévues</h3>
                    <div class="info-value">
                        ' . date('d/m/Y', strtotime($intervention['scheduled_date'])) . '<br>
                        ' . substr($intervention['scheduled_time'], 0, 5) . ' - ' . ($intervention['estimated_end_time'] ? substr($intervention['estimated_end_time'], 0, 5) : '--:--') . '
                    </div>
                </div>
                <div class="info-box">
                    <h3>Client</h3>
                    <div class="info-value">
                        ' . htmlspecialchars($intervention['client_company']) . '<br>
                        ' . htmlspecialchars($intervention['client_address']) . '<br>
                        ' . htmlspecialchars($intervention['client_postal_code'] . ' ' . $intervention['client_city']) . '
                    </div>
                </div>
                <div class="info-box">
                    <h3>Agent</h3>
                    <div class="info-value">
                        ' . htmlspecialchars($intervention['agent_name']) . '<br>
                        ' . htmlspecialchars($intervention['agent_phone']) . '
                    </div>
                </div>
            </div>

            <div class="section">
                <h3>Services réalisés</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Service</th>
                            <th style="text-align: center;">Qté</th>
                            <th style="text-align: right;">Prix unit.</th>
                            <th style="text-align: right;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        ' . ($servicesHtml ?: '<tr><td colspan="4" style="text-align: center; color: #999;">Aucun service enregistré</td></tr>') . '
                    </tbody>
                </table>
            </div>

            ' . ($intervention['notes'] ? '
            <div class="section">
                <h3>Notes de l\'intervention</h3>
                <p style="padding: 10px; background: #f8fafc; border-radius: 8px;">' . nl2br(htmlspecialchars($intervention['notes'])) . '</p>
            </div>
            ' : '') . '

            ' . ($intervention['actual_start_time'] ? '
            <div class="section">
                <h3>Temps réel</h3>
                <div style="display: flex; gap: 30px;">
                    <div><strong>Début:</strong> ' . date('d/m/Y H:i', strtotime($intervention['actual_start_time'])) . '</div>
                    ' . ($intervention['actual_end_time'] ? '<div><strong>Fin:</strong> ' . date('d/m/Y H:i', strtotime($intervention['actual_end_time'])) . '</div>' : '') . '
                </div>
            </div>
            ' : '') . '

            <div class="signatures">
                <div class="signature-box">
                    <h4>Signature de l\'agent</h4>
                    <div class="signature-line">
                        ' . $signatureAgent . '
                    </div>
                    <div style="margin-top: 10px; font-size: 10px; color: #666;">
                        ' . ($intervention['actual_end_time'] ? 'Signé le ' . date('d/m/Y H:i', strtotime($intervention['actual_end_time'])) : '') . '
                    </div>
                </div>
                <div class="signature-box">
                    <h4>Signature du client</h4>
                    <div class="signature-line">
                        ' . $signatureClient . '
                    </div>
                    <div style="margin-top: 10px; font-size: 10px; color: #666;">
                        ' . ($intervention['validated_at'] ? 'Validé le ' . date('d/m/Y H:i', strtotime($intervention['validated_at'])) : '') . '
                    </div>
                </div>
            </div>

            ' . ($intervention['client_rating'] ? '
            <div class="section" style="text-align: center; margin-top: 30px;">
                <h3>Satisfaction client</h3>
                <div class="rating">
                    ' . str_repeat('★', $intervention['client_rating']) . str_repeat('☆', 5 - $intervention['client_rating']) . '
                </div>
                ' . ($intervention['client_feedback'] ? '<p style="margin-top: 10px; font-style: italic;">"' . htmlspecialchars($intervention['client_feedback']) . '"</p>' : '') . '
            </div>
            ' : '') . '

            <div class="footer">
                <p>Document généré le ' . date('d/m/Y à H:i') . ' | ' . htmlspecialchars($this->companyInfo['name']) . '</p>
                <p>Cette fiche d\'intervention fait foi des prestations réalisées.</p>
            </div>
        </body>
        </html>
        ';
    }
}
