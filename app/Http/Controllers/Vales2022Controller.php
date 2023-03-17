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
class Vales2022Controller extends Controller
{
    function getPermisos()
    {
        $user = auth()->user();

        $permisos = DB::table('users_menus')
            ->where(['idUser' => $user->id, 'idMenu' => '23'])
            ->get()
            ->first();
        if ($permisos !== null) {
            return $permisos;
        } else {
            return null;
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

    function getSolicitudes(Request $request)
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

            $seguimiento = $permisos->Seguimiento;
            $viewall = $permisos->ViewAll;
            $filtroCapturo = '';

            $solicitudes = DB::table('vales')

                ->selectRaw(
                    'vales.id,' .
                        'lpad(hex(vales.id),6,0) AS FolioSolicitud, ' .
                        'c.Folio, ' .
                        'r.RemesaSistema AS Remesa, ' .
                        'vales.FechaSolicitud, ' .
                        'vales.Nombre, ' .
                        'vales.Paterno, ' .
                        'vales.Materno, ' .
                        'm.SubRegion AS Region,' .
                        'm.Nombre AS Municipio,' .
                        'vales.Calle, ' .
                        'vales.Calle, ' .
                        'vales.Colonia, ' .
                        'vales.NumExt, ' .
                        'vales.NumInt, ' .
                        'vales.CP, ' .
                        'i.Incidencia, ' .
                        'CASE WHEN vales.isEntregado = 1 THEN "SI" ELSE "NO" END AS Entregado, ' .
                        'vales.entrega_at AS FechaEntrega, ' .
                        'vales.CURP, ' .
                        'vales.TelCelular, ' .
                        "CONCAT_WS( ' ', responsable.Nombre, responsable.Paterno, responsable.Materno ) AS Responsable"
                )
                ->JOIN('vales_remesas AS r', 'vales.Remesa', 'r.Remesa')
                ->JOIN(
                    DB::raw(
                        '(SELECT * FROM cedulas_solicitudes WHERE FechaElimino IS NULL AND idVale IS NOT NULL) AS c'
                    ),
                    'c.idVale',
                    'vales.id'
                )
                ->JOIN('et_cat_municipio AS m', 'm.id', 'vales.idMunicipio')
                ->JOIN(
                    'users AS responsable',
                    'responsable.id',
                    'vales.UserOwned'
                )
                ->LEFTJOIN(
                    'vales_incidencias AS i',
                    'i.id',
                    'vales.idIncidencia'
                )
                ->WHERERAW('vales.Ejercicio = 2022');

            if ($viewall < 1) {
                $region = DB::table('users_aplicativo_web')
                    ->selectRaw('idRegion')
                    ->where('idUser', $user->id)
                    ->get()
                    ->first();

                $solicitudes = $solicitudes->where(
                    'm.SubRegion',
                    $region->idRegion
                );
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
                ->OrderByRaw('r.RemesaSistema', 'DESC')
                ->OrderByRaw('r.Remesa', 'ASC')
                ->offset($startIndex)
                ->take($pageSize)
                ->get();

            $filtro_usuario = VNegociosFiltros::where('idUser', '=', $user->id)
                ->where('api', '=', 'getVales2022')
                ->first();

            if ($filtro_usuario) {
                $filtro_usuario->parameters = $parameters_serializado;
                $filtro_usuario->updated_at = time::now();
                $filtro_usuario->update();
            } else {
                $objeto_nuevo = new VNegociosFiltros();
                $objeto_nuevo->api = 'getVales2022';
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
                    'FolioSolicitud' => $data->FolioSolicitud,
                    'Folio' => $data->Folio,
                    'Remesa' => $data->Remesa,
                    'FechaSolicitud' => $data->FechaSolicitud,
                    'Nombre' => $data->Nombre,
                    'Paterno' => $data->Paterno,
                    'Materno' => $data->Materno,
                    'Region' => $data->Region,
                    'Municipio' => $data->Municipio,
                    'Calle' => $data->Calle,
                    'Colonia' => $data->Colonia,
                    'NumExt' => $data->NumExt,
                    'NumInt' => $data->NumInt,
                    'CP' => $data->CP,
                    'Incidencia' => $data->Incidencia,
                    'Entregado' => $data->Entregado,
                    'FechaEntrega' => $data->FechaEntrega,
                    'CURP' => $data->CURP,
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

    public function getReporteVales2022(Request $request)
    {
        ini_set('memory_limit', '-1');
        $user = auth()->user();
        $parameters['UserCreated'] = $user->id;
        $tableSol = 'vales';

        $res = DB::table('vales')

            ->selectRaw(
                'vales.id,' .
                    'lpad(hex(vales.id),6,0) AS FolioSolicitud, ' .
                    'c.Folio, ' .
                    'r.RemesaSistema AS Remesa, ' .
                    'sol.SerieInicial,' .
                    'sol.SerieFinal,' .
                    'vales.FechaSolicitud, ' .
                    'vales.CURP, ' .
                    'vales.Sexo, ' .
                    'vales.Nombre, ' .
                    'vales.Paterno, ' .
                    'vales.Materno, ' .
                    'vales.FechaNacimiento, ' .
                    'entidad.Entidad, ' .
                    'm.SubRegion AS Region,' .
                    'm.id AS idMunicipio,' .
                    'm.Nombre AS Municipio,' .
                    'LPAD(l.Numero ,3,0)AS NumLocalidad,' .
                    'l.CveInegi,' .
                    'l.Nombre AS Localidad,' .
                    'vales.Colonia, ' .
                    'vales.Calle, ' .
                    'vales.NumExt, ' .
                    'vales.NumInt, ' .
                    'vales.CP, ' .
                    'c.Latitud,' .
                    'c.Longitud,' .
                    'vales.TelCelular, ' .
                    'vales.TelFijo, ' .
                    'vales.CorreoElectronico, ' .
                    'i.Incidencia, ' .
                    'CASE WHEN vales.isEntregado = 1 THEN "SI" ELSE "NO" END AS Entregado, ' .
                    'vales.entrega_at AS FechaEntrega, ' .
                    's.Estatus,' .
                    "CONCAT_WS( ' ', responsable.Nombre, responsable.Paterno, responsable.Materno ) AS Responsable"
            )
            ->JOIN('vales_remesas AS r', 'vales.Remesa', 'r.Remesa')
            ->LEFTJOIN(
                'vales_solicitudes AS sol',
                'sol.idSolicitud',
                'vales.id'
            )
            ->JOIN(
                DB::raw(
                    '(SELECT * FROM cedulas_solicitudes WHERE FechaElimino IS NULL AND idVale IS NOT NULL) AS c'
                ),
                'c.idVale',
                'vales.id'
            )
            ->LEFTJOIN(
                'cat_entidad AS entidad',
                'c.idEntidadNacimiento',
                'entidad.id'
            )
            ->JOIN('et_cat_municipio AS m', 'm.id', 'vales.idMunicipio')
            ->JOIN('et_cat_localidad_2022 AS l', 'l.id', 'vales.idLocalidad')
            ->JOIN('users AS responsable', 'responsable.id', 'vales.UserOwned')
            ->LEFTJOIN('vales_status AS s', 's.id', 'vales.idStatus')
            ->LEFTJOIN('vales_incidencias AS i', 'i.id', 'vales.idIncidencia')
            ->WHERE('r.Ejercicio', '2022');

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

        if ($viewall < 1) {
            $region = DB::table('users_aplicativo_web')
                ->selectRaw('idRegion')
                ->where('idUser', $user->id)
                ->get()
                ->first();

            $res = $res->where('m.SubRegion', $region->idRegion);
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
            ->where('api', '=', 'getVales2022')
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
                            $id = 'c' . $id;
                        } elseif (in_array($id, $filtersRemesas)) {
                            $id = 'r.RemesaSistema';
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
            }
        }

        if ($filterQuery != '') {
            $res->whereRaw($filterQuery);
        }

        $data = $res
            ->orderBy('vales.Remesa', 'asc')
            ->orderBy('m.SubRegion', 'asc')
            ->orderBy('m.Nombre', 'asc')
            ->orderBy('l.Nombre', 'asc')
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
            public_path() . '/archivos/Vales2022.xlsx'
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
        $writer->save('archivos/' . $user->email . 'ValesEjercicio2022.xlsx');
        $file =
            public_path() .
            '/archivos/' .
            $user->email .
            'ValesEjercicio2022.xlsx';

        return response()->download(
            $file,
            $user->email . 'ValesEjercicio2022_' . date('Y-m-d H:i:s') . '.xlsx'
        );
    }

    public function getRemesas(Request $request)
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
            $res = DB::table('vales_remesas')
                ->distinct()
                ->where('Ejercicio', '2022');

            $res = $res->orderBy('RemesaSistema');

            $total = $res->count();
            $res = $res->get(['RemesaSistema']);

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
