<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use \Illuminate\Database\QueryException;
use DB;
use Illuminate\Contracts\Validation\ValidationException;
use Validator;

class CatLocalidadesController extends Controller
{

    function getLocalidades(Request $request){

        $parameters = $request->all();

        try {
            
            $res = DB::table('cat_localidades')
            ->select(
                'mapa',
                'idEstado',
                'cve_ent',
                'Entidad',
                'nom_abr',
                'idMunicipio',
                'cve_mun',
                'Municipio',
                'cve_loc',
                'nom_loc',
                'Ambito',
                'latitud',
                'longitud',
                'lat_decimal',
                'lon_decimal',
                'altitud',
                'cve_carta',
                'PoblacionTotal',
                'PoblacionMasculina',
                'PoblacionFemenina',
                'TotalViviendasHabitadas',
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

    /* function getLocalidadesOLD(Request $request){

        $v = Validator::make($request->all(), [
            'idMunicipio' => 'required',
            'idEstado' => 'required',
        ]);
        
		if ($v->fails()){
            if(!isset($parameters['filtered'])){
                $parameters['filtered']=false;
            }
            $response =  ['success'=>false,'results'=>false,
            'filtros'=>$parameters['filtered'],'errors'=>$v->errors(),'data'=>[]];

            return response()->json($response,200);
        }

        $parameters = $request->all();

        try {
            
            $res = DB::table('cat_localidades AS A')
            ->select(
                'A.mapa',
                'A.cve_ent',
                'A.Entidad',
                'A.nom_abr',
                'A.cve_mun',
                'A.Municipio',
                'A.cve_loc',
                'A.nom_loc',
                'A.Ambito',
                'A.latitud',
                'A.longitud',
                'A.lat_decimal',
                'A.lon_decimal',
                'A.altitud',
                'A.cve_carta',
                'A.PoblacionTotal',
                'A.PoblacionMasculina',
                'A.PoblacionFemenina',
                'A.TotalViviendasHabitadas',
            )
            ->where('idEstado','=',$parameters['idEstado'])
            ->where('idMunicipio','=',$parameters['idMunicipio'])
            ->orderBy('nom_loc', 'asc')->get();
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
