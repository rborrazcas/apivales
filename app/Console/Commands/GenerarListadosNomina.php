<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use DB;
use File;
use Storage;

class GenerarListadosNomina extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate-paysheet-list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    private function getGroups($municipios){
        return DB::table('vales_grupos')
                ->select(
                    'vales_grupos.id',
                    'vales_grupos.idMunicipio',
                    'vales_grupos.CveInterventor',
                    'vales_grupos.idLocalidad',
                    'vales_grupos.ResponsableEntrega',
                    'et_cat_municipio.Nombre as Municipio',
                    'et_cat_localidad_2022.Nombre as Localidad',
                    'vales_grupos.TotalAprobados',
                    'vales_grupos.Remesa',
                    'vales_grupos.created_at',
                    'vales_grupos.UserCreated',
                    'vales_grupos.updated_at',
                    'vales_grupos.Ejercicio'
                )
                ->JOIN(
                    'et_cat_municipio',
                    'et_cat_municipio.id',
                    '=',
                    'vales_grupos.idMunicipio'
                )
                ->JOIN(
                    'et_cat_localidad_2022',
                    'et_cat_localidad_2022.id',
                    '=',
                    'vales_grupos.idLocalidad'
                )
                ->where('Ejercicio', 2023)
                ->whereIn('et_cat_municipio.id', $municipios)
                ->orderBy('et_cat_municipio.SubRegion', 'asc')
                ->orderBy('et_cat_municipio.Nombre', 'asc')
                ->orderBy('vales_grupos.CveInterventor', 'asc')
                ->orderBy('et_cat_localidad_2022.id', 'asc')
                ->orderBy('vales_grupos.ResponsableEntrega', 'asc')
                ->get();
    }

    private function generateFile($data, $dataGroup){
        try {
            $reader = IOFactory::createReader('Xlsx');
            $spreadsheet = $reader->load(
                public_path() . '/archivos/formatoReporteNominaValesv3.xlsx'
            );
            $sheet = $spreadsheet->getActiveSheet();
            $largo = count($data);
            $impresion = $largo + 10;

            $sheet->getPageSetup()->setPrintArea('A1:O' . ($impresion + 15));
            $sheet
                ->getPageSetup()
                ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
            $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_LETTER);

            $largo = count($data);

            $sheet->fromArray($data, null, 'B11');
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setCellValue('K6', $dataGroup->Municipio);
            $sheet->setCellValue('K7', $dataGroup->Localidad);
            $sheet->setCellValue('K9', $dataGroup->ResponsableEntrega);
            $sheet->setCellValue('K4', $dataGroup->NumAcuerdo);
            $sheet->setCellValue('K5', $dataGroup->FechaAcuerdo);
            $sheet->setCellValue('A2', $dataGroup->Leyenda);
            $sheet->setCellValue(
                'A3',
                'Aprobados mediante ' .
                    $dataGroup->NumAcuerdo .
                    ' de fecha ' .
                    $dataGroup->FechaAcuerdo
            );

            $veces = 0;

            if ($largo > 25) {
                for ($lb = 20; $lb < $largo; $lb += 20) {
                    $veces++;
                    $spreadsheet
                        ->getActiveSheet()
                        ->setBreak(
                            'A' . ($lb + 10),
                            \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::BREAK_ROW
                        );
                }
            }

            for ($i = 1; $i <= $largo; $i++) {
                $inicio = 10 + $i;
                $sheet->setCellValue('A' . $inicio, $i);
            }

            $writer = new Xlsx($spreadsheet);
            $strRem = str_replace('/', '_', $dataGroup->Remesa);
            $filename = $strRem .
                    '_' .
                    $dataGroup->idMunicipio .
                    '_' .
                    str_replace(' ', '_', $dataGroup->ResponsableEntrega) .
                    '_formatoNominaVales_'.str_pad($dataGroup->id, 4, '0', STR_PAD_LEFT).'.xlsx';
            $relativePath = 'archivos/FormatosNomina/' .$filename;
            $fullPath = public_path().'/'.$relativePath;

            $writer->save($fullPath);

            return [
                $success = true, 
                $message = 'Archivo del Grupo: '.$dataGroup->id. ' generado con éxito',
                $filename
            ];
        } catch (Exception $error) {
            $this->error($error->getMessage());

            return [
                $success = false, 
                $message = 'Error al generar archivo del Grupo: '.$dataGroup->id,
                $filename = ''
            ];
        }
    }

    private function getReporteNominaVales2023($groupId)
    {
        try {
            $dataGroup = DB::table('vales_grupos as G')
                ->select(
                    'G.id',
                    'R.NumAcuerdo',
                    'R.Leyenda',
                    'R.FechaAcuerdo',
                    'G.TotalAprobados',
                    'G.ResponsableEntrega',
                    'M.Nombre AS Municipio',
                    'L.Nombre AS Localidad',
                    'G.Remesa',
                    'G.idMunicipio'
                )
                ->JOIN('vales_remesas as R', 'R.Remesa', '=', 'G.Remesa')
                ->JOIN('et_cat_municipio as M', 'G.idMunicipio', '=', 'M.Id')
                ->JOIN('et_cat_localidad_2022 as L', 'G.idLocalidad', '=', 'L.id')
                ->where('G.id', '=', $groupId)
                ->first();

            if (!$dataGroup) {
                return  $this->error('No se encontraron resultados del Grupo: '.$groupId);
            }

            $data = DB::table('vales as N')
                ->select(
                    'M.SubRegion AS Region',
                    DB::raw('LPAD(HEX(N.id),6,0) AS ClaveUnica'),
                    'N.CURP',
                    DB::raw(
                        "concat_ws(' ',N.Nombre, N.Paterno, N.Materno) as NombreCompleto"
                    ),
                    'N.Sexo',
                    DB::raw(
                        "concat_ws(' ',N.Calle, if(N.NumExt is null, ' ', concat('NumExt ',N.NumExt)), if(N.NumInt is null, ' ', concat('Int ',N.NumInt))) AS Direccion"
                    ),
                    'N.Colonia',
                    'N.CP',
                    'M.Nombre AS Municipio',
                    'L.Nombre AS Localidad',
                    'VS.SerieInicial',
                    'VS.SerieFinal'
                )
                ->JOIN('et_cat_municipio as M', 'N.idMunicipio', '=', 'M.Id')
                ->JOIN('et_cat_localidad_2022 as L', 'N.idLocalidad', '=', 'L.id')
                ->leftJoin('vales_solicitudes as VS', 'VS.idSolicitud', '=', 'N.id')
                ->WHERE('N.idGrupo', $groupId)
                ->orderBy('M.Nombre', 'asc')
                ->orderBy('N.CveInterventor', 'asc')
                ->orderBy('L.Nombre', 'asc')
                ->orderBy('N.ResponsableEntrega', 'asc')
                ->orderBy('N.Nombre', 'asc')
                ->orderBy('N.Paterno', 'asc')
                ->get();


            if (count($data) == 0) {
                return  $this->error('No se encontraron resultados del Grupo: '.$groupId);
            }

            $data = $data
                ->map(function ($x) {
                    $x = is_object($x) ? (array) $x : $x;
                    return $x;
                })
                ->toArray();

            [$success, $message, $filename] = $this->generateFile($data, $dataGroup);
            if($success == true){
                $log = $dataGroup->id.'|'.$message.'|'.$filename.'\n';
                Storage::append('logs.txt', $log);
            }else{
                $this->error($message);
            }
        } catch (Exception $error) {
            $this->error('Error General en Grupo: '.$dataGroup->id);
            return $this->error($error->getMessage());
        }
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $municipios = DB::table('et_cat_municipio')->whereIn('Region', [1,2,3])->whereNotIn('id', [27, 31, 37])->pluck('id');
        $groups = $this->getGroups($municipios);
        $bar    = $this->output->createProgressBar($groups->count());
        
        $bar->start();
        $groups->each(function($item) use($bar){
            $this->getReporteNominaVales2023($item->id);
            $bar->advance();
        });
        $bar->finish();

        $this->output->success('Finished');
    }

}
