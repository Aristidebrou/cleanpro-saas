import { useState } from 'react';
import { useInterventions } from '@/hooks/useInterventions';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import {
  Search,
  Plus,
  Filter,
  Calendar,
  MapPin,
  User,
  MoreHorizontal,
  CheckCircle,
  Play,
  X,
} from 'lucide-react';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Link } from 'react-router-dom';

const statusLabels: Record<string, string> = {
  scheduled: 'Planifiée',
  in_progress: 'En cours',
  completed: 'Terminée',
  validated: 'Validée',
  cancelled: 'Annulée',
};

const statusColors: Record<string, string> = {
  scheduled: 'bg-blue-100 text-blue-800',
  in_progress: 'bg-orange-100 text-orange-800',
  completed: 'bg-green-100 text-green-800',
  validated: 'bg-teal-100 text-teal-800',
  cancelled: 'bg-red-100 text-red-800',
};

export function Interventions() {
  const [filters, setFilters] = useState({
    status: '',
    search: '',
    date_from: '',
    date_to: '',
  });

  const { interventions, loading, startIntervention, completeIntervention } = useInterventions({
    status: filters.status || undefined,
    date_from: filters.date_from || undefined,
    date_to: filters.date_to || undefined,
  });

  const filteredInterventions = interventions.filter((intervention) => {
    if (!filters.search) return true;
    const search = filters.search.toLowerCase();
    return (
      intervention.client_company?.toLowerCase().includes(search) ||
      intervention.reference?.toLowerCase().includes(search) ||
      intervention.agent_name?.toLowerCase().includes(search)
    );
  });

  const handleStart = async (id: number) => {
    try {
      await startIntervention(id);
    } catch (error) {
      console.error('Error starting intervention:', error);
    }
  };

  const handleComplete = async (id: number) => {
    try {
      await completeIntervention(id);
    } catch (error) {
      console.error('Error completing intervention:', error);
    }
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
            Interventions
          </h1>
          <p className="text-gray-500 dark:text-gray-400">
            Gérez vos interventions et suivez leur avancement
          </p>
        </div>
        <Link to="/interventions/new">
          <Button className="bg-teal-500 hover:bg-teal-600">
            <Plus className="w-4 h-4 mr-2" />
            Nouvelle intervention
          </Button>
        </Link>
      </div>

      {/* Filtres */}
      <Card>
        <CardContent className="p-4">
          <div className="flex flex-col md:flex-row gap-4">
            <div className="relative flex-1">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
              <Input
                placeholder="Rechercher..."
                value={filters.search}
                onChange={(e) => setFilters({ ...filters, search: e.target.value })}
                className="pl-10"
              />
            </div>
            <Select
              value={filters.status}
              onValueChange={(value) => setFilters({ ...filters, status: value })}
            >
              <SelectTrigger className="w-full md:w-48">
                <Filter className="w-4 h-4 mr-2" />
                <SelectValue placeholder="Statut" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="">Tous les statuts</SelectItem>
                <SelectItem value="scheduled">Planifiées</SelectItem>
                <SelectItem value="in_progress">En cours</SelectItem>
                <SelectItem value="completed">Terminées</SelectItem>
                <SelectItem value="validated">Validées</SelectItem>
                <SelectItem value="cancelled">Annulées</SelectItem>
              </SelectContent>
            </Select>
            <Input
              type="date"
              placeholder="Date début"
              value={filters.date_from}
              onChange={(e) => setFilters({ ...filters, date_from: e.target.value })}
              className="w-full md:w-40"
            />
            <Input
              type="date"
              placeholder="Date fin"
              value={filters.date_to}
              onChange={(e) => setFilters({ ...filters, date_to: e.target.value })}
              className="w-full md:w-40"
            />
          </div>
        </CardContent>
      </Card>

      {/* Liste */}
      <Card>
        <CardContent className="p-0">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Référence</TableHead>
                <TableHead>Client</TableHead>
                <TableHead>Date</TableHead>
                <TableHead>Agent</TableHead>
                <TableHead>Statut</TableHead>
                <TableHead>Montant</TableHead>
                <TableHead className="w-10"></TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {loading ? (
                <TableRow>
                  <TableCell colSpan={7} className="text-center py-8">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-teal-500 mx-auto" />
                  </TableCell>
                </TableRow>
              ) : filteredInterventions.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={7} className="text-center py-8 text-gray-500">
                    Aucune intervention trouvée
                  </TableCell>
                </TableRow>
              ) : (
                filteredInterventions.map((intervention) => (
                  <TableRow key={intervention.id}>
                    <TableCell className="font-medium">
                      {intervention.reference}
                    </TableCell>
                    <TableCell>
                      <div>
                        <p className="font-medium">{intervention.client_company}</p>
                        <p className="text-sm text-gray-500 flex items-center gap-1">
                          <MapPin className="w-3 h-3" />
                          {intervention.client_city}
                        </p>
                      </div>
                    </TableCell>
                    <TableCell>
                      <div className="flex items-center gap-1 text-sm">
                        <Calendar className="w-4 h-4 text-gray-400" />
                        {intervention.scheduled_date}
                        <span className="text-gray-400">
                          à {intervention.scheduled_time?.substring(0, 5)}
                        </span>
                      </div>
                    </TableCell>
                    <TableCell>
                      <div className="flex items-center gap-1 text-sm">
                        <User className="w-4 h-4 text-gray-400" />
                        {intervention.agent_name}
                      </div>
                    </TableCell>
                    <TableCell>
                      <Badge className={statusColors[intervention.status]}>
                        {statusLabels[intervention.status]}
                      </Badge>
                    </TableCell>
                    <TableCell>
                      {new Intl.NumberFormat('fr-FR', {
                        style: 'currency',
                        currency: 'EUR',
                      }).format(intervention.total_amount)}
                    </TableCell>
                    <TableCell>
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="ghost" size="sm">
                            <MoreHorizontal className="w-4 h-4" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                          <DropdownMenuItem asChild>
                            <Link to={`/interventions/${intervention.id}`}>
                              Voir détails
                            </Link>
                          </DropdownMenuItem>
                          {intervention.status === 'scheduled' && (
                            <DropdownMenuItem onClick={() => handleStart(intervention.id)}>
                              <Play className="w-4 h-4 mr-2" />
                              Démarrer
                            </DropdownMenuItem>
                          )}
                          {intervention.status === 'in_progress' && (
                            <DropdownMenuItem onClick={() => handleComplete(intervention.id)}>
                              <CheckCircle className="w-4 h-4 mr-2" />
                              Terminer
                            </DropdownMenuItem>
                          )}
                          {intervention.status === 'scheduled' && (
                            <DropdownMenuItem className="text-red-600">
                              <X className="w-4 h-4 mr-2" />
                              Annuler
                            </DropdownMenuItem>
                          )}
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  );
}
