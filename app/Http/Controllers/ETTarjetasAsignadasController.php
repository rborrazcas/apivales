<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use \Illuminate\Database\QueryException;
use DB;
use Illuminate\Contracts\Validation\ValidationException;
use Validator;
use App\ETTarjetasAsignadas;
use App\ETTarjetas;
use App\ETTarjetasAsignadasHistoricos;
use App\ETAprobadosComite;
use App\ETGrupo;
class ETTarjetasAsignadasController extends Controller
{
    function getTarjetaAsignadaET(Request $request){
        $parameters = $request->all();
        /* 'Terminacion', 
        'id', 
        'idGrupo', 
        'CURP', 
        'Nombre', 
        'Paterno', 
        'Materno', 
        'idMunicipio', 
        'idLocalidad', 
        'Calle', 
        'NumExt', 
        'NumInt', 
        'Colonia', 
        'CP', 
        'TipoGral', 
        'UserCreated', 
        'created_at', 
        'updated_at'
 */
        try {
            $res = DB::table('et_tarjetas_asignadas')
            ->select(
                'et_tarjetas_asignadas.Terminacion',
                'et_aprobadoscomite.FolioC',
                'et_aprobadoscomite.FechaNacimientoC',
                'et_tarjetas_asignadas.id',
                'et_aprobadoscomite.CURP',
                'et_aprobadoscomite.NombreC as Nombre',
                'et_aprobadoscomite.PaternoC as Paterno',
                'et_aprobadoscomite.MaternoC as Materno',
                'et_aprobadoscomite.SexoC as Sexo',
                'et_tarjetas_asignadas.idLocalidad',
                'et_cat_localidad.Nombre AS Localidad',
                
                'et_tarjetas_asignadas.idMunicipio',
                    'et_cat_municipio.Nombre AS Municipio',
                    'et_cat_municipio.SubRegion',
                'et_tarjetas_asignadas.idGrupo',
                'et_grupo.NombreGrupo',
                    'et_grupo.created_at AS FechaGrupo',
                    'et_grupo.UserCreated AS UserCreatedGrupo',
                'et_tarjetas_asignadas.UserCreated',
                    'users.Nombre AS NombreCapturo',
                    'users.Paterno AS PaternoCapturo',
                    'users.Materno AS MaternoCapturo',
                    'users.email AS emailCapturo',
                'et_tarjetas_asignadas.created_at AS created_at',
                'et_aprobadoscomite.CalleC as Calle', 
                'et_aprobadoscomite.NumeroC as NumExt', 
                'et_aprobadoscomite.NumeroInteriorC', 
                'et_aprobadoscomite.ColoniaC as Colonia', 
                'et_aprobadoscomite.CodigoPostalC as CP', 
                'et_aprobadoscomite.TipoGral',
                'et_tarjetas_asignadas.updated_at'
            )
            
            ->leftJoin('et_aprobadoscomite','et_aprobadoscomite.id','=','et_tarjetas_asignadas.id')
            ->leftJoin('et_cat_municipio','et_cat_municipio.Id','=','et_aprobadoscomite.idMunicipioC')
            ->leftJoin('et_cat_localidad','et_cat_localidad.Id','=','et_aprobadoscomite.idLocalidadC')
            ->leftJoin('et_grupo', function ($join) {
                $join->on('et_grupo.id','=','et_tarjetas_asignadas.idGrupo')
                ->on('et_tarjetas_asignadas.idMunicipio','=','et_grupo.idMunicipio');
            })
            ->leftJoin('users','users.id','=','et_tarjetas_asignadas.UserCreated');

            

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

            if(isset($parameters['NombreCompleto'])){
                $persona_recibida = $parameters['NombreCompleto'];
                $persona_recibida = str_replace(" ","",$persona_recibida);
                

                $res->where(
                    DB::raw("
                    REPLACE(
                    CONCAT(
                        
                        et_tarjetas_asignadas.Terminacion,
                        et_aprobadoscomite.NombreC,
                        et_aprobadoscomite.PaternoC,
                        et_aprobadoscomite.MaternoC,
                        et_tarjetas_asignadas.Terminacion,
                        et_aprobadoscomite.NombreC,
                        et_aprobadoscomite.MaternoC,
                        et_aprobadoscomite.PaternoC,
                        et_tarjetas_asignadas.Terminacion,
                        et_aprobadoscomite.PaternoC,
                        et_aprobadoscomite.NombreC,
                        et_aprobadoscomite.MaternoC,
                        et_tarjetas_asignadas.Terminacion,
                        et_aprobadoscomite.PaternoC,
                        et_aprobadoscomite.MaternoC,
                        et_aprobadoscomite.NombreC,
                        et_tarjetas_asignadas.Terminacion,
                        et_aprobadoscomite.MaternoC,
                        et_aprobadoscomite.NombreC,
                        et_aprobadoscomite.PaternoC,
                        et_tarjetas_asignadas.Terminacion,
                        et_aprobadoscomite.MaternoC,
                        et_aprobadoscomite.PaternoC,
                        et_aprobadoscomite.NombreC,
                        et_aprobadoscomite.NombreC,
                        et_tarjetas_asignadas.Terminacion,
                        et_aprobadoscomite.PaternoC,
                        et_aprobadoscomite.MaternoC,
                        et_aprobadoscomite.NombreC,
                        et_tarjetas_asignadas.Terminacion,
                        et_aprobadoscomite.MaternoC,
                        et_aprobadoscomite.PaternoC,
                        et_aprobadoscomite.NombreC,
                        et_aprobadoscomite.PaternoC,
                        et_tarjetas_asignadas.Terminacion,
                        et_aprobadoscomite.MaternoC,
                        et_aprobadoscomite.NombreC,
                        et_aprobadoscomite.PaternoC,
                        et_aprobadoscomite.MaternoC,
                        et_tarjetas_asignadas.Terminacion,
                        et_aprobadoscomite.NombreC,
                        et_aprobadoscomite.MaternoC,
                        et_tarjetas_asignadas.Terminacion,
                        et_aprobadoscomite.PaternoC,
                        et_aprobadoscomite.NombreC,
                        et_aprobadoscomite.MaternoC,
                        et_aprobadoscomite.PaternoC,
                        et_tarjetas_asignadas.Terminacion,
                        et_aprobadoscomite.PaternoC,
                        et_tarjetas_asignadas.Terminacion,
                        et_aprobadoscomite.NombreC,
                        et_aprobadoscomite.MaternoC,
                        et_aprobadoscomite.PaternoC,
                        et_tarjetas_asignadas.Terminacion,
                        et_aprobadoscomite.MaternoC,
                        et_aprobadoscomite.NombreC,
                        et_aprobadoscomite.PaternoC,
                        et_aprobadoscomite.NombreC,
                        et_tarjetas_asignadas.Terminacion,
                        et_aprobadoscomite.MaternoC,
                        et_aprobadoscomite.PaternoC,
                        et_aprobadoscomite.NombreC,
                        et_aprobadoscomite.MaternoC,
                        et_tarjetas_asignadas.Terminacion,
                        et_aprobadoscomite.PaternoC,
                        et_aprobadoscomite.MaternoC,
                        et_tarjetas_asignadas.Terminacion,
                        et_aprobadoscomite.NombreC,
                        et_aprobadoscomite.PaternoC,
                        et_aprobadoscomite.MaternoC,
                        et_aprobadoscomite.NombreC,
                        et_tarjetas_asignadas.Terminacion,
                        et_aprobadoscomite.MaternoC,
                        et_tarjetas_asignadas.Terminacion,
                        et_aprobadoscomite.NombreC,
                        et_aprobadoscomite.PaternoC,
                        et_aprobadoscomite.MaternoC,
                        et_tarjetas_asignadas.Terminacion,
                        et_aprobadoscomite.PaternoC,
                        et_aprobadoscomite.NombreC,
                        et_aprobadoscomite.MaternoC,
                        et_aprobadoscomite.NombreC,
                        et_tarjetas_asignadas.Terminacion,
                        et_aprobadoscomite.PaternoC,
                        et_aprobadoscomite.MaternoC,
                        et_aprobadoscomite.NombreC,
                        et_aprobadoscomite.PaternoC,
                        et_tarjetas_asignadas.Terminacion,
                        et_aprobadoscomite.MaternoC,
                        et_aprobadoscomite.PaternoC,
                        et_tarjetas_asignadas.Terminacion,
                        et_aprobadoscomite.NombreC,
                        et_aprobadoscomite.MaternoC,
                        et_aprobadoscomite.PaternoC,
                        et_aprobadoscomite.NombreC,
                        et_tarjetas_asignadas.Terminacion
                        
                    ), ' ', '')"
                    )
            
            ,'like',"%".$persona_recibida."%");
                
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

    function setTarjetaAsignadaET(Request $request){

        $v = Validator::make($request->all(), [
            'Terminacion'=> 'required|unique:et_tarjetas_asignadas',
            'id'=>'required|unique:et_tarjetas_asignadas',
            'CURP'=> 'required',
            'Nombre'=> 'required',
            'Paterno'=> 'required',
            'Materno'=> 'required',
            'idMunicipio'=> 'required',
            'idGrupo'=> 'required',
            'idLocalidad'=>'required',
        ]);
        
		if ($v->fails()){
            $response =  ['success'=>false,'results'=>false,
            'errors'=>$v->errors(),'data'=>[]];

            return response()->json($response,200);
        }

        try {
            $parameters = $request->all();

            $terminacion_recibido = $parameters["Terminacion"];
                $terminacion_recibido = str_replace(" ","",$terminacion_recibido);

            $obj_terminacion = ETTarjetas::where('Terminacion',$terminacion_recibido)->first();
            if(!$obj_terminacion){
                return ['success'=>true,'results'=>false,
                'errors'=>'La terminacion de tarjeta que desea ligar no existe.',
                'data'=>[]];
            }
            //Checar el comite
            $persona_aprobada_comite = ETAprobadosComite::find($parameters["id"]);

            if($persona_aprobada_comite == null){
                return ['success'=>true,'results'=>false,
                'errors'=>'Hubo un problema al buscar la persona que desea ligar.',
                'data'=>[]];
            }

            $grupo = ETGrupo::find($parameters["idGrupo"]);
            if($grupo == null){
                return ['success'=>true,'results'=>false,
                'errors'=>'Hubo un problema al buscar el grupo que desea ligar.',
                'data'=>[]];
            }

            // if($persona_aprobada_comite->idMunicipioC != $grupo->idMunicipio){
            //     return ['success'=>true,'results'=>false,
            //     'errors'=>'Hubo un problema, El municipio de la persona no coincide con el municipio del grupo.',
            //     'data'=>[]];
            // }

            $user = auth()->user();
            $parameters['UserCreated'] = $user->id;
            $ETTarjetasAsignadas = ETTarjetasAsignadas::create($parameters);
            $obj = ETTarjetasAsignadas::where('Terminacion','=',$ETTarjetasAsignadas->Terminacion)->first();
            
            return ['success'=>true,'results'=>true,
                'data'=>$obj];
        } catch(QueryException $e){
            return [
                'success' => false,
                'errors' => $e->getMessage()
            ];
        }
    }

    function deleteTarjetaAsignadaET(Request $request){

        $v = Validator::make($request->all(), [
            'Terminacion'=> 'required',
        ]);
        
		if ($v->fails()){
            
            $response =  ['success'=>false,'results'=>false,
            'errors'=>$v->errors(),'data'=>[]];

            return response()->json($response,200);
        }
        $parameters = $request->all();
        

        /* $obj = ETTarjetasAsignadas::where('Terminacion','=',$parameters["Terminacion"])->first();
        if(!$obj){
            return ['success'=>false,'results'=>false,
            'data'=>'El registro que desea eliminar no fue encontrado.'];
        } */
        /*
        'uid', 'Terminacion', 'id', 'idGrupo', 'CURP', 'Nombre',
         'Paterno', 'Materno', 'idMunicipio', 'idLocalidad', 'Calle', 
         'NumExt', 'NumInt', 'Colonia', 'CP', 'TipoGral', 'UserCreated', 
         'created_at', 'UserDeleted', 'deleted_at', 'updated_at'
        */
        /* $obj_deleted = new ETTarjetasAsignadasHistoricos;
        $obj_deleted->Terminacion=$obj->Terminacion;
        $obj_deleted->id=$obj->id;
        $obj_deleted->idGrupo=$obj->idGrupo;
        $obj_deleted->CURP=$obj->CURP;
        $obj_deleted->Nombre=$obj->Nombre;
        $obj_deleted->Paterno=$obj->Paterno;
        $obj_deleted->Materno=$obj->Materno;
        $obj_deleted->idMunicipio=$obj->idMunicipio;
        $obj_deleted->idLocalidad=$obj->idLocalidad;
        $obj_deleted->Calle=$obj->Calle;
        $obj_deleted->NumExt=$obj->NumExt;
        $obj_deleted->NumInt=$obj->NumInt;
        $obj_deleted->Colonia=$obj->Colonia;
        $obj_deleted->CP=$obj->CP;
        $obj_deleted->TipoGral=$obj->TipoGral;
        $obj_deleted->UserCreated=$obj->UserCreated;
        $obj_deleted->created_at=$obj->created_at;
        $obj_deleted->UserDeleted=auth()->user()->id;
        $obj_deleted->deleted_at= date("Y-m-d H:i:s"); 
        $obj_deleted->updated_at=$obj->updated_at;
        $obj_deleted->save();
        $obj->delete(); */
        
        return ['success'=>true,'results'=>false,
            'errors'=>'Favor de consultar esta accion con el administrador del sistema.'];
    }
}
