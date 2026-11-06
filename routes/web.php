<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Electeur\ConnexionController;
use App\Http\Controllers\Electeur\VoteController;
use App\Http\Controllers\Responsable\TableauDeBordController;
use App\Http\Controllers\Responsable\CandidatController;
use App\Http\Controllers\Responsable\ImportElecteurController;
use App\Http\Controllers\Responsable\AuthResponsableController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/vote', function () {
    return view('electeur.portail');
});

Route::get('/responsable', function () {
    return view('responsable.tableau');
});

Route::get('/candidats-scrutin', function () {
    return response()->json(
        \App\Models\Candidat::query()
            ->where('id_scrutin', 1)
            ->get(['id', 'id_scrutin', 'nom_candidat'])
    );
});

Route::get('/responsable/statut', [AuthResponsableController::class, 'statut']);

// Routes sensibles avec limitation des tentatives
Route::post('/connexion', [ConnexionController::class, 'verifierIdentite'])->middleware('throttle:10,1');
Route::post('/verifier-otp', [ConnexionController::class, 'verifierOtp'])->middleware('throttle:5,1');
Route::post('/responsable/connexion', [AuthResponsableController::class, 'connexion'])->middleware('throttle:5,1');

Route::post('/voter', [VoteController::class, 'voter']);
Route::post('/responsable/deconnexion', [AuthResponsableController::class, 'deconnexion']);

Route::middleware('responsable.auth')->group(function () {
    Route::post('/responsable/candidats', [CandidatController::class, 'enregistrer']);
    Route::get('/responsable/candidats', [CandidatController::class, 'liste']);
    Route::delete('/responsable/candidats/{id}', [CandidatController::class, 'supprimer']);
    Route::post('/responsable/import-electeurs', [ImportElecteurController::class, 'importer']);
    Route::get('/responsable/tableau-de-bord', [TableauDeBordController::class, 'statistiques']);
    Route::get('/responsable/electeurs', [ImportElecteurController::class, 'liste']);
    Route::post('/responsable/electeurs/{id}/droit-vote', [ImportElecteurController::class, 'basculerDroitVote']);
});
