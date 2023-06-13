<?php

namespace App\Imports;

use JWTAuth;
use App\Padron;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;

class PadronesImport implements
    ToArray,
    WithHeadingRow,
    WithChunkReading,
    WithBatchInserts,
    WithCalculatedFormulas
{
    use Importable;

    private $idArchivo = null;
    private $codigo = null;
    private $remesa = null;
    private $headers = [
        'orden' => null,
        'orden_x_mpio' => null,
        'id' => null,
        'region' => null,
        'nombres' => null,
        'apellido_1' => null,
        'apellido_2' => null,
        'fecha_nac' => null,
        'sexo' => null,
        'edo_nac' => null,
        'curp' => null,
        'validador' => null,
        'municipio' => null,
        'num_loc' => null,
        'localidad' => null,
        'colonia' => null,
        'cve_colonia' => null,
        'cve_interventor' => null,
        'cve_tipo_calle' => null,
        'calle' => null,
        'num_ext' => null,
        'num_int' => null,
        'cp' => null,
        'tel_casa' => null,
        'tel_cel' => null,
        'tel_recados' => null,
        'ano_de_vigencia_de_ine' => null,
        'folio_tarjeta_contigo_si' => null,
        'apoyo_solicitado' => null,
        'vertiente' => null,
        'enlace_origen' => null,
        'largo_curp' => null,
        'frecuencia_curp' => null,
        'periodo' => null,
        'nombres_del_menor' => null,
        'apellido_1_del_menor' => null,
        'apellido_2_del_menor' => null,
        'fecha_nac_del_menor' => null,
        'sexo_del_menor' => null,
        'edo_nac_del_menor' => null,
        'curp_del_menor' => null,
        'validador_curp_del_menor' => null,
        'largo_curp_menor' => null,
        'frecuencia_curp_del_menor' => null,
        'enlace_intervencion_1' => null,
        'enlace_intervencion_2' => null,
        'enlace_intervencion_3' => null,
        'fecha_solicitud' => null,
        'responsable_de_la_entrega' => null,
        'validacion_datos_de_contacto' => null,
        'estatus_origen' => null,
    ];

    public function __construct($dataFile)
    {
        $this->idArchivo = $dataFile['idArchivo'];
        $this->codigo = $dataFile['codigo'];
        $this->remesa = $dataFile['remesa'];
    }

    public function array(array $rows)
    {
        $userId = JWTAuth::parseToken()->toUser()->id;
        $insert_data = [];

        foreach ($rows as $row) {
            $headersValidation = array_intersect_key($this->headers, $row);
            if (count($headersValidation) === 51) {
                $nombre = $this->removeSpaces(
                    $this->cleanLetter($row['nombres'])
                );
                $paterno = $this->removeSpaces(
                    $this->cleanLetter($row['apellido_1'])
                );
                $materno = $this->removeSpaces(
                    $this->cleanLetter($row['apellido_2'])
                );
                $curp = $this->removeSpaces($this->cleanLetter($row['curp']));
                $colonia = $this->removeSpaces(
                    $this->cleanLetter($row['colonia'])
                );
                $calle = $this->removeSpaces($this->cleanLetter($row['calle']));
                $celular = $this->validarTelefono($row['tel_cel']);
                $telefono = $this->validarTelefono($row['tel_casa']);
                $telRecados = $this->validarTelefono($row['tel_recados']);
                $telefonoValido =
                    $celular === 0 && $telefono === 0 && $telRecados === 0
                        ? 0
                        : 1;
                $insert_data[] = [
                    'Orden' => trim($row['orden']),
                    'OrdenMunicipio' => trim($row['orden_x_mpio']),
                    'Identificador' => trim($row['id'])
                        ? trim($row['id'])
                        : null,
                    'Region' => trim($row['region'])
                        ? trim($row['region'])
                        : null,
                    'Nombre' => $nombre,
                    'Paterno' => $paterno,
                    'Materno' => $materno,
                    'FechaNacimiento' => is_numeric($row['fecha_nac'])
                        ? \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject(
                            trim($row['fecha_nac'])
                        )
                        : null,
                    'Sexo' => trim($row['sexo']) ? trim($row['sexo']) : null,
                    'EstadoNacimiento' => trim($row['edo_nac'])
                        ? trim($row['edo_nac'])
                        : null,
                    'CURP' => $curp,
                    'Validador' => trim($row['validador'])
                        ? trim($row['validador'])
                        : null,
                    'Municipio' => trim($row['municipio'])
                        ? trim($row['municipio'])
                        : null,
                    'NumLocalidad' => trim($row['num_loc'])
                        ? trim($row['num_loc'])
                        : null,
                    'Localidad' => trim($row['localidad'])
                        ? trim($row['localidad'])
                        : null,
                    'Colonia' => $colonia,
                    'CveColonia' => trim($row['cve_colonia'])
                        ? $this->remesa . '2023' . trim($row['cve_colonia'])
                        : null,
                    'CveInterventor' => trim($row['cve_interventor'])
                        ? $this->remesa . '2023' . trim($row['cve_interventor'])
                        : null,
                    'CveTipoCalle' => trim($row['cve_tipo_calle'])
                        ? $this->remesa . '2023' . trim($row['cve_tipo_calle'])
                        : null,
                    'Calle' => $calle,
                    'NumExt' => trim($row['num_ext'])
                        ? trim($row['num_ext'])
                        : null,
                    'NumInt' => trim($row['num_int'])
                        ? trim($row['num_int'])
                        : 'S/N',
                    'CP' => trim($row['cp']) ? trim($row['cp']) : null,
                    'Telefono' => trim($row['tel_casa'])
                        ? str_replace(' ', '', trim($row['tel_casa']))
                        : null,
                    'Celular' => trim($row['tel_cel'])
                        ? str_replace(' ', '', trim($row['tel_cel']))
                        : null,
                    'TelRecados' => trim($row['tel_recados'])
                        ? str_replace(' ', '', trim($row['tel_recados']))
                        : null,
                    'FechaIne' => trim($row['ano_de_vigencia_de_ine'])
                        ? trim($row['ano_de_vigencia_de_ine'])
                        : null,
                    'FolioTarjetaContigoSi' => trim(
                        $row['folio_tarjeta_contigo_si']
                    )
                        ? trim($row['folio_tarjeta_contigo_si'])
                        : null,
                    'Apoyo' => trim($row['apoyo_solicitado'])
                        ? trim($row['apoyo_solicitado'])
                        : null,
                    'Variante' => trim($row['vertiente'])
                        ? trim($row['vertiente'])
                        : null,
                    'Enlace' => trim($row['enlace_origen'])
                        ? trim($row['enlace_origen'])
                        : null,
                    'LargoCURP' => trim($row['largo_curp'])
                        ? trim($row['largo_curp'])
                        : null,
                    'FrecuenciaCURP' => trim($row['frecuencia_curp'])
                        ? trim($row['frecuencia_curp'])
                        : null,
                    'Periodo' => trim($row['periodo'])
                        ? trim($row['periodo'])
                        : null,
                    // 'NombreMenor' => trim($row['nombres_del_menor'])
                    //     ? trim($row['nombres_del_menor'])
                    //     : null,
                    // 'PaternoMenor' => trim($row['apellido_1_del_menor'])
                    //     ? trim($row['apellido_1_del_menor'])
                    //     : null,
                    // 'MaternoMenor' => trim($row['apellido_2_del_menor'])
                    //     ? trim($row['apellido_1_del_menor2'])
                    //     : null,
                    // 'FechaNacimientoMenor' => is_numeric(
                    //     $row['fecha_nac_del_menor']
                    // )
                    //     ? \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject(
                    //         trim($row['fecha_nac_del_menor'])
                    //     )
                    //     : null,
                    // 'SexoMenor' => trim($row['sexo_del_menor'])
                    //     ? trim($row['sexo_del_menor'])
                    //     : null,
                    // 'EstadoNacimientoMenor' => trim($row['edo_nac_del_menor'])
                    //     ? trim($row['edo_nac_del_menor'])
                    //     : null,
                    // 'CURPMenor' => trim($row['curp_del_menor'])
                    //     ? trim($row['curp_del_menor'])
                    //     : null,
                    // 'ValidadorCURPMenor' => trim(
                    //     $row['validador_curp_del_menor']
                    // )
                    //     ? trim($row['validador_curp_del_menor'])
                    //     : null,
                    // 'LargoCURPMenor' => trim($row['largo_curp_menor'])
                    //     ? trim($row['largo_curp_menor'])
                    //     : null,
                    // 'FrecuenciaCURPMenor' => trim(
                    //     $row['frecuencia_curp_del_menor']
                    // )
                    //     ? trim($row['frecuencia_curp_del_menor'])
                    //     : null,
                    'EnlaceIntervencion1' => trim($row['enlace_intervencion_1'])
                        ? trim($row['enlace_intervencion_1'])
                        : null,
                    'EnlaceIntervencion2' => trim($row['enlace_intervencion_2'])
                        ? trim($row['enlace_intervencion_2'])
                        : null,
                    'EnlaceIntervencion3' => trim($row['enlace_intervencion_3'])
                        ? trim($row['enlace_intervencion_3'])
                        : null,
                    'FechaSolicitud' => is_numeric($row['fecha_solicitud'])
                        ? \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject(
                            trim($row['fecha_solicitud'])
                        )
                        : null,
                    'ResponsableEntrega' => trim(
                        $row['responsable_de_la_entrega']
                    )
                        ? trim($row['responsable_de_la_entrega'])
                        : null,
                    'EstatusOrigen' => trim($row['estatus_origen'])
                        ? strtoupper(trim($row['estatus_origen']))
                        : null,
                    'idUsuarioCreo' => $userId,
                    'FechaCreo' => date('Y-m-d h-m-s'),
                    'idArchivo' => $this->idArchivo,
                    'Remesa' => $this->remesa,
                    'NoValido' => $this->is_curp($row['curp']) ? 0 : 1,
                    'NombreValido' => $this->validarCadena($nombre),
                    'PaternoValido' => $this->validarCadena($paterno),
                    'CURPValido' => $this->is_curp($row['curp']) ? 1 : 0,
                    'MunicipioValido' => $this->validarCadena(
                        $row['municipio']
                    ),
                    'LocalidadValido' =>
                        $this->validarCadena($row['num_loc']) === 1
                            ? $this->esNumero($row['num_loc'])
                            : 0,
                    'ColoniaValido' => $this->validarCadena($colonia),
                    'CalleValido' => $this->validarCadena($calle),
                    'CPValido' =>
                        $this->validarCadena($row['cp'], true, 5) === 1
                            ? $this->esNumero($row['cp'])
                            : 0,
                    'TelefonoValido' => $telefono,
                    'CelularValido' => $celular,
                    'TelRecadosValido' => $telRecados,
                    'TelefonoContactoValido' => $telefonoValido,
                    // 'CelularValido' => $this->validarTelefono($row['tel_cel']),
                    'NumExtValido' => $this->validarCadena($row['num_ext']),
                    'FechaIneValido' =>
                        $this->validarCadena(
                            $row['ano_de_vigencia_de_ine'],
                            true,
                            4
                        ) === 1
                            ? $this->esNumero(
                                $row['ano_de_vigencia_de_ine'],
                                true
                            )
                            : 0,
                    'EnlaceValido' => $this->validarCadena(
                        $row['enlace_origen']
                    ),
                    'ResponsableEntregaValido' => $this->validarCadena(
                        $row['responsable_de_la_entrega']
                    ),
                    'EstatusOrigenValido' =>
                        $this->validarCadena(
                            $row['estatus_origen'],
                            true,
                            2
                        ) === 1
                            ? (in_array(strtoupper($row['estatus_origen']), [
                                'SI',
                                'NO',
                            ])
                                ? 1
                                : 0)
                            : 0,
                    'Aprobado' =>
                        $this->validarCadena(
                            $row['estatus_origen'],
                            true,
                            2
                        ) === 1
                            ? (strtoupper($row['estatus_origen']) == 'NO'
                                ? 0
                                : 1)
                            : 1,
                ];
            }
        }
        if (count($insert_data) > 0) {
            DB::table('padron_carga_inicial')->insert($insert_data);
        }

        unset($insert_data);
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

    public function removeSpaces($c)
    {
        if ($c !== null) {
            $array = explode(' ', $c);
            $newC = '';

            foreach ($array as $palabra) {
                if ($palabra !== null && $palabra !== '') {
                    $newC = $newC . ' ' . $palabra;
                }
            }

            if ($newC === '') {
                return null;
            }

            return trim(strtoupper($newC));
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

    public function is_curp($string)
    {
        if (
            $string !== null &&
            trim($string) !== '' &&
            strlen($string) === 18
        ) {
            // By @JorhelR
            // TRANSFORMARMOS EN STRING EN MAYÚSCULAS RESPETANDO LAS Ñ PARA EVITAR ERRORES
            $string = mb_strtoupper($string, 'UTF-8');
            // EL REGEX POR @MARIANO
            $pattern =
                '/^([A-Z][AEIOUX][A-Z]{2}\d{2}(?:0[1-9]|1[0-2])(?:0[1-9]|[12]\d|3[01])[HM](?:AS|B[CS]|C[CLMSH]|D[FG]|G[TR]|HG|JC|M[CNS]|N[ETL]|OC|PL|Q[TR]|S[PLR]|T[CSL]|VZ|YN|ZS)[B-DF-HJ-NP-TV-Z]{3}[A-Z\d])(\d)$/';
            $validate = preg_match($pattern, $string, $match);
            if ($validate === false) {
                // SI EL STRING NO CUMPLE CON EL PATRÓN REQUERIDO RETORNA FALSE
                return false;
            }
            if (count($match) == 0) {
                return false;
            }
            // ASIGNAMOS VALOR DE 0 A 36 DIVIDIENDO EL STRING EN UN ARRAY
            $ind = preg_split(
                '//u',
                '0123456789ABCDEFGHIJKLMNÑOPQRSTUVWXYZ',
                null,
                PREG_SPLIT_NO_EMPTY
            );
            // REVERTIMOS EL CURP Y LE COLOCAMOS UN DÍGITO EXTRA PARA QUE EL VALOR DEL PRIMER CARACTER SEA 0 Y EL DEL PRIMER DIGITO DE LA CURP (INVERSA) SEA 1
            $vals = str_split(strrev($match[0] . '?'));
            // ELIMINAMOS EL CARACTER ADICIONAL Y EL PRIMER DIGITO DE LA CURP (INVERSA)
            unset($vals[0]);
            unset($vals[1]);
            $tempSum = 0;
            foreach ($vals as $v => $d) {
                // SE BUSCA EL DÍGITO DE LA CURP EN EL INDICE DE LETRAS Y SU CLAVE(VALOR) SE MULTIPLICA POR LA CLAVE(VALOR) DEL DÍGITO. TODO ESTO SE SUMA EN $tempSum
                $tempSum = array_search($d, $ind) * $v + $tempSum;
            }
            // ESTO ES DE @MARIANO NO SUPE QUE ERA
            $digit = 10 - ($tempSum % 10);
            // SI EL DIGITO CALCULADO ES 10 ENTONCES SE REASIGNA A 0
            $digit = $digit == 10 ? 0 : $digit;
            // SI EL DIGITO COINCIDE CON EL ÚLTIMO DÍGITO DE LA CURP RETORNA TRUE, DE LO CONTRARIO FALSE
            return $match[2] == $digit;
        } else {
            return false;
        }
    }

    public function validarCadena(
        $string,
        $validaLongitud = false,
        $largo = null
    ) {
        if ($string !== null) {
            if (strlen(trim($string)) > 0) {
                if (!stristr($string, '#N/D')) {
                    if (!stristr($string, '#VALOR!')) {
                        if ($validaLongitud) {
                            if (strlen($string) !== $largo) {
                                return 0;
                            }
                        }
                        return 1;
                    }
                }
            }
        }
        return 0;
    }

    public function validarTelefono($c)
    {
        if ($c === null || strlen(trim($c)) === 0) {
            return 0;
        }

        $cadena = str_replace(' ', '', $c);

        if ($this->validarCadena($cadena, true, 10) === 0) {
            return 0;
        }

        if (!$this->esNumero($cadena)) {
            return 0;
        }

        $patern = '/(\d)\1\1\1\1\1\1\1\1\1/';
        $flagRepetidos = preg_match($patern, $cadena);

        if ($flagRepetidos) {
            return 0;
        }

        $patern = '/^(123)(\d){7}/';
        $flagRepetidos = preg_match($patern, $cadena);

        if ($flagRepetidos) {
            return 0;
        }

        return 1;
    }

    public function esNumero($cadena, $compara = false)
    {
        if ($cadena !== null) {
            if (is_numeric($cadena)) {
                if ($compara) {
                    $anioActual = date('Y');
                    if ($cadena < $anioActual) {
                        return 0;
                    }
                }
                return 1;
            }
        }
        return 0;
    }
}
