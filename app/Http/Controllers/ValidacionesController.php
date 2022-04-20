<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use GuzzleHttp\Client;
use App\ValeValidaciones;

use DB;


class ValidacionesController extends Controller
{
    public function copiarTablaVales(){

        try{
            $data = DB::table('vales')
            ->whereNotIn('id',function($query){
                $query->select('id')->from('vales_validaciones');
            })
            ->get();
            
            if(count($data)==0) return response()->json(['success'=>true,'results'=>false,'message'=>'No se han encontrado registros nuevos que procesar','total'=>0]);
               
            //Mapeamos el resultado como un array
            $res = $data->map(function($x){
                $x = is_object($x)?(array)$x:$x;                
                return $x;
                })->toArray();
                $array = [];
            
            $i = 0;
            foreach (array_chunk($res,500) as $chunk)
            {
                foreach($chunk as $data){
                    $array[$i] = $data['id'];
                    $i++;
                 }
                DB::table('vales_validaciones')->insert($chunk);
            }
            DB::table('vales')->whereIn('id',$array)->update(['idStatus'=>6]);

            return response()->json(['success'=>true,'results'=>true,'message'=>'Se realizo el proceso con exito','total'=>count($res)]);
        }
        catch(\Illuminate\Database\QueryException $e){
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    public function getReporteCruceVales(){

        try{
            $select = "M.Region, M.Municipio, M.Apoyos as Meta, M.Solicitudes, (100*(M.Solicitudes/M.Apoyos)) as 'Solicitudes / Meta', (if(R1.Remesa1 is null, 0, R1.Remesa1) + if(R2.Remesa2 is null, 0, R2.Remesa2)) 'Aprobados Comité', 100*((if(R1.Remesa1 is null, 0, R1.Remesa1) + if(R2.Remesa2 is null, 0, R2.Remesa2))/M.Apoyos) 'Avance (Aprobados / Meta)', R1.Remesa1, R2.Remesa2";
            $table1 = "(SELECT M.idMunicipio, M.Region, M.Municipio, M.Apoyos, count(V.id) Solicitudes From meta_municipio M inner join vales V on (V.idMunicipio = M.idMunicipio) group by M.idMunicipio, V.idMunicipio) M";
            $table2 = "(SELECT M.idMunicipio, M.Region, M.Municipio, count(V.id) Remesa1 From meta_municipio M inner join vales V on (V.idMunicipio = M.idMunicipio) where V.Remesa='Remesa1' and V.idStatus=5 group by M.idMunicipio, V.idMunicipio) R1";
            $table3 = "(SELECT M.idMunicipio, M.Region, M.Municipio, count(V.id) Remesa2 From meta_municipio M inner join vales V on (V.idMunicipio = M.idMunicipio) where V.Remesa='Remesa2' and V.idStatus=5 group by M.idMunicipio, V.idMunicipio) R2";

            $data = DB::table(DB::raw($table1))
            ->select(DB::raw($select))
            ->leftJoin(DB::raw($table2),'R1.idMunicipio', '=', 'M.idMunicipio')
            ->leftJoin(DB::raw($table3),'R2.idMunicipio', '=', 'M.idMunicipio')
            ->orderBy('M.Region','ASC')
            ->orderBy('M.Municipio','ASC')
            ->get();

            $res = $data->map(function($x){
                $x = is_object($x)?(array)$x:$x;                
                return $x;
            })->toArray();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $sheet->setCellValue('A1','Región');
            $sheet->setCellValue('B1','Municipio');
            $sheet->setCellValue('C1','Meta');
            $sheet->setCellValue('D1','Solicitudes');
            $sheet->setCellValue('E1','Solicitudes / Meta');
            $sheet->setCellValue('F1','Aprobados Comité');
            $sheet->setCellValue('G1','Avance (Aprobados / Meta)');
            $sheet->setCellValue('H1','Remesa1');
            $sheet->setCellValue('I1','Remesa2');

            $sheet->fromArray($res,null,'A2');

            //guardamos el excel creado y luego lo obtenemos en $file para poder descargarlo
            $writer = new Xlsx($spreadsheet);
            $writer->save('reporteResumenComite.xlsx');
            $file = public_path() .'/reporteResumenComite.xlsx';

            return response()->download($file,'reporteResumenComite.xlsx');
        }
        catch(\Illuminate\Database\QueryException $e){
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

   

    public function cruceVales(){

        try{
            $select = "M.Region, M.Municipio, M.Apoyos as Meta, M.Solicitudes, (100*(M.Solicitudes/M.Apoyos)) as 'Solicitudes / Meta', (if(R1.Remesa1 is null, 0, R1.Remesa1) + if(R2.Remesa2 is null, 0, R2.Remesa2)) 'Aprobados Comité', 100*((if(R1.Remesa1 is null, 0, R1.Remesa1) + if(R2.Remesa2 is null, 0, R2.Remesa2))/M.Apoyos) 'Avance (Aprobados / Meta)', R1.Remesa1, R2.Remesa2";
            $table1 = "(SELECT M.idMunicipio, M.Region, M.Municipio, M.Apoyos, count(V.id) Solicitudes From meta_municipio M inner join vales V on (V.idMunicipio = M.idMunicipio) group by M.idMunicipio, V.idMunicipio) M";
            $table2 = "(SELECT M.idMunicipio, M.Region, M.Municipio, count(V.id) Remesa1 From meta_municipio M inner join vales V on (V.idMunicipio = M.idMunicipio) where V.Remesa='Remesa1' and V.idStatus=5 group by M.idMunicipio, V.idMunicipio) R1";
            $table3 = "(SELECT M.idMunicipio, M.Region, M.Municipio, count(V.id) Remesa2 From meta_municipio M inner join vales V on (V.idMunicipio = M.idMunicipio) where V.Remesa='Remesa2' and V.idStatus=5 group by M.idMunicipio, V.idMunicipio) R2";

            $res = DB::table(DB::raw($table1))
            ->select(DB::raw($select))
            ->leftJoin(DB::raw($table2),'R1.idMunicipio', '=', 'M.idMunicipio')
            ->leftJoin(DB::raw($table3),'R2.idMunicipio', '=', 'M.idMunicipio')
            ->orderBy('M.Region','ASC')
            ->orderBy('M.Municipio','ASC')
            ->get();

            return response()->json(['success'=>true,'results'=>true,'data'=>$res]);
        }
        catch(\Illuminate\Database\QueryException $e){
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    public function setValidaciones(){
        ini_set("max_execution_time", 800);
        $inicio = date('Y-m-d H:i:s');
        $client = new Client();
        $data = DB::table('vales_validaciones')->whereNull('FechaValidacion')->get();

        if(count($data)==0) return response()->json(['success'=>true,'results'=>false,'message'=>'No se han encontrado registros nuevos que validar','total'=>0]);

        $res = $data->map(function($x){
            $x = is_object($x)?(array)$x:$x;                
            return $x;
            })->toArray();

            try{
                foreach (array_chunk($res,500) as $chunk)
                {   
                    $array = [];
                    // dd($chunk);
                    $res2 = $client->request('POST', 'https://api.tableroestrategico.com/api/setCrucesJefatura', [
                        'json' => [$chunk,"token"=>"12345"],"http_errors" => false,
                    ]);
                    
                        //dd($res2->getBody()->getContents());
                    $result = json_decode($res2->getBody()->getContents());
                    // dd($result);
                    for($i=0;$i<count($result);$i++){
                        DB::table('vales_validaciones')->where('id',$result[$i]->id)
                        ->update([
                            "cPlantilla"=>$result[$i]->cPlantilla,
                            "cActoresPoliticos" => $result[$i]->cActoresPoliticos,
                            "celectoralAbogados" => $result[$i]->celectoralAbogados,
                            "ccasasAzules" => $result[$i]->ccasasAzules,
                            "cRCs" => $result[$i]->cRCs,
                            "cRGs" => $result[$i]->cRGs,
                            "cPromocion" => $result[$i]->cPromocion,
                            "cPAN" => $result[$i]->cPAN,
                            "cMORENA" => $result[$i]->cMORENA,
                            "cPRD" => $result[$i]->cPRD,
                            "cPRI" => $result[$i]->cPRI,
                            "cPVEM" => $result[$i]->cPVEM,
                            "cVOTA" => $result[$i]->cVOTA,
                            "cINE" => $result[$i]->cINE,
                            "FechaValidacion" => date('Y-m-d H:i:s')
                        ]);
                        $array[$i] = $result[$i]->id;
                    }
                    DB::table('vales')->whereIn('id',$array)->update(['idStatus'=>7]);

                }

            }
            catch (\GuzzleHttp\Exception\ClientException $e) {

                return response()->json(['success'=>false,'results'=>false, 'error'=>$e->getResponse()->getBody()->getContents()]);

            }

        return response()->json(['success'=>true,'results'=>true,'message'=>'Se validaron los registros','total'=>count($result),'inicio'=>$inicio,'fin'=>date('Y-m-d H:i:s')]);

    }
}
