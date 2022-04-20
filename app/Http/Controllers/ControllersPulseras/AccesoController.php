<?php

namespace App\Http\Controllers\ControllersPulseras;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use \Illuminate\Database\QueryException;
use Illuminate\Contracts\Validation\ValidationException;
use App\ModelsPulseras\{Acceso};
use App\ModelsPulseras\{ApiLogs};
use App\ModelsPulseras\{Invitado};
use Validator;
use JWTAuth;
use DB;
use Carbon\Carbon as time;

class AccesoController extends Controller
{
    function getListadoAccesos(Request $request){
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

            $res = DB::table('bravos_accesos')
            ->select(
                'id',
                'Folio',
                'Nombres',
                'FechaHoraEscaneada',
                'Observacion',
                'created_at',
                'updated_at',
                'UserCreated',
                'UserOwned',
                'UserUpdated'

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
    function setAcceso(Request $request){
        try {
        $v = Validator::make($request->all(), [
            
            'Folio' => 'required | numeric',
            'Nombres'  => 'required',
            //'FechaHoraEscaneada' => 'required'
            //'Observacion',
            
        ]);
        

        if ($v->fails()){
            $response =  ['success'=>false,'results'=>false,
            'errors'=>$v->errors(),'data'=>[]];

            return response()->json($response,200);
        }

        $parameters = $request->all();

        $invitado = DB::table('bravos_invitados')
        ->select(
            'bravos_invitados.*'
            )
        ->where('bravos_invitados.Folio','=',$parameters['Folio'])->first();

        if(!$invitado){
            $response =  ['success'=>true,'results'=>false,
            'errors'=>["Folio"=>["El folio no existe"]],
			'message'=>'El invitado que desea actualizar no existe.'];

			return response()->json($response,200);
        }
        if(!$invitado->CodigoBarras){
            $response =  ['success'=>true,'results'=>false,
            'errors'=>["CodigoBarras"=>["CodigoBarras en el registro de invitado nulo."]],
			'message'=>'El invitado tiene que tener asignado un código de barras antes de tener acceso.'];

			return response()->json($response,200);
        }
        $user = auth()->user();
        $parameters['UserCreated'] = $user->id;
        $parameters['UserUpdated'] = $user->id;
        $parameters['UserOwned'] = $user->id;
        $parameters['FechaHoraEscaneada'] = time::now();
        
        $acceso_ = Acceso::create($parameters);
        $acceso = DB::table('bravos_accesos')
        ->select(
            'bravos_accesos.*'
            )
        ->where('bravos_accesos.id','=',$acceso_->id)->first();
        return ['success'=>true,'results'=>true,
            'data'=>$acceso];
        } 
        catch(QueryException $e){
            $errors = [
                "Clave"=>"02"
            ];
            $response = ['success'=>true,'results'=>false, 
            'total'=>0,'filtros'=>$parameters['filtered'],
            'errors'=>$e->getMessage(), 'message' =>'¡Algo salió mal en la captura del registro!'];

            return  response()->json($response, 200);
        }
    }

    
    //APIS MOVIL 'idUser'=> 'required | numeric',
    function getListadoAccesosMovil(Request $request){
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
            $res = DB::table('bravos_accesos')
            ->select(
                'id',
                'Folio',
                'Nombres',
                'FechaHoraEscaneada',
                'Observacion',
                'created_at',
                'updated_at',
                'UserCreated',
                'UserOwned',
                'UserUpdated'

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
    function setAccesoMovil(Request $request){
        try {
        $v = Validator::make($request->all(), [
            'data'=> 'array',
            'DeviceID' => 'required'
            //SEGURA2038
        ]);
        

        if ($v->fails()){
            $response =  ['success'=>false,'results'=>false,
            'errors'=>$v->errors(),'data'=>[]];

            return response()->json($response,200);
        }

        $parameters = $request->all();
        
        $ArrayDatos = $parameters['data'];
        $ArrayDatos_Regreso = []; 
        $flag_error=false;
        foreach ($ArrayDatos as $item) {

            
            $log = new ApiLogs;
            $log->DeviceSync=$parameters['DeviceID'];
            $log->Folio=$item['Folio'];
            $log->CodigoBarras=$item['CodigoBarras'];
            $log->FechaHoraAcceso=$item['FechaHoraAcceso'];
            $log->NewInvitado=(isset($item['NewInvitado']))?$item['NewInvitado'] :null;
            $log->isSync=1;
            $log->save();
            

            $invitado = Invitado::where('bravos_invitados.Folio','=',$item['Folio'])->first();
            

            if(!$invitado){
                $flag_error=true;
                $temp = [
                'Folio'=> $item['Folio'],
                'CodigoBarras'=>$item['CodigoBarras'],
                'FechaHoraAcceso'=>$item['FechaHoraAcceso'],
                'NewInvitado'=>(isset($item['NewInvitado']))?$item['NewInvitado'] :null,
                'FechaSync'=> time::now()->toDateTimeString(),
                'isSync'=>0
                ];
                array_push($ArrayDatos_Regreso,$temp);
                continue;
            }
            
            $invitado->isSync = 1;
            $invitado->FechaSync = time::now()->toDateTimeString();
            $invitado->DeviceSync = $parameters['DeviceID'];
            $invitado->FechaHoraAcceso = $item['FechaHoraAcceso'];
            $invitado->NewInvitado = (isset($item['NewInvitado']))?$item['NewInvitado'] :null;
            $invitado->update();

            $temp = [
                'Folio'=> $item['Folio'],
                'CodigoBarras'=>$item['CodigoBarras'],
                'FechaHoraAcceso'=>$item['FechaHoraAcceso'],
                'NewInvitado'=>(isset($item['NewInvitado']))?$item['NewInvitado'] :null,
                'FechaSync'=> time::now()->toDateTimeString(),
                'isSync'=>1
            ];
            array_push($ArrayDatos_Regreso,$temp);

        }

        if(!$flag_error){
            return ['success'=>true,'results'=>true,
            'data'=>$ArrayDatos_Regreso,'DeviceID'=>$parameters['DeviceID'],'message'=>'¡Actualización Exitosa!'];
        }else{
            return ['success'=>true,'results'=>true,
            'data'=>$ArrayDatos_Regreso,'DeviceID'=>$parameters['DeviceID'],'message'=>'¡Actualización incompleta, no todos los elementos pudieron sincronizarce!'];
        }
        

        }
        
        catch(QueryException $e){
            $errors = [
                "Clave"=>"02"
            ];
            $response = ['success'=>true,'results'=>false, 
            'total'=>0,'filtros'=>'Sin filtros',
            'errors'=>$e->getMessage(), 'message' =>'¡Algo salió mal en la sincronizacion de los datos!'];

            return  response()->json($response, 200);
        }
    }
}
