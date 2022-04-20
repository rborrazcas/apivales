<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use \Illuminate\Database\QueryException;
use DB;
use Illuminate\Contracts\Validation\ValidationException;
use Validator;
use App\CatMunicipio;

class CatMunicipioController extends Controller
{
    function getMunicipios(Request $request){
        $parameters = $request->all();

        try {
            $res = DB::table('cat_municipio')
            ->select(
                'cat_municipio.id','cat_municipio.Municipio','cat_municipio.Clave', 'cat_municipio.idEstado',
                'cat_estados.id as idEstadoB', 'cat_estados.Estado as Estado','cat_municipio.created_at','cat_municipio.updated_at'
            )
            ->leftJoin('cat_estados','cat_estados.id','=','cat_municipio.idEstado');

            
            
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

            $array_res = [];
            $temp = [];
            $data_temp = [];
            foreach($res as $data){
                $temp = [
                   'id'=> $data->idEstadoB,
                   'Estado'=> $data->Estado,
                ];
                $data_temp = [
                    'id'=>$data->id,
                    'Municipio'=>$data->Municipio,
                    'Clave'=> $data->Clave,
                    'idEstado' => $temp
                ];
                
                array_push($array_res,$data_temp);
            }
            
            return ['success'=>true,'results'=>true,'total'=>$total,'filtros'=>$parameters['filtered'],'data'=>$array_res];

        } catch(QueryException $e){
            return [
                'success' => false,
                'errors' => $e->getMessage()
            ];
        }
        
    }


    /* function getMunicipiosDeEstado(Request $request, $id){
        $parameters = $request->all();

        try {
            $res = DB::table('cat_municipio AS A')
            ->select(
                'A.id','A.Municipio','A.Clave', 'A.idEstado',
                'B.id as idEstadoB', 'B.Estado as Estado','A.created_at','A.updated_at'
            )
            ->leftJoin('cat_estados AS B','B.id','=','A.idEstado')
            ->where('A.idEstado','=',$id)
            ->orderBy('Municipio', 'asc')->get();
            $array_res = [];
            $temp = [];
            $data_temp = [];
            foreach($res as $data){
                $temp = [
                   'label'=> $data->idEstadoB,
                   'value'=> $data->Estado,
                ];
                $data_temp = [
                    'id'=>$data->id,
                    'Municipio'=>$data->Municipio,
                    'Clave'=> $data->Clave,
                    'Estado' => $temp
                ];
                
                array_push($array_res,$data_temp);
            }
            $total = $res->count();

            if(isset($parameters['sorted'])){
                //Lo pondre para cuando me pidan el order

                 for($i=0;$i<count($parameters['sorted']);$i++){

                    if($parameters['sorted'][$i]['desc']===true){

                        $res->orderBy('A.'.$parameters['sorted'][$i]['id'],'desc');
                    }
                    else{
                        $res->orderBy('A.'.$parameters['sorted'][$i]['id'],'asc');
                    }
                } 
            }
            if(!isset($parameters['filtered'])){
                $parameters['filtered']=false;
            }
            return ['success'=>true,'results'=>true,'total'=>$total,'filtros'=>$parameters['filtered'],'data'=>$array_res];

        } catch(QueryException $e){
            return [
                'success' => false,
                'errors' => $e->getMessage()
            ];
        }
    } */
}
