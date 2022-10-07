<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Client;

class EstatusCalentadorVentanilla extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vim:calentador';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Consulta el estatus en VIM de los registros enviados';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('Actualizando Folios..');
        $folios = DB::table('calentadores_cedulas')
            ->select('id', 'idSolicitud', 'Folio')
            ->whereRaw('FechaElimino IS NULL')
            ->where('idEstatus', 8)
            ->where('CodigoVentanilla', '<>', 5)
            ->where('ListaParaEnviar', 2)
            ->get()
            ->chunk(500);

        $user = auth()->user();

        if ($folios != null) {
            foreach ($folios as $info) {
                foreach ($info as $folio) {
                    $urlValidacionFolio =
                        'https://api-integracion-ventanilla-impulso.guanajuato.gob.mx/v1/application/cedula/' .
                        $folio->Folio;
                    $client = new Client();
                    $response = $client->request('GET', $urlValidacionFolio, [
                        'verify' => false,
                        'headers' => [
                            'Content-Type' => 'multipart/form-data',
                            'Authorization' => '616c818fe33268648502f962',
                        ],
                    ]);

                    $responseBody = json_decode($response->getBody());
                    if ($responseBody->success) {
                        $codigoSolicitud =
                            $responseBody->result->estausLog->codigo;
                        $estatusSolicitud =
                            $responseBody->result->estausLog->descripcion;

                        DB::table('calentadores_cedulas')
                            ->where('id', $folio->id)
                            ->update([
                                'EstatusVentanilla' => $estatusSolicitud,
                                'CodigoVentanilla' => $codigoSolicitud,
                            ]);

                        DB::table('calentadores_solicitudes')
                            ->where('id', $folio->idSolicitud)
                            ->update([
                                'EstatusVentanilla' => $estatusSolicitud,
                                'CodigoVentanilla' => $codigoSolicitud,
                            ]);
                    }
                }
            }
        }
        $this->info('Folios actualizados correctamente');
    }
}
