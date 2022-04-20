<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use \Illuminate\Database\QueryException;
use DB;
use Illuminate\Contracts\Validation\ValidationException;
use Validator;
use App\Persona;

class PersonaController extends Controller
{
    function getPersonas(Request $request){
        $parameters = $request->all();

        try {
            $res = DB::table('personas')
            ->select(
                
                //Datos persona
                'personas.id', 'personas.CURP', 'personas.Nombre', 'personas.Paterno',
                'personas.Materno', 'personas.Sexo', 
                'personas.FechaNacimiento', 
                'personas.idEntidadNacimiento',
                    'cat_estados.id as idB','cat_estados.Estado as EstadoB','cat_estados.Clave as ClaveB',

                'personas.TelCasa', 'personas.TelCelular', 
                'personas.Calle', 'personas.NumExt', 
                'personas.NumInt', 'personas.Colonia', 'personas.CP', 
                'personas.idMunicipio',
                    'cat_municipio.id as idC','cat_municipio.Municipio as MunicipioC','cat_municipio.Clave as ClaveC',
                'personas.idLocalidad', 
                    'cat_localidades.mapa as mapaD',
                    'cat_localidades.idEstado as idEstadoD',
                    'cat_localidades.cve_ent as cve_entD',
                    'cat_localidades.Entidad as EntidadD',
                    'cat_localidades.nom_abr as nom_abrD',
                    'cat_localidades.idMunicipio as idMunicipioD',
                    'cat_localidades.cve_mun as cve_munD',
                    'cat_localidades.Municipio as MunicipioD',
                    'cat_localidades.cve_loc as cve_locD',
                    'cat_localidades.nom_loc as nom_locD',
                    'cat_localidades.Ambito as AmbitoD',
                    'cat_localidades.lat_decimal as lat_decimalD',
                    'cat_localidades.lon_decimal as lon_decimalD',
                    'cat_localidades.PoblacionTotal as PoblacionTotalD',
                    'cat_localidades.PoblacionMasculina as PoblacionMasculinaD',
                    'cat_localidades.PoblacionFemenina as PoblacionFemeninaD',
                    'cat_localidades.TotalViviendasHabitadas as TotalViviendasHabitadasD',

                'personas.created_at', 'personas.updated_at',
                'personas.UserCreated',
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
                    'users.Cedula as CedulaE',
                    'users.Especialidad as EspecialidadE',
                    'users.idCLUES',
                        'clues.id as idEB',
                        'clues.CLUES as CLUESEB',
                        'clues.NombreMunicipio as NombreMunicipioEB',
                        'clues.ClaveMunicipio as ClaveMunicipioEB',
                        'clues.NombreUnidad as NombreUnidadEB',
                'personas.UserUpdated',
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
                    'usersB.Cedula as CedulaF',
                    'usersB.Especialidad as EspecialidadF',
                    'usersB.idCLUES',
                        'cluesB.id as idFB',
                        'cluesB.CLUES as CLUESFB',
                        'cluesB.NombreMunicipio as NombreMunicipioFB',
                        'cluesB.ClaveMunicipio as ClaveMunicipioFB',
                        'cluesB.NombreUnidad as NombreUnidadFB',
            )
            ->leftJoin('cat_estados','cat_estados.id','=','personas.idEntidadNacimiento')
            ->leftJoin('cat_municipio','cat_municipio.id','=','personas.idMunicipio')
            ->leftJoin('cat_localidades','cat_localidades.mapa','=','personas.idLocalidad')
            ->leftJoin('users','users.id','=','personas.UserCreated')
            ->leftJoin('cat_usertipo','cat_usertipo.id','=','users.idTipoUser')
            ->leftJoin('clues','clues.id','=','users.idCLUES')
            ->leftJoin('users as usersB','usersB.id','=','personas.UserUpdated')
            ->leftJoin('cat_usertipo as cat_usertipoB','cat_usertipoB.id','=','usersB.idTipoUser')
            ->leftJoin('clues as cluesB','cluesB.id','=','usersB.idCLUES');

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

            /*
            ABC     ACB
            BAC     BCA
            CAB     CBA
            */
            if(isset($parameters['NombreCompleto'])){
                $persona_recibida = $parameters['NombreCompleto'];
                $persona_recibida = str_replace(" ","",$persona_recibida);
                
                $res->where(
                        DB::raw("
                        REPLACE(
                        CONCAT(
                            personas.Nombre,
                            personas.Paterno,
                            personas.Materno,

                            personas.Paterno,
                            personas.Nombre,
                            personas.Materno,

                            personas.Materno,
                            personas.Nombre,
                            personas.Paterno,

                            personas.Nombre,
                            personas.Materno,
                            personas.Paterno,

                            personas.Paterno,
                            personas.Materno,
                            personas.Nombre,

                            personas.Materno,
                            personas.Paterno,
                            personas.Nombre
                            
                        ), ' ', '')")
                
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
           
            $array_res = [];
            $temp = [];
            $data_temp = [];
            foreach($res as $data){
                $temp = [
                   'id'=> $data->id,
                   'CURP'=> $data->CURP,
                   'Nombre'=> $data->Nombre,
                   'Paterno'=> $data->Paterno,
                   'Materno'=> $data->Materno,
                   'Sexo'=> $data->Sexo,
                   'FechaNacimiento' =>  $data->FechaNacimiento,
                   'idEntidadNacimiento' =>  [
                        'id'=> $data->idB,
                        'Estado' => $data->EstadoB,
                        'Clave' => $data->ClaveB,
                   ],
                   'TelCasa' =>  $data->TelCasa,
                   'TelCelular' =>  $data->TelCelular,
                   'Calle' =>  $data->Calle,
                   'NumExt' =>  $data->NumExt,
                   'NumInt' =>  $data->NumInt,
                   'Colonia' =>  $data->Colonia,
                   'CP' =>  $data->CP,
                   'idMunicipio' =>  [
                       'id'=>$data->idC,
                       'Municipio'=>$data->MunicipioC,
                       'Clave'=>$data->ClaveC,
                   ],
                   'idLocalidad' =>  [
                        'mapa'=>$data->mapaD,
                        'idEstado'=>$data->idEstadoD,
                        'cve_ent'=>$data->cve_entD,
                        'Entidad'=>$data->EntidadD,
                        'nom_abr'=>$data->nom_abrD,
                        'idMunicipio'=>$data->idMunicipioD,
                        'cve_mun'=>$data->cve_munD,
                        'Municipio'=>$data->MunicipioD,
                        'cve_loc'=>$data->cve_locD,
                        'nom_loc'=>$data->nom_locD,
                        'Ambito'=>$data->AmbitoD,
                        'lat_decimal'=>$data->lat_decimalD,
                        'lon_decimal'=>$data->lon_decimalD,
                        'PoblacionTotal'=>$data->PoblacionTotalD,
                        'PoblacionMasculina'=>$data->PoblacionMasculinaD,
                        'PoblacionFemenina'=>$data->PoblacionFemeninaD,
                        'TotalViviendasHabitadas'=>$data->TotalViviendasHabitadasD,
                    ],
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
                        ],
                        'Cedula'=> $data->CedulaE,
                        'Especialidad'=> $data->EspecialidadE,
                        'idCLUES' =>[
                            'id'=> $data->idEB,
                            'CLUES'=> $data->CLUESEB,
                            'NombreMunicipio'=> $data->NombreMunicipioEB,
                            'ClaveMunicipio'=> $data->ClaveMunicipioEB,
                            'NombreUnidad'=> $data->NombreUnidadEB,
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
                        ],
                        'Cedula'=> $data->CedulaF,
                        'Especialidad'=> $data->EspecialidadF,
                        'idCLUES' =>[
                            'id'=> $data->idFB,
                            'CLUES'=> $data->CLUESFB,
                            'NombreMunicipio'=> $data->NombreMunicipioFB,
                            'ClaveMunicipio'=> $data->ClaveMunicipioFB,
                            'NombreUnidad'=> $data->NombreUnidadFB,
                        ]
                    ]

                ];

                $data_temp = $temp;
                
                array_push($array_res,$data_temp);
            }

            //$string = "fecha.created_at";
            //$array_ = explode( '.', $string );
            
            return ['success'=>true,'results'=>true,
             'total'=>$total,'filtros'=>$parameters['filtered'],'data'=>$array_res];

        } catch(QueryException $e){
            return [
                'success' => false,
                'errors' => $e->getMessage()
            ];
        }

    }

    function setPersonas(Request $request){

        /* EJEMPLO JSON:
       {
        "Nombre":"Zincri",
        "Paterno":"FERNANDEZ",
        "Materno":"LOPEZ",
        "idMunicipio":523,
        "Colonia":"Colonia Venta Prieta",
        "Sexo":"M",
        "FechaNacimiento":"2020-04-08",
        "idEntidadNacimiento":7,
        "CP":"29019",
        "idLocalidad":"130480001"
        }
        */
        $v = Validator::make($request->all(), [
            'Nombre' => 'required|max:255',
            'Paterno' => 'required|max:255',
            'Materno' => 'required|max:255',
            'Sexo' => 'required|max:1',
            'FechaNacimiento' => 'required', 
            'Colonia' => 'required',
            'idMunicipio' => 'required',
            
            

            //Validaciones de la tabla
            'idEntidadNacimiento' => 'required',
            'CP' => 'required|max:7',
            'idLocalidad'=>'required',

        ]);
        
		if ($v->fails()){
            if(!isset($parameters['filtered'])){
                $parameters['filtered']=[];
            }
            $response =  ['success'=>false,'results'=>false,
            'filtros'=>$parameters['filtered'],'errors'=>$v->errors(),'data'=>[]];

            return response()->json($response,200);
        }
        $parameters = $request->all();

        $persona_recibida = 
            $parameters['Nombre'].$parameters['Paterno'].$parameters['Materno'].
            $parameters['idMunicipio'].$parameters['Colonia'];
        $persona_recibida = str_replace(" ","",$persona_recibida);
        $res = DB::table('personas')->select(
                'id',
                DB::raw("
                REPLACE(
                CONCAT(
                    Nombre,
                    Paterno,
                    Materno,
                    idMunicipio,
                    Colonia
                ), ' ', '') as NombreCompleto")
        )->get();
        
        $flag=false;
        $id_existente=0;
        for ($i=0; $i < $res->count();  $i++) { 
            if(strcasecmp($persona_recibida, $res[$i]->NombreCompleto) === 0){
                $flag=true;
                $id_existente = $res[$i]->id;
                break;
            }
        }
        if($flag){
            //$response = [$persona_recibida,$res[0],"id_existente"=>$id_existente,"Comparacion"=>$flag];
            if(!isset($parameters['filtered'])){
                $parameters['filtered']=[];
            }
            $persona_existente = Persona::find($id_existente); 
            $response = ['success'=>false,'results'=>false,
            'filtros'=>$parameters['filtered'],'errors'=>'La persona que quizo registrar ya se encuentra registrada.', 'Persona Existente'=>$persona_existente];

            return response()->json($response,200);
        }
        $user = auth()->user();
        $parameters['UserCreated'] = $user->id;
        $parameters['UserUpdated'] = $user->id;
        $persona = Persona::create($parameters);
        if(!isset($parameters['filtered'])){
            $parameters['filtered']=[];
        }
        
        return ['success'=>true,'results'=>true,
            'filtros'=>$parameters['filtered'],'data'=>$persona];
    }
}
