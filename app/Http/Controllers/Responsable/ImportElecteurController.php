<?php

namespace App\Http\Controllers\Responsable;

use App\Http\Controllers\Controller;
use App\Models\Electeur;
use App\Models\Emargement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportElecteurController extends Controller
{
    public function importer(Request $request)
    {
        $request->validate([
            'fichier' => 'required|file|mimes:xlsx,csv,txt|max:5120',
        ]);

        $chemin = $request->file('fichier')->getRealPath();
        $extension = $request->file('fichier')->getClientOriginalExtension();

        if ($extension === 'xlsx') {
            $spreadsheet = IOFactory::load($chemin);
            $feuille = $spreadsheet->getActiveSheet();
            $lignes = $feuille->toArray();
            array_shift($lignes);
        } else {
            $handle = fopen($chemin, 'r');
            fgetcsv($handle);
            $lignes = [];
            while (($ligne = fgetcsv($handle)) !== false) {
                $lignes[] = $ligne;
            }
            fclose($handle);
        }

        $importes = 0;
        $rejetees = 0;

        DB::transaction(function () use ($lignes, &$importes, &$rejetees): void {
            foreach ($lignes as $ligne) {
                if (count($ligne) < 4) {
                    $rejetees++;
                    continue;
                }

                [$nom, $prenom, $matricule, $email] = array_map(
                    static fn ($value): string => trim((string) $value),
                    array_slice($ligne, 0, 4)
                );

                if ($nom === '' || $prenom === '' || $matricule === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $rejetees++;
                    continue;
                }

                Electeur::updateOrCreate(
                    ['matricule' => $matricule],
                    ['nom' => $nom, 'prenom' => $prenom, 'email' => $email]
                );
                $importes++;
            }
        });

        return response()->json(['statut' => 'import_termine', 'importes' => $importes, 'rejetees' => $rejetees]);
    }

    public function liste()
    {
        $ayantVote = Emargement::where('id_scrutin', 1)->pluck('id_electeur')->flip();

        $electeurs = Electeur::orderBy('id')->get()->map(function ($e) use ($ayantVote) {
            return [
                'id' => $e->id,
                'nom' => $e->nom,
                'prenom' => $e->prenom,
                'matricule' => $e->matricule,
                'email' => $e->email,
                'a_vote' => isset($ayantVote[$e->id]),
                'peut_voter' => (bool) $e->peut_voter,
            ];
        });

        return response()->json($electeurs);
    }

    public function basculerDroitVote($id)
    {
        $electeur = Electeur::find($id);

        if (!$electeur) {
            return response()->json(['erreur' => 'Électeur introuvable.'], 404);
        }

        $electeur->peut_voter = !$electeur->peut_voter;
        $electeur->save();

        return response()->json(['statut' => 'mis_a_jour', 'peut_voter' => $electeur->peut_voter]);
    }
}
