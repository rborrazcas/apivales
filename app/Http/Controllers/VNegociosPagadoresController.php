<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use \Illuminate\Database\QueryException;
use DB;
use Illuminate\Contracts\Validation\ValidationException;
use Validator;
use App\VNegociosPagadores;

class VNegociosPagadoresController extends Controller
{
    function getNegociosPagadores(Request $request){
        $parameters = $request->all();

        try {
            $res = DB::table('v_negocios_pagadores')
            ->select(

                'v_negocios_pagadores.id', 
                'v_negocios_pagadores.idNegocio', 
                    'v_negocios.RFC as RFCA', 
                    'v_negocios.NombreEmpresa as NombreEmpresaA', 
                    'v_negocios.Nombre as NombreA', 
                    'v_negocios.Paterno as PaternoA', 
                    'v_negocios.Materno as MaternoA', 
                    'v_negocios.TelNegocio as TelNegocioA', 
                    'v_negocios.TelCasa as TelCasaA', 
                    'v_negocios.Celular as CelularA', 
                    'v_negocios.idMunicipio', 
                        'et_cat_municipioA.Nombre as Municipio',
                        'et_cat_municipioA.SubRegion as Region',
                'v_negocios_pagadores.CURP', 
                'v_negocios_pagadores.Nombre', 
                'v_negocios_pagadores.Paterno', 
                'v_negocios_pagadores.Materno', 
                'v_negocios_pagadores.created_at', 
                'v_negocios_pagadores.updated_at',
                'v_negocios_pagadores.idStatus',
                    'v_status.id as idD', 
                    'v_status.Estatus as EstatusD', 
                'v_negocios_pagadores.UserCreated',
                //Datos Usuario created
                    'users.id as idE',
                    'users.email as emailE',
                    'users.Nombre as NombreE',
                    'users.Paterno as PaternoE',
                    'users.Materno as MaternoE',
                    'users.idTipoUser as idTipoUserE',
                        'cat_usertipo.TipoUser as TipoUserEA',
                'v_negocios_pagadores.UserUpdated',
                //Datos Usuario updated
                    'usersB.id as idF',
                    'usersB.email as emailF',
                    'usersB.Nombre as NombreF',
                    'usersB.Paterno as PaternoF',
                    'usersB.Materno as MaternoF',
                    'usersB.idTipoUser as idTipoUserF',
                        'cat_usertipoB.TipoUser as TipoUserFA'
            )
            ->leftJoin('v_negocios','v_negocios.id','=','v_negocios_pagadores.idNegocio')
            ->leftJoin('et_cat_municipio as et_cat_municipioA','et_cat_municipioA.Id','=','v_negocios.idMunicipio')
             ->leftJoin('v_status','v_status.id','=','v_negocios_pagadores.idStatus')
            ->leftJoin('users','users.id','=','v_negocios_pagadores.UserCreated')
            ->leftJoin('cat_usertipo','cat_usertipo.id','=','users.idTipoUser')
            ->leftJoin('users as usersB','usersB.id','=','v_negocios_pagadores.UserUpdated')
            ->leftJoin('cat_usertipo as cat_usertipoB','cat_usertipoB.id','=','usersB.idTipoUser');
            
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
            foreach($res as $data){
            $temp = [
                'id'=> $data->id,
                'idNegocio'=> [
                    'id'=>$data->idNegocio,
                    'RFC'=>$data->RFCA,
                    'NombreEmpresa'=>$data->NombreEmpresaA,
                    'Nombre'=>$data->NombreA,
                    'Paterno'=>$data->PaternoA,
                    'Materno'=>$data->MaternoA,
                    'TelNegocio'=>$data->TelNegocioA,
                    'TelCasa'=>$data->TelCasaA,
                    'Celular'=>$data->CelularA,
                    'idMunicipio'=>[
                        'Id'=>$data->idMunicipio,
                        'Municipio'=>$data->Municipio,
                        'Region'=>$data->Region
                    ]
                ],
                'CURP'=> $data->CURP,
                'Nombre'=> $data->Nombre,
                'Paterno'=> $data->Paterno,
                'Materno'=> $data->Materno,
                'created_at' =>  $data->created_at,
                'updated_at' =>  $data->updated_at,
                'UserCreated' => [
                    'id'=> $data->idE,
                    'email'=> $data->emailE,
                    'Nombre'=> $data->NombreE,
                    'Paterno'=> $data->PaternoE,
                    'Materno'=> $data->MaternoE,
                    'idTipoUser' =>[
                        'id'=> $data->idTipoUserE,
                        'TipoUser'=> $data->TipoUserEA,
                    ]
                ],
                'UserUpdated' => [
                    'id'=> $data->idF,
                    'email'=> $data->emailF,
                    'Nombre'=> $data->NombreF,
                    'Paterno'=> $data->PaternoF,
                    'Materno'=> $data->MaternoF,
                    'idTipoUser' =>[
                        'id'=> $data->idTipoUserF,
                        'TipoUser'=> $data->TipoUserFA,
                    ]
                ],
                'idStatus' =>  [
                    'id'=>$data->idD,
                    'Estatus'=>$data->EstatusD,
                    'created_at'=>$data->created_atD,
                    'updated_at'=>$data->updated_atD,
                ],
             ];
             array_push($array_res,$temp);
            }
            
            return ['success'=>true,'results'=>true,
             'total'=>$total,'filtros'=>$parameters['filtered'],'data'=>$array_res];

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

    function setNegociosPagadores(Request $request){

        $v = Validator::make($request->all(), [
            'idNegocio' => 'required', 
            'CURP'=> 'required', 
            'Nombre'=> 'required',  
            'Paterno'=> 'required',  
            'Materno'=> 'required', 
            'idStatus'=> 'required',

        ]);
        
		if ($v->fails()){
            $response =  ['success'=>false,'results'=>false,
            'errors'=>$v->errors(),'data'=>[]];

            return response()->json($response,200);
        }
        $parameters = $request->all();
        $user = auth()->user();
        $parameters['UserCreated'] = $user->id;
        $parameters['UserUpdated'] = $user->id;
        $negocio_ = VNegociosPagadores::create($parameters);

        $negocio = VNegociosPagadores::find($negocio_->id);
        return ['success'=>true,'results'=>true,
            'data'=>$negocio];
    }

    function updateNegociosPagadores(Request $request){

        $v = Validator::make($request->all(), [
            'id' => 'required'
        ]);
        
		if ($v->fails()){
            $response =  ['success'=>false,'results'=>false,
            'errors'=>$v->errors(),'data'=>[]];

            return response()->json($response,200);
        }
        $parameters = $request->all();


        $user = auth()->user();
        $parameters['UserUpdated'] = $user->id;
        $negocio = VNegociosPagadores::find($parameters['id']);
        if(!$negocio){
            return ['success'=>true,'results'=>false,
                'errors'=>'El negocio que desea actualizar no existe.',
                'data'=>[]];
        }
        $negocio->update($parameters);
        return ['success'=>true,'results'=>true,
            'data'=>$negocio];
    }
}
