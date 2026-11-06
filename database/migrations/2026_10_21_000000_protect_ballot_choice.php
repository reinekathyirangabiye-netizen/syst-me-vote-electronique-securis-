<?php

use App\Services\BulletinCipher;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bulletins') || !Schema::hasColumn('bulletins', 'id_candidat')) {
            throw new RuntimeException('Le schéma des bulletins ne correspond pas à la migration de confidentialité.');
        }

        $cipher = app(BulletinCipher::class);
        $cipher->validerCle();

        if (!Schema::hasTable('emargements')) {
            Schema::create('emargements', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('id_scrutin');
                $table->integer('id_electeur');
                $table->unique(['id_scrutin', 'id_electeur']);
                $table->foreign('id_scrutin')->references('id')->on('scrutins');
                $table->foreign('id_electeur')->references('id')->on('electeurs');
            });
        }

        // Dans l'ancien schéma, l'émargement était porté par la ligne du bulletin.
        if (Schema::hasColumn('bulletins', 'id_electeur')) {
            DB::table('bulletins')
                ->select('id_scrutin', 'id_electeur')
                ->whereNotNull('id_electeur')
                ->distinct()
                ->orderBy('id_scrutin')
                ->orderBy('id_electeur')
                ->chunk(500, function ($rows): void {
                    foreach ($rows as $row) {
                        DB::table('emargements')->insertOrIgnore([
                            'id_scrutin' => $row->id_scrutin,
                            'id_electeur' => $row->id_electeur,
                        ]);
                    }
                });
        }

        // Rechiffrer à partir de la colonne historique avant de supprimer le choix en clair.
        DB::table('bulletins')
            ->select('id', 'id_candidat')
            ->orderBy('id')
            ->chunk(500, function ($rows) use ($cipher): void {
                foreach ($rows as $row) {
                    DB::table('bulletins')->where('id', $row->id)->update([
                        'bulletin_chiffre' => $cipher->chiffrer((int) $row->id_candidat),
                    ]);
                }
            });

        $columnsToDrop = ['id_candidat'];
        if (Schema::hasColumn('bulletins', 'id_electeur')) {
            $columnsToDrop[] = 'id_electeur';
        }

        Schema::table('bulletins', function (Blueprint $table) use ($columnsToDrop): void {
            $table->dropColumn($columnsToDrop);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Cette migration protège le secret du vote et ne peut pas être annulée en restaurant des choix en clair.');
    }
};
