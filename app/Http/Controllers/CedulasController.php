<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use \Illuminate\Database\QueryException;
use DB;
use JWTAuth;
use Illuminate\Contracts\Validation\ValidationException;
use Validator;
use App\Cedula;
use Arr;
use Carbon\Carbon as time;

class CedulasController extends Controller
{
    function getCatalogsCedula(Request $request){
        try {
            $estadoCivi = DB::table("cat_estado_civil")
            ->select("id AS value", "EstadoCivil AS label")
            ->get();

            $entidades = DB::table("cat_entidad")
            ->select("id AS value", "Entidad AS label", "Clave_CURP")
            ->where("id", "<>", 1)
            ->get();

            $parentescosJefe = DB::table("cat_parentesco_jefe_hogar")
            ->select("id AS value", "Parentesco AS label")
            ->get();

            $parentescosTutor = DB::table("cat_parentesco_tutor")
            ->select("id AS value", "Parentesco AS label")
            ->get();

            $situaciones = DB::table("cat_situacion_actual")
            ->select("id AS value", "Situacion AS label")
            ->get();

            $catalogs  = [
                "entidades" => $entidades,
                "cat_parentesco_jefe_hogar" => $parentescosJefe,
                "cat_parentesco_tutor" => $parentescosTutor,
                "cat_situacion_actual" => $situaciones,
                "cat_estado_civil" => $estadoCivi,
            ];


            $response = [
                'success'=>true,
                'results'=>true,
                'data' => $catalogs
            ];
            return  response()->json($response, 200);
        } catch (QueryException $errors) {
            $response = [
                'success'=>false,
                'results'=>false, 
                'total'=>0,
                'errors'=>$errors, 
                'message' =>'Ha ocurrido un error, consulte al administrador'
            ];
            return  response()->json($response, 200);
        }
    }

    function getSolicitudes(Request $request){
        try {
            $v = Validator::make($request->all(), [
                'page' => 'required', 
                'pageSize' => 'required',
            ]); 
            if ($v->fails()){
                $response =  [
                    'success'=>true,
                    'results'=>false,
                    'errors'=>$v->errors()
                ];
                return response()->json($response,200);
            }

            $params = $request->all();
            $solicitudes =  DB::table("cedulas_solicitudes")
                            ->selectRaw("cedulas_solicitudes.*, 
                            entidadesNacimiento.Entidad AS EntidadNacimiento, cat_estado_civil.EstadoCivil, 
                            cat_parentesco_jefe_hogar.Parentesco, cat_parentesco_tutor.Parentesco, 
                            entidadesVive.Entidad AS EntidadVive, 
                            CONCAT_WS(' ', creadores.Nombre, creadores.Paterno, creadores.Materno) AS CreadoPor,
                            CONCAT_WS(' ', editores.Nombre, editores.Paterno, editores.Materno) AS ActualizadoPor,
                            cedulas.id AS idCedula, cedulas.ListaParaEnviar")
                            ->join("cat_entidad AS entidadesNacimiento", "entidadesNacimiento.id", "cedulas_solicitudes.idEntidadNacimiento")
                            ->join("cat_estado_civil", "cat_estado_civil.id", "cedulas_solicitudes.idEstadoCivil")
                            ->join("cat_parentesco_jefe_hogar", "cat_parentesco_jefe_hogar.id", "cedulas_solicitudes.idParentescoJefeHogar")
                            ->leftJoin("cat_parentesco_tutor", "cat_parentesco_tutor.id", "cedulas_solicitudes.idParentescoTutor")
                            ->join("cat_entidad AS entidadesVive", "entidadesVive.id", "cedulas_solicitudes.idEntidadVive")
                            ->join("users AS creadores", "creadores.id", "cedulas_solicitudes.idUsuarioCreo")
                            ->leftJoin("users AS editores", "editores.id", "cedulas_solicitudes.idUsuarioActualizo")
                            ->leftJoin("cedulas", "cedulas.idSolicitud", "cedulas_solicitudes.id");
            $filterQuery = "";
            if(isset($params['filtered']) && count($params['filtered']) > 0){
                foreach($params['filtered'] as $filtro){
                    if($filterQuery != ""){
                        $filterQuery .= " AND ";
                    }
                    $id = $filtro['id'];
                    $value = $filtro['value'];

                    switch(gettype($value)){
                        case "string":
                            $filterQuery .= " $id LIKE '%$value%' ";
                        break;
                        case "array":
                            $colonDividedValue = implode(", ", $value);
                            $filterQuery .= " $id IN ($colonDividedValue) ";
                        default:
                            if($value === -1)
                                $filterQuery .= " $id IS NOT NULL ";
                            else
                                $filterQuery .= " $id = $value ";
                    }
                }
            }

            if($filterQuery != ""){
                $solicitudes->whereRaw($filterQuery);
            }
            
            $solicitudes = $solicitudes->paginate($params["pageSize"]);

            $response = [ 
                "success" => true,
                "results" => true,
                "data" => $solicitudes->items(),
                "total" => $solicitudes->total()
            ];
            return  response()->json($response, 200);
        } catch (QueryException $errors) {
            $response = [
                'success'=>false,
                'results'=>false, 
                'total'=>0,
                'errors'=>$errors, 
                'message' =>'Ha ocurrido un error, consulte al administrador'
            ];
            return  response()->json($response, 200);
        }
    }


    function createSolicitud(Request $request){
        try {
            $v = Validator::make($request->all(), [
                'FechaSolicitud' => 'required', 
                'Nombre' => 'required', 
                'Paterno' => 'required', 
                'Materno' => 'required', 
                'FechaNacimiento' => 'required', 
                'Edad' => 'required', 
                'Sexo' => 'required', 
                'idEntidadNacimiento' => 'required', 
                'CURP' => 'required',  
                'idEstadoCivil' => 'required',
                'idParentescoJefeHogar' => 'required',
                'NumHijos' => 'required',
                'NumHijas' => 'required',
                'Afromexicano' => 'required',
                'idSituacionActual' => 'required',
                'TarjetaImpulso' => 'required',
                'ContactoTarjetaImpulso' => 'required',
                'NecesidadSolicitante' => 'required',
                'CostoNecesidad' => 'required',
                'idEntidadVive' => 'required',
                'MunicipioVive' => 'required',
                'LocalidadVive' => 'required',
                'CPVive' => 'required',
                'ColoniaVive' => 'required',
                'CalleVive' => 'required',
                'NoExtVive' => 'required',
                'NoIntVive' => 'required',
                'Referencias' => 'required',
            ]); 

            if ($v->fails()){
                $response =  [
                    'success'=>true,
                    'results'=>false,
                    'errors'=>$v->errors()
                ];
                return response()->json($response,200);
            }

            $params = $request->all();

            if(
                !isset($params['Celular']) && !isset($params['Telefono']) && 
                !isset($params['Correo']) && !isset($params['TelRecados'])
            ){
                $response =  [
                    'success'=>true,
                    'results'=>false,
                    'errors'=>"Agregue al menos un método de contacto"
                ];
                return response()->json($response,200);
            }

            if($params['Edad'] < 18){
                if(
                    !isset($params['idParentescoTutor']) &&
                    !isset($params['NombreTutor']) && !isset($params['PaternoTutor']) && 
                    !isset($params['MaternoTutor']) && !isset($params['FechaNacimientoTutor']) &&
                    !isset($params['EdadTutor']) && !isset($params['CURPTutor'])
                ){
                    $response =  [
                        'success'=>true,
                        'results'=>false,
                        'errors'=>"Información de tutor incompleta"
                    ];
                    return response()->json($response,200);
                }
            }
            $cedulaModel = new Cedula;
            $user = auth()->user();
            $params["idUsuarioCreo"] = $user->id;
            $params['FechaCreo'] = date("Y-m-d");
            $params['idEstatus'] = 1;
            $id =   DB::table("cedulas_solicitudes")
                    ->insertGetId($params);

            $response = [
                'success'=>true,
                'results'=>true, 
                'message' =>'Solicitud creada con éxito',
                'data' => $id
            ];
    
            return  response()->json($response, 200);
        } catch (Throwable $errors) {
            $response = [
            'success'=>false,
            'results'=>false, 
            'total'=>0,
            'errors'=>$errors, 
            'message' =>'Ha ocurrido un error, consulte al administrador'];

            return  response()->json($response, 200);
        }
    }

    function updateSolicitud(Request $request){
        try {
            $v = Validator::make($request->all(), [
                'id' => 'required',
                'FechaSolicitud' => 'required', 
                'Nombre' => 'required', 
                'Paterno' => 'required', 
                'Materno' => 'required', 
                'FechaNacimiento' => 'required', 
                'Edad' => 'required', 
                'Sexo' => 'required', 
                'idEntidadNacimiento' => 'required', 
                'CURP' => 'required',  
                'idEstadoCivil' => 'required',
                'idParentescoJefeHogar' => 'required',
                'NumHijos' => 'required',
                'NumHijas' => 'required',
                'Afromexicano' => 'required',
                'idSituacionActual' => 'required',
                'TarjetaImpulso' => 'required',
                'ContactoTarjetaImpulso' => 'required',
                'NecesidadSolicitante' => 'required',
                'CostoNecesidad' => 'required',
                'idEntidadVive' => 'required',
                'MunicipioVive' => 'required',
                'LocalidadVive' => 'required',
                'CPVive' => 'required',
                'ColoniaVive' => 'required',
                'CalleVive' => 'required',
                'NoExtVive' => 'required',
                'NoIntVive' => 'required',
                'Referencias' => 'required',
            ]); 

            if ($v->fails()){
                $response =  [
                    'success'=>true,
                    'results'=>false,
                    'errors'=>$v->errors()
                ];
                return response()->json($response,200);
            }

            $params = $request->all();

            $solicitud =    DB::table("cedulas_solicitudes")
                            ->select("cedulas_solicitudes.idEstatus", "cedulas.id AS idCedula", "cedulas.ListaParaEnviar")
                            ->leftJoin("cedulas", "cedulas.idSolicitud", "cedulas_solicitudes.id")
                            ->where("cedulas_solicitudes.id", $params["id"])
                            ->first();
            if($solicitud->idEstatus != 1 || !isset($solicitud->idCedula)){
                $response =  [
                    'success'=>true,
                    'results'=>false,
                    'errors'=>"La solicitud no se puede editar, tiene una cédula activa o ya fue aceptada"
                ];
                return response()->json($response,200);
            }

            if(
                !isset($params['Celular']) && !isset($params['Telefono']) && 
                !isset($params['Correo']) && !isset($params['TelRecados'])
            ){
                $response =  [
                    'success'=>true,
                    'results'=>false,
                    'errors'=>"Agregue al menos un método de contacto"
                ];
                return response()->json($response,200);
            }

            if($params['Edad'] < 18){
                if(
                    !isset($params['idParentescoTutor']) &&
                    !isset($params['NombreTutor']) && !isset($params['PaternoTutor']) && 
                    !isset($params['MaternoTutor']) && !isset($params['FechaNacimientoTutor']) &&
                    !isset($params['EdadTutor']) && !isset($params['CURPTutor'])
                ){
                    $response =  [
                        'success'=>true,
                        'results'=>false,
                        'errors'=>"Información de tutor incompleta"
                    ];
                    return response()->json($response,200);
                }
            }

            $user = auth()->user();
            $id = $params["id"];
            unset($params["id"]);
            $params["idUsuarioActualizo"] = $user->id;
            $params['FechaActualizo'] = date("Y-m-d");
            $params['idEstatus'] = 1;
            DB::table("cedulas_solicitudes")
                    ->where("id", $id)
                    ->update($params);

            $response = [
                'success'=>true,
                'results'=>true, 
                'message' =>'Solicitud actualizada con éxito',
                'data' => []
            ];
    
            return  response()->json($response, 200);
        } catch (\Throwable $errors) {
            $response = [
                'success'=>false,
                'results'=>false, 
                'total'=>0,
                'errors'=>$errors, 
                'message' =>'Ha ocurrido un error, consulte al administrador'];
    
                return  response()->json($response, 200);
        }
    }

    function deleteSolicitud(Request $request){
        try {
            $v = Validator::make($request->all(), [
                'id' => 'required', 
            ]); 
            if ($v->fails()){
                $response =  [
                    'success'=>true,
                    'results'=>false,
                    'errors'=>$v->errors()
                ];
                return response()->json($response,200);
            }

            $params = $request->all();
            $solicitud =    DB::table("cedulas_solicitudes")
                            ->select("cedulas_solicitudes.idEstatus", "cedulas.id AS idCedula", "cedulas.ListaParaEnviar")
                            ->leftJoin("cedulas", "cedulas.idSolicitud", "cedulas_solicitudes.id")
                            ->where("cedulas_solicitudes.id", $params["id"])
                            ->first();
            if($solicitud->idEstatus != 1 || !isset($solicitud->idCedula)){
                $response =  [
                    'success'=>true,
                    'results'=>false,
                    'errors'=>"La solicitud no se puede eliminar, tiene una cédula activa o ya fue aceptada"
                ];
                return response()->json($response,200);
            }

            DB::table("cedulas_solicitudes")
            ->where("id", $params["id"])
            ->delete();
            
            $response = [
                'success'=>true,
                'results'=>true, 
                'message' =>'Solicitud eliminada con éxito',
                'data' => []
            ];
    
            return  response()->json($response, 200);
            
        } catch (\Throwable $errors) {
            $response = [
                'success'=>false,
                'results'=>false, 
                'total'=>0,
                'errors'=>$errors, 
                'message' =>'Ha ocurrido un error, consulte al administrador'];
    
                return  response()->json($response, 200);
        }
    }

    function getCatalogsCedulaCompletos(Request $request){
        try {
            $cat_estado_civil = DB::table("cat_estado_civil")
            ->select("id AS value", "EstadoCivil AS label")
            ->get();

            $entidades = DB::table("cat_entidad")
            ->select("id AS value", "Entidad AS label")
            ->where("id", "<>", 1)
            ->get();

            $cat_parentesco_jefe_hogar = DB::table("cat_parentesco_jefe_hogar")
            ->select("id AS value", "Parentesco AS label")
            ->get();

            $cat_parentesco_tutor = DB::table("cat_parentesco_tutor")
            ->select("id AS value", "Parentesco AS label")
            ->get();

            $cat_situacion_actual = DB::table("cat_situacion_actual")
            ->select("id AS value", "Situacion AS label")
            ->get();

            $cat_actividades = DB::table("cat_actividades")
            ->select("id AS value", "Actividad AS label")
            ->get();

            $cat_codigos_dificultad = DB::table("cat_codigos_dificultad")
            ->select("id AS value", "Grado AS label")
            ->get();

            $cat_enfermedades = DB::table("cat_enfermedades")
            ->select("id AS value", "Enfermedad AS label")
            ->get();

            $cat_grados_educacion = DB::table("cat_grados_educacion")
            ->select("id AS value", "Grado AS label")
            ->get();

            $cat_niveles_educacion = DB::table("cat_niveles_educacion")
            ->select("id AS value", "Nivel AS label")
            ->get();

            $cat_prestaciones = DB::table("cat_prestaciones")
            ->select("id AS value", "Prestacion AS label")
            ->get();

            $cat_situacion_actual = DB::table("cat_situacion_actual")
            ->select("id AS value", "Situacion AS label")
            ->get();

            $cat_tipo_seguro = DB::table("cat_tipo_seguro")
            ->select("id AS value", "Tipo AS label")
            ->get();

            $cat_tipos_agua = DB::table("cat_tipos_agua")
            ->select("id AS value", "Agua AS label")
            ->get();

            $cat_tipos_combustibles = DB::table("cat_tipos_combustibles")
            ->select("id AS value", "Combustible AS label")
            ->get();

            $cat_tipos_drenajes = DB::table("cat_tipos_drenajes")
            ->select("id AS value", "Drenaje AS label")
            ->get();

            $cat_tipos_luz = DB::table("cat_tipos_luz")
            ->select("id AS value", "Luz AS label")
            ->get();

            $cat_tipos_muros = DB::table("cat_tipos_muros")
            ->select("id AS value", "Muro AS label")
            ->get();

            $cat_tipos_pisos = DB::table("cat_tipos_pisos")
            ->select("id AS value", "Piso AS label")
            ->get();

            $cat_tipos_techos = DB::table("cat_tipos_techos")
            ->select("id AS value", "Techo AS label")
            ->get();

            $cat_tipos_viviendas = DB::table("cat_tipos_viviendas")
            ->select("id AS value", "Tipo AS label")
            ->get();

            $cat_periodicidad = DB::table("cat_periodicidad")
            ->select("id AS value", "Periodicidad AS label")
            ->get();

            $archivos_clasificacion = DB::table("cedula_archivos_clasificacion")
            ->select("id AS value", "Clasificacion AS label")
            ->get();

            $catalogs  = [
                "entidades" => $entidades,
                "cat_parentesco_jefe_hogar" => $cat_parentesco_jefe_hogar,
                "cat_parentesco_tutor" => $cat_parentesco_tutor,
                "cat_situacion_actual" => $cat_situacion_actual,
                "cat_estado_civil" => $cat_estado_civil,
                "cat_actividades" => $cat_actividades,
                "cat_codigos_dificultad" => $cat_codigos_dificultad,
                "cat_enfermedades" => $cat_enfermedades,
                "cat_grados_educacion" => $cat_grados_educacion,
                "cat_niveles_educacion" => $cat_niveles_educacion,
                "cat_prestaciones" => $cat_prestaciones,
                "cat_situacion_actual" => $cat_situacion_actual,
                "cat_tipo_seguro" => $cat_tipo_seguro,
                "cat_tipos_combustibles" => $cat_tipos_combustibles,
                "cat_tipos_drenajes" => $cat_tipos_drenajes,
                "cat_tipos_luz" => $cat_tipos_luz,
                "cat_tipos_muros" => $cat_tipos_muros,
                "cat_tipos_pisos" => $cat_tipos_pisos,
                "cat_tipos_techos" => $cat_tipos_techos,
                "cat_tipos_viviendas" => $cat_tipos_viviendas,
                "cat_tipos_agua" => $cat_tipos_agua,
                "cat_periodicidad"=> $cat_periodicidad,
                "archivos_clasificacion" => $archivos_clasificacion
            ];


            $response = [
                'success'=>true,
                'results'=>true,
                'data' => $catalogs
            ];
            return  response()->json($response, 200);
        } catch (\Throwable $errors) {
            $response = [
                'success'=>false,
                'results'=>false, 
                'total'=>0,
                'errors'=>$errors, 
                'message' =>'Ha ocurrido un error, consulte al administrador'];
    
                return  response()->json($response, 200);
        }
    }

    function getClasificacionArchivos(Request $request){
        try {
            $archivos_clasificacion = DB::table("cedula_archivos_clasificacion")
            ->select("id AS value", "Clasificacion AS label")
            ->get();

            $response = [
                'success'=>true,
                'results'=>true,
                'data' => $archivos_clasificacion
            ];
            return  response()->json($response, 200);

        } catch (\Throwable $errors) {
            $response = [
                'success'=>false,
                'results'=>false, 
                'total'=>0,
                'errors'=>$errors, 
                'message' =>'Ha ocurrido un error, consulte al administrador'];
    
                return  response()->json($response, 200);
        }
    }

    function create(Request $request){
        try {
            $v = Validator::make($request->all(), [
                'idSolicitud' => 'required',
                'FechaSolicitud' => 'required', 
                'Nombre' => 'required', 
                'Paterno' => 'required', 
                'Materno' => 'required', 
                'FechaNacimiento' => 'required', 
                'Edad' => 'required', 
                'Sexo' => 'required', 
                'idEntidadNacimiento' => 'required', 
                'CURP' => 'required',  
                'Celular' => 'required',
                'Correo' => 'required',
                'idEstadoCivil' => 'required',
                'idParentescoJefeHogar' => 'required',
                'NumHijos' => 'required',
                'NumHijas' => 'required',
                'Afromexicano' => 'required',
                'idSituacionActual' => 'required',
                'TarjetaImpulso' => 'required',
                'ContactoTarjetaImpulso' => 'required',
                'NecesidadSolicitante' => 'required',
                'CostoNecesidad' => 'required',
                'idEntidadVive' => 'required',
                'MunicipioVive' => 'required',
                'LocalidadVive' => 'required',
                'CPVive' => 'required',
                'AGEBVive' => 'required',	
                'ManzanaVive' => 'required',	
                'TipoAsentamientoVive' => 'required',	
                'ColoniaVive' => 'required',	
                'CalleVive' => 'required',	
                'NoExtVive' => 'required',	
                'NoIntVive' => 'required',	
                'Referencias' => 'required',	
                'TotalHogares' => 'required',	
                'NumeroMujeresHogar' => 'required',	
                'NumeroHombresHogar' => 'required',	
                'PersonasMayoresEdad' => 'required',	
                'PersonasTerceraEdad' => 'required',	
                'PersonaJefaFamilia' => 'required',
                'DificultadMovilidad' => 'required',	
                'DificultadVer' => 'required',	
                'DificultadHablar' => 'required',	
                'DificultadOir' => 'required',	
                'DificultadVestirse' => 'required',	
                'DificultadRecordar' => 'required',	
                'DificultadBrazos' => 'required',	
                'DificultadMental' => 'required',	
                'AsisteEscuela' => 'required',	
                'idNivelEscuela' => 'required',	
                'idGradoEscuela' => 'required',	
                'idActividades' => 'required',
                'IngresoTotalMesPasado' => 'required',	
                'PensionMensual' => 'required',	
                'IngresoOtrosPaises' => 'required',	
                'GastoAlimentos' => 'required',	
                'PeriodicidadAlimentos' => 'required',	
                'GastoVestido' => 'required',	
                'PeriodicidadVestido' => 'required',	
                'GastoEducacion' => 'required',	
                'PeriodicidadEducacion' => 'required',	
                'GastoMedicinas' => 'required',	
                'PeriodicidadMedicinas' => 'required',	
                'GastosConsultas' => 'required',	
                'PeriodicidadConsultas' => 'required',	
                'GastosCombustibles' => 'required',	
                'PeriodicidadCombustibles' => 'required',	
                'GastosServiciosBasicos' => 'required',	
                'PeriodicidadServiciosBasicos' => 'required',	
                'GastosServiciosRecreacion' => 'required',	
                'PeriodicidadServiciosRecreacion' => 'required',	
                'AlimentacionPocoVariada' => 'required',	
                'ComioMenos' => 'required',	
                'DisminucionComida' => 'required',	
                'NoComio' => 'required',	
                'DurmioHambre' => 'required',	
                'DejoComer' => 'required',	
                'PersonasHogar' => 'required',	
                'CuartosHogar' => 'required',	
                'idTipoVivienda' => 'required',	
                'idTipoPiso' => 'required',	
                'idTipoParedes' => 'required',	
                'idTipoTecho' => 'required',	
                'idTipoAgua' => 'required',	
                'idTipoDrenaje' => 'required',	
                'idTipoLuz' => 'required',	
                'idTipoCombustible' => 'required',	
                'Refrigerador' => 'required',	
                'Lavadora' => 'required',	
                'Computadora' => 'required',	
                'Estufa' => 'required',	
                'Calentador' => 'required',	
                'CalentadorSolar' => 'required',	
                'Television' => 'required',	
                'Internet' => 'required',	
                'TieneTelefono' => 'required',	
                'Tinaco' => 'required',	
                'ColoniaSegura' => 'required',	
                'ListaParaEnviar' => 'required',
                'Prestaciones'  => 'required|array',
                'Enfermedades'  => 'required|array',
                'AtencionesMedicas'  => 'required|array',
            ]); 
            if ($v->fails()){
                $response =  [
                    'success'=>true,
                    'results'=>false,
                    'errors'=>$v->errors()
                ];
                return response()->json($response,200);
            }
            $params = $request->all();

            DB::beginTransaction();

            $prestaciones = $params["Prestaciones"];
            $enfermedades = $params["Enfermedades"];
            $atencionesMedicas = $params["AtencionesMedicas"];
            $newClasificacion = isset($params["NewClasificacion"]) ? $params["NewClasificacion"] : [];
            $user = auth()->user();
            $params['idUsuarioCreo'] =  $user->id;
            $params['FechaCreo'] = date("Y-m-d");
            unset($params["Prestaciones"]);
            unset($params["Enfermedades"]);
            unset($params["AtencionesMedicas"]);
            unset($params["NewClasificacion"]);

            $id =   DB::table("cedulas")
                    ->insertGetId($params);

            $this->updateSolicitudFromCedula($params, $user);

            $formatedPrestaciones = [];
            foreach($prestaciones as $prestacion){
                array_push($formatedPrestaciones, [
                    'idCedula' => $id,
                    'idPrestacion' => $prestacion
                ]);
            }
            DB::table("cedulas_prestaciones")
            ->insert($formatedPrestaciones);

            $formatedEnfermedades = [];
            foreach($enfermedades as $enfermedad){
                array_push($formatedEnfermedades, [
                    'idCedula' => $id,
                    'idEnfermedad' => $enfermedad
                ]);
            }
            DB::table("cedulas_enfermedades")
            ->insert($formatedEnfermedades);


            $formatedAtencionesMedicas = [];
            foreach($atencionesMedicas as $atencion){
                array_push($formatedAtencionesMedicas, [
                    'idCedula' => $id,
                    'idAtencionMedica' => $atencion
                ]);
            }
            DB::table("cedulas_atenciones_medicas")
            ->insert($formatedAtencionesMedicas);

            if(isset($request->NewFiles)){
                $this->createCedulaFiles($id, $request->NewFiles, $newClasificacion, $user->id);
            }

            DB::commit();

            $response = [
                "success" => true,
                "results" => true,
                "message" => "Creada con éxito",
                "data" => []
            ];
            return  response()->json($response, 200);
        } catch (\Throwable $errors) {
            DB::rollBack();
            $response = [
                'success'=>false,
                'results'=>false, 
                'total'=>0,
                'errors'=>$errors, 
                'message' =>'Ha ocurrido un error, consulte al administrador'];
    
                return  response()->json($response, 200);
        }
    }

    function getById(Request $request, $id){
        try {
           $cedula =    DB::table("cedulas")
                        ->selectRaw("
                            cedulas.*
                        ")
                        // , 
                        //     entidadesNacimiento.Entidad AS EntidadNacimiento, cat_estado_civil.EstadoCivil, 
                        //     cat_parentesco_jefe_hogar.Parentesco, cat_parentesco_tutor.Parentesco, 
                        //     entidadesVive.Entidad AS EntidadVive,
                        //     cat_niveles_educacion.Nivel, cat_grados_educacion.Grado, cat_actividades.Actividad,
                        //     cat_tipos_viviendas.Tipo, cat_tipos_pisos.Piso, cat_tipos_muros.Muro,
                        //     cat_tipos_techos.Techo, cat_tipos_agua.Agua, cat_tipos_drenajes.Drenaje,
                        //     cat_tipos_luz.Luz, cat_tipos_combustibles.Combustible
                        // ->join("cat_entidad AS entidadesNacimiento", "entidadesNacimiento.id", "cedulas.idEntidadNacimiento")
                        // ->join("cat_estado_civil", "cat_estado_civil.id", "cedulas.idEstadoCivil")
                        // ->join("cat_parentesco_jefe_hogar", "cat_parentesco_jefe_hogar.id", "cedulas.idParentescoJefeHogar")
                        // ->leftJoin("cat_parentesco_tutor", "cat_parentesco_tutor.id", "cedulas.idParentescoTutor")
                        // ->join("cat_entidad AS entidadesVive", "entidadesVive.id", "cedulas.idEntidadVive")
                        // ->leftJoin("cat_niveles_educacion", "cat_niveles_educacion.id", "cedulas.idNivelEscuela")
                        // ->leftJoin("cat_grados_educacion", "cat_grados_educacion.id", "cedulas.idGradoEscuela")
                        // ->leftJoin("cat_actividades", "cat_actividades.id", "cedulas.idActividades")
                        // ->leftJoin("cat_tipos_viviendas", "cat_tipos_viviendas.id", "cedulas.idTipoVivienda")
                        // ->leftJoin("cat_tipos_pisos", "cat_tipos_pisos.id", "cedulas.idTipoPiso")
                        // ->leftJoin("cat_tipos_muros", "cat_tipos_muros.id", "cedulas.idTipoParedes")
                        // ->leftJoin("cat_tipos_techos", "cat_tipos_techos.id", "cedulas.idTipoTecho")
                        // ->leftJoin("cat_tipos_agua", "cat_tipos_agua.id", "cedulas.idTipoAgua")
                        // ->leftJoin("cat_tipos_drenajes", "cat_tipos_drenajes.id", "cedulas.idTipoDrenaje")
                        // ->leftJoin("cat_tipos_luz", "cat_tipos_luz.id", "cedulas.idTipoLuz")
                        // ->leftJoin("cat_tipos_combustibles", "cat_tipos_combustibles.id", "cedulas.idTipoCombustible")
                        ->where("cedulas.id", $id)
                        ->first();

            $prestaciones = DB::table("cedulas_prestaciones")
                            ->select("idPrestacion")
                            ->where("idCedula", $id)
                            ->get();
            
            $enfermedades = DB::table("cedulas_enfermedades")
                            ->select("idEnfermedad")
                            ->where("idCedula", $id)
                            ->get();

            $atencionesMedicas = DB::table("cedulas_atenciones_medicas")
                            ->select("idAtencionMedica")
                            ->where("idCedula", $id)
                            ->get();

            $archivos = DB::table("cedula_archivos")
                        ->select("id", "idClasificacion", "NombreOriginal AS name", "NombreSistema", "Tipo AS type")
                        ->where("idCedula", $id)
                        ->whereRaw("FechaElimino IS NULL")
                        ->get();
            $cedula->Prestaciones = array_map(function($o) { return $o->idPrestacion;}, $prestaciones->toArray());
            $cedula->Enfermedades = array_map(function($o) { return $o->idEnfermedad;}, $enfermedades->toArray());
            $cedula->AtencionesMedicas = array_map(function($o) { return $o->idAtencionMedica;}, $atencionesMedicas->toArray());
            $cedula->Files = $archivos;
            $cedula->ArchivosClasificacion = array_map(function($o) { return $o->idClasificacion;}, $archivos->toArray());

            $response = [
                "success" => true,
                "results" => true,
                "message" => "éxito",
                "data" => $cedula
            ];
            return  response()->json($response, 200);
                        
        } catch (\Throwable $errors) {
            $response = [
                'success'=>false,
                'results'=>false, 
                'total'=>0,
                'errors'=>$errors, 
                'message' =>'Ha ocurrido un error, consulte al administrador'];
    
                return  response()->json($response, 200);
        }
    }

    function getFilesById(Request $request, $id){
        try {
            $archivos = DB::table("cedula_archivos")
                        ->select("id", "idClasificacion", "NombreOriginal AS name", "NombreSistema", "Tipo AS type")
                        ->where("idCedula", $id)
                        ->whereRaw("FechaElimino IS NULL")
                        ->get();
            $archivosClasificacion = array_map(function($o) { return $o->idClasificacion;}, $archivos->toArray());
            $response = [
                "success" => true,
                "results" => true,
                "message" => "éxito",
                "data" => [
                    "Archivos" =>$archivos, 
                    "ArchivosClasificacion"=>$archivosClasificacion
                ]
            ];
            return  response()->json($response, 200);
        } catch (\Throwable $errors) {
            $response = [
                'success'=>false,
                'results'=>false, 
                'total'=>0,
                'errors'=>$errors, 
                'message' =>'Ha ocurrido un error, consulte al administrador'];
    
                return  response()->json($response, 200);
        }
    }

    function update(Request $request){
        try {
            $v = Validator::make($request->all(), [
                'id' => 'required',
                'idSolicitud' => 'required',
                'FechaSolicitud' => 'required', 
                'Nombre' => 'required', 
                'Paterno' => 'required', 
                'Materno' => 'required', 
                'FechaNacimiento' => 'required', 
                'Edad' => 'required', 
                'Sexo' => 'required', 
                'idEntidadNacimiento' => 'required', 
                'CURP' => 'required',  
                'Celular' => 'required',
                'Correo' => 'required',
                'idEstadoCivil' => 'required',
                'idParentescoJefeHogar' => 'required',
                'NumHijos' => 'required',
                'NumHijas' => 'required',
                'Afromexicano' => 'required',
                'idSituacionActual' => 'required',
                'TarjetaImpulso' => 'required',
                'ContactoTarjetaImpulso' => 'required',
                'NecesidadSolicitante' => 'required',
                'CostoNecesidad' => 'required',
                'idEntidadVive' => 'required',
                'MunicipioVive' => 'required',
                'LocalidadVive' => 'required',
                'CPVive' => 'required',
                'AGEBVive' => 'required',	
                'ManzanaVive' => 'required',	
                'TipoAsentamientoVive' => 'required',	
                'ColoniaVive' => 'required',	
                'CalleVive' => 'required',	
                'NoExtVive' => 'required',	
                'NoIntVive' => 'required',	
                'Referencias' => 'required',	
                'TotalHogares' => 'required',	
                'NumeroMujeresHogar' => 'required',	
                'NumeroHombresHogar' => 'required',	
                'PersonasMayoresEdad' => 'required',	
                'PersonasTerceraEdad' => 'required',	
                'PersonaJefaFamilia' => 'required',
                'DificultadMovilidad' => 'required',	
                'DificultadVer' => 'required',	
                'DificultadHablar' => 'required',	
                'DificultadOir' => 'required',	
                'DificultadVestirse' => 'required',	
                'DificultadRecordar' => 'required',	
                'DificultadBrazos' => 'required',	
                'DificultadMental' => 'required',	
                'AsisteEscuela' => 'required',	
                'idNivelEscuela' => 'required',	
                'idGradoEscuela' => 'required',	
                'idActividades' => 'required',
                'IngresoTotalMesPasado' => 'required',	
                'PensionMensual' => 'required',	
                'IngresoOtrosPaises' => 'required',	
                'GastoAlimentos' => 'required',	
                'PeriodicidadAlimentos' => 'required',	
                'GastoVestido' => 'required',	
                'PeriodicidadVestido' => 'required',	
                'GastoEducacion' => 'required',	
                'PeriodicidadEducacion' => 'required',	
                'GastoMedicinas' => 'required',	
                'PeriodicidadMedicinas' => 'required',	
                'GastosConsultas' => 'required',	
                'PeriodicidadConsultas' => 'required',	
                'GastosCombustibles' => 'required',	
                'PeriodicidadCombustibles' => 'required',	
                'GastosServiciosBasicos' => 'required',	
                'PeriodicidadServiciosBasicos' => 'required',	
                'GastosServiciosRecreacion' => 'required',	
                'PeriodicidadServiciosRecreacion' => 'required',	
                'AlimentacionPocoVariada' => 'required',	
                'ComioMenos' => 'required',	
                'DisminucionComida' => 'required',	
                'NoComio' => 'required',	
                'DurmioHambre' => 'required',	
                'DejoComer' => 'required',	
                'PersonasHogar' => 'required',	
                'CuartosHogar' => 'required',	
                'idTipoVivienda' => 'required',	
                'idTipoPiso' => 'required',	
                'idTipoParedes' => 'required',	
                'idTipoTecho' => 'required',	
                'idTipoAgua' => 'required',	
                'idTipoDrenaje' => 'required',	
                'idTipoLuz' => 'required',	
                'idTipoCombustible' => 'required',	
                'Refrigerador' => 'required',	
                'Lavadora' => 'required',	
                'Computadora' => 'required',	
                'Estufa' => 'required',	
                'Calentador' => 'required',	
                'CalentadorSolar' => 'required',	
                'Television' => 'required',	
                'Internet' => 'required',	
                'TieneTelefono' => 'required',	
                'Tinaco' => 'required',	
                'ColoniaSegura' => 'required',	
                'ListaParaEnviar' => 'required',
                'Prestaciones'  => 'required|array',
                'Enfermedades'  => 'required|array',
                'AtencionesMedicas'  => 'required|array',
            ]); 
            if ($v->fails()){
                $response =  [
                    'success'=>true,
                    'results'=>false,
                    'errors'=>$v->errors()
                ];
                return response()->json($response,200);
            }
            $params = $request->all();
            $user = auth()->user();
            $id = $params["id"];
            unset($params["id"]);

            $cedula =   DB::table("cedulas")
                        ->where("id", $id)
                        ->first();

            if($cedula == null){
                $response =  [
                    'success'=>true,
                    'results'=>false,
                    'errors'=>"La cédula no fue encontrada"
                ];
                return response()->json($response,200); 
            }

            if($cedula->ListaParaEnviar && $user->id != 52){
                $response =  [
                    'success'=>true,
                    'results'=>false,
                    'errors'=>"La cédula tiene estatus 'Lista para enviarse', no se puede editar"
                ];
                return response()->json($response,200);
            }

            DB::beginTransaction();
            $prestaciones = $params["Prestaciones"];
            $enfermedades = $params["Enfermedades"];
            $atencionesMedicas = $params["AtencionesMedicas"];
            $oldClasificacion = isset($params["OldClasificacion"]) ? $params["OldClasificacion"] : [];
            $newClasificacion = isset($params["NewClasificacion"]) ? $params["NewClasificacion"]: [] ;
            $params['idUsuarioActualizo'] =  $user->id;
            $params['FechaActualizo'] = date("Y-m-d");
            unset($params["Prestaciones"]);
            unset($params["Enfermedades"]);
            unset($params["AtencionesMedicas"]);
            unset($params["OldFiles"]);
            unset($params["OldClasificacion"]);
            unset($params["NewFiles"]);
            unset($params["NewClasificacion"]);

            DB::table("cedulas")
            ->where("id", $id)
            ->update($params);

            DB::table("cedulas_prestaciones")
            ->where("idCedula", $id)
            ->delete();
            $formatedPrestaciones = [];
            foreach($prestaciones as $prestacion){
                array_push($formatedPrestaciones, [
                    'idCedula' => $id,
                    'idPrestacion' => $prestacion
                ]);
            }
            DB::table("cedulas_prestaciones")
            ->insert($formatedPrestaciones);

            DB::table("cedulas_enfermedades")
            ->where("idCedula", $id)
            ->delete();
            $formatedEnfermedades = [];
            foreach($enfermedades as $enfermedad){
                array_push($formatedEnfermedades, [
                    'idCedula' => $id,
                    'idEnfermedad' => $enfermedad
                ]);
            }
            DB::table("cedulas_enfermedades")
            ->insert($formatedEnfermedades);

            DB::table("cedulas_atenciones_medicas")
            ->where("idCedula", $id)
            ->delete();
            $formatedAtencionesMedicas = [];
            foreach($atencionesMedicas as $atencion){
                array_push($formatedAtencionesMedicas, [
                    'idCedula' => $id,
                    'idAtencionMedica' => $atencion
                ]);
            }
            DB::table("cedulas_atenciones_medicas")
            ->insert($formatedAtencionesMedicas);

            $oldFiles = DB::table("cedula_archivos")
            ->select("id", "idClasificacion")
            ->where("idCedula", $id)
            ->whereRaw("FechaElimino IS NULL")
            ->get();
            $oldFilesIds = array_map(function($o) { return $o->id;}, $oldFiles->toArray());
            if(isset($request->NewFiles)){
                $this->createCedulaFiles($id, $request->NewFiles, $newClasificacion, $user->id);
            }
            if(isset($request->OldFiles)){
               $oldFilesIds = $this->updateCedulaFiles($id, $request->OldFiles, $oldClasificacion, $user->id, $oldFilesIds, $oldFiles);
            }

            if(count($oldFilesIds) > 0){
                DB::table("cedula_archivos")
                ->whereIn("id", $oldFilesIds)
                ->update([
                    'idUsuarioElimino'=>$user->id,
                    'FechaElimino'=>date("Y-m-d H:i:s")
                ]);
            }

            DB::commit();

            $response = [
                "success" => true,
                "results" => true,
                "message" => "Editada con éxito",
                "data" => []
            ];
            return  response()->json($response, 200);
        } catch (QueryException $errors) {
            DB::rollBack();
            $response = [
                'success'=>false,
                'results'=>false, 
                'total'=>0,
                'errors'=>$errors, 
                'message' =>'Ha ocurrido un error, consulte al administrador'];
    
                return  response()->json($response, 200);
        }
    }

    function updateArchivosCedula(Request $request){
        try {
            $v = Validator::make($request->all(), [
                'id' => 'required',
            ]);
            if ($v->fails()){
                $response =  [
                    'success'=>true,
                    'results'=>false,
                    'errors'=>$v->errors()
                ];
                return response()->json($response,200);
            }
            $params = $request->all();
            $oldClasificacion = isset($params["OldClasificacion"]) ? $params["OldClasificacion"] : [];
            $newClasificacion = isset($params["NewClasificacion"]) ? $params["NewClasificacion"] : [];
            $id = $params["id"];
            $user = auth()->user();

            DB::beginTransaction();
            $oldFiles = DB::table("cedula_archivos")
            ->select("id", "idClasificacion")
            ->where("idCedula", $id)
            ->whereRaw("FechaElimino IS NULL")
            ->get();
            $oldFilesIds = array_map(function($o) { return $o->id;}, $oldFiles->toArray());
            if(isset($request->NewFiles)){
                $this->createCedulaFiles($id, $request->NewFiles, $newClasificacion, $user->id);
            }
            if(isset($request->OldFiles)){
               $oldFilesIds = $this->updateCedulaFiles($id, $request->OldFiles, $oldClasificacion, $user->id, $oldFilesIds, $oldFiles);
            }

            if(count($oldFilesIds) > 0){
                DB::table("cedula_archivos")
                ->whereIn("id", $oldFilesIds)
                ->update([
                    'idUsuarioElimino'=>$user->id,
                    'FechaElimino'=>date("Y-m-d H:i:s")
                ]);
            }
            DB::commit();
            $response = [
                "success" => true,
                "results" => true,
                "message" => "Editada con éxito",
                "data" => []
            ];
            return  response()->json($response, 200);
        } catch (QueryException $errors) {
            DB::rollBack();
            $response = [
                'success'=>false,
                'results'=>false, 
                'total'=>0,
                'errors'=>$errors, 
                'message' =>'Ha ocurrido un error, consulte al administrador'];
    
                return  response()->json($response, 200);
        }
    }

    function delete(Request $request){
        try {
            $v = Validator::make($request->all(), [
                'id' => 'required',
            ]); 
            if ($v->fails()){
                $response =  [
                    'success'=>true,
                    'results'=>false,
                    'errors'=>$v->errors()
                ];
                return response()->json($response,200);
            }
            $params = $request->all();
            $user = auth()->user();
            $id = $params['id'];

            $cedula =   DB::table("cedulas")
            ->where("id", $id)
            ->first();

            if($cedula == null){
                $response =  [
                    'success'=>true,
                    'results'=>false,
                    'errors'=>"La cédula no fue encontrada"
                ];
                return response()->json($response,200); 
            }

            if($cedula->ListaParaEnviar && $user->id != 52){
                $response =  [
                    'success'=>true,
                    'results'=>false,
                    'errors'=>"La cédula tiene estatus 'Lista para enviarse', no se puede editar"
                ];
                return response()->json($response,200);
            }

            DB::beginTransaction();

            DB::table("cedulas_prestaciones")
            ->where("idCedula", $id)
            ->delete();

            DB::table("cedulas_enfermedades")
            ->where("idCedula", $id)
            ->delete();

            DB::table("cedulas_atenciones_medicas")
            ->where("idCedula", $id)
            ->delete();

            DB::table("cedulas")
            ->where("id", $id)
            ->delete();

            DB::commit();

            $response = [
                "success" => true,
                "results" => true,
                "message" => "Eliminada con éxito",
                "data" => []
            ];
            return  response()->json($response, 200);

        } catch (\Throwable $errors) {
            dd($errors);
            DB::rollBack();
            $response = [
                'success'=>false,
                'results'=>false, 
                'total'=>0,
                'errors'=>$errors, 
                'message' =>'Ha ocurrido un error, consulte al administrador'];
    
                return  response()->json($response, 200);
        }
    }

    private function updateSolicitudFromCedula($cedula, $user){
            $params = [
                "FechaSolicitud"=>$cedula->FechaSolicitud,
                "FolioTarjetaImpulso"=>$cedula->FolioTarjetaImpulso,
                "Nombre"=>$cedula->Nombre,
                "Paterno"=>$cedula->Paterno,
                "Materno"=>$cedula->Materno,
                "FechaNacimiento"=>$cedula->FechaNacimiento,
                "Edad"=>$cedula->Edad,
                "Sexo"=>$cedula->Sexo,
                "idEntidadNacimiento"=>$cedula->idEntidadNacimiento,
                "CURP"=>$cedula->CURP,
                "RFC"=>$cedula->RFC ? $cedula->RFC : null,
                "idEstadoCivil"=>$cedula->idEstadoCivil,
                "idParentescoJefeHogar"=>$cedula->idParentescoJefeHogar,
                "NumHijos"=>$cedula->NumHijos,
                "NumHijas"=>$cedula->NumHijas,
                "ComunidadIndigena"=>$cedula->ComunidadIndigena ? $cedula->ComunidadIndigena : null,
                "Dialecto"=>$cedula->Dialecto ? $cedula->Dialecto : null,
                "Afromexicano"=>$cedula->Afromexicano,
                "idSituacionActual"=>$cedula->idSituacionActual,
                "TarjetaImpulso"=>$cedula->TarjetaImpulso,
                "ContactoTarjetaImpulso"=>$cedula->ContactoTarjetaImpulso,
                "Celular"=>$cedula->Celular,
                "Telefono"=>$cedula->Telefono ? $cedula->Telefono : null,
                "TelRecados"=>$cedula->TelRecados ? $cedula->TelRecados : null,
                "Correo"=>$cedula->Correo,
                "idParentescoTutor"=>$cedula->idParentescoTutor ? $cedula->idParentescoTutor : null,
                "NombreTutor"=>$cedula->NombreTutor ? $cedula->NombreTutor : null,
                "PaternoTutor"=>$cedula->PaternoTutor ? $cedula->PaternoTutor : null,
                "MaternoTutor"=>$cedula->MaternoTutor ? $cedula->MaternoTutor : null,
                "FechaNacimientoTutor"=>$cedula->FechaNacimientoTutor ? $cedula->FechaNacimientoTutor : null,
                "EdadTutor"=>$cedula->EdadTutor ? $cedula->EdadTutor : null,
                "SexoTutor"=>$cedula->SexoTutor ? $cedula->SexoTutor : null,
                "idEntidadNacimientoTutor"=>$cedula->idEntidadNacimientoTutor ? $cedula->idEntidadNacimientoTutor : null,
                "CURPTutor"=>$cedula->CURPTutor ? $cedula->CURPTutor : null,
                "TelefonoTutor"=>$cedula->TelefonoTutor ? $cedula->TelefonoTutor : null,
                "CorreoTutor"=>$cedula->CorreoTutor ? $cedula->CorreoTutor : null,
                "NecesidadSolicitante"=>$cedula->NecesidadSolicitante,
                "CostoNecesidad"=>$cedula->CostoNecesidad,
                "idEntidadVive"=>$cedula->idEntidadVive,
                "MunicipioVive"=>$cedula->MunicipioVive,
                "LocalidadVive"=>$cedula->LocalidadVive,
                "CPVive"=>$cedula->CPVive,
                "ColoniaVive"=>$cedula->ColoniaVive,
                "CalleVive"=>$cedula->CalleVive,
                "NoExtVive"=>$cedula->NoExtVive,
                "NoIntVive"=>$cedula->NoIntVive,
                "Referencias"=>$cedula->Referencias,
                "idUsuarioActualizo"=>$user->id,
                "FechaActualizo"=> date("Y-m-d")
            ];

            DB::table("cedulas_solicitudes")
            ->where("id", $cedula->idSolicitud)
            ->update($params);
    }

    private function getFileType($extension){
        if(in_array($extension, ["png", "jpg", "jpeg"]))
            return "image";
        if(in_array($extension, ["xlsx", "xls", "numbers"]))
            return "sheet";
        if(in_array($extension, ["doc", "docx"]))
            return "document";
        if($extension == "pdf")
            return "pdf";
        return "other";
    }

    private function createCedulaFiles($id, $files, $clasificationArray, $userId){
        foreach($files as $key=>$file){
            $originalName = $file->getClientOriginalName();
            $extension = explode('.', $originalName);
            $extension = $extension[count($extension) - 1];
            $uniqueName = uniqid().".".$extension;
            $size = $file->getSize();
            $clasification = $clasificationArray[$key];
            $fileObject = [
                "idCedula"=>intval($id),
                "idClasificacion"=>intval($clasification),
                "NombreOriginal"=>$originalName,
                "NombreSistema"=>$uniqueName,
                "Extension"=>$extension,
                "Tipo"=>$this->getFileType($extension),
                "Tamanio"=>$size,
                "idUsuarioCreo"=>$userId,
                "FechaCreo"=>date("Y-m-d H:i:s")
            ];
            $file->move("subidos", $uniqueName);
            DB::table("cedula_archivos")
            ->insert($fileObject);
        }
    }

    private function updateCedulaFiles($id, $files, $clasificationArray, $userId, $oldFilesIds, $oldFiles){
        foreach($files as $key=>$file){
            $fileAux = json_decode($file);
            $encontrado = array_search($fileAux->id, $oldFilesIds);
            if($encontrado !== false){
                if($oldFiles[$encontrado]->idClasificacion != $clasificationArray[$key]){
                    DB::table("cedula_archivos")
                    ->where("id", $fileAux->id)
                    ->update([
                        "idClasificacion"=>$clasificationArray[$key],
                        "idUsuarioActualizo"=>$userId,
                        "FechaActualizo"=>date("Y-m-d H:i:s")
                    ]);
                }
                unset($oldFilesIds[$encontrado]);
            }
        }
        return $oldFilesIds;
    }
}
