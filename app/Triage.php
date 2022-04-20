<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Triage extends Model
{
    protected $table = 'triage';
    //
    protected $fillable = [
        'id', 
        'idPaciente', 
        'CURP', 
        'Nombre', 
        'Paterno', 
        'Materno', 
        'FechaNacimiento', 
        'Sexo', 
        'FechaHora', 
        'Calle', 
        'NumExt', 
        'NumInt', 
        'Colonia', 
        'CP', 
        'idMunicipio', 
        'idLocalidad', 
        'Diagnostico', 
        'FechaInicioPadecimiento', 
        'Menos7Dias', 
        'TieneTos', 
        'TieneFiebre38', 
        'TieneCefalea', 
        'Tiene2oMas', 
        'SintomaDisnea', 
        'SintomaArtralgias', 
        'SintomaMialgias', 
        'SintomaOdinofagia', 
        'SintomaRinorrea', 
        'SintomaConjuntivitis', 
        'SintomaDolorToracico', 
        'SintomaVomito', 
        'SintomaDiarrea', 
        'CEdad65o5', 
        'CDiabetesMellitus', 
        'CHipertensionArterial', 
        'CObesidad', 
        'CEnfermedadCardiovascular', 
        'CNeumopatiaCronica', 
        'CNefropatiaCronica', 
        'CHepatopatiaCronica', 
        'CEnfermedadHematologica', 
        'CEnfermedadNeurologicaCronica', 
        'CVIH', 
        'CCancer', 
        'COtraInmunosupresion', 
        'CEmbarazo', 
        'C2SemanasPuerperio',
        'TieneComorbilidad', 
        'FC', 
        'FR', 
        'SaO2', 
        'SaO2_FiO2', 
        'TAMin', 
        'TAMax', 
        'Glasgow', 
        'Temp', 
        'MalestarGeneral', 
        'ExisteAlgunaAlteracion', 
        'PosibleCovid19', 
        'UserCreated', 
        'UserUpdated', 
        'idServicio', 
        'created_at', 
        'updated_at'
        
    ];
}
