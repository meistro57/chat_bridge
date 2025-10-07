import { Message } from '../types';

interface MessageBubbleProps {
  message: Message;
}

export function MessageBubble({ message }: MessageBubbleProps) {
  const isUser = message.sender === 'user';
  const isAgentA = message.sender === 'agent_a';
  
  const bubbleColor = isUser
    ? 'bg-blue-600 text-white'
    : isAgentA
    ? 'bg-purple-100 dark:bg-purple-900 text-purple-900 dark:text-purple-100'
    : 'bg-green-100 dark:bg-green-900 text-green-900 dark:text-green-100';
  
  const alignment = isUser ? 'justify-end' : 'justify-start';
  
  const senderIcon = isUser ? '👤' : isAgentA ? '🤖' : '🤖';
  const senderName = isUser ? 'You' : isAgentA ? 'Agent A' : 'Agent B';

  return (
    <div className={`flex ${alignment} gap-2 animate-fade-in`}>
      <div className={`max-w-[70%] ${bubbleColor} rounded-2xl px-4 py-3 shadow-sm`}>
        <div className="flex items-center gap-2 mb-1">
          <span className="text-sm font-semibold">
            {senderIcon} {senderName}
          </span>
          {message.persona && (
            <span className="text-xs opacity-75">
              ({message.persona})
            </span>
          )}
        </div>
        
        <div className="text-sm whitespace-pre-wrap">
          {message.content}
        </div>
        
        <div className="text-xs opacity-70 mt-1">
          {new Date(message.timestamp).toLocaleTimeString()}
        </div>
      </div>
    </div>
  );
}
