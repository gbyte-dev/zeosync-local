<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('compliance_requests')) {
            Schema::create('compliance_requests', function (Blueprint $t) {
                $t->id();
                $t->string('event_id',191)->unique();
                $t->foreignId('shop_id')->nullable()->constrained('shops')->nullOnDelete();
                $t->string('shop_domain_hash',64)->nullable()->index();
                $t->string('type',64)->index();
                $t->string('customer_id',191)->nullable()->index();
                $t->string('customer_email_hash',64)->nullable()->index();
                $t->string('status',32)->default('received')->index();
                $t->longText('request_payload')->nullable();
                $t->longText('result_payload')->nullable();
                $t->text('error')->nullable();
                $t->timestamp('completed_at')->nullable();
                $t->timestamp('purge_after')->nullable()->index();
                $t->timestamps();
            });
        } elseif (!Schema::hasColumn('compliance_requests','purge_after')) {
            Schema::table('compliance_requests', function (Blueprint $t) {
                $t->timestamp('purge_after')->nullable()->index()->after('completed_at');
            });
        }
        if (!Schema::hasTable('privacy_tombstones')) {
            Schema::create('privacy_tombstones', function (Blueprint $t) {
                $t->id();
                $t->foreignId('shop_id')->constrained()->cascadeOnDelete();
                $t->string('kind',20);
                $t->string('digest',64);
                $t->timestamps();
                $t->unique(['shop_id','kind','digest']);
            });
        }
    }
    public function down(): void {
        Schema::dropIfExists('privacy_tombstones');
        Schema::dropIfExists('compliance_requests');
    }
};
