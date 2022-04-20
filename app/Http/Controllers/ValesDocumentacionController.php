<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use \Illuminate\Database\QueryException;
use DB;
use Illuminate\Contracts\Validation\ValidationException;
use Validator;
use App\VVales;
use App\ValesDocumentacion;
use App\VNegociosFiltros;
use Carbon\Carbon as time;

class ValesDocumentacionController extends Controller
{
    //
    function getValesDocumentacion(Request $request){
        $parameters = $request->all();

        try {
            $res = DB::table('vales_documentacion')
            ->select(
                'vales_documentacion.id', 
                DB::raw('LPAD(HEX(vales_documentacion.id),6,0) as ClaveUnica'),
                DB::raw('concat_ws(" ",vales_documentacion.Nombre, vales_documentacion.Paterno,vales_documentacion.Materno) as FullName'),
                'vales_documentacion.TelRecados',
                'vales_documentacion.Compania',
                'vales_documentacion.TelFijo',
                'vales_documentacion.FolioSolicitud',
                'vales_documentacion.FechaSolicitud',
                'vales_documentacion.CURP', 
                'vales_documentacion.Nombre', 
                'vales_documentacion.Paterno', 
                'vales_documentacion.Materno', 
                'vales_documentacion.Sexo', 
                'vales_documentacion.FechaNacimiento', 
                'vales_documentacion.Calle', 
                'vales_documentacion.NumExt', 
                'vales_documentacion.NumInt', 
                'vales_documentacion.Colonia', 
                'vales_documentacion.CP', 
                'vales_documentacion.idMunicipio', 
                    'et_cat_municipio.Id AS IdM',
                    'et_cat_municipio.Nombre AS Municipio',
                    'et_cat_municipio.SubRegion AS Region',
                'vales_documentacion.idLocalidad',
                    'et_cat_localidad.Id AS Clave',
                    'et_cat_localidad.Nombre AS Localidad',
                'vales_documentacion.TelFijo', 
                'vales_documentacion.TelCelular', 
                //'vales_documentacion.isEntregado',
                //'vales_documentacion.entrega_at',
                'vales_documentacion.CorreoElectronico', 
                //'vales_documentacion.FechaDocumentacion',
                //'vales_documentacion.isDocumentacionEntrega',
                //'vales_documentacion.idUserDocumentacion',
                //'vales_documentacion.isEntregadoOwner',
                //'vales_documentacion.idUserReportaEntrega',
                //'vales_documentacion.ComentarioEntrega',
                //'vales_documentacion.FechaReportaEntrega',
                'vales_documentacion.idStatusDocumentacion', 
                    'vales_status_documentacion.id as idES',
                    'vales_status_documentacion.Estatus',
                    'vales_status_documentacion.Clave as ClaveA',

                //CAMPOS ADICIONALES
                'vales_documentacion.TieneFolio',
                'vales_documentacion.TieneFechaSolicitud',
                'vales_documentacion.TieneCURPValida',
                'vales_documentacion.NombreCoincideConINE',
                'vales_documentacion.TieneDomicilio',
                'vales_documentacion.TieneArticuladorReverso',
                'vales_documentacion.FolioCoincideListado',
                'vales_documentacion.FechaSolicitudChange',
                'vales_documentacion.CURPCoincideListado',
                'vales_documentacion.NombreChanged',
                'vales_documentacion.PaternoChanged',
                'vales_documentacion.MaternoChanged',
                'vales_documentacion.CalleChanged',
                'vales_documentacion.NumExtChanged',
                'vales_documentacion.NumIntChanged',
                'vales_documentacion.ColoniaChanged',
                'vales_documentacion.idLocalidadChanged',
                'vales_documentacion.idMunicipioChanged',
                'vales_documentacion.UserOwnedchanged',
                //CAMPOS ADICIONALES

                'vales_documentacion.created_at', 
                'vales_documentacion.updated_at', 
                'vales_documentacion.UserCreated',
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
                'vales_documentacion.UserUpdated',
                //Datos Usuario updated
                    'usersB.id as idF',
                    'usersB.email as emailF',
                    'usersB.Nombre as NombreF',
                    'usersB.Paterno as PaternoF',
                    'usersB.Materno as MaternoF',
                    'usersB.idTipoUser',
                        'cat_usertipoB.id as idFA',
                        'cat_usertipoB.TipoUser as TipoUserFA',
                        'cat_usertipoB.Clave as ClaveFA',
                'vales_documentacion.UserOwned',
                //Datos Usuario owned
                    'usersC.id as idO',
                    'usersC.email as emailO',
                    'usersC.Nombre as NombreO',
                    'usersC.Paterno as PaternoO',
                    'usersC.Materno as MaternoO',
                    'usersC.idTipoUser',
                        'cat_usertipoC.id as idGO',
                        'cat_usertipoC.TipoUser as TipoUserGO',
                        'cat_usertipoC.Clave as ClaveGO'
            )
            ->leftJoin('et_cat_municipio','et_cat_municipio.Id','=','vales_documentacion.idMunicipio')
            ->leftJoin('et_cat_localidad','et_cat_localidad.Id','=','vales_documentacion.idLocalidad')
            ->leftJoin('vales_status_documentacion','vales_status_documentacion.id','=','vales_documentacion.idStatusDocumentacion')
            ->leftJoin('users','users.id','=','vales_documentacion.UserCreated')
            ->leftJoin('cat_usertipo','cat_usertipo.id','=','users.idTipoUser')
            ->leftJoin('users as usersB','usersB.id','=','vales_documentacion.UserUpdated')
            ->leftJoin('cat_usertipo as cat_usertipoB','cat_usertipoB.id','=','usersB.idTipoUser')
            ->leftJoin('users as usersC','usersC.id','=','vales_documentacion.UserOwned')
            ->leftJoin('users as usersCretaed','usersCretaed.id','=','vales_documentacion.UserCreated')
            ->leftJoin('cat_usertipo as cat_usertipoC','cat_usertipoC.id','=','usersC.idTipoUser')
            ->leftJoin('cat_cp','cat_cp.d_codigo','=','vales_documentacion.CP');

            
            if(isset($parameters['Propietario'])){
                $valor_id = $parameters['Propietario'];
                $res->where(function($q)use ($valor_id) {
                    $q->where('vales_documentacion.UserCreated', $valor_id)
                      ->orWhere('vales_documentacion.UserOwned', $valor_id);
                });
            }
            if(isset($parameters['Folio'])){
                $valor_id = $parameters['Folio'];
                $res->where(DB::raw('LPAD(HEX(vales_documentacion.id),6,0)'),'like','%'.$valor_id.'%');
            }
            if(isset($parameters['Regiones'])){
                if(is_array ($parameters['Regiones'])){
                    $res->whereIn('et_cat_municipio.SubRegion',$parameters['Regiones']);
                }else{
                    $res->where('et_cat_municipio.SubRegion','=',$parameters['Regiones']);
                }    
            }
            if(isset($parameters['UserOwned'])){
                if(is_array ($parameters['UserOwned'])){
                    $res->whereIn('vales_documentacion.UserOwned',$parameters['UserOwned']);
                }else{
                    $res->where('vales_documentacion.UserOwned','=',$parameters['UserOwned']);
                }    
            }
            
            if(isset($parameters['idMunicipio'])){
                if(is_array ($parameters['idMunicipio'])){
                    $res->whereIn('vales_documentacion.idMunicipio',$parameters['idMunicipio']);
                }else{
                    $res->where('vales_documentacion.idMunicipio','=',$parameters['idMunicipio']);
                }    
            }
            if(isset($parameters['Colonia'])){
                if(is_array ($parameters['Colonia'])){
                    $res->whereIn('cat_cp.d_asenta',$parameters['Colonia']);
                }else{
                    $res->where('cat_cp.d_asenta','=',$parameters['Colonia']);
                }    
            }
            if(isset($parameters['idStatus'])){
                if(is_array ($parameters['idStatus'])){
                    $res->whereIn('vales_status_documentacion.id',$parameters['idStatus']);
                }else{
                    $res->where('vales_status_documentacion.id','=',$parameters['idStatus']);
                } 
                
            }

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
                            if(strcmp($parameters['filtered'][$i]['id'], 'vales_documentacion.UserCreated') === 0 || strcmp($parameters['filtered'][$i]['id'], 'vales_documentacion.UserUpdated') === 0 || strcmp($parameters['filtered'][$i]['id'], 'vales_documentacion.UserOwned') === 0){
                                $res->where($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
                            }
                            else{
                                if(strpos($parameters['filtered'][$i]['id'], 'is') !== false){
                                    
                                    $res->where($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);

                                }else{
                                    $res->where($parameters['filtered'][$i]['id'],'LIKE','%'.$parameters['filtered'][$i]['value'].'%');
                                }
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
                                if(strcmp($parameters['filtered'][$i]['id'], 'vales_documentacion.UserCreated') === 0 || strcmp($parameters['filtered'][$i]['id'], 'vales_documentacion.UserUpdated') === 0 || strcmp($parameters['filtered'][$i]['id'], 'vales_documentacion.UserOwned') === 0){
                                    $res->where($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
                                }else{
                                    if(strpos($parameters['filtered'][$i]['id'], 'is') !== false){
                                        $res->where($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
    
                                    }else{
                                        $res->where($parameters['filtered'][$i]['id'],'LIKE','%'.$parameters['filtered'][$i]['value'].'%');
                                    }
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
                                if(strcmp($parameters['filtered'][$i]['id'], 'vales_documentacion.UserCreated') === 0 || strcmp($parameters['filtered'][$i]['id'], 'vales_documentacion.UserUpdated') === 0){
                                    $res->orWhere($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
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

            if(isset($parameters['NombreCompleto'])){
                $filtro_recibido = $parameters['NombreCompleto'];
                $filtro_recibido = str_replace(" ","",$filtro_recibido);
                $res->where(
                    DB::raw("
                    REPLACE(
                    CONCAT(
                        vales_documentacion.Nombre,
                        vales_documentacion.Paterno,
                        vales_documentacion.Materno,
                        vales_documentacion.Paterno,
                        vales_documentacion.Nombre,
                        vales_documentacion.Materno,
                        vales_documentacion.Materno,
                        vales_documentacion.Nombre,
                        vales_documentacion.Paterno,
                        vales_documentacion.Nombre,
                        vales_documentacion.Materno,
                        vales_documentacion.Paterno,
                        vales_documentacion.Paterno,
                        vales_documentacion.Materno,
                        vales_documentacion.Nombre,
                        vales_documentacion.Materno,
                        vales_documentacion.Paterno,
                        vales_documentacion.Nombre
                    ), ' ', '')")
            
                    ,'like',"%".$filtro_recibido."%");
                
            }

            if(isset($parameters['NombreOwner'])){
                $filtro_recibido = $parameters['NombreOwner'];
                $filtro_recibido = str_replace(" ","",$filtro_recibido);
                $res->where(
                    DB::raw("
                    REPLACE(
                    CONCAT(
                        usersC.Nombre,
                        usersC.Paterno,
                        usersC.Materno,
                        usersC.Paterno,
                        usersC.Nombre,
                        usersC.Materno,
                        usersC.Materno,
                        usersC.Nombre,
                        usersC.Paterno,
                        usersC.Nombre,
                        usersC.Materno,
                        usersC.Paterno,
                        usersC.Paterno,
                        usersC.Materno,
                        usersC.Nombre,
                        usersC.Materno,
                        usersC.Paterno,
                        usersC.Nombre
                    ), ' ', '')")
            
                    ,'like',"%".$filtro_recibido."%");
                
            }

            if(isset($parameters['NombreCreated'])){
                $filtro_recibido = $parameters['NombreCreated'];
                $filtro_recibido = str_replace(" ","",$filtro_recibido);
                $res->where(
                    DB::raw("
                    REPLACE(
                    CONCAT(
                        usersCretaed.Nombre,
                        usersCretaed.Paterno,
                        usersCretaed.Materno,
                        usersCretaed.Paterno,
                        usersCretaed.Nombre,
                        usersCretaed.Materno,
                        usersCretaed.Materno,
                        usersCretaed.Nombre,
                        usersCretaed.Paterno,
                        usersCretaed.Nombre,
                        usersCretaed.Materno,
                        usersCretaed.Paterno,
                        usersCretaed.Paterno,
                        usersCretaed.Materno,
                        usersCretaed.Nombre,
                        usersCretaed.Materno,
                        usersCretaed.Paterno,
                        usersCretaed.Nombre
                    ), ' ', '')")
            
                    ,'like',"%".$filtro_recibido."%");
                
            }

            $total = $res->count(); 
            $res = $res->offset($startIndex)
            ->take($pageSize)
            ->get();

            $parameters_serializado = serialize($parameters);
            //$array = unserialize($parameters_serializado);
            $user = auth()->user();
            $filtro_usuario=VNegociosFiltros::where('idUser','=',$user->id)->where('api','=','getVales')->first();
            if($filtro_usuario){
                $filtro_usuario->parameters=$parameters_serializado;
                $filtro_usuario->updated_at=time::now();
                $filtro_usuario->update();
            }
            else{
                $objeto_nuevo = new VNegociosFiltros;
                $objeto_nuevo->api="getVales";
                $objeto_nuevo->idUser=$user->id;
                $objeto_nuevo->parameters=$parameters_serializado;
                $objeto_nuevo->save();
            }

            
            $array_res = [];
            $temp = [];
            foreach($res as $data){
                $temp = [
                    'id'=> $data->id,
                    'ClaveUnica'=> $data->ClaveUnica,
                    'TelRecados'=> $data->TelRecados,
                    'Compania'=> $data->Compania,
                    'TelFijo'=> $data->TelFijo,
                    'FolioSolicitud'=> $data->FolioSolicitud,
                    'FechaSolicitud'=> $data->FechaSolicitud,
                    'CURP'=> $data->CURP,
                    'FullName'=>$data->FullName,
                    'Nombre'=> $data->Nombre,
                    'Paterno'=> $data->Paterno,
                    'Materno'=> $data->Materno,
                    'Sexo'=> $data->Sexo,
                    'FechaNacimiento'=> $data->FechaNacimiento,
                    'Calle'=> $data->Calle,
                    'NumExt'=> $data->NumExt,
                    'NumInt'=> $data->NumInt,
                    'Colonia'=> $data->Colonia,
                    'CP'=> $data->CP,
                    //'FechaDocumentacion'=> $data->FechaDocumentacion,
                    //'isDocumentacionEntrega'=> $data->isDocumentacionEntrega,
                    //'idUserDocumentacion'=> $data->idUserDocumentacion,
                    //'isEntregadoOwner'=> $data->isEntregadoOwner,
                    //'idUserReportaEntrega'=> $data->idUserReportaEntrega,
                    //'ComentarioEntrega'=> $data->ComentarioEntrega,
                    //'FechaReportaEntrega'=> $data->FechaReportaEntrega,

                    //CAMPOS ADICIONALES
                    'TieneFolio'=> $data->TieneFolio,
                    'TieneFechaSolicitud'=> $data->TieneFechaSolicitud,
                    'TieneCURPValida'=> $data->TieneCURPValida,
                    'NombreCoincideConINE'=> $data->NombreCoincideConINE,
                    'TieneDomicilio'=> $data->TieneDomicilio,
                    'TieneArticuladorReverso'=> $data->TieneArticuladorReverso,
                    'FolioCoincideListado'=> $data->FolioCoincideListado,
                    'FechaSolicitudChange'=> $data->FechaSolicitudChange,
                    'CURPCoincideListado'=> $data->CURPCoincideListado,
                    'NombreChanged'=> $data->NombreChanged,
                    'PaternoChanged'=> $data->PaternoChanged,
                    'MaternoChanged'=> $data->MaternoChanged,
                    'CalleChanged'=> $data->CalleChanged,
                    'NumExtChanged'=> $data->NumExtChanged,
                    'NumIntChanged'=> $data->NumIntChanged,
                    'ColoniaChanged'=> $data->ColoniaChanged,
                    'idLocalidadChanged'=> $data->idLocalidadChanged,
                    'idMunicipioChanged'=> $data->idMunicipioChanged,
                    'UserOwnedchanged'=> $data->UserOwnedchanged,
                    //CAMPOS ADICIONALES


                    'idMunicipio'=> [
                        'id'=> $data->IdM,
                        'Municipio'=> $data->Municipio,
                        'Region'=> $data->Region
                    ],
                    'idLocalidad'=> [
                        'id'=> $data->Clave,
                        'Nombre'=> $data->Localidad
                    ],
                    'TelFijo'=> $data->TelFijo,
                    'TelCelular'=> $data->TelCelular,
                    'CorreoElectronico'=> $data->CorreoElectronico,
                    'idStatusDocumentacion'=> [
                        'id'=> $data->idES,
                        'Clave'=> $data->ClaveA,
                        'Estatus'=> $data->Estatus
                    ],
                    'created_at'=> $data->created_at,
                    'updated_at'=> $data->updated_at,
                    'UserCreated' => [
                        'id'=> $data->idE,
                        'email'=> $data->emailE,
                        'Nombre'=> $data->NombreE,
                        'Paterno'=> $data->PaternoE,
                        'Materno'=> $data->MaternoE,
                        'idTipoUser' =>[
                            'id'=> $data->idEA,
                            'TipoUser'=> $data->TipoUserEA,
                            'Clave'=> $data->ClaveEA
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
                            'Clave'=> $data->ClaveFA
                        ]
                    ],
                    'UserOwned' => [
                        'id'=> $data->idO,
                        'email'=> $data->emailO,
                        'Nombre'=> $data->NombreO,
                        'Paterno'=> $data->PaternoO,
                        'Materno'=> $data->MaternoO,
                        'idTipoUser' =>[
                            'id'=> $data->idGO,
                            'TipoUser'=> $data->TipoUserGO,
                            'Clave'=> $data->ClaveGO
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
            'errors'=>$e->getMessage(), 'message' =>'Campo de consulta incorrecto'];

            return  response()->json($response, 200);
        }

    }
    function getValesDocumentacionNotIn(Request $request){
        $parameters = $request->all();

        try {
            $res_1 = DB::table('vales_documentacion')
            ->select('vales_documentacion.id')
            ->get();

            $array = [];
            foreach ($res_1 as $data) {
                array_push($array,$data->id);
            }

            $res = DB::table('vales')
            ->select(
                'vales.id', 
                DB::raw('LPAD(HEX(vales.id),6,0) as ClaveUnica'),
                DB::raw('concat_ws(" ",vales.Nombre, vales.Paterno,vales.Materno) as FullName'),
                'vales.TelRecados',
                'vales.Compania',
                'vales.TelFijo',
                'vales.FolioSolicitud',
                'vales.FechaSolicitud',
                'vales.CURP', 
                'vales.Nombre', 
                'vales.Paterno', 
                'vales.Materno', 
                'vales.Sexo', 
                'vales.FechaNacimiento', 
                'vales.Calle', 
                'vales.NumExt', 
                'vales.NumInt', 
                'vales.Colonia', 
                'vales.CP', 
                'vales.idMunicipio', 
                    'et_cat_municipio.Id AS IdM',
                    'et_cat_municipio.Nombre AS Municipio',
                    'et_cat_municipio.SubRegion AS Region',
                'vales.idLocalidad',
                    'et_cat_localidad.Id AS Clave',
                    'et_cat_localidad.Nombre AS Localidad',
                'vales.TelFijo', 
                'vales.TelCelular', 
                'vales.CorreoElectronico', 
                'vales.idStatus', 
                    'vales_status.id as idES',
                    'vales_status.Estatus',
                    'vales_status.Clave as ClaveA',
                'vales.created_at', 
                'vales.updated_at', 
                'vales.UserCreated',
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
                'vales.UserUpdated',
                //Datos Usuario updated
                    'usersB.id as idF',
                    'usersB.email as emailF',
                    'usersB.Nombre as NombreF',
                    'usersB.Paterno as PaternoF',
                    'usersB.Materno as MaternoF',
                    'usersB.idTipoUser',
                        'cat_usertipoB.id as idFA',
                        'cat_usertipoB.TipoUser as TipoUserFA',
                        'cat_usertipoB.Clave as ClaveFA',
                'vales.UserOwned',
                //Datos Usuario owned
                    'usersC.id as idO',
                    'usersC.email as emailO',
                    'usersC.Nombre as NombreO',
                    'usersC.Paterno as PaternoO',
                    'usersC.Materno as MaternoO',
                    'usersC.idTipoUser',
                        'cat_usertipoC.id as idGO',
                        'cat_usertipoC.TipoUser as TipoUserGO',
                        'cat_usertipoC.Clave as ClaveGO'
            )
            ->leftJoin('et_cat_municipio','et_cat_municipio.Id','=','vales.idMunicipio')
            ->leftJoin('et_cat_localidad','et_cat_localidad.Id','=','vales.idLocalidad')
            ->leftJoin('vales_status','vales_status.id','=','idStatus')
            ->leftJoin('users','users.id','=','vales.UserCreated')
            ->leftJoin('cat_usertipo','cat_usertipo.id','=','users.idTipoUser')
            ->leftJoin('users as usersB','usersB.id','=','vales.UserUpdated')
            ->leftJoin('cat_usertipo as cat_usertipoB','cat_usertipoB.id','=','usersB.idTipoUser')
            ->leftJoin('users as usersC','usersC.id','=','vales.UserOwned')
            ->leftJoin('users as usersCretaed','usersCretaed.id','=','vales.UserCreated')
            ->leftJoin('cat_usertipo as cat_usertipoC','cat_usertipoC.id','=','usersC.idTipoUser')
            ->where('vales.idStatus','=',5)
            ->whereNotIn('vales.id',$array);
            
            if(isset($parameters['Propietario'])){
                $valor_id = $parameters['Propietario'];
                $res->where(function($q)use ($valor_id) {
                    $q->where('vales.UserCreated', $valor_id)
                      ->orWhere('vales.UserOwned', $valor_id);
                });
            }
            if(isset($parameters['Folio'])){
                $valor_id = $parameters['Folio'];
                $res->where(DB::raw('LPAD(HEX(vales.id),6,0)'),'like','%'.$valor_id.'%');
            }
            if(isset($parameters['Regiones'])){
                if(is_array ($parameters['Regiones'])){
                    $res->whereIn('et_cat_municipio.SubRegion',$parameters['Regiones']);
                }else{
                    $res->where('et_cat_municipio.SubRegion','=',$parameters['Regiones']);
                }    
            }
            if(isset($parameters['UserOwned'])){
                if(is_array ($parameters['UserOwned'])){
                    $res->whereIn('vales.UserOwned',$parameters['UserOwned']);
                }else{
                    $res->where('vales.UserOwned','=',$parameters['UserOwned']);
                }    
            }
            
            if(isset($parameters['idMunicipio'])){
                if(is_array ($parameters['idMunicipio'])){
                    $res->whereIn('vales.idMunicipio',$parameters['idMunicipio']);
                }else{
                    $res->where('vales.idMunicipio','=',$parameters['idMunicipio']);
                }    
            }
            if(isset($parameters['Colonia'])){
                if(is_array ($parameters['Colonia'])){
                    $res->whereIn('cat_cp.d_asenta',$parameters['Colonia']);
                }else{
                    $res->where('cat_cp.d_asenta','=',$parameters['Colonia']);
                }    
            }
            if(isset($parameters['idStatus'])){
                if(is_array ($parameters['idStatus'])){
                    $res->whereIn('vales_status.id',$parameters['idStatus']);
                }else{
                    $res->where('vales_status.id','=',$parameters['idStatus']);
                } 
                
            }
            if(isset($parameters['Remesa'])){
                if(is_array ($parameters['Remesa'])){
                    $flag_null = false;
                    foreach ($parameters['Remesa'] as $dato) {
                        if(strcmp($dato, 'null') === 0){
                            $flag_null = true;
                        }
                    }
                    if($flag_null){
                        $valor_id = $parameters['Remesa'];
                        $res->where(function($q) use ($valor_id) {
                            $q->whereIn('vales.Remesa', $valor_id)
                              ->orWhereNull('vales.Remesa');
                        });
                         
                    }
                    else{
                        $res->whereIn('vales.Remesa',$parameters['Remesa']);
                    }
                    
                    
                }else{
                     
                    if(strcmp($parameters['Remesa'], 'null') === 0){
                        $res->whereNull('vales.Remesa');
                    }
                    else{
                        $res->where('vales.Remesa','=',$parameters['Remesa']);
                    }
                }
            }
            
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
                            if(strcmp($parameters['filtered'][$i]['id'], 'vales.UserCreated') === 0 || strcmp($parameters['filtered'][$i]['id'], 'vales.UserUpdated') === 0 || strcmp($parameters['filtered'][$i]['id'], 'vales.UserOwned') === 0){
                                $res->where($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
                            }
                            else{
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
                                if(strcmp($parameters['filtered'][$i]['id'], 'vales.UserCreated') === 0 || strcmp($parameters['filtered'][$i]['id'], 'vales.UserUpdated') === 0 || strcmp($parameters['filtered'][$i]['id'], 'vales.UserOwned') === 0){
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
                                if(strcmp($parameters['filtered'][$i]['id'], 'vales.UserCreated') === 0 || strcmp($parameters['filtered'][$i]['id'], 'vales.UserUpdated') === 0){
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

            if(isset($parameters['NombreCompleto'])){
                $filtro_recibido = $parameters['NombreCompleto'];
                $filtro_recibido = str_replace(" ","",$filtro_recibido);
                $res->where(
                    DB::raw("
                    REPLACE(
                    CONCAT(
                        vales.Nombre,
                        vales.Paterno,
                        vales.Materno,
                        vales.Paterno,
                        vales.Nombre,
                        vales.Materno,
                        vales.Materno,
                        vales.Nombre,
                        vales.Paterno,
                        vales.Nombre,
                        vales.Materno,
                        vales.Paterno,
                        vales.Paterno,
                        vales.Materno,
                        vales.Nombre,
                        vales.Materno,
                        vales.Paterno,
                        vales.Nombre
                    ), ' ', '')")
            
                    ,'like',"%".$filtro_recibido."%");
                
            }

            if(isset($parameters['NombreOwner'])){
                $filtro_recibido = $parameters['NombreOwner'];
                $filtro_recibido = str_replace(" ","",$filtro_recibido);
                $res->where(
                    DB::raw("
                    REPLACE(
                    CONCAT(
                        usersC.Nombre,
                        usersC.Paterno,
                        usersC.Materno,
                        usersC.Paterno,
                        usersC.Nombre,
                        usersC.Materno,
                        usersC.Materno,
                        usersC.Nombre,
                        usersC.Paterno,
                        usersC.Nombre,
                        usersC.Materno,
                        usersC.Paterno,
                        usersC.Paterno,
                        usersC.Materno,
                        usersC.Nombre,
                        usersC.Materno,
                        usersC.Paterno,
                        usersC.Nombre
                    ), ' ', '')")
            
                    ,'like',"%".$filtro_recibido."%");
                
            }

            if(isset($parameters['NombreCreated'])){
                $filtro_recibido = $parameters['NombreCreated'];
                $filtro_recibido = str_replace(" ","",$filtro_recibido);
                $res->where(
                    DB::raw("
                    REPLACE(
                    CONCAT(
                        usersCretaed.Nombre,
                        usersCretaed.Paterno,
                        usersCretaed.Materno,
                        usersCretaed.Paterno,
                        usersCretaed.Nombre,
                        usersCretaed.Materno,
                        usersCretaed.Materno,
                        usersCretaed.Nombre,
                        usersCretaed.Paterno,
                        usersCretaed.Nombre,
                        usersCretaed.Materno,
                        usersCretaed.Paterno,
                        usersCretaed.Paterno,
                        usersCretaed.Materno,
                        usersCretaed.Nombre,
                        usersCretaed.Materno,
                        usersCretaed.Paterno,
                        usersCretaed.Nombre
                    ), ' ', '')")
            
                    ,'like',"%".$filtro_recibido."%");
                
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
                    'ClaveUnica'=> $data->ClaveUnica,
                    'TelRecados'=> $data->TelRecados,
                    'Compania'=> $data->Compania,
                    'FullName'=>$data->FullName,
                    'TelFijo'=> $data->TelFijo,
                    'FolioSolicitud'=> $data->FolioSolicitud,
                    'FechaSolicitud'=> $data->FechaSolicitud,
                    'CURP'=> $data->CURP,
                    'Nombre'=> $data->Nombre,
                    'Paterno'=> $data->Paterno,
                    'Materno'=> $data->Materno,
                    'Sexo'=> $data->Sexo,
                    'FechaNacimiento'=> $data->FechaNacimiento,
                    'Calle'=> $data->Calle,
                    'NumExt'=> $data->NumExt,
                    'NumInt'=> $data->NumInt,
                    'Colonia'=> $data->Colonia,
                    'CP'=> $data->CP,
                    'idMunicipio'=> [
                        'id'=> $data->IdM,
                        'Municipio'=> $data->Municipio,
                        'Region'=> $data->Region
                    ],
                    'idLocalidad'=> [
                        'id'=> $data->Clave,
                        'Nombre'=> $data->Localidad
                    ],
                    'TelFijo'=> $data->TelFijo,
                    'TelCelular'=> $data->TelCelular,
                    'CorreoElectronico'=> $data->CorreoElectronico,
                    'idStatus'=> [
                        'id'=> $data->idES,
                        'Clave'=> $data->ClaveA,
                        'Estatus'=> $data->Estatus
                    ],
                    'created_at'=> $data->created_at,
                    'updated_at'=> $data->updated_at,
                    'UserCreated' => [
                        'id'=> $data->idE,
                        'email'=> $data->emailE,
                        'Nombre'=> $data->NombreE,
                        'Paterno'=> $data->PaternoE,
                        'Materno'=> $data->MaternoE,
                        'idTipoUser' =>[
                            'id'=> $data->idEA,
                            'TipoUser'=> $data->TipoUserEA,
                            'Clave'=> $data->ClaveEA
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
                            'Clave'=> $data->ClaveFA
                        ]
                    ],
                    'UserOwned' => [
                        'id'=> $data->idO,
                        'email'=> $data->emailO,
                        'Nombre'=> $data->NombreO,
                        'Paterno'=> $data->PaternoO,
                        'Materno'=> $data->MaternoO,
                        'idTipoUser' =>[
                            'id'=> $data->idGO,
                            'TipoUser'=> $data->TipoUserGO,
                            'Clave'=> $data->ClaveGO
                        ]
                    ]


                ];
                
                array_push($array_res,$temp);
            }
            
            return ['success'=>true,'results'=>true,
             'total'=>$total,'filtros'=>$parameters['filtered'],'data'=>$array_res];

        } catch(QueryException $e){
            dd($e->getMessage());
            $errors = [
                "Clave"=>"01"
            ];
            $response = ['success'=>true,'results'=>false, 
            'total'=>0,'filtros'=>$parameters['filtered'],
            'errors'=>$errors, 'message' =>'Campo de consulta incorrecto'];

            return  response()->json($response, 200);
        }

    }
    function getValesDocumentacionIn(Request $request){
        $parameters = $request->all();

        try {
            $res_1 = DB::table('vales_documentacion')
            ->select('vales_documentacion.id')
            ->get();

            $array = [];
            foreach ($res_1 as $data) {
                array_push($array,$data->id);
            }

            $res = DB::table('vales')
            ->select(
                'vales.id', 
                DB::raw('LPAD(HEX(vales.id),6,0) as ClaveUnica'),
                DB::raw('concat_ws(" ",vales.Nombre, vales.Paterno,vales.Materno) as FullName'),
                'vales.TelRecados',
                'vales.Compania',
                'vales.TelFijo',
                'vales.FolioSolicitud',
                'vales.FechaSolicitud',
                'vales.CURP', 
                'vales.Nombre', 
                'vales.Paterno', 
                'vales.Materno', 
                'vales.Sexo', 
                'vales.FechaNacimiento', 
                'vales.Calle', 
                'vales.NumExt', 
                'vales.NumInt', 
                'vales.Colonia', 
                'vales.CP', 
                'vales.isEntregadoOwner',
                //SOLICITUD
                'vales_solicitudes.id as idRegistro_Solicitud',
                'vales_solicitudes.idSolicitud as id_Solicitud',
                'vales_solicitudes.CURP as CURP_Solicitud',
                'vales_solicitudes.Nombre as Nombre_Solicitud',
                'vales_solicitudes.Paterno as Paterno_Solicitud',
                'vales_solicitudes.Materno as Materno_Solicitud',
                'vales_solicitudes.CodigoBarrasInicial as CodigoBarrasInicial_Solicitud',
                'vales_solicitudes.CodigoBarrasFinal as CodigoBarrasFinal_Solicitud',
                'vales_solicitudes.SerieInicial as SerieInicial_Solicitud',
                'vales_solicitudes.SerieFinal as SerieFinal_Solicitud',
                'vales_solicitudes.Remesa as Remesa_Solicitud',
                'vales_solicitudes.Comentario as Comentario_Solicitud',
                'vales_solicitudes.created_at as created_at_Solicitud',
                'vales_solicitudes.UserCreated as UserCreated_Solicitud',
                'vales_solicitudes.updated_at as updated_at_Solicitud',

                'vales.idMunicipio', 
                    'et_cat_municipio.Id AS IdM',
                    'et_cat_municipio.Nombre AS Municipio',
                    'et_cat_municipio.SubRegion AS Region',
                'vales.idLocalidad',
                    'et_cat_localidad.Id AS Clave',
                    'et_cat_localidad.Nombre AS Localidad',
                'vales.TelFijo', 
                'vales.TelCelular', 
                'vales.CorreoElectronico', 
                'vales.idStatus', 
                    'vales_status.id as idES',
                    'vales_status.Estatus',
                    'vales_status.Clave as ClaveA',
                'vales.created_at', 
                'vales.updated_at', 
                'vales.UserCreated',
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
                'vales.UserUpdated',
                //Datos Usuario updated
                    'usersB.id as idF',
                    'usersB.email as emailF',
                    'usersB.Nombre as NombreF',
                    'usersB.Paterno as PaternoF',
                    'usersB.Materno as MaternoF',
                    'usersB.idTipoUser',
                        'cat_usertipoB.id as idFA',
                        'cat_usertipoB.TipoUser as TipoUserFA',
                        'cat_usertipoB.Clave as ClaveFA',
                'vales.UserOwned',
                //Datos Usuario owned
                    'usersC.id as idO',
                    'usersC.email as emailO',
                    'usersC.Nombre as NombreO',
                    'usersC.Paterno as PaternoO',
                    'usersC.Materno as MaternoO',
                    'usersC.idTipoUser',
                        'cat_usertipoC.id as idGO',
                        'cat_usertipoC.TipoUser as TipoUserGO',
                        'cat_usertipoC.Clave as ClaveGO'
            )
            ->leftJoin('et_cat_municipio','et_cat_municipio.Id','=','vales.idMunicipio')
            ->leftJoin('et_cat_localidad','et_cat_localidad.Id','=','vales.idLocalidad')
            ->leftJoin('vales_status','vales_status.id','=','idStatus')
            ->leftJoin('users','users.id','=','vales.UserCreated')
            ->leftJoin('cat_usertipo','cat_usertipo.id','=','users.idTipoUser')
            ->leftJoin('users as usersB','usersB.id','=','vales.UserUpdated')
            ->leftJoin('cat_usertipo as cat_usertipoB','cat_usertipoB.id','=','usersB.idTipoUser')
            ->leftJoin('users as usersC','usersC.id','=','vales.UserOwned')
            ->leftJoin('users as usersCretaed','usersCretaed.id','=','vales.UserCreated')
            ->leftJoin('cat_usertipo as cat_usertipoC','cat_usertipoC.id','=','usersC.idTipoUser')
            ->where('vales.idStatus','=',5)
            ->whereIn('vales.id',$array)
            ->leftJoin('vales_solicitudes','vales_solicitudes.idSolicitud','=','vales.id')
            ->orderBy('et_cat_municipio.Nombre','asc')->orderBy('vales.Colonia','asc')->orderBy('vales.Nombre','asc')->orderBy('vales.Paterno','asc');
            

            if(isset($parameters['Propietario'])){
                $valor_id = $parameters['Propietario'];
                $res->where(function($q)use ($valor_id) {
                    $q->where('vales.UserCreated', $valor_id)
                      ->orWhere('vales.UserOwned', $valor_id);
                });
            }
            if(isset($parameters['Folio'])){
                $valor_id = $parameters['Folio'];
                $res->where(DB::raw('LPAD(HEX(vales.id),6,0)'),'like','%'.$valor_id.'%');
            }
            if(isset($parameters['Regiones'])){
                if(is_array ($parameters['Regiones'])){
                    $res->whereIn('et_cat_municipio.SubRegion',$parameters['Regiones']);
                }else{
                    $res->where('et_cat_municipio.SubRegion','=',$parameters['Regiones']);
                }    
            }
            if(isset($parameters['UserOwned'])){
                if(is_array ($parameters['UserOwned'])){
                    $res->whereIn('vales.UserOwned',$parameters['UserOwned']);
                }else{
                    $res->where('vales.UserOwned','=',$parameters['UserOwned']);
                }    
            }
            
            if(isset($parameters['idMunicipio'])){
                if(is_array ($parameters['idMunicipio'])){
                    $res->whereIn('vales.idMunicipio',$parameters['idMunicipio']);
                }else{
                    $res->where('vales.idMunicipio','=',$parameters['idMunicipio']);
                }    
            }
            if(isset($parameters['Colonia'])){
                if(is_array ($parameters['Colonia'])){
                    $res->whereIn('cat_cp.d_asenta',$parameters['Colonia']);
                }else{
                    $res->where('cat_cp.d_asenta','=',$parameters['Colonia']);
                }    
            }
            if(isset($parameters['idStatus'])){
                if(is_array ($parameters['idStatus'])){
                    $res->whereIn('vales_status.id',$parameters['idStatus']);
                }else{
                    $res->where('vales_status.id','=',$parameters['idStatus']);
                } 
                
            }
            if(isset($parameters['isEntregadoOwner'])){
                if(is_array ($parameters['isEntregadoOwner'])){
                    $res->whereIn('vales.isEntregadoOwner',$parameters['isEntregadoOwner']);
                }else{
                    $res->where('vales.isEntregadoOwner','=',$parameters['isEntregadoOwner']);
                } 
                
            }
            if(isset($parameters['Remesa'])){
                if(is_array ($parameters['Remesa'])){
                    $flag_null = false;
                    foreach ($parameters['Remesa'] as $dato) {
                        if(strcmp($dato, 'null') === 0){
                            $flag_null = true;
                        }
                    }
                    if($flag_null){
                        $valor_id = $parameters['Remesa'];
                        $res->where(function($q) use ($valor_id) {
                            $q->whereIn('vales.Remesa', $valor_id)
                              ->orWhereNull('vales.Remesa');
                        });
                         
                    }
                    else{
                        $res->whereIn('vales.Remesa',$parameters['Remesa']);
                    }
                    
                    
                }else{
                     
                    if(strcmp($parameters['Remesa'], 'null') === 0){
                        $res->whereNull('vales.Remesa');
                    }
                    else{
                        $res->where('vales.Remesa','=',$parameters['Remesa']);
                    }
                }
            }
            /* if(isset($parameters['UserOwned'])){
                if(is_array ($parameters['UserOwned'])){
                    $res->whereIn('vales.UserOwned',$parameters['UserOwned']);
                }else{
                    $res->where('vales.UserOwned','=',$parameters['UserOwned']);
                }    
            }
            if(isset($parameters['Remesa'])){
                if(is_array ($parameters['Remesa'])){
                    $res->whereIn('vales.Remesa',$parameters['Remesa']);
                }else{
                    $res->where('vales.Remesa','=',$parameters['Remesa']);
                }    
            } */
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
                            if(strcmp($parameters['filtered'][$i]['id'], 'vales.UserCreated') === 0 || strcmp($parameters['filtered'][$i]['id'], 'vales.UserUpdated') === 0 || strcmp($parameters['filtered'][$i]['id'], 'vales.UserOwned') === 0){
                                $res->where($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
                            }
                            else{
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
                                if(strcmp($parameters['filtered'][$i]['id'], 'vales.UserCreated') === 0 || strcmp($parameters['filtered'][$i]['id'], 'vales.UserUpdated') === 0 || strcmp($parameters['filtered'][$i]['id'], 'vales.UserOwned') === 0){
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
                                if(strcmp($parameters['filtered'][$i]['id'], 'vales.UserCreated') === 0 || strcmp($parameters['filtered'][$i]['id'], 'vales.UserUpdated') === 0){
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

            if(isset($parameters['NombreCompleto'])){
                $filtro_recibido = $parameters['NombreCompleto'];
                $filtro_recibido = str_replace(" ","",$filtro_recibido);
                $res->where(
                    DB::raw("
                    REPLACE(
                    CONCAT(
                        vales.Nombre,
                        vales.Paterno,
                        vales.Materno,
                        vales.Paterno,
                        vales.Nombre,
                        vales.Materno,
                        vales.Materno,
                        vales.Nombre,
                        vales.Paterno,
                        vales.Nombre,
                        vales.Materno,
                        vales.Paterno,
                        vales.Paterno,
                        vales.Materno,
                        vales.Nombre,
                        vales.Materno,
                        vales.Paterno,
                        vales.Nombre
                    ), ' ', '')")
            
                    ,'like',"%".$filtro_recibido."%");
                
            }

            if(isset($parameters['NombreOwner'])){
                $filtro_recibido = $parameters['NombreOwner'];
                $filtro_recibido = str_replace(" ","",$filtro_recibido);
                $res->where(
                    DB::raw("
                    REPLACE(
                    CONCAT(
                        usersC.Nombre,
                        usersC.Paterno,
                        usersC.Materno,
                        usersC.Paterno,
                        usersC.Nombre,
                        usersC.Materno,
                        usersC.Materno,
                        usersC.Nombre,
                        usersC.Paterno,
                        usersC.Nombre,
                        usersC.Materno,
                        usersC.Paterno,
                        usersC.Paterno,
                        usersC.Materno,
                        usersC.Nombre,
                        usersC.Materno,
                        usersC.Paterno,
                        usersC.Nombre
                    ), ' ', '')")
            
                    ,'like',"%".$filtro_recibido."%");
                
            }

            if(isset($parameters['NombreCreated'])){
                $filtro_recibido = $parameters['NombreCreated'];
                $filtro_recibido = str_replace(" ","",$filtro_recibido);
                $res->where(
                    DB::raw("
                    REPLACE(
                    CONCAT(
                        usersCretaed.Nombre,
                        usersCretaed.Paterno,
                        usersCretaed.Materno,
                        usersCretaed.Paterno,
                        usersCretaed.Nombre,
                        usersCretaed.Materno,
                        usersCretaed.Materno,
                        usersCretaed.Nombre,
                        usersCretaed.Paterno,
                        usersCretaed.Nombre,
                        usersCretaed.Materno,
                        usersCretaed.Paterno,
                        usersCretaed.Paterno,
                        usersCretaed.Materno,
                        usersCretaed.Nombre,
                        usersCretaed.Materno,
                        usersCretaed.Paterno,
                        usersCretaed.Nombre
                    ), ' ', '')")
            
                    ,'like',"%".$filtro_recibido."%");
                
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
                    'ClaveUnica'=> $data->ClaveUnica,
                    'TelRecados'=> $data->TelRecados,
                    'Compania'=> $data->Compania,
                    'TelFijo'=> $data->TelFijo,
                    'FolioSolicitud'=> $data->FolioSolicitud,
                    'FechaSolicitud'=> $data->FechaSolicitud,
                    'CURP'=> $data->CURP,
                    'FullName'=>$data->FullName,
                    'Nombre'=> $data->Nombre,
                    'Paterno'=> $data->Paterno,
                    'Materno'=> $data->Materno,
                    'Sexo'=> $data->Sexo,
                    'FechaNacimiento'=> $data->FechaNacimiento,
                    'Calle'=> $data->Calle,
                    'NumExt'=> $data->NumExt,
                    'NumInt'=> $data->NumInt,
                    'Colonia'=> $data->Colonia,
                    'CP'=> $data->CP,
                    'isEntregadoOwner'=> $data->isEntregadoOwner,

                    //SOLICITUD
                    'idSolicitud'=> [
                    'id '=> $data->idRegistro_Solicitud,
                    'idSolicitud'=> $data->id_Solicitud,
                    'CURP'=> $data->CURP_Solicitud,
                    'Nombre'=> $data->Nombre_Solicitud,
                    'Paterno'=> $data->Paterno_Solicitud,
                    'Materno'=> $data->Materno_Solicitud,
                    'CodigoBarrasInicial'=> $data->CodigoBarrasInicial_Solicitud,
                    'CodigoBarrasFinal'=> $data->CodigoBarrasFinal_Solicitud,
                    'SerieInicial'=> $data->SerieInicial_Solicitud,
                    'SerieFinal'=> $data->SerieFinal_Solicitud,
                    'Remesa'=> $data->Remesa_Solicitud,
                    'Comentario'=> $data->Comentario_Solicitud,
                    'created_at'=> $data->created_at_Solicitud,
                    'UserCreated'=> $data->UserCreated_Solicitud,
                    'updated_at'=> $data->updated_at_Solicitud
                    ],


                    'idMunicipio'=> [
                        'id'=> $data->IdM,
                        'Municipio'=> $data->Municipio,
                        'Region'=> $data->Region
                    ],
                    'idLocalidad'=> [
                        'id'=> $data->Clave,
                        'Nombre'=> $data->Localidad
                    ],
                    'TelFijo'=> $data->TelFijo,
                    'TelCelular'=> $data->TelCelular,
                    'CorreoElectronico'=> $data->CorreoElectronico,
                    'idStatus'=> [
                        'id'=> $data->idES,
                        'Clave'=> $data->ClaveA,
                        'Estatus'=> $data->Estatus
                    ],
                    'created_at'=> $data->created_at,
                    'updated_at'=> $data->updated_at,
                    'UserCreated' => [
                        'id'=> $data->idE,
                        'email'=> $data->emailE,
                        'Nombre'=> $data->NombreE,
                        'Paterno'=> $data->PaternoE,
                        'Materno'=> $data->MaternoE,
                        'idTipoUser' =>[
                            'id'=> $data->idEA,
                            'TipoUser'=> $data->TipoUserEA,
                            'Clave'=> $data->ClaveEA
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
                            'Clave'=> $data->ClaveFA
                        ]
                    ],
                    'UserOwned' => [
                        'id'=> $data->idO,
                        'email'=> $data->emailO,
                        'Nombre'=> $data->NombreO,
                        'Paterno'=> $data->PaternoO,
                        'Materno'=> $data->MaternoO,
                        'idTipoUser' =>[
                            'id'=> $data->idGO,
                            'TipoUser'=> $data->TipoUserGO,
                            'Clave'=> $data->ClaveGO
                        ]
                    ]


                ];
                
                array_push($array_res,$temp);
            }
            
            return ['success'=>true,'results'=>true,
             'total'=>$total,'filtros'=>$parameters['filtered'],'data'=>$array_res];

        } catch(QueryException $e){
            dd($e->getMessage());
            $errors = [
                "Clave"=>"01"
            ];
            $response = ['success'=>true,'results'=>false, 
            'total'=>0,'filtros'=>$parameters['filtered'],
            'errors'=>$errors, 'message' =>'Campo de consulta incorrecto'];

            return  response()->json($response, 200);
        }

    }

    function setValesDocumentacion(Request $request){
        $v = Validator::make($request->all(), [
            'id' => 'required|unique:vales_documentacion',
            'Nombre'=> 'required',
            'Paterno'=> 'required',
            'FechaNacimiento'=> 'required',
            'Sexo'=> 'required',
            'CURP'=> 'required|unique:vales_documentacion',
            'idMunicipio'=> 'required',
            'idLocalidad'=> 'required', 
            'Colonia'=> 'required',
            'NumExt'=> 'required',
            'CP'=> 'required',
            'FechaSolicitud'=> 'required', 
            'UserOwned'=> 'required',

            //Campos adicionales.
            'idStatusDocumentacion'=> 'required',
            'TieneFolio'=> 'required',
            'TieneFechaSolicitud'=> 'required',
            'TieneCURPValida'=> 'required',
            'NombreCoincideConINE'=> 'required',
            'TieneDomicilio'=> 'required',
            'TieneArticuladorReverso'=> 'required',
            'FolioCoincideListado'=> 'required',
            'FechaSolicitudChange'=> 'required',
            'CURPCoincideListado'=> 'required',
            'NombreChanged'=> 'required',
            'PaternoChanged'=> 'required',
            'MaternoChanged'=> 'required',
            'CalleChanged'=> 'required',
            'NumExtChanged'=> 'required',
            'NumIntChanged'=> 'required',
            'ColoniaChanged'=> 'required',
            'idLocalidadChanged'=> 'required',
            'idMunicipioChanged'=> 'required',
            'UserOwnedchanged'=> 'required'
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
        $parameters['UserOwned'] = $parameters['UserOwned'];
        $posible_registro = DB::table('vales_documentacion')
        ->select(
            'vales_documentacion.*',
            DB::raw('LPAD(HEX(vales_documentacion.id),6,0) as ClaveUnica')
            )
        ->where('vales_documentacion.id','=',$parameters['id'])->first();

        if($posible_registro){
            return ['success'=>true,'results'=>false,
            'errors'=>'El negocio que desea agregar ya existe.',
            'data'=>$posible_registro];
        }
        $vale_ = ValesDocumentacion::create($parameters);
        //$vale =  VVales::find($vale_->id);
        $vale = DB::table('vales_documentacion')
        ->select(
            'vales_documentacion.*',
            DB::raw('LPAD(HEX(vales_documentacion.id),6,0) as ClaveUnica')
            )
        ->where('vales_documentacion.id','=',$parameters['id'])->first();
        return ['success'=>true,'results'=>true,
            'data'=>$vale];
    }

    function deleteValesDocumentacion(Request $request){

        $v = Validator::make($request->all(), [
            'id' => 'required'
        ]);
        
		if ($v->fails()){
            $response =  ['success'=>false,'results'=>false,
            'errors'=>$v->errors(),'data'=>[]];

            return response()->json($response,200);
        }
        $parameters = $request->all();

        
        $user = auth()->user();
        
        $vale = DB::table('vales_documentacion')
        ->select(
            'vales_documentacion.*',
            DB::raw('LPAD(HEX(vales_documentacion.id),6,0) as ClaveUnica')
            )
        ->where('vales_documentacion.id','=',$parameters['id'])->first();
        if(!$vale){
            return ['success'=>true,'results'=>false,
                'errors'=>'El negocio que desea eliminar no existe.',
                'data'=>[]];
        }
        $vale->delete();

        return ['success'=>true,'results'=>true,
            'data'=>$vale];
    }

    function getArticularDocumentacion(Request $request){
        $parameters = $request->all();

        try {

            $res_1 = DB::table('vales as V')
            ->select('V.UserOwned',
            'M.SubRegion AS Region',
            'V.idMunicipio',
            'M.Nombre AS Municipio',
            'V.Remesa',
            DB::raw('concat_ws(" ",A.Nombre, A.Paterno,A.Materno) as FullName'),
            DB::raw('count(V.id) Total')
            )
            ->leftJoin('et_cat_municipio as M','V.idMunicipio','=','M.Id')
            ->leftJoin('users as A','V.UserOwned','=','A.id')
            ->where('V.idStatus','=',5)
             ->groupBy('V.UserOwned')
             ->groupBy('M.SubRegion')
             ->groupBy('V.idMunicipio')
             ->groupBy('V.Remesa')->get();

             //________________________________________________________________

            $res = DB::table('vales as V')
            ->select('V.UserOwned',
            'M.SubRegion AS Region',
            'V.idMunicipio',
            'M.Nombre AS Municipio',
            'V.Remesa',
            DB::raw('concat_ws(" ",A.Nombre, A.Paterno,A.Materno) as FullName'),
            DB::raw('count(V.id) Faltantes')
            )
            ->leftJoin('et_cat_municipio as M','V.idMunicipio','=','M.Id')
            ->leftJoin('users as A','V.UserOwned','=','A.id')
            ->where('V.idStatus','=',5)
            
            ->whereNotIn('V.id',function($query){
                $query->select('id')->from('vales_documentacion');
             })
             ->groupBy('V.UserOwned')
             ->groupBy('M.SubRegion')
             ->groupBy('V.idMunicipio')
             ->groupBy('V.Remesa')
             ->orderBy('V.updated_at','desc'); 
             
             

             if(isset($parameters['isEntregadoOwner'])){
                if(is_array ($parameters['isEntregadoOwner'])){
                    $res->whereIn('V.isEntregadoOwner',$parameters['isEntregadoOwner']);
                }else{
                    $res->where('V.isEntregadoOwner','=',$parameters['isEntregadoOwner']);
                } 
                
            }
            
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
                            }
                            else{
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
                                if(strcmp($parameters['filtered'][$i]['id'], 'UserCreated') === 0 || strcmp($parameters['filtered'][$i]['id'], 'UserUpdated') === 0 ){
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

            if(isset($parameters['NombreCompleto'])){
                $filtro_recibido = $parameters['NombreCompleto'];
                $filtro_recibido = str_replace(" ","",$filtro_recibido);
                $res->where(
                    DB::raw("
                    REPLACE(
                    CONCAT(
                        A.Nombre,
                        A.Paterno,
                        A.Materno,
                        A.Paterno,
                        A.Nombre,
                        A.Materno,
                        A.Materno,
                        A.Nombre,
                        A.Paterno,
                        A.Nombre,
                        A.Materno,
                        A.Paterno,
                        A.Paterno,
                        A.Materno,
                        A.Nombre,
                        A.Materno,
                        A.Paterno,
                        A.Nombre
                    ), ' ', '')")
            
                    ,'like',"%".$filtro_recibido."%");
                
            }

            $total = $res->count(); 
            $res = $res->offset($startIndex)
            ->take($pageSize)
            ->get();

            $res_array = [];
            for ($i=0; $i < count($res) ; $i++) { 
                $temp = $res[$i];
                $temp->Total = 0;
                //$temp->Faltantes = 0;
                for ($x=0; $x < count($res_1) ; $x++) { 
                    if($res[$i]->UserOwned === $res_1[$x]->UserOwned &&
                    $res[$i]->idMunicipio === $res_1[$x]->idMunicipio &&
                    $res[$i]->Remesa === $res_1[$x]->Remesa
                    ){
                        $temp->Total = $res_1[$x]->Total;
                        //$temp->Faltantes = $res[$i]->Total - $res_1[$x]->Entregados;
                    }
                }
                

                array_push($res_array,$temp);

            }
            
            
            
            return ['success'=>true,'results'=>true,
             'total'=>$total,'filtros'=>$parameters['filtered'],'data'=>$res_array];

        } catch(QueryException $e){
            $errors = [
                "Clave"=>"01"
            ];
            $response = ['success'=>true,'results'=>false, 
            'total'=>0,'filtros'=>$parameters['filtered'],
            'errors'=>$e, 'message' =>'Campo de consulta incorrecto'];

            return  response()->json($response, 200);
        }

    }

    function getArticularEntregado(Request $request){
        $parameters = $request->all();

        try {

            $res_1 = DB::table('vales as V')
            ->select('V.UserOwned',
            'M.SubRegion AS Region',
            'V.idMunicipio',
            'M.Nombre AS Municipio',
            'V.Remesa',
            DB::raw('concat_ws(" ",A.Nombre, A.Paterno,A.Materno) as FullName'),
            DB::raw('count(V.id) Entregados')
            )
            ->leftJoin('et_cat_municipio as M','V.idMunicipio','=','M.Id')
            ->leftJoin('users as A','V.UserOwned','=','A.id')
            ->where('V.idStatus','=',5)
            //->whereNotNull('V.Remesa')
            
            ->whereIn('V.id',function($query){
                $query->select('id')->from('vales_documentacion');
             })
             ->where('V.isEntregadoOwner','=',1)
             ->groupBy('V.UserOwned')
             ->groupBy('M.SubRegion')
             ->groupBy('V.idMunicipio')
             ->groupBy('V.Remesa')->get();

            //_______________________________________________________________
             
            $res = DB::table('vales as V')
            ->select('V.UserOwned',
            'M.SubRegion AS Region',
            'V.idMunicipio',
            'M.Nombre AS Municipio',
            'V.Remesa',
            DB::raw('concat_ws(" ",A.Nombre, A.Paterno,A.Materno) as FullName'),
            DB::raw('count(V.id) Total')
            //'res_1.Entregados as Entregados'
            )
            ->leftJoin('et_cat_municipio as M','V.idMunicipio','=','M.Id')
            ->leftJoin('users as A','V.UserOwned','=','A.id')
            /* ->joinSub($res_1, 'res_1', function ($join) {
                $join->on('V.UserOwned', '=', 'res_1.UserOwned')
                ->on('V.idMunicipio','=','res_1.idMunicipio');
            }) */
            ->where('V.idStatus','=',5)
            ->whereIn('V.id',function($query){
                $query->select('id')->from('vales_documentacion');
             })
             ->groupBy('V.UserOwned')
             ->groupBy('M.SubRegion')
             ->groupBy('V.idMunicipio')
             ->groupBy('V.Remesa');        
             
            

             if(isset($parameters['isEntregadoOwner'])){
                if(is_array ($parameters['isEntregadoOwner'])){
                    $res->whereIn('V.isEntregadoOwner',$parameters['isEntregadoOwner']);
                }else{
                    $res->where('V.isEntregadoOwner','=',$parameters['isEntregadoOwner']);
                } 
                
            }
            
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
                            }
                            else{
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
                                if(strcmp($parameters['filtered'][$i]['id'], 'UserCreated') === 0 || strcmp($parameters['filtered'][$i]['id'], 'UserUpdated') === 0 ){
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

            if(isset($parameters['NombreCompleto'])){
                $filtro_recibido = $parameters['NombreCompleto'];
                $filtro_recibido = str_replace(" ","",$filtro_recibido);
                $res->where(
                    DB::raw("
                    REPLACE(
                    CONCAT(
                        A.Nombre,
                        A.Paterno,
                        A.Materno,
                        A.Paterno,
                        A.Nombre,
                        A.Materno,
                        A.Materno,
                        A.Nombre,
                        A.Paterno,
                        A.Nombre,
                        A.Materno,
                        A.Paterno,
                        A.Paterno,
                        A.Materno,
                        A.Nombre,
                        A.Materno,
                        A.Paterno,
                        A.Nombre
                    ), ' ', '')")
            
                    ,'like',"%".$filtro_recibido."%");
                
            }

            $total = $res->count(); 
            $res = $res->offset($startIndex)
            ->take($pageSize)
            ->get();
            $res_array = [];
            for ($i=0; $i < count($res) ; $i++) { 
                $temp = $res[$i];
                $temp->Entregados = 0;
                //$temp->Faltantes = 0;
                for ($x=0; $x < count($res_1) ; $x++) { 
                    if($res[$i]->UserOwned === $res_1[$x]->UserOwned &&
                    $res[$i]->idMunicipio === $res_1[$x]->idMunicipio &&
                    $res[$i]->Remesa === $res_1[$x]->Remesa
                    ){
                        $temp->Entregados = $res_1[$x]->Entregados;
                        //$temp->Faltantes = $res[$i]->Total - $res_1[$x]->Entregados;
                    }
                }
                

                array_push($res_array,$temp);

            }

            
            
            
            return ['success'=>true,'results'=>true,
             'total'=>$total,'filtros'=>$parameters['filtered'],'data'=>$res_array];

        } catch(QueryException $e){
            $errors = [
                "Clave"=>"01"
            ];
            $response = ['success'=>true,'results'=>false, 
            'total'=>0,'filtros'=>$parameters['filtered'],
            'errors'=>$e, 'message' =>'Campo de consulta incorrecto'];

            return  response()->json($response, 200);
        }

    }
}
