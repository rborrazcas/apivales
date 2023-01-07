<?php

namespace App\Http\Controllers;

use PDF;
use Excel;
use JWTAuth;
use Imagick;
use Validator;
use App\Cedula;
use HTTP_Request2;
use GuzzleHttp\Client;
use App\VNegociosFiltros;
use Carbon\Carbon as time;

use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Validation\ValidationException;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

use App\Imports\ConciliacionImport;

class TrabajemosJuntosController extends Controller
{
    public function getPermisos()
    {
        $user = auth()->user();
        $permisos = DB::table('users_menus')
            ->where(['idUser' => $user->id, 'idMenu' => '21'])
            ->get()
            ->first();
        if ($permisos !== null) {
            return $permisos;
        } else {
            return null;
        }
    }

    public function getEstatusGlobal(Request $request)
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
        $procedimiento = 'call getEstatusGlobalTrabajemosGeneral';

        if ($viewall < 1 && $seguimiento < 1) {
            $procedimiento =
                "call getEstatusGlobalTrabajemos('" . $user->id . "')";
        } elseif ($viewall < 1) {
            $region = DB::table('users_aplicativo_web')
                ->selectRaw('idRegion')
                ->where('idUser', $user->id)
                ->get()
                ->first();
            $procedimiento =
                " call getEstatusGlobalTrabajemosRegional('" .
                $region->idRegion .
                "')";
        }
        try {
            $res = DB::select($procedimiento);
            return ['success' => true, 'results' => true, 'data' => $res];
        } catch (QueryException $e) {
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

    public function getMunicipios(Request $request)
    {
        $parameters = $request->all();
        $user = auth()->user();
        $region = DB::table('users_aplicativo_web')
            ->selectRaw('idRegion')
            ->where('idUser', $user->id)
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
                $municipios = DB::table('trabajemos_solicitudes')
                    ->select('et_cat_municipio.Nombre as municipio')
                    ->Join(
                        'et_cat_municipio',
                        'trabajemos_solicitudes.idMunicipioVive',
                        'et_cat_municipio.id'
                    )
                    ->where('trabajemos_solicitudes.idUsuarioCreo', $user->id);
            } elseif ($permisos->ViewAll < 1) {
                $municipios = DB::table('et_cat_municipio')
                    ->select('Nombre as municipio')
                    ->where('SubRegion', $region->idRegion);
            } else {
                $municipios = DB::table('et_cat_municipio')->select(
                    'Nombre as municipio'
                );
            }

            $municipios = $municipios
                ->groupBy('municipio')
                ->OrderBy('municipio')
                ->get();

            $arrayMPios = [];

            foreach ($municipios as $data) {
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

    public function getCatalogs(Request $request)
    {
        try {
            $userId = JWTAuth::parseToken()->toUser()->id;

            $articuladores = DB::table('users_aplicativo_web')
                ->select(
                    'users_aplicativo_web.idUser AS value',
                    'users_aplicativo_web.Nombre AS label'
                )
                ->join(
                    DB::RAW(
                        '(SELECT * FROM users_menus WHERE idMenu = 21 AND ViewAll < 0) AS users_menus'
                    ),
                    'users_menus.idUser',
                    'users_aplicativo_web.idUser'
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
                    ->join(
                        'users_menus',
                        'users_menus.idUser',
                        'users_aplicativo_web.idUser'
                    )
                    ->where([
                        'users_aplicativo_web.idUser' => $userId,
                        'users_menus.idMenu' => 21,
                    ])
                    ->get()
                    ->first();
                if ($idUserOwner != null) {
                    $articuladores->where(
                        'users_aplicativo_web.idUserOwner',
                        $idUserOwner->idUserOwner
                    );
                } else {
                    $articuladores->where(
                        'users_aplicativo_web.idUser',
                        $userId
                    );
                }
            }

            $articuladores
                ->where('users_aplicativo_web.Activo', '1')
                ->orderBy('label')
                ->get();

            $entidades = DB::table('cat_entidad')
                ->select('id AS value', 'Entidad AS label', 'Clave_CURP')
                ->where('id', '<>', 1)
                ->orderBy('label')
                ->get();

            $parentescosTutor = DB::table('cat_parentesco_tutor')
                ->select('id AS value', 'Parentesco AS label')
                ->orderBy('label')
                ->get();

            $municipios = DB::table('et_cat_municipio')
                ->select('id AS value', 'Nombre AS label')
                ->orderBy('label')
                ->get();

            $archivos_clasificacion = DB::table(
                'trabajemos_archivos_clasificacion'
            )
                ->select('id AS value', 'Clasificacion AS label')
                ->orderBy('label')
                ->get();

            $catalogs = [
                'entidades' => $entidades,
                'cat_parentesco_tutor' => $parentescosTutor,
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

    public function getLocalidadesByMunicipio(Request $request, $id)
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

    public function getSolicitudes(Request $request)
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
            $tableSol = 'trabajemos_solicitudes';
            $user = auth()->user();
            $permisos = $this->getPermisos();
            $parameters_serializado = serialize($params);

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
                    "')";
            } elseif ($viewall < 1) {
                $reg = DB::table('users_aplicativo_web')
                    ->selectRaw('cuentas_regionales_ventanilla.idRegion')
                    ->join(
                        'cuentas_regionales_ventanilla',
                        'users_aplicativo_web.idUserOwner',
                        '=',
                        'cuentas_regionales_ventanilla.idRegional'
                    )
                    ->where('users_aplicativo_web.idUser', $user->id)
                    ->get()
                    ->first();

                $filtroCapturo =
                    '(' .
                    $tableSol .
                    '.Region = "' .
                    $reg->idRegion .
                    '"' .
                    ')';
            }

            $solicitudes = DB::table($tableSol)
                ->selectRaw(
                    $tableSol .
                        '.*,' .
                        'entidadesVive.Entidad AS EntidadVive, ' .
                        'm.Nombre AS MunicipioVive, ' .
                        "CONCAT_WS( ' ', creadores.Nombre, creadores.Paterno, creadores.Materno ) AS CreadoPor, " .
                        "CONCAT_WS( ' ', editores.Nombre, editores.Paterno, editores.Materno ) AS ActualizadoPor, " .
                        'lpad(hex(' .
                        $tableSol .
                        '.id),6,0) AS FolioSolicitud'
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
                ->leftJoin(
                    'et_cat_municipio as m',
                    'm.id',
                    $tableSol . '.idMunicipioVive'
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

                    if ($id == '.id') {
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

                    $id = $tableSol . $id;

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
            if ($filterQuery !== '') {
                $solicitudes->whereRaw($filterQuery);
            }

            if ($filtroCapturo !== '') {
                $solicitudes->whereRaw($filtroCapturo);
            }

            if ($idsUsers !== '') {
                $filtroArticuladores =
                    '(' .
                    $tableSol .
                    '.idUsuarioCreo IN (' .
                    $idsUsers .
                    ')' .
                    ')';
                $solicitudes->whereRaw($filtroArticuladores);
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
                ->OrderByRaw($tableSol . '.id', 'DESC')
                ->offset($startIndex)
                ->take($pageSize)
                ->get();

            $filtro_usuario = VNegociosFiltros::where('idUser', '=', $user->id)
                ->where('api', '=', 'getSolicitudesTrabajemos')
                ->first();

            if ($filtro_usuario) {
                $filtro_usuario->parameters = $parameters_serializado;
                $filtro_usuario->updated_at = time::now();
                $filtro_usuario->update();
            } else {
                $objeto_nuevo = new VNegociosFiltros();
                $objeto_nuevo->api = 'getSolicitudesTrabajemos';
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

            $array_res = $solicitudes
                ->map(function ($x) {
                    $x = is_object($x) ? (array) $x : $x;
                    return $x;
                })
                ->toArray();

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

    public function createSolicitud(Request $request)
    {
        try {
            $v = Validator::make($request->all(), [
                'Nombre' => 'required',
                'Paterno' => 'required',
                'CURP' => 'required',
                'idEntidadVive' => 'required',
                'idMunicipioVive' => 'required',
                'idLocalidadVive' => 'required',
                'CPVive' => 'required',
                'ColoniaVive' => 'required',
                'CalleVive' => 'required',
                'NoExtVive' => 'required',
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
            $user = auth()->user();
            $idAplicativo = '';
            $necesidad = '';
            $costo = '';
            $tableSol = 'trabajemos_solicitudes';
            $year_start = idate('Y', strtotime('first day of January', time()));

            if (isset($params['idMunicipioVive'])) {
                $region = DB::table('et_cat_municipio')
                    ->where('id', $params['idMunicipioVive'])
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
            $params['Correo'] =
                isset($params['Correo']) && $params['Correo'] != ''
                    ? $params['Correo']
                    : null;

            unset($params['FolioSolicitud']);
            unset($params['NewClasificacion']);
            unset($params['NewFiles']);

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

            $curpRegistrado = DB::table('trabajemos_solicitudes')
                ->select(DB::RAW('lpad( hex(id ), 6, 0 ) AS Folio'), 'CURP')
                ->where('CURP', $params['CURP'])
                ->whereRaw('FechaElimino IS NULL')
                ->whereRaw('YEAR(FechaCreo) = ' . $year_start)
                ->get()
                ->first();

            if ($curpRegistrado != null) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' =>
                        'El Beneficiario con CURP ' .
                        $params['CURP'] .
                        ' ya se encuentra registrado para el ejercicio ' .
                        $year_start .
                        ' con el Folio ' .
                        $curpRegistrado->Folio,
                    'message' =>
                        'El Beneficiario con CURP ' .
                        $params['CURP'] .
                        ' ya se encuentra registrado para el ejercicio ' .
                        $year_start .
                        ' con el Folio ' .
                        $curpRegistrado->Folio,
                ];

                return response()->json($response, 200);
            }

            $params['idUsuarioCreo'] = $user->id;
            $params['FechaCreo'] = date('Y-m-d H:i:s');

            DB::beginTransaction();
            $id = DB::table($tableSol)->insertGetId($params);
            DB::commit();

            if (isset($request->NewFiles)) {
                $this->createSolicitudFiles(
                    $id,
                    $request->NewFiles,
                    $newClasificacion,
                    $user->id
                );
            }
            $folioSolicitud = str_pad(dechex($id), 6, '0', STR_PAD_LEFT);
            $response = [
                'success' => true,
                'results' => true,
                'message' =>
                    'Solicitud creada con éxito, Folio: ' .
                    strtoupper($folioSolicitud),
                'data' => ['id' => $id, 'Folio' => $folioSolicitud],
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

    public function updateSolicitud(Request $request)
    {
        try {
            $v = Validator::make($request->all(), [
                'id' => 'required',
                'Nombre' => 'required',
                'Paterno' => 'required',
                'CURP' => 'required',
                'idEntidadVive' => 'required',
                'idMunicipioVive' => 'required',
                'idLocalidadVive' => 'required',
                'CPVive' => 'required',
                'ColoniaVive' => 'required',
                'CalleVive' => 'required',
                'NoExtVive' => 'required',
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
            $tableSol = 'trabajemos_solicitudes';

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
            unset($params['FolioSolicitud']);

            $params['idUsuarioActualizo'] = $user->id;
            $params['FechaActualizo'] = date('Y-m-d H:i:s');

            if (isset($params['FechaINE'])) {
                $fechaINE = intval($params['FechaINE']);
                $year_start = idate(
                    'Y',
                    strtotime('first day of January', time())
                );

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

            $oldFiles = DB::table('trabajemos_archivos')
                ->select('id', 'idClasificacion')
                ->where('idSolicitud', $id)
                ->whereRaw('FechaElimino IS NULL')
                ->get();

            if ($oldFiles->count() > 0) {
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
                        DB::table('trabajemos_archivos')
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

    public function deleteSolicitud(Request $request)
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
                    'errors' => 'Ocurrió un error al borrar la solicitud',
                ];
                return response()->json($response, 200);
            }

            $params = $request->all();
            $tableSol = 'trabajemos_solicitudes';

            $solicitud = DB::table($tableSol)
                ->select('idEstatus')
                ->where('id', $params['id'])
                ->first();

            if ($solicitud->idEstatus > 1) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' =>
                        'La solicitud se encuentra aprobada, no se puede eliminar.',
                ];
                return response()->json($response, 200);
            }

            DB::table('trabajemos_grupos_solicitudes')
                ->where('idSolicitud', $params['id'])
                ->whereNull('FechaElimino')
                ->update([
                    'FechaElimino' => date('Y-m-d H:i:s'),
                    'idUsuarioElimino' => $user->id,
                ]);

            DB::table($tableSol)
                ->where('id', $params['id'])
                ->update([
                    'FechaElimino' => date('Y-m-d H:i:s'),
                    'idUsuarioElimino' => $user->id,
                ]);

            $oldFiles = DB::table('trabajemos_archivos')
                ->select('id', 'idClasificacion')
                ->where('idSolicitud', $params['id'])
                ->whereRaw('FechaElimino IS NULL')
                ->get();

            $oldFilesIds = array_map(function ($o) {
                return $o->id;
            }, $oldFiles->toArray());

            if (count($oldFilesIds) > 0) {
                DB::table('trabajemos_archivos')
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

    public function getCatalogsCedulaCompletos(Request $request)
    {
        try {
            $entidades = DB::table('cat_entidad')
                ->select('id AS value', 'Entidad AS label', 'Clave_CURP')
                ->where('id', '<>', 1)
                ->orderBy('label')
                ->get();

            $cat_parentesco_tutor = DB::table('cat_parentesco_tutor')
                ->select('id AS value', 'Parentesco AS label')
                ->orderBy('label')
                ->get();

            $archivos_clasificacion = DB::table(
                'trabajemos_archivos_clasificacion'
            )
                ->select('id AS value', 'Clasificacion AS label')
                ->orderBy('label')
                ->get();

            $municipios = DB::table('et_cat_municipio')
                ->select('id AS value', 'Nombre AS label')
                ->orderBy('label')
                ->get();

            $catalogs = [
                'entidades' => $entidades,
                'cat_parentesco_tutor' => $cat_parentesco_tutor,
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

    public function getClasificacionArchivos(Request $request)
    {
        try {
            $archivos_clasificacion = DB::table(
                'trabajemos_archivos_clasificacion'
            )
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

    public function getFilesById(Request $request, $id)
    {
        try {
            $archivos2 = DB::table('trabajemos_archivos')
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

    public function updateArchivosSolicitud(Request $request)
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
            $oldFiles = DB::table('trabajemos_archivos')
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
                DB::table('trabajemos_archivos')
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
            $tableArchivos = 'trabajemos_archivos';
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
        $tableArchivos = 'trabajemos_archivos';
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

    public function getReporteSolicitudTrabajemos(Request $request)
    {
        $params = $request->all();
        $user = auth()->user();
        $res = DB::table('trabajemos_solicitudes as ts')
            ->select(
                DB::raw('LPAD(HEX(ts.id),6,0) as Folio'),
                'et_cat_municipio.SubRegion AS Region',
                'ts.FechaSolicitud',
                'ts.CURP',
                'ts.Nombre',
                DB::raw("IFNULL(ts.Paterno,'') AS Paterno"),
                DB::raw("IFNULL(ts.Materno,'') AS Materno"),
                'ts.Sexo',
                'ts.ColoniaVive',
                'ts.CalleVive',
                'ts.NoExtVive',
                'ts.NoIntVive',
                'ts.CPVive',
                'et_cat_municipio.Nombre AS Municipio',
                'et_cat_localidad_2022.Nombre AS Localidad',
                'ts.Telefono',
                'ts.Celular',
                'ts.Correo',
                'solicitudes_status.Estatus',
                DB::raw(
                    "CONCAT_WS( ' ', users.Nombre, users.Paterno, users.Materno ) AS UserInfoCapturo"
                ),
                DB::raw(
                    "CONCAT_WS( ' ', actualizo.Nombre, actualizo.Paterno, actualizo.Materno ) AS UserUpdated"
                ),
                DB::raw(
                    " CONCAT_WS( ' ', enlace.Nombre, enlace.Paterno, enlace.Materno ) AS Enlace"
                ),
                DB::raw('LPAD(HEX(gs.id),6,0) as FolioGrupo'),
                'gs.Nombre AS NombreGrupo'
            )
            ->JOIN(
                'solicitudes_status',
                'solicitudes_status.id',
                '=',
                'ts.idEstatus'
            )
            ->JOIN('users', 'users.id', '=', 'ts.idUsuarioCreo')
            ->LEFTJOIN(
                'users as actualizo',
                'actualizo.id',
                '=',
                'ts.idUsuarioActualizo'
            )
            ->JOIN(
                'et_cat_municipio',
                'et_cat_municipio.id',
                '=',
                'ts.idMunicipioVive'
            )
            ->JOIN(
                'et_cat_localidad_2022',
                'et_cat_localidad_2022.id',
                '=',
                'ts.idLocalidadVive'
            )
            ->LEFTJOIN(
                DB::RAW(
                    '(SELECT * FROM trabajemos_grupos_solicitudes WHERE FechaElimino IS NULL) AS tgs'
                ),
                'tgs.idSolicitud',
                'ts.id'
            )
            ->LEFTJOIN(
                DB::RAW(
                    '(SELECT * FROM trabajemos_grupos WHERE FechaElimino IS NULL) AS gs'
                ),
                'gs.id',
                'tgs.idGrupo'
            )
            ->LEFTJOIN('users AS enlace', 'enlace.id', '=', 'ts.idEnlace')
            ->whereRaw('ts.FechaElimino IS NULL');

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
            $filtroCapturo = "(ts.idUsuarioCreo = '" . $user->id . "')";
        } elseif ($viewall < 1) {
            $region = DB::table('users_aplicativo_web')
                ->selectRaw('idRegion')
                ->where('idUser', $user->id)
                ->get()
                ->first();

            $filtroCapturo = '(ts.Region = "' . $region->idRegion . ')';
        }

        //agregando los filtros seleccionados
        $filterQuery = '';
        $municipioRegion = [];
        $mun = [];
        $newFilter = [];
        $idsUsers = '';
        $usersApp = '';

        $filtro_usuario = VNegociosFiltros::where('idUser', '=', $user->id)
            ->where('api', '=', 'getSolicitudesTrabajemos')
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

                        if ($id == '.Folio') {
                            $id = '.id';
                            $value = hexdec($value);
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

                        $id = 'ts.' . $id;

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
                '(ts.idUsuarioCreo IN (' . $idsUsers . ')' . ')';
            $res->whereRaw($filtroArticuladores);
        }

        $data = $res
            ->orderBy('ts.Paterno', 'asc')
            ->orderBy('ts.Materno', 'asc')
            ->orderBy('ts.Nombre', 'asc')
            ->get();

        //dd(str_replace_array('?', $data->getBindings(), $data->toSql()));

        if (count($data) == 0) {
            $reader = IOFactory::createReader('Xlsx');
            $spreadsheet = $reader->load(
                public_path() . '/archivos/formatoReporteSolicitudValesV12.xlsx'
            );
            $writer = new Xlsx($spreadsheet);
            $writer->save(
                'archivos/' . $user->email . 'SolicitudesTrabajemos.xlsx'
            );
            $file =
                public_path() .
                '/archivos/' .
                $user->email .
                'SolicitudesTrabajemos.xlsx';

            return response()->download(
                $file,
                'SolicitudesTrabajemos' . date('Y-m-d') . '.xlsx'
            );
        }

        //Mapeamos el resultado como un array
        $res = $data
            ->map(function ($x) {
                $x = is_object($x) ? (array) $x : $x;
                return $x;
            })
            ->toArray();

        $reader = IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load(
            public_path() . '/archivos/formatoReporteSolicitudValesV12.xlsx'
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

        $sheet->getDefaultRowDimension()->setRowHeight(-1);

        //guardamos el excel creado y luego lo obtenemos en $file para poder descargarlo
        $writer = new Xlsx($spreadsheet);
        $writer->save(
            'archivos/' . $user->email . 'SolicitudesTrabajemosJuntos.xlsx'
        );
        $file =
            public_path() .
            '/archivos/' .
            $user->email .
            'SolicitudesTrabajemosJuntos.xlsx';

        return response()->download(
            $file,
            $user->email .
                'SolicitudesTrabajemosJuntos' .
                date('Y-m-d H:i:s') .
                '.xlsx'
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

    public function getArticuladores(Request $request)
    {
        $user = auth()->user();
        $parameters = $request->all();
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

        $viewall = $permisos->ViewAll;

        try {
            $res = DB::table('users_aplicativo_web')
                ->select(
                    'users_aplicativo_web.idUser',
                    'users_aplicativo_web.Nombre'
                )
                ->join(
                    'users_menus',
                    'users_menus.idUser',
                    'users_aplicativo_web.idUser'
                )
                ->where([
                    'users_menus.idMenu' => 21,
                    'users_menus.ViewAll' => 0,
                ]);

            if ($viewall < 1) {
                $idRegion = DB::table('users_aplicativo_web')
                    ->select('users_aplicativo_web.idRegion')
                    ->join(
                        'users_menus',
                        'users_menus.idUser',
                        'users_aplicativo_web.idUser'
                    )
                    ->where([
                        'users_aplicativo_web.idUser' => $user->id,
                        'users_menus.idMenu' => 21,
                    ])
                    ->first();

                $res = $res->where(
                    'users_aplicativo_web.idRegion',
                    $idRegion->idRegion
                );
            }

            $res = $res->orderBy('users_aplicativo_web.Nombre');

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

    public function validarExpediente($id)
    {
        $ine = DB::table('trabajemos_archivos')
            ->where('idSolicitud', $id)
            ->where('idClasificacion', '2')
            ->whereNull('FechaElimino')
            ->get()
            ->first();

        $acuse = DB::table('trabajemos_archivos')
            ->where('idSolicitud', $id)
            ->where('idClasificacion', '5')
            ->whereNull('FechaElimino')
            ->get()
            ->first();

        if ($ine == null) {
            $tarjeta = DB::table('trabajemos_archivos')
                ->where('idSolicitud', $id)
                ->where('idClasificacion', '8')
                ->whereNull('FechaElimino')
                ->get()
                ->first();
            if ($tarjeta == null) {
                DB::table('trabajemos_solicitudes')
                    ->where('id', $id)
                    ->update(['ExpedienteCompleto' => 0]);
                return false;
            }
        }

        if ($acuse != null) {
            DB::table('trabajemos_solicitudes')
                ->where('id', $id)
                ->update(['ExpedienteCompleto' => 1]);
        }
        return true;
    }

    public function getGruposByMunicipio(Request $request)
    {
        try {
            $v = Validator::make($request->all(), [
                'idMunicipio' => 'required',
            ]);

            if ($v->fails()) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' => 'Ocurrio un error al obtener los grupos',
                ];
                return response()->json($response, 200);
            }
            $params = $request->all();
            $grupos = DB::Table('trabajemos_grupos')
                ->select(
                    'trabajemos_grupos.id as value',
                    DB::RAW(
                        'CONCAT (trabajemos_grupos.Nombre," - ",et_cat_municipio.Nombre) AS label'
                    )
                )
                ->where('idMunicipio', $params['idMunicipio'])
                ->WhereNull('FechaElimino');
            if ($params['idSolicitud'] > 0) {
                $gruposActivos = DB::table('trabajemos_grupos_solicitudes')
                    ->select('idGrupo')
                    ->where('idSolicitud', $params['idSolicitud'])
                    ->whereNull('FechaElimino')
                    ->get();
                if ($gruposActivos->count() > 0) {
                    $grupos = $grupos->whereRaw(
                        'trabajemos_grupos.id NOT IN (SELECT idGrupo FROM trabajemos_grupos_solicitudes WHERE FechaElimino IS NULL AND idSolicitud = ' .
                            $params['idSolicitud'] .
                            ')'
                    );
                }
            }
            $grupos = $grupos
                ->Join(
                    'et_cat_municipio',
                    'trabajemos_grupos.idMunicipio',
                    'et_cat_municipio.id'
                )
                ->get();

            $response = [
                'success' => true,
                'results' => true,
                'data' => $grupos,
            ];

            return response()->json($response, 200);
        } catch (QueryException $errors) {
            $response = [
                'success' => false,
                'results' => false,
                'errors' => $errors,
            ];

            return response()->json($response, 200);
        }
    }

    public function getGrupos(Request $request)
    {
        try {
            $v = Validator::make($request->all(), [
                'idSolicitud' => 'required',
            ]);

            if ($v->fails()) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' =>
                        'Ocurrio un error al obtener los grupos de la solicitud',
                ];
                return response()->json($response, 200);
            }
            $params = $request->all();

            $grupos = DB::Table('trabajemos_grupos_solicitudes AS tgs')
                ->select(
                    'gp.id',
                    DB::raw('LPAD(HEX(gp.id),6,0) as FolioGrupo'),
                    'gp.Nombre',
                    'm.Nombre AS Municipio'
                )
                ->join('trabajemos_grupos AS gp', 'gp.id', 'tgs.idGrupo')
                ->Join('et_cat_municipio as m', 'gp.idMunicipio', 'm.id')
                ->where('tgs.idSolicitud', $params['idSolicitud'])
                ->WhereNull('tgs.FechaElimino')
                ->WhereNull('gp.FechaElimino')
                ->get();

            $response = [
                'success' => true,
                'results' => true,
                'data' => $grupos,
                'total' => $grupos->count(),
            ];

            return response()->json($response, 200);
        } catch (QueryException $errors) {
            $response = [
                'success' => false,
                'results' => false,
                'errors' => $errors,
            ];

            return response()->json($response, 200);
        }
    }

    public function getGruposDisponibles(Request $request)
    {
        try {
            $v = Validator::make($request->all(), [
                'idSolicitud' => 'required',
            ]);

            if ($v->fails()) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' =>
                        'Ocurrio un error al obtener los grupos de la solicitud',
                ];
                return response()->json($response, 200);
            }
            $params = $request->all();

            $grupos = DB::Table('trabajemos_grupos AS tg')
                ->select(
                    'tg.id AS idGrupo',
                    DB::raw('LPAD(HEX(tg.id),6,0) as FolioGrupo'),
                    'tg.Nombre',
                    'm.Nombre AS Municipio',
                    'tgs.id AS Registrado'
                )
                ->LeftJoin(
                    DB::RAW(
                        '( SELECT * FROM trabajemos_grupos_solicitudes WHERE FechaElimino IS NULL AND idSolicitud = ' .
                            $params['idSolicitud'] .
                            ' ) AS tgs'
                    ),
                    'tg.id',
                    'tgs.idGrupo'
                )
                ->Join('et_cat_municipio as m', 'tg.idMunicipio', 'm.id')
                ->where('tg.idMunicipio', $params['idMunicipio'])
                ->WhereNull('tg.FechaElimino')
                ->WhereNull('tgs.id')
                ->get();

            // dd(
            //     str_replace_array('?', $grupos->getBindings(), $grupos->toSql())
            // );

            $response = [
                'success' => true,
                'results' => true,
                'data' => $grupos,
                'total' => $grupos->count(),
            ];

            return response()->json($response, 200);
        } catch (QueryException $errors) {
            $response = [
                'success' => false,
                'results' => false,
                'errors' => $errors,
            ];

            return response()->json($response, 200);
        }
    }

    public function deleteRelation(Request $request)
    {
        try {
            $v = Validator::make($request->all(), [
                'idSolicitud' => 'required',
                'idGrupo' => 'required',
            ]);

            if ($v->fails()) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' => 'Ocurrio un error eliminar la relación',
                ];
                return response()->json($response, 200);
            }

            $params = $request->all();
            $user = auth()->user();

            DB::table('trabajemos_grupos_solicitudes')
                ->where([
                    'idSolicitud' => $params['idSolicitud'],
                    'idGrupo' => $params['idGrupo'],
                ])
                ->update([
                    'idUsuarioElimino' => $user->id,
                    'FechaElimino' => date('Y-m-d H:i:s'),
                ]);

            $response = [
                'success' => true,
                'results' => true,
                'message' => 'Eliminada con éxito',
            ];

            return response()->json($response, 200);
        } catch (QueryException $errors) {
            $response = [
                'success' => false,
                'results' => false,
                'errors' => $errors,
            ];

            return response()->json($response, 200);
        }
    }

    public function addRelation(Request $request)
    {
        try {
            $v = Validator::make($request->all(), [
                'idSolicitud' => 'required',
                'idGrupo' => 'required',
            ]);

            if ($v->fails()) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' => 'Ocurrio un error al añadir la relación',
                ];
                return response()->json($response, 200);
            }

            $params = $request->all();
            $user = auth()->user();

            $relation = [
                'idGrupo' => $params['idGrupo'],
                'idSolicitud' => $params['idSolicitud'],
                'idUsuarioCreo' => $user->id,
                'FechaCreo' => date('Y-m-d H:i:s'),
            ];

            DB::table('trabajemos_grupos_solicitudes')->insert($relation);

            $response = [
                'success' => true,
                'results' => true,
                'message' => 'Añadido con éxito',
            ];

            return response()->json($response, 200);
        } catch (QueryException $errors) {
            $response = [
                'success' => false,
                'results' => false,
                'errors' => $errors,
            ];

            return response()->json($response, 200);
        }
    }
}
