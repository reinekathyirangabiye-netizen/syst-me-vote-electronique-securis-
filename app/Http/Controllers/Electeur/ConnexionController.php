<?php

namespace App\Http\Controllers\Electeur;

use App\Http\Controllers\Controller;
use App\Models\Electeur;
use App\Models\CodeVerification;
use App\Models\Emargement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Hash;

class ConnexionController extends Controller
{
    private const MAX_ECHECS = 10;
    private const BLOCAGE_SECONDES = 900;

    public function verifierIdentite(Request $request)
    {
        $request->session()->forget(['electeur_en_attente', 'electeur_verifie']);
        $request->validate([
            'nom' => ['required', 'string', 'max:100'],
            'prenom' => ['required', 'string', 'max:100'],
            'matricule' => ['required', 'string', 'max:50'],
        ]);

        $cle = 'identite:' . $request->ip();

        if (RateLimiter::tooManyAttempts($cle, self::MAX_ECHECS)) {
            $reste = RateLimiter::availableIn($cle);
            return response()->json(['erreur' => "Trop d'échecs consécutifs. Réessayez dans $reste secondes."], 429);
        }

        $electeur = Electeur::where('nom', $request->nom)
            ->where('prenom', $request->prenom)
            ->where('matricule', $request->matricule)
            ->first();

        if (!$electeur) {
            RateLimiter::hit($cle, self::BLOCAGE_SECONDES);
            return response()->json(['erreur' => 'Informations incorrectes.'], 401);
        }

        RateLimiter::clear($cle);

        if (!$electeur->peut_voter) {
            return response()->json(['erreur' => 'Vous n\'êtes pas autorisé à voter pour ce scrutin.'], 403);
        }

        // Un seul code par minute et par électeur
        if (Emargement::where('id_scrutin', 1)->where('id_electeur', $electeur->id)->exists()) {
            return response()->json(['erreur' => 'Un vote est déjà enregistré pour cet électeur.'], 409);
        }

        $dernier = CodeVerification::where('id_electeur', $electeur->id)->orderByDesc('id')->first();

        if ($dernier && \Carbon\Carbon::parse($dernier->date_expiration)->gt(now()->addMinutes(9))) {
            session(['electeur_en_attente' => $electeur->id]);
            return response()->json(['statut' => 'code_deja_envoye']);
        }

        $code = random_int(100000, 999999);

        CodeVerification::create([
            'id_electeur' => $electeur->id,
            'code_hache' => Hash::make((string) $code),
            'date_expiration' => now()->addMinutes(10),
            'utilise' => false,
        ]);

        Mail::raw("Votre code de vérification est : $code (valide 10 minutes)", function ($message) use ($electeur) {
            $message->to($electeur->email)
                    ->subject('Code de vérification - Vote électronique');
        });

        session(['electeur_en_attente' => $electeur->id]);

        return response()->json(['statut' => 'code_envoye']);
    }

    public function verifierOtp(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);

        $idElecteur = session('electeur_en_attente');

        if (!$idElecteur) {
            return response()->json(['erreur' => 'Veuillez d\'abord saisir votre identité.'], 401);
        }

        $cle = 'otp:' . $idElecteur;

        if (RateLimiter::tooManyAttempts($cle, self::MAX_ECHECS)) {
            $reste = RateLimiter::availableIn($cle);
            return response()->json(['erreur' => "Trop de codes incorrects. Réessayez dans $reste secondes."], 429);
        }

        $entree = CodeVerification::where('id_electeur', $idElecteur)
            ->where('utilise', false)
            ->where('date_expiration', '>', now())
            ->orderByDesc('id')
            ->first();

        if (!$entree || !Hash::check($request->code, $entree->code_hache)) {
            RateLimiter::hit($cle, self::BLOCAGE_SECONDES);
            return response()->json(['erreur' => 'Code invalide ou expiré.'], 401);
        }

        RateLimiter::clear($cle);
        $entree->update(['utilise' => true]);

        $request->session()->forget(['electeur_en_attente', 'responsable_connecte']);
        $request->session()->regenerate();
        $request->session()->put('electeur_verifie', $idElecteur);

        return response()->json(['statut' => 'authentifie', 'csrf_token' => csrf_token()]);
    }
}
