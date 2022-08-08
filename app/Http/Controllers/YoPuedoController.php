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
use Storage;
use Validator;
use HTTP_Request2;

class YoPuedoController extends Controller
{
    function getPermisos()
    {
        $user = auth()->user();

        $permisos = DB::table('users_menus')
            ->where(['idUser' => $user->id, 'idMenu' => '17'])
            ->get()
            ->first();
        return $permisos;
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
                ->where('programa', '=', 'YO PUEDO, GTO PUEDE')
                ->where('Activo', '1')
                ->orderBy('label')
                ->get();

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
                "call getEstatusGlobalVentanillaYoPuedo('" .
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
                " call getEstatusGlobalVentanillaYoPuedoRegional('" .
                $idUserOwner->idUserOwner .
                "')";
        }

        if ($procedimiento === '') {
            $procedimiento = 'call getEstatusGlobalVentanillaYoPuedoGeneral';
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

    function getMunicipios(Request $request)
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
                $res_Vales = DB::table('yopuedo_solicitudes')
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

    function getCatalogosCedulas(Request $request)
    {
        try {
            $userId = JWTAuth::parseToken()->toUser()->id;

            $articuladores = DB::table('users_aplicativo_web')->select(
                'idUser AS value',
                'Nombre AS label'
            );

            $permisos = $this->getPermisos();

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
                ->where('programa', '=', 'YO PUEDO, GTO PUEDE')
                ->where('Activo', '1')
                ->orderBy('label')
                ->get();

            $cat_estado_civil = DB::table('cat_estado_civil')
                ->select('id AS value', 'EstadoCivil AS label')
                ->get();

            $cat_estatus_persona = DB::table('cat_estatus_persona')
                ->select('id AS value', 'Estatus AS label')
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
                'cat_estatus_persona' => $cat_estatus_persona,
                'archivos_clasificacion' => $archivos_clasificacion,
                'municipios' => $municipios,
                'articuladores' => $articuladores->get(),
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
            $user = auth()->user();

            $userId = JWTAuth::parseToken()->toUser()->id;

            DB::table('users_filtros')
                ->where('UserCreated', $userId)
                ->where('api', 'getYoPuedoVentanilla')
                ->delete();

            $parameters_serializado = serialize($params);

            //Insertamos los filtros
            DB::table('users_filtros')->insert([
                'UserCreated' => $userId,
                'Api' => 'getYoPuedoVentanilla',
                'Consulta' => $parameters_serializado,
                'created_at' => date('Y-m-d h-m-s'),
            ]);

            $tableSol = 'yopuedo_solicitudes';
            $tableCedulas =
                '(SELECT * FROM yopuedo_cedulas WHERE FechaElimino IS NULL) AS yopuedo_cedulas';

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

            $solicitudes = DB::table('yopuedo_solicitudes')
                ->selectRaw(
                    'yopuedo_solicitudes.*,' .
                        ' entidadesNacimiento.Entidad AS EntidadNacimiento, ' .
                        ' cat_estado_civil.EstadoCivil, ' .
                        ' cat_parentesco_jefe_hogar.Parentesco, ' .
                        ' cat_parentesco_tutor.Parentesco, ' .
                        ' entidadesVive.Entidad AS EntidadVive, ' .
                        ' m.Region AS RegionM, ' .
                        'CASE ' .
                        'WHEN ' .
                        'yopuedo_solicitudes.idUsuarioCreo = 1312 ' .
                        'THEN ' .
                        'ap.Nombre ' .
                        'ELSE ' .
                        "CONCAT_WS( ' ', creadores.Nombre, creadores.Paterno, creadores.Materno ) " .
                        'END AS CreadoPor, ' .
                        " CONCAT_WS(' ', editores.Nombre, editores.Paterno, editores.Materno) AS ActualizadoPor, " .
                        ' yopuedo_cedulas.id AS idCedula, ' .
                        ' yopuedo_cedulas.ListaParaEnviar as ListaParaEnviarY'
                )
                ->leftjoin(
                    'cat_entidad AS entidadesNacimiento',
                    'entidadesNacimiento.id',
                    'yopuedo_solicitudes.idEntidadNacimiento'
                )
                ->leftjoin(
                    'cat_estado_civil',
                    'cat_estado_civil.id',
                    'yopuedo_solicitudes.idEstadoCivil'
                )
                ->leftjoin(
                    'cat_parentesco_jefe_hogar',
                    'cat_parentesco_jefe_hogar.id',
                    'yopuedo_solicitudes.idParentescoJefeHogar'
                )
                ->leftJoin(
                    'cat_parentesco_tutor',
                    'cat_parentesco_tutor.id',
                    'yopuedo_solicitudes.idParentescoTutor'
                )
                ->leftjoin(
                    'cat_entidad AS entidadesVive',
                    'entidadesVive.id',
                    'yopuedo_solicitudes.idEntidadVive'
                )
                ->join(
                    'users AS creadores',
                    'creadores.id',
                    'yopuedo_solicitudes.idUsuarioCreo'
                )
                ->leftJoin(
                    'users AS editores',
                    'editores.id',
                    'yopuedo_solicitudes.idUsuarioActualizo'
                )
                ->leftJoin(
                    DB::raw($tableCedulas),
                    'yopuedo_cedulas.idSolicitud',
                    'yopuedo_solicitudes.id'
                )
                ->leftJoin(
                    'et_cat_municipio as m',
                    'm.Nombre',
                    'yopuedo_solicitudes.MunicipioVive'
                )
                ->leftJoin(
                    'users_aplicativo_web as ap',
                    'ap.UserName',
                    'yopuedo_solicitudes.UsuarioAplicativo'
                )
                ->whereNull('yopuedo_solicitudes.FechaElimino');

            $filterQuery = '';
            $municipioRegion = [];
            $mun = [];
            $usersNames = [];
            $newFilter = [];
            $idsUsers = '';
            $usersApp = '';

            if (isset($params['filtered']) && count($params['filtered']) > 0) {
                $filtersCedulas = ['.id', '.MunicipioVive'];

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

                foreach ($newFilter as $filtro) {
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

                    if (in_array($id, $filtersCedulas)) {
                        $id = 'yopuedo_solicitudes' . $id;
                    } else {
                        $id = 'yopuedo_solicitudes' . $id;
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
            }

            $page = $params['page'];
            $pageSize = $params['pageSize'];

            $startIndex = $page * $pageSize;

            $total = $solicitudes->count();
            $solicitudes = $solicitudes
                ->offset($startIndex)
                ->take($pageSize)
                ->orderBy('yopuedo_solicitudes.FolioYoPuedo')
                ->get();

            $filtro_usuario = VNegociosFiltros::where('idUser', '=', $user->id)
                ->where('api', '=', 'getYoPuedoVentanilla')
                ->first();

            if ($filtro_usuario) {
                $filtro_usuario->parameters = $parameters_serializado;
                $filtro_usuario->updated_at = time::now();
                $filtro_usuario->update();
            } else {
                $objeto_nuevo = new VNegociosFiltros();
                $objeto_nuevo->api = 'getYoPuedoVentanilla';
                $objeto_nuevo->idUser = $user->id;
                $objeto_nuevo->parameters = $parameters_serializado;
                $objeto_nuevo->save();
            }

            // $solicitudes = $solicitudes
            //     ->orderByDesc('FechaCreo')
            //     ->paginate($params['pageSize']);

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
                    'idEnlace' => $data->idEnlace,
                    'Enlace' => $data->Enlace,
                    'idSolicitudAplicativo' => $data->idSolicitudAplicativo,
                    'Latitud' => $data->Latitud,
                    'Longitud' => $data->Longitud,
                    'EntidadNacimiento' => $data->EntidadNacimiento,
                    'EstadoCivil' => $data->EstadoCivil,
                    'Parentesco' => $data->Parentesco,
                    'EntidadVive' => $data->EntidadVive,
                    'RegionM' => $data->RegionM,
                    'CreadoPor' => $data->CreadoPor,
                    'ActualizadoPor' => $data->ActualizadoPor,
                    'idCedula' => $data->idCedula,
                    'ListaParaEnviarY' => $data->ListaParaEnviarY,
                    'idGrupo' => $data->idGrupo,
                    'idMunicipioGrupo' => $data->idMunicipioGrupo,
                    'idEstatusGrupo' => $data->idEstatusGrupo,
                    'FolioYoPuedo' => $data->FolioYoPuedo,
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
            $params['idUsuarioCreo'] = $user->id;
            $params['FechaCreo'] = date('Y-m-d H:i:s');
            $params['idEstatus'] = 1;
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

            if (isset($params['Folio'])) {
                $folioRegistrado = DB::table('yopuedo_solicitudes')
                    ->where(['Folio' => $params['Folio']])
                    ->whereRaw('FechaElimino IS NULL')
                    ->first();
                if ($folioRegistrado != null) {
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
                    ];
                    return response()->json($response, 200);
                }
            }

            unset($params['Files']);
            unset($params['ArchivosClasificacion']);

            $id = DB::table('yopuedo_solicitudes')->insertGetId($params);

            $response = [
                'success' => true,
                'results' => true,
                'message' => 'Solicitud creada con éxito',
                'data' => $id,
            ];

            return response()->json($response, 200);
        } catch (Throwable $errors) {
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
            $params['idUsuarioActualizo'] = $user->id;
            $params['FechaActualizo'] = date('Y-m-d H:i:s');

            if (!isset($params['idEstatus'])) {
                $params['idEstatus'] = 1;
            }

            unset($params['id']);
            unset($params['Files']);
            unset($params['ArchivosClasificacion']);
            unset($params['OldFiles']);
            unset($params['OldClasificacion']);
            unset($params['NewFiles']);
            unset($params['NewClasificacion']);

            if (!isset($params['Folio'])) {
                $program = 4;
            }

            $tableCedulas =
                '(SELECT * FROM yopuedo_cedulas WHERE FechaElimino IS NULL) AS yopuedo_cedulas';

            $solicitud = DB::table('yopuedo_solicitudes')
                ->select(
                    'yopuedo_solicitudes.idEstatus',
                    'yopuedo_cedulas.id AS idCedula',
                    'yopuedo_cedulas.ListaParaEnviar'
                )
                ->leftJoin(
                    DB::raw($tableCedulas),
                    'yopuedo_cedulas.idSolicitud',
                    'yopuedo_solicitudes.id'
                )
                ->where('yopuedo_solicitudes.id', $id)
                ->first();
            if ($solicitud->idEstatus != 1 || isset($solicitud->idCedula)) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' =>
                        'La solicitud no se puede editar, tiene una cédula activa o ya fue aceptada',
                ];
                return response()->json($response, 200);
            }

            DB::table('yopuedo_solicitudes')
                ->where('id', $id)
                ->update($params);

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
            }

            $tableCedulas =
                '(SELECT * FROM yopuedo_cedulas WHERE FechaElimino IS NULL) AS yopuedo_cedulas';

            $solicitud = DB::table('yopuedo_solicitudes')
                ->select(
                    'yopuedo_solicitudes.idEstatus',
                    'yopuedo_cedulas.id AS idCedula',
                    'yopuedo_cedulas.ListaParaEnviar'
                )
                ->leftJoin(
                    DB::raw($tableCedulas),
                    'yopuedo_cedulas.idSolicitud',
                    'yopuedo_solicitudes.id'
                )
                ->where('yopuedo_solicitudes.id', $params['id'])
                ->first();

            if ($solicitud->idEstatus != 1 || isset($solicitud->idCedula)) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' =>
                        'La solicitud no se puede eliminar, tiene una cédula activa o ya fue aceptada',
                ];
                return response()->json($response, 200);
            }
            $user = auth()->user();

            DB::table('yopuedo_solicitudes')
                ->where('id', $params['id'])
                ->update([
                    'FechaElimino' => date('Y-m-d H:i:s'),
                    'idUsuarioElimino' => $user->id,
                ]);

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
                ->select('id AS value', 'Entidad AS label')
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
            $params['idEstatus'] = 1;
            $params['idUsuarioCreo'] = $user->id;
            $params['FechaCreo'] = date('Y-m-d H:i:s');
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
            unset($params['Boiler']);

            DB::beginTransaction();

            $id = DB::table('yopuedo_cedulas')->insertGetId($params);

            $this->updateSolicitudFromCedula($params, $user);

            if (count($prestaciones) > 0) {
                $formatedPrestaciones = [];
                foreach ($prestaciones as $prestacion) {
                    $formatedPrestaciones[] = [
                        'idCedula' => $id,
                        'idPrestacion' => $prestacion,
                    ];
                }
                DB::table('yopuedo_prestaciones')->insert(
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
                DB::table('yopuedo_enfermedades')->insert(
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
                DB::table('yopuedo_atenciones_medicas')->insert(
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
                'data' => [],
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
            $cedula = DB::table('yopuedo_cedulas')
                ->selectRaw('yopuedo_cedulas.*')
                ->where('yopuedo_cedulas.id', $id)
                ->whereNull('yopuedo_cedulas.FechaElimino')
                ->first();

            $prestaciones = DB::table('yopuedo_prestaciones')
                ->select('idPrestacion')
                ->where('idCedula', $id)
                ->get();

            $enfermedades = DB::table('yopuedo_enfermedades')
                ->select('idEnfermedad')
                ->where('idCedula', $id)
                ->get();

            $atencionesMedicas = DB::table('yopuedo_atenciones_medicas')
                ->select('idAtencionMedica')
                ->where('idCedula', $id)
                ->get();

            $archivos = DB::table('yopuedo_cedula_archivos')
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

            $archivos2 = DB::table('yopuedo_cedula_archivos')
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
                $o->ruta = Storage::disk('subidos')->url($o->NombreSistema);
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
            $archivos2 = DB::table('yopuedo_cedula_archivos')
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

    function getFilesByIdSolicitud(Request $request, $id)
    {
        try {
            $v = Validator::make($request->all(), [
                'id' => 'required',
            ]);
            // if ($v->fails()) {
            //     $response = [
            //         'success' => true,
            //         'results' => false,
            //         'errors' => $v->errors(),
            //     ];
            //     return response()->json($response, 200);
            // }
            // $params = $request->all();
            // $idSolicitud = $params['id'];
            $cedula = DB::table('yopuedo_cedulas')
                ->select('id')
                ->where('idSolicitud', $id)
                ->whereRaw('FechaElimino IS NULL')
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

            $archivos2 = DB::table('yopuedo_cedula_archivos')
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
            unset($params['id']);

            $cedula = DB::table('yopuedo_cedulas')
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
            $params['FechaActualizo'] = date('Y-m-d H:i:s');
            $params['Correo'] =
                isset($params['Correo']) && $params['Correo'] != ''
                    ? $params['Correo']
                    : '';
            if (!isset($params['idEstatus'])) {
                $params['idEstatus'] = 1;
            }

            unset($params['Prestaciones']);
            unset($params['Enfermedades']);
            unset($params['AtencionesMedicas']);
            unset($params['OldFiles']);
            unset($params['OldClasificacion']);
            unset($params['NewFiles']);
            unset($params['NewClasificacion']);
            unset($params['idCedula']);
            unset($params['Boiler']);

            DB::table('yopuedo_cedulas')
                ->where('id', $id)
                ->update($params);

            $this->updateSolicitudFromCedula($params, $user);

            DB::table('yopuedo_prestaciones')
                ->where('idCedula', $id)
                ->delete();
            $formatedPrestaciones = [];
            foreach ($prestaciones as $prestacion) {
                array_push($formatedPrestaciones, [
                    'idCedula' => $id,
                    'idPrestacion' => $prestacion,
                ]);
            }
            if (count($formatedPrestaciones) > 0) {
                DB::table('yopuedo_prestaciones')->insert(
                    $formatedPrestaciones
                );
            }

            DB::table('yopuedo_enfermedades')
                ->where('idCedula', $id)
                ->delete();
            $formatedEnfermedades = [];
            foreach ($enfermedades as $enfermedad) {
                array_push($formatedEnfermedades, [
                    'idCedula' => $id,
                    'idEnfermedad' => $enfermedad,
                ]);
            }
            if (count($formatedEnfermedades) > 0) {
                DB::table('yopuedo_enfermedades')->insert(
                    $formatedEnfermedades
                );
            }
            DB::table('yopuedo_atenciones_medicas')
                ->where('idCedula', $id)
                ->delete();
            $formatedAtencionesMedicas = [];
            foreach ($atencionesMedicas as $atencion) {
                array_push($formatedAtencionesMedicas, [
                    'idCedula' => $id,
                    'idAtencionMedica' => $atencion,
                ]);
            }

            if (count($formatedAtencionesMedicas) > 0) {
                DB::table('yopuedo_atenciones_medicas')->insert(
                    $formatedAtencionesMedicas
                );
            }

            $oldFiles = DB::table('yopuedo_cedula_archivos')
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
                DB::table('yopuedo_cedula_archivos')
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
            $oldFiles = DB::table('yopuedo_cedula_archivos')
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
                DB::table('yopuedo_cedula_archivos')
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

            $cedula = DB::table('yopuedo_cedulas')
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

            DB::table('yopuedo_prestaciones')
                ->where('idCedula', $id)
                ->delete();

            DB::table('yopuedo_enfermedades')
                ->where('idCedula', $id)
                ->delete();

            DB::table('yopuedo_atenciones_medicas')
                ->where('idCedula', $id)
                ->delete();

            DB::table('yopuedo_cedula_archivos')
                ->where('idCedula', $id)
                ->update([
                    'idUsuarioElimino' => $user->id,
                    'FechaElimino' => date('Y-m-d H:i:s'),
                ]);

            DB::table('yopuedo_cedulas')
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

    private function updateSolicitudFromCedula($cedula, $user)
    {
        $params = [];
        if (isset($cedula['FechaSolicitud'])) {
            $params = array_merge($params, [
                'FechaSolicitud' => $cedula['FechaSolicitud'],
            ]);
        }
        if (isset($cedula['FolioTarjetaImpulso'])) {
            $params = $params = array_merge($params, [
                'FolioTarjetaImpulso' => $cedula['FolioTarjetaImpulso'],
            ]);
        }
        if (isset($cedula['Folio'])) {
            $params = $params = array_merge($params, [
                'Folio' => $cedula['Folio'],
            ]);
        }
        if (isset($cedula['Nombre'])) {
            $params = $params = array_merge($params, [
                'Nombre' => $cedula['Nombre'],
            ]);
        }
        if (isset($cedula['Paterno'])) {
            $params = $params = array_merge($params, [
                'Paterno' => $cedula['Paterno'],
            ]);
        }
        if (isset($cedula['Materno'])) {
            $params = $params = array_merge($params, [
                'Materno' => $cedula['Materno'],
            ]);
        }
        if (isset($cedula['FechaNacimiento'])) {
            $params = $params = array_merge($params, [
                'FechaNacimiento' => $cedula['FechaNacimiento'],
            ]);
        }
        if (isset($cedula['Edad'])) {
            $params = $params = array_merge($params, [
                'Edad' => $cedula['Edad'],
            ]);
        }
        if (isset($cedula['Sexo'])) {
            $params = $params = array_merge($params, [
                'Sexo' => $cedula['Sexo'],
            ]);
        }
        if (isset($cedula['idEntidadNacimiento'])) {
            $params = $params = array_merge($params, [
                'idEntidadNacimiento' => $cedula['idEntidadNacimiento'],
            ]);
        }
        if (isset($cedula['CURP'])) {
            $params = $params = array_merge($params, [
                'CURP' => $cedula['CURP'],
            ]);
        }

        if (isset($cedula['RFC'])) {
            $params = $params = array_merge($params, ['RFC' => $cedula['RFC']]);
        }
        if (isset($cedula['idEstadoCivil'])) {
            $params = $params = array_merge($params, [
                'idEstadoCivil' => $cedula['idEstadoCivil'],
            ]);
        }
        if (isset($cedula['idParentescoJefeHogar'])) {
            $params = $params = array_merge($params, [
                'idParentescoJefeHogar' => $cedula['idParentescoJefeHogar'],
            ]);
        }
        if (isset($cedula['NumHijos'])) {
            $params = $params = array_merge($params, [
                'NumHijos' => $cedula['NumHijos'],
            ]);
        }
        if (isset($cedula['NumHijas'])) {
            $params = $params = array_merge($params, [
                'NumHijas' => $cedula['NumHijas'],
            ]);
        }
        if (isset($cedula['ComunidadIndigena'])) {
            $params = $params = array_merge($params, [
                'ComunidadIndigena' => $cedula['ComunidadIndigena'],
            ]);
        }
        if (isset($cedula['Dialecto'])) {
            $params = $params = array_merge($params, [
                'Dialecto' => $cedula['Dialecto'],
            ]);
        }
        if (isset($cedula['Afromexicano'])) {
            $params = $params = array_merge($params, [
                'Afromexicano' => $cedula['Afromexicano'],
            ]);
        }
        if (isset($cedula['idSituacionActual'])) {
            $params = $params = array_merge($params, [
                'idSituacionActual' => $cedula['idSituacionActual'],
            ]);
        }
        if (isset($cedula['TarjetaImpulso'])) {
            $params = $params = array_merge($params, [
                'TarjetaImpulso' => $cedula['TarjetaImpulso'],
            ]);
        }
        if (isset($cedula['ContactoTarjetaImpulso'])) {
            $params = $params = array_merge($params, [
                'ContactoTarjetaImpulso' => $cedula['ContactoTarjetaImpulso'],
            ]);
        }
        if (isset($cedula['Celular'])) {
            $params = $params = array_merge($params, [
                'Celular' => $cedula['Celular'],
            ]);
        }
        if (isset($cedula['Telefono'])) {
            $params = $params = array_merge($params, [
                'Telefono' => $cedula['Telefono'],
            ]);
        }
        if (isset($cedula['TelRecados'])) {
            $params = $params = array_merge($params, [
                'TelRecados' => $cedula['TelRecados'],
            ]);
        }
        if (isset($cedula['Correo'])) {
            $params = $params = array_merge($params, [
                'Correo' => $cedula['Correo'],
            ]);
        }
        if (isset($cedula['idParentescoTutor'])) {
            $params = $params = array_merge($params, [
                'idParentescoTutor' => $cedula['idParentescoTutor'],
            ]);
        }
        if (isset($cedula['NombreTutor'])) {
            $params = $params = array_merge($params, [
                'NombreTutor' => $cedula['NombreTutor'],
            ]);
        }
        if (isset($cedula['PaternoTutor'])) {
            $params = $params = array_merge($params, [
                'PaternoTutor' => $cedula['PaternoTutor'],
            ]);
        }
        if (isset($cedula['MaternoTutor'])) {
            $params = $params = array_merge($params, [
                'MaternoTutor' => $cedula['MaternoTutor'],
            ]);
        }
        if (isset($cedula['FechaNacimientoTutor'])) {
            $params = $params = array_merge($params, [
                'FechaNacimientoTutor' => $cedula['FechaNacimientoTutor'],
            ]);
        }
        if (isset($cedula['EdadTutor'])) {
            $params = $params = array_merge($params, [
                'EdadTutor' => $cedula['EdadTutor'],
            ]);
        }
        if (isset($cedula['SexoTutor'])) {
            $params = $params = array_merge($params, [
                'SexoTutor' => $cedula['SexoTutor'],
            ]);
        }
        if (isset($cedula['idEntidadNacimientoTutor'])) {
            $params = $params = array_merge($params, [
                'idEntidadNacimientoTutor' =>
                    $cedula['idEntidadNacimientoTutor'],
            ]);
        }
        if (isset($cedula['CURPTutor'])) {
            $params = $params = array_merge($params, [
                'CURPTutor' => $cedula['CURPTutor'],
            ]);
        }
        if (isset($cedula['TelefonoTutor'])) {
            $params = $params = array_merge($params, [
                'TelefonoTutor' => $cedula['TelefonoTutor'],
            ]);
        }
        if (isset($cedula['CorreoTutor'])) {
            $params = $params = array_merge($params, [
                'CorreoTutor' => $cedula['CorreoTutor'],
            ]);
        }
        if (isset($cedula['idEntidadVive'])) {
            $params = $params = array_merge($params, [
                'idEntidadVive' => $cedula['idEntidadVive'],
            ]);
        }
        if (isset($cedula['MunicipioVive'])) {
            $params = $params = array_merge($params, [
                'MunicipioVive' => $cedula['MunicipioVive'],
            ]);
        }
        if (isset($cedula['LocalidadVive'])) {
            $params = $params = array_merge($params, [
                'LocalidadVive' => $cedula['LocalidadVive'],
            ]);
        }
        if (isset($cedula['CPVive'])) {
            $params = $params = array_merge($params, [
                'CPVive' => $cedula['CPVive'],
            ]);
        }
        if (isset($cedula['ColoniaVive'])) {
            $params = $params = array_merge($params, [
                'ColoniaVive' => $cedula['ColoniaVive'],
            ]);
        }
        if (isset($cedula['CalleVive'])) {
            $params = $params = array_merge($params, [
                'CalleVive' => $cedula['CalleVive'],
            ]);
        }
        if (isset($cedula['NoExtVive'])) {
            $params = $params = array_merge($params, [
                'NoExtVive' => $cedula['NoExtVive'],
            ]);
        }
        if (isset($cedula['NoIntVive'])) {
            $params = $params = array_merge($params, [
                'NoIntVive' => $cedula['NoIntVive'],
            ]);
        }
        if (isset($cedula['Referencias'])) {
            $params = $params = array_merge($params, [
                'Referencias' => $cedula['Referencias'],
            ]);
        }
        if (isset($cedula['Latitud'])) {
            $params = $params = array_merge($params, [
                'Latitud' => $cedula['Latitud'],
            ]);
        }
        if (isset($cedula['Longitud'])) {
            $params = $params = array_merge($params, [
                'Longitud' => $cedula['Longitud'],
            ]);
        }
        if (isset($cedula['idGrupo'])) {
            $params = $params = array_merge($params, [
                'idGrupo' => $cedula['idGrupo'],
            ]);
        }
        if (isset($cedula['idMunicipioGrupo'])) {
            $params = $params = array_merge($params, [
                'idMunicipioGrupo' => $cedula['idMunicipioGrupo'],
            ]);
        }
        if (isset($cedula['idEstatusGrupo'])) {
            $params = $params = array_merge($params, [
                'idEstatusGrupo' => $cedula['idEstatusGrupo'],
            ]);
        }
        if (isset($cedula['idEnlace'])) {
            $params = $params = array_merge($params, [
                'idEnlace' => $cedula['idEnlace'],
            ]);
        }
        if (isset($cedula['Enlace'])) {
            $params = $params = array_merge($params, [
                'Enlace' => $cedula['Enlace'],
            ]);
        }
        if (isset($cedula['idUsuarioActualizo'])) {
            $params = $params = array_merge($params, [
                'idUsuarioActualizo' => $cedula['idUsuarioActualizo'],
            ]);
        }
        if (isset($cedula['FechaActualizo'])) {
            $params = $params = array_merge($params, [
                'FechaActualizo' => $cedula['FechaActualizo'],
            ]);
        }

        DB::table('yopuedo_solicitudes')
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

            Storage::disk('subidos')->put(
                $uniqueName,
                File::get($file->getRealPath()),
                'public'
            );

            DB::table('yopuedo_cedula_archivos')->insert($fileObject);
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
                    DB::table('yopuedo_cedula_archivos')
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

    public function enviarIGTO(Request $request)
    {
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
            $id = $params['id'];
            $folio = DB::table('yopuedo_cedulas')
                ->select('Folio')
                ->where('id', $id)
                ->get()
                ->first();
            $user = auth()->user();
        } catch (Exception $e) {
            $response = [
                'success' => false,
                'results' => false,
                'message' => 'Ocurrio un error al recuperar la cedula',
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
                        'message' => 'El Folio de la cedula no es válido',
                    ];
                    return response()->json($response, 200);
                }
            } else {
                $response = [
                    'success' => true,
                    'results' => false,
                    'message' =>
                        'La cedula no cuenta con Folio, revise su información',
                ];
                return response()->json($response, 200);
            }
        } catch (Exception $e) {
            $response = [
                'success' => false,
                'results' => false,
                'message' =>
                    'Ocurrio un error al validar el Folio de la cedula',
            ];
            return response()->json($response, 200);
        }

        $cedula = DB::table('yopuedo_cedulas as cedulas')
            ->selectRaw(
                "
                        cedulas.*, cat_estado_civil.EstadoCivil, 
                        cat_parentesco_jefe_hogar.Parentesco AS ParentescoJefeHogar,
                        cat_situacion_actual.Situacion AS SituacionActual, 
                        entidadNacimiento.Entidad AS EntidadNacimiento, 
                        entidadVive.Entidad AS EntidadVive,
                        cat_parentesco_tutor.Parentesco AS ParentescoTutor,
                        gradoEducacion.Grado AS GradoEducacion,
                        nivelesEducacion.Nivel AS NivelEducacion,
                        actividades.Actividad,
                        viviendas.Tipo AS TipoVivienda,
                        pisos.Piso,
                        muros.Muro,
                        techos.Techo,
                        aguas.Agua,
                        drenajes.Drenaje,
                        luz.Luz,
                        combustibles.Combustible
                    "
            )
            ->leftjoin(
                'cat_estado_civil',
                'cat_estado_civil.id',
                'cedulas.idEstadoCivil'
            )
            ->leftjoin(
                'cat_parentesco_jefe_hogar',
                'cat_parentesco_jefe_hogar.id',
                'cedulas.idParentescoJefeHogar'
            )
            ->leftjoin(
                'cat_situacion_actual',
                'cat_situacion_actual.id',
                'cedulas.idSituacionActual'
            )
            ->leftJoin(
                'cat_parentesco_tutor',
                'cat_parentesco_tutor.id',
                'cedulas.idParentescoTutor'
            )
            ->leftjoin(
                'cat_entidad AS entidadNacimiento',
                'entidadNacimiento.id',
                'cedulas.idEntidadNacimiento'
            )
            ->leftjoin(
                'cat_entidad AS entidadVive',
                'entidadVive.id',
                'cedulas.idEntidadVive'
            )
            ->leftjoin(
                'cat_grados_educacion AS gradoEducacion',
                'gradoEducacion.id',
                'cedulas.idGradoEscuela'
            )
            ->leftjoin(
                'cat_niveles_educacion AS nivelesEducacion',
                'nivelesEducacion.id',
                'cedulas.idNivelEscuela'
            )
            ->leftjoin(
                'cat_actividades AS actividades',
                'actividades.id',
                'cedulas.idActividades'
            )
            ->leftjoin(
                'cat_tipos_viviendas AS viviendas',
                'viviendas.id',
                'cedulas.idTipoVivienda'
            )
            ->leftjoin(
                'cat_tipos_pisos AS pisos',
                'pisos.id',
                'cedulas.idTipoPiso'
            )
            ->leftjoin(
                'cat_tipos_muros AS muros',
                'muros.id',
                'cedulas.idTipoParedes'
            )
            ->leftjoin(
                'cat_tipos_techos AS techos',
                'techos.id',
                'cedulas.idTipoTecho'
            )
            ->leftjoin(
                'cat_tipos_agua AS aguas',
                'aguas.id',
                'cedulas.idTipoAgua'
            )
            ->leftjoin(
                'cat_tipos_drenajes AS drenajes',
                'drenajes.id',
                'cedulas.idTipoDrenaje'
            )
            ->leftjoin('cat_tipos_luz AS luz', 'luz.id', 'cedulas.idTipoLuz')
            ->leftjoin(
                'cat_tipos_combustibles AS combustibles',
                'combustibles.id',
                'cedulas.idTipoCombustible'
            )
            ->where('cedulas.id', $id)
            ->first();

        if (!isset($cedula->MunicipioVive) || !isset($cedula->LocalidadVive)) {
            $response = [
                'success' => true,
                'results' => false,
                'message' =>
                    'La solicitud no cuenta con municipio o localidad, Revise su información',
            ];
            return response()->json($response, 200);
        }

        if (!isset($cedula->Edad)) {
            $response = [
                'success' => true,
                'results' => false,
                'message' =>
                    'La persona no cuenta con edad registrada, Revise su información',
            ];
            return response()->json($response, 200);
        }

        if ($cedula->Edad > 17) {
            $seguros = DB::table('yopuedo_atenciones_medicas')
                ->where('idCedula', $id)
                ->get();
            $seguros = array_map(function ($o) {
                return $o->idAtencionMedica;
            }, $seguros->toArray());

            $enfermedades = DB::table('yopuedo_enfermedades')
                ->where('idCedula', $id)
                ->get();
            $enfermedades = array_map(function ($o) {
                return $o->idEnfermedad;
            }, $enfermedades->toArray());

            $prestaciones = DB::table('yopuedo_prestaciones')
                ->where('idCedula', $id)
                ->get();
            $prestaciones = array_map(function ($o) {
                return $o->idPrestacion;
            }, $prestaciones->toArray());
        }

        $files = DB::table('yopuedo_cedula_archivos')
            ->select(
                'yopuedo_cedula_archivos.*',
                'cedula_archivos_clasificacion.Clasificacion'
            )
            ->join(
                'cedula_archivos_clasificacion',
                'cedula_archivos_clasificacion.id',
                'yopuedo_cedula_archivos.idClasificacion'
            )
            ->where('idCedula', $id)
            ->whereIn('cedula_archivos_clasificacion.id', [4])
            ->whereRaw('yopuedo_cedula_archivos.FechaElimino IS NULL')
            ->get();

        if ($files->count() != 1) {
            $response = [
                'success' => true,
                'results' => false,
                'errors' =>
                    'Revise los documentos de CURP Solo debe agregar un archivo por clasificación.',
                'message' =>
                    'Revise los documentos de CURP Solo debe agregar un archivo por clasificación.',
            ];
            return response()->json($response, 200);
        } else {
            $clasificaciones = [];
            foreach ($files as $file) {
                $clasificaciones[] = $file->idClasificacion;
            }
            if (!in_array(4, $clasificaciones)) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' => 'Falta la CURP',
                    'message' => 'Falta la CURP',
                ];
                return response()->json($response, 200);
            }
        }

        $filesAcuse = DB::table('yopuedo_cedula_archivos')
            ->select(
                'yopuedo_cedula_archivos.*',
                'cedula_archivos_clasificacion.Clasificacion'
            )
            ->join(
                'cedula_archivos_clasificacion',
                'cedula_archivos_clasificacion.id',
                'yopuedo_cedula_archivos.idClasificacion'
            )
            ->where('idCedula', $id)
            ->whereIn('cedula_archivos_clasificacion.id', [5])
            ->whereRaw('yopuedo_cedula_archivos.FechaElimino IS NULL')
            ->get();

        if ($filesAcuse->count() != 1) {
            $response = [
                'success' => true,
                'results' => false,
                'errors' =>
                    'Revise el documento de Formato de Firma y Acuse, Solo debe agregar un archivo por clasificación.',
                'message' =>
                    'Revise el documento de Formato de Firma y Acuse, Solo debe agregar un archivo por clasificación.',
            ];
            return response()->json($response, 200);
        }

        $solicitudJson = $this->formatSolicitudIGTOJson($cedula);
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

        if ($cedula->Edad > 17) {
            $catalogs = [
                'seguros' => $seguros,
                'enfermedades' => $enfermedades,
                'prestaciones' => $prestaciones,
            ];
        }

        if ($cedula->Edad > 17) {
            $cedulaJson = $this->formatCedulaIGTOJson($cedula, $catalogs);
        }

        $formatedFiles = $this->formatArchivos($files, 1);
        $infoFiles = $this->getInfoArchivos($files, 1);
        $formatedFilesAcuse = $this->formatArchivos($filesAcuse, 1);
        $infoFilesAcuse = $this->getInfoArchivos($filesAcuse, 1);

        $infoFiles = array_merge($infoFiles, $infoFilesAcuse);

        $formatedFiles = array_merge($formatedFiles, $formatedFilesAcuse);

        if ($cedula->Edad && $cedula->Edad < 18) {
            $cvep = 'Q0256-01';
            $cve = '0256-01-01';
            $nombreApoyo =
                'Capacitación en cuatro módulos para menores de edad';
        } else {
            $cvep = 'Q0256-02';
            $cve = '0256-02-02';
            $nombreApoyo =
                'Capacitación en cuatro módulos para mayores de edad';
        }

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
                    'q' => 'Q0256',
                    'nombre' => 'YO PUEDO GUANAJUATO PUEDE',
                    'modalidad' => [
                        'nombre' => 'PROCESO FORMATIVO',
                        'clave' => $cvep,
                    ],
                    'tipoApoyo' => [
                        'clave' => $cve,
                        'nombre' => $nombreApoyo,
                    ],
                ],
            ],
            JSON_UNESCAPED_UNICODE
        );

        $docs = json_encode(
            [
                'estandar' => $formatedFiles,
                'especifico' => [],
            ],
            JSON_UNESCAPED_UNICODE
        );

        $cUsuario = $this->getCampoUsuario($cedula);

        if ($cedula->idUsuarioCreo == 1312) {
            $authUsuario = $this->getAuthUsuario($cedula->UsuarioAplicativo, 1);
        } else {
            $authUsuario = $this->getAuthUsuario($cedula->idUsuarioCreo, 2);
        }

        $dataCompleted = [
            'solicitud' => $solicitudJson['solicitud'],
            'programa' => $programa,
            'documentos' => $docs,
            'authUsuario' => $authUsuario,
            'campoUsuario' => $cUsuario,
        ];

        if ($cedula->Edad > 17) {
            $dataCedula = ['cedula' => $cedulaJson];
            $dataCompleted = array_merge($dataCompleted, $dataCedula);
        }

        $request2 = new HTTP_Request2();
        $request2->setUrl(
            'https://qa-api-utils-ventanilla-impulso.guanajuato.gob.mx/v1/application/external/cedula/register'
            //'https://api-integracion-ventanilla-impulso.guanajuato.gob.mx/v1/application/external/cedula/register'
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
            $message = json_decode($response->getBody());
            //dd($response->getReasonPhrase());
            if ($response->getStatus() == 200) {
                if ($message->success) {
                    try {
                        DB::table('yopuedo_solicitudes')
                            ->where('id', $cedula->idSolicitud)
                            ->update([
                                'idEstatus' => '8',
                                'ListaParaEnviar' => '2',
                                'idUsuarioEnvio' => $user->id,
                                'FechaEnvio' => date('Y-m-d H:i:s'),
                            ]);

                        DB::table('yopuedo_cedulas')
                            ->where('id', $cedula->id)
                            ->update([
                                'idEstatus' => '8',
                                'ListaParaEnviar' => '2',
                                'idUsuarioEnvio' => $user->id,
                                'FechaEnvio' => date('Y-m-d H:i:s'),
                            ]);

                        return [
                            'success' => true,
                            'results' => true,
                            'message' => 'Enviada Correctamente',
                        ];
                    } catch (Exception $e) {
                        $response2 = [
                            'success' => true,
                            'results' => false,
                            'errors' => $e->errors,
                            'message' =>
                                'La Cedula fue enviada pero hubo un problema al actualizar el estatus',
                        ];
                        return response()->json($response2, 200);
                    }
                } else {
                    $response2 = [
                        'success' => false,
                        'results' => false,
                        'errors' => $e->errors,
                        'message' => 'La Cedula no fue enviada',
                    ];
                    return response()->json($response2, 200);
                }
            } else {
                $response2 = [
                    'success' => true,
                    'results' => false,
                    'errors' => $response->getBody(),
                    'message' =>
                        'Ha ocurrido un error al enviar, consulte al administrador',
                ];
                return response()->json($response2, 200);
            }
        } catch (HTTP_Request2_Exception $e) {
            $response2 = [
                'success' => true,
                'results' => false,
                'errors' => $e . 'error',
                'message' => 'Ha ocurrido un error, consulte al administrador',
            ];
            return response()->json($response2, 200);
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
        $cveLocalidad = DB::table('et_cat_localidad')
            ->select('CveInegi')
            ->where('Nombre', $solicitud->LocalidadVive)
            ->get()
            ->first();

        $json = [
            'solicitud' => json_encode(
                [
                    'tipoSolicitud' => 'Ciudadana',
                    'origen' => 'F',
                    'tutor' => [
                        'respuesta' => $solicitud->NombreTutor ? true : false,
                    ],
                    'datosCurp' => [
                        'folio' => $solicitud->Folio,
                        'curp' => $solicitud->CURP,
                        'entidadNacimiento' => $solicitud->EntidadNacimiento,
                        'fechaNacimientoDate' => date(
                            $solicitud->FechaNacimiento
                        ),
                        'fechaNacimientoTexto' => $solicitud->FechaNacimiento,
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
                            'descripcion' => $solicitud->ParentescoJefeHogar,
                        ],
                        'migrante' => [
                            'respuesta' =>
                                $solicitud->idSituacionActual !== 5 &&
                                $solicitud->idSituacionActual !== null &&
                                $solicitud->idSituacionActual !== 0,
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
                        'numeroExt' => $solicitud->NoExtVive,
                        'numeroInt' => $solicitud->NoIntVive,
                        'entidadFederativa' => $solicitud->EntidadVive,
                        'localidad' => [
                            'nombre' => $solicitud->LocalidadVive
                                ? $solicitud->LocalidadVive
                                : '',
                            'codigo' => $cveLocalidad->CveInegi
                                ? $cveLocalidad->CveInegi
                                : '',
                        ],
                        'municipio' => $solicitud->MunicipioVive,
                        'calle' => $solicitud->CalleVive,
                        'referencias' => $solicitud->Referencias,
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
        // if ($solicitud->TelRecados != null) {
        //     array_push($telefonos, [
        //         'tipo' => 'Teléfono de recados',
        //         'descripcion' =>
        //             $solicitud->TelRecados == 0 ? '' : $solicitud->TelRecados,
        //     ]);
        // }
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

    private function formatCedulaIGTOJson($cedula, $catalogs)
    {
        $periodicidades = DB::table('cat_periodicidad')->get();
        $json = json_encode(
            [
                'solicitudImpulso' => true,
                'cedulaImpulso' => true,
                'datosHogar' => [
                    'numeroHogares' => $cedula->TotalHogares,
                    'integrantesMujer' => $cedula->NumeroMujeresHogar,
                    'integrantesHombre' => $cedula->NumeroHombresHogar,
                    'menores18' => $cedula->PersonasMayoresEdad > 0,
                    'mayores65' => $cedula->PersonasTerceraEdad > 0,
                    'hombreJefeFamilia' => $cedula->PersonaJefaFamilia == 'H',
                ],
                'datosSalud' => [
                    'limitacionMental' => $cedula->DificultadMental == 1,
                    'servicioMedico' => [
                        [
                            'respuesta' => in_array(1, $catalogs['seguros']),
                            'codigo' => 1,
                            'descripcion' => 'Seguro Social IMSS',
                        ],
                        [
                            'respuesta' => in_array(2, $catalogs['seguros']),
                            'codigo' => 2,
                            'descripcion' =>
                                'IMSS facultativo para estudiantes',
                        ],
                        [
                            'respuesta' => in_array(3, $catalogs['seguros']),
                            'codigo' => 3,
                            'descripcion' => 'ISSSTE',
                        ],
                        [
                            'respuesta' => in_array(4, $catalogs['seguros']),
                            'codigo' => 4,
                            'descripcion' => 'ISSSTE Estatal',
                        ],
                        [
                            'respuesta' => in_array(5, $catalogs['seguros']),
                            'codigo' => 5,
                            'descripcion' => 'PEMEX, Defensa o Marina',
                        ],
                        [
                            'respuesta' => in_array(6, $catalogs['seguros']),
                            'codigo' => 6,
                            'descripcion' => 'INSABI (antes Seguro Popular)',
                        ],
                        [
                            'respuesta' => in_array(7, $catalogs['seguros']),
                            'codigo' => 7,
                            'descripcion' => 'Seguro Privado',
                        ],
                        [
                            'respuesta' => in_array(8, $catalogs['seguros']),
                            'codigo' => 8,
                            'descripcion' => 'En otra institución',
                        ],
                        [
                            'respuesta' => in_array(9, $catalogs['seguros']),
                            'codigo' => 9,
                            'descripcion' =>
                                'No tienen derecho a servicios médicos',
                        ],
                    ],
                    'enfermedadCronica' => [
                        [
                            'respuesta' => in_array(
                                1,
                                $catalogs['enfermedades']
                            ),
                            'codigo' => 1,
                            'descripcion' => 'Artritis Reumatoide',
                        ],
                        [
                            'respuesta' => in_array(
                                2,
                                $catalogs['enfermedades']
                            ),
                            'codigo' => 2,
                            'descripcion' => 'Cáncer',
                        ],
                        [
                            'respuesta' => in_array(
                                3,
                                $catalogs['enfermedades']
                            ),
                            'codigo' => 3,
                            'descripcion' => 'Cirrosis Hepática',
                        ],
                        [
                            'respuesta' => in_array(
                                4,
                                $catalogs['enfermedades']
                            ),
                            'codigo' => 4,
                            'descripcion' => 'Insuficiencia Renal',
                        ],
                        [
                            'respuesta' => in_array(
                                5,
                                $catalogs['enfermedades']
                            ),
                            'codigo' => 5,
                            'descripcion' => 'Diabetes Mellitus',
                        ],
                        [
                            'respuesta' => in_array(
                                6,
                                $catalogs['enfermedades']
                            ),
                            'codigo' => 6,
                            'descripcion' => 'Cardiopatías',
                        ],
                        [
                            'respuesta' => in_array(
                                7,
                                $catalogs['enfermedades']
                            ),
                            'codigo' => 7,
                            'descripcion' => 'Enfermedad Pulmonar Crónica',
                        ],
                        [
                            'respuesta' => in_array(
                                8,
                                $catalogs['enfermedades']
                            ),
                            'codigo' => 8,
                            'descripcion' =>
                                'Deficiencia nutricional (Desnutrición)',
                        ],
                        [
                            'respuesta' => in_array(
                                9,
                                $catalogs['enfermedades']
                            ),
                            'codigo' => 9,
                            'descripcion' => 'Hipertensión Arterial',
                        ],
                        [
                            'respuesta' => in_array(
                                10,
                                $catalogs['enfermedades']
                            ),
                            'codigo' => 10,
                            'descripcion' => 'Obesidad',
                        ],
                        [
                            'respuesta' => in_array(
                                11,
                                $catalogs['enfermedades']
                            ),
                            'codigo' => 11,
                            'descripcion' =>
                                'Adicción a la Ingestión de Sustancias (Drogas)',
                        ],
                        [
                            'respuesta' => in_array(
                                12,
                                $catalogs['enfermedades']
                            ),
                            'codigo' => 12,
                            'descripcion' =>
                                'Adicciones de la conducta (Juego, internet)',
                        ],
                        [
                            'respuesta' => in_array(
                                13,
                                $catalogs['enfermedades']
                            ),
                            'codigo' => 13,
                            'descripcion' => 'Depresión',
                        ],
                        [
                            'respuesta' => in_array(
                                14,
                                $catalogs['enfermedades']
                            ),
                            'codigo' => 14,
                            'descripcion' => 'Ansiedad',
                        ],
                        [
                            'respuesta' => in_array(
                                15,
                                $catalogs['enfermedades']
                            ),
                            'codigo' => 15,
                            'descripcion' => 'Trasplante de Órganos',
                        ],
                        [
                            'respuesta' => in_array(
                                16,
                                $catalogs['enfermedades']
                            ),
                            'codigo' => 16,
                            'descripcion' => 'Ninguna',
                        ],
                    ],
                ],
                'datosEducacion' => [
                    'estudiante' => $cedula->AsisteEscuela == 1,
                    'ultimoNivel' => [
                        'codigo' => $cedula->idNivelEscuela,
                        'descripcion' => $cedula->NivelEducacion,
                    ],
                    'grado' => [
                        'codigo' =>
                            $cedula->idGradoEscuela == 7
                                ? 0
                                : $cedula->idGradoEscuela,
                        'descripcion' => $cedula->GradoEducacion,
                    ],
                ],
                'datosIngreso' => [
                    'situacionEmpleo' => [
                        'codigo' => $cedula->idActividades,
                        'descripcion' => $cedula->Actividad,
                    ],
                    'prestacionesTrabajo' => [
                        [
                            'respuesta' => in_array(
                                1,
                                $catalogs['prestaciones']
                            ),
                            'codigo' => 1,
                            'descripcion' =>
                                'Incapacidad en caso de enfermedad, accidente o maternidad',
                        ],
                        [
                            'respuesta' => in_array(
                                2,
                                $catalogs['prestaciones']
                            ),
                            'codigo' => 2,
                            'descripcion' => 'Aguinaldo',
                        ],
                        [
                            'respuesta' => in_array(
                                3,
                                $catalogs['prestaciones']
                            ),
                            'codigo' => 3,
                            'descripcion' => 'Crédito de vivienda',
                        ],
                        [
                            'respuesta' => in_array(
                                4,
                                $catalogs['prestaciones']
                            ),
                            'codigo' => 4,
                            'descripcion' =>
                                'Guarderías y estancias infantiles',
                        ],
                        [
                            'respuesta' => in_array(
                                5,
                                $catalogs['prestaciones']
                            ),
                            'codigo' => 5,
                            'descripcion' => 'SAR o AFORE',
                        ],
                        [
                            'respuesta' => in_array(
                                6,
                                $catalogs['prestaciones']
                            ),
                            'codigo' => 6,
                            'descripcion' => 'Seguro de vida',
                        ],
                        [
                            'respuesta' => in_array(
                                7,
                                $catalogs['prestaciones']
                            ),
                            'codigo' => 7,
                            'descripcion' =>
                                'No tienen prestaciones provenientes de su trabajo',
                        ],
                    ],
                    'totalIngreso' => $cedula->IngresoTotalMesPasado,
                    'totalPension' => $cedula->PensionMensual,
                    'totalRemesa' => $cedula->IngresoOtrosPaises,
                ],
                'datosAlimentacion' => [
                    'pocaVariedadAlimento' =>
                        $cedula->AlimentacionPocoVariada == 1,
                    'comioMenos' => $cedula->ComioMenos == 1,
                    'disminuyoCantidad' => $cedula->DisminucionComida == 1,
                    'tuvoHambreNoComio' => $cedula->NoComio == 1,
                    'durmioConHambre' => $cedula->DurmioHambre == 1,
                    'comioUnaVezoNo' => $cedula->DejoComer == 1,
                ],
                'discapacidad' => [
                    'movilidadInferior' => $cedula->DificultadMovilidad,
                    'visual' => $cedula->DificultadVer,
                    'habla' => $cedula->DificultadHablar,
                    'auditivo' => $cedula->DificultadOir,
                    'valerse' => $cedula->DificultadVestirse,
                    'memoria' => $cedula->DificultadRecordar,
                    'movilidadSuperior' => $cedula->DificultadBrazos,
                ],
                'datosGasto' => [
                    'comida' => [
                        'gasto' => $cedula->GastoAlimentos,
                        'periodo' => [
                            'codigo' =>
                                $cedula->PeriodicidadAlimentos == 0
                                    ? 2
                                    : $cedula->PeriodicidadAlimentos,
                            'descripcion' => $periodicidades
                                ->where(
                                    'id',
                                    $cedula->PeriodicidadAlimentos == 0
                                        ? 2
                                        : $cedula->PeriodicidadAlimentos
                                )
                                ->first()->Periodicidad,
                        ],
                    ],
                    'ropa' => [
                        'gasto' => $cedula->GastoVestido,
                        'periodo' => [
                            'codigo' =>
                                $cedula->PeriodicidadVestido == 0
                                    ? 2
                                    : $cedula->PeriodicidadVestido,
                            'descripcion' => $periodicidades
                                ->where(
                                    'id',
                                    $cedula->PeriodicidadVestido == 0
                                        ? 2
                                        : $cedula->PeriodicidadVestido
                                )
                                ->first()->Periodicidad,
                        ],
                    ],
                    'educacion' => [
                        'gasto' => $cedula->GastoEducacion,
                        'periodo' => [
                            'codigo' =>
                                $cedula->PeriodicidadEducacion == 0
                                    ? 2
                                    : $cedula->PeriodicidadEducacion,
                            'descripcion' => $periodicidades
                                ->where(
                                    'id',
                                    $cedula->PeriodicidadEducacion == 0
                                        ? 2
                                        : $cedula->PeriodicidadEducacion
                                )
                                ->first()->Periodicidad,
                        ],
                    ],
                    'medicina' => [
                        'gasto' => $cedula->GastoMedicinas,
                        'periodo' => [
                            'codigo' =>
                                $cedula->PeriodicidadMedicinas == 0
                                    ? 2
                                    : $cedula->PeriodicidadMedicinas,
                            'descripcion' => $periodicidades
                                ->where(
                                    'id',
                                    $cedula->PeriodicidadMedicinas == 0
                                        ? 2
                                        : $cedula->PeriodicidadMedicinas
                                )
                                ->first()->Periodicidad,
                        ],
                    ],
                    'consultas' => [
                        'gasto' => $cedula->GastosConsultas,
                        'periodo' => [
                            'codigo' =>
                                $cedula->PeriodicidadConsultas == 0
                                    ? 2
                                    : $cedula->PeriodicidadConsultas,
                            'descripcion' => $periodicidades
                                ->where(
                                    'id',
                                    $cedula->PeriodicidadConsultas == 0
                                        ? 2
                                        : $cedula->PeriodicidadConsultas
                                )
                                ->first()->Periodicidad,
                        ],
                    ],
                    'combustible' => [
                        'gasto' => $cedula->GastosCombustibles,
                        'periodo' => [
                            'codigo' =>
                                $cedula->PeriodicidadCombustibles == 0
                                    ? 2
                                    : $cedula->PeriodicidadCombustibles,
                            'descripcion' => $periodicidades
                                ->where(
                                    'id',
                                    $cedula->PeriodicidadCombustibles == 0
                                        ? 2
                                        : $cedula->PeriodicidadCombustibles
                                )
                                ->first()->Periodicidad,
                        ],
                    ],
                    'serviciosBasicos' => [
                        'gasto' => $cedula->GastosServiciosBasicos,
                        'periodo' => [
                            'codigo' => $cedula->PeriodicidadServiciosBasicos,
                            'descripcion' => $periodicidades
                                ->where(
                                    'id',
                                    $cedula->PeriodicidadServiciosBasicos == 0
                                        ? 2
                                        : $cedula->PeriodicidadServiciosBasicos
                                )
                                ->first()->Periodicidad,
                        ],
                    ],
                    'recreacion' => [
                        'gasto' => $cedula->GastosServiciosRecreacion,
                        'periodo' => [
                            'codigo' =>
                                $cedula->PeriodicidadServiciosRecreacion == 0
                                    ? 2
                                    : $cedula->PeriodicidadServiciosRecreacion,
                            'descripcion' => $periodicidades
                                ->where(
                                    'id',
                                    $cedula->PeriodicidadServiciosRecreacion ==
                                    0
                                        ? 2
                                        : $cedula->PeriodicidadServiciosRecreacion
                                )
                                ->first()->Periodicidad,
                        ],
                    ],
                ],
                'datosVivienda' => [
                    'estatusVivienda' => [
                        'codigo' => $cedula->idTipoVivienda,
                        'descripcion' => $cedula->TipoVivienda,
                    ],
                    'materialPiso' => [
                        'codigo' =>
                            $cedula->idTipoPiso == 0 ? 1 : $cedula->idTipoPiso,
                        'descripcion' =>
                            $cedula->Piso == null
                                ? 'Cemento o firme'
                                : $cedula->Piso,
                    ],
                    'materialPared' => [
                        'codigo' => $cedula->idTipoParedes,
                        'descripcion' => $cedula->Muro,
                    ],
                    'materialTecho' => [
                        'codigo' => $cedula->idTipoTecho,
                        'descripcion' => $cedula->Techo,
                    ],
                    'fuenteAgua' => [
                        'codigo' => $cedula->idTipoAgua,
                        'descripcion' => $cedula->Agua,
                    ],
                    'drenaje' => [
                        'codigo' => $cedula->idTipoDrenaje,
                        'descripcion' => $cedula->Drenaje,
                    ],
                    'fuenteLuzElectrica' => [
                        'codigo' => $cedula->idTipoLuz,
                        'descripcion' => $cedula->Luz,
                    ],
                    'combustibleCocina' => [
                        'codigo' => $cedula->idTipoCombustible,
                        'descripcion' => $cedula->Combustible,
                    ],
                    'numeroCuartos' => $cedula->CuartosHogar,
                    'numeroPersonaHabitantes' => $cedula->PersonasHogar,
                ],
                'datosEnseres' => [
                    'refrigerador' => $cedula->Refrigerador == 1,
                    'lavadora' => $cedula->Lavadora == 1,
                    'computadora' => $cedula->Computadora == 1,
                    'estufa' => $cedula->Estufa == 1,
                    'boiler' => $cedula->Calentador == 1,
                    'calentadorSolar' => $cedula->CalentadorSolar == 1,
                    'tv' => $cedula->Television == 1,
                    'internet' => $cedula->Internet == 1,
                    'celular' => $cedula->TieneTelefono == 1,
                    'tinaco' => $cedula->Tinaco == 1,
                ],
                'percepcionSeguridad' => $cedula->ColoniaSegura == 1,
            ],
            JSON_UNESCAPED_UNICODE
        );

        return $json;
    }

    private function formatCedulaIGTOJsonMenor($cedula, $catalogs)
    {
        $json = json_encode(
            [
                'solicitudImpulso' => true,
                'cedulaImpulso' => true,
                'datosHogar' => [
                    'numeroHogares' => null,
                    'integrantesMujer' => null,
                    'integrantesHombre' => null,
                    'menores18' => null,
                    'mayores65' => null,
                    'hombreJefeFamilia' => null,
                ],
                'datosSalud' => [
                    'limitacionMental' => null,
                    'servicioMedico' => [
                        [
                            'respuesta' => null,
                            'codigo' => 1,
                            'descripcion' => 'Seguro Social IMSS',
                        ],
                        [
                            'respuesta' => null,
                            'codigo' => 2,
                            'descripcion' =>
                                'IMSS facultativo para estudiantes',
                        ],
                        [
                            'respuesta' => null,
                            'codigo' => 3,
                            'descripcion' => 'ISSSTE',
                        ],
                        [
                            'respuesta' => null,
                            'codigo' => 4,
                            'descripcion' => 'ISSSTE Estatal',
                        ],
                        [
                            'respuesta' => null,
                            'codigo' => 5,
                            'descripcion' => 'PEMEX, Defensa o Marina',
                        ],
                        [
                            'respuesta' => null,
                            'codigo' => 6,
                            'descripcion' => 'INSABI (antes Seguro Popular)',
                        ],
                        [
                            'respuesta' => null,
                            'codigo' => 7,
                            'descripcion' => 'Seguro Privado',
                        ],
                        [
                            'respuesta' => null,
                            'codigo' => 8,
                            'descripcion' => 'En otra institución',
                        ],
                        [
                            'respuesta' => null,
                            'codigo' => 9,
                            'descripcion' =>
                                'No tienen derecho a servicios médicos',
                        ],
                    ],
                    'enfermedadCronica' => [
                        [
                            'respuesta' => null,
                            'codigo' => 1,
                            'descripcion' => 'Artritis Reumatoide',
                        ],
                        [
                            'respuesta' => null,
                            'codigo' => 2,
                            'descripcion' => 'Cáncer',
                        ],
                        [
                            'respuesta' => null,
                            'codigo' => 3,
                            'descripcion' => 'Cirrosis Hepática',
                        ],
                        [
                            'respuesta' => null,
                            'codigo' => 4,
                            'descripcion' => 'Insuficiencia Renal',
                        ],
                        [
                            'respuesta' => null,
                            'codigo' => 5,
                            'descripcion' => 'Diabetes Mellitus',
                        ],
                        [
                            'respuesta' => null,
                            'codigo' => 6,
                            'descripcion' => 'Cardiopatías',
                        ],
                        [
                            'respuesta' => null,
                            'codigo' => 7,
                            'descripcion' => 'Enfermedad Pulmonar Crónica',
                        ],
                        [
                            'respuesta' => null,
                            'codigo' => 8,
                            'descripcion' =>
                                'Deficiencia nutricional (Desnutrición)',
                        ],
                        [
                            'respuesta' => null,
                            'codigo' => 9,
                            'descripcion' => 'Hipertensión Arterial',
                        ],
                        [
                            'respuesta' => null,
                            'codigo' => 10,
                            'descripcion' => 'Obesidad',
                        ],
                        [
                            'respuesta' => null,
                            'codigo' => 11,
                            'descripcion' =>
                                'Adicción a la Ingestión de Sustancias (Drogas)',
                        ],
                        [
                            'respuesta' => null,
                            'codigo' => 12,
                            'descripcion' =>
                                'Adicciones de la conducta (Juego, internet)',
                        ],
                        [
                            'respuesta' => null,
                            'codigo' => 13,
                            'descripcion' => 'Depresión',
                        ],
                        [
                            'respuesta' => null,
                            'codigo' => 14,
                            'descripcion' => 'Ansiedad',
                        ],
                        [
                            'respuesta' => null,
                            'codigo' => 15,
                            'descripcion' => 'Trasplante de Órganos',
                        ],
                        [
                            'respuesta' => null,
                            'codigo' => 16,
                            'descripcion' => 'Ninguna',
                        ],
                    ],
                ],
                'datosEducacion' => [
                    'estudiante' => null,
                    'ultimoNivel' => [
                        'codigo' => null,
                        'descripcion' => null,
                    ],
                    'grado' => [
                        'codigo' => null,
                    ],
                ],
                'datosIngreso' => [
                    'situacionEmpleo' => [
                        'codigo' => null,
                        'descripcion' => null,
                    ],
                    'prestacionesTrabajo' => [
                        [
                            'respuesta' => null,
                            'codigo' => 1,
                            'descripcion' =>
                                'Incapacidad en caso de enfermedad, accidente o maternidad',
                        ],
                        [
                            'respuesta' => null,
                            'codigo' => 2,
                            'descripcion' => 'Aguinaldo',
                        ],
                        [
                            'respuesta' => null,
                            'codigo' => 3,
                            'descripcion' => 'Crédito de vivienda',
                        ],
                        [
                            'respuesta' => null,
                            'codigo' => 4,
                            'descripcion' =>
                                'Guarderías y estancias infantiles',
                        ],
                        [
                            'respuesta' => null,
                            'codigo' => 5,
                            'descripcion' => 'SAR o AFORE',
                        ],
                        [
                            'respuesta' => null,
                            'codigo' => 6,
                            'descripcion' => 'Seguro de vida',
                        ],
                        [
                            'respuesta' => null,
                            'codigo' => 7,
                            'descripcion' =>
                                'No tienen prestaciones provenientes de su trabajo',
                        ],
                    ],
                    'totalIngreso' => null,
                    'totalPension' => null,
                    'totalRemesa' => null,
                ],
                'datosAlimentacion' => [
                    'pocaVariedadAlimento' => null,
                    'comioMenos' => null,
                    'disminuyoCantidad' => null,
                    'tuvoHambreNoComio' => null,
                    'durmioConHambre' => null,
                    'comioUnaVezoNo' => null,
                ],
                'discapacidad' => [
                    'movilidadInferior' => null,
                    'visual' => null,
                    'habla' => null,
                    'auditivo' => null,
                    'valerse' => null,
                    'memoria' => null,
                    'movilidadSuperior' => null,
                ],
                'datosGasto' => [
                    'comida' => [
                        'gasto' => null,
                        'periodo' => [
                            'codigo' => null,
                            'descripcion' => null,
                        ],
                    ],
                    'ropa' => [
                        'gasto' => null,
                        'periodo' => [
                            'codigo' => null,
                            'descripcion' => null,
                        ],
                    ],
                    'educacion' => [
                        'gasto' => null,
                        'periodo' => [
                            'codigo' => null,
                            'descripcion' => null,
                        ],
                    ],
                    'medicina' => [
                        'gasto' => null,
                        'periodo' => [
                            'codigo' => null,
                            'descripcion' => null,
                        ],
                    ],
                    'consultas' => [
                        'gasto' => null,
                        'periodo' => [
                            'codigo' => null,
                            'descripcion' => null,
                        ],
                    ],
                    'combustible' => [
                        'gasto' => null,
                        'periodo' => [
                            'codigo' => null,
                            'descripcion' => null,
                        ],
                    ],
                    'serviciosBasicos' => [
                        'gasto' => null,
                        'periodo' => [
                            'codigo' => null,
                            'descripcion' => null,
                        ],
                    ],
                    'recreacion' => [
                        'gasto' => null,
                        'periodo' => [
                            'codigo' => null,
                            'descripcion' => null,
                        ],
                    ],
                ],
                'datosVivienda' => [
                    'estatusVivienda' => [
                        'codigo' => null,
                        'descripcion' => null,
                    ],
                    'materialPiso' => [
                        'codigo' => null,
                        'descripcion' => null,
                    ],
                    'materialPared' => [
                        'codigo' => null,
                        'descripcion' => null,
                    ],
                    'materialTecho' => [
                        'codigo' => null,
                        'descripcion' => null,
                    ],
                    'fuenteAgua' => [
                        'codigo' => null,
                        'descripcion' => null,
                    ],
                    'drenaje' => [
                        'codigo' => null,
                        'descripcion' => null,
                    ],
                    'fuenteLuzElectrica' => [
                        'codigo' => null,
                        'descripcion' => null,
                    ],
                    'combustibleCocina' => [
                        'codigo' => null,
                        'descripcion' => null,
                    ],
                    'numeroCuartos' => null,
                    'numeroPersonaHabitantes' => null,
                ],
                'datosEnseres' => [
                    'refrigerador' => null,
                    'lavadora' => null,
                    'computadora' => null,
                    'estufa' => null,
                    'boiler' => null,
                    'calentadorSolar' => null,
                    'tv' => null,
                    'internet' => null,
                    'celular' => null,
                    'tinaco' => null,
                ],
                'percepcionSeguridad' => null,
            ],
            JSON_UNESCAPED_UNICODE
        );

        return $json;
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
            if ($file->idClasificacion == 12) {
                $file->Clasificacion = 'Evidencia Fotográfica';
            } elseif ($file->idClasificacion == 5) {
                $file->Clasificacion = 'Acuse';
            }

            $formatedFile = [
                'llave' => $formato . '_' . $file->Clasificacion,
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
            if ($file->idClasificacion == 12) {
                $file->Clasificacion = 'Evidencia Fotográfica';
            } elseif ($file->idClasificacion == 5) {
                $file->Clasificacion = 'Acuse';
            }

            $mimeType = 'image/jpeg';
            if (strtoupper($file->Extension) == 'PDF') {
                $mimeType = 'application/pdf';
            } elseif (strtoupper($file->Extension) == 'PNG') {
                $mimeType = 'image/png';
            }

            $formatedFile = [
                'llave' => $formato . '_' . $file->Clasificacion,
                'ruta' =>
                    '/Users/diegolopez/Documents/GitProyect/vales/apivales/public/subidos/' .
                    $file->NombreSistema,
                //'ruta' => Storage::disk('subidos')->path($file->NombreSistema),
                'nombre' => $file->Clasificacion,
                'header' => $mimeType,
            ];
            array_push($files, $formatedFile);
        }
        return $files;
    }

    public function uploadFilesYoPuedo(Request $request)
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
        $extension = $params['ArrayExtension'];
        $names = $params['NamesFiles'];

        try {
            $solicitud = DB::table('yopuedo_cedulas')
                ->select('idUsuarioCreo', 'id')
                ->where('yopuedo_cedulas.idSolicitud', $id)
                ->first();

            if ($solicitud == null) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' => 'No se encuentra la cedula',
                ];
                return response()->json($response, 200);
            }

            foreach ($files as $key => $file) {
                $imageContent = $this->imageBase64Content($file);
                $uniqueName = uniqid() . $extension[$key];
                $clasification = $arrayClasifiacion[$key];
                $originalName = $names[$key];

                Storage::disk('subidos')->put(
                    $uniqueName,
                    $imageContent,
                    'public'
                );

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
                $tableArchivos = 'yopuedo_cedula_archivos';
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

    public function getAuthUsuario($idUser, $index)
    {
        $json = json_encode(
            [
                'uid' => '62cdc01786674330d7288cf1',
                'name' => 'MARIA DE MONTSERRAT RAMIREZ FUENTES (RESPONSABLE Q)', //Cambiar a sedeshu
                'email' => 'mramirezfuen@guanajuato.gob.mx',
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

        return $json;
    }

    public function getReporteSolicitudVentanillaYoPuedo(Request $request)
    {
        $user = auth()->user();
        $parameters['UserCreated'] = $user->id;
        $tableSol = 'vales';
        $res = DB::table('yopuedo_solicitudes as vales')
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
                DB::raw(
                    "concat_ws(' ',vales.CalleVive, concat('Num. ', vales.NoExtVive), if(vales.NoIntVive is not null,concat('NumInt. ',vales.NoIntVive), ''), concat('Col. ',vales.ColoniaVive)) as Direccion"
                ),
                'yopuedo_cedulas.AGEBVive AS ClaveAGEB',
                'yopuedo_cedulas.ManzanaVive AS Manzana',
                'vales.CPVive',
                'vales.MunicipioVive AS Municipio',
                'vales.LocalidadVive AS Localidad',
                'vales.Telefono',
                'vales.Celular',
                'vales.TelRecados',
                'vales.Correo',
                'yopuedo_cedulas.TotalHogares AS PersonasGastosSeparados',
                DB::raw("
                    CASE
                        WHEN
                        yopuedo_cedulas.ListaParaEnviar = 1
                        THEN
                            'SI'
                        ELSE
                            'NO'
                        END
                    AS ListaParaEnviar
                    "),
                'yopuedo_cedulas.NumeroMujeresHogar',
                'yopuedo_cedulas.NumeroHombresHogar',
                DB::raw("
                    CASE
                        WHEN
                        yopuedo_cedulas.PersonasMayoresEdad = 1
                        THEN
                            'SI'
                        ELSE
                            'NO'
                        END
                    AS MayoresEdad
                    "),
                DB::raw("
                    CASE
                        WHEN
                        yopuedo_cedulas.PersonasTerceraEdad = 1
                        THEN
                            'SI'
                        ELSE
                            'NO'
                        END
                    AS TerceraEdad
                    "),
                'yopuedo_cedulas.PersonaJefaFamilia',
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
            ->leftJoin(
                DB::raw(
                    '(SELECT * FROM yopuedo_cedulas WHERE yopuedo_cedulas.FechaElimino IS NULL) AS yopuedo_cedulas'
                ),
                'yopuedo_cedulas.idSolicitud',
                'vales.id'
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
        $usersNames = [];
        $newFilter = [];
        $idsUsers = '';
        $usersApp = '';

        $filtro_usuario = VNegociosFiltros::where('idUser', '=', $user->id)
            ->where('api', '=', 'getYoPuedoVentanilla')
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

                    foreach ($newFilter as $filtro) {
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
                'SolicitudesCalentadores' . date('Y-m-d') . '.xlsx'
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
            public_path() . '/archivos/formatoReporteSolicitudValesV6.xlsx'
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
                'SolicitudesCalentadores' .
                date('Y-m-d H:i:s') .
                '.xlsx'
        );
    }

    public function crearBordes($largo, $columna, &$sheet)
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

        $solicitud = DB::table('yopuedo_solicitudes')
            ->select('Enlace')
            ->where('id', $cedula->idSolicitud)
            ->whereNull('FechaElimino')
            ->get()
            ->first();

        if ($solicitud->Enlace != null || $solicitud->Enlace != '') {
            return json_encode(
                [
                    'nombre' => $solicitud->Enlace,
                    'observaciones' => '',
                ],
                JSON_UNESCAPED_UNICODE
            );
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

    public function getArchivosBeneficiaroYoPuedo(Request $request)
    {
        // $fullPath = public_path('/subidos/');
        ini_set('max_execution_time', 800);
        $inicio = date('Y-m-d H:i:s');
        $client = new Client();
        $url =
            'https://socioeducativo.guanajuato.gob.mx/api/idseWS/documentosPorCURP/';

        $data = DB::table('curps_yopuedo_archivos')
            ->select('id', 'curp')
            ->whereRaw('(descargado IS NULL OR descargado = "0")')
            ->get()
            ->chunk(500);

        if (count($data) == 0) {
            return response()->json([
                'success' => true,
                'results' => false,
                'message' => 'No se encuentran registros para extraer archivos',
                'total' => 0,
            ]);
        }

        try {
            foreach ($data as $reg) {
                foreach ($reg as $r) {
                    $request = $client->request('GET', $url . $r->curp, [
                        'verify' => false,
                        'headers' => [
                            'content-type' => 'application/json',
                            'Accept' => 'application/json',
                        ],
                    ]);

                    $res = json_decode($request->getBody()->getContents());

                    foreach ($res as $file) {
                        $archivo = explode('/', $file->Ruta);
                        $originalName = end($archivo);
                        $tipoArchivo =
                            strtoupper($file->Tipo) == 'CURP' ? 4 : 5;
                        $extensionArray = explode('.', $originalName);
                        $extension = end($extensionArray);
                        $uniqueName = uniqid() . '.' . $extension;
                        $tipo = $this->getFileType($extension);

                        $requestD = $client->request(
                            'GET',
                            'https://' . $file->Ruta,
                            [
                                'verify' => false,
                                'headers' => [
                                    'content-type' => 'application/json',
                                    'Accept' => 'application/json',
                                ],
                            ]
                        );
                        $f = $requestD->getBody()->getContents();
                        // File::put($fullPath . $uniqueName, $f);
                        Storage::disk('subidos')->put(
                            $uniqueName,
                            $f,
                            'public'
                        );

                        $fileObject = [
                            'idCurp' => $r->id,
                            'idClasificacion' => $tipoArchivo,
                            'NombreOriginal' => $originalName,
                            'NombreSistema' => $uniqueName,
                            'Extension' => $extension,
                            'Tipo' => $tipo,
                            'Tamanio' => '',
                            'idUsuarioCreo' => '1',
                            'FechaCreo' => date('Y-m-d H:i:s'),
                        ];

                        DB::table('archivos_curp_yopuedo')->insert($fileObject);
                    }

                    DB::table('curps_yopuedo_archivos')
                        ->where('id', $r->id)
                        ->update(['descargado' => 1]);
                }
            }
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            return response()->json([
                'success' => false,
                'results' => false,
                'error' => $e
                    ->getResponse()
                    ->getBody()
                    ->getContents(),
            ]);
        }
        return response()->json([
            'success' => true,
            'results' => true,
            'message' => 'Se obtuvieron los archivos con exito',
            'inicio' => $inicio,
            'fin' => date('Y-m-d H:i:s'),
        ]);
    }

    function getArticuladoresVentanilla(Request $request)
    {
        $parameters = $request->all();
        $user = auth()->user();
        $id_valor = $user->id;
        $permisos = $this->getPermisos();
        $seguimiento = $permisos->Seguimiento;
        $viewall = $permisos->ViewAll;

        try {
            $res = DB::table('users_aplicativo_web')
                ->select('idUser', 'Nombre')
                ->where('programa', '=', 'YO PUEDO, GTO PUEDE');

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
}
