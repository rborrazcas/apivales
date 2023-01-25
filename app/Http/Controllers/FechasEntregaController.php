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
class FechasEntregaController extends Controller
{
    function getCatalogsFechasEntrega(Request $request)
    {
        try {
            $remesas = DB::table('vales_remesas')
                ->distinct()
                ->where('Ejercicio', '2022')
                ->get('RemesaSistema');

            $catalogs = [
                'remesas' => $remesas,
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

    public function uploadExcel(Request $request)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', 600);
        $v = Validator::make($request->all(), [
            'NewFiles' => 'required',
        ]);

        if ($v->fails()) {
            $response = [
                'success' => true,
                'results' => false,
                'message' => 'No se envió ningun archivo',
                'errors' => 'No se envió ningun archivo',
            ];
            return response()->json($response, 200);
        }

        $params = $request->all();
        $userId = JWTAuth::parseToken()->toUser()->id;

        $conciliacionPendiente = DB::table('users_filtros')
            ->where('UserCreated', $userId)
            ->where('api', 'uploadClasificacion')
            ->get()
            ->first();

        if ($conciliacionPendiente != null) {
            $response = [
                'success' => true,
                'results' => false,
                'message' =>
                    'Su usuario tiene una conciliacion pendiente, Contacte al administrador',
            ];
            return response()->json($response, 200);
        }

        DB::table('users_filtros')->insert([
            'UserCreated' => $userId,
            'Api' => 'uploadClasificacion',
            'Consulta' => '',
            'created_at' => date('Y-m-d h-m-s'),
        ]);

        $file = $params['NewFiles'][0];

        DB::table('preconciliacion_vales')
            ->where('idUser', $userId)
            ->delete();

        Excel::import(new ConciliacionImport(), $file);

        $totalRows = DB::table('preconciliacion_vales')
            ->selectRaw('COUNT(id) AS total')
            ->where('idUser', $userId)
            ->get()
            ->first();

        if ($totalRows->total == 0) {
            $this->limpiarTabla();
            $response = [
                'success' => true,
                'results' => false,
                'message' =>
                    'El excel esta vacio o los encabezados no coinciden',
                'errors' =>
                    'El excel esta vacio o los encabezados no coinciden',
            ];
            return response()->json($response, 200);
        }

        $nombreArchivo = $file->getClientOriginalName();
        $fechaActual = date('Y-m-d h-m-s');
        $size = $file->getSize();
        $total = intval($totalRows->total);

        $archivoConciliacion = [
            'Quincena' => $fechaActual,
            'Nombre' => $nombreArchivo,
            'Peso' => $size,
            'Registros' => $total,
            'FechaUpload' => $fechaActual,
            'UserUpload' => $userId,
        ];

        DB::beginTransaction();
        $id = DB::table('conciliacion_archivos')->insertGetId(
            $archivoConciliacion
        );
        DB::commit();

        $infoVales = DB::table('preconciliacion_vales')
            ->select(
                'idUser',
                'folio_vale',
                'cantidad',
                'codigo',
                'responsable_de_escaneo',
                'farmacia',
                'fecha_de_canje',
                'tipo_de_operacion',
                'fecha_de_captura',
                'mes_de_canje',
                'observacion'
            )
            ->where('idUser', $userId)
            ->get()
            ->chunk(1000);

        try {
            foreach ($infoVales as $items) {
                $insert_data = [];
                $folios = [];
                foreach ($items as $data) {
                    $insert_data[] = [
                        'cantidad' => $data->cantidad,
                        'codigo' => $data->codigo,
                        'responsable_de_escaneo' =>
                            $data->responsable_de_escaneo,
                        'farmacia' => $data->farmacia,
                        'fecha_de_canje' => $data->fecha_de_canje,
                        'tipo_de_operacion' => $data->tipo_de_operacion,
                        'fecha_de_captura' => $data->fecha_de_captura,
                        'mes_de_canje' => $data->mes_de_canje,
                        'folio_vale' => $data->folio_vale,
                        'observacion' => $data->observacion,
                        'idArchivo' => $id,
                    ];
                    $folios[] = $data->folio_vale;
                }
                DB::table('conciliacion_vales')->insert($insert_data);
                DB::table('vales_series_2022')
                    ->whereIn('Serie', $folios)
                    ->update(['Conciliado' => 1]);
                unset($insert_data);
                unset($folios);
            }

            $this->limpiarTabla($userId);

            return [
                'success' => true,
                'results' => true,
                'message' => 'Cargado con éxito',
            ];
        } catch (\Illuminate\Database\QueryException $e) {
            $this->limpiarTabla($userId);
            return [
                'success' => false,
                'message' =>
                    'Ha ocurrido un error en la petición ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    public function limpiarTabla($userId)
    {
        DB::table('preconciliacion_vales')
            ->where('idUser', $userId)
            ->delete();

        DB::table('users_filtros')
            ->where('UserCreated', $userId)
            ->where('api', 'uploadClasificacion')
            ->delete();
    }

    public function getArchivos(Request $request)
    {
        $params = $request->all();
        try {
            $page = $params['page'];
            $pageSize = $params['pageSize'];
            $startIndex = $page * $pageSize;

            $res = DB::table('fechas_entrega_archivos as a')
                ->select(
                    'a.id',
                    'a.Fecha',
                    'a.Nombre',
                    'a.Remesa',
                    'a.Peso',
                    'a.Registros',
                    'a.FechaUpload',
                    DB::raw(
                        "CONCAT_WS(' ',b.Nombre,IFNULL(b.Paterno,''),IFNULL(b.Materno,'')) as UserUpload"
                    )
                )
                ->leftJoin('users as b', 'b.id', '=', 'a.UserUpload');
            $total = $res->count();

            $data = $res
                ->orderBy('a.id', 'desc')
                ->offset($startIndex)
                ->take($pageSize)
                ->get();

            return [
                'success' => true,
                'results' => true,
                'total' => $total,
                'data' => $data,
            ];
        } catch (\Illuminate\Database\QueryException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function getValesConciliacion(Request $request)
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
        ini_set('memory_limit', '-1');
        $params = $request->all();
        $idArchivo = $params['id'];
        $user = auth()->user();
        $res = DB::table('conciliacion_vales AS cv')
            ->select(
                'cv.cantidad',
                'cv.codigo',
                'cv.responsable_de_escaneo',
                'cv.farmacia',
                'cv.fecha_de_canje',
                'cv.tipo_de_operacion',
                'cv.mes_de_canje',
                'cv.observacion',
                'cv.folio_vale',
                'vs.SerieInicial',
                'vs.SerieFinal',
                'vr.RemesaSistema',
                'vss.CURP',
                'vss.idSolicitud',
                DB::RAW('lpad( hex( vss.idSolicitud ), 6, 0 ) FolioSolicitud'),
                'vss.Nombre',
                'vss.Paterno',
                'vss.Materno',
                'vss.Municipio',
                'v.entrega_at',
                'vss.Articulador'
            )
            ->join('vales_series_2022 AS vs', 'cv.folio_vale', 'vs.Serie')
            ->join(
                DB::RAW(
                    '(SELECT * FROM vales_solicitudes WHERE Ejercicio = 2022 ) AS vss'
                ),
                'vs.SerieInicial',
                'vss.SerieInicial'
            )
            ->join('vales AS v', 'vss.IdSolicitud', 'v.id')
            ->join('vales_remesas AS vr', 'v.Remesa', 'vr.Remesa')
            ->where('cv.idArchivo', $idArchivo)
            ->orderBy('cv.folio_vale')
            ->get();

        //dd(str_replace_array('?', $res->getBindings(), $res->toSql()));

        $total = $res->count();
        if ($total == 0) {
            $reader = IOFactory::createReader('Xlsx');
            $spreadsheet = $reader->load(
                public_path() . '/archivos/formatoReporteSolicitudVales11.xlsx'
            );
            $writer = new Xlsx($spreadsheet);
            $writer->save(
                'archivos/' . $user->email . 'reporteConciliacionVales.xlsx'
            );
            $file =
                public_path() .
                '/archivos/' .
                $user->email .
                'reporteConciliacionVales.xlsx';

            return response()->download(
                $file,
                'ConciliacionValesGrandeza' . date('Y-m-d') . '.xlsx'
            );
        }

        //!Mapeamos el resultado como un array
        $res = $res
            ->map(function ($x) {
                $x = is_object($x) ? (array) $x : $x;
                return $x;
            })
            ->toArray();

        $reader = IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load(
            public_path() . '/archivos/formatoReporteSolicitudVales10.xlsx'
        );
        $sheet = $spreadsheet->getActiveSheet();
        $sheet
            ->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_LETTER);
        $sheet->getPageSetup()->setPrintArea('A1:V' . (intval($total) + 10));

        // //Llenar excel con el resultado del query
        $sheet->fromArray($res, null, 'B5');
        unset($res);
        //Agregamos la fecha
        $sheet->setCellValue('H2', 'Fecha Reporte: ' . date('Y-m-d H:i:s'));
        // // //Agregar el indice autonumerico
        for ($i = 1; $i <= $total; $i++) {
            $inicio = 4 + $i;
            $sheet->setCellValue('A' . $inicio, $i);
        }
        $sheet->getDefaultRowDimension()->setRowHeight(-1);
        // //guardamos el excel creado y luego lo obtenemos en $file para poder descargarlo
        $writer = new Xlsx($spreadsheet);
        $writer->save(
            'archivos/' . $user->email . 'ConciliacionValesGrandeza.xlsx'
        );
        unset($sheet);
        unset($writer);
        $file =
            public_path() .
            '/archivos/' .
            $user->email .
            'ConciliacionValesGrandeza.xlsx';

        return response()->download(
            $file,
            $user->email .
                'ConciliacionValesGrandeza' .
                date('Y-m-d H:i:s') .
                '.xlsx'
        );
    }
}
