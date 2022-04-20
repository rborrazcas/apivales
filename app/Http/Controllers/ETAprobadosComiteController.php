<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use \Illuminate\Database\QueryException;
use DB;
use Illuminate\Contracts\Validation\ValidationException;
use Validator;
use App\ETAprobadosComite;

class ETAprobadosComiteController extends Controller
{
    
    function getAprobados(Request $request){
        $parameters = $request->all();

        try {
            
            
            $res = DB::table('et_aprobadoscomite')
            ->select(
                'et_aprobadoscomite.id',
                'et_aprobadoscomite.CURP',
                'et_aprobadoscomite.NombreC',
                'et_aprobadoscomite.PaternoC',
                'et_aprobadoscomite.MaternoC',
                'et_aprobadoscomite.FechaNacimientoC',
                'et_aprobadoscomite.SexoC',
                'et_aprobadoscomite.EntidadNacimientoC',
                'et_aprobadoscomite.CalleC',
                'et_aprobadoscomite.NumeroC',
                'et_aprobadoscomite.NumeroInteriorC',
                'et_aprobadoscomite.ColoniaC',
                'et_aprobadoscomite.idMunicipioC',
                    'et_cat_municipio.Nombre AS MunicipioC',
                'et_aprobadoscomite.idLocalidadC',
                    'et_cat_localidad.Numero AS NumeroLocalidad',
                    'et_cat_localidad.Nombre AS Localidad',
                'et_aprobadoscomite.CodigoPostalC',
                'et_aprobadoscomite.FolioC',
                'et_aprobadoscomite.EstatusExpediente'
            )
            ->leftJoin('et_cat_municipio','et_cat_municipio.Id','=','et_aprobadoscomite.idMunicipioC')
            ->leftJoin('et_cat_localidad','et_cat_localidad.Id','=','et_aprobadoscomite.idLocalidadC')
            ->whereNotNull('LastUpdateC');
            
            

            if(isset($parameters['all'])){
                if($parameters['all']==0){
                    $et_asignadas = DB::table('et_tarjetas_asignadas')
                    ->select('id')->get();
                    $array_asignadas = [];
                    foreach ($et_asignadas as $data) {
                        array_push($array_asignadas,$data->id);
                    }
                    $res->whereNotIn("et_aprobadoscomite.id",$array_asignadas);
                }
            }
            $flag = 0;
            if(isset($parameters['filtered'])){

                for($i=0;$i<count($parameters['filtered']);$i++){

                    if($flag==0){
                        if($parameters['filtered'][$i]['id'] &&  strpos($parameters['filtered'][$i]['id'], 'id') !== false){
                            if(is_array ($parameters['filtered'][$i]['value'])){
                                $res->whereIn($parameters['filtered'][$i]['id'],$parameters['filtered'][$i]['value']);
                            }else{
                                $res->where($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
                            }
                            
                        }else{
                            $res->where($parameters['filtered'][$i]['id'],'LIKE','%'.$parameters['filtered'][$i]['value'].'%');
                        }
                        $flag = 1;
                    }
                    else{
                        if($parameters['tipo']=='and'){
                            if($parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'id') !== false){
                                if(is_array($parameters['filtered'][$i]['value'])){
                                    $res->whereIn($parameters['filtered'][$i]['id'],$parameters['filtered'][$i]['value']);
                                }else{
                                    $res->where($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
                                }
                            }else{
                                $res->where($parameters['filtered'][$i]['id'],'LIKE','%'.$parameters['filtered'][$i]['value'].'%');
                            } 
                        }
                        else{
                            if($parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'id') !== false){
                                if(is_array ($parameters['filtered'][$i]['value'])){
                                    $res->orWhereIn($parameters['filtered'][$i]['id'],$parameters['filtered'][$i]['value']);
                                }else{
                                    $res->orWhere($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
                                }
                            }else{
                                $res->orWhere($parameters['filtered'][$i]['id'],'LIKE','%'.$parameters['filtered'][$i]['value'].'%');
                            }
                        }
                    }
                }
            }

            if(isset($parameters['NombreCompleto'])){
                $persona_recibida = $parameters['NombreCompleto'];
                $persona_recibida = str_replace(" ","",$persona_recibida);
                
                $res->where(
                        DB::raw("
                        REPLACE(
                        CONCAT(
                            et_aprobadoscomite.NombreC,
                            et_aprobadoscomite.PaternoC,
                            et_aprobadoscomite.MaternoC,

                            et_aprobadoscomite.PaternoC,
                            et_aprobadoscomite.NombreC,
                            et_aprobadoscomite.MaternoC,

                            et_aprobadoscomite.MaternoC,
                            et_aprobadoscomite.NombreC,
                            et_aprobadoscomite.PaternoC,

                            et_aprobadoscomite.NombreC,
                            et_aprobadoscomite.MaternoC,
                            et_aprobadoscomite.PaternoC,

                            et_aprobadoscomite.PaternoC,
                            et_aprobadoscomite.MaternoC,
                            et_aprobadoscomite.NombreC,

                            et_aprobadoscomite.MaternoC,
                            et_aprobadoscomite.PaternoC,
                            et_aprobadoscomite.NombreC
                            
                        ), ' ', '')")
                
                ,'like',"%".$persona_recibida."%");
                
                
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
            $total = $res->count(); 
            $res = $res->offset($startIndex)
            ->take($pageSize)
            ->get();


            
            return ['success'=>true,'results'=>true,'total'=>$total,'filtros'=>$parameters['filtered'],'data'=>$res];

        } catch(QueryException $e){
            return [
                'success' => false,
                'errors' => $e->getMessage()
            ];
        }

    }

    function getRechazados(Request $request){
        $parameters = $request->all();

        try {
            
            
            $res = DB::table('et_aprobadoscomite')
            ->select(
                'et_aprobadoscomite.id',
                'et_aprobadoscomite.CURP',
                'et_aprobadoscomite.NombreC',
                'et_aprobadoscomite.PaternoC',
                'et_aprobadoscomite.MaternoC',
                'et_aprobadoscomite.FechaNacimientoC',
                'et_aprobadoscomite.SexoC',
                'et_aprobadoscomite.EntidadNacimientoC',
                'et_aprobadoscomite.CalleC',
                'et_aprobadoscomite.NumeroC',
                'et_aprobadoscomite.NumeroInteriorC',
                'et_aprobadoscomite.ColoniaC',
                'et_aprobadoscomite.idMunicipioC',
                    'et_cat_municipio.Nombre AS MunicipioC',
                'et_aprobadoscomite.idLocalidadC',
                    'et_cat_localidad.Numero AS NumeroLocalidad',
                    'et_cat_localidad.Nombre AS Localidad',
                'et_aprobadoscomite.CodigoPostalC',
                'et_aprobadoscomite.FolioC',
                'et_aprobadoscomite.EstatusExpediente'
            )
            ->leftJoin('et_cat_municipio','et_cat_municipio.Id','=','et_aprobadoscomite.idMunicipioC')
            ->leftJoin('et_cat_localidad','et_cat_localidad.Id','=','et_aprobadoscomite.idLocalidadC')
            ->whereNotNull('LastUpdateC')
            ->where('EstatusExpediente','!=','COMPLETO');
            

            if(isset($parameters['all'])){
                if($parameters['all']==0){
                    $et_asignadas = DB::table('et_tarjetas_asignadas')
                    ->select('id')->get();
                    $array_asignadas = [];
                    foreach ($et_asignadas as $data) {
                        array_push($array_asignadas,$data->id);
                    }
                    $res->whereNotIn("et_aprobadoscomite.id",$array_asignadas);
                }
            }

            if(isset($parameters['NombreCompleto'])){
                $res->Where(DB::raw("CONCAT(et_aprobadoscomite.NombreC, ' ', et_aprobadoscomite.PaternoC, ' ', et_aprobadoscomite.MaternoC, ' ',et_aprobadoscomite.NombreC, et_aprobadoscomite.PaternoC, ' ', et_aprobadoscomite.NombreC, ' ', et_aprobadoscomite.MaternoC)"), 'LIKE', "%".$parameters['NombreCompleto']."%");

            }

            $flag = 0;
            if(isset($parameters['filtered'])){

                for($i=0;$i<count($parameters['filtered']);$i++){

                    if($flag==0){
                        if($parameters['filtered'][$i]['id'] &&  strpos($parameters['filtered'][$i]['id'], 'id') !== false){
                            if(is_array ($parameters['filtered'][$i]['value'])){
                                $res->whereIn($parameters['filtered'][$i]['id'],$parameters['filtered'][$i]['value']);
                            }else{
                                $res->where($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
                            }
                            
                        }else{
                            $res->where($parameters['filtered'][$i]['id'],'LIKE','%'.$parameters['filtered'][$i]['value'].'%');
                        }
                        $flag = 1;
                    }
                    else{
                        if($parameters['tipo']=='and'){
                            if($parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'id') !== false){
                                if(is_array($parameters['filtered'][$i]['value'])){
                                    $res->whereIn($parameters['filtered'][$i]['id'],$parameters['filtered'][$i]['value']);
                                }else{
                                    $res->where($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
                                }
                            }else{
                                $res->where($parameters['filtered'][$i]['id'],'LIKE','%'.$parameters['filtered'][$i]['value'].'%');
                            } 
                        }
                        else{
                            if($parameters['filtered'][$i]['id'] && strpos($parameters['filtered'][$i]['id'], 'id') !== false){
                                if(is_array ($parameters['filtered'][$i]['value'])){
                                    $res->orWhereIn($parameters['filtered'][$i]['id'],$parameters['filtered'][$i]['value']);
                                }else{
                                    $res->orWhere($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
                                }
                            }else{
                                $res->orWhere($parameters['filtered'][$i]['id'],'LIKE','%'.$parameters['filtered'][$i]['value'].'%');
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
            $total = $res->count(); 
            $res = $res->offset($startIndex)
            ->take($pageSize)
            ->get();


            
            return ['success'=>true,'results'=>true,'total'=>$total,'filtros'=>$parameters['filtered'],'data'=>$res];

        } catch(QueryException $e){
            return [
                'success' => false,
                'errors' => $e->getMessage()
            ];
        }

    }
}
