<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PadronBeneficiariosExport implements FromQuery, WithHeadings, ShouldQueue
{
    use Exportable;

    protected $query;

    public function __construct($query)
    {
        $this->query = $query;
    }

    public function headings(): array
    {
        return [
            'Origen',
            'FolioPadron',
            'Región',
            'Nombres *',
            'Apellido_1 *',
            'Apellido_2 *',
            'Fecha_Nac',
            'Sexo',
            'Edo_Nac',
            'CURP *',
            'CURP Anterior',
            'Municipio *',
            'Num_Loc',
            'Localidad *',
            'Colonia *',
            'Cve Colonia',
            'Cve Interventor',
            'Cve Tipo Calle',
            'Calle *',
            'Num_Ext *',
            'Num_Int',
            'CP',
            'Tel_Casa',
            'Tel_Cel *',
            'Tel_Recados',
            'AÑO DE VIGENCIA DE INE *',
            'FOLIO TARJETA GTO CONTIGO SÍ / IMPULSO',
            'Enlace / Origen *',
            'ENLACE INTERVENCION 1',
            'ENLACE INTERVENCION 2',
            'ENLACE INTERVENCION 3',
            'FECHA SOLICITUD',
            'RESPONSABLE DE LA ENTREGA',
            'ESTATUS ORIGEN',
            'Remesa',
            'APOYO ANTERIOR',
            'Observacion en CURP',
            //'ESTATUS',
        ];
    }

    public function query()
    {
        return $this->query;
    }
}
