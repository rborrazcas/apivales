<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PadronValidadoExport implements FromQuery, WithHeadings, ShouldQueue
{
    use Exportable;

    protected $remesa;
    protected $region;

    public function __construct($remesa)
    {
        $this->remesa = $remesa;
    }

    public function headings(): array
    {
        return [
            'Identificador',
            'ID',
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
            'Codigo Archivo',
            'RESPONSABLE DE VALIDACION',
            'FECHA VALIDACION',
            'APOYO ANTERIOR',
            'Observacion en CURP',
            //'ESTATUS',
        ];
    }

    public function query()
    {
        return DB::table('padron_validado AS p')
            ->select(
                DB::RAW('LPAD(HEX(p.id),6,0) AS FolioPadron'),
                'p.Identificador',
                'p.Region',
                'p.Nombre',
                'p.Paterno',
                'p.Materno',
                'p.FechaNacimiento',
                'p.Sexo',
                'p.EstadoNacimiento',
                'p.CURP',
                'p.CURPAnterior',
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
                'p.EnlaceOrigen',
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
                    "IF (p.TieneApoyo = 1,'El BENEFICIARIO TIENE APOYO EN OTRA REMESA',NULL) AS ApoyoMultiple"
                ),
                DB::raw(
                    "IF (p.CURPAnterior IS NOT NULL ,'El CURP FUE ACTUALIZADO POR RENAPO',NULL) AS CURPDiferente"
                )
                //'e.Estatus'
            )
            ->Join('users AS u', 'u.id', '=', 'p.idUsuarioCreo')
            ->Join('padron_estatus AS e', 'e.id', '=', 'p.idEstatus')
            ->JOIN('municipios_padron_vales AS m', 'p.Municipio', 'm.Municipio')
            ->JOIN('et_cat_municipio AS mr', 'm.idCatalogo', 'mr.id')
            ->JOIN('padron_archivos AS a', 'a.id', 'p.idArchivo')
            ->Where('p.Remesa', $this->remesa)
            ->ORDERBY('p.id');
    }
}
