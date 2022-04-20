<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use \Illuminate\Database\QueryException;
use DB;
use Illuminate\Contracts\Validation\ValidationException;
use Validator;

class CatCie10Controller extends Controller
{
    function getCie10(Request $request){
        $parameters = $request->all();

        try {
            $res = DB::table('cat_cie10')
            ->select(
                'id', 'LETRA', 'CATALOG_KEY', 'ASTERISCO', 'NOMBRE', 'LSEX', 'LINF', 'LSUP', 'TRIVIAL',
                'ERRADICADO', 'N_INTER', 'NIN', 'NINMTOBS', 'NO_CBD', 'NO_APH', 'FETAL', 'CLAVE_CAPITULO_TYPE',
                'CAPITULO_TYPE', 'RUBRICA_TYPE', 'YEAR_MODIFI', 'YEAR_APLICACION', 'NOTDIARIA', 'NOTSEMANAL',
                'SISTEMA_ESPECIAL', 'BIRMM', 'CVE_CAUSA_TYPE', 'CAUSA_TYPE', 'EPI_MORTA', 'EPI_MORTA_M5', 
                'EDAS_E_IRAS_EN_M5', 'LISTA1', 'LISTA5', 'PRINMORTA', 'PRINMORBI', 'LM_MORBI', 'LM_MORTA',
                'LGBD165', 'LOMSBECK', 'LGBD190', 'ES_CAUSES', 'NUM_CAUSES', 'ES_SUIVE_MORTA', 'DAGA', 'EPI_CLAVE',
                'EPI_CLAVE_DESC', 'ES_SUIVE_MORB', 'ES_SUIVE_NOTIN', 'ES_SUIVE_EST_EPI', 'ES_SUIVE_EST_BROTE', 
                'SINAC', 'CODIGOX', 'COD_SIT_LESION'
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



            return ['success'=>true,'results'=>true,'total'=>$total,'filtros'=>$parameters['filtered'],'data'=>$res];

        } catch(QueryException $e){
            return [
                'success' => false,
                'errors' => $e->getMessage()
            ];
        }
        
    }
}
