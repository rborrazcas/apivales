<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DB;
use Storage;
use File;
use PDF;
use Zipper;
use Milon\Barcode\DNS1D;

class GenerateZipFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate-zip-files';

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

    private function getAcuseVales2023($groupId){
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', 1000);
        $resGpo = DB::table('vales_grupos as G')
            ->select(
                'G.id',
                'G.idMunicipio',
                'G.Remesa',
                'G.TotalAprobados',
                'G.ResponsableEntrega'
            )
            ->where('G.id', '=', $groupId)
            ->first();

        $carpeta = $resGpo->id . $resGpo->idMunicipio . $resGpo->Remesa;

        $path = public_path() . '/subidos/' . $carpeta;
        $fileExists = public_path() . '/subidos/' . $carpeta . '.zip';

        // if (file_exists($fileExists)) {
        //     return response()->download($fileExists);
        // }

        $res = DB::table('vales as N')
            ->select(
                DB::raw('LPAD(HEX(N.id),6,0) AS id'),
                'N.id  AS idVale',
                'vr.NumAcuerdo AS acuerdo',
                'M.SubRegion AS region',
                'N.ResponsableEntrega AS enlace',
                DB::raw(
                    "concat_ws(' ',N.Nombre, N.Paterno, N.Materno) as nombre"
                ),
                'N.curp',
                DB::raw(
                    "concat_ws(' ',N.Calle, if(N.NumExt is null, ' ', concat('NumExt ',N.NumExt)), if(N.NumInt is null, ' ', concat('Int ',N.NumInt))) AS domicilio"
                ),
                'M.Nombre AS municipio',
                'L.Nombre AS localidad',
                'N.Colonia AS colonia',
                'N.CP AS cp',
                'VS.SerieInicial AS folioinicial',
                'VS.SerieFinal AS foliofinal'
            )
            ->JOIN('et_cat_municipio as M', 'N.idMunicipio', '=', 'M.Id')
            ->JOIN('et_cat_localidad_2022 as L', 'N.idLocalidad', '=', 'L.id')
            ->JOIN(
                DB::RAW(
                    '(SELECT idSolicitud,SerieInicial,SerieFinal FROM vales_solicitudes WHERE Ejercicio = 2023) as VS'
                ),
                'VS.idSolicitud',
                '=',
                'N.id'
            )
            ->Join('vales_remesas AS vr', 'N.Remesa', '=', 'vr.Remesa')
            ->join('vales_status as E', 'N.idStatus', '=', 'E.id')
            ->where('N.idGrupo', '=', $resGpo->id)
            ->orderBy('M.Nombre', 'asc')
            ->orderBy('N.CveInterventor')
            ->orderBy('L.Nombre', 'asc')
            ->orderBy('N.ResponsableEntrega', 'asc')
            ->orderBy('N.Nombre', 'asc')
            ->orderBy('N.Paterno', 'asc')
            ->get();

        $d = $res
            ->map(function ($x) {
                $x = is_object($x) ? (array) $x : $x;
                $x['codigo'] = DNS1D::getBarcodePNG($x['id'], 'C39');
                return $x;
            })
            ->toArray();
        unset($data);
        unset($res);

        if (count($d) == 0) {
            $file =
                public_path() . '/archivos/formatoReporteNominaValesv3.xlsx';

            return response()->download(
                $file,
                $resGpo->Remesa .
                    '_' .
                    $resGpo->idMunicipio .
                    '_' .
                    $resGpo->ResponsableEntrega .
                    '_NominaValesGrandeza' .
                    date('Y-m-d') .
                    '.xlsx'
            );
        }

        $nombreArchivo =
            'acuses_vales_' .
            $resGpo->id .
            '_' .
            $resGpo->idMunicipio .
            '_' .
            $resGpo->Remesa;

        File::makeDirectory($path, $mode = 0777, true, true);

        $counter = 0;
        foreach (array_chunk($d, 20) as $arrayData) {
            $counter++;
            $vales = $arrayData;
            $pdf = \PDF::loadView('pdf', compact('vales'))->save(
                $path . '/' . $nombreArchivo . '_' . strval($counter) . '.pdf'
            );
            unset($pdf);
        }

        $this->createZipEvidencia($carpeta);
        dd(public_path('subidos/' . $carpeta . '.zip'));
    
    }

    private function createZipEvidencia($carpeta)
    {
        try {
            $files = glob(public_path('subidos/' . $carpeta . '/*'));
            $fileName = $carpeta . '.zip';
            $path = public_path('subidos/' . $fileName);
            Zipper::make($path)
                ->add($files)
                ->close();
            if (\file_exists(public_path('subidos/' . $carpeta))) {
                File::deleteDirectory(public_path('subidos/' . $carpeta));
            }
        } catch (Exception $e) {
            return false;
        }
    }
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $regionId = 1;
        $groups = DB::table('vales_grupos')->whereIn('idMunicipio',function($query) use($regionId){
            $query->select('id')->from('et_cat_municipio')->where('Region', $regionId);
        })->get();

        $groups->each(function($row){
            $groupId = $row->id;
            dd($groupId);
        });
        // $this->getAcuseVales2023($groupId);
        // dd(public_path('subidos/' . $carpeta . '.zip'));
    }
}
