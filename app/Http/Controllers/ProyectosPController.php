<?php

namespace App\Http\Controllers;

use App\VNegociosFiltros;
use Carbon\Carbon as time;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use Illuminate\Contracts\Validation\ValidationException;

use Imagick;
use JWTAuth;
use Validator;

class ProyectosPController extends Controller
{
    public function getPermisos($id)
    {
        $permisos = DB::table('users_menus AS um')
            ->Select('um.idUser', 'um.Seguimiento', 'um.ViewAll')
            ->where(['um.idUser' => $id, 'um.idMenu' => 33])
            ->first();
        return $permisos;
    }

    public function getTotal(Request $request)
    {
        try {
            $user = auth()->user();
            $params = $request->all();

            $res = DB::table('solicitudes_proyectos AS p')
                ->SelectRaw('COUNT(p.id) AS Total')
                ->Where('p.Ejercicio', $params['Ejercicio'])
                ->Join('et_cat_municipio AS m', 'm.id', 'p.idMunicipio')
                ->whereRaw('p.FechaElimino IS NULL');

            $permisos = $this->getPermisos($user->id);
            $seguimiento = $permisos->Seguimiento;
            $viewall = $permisos->ViewAll;
            $filtroPermisos = '';

            if ($viewall < 1 && $seguimiento < 1) {
                $filtroPermisos = 'p.idUsuarioCreo = ' . $user->id;
            } elseif ($viewall < 1) {
                $filtroPermisos =
                    'm.SubRegion IN (' .
                    'SELECT Region FROM users_region WHERE idUser = ' .
                    $user->id .
                    ')';
            }

            if ($filtroPermisos !== '') {
                $res->whereRaw($filtroPermisos);
            }

            $capturadas = $res->first();
            $total = 0;

            if ($capturadas) {
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

    public function getPendientes(Request $request)
    {
        try {
            $user = auth()->user();
            $params = $request->all();
            $res = DB::table('solicitudes_proyectos AS p')
                ->selectRaw('COUNT(p.id) AS Total')
                ->Where('p.Ejercicio', $params['Ejercicio'])
                ->JOIN('et_cat_municipio AS m', 'm.id', 'p.idMunicipio')
                ->where('p.idEstatusSolicitud', 1)
                ->whereNull('p.FechaElimino');
            $permisos = $this->getPermisos($user->id);
            $seguimiento = $permisos->Seguimiento;
            $viewall = $permisos->ViewAll;
            $filtroPermisos = '';

            if ($viewall < 1 && $seguimiento < 1) {
                $filtroPermisos = 'p.idUsuarioCreo = ' . $user->id;
            } elseif ($viewall < 1) {
                $filtroPermisos =
                    'm.SubRegion IN (' .
                    'SELECT Region FROM users_region WHERE idUser = ' .
                    $user->id .
                    ')';
            }

            if ($filtroPermisos !== '') {
                $res->whereRaw($filtroPermisos);
            }

            $pendientes = $res->first();
            $total = 0;

            if ($pendientes) {
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

    public function getObservadas(Request $request)
    {
        try {
            $user = auth()->user();
            $params = $request->all();
            $res = DB::table('solicitudes_proyectos AS p')
                ->selectRaw('COUNT(p.id) AS Total')
                ->Where('p.Ejercicio', $params['Ejercicio'])
                ->JOIN('et_cat_municipio AS m', 'm.id', 'p.idMunicipio')
                ->where('p.idEstatusSolicitud', 11)
                ->whereNull('p.FechaElimino');
            $permisos = $this->getPermisos($user->id);
            $seguimiento = $permisos->Seguimiento;
            $viewall = $permisos->ViewAll;
            $filtroPermisos = '';

            if ($viewall < 1 && $seguimiento < 1) {
                $filtroPermisos = 'p.idUsuarioCreo = ' . $user->id;
            } elseif ($viewall < 1) {
                $filtroPermisos =
                    'm.SubRegion IN (' .
                    'SELECT Region FROM users_region WHERE idUser = ' .
                    $user->id .
                    ')';
            }

            if ($filtroPermisos !== '') {
                $res->whereRaw($filtroPermisos);
            }

            $observadas = $res->first();
            $total = 0;
            if ($observadas) {
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

    public function getValidas(Request $request)
    {
        try {
            $user = auth()->user();
            $res = DB::table('solicitudes_proyectos AS p')
                ->selectRaw('COUNT(p.id) AS Total')
                ->JOIN('et_cat_municipio AS m', 'm.id', 'p.idMunicipio')
                ->where('p.idEstatusSolicitud', 12)
                ->whereNull('p.FechaElimino');
            $permisos = $this->getPermisos($user->id);
            $seguimiento = $permisos->Seguimiento;
            $viewall = $permisos->ViewAll;
            $filtroPermisos = '';

            if ($viewall < 1 && $seguimiento < 1) {
                $filtroPermisos = 'p.idUsuarioCreo = ' . $user->id;
            } elseif ($viewall < 1) {
                $filtroPermisos =
                    'm.SubRegion IN (' .
                    'SELECT Region FROM users_region WHERE idUser = ' .
                    $user->id .
                    ')';
            }

            if ($filtroPermisos !== '') {
                $res->whereRaw($filtroPermisos);
            }

            $validadas = $res->first();
            $total = 0;

            if ($validadas) {
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

    function getSolicitud(Request $request, $id)
    {
        try {
            $user = auth()->user();
            $programa = 3;

            $solicitud = DB::table('solicitudes_proyectos AS c')
                ->SELECT(
                    'c.id',
                    'c.FechaSolicitud',
                    'c.FolioTarjetaImpulso',
                    'c.FolioSDES',
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
                    'c.idEnlace',
                    'c.Enlace',
                    'c.idEstatusSolicitud',
                    'm.SubRegion As Region',
                    'p.Nombre AS NombreProyecto',
                    'p.idTipoProyecto',
                    'p.idSector',                    
                    'p.EmpleosAutogenerados',
                    'p.EmpleosActualesHombres',
                    'p.EmpleosActualesMujeres',
                    'p.EmpleosColocadosHombres',
                    'p.EmpleosColocadosMujeres',
                    'p.MontoSolicitado',
                    'p.idMunicipio AS idMunicipioProyecto',
                    'p.idLocalidad AS idLocalidadProyecto',
                    'p.CP AS CPProyecto',
                    'p.Colonia AS ColoniaProyecto',
                    'p.Calle AS CalleProyecto',
                    'p.NumExt AS NumExtProyecto'                    
                )
                ->join('et_cat_municipio AS m', 'c.idMunicipio', 'm.id')
                ->LeftJoin('tbl_proyectos AS p','p.idSolicitud','c.id')                
                ->Where('c.id', $id)
                ->WhereNull('c.FechaElimino')
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
            $user = auth()->user();
            $programa = 3;
            $parameters_serializado = serialize($params);
            $tabla = 'solicitudes_proyectos AS c';

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

            if ($viewall < 1 && $seguimiento < 1) {
                $filtroPermisos = 'c.idUsuarioCreo = ' . $user->id;
            } elseif ($viewall < 1) {
                $filtroPermisos =
                    '(m.SubRegion IN (' .
                    'SELECT Region FROM users_region WHERE idUser = ' .
                    $user->id .
                    ')' .
                    ')';
            }

            $solicitudes = DB::table($tabla)
                ->SELECT(
                    DB::RAW('LPAD(HEX(c.id),6,0) AS FolioSolicitud'),
                    'c.id',
                    'c.idEstatusSolicitud',
                    'c.FolioSDES',
                    'e.Estatus',
                    'c.Nombre',
                    'p.Nombre AS NombreProyecto',
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
                ->Join('users AS creadores', 'creadores.id', 'c.idUsuarioCreo')
                ->LeftJoin(
                    'users AS editores',
                    'editores.id',
                    'c.idUsuarioActualizo'
                )
                ->Join(
                    'solicitudes_status AS e',
                    'e.id',
                    'c.idEstatusSolicitud'
                )
                ->LeftJoin('tbl_proyectos as p', 'p.idSolicitud', 'c.id')
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

            // dd(
            //     str_replace_array(
            //         '?',
            //         $solicitudes->getBindings(),
            //         $solicitudes->toSql()
            //     )
            // );

            $total = $solicitudes->count();
            $page = $params['page'];
            $pageSize = $params['pageSize'];
            $startIndex = $page * $pageSize;

            $solicitudes = $solicitudes
                ->offset($startIndex)
                ->take($pageSize)
                ->orderby('c.id', 'desc')
                ->get();

            $filtro_usuario = VNegociosFiltros::where('idUser', '=', $user->id)
                ->where('api', '=', 'getProyectosP')
                ->first();

            if ($filtro_usuario) {
                $filtro_usuario->parameters = $parameters_serializado;
                $filtro_usuario->updated_at = time::now();
                $filtro_usuario->update();
            } else {
                $objeto_nuevo = new VNegociosFiltros();
                $objeto_nuevo->api = 'getProyectosP';
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

    public function getSolicitudesReporte(Request $request)
    {
        $user = auth()->user();
        $table = 'solicitudes_proyectos AS c';
        $res = DB::table($table)
            ->select(
                DB::raw('LPAD(HEX(c.id),6,0) as id'),
                'c.FolioSDES',                
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
                'c.Correo',
                'p.Nombre AS NombreProyecto',
                'tp.Tipo',
                'se.Sector',
                'p.EmpleosAutogenerados',
                'p.EmpleosActualesHombres',
                'p.EmpleosActualesMujeres',
                'p.EmpleosColocadosHombres',
                'p.EmpleosColocadosMujeres',
                'p.MontoSolicitado',
                'mp.Nombre AS MunicipioProyecto',
                // 'lp.Nombre AS LocalidadProyecto',
                // 'p.CP AS CPProyecto',
                // 'p.Colonia AS ColoniaProyecto',
                // 'p.Calle AS CalleProyecto',
                // 'p.NumExt AS NumExtProyecto',
                'e.Enlace',                
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
            ->LeftJoin(
                'users AS editor',
                'editor.id',
                '=',
                'c.idUsuarioActualizo'
            )
            ->JOIN('et_cat_municipio AS m', 'm.id', 'c.idMunicipio')
            ->JOIN('et_cat_localidad_2022 AS l', 'l.id', 'c.idLocalidad')
            ->JOIN('solicitudes_status AS s', 'c.idEstatusSolicitud', 's.id')
            ->LeftJoin('tbl_proyectos AS p', 'p.idSolicitud', 'c.id')
            ->LeftJoin('cat_tipo_proyecto AS tp', 'tp.id', 'p.idTipoProyecto')
            ->LeftJoin('cat_sectores_economicos AS se', 'se.id', 'p.idSector')
            ->LeftJoin('cat_enlaces_proyectos As e', 'e.id', 'c.idEnlace')
            ->LeftJoin('et_cat_municipio AS mp', 'mp.id', 'p.idMunicipio')
            ->LeftJoin('et_cat_localidad As lp', 'lp.id', 'p.idLocalidad')            
            ->whereRaw('c.FechaElimino IS NULL');

        $permisos = $this->getPermisos($user->id);
        $seguimiento = $permisos->Seguimiento;
        $viewall = $permisos->ViewAll;
        $filtroPermisos = '';

        if ($viewall < 1 && $seguimiento < 1) {
            $filtroPermisos = '(c.idUsuarioCreo = ' . $user->id . ')';
        } elseif ($viewall < 1) {
            $filtroPermisos =
                '(m.SubRegion IN (' .
                'SELECT Region FROM users_region WHERE idUser = ' .
                $user->id .
                ')' .
                ')';
        }
        //agregando los filtros seleccionados
        $filterQuery = '';
        $municipioRegion = [];
        $mun = [];
        $filtro_usuario = VNegociosFiltros::where('idUser', '=', $user->id)
            ->where('api', '=', 'getProyectosP')
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
            ->orderBy('c.Colonia', 'asc')
            ->orderBy('c.Calle', 'asc')
            ->orderBy('c.Paterno', 'asc')
            ->orderBy('c.Materno', 'asc')
            ->orderBy('c.Nombre', 'asc')
            ->get();

        if (count($data) == 0) {
            $file = public_path() . '/archivos/formatoReporteProyectosP.xlsx';
            return response()->download(
                $file,
                'SolicitudesProyectos_' . date('Y-m-d H:i:s') . '.xlsx'
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
            public_path() . '/archivos/formatoReporteProyectosP.xlsx'
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
        $writer->save('archivos/' . $user->email . 'SolicitudesProyectos.xlsx');
        $file =
            public_path() .
            '/archivos/' .
            $user->email .
            'SolicitudesProyectos.xlsx';

        return response()->download(
            $file,
            $user->email .
                'SolicitudesProyectos' .
                date('Y-m-d H:i:s') .
                '.xlsx'
        );
    }

    public function getMunicipios(Request $request)
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

        if ($viewall < 1) {
            $query = DB::table('et_cat_municipio AS m')
                ->select('m.Id', 'm.Nombre', 'm.SubRegion')
                ->join('users_region AS r', 'r.Region', 'm.Subregion')
                ->where(['r.idUser' => $user->id]);
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
                ->whereIn('idPrograma', [0, 3])
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
            if ($file->idEstatus === 12) {
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
                    DB::table('solicitudes_proyectos')
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

    public function changeFiles(Request $request)
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
                    'idPrograma' => 3,
                ])
                ->WhereNull('FechaElimino')
                ->first();
            if (!$observadas) {
                DB::table('solicitudes_proyectos')
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
                'idPrograma' => 3,
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

    public function saveNewFiles(Request $request)
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
                        'idPrograma' => 3,
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
                ->where('idPrograma', 3)
                ->where('idEstatus', '<>', 3)
                ->WhereNull('FechaElimino')
                ->first();

            if (!$aprobadas) {
                DB::table('solicitudes_proyectos')
                    ->where('id', $id)
                    ->update([
                        'idEstatusSolicitud' => 1,
                    ]);
            }

            DB::beginTransaction();
            $this->createFiles($id, $request->NewFiles, $newClasificacion);
            DB::commit();

            if ($this->validateExpediente($id)) {
                DB::table('solicitudes_proyectos')
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
                'idPrograma' => 3,
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

    public function validateExpediente($id)
    {
        $expediente = DB::table('solicitudes_proyectos AS c')
            ->Select(
                'c.id',
                'solicitud.idSolicitud AS solicitud',
                'ine.idSolicitud AS ine',
                'comp.idSolicitud AS comprobante',
                'curp.idSolicitud AS curp',
                'ct2.idSolicitud AS ct02',
                'ct3.idSolicitud AS ct03',
                'cot.idSolicitud AS cot',
                'rfc.idSolicitud AS rfc',
                'carta.idSolicitud AS carta',
                'foto.idSolicitud AS foto'
            )
            ->LeftJoin(
                DB::RAW(
                    '(SELECT idSolicitud FROM solicitudes_archivos WHERE idPrograma = 3 AND FechaElimino IS NULL AND idSolicitud = ' .
                        $id .
                        ' AND idClasificacion = 1 ) AS solicitud'
                ),
                'solicitud.idSolicitud',
                'c.id'
            )
            ->LeftJoin(
                DB::RAW(
                    '(SELECT idSolicitud FROM solicitudes_archivos WHERE idPrograma = 3 AND FechaElimino IS NULL AND idSolicitud = ' .
                        $id .
                        ' AND idClasificacion = 2 ) AS ine'
                ),
                'ine.idSolicitud',
                'c.id'
            )
            ->LeftJoin(
                DB::RAW(
                    '(SELECT idSolicitud FROM solicitudes_archivos WHERE idPrograma = 3 AND FechaElimino IS NULL AND idSolicitud = ' .
                        $id .
                        ' AND idClasificacion = 3 ) AS comp'
                ),
                'comp.idSolicitud',
                'c.id'
            )
            ->LeftJoin(
                DB::RAW(
                    '(SELECT idSolicitud FROM solicitudes_archivos WHERE idPrograma = 3 AND FechaElimino IS NULL AND idSolicitud = ' .
                        $id .
                        ' AND idClasificacion = 11 ) AS curp'
                ),
                'curp.idSolicitud',
                'c.id'
            )
            ->LeftJoin(
                DB::RAW(
                    '(SELECT idSolicitud FROM solicitudes_archivos WHERE idPrograma = 3 AND FechaElimino IS NULL AND idSolicitud = ' .
                        $id .
                        ' AND idClasificacion = 19 ) AS ct2'
                ),
                'ct2.idSolicitud',
                'c.id'
            )
            ->LeftJoin(
                DB::RAW(
                    '(SELECT idSolicitud FROM solicitudes_archivos WHERE idPrograma = 3 AND FechaElimino IS NULL AND idSolicitud = ' .
                        $id .
                        ' AND idClasificacion = 14 ) AS ct3'
                ),
                'ct3.idSolicitud',
                'c.id'
            )
            ->LeftJoin(
                DB::RAW(
                    '(SELECT idSolicitud FROM solicitudes_archivos WHERE idPrograma = 3 AND FechaElimino IS NULL AND idSolicitud = ' .
                        $id .
                        ' AND idClasificacion = 12 ) AS cot'
                ),
                'cot.idSolicitud',
                'c.id'
            )
            ->LeftJoin(
                DB::RAW(
                    '(SELECT idSolicitud FROM solicitudes_archivos WHERE idPrograma = 3 AND FechaElimino IS NULL AND idSolicitud = ' .
                        $id .
                        ' AND idClasificacion = 20 ) AS rfc'
                ),
                'rfc.idSolicitud',
                'c.id'
            )
            ->LeftJoin(
                DB::RAW(
                    '(SELECT idSolicitud FROM solicitudes_archivos WHERE idPrograma = 3 AND FechaElimino IS NULL AND idSolicitud = ' .
                        $id .
                        ' AND idClasificacion = 22 ) AS carta'
                ),
                'carta.idSolicitud',
                'c.id'
            )
            ->LeftJoin(
                DB::RAW(
                    '(SELECT idSolicitud FROM solicitudes_archivos WHERE idPrograma = 3 AND FechaElimino IS NULL AND idSolicitud = ' .
                        $id .
                        ' AND idClasificacion = 21 ) AS foto'
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

        $datosInformante = DB::table('solicitudes_proyectos')
            ->select('idParentescoTutor')
            ->whereNull('FechaElimino')
            ->Where('id', $id)
            ->first();

        if ($datosInformante->idParentescoTutor !== null) {
            $archivoInformante = DB::table('solicitudes_archivos')
                ->Select('id')
                ->Where([
                    'idPrograma' => 3,
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

    public function create(Request $request)
    {
        try {
            $v = Validator::make($request->all(), [
                'CURP' => 'required',
                'Nombre' => 'required',
                'Paterno' => 'required',                
                'FechaINE' => 'required',             
                'idMunicipio' => 'required',
                'idLocalidad' => 'required',
                'CP' => 'required',
                'Colonia' => 'required',
                'Calle' => 'required',
                'NumExt' => 'required',
                'Celular' => 'required',  
                'idEnlace'=>'required',   
                'NombreProyecto'=>'required',
                "idTipoProyecto"=>'required',
                "idSector"=>'required',
                "EmpleosAutogenerados"=>'required',
                "EmpleosActualesHombres"=>'required',
                "EmpleosActualesMujeres"=>'required',
                "EmpleosColocadosHombres"=>'required',
                "EmpleosColocadosMujeres"=>'required',
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
            $year_start = idate('Y', strtotime('first day of January', time()));
            $params['Correo'] = isset($params['Correo'])
            ? $params['Correo']
            : null;
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

            if (isset($params['FechaINE'])) {
                $fechaINE = intval($params['FechaINE']);
                if ($fechaINE < $year_start) {
                    $response = [
                        'success' => true,
                        'results' => false,
                        'errors' =>
                            'La vigencia de la Identificación Oficial no cumple con los requisitos',
                    ];
                    return response()->json($response, 200);
                }
            }

            $curpRegistrado = DB::table('solicitudes_proyectos')
                ->select(DB::RAW('lpad( hex(id ), 6, 0 ) AS Folio'), 'CURP','Ejercicio')
                ->where('CURP', $params['CURP'])
                ->whereNull('FechaElimino')                
                ->first();

            if ($curpRegistrado !== null) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' =>
                        'El Beneficiario con CURP ' .
                        $params['CURP'] .
                        ' ya se encuentra registrado para el ejercicio ' .
                        $curpRegistrado->Ejercicio .
                        ' con el Folio ' .
                        $curpRegistrado->Folio,
                    'message' =>
                        'El Beneficiario con CURP ' .
                        $params['CURP'] .
                        ' ya se encuentra registrado para el ejercicio ' .
                        $curpRegistrado->Ejercicio .
                        ' con el Folio ' .
                        $curpRegistrado->Folio,
                ];

                return response()->json($response, 200);
            }

            $newSolicitud = [
                'FolioSDES'=>isset($params['FolioSDES'])?$params['FolioSDES']:null,
                'FechaSolicitud' => isset($params['FechaSolicitud'])
                    ? $params['FechaSolicitud']
                    : null,
                'FolioTarjetaImpulso'=> isset($params['FolioTarjetaImpulso'])
                    ? $params['FolioTarjetaImpulso']
                    : null,
                'Nombre' => $params['Nombre'],
                'Paterno' => $params['Paterno'],
                'Materno' => isset($params['Materno'])
                    ? $params['Materno']
                    : null,
                'FechaNacimiento' => isset($params['FechaNacimiento'])?$params['FechaNacimiento']:null,
                'Sexo' => isset($params['Sexo'])?$params['Sexo']:null,
                'idEntidadNacimiento'=> isset($params['idEntidadNacimiento'])?$params['idEntidadNacimiento']:null,
                'CURP' => $params['CURP'],
                'Celular' => $params['Celular'],
                'Correo' => isset($params['Correo'])?$params['Correo']:null,
                'Telefono' => isset($params['Telefono'])?$params['Telefono']:null,
                'Region'=>$params['Region'],
                'idEntidadVive' => 12,
                'idMunicipio' => $params['idMunicipio'],
                'idLocalidad' => $params['idLocalidad'],
                'CP' => $params['CP'],
                'Colonia' => $params['Colonia'],
                'Calle' => $params['Calle'],
                'NumExt' => $params['NumExt'],
                'NumInt' => isset($params['NumInt'])?$params['NumInt']:null,
                'Referencias' => isset($params['Referencias'])?$params['Referencias']:null,
                'idParentescoTutor'=> isset($params['idParentescoTutor'])?$params['idParentescoTutor']:null,
                'NombreTutor'=> isset($params['NombreTutor'])?$params['NombreTutor']:null,
                'PaternoTutor'=> isset($params['PaternoTutor'])?$params['PaternoTutor']:null,
                'MaternoTutor'=> isset($params['MaternoTutor'])?$params['MaternoTutor']:null,
                'CURPTutor'=> isset($params['CURPTutor'])?$params['CURPTutor']:null,                
                'CorreoTutor'=> isset($params['CorreoTutor'])?$params['CorreoTutor']:null,
                'TelefonoTutor'=> isset($params['TelefonoTutor'])?$params['TelefonoTutor']:null,
                'idUsuarioCreo' => $user->id,
                'FechaCreo' => date('Y-m-d H:i:s'),
                'idEstatusSolicitud' => 1,
                'ExpedienteCompleto' => 0,
                'Ejercicio' => $params['Ejercicio'],
                'idEnlace'=> $params['idEnlace'],
                'Formato'=>1,
                'FechaINE'=>$params['FechaINE'],                
            ];
            DB::beginTransaction();
            $id = DB::table('solicitudes_proyectos')->insertGetId($newSolicitud);            
            DB::commit();

            $newProyecto = [
                "idSolicitud"=>$id,
                "Nombre"=>$params['NombreProyecto'],
                "idTipoProyecto"=>$params['idTipoProyecto'],
                "idSector"=>$params['idSector'],
                "EmpleosAutogenerados"=>$params['EmpleosAutogenerados'],
                "EmpleosActualesHombres"=>$params['EmpleosActualesHombres'],
                "EmpleosActualesMujeres"=>$params['EmpleosActualesMujeres'],
                "EmpleosColocadosHombres"=>$params['EmpleosColocadosHombres'],
                "EmpleosColocadosMujeres"=>$params['EmpleosColocadosMujeres'],
                "MontoSolicitado"=>$params['MontoSolicitado'],
                "idMunicipio"=>$params['idMunicipioProyecto'],
                "idLocalidad"=>$params['idLocalidadProyecto'],
                "CP"=>$params['CPProyecto'],
                "Colonia"=>$params['ColoniaProyecto'],
                "Calle"=>$params['CalleProyecto'],
                "NumExt"=>$params['NumExtProyecto'],
                "idUsuarioCreo"=>$user->id,
                "FechaCreo"=>date('Y-m-d H:i:s'),                
            ];

            DB::beginTransaction();
            DB::table('tbl_proyectos')->insert($newProyecto);
            DB::commit();

            $folioSolicitud = str_pad(dechex($id), 6, '0', STR_PAD_LEFT);
            DB::beginTransaction();



            $nextInvoice = DB::table('proyectos_folios_sdes')
                            ->Select('Folio')
                            ->Where(["Ejercicio"=>$params['Ejercicio'],"idMunicipio"=>$params['idMunicipio']])
                            ->WhereRaw('FechaElimino IS NULL')
                            ->first();
            
            if(!$nextInvoice){
                $numFolio = 1;
            }else{
                $numFolio = $nextInvoice->Folio + 1;
            }

            $nuevoFolio = [
                "idSolicitud"=>$id,
                'idMunicipio' => $params['idMunicipio'],                
                'Folio' => $numFolio,                
                'Ejercicio' => $params['Ejercicio'],
                'idUsuarioCreo' => $user->id,
                'FechaCreo' => date('Y-m-d H:i:s'),
            ];
                        
            DB::commit();

            $dpnd = $params['Ejercicio'] < 2025 ? "SEDESHU" : "SNCO";

            $abreviacion = DB::table('et_cat_municipio')
                ->select('Abreviacion')
                ->where('id', $params['idMunicipio'])
                ->first();

            $_folioSDES = $dpnd . $abreviacion->Abreviacion . '/' .  str_pad($numFolio, 4, '0', STR_PAD_LEFT) . '/' . $params['Ejercicio'] ;

            DB::table('solicitudes_proyectos')
                ->where('id', $id)
                ->update([
                    'FolioSDES' => $_folioSDES,
                ]); 
            DB::commit();

            $response = [
                'success' => true,
                'results' => true,
                'message' =>
                    'Solicitud creada con éxito, FolioAPI: ' .
                    strtoupper($folioSolicitud). ' y FolioSDES: ' . $_folioSDES,
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

    public function update(Request $request)
    {
        try {
            $v = Validator::make($request->all(), [
                'id' => 'required',
                'Nombre' => 'required',
                'Paterno' => 'required',                
                'CURP' => 'required',
                'idMunicipio' => 'required',
                'idLocalidad' => 'required',
                'CP' => 'required',
                'Colonia' => 'required',
                'Calle' => 'required',
                'NumExt' => 'required',
                'NombreProyecto' => 'required',
                'idTipoProyecto' => 'required',                          
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
            
            $solicitud = DB::table('solicitudes_proyectos')
                ->select(
                    'solicitudes_proyectos.idEstatusSolicitud',
                    'solicitudes_proyectos.ExpedienteCompleto',
                    'solicitudes_proyectos.id',
                    'solicitudes_proyectos.CURP'
                )
                ->where('solicitudes_proyectos.id', $params['id'])
                ->first();

            if ($solicitud->idEstatusSolicitud === 12) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' =>
                        'La solicitud se encuentra validada no se puede editar',
                ];
                return response()->json($response, 200);
            }

            if($params['CURP'] != $solicitud->CURP){
                $curpRegistrado = DB::table('solicitudes_proyectos')
                ->select(DB::RAW('lpad( hex(id ), 6, 0 ) AS Folio'), 'CURP','Ejercicio')
                ->where('CURP', $params['CURP'])
                ->whereNull('FechaElimino')
                ->first();

                if($curpRegistrado){
                    $response = [
                        'success' => true,
                        'results' => false,
                        'errors' =>
                            'El Beneficiario con CURP ' .
                            $params['CURP'] .
                            ' ya se encuentra registrado para el ejercicio ' .
                            $curpRegistrado->Ejercicio .
                            ' con el Folio ' .
                            $curpRegistrado->Folio,
                        'message' =>
                            'El Beneficiario con CURP ' .
                            $params['CURP'] .
                            ' ya se encuentra registrado para el ejercicio ' .
                            $curpRegistrado->Ejercicio .
                            ' con el Folio ' .
                            $curpRegistrado->Folio,
                    ];

                    return response()->json($response, 200);
                }
            }

            if (
                isset($params['idEstatusSolicitud']) &&
                $params['idEstatusSolicitud'] == 12
            ) {
                if ($solicitud->ExpedienteCompleto === 0) {
                    $response = [
                        'success' => true,
                        'results' => false,
                        'message' =>
                            'El expediente digital aún no esta completo',
                        'errors' =>
                            'El expediente digital aún no esta completo',
                    ];
                    return response()->json($response, 200);
                }

                $archivosObservados = DB::table('solicitudes_archivos AS a')
                    ->select('a.id')
                    ->whereNull('a.FechaElimino')
                    ->where([
                        'a.idSolicitud' => $params['id'],
                        'a.idEstatus' => 2,
                        'idPrograma' => 3,
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

            $id = $params['id'];
            $region = DB::table('et_cat_municipio')
                ->where('id', $params['idMunicipio'])
                ->first();

            if ($region != null) {
                $params['Region'] = $region->SubRegion;
            }

            $updatedRegister = [
               'FolioSDES'=>isset($params['FolioSDES'])?$params['FolioSDES']:null,
                'FechaSolicitud' => isset($params['FechaSolicitud'])
                    ? $params['FechaSolicitud']
                    : null,
                'FolioTarjetaImpulso'=> isset($params['FolioTarjetaImpulso'])
                    ? $params['FolioTarjetaImpulso']
                    : null,
                'CURP' => $params['CURP'],
                'Nombre' => $params['Nombre'],
                'Paterno' => $params['Paterno'],
                'Materno' => isset($params['Materno'])
                    ? $params['Materno']
                    : null,
                'FechaNacimiento' => isset($params['FechaNacimiento'])?$params['FechaNacimiento']:null,
                'Edad'=> $params['Edad'],
                'Sexo' => isset($params['Sexo'])?$params['Sexo']:null,
                'FechaINE'=>$params['FechaINE'],                
                'idEntidadNacimiento'=> isset($params['idEntidadNacimiento'])?$params['idEntidadNacimiento']:null,                
                'Celular' => $params['Celular'],
                'Correo' => isset($params['Correo'])?$params['Correo']:null,
                'Telefono' => isset($params['Telefono'])?$params['Telefono']:null,
                'Region'=>$params['Region'],
                'idEntidadVive' => 12,
                'idMunicipio' => $params['idMunicipio'],
                'idLocalidad' => $params['idLocalidad'],
                'CP' => $params['CP'],
                'Colonia' => $params['Colonia'],
                'Calle' => $params['Calle'],
                'NumExt' => $params['NumExt'],
                'NumInt' => isset($params['NumInt'])?$params['NumInt']:null,
                'Referencias' => isset($params['Referencias'])?$params['Referencias']:null,
                'idParentescoTutor'=> isset($params['idParentescoTutor'])?$params['idParentescoTutor']:null,
                'NombreTutor'=> isset($params['NombreTutor'])?$params['NombreTutor']:null,
                'PaternoTutor'=> isset($params['PaternoTutor'])?$params['PaternoTutor']:null,
                'MaternoTutor'=> isset($params['MaternoTutor'])?$params['MaternoTutor']:null,
                'CURPTutor'=> isset($params['CURPTutor'])?$params['CURPTutor']:null,                                
                'TelefonoTutor'=> isset($params['TelefonoTutor'])?$params['TelefonoTutor']:null,
                'idEnlace'=> $params['idEnlace'],
                'idUsuarioActualizo' => $user->id,
                'FechaActualizo' => date('Y-m-d H:i:s'),
                'idEstatusSolicitud' => isset($params['idEstatusSolicitud'])
                    ? $params['idEstatusSolicitud']
                    : 1,           
                'Region' => $params['Region']
            ];

            DB::table('solicitudes_proyectos')
                ->where('id', $id)
                ->update($updatedRegister);

            $updatedProject = [
                'Nombre' => $params['NombreProyecto'],
                'idTipoProyecto' => $params['idTipoProyecto'],
                'idSector' => $params['idSector'],
                'EmpleosAutogenerados' => $params['EmpleosAutogenerados'],
                'EmpleosActualesHombres' => $params['EmpleosActualesHombres'],
                'EmpleosActualesMujeres' => $params['EmpleosActualesMujeres'],
                'EmpleosColocadosHombres' => $params['EmpleosColocadosHombres'],
                'EmpleosColocadosMujeres' => $params['EmpleosColocadosMujeres'],
                'MontoSolicitado' => $params['MontoSolicitado'],
                'idMunicipio'=>$params['idMunicipioProyecto'],
                'idLocalidad' => $params['idLocalidadProyecto'],
                'CP' => $params['CPProyecto'],
                'Colonia' => $params['ColoniaProyecto'],
                'Calle'=>$params['CalleProyecto'],
                'NumExt'=>$params['NumExtProyecto']
            ];

            DB::table('tbl_proyectos')
            ->where('idSolicitud',$id)
            ->update($updatedProject);

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

            $solicitud = DB::table('solicitudes_proyectos AS c')
                ->select('c.id', 'c.idEstatusSolicitud')
                ->where('c.id', $id)
                ->first();
            if ($solicitud->idEstatusSolicitud === 12) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'message' =>
                        'No se puede eliminar la solicitud, se encuentra validada',
                    'data' => [],
                ];
                return response()->json($response, 200);
            }

            DB::beginTransaction();
            DB::table('solicitudes_archivos AS c')
                ->where('c.idSolicitud', $id)
                ->where('c.idPrograma', 3)
                ->whereNull('FechaElimino')
                ->update([
                    'idUsuarioElimino' => $user->id,
                    'FechaElimino' => date('Y-m-d H:i:s'),
                ]);
            DB::table('solicitudes_proyectos AS c')
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

    public function createCotizacion(Request $request)
    {
        $v = Validator::make($request->all(), [
            'idSolicitud' => 'required',
            'productos' => 'required',
            'archivo' => 'required',
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
        $productos = json_decode($params['productos']);

        try {
            $solicitud = DB::table('solicitudes_proyectos AS c')
                ->select('c.id', 'c.idEstatusSolicitud')
                ->whereNull('FechaElimino')
                ->where('c.id', $params['idSolicitud'])
                ->first();
            if ($solicitud->idEstatusSolicitud === 12) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'message' =>
                        'La solicitud ya fue validada, no puede agregar más cotizaciones',
                    'data' => [],
                ];
                return response()->json($response, 200);
            }

            DB::beginTransaction();
            if (isset($params['archivo'])) {
                $idFiles = $this->createFileCotizacion(
                    $params['idSolicitud'],
                    $request->archivo
                );
            }
            DB::commit();

            $dataCotizacion = [
                'idArchivo' => $idFiles,
                'FolioCotizacion' => $params['folioCotizacion'],
                'Subtotal' => $params['subtotal'],
                'Iva' => $params['iva'],
                'Total' => $params['total'],
                'idUsuarioCreo' => $user->id,
                'FechaCreo' => date('Y-m-d H:i:s'),
            ];

            DB::beginTransaction();
            $id = DB::table('solicitudes_proyectos_cotizaciones')->insertGetId(
                $dataCotizacion
            );
            DB::commit();

            $dataProductos = [];
            foreach ($productos as $producto) {
                $dataProductos[] = [
                    'idCotizacion' => $id,
                    'Producto' => $producto->producto,
                    'Precio' => $producto->precio,
                    'Cantidad' => $producto->cantidad,
                ];
            }

            DB::beginTransaction();
            DB::table('solicitudes_proyectos_productos')->insert(
                $dataProductos
            );
            DB::commit();

            $response = [
                'success' => true,
                'results' => true,
                'message' => 'Cotización creada con éxito',
            ];

            return response()->json($response, 200);
        } catch (QueryException $errors) {
            DB::rollBack();
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

    private function createFileCotizacion($idSolicitud, $files)
    {
        $user = auth()->user();
        $img = new Imagick();
        $width = 1920;
        $height = 1920;
        foreach ($files as $key => $file) {
            $originalName = $file->getClientOriginalName();
            $extension = explode('.', $originalName);
            $extension = $extension[count($extension) - 1];
            $uniqueName = uniqid() . '.' . $extension;
            $size = $file->getSize();
            $fileObject = [
                'idSolicitud' => intval($idSolicitud),
                'idClasificacion' => 12,
                'idPrograma' => 3,
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
            $idFile = DB::table('solicitudes_archivos')->insertGetId(
                $fileObject
            );

            return $idFile;
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
            $res = DB::table('solicitudes_proyectos as N')
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
            $pdf = \PDF::loadView('pdf_solicitud_p', compact('calentadores'));
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

    public function getEjercicio(Request $request){
        try{
            $ejercicio = DB::table('cat_ejercicio_fiscal')->Select('Ejercicio AS value','Ejercicio AS label')->get();
            $response = [
                'success' => true,
                'results' => true,
                'data' => $ejercicio,
            ];
        }catch(QueryException $errors){
            $response = [
                'success' => false,
                'results' => false,
                'total' => 0,
                'errors' => $errors->getMessage(),
                'message' => 'Ha ocurrido un error, consulte al administrador',
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
        DB::table('solicitudes_proyectos')
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
}
