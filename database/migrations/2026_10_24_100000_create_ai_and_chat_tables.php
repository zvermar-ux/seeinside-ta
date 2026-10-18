<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // AI connection per agency (agency_id NULL = the super admin's own / platform account)
        Schema::create('ai_accounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $t->text('openai_key')->nullable();
            $t->text('anthropic_key')->nullable();
            $t->text('gemini_key')->nullable();
            $t->boolean('assistant_enabled')->default(true);
            $t->string('assistant_provider', 20)->default('anthropic');
            $t->string('assistant_model', 80)->nullable();
            $t->decimal('assistant_limit', 10, 2)->nullable();        // USD per month, null = no limit
            $t->decimal('user_daily_limit', 10, 2)->nullable();       // USD per operator per day
            $t->boolean('images_enabled')->default(true);
            $t->string('image_provider', 20)->default('openai');
            $t->string('image_model', 80)->nullable();
            $t->boolean('chat_enabled')->default(false);
            $t->string('chat_provider', 20)->default('anthropic');
            $t->string('chat_model', 80)->nullable();
            $t->decimal('chat_limit', 10, 2)->nullable();
            $t->json('prices')->nullable();                          // model => [in, out] per 1M tokens / image price
            $t->boolean('platform_allowed')->default(false);         // super admin: agency may use the platform keys
            $t->decimal('platform_limit', 10, 2)->nullable();        // super admin: max USD / month on platform keys
            $t->string('alert_email', 150)->nullable();
            $t->json('alerts_sent')->nullable();
            $t->json('chat')->nullable();                            // live chat widget, channels, bot behaviour
            $t->timestamps();
        });

        Schema::create('ai_usages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('feature', 20)->index();      // assistant | image | chat | training
            $t->string('provider', 20);
            $t->string('model', 80);
            $t->unsignedInteger('input_tokens')->default(0);
            $t->unsignedInteger('output_tokens')->default(0);
            $t->unsignedSmallInteger('images')->default(0);
            $t->decimal('cost', 12, 6)->default(0);
            $t->boolean('via_platform')->default(false);
            $t->string('note', 190)->nullable();
            $t->timestamp('created_at')->useCurrent()->index();
        });

        // what the chatbot knows besides the offer: entered by operators, made from the site analysis or from corrected answers
        Schema::create('chat_knowledge', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->string('title', 200);
            $t->text('content');
            $t->string('source', 20)->default('manual');   // manual | analysis | correction
            $t->boolean('is_active')->default(true);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('chat_questionnaires', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->json('title');
            $t->json('intro')->nullable();
            $t->string('icon', 16)->nullable();             // emoji shown on the card
            $t->string('image')->nullable();
            $t->string('category', 30)->nullable();        // winter | summer | spa | travel | city | event | other
            $t->json('questions');
            $t->json('offer_types')->nullable();           // which offers may be recommended: tour, event, accommodation
            $t->boolean('is_active')->default(true);
            $t->boolean('made_by_ai')->default(false);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('chat_conversations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->uuid('visitor_id')->index();
            $t->string('token', 64)->unique();             // the visitor's browser proves it owns the conversation
            $t->string('channel', 20)->default('ai');      // ai | test
            $t->string('name', 120)->nullable();
            $t->string('email', 150)->nullable();
            $t->string('phone', 50)->nullable();
            $t->string('locale', 5)->default('mk');
            $t->string('page_url', 500)->nullable();
            $t->string('status', 20)->default('open');     // open | closed
            $t->boolean('needs_human')->default(false);
            $t->boolean('bot_paused')->default(false);     // an operator took over
            $t->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('questionnaire_id')->nullable()->constrained('chat_questionnaires')->nullOnDelete();
            $t->json('answers')->nullable();
            $t->unsignedInteger('messages_count')->default(0);
            $t->decimal('cost', 10, 6)->default(0);
            $t->string('ip', 45)->nullable();
            $t->timestamp('last_message_at')->nullable()->index();
            $t->timestamp('operator_seen_at')->nullable();
            $t->timestamps();
        });

        Schema::create('chat_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
            $t->string('role', 12);                         // visitor | bot | operator | system
            $t->text('body');
            $t->json('items')->nullable();                 // recommended offers shown as cards
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('rating', 10)->nullable();          // good | bad (set by operators when training)
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['chat_messages', 'chat_conversations', 'chat_questionnaires', 'chat_knowledge', 'ai_usages', 'ai_accounts'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
