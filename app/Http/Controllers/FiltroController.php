<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use \Illuminate\Database\QueryException;
use DB;
use Illuminate\Contracts\Validation\ValidationException;
use Validator;
use App\Filtro;

class FiltroController extends Controller
{
    function getFiltro(Request $request){
        $parameters = $request->all();

        try {
            $res = DB::table('filtro')
            ->select(
                'filtro.id', 'filtro.idPaciente',
                //Datos persona
                    'personas.id as idB',
                    'personas.CURP as CURPB',
                    'personas.Nombre as NombreB',
                    'personas.Paterno as PaternoB',
                    'personas.Materno as MaternoB',
                    'personas.Sexo as SexoB', 
                    'personas.FechaNacimiento as FechaNacimientoB',
                    'personas.idEntidadNacimiento as idEntidadNacimientoB',
                        'cat_estados.id as idBA',
                        'cat_estados.Estado as EstadoBA',
                        'cat_estados.Clave as ClaveBA',

                    'personas.TelCasa as TelCasaB',
                    'personas.TelCelular as TelCelularB', 
                    'personas.Calle as CalleB',
                    'personas.NumExt as NumExtB', 
                    'personas.NumInt as NumIntB',
                    'personas.Colonia as ColoniaB',
                    'personas.CP as CPB', 
                    'personas.idMunicipio as idMunicipioB',
                        'cat_municipio.id as idBB',
                        'cat_municipio.Municipio as MunicipioBB',
                        'cat_municipio.Clave as ClaveBB',
                    'personas.idLocalidad as idLocalidadB', 
                        'cat_localidades.mapa as mapaBC',
                        'cat_localidades.nom_loc as nom_locBC',
                        'cat_localidades.cve_loc as cve_locBC',
                'filtro.idMunicipioProdencia',
                //Datos Municipio
                    'cat_municipioB.id as idC',
                    'cat_municipioB.Municipio as MunicipioC',
                    'cat_municipioB.Clave as ClaveC',
                'filtro.MotivoConsulta',
                'filtro.TieneFiebre',
                'filtro.TieneTos',
                'filtro.TieneCefaleas',
                'filtro.TuvoExposicion',
                'filtro.OtroSintoma',
                'filtro.TiempoEvolucionSintomas',
                'filtro.Indicaciones',
                'filtro.idServicio',
                //Datos Servicio
                    'cat_servicios.id as idD',
                    'cat_servicios.Servicio as ServicioD',
                'filtro.FechaHoraServicio',
                'filtro.created_at',
                'filtro.updated_at',
                'filtro.UserCreated',
                //Datos Usuario created
                    'users.id as idE',
                    'users.Nombre as NombreE',
                    'users.Paterno as PaternoE',
                    'users.Materno as MaternoE',
                    'users.email as emailE',
                'filtro.UserUpdated',
                //Datos Usuario upated
                    'usersB.id as idF',
                    'usersB.Nombre as NombreF',
                    'usersB.Paterno as PaternoF',
                    'usersB.Materno as MaternoF',
                    'usersB.email as emailF',
            )
            ->leftJoin('personas','personas.id','=','filtro.idPaciente')
            ->leftJoin('cat_estados','cat_estados.id','=','personas.idEntidadNacimiento')
            ->leftJoin('cat_municipio','cat_municipio.id','=','personas.idMunicipio')
            ->leftJoin('cat_localidades','cat_localidades.mapa','=','personas.idLocalidad')

            ->leftJoin('cat_municipio AS cat_municipioB','cat_municipioB.id','=','filtro.idMunicipioProdencia')
            ->leftJoin('cat_servicios','cat_servicios.id','=','filtro.idServicio')
            ->leftJoin('users','users.id','=','filtro.UserCreated')
            ->leftJoin('users AS usersB','usersB.id','=','filtro.UserUpdated');

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
                   'id'=> $data->idB,
                   'CURP'=> $data->CURPB,
                   'Nombre'=> $data->NombreB,
                   'Paterno'=> $data->PaternoB,
                   'Materno'=> $data->MaternoB,
                   'Sexo'=> $data->SexoB,
                   'FechaNacimiento' =>  $data->FechaNacimientoB,
                   'idEntidadNacimiento' =>  [
                    'id'=> $data->idBA,
                    'Estado' => $data->EstadoBA,
                    'Clave' => $data->ClaveBA,
                   ],
                   'TelCasa' =>  $data->TelCasaB,
                   'TelCelular' =>  $data->TelCelularB,
                   'Calle' =>  $data->CalleB,
                   'NumExt' =>  $data->NumExtB,
                   'NumInt' =>  $data->NumIntB,
                   'Colonia' =>  $data->ColoniaB,
                   'CP' =>  $data->CPB,
                   'idMunicipio' =>  [
                       'id'=>$data->idBB,
                       'Municipio'=>$data->MunicipioBB,
                       'Clave'=>$data->ClaveBB,
                   ],

                   'idLocalidad' =>  [
                    'mapa'=>$data->mapaBC,
                    'nom_loc'=>$data->nom_locBC,
                    'cve_loc'=>$data->cve_locBC,
                ],
                ];
                $temp2 = [
                    'id'=> $data->idC,
                    'Municipio'=> $data->MunicipioC,
                    'Clave'=> $data->ClaveC,
                 ];
                 $temp3 = [
                    'id'=> $data->idD,
                    'Servicio'=> $data->ServicioD,
                 ];
                 $temp4 = [
                    'id'=> $data->idE,
                    'Nombre'=> $data->NombreE,
                    'Paterno'=> $data->PaternoE,
                    'Materno'=> $data->MaternoE,
                    'email'=> $data->emailE,
                 ];
                 $temp5 = [
                    'id'=> $data->idF,
                    'Nombre'=> $data->NombreF,
                    'Paterno'=> $data->PaternoF,
                    'Materno'=> $data->MaternoF,
                    'email'=> $data->emailF,
                 ];

                $data_temp = [
                    'id'=>$data->id,
                    'idPaciente'=>$temp,
                    'idMunicipioProdencia'=> $temp2,
                    'MotivoConsulta' => $data->MotivoConsulta,
                    'TieneFiebre'=>$data->TieneFiebre,
                    'TieneTos'=>$data->TieneTos,
                    'TieneCefaleas'=> $data->TieneCefaleas,
                    'TuvoExposicion' => $data->TuvoExposicion,

                    'OtroSintoma'=>$data->OtroSintoma,
                    'TiempoEvolucionSintomas'=>$data->TiempoEvolucionSintomas,
                    'Indicaciones'=> $data->Indicaciones,
                    'idServicio' => $temp3,
                    'FechaHoraServicio'=>$data->FechaHoraServicio,
                    'created_at'=>$data->created_at,
                    'updated_at'=> $data->updated_at,
                    'UserCreated' => $temp4,
                    'UserUpdated' => $temp5
                ];
                
                array_push($array_res,$data_temp);
            }
            
            return ['success'=>true,'results'=>true,
             'total'=>$total,'filtros'=>$parameters['filtered'],'data'=>$array_res];

        } catch(QueryException $e){
            return [
                'success' => false,
                'errors' => $e->getMessage()
            ];
        }

    }

    function setFiltro(Request $request){

        $v = Validator::make($request->all(), [
            'idPaciente' => 'required',
            'idMunicipioProdencia' => 'required',
            'TieneFiebre' => 'required',
            'TieneTos' => 'required',
            'TieneCefaleas' => 'required',
            'TuvoExposicion' => 'required',
            'TiempoEvolucionSintomas' => 'required',
            'idServicio' => 'required',
            'FechaHoraServicio' => 'required',
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
        $user = auth()->user();
        $parameters['UserCreated'] = $user->id;
        $parameters['UserUpdated'] = $user->id;
        $filtro = Filtro::create($parameters);
        if(!isset($parameters['filtered'])){
            $parameters['filtered']=[];
        }
        
        return ['success'=>true,'results'=>true,
            'filtros'=>$parameters['filtered'],'data'=>$filtro];
    }
}
