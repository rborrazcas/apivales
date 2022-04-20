<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use \Illuminate\Database\QueryException;
use DB;
use Illuminate\Contracts\Validation\ValidationException;
use Validator;

class CatCPController extends Controller
{
    function getCP(Request $request){
        $parameters = $request->all();

        try {
            $res = DB::table('cat_cp')
            ->select(
                'id', 'd_codigo', 'd_asenta', 'd_tipo_asenta', 'D_mnpio', 'd_estado',
                'd_ciudad', 'd_CP', 'c_estado', 'c_oficina', 'c_CP', 'c_tipo_asenta',
                'c_mnpio', 'id_asenta_cpcons', 'd_zona', 'c_cve_ciudad'
            );

            if(isset($parameters['idMunicipio'])){
                if(is_array ($parameters['idMunicipio'])){
                    $res->whereIn('cat_cp.c_mnpio',$parameters['idMunicipio']);
                }else{
                    $res->where('cat_cp.c_mnpio','=',$parameters['idMunicipio']);
                }    
            }
            
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
                            if($parameters['filtered'][$i]['id'] == "c_mnpio")
                                    $res->where($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
                                else
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
                                if($parameters['filtered'][$i]['id'] == "c_mnpio")
                                    $res->where($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
                                else
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
                                if($parameters['filtered'][$i]['id'] == "c_mnpio")
                                    $res->where($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
                                else
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
            
            return ['success'=>true,'results'=>true,'total'=>$total,'filtros'=>$parameters['filtered'],'data'=>$res];

        } catch(QueryException $e){
            return [
                'success' => false,
                'errors' => $e->getMessage()
            ];
        }
        
    }

    /* function getCPDeMunicipio(Request $request,$id){
        $parameters = $request->all();

        try {
            $res = DB::table('cat_cp AS A')
            ->select(
                'A.id', 'A.d_codigo', 'A.d_asenta', 'A.d_tipo_asenta', 'A.D_mnpio', 'A.d_estado',
                'A.d_ciudad', 'A.d_CP', 'A.c_estado', 'A.c_oficina', 'A.c_CP', 'A.c_tipo_asenta',
                'A.c_mnpio', 'A.id_asenta_cpcons', 'A.d_zona', 'A.c_cve_ciudad'
            )
            ->where('c_mnpio','=',$id)
            ->orderBy('d_codigo', 'asc')->get();
            
            $total = $res->count();

            if(isset($parameters['sorted'])){
                //Lo pondre para cuando me pidan el order

                 for($i=0;$i<count($parameters['sorted']);$i++){

                    if($parameters['sorted'][$i]['desc']===true){

                        $res->orderBy($parameters['sorted'][$i]['id'],'desc');
                    }
                    else{
                        $res->orderBy($parameters['sorted'][$i]['id'],'asc');
                    }
                } 
            }

            if(!isset($parameters['filtered'])){
                $parameters['filtered']=false;
            }

            
            return ['success'=>true,'results'=>true,'total'=>$total,'filtros'=>$parameters['filtered'],'data'=>$res];

        } catch(QueryException $e){
            return [
                'success' => false,
                'errors' => $e->getMessage()
            ];
        }
        
    } */
}
