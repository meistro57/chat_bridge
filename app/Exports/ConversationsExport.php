<?php

namespace App\Exports;

use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ConversationsExport implements FromQuery, WithHeadings, WithMapping
{
    use Exportable;

    /**
     * @param  array{keyword?:string,date_from?:string,date_to?:string,persona_id?:string,role?:string,status?:string}  $filters
     */
    public function __construct(
        private readonly User $user,
        private readonly array $filters = [],
    ) {}

    public function query(): Builder
    {
        $query = Message::query()
            ->whereHas('conversation', function ($q) {
                $q->where('user_id', $this->user->id);
            })
            ->with(['conversation', 'persona']);

        if (! empty($this->filters['keyword'])) {
            $keyword = $this->filters['keyword'];
            $query->where('content', 'like', "%{$keyword}%");
        }

        if (! empty($this->filters['date_from'])) {
            $query->where('created_at', '>=', $this->filters['date_from']);
        }
        if (! empty($this->filters['date_to'])) {
            $query->where('created_at', '<=', $this->filters['date_to'].' 23:59:59');
        }

        if (! empty($this->filters['persona_id'])) {
            $query->where('persona_id', $this->filters['persona_id']);
        }

        if (! empty($this->filters['role'])) {
            $query->where('role', $this->filters['role']);
        }

        if (! empty($this->filters['status'])) {
            $query->whereHas('conversation', function ($q) {
                $q->where('status', $this->filters['status']);
            });
        }

        return $query->orderByDesc('created_at');
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Date',
            'Time',
            'Conversation ID',
            'Persona',
            'Role',
            'Provider',
            'Model',
            'Tokens Used',
            'Content',
        ];
    }

    /**
     * @return array<int, string|int>
     */
    public function map($message): array
    {
        $conversation = $message->conversation;
        $isPersonaA = $message->persona_id && $conversation && $message->persona_id === $conversation->persona_a_id;
        $provider = $conversation ? ($isPersonaA ? $conversation->provider_a : $conversation->provider_b) : 'N/A';
        $model = $conversation ? ($isPersonaA ? $conversation->model_a : $conversation->model_b) : null;

        return [
            $message->created_at->format('Y-m-d'),
            $message->created_at->format('H:i:s'),
            $message->conversation_id,
            $message->persona->name ?? 'N/A',
            $message->role,
            $provider,
            $model ?? 'N/A',
            $message->tokens_used ?? 0,
            $message->content,
        ];
    }
}
