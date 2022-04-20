<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use \Illuminate\Database\QueryException;
use DB;
use Illuminate\Contracts\Validation\ValidationException;
use Validator;
use App\Clues;
class CluesController extends Controller
{
    function getClues(Request $request){
        $parameters = $request->all();

        try {
            $res = DB::table('clues')
            ->select(
                'id','CLUES','NombreEntidad',
                'ClaveEntidad','NombreMunicipio','ClaveMunicipio',
                'NombreLocalidad','ClaveLocalidad','NombreJurisdiccion',
                'ClaveJurisdiccion','NombreInstitucion','ClaveInstitucion',
                'NombreTipologia','ClaveTipologia','TotalCamas','NombreUnidad'
                
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

    function setClues(Request $request){

        $v = Validator::make($request->all(), [
            'CLUES' => 'required',
            'NombreEntidad' => 'required',
            'ClaveEntidad' => 'required',
            'NombreMunicipio' => 'required',
            'ClaveMunicipio' => 'required',
            'NombreLocalidad' => 'required',
            'ClaveLocalidad' => 'required',
            'NombreJurisdiccion' => 'required',
            'ClaveJurisdiccion' => 'required',
            'NombreInstitucion' => 'required',
            'ClaveInstitucion' => 'required',
            'NombreTipologia' => 'required',
            'ClaveTipologia' => 'required',
            'TotalCamas' => 'required',
            'NombreUnidad' => 'required',
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
        $obj = new Clues;
        $obj->CLUES = $parameters['CLUES'];
        $obj->NombreEntidad = $parameters['NombreEntidad'];
        $obj->ClaveEntidad = $parameters['ClaveEntidad'];
        $obj->NombreMunicipio = $parameters['NombreMunicipio'];

        $obj->ClaveMunicipio = $parameters['ClaveMunicipio'];
        $obj->NombreLocalidad = $parameters['NombreLocalidad'];
        $obj->ClaveLocalidad = $parameters['ClaveLocalidad'];
        $obj->NombreJurisdiccion = $parameters['NombreJurisdiccion'];

        $obj->ClaveJurisdiccion = $parameters['ClaveJurisdiccion'];
        $obj->NombreInstitucion = $parameters['NombreInstitucion'];
        $obj->ClaveInstitucion = $parameters['ClaveInstitucion'];
        $obj->NombreTipologia = $parameters['NombreTipologia'];

        $obj->ClaveTipologia = $parameters['ClaveTipologia'];
        $obj->TotalCamas = $parameters['TotalCamas'];
        $obj->NombreUnidad = $parameters['NombreUnidad'];
        $obj->save(); 

        if(!isset($parameters['filtered'])){
            $parameters['filtered']=false;
        }
        
        return ['success'=>true,'results'=>true,
            'filtros'=>$parameters['filtered'],'data'=>$obj];
    }
}
