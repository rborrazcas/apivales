<?php
namespace App\Http\Controllers;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpWord\TemplateProcessor;


use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;

class ReportesPostController extends Controller
{
    public function getReporteGrupos(Request $request){
        // ,'d.FechaNacimientoC','d.SexoC as Sexo'

        $res = DB::table('et_tarjetas_asignadas as a')
        ->select(
            DB::raw("concat_ws(' ', lpad(HEX(a.idGrupo),3,'0'),c.Nombre) as Grupo"),
            'd.FolioC','f.Nombre as Municipio','e.Nombre as Localidad','d.NombreC as Nombre','d.PaternoC as Paterno','d.MaternoC as Materno',
            'd.ColoniaC as Colonia','d.CalleC as Calle',
            'd.NumeroC as Numero','d.CodigoPostalC as CP','a.Terminacion'
        )
        ->join('et_grupo as b','a.idGrupo','=','b.id')
        ->join('et_cat_municipio as c','b.idMunicipio','=','c.id')
        ->join('et_aprobadoscomite as d','a.id','=','d.id')
        ->join('et_cat_localidad as e','e.Id','=','d.idLocalidadC')
        ->join('et_cat_municipio as f','f.id','=','d.idMunicipioC');

        if(isset($request->idGrupo)){
            $res->where('a.idGrupo',$request->idGrupo);
        }

        $data = $res->orderBy('a.idMunicipio','asc')->orderBy('a.idGrupo','asc')->orderBy('NombreC','asc')->orderBy('PaternoC','asc')->orderBy('MaternoC','asc')->get();

        //Mapeamos el resultado como un array
        $res = $data->map(function($x){
            $x = is_object($x)?(array)$x:$x;                
            return $x;
        })->toArray();

        //------------------------------------------------- Para generar el archivo excel ----------------------------------------------------------------
        // $spreadsheet = new Spreadsheet();
        // $sheet = $spreadsheet->getActiveSheet();
        
        //Para los titulos del excel
        // $titulos = ['Grupo','Folio','Nombre','Paterno','Materno','Fecha de Nacimiento','Sexo','Calle','Numero','Colonia','Municipio','Localidad','CP','Terminación'];
        // $sheet->fromArray($titulos,null,'A1');
        // $sheet->getStyle('A1:N1')->getFont()->getColor()->applyFromArray(['rgb' => '808080']);

        $reader = IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load(public_path() .'/archivos/formato.xlsx');
        $sheet = $spreadsheet->getActiveSheet();

        $largo = count($res);
        //colocar los bordes
        self::crearBordes($largo,'B',$sheet);
        self::crearBordes($largo,'C',$sheet);
        self::crearBordes($largo,'D',$sheet);
        self::crearBordes($largo,'E',$sheet);
        self::crearBordes($largo,'F',$sheet);
        self::crearBordes($largo,'G',$sheet);
        self::crearBordes($largo,'H',$sheet);
        self::crearBordes($largo,'I',$sheet);
        self::crearBordes($largo,'J',$sheet);
        self::crearBordes($largo,'K',$sheet);
        self::crearBordes($largo,'L',$sheet);
        self::crearBordes($largo,'M',$sheet);
        self::crearBordes($largo,'N',$sheet);
        self::crearBordes($largo,'O',$sheet);

        //Llenar excel con el resultado del query
        $sheet->fromArray($res,null,'C11');
        //Agregamos la fecha
        $sheet->setCellValue('O6', 'FECHA: '.date('Y-m-d'));
        
        //Agregar el indice autonumerico

        for($i=1;$i<=$largo;$i++){
            $inicio = 10+$i; 
            $sheet->setCellValue('B'.$inicio, $i);
        }

        //----------------------------------------Para colocar la firma-------------------------------------
        //combinar celdas
        $largo2 = $largo+12;
        $largo3 = $largo+15;

        $combinar1 = 'C'.$largo2.':E'.$largo2;
        $combinar2 = 'C'.$largo3.':E'.$largo3;

        $sheet->mergeCells($combinar1);
        $sheet->mergeCells($combinar2);

        //Colocar textos...
        $sheet->getStyle('C'.$largo2)->getFont()->setBold(true);
        $sheet->getStyle('C'.$largo2)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->setCellValue('C'.$largo2, 'REPRESENTANTE DE LA SEDESHU');

        $sheet->getStyle('C'.$largo3)->getFont()->setBold(true);
        $sheet->getStyle('C'.$largo3)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->setCellValue('C'.$largo3, 'NOMBRE Y FIRMA');
        $sheet->getStyle($combinar2)->getBorders()->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);


        //guardamos el excel creado y luego lo obtenemos en $file para poder descargarlo
        $writer = new Xlsx($spreadsheet);
        $writer->save('archivos/reporteTrabajoTemporal.xlsx');
        $file = public_path() .'/archivos/reporteTrabajoTemporal.xlsx';

        return response()->download($file,'reporteTrabajoTemporal.xlsx');
    }   

    //funcion para generar bordes en el excel.
    public static function crearBordes($largo,$columna,&$sheet){

        for($i=0;$i<$largo;$i++){
            $inicio = 11 + $i;

            $sheet->getStyle($columna.$inicio)->getBorders()->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
            $sheet->getStyle($columna.$inicio)->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
            $sheet->getStyle($columna.$inicio)->getBorders()->getLeft()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
            $sheet->getStyle($columna.$inicio)->getBorders()->getRight()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        }
    }

    public function getTarjeta(Request $request){

        if(!isset($request->curp)){
            return response()->json(['success'=>false,'results'=>false,'message'=>'Hace falta la CURP']);
        }

        $data = DB::table('et_tarjetas_asignadas as a')
        ->select(
            DB::raw("concat_ws(' ', lpad(HEX(a.idGrupo),3,'0'),c.Nombre) as Grupo"),
            'd.FolioC','f.Nombre as Municipio','e.Nombre as Localidad','d.NombreC as Nombre','d.PaternoC as Paterno','d.MaternoC as Materno',
            'd.ColoniaC as Colonia','d.CalleC as Calle',
            'd.NumeroC as Numero','d.CodigoPostalC as CP','a.Terminacion'
        )
        ->join('et_grupo as b','a.idGrupo','=','b.id')
        ->join('et_cat_municipio as c','b.idMunicipio','=','c.id')
        ->join('et_aprobadoscomite as d','a.id','=','d.id')
        ->join('et_cat_localidad as e','e.Id','=','d.idLocalidadC')
        ->join('et_cat_municipio as f','f.id','=','d.idMunicipioC')
        ->where('a.CURP',$request->curp)
        ->orderBy('NombreC','asc')->orderBy('PaternoC','asc')->orderBy('MaternoC','asc')
        ->first();
        
        if(is_null($data)){
            return response()->json(['success'=>true,'results'=>false,'message'=>'No existe el registro']);

        }
        $templateProcessor = new TemplateProcessor(public_path() .'/archivos/formatoWord.docx');
        $templateProcessor->setValue('folio', $data->FolioC);
        $templateProcessor->setValue('terminacion', $data->Terminacion);
        $templateProcessor->setValue('nombre', $data->Nombre.' '.$data->Paterno.' '.$data->Materno);
        $templateProcessor->setValue('mun', $data->Municipio);
        $templateProcessor->setValue('localidad', $data->Localidad);
        $templateProcessor->saveAs('tarjeta'.$request->curp.'.docx');
        return response()->download('tarjeta'.$request->curp.'.docx');
    }

    public function getSumaVoluntadesWord(Request $request){


        if(!isset($request->id)){
            return response()->json(['success'=>false,'results'=>false,'message'=>'Hace falta la CURP']);
        }

        $data = DB::table('v_negocios as N')
        ->select('N.id',
            DB::raw("LPAD(HEX(N.id),6,'0') ClaveUnica"), 
            DB::raw("md5(LPAD(HEX(N.id),6,'0')) SecClaveUnica"), 
            'N.NombreEmpresa', 
            DB::raw("concat_WS(' ',N.Nombre, N.Paterno, N.Materno) AS Contacto"),
            'M.Nombre AS Municipio',
            'N.Banco',
            'N.CLABE',
            'N.NumTarjeta',
            'N.QuiereTransferencia',
            'N.FechaInscripcion', DB::raw("date_format(FechaInscripcion,'%d') DD"),
            DB::raw("date_format(FechaInscripcion,'%d') DD"),
            DB::raw("date_format(FechaInscripcion,'%m') MM")
        )
        ->join('et_cat_municipio as M','N.idMunicipio','=','M.Id')
        ->where('N.id',$request->id)
        ->first();
        
        if(is_null($data)){
            return response()->json(['success'=>true,'results'=>false,'message'=>'No existe el registro']);
        }

        $Facultado = DB::table('v_negocios_pagadores as P')
            ->select(
                'P.id', 
                'P.idNegocio', 
                'P.CURP', DB::raw("concat_WS(' ',P.Nombre, P.Paterno, P.Materno) as Facultado"),
                'P.idStatus'
            )
            ->where('P.idNegocio','=',$data->id)->first();

        $InfoMes["01"]="Enero";
        $InfoMes["02"]="Febrero";
        $InfoMes["03"]="Marzo";
        $InfoMes["04"]="Abril";
        $InfoMes["05"]="Mayo";
        $InfoMes["06"]="Junio";
        $InfoMes["07"]="Julio";
        $InfoMes["08"]="Agosto";
        $InfoMes["09"]="Septiembre";
        $InfoMes["10"]="Octubre";
        $InfoMes["11"]="Noviembre";
        $InfoMes["12"]="Diciembre";

        $templateProcessor = new TemplateProcessor(public_path() .'/archivos/Suma_Voluntades_Comercio_ANEXO_2.docx');
        $templateProcessor->setValue('Contacto', $data->Contacto);
        $templateProcessor->setValue('Comercio', $data->NombreEmpresa);
        $templateProcessor->setValue('Municipio', $data->Municipio);
        $templateProcessor->setValue('CLABE', $data->CLABE);
        $templateProcessor->setValue('NumTarjeta', $data->NumTarjeta);
        $templateProcessor->setValue('Banco', $data->Banco);
        if(!(is_null($Facultado))) $templateProcessor->setValue('Facultado', $Facultado->Facultado);
        else $templateProcessor->setValue('Facultado', '');
        $templateProcessor->setValue('Dia', $data->DD);
        $templateProcessor->setValue('Mes', $InfoMes[$data->MM]);
        $templateProcessor->saveAs('AcuerdoVoluntades_'.$data->NombreEmpresa.'.docx');
        return response()->download('AcuerdoVoluntades_'.$data->NombreEmpresa.'.docx');
    }



    public function getTarjetas(Request $request){

        if(!isset($request->idGrupo)){
            return response()->json(['success'=>false,'results'=>false,'message'=>'Hace falta el id del grupo']);
        }

        $data = DB::table('et_tarjetas_asignadas as a')
        ->select(
            DB::raw("concat_ws(' ', lpad(HEX(a.idGrupo),3,'0'),c.Nombre) as Grupo"),
            'd.FolioC','f.Nombre as Municipio','e.Nombre as Localidad','d.NombreC as Nombre','d.PaternoC as Paterno','d.MaternoC as Materno',
            'd.ColoniaC as Colonia','d.CalleC as Calle',
            'd.NumeroC as Numero','d.CodigoPostalC as CP','a.Terminacion','a.CURP'
        )
        ->join('et_grupo as b','a.idGrupo','=','b.id')
        ->join('et_cat_municipio as c','b.idMunicipio','=','c.id')
        ->join('et_aprobadoscomite as d','a.id','=','d.id')
        ->join('et_cat_localidad as e','e.Id','=','d.idLocalidadC')
        ->join('et_cat_municipio as f','f.id','=','d.idMunicipioC')
        ->where('a.idGrupo',$request->idGrupo)
        ->orderBy('NombreC','asc')->orderBy('PaternoC','asc')->orderBy('MaternoC','asc')
        ->get();
        
        if(is_null($data)){
            return response()->json(['success'=>true,'results'=>false,'message'=>'No existe el registro']);

        }

        $templateProcessor = new TemplateProcessor(public_path() .'/archivos/formatoWord.docx');
        $templateProcessor->cloneBlock('CLONEME', 3);

        for($i=0;$i<count($data);$i++){
            $templateProcessor->setValue('folio', $data[$i]->FolioC);
            $templateProcessor->setValue('terminacion', $data[$i]->Terminacion);
            $templateProcessor->setValue('nombre', $data[$i]->Nombre.' '.$data[$i]->Paterno.' '.$data[$i]->Materno);
            $templateProcessor->setValue('mun', $data[$i]->Municipio);
            $templateProcessor->setValue('localidad', $data[$i]->Localidad);
        }
        $templateProcessor->saveAs('tarjeta.docx');

        // return response()->download('tarjeta'.$request->curp.'.docx');
    }
}
