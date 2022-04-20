<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use \Illuminate\Database\QueryException;
use DB;
use Illuminate\Contracts\Validation\ValidationException;
use Validator;
use App\VStatus;

class VStatusController extends Controller
{
    function getStatus(Request $request){
        $parameters = $request->all();

        try {
            $res = DB::table('v_negocios_status')
            ->select(
                
                'v_negocios_status.id', 
                'v_negocios_status.Estatus',
                'v_negocios_status.created_at', 
                'v_negocios_status.updated_at'
                
            );
            $flag = 0;
            if(isset($parameters['filtered'])){

                for($i=0;$i<count($parameters['filtered']);$i++){

                    if($flag==0){
                        if($parameters['filtered'][$i]['id'] &&  strpos($parameters['filtered'][$i]['id'], 'id') !== false){
                            if(is_array ($parameters['filtered'][$i]['value'])){
                                $res->whereIn($parameters['filtered'][$i]['id'],$parameters['filtered'][$i]['value']);
                            }else{
                                $res->where($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
                            }
                            
                        }else{
                            $res->where($parameters['filtered'][$i]['id'],'LIKE','%'.$parameters['filtered'][$i]['value'].'%');
                        }
                        $flag = 1;
                    }
                    else{
                        if($parameters['tipo']=='and'){
                            if($parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'id') !== false){
                                if(is_array($parameters['filtered'][$i]['value'])){
                                    $res->whereIn($parameters['filtered'][$i]['id'],$parameters['filtered'][$i]['value']);
                                }else{
                                    $res->where($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
                                }
                            }else{
                                $res->where($parameters['filtered'][$i]['id'],'LIKE','%'.$parameters['filtered'][$i]['value'].'%');
                            } 
                        }
                        else{
                            if($parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'id') !== false){
                                if(is_array ($parameters['filtered'][$i]['value'])){
                                    $res->orWhereIn($parameters['filtered'][$i]['id'],$parameters['filtered'][$i]['value']);
                                }else{
                                    $res->orWhere($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
                                }
                            }else{
                                $res->orWhere($parameters['filtered'][$i]['id'],'LIKE','%'.$parameters['filtered'][$i]['value'].'%');
                            }
                        }
                    }
                }
            }
            

            $page = $parameters['page'];
            $pageSize = $parameters['pageSize'];

            $startIndex =  $page * $pageSize;
            if(isset($parameters['sorted'])){

                for($i=0;$i<count($parameters['sorted']);$i++){

                    if($parameters['sorted'][$i]['desc']===true){

                        $res->orderBy($parameters['sorted'][$i]['id'],'desc');
                    }
                    else{
                        $res->orderBy($parameters['sorted'][$i]['id'],'asc');
                    }
                }
            }

            $total = $res->count(); 
            $res = $res->offset($startIndex)
            ->take($pageSize)
            ->get();
            
            return ['success'=>true,'results'=>true,
             'total'=>$total,'filtros'=>$parameters['filtered'],'data'=>$res];

        } catch(QueryException $e){
            $errors = [
                "Clave"=>"01"
            ];
            $response = ['success'=>true,'results'=>false, 
            'total'=>0,'filtros'=>$parameters['filtered'],
            'errors'=>$errors, 'message' =>'Campo de consulta incorrecto'];

            return  response()->json($response, 200);
        }

    }
}
