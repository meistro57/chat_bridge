// Type definitions for the chat bridge

export interface Persona {
  id: string;
  name: string;
  description?: string;
  system_preview?: string;
}

export interface Message {
  content: string;
  sender: 'user' | 'agent_a' | 'agent_b';
  timestamp: string;
  persona?: string;
}

export interface Model {
  id: string;
  name: string;
}

export interface ProviderModels {
  provider: string;
  models: Model[];
}

export interface Guide {
  id: string;
  title: string;
  category: string;
  file: string;
  description: string;
}

export interface GuideContent {
  guide_id: string;
  content: string;
}