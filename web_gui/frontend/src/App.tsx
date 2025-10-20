// App.tsx
import { useState, useEffect, useMemo, useRef, useCallback } from 'react';
import type { Persona, Message } from './types';

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
  { value: 'ollama', label: 'Ollama' },
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
  idle: 'bg-slate-700/40 text-slate-200',
  configuring: 'bg-amber-500/20 text-amber-200',
  running: 'bg-emerald-500/20 text-emerald-200',
  finished: 'bg-indigo-500/20 text-indigo-200',
  error: 'bg-rose-500/20 text-rose-200',
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
        className="rounded-md border border-white/10 px-2 py-1 text-xs uppercase tracking-wide text-white/80 transition hover:text-white"
        onClick={onClose}
      >
        Dismiss
      </button>
    </div>
  );
};

const PersonaSummary = ({ title, persona, onSelect }: { title: string; persona: Persona | null; onSelect: () => void }) => (
  <div className="space-y-2">
    <p className="text-xs uppercase tracking-wide text-slate-300">{title}</p>
    <button
      type="button"
      onClick={onSelect}
      className={`group w-full rounded-2xl border border-white/10 bg-white/[0.03] p-4 text-left transition hover:border-white/30 hover:bg-white/[0.06] ${
        persona ? 'shadow-lg shadow-indigo-500/10' : 'border-dashed'
      }`}
    >
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-lg font-semibold text-white">
            {persona ? persona.name : 'Choose a persona'}
          </p>
          <p className="mt-1 text-sm text-slate-300">
            {persona?.description ?? 'Open the library to assign a persona to this agent.'}
          </p>
        </div>
        <span className="rounded-full border border-white/20 px-3 py-1 text-xs text-white/70 transition group-hover:border-white/40 group-hover:text-white">
          Configure
        </span>
      </div>
      {persona?.system_preview && (
        <p className="mt-3 line-clamp-2 text-xs text-slate-400">
          {persona.system_preview}
        </p>
      )}
    </button>
  </div>
);

const StatusBadge = ({ status }: { status: ConversationStatus }) => (
  <div className={`inline-flex items-center gap-2 rounded-full px-4 py-2 text-xs font-semibold uppercase tracking-wide ${statusTone[status]}`}>
    <span className="inline-block h-2.5 w-2.5 rounded-full bg-current shadow-lg" />
    {STATUS_COPY[status].label}
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

const CyberpunkChatBridge = () => {
  const [personas, setPersonas] = useState<Persona[]>([]);
  const [isLoadingPersonas, setIsLoadingPersonas] = useState(true);
  const [selectedPersonaA, setSelectedPersonaA] = useState<Persona | null>(null);
  const [selectedPersonaB, setSelectedPersonaB] = useState<Persona | null>(null);
  const [messages, setMessages] = useState<Message[]>([]);
  const [inputMessage, setInputMessage] = useState('');
  const [selectedProviderA, setSelectedProviderA] = useState(PROVIDER_OPTIONS[0]?.value ?? 'openai');
  const [selectedProviderB, setSelectedProviderB] = useState(PROVIDER_OPTIONS[1]?.value ?? 'anthropic');
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

  useEffect(() => {
    const fetchPersonas = async () => {
      setIsLoadingPersonas(true);
      try {
        const response = await fetch('/api/personas');
        if (!response.ok) {
          throw new Error(`Server responded with ${response.status}`);
        }
        const data = await response.json();
        setPersonas(data.personas ?? []);
        setBanner(null);
      } catch (error) {
        console.error('Failed to load personas:', error);
        setBanner({
          type: 'error',
          message: 'Could not fetch personas from the server. Please confirm the backend is online and refresh.',
        });
      } finally {
        setIsLoadingPersonas(false);
      }
    };

    fetchPersonas();
  }, []);

  useEffect(() => {
    if (!conversationId) {
      return undefined;
    }

    const ws = new WebSocket(`ws://localhost:8000/ws/conversations/${conversationId}`);
    wsRef.current = ws;

    ws.onopen = () => {
      setBanner({ type: 'info', message: 'Agents connected. The dialogue will appear here in real-time.' });
    };

    ws.onmessage = (event) => {
      try {
        const payload = JSON.parse(event.data);
        if (payload.type === 'message') {
          setIsTyping(false);
          setConversationStatus('running');
          setMessages((prev) => [...prev, payload.data as Message]);
        }
        if (payload.type === 'conversation_end') {
          setConversationStatus('finished');
          setBanner({
            type: 'success',
            message: 'Conversation ended gracefully. Reset the stage to host another debate.',
          });
        }
      } catch (error) {
        console.error('Unable to parse WebSocket payload', error);
      }
    };

    ws.onerror = () => {
      setConversationStatus('error');
      setBanner({ type: 'error', message: 'WebSocket connection failed. Please reset and try again.' });
    };

    ws.onclose = () => {
      wsRef.current = null;
    };

    return () => {
      ws.close();
    };
  }, [conversationId]);

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  const closeModal = useCallback(() => {
    setIsModalOpen(false);
    setModalType(null);
    setPersonaSearchTerm('');
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

  const renderProviderSelect = (
    label: string,
    value: string,
    onChange: (next: string) => void,
  ) => (
    <label className="flex flex-col gap-2 text-sm text-slate-200">
      <span className="text-xs uppercase tracking-wide text-slate-400">{label}</span>
      <select
        value={value}
        onChange={(event) => onChange(event.target.value)}
        className="rounded-xl border border-white/10 bg-white/[0.03] px-3 py-2 text-white shadow-inner shadow-black/20 transition focus:border-indigo-400 focus:outline-none"
      >
        {PROVIDER_OPTIONS.map((option) => (
          <option key={option.value} value={option.value} className="bg-slate-900 text-white">
            {option.label}
          </option>
        ))}
      </select>
    </label>
  );

  return (
    <div className="relative min-h-screen overflow-hidden bg-gradient-to-br from-slate-950 via-indigo-950 to-slate-900 text-white">
      <div className="pointer-events-none absolute inset-0 opacity-60" aria-hidden="true">
        <div className="absolute -left-24 top-32 h-72 w-72 rounded-full bg-indigo-600/40 blur-3xl" />
        <div className="absolute right-0 top-0 h-96 w-96 rounded-full bg-purple-500/20 blur-3xl" />
        <div className="absolute bottom-0 left-1/2 h-64 w-64 -translate-x-1/2 rounded-full bg-blue-600/30 blur-3xl" />
      </div>

      <div className="relative z-10 mx-auto flex min-h-screen w-full max-w-6xl flex-col gap-8 px-4 py-10">
        <header className="space-y-4 text-center lg:text-left">
          <div className="inline-flex items-center gap-3 rounded-full border border-white/10 bg-white/[0.04] px-5 py-2 text-xs font-semibold uppercase tracking-widest text-white/70">
            <span role="img" aria-label="spark">✨</span>
            Orchestrate meaningful multi-agent conversations
          </div>
          <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div className="space-y-3">
              <h1 className="text-4xl font-bold sm:text-5xl">Chat Bridge Studio</h1>
              <p className="max-w-2xl text-base text-slate-300 sm:text-lg">
                Configure two AI personas, calibrate their tone, and launch beautifully rendered debates complete with live feedback and session history.
              </p>
            </div>
            <StatusBadge status={conversationStatus} />
          </div>
          <p className="text-sm text-slate-400">{STATUS_COPY[conversationStatus].description}</p>
        </header>

        {banner && <Banner banner={banner} onClose={() => setBanner(null)} />}

        <div className="grid flex-1 gap-6 lg:grid-cols-[360px,1fr]">
          <aside className="space-y-6 rounded-3xl border border-white/10 bg-white/[0.04] p-6 shadow-2xl shadow-indigo-900/30 backdrop-blur-xl">
            <h2 className="text-lg font-semibold">Agent configuration</h2>
            <p className="text-sm text-slate-400">Select personas, providers, and temperatures to craft the perfect dialogue.</p>

            <div className="space-y-5">
              <PersonaSummary title="Agent A" persona={selectedPersonaA} onSelect={() => openModal('agentA')} />
              <PersonaSummary title="Agent B" persona={selectedPersonaB} onSelect={() => openModal('agentB')} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              <label className="flex flex-col gap-2 text-sm text-slate-200">
                <span className="text-xs uppercase tracking-wide text-slate-400">Max rounds</span>
                <input
                  type="number"
                  value={maxRounds}
                  min={1}
                  max={100}
                  onChange={(event) => setMaxRounds(Number(event.target.value))}
                  className="rounded-xl border border-white/10 bg-white/[0.03] px-3 py-2 text-white shadow-inner shadow-black/20 transition focus:border-indigo-400 focus:outline-none"
                />
              </label>
              <label className="flex flex-col gap-2 text-sm text-slate-200">
                <span className="text-xs uppercase tracking-wide text-slate-400">Starter tone</span>
                <div className="rounded-xl border border-white/10 bg-white/[0.03] px-3 py-2 text-white text-sm">
                  {inputMessage.trim().length > 0 ? `${inputMessage.trim().length} characters` : 'Awaiting your starter prompt'}
                </div>
              </label>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              <label className="flex flex-col gap-2 text-sm text-slate-200">
                <span className="text-xs uppercase tracking-wide text-slate-400">Temperature A</span>
                <input
                  type="number"
                  value={temperatureA}
                  min={0}
                  max={2}
                  step={0.1}
                  onChange={(event) => setTemperatureA(Number(event.target.value))}
                  className="rounded-xl border border-white/10 bg-white/[0.03] px-3 py-2 text-white shadow-inner shadow-black/20 transition focus:border-indigo-400 focus:outline-none"
                />
              </label>
              <label className="flex flex-col gap-2 text-sm text-slate-200">
                <span className="text-xs uppercase tracking-wide text-slate-400">Temperature B</span>
                <input
                  type="number"
                  value={temperatureB}
                  min={0}
                  max={2}
                  step={0.1}
                  onChange={(event) => setTemperatureB(Number(event.target.value))}
                  className="rounded-xl border border-white/10 bg-white/[0.03] px-3 py-2 text-white shadow-inner shadow-black/20 transition focus:border-indigo-400 focus:outline-none"
                />
              </label>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              {renderProviderSelect('Provider A', selectedProviderA, setSelectedProviderA)}
              {renderProviderSelect('Provider B', selectedProviderB, setSelectedProviderB)}
            </div>

            <div className="space-y-3">
              <label className="text-xs uppercase tracking-wide text-slate-400">Starter message</label>
              <textarea
                value={inputMessage}
                onChange={(event) => setInputMessage(event.target.value)}
                placeholder="Describe the topic the agents should explore..."
                rows={4}
                className="w-full resize-none rounded-2xl border border-white/10 bg-white/[0.03] px-4 py-3 text-sm text-white shadow-inner shadow-black/20 transition focus:border-indigo-400 focus:outline-none"
              />
              <div className="flex flex-col gap-3 sm:flex-row">
                <button
                  type="button"
                  onClick={startConversation}
                  disabled={!canStartConversation}
                  className="flex-1 rounded-2xl bg-gradient-to-r from-indigo-500 via-purple-500 to-blue-500 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-900/40 transition hover:shadow-xl disabled:cursor-not-allowed disabled:opacity-50"
                >
                  {conversationStatus === 'configuring' ? 'Launching…' : 'Launch conversation'}
                </button>
                <button
                  type="button"
                  onClick={resetConversation}
                  className="rounded-2xl border border-white/10 px-6 py-3 text-sm font-semibold text-white/70 transition hover:border-white/30 hover:text-white"
                >
                  Reset
                </button>
              </div>
            </div>
          </aside>

          <section className="flex flex-col rounded-3xl border border-white/10 bg-black/40 shadow-2xl shadow-indigo-900/30 backdrop-blur-xl">
            <div className="border-b border-white/5 px-6 py-5">
              <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <h2 className="text-lg font-semibold">Live conversation</h2>
                  <p className="text-sm text-slate-400">
                    {conversationId ? `Conversation ID: ${conversationId}` : 'No active conversation yet. Configure the agents to begin.'}
                  </p>
                </div>
                {conversationId && (
                  <div className="flex items-center gap-3 text-xs text-slate-300">
                    <div className="flex items-center gap-1">
                      <span className={`h-2.5 w-2.5 rounded-full ${conversationStatus === 'running' ? 'bg-emerald-400 animate-pulse' : 'bg-slate-500'}`} />
                      {conversationStatus === 'running' ? 'Streaming' : 'Standing by'}
                    </div>
                    <span className="hidden sm:inline">•</span>
                    <div>{messages.length} messages</div>
                  </div>
                )}
              </div>
            </div>

            <div className="flex-1 space-y-6 overflow-y-auto px-6 py-6" role="log" aria-live="polite">
              {messages.length === 0 && conversationStatus === 'idle' && (
                <div className="flex h-full flex-col items-center justify-center rounded-2xl border border-dashed border-white/10 bg-white/[0.02] p-10 text-center">
                  <p className="text-lg font-semibold text-white/80">Ready when you are</p>
                  <p className="mt-2 max-w-md text-sm text-slate-400">
                    Choose two personas, brief them with a starter message, and press “Launch conversation” to watch the dialogue unfold in real-time.
                  </p>
                </div>
              )}

              {messages.map((message, index) => {
                const isUser = message.sender === 'user';
                const isAgentA = message.sender === 'agent_a';
                const bubbleTone = isUser
                  ? 'bg-sky-500/30 border-sky-400/40'
                  : isAgentA
                    ? 'bg-indigo-500/30 border-indigo-400/40'
                    : 'bg-rose-500/25 border-rose-400/40';

                return (
                  <article
                    key={`${message.timestamp}-${index}`}
                    className={`max-w-2xl rounded-3xl border px-5 py-4 shadow-lg shadow-black/20 ${bubbleTone} ${
                      isUser ? 'ml-auto text-right' : 'mr-auto text-left'
                    } animate-fade-in`}
                  >
                    <header className="flex items-center justify-between text-xs font-semibold uppercase tracking-wide text-white/70">
                      <span>{isUser ? 'You' : isAgentA ? 'Agent A' : 'Agent B'}</span>
                      <span>{formatTimestamp(message.timestamp)}</span>
                    </header>
                    <p className="mt-3 whitespace-pre-wrap text-sm leading-relaxed text-slate-100">{message.content}</p>
                  </article>
                );
              })}

              {isTyping && (
                <div className="flex items-center gap-2 rounded-2xl border border-indigo-400/40 bg-indigo-500/20 px-4 py-3 text-sm text-indigo-100 shadow-lg shadow-indigo-900/30">
                  <div className="flex items-center gap-1">
                    <span className="h-2.5 w-2.5 animate-pulse rounded-full bg-indigo-300" />
                    <span className="h-2.5 w-2.5 animate-pulse rounded-full bg-indigo-200" style={{ animationDelay: '0.1s' }} />
                    <span className="h-2.5 w-2.5 animate-pulse rounded-full bg-indigo-100" style={{ animationDelay: '0.2s' }} />
                  </div>
                  <span>Agents are drafting their next response…</span>
                </div>
              )}

              <div ref={messagesEndRef} />
            </div>
          </section>
        </div>
      </div>

      {isModalOpen && (
        <div className="fixed inset-0 z-40 flex items-center justify-center bg-slate-950/70 px-4 py-6 backdrop-blur">
          <div className="flex w-full max-w-xl flex-col gap-4 rounded-3xl border border-white/10 bg-slate-900/90 p-6 shadow-2xl shadow-indigo-900/40">
            <header className="flex items-start justify-between">
              <div>
                <h3 className="text-lg font-semibold">
                  Select {modalType === 'agentA' ? 'Agent A' : 'Agent B'} persona
                </h3>
                <p className="text-sm text-slate-400">Browse the persona library and tap to assign.</p>
              </div>
              <button
                type="button"
                onClick={closeModal}
                className="rounded-full border border-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-white/70 transition hover:border-white/40 hover:text-white"
              >
                Close
              </button>
            </header>

            <div className="relative">
              <input
                value={personaSearchTerm}
                onChange={(event) => setPersonaSearchTerm(event.target.value)}
                placeholder="Search personas by name or description"
                className="w-full rounded-2xl border border-white/10 bg-white/[0.06] px-4 py-3 text-sm text-white shadow-inner shadow-black/40 transition focus:border-indigo-400 focus:outline-none"
              />
              {isLoadingPersonas && (
                <span className="absolute right-4 top-1/2 -translate-y-1/2 text-xs text-slate-300">Loading…</span>
              )}
            </div>

            <div className="grid max-h-80 gap-3 overflow-y-auto pr-2">
              {filteredPersonas.length === 0 && !isLoadingPersonas && (
                <div className="rounded-2xl border border-dashed border-white/10 bg-white/[0.02] p-6 text-center text-sm text-slate-400">
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
                    className={`flex flex-col gap-2 rounded-2xl border px-4 py-3 text-left transition ${
                      isSelected
                        ? 'border-indigo-400 bg-indigo-500/20 text-white'
                        : 'border-white/10 bg-white/[0.04] text-slate-100 hover:border-white/30 hover:bg-white/[0.08]'
                    }`}
                  >
                    <span className="text-sm font-semibold">{persona.name}</span>
                    {persona.description && <span className="text-xs text-slate-300">{persona.description}</span>}
                    {persona.system_preview && (
                      <span className="text-xs text-slate-400 line-clamp-2">{persona.system_preview}</span>
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

export default CyberpunkChatBridge;
