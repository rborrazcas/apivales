<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Database\QueryException;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use Illuminate\Contracts\Validation\ValidationException;

use App\Cedula;
use GuzzleHttp\Client;
use App\VNegociosFiltros;
use Carbon\Carbon as time;

use DB;
use Arr;
use File;
use Zipper;
use JWTAuth;
use Validator;
use HTTP_Request2;

class DiagnosticoController extends Controller
{
    function getPermisos()
    {
        $user = auth()->user();
        $permisos = DB::table('users_menus')
            ->where(['idUser' => $user->id, 'idMenu' => '16'])
            ->get()
            ->first();
        return $permisos;
    }

    function getEstatusGlobal(Request $request)
    {
        $parameters = $request->all();
        $user = auth()->user();

        $permisos = $this->getPermisos();

        $seguimiento = $permisos->Seguimiento;
        $viewall = $permisos->ViewAll;
        $procedimiento = '';

        if ($viewall < 1 && $seguimiento < 1) {
            $usuarioApp = DB::table('users_aplicativo_web')
                ->select('UserName')
                ->where('idUser', $user->id)
                ->get()
                ->first();
            $procedimiento =
                "call getEstatusGlobalVentanillaDiagnostico('" .
                $usuarioApp->UserName .
                "','" .
                $user->id .
                "')";
        } elseif ($viewall < 1) {
            $idUserOwner = DB::table('users_aplicativo_web')
                ->selectRaw('idUserOwner,Region')
                ->where('idUser', $user->id)
                ->get()
                ->first();
            $procedimiento =
                " call getEstatusGlobalVentanillaDiagnosticoRegional('" .
                $idUserOwner->idUserOwner .
                "')";
        }

        if ($procedimiento === '') {
            $procedimiento =
                'call getEstatusGlobalVentanillaDiagnosticoGeneral';
        }

        try {
            $res = DB::select($procedimiento);
            return ['success' => true, 'results' => true, 'data' => $res];
        } catch (QueryException $e) {
            $errors = [
                'Clave' => '01',
            ];
            $response = [
                'success' => true,
                'results' => false,
                'total' => 0,
                'errors' => $e,
                'message' => 'Campo de consulta incorrecto',
            ];

            return response()->json($response, 200);
        }
    }

    function getCedulas(Request $request)
    {
        try {
            $v = Validator::make($request->all(), [
                'page' => 'required',
                'pageSize' => 'required',
                'programa' => 'required',
            ]);
            if ($v->fails()) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' => $v->errors(),
                ];
                return response()->json($response, 200);
            }

            $params = $request->all();
            $user = auth()->user();

            $userId = JWTAuth::parseToken()->toUser()->id;

            DB::table('users_filtros')
                ->where('UserCreated', $userId)
                ->where('api', 'getDiagnosticoVentanilla')
                ->delete();

            $parameters_serializado = serialize($params);

            //Insertamos los filtros
            DB::table('users_filtros')->insert([
                'UserCreated' => $userId,
                'Api' => 'getDiagnosticoVentanilla',
                'Consulta' => $parameters_serializado,
                'created_at' => date('Y-m-d h-m-s'),
            ]);

            $tableCedulas = 'diagnostico_cedula';
            $permisos = $this->getPermisos();
            $seguimiento = $permisos->Seguimiento;
            $viewall = $permisos->ViewAll;
            $filtroCapturo = '';

            if ($viewall < 1 && $seguimiento < 1) {
                $usuarioApp = DB::table('users_aplicativo_web')
                    ->where('idUser', $user->id)
                    ->get()
                    ->first();
                $filtroCapturo =
                    '(' .
                    $tableCedulas .
                    ".idUsuarioCreo = '" .
                    $user->id .
                    "' OR " .
                    $tableCedulas .
                    ".idUsuarioActualizo = '" .
                    $user->id .
                    "' OR " .
                    $tableCedulas .
                    ".UsuarioAplicativo = '" .
                    $usuarioApp->UserName .
                    "')";
            } elseif ($viewall < 1) {
                $idUserOwner = DB::table('users_aplicativo_web')
                    ->selectRaw('idUserOwner')
                    ->where('idUser', $user->id)
                    ->get()
                    ->first();

                $filtroCapturo =
                    '(' .
                    $tableCedulas .
                    '.idUsuarioCreo IN (' .
                    'SELECT idUser FROM users_aplicativo_web WHERE idUserOwner = ' .
                    $idUserOwner->idUserOwner .
                    ') OR ' .
                    $tableCedulas .
                    '.UsuarioAplicativo IN (' .
                    'SELECT UserName FROM users_aplicativo_web WHERE idUserOwner = ' .
                    $idUserOwner->idUserOwner .
                    ')' .
                    ')';
            }

            $solicitudes = DB::table('diagnostico_cedula')
                ->selectRaw(
                    'diagnostico_cedula.*,' .
                        ' entidadesNacimiento.Entidad AS EntidadNacimiento, ' .
                        ' cat_estado_civil.EstadoCivil, ' .
                        ' cat_parentesco_jefe_hogar.Parentesco, ' .
                        ' cat_parentesco_tutor.Parentesco, ' .
                        ' entidadesVive.Entidad AS EntidadVive, ' .
                        ' m.Region AS RegionM, ' .
                        'CASE ' .
                        'WHEN ' .
                        'diagnostico_cedula.idUsuarioCreo = 1312 ' .
                        'THEN ' .
                        'ap.Nombre ' .
                        'ELSE ' .
                        "CONCAT_WS( ' ', creadores.Nombre, creadores.Paterno, creadores.Materno ) " .
                        'END AS CreadoPor, ' .
                        " CONCAT_WS(' ', editores.Nombre, editores.Paterno, editores.Materno) AS ActualizadoPor"
                )
                ->join(
                    'cat_entidad AS entidadesNacimiento',
                    'entidadesNacimiento.id',
                    'diagnostico_cedula.idEntidadNacimiento'
                )
                ->join(
                    'cat_estado_civil',
                    'cat_estado_civil.id',
                    'diagnostico_cedula.idEstadoCivil'
                )
                ->join(
                    'cat_parentesco_jefe_hogar',
                    'cat_parentesco_jefe_hogar.id',
                    'diagnostico_cedula.idParentescoJefeHogar'
                )
                ->leftJoin(
                    'cat_parentesco_tutor',
                    'cat_parentesco_tutor.id',
                    'diagnostico_cedula.idParentescoTutor'
                )
                ->join(
                    'cat_entidad AS entidadesVive',
                    'entidadesVive.id',
                    'diagnostico_cedula.idEntidadVive'
                )
                ->join(
                    'users AS creadores',
                    'creadores.id',
                    'diagnostico_cedula.idUsuarioCreo'
                )
                ->leftJoin(
                    'users AS editores',
                    'editores.id',
                    'diagnostico_cedula.idUsuarioActualizo'
                )
                ->leftJoin(
                    'et_cat_municipio as m',
                    'm.Nombre',
                    'diagnostico_cedula.MunicipioVive'
                )
                ->leftJoin(
                    'users_aplicativo_web as ap',
                    'ap.UserName',
                    'diagnostico_cedula.UsuarioAplicativo'
                )
                ->whereNull('diagnostico_cedula.FechaElimino');

            $filterQuery = '';
            $municipioRegion = [];
            $mun = [];

            if (isset($params['filtered']) && count($params['filtered']) > 0) {
                foreach ($params['filtered'] as $filtro) {
                    if ($filterQuery != '') {
                        $filterQuery .= ' AND ';
                    }

                    $id = $filtro['id'];
                    $value = $filtro['value'];

                    if ($id == '.FechaSolicitud') {
                        $timestamp = strtotime($value);
                        $value = date('Y-m-d', $timestamp);
                    }

                    if ($id == '.MunicipioVive') {
                        foreach ($value as $m) {
                            $mun[] = "'" . $m . "'";
                        }
                        $value = $mun;
                    }

                    if ($id == 'region') {
                        $municipios = DB::table('et_cat_municipio')
                            ->select('Nombre')
                            ->whereIN('SubRegion', $value)
                            ->get();
                        foreach ($municipios as $m) {
                            $municipioRegion[] = "'" . $m->Nombre . "'";
                        }
                        $id = '.MunicipioVive';
                        $value = $municipioRegion;
                    }

                    $id = 'diagnostico_cedula' . $id;

                    switch (gettype($value)) {
                        case 'string':
                            $filterQuery .= " $id LIKE '%$value%' ";
                            break;
                        case 'array':
                            $colonDividedValue = implode(', ', $value);
                            $filterQuery .= " $id IN ($colonDividedValue) ";
                            break;
                        default:
                            if ($value === -1) {
                                $filterQuery .= " $id IS NOT NULL ";
                            } else {
                                $filterQuery .= " $id = $value ";
                            }
                    }
                }
            }

            if ($filterQuery != '') {
                $solicitudes->whereRaw($filterQuery);
            }

            if ($filtroCapturo !== '') {
                $solicitudes->whereRaw($filtroCapturo);
            }

            // dd(
            //     str_replace_array(
            //         '?',
            //         $solicitudes->getBindings(),
            //         $solicitudes->toSql()
            //     )
            // );
            $page = $params['page'];
            $pageSize = $params['pageSize'];

            $startIndex = $page * $pageSize;

            $total = $solicitudes->count();
            $solicitudes = $solicitudes
                ->offset($startIndex)
                ->take($pageSize)
                ->get();

            $filtro_usuario = VNegociosFiltros::where('idUser', '=', $user->id)
                ->where('api', '=', 'getDiagnosticoVentanilla')
                ->first();

            if ($filtro_usuario) {
                $filtro_usuario->parameters = $parameters_serializado;
                $filtro_usuario->updated_at = time::now();
                $filtro_usuario->update();
            } else {
                $objeto_nuevo = new VNegociosFiltros();
                $objeto_nuevo->api = 'getDiagnosticoVentanilla';
                $objeto_nuevo->idUser = $user->id;
                $objeto_nuevo->parameters = $parameters_serializado;
                $objeto_nuevo->save();
            }

            $array_res = [];

            if ($total == 0) {
                return [
                    'success' => true,
                    'results' => true,
                    'total' => $total,
                    'filtros' => $params['filtered'],
                    'data' => $array_res,
                ];
            }

            $temp = [];
            foreach ($solicitudes as $data) {
                $temp = [
                    'id' => $data->id,
                    'Folio' => $data->Folio,
                    'FechaSolicitud' => $data->FechaSolicitud,
                    'FolioTarjetaImpulso' => $data->FolioTarjetaImpulso,
                    'Nombre' => $data->Nombre,
                    'Paterno' => $data->Paterno,
                    'Materno' => $data->Materno,
                    'FechaNacimiento' => $data->FechaNacimiento,
                    'Edad' => $data->Edad,
                    'Sexo' => $data->Sexo,
                    'idEntidadNacimiento' => $data->idEntidadNacimiento,
                    'CURP' => $data->CURP,
                    'RFC' => $data->RFC,
                    'idEstadoCivil' => $data->idEstadoCivil,
                    'idParentescoJefeHogar' => $data->idParentescoJefeHogar,
                    'NumHijos' => $data->NumHijos,
                    'NumHijas' => $data->NumHijas,
                    'ComunidadIndigena' => $data->ComunidadIndigena,
                    'Dialecto' => $data->Dialecto,
                    'Afromexicano' => $data->Afromexicano,
                    'idSituacionActual' => $data->idSituacionActual,
                    'TarjetaImpulso' => $data->TarjetaImpulso,
                    'ContactoTarjetaImpulso' => $data->ContactoTarjetaImpulso,
                    'Celular' => $data->Celular,
                    'Correo' => $data->Correo,
                    'Telefono' => $data->Telefono,
                    'TelRecados' => $data->TelRecados,
                    'idParentescoTutor' => $data->idParentescoTutor,
                    'NombreTutor' => $data->NombreTutor,
                    'PaternoTutor' => $data->PaternoTutor,
                    'MaternoTutor' => $data->MaternoTutor,
                    'FechaNacimientoTutor' => $data->FechaNacimientoTutor,
                    'EdadTutor' => $data->EdadTutor,
                    'SexoTutor' => $data->SexoTutor,
                    'idEntidadNacimientoTutor' =>
                        $data->idEntidadNacimientoTutor,
                    'CURPTutor' => $data->CURPTutor,
                    'TelefonoTutor' => $data->TelefonoTutor,
                    'CorreoTutor' => $data->CorreoTutor,
                    'NecesidadSolicitante' => $data->NecesidadSolicitante,
                    'CostoNecesidad' => $data->CostoNecesidad,
                    'idEntidadVive' => $data->idEntidadVive,
                    'MunicipioVive' => $data->MunicipioVive,
                    'LocalidadVive' => $data->LocalidadVive,
                    'CPVive' => $data->CPVive,
                    'AGEBVive' => $data->AGEBVive,
                    'ManzanaVive' => $data->ManzanaVive,
                    'TipoAsentamientoVive' => $data->TipoAsentamientoVive,
                    'ColoniaVive' => $data->ColoniaVive,
                    'CalleVive' => $data->CalleVive,
                    'NoExtVive' => $data->NoExtVive,
                    'NoIntVive' => $data->NoIntVive,
                    'Referencias' => $data->Referencias,
                    'Latitud' => $data->Latitud,
                    'Longitud' => $data->Longitud,
                    'TotalHogares' => $data->TotalHogares,
                    'NumeroMujeresHogar' => $data->NumeroMujeresHogar,
                    'NumeroHombresHogar' => $data->NumeroHombresHogar,
                    'PersonasMayoresEdad' => $data->PersonasMayoresEdad,
                    'PersonasTerceraEdad' => $data->PersonasTerceraEdad,
                    'PersonaJefaFamilia' => $data->PersonaJefaFamilia,
                    'DificultadMovilidad' => $data->DificultadMovilidad,
                    'DificultadVer' => $data->DificultadVer,
                    'DificultadHablar' => $data->DificultadHablar,
                    'DificultadOir' => $data->DificultadOir,
                    'DificultadVestirse' => $data->DificultadVestirse,
                    'DificultadRecordar' => $data->DificultadRecordar,
                    'DificultadBrazos' => $data->DificultadBrazos,
                    'DificultadMental' => $data->DificultadMental,
                    'AsisteEscuela' => $data->AsisteEscuela,
                    'idNivelEscuela' => $data->idNivelEscuela,
                    'idGradoEscuela' => $data->idGradoEscuela,
                    'idActividades' => $data->idActividades,
                    'IngresoTotalMesPasado' => $data->IngresoTotalMesPasado,
                    'PensionMensual' => $data->PensionMensual,
                    'IngresoOtrosPaises' => $data->IngresoOtrosPaises,
                    'GastoAlimentos' => $data->GastoAlimentos,
                    'PeriodicidadAlimentos' => $data->PeriodicidadAlimentos,
                    'GastoVestido' => $data->GastoVestido,
                    'PeriodicidadVestido' => $data->PeriodicidadVestido,
                    'GastoEducacion' => $data->GastoEducacion,
                    'PeriodicidadEducacion' => $data->PeriodicidadEducacion,
                    'GastoMedicinas' => $data->GastoMedicinas,
                    'PeriodicidadMedicinas' => $data->PeriodicidadMedicinas,
                    'GastosConsultas' => $data->GastosConsultas,
                    'PeriodicidadConsultas' => $data->PeriodicidadConsultas,
                    'GastosCombustibles' => $data->GastosCombustibles,
                    'PeriodicidadCombustibles' =>
                        $data->PeriodicidadCombustibles,
                    'GastosServiciosBasicos' => $data->GastosServiciosBasicos,
                    'PeriodicidadServiciosBasicos' =>
                        $data->PeriodicidadServiciosBasicos,
                    'GastosServiciosRecreacion' =>
                        $data->GastosServiciosRecreacion,
                    'PeriodicidadServiciosRecreacion' =>
                        $data->PeriodicidadServiciosRecreacion,
                    'AlimentacionPocoVariada' => $data->AlimentacionPocoVariada,
                    'ComioMenos' => $data->ComioMenos,
                    'DisminucionComida' => $data->DisminucionComida,
                    'NoComio' => $data->NoComio,
                    'DurmioHambre' => $data->DurmioHambre,
                    'DejoComer' => $data->DejoComer,
                    'PersonasHogar' => $data->PersonasHogar,
                    'CuartosHogar' => $data->CuartosHogar,
                    'idTipoVivienda' => $data->idTipoVivienda,
                    'idTipoPiso' => $data->idTipoPiso,
                    'idTipoParedes' => $data->idTipoParedes,
                    'idTipoTecho' => $data->idTipoTecho,
                    'idTipoAgua' => $data->idTipoAgua,
                    'idTipoDrenaje' => $data->idTipoDrenaje,
                    'idTipoLuz' => $data->idTipoLuz,
                    'idTipoCombustible' => $data->idTipoCombustible,
                    'Refrigerador' => $data->Refrigerador,
                    'Lavadora' => $data->Lavadora,
                    'Computadora' => $data->Computadora,
                    'Estufa' => $data->Estufa,
                    'Calentador' => $data->Calentador,
                    'CalentadorSolar' => $data->CalentadorSolar,
                    'Television' => $data->Television,
                    'Internet' => $data->Internet,
                    'TieneTelefono' => $data->TieneTelefono,
                    'Tinaco' => $data->Tinaco,
                    'ColoniaSegura' => $data->ColoniaSegura,
                    'idEstatus' => $data->idEstatus,
                    'UsuarioAplicativo' => $data->UsuarioAplicativo,
                    'Enlace' => $data->Enlace,
                    'ListaParaEnviar' => $data->ListaParaEnviar,
                    'idUsuarioCreo' => $data->idUsuarioCreo,
                    'FechaCreo' => $data->FechaCreo,
                    'idUsuarioActualizo' => $data->idUsuarioActualizo,
                    'FechaActualizo' => $data->FechaActualizo,
                    'ListaParaEnviar' => $data->ListaParaEnviar,
                    'idUsuarioElimino' => $data->idUsuarioElimino,
                    'FechaElimino' => $data->FechaElimino,
                    'Region' => $data->RegionM,
                    'EntidadNacimiento' => $data->EntidadNacimiento,
                    'EstadoCivil' => $data->EstadoCivil,
                    'Parentesco' => $data->Parentesco,
                    'EntidadVive' => $data->EntidadVive,
                    'CreadoPor' => $data->CreadoPor,
                    'ActualizadoPor' => $data->ActualizadoPor,
                    'idSolicitudAplicativo' => $data->idSolicitudAplicativo,
                ];

                array_push($array_res, $temp);
            }

            $filtros = '';
            if (isset($params['filtered'])) {
                $filtros = $params['filtered'];
            }

            $response = [
                'success' => true,
                'results' => true,
                'data' => $array_res,
                'total' => $total,
                'filtros' => $filtros,
            ];
            return response()->json($response, 200);
        } catch (QueryException $errors) {
            $response = [
                'success' => false,
                'results' => false,
                'total' => 0,
                'errors' => $errors,
                'message' => 'Ha ocurrido un error, consulte al administrador',
            ];
            return response()->json($response, 200);
        }
    }

    function create(Request $request)
    {
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
                'Prestaciones' => 'required|array',
                'Enfermedades' => 'required|array',
                'AtencionesMedicas' => 'required|array',
            ]);
            // if ($v->fails()) {
            //     $response = [
            //         'success' => true,
            //         'results' => false,
            //         'errors' => $v->errors(),
            //     ];
            //     return response()->json($response, 200);
            // }
            $params = $request->all();

            $prestaciones = isset($params['Prestaciones'])
                ? $params['Prestaciones']
                : [];
            $enfermedades = isset($params['Enfermedades'])
                ? $params['Enfermedades']
                : [];
            $atencionesMedicas = isset($params['AtencionesMedicas'])
                ? $params['AtencionesMedicas']
                : [];
            $newClasificacion = isset($params['NewClasificacion'])
                ? $params['NewClasificacion']
                : [];

            //GASTOS PERIODICIDAD
            if (!isset($params['GastoAlimentos'])) {
                $params['GastoAlimentos'] = 0;
            }
            if (!isset($params['GastoVestido'])) {
                $params['GastoVestido'] = 0;
            }
            if (!isset($params['GastoEducacion'])) {
                $params['GastoEducacion'] = 0;
            }
            if (!isset($params['GastoMedicinas'])) {
                $params['GastoMedicinas'] = 0;
            }
            if (!isset($params['GastosConsultas'])) {
                $params['GastosConsultas'] = 0;
            }
            if (!isset($params['GastosCombustibles'])) {
                $params['GastosCombustibles'] = 0;
            }
            if (!isset($params['GastosServiciosBasicos'])) {
                $params['GastosServiciosBasicos'] = 0;
            }
            if (!isset($params['GastosServiciosRecreacion'])) {
                $params['GastosServiciosRecreacion'] = 0;
            }

            $user = auth()->user();
            $params['idUsuarioCreo'] = $user->id;
            $params['FechaCreo'] = date('Y-m-d');
            $params['Correo'] =
                isset($params['Correo']) && $params['Correo'] != ''
                    ? $params['Correo']
                    : '';
            unset($params['Prestaciones']);
            unset($params['Enfermedades']);
            unset($params['AtencionesMedicas']);
            unset($params['NewClasificacion']);
            unset($params['NewFiles']);
            unset($params['idCedula']);
            unset($params['id']);
            unset($params['idSolicitud']);
            unset($params['programa']);
            unset($params['idGrupo']);
            unset($params['idEstatusGrupo']);
            unset($params['idMunicipioGrupo']);

            DB::beginTransaction();
            if (isset($params['MunicipioVive'])) {
                $region = DB::table('et_cat_municipio')
                    ->where('Nombre', $params['MunicipioVive'])
                    ->get()
                    ->first();
                if ($region != null) {
                    $params['Region'] = $region->SubRegion;
                }
            } else {
                unset($params['Region']);
            }

            $id = DB::table('diagnostico_cedula')->insertGetId($params);

            if (count($prestaciones) > 0) {
                $formatedPrestaciones = [];
                foreach ($prestaciones as $prestacion) {
                    $formatedPrestaciones[] = [
                        'idCedula' => $id,
                        'idPrestacion' => $prestacion,
                    ];
                }
                DB::table('diagnostico_prestaciones')->insert(
                    $formatedPrestaciones
                );
            }

            if (count($enfermedades) > 0) {
                $formatedEnfermedades = [];
                foreach ($enfermedades as $enfermedad) {
                    $formatedEnfermedades[] = [
                        'idCedula' => $id,
                        'idEnfermedad' => $enfermedad,
                    ];
                }
                DB::table('diagnostico_enfermedades')->insert(
                    $formatedEnfermedades
                );
            }

            if (count($atencionesMedicas) > 0) {
                $formatedAtencionesMedicas = [];
                foreach ($atencionesMedicas as $atencion) {
                    $formatedAtencionesMedicas[] = [
                        'idCedula' => $id,
                        'idAtencionMedica' => $atencion,
                    ];
                }
                DB::table('diagnostico_atenciones_medicas')->insert(
                    $formatedAtencionesMedicas
                );
            }

            if (isset($request->NewFiles)) {
                $this->createCedulaFiles(
                    $id,
                    $request->NewFiles,
                    $newClasificacion,
                    $user->id
                );
            }

            DB::commit();

            $response = [
                'success' => true,
                'results' => true,
                'message' => 'Creada con éxito',
                'data' => $id,
            ];
            return response()->json($response, 200);
        } catch (\Throwable $errors) {
            DB::rollBack();
            $response = [
                'success' => false,
                'results' => false,
                'total' => 0,
                'errors' => $errors,
                'message' => 'Ha ocurrido un error, consulte al administrador',
            ];

            return response()->json($response, 200);
        }
    }

    function getById(Request $request, $id)
    {
        try {
            $cedula = DB::table('diagnostico_cedula')
                ->where('id', $id)
                ->whereNull('FechaElimino')
                ->first();

            $prestaciones = DB::table('diagnostico_prestaciones')
                ->select('idPrestacion')
                ->where('idCedula', $id)
                ->get();

            $enfermedades = DB::table('diagnostico_enfermedades')
                ->select('idEnfermedad')
                ->where('idCedula', $id)
                ->get();

            $atencionesMedicas = DB::table('diagnostico_atenciones_medicas')
                ->select('idAtencionMedica')
                ->where('idCedula', $id)
                ->get();

            $archivos = DB::table('diagnostico_cedula_archivos')
                ->select(
                    'id',
                    'idClasificacion',
                    'NombreOriginal AS name',
                    'NombreSistema',
                    'Tipo AS type'
                )
                ->where('idCedula', $id)
                ->whereRaw('FechaElimino IS NULL')
                ->get();

            $archivos2 = DB::table('diagnostico_cedula_archivos')
                ->select(
                    'id',
                    'idClasificacion',
                    'NombreOriginal AS name',
                    'NombreSistema',
                    'Tipo AS type'
                )
                ->where('idCedula', $id)
                ->whereRaw('FechaElimino IS NULL')
                ->get();

            $archivosClasificacion = array_map(function ($o) {
                return $o->idClasificacion;
            }, $archivos2->toArray());

            $archivos3 = array_map(function ($o) {
                $o->ruta =
                    'https://apivales.apisedeshu.com/subidos/' .
                    $o->NombreSistema;
                // '/var/www/html/plataforma/apivales/public/subidos/' .
                // $o->NombreSistema;
                return $o;
            }, $archivos2->toArray());

            $cedula->Prestaciones = array_map(function ($o) {
                return $o->idPrestacion;
            }, $prestaciones->toArray());
            $cedula->Enfermedades = array_map(function ($o) {
                return $o->idEnfermedad;
            }, $enfermedades->toArray());
            $cedula->AtencionesMedicas = array_map(function ($o) {
                return $o->idAtencionMedica;
            }, $atencionesMedicas->toArray());
            $cedula->Files = $archivos3;
            $cedula->ArchivosClasificacion = array_map(function ($o) {
                return $o->idClasificacion;
            }, $archivos->toArray());

            $response = [
                'success' => true,
                'results' => true,
                'message' => 'éxito',
                'data' => $cedula,
            ];
            return response()->json($response, 200);
        } catch (\Throwable $errors) {
            $response = [
                'success' => false,
                'results' => false,
                'total' => 0,
                'errors' => $errors,
                'message' => 'Ha ocurrido un error, consulte al administrador',
            ];

            return response()->json($response, 200);
        }
    }

    function getFilesById(Request $request, $id)
    {
        try {
            $archivos2 = DB::table('diagnostico_cedula_archivos')
                ->select(
                    'id',
                    'idClasificacion',
                    'NombreOriginal AS name',
                    'NombreSistema',
                    'Tipo AS type'
                )
                ->where('idCedula', $id)
                ->whereRaw('FechaElimino IS NULL')
                ->get();
            $archivosClasificacion = array_map(function ($o) {
                return $o->idClasificacion;
            }, $archivos2->toArray());
            $archivos = array_map(function ($o) {
                $o->ruta =
                    'https://apivales.apisedeshu.com/subidos/' .
                    $o->NombreSistema;
                return $o;
            }, $archivos2->toArray());

            $response = [
                'success' => true,
                'results' => true,
                'message' => 'éxito',
                'data' => [
                    'Archivos' => $archivos,
                    'ArchivosClasificacion' => $archivosClasificacion,
                ],
            ];
            return response()->json($response, 200);
        } catch (\Throwable $errors) {
            $response = [
                'success' => false,
                'results' => false,
                'total' => 0,
                'errors' => $errors,
                'message' => 'Ha ocurrido un error, consulte al administrador',
            ];

            return response()->json($response, 200);
        }
    }

    function getFilesByIdSolicitud(Request $request)
    {
        try {
            $v = Validator::make($request->all(), [
                'id' => 'required',
            ]);
            if ($v->fails()) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' => $v->errors(),
                ];
                return response()->json($response, 200);
            }

            $params = $request->all();
            $id = $params['id'];

            $cedula = DB::table('diagnostico_cedula')
                ->select('id')
                ->where('id', $id)
                ->whereNull('FechaElimino')
                ->get()
                ->first();

            if ($cedula == null) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' => 'No fue posible encontrar la cédula',
                ];
                return response()->json($response, 200);
            }

            $archivos2 = DB::table('diagnostico_cedula_archivos')
                ->select(
                    'id',
                    'idClasificacion',
                    'NombreOriginal AS name',
                    'NombreSistema',
                    'Tipo AS type'
                )
                ->where('idCedula', $cedula->id)
                ->whereRaw('FechaElimino IS NULL')
                ->get();

            $archivosClasificacion = array_map(function ($o) {
                return $o->idClasificacion;
            }, $archivos2->toArray());

            $archivos = array_map(function ($o) {
                $o->ruta =
                    'https://apivales.apisedeshu.com/subidos/' .
                    $o->NombreSistema;
                return $o;
            }, $archivos2->toArray());

            $response = [
                'success' => true,
                'results' => true,
                'message' => 'éxito',
                'data' => [
                    'Archivos' => $archivos,
                    'ArchivosClasificacion' => $archivosClasificacion,
                ],
            ];
            return response()->json($response, 200);
        } catch (\Throwable $errors) {
            $response = [
                'success' => false,
                'results' => false,
                'total' => 0,
                'errors' => $errors,
                'message' => 'Ha ocurrido un error, consulte al administrador',
            ];

            return response()->json($response, 200);
        }
    }

    function update(Request $request)
    {
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
                'Prestaciones' => 'required|array',
                'Enfermedades' => 'required|array',
                'AtencionesMedicas' => 'required|array',
                'Folio' => 'required',
            ]);
            // if ($v->fails()) {
            //     $response = [
            //         'success' => true,
            //         'results' => false,
            //         'errors' => $v->errors(),
            //     ];
            //     return response()->json($response, 200);
            // }
            $params = $request->all();
            $user = auth()->user();
            $id = $params['id'];
            $cedula = DB::table('diagnostico_cedula')
                ->where('id', $id)
                ->whereNull('FechaElimino')
                ->first();

            if ($cedula == null) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' => 'La cédula no fue encontrada',
                ];
                return response()->json($response, 200);
            }

            if ($cedula->ListaParaEnviar) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' =>
                        "La cédula tiene estatus 'Lista para enviarse', no se puede editar",
                ];
                return response()->json($response, 200);
            }

            $prestaciones = isset($params['Prestaciones'])
                ? $params['Prestaciones']
                : [];
            $enfermedades = isset($params['Enfermedades'])
                ? $params['Enfermedades']
                : [];
            $atencionesMedicas = isset($params['AtencionesMedicas'])
                ? $params['AtencionesMedicas']
                : [];
            $oldClasificacion = isset($params['OldClasificacion'])
                ? $params['OldClasificacion']
                : [];
            $newClasificacion = isset($params['NewClasificacion'])
                ? $params['NewClasificacion']
                : [];
            $params['idUsuarioActualizo'] = $user->id;
            $params['FechaActualizo'] = date('Y-m-d');
            $params['Correo'] =
                isset($params['Correo']) && $params['Correo'] != ''
                    ? $params['Correo']
                    : '';

            unset($params['id']);
            unset($params['Prestaciones']);
            unset($params['Enfermedades']);
            unset($params['AtencionesMedicas']);
            unset($params['OldFiles']);
            unset($params['OldClasificacion']);
            unset($params['NewFiles']);
            unset($params['NewClasificacion']);
            unset($params['idGrupo']);
            unset($params['idEstatusGrupo']);
            unset($params['idMunicipioGrupo']);

            DB::beginTransaction();

            DB::table('diagnostico_cedula')
                ->where('id', $id)
                ->update($params);

            DB::table('diagnostico_prestaciones')
                ->where('idCedula', $id)
                ->delete();

            $formatedPrestaciones = [];
            foreach ($prestaciones as $prestacion) {
                array_push($formatedPrestaciones, [
                    'idCedula' => $id,
                    'idPrestacion' => $prestacion,
                ]);
            }

            DB::table('diagnostico_prestaciones')->insert(
                $formatedPrestaciones
            );

            DB::table('diagnostico_enfermedades')
                ->where('idCedula', $id)
                ->delete();
            $formatedEnfermedades = [];
            foreach ($enfermedades as $enfermedad) {
                array_push($formatedEnfermedades, [
                    'idCedula' => $id,
                    'idEnfermedad' => $enfermedad,
                ]);
            }
            DB::table('diagnostico_enfermedades')->insert(
                $formatedEnfermedades
            );

            DB::table('diagnostico_atenciones_medicas')
                ->where('idCedula', $id)
                ->delete();
            $formatedAtencionesMedicas = [];
            foreach ($atencionesMedicas as $atencion) {
                array_push($formatedAtencionesMedicas, [
                    'idCedula' => $id,
                    'idAtencionMedica' => $atencion,
                ]);
            }
            DB::table('diagnostico_atenciones_medicas')->insert(
                $formatedAtencionesMedicas
            );

            $oldFiles = DB::table('diagnostico_cedula_archivos')
                ->select('id', 'idClasificacion')
                ->where('idCedula', $id)
                ->whereRaw('FechaElimino IS NULL')
                ->get();
            $oldFilesIds = array_map(function ($o) {
                return $o->id;
            }, $oldFiles->toArray());
            if (isset($request->NewFiles)) {
                $this->createCedulaFiles(
                    $id,
                    $request->NewFiles,
                    $newClasificacion,
                    $user->id
                );
            }
            if (isset($request->OldFiles)) {
                $oldFilesIds = $this->updateCedulaFiles(
                    $id,
                    $request->OldFiles,
                    $oldClasificacion,
                    $user->id,
                    $oldFilesIds,
                    $oldFiles
                );
            }

            if (count($oldFilesIds) > 0) {
                DB::table('diagnostico_cedula_archivos')
                    ->whereIn('id', $oldFilesIds)
                    ->update([
                        'idUsuarioElimino' => $user->id,
                        'FechaElimino' => date('Y-m-d H:i:s'),
                    ]);
            }

            DB::commit();

            $response = [
                'success' => true,
                'results' => true,
                'message' => 'Editada con éxito',
                'data' => [],
            ];
            return response()->json($response, 200);
        } catch (QueryException $errors) {
            DB::rollBack();
            $response = [
                'success' => false,
                'results' => false,
                'total' => 0,
                'errors' => $errors,
                'message' => 'Ha ocurrido un error, consulte al administrador',
            ];

            return response()->json($response, 200);
        }
    }

    function updateArchivosCedula(Request $request)
    {
        try {
            $v = Validator::make($request->all(), [
                'id' => 'required',
            ]);
            if ($v->fails()) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' => $v->errors(),
                ];
                return response()->json($response, 200);
            }
            $params = $request->all();
            $oldClasificacion = isset($params['OldClasificacion'])
                ? $params['OldClasificacion']
                : [];
            $newClasificacion = isset($params['NewClasificacion'])
                ? $params['NewClasificacion']
                : [];
            $id = $params['id'];
            $user = auth()->user();

            DB::beginTransaction();
            $oldFiles = DB::table('diagnostico_cedula_archivos')
                ->select('id', 'idClasificacion')
                ->where('idCedula', $id)
                ->whereRaw('FechaElimino IS NULL')
                ->get();
            $oldFilesIds = array_map(function ($o) {
                return $o->id;
            }, $oldFiles->toArray());
            if (isset($request->NewFiles)) {
                $this->createCedulaFiles(
                    $id,
                    $request->NewFiles,
                    $newClasificacion,
                    $user->id
                );
            }
            if (isset($request->OldFiles)) {
                $oldFilesIds = $this->updateCedulaFiles(
                    $id,
                    $request->OldFiles,
                    $oldClasificacion,
                    $user->id,
                    $oldFilesIds,
                    $oldFiles
                );
            }

            if (count($oldFilesIds) > 0) {
                DB::table('diagnostico_cedula_archivos')
                    ->whereIn('id', $oldFilesIds)
                    ->update([
                        'idUsuarioElimino' => $user->id,
                        'FechaElimino' => date('Y-m-d H:i:s'),
                    ]);
            }
            DB::commit();
            $response = [
                'success' => true,
                'results' => true,
                'message' => 'Editada con éxito',
                'data' => [],
            ];
            return response()->json($response, 200);
        } catch (QueryException $errors) {
            DB::rollBack();
            $response = [
                'success' => false,
                'results' => false,
                'total' => 0,
                'errors' => $errors,
                'message' => 'Ha ocurrido un error, consulte al administrador',
            ];

            return response()->json($response, 200);
        }
    }

    function delete(Request $request)
    {
        try {
            $v = Validator::make($request->all(), [
                'id' => 'required',
            ]);
            if ($v->fails()) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' => $v->errors(),
                ];
                return response()->json($response, 200);
            }
            $params = $request->all();
            $user = auth()->user();
            $id = $params['id'];

            $cedula = DB::table('diagnostico_cedula')
                ->where('id', $id)
                ->first();

            if ($cedula == null) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' => 'La cédula no fue encontrada',
                ];
                return response()->json($response, 200);
            }
            if ($cedula->ListaParaEnviar && $user->id != 52) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' =>
                        "La cédula tiene estatus 'Lista para enviarse', no se puede editar",
                ];
                return response()->json($response, 200);
            }

            DB::beginTransaction();

            DB::table('diagnostico_prestaciones')
                ->where('idCedula', $id)
                ->delete();

            DB::table('diagnostico_enfermedades')
                ->where('idCedula', $id)
                ->delete();

            DB::table('diagnostico_atenciones_medicas')
                ->where('idCedula', $id)
                ->delete();

            DB::table('diagnostico_cedula_archivos')
                ->where('idCedula', $id)
                ->update([
                    'idUsuarioElimino' => $user->id,
                    'FechaElimino' => date('Y-m-d H:i:s'),
                ]);

            DB::table('diagnostico_cedula')
                ->where('id', $id)
                ->update([
                    'idUsuarioElimino' => $user->id,
                    'FechaElimino' => date('Y-m-d H:i:s'),
                ]);

            DB::commit();

            $response = [
                'success' => true,
                'results' => true,
                'message' => 'Eliminada con éxito',
                'data' => [],
            ];
            return response()->json($response, 200);
        } catch (\Throwable $errors) {
            //dd($errors);
            DB::rollBack();
            $response = [
                'success' => false,
                'results' => false,
                'total' => 0,
                'errors' => $errors,
                'message' => 'Ha ocurrido un error, consulte al administrador',
            ];

            return response()->json($response, 200);
        }
    }

    function validar(Request $request)
    {
        try {
            $v = Validator::make($request->all(), [
                'id' => 'required',
            ]);
            if ($v->fails()) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' => $v->errors(),
                ];
                return response()->json($response, 200);
            }
            $params = $request->all();
            $user = auth()->user();
            $id = $params['id'];

            $cedula = DB::table('diagnostico_cedula')
                ->where('id', $id)
                ->first();

            if ($cedula == null) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' => 'La cédula no fue encontrada',
                ];
                return response()->json($response, 200);
            }

            DB::beginTransaction();
            DB::table('diagnostico_cedula')
                ->where('id', $id)
                ->update([
                    'idEstatus' => '8',
                    'idUsuarioActualizo' => $user->id,
                    'FechaActualizo' => date('Y-m-d H:i:s'),
                ]);

            DB::commit();

            $response = [
                'success' => true,
                'results' => true,
                'message' => 'Eliminada con éxito',
                'data' => [],
            ];
            return response()->json($response, 200);
        } catch (\Throwable $errors) {
            //dd($errors);
            DB::rollBack();
            $response = [
                'success' => false,
                'results' => false,
                'total' => 0,
                'errors' => $errors,
                'message' => 'Ha ocurrido un error, consulte al administrador',
            ];

            return response()->json($response, 200);
        }
    }

    private function getFileType($extension)
    {
        if (in_array($extension, ['png', 'jpg', 'jpeg'])) {
            return 'image';
        }
        if (in_array($extension, ['xlsx', 'xls', 'numbers'])) {
            return 'sheet';
        }
        if (in_array($extension, ['doc', 'docx'])) {
            return 'document';
        }
        if ($extension == 'pdf') {
            return 'pdf';
        }
        return 'other';
    }

    private function createCedulaFiles(
        $id,
        $files,
        $clasificationArray,
        $userId
    ) {
        foreach ($files as $key => $file) {
            $originalName = $file->getClientOriginalName();
            $extension = explode('.', $originalName);
            $extension = $extension[count($extension) - 1];
            $uniqueName = uniqid() . '.' . $extension;
            $size = $file->getSize();
            $clasification = $clasificationArray[$key];
            $fileObject = [
                'idCedula' => intval($id),
                'idClasificacion' => intval($clasification),
                'NombreOriginal' => $originalName,
                'NombreSistema' => $uniqueName,
                'Extension' => $extension,
                'Tipo' => $this->getFileType($extension),
                'Tamanio' => $size,
                'idUsuarioCreo' => $userId,
                'FechaCreo' => date('Y-m-d H:i:s'),
            ];
            $file->move('subidos', $uniqueName);
            DB::table('diagnostico_cedula_archivos')->insert($fileObject);
        }
    }

    private function updateCedulaFiles(
        $id,
        $files,
        $clasificationArray,
        $userId,
        $oldFilesIds,
        $oldFiles
    ) {
        foreach ($files as $key => $file) {
            $fileAux = json_decode($file);
            $encontrado = array_search($fileAux->id, $oldFilesIds);
            if ($encontrado !== false) {
                if (
                    $oldFiles[$encontrado]->idClasificacion !=
                    $clasificationArray[$key]
                ) {
                    DB::table('diagnostico_cedula_archivos')
                        ->where('id', $fileAux->id)
                        ->update([
                            'idClasificacion' => $clasificationArray[$key],
                            'idUsuarioActualizo' => $userId,
                            'FechaActualizo' => date('Y-m-d H:i:s'),
                        ]);
                }
                unset($oldFilesIds[$encontrado]);
            }
        }
        return $oldFilesIds;
    }

    public function uploadFilesDiagnostico(Request $request)
    {
        $v = Validator::make($request->all(), [
            'id' => 'required',
            'NewFiles' => 'required',
            'NamesFiles' => 'required',
            'ArrayClasificacion' => 'required',
            'ArrayExtension' => 'required',
        ]);

        if ($v->fails()) {
            $response = [
                'success' => true,
                'results' => false,
                'errors' => $v->errors(),
            ];
            return response()->json($response, 200);
        }

        $params = $request->all();
        $id = $params['id'];
        $files = $params['NewFiles'];
        $arrayClasifiacion = $params['ArrayClasificacion'];
        $fullPath = public_path('/subidos/');
        $extension = $params['ArrayExtension'];
        $names = $params['NamesFiles'];
        try {
            $solicitud = DB::table('diagnostico_cedula')
                ->select('idUsuarioCreo', 'id')
                ->where('id', $id)
                ->first();
            if ($solicitud == null) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' => 'No se encuentra la cedula de diagnostico',
                ];
                return response()->json($response, 200);
            }
            foreach ($files as $key => $file) {
                $imageContent = $this->imageBase64Content($file);
                $uniqueName = uniqid() . $extension[$key];
                $clasification = $arrayClasifiacion[$key];
                $originalName = $names[$key];

                File::put($fullPath . $uniqueName, $imageContent);
                $fileObject = [
                    'idCedula' => intval($solicitud->id),
                    'idClasificacion' => intval($clasification),
                    'NombreOriginal' => $originalName,
                    'NombreSistema' => $uniqueName,
                    'Extension' => str_replace('.', '', $extension[$key]),
                    'Tipo' => $this->getFileType(
                        str_replace('.', '', $extension[$key])
                    ),
                    'Tamanio' => '',
                    'idUsuarioCreo' => $solicitud->idUsuarioCreo,
                    'FechaCreo' => date('Y-m-d H:i:s'),
                ];
                $tableArchivos = 'diagnostico_cedula_archivos';
                DB::table($tableArchivos)->insert($fileObject);
            }
            $response = [
                'success' => true,
                'results' => true,
                'errors' => 'Archivos cargados con exito',
            ];
            return response()->json($response, 200);
        } catch (QueryException $errors) {
            DB::rollBack();
            $response = [
                'success' => false,
                'results' => false,
                'total' => 0,
                'errors' => $errors,
                'message' => 'Ha ocurrido un error, consulte al administrador',
            ];

            return response()->json($response, 200);
        }
    }

    public function imageBase64Content($image)
    {
        $image = str_replace('data:image/png;base64,', '', $image);
        $image = str_replace(' ', '+', $image);
        return base64_decode($image);
    }

    public function getReporteSolicitudVentanillaDiagnostico(Request $request)
    {
        $user = auth()->user();
        $parameters['UserCreated'] = $user->id;
        $tableSol = 'vales';
        $res = DB::table('diagnostico_cedula as vales')
            ->select(
                'et_cat_municipio.SubRegion AS Region',
                'vales.Folio AS Folio',
                'vales.FechaSolicitud',
                'vales.CURP',
                'vales.RFC',
                DB::raw(
                    "concat_ws(' ',vales.Nombre,vales.Paterno,vales.Materno) as NombreCompleto"
                ),
                'vales.Sexo',
                'vales.FechaNacimiento',
                'cat_estado_civil.EstadoCivil',
                'cat_parentesco_jefe_hogar.Parentesco',
                'entidadesVive.Entidad',
                'vales.MunicipioVive AS Municipio',
                'vales.LocalidadVive AS Localidad',
                'vales.CPVive',
                DB::raw(
                    "concat_ws(' ',vales.CalleVive, concat('Num. ', vales.NoExtVive), if(vales.NoIntVive is not null,concat('NumInt. ',vales.NoIntVive), ''), concat('Col. ',vales.ColoniaVive)) as Direccion"
                ),
                'vales.AGEBVive AS ClaveAGEB',
                'vales.ManzanaVive AS Manzana',
                'vales.TipoAsentamientoVive',
                'vales.Referencias',
                'vales.Telefono',
                'vales.Celular',
                'vales.TelRecados',
                'vales.Correo',
                DB::raw("
                        CASE
                             WHEN
                             vales.NumHijos = 0
                             THEN
                                 '-'
                             ELSE
                                 vales.NumHijos
                             END
                         AS NumHijos
                         "),
                DB::raw("
                        CASE
                             WHEN
                             vales.NumHijas = 0
                             THEN
                                 '-'
                             ELSE
                                 vales.NumHijas
                             END
                         AS NumHijas
                         "),
                'vales.TotalHogares AS PersonasGastosSeparados',
                'vales.NumeroMujeresHogar',
                'vales.NumeroHombresHogar',
                DB::raw("
                        CASE
                            WHEN
                            vales.PersonasMayoresEdad = 1
                            THEN
                                'SI'
                            ELSE
                                'NO'
                            END
                        AS PersonasMayoresEdad
                        "),
                DB::raw("
                        CASE
                            WHEN
                            vales.PersonasTerceraEdad = 1
                            THEN
                                'SI'
                            ELSE
                                'NO'
                            END
                        AS PersonasTerceraEdad
                        "),
                DB::raw("
                        CASE
                            WHEN
                            vales.PersonaJefaFamilia = 'H'
                            THEN
                                'HOMBRE'
                            ELSE
                                'MUJER'
                            END
                        AS PersonaJefaFamilia
                        "),
                DB::raw("
                        CASE
                            WHEN
                            vales.DificultadMovilidad = 0
                            THEN
                                'NO'
                            ELSE
                                movimiento.Grado
                            END
                        AS DificultadMovilidad
                        "),
                DB::raw("
                        CASE
                            WHEN
                            vales.DificultadVer = 0
                            THEN
                                'NO'
                            ELSE
                                ver.Grado
                            END
                        AS DificultadVer
                        "),
                DB::raw("
                        CASE
                            WHEN
                            vales.DificultadOir = 0
                            THEN
                                'NO'
                            ELSE
                                oir.Grado
                            END
                        AS DificultadOir
                        "),
                DB::raw("
                        CASE
                            WHEN
                            vales.DificultadHablar = 0
                            THEN
                                'NO'
                            ELSE
                                hab.Grado
                            END
                        AS DificultadHablar
                        "),
                DB::raw("
                        CASE
                            WHEN
                            vales.DificultadVestirse = 0
                            THEN
                                'NO'
                            ELSE
                                vestir.Grado
                            END
                        AS DificultadVestirse
                        "),
                DB::raw("
                        CASE
                            WHEN
                            vales.DificultadRecordar = 0
                            THEN
                                'NO'
                            ELSE
                                rec.Grado
                            END
                        AS DificultadRecordar
                        "),
                DB::raw("
                        CASE
                            WHEN
                            vales.DificultadBrazos = 0
                            THEN
                                'NO'
                            ELSE
                                brazos.Grado
                            END
                        AS DificultadBrazos
                        "),
                DB::raw("
                    CASE
                        WHEN
                        vales.DificultadMental = 1
                        THEN
                            'SI'
                        ELSE
                            'NO'
                        END
                    AS DificultadMental
                    "),
                DB::raw("
                    CASE
                        WHEN
                        vales.AsisteEscuela = 1
                        THEN
                            'SI'
                        ELSE
                            'NO'
                        END
                    AS AsisteEscuela
                    "),
                'cat_niveles_educacion.Nivel',
                'educacionGrado.Grado',
                'cat_actividades.Actividad',
                'vales.IngresoTotalMesPasado',
                'vales.PensionMensual',
                'vales.IngresoOtrosPaises',
                'vales.GastoAlimentos',
                DB::raw("
                        CASE
                            WHEN
                            vales.PeriodicidadAlimentos = 0
                            THEN
                                '-'
                            ELSE
                            ali.Periodicidad
                            END
                        AS PeriodicidadAlimentos
                        "),
                'vales.GastoVestido',
                DB::raw("
                        CASE
                            WHEN
                            vales.PeriodicidadVestido = 0
                            THEN
                                '-'
                            ELSE
                            ves.Periodicidad
                            END
                        AS PeriodicidadVestido
                        "),
                'vales.GastoEducacion',
                DB::raw("
                        CASE
                            WHEN
                            vales.PeriodicidadEducacion = 0
                            THEN
                                '-'
                            ELSE
                            edu.Periodicidad
                            END
                        AS PeriodicidadEducacion
                        "),
                'vales.GastoMedicinas',
                DB::raw("
                        CASE
                            WHEN
                            vales.PeriodicidadMedicinas = 0
                            THEN
                                '-'
                            ELSE
                            medi.Periodicidad
                            END
                        AS PeriodicidadMedicinas
                        "),
                'vales.GastosConsultas',
                DB::raw("
                        CASE
                            WHEN
                            vales.PeriodicidadConsultas = 0
                            THEN
                                '-'
                            ELSE
                            cons.Periodicidad
                            END
                        AS PeriodicidadConsultas
                        "),
                'vales.GastosCombustibles',
                DB::raw("
                        CASE
                            WHEN
                            vales.PeriodicidadCombustibles = 0
                            THEN
                                '-'
                            ELSE
                            comb.Periodicidad
                            END
                        AS PeriodicidadCombustibles
                        "),
                'vales.GastosServiciosBasicos',
                DB::raw("
                        CASE
                            WHEN
                            vales.PeriodicidadServiciosBasicos = 0
                            THEN
                                '-'
                            ELSE
                            serv.Periodicidad
                            END
                        AS PeriodicidadServiciosBasicos
                        "),
                'vales.GastosServiciosRecreacion',
                DB::raw("
                        CASE
                            WHEN
                            vales.PeriodicidadServiciosRecreacion = 0
                            THEN
                                '-'
                            ELSE
                            recre.Periodicidad
                            END
                        AS PeriodicidadServiciosRecreacion
                        "),
                DB::raw("
                        CASE
                            WHEN
                            vales.AlimentacionPocoVariada = 1
                            THEN
                                'SI'
                            ELSE
                                'NO'
                            END
                        AS AlimentacionPocoVariada
                        "),
                DB::raw("
                        CASE
                            WHEN
                            vales.ComioMenos = 1
                            THEN
                                'SI'
                            ELSE
                                'NO'
                            END
                        AS ComioMenos
                        "),
                DB::raw("
                        CASE
                            WHEN
                            vales.DisminucionComida = 1
                            THEN
                                'SI'
                            ELSE
                                'NO'
                            END
                        AS DisminucionComida
                        "),
                DB::raw("
                        CASE
                            WHEN
                            vales.NoComio = 1
                            THEN
                                'SI'
                            ELSE
                                'NO'
                            END
                        AS NoComio
                        "),
                DB::raw("
                        CASE
                            WHEN
                            vales.DurmioHambre = 1
                            THEN
                                'SI'
                            ELSE
                                'NO'
                            END
                        AS DurmioHambre
                        "),
                DB::raw("
                        CASE
                            WHEN
                            vales.DejoComer = 1
                            THEN
                                'SI'
                            ELSE
                                'NO'
                            END
                        AS DejoComer
                        "),
                'vales.PersonasHogar',
                'vales.CuartosHogar',
                'cat_tipos_viviendas.Tipo',
                'cat_tipos_pisos.Piso',
                'cat_tipos_muros.Muro',
                'cat_tipos_techos.Techo',
                'cat_tipos_agua.Agua',
                'cat_tipos_drenajes.Drenaje',
                'cat_tipos_luz.Luz',
                'cat_tipos_combustibles.Combustible',
                DB::raw("
                        CASE
                            WHEN
                            vales.Refrigerador = 1
                            THEN
                                'SI'
                            ELSE
                                'NO'
                            END
                        AS Refrigerador
                        "),
                DB::raw("
                        CASE
                            WHEN
                            vales.Lavadora = 1
                            THEN
                                'SI'
                            ELSE
                                'NO'
                            END
                        AS Lavadora
                        "),
                DB::raw("
                        CASE
                            WHEN
                            vales.Computadora = 1
                            THEN
                                'SI'
                            ELSE
                                'NO'
                            END
                        AS Computadora
                        "),
                DB::raw("
                        CASE
                            WHEN
                            vales.Estufa = 1
                            THEN
                                'SI'
                            ELSE
                                'NO'
                            END
                        AS Estufa
                        "),
                DB::raw("
                        CASE
                            WHEN
                            vales.Calentador = 1
                            THEN
                                'SI'
                            ELSE
                                'NO'
                            END
                        AS Calentador
                        "),
                DB::raw("
                        CASE
                            WHEN
                            vales.CalentadorSolar = 1
                            THEN
                                'SI'
                            ELSE
                                'NO'
                            END
                        AS CalentadorSolar
                        "),
                DB::raw("
                        CASE
                            WHEN
                            vales.Television = 1
                            THEN
                                'SI'
                            ELSE
                                'NO'
                            END
                        AS Television
                        "),
                DB::raw("
                        CASE
                            WHEN
                            vales.Internet = 1
                            THEN
                                'SI'
                            ELSE
                                'NO'
                            END
                        AS Internet
                        "),
                DB::raw("
                        CASE
                            WHEN
                            vales.TieneTelefono = 1
                            THEN
                                'SI'
                            ELSE
                                'NO'
                            END
                        AS TieneTelefono
                        "),
                DB::raw("
                        CASE
                            WHEN
                            vales.Tinaco = 1
                            THEN
                                'SI'
                            ELSE
                                'NO'
                            END
                        AS Tinaco
                        "),
                DB::raw("
                    CASE
                        WHEN
                        vales.ColoniaSegura = 1
                        THEN
                            'SI'
                        ELSE
                            'NO'
                        END
                    AS ColoniaSegura
                    "),
                DB::raw(
                    "CASE
                            WHEN
                                vales.ListaParaEnviar = 0
                            THEN
                                'Por validar'
                            ELSE
                                'Validada'
                            END
                        AS Estatus"
                ),
                DB::raw(
                    "CASE
                            WHEN
                                vales.idUsuarioCreo = 1312
                            THEN
                                users_aplicativo_web.Nombre
                            ELSE
                                CONCAT_WS( ' ', users.Nombre, users.Paterno, users.Materno )
                            END
                        AS UserInfoCapturo"
                ),
                'vales.Enlace AS Enlace',
                'vales.Latitud',
                'vales.Longitud'
            )
            ->leftJoin('vales_status', 'vales_status.id', '=', 'idEstatus')
            ->leftJoin('users', 'users.id', '=', 'vales.idUsuarioCreo')
            ->leftJoin(
                'cat_estado_civil',
                'cat_estado_civil.id',
                '=',
                'vales.idEstadoCivil'
            )
            ->leftJoin(
                'cat_parentesco_jefe_hogar',
                'cat_parentesco_jefe_hogar.id',
                '=',
                'vales.idParentescoJefeHogar'
            )
            ->leftJoin(
                'et_cat_municipio',
                'et_cat_municipio.Nombre',
                '=',
                'vales.MunicipioVive'
            )
            ->leftJoin(
                'users_aplicativo_web',
                'users_aplicativo_web.UserName',
                'vales.UsuarioAplicativo'
            )
            ->leftJoin(
                'cat_entidad AS entidadesVive',
                'entidadesVive.id',
                'vales.idEntidadVive'
            )
            ->leftJoin(
                'cat_codigos_dificultad as movimiento',
                'movimiento.id',
                '=',
                'vales.DificultadMovilidad'
            )
            ->leftJoin(
                'cat_codigos_dificultad as ver',
                'ver.id',
                'vales.DificultadVer'
            )
            ->leftJoin(
                'cat_codigos_dificultad as oir',
                'oir.id',
                'vales.DificultadOir'
            )
            ->leftJoin(
                'cat_codigos_dificultad as hab',
                'hab.id',
                'vales.DificultadHablar'
            )
            ->leftJoin(
                'cat_codigos_dificultad as vestir',
                'vestir.id',
                'vales.DificultadVestirse'
            )
            ->leftJoin(
                'cat_codigos_dificultad as rec',
                'rec.id',
                'vales.DificultadRecordar'
            )
            ->leftJoin(
                'cat_codigos_dificultad as brazos',
                'brazos.id',
                'vales.DificultadBrazos'
            )
            ->leftJoin(
                'cat_niveles_educacion',
                'cat_niveles_educacion.id',
                'vales.idNivelEscuela'
            )
            ->leftJoin(
                'cat_grados_educacion as educacionGrado',
                'educacionGrado.id',
                'vales.idGradoEscuela'
            )
            ->leftJoin(
                'cat_actividades',
                'cat_actividades.id',
                'vales.idActividades'
            )
            ->leftJoin(
                'cat_periodicidad as ali',
                'ali.id',
                'vales.PeriodicidadAlimentos'
            )
            ->leftJoin(
                'cat_periodicidad as ves',
                'ves.id',
                'vales.PeriodicidadVestido'
            )
            ->leftJoin(
                'cat_periodicidad as edu',
                'edu.id',
                'vales.PeriodicidadEducacion'
            )
            ->leftJoin(
                'cat_periodicidad as medi',
                'medi.id',
                'vales.PeriodicidadMedicinas'
            )
            ->leftJoin(
                'cat_periodicidad as cons',
                'cons.id',
                'vales.PeriodicidadConsultas'
            )
            ->leftJoin(
                'cat_periodicidad as comb',
                'comb.id',
                'vales.PeriodicidadCombustibles'
            )
            ->leftJoin(
                'cat_periodicidad as serv',
                'serv.id',
                'vales.PeriodicidadServiciosBasicos'
            )
            ->leftJoin(
                'cat_periodicidad as recre',
                'recre.id',
                'vales.PeriodicidadServiciosRecreacion'
            )
            ->leftJoin(
                'cat_tipos_viviendas',
                'cat_tipos_viviendas.id',
                'vales.idTipoVivienda'
            )
            ->leftJoin(
                'cat_tipos_pisos',
                'cat_tipos_pisos.id',
                'vales.idTipoPiso'
            )
            ->leftJoin(
                'cat_tipos_muros',
                'cat_tipos_muros.id',
                'vales.idTipoParedes'
            )
            ->leftJoin(
                'cat_tipos_techos',
                'cat_tipos_techos.id',
                'vales.idTipoTecho'
            )
            ->leftJoin(
                'cat_tipos_agua',
                'cat_tipos_agua.id',
                'vales.idTipoAgua'
            )
            ->leftJoin(
                'cat_tipos_drenajes',
                'cat_tipos_drenajes.id',
                'vales.idTipoDrenaje'
            )
            ->leftJoin('cat_tipos_luz', 'cat_tipos_luz.id', 'vales.idTipoLuz')
            ->leftJoin(
                'cat_tipos_combustibles',
                'cat_tipos_combustibles.id',
                'vales.idTipoCombustible'
            )
            ->whereRaw('vales.FechaElimino IS NULL');

        //dd($res->toSql());

        //Agregando Filtros por permisos
        $permisos = $this->getPermisos();

        $seguimiento = $permisos->Seguimiento;
        $viewall = $permisos->ViewAll;
        $filtroCapturo = '';

        if ($viewall < 1 && $seguimiento < 1) {
            $usuarioApp = DB::table('users_aplicativo_web')
                ->where('idUser', $user->id)
                ->get()
                ->first();
            $filtroCapturo =
                '(' .
                $tableSol .
                ".idUsuarioCreo = '" .
                $user->id .
                "' OR " .
                $tableSol .
                ".idUsuarioActualizo = '" .
                $user->id .
                "' OR " .
                $tableSol .
                ".UsuarioAplicativo = '" .
                $usuarioApp->UserName .
                "')";
        } elseif ($viewall < 1) {
            $idUserOwner = DB::table('users_aplicativo_web')
                ->selectRaw('idUserOwner')
                ->where('idUser', $user->id)
                ->get()
                ->first();

            $filtroCapturo =
                '(' .
                $tableSol .
                '.idUsuarioCreo IN (' .
                'SELECT idUser FROM users_aplicativo_web WHERE idUserOwner = ' .
                $idUserOwner->idUserOwner .
                ') OR ' .
                $tableSol .
                '.UsuarioAplicativo IN (' .
                'SELECT UserName FROM users_aplicativo_web WHERE idUserOwner = ' .
                $idUserOwner->idUserOwner .
                ')' .
                ')';
        }

        //agregando los filtros seleccionados
        $filterQuery = '';
        $municipioRegion = [];
        $mun = [];
        $filtro_usuario = VNegociosFiltros::where('idUser', '=', $user->id)
            ->where('api', '=', 'getDiagnosticoVentanilla')
            ->first();
        if ($filtro_usuario) {
            $hoy = date('Y-m-d H:i:s');
            $intervalo = $filtro_usuario->updated_at->diff($hoy);
            if ($intervalo->h === 0) {
                //Si es 0 es porque no ha pasado una hora.
                $params = unserialize($filtro_usuario->parameters);

                if (
                    isset($params['filtered']) &&
                    count($params['filtered']) > 0
                ) {
                    //dd($params['filtered']);
                    $filtersCedulas = ['.id'];
                    foreach ($params['filtered'] as $filtro) {
                        if ($filterQuery != '') {
                            $filterQuery .= ' AND ';
                        }
                        $id = $filtro['id'];
                        $value = $filtro['value'];

                        if ($id == '.FechaSolicitud') {
                            $timestamp = strtotime($value);
                            $value = date('Y-m-d', $timestamp);
                        }

                        if ($id == '.FechaCreo') {
                            $timestamp = strtotime($value);
                            $value = date('Y-m-d', $timestamp);
                        }

                        if ($id == '.MunicipioVive') {
                            foreach ($value as $m) {
                                $mun[] = "'" . $m . "'";
                            }
                            $value = $mun;
                        }

                        if ($id == 'region') {
                            $municipios = DB::table('et_cat_municipio')
                                ->select('Nombre')
                                ->whereIN('SubRegion', $value)
                                ->get();
                            foreach ($municipios as $m) {
                                $municipioRegion[] = "'" . $m->Nombre . "'";
                            }

                            $id = '.MunicipioVive';
                            $value = $municipioRegion;
                        }

                        $id = 'vales' . $id;

                        switch (gettype($value)) {
                            case 'string':
                                $filterQuery .= " $id LIKE '%$value%' ";
                                break;
                            case 'array':
                                $colonDividedValue = implode(', ', $value);
                                $filterQuery .= " $id IN ($colonDividedValue) ";
                                break;
                            default:
                                //dd($value);
                                if ($value === -1) {
                                    $filterQuery .= " $id IS NOT NULL ";
                                } else {
                                    $filterQuery .= " $id = $value ";
                                }
                        }
                    }
                }
            }
        }

        if ($filterQuery != '') {
            $res->whereRaw($filterQuery);
        }

        if ($filtroCapturo !== '') {
            $res->whereRaw($filtroCapturo);
        }

        $data = $res
            ->orderBy('vales.Paterno', 'asc')
            ->orderBy('vales.Materno', 'asc')
            ->orderBy('vales.Nombre', 'asc')
            ->get();

        if (count($data) == 0) {
            //return response()->json(['success'=>false,'results'=>false,'message'=>$res->toSql()]);
            $reader = IOFactory::createReader('Xlsx');
            $spreadsheet = $reader->load(
                public_path() . '/archivos/formatoReporteSolicitudValesV3.xlsx'
            );
            $writer = new Xlsx($spreadsheet);
            $writer->save(
                'archivos/' . $user->email . 'reporteDiagnostico.xlsx'
            );
            $file =
                public_path() .
                '/archivos/' .
                $user->email .
                'reporteComercioVales.xlsx';

            return response()->download(
                $file,
                'SolicitudesDiagnostico' . date('Y-m-d') . '.xlsx'
            );
        }

        //Mapeamos el resultado como un array
        $res = $data
            ->map(function ($x) {
                $x = is_object($x) ? (array) $x : $x;
                return $x;
            })
            ->toArray();

        //------------------------------------------------- Para generar el archivo excel ----------------------------------------------------------------
        // $spreadsheet = new Spreadsheet();
        // $sheet = $spreadsheet->getActiveSheet();

        //Para los titulos del excel
        // $titulos = ['Grupo','Folio','Nombre','Paterno','Materno','Fecha de Nacimiento','Sexo','Calle','Numero','Colonia','Municipio','Localidad','CP','Terminación'];
        // $sheet->fromArray($titulos,null,'A1');
        // $sheet->getStyle('A1:N1')->getFont()->getColor()->applyFromArray(['rgb' => '808080']);

        $reader = IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load(
            public_path() . '/archivos/formatoReporteSolicitudValesV7.xlsx'
        );
        $sheet = $spreadsheet->getActiveSheet();
        $largo = count($res);
        $impresion = $largo + 10;
        $sheet->getPageSetup()->setPrintArea('A1:V' . $impresion);
        $sheet
            ->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_LETTER);

        $largo = count($res);
        //colocar los bordes
        // self::crearBordes($largo, 'B', $sheet);
        // self::crearBordes($largo, 'C', $sheet);
        // self::crearBordes($largo, 'D', $sheet);
        // self::crearBordes($largo, 'E', $sheet);
        // self::crearBordes($largo, 'F', $sheet);
        // self::crearBordes($largo, 'G', $sheet);
        // self::crearBordes($largo, 'H', $sheet);
        // self::crearBordes($largo, 'I', $sheet);
        // self::crearBordes($largo, 'J', $sheet);
        // self::crearBordes($largo, 'K', $sheet);
        // self::crearBordes($largo, 'L', $sheet);
        // self::crearBordes($largo, 'M', $sheet);
        // self::crearBordes($largo, 'N', $sheet);
        // self::crearBordes($largo, 'O', $sheet);
        // self::crearBordes($largo, 'P', $sheet);
        // self::crearBordes($largo, 'Q', $sheet);
        // self::crearBordes($largo, 'R', $sheet);
        // self::crearBordes($largo, 'S', $sheet);
        // self::crearBordes($largo, 'T', $sheet);
        // self::crearBordes($largo, 'U', $sheet);
        // self::crearBordes($largo, 'V', $sheet);
        // self::crearBordes($largo, 'W', $sheet);
        // self::crearBordes($largo, 'X', $sheet);
        // self::crearBordes($largo, 'Y', $sheet);
        // self::crearBordes($largo, 'Z', $sheet);
        // self::crearBordes($largo, 'AA', $sheet);
        // self::crearBordes($largo, 'AB', $sheet);
        // self::crearBordes($largo, 'AC', $sheet);
        // self::crearBordes($largo, 'AD', $sheet);
        // self::crearBordes($largo, 'AE', $sheet);
        // self::crearBordes($largo, 'AF', $sheet);
        // self::crearBordes($largo, 'AG', $sheet);

        //Llenar excel con el resultado del query
        $sheet->fromArray($res, null, 'C11');
        //Agregamos la fecha
        $sheet->setCellValue('U6', 'Fecha Reporte: ' . date('Y-m-d H:i:s'));

        //Agregar el indice autonumerico

        for ($i = 1; $i <= $largo; $i++) {
            $inicio = 10 + $i;
            $sheet->setCellValue('B' . $inicio, $i);
        }

        if ($largo > 75) {
            //     //dd('Se agrega lineBreak');
            for ($lb = 70; $lb < $largo; $lb += 70) {
                //         $veces++;
                //         //dd($largo);
                $sheet->setBreak(
                    'B' . ($lb + 10),
                    \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::BREAK_ROW
                );
            }
        }

        $sheet->getDefaultRowDimension()->setRowHeight(-1);

        //guardamos el excel creado y luego lo obtenemos en $file para poder descargarlo
        $writer = new Xlsx($spreadsheet);
        $writer->save('archivos/' . $user->email . 'Diagnostico.xlsx');
        $file =
            public_path() . '/archivos/' . $user->email . 'Diagnostico.xlsx';

        return response()->download(
            $file,
            $user->email . 'Diagnostico' . date('Y-m-d H:i:s') . '.xlsx'
        );
    }

    //funcion para generar bordes en el excel.
    public static function crearBordes($largo, $columna, &$sheet)
    {
        for ($i = 0; $i < $largo; $i++) {
            $inicio = 11 + $i;

            $sheet
                ->getStyle($columna . $inicio)
                ->getBorders()
                ->getTop()
                ->setBorderStyle(
                    \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
                );
            $sheet
                ->getStyle($columna . $inicio)
                ->getBorders()
                ->getBottom()
                ->setBorderStyle(
                    \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
                );
            $sheet
                ->getStyle($columna . $inicio)
                ->getBorders()
                ->getLeft()
                ->setBorderStyle(
                    \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
                );
            $sheet
                ->getStyle($columna . $inicio)
                ->getBorders()
                ->getRight()
                ->setBorderStyle(
                    \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
                );
        }
    }
}
