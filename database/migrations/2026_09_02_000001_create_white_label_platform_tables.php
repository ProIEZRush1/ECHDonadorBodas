<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('brand_name')->default('Buy Overcloud');
            $table->string('brand_color', 20)->default('#6C5CE7');
            $table->string('status', 20)->default('active');
            $table->string('timezone')->default('America/Mexico_City');
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->timestamp('email_verified_at')->nullable()->after('email');
            $table->string('role', 30)->default('owner')->after('password');
            $table->boolean('is_active')->default(true)->after('role');
        });

        Schema::create('whatsapp_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name')->default('WhatsApp principal');
            $table->string('waba_id')->nullable();
            $table->string('phone_number_id')->nullable()->unique();
            $table->string('display_phone')->nullable();
            $table->text('access_token')->nullable();
            // Encrypted casts exceed varchar(255), even for short raw tokens.
            $table->text('verify_token')->nullable();
            $table->string('status', 30)->default('pending');
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();
        });

        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_connection_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('language', 10)->default('es_MX');
            $table->string('category', 30)->default('MARKETING');
            $table->string('status', 30)->default('draft');
            $table->text('body');
            $table->json('components')->nullable();
            $table->string('meta_template_id')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'name', 'language']);
        });

        Schema::create('conversation_flows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('system_prompt')->nullable();
            $table->json('nodes');
            $table->boolean('is_active')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        Schema::create('billing_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('stripe_customer_id')->nullable()->unique();
            $table->string('stripe_payment_method_id')->nullable();
            $table->string('card_brand')->nullable();
            $table->string('card_last_four', 4)->nullable();
            $table->string('card_expiry', 7)->nullable();
            $table->string('currency', 3)->default('mxn');
            $table->decimal('spend_limit', 12, 2)->nullable();
            $table->string('status', 30)->default('incomplete');
            $table->timestamps();
        });

        Schema::create('ai_usage_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 30)->default('anthropic');
            $table->string('model')->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('cost_usd', 12, 6)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'created_at']);
        });

        foreach (['contacts', 'messages', 'campaigns', 'settings', 'donations', 'conversation_states'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            });
        }

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropUnique(['telefono']);
            $table->unique(['organization_id', 'telefono']);
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->dropUnique(['key']);
            $table->unique(['organization_id', 'key']);
        });

        $organizationId = DB::table('organizations')->insertGetId([
            'name' => 'Buy Overcloud',
            'slug' => 'buy-overcloud',
            'brand_name' => 'Buy Overcloud',
            'brand_color' => '#6C5CE7',
            'status' => 'active',
            'timezone' => 'America/Mexico_City',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->update(['organization_id' => $organizationId, 'role' => 'super_admin']);
        foreach (['contacts', 'messages', 'campaigns', 'settings', 'donations', 'conversation_states'] as $name) {
            DB::table($name)->whereNull('organization_id')->update(['organization_id' => $organizationId]);
        }
    }

    public function down(): void
    {
        Schema::table('settings', fn (Blueprint $table) => $table->dropUnique(['organization_id', 'key']));
        Schema::table('contacts', fn (Blueprint $table) => $table->dropUnique(['organization_id', 'telefono']));
        foreach (['conversation_states', 'donations', 'settings', 'campaigns', 'messages', 'contacts'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropConstrainedForeignId('organization_id'));
        }
        Schema::dropIfExists('ai_usage_records');
        Schema::dropIfExists('billing_profiles');
        Schema::dropIfExists('conversation_flows');
        Schema::dropIfExists('message_templates');
        Schema::dropIfExists('whatsapp_connections');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn(['email_verified_at', 'role', 'is_active']);
        });
        Schema::dropIfExists('organizations');
    }
};
