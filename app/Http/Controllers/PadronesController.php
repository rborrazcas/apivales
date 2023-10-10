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
use App\Imports\PadronesImport;
use App\Exports\PadronValidadoExport;

class PadronesController extends Controller
{
    function getRemesas()
    {
        try {
            $res = DB::table('vales_remesas')
                ->select('Remesa AS label', 'Remesa AS value')
                ->where(['Ejercicio' => '2023', 'Estatus' => 0])
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

    function getOrigin()
    {
        try {
            $res = DB::table('cat_padron_origen')
                ->select('Origen AS label', 'id AS value')
                ->where(['idPrograma' => 1])
                ->orderBy('Origen')
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

    public function getPadronesRemesasUpload(Request $request)
    {
        $params = $request->all();
        try {
            $page = $params['page'];
            $pageSize = $params['pageSize'];
            $startIndex = $page * $pageSize;

            $res = DB::table('padron_remesas as p')
                ->select(
                    'p.id',
                    'p.Remesa',
                    'p.Registros',
                    'p.FechaUpload',
                    DB::raw(
                        "CONCAT_WS(' ',u.Nombre,IFNULL(u.Paterno,''),IFNULL(u.Materno,'')) as UserUpload"
                    ),
                    'r.Estatus'
                )
                ->leftJoin('users as u', 'u.id', '=', 'p.UserUpload')
                ->Join('vales_remesas as r', 'p.Remesa', 'r.Remesa');
            $total = $res->count();

            $data = $res
                ->orderBy('p.id', 'desc')
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

    public function uploadExcel(Request $request)
    {
        //ini_set('memory_limit', '-1');

        $v = Validator::make($request->all(), [
            'NewFiles' => 'required',
            'NewRemesa' => 'required',
            'NewCodigo' => 'required',
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
        $fechaActual = date('Y-m-d h-m-s');
        $total = 0;
        $flagRegistrada = false;

        try {
            $file = $params['NewFiles'][0];
            $origen = $params['NewOrigen'][0];
            $remesa = $params['NewRemesa'][0];
            $codigo = $params['NewCodigo'][0];
            $nombreArchivo = $file->getClientOriginalName();
            $archivoPadron = [
                'idOrigen' => $origen,
                'Remesa' => $remesa,
                'Nombre' => $nombreArchivo,
                'Codigo' => $codigo,
                'Registros' => $total,
                'FechaUpload' => $fechaActual,
                'UserUpload' => $userId,
            ];

            DB::beginTransaction();
            $id = DB::table('padron_archivos')->insertGetId($archivoPadron);
            DB::commit();
            unset($archivoPadron);
            $dataFile = [
                'idArchivo' => $id,
                'codigo' => $codigo,
                'remesa' => $remesa,
            ];

            Excel::import(new PadronesImport($dataFile), $file);
            unset($dataFile);

            $totalRows = DB::table('padron_carga_inicial')
                ->selectRaw('COUNT(id) AS total')
                ->where('idArchivo', $id)
                ->get()
                ->first();

            if ($totalRows === null || $totalRows->total == 0) {
                DB::table('padron_archivos')
                    ->where('id', $id)
                    ->delete();
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

            DB::table('padron_archivos')
                ->where('id', $id)
                ->update(['Registros' => $totalRows->total]);
            unset($totalRows);

            DB::select('CALL padron_validacion_curp_registrada(' . $id . ')');

            DB::select('CALL padron_validacion_curp_funcionario(' . $id . ')');

            DB::select('CALL padron_validacion(' . $id . ')');

            DB::select('CALL padron_validacion_peb(' . $id . ')');

            $padronAValidarRenapo = DB::table('padron_carga_inicial AS p')
                ->select('p.id', 'p.CURP', 'p.Remesa')
                ->where([
                    'p.idArchivo' => $id,
                    'p.CURPValido' => 1,
                    'p.CURPValidada' => 0,
                    'p.CURPYaRegistrada' => 0,
                ])
                ->get();

            if ($padronAValidarRenapo->count() > 0) {
                $validacionConRenapo = $this->validateCurpRenapo(
                    $padronAValidarRenapo
                );
            }
            unset($padronAValidarRenapo);

            DB::select('CALL padron_validacion_nombre(' . $id . ')');

            DB::select('CALL padron_validacion_multiapoyo(' . $id . ')');

            DB::select('CALL padron_validacion_edad(' . $id . ')');

            //validación de estatus
            DB::select('CALL padron_validacion_estatus_origen(' . $id . ')');

            DB::select('CALL padron_con_incidencia(' . $id . ')');

            DB::select('CALL padron_correcto(' . $id . ')');

            $padronValido = DB::table('padron_validado as p')
                ->selectRaw('COUNT(id) AS total')
                ->where([
                    'Remesa' => $remesa,
                    'idArchivo' => $id,
                    'EstatusOrigen' => 'SI',
                ])
                ->first();
            $flagCorrectors = false;

            if ($padronValido !== null) {
                if ($padronValido->total > 0) {
                    $remesaRegistrada = DB::table('padron_remesas')
                        ->select('Registros')
                        ->where('Remesa', $remesa)
                        ->first();

                    if ($remesaRegistrada === null) {
                        $padronRemesa = [
                            'Remesa' => $remesa,
                            'Registros' => $padronValido->total,
                            'FechaUpload' => $fechaActual,
                            'UserUpload' => $userId,
                        ];

                        DB::beginTransaction();
                        $idPadronRemesas = DB::table(
                            'padron_remesas'
                        )->insertGetId($padronRemesa);
                        DB::commit();
                    } else {
                        $totalRemesa = DB::table('padron_validado as p')
                            ->selectRaw('COUNT(id) AS total')
                            ->where([
                                'Remesa' => $remesa,
                                'EstatusOrigen' => 'SI',
                            ])
                            ->first();

                        // DB::table('padron_remesas')
                        //     ->Select('Registros')
                        //     ->Where('Remesa', $remesa)
                        //     ->first();

                        $totalR = intval($totalRemesa->total);

                        DB::table('padron_remesas')
                            ->where('Remesa', $remesa)
                            ->update(['Registros' => $totalR]);
                    }
                    $flagCorrectors = true;
                    unset($remesaRegistrada);
                }
            }
            unset($padronValido);

            $errores = DB::table('padron_carga_inicial')
                ->select('id')
                ->where(['idArchivo' => $id])
                ->count();

            $flagErrores = false;
            $message = 'Cargado con éxito';
            if ($errores > 0 && $flagCorrectors) {
                $flagErrores = true;
                $message =
                    'Se cargo una parte del archivo con éxito, el resto de registros contienen incidencias, favor de revisar el archivo que se descargo automáticamente';
            } elseif ($errores > 0 && !$flagCorrectors) {
                $flagErrores = true;
                $message =
                    'Todos los registros del archivo contienen incidencia, favor de revisar el archivo que se descargo automáticamente';
            }

            return [
                'success' => true,
                'results' => true,
                'message' => $message,
                'incidencias' => $flagErrores,
                'idArchivo' => $id,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' =>
                    'Ha ocurrido un error en la petición ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    private function validateCurpRenapo($arrayData)
    {
        $client = new Client(); //GuzzleHttp\Client
        foreach ($arrayData as $solicitud) {
            if ($solicitud->CURP !== null) {
                $url =
                    'https://seguimiento.guanajuato.gob.mx/apiinformacionsocial/api/renapo/porcurp/pL@t_1n|Run$28/' .
                    $solicitud->CURP .
                    '/7';
                $response = $client->request('GET', $url, [
                    'verify' => false,
                ]);
                $responseBody = json_decode($response->getBody());

                if ($responseBody->Mensaje === 'OK') {
                    $curp = $responseBody->Resultado;
                    $timestamp = strtotime($curp->fechNac);

                    $curpRegistrada = DB::table('Renapo_Local')
                        ->select('CURP')
                        ->Where('CURP', $curp->CURP)
                        ->first();

                    if ($curpRegistrada === null) {
                        DB::table('Renapo_Local')->insert([
                            'CURP' => $curp->CURP,
                            'apellido1' => $curp->apellido1,
                            'apellido2' =>
                                $curp->apellido2 == null ||
                                strlen($curp->apellido2) == 0
                                    ? null
                                    : $curp->apellido2,
                            'nombres' => $curp->nombres,
                            'sexo' => $curp->sexo,
                            'fechNac' => date('Y-m-d', $timestamp),
                            'nacionalidad' => $curp->nacionalidad,
                            'apellido1Limpio' => $curp->apellido1,
                            'apellido2Limpio' =>
                                $curp->apellido2 == null ||
                                strlen($curp->apellido2) == 0
                                    ? null
                                    : $curp->apellido2,
                            'nombresLimpio' => str_replace(
                                '.',
                                '',
                                $curp->nombres
                            ),
                            'cveEntidadNac' => $curp->cveEntidadNac,
                        ]);
                    }

                    $dataPadron = [
                        'CURPValidada' => 1,
                        'CURPRENAPO' => 1,
                    ];

                    if ($solicitud->CURP !== $curp->CURP) {
                        $dataPadron['CURPAnterior'] = $solicitud->CURP;
                        $dataPadron['CURP'] = $curp->CURP;
                        $dataPadron['CURPRenapoDiferente'] = 1;
                    }

                    $curpRegistrada = DB::table('padron_validado')
                        ->select('id')
                        ->where('Remesa', $solicitud->Remesa)
                        ->WhereRaw(
                            '(CURP = "' .
                                $curp->CURP .
                                '" OR CURPAnterior = "' .
                                $curp->CURP .
                                '" )'
                        )
                        ->first();

                    if ($curpRegistrada !== null) {
                        $dataPadron['NoValido'] = 1;
                        $dataPadron['CURPYaRegistrada'] = 1;
                        $dataPadron['NombreValido'] = 1;
                        $dataPadron['PaternoValido'] = 1;
                        $dataPadron['MunicipioValido'] = 1;
                        $dataPadron['LocalidadValido'] = 1;
                        $dataPadron['ColoniaValido'] = 1;
                        $dataPadron['CalleValido'] = 1;
                        $dataPadron['NumExtValido'] = 1;
                        $dataPadron['CPValido'] = 1;
                        $dataPadron['TelefonoContactoValido'] = 1;
                        $dataPadron['FechaIneValido'] = 1;
                        $dataPadron['EnlaceValido'] = 1;
                        $dataPadron['NombreRenapoValido'] = 1;
                        $dataPadron['ResponsableEntregaValido'] = 1;
                        $dataPadron['MenorEdad'] = 0;
                    }
                    DB::table('padron_carga_inicial')
                        ->where('id', $solicitud->id)
                        ->update($dataPadron);
                }
            }
        }
        unset($arrayData);
        return true;
    }

    public function getReporteIncidencias(Request $request)
    {
        $params = $request->all();
        $id = $params['id'];
        $user = auth()->user();
        $res = DB::table('padron_carga_inicial AS p')
            ->select(
                'p.Orden',
                'p.OrdenMunicipio',
                'p.Identificador',
                'p.Region',
                'p.Nombre',
                'p.Paterno',
                'p.Materno',
                'p.FechaNacimiento',
                'p.Sexo',
                'p.EstadoNacimiento',
                'p.CURP',
                'p.Validador',
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
                'p.Apoyo',
                'p.Variante',
                'p.Enlace',
                'p.LargoCURP',
                'p.FrecuenciaCURP',
                'p.Periodo',
                'p.NombreMenor',
                'p.PaternoMenor',
                'p.MaternoMenor',
                'p.FechaNacimientoMenor',
                'p.SexoMenor',
                'p.EstadoNacimientoMenor',
                'p.CURPMenor',
                'p.ValidadorCURPMenor',
                'p.LargoCURPMenor',
                'p.FrecuenciaCURPMenor',
                'p.EnlaceIntervencion1',
                'p.EnlaceIntervencion2',
                'p.EnlaceIntervencion3',
                'p.FechaSolicitud',
                'p.ResponsableEntrega',
                'p.EstatusOrigen',
                'p.Remesa',
                'a.Codigo',
                DB::RAW(
                    "CONCAT_WS(' ',u.Nombre,u.Paterno,u.Materno) AS ResponsableDeValidacion"
                ),
                'FechaCreo',
                DB::raw(
                    "IF (p.CURPValido = 0,'LA CURP NO CUMPLE CON EL FORMATO','') AS FormatoCURP"
                ),
                DB::raw(
                    "IF (p.CURPDuplicada = 1,'LA CURP ESTA DUPLICADA EN EL ARCHIVO ORIGEN','') AS CURPDuplicada"
                ),
                DB::raw(
                    "IF (p.CURPYaRegistrada = 1,'LA CURP YA ESTA REGISTRADA EN LA REMESA','') AS CUPRRegistrada"
                ),
                DB::raw(
                    "IF (p.CURPValidada = 0,'LA CURP NO SE ENCUENTRA EN RENAPO','') AS CURPEnRenapo"
                ),
                DB::raw(
                    "IF (p.NombreValido = 0,'EL NOMBRE ES INVALIDO','') AS NombreValido"
                ),
                DB::raw(
                    "IF (p.PaternoValido = 0,'EL APELLIDO 1 ES INVALIDO (SI SOLO TIENE UN APELLIDO DEBE COLOCARLO EN EL APELLIDO 1)','') AS PaternoValido"
                ),
                DB::raw(
                    "IF (p.NombreRenapoValido = 0,'EL NOMBRE ES DIFERENTE A RENAPO','') AS NombreRenapoValido"
                ),
                DB::raw(
                    "IF (p.NombreRenapoValido = 0,rp.nombres,'') AS NombreRenapoCorrecto"
                ),
                DB::raw(
                    "IF (p.NombreRenapoValido = 0,rp.apellido1,'') AS PaternoRenapoCorrecto"
                ),
                DB::raw(
                    "IF (p.NombreRenapoValido = 0,rp.apellido2,'') AS MaternoRenapoCorrecto"
                ),
                DB::raw(
                    "IF (p.MunicipioValido = 0,'EL MUNICIPIO NO ES VALIDO','') AS MunicipioValido"
                ),
                DB::raw(
                    "IF (p.LocalidadValido = 0,'EL NÚMERO DE LOCALIDAD NO SE ENCUENTRA EN EL CATÁLOGO','') AS LocalidadValido"
                ),
                DB::raw(
                    "IF (p.ColoniaValido = 0,'LA COLONIA NO ES VALIDA','') AS ColoniaValido"
                ),
                DB::raw(
                    "IF (p.CalleValido = 0,'LA CALLE NO ES VALIDA','') AS CalleValido"
                ),
                DB::raw(
                    "IF (p.NumExtValido = 0,'EL NUMERO EXTERIOR NO ES VALIDO','') AS NumExtValido"
                ),
                DB::raw(
                    "IF (p.CPValido = 0,'EL CP NO ES VALIDO','') AS CPValido"
                ),
                DB::raw(
                    "IF (p.TelefonoContactoValido = 0,'DEBE AGREGAR POR LO MENOS UN TELÉFONO VÁLIDO','') AS TelefonoContactoValido"
                ),
                DB::raw(
                    "IF (p.FechaIneValido = 0,'LA FECHA DE LA INE NO ES VALIDA','') AS FechaIneValido"
                ),
                DB::raw(
                    "IF (p.EnlaceValido = 0,'EL ENLACE ORIGEN NO ES VALIDO','') AS EnlaceValido"
                ),
                DB::raw(
                    "IF (p.ResponsableEntregaValido = 0,'EL RESPONSABLE DE ENTREGA NO ES VALIDO','') AS ResponsableEntregaValido"
                ),
                DB::raw(
                    "IF (p.MenorEdad = 1,'EL REGISTRO ES DE UN MENOR DE EDAD','') AS MenorEdad"
                ),
                DB::raw(
                    "IF (p.EstatusOrigenValido = 0,'EL ESTATUS ORIGEN ES INVALIDO','') AS EnlaceOrigenValido"
                ),
                DB::raw(
                    "IF (p.Aprobado = 0,'EL REGISTRO FUE RECHAZADO POR COMITÉ','') AS Aprobado"
                )
            )
            ->Join('users AS u', 'u.id', '=', 'p.idUsuarioCreo')
            ->Join('padron_archivos AS a', 'a.id', 'p.idArchivo')
            ->LEFTJOIN('Renapo_Local AS rp', 'p.CURP', 'rp.CURP')
            ->Where('p.idArchivo', $id)
            ->orderBy('p.id', 'asc')
            ->get();

        //dd(str_replace_array('?', $res->getBindings(), $res->toSql()));

        $totalReg = $res->count();
        if ($totalReg === 0) {
            //return response()->json(['success'=>false,'results'=>false,'message'=>$res->toSql()]);
            $reader = IOFactory::createReader('Xlsx');
            $spreadsheet = $reader->load(
                public_path() . '/archivos/PlantillaParaErroresPadron.xlsx'
            );
            $writer = new Xlsx($spreadsheet);
            $writer->save(
                'archivos/' . $user->email . 'PlantillaErroresPadron.xlsx'
            );
            $file =
                public_path() .
                '/archivos/' .
                $user->email .
                'PlantillaErroresPadron.xlsx';

            return response()->download(
                $file,
                'PlantillaErroresPadron' . date('Y-m-d') . '.xlsx'
            );
        }

        $archivo = DB::table('padron_archivos')
            ->select('Codigo', 'Registros')
            ->where('id', $id)
            ->first();

        $registrosConError = [
            'idArchivo' => $id,
            'Registros' => intval($archivo->Registros),
        ];

        DB::table('padron_registros_error')->insert($registrosConError);

        //Mapeamos el resultado como un array
        $res = $res
            ->map(function ($x) {
                $x = is_object($x) ? (array) $x : $x;
                return $x;
            })
            ->toArray();

        $reader = IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load(
            public_path() . '/archivos/PlantillaParaErroresPadron.xlsx'
        );
        $sheet = $spreadsheet->getActiveSheet();

        $sheet
            ->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_LETTER);

        $sheet->fromArray($res, null, 'A5');

        $sheet->setCellValue('F2', 'Fecha Reporte: ' . date('Y-m-d H:i:s'));
        $sheet->setCellValue(
            'H2',
            'Total registros de origen: ' . $archivo->Registros
        );
        $sheet->setCellValue('H2', 'Total registros con error: ' . $totalReg);

        $sheet->getDefaultRowDimension()->setRowHeight(-1);

        $writer = new Xlsx($spreadsheet);
        $writer->save('archivos/' . $user->email . 'ErroresPadron.xlsx');
        $file =
            public_path() . '/archivos/' . $user->email . 'ErroresPadron.xlsx';

        DB::select('CALL padron_errores(' . $id . ')');
        return response()->download(
            $file,
            'ErroresPadron_' .
                $archivo->Codigo .
                '_' .
                date('Y-m-d H:i:s') .
                '.xlsx'
        );
    }

    public function getReportePadronCorrecto(Request $request)
    {
        ini_set('memory_limit', '-1');
        set_time_limit(0);
        $params = $request->all();
        $id = $params['id'];
        $user = auth()->user();

        return (new PadronValidadoExport($id))->download(
            'Padron_Validado_Remesa-' . $id . '.xlsx'
        );
    }

    public function getPlantilla()
    {
        $file = public_path() . '/archivos/PlantillaPadron.xlsx';
        return response()->download($file, 'PlantillaPadron.xlsx');
    }

    public function setStatusRemesa(Request $request)
    {
        $v = Validator::make($request->all(), [
            'Remesa' => 'required',
        ]);

        if ($v->fails()) {
            $response = [
                'success' => true,
                'results' => false,
                'message' => 'Contacte al administrador',
                'errors' => 'No se envío la remesa',
            ];
            return response()->json($response, 200);
        }

        $params = $request->all();
        $userId = JWTAuth::parseToken()->toUser()->id;
        $fechaActual = date('Y-m-d h-m-s');

        try {
            DB::table('vales_remesas')
                ->where('Remesa', $params['Remesa'])
                ->update(['Estatus' => 1]);

            DB::table('padron_validado')
                ->where('Remesa', $params['Remesa'])
                ->update([
                    'idUsuarioCerro' => $userId,
                    'FechaCerro' => date('Y-m-d H:i:s'),
                    // ! Si los que se cargan son directamente aprobados se actualiza a aprobado comité
                    'idEstatus' => 2,
                ]);

            DB::select('CALL padron_vales("' . $params['Remesa'] . '")');

            DB::select('CALL padron_vales_grupos("' . $params['Remesa'] . '")');

            DB::select(
                'CALL padron_vales_grupos_totales("' . $params['Remesa'] . '")'
            );

            DB::select(
                'CALL padron_vales_grupos_ids("' . $params['Remesa'] . '")'
            );

            return [
                'success' => true,
                'results' => true,
                'message' => 'Remesa cerrada correctamente',
            ];
        } catch (Exception $e) {
            return [
                'success' => true,
                'results' => false,
                'message' => 'Contacte al administrador',
                'errors' => $e->getMessage(),
            ];
        }
    }
}
