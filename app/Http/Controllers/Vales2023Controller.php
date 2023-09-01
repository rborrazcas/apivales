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
use DateTime;
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
use App\Exports\PadronBeneficiariosExport;

class Vales2023Controller extends Controller
{
    function getPermisos()
    {
        $user = auth()->user();

        $permisos = DB::table('users_menus')
            ->where(['idUser' => $user->id, 'idMenu' => '29'])
            ->first();
        if ($permisos !== null) {
            return $permisos;
        } else {
            return null;
        }
    }

    function getPermisosPrevalidacion()
    {
        $user = auth()->user();
        $permisos = DB::table('users_menus')
            ->where(['idUser' => $user->id, 'idMenu' => '30'])
            ->first();
        if ($permisos !== null) {
            return $permisos;
        } else {
            return null;
        }
    }

    function getClasificacionArchivos(Request $request)
    {
        try {
            $archivos_clasificacion = DB::table('vales_archivos_clasificacion')
                ->select('id AS value', 'Clasificacion AS label')
                ->orderby('Clasificacion')
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

    function getSemanas(Request $request)
    {
        try {
            $user = auth()->user();
            $permisos = DB::table('users_menus AS um')
                ->Select('Seguimiento', 'ViewAll')
                ->Where(['idUser' => $user->id, 'idMenu' => 31])
                ->first();

            $filtroPermisos = '';

            if ($permisos) {
                if ($permisos->ViewAll === 0) {
                    $userRegion = DB::table('users_region')
                        ->select('Region')
                        ->Where('idUser', $user->id)
                        ->first();
                    $filtroPermisos =
                        ' AND m.SubRegion = ' . $userRegion->Region . ' ';
                }
            } else {
                $response = [
                    'success' => false,
                    'results' => false,
                    'total' => 0,
                    'errors' => 'No tiene permisos en este menú',
                    'message' => 'No tiene permisos en este menú',
                ];

                return response()->json($response, 200);
            }

            $semanas = DB::Select(
                "
                SELECT
	                CONCAT( 'SEMANA ', WEEK ( s.created_at ), ' - REMESA ', s.Remesa ) AS label,
	                CONCAT( WEEK ( s.created_at ), '-', s.Remesa ) AS value
                FROM
	                vales_solicitudes AS s 
                    JOIN et_cat_municipio AS m ON s.idMunicipio = m.Id
                WHERE
	                s.Ejercicio = 2023 " .
                    $filtroPermisos .
                    " GROUP BY
	                CONCAT( 'SEMANA ', WEEK ( s.created_at ), ' - REMESA ', s.Remesa ),
	                CONCAT( WEEK ( s.created_at ), '-', s.Remesa );
                "
            );

            $response = [
                'success' => true,
                'results' => true,
                'data' => $semanas,
            ];
            return response()->json($response, 200);
        } catch (\QueryException $errors) {
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

    function getRemesas(Request $request)
    {
        try {
            $remesas = DB::Select(
                "
                SELECT
	                DISTINCT(RemesaSistema) AS value
                FROM
	                vales_remesas AS r 
                WHERE
	                r.Ejercicio = 2023 
                    AND Estatus = 1
                "
            );

            $response = [
                'success' => true,
                'results' => true,
                'data' => $remesas,
            ];
            return response()->json($response, 200);
        } catch (\QueryException $errors) {
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

    function getRemesasAll(Request $request)
    {
        try {
            $parameters = $request->all();
            $user = auth()->user();

            $ejercicio = 2022;
            if (isset($parameters['ejercicio'])) {
                $ejercicio = $parameters['ejercicio'];
            }

            if ($ejercicio == 2022) {
                $res = DB::table('vales_remesas')
                    ->distinct()
                    ->where('Ejercicio', $ejercicio);

                $res = $res->orderBy('RemesaSistema');

                $total = $res->count();
                $remesas = $res->get(['RemesaSistema']);
            } else {
                $remesas = DB::Select(
                    "
                    SELECT
                        Remesa AS RemesaSistema
                    FROM
                        vales_remesas AS r 
                    WHERE
                        r.Ejercicio = 2023                         
                    "
                );
            }

            $response = [
                'success' => true,
                'results' => true,
                'total' => 0,
                'data' => $remesas,
            ];
            return response()->json($response, 200);
        } catch (\QueryException $errors) {
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

    public function getDays(Request $request)
    {
        $v = Validator::make($request->all(), [
            'clave' => 'required',
        ]);
        if ($v->fails()) {
            $response = [
                'success' => true,
                'results' => false,
                'errors' => 'No se envió la semana a consultar',
            ];
            return response()->json($response, 200);
        }

        $params = $request->all();
        $clave = explode('-', $params['clave']);
        $user = auth()->user();
        $permisos = DB::table('users_menus AS um')
            ->Select('Seguimiento', 'ViewAll')
            ->Where(['idUser' => $user->id, 'idMenu' => 31])
            ->first();

        $filtroPermisos = '';

        if ($permisos) {
            if ($permisos->ViewAll === 0) {
                $userRegion = DB::table('users_region')
                    ->select('Region')
                    ->Where('idUser', $user->id)
                    ->first();
                $filtroPermisos =
                    ' AND m.SubRegion = ' . $userRegion->Region . ' ';
            }
        } else {
            $response = [
                'success' => false,
                'results' => false,
                'total' => 0,
                'errors' => 'No tiene permisos en este menú',
                'message' => 'No tiene permisos en este menú',
            ];

            return response()->json($response, 200);
        }

        try {
            $dias = DB::Select(
                "
                    SELECT
	                    DATE( s.created_at ) AS value 
                    FROM
	                    vales_solicitudes AS s 
                        JOIN et_cat_municipio AS m ON m.Id = s.idMunicipio
                    WHERE
	                    WEEK ( s.created_at ) = " .
                    $clave[0] .
                    "
                        AND Remesa = '" .
                    $clave[1] .
                    "'" .
                    $filtroPermisos .
                    "   
                    GROUP BY
	                    DATE( s.created_at ) 
                    ORDER BY
	                    value
                "
            );

            $response = [
                'success' => true,
                'results' => true,
                'data' => $dias,
            ];
            return response()->json($response, 200);
        } catch (\QueryException $errors) {
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

    public function getPineo(Request $request)
    {
        $v = Validator::make($request->all(), [
            'semana' => 'required',
        ]);
        if ($v->fails()) {
            $response = [
                'success' => true,
                'results' => false,
                'errors' => 'No se envió la semana a consultar',
            ];
            return response()->json($response, 200);
        }

        $params = $request->all();
        $clave = explode('-', $params['semana']);
        $remesa = $clave[1];

        $user = auth()->user();
        $permisos = DB::table('users_menus AS um')
            ->Select('Seguimiento', 'ViewAll')
            ->Where(['idUser' => $user->id, 'idMenu' => 31])
            ->first();

        $filtroPermisos = '';

        if ($permisos) {
            if ($permisos->ViewAll === 0) {
                $userRegion = DB::table('users_region')
                    ->select('Region')
                    ->Where('idUser', $user->id)
                    ->first();
                $filtroPermisos =
                    ' AND m.SubRegion = ' . $userRegion->Region . ' ';
            }
        } else {
            $response = [
                'success' => false,
                'results' => false,
                'total' => 0,
                'errors' => 'No tiene permisos en este menú',
                'message' => 'No tiene permisos en este menú',
            ];

            return response()->json($response, 200);
        }

        try {
            $dias = DB::Select(
                "
                    SELECT
                        DATE( s.created_at ) AS value 
                    FROM
                        vales_solicitudes AS s 
                        JOIN et_cat_municipio AS m ON s.idMunicipio = m.Id
                    WHERE
                        WEEK ( s.created_at ) = " .
                    $clave[0] .
                    "
                        AND Remesa = '" .
                    $remesa .
                    "' " .
                    $filtroPermisos .
                    "   
                    GROUP BY
                        DATE( s.created_at ) 
                    ORDER BY
                        value
                "
            );

            $dataWeek = [];

            foreach ($dias as $dia) {
                $data = DB::Select(
                    "
                        SELECT 
                            HOUR( s.created_at ) AS Hora,
                            COUNT( s.IdSolicitud ) AS Total 
                        FROM
                            vales_solicitudes AS s 
                            JOIN et_cat_municipio AS m ON s.idMunicipio = m.Id
                        WHERE
                            s.Ejercicio = 2023
                            AND s.Remesa = '" .
                        $remesa .
                        "'  AND DATE(s.created_at) = '" .
                        $dia->value .
                        "' " .
                        $filtroPermisos .
                        "GROUP BY
                            HOUR ( s.created_at );
                    "
                );

                $dataTotal = DB::Select(
                    "
                        SELECT
                            COUNT(*) AS Total
                        FROM
                            vales_solicitudes AS s
                            JOIN et_cat_municipio AS m ON s.idMunicipio = m.Id
                        WHERE
                            s.Ejercicio = 2023
                            AND s.Remesa = '" .
                        $remesa .
                        "'  AND DATE(s.created_at) = '" .
                        $dia->value .
                        "' " .
                        $filtroPermisos .
                        ";
                    "
                );

                $dataWeek[] = [
                    'dia' => $dia->value,
                    'total' => $dataTotal ? $dataTotal[0]->Total : 0,
                    'data' => $data,
                ];
            }
            // if ($params['vistaPor'] == 2) {
            //     $data = DB::Select(
            //         "
            //             SELECT
            //                 HOUR( s.created_at ) AS Hora,
            //                 COUNT( s.IdSolicitud ) AS Total
            //             FROM
            //                 vales_solicitudes AS s
            //             WHERE
            //                 s.Ejercicio = 2023
            //                 AND s.Remesa = '" .
            //             $remesa .
            //             "'  AND DATE(s.created_at) = '" .
            //             $params['fecha'] .
            //             "' GROUP BY
            //                 HOUR ( s.created_at );
            //         "
            //     );
            // } else {
            //     $data = DB::Select(
            //         "
            //             SELECT
            //                 DATE( s.created_at ) AS Fecha,
            //                 COUNT( s.IdSolicitud ) AS Total
            //             FROM
            //                 vales_solicitudes AS s
            //             WHERE
            //                 s.Ejercicio = 2023
            //                 AND s.Remesa = '" .
            //             $remesa .
            //             "'  AND WEEK(s.created_at) = '" .
            //             $clave[0] .
            //             "' GROUP BY
            //                 DATE( s.created_at );
            //         "
            //     );
            // }
            $response = [
                'success' => true,
                'results' => true,
                'data' => $dataWeek,
            ];
            return response()->json($response, 200);
        } catch (\QueryException $errors) {
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

    public function getPineoUser(Request $request)
    {
        $v = Validator::make($request->all(), [
            'semana' => 'required',
            'fecha' => 'required',
        ]);
        if ($v->fails()) {
            $response = [
                'success' => true,
                'results' => false,
                'errors' => 'No se envió la semana a consultar',
            ];
            return response()->json($response, 200);
        }

        $params = $request->all();
        $clave = explode('-', $params['semana']);
        $remesa = $clave[1];
        $user = auth()->user();
        $permisos = DB::table('users_menus AS um')
            ->Select('Seguimiento', 'ViewAll')
            ->Where(['idUser' => $user->id, 'idMenu' => 31])
            ->first();

        $filtroPermisos = '';

        if ($permisos) {
            if ($permisos->ViewAll === 0) {
                $userRegion = DB::table('users_region')
                    ->select('Region')
                    ->Where('idUser', $user->id)
                    ->first();
                $filtroPermisos =
                    ' AND m.SubRegion = ' . $userRegion->Region . ' ';
            }
        } else {
            $response = [
                'success' => false,
                'results' => false,
                'total' => 0,
                'errors' => 'No tiene permisos en este menú',
                'message' => 'No tiene permisos en este menú',
            ];

            return response()->json($response, 200);
        }

        try {
            $data = DB::Select(
                "
                    SELECT
	                    CONCAT_WS( ' ', u.Nombre, u.Paterno, u.Materno ) AS User,
	                    COUNT( s.IdSolicitud ) AS Total 
                    FROM
	                    vales_solicitudes AS s
	                    JOIN users AS u ON s.UserCreated = u.id 
                        JOIN et_cat_municipio AS m ON m.Id = s.idMunicipio
                    WHERE
	                    s.Ejercicio = 2023 
	                    AND s.Remesa = '" .
                    $remesa .
                    "' AND DATE( s.created_at ) = '" .
                    $params['fecha'] .
                    "' " .
                    $filtroPermisos .
                    "
                    GROUP BY
	                    s.UserCreated
                        ORDER BY Total DESC
                "
            );

            $response = [
                'success' => true,
                'results' => true,
                'data' => $data,
            ];
            return response()->json($response, 200);
        } catch (\QueryException $errors) {
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

    public function getPineoRegionMunicipio(Request $request)
    {
        $v = Validator::make($request->all(), [
            'remesa' => 'required',
            'region' => 'required',
        ]);
        if ($v->fails()) {
            $response = [
                'success' => true,
                'results' => false,
                'errors' => 'No se enviaron los datos a consultar',
            ];
            return response()->json($response, 200);
        }

        $params = $request->all();
        $user = auth()->user();
        $permisos = DB::table('users_menus AS um')
            ->Select('Seguimiento', 'ViewAll')
            ->Where(['idUser' => $user->id, 'idMenu' => 31])
            ->first();

        $filtroPermisos = '';

        if ($permisos) {
            if ($permisos->ViewAll === 0) {
                $userRegion = DB::table('users_region')
                    ->select('Region')
                    ->Where('idUser', $user->id)
                    ->first();
                $filtroPermisos =
                    ' AND m.SubRegion = ' . $userRegion->Region . ' ';
                $params['region'] = $userRegion->Region;
            }
        } else {
            $response = [
                'success' => false,
                'results' => false,
                'total' => 0,
                'errors' => 'No tiene permisos en este menú',
                'message' => 'No tiene permisos en este menú',
            ];

            return response()->json($response, 200);
        }

        try {
            $dataRegion = DB::Select(
                "
                    SELECT 
                        m.SubRegion AS Region,
                        COUNT( s.idSolicitud ) AS Total 
                    FROM
                        vales AS v
                        LEFT JOIN vales_solicitudes AS s ON v.id = s.idSolicitud
                        JOIN et_cat_municipio AS m ON v.idMunicipio = m.id 
                        JOIN vales_remesas AS r ON v.Remesa = r.Remesa
                    WHERE
                            r.RemesaSistema = '" .
                    $params['remesa'] .
                    "' " .
                    $filtroPermisos .
                    "
                    GROUP BY
                        m.SubRegion;
                "
            );

            $dataMetasRegion = DB::Select(
                "
                    SELECT 
                        m.SubRegion AS Region,
                        COUNT( v.id ) AS Total 
                    FROM
                        vales AS v
                        JOIN et_cat_municipio AS m ON v.idMunicipio = m.id 
                        JOIN vales_remesas AS r ON v.Remesa = r.Remesa
                    WHERE
                            r.RemesaSistema = '" .
                    $params['remesa'] .
                    "' " .
                    $filtroPermisos .
                    "
                    GROUP BY
                        m.SubRegion;
                "
            );

            $dataMunicipio = DB::Select(
                "
                    SELECT 
                        m.Nombre AS Municipio,
                        COUNT( s.idSolicitud ) AS Total 
                    FROM
                        vales AS v
                        LEFT JOIN vales_solicitudes AS s ON v.id = s.idSolicitud
                        JOIN et_cat_municipio AS m ON v.idMunicipio = m.id 
                        JOIN vales_remesas AS r ON v.Remesa = r.Remesa
                    WHERE
                        r.RemesaSistema = '" .
                    $params['remesa'] .
                    "' AND m.Subregion = " .
                    $params['region'] .
                    ' ' .
                    $filtroPermisos .
                    "
                    GROUP BY
                        m.Nombre;
                "
            );

            $dataMetasMunicipio = DB::Select(
                "
                    SELECT 
                        m.Nombre AS Municipio,
                        COUNT( v.id ) AS Total 
                    FROM
                        vales AS v
                        JOIN et_cat_municipio AS m ON v.idMunicipio = m.id 
                        JOIN vales_remesas AS r ON v.Remesa = r.Remesa
                        WHERE
                        r.RemesaSistema = '" .
                    $params['remesa'] .
                    "' AND m.Subregion = " .
                    $params['region'] .
                    ' ' .
                    $filtroPermisos .
                    " 
                    GROUP BY
                        m.Nombre;
                "
            );

            $dataCveInterventor = DB::Select(
                "
                    SELECT
	                    g.Remesa,
	                    g.CveInterventor,
	                    COUNT( v.id ) AS Meta,
	                    COUNT( s.id ) AS Pineados,
	                    COUNT( v.id ) - COUNT( s.id ) AS Pendientes 
                    FROM
	                    vales_grupos AS g
	                    JOIN vales_remesas AS r ON g.Remesa = r.Remesa
	                    JOIN vales AS v ON g.id = v.idGrupo
	                    LEFT JOIN vales_solicitudes AS s ON s.idSolicitud = v.id 
                    WHERE
	                    r.RemesaSistema = '" .
                    $params['remesa'] .
                    "' " .
                    ' AND g.idMunicipio IN ( SELECT id FROM et_cat_municipio WHERE SubRegion = ' .
                    $params['region'] .
                    " ) 
                    GROUP BY
	                    g.Remesa,
	                    g.CveInterventor 
                    ORDER BY
	                    Pendientes DESC,
	                    Meta DESC
	                    LIMIT 5
                "
            );

            $response = [
                'success' => true,
                'results' => true,
                'data' => [
                    'region' => $dataRegion,
                    'metasRegion' => $dataMetasRegion,
                    'municipio' => $dataMunicipio,
                    'metasMunicipio' => $dataMetasMunicipio,
                    'cveInterventor' => $dataCveInterventor,
                ],
            ];
            return response()->json($response, 200);
        } catch (\QueryException $errors) {
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
    //Aqui
    function getMunicipios(Request $request)
    {
        $user = auth()->user();
        try {
            $municipios = DB::table('et_cat_municipio')->select(
                'id',
                'Nombre',
                'Region',
                'SubRegion'
            );

            $permisos = $this->getPermisos();
            if ($permisos !== null) {
                $region = DB::table('users_region')
                    ->selectRaw('Region')
                    ->where('idUser', $user->id)
                    ->first();

                if ($region !== null) {
                    $municipios->where('SubRegion', $region->Region);
                }
            }

            $municipios->orderby('Nombre');

            $response = [
                'success' => true,
                'results' => true,
                'data' => $municipios->get(),
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
            $archivos2 = DB::table('vales_archivos AS a')
                ->select(
                    'a.id',
                    'a.idClasificacion',
                    'c.Clasificacion',
                    'a.NombreOriginal AS name',
                    'a.NombreSistema',
                    'a.Tipo AS type'
                )
                ->Join(
                    'vales_archivos_clasificacion AS c',
                    'c.id',
                    'a.idClasificacion'
                )
                ->where('a.idSolicitud', $id)
                ->whereRaw('a.FechaElimino IS NULL')
                ->get();
            $archivosClasificacion = array_map(function ($o) {
                return $o->idClasificacion;
            }, $archivos2->toArray());
            $archivos = array_map(function ($o) {
                $o->ruta =
                    'https://apivales.apisedeshu.com/Remesa01/' .
                    $o->NombreSistema;
                //$o->ruta = Storage::disk('expedientes')->url($o->NombreSistema);
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

    function getSolicitudes2023(Request $request)
    {
        try {
            $v = Validator::make($request->all(), [
                'page' => 'required',
                'pageSize' => 'required',
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
            $parameters_serializado = serialize($params);

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

            $viewall = $permisos->ViewAll;

            $solicitudes = DB::table('vales as v')

                ->Select(
                    'v.id',
                    DB::RAW('lpad(hex(v.id),6,0) AS FolioSolicitud'),
                    'v.FechaSolicitud',
                    'r.Remesa',
                    'v.Nombre',
                    'v.Paterno',
                    'v.Materno',
                    'v.CURP',
                    'v.Sexo',
                    'v.FechaIne',
                    'm.SubRegion AS Region',
                    'm.Nombre AS Municipio',
                    'l.Nombre AS Localidad',
                    'v.Calle',
                    'v.Calle',
                    'v.Colonia',
                    'v.NumExt',
                    'v.NumInt',
                    'v.CP',
                    'v.FolioTarjetaContigoSi',
                    'i.Incidencia',
                    DB::Raw(
                        'CASE WHEN v.isEntregado = 1 THEN "SI" ELSE "NO" END AS Entregado'
                    ),
                    'v.entrega_at AS FechaEntrega',
                    'v.TelFijo',
                    'v.TelCelular',
                    'v.ResponsableEntrega AS Responsable'
                )
                ->JOIN('vales_remesas AS r', 'v.Remesa', 'r.Remesa')
                ->JOIN('et_cat_municipio AS m', 'm.id', 'v.idMunicipio')
                ->JOIN('et_cat_localidad_2022 AS l', 'l.id', 'v.idLocalidad')
                ->LEFTJOIN('vales_incidencias AS i', 'i.id', 'v.idIncidencia')
                ->Where('v.Ejercicio', 2023);

            if ($viewall < 1) {
                $region = DB::table('users_region')
                    ->selectRaw('Region')
                    ->where('idUser', $user->id)
                    ->first();

                if ($region === null) {
                    $response = [
                        'success' => true,
                        'results' => false,
                        'total' => 0,
                        'message' => 'No tiene region asignada',
                    ];

                    return response()->json($response, 200);
                }

                $solicitudes = $solicitudes->where('m.Region', $region->Region);
            }

            $filterQuery = '';
            $municipioRegion = [];
            $mun = [];

            if (isset($params['filtered']) && count($params['filtered']) > 0) {
                $filtersCedulas = ['.Folio'];
                $filtersRemesas = ['.Remesa'];
                foreach ($params['filtered'] as $filtro) {
                    if ($filterQuery != '') {
                        $filterQuery .= ' AND ';
                    }
                    $id = $filtro['id'];
                    $value = $filtro['value'];

                    if ($id == '.id') {
                        $value = hexdec($value);
                    }

                    if ($id == '.isEntregado' && $value == 0) {
                        if ($filterQuery != '') {
                            $filterQuery .= ' Devuelto = 0 AND';
                        } else {
                            $filterQuery = 'Devuelto = 0 AND ';
                        }
                    }

                    if ($id == 'region') {
                        $municipios = DB::table('et_cat_municipio')
                            ->select('id')
                            ->whereIN('SubRegion', $value)
                            ->get();
                        foreach ($municipios as $m) {
                            $municipioRegion[] = "'" . $m->id . "'";
                        }

                        $id = '.idMunicipio';
                        $value = $municipioRegion;
                    }

                    if (in_array($id, $filtersCedulas)) {
                        $id = 'c' . $id;
                    } elseif (in_array($id, $filtersRemesas)) {
                        $id = 'r.Remesa';
                    } else {
                        $id = 'v' . $id;
                    }

                    switch (gettype($value)) {
                        case 'string':
                            $filterQuery .= " $id LIKE '%$value%' ";
                            break;
                        case 'array':
                            $colonDividedValue = implode(', ', $value);
                            $filterQuery .= " $id IN ($colonDividedValue) ";
                            break;
                        case 'boolean':
                            $filterQuery .= " $id <> 1 ";
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

            $page = $params['page'];
            $pageSize = $params['pageSize'];

            $startIndex = $page * $pageSize;

            $total = $solicitudes->count();
            $solicitudes = $solicitudes
                ->OrderBy('r.RemesaSistema', 'DESC')
                ->OrderBy('m.Subregion', 'ASC')
                ->OrderBy('m.Nombre', 'ASC')
                ->OrderBy('l.Nombre', 'ASC')
                ->OrderBy('r.Remesa', 'ASC')
                ->offset($startIndex)
                ->take($pageSize)
                ->get();

            $filtro_usuario = VNegociosFiltros::where('idUser', '=', $user->id)
                ->where('api', '=', 'getVales2023')
                ->first();

            if ($filtro_usuario) {
                $filtro_usuario->parameters = $parameters_serializado;
                $filtro_usuario->updated_at = time::now();
                $filtro_usuario->update();
            } else {
                $objeto_nuevo = new VNegociosFiltros();
                $objeto_nuevo->api = 'getVales2023';
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
                    'FolioTarjetaImpulso' => $data->FolioTarjetaContigoSi,
                    'FolioSolicitud' => $data->FolioSolicitud,
                    'FechaINE' => $data->FechaIne,
                    'Remesa' => $data->Remesa,
                    'Nombre' => $data->Nombre,
                    'Paterno' => $data->Paterno,
                    'Materno' => $data->Materno,
                    'Sexo' => $data->Sexo,
                    'CURP' => $data->CURP,
                    'Region' => $data->Region,
                    'Municipio' => $data->Municipio,
                    'Localidad' => $data->Localidad,
                    'Calle' => $data->Calle,
                    'Colonia' => $data->Colonia,
                    'NumExt' => $data->NumExt,
                    'NumInt' => $data->NumInt,
                    'CP' => $data->CP,
                    'Incidencia' => $data->Incidencia,
                    'Entregado' => $data->Entregado,
                    'FechaEntrega' => $data->FechaEntrega,
                    'TelFijo' => $data->TelFijo,
                    'TelCelular' => $data->TelCelular,
                    'Responsable' => $data->Responsable,
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

    function getListadoSolicitudes2023(Request $request)
    {
        try {
            $v = Validator::make($request->all(), [
                'page' => 'required',
                'pageSize' => 'required',
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

            $permisos = DB::table('users_menus')
                ->where(['idUser' => $user->id, 'idMenu' => '35'])
                ->get()
                ->first();

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

            $solicitudes = DB::table('vales as v')

                ->Select(
                    'v.id',
                    DB::RAW('lpad(hex(v.id),6,0) AS FolioSolicitud'),
                    'r.Remesa',
                    'v.Nombre',
                    'v.Paterno',
                    'v.Materno',
                    'v.CURP',
                    's.id AS idSol',
                    's.SerieInicial',
                    's.SerieFinal'
                )
                ->JOIN('vales_remesas AS r', 'v.Remesa', 'r.Remesa')
                ->JOIN('et_cat_municipio AS m', 'm.id', 'v.idMunicipio')
                ->Join('vales_solicitudes AS s', 's.idSolicitud', 'v.id')
                ->Where('idIncidencia', 1);

            if ($viewall < 1) {
                $region = DB::table('users_region')
                    ->selectRaw('Region')
                    ->where('idUser', $user->id)
                    ->first();

                if ($region === null) {
                    $response = [
                        'success' => true,
                        'results' => false,
                        'total' => 0,
                        'message' => 'No tiene region asignada',
                    ];

                    return response()->json($response, 200);
                }

                $solicitudes = $solicitudes->where('m.Region', $region->Region);
            }

            $filterQuery = '';
            $municipioRegion = [];
            $mun = [];

            if (isset($params['filtered']) && count($params['filtered']) > 0) {
                $filtersSeries = ['.SerieInicial', '.CodigoBarrasInicial'];
                foreach ($params['filtered'] as $filtro) {
                    if ($filterQuery != '') {
                        $filterQuery .= ' AND ';
                    }
                    $id = $filtro['id'];
                    $value = $filtro['value'];

                    if ($id == '.id') {
                        $value = hexdec($value);
                    }

                    if (in_array($id, $filtersSeries)) {
                        $id = 's' . $id;
                    } else {
                        $id = 'v' . $id;
                    }

                    switch (gettype($value)) {
                        case 'string':
                            $filterQuery .= " $id LIKE '%$value%' ";
                            break;
                        case 'array':
                            $colonDividedValue = implode(', ', $value);
                            $filterQuery .= " $id IN ($colonDividedValue) ";
                            break;
                        case 'boolean':
                            $filterQuery .= " $id <> 1 ";
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

            $page = $params['page'];
            $pageSize = $params['pageSize'];

            $startIndex = $page * $pageSize;

            $total = $solicitudes->count();
            $solicitudes = $solicitudes
                ->OrderBy('s.SerieInicial', 'ASC')
                ->offset($startIndex)
                ->take($pageSize)
                ->get();

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
                    'FolioSolicitud' => $data->FolioSolicitud,
                    'Remesa' => $data->Remesa,
                    'Nombre' => $data->Nombre,
                    'Paterno' => $data->Paterno,
                    'Materno' => $data->Materno,
                    'CURP' => $data->CURP,
                    'idSol' => $data->idSol,
                    'SerieInicial' => $data->SerieInicial,
                    'SerieFinal' => $data->SerieFinal,
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

    function updateArchivosSolicitud(Request $request)
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

            $oldFiles = DB::table('vales_archivos')
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
                    $newClasificacion
                );
            }
            if (isset($request->OldFiles)) {
                $oldFilesIds = $this->updateSolicitudFiles(
                    $id,
                    $request->OldFiles,
                    $oldClasificacion,
                    $oldFilesIds,
                    $oldFiles
                );
            }

            if (count($oldFilesIds) > 0) {
                DB::table('vales_archivos')
                    ->whereIn('id', $oldFilesIds)
                    ->update([
                        'idUsuarioElimino' => $user->id,
                        'FechaElimino' => date('Y-m-d H:i:s'),
                    ]);
            }

            $flag = $this->validarExpediente($id);

            return response()->json(
                [
                    'success' => true,
                    'results' => true,
                    'message' => 'Editada con éxito',
                    'data' => [],
                ],
                200
            );
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

    private function createSolicitudFiles($id, $files, $clasificationArray)
    {
        $user = auth()->user();
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
                $img = new Imagick();
                $file->move('subidos/tmp', $uniqueName);
                $img_tmp_path = sprintf('subidos/tmp/%s', $uniqueName);
                $img->readImage($img_tmp_path);
                $img->adaptiveResizeImage($width, $height);

                //Guardar en el nuevo storage
                $url_storage = Storage::disk('expedientes')->path($uniqueName);
                // $img->writeImage(sprintf('subidos/%s', $uniqueName));
                $img->writeImage($url_storage);
                File::delete($img_tmp_path);
                unset($img);
            } else {
                // $file->move('subidos', $uniqueName);
                Storage::disk('expedientes')->put(
                    $uniqueName,
                    File::get($file->getRealPath()),
                    'public'
                );
            }
            $tableArchivos = 'vales_archivos';
            DB::table($tableArchivos)->insert($fileObject);
        }
    }

    private function updateSolicitudFiles(
        $id,
        $files,
        $clasificationArray,
        $oldFilesIds,
        $oldFiles
    ) {
        $user = auth()->user();
        $tableArchivos = 'vales_archivos';
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
                            'idUsuarioActualizo' => $user->id,
                            'FechaActualizo' => date('Y-m-d H:i:s'),
                        ]);
                }
                unset($oldFilesIds[$encontrado]);
            }
        }
        return $oldFilesIds;
    }

    public function validarExpediente($id)
    {
        $expediente = DB::table('vales AS v')
            ->Select(
                'v.id',
                'acuse.idSolicitud AS acuse',
                'solicitud.idSolicitud AS solicitud',
                'ine.idSolicitud AS ine',
                'evidencia.idSolicitud AS evidencia'
            )
            ->LeftJoin(
                DB::RAW(
                    '(SELECT idSolicitud FROM vales_archivos WHERE FechaElimino IS NULL AND idSolicitud = ' .
                        $id .
                        ' AND idClasificacion = 1 ) AS acuse'
                ),
                'acuse.idSolicitud',
                'v.id'
            )
            ->LeftJoin(
                DB::RAW(
                    '(SELECT idSolicitud FROM vales_archivos WHERE FechaElimino IS NULL AND idSolicitud = ' .
                        $id .
                        ' AND idClasificacion = 2 ) AS solicitud'
                ),
                'solicitud.idSolicitud',
                'v.id'
            )
            ->LeftJoin(
                DB::RAW(
                    '(SELECT idSolicitud FROM vales_archivos WHERE FechaElimino IS NULL AND idSolicitud = ' .
                        $id .
                        ' AND idClasificacion = 3 ) AS ine'
                ),
                'ine.idSolicitud',
                'v.id'
            )
            ->LeftJoin(
                DB::RAW(
                    '(SELECT idSolicitud FROM vales_archivos WHERE FechaElimino IS NULL AND idSolicitud = ' .
                        $id .
                        ' AND idClasificacion IN (5,6,7,9) LIMIT 1 ) AS evidencia'
                ),
                'evidencia.idSolicitud',
                'v.id'
            )
            ->where('v.id', $id)
            ->first();

        $flag = true;
        foreach ($expediente as $file) {
            if (!$file) {
                $flag = false;
                break;
            }
        }

        if ($flag) {
            DB::table('vales')
                ->where('id', $id)
                ->update(['ExpedienteCompleto' => 1]);
        } else {
            DB::table('vales')
                ->where('id', $id)
                ->update(['ExpedienteCompleto' => 0]);
        }

        return $flag;
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

    function validateCveInterventor(Request $request)
    {
        try {
            $v = Validator::make($request->all(), [
                'CveInterventor' => 'required',
                'Fecha' => 'required',
            ]);
            if ($v->fails()) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' => 'CveInterventor no enviado',
                ];
                return response()->json($response, 200);
            }
            $params = $request->all();
            $r = substr($params['CveInterventor'], 0, 7);
            $cve = str_replace($r . '_', '', $params['CveInterventor']);
            $user = auth()->user();
            $fechaEntrega = DateTime::createFromFormat(
                'Y-m-d',
                $params['Fecha']
            )->format('Y-m-d');
            $minDate = strtotime(
                DateTime::createFromFormat('Y-m-d', '2023-04-01')->format(
                    'Y-m-d'
                )
            );
            $maxDate = strtotime(
                DateTime::createFromFormat('Y-m-d', '2023-12-30')->format(
                    'Y-m-d'
                )
            );

            if (
                strtotime($fechaEntrega) < $minDate ||
                strtotime($fechaEntrega) > $maxDate
            ) {
                return response()->json([
                    'success' => true,
                    'results' => false,
                    'message' => 'La fecha ingresada no es válida.',
                    'errors' => 'Fecha no válida',
                ]);
            }

            $vales = DB::table('vales AS v')
                ->select(
                    'm.Nombre AS Municipio',
                    'v.idMunicipio',
                    'm.SubRegion AS Region',
                    DB::RAW("CONCAT(v.Remesa,'_',v.CveInterventor) AS Cve")
                )
                ->join('et_cat_municipio as m', 'v.idMunicipio', 'm.id')
                ->LEFTjoin('vales_solicitudes AS s', 'v.id', 's.idSolicitud')
                ->where(['v.Remesa' => $r, 'v.CveInterventor' => $cve])
                ->first();

            if ($vales) {
                $registrado = DB::table('vales AS v')
                    ->select('v.isEntregado')
                    ->join('et_cat_municipio as m', 'v.idMunicipio', 'm.id')
                    ->LEFTjoin(
                        'vales_solicitudes AS s',
                        'v.id',
                        's.idSolicitud'
                    )
                    ->where([
                        'v.Remesa' => $r,
                        'v.CveInterventor' => $cve,
                        'isEntregado' => 1,
                    ])
                    ->first();
                if ($registrado) {
                    $response = [
                        'success' => true,
                        'results' => false,
                        'message' =>
                            'Los grupos con esta clave ya fueron recepcionados',
                    ];
                } else {
                    $response = [
                        'success' => true,
                        'results' => true,
                        'data' => $vales,
                    ];
                }
            } else {
                $response = [
                    'success' => true,
                    'results' => false,
                    'message' =>
                        'No se encontró ningún registro con esta clave, intente nuevamente',
                ];
            }
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

    function validateFolio(Request $request)
    {
        try {
            $v = Validator::make($request->all(), [
                'Folio' => 'required',
            ]);
            if ($v->fails()) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' => 'Folio no enviado',
                ];
                return response()->json($response, 200);
            }
            $params = $request->all();
            $folio = $params['Folio'];
            $r = substr($params['CveInterventor'], 0, 7);
            $cve = str_replace($r . '_', '', $params['CveInterventor']);
            $user = auth()->user();
            try {
                if (!ctype_xdigit($folio)) {
                    return response()->json([
                        'success' => true,
                        'results' => false,
                        'data' => [],
                        'message' => 'El folio ingresado no es válido.',
                    ]);
                }
                $id = hexdec($folio);
            } catch (Exception $e) {
                return response()->json([
                    'success' => true,
                    'results' => false,
                    'data' => [],
                    'message' => 'El folio ingresado no es válido.',
                ]);
            }

            $vales = DB::table('vales AS v')
                ->select(
                    DB::RAW('LPAD(HEX(v.id),6,0) AS Folio'),
                    's.SerieInicial'
                )
                ->LeftJoin('vales_solicitudes as s', 'v.id', 's.idSolicitud')
                ->where(['v.id' => $id])
                ->first();

            if ($vales) {
                if ($vales->SerieInicial) {
                    $valesCve = DB::table('vales AS v')
                        ->select(DB::RAW('LPAD(HEX(v.id),6,0) AS Folio'))
                        ->where(['v.id' => $id, 'CveInterventor' => $cve])
                        ->first();
                    if ($valesCve) {
                        $response = [
                            'success' => true,
                            'results' => true,
                            'data' => $vales,
                        ];
                    } else {
                        $response = [
                            'success' => true,
                            'results' => false,
                            'message' =>
                                'Este registro no corresponde con la CveInterventor ingresada',
                        ];
                    }
                } else {
                    $response = [
                        'success' => true,
                        'results' => false,
                        'message' => 'Este registro no tiene valera asignada',
                    ];
                }
            } else {
                $response = [
                    'success' => true,
                    'results' => false,
                    'message' =>
                        'No se encontró ningún registro con esta clave, intente nuevamente',
                ];
            }
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

    function recepcionVales(Request $request)
    {
        try {
            $v = Validator::make($request->all(), [
                'Cve' => 'required',
                'Fecha' => 'required',
                'Folios' => 'required',
            ]);
            if ($v->fails()) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' => 'Información incompleta',
                ];
                return response()->json($response, 200);
            }
            $params = $request->all();
            $r = substr($params['Cve'], 0, 7);
            $cve = str_replace($r . '_', '', $params['Cve']);
            $user = auth()->user();
            $fechaEntrega = DateTime::createFromFormat(
                'Y-m-d',
                $params['Fecha']
            )->format('Y-m-d');
            $foliosValidos = [];
            $foliosNoValidos = '';
            $flagNoValidos = false;
            $foliosDevueltos = [];

            foreach ($params['Folios'] as $folio) {
                $id = hexdec($folio['Folio']);
                $foliosValidos[] = [
                    'idSolicitud' => $id,
                    'idIncidencia' => 7,
                    'FechaCreo' => $folio['FechaHora'],
                    'UsuarioCreo' => $user->id,
                ];
                $foliosDevueltos[] = $id;
            }
            DB::table('vales_devueltos')->insert($foliosValidos);
            DB::table('vales')
                ->where([
                    'Remesa' => $r,
                    'CveInterventor' => $cve,
                ])
                ->whereNotIn('id', $foliosDevueltos)
                ->update(['isEntregado' => 1, 'entrega_at' => $fechaEntrega]);

            $response = [
                'success' => true,
                'results' => true,
                'message' => 'Folios recepcionados con éxito',
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

    function getGroupList(Request $request)
    {
        try {
            $v = Validator::make($request->all(), [
                'Cve' => 'required',
            ]);
            if ($v->fails()) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' => 'Falta la clave',
                ];
                return response()->json($response, 200);
            }
            $params = $request->all();
            $r = substr($params['Cve'], 0, 7);
            $cve = str_replace($r . '_', '', $params['Cve']);
            $user = auth()->user();

            $grupos = DB::table('vales_grupos AS g')
                ->select(
                    'g.id',
                    'g.ResponsableEntrega AS Responsable',
                    DB::RAW('CONCAT_WS("-",m.Nombre,g.CveInterventor) AS label')
                )
                ->Join('et_cat_municipio as m', 'm.id', 'g.idMunicipio')
                ->where(['g.Remesa' => $r, 'g.CveInterventor' => $cve])
                ->get();

            $response = [
                'success' => true,
                'results' => true,
                'data' => $grupos,
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
    public function updateValeSolicitud(Request $request)
    {
        try {
            $v = Validator::make($request->all(), [
                'id' => 'required',
                'Nombre' => 'required',
                'Paterno' => 'required',
            ]);
            if ($v->fails()) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' => 'Información incompleta',
                ];
                return response()->json($response, 200);
            }
            $params = $request->all();
            $user = auth()->user();

            $nombreAnterior = DB::table('vales')
                ->select('id', 'Nombre', 'Paterno', 'Materno')
                ->where('id', $params['id'])
                ->first();

            $nombreHistorico = [
                'idVale' => $nombreAnterior->id,
                'Nombre' => $nombreAnterior->Nombre,
                'Paterno' => $nombreAnterior->Paterno,
                'Materno' => $nombreAnterior->Materno,
            ];

            DB::table('vales_nombres_modificados')->insert($nombreHistorico);
            DB::table('vales')
                ->where('id', $params['id'])
                ->update([
                    'Nombre' => $params['Nombre'],
                    'Paterno' => $params['Paterno'],
                    'Materno' => $params['Materno'],
                ]);

            $response = [
                'success' => true,
                'results' => true,
                'message' => 'Solicitud modificada correctamente',
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

    function getValesExpedientes(Request $request)
    {
        try {
            $v = Validator::make($request->all(), [
                'page' => 'required',
                'pageSize' => 'required',
            ]);
            if ($v->fails()) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' => $v->errors(),
                ];
                return response()->json($response, 200);
            }

            $user = auth()->user();
            $params = $request->all();
            $parameters_serializado = serialize($params);

            $permisos = $this->getPermisosPrevalidacion();
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

            $solicitudes = DB::table('view_cveinterventor_vales as v')
                ->SELECT(
                    'v.Region',
                    'v.Municipio',
                    'v.CveInterventor',
                    'v.Remesa',
                    'v.Beneficiarios',
                    'v.Expedientes',
                    'v.Validados'
                )
                ->Join('vales_remesas AS r', 'v.Remesa', 'r.Remesa');

            if ($viewall < 1) {
                $region = DB::table('users_region')
                    ->selectRaw('Region')
                    ->where(['idUser' => $user->id, 'idPrograma' => 1])
                    ->first();

                if ($region === null) {
                    $response = [
                        'success' => true,
                        'results' => false,
                        'total' => 0,
                        'message' =>
                            'No tiene region asignada, Contacte al administrador',
                    ];

                    return response()->json($response, 200);
                }

                $solicitudes = $solicitudes->where('v.Region', $region->Region);
            }

            $filterQuery = '';
            $municipioRegion = [];
            $mun = [];

            if (isset($params['filtered']) && count($params['filtered']) > 0) {
                $filtersRemesas = ['.Remesa'];
                foreach ($params['filtered'] as $filtro) {
                    if ($filterQuery != '') {
                        $filterQuery .= ' AND ';
                    }
                    $id = $filtro['id'];
                    $value = $filtro['value'];

                    if ($id == 'region') {
                        $municipios = DB::table('et_cat_municipio')
                            ->select('id')
                            ->whereIN('SubRegion', $value)
                            ->get();
                        foreach ($municipios as $m) {
                            $municipioRegion[] = "'" . $m->id . "'";
                        }

                        $id = '.idMunicipio';
                        $value = $municipioRegion;
                    }

                    if ($id == '.Validado') {
                        if ($value == 1) {
                            $filterQuery .= '(v.Beneficiarios = v.Validados)';
                        } else {
                            $filterQuery .= '(v.Beneficiarios <> v.Validados)';
                        }
                    } else {
                        if (in_array($id, $filtersRemesas)) {
                            $id = 'r.Remesa';
                        } else {
                            $id = 'v' . $id;
                        }

                        switch (gettype($value)) {
                            case 'string':
                                $filterQuery .= " $id LIKE '%$value%' ";
                                break;
                            case 'array':
                                $colonDividedValue = implode(', ', $value);
                                $filterQuery .= " $id IN ($colonDividedValue) ";
                                break;
                            case 'boolean':
                                $filterQuery .= " $id <> 1 ";
                                break;
                            default:
                                if ($id == 'v.Expedientes' && $value > 0) {
                                    $filterQuery .= " $id > 0 ";
                                } else {
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
                $solicitudes->whereRaw($filterQuery);
                $total = (clone $solicitudes)->get()->count();
            } else {
                $total = $solicitudes->count();
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

            $solicitudes = $solicitudes
                ->OrderBy('v.Remesa', 'ASC')
                ->OrderBy('v.Region', 'ASC')
                ->OrderBy('v.Municipio', 'ASC')
                ->OrderBy('v.CveInterventor', 'ASC')
                ->offset($startIndex)
                ->take($pageSize)
                ->get();

            if ($total == 0) {
                return [
                    'success' => true,
                    'results' => true,
                    'total' => $total,
                    'filtros' => $params['filtered'],
                    'data' => [],
                ];
            }

            $filtros = '';
            if (isset($params['filtered'])) {
                $filtros = $params['filtered'];
            }

            $response = [
                'success' => true,
                'results' => true,
                'data' => $solicitudes,
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

    function getTotalSolicitudes(Request $request)
    {
        try {
            $user = auth()->user();
            $params = $request->all();

            $permisos = $this->getPermisosPrevalidacion();
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

            $solicitudes = DB::table('vales as v')

                ->Select(DB::RAW('COUNT( v.id ) AS Total'))
                ->Join('et_cat_municipio AS m', 'm.id', 'v.idMunicipio')
                ->Join('vales_remesas AS r', 'r.Remesa', 'v.Remesa')
                ->WhereRaw('r.Ejercicio = 2023');

            if ($viewall < 1) {
                $region = DB::table('users_region')
                    ->selectRaw('Region')
                    ->where(['idUser' => $user->id, 'idPrograma' => 1])
                    ->first();

                if ($region === null) {
                    $response = [
                        'success' => true,
                        'results' => false,
                        'total' => 0,
                        'message' =>
                            'No tiene region asignada, Contacte al administrador',
                    ];

                    return response()->json($response, 200);
                }

                $solicitudes = $solicitudes->where('m.Region', $region->Region);
            }

            $filterQuery = '';

            if ($filterQuery != '') {
                $solicitudes->whereRaw($filterQuery);
            }

            $solicitudes = $solicitudes->First();

            if (!$solicitudes) {
                $total = 0;
            } else {
                $total = $solicitudes->Total;
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
                'total' => 0,
                'errors' => $errors,
                'message' => 'Ha ocurrido un error, consulte al administrador',
            ];
            return response()->json($response, 200);
        }
    }

    function getTotalExpedientes(Request $request)
    {
        try {
            $user = auth()->user();
            $params = $request->all();

            $permisos = $this->getPermisosPrevalidacion();
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

            $solicitudes = DB::table('vales as v')

                ->Select(DB::RAW('COUNT( v.id ) AS Total'))
                ->Join('et_cat_municipio AS m', 'm.id', 'v.idMunicipio')
                ->Join('vales_remesas AS r', 'r.Remesa', 'v.Remesa')
                ->where('v.ExpedienteCompleto', 1)
                ->Where('r.Ejercicio', 2023);

            if ($viewall < 1) {
                $region = DB::table('users_region')
                    ->selectRaw('Region')
                    ->where(['idUser' => $user->id, 'idPrograma' => 1])
                    ->first();

                if ($region === null) {
                    $response = [
                        'success' => true,
                        'results' => false,
                        'total' => 0,
                        'message' =>
                            'No tiene region asignada, Contacte al administrador',
                    ];

                    return response()->json($response, 200);
                }

                $solicitudes = $solicitudes->where('m.Region', $region->Region);
            }

            $filterQuery = '';

            if ($filterQuery != '') {
                $solicitudes->whereRaw($filterQuery);
            }

            $solicitudes = $solicitudes->First();

            if (!$solicitudes) {
                $total = 0;
            } else {
                $total = $solicitudes->Total;
            }

            $response = [
                'success' => true,
                'results' => true,
                'expedientes' => $total,
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

    function getTotalPendientes(Request $request)
    {
        try {
            $user = auth()->user();
            $params = $request->all();

            $permisos = $this->getPermisosPrevalidacion();
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

            $solicitudes = DB::table('vales as v')

                ->Select(DB::RAW('COUNT( v.id ) AS Total'))
                ->Join('et_cat_municipio AS m', 'm.id', 'v.idMunicipio')
                ->Join('vales_remesas AS r', 'r.Remesa', 'v.Remesa')
                ->where('v.Validado', 0)
                ->Where('r.Ejercicio', 2023);

            if ($viewall < 1) {
                $region = DB::table('users_region')
                    ->selectRaw('Region')
                    ->where(['idUser' => $user->id, 'idPrograma' => 1])
                    ->first();

                if ($region === null) {
                    $response = [
                        'success' => true,
                        'results' => false,
                        'total' => 0,
                        'message' =>
                            'No tiene region asignada, Contacte al administrador',
                    ];

                    return response()->json($response, 200);
                }

                $solicitudes = $solicitudes->where('m.Region', $region->Region);
            }

            $filterQuery = '';

            if ($filterQuery != '') {
                $solicitudes->whereRaw($filterQuery);
            }

            $solicitudes = $solicitudes->First();

            if (!$solicitudes) {
                $total = 0;
            } else {
                $total = $solicitudes->Total;
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
                'total' => 0,
                'errors' => $errors,
                'message' => 'Ha ocurrido un error, consulte al administrador',
            ];
            return response()->json($response, 200);
        }
    }

    function getTotalValidados(Request $request)
    {
        try {
            $user = auth()->user();
            $params = $request->all();

            $permisos = $this->getPermisosPrevalidacion();
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

            $solicitudes = DB::table('vales as v')

                ->Select(DB::RAW('COUNT( v.id ) AS Total'))
                ->Join('et_cat_municipio AS m', 'm.id', 'v.idMunicipio')
                ->Join('vales_remesas AS r', 'r.Remesa', 'v.Remesa')
                ->where('v.Validado', 1)
                ->Where('r.Ejercicio', 2023);

            if ($viewall < 1) {
                $region = DB::table('users_region')
                    ->selectRaw('Region')
                    ->where(['idUser' => $user->id, 'idPrograma' => 1])
                    ->first();

                if ($region === null) {
                    $response = [
                        'success' => true,
                        'results' => false,
                        'total' => 0,
                        'message' =>
                            'No tiene region asignada, Contacte al administrador',
                    ];

                    return response()->json($response, 200);
                }

                $solicitudes = $solicitudes->where('m.Region', $region->Region);
            }

            $filterQuery = '';

            if ($filterQuery != '') {
                $solicitudes->whereRaw($filterQuery);
            }

            $solicitudes = $solicitudes->First();

            if (!$solicitudes) {
                $total = 0;
            } else {
                $total = $solicitudes->Total;
            }

            $response = [
                'success' => true,
                'results' => true,
                'validados' => $total,
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

    function getSolicitudesExpedientes(Request $request)
    {
        try {
            $v = Validator::make($request->all(), [
                'page' => 'required',
                'pageSize' => 'required',
                'CveInterventor' => 'required',
                'Remesa' => 'required',
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
            $parameters_serializado = serialize($params);

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

            $viewall = $permisos->ViewAll;

            $solicitudes = DB::table('vales as v')

                ->Select(
                    'v.id',
                    DB::Raw('lpad(hex(v.id),6,0) AS FolioSolicitud'),
                    'v.FechaSolicitud',
                    'v.Remesa',
                    'v.Nombre',
                    'v.Paterno',
                    'v.Materno',
                    'v.CURP',
                    'v.Sexo',
                    'v.FechaIne',
                    'm.SubRegion AS Region',
                    'm.Nombre AS Municipio',
                    'l.Nombre AS Localidad',
                    'v.Calle',
                    'v.Calle',
                    'v.Colonia',
                    'v.NumExt',
                    'v.NumInt',
                    'v.CP',
                    'v.FolioTarjetaContigoSi',
                    'v.TelFijo',
                    'v.TelCelular',
                    'v.ResponsableEntrega AS Responsable',
                    'v.ExpedienteCompleto',
                    'v.Validado'
                )
                ->JOIN('vales_remesas AS r', 'v.Remesa', 'r.Remesa')
                ->JOIN('et_cat_municipio AS m', 'm.id', 'v.idMunicipio')
                ->JOIN('et_cat_localidad_2022 AS l', 'l.id', 'v.idLocalidad')
                ->Where([
                    'v.Remesa' => $params['Remesa'],
                    'v.CveInterventor' => $params['CveInterventor'],
                ])
                ->WHERERAW('v.Ejercicio = 2023');

            if ($viewall < 1) {
                $region = DB::table('users_region')
                    ->selectRaw('Region')
                    ->where(['idUser' => $user->id, 'idPrograma' => 1])
                    ->first();

                if ($region === null) {
                    $response = [
                        'success' => true,
                        'results' => false,
                        'total' => 0,
                        'message' => 'No tiene region asignada',
                    ];

                    return response()->json($response, 200);
                }

                $solicitudes = $solicitudes->where('m.Region', $region->Region);
            }

            $filterQuery = '';
            $municipioRegion = [];
            $mun = [];

            if (isset($params['filtered']) && count($params['filtered']) > 0) {
                $filtersCedulas = ['.Folio'];
                $filtersRemesas = ['.Remesa'];
                foreach ($params['filtered'] as $filtro) {
                    if ($filterQuery != '') {
                        $filterQuery .= ' AND ';
                    }
                    $id = $filtro['id'];
                    $value = $filtro['value'];

                    if ($id == '.id') {
                        $value = hexdec($value);
                    }

                    if ($id == 'region') {
                        $municipios = DB::table('et_cat_municipio')
                            ->select('id')
                            ->whereIN('SubRegion', $value)
                            ->get();
                        foreach ($municipios as $m) {
                            $municipioRegion[] = "'" . $m->id . "'";
                        }

                        $id = '.idMunicipio';
                        $value = $municipioRegion;
                    }

                    if (in_array($id, $filtersCedulas)) {
                        $id = 'c' . $id;
                    } elseif (in_array($id, $filtersRemesas)) {
                        $id = 'r.RemesaSistema';
                    } else {
                        $id = 'v' . $id;
                    }

                    switch (gettype($value)) {
                        case 'string':
                            $filterQuery .= " $id LIKE '%$value%' ";
                            break;
                        case 'array':
                            $colonDividedValue = implode(', ', $value);
                            $filterQuery .= " $id IN ($colonDividedValue) ";
                            break;
                        case 'boolean':
                            $filterQuery .= " $id <> 1 ";
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

            $page = $params['page'];
            $pageSize = $params['pageSize'];

            $startIndex = $page * $pageSize;

            $total = $solicitudes->count();
            $solicitudes = $solicitudes
                ->OrderBy('v.Remesa', 'DESC')
                ->OrderBy('m.Subregion', 'ASC')
                ->OrderBy('m.Nombre', 'ASC')
                ->OrderBy('l.Nombre', 'ASC')
                ->OrderBy('v.Colonia', 'ASC')
                ->offset($startIndex)
                ->take($pageSize)
                ->get();

            $filtro_usuario = VNegociosFiltros::where('idUser', '=', $user->id)
                ->where('api', '=', 'getVales2023')
                ->first();

            if ($filtro_usuario) {
                $filtro_usuario->parameters = $parameters_serializado;
                $filtro_usuario->updated_at = time::now();
                $filtro_usuario->update();
            } else {
                $objeto_nuevo = new VNegociosFiltros();
                $objeto_nuevo->api = 'getVales2023';
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
                    'FolioTarjetaImpulso' => $data->FolioTarjetaContigoSi,
                    'FolioSolicitud' => $data->FolioSolicitud,
                    'FechaINE' => $data->FechaIne,
                    'Remesa' => $data->Remesa,
                    'Nombre' => $data->Nombre,
                    'Paterno' => $data->Paterno,
                    'Materno' => $data->Materno,
                    'Sexo' => $data->Sexo,
                    'CURP' => $data->CURP,
                    'Region' => $data->Region,
                    'Municipio' => $data->Municipio,
                    'Localidad' => $data->Localidad,
                    'Calle' => $data->Calle,
                    'Colonia' => $data->Colonia,
                    'NumExt' => $data->NumExt,
                    'NumInt' => $data->NumInt,
                    'CP' => $data->CP,
                    'TelFijo' => $data->TelFijo,
                    'TelCelular' => $data->TelCelular,
                    'Responsable' => $data->Responsable,
                    'ExpedienteCompleto' => $data->ExpedienteCompleto,
                    'Validado' => $data->Validado,
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

    function validateSolicitud(Request $request)
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
            $filesValidate = [1, 2, 3, 6];

            foreach ($filesValidate as $file) {
                $validateFile = DB::table('vales_archivos')
                    ->Select('id')
                    ->WhereNull('FechaElimino')
                    ->Where(['idSolicitud' => $id, 'idClasificacion' => $file])
                    ->first();
                if (!$validateFile) {
                    $clasificacion = DB::table('vales_archivos_clasificacion')
                        ->where('id', $file)
                        ->first();
                    $response = [
                        'success' => true,
                        'results' => false,
                        'message' =>
                            'Falta cargar el archivo ' .
                            $clasificacion->Clasificacion .
                            ' para validar la solicitud',
                    ];
                    //return response()->json($response, 200);
                }
            }

            DB::table('vales')
                ->where('id', $id)
                ->update(['Validado' => 1]);

            $response = [
                'success' => true,
                'results' => true,
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

    public function cargaMasiva()
    {
        try {
            $pendientes = DB::table('carga_archivos_masivo')
                ->whereNull('Cargado')
                ->get();
            if ($pendientes->count() > 0) {
                $pendientes->each(function ($item, $key) {
                    $curpRegistrada = DB::table('vales')
                        ->Select('id', 'CURP')
                        ->Where('CURP', $item->CURP)
                        ->first();
                    if ($curpRegistrada) {
                        $archivoRegistrado = DB::table('vales_archivos')
                            ->Select('idSolicitud')
                            ->WhereNull('FechaElimino')
                            ->Where([
                                'idSolicitud' => $curpRegistrada->id,
                                'idClasificacion' => $item->Clasificacion,
                            ])
                            ->first();
                        if (!$archivoRegistrado) {
                            $extension = explode('.', $item->NombreArchivo);
                            $valesArchivos = [
                                'idSolicitud' => $curpRegistrada->id,
                                'idClasificacion' => $item->Clasificacion,
                                'NombreOriginal' => $item->NombreArchivo,
                                'NombreSistema' => $item->NombreArchivo,
                                'Tipo' => 'image',
                                'Extension' => $extension[1],
                                'idUsuarioCreo' => 1,
                                'FechaCreo' => date('Y-m-d H:i:s'),
                            ];

                            DB::table('vales_archivos')->insert($valesArchivos);
                            DB::table('carga_archivos_masivo')
                                ->where('id', $item->ID)
                                ->update(['Cargado' => 1]);
                            $this->validarExpediente($curpRegistrada->id);
                        } else {
                            DB::table('carga_archivos_masivo')
                                ->where('id', $item->ID)
                                ->update(['Cargado' => 2]);
                        }
                    } else {
                        DB::table('carga_archivos_masivo')
                            ->where('id', $item->ID)
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

    public function getAvancesGrupos(Request $request)
    {
        $parameters = $request->all();
        $user = auth()->user();
        $permisos = DB::table('users_menus')
            ->where(['idUser' => $user->id, 'idMenu' => '11'])
            ->get()
            ->first();

        try {
            $select = DB::table('vales_grupos AS g')
                ->select(
                    'r.Remesa AS RemesaSistema',
                    'm.SubRegion AS Region',
                    'm.Nombre AS Municipio',
                    'g.CveInterventor',
                    'l.Nombre AS Localidad',
                    'g.ResponsableEntrega',
                    'g.TotalAprobados',
                    DB::RAW('COUNT( s.idSolicitud ) AS Avance')
                )
                ->JOIN('et_cat_municipio AS m', 'g.idMunicipio', 'm.Id')
                ->JOIN('et_cat_localidad_2022 AS l', 'g.idLocalidad', 'l.Id')
                ->JOIN('vales AS v', 'g.id', 'v.idGrupo')
                ->JOIN('vales_remesas AS r', 'g.Remesa', 'r.Remesa')
                ->LEFTJOIN('vales_solicitudes AS s', 'v.id', 's.idSolicitud')
                ->WHERE('r.Remesa', $parameters['remesa'])
                ->GROUPBY('g.id')
                ->ORDERBY('g.id');

            if ($permisos) {
                if ($permisos->ViewAll == 0) {
                    $userRegion = DB::table('users_region')
                        ->select('Region')
                        ->Where('idUser', $user->id)
                        ->first();
                    $select->where('m.SubRegion', $userRegion->Region);
                }
            } else {
                return response()->json([
                    'success' => true,
                    'results' => false,
                    'errors' => 'No tiene permisos asignados en el menú',
                    'data' => $data,
                ]);
            }

            if (
                isset($parameters['region']) &&
                intval($parameters['region'] > 0)
            ) {
                $select->where('m.SubRegion', $parameters['region']);
            }

            $total = (clone $select)->get()->count();
            $page = $parameters['page'];
            $pageSize = $parameters['pageSize'];
            $startIndex = $page * $pageSize;

            $data = $select
                ->offset($startIndex)
                ->take($pageSize)
                ->get();

            return response()->json([
                'success' => true,
                'results' => true,
                'total' => $total,
                'data' => $data,
            ]);
        } catch (QueryException $e) {
            return ['success' => false, 'errors' => $e->getMessage()];
        }
    }

    public function getAvancesGruposReporte(Request $request)
    {
        $parameters = $request->all();
        $user = auth()->user();
        $permisos = DB::table('users_menus')
            ->where(['idUser' => $user->id, 'idMenu' => '11'])
            ->get()
            ->first();

        try {
            $select = DB::table('vales_grupos AS g')
                ->select(
                    'r.Remesa AS RemesaSistema',
                    'm.SubRegion AS Region',
                    'm.Nombre AS Municipio',
                    'g.CveInterventor',
                    'l.Nombre AS Localidad',
                    'g.ResponsableEntrega',
                    'g.TotalAprobados',
                    DB::RAW('COUNT( s.idSolicitud ) AS Avance')
                )
                ->JOIN('et_cat_municipio AS m', 'g.idMunicipio', 'm.Id')
                ->JOIN('et_cat_localidad_2022 AS l', 'g.idLocalidad', 'l.Id')
                ->JOIN('vales AS v', 'g.id', 'v.idGrupo')
                ->JOIN('vales_remesas AS r', 'g.Remesa', 'r.Remesa')
                ->LEFTJOIN('vales_solicitudes AS s', 'v.id', 's.idSolicitud')
                ->WHERE('r.Remesa', $parameters['remesa'])
                ->GROUPBY('g.id')
                ->ORDERBY('g.id');

            if ($permisos) {
                if ($permisos->ViewAll == 0) {
                    $userRegion = DB::table('users_region')
                        ->select('Region')
                        ->Where('idUser', $user->id)
                        ->first();
                    $select->where('m.SubRegion', $userRegion->Region);
                }
            } else {
                return response()->json([
                    'success' => true,
                    'results' => false,
                    'errors' => 'No tiene permisos asignados en el menú',
                    'data' => $data,
                ]);
            }

            if (
                isset($parameters['region']) &&
                intval($parameters['region'] > 0)
            ) {
                $select->where('m.SubRegion', $parameters['region']);
            }

            $data = $select->get();

            $res = $data
                ->map(function ($x) {
                    $x = is_object($x) ? (array) $x : $x;
                    return $x;
                })
                ->toArray();

            $reader = IOFactory::createReader('Xlsx');
            $spreadsheet = $reader->load(
                public_path() . '/archivos/formatoAvanceGrupos.xlsx'
            );
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->fromArray($res, null, 'B6');
            $largo = count($res);
            $sheet->setCellValue('D3', 'Fecha Reporte: ' . date('Y-m-d H:i:s'));

            for ($i = 1; $i <= $largo; $i++) {
                $inicio = 5 + $i;
                $sheet->setCellValue('A' . $inicio, $i);
            }
            $sheet->getDefaultRowDimension()->setRowHeight(-1);

            $writer = new Xlsx($spreadsheet);
            $writer->save(
                'archivos/' .
                    $user->email .
                    'AvanceGrupos_' .
                    $parameters['remesa'] .
                    '.xlsx'
            );
            $file =
                public_path() .
                '/archivos/' .
                $user->email .
                'AvanceGrupos_' .
                $parameters['remesa'] .
                '.xlsx';

            return response()->download(
                $file,
                $user->email .
                    'AvanceGrupos_' .
                    $parameters['remesa'] .
                    date('Y-m-d H:i:s') .
                    '.xlsx'
            );

            return response()->json([
                'success' => true,
                'results' => true,
                'total' => $total,
                'data' => $data,
            ]);
        } catch (QueryException $e) {
            return ['success' => false, 'errors' => $e->getMessage()];
        }
    }

    function getRemesaEjercicio(Request $request)
    {
        $params = $request->all();
        try {
            $res = DB::table('vales_remesas')
                ->select('RemesaSistema AS label', 'RemesaSistema AS value')
                ->where(['Ejercicio' => $params['ejercicio']])
                ->orderBy('RemesaSistema')
                ->distinct()
                ->get();

            return [
                'success' => true,
                'results' => true,
                'data' => $res,
            ];
        } catch (QueryException $e) {
            $response = [
                'success' => true,
                'results' => false,
                'errors' => $e->getMessage(),
                'message' => 'Campo de consulta incorrecto',
            ];

            return response()->json($response, 200);
        }
    }

    function getRegiones(Request $request)
    {
        try {
            $user = auth()->user();
            $res = DB::table('cat_regiones')
                ->select('id AS value', 'Region AS label')
                ->orderBy('id')
                ->get();

            $permisos = DB::table('users_menus')
                ->where(['idUser' => $user->id, 'idMenu' => '32'])
                ->get()
                ->first();

            if ($permisos) {
                if ($permisos->ViewAll > 0) {
                    $res->push(['value' => 99, 'label' => 'OTROS']);
                }
            }

            return [
                'success' => true,
                'results' => true,
                'data' => $res,
            ];
        } catch (QueryException $e) {
            $response = [
                'success' => true,
                'results' => false,
                'errors' => $e->getMessage(),
                'message' => 'Campo de consulta incorrecto',
            ];

            return response()->json($response, 200);
        }
    }

    function getAvancesPadron(Request $request)
    {
        try {
            $v = Validator::make($request->all(), [
                'remesa' => 'required',
            ]);
            if ($v->fails()) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' => 'No se recibió la remesa consultar',
                ];
                return response()->json($response, 200);
            }

            $params = $request->all();
            $procedure = '';

            if (isset($params['region'])) {
                if ($params['region'] == 99) {
                    $procedure = 'remesa_otros("' . $params['remesa'];
                } else {
                    $procedure =
                        'remesa_region("' .
                        $params['remesa'] .
                        '", "' .
                        $params['region'];
                }
            } else {
                $procedure = 'remesa("' . $params['remesa'];
            }

            $data = DB::Select(
                'CALL padron_resumen_avance_' . $procedure . '")'
            );

            $response = [
                'success' => true,
                'results' => true,
                'data' => $data,
                'total' => count($data),
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

    function getReporteAvances(Request $request)
    {
        try {
            $v = Validator::make($request->all(), [
                'remesa' => 'required',
            ]);
            if ($v->fails()) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' => 'No se recibió la remesa consultar',
                ];
                return response()->json($response, 200);
            }

            $params = $request->all();
            $procedure = '';

            if (isset($params['region'])) {
                if ($params['region'] == 99) {
                    $procedure = 'remesa_otros("' . $params['remesa'];
                } else {
                    $procedure =
                        'remesa_region("' .
                        $params['remesa'] .
                        '", "' .
                        $params['region'];
                }
            } else {
                $procedure = 'remesa("' . $params['remesa'];
            }

            $data = DB::Select(
                'CALL padron_resumen_avance_' . $procedure . '")'
            );

            $res = [];
            foreach ($data as $item) {
                $res[] = is_object($item) ? (array) $item : $item;
            }

            $reader = IOFactory::createReader('Xlsx');
            $spreadsheet = $reader->load(
                public_path() . '/archivos/formatoReporteAvancePadron.xlsx'
            );
            $sheet = $spreadsheet->getActiveSheet();
            $largo = count($res);
            $impresion = $largo + 12;
            $sheet->getPageSetup()->setPrintArea('A1:I' . $impresion);
            $sheet
                ->getPageSetup()
                ->setOrientation(PageSetup::ORIENTATION_PORTRAIT);
            $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_LETTER);

            $sheet->fromArray($res, null, 'C6');

            $sheet->setCellValue('E2', 'Fecha: ' . date('Y-m-d H:i:s'));

            for ($i = 1; $i <= $largo; $i++) {
                $inicio = 5 + $i;
                $sheet->setCellValue('B' . $inicio, $i);
            }

            $sheet->getDefaultRowDimension()->setRowHeight(-1);

            $writer = new Xlsx($spreadsheet);
            $writer->save('archivos/Avance_' . $params['remesa'] . '.xlsx');
            $file =
                public_path() .
                '/archivos/Avance_' .
                $params['remesa'] .
                '.xlsx';

            return response()->download(
                $file,
                'Avance_' . $params['remesa'] . '.xlsx'
            );
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

    function getBeneficiariosAvances(Request $request)
    {
        $v = Validator::make($request->all(), [
            'region' => 'required',
        ]);
        if ($v->fails()) {
            $response = [
                'success' => true,
                'results' => false,
                'errors' => 'No se recibió la region a consultar',
            ];
            return response()->json($response, 200);
        }

        $params = $request->all();
        ini_set('memory_limit', '-1');
        set_time_limit(0);

        $data = DB::table('padron_validado AS p')
            ->select(
                'o.Origen',
                DB::RAW('LPAD(HEX(p.id),6,0) AS FolioPadron'),
                'p.Region',
                'p.Nombre',
                'p.Paterno',
                'p.Materno',
                'p.FechaNacimiento',
                'p.Sexo',
                'p.EstadoNacimiento',
                'p.CURP',
                'p.CURPAnterior',
                'p.Municipio',
                'p.NumLocalidad',
                'p.Localidad',
                'p.Colonia',
                'p.CveColonia',
                'p.CveInterventor',
                'p.CveTipoCalle',
                'p.Calle',
                'p.NumExt',
                'p.NumInt',
                'p.CP',
                'p.Telefono',
                'p.Celular',
                'p.TelRecados',
                'p.FechaIne',
                'p.FolioTarjetaContigoSi',
                'p.EnlaceOrigen',
                'p.EnlaceIntervencion1',
                'p.EnlaceIntervencion2',
                'p.EnlaceIntervencion3',
                'p.FechaSolicitud',
                'p.ResponsableEntrega',
                'p.EstatusOrigen',
                'p.Remesa',
                DB::raw(
                    "IF (p.TieneApoyo = 1,'El BENEFICIARIO TIENE APOYO EN OTRA REMESA',NULL) AS ApoyoMultiple"
                ),
                DB::raw(
                    "IF (p.CURPAnterior IS NOT NULL ,'El CURP FUE ACTUALIZADO POR RENAPO',NULL) AS CURPDiferente"
                )
            )
            ->JOIN('padron_archivos AS a', 'a.id', 'p.idArchivo')
            ->Join('cat_padron_origen AS o', 'a.idOrigen', '=', 'o.id')
            ->JOIN('vales_remesas AS r', 'p.Remesa', 'r.Remesa');

        if ($params['region'] == 99) {
            $data
                ->Where('r.RemesaSistema', $params['remesa'])
                ->Where('o.id', '>', 7);
        } else {
            $data
                ->JOIN(
                    'municipios_padron_vales AS mp',
                    'mp.Municipio',
                    'p.Municipio'
                )
                ->JOIN('et_cat_municipio AS m', 'mp.idCatalogo', 'm.Id')
                ->Where('r.RemesaSistema', $params['remesa'])
                ->Where('m.SubRegion', $params['region'])
                ->Where('o.id', '<', 8);
        }

        $data->Where('p.EstatusOrigen', 'SI')->OrderBy('p.id');
        //dd(str_replace_array('?', $data->getBindings(), $data->toSql()));

        return (new PadronBeneficiariosExport($data))->Download(
            'Padron_Validado_Remesa_' . $params['remesa'] . '.xlsx'
        );
    }

    public function getRegionesMenu(Request $request)
    {
        try {
            $user = auth()->user();
            $parameters = $request->all();
            $res = DB::table('cat_regiones')->select(
                'id AS value',
                'Region AS label'
            );

            if (isset($parameters['menu'])) {
                $permisos = DB::table('users_menus')
                    ->where([
                        'idUser' => $user->id,
                        'idMenu' => $parameters['menu'],
                    ])
                    ->get()
                    ->first();

                if ($permisos) {
                    if ($permisos->ViewAll == 0) {
                        $res->WhereRaw(
                            'id IN (' .
                                'SELECT Region FROM users_region WHERE idUser = ' .
                                $user->id .
                                ')'
                        );
                    }
                }
            }

            $res = $res->orderBy('id')->get();

            return [
                'success' => true,
                'results' => true,
                'data' => $res,
            ];
        } catch (QueryException $e) {
            $response = [
                'success' => true,
                'results' => false,
                'errors' => $e->getMessage(),
                'message' => 'Campo de consulta incorrecto',
            ];

            return response()->json($response, 200);
        }
    }

    public function getMunicipiosRemesas(Request $request)
    {
        try {
            $params = $request->all();
            $user = auth()->user();
            $municipios = DB::Select(
                "
                    SELECT
                        DISTINCT v.idMunicipio AS value,
                        m.Nombre AS label
                    FROM
                        vales AS v 
                        JOIN et_cat_municipio AS m ON v.idMunicipio = m.id 
                    WHERE
                        v.Remesa = '" .
                    $params['remesa'] .
                    "'"
            );

            $response = [
                'success' => true,
                'results' => true,
                'data' => $municipios,
            ];
            return response()->json($response, 200);
        } catch (\QueryException $errors) {
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

    public function getCveInterventor(Request $request)
    {
        try {
            $params = $request->all();
            $user = auth()->user();

            $permisos = DB::table('users_menus AS um')
                ->Select('Seguimiento', 'ViewAll')
                ->Where(['idUser' => $user->id, 'idMenu' => 34])
                ->first();

            $filtroPermisos = '';

            if ($permisos) {
                if ($permisos->ViewAll === 0) {
                    $userRegion = DB::table('users_region')
                        ->select('Region')
                        ->Where('idUser', $user->id)
                        ->first();
                    $filtroPermisos =
                        ' AND m.SubRegion = ' . $userRegion->Region . ' ';
                }
            } else {
                $response = [
                    'success' => false,
                    'results' => false,
                    'total' => 0,
                    'errors' => 'No tiene permisos en este menú',
                    'message' => 'No tiene permisos en este menú',
                ];

                return response()->json($response, 200);
            }

            $cveInterventor = DB::Select(
                "
                    SELECT
                        DISTINCT v.CveInterventor
                    FROM
                        vales AS v    
                        JOIN et_cat_municipio AS m ON v.idMunicipio = m.Id                     
                    WHERE
                        v.Remesa = '" .
                    $params['remesa'] .
                    "'" .
                    $filtroPermisos .
                    ' ORDER BY v.CveInterventor'
            );

            $response = [
                'success' => true,
                'results' => true,
                'data' => $cveInterventor,
            ];
            return response()->json($response, 200);
        } catch (\QueryException $errors) {
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

    public function getLocalidad(Request $request)
    {
        try {
            $params = $request->all();
            $user = auth()->user();
            $localidades = DB::Select(
                "
                    SELECT
                        DISTINCT v.idLocalidad AS value,
                        l.Nombre AS label
                    FROM
                        vales AS v
                        JOIN et_cat_localidad_2022 AS l ON v.idLocalidad = l.Id                    
                    WHERE
                        v.Remesa = '" .
                    $params['remesa'] .
                    "' AND v.idMunicipio = " .
                    $params['municipio'] .
                    " AND CveInterventor = '" .
                    $params['cveInterventor'] .
                    "'"
            );

            $response = [
                'success' => true,
                'results' => true,
                'data' => $localidades,
            ];
            return response()->json($response, 200);
        } catch (\QueryException $errors) {
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

    public function getResponsables(Request $request)
    {
        try {
            $params = $request->all();
            $user = auth()->user();
            $responsables = DB::Select(
                "
                    SELECT
                        DISTINCT v.ResponsableEntrega AS Responsable
                    FROM
                        vales AS v                        
                    WHERE
                        v.Remesa = '" .
                    $params['remesa'] .
                    "' AND CveInterventor = '" .
                    $params['cveInterventor'] .
                    "'"
            );

            $response = [
                'success' => true,
                'results' => true,
                'data' => $responsables,
            ];
            return response()->json($response, 200);
        } catch (\QueryException $errors) {
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

    public function getGruposRevision(Request $request)
    {
        try {
            $params = $request->all();
            $user = auth()->user();

            $grupos = DB::Select(
                "
                SELECT
                    g.id AS idGrupo,
                    g.Remesa,
                    g.idMunicipio,
                    m.Nombre AS Municipio,
                    g.CveInterventor,
                    g.idLocalidad,
                    l.Nombre AS Localidad,
                    g.ResponsableEntrega AS Responsable,
                    g.TotalAprobados AS Aprobados,
                    COUNT( s.idSolicitud ) AS Pineados 
                FROM
                    vales_grupos AS g
                    JOIN vales AS v ON g.id = v.idGrupo
                    LEFT JOIN vales_solicitudes AS s ON v.id = s.idSolicitud
                    JOIN et_cat_municipio AS m ON g.idMunicipio = m.Id
                    JOIN et_cat_localidad_2022 AS l ON g.idLocalidad = l.Id 
                WHERE
                    g.Remesa = '" .
                    $params['remesa'] .
                    "' 
                    AND g.CveInterventor = '" .
                    $params['cveInterventor'] .
                    "' 
                    AND g.ResponsableEntrega = '" .
                    $params['responsable'] .
                    "' 
                GROUP BY
                    g.id
                "
            );

            $total = count($grupos);

            $response = [
                'success' => true,
                'results' => true,
                'total' => $total,
                'data' => $grupos,
            ];
            return response()->json($response, 200);
        } catch (\QueryException $errors) {
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

    public function valesIncidencia(Request $request)
    {
        $v = Validator::make($request->all(), [
            'devueltos' => 'required',
        ]);
        if ($v->fails()) {
            $response = [
                'success' => true,
                'results' => false,
                'errors' => 'No se recibió ningun registro',
            ];
            return response()->json($response, 200);
        }

        $params = $request->all();
        $user = auth()->user();
        $valesADevolver = $params['devueltos'];
        $valesConciliados = '';
        foreach ($valesADevolver as $valera) {
            $conciliado = DB::table('vales_solicitudes AS s')
                ->Select('s.idSolicitud')
                ->Join(
                    'vales_series_2023 AS serie',
                    'serie.SerieInicial',
                    's.SerieInicial'
                )
                ->Join(
                    'vales_conciliados_2023 AS conciliados',
                    'conciliados.SerieVale',
                    'serie.Serie'
                )
                ->Where('s.SerieInicial', $valera['SerieInicial'])
                ->first();

            if ($conciliado) {
                $valesConciliados =
                    $valesConciliados . "\n  - " . $valera['FolioSolicitud'];
            }
        }

        if (strlen($valesConciliados) > 0) {
            $response = [
                'success' => true,
                'results' => false,
                'message' =>
                    'Los siguientes folios no pueden ser devueltos porque se encuentran conciliados: ' .
                    $valesConciliados,
            ];
            return response()->json($response, 200);
        }

        $valesEntregados = '';
        foreach ($valesADevolver as $valera) {
            $entregado = DB::table('vales AS v')
                ->Select('v.id')
                ->Where('v.id', $valera['id'])
                ->WhereRaw('v.entrega_at IS NOT NULL')
                ->first();

            if ($entregado) {
                $valesEntregados =
                    $valesEntregados . "\n  - " . $valera['FolioSolicitud'];
            }
        }

        if (strlen($valesEntregados) > 0) {
            $response = [
                'success' => true,
                'results' => false,
                'message' =>
                    'Los siguientes folios no pueden ser devueltos porque están marcados como entregados : ' .
                    $valesEntregados,
            ];
            return response()->json($response, 200);
        }

        $infoDevueltos = [
            'idUsuarioCarga' => $user->id,
            'FechaCarga' => date('Y-m-d H:i:s'),
            'TotalDevueltos' => count($valesADevolver),
        ];

        $idCarga = DB::table('vales_carga_devueltos')->insertGetId(
            $infoDevueltos
        );

        foreach ($valesADevolver as $valera) {
            $sol = DB::table('vales_solicitudes AS s')
                ->Where('s.id', $valera['idSol'])
                ->first();
            $solicitud = (array) $sol;
            $solicitud['UserDeleted'] = $user->id;
            $solicitud['deleted_at'] = date('Y-m-d H:i:s');
            $solicitud['idCargaDevueltos'] = $idCarga;
            DB::beginTransaction();
            DB::table('vales_solicitudes_devueltos')->insert($solicitud);
            DB::commit();
            DB::beginTransaction();
            DB::table('vales AS v')
                ->Where('id', $valera['id'])
                ->update([
                    'idIncidencia' => 7,
                    'UserUpdated' => $user->id,
                    'updated_at' => date('Y-m-d H:i:s'),
                    'Devuelto' => 1,
                ]);
            DB::commit();
            DB::beginTransaction();
            DB::table('vales_solicitudes')
                ->where('id', $valera['idSol'])
                ->delete();
            DB::commit();
        }

        $response = [
            'success' => true,
            'results' => true,
            'message' => 'Valeras devueltas correctamente',
            'idCarga' => $idCarga,
        ];
        return response()->json($response, 200);
    }

    public function getReporteDevueltos(Request $request)
    {
        $params = $request->all();
        $user = auth()->user();
        $query =
            'v.id AS idVale,' .
            'CONCAT(lpad(hex(v.id),6,0)," ") AS FolioSolicitud, ' .
            'r.Remesa, ' .
            'sol.SerieInicial,' .
            'sol.SerieFinal,' .
            'v.FechaSolicitud, ' .
            'v.CURP, ' .
            'v.Nombre, ' .
            'v.Paterno, ' .
            'v.Materno, ' .
            'm.SubRegion AS Region,' .
            'm.Nombre AS Municipio,' .
            'l.Nombre AS Localidad,' .
            'v.Colonia, ' .
            'v.Calle, ' .
            'v.NumExt, ' .
            'v.NumInt, ' .
            'v.CP, ' .
            'v.TelCelular, ' .
            'v.TelFijo, ' .
            'i.Incidencia, ' .
            'v.ResponsableEntrega, ' .
            'CASE WHEN v.isEntregado = 1 THEN "SI" ELSE "NO" END AS Entregado, ' .
            '"DEVUELTO" AS Devuelto';

        $res = DB::table('vales AS v')
            ->selectRaw($query)
            ->JOIN('vales_remesas AS r', 'v.Remesa', 'r.Remesa')
            ->LEFTJOIN(
                'vales_solicitudes_devueltos AS sol',
                'sol.idSolicitud',
                'v.id'
            )
            ->JOIN('et_cat_municipio AS m', 'm.id', 'v.idMunicipio')
            ->JOIN('et_cat_localidad_2022 AS l', 'l.id', 'v.idLocalidad')
            ->LEFTJOIN('vales_status AS s', 's.id', 'v.idStatus')
            ->LEFTJOIN('vales_incidencias AS i', 'i.id', 'v.idIncidencia')
            ->WHERE('r.Ejercicio', '2023')
            ->Where([
                'sol.idCargaDevueltos' => $params['Carga'],
            ])
            ->orderBy('sol.SerieInicial', 'asc');

        $data = $res->get();

        if (count($data) == 0) {
            $file =
                public_path() . '/archivos/formatoReporteSolicitudValesV3.xlsx';

            return response()->download(
                $file,
                'ValesDevueltos' . date('Y-m-d') . '.xlsx'
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
            public_path() . '/archivos/ValesDevueltos2023.xlsx'
        );

        $sheet = $spreadsheet->getActiveSheet();
        $largo = count($res);
        $sheet
            ->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_LETTER);

        $largo = count($res);

        //Llenar excel con el resultado del query
        $sheet->fromArray($res, null, 'A3');
        //Agregamos la fecha
        $sheet->setCellValue('C1', 'Fecha Reporte: ' . date('Y-m-d H:i:s'));

        $sheet->getDefaultRowDimension()->setRowHeight(-1);

        //guardamos el excel creado y luego lo obtenemos en $file para poder descargarlo
        $writer = new Xlsx($spreadsheet);
        $writer->save(
            'archivos/' .
                $user->id .
                '_' .
                $user->email .
                '_' .
                'ValesDevueltosEjercicio2023.xlsx'
        );
        $file =
            public_path() .
            '/archivos/' .
            $user->id .
            '_' .
            $user->email .
            '_' .
            'ValesDevueltosEjercicio2023.xlsx';
        $fecha = date('Y-m-d H-i-s');

        return response()->download(
            $file,
            'ValesDevueltosEjercicio2023_' .
                date('Y-m-d') .
                '_' .
                str_replace(' ', '_', $fecha) .
                '.xlsx'
        );
    }

    public function validateInput($value): bool
    {
        if (gettype($value) === 'array') {
            foreach ($value as $v) {
                $containsSpecialChars = preg_match(
                    '@[' . preg_quote("=%;-?!¡\"`+") . ']@',
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
}
