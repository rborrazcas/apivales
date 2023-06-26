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
                $filtroPermisos = 'c.idUsuarioCreo = ' . $user->id;
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
                $filtroPermisos = 'c.idUsuarioCreo = ' . $user->id;
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
                $filtroPermisos = 'c.idUsuarioCreo = ' . $user->id;
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
                $filtroPermisos = 'c.idUsuarioCreo = ' . $user->id;
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

            $seguimiento = $permisos->Seguimiento;
            $viewall = $permisos->ViewAll;
            $filtroPermisos = '';
            $filtroSeguimiento = '';

            if ($viewall < 1 && $seguimiento < 1) {
                $filtroPermisos =
                    '(c.idMunicipio IN (' .
                    'SELECT idMunicipio FROM users_municipios WHERE idPrograma = 2 AND idUser = ' .
                    $user->id .
                    ')' .
                    ')';
                $filtroSeguimiento = 'c.idUsuarioCreo = ' . $user->id;
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
                'c.FechaActualizo'
            )
            ->JOIN('users AS creador', 'creador.id', '=', 'c.idUsuarioCreo')
            ->leftJoin(
                'users AS editor',
                'editor.id',
                '=',
                'c.idUsuarioActualizo'
            )
            ->JOIN('et_cat_municipio AS m', 'm.id', 'c.idMunicipio')
            ->JOIN('et_cat_localidad_2022 AS l', 'l.id', 'c.idLocalidad')
            ->JOIN('solicitudes_status AS s', 'c.idEstatusSolicitud', 's.id')
            ->whereRaw('c.FechaElimino IS NULL');

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
        $spreadsheet = $reader->load(
            public_path() . '/archivos/formatoReporteNominaCalentador.xlsx'
        );
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
                ->where(['idSolicitud' => $idSol, 'idEstatus' => 2])
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
                'idEntidadNacimiento' => 'required',
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
                        'Uno o más campos obligatorios están vaciós o no tiene el formato correcto',
                    'message' =>
                        'Uno o más campos obligatorios están vaciós o no tiene el formato correcto',
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

            $curpRegistrado = DB::table('solicitudes_calentadores')
                ->select(DB::RAW('lpad( hex(id ), 6, 0 ) AS Folio'), 'CURP')
                ->where('CURP', $params['CURP'])
                ->whereNull('FechaElimino')
                ->whereRaw('YEAR(FechaCreo) = ' . $year_start)
                ->first();

            if ($curpRegistrado !== null) {
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
                'NumInt' => 'required',
                'Referencias' => 'required',
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

            if ($solicitud->idEstatusSolicitud === 5) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' =>
                        'La solicitud se encuentra validada no se puede editar',
                ];
                return response()->json($response, 200);
            }
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
                ->select('c.id', 'c.idEstatusSolicitud')
                ->where('c.id', $id)
                ->first();
            if ($solicitud->idEstatusSolicitud === 5) {
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
}
