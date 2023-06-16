<?php

namespace App\Imports;

use JWTAuth;
use App\Conciliacion;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;

class ConciliacionImport implements
    ToArray,
    WithHeadingRow,
    WithChunkReading,
    WithBatchInserts,
    WithCalculatedFormulas
{
    use Importable;

    private $idArchivo = null;
    private $headers = [
        'cantidad' => null,
        'codigo' => null,
        'responsable_escaneo' => null,
        'farmacia' => null,
        'fecha_de_canje' => null,
        'tipo_de_operacion' => null,
        'fecha_de_captura' => null,
        'mes_de_canje' => null,
        'folio_vale' => null,
        'observacion' => null,
    ];

    public function __construct($data)
    {
        $this->idArchivo = $data;
    }

    public function array(array $rows)
    {
        $userId = JWTAuth::parseToken()->toUser()->id;
        $insertData = [];
        foreach ($rows as $row) {
            $headersValidation = array_intersect_key($this->headers, $row);
            if (count($headersValidation) === 9) {
                $insertData[] = [
                    'idArchivo' => $this->idArchivo,
                    'folio_vale' => trim($row['folio_vale']),
                    'cantidad' => trim($row['cantidad']),
                    'codigo' => $this->cleanLetter(trim($row['codigo'])),
                    'responsable_de_escaneo' => trim(
                        $row['responsable_de_escaneo']
                    ),
                    'farmacia' => trim($row['farmacia']),
                    'fecha_de_canje' => is_numeric($row['fecha_de_canje'])
                        ? \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject(
                            trim($row['fecha_de_canje'])
                        )
                        : null,
                    'tipo_de_operacion' => trim($row['tipo_de_operacion']),
                    'fecha_de_captura' => is_numeric($row['fecha_de_captura'])
                        ? \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject(
                            trim($row['fecha_de_captura'])
                        )
                        : null,
                    'mes_de_canje' => trim($row['mes_de_canje']),
                    'observacion' => trim($row['observacion']),
                    'idUsuarioCargo' => $userId,
                    'FechaCarga' => date('Y-m-d'),
                ];
            }
        }
        if (count($insertData) > 0) {
            DB::table('conciliacion_vales')->insert($insertData);
        }
        unset($insertData);
    }

    public function cleanLetter($c)
    {
        if ($c !== null) {
            if (strlen($c) > 0) {
                $cadena = strtoupper(trim($c));

                $originales =
                    'ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðòóôõöøùúûýýþÿ';
                $modificadas =
                    'AAAAAAACEEEEIIIIDOOOOOOUUUUYBSAAAAAAACEEEEIIIIDOOOOOOUUUYYBY';
                $cadena = utf8_decode($cadena);
                $cadena = strtr(
                    $cadena,
                    utf8_decode($originales),
                    $modificadas
                );
                $cadena = str_replace('.', ' ', $cadena);
                return utf8_encode($cadena);
            } else {
                return null;
            }
        }
        return null;
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function batchSize(): int
    {
        return 1000;
    }

    public function headingRow(): int
    {
        return 1;
    }
}
