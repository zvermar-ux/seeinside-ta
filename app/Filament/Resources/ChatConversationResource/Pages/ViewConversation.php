<?php

namespace App\Filament\Resources\ChatConversationResource\Pages;

use App\Filament\Resources\ChatConversationResource;
use App\Models\ChatKnowledge;
use App\Models\ChatMessage;
use App\Services\Ai\ChatBot;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/** One conversation: the thread, the agent's answer box, take over from the bot, teach the bot */
class ViewConversation extends Page
{
    use InteractsWithRecord;

    protected static string $resource = ChatConversationResource::class;

    protected static string $view = 'filament.chat.conversation';

    public string $reply = '';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        abort_unless(static::getResource()::can('view', $this->record), 403);
        $this->record->forceFill(['operator_seen_at' => now()])->saveQuietly();
    }

    public function getTitle(): string
    {
        return $this->record->displayName();
    }

    public function getSubheading(): ?string
    {
        $r = $this->record;

        return implode(' · ', array_filter([$r->phone, $r->email, strtoupper($r->locale), $r->page_url ? Str::limit($r->page_url, 60) : null, $r->created_at->format('d.m.Y H:i')]));
    }

    public function messages()
    {
        $this->record->forceFill(['operator_seen_at' => now()])->saveQuietly();

        return $this->record->messages()->with('user:id,name')->get();
    }

    protected function canWrite(): bool
    {
        return static::getResource()::can('edit', $this->record);
    }

    public function send(): void
    {
        abort_unless($this->canWrite(), 403);
        $text = trim($this->reply);
        if ($text === '') {
            return;
        }
        $this->record->messages()->create(['role' => 'operator', 'body' => $text, 'user_id' => Auth::id()]);
        // an agent answered: the bot stays quiet in this conversation until it is given back
        $this->record->forceFill(['bot_paused' => true, 'needs_human' => false, 'operator_id' => Auth::id(), 'last_message_at' => now(), 'status' => 'open', 'messages_count' => $this->record->messages_count + 1])->save();
        $this->reply = '';
    }

    public function rate(int $id, string $rating): void
    {
        abort_unless($this->canWrite(), 403);
        ChatMessage::where('conversation_id', $this->record->id)->whereKey($id)->update(['rating' => $rating]);
    }

    protected function getHeaderActions(): array
    {
        $r = $this->record;

        return [
            Actions\Action::make('takeover')->label(__('Take over from the bot'))->icon('heroicon-o-hand-raised')->color('warning')
                ->visible(fn () => ! $this->record->bot_paused && $this->canWrite())
                ->action(fn () => $this->record->forceFill(['bot_paused' => true, 'operator_id' => Auth::id()])->save()),
            Actions\Action::make('giveback')->label(__('Give back to the bot'))->icon('heroicon-o-cpu-chip')->color('gray')
                ->visible(fn () => $this->record->bot_paused && $this->canWrite())
                ->action(fn () => $this->record->forceFill(['bot_paused' => false, 'needs_human' => false])->save()),
            Actions\Action::make('done')->label(__('Handled'))->icon('heroicon-o-check')->color('success')
                ->visible(fn () => $this->record->needs_human && $this->canWrite())
                ->action(fn () => $this->record->forceFill(['needs_human' => false])->save()),
            Actions\Action::make('close')->label(__('Close'))->icon('heroicon-o-archive-box')->color('gray')
                ->visible(fn () => $this->record->status === 'open' && $this->canWrite())
                ->action(fn () => $this->record->forceFill(['status' => 'closed', 'needs_human' => false])->save()),
            Actions\DeleteAction::make()->record($r)->successRedirectUrl(ChatConversationResource::getUrl()),
        ];
    }

    /** "Correct this answer": the right answer becomes bot knowledge */
    public function correctAction(): Actions\Action
    {
        return Actions\Action::make('correct')->label(__('Correct the answer'))->icon('heroicon-o-academic-cap')
            ->modalHeading(__('Teach the bot'))
            ->modalDescription(__('Write how the bot should have answered. It is saved in the bot knowledge and used from now on.'))
            ->fillForm(function (array $arguments) {
                $m = ChatMessage::where('conversation_id', $this->record->id)->find($arguments['id'] ?? 0);
                $q = $m ? ChatMessage::where('conversation_id', $this->record->id)->where('id', '<', $m->id)->where('role', 'visitor')->latest('id')->value('body') : '';

                return ['question' => Str::limit((string) $q, 180, ''), 'answer' => $m?->body];
            })
            ->form([
                Forms\Components\TextInput::make('question')->label(__('Question of the visitor'))->required()->maxLength(190),
                Forms\Components\Textarea::make('answer')->label(__('Correct answer'))->required()->rows(5)->autosize(),
            ])
            ->action(function (array $data, array $arguments) {
                ChatKnowledge::create([
                    'agency_id' => $this->record->agency_id, 'title' => $data['question'], 'content' => $data['answer'],
                    'source' => 'correction', 'created_by' => Auth::id(),
                ]);
                ChatMessage::where('conversation_id', $this->record->id)->whereKey($arguments['id'] ?? 0)->update(['rating' => 'bad']);
                Notification::make()->success()->title(__('The bot learned the answer'))->send();
            });
    }
}
