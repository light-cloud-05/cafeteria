<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('bitacoras',function(Blueprint $t) {
            $t->string('ip',45)->nullable();
            $t->index('fechaHora');
            $t->index(['idUsuario','fechaHora']);
            $t->index(['accion','fechaHora']);
        });
    }
    public function down(): void {
        Schema::table('bitacoras',function(Blueprint $t) {
            $t->dropIndex(['fechaHora']);$t->dropIndex(['idUsuario','fechaHora']);$t->dropIndex(['accion','fechaHora']);$t->dropColumn('ip');
        });
    }
};
