<?php

namespace App\Http\Controllers;

use JWTAuth;
use Validator;
use HTTP_Request2;
use GuzzleHttp\Client;
use Carbon\Carbon as time;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\QueryException;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Illuminate\Contracts\Validation\ValidationException;

class FilesTarjetaController extends Controller
{
    public function sendFiles()
    {
        $token = $this->getTokenImpulso();
        if (!$token) {
            $response = [
                'success' => false,
                'results' => false,
                'message' => 'Ocurrio un error al generar el token',
            ];
            return response()->json($response, 200);
        }

        // $request = new HTTP_Request2();
        // $request->setUrl(
        //     'https://seguimiento.guanajuato.gob.mx/ApiTarjeta/api/CargaDocumento'
        // );
        // $request->setMethod(HTTP_Request2::METHOD_POST);
        // $request->setConfig([
        //     'follow_redirects' => true,
        // ]);
        // $request->setHeader([
        //     'Authorization' => 'Bearer ' . $token,
        // ]);

        $reg = DB::table('EnvioINEVales2022')
            ->Select('id', 'CURP', 'Ejercicio', 'idCedula', 'NombreSistema')
            ->Where('Enviado', 2)
            ->WhereRaw('NombreSistema IS NOT NULL')
            ->OrderBy('id', 'ASC')
            ->get();

        $i = 0;

        foreach ($reg as $r) {
            $i++;
            $request = new HTTP_Request2();
            $request->setUrl(
                'https://seguimiento.guanajuato.gob.mx/ApiTarjeta/api/CargaDocumento'
            );
            $request->setMethod(HTTP_Request2::METHOD_POST);
            $request->setConfig([
                'follow_redirects' => true,
                'ssl_verify_peer' => false,
                'ssl_verify_host' => false,
            ]);
            $request->setHeader([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'multipart/form-data',
            ]);

            $extension = explode('.', $r->NombreSistema);

            $request->addPostParameter([
                'IdTipoDocumento' => '1',
                'CURP' => $r->CURP,
                'FechaExpedidaArchivo' => '2023-07-31',
            ]);

            $fileName = $r->CURP . '.' . strtolower($extension[1]);
            $request->addUpload(
                'archivo',
                //Storage::disk('subidos')->path($r->NombreSistema),
                '/Users/diegolopez/Desktop/PruebaEnvioIne/' . $r->NombreSistema,
                $fileName,
                strtolower($extension[1]) == 'pdf'
                    ? 'application/pdf'
                    : 'image/png'
            );

            try {
                //dd($request);
                $response = $request->send();
                $d = json_decode($response->getBody());
                if ($response->getStatus() == 200) {
                    if ($d->result == 1) {
                        DB::table('EnvioINEVales2022')
                            ->where('id', $r->id)
                            ->update([
                                'Enviado' => 1,
                                'FechaEnvio' => date('Y-m-d H:i:s'),
                                'Exception' => $d->message,
                            ]);
                    } else {
                        DB::table('EnvioINEVales2022')
                            ->where('id', $r->id)
                            ->update([
                                'Enviado' => 4,
                                'FechaEnvio' => date('Y-m-d H:i:s'),
                                'Exception' => $d->message,
                            ]);
                    }
                } else {
                    DB::table('EnvioINEVales2022')
                        ->where('id', $r->id)
                        ->update([
                            'Enviado' => 2,
                            'FechaEnvio' => date('Y-m-d H:i:s'),
                            'Exception' =>
                                'Unexpected HTTP status: ' .
                                $response->getStatus() .
                                ' ' .
                                $response->getReasonPhrase(),
                        ]);
                }
            } catch (HTTP_Request2_Exception $e) {
                DB::table('EnvioINEVales2022')
                    ->where('id', $r->id)
                    ->update([
                        'Enviado' => 3,
                        'FechaEnvio' => date('Y-m-d H:i:s'),
                        'Exception' => 'Error: ' . $e->getMessage(),
                    ]);
            }
            if ($i == 1000) {
                $token = $this->getTokenImpulso();
                $i = 0;
            }
        }
        $response = [
            'success' => true,
            'results' => true,
            'message' => 'No hay más archivos por enviar',
        ];
        return response()->json($response, 200);
    }

    public function getTokenImpulso()
    {
        $request = new HTTP_Request2();
        $request->setUrl(
            'https://seguimiento.guanajuato.gob.mx/ApiTarjeta/token'
        );
        $request->setMethod(HTTP_Request2::METHOD_POST);
        $request->setConfig([
            'follow_redirects' => true,
        ]);
        $request->setHeader([
            'Content-Type' => 'application/x-www-form-urlencoded',
        ]);
        $request->addPostParameter([
            'username' => 'sedeshu.peb@gmail.com',
            'password' => 'Temporal_Archivos14',
            'grant_type' => 'password',
        ]);
        try {
            $response = $request->send();
            if ($response->getStatus() == 200) {
                $r = json_decode($response->getBody());
                $token = $r->access_token;
                return $token;
            } else {
                $message =
                    'Unexpected HTTP status: ' .
                    $response->getStatus() .
                    ' ' .
                    $response->getReasonPhrase();
                return null;
            }
        } catch (HTTP_Request2_Exception $e) {
            $message = $e->getMessage();
            return null;
        }
    }
}
