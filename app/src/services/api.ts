import axios, { type AxiosInstance, type AxiosError } from 'axios';
import type { Client, Intervention, Invoice, Service } from '@/types';

const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api';

// Création de l'instance axios
const api: AxiosInstance = axios.create({
  baseURL: API_URL,
  headers: {
    'Content-Type': 'application/json',
  },
  timeout: 10000,
});

// Intercepteur pour ajouter le token
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => Promise.reject(error)
);

// Intercepteur pour gérer les erreurs
api.interceptors.response.use(
  (response) => response,
  (error: AxiosError) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('token');
      localStorage.removeItem('user');
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);

// Services d'authentification
export const authService = {
  login: (email: string, password: string) =>
    api.post('/auth/login', { email, password }),
  
  register: (data: {
    email: string;
    password: string;
    first_name: string;
    last_name: string;
    role: string;
    phone?: string;
  }) => api.post('/auth/register', data),
  
  getProfile: () => api.get('/auth/profile'),
  
  updateProfile: (data: {
    first_name?: string;
    last_name?: string;
    phone?: string;
  }) => api.put('/auth/profile', data),
  
  changePassword: (current_password: string, new_password: string) =>
    api.post('/auth/change-password', { current_password, new_password }),
  
  getAgents: () => api.get('/auth/agents'),
};

// Services clients
export const clientService = {
  getAll: () => api.get('/clients'),
  
  getById: (id: number) => api.get(`/clients/${id}`),
  
  create: (data: {
    company_name: string;
    email: string;
    contact_name: string;
    password?: string;
    contact_phone?: string;
    address?: string;
    postal_code?: string;
    city?: string;
    siret?: string;
    vat_number?: string;
    billing_type?: string;
    monthly_quota?: number;
    monthly_amount?: number;
    notes?: string;
  }) => api.post('/clients', data),
  
  update: (id: number, data: Partial<Client>) =>
    api.put(`/clients/${id}`, data),
  
  delete: (id: number) => api.delete(`/clients/${id}`),
  
  getStatistics: () => api.get('/clients/statistics'),
  
  // Espace client
  getMyProfile: () => api.get('/client/profile'),
  getMyInterventions: () => api.get('/client/interventions'),
  getMyInvoices: () => api.get('/client/invoices'),
  requestQuote: (data: {
    service_type: string;
    description: string;
    preferred_date?: string;
    estimated_budget?: number;
  }) => api.post('/client/quote-request', data),
};

// Services interventions
export const interventionService = {
  getAll: (params?: {
    client_id?: number;
    agent_id?: number;
    status?: string;
    date_from?: string;
    date_to?: string;
  }) => api.get('/interventions', { params }),
  
  getById: (id: number) => api.get(`/interventions/${id}`),
  
  create: (data: {
    client_id: number;
    agent_id: number;
    scheduled_date: string;
    scheduled_time: string;
    estimated_end_time?: string;
    type?: string;
    notes?: string;
    priority?: string;
    services?: { service_id: number; quantity: number; unit_price: number; notes?: string }[];
  }) => api.post('/interventions', data),
  
  update: (id: number, data: Partial<Intervention>) =>
    api.put(`/interventions/${id}`, data),
  
  start: (id: number) => api.post(`/interventions/${id}/start`),
  
  complete: (id: number, data?: { notes?: string; agent_signature?: string }) =>
    api.post(`/interventions/${id}/complete`, data),
  
  validate: (id: number, data?: { client_signature?: string; feedback?: string; rating?: number }) =>
    api.post(`/interventions/${id}/validate`, data),
  
  cancel: (id: number) => api.post(`/interventions/${id}/cancel`),
  
  getSchedule: (params?: { agent_id?: number; date_from?: string; date_to?: string }) =>
    api.get('/interventions/schedule', { params }),
  
  checkConflicts: (data: {
    agent_id: number;
    date: string;
    start_time: string;
    end_time: string;
    exclude_id?: number;
  }) => api.post('/interventions/check-conflicts', data),
  
  getStatistics: (params?: { date_from?: string; date_to?: string }) =>
    api.get('/interventions/statistics', { params }),
  
  getUpcoming: (limit?: number) =>
    api.get('/interventions/upcoming', { params: { limit } }),
  
  generatePdf: (id: number) => api.get(`/interventions/${id}/pdf`),
};

// Services factures
export const invoiceService = {
  getAll: (params?: {
    client_id?: number;
    status?: string;
    type?: string;
    unpaid_only?: boolean;
  }) => api.get('/invoices', { params }),
  
  getById: (id: number) => api.get(`/invoices/${id}`),
  
  create: (data: {
    client_id: number;
    items: { description: string; quantity: number; unit_price: number; service_id?: number }[];
    type?: string;
    notes?: string;
    due_date?: string;
    discount_amount?: number;
    discount_reason?: string;
    promo_code_id?: number;
  }) => api.post('/invoices', data),
  
  createFromIntervention: (intervention_id: number, promo_code_id?: number) =>
    api.post('/invoices/from-intervention', { intervention_id, promo_code_id }),
  
  update: (id: number, data: Partial<Invoice>) =>
    api.put(`/invoices/${id}`, data),
  
  send: (id: number) => api.post(`/invoices/${id}/send`),
  
  markAsPaid: (id: number, data?: { payment_method?: string; transaction_id?: string }) =>
    api.post(`/invoices/${id}/pay`, data),
  
  cancel: (id: number) => api.post(`/invoices/${id}/cancel`),
  
  getOverdue: () => api.get('/invoices/overdue'),
  
  getStatistics: (params?: { date_from?: string; date_to?: string }) =>
    api.get('/invoices/statistics', { params }),
  
  generatePdf: (id: number) => api.get(`/invoices/${id}/pdf`),
  
  downloadPdf: (id: number) =>
    api.get(`/invoices/${id}/download`, { responseType: 'blob' }),
};

// Services dashboard
export const dashboardService = {
  getDashboard: (period?: string) =>
    api.get('/dashboard', { params: { period } }),
  
  getProfitabilityReport: (params?: { date_from?: string; date_to?: string }) =>
    api.get('/dashboard/profitability', { params }),
};

// Services services
export const serviceService = {
  getAll: () => api.get('/services'),
  
  getById: (id: number) => api.get(`/services/${id}`),
  
  create: (data: {
    name: string;
    description?: string;
    category: string;
    base_price: number;
    unit?: string;
    estimated_duration?: number;
  }) => api.post('/services', data),
  
  update: (id: number, data: Partial<Service>) =>
    api.put(`/services/${id}`, data),
  
  delete: (id: number) => api.delete(`/services/${id}`),
};

export default api;
