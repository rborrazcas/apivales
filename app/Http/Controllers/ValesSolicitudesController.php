<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use \Illuminate\Database\QueryException;
use DB;
use Illuminate\Contracts\Validation\ValidationException;
use Validator;
use App\ValesSolicitudes;

class ValesSolicitudesController extends Controller
{
    function getValesSolicitudes(Request $request){
        $parameters = $request->all();

        try {
            $res = DB::table('vales_solicitudes')
            ->select(
                
                'id', 
                'CURP', 
                'Nombre', 
                'Paterno', 
                'Materno', 
                'FolioInicial', 
                'FolioFinal', 
                'SerieInicial', 
                'SerieFinal', 
                'Remesa', 
                'Comentario', 
                'created_at', 
                'UserCreated', 
                'updated_at'
            );

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
           
            
            
            return ['success'=>true,'results'=>true,
             'total'=>$total,'filtros'=>$parameters['filtered'],'data'=>$res];

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

    function setValesSolicitudes(Request $request){

        $v = Validator::make($request->all(), [
            //'CURP' => 'required|unique:vales_solicitudes',
            'idSolicitud'=> 'required|unique:vales_solicitudes',
            'CURP' => 'required',
            'Nombre' => 'required',
            'UserOwned' => 'required',
            'Articulador' => 'required',
            'idMunicipio' => 'required',
            'Municipio' => 'required',
            // 'CodigoBarrasInicial' => 'required|unique:vales_solicitudes',
            // 'CodigoBarrasFinal' => 'required|unique:vales_solicitudes',
            // 'SerieInicial' => 'required|unique:vales_solicitudes',
            // 'SerieFinal' => 'required|unique:vales_solicitudes',
            'Remesa' => 'required'
        ]); 
        
		if ($v->fails()){
            $response =  ['success'=>false,'results'=>false,
            'errors'=>$v->errors(),'data'=>[]];

            return response()->json($response,200);
        }
       
        try{
            $config = DB::table('config')->where('id','=',1)->first();
            
            
            if($config->ValidaIntermedios == 1){
                $SerieInicial = $request["SerieInicial"];
                $SerieFinal = $request["SerieFinal"];

                $res_pendiente =DB::table('vales_solicitudes')
                ->select('*')
                ->where('Ejercicio', '=',date("Y") )
                ->where('SerieInicial', '>=',$SerieInicial )
                ->where('SerieFinal', '<=',$SerieInicial )
                // ->orWhere('SerieInicial','<=',$SerieInicial)
                // ->where('SerieFinal', '>=',$SerieInicial )
                // ->orWhere('SerieInicial','<=',$SerieFinal)
                // ->where('SerieFinal', '>=',$SerieFinal )
                ->first();

                //dd($res_pendiente);

                if($res_pendiente !== null){
                    $errors = [
                        "Clave"=>"02"
                    ];
                    $response = ['success'=>true,'results'=>false, 
                    'total'=>0,
                    'errors'=>$errors, 'message' =>'El folio final o incicial que ingreso se encuentra en el intervalo de otro registro.',
                    'data'=>$res_pendiente];
        
                    return  response()->json($response, 200);
                }

                $res_pendiente2 =DB::table('vales_solicitudes')
                ->select('*')
                ->where('Ejercicio', '=',date("Y") )
                ->where('SerieInicial', '>=',$SerieFinal )
                ->where('SerieFinal', '<=',$SerieFinal )
                // ->orWhere('SerieInicial','<=',$SerieInicial)
                // ->where('SerieFinal', '>=',$SerieInicial )
                // ->orWhere('SerieInicial','<=',$SerieFinal)
                // ->where('SerieFinal', '>=',$SerieFinal )
                ->first();

                if($res_pendiente2 !== null){
                    $errors = [
                        "Clave"=>"02"
                    ];
                    $response = ['success'=>true,'results'=>false, 
                    'total'=>0,
                    'errors'=>$errors, 'message' =>'El folio final o incicial que ingreso se encuentra en el intervalo de otro registro.',
                    'data'=>$res_pendiente];
        
                    return  response()->json($response, 200);
                }

                $res_pendiente3 =DB::table('vales_solicitudes')
                ->select('*')
                ->where('Ejercicio', '=',date("Y") )
                ->where('SerieInicial','<=',$SerieInicial)
                ->where('SerieFinal', '>=',$SerieInicial )
                // ->orWhere('SerieInicial','<=',$SerieFinal)
                // ->where('SerieFinal', '>=',$SerieFinal )
                ->first();

                if($res_pendiente3 !== null){
                    $errors = [
                        "Clave"=>"02"
                    ];
                    $response = ['success'=>true,'results'=>false, 
                    'total'=>0,
                    'errors'=>$errors, 'message' =>'El folio final o incicial que ingreso se encuentra en el intervalo de otro registro.',
                    'data'=>$res_pendiente];
        
                    return  response()->json($response, 200);
                }


            }
            $parameters = $request->all();
            $user = auth()->user();
            $parameters['UserCreated'] = $user->id;
            $parameters['Ejercicio'] = date("Y");

            


            $vale_solicitud_ = ValesSolicitudes::create($parameters);
            $vale_solicitud = ValesSolicitudes::find($vale_solicitud_->id);
            return ['success'=>true,'results'=>true,
                'data'=>$vale_solicitud];

        }
        catch(QueryException $e){
            dd($e->getMessage());
            $errors = [
                "Clave"=>"01"
            ];
            $response = ['success'=>true,'results'=>false, 
            'total'=>0,
            'errors'=>$errors, 'message' =>'Hubo un error a al crear el registro'];

            return  response()->json($response, 200);
        }
        
    }

    function actualizarTablaValesUsados(Request $request){
        $v = Validator::make($request->all(), [
            'tabla'=> 'required',
        ]); 
        
		if ($v->fails()){
            $response =  ['success'=>false,'results'=>false,
            'errors'=>$v->errors(),'data'=>[]];

            return response()->json($response,200);
        }
        $params = $request->all();
        try{
            // $tabla = $params['tabla'];
            // $valesEntregados = DB::table($params['tabla'])
            // ->select("$tabla.Farmacia", "$tabla.TipoVales", "$tabla.FolioValeGrandeza", "$tabla.Serie del Vale Grandeza",
            // "vales_series.Serie", "$tabla.Folio de Transaccion", "$tabla.Folio de operacion", "$tabla.Tipo de operacion",
            // "$tabla.Fecha de Canje", "$tabla.Clave Unica de Comercio", "$tabla.RFC de Comercio",
            // "$tabla.Nombre del Comercio", "$tabla.Titular del Comercio", "$tabla.Telefono del Comercio",
            // "$tabla.Municipio del Comercio", "$tabla.Giro de Comercio", "$tabla.Correo de Comercio", 
            // "$tabla.Tipo de Identificacion", "$tabla.Dato de Identificacion")
            // ->join("vales_series", "vales_series.CodigoBarra", "$tabla.FolioValeGrandeza")
            // ->take(10)
            // ->get();

            $valesSolicitudes = DB::table('vales_solicitudes')
            ->where('Ejercicio', 2021)
            // ->take(10)
            ->get();

            foreach($valesSolicitudes as $vale){
                DB::table('vales_series')
                ->where('Serie', '>=', $vale->SerieInicial)
                ->where('Serie', '<=', $vale->SerieFinal)
                ->where('Ejercicio', 2021)
                ->update([
                    'Remesa' => $vale->Remesa,
                    'CURP' => $vale->CURP,
                    'idSolicitud' => $vale->idSolicitud
                ]);
            }

            return ['success'=>true, 'message'=>"Actualizados con exito"];

            // dd($valesSolicitudes);
        }catch(QueryException $e){
            dd($e->getMessage());
            $errors = [
                "Clave"=>"01"
            ];
            $response = ['success'=>true,'results'=>false, 
            'total'=>0,
            'errors'=>$errors, 'message' =>'Hubo un error al actualizar la tabla'];

            return  response()->json($response, 200);
        }

    }
}