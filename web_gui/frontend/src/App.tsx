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
  const [isConnected, setIsConnected] = useState(false);
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
        setIsConnected(true);
      };

      ws.onmessage = (event) => {
        const data = JSON.parse(event.data);
        
        if (data.type === 'message') {
          setIsTyping(false);
          setMessages(prev => [...prev, data.data]);
        } else if (data.type === 'conversation_end') {
          setIsConnected(false);
        }
      };

      ws.onerror = () => {
        setIsConnected(false);
      };

      ws.onclose = () => {
        setIsConnected(false);
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
    setIsConnected(false);
  };

  return (
    <div className="relative min-h-screen bg-black text-white overflow-hidden">
      <style>{`
        @import url('https://fonts.googleapis.com/css2?family=VT323&display=swap');
        
        * { 
          font-family: 'MS Sans Serif', sans-serif; 
          -webkit-font-smoothing: none;
          -moz-osx-font-smoothing: none;
        }
        
        .window {
          background: linear-gradient(to bottom, #c8c8c8 0%, #d0d0d0 50%, #e8e8e8 100%);
          border: 2px inset #808080;
          box-shadow: 
            inset 1px 1px 0px #ffffff,
            inset -1px -1px 0px #000000,
            2px 2px 2px rgba(0,0,0,0.5);
        }
        
        .title-bar {
          background: linear-gradient(to right, #000080 0%, #0000a0 100%);
          color: #ffffff;
          padding: 2px 4px;
          text-shadow: 1px 1px 0px #000000;
          font-weight: bold;
        }
        
        .button-3d {
          background: linear-gradient(to bottom, #d0d0d0 0%, #c8c8c8 100%);
          border: 2px outset #c0c0c0;
          padding: 2px 6px;
          color: #000000;
          font-size: 11px;
          text-transform: uppercase;
          font-weight: bold;
          cursor: pointer;
        }
        
        .button-3d:active {
          background: linear-gradient(to bottom, #a0a0a0 0%, #b0b0b0 100%);
          border: 2px inset #c0c0c0;
          padding: 3px 5px 1px 7px;
        }
        
        .input-winamp {
          background: #ffffff;
          border: 2px inset #c0c0c0;
          padding: 2px;
          font-family: 'MS Sans Serif', sans-serif;
          font-size: 11px;
        }
        
        .scrollbar-winamp {
          scrollbar-width: thin;
          scrollbar-color: #c0c0c0 #f0f0f0;
        }
        
        .scrollbar-winamp::-webkit-scrollbar {
          width: 16px;
        }
        
        .scrollbar-winamp::-webkit-scrollbar-track {
          background: #f0f0f0;
          border: 2px inset #c0c0c0;
        }
        
        .scrollbar-winamp::-webkit-scrollbar-thumb {
          background: #c0c0c0;
          border: 1px solid #808080;
        }
        
        .message-bubble-winamp {
          background: #ffffe1;
          border: 1px inset #c0c0c0;
          padding: 4px;
          font-size: 11px;
        }
        
        .agent-winamp { background: #e0f0ff; }
        .user-winamp { background: #f0f0e0; }
        
        .status-indicator {
          background: #c0c0c0;
          border: 1px outset #808080;
          padding: 1px;
          font-size: 10px;
        }
        
        .pulse-winamp {
          animation: pulse-winamp 2s infinite;
        }
        
        @keyframes pulse-winamp {
          0%, 100% { background-color: #c8c8c8; }
          50% { background-color: #a8a8a8; }
        }
        
        .rounded-winamp { border-radius: 0; }
        
        .text-winamp { color: #000000; font-size: 11px; }
        
        .icon-winamp {
          width: 16px;
          height: 16px;
          background: #800000;
          clip-path: polygon(0 0, 100% 0, 50% 100%);
        }
      `}</style>

      <div className="window scrollbar-winamp">
        <div className="title-bar text-sm font-bold">
          Chat Bridge v1.4.0 - Winamp Inspired
          <div className="float-right text-xs">AI Chat Player</div>
        </div>

        {/* Main Content */}
        <div className="flex-1 flex flex-col">
          <div className="flex-1 p-4 overflow-y-auto scrollbar-winamp">
            {!conversationId ? (
              <div className="p-4 bg-gray-100 border border-gray-800 max-w-md mx-auto mt-8">
                <h2 className="text-lg font-bold text-black mb-4">Welcome to Chat Bridge</h2>
                <p className="text-sm text-black mb-4">
                  Connect two AI agents and watch them converse! Select personas and start chatting.
                </p>
                
                {/* Settings Panel */}
                <div className="space-y-4">
                  <div className="grid grid-cols-2 gap-2">
                    <div>
                      <label className="block text-xs text-black mb-1">Max Rounds</label>
                      <input
                        type="number"
                        value={maxRounds}
                        onChange={(e) => setMaxRounds(Number(e.target.value))}
                        min="1"
                        max="100"
                        className="input-winamp w-full"
                      />
                    </div>
                    <div>
                      <label className="block text-xs text-black mb-1">Temp A</label>
                      <input
                        type="number"
                        value={temperatureA}
                        onChange={(e) => setTemperatureA(Number(e.target.value))}
                        min="0"
                        max="2"
                        step="0.1"
                        className="input-winamp w-full"
                      />
                    </div>
                  </div>
                  <div className="grid grid-cols-2 gap-2">
                    <div>
                      <label className="block text-xs text-black mb-1">Temp B</label>
                      <input
                        type="number"
                        value={temperatureB}
                        onChange={(e) => setTemperatureB(Number(e.target.value))}
                        min="0"
                        max="2"
                        step="0.1"
                        className="input-winamp w-full"
                      />
                    </div>
                  </div>
                  <div className="grid grid-cols-2 gap-2">
                    <div>
                      <label className="block text-xs text-black mb-1">Provider A</label>
                      <select value={selectedProviderA} onChange={(e) => setSelectedProviderA(e.target.value)} className="input-winamp w-full">
                        <option value="openai">OpenAI</option>
                        <option value="anthropic">Anthropic</option>
                        <option value="gemini">Gemini</option>
                        <option value="deepseek">DeepSeek</option>
                        <option value="ollama">Ollama</option>
                      </select>
                    </div>
                    <div>
                      <label className="block text-xs text-black mb-1">Provider B</label>
                      <select value={selectedProviderB} onChange={(e) => setSelectedProviderB(e.target.value)} className="input-winamp w-full">
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
            ) : (
              <>
                {messages.map((msg, i) => {
                  const isUser = msg.sender === 'user';
                  const isAgentA = msg.sender === 'agent_a';
                  
                  return (
                    <div key={i} className={`mb-3 flex ${isUser ? 'justify-end' : 'justify-start'}`}>
                      <div className={`message-bubble-winamp ${isUser ? 'message-bubble-winamp user-winamp' : 'message-bubble-winamp agent-winamp'} max-w-[70%]`}>
                        <div className="text-xs text-black font-bold mb-1">
                          {isUser ? 'You' : isAgentA ? 'Agent A' : 'Agent B'} - {new Date(msg.timestamp).toLocaleTimeString()}
                        </div>
                        <div className="text-sm text-black">{msg.content}</div>
                      </div>
                    </div>
                  );
                })}
                
                {isTyping && (
                  <div className="mb-3 flex justify-start">
                    <div className="status-indicator pulse-winamp">
                      Processing...
                    </div>
                  </div>
                )}
                
                <div ref={messagesEndRef} />
              </>
            )}
          </div>

          {/* Controls Panel */}
          {!conversationId ? (
            <div className="p-4 bg-gray-300 border-t border-gray-800">
              <div className="flex gap-2 mb-2">
                <button onClick={() => openModal('agentA')} className="button-3d">
                  {selectedPersonaA ? selectedPersonaA.name : 'Select Agent A'}
                </button>
                <button onClick={() => openModal('agentB')} className="button-3d">
                  {selectedPersonaB ? selectedPersonaB.name : 'Select Agent B'}
                </button>
                {conversationId && (
                  <button onClick={resetConversation} className="button-3d">
                    New Session
                  </button>
                )}
              </div>
              
              <div className="flex gap-2">
                <textarea
                  value={inputMessage}
                  onChange={(e) => setInputMessage(e.target.value)}
                  placeholder="Enter starter message..."
                  className="input-winamp flex-1 resize-none"
                  rows={2}
                />
                <button
                  onClick={startConversation}
                  disabled={!selectedPersonaA || !selectedPersonaB || !inputMessage.trim()}
                  className="button-3d"
                >
                  Start
                </button>
              </div>
            </div>
          ) : null}
        </div>
      </div>

      {/* Persona Modal */}
      {isModalOpen && (
        <div className="fixed inset-0 bg-gray-400 flex items-center justify-center z-50 p-8">
          <div className="window w-full max-w-md p-4">
            <div className="title-bar text-sm font-bold mb-4">
              Select Persona
              <button onClick={() => setIsModalOpen(false)} className="float-right button-3d text-xs w-6 h-6">X</button>
            </div>
            
            <div className="space-y-2 scrollbar-winamp max-h-64 overflow-y-auto">
              {personas.map(persona => (
                <div
                  key={persona.id}
                  onClick={() => selectPersona(persona)}
                  className="p-2 border border-gray-600 cursor-pointer hover:bg-gray-200"
                >
                  <div className="font-bold text-sm text-black">{persona.name}</div>
                  <div className="text-xs text-gray-600">{persona.description}</div>
                  {persona.system_preview && (
                    <div className="text-xs text-gray-500 mt-1">{persona.system_preview}</div>
                  )}
                </div>
              ))}
            </div>
          </div>
        </div>
      )}

        </div>
      )}

    </div>
  );
};

export default CyberpunkChatBridge;