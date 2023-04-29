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

    function getCatalogos(Request $request)
    {
        try {
            $entidades = DB::table('cat_entidad')
                ->select('id AS value', 'Entidad AS label', 'Clave_CURP')
                ->where('id', '<>', 1)
                ->orderBy('label')
                ->get();

            $municipios = DB::table('et_cat_municipio')
                ->select('id AS value', 'Nombre AS label')
                ->orderBy('label')
                ->get();

            $cat_parentesco_tutor = DB::table('cat_parentesco_tutor')
                ->select('id AS value', 'Parentesco AS label')
                ->orderBy('label')
                ->get();

            $catalogs = [
                'entidades' => $entidades,
                'municipios' => $municipios,
                'cat_parentesco_tutor' => $cat_parentesco_tutor,
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
                    )
                )
                ->JOIN('cat_estatus_archivos AS ea', 'ea.id', 'a.idEstatus')
                ->JOIN(
                    'solicitudes_archivos_clasificacion AS ac',
                    'a.idClasificacion',
                    'ac.id'
                )
                ->LEFTJOIN('users AS u', 'a.idUsuarioAprobo', 'u.id')
                ->where(['a.idSolicitud' => $id, 'a.idPrograma' => $idPrograma])
                ->whereNull('a.FechaElimino')
                ->get();

            $archivos = array_map(function ($o) {
                $o->ruta = 'http://localhost:8080/subidos/' . $o->NombreSistema;
                //Storage::disk('subidos')->url($o->NombreSistema);

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
                'errors' => $errors,
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
