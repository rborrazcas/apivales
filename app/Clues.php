<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Clues extends Model
{
    protected $table = 'clues';
    //
    protected $fillable = [
        'id', 'CLUES', 'NombreEntidad',
        'ClaveEntidad', 'NombreMunicipio', 'ClaveMunicipio',
        'NombreLocalidad', 'ClaveLocalidad', 'NombreJurisdiccion',
        'ClaveJurisdiccion', 'NombreInstitucion', 'ClaveInstitucion',
        //'TipoEstablecimiento', 'ClaveEstablecimiento',
        'NombreTipologia','ClaveTipologia', 
        /*'NombreSubtipologia', 'ClaveSubtipologia',
        'ClaveSCIAN', 'DescripcionClaveSCIAN', 'ConsultoriosMedGral',
        'ConsultoriosOtrasAreas', 'TotalConsultorios', 'CamasEnAreaHospital','CamasEnOtrasAreas',  */
        'TotalCamas', 'NombreUnidad',
        
        /*
        'ClaveVialidad', 'TipoVialidad', 'Vialidad',
        'NumExterior', 'NumInterior', 'ClaveTipoAsentamiento',
        'TipoAsentamiento', 'Asentamiento', 'EntreTipoVialidad1',
        'EntreVialidad1', 'EntreTipoVialidad2', 'EntreVialidad2',
        'ObservacionesDireccion', 'CP', 'EstatusOperacion',
        'ClaveEstatus_Operacion', 'TieneLicenciaSanitaria', 'NumeroLicenciaSanitaria',
        'TieneAvisoFuncionamiento', 'FechaEmisionAvisoFuncionamiento', 'RFCEstablecimiento',
        'FechaContrauccion', 'FechaInicioOperacion', 'UnidadMovilMarca',
        'UnidadMovilModelo', 'UnidadMovilCapacidad', 'UnidadMovilPrograma',
        'UnidadMoveilClavePrograma', 'UnidadMovilTipo', 'UnidadMOveilClaveTipo',
        'UnidadMovilTipologia', 'UnidadMoveilClaveTipologia', 'LONGITUD',
        'LATITUD', 'NombreInsAdm', 'ClaveInsAdm',
        'NivelAtencion', 'ClaveNivelAtencion', 'EstatusAcreditacion',
        'ClaveEstatusAcreditacion', 'Acreditaciones', 'Subacreditacion',
        'EstratoUnidad', 'ClaveEstratoUnidad', 'TipoObra',
        'ClaveTipoObra', 'HorarioAtencion', 'AreasyServicios',
        'UltimoMovimiento', 'FechaUltimoMovimiento', 'MotivoBaja',
        'FechaEfectivaBaja', 'CertificacionCSG', 'TipoCertificacion', */

    ];
}
