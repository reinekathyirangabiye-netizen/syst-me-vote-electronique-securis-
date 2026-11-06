<?php

namespace App\Http\Controllers\Electeur;

use App\Http\Controllers\Controller;
use App\Models\Bulletin;
use App\Models\Candidat;
use App\Models\Electeur;
use App\Models\Emargement;
use App\Services\BulletinCipher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VoteController extends Controller
{
    private const ID_SCRUTIN = 1;

    public function voter(Request $request, BulletinCipher $cipher)
    {
        $idElecteur = session('electeur_verifie');

        if (!$idElecteur) {
            return response()->json(['erreur' => 'Non authentifié.'], 401);
        }

        $request->validate([
            'id_candidat' => 'required|integer',
        ]);

        $scrutin = DB::table('scrutins')->where('id', self::ID_SCRUTIN)->first();
        if (
            !$scrutin ||
            $scrutin->statut !== 'ouvert' ||
            now()->lt($scrutin->date_ouverture) ||
            now()->gt($scrutin->date_cloture)
        ) {
            return response()->json(['erreur' => 'Le scrutin n’est pas ouvert.'], 403);
        }

        $electeur = Electeur::find($idElecteur);

        if (!$electeur || !$electeur->peut_voter) {
            return response()->json(['erreur' => 'Vous n\'êtes pas autorisé à voter pour ce scrutin.'], 403);
        }

        $candidatValide = Candidat::where('id', $request->id_candidat)
            ->where('id_scrutin', self::ID_SCRUTIN)
            ->exists();

        if (!$candidatValide) {
            return response()->json(['erreur' => 'Candidat invalide.'], 422);
        }

        $bulletinChiffre = $cipher->chiffrer((int) $request->id_candidat);

        try {
            DB::transaction(function () use ($request, $idElecteur, $bulletinChiffre) {
                Emargement::create([
                    'id_scrutin' => self::ID_SCRUTIN,
                    'id_electeur' => $idElecteur,
                ]);

                Bulletin::create([
                    'id_scrutin' => self::ID_SCRUTIN,
                    'bulletin_chiffre' => $bulletinChiffre,
                ]);
            });
        } catch (\Illuminate\Database\QueryException $e) {
            if ((string) $e->getCode() !== '23505') {
                throw $e;
            }
            return response()->json(['erreur' => 'Vous avez déjà voté.'], 409);
        }

        $request->session()->forget('electeur_verifie');

        return response()->json([
            'statut' => 'vote_enregistre',
            'confirmation' => 'VOTE-' . strtoupper(bin2hex(random_bytes(8))),
        ]);
    }
}
