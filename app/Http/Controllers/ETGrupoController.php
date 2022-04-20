<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use \Illuminate\Database\QueryException;
use DB;
use  JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Contracts\Validation\ValidationException;
use Validator;
use App\ETGrupo;

class ETGrupoController extends Controller
{
    function getGrupoET(Request $request){
        $parameters = $request->all();

        try {
            
            
            $res_1 = DB::table('et_grupo as G')
            ->select(
                'G.id',
                'G.idMunicipio',
                'et_cat_municipio.Nombre AS Municipio',
                'G.NombreGrupo',
                'G.idStatus',
                'et_cat_municipio.SubRegion',
                'G.created_at',
                'G.UserCreated',
                    'users.email',
                    'users.Nombre',
                    'users.Paterno',
                    'users.Materno',
                    'users.idTipoUser',
                        'cat_usertipo.TipoUser',
                        'cat_usertipo.Clave'
            )
            ->leftJoin('et_cat_municipio','et_cat_municipio.Id','=','G.idMunicipio')
            ->leftJoin('users','users.id','=','G.UserCreated')
            ->leftJoin('cat_usertipo','cat_usertipo.id','=','users.idTipoUser');


            $res_2 = DB::table('et_tarjetas_asignadas as A')
            ->select(
                'A.idGrupo', 
                DB::raw('count(A.idGrupo) TotalAsignado')
            )
            ->groupBy('idGrupo');

             
            $res = DB::table( DB::raw("({$res_1->toSql()}) as G"))
            ->select(
                'G.id', 
                'A.TotalAsignado', 
                'G.idMunicipio', 
                'G.NombreGrupo', 
                'G.Municipio', 
                'G.SubRegion', 
                'G.created_at', 
                'G.UserCreated as idUserCreated', 
                'G.email', 
                'G.Nombre', 
                'G.Paterno', 
                'G.Materno', 
                'G.idTipoUser', 
                'G.TipoUser', 
                'G.Clave',
                'users.email',
                    'users.Nombre',
                    'users.Paterno',
                    'users.Materno',
                    'users.idTipoUser',
                        'cat_usertipo.TipoUser',
                        'cat_usertipo.Clave',
            )
                ->leftJoin(DB::raw("({$res_2->toSql()}) as A"),'A.idGrupo','=','G.id')
                ->leftJoin('et_cat_municipio','et_cat_municipio.Id','=','G.idMunicipio')
            ->leftJoin('users','users.id','=','G.UserCreated')
            ->leftJoin('cat_usertipo','cat_usertipo.id','=','users.idTipoUser');

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
                            if(strcmp($parameters['filtered'][$i]['id'], 'UserCreated') === 0 || strcmp($parameters['filtered'][$i]['id'], 'UserUpdated') === 0){
                               
                                $res->where($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);

                            }else{
                            $res->where($parameters['filtered'][$i]['id'],'LIKE','%'.$parameters['filtered'][$i]['value'].'%');
                            }
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
                                if(strcmp($parameters['filtered'][$i]['id'], 'UserCreated') === 0 || strcmp($parameters['filtered'][$i]['id'], 'UserUpdated') === 0){
                                    
                                    $res->where($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);

                                }else{
                                $res->where($parameters['filtered'][$i]['id'],'LIKE','%'.$parameters['filtered'][$i]['value'].'%');
                                }
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
                            G.id,
                            G.NombreGrupo,
                            Municipio,
                            
                            G.NombreGrupo,
                            G.id,
                            Municipio,

                            Municipio,
                            G.id,
                            G.NombreGrupo,

                            G.id,
                            Municipio,
                            G.NombreGrupo,

                            
                            G.NombreGrupo,
                            Municipio,
                            G.id,

                            
                            Municipio,
                            G.NombreGrupo,
                            G.id
                            
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


            
            return ['success'=>true,'results'=>true,'total'=>$total,'filtros'=>$parameters['filtered'],'data'=>$res];

        } catch(QueryException $e){
            return [
                'success' => false,
                'errors' => $e->getMessage()
            ];
        }

    }

    function setGrupoET(Request $request){//AQUI ID USUARIO

        $v = Validator::make($request->all(), [
            'idMunicipio' => 'required',
            'NombreGrupo' => 'required',
        ]);
        
		if ($v->fails()){
            $response =  ['success'=>false,'results'=>false,
            'errors'=>$v->errors(),'data'=>[]];

            return response()->json($response,200);
        }
        try {



        $parameters = $request->all();
        $user = auth()->user();
        $parameters['UserCreated'] = $user->id;


        $grupo_recibido = $parameters['idMunicipio'].$parameters['NombreGrupo'].$user->id;
        $grupo_recibido = str_replace(" ","",$grupo_recibido);
        $res = DB::table('et_grupo')->select(
            'id',
            DB::raw("
            REPLACE(
            CONCAT(
                idMunicipio,
                NombreGrupo,
                UserCreated
            ), ' ', '') as NombreCompleto")
            )->get();
            
            $flag=false;
            $id_existente=0;
            for ($i=0; $i < $res->count();  $i++) { 
                if(strcasecmp($grupo_recibido, $res[$i]->NombreCompleto) === 0){
                    $flag=true;
                    $id_existente = $res[$i]->id;
                    break;
                }
            }
            if($flag){
                 if(!isset($parameters['filtered'])){
                    $parameters['filtered']=[];
                }
                $grupo_existente = ETGrupo::find($id_existente); 
                $response = ['success'=>false,'results'=>false,
                'filtros'=>$parameters['filtered'],'errors'=>'El Grupo que quizo registrar ya se encuentra registrada.', 'Grupo Existente'=>$grupo_existente];

                return response()->json($response,200);
            }





        $ETGrupo = ETGrupo::create($parameters);



        return ['success'=>true,'results'=>true,
            'data'=>$ETGrupo];
        } catch(QueryException $e){
            return [
                'success' => false,
                'errors' => $e->getMessage()
            ];
        }
    }

    public  function  updateGrupoET(Request  $request) {
		
		$v = Validator::make($request->all(), [
            'id'=>'required',
		]);
		
        
		if ($v->fails()){
           
            $response =  ['success'=>false,'results'=>false,
            'errors'=>$v->errors(),'data'=>[]];

            return response()->json($response,200);
		}
		$parameters = $request->all();
        

		$grupo = ETGrupo::find($parameters['id']);
		if(!$grupo){
			$response =  ['success'=>true,'results'=>false,
			'errors'=>'El grupo que desea actualizar no existe.'];

			return response()->json($response,200);

		}
		$user_loggeado = auth()->user();
		$parameters["UserUpdated"]= $user_loggeado->id;
		$grupo->update($parameters);
		

		$response =  ['success'=>true,'results'=>true,
		'data'=>$grupo];

		return response()->json($response,200);
	}
}
