<?php

namespace App\Http\Controllers\ControllersPulseras;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use \Illuminate\Database\QueryException;
use Illuminate\Contracts\Validation\ValidationException;
use App\ModelsPulseras\{CodigoBarra};
use Validator;
use JWTAuth;
use DB;
use Carbon\Carbon as time;

class CodigoBarraController extends Controller
{
    function getListadoCodigoBarra(Request $request){
        try {
            //$user = auth()->user();
            $v = Validator::make($request->all(), [
                'filtered'=> 'array',
                'sorted'=> 'array',
                'page'=> 'required',
                'pageSize'=> 'required',
                'tipo'=> 'required'
            ]);
            if ($v->fails()){
                $response =  ['success'=>false,'results'=>false,
                'errors'=>$v->errors(),'data'=>[]];
                return response()->json($response,200);
            }
            $parameters = $request->all();

            $res = DB::table('bravos_codigobarras')
            ->select(
                'bravos_codigobarras.id',
                'bravos_codigobarras.Folio',
                'bravos_codigobarras.CodigoBarras',
                'bravos_codigobarras.created_at',
                'bravos_codigobarras.updated_at',
                'bravos_codigobarras.UserCreated',
                'bravos_codigobarras.UserOwned',
                'bravos_codigobarras.UserUpdated'

            );
            $flag = 0;
            if(isset($parameters['filtered'])){

                for($i=0;$i<count($parameters['filtered']);$i++){

                    if($flag==0){
                        if($parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'id') !== false
                        || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'Folio') !== false
                        || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'User') !== false
                        ){
                            if(is_array ($parameters['filtered'][$i]['value'])){
                                $res->whereIn($parameters['filtered'][$i]['id'],$parameters['filtered'][$i]['value']);
                            }else{
                                $res->where($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
                            }
                            
                        }else{
                                if(strpos($parameters['filtered'][$i]['id'], 'is') !== false){
                                    $res->where($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
                                }else{
                                    $res->where($parameters['filtered'][$i]['id'],'LIKE','%'.$parameters['filtered'][$i]['value'].'%');
                                }
                        }
                        $flag = 1;
                    }
                    else{
                        if($parameters['tipo']=='and'){
                            if($parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'id') !== false
                            || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'Folio') !== false
                            || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'User') !== false
                            ){
                                if(is_array($parameters['filtered'][$i]['value'])){
                                    $res->whereIn($parameters['filtered'][$i]['id'],$parameters['filtered'][$i]['value']);
                                }else{
                                    $res->where($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
                                }
                            }else{
                                    if(strpos($parameters['filtered'][$i]['id'], 'is') !== false){
                                        $res->where($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
                                    }else{
                                        $res->where($parameters['filtered'][$i]['id'],'LIKE','%'.$parameters['filtered'][$i]['value'].'%');
                                    }
                            } 
                        }
                        else{
                            if($parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'id') !== false
                            || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'Folio') !== false
                            || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'User') !== false
                            ){
                                if(is_array ($parameters['filtered'][$i]['value'])){
                                    $res->orWhereIn($parameters['filtered'][$i]['id'],$parameters['filtered'][$i]['value']);
                                }else{
                                    $res->orWhere($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
                                }
                            }else{
                                    if(strpos($parameters['filtered'][$i]['id'], 'is') !== false){
                                        $res->orWhere($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
                                    }else{
                                    $res->orWhere($parameters['filtered'][$i]['id'],'LIKE','%'.$parameters['filtered'][$i]['value'].'%');
                                    }
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

            
            return ['success'=>true,'results'=>true,
             'total'=>$total,'filtros'=>$parameters['filtered'],'data'=>$res];
        } 
        catch(QueryException $e){
            $errors = [
                "Clave"=>"01"
            ];
            $response = ['success'=>true,'results'=>false, 
            'total'=>0,'filtros'=>$parameters['filtered'],
            'errors'=>$e->getMessage(), 'message' =>'Campo de consulta incorrecto'];

            return  response()->json($response, 200);
        }
    }
    function setCodigoBarra(Request $request){
        try {
            //$user = auth()->user();
            $v = Validator::make($request->all(), [
                'CodigoBarras' => 'required'
            ]);
            if ($v->fails()){
                $response =  ['success'=>false,'results'=>false,
                'errors'=>$v->errors(),'data'=>[]];
                return response()->json($response,200);
            }
            $parameters = $request->all();

            $res = DB::table('bravos_codigobarras')
            ->select(
                'bravos_codigobarras.id',
                'bravos_codigobarras.Folio',
                'bravos_codigobarras.CodigoBarras',
                'bravos_codigobarras.created_at',
                'bravos_codigobarras.updated_at',
                'bravos_codigobarras.UserCreated',
                'bravos_codigobarras.UserOwned',
                'bravos_codigobarras.UserUpdated'

            )->where('CodigoBarras',$parameters['CodigoBarras'])->first();
            if($res){
                $response =  ['success'=>true,'results'=>false,
                'errors'=>["CodigoBarras"=>["El código de barras ya existe"]],
                'message'=>'El código de barras que intenta registrar ya existe.'];

                return response()->json($response,200);
            }
            $user = auth()->user();
            $parameters['UserCreated'] = $user->id;
            $parameters['UserUpdated'] = $user->id;
            $parameters['UserOwned'] = $user->id;
            
            $CodigoBarra_ = CodigoBarra::create($parameters);
            dd($CodigoBarra_);
            $CodigoBarra = DB::table('bravos_codigobarras')
            ->select(
                'bravos_codigobarras.id',
                'bravos_codigobarras.Folio',
                'bravos_codigobarras.CodigoBarras',
                'bravos_codigobarras.created_at',
                'bravos_codigobarras.updated_at',
                'bravos_codigobarras.UserCreated',
                'bravos_codigobarras.UserOwned',
                'bravos_codigobarras.UserUpdated'

            )->where('CodigoBarras',$CodigoBarra_->id)->first();
            
            return ['success'=>true,'results'=>true,'filtros'=>'Sin filtros','data'=>$CodigoBarra];
        } 
        catch(QueryException $e){
            $errors = [
                "Clave"=>"01"
            ];
            $response = ['success'=>true,'results'=>false, 
            'total'=>0,'filtros'=>$parameters['filtered'],
            'errors'=>$e->getMessage(), 'message' =>'Campo de consulta incorrecto'];

            return  response()->json($response, 200);
        }
    }
    
    function getDisponibilidadCodigoBarra(Request $request){
        try {
            //$user = auth()->user();
            $v = Validator::make($request->all(), [
                'CodigoBarras' => 'required'
            ]);
            if ($v->fails()){
                $response =  ['success'=>false,'results'=>false,
                'errors'=>$v->errors(),'data'=>[]];
                return response()->json($response,200);
            }
            $parameters = $request->all();
                if(strlen($parameters['CodigoBarras']) === 5){
                    $codigo_ = CodigoBarra::where('CodigoBarras',$parameters['CodigoBarras'])->first();
                    if(!$codigo_){
                        $response =  ['success'=>true,'results'=>false,
                        'errors'=>["CodigoBarras"=>["El Codigo de Barras no existe"]],
                        'message'=>'El Codigo de Barras que desea asignar no existe.'];

                        return response()->json($response,200);

                    }
                    $invitado_asignado = CodigoBarra::where('CodigoBarras',$codigo_->CodigoBarras)->whereNotNull('Folio')->first();
                    if($invitado_asignado){
                        $response =  ['success'=>true,'results'=>false,
                        'errors'=>["CodigoBarras"=>["CodigoBarras asignado a otro invitado."]],
                        'message'=>'El código de barras que desea asignar ya se encuentra asignado.'];

                        return response()->json($response,200);

                    }
                    return ['success'=>true,'results'=>true,'filtros'=>'Sin filtros','data'=>$codigo_];
                }
                else if(strlen($parameters['CodigoBarras']) < 4) {
                    if(is_numeric($parameters['CodigoBarras'])){
                        $codigo_ = CodigoBarra::where('id','=',$parameters['CodigoBarras'])->first();
                        if(!$codigo_){
                            $response =  ['success'=>true,'results'=>false,
                            'errors'=>["CodigoBarras"=>["El Codigo de Barras no existe"]],
                            'message'=>'El Codigo de Barras que desea asignar no existe.'];

                            return response()->json($response,200);

                        }
                        $invitado_asignado = CodigoBarra::where('id','=',$parameters['CodigoBarras'])->whereNotNull('Folio')->first();
                        if($invitado_asignado){
                            $response =  ['success'=>true,'results'=>false,
                            'errors'=>["CodigoBarras"=>["CodigoBarras asignado a otro invitado."]],
                            'message'=>'El código de barras que desea asignar ya se encuentra asignado.'];

                            return response()->json($response,200);

                        }
                        return ['success'=>true,'results'=>true,'filtros'=>'Sin filtros','data'=>$codigo_];
                    }
                    else{
                        return ['success'=>true,'results'=>false,'filtros'=>'Sin filtros',
                        'errors'=>["CodigoBarras"=>["CodigoBarras Invalido."]],
                        'message'=>'El código de barras que busca es invalido.'];
                    }
                    

                }
                else{
                    return ['success'=>true,'results'=>false,'filtros'=>'Sin filtros',
                    'errors'=>["CodigoBarras"=>["CodigoBarras Invalido."]],
                    'message'=>'El código de barras que busca es invalido.'];
                }
                
        } 
        catch(QueryException $e){
            $errors = [
                "Clave"=>"01"
            ];
            $response = ['success'=>true,'results'=>false, 
            'total'=>0,'filtros'=>$parameters['filtered'],
            'errors'=>$e->getMessage(), 'message' =>'Campo de consulta incorrecto'];

            return  response()->json($response, 200);
        }
    }

    
    //APIS MOVIL 
    function getListadoCodigoBarraMovil(Request $request){
        try {
            //$user = auth()->user();
            $v = Validator::make($request->all(), [
                'idUser'=> 'required | numeric',
                'filtered'=> 'array',
                'sorted'=> 'array',
                'page'=> 'required',
                'pageSize'=> 'required',
                'tipo'=> 'required'
            ]);
            if ($v->fails()){
                $response =  ['success'=>false,'results'=>false,
                'errors'=>$v->errors(),'data'=>[]];
                return response()->json($response,200);
            }
            $parameters = $request->all();

            $user = DB::table('users')->where('id',$parameters['idUser'])->first(); //auth()->user();
            if(!$user){
                $response =  ['success'=>true,'results'=>false,
                'errors'=>["idUser"=>["El usuario no existe."]],
                'message'=>'El usuario que hizo la petición no existe.'];

                return response()->json($response,200);
            }

            $res = DB::table('bravos_codigobarras')
            ->select(
                'bravos_codigobarras.id',
                'bravos_codigobarras.Folio',
                'bravos_codigobarras.CodigoBarras',
                'bravos_codigobarras.created_at',
                'bravos_codigobarras.updated_at',
                'bravos_codigobarras.UserCreated',
                'bravos_codigobarras.UserOwned',
                'bravos_codigobarras.UserUpdated'

            );
            $flag = 0;
            if(isset($parameters['filtered'])){

                for($i=0;$i<count($parameters['filtered']);$i++){

                    if($flag==0){
                        if($parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'id') !== false
                        || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'Folio') !== false
                        || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'User') !== false
                        ){
                            if(is_array ($parameters['filtered'][$i]['value'])){
                                $res->whereIn($parameters['filtered'][$i]['id'],$parameters['filtered'][$i]['value']);
                            }else{
                                $res->where($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
                            }
                            
                        }else{
                                if(strpos($parameters['filtered'][$i]['id'], 'is') !== false){
                                    $res->where($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
                                }else{
                                    $res->where($parameters['filtered'][$i]['id'],'LIKE','%'.$parameters['filtered'][$i]['value'].'%');
                                }
                        }
                        $flag = 1;
                    }
                    else{
                        if($parameters['tipo']=='and'){
                            if($parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'id') !== false
                            || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'Folio') !== false
                            || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'User') !== false
                            ){
                                if(is_array($parameters['filtered'][$i]['value'])){
                                    $res->whereIn($parameters['filtered'][$i]['id'],$parameters['filtered'][$i]['value']);
                                }else{
                                    $res->where($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
                                }
                            }else{
                                    if(strpos($parameters['filtered'][$i]['id'], 'is') !== false){
                                        $res->where($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
                                    }else{
                                        $res->where($parameters['filtered'][$i]['id'],'LIKE','%'.$parameters['filtered'][$i]['value'].'%');
                                    }
                            } 
                        }
                        else{
                            if($parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'id') !== false
                            || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'Folio') !== false
                            || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'User') !== false
                            ){
                                if(is_array ($parameters['filtered'][$i]['value'])){
                                    $res->orWhereIn($parameters['filtered'][$i]['id'],$parameters['filtered'][$i]['value']);
                                }else{
                                    $res->orWhere($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
                                }
                            }else{
                                    if(strpos($parameters['filtered'][$i]['id'], 'is') !== false){
                                        $res->orWhere($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
                                    }else{
                                    $res->orWhere($parameters['filtered'][$i]['id'],'LIKE','%'.$parameters['filtered'][$i]['value'].'%');
                                    }
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

            
            return ['success'=>true,'results'=>true,
             'total'=>$total,'filtros'=>$parameters['filtered'],'data'=>$res];
        } 
        catch(QueryException $e){
            $errors = [
                "Clave"=>"01"
            ];
            $response = ['success'=>true,'results'=>false, 
            'total'=>0,'filtros'=>$parameters['filtered'],
            'errors'=>$e->getMessage(), 'message' =>'Campo de consulta incorrecto'];

            return  response()->json($response, 200);
        }
    }
    function setCodigoBarraMovil(Request $request){
        try {
            //$user = auth()->user();
            $v = Validator::make($request->all(), [
                'CodigoBarras' => 'required',
                'idUser'=> 'required | numeric',
            ]);
            if ($v->fails()){
                $response =  ['success'=>false,'results'=>false,
                'errors'=>$v->errors(),'data'=>[]];
                return response()->json($response,200);
            }
            $parameters = $request->all();

            $user = DB::table('users')->where('id',$parameters['idUser'])->first(); //auth()->user();
            if(!$user){
                $response =  ['success'=>true,'results'=>false,
                'errors'=>["idUser"=>["El usuario no existe."]],
                'message'=>'El usuario que hizo la petición no existe.'];

                return response()->json($response,200);
            } 

            $res = DB::table('bravos_codigobarras')
            ->select(
                'bravos_codigobarras.id',
                'bravos_codigobarras.Folio',
                'bravos_codigobarras.CodigoBarras',
                'bravos_codigobarras.created_at',
                'bravos_codigobarras.updated_at',
                'bravos_codigobarras.UserCreated',
                'bravos_codigobarras.UserOwned',
                'bravos_codigobarras.UserUpdated'

            )->where('CodigoBarras',$parameters['CodigoBarras'])->first();
            if($res){
                $response =  ['success'=>true,'results'=>false,
                'errors'=>["CodigoBarras"=>["El código de barras ya existe"]],
                'message'=>'El código de barras que intenta registrar ya existe.'];

                return response()->json($response,200);
            }
            $user = auth()->user();
            $parameters['UserCreated'] = $user->id;
            $parameters['UserUpdated'] = $user->id;
            $parameters['UserOwned'] = $user->id;
            
            $CodigoBarra_ = CodigoBarra::create($parameters);
            dd($CodigoBarra_);
            $CodigoBarra = DB::table('bravos_codigobarras')
            ->select(
                'bravos_codigobarras.id',
                'bravos_codigobarras.Folio',
                'bravos_codigobarras.CodigoBarras',
                'bravos_codigobarras.created_at',
                'bravos_codigobarras.updated_at',
                'bravos_codigobarras.UserCreated',
                'bravos_codigobarras.UserOwned',
                'bravos_codigobarras.UserUpdated'

            )->where('CodigoBarras',$CodigoBarra_->id)->first();
            
            return ['success'=>true,'results'=>true,'filtros'=>'Sin filtros','data'=>$CodigoBarra];
        } 
        catch(QueryException $e){
            $errors = [
                "Clave"=>"01"
            ];
            $response = ['success'=>true,'results'=>false, 
            'total'=>0,'filtros'=>$parameters['filtered'],
            'errors'=>$e->getMessage(), 'message' =>'Campo de consulta incorrecto'];

            return  response()->json($response, 200);
        }
    }
}
