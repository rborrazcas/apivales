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

class ConciliacionImport implements
    ToArray,
    WithHeadingRow,
    WithChunkReading,
    WithBatchInserts,
    WithMultipleSheets
{
    use Importable;
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function array(array $rows)
    {
        $userId = JWTAuth::parseToken()->toUser()->id;
        $insertData = [];
        foreach ($rows as $row) {
            $insert_data[] = [
                'cantidad' => trim($row['cantidad']),
                'idUser' => $userId,
                'codigo' => trim($row['codigo']),
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
                'folio_vale' => trim($row['folio_vale']),
                'observacion' => trim($row['observacion']),
            ];
        }
        DB::table('preconciliacion_vales')->insert($insert_data);
        unset($insert_data);
    }

    public function sheets(): array
    {
        return [
            0 => new ConciliacionImport(),
        ];
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function batchSize(): int
    {
        return 1000;
    }
}
