<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Ajouter parent_id à la table eleves
        Schema::table('eleves', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_id')->nullable()->after('user_id');
            $table->foreign('parent_id')->references('id')->on('users')->onDelete('set null');
        });

        // Modifier la colonne role dans users pour inclure 'parent'
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['administrateur', 'enseignant', 'eleve', 'parent'])->change();
        });
    }

    public function down()
    {
        Schema::table('eleves', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn('parent_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['administrateur', 'enseignant', 'eleve'])->change();
        });
    }
};