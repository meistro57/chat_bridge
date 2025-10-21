// App.tsx - Windows 95/WinAmp Theme
import { useState, useEffect, useMemo, useRef, useCallback } from 'react';
import type { Persona, Message, Model } from './types';

interface BannerState {
  type: 'info' | 'success' | 'warning' | 'error';
  message: string;
}

type ModalType = 'agentA' | 'agentB' | null;

type ConversationStatus = 'idle' | 'configuring' | 'running' | 'finished' | 'error';

const PROVIDER_OPTIONS = [
  { value: 'openai', label: 'OpenAI' },
  { value: 'anthropic', label: 'Anthropic' },
  { value: 'gemini', label: 'Gemini' },
  { value: 'deepseek', label: 'DeepSeek' },
  { value: 'openrouter', label: 'OpenRouter' },
  { value: 'ollama', label: 'Ollama' },
  { value: 'lmstudio', label: 'LM Studio' },
];

const STATUS_COPY: Record<ConversationStatus, { label: string; description: string }> = {
  idle: {
    label: 'Waiting to begin',
    description: 'Select two personas, tune their providers, and compose a compelling opener.',
  },
  configuring: {
    label: 'Dialling in settings',
    description: 'Submitting your conversation blueprint to the bridge. One moment…',
  },
  running: {
    label: 'Agents live',
    description: 'Both agents are exchanging ideas in real-time. Enjoy the show!',
  },
  finished: {
    label: 'Conversation wrapped',
    description: 'The final turn has landed. Reset to orchestrate another encounter.',
  },
  error: {
    label: 'Connection interrupted',
    description: 'Something went awry. Review the notice below and try again.',
  },
};

const statusTone: Record<ConversationStatus, string> = {
  idle: 'bg-win-gray-300 text-win-gray-600 border-win-gray-400',
  configuring: 'bg-winamp-orange text-win-gray-600 border-win-gray-400',
  running: 'bg-winamp-green text-win-gray-600 border-win-gray-400',
  finished: 'bg-winamp-blue text-win-gray-600 border-win-gray-400',
  error: 'bg-winamp-red text-win-gray-600 border-win-gray-400',
};

const Banner = ({ banner, onClose }: { banner: BannerState | null; onClose: () => void }) => {
  if (!banner) {
    return null;
  }

  const tone = {
    info: 'bg-blue-500/15 border-blue-400/40 text-blue-100',
    success: 'bg-emerald-500/15 border-emerald-400/40 text-emerald-100',
    warning: 'bg-amber-500/15 border-amber-400/40 text-amber-100',
    error: 'bg-rose-500/15 border-rose-400/40 text-rose-100',
  }[banner.type];

  return (
    <div className={`flex items-start gap-3 rounded-xl border px-4 py-3 text-sm ${tone}`}>
      <span className="mt-0.5 text-base">{banner.type === 'error' ? '⚠️' : 'ℹ️'}</span>
      <div className="flex-1 leading-relaxed">{banner.message}</div>
      <button
        type="button"
        className="rounded-md border border-win-gray-400 px-2 py-1 text-xs uppercase tracking-wide text-win-gray-600 transition hover:text-win-gray-800 hover:border-win-gray-600"
        onClick={onClose}
      >
        Dismiss
      </button>
    </div>
  );
};

const PersonaSummary = ({ title, persona, onSelect }: { title: string; persona: Persona | null; onSelect: () => void }) => (
  <div className="space-y-2">
    <p className="text-xs uppercase tracking-wide text-win-gray-600">{title}</p>
    <button
      type="button"
      onClick={onSelect}
      className={`group w-full rounded-lg border border-win-gray-400 bg-win-gray-100 p-4 text-left transition hover:border-win-gray-600 hover:bg-win-gray-200 ${
        persona ? 'shadow-inner shadow-win-gray-500' : 'border-dashed'
      }`}
    >
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-lg font-semibold text-win-gray-800">
            {persona ? persona.name : 'Choose a persona'}
          </p>
          <p className="mt-1 text-sm text-win-gray-600">
            {persona?.description ?? 'Open the library to assign a persona to this agent.'}
          </p>
        </div>
        <span className="rounded-full border border-win-gray-400 px-3 py-1 text-xs text-win-gray-600 transition group-hover:border-win-gray-600 group-hover:text-win-gray-800">
          Configure
        </span>
      </div>
      {persona?.system_preview && (
        <p className="mt-3 line-clamp-2 text-xs text-win-gray-500">
          {persona.system_preview}
        </p>
      )}
    </button>
  </div>
);

const StatusBadge = ({ status }: { status: ConversationStatus }) => (
  <div className={`inline-flex items-center gap-2 rounded-full border px-4 py-2 text-xs font-semibold uppercase tracking-wide ${statusTone[status]}`}>
    <span className="inline-block h-2.5 w-2.5 rounded-full bg-current shadow-lg" />
    {STATUS_COPY[status].label}
  </div>
);

const ProviderStatusIndicator = ({ providerStatus, isLoading }: { providerStatus: Record<string, any>; isLoading: boolean }) => (
  <div className="mb-6 rounded-lg border border-win-gray-400 bg-win-gray-200 p-4 shadow-inner shadow-win-gray-500">
    <h3 className="text-sm font-semibold text-win-gray-800 mb-3">Provider Status</h3>
    <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
      {Object.entries(providerStatus).map(([key, status]: [string, any]) => (
        <div key={key} className="flex flex-col items-center gap-1">
          <div className="text-xs text-win-gray-600 uppercase tracking-wide">{status?.label || key}</div>
          <div className="flex items-center gap-2">
            {isLoading ? (
              <div className="w-2.5 h-2.5 rounded-full bg-win-gray-400 animate-pulse" />
            ) : (
              <div className={`w-2.5 h-2.5 rounded-full ${status?.connected ? 'bg-winamp-green' : 'bg-winamp-red'}`} />
            )}
            <span className="text-xs text-win-gray-700">
              {isLoading ? 'Checking' : (status?.connected ? 'Connected' : 'Needs Auth')}
            </span>
          </div>
        </div>
      ))}
    </div>
    <div className="mt-3 text-xs text-win-gray-500 text-center">
      {isLoading ? 'Checking provider connectivity...' : 'Display shows which providers have valid API keys configured.'}
    </div>
  </div>
);

const formatTimestamp = (timestamp: string) => {
  try {
    return new Date(timestamp).toLocaleTimeString([], {
      hour: '2-digit',
      minute: '2-digit',
    });
  } catch (error) {
    console.error('Unable to format timestamp', error);
    return timestamp;
  }
};

const Windows95ChatBridge = () => {
  const [personas, setPersonas] = useState<Persona[]>([]);
  const [isLoadingPersonas, setIsLoadingPersonas] = useState(true);
  const [selectedPersonaA, setSelectedPersonaA] = useState<Persona | null>(null);
  const [selectedPersonaB, setSelectedPersonaB] = useState<Persona | null>(null);
  const [messages, setMessages] = useState<Message[]>([]);
  const [inputMessage, setInputMessage] = useState('');
  const [selectedProviderA, setSelectedProviderA] = useState(PROVIDER_OPTIONS[0]?.value ?? 'openai');
  const [selectedProviderB, setSelectedProviderB] = useState(PROVIDER_OPTIONS[1]?.value ?? 'anthropic');
  const [selectedModelA, setSelectedModelA] = useState<string>('');
  const [selectedModelB, setSelectedModelB] = useState<string>('');
  const [modelsA, setModelsA] = useState<Model[]>([]);
  const [modelsB, setModelsB] = useState<Model[]>([]);
  const [isLoadingModelsA, setIsLoadingModelsA] = useState(false);
  const [isLoadingModelsB, setIsLoadingModelsB] = useState(false);
  const [maxRounds, setMaxRounds] = useState(24);
  const [temperatureA, setTemperatureA] = useState(0.7);
  const [temperatureB, setTemperatureB] = useState(0.7);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [modalType, setModalType] = useState<ModalType>(null);
  const [personaSearchTerm, setPersonaSearchTerm] = useState('');
  const [conversationId, setConversationId] = useState<string | null>(null);
  const [isTyping, setIsTyping] = useState(false);
  const [conversationStatus, setConversationStatus] = useState<ConversationStatus>('idle');
  const [banner, setBanner] = useState<BannerState | null>(null);

  const messagesEndRef = useRef<HTMLDivElement>(null);
  const wsRef = useRef<WebSocket | null>(null);

  const [providerStatus, setProviderStatus] = useState<Record<string, any>>({});
  const [isLoadingProviderStatus, setIsLoadingProviderStatus] = useState(true);

  const fetchProviderStatus = async () => {
    setIsLoadingProviderStatus(true);
    try {
      const response = await fetch('/api/provider-status');
      if (!response.ok) {
        throw new Error(`Server responded with ${response.status}`);
      }
      const data = await response.json();
      setProviderStatus(data.providers ?? {});
    } catch (error) {
      console.error('Failed to fetch provider status:', error);
      // Set all providers to disconnected on error
      const disconnectedStatus: Record<string, any> = {};
      Object.keys(PROVIDER_OPTIONS.reduce((acc, opt) => ({ ...acc, [opt.value]: true }), {})).forEach(key => {
        disconnectedStatus[key] = { connected: false, label: key, error: 'Status check failed' };
      });
      setProviderStatus(disconnectedStatus);
    } finally {
      setIsLoadingProviderStatus(false);
    }
  };

  const fetchModels = async (provider: string, isAgentA: boolean) => {
    if (isAgentA) {
      setIsLoadingModelsA(true);
    } else {
      setIsLoadingModelsB(true);
    }
    try {
      const response = await fetch(`/api/models?provider=${provider}`);
      if (!response.ok) {
        throw new Error(`Failed to fetch models for ${provider}`);
      }
      const data = await response.json();
      const models = data.models ?? [];
      if (isAgentA) {
        setModelsA(models);
        if (models.length > 0) {
          setSelectedModelA(models[0].id);
        }
      } else {
        setModelsB(models);
        if (models.length > 0) {
          setSelectedModelB(models[0].id);
        }
      }
    } catch (error) {
      console.error('Failed to fetch models:', error);
    } finally {
      if (isAgentA) {
        setIsLoadingModelsA(false);
      } else {
        setIsLoadingModelsB(false);
      }
    }
  };

  useEffect(() => {
    fetchModels(selectedProviderA, true);
  }, [selectedProviderA]);

  useEffect(() => {
    fetchModels(selectedProviderB, false);
  }, [selectedProviderB]);

  useEffect(() => {
    if (!conversationId) {
      return undefined;
    }

    const ws = new WebSocket(`ws://localhost:8000/ws/conversations/${conversationId}`);
    wsRef.current = ws;

    ws.onopen = () => {
      console.log('WebSocket connected');
      setConversationStatus('running');
      setBanner({ type: 'success', message: 'Connected! Agents are now conversing in real-time.' });
    };

    ws.onmessage = (event) => {
      try {
        const data = JSON.parse(event.data);
        console.log('Received message:', data);

        if (data.type === 'message' && data.data) {
          setIsTyping(false);
          setMessages((prev) => [
            ...prev,
            {
              content: data.data.content,
              sender: data.data.sender,
              timestamp: data.data.timestamp || new Date().toISOString(),
              persona: data.data.persona,
            },
          ]);
          setIsTyping(true); // Show typing indicator for next message
        } else if (data.type === 'complete') {
          setIsTyping(false);
          setConversationStatus('finished');
          setBanner({ type: 'success', message: 'Conversation completed successfully!' });
        } else if (data.type === 'error') {
          setIsTyping(false);
          setConversationStatus('error');
          setBanner({ type: 'error', message: data.message || 'An error occurred during the conversation.' });
        }
      } catch (error) {
        console.error('Failed to parse WebSocket message:', error);
      }
    };

    ws.onerror = (error) => {
      console.error('WebSocket error:', error);
      setIsTyping(false);
      setConversationStatus('error');
      setBanner({ type: 'error', message: 'WebSocket connection error. Check that the backend is running.' });
    };

    ws.onclose = () => {
      console.log('WebSocket closed');
      setIsTyping(false);
      if (conversationStatus === 'running') {
        setConversationStatus('finished');
      }
    };

    return () => {
      ws.close();
      wsRef.current = null;
    };
  }, [conversationId]);

  useEffect(() => {
    messagesEndRef?.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  const closeModal = useCallback(() => {
    setIsModalOpen(false);
    setModalType(null);
    setPersonaSearchTerm('');
  }, []);

  useEffect(() => {
    fetchProviderStatus();

    // Fetch personas
    const fetchPersonas = async () => {
      setIsLoadingPersonas(true);
      try {
        const response = await fetch('/api/personas');
        if (!response.ok) {
          throw new Error(`Failed to fetch personas: ${response.status}`);
        }
        const data = await response.json();
        setPersonas(data.personas ?? []);
      } catch (error) {
        console.error('Failed to fetch personas:', error);
        setPersonas([]);
      } finally {
        setIsLoadingPersonas(false);
      }
    };

    fetchPersonas();
  }, []);

  useEffect(() => {
    if (!isModalOpen) {
      return undefined;
    }

    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        closeModal();
      }
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [closeModal, isModalOpen]);

  const filteredPersonas = useMemo(() => {
    if (!personaSearchTerm.trim()) {
      return personas;
    }
    const search = personaSearchTerm.toLowerCase();
    return personas.filter((persona) =>
      [persona.name, persona.description, persona.system_preview]
        .filter(Boolean)
        .some((value) => value!.toLowerCase().includes(search)),
    );
  }, [personaSearchTerm, personas]);

  const canStartConversation =
    !!selectedPersonaA &&
    !!selectedPersonaB &&
    Boolean(inputMessage.trim()) &&
    conversationStatus !== 'configuring' &&
    conversationStatus !== 'running';

  const startConversation = async () => {
    if (!canStartConversation) {
      setBanner({ type: 'warning', message: 'Assign both personas and craft an opener before starting the session.' });
      return;
    }

    try {
      setConversationStatus('configuring');
      setBanner({ type: 'info', message: 'Setting up the bridge. Your agents will begin shortly.' });

      const response = await fetch('/api/conversations', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          provider_a: selectedProviderA,
          provider_b: selectedProviderB,
          persona_a: selectedPersonaA?.id,
          persona_b: selectedPersonaB?.id,
          starter_message: inputMessage,
          max_rounds: maxRounds,
          temperature_a: temperatureA,
          temperature_b: temperatureB,
          model_a: selectedModelA,
          model_b: selectedModelB,
        }),
      });

      if (!response.ok) {
        const errorData = await response.json().catch(() => ({}));
        throw new Error(errorData.detail ?? 'Unexpected error returned by the server.');
      }

      const data = await response.json();
      setMessages([]);
      setConversationId(data.conversation_id);
      setIsTyping(true);
    } catch (error) {
      console.error('Failed to start conversation:', error);
      setConversationStatus('error');
      setBanner({
        type: 'error',
        message: error instanceof Error ? error.message : 'Failed to connect to server. Ensure the backend is running.',
      });
    }
  };

  const openModal = (type: Exclude<ModalType, null>) => {
    setModalType(type);
    setIsModalOpen(true);
  };

  const selectPersona = (persona: Persona) => {
    if (modalType === 'agentA') {
      setSelectedPersonaA(persona);
    }
    if (modalType === 'agentB') {
      setSelectedPersonaB(persona);
    }
    closeModal();
  };

  const resetConversation = () => {
    wsRef.current?.close();
    wsRef.current = null;
    setConversationId(null);
    setMessages([]);
    setIsTyping(false);
    setConversationStatus('idle');
    setBanner({ type: 'info', message: 'Conversation reset. Adjust the settings and launch another exchange.' });
  };

  const renderProviderModelSelect = (
    label: string,
    provider: string,
    model: string,
    onProviderChange: (next: string) => void,
    onModelChange: (next: string) => void,
    models: Model[],
    isLoading: boolean,
  ) => (
    <div className="flex flex-col gap-2">
      <label className="flex flex-col gap-2 text-sm text-win-gray-600">
        <span className="text-xs uppercase tracking-wide text-win-gray-600">{label} Provider</span>
        <select
          value={provider}
          onChange={(event) => onProviderChange(event.target.value)}
          className="rounded-lg border border-win-gray-400 bg-win-gray-100 px-3 py-2 text-win-gray-800 shadow-inner shadow-win-gray-300 transition focus:border-win-gray-600 focus:outline-none"
        >
          {PROVIDER_OPTIONS.map((option) => (
            <option key={option.value} value={option.value} className="bg-win-gray-100 text-win-gray-800">
              {option.label}
            </option>
          ))}
        </select>
      </label>
      <label className="flex flex-col gap-2 text-sm text-win-gray-600">
        <span className="text-xs uppercase tracking-wide text-win-gray-600">{label} Model</span>
        <select
          value={model}
          onChange={(event) => onModelChange(event.target.value)}
          disabled={isLoading || models.length === 0}
          className="rounded-lg border border-win-gray-400 bg-win-gray-100 px-3 py-2 text-win-gray-800 shadow-inner shadow-win-gray-300 transition focus:border-win-gray-600 focus:outline-none disabled:opacity-50"
        >
          {isLoading ? (
            <option>Loading models...</option>
          ) : models.length === 0 ? (
            <option>No models available</option>
          ) : (
            models.map((m) => (
              <option key={m.id} value={m.id} className="bg-win-gray-100 text-win-gray-800">
                {m.name}
              </option>
            ))
          )}
        </select>
      </label>
    </div>
  );

  return (
    <div className="min-h-screen bg-win-gray-100 font-sans">
      {/* Windows 95 frame */}
      <div className="mx-auto flex min-h-screen w-full max-w-6xl flex-col gap-8 px-4 py-10">
        {/* Title bar */}
        <header className="flex flex-col gap-4 border-b-2 border-win-gray-400 bg-win-gray-300 px-4 py-2 shadow-md">
          <div className="flex flex-row items-center justify-between">
            <div className="flex items-center gap-3">
              <span role="img" aria-label="spark" className="text-xl">🎵</span>
              <h1 className="text-xl font-bold text-win-gray-800">Chat Bridge Studio</h1>
            </div>
            <div className="flex items-center gap-3">
              {conversationId && (
                <button
                  type="button"
                  onClick={async () => {
                    try {
                      const response = await fetch(`/api/conversations/${conversationId}/transcript`);
                      if (!response.ok) {
                        throw new Error(`Server responded with ${response.status}`);
                      }
                      const data = await response.json();
                      
                      // Create a blob and download
                      const blob = new Blob([data.transcript], { type: 'text/markdown' });
                      const url = URL.createObjectURL(blob);
                      const a = document.createElement('a');
                      a.href = url;
                      a.download = data.filename;
                      document.body.appendChild(a);
                      a.click();
                      document.body.removeChild(a);
                      URL.revokeObjectURL(url);
                      
                      setBanner({ type: 'success', message: `Transcript downloaded as ${data.filename}` });
                    } catch (error) {
                      console.error('Failed to download transcript:', error);
                      setBanner({ type: 'error', message: 'Failed to download transcript' });
                    }
                  }}
                  className="rounded-lg border-2 border-win-gray-400 bg-win-gray-200 px-4 py-2 text-sm font-semibold text-win-gray-800 shadow-inner shadow-win-gray-300 transition hover:border-win-gray-600 hover:bg-win-gray-300"
                >
                  📄 Transcript
                </button>
              )}
              <StatusBadge status={conversationStatus} />
            </div>
          </div>
          <p className="text-sm text-win-gray-600">{STATUS_COPY[conversationStatus].description}</p>
        </header>

        <ProviderStatusIndicator 
          providerStatus={providerStatus} 
          isLoading={isLoadingProviderStatus} 
        />

        {banner && <Banner banner={banner} onClose={() => setBanner(null)} />}

        <div className="grid flex-1 gap-6 lg:grid-cols-[360px,1fr]">
          {/* Configuration panel - styled like a Windows 95 dialog box */}
          <aside className="space-y-6 rounded-lg border-2 border-win-gray-400 bg-win-gray-200 p-4 shadow-inner shadow-win-gray-500">
            <h2 className="text-lg font-semibold text-win-gray-800">Agent configuration</h2>
            <p className="text-sm text-win-gray-600">Select personas, providers, and temperatures to craft the perfect dialogue.</p>

            <div className="space-y-5">
              <PersonaSummary title="Agent A" persona={selectedPersonaA} onSelect={() => openModal('agentA')} />
              <PersonaSummary title="Agent B" persona={selectedPersonaB} onSelect={() => openModal('agentB')} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              <label className="flex flex-col gap-2 text-sm text-win-gray-600">
                <span className="text-xs uppercase tracking-wide text-win-gray-600">Max rounds</span>
                <input
                  type="number"
                  value={maxRounds}
                  min={1}
                  max={100}
                  onChange={(event) => setMaxRounds(Number(event.target.value))}
                  className="rounded-lg border border-win-gray-400 bg-win-gray-100 px-3 py-2 text-win-gray-800 shadow-inner shadow-win-gray-300 transition focus:border-win-gray-600 focus:outline-none"
                />
              </label>
              <label className="flex flex-col gap-2 text-sm text-win-gray-600">
                <span className="text-xs uppercase tracking-wide text-win-gray-600">Starter tone</span>
                <div className="rounded-lg border border-win-gray-400 bg-win-gray-100 px-3 py-2 text-win-gray-800 text-sm shadow-inner shadow-win-gray-300">
                  {inputMessage.trim().length > 0 ? `${inputMessage.trim().length} characters` : 'Awaiting your starter prompt'}
                </div>
              </label>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              <label className="flex flex-col gap-2 text-sm text-win-gray-600">
                <span className="text-xs uppercase tracking-wide text-win-gray-600">Temperature A</span>
                <input
                  type="number"
                  value={temperatureA}
                  min={0}
                  max={2}
                  step={0.1}
                  onChange={(event) => setTemperatureA(Number(event.target.value))}
                  className="rounded-lg border border-win-gray-400 bg-win-gray-100 px-3 py-2 text-win-gray-800 shadow-inner shadow-win-gray-300 transition focus:border-win-gray-600 focus:outline-none"
                />
              </label>
              <label className="flex flex-col gap-2 text-sm text-win-gray-600">
                <span className="text-xs uppercase tracking-wide text-win-gray-600">Temperature B</span>
                <input
                  type="number"
                  value={temperatureB}
                  min={0}
                  max={2}
                  step={0.1}
                  onChange={(event) => setTemperatureB(Number(event.target.value))}
                  className="rounded-lg border border-win-gray-400 bg-win-gray-100 px-3 py-2 text-win-gray-800 shadow-inner shadow-win-gray-300 transition focus:border-win-gray-600 focus:outline-none"
                />
              </label>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              {renderProviderModelSelect(
                'A',
                selectedProviderA,
                selectedModelA,
                setSelectedProviderA,
                setSelectedModelA,
                modelsA,
                isLoadingModelsA,
              )}
              {renderProviderModelSelect(
                'B',
                selectedProviderB,
                selectedModelB,
                setSelectedProviderB,
                setSelectedModelB,
                modelsB,
                isLoadingModelsB,
              )}
            </div>

            <div className="space-y-3">
              <label className="text-xs uppercase tracking-wide text-win-gray-600">Starter message</label>
              <textarea
                value={inputMessage}
                onChange={(event) => setInputMessage(event.target.value)}
                placeholder="Describe the topic the agents should explore..."
                rows={4}
                className="w-full resize-none rounded-lg border border-win-gray-400 bg-win-gray-100 px-4 py-3 text-sm text-win-gray-800 shadow-inner shadow-win-gray-300 transition focus:border-win-gray-600 focus:outline-none"
              />
              <div className="flex flex-col gap-3 sm:flex-row">
                <button
                  type="button"
                  onClick={startConversation}
                  disabled={!canStartConversation}
                  className="flex-1 rounded-lg border-2 border-win-gray-400 bg-gradient-to-r from-winamp-teal to-winamp-green px-6 py-3 text-sm font-semibold text-win-gray-800 shadow-inner shadow-win-gray-300 transition hover:shadow-win-gray-500 disabled:cursor-not-allowed disabled:opacity-50 hover:shadow-md"
                >
                  {conversationStatus === 'configuring' ? 'Launching…' : 'Launch conversation'}
                </button>
                <button
                  type="button"
                  onClick={resetConversation}
                  className="rounded-lg border-2 border-win-gray-400 px-6 py-3 text-sm font-semibold text-win-gray-600 shadow-inner shadow-win-gray-300 transition hover:border-win-gray-600 hover:bg-win-gray-300 hover:text-win-gray-800"
                >
                  Reset
                </button>
              </div>
            </div>
          </aside>

          {/* Chat area with window styling */}
          <section className="flex flex-col rounded-lg border-2 border-win-gray-400 bg-win-gray-100 shadow-inner shadow-win-gray-500">
            {/* Chat title bar */}
            <div className="border-b-2 border-win-gray-400 bg-win-gray-300 px-4 py-3 shadow-sm">
              <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <h2 className="text-lg font-semibold text-win-gray-800">Live conversation</h2>
                  <p className="text-sm text-win-gray-600">
                    {conversationId ? `Conversation ID: ${conversationId}` : 'No active conversation yet. Configure the agents to begin.'}
                  </p>
                </div>
                {conversationId && (
                  <div className="flex items-center gap-3 text-xs text-win-gray-600">
                    <div className="flex items-center gap-1">
                      <span className={`h-2.5 w-2.5 rounded-full ${conversationStatus === 'running' ? 'bg-winamp-green animate-pulse' : 'bg-win-gray-400'}`} />
                      {conversationStatus === 'running' ? 'Streaming' : 'Standing by'}
                    </div>
                    <span className="hidden sm:inline">•</span>
                    <div>{messages.length} messages</div>
                  </div>
                )}
              </div>
            </div>

            {/* Chat messages */}
            <div className="h-[calc(100vh-20rem)] min-h-[500px] space-y-4 overflow-y-auto p-4" role="log" aria-live="polite">
              {messages.length === 0 && conversationStatus === 'idle' && (
                <div className="flex h-full flex-col items-center justify-center rounded-lg border-2 border-dashed border-win-gray-400 bg-win-gray-200 p-6 text-center">
                  <p className="text-lg font-semibold text-win-gray-800">Ready when you are</p>
                  <p className="mt-2 max-w-md text-sm text-win-gray-600">
                    Choose two personas, brief them with a starter message, and press “Launch conversation” to watch the dialogue unfold in real-time.
                  </p>
                </div>
              )}

              {messages.map((message, index) => {
                const isUser = message.sender === 'user';
                const isAgentA = message.sender === 'agent_a';
                const textColor = isUser ? 'text-win-gray-800' : isAgentA ? 'text-winamp-blue' : 'text-winamp-red';
                const bgColor = isUser
                  ? 'bg-win-gray-200 border-win-gray-400'
                  : isAgentA
                    ? 'bg-winamp-blue/10 border-winamp-blue/30'
                    : 'bg-winamp-red/10 border-winamp-red/30';

                // Determine display name
                let displayName = 'You';
                if (!isUser) {
                  if (message.persona) {
                    displayName = message.persona;
                  } else if (isAgentA) {
                    displayName = selectedPersonaA?.name || 'Agent A';
                  } else {
                    displayName = selectedPersonaB?.name || 'Agent B';
                  }
                }

                return (
                  <article
                    key={`${message.timestamp}-${index}`}
                    className={`max-w-2xl rounded-lg border px-4 py-3 shadow-inner shadow-win-gray-300 ${bgColor} ${
                      isUser ? 'ml-auto text-right' : 'mr-auto text-left'
                    }`}
                  >
                    <header className="flex items-center justify-between text-xs font-semibold uppercase tracking-wide text-win-gray-600">
                      <span>{displayName}</span>
                      <span>{formatTimestamp(message.timestamp)}</span>
                    </header>
                    <p className={`mt-2 whitespace-pre-wrap text-sm ${textColor}`}>{message.content}</p>
                  </article>
                );
              })}

              {isTyping && (
                <div className="flex items-center gap-2 rounded-lg border-2 border-winamp-blue/30 bg-winamp-blue/10 px-4 py-2 text-sm text-winamp-blue shadow-sm">
                  <div className="flex items-center gap-1">
                    <span className="h-2.5 w-2.5 animate-pulse rounded-full bg-winamp-blue" />
                    <span className="h-2.5 w-2.5 animate-pulse rounded-full bg-winamp-blue" style={{ animationDelay: '0.1s' }} />
                    <span className="h-2.5 w-2.5 animate-pulse rounded-full bg-winamp-blue" style={{ animationDelay: '0.2s' }} />
                  </div>
                  <span>Agents are drafting their next response…</span>
                </div>
              )}

              <div ref={messagesEndRef} />
            </div>
          </section>
        </div>
      </div>

      {/* Modal dialog - styled like a Windows 95 dialog */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-win-gray-300/70 px-4 py-6 backdrop-blur-sm">
          <div className="flex w-full max-w-xl flex-col gap-4 rounded-lg border-2 border-win-gray-400 bg-win-gray-100 p-4 shadow-lg shadow-win-gray-500">
            <header className="flex items-start justify-between border-b-2 border-win-gray-400 pb-2">
              <div>
                <h3 className="text-lg font-semibold text-win-gray-800">
                  Select {modalType === 'agentA' ? 'Agent A' : 'Agent B'} persona
                </h3>
                <p className="text-sm text-win-gray-600">Browse the persona library and tap to assign.</p>
              </div>
              <button
                type="button"
                onClick={closeModal}
                className="rounded border-2 border-win-gray-400 bg-win-gray-200 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-win-gray-600 transition hover:border-win-gray-600 hover:bg-win-gray-300 hover:text-win-gray-800"
              >
                Close
              </button>
            </header>

            <div className="relative">
              <input
                value={personaSearchTerm}
                onChange={(event) => setPersonaSearchTerm(event.target.value)}
                placeholder="Search personas by name or description"
                className="w-full rounded border-2 border-win-gray-400 bg-win-gray-100 px-4 py-2 text-sm text-win-gray-800 shadow-inner shadow-win-gray-300 transition focus:border-win-gray-600 focus:outline-none"
              />
              {isLoadingPersonas && (
                <span className="absolute right-4 top-1/2 -translate-y-1/2 text-xs text-win-gray-500">Loading…</span>
              )}
            </div>

            <div className="grid max-h-[500px] gap-2 overflow-y-auto pr-2">
              {filteredPersonas.length === 0 && !isLoadingPersonas && (
                <div className="rounded-lg border-2 border-dashed border-win-gray-400 bg-win-gray-200 p-4 text-center text-sm text-win-gray-500">
                  No personas match that description. Adjust your search and try again.
                </div>
              )}

              {filteredPersonas.map((persona) => {
                const isSelected =
                  (modalType === 'agentA' && selectedPersonaA?.id === persona.id) ||
                  (modalType === 'agentB' && selectedPersonaB?.id === persona.id);

                return (
                  <button
                    key={persona.id}
                    type="button"
                    onClick={() => selectPersona(persona)}
                    className={`flex flex-col gap-1 rounded-lg border-2 px-4 py-2 text-left transition ${
                      isSelected
                        ? 'border-winamp-teal bg-winamp-teal/10 text-win-gray-800 shadow-inner shadow-win-gray-300'
                        : 'border-win-gray-400 bg-win-gray-100 text-win-gray-600 hover:border-win-gray-600 hover:bg-win-gray-200 hover:shadow-inner hover:shadow-win-gray-300'
                    }`}
                  >
                    <div className="flex items-start justify-between">
                      <span className="text-sm font-semibold">{persona.name}</span>
                      {isSelected && (
                        <span className="inline-flex h-4 w-4 items-center justify-center rounded-full bg-winamp-teal text-xs text-win-gray-800">
                          ✓
                        </span>
                      )}
                    </div>
                    {persona.description && <span className="text-xs text-win-gray-500">{persona.description}</span>}
                    {persona.system_preview && (
                      <span className="text-xs text-win-gray-500 line-clamp-2">{persona.system_preview}</span>
                    )}
                  </button>
                );
              })}
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default Windows95ChatBridge;