import { useState, useEffect } from 'react';
import { PlanningCalendar } from '@/components/planning/PlanningCalendar';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Plus, Filter } from 'lucide-react';
import { Link } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';
import { authService } from '@/services/api';
import type { User } from '@/types';

export function Planning() {
  const { user } = useAuth();
  const [selectedAgent, setSelectedAgent] = useState<number | undefined>(undefined);
  const [agents, setAgents] = useState<User[]>([]);

  useEffect(() => {
    const fetchAgents = async () => {
      try {
        const response = await authService.getAgents();
        setAgents(response.data.data.agents);
      } catch (error) {
        console.error('Error fetching agents:', error);
      }
    };

    if (user?.role === 'admin') {
      fetchAgents();
    }
  }, [user?.role]);

  return (
    <div className="space-y-6 h-[calc(100vh-8rem)]">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
            Planning
          </h1>
          <p className="text-gray-500 dark:text-gray-400">
            Visualisez et gérez le planning des interventions
          </p>
        </div>
        <div className="flex gap-2">
          {user?.role === 'admin' && (
            <Select
              value={selectedAgent?.toString()}
              onValueChange={(value) => setSelectedAgent(value ? parseInt(value) : undefined)}
            >
              <SelectTrigger className="w-48">
                <Filter className="w-4 h-4 mr-2" />
                <SelectValue placeholder="Tous les agents" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="">Tous les agents</SelectItem>
                {agents.map((agent) => (
                  <SelectItem key={agent.id} value={agent.id.toString()}>
                    {agent.first_name} {agent.last_name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          )}
          <Link to="/interventions/new">
            <Button className="bg-teal-500 hover:bg-teal-600">
              <Plus className="w-4 h-4 mr-2" />
              Nouvelle intervention
            </Button>
          </Link>
        </div>
      </div>

      {/* Calendar */}
      <Card className="h-[calc(100%-6rem)]">
        <CardContent className="p-0 h-full">
          <PlanningCalendar
            agentId={selectedAgent}
            onEventClick={(intervention) => {
              // Navigation vers la page de détails
              console.log('Clicked intervention:', intervention);
            }}
            onDateSelect={(date) => {
              // Ouvrir le modal de création
              console.log('Selected date:', date);
            }}
          />
        </CardContent>
      </Card>

      {/* Légende */}
      <div className="flex flex-wrap gap-4 text-sm">
        <div className="flex items-center gap-2">
          <div className="w-3 h-3 rounded-full bg-blue-500" />
          <span>Planifiée</span>
        </div>
        <div className="flex items-center gap-2">
          <div className="w-3 h-3 rounded-full bg-orange-500" />
          <span>En cours</span>
        </div>
        <div className="flex items-center gap-2">
          <div className="w-3 h-3 rounded-full bg-green-500" />
          <span>Terminée</span>
        </div>
        <div className="flex items-center gap-2">
          <div className="w-3 h-3 rounded-full bg-teal-600" />
          <span>Validée</span>
        </div>
        <div className="flex items-center gap-2">
          <div className="w-3 h-3 rounded-full bg-red-500" />
          <span>Annulée</span>
        </div>
      </div>
    </div>
  );
}
