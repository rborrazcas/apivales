<?php

namespace App\Http\Controllers;

use Validator;
use Illuminate\Http\Request;
use DB;

class ValesSeriesController extends Controller
{
    //
    public function getSerieVale(Request $request)
    {
        $v = Validator::make($request->all(), [
            'CodigoBarra' => 'required',
        ]);

        if ($v->fails()) {
            $response = [
                'success' => false,
                'results' => false,
                'errors' => $v->errors(),
                'data' => [],
            ];

            return response()->json($response, 200);
        }

        // if(strlen($request->CodigoBarra) < 22){
        //     $resultall = DB::table('vales_series')->where('Ejercicio','=',date("Y"))->where('Serie','=',$request->CodigoBarra)->first();
        // }
        // else{
        //     $resultall = DB::table('vales_series')->where('Ejercicio','=',date("Y"))->where('CodigoBarra','=',$request->CodigoBarra)->first();
        // }
        // if($resultall){
        //     $nueva_serie = $resultall->Serie + 9;
        //     $result2 = DB::table('vales_series')->where('Ejercicio','=',date("Y"))->where('Serie','=',$nueva_serie)->first();

        //     $response = ['FolioInicial'=>$resultall->Serie,'FolioFinal'=>$result2->Serie,
        //     'CodigoBarraInicial'=>$resultall->CodigoBarra,'CodigoBarraFinal'=>$result2->CodigoBarra];
        //     return response ()->json(['success'=>true,'results'=>true,'data'=>$response]);
        // }

        if (strlen($request->CodigoBarra) < 22) {
            $resultall = DB::table('vales_series')
                ->where('Ejercicio', '=', 2022)
                ->where('Serie', '=', $request->CodigoBarra)
                ->first();
        } else {
            $resultall = DB::table('vales_series')
                ->where('Ejercicio', '=', 2022)
                ->where('CodigoBarra', '=', $request->CodigoBarra)
                ->first();
        }
        if ($resultall) {
            $nueva_serie = $resultall->Serie + 9;
            $result2 = DB::table('vales_series')
                ->where('Ejercicio', '=', 2022)
                ->where('Serie', '=', $nueva_serie)
                ->first();

            $response = [
                'FolioInicial' => $resultall->Serie,
                'FolioFinal' => $result2->Serie,
                'CodigoBarraInicial' => $resultall->CodigoBarra,
                'CodigoBarraFinal' => $result2->CodigoBarra,
            ];
            return response()->json([
                'success' => true,
                'results' => true,
                'data' => $response,
            ]);
        }

        return response()->json([
            'success' => true,
            'results' => false,
            'data' => [],
            'message' => 'No se encontraron resultados.',
        ]);
        //Serie,GTOGRMXIE200000011A35E
    }

    public function getSerieVale2023(Request $request)
    {
        $v = Validator::make($request->all(), [
            'CodigoBarra' => 'required',
        ]);

        if ($v->fails()) {
            $response = [
                'success' => false,
                'results' => false,
                'errors' => $v->errors(),
                'data' => [],
            ];

            return response()->json($response, 200);
        }

        // if (strlen($request->CodigoBarra) < 22) {
        //     $resultall = DB::table('folios_vales_2023')
        //         ->where('Serie', '=', $request->CodigoBarra)
        //         ->first();
        // } else {
        //     $resultall = DB::table('folios_vales_2023')
        //         ->where('CodigoBarra', '=', $request->CodigoBarra)
        //         ->first();
        // }
        $user = auth()->user();

        if (strlen($request->CodigoBarra) < 22) {
            $resultall = DB::table('folios_vales_2023')

                ->where('Serie', '=', $request->CodigoBarra)
                ->first();
        } else {
            $resultall = DB::table('folios_vales_2023')

                ->where('CodigoBarras', '=', $request->CodigoBarra)
                ->first();
        }

        if ($resultall) {
            if ($resultall->Estatus == 0) {
                $data = [
                    'CodigoBarras' => $request->CodigoBarra,
                    'idUsuarioValido' => $user->id,
                    'FechaValido' => date('Y-m-d H:i:s'),
                    'esValido' => 0,
                    'Error' => 'Codigo de barras bloqueado',
                ];

                DB::table('vales_folios_solicitud')->insert($data);
                return response()->json([
                    'success' => true,
                    'results' => false,
                    'data' => [],
                    'message' => 'La valera se encuentra bloqueada.',
                ]);
            }

            $nueva_serie = $resultall->Serie + 9;
            $result2 = DB::table('folios_vales_2023')

                ->where('Serie', '=', $nueva_serie)
                ->first();

            $response = [
                'FolioInicial' => $resultall->Serie,
                'FolioFinal' => $result2->Serie,
                'CodigoBarraInicial' => $resultall->CodigoBarras,
                'CodigoBarraFinal' => $result2->CodigoBarras,
            ];
            return response()->json([
                'success' => true,
                'results' => true,
                'data' => $response,
            ]);
        }

        if ($resultall) {
            $nueva_serie = $resultall->Serie + 9;
            $result2 = DB::table('folios_vales_2023')
                ->where('Serie', '=', $nueva_serie)
                ->first();

            $response = [
                'FolioInicial' => $resultall->Serie,
                'FolioFinal' => $result2->Serie,
                'CodigoBarraInicial' => $resultall->CodigoBarras,
                'CodigoBarraFinal' => $result2->CodigoBarras,
            ];
            return response()->json([
                'success' => true,
                'results' => true,
                'data' => $response,
            ]);
        }

        return response()->json([
            'success' => true,
            'results' => false,
            'data' => [],
            'message' => 'No se encontraron resultados.',
        ]);
        //Serie,GTOGRMXIE200000011A35E
    }
}
