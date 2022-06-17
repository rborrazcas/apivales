<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ReportesController;
use GuzzleHttp\Client;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Hash;

use App\ValesZis;
use File;
use DB;

class ZisController extends Controller
{
    public function load(Request $request)
    {
        $headers = [];
        $requireds = ['n_0', 'lon', 'lat'];
        $notFound = [];
        $data = [];
        $dataRow = [];
        $filterSubset = new MyReadFilter();

        try {
            $file = $request->file('Archivo');
            $minField = 1;
            $maxField = 20;

            //Se obtiene el documento para trabajar
            $inputFileType = IOFactory::identify($file);
            $reader = IOFactory::createReader($inputFileType);
            $reader->setReadDataOnly(true);
            $reader->setReadFilter($filterSubset);

            $chunkSize = 2048;
            $filterSubset->setRows(0, 1);

            $document = $reader->load($file);
            $headers = $document->getActiveSheet()->toArray()[0];
            $columns = count($headers);

            if ($columns < 1) {
                return [
                    'success' => true,
                    'results' => false,
                    'message' => 'La primer fila no contiene encabezados',
                    'error' => '',
                ];
            }

            if ($columns > $maxField + 4) {
                return [
                    'success' => true,
                    'results' => false,
                    'message' =>
                        'El archivo contiene más columnas que la tabla destino. Revise el archivo y vuelva a intentarlo.',
                    'error' => '',
                ];
            }

            $flag = is_int($headers[0]);

            if ($flag) {
                return [
                    'success' => true,
                    'results' => false,
                    'message' =>
                        'La primera fila no contiene el formato correcto para los encabezados. Revise el archivo y vuelva a intentarlo.',
                    'error' => '',
                ];
            }

            foreach ($requireds as $required) {
                if (!in_array($required, $headers, true)) {
                    $notFound[] = $required;
                }
            }

            if (count($notFound) > 0) {
                return [
                    'success' => true,
                    'results' => false,
                    'message' =>
                        'La primera fila debe contener los titulos --> ' .
                        implode(',', $notFound),
                    'error' => '',
                ];
            }

            DB::table('vales_zis')->truncate();

            for ($startRow = 1; $startRow <= 65536; $startRow += $chunkSize) {
                $data = [];
                $filterSubset->setRows($startRow, $chunkSize);
                $document = $reader->load($file);
                $rows = $document->getActiveSheet()->toArray();

                $dataRow = array_map(function ($x) {
                    if (count($x) > 0) {
                        if (!empty($x[0])) {
                            return $x;
                        }
                    }
                }, $rows);

                $dataRow = array_filter($dataRow, function ($x) {
                    if (!is_null($x)) {
                        return $x;
                    }
                });

                foreach ($dataRow as $r) {
                    $dataCombine = array_combine($headers, $r);
                    array_push($data, $dataCombine);
                }

                $data_insert = collect($data)->map(function ($row) use (
                    $minField,
                    $requireds
                ) {
                    $count = 1;
                    $newRow = [];
                    foreach ($row as $index => $value) {
                        if (!in_array($index, $requireds)) {
                            $index = "Campo$count";
                            $count++;
                        }
                        $newRow[$index] = $value;
                    }
                    $newRow = array_filter(
                        $newRow,
                        function ($key) {
                            return !is_numeric($key);
                        },
                        ARRAY_FILTER_USE_KEY
                    );
                    $newRow['zis'] = '';
                    $newRow['actualizado'] = 0;
                    return $newRow;
                });

                DB::beginTransaction();
                $chunks = $data_insert->chunk(500);
                foreach ($chunks as $chunk) {
                    DB::table('vales_zis')->insert($chunk->toArray());
                }
                DB::commit();
            }
            return [
                'success' => true,
                'results' => true,
                'message' => 'archivo cargado con exito',
            ];
        } catch (Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'results' => false,
                'message' => 'Error',
                'error' => $e,
            ];
        }
    }

    public function getZis()
    {
        ini_set('max_execution_time', 800);
        $inicio = date('Y-m-d H:i:s');
        $client = new Client();

        $data = DB::table('vales_zis')
            ->where('actualizado', '!=', '1')
            ->get();
        if (count($data) == 0) {
            return response()->json([
                'success' => true,
                'results' => false,
                'message' =>
                    'No se han encontrado registros nuevos que validar',
                'total' => 0,
            ]);
        }

        $res = $data
            ->map(function ($x) {
                $x = is_object($x) ? (array) $x : $x;
                return $x;
            })
            ->toArray();

        try {
            foreach (array_chunk($res, 500) as $chunk) {
                $array = [];
                //https://sdsh.guanajuato.gob.mx/apis/datosapizis?lat=21.13498&lon=-101.66926

                $array = array_map(function ($x) {
                    return [
                        strval($x['n_0']),
                        'https://sdsh.guanajuato.gob.mx/apis/datosapizis?lat=' .
                        strval($x['lat']) .
                        '&lon=' .
                        strval($x['lon']),
                    ];
                }, $chunk);

                foreach ($array as $r) {
                    $res2 = $client->request('GET', $r[1], ['verify' => false]);
                    $res3 = json_decode($res2->getBody()->getContents());

                    if (!is_null($res3)) {
                        DB::table('vales_zis')
                            ->where('n_0', $r[0])
                            ->update(['zis' => $res3[0], 'actualizado' => '1']);
                    } else {
                        DB::table('vales_zis')
                            ->where('n_0', $r[0])
                            ->update(['zis' => null, 'actualizado' => '1']);
                    }
                }
            }
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            return response()->json([
                'success' => false,
                'results' => false,
                'error' => $e
                    ->getResponse()
                    ->getBody()
                    ->getContents(),
            ]);
        }
        return response()->json([
            'success' => true,
            'results' => true,
            'message' => 'Se guardaron las zis con exito',
            'inicio' => $inicio,
            'fin' => date('Y-m-d H:i:s'),
        ]);
    }

    public function hashPassword()
    {
        $data = DB::table('users_aplicativo_web')
            ->selectRaw('id,UserName')
            ->whereNull('Celular')
            ->get();

        if (count($data) == 0) {
            return response()->json([
                'success' => true,
                'results' => false,
                'message' => 'No se han encontrado registros para procesar',
                'total' => 0,
            ]);
        }

        $res = $data
            ->map(function ($x) {
                $x = is_object($x) ? (array) $x : $x;
                return $x;
            })
            ->toArray();

        foreach (array_chunk($res, 500) as $chunk) {
            foreach ($chunk as $r) {
                $password = Hash::make($r['UserName']);
                if (!is_null($password)) {
                    DB::table('users_aplicativo_web')
                        ->where('id', $r['id'])
                        ->update(['Celular' => $password]);
                }
            }
        }

        return [
            'success' => true,
            'results' => true,
            'message' => 'datos procesados con exito',
        ];
    }
}

class MyReadFilter implements IReadFilter
{
    private $startRow = 0;
    private $endRow = 0;

    public function setRows($startRow, $chunkSize)
    {
        $this->startRow = $startRow;
        $this->endRow = $startRow + $chunkSize;
    }

    public function getRows()
    {
        return $this->endRow;
    }

    public function readCell($columnAddress, $row, $worksheetName = '')
    {
        if ($row > $this->startRow && $row <= $this->endRow) {
            return true;
        }
        return false;
    }
}
