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

  // Particle generation
  useEffect(() => {
    const particlesContainer = document.getElementById('particles');
    if (!particlesContainer) return;
    
    for (let i = 0; i < 50; i++) {
      const particle = document.createElement('div');
      particle.className = 'particle';
      particle.style.left = Math.random() * 100 + '%';
      particle.style.top = Math.random() * 100 + '%';
      particle.style.animationDelay = Math.random() * 20 + 's';
      particlesContainer.appendChild(particle);
    }
  }, []);

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
        @import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&display=swap');
        
        * { font-family: 'Space Grotesk', sans-serif; }
        
        .particle {
          position: absolute;
          width: 2px;
          height: 2px;
          background: #00ffff;
          border-radius: 50%;
          box-shadow: 0 0 10px #00ffff;
          animation: float 20s infinite;
          opacity: 0.6;
        }
        
        @keyframes float {
          0%, 100% { transform: translate(0, 0) scale(1); }
          25% { transform: translate(100px, -100px) scale(1.5); }
          50% { transform: translate(-50px, -150px) scale(0.8); }
          75% { transform: translate(150px, -50px) scale(1.2); }
        }
        
        .cyber-grid {
          position: fixed;
          top: 0;
          left: 0;
          width: 100%;
          height: 100%;
          background: 
            linear-gradient(0deg, transparent 24%, rgba(0, 255, 255, 0.05) 25%, rgba(0, 255, 255, 0.05) 26%, transparent 27%, transparent 74%, rgba(0, 255, 255, 0.05) 75%, rgba(0, 255, 255, 0.05) 76%, transparent 77%, transparent),
            linear-gradient(90deg, transparent 24%, rgba(0, 255, 255, 0.05) 25%, rgba(0, 255, 255, 0.05) 26%, transparent 27%, transparent 74%, rgba(0, 255, 255, 0.05) 75%, rgba(0, 255, 255, 0.05) 76%, transparent 77%, transparent);
          background-size: 50px 50px;
          animation: gridMove 30s linear infinite;
          opacity: 0.3;
        }
        
        @keyframes gridMove {
          0% { transform: perspective(500px) rotateX(60deg) translateY(0); }
          100% { transform: perspective(500px) rotateX(60deg) translateY(50px); }
        }
        
        .aurora {
          position: fixed;
          top: -50%;
          left: -50%;
          width: 200%;
          height: 200%;
          background: radial-gradient(ellipse at center, rgba(0, 255, 255, 0.15) 0%, transparent 50%),
                      radial-gradient(ellipse at 70% 30%, rgba(255, 0, 255, 0.15) 0%, transparent 50%),
                      radial-gradient(ellipse at 30% 70%, rgba(255, 255, 0, 0.1) 0%, transparent 50%);
          animation: auroraShift 15s ease-in-out infinite;
          pointer-events: none;
        }
        
        @keyframes auroraShift {
          0%, 100% { transform: translate(0, 0) rotate(0deg); opacity: 0.6; }
          33% { transform: translate(5%, -5%) rotate(120deg); opacity: 0.8; }
          66% { transform: translate(-5%, 5%) rotate(240deg); opacity: 0.7; }
        }
        
        .cyber-clip {
          clip-path: polygon(0 0, calc(100% - 10px) 0, 100% 10px, 100% 100%, 10px 100%, 0 calc(100% - 10px));
        }
        
        .message-clip {
          clip-path: polygon(0 0, calc(100% - 12px) 0, 100% 12px, 100% 100%, 12px 100%, 0 calc(100% - 12px));
        }
        
        @keyframes pulse {
          0%, 100% { transform: scale(1); opacity: 1; }
          50% { transform: scale(1.1); opacity: 0.8; }
        }
        
        @keyframes scan {
          0% { transform: translateX(-100%); }
          100% { transform: translateX(100%); }
        }
      `}</style>

      {/* Background Effects */}
      <div id="particles" className="fixed inset-0 pointer-events-none z-0" />
      <div className="cyber-grid" />
      <div className="aurora" />

      {/* Main App */}
      <div className="relative z-10 flex flex-col h-screen">
        {/* Header */}
        <div className="h-[70px] relative overflow-hidden" style={{
          background: 'linear-gradient(135deg, rgba(0, 255, 255, 0.1) 0%, rgba(255, 0, 255, 0.1) 100%)',
          backdropFilter: 'blur(20px) saturate(180%)',
          borderBottom: '2px solid #00ffff',
          boxShadow: '0 0 30px rgba(0, 255, 255, 0.3)'
        }}>
          <div className="absolute inset-0 pointer-events-none" style={{
            background: 'linear-gradient(90deg, transparent 0%, rgba(0, 255, 255, 0.2) 50%, transparent 100%)',
            animation: 'scan 3s linear infinite'
          }} />
          
          <div className="relative z-10 h-full flex items-center justify-between px-5">
            <div className="flex items-center gap-3">
              <span className="text-2xl" style={{ 
                color: '#00ffff',
                textShadow: '0 0 20px #00ffff, 0 0 40px #00ffff',
                animation: 'pulse 2s ease-in-out infinite'
              }}>⚡</span>
              <span className="text-xl font-bold uppercase tracking-wider bg-gradient-to-r from-cyan-400 via-fuchsia-500 to-yellow-400 bg-clip-text text-transparent">
                Chat Bridge Nexus
              </span>
            </div>
            
            <div className="flex gap-2">
              {conversationId && (
                <button
                  onClick={resetConversation}
                  className="px-4 py-2 text-xs font-semibold uppercase tracking-wide border-2 border-cyan-400 text-cyan-400 cyber-clip hover:bg-cyan-400/20 transition-all"
                  style={{ boxShadow: isConnected ? '0 0 20px rgba(0, 255, 255, 0.5)' : 'none' }}
                >
                  New Session
                </button>
              )}
            </div>
          </div>
        </div>

        {/* Chat Area */}
        <div className="flex-1 overflow-hidden flex flex-col">
          <div className="flex-1 overflow-y-auto p-5 space-y-5">
            {!conversationId ? (
              <div className="flex flex-col items-center justify-center h-full text-center px-8">
                <div className="text-6xl mb-5 bg-gradient-to-r from-cyan-400 via-fuchsia-500 to-yellow-400 bg-clip-text text-transparent" style={{
                  animation: 'pulse 3s ease-in-out infinite',
                  filter: 'drop-shadow(0 0 20px rgba(0, 255, 255, 0.5))'
                }}>
                  ⚡
                </div>
                <h2 className="text-3xl font-bold uppercase tracking-widest mb-3 bg-gradient-to-r from-cyan-400 to-fuchsia-500 bg-clip-text text-transparent">
                  AI Nexus
                </h2>
                <p className="text-sm text-cyan-400/70 mb-8 max-w-xs uppercase tracking-wide">
                  Connect AI agents in real-time cyberpunk conversation
                </p>
                
                <div className="grid grid-cols-2 gap-3 max-w-sm">
                  {[
                    { icon: '🤖', title: 'Multi-Agent', desc: 'Connect any AI' },
                    { icon: '⚡', title: 'Real-Time', desc: 'Live streaming' },
                    { icon: '🎭', title: 'Personas', desc: 'Custom roles' },
                    { icon: '🔮', title: 'Quantum', desc: 'Neural bridge' }
                  ].map((feature, i) => (
                    <div key={i} className="p-4 border border-cyan-400/30 cyber-clip hover:bg-cyan-400/10 hover:border-cyan-400 transition-all" style={{
                      background: 'rgba(0, 255, 255, 0.05)'
                    }}>
                      <div className="text-2xl mb-2" style={{ color: '#00ffff', textShadow: '0 0 10px #00ffff' }}>
                        {feature.icon}
                      </div>
                      <div className="text-xs font-semibold uppercase tracking-wide text-cyan-400 mb-1">
                        {feature.title}
                      </div>
                      <div className="text-[10px] text-white/50">
                        {feature.desc}
                      </div>
                    </div>
                  ))}
                </div>

                {/* Settings */}
                <div className="mt-8 w-full max-w-sm grid grid-cols-2 gap-4 mb-4">
                  <div>
                    <label className="block text-xs uppercase tracking-wide text-cyan-400 mb-2">Max Rounds</label>
                    <input
                      type="number"
                      value={maxRounds}
                      onChange={(e) => setMaxRounds(Number(e.target.value))}
                      min="1"
                      max="100"
                      className="w-full p-2 text-sm cyber-clip bg-black/50 border border-cyan-400 text-cyan-400"
                    />
                  </div>
                  <div>
                    <label className="block text-xs uppercase tracking-wide text-cyan-400 mb-2">Temp A</label>
                    <input
                      type="number"
                      value={temperatureA}
                      onChange={(e) => setTemperatureA(Number(e.target.value))}
                      min="0"
                      max="2"
                      step="0.1"
                      className="w-full p-2 text-sm cyber-clip bg-black/50 border border-cyan-400 text-cyan-400"
                    />
                  </div>
                </div>
                <div className="w-full max-w-sm mb-8">
                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <label className="block text-xs uppercase tracking-wide text-fuchsia-400 mb-2">Temp B</label>
                      <input
                        type="number"
                        value={temperatureB}
                        onChange={(e) => setTemperatureB(Number(e.target.value))}
                        min="0"
                        max="2"
                        step="0.1"
                        className="w-full p-2 text-sm cyber-clip bg-black/50 border border-fuchsia-500 text-fuchsia-400"
                      />
                    </div>
                  </div>
                </div>

                {/* Provider Selection */}
                <div className="mt-8 w-full max-w-sm">
                  <div className="grid grid-cols-2 gap-4 mb-4">
                    <div>
                      <label className="block text-xs uppercase tracking-wide text-cyan-400 mb-2">Agent A Provider</label>
                      <select 
                        value={selectedProviderA}
                        onChange={(e) => setSelectedProviderA(e.target.value)}
                        className="w-full p-2 text-sm cyber-clip bg-black/50 border border-cyan-400 text-cyan-400"
                      >
                        <option value="openai">OpenAI</option>
                        <option value="anthropic">Anthropic</option>
                        <option value="gemini">Gemini</option>
                        <option value="deepseek">DeepSeek</option>
                        <option value="ollama">Ollama</option>
                        <option value="lmstudio">LM Studio</option>
                      </select>
                    </div>
                    <div>
                      <label className="block text-xs uppercase tracking-wide text-fuchsia-400 mb-2">Agent B Provider</label>
                      <select 
                        value={selectedProviderB}
                        onChange={(e) => setSelectedProviderB(e.target.value)}
                        className="w-full p-2 text-sm cyber-clip bg-black/50 border border-fuchsia-500 text-fuchsia-400"
                      >
                        <option value="openai">OpenAI</option>
                        <option value="anthropic">Anthropic</option>
                        <option value="gemini">Gemini</option>
                        <option value="deepseek">DeepSeek</option>
                        <option value="ollama">Ollama</option>
                        <option value="lmstudio">LM Studio</option>
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
                    <div key={i} className={`flex gap-3 ${isUser ? 'flex-row-reverse' : ''}`}>
                      <div className="w-10 h-10 flex items-center justify-center font-bold text-base" style={{
                        background: isUser ? 'linear-gradient(135deg, #ffff00, #ff6bc9)' : 'linear-gradient(135deg, #00ffff, #ff00ff)',
                        clipPath: 'polygon(10% 0%, 100% 0%, 90% 100%, 0% 100%)',
                        boxShadow: isUser ? '0 0 20px rgba(255, 255, 0, 0.5)' : '0 0 20px rgba(0, 255, 255, 0.5)'
                      }}>
                        {isUser ? '👤' : '🤖'}
                      </div>
                      
                      <div className="flex-1 max-w-[calc(100%-52px)] p-4 message-clip" style={{
                        background: isUser ? 'rgba(40, 26, 40, 0.8)' : 'rgba(26, 26, 40, 0.8)',
                        backdropFilter: 'blur(10px)',
                        border: '2px solid transparent',
                        borderImage: isUser ? 'linear-gradient(135deg, #ffff00, #ff6bc9) 1' : 'linear-gradient(135deg, #00ffff, #ff00ff) 1'
                      }}>
                        <div className="flex justify-between items-center mb-2">
                          <span className="text-xs font-semibold uppercase tracking-wide" style={{
                            color: isUser ? '#ffff00' : '#00ffff',
                            textShadow: isUser ? '0 0 10px #ffff00' : '0 0 10px #00ffff'
                          }}>
                            {isUser ? 'You' : isAgentA ? 'Agent A' : 'Agent B'}
                          </span>
                          <span className="text-[10px] text-white/40 font-mono">
                            {new Date(msg.timestamp).toLocaleTimeString()}
                          </span>
                        </div>
                        <div className="text-sm leading-relaxed text-gray-200">
                          {msg.content}
                        </div>
                      </div>
                    </div>
                  );
                })}
                
                {isTyping && (
                  <div className="flex items-center gap-2 px-5 py-3 border border-cyan-400 cyber-clip" style={{
                    background: 'rgba(0, 255, 255, 0.1)',
                    boxShadow: '0 0 15px rgba(0, 255, 255, 0.3)'
                  }}>
                    <span className="text-xs text-cyan-400">Processing</span>
                    <div className="flex gap-1">
                      {[0, 1, 2].map(i => (
                        <div key={i} className="w-1.5 h-1.5 bg-cyan-400" style={{
                          boxShadow: '0 0 8px #00ffff',
                          animation: `pulse 1.4s ease-in-out infinite ${i * 0.2}s`
                        }} />
                      ))}
                    </div>
                  </div>
                )}
                
                <div ref={messagesEndRef} />
              </>
            )}
          </div>
        </div>

        {/* Input Area */}
        {!conversationId && (
          <div className="p-4" style={{
            background: 'linear-gradient(135deg, rgba(0, 255, 255, 0.1) 0%, rgba(255, 0, 255, 0.1) 100%)',
            backdropFilter: 'blur(20px)',
            borderTop: '2px solid #00ffff',
            boxShadow: '0 -5px 30px rgba(0, 255, 255, 0.2)'
          }}>
            <div className="flex gap-3 mb-3">
              <button
                onClick={() => openModal('agentA')}
                className="flex-1 p-3 border-2 border-cyan-400 text-cyan-400 cyber-clip hover:bg-cyan-400/20 transition-all text-xs font-semibold uppercase"
              >
                {selectedPersonaA ? `🎭 ${selectedPersonaA.name}` : 'Select Agent A'}
              </button>
              <button
                onClick={() => openModal('agentB')}
                className="flex-1 p-3 border-2 border-fuchsia-500 text-fuchsia-500 cyber-clip hover:bg-fuchsia-500/20 transition-all text-xs font-semibold uppercase"
              >
                {selectedPersonaB ? `🎭 ${selectedPersonaB.name}` : 'Select Agent B'}
              </button>
            </div>
            
            <div className="flex gap-3">
              <textarea
                value={inputMessage}
                onChange={(e) => setInputMessage(e.target.value)}
                placeholder="Enter starter message..."
                className="flex-1 p-4 text-sm cyber-clip resize-none min-h-[48px] max-h-[80px]"
                style={{
                  background: 'rgba(13, 13, 20, 0.9)',
                  border: '2px solid #00ffff',
                  color: '#e0e0ff'
                }}
              />
              <button
                onClick={startConversation}
                disabled={!selectedPersonaA || !selectedPersonaB || !inputMessage.trim()}
                className="w-12 h-12 flex items-center justify-center text-lg disabled:opacity-50"
                style={{
                  background: 'linear-gradient(135deg, #00ffff, #ff00ff)',
                  clipPath: 'polygon(10% 0%, 100% 0%, 90% 100%, 0% 100%)',
                  boxShadow: '0 0 20px rgba(0, 255, 255, 0.5)',
                  color: '#000'
                }}
              >
                ⚡
              </button>
            </div>
          </div>
        )}
      </div>

      {/* Persona Modal */}
      {isModalOpen && (
        <div className="fixed inset-0 bg-black/95 backdrop-blur-xl flex items-center justify-center z-50 p-5">
          <div className="w-full max-w-lg max-h-[80vh] overflow-y-auto p-8" style={{
            background: 'linear-gradient(135deg, rgba(0, 255, 255, 0.1) 0%, rgba(255, 0, 255, 0.1) 100%)',
            backdropFilter: 'blur(30px)',
            border: '2px solid #00ffff',
            clipPath: 'polygon(20px 0, 100% 0, 100% calc(100% - 20px), calc(100% - 20px) 100%, 0 100%, 0 20px)',
            boxShadow: '0 0 50px rgba(0, 255, 255, 0.5)'
          }}>
            <div className="flex justify-between items-center mb-6">
              <h3 className="text-2xl font-bold uppercase tracking-wider bg-gradient-to-r from-cyan-400 to-fuchsia-500 bg-clip-text text-transparent">
                Select Persona
              </h3>
              <button
                onClick={() => setIsModalOpen(false)}
                className="w-9 h-9 flex items-center justify-center border-2 border-cyan-400 text-cyan-400 cyber-clip hover:bg-cyan-400 hover:text-black transition-all"
              >
                ✕
              </button>
            </div>
            
            <div className="space-y-3">
              {personas.map(persona => (
                <div
                  key={persona.id}
                  onClick={() => selectPersona(persona)}
                  className="p-4 border-2 border-cyan-400/30 cyber-clip cursor-pointer hover:bg-cyan-400/10 hover:border-cyan-400 transition-all"
                  style={{ background: 'rgba(0, 255, 255, 0.05)' }}
                >
                  <div className="flex items-center gap-3 mb-2">
                    <div className="w-10 h-10 flex items-center justify-center text-xl" style={{
                      background: 'linear-gradient(135deg, #00ffff, #ff00ff)',
                      clipPath: 'polygon(15% 0%, 100% 0%, 85% 100%, 0% 100%)',
                      boxShadow: '0 0 15px rgba(0, 255, 255, 0.5)'
                    }}>
                      🎭
                    </div>
                    <div className="flex-1">
                      <div className="font-semibold text-cyan-400">{persona.name}</div>
                      <div className="text-xs text-white/50">{persona.description}</div>
                    </div>
                  </div>
                  {persona.system_preview && (
                    <div className="text-xs text-white/70 leading-relaxed">
                      {persona.system_preview}
                    </div>
                  )}
                </div>
              ))}
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default CyberpunkChatBridge;