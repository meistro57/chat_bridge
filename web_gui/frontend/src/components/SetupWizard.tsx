import { useState } from 'react';
import { PersonaSelector } from './PersonaSelector';
import { Persona, Provider, ConversationRequest } from '../types';

interface SetupWizardProps {
  personas: Persona[];
  providers: Provider[];
  selectedPersonas: {
    agentA?: Persona;
    agentB?: Persona;
  };
  onPersonasChange: (personas: { agentA?: Persona; agentB?: Persona }) => void;
  onStartConversation: (request: ConversationRequest) => void;
}

export function SetupWizard({
  personas,
  providers,
  selectedPersonas,
  onPersonasChange,
  onStartConversation
}: SetupWizardProps) {
  const [activeStep, setActiveStep] = useState<'personas' | 'settings' | 'start'>('personas');
  const [starterMessage, setStarterMessage] = useState('');
  const [maxRounds, setMaxRounds] = useState(30);
  const [temperatureA, setTemperatureA] = useState(0.7);
  const [temperatureB, setTemperatureB] = useState(0.7);

  const handlePersonaSelect = (agent: 'agentA' | 'agentB', persona: Persona | undefined) => {
    onPersonasChange({
      ...selectedPersonas,
      [agent]: persona
    });
  };

  const handleStartConversation = () => {
    if (!starterMessage.trim()) {
      alert('Please enter a starter message');
      return;
    }

    if (!selectedPersonas.agentA || !selectedPersonas.agentB) {
      alert('Please select personas for both agents');
      return;
    }

    const request: ConversationRequest = {
      provider_a: selectedPersonas.agentA.provider,
      provider_b: selectedPersonas.agentB.provider,
      persona_a: selectedPersonas.agentA.id,
      persona_b: selectedPersonas.agentB.id,
      starter_message: starterMessage,
      max_rounds: maxRounds,
      temperature_a: temperatureA,
      temperature_b: temperatureB,
    };

    onStartConversation(request);
  };

  return (
    <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
      {/* Personas step */}
      {activeStep === 'personas' && (
        <div className="space-y-6">
          <div className="text-center">
            <h2 className="text-2xl font-semibold text-gray-900 dark:text-white mb-2">
              Choose AI Personas
            </h2>
            <p className="text-gray-600 dark:text-gray-400">
              Select personalities for each agent in the conversation
            </p>
          </div>

          <div className="grid md:grid-cols-2 gap-6">
            <PersonaSelector
              personas={personas}
              selectedPersona={selectedPersonas.agentA}
              onSelect={(persona) => handlePersonaSelect('agentA', persona)}
              label="Agent A Persona"
              agentName="Agent A"
            />

            <PersonaSelector
              personas={personas}
              selectedPersona={selectedPersonas.agentB}
              onSelect={(persona) => handlePersonaSelect('agentB', persona)}
              label="Agent B Persona"
              agentName="Agent B"
            />
          </div>

          <div className="flex justify-end">
            <button
              onClick={() => setActiveStep('settings')}
              disabled={!selectedPersonas.agentA || !selectedPersonas.agentB}
              className="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              Next: Configure Settings
            </button>
          </div>
        </div>
      )}

      {/* Settings step */}
      {activeStep === 'settings' && (
        <div className="space-y-6">
          <div className="text-center">
            <h2 className="text-2xl font-semibold text-gray-900 dark:text-white mb-2">
              Configure Settings
            </h2>
            <p className="text-gray-600 dark:text-gray-400">
              Adjust conversation parameters
            </p>
          </div>

          <div className="grid md:grid-cols-2 gap-6">
            <div className="space-y-4">
              <h3 className="text-lg font-medium text-gray-900 dark:text-white">
                Agent A Settings
              </h3>
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  Creativity (Temperature): {temperatureA.toFixed(1)}
                </label>
                <input
                  type="range"
                  min="0"
                  max="2"
                  step="0.1"
                  value={temperatureA}
                  onChange={(e) => setTemperatureA(parseFloat(e.target.value))}
                  className="w-full"
                />
                <p className="text-xs text-gray-500 mt-1">
                  Lower = more focused, Higher = more creative
                </p>
              </div>
            </div>

            <div className="space-y-4">
              <h3 className="text-lg font-medium text-gray-900 dark:text-white">
                Agent B Settings
              </h3>
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  Creativity (Temperature): {temperatureB.toFixed(1)}
                </label>
                <input
                  type="range"
                  min="0"
                  max="2"
                  step="0.1"
                  value={temperatureB}
                  onChange={(e) => setTemperatureB(parseFloat(e.target.value))}
                  className="w-full"
                />
                <p className="text-xs text-gray-500 mt-1">
                  Lower = more focused, Higher = more creative
                </p>
              </div>
            </div>
          </div>

          <div className="max-w-xs">
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Max Conversation Rounds: {maxRounds}
            </label>
            <input
              type="range"
              min="5"
              max="100"
              step="5"
              value={maxRounds}
              onChange={(e) => setMaxRounds(parseInt(e.target.value))}
              className="w-full"
            />
          </div>

          <div className="flex justify-between">
            <button
              onClick={() => setActiveStep('personas')}
              className="px-6 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg transition-colors"
            >
              Back
            </button>
            <button
              onClick={() => setActiveStep('start')}
              className="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors"
            >
              Next: Start Conversation
            </button>
          </div>
        </div>
      )}

      {/* Start step */}
      {activeStep === 'start' && (
        <div className="space-y-6">
          <div className="text-center">
            <h2 className="text-2xl font-semibold text-gray-900 dark:text-white mb-2">
              Start Conversation
            </h2>
            <p className="text-gray-600 dark:text-gray-400">
              Enter a topic or question to begin
            </p>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Starter Message
            </label>
            <textarea
              value={starterMessage}
              onChange={(e) => setStarterMessage(e.target.value)}
              placeholder="What would you like the agents to discuss?"
              rows={4}
              className="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>

          <div className="flex justify-between">
            <button
              onClick={() => setActiveStep('settings')}
              className="px-6 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg transition-colors"
            >
              Back
            </button>
            <button
              onClick={handleStartConversation}
              disabled={!starterMessage.trim()}
              className="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              Start Conversation
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
