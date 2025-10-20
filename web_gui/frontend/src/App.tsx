import { useState, useEffect, useRef } from 'react';

interface Persona {
  id: string;
  name: string;
  description?: string;
  system_preview?: string;
}

interface Message {
  content: string;
  sender: 'user' | 'agent_a' | 'agent_b';
  timestamp: string;
  persona?: string;
}

const CyberpunkChatBridge = () => {
  const [personas, setPersonas] = useState<Persona[]>([]);
  const [selectedPersonaA, setSelectedPersonaA] = useState<Persona | null>(null);
  const [selectedPersonaB, setSelectedPersonaB] = useState<Persona | null>(null);
  const [messages, setMessages] = useState<Message[]>([]);
  const [inputMessage, setInputMessage] = useState('');
  const [selectedProviderA, setSelectedProviderA] = useState('openai');
  const [selectedProviderB, setSelectedProviderB] = useState('anthropic');
  const [maxRounds, setMaxRounds] = useState(50);
  const [temperatureA, setTemperatureA] = useState(0.6);
  const [temperatureB, setTemperatureB] = useState(0.6);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [modalType, setModalType] = useState<'agentA' | 'agentB' | null>(null);
  const [conversationId, setConversationId] = useState<string | null>(null);
  const [isTyping, setIsTyping] = useState(false);
  const messagesEndRef = useRef<HTMLDivElement>(null);
  const wsRef = useRef<WebSocket | null>(null);

  // Removed particle effects for Winamp theme
  // useEffect(() => {
  //   [...]
  // }, []);

  // Fetch personas
  useEffect(() => {
    fetchPersonas();
  }, []);

  // WebSocket connection
  useEffect(() => {
    if (conversationId) {
      const ws = new WebSocket(`ws://localhost:8000/ws/conversations/${conversationId}`);
      wsRef.current = ws;

      ws.onopen = () => {
      };

      ws.onmessage = (event) => {
        const data = JSON.parse(event.data);
        
        if (data.type === 'message') {
          setIsTyping(false);
          setMessages(prev => [...prev, data.data]);
        } else if (data.type === 'conversation_end') {
        }
      };

      ws.onerror = () => {
      };

      ws.onclose = () => {
      };

      return () => {
        ws.close();
      };
    }
  }, [conversationId]);

  // Auto-scroll
  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  const fetchPersonas = async () => {
    try {
      const response = await fetch('/api/personas');
      const data = await response.json();
      setPersonas(data.personas || []);
    } catch (error) {
      console.error('Failed to load personas:', error);
    }
  };

  const startConversation = async () => {
    if (!selectedPersonaA || !selectedPersonaB || !inputMessage.trim()) {
      alert('Please select both personas and enter a starter message');
      return;
    }

    try {
      const response = await fetch('/api/conversations', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          provider_a: selectedProviderA,
          provider_b: selectedProviderB,
          persona_a: selectedPersonaA.id,
          persona_b: selectedPersonaB.id,
          starter_message: inputMessage,
          max_rounds: maxRounds,
          temperature_a: temperatureA,
          temperature_b: temperatureB,
        }),
      });

      if (!response.ok) {
        const errorData = await response.json();
        alert(`Failed to start conversation: ${errorData.detail || 'Unknown error'}`);
        return;
      }

      const data = await response.json();
      setConversationId(data.conversation_id);
      setIsTyping(true);
    } catch (error) {
      console.error('Failed to start conversation:', error);
      alert('Failed to connect to server. Make sure the backend is running.');
    }
  };

  const openModal = (type: 'agentA' | 'agentB') => {
    setModalType(type);
    setIsModalOpen(true);
  };

  const selectPersona = (persona: Persona) => {
    if (modalType === 'agentA') {
      setSelectedPersonaA(persona);
    } else {
      setSelectedPersonaB(persona);
    }
    setIsModalOpen(false);
  };

  const resetConversation = () => {
    setConversationId(null);
    setMessages([]);
    setInputMessage('');
  };

  return (
    <div className="relative min-h-screen bg-gradient-to-br from-gray-900 to-black text-white overflow-hidden">
      <style>{`
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        * { 
          font-family: 'Inter', sans-serif;
        }
        
        .glass-effect {
          background: rgba(30, 30, 40, 0.7);
          backdrop-filter: blur(10px);
          -webkit-backdrop-filter: blur(10px);
          border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .gradient-border {
          position: relative;
          border: 1px solid transparent;
          background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
          background-clip: padding-box;
        }
        
        .gradient-border::before {
          content: '';
          position: absolute;
          top: -1px;
          left: -1px;
          right: -1px;
          bottom: -1px;
          z-index: -1;
          background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
          border-radius: inherit;
        }
        
        .card-hover:hover {
          transform: translateY(-2px);
          box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        }
        
        .persona-card {
          transition: all 0.3s ease;
          cursor: pointer;
        }
        
        .persona-card:hover {
          background: rgba(102, 126, 234, 0.15);
          border-color: rgba(102, 126, 234, 0.5);
        }
        
        .persona-card.selected {
          background: rgba(102, 126, 234, 0.25);
          border-color: #667eea;
        }
        
        .btn-primary {
          background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
          color: white;
          font-weight: 600;
          transition: all 0.3s ease;
          border: none;
        }
        
        .btn-primary:hover:not(:disabled) {
          transform: translateY(-2px);
          box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-primary:disabled {
          opacity: 0.6;
          cursor: not-allowed;
        }
        
        .btn-secondary {
          background: rgba(255, 255, 255, 0.1);
          color: white;
          font-weight: 500;
          transition: all 0.3s ease;
          border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .btn-secondary:hover {
          background: rgba(255, 255, 255, 0.2);
        }
        
        .message-user {
          background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
          color: white;
        }
        
        .message-agent-a {
          background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
          color: white;
        }
        
        .message-agent-b {
          background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
          color: white;
        }
        
        .typing-indicator {
          display: flex;
          align-items: center;
          gap: 4px;
        }
        
        .typing-dot {
          width: 8px;
          height: 8px;
          background: #667eea;
          border-radius: 50%;
          animation: bounce 1.5s infinite;
        }
        
        .typing-dot:nth-child(2) {
          animation-delay: 0.2s;
        }
        
        .typing-dot:nth-child(3) {
          animation-delay: 0.4s;
        }
        
        @keyframes bounce {
          0%, 100% { transform: translateY(0); }
          50% { transform: translateY(-5px); }
        }
        
        .input-field {
          background: rgba(255, 255, 255, 0.05);
          border: 1px solid rgba(255, 255, 255, 0.1);
          color: white;
          transition: all 0.3s ease;
        }
        
        .input-field:focus {
          outline: none;
          border-color: #667eea;
          box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.3);
        }
      `}</style>

      <div className="container mx-auto px-4 py-8 max-w-6xl">
        <header className="text-center mb-12">
          <h1 className="text-4xl md:text-5xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-blue-400 to-purple-500 mb-2">
            Chat Bridge
          </h1>
          <p className="text-gray-400 text-lg">Connect two AI agents and watch them converse</p>
        </header>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          {/* Settings Panel */}
          <div className="lg:col-span-1">
            <div className="glass-effect rounded-xl p-6 card-hover">
              <h2 className="text-2xl font-bold mb-6 text-center">Configuration</h2>
              
              <div className="space-y-6">
                {/* Personas Selection */}
                <div>
                  <h3 className="text-lg font-semibold mb-3 text-gray-200">Select Personas</h3>
                  <div className="space-y-3">
                    <div>
                      <button 
                        onClick={() => openModal('agentA')}
                        className={`w-full text-left p-4 rounded-lg border transition-all ${
                          selectedPersonaA 
                            ? 'border-blue-500 bg-blue-500/10' 
                            : 'border-gray-700 hover:border-gray-500'
                        }`}
                      >
                        <div className="font-medium">
                          {selectedPersonaA ? selectedPersonaA.name : 'Agent A'}
                        </div>
                        {selectedPersonaA && selectedPersonaA.description && (
                          <div className="text-sm text-gray-400 mt-1">
                            {selectedPersonaA.description}
                          </div>
                        )}
                      </button>
                    </div>
                    
                    <div>
                      <button 
                        onClick={() => openModal('agentB')}
                        className={`w-full text-left p-4 rounded-lg border transition-all ${
                          selectedPersonaB 
                            ? 'border-purple-500 bg-purple-500/10' 
                            : 'border-gray-700 hover:border-gray-500'
                        }`}
                      >
                        <div className="font-medium">
                          {selectedPersonaB ? selectedPersonaB.name : 'Agent B'}
                        </div>
                        {selectedPersonaB && selectedPersonaB.description && (
                          <div className="text-sm text-gray-400 mt-1">
                            {selectedPersonaB.description}
                          </div>
                        )}
                      </button>
                    </div>
                  </div>
                </div>
                
                {/* Conversation Settings */}
                <div>
                  <h3 className="text-lg font-semibold mb-3 text-gray-200">Settings</h3>
                  <div className="space-y-4">
                    <div>
                      <label className="block text-sm text-gray-300 mb-2">Max Rounds</label>
                      <input
                        type="number"
                        value={maxRounds}
                        onChange={(e) => setMaxRounds(Number(e.target.value))}
                        min="1"
                        max="100"
                        className="input-field w-full p-3 rounded-lg"
                      />
                    </div>
                    
                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <label className="block text-sm text-gray-300 mb-2">Temp A</label>
                        <input
                          type="number"
                          value={temperatureA}
                          onChange={(e) => setTemperatureA(Number(e.target.value))}
                          min="0"
                          max="2"
                          step="0.1"
                          className="input-field w-full p-3 rounded-lg"
                        />
                      </div>
                      <div>
                        <label className="block text-sm text-gray-300 mb-2">Temp B</label>
                        <input
                          type="number"
                          value={temperatureB}
                          onChange={(e) => setTemperatureB(Number(e.target.value))}
                          min="0"
                          max="2"
                          step="0.1"
                          className="input-field w-full p-3 rounded-lg"
                        />
                      </div>
                    </div>
                    
                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <label className="block text-sm text-gray-300 mb-2">Provider A</label>
                        <select 
                          value={selectedProviderA} 
                          onChange={(e) => setSelectedProviderA(e.target.value)}
                          className="input-field w-full p-3 rounded-lg"
                        >
                          <option value="openai">OpenAI</option>
                          <option value="anthropic">Anthropic</option>
                          <option value="gemini">Gemini</option>
                          <option value="deepseek">DeepSeek</option>
                          <option value="ollama">Ollama</option>
                        </select>
                      </div>
                      <div>
                        <label className="block text-sm text-gray-300 mb-2">Provider B</label>
                        <select 
                          value={selectedProviderB} 
                          onChange={(e) => setSelectedProviderB(e.target.value)}
                          className="input-field w-full p-3 rounded-lg"
                        >
                          <option value="openai">OpenAI</option>
                          <option value="anthropic">Anthropic</option>
                          <option value="gemini">Gemini</option>
                          <option value="deepseek">DeepSeek</option>
                          <option value="ollama">Ollama</option>
                        </select>
                      </div>
                    </div>
                  </div>
                </div>
                
                {/* Start Button */}
                <button
                  onClick={startConversation}
                  disabled={!selectedPersonaA || !selectedPersonaB || !inputMessage.trim()}
                  className="btn-primary w-full py-3 px-6 rounded-lg text-lg"
                >
                  Start Conversation
                </button>
              </div>
            </div>
          </div>
          
          {/* Chat Area */}
          <div className="lg:col-span-2">
            <div className="glass-effect rounded-xl h-full flex flex-col">
              {/* Messages Area */}
              <div className="flex-1 p-6 overflow-y-auto max-h-[600px] scrollbar-thin scrollbar-thumb-gray-600 scrollbar-track-transparent">
                {messages.length === 0 && !conversationId ? (
                  <div className="h-full flex items-center justify-center text-gray-400">
                    <div className="text-center">
                      <div className="text-2xl mb-2">👋</div>
                      <p>Select personas and start a conversation!</p>
                    </div>
                  </div>
                ) : (
                  <>
                    {messages.map((msg, i) => {
                      const isUser = msg.sender === 'user';
                      const isAgentA = msg.sender === 'agent_a';
                      
                      return (
                        <div key={i} className={`mb-6 ${isUser ? 'text-right' : 'text-left'}`}>
                          <div className={`inline-block max-w-[80%] rounded-2xl p-4 shadow-lg ${
                            isUser 
                              ? 'message-user' 
                              : isAgentA 
                                ? 'message-agent-a' 
                                : 'message-agent-b'
                          }`}>
                            <div className="font-bold text-sm mb-1">
                              {isUser ? 'You' : isAgentA ? 'Agent A' : 'Agent B'}
                            </div>
                            <div className="text-sm whitespace-pre-wrap">{msg.content}</div>
                            <div className="text-xs opacity-80 mt-2">
                              {new Date(msg.timestamp).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}
                            </div>
                          </div>
                        </div>
                      );
                    })}
                    
                    {isTyping && (
                      <div className="mb-6">
                        <div className="inline-block rounded-2xl p-4 shadow-lg message-agent-a">
                          <div className="typing-indicator">
                            <div className="typing-dot"></div>
                            <div className="typing-dot"></div>
                            <div className="typing-dot"></div>
                          </div>
                        </div>
                      </div>
                    )}
                    
                    <div ref={messagesEndRef} />
                  </>
                )}
              </div>
              
              {/* Input Area */}
              {!conversationId && (
                <div className="p-6 border-t border-gray-700">
                  <div className="flex gap-3">
                    <textarea
                      value={inputMessage}
                      onChange={(e) => setInputMessage(e.target.value)}
                      placeholder="Enter starter message..."
                      className="input-field flex-1 p-3 rounded-lg resize-none"
                      rows={2}
                    />
                    {conversationId && (
                      <button
                        onClick={resetConversation}
                        className="btn-secondary px-4 rounded-lg self-end"
                      >
                        Reset
                      </button>
                    )}
                  </div>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* Persona Modal */}
      {isModalOpen && (
        <div className="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 p-4">
          <div className="glass-effect rounded-xl w-full max-w-md max-h-[80vh] flex flex-col">
            <div className="p-4 border-b border-gray-700 flex justify-between items-center">
              <h3 className="text-xl font-bold">
                Select {modalType === 'agentA' ? 'Agent A' : 'Agent B'} Persona
              </h3>
              <button 
                onClick={() => setIsModalOpen(false)}
                className="btn-secondary w-8 h-8 rounded-full flex items-center justify-center"
              >
                ×
              </button>
            </div>
            
            <div className="flex-1 overflow-y-auto p-4 space-y-3">
              {personas.map(persona => (
                <div
                  key={persona.id}
                  onClick={() => selectPersona(persona)}
                  className={`persona-card p-4 rounded-lg border cursor-pointer transition-all ${
                    (modalType === 'agentA' && selectedPersonaA?.id === persona.id) || 
                    (modalType === 'agentB' && selectedPersonaB?.id === persona.id)
                      ? 'selected border-blue-500 bg-blue-500/10'
                      : 'border-gray-700'
                  }`}
                >
                  <div className="font-bold text-white">{persona.name}</div>
                  {persona.description && (
                    <div className="text-sm text-gray-300 mt-1">{persona.description}</div>
                  )}
                  {persona.system_preview && (
                    <div className="text-xs text-gray-400 mt-2 line-clamp-2">{persona.system_preview}</div>
                  )}
                </div>
              ))}
            </div>
            
            <div className="p-4 border-t border-gray-700 text-center">
              <button 
                onClick={() => setIsModalOpen(false)}
                className="btn-secondary px-4 py-2 rounded-lg"
              >
                Close
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default CyberpunkChatBridge;