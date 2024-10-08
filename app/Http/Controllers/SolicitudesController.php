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
class SolicitudesController extends Controller
{
    function getCatalogsFiles($id)
    {
        $idPrograma = $id;

        try {
            $archivosClasificacion = DB::table(
                'solicitudes_archivos_clasificacion AS ac'
            )
                ->select('ac.id AS value', 'ac.Clasificacion AS label')
                ->WhereIn('ac.idPrograma', [0, $idPrograma])
                ->orderBy('label')
                ->get();

            $archivosEstatus = DB::table('cat_estatus_archivos AS ea')
                ->select('ea.id AS value', 'ea.Estatus AS label')
                ->orderBy('label')
                ->get();

            $catalogs = [
                'archivos_estatus' => $archivosEstatus,
                'archivos_clasificacion' => $archivosClasificacion,
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

    function getCatalogos(Request $request, $id)
    {
        try {
            $user = auth()->user();
            $entidades = DB::table('cat_entidad')
                ->select('id AS value', 'Entidad AS label', 'Clave_CURP')
                ->where('id', '<>', 1)
                ->orderBy('label')
                ->get();

            $municipios = DB::table('et_cat_municipio')->select(
                'id AS value',
                'Nombre AS label'
            );

            switch ($id) {
                case 2:
                    $menu = 27;
                    break;
                case 3:
                    $menu = 33;
                    break;
                default:
                    $menu = 27;
                    break;
            }

            $permisos = DB::table('users_menus AS um')
                ->Select('um.idUser', 'um.Seguimiento', 'um.ViewAll')
                ->where(['um.idUser' => $user->id, 'um.idMenu' => $menu])
                ->first();
            $filtroPermisos = '';

            if ($permisos->ViewAll < 1) {
                $filtroPermisos =
                    'et_cat_municipio.SubRegion IN (' .
                    'SELECT Region FROM users_region WHERE idUser = ' .
                    $user->id .
                    ')';
            }

            if ($filtroPermisos !== '') {
                $municipios->whereRaw($filtroPermisos);
            }

            $cat_parentesco_tutor = DB::table('cat_parentesco_tutor')
                ->select('id AS value', 'Parentesco AS label')
                ->orderBy('label')
                ->get();

            $ejercicios = DB::table('cat_ejercicio_fiscal')->Select('Ejercicio AS value','Ejercicio AS label');
            $year = idate('Y', strtotime('first day of January', time()));
            $usersEjercicios = DB::table('users_especial')->Where(['idPrograma'=>2,"Opcion"=>'Ejercicios','idUsuario'=>$user->id])->first();
            
            if(!$usersEjercicios){
                $ejercicios = $ejercicios->Where(['Ejercicio'=>$year]);
            }


            $catalogs = [
                'entidades' => $entidades,
                'municipios' => $municipios->orderBy('label')->get(),
                'cat_parentesco_tutor' => $cat_parentesco_tutor,
                'ejercicios' => $ejercicios->get(),
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

    function getFiles(Request $request)
    {
        $v = Validator::make($request->all(), [
            'idSolicitud' => 'required',
            'idPrograma' => 'required',
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
        $idPrograma = $params['idPrograma'];

        try {
            $archivos2 = DB::table('solicitudes_archivos AS a')
                ->SELECT(
                    'a.id',
                    'a.idSolicitud',
                    'a.idPrograma',
                    'a.idClasificacion',
                    'ac.Clasificacion',
                    'a.idEstatus',
                    'ea.Estatus',
                    'a.NombreOriginal',
                    'a.NombreSistema',
                    'a.Extension',
                    'a.Tipo',
                    DB::RAW(
                        'CONCAT_WS(" ",u.Nombre,u.Paterno,u.Materno) AS UserAprobo'
                    ),
                    'pc.id AS idCotizacion',
                    'pc.FolioCotizacion',
                    'pc.Subtotal',
                    'pc.Iva',
                    'pc.Total'
                )
                ->JOIN('cat_estatus_archivos AS ea', 'ea.id', 'a.idEstatus')
                ->JOIN(
                    'solicitudes_archivos_clasificacion AS ac',
                    'a.idClasificacion',
                    'ac.id'
                )
                ->LEFTJOIN('users AS u', 'a.idUsuarioAprobo', 'u.id')
                ->LeftJoin(
                    'solicitudes_proyectos_cotizaciones AS pc',
                    'a.id',
                    'pc.idArchivo'
                )
                ->where(['a.idSolicitud' => $id, 'a.idPrograma' => $idPrograma])
                ->whereNull('a.FechaElimino')
                ->OrderBy('a.idClasificacion', 'DESC')
                ->get();

            $archivos = array_map(function ($o) {
                $o->ruta =
                    //     'https://apivales.apisedeshu.com/subidos/' .
                    //     $o->NombreSistema;
                    Storage::disk('subidos')->url($o->NombreSistema);

                $observaciones = DB::table(
                    'solicitudes_archivos_observaciones AS o'
                )
                    ->select('Observacion')
                    ->where([
                        'idSolicitud' => $o->idSolicitud,
                        'idPrograma' => $o->idPrograma,
                        'idArchivo' => $o->id,
                        'Estatus' => 0,
                    ])
                    ->get();

                $obs = [];
                if ($observaciones != null) {
                    $obs = $observaciones->toArray();
                }

                $o->observaciones = $obs;

                $correciones = DB::table(
                    'solicitudes_archivos_observaciones AS o'
                )
                    ->select('Observacion')
                    ->where([
                        'idSolicitud' => $o->idSolicitud,
                        'idPrograma' => $o->idPrograma,
                        'idArchivo' => $o->id,
                        'Estatus' => 1,
                    ])
                    ->get();

                $crs = [];
                if ($correciones != null) {
                    $crs = $correciones->toArray();
                }

                $o->correciones = $crs;

                $productos = DB::table('solicitudes_proyectos_productos AS c')
                    ->select('Producto', 'Cantidad', 'Precio')
                    ->where([
                        'c.idCotizacion' => $o->idCotizacion,
                    ])
                    ->get();
                $pd = [];
                if ($productos != null) {
                    $pd = $productos->toArray();
                }
                $o->productos = $pd;

                $o->color = $this->getColor($o->idEstatus);

                return $o;
            }, $archivos2->toArray());

            $response = [
                'success' => true,
                'results' => true,
                'message' => 'éxito',
                'data' => $archivos,
            ];
            return response()->json($response, 200);
        } catch (\Throwable $errors) {
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

    function getFilesProyectos(Request $request)
    {
        $v = Validator::make($request->all(), [
            'idSolicitud' => 'required',
            'idPrograma' => 'required',
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
        $idPrograma = $params['idPrograma'];

        try {
            $archivos2 = DB::table('solicitudes_archivos AS a')
                ->SELECT(
                    'a.id',
                    'a.idSolicitud',
                    'a.idPrograma',
                    'a.idClasificacion',
                    'ac.Clasificacion',
                    'a.idEstatus',
                    'ea.Estatus',
                    'a.NombreOriginal',
                    'a.NombreSistema',
                    'a.Extension',
                    'a.Tipo',
                    DB::RAW(
                        'CONCAT_WS(" ",u.Nombre,u.Paterno,u.Materno) AS UserAprobo'
                    ),
                    'pc.id AS idCotizacion',
                    'pc.FolioCotizacion',
                    'pc.Subtotal',
                    'pc.Iva',
                    'pc.Total'
                )
                ->JOIN('cat_estatus_archivos AS ea', 'ea.id', 'a.idEstatus')
                ->JOIN(
                    'solicitudes_archivos_clasificacion AS ac',
                    'a.idClasificacion',
                    'ac.id'
                )
                ->LeftJoin('users AS u', 'a.idUsuarioAprobo', 'u.id')
                ->LeftJoin(
                    'solicitudes_proyectos_cotizaciones AS pc',
                    'a.id',
                    'pc.idArchivo'
                )
                ->where(['a.idSolicitud' => $id, 'a.idPrograma' => $idPrograma])
                ->whereNull('a.FechaElimino')
                ->OrderBy('a.idClasificacion', 'DESC')
                ->get();

            $archivos = array_map(function ($o) {
                $o->ruta =
                    //'https://apivales.apisedeshu.com/subidos/' .
                    //$o->NombreSistema;
                    Storage::disk('subidos')->url($o->NombreSistema);

                $productos = DB::table('solicitudes_proyectos_productos AS c')
                    ->select('Producto', 'Cantidad', 'SubTotal')
                    ->where([
                        'c.idCotizacion' => $o->idCotizacion,
                    ])
                    ->WhereNull('c.FechaElimino')
                    ->get();
                $obs = [];
                if ($productos != null) {
                    $obs = $productos->toArray();
                }
                $o->productos = $obs;
                $o->color = $this->getColor('#CCC');

                return $o;
            }, $archivos2->toArray());

            $response = [
                'success' => true,
                'results' => true,
                'message' => 'éxito',
                'data' => $archivos,
            ];
            return response()->json($response, 200);
        } catch (\Throwable $errors) {
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

    function changeStatusFiles(Request $request)
    {
        $v = Validator::make($request->all(), [
            'idArchivo' => 'required',
            'idEstatus' => 'required',
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
        $id = $params['idArchivo'];
        $idEstatus = $params['idEstatus'];

        try {
            $solicitud = [
                'idEstatus' => $idEstatus,
            ];

            switch ($idEstatus) {
                case 3:
                    $solicitud['idUsuarioAprobo'] = $user->id;
                    $solicitud['FechaAprobo'] = date('Y-m-d H:i:s');
                    break;
                case 5:
                    $solicitud['idUsuarioElimino'] = $user->id;
                    $solicitud['FechaElimino'] = date('Y-m-d H:i:s');
                    break;
            }

            $flag = DB::table('solicitudes_archivos')
                ->where(['id' => $id])
                ->update($solicitud);

            if ($idEstatus == 3) {
                $idSolicitud = DB::table('solicitudes_archivos')
                    ->select('idSolicitud')
                    ->whereNull('FechaElimino')
                    ->where('id', $id)
                    ->first();
                $aprobadas = DB::table('solicitudes_archivos')
                    ->where('idSolicitud', $idSolicitud->idSolicitud)
                    ->where('idEstatus', '<>', 3)
                    ->WhereNull('FechaElimino')
                    ->first();
                if (!$aprobadas) {
                    DB::table('solicitudes_calentadores')
                        ->where('id', $idSolicitud->idSolicitud)
                        ->update([
                            'idEstatusSolicitud' => 5,
                        ]);
                }
            }

            $response = [
                'success' => true,
                'results' => true,
                'message' => 'con éxito',
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

    function getColor($idEstatus)
    {
        switch ($idEstatus) {
            case 1:
                return '#ffe615';
                break;
            case 2:
                return '#ff0202';
                break;
            case 3:
                return '#49ff33';
                break;
            case 4:
                return '#ffe615';
                break;
        }
    }
}
