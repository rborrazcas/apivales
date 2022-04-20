<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use \Illuminate\Database\QueryException;
use DB;
use Illuminate\Contracts\Validation\ValidationException;
use Validator;

class ETCatMunicipioController extends Controller
{
    function getMunicipiosET(Request $request){
        $parameters = $request->all();

        try {
            $res = DB::table('et_cat_municipio')
            ->select(
                'Id', 'Nombre', 'Region', 'SubRegion'
            );

            if(isset($parameters['Regiones'])){
                if(is_array ($parameters['Regiones'])){
                    $res->whereIn('et_cat_municipio.SubRegion',$parameters['Regiones']);
                }else{
                    $res->where('et_cat_municipio.SubRegion','=',$parameters['Regiones']);
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
    function getMunicipiosETVales(Request $request){
        $parameters = $request->all();

        try {
                $res_Vales = DB::table('vales')
                ->select('vales.idMunicipio as idMunicipioVales');

                if(isset($parameters['filtered']) && !is_null($parameters['filtered']))
                {
                    for($i=0;$i<count($parameters['filtered']);$i++){
                       if($parameters['filtered'][$i]['id']=='userID')
                       {
                            $res_Vales->where('vales.UserCreated','=',$parameters['filtered'][$i]['value'])
                            ->orWhere('vales.UserOwned',$parameters['filtered'][$i]['value']);
                        }
                    }
                }
            
            $res_Vales=$res_Vales->groupBy('vales.idMunicipio');
            
            $res_Vales=$res_Vales->get();

            $arrayMPios = [];
            foreach ($res_Vales as $data) {
                array_push($arrayMPios,$data->idMunicipioVales);
            }

            $res = DB::table('et_cat_municipio')
            ->select(
                'Id', 'Nombre', 'Region', 'SubRegion'
            );

            if(isset($parameters['Regiones']) && !is_null($parameters['Regiones'])){
                    $res->whereIn('et_cat_municipio.SubRegion',$parameters['Regiones'])
                    ->orWhereIn('Id',$arrayMPios);
            }
            else
            {
                $res->whereIn('Id',$arrayMPios);
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
            //->toSql();
            //dd($res);
            
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
