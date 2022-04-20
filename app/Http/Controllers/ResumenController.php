<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use \Illuminate\Database\QueryException;
use DB;
use Illuminate\Contracts\Validation\ValidationException;
use Validator;
use App\VVales;

class ResumenController extends Controller
{
    function getResumenVales(Request $request){
     

        try {
            $res = DB::table('vales')
            ->select(
                'vales.idStatus', 
                DB::raw('count(vales.id) as Total'),
                'vales_status.Estatus'
            )
            ->leftJoin('vales_status','vales_status.id','=','vales.idStatus')
            ->orWhere('vales.UserCreated',$request->idUser)
            ->orWhere('vales.UserOwned',$request->idUser)
            ->get();
            

            $resTotal = DB::table('vales')
            ->select(
                DB::raw('count(vales.id) as Total')
            )
            ->orWhere('vales.UserCreated',$request->idUser)
            ->orWhere('vales.UserOwned',$request->idUser)
            ->get();

           
            $data = [
                "Estatus"=>$res,
                "Total"=>$resTotal[0]->Total
            ];

           
            
            return ['success'=>true,'results'=>true,
             'data'=>$data];

        } catch(QueryException $e){
            $errors = [
                "Clave"=>"01"
            ];
            $response = ['success'=>true,'results'=>false, 
            'errors'=>$e->getMessage(), 'message' =>'Campo de consulta incorrecto'];

            return  response()->json($response, 200);
        }

    }
}
