<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use \Illuminate\Database\QueryException;
use DB;
use Illuminate\Contracts\Validation\ValidationException;
use Validator;
use App\ValesGrupos;

class ValesGruposController extends Controller
{
    function getGrupos(Request $request){
        $parameters = $request->all();

        try {
            //Concatenacion : Remesa, Municipio,Nombre,Paterno,Materno
            //Total, Cuantos Faltan, Cuantos Llevo.
            //Texto incompleto en la tablita de listado de grupos.
            
            $res = DB::table('vales_grupos')
            ->select(
                'vales_grupos.id',
                'vales_grupos.UserOwned',
                DB::raw('concat_ws(" ",users.Nombre, users.Paterno, users.Materno) AS Articulador'),
                'vales_grupos.idMunicipio', 
                'et_cat_municipio.Nombre as Municipio', 
                'vales_grupos.TotalAprobados', 
                'vales_grupos.Remesa', 
                'vales_grupos.created_at', 
                'vales_grupos.UserCreated', 
                'vales_grupos.updated_at'
                //DB::raw('count(vales.id) as Capturados')
            )
            ->leftJoin('users','users.id','=','vales_grupos.UserOwned')
            ->leftJoin('et_cat_municipio','et_cat_municipio.id','=','vales_grupos.idMunicipio');
            //->leftJoin('vales','vales.UserOwned','=','vales_grupos.UserOwned')
            //->whereIn('vales.id',function($query){
            //    $query->select('idSolicitud')->from('vales_solicitudes');
            // })
            // ->where('vales.idStatus','=',5)
             //->groupBy('vales_grupos.id');
           


            $flag = 0;
            if(isset($parameters['Propietario'])){
                $valor_id = $parameters['Propietario'];
                $res->where(function($q)use ($valor_id) {
                    $q->where('vales_grupos.UserCreated', $valor_id)
                      ->orWhere('vales_grupos.UserOwned', $valor_id);
                });
            }
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
                $filtro_recibido = $parameters['NombreCompleto'];
                $filtro_recibido = str_replace(" ","",$filtro_recibido);
                $res->where(
                    DB::raw("
                    REPLACE(
                    CONCAT(
                        vales_grupos.Remesa,
                        et_cat_municipio.Nombre,
                        users.Nombre,
                        users.Paterno,
                        users.Materno,

                        vales_grupos.Remesa,
                        et_cat_municipio.Nombre,
                        users.Paterno,
                        users.Nombre,
                        users.Materno,

                        vales_grupos.Remesa,
                        et_cat_municipio.Nombre,
                        users.Materno,
                        users.Nombre,
                        users.Paterno,

                        vales_grupos.Remesa,
                        et_cat_municipio.Nombre,
                        users.Nombre,
                        users.Materno,
                        users.Paterno,

                        vales_grupos.Remesa,
                        et_cat_municipio.Nombre,
                        users.Paterno,
                        users.Materno,
                        users.Nombre,

                        vales_grupos.Remesa,
                        et_cat_municipio.Nombre,
                        users.Materno,
                        users.Paterno,
                        users.Nombre
                    ), ' ', '')")
            
                    ,'like',"%".$filtro_recibido."%");
                
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
            'errors'=>$e->getMessage(), 'message' =>'Campo de consulta incorrecto'];

            return  response()->json($response, 200);
        }

    }

    function setGrupos(Request $request){
        $v = Validator::make($request->all(), [
            'UserOwned' => 'required',//|unique:vales_grupos',
            'idMunicipio' => 'required',
            'TotalAprobados' => 'required',
            'Remesa' => 'required',
            'idIncidencia' => 'required'
        ]);
        
		if ($v->fails()){
            $response =  ['success'=>false,'results'=>false,
            'errors'=>$v->errors(),'data'=>[]];

            return response()->json($response,200);
        }
        try{
            $parameters = $request->all();

            //Checar vales idIncidencia del owned 
            //$parameters['UserOwned'];
            $res_in = DB::table('vales_solicitudes')
            ->select('vales_solicitudes.idSolicitud')
            ->toSql();

            $res_validacion = DB::table('vales')
            ->select(
                'vales.id' 
            )
            ->where('vales.idStatus','=',5)
            ->where('vales.UserOwned','=',$parameters['UserOwned'])
            ->where('vales.idMunicipio','=',$parameters['idMunicipio'])
            ->where('vales.Remesa','=',$parameters['Remesa'])
            //->whereNotIn('vales.id',DB::raw($res_1));
            ->where('vales.idIncidencia','=',$parameters['idIncidencia'])
            ->whereRaw('vales.id NOT IN('.$res_in.')')
            ->get();
            
            if(count($res_validacion->toArray()) == 0){
                $response = ['success'=>true,'results'=>false,'data'=>[],
                'message'=>'Las solicitudes pertenecientes a este grupo no cumplieron la validacion de incidencia.'];
                // 'message' =>'El grupo que desea registrar ya se encuentra registrado.'];
                return  response()->json($response, 200);

            }

            $grupo_recibido = $parameters['UserOwned'].$parameters['idMunicipio'].$parameters['Remesa'];
            $grupo_recibido = str_replace(" ","",$grupo_recibido);
            $res = ValesGrupos::where(
                DB::raw("
                REPLACE(CONCAT(UserOwned,idMunicipio,Remesa), ' ', '')
                ")
                ,'=',$grupo_recibido
            )->first();
            if($res){
                $response = ['success'=>true,'results'=>true,'data'=>$res];
                // 'message' =>'El grupo que desea registrar ya se encuentra registrado.'];
                return  response()->json($response, 200);

            }
            
            $user = auth()->user();
            $parameters['UserCreated'] = $user->id;
            $grupo_ = ValesGrupos::create($parameters);
            $grupo = ValesGrupos::find($grupo_->id);
            return ['success'=>true,'results'=>true,
                'data'=>$grupo];

        }
        catch(QueryException $e){
            $errors = [
                "Clave"=>"01"
            ];
            $response = ['success'=>true,'results'=>false, 
            'total'=>0,'filtros'=>$parameters['filtered'],
            'errors'=>$e->getMessage(), 'message' =>'Hubo un error a al crear el registro'];

            return  response()->json($response, 200);
        }
        
    }
}
