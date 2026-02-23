import { useState, useEffect, useCallback } from 'react';
import { dashboardService } from '@/services/api';
import type { DashboardData, Alert } from '@/types';

export function useDashboard(period: string = 'month') {
  const [data, setData] = useState<DashboardData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchDashboard = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const response = await dashboardService.getDashboard(period);
      setData(response.data.data.dashboard);
    } catch (err: any) {
      setError(err.response?.data?.message || 'Erreur lors du chargement du dashboard');
    } finally {
      setLoading(false);
    }
  }, [period]);

  useEffect(() => {
    fetchDashboard();
  }, [fetchDashboard]);

  return { data, loading, error, refresh: fetchDashboard };
}

export function useAlerts() {
  const [alerts, setAlerts] = useState<Alert[]>([]);
  const [unreadCount, setUnreadCount] = useState(0);

  const dismissAlert = (index: number) => {
    setAlerts(prev => prev.filter((_, i) => i !== index));
  };

  const addAlert = (alert: Alert) => {
    setAlerts(prev => [alert, ...prev]);
    setUnreadCount(prev => prev + 1);
  };

  return { alerts, unreadCount, dismissAlert, addAlert };
}
