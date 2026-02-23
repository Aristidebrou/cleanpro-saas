import { useState } from 'react';
import { useDashboard } from '@/hooks/useDashboard';
import { KPICard } from '@/components/dashboard/KPICard';
import {
  RevenueChart,
  MonthlyComparisonChart,
  InterventionsByTypeChart,
  AgentWorkloadChart,
} from '@/components/dashboard/Charts';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
  Euro,
  Briefcase,
  Users,
  TrendingUp,
  AlertCircle,
  ArrowRight,
  Calendar,
  FileText,
} from 'lucide-react';
import { Link } from 'react-router-dom';

const periodOptions = [
  { value: 'week', label: '7 jours' },
  { value: 'month', label: 'Ce mois' },
  { value: 'quarter', label: 'Ce trimestre' },
  { value: 'year', label: 'Cette année' },
];

export function Dashboard() {
  const [period, setPeriod] = useState('month');
  const { data, loading, error } = useDashboard(period);

  if (error) {
    return (
      <Alert variant="destructive">
        <AlertCircle className="h-4 w-4" />
        <AlertTitle>Erreur</AlertTitle>
        <AlertDescription>{error}</AlertDescription>
      </Alert>
    );
  }

  const kpis = data?.kpis;
  const charts = data?.charts;
  const alerts = data?.alerts;
  const recentActivity = data?.recent_activity;

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
            Dashboard
          </h1>
          <p className="text-gray-500 dark:text-gray-400">
            Vue d'ensemble de votre activité
          </p>
        </div>
        
        {/* Sélecteur de période */}
        <div className="flex gap-2">
          {periodOptions.map((option) => (
            <Button
              key={option.value}
              variant={period === option.value ? 'default' : 'outline'}
              size="sm"
              onClick={() => setPeriod(option.value)}
              className={period === option.value ? 'bg-teal-500 hover:bg-teal-600' : ''}
            >
              {option.label}
            </Button>
          ))}
        </div>
      </div>

      {/* Alertes */}
      {alerts && alerts.length > 0 && (
        <div className="space-y-2">
          {alerts.map((alert, index) => (
            <Alert
              key={index}
              variant={alert.type === 'error' ? 'destructive' : 'default'}
              className={alert.type === 'warning' ? 'border-orange-500 bg-orange-50' : ''}
            >
              <AlertCircle className="h-4 w-4" />
              <AlertTitle>{alert.title}</AlertTitle>
              <AlertDescription className="flex items-center justify-between">
                {alert.message}
                {alert.action && (
                  <Link to={alert.action}>
                    <Button variant="link" size="sm" className="text-teal-600">
                      Voir <ArrowRight className="w-4 h-4 ml-1" />
                    </Button>
                  </Link>
                )}
              </AlertDescription>
            </Alert>
          ))}
        </div>
      )}

      {/* KPIs */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <KPICard
          title="Chiffre d'affaires"
          value={kpis?.revenue.value || 0}
          change={kpis?.revenue.change}
          icon={Euro}
          color="teal"
          currency="€"
          loading={loading}
        />
        <KPICard
          title="Interventions"
          value={kpis?.interventions.value || 0}
          subtitle={`${kpis?.interventions.completed || 0} terminées`}
          change={kpis?.interventions.change}
          icon={Briefcase}
          color="blue"
          loading={loading}
        />
        <KPICard
          title="Clients actifs"
          value={kpis?.clients?.active || 0}
          subtitle={`sur ${kpis?.clients?.total || 0} total`}
          icon={Users}
          color="green"
          loading={loading}
        />
        <KPICard
          title="Rentabilité"
          value={kpis?.profitability.value || 0}
          subtitle={`Marge: ${kpis?.profitability.margin || 0}%`}
          change={kpis?.profitability.change}
          icon={TrendingUp}
          color="purple"
          currency="€"
          loading={loading}
        />
      </div>

      {/* Graphiques */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Évolution du CA */}
        <Card>
          <CardHeader>
            <CardTitle>Évolution du chiffre d'affaires</CardTitle>
          </CardHeader>
          <CardContent>
            {loading ? (
              <div className="h-[300px] flex items-center justify-center">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-teal-500" />
              </div>
            ) : (
              charts?.revenue_evolution && (
                <RevenueChart data={charts.revenue_evolution} />
              )
            )}
          </CardContent>
        </Card>

        {/* Comparaison mensuelle */}
        <Card>
          <CardHeader>
            <CardTitle>Comparaison mensuelle</CardTitle>
          </CardHeader>
          <CardContent>
            {loading ? (
              <div className="h-[300px] flex items-center justify-center">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-teal-500" />
              </div>
            ) : (
              charts?.monthly_comparison && (
                <MonthlyComparisonChart data={charts.monthly_comparison} />
              )
            )}
          </CardContent>
        </Card>

        {/* Interventions par type */}
        <Card>
          <CardHeader>
            <CardTitle>Répartition par type de service</CardTitle>
          </CardHeader>
          <CardContent>
            {loading ? (
              <div className="h-[250px] flex items-center justify-center">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-teal-500" />
              </div>
            ) : (
              charts?.interventions_by_type && (
                <InterventionsByTypeChart data={charts.interventions_by_type} />
              )
            )}
          </CardContent>
        </Card>

        {/* Charge des agents */}
        <Card>
          <CardHeader>
            <CardTitle>Charge de travail des agents</CardTitle>
          </CardHeader>
          <CardContent>
            {loading ? (
              <div className="h-[300px] flex items-center justify-center">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-teal-500" />
              </div>
            ) : (
              charts?.agent_workload && (
                <AgentWorkloadChart data={charts.agent_workload} />
              )
            )}
          </CardContent>
        </Card>
      </div>

      {/* Activité récente */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Interventions récentes */}
        <Card>
          <CardHeader className="flex flex-row items-center justify-between">
            <CardTitle>Interventions à venir</CardTitle>
            <Link to="/interventions">
              <Button variant="link" size="sm" className="text-teal-600">
                Voir tout <ArrowRight className="w-4 h-4 ml-1" />
              </Button>
            </Link>
          </CardHeader>
          <CardContent>
            <div className="space-y-3">
              {recentActivity?.interventions?.slice(0, 5).map((intervention) => (
                <div
                  key={intervention.id}
                  className="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg"
                >
                  <div className="flex items-center gap-3">
                    <div className="w-10 h-10 bg-teal-100 dark:bg-teal-900 rounded-lg flex items-center justify-center">
                      <Calendar className="w-5 h-5 text-teal-600 dark:text-teal-400" />
                    </div>
                    <div>
                      <p className="font-medium text-sm">{intervention.client_company}</p>
                      <p className="text-xs text-gray-500">
                        {intervention.scheduled_date} à {intervention.scheduled_time?.substring(0, 5)}
                      </p>
                    </div>
                  </div>
                  <Badge
                    variant={intervention.status === 'scheduled' ? 'default' : 'secondary'}
                    className={
                      intervention.status === 'scheduled'
                        ? 'bg-blue-100 text-blue-800'
                        : ''
                    }
                  >
                    {intervention.status === 'scheduled' ? 'Planifiée' : intervention.status}
                  </Badge>
                </div>
              ))}
              {(!recentActivity?.interventions || recentActivity.interventions.length === 0) && (
                <p className="text-center text-gray-500 py-4">Aucune intervention à venir</p>
              )}
            </div>
          </CardContent>
        </Card>

        {/* Factures récentes */}
        <Card>
          <CardHeader className="flex flex-row items-center justify-between">
            <CardTitle>Factures récentes</CardTitle>
            <Link to="/invoices">
              <Button variant="link" size="sm" className="text-teal-600">
                Voir tout <ArrowRight className="w-4 h-4 ml-1" />
              </Button>
            </Link>
          </CardHeader>
          <CardContent>
            <div className="space-y-3">
              {recentActivity?.invoices?.slice(0, 5).map((invoice) => (
                <div
                  key={invoice.id}
                  className="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg"
                >
                  <div className="flex items-center gap-3">
                    <div className="w-10 h-10 bg-orange-100 dark:bg-orange-900 rounded-lg flex items-center justify-center">
                      <FileText className="w-5 h-5 text-orange-600 dark:text-orange-400" />
                    </div>
                    <div>
                      <p className="font-medium text-sm">{invoice.invoice_number}</p>
                      <p className="text-xs text-gray-500">{invoice.client_company}</p>
                    </div>
                  </div>
                  <div className="text-right">
                    <p className="font-medium text-sm">
                      {new Intl.NumberFormat('fr-FR', {
                        style: 'currency',
                        currency: 'EUR',
                      }).format(invoice.total_amount)}
                    </p>
                    <Badge
                      variant={invoice.status === 'paid' ? 'default' : 'secondary'}
                      className={
                        invoice.status === 'paid'
                          ? 'bg-green-100 text-green-800'
                          : invoice.status === 'overdue'
                          ? 'bg-red-100 text-red-800'
                          : ''
                      }
                    >
                      {invoice.status === 'paid'
                        ? 'Payée'
                        : invoice.status === 'overdue'
                        ? 'En retard'
                        : 'En attente'}
                    </Badge>
                  </div>
                </div>
              ))}
              {(!recentActivity?.invoices || recentActivity.invoices.length === 0) && (
                <p className="text-center text-gray-500 py-4">Aucune facture récente</p>
              )}
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
