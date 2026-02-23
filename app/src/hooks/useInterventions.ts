import { useState, useEffect, useCallback } from 'react';
import { interventionService } from '@/services/api';
import type { Intervention } from '@/types';

interface UseInterventionsOptions {
  client_id?: number;
  agent_id?: number;
  status?: string;
  date_from?: string;
  date_to?: string;
}

export function useInterventions(options: UseInterventionsOptions = {}) {
  const [interventions, setInterventions] = useState<Intervention[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchInterventions = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const response = await interventionService.getAll(options);
      setInterventions(response.data.data.interventions);
    } catch (err: any) {
      setError(err.response?.data?.message || 'Erreur lors du chargement des interventions');
    } finally {
      setLoading(false);
    }
  }, [options.client_id, options.agent_id, options.status, options.date_from, options.date_to]);

  useEffect(() => {
    fetchInterventions();
  }, [fetchInterventions]);

  const createIntervention = async (data: Parameters<typeof interventionService.create>[0]) => {
    const response = await interventionService.create(data);
    await fetchInterventions();
    return response.data;
  };

  const updateIntervention = async (id: number, data: Partial<Intervention>) => {
    const response = await interventionService.update(id, data);
    await fetchInterventions();
    return response.data;
  };

  const startIntervention = async (id: number) => {
    const response = await interventionService.start(id);
    await fetchInterventions();
    return response.data;
  };

  const completeIntervention = async (id: number, data?: { notes?: string; agent_signature?: string }) => {
    const response = await interventionService.complete(id, data);
    await fetchInterventions();
    return response.data;
  };

  const validateIntervention = async (id: number, data?: { client_signature?: string; feedback?: string; rating?: number }) => {
    const response = await interventionService.validate(id, data);
    await fetchInterventions();
    return response.data;
  };

  return {
    interventions,
    loading,
    error,
    refresh: fetchInterventions,
    createIntervention,
    updateIntervention,
    startIntervention,
    completeIntervention,
    validateIntervention,
  };
}

export function useIntervention(id: number | null) {
  const [intervention, setIntervention] = useState<Intervention | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const fetchIntervention = useCallback(async () => {
    if (!id) return;
    
    try {
      setLoading(true);
      setError(null);
      const response = await interventionService.getById(id);
      setIntervention(response.data.data.intervention);
    } catch (err: any) {
      setError(err.response?.data?.message || 'Erreur lors du chargement de l\'intervention');
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    fetchIntervention();
  }, [fetchIntervention]);

  return { intervention, loading, error, refresh: fetchIntervention };
}
