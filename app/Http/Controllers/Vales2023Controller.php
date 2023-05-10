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

class Vales2023Controller extends Controller
{
    function getPermisos()
    {
        $user = auth()->user();

        $permisos = DB::table('users_menus')
            ->where(['idUser' => $user->id, 'idMenu' => '29'])
            ->get()
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
            ->get()
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

    function getMunicipios(Request $request)
    {
        try {
            $archivos_clasificacion = DB::table('et_cat_municipio')
                ->select('id', 'Nombre', 'Region', 'SubRegion')
                ->orderby('Nombre')
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

    function getFilesById(Request $request, $id)
    {
        try {
            $archivos2 = DB::table('vales_archivos')
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

            $viewall = $permisos->ViewAll;

            $solicitudes = DB::table('vales as v')

                ->selectRaw(
                    'v.id,' .
                        'lpad(hex(v.id),6,0) AS FolioSolicitud, ' .
                        'v.FechaSolicitud, ' .
                        'r.RemesaSistema AS Remesa, ' .
                        'v.Nombre, ' .
                        'v.Paterno, ' .
                        'v.Materno, ' .
                        'v.CURP, ' .
                        'v.Sexo, ' .
                        'v.FechaIne, ' .
                        'm.SubRegion AS Region,' .
                        'm.Nombre AS Municipio,' .
                        'l.Nombre AS Localidad,' .
                        'v.Calle, ' .
                        'v.Calle, ' .
                        'v.Colonia, ' .
                        'v.NumExt, ' .
                        'v.NumInt, ' .
                        'v.CP, ' .
                        'v.FolioTarjetaContigoSi, ' .
                        'i.Incidencia, ' .
                        'CASE WHEN v.isEntregado = 1 THEN "SI" ELSE "NO" END AS Entregado, ' .
                        'v.entrega_at AS FechaEntrega, ' .
                        'v.TelFijo, ' .
                        'v.TelCelular, ' .
                        'v.ResponsableEntrega AS Responsable'
                )
                ->JOIN('vales_remesas AS r', 'v.Remesa', 'r.Remesa')
                ->JOIN('et_cat_municipio AS m', 'm.id', 'v.idMunicipio')
                ->JOIN('et_cat_localidad_2022 AS l', 'l.id', 'v.idLocalidad')
                ->LEFTJOIN('vales_incidencias AS i', 'i.id', 'v.idIncidencia')
                ->WHERERAW('v.Ejercicio = 2023');

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
                ->OrderByRaw('r.RemesaSistema', 'DESC')
                ->OrderBy('m.Subregion', 'ASC')
                ->OrderBy('m.Nombre', 'ASC')
                ->OrderBy('l.Nombre', 'ASC')
                ->OrderByRaw('r.Remesa', 'ASC')
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
                $url_storage = Storage::disk('subidos')->path($uniqueName);
                // $img->writeImage(sprintf('subidos/%s', $uniqueName));
                $img->writeImage($url_storage);
                File::delete($img_tmp_path);
                unset($img);
            } else {
                // $file->move('subidos', $uniqueName);
                Storage::disk('subidos')->put(
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
        $ine = DB::table('vales_archivos')
            ->where('idSolicitud', $id)
            ->where('idClasificacion', '2')
            ->whereNull('FechaElimino')
            ->get()
            ->first();

        $pvg = DB::table('vales_archivos')
            ->where('idSolicitud', $id)
            ->where('idClasificacion', '1')
            ->whereNull('FechaElimino')
            ->get()
            ->first();

        $acuse = DB::table('vales_archivos')
            ->where('idSolicitud', $id)
            ->where('idClasificacion', '3')
            ->whereNull('FechaElimino')
            ->get()
            ->first();

        if (!$ine || !$pvg || !$acuse) {
            DB::table('vales')
                ->where('id', $id)
                ->update(['ExpedienteCompleto' => 0]);
            return false;
        }

        DB::table('vales')
            ->where('id', $id)
            ->update(['ExpedienteCompleto' => 1]);

        return true;
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
                ->WhereRaw('v.Ejercicio = 2023');

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
                ->where('v.ExpedienteCompleto', 1)
                ->WhereRaw('v.Ejercicio = 2023');

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
                ->where('v.Validado', 0)
                ->WhereRaw('v.Ejercicio = 2023');

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
                ->where('v.Validado', 1)
                ->WhereRaw('v.Ejercicio = 2023');

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

            $viewall = $permisos->ViewAll;

            $solicitudes = DB::table('vales as v')

                ->selectRaw(
                    'v.id,' .
                        'lpad(hex(v.id),6,0) AS FolioSolicitud, ' .
                        'v.FechaSolicitud, ' .
                        'v.Remesa, ' .
                        'v.Nombre, ' .
                        'v.Paterno, ' .
                        'v.Materno, ' .
                        'v.CURP, ' .
                        'v.Sexo, ' .
                        'v.FechaIne, ' .
                        'm.SubRegion AS Region,' .
                        'm.Nombre AS Municipio,' .
                        'l.Nombre AS Localidad,' .
                        'v.Calle, ' .
                        'v.Calle, ' .
                        'v.Colonia, ' .
                        'v.NumExt, ' .
                        'v.NumInt, ' .
                        'v.CP, ' .
                        'v.FolioTarjetaContigoSi, ' .
                        'v.TelFijo, ' .
                        'v.TelCelular, ' .
                        'v.ResponsableEntrega AS Responsable, ' .
                        'v.ExpedienteCompleto, ' .
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
                ->OrderByRaw('v.Remesa', 'DESC')
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
}
