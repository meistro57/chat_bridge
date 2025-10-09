export interface Persona {
  id: string;
  name: string;
  provider?: string;
  provider_label?: string;
  description?: string;
  system_preview?: string;
}

export interface Provider {
  key: string;
  label: string;
  description?: string;
}

export interface Message {
  content: string;
  sender: 'user' | 'agent_a' | 'agent_b';
  timestamp: string;
  persona?: string;
}

export interface ConversationRequest {
  provider_a: string;
  provider_b: string;
  persona_a?: string;
  persona_b?: string;
  starter_message: string;
  max_rounds: number;
  mem_rounds: number; // Add mem_rounds here
  temperature_a: number;
  temperature_b: number;
}

export interface ConversationResponse {
  conversation_id: string;
  status: string;
  starter_message: string;
}