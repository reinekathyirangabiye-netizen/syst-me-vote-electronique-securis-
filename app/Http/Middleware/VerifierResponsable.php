<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifierResponsable
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!session('responsable_connecte')) {
            return response()->json(['erreur' => 'Accès réservé au responsable.'], 401);
        }

        return $next($request);
    }
}