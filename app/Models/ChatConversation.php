<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatConversation extends Model
{
    use BelongsToAgency;

    protected $guarded = ['id'];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            'answers' => 'array', 'needs_human' => 'boolean', 'bot_paused' => 'boolean', 'cost' => 'decimal:6',
            'last_message_at' => 'datetime', 'operator_seen_at' => 'datetime',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id')->orderBy('id');
    }

    public function questionnaire(): BelongsTo
    {
        return $this->belongsTo(ChatQuestionnaire::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function displayName(): string
    {
        return $this->name ?: __('Visitor').' #'.$this->id;
    }

    public function hasUnread(): bool
    {
        return $this->last_message_at && (! $this->operator_seen_at || $this->operator_seen_at->lt($this->last_message_at));
    }
}
