<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Contracts\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Arr;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PDF;
use JWTAuth;
use Imagick;
use Validator;
use App\Cedula;
use HTTP_Request2;
use GuzzleHttp\Client;
use App\VNegociosFiltros;
use Carbon\Carbon as time;
use Excel;
use App\Imports\ConciliacionImport;
class CedulasController extends Controller
{
    function getPermisos()
    {
        $user = auth()->user();

        $permisos = DB::table('users_menus')
            ->where(['idUser' => $user->id, 'idMenu' => '13'])
            ->get()
            ->first();
        if ($permisos !== null) {
            return $permisos;
        } else {
            return null;
        }
    }

    function getEstatusGlobal(Request $request)
    {
        $parameters = $request->all();
        $user = auth()->user();

        $permisos = $this->getPermisos();

        if ($permisos === null) {
            $response = [
                'success' => true,
                'results' => false,
                'total' => 0,
                'message' => 'No tiene permisos en este módulo',
            ];

            return response()->json($response, 200);
        }

        $seguimiento = $permisos->Seguimiento;
        $viewall = $permisos->ViewAll;
        $procedimiento = 'call getEstatusGlobalVentanillaValesGeneral';

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
        if ($permisos === null) {
            $response = [
                'success' => true,
                'results' => false,
                'total' => 0,
                'message' => 'No tiene permisos en este módulo',
            ];

            return response()->json($response, 200);
        }

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
                } else {
                    $response = [
                        'success' => true,
                        'results' => false,
                        'total' => 0,
                        'message' => 'No tiene región asignada',
                    ];

                    return response()->json($response, 200);
                }

                $res_Vales = DB::table('et_cat_municipio')
                    ->select('Nombre as municipio')
                    ->where('SubRegion', $region);
            } else {
                $res_Vales = DB::table('et_cat_municipio')->select(
                    'Nombre as municipio'
                );
            }

            $res_Vales = $res_Vales->groupBy('municipio')->OrderBy('municipio');
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
            $userId = JWTAuth::parseToken()->toUser()->id;

            $articuladores = DB::table('users_aplicativo_web')->select(
                'idUser AS value',
                'Nombre AS label'
            );

            $permisos = $this->getPermisos();
            if ($permisos === null) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'total' => 0,
                    'message' => 'No tiene permisos en este módulo',
                ];

                return response()->json($response, 200);
            }

            if ($permisos->ViewAll < 1) {
                $idUserOwner = DB::table('users_aplicativo_web')
                    ->selectRaw('idUserOwner')
                    ->where('idUser', $userId)
                    ->get()
                    ->first();
                if ($idUserOwner != null) {
                    $articuladores->where(
                        'idUserOwner',
                        $idUserOwner->idUserOwner
                    );
                } else {
                    $articuladores->where('idUser', $userId);
                }
            }

            $articuladores
                ->where('programa', '=', 'VALES / CALENTADORES')
                ->where('Activo', '1')
                ->orderBy('label')
                ->get();

            $estadoCivi = DB::table('cat_estado_civil')
                ->select('id AS value', 'EstadoCivil AS label')
                ->orderBy('label')
                ->get();

            $entidades = DB::table('cat_entidad')
                ->select('id AS value', 'Entidad AS label', 'Clave_CURP')
                ->where('id', '<>', 1)
                ->orderBy('label')
                ->get();

            $parentescosJefe = DB::table('cat_parentesco_jefe_hogar')
                ->select('id AS value', 'Parentesco AS label')
                ->orderBy('label')
                ->get();

            $parentescosTutor = DB::table('cat_parentesco_tutor')
                ->select('id AS value', 'Parentesco AS label')
                ->orderBy('label')
                ->get();

            $situaciones = DB::table('cat_situacion_actual')
                ->select('id AS value', 'Situacion AS label')
                ->orderBy('label')
                ->get();

            $municipios = DB::table('et_cat_municipio')
                ->select('id AS value', 'Nombre AS label')
                ->orderBy('label')
                ->get();

            $archivos_clasificacion = DB::table('cedula_archivos_clasificacion')
                ->select('id AS value', 'Clasificacion AS label')
                ->orderBy('label')
                ->get();

            $catalogs = [
                'entidades' => $entidades,
                'cat_parentesco_jefe_hogar' => $parentescosJefe,
                'cat_parentesco_tutor' => $parentescosTutor,
                'cat_situacion_actual' => $situaciones,
                'cat_estado_civil' => $estadoCivi,
                'archivos_clasificacion' => $archivos_clasificacion,
                'municipios' => $municipios,
                'articuladores' => $articuladores->get(),
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
            $localidades = DB::table('et_cat_localidad_2022')
                ->select('id AS value', 'Nombre AS label')
                ->where('IdMunicipio', $id)
                ->orderBy('label')
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

    function getTipoAsentamientoLocalidad(Request $request, $id)
    {
        try {
            $params = $request->all();
            $localidades = DB::table('et_cat_localidad_2022')
                ->select('Ambito')
                ->where('id', $id)
                ->get()
                ->first();

            $data = '';
            if ($localidades != null) {
                if ($localidades->Ambito == 'R') {
                    $data = 'RURAL';
                } else {
                    $data = 'URBANO';
                }
            }

            $response = [
                'success' => true,
                'results' => true,
                'data' => $data,
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
                ->orderBy('label')
                ->get();
            $manzanas = DB::table('cat_manzana_cedula')
                ->select('id AS value', 'CVE_MZA AS label')
                ->where('IdLocalidad', $id)
                ->orderBy('label')
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

            DB::beginTransaction();

            DB::table('users_filtros')
                ->where('UserCreated', $userId)
                ->where('api', 'getValesVentanilla')
                ->delete();

            DB::commit();
            $parameters_serializado = serialize($params);

            //Insertamos los filtros
            DB::table('users_filtros')->insert([
                'UserCreated' => $userId,
                'Api' => 'getValesVentanilla',
                'Consulta' => $parameters_serializado,
                'created_at' => date('Y-m-d h-m-s'),
            ]);

            $tableSol = 'cedulas_solicitudes';
            $tableCedulas =
                '(SELECT * FROM cedulas WHERE FechaElimino IS NULL) AS cedulas';
            $tableName = 'cedulas';

            $user = auth()->user();

            $permisos = $this->getPermisos();
            if ($permisos === null) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'total' => 0,
                    'message' => 'No tiene permisos en este módulo',
                ];

                return response()->json($response, 200);
            }

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

            // $queryTotal = DB::table($tableSol)
            //     ->SELECTRAW('COUNT(cedulas_solicitudes.idVale) AS Total')
            //     ->JOIN(
            //         'users AS creadores',
            //         'creadores.id',
            //         $tableSol . '.idUsuarioCreo'
            //     )
            //     ->JOIN(
            //         'et_cat_municipio as m',
            //         'm.Nombre',
            //         $tableSol . '.MunicipioVive'
            //     )
            //     ->JOIN('vales as v', 'v.id', $tableSol . '.idVale')
            //     ->WHERENULL('FechaElimino')
            //     ->WHERERAW('idVale IS NOT NULL');

            $solicitudes = DB::table($tableSol)

                ->selectRaw(
                    'cedulas_solicitudes.id, ' .
                        'cedulas_solicitudes.FechaSolicitud, ' .
                        'cedulas_solicitudes.FolioTarjetaImpulso, ' .
                        'cedulas_solicitudes.Nombre, ' .
                        'cedulas_solicitudes.Paterno, ' .
                        'cedulas_solicitudes.Materno, ' .
                        'cedulas_solicitudes.FechaNacimiento, ' .
                        'cedulas_solicitudes.Edad, ' .
                        'cedulas_solicitudes.Sexo, ' .
                        'cedulas_solicitudes.idEntidadNacimiento, ' .
                        'cedulas_solicitudes.CURP, ' .
                        'cedulas_solicitudes.RFC, ' .
                        'cedulas_solicitudes.idEstadoCivil, ' .
                        'cedulas_solicitudes.idParentescoJefeHogar, ' .
                        'cedulas_solicitudes.NumHijos, ' .
                        'cedulas_solicitudes.NumHijas, ' .
                        'cedulas_solicitudes.ComunidadIndigena, ' .
                        'cedulas_solicitudes.Dialecto, ' .
                        'cedulas_solicitudes.Afromexicano, ' .
                        'cedulas_solicitudes.idSituacionActual, ' .
                        'cedulas_solicitudes.TarjetaImpulso, ' .
                        'cedulas_solicitudes.ContactoTarjetaImpulso, ' .
                        'cedulas_solicitudes.Celular, ' .
                        'cedulas_solicitudes.Telefono, ' .
                        'cedulas_solicitudes.TelRecados, ' .
                        'cedulas_solicitudes.Correo, ' .
                        'cedulas_solicitudes.idParentescoTutor, ' .
                        'cedulas_solicitudes.NombreTutor, ' .
                        'cedulas_solicitudes.PaternoTutor, ' .
                        'cedulas_solicitudes.MaternoTutor, ' .
                        'cedulas_solicitudes.FechaNacimientoTutor, ' .
                        'cedulas_solicitudes.EdadTutor, ' .
                        'cedulas_solicitudes.CURPTutor, ' .
                        'cedulas_solicitudes.TelefonoTutor, ' .
                        'cedulas_solicitudes.CorreoTutor, ' .
                        'cedulas_solicitudes.NecesidadSolicitante, ' .
                        'cedulas_solicitudes.CostoNecesidad, ' .
                        'cedulas_solicitudes.idEntidadVive, ' .
                        'cedulas_solicitudes.MunicipioVive, ' .
                        'cedulas_solicitudes.LocalidadVive, ' .
                        'cedulas_solicitudes.TipoAsentamiento, ' .
                        'cedulas_solicitudes.CPVive, ' .
                        'cedulas_solicitudes.ColoniaVive, ' .
                        'cedulas_solicitudes.CalleVive, ' .
                        'cedulas_solicitudes.NoExtVive, ' .
                        'cedulas_solicitudes.NoIntVive, ' .
                        'cedulas_solicitudes.Referencias, ' .
                        'cedulas_solicitudes.idEstatus, ' .
                        'cedulas_solicitudes.idUsuarioCreo, ' .
                        'cedulas_solicitudes.FechaCreo, ' .
                        'cedulas_solicitudes.idUsuarioActualizo, ' .
                        'cedulas_solicitudes.FechaActualizo, ' .
                        'cedulas_solicitudes.SexoTutor, ' .
                        'cedulas_solicitudes.idEntidadNacimientoTutor, ' .
                        'cedulas_solicitudes.Folio, ' .
                        'cedulas_solicitudes.ListaParaEnviar, ' .
                        'cedulas_solicitudes.idUsuarioElimino, ' .
                        'cedulas_solicitudes.FechaElimino, ' .
                        'cedulas_solicitudes.UsuarioAplicativo, ' .
                        'cedulas_solicitudes.idSolicitudAplicativo, ' .
                        'cedulas_solicitudes.Region, ' .
                        'cedulas_solicitudes.idEnlace, ' .
                        'cedulas_solicitudes.Enlace, ' .
                        'cedulas_solicitudes.Latitud, ' .
                        'cedulas_solicitudes.Longitud, ' .
                        'cedulas_solicitudes.IngresoMensual, ' .
                        'cedulas_solicitudes.OtrosIngresos, ' .
                        'cedulas_solicitudes.TotalIngreso, ' .
                        'cedulas_solicitudes.PersonasDependientes, ' .
                        'cedulas_solicitudes.IngresoPercapita, ' .
                        'cedulas_solicitudes.OcupacionJefeHogar, ' .
                        'cedulas_solicitudes.FechaINE, ' .
                        'cedulas_solicitudes.ValidadoTarjetaContigoSi, ' .
                        'cedulas_solicitudes.idVale, ' .
                        'cedulas_solicitudes.ExpedienteCompleto, ' .
                        'cedulas_solicitudes.Formato, ' .
                        'entidadesNacimiento.Entidad AS EntidadNacimiento, ' .
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
                        '.ListaParaEnviar,' .
                        'lpad(hex(' .
                        $tableSol .
                        '.idVale),6,0) FolioSolicitud,' .
                        'v.Remesa'
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
                ->JOIN(
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
                ->JOIN(
                    'et_cat_municipio as m',
                    'm.Nombre',
                    $tableSol . '.MunicipioVive'
                )
                ->JOIN(
                    'vales_respaldo_2022 as v',
                    'v.id',
                    $tableSol . '.idVale'
                )
                ->whereNull($tableSol . '.FechaElimino');
            $filterQuery = '';
            $municipioRegion = [];
            $mun = [];
            $usersNames = [];
            $newFilter = [];
            $idsUsers = '';
            $usersApp = '';
            if (isset($params['filtered']) && count($params['filtered']) > 0) {
                foreach ($params['filtered'] as $filtro) {
                    if ($filtro['id'] == '.articulador') {
                        $idsUsers = implode(', ', $filtro['value']);
                        foreach ($filtro['value'] as $idUser) {
                            $userN = DB::table('users_aplicativo_web')
                                ->select('UserName')
                                ->where('idUser', $idUser)
                                ->get()
                                ->first();

                            if ($userN != null) {
                                $usersNames[] = "'" . $userN->UserName . "'";
                            }
                        }
                        if (count($usersNames) > 0) {
                            $usersApp = implode(', ', $usersNames);
                        }
                    } else {
                        $newFilter[] = [
                            'id' => $filtro['id'],
                            'value' => $filtro['value'],
                        ];
                    }
                }
                $filtersCedulas = ['.id'];
                foreach ($newFilter as $filtro) {
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

                        if ($id == '.id') {
                            $id = '.idVale';
                            $value = hexdec($value);
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
                //$queryTotal->whereRaw($filterQuery);
            }

            if ($filtroCapturo !== '') {
                $solicitudes->whereRaw($filtroCapturo);
                //$queryTotal->whereRaw($filtroCapturo);
            }

            if ($idsUsers !== '') {
                $filtroArticuladores =
                    '(' .
                    $tableSol .
                    '.idUsuarioCreo IN (' .
                    $idsUsers .
                    ') OR ' .
                    $tableSol .
                    '.UsuarioAplicativo IN (' .
                    $usersApp .
                    ')' .
                    ')';
                $solicitudes->whereRaw($filtroArticuladores);
                //$queryTotal->whereRaw($filtroArticuladores);
            }

            // $dd = str_replace_array(
            //     '?',
            //     $solicitudes->getBindings(),
            //     $solicitudes->toSql()
            // );

            // $response = [
            //     'success' => true,
            //     'results' => false,
            //     'data' => $dd,
            // ];
            // return response()->json($response, 200);
            //$total = $queryTotal->first();
            $page = $params['page'];
            $pageSize = $params['pageSize'];

            $startIndex = $page * $pageSize;
            $total = $solicitudes->count();
            $solicitudes = $solicitudes
                ->OrderByRaw($tableSol . '.id', 'DESC')
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
                    'idEnlace' => $data->idEnlace,
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
                    'FechaINE' => $data->FechaINE,
                    'TipoAsentamiento' => $data->TipoAsentamiento,
                    'FolioSolicitud' => $data->FolioSolicitud,
                    'ValidadoTarjetaContigoSi' =>
                        $data->ValidadoTarjetaContigoSi,
                    'Formato' => $data->Formato,
                    'Remesa' => $data->Remesa,
                ];

                array_push($array_res, $temp);
            }

            $filtros = '';
            if (isset($params['filtered'])) {
                $filtros = $params['filtered'];
            }

            //$total = count($array_res);

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
            $user = auth()->user();
            $idAplicativo = '';
            $necesidad = '';
            $costo = '';

            if (isset($params['programa'])) {
                switch (strtoupper($params['programa'])) {
                    case 'VALE GRANDEZA':
                        $program = 1;
                        break;
                    case 'CALENTADORES SOLARES':
                        $program = 2;
                        break;
                    case 'PROYECTOS PRODUCTIVOS':
                        $program = 3;
                        break;
                    case 'YO PUEDO':
                        $program = 4;
                        break;
                }
            } else {
                $program = 1;
            }

            switch ($program) {
                case 1:
                    $tableSol = 'cedulas_solicitudes';
                    $necesidad = 'VALES GRANDEZA';
                    $costo = '500';
                    break;
                case 2:
                    $tableCedulas = 'calentadores_cedulas';
                    $tableSol = 'calentadores_solicitudes';
                    $necesidad = 'CALENTADOR SOLAR';
                    $costo = '8145';
                    break;
                case 3:
                    $tableSol = 'proyectos_solicitudes';
                    $tableCedulas = 'proyectos_cedulas';
                    unset($params['Latitud']);
                    unset($params['Longitud']);
                    unset($params['idParentescoTutor']);
                    unset($params['NombreTutor']);
                    unset($params['PaternoTutor']);
                    unset($params['MaternoTutor']);
                    unset($params['FechaNacimientoTutor']);
                    unset($params['EdadTutor']);
                    unset($params['CURPTutor']);
                    unset($params['TelefonoTutor']);
                    unset($params['CorreoTutor']);
                    break;
                case 4:
                    $tableCedulas = 'yopuedo_cedulas';
                    $tableSol = 'yopuedo_solicitudes';
                    $necesidad =
                        'CAPACITACIÓN DEL PROGRAMA YO PUEDO, GTO PUEDE';
                    $costo = 'NO APLICA';
                    break;
            }

            if (isset($params['MunicipioVive'])) {
                $region = DB::table('et_cat_municipio')
                    ->where('Nombre', $params['MunicipioVive'])
                    ->get()
                    ->first();
                if ($region != null) {
                    $params['Region'] = $region->SubRegion;
                }
            }

            $newClasificacion = isset($params['NewClasificacion'])
                ? $params['NewClasificacion']
                : [];
            $files = isset($params['NewFiles']) ? $params['NewFiles'] : [];

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

            if ($program != 3) {
                $params['NecesidadSolicitante'] = $necesidad;
                $params['CostoNecesidad'] = $costo;
            }
            if (isset($params['idSolicitudAplicativo'])) {
                $idAplicativo = $params['idSolicitudAplicativo'];
            }

            unset($params['programa']);
            unset($params['NewClasificacion']);
            unset($params['NewFiles']);
            unset($params['idGrupo']);
            unset($params['idEstatusGrupo']);
            unset($params['idMunicipioGrupo']);
            unset($params['FolioSolicitud']);

            if (!isset($params['ListaParaEnviar'])) {
                $params['ListaParaEnviar'] = 0;
            }
            if (!isset($params['idEstatus'])) {
                $params['idEstatus'] = 1;
            }

            if ($program == 4) {
                if (isset($params['CURP'])) {
                    $curpRegistrado = DB::table($tableSol)
                        ->where(['CURP' => $params['CURP']])
                        ->whereRaw('FechaElimino IS NULL')
                        ->first();
                }

                if ($curpRegistrado != null) {
                    $curpRegistradoCedula = DB::table($tableSol)
                        ->where(['CURP' => $params['CURP']])
                        ->whereRaw('FechaElimino IS NULL')
                        ->whereRaw('ListaParaEnviar = "1"')
                        ->first();

                    if ($curpRegistradoCedula != null) {
                        $response = [
                            'success' => true,
                            'results' => false,
                            'errors' =>
                                'El CURP ' .
                                $params['CURP'] .
                                ' ya esta tiene una solicitud lista para enviar ',
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
                            ->where('id', $curpRegistrado->id)
                            ->update($params);
                        $response = [
                            'success' => true,
                            'results' => true,
                            'message' => [
                                'idSolicitud' => $curpRegistrado->id,
                                'Folio' => $params['Folio'],
                                'CURP' => $curpRegistrado->CURP,
                                'Nombre' => $curpRegistrado->Nombre,
                                'Paterno' => $curpRegistrado->Paterno,
                                'Materno' => $curpRegistrado->Materno,
                            ],
                            'data' => $curpRegistrado->id,
                        ];
                        return response()->json($response, 200);
                    }
                }
            }

            //$year_start = idate('Y', strtotime('first day of January', time()));
            $year_start = 2022;
            if (isset($params['FechaINE'])) {
                $fechaINE = intval($params['FechaINE']);
                if ($year_start > $fechaINE) {
                    $response = [
                        'success' => true,
                        'results' => false,
                        'errors' =>
                            'La vigencia de la Identificación Oficial no cumple con los requisitos',
                    ];
                    return response()->json($response, 200);
                }
            }

            if ($program == 1) {
                $curpRegistrado = DB::table('cedulas_solicitudes')
                    ->select('idVale', 'CURP')
                    ->where('CURP', $params['CURP'])
                    ->whereRaw('FechaElimino IS NULL')
                    ->whereRaw('YEAR(FechaCreo) = ' . $year_start)
                    ->get()
                    ->first();

                if ($curpRegistrado != null) {
                    if ($curpRegistrado->idVale != null) {
                        $curpSinRemesa = DB::table('vales')
                            ->select(
                                DB::RAW(
                                    'lpad( hex(id ), 6, 0 ) AS FolioSolicitud'
                                ),
                                'CURP'
                            )
                            ->where('CURP', $params['CURP'])
                            ->whereRaw('Remesa IS NULL')
                            ->get()
                            ->first();

                        if ($curpSinRemesa != null) {
                            $response = [
                                'success' => true,
                                'results' => false,
                                'errors' =>
                                    'El Beneficiario con CURP ' .
                                    $params['CURP'] .
                                    ' ya cuenta con una solicitud por aprobar con el Folio ' .
                                    $curpSinRemesa->FolioSolicitud,
                                'message' =>
                                    'El Beneficiario con CURP ' .
                                    $params['CURP'] .
                                    ' ya cuenta con una solicitud por aprobar con el Folio ' .
                                    $curpSinRemesa->FolioSolicitud,
                            ];

                            return response()->json($response, 200);
                        }
                    }
                }

                // $beneficiarioRegistrado = DB::table('cedulas_solicitudes')
                //     ->select(
                //         DB::RAW('lpad( hex(idVale ), 6, 0 ) AS FolioSolicitud'),
                //         'CURP',
                //         'Nombre',
                //         'Paterno',
                //         'Materno'
                //     )
                //     ->where([
                //         'CURP' => $params['CURP'],
                //         'Nombre' => $params['Nombre'],
                //         'Paterno' => $params['Paterno'],
                //         'Materno' => $params['Materno'],
                //     ])
                //     ->whereRaw('FechaElimino IS NULL')
                //     ->whereRaw('YEAR(FechaCreo) = ' . $year_start)
                //     ->get()
                //     ->first();

                // if ($beneficiarioRegistrado != null) {
                //     $response = [
                //         'success' => true,
                //         'results' => false,
                //         'errors' =>
                //             'El Beneficiario con CURP ' .
                //             $params['CURP'] .
                //             ' ya se encuentra registrado para el ejercicio ' .
                //             $year_start .
                //             ' con el Folio ' .
                //             $curpRegistrado->FolioSolicitud,
                //         'message' =>
                //             'El Beneficiario con CURP ' .
                //             $params['CURP'] .
                //             ' ya se encuentra registrado para el ejercicio ' .
                //             $year_start .
                //             ' con el Folio ' .
                //             $curpRegistrado->FolioSolicitud,
                //     ];

                //     return response()->json($response, 200);
                // }
            }

            if ($program != 1) {
                if (isset($params['Folio'])) {
                    $folioRegistrado = DB::table($tableSol)
                        ->where(['Folio' => $params['Folio']])
                        ->whereRaw('FechaElimino IS NULL')
                        ->first();
                    if ($folioRegistrado != null) {
                        if ($program > 1) {
                            $folioRegistradoCalentadores = DB::table($tableSol)
                                ->where([
                                    $tableSol . '.Folio' => $params['Folio'],
                                ])
                                ->whereRaw($tableSol . '.FechaElimino IS NULL')
                                ->leftjoin(
                                    $tableCedulas,
                                    $tableCedulas . '.idSolicitud',
                                    $tableSol . '.id'
                                )
                                ->first();
                            if (
                                //$folioRegistradoCalentadores->ListaParaEnviar == 1
                                $folioRegistradoCalentadores != null
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
                                //->whereRaw('ListaParaEnviar = "1"')
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
                $params['FechaCreo'] = date('Y-m-d H:i:s');
                DB::beginTransaction();
                $id = DB::table($tableSol)->insertGetId($params);
                DB::commit();

                //Se envía a Vales -Aqui
                $infoVale = $this->setVales($id);
                DB::beginTransaction();
                $idVale = DB::table('vales')->insertGetId($infoVale);
                DB::commit();

                DB::beginTransaction();
                DB::table('cedulas_solicitudes')
                    ->where('id', $id)
                    ->update([
                        'idVale' => $idVale,
                    ]);
                DB::commit();
            } else {
                $id = $idSol->id;
                DB::table($tableSol)
                    ->where('id', $id)
                    ->update($params);
            }
            if (isset($request->NewFiles) && $program === 1) {
                $this->createSolicitudFiles(
                    $id,
                    $request->NewFiles,
                    $newClasificacion,
                    $user->id
                );
            }

            $message = 'Solicitud creada con exito';
            if ($idVale != null) {
                $folioImpulso = str_pad(dechex($idVale), 6, '0', STR_PAD_LEFT);
                $message = $message . ' Folio PVG ' . strtoupper($folioImpulso);
            }

            $response = [
                'success' => true,
                'results' => true,
                'message' => $message,
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
                // 'FechaSolicitud' => 'required',
                'Nombre' => 'required',
                'Paterno' => 'required',
                //'Materno' => 'required',
                // 'FechaNacimiento' => 'required',
                // 'Edad' => 'required',
                // 'Sexo' => 'required',
                // 'idEntidadNacimiento' => 'required',
                'CURP' => 'required',
                // 'idEstadoCivil' => 'required',
                // 'idParentescoJefeHogar' => 'required',
                // 'NumHijos' => 'required',
                // 'NumHijas' => 'required',
                // 'Afromexicano' => 'required',
                // 'idSituacionActual' => 'required',
                // 'TarjetaImpulso' => 'required',
                // 'ContactoTarjetaImpulso' => 'required',
                // 'NecesidadSolicitante' => 'required',
                // 'CostoNecesidad' => 'required',
                // 'idEntidadVive' => 'required',
                'MunicipioVive' => 'required',
                'LocalidadVive' => 'required',
                'CPVive' => 'required',
                'ColoniaVive' => 'required',
                'CalleVive' => 'required',
                'NoExtVive' => 'required',
                // 'NoIntVive' => 'required',
                // 'Referencias' => 'required',
                // 'Folio' => 'required',
            ]);

            if ($v->fails()) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' =>
                        'Uno o más campos obligatorios están vaciós o no tiene el formato correcto',
                    'message' =>
                        'Uno o más campos obligatorios están vaciós o no tiene el formato correcto',
                ];
                return response()->json($response, 200);
            }
            $params = $request->all();
            $curp = $params['CURP'];

            if ($curp === '' || $curp === null) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' =>
                        'Ocurrio un error con el CURP al actualizar, recargue la página e intente nuevamente',
                    'message' =>
                        'Ocurrio un error con el CURP al actualizar, recargue la página e intente nuevamente',
                ];
                return response()->json($response, 200);
            }

            $program = 1;
            $tableSol = 'cedulas_solicitudes';
            $tableCedulas = 'cedulas';

            $solicitud = DB::table($tableSol)
                ->select('idEstatus')
                ->where('id', $params['id'])
                ->whereNull('FechaElimino')
                ->first();

            if ($solicitud !== null && $solicitud->idEstatus > 1) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' =>
                        'La solicitud se encuentra validada, no se puede editar',
                ];
                return response()->json($response, 200);
            }

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
            unset($params['programa']);
            unset($params['idGrupo']);
            unset($params['idEstatusGrupo']);
            unset($params['idMunicipioGrupo']);
            unset($params['FolioSolicitud']);

            $params['idUsuarioActualizo'] = $user->id;
            $params['FechaActualizo'] = date('Y-m-d H:i:s');
            if (!isset($params['idEstatus'])) {
                $params['idEstatus'] = 1;
            }
            if (isset($params['ListaParaEnviar'])) {
                if ($params['ListaParaEnviar'] == 1) {
                    $params['idEstatus'] = 9;
                }
            }

            if (isset($params['FechaINE'])) {
                $fechaINE = intval($params['FechaINE']);
                // $year_start = idate(
                //     'Y',
                //     strtotime('first day of January', time())
                // );
                $year_start = 2022;

                if ($year_start > $fechaINE) {
                    $response = [
                        'success' => true,
                        'results' => false,
                        'errors' =>
                            'La vigencia de la Identificación Oficial no cumple con los requisitos',
                    ];
                    return response()->json($response, 200);
                }
            }

            if (isset($params['Actualizada'])) {
                $solicitudAnterior = DB::table('cedulas_solicitudes')
                    ->where('id', $id)
                    ->get()
                    ->first();

                if ($solicitudAnterior != null) {
                    $temp = [
                        'idSolicitud' => $solicitudAnterior->id,
                        'FechaSolicitud' => $solicitudAnterior->FechaSolicitud,
                        'FolioTarjetaImpulso' =>
                            $solicitudAnterior->FolioTarjetaImpulso,
                        'Nombre' => $solicitudAnterior->Nombre,
                        'Paterno' => $solicitudAnterior->Paterno,
                        'Materno' => $solicitudAnterior->Materno,
                        'FechaNacimiento' =>
                            $solicitudAnterior->FechaNacimiento,
                        'Edad' => $solicitudAnterior->Edad,
                        'Sexo' => $solicitudAnterior->Sexo,
                        'idEntidadNacimiento' =>
                            $solicitudAnterior->idEntidadNacimiento,
                        'CURP' => $solicitudAnterior->CURP,
                        'RFC' => $solicitudAnterior->RFC,
                        'idEstadoCivil' => $solicitudAnterior->idEstadoCivil,
                        'idParentescoJefeHogar' =>
                            $solicitudAnterior->idParentescoJefeHogar,
                        'NumHijos' => $solicitudAnterior->NumHijos,
                        'NumHijas' => $solicitudAnterior->NumHijas,
                        'ComunidadIndigena' =>
                            $solicitudAnterior->ComunidadIndigena,
                        'Dialecto' => $solicitudAnterior->Dialecto,
                        'Afromexicano' => $solicitudAnterior->Afromexicano,
                        'idSituacionActual' =>
                            $solicitudAnterior->idSituacionActual,
                        'TarjetaImpulso' => $solicitudAnterior->TarjetaImpulso,
                        'ContactoTarjetaImpulso' =>
                            $solicitudAnterior->ContactoTarjetaImpulso,
                        'Celular' => $solicitudAnterior->Celular,
                        'Telefono' => $solicitudAnterior->Telefono,
                        'TelRecados' => $solicitudAnterior->TelRecados,
                        'Correo' => $solicitudAnterior->Correo,
                        'idParentescoTutor' =>
                            $solicitudAnterior->idParentescoTutor,
                        'NombreTutor' => $solicitudAnterior->NombreTutor,
                        'PaternoTutor' => $solicitudAnterior->PaternoTutor,
                        'MaternoTutor' => $solicitudAnterior->MaternoTutor,
                        'FechaNacimientoTutor' =>
                            $solicitudAnterior->FechaNacimientoTutor,
                        'EdadTutor' => $solicitudAnterior->EdadTutor,
                        'CURPTutor' => $solicitudAnterior->CURPTutor,
                        'TelefonoTutor' => $solicitudAnterior->TelefonoTutor,
                        'CorreoTutor' => $solicitudAnterior->CorreoTutor,
                        'NecesidadSolicitante' =>
                            $solicitudAnterior->NecesidadSolicitante,
                        'CostoNecesidad' => $solicitudAnterior->CostoNecesidad,
                        'idEntidadVive' => $solicitudAnterior->idEntidadVive,
                        'MunicipioVive' => $solicitudAnterior->MunicipioVive,
                        'LocalidadVive' => $solicitudAnterior->LocalidadVive,
                        'CPVive' => $solicitudAnterior->CPVive,
                        'ColoniaVive' => $solicitudAnterior->ColoniaVive,
                        'CalleVive' => $solicitudAnterior->CalleVive,
                        'NoExtVive' => $solicitudAnterior->NoExtVive,
                        'NoIntVive' => $solicitudAnterior->NoIntVive,
                        'Referencias' => $solicitudAnterior->Referencias,
                        'idEstatus' => $solicitudAnterior->idEstatus,
                        'idUsuarioCreo' => $solicitudAnterior->idUsuarioCreo,
                        'FechaCreo' => $solicitudAnterior->FechaCreo,
                        'idUsuarioActualizo' =>
                            $solicitudAnterior->idUsuarioActualizo,
                        'FechaActualizo' => $solicitudAnterior->FechaActualizo,
                        'SexoTutor' => $solicitudAnterior->SexoTutor,
                        'idEntidadNacimientoTutor' =>
                            $solicitudAnterior->idEntidadNacimientoTutor,
                        'Folio' => $solicitudAnterior->Folio,
                        'ListaParaEnviar' =>
                            $solicitudAnterior->ListaParaEnviar,
                        'idUsuarioElimino' =>
                            $solicitudAnterior->idUsuarioElimino,
                        'FechaElimino' => $solicitudAnterior->FechaElimino,
                        'UsuarioAplicativo' =>
                            $solicitudAnterior->UsuarioAplicativo,
                        'Region' => $solicitudAnterior->Region,
                        'Enlace' => $solicitudAnterior->Enlace,
                        'idSolicitudAplicativo' =>
                            $solicitudAnterior->idSolicitudAplicativo,
                        'Latitud' => $solicitudAnterior->Latitud,
                        'Longitud' => $solicitudAnterior->Longitud,
                        'IngresoMensual' => $solicitudAnterior->IngresoMensual,
                        'OtrosIngresos' => $solicitudAnterior->OtrosIngresos,
                        'TotalIngreso' => $solicitudAnterior->TotalIngreso,
                        'PersonasDependientes' =>
                            $solicitudAnterior->PersonasDependientes,
                        'IngresoPercapita' =>
                            $solicitudAnterior->IngresoPercapita,
                        'OcupacionJefeHogar' =>
                            $solicitudAnterior->OcupacionJefeHogar,
                        'idVale' => $solicitudAnterior->idVale,
                        'UsuarioEnvio' => $solicitudAnterior->UsuarioEnvio,
                        'FechaEnvio' => $solicitudAnterior->FechaEnvio,
                    ];
                    DB::table('cedulas_solicitudes_history')->insert($temp);
                }
            }

            try {
                $idVale = DB::table($tableSol)
                    ->select('idVale', 'CURP')
                    ->where('id', $id)
                    ->whereNull('FechaElimino')
                    ->get()
                    ->first();

                if ($idVale != null) {
                    if ($idVale->idVale != null) {
                        $remesa = DB::table('vales_respaldo_2022')
                            ->select('Remesa')
                            ->where('id', $idVale->idVale)
                            ->get()
                            ->first();

                        if ($remesa != null && $user->id != 1) {
                            if ($remesa->Remesa != null) {
                                $validarCurp = DB::table('vales_respaldo_2022')
                                    ->where('id', $idVale->idVale)
                                    ->where([
                                        'CURP' => $curp,
                                        'Nombre' => $params['Nombre'],
                                    ])
                                    ->get()
                                    ->first();
                                //dd($validarCurp);
                                if ($validarCurp == null) {
                                    $response = [
                                        'success' => true,
                                        'results' => false,
                                        'errors' =>
                                            'El beneficiario ya fue aprobado, ¡No se puede modificar la información personal del solicitante !',
                                    ];
                                    return response()->json($response, 200);
                                }
                            }
                        }
                        DB::table($tableSol)
                            ->where('id', $id)
                            ->update($params);

                        $infoVale = $this->setValesUpdate($id);
                        DB::beginTransaction();
                        DB::table('vales_respaldo_2022')
                            ->where('id', $idVale->idVale)
                            ->update($infoVale);
                        DB::commit();
                    } else {
                        if ($user->id == 1) {
                            DB::beginTransaction();
                            DB::table($tableSol)
                                ->where('id', $id)
                                ->update($params);
                            DB::commit();

                            $infoVale = $this->setVales($id);
                            DB::beginTransaction();
                            $idVale = DB::table('vales')->insertGetId(
                                $infoVale
                            );
                            DB::commit();

                            DB::beginTransaction();
                            DB::table('cedulas_solicitudes')
                                ->where('id', $id)
                                ->update([
                                    'idVale' => $idVale,
                                ]);
                            DB::commit();
                        } else {
                            DB::table($tableSol)
                                ->where('id', $id)
                                ->update($params);
                        }
                    }
                } else {
                    if ($user->id == 1) {
                        $infoVale = $this->setVales($id);
                        DB::beginTransaction();
                        $idVale = DB::table('vales')->insertGetId($infoVale);
                        DB::commit();

                        DB::beginTransaction();
                        DB::table('cedulas_solicitudes')
                            ->where('id', $id)
                            ->update([
                                'idVale' => $idVale,
                            ]);
                        DB::commit();
                        DB::table($tableSol)
                            ->where('id', $id)
                            ->update($params);
                    } else {
                        DB::table($tableSol)
                            ->where('id', $id)
                            ->update($params);
                    }
                }
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

                    if (count($newIds) > 0) {
                        DB::table('solicitud_archivos')
                            ->whereIn('id', $newIds)
                            ->update([
                                'idUsuarioElimino' => $user->id,
                                'FechaElimino' => date('Y-m-d H:i:s'),
                            ]);
                    }
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

            $tableSol = 'cedulas_solicitudes';
            $tableCedulas = 'cedulas';

            $solicitud = DB::table($tableSol)
                ->select('idEstatus', 'ListaParaEnviar', 'idVale')
                ->where('id', $params['id'])
                ->first();

            if ($solicitud->idEstatus > 1) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' =>
                        'La solicitud no se puede eliminar, tiene una cédula activa o ya fue aceptada',
                ];
                return response()->json($response, 200);
            }

            if ($solicitud != null) {
                $remesa = DB::table('vales')
                    ->select('Remesa')
                    ->where('id', $solicitud->idVale)
                    ->get()
                    ->first();

                if ($remesa->Remesa != null) {
                    $response = [
                        'success' => true,
                        'results' => false,
                        'errors' =>
                            'El beneficiario ya fue aprobado, ¡No se puede eliminar la solicitud!',
                    ];
                    return response()->json($response, 200);
                }
            }

            DB::table('vales')
                ->where('id', $solicitud->idVale)
                ->delete();

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
                ->orderBy('label')
                ->get();

            $entidades = DB::table('cat_entidad')
                ->select('id AS value', 'Entidad AS label', 'Clave_CURP')
                ->where('id', '<>', 1)
                ->orderBy('label')
                ->get();

            $cat_parentesco_jefe_hogar = DB::table('cat_parentesco_jefe_hogar')
                ->select('id AS value', 'Parentesco AS label')
                ->orderBy('label')
                ->get();

            $cat_parentesco_tutor = DB::table('cat_parentesco_tutor')
                ->select('id AS value', 'Parentesco AS label')
                ->orderBy('label')
                ->get();

            $cat_situacion_actual = DB::table('cat_situacion_actual')
                ->select('id AS value', 'Situacion AS label')
                ->orderBy('label')
                ->get();

            $cat_actividades = DB::table('cat_actividades')
                ->select('id AS value', 'Actividad AS label')
                ->orderBy('label')
                ->get();

            $cat_codigos_dificultad = DB::table('cat_codigos_dificultad')
                ->select('id AS value', 'Grado AS label')
                ->orderBy('label')
                ->get();

            $cat_enfermedades = DB::table('cat_enfermedades')
                ->select('id AS value', 'Enfermedad AS label')
                ->orderBy('label')
                ->get();

            $cat_grados_educacion = DB::table('cat_grados_educacion')
                ->select('id AS value', 'Grado AS label')
                ->orderBy('label')
                ->get();

            $cat_niveles_educacion = DB::table('cat_niveles_educacion')
                ->select('id AS value', 'Nivel AS label')
                ->orderBy('label')
                ->get();

            $cat_prestaciones = DB::table('cat_prestaciones')
                ->select('id AS value', 'Prestacion AS label')
                ->orderBy('label')
                ->get();

            $cat_situacion_actual = DB::table('cat_situacion_actual')
                ->select('id AS value', 'Situacion AS label')
                ->orderBy('label')
                ->get();

            $cat_tipo_seguro = DB::table('cat_tipo_seguro')
                ->select('id AS value', 'Tipo AS label')
                ->orderBy('label')
                ->get();

            $cat_tipos_agua = DB::table('cat_tipos_agua')
                ->select('id AS value', 'Agua AS label')
                ->orderBy('label')
                ->get();

            $cat_tipos_combustibles = DB::table('cat_tipos_combustibles')
                ->select('id AS value', 'Combustible AS label')
                ->orderBy('label')
                ->get();

            $cat_tipos_drenajes = DB::table('cat_tipos_drenajes')
                ->select('id AS value', 'Drenaje AS label')
                ->orderBy('label')
                ->get();

            $cat_tipos_luz = DB::table('cat_tipos_luz')
                ->select('id AS value', 'Luz AS label')
                ->orderBy('label')
                ->get();

            $cat_tipos_muros = DB::table('cat_tipos_muros')
                ->select('id AS value', 'Muro AS label')
                ->orderBy('label')
                ->get();

            $cat_tipos_pisos = DB::table('cat_tipos_pisos')
                ->select('id AS value', 'Piso AS label')
                ->orderBy('label')
                ->get();

            $cat_tipos_techos = DB::table('cat_tipos_techos')
                ->select('id AS value', 'Techo AS label')
                ->orderBy('label')
                ->get();

            $cat_tipos_viviendas = DB::table('cat_tipos_viviendas')
                ->select('id AS value', 'Tipo AS label')
                ->orderBy('label')
                ->get();

            $cat_periodicidad = DB::table('cat_periodicidad')
                ->select('id AS value', 'Periodicidad AS label')
                ->orderBy('label')
                ->get();

            $archivos_clasificacion = DB::table('cedula_archivos_clasificacion')
                ->select('id AS value', 'Clasificacion AS label')
                ->orderBy('label')
                ->get();

            $municipios = DB::table('et_cat_municipio')
                ->select('id AS value', 'Nombre AS label')
                ->orderBy('label')
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

            if (isset($params['programa'])) {
                switch (strtoupper($params['programa'])) {
                    case 'VALE GRANDEZA':
                        $program = 1;
                        break;
                    case 'CALENTADORES SOLARES':
                        $program = 2;
                        break;
                    case 'PROYECTOS PRODUCTIVOS':
                        $program = 3;
                        break;
                    case 'YO PUEDO':
                        $program = 4;
                        break;
                }
            } else {
                if (!isset($params['Folio'])) {
                    $program = 1;
                } else {
                    $response = [
                        'success' => false,
                        'results' => false,
                        'errors' =>
                            'El registro no tiene un programa seleccionado',
                        'message' =>
                            'El registro no tiene un programa seleccionado',
                    ];
                    return response()->json($response, 200);
                }
            }

            switch ($program) {
                case 1:
                    $tableSol = 'cedulas_solicitudes';
                    $tableCedulas = 'cedulas';
                    $tablePrestaciones = 'cedulas_prestaciones';
                    $tableEnfermedades = 'cedulas_enfermedades';
                    $tableAtnMedica = 'cedulas_atenciones_medicas';
                    break;
                case 2:
                    $tableSol = 'calentadores_solicitudes';
                    $tableCedulas = 'calentadores_cedulas';
                    $tablePrestaciones = 'calentadores_prestaciones';
                    $tableEnfermedades = 'calentadores_enfermedades';
                    $tableAtnMedica = 'calentadores_atenciones_medicas';
                    break;
                case 3:
                    $tableSol = 'proyectos_solicitudes';
                    $tableCedulas = 'proyectos_cedulas';
                    $tablePrestaciones = 'proyectos_prestaciones';
                    $tableEnfermedades = 'proyectos_enfermedades';
                    $tableAtnMedica = 'proyectos_atenciones_medicas';
                    unset($params['Latitud']);
                    unset($params['Longitud']);
                    unset($params['idParentescoTutor']);
                    unset($params['NombreTutor']);
                    unset($params['PaternoTutor']);
                    unset($params['MaternoTutor']);
                    unset($params['FechaNacimientoTutor']);
                    unset($params['EdadTutor']);
                    unset($params['CURPTutor']);
                    unset($params['TelefonoTutor']);
                    unset($params['CorreoTutor']);
                    break;
                case 4:
                    $tableSol = 'yopuedo_solicitudes';
                    $tableCedulas = 'yopuedo_cedulas';
                    $tablePrestaciones = 'yopuedo_prestaciones';
                    $tableEnfermedades = 'yopuedo_enfermedades';
                    $tableAtnMedica = 'yopuedo_atenciones_medicas';
                    break;
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
            $newClasificacion = isset($params['NewClasificacion'])
                ? $params['NewClasificacion']
                : [];

            $user = auth()->user();
            $params['idUsuarioCreo'] = $user->id;
            $params['FechaCreo'] = date('Y-m-d H:i:s');
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
            if (isset($params['MunicipioVive'])) {
                $region = DB::table('et_cat_municipio')
                    ->where('Nombre', $params['MunicipioVive'])
                    ->get()
                    ->first();
                if ($region != null) {
                    $params['Region'] = $region->SubRegion;
                }
            }
            unset($params['Prestaciones']);
            unset($params['Enfermedades']);
            unset($params['AtencionesMedicas']);
            unset($params['NewClasificacion']);
            unset($params['NewFiles']);
            unset($params['idCedula']);
            unset($params['programa']);
            unset($params['idGrupo']);
            unset($params['idEstatusGrupo']);
            unset($params['idMunicipioGrupo']);

            if ($user->id == 1312) {
                unset($params['ListaParaEnviar']);
            }
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

            DB::beginTransaction();
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

                        $this->updateSolicitudFromCedula(
                            $params,
                            $user,
                            $program
                        );

                        DB::table($tablePrestaciones)
                            ->where('idCedula', $id)
                            ->delete();
                        $formatedPrestaciones = [];
                        foreach ($prestaciones as $prestacion) {
                            if ($prestacion != null) {
                                array_push($formatedPrestaciones, [
                                    'idCedula' => $id,
                                    'idPrestacion' => $prestacion,
                                ]);
                            }
                        }

                        if (count($formatedPrestaciones) > 0) {
                            DB::table($tablePrestaciones)->insert(
                                $formatedPrestaciones
                            );
                        }

                        DB::table($tableEnfermedades)
                            ->where('idCedula', $id)
                            ->delete();
                        $formatedEnfermedades = [];
                        foreach ($enfermedades as $enfermedad) {
                            if ($enfermedad != null) {
                                array_push($formatedEnfermedades, [
                                    'idCedula' => $id,
                                    'idEnfermedad' => $enfermedad,
                                ]);
                            }
                        }

                        if (count($formatedEnfermedades) > 0) {
                            DB::table($tableEnfermedades)->insert(
                                $formatedEnfermedades
                            );
                        }

                        DB::table($tableAtnMedica)
                            ->where('idCedula', $id)
                            ->delete();
                        $formatedAtencionesMedicas = [];
                        foreach ($atencionesMedicas as $atencion) {
                            if ($atencion != null) {
                                array_push($formatedAtencionesMedicas, [
                                    'idCedula' => $id,
                                    'idAtencionMedica' => $atencion,
                                ]);
                            }
                        }

                        if (count($formatedAtencionesMedicas) > 0) {
                            DB::table($tableAtnMedica)->insert(
                                $formatedAtencionesMedicas
                            );
                        }

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
            $this->updateSolicitudFromCedula($params, $user, $program);

            if ($program == 4) {
                $curpRegistrada = DB::table('curps_yopuedo_archivos')
                    ->where('CURP', $params['CURP'])
                    ->get();

                if ($curpRegistrada != null) {
                    $archivos = DB::table('curps_yopuedo_archivos AS c')
                        ->select(
                            'c.curp AS CURP',
                            'a.idClasificacion AS idClasificacion',
                            'a.NombreOriginal AS NombreOriginal',
                            'a.NombreSistema AS NombreSistema',
                            'a.Extension AS Extension',
                            'a.Tipo AS Tipo',
                            'a.Tamanio AS Tamanio',
                            'a.idUsuarioCreo AS idUsuarioCreo',
                            'a.FechaCreo AS FechaCreo'
                        )
                        ->join('archivos_curp_yopuedo AS a', 'c.id', 'a.idCurp')
                        ->where('c.curp', $params['CURP'])
                        ->get();

                    if ($archivos != null) {
                        foreach ($archivos as $a) {
                            $fileObject = [
                                'idCedula' => intval($id),
                                'idClasificacion' => intval(
                                    $a->idClasificacion
                                ),
                                'NombreOriginal' => $a->NombreOriginal,
                                'NombreSistema' => $a->NombreSistema,
                                'Extension' => $a->Extension,
                                'Tipo' => $a->Tipo,
                                'Tamanio' => '',
                                'idUsuarioCreo' => $user->id,
                                'FechaCreo' => date('Y-m-d H:i:s'),
                            ];

                            DB::table('yopuedo_cedula_archivos')->insert(
                                $fileObject
                            );
                        }
                    }
                }
            }

            if (count($prestaciones) > 0) {
                $formatedPrestaciones = [];
                foreach ($prestaciones as $prestacion) {
                    if ($prestacion != null) {
                        $formatedPrestaciones[] = [
                            'idCedula' => $id,
                            'idPrestacion' => $prestacion,
                        ];
                    }
                }
                if (count($formatedPrestaciones) > 0) {
                    DB::table($tablePrestaciones)->insert(
                        $formatedPrestaciones
                    );
                }
            }

            if (count($enfermedades) > 0) {
                $formatedEnfermedades = [];
                foreach ($enfermedades as $enfermedad) {
                    if ($enfermedad != null) {
                        $formatedEnfermedades[] = [
                            'idCedula' => $id,
                            'idEnfermedad' => $enfermedad,
                        ];
                    }
                }

                if (count($formatedEnfermedades) > 0) {
                    DB::table($tableEnfermedades)->insert(
                        $formatedEnfermedades
                    );
                }
            }

            if (count($atencionesMedicas) > 0) {
                $formatedAtencionesMedicas = [];
                foreach ($atencionesMedicas as $atencion) {
                    if ($atencion != null) {
                        $formatedAtencionesMedicas[] = [
                            'idCedula' => $id,
                            'idAtencionMedica' => $atencion,
                        ];
                    }
                }

                if (count($formatedAtencionesMedicas) > 0) {
                    DB::table($tableAtnMedica)->insert(
                        $formatedAtencionesMedicas
                    );
                }
            }

            if (isset($request->NewFiles)) {
                $this->createCedulaFiles(
                    $id,
                    $request->NewFiles,
                    $newClasificacion,
                    $user->id,
                    $program
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
                // $o->ruta =
                //     'https://apivales.apisedeshu.com/subidos/' .
                //     $o->NombreSistema;
                $o->ruta = Storage::disk('subidos')->url($o->NombreSistema);
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
            $archivos2 = DB::table('calentadores_cedula_archivos as a')
                ->select(
                    'a.id',
                    'a.idClasificacion',
                    'a.NombreOriginal AS name',
                    'a.NombreSistema',
                    'a.Tipo AS type',
                    'a.idEstatus'
                )
                ->where('a.idCedula', $id)
                ->whereRaw('a.FechaElimino IS NULL')
                ->get();
            $archivosClasificacion = array_map(function ($o) {
                return $o->idClasificacion;
            }, $archivos2->toArray());

            $archivos = array_map(function ($o) {
                $o->ruta = Storage::disk('subidos')->url($o->NombreSistema); // 'https://apivales.apisedeshu.com/subidos/' . $o->NombreSistema;
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
                $response = [
                    'success' => false,
                    'results' => false,
                    'errors' => 'El registro no tiene un programa seleccionado',
                    'message' =>
                        'El registro no tiene un programa seleccionado',
                ];
                return response()->json($response, 200);
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
            unset($params['idGrupo']);
            unset($params['idEstatusGrupo']);
            unset($params['idMunicipioGrupo']);

            DB::table($tableCedulas)
                ->where('id', $id)
                ->update($params);

            $this->updateSolicitudFromCedula($params, $user, $program);

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

            $oldFiles = DB::table('cedula_archivos')
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
                DB::table('cedula_archivos')
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

            $flag = $this->validarExpediente($id);

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

        if ($folio->Folio == null) {
            $response = [
                'success' => true,
                'results' => false,
                'message' =>
                    'La solicitud no cuenta con Folio, revise su iformación',
            ];
            return response()->json($response, 200);
        }

        try {
            if ($folio != null) {
                $urlValidacionFolio =
                    'https://api-integracion-ventanilla-impulso.guanajuato.gob.mx/v1/application/external/validate/' .
                    //'https://qa-api-utils-ventanilla-impulso.guanajuato.gob.mx/v1/application/external/validate/' .
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

        if ($files->count() != 2) {
            $response = [
                'success' => true,
                'results' => false,
                'errors' =>
                    'Revise los documentos de Identificación Oficial Vigente,' .
                    ' y el Formato de Firma y Acuse, Solo debe agregar un archivo por clasificación.',
                'message' =>
                    'Revise los documentos de Identificación Oficial Vigente,' .
                    ' y el Formato de Firma y Acuse, Solo debe agregar un archivo por clasificación.',
            ];
            return response()->json($response, 200);
        } else {
            $clasificaciones = [];
            foreach ($files as $file) {
                $clasificaciones[] = $file->idClasificacion;
            }
            if (!in_array(3, $clasificaciones)) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' => 'Falta la Identificación Oficial Vigente',
                    'message' => 'Falta la Identificación Oficial Vigente',
                ];
                return response()->json($response, 200);
            }
            if (!in_array(5, $clasificaciones)) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' => 'Falta el Formato de Firma y Acuse',
                    'message' => 'Falta el Formato de Firma y Acuse',
                ];
                return response()->json($response, 200);
            }
        }

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

        if ($filesEspecifico->count() != 1) {
            $response = [
                'success' => true,
                'results' => false,
                'errors' =>
                    'Revise el documento de Formato SEDESHU-PVG-01, Debe agregar un archivo por clasificación.',
                'message' =>
                    'Revise el documento de Formato SEDESHU-PVG-01, Debe agregar un archivo por clasificación.',
            ];
            return response()->json($response, 200);
        }

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

        if ($solicitud->idUsuarioCreo == 1312) {
            $authUsuario = $this->getAuthUsuario(
                $solicitud->UsuarioAplicativo,
                1
            );
        } else {
            $authUsuario = $this->getAuthUsuario($solicitud->idUsuarioCreo, 2);
        }

        $dataCompleted = [
            'solicitud' => $solicitudJson['solicitud'],
            'programa' => $programa,
            'documentos' => $docs,
            'authUsuario' => $authUsuario,
            'campoUsuario' => $cUsuario,
        ];

        $url = '';

        if ($solicitud->idVersion == 2) {
            $url =
                'https://api-integracion-ventanilla-impulso.guanajuato.gob.mx/v1/application/external/solicitud/register/v2';
        } else {
            $url =
                'https://api-integracion-ventanilla-impulso.guanajuato.gob.mx/v1/application/external/solicitud/register';
        }

        $request2 = new HTTP_Request2();
        $request2->setUrl(
            // //QA
            // //'https://qa-api-utils-ventanilla-impulso.guanajuato.gob.mx/v1/application/external/solicitud/register'
            // //Productivo
            // 'https://api-integracion-ventanilla-impulso.guanajuato.gob.mx/v1/application/external/solicitud/register'
            $url
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
        //dd($request2);
        try {
            $response = $request2->send();
            $resp = json_decode($response->getBody());
            $message = $resp;
            if ($response->getStatus() == 200) {
                if ($resp->success) {
                    try {
                        // $infoVale = $this->setVales($id);
                        // $idVale = DB::table('vales')->insertGetId($infoVale);

                        // $vale = DB::table('vales')
                        //     ->select(
                        //         'vales.*',
                        //         DB::raw('LPAD(HEX(vales.id),6,0) as ClaveUnica')
                        //     )
                        //     ->where('vales.id', '=', $idVale)
                        //     ->first();

                        DB::table('cedulas_solicitudes')
                            ->where('id', $id)
                            ->update([
                                'idEstatus' => '8',
                                'ListaParaEnviar' => '2',
                                //'idVale' => $idVale,
                                'UsuarioEnvio' => $user->id,
                                'FechaEnvio' => date('Y-m-d H:i:s'),
                            ]);

                        return [
                            'success' => true,
                            'results' => true,
                            //'message' => $vale->ClaveUnica,
                            'message' => 'Enviada',
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
                } else {
                    $response2 = [
                        'success' => true,
                        'results' => false,
                        'errors' => $resp,
                        'message' => 'Ocurrio un error al enviar la solicitud',
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

        $idMunicipio = DB::table('et_cat_municipio')
            ->select('id')
            ->where('Nombre', $solicitud->MunicipioVive)
            ->get()
            ->first();

        $cveLocalidad = DB::table('et_cat_localidad_2022')
            ->select('CveInegi', 'Nombre')
            ->where('idMunicipio', $idMunicipio->id)
            ->where('Nombre', $solicitud->LocalidadVive)
            ->get()
            ->first();

        if ($solicitud->idVersion == 1) {
            $json = [
                'solicitud' => json_encode(
                    [
                        'tipoSolicitud' => 'Ciudadana',
                        'origen' => 'F',
                        'tutor' => [
                            'respuesta' => false,
                        ],

                        'datosCurp' => [
                            'folio' => $solicitud->Folio,
                            'curp' => $solicitud->CURP,
                            'entidadNacimiento' =>
                                $solicitud->EntidadNacimiento,
                            'fechaNacimientoDate' => date(
                                $solicitud->FechaNacimiento
                            ),
                            'fechaNacimientoTexto' =>
                                $solicitud->FechaNacimiento,
                            'genero' =>
                                strtoupper($solicitud->Sexo) == 'H'
                                    ? 'MASCULINO'
                                    : 'FEMENINO',
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
                                'descripcion' =>
                                    $solicitud->ParentescoJefeHogar,
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
                                'tipo' => 'Colonia',
                                'nombre' => $solicitud->ColoniaVive,
                            ],
                            'numeroExt' =>
                                $solicitud->NoExtVive != null
                                    ? $solicitud->NoExtVive
                                    : 'S/N',
                            'numeroInt' => $solicitud->NoIntVive,
                            'entidadFederativa' => $solicitud->EntidadVive,
                            //'localidad' => $solicitud->LocalidadVive,
                            'localidad' => [
                                'nombre' => $solicitud->LocalidadVive
                                    ? $solicitud->LocalidadVive
                                    : '',
                                'codigo' => $cveLocalidad->CveInegi,
                            ],
                            'municipio' => $solicitud->MunicipioVive,
                            'calle' => $solicitud->CalleVive,
                            'referencias' => is_null($solicitud->Referencias)
                                ? ''
                                : $solicitud->Referencias,
                            'solicitudImpulso' =>
                                $solicitud->TarjetaImpulso == 1,
                            'autorizaContacto' =>
                                $solicitud->ContactoTarjetaImpulso == 1,
                        ],
                    ],
                    JSON_UNESCAPED_UNICODE
                ),
            ];
        } else {
            $json = [
                'solicitud' => json_encode(
                    [
                        'tipoSolicitud' => 'Ciudadana',
                        'origen' => 'F',
                        'tutor' => [
                            'respuesta' => false,
                        ],

                        'datosCurp' => [
                            'folio' => $solicitud->Folio,
                            'curp' => $solicitud->CURP,
                            'entidadNacimiento' =>
                                $solicitud->EntidadNacimiento,
                            'fechaNacimientoDate' => date(
                                $solicitud->FechaNacimiento
                            ),
                            'fechaNacimientoTexto' =>
                                $solicitud->FechaNacimiento,
                            'genero' =>
                                strtoupper($solicitud->Sexo) == 'H'
                                    ? 'MASCULINO'
                                    : 'FEMENINO',
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
                                'descripcion' =>
                                    $solicitud->ParentescoJefeHogar,
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
                            'tipoAsentamiento' => [
                                'codigo' => $solicitud->CveTipoColonia
                                    ? $solicitud->CveTipoColonia
                                    : ' ',
                                'nombre' => $solicitud->TipoColonia,
                            ],
                            'asentamiento' => $solicitud->ColoniaVive,
                            'numeroExt' =>
                                $solicitud->NoExtVive != null
                                    ? $solicitud->NoExtVive
                                    : 'S/N',
                            'numeroInt' => $solicitud->NoIntVive,
                            'entidadFederativa' => $solicitud->EntidadVive,
                            //'localidad' => $solicitud->LocalidadVive,
                            'localidad' => [
                                'codigo' => strval($cveLocalidad->CveInegi),
                                'nombre' => $cveLocalidad->Nombre
                                    ? $cveLocalidad->Nombre
                                    : '',
                            ],
                            'municipio' => [
                                'codigo' => substr(
                                    $cveLocalidad->CveInegi,
                                    0,
                                    5
                                ),
                                'nombre' => $solicitud->MunicipioVive
                                    ? $solicitud->MunicipioVive
                                    : '',
                            ],
                            'zonaImpulso' => [
                                'codigo' => $solicitud->CveZAP
                                    ? substr($solicitud->CveZAP, 7, 5)
                                    : 'Z',
                                'nombre' => $solicitud->CveZAP
                                    ? $solicitud->CveZAP
                                    : 'Z',
                            ],
                            'tipoVialidad' => [
                                'codigo' => $solicitud->CveTipoVialidad
                                    ? $solicitud->CveTipoVialidad
                                    : ' ',
                                'nombre' => $solicitud->TipoVialidad
                                    ? $solicitud->TipoVialidad
                                    : ' ',
                            ],
                            'calle' => $solicitud->CalleVive,
                            'referencias' => is_null($solicitud->Referencias)
                                ? ''
                                : $solicitud->Referencias,
                            'coordenadas' => [
                                'latitud' => $solicitud->Latitud
                                    ? intval($solicitud->Latitud)
                                    : 0,
                                'longitud' => $solicitud->Longitud
                                    ? intval($solicitud->Longitud)
                                    : 0,
                            ],
                            'ageb' => $solicitud->AGEB ? $solicitud->AGEB : '',
                            'manzana' => $solicitud->Manzana
                                ? $solicitud->Manzana
                                : '',
                            'zona' => $solicitud->TipoZona
                                ? $solicitud->TipoZona
                                : '',
                            'solicitudImpulso' =>
                                $solicitud->TarjetaImpulso == 1,
                            'autorizaContacto' =>
                                $solicitud->ContactoTarjetaImpulso == 1,
                        ],
                    ],
                    JSON_UNESCAPED_UNICODE
                ),
            ];
        }
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
                'tipo' => 'Teléfono de Recados',
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
            $mimeType = 'image/jpeg';
            if (strtoupper($file->Extension) == 'PDF') {
                $mimeType = 'application/pdf';
            } elseif (strtoupper($file->Extension) == 'PNG') {
                $mimeType = 'image/png';
            }

            $formatedFile = [
                'llave' =>
                    $formato . '_' . str_replace('.', '', $file->Clasificacion),
                'ruta' => Storage::disk('subidos')->path($file->NombreSistema),
                //     '/Users/diegolopez/Documents/GitProyect/vales/apivales/public/subidos/' .
                //     $file->NombreSistema,
                // '/var/www/html/plataforma/apivales/public/subidos/' .$file->NombreSistema,
                'nombre' => str_replace('.', '', $file->Clasificacion),
                'header' => $mimeType,
            ];
            array_push($files, $formatedFile);
        }
        return $files;
    }

    private function updateSolicitudFromCedula($cedula, $user, $programa)
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

        if ($programa == 2) {
            $tableSol = 'calentadores_solicitudes';
            $tableCedulas = 'calentadores_cedulas';
        } elseif ($programa == 1) {
            $tableSol = 'cedulas_solicitudes';
            $tableCedulas = 'cedulas';
        } elseif ($programa == 3) {
            $tableSol = 'proyectos_solicitudes';
            $tableCedulas = 'proyectos_cedulas';
        } else {
            $tableSol = 'yopuedo_solicitudes';
            $tableCedulas = 'yopuedo_cedulas';
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
            // $file->move('subidos', $uniqueName);

            Storage::disk('subidos')->put(
                $uniqueName,
                File::get($file->getRealPath()),
                'public'
            );

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
        $img = new Imagick();
        $width = 1920;
        $height = 1920;

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

            if (
                in_array(mb_strtolower($extension, 'utf-8'), [
                    'png',
                    'jpg',
                    'jpeg',
                    'gif',
                    'tiff',
                ])
            ) {
                //Ruta temporal para reducción de tamaño
                $file->move('subidos/tmp', $uniqueName);
                $img_tmp_path = sprintf('subidos/tmp/%s', $uniqueName);
                $img->readImage($img_tmp_path);
                $img->adaptiveResizeImage($width, $height);

                //Guardar en el nuevo storage
                $url_storage = Storage::disk('subidos')->path($uniqueName);
                // $img->writeImage(sprintf('subidos/%s', $uniqueName));
                $img->writeImage($url_storage);
                File::delete($img_tmp_path);
            } else {
                // $file->move('subidos', $uniqueName);
                Storage::disk('subidos')->put(
                    $uniqueName,
                    File::get($file->getRealPath()),
                    'public'
                );
            }
            $tableArchivos = 'solicitud_archivos';
            DB::table($tableArchivos)->insert($fileObject);
        }
        $flag = $this->validarExpediente($id);
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
        $flag = $this->validarExpediente($id);
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
        // $fullPath = public_path('/subidos/');
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
            $imageMagick = new Imagick();
            foreach ($files as $key => $file) {
                $imageContent = $this->imageBase64Content($file);
                $uniqueName = uniqid() . $extension[$key];
                $clasification = $arrayClasifiacion[$key];
                $originalName = $names[$key];

                // File::put($fullPath . $uniqueName, $imageContent);
                Storage::disk('subidos')->put(
                    $uniqueName,
                    $imageContent,
                    'public'
                );

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
            $flag = $this->validarExpediente($id);
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
        return base64_decode($image);
    }

    public function envioMasivoVentanilla(Request $request)
    {
        try {
            $arraySinFolio = [];
            $arrayFoliosNoValidos = [];
            $arrayCURPNoValida = [];
            $arraySolicitudesIncompletas = [];
            $arrayEnviadas = [];
            $solicitudesAEnviar = DB::table('valesrvii_ventanilla_completos')
                ->select('id')
                ->whereNULL('Enviada')
                ->get();

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
        //Se valida si ya este registrado.
        if ($folio->Folio != null) {
            $urlValidacionFolio =
                'https://api-integracion-ventanilla-impulso.guanajuato.gob.mx/v1/application/external/validate/' .
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
                    'success' => false,
                    'data' => $responseBody->message,
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

        if (
            !isset($solicitud->MunicipioVive) ||
            !isset($solicitud->LocalidadVive)
        ) {
            return [
                'success' => false,
                'data' => 'Solicitud sin Municipio o Localidad',
                'codigo' => '1',
            ];
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
            ->whereNULL('FechaElimino')
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
            ->whereNULL('FechaElimino')
            ->get();

        $solicitudJson = $this->formatSolicitudIGTOJson($solicitud);
        if (!$solicitudJson['success']) {
            return [
                'success' => false,
                'data' => $solicitudJson['error'],
                'codigo' => '3',
            ];
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

        if ($solicitud->idUsuarioCreo == 1312) {
            $authUsuario = $this->getAuthUsuario(
                $solicitud->UsuarioAplicativo,
                1
            );
        } else {
            $authUsuario = $this->getAuthUsuario($solicitud->idUsuarioCreo, 2);
        }

        $dataCompleted = [
            'solicitud' => $solicitudJson['solicitud'],
            'programa' => $programa,
            'documentos' => $docs,
            'authUsuario' => $authUsuario,
            'campoUsuario' => $cUsuario,
        ];
        //dd($infoFiles);
        $request2 = new HTTP_Request2();

        $url = '';

        if ($solicitud->idVersion == 2) {
            $url =
                'https://api-integracion-ventanilla-impulso.guanajuato.gob.mx/v1/application/external/solicitud/register/v2';
        } else {
            $url =
                'https://api-integracion-ventanilla-impulso.guanajuato.gob.mx/v1/application/external/solicitud/register';
        }

        $request2->setUrl($url);
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
            //dd($response);
            if ($response->getStatus() == 200) {
                $resp = json_decode($response->getBody());
                $message = $resp->message;
                if ($resp->success) {
                    try {
                        DB::table('cedulas_solicitudes')
                            ->where('id', $id)
                            ->update([
                                'idEstatus' => '8',
                                'ListaParaEnviar' => '2',
                                'UsuarioEnvio' => '1',
                                'FechaEnvio' => date('Y-m-d H:i:s'),
                            ]);

                        DB::table('valesrvii_ventanilla_completos')
                            ->where('id', $id)
                            ->update([
                                'Enviada' => '1',
                            ]);

                        $flag = true;
                        return [
                            'success' => $flag,
                            'data' => $message,
                            'codigo' => '4',
                        ];
                    } catch (Exception $e) {
                        return [
                            'success' => false,
                            'data' => $message,
                            'codigo' => '5',
                        ];
                    }
                } else {
                    return [
                        'success' => false,
                        'data' => $message,
                        'codigo' => '2',
                    ];
                }
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
        $userCreo = null;
        $userOwned = null;

        $solicitud = DB::table('cedulas_solicitudes')
            ->where('id', $id)
            ->get()
            ->first();

        $userCreo = null;

        if ($solicitud->idEnlace != null) {
            $userOwned = $solicitud->idEnlace;
        }

        $idMunicipio = DB::table('et_cat_municipio')
            ->select('id')
            ->where('Nombre', $solicitud->MunicipioVive)
            ->get()
            ->first();

        $idLocalidad = DB::table('et_cat_localidad_2022')
            ->select('id')
            ->where([
                'idMunicipio' => $idMunicipio->id,
                'Nombre' => $solicitud->LocalidadVive,
            ])
            ->get()
            ->first();
        if ($solicitud->idUsuarioCreo == 1312) {
            if ($solicitud->UsuarioAplicativo != null) {
                $userCreo = DB::table('users_aplicativo_web')
                    ->select('idUser')
                    ->where('UserName', $solicitud->UsuarioAplicativo)
                    ->get()
                    ->first();
            }
        }

        $dataVales = [
            'FechaSolicitud' => $solicitud->FechaSolicitud
                ? $solicitud->FechaSolicitud
                : null,
            'FolioSolicitud' => $solicitud->Folio ? $solicitud->Folio : null,
            'idIncidencia' => '1',
            'CURP' => $solicitud->CURP ? $solicitud->CURP : null,
            'Ocupacion' => $solicitud->OcupacionJefeHogar
                ? $solicitud->OcupacionJefeHogar
                : null,
            'Nombre' => $solicitud->Nombre ? $solicitud->Nombre : null,
            'Paterno' => $solicitud->Paterno ? $solicitud->Paterno : null,
            'Materno' => $solicitud->Materno ? $solicitud->Materno : null,
            'Sexo' => $solicitud->Sexo ? $solicitud->Sexo : null,
            'FechaNacimiento' => $solicitud->FechaNacimiento
                ? $solicitud->FechaNacimiento
                : null,
            'Calle' => $solicitud->CalleVive ? $solicitud->CalleVive : null,
            'NumInt' => $solicitud->NoIntVive ? $solicitud->NoIntVive : null,
            'NumExt' => $solicitud->NoExtVive ? $solicitud->NoExtVive : null,
            'Colonia' => $solicitud->ColoniaVive
                ? $solicitud->ColoniaVive
                : null,
            'CP' => $solicitud->CPVive ? $solicitud->CPVive : null,
            'idMunicipio' => $idMunicipio->id ? $idMunicipio->id : null,
            'idLocalidad' => $idLocalidad->id ? $idLocalidad->id : null,
            'TelFijo' => $solicitud->Telefono ? $solicitud->Telefono : null,
            'TelCelular' => $solicitud->Celular ? $solicitud->Celular : null,
            'TelRecados' => $solicitud->TelRecados
                ? $solicitud->TelRecados
                : null,
            'CorreoElectronico' => $solicitud->Correo
                ? $solicitud->Correo
                : null,
            'idStatus' => 1,
            'IngresoPercibido' => $solicitud->IngresoMensual
                ? $solicitud->IngresoMensual
                : null,
            'OtrosIngresos' => $solicitud->OtrosIngresos
                ? $solicitud->OtrosIngresos
                : null,
            'NumeroPersonas' => $solicitud->PersonasDependientes
                ? $solicitud->PersonasDependientes
                : null,
            'UserOwned' =>
                $userOwned != null
                    ? $userOwned
                    : ($solicitud->idUsuarioCreo == 1312 && $userCreo != null
                        ? $userCreo->idUser
                        : $solicitud->idUsuarioCreo),

            'TotalIngresos' => $solicitud->TotalIngreso
                ? $solicitud->TotalIngreso
                : null,
            //'OcupacionOtro' => 0,
            'UserCreated' =>
                $solicitud->idUsuarioCreo == 1312 && $userCreo != null
                    ? $userCreo->idUser
                    : $solicitud->idUsuarioCreo,
            'UserUpdated' =>
                $solicitud->idUsuarioCreo == 1312 && $userCreo != null
                    ? $userCreo->idUser
                    : $solicitud->idUsuarioCreo,
            'INEVencida' => 0,
            'isDocumentacionEntrega' => 0,
            'Bloqueado' => 1,
            // 'BloqueadoUser' => $user->id,
            'BloqueadoDate' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        return $dataVales;
    }

    public function setValesUpdate($id)
    {
        $user = auth()->user();
        $userCreo = null;
        $userOwned = null;

        $solicitud = DB::table('cedulas_solicitudes')
            ->where('id', $id)
            ->get()
            ->first();

        $userCreo = null;

        if ($solicitud->idEnlace != null) {
            $userOwned = $solicitud->idEnlace;
        }

        $idMunicipio = DB::table('et_cat_municipio')
            ->select('id')
            ->where('Nombre', $solicitud->MunicipioVive)
            ->get()
            ->first();

        $idLocalidad = DB::table('et_cat_localidad_2022')
            ->select('id')
            ->where([
                'idMunicipio' => $idMunicipio->id,
                'Nombre' => $solicitud->LocalidadVive,
            ])
            ->get()
            ->first();
        if ($solicitud->idUsuarioCreo == 1312) {
            if ($solicitud->UsuarioAplicativo != null) {
                $userCreo = DB::table('users_aplicativo_web')
                    ->select('idUser')
                    ->where('UserName', $solicitud->UsuarioAplicativo)
                    ->get()
                    ->first();
            }
        }

        $dataVales = [
            'FechaSolicitud' => $solicitud->FechaSolicitud
                ? $solicitud->FechaSolicitud
                : null,
            'FolioSolicitud' => $solicitud->Folio ? $solicitud->Folio : null,
            'idIncidencia' => '1',
            'CURP' => $solicitud->CURP ? $solicitud->CURP : null,
            'Ocupacion' => $solicitud->OcupacionJefeHogar
                ? $solicitud->OcupacionJefeHogar
                : null,
            'Nombre' => $solicitud->Nombre ? $solicitud->Nombre : null,
            'Paterno' => $solicitud->Paterno ? $solicitud->Paterno : null,
            'Materno' => $solicitud->Materno ? $solicitud->Materno : null,
            'Sexo' => $solicitud->Sexo ? $solicitud->Sexo : null,
            'FechaNacimiento' => $solicitud->FechaNacimiento
                ? $solicitud->FechaNacimiento
                : null,
            'Calle' => $solicitud->CalleVive ? $solicitud->CalleVive : null,
            'NumInt' => $solicitud->NoIntVive ? $solicitud->NoIntVive : null,
            'NumExt' => $solicitud->NoExtVive ? $solicitud->NoExtVive : null,
            'Colonia' => $solicitud->ColoniaVive
                ? $solicitud->ColoniaVive
                : null,
            'CP' => $solicitud->CPVive ? $solicitud->CPVive : null,
            'idMunicipio' => $idMunicipio->id ? $idMunicipio->id : null,
            'idLocalidad' => $idLocalidad->id ? $idLocalidad->id : null,
            'TelFijo' => $solicitud->Telefono ? $solicitud->Telefono : null,
            'TelCelular' => $solicitud->Celular ? $solicitud->Celular : null,
            'TelRecados' => $solicitud->TelRecados
                ? $solicitud->TelRecados
                : null,
            'CorreoElectronico' => $solicitud->Correo
                ? $solicitud->Correo
                : null,
            'IngresoPercibido' => $solicitud->IngresoMensual
                ? $solicitud->IngresoMensual
                : null,
            'OtrosIngresos' => $solicitud->OtrosIngresos
                ? $solicitud->OtrosIngresos
                : null,
            'NumeroPersonas' => $solicitud->PersonasDependientes
                ? $solicitud->PersonasDependientes
                : null,
            'UserOwned' =>
                $userOwned != null
                    ? $userOwned
                    : ($solicitud->idUsuarioCreo == 1312 && $userCreo != null
                        ? $userCreo->idUser
                        : $solicitud->idUsuarioCreo),

            'TotalIngresos' => $solicitud->TotalIngreso
                ? $solicitud->TotalIngreso
                : null,
            'UserUpdated' =>
                $solicitud->idUsuarioCreo == 1312 && $userCreo != null
                    ? $userCreo->idUser
                    : $solicitud->idUsuarioCreo,
            'Bloqueado' => 1,
            // 'BloqueadoUser' => $user->id,
            'BloqueadoDate' => date('Y-m-d H:i:s'),
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
            $userDefault = DB::table('cuentas_regionales_ventanilla')
                ->selectRaw('uId,Nombre,correoCuenta,rol')
                ->where('id', 8)
                ->first();
            $json = json_encode(
                [
                    'uid' => $userDefault->uId,
                    'name' => $userDefault->Nombre, //Cambiar a sedeshu
                    'email' => $userDefault->correoCuenta,
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
                DB::raw('LPAD(HEX(vales.id),6,0) as Id'),
                'et_cat_municipio.SubRegion AS Region',
                DB::raw('LPAD(HEX(vales.idVale),6,0) as FolioVales'),
                // DB::raw('LPAD(HEX(vales.idVale),6,0) as FolioVales'),
                'vales.FechaSolicitud',
                'vales.CURP',
                'vales.Nombre',
                DB::raw("IFNULL(vales.Paterno,'')"),
                DB::raw("IFNULL(vales.Materno,'')"),
                'vales.Sexo',
                //'vales.FechaNacimiento',
                //'vales.OcupacionJefeHogar',
                'vales.ColoniaVive',
                'vales.CalleVive',
                'vales.NoExtVive',
                'vales.NoIntVive',
                'vales.CPVive',
                'vales.MunicipioVive AS Municipio',
                'vales.LocalidadVive AS Localidad',
                'vales.Latitud',
                'vales.Longitud',
                'vales.Telefono',
                'vales.Celular',
                'vales_status.Estatus',
                'i.Incidencia',
                'v.Remesa',
                's.SerieInicial',
                's.SerieFinal',
                //'vales.TelRecados',
                //'vales.Correo',
                //'vales.IngresoMensual',
                //'vales.OtrosIngresos',
                //'vales.TotalIngreso',
                //'vales.PersonasDependientes',
                //DB::raw("'Sin Incidencia' AS Incidencia"),
                // DB::raw("
                //     CASE
                //         WHEN
                //             vales.ListaParaEnviar = 1
                //         THEN
                //             'SI'
                //         ELSE
                //             'NO'
                //         END
                //     AS ListaParaEnviar
                //     "),

                DB::raw("IF(d.idSolicitud IS NULL,'','DEVUELTO') AS Devuelto"),
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
                DB::raw(
                    "CONCAT_WS( ' ', actualizo.Nombre, actualizo.Paterno, actualizo.Materno ) AS UserUpdated"
                ),
                DB::raw(
                    "CASE 
                        WHEN 
                            vales.idEnlace IS NULL 
                        THEN 
                            vales.Enlace 
                        ELSE 
                            CONCAT_WS( ' ', enlace.Nombre, enlace.Paterno, enlace.Materno ) 
                        END 
                        AS Enlace"
                )
            )

            ->leftJoin('users', 'users.id', '=', 'vales.idUsuarioCreo')
            ->leftJoin(
                'users as actualizo',
                'actualizo.id',
                '=',
                'vales.idUsuarioActualizo'
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
            ->leftJoin('users AS enlace', 'enlace.id', '=', 'vales.idEnlace')
            ->Join('vales AS v', 'v.id', '=', 'vales.idVale')
            ->Join('vales_incidencias AS i', 'v.idIncidencia', '=', 'i.id')
            ->Join('vales_status', 'vales_status.id', '=', 'v.idStatus')
            ->LeftJoin('vales_devueltos AS d', 'v.id', '=', 'd.idSolicitud')
            ->LeftJoin('vales_solicitudes AS s', 'v.id', '=', 's.idSolicitud')
            ->whereRaw('FechaElimino IS NULL');

        //dd($res->toSql());

        //Agregando Filtros por permisos
        $permisos = $this->getPermisos();
        if ($permisos === null) {
            $response = [
                'success' => true,
                'results' => false,
                'total' => 0,
                'message' => 'No tiene permisos en este módulo',
            ];

            return response()->json($response, 200);
        }

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
        $usersNames = [];
        $newFilter = [];
        $idsUsers = '';
        $usersApp = '';

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
                    foreach ($params['filtered'] as $filtro) {
                        if ($filtro['id'] == '.articulador') {
                            $idsUsers = implode(', ', $filtro['value']);
                            foreach ($filtro['value'] as $idUser) {
                                $userN = DB::table('users_aplicativo_web')
                                    ->select('UserName')
                                    ->where('idUser', $idUser)
                                    ->get()
                                    ->first();

                                if ($userN != null) {
                                    $usersNames[] =
                                        "'" . $userN->UserName . "'";
                                }
                            }
                            if (count($usersNames) > 0) {
                                $usersApp = implode(', ', $usersNames);
                            }
                        } else {
                            $newFilter[] = [
                                'id' => $filtro['id'],
                                'value' => $filtro['value'],
                            ];
                        }
                    }
                    $filtersCedulas = [''];
                    foreach ($newFilter as $filtro) {
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

                            if ($id == '.Folio') {
                                $id = '.idVale';
                                $value = hexdec($value);
                            }

                            if ($id == '.MunicipioVive') {
                                foreach ($value as $m) {
                                    $mun[] = "'" . $m . "'";
                                }
                                $value = $mun;
                            }

                            if ($id == '.id') {
                                $id = '.idVale';
                                $value = hexdec($value);
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
            }
        }

        if ($filterQuery != '') {
            $res->whereRaw($filterQuery);
        }

        if ($filtroCapturo !== '') {
            $res->whereRaw($filtroCapturo);
        }

        if ($idsUsers !== '') {
            $filtroArticuladores =
                '(' .
                $tableSol .
                '.idUsuarioCreo IN (' .
                $idsUsers .
                ') OR ' .
                $tableSol .
                '.UsuarioAplicativo IN (' .
                $usersApp .
                ')' .
                ')';
            $res->whereRaw($filtroArticuladores);
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

    public function getArticuladoresVentanilla(Request $request)
    {
        $parameters = $request->all();
        $user = auth()->user();
        $id_valor = $user->id;
        $permisos = $this->getPermisos();
        if ($permisos === null) {
            $response = [
                'success' => true,
                'results' => false,
                'total' => 0,
                'message' => 'No tiene permisos en este módulo',
            ];

            return response()->json($response, 200);
        }
        $seguimiento = $permisos->Seguimiento;
        $viewall = $permisos->ViewAll;

        try {
            $res = DB::table('users_aplicativo_web')->select(
                'idUser',
                'Nombre'
            );

            if ($viewall < 1 && $seguimiento < 1) {
                $res = DB::table('users_aplicativo_web')
                    ->select('idUser', 'Nombre')
                    ->where('idUser', $user->id);
            } elseif ($viewall < 1) {
                $res->whereIn('idUserOwner', function ($query) use ($id_valor) {
                    $query
                        ->select('idUserOwner')
                        ->from('users_aplicativo_web')
                        ->where('idUser', '=', $id_valor);
                });
            }

            if ($res->count() === 0) {
                $res = DB::table('users_aplicativo_web')->select(
                    'idUser',
                    'Nombre'
                );
            }

            $res = $res->orderBy('Nombre');

            $total = $res->count();
            $res = $res->get();

            return [
                'success' => true,
                'results' => true,
                'total' => $total,
                'filtros' => $parameters['filtered'],
                'data' => $res,
            ];
        } catch (QueryException $e) {
            $errors = [
                'Clave' => '01',
            ];
            $response = [
                'success' => true,
                'results' => false,
                'total' => 0,
                'filtros' => $parameters['filtered'],
                'errors' => $e->getMessage(),
                'message' => 'Campo de consulta incorrecto',
            ];

            return response()->json($response, 200);
        }
    }

    public function convertImage(Request $request)
    {
        $solicitudesCollect = DB::table('solicitud_ines')
            ->select('id')
            ->whereNULL('Procesada')
            ->get()
            ->chunk(500);

        $flag = false;

        foreach ($solicitudesCollect as $solicitudes) {
            foreach ($solicitudes as $s) {
                $flag = $this->convert($s->id);
                if ($flag) {
                    DB::table('solicitud_ines')
                        ->where('id', $s->id)
                        ->update(['Procesada' => 1]);
                }
            }
        }

        $response = [
            'success' => true,
            'results' => true,
            'message' => 'Archivos Procesados',
        ];

        return response()->json($response, 200);
    }

    public function convert($id)
    {
        try {
            $files = DB::table('solicitud_archivos')
                ->where('idSolicitud', $id)
                ->whereIN('idClasificacion', ['3', '9'])
                ->whereIN('Extension', ['jpg', 'jpeg', 'png'])
                ->whereNull('FechaElimino')
                ->get();

            if ($files != null) {
                $img = new Imagick();
                $width = 1500;
                $height = 1500;
                $arrayFiles = [];
                foreach ($files as $file) {
                    $img_tmp_path = Storage::disk('subidos')->path(
                        $file->NombreSistema
                    );
                    $img->readImage($img_tmp_path);
                    $img->adaptiveResizeImage($width, $height);
                    $newFileName = $file->NombreSistema . '_adaptative';
                    $url_storage = Storage::disk('subidos')->path($newFileName);
                    $img->writeImage($url_storage);
                    $img2 = file_get_contents($url_storage);
                    $data = base64_encode($img2);
                    $arrayFiles[] = $data;
                    File::delete($url_storage);
                }

                if (count($arrayFiles) > 0) {
                    try {
                        $request = new HTTP_Request2();
                        $request->setUrl(
                            'http://seguimiento.guanajuato.gob.mx/Validacion/api/Images64ToPDF'
                        );
                        $request->setMethod(HTTP_Request2::METHOD_POST);
                        $request->setConfig([
                            'follow_redirects' => true,
                        ]);
                        $request->setHeader([
                            'Content-Type' => 'application/json',
                        ]);

                        $request->setBody(
                            json_encode(
                                [
                                    'lstPDF' => $arrayFiles,
                                ],
                                JSON_UNESCAPED_UNICODE
                            )
                        );
                        try {
                            $response = $request->send();

                            if ($response->getStatus() == 200) {
                                $f = $response->getBody();
                                //$imageContent = $this->imageBase64Content($f);
                                $uniqueName = uniqid() . '.pdf';
                                $clasification = '3';
                                $originalName = 'INE';

                                Storage::disk('subidos')->put(
                                    $uniqueName,
                                    $f,
                                    'public'
                                );

                                DB::table('solicitud_archivos')
                                    ->where('idSolicitud', $id)
                                    ->whereIn('idClasificacion', [3, 9])
                                    ->update(['idClasificacion' => 8]);

                                $fileObject = [
                                    'idSolicitud' => intval($id),
                                    'idClasificacion' => intval($clasification),
                                    'NombreOriginal' => $originalName,
                                    'NombreSistema' => $uniqueName,
                                    'Extension' => 'pdf',
                                    'Tipo' => 'pdf',
                                    'Tamanio' => '',
                                    'idUsuarioCreo' => '1312',
                                    'FechaCreo' => date('Y-m-d H:i:s'),
                                ];
                                $tableArchivos = 'solicitud_archivos';
                                DB::table($tableArchivos)->insert($fileObject);

                                return true;
                            } else {
                                return false;
                            }
                        } catch (HTTP_Request2_Exception $e) {
                            return false;
                            //dd('Error: ' . $e->getMessage());
                        }

                        return false;
                    } catch (Exception $e) {
                        return false;
                    }
                }
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    public function getFile(Request $request)
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

            $solicitudVale = DB::table('cedulas_solicitudes')
                ->selectRaw('*,lpad(hex(idVale),6,0)AS idValeHex')
                ->where('id', $params['id'])
                ->whereNull('FechaElimino')
                ->get()
                ->first();

            if ($solicitudVale == null) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' => 'Solicitud no encontrada',
                    'message' => 'Solicitud no encontrada',
                ];
                return response()->json($response, 200);
            }

            //$id = $solicitudVale->idValeHex;
            $id = '02B9E8';
            $folio = $solicitudVale->Folio;
            $acuerdo = 'ACUERDO 01/ORD01/2022';
            $region = $solicitudVale->Region;
            $enlace = 'Diego Alejandro Lopez Puente';
            $nombre =
                $solicitudVale->Nombre .
                ' ' .
                $solicitudVale->Paterno .
                ' ' .
                $solicitudVale->Materno;
            $curp = $solicitudVale->CURP;
            $domicilio =
                $solicitudVale->CalleVive .
                ' NoExt ' .
                $solicitudVale->NoExtVive .
                ' NoInt ' .
                $solicitudVale->NoIntVive;
            $municipio = $solicitudVale->MunicipioVive;
            $localidad = $solicitudVale->LocalidadVive;
            $colonia = $solicitudVale->ColoniaVive;
            $cp = $solicitudVale->CPVive;
            $folioinicial = '1';
            $foliofinal = '10';

            $nombreArchivo = 'acuse_' . $solicitudVale->Folio;

            $pdf = \PDF::loadView(
                'pdf',
                compact(
                    'id',
                    'folio',
                    'acuerdo',
                    'region',
                    'enlace',
                    'nombre',
                    'curp',
                    'domicilio',
                    'municipio',
                    'localidad',
                    'colonia',
                    'cp',
                    'folioinicial',
                    'foliofinal'
                )
            );

            return $pdf->download($nombreArchivo . '.pdf');
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

    public function convertImg($id, $clasificacion, $table)
    {
        try {
            $files = DB::table($table)
                ->where('', $id)
                ->whereIN('idClasificacion', [$clasificacion])
                ->whereIN('Extension', ['jpg', 'jpeg', 'png'])
                ->whereNull('FechaElimino')
                ->get();

            if ($files != null) {
                $img = new Imagick();
                $width = 1200;
                $height = 1200;
                $arrayFiles = [];
                foreach ($files as $file) {
                    $img_tmp_path = Storage::disk('subidos')->path(
                        $file->NombreSistema
                    );
                    $img->readImage($img_tmp_path);
                    $img->adaptiveResizeImage($width, $height);
                    $newFileName = $file->NombreSistema . '_adaptative';
                    $url_storage = Storage::disk('subidos')->path($newFileName);
                    $img->writeImage($url_storage);
                    $img2 = file_get_contents($url_storage);
                    $data = base64_encode($img2);
                    $arrayFiles[] = $data;
                    File::delete($url_storage);
                }

                if (count($arrayFiles) > 0) {
                    try {
                        $request = new HTTP_Request2();
                        $request->setUrl(
                            'http://seguimiento.guanajuato.gob.mx/Validacion/api/Images64ToPDF'
                        );
                        $request->setMethod(HTTP_Request2::METHOD_POST);
                        $request->setConfig([
                            'follow_redirects' => true,
                        ]);
                        $request->setHeader([
                            'Content-Type' => 'application/json',
                        ]);

                        $request->setBody(
                            json_encode(
                                [
                                    'lstPDF' => $arrayFiles,
                                ],
                                JSON_UNESCAPED_UNICODE
                            )
                        );
                        try {
                            $response = $request->send();

                            if ($response->getStatus() == 200) {
                                $f = $response->getBody();
                                //$imageContent = $this->imageBase64Content($f);
                                $uniqueName = uniqid() . '.pdf';
                                $clasification = $clasifiacion;
                                $originalName = 'INE';

                                Storage::disk('subidos')->put(
                                    $uniqueName,
                                    $f,
                                    'public'
                                );

                                DB::table('solicitud_archivos')
                                    ->where('idSolicitud', $id)
                                    ->whereIn('idClasificacion', [
                                        $clasificacion,
                                    ])
                                    ->update(['idClasificacion' => 8]);

                                $fileObject = [
                                    'idSolicitud' => intval($id),
                                    'idClasificacion' => intval($clasification),
                                    'NombreOriginal' => $originalName,
                                    'NombreSistema' => $uniqueName,
                                    'Extension' => 'pdf',
                                    'Tipo' => 'pdf',
                                    'Tamanio' => '',
                                    'idUsuarioCreo' => '1312',
                                    'FechaCreo' => date('Y-m-d H:i:s'),
                                ];
                                $tableArchivos = 'solicitud_archivos';
                                DB::table($tableArchivos)->insert($fileObject);

                                return true;
                            } else {
                                return false;
                            }
                        } catch (HTTP_Request2_Exception $e) {
                            return false;
                            //dd('Error: ' . $e->getMessage());
                        }

                        return false;
                    } catch (Exception $e) {
                        return false;
                    }
                }
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    public function validarExpediente($id)
    {
        $formato = DB::table('cedulas_solicitudes')
            ->select('Formato')
            ->where('id', $id)
            ->get()
            ->first();

        $ine = DB::table('solicitud_archivos')
            ->where('idSolicitud', $id)
            ->where('idClasificacion', '3')
            ->whereNull('FechaElimino')
            ->get()
            ->first();

        $pvg = DB::table('solicitud_archivos')
            ->where('idSolicitud', $id)
            ->where('idClasificacion', '2')
            ->whereNull('FechaElimino')
            ->get()
            ->first();

        $acuse = DB::table('solicitud_archivos')
            ->where('idSolicitud', $id)
            ->where('idClasificacion', '5')
            ->whereNull('FechaElimino')
            ->get()
            ->first();

        if ($formato->Formato == 1) {
            if ($ine == null || $pvg == null || $acuse == null) {
                DB::table('cedulas_solicitudes')
                    ->where('id', $id)
                    ->update(['ExpedienteCompleto' => 0]);
                return false;
            }
        } else {
            if ($ine == null) {
                $tarjeta = DB::table('solicitud_archivos')
                    ->where('idSolicitud', $id)
                    ->where('idClasificacion', '13')
                    ->whereNull('FechaElimino')
                    ->get()
                    ->first();
                if ($tarjeta == null) {
                    DB::table('cedulas_solicitudes')
                        ->where('id', $id)
                        ->update(['ExpedienteCompleto' => 0]);
                    return false;
                }
            }
            if ($pvg == null) {
                DB::table('cedulas_solicitudes')
                    ->where('id', $id)
                    ->update(['ExpedienteCompleto' => 0]);
                return false;
            }
        }

        DB::table('cedulas_solicitudes')
            ->where('id', $id)
            ->update(['ExpedienteCompleto' => 1]);

        return true;
    }

    function updateLocation(Request $request)
    {
        $v = Validator::make($request->all(), [
            'tabla' => 'required',
        ]);

        if ($v->fails()) {
            $response = [
                'success' => true,
                'results' => false,
                'errors' => 'Falta Indicar la tabla a actualizar',
            ];
            return response()->json($response, 200);
        }
        $params = $request->all();
        $tabla = $params['tabla'];

        $registros = DB::table($tabla)
            ->select('id')
            ->whereNull('FechaElimino')
            ->where('ValidadoTarjetaContigoSi', 0)
            ->whereRaw('CalleVive IS NOT NULL AND NoExtVive IS NOT NULL')
            ->get()
            ->chunk(500);

        if ($registros->count() > 0) {
            foreach ($registros as $registro) {
                foreach ($registro as $data) {
                    $this->getDataLocation($data->id, $tabla);
                }
            }
        }
        $response = [
            'success' => true,
            'results' => true,
            'message' => 'Registros Procesados',
        ];

        return response()->json($response, 200);
    }

    function getDataLocation($id, $tableSol)
    {
        //http://139.70.80.197:8080/domicilios?loc=110200001&calle=poesstas&num=2089
        try {
            $folio = DB::table($tableSol)
                ->select(
                    $tableSol . '.*',
                    'et_cat_municipio.Id AS idMunicipio',
                    'et_cat_localidad_2022.CveInegi'
                )
                ->leftJoin(
                    'et_cat_municipio',
                    $tableSol . '.MunicipioVive',
                    'et_cat_municipio.Nombre'
                )
                ->leftJoin('et_cat_localidad_2022', function ($join) {
                    $join
                        ->on(
                            'et_cat_localidad_2022.idMunicipio',
                            'et_cat_municipio.Id'
                        )
                        ->on('et_cat_localidad_2022.Nombre', 'LocalidadVive');
                })
                ->where($tableSol . '.id', $id)
                ->get()
                ->first();
            $user = auth()->user();
        } catch (Exception $e) {
            return false;
        }
        try {
            if ($folio != null) {
                //dd($folio);
                $urlValidacionFolio =
                    'http://139.70.80.197:8080/domicilios?loc=' .
                    $folio->CveInegi .
                    '&calle=' .
                    $folio->CalleVive .
                    '&num=' .
                    $folio->NoExtVive;
                //dd($urlValidacionFolio);
                $client = new Client();
                $response = $client->request('GET', $urlValidacionFolio, [
                    'verify' => false,
                    'headers' => [
                        'Content-Type' => 'multipart/form-data',
                        //'Authorization' => '616c818fe33268648502f962',
                    ],
                ]);

                $responseBody = json_decode($response->getBody());

                if ($responseBody != null) {
                    if (count($responseBody) > 7) {
                        $latitud = explode(
                            ':',
                            str_replace("'", '', $responseBody[6])
                        );
                        $longitud = explode(
                            ':',
                            str_replace("'", '', $responseBody[7])
                        );
                        DB::table($tableSol)
                            ->where('id', $id)
                            ->update([
                                'Latitud' => $latitud[1],
                                'Longitud' => $longitud[1],
                                'ValidadoTarjetaContigoSi' => 1,
                            ]);
                    }
                }
            } else {
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    public function getReporteCompletoVales(Request $request)
    {
        ini_set('memory_limit', '-1');
        $user = auth()->user();
        $res = DB::table('reporteCompletoVales AS vales')
            ->select(
                'Region',
                'Incidencia',
                'id',
                'FolioVentanilla',
                'FolioImpulso',
                'FechaSolicitud',
                'CURP',
                'Nombre',
                'Paterno',
                'Materno',
                'Sexo',
                'FechaNacimiento',
                'Colonia',
                'Calle',
                'NumExt',
                'NumInt',
                'CP',
                'Latitud',
                'Longitud',
                'TelFijo',
                'TelCelular',
                'CorreoElectronico',
                'idLocalidad',
                'NumLocINEGI',
                'NumeroLocalidad',
                'CveZAP',
                'Localidad',
                'Municipio',
                'created_at',
                'updated_at',
                'UserCreated',
                'UserCapturo',
                'vales.UserOwned',
                'Responsable',
                'Estatus',
                'SerieInicial',
                'SerieFinal',
                'Remesa',
                'Entregado',
                'entrega_at',
                'idUserDocumentacion',
                'FechaDocumentacion'
            )
            ->get();

        // dd(str_replace_array('?', $res->getBindings(), $res->toSql()));

        $total = $res->count();
        if ($total == 0) {
            $reader = IOFactory::createReader('Xlsx');
            $spreadsheet = $reader->load(
                public_path() . '/archivos/formatoReporteSolicitudValesV8.xlsx'
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

        // dd('aqui');
        // //Mapeamos el resultado como un array
        $res = $res
            ->map(function ($x) {
                $x = is_object($x) ? (array) $x : $x;
                return $x;
            })
            ->toArray();

        $reader = IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load(
            public_path() . '/archivos/formatoReporteSolicitudValesV8.xlsx'
        );
        $sheet = $spreadsheet->getActiveSheet();
        $sheet
            ->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_LETTER);
        $sheet->getPageSetup()->setPrintArea('A1:V' . (intval($total) + 10));

        // //Llenar excel con el resultado del query
        $sheet->fromArray($res, null, 'B5');
        unset($res);
        //Agregamos la fecha
        $sheet->setCellValue('H2', 'Fecha Reporte: ' . date('Y-m-d H:i:s'));
        // // //Agregar el indice autonumerico
        for ($i = 1; $i <= $total; $i++) {
            $inicio = 4 + $i;
            $sheet->setCellValue('A' . $inicio, $i);
        }
        $sheet->getDefaultRowDimension()->setRowHeight(-1);
        // //guardamos el excel creado y luego lo obtenemos en $file para poder descargarlo
        $writer = new Xlsx($spreadsheet);
        $writer->save(
            'archivos/' . $user->email . 'SolicitudesValesGrandeza.xlsx'
        );
        unset($sheet);
        unset($writer);
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

    public function uploadExcel(Request $request)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', 600);
        $v = Validator::make($request->all(), [
            'NewFiles' => 'required',
        ]);

        if ($v->fails()) {
            $response = [
                'success' => true,
                'results' => false,
                'message' => 'No se envió ningun archivo',
                'errors' => 'No se envió ningun archivo',
            ];
            return response()->json($response, 200);
        }

        $params = $request->all();
        $userId = JWTAuth::parseToken()->toUser()->id;
        $file = $params['NewFiles'][0];
        $fechaActual = date('Y-m-d h-m-s');
        $nombreArchivo = $file->getClientOriginalName();
        $size = $file->getSize();

        $dataFile = [
            'Quincena' => $fechaActual,
            'Nombre' => $nombreArchivo,
            'Peso' => $size,
            'Registros' => 0,
            'FechaUpload' => $fechaActual,
            'UserUpload' => $userId,
        ];

        DB::beginTransaction();
        $id = DB::table('conciliacion_archivos')->insertGetId($dataFile);
        DB::commit();

        try {
            Excel::import(new ConciliacionImport($id), $file);

            $totalRows = DB::table('conciliacion_vales')
                ->selectRaw('COUNT(id) AS total')
                ->where('idArchivo', $id)
                ->get()
                ->first();

            if ($totalRows->total == 0) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'message' =>
                        'El excel esta vacio o los encabezados no coinciden',
                    'errors' =>
                        'El excel esta vacio o los encabezados no coinciden',
                ];
                return response()->json($response, 200);
            }

            DB::table('conciliacion_archivos')
                ->where('id', $id)
                ->update(['Registros' => intval($totalRows->total)]);

            return [
                'success' => true,
                'results' => true,
                'message' => 'Cargado con éxito',
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' =>
                    'Ha ocurrido un error en la petición ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    public function getConciliacionArchivos(Request $request)
    {
        $params = $request->all();
        try {
            $page = $params['page'];
            $pageSize = $params['pageSize'];
            $startIndex = $page * $pageSize;

            $res = DB::table('conciliacion_archivos as a')
                ->select(
                    'a.id',
                    'a.Quincena',
                    'a.Nombre',
                    'a.Peso',
                    'a.Registros',
                    'a.FechaUpload',
                    DB::raw(
                        "CONCAT_WS(' ',b.Nombre,IFNULL(b.Paterno,''),IFNULL(b.Materno,'')) as UserUpload"
                    )
                )
                ->leftJoin('users as b', 'b.id', '=', 'a.UserUpload')
                ->Where('Registros', '>', 0);
            $total = $res->count();

            $data = $res
                ->orderBy('a.id', 'desc')
                ->offset($startIndex)
                ->take($pageSize)
                ->get();

            return [
                'success' => true,
                'results' => true,
                'total' => $total,
                'data' => $data,
            ];
        } catch (\Illuminate\Database\QueryException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function getValesConciliacion(Request $request)
    {
        $v = Validator::make($request->all(), [
            'id' => 'required',
        ]);

        if ($v->fails()) {
            $response = [
                'success' => true,
                'results' => false,
                'errors' => 'Ocurrio un error.',
            ];
            return response()->json($response, 200);
        }
        ini_set('memory_limit', '-1');
        $params = $request->all();
        $idArchivo = $params['id'];
        $user = auth()->user();
        $res = DB::table('conciliacion_vales AS cv')
            ->select(
                'cv.cantidad',
                'cv.codigo',
                'cv.responsable_de_escaneo',
                'cv.farmacia',
                'cv.fecha_de_canje',
                'cv.tipo_de_operacion',
                'cv.mes_de_canje',
                'cv.observacion',
                'vs.Serie',
                'vs.SerieInicial',
                'vs.SerieFinal',
                'vr.RemesaSistema',
                'v.CURP',
                'v.id AS idSolicitud',
                DB::RAW('lpad( hex( v.id ), 6, 0 ) FolioSolicitud'),
                'v.Nombre',
                'v.Paterno',
                'v.Materno',
                'm.Nombre AS Municipio',
                'v.entrega_at',
                'v.ResponsableEntrega'
            )
            ->LeftJoin('folios_vales_2023 AS f', 'cv.codigo', 'f.CodigoBarras')
            ->LeftJoin('vales_series_2023 AS vs', 'f.Serie', 'vs.Serie')
            ->LeftJoin(
                DB::RAW(
                    '(SELECT id,idSolicitud,SerieInicial,SerieFinal FROM vales_solicitudes WHERE Ejercicio = 2023 ) AS s'
                ),
                's.SerieInicial',
                'vs.SerieInicial'
            )
            ->LeftJoin('vales AS v', 's.IdSolicitud', 'v.id')
            ->LeftJoin('vales_remesas AS vr', 'v.Remesa', 'vr.Remesa')
            ->LeftJoin('et_cat_municipio AS m', 'v.idMunicipio', 'm.id')
            ->where('cv.idArchivo', $idArchivo)
            ->orderBy('cv.id')
            ->get();

        //dd(str_replace_array('?', $res->getBindings(), $res->toSql()));

        $total = $res->count();
        if ($total == 0) {
            $reader = IOFactory::createReader('Xlsx');
            $spreadsheet = $reader->load(
                public_path() . '/archivos/formatoReporteSolicitudVales11.xlsx'
            );
            $writer = new Xlsx($spreadsheet);
            $writer->save(
                'archivos/' . $user->email . 'reporteConciliacionVales.xlsx'
            );
            $file =
                public_path() .
                '/archivos/' .
                $user->email .
                'reporteConciliacionVales.xlsx';

            return response()->download(
                $file,
                'ConciliacionValesGrandeza' . date('Y-m-d') . '.xlsx'
            );
        }

        //!Mapeamos el resultado como un array
        $res = $res
            ->map(function ($x) {
                $x = is_object($x) ? (array) $x : $x;
                return $x;
            })
            ->toArray();

        $reader = IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load(
            public_path() . '/archivos/formatoReporteSolicitudVales10.xlsx'
        );
        $sheet = $spreadsheet->getActiveSheet();
        $sheet
            ->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_LETTER);
        $sheet->getPageSetup()->setPrintArea('A1:V' . (intval($total) + 10));

        // //Llenar excel con el resultado del query
        $sheet->fromArray($res, null, 'B5');
        unset($res);
        //Agregamos la fecha
        $sheet->setCellValue('H2', 'Fecha Reporte: ' . date('Y-m-d H:i:s'));
        // // //Agregar el indice autonumerico
        for ($i = 1; $i <= $total; $i++) {
            $inicio = 4 + $i;
            $sheet->setCellValue('A' . $inicio, $i);
        }
        $sheet->getDefaultRowDimension()->setRowHeight(-1);
        // //guardamos el excel creado y luego lo obtenemos en $file para poder descargarlo
        $writer = new Xlsx($spreadsheet);
        $writer->save(
            'archivos/' . $user->email . 'ConciliacionValesGrandeza.xlsx'
        );
        unset($sheet);
        unset($writer);
        $file =
            public_path() .
            '/archivos/' .
            $user->email .
            'ConciliacionValesGrandeza.xlsx';

        return response()->download(
            $file,
            $user->email .
                'ConciliacionValesGrandeza' .
                date('Y-m-d H:i:s') .
                '.xlsx'
        );
    }
}
