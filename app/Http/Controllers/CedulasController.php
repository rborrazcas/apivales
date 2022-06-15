<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use DB;
use JWTAuth;
use Illuminate\Contracts\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use Validator;
use App\Cedula;
use App\VNegociosFiltros;
use Arr;
use Carbon\Carbon as time;
use GuzzleHttp\Client;
use HTTP_Request2;
use File;

class CedulasController extends Controller
{
    function getPermisos()
    {
        $user = auth()->user();

        $permisos = DB::table('users_menus')
            ->where(['idUser' => $user->id, 'idMenu' => '13'])
            ->get()
            ->first();
        return $permisos;
    }

    function getPrograma($folio)
    {
        if (!is_null($folio)) {
            $q = str_contains($folio, 'Q3450');
            $q2 = str_contains($folio, 'q3450');
            if ($q || $q2) {
                return 1; //1 para vales
            } else {
                return 2;
            } //2 para calentadores
        } else {
            return 0;
        } //No existe folio
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
                "call getEstatusGlobalVentanillaVales('" .
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
                " call getEstatusGlobalVentanillaValesRegional('" .
                $idUserOwner->idUserOwner .
                "')";
        }

        if ($procedimiento === '') {
            $procedimiento = 'call getEstatusGlobalVentanillaValesGeneral';
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

    function getMunicipiosVales(Request $request)
    {
        $parameters = $request->all();
        $user = auth()->user();
        $userName = DB::table('users_aplicativo_web')
            ->selectRaw('UserName,Region')
            ->where('idUser', $user->id)
            ->get()
            ->first();
        $permisos = $this->getPermisos();
        try {
            if ($permisos->ViewAll < 1 && $permisos->Seguimiento < 1) {
                $res_Vales = DB::table('cedulas_solicitudes')
                    ->select('MunicipioVive as municipio')
                    ->where('idUsuarioCreo', $user->id)
                    ->orWhere('UsuarioAplicativo', $userName->UserName);
            } elseif ($permisos->ViewAll < 1) {
                $region = '';
                if ($userName->Region == 'I') {
                    $region = 1;
                } elseif ($userName->Region == 'II') {
                    $region = 2;
                } elseif ($userName->Region == 'III') {
                    $region = 3;
                } elseif ($userName->Region == 'IV') {
                    $region = 4;
                } elseif ($userName->Region == 'V') {
                    $region = 5;
                } elseif ($userName->Region == 'VI') {
                    $region = 6;
                } elseif ($userName->Region == 'VII') {
                    $region = 7;
                }

                $res_Vales = DB::table('et_cat_municipio')
                    ->select('Nombre as municipio')
                    ->where('SubRegion', $region);
            } else {
                $res_Vales = DB::table('et_cat_municipio')->select(
                    'Nombre as municipio'
                );
            }

            $res_Vales = $res_Vales->groupBy('municipio');
            $res_Vales = $res_Vales->get();

            $arrayMPios = [];

            foreach ($res_Vales as $data) {
                $arrayMPios[] = $data->municipio;
            }

            $res = DB::table('et_cat_municipio')
                ->select('Id', 'Nombre', 'Region', 'SubRegion')
                ->whereIn('Nombre', $arrayMPios)
                ->get();

            return [
                'success' => true,
                'results' => true,
                'data' => $res,
            ];
        } catch (QueryException $e) {
            return [
                'success' => false,
                'errors' => $e->getMessage(),
            ];
        }
    }

    function getCatalogsCedula(Request $request)
    {
        try {
            $estadoCivi = DB::table('cat_estado_civil')
                ->select('id AS value', 'EstadoCivil AS label')
                ->get();

            $entidades = DB::table('cat_entidad')
                ->select('id AS value', 'Entidad AS label', 'Clave_CURP')
                ->where('id', '<>', 1)
                ->get();

            $parentescosJefe = DB::table('cat_parentesco_jefe_hogar')
                ->select('id AS value', 'Parentesco AS label')
                ->get();

            $parentescosTutor = DB::table('cat_parentesco_tutor')
                ->select('id AS value', 'Parentesco AS label')
                ->get();

            $situaciones = DB::table('cat_situacion_actual')
                ->select('id AS value', 'Situacion AS label')
                ->get();

            $municipios = DB::table('et_cat_municipio')
                ->select('id AS value', 'Nombre AS label')
                ->get();

            $archivos_clasificacion = DB::table('cedula_archivos_clasificacion')
                ->select('id AS value', 'Clasificacion AS label')
                ->get();

            $catalogs = [
                'entidades' => $entidades,
                'cat_parentesco_jefe_hogar' => $parentescosJefe,
                'cat_parentesco_tutor' => $parentescosTutor,
                'cat_situacion_actual' => $situaciones,
                'cat_estado_civil' => $estadoCivi,
                'archivos_clasificacion' => $archivos_clasificacion,
                'municipios' => $municipios,
            ];

            $response = [
                'success' => true,
                'results' => true,
                'data' => $catalogs,
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

    function getLocalidadesByMunicipio(Request $request, $id)
    {
        try {
            $params = $request->all();
            $localidades = DB::table('cat_localidad_cedula')
                ->select('id AS value', 'Nombre AS label')
                ->where('IdMunicipio', $id)
                ->get();
            $response = [
                'success' => true,
                'results' => true,
                'data' => $localidades,
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

    function getAgebsManzanasByLocalidad(Request $request, $id)
    {
        try {
            $params = $request->all();
            $agebs = DB::table('cat_ageb_cedula')
                ->select('id AS value', 'CVE_AGEB AS label')
                ->where('IdLocalidad', $id)
                ->get();
            $manzanas = DB::table('cat_manzana_cedula')
                ->select('id AS value', 'CVE_MZA AS label')
                ->where('IdLocalidad', $id)
                ->get();

            $ambito = DB::table('cat_localidad_cedula')
                ->select('Ambito')
                ->where('Id', $id)
                ->first();
            $response = [
                'success' => true,
                'results' => true,
                'data' => [
                    'agebs' => $agebs,
                    'manzanas' => $manzanas,
                    'ambito' => $ambito,
                ],
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

    function getSolicitudes(Request $request)
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
            $programa = $params['programa'];

            $userId = JWTAuth::parseToken()->toUser()->id;

            DB::table('users_filtros')
                ->where('UserCreated', $userId)
                ->where('api', 'getValesVentanilla')
                ->delete();

            $parameters_serializado = serialize($params);

            //Insertamos los filtros
            DB::table('users_filtros')->insert([
                'UserCreated' => $userId,
                'Api' => 'getValesVentanilla',
                'Consulta' => $parameters_serializado,
                'created_at' => date('Y-m-d h-m-s'),
            ]);

            if ($programa > 1) {
                $tableSol = 'calentadores_solicitudes';
                $tableCedulas =
                    '(SELECT * FROM calentadores_cedulas WHERE FechaElimino IS NULL) AS calentadores_cedulas';
                $tableName = 'calentadores_cedulas';
            } else {
                $tableSol = 'cedulas_solicitudes';
                $tableCedulas =
                    '(SELECT * FROM cedulas WHERE FechaElimino IS NULL) AS cedulas';
                $tableName = 'cedulas';
            }

            $user = auth()->user();

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

            $solicitudes = DB::table($tableSol)

                ->selectRaw(
                    $tableSol .
                        ".*, 
                            entidadesNacimiento.Entidad AS EntidadNacimiento, " .
                        'cat_estado_civil.EstadoCivil, ' .
                        'cat_parentesco_jefe_hogar.Parentesco, ' .
                        'cat_parentesco_tutor.Parentesco, ' .
                        'entidadesVive.Entidad AS EntidadVive, ' .
                        'm.Region AS RegionM, ' .
                        'CASE ' .
                        'WHEN ' .
                        'cedulas_solicitudes.idUsuarioCreo = 1312 ' .
                        'THEN ' .
                        'ap.Nombre ' .
                        'ELSE ' .
                        "CONCAT_WS( ' ', creadores.Nombre, creadores.Paterno, creadores.Materno ) " .
                        'END AS CreadoPor, ' .
                        "CONCAT_WS( ' ', editores.Nombre, editores.Paterno, editores.Materno ) AS ActualizadoPor, " .
                        $tableSol .
                        '.ListaParaEnviar'
                )
                ->leftJoin(
                    'cat_entidad AS entidadesNacimiento',
                    'entidadesNacimiento.id',
                    $tableSol . '.idEntidadNacimiento'
                )
                ->leftJoin(
                    'cat_estado_civil',
                    'cat_estado_civil.id',
                    $tableSol . '.idEstadoCivil'
                )
                ->leftJoin(
                    'cat_parentesco_jefe_hogar',
                    'cat_parentesco_jefe_hogar.id',
                    $tableSol . '.idParentescoJefeHogar'
                )
                ->leftJoin(
                    'cat_parentesco_tutor',
                    'cat_parentesco_tutor.id',
                    $tableSol . '.idParentescoTutor'
                )
                ->leftJoin(
                    'cat_entidad AS entidadesVive',
                    'entidadesVive.id',
                    $tableSol . '.idEntidadVive'
                )
                ->leftJoin(
                    'users AS creadores',
                    'creadores.id',
                    $tableSol . '.idUsuarioCreo'
                )
                ->leftJoin(
                    'users AS editores',
                    'editores.id',
                    $tableSol . '.idUsuarioActualizo'
                )
                // ->leftJoin(
                //     DB::raw($tableCedulas),
                //     $tableName . '.idSolicitud',
                //     $tableSol . '.id'
                // )
                ->leftJoin(
                    'users_aplicativo as ap',
                    'ap.UserName',
                    $tableSol . '.UsuarioAplicativo'
                )
                ->leftJoin(
                    'et_cat_municipio as m',
                    'm.Nombre',
                    $tableSol . '.MunicipioVive'
                )
                ->whereNull($tableSol . '.FechaElimino');
            $filterQuery = '';
            $municipioRegion = [];
            $mun = [];
            if (isset($params['filtered']) && count($params['filtered']) > 0) {
                //dd($params['filtered']);
                $filtersCedulas = ['.id'];
                foreach ($params['filtered'] as $filtro) {
                    if ($filtro['id'] != '.ListaParaEnviarCedula') {
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

                        if (in_array($id, $filtersCedulas)) {
                            $id = $tableCedulas . $id;
                        } else {
                            $id = $tableSol . $id;
                        }

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
                ->where('api', '=', 'getValesVentanilla')
                ->first();

            if ($filtro_usuario) {
                $filtro_usuario->parameters = $parameters_serializado;
                $filtro_usuario->updated_at = time::now();
                $filtro_usuario->update();
            } else {
                $objeto_nuevo = new VNegociosFiltros();
                $objeto_nuevo->api = 'getValesVentanilla';
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
                    'Telefono' => $data->Telefono,
                    'TelRecados' => $data->TelRecados,
                    'Correo' => $data->Correo,
                    'idParentescoTutor' => $data->idParentescoTutor,
                    'NombreTutor' => $data->NombreTutor,
                    'PaternoTutor' => $data->PaternoTutor,
                    'MaternoTutor' => $data->MaternoTutor,
                    'FechaNacimientoTutor' => $data->FechaNacimientoTutor,
                    'EdadTutor' => $data->EdadTutor,
                    'CURPTutor' => $data->CURPTutor,
                    'TelefonoTutor' => $data->TelefonoTutor,
                    'CorreoTutor' => $data->CorreoTutor,
                    'NecesidadSolicitante' => $data->NecesidadSolicitante,
                    'CostoNecesidad' => $data->CostoNecesidad,
                    'idEntidadVive' => $data->idEntidadVive,
                    'MunicipioVive' => $data->MunicipioVive,
                    'LocalidadVive' => $data->LocalidadVive,
                    'CPVive' => $data->CPVive,
                    'ColoniaVive' => $data->ColoniaVive,
                    'CalleVive' => $data->CalleVive,
                    'NoExtVive' => $data->NoExtVive,
                    'NoIntVive' => $data->NoIntVive,
                    'Referencias' => $data->Referencias,
                    'idEstatus' => $data->idEstatus,
                    'idUsuarioCreo' => $data->idUsuarioCreo,
                    'FechaCreo' => $data->FechaCreo,
                    'idUsuarioActualizo' => $data->idUsuarioActualizo,
                    'FechaActualizo' => $data->FechaActualizo,
                    'SexoTutor' => $data->SexoTutor,
                    'idEntidadNacimientoTutor' =>
                        $data->idEntidadNacimientoTutor,
                    'Folio' => $data->Folio,
                    'ListaParaEnviar' => $data->ListaParaEnviar,
                    'idUsuarioElimino' => $data->idUsuarioElimino,
                    'FechaElimino' => $data->FechaElimino,
                    'UsuarioAplicativo' => $data->UsuarioAplicativo,
                    'Region' => $data->Region,
                    'Enlace' => $data->Enlace,
                    'idSolicitudAplicativo' => $data->idSolicitudAplicativo,
                    'Latitud' => $data->Latitud,
                    'Longitud' => $data->Longitud,
                    'IngresoMensual' => $data->IngresoMensual,
                    'OtrosIngresos' => $data->OtrosIngresos,
                    'TotalIngreso' => $data->TotalIngreso,
                    'PersonasDependientes' => $data->PersonasDependientes,
                    'IngresoPercapita' => $data->IngresoPercapita,
                    'OcupacionJefeHogar' => $data->OcupacionJefeHogar,
                    'idVale' => $data->idVale,
                    'EntidadNacimiento' => $data->EntidadNacimiento,
                    'EstadoCivil' => $data->EstadoCivil,
                    'Parentesco' => $data->Parentesco,
                    'EntidadVive' => $data->EntidadVive,
                    'RegionM' => $data->RegionM,
                    'CreadoPor' => $data->CreadoPor,
                    'ActualizadoPor' => $data->ActualizadoPor,
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

    function createSolicitud(Request $request)
    {
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
                'Folio' => 'required',
            ]);

            // if ($v->fails()){
            //     $response =  [
            //         'success'=>true,
            //         'results'=>false,
            //         'errors'=>$v->errors()
            //     ];
            //     return response()->json($response,200);
            // }

            $params = $request->all();
            $idAplicativo = '';

            if (!isset($params['Folio'])) {
                $program = 1;
            } else {
                $program = $this->getPrograma($params['Folio']);
            }

            if ($program > 1) {
                $tableSol = 'calentadores_solicitudes';
            } else {
                $tableSol = 'cedulas_solicitudes';
            }

            if (isset($params['idSolicitudAplicativo'])) {
                $idAplicativo = $params['idSolicitudAplicativo'];
            }

            $newClasificacion = isset($params['NewClasificacion'])
                ? $params['NewClasificacion']
                : [];
            $files = isset($params['NewFiles']) ? $params['NewFiles'] : [];
            unset($params['NewClasificacion']);
            unset($params['NewFiles']);

            $user = auth()->user();
            $params['idEstatus'] = 1;
            $params['Correo'] =
                isset($params['Correo']) && $params['Correo'] != ''
                    ? ($params['Correo'] == 'correo@electronico'
                        ? ''
                        : $params['Correo'])
                    : '';
            $params['TelRecados'] =
                isset($params['TelRecados']) && $params['TelRecados'] != ''
                    ? ($params['TelRecados'] == '0'
                        ? ''
                        : $params['TelRecados'])
                    : '';

            $necesidad = '';
            $costo = '';
            if ($program == 1) {
                $necesidad = 'VALES GRANDEZA';
                $costo = '500';
            } else {
                $necesidad = 'CALENTADOR SOLAR';
                $costo = '8145';
            }

            $params['NecesidadSolicitante'] = $necesidad;
            $params['CostoNecesidad'] = $costo;
            unset($params['programa']);

            if (isset($params['Folio'])) {
                $folioRegistrado = DB::table($tableSol)
                    ->where(['Folio' => $params['Folio']])
                    ->whereRaw('FechaElimino IS NULL')
                    ->first();
                if ($folioRegistrado != null) {
                    if ($program == 2) {
                        $folioRegistradoCalentadores = DB::table($tableSol)
                            ->where([$tableSol . '.Folio' => $params['Folio']])
                            ->whereRaw($tableSol . '.FechaElimino IS NULL')
                            ->leftjoin(
                                'calentadores_cedulas',
                                'calentadores_cedulas.idSolicitud',
                                $tableSol . '.id'
                            )
                            ->first();
                        if (
                            $folioRegistradoCalentadores->ListaParaEnviar == 1
                        ) {
                            $response = [
                                'success' => true,
                                'results' => false,
                                'errors' =>
                                    'El Folio ' .
                                    $params['Folio'] .
                                    ' ya esta registrado para la persona ' .
                                    $folioRegistrado->Nombre .
                                    ' ' .
                                    $folioRegistrado->Paterno .
                                    ' ' .
                                    $folioRegistrado->Materno .
                                    ' con CURP ' .
                                    $folioRegistrado->CURP,
                                'message' => [
                                    'idSolicitud' => $folioRegistrado->id,
                                    'Folio' => $params['Folio'],
                                    'CURP' => $folioRegistrado->CURP,
                                    'Nombre' => $folioRegistrado->Nombre,
                                    'Paterno' => $folioRegistrado->Paterno,
                                    'Materno' => $folioRegistrado->Materno,
                                ],
                            ];
                            return response()->json($response, 200);
                        } else {
                            DB::table($tableSol)
                                ->where('id', $folioRegistrado->id)
                                ->update($params);
                            $response = [
                                'success' => true,
                                'results' => true,
                                'message' => [
                                    'idSolicitud' => $folioRegistrado->id,
                                    'Folio' => $params['Folio'],
                                    'CURP' => $folioRegistrado->CURP,
                                    'Nombre' => $folioRegistrado->Nombre,
                                    'Paterno' => $folioRegistrado->Paterno,
                                    'Materno' => $folioRegistrado->Materno,
                                ],
                                'data' => $folioRegistrado->id,
                            ];
                            return response()->json($response, 200);
                        }
                    } else {
                        $folioRegistradoVales = DB::table($tableSol)
                            ->where(['Folio' => $params['Folio']])
                            ->whereRaw('FechaElimino IS NULL')
                            ->whereRaw('ListaParaEnviar = "1"')
                            ->first();

                        if ($folioRegistradoVales != null) {
                            $response = [
                                'success' => true,
                                'results' => false,
                                'errors' =>
                                    'El Folio ' .
                                    $params['Folio'] .
                                    ' ya esta registrado para la persona ' .
                                    $folioRegistrado->Nombre .
                                    ' ' .
                                    $folioRegistrado->Paterno .
                                    ' ' .
                                    $folioRegistrado->Materno .
                                    ' con CURP ' .
                                    $folioRegistrado->CURP,
                                'message' => [
                                    'idSolicitud' => $folioRegistrado->id,
                                    'Folio' => $params['Folio'],
                                    'CURP' => $folioRegistrado->CURP,
                                    'Nombre' => $folioRegistrado->Nombre,
                                    'Paterno' => $folioRegistrado->Paterno,
                                    'Materno' => $folioRegistrado->Materno,
                                ],
                            ];
                            return response()->json($response, 200);
                        } else {
                            DB::table($tableSol)
                                ->where('id', $folioRegistrado->id)
                                ->update($params);
                            $response = [
                                'success' => true,
                                'results' => true,
                                'message' => [
                                    'idSolicitud' => $folioRegistrado->id,
                                    'Folio' => $params['Folio'],
                                    'CURP' => $folioRegistrado->CURP,
                                    'Nombre' => $folioRegistrado->Nombre,
                                    'Paterno' => $folioRegistrado->Paterno,
                                    'Materno' => $folioRegistrado->Materno,
                                ],
                                'data' => $folioRegistrado->id,
                            ];
                            return response()->json($response, 200);
                        }
                    }
                }
            }

            $idSol = null;

            if ($idAplicativo !== '') {
                $idSol = DB::table($tableSol)
                    ->selectRaw('id')
                    ->where('idSolicitudAplicativo', $idAplicativo)
                    ->first();
            }

            if ($idSol == null) {
                $params['idUsuarioCreo'] = $user->id;
                $params['FechaCreo'] = date('Y-m-d');
                DB::beginTransaction();

                $id = DB::table($tableSol)->insertGetId($params);

                if (isset($request->NewFiles) && $program === 1) {
                    $this->createSolicitudFiles(
                        $id,
                        $request->NewFiles,
                        $newClasificacion,
                        $user->id
                    );
                }
                DB::commit();
            } else {
                $id = $idSol->id;
                DB::table($tableSol)
                    ->where('id', $id)
                    ->update($params);

                if (isset($request->NewFiles) && $program === 1) {
                    $this->createSolicitudFiles(
                        $id,
                        $request->NewFiles,
                        $newClasificacion,
                        $user->id
                    );
                }
            }
            $response = [
                'success' => true,
                'results' => true,
                'message' => 'Solicitud creada con exito',
                'data' => $id,
            ];

            return response()->json($response, 200);
        } catch (Throwable $errors) {
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

    function updateSolicitud(Request $request)
    {
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
                'Folio' => 'required',
            ]);

            // if ($v->fails()){
            //     $response =  [
            //         'success'=>true,
            //         'results'=>false,
            //         'errors'=>$v->errors()
            //     ];
            //     return response()->json($response,200);
            // }

            $params = $request->all();
            $program = 1;
            if (!isset($params['Folio'])) {
                $program = 1;
            } else {
                $program = $this->getPrograma($params['Folio']);
            }

            if ($program > 1) {
                $tableSol = 'calentadores_solicitudes';
                $tableCedulas = 'calentadores_cedulas';
            } else {
                $tableSol = 'cedulas_solicitudes';
                $tableCedulas = 'cedulas';
            }

            $solicitud = DB::table($tableSol)
                ->select(
                    $tableSol . '.idEstatus',
                    $tableCedulas . '.id AS idCedula',
                    $tableSol . '.ListaParaEnviar'
                )
                ->leftJoin(
                    $tableCedulas,
                    $tableCedulas . '.idSolicitud',
                    $tableSol . '.id'
                )
                ->where($tableSol . '.id', $params['id'])
                ->first();

            try {
                if (
                    ($solicitud->idEstatus != 1 ||
                        isset($solicitud->idCedula)) &&
                    $program > 1
                ) {
                    $response = [
                        'success' => true,
                        'results' => false,
                        'errors' =>
                            'La solicitud no se puede editar, tiene una cédula activa o ya fue aceptada',
                    ];
                    return response()->json($response, 200);
                }
            } catch (Exception $e) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' =>
                        'Ocurrio un error al validar la cedula de la solicitud',
                ];
                return response()->json($response, 200);
            }

            // if(
            //     !isset($params['Celular']) && !isset($params['Telefono']) &&
            //     !isset($params['Correo']) && !isset($params['TelRecados'])
            // ){
            //     $response =  [
            //         'success'=>true,
            //         'results'=>false,
            //         'errors'=>"Agregue al menos un método de contacto"
            //     ];
            //     return response()->json($response,200);
            // }

            // if($params['Edad'] < 18){
            //     if(
            //         !isset($params['idParentescoTutor']) &&
            //         !isset($params['NombreTutor']) && !isset($params['PaternoTutor']) &&
            //         !isset($params['MaternoTutor']) && !isset($params['FechaNacimientoTutor']) &&
            //         !isset($params['EdadTutor']) && !isset($params['CURPTutor'])
            //     ){
            //         $response =  [
            //             'success'=>true,
            //             'results'=>false,
            //             'errors'=>"Información de tutor incompleta"
            //         ];
            //         return response()->json($response,200);
            //     }
            // }
            $oldClasificacion = isset($params['OldClasificacion'])
                ? $params['OldClasificacion']
                : [];
            $newClasificacion = isset($params['NewClasificacion'])
                ? $params['NewClasificacion']
                : [];

            $user = auth()->user();
            $id = $params['id'];
            unset($params['id']);
            unset($params['OldFiles']);
            unset($params['OldClasificacion']);
            unset($params['NewFiles']);
            unset($params['NewClasificacion']);

            $params['idUsuarioActualizo'] = $user->id;
            $params['FechaActualizo'] = date('Y-m-d');
            $params['idEstatus'] = 1;
            if (isset($params['ListaParaEnviar'])) {
                if ($params['ListaParaEnviar'] == 1) {
                    $params['idEstatus'] = 9;
                }
            }

            try {
                DB::table($tableSol)
                    ->where('id', $id)
                    ->update($params);
            } catch (Exception $e) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' =>
                        'Ocurrio un error al actualizar la informacion de la solicitud',
                ];
                return response()->json($response, 200);
            }
            try {
                $oldFiles = DB::table('solicitud_archivos')
                    ->select('id', 'idClasificacion')
                    ->where('idSolicitud', $id)
                    ->whereRaw('FechaElimino IS NULL')
                    ->get();
            } catch (Exception $e) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' => 'Error al actualizar los archivos - codigo 1',
                ];
                return response()->json($response, 200);
            }

            if (count($oldFiles) > 0) {
                $oldFilesIds = array_map(function ($o) {
                    return $o->id;
                }, $oldFiles->toArray());

                if (isset($request->NewFiles)) {
                    $this->createSolicitudFiles(
                        $id,
                        $request->NewFiles,
                        $newClasificacion,
                        $user->id
                    );
                }
                //Aqui
                if (isset($request->OldFiles)) {
                    $newIds = $this->updateSolicitudFiles(
                        $id,
                        $request->OldFiles,
                        $oldClasificacion,
                        $user->id,
                        $oldFilesIds,
                        $oldFiles,
                        $program
                    );
                }

                if (count($newIds) > 0) {
                    DB::table('solicitud_archivos')
                        ->whereIn('id', $newIds)
                        ->update([
                            'idUsuarioElimino' => $user->id,
                            'FechaElimino' => date('Y-m-d H:i:s'),
                        ]);
                }
            } elseif (isset($request->NewFiles)) {
                $this->createSolicitudFiles(
                    $id,
                    $request->NewFiles,
                    $newClasificacion,
                    $user->id
                );
            }
            $response = [
                'success' => true,
                'results' => true,
                'message' => 'Solicitud actualizada con éxito',
                'data' => [],
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

    function deleteSolicitud(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->toUser();
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

            if (!isset($params['Folio'])) {
                $program = 1;
            } else {
                $program = $this->getPrograma($params['Folio']);
            }

            if ($program > 1) {
                $tableSol = 'calentadores_solicitudes';
                $tableCedulas = 'calentadores_cedulas';
            } else {
                $tableSol = 'cedulas_solicitudes';
                $tableCedulas = 'cedulas';
            }

            $solicitud = DB::table($tableSol)
                ->select(
                    $tableSol . '.idEstatus',
                    $tableCedulas . '.id AS idCedula',
                    $tableSol . '.ListaParaEnviar'
                )
                ->leftJoin(
                    $tableCedulas,
                    $tableCedulas . '.idSolicitud',
                    $tableSol . '.id'
                )
                ->where($tableSol . '.id', $params['id'])
                ->first();
            if (
                ($solicitud->idEstatus != 1 || isset($solicitud->idCedula)) &&
                $program > 1
            ) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' =>
                        'La solicitud no se puede eliminar, tiene una cédula activa o ya fue aceptada',
                ];
                return response()->json($response, 200);
            }

            // DB::table($tableSol)
            //     ->where('id', $params['id'])
            //     ->delete();
            DB::table($tableSol)
                ->where('id', $params['id'])
                ->update([
                    'FechaElimino' => date('Y-m-d H:i:s'),
                    'idUsuarioElimino' => $user->id,
                ]);

            $oldFiles = DB::table('solicitud_archivos')
                ->select('id', 'idClasificacion')
                ->where('idSolicitud', $params['id'])
                ->whereRaw('FechaElimino IS NULL')
                ->get();
            $oldFilesIds = array_map(function ($o) {
                return $o->id;
            }, $oldFiles->toArray());

            if (count($oldFilesIds) > 0) {
                DB::table('solicitud_archivos')
                    ->whereIn('id', $oldFilesIds)
                    ->update([
                        'idUsuarioElimino' => $user->id,
                        'FechaElimino' => date('Y-m-d H:i:s'),
                    ]);
            }

            $response = [
                'success' => true,
                'results' => true,
                'message' => 'Solicitud eliminada con éxito',
                'data' => [],
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

    function getCatalogsCedulaCompletos(Request $request)
    {
        try {
            $cat_estado_civil = DB::table('cat_estado_civil')
                ->select('id AS value', 'EstadoCivil AS label')
                ->get();

            $entidades = DB::table('cat_entidad')
                ->select('id AS value', 'Entidad AS label', 'Clave_CURP')
                ->where('id', '<>', 1)
                ->get();

            $cat_parentesco_jefe_hogar = DB::table('cat_parentesco_jefe_hogar')
                ->select('id AS value', 'Parentesco AS label')
                ->get();

            $cat_parentesco_tutor = DB::table('cat_parentesco_tutor')
                ->select('id AS value', 'Parentesco AS label')
                ->get();

            $cat_situacion_actual = DB::table('cat_situacion_actual')
                ->select('id AS value', 'Situacion AS label')
                ->get();

            $cat_actividades = DB::table('cat_actividades')
                ->select('id AS value', 'Actividad AS label')
                ->get();

            $cat_codigos_dificultad = DB::table('cat_codigos_dificultad')
                ->select('id AS value', 'Grado AS label')
                ->get();

            $cat_enfermedades = DB::table('cat_enfermedades')
                ->select('id AS value', 'Enfermedad AS label')
                ->get();

            $cat_grados_educacion = DB::table('cat_grados_educacion')
                ->select('id AS value', 'Grado AS label')
                ->get();

            $cat_niveles_educacion = DB::table('cat_niveles_educacion')
                ->select('id AS value', 'Nivel AS label')
                ->get();

            $cat_prestaciones = DB::table('cat_prestaciones')
                ->select('id AS value', 'Prestacion AS label')
                ->get();

            $cat_situacion_actual = DB::table('cat_situacion_actual')
                ->select('id AS value', 'Situacion AS label')
                ->get();

            $cat_tipo_seguro = DB::table('cat_tipo_seguro')
                ->select('id AS value', 'Tipo AS label')
                ->get();

            $cat_tipos_agua = DB::table('cat_tipos_agua')
                ->select('id AS value', 'Agua AS label')
                ->get();

            $cat_tipos_combustibles = DB::table('cat_tipos_combustibles')
                ->select('id AS value', 'Combustible AS label')
                ->get();

            $cat_tipos_drenajes = DB::table('cat_tipos_drenajes')
                ->select('id AS value', 'Drenaje AS label')
                ->get();

            $cat_tipos_luz = DB::table('cat_tipos_luz')
                ->select('id AS value', 'Luz AS label')
                ->get();

            $cat_tipos_muros = DB::table('cat_tipos_muros')
                ->select('id AS value', 'Muro AS label')
                ->get();

            $cat_tipos_pisos = DB::table('cat_tipos_pisos')
                ->select('id AS value', 'Piso AS label')
                ->get();

            $cat_tipos_techos = DB::table('cat_tipos_techos')
                ->select('id AS value', 'Techo AS label')
                ->get();

            $cat_tipos_viviendas = DB::table('cat_tipos_viviendas')
                ->select('id AS value', 'Tipo AS label')
                ->get();

            $cat_periodicidad = DB::table('cat_periodicidad')
                ->select('id AS value', 'Periodicidad AS label')
                ->get();

            $archivos_clasificacion = DB::table('cedula_archivos_clasificacion')
                ->select('id AS value', 'Clasificacion AS label')
                ->get();

            $municipios = DB::table('et_cat_municipio')
                ->select('id AS value', 'Nombre AS label')
                ->get();

            // $localidades = DB::table('cat_localidad_cedula')
            //     ->select('id AS value', 'Nombre AS label')
            //     ->orderBy('label')
            //     ->get();

            $catalogs = [
                'entidades' => $entidades,
                'cat_parentesco_jefe_hogar' => $cat_parentesco_jefe_hogar,
                'cat_parentesco_tutor' => $cat_parentesco_tutor,
                'cat_situacion_actual' => $cat_situacion_actual,
                'cat_estado_civil' => $cat_estado_civil,
                'cat_actividades' => $cat_actividades,
                'cat_codigos_dificultad' => $cat_codigos_dificultad,
                'cat_enfermedades' => $cat_enfermedades,
                'cat_grados_educacion' => $cat_grados_educacion,
                'cat_niveles_educacion' => $cat_niveles_educacion,
                'cat_prestaciones' => $cat_prestaciones,
                'cat_situacion_actual' => $cat_situacion_actual,
                'cat_tipo_seguro' => $cat_tipo_seguro,
                'cat_tipos_combustibles' => $cat_tipos_combustibles,
                'cat_tipos_drenajes' => $cat_tipos_drenajes,
                'cat_tipos_luz' => $cat_tipos_luz,
                'cat_tipos_muros' => $cat_tipos_muros,
                'cat_tipos_pisos' => $cat_tipos_pisos,
                'cat_tipos_techos' => $cat_tipos_techos,
                'cat_tipos_viviendas' => $cat_tipos_viviendas,
                'cat_tipos_agua' => $cat_tipos_agua,
                'cat_periodicidad' => $cat_periodicidad,
                'archivos_clasificacion' => $archivos_clasificacion,
                'municipios' => $municipios,
                //'localidades' => $localidades,
            ];

            $response = [
                'success' => true,
                'results' => true,
                'data' => $catalogs,
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

    function getClasificacionArchivos(Request $request)
    {
        try {
            $archivos_clasificacion = DB::table('cedula_archivos_clasificacion')
                ->select('id AS value', 'Clasificacion AS label')
                ->get();

            $response = [
                'success' => true,
                'results' => true,
                'data' => $archivos_clasificacion,
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
            // if ($v->fails()){
            //     $response =  [
            //         'success'=>true,
            //         'results'=>false,
            //         'errors'=>$v->errors()
            //     ];
            //     return response()->json($response,200);
            // }
            $params = $request->all();

            if (!isset($params['Folio'])) {
                $program = 1;
            } else {
                $program = $this->getPrograma($params['Folio']);
            }

            if ($program > 1) {
                $tableSol = 'calentadores_solicitudes';
                $tableCedulas = 'calentadores_cedulas';
                $tablePrestaciones = 'calentadores_prestaciones';
                $tableEnfermedades = 'calentadores_enfermedades';
                $tableAtnMedica = 'calentadores_atenciones_medicas';
            } else {
                $tableSol = 'cedulas_solicitudes';
                $tableCedulas = 'cedulas';
                $tablePrestaciones = 'cedulas_prestaciones';
                $tableEnfermedades = 'cedulas_enfermedades';
                $tableAtnMedica = 'cedulas_atenciones_medicas';
            }

            DB::beginTransaction();

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

            $user = auth()->user();
            $params['idUsuarioCreo'] = $user->id;
            $params['FechaCreo'] = date('Y-m-d');
            $params['Correo'] =
                isset($params['Correo']) && $params['Correo'] != ''
                    ? ($params['Correo'] == 'correo@electronico'
                        ? ''
                        : $params['Correo'])
                    : '';
            $params['TelRecados'] =
                isset($params['TelRecados']) && $params['TelRecados'] != ''
                    ? ($params['TelRecados'] == '0'
                        ? ''
                        : $params['TelRecados'])
                    : '';
            $params['idEstatus'] = 1;
            unset($params['Prestaciones']);
            unset($params['Enfermedades']);
            unset($params['AtencionesMedicas']);
            unset($params['NewClasificacion']);
            unset($params['NewFiles']);
            unset($params['idCedula']);
            unset($params['ListaParaEnviar']);
            unset($params['programa']);
            //GASTOS PERIODICIDAD
            if (!isset($params['GastoAlimentos'])) {
                $params['GastoAlimentos'] = 0;
            }
            if (!isset($params['GastoVestido'])) {
                $params['GastoVestido'] = 0;
            }
            if (!isset($params['GastoEducacion'])) {
                $params['GestoEducacion'] = 0;
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

            if (isset($params['idSolicitud'])) {
                $cedula = DB::table($tableCedulas)
                    ->where('idSolicitud', $params['idSolicitud'])
                    ->whereRaw('FechaElimino IS NULL')
                    ->first();

                if ($cedula != null) {
                    if ($cedula->ListaParaEnviar == 1) {
                        $response = [
                            'success' => true,
                            'results' => false,
                            'errors' =>
                                'Esta solicitud ya cuenta con una cedula lista para enviar',
                            'message' => [
                                'idSolicitud' => $cedula->idSolicitud,
                                'Folio' => $cedula->Folio,
                                'CURP' => $cedula->CURP,
                                'Nombre' => $cedula->Nombre,
                                'Paterno' => $cedula->Paterno,
                                'Materno' => $cedula->Materno,
                            ],
                        ];
                        return response()->json($response, 200);
                    } else {
                        $id = $cedula->id;
                        DB::table($tableCedulas)
                            ->where('id', $id)
                            ->update($params);
                        $programa = $this->getPrograma($params['Folio']);

                        $this->updateSolicitudFromCedula($params, $user);

                        DB::table($tablePrestaciones)
                            ->where('idCedula', $id)
                            ->delete();
                        $formatedPrestaciones = [];
                        foreach ($prestaciones as $prestacion) {
                            array_push($formatedPrestaciones, [
                                'idCedula' => $id,
                                'idPrestacion' => $prestacion,
                            ]);
                        }
                        DB::table($tablePrestaciones)->insert(
                            $formatedPrestaciones
                        );

                        DB::table($tableEnfermedades)
                            ->where('idCedula', $id)
                            ->delete();
                        $formatedEnfermedades = [];
                        foreach ($enfermedades as $enfermedad) {
                            array_push($formatedEnfermedades, [
                                'idCedula' => $id,
                                'idEnfermedad' => $enfermedad,
                            ]);
                        }
                        DB::table($tableEnfermedades)->insert(
                            $formatedEnfermedades
                        );

                        DB::table($tableAtnMedica)
                            ->where('idCedula', $id)
                            ->delete();
                        $formatedAtencionesMedicas = [];
                        foreach ($atencionesMedicas as $atencion) {
                            array_push($formatedAtencionesMedicas, [
                                'idCedula' => $id,
                                'idAtencionMedica' => $atencion,
                            ]);
                        }
                        DB::table($tableAtnMedica)->insert(
                            $formatedAtencionesMedicas
                        );

                        $response = [
                            'success' => true,
                            'results' => false,
                            'message' => [
                                'idSolicitud' => $params['idSolicitud'],
                                'Folio' => $cedula->Folio,
                                'CURP' => $cedula->CURP,
                                'Nombre' => $cedula->Nombre,
                                'Paterno' => $cedula->Paterno,
                                'Materno' => $cedula->Materno,
                            ],
                            'data' => $params['idSolicitud'],
                        ];

                        return response()->json($response, 200);
                    }
                }
            }

            $id = DB::table($tableCedulas)->insertGetId($params);
            $programa = $this->getPrograma($params['Folio']);

            $this->updateSolicitudFromCedula($params, $user);

            $formatedPrestaciones = [];
            foreach ($prestaciones as $prestacion) {
                array_push($formatedPrestaciones, [
                    'idCedula' => $id,
                    'idPrestacion' => $prestacion,
                ]);
            }
            DB::table($tablePrestaciones)->insert($formatedPrestaciones);

            $formatedEnfermedades = [];
            foreach ($enfermedades as $enfermedad) {
                array_push($formatedEnfermedades, [
                    'idCedula' => $id,
                    'idEnfermedad' => $enfermedad,
                ]);
            }
            DB::table($tableEnfermedades)->insert($formatedEnfermedades);

            $formatedAtencionesMedicas = [];
            foreach ($atencionesMedicas as $atencion) {
                array_push($formatedAtencionesMedicas, [
                    'idCedula' => $id,
                    'idAtencionMedica' => $atencion,
                ]);
            }
            DB::table($tableAtnMedica)->insert($formatedAtencionesMedicas);

            if (isset($request->NewFiles)) {
                $this->createCedulaFiles(
                    $id,
                    $request->NewFiles,
                    $newClasificacion,
                    $user->id,
                    $programa
                );
            }

            DB::commit();

            $response = [
                'success' => true,
                'results' => true,
                'message' => 'Creada con éxito',
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

    function getByIdV(Request $request, $id)
    {
        try {
            $cedula = DB::table('cedulas')
                ->selectRaw(
                    "
                            cedulas.*
                        "
                )
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
                ->where('cedulas.id', $id)
                ->first();

            $prestaciones = DB::table('cedulas_prestaciones')
                ->select('idPrestacion')
                ->where('idCedula', $id)
                ->get();

            $enfermedades = DB::table('cedulas_enfermedades')
                ->select('idEnfermedad')
                ->where('idCedula', $id)
                ->get();

            $atencionesMedicas = DB::table('cedulas_atenciones_medicas')
                ->select('idAtencionMedica')
                ->where('idCedula', $id)
                ->get();

            $archivos = DB::table('cedula_archivos')
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
            $cedula->Prestaciones = array_map(function ($o) {
                return $o->idPrestacion;
            }, $prestaciones->toArray());
            $cedula->Enfermedades = array_map(function ($o) {
                return $o->idEnfermedad;
            }, $enfermedades->toArray());
            $cedula->AtencionesMedicas = array_map(function ($o) {
                return $o->idAtencionMedica;
            }, $atencionesMedicas->toArray());
            $cedula->Files = $archivos;
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

    function getByIdC(Request $request, $id)
    {
        try {
            $cedula = DB::table('calentadores_cedulas')
                ->selectRaw(
                    "
                            calentadores_cedulas.*
                        "
                )
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
                ->where('calentadores_cedulas.id', $id)
                ->first();

            $prestaciones = DB::table('calentadores_prestaciones')
                ->select('idPrestacion')
                ->where('idCedula', $id)
                ->get();

            $enfermedades = DB::table('calentadores_enfermedades')
                ->select('idEnfermedad')
                ->where('idCedula', $id)
                ->get();

            $atencionesMedicas = DB::table('calentadores_atenciones_medicas')
                ->select('idAtencionMedica')
                ->where('idCedula', $id)
                ->get();

            $archivos = DB::table('calentadores_cedula_archivos')
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
            $cedula->Prestaciones = array_map(function ($o) {
                return $o->idPrestacion;
            }, $prestaciones->toArray());
            $cedula->Enfermedades = array_map(function ($o) {
                return $o->idEnfermedad;
            }, $enfermedades->toArray());
            $cedula->AtencionesMedicas = array_map(function ($o) {
                return $o->idAtencionMedica;
            }, $atencionesMedicas->toArray());
            $cedula->Files = $archivos;
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

    function getFilesByIdV(Request $request, $id)
    {
        try {
            $archivos2 = DB::table('solicitud_archivos')
                ->select(
                    'id',
                    'idClasificacion',
                    'NombreOriginal AS name',
                    'NombreSistema',
                    'Tipo AS type'
                )
                ->where('idSolicitud', $id)
                ->whereRaw('FechaElimino IS NULL')
                ->get();
            $archivosClasificacion = array_map(function ($o) {
                return $o->idClasificacion;
            }, $archivos2->toArray());
            $archivos = array_map(function ($o) {
                $o->ruta =
                    'https://apivales.apisedeshu.com/subidos/' .
                    $o->NombreSistema;
                // '/var/www/html/plataforma/apivales/public/subidos/' .
                // $o->NombreSistema;
                return $o;
            }, $archivos2->toArray());

            $response = [
                'success' => true,
                'results' => true,
                'message' => 'éxito',
                'data' => [
                    'Archivos' => $archivos,
                    'ArchivosClasificacion' => $archivosClasificacion,
                    'RutasArchivos',
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

    function getFilesByIdC(Request $request, $id)
    {
        try {
            $archivos2 = DB::table('calentadores_cedula_archivos')
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
                // '/var/www/html/plataforma/apivales/public/subidos/' .
                // $o->NombreSistema;
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
                'idTipoCombustibleAgua' => 'required',
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
            // if ($v->fails()){
            //     $response =  [
            //         'success'=>true,
            //         'results'=>false,
            //         'errors'=>$v->errors()
            //     ];
            //     return response()->json($response,200);
            // }
            $params = $request->all();
            $user = auth()->user();
            $id = $params['id'];
            unset($params['id']);
            if (!isset($params['Folio'])) {
                $program = 1;
            } else {
                $program = $this->getPrograma($params['Folio']);
            }

            if ($program > 1) {
                $tableSol = 'calentadores_solicitudes';
                $tableCedulas = 'calentadores_cedulas';
                $tablePrestaciones = 'calentadores_prestaciones';
                $tableEnfermedades = 'calentadores_enfermedades';
                $tableAtnMedica = 'calentadores_atenciones_medicas';
            } else {
                $tableSol = 'cedulas_solicitudes';
                $tableCedulas = 'cedulas';
                $tablePrestaciones = 'cedulas_prestaciones';
                $tableEnfermedades = 'cedulas_enfermedades';
                $tableAtnMedica = 'cedulas_atenciones_medicas';
            }

            $cedula = DB::table($tableCedulas)
                ->where('id', $id)
                ->WhereRaw('FechaElimino IS NULL')
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
                    : 'Sin correo';
            unset($params['Prestaciones']);
            unset($params['Enfermedades']);
            unset($params['AtencionesMedicas']);
            unset($params['OldFiles']);
            unset($params['OldClasificacion']);
            unset($params['NewFiles']);
            unset($params['NewClasificacion']);

            DB::table($tableCedulas)
                ->where('id', $id)
                ->update($params);

            $this->updateSolicitudFromCedula($params, $user);

            DB::table($tablePrestaciones)
                ->where('idCedula', $id)
                ->delete();
            $formatedPrestaciones = [];
            foreach ($prestaciones as $prestacion) {
                array_push($formatedPrestaciones, [
                    'idCedula' => $id,
                    'idPrestacion' => $prestacion,
                ]);
            }
            DB::table($tablePrestaciones)->insert($formatedPrestaciones);

            DB::table($tableEnfermedades)
                ->where('idCedula', $id)
                ->delete();
            $formatedEnfermedades = [];
            foreach ($enfermedades as $enfermedad) {
                array_push($formatedEnfermedades, [
                    'idCedula' => $id,
                    'idEnfermedad' => $enfermedad,
                ]);
            }
            DB::table($tableEnfermedades)->insert($formatedEnfermedades);

            DB::table($tableAtnMedica)
                ->where('idCedula', $id)
                ->delete();
            $formatedAtencionesMedicas = [];
            foreach ($atencionesMedicas as $atencion) {
                array_push($formatedAtencionesMedicas, [
                    'idCedula' => $id,
                    'idAtencionMedica' => $atencion,
                ]);
            }
            DB::table($tableAtnMedica)->insert($formatedAtencionesMedicas);

            $oldFiles = DB::table($tableArchivos)
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
                    $user->id,
                    $programa
                );
            }
            if (isset($request->OldFiles)) {
                $oldFilesIds = $this->updateCedulaFiles(
                    $id,
                    $request->OldFiles,
                    $oldClasificacion,
                    $user->id,
                    $oldFilesIds,
                    $oldFiles,
                    $programa
                );
            }

            if (count($oldFilesIds) > 0) {
                DB::table($tableArchivos)
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
            $oldClasificacion = isset($params['OldClasificacion'])
                ? $params['OldClasificacion']
                : [];
            $newClasificacion = isset($params['NewClasificacion'])
                ? $params['NewClasificacion']
                : [];
            $id = $params['id'];
            $user = auth()->user();
            $programa = $params['programa'];

            if ($programa > 1) {
                $tableArchivos = 'calentadores_cedula_archivos';
            } else {
                $tableArchivos = 'cedula_archivos';
            }

            DB::beginTransaction();
            $oldFiles = DB::table($tableArchivos)
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
                    $user->id,
                    $programa
                );
            }
            if (isset($request->OldFiles)) {
                $oldFilesIds = $this->updateCedulaFiles(
                    $id,
                    $request->OldFiles,
                    $oldClasificacion,
                    $user->id,
                    $oldFilesIds,
                    $oldFiles,
                    $programa
                );
            }

            if (count($oldFilesIds) > 0) {
                DB::table($tableArchivos)
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

    function updateArchivosSolicitud(Request $request)
    {
        try {
            $v = Validator::make($request->all(), [
                'id' => 'required',
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
            $oldClasificacion = isset($params['OldClasificacion'])
                ? $params['OldClasificacion']
                : [];
            $newClasificacion = isset($params['NewClasificacion'])
                ? $params['NewClasificacion']
                : [];
            $id = $params['id'];
            $user = auth()->user();
            $programa = $params['programa'];

            DB::beginTransaction();
            $oldFiles = DB::table('solicitud_archivos')
                ->select('id', 'idClasificacion')
                ->where('idSolicitud', $id)
                ->whereRaw('FechaElimino IS NULL')
                ->get();
            $oldFilesIds = array_map(function ($o) {
                return $o->id;
            }, $oldFiles->toArray());
            if (isset($request->NewFiles)) {
                $this->createSolicitudFiles(
                    $id,
                    $request->NewFiles,
                    $newClasificacion,
                    $user->id
                );
            }
            if (isset($request->OldFiles)) {
                $oldFilesIds = $this->updateSolicitudFiles(
                    $id,
                    $request->OldFiles,
                    $oldClasificacion,
                    $user->id,
                    $oldFilesIds,
                    $oldFiles,
                    $programa
                );
            }

            if (count($oldFilesIds) > 0) {
                DB::table('solicitud_archivos')
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
            $id = $params['id'];
            $programa = $params['programa'];

            if ($programa > 1) {
                $tableCedulas = 'calentadores_cedulas';
            } else {
                $tableCedulas = 'cedulas';
            }

            $cedula = DB::table($tableCedulas)
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

            DB::table('cedulas_prestaciones')
                ->where('idCedula', $id)
                ->delete();

            DB::table('cedulas_enfermedades')
                ->where('idCedula', $id)
                ->delete();

            DB::table('cedulas_atenciones_medicas')
                ->where('idCedula', $id)
                ->delete();

            DB::table('cedulas')
                ->where('id', $id)
                ->update([
                    'idUsuarioElimino' => $user->id,
                    'FechaElimino' => date('Y-m-d H:i:s'),
                ]);

            DB::table('cedula_archivos')
                ->where('idCedula', $id)
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

    public function enviarIGTO(Request $request)
    {
        $flag = false;
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

        try {
            $params = $request->all();
            $user = auth()->user();
            $id = $params['id'];
            $folio = DB::table('cedulas_solicitudes')
                ->select('Folio')
                ->where('id', $id)
                ->get()
                ->first();
        } catch (Exception $e) {
            $response = [
                'success' => false,
                'results' => false,
                'message' => 'Ocurrio un error al recuperar la solicitud',
            ];
            return response()->json($response, 200);
        }

        try {
            if ($folio != null) {
                $urlValidacionFolio =
                    'https://qa-api-utils-ventanilla-impulso.guanajuato.gob.mx/v1/application/external/validate/' .
                    $folio->Folio;
                $client = new Client();
                $response = $client->request('GET', $urlValidacionFolio, [
                    'verify' => false,
                    'headers' => [
                        'Content-Type' => 'multipart/form-data',
                        'Authorization' => '616c818fe33268648502g834',
                    ],
                ]);

                $responseBody = json_decode($response->getBody());
                if (!$responseBody->success) {
                    $response = [
                        'success' => true,
                        'results' => false,
                        'message' => 'El Folio de la solicitud no es válido',
                    ];
                    return response()->json($response, 200);
                }
            } else {
                $response = [
                    'success' => true,
                    'results' => false,
                    'message' =>
                        'La solicitud no cuenta con Folio, revise su información',
                ];
                return response()->json($response, 200);
            }
        } catch (Exception $e) {
            $response = [
                'success' => false,
                'results' => false,
                'message' =>
                    'Ocurrio un error al validar el Folio de la solicitud',
            ];
            return response()->json($response, 200);
        }

        $solicitud = DB::table('cedulas_solicitudes')
            ->selectRaw(
                "
                        cedulas_solicitudes.*, 
                        cat_estado_civil.EstadoCivil,
                        cat_parentesco_jefe_hogar.Parentesco AS ParentescoJefeHogar,
                        cat_situacion_actual.Situacion AS SituacionActual,
                        entidadNacimiento.Entidad AS EntidadNacimiento,
                        entidadVive.Entidad AS EntidadVive,
                        cat_parentesco_tutor.Parentesco AS ParentescoTutor
            "
            )
            ->leftjoin(
                'cat_estado_civil',
                'cat_estado_civil.id',
                'cedulas_solicitudes.idEstadoCivil'
            )
            ->leftjoin(
                'cat_parentesco_jefe_hogar',
                'cat_parentesco_jefe_hogar.id',
                'cedulas_solicitudes.idParentescoJefeHogar'
            )
            ->leftjoin(
                'cat_situacion_actual',
                'cat_situacion_actual.id',
                'cedulas_solicitudes.idSituacionActual'
            )
            ->leftJoin(
                'cat_parentesco_tutor',
                'cat_parentesco_tutor.id',
                'cedulas_solicitudes.idParentescoTutor'
            )
            ->leftjoin(
                'cat_entidad AS entidadNacimiento',
                'entidadNacimiento.id',
                'cedulas_solicitudes.idEntidadNacimiento'
            )
            ->leftjoin(
                'cat_entidad AS entidadVive',
                'entidadVive.id',
                'cedulas_solicitudes.idEntidadVive'
            )
            ->where('cedulas_solicitudes.id', $id)
            ->first();

        if (
            !isset($solicitud->MunicipioVive) ||
            !isset($solicitud->LocalidadVive)
        ) {
            $response = [
                'success' => true,
                'results' => false,
                'message' =>
                    'La solicitud no municipio o localidad, Revise su información',
            ];
            return response()->json($response, 200);
        }

        $files = DB::table('solicitud_archivos')
            ->select(
                'solicitud_archivos.*',
                'cedula_archivos_clasificacion.Clasificacion'
            )
            ->join(
                'cedula_archivos_clasificacion',
                'cedula_archivos_clasificacion.id',
                'solicitud_archivos.idClasificacion'
            )
            ->where('idSolicitud', $id)
            ->whereIn('cedula_archivos_clasificacion.id', [3, 5])
            ->whereRaw('solicitud_archivos.FechaElimino IS NULL')
            ->get();

        $filesEspecifico = DB::table('solicitud_archivos')
            ->select(
                'solicitud_archivos.*',
                'cedula_archivos_clasificacion.Clasificacion'
            )
            ->join(
                'cedula_archivos_clasificacion',
                'cedula_archivos_clasificacion.id',
                'solicitud_archivos.idClasificacion'
            )
            ->where('idSolicitud', $id)
            ->whereRaw('solicitud_archivos.FechaElimino IS NULL')
            ->where('cedula_archivos_clasificacion.id', '2')
            ->get();

        $solicitudJson = $this->formatSolicitudIGTOJson($solicitud);
        if (!$solicitudJson['success']) {
            $response = [
                'success' => true,
                'results' => false,
                'errors' => $solicitudJson['error'],
                'message' => $solicitudJson['error'],
            ];
            return response()->json($response, 200);
        }

        $solicitudJson = $solicitudJson['data'];
        $formatedFiles = $this->formatArchivos($files, 1);
        $infoFiles = $this->getInfoArchivos($files, 1);
        $formatedFilesEspecifico = $this->formatArchivos($filesEspecifico, 2);
        $infoFilesEspecifico = $this->getInfoArchivos($filesEspecifico, 2);

        $infoFiles = array_merge($infoFiles, $infoFilesEspecifico);

        $programa = json_encode(
            [
                'dependencia' => [
                    'sociedad' => '',
                    'codigo' => '0005',
                    'nombre' => 'SECRETARÍA DE DESARROLLO SOCIAL Y HUMANO',
                    'siglas' => 'SDSH',
                    'eje' => [
                        'codigo' => 'II',
                        'descripcion' => 'Desarrollo Humano y Social',
                    ],
                ],
                'programa' => [
                    'q' => 'Q3450',
                    'nombre' => 'Vale Grandeza - Compra Local',
                    'modalidad' => [
                        'nombre' => 'Vales Grandeza',
                        'clave' => 'Q3450-01',
                    ],
                    'tipoApoyo' => [
                        'clave' => 'Q3450-01-01',
                        'nombre' => 'Vales Grandeza',
                    ],
                ],
            ],
            JSON_UNESCAPED_UNICODE
        );

        $docs = json_encode(
            [
                'estandar' => $formatedFiles,
                'especifico' => $formatedFilesEspecifico,
            ],
            JSON_UNESCAPED_UNICODE
        );

        $cUsuario = $this->getCampoUsuario($solicitud);

        // $cUsuario = json_encode(
        //     [
        //         'nombre' => $solicitud->Enlace,
        //         'observaciones' => '',
        //     ],
        //     JSON_UNESCAPED_UNICODE
        // );

        if ($solicitud->idUsuarioCreo == 1312) {
            $authUsuario = $this->getAuthUsuario(
                $solicitud->UsuarioAplicativo,
                1
            );
        } else {
            $authUsuario = $this->getAuthUsuario($solicitud->idUsuarioCreo, 2);
        }
        // dd($authUsuario);
        // $authUsuario = json_encode(
        //     [
        //         'uid' => '626c06d49c1fce80afa1faa6',
        //         'name' => 'ALEJANDRA CAUDILLO OLMOS (RESPONSABLE Q)', //Cambiar a sedeshu
        //         'email' => 'acaudilloo@guanajuato.gob.mx',
        //         'role' => [
        //             'key' => 'RESPONSABLE_Q_ROL',
        //             'name' => 'Rol Responsable Programa VIM',
        //         ],
        //         'dependency' => [
        //             'name' => 'Secretaría de Desarrollo Social y Humano',
        //             'acronym' => 'SDSH',
        //             'office' => [
        //                 'address' =>
        //                     'Bugambilias esquina con calle Irapuato Las Margaritas 37234 León, Guanajuato',
        //                 'name' => 'Dirección de Articulación Regional IV',
        //                 'georef' => [
        //                     'type' => 'Point',
        //                     'coordinates' => [21.1378241, -101.6541802],
        //                 ],
        //             ],
        //         ],
        //     ],
        //     JSON_UNESCAPED_UNICODE
        // );

        $dataCompleted = [
            'solicitud' => $solicitudJson['solicitud'],
            'programa' => $programa,
            'documentos' => $docs,
            'authUsuario' => $authUsuario,
            'campoUsuario' => $cUsuario,
        ];

        $request2 = new HTTP_Request2();
        $request2->setUrl(
            //QA
            //'https://qa-api-utils-ventanilla-impulso.guanajuato.gob.mx/v1/application/external/solicitud/register'
            //Productivo
            //'https://api-utils-ventanilla-impulso.guanajuato.gob.mx/v1/application/external/solicitud/register'
            'https://api-integracion-ventanilla-impulso.guanajuato.gob.mx/v1/application/external/solicitud/register'
        );
        $request2->setMethod(HTTP_Request2::METHOD_POST);
        $request2->setConfig([
            'follow_redirects' => true,
            'ssl_verify_peer' => false,
            'ssl_verify_host' => false,
        ]);
        $request2->setHeader([
            'Authorization' => '616c818fe33268648502g834',
        ]);
        $request2->addPostParameter($dataCompleted);

        foreach ($infoFiles as $file) {
            $request2->addUpload(
                $file['llave'],
                $file['ruta'],
                $file['nombre'],
                $file['header']
            );
        }
        try {
            $response = $request2->send();
            $message = json_decode($response->getBody());
            if ($response->getStatus() == 200) {
                try {
                    $infoVale = $this->setVales($id);
                    $idVale = DB::table('vales')->insertGetId($infoVale);

                    $vale = DB::table('vales')
                        ->select(
                            'vales.*',
                            DB::raw('LPAD(HEX(vales.id),6,0) as ClaveUnica')
                        )
                        ->where('vales.id', '=', $idVale)
                        ->first();

                    DB::table('cedulas_solicitudes')
                        ->where('id', $id)
                        ->update([
                            'idEstatus' => '8',
                            'ListaParaEnviar' => '2',
                            'idVale' => $idVale,
                            'UsuarioEnvio' => $user->id,
                            'FechaEnvio' => date('Y-m-d H:i:s'),
                        ]);

                    return [
                        'success' => true,
                        'results' => true,
                        'message' => $vale->ClaveUnica,
                    ];
                    return response()->json($response2, 200);
                } catch (Exception $e) {
                    $response2 = [
                        'success' => true,
                        'results' => false,
                        'errors' => $e->errors,
                        'message' =>
                            'La solicitud fue enviada pero hubo un problema al actualizar la solicitud',
                    ];
                    return response()->json($response2, 200);
                }

                $response2 = [
                    'success' => true,
                    'results' => true,
                    'message' => $message,
                    'message' => 'Solicitud enviada con éxito',
                ];
                return response()->json($response2, 200);
            } else {
                $response2 = [
                    'success' => true,
                    'results' => false,
                    'errors' => $message,
                    'message' =>
                        'Ha ocurrido un error, consulte al administrador',
                ];
                return response()->json($response2, 200);
            }
        } catch (HTTP_Request2_Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            return ['success' => 'false', 'message' => $message];
        }
    }

    private function formatSolicitudIGTOJson($solicitud)
    {
        $client = new Client(); //GuzzleHttp\Client
        $url =
            'https://seguimiento.guanajuato.gob.mx/apiinformacionsocial/api/renapo/porcurp/pL@t_1n|Run$28/' .
            $solicitud->CURP .
            '/7';

        $response = $client->request('GET', $url, [
            'verify' => false,
        ]);
        $responseBody = json_decode($response->getBody());
        if ($responseBody->Mensaje !== 'OK') {
            return [
                'success' => false,
                'error' =>
                    $responseBody->Mensaje .
                    ' - ' .
                    $solicitud->CURP .
                    ' - ' .
                    $solicitud->Folio,
            ];
        }
        $curp = $responseBody->Resultado;

        $json = [
            'solicitud' => json_encode(
                [
                    'tipoSolicitud' => 'Ciudadana',
                    'origen' => 'F',
                    'tutor' => [
                        'respuesta' => $solicitud->idParentescoTutor != null,
                    ],

                    'datosCurp' => [
                        'folio' => $solicitud->Folio,
                        'curp' => $solicitud->CURP,
                        'entidadNacimiento' => $solicitud->EntidadNacimiento,
                        'fechaNacimientoDate' => date(
                            $solicitud->FechaNacimiento
                        ),
                        'fechaNacimientoTexto' => $solicitud->FechaNacimiento,
                        'genero' => $solicitud->Sexo,
                        'nacionalidad' => $curp->nacionalidad,
                        'nombre' => $solicitud->Nombre,
                        'primerApellido' =>
                            $solicitud->Paterno != 'XX'
                                ? $solicitud->Paterno
                                : 'X',
                        'segundoApellido' =>
                            $solicitud->Materno != 'XX'
                                ? $solicitud->Materno
                                : 'X',
                        'anioRegistro' => $curp->anioReg,
                        'descripcion' => $solicitud->NecesidadSolicitante,
                        'costoAproximado' => $solicitud->CostoNecesidad,
                    ],
                    'datosComplementarios' => [
                        'estadoCivil' => $solicitud->EstadoCivil,
                        'parentescoJefeHogar' => [
                            'codigo' =>
                                $solicitud->idParentescoJefeHogar < 3
                                    ? $solicitud->idParentescoJefeHogar
                                    : $solicitud->idParentescoJefeHogar - 1,
                            'descripcion' => $solicitud->ParentescoJefeHogar,
                        ],
                        'migrante' => [
                            'respuesta' =>
                                $solicitud->idSituacionActual !== 5 &&
                                $solicitud->idSituacionActual !== null,
                            'codigo' =>
                                $solicitud->idSituacionActual !== 5
                                    ? $solicitud->idSituacionActual
                                    : 0,
                            'descripcion' =>
                                $solicitud->idSituacionActual !== 5
                                    ? $solicitud->SituacionActual
                                    : 'No Aplica',
                        ],
                        'afroMexicano' => $solicitud->Afromexicano > 0,
                        'comunidadIndigena' => [
                            'respuesta' =>
                                $solicitud->ComunidadIndigena !== null,
                            'codigo' => 0,
                            'descripcion' =>
                                $solicitud->ComunidadIndigena !== null
                                    ? $solicitud->ComunidadIndigena
                                    : '',
                        ],
                        'hablaDialecto' => [
                            'respuesta' => $solicitud->Dialecto !== null,
                            'codigo' => 0,
                            'descripcion' =>
                                $solicitud->Dialecto !== null
                                    ? $solicitud->Dialecto
                                    : '',
                        ],
                        'tieneHijos' => [
                            'respuesta' =>
                                $solicitud->NumHijos > 0 ||
                                $solicitud->NumHijas > 0,
                            'descripcion' => [
                                'hombres' => $solicitud->NumHijos,
                                'mujeres' => $solicitud->NumHijas,
                            ],
                        ],
                    ],
                    'datosContacto' => [
                        'telefonos' => $this->getTelefonos($solicitud),
                        'correos' => is_null($this->getCorreos($solicitud))
                            ? []
                            : $this->getCorreos($solicitud),
                        'cp' => $solicitud->CPVive,
                        'asentamiento' => [
                            'tipo' => 'colonia',
                            'nombre' => $solicitud->ColoniaVive,
                        ],
                        'numeroExt' => $solicitud->NoExtVive,
                        'numeroInt' => $solicitud->NoIntVive,
                        'entidadFederativa' => $solicitud->EntidadVive,
                        'localidad' => $solicitud->LocalidadVive,
                        'municipio' => $solicitud->MunicipioVive,
                        'calle' => $solicitud->CalleVive,
                        'referencias' => is_null($solicitud->Referencias)
                            ? ''
                            : $solicitud->Referencias,
                        'solicitudImpulso' => $solicitud->TarjetaImpulso == 1,
                        'autorizaContacto' =>
                            $solicitud->ContactoTarjetaImpulso == 1,
                    ],
                ],
                JSON_UNESCAPED_UNICODE
            ),
        ];

        return ['success' => true, 'data' => $json];
    }

    private function getTelefonos($solicitud)
    {
        $telefonos = [];
        if ($solicitud->Celular != null) {
            array_push($telefonos, [
                'tipo' => 'Celular',
                'descripcion' => $solicitud->Celular,
            ]);
        }
        if ($solicitud->Telefono != null) {
            array_push($telefonos, [
                'tipo' => 'Teléfono de Casa',
                'descripcion' => $solicitud->Telefono,
            ]);
        }
        if ($solicitud->TelRecados != null) {
            array_push($telefonos, [
                'tipo' => 'Telefono de Recados',
                'descripcion' => $solicitud->TelRecados,
            ]);
        }
        // if ($solicitud->TelefonoTutor != null) {
        //     array_push($telefonos, [
        //         'tipo' => 'Teléfono del tutor',
        //         'descripcion' => $solicitud->TelefonoTutor,
        //     ]);
        // }
        return $telefonos;
    }

    private function getCorreos($solicitud)
    {
        $correos = [];
        if ($solicitud->Correo) {
            array_push($correos, [
                'tipo' => 'Personal',
                'descripcion' => $solicitud->Correo,
            ]);
        }
        if ($solicitud->CorreoTutor) {
            array_push($correos, [
                'tipo' => 'Corrreo del tutor',
                'descripcion' => $solicitud->CorreoTutor,
            ]);
        }
    }

    private function formatCedulaIGTOJson($solicitud, $catalogs)
    {
        $periodicidades = DB::table('cat_periodicidad')->get();

        return [
            'solicitudImpulso' => true,
            'cedulaImpulso' => true,
            // 'datosHogar' => [
            //     'numeroHogares' => $cedula->TotalHogares,
            //     'integrantesMujer' => $cedula->NumeroMujeresHogar,
            //     'integrantesHombre' => $cedula->NumeroHombresHogar,
            //     'menores18' => $cedula->PersonasMayoresEdad > 0,
            //     'mayores65' => $cedula->PersonasTerceraEdad > 0,
            //     'hombreJefeFamilia' => $cedula->PersonaJefaFamilia == 'H',
            // ],
            'datosHogar' => [
                'numeroHogares' => 0,
                'integrantesMujer' => 0,
                'integrantesHombre' => 0,
                'menores18' => false,
                'mayores65' => false,
                'hombreJefeFamilia' => false,
            ],
            'datosSalud' => [
                'limitacionMental' => false,
                'servicioMedico' => [
                    [
                        [
                            'respuesta' => false,
                            'codigo' => 1,
                            'descripcion' => 'Seguro Social IMSS',
                        ],
                        [
                            'respuesta' => false,
                            'codigo' => 2,
                            'descripcion' =>
                                'IMSS facultativo para estudiantes',
                        ],
                        [
                            'respuesta' => false,
                            'codigo' => 3,
                            'descripcion' => 'ISSSTE',
                        ],
                        [
                            'respuesta' => false,
                            'codigo' => 4,
                            'descripcion' => 'ISSSTE Estatal',
                        ],
                        [
                            'respuesta' => false,
                            'codigo' => 5,
                            'descripcion' => 'PEMEX, Defensa o Marina',
                        ],
                        [
                            'respuesta' => false,
                            'codigo' => 6,
                            'descripcion' => 'INSABI (antes Seguro Popular)',
                        ],
                        [
                            'respuesta' => false,
                            'codigo' => 7,
                            'descripcion' => 'Seguro Privado',
                        ],
                        [
                            'respuesta' => false,
                            'codigo' => 8,
                            'descripcion' => 'En otra institución',
                        ],
                        [
                            'respuesta' => false,
                            'codigo' => 9,
                            'descripcion' =>
                                'No tienen derecho a servicios médicos',
                        ],
                    ],
                ],
                'enfermedadCronica' => [
                    [
                        'respuesta' => false,
                        'codigo' => 1,
                        'descripcion' => 'Artritis Reumatoide',
                    ],
                    [
                        'respuesta' => false,
                        'codigo' => 2,
                        'descripcion' => 'Cáncer',
                    ],
                    [
                        'respuesta' => false,
                        'codigo' => 3,
                        'descripcion' => 'Cirrosis Hepática',
                    ],
                    [
                        'respuesta' => false,
                        'codigo' => 4,
                        'descripcion' => 'Insuficiencia Renal',
                    ],
                    [
                        'respuesta' => false,
                        'codigo' => 5,
                        'descripcion' => 'Diabetes Mellitus',
                    ],
                    [
                        'respuesta' => false,
                        'codigo' => 6,
                        'descripcion' => 'Cardiopatías',
                    ],
                    [
                        'respuesta' => false,
                        'codigo' => 7,
                        'descripcion' => 'Enfermedad Pulmonar Crónica',
                    ],
                    [
                        'respuesta' => false,
                        'codigo' => 8,
                        'descripcion' =>
                            'Deficiencia nutricional (Desnutrición)',
                    ],
                    [
                        'respuesta' => false,
                        'codigo' => 9,
                        'descripcion' => 'Hipertensión Arterial',
                    ],
                    [
                        'respuesta' => false,
                        'codigo' => 10,
                        'descripcion' => 'Obesidad',
                    ],
                    [
                        'respuesta' => false,
                        'codigo' => 11,
                        'descripcion' =>
                            'Adicción a la Ingestión de Sustancias (Drogas)',
                    ],
                    [
                        'respuesta' => false,
                        'codigo' => 12,
                        'descripcion' =>
                            'Adicciones de la conducta (Juego, internet)',
                    ],
                    [
                        'respuesta' => false,
                        'codigo' => 13,
                        'descripcion' => 'Depresión',
                    ],
                    [
                        'respuesta' => false,
                        'codigo' => 14,
                        'descripcion' => 'Ansiedad',
                    ],
                    [
                        'respuesta' => false,
                        'codigo' => 15,
                        'descripcion' => 'Trasplante de Órganos',
                    ],
                    [
                        'respuesta' => false,
                        'codigo' => 16,
                        'descripcion' => 'Ninguna',
                    ],
                ],
            ],
            'datosEducacion' => [
                'estudiante' => false,
                'ultimoNivel' => [
                    'codigo' => 0,
                    'descripcion' => '',
                ],
                'grado' => [
                    'codigo' => 0,
                    'descripcion' => '',
                ],
            ],
            'datosIngreso' => [
                'situacionEmpleo' => [
                    'codigo' => 0,
                    'descripcion' => '',
                ],
                'prestacionesTrabajo' => [
                    [
                        'respuesta' => false,
                        'codigo' => 1,
                        'descripcion' =>
                            'Incapacidad en caso de enfermedad, accidente o maternidad',
                    ],
                    [
                        'respuesta' => false,
                        'codigo' => 2,
                        'descripcion' => 'Aguinaldo',
                    ],
                    [
                        'respuesta' => false,
                        'codigo' => 3,
                        'descripcion' => 'Crédito de vivienda',
                    ],
                    [
                        'respuesta' => false,
                        'codigo' => 4,
                        'descripcion' => 'Guarderías y estancias infantiles',
                    ],
                    [
                        'respuesta' => false,
                        'codigo' => 5,
                        'descripcion' => 'SAR o AFORE',
                    ],
                    [
                        'respuesta' => false,
                        'codigo' => 6,
                        'descripcion' => 'Seguro de vida',
                    ],
                    [
                        'respuesta' => false,
                        'codigo' => 7,
                        'descripcion' =>
                            'No tienen prestaciones provenientes de su trabajo',
                    ],
                ],
                'totalIngreso' =>
                    $solicitud->TotalIngreso != null
                        ? $solicitud->TotalIngreso
                        : 0,
                'totalPension' => 0,
                'totalRemesa' => 0,
            ],
            'datosAlimentacion' => [
                'pocaVariedadAlimento' => false,
                'comioMenos' => false,
                'disminuyoCantidad' => false,
                'tuvoHambreNoComio' => false,
                'durmioConHambre' => false,
                'comioUnaVezoNo' => false,
            ],
            'discapacidad' => [
                'movilidadInferior' => 0,
                'visual' => 0,
                'habla' => 0,
                'auditivo' => 0,
                'valerse' => 0,
                'memoria' => 0,
                'movilidadSuperior' => 0,
            ],
            'datosGasto' => [
                'comida' => [
                    'gasto' => 0,
                    'periodo' => [
                        'codigo' => 0,
                        'descripcion' => '',
                    ],
                ],
                'ropa' => [
                    'gasto' => 0,
                    'periodo' => [
                        'codigo' => 0,
                        'descripcion' => '',
                    ],
                ],
                'educacion' => [
                    'gasto' => 0,
                    'periodo' => [
                        'codigo' => 0,
                        'descripcion' => '',
                    ],
                ],
                'medicina' => [
                    'gasto' => 0,
                    'periodo' => [
                        'codigo' => 0,
                        'descripcion' => '',
                    ],
                ],
                'consultas' => [
                    'gasto' => 0,
                    'periodo' => [
                        'codigo' => 0,
                        'descripcion' => '',
                    ],
                ],
                'combustible' => [
                    'gasto' => 0,
                    'periodo' => [
                        'codigo' => 0,
                        'descripcion' => '',
                    ],
                ],
                'serviciosBasicos' => [
                    'gasto' => 0,
                    'periodo' => [
                        'codigo' => 0,
                        'descripcion' => '',
                    ],
                ],
                'recreacion' => [
                    'gasto' => 0,
                    'periodo' => [
                        'codigo' => 0,
                        'descripcion' => '',
                    ],
                ],
            ],
            'datosVivienda' => [
                'estatusVivienda' => [
                    'codigo' => 0,
                    'descripcion' => '',
                ],
                'materialPiso' => [
                    'codigo' => 0,
                    'descripcion' => '',
                ],
                'materialPared' => [
                    'codigo' => 0,
                    'descripcion' => '',
                ],
                'materialTecho' => [
                    'codigo' => 0,
                    'descripcion' => '',
                ],
                'fuenteAgua' => [
                    'codigo' => 0,
                    'descripcion' => '',
                ],
                'drenaje' => [
                    'codigo' => 0,
                    'descripcion' => '',
                ],
                'fuenteLuzElectrica' => [
                    'codigo' => 0,
                    'descripcion' => '',
                ],
                'combustibleCocina' => [
                    'codigo' => 0,
                    'descripcion' => '',
                ],
                'numeroCuartos' => 0,
                'numeroPersonaHabitantes' => 0,
            ],
            'datosEnseres' => [
                'refrigerador' => false,
                'lavadora' => false,
                'computadora' => false,
                'estufa' => false,
                'boiler' => false,
                'calentadorSolar' => false,
                'tv' => false,
                'internet' => false,
                'celular' => false,
                'tinaco' => false,
            ],
            'percepcionSeguridad' => false,
        ];
    }

    private function formatArchivos($archivos, $index)
    {
        $files = [];
        $formato = '';
        if ($index == 1) {
            $formato = 'estandar';
        } else {
            $formato = 'especifico';
        }
        foreach ($archivos as $file) {
            //$fileConverted = fopen('subidos/' . $file->NombreSistema, 'r');
            $formatedFile = [
                'llave' =>
                    $formato . '_' . str_replace('.', '', $file->Clasificacion),
                'nombre' => $file->Clasificacion,
                'uid' => '',
                'vigencia' => '',
            ];

            array_push($files, $formatedFile);
        }
        return $files;
    }

    private function getInfoArchivos($archivos, $index)
    {
        $files = [];
        $formato = '';
        if ($index == 1) {
            $formato = 'estandar';
        } else {
            $formato = 'especifico';
        }
        foreach ($archivos as $file) {
            $fileContent = fopen('subidos/' . $file->NombreSistema, 'r');
            $formatedFile = [
                'llave' =>
                    $formato . '_' . str_replace('.', '', $file->Clasificacion),
                'ruta' =>
                    '/var/www/html/plataforma/apivales/public/subidos/' .
                    //'/Users/diegolopez/Documents/GitProyect/vales/apivales/public/subidos/' .
                    $file->NombreSistema,
                //'content' => $fileContent,
                'nombre' => str_replace('.', '', $file->Clasificacion),
                'header' => '<Content-Type Header>',
            ];
            array_push($files, $formatedFile);
        }
        return $files;
    }
    //'/Users/diegolopez/Documents/GitProyect/vales/apivales/public/subidos/' .
    // private function getInfoArchivos($archivos)
    // {
    //     $files = [];
    //     foreach ($archivos as $file) {
    //         $fileContent = fopen('subidos/' . $file->NombreSistema, 'r');
    //         $formatedFile = [
    //             'llave' =>
    //                 'estandar' . str_replace('.', '', $file->Clasificacion),
    //             'content' => $fileContent,
    //         ];
    //         array_push($files, $formatedFile);
    //     }
    //     return $files;
    // }

    private function updateSolicitudFromCedula($cedula, $user)
    {
        $params = [
            'FechaSolicitud' => $cedula['FechaSolicitud']
                ? $cedula['FechaSolicitud']
                : null,
            'FolioTarjetaImpulso' => $cedula['FolioTarjetaImpulso']
                ? $cedula['FolioTarjetaImpulso']
                : null,
            'Folio' => $cedula['Folio'] ? $cedula['Folio'] : null,
            'Nombre' => $cedula['Nombre'] ? $cedula['Nombre'] : null,
            'Paterno' => $cedula['Paterno'] ? $cedula['Paterno'] : null,
            'Materno' => $cedula['Materno'] ? $cedula['Materno'] : null,
            'FechaNacimiento' => $cedula['FechaNacimiento']
                ? $cedula['FechaNacimiento']
                : null,
            'Edad' => $cedula['Edad'] ? $cedula['Edad'] : null,
            'Sexo' => $cedula['Sexo'] ? $cedula['Sexo'] : null,
            'idEntidadNacimiento' => $cedula['idEntidadNacimiento']
                ? $cedula['idEntidadNacimiento']
                : null,
            'CURP' => $cedula['CURP'] ? $cedula['CURP'] : null,
            'RFC' => $cedula['RFC'] ? $cedula['RFC'] : null,
            'idEstadoCivil' => $cedula['idEstadoCivil']
                ? $cedula['idEstadoCivil']
                : null,
            'idParentescoJefeHogar' => $cedula['idParentescoJefeHogar']
                ? $cedula['idParentescoJefeHogar']
                : null,
            'NumHijos' => $cedula['NumHijos'] ? $cedula['NumHijos'] : null,
            'NumHijas' => $cedula['NumHijas'] ? $cedula['NumHijas'] : null,
            'ComunidadIndigena' => $cedula['ComunidadIndigena']
                ? $cedula['ComunidadIndigena']
                : null,
            'Dialecto' => $cedula['Dialecto'] ? $cedula['Dialecto'] : null,
            'Afromexicano' => $cedula['Afromexicano'] ?: null,
            'idSituacionActual' => $cedula['idSituacionActual'] ?: null,
            'TarjetaImpulso' => $cedula['TarjetaImpulso'] ?: null,
            'ContactoTarjetaImpulso' =>
                $cedula['ContactoTarjetaImpulso'] ?: null,
            'Celular' => $cedula['Celular'] ?: null,
            'Telefono' => $cedula['Telefono'] ? $cedula['Telefono'] : null,
            'TelRecados' => $cedula['TelRecados']
                ? $cedula['TelRecados']
                : null,
            'Correo' => $cedula['Correo'] ?: null,
            'idParentescoTutor' => $cedula['idParentescoTutor']
                ? $cedula['idParentescoTutor']
                : null,
            'NombreTutor' => $cedula['NombreTutor']
                ? $cedula['NombreTutor']
                : null,
            'PaternoTutor' => $cedula['PaternoTutor']
                ? $cedula['PaternoTutor']
                : null,
            'MaternoTutor' => $cedula['MaternoTutor']
                ? $cedula['MaternoTutor']
                : null,
            'FechaNacimientoTutor' => $cedula['FechaNacimientoTutor']
                ? $cedula['FechaNacimientoTutor']
                : null,
            'EdadTutor' => $cedula['EdadTutor'] ? $cedula['EdadTutor'] : null,
            'SexoTutor' => $cedula['SexoTutor'] ? $cedula['SexoTutor'] : null,
            'idEntidadNacimientoTutor' => $cedula['idEntidadNacimientoTutor']
                ? $cedula['idEntidadNacimientoTutor']
                : null,
            'CURPTutor' => $cedula['CURPTutor'] ? $cedula['CURPTutor'] : null,
            'TelefonoTutor' => $cedula['TelefonoTutor']
                ? $cedula['TelefonoTutor']
                : null,
            'CorreoTutor' => $cedula['CorreoTutor']
                ? $cedula['CorreoTutor']
                : null,
            'NecesidadSolicitante' => $cedula['NecesidadSolicitante'],
            'CostoNecesidad' => $cedula['CostoNecesidad'] ?: null,
            'idEntidadVive' => $cedula['idEntidadVive'] ?: null,
            'MunicipioVive' => $cedula['MunicipioVive'] ?: null,
            'LocalidadVive' => $cedula['LocalidadVive'] ?: null,
            'CPVive' => $cedula['CPVive'] ?: null,
            'ColoniaVive' => $cedula['ColoniaVive'] ?: null,
            'CalleVive' => $cedula['CalleVive'] ?: null,
            'NoExtVive' => $cedula['NoExtVive'] ?: null,
            'NoIntVive' => $cedula['NoIntVive'] ?: null,
            'Referencias' => $cedula['Referencias'] ?: null,
            'idUsuarioActualizo' => $user->id,
            'FechaActualizo' => date('Y-m-d'),
        ];

        if ($this->getPrograma($cedula['Folio']) > 1) {
            $tableSol = 'calentadores_solicitudes';
            $tableCedulas = 'calentadores_cedulas';
        } else {
            $tableSol = 'cedulas_solicitudes';
            $tableCedulas = 'cedulas';
        }

        DB::table($tableSol)
            ->where('id', $cedula['idSolicitud'])
            ->update($params);
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
        $userId,
        $programa
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

            if ($program > 1) {
                $tableArchivos = 'calentadores_cedulas_archivos';
            } else {
                $tableArchivos = 'cedula_archivos';
            }

            DB::table($tableArchivos)->insert($fileObject);
        }
    }

    private function updateCedulaFiles(
        $id,
        $files,
        $clasificationArray,
        $userId,
        $oldFilesIds,
        $oldFiles,
        $programa
    ) {
        if ($program > 1) {
            $tableArchivos = 'calentadores_cedulas_archivos';
        } else {
            $tableArchivos = 'cedula_archivos';
        }
        foreach ($files as $key => $file) {
            $fileAux = json_decode($file);
            $encontrado = array_search($fileAux->id, $oldFilesIds);
            if ($encontrado !== false) {
                if (
                    $oldFiles[$encontrado]->idClasificacion !=
                    $clasificationArray[$key]
                ) {
                    DB::table($tableArchivos)
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

    private function createSolicitudFiles(
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
                'idSolicitud' => intval($id),
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
            $tableArchivos = 'solicitud_archivos';
            // if($program>1){
            //     $tableArchivos = 'solicitud_archivos';
            // }else{
            //     $tableArchivos = 'cedula_archivos';
            // }

            DB::table($tableArchivos)->insert($fileObject);
        }
    }

    private function updateSolicitudFiles(
        $id,
        $files,
        $clasificationArray,
        $userId,
        $oldFilesIds,
        $oldFiles,
        $programa
    ) {
        // if ($program > 1) {
        //     $tableArchivos = 'calentadores_cedulas_archivos';
        // } else {
        //     $tableArchivos = 'cedula_archivos';
        // }
        $tableArchivos = 'solicitud_archivos';
        foreach ($files as $key => $file) {
            $fileAux = json_decode($file);
            $encontrado = array_search($fileAux->id, $oldFilesIds);
            if ($encontrado !== false) {
                if (
                    $oldFiles[$encontrado]->idClasificacion !=
                    $clasificationArray[$key]
                ) {
                    DB::table($tableArchivos)
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

    public function uploadFilesSolicitud(Request $request)
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
            $solicitud = DB::table('cedulas_solicitudes')
                ->select('idUsuarioCreo')
                ->where('cedulas_solicitudes.id', $id)
                ->first();
            if ($solicitud == null) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' => 'No se encuentra la solicitud',
                ];
                return response()->json($response, 200);
            }

            foreach ($files as $key => $file) {
                $imageContent = $this->imageBase64Content($file);
                $uniqueName = uniqid() . $extension[$key];
                $clasification = $arrayClasifiacion[$key];
                $originalName = $names[$key];

                File::put($fullPath . $uniqueName, $imageContent);

                // $extension = explode('.', $originalName);
                // $extension = $extension[count($extension) - 1];

                // $size = $file->getSize();
                // $clasification = $clasificationArray[$key];
                $fileObject = [
                    'idSolicitud' => intval($id),
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
                // $file->move('subidos', $uniqueName);

                $tableArchivos = 'solicitud_archivos';
                // if($program>1){
                //     $tableArchivos = 'solicitud_archivos';
                // }else{
                //     $tableArchivos = 'cedula_archivos';
                // }

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

    public function envioMasivoVentanilla(Request $request)
    {
        try {
            $solicitudesAEnviar = DB::table('valesrvii_ventanilla')
                ->select('id')
                ->get();

            $arraySinFolio = [];
            $arrayFoliosNoValidos = [];
            $arrayCURPNoValida = [];
            $arraySolicitudesIncompletas = [];
            $arrayEnviadas = [];

            foreach ($solicitudesAEnviar as $key) {
                $flag = $this->enviarIGTOMasivo($key->id);
                if (!$flag['success']) {
                    if ($flag['codigo'] == 1) {
                        $arraySinFolio[] = $key->id;
                    } elseif ($flag['codigo'] == 2) {
                        $arrayFoliosNoValidos[] = [
                            $key->id => $flag['data'],
                        ];
                    } elseif ($flag['codigo'] == 3) {
                        $arrayCURPNoValida[] = [
                            $key->id => $flag['data'],
                        ];
                    } elseif ($flag['codigo'] == 5) {
                        $arraySolicitudesIncompletas[] = [
                            $key->id => $flag['data'],
                        ];
                    }
                } else {
                    $arrayEnviadas[] = [
                        $key->id => $flag['data'],
                    ];
                }
            }

            $response = [
                'success' => true,
                'results' => true,
                'SinFolio' => $arraySinFolio,
                'FoliosNoValidos' => $arrayFoliosNoValidos,
                'CURPNoValida' => $arrayCURPNoValida,
                'SolicitudesNoEnviadas' => $arraySolicitudesIncompletas,
                'Enviadas' => $arrayEnviadas,
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

    public function enviarIGTOMasivo($id)
    {
        $flag = false;
        $folio = DB::table('cedulas_solicitudes')
            ->select('Folio')
            ->where('id', $id)
            ->get()
            ->first();

        if ($folio->Folio) {
            $urlValidacionFolio =
                'https://qa-api-utils-ventanilla-impulso.guanajuato.gob.mx/v1/application/external/validate/' .
                $folio->Folio;
            $client = new Client();
            $response = $client->request('GET', $urlValidacionFolio, [
                'verify' => false,
                'headers' => [
                    'Content-Type' => 'multipart/form-data',
                    'Authorization' => '616c818fe33268648502g834',
                ],
            ]);

            $responseBody = json_decode($response->getBody());
            if (!$responseBody->success) {
                return [
                    'success' => $flag,
                    'data' => 'Folio no encontrado ' . $folio->Folio,
                    'codigo' => '2',
                ];
            }
        } else {
            return [
                'success' => $flag,
                'data' => 'Solicitud sin folio',
                'codigo' => '1',
            ];
        }

        $solicitud = DB::table('cedulas_solicitudes')
            ->selectRaw(
                "
                            cedulas_solicitudes.*, 
                            cat_estado_civil.EstadoCivil,
                            cat_parentesco_jefe_hogar.Parentesco AS ParentescoJefeHogar,
                            cat_situacion_actual.Situacion AS SituacionActual,
                            entidadNacimiento.Entidad AS EntidadNacimiento,
                            entidadVive.Entidad AS EntidadVive,
                            cat_parentesco_tutor.Parentesco AS ParentescoTutor
                "
            )
            ->leftjoin(
                'cat_estado_civil',
                'cat_estado_civil.id',
                'cedulas_solicitudes.idEstadoCivil'
            )
            ->leftjoin(
                'cat_parentesco_jefe_hogar',
                'cat_parentesco_jefe_hogar.id',
                'cedulas_solicitudes.idParentescoJefeHogar'
            )
            ->leftjoin(
                'cat_situacion_actual',
                'cat_situacion_actual.id',
                'cedulas_solicitudes.idSituacionActual'
            )
            ->leftJoin(
                'cat_parentesco_tutor',
                'cat_parentesco_tutor.id',
                'cedulas_solicitudes.idParentescoTutor'
            )
            ->leftjoin(
                'cat_entidad AS entidadNacimiento',
                'entidadNacimiento.id',
                'cedulas_solicitudes.idEntidadNacimiento'
            )
            ->leftjoin(
                'cat_entidad AS entidadVive',
                'entidadVive.id',
                'cedulas_solicitudes.idEntidadVive'
            )
            ->where('cedulas_solicitudes.id', $id)
            ->first();

        $files = DB::table('solicitud_archivos')
            ->select(
                'solicitud_archivos.*',
                'cedula_archivos_clasificacion.Clasificacion'
            )
            ->join(
                'cedula_archivos_clasificacion',
                'cedula_archivos_clasificacion.id',
                'solicitud_archivos.idClasificacion'
            )
            ->where('idSolicitud', $id)
            ->whereIn('cedula_archivos_clasificacion.id', [3, 5])
            ->get();

        $filesEspecifico = DB::table('solicitud_archivos')
            ->select(
                'solicitud_archivos.*',
                'cedula_archivos_clasificacion.Clasificacion'
            )
            ->join(
                'cedula_archivos_clasificacion',
                'cedula_archivos_clasificacion.id',
                'solicitud_archivos.idClasificacion'
            )
            ->where('idSolicitud', $id)
            ->where('cedula_archivos_clasificacion.id', '2')
            ->get();

        $solicitudJson = $this->formatSolicitudIGTOJson($solicitud);
        if (!$solicitudJson['success']) {
            $response = [
                'success' => false,
                'data' => $solicitudJson['error'],
                'codigo' => '3',
            ];
            return $response;
        }

        $solicitudJson = $solicitudJson['data'];
        $formatedFiles = $this->formatArchivos($files, 1);
        $infoFiles = $this->getInfoArchivos($files, 1);
        $formatedFilesEspecifico = $this->formatArchivos($filesEspecifico, 2);
        $infoFilesEspecifico = $this->getInfoArchivos($filesEspecifico, 2);

        $infoFiles = array_merge($infoFiles, $infoFilesEspecifico);

        $programa = json_encode(
            [
                'dependencia' => [
                    'sociedad' => '',
                    'codigo' => '0005',
                    'nombre' => 'SECRETARÍA DE DESARROLLO SOCIAL Y HUMANO',
                    'siglas' => 'SDSH',
                    'eje' => [
                        'codigo' => 'II',
                        'descripcion' => 'Desarrollo Humano y Social',
                    ],
                ],
                'programa' => [
                    'q' => 'Q3450',
                    'nombre' => 'Vale Grandeza - Compra Local',
                    'modalidad' => [
                        'nombre' => 'Vales Grandeza',
                        'clave' => 'Q3450-01',
                    ],
                    'tipoApoyo' => [
                        'clave' => 'Q3450-01-01',
                        'nombre' => 'Vales Grandeza',
                    ],
                ],
            ],
            JSON_UNESCAPED_UNICODE
        );

        $docs = json_encode(
            [
                'estandar' => $formatedFiles,
                'especifico' => $formatedFilesEspecifico,
            ],
            JSON_UNESCAPED_UNICODE
        );

        $cUsuario = json_encode(
            [
                'nombre' => $solicitud->Enlace,
                'observaciones' => '',
            ],
            JSON_UNESCAPED_UNICODE
        );

        $authUsuario = json_encode(
            [
                'uid' => '626c06d49c1fce80afa1faa6',
                'name' => 'ALEJANDRA CAUDILLO OLMOS (RESPONSABLE Q)', //Cambiar a sedeshu
                'email' => 'acaudilloo@guanajuato.gob.mx',
                'role' => [
                    'key' => 'RESPONSABLE_Q_ROL',
                    'name' => 'ol Responsable Programa VIM',
                ],
                'dependency' => [
                    'name' => 'Secretaría de Desarrollo Social y Humano',
                    'acronym' => 'SDSH',
                    'office' => [
                        'address' =>
                            'Bugambilias esquina con calle Irapuato Las Margaritas 37234 León, Guanajuato',
                        'name' => 'Dirección de Articulación Regional IV',
                        'georef' => [
                            'type' => 'Point',
                            'coordinates' => [21.1378241, -101.6541802],
                        ],
                    ],
                ],
            ],
            JSON_UNESCAPED_UNICODE
        );

        $dataCompleted = [
            'solicitud' => $solicitudJson['solicitud'],
            'programa' => $programa,
            'documentos' => $docs,
            'authUsuario' => $authUsuario,
            'campoUsuario' => $cUsuario,
        ];

        $request = new HTTP_Request2();
        $request->setUrl(
            'https://qa-api-utils-ventanilla-impulso.guanajuato.gob.mx/v1/application/external/solicitud/register'
        );
        $request->setMethod(HTTP_Request2::METHOD_POST);
        $request->setConfig([
            'follow_redirects' => true,
            'ssl_verify_peer' => false,
            'ssl_verify_host' => false,
        ]);
        $request->setHeader([
            'Authorization' => '616c818fe33268648502g834',
        ]);
        $request->addPostParameter($dataCompleted);

        foreach ($infoFiles as $file) {
            //$documentos[$file['llave']] = $file['content'];
            $request->addUpload(
                $file['llave'],
                $file['ruta'],
                $file['nombre'],
                $file['header']
            );
        }
        //dd($request);
        try {
            $response = $request->send();
            if ($response->getStatus() == 200) {
                $flag = true;
                $message = json_decode($response->getBody());
                return [
                    'success' => $flag,
                    'data' => $message,
                    'codigo' => '4',
                ];
            } else {
                $message = $response->getBody();
            }
        } catch (HTTP_Request2_Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            return ['success' => 'false', 'data' => $message, 'codigo' => '5'];
        }
    }

    public function setVales($id)
    {
        $user = auth()->user();
        $solicitud = DB::table('cedulas_solicitudes')
            ->where('id', $id)
            ->get()
            ->first();

        $idMunicipio = DB::table('et_cat_municipio')
            ->select('id')
            ->where('Nombre', $solicitud->MunicipioVive)
            ->get()
            ->first();

        $idLocalidad = DB::table('et_cat_localidad')
            ->select('id')
            ->where([
                'idMunicipio' => $idMunicipio->id,
                'Nombre' => $solicitud->LocalidadVive,
            ])
            ->get()
            ->first();

        $userCreo = null;
        if ($solicitud->idUsuarioCreo == 1312) {
            if (
                $solicitud->UsuarioAplicativo != null &&
                $solicitud->UsuarioAplicativo != ''
            ) {
                $userCreo = DB::table('users_aplicativo_web')
                    ->select('idUser')
                    ->where('UserName', $solicitud->UsuarioAplicativo)
                    ->get()
                    ->first();
            }
        }

        $dataVales = [
            'FechaSolicitud' => $solicitud->FechaSolicitud,
            'idIncidencia' => '1',
            'CURP' => $solicitud->CURP,
            'Ocupacion' => $solicitud->OcupacionJefeHogar,
            'Nombre' => $solicitud->Nombre,
            'Paterno' => $solicitud->Paterno,
            'Materno' => $solicitud->Materno,
            'Sexo' => $solicitud->Sexo,
            'FechaNacimiento' => $solicitud->FechaNacimiento,
            'Calle' => $solicitud->CalleVive,
            'NumInt' => $solicitud->NoIntVive,
            'NumExt' => $solicitud->NoExtVive,
            'Colonia' => $solicitud->ColoniaVive,
            'CP' => $solicitud->CPVive,
            'idMunicipio' => $idMunicipio->id,
            'idLocalidad' => $idLocalidad->id,
            'TelFijo' => $solicitud->Telefono,
            'TelCelular' => $solicitud->Celular,
            'TelRecados' => $solicitud->TelRecados,
            'CorreoElectronico' => $solicitud->Correo,
            'idStatus' => 1,
            'IngresoPercibido' => $solicitud->IngresoMensual,
            'OtrosIngresos' => $solicitud->OtrosIngresos,
            'NumeroPersonas' => $solicitud->PersonasDependientes,
            'UserOwned' =>
                $solicitud->idUsuarioCreo == 1312 && $userCreo != null
                    ? $userCreo->idUser
                    : $solicitud->idUsuarioCreo,
            'TotalIngresos' => $solicitud->TotalIngreso,
            'OcupacionOtro' => 0,
            'UserCreated' => $user->id,
            'UserUpdated' => $user->id,
            'INEVencida' => 0,
            'isDocumentacionEntrega' => 0,
            'Bloqueado' => 1,
            'BloqueadoUser' => $user->id,
            'BloqueadoDate' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        return $dataVales;
    }

    public function getAuthUsuario($idUser, $index)
    {
        $flag = false;
        if ($idUser != null && $idUser != '') {
            $filtro = '';
            if ($index == 1) {
                $filtro = "UserName = '" . $idUser . "'";
            } else {
                $filtro = "idUser = '" . $idUser . "'";
            }
            $idRegional = DB::table('users_aplicativo_web')
                ->select('idUserOwner')
                ->whereRaw($filtro)
                ->get()
                ->first();
            if ($idRegional != null) {
                $datosRegional = DB::table('cuentas_regionales_ventanilla')
                    ->selectRaw('uId,Nombre,correoCuenta,rol')
                    ->where('idRegional', $idRegional->idUserOwner)
                    ->get()
                    ->first();
                if ($datosRegional != null) {
                    $json = json_encode(
                        [
                            'uid' => $datosRegional->uId,
                            'name' => $datosRegional->Nombre, //Cambiar a sedeshu
                            'email' => $datosRegional->correoCuenta,
                            'role' => [
                                'key' => $datosRegional->rol,
                                'name' => 'Rol Responsable Programa',
                            ],
                            'dependency' => [
                                'name' =>
                                    'Secretaría de Desarrollo Social y Humano',
                                'acronym' => 'SDSH',
                                'office' => [
                                    'address' =>
                                        'Bugambilias esquina con calle Irapuato Las Margaritas 37234 León, Guanajuato',
                                    'name' =>
                                        'Dirección de Articulación Regional IV',
                                    'georef' => [
                                        'type' => 'Point',
                                        'coordinates' => [
                                            21.1378241,
                                            -101.6541802,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        JSON_UNESCAPED_UNICODE
                    );
                } else {
                    $flag = true;
                }
            } else {
                $flag = true;
            }
        } else {
            $flag = true;
        }

        if ($flag) {
            $json = json_encode(
                [
                    'uid' => '626c06d49c1fce80afa1faa6',
                    'name' => 'ALEJANDRA CAUDILLO OLMOS (RESPONSABLE Q)', //Cambiar a sedeshu
                    'email' => 'acaudilloo@guanajuato.gob.mx',
                    'role' => [
                        'key' => 'RESPONSABLE_Q_ROL',
                        'name' => 'Rol Responsable Programa VIM',
                    ],
                    'dependency' => [
                        'name' => 'Secretaría de Desarrollo Social y Humano',
                        'acronym' => 'SDSH',
                        'office' => [
                            'address' =>
                                'Bugambilias esquina con calle Irapuato Las Margaritas 37234 León, Guanajuato',
                            'name' => 'Dirección de Articulación Regional IV',
                            'georef' => [
                                'type' => 'Point',
                                'coordinates' => [21.1378241, -101.6541802],
                            ],
                        ],
                    ],
                ],
                JSON_UNESCAPED_UNICODE
            );
        }
        return $json;
    }

    public function getReporteSolicitudVentanillaVales(Request $request)
    {
        $user = auth()->user();
        $parameters['UserCreated'] = $user->id;
        $tableSol = 'vales';
        $res = DB::table('cedulas_solicitudes as vales')
            ->select(
                'et_cat_municipio.SubRegion AS Region',
                //DB::raw('LPAD(HEX(vales.id),6,0) as ClaveUnica'),
                'vales.Folio AS Folio',
                'vales.FechaSolicitud',
                'vales.CURP',
                DB::raw(
                    "concat_ws(' ',vales.Nombre, vales.Paterno, vales.Materno) as NombreCompleto"
                ),
                'vales.Sexo',
                'vales.FechaNacimiento',
                'vales.OcupacionJefeHogar',
                DB::raw(
                    "concat_ws(' ',vales.CalleVive, concat('Num. ', vales.NoExtVive), if(vales.NoIntVive is not null,concat('NumInt. ',vales.NoIntVive), ''), concat('Col. ',vales.ColoniaVive)) as Direccion"
                ),
                'vales.CPVive',
                'vales.MunicipioVive AS Municipio',
                'vales.LocalidadVive AS Localidad',
                'vales.Telefono',
                'vales.Celular',
                'vales.TelRecados',
                'vales.Correo',
                'vales.IngresoMensual',
                'vales.OtrosIngresos',
                DB::raw(
                    '(vales.IngresoMensual + vales.OtrosIngresos) as TotalIngresos'
                ),
                'vales.PersonasDependientes',
                DB::raw("'Sin Incidencia' AS Incidencia"),
                DB::raw("
                    CASE
                        WHEN
                            vales.ListaParaEnviar = 1
                        THEN
                            'SI'
                        ELSE
                            'NO'
                        END
                    AS ListaParaEnviar
                    "),
                'vales_status.Estatus',
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
                'vales.Enlace AS Enlace'
            )
            ->leftJoin('vales_status', 'vales_status.id', '=', 'idEstatus')
            ->leftJoin('users', 'users.id', '=', 'vales.idUsuarioCreo')
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
            ->whereRaw('FechaElimino IS NULL');

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
            ->where('api', '=', 'getValesVentanilla')
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
                        if ($filtro['id'] != '.ListaParaEnviarCedula') {
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

                            if (in_array($id, $filtersCedulas)) {
                                $id = 'vales' . $id;
                            } else {
                                $id = 'vales' . $id;
                            }

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
        //$data2 = $resGrupo->first();

        //dd($data);

        if (count($data) == 0) {
            //return response()->json(['success'=>false,'results'=>false,'message'=>$res->toSql()]);
            $reader = IOFactory::createReader('Xlsx');
            $spreadsheet = $reader->load(
                public_path() . '/archivos/formatoReporteSolicitudValesV3.xlsx'
            );
            $writer = new Xlsx($spreadsheet);
            $writer->save(
                'archivos/' . $user->email . 'reporteComercioVales.xlsx'
            );
            $file =
                public_path() .
                '/archivos/' .
                $user->email .
                'reporteComercioVales.xlsx';

            return response()->download(
                $file,
                'SolicitudesValesGrandeza' . date('Y-m-d') . '.xlsx'
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
            public_path() . '/archivos/formatoReporteSolicitudValesV5.xlsx'
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
        $writer->save(
            'archivos/' . $user->email . 'SolicitudesValesGrandeza.xlsx'
        );
        $file =
            public_path() .
            '/archivos/' .
            $user->email .
            'SolicitudesValesGrandeza.xlsx';

        return response()->download(
            $file,
            $user->email .
                'SolicitudesValesGrandeza' .
                date('Y-m-d H:i:s') .
                '.xlsx'
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

    public function getCampoUsuario($cedula)
    {
        if ($cedula->idUsuarioCreo == 1312) {
            $getUserOffline = DB::table('users_aplicativo_web')
                ->select('Nombre')
                ->where('UserName', $cedula->UsuarioAplicativo)
                ->get()
                ->first();

            if ($getUserOffline != null && $getUserOffline != '') {
                return json_encode(
                    [
                        'nombre' => $getUserOffline->Nombre,
                        'observaciones' => '',
                    ],
                    JSON_UNESCAPED_UNICODE
                );
            }
        }

        $getUserApi = DB::table('users')
            ->select('Nombre', 'Paterno', 'Materno')
            ->where('id', $cedula->idUsuarioCreo)
            ->get()
            ->first();

        return json_encode(
            [
                'nombre' =>
                    $getUserApi->Nombre .
                    ' ' .
                    $getUserApi->Paterno .
                    ' ' .
                    $getUserApi->Materno,
                'observaciones' => '',
            ],
            JSON_UNESCAPED_UNICODE
        );
    }
}
