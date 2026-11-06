<?php

namespace App\Http\Controllers\Responsable;

use App\Http\Controllers\Controller;
use App\Models\Bulletin;
use App\Models\Emargement;
use App\Services\BulletinCipher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TableauDeBordController extends Controller
{
    public function statistiques(Request $request, BulletinCipher $cipher)
    {
        $idScrutin = (int) $request->query('id_scrutin', 1);
        $scrutin = DB::table('scrutins')->where('id', $idScrutin)->first();

        $totalElecteurs = DB::table('electeurs')->where('peut_voter', true)->count();
        $votants = Emargement::where('id_scrutin', $idScrutin)->count();
        $resultats = DB::table('candidats')
            ->where('id_scrutin', $idScrutin)
            ->select('id', 'nom_candidat')
            ->get();

        $resultatsPublies = $scrutin && now()->gt($scrutin->date_cloture);

        if ($resultatsPublies) {
            $voixParCandidat = [];
            foreach (Bulletin::where('id_scrutin', $idScrutin)->get(['bulletin_chiffre']) as $bulletin) {
                $idCandidat = $cipher->dechiffrer($bulletin->bulletin_chiffre);
                $voixParCandidat[$idCandidat] = ($voixParCandidat[$idCandidat] ?? 0) + 1;
            }

            $resultats = $resultats->map(function ($candidat) use ($voixParCandidat) {
                $candidat->voix = $voixParCandidat[$candidat->id] ?? 0;
                return $candidat;
            });
        } else {
            $resultats = $resultats->map(function ($candidat) {
                $candidat->voix = '—';
                return $candidat;
            });
        }

        return response()->json([
            'votants' => $votants,
            'restants' => max(0, $totalElecteurs - $votants),
            'resultats' => $resultats,
            'resultats_publies' => (bool) $resultatsPublies,
        ]);
    }
}
