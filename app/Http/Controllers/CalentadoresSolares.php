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

            if ($viewall < 1 && $seguimiento < 1) {
                $filtroPermisos =
                    '(c.idMunicipio IN (' .
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
                    //'c.Responsable',
                    DB::RAW(
                        "CONCAT_WS(' ',creadores.Nombre,creadores.Paterno,creadores.Materno) AS Creador"
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

            // dd(
            //     str_replace_array(
            //         '?',
            //         $solicitudes->getBindings(),
            //         $solicitudes->toSql()
            //     )
            // );

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
        //$img = new Imagick();
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

            // if (
            //     in_array(mb_strtolower($extension, 'utf-8'), [
            //         'png',
            //         'jpg',
            //         'jpeg',
            //     ])
            // ) {
            //     //Ruta temporal para reducción de tamaño
            //     $file->move('subidos/tmp', $uniqueName);
            //     $img_tmp_path = sprintf('subidos/tmp/%s', $uniqueName);
            //     $img->readImage($img_tmp_path);
            //     $img->adaptiveResizeImage($width, $height);

            //     //Guardar en el nuevo storage
            //     $url_storage = Storage::disk('subidos')->path($uniqueName);
            //     $img->writeImage($url_storage);

            //     //Eliminar el archivo original después de guardar el archivo reducido
            //     File::delete($img_tmp_path);
            // } else {
            //     Storage::disk('subidos')->put(
            //         $uniqueName,
            //         File::get($file->getRealPath()),
            //         'public'
            //     );
            // }

            $file->move('subidos', $uniqueName);
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
            $idClasificacion = $params['newIdClasificacion'];

            $clasificacionRepetida = DB::table('solicitudes_archivos')
                ->where([
                    'idSolicitud' => $id,
                    'idClasificacion' => $idClasificacion,
                ])
                ->whereNull('FechaElimino')
                ->first();

            if ($clasificacionRepetida) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'message' =>
                        'La solicitud ya cuenta con un archivo en esta clasificación',
                    'data' => [],
                ];
                return response()->json($response, 200);
            }

            DB::beginTransaction();
            if (isset($request->NewFiles)) {
                $this->createFiles($id, $request->NewFiles, $idClasificacion);
            } else {
                $response = [
                    'success' => true,
                    'results' => false,
                    'message' => 'No se envió ningún archivo',
                    'data' => [],
                ];
                return response()->json($response, 200);
            }
            DB::commit();
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
        //$img = new Imagick();
        $width = 1920;
        $height = 1920;
        foreach ($files as $key => $file) {
            $originalName = $file->getClientOriginalName();
            $extension = explode('.', $originalName);
            $extension = $extension[count($extension) - 1];
            $uniqueName = uniqid() . '.' . $extension;
            $size = $file->getSize();
            $fileObject = [
                'idSolicitud' => intval($id),
                'idClasificacion' => intval($idClasificacion),
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

            // if (
            //     in_array(mb_strtolower($extension, 'utf-8'), [
            //         'png',
            //         'jpg',
            //         'jpeg',
            //     ])
            // ) {
            //     //Ruta temporal para reducción de tamaño
            //     $file->move('subidos/tmp', $uniqueName);
            //     $img_tmp_path = sprintf('subidos/tmp/%s', $uniqueName);
            //     $img->readImage($img_tmp_path);
            //     $img->adaptiveResizeImage($width, $height);

            //     //Guardar en el nuevo storage
            //     $url_storage = Storage::disk('subidos')->path($uniqueName);
            //     $img->writeImage($url_storage);

            //     //Eliminar el archivo original después de guardar el archivo reducido
            //     File::delete($img_tmp_path);
            // } else {
            //     Storage::disk('subidos')->put(
            //         $uniqueName,
            //         File::get($file->getRealPath()),
            //         'public'
            //     );
            // }
            $file->move('subidos', $uniqueName);
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
}
