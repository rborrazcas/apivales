<?php

namespace App\Http\Controllers\ControllersPulseras;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

use PhpOffice\PhpWord\TemplateProcessor;
use Illuminate\Filesystem\Filesystem;
use \Milon\Barcode\DNS1D;

use DB;
use File;
use Zipper;

use PhpOffice\PhpPresentation\IOFactory as IOFactories;

use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\Style\Alignment;
use PhpOffice\PhpPresentation\Style\Color;

use App\VNegociosFiltros;
use Arr;
use Illuminate\Contracts\Validation\ValidationException;
set_time_limit(0);

class ReporteController extends Controller
{

    public static function crearBordes($largo,$columna,&$sheet){

        for($i=0;$i<$largo;$i++){
            $inicio = 6 + $i;

            $sheet->getStyle($columna.$inicio)->getBorders()->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
            $sheet->getStyle($columna.$inicio)->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
            $sheet->getStyle($columna.$inicio)->getBorders()->getLeft()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
            $sheet->getStyle($columna.$inicio)->getBorders()->getRight()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        }
    }

    public function getReporteInvitados(Request $request){

        $res = DB::table('bravos_invitados')
            ->select(
                'bravos_invitados.Folio',
                DB::raw("IFNULL(bravos_invitados.CodigoBarras,'Sin código de barra') as CodigoBarras"),
                DB::raw('UPPER(bravos_invitados.Responsable) as Responsable'),
                'bravos_invitados.NumeroInvitado',
                'bravos_invitados.Municipio',
                DB::raw('UPPER(bravos_invitados.Nombres) as Nombres'),
                DB::raw('UPPER(bravos_invitados.Materno) as Materno'),
                DB::raw('UPPER(bravos_invitados.Paterno) as Paterno'),
                DB::raw('UPPER(CONCAT_WS(" ",bravos_invitados.Nombres,bravos_invitados.Paterno,bravos_invitados.Materno))as NombreCompleto'),
                DB::raw('UPPER(bravos_invitados.CURP) as CURP'),
                'bravos_invitados.Celular',
                'bravos_invitados.NumeroBurbuja',
                'bravos_invitados.created_at',
                'bravos_invitados.updated_at',
                'bravos_invitados.UserCreated',
                'bravos_invitados.UserOwned',
                'bravos_invitados.UserUpdated'

            );

            
            $user = auth()->user();
            $filtro_usuario=VNegociosFiltros::where('idUser','=',$user->id)->where('api','=','getListadoInvitados')->first();
            if($filtro_usuario){
                $hoy = date("Y-m-d H:i:s");    
                $intervalo = $filtro_usuario->updated_at->diff($hoy);
                if($intervalo->h===0){
                    $parameters = unserialize($filtro_usuario->parameters);
                    
                    if(isset($parameters['excluir_asignados'])){
                        if($parameters['excluir_asignados']==true){
                            $res->whereNull('CodigoBarras');
                        }
                    }
                    if(isset($parameters['NombreCompleto'])){
                        $filtro_recibido = $parameters['NombreCompleto'];
                        $filtro_recibido = str_replace(" ","",$filtro_recibido);
                        $res->where(
                            DB::raw("
                            REPLACE(
                            CONCAT(
                                bravos_invitados.Nombres,
                                bravos_invitados.Paterno,
                                bravos_invitados.Materno,
                                bravos_invitados.Paterno,
                                bravos_invitados.Nombres,
                                bravos_invitados.Materno,
                                bravos_invitados.Materno,
                                bravos_invitados.Nombres,
                                bravos_invitados.Paterno,
                                bravos_invitados.Nombres,
                                bravos_invitados.Materno,
                                bravos_invitados.Paterno,
                                bravos_invitados.Paterno,
                                bravos_invitados.Materno,
                                bravos_invitados.Nombres,
                                bravos_invitados.Materno,
                                bravos_invitados.Paterno,
                                bravos_invitados.Nombres
                            ), ' ', '')")
                    
                            ,'like',"%".$filtro_recibido."%");
                        
                    }
        
                    $flag = 0;
                    if(isset($parameters['filtered'])){
        
                        for($i=0;$i<count($parameters['filtered']);$i++){
        
                            if($flag==0){
                                if($parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'id') !== false
                                || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'Folio') !== false
                                || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'NumeroInvitado') !== false
                                || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'NumeroBurbuja') !== false
                                || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'User') !== false
                                ){
                                    if(is_array ($parameters['filtered'][$i]['value'])){
                                        $res->whereIn($parameters['filtered'][$i]['id'],$parameters['filtered'][$i]['value']);
                                    }else{
                                        $res->where($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
                                    }
                                    
                                }else{
                                        if(strpos($parameters['filtered'][$i]['id'], 'is') !== false){
                                            $res->where($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
                                        }else{
                                            $res->where($parameters['filtered'][$i]['id'],'LIKE','%'.$parameters['filtered'][$i]['value'].'%');
                                        }
                                }
                                $flag = 1;
                            }
                            else{
                                if($parameters['tipo']=='and'){
                                    if($parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'id') !== false
                                    || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'Folio') !== false
                                    || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'NumeroInvitado') !== false
                                    || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'NumeroBurbuja') !== false
                                    || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'User') !== false
                                    ){
                                        if(is_array($parameters['filtered'][$i]['value'])){
                                            $res->whereIn($parameters['filtered'][$i]['id'],$parameters['filtered'][$i]['value']);
                                        }else{
                                            $res->where($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
                                        }
                                    }else{
                                            if(strpos($parameters['filtered'][$i]['id'], 'is') !== false){
                                                $res->where($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
                                            }else{
                                                $res->where($parameters['filtered'][$i]['id'],'LIKE','%'.$parameters['filtered'][$i]['value'].'%');
                                            }
                                    } 
                                }
                                else{
                                    if($parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'id') !== false
                                    || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'Folio') !== false
                                    || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'NumeroInvitado') !== false
                                    || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'NumeroBurbuja') !== false
                                    || $parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'User') !== false
                                    ){
                                        if(is_array ($parameters['filtered'][$i]['value'])){
                                            $res->orWhereIn($parameters['filtered'][$i]['id'],$parameters['filtered'][$i]['value']);
                                        }else{
                                            $res->orWhere($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
                                        }
                                    }else{
                                            if(strpos($parameters['filtered'][$i]['id'], 'is') !== false){
                                                $res->orWhere($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
                                            }else{
                                            $res->orWhere($parameters['filtered'][$i]['id'],'LIKE','%'.$parameters['filtered'][$i]['value'].'%');
                                            }
                                    }
                                }
                            }
                        }
                    }
        
                    $page = $parameters['page'];
                    $pageSize = $parameters['pageSize'];
        
                    $startIndex =  $page * $pageSize;
                    if(isset($parameters['sorted'])){
        
                        for($i=0;$i<count($parameters['sorted']);$i++){
        
                            if($parameters['sorted'][$i]['desc']===true){
        
                                $res->orderBy($parameters['sorted'][$i]['id'],'desc');
                            }
                            else{
                                $res->orderBy($parameters['sorted'][$i]['id'],'asc');
                            }
                        }
                    }
                }
                else{
                    return ['success'=>true,'results'=>false,
                    'total'=>0,'filtros'=>'Filtros expirados','data'=>[],
                    'message'=>"Este reporte expiro porque tiene mas de una hora."];
                }
            }
            else{
                return ['success'=>true,'results'=>false,
                'total'=>0,'filtros'=>'Sin filtros','data'=>[],
                'message'=>"No se encuentra ningún filtro aplicado al reporte."];
            }
            

        $data = $res->get();
        if(count($data)==0)   return response()->json(['success'=>false,'results'=>false,'message'=>'No se encontraron datos']);
             

        //Mapeamos el resultado como un array
       /*  $res = $data->map(function($x){
            $x = is_object($x)?(array)$x:$x;                
            return $x;
        })->toArray();  */
 
        $burbuja=[];
        foreach ($data as $key) {
      
            $temp=[
                "NumInvitado"=>$key->NumeroInvitado,
                "folio"=>$key->Folio,
                "codigoBarra"=>$key->CodigoBarras,
                "Responsable"=>$key->Responsable,
                "Municipio"=>$key->Municipio,
                "Nombre"=>$key->Nombres,
                "Paterno"=>$key->Paterno,
                "Materno"=>$key->Materno,
                "curp"=>$key->CURP,
                "cel"=>$key->Celular
            ];
            array_push($burbuja,$temp);
        
        } 

           // dd($butaca);
        //MODIFICAR JHOANA
        $reader = IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load(public_path() .'/archivos/ReporteListadeInvitados.xlsx');
        $sheet = $spreadsheet->getActiveSheet();


        $largo = count($burbuja);
        //colocar los bordes
        self::crearBordes($largo,'A',$sheet);
        self::crearBordes($largo,'B',$sheet);
        self::crearBordes($largo,'C',$sheet);
        self::crearBordes($largo,'D',$sheet);
        self::crearBordes($largo,'E',$sheet);
        self::crearBordes($largo,'F',$sheet);
        self::crearBordes($largo,'G',$sheet);
        self::crearBordes($largo,'H',$sheet);
        self::crearBordes($largo,'I',$sheet);
        self::crearBordes($largo,'J',$sheet);
        self::crearBordes($largo,'k',$sheet);

        //Llenar excel con el resultado del query
        $sheet->fromArray($burbuja,null,'A6');
        //Agregamos la fecha
        
        $sheet->setCellValue('X6', 'Fecha Reporte: '.date('Y-m-d'));


       /*  $sheet->getPageSetup()->setPrintArea('A1:W'.$impresion);
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_PORTRAIT);
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_LETTER); */


        //guardamos el excel creado y luego lo obtenemos en $file para poder descargarlo
        $writer = new Xlsx($spreadsheet);
        $writer->save('archivos/ReporteListadeInvitados'.date('Y-m-d').'.xlsx');
        $file = public_path() .'/archivos/ReporteListadeInvitados'.date('Y-m-d').'.xlsx';

        return response()->download($file,'ReporteListadeInvitados'.date('Y-m-d').'.xlsx');
        
    }

    public function getReporteInvitadosPorResponsable(Request $request){
        $parameters = $request->all();
        $res = DB::table('bravos_invitados')
            ->select(
                'bravos_invitados.Folio',
                DB::raw("IFNULL(bravos_invitados.CodigoBarras,'Sin código de barra') as CodigoBarras"),
                DB::raw('UPPER(bravos_invitados.Responsable) as Responsable'),
                'bravos_invitados.NumeroInvitado',
                'bravos_invitados.Municipio',
                DB::raw('UPPER(bravos_invitados.Nombres) as Nombres'),
                DB::raw('UPPER(bravos_invitados.Materno) as Materno'),
                DB::raw('UPPER(bravos_invitados.Paterno) as Paterno'),
                DB::raw('UPPER(CONCAT_WS(" ",bravos_invitados.Nombres,bravos_invitados.Paterno,bravos_invitados.Materno))as NombreCompleto'),
                DB::raw('UPPER(bravos_invitados.CURP) as CURP'),
                'bravos_invitados.Celular',
                'bravos_invitados.NumeroBurbuja',
                'bravos_invitados.created_at',
                'bravos_invitados.updated_at',
                'bravos_invitados.UserCreated',
                'bravos_invitados.UserOwned',
                'bravos_invitados.UserUpdated'

            )
            ->where('Responsable',$parameters['Responsable'])
            ->where('Municipio',$parameters['Municipio'])
            ->orderBy('Responsable')
            ->orderBy('Municipio');

            if(isset($parameters['Asignado'])){
                if($parameters['Asignado']==1){
                    $res->whereNotNull('CodigoBarras');
                }
                if($parameters['Asignado']==2){
                    $res->whereNull('CodigoBarras');
                }
            }

            

        $data = $res->get();
        //if(count($data)==0)   return response()->json(['success'=>false,'results'=>false,'message'=>'No se encontraron datos']);
             

        //Mapeamos el resultado como un array
       /*  $res = $data->map(function($x){
            $x = is_object($x)?(array)$x:$x;                
            return $x;
        })->toArray();  */
 
        $burbuja=[];
        $responsable="";
        foreach ($data as $numero =>$key) {
            $i=$numero+1;
            $responsable= $key->Responsable;
            $temp=[
                "Consecutivo"=>$i,
                "folio"=>$key->Folio,
                "codigoBarra"=>$key->CodigoBarras,
                "Municipio"=>$key->Municipio,
                "Nombre"=>$key->Nombres,
                "Paterno"=>$key->Paterno,
                "Materno"=>$key->Materno,
                "curp"=>$key->CURP,
                "cel"=>$key->Celular
            ];
            array_push($burbuja,$temp);
        
            } 

           // dd($butaca);
        //MODIFICAR JHOANA
        $reader = IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load(public_path() .'/archivos/ReporteListadeInvitados.xlsx');
        $sheet =$spreadsheet->getActiveSheet();

       /*  $sheet->getPageSetup()->setPrintArea('A1:W'.$impresion);
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_PORTRAIT);
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_LETTER); */

        $largo = count($burbuja);
        //colocar los bordes
        self::crearBordes($largo,'A',$sheet);
        self::crearBordes($largo,'B',$sheet);
        self::crearBordes($largo,'C',$sheet);
        self::crearBordes($largo,'D',$sheet);
        self::crearBordes($largo,'E',$sheet);
        self::crearBordes($largo,'F',$sheet);
        self::crearBordes($largo,'G',$sheet);
        self::crearBordes($largo,'H',$sheet);
        self::crearBordes($largo,'I',$sheet);
        self::crearBordes($largo,'J',$sheet);

        //Llenar excel con el resultado del query
        $sheet->fromArray($burbuja,null,'A6');
        //Agregamos la fecha
        $sheet->setCellValue('G2', 'Responsable: '.$responsable);
        $sheet->setCellValue('I3', 'Fecha Reporte: '.date('Y-m-d'));

    

        //guardamos el excel creado y luego lo obtenemos en $file para poder descargarlo
        $writer = new Xlsx($spreadsheet);
        $writer->save('archivos/ReporteListadeInvitados'.date('Y-m-d').'.xlsx');
        $file = public_path() .'/archivos/ReporteListadeInvitados'.date('Y-m-d').'.xlsx';

        return response()->download($file,'ReporteListadeInvitados'.date('Y-m-d').'.xlsx');
        
    }

    public function getCodigoBarras(Request $request){


        $data = DB::table('bravos_codigobarras as N')
        ->select('N.CodigoBarras','N.id')
        ->get();
        
        //$d->setStorPath(__DIR__.'/cache/');
        //echo $d->getBarcodePNGPath('9780691147727', 'EAN13');
       // dd($d);
        $templateProcessor = new TemplateProcessor(public_path() .'/archivos/CodigoBarra.docx');
        $d = new DNS1D();
        $d->setStorPath(base_path().'/barcode/');
        foreach ($data as $key => $value) {
           // 
           $templateProcessor->setValue('folio'.$key, $value->id);
            $templateProcessor->setValue('CodigoBarra'.$key, $value->CodigoBarras);
            $templateProcessor->setImageValue('Barcode'.$key, array( 'path'=> $d->getBarcodePNGPath($value->CodigoBarras, 'C128'), 'width' => 90, 'height' => 25, 'ratio' => false));
        }
        
        $templateProcessor->saveAs('CodigosdeBarra.docx');
        return response()->download('CodigosdeBarra.docx');
    } 


     public function getCodigoBarrasInvitados(Request $request){

        $parameters = $request->all();
        
        $res = DB::table('bravos_invitados')
            ->select(
                'bravos_invitados.Folio',
                DB::raw("IFNULL(bravos_invitados.CodigoBarras,'Sin código de barra') as CodigoBarras"),
                DB::raw('UPPER(CONCAT_WS(" ",bravos_invitados.Nombres,bravos_invitados.Paterno,bravos_invitados.Materno))as NombreCompleto')
            )
            ->where('Responsable',$parameters['Responsable'])
            ->where('Municipio',$parameters['Municipio'])
            ->whereNotNull('bravos_invitados.CodigoBarras');

            //dd($res->get());
        $data = $res->get();
        
        //$d->setStorPath(__DIR__.'/cache/');
        //echo $d->getBarcodePNGPath('9780691147727', 'EAN13');
       // dd($d);
       if(count($data)<9){
        $templateProcessor = new TemplateProcessor(public_path() .'/archivos/pases8.docx');
       }
       else if (count($data)<25 && count($data)>8){
        $templateProcessor = new TemplateProcessor(public_path() .'/archivos/pases25.docx');
       }
       else if (count($data)<41 && count($data)>25){
        $templateProcessor = new TemplateProcessor(public_path() .'/archivos/pases41.docx');
        }
        else if(count($data)<73 && count($data)>41){
            $templateProcessor = new TemplateProcessor(public_path() .'/archivos/pases72.docx');
        }
        else{
            $templateProcessor = new TemplateProcessor(public_path() .'/archivos/pases80.docx');
        }

        
        $d = new DNS1D();
        $d->setStorPath(base_path().'/barcode/');
        foreach ($data as $key => $value) {
           //
           //dd($value->CodigoBarras);
            $templateProcessor->setValue('Nombre'.$key, $value->NombreCompleto); 
            $templateProcessor->setValue('CodigoBarra'.$key, $value->CodigoBarras);
            $templateProcessor->setImageValue('Barcode'.$key, array( 'path'=> $d->getBarcodePNGPath(Str::lower($value->CodigoBarras), 'C128'), 'width' => 90, 'height' => 25, 'ratio' => false));
        }

        
        $templateProcessor->saveAs(public_path().'/CodigosdeBarraInvitaciones.docx');
        return response()->download(public_path().'/CodigosdeBarraInvitaciones.docx');
    } 


    public function getCodigoBarrasInvitadosUno(Request $request){

        $parameters = $request->all();
        $NombreCompleto = $parameters['Nombre'];
        $NombreCompleto = str_replace(" ","%",$NombreCompleto);
        //dd($NombreCompleto);
        $res = DB::table('bravos_invitados')
            ->select(
                'bravos_invitados.Folio',
                DB::raw("IFNULL(bravos_invitados.CodigoBarras,'Sin código de barra') as CodigoBarras"),
                DB::raw('UPPER(CONCAT_WS(" ",bravos_invitados.Nombres,bravos_invitados.Paterno,bravos_invitados.Materno))as NombresCompleto')
            )
           /*  ->where('Responsable',$parameters['Responsable'])
            ->where('Municipio',$parameters['Municipio']) */
            
           // ->where(DB::raw("CONCAT_WS(' ',bravos_invitados.Nombres,bravos_invitados.Paterno,bravos_invitados.Materno)"),'like',"%".$NombreCompleto."%")
            ->where("bravos_invitados.CodigoBarras",$parameters['Codigo'])
            ->whereNotNull('bravos_invitados.CodigoBarras');

            //dd($res->first());
        $data = $res->first();
        
        //dd($data);
        //$d->setStorPath(__DIR__.'/cache/');
        //echo $d->getBarcodePNGPath('9780691147727', 'EAN13');
       // dd($d);
       $templateProcessor = new TemplateProcessor(public_path() .'/archivos/pases8.docx');
      /*  if(count($data)<9){
        $templateProcessor = new TemplateProcessor(public_path() .'/archivos/pases8.docx');
       }
       else if (count($data)<25 && count($data)>8){
        $templateProcessor = new TemplateProcessor(public_path() .'/archivos/pases25.docx');
       }
       else if (count($data)<41 && count($data)>25){
        $templateProcessor = new TemplateProcessor(public_path() .'/archivos/pases41.docx');
        }
        else if(count($data)<73 && count($data)>41){
            $templateProcessor = new TemplateProcessor(public_path() .'/archivos/pases72.docx');
        }
        else{
            $templateProcessor = new TemplateProcessor(public_path() .'/archivos/pases80.docx');
        } */

        
        $d = new DNS1D();
        $d->setStorPath(base_path().'/barcode/');
       // foreach ($data as $key => $value) {
           //
           //dd($value->CodigoBarras);
            $templateProcessor->setValue('Nombre0', $data->NombresCompleto); 
            $templateProcessor->setValue('CodigoBarra0', $data->CodigoBarras);
            $templateProcessor->setImageValue('Barcode0', array( 'path'=> $d->getBarcodePNGPath(Str::lower($data->CodigoBarras), 'C128'), 'width' => 90, 'height' => 25, 'ratio' => false));
        //}

        
        $templateProcessor->saveAs(public_path().'/CodigosdeBarraInvitaciones.docx');
        return response()->download(public_path().'/CodigosdeBarraInvitaciones.docx');
    } 
}
