<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use \Illuminate\Database\QueryException;
use DB;
use Illuminate\Contracts\Validation\ValidationException;
use Validator;
use App\ETGrupoUsers;

class ETGrupoUsersController extends Controller
{
    function getTarjetasAsignadasGrupoET(Request $request){
        $parameters = $request->all();

        try {
            $res = DB::table('et_grupo_users')
            ->select(
                'et_grupo_users.idGrupo',
                'et_grupo_users.idUser',
                'users.email AS emailU',
                'users.Nombre AS NombreU',
                'users.Paterno AS PaternoU',
                'users.Materno AS MaternoU',
                'users.idTipoUser',
                'cat_usertipo.TipoUser',
                'et_grupo_users.created_at',
                'et_tarjetas_asignadas.Terminacion',
                'et_tarjetas_asignadas.id',
                'et_tarjetas_asignadas.CURP',
                'et_tarjetas_asignadas.Nombre',
                'et_tarjetas_asignadas.Paterno',
                'et_tarjetas_asignadas.Materno',
                'et_tarjetas_asignadas.idMunicipio',
                'et_cat_municipio.Nombre AS Municipio',
                'et_cat_municipio.SubRegion',
                'et_aprobadoscomite.SexoC',
                'et_aprobadoscomite.CalleC',
                'et_aprobadoscomite.NumeroC',
                'et_aprobadoscomite.NumeroInteriorC',
                'et_aprobadoscomite.ColoniaC',
                'et_aprobadoscomite.idLocalidadC',
                'et_cat_localidad.Numero AS NumeroLocalidad',
                'et_cat_localidad.Nombre AS Localidad',
                'et_aprobadoscomite.CodigoPostalC',
                'et_aprobadoscomite.FolioC',
                'et_aprobadoscomite.EstatusExpediente'
            )
            ->leftJoin('et_tarjetas_asignadas','et_tarjetas_asignadas.idGrupo','=','et_grupo_users.idGrupo')
            ->leftJoin('users','users.id','=','et_grupo_users.idUser')
            ->leftJoin('cat_usertipo','cat_usertipo.id','=','users.idTipoUser')
            ->leftJoin('et_cat_municipio','et_cat_municipio.Id','=','et_tarjetas_asignadas.idMunicipio')
            ->leftJoin('et_aprobadoscomite','et_aprobadoscomite.id','=','et_tarjetas_asignadas.id')
            ->leftJoin('et_cat_localidad','et_cat_localidad.Id','=','et_aprobadoscomite.idLocalidadC')
            ->distinct();

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

    function setGrupoUserET(Request $request){

        $v = Validator::make($request->all(), [
            'idUser'=> 'required|unique:et_grupo_users',
            'idGrupo'=> 'required',
        ]);
        
		if ($v->fails()){
            
            $response =  ['success'=>false,'results'=>false,
            'errors'=>$v->errors(),'data'=>[]];

            return response()->json($response,200);
        }
        try {
        $parameters = $request->all();
        $ETGrupoUsers = ETGrupoUsers::create($parameters);
        $obj_g_u = ETGrupoUsers::where('idUser','=',$ETGrupoUsers->idUser)->first();
        

        
        return ['success'=>true,'results'=>true,
            'data'=>$obj_g_u];
        } catch(QueryException $e){
            return [
                'success' => false,
                'errors' => $e->getMessage()
            ];
        }
    }
}
