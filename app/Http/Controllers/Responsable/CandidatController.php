<?php

namespace App\Http\Controllers\Responsable;

use App\Http\Controllers\Controller;
use App\Models\Candidat;
use Illuminate\Http\Request;

class CandidatController extends Controller
{
    public function enregistrer(Request $request)
{
    $request->validate([
        'id_scrutin' => 'required|integer',
        'id_electeur' => 'required|integer|exists:electeurs,id',
    ]);

    $electeur = \App\Models\Electeur::find($request->id_electeur);

    // Empêche d'ajouter deux fois le même électeur comme candidat sur le même scrutin
    $dejaCandidat = Candidat::where('id_scrutin', $request->id_scrutin)
        ->where('id_electeur', $request->id_electeur)
        ->exists();

    if ($dejaCandidat) {
        return response()->json(['erreur' => 'Cet électeur est déjà candidat sur ce scrutin.'], 409);
    }

    $candidat = Candidat::create([
        'id_scrutin' => $request->id_scrutin,
        'id_electeur' => $request->id_electeur,
        'nom_candidat' => $electeur->nom . ' ' . $electeur->prenom,
    ]);

    return response()->json([
        'statut' => 'candidat_enregistre',
        'candidat' => $candidat,
    ]);
}

    public function liste(Request $request)
    {
        $idScrutin = $request->query('id_scrutin');

        $candidats = Candidat::where('id_scrutin', $idScrutin)->get();

        return response()->json($candidats);
    }
    public function supprimer($id)
    {
        $candidat = Candidat::find($id);

      if (!$candidat) {
        return response()->json(['erreur' => 'Candidat introuvable.'], 404);
       }

        // Empêche la suppression si des votes existent déjà pour ce candidat
        $aDesVotes = \App\Models\Emargement::where('id_scrutin', $candidat->id_scrutin)->exists();
      if ($aDesVotes) {
        return response()->json(['erreur' => 'Impossible de supprimer un candidat ayant déjà reçu des votes.'], 409);
       }

        $candidat->delete();

      return response()->json(['statut' => 'candidat_supprime']);
    }
}
