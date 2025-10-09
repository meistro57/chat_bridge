import { useState, useEffect } from 'react';
import { ChatInterface } from './components/ChatInterface';
import { SetupWizard } from './components/SetupWizard';
import { Persona, Provider, ConversationRequest, ConversationResponse } from './types';

function App() {
  const [personas, setPersonas] = useState<Persona[]>([]);
  const [providers] = useState<Provider[]>([]);
  const [conversationId, setConversationId] = useState<string | null>(null);
  const [selectedPersonas, setSelectedPersonas] = useState<{
    agentA?: Persona;
    agentB?: Persona;
  }>({});
  const [setupComplete, setSetupComplete] = useState(false);

  useEffect(() => {
    // Load available personas on app start
    fetchPersonas();
  }, []);

  const fetchPersonas = async () => {
    try {
      const response = await fetch('/api/personas');
      const data = await response.json();
      setPersonas(data.personas || []);
    } catch (error) {
      console.error('Failed to load personas:', error);
    }
  };

  const startConversation = async (request: ConversationRequest) => {
    try {
      const response = await fetch('/api/conversations', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(request),
      });

      if (!response.ok) {
        let message = 'Failed to start conversation';
        const clonedResponse = response.clone();
        try {
          const errorBody = await clonedResponse.json();
          if (errorBody && typeof errorBody.detail === 'string') {
            message = errorBody.detail;
          }
        } catch (parseError) {
          try {
            const text = await response.text();
            if (text) {
              message = text;
            }
          } catch {
            // Ignore secondary parsing errors
          }
        }

        throw new Error(message);
      }

      const data: ConversationResponse = await response.json();
      setConversationId(data.conversation_id);
      setSetupComplete(true);
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Failed to start conversation';
      console.error('Failed to start conversation:', error);
      alert(message);
    }
  };

  const resetSetup = () => {
    setConversationId(null);
    setSetupComplete(false);
    setSelectedPersonas({});
  };

  if (!setupComplete) {
    return (
      <div className="min-h-screen bg-gray-50 dark:bg-gray-900">
        <div className="container mx-auto px-4 py-8">
          <div className="max-w-4xl mx-auto">
            <header className="text-center mb-8">
              <h1 className="text-4xl font-bold text-gray-900 dark:text-white mb-2">
                🌉 Chat Bridge Web
              </h1>
              <p className="text-gray-600 dark:text-gray-400">
                Connect AI agents in real-time conversation
              </p>
            </header>

            <SetupWizard
              personas={personas}
              providers={providers}
              selectedPersonas={selectedPersonas}
              onPersonasChange={setSelectedPersonas}
              onStartConversation={startConversation}
            />
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50 dark:bg-gray-900">
      <ChatInterface
        conversationId={conversationId!}
        onNewConversation={resetSetup}
      />
    </div>
  );
}

export default App;