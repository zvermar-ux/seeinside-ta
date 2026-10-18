<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;

/** One piece of knowledge the chatbot uses (opening hours, payment, visa rules, a corrected answer…) */
class ChatKnowledge extends Model
{
    use BelongsToAgency;

    protected $table = 'chat_knowledge';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public static function sources(): array
    {
        return ['manual' => __('Entered by an operator'), 'analysis' => __('Website analysis'), 'correction' => __('Corrected answer')];
    }
}
