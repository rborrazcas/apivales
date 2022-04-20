<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use \Illuminate\Database\QueryException;
use DB;
use Illuminate\Contracts\Validation\ValidationException;
use Validator;
use App\VGiros;

class VGirosController extends Controller
{
    function getGiros(Request $request){
        $parameters = $request->all();

        try {
            $res = DB::table('v_giros')
            ->select(
                
                //Datos Giro
                'v_giros.id', 
                'v_giros.Giro',
                'v_giros.created_at',
                'v_giros.updated_at',
                'v_giros.UserCreated',
                //Datos Usuario created
                    'users.id as idE',
                    'users.email as emailE',
                    'users.Nombre as NombreE',
                    'users.Paterno as PaternoE',
                    'users.Materno as MaternoE',
                    'users.idTipoUser',
                        'cat_usertipo.id as idEA',
                        'cat_usertipo.TipoUser as TipoUserEA',
                        'cat_usertipo.Clave as ClaveEA',
                'v_giros.UserUpdated',
                //Datos Usuario updated
                    'usersB.id as idF',
                    'usersB.email as emailF',
                    'usersB.Nombre as NombreF',
                    'usersB.Paterno as PaternoF',
                    'usersB.Materno as MaternoF',
                    'usersB.idTipoUser',
                        'cat_usertipoB.id as idFA',
                        'cat_usertipoB.TipoUser as TipoUserFA',
                        'cat_usertipoB.Clave as ClaveFA'
            )
            ->leftJoin('users','users.id','=','v_giros.UserCreated')
            ->leftJoin('cat_usertipo','cat_usertipo.id','=','users.idTipoUser')
            ->leftJoin('users as usersB','usersB.id','=','v_giros.UserUpdated')
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
                   'Giro'=> $data->Giro,
                    'UserCreated' => [
                        'id'=> $data->idE,
                        'email'=> $data->emailE,
                        'Nombre'=> $data->NombreE,
                        'Paterno'=> $data->PaternoE,
                        'Materno'=> $data->MaternoE,
                        'idTipoUser' =>[
                            'id'=> $data->idEA,
                            'TipoUser'=> $data->TipoUserEA,
                            'Clave'=> $data->ClaveEA,
                        ]
                    ],
                    'UserUpdated' => [
                        'id'=> $data->idF,
                        'email'=> $data->emailF,
                        'Nombre'=> $data->NombreF,
                        'Paterno'=> $data->PaternoF,
                        'Materno'=> $data->MaternoF,
                        'idTipoUser' =>[
                            'id'=> $data->idFA,
                            'TipoUser'=> $data->TipoUserFA,
                            'Clave'=> $data->ClaveFA,
                        ]
                    ]

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

    function setGiros(Request $request){

        $v = Validator::make($request->all(), [
            'Giro' => 'required'
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
        $giro_ = VGiros::create($parameters);
        $giro = VGiros::find($giro_->id);
        return ['success'=>true,'results'=>true,
            'data'=>$giro];
    }

    function updateGiros(Request $request){

        $v = Validator::make($request->all(), [
            'id' => 'required',
            'Giro' => 'required'
        ]);
        
		if ($v->fails()){
            $response =  ['success'=>false,'results'=>false,
            'errors'=>$v->errors(),'data'=>[]];

            return response()->json($response,200);
        }
        $parameters = $request->all();


        $user = auth()->user();
        $parameters['UserUpdated'] = $user->id;
        $giro = VGiros::find($parameters['id']);
        if(!$giro){
            return ['success'=>true,'results'=>false,
                'errors'=>'El giro que desea actualizar no existe.',
                'data'=>[]];
        }
        $giro->update($parameters);
        return ['success'=>true,'results'=>true,
            'data'=>$giro];
    }
}
