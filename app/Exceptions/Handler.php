<?php

namespace App\Exceptions;

use Exception;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenBlacklistedException;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function report(Exception $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $exception)
    {
        
        if ($exception instanceof TokenExpiredException) {
            $response =  ['success'=>false,'results'=>false,
            'filtros'=>false,'errors'=>'token_expired','data'=>[]];
            return response()->json($response, 200);
        }
        else if ($exception instanceof TokenBlacklistedException) {
            $response =  ['success'=>false,'results'=>false,
            'filtros'=>false,'errors'=>'token_blacklist','data'=>[]];
            return response()->json($response, 200);
        }
        else if ($exception instanceof TokenInvalidException) {
            $response =  ['success'=>false,'results'=>false,
            'filtros'=>false,'errors'=>'token_invalid','data'=>[]];
            return response()->json($response, 200);
        }
        else if ($exception instanceof JWTException) {
            $response =  ['success'=>false,'results'=>false,
            'filtros'=>false,'errors'=>'token_invalid','data'=>[]];
            return response()->json($response, 200);
        }
        else if($exception instanceof UnauthorizedHttpException){
            $errors = [
				"Clave"=>"XX"
            ];
            
            $response =  ['success'=>false,'results'=>false,
            'filtros'=>false,'errors'=> $errors, 'message' => 'El token no fue enviado, es un token expirado o no es valido.','data'=>[]];
            return response()->json($response, 200);
        }
        return parent::render($request, $exception);
    }
}
