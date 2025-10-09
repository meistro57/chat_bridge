import { Persona } from '../types';

interface PersonaSelectorProps {
  personas: Persona[];
  selectedPersona?: Persona;
  onSelect: (persona: Persona | undefined) => void;
  label: string;
  agentName: string;
}

export function PersonaSelector({
  personas,
  selectedPersona,
  onSelect,
  label,
  agentName
}: PersonaSelectorProps) {
  return (
    <div className="space-y-2">
      <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
        {label}
      </label>
      <select
        value={selectedPersona?.id || ''}
        onChange={(e) => {
          const persona = personas.find(p => p.id === e.target.value);
          onSelect(persona);
        }}
        className="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
      >
        <option value="">Default AI ({agentName})</option>
        {personas.map(persona => {
          const providerLabel = persona.provider_label || persona.provider;
          const providerSuffix = providerLabel ? ` (${providerLabel})` : '';
          return (
            <option key={persona.id} value={persona.id}>
              🎭 {persona.name}{providerSuffix}
            </option>
          );
        })}
      </select>
      
      {selectedPersona && selectedPersona.system_preview && (
        <div className="mt-2 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
          <p className="text-xs text-gray-600 dark:text-gray-400">
            {selectedPersona.system_preview}
          </p>
        </div>
      )}
    </div>
  );
}
