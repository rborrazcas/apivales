<?php

namespace App\Http\Controllers\ControllersPulseras;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use \Illuminate\Database\QueryException;
use Illuminate\Contracts\Validation\ValidationException;
use App\ModelsPulseras\{Invitado};
use App\ModelsPulseras\{CodigoBarra};
use App\VNegociosFiltros;
use Validator;
use JWTAuth;
use DB;
use Carbon\Carbon as time;
class InvitadoController extends Controller
{
    
    function getListadoInvitados(Request $request){
        try {
            //$user = auth()->user();
            $v = Validator::make($request->all(), [
                'filtered'=> 'array',
                'sorted'=> 'array',
                'page'=> 'required',
                'pageSize'=> 'required',
                'tipo'=> 'required',
                'excluir_asignados'=> 'required'
            ]);
            if ($v->fails()){
                $response =  ['success'=>false,'results'=>false,
                'errors'=>$v->errors(),'data'=>[]];
                return response()->json($response,200);
            }
            $parameters = $request->all();
            $parameters_serializado = serialize($parameters);
            $user = auth()->user();
            $filtro_usuario=VNegociosFiltros::where('idUser','=',$user->id)->where('api','=','getListadoInvitados')->first();
            
            if($filtro_usuario){
                $filtro_usuario->parameters=$parameters_serializado;
                $filtro_usuario->updated_at=time::now();
                $filtro_usuario->update();
            }
            else{
                $objeto_nuevo = new VNegociosFiltros;
                $objeto_nuevo->api="getListadoInvitados";
                $objeto_nuevo->idUser=$user->id;
                $objeto_nuevo->parameters=$parameters_serializado;
                $objeto_nuevo->save();
            }
            
            

            $res = DB::table('bravos_invitados')
            ->select(
                'bravos_invitados.Folio',
                'bravos_invitados.CodigoBarras',
                DB::raw('UPPER(bravos_invitados.Responsable) as Responsable'),
                'bravos_invitados.NumeroInvitado',
                DB::raw('UPPER(bravos_invitados.Nombres) as Nombres'),
                DB::raw('UPPER(bravos_invitados.Materno) as Materno'),
                DB::raw('UPPER(bravos_invitados.Paterno) as Paterno'),
                DB::raw('UPPER(CONCAT_WS(" ",bravos_invitados.Nombres,bravos_invitados.Paterno,bravos_invitados.Materno))as NombreCompleto'),
                DB::raw('UPPER(bravos_invitados.CURP) as CURP'),
                'bravos_invitados.Celular',
                'bravos_invitados.NumeroBurbuja',
                'bravos_invitados.created_at',
                'bravos_invitados.updated_at',
                'bravos_invitados.UserCreated',
                'bravos_invitados.UserOwned',
                'bravos_invitados.UserUpdated',
                'bravos_invitados.Municipio'
            );
            $res_1 = DB::table('bravos_invitados')
            ->select(
                'bravos_invitados.Folio',
                'bravos_invitados.CodigoBarras',
                DB::raw('UPPER(bravos_invitados.Responsable) as Responsable'),
                'bravos_invitados.NumeroInvitado',
                DB::raw('UPPER(bravos_invitados.Nombres) as Nombres'),
                DB::raw('UPPER(bravos_invitados.Materno) as Materno'),
                DB::raw('UPPER(bravos_invitados.Paterno) as Paterno'),
                DB::raw('UPPER(CONCAT_WS(" ",bravos_invitados.Nombres,bravos_invitados.Paterno,bravos_invitados.Materno))as NombreCompleto'),
                DB::raw('UPPER(bravos_invitados.CURP) as CURP'),
                'bravos_invitados.Celular',
                'bravos_invitados.NumeroBurbuja',
                'bravos_invitados.created_at',
                'bravos_invitados.updated_at',
                'bravos_invitados.UserCreated',
                'bravos_invitados.UserOwned',
                'bravos_invitados.UserUpdated',
                'bravos_invitados.Municipio'
            );

            

            if(isset($parameters['excluir_asignados'])){
                if($parameters['excluir_asignados']==true){
                    $res->whereNull('CodigoBarras');
                }
            }

            if(isset($parameters['NombreCompleto'])){
                $filtro_recibido = $parameters['NombreCompleto'];
                $filtro_recibido = str_replace(" ","%",$filtro_recibido);
                $res->where(
                    DB::raw("
                    CONCAT_WS(' ',
                        bravos_invitados.Nombres,
                        bravos_invitados.Paterno,
                        bravos_invitados.Materno,
                        bravos_invitados.Paterno,
                        bravos_invitados.Nombres,
                        bravos_invitados.Materno,
                        bravos_invitados.Materno,
                        bravos_invitados.Nombres,
                        bravos_invitados.Paterno,
                        bravos_invitados.Nombres,
                        bravos_invitados.Materno,
                        bravos_invitados.Paterno,
                        bravos_invitados.Paterno,
                        bravos_invitados.Materno,
                        bravos_invitados.Nombres,
                        bravos_invitados.Materno,
                        bravos_invitados.Paterno,
                        bravos_invitados.Nombres
                    )")
            
                    ,'like',"%".$filtro_recibido."%");

                $res_1->where(
                    DB::raw("
                    CONCAT_WS(' ',
                        bravos_invitados.Nombres,
                        bravos_invitados.Paterno,
                        bravos_invitados.Materno,
                        bravos_invitados.Paterno,
                        bravos_invitados.Nombres,
                        bravos_invitados.Materno,
                        bravos_invitados.Materno,
                        bravos_invitados.Nombres,
                        bravos_invitados.Paterno,
                        bravos_invitados.Nombres,
                        bravos_invitados.Materno,
                        bravos_invitados.Paterno,
                        bravos_invitados.Paterno,
                        bravos_invitados.Materno,
                        bravos_invitados.Nombres,
                        bravos_invitados.Materno,
                        bravos_invitados.Paterno,
                        bravos_invitados.Nombres
                    )")
            
                    ,'like',"%".$filtro_recibido."%");
                
            }

            $flag = 0;
            if(isset($parameters['filtered'])){

                for($i=0;$i<count($parameters['filtered']);$i++){

                    if($flag==0){
                        if($parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'id') !== false
                        || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'Folio') !== false
                        || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'NumeroInvitado') !== false
                        || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'NumeroBurbuja') !== false
                        || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'User') !== false
                        ){
                            if(is_array ($parameters['filtered'][$i]['value'])){
                                $res->whereIn($parameters['filtered'][$i]['id'],$parameters['filtered'][$i]['value']);
                                $res_1->whereIn($parameters['filtered'][$i]['id'],$parameters['filtered'][$i]['value']);
                            }else{
                                $res->where($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
                                $res_1->where($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
                            }
                            
                        }else{
                                if(strpos($parameters['filtered'][$i]['id'], 'is') !== false){
                                    $res->where($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
                                    $res_1->where($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
                                }else{
                                    $res->where($parameters['filtered'][$i]['id'],'LIKE','%'.$parameters['filtered'][$i]['value'].'%');
                                    $res_1->where($parameters['filtered'][$i]['id'],'LIKE','%'.$parameters['filtered'][$i]['value'].'%');
                                }
                        }
                        $flag = 1;
                    }
                    else{
                        if($parameters['tipo']=='and'){
                            if($parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'id') !== false
                            || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'Folio') !== false
                            || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'NumeroInvitado') !== false
                            || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'NumeroBurbuja') !== false
                            || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'User') !== false
                            ){
                                if(is_array($parameters['filtered'][$i]['value'])){
                                    $res->whereIn($parameters['filtered'][$i]['id'],$parameters['filtered'][$i]['value']);
                                    $res_1->whereIn($parameters['filtered'][$i]['id'],$parameters['filtered'][$i]['value']);
                                }else{
                                    $res->where($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
                                    $res_1->where($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
                                }
                            }else{
                                    if(strpos($parameters['filtered'][$i]['id'], 'is') !== false){
                                        $res->where($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
                                        $res_1->where($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
                                    }else{
                                        $res->where($parameters['filtered'][$i]['id'],'LIKE','%'.$parameters['filtered'][$i]['value'].'%');
                                        $res_1->where($parameters['filtered'][$i]['id'],'LIKE','%'.$parameters['filtered'][$i]['value'].'%');
                                    }
                            } 
                        }
                        else{
                            if($parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'id') !== false
                            || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'Folio') !== false
                            || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'NumeroInvitado') !== false
                            || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'NumeroBurbuja') !== false
                            || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'User') !== false
                            ){
                                if(is_array ($parameters['filtered'][$i]['value'])){
                                    $res->orWhereIn($parameters['filtered'][$i]['id'],$parameters['filtered'][$i]['value']);
                                    $res_1->orWhereIn($parameters['filtered'][$i]['id'],$parameters['filtered'][$i]['value']);
                                }else{
                                    $res->orWhere($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
                                    $res_1->orWhere($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
                                }
                            }else{
                                    if(strpos($parameters['filtered'][$i]['id'], 'is') !== false){
                                        $res->orWhere($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
                                        $res_1->orWhere($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
                                    }else{
                                    $res->orWhere($parameters['filtered'][$i]['id'],'LIKE','%'.$parameters['filtered'][$i]['value'].'%');
                                    $res_1->orWhere($parameters['filtered'][$i]['id'],'LIKE','%'.$parameters['filtered'][$i]['value'].'%');
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
                        $res_1->orderBy($parameters['sorted'][$i]['id'],'desc');
                    }
                    else{
                        $res->orderBy($parameters['sorted'][$i]['id'],'asc');
                        $res_1->orderBy($parameters['sorted'][$i]['id'],'asc');
                    }
                }
            }

            $total = $res->count(); 
            $res = $res->offset($startIndex)
            ->take($pageSize)
            ->get();

            $total_1 = $res_1->count(); 
            $res_1 = $res_1->offset($startIndex)
            ->take($pageSize)
            ->get();

            $total_2= $total_1 - $total;

            /* if($total === 0){
                return ['success'=>true,'results'=>false,
                'total'=>$total,'filtros'=>$parameters['filtered'],'data'=>[]];
            } */
            
            return ['success'=>true,'results'=>true,
             'total'=>$total_1,'pendientes'=>$total, 'realizados'=>$total_2,'filtros'=>$parameters['filtered'],'data'=>$res];
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
    function getResponsables(Request $request){
        try {
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

            
            

            $res = DB::table('bravos_invitados')
            ->select(
                DB::raw('UPPER(bravos_invitados.Responsable) as Responsable'),
                'bravos_invitados.Municipio'
            )
            ->groupBy('Responsable')
            ->groupBy('Municipio');

            /* if(isset($parameters['excluir_asignados'])){
                if($parameters['excluir_asignados']==true){
                    $res->whereNotNull('CodigoBarras');
                }
            } */

            $flag = 0;
            if(isset($parameters['filtered'])){

                for($i=0;$i<count($parameters['filtered']);$i++){

                    if($flag==0){
                        if($parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'id') !== false
                        || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'Folio') !== false
                        || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'NumeroInvitado') !== false
                        || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'NumeroBurbuja') !== false
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
                            || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'NumeroInvitado') !== false
                            || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'NumeroBurbuja') !== false
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
                            || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'NumeroInvitado') !== false
                            || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'NumeroBurbuja') !== false
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

            /* $array_res = [];
            $temp = [];
            foreach($res as $data){
                $temp = [
                    'id'=> $data->id,
                    'NombrePropone'=> $data->NombrePropone,
                    'Cargo'=> $data->Cargo,
                    'created_at'=> $data->created_at,
                    'updated_at'=> $data->updated_at,
                    'UserCreated' => [
                        'id'=> $data->idE,
                        'email'=> $data->emailE,
                        'Nombre'=> $data->userCreated
                    ],
                    'UserUpdated' => [
                        'id'=> $data->idF,
                        'email'=> $data->emailF,
                        'Nombre'=> $data->userUpdated
                    ]
                ];
                
                array_push($array_res,$temp);
            } */
            
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
    function setInvitado(Request $request){
        try {
        $v = Validator::make($request->all(), [
            //Folio
            //CodigoBarras
            'Responsable' => 'required',
            'NumeroInvitado' => 'required | numeric',
            'Municipio' => 'required',
            'Nombres' => 'required',
            'Materno' => 'required',
            'Paterno' => 'required',
            'CURP' => 'required | unique:bravos_invitados',
            'Celular' => 'required',
            'NumeroBurbuja' => 'required | numeric'
            
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
        $parameters['UserOwned'] = $user->id;
        $invitado_ = Invitado::create($parameters);
        $invitado = DB::table('bravos_invitados')
        ->select(
            'bravos_invitados.*'
            )
        ->where('bravos_invitados.Folio','=',$invitado_->Folio)->first();
        return ['success'=>true,'results'=>true,
            'data'=>$invitado];
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
    function updateInvitado(Request $request){
        try {
        $v = Validator::make($request->all(), [
            'Folio' => 'required | numeric'
            
        ]);
        

        if ($v->fails()){
            $response =  ['success'=>false,'results'=>false,
            'errors'=>$v->errors(),'data'=>[]];

            return response()->json($response,200);
        }

        $parameters = $request->all();
        $user = auth()->user();
        $parameters['UserUpdated'] = $user->id;
        $invitado_ = Invitado::where('Folio',$parameters['Folio'])->first();
		if(!$invitado_){
            $response =  ['success'=>true,'results'=>false,
            'errors'=>["Folio"=>["El folio no existe"]],
			'message'=>'El invitado que desea actualizar no existe.'];

			return response()->json($response,200);

		}
		$user_loggeado = auth()->user();
		$parameters["UserUpdated"]= $user_loggeado->id;
		$invitado_->update($parameters);


        $invitado = DB::table('bravos_invitados')
        ->select(
            'bravos_invitados.*'
            )
        ->where('bravos_invitados.Folio','=',$invitado_->Folio)->first();
        return ['success'=>true,'results'=>true,
            'data'=>$invitado];
        } 
        catch(QueryException $e){
            $errors = [
                "Clave"=>"02"
            ];
            $response = ['success'=>true,'results'=>false, 
            'total'=>0,'filtros'=>$parameters['filtered'],
            'errors'=>$e->getMessage(), 'message' =>'¡Algo salió mal en la edición del registro!'];

            return  response()->json($response, 200);
        }
    }
    function setAsignarCodigoBarrasInvitado(Request $request){
        try {
        $v = Validator::make($request->all(), [
            'Folio' => 'required | numeric',
            'CodigoBarras' => 'required'
            
        ]);
        

        if ($v->fails()){
            $response =  ['success'=>false,'results'=>false,
            'errors'=>$v->errors(),'data'=>[]];

            return response()->json($response,200);
        }

        $parameters = $request->all();
        $user = auth()->user();
        $parameters['UserUpdated'] = $user->id;
        $invitado_ = Invitado::where('Folio',$parameters['Folio'])->first();
		if(!$invitado_){
            $response =  ['success'=>true,'results'=>false,
            'errors'=>["Folio"=>["El folio no existe"]],
			'message'=>'El invitado que desea asignar no existe.'];

			return response()->json($response,200);

        }
        $codigo_ = CodigoBarra::where('CodigoBarras',$parameters['CodigoBarras'])->first();
		if(!$codigo_){
            $response =  ['success'=>true,'results'=>false,
            'errors'=>["CodigoBarras"=>["El Codigo de Barras no existe"]],
			'message'=>'El Codigo de Barras que desea asignar no existe.'];

			return response()->json($response,200);

        }
        $invitado_asignado = Invitado::where('CodigoBarras',$codigo_->CodigoBarras)->first();
		if($invitado_asignado){
            $response =  ['success'=>true,'results'=>false,
            'errors'=>["CodigoBarras"=>["CodigoBarras asignado a otro invitado."]],
			'message'=>'El código de barras que desea asignar ya se encuentra asignado.'];

			return response()->json($response,200);

        }
        
        $invitado_asignado_verificacion = Invitado::where('Folio',$parameters['Folio'])->whereNotNull('CodigoBarras')->first();
        
		if($invitado_asignado_verificacion){
            $response =  ['success'=>true,'results'=>false,
            'errors'=>["Folio"=>["El folio ya tiene asignado un código de barras"]],
			'message'=>'El invitado que desea asignar ya cuenta con otro código de barras asignado.'];

			return response()->json($response,200);

        }

        /* $invitado_asignado_verificacion = Invitado::where('Folio',$parameters['Folio'])->whereNotNull('CodigoBarras')->first();
        
		if($invitado_asignado_verificacion){
            $response =  ['success'=>true,'results'=>false,
            'errors'=>["Folio"=>["El folio ya tiene asignado un código de barras"]],
			'message'=>'El invitado que desea asignar ya cuenta con otro código de barras asignado.'];

			return response()->json($response,200);

        } */

		$user_loggeado = auth()->user();
        $parameters["UserUpdated"]= $user_loggeado->id;
        $parameters["CodigoBarras"]= $codigo_->CodigoBarras;
        $invitado_->update($parameters);
        
        $parameters["UserUpdated"]= $user_loggeado->id;
        $parameters["Folio"]= $parameters['Folio'];
		$codigo_->update($parameters);


        $invitado = DB::table('bravos_invitados')
        ->select(
            'bravos_invitados.*'
            )
        ->where('bravos_invitados.Folio','=',$invitado_->Folio)->first();
        return ['success'=>true,'results'=>true,
            'data'=>$invitado];
        } 
        catch(QueryException $e){
            $errors = [
                "Clave"=>"02"
            ];
            $response = ['success'=>true,'results'=>false, 
            'total'=>0,'filtros'=>$parameters['filtered'],
            'errors'=>$e->getMessage(), 'message' =>'¡Algo salió mal en la asignacion del registro!'];

            return  response()->json($response, 200);
        }
    }
    function getListadoResponsables(Request $request){
        try {
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
           
            //__________________________________________________________
            $res_2 = DB::table('bravos_invitados as BI2')
            ->select(
                'BI2.Responsable as ResponsableBI',
                'BI2.Municipio as MunicipioBI',
                DB::raw('0 as InvitadosFaltantes'),
                DB::raw('count(BI2.Folio) as InvitadosRealizados')
                
            )
            ->whereNotNull('BI2.CodigoBarras')
            ->groupBy('BI2.Responsable')
            ->groupBy('BI2.Municipio')
            ->toSql();
            //__________________________________________________________
            $res = DB::table('bravos_invitados')
            ->select(
                
                'bravos_invitados.Responsable',
                'bravos_invitados.Municipio',
                DB::raw('count(distinct(bravos_invitados.Folio)) as TotalInvitados'),
                DB::raw('case when BI2.InvitadosRealizados is null then 0 else BI2.InvitadosRealizados end as InvitadosRealizados'),
                DB::raw('case when BI2.InvitadosRealizados is null then count(distinct(bravos_invitados.Folio)) else (count(distinct(bravos_invitados.Folio))-BI2.InvitadosRealizados) end as InvitadosFaltantes')

            )
            ->leftJoin(DB::raw('('.$res_2.') BI2') , function ($join) {
                $join->on('bravos_invitados.Responsable', '=', 'BI2.ResponsableBI');
                $join->on('bravos_invitados.Municipio', '=', 'BI2.MunicipioBI');
            })
            
            ->groupBy('Responsable')
            ->groupBy('Municipio')
            ->orderBy('Responsable')
            ->orderBy('Municipio')
            ->orderBy('updated_at');

            

            $flag = 0;
            if(isset($parameters['filtered'])){

                for($i=0;$i<count($parameters['filtered']);$i++){

                    if($flag==0){
                        if($parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'id') !== false
                        || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'Folio') !== false
                        || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'NumeroInvitado') !== false
                        || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'NumeroBurbuja') !== false
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
                            || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'NumeroInvitado') !== false
                            || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'NumeroBurbuja') !== false
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
                            || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'NumeroInvitado') !== false
                            || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'NumeroBurbuja') !== false
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

            $res_aux = $res;
            $total_aux = count($res_aux->get());
            
            $res = $res->offset($startIndex)
            ->take($pageSize)
            ->get();
            $total = count($res);

            $bandera_nuevo_total = false;
            $nuevo_resultado = $res_aux->get();
            if(isset($parameters['Total']) && $parameters['Total'] != "") {
                $parametro = $parameters['Total'];
                $resultados = collect($res);
                $nuevo_res = $resultados->filter(function ($value, $key) use ($parametro) {
                    return data_get($value, 'TotalInvitados') == $parametro;
                });
                $nuevo_res = $nuevo_res->all();
                $nuevo_resultado = [];
                foreach ($nuevo_res as $value) {
                    
                    array_push($nuevo_resultado,$value);
                }
                $bandera_nuevo_total = true;
            }
            if(isset($parameters['Faltantes']) && $parameters['Faltantes'] != "") {
                $parametro = $parameters['Faltantes'];
                $resultados = collect($res);
                $nuevo_res = $resultados->filter(function ($value, $key) use ($parametro) {
                    return data_get($value, 'InvitadosFaltantes') == $parametro;
                });
                $nuevo_res = $nuevo_res->all();
                $nuevo_resultado = [];
                foreach ($nuevo_res as $value) {
                    
                    array_push($nuevo_resultado,$value);
                }
                $bandera_nuevo_total = true;
            }
            if(isset($parameters['Realizados']) && $parameters['Realizados'] != "") {
                $parametro = $parameters['Realizados'];
                $resultados = collect($res);
                $nuevo_res = $resultados->filter(function ($value, $key) use ($parametro) {
                    return data_get($value, 'InvitadosRealizados') == $parametro;
                });
                $nuevo_res = $nuevo_res->all();
                $nuevo_resultado = [];
                foreach ($nuevo_res as $value) {
                    
                    array_push($nuevo_resultado,$value);
                }
                $bandera_nuevo_total = true;
            }
            if($bandera_nuevo_total == true){
                $total_aux = count($nuevo_resultado);
            }
            

            
            
            
            return ['success'=>true,'results'=>true,
             'total'=>$total_aux,'filtros'=>$parameters['filtered'],'data'=>$nuevo_resultado];
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
    function getListadoInvitadosMovil(Request $request){
        try {
            //$user = auth()->user();
            $v = Validator::make($request->all(), [
                'idUser'=> 'required | numeric',
                'filtered'=> 'array',
                'sorted'=> 'array',
                'page'=> 'required',
                'pageSize'=> 'required',
                'tipo'=> 'required',
                'excluir_asignados'=> 'required'
            ]);
            if ($v->fails()){
                $response =  ['success'=>false,'results'=>false,
                'errors'=>$v->errors(),'data'=>[]];
                return response()->json($response,200);
            }
            $parameters = $request->all();
            $parameters_serializado = serialize($parameters);
            //
            $user = DB::table('users')->where('id',$parameters['idUser'])->first(); //auth()->user();
            if(!$user){
                $response =  ['success'=>true,'results'=>false,
                'errors'=>["idUser"=>["El usuario no existe."]],
                'message'=>'El usuario que hizo la petición no existe.'];

                return response()->json($response,200);
            }

            $filtro_usuario=VNegociosFiltros::where('idUser','=',$user->id)->where('api','=','getListadoInvitados')->first();
            
            if($filtro_usuario){
                $filtro_usuario->parameters=$parameters_serializado;
                $filtro_usuario->updated_at=time::now();
                $filtro_usuario->update();
            }
            else{
                $objeto_nuevo = new VNegociosFiltros;
                $objeto_nuevo->api="getListadoInvitados";
                $objeto_nuevo->idUser=$user->id;
                $objeto_nuevo->parameters=$parameters_serializado;
                $objeto_nuevo->save();
            }
            

            $res = DB::table('bravos_invitados')
            ->select(
                'bravos_invitados.Folio',
                'bravos_invitados.CodigoBarras',
                DB::raw('UPPER(bravos_invitados.Responsable) as Responsable'),
                'bravos_invitados.NumeroInvitado',
                DB::raw('UPPER(bravos_invitados.Nombres) as Nombres'),
                DB::raw('UPPER(bravos_invitados.Materno) as Materno'),
                DB::raw('UPPER(bravos_invitados.Paterno) as Paterno'),
                DB::raw('UPPER(CONCAT_WS(" ",bravos_invitados.Nombres,bravos_invitados.Paterno,bravos_invitados.Materno))as NombreCompleto'),
                DB::raw('UPPER(bravos_invitados.CURP) as CURP'),
                'bravos_invitados.Celular',
                'bravos_invitados.NumeroBurbuja',
                'bravos_invitados.created_at',
                'bravos_invitados.updated_at',
                'bravos_invitados.UserCreated',
                'bravos_invitados.UserOwned',
                'bravos_invitados.UserUpdated',
                'bravos_invitados.Municipio'

            );

            if(isset($parameters['excluir_asignados'])){
                if($parameters['excluir_asignados']==true){
                    $res->whereNull('CodigoBarras');
                }
            }

            if(isset($parameters['NombreCompleto'])){
                $filtro_recibido = $parameters['NombreCompleto'];
                $filtro_recibido = str_replace(" ","",$filtro_recibido);
                $res->where(
                    DB::raw("
                    REPLACE(
                    CONCAT(
                        bravos_invitados.Nombres,
                        bravos_invitados.Paterno,
                        bravos_invitados.Materno,
                        bravos_invitados.Paterno,
                        bravos_invitados.Nombres,
                        bravos_invitados.Materno,
                        bravos_invitados.Materno,
                        bravos_invitados.Nombres,
                        bravos_invitados.Paterno,
                        bravos_invitados.Nombres,
                        bravos_invitados.Materno,
                        bravos_invitados.Paterno,
                        bravos_invitados.Paterno,
                        bravos_invitados.Materno,
                        bravos_invitados.Nombres,
                        bravos_invitados.Materno,
                        bravos_invitados.Paterno,
                        bravos_invitados.Nombres
                    ), ' ', '')")
            
                    ,'like',"%".$filtro_recibido."%");
                
            }
            $flag = 0;
            if(isset($parameters['filtered'])){

                for($i=0;$i<count($parameters['filtered']);$i++){

                    if($flag==0){
                        if($parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'id') !== false
                        || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'Folio') !== false
                        || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'NumeroInvitado') !== false
                        || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'NumeroBurbuja') !== false
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
                            || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'NumeroInvitado') !== false
                            || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'NumeroBurbuja') !== false
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
                            || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'NumeroInvitado') !== false
                            || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'NumeroBurbuja') !== false
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

            /* $array_res = [];
            $temp = [];
            foreach($res as $data){
                $temp = [
                    'id'=> $data->id,
                    'NombrePropone'=> $data->NombrePropone,
                    'Cargo'=> $data->Cargo,
                    'created_at'=> $data->created_at,
                    'updated_at'=> $data->updated_at,
                    'UserCreated' => [
                        'id'=> $data->idE,
                        'email'=> $data->emailE,
                        'Nombre'=> $data->userCreated
                    ],
                    'UserUpdated' => [
                        'id'=> $data->idF,
                        'email'=> $data->emailF,
                        'Nombre'=> $data->userUpdated
                    ]
                ];
                
                array_push($array_res,$temp);
            } */
            
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
    function getResponsablesMovil(Request $request){
        try {
            //$user = auth()->user();
            $v = Validator::make($request->all(), [
                'idUser'=> 'required | numeric',
                'filtered'=> 'array',
                'sorted'=> 'array',
                'page'=> 'required',
                'pageSize'=> 'required',
                'tipo'=> 'required'//,
                //'excluir_asignados'=> 'required'
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
            

            $res = DB::table('bravos_invitados')
            ->select(
                DB::raw('UPPER(bravos_invitados.Responsable) as Responsable'),
                'bravos_invitados.Municipio'

            )
            ->groupBy('Responsable');

            $flag = 0;
            if(isset($parameters['filtered'])){

                for($i=0;$i<count($parameters['filtered']);$i++){

                    if($flag==0){
                        if($parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'id') !== false
                        || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'Folio') !== false
                        || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'NumeroInvitado') !== false
                        || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'NumeroBurbuja') !== false
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
                            || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'NumeroInvitado') !== false
                            || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'NumeroBurbuja') !== false
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
                            || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'NumeroInvitado') !== false
                            || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'NumeroBurbuja') !== false
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

            /* $array_res = [];
            $temp = [];
            foreach($res as $data){
                $temp = [
                    'id'=> $data->id,
                    'NombrePropone'=> $data->NombrePropone,
                    'Cargo'=> $data->Cargo,
                    'created_at'=> $data->created_at,
                    'updated_at'=> $data->updated_at,
                    'UserCreated' => [
                        'id'=> $data->idE,
                        'email'=> $data->emailE,
                        'Nombre'=> $data->userCreated
                    ],
                    'UserUpdated' => [
                        'id'=> $data->idF,
                        'email'=> $data->emailF,
                        'Nombre'=> $data->userUpdated
                    ]
                ];
                
                array_push($array_res,$temp);
            } */
            
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
    function setInvitadoMovil(Request $request){
        try {
        $v = Validator::make($request->all(), [
            //Folio
            //CodigoBarras
            'idUser'=> 'required | numeric',
            'Responsable' => 'required',
            'NumeroInvitado' => 'required | numeric',
            'Municipio' => 'required',
            'Nombres' => 'required',
            'Materno' => 'required',
            'Paterno' => 'required',
            'CURP' => 'required | unique:bravos_invitados',
            'Celular' => 'required',
            'NumeroBurbuja' => 'required | numeric'
            
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

        $parameters['UserCreated'] = $user->id;
        $parameters['UserUpdated'] = $user->id;
        $parameters['UserOwned'] = $user->id;
        $invitado_ = Invitado::create($parameters);
        $invitado = DB::table('bravos_invitados')
        ->select(
            'bravos_invitados.*'
            )
        ->where('bravos_invitados.Folio','=',$invitado_->Folio)->first();
        return ['success'=>true,'results'=>true,
            'data'=>$invitado];
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
    function updateInvitadoMovil(Request $request){
        try {
        $v = Validator::make($request->all(), [
            'idUser'=> 'required | numeric',
            'Folio' => 'required | numeric'
            
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
        $parameters['UserUpdated'] = $user->id;
        $invitado_ = Invitado::where('Folio',$parameters['Folio'])->first();
		if(!$invitado_){
            $response =  ['success'=>true,'results'=>false,
            'errors'=>["Folio"=>["El folio no existe"]],
			'message'=>'El invitado que desea actualizar no existe.'];

			return response()->json($response,200);

		}
		$user_loggeado = auth()->user();
		$parameters["UserUpdated"]= $user_loggeado->id;
		$invitado_->update($parameters);


        $invitado = DB::table('bravos_invitados')
        ->select(
            'bravos_invitados.*'
            )
        ->where('bravos_invitados.Folio','=',$invitado_->Folio)->first();
        return ['success'=>true,'results'=>true,
            'data'=>$invitado];
        } 
        catch(QueryException $e){
            $errors = [
                "Clave"=>"02"
            ];
            $response = ['success'=>true,'results'=>false, 
            'total'=>0,'filtros'=>$parameters['filtered'],
            'errors'=>$e->getMessage(), 'message' =>'¡Algo salió mal en la edición del registro!'];

            return  response()->json($response, 200);
        }
    }
    function setAsignarCodigoBarrasInvitadoMovil(Request $request){
        try {
        $v = Validator::make($request->all(), [
            'idUser'=> 'required | numeric',
            'Folio' => 'required | numeric',
            'CodigoBarras' => 'required'
            
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
        $parameters['UserUpdated'] = $user->id;
        $invitado_ = Invitado::where('Folio',$parameters['Folio'])->first();
		if(!$invitado_){
            $response =  ['success'=>true,'results'=>false,
            'errors'=>["Folio"=>["El folio no existe"]],
			'message'=>'El invitado que desea asignar no existe.'];

			return response()->json($response,200);

        }
        $codigo_ = CodigoBarra::where('CodigoBarras',$parameters['CodigoBarras'])->first();
		if(!$codigo_){
            $response =  ['success'=>true,'results'=>false,
            'errors'=>["CodigoBarras"=>["El Codigo de Barras no existe"]],
			'message'=>'El Codigo de Barras que desea asignar no existe.'];

			return response()->json($response,200);

        }
        $invitado_asignado = Invitado::where('CodigoBarras',$codigo_->CodigoBarras)->first();
		if($invitado_asignado){
            $response =  ['success'=>true,'results'=>false,
            'errors'=>["CodigoBarras"=>["CodigoBarras asignado a otro invitado."]],
			'message'=>'El código de barras que desea asignar ya se encuentra asignado.'];

			return response()->json($response,200);

        }
        $invitado_asignado_verificacion = Invitado::where('Folio',$parameters['Folio'])->whereNotNull('CodigoBarras')->first();
        
		if($invitado_asignado_verificacion){
            $response =  ['success'=>true,'results'=>false,
            'errors'=>["Folio"=>["El folio ya tiene asignado un código de barras"]],
			'message'=>'El invitado que desea asignar ya cuenta con otro código de barras asignado.'];

			return response()->json($response,200);

        }

		$user_loggeado = auth()->user();
        $parameters["UserUpdated"]= $user_loggeado->id;
        $parameters["CodigoBarras"]= $codigo_->CodigoBarras;
        $invitado_->update($parameters);
        
        $parameters["UserUpdated"]= $user_loggeado->id;
        $parameters["Folio"]= $parameters['Folio'];
		$codigo_->update($parameters);


        $invitado = DB::table('bravos_invitados')
        ->select(
            'bravos_invitados.*'
            )
        ->where('bravos_invitados.Folio','=',$invitado_->Folio)->first();
        return ['success'=>true,'results'=>true,
            'data'=>$invitado];
        } 
        catch(QueryException $e){
            $errors = [
                "Clave"=>"02"
            ];
            $response = ['success'=>true,'results'=>false, 
            'total'=>0,'filtros'=>$parameters['filtered'],
            'errors'=>$e->getMessage(), 'message' =>'¡Algo salió mal en la asignacion del registro!'];

            return  response()->json($response, 200);
        }
    }
    function getListadoResponsablesMovil(Request $request){
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
            //__________________________________________________________
            $res_1 = DB::table('bravos_invitados as BI')
            ->select(
                'BI.Responsable as ResponsableBI',
                'BI.Municipio as MunicipioBI',
                DB::raw('count(BI.Folio) as InvitadosFaltantes'),
                DB::raw('0 as InvitadosRealizados')
            )
            ->whereNull('BI.CodigoBarras')
            ->groupBy('Responsable')
            ->groupBy('Municipio');
            //__________________________________________________________
            $res_2 = DB::table('bravos_invitados as BI2')
            ->select(
                'BI2.Responsable as ResponsableBI',
                'BI2.Municipio as MunicipioBI',
                DB::raw('0 as InvitadosFaltantes'),
                DB::raw('count(BI2.Folio) as InvitadosRealizados')
                
            )
            ->whereNotNull('BI2.CodigoBarras')
            ->union($res_1)
            ->groupBy('BI2.Responsable')
            ->groupBy('BI2.Municipio');
            //->distinct();
            //dd($res_2->get());






            //__________________________________________________________
            $res = DB::table('bravos_invitados')
            ->select(
                'bravos_invitados.Responsable',
                'bravos_invitados.Municipio',
                DB::raw('count(distinct(bravos_invitados.Folio)) as TotalInvitados'),
                DB::raw('BI2.InvitadosRealizados')

            )
            ->joinSub($res_2, 'BI2', function ($join) {
                $join->on('bravos_invitados.Responsable', '=', 'BI2.ResponsableBI');
            })
            ->groupBy('Responsable')
            ->groupBy('Municipio')
            ->orderBy('Responsable')
            ->orderBy('Municipio')
            ->orderBy('updated_at');

            $flag = 0;
            if(isset($parameters['filtered'])){

                for($i=0;$i<count($parameters['filtered']);$i++){

                    if($flag==0){
                        if($parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'id') !== false
                        || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'Folio') !== false
                        || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'NumeroInvitado') !== false
                        || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'NumeroBurbuja') !== false
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
                            || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'NumeroInvitado') !== false
                            || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'NumeroBurbuja') !== false
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
                            || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'NumeroInvitado') !== false
                            || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'NumeroBurbuja') !== false
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

            /* $array_res = [];
            $temp = [];
            foreach($res as $data){
                $temp = [
                    'id'=> $data->id,
                    'NombrePropone'=> $data->NombrePropone,
                    'Cargo'=> $data->Cargo,
                    'created_at'=> $data->created_at,
                    'updated_at'=> $data->updated_at,
                    'UserCreated' => [
                        'id'=> $data->idE,
                        'email'=> $data->emailE,
                        'Nombre'=> $data->userCreated
                    ],
                    'UserUpdated' => [
                        'id'=> $data->idF,
                        'email'=> $data->emailF,
                        'Nombre'=> $data->userUpdated
                    ]
                ];
                
                array_push($array_res,$temp);
            } */
            
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
}
