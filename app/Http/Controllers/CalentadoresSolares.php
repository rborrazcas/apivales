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

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Arr;

use GuzzleHttp\Client;
use Carbon\Carbon as time;

use Zipper;
use Imagick;
use JWTAuth;
use Validator;
use HTTP_Request2;

use App\VNegociosFiltros;
use App\Cedula;

class CalentadoresSolares extends Controller
{
    function getPermisos($id)
    {
        $permisos = DB::table('users_menus AS um')
            ->Select('um.idUser', 'um.Seguimiento', 'um.ViewAll')
            ->where(['um.idUser' => $id, 'um.idMenu' => '27'])
            ->first();
        return $permisos;
    }

    function getCapturadas(Request $request)
    {
        try {
            $user = auth()->user();
            $res = DB::table('solicitudes_calentadores AS c')
                ->selectRaw('COUNT(c.id) AS Total')
                ->JOIN('et_cat_municipio AS m', 'm.id', 'c.idMunicipio')
                ->whereRaw('c.FechaElimino IS NULL');
            $permisos = $this->getPermisos($user->id);
            $seguimiento = $permisos->Seguimiento;
            $viewall = $permisos->ViewAll;
            $filtroPermisos = '';

            if ($viewall < 1 && $seguimiento < 1) {
                $userMunicipio = DB::table('users_municipios')
                    ->Where('idUser', $user->id)
                    ->get();

                if ($userMunicipio->count() == 0) {
                    $filtroPermisos = 'c.idUsuarioCreo = ' . $user->id;
                } else {
                    $filtroPermisos =
                        '(c.idMunicipio IN (' .
                        'SELECT idMunicipio FROM users_municipios WHERE idPrograma = 2 AND idUser = ' .
                        $user->id .
                        ')' .
                        ')';
                }
            } elseif ($viewall < 1) {
                $filtroPermisos =
                    'm.SubRegion IN (' .
                    'SELECT Region FROM users_region WHERE idPrograma = 2 AND idUser = ' .
                    $user->id .
                    ')';
            }

            if ($filtroPermisos !== '') {
                $res->whereRaw($filtroPermisos);
            }

            $capturadas = $res->first();

            if (!$capturadas) {
                $total = 0;
            } else {
                $total = $capturadas->Total;
            }

            $response = [
                'success' => true,
                'results' => true,
                'capturadas' => $total,
            ];

            return response()->json($response, 200);
        } catch (QueryException $errors) {
            $response = [
                'success' => false,
                'results' => false,
                'errors' => $errors,
                'message' => 'Ha ocurrido un error, consulte al administrador',
            ];
            return response()->json($response, 200);
        }
    }

    function getPendientes(Request $request)
    {
        try {
            $user = auth()->user();
            $res = DB::table('solicitudes_calentadores AS c')
                ->selectRaw('COUNT(c.id) AS Total')
                ->JOIN('et_cat_municipio AS m', 'm.id', 'c.idMunicipio')
                ->whereRaw(
                    '(c.idEstatusSolicitud = 1 OR c.idEstatusSolicitud = 13)'
                )
                ->whereRaw('c.FechaElimino IS NULL');
            $permisos = $this->getPermisos($user->id);
            $seguimiento = $permisos->Seguimiento;
            $viewall = $permisos->ViewAll;
            $filtroPermisos = '';

            if ($viewall < 1 && $seguimiento < 1) {
                $userMunicipio = DB::table('users_municipios')
                    ->Where('idUser', $user->id)
                    ->get();

                if ($userMunicipio->count() == 0) {
                    $filtroPermisos = 'c.idUsuarioCreo = ' . $user->id;
                } else {
                    $filtroPermisos =
                        '(c.idMunicipio IN (' .
                        'SELECT idMunicipio FROM users_municipios WHERE idPrograma = 2 AND idUser = ' .
                        $user->id .
                        ')' .
                        ')';
                }
            } elseif ($viewall < 1) {
                $filtroPermisos =
                    'm.SubRegion IN (' .
                    'SELECT Region FROM users_region WHERE idPrograma = 2 AND idUser = ' .
                    $user->id .
                    ')';
            }

            if ($filtroPermisos !== '') {
                $res->whereRaw($filtroPermisos);
            }

            $pendientes = $res->first();

            if (!$pendientes) {
                $total = 0;
            } else {
                $total = $pendientes->Total;
            }

            $response = [
                'success' => true,
                'results' => true,
                'pendientes' => $total,
            ];

            return response()->json($response, 200);
        } catch (QueryException $errors) {
            $response = [
                'success' => false,
                'results' => false,
                'errors' => $errors,
                'message' => 'Ha ocurrido un error, consulte al administrador',
            ];
            return response()->json($response, 200);
        }
    }

    function getObservadas(Request $request)
    {
        try {
            $user = auth()->user();
            $res = DB::table('solicitudes_calentadores AS c')
                ->selectRaw('COUNT(c.id) AS Total')
                ->JOIN('et_cat_municipio AS m', 'm.id', 'c.idMunicipio')
                ->where('c.idEstatusSolicitud', 11)
                ->whereRaw('c.FechaElimino IS NULL');
            $permisos = $this->getPermisos($user->id);
            $seguimiento = $permisos->Seguimiento;
            $viewall = $permisos->ViewAll;
            $filtroPermisos = '';

            if ($viewall < 1 && $seguimiento < 1) {
                $userMunicipio = DB::table('users_municipios')
                    ->Where('idUser', $user->id)
                    ->get();

                if ($userMunicipio->count() == 0) {
                    $filtroPermisos = 'c.idUsuarioCreo = ' . $user->id;
                } else {
                    $filtroPermisos =
                        '(c.idMunicipio IN (' .
                        'SELECT idMunicipio FROM users_municipios WHERE idPrograma = 2 AND idUser = ' .
                        $user->id .
                        ')' .
                        ')';
                }
            } elseif ($viewall < 1) {
                $filtroPermisos =
                    'm.SubRegion IN (' .
                    'SELECT Region FROM users_region WHERE idPrograma = 2 AND idUser = ' .
                    $user->id .
                    ')';
            }

            if ($filtroPermisos !== '') {
                $res->whereRaw($filtroPermisos);
            }

            $observadas = $res->first();

            if (!$observadas) {
                $total = 0;
            } else {
                $total = $observadas->Total;
            }

            $response = [
                'success' => true,
                'results' => true,
                'observadas' => $total,
            ];

            return response()->json($response, 200);
        } catch (QueryException $errors) {
            $response = [
                'success' => false,
                'results' => false,
                'errors' => $errors,
                'message' => 'Ha ocurrido un error, consulte al administrador',
            ];
            return response()->json($response, 200);
        }
    }

    function getValidadas(Request $request)
    {
        try {
            $user = auth()->user();
            $res = DB::table('solicitudes_calentadores AS c')
                ->selectRaw('COUNT(c.id) AS Total')
                ->JOIN('et_cat_municipio AS m', 'm.id', 'c.idMunicipio')
                ->where('c.idEstatusSolicitud', 5)
                ->whereRaw('c.FechaElimino IS NULL');
            $permisos = $this->getPermisos($user->id);
            $seguimiento = $permisos->Seguimiento;
            $viewall = $permisos->ViewAll;
            $filtroPermisos = '';

            if ($viewall < 1 && $seguimiento < 1) {
                $userMunicipio = DB::table('users_municipios')
                    ->Where('idUser', $user->id)
                    ->get();

                if ($userMunicipio->count() == 0) {
                    $filtroPermisos = 'c.idUsuarioCreo = ' . $user->id;
                } else {
                    $filtroPermisos =
                        '(c.idMunicipio IN (' .
                        'SELECT idMunicipio FROM users_municipios WHERE idPrograma = 2 AND idUser = ' .
                        $user->id .
                        ')' .
                        ')';
                }
            } elseif ($viewall < 1) {
                $filtroPermisos =
                    'm.SubRegion IN (' .
                    'SELECT DISTINCT Region FROM users_region WHERE idPrograma = 2 AND idUser = ' .
                    $user->id .
                    ')';
            }

            if ($filtroPermisos !== '') {
                $res->whereRaw($filtroPermisos);
            }

            $validadas = $res->first();

            if (!$validadas) {
                $total = 0;
            } else {
                $total = $validadas->Total;
            }

            $response = [
                'success' => true,
                'results' => true,
                'validadas' => $total,
            ];

            return response()->json($response, 200);
        } catch (QueryException $errors) {
            $response = [
                'success' => false,
                'results' => false,
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
            $programa = 2;
            $parameters_serializado = serialize($params);
            $tabla = 'solicitudes_calentadores AS c';

            $permisos = $this->getPermisos($user->id);

            if (!$permisos) {
                return [
                    'success' => true,
                    'results' => false,
                    'message' => 'No tiene permisos para ver la información',
                    'data' => [],
                ];
            }

            if (isset($params['filtered']) && count($params['filtered']) > 0) {
                foreach ($params['filtered'] as $filtro) {
                    $value = $filtro['value'];

                    if (!$this->validateInput($value)) {
                        $response = [
                            'success' => true,
                            'results' => false,
                            'message' =>
                                'Uno o más filtros utilizados no son válidos, intente nuevamente',
                        ];

                        return response()->json($response, 200);
                    }
                }
            }

            $seguimiento = $permisos->Seguimiento;
            $viewall = $permisos->ViewAll;
            $filtroPermisos = '';
            $filtroSeguimiento = '';

            if ($viewall < 1 && $seguimiento < 1) {
                $userMunicipio = DB::table('users_municipios')
                    ->Where('idUser', $user->id)
                    ->get();

                if ($userMunicipio->count() == 0) {
                    $filtroSeguimiento = 'c.idUsuarioCreo = ' . $user->id;
                } else {
                    $filtroPermisos =
                        '(c.idMunicipio IN (' .
                        'SELECT idMunicipio FROM users_municipios WHERE idPrograma = 2 AND idUser = ' .
                        $user->id .
                        ')' .
                        ')';
                }
            } elseif ($viewall < 1) {
                $filtroPermisos =
                    '(m.SubRegion IN (' .
                    'SELECT Region FROM users_region WHERE idPrograma = 2 AND idUser = ' .
                    $user->id .
                    ')' .
                    ')';
            }

            $solicitudes = DB::table($tabla)
                ->SELECT(
                    DB::RAW('LPAD(HEX(c.id),6,0) AS FolioSolicitud'),
                    'c.id',
                    'c.FolioImpulso',
                    'c.idEstatusSolicitud',
                    'e.Estatus',
                    'c.Nombre',
                    'c.Paterno',
                    'c.Materno',
                    'c.CURP',
                    'm.SubRegion AS Region',
                    'm.Nombre As Municipio',
                    'c.Colonia',
                    'c.Calle',
                    'c.NumExt',
                    'c.CP',
                    'c.Telefono',
                    'c.Celular',
                    'c.ExpedienteCompleto',
                    'c.Formato',
                    'c.Ejercicio',
                    'tac.Apoyo AS TipoApoyo',
                    DB::RAW(
                        "CONCAT_WS(' ',creadores.Nombre,creadores.Paterno,creadores.Materno) AS CreadoPor"
                    )
                )
                ->leftJoin(
                    'users AS creadores',
                    'creadores.id',
                    'c.idUsuarioCreo'
                )
                ->leftJoin(
                    'cat_tipo_apoyo_calentador AS tac',
                    'tac.id',
                    'c.Formato'
                )
                ->leftJoin(
                    'users AS editores',
                    'editores.id',
                    'c.idUsuarioActualizo'
                )
                ->leftJoin(
                    'solicitudes_status AS e',
                    'e.id',
                    'c.idEstatusSolicitud'
                )
                ->JOIN('et_cat_municipio as m', 'm.id', 'c.idMunicipio')
                ->whereNull('c.FechaElimino');

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

                    if ($id == '.id') {
                        $value = hexdec($value);
                    }

                    if ($id == 'region') {
                        $municipios = DB::table('et_cat_municipio')
                            ->select('Id')
                            ->whereIN('SubRegion', $value)
                            ->get();
                        foreach ($municipios as $m) {
                            $municipioRegion[] = $m->Id;
                        }

                        $id = '.idMunicipio';
                        $value = $municipioRegion;
                    }

                    $id = 'c' . $id;
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

            if ($filtroPermisos !== '') {
                $solicitudes->whereRaw($filtroPermisos);
            }

            if ($filtroSeguimiento !== '') {
                $solicitudes->whereRaw($filtroSeguimiento);
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
                ->orderby('c.id', 'desc')
                ->get();

            $filtro_usuario = VNegociosFiltros::where('idUser', '=', $user->id)
                ->where('api', '=', 'getCalentadores')
                ->first();

            if ($filtro_usuario) {
                $filtro_usuario->parameters = $parameters_serializado;
                $filtro_usuario->updated_at = time::now();
                $filtro_usuario->update();
            } else {
                $objeto_nuevo = new VNegociosFiltros();
                $objeto_nuevo->api = 'getCalentadores';
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

            foreach ($solicitudes as $data) {
                $array_res[] = $data;
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

    function getSolicitud(Request $request, $id)
    {
        try {
            $user = auth()->user();
            $programa = 2;

            $solicitud = DB::table('solicitudes_calentadores AS c')
                ->SELECT(
                    'c.id',
                    'c.FechaSolicitud',
                    'c.FolioTarjetaImpulso',
                    'c.CURP',
                    DB::RAW('LPAD(HEX(c.id),6,0) AS Folio'),
                    'c.Nombre',
                    'c.Paterno',
                    'c.Materno',
                    'c.FechaNacimiento',
                    'c.Edad',
                    'c.Sexo',
                    'c.FechaINE',
                    'c.Calle',
                    'c.NumExt',
                    'c.NumInt',
                    'c.CP',
                    'c.Colonia',
                    'c.idMunicipio',
                    'c.idLocalidad',
                    'c.Referencias',
                    'c.Telefono',
                    'c.Celular',
                    'c.Correo',
                    'c.idParentescoTutor',
                    'c.CURPTutor',
                    'c.NombreTutor',
                    'c.PaternoTutor',
                    'c.MaternoTutor',
                    'c.Enlace',
                    'c.Formato',
                    'c.Ejercicio',
                    'c.idEstatusSolicitud',
                    'm.SubRegion As Region'
                )
                ->join('et_cat_municipio AS m', 'c.idMunicipio', 'm.id')
                ->Where('c.id', $id)
                ->WhereNull('FechaElimino')
                ->first();

            $response = [
                'success' => true,
                'results' => true,
                'data' => $solicitud,
            ];
            return response()->json($response, 200);
        } catch (QueryException $errors) {
            $response = [
                'success' => false,
                'results' => false,
                'errors' => $errors,
                'message' => 'Ha ocurrido un error, consulte al administrador',
            ];
            return response()->json($response, 200);
        }
    }

    public function getSolicitudesReporte(Request $request)
    {
        $user = auth()->user();
        $parameters['UserCreated'] = $user->id;
        $table = 'solicitudes_calentadores AS c';
        $res = DB::table($table)
            ->select(
                DB::raw('LPAD(HEX(c.id),6,0) as ID'),
                'm.SubRegion AS Region',
                'c.FechaSolicitud',
                's.Estatus',
                'c.CURP',
                'c.Nombre',
                DB::raw("IFNULL(c.Paterno,'')"),
                DB::raw("IFNULL(c.Materno,'')"),
                'c.Sexo',
                'c.FechaNacimiento',
                'c.FechaINE',
                'c.Colonia',
                'c.Calle',
                'c.NumExt',
                'c.NumInt',
                'c.Cp',
                'm.Nombre AS Municipio',
                'l.CveInegi',
                'l.Nombre AS Localidad',
                'c.Telefono',
                'c.Celular',
                'c.TelRecados',
                'c.Correo',
                'c.Enlace',
                DB::raw(
                    'CONCAT_WS(" ",creador.Nombre,creador.Paterno,creador.Materno) AS Creador'
                ),
                'c.FechaCreo',
                DB::raw(
                    'CONCAT_WS(" ",editor.Nombre,editor.Paterno,editor.Materno) AS Actualizo'
                ),
                'c.FechaActualizo',
                'c.FolioImpulso',
                'c.Ejercicio'
            )
            ->JOIN('users AS creador', 'creador.id', '=', 'c.idUsuarioCreo')
            ->leftJoin(
                'users AS editor',
                'editor.id',
                '=',
                'c.idUsuarioActualizo'
            )
            ->JOIN('et_cat_municipio AS m', 'm.id', 'c.idMunicipio')
            ->LEFTJOIN('et_cat_localidad_2022 AS l', 'l.id', 'c.idLocalidad')
            ->JOIN('solicitudes_status AS s', 'c.idEstatusSolicitud', 's.id')
            ->LEFTJOIN('cat_tipo_apoyo_calentador AS t', 't.id', 'c.Formato')
            ->whereRaw('c.FechaElimino IS NULL');

        $archivo = '/archivos/formatoReporteNominaCalentador.xlsx';
        if (
            in_array($user->id, [
                1,
                52,
                1073,
                1360,
                1469,
                1582,
                1682,
                1887,
                1888,
                1889,
                1890,
                1909,
                59,
                85,
                171,
                1908,
                1294,
                1295,
                1340,
                2063,
            ])
        ) {
            $archivo = '/archivos/formatoReporteNominaCalentador2.xlsx';
            $res = $res->AddSelect('t.Apoyo AS TipoApoyo');
        }

        $permisos = $this->getPermisos($user->id);
        $seguimiento = $permisos->Seguimiento;
        $viewall = $permisos->ViewAll;
        $filtroPermisos = '';

        if ($viewall < 1 && $seguimiento < 1) {
            $filtroPermisos =
                '(' .
                $tabla .
                '.idMunicipio IN (' .
                'SELECT idMunicipio FROM users_municipios WHERE idPrograma = 2 AND idUser = ' .
                $user->id .
                ')' .
                ')';
        } elseif ($viewall < 1) {
            $filtroPermisos =
                '(m.SubRegion IN (' .
                'SELECT Region FROM users_region WHERE idPrograma = 2 AND idUser = ' .
                $user->id .
                ')' .
                ')';
        }

        //agregando los filtros seleccionados
        $filterQuery = '';
        $municipioRegion = [];
        $mun = [];
        $filtro_usuario = VNegociosFiltros::where('idUser', '=', $user->id)
            ->where('api', '=', 'getCalentadores')
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

                        if ($id == 'region') {
                            $municipios = DB::table('et_cat_municipio')
                                ->select('Id')
                                ->whereIN('SubRegion', $value)
                                ->get();
                            foreach ($municipios as $m) {
                                $municipioRegion[] = $m->Id;
                            }

                            $id = '.idMunicipio';
                            $value = $municipioRegion;
                        }

                        $id = 'c' . $id;
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
            }
        }

        if ($filterQuery != '') {
            $res->whereRaw($filterQuery);
        }

        if ($filtroPermisos !== '') {
            $res->whereRaw($filtroPermisos);
        }
        $data = $res
            ->orderBy('m.SubRegion', 'asc')
            ->orderBy('m.Nombre', 'asc')
            ->orderBy('l.Nombre', 'asc')
            ->orderBy('c.Enlace', 'asc')
            ->orderBy('c.Colonia', 'asc')
            ->orderBy('c.Calle', 'asc')
            ->orderBy('c.Paterno', 'asc')
            ->orderBy('c.Materno', 'asc')
            ->orderBy('c.Nombre', 'asc')
            ->get();

        if (count($data) == 0) {
            $file =
                public_path() . '/archivos/formatoReporteNominaCalentador.xlsx';

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

        $reader = IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load(public_path() . $archivo);
        $sheet = $spreadsheet->getActiveSheet();
        $largo = count($res);
        $impresion = $largo + 10;
        $sheet->getPageSetup()->setPrintArea('A1:V' . $impresion);
        $sheet
            ->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_LETTER);

        $sheet->fromArray($res, null, 'B11');

        $sheet->setCellValue('U6', 'Fecha Reporte: ' . date('Y-m-d H:i:s'));

        for ($i = 1; $i <= $largo; $i++) {
            $inicio = 10 + $i;
            $sheet->setCellValue('A' . $inicio, $i);
        }

        if ($largo > 90) {
            for ($lb = 70; $lb < $largo; $lb += 70) {
                $sheet->setBreak(
                    'B' . ($lb + 10),
                    \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::BREAK_ROW
                );
            }
        }

        $sheet->getDefaultRowDimension()->setRowHeight(-1);

        $writer = new Xlsx($spreadsheet);
        $writer->save(
            'archivos/' . $user->email . 'SolicitudesCalentadores.xlsx'
        );
        $file =
            public_path() .
            '/archivos/' .
            $user->email .
            'SolicitudesCalentadores.xlsx';

        return response()->download(
            $file,
            $user->email .
                'SolicitudesCalentadores' .
                date('Y-m-d H:i:s') .
                '.xlsx'
        );
    }

    function getMunicipios(Request $request)
    {
        $parameters = $request->all();
        $user = auth()->user();
        $permisos = $this->getPermisos($user->id);
        if (!$permisos) {
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

        if ($viewall < 1 && $seguimiento < 1) {
            $query = DB::table('users_municipios AS um')
                ->select('m.Id', 'm.Nombre', 'm.SubRegion')
                ->join('et_cat_municipio AS m', 'm.Id', 'um.idMunicipio')
                ->where(['um.idUser' => $user->id, 'um.idPrograma' => 2]);
        } elseif ($viewall < 1) {
            $queryRegiones = DB::table('users_region')
                ->select('Region')
                ->where(['idUser' => $user->id, 'idPrograma' => 2])
                ->get();
            $regiones = [];
            foreach ($queryRegiones as $region) {
                $regiones[] = $region->Region;
            }
            $query = DB::table('et_cat_municipio AS m')
                ->select('m.Id', 'm.Nombre', 'm.SubRegion')
                ->WhereIn('m.SubRegion', $regiones);
        } else {
            $query = DB::table('et_cat_municipio AS m')->select(
                'm.Id',
                'm.Nombre',
                'm.SubRegion'
            );
        }

        $res = $query->get();

        return [
            'success' => true,
            'results' => true,
            'data' => $res,
        ];
    }

    public function getFilesClasification(Request $request)
    {
        try {
            $clasificacion = DB::table('solicitudes_archivos_clasificacion')
                ->select('id AS value', 'Clasificacion AS label')
                ->whereIn('idPrograma', [0, 2])
                ->OrderBy('Clasificacion', 'ASC')
                ->get();

            $response = [
                'success' => true,
                'results' => true,
                'data' => $clasificacion,
            ];
            return response()->json($response, 200);
        } catch (\Throwable $errors) {
            $response = [
                'success' => false,
                'results' => false,
                'errors' => $errors,
                'message' => 'Ha ocurrido un error, consulte al administrador',
            ];

            return response()->json($response, 200);
        }
    }

    public function setFileStatus(Request $request)
    {
        $v = Validator::make($request->all(), [
            'idArchivo' => 'required',
            'idClasificacion' => 'required',
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

        $file = DB::table('solicitudes_archivos AS a')
            ->select(
                'a.id',
                'a.idClasificacion',
                'a.idEstatus',
                'a.idPrograma',
                'a.idSolicitud'
            )
            ->where('a.id', $params['idArchivo'])
            ->first();

        if ($file) {
            if ($file->idEstatus === 3) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'message' =>
                        'El archivo está aprobado, no puede modificarse',
                ];
            }
            $fileExists = DB::table('solicitudes_archivos AS a')
                ->select('a.id', 'a.idClasificacion', 'idEstatus', 'idPrograma')
                ->where([
                    'a.idSolicitud' => $file->idSolicitud,
                    'a.idPrograma' => $file->idPrograma,
                    'a.idClasificacion' => $params['idClasificacion'],
                ])
                ->WhereRaw('a.FechaElimino IS NULL')
                ->first();

            if ($fileExists) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'message' =>
                        'La solicitud ya tiene un archivo con esta clasificación',
                ];
            } else {
                DB::table('solicitudes_archivos AS a')
                    ->where('a.id', $params['idArchivo'])
                    ->update([
                        'idClasificacion' => $params['idClasificacion'],
                        'idUsuarioActualizo' => $user->id,
                        'FechaActualizo' => date('Y-m-d H:i:s'),
                    ]);

                $sol = DB::table('solicitudes_archivos AS a')
                    ->Select('a.idSolicitud')
                    ->where('a.id', $params['idArchivo'])
                    ->first();

                if ($this->validateExpediente($sol->idSolicitud)) {
                    DB::table('solicitudes_calentadores')
                        ->where('id', $sol->idSolicitud)
                        ->update([
                            'ExpedienteCompleto' => 1,
                        ]);
                }

                $response = [
                    'success' => true,
                    'results' => true,
                    'message' => 'Se cambió la clasificación con éxito',
                ];
            }
        } else {
            $response = [
                'success' => true,
                'results' => false,
                'message' => 'No se encuentra el archivo',
            ];
        }

        return response()->json($response, 200);
    }

    public function setFilesComments(Request $request)
    {
        $v = Validator::make($request->all(), [
            'idSolicitud' => 'required',
            'idPrograma' => 'required',
            'idArchivo' => 'required',
            'Estatus' => 'required',
            'Observacion' => 'required',
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
        $params['idUsuarioCreo'] = $user->id;
        $params['FechaCreo'] = date('Y-m-d H:i:s');

        DB::table('solicitudes_archivos')
            ->where('id', $params['idArchivo'])
            ->update([
                'idEstatus' => 2,
                'idUsuarioObservo' => $user->id,
                'FechaObservo' => date('Y-m-d H:i:s'),
            ]);
        DB::table('solicitudes_calentadores')
            ->where('id', $params['idSolicitud'])
            ->update([
                'idEstatusSolicitud' => 11,
            ]);
        DB::table('solicitudes_archivos_observaciones')->insert($params);

        $response = [
            'success' => true,
            'results' => true,
            'message' => 'Se agrego correctamente la observación',
        ];

        return response()->json($response, 200);
    }

    function changeFiles(Request $request)
    {
        try {
            $v = Validator::make($request->all(), [
                'idArchivo' => 'required',
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
            $id = $params['idArchivo'];
            $idSol = $params['idSolicitud'];
            $user = auth()->user();

            DB::beginTransaction();

            if (isset($request->NewFiles)) {
                $this->replaceFiles($id, $request->NewFiles, $idSol);
            } else {
                $response = [
                    'success' => true,
                    'results' => false,
                    'message' => 'No se envió ningún archivo',
                    'data' => [],
                ];
                return response()->json($response, 200);
            }
            $observadas = DB::table('solicitudes_archivos')
                ->where([
                    'idSolicitud' => $idSol,
                    'idEstatus' => 2,
                    'idPrograma' => 2,
                ])
                ->WhereNull('FechaElimino')
                ->first();
            if (!$observadas) {
                DB::table('solicitudes_calentadores')
                    ->where('id', $idSol)
                    ->update([
                        'idEstatusSolicitud' => 13,
                    ]);
            }
            DB::commit();
            $response = [
                'success' => true,
                'results' => true,
                'message' => 'Archivo remplazado con éxito',
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

    private function replaceFiles($id, $files, $idSol)
    {
        $user = auth()->user();
        $img = new Imagick();
        $width = 1920;
        $height = 1920;
        foreach ($files as $key => $file) {
            $oldFile = DB::table('solicitudes_archivos')
                ->where('id', $id)
                ->first();
            $originalName = $file->getClientOriginalName();
            $extension = explode('.', $originalName);
            $extension = $extension[count($extension) - 1];
            $uniqueName = uniqid() . '.' . $extension;
            $size = $file->getSize();
            $fileObject = [
                'idSolicitud' => intval($idSol),
                'idClasificacion' => intval($oldFile->idClasificacion),
                'idPrograma' => 2,
                'idEstatus' => 4,
                'NombreOriginal' => $originalName,
                'NombreSistema' => $uniqueName,
                'Tipo' => $this->getFileType($extension),
                'Extension' => $extension,
                'Tamanio' => $size,
                'idUsuarioCreo' => $user->id,
                'FechaCreo' => date('Y-m-d H:i:s'),
            ];

            if (
                in_array(mb_strtolower($extension, 'utf-8'), [
                    'png',
                    'jpg',
                    'jpeg',
                ])
            ) {
                //Ruta temporal para reducción de tamaño
                $file->move('subidos/tmp', $uniqueName);
                $img_tmp_path = sprintf('subidos/tmp/%s', $uniqueName);
                $img->readImage($img_tmp_path);
                $img->adaptiveResizeImage($width, $height);

                //Guardar en el nuevo storage
                $url_storage = Storage::disk('subidos')->path($uniqueName);
                $img->writeImage($url_storage);

                //Eliminar el archivo original después de guardar el archivo reducido
                File::delete($img_tmp_path);
            } else {
                Storage::disk('subidos')->put(
                    $uniqueName,
                    File::get($file->getRealPath()),
                    'public'
                );
            }

            //$file->move('subidos', $uniqueName);
            DB::table('solicitudes_archivos')
                ->where('id', $id)
                ->update([
                    'idUsuarioElimino' => $user->id,
                    'FechaElimino' => date('Y-m-d H:i:s'),
                ]);
            $idNuevo = DB::table('solicitudes_archivos')->insertGetId(
                $fileObject
            );
            DB::table('solicitudes_archivos_observaciones')
                ->where(['idArchivo' => $id])
                ->update(['idArchivo' => $idNuevo, 'Estatus' => 1]);
        }
    }

    function saveNewFiles(Request $request)
    {
        try {
            $v = Validator::make($request->all(), [
                'idSolicitud' => 'required',
                'NewFiles' => 'required',
                'newIdClasificacion' => 'required',
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
            $id = $params['idSolicitud'];
            $newClasificacion = isset($params['newIdClasificacion'])
                ? $params['newIdClasificacion']
                : [];
            foreach ($newClasificacion as $clasificacion) {
                $clasificacionRepetida = DB::table('solicitudes_archivos')
                    ->where([
                        'idSolicitud' => $id,
                        'idClasificacion' => $clasificacion,
                        'idPrograma' => 2,
                    ])
                    ->whereNull('FechaElimino')
                    ->first();

                if ($clasificacionRepetida) {
                    $cls = DB::table('solicitudes_archivos_clasificacion AS s')
                        ->select('s.Clasificacion')
                        ->where(['id' => $clasificacion])
                        ->first();
                    $response = [
                        'success' => true,
                        'results' => false,
                        'message' =>
                            'La solicitud ya cuenta con ' .
                            $cls->Clasificacion .
                            ', cambie la clasificación del archivo',
                        'data' => [],
                    ];
                    return response()->json($response, 200);
                }
            }

            $aprobadas = DB::table('solicitudes_archivos')
                ->where('idSolicitud', $id)
                ->where('idPrograma', 2)
                ->where('idEstatus', '<>', 3)
                ->WhereNull('FechaElimino')
                ->first();

            if (!$aprobadas) {
                DB::table('solicitudes_calentadores')
                    ->where('id', $id)
                    ->update([
                        'idEstatusSolicitud' => 1,
                    ]);
            }

            DB::beginTransaction();
            $this->createFiles($id, $request->NewFiles, $newClasificacion);
            DB::commit();

            if ($this->validateExpediente($id)) {
                DB::table('solicitudes_calentadores')
                    ->where('id', $id)
                    ->update([
                        'ExpedienteCompleto' => 1,
                    ]);
            }

            $response = [
                'success' => true,
                'results' => true,
                'message' => 'Se cargo el archivo correctamente',
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

    private function createFiles($id, $files, $idClasificacion)
    {
        $user = auth()->user();
        $img = new Imagick();
        $width = 1920;
        $height = 1920;
        foreach ($files as $key => $file) {
            $originalName = $file->getClientOriginalName();
            $extension = explode('.', $originalName);
            $extension = $extension[count($extension) - 1];
            $clasification = $idClasificacion[$key];
            $uniqueName = uniqid() . '.' . $extension;
            $size = $file->getSize();
            $fileObject = [
                'idSolicitud' => intval($id),
                'idClasificacion' => intval($clasification),
                'idPrograma' => 2,
                'idEstatus' => 1,
                'NombreOriginal' => $originalName,
                'NombreSistema' => $uniqueName,
                'Tipo' => $this->getFileType($extension),
                'Extension' => $extension,
                'Tamanio' => $size,
                'idUsuarioCreo' => $user->id,
                'FechaCreo' => date('Y-m-d H:i:s'),
            ];

            if (
                in_array(mb_strtolower($extension, 'utf-8'), [
                    'png',
                    'jpg',
                    'jpeg',
                ])
            ) {
                //Ruta temporal para reducción de tamaño
                $file->move('subidos/tmp', $uniqueName);
                $img_tmp_path = sprintf('subidos/tmp/%s', $uniqueName);
                $img->readImage($img_tmp_path);
                $img->adaptiveResizeImage($width, $height);

                //Guardar en el nuevo storage
                $url_storage = Storage::disk('subidos')->path($uniqueName);
                $img->writeImage($url_storage);

                //Eliminar el archivo original después de guardar el archivo reducido
                File::delete($img_tmp_path);
            } else {
                Storage::disk('subidos')->put(
                    $uniqueName,
                    File::get($file->getRealPath()),
                    'public'
                );
            }
            // $file->move('subidos', $uniqueName);
            DB::table('solicitudes_archivos')->insert($fileObject);
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

    public function create(Request $request)
    {
        try {
            $v = Validator::make($request->all(), [
                'CURP' => 'required',
                'Nombre' => 'required',
                'Paterno' => 'required',
                'Sexo' => 'required',
                'FechaINE' => 'required',
                //'idEntidadNacimiento' => 'required',
                'idMunicipio' => 'required',
                'idLocalidad' => 'required',
                'CP' => 'required',
                'Colonia' => 'required',
                'Calle' => 'required',
                'NumExt' => 'required',
                'Celular' => 'required',
                'Enlace' => 'required',
            ]);

            if ($v->fails()) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' =>
                        'Uno o más campos obligatorios están vacíos o no tiene el formato correcto',
                    'message' =>
                        'Uno o más campos obligatorios están vacíos o no tiene el formato correcto',
                ];
                return response()->json($response, 200);
            }

            $params = $request->all();            
            $user = auth()->user();
            $idAplicativo = '';
            $year_start = idate('Y', strtotime('first day of January', time()));

            $region = DB::table('et_cat_municipio')
                ->where('id', $params['idMunicipio'])
                ->first();
            if ($region != null) {
                $params['Region'] = $region->SubRegion;
            }

            $newClasificacion = isset($params['NewClasificacion'])
                ? $params['NewClasificacion']
                : [];
            $files = isset($params['NewFiles']) ? $params['NewFiles'] : [];
            $params['Correo'] = isset($params['Correo'])
                ? $params['Correo']
                : null;

            unset($params['Folio']);
            unset($params['NewClasificacion']);
            unset($params['NewFiles']);

            if (isset($params['FechaINE'])) {
                $fechaINE = intval($params['FechaINE']);
                if ($year_start > $fechaINE && $params['Ejercicio'] > $fechaINE ) {
                    $response = [
                        'success' => true,
                        'results' => false,
                        'errors' =>
                            'La vigencia de la Identificación Oficial no cumple con los requisitos',
                    ];
                    return response()->json($response, 200);
                }
            }

            if(!in_array($params['CURP'],['JIRF650621MGTMML06','AAGS870613HGTNRB05','GUSF860705MGTJNR05','TOGF680526HDFRNL05','LOBJ560319HGTPDS07','COJJ591105HDFRMS03','OIGY980113MGTRMR05','GAXM591130HGTRXG06','VIBG490328MZSLRR01','LORF570114MJCPML04','AAMG621126MGTLRD07','IATC840512HGTBRR00'])){
                $curpRegistrado = DB::table('solicitudes_calentadores')
                ->select(
                    DB::RAW('lpad( hex(id ), 6, 0 ) AS Folio'),
                    'CURP',
                    'Ejercicio'
                )
                ->where('CURP', $params['CURP'])
                ->whereNull('FechaElimino')
                //->whereRaw('YEAR(FechaCreo) = ' . $year_start)
                ->first();

            if ($curpRegistrado !== null) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' =>
                        'El Beneficiario con CURP ' .
                        $params['CURP'] .
                        ' ya se encuentra registrado en el padron con el Folio ' .
                        $curpRegistrado->Folio .
                        ', para el ejercicio ' .
                        $curpRegistrado->Ejercicio,
                    'message' =>
                        'El Beneficiario con CURP ' .
                        $params['CURP'] .
                        ' ya se encuentra registrado en el padron con el Folio ' .
                        $curpRegistrado->Folio .
                        ', para el ejercicio ' .
                        $curpRegistrado->Ejercicio,
                ];

                return response()->json($response, 200);
            }

            $curpRegistrado2 = DB::table('calentadores_solicitudes')
                ->select(DB::RAW('lpad( hex(id ), 6, 0 ) AS Folio'), 'CURP')
                ->where('CURP', $params['CURP'])
                ->whereNull('FechaElimino')
                //->whereRaw('YEAR(FechaCreo) = ' . $year_start)
                ->first();

            if ($curpRegistrado2 !== null) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' =>
                        'El Beneficiario con CURP ' .
                        $params['CURP'] .
                        ' fue registrado en el ejercicio 2022 con el Folio ' .
                        $curpRegistrado2->Folio,
                    'message' =>
                        'El Beneficiario con CURP ' .
                        $params['CURP'] .
                        ' fue registrado en el ejercicio 2022 con el Folio ' .
                        $curpRegistrado2->Folio,
                ];

                return response()->json($response, 200);
            }

            $isRegistered = $this->isRegistered($params['CURP']);
            if ($isRegistered) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'La CURP ya cuenta con una solicitud para calentador solar',
                    'message' =>
                        'El Beneficiario con CURP ' .
                        $params['CURP'] .
                        ' fue registrado con el Folio Impulso ' .
                        $isRegistered->FolioImpulso,
                ];
                return response()->json($response, 200);
            }

            }

            $params['idUsuarioCreo'] = $user->id;
            $params['FechaCreo'] = date('Y-m-d H:i:s');
            $params['idEntidadVive'] = 12;

            DB::beginTransaction();
            $id = DB::table('solicitudes_calentadores')->insertGetId($params);
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

            $curpValida = $this->isCurp($params['CURP']);
            $newRecord = [
                'FolioApi' => strtoupper($folioSolicitud),
                'FechaSolicitud' => $params['FechaSolicitud'],
                'FolioTarjetaImpulso' => isset($params['FolioTarjetaImpulso'])
                    ? $params['FolioTarjetaImpulso']
                    : null,
                'CURP' => $params['CURP'],
                'Nombre' => $params['Nombre'],
                'Paterno' => $params['Paterno'],
                'Materno' => isset($params['Materno'])
                    ? $params['Materno']
                    : null,
                'FechaNacimiento' => isset($params['FechaNacimiento'])
                    ? $params['FechaNacimiento']
                    : null,
                'Sexo' => isset($params['Sexo']) ? $params['Sexo'] : null,
                'EntidadNacimiento' => isset($params['idEntidadNacimiento'])
                    ? $params['idEntidadNacimiento']
                    : null,
                'RFC' => isset($params['RFC']) ? $params['RFC'] : null,
                'Celular' => isset($params['Celular'])
                    ? $params['Celular']
                    : null,
                'Telefono' => isset($params['Telefono'])
                    ? $params['Telefono']
                    : null,
                'TelRecados' => isset($params['TelRecados'])
                    ? $params['TelRecados']
                    : null,
                'Correo' => isset($params['Correo']) ? $params['Correo'] : null,
                'idMunicipio' => isset($params['idMunicipio'])
                    ? $params['idMunicipio']
                    : null,
                'idLocalidad' => isset($params['idLocalidad'])
                    ? $params['idLocalidad']
                    : null,
                'Colonia' => isset($params['Colonia'])
                    ? $params['Colonia']
                    : null,
                'Calle' => isset($params['Calle']) ? $params['Calle'] : null,
                'NumExt' => isset($params['NumExt']) ? $params['NumExt'] : null,
                'NumInt' => isset($params['NumInt']) ? $params['NumInt'] : null,
                'CP' => isset($params['CP']) ? $params['CP'] : null,
                'Referencias' => isset($params['Referencias'])
                    ? $params['Referencias']
                    : null,
                'CURPInformante' => isset($params['CURPTutor'])
                    ? $params['CURPTutor']
                    : null,
                'NombreInformante' => isset($params['NombreTutor'])
                    ? $params['NombreTutor']
                    : null,
                'PaternoInformante' => isset($params['PaternoTutor'])
                    ? $params['PaternoTutor']
                    : null,
                'MaternoInformante' => isset($params['MaternoTutor'])
                    ? $params['MaternoTutor']
                    : null,
                'TelefonoInformante' => isset($params['TelefonoTutor'])
                    ? $params['TelefonoTutor']
                    : null,
                'Enlace' => isset($params['Enlace']) ? $params['Enlace'] : null,
                'idEstatusSolicitud' => 1,
                'FormatoCURPCorrecto' => $curpValida,
                'idUsuarioCreo' => $user->id,
                'FechaCreo' => date('Y-m-d H:i:s'),
                'Ejercicio' => isset($params['Ejercicio'])
                    ? $params['Ejercicio']
                    : 2024,
            ];

            $tabla = 'solicitudes_calentadores_master_' . $params['Ejercicio'];

            DB::beginTransaction();
            $idImpulso = DB::table($tabla)->insertGetId($newRecord);
            DB::commit();

            $folioImpulso = '';
            $idImpulso > '9999'
                ? ($folioImpulso = 'S'.$params['Ejercicio'].'QC1417010')
                : ($folioImpulso = 'S'.$params['Ejercicio'].'QC14170100');

            $folioImpulso .= $idImpulso;

            DB::beginTransaction();
            DB::table($tabla)
                ->Where('id', $idImpulso)
                ->update([
                    'FolioImpulso' => $folioImpulso,
                ]);
            DB::table('solicitudes_calentadores')
                ->Where('id', $id)
                ->update(['FolioImpulso' => $folioImpulso]);
            DB::commit();

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

    function update(Request $request)
    {
        try {
            $v = Validator::make($request->all(), [
                'id' => 'required',
                'Nombre' => 'required',
                'Paterno' => 'required',
                'Sexo' => 'required',
                'CURP' => 'required',
                'idMunicipio' => 'required',
                'idLocalidad' => 'required',
                'CP' => 'required',
                'Colonia' => 'required',
                'Calle' => 'required',
                'NumExt' => 'required',
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
            $solicitud = DB::table('solicitudes_calentadores')
                ->select('solicitudes_calentadores.idEstatusSolicitud')
                ->where('solicitudes_calentadores.id', $params['id'])
                ->first();

            // if ($solicitud->idEstatusSolicitud == 14) {
            //     $response = [
            //         'success' => true,
            //         'results' => false,
            //         'errors' =>
            //             'La solicitud se encuentra aprobada por comité no se puede editar',
            //         'message' =>
            //             'La solicitud se encuentra aprobada por comité no se puede editar',
            //     ];
            //     return response()->json($response, 200);
            // }
            if (
                isset($params['idEstatusSolicitud']) &&
                $params['idEstatusSolicitud'] == 5
            ) {
                $expediente = DB::table('solicitudes_calentadores AS c')
                    ->Select(
                        'c.id',
                        'ine.idSolicitud AS ine',
                        'visita.idSolicitud AS visita',
                        'comp.idSolicitud AS comprobante',
                        'sol.idSolicitud AS solicitud',
                        'ev.idSolicitud AS evidencia'
                    )
                    ->LeftJoin(
                        DB::RAW(
                            '(SELECT idSolicitud FROM solicitudes_archivos WHERE FechaElimino IS NULL AND idPrograma = 2 AND idClasificacion = 1 AND idSolicitud = ' .
                                $params['id'] .
                                ') AS sol'
                        ),
                        'c.id',
                        'sol.idSolicitud'
                    )
                    ->LeftJoin(
                        DB::RAW(
                            '(SELECT idSolicitud FROM solicitudes_archivos WHERE FechaElimino IS NULL AND idPrograma = 2 AND idClasificacion = 2 AND idSolicitud = ' .
                                $params['id'] .
                                ') AS ine'
                        ),
                        'c.id',
                        'ine.idSolicitud'
                    )
                    ->LeftJoin(
                        DB::RAW(
                            '(SELECT idSolicitud FROM solicitudes_archivos WHERE FechaElimino IS NULL AND idPrograma = 2 AND idClasificacion = 3 AND idSolicitud = ' .
                                $params['id'] .
                                ') AS comp'
                        ),
                        'c.id',
                        'comp.idSolicitud'
                    )
                    ->LeftJoin(
                        DB::RAW(
                            '(SELECT idSolicitud FROM solicitudes_archivos WHERE FechaElimino IS NULL AND idPrograma = 2 AND idClasificacion = 5 AND idSolicitud = ' .
                                $params['id'] .
                                ') AS visita'
                        ),
                        'c.id',
                        'visita.idSolicitud'
                    )

                    ->LeftJoin(
                        DB::RAW(
                            '(SELECT idSolicitud FROM solicitudes_archivos WHERE FechaElimino IS NULL AND idPrograma = 2 AND idClasificacion = 6 AND idSolicitud = ' .
                                $params['id'] .
                                ') AS ev'
                        ),
                        'c.id',
                        'ev.idSolicitud'
                    )
                    ->Where('c.id', $params['id'])
                    ->WhereNull('FechaElimino')
                    ->first();
                if ($expediente) {
                    $message = '';
                    if (!$expediente->ine) {
                        $message = 'Falta cargar la INE';
                    } elseif (!$expediente->visita) {
                        $message = 'Falta cargar el formato de visita';
                    } elseif (!$expediente->comprobante) {
                        $message = 'Falta cargar el Comprobante de Domicilio';
                    } elseif (!$expediente->solicitud) {
                        $message = 'Falta cargar la Solicitud';
                    } elseif (!$expediente->evidencia) {
                        $message =
                            'Falta cargar al menos una Evidencia Fotográfica';
                    }

                    if ($message !== '') {
                        $response = [
                            'success' => true,
                            'results' => false,
                            'message' => $message,
                        ];
                        return response()->json($response, 200);
                    }
                } else {
                    $response = [
                        'success' => true,
                        'results' => false,
                        'errors' =>
                            'Debe cargar los archivos para validar la solicitud',
                    ];
                    return response()->json($response, 200);
                }

                $archivosObservados = DB::table('solicitudes_archivos AS a')
                    ->select('a.id')
                    ->whereNull('a.FechaElimino')
                    ->where([
                        'a.idSolicitud' => $params['id'],
                        'a.idEstatus' => 2,
                        'idPrograma' => 2,
                    ])
                    ->first();

                if ($archivosObservados) {
                    $response = [
                        'success' => true,
                        'results' => false,
                        'errors' =>
                            'La solicitud tiene un archivo observado, no puede ser validada',
                    ];
                    return response()->json($response, 200);
                }
            }
            $user = auth()->user();
            $params['idUsuarioActualizo'] = $user->id;
            $params['FechaActualizo'] = date('Y-m-d H:i:s');
            $id = $params['id'];
            unset($params['id']);
            unset($params['Folio']);
            unset($params['Ejercicio']);

            DB::table('solicitudes_calentadores')
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

    public function delete(Request $request)
    {
        try {
            $v = Validator::make($request->all(), [
                'id' => 'required',
            ]);
            if ($v->fails()) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' => 'No se envío la solicitud a eliminar',
                ];
                return response()->json($response, 200);
            }

            $params = $request->all();
            $id = $params['id'];
            $user = auth()->user();

            $solicitud = DB::table('solicitudes_calentadores AS c')
                ->select('c.id', 'c.idEstatusSolicitud', 'c.CURP')
                ->where('c.id', $id)
                ->first();
            if ($solicitud->idEstatusSolicitud === 14) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'message' =>
                        'No se puede eliminar la solicitud, se encuentra aprobada',
                    'data' => [],
                ];
                return response()->json($response, 200);
            }

            DB::beginTransaction();
            DB::table('solicitudes_archivos AS c')
                ->where('c.idSolicitud', $id)
                ->whereNull('FechaElimino')
                ->where('c.idPrograma', 2)
                ->update([
                    'idUsuarioElimino' => $user->id,
                    'FechaElimino' => date('Y-m-d H:i:s'),
                ]);
            DB::table('solicitudes_calentadores AS c')
                ->where('c.id', $id)
                ->update([
                    'idUsuarioElimino' => $user->id,
                    'FechaElimino' => date('Y-m-d H:i:s'),
                ]);

            DB::table('solicitudes_calentadores_master_2024 AS c')
                ->where('c.CURP', $solicitud->CURP)
                ->update([
                    'idUsuarioElimino' => $user->id,
                    'FechaElimino' => date('Y-m-d H:i:s'),
                ]);
            DB::table('solicitudes_calentadores_master_2023 AS c')
            ->where('c.CURP', $solicitud->CURP)
                ->update([
                    'idUsuarioElimino' => $user->id,
                    'FechaElimino' => date('Y-m-d H:i:s'),
                ]);
            DB::commit();
            $response = [
                'success' => true,
                'results' => true,
                'message' => 'Se elimino correctamente la solicitud',
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

    public function getPdf(Request $request)
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
        $params = $request->all();
        $id = $params['id'];
        try {
            $res = DB::table('solicitudes_calentadores as N')
                ->select(
                    DB::raw('LPAD(HEX(N.id),6,0) AS id'),
                    DB::RAw(
                        'CASE WHEN N.FechaSolicitud IS NOT NULL THEN date_format(N.FechaSolicitud,"%d/%m/%Y")
            ELSE "          " END AS FechaSolicitud'
                    ),
                    DB::raw(
                        'CONCAT_WS(" ",N.Nombre,N.Paterno,N.Materno) AS Nombre'
                    ),
                    'N.CURP',
                    'N.Sexo',
                    'N.Calle',
                    'N.NumExt',
                    'N.NumInt',
                    'N.CP',
                    'N.Colonia',
                    'L.Nombre AS Localidad',
                    'm.Nombre AS Municipio',
                    DB::raw(
                        'CONCAT_WS(" ",N.NombreTutor,N.PaternoTutor,N.MaternoTutor) AS Tutor'
                    ),
                    'p.Parentesco',
                    'N.CURPTutor',
                    'N.Telefono',
                    'N.Celular',
                    'N.Correo'
                )
                ->JOIN('et_cat_municipio as m', 'N.idMunicipio', '=', 'm.Id')
                ->JOIN(
                    'et_cat_localidad_2022 as L',
                    'N.idLocalidad',
                    '=',
                    'L.id'
                )
                ->LeftJoin(
                    'cat_parentesco_tutor AS p',
                    'p.id',
                    'N.idParentescoTutor'
                )
                ->where('N.id', $id)
                ->get();
            $calentadores = $res
                ->map(function ($x) {
                    $x = is_object($x) ? (array) $x : $x;
                    return $x;
                })
                ->toArray();

            $path = public_path() . '/subidos/' . $id . '.pdf';
            $pdf = \PDF::loadView('pdf_solicitud_c', compact('calentadores'));
            return $pdf->stream($id . '.pdf');
        } catch (QueryException $errors) {
            $response = [
                'success' => false,
                'results' => false,
                'total' => 0,
                'errors' => $errors->getMessage(),
                'message' => 'Ha ocurrido un error, consulte al administrador',
            ];

            return response()->json($response, 200);
        }
    }

    public function validateExpediente($id)
    {
        $expediente = DB::table('solicitudes_calentadores AS c')
            ->Select(
                'c.id',
                'solicitud.idSolicitud AS solicitud',
                'ine.idSolicitud AS ine',
                'comp.idSolicitud AS comprobante',
                'formato.idSolicitud AS visita',
                'foto.idSolicitud AS foto'
            )
            ->LeftJoin(
                DB::RAW(
                    '(SELECT idSolicitud FROM solicitudes_archivos WHERE idPrograma = 2 AND FechaElimino IS NULL AND idSolicitud = ' .
                        $id .
                        ' AND idClasificacion = 1 ) AS solicitud'
                ),
                'solicitud.idSolicitud',
                'c.id'
            )
            ->LeftJoin(
                DB::RAW(
                    '(SELECT idSolicitud FROM solicitudes_archivos WHERE idPrograma = 2 AND FechaElimino IS NULL AND idSolicitud = ' .
                        $id .
                        ' AND idClasificacion = 2 ) AS ine'
                ),
                'ine.idSolicitud',
                'c.id'
            )
            ->LeftJoin(
                DB::RAW(
                    '(SELECT idSolicitud FROM solicitudes_archivos WHERE idPrograma = 2 AND FechaElimino IS NULL AND idSolicitud = ' .
                        $id .
                        ' AND idClasificacion = 3 ) AS comp'
                ),
                'comp.idSolicitud',
                'c.id'
            )
            ->LeftJoin(
                DB::RAW(
                    '(SELECT idSolicitud FROM solicitudes_archivos WHERE idPrograma = 2 AND FechaElimino IS NULL AND idSolicitud = ' .
                        $id .
                        ' AND idClasificacion = 5 ) AS formato'
                ),
                'formato.idSolicitud',
                'c.id'
            )
            ->LeftJoin(
                DB::RAW(
                    '(SELECT idSolicitud FROM solicitudes_archivos WHERE idPrograma = 2 AND FechaElimino IS NULL AND idSolicitud = ' .
                        $id .
                        ' AND idClasificacion = 6 ) AS foto'
                ),
                'foto.idSolicitud',
                'c.id'
            )
            ->WhereNull('c.FechaElimino')
            ->Where('c.id', $id)
            ->first();

        $flag = true;
        foreach ($expediente as $file) {
            if (!$file) {
                $flag = false;
                break;
            }
        }

        $datosInformante = DB::table('solicitudes_calentadores')
            ->select('idParentescoTutor')
            ->whereNull('FechaElimino')
            ->Where('id', $id)
            ->first();

        if ($datosInformante->idParentescoTutor !== null) {
            $archivoInformante = DB::table('solicitudes_archivos')
                ->Select('id')
                ->Where([
                    'idPrograma' => 2,
                    'idSolicitud' => $id,
                    'idClasificacion' => 4,
                ])
                ->WhereNull('FechaElimino')
                ->first();

            if (!$archivoInformante) {
                $flag = false;
            }
        }

        return $flag;
    }

    public function cargaMasiva()
    {
        try {
            $pendientes = DB::table('carga_archivos_masivo_calentadores')
                ->whereNull('Cargado')
                ->get();
            if ($pendientes->count() > 0) {
                $pendientes->each(function ($item, $key) {
                    $curpRegistrada = DB::table('solicitudes_calentadores')
                        ->Select('id', 'CURP')
                        ->Where('CURP', $item->CURP)
                        ->first();
                    if ($curpRegistrada) {
                        $extension = explode('.', $item->NombreArchivo);
                        $valesArchivos = [
                            'idSolicitud' => $curpRegistrada->id,
                            'idClasificacion' => $item->Clasificacion,
                            'idPrograma' => 2,
                            'idEstatus' => 1,
                            'NombreOriginal' => $item->NombreArchivo,
                            'NombreSistema' => $item->NombreArchivo,
                            'Descripcion' => 'Carga masiva',
                            'Tipo' => 'image',
                            'Extension' => $extension[1],
                            'idUsuarioCreo' => 1,
                            'FechaCreo' => date('Y-m-d H:i:s'),
                        ];

                        DB::table('solicitudes_archivos')->insert(
                            $valesArchivos
                        );
                        DB::table('carga_archivos_masivo_calentadores')
                            ->where('id', $item->id)
                            ->update(['Cargado' => 1]);

                        if ($this->validateExpediente($curpRegistrada->id)) {
                            DB::table('solicitudes_calentadores')
                                ->where('id', $curpRegistrada->id)
                                ->update([
                                    'ExpedienteCompleto' => 1,
                                ]);
                        }
                    } else {
                        DB::table('carga_archivos_masivo_calentadores')
                            ->where('id', $item->id)
                            ->update(['Cargado' => 0]);
                    }
                });
                $response = [
                    'success' => true,
                    'results' => true,
                    'message' => 'Archivos cargados con éxito',
                ];
                return response()->json($response, 200);
            }
            $response = [
                'success' => true,
                'results' => true,
                'message' => 'No hay archivos pendientes de carga',
            ];
            return response()->json($response, 200);
        } catch (Exception $e) {
            $response = [
                'success' => false,
                'results' => false,
                'errors' => $e,
                'message' => 'Ha ocurrido un error, consulte al administrador',
            ];
            return response()->json($response, 200);
        }
    }

    public function checkFiles()
    {
        try {
            $pendientes = DB::table('solicitudes_calentadores')
                ->whereNull('FechaElimino')
                ->Where('ExpedienteCompleto', 0)
                ->get();
            if ($pendientes->count() > 0) {
                $pendientes->each(function ($item, $key) {
                    if ($this->validateExpediente($item->id)) {
                        DB::table('solicitudes_calentadores')
                            ->where('id', $item->id)
                            ->update([
                                'ExpedienteCompleto' => 1,
                            ]);
                    }
                });
                $response = [
                    'success' => true,
                    'results' => true,
                    'message' => 'Solicitudes Validadas con Éxito',
                ];
                return response()->json($response, 200);
            }
            $response = [
                'success' => true,
                'results' => true,
                'message' => 'No hay solicitudes pendientes de revisión',
            ];
            return response()->json($response, 200);
        } catch (Exception $e) {
            $response = [
                'success' => false,
                'results' => false,
                'errors' => $e,
                'message' => 'Ha ocurrido un error, consulte al administrador',
            ];
            return response()->json($response, 200);
        }
    }

    public function validateCURP(Request $request)
    {
        $v = Validator::make(
            $request->all(),
            [
                'CURP' => 'required|size:18',
            ],
            $messages = [
                'size' =>
                    'El campo :attribute debe ser una cadena de :size caractares.',
                'required' => 'El campo :attribute es obligatorio',
            ]
        );

        if ($v->fails()) {
            $response = [
                'success' => true,
                'results' => false,
                'message' => $v->errors(),
            ];
            return response()->json($response, 200);
        }

        $params = $request->only(['CURP']);
        $user = auth()->user();

        $isRegistered = $this->isRegistered($params['CURP']);

        if ($isRegistered) {
            $response = [
                'success' => true,
                'results' => false,
                'message' =>
                    'La CURP ya cuenta con una solicitud para calentador solar',
                'data' => $isRegistered,
            ];

            return response()->json($response, 200);
        } else {
            $curpValida = $this->isCurp($params['CURP']);

            $response = [
                'success' => true,
                'results' => true,
                'message' => 'CURP no registrada',
                'formatoCorrecto' => $curpValida,
            ];

            return response()->json($response, 200);
        }
    }

    public function register(Request $request)
    {
        $v = Validator::make(
            $request->all(),
            [
                'CURP' => 'required|size:18',
                'FechaSolicitud' => 'required|date_format:Y-m-d',
                'Nombre' => 'required|between:3,150',
                'Paterno' => 'required|between:3,150',
                'FolioApi' => 'max:6',
                'FolioTarjetaImpulso' => 'max:15',
                'Materno' => 'max:150',
                'Sexo' => 'max:1',
                'RFC' => 'max:13',
                'Celular' => 'max:10',
                'Telefono' => 'max:13',
                'TelRecados' => 'max:13',
                'Correo' => 'max:70',
                'Colonia' => 'max:150',
                'Calle' => 'max:120',
                'NumExt' => 'max:20',
                'NumInt' => 'max:20',
                'CP' => 'max:6',
                'CURPInformante' => 'max:18',
                'NombreInformante' => 'max:65',
                'PaternoInformante' => 'max:65',
                'MaternoInformante' => 'max:65',
                'TelefonoInformante' => 'max:10',
            ],
            $messages = [
                'size' =>
                    'El campo :attribute debe ser una cadena de :size caractares.',
                'required' => 'El campo :attribute es obligatorio',
                'between' =>
                    'El campo :attribute debe tener una longitud de entre :min y :max caracteres.',
                'date_format' =>
                    'El formato del campo :attribute es incorrecto debe enviarse como: aaaa-m-d',
                'max' =>
                    'El campo :attribute de tener una longitud máxima de :max caracteres.',
            ]
        );

        if ($v->fails()) {
            $response = [
                'success' => true,
                'results' => false,
                'message' => $v->errors(),
            ];
            return response()->json($response, 200);
        }

        $params = $request->all();
        $user = auth()->user();
        $isRegistered = $this->isRegistered($params['CURP']);
        if ($isRegistered) {
            $response = [
                'success' => true,
                'results' => false,
                'message' =>
                    'La CURP ya cuenta con una solicitud para calentador solar',
                'data' => $isRegistered,
            ];
            return response()->json($response, 200);
        } else {
            if (isset($params['idMunicipio'])) {
                $mun = DB::table('et_cat_muninicipio')
                    ->Select('id')
                    ->Where('id', $params['idMunicipio'])
                    ->first();
                if (!$mun) {
                    $response = [
                        'success' => true,
                        'results' => false,
                        'message' =>
                            'El municipio enviado no se encuentra en el catálogo',
                    ];
                    return response()->json($response, 200);
                }

                $loc = DB::table('et_cat_localidad_2022')
                    ->Select('id')
                    ->Where([
                        'id' => $params['idLocalidad'],
                    ])
                    ->first();
                if (!$loc) {
                    $response = [
                        'success' => true,
                        'results' => false,
                        'message' =>
                            'La localidad enviada no se encuentra en el catálogo',
                    ];
                    return response()->json($response, 200);
                }
            }

            $curpValida = $this->isCurp($params['CURP']);
            $newRecord = [
                'FolioApi' => isset($params['FolioApi'])
                    ? $params['FolioApi']
                    : null,
                'FechaSolicitud' => $params['FechaSolicitud'],
                'FolioTarjetaImpulso' => isset($params['FolioTarjetaImpulso'])
                    ? $params['FolioTarjetaImpulso']
                    : null,
                'CURP' => $params['CURP'],
                'Nombre' => $params['Nombre'],
                'Paterno' => $params['Paterno'],
                'Materno' => isset($params['Materno'])
                    ? $params['Materno']
                    : null,
                'FechaNacimiento' => isset($params['FechaNacimiento'])
                    ? $params['FechaNacimiento']
                    : null,
                'Sexo' => isset($params['Sexo']) ? $params['Sexo'] : null,
                'EntidadNacimiento' => isset($params['EntidadNacimiento'])
                    ? $params['EntidadNacimiento']
                    : null,
                'RFC' => isset($params['RFC']) ? $params['RFC'] : null,
                'Celular' => isset($params['Celular'])
                    ? $params['Celular']
                    : null,
                'Telefono' => isset($params['Telefono'])
                    ? $params['Telefono']
                    : null,
                'TelRecados' => isset($params['TelRecados'])
                    ? $params['TelRecados']
                    : null,
                'Correo' => isset($params['Correo']) ? $params['Correo'] : null,
                'idMunicipio' => isset($params['idMunicipio'])
                    ? $params['idMunicipio']
                    : null,
                'idLocalidad' => isset($params['idLocalidad'])
                    ? $params['idLocalidad']
                    : null,
                'Colonia' => isset($params['Colonia'])
                    ? $params['Colonia']
                    : null,
                'Calle' => isset($params['Calle']) ? $params['Calle'] : null,
                'NumExt' => isset($params['NumExt']) ? $params['NumExt'] : null,
                'NumInt' => isset($params['NumInt']) ? $params['NumInt'] : null,
                'CP' => isset($params['CP']) ? $params['CP'] : null,
                'Referencias' => isset($params['Referencias'])
                    ? $params['Referencias']
                    : null,
                'Latitud' => isset($params['Latitud'])
                    ? $params['Latitud']
                    : null,
                'Longitud' => isset($params['Longitud'])
                    ? $params['Longitud']
                    : null,
                'CveZap' => isset($params['CveZap']) ? $params['CveZap'] : null,
                'CURPInformante' => isset($params['CURPInformante'])
                    ? $params['CURPInformante']
                    : null,
                'NombreInformante' => isset($params['NombreInformante'])
                    ? $params['NombreInformante']
                    : null,
                'PaternoInformante' => isset($params['PaternoInformante'])
                    ? $params['PaternoInformante']
                    : null,
                'MaternoInformante' => isset($params['MaternoInformante'])
                    ? $params['MaternoInformante']
                    : null,
                'TelefonoInformante' => isset($params['TelefonoInformante'])
                    ? $params['TelefonoInformante']
                    : null,
                'Enlace' => isset($params['Enlace']) ? $params['Enlace'] : null,
                'idEstatusSolicitud' => 1,
                'FormatoCURPCorrecto' => $curpValida,
                'idUsuarioCreo' => $user->id,
                'FechaCreo' => date('Y-m-d H:i:s'),
            ];
            DB::beginTransaction();
            $idImpulso = DB::table(
                'solicitudes_calentadores_master'
            )->insertGetId($newRecord);
            DB::commit();
            $folioImpulso = 'S2023QC14170100' . $idImpulso;
            DB::beginTransaction();
            DB::table('solicitudes_calentadores_master')
                ->Where('id', $idImpulso)
                ->update([
                    'FolioImpulso' => $folioImpulso,
                ]);
            DB::commit();
            $response = [
                'success' => true,
                'results' => true,
                'message' => 'Solicitud registada con éxito',
                'folio' => $folioImpulso,
            ];
            return response()->json($response, 200);
        }
    }

    public function isRegistered($curp)
    {
        try {
            $res = DB::table('solicitudes_calentadores_master_2023 as c')
                ->select(
                    'c.id',
                    'c.FolioImpulso',
                    'c.FechaSolicitud',
                    'c.CURP',
                    'e.Estatus'
                )
                ->LeftJoin(
                    'solicitudes_status AS e',
                    'c.idEstatusSolicitud',
                    'e.id'
                )
                ->where('c.CURP', $curp)
                ->first();

            if ($res) {
                return $res;
            } else {
                $res = DB::table('solicitudes_calentadores_master_2024 as c')
                    ->select(
                        'c.id',
                        'c.FolioImpulso',
                        'c.FechaSolicitud',
                        'c.CURP',
                        'e.Estatus'
                    )
                    ->LeftJoin(
                        'solicitudes_status AS e',
                        'c.idEstatusSolicitud',
                        'e.id'
                    )
                    ->where('c.CURP', $curp)
                    ->WhereNull('c.FechaElimino')
                    ->first();

                if ($res) {
                    return $res;
                } else {
                    return null;
                }
            }
        } catch (QueryException $errors) {
            return null;
        }
    }

    public function getList(Request $request)
    {
        try {
            $params = $request->only(['filtered']);
            $user = auth()->user();
            $permisos = DB::table('users')
                ->Select('idTipoUser')
                ->Where('id', $user->id)
                ->first();

            if (!$permisos) {
                return [
                    'success' => true,
                    'results' => false,
                    'message' => 'No tiene acceso para ver la información',
                    'data' => [],
                ];
                return response()->json($response, 200);
            }

            if ($permisos->idTipoUser !== 10) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'message' =>
                        'No tiene autorización para ver la información',
                    'data' => [],
                ];
                return response()->json($response, 200);
            }

            $filtros = [
                'FolioImpulso',
                'FolioApi',
                'FechaSolicitud',
                'CURP',
                'Nombre',
                'Paterno',
                'Materno',
                'Region',
                'idMunicipio',
            ];

            if (isset($params['filtered'])) {
                foreach ($params['filtered'] as $filtro) {
                    if (!in_array($filtro['id'], $filtros)) {
                        $response = [
                            'success' => true,
                            'results' => false,
                            'message' =>
                                'Los campos de filtrado no son válidos',
                            'data' => [],
                        ];
                        return response()->json($response, 200);
                    }
                }
            }

            $tabla = 'solicitudes_calentadores AS c';

            $solicitudes = DB::table($tabla)
                ->SELECT(
                    DB::RAW('LPAD(HEX(c.id),6,0) AS FolioApi'),
                    'c.FolioImpulso',
                    'e.Estatus',
                    'c.FechaSolicitud',
                    'c.FolioTarjetaImpulso',
                    'c.Nombre',
                    'c.Paterno',
                    'c.Materno',
                    'c.FechaNacimiento',
                    'c.Sexo',
                    'c.CURP',
                    'm.SubRegion AS Region',
                    'm.Nombre As Municipio',
                    'l.Nombre AS Localidad',
                    'c.Colonia',
                    'c.Calle',
                    'c.NumExt',
                    'c.NumInt',
                    'c.Referencias',
                    'c.CP',
                    'c.Telefono',
                    'c.Celular',
                    'c.TelRecados',
                    'c.Correo',
                    'c.NombreTutor AS NombreInformante',
                    'c.PaternoTutor AS PaternoInformante',
                    'c.MaternoTutor AS MaternoInformante',
                    'c.CURPTutor AS CURPInformante',
                    DB::RAW(
                        "CONCAT_WS(' ',creadores.Nombre,creadores.Paterno,creadores.Materno) AS CreadoPor"
                    )
                )
                ->leftJoin(
                    'users AS creadores',
                    'creadores.id',
                    'c.idUsuarioCreo'
                )
                ->leftJoin(
                    'solicitudes_status AS e',
                    'e.id',
                    'c.idEstatusSolicitud'
                )
                ->JOIN('et_cat_municipio as m', 'm.id', 'c.idMunicipio')
                ->JOIN('et_cat_localidad_2022 as l', 'l.id', 'c.idLocalidad')
                ->whereNull('c.FechaElimino')
                ->Where('c.idEstatusSolicitud', 14);

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

                    if ($id == 'FechaSolicitud') {
                        $timestamp = strtotime($value);
                        $value = date('Y-m-d', $timestamp);
                    }

                    if ($id == 'FolioApi') {
                        $id = 'id';
                        $value = hexdec($value);
                    }

                    if ($id == 'Region') {
                        $municipios = DB::table('et_cat_municipio')
                            ->select('Id')
                            ->whereIN('SubRegion', $value)
                            ->get();
                        foreach ($municipios as $m) {
                            $municipioRegion[] = $m->Id;
                        }

                        $id = 'idMunicipio';
                        $value = $municipioRegion;
                    }
                    $id = 'c.' . $id;
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

            // dd(
            //     str_replace_array(
            //         '?',
            //         $solicitudes->getBindings(),
            //         $solicitudes->toSql()
            //     )
            // );

            $total = $solicitudes->count();
            $solicitudes = $solicitudes->orderby('c.id', 'asc')->get();

            $parameters_serializado = serialize($params);
            $filtro_usuario = VNegociosFiltros::where('idUser', '=', $user->id)
                ->where('api', '=', 'getCalentadores')
                ->first();

            if ($filtro_usuario) {
                $filtro_usuario->parameters = $parameters_serializado;
                $filtro_usuario->updated_at = time::now();
                $filtro_usuario->update();
            } else {
                $objeto_nuevo = new VNegociosFiltros();
                $objeto_nuevo->api = 'getCalentadores';
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
                    'data' => $array_res,
                ];
            }

            foreach ($solicitudes as $data) {
                // $files = DB::table('solicitudes_archivos AS a')
                //     ->Select('a.NombreSistema')
                //     ->WhereNULL('a.FechaElimino')
                //     ->Where('a.idSolicitud', $data->id)
                //     ->get();

                // if ($files->count() > 0) {
                //     $dataImg = [];
                //     foreach ($files as $f) {
                //         // $urlStorage = Storage::disk('subidos')->path(
                //         //     $f->NombreSistema
                //         // );
                //         $urlStorage =
                //             '/Users/diegolopez/Desktop/PruebaEnvioIne/' .
                //             $f->NombreSistema;
                //         $img2 = file_get_contents($urlStorage);
                //         $imgEncode = base64_encode($img2);
                //         $dataImg[] = $imgEncode;
                //     }
                //     $data->Files = $dataImg;
                // }
                $array_res[] = $data;
            }

            $response = [
                'success' => true,
                'results' => true,
                'data' => $array_res,
                'total' => $total,
            ];
            return response()->json($response, 200);
        } catch (QueryException $errors) {
            $response = [
                'success' => false,
                'results' => false,
                'total' => 0,
                'errors' => $errors,
                'message' => 'Ocurrio un error, consulte al administrador',
            ];
            return response()->json($response, 200);
        }
    }

    public function getFilesByFolioApi(Request $request)
    {
        $v = Validator::make(
            $request->all(),
            [
                'FolioApi' => 'required|size:6',
            ],
            $messages = [
                'size' =>
                    'El campo :attribute debe ser una cadena de :size caractares.',
                'required' => 'El campo :attribute es obligatorio',
            ]
        );

        if ($v->fails()) {
            $response = [
                'success' => true,
                'results' => false,
                'message' => $v->errors(),
            ];
            return response()->json($response, 200);
        }

        try {
            $params = $request->only(['FolioApi']);
            $user = auth()->user();
            $permisos = DB::table('users')
                ->Select('idTipoUser')
                ->Where('id', $user->id)
                ->first();

            if (!$permisos) {
                return [
                    'success' => true,
                    'results' => false,
                    'message' => 'No tiene acceso para ver la información',
                    'data' => [],
                ];
                return response()->json($response, 200);
            }

            if ($permisos->idTipoUser !== 10) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'message' =>
                        'No tiene autorización para ver la información',
                    'data' => [],
                ];
                return response()->json($response, 200);
            }

            $tabla = 'solicitudes_archivos AS a';
            try {
                $folioApi = hexdec($params['FolioApi']);
            } catch (Exception $e) {
                return response()->json([
                    'success' => true,
                    'results' => false,
                    'data' => [],
                    'message' => 'El folio ingresado no es válido.',
                ]);
            }

            $solicitud = DB::Table('solicitudes_calentadores')
                ->Select('idEstatusSolicitud')
                ->Where('id', $folioApi)
                ->WhereNull('FechaElimino')
                ->first();

            if (!$solicitud) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'message' => 'La solicitud no fue encontrada',
                    'data' => [],
                ];
                return response()->json($response, 200);
            }

            if ($solicitud->idEstatusSolicitud != 14) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'message' =>
                        'La solicitud aún no está marcada como aprobada por comité',
                    'data' => [],
                ];
                return response()->json($response, 200);
            }

            $archivos = DB::table($tabla)
                ->SELECT('c.Clasificacion AS Nombre', 'a.NombreSistema')
                ->JOIN(
                    'solicitudes_archivos_clasificacion as c',
                    'c.id',
                    'a.idClasificacion'
                )
                ->whereNull('a.FechaElimino')
                ->Where('a.idSolicitud', $folioApi);

            // dd(
            //     str_replace_array(
            //         '?',
            //         $solicitudes->getBindings(),
            //         $solicitudes->toSql()
            //     )
            // );

            $total = $archivos->count();
            $solicitudes = $archivos
                ->orderby('a.idClasificacion', 'asc')
                ->get();

            $array_res = [];

            if ($total == 0) {
                return [
                    'success' => true,
                    'results' => false,
                    'message' =>
                        'La solicitud consultada no fue encontrada o no tiene archivos',
                    'data' => $array_res,
                ];
            }

            foreach ($solicitudes as $data) {
                $urlStorage = Storage::disk('subidos')->path(
                    $data->NombreSistema
                );
                // $urlStorage =
                //     '/Users/diegolopez/Desktop/PruebaEnvioIne/' .
                //     $data->NombreSistema;

                $img2 = file_get_contents($urlStorage);
                $extension = explode('.', $data->NombreSistema);
                if (strtolower($extension[1]) == 'pdf') {
                    $imgEncode = base64_encode($img2);
                } else {
                    $imgEncode =
                        'data:image/' .
                        strtolower($extension['1']) .
                        ';base64,' .
                        base64_encode($img2);
                }

                $data->Archivo = $imgEncode;
                unset($data->NombreSistema);
            }

            $array_res[] = $solicitudes;
            $response = [
                'success' => true,
                'results' => true,
                'data' => $array_res,
                'totalArchivos' => $total,
            ];
            return response()->json($response, 200);
        } catch (QueryException $errors) {
            $response = [
                'success' => false,
                'results' => false,
                'totalArchivos' => 0,
                'errors' => $errors,
                'message' => 'Ocurrio un error, consulte al administrador',
            ];
            return response()->json($response, 200);
        }
    }

    public function getFilesByFolioImpulso(Request $request)
    {
        $v = Validator::make(
            $request->all(),
            [
                'FolioImpulso' => 'required|size:19',
            ],
            $messages = [
                'size' =>
                    'El campo :attribute debe ser una cadena de :size caractares.',
                'required' => 'El campo :attribute es obligatorio',
            ]
        );

        if ($v->fails()) {
            $response = [
                'success' => true,
                'results' => false,
                'message' => $v->errors(),
            ];
            return response()->json($response, 200);
        }

        try {
            $params = $request->only(['FolioImpulso']);

            if (!preg_match('/^S2023QC141701/', $params['FolioImpulso'])) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'message' => 'El folio impulso no es válido',
                ];
                return response()->json($response, 200);
            }

            $user = auth()->user();
            $permisos = DB::table('users')
                ->Select('idTipoUser')
                ->Where('id', $user->id)
                ->first();

            if (!$permisos) {
                return [
                    'success' => true,
                    'results' => false,
                    'message' => 'No tiene acceso para ver la información',
                    'data' => [],
                ];
                return response()->json($response, 200);
            }

            if ($permisos->idTipoUser !== 10) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'message' =>
                        'No tiene autorización para ver la información',
                    'data' => [],
                ];
                return response()->json($response, 200);
            }
            $tabla = 'solicitudes_archivos AS a';
            $folioApi = $params['FolioImpulso'];

            $solicitud = DB::Table('solicitudes_calentadores')
                ->Select('idEstatusSolicitud')
                ->Where('FolioImpulso', $folioApi)
                ->WhereNull('FechaElimino')
                ->first();

            if (!$solicitud) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'message' => 'La solicitud no fue encontrada',
                    'data' => [],
                ];
                return response()->json($response, 200);
            }

            if ($solicitud->idEstatusSolicitud != 14) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'message' =>
                        'La solicitud aún no está marcada como aprobada por comité',
                    'data' => [],
                ];
                return response()->json($response, 200);
            }

            $archivos = DB::Table($tabla)
                ->Select('c.Clasificacion AS Nombre', 'a.NombreSistema')
                ->Join(
                    'solicitudes_calentadores AS sol',
                    'sol.id',
                    'a.idSolicitud'
                )
                ->Join(
                    'solicitudes_archivos_clasificacion as c',
                    'c.id',
                    'a.idClasificacion'
                )
                ->whereNull('a.FechaElimino')
                ->Where('sol.FolioImpulso', $folioApi);

            // dd(
            //     str_replace_array(
            //         '?',
            //         $solicitudes->getBindings(),
            //         $solicitudes->toSql()
            //     )
            // );

            $total = $archivos->count();
            $solicitudes = $archivos
                ->orderby('a.idClasificacion', 'asc')
                ->get();

            $array_res = [];

            if ($total == 0) {
                return [
                    'success' => true,
                    'results' => false,
                    'message' =>
                        'La solicitud consultada no fue encontrada o no tiene archivos',
                    'data' => $array_res,
                ];
            }

            foreach ($solicitudes as $data) {
                $urlStorage = Storage::disk('subidos')->path(
                    $data->NombreSistema
                );
                // $urlStorage =
                //     '/Users/diegolopez/Desktop/PruebaEnvioIne/' .
                //     $data->NombreSistema;

                $img2 = file_get_contents($urlStorage);
                $extension = explode('.', $data->NombreSistema);
                if (strtolower($extension[1]) == 'pdf') {
                    $imgEncode = base64_encode($img2);
                } else {
                    $imgEncode =
                        'data:image/' .
                        strtolower($extension['1']) .
                        ';base64,' .
                        base64_encode($img2);
                }

                $data->Archivo = $imgEncode;
                unset($data->NombreSistema);
            }

            $array_res[] = $solicitudes;
            $response = [
                'success' => true,
                'results' => true,
                'data' => $array_res,
                'totalArchivos' => $total,
            ];
            return response()->json($response, 200);
        } catch (QueryException $errors) {
            $response = [
                'success' => false,
                'results' => false,
                'totalArchivos' => 0,
                'errors' => $errors,
                'message' => 'Ocurrio un error, consulte al administrador',
            ];
            return response()->json($response, 200);
        }
    }

    public function isCurp($string)
    {
        if (
            $string !== null &&
            trim($string) !== '' &&
            strlen($string) === 18
        ) {
            // By @JorhelR
            // TRANSFORMARMOS EN STRING EN MAYÚSCULAS RESPETANDO LAS Ñ PARA EVITAR ERRORES
            $string = mb_strtoupper($string, 'UTF-8');
            // EL REGEX POR @MARIANO
            $pattern =
                '/^([A-Z][AEIOUX][A-Z]{2}\d{2}(?:0[1-9]|1[0-2])(?:0[1-9]|[12]\d|3[01])[HM](?:AS|B[CS]|C[CLMSH]|D[FG]|G[TR]|HG|JC|M[CNS]|N[ETL]|OC|PL|Q[TR]|S[PLR]|T[CSL]|VZ|YN|ZS)[B-DF-HJ-NP-TV-Z]{3}[A-Z\d])(\d)$/';
            $validate = preg_match($pattern, $string, $match);
            if ($validate === false) {
                // SI EL STRING NO CUMPLE CON EL PATRÓN REQUERIDO RETORNA FALSE
                return false;
            }
            if (count($match) == 0) {
                return false;
            }
            // ASIGNAMOS VALOR DE 0 A 36 DIVIDIENDO EL STRING EN UN ARRAY
            $ind = preg_split(
                '//u',
                '0123456789ABCDEFGHIJKLMNÑOPQRSTUVWXYZ',
                null,
                PREG_SPLIT_NO_EMPTY
            );
            // REVERTIMOS EL CURP Y LE COLOCAMOS UN DÍGITO EXTRA PARA QUE EL VALOR DEL PRIMER CARACTER SEA 0 Y EL DEL PRIMER DIGITO DE LA CURP (INVERSA) SEA 1
            $vals = str_split(strrev($match[0] . '?'));
            // ELIMINAMOS EL CARACTER ADICIONAL Y EL PRIMER DIGITO DE LA CURP (INVERSA)
            unset($vals[0]);
            unset($vals[1]);
            $tempSum = 0;
            foreach ($vals as $v => $d) {
                // SE BUSCA EL DÍGITO DE LA CURP EN EL INDICE DE LETRAS Y SU CLAVE(VALOR) SE MULTIPLICA POR LA CLAVE(VALOR) DEL DÍGITO. TODO ESTO SE SUMA EN $tempSum
                $tempSum = array_search($d, $ind) * $v + $tempSum;
            }
            // ESTO ES DE @MARIANO NO SUPE QUE ERA
            $digit = 10 - ($tempSum % 10);
            // SI EL DIGITO CALCULADO ES 10 ENTONCES SE REASIGNA A 0
            $digit = $digit == 10 ? 0 : $digit;
            // SI EL DIGITO COINCIDE CON EL ÚLTIMO DÍGITO DE LA CURP RETORNA TRUE, DE LO CONTRARIO FALSE
            return $match[2] == $digit;
        } else {
            return false;
        }
    }

    public function validateCalentadores(Request $request)
    {
        try {
            $user = auth()->user();
            $tabla = 'solicitudes_calentadores AS c';
            $solicitudes = DB::table($tabla)
                ->SELECT(
                    'c.id',
                    DB::RAW('LPAD(HEX(c.id),6,0) AS FolioApi'),
                    'c.FechaSolicitud',
                    'c.FolioTarjetaImpulso',
                    'c.CURP',
                    'c.Nombre',
                    'c.Paterno',
                    'c.Materno',
                    'c.FechaNacimiento',
                    'c.Sexo',
                    'c.idEntidadNacimiento',
                    'c.RFC',
                    'c.Celular',
                    'c.Telefono',
                    'c.TelRecados',
                    'c.Correo',
                    DB::RAW('12 as idEntidad'),
                    'c.idMunicipio',
                    'c.idLocalidad',
                    'c.Colonia',
                    'c.Calle',
                    'c.NumExt',
                    'c.NumInt',
                    'c.CP',
                    'c.Referencias',
                    'c.CURPTutor AS CURPInformante',
                    'c.NombreTutor AS NombreInformante',
                    'c.PaternoTutor AS PaternoInformante',
                    'c.MaternoTutor AS MaternoInformante',
                    'c.Enlace',
                    DB::RAW('1 AS idEstatusSolicitud'),
                    DB::RAW('1 AS FormatoCURPCorrecto'),
                    'c.idUsuarioCreo',
                    'c.FechaCreo'
                )
                ->LeftJoin(
                    'solicitudes_calentadores_master_2023 AS m',
                    'm.CURP',
                    'c.CURP'
                )
                ->whereNull('c.FechaElimino')
                ->WhereNull('m.id');

            // dd(
            //     str_replace_array(
            //         '?',
            //         $solicitudes->getBindings(),
            //         $solicitudes->toSql()
            //     )
            // );

            $total = $solicitudes->count();
            $solicitudes = $solicitudes->get();
            if ($total == 0) {
                return [
                    'success' => true,
                    'results' => true,
                    'total' => $total,
                    'data' => $array_res,
                ];
            }

            $newRegisters = [];
            foreach ($solicitudes as $data) {
                $newRecord = [
                    'FolioApi' => $data->FolioApi,
                    'FechaSolicitud' => $data->FechaSolicitud,
                    'FolioTarjetaImpulso' => $data->FolioTarjetaImpulso,
                    'CURP' => $data->CURP,
                    'Nombre' => $data->Nombre,
                    'Paterno' => $data->Paterno,
                    'Materno' => $data->Materno,
                    'FechaNacimiento' => $data->FechaNacimiento,
                    'Sexo' => $data->Sexo,
                    'EntidadNacimiento' => $data->idEntidadNacimiento,
                    'RFC' => $data->RFC,
                    'Celular' => $data->Celular,
                    'Telefono' => $data->Telefono,
                    'TelRecados' => $data->TelRecados,
                    'Correo' => $data->Correo,
                    'idEntidad' => $data->idEntidad,
                    'idMunicipio' => $data->idMunicipio,
                    'idLocalidad' => $data->idLocalidad,
                    'Colonia' => $data->Colonia,
                    'Calle' => $data->Calle,
                    'NumExt' => $data->NumExt,
                    'NumInt' => $data->NumInt,
                    'CP' => $data->CP,
                    'Referencias' => $data->Referencias,
                    'CURPInformante' => $data->CURPInformante,
                    'NombreInformante' => $data->NombreInformante,
                    'PaternoInformante' => $data->PaternoInformante,
                    'MaternoInformante' => $data->MaternoInformante,
                    'Enlace' => $data->Enlace,
                    'idEstatusSolicitud' => 1,
                    'FormatoCURPCorrecto' => 1,
                    'idUsuarioCreo' => $data->idUsuarioCreo,
                    'FechaCreo' => $data->FechaCreo,
                ];

                DB::beginTransaction();
                $idImpulso = DB::table(
                    'solicitudes_calentadores_master_2023'
                )->insertGetId($newRecord);
                DB::commit();
                $folioImpulso = 'S2023QC14170100' . $idImpulso;
                DB::beginTransaction();
                DB::table('solicitudes_calentadores_master_2023')
                    ->Where('id', $idImpulso)
                    ->update([
                        'FolioImpulso' => $folioImpulso,
                    ]);
                DB::commit();
                DB::beginTransaction();
                DB::table('solicitudes_calentadores')
                    ->Where('id', $data->id)
                    ->update([
                        'FolioImpulso' => $folioImpulso,
                    ]);
                DB::commit();
            }

            $response = [
                'success' => true,
                'results' => true,
                'data' => 0,
                'totalArchivos' => $total,
            ];
            return response()->json($response, 200);
        } catch (QueryException $errors) {
            $response = [
                'success' => false,
                'results' => false,
                'totalArchivos' => 0,
                'errors' => $errors,
                'message' => 'Ocurrio un error, consulte al administrador',
            ];
            return response()->json($response, 200);
        }
    }

    public function getPdfByFolioApi(Request $request)
    {
        $v = Validator::make(
            $request->all(),
            [
                'FolioApi' => 'required|size:6',
            ],
            $messages = [
                'size' =>
                    'El campo :attribute debe ser una cadena de :size caractares.',
                'required' => 'El campo :attribute es obligatorio',
            ]
        );

        if ($v->fails()) {
            $response = [
                'success' => true,
                'results' => false,
                'message' => $v->errors(),
            ];
            return response()->json($response, 200);
        }
        $params = $request->only(['FolioApi']);
        $user = auth()->user();
        $permisos = DB::table('users')
            ->Select('idTipoUser')
            ->Where('id', $user->id)
            ->first();
        if (!$permisos) {
            return [
                'success' => true,
                'results' => false,
                'message' => 'No tiene acceso para ver la información',
                'data' => [],
            ];
            return response()->json($response, 200);
        }
        if ($permisos->idTipoUser !== 10) {
            $response = [
                'success' => true,
                'results' => false,
                'message' => 'No tiene autorización para ver la información',
                'data' => [],
            ];
            return response()->json($response, 200);
        }
        try {
            $folioApi = hexdec($params['FolioApi']);
        } catch (Exception $e) {
            return response()->json([
                'success' => true,
                'results' => false,
                'data' => [],
                'message' => 'El folio ingresado no es válido.',
            ]);
        }

        $solicitud = DB::Table('solicitudes_calentadores')
            ->Select('idEstatusSolicitud')
            ->Where('id', $folioApi)
            ->WhereNull('FechaElimino')
            ->first();

        if (!$solicitud) {
            $response = [
                'success' => true,
                'results' => false,
                'message' => 'La solicitud no fue encontrada',
                'data' => [],
            ];
            return response()->json($response, 200);
        }

        if ($solicitud->idEstatusSolicitud != 14) {
            $response = [
                'success' => true,
                'results' => false,
                'message' =>
                    'La solicitud aún no está marcada como aprobada por comité',
                'data' => [],
            ];
            return response()->json($response, 200);
        }

        try {
            $res = DB::table('solicitudes_calentadores as N')
                ->select(
                    'N.FolioImpulso AS id',
                    DB::RAw(
                        'CASE WHEN N.FechaSolicitud IS NOT NULL THEN date_format(N.FechaSolicitud,"%d/%m/%Y")
            ELSE "          " END AS FechaSolicitud'
                    ),
                    DB::raw(
                        'CONCAT_WS(" ",N.Nombre,N.Paterno,N.Materno) AS Nombre'
                    ),
                    'N.CURP',
                    'N.Sexo',
                    'N.Calle',
                    'N.NumExt',
                    'N.NumInt',
                    'N.CP',
                    'N.Colonia',
                    'L.Nombre AS Localidad',
                    'm.Nombre AS Municipio',
                    DB::raw(
                        'CONCAT_WS(" ",N.NombreTutor,N.PaternoTutor,N.MaternoTutor) AS Tutor'
                    ),
                    'p.Parentesco',
                    'N.CURPTutor',
                    'N.Telefono',
                    'N.Celular',
                    'N.Correo'
                )
                ->JOIN('et_cat_municipio as m', 'N.idMunicipio', '=', 'm.Id')
                ->JOIN(
                    'et_cat_localidad_2022 as L',
                    'N.idLocalidad',
                    '=',
                    'L.id'
                )
                ->LeftJoin(
                    'cat_parentesco_tutor AS p',
                    'p.id',
                    'N.idParentescoTutor'
                )
                ->where('N.id', $folioApi)
                ->get();

            if ($res->count() === 0) {
                return [
                    'success' => true,
                    'results' => false,
                    'message' => 'La solicitud consultada no fue encontrada.',
                    'data' => [],
                ];
            }

            $calentadores = $res
                ->map(function ($x) {
                    $x = is_object($x) ? (array) $x : $x;
                    return $x;
                })
                ->toArray();
            $pdf = \PDF::loadView('pdf_solicitud_c2', compact('calentadores'));
            return $pdf->download($folioApi . '.pdf');
        } catch (QueryException $errors) {
            $response = [
                'success' => false,
                'results' => false,
                'total' => 0,
                'errors' => $errors->getMessage(),
                'message' => 'Ha ocurrido un error, consulte al administrador',
            ];

            return response()->json($response, 200);
        }
    }

    public function getPdfByFolioImpulso(Request $request)
    {
        $v = Validator::make(
            $request->all(),
            [
                'FolioImpulso' => 'required|size:19',
            ],
            $messages = [
                'size' =>
                    'El campo :attribute debe ser una cadena de :size caractares.',
                'required' => 'El campo :attribute es obligatorio',
            ]
        );

        if ($v->fails()) {
            $response = [
                'success' => true,
                'results' => false,
                'message' => $v->errors(),
            ];
            return response()->json($response, 200);
        }
        $params = $request->only(['FolioImpulso']);
        if (!preg_match('/^S2023QC141701/', $params['FolioImpulso'])) {
            $response = [
                'success' => true,
                'results' => false,
                'message' => 'El folio impulso no es válido',
            ];
            return response()->json($response, 200);
        }
        $user = auth()->user();
        $permisos = DB::table('users')
            ->Select('idTipoUser')
            ->Where('id', $user->id)
            ->first();
        if (!$permisos) {
            return [
                'success' => true,
                'results' => false,
                'message' => 'No tiene acceso para ver la información',
                'data' => [],
            ];
            return response()->json($response, 200);
        }
        if ($permisos->idTipoUser !== 10) {
            $response = [
                'success' => true,
                'results' => false,
                'message' => 'No tiene autorización para ver la información',
                'data' => [],
            ];
            return response()->json($response, 200);
        }

        $folioApi = $params['FolioImpulso'];

        $solicitud = DB::Table('solicitudes_calentadores')
            ->Select('idEstatusSolicitud')
            ->Where('FolioImpulso', $folioApi)
            ->WhereNull('FechaElimino')
            ->first();

        if (!$solicitud) {
            $response = [
                'success' => true,
                'results' => false,
                'message' => 'La solicitud no fue encontrada',
                'data' => [],
            ];
            return response()->json($response, 200);
        }

        if ($solicitud->idEstatusSolicitud != 14) {
            $response = [
                'success' => true,
                'results' => false,
                'message' =>
                    'La solicitud aún no está marcada como aprobada por comité',
                'data' => [],
            ];
            return response()->json($response, 200);
        }

        try {
            $res = DB::table('solicitudes_calentadores as N')
                ->select(
                    'N.FolioImpulso AS id',
                    DB::RAw(
                        'CASE WHEN N.FechaSolicitud IS NOT NULL THEN date_format(N.FechaSolicitud,"%d/%m/%Y")
            ELSE "          " END AS FechaSolicitud'
                    ),
                    DB::raw(
                        'CONCAT_WS(" ",N.Nombre,N.Paterno,N.Materno) AS Nombre'
                    ),
                    'N.CURP',
                    'N.Sexo',
                    'N.Calle',
                    'N.NumExt',
                    'N.NumInt',
                    'N.CP',
                    'N.Colonia',
                    'L.Nombre AS Localidad',
                    'm.Nombre AS Municipio',
                    DB::raw(
                        'CONCAT_WS(" ",N.NombreTutor,N.PaternoTutor,N.MaternoTutor) AS Tutor'
                    ),
                    'p.Parentesco',
                    'N.CURPTutor',
                    'N.Telefono',
                    'N.Celular',
                    'N.Correo'
                )
                ->JOIN('et_cat_municipio as m', 'N.idMunicipio', '=', 'm.Id')
                ->JOIN(
                    'et_cat_localidad_2022 as L',
                    'N.idLocalidad',
                    '=',
                    'L.id'
                )
                ->LeftJoin(
                    'cat_parentesco_tutor AS p',
                    'p.id',
                    'N.idParentescoTutor'
                )
                ->where('N.FolioImpulso', $folioApi)
                ->get();

            if ($res->count() === 0) {
                return [
                    'success' => true,
                    'results' => false,
                    'message' => 'La solicitud consultada no fue encontrada.',
                    'data' => [],
                ];
            }

            $calentadores = $res
                ->map(function ($x) {
                    $x = is_object($x) ? (array) $x : $x;
                    return $x;
                })
                ->toArray();
            $pdf = \PDF::loadView('pdf_solicitud_c2', compact('calentadores'));
            return $pdf->download($folioApi . '.pdf');
        } catch (QueryException $errors) {
            $response = [
                'success' => false,
                'results' => false,
                'total' => 0,
                'errors' => $errors->getMessage(),
                'message' => 'Ha ocurrido un error, consulte al administrador',
            ];

            return response()->json($response, 200);
        }
    }

    public function validateInput($value): bool
    {
        if (gettype($value) === 'array') {
            foreach ($value as $v) {
                $containsSpecialChars = preg_match(
                    '@[' . preg_quote("'=%;-?!¡\"`+") . ']@',
                    $v
                );
            }
        } else {
            $containsSpecialChars = preg_match(
                '@[' . preg_quote("'=%;-?!¡\"`+") . ']@',
                $value
            );
        }
        return !$containsSpecialChars;
    }

    public function getExpedientesCS(Request $request)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', 1000);

        // $filePath =
        //     '/var/www/html/plataforma/apivales/public/subidos/calentadores/LEON.zip';
        // $fileName = 'LEON.zip';

        // $callback = function () use ($filePath, $fileName) {
        //     // Open output stream
        //     if ($file = fopen($filePath, 'rb')) {
        //         while (!feof($file) and connection_status() == 0) {
        //             print fread($file, 1024 * 1024);
        //             flush();
        //         }
        //         fclose($file);
        //     }
        // };

        // $headers = [
        //     'Content-Type' => 'application/octet-stream',
        //     'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        //     'Content-Transfer-Encoding' => 'Binary',
        //     'Pragma' => 'no-cache',
        // ];

        // return response()->streamDownload($callback, $fileName, $headers);

        if (!isset($request->idMunicipio) && !isset($request->CURP)) {
            return response()->json([
                'success' => true,
                'results' => false,
                'data' => [],
                'message' => 'Debe indicar el municipio o CURP a descargar.',
            ]);
        }

        if (isset($request->idMunicipio)) {
            if (
                !is_numeric($request->idMunicipio) ||
                $request->idMunicipio < 1 ||
                $request->idMunicipio > 46
            ) {
                return response()->json([
                    'success' => true,
                    'results' => false,
                    'data' => [],
                    'message' => 'El idMunicipio enviado no es válido',
                ]);
            }
        }

        if (isset($request->CURP)) {
            if (
                !$this->validateInput($request->CURP) ||
                strlen($request->CURP) != 18
            ) {
                return response()->json([
                    'success' => true,
                    'results' => false,
                    'data' => [],
                    'message' => 'El CURP enviado no es válido',
                ]);
            }
        }

        $user = auth()->user();

        $curps = DB::table('solicitudes_calentadores AS c')
            ->select('c.id', 'c.CURP')
            ->where('c.idMunicipio', $request->idMunicipio)
            ->whereIn('idEstatusSolicitud', [14, 15])
            ->WhereNull('FechaElimino');
        //->get();

        if (isset($request->idMunicipio)) {
            $mun = DB::table('et_cat_municipio')
                ->Where('id', $request->idMunicipio)
                ->first();
            $carpeta = $mun->Nombre;
            if (str_contains($carpeta, 'DOLORES')) {
                $carpeta = 'DOLORES HIDALGO';
            } elseif (str_contains($carpeta, 'SILAO')) {
                $carpeta = 'SILAO';
            }
            $curps = $curps
                ->where('c.idMunicipio', $request->idMunicipio)
                ->get();
        } else {
            $carpeta = $request->CURP;
            $curps = $curps->where('c.CURP', $request->CURP)->get();
        }

        if ($curps->count() > 0) {
            $path = public_path() . '/subidos/calentadores/' . $carpeta;
            if (
                \file_exists(
                    public_path('subidos/calentadores/' . $carpeta . '.zip')
                )
            ) {
                File::delete(
                    public_path('subidos/calentadores/' . $carpeta . '.zip')
                );
            }
            File::makeDirectory($path, $mode = 0777, true, true);

            foreach ($curps as $curp) {
                $archivos = DB::table('solicitudes_archivos AS a')
                    ->select('a.NombreSistema', 'ac.Clasificacion')
                    ->JOIN(
                        'solicitudes_archivos_clasificacion as ac',
                        'ac.id',
                        '=',
                        'a.idClasificacion'
                    )
                    ->Where(['a.idSolicitud' => $curp->id, 'a.idPrograma' => 2])
                    ->WhereNull('a.FechaElimino')
                    ->get();

                if ($archivos->count() > 0) {
                    if (isset($request->idMunicipio)) {
                        $pathCarpeta = $path . '/' . $curp->CURP;

                        File::makeDirectory(
                            $pathCarpeta,
                            $mode = 0777,
                            true,
                            true
                        );
                    } else {
                        $pathCarpeta = $path;
                    }

                    foreach ($archivos as $a) {
                        $rutaOrigen = Storage::disk('subidos')->path(
                            $a->NombreSistema
                        );

                        $rutaDestino =
                            $pathCarpeta .
                            '/' .
                            $a->Clasificacion .
                            '_' .
                            $a->NombreSistema;

                        copy($rutaOrigen, $rutaDestino);
                    }
                    $this->createZipEvidencia($curp->CURP, $carpeta);
                }
            }
            $this->createZipGeneral($carpeta);

            //     return response()->json([
            //         'success' => true,
            //         'results' => true,
            //         'message' => 'Expedientes generados.',
            //     ]);
            $filePath = $path . '.zip';
            $fileName = $carpeta . '.zip';

            $callback = function () use ($filePath, $fileName) {
                // Open output stream
                if ($file = fopen($filePath, 'rb')) {
                    while (!feof($file) and connection_status() == 0) {
                        print fread($file, 1024 * 1024);
                        flush();
                    }
                    fclose($file);
                }
            };

            $headers = [
                'Content-Type' => 'application/octet-stream',
                'Content-Disposition' =>
                    'attachment; filename="' . $fileName . '"',
                'Content-Transfer-Encoding' => 'Binary',
                'Pragma' => 'no-cache',
            ];

            return response()->streamDownload($callback, $fileName, $headers);

            // return response()->download($path . '.zip');
        } else {
            return response()->json([
                'success' => true,
                'results' => false,
                'data' => [],
                'message' => 'No se encontraron archivos para descargar.',
            ]);
        }
    }

    private function createZipEvidencia($curp, $carpeta)
    {
        try {
            $files = glob(
                public_path(
                    'subidos/calentadores/' . $carpeta . '/' . $curp . '/*'
                )
            );
            //$path = Storage::disk('subidos')->path($fileName);
            $path = public_path(
                'subidos/calentadores/' . $carpeta . '/' . $curp . '.zip'
            );
            Zipper::make($path)
                ->add($files)
                ->close();

            File::deleteDirectory(
                public_path('subidos/calentadores/' . $carpeta . '/' . $curp)
            );
            // if (\file_exists(public_path($carpeta))) {
            //     File::deleteDirectory(public_path($carpeta));
            // }
        } catch (Exception $e) {
            return false;
        }
    }

    private function createZipGeneral($carpeta)
    {
        try {
            $files = glob(
                public_path('subidos/calentadores/' . $carpeta . '/*')
            );
            $path = public_path('subidos/calentadores/' . $carpeta . '.zip');
            Zipper::make($path)
                ->add($files)
                ->close();
            File::deleteDirectory(
                public_path('subidos/calentadores/' . $carpeta)
            );
            // if (\file_exists(public_path($carpeta))) {
            //     File::deleteDirectory(public_path($carpeta));
            // }
        } catch (Exception $e) {
            return false;
        }
    }

    public function getFilesFromVentanilla()
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', 1000);
        $inicio = date('Y-m-d H:i:s');

        $user = auth()->user();

        $curps = DB::table('ExpedientesCSVentanilla AS c')
            ->select('c.id', 'c.CURP', 'c.FOLIO')
            ->Where('c.Descargado', 0)
            ->get();

        foreach ($curps as $curp) {
            try {
                $uniqueName = $curp->FOLIO . '.zip';
                $client = new Client();
                $requestD = $client->request(
                    'GET',
                    'https://qa-api-utils-ventanilla-impulso.guanajuato.gob.mx/zipgenerator/zip/generateZipByFolio?folio=' .
                        $curp->FOLIO,
                    [
                        'verify' => false,
                        'headers' => [
                            'content-type' => 'application/json',
                            'Accept' => 'application/json',
                        ],
                    ]
                );

                $f = $requestD->getBody()->getContents();
                $path =
                    public_path() .
                    '/subidos/ExpedientesVentanilla/' .
                    $uniqueName;
                File::put($path, $f);

                DB::table('ExpedientesCSVentanilla')
                    ->Where('FOLIO', $curp->FOLIO)
                    ->update(['Descargado' => 1, 'NombreZip' => $uniqueName]);
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                return response()->json([
                    'success' => false,
                    'results' => false,
                    'error' => 'Error al descargar archivos',
                ]);
                return false;
            }
        }

        return response()->json([
            'success' => true,
            'results' => true,
            'message' => 'Se obtuvieron los archivos con exito',
            'inicio' => $inicio,
            'fin' => date('Y-m-d H:i:s'),
        ]);
    }
}
