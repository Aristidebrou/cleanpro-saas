<?php

declare(strict_types=1);

namespace CleanPro\Controllers;

use CleanPro\Models\Client;
use CleanPro\Models\Intervention;
use CleanPro\Models\Invoice;
use CleanPro\Utils\Response;

class DashboardController extends Controller
{
    private Client $clientModel;
    private Intervention $interventionModel;
    private Invoice $invoiceModel;

    public function __construct()
    {
        $this->clientModel = new Client();
        $this->interventionModel = new Intervention();
        $this->invoiceModel = new Invoice();
    }

    /**
     * Dashboard principal avec KPIs
     */
    public function index(): void
    {
        $this->requireAuth();

        $period = $_GET['period'] ?? 'month';
        
        // Définition des dates selon la période
        $dateFrom = match($period) {
            'week' => date('Y-m-d', strtotime('-7 days')),
            'month' => date('Y-m-01'),
            'quarter' => date('Y-m-d', strtotime('-3 months')),
            'year' => date('Y-01-01'),
            default => date('Y-m-01')
        };
        $dateTo = date('Y-m-d');

        $dashboard = [
            'kpis' => $this->getKPIs($dateFrom, $dateTo),
            'charts' => $this->getChartsData($dateFrom, $dateTo),
            'recent_activity' => $this->getRecentActivity(),
            'alerts' => $this->getAlerts()
        ];

        Response::success(['dashboard' => $dashboard]);
    }

    /**
     * KPIs principaux
     */
    private function getKPIs(string $dateFrom, string $dateTo): array
    {
        // Chiffre d'affaires
        $invoiceStats = $this->invoiceModel->getStatistics($dateFrom, $dateTo);
        
        // Interventions
        $interventionStats = $this->interventionModel->getStatistics($dateFrom, $dateTo);
        
        // Clients
        $clientStats = $this->clientModel->getStatistics();

        // Calcul de la rentabilité (CA - coûts estimés)
        $totalRevenue = $invoiceStats['total_paid'];
        $estimatedCosts = $this->calculateEstimatedCosts($dateFrom, $dateTo);
        $profitability = $totalRevenue - $estimatedCosts;

        return [
            'revenue' => [
                'value' => $totalRevenue,
                'change' => $this->calculateChange('revenue', $dateFrom, $dateTo),
                'currency' => '€'
            ],
            'interventions' => [
                'value' => $interventionStats['total'],
                'completed' => $interventionStats['by_status']['completed'] ?? 0,
                'change' => $this->calculateChange('interventions', $dateFrom, $dateTo)
            ],
            'clients' => [
                'total' => $clientStats['total'],
                'active' => $clientStats['active'],
                'retention_rate' => $this->calculateRetentionRate()
            ],
            'profitability' => [
                'value' => $profitability,
                'margin' => $totalRevenue > 0 ? round(($profitability / $totalRevenue) * 100, 1) : 0,
                'currency' => '€'
            ],
            'outstanding' => [
                'amount' => $invoiceStats['total_outstanding'],
                'count' => $invoiceStats['overdue_count'],
                'currency' => '€'
            ]
        ];
    }

    /**
     * Données pour les graphiques
     */
    private function getChartsData(string $dateFrom, string $dateTo): array
    {
        return [
            'revenue_evolution' => $this->getRevenueEvolution($dateFrom, $dateTo),
            'interventions_by_type' => $this->getInterventionsByType($dateFrom, $dateTo),
            'agent_workload' => $this->getAgentWorkload($dateFrom, $dateTo),
            'client_retention' => $this->getClientRetentionData(),
            'monthly_comparison' => $this->getMonthlyComparison()
        ];
    }

    /**
     * Évolution du chiffre d'affaires
     */
    private function getRevenueEvolution(string $dateFrom, string $dateTo): array
    {
        $sql = "
            SELECT 
                DATE(issue_date) as date,
                SUM(total_amount) as amount
            FROM invoices
            WHERE status = 'paid'
            AND issue_date BETWEEN :date_from AND :date_to
            GROUP BY DATE(issue_date)
            ORDER BY date
        ";

        $stmt = $this->invoiceModel->query($sql, [
            ':date_from' => $dateFrom,
            ':date_to' => $dateTo
        ]);

        $data = $stmt->fetchAll();
        
        $labels = [];
        $values = [];
        
        foreach ($data as $row) {
            $labels[] = date('d/m', strtotime($row['date']));
            $values[] = (float) $row['amount'];
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Chiffre d\'affaires',
                    'data' => $values,
                    'borderColor' => '#0d9488',
                    'backgroundColor' => 'rgba(13, 148, 136, 0.1)',
                    'fill' => true
                ]
            ]
        ];
    }

    /**
     * Interventions par type de service
     */
    private function getInterventionsByType(string $dateFrom, string $dateTo): array
    {
        $sql = "
            SELECT 
                s.category,
                COUNT(DISTINCT i.id) as count
            FROM interventions i
            JOIN intervention_services iss ON i.id = iss.intervention_id
            JOIN services s ON iss.service_id = s.id
            WHERE i.scheduled_date BETWEEN :date_from AND :date_to
            GROUP BY s.category
        ";

        $stmt = $this->interventionModel->query($sql, [
            ':date_from' => $dateFrom,
            ':date_to' => $dateTo
        ]);

        $data = $stmt->fetchAll();

        $labels = [];
        $values = [];
        $colors = ['#0d9488', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6'];

        $categoryLabels = [
            'cleaning' => 'Nettoyage',
            'gardening' => 'Jardinage',
            'maintenance' => 'Maintenance'
        ];

        foreach ($data as $index => $row) {
            $labels[] = $categoryLabels[$row['category']] ?? $row['category'];
            $values[] = (int) $row['count'];
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'data' => $values,
                    'backgroundColor' => array_slice($colors, 0, count($values))
                ]
            ]
        ];
    }

    /**
     * Charge de travail des agents (heatmap)
     */
    private function getAgentWorkload(string $dateFrom, string $dateTo): array
    {
        $sql = "
            SELECT 
                u.id as agent_id,
                CONCAT(u.first_name, ' ', u.last_name) as agent_name,
                DATE(i.scheduled_date) as date,
                COUNT(*) as intervention_count,
                SUM(i.total_amount) as revenue
            FROM interventions i
            JOIN users u ON i.agent_id = u.id
            WHERE i.scheduled_date BETWEEN :date_from AND :date_to
            AND i.status NOT IN ('cancelled')
            GROUP BY u.id, DATE(i.scheduled_date)
            ORDER BY u.last_name, date
        ";

        $stmt = $this->interventionModel->query($sql, [
            ':date_from' => $dateFrom,
            ':date_to' => $dateTo
        ]);

        $data = $stmt->fetchAll();

        // Organisation par agent
        $agents = [];
        foreach ($data as $row) {
            $agentId = $row['agent_id'];
            if (!isset($agents[$agentId])) {
                $agents[$agentId] = [
                    'name' => $row['agent_name'],
                    'total_interventions' => 0,
                    'total_revenue' => 0,
                    'daily_data' => []
                ];
            }
            $agents[$agentId]['total_interventions'] += $row['intervention_count'];
            $agents[$agentId]['total_revenue'] += $row['revenue'];
            $agents[$agentId]['daily_data'][$row['date']] = [
                'count' => $row['intervention_count'],
                'revenue' => $row['revenue']
            ];
        }

        return array_values($agents);
    }

    /**
     * Données de rétention client
     */
    private function getClientRetentionData(): array
    {
        // Clients actifs par mois sur les 12 derniers mois
        $sql = "
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as new_clients
            FROM clients
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month
        ";

        $stmt = $this->clientModel->query($sql);
        $newClients = $stmt->fetchAll();

        // Clients toujours actifs (ayant eu une intervention dans les 3 derniers mois)
        $sql = "
            SELECT 
                COUNT(DISTINCT client_id) as active_clients
            FROM interventions
            WHERE scheduled_date >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
            AND status IN ('completed', 'validated')
        ";

        $stmt = $this->clientModel->query($sql);
        $activeClients = (int) $stmt->fetchColumn();

        $totalClients = $this->clientModel->getStatistics()['total'];

        return [
            'retention_rate' => $totalClients > 0 ? round(($activeClients / $totalClients) * 100, 1) : 0,
            'active_clients' => $activeClients,
            'total_clients' => $totalClients,
            'new_clients_by_month' => $newClients
        ];
    }

    /**
     * Comparaison mensuelle
     */
    private function getMonthlyComparison(): array
    {
        $months = [];
        $revenue = [];
        $interventions = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-$i months"));
            $monthName = date('M Y', strtotime("-$i months"));
            
            $months[] = $monthName;

            // CA du mois
            $sql = "
                SELECT COALESCE(SUM(total_amount), 0)
                FROM invoices
                WHERE status = 'paid'
                AND DATE_FORMAT(issue_date, '%Y-%m') = :month
            ";
            $stmt = $this->invoiceModel->query($sql, [':month' => $month]);
            $revenue[] = (float) $stmt->fetchColumn();

            // Interventions du mois
            $sql = "
                SELECT COUNT(*)
                FROM interventions
                WHERE DATE_FORMAT(scheduled_date, '%Y-%m') = :month
                AND status IN ('completed', 'validated')
            ";
            $stmt = $this->interventionModel->query($sql, [':month' => $month]);
            $interventions[] = (int) $stmt->fetchColumn();
        }

        return [
            'labels' => $months,
            'datasets' => [
                [
                    'label' => 'Chiffre d\'affaires (€)',
                    'data' => $revenue,
                    'borderColor' => '#0d9488',
                    'yAxisID' => 'y'
                ],
                [
                    'label' => 'Interventions',
                    'data' => $interventions,
                    'borderColor' => '#3b82f6',
                    'yAxisID' => 'y1'
                ]
            ]
        ];
    }

    /**
     * Activité récente
     */
    private function getRecentActivity(): array
    {
        // Interventions récentes
        $recentInterventions = $this->interventionModel->getUpcoming(5);

        // Factures récentes
        $sql = "
            SELECT i.*, c.company_name as client_company
            FROM invoices i
            JOIN clients c ON i.client_id = c.id
            ORDER BY i.created_at DESC
            LIMIT 5
        ";
        $stmt = $this->invoiceModel->query($sql);
        $recentInvoices = $stmt->fetchAll();

        return [
            'interventions' => $recentInterventions,
            'invoices' => $recentInvoices
        ];
    }

    /**
     * Alertes et notifications
     */
    private function getAlerts(): array
    {
        $alerts = [];

        // Factures en retard
        $overdue = $this->invoiceModel->getOverdue();
        if (!empty($overdue)) {
            $totalOverdue = array_sum(array_column($overdue, 'total_amount'));
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Factures en retard',
                'message' => count($overdue) . ' facture(s) en retard pour un total de ' . number_format($totalOverdue, 2, ',', ' ') . ' €',
                'action' => '/invoices/overdue'
            ];
        }

        // Clients avec quota dépassé
        $exceededQuota = $this->clientModel->getClientsWithExceededQuota();
        if (!empty($exceededQuota)) {
            $alerts[] = [
                'type' => 'info',
                'title' => 'Quotas dépassés',
                'message' => count($exceededQuota) . ' client(s) ont dépassé leur quota mensuel',
                'action' => '/clients/quota-exceeded'
            ];
        }

        // Interventions à venir aujourd'hui
        $todayInterventions = $this->interventionModel->where([
            'scheduled_date' => date('Y-m-d'),
            'status' => Intervention::STATUS_SCHEDULED
        ]);
        
        if (!empty($todayInterventions)) {
            $alerts[] = [
                'type' => 'success',
                'title' => 'Interventions aujourd\'hui',
                'message' => count($todayInterventions) . ' intervention(s) prévue(s) aujourd\'hui',
                'action' => '/interventions/today'
            ];
        }

        return $alerts;
    }

    /**
     * Calcul des coûts estimés
     */
    private function calculateEstimatedCosts(string $dateFrom, string $dateTo): float
    {
        // Coût horaire moyen des agents + frais de déplacement estimés
        $sql = "
            SELECT 
                COUNT(*) as intervention_count,
                SUM(TIMESTAMPDIFF(HOUR, 
n                    CONCAT(scheduled_date, ' ', scheduled_time),
                    COALESCE(CONCAT(scheduled_date, ' ', estimated_end_time), DATE_ADD(CONCAT(scheduled_date, ' ', scheduled_time), INTERVAL 2 HOUR))
                )) as total_hours
            FROM interventions
            WHERE scheduled_date BETWEEN :date_from AND :date_to
            AND status IN ('completed', 'validated')
        ";

        $stmt = $this->interventionModel->query($sql, [
            ':date_from' => $dateFrom,
            ':date_to' => $dateTo
        ]);

        $result = $stmt->fetch();
        $totalHours = (float) ($result['total_hours'] ?? 0);
        $interventionCount = (int) ($result['intervention_count'] ?? 0);

        // Coût horaire moyen: 25€/h, frais de déplacement: 15€/intervention
        $hourlyCost = $totalHours * 25;
        $travelCost = $interventionCount * 15;

        return $hourlyCost + $travelCost;
    }

    /**
     * Calcul de l'évolution par rapport à la période précédente
     */
    private function calculateChange(string $metric, string $currentFrom, string $currentTo): array
    {
        $periodLength = strtotime($currentTo) - strtotime($currentFrom);
        $previousFrom = date('Y-m-d', strtotime($currentFrom) - $periodLength);
        $previousTo = date('Y-m-d', strtotime($currentFrom) - 86400);

        $currentValue = 0;
        $previousValue = 0;

        if ($metric === 'revenue') {
            $sql = "SELECT COALESCE(SUM(total_amount), 0) FROM invoices WHERE status = 'paid' AND issue_date BETWEEN :from AND :to";
            
            $stmt = $this->invoiceModel->query($sql, [':from' => $currentFrom, ':to' => $currentTo]);
            $currentValue = (float) $stmt->fetchColumn();
            
            $stmt = $this->invoiceModel->query($sql, [':from' => $previousFrom, ':to' => $previousTo]);
            $previousValue = (float) $stmt->fetchColumn();
        } elseif ($metric === 'interventions') {
            $sql = "SELECT COUNT(*) FROM interventions WHERE scheduled_date BETWEEN :from AND :to AND status IN ('completed', 'validated')";
            
            $stmt = $this->interventionModel->query($sql, [':from' => $currentFrom, ':to' => $currentTo]);
            $currentValue = (int) $stmt->fetchColumn();
            
            $stmt = $this->interventionModel->query($sql, [':from' => $previousFrom, ':to' => $previousTo]);
            $previousValue = (int) $stmt->fetchColumn();
        }

        $change = $previousValue > 0 ? (($currentValue - $previousValue) / $previousValue) * 100 : 0;

        return [
            'value' => round($change, 1),
            'direction' => $change >= 0 ? 'up' : 'down'
        ];
    }

    /**
     * Calcul du taux de rétention
     */
    private function calculateRetentionRate(): float
    {
        $data = $this->getClientRetentionData();
        return $data['retention_rate'];
    }

    /**
     * Rapport détaillé de rentabilité
     */
    public function profitabilityReport(): void
    {
        $this->requireAuth();
        $this->requireRole($this->getAuthUser(), 'admin');

        $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
        $dateTo = $_GET['date_to'] ?? date('Y-m-d');

        $revenue = $this->invoiceModel->getStatistics($dateFrom, $dateTo)['total_paid'];
        $costs = $this->calculateEstimatedCosts($dateFrom, $dateTo);

        $report = [
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'revenue' => $revenue,
            'costs' => [
                'total' => $costs,
                'breakdown' => [
                    'labor' => $costs * 0.7, // 70% main d'œuvre
                    'travel' => $costs * 0.3  // 30% déplacements
                ]
            ],
            'profit' => $revenue - $costs,
            'margin' => $revenue > 0 ? round((($revenue - $costs) / $revenue) * 100, 1) : 0
        ];

        Response::success(['report' => $report]);
    }
}
