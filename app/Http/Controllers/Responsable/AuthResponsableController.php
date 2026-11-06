<?php

namespace App\Http\Controllers\Responsable;

use App\Http\Controllers\Controller;
use App\Models\Responsable;
use Illuminate\Http\Request;

class AuthResponsableController extends Controller
{
    public function connexion(Request $request)
    {
        $request->validate([
            'nom_utilisateur' => 'required|string|max:100',
            'mot_de_passe' => 'required|string|max:200',
        ]);

        $responsable = Responsable::where('nom_utilisateur', $request->nom_utilisateur)->first();

        if (!$responsable || !password_verify($request->mot_de_passe, $responsable->mot_de_passe_hache)) {
            return response()->json(['erreur' => 'Identifiants incorrects.'], 401);
        }

        $request->session()->forget(['electeur_en_attente', 'electeur_verifie']);
        $request->session()->regenerate();
        $request->session()->put('responsable_connecte', $responsable->id);

        return response()->json(['statut' => 'connecte', 'csrf_token' => csrf_token()]);
    }

    public function deconnexion(Request $request)
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return response()->json(['statut' => 'deconnecte', 'csrf_token' => csrf_token()]);
    }
    public function statut(Request $request)
   {
        return response()->json([
        'connecte' => (bool) session('responsable_connecte'),
     ]);
    }
}
