$parameters_serializado = serialize($parameters);
                //$array = unserialize($parameters_serializado);
                $user = auth()->user();
                $filtro_usuario=VNegociosFiltros::where('idUser','=',$user->id)->where('api','=','getNegociosApp')->first();
                if($filtro_usuario){
                    $filtro_usuario->parameters=$parameters_serializado;
                    $filtro_usuario->save();
                }
                else{
                    $objeto_nuevo = new VNegociosFiltros;
                    $objeto_nuevo->api="getNegociosApp";
                    $objeto_nuevo->idUser=$user->id;
                    $objeto_nuevo->parameters=$parameters_serializado;
                    $objeto_nuevo->save();
                }