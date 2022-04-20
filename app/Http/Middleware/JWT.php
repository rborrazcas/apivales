<?php

namespace App\Http\Middleware;

use Closure;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JWT
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if(!$request->token){
            $response =  ['success'=>false,'results'=>false,
            'filtros'=>false,'errors'=>'token_absent','data'=>[]];
            return response()->json($response, 200);
        }
        JWTAuth::parseToken()->authenticate();
        return $next($request);
    }
}
