<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use DB;
use JWTAuth;
use Illuminate\Contracts\Validation\ValidationException;
use Validator;
use App\VVales;
use App\VNegociosFiltros;
use Arr;
use Carbon\Carbon as time;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class VValesController extends Controller
{
    function getSearchFolio(Request $request)
    {
        $parameters = $request->all();

        try {
            $res = DB::table('vales_solicitudes as VS')
                ->select(
                    'VS.id',
                    'VS.Ejercicio',
                    'VS.idSolicitud',
                    'VS.CURP',
                    'VS.Nombre',
                    'VS.Paterno',
                    'VS.Materno',
                    'VS.CodigoBarrasInicial',
                    'VS.CodigoBarrasFinal',
                    'VS.SerieInicial',
                    'VS.SerieFinal',
                    'VS.Articulador',
                    'VS.Municipio',
                    'VS.Remesa',
                    'VS.created_at',
                    'VS.UserCreated',
                    'VS.updated_at',
                    DB::raw(
                        "concat_ws(' ',U.Nombre, U.Paterno, U.Materno) AS Capturo"
                    )
                )
                ->leftJoin('users as U', 'U.id', '=', 'VS.UserCreated')
                ->where('VS.Ejercicio', '=', date('Y'));
            $flag = 0;

            if (isset($parameters['Folio'])) {
                $valor_id = $parameters['Folio'];
                $res->where('VS.SerieInicial', '=', $parameters['Folio']);
            }

            $total = $res->count();
            $res = $res->first();
            //dd($res);

            return [
                'success' => true,
                'results' => true,
                'total' => $total,
                'data' => $res,
            ];
        } catch (QueryException $e) {
            $errors = [
                'Clave' => '01',
            ];
            $response = [
                'success' => true,
                'results' => false,
                'total' => 0,
                'filtros' => $parameters['filtered'],
                'errors' => $e->getMessage(),
                'message' => 'Campo de consulta incorrecto',
            ];

            return response()->json($response, 200);
        }
    }

    function getVales(Request $request)
    {
        $parameters = $request->all();

        try {
            $res = DB::table('vales')
                ->select(
                    'vales.id',
                    DB::raw('LPAD(HEX(vales.id),6,0) as ClaveUnica'),
                    'vales.TelRecados',
                    'vales.Compania',
                    'vales.TelFijo',
                    'vales.FolioSolicitud',
                    'vales.FechaSolicitud',
                    'vales.CURP',
                    'vales.Nombre',
                    'vales.Paterno',
                    'vales.Materno',
                    'vales.Sexo',
                    'vales.FechaNacimiento',
                    'vales.Calle',
                    'vales.NumExt',
                    'vales.NumInt',
                    'vales.IngresoPercibido',
                    'vales.OtrosIngresos',
                    'vales.NumeroPersonas',
                    'vales.OcupacionOtro',
                    'vales.Ocupacion',
                    'vales.Bloqueado',
                    'vales.Colonia',
                    'vales.CP',
                    'vales.idMunicipio',
                    'et_cat_municipio.Id AS IdM',
                    'et_cat_municipio.Nombre AS Municipio',
                    'et_cat_municipio.SubRegion AS Region',
                    'vales.idLocalidad',
                    'et_cat_localidad.Id AS Clave',
                    'et_cat_localidad.Nombre AS Localidad',
                    'vales.TelFijo',
                    'vales.TelCelular',
                    'vales.isEntregado',
                    'vales.entrega_at',
                    'vales.CorreoElectronico',
                    'vales.FechaDocumentacion',
                    'vales.isDocumentacionEntrega',
                    'vales.idUserDocumentacion',
                    'vales.isEntregadoOwner',
                    'vales.idUserReportaEntrega',
                    'vales.ComentarioEntrega',
                    'vales.FechaReportaEntrega',
                    'vales.idStatus',
                    'vales_status.id as idES',
                    'vales_status.Estatus',
                    'vales_status.Clave as ClaveA',
                    'vales.created_at',
                    'vales.updated_at',
                    'vales.UserCreated',
                    //Datos Usuario created
                    'users.id as idE',
                    'users.email as emailE',
                    'users.Nombre as NombreE',
                    'users.Paterno as PaternoE',
                    'users.Materno as MaternoE',
                    'users.idTipoUser',
                    'cat_usertipo.id as idEA',
                    'cat_usertipo.TipoUser as TipoUserEA',
                    'cat_usertipo.Clave as ClaveEA',
                    'vales.UserUpdated',
                    //Datos Usuario updated
                    'usersB.id as idF',
                    'usersB.email as emailF',
                    'usersB.Nombre as NombreF',
                    'usersB.Paterno as PaternoF',
                    'usersB.Materno as MaternoF',
                    'usersB.idTipoUser',
                    'cat_usertipoB.id as idFA',
                    'cat_usertipoB.TipoUser as TipoUserFA',
                    'cat_usertipoB.Clave as ClaveFA',
                    'vales.UserOwned',
                    //Datos Usuario owned
                    'usersC.id as idO',
                    'usersC.email as emailO',
                    'usersC.Nombre as NombreO',
                    'usersC.Paterno as PaternoO',
                    'usersC.Materno as MaternoO',
                    'usersC.idTipoUser',
                    'cat_usertipoC.id as idGO',
                    'cat_usertipoC.TipoUser as TipoUserGO',
                    'cat_usertipoC.Clave as ClaveGO'
                )
                ->leftJoin(
                    'et_cat_municipio',
                    'et_cat_municipio.Id',
                    '=',
                    'vales.idMunicipio'
                )
                ->leftJoin(
                    'et_cat_localidad',
                    'et_cat_localidad.Id',
                    '=',
                    'vales.idLocalidad'
                )
                ->leftJoin('vales_status', 'vales_status.id', '=', 'idStatus')
                ->leftJoin('users', 'users.id', '=', 'vales.UserCreated')
                ->leftJoin(
                    'cat_usertipo',
                    'cat_usertipo.id',
                    '=',
                    'users.idTipoUser'
                )
                ->leftJoin(
                    'users as usersB',
                    'usersB.id',
                    '=',
                    'vales.UserUpdated'
                )
                ->leftJoin(
                    'cat_usertipo as cat_usertipoB',
                    'cat_usertipoB.id',
                    '=',
                    'usersB.idTipoUser'
                )
                ->leftJoin(
                    'users as usersC',
                    'usersC.id',
                    '=',
                    'vales.UserOwned'
                )
                ->leftJoin(
                    'users as usersCretaed',
                    'usersCretaed.id',
                    '=',
                    'vales.UserCreated'
                )
                ->leftJoin(
                    'cat_usertipo as cat_usertipoC',
                    'cat_usertipoC.id',
                    '=',
                    'usersC.idTipoUser'
                );
            $flag = 0;

            if (isset($parameters['Propietario'])) {
                $valor_id = $parameters['Propietario'];
                $res->where(function ($q) use ($valor_id) {
                    $q
                        ->where('vales.UserCreated', $valor_id)
                        ->orWhere('vales.UserOwned', $valor_id);
                });
            }
            if (isset($parameters['Folio'])) {
                $valor_id = $parameters['Folio'];
                $res->where(
                    DB::raw('LPAD(HEX(vales.id),6,0)'),
                    'like',
                    '%' . $valor_id . '%'
                );
            }

            if (isset($parameters['Regiones'])) {
                $resMunicipio = DB::table('et_cat_municipio')
                    ->whereIn('SubRegion', $parameters['Regiones'])
                    ->pluck('Id');

                //dd($resMunicipio);

                $res->whereIn('vales.idMunicipio', $resMunicipio);
            }

            if (isset($parameters['Ejercicio'])) {
                $valor_id = $parameters['Ejercicio'];
                $res->where(
                    DB::raw('YEAR(vales.FechaSolicitud)'),
                    '=',
                    $valor_id
                );
            }

            if (isset($parameters['filtered'])) {
                for ($i = 0; $i < count($parameters['filtered']); $i++) {
                    if ($flag == 0) {
                        if (
                            $parameters['filtered'][$i]['id'] &&
                            strpos($parameters['filtered'][$i]['id'], 'id') !==
                                false
                        ) {
                            if (
                                is_array($parameters['filtered'][$i]['value'])
                            ) {
                                $res->whereIn(
                                    $parameters['filtered'][$i]['id'],
                                    $parameters['filtered'][$i]['value']
                                );
                            } else {
                                $res->where(
                                    $parameters['filtered'][$i]['id'],
                                    '=',
                                    $parameters['filtered'][$i]['value']
                                );
                            }
                        } else {
                            if (
                                strcmp(
                                    $parameters['filtered'][$i]['id'],
                                    'vales.UserCreated'
                                ) === 0 ||
                                strcmp(
                                    $parameters['filtered'][$i]['id'],
                                    'vales.UserUpdated'
                                ) === 0 ||
                                strcmp(
                                    $parameters['filtered'][$i]['id'],
                                    'vales.UserOwned'
                                ) === 0
                            ) {
                                $res->where(
                                    $parameters['filtered'][$i]['id'],
                                    '=',
                                    $parameters['filtered'][$i]['value']
                                );
                            } else {
                                if (
                                    strpos(
                                        $parameters['filtered'][$i]['id'],
                                        'is'
                                    ) !== false
                                ) {
                                    $res->where(
                                        $parameters['filtered'][$i]['id'],
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    $res->where(
                                        $parameters['filtered'][$i]['id'],
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                }
                            }
                        }
                        $flag = 1;
                    } else {
                        if ($parameters['tipo'] == 'and') {
                            if (
                                $parameters['filtered'][$i]['id'] &&
                                strpos(
                                    $parameters['filtered'][$i]['id'],
                                    'id'
                                ) !== false
                            ) {
                                if (
                                    is_array(
                                        $parameters['filtered'][$i]['value']
                                    )
                                ) {
                                    $res->whereIn(
                                        $parameters['filtered'][$i]['id'],
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    $res->where(
                                        $parameters['filtered'][$i]['id'],
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                }
                            } else {
                                if (
                                    strcmp(
                                        $parameters['filtered'][$i]['id'],
                                        'vales.UserCreated'
                                    ) === 0 ||
                                    strcmp(
                                        $parameters['filtered'][$i]['id'],
                                        'vales.UserUpdated'
                                    ) === 0 ||
                                    strcmp(
                                        $parameters['filtered'][$i]['id'],
                                        'vales.UserOwned'
                                    ) === 0
                                ) {
                                    $res->where(
                                        $parameters['filtered'][$i]['id'],
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    if (
                                        strpos(
                                            $parameters['filtered'][$i]['id'],
                                            'is'
                                        ) !== false
                                    ) {
                                        $res->where(
                                            $parameters['filtered'][$i]['id'],
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->where(
                                            $parameters['filtered'][$i]['id'],
                                            'LIKE',
                                            '%' .
                                                $parameters['filtered'][$i][
                                                    'value'
                                                ] .
                                                '%'
                                        );
                                    }
                                }
                            }
                        } else {
                            if (
                                $parameters['filtered'][$i]['id'] &&
                                strpos(
                                    $parameters['filtered'][$i]['id'],
                                    'id'
                                ) !== false
                            ) {
                                if (
                                    is_array(
                                        $parameters['filtered'][$i]['value']
                                    )
                                ) {
                                    $res->orWhereIn(
                                        $parameters['filtered'][$i]['id'],
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    $res->orWhere(
                                        $parameters['filtered'][$i]['id'],
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                }
                            } else {
                                if (
                                    strcmp(
                                        $parameters['filtered'][$i]['id'],
                                        'vales.UserCreated'
                                    ) === 0 ||
                                    strcmp(
                                        $parameters['filtered'][$i]['id'],
                                        'vales.UserUpdated'
                                    ) === 0
                                ) {
                                    $res->orWhere(
                                        $parameters['filtered'][$i]['id'],
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    if (
                                        strpos(
                                            $parameters['filtered'][$i]['id'],
                                            'is'
                                        ) !== false
                                    ) {
                                        $res->orWhere(
                                            $parameters['filtered'][$i]['id'],
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->orWhere(
                                            $parameters['filtered'][$i]['id'],
                                            'LIKE',
                                            '%' .
                                                $parameters['filtered'][$i][
                                                    'value'
                                                ] .
                                                '%'
                                        );
                                    }
                                }
                            }
                        }
                    }
                }
            }

            $page = $parameters['page'];
            $pageSize = $parameters['pageSize'];

            $startIndex = $page * $pageSize;
            if (isset($parameters['sorted'])) {
                for ($i = 0; $i < count($parameters['sorted']); $i++) {
                    if ($parameters['sorted'][$i]['desc'] === true) {
                        $res->orderBy($parameters['sorted'][$i]['id'], 'desc');
                    } else {
                        $res->orderBy($parameters['sorted'][$i]['id'], 'asc');
                    }
                }
            }

            if (isset($parameters['NombreCompleto'])) {
                $filtro_recibido = $parameters['NombreCompleto'];
                $filtro_recibido = str_replace(' ', '', $filtro_recibido);
                $res->where(
                    DB::raw("
                    REPLACE(
                    CONCAT(
                        vales.Nombre,
                        vales.Paterno,
                        vales.Materno,
                        vales.Paterno,
                        vales.Nombre,
                        vales.Materno,
                        vales.Materno,
                        vales.Nombre,
                        vales.Paterno,
                        vales.Nombre,
                        vales.Materno,
                        vales.Paterno,
                        vales.Paterno,
                        vales.Materno,
                        vales.Nombre,
                        vales.Materno,
                        vales.Paterno,
                        vales.Nombre
                    ), ' ', '')"),

                    'like',
                    '%' . $filtro_recibido . '%'
                );
            }

            if (isset($parameters['NombreOwner'])) {
                $filtro_recibido = $parameters['NombreOwner'];
                $filtro_recibido = str_replace(' ', '', $filtro_recibido);
                $res->where(
                    DB::raw("
                    REPLACE(
                    CONCAT(
                        usersC.Nombre,
                        usersC.Paterno,
                        usersC.Materno,
                        usersC.Paterno,
                        usersC.Nombre,
                        usersC.Materno,
                        usersC.Materno,
                        usersC.Nombre,
                        usersC.Paterno,
                        usersC.Nombre,
                        usersC.Materno,
                        usersC.Paterno,
                        usersC.Paterno,
                        usersC.Materno,
                        usersC.Nombre,
                        usersC.Materno,
                        usersC.Paterno,
                        usersC.Nombre
                    ), ' ', '')"),

                    'like',
                    '%' . $filtro_recibido . '%'
                );
            }

            if (isset($parameters['NombreCreated'])) {
                $filtro_recibido = $parameters['NombreCreated'];
                $filtro_recibido = str_replace(' ', '', $filtro_recibido);
                $res->where(
                    DB::raw("
                    REPLACE(
                    CONCAT(
                        usersCretaed.Nombre,
                        usersCretaed.Paterno,
                        usersCretaed.Materno,
                        usersCretaed.Paterno,
                        usersCretaed.Nombre,
                        usersCretaed.Materno,
                        usersCretaed.Materno,
                        usersCretaed.Nombre,
                        usersCretaed.Paterno,
                        usersCretaed.Nombre,
                        usersCretaed.Materno,
                        usersCretaed.Paterno,
                        usersCretaed.Paterno,
                        usersCretaed.Materno,
                        usersCretaed.Nombre,
                        usersCretaed.Materno,
                        usersCretaed.Paterno,
                        usersCretaed.Nombre
                    ), ' ', '')"),

                    'like',
                    '%' . $filtro_recibido . '%'
                );
            }

            $total = $res->count();
            $res = $res
                ->offset($startIndex)
                ->take($pageSize)
                ->get();

            $parameters_serializado = serialize($parameters);
            //$array = unserialize($parameters_serializado);
            $user = auth()->user();
            $filtro_usuario = VNegociosFiltros::where('idUser', '=', $user->id)
                ->where('api', '=', 'getVales')
                ->first();
            if ($filtro_usuario) {
                $filtro_usuario->parameters = $parameters_serializado;
                $filtro_usuario->update();
            } else {
                $objeto_nuevo = new VNegociosFiltros();
                $objeto_nuevo->api = 'getVales';
                $objeto_nuevo->idUser = $user->id;
                $objeto_nuevo->parameters = $parameters_serializado;
                $objeto_nuevo->save();
            }

            $array_res = [];
            $temp = [];
            foreach ($res as $data) {
                $temp = [
                    'id' => $data->id,
                    'ClaveUnica' => $data->ClaveUnica,
                    'TelRecados' => $data->TelRecados,
                    'Compania' => $data->Compania,
                    'TelFijo' => $data->TelFijo,
                    'FolioSolicitud' => $data->FolioSolicitud,
                    'FechaSolicitud' => $data->FechaSolicitud,
                    'CURP' => $data->CURP,
                    'Nombre' => $data->Nombre,
                    'Paterno' => $data->Paterno,
                    'Materno' => $data->Materno,
                    'Sexo' => $data->Sexo,
                    'FechaNacimiento' => $data->FechaNacimiento,
                    'Calle' => $data->Calle,
                    'NumExt' => $data->NumExt,
                    'NumInt' => $data->NumInt,
                    'Colonia' => $data->Colonia,
                    'CP' => $data->CP,
                    'FechaDocumentacion' => $data->FechaDocumentacion,
                    'isDocumentacionEntrega' => $data->isDocumentacionEntrega,
                    'idUserDocumentacion' => $data->idUserDocumentacion,
                    'isEntregadoOwner' => $data->isEntregadoOwner,
                    'idUserReportaEntrega' => $data->idUserReportaEntrega,
                    'ComentarioEntrega' => $data->ComentarioEntrega,
                    'FechaReportaEntrega' => $data->FechaReportaEntrega,

                    'IngresoPercibido' => $data->IngresoPercibido,
                    'OtrosIngresos' => $data->OtrosIngresos,
                    'NumeroPersonas' => $data->NumeroPersonas,
                    'OcupacionOtro' => $data->OcupacionOtro,
                    'Ocupacion' => $data->Ocupacion,
                    'Bloqueado' => $data->Bloqueado,
                    'idMunicipio' => [
                        'id' => $data->IdM,
                        'Municipio' => $data->Municipio,
                        'Region' => $data->Region,
                    ],
                    'idLocalidad' => [
                        'id' => $data->Clave,
                        'Nombre' => $data->Localidad,
                    ],
                    'TelFijo' => $data->TelFijo,
                    'TelCelular' => $data->TelCelular,
                    'CorreoElectronico' => $data->CorreoElectronico,
                    'idStatus' => [
                        'id' => $data->idES,
                        'Clave' => $data->ClaveA,
                        'Estatus' => $data->Estatus,
                    ],
                    'created_at' => $data->created_at,
                    'updated_at' => $data->updated_at,
                    'UserCreated' => [
                        'id' => $data->idE,
                        'email' => $data->emailE,
                        'Nombre' => $data->NombreE,
                        'Paterno' => $data->PaternoE,
                        'Materno' => $data->MaternoE,
                        'idTipoUser' => [
                            'id' => $data->idEA,
                            'TipoUser' => $data->TipoUserEA,
                            'Clave' => $data->ClaveEA,
                        ],
                    ],
                    'UserUpdated' => [
                        'id' => $data->idF,
                        'email' => $data->emailF,
                        'Nombre' => $data->NombreF,
                        'Paterno' => $data->PaternoF,
                        'Materno' => $data->MaternoF,
                        'idTipoUser' => [
                            'id' => $data->idFA,
                            'TipoUser' => $data->TipoUserFA,
                            'Clave' => $data->ClaveFA,
                        ],
                    ],
                    'UserOwned' => [
                        'id' => $data->idO,
                        'email' => $data->emailO,
                        'Nombre' => $data->NombreO,
                        'Paterno' => $data->PaternoO,
                        'Materno' => $data->MaternoO,
                        'idTipoUser' => [
                            'id' => $data->idGO,
                            'TipoUser' => $data->TipoUserGO,
                            'Clave' => $data->ClaveGO,
                        ],
                    ],
                ];

                array_push($array_res, $temp);
            }

            return [
                'success' => true,
                'results' => true,
                'total' => $total,
                'filtros' => $parameters['filtered'],
                'data' => $array_res,
            ];
        } catch (QueryException $e) {
            $errors = [
                'Clave' => '01',
            ];
            $response = [
                'success' => true,
                'results' => false,
                'total' => 0,
                'filtros' => $parameters['filtered'],
                'errors' => $e->getMessage(),
                'message' => 'Campo de consulta incorrecto',
            ];

            return response()->json($response, 200);
        }
    }

    function getValesAvances(Request $request)
    {
        $parameters = $request->all();
        $anio = 2022;
        $user = auth()->user();

        if (isset($parameters['Anio'])) {
            $anio = $parameters['Anio'];
        }

        DB::beginTransaction();

        DB::table('users_filtros')
            ->where('UserCreated', $user->id)
            ->where('api', 'getReporteAvancesVales')
            ->delete();

        DB::commit();
        $parameters_serializado = serialize($parameters);

        //Insertamos los filtros
        DB::table('users_filtros')->insert([
            'UserCreated' => $user->id,
            'Api' => 'getReporteAvancesVales',
            'Consulta' => $parameters_serializado,
            'created_at' => date('Y-m-d h-m-s'),
        ]);

        try {
            $tablaMeta = "(
                    Select M.Id, M.Subregion as Region, M.Nombre as Municipio, MM.ApoyoAmpliado as Apoyos
                    from et_cat_municipio as M inner join meta_municipio as MM on (M.Id = MM.idMunicipio)
                    where MM.Ejercicio=$anio) as M ";

            $tabla3 = "(
                    select idMunicipio, count(id) as AprobadosComite
                    from vales
                    where Remesa is not null and YEAR(FechaSolicitud) = $anio
                    group by idMunicipio) as AC";

            $tabla4 = "(
                        select idMunicipio, count(id) as Entregados
                        from vales
                        where Remesa is not null and YEAR(FechaSolicitud) = $anio and isEntregado=1
                        group by idMunicipio) as ET";

            $tablaIncidencias = "(
                            select idMunicipio, count(id) as Incidencias
                            from vales
                            where Remesa is not null and idIncidencia !=1 and YEAR(FechaSolicitud) = $anio
                            group by idMunicipio) as VI";

            if ($anio == 2022) {
                $tabla1 = "(
                        select vales.idMunicipio, count(vales.id) as SolicitudesPorAprobar
                        from vales JOIN cedulas_solicitudes ON vales.id = cedulas_solicitudes.idVale
                        where vales.Remesa is null and YEAR(vales.FechaSolicitud) = $anio
                        group by vales.idMunicipio
                        ) as S ";

                $tabla2 = "(
                    select vales.idMunicipio, count(vales.id) as ExpedientesRecibidos
                    from vales JOIN cedulas_solicitudes ON vales.id = cedulas_solicitudes.idVale
                    where YEAR(vales.FechaSolicitud) = $anio AND cedulas_solicitudes.ExpedienteCompleto = 1
                    group by idMunicipio) as E";
            } else {
                $tabla1 = "(
                    select idMunicipio, count(id) as SolicitudesPorAprobar
                    from vales
                    where Remesa is null and YEAR(FechaSolicitud) = $anio and  isDocumentacionEntrega=0
                    group by idMunicipio
                    ) as S ";

                $tabla2 = "(
                select idMunicipio, count(id) as ExpedientesRecibidos
                from vales
                where Remesa is null and isDocumentacionEntrega=1 and YEAR(FechaSolicitud) = $anio
                group by idMunicipio) as E";
            }

            $queryGeneral = DB::table(DB::raw($tablaMeta))
                ->selectRaw(
                    'M.Region, M.Municipio, M.Apoyos, AC.AprobadosComite, if(VI.Incidencias is null, 0, VI.Incidencias) as Incidencias
                        , (M.Apoyos + if(VI.Incidencias is null, 0, VI.Incidencias) - if(AC.AprobadosComite is null, 0, AC.AprobadosComite)) as ApoyosMenosApronadosComite
                        , if(ET.Entregados is null, 0, ET.Entregados) as Entregados
                        , S.SolicitudesPorAprobar
                        , CASE WHEN E.ExpedientesRecibidos IS NULL THEN 0 ELSE E.ExpedientesRecibidos END AS ExpedientesRecibidos'
                )
                ->leftJoin(DB::raw($tabla1), 'S.idMunicipio', '=', 'M.Id')
                ->leftJoin(DB::raw($tabla2), 'E.idMunicipio', '=', 'M.Id')
                ->leftJoin(DB::raw($tabla3), 'AC.idMunicipio', '=', 'M.Id')
                ->leftJoin(DB::raw($tabla4), 'ET.idMunicipio', '=', 'M.Id')
                ->leftJoin(
                    DB::raw($tablaIncidencias),
                    'VI.idMunicipio',
                    '=',
                    'M.Id'
                );

            if (isset($parameters['Regiones'])) {
                $resMunicipio = DB::table('et_cat_municipio')
                    ->whereIn('SubRegion', $parameters['Regiones'])
                    ->pluck('Id');

                //dd($resMunicipio);

                $queryGeneral->whereIn('M.Id', $resMunicipio);
            }

            $queryGeneral
                ->orderBy('M.Region', 'ASC')
                ->orderBy('M.Municipio', 'ASC');

            $Items = $queryGeneral->get();

            return ['success' => true, 'results' => true, 'data' => $Items];
        } catch (QueryException $e) {
            $errors = [
                'Clave' => '01',
            ];
            $response = [
                'success' => true,
                'results' => false,
                'errors' => $e->getMessage(),
                'message' => 'Campo de consulta incorrecto',
            ];

            return response()->json($response, 200);
        }
    }

    public function getReporteAvances(Request $request)
    {
        $user = auth()->user();
        $parameters['UserCreated'] = $user->id;

        $filtro_usuario = DB::table('users_filtros')
            ->where('UserCreated', '=', $user->id)
            ->where('Api', '=', 'getReporteAvancesVales')
            ->first();
        $parameters = unserialize($filtro_usuario->Consulta);
        $anio = 2022;
        if (isset($parameters['Anio'])) {
            $anio = $parameters['Anio'];
        }

        $tablaMeta = "(
                Select M.Id, M.Subregion as Region, M.Nombre as Municipio, MM.ApoyoAmpliado as Apoyos
                from et_cat_municipio as M inner join meta_municipio as MM on (M.Id = MM.idMunicipio)
                where MM.Ejercicio=$anio) as M ";

        $tabla1 = "(
                    select vales.idMunicipio, count(vales.id) as SolicitudesPorAprobar
                    from vales JOIN cedulas_solicitudes ON vales.id = cedulas_solicitudes.idVale
                    where vales.Remesa is null and YEAR(vales.FechaSolicitud) = $anio
                    group by vales.idMunicipio
                    ) as S ";

        $tabla2 = "(
                        select vales.idMunicipio, count(vales.id) as ExpedientesRecibidos
                        from vales JOIN cedulas_solicitudes ON vales.id = cedulas_solicitudes.idVale
                        where YEAR(vales.FechaSolicitud) = $anio AND cedulas_solicitudes.ExpedienteCompleto = 1
                        group by idMunicipio) as E";

        $tabla3 = "(
                    select idMunicipio, count(id) as AprobadosComite
                    from vales
                    where Remesa is not null and YEAR(FechaSolicitud) = $anio
                    group by idMunicipio) as AC";

        $tabla4 = "(
                        select idMunicipio, count(id) as Entregados
                        from vales
                        where Remesa is not null and YEAR(FechaSolicitud) = $anio and isEntregado=1
                        group by idMunicipio) as ET";

        $tabla5 = "( SELECT 
                        vales.idMunicipio,
                        count( vales.id ) AS Capturados 
                     FROM
	                    cedulas_solicitudes
	                    JOIN vales ON cedulas_solicitudes.idVale = vales.id 
                     WHERE	
                        YEAR ( vales.FechaSolicitud ) = $anio 
                        AND cedulas_solicitudes.FechaElimino IS NULL
                     GROUP BY
	                    vales.idMunicipio
                    ) as CAP ";

        $tablaIncidencias = "(
                    select idMunicipio, count(id) as Incidencias
                    from vales
                    where Remesa is not null and idIncidencia !=1 and YEAR(FechaSolicitud) = $anio
                    group by idMunicipio) as VI";

        $queryGeneral = DB::table(DB::raw($tablaMeta))
            ->selectRaw(
                'M.Region, M.Municipio, M.Apoyos, AC.AprobadosComite
                , if(ET.Entregados is null, 0, ET.Entregados) as Entregados, if(VI.Incidencias is null, 0, VI.Incidencias) as Incidencias
                , (M.Apoyos + if(VI.Incidencias is null, 0, VI.Incidencias) - if(AC.AprobadosComite is null, 0, AC.AprobadosComite)) as ApoyosMenosApronadosComite
                , S.SolicitudesPorAprobar
                , CASE WHEN E.ExpedientesRecibidos IS NULL THEN 0 ELSE E.ExpedientesRecibidos END AS ExpedientesRecibidos'
            )
            ->leftJoin(DB::raw($tabla1), 'S.idMunicipio', '=', 'M.Id')
            ->leftJoin(DB::raw($tabla2), 'E.idMunicipio', '=', 'M.Id')
            ->leftJoin(DB::raw($tabla3), 'AC.idMunicipio', '=', 'M.Id')
            ->leftJoin(DB::raw($tabla4), 'ET.idMunicipio', '=', 'M.Id')
            ->leftJoin(DB::raw($tabla5), 'CAP.idMunicipio', '=', 'M.Id')
            ->leftJoin(
                DB::raw($tablaIncidencias),
                'VI.idMunicipio',
                '=',
                'M.Id'
            );

        if (isset($parameters['Regiones'])) {
            $resMunicipio = DB::table('et_cat_municipio')
                ->whereIn('SubRegion', $parameters['Regiones'])
                ->pluck('Id');

            //dd($resMunicipio);

            $queryGeneral->whereIn('M.Id', $resMunicipio);
        }

        $queryGeneral
            ->orderBy('M.Region', 'ASC')
            ->orderBy('M.Municipio', 'ASC');

        $Items = $queryGeneral->get();
        //Mapeamos el resultado como un array
        if ($Items != null) {
            $res2 = $Items
                ->map(function ($x) {
                    $x = is_object($x) ? (array) $x : $x;
                    return $x;
                })
                ->toArray();

            $res = [];
            foreach ($res2 as $arrayDatos) {
                $res[] = [
                    'Region' => $arrayDatos['Region']
                        ? $arrayDatos['Region']
                        : '0',
                    'Municipio' => $arrayDatos['Municipio'],
                    'Apoyos' => $arrayDatos['Apoyos']
                        ? $arrayDatos['Apoyos']
                        : '0',
                    'AprobadosComite' => $arrayDatos['AprobadosComite']
                        ? $arrayDatos['AprobadosComite']
                        : '0',
                    'Entregados' => $arrayDatos['Entregados']
                        ? $arrayDatos['Entregados']
                        : '0',
                    'Incidencias' => $arrayDatos['Incidencias']
                        ? $arrayDatos['Incidencias']
                        : '0',
                    'ApoyosMenosApronadosComite' => $arrayDatos[
                        'ApoyosMenosApronadosComite'
                    ]
                        ? $arrayDatos['ApoyosMenosApronadosComite']
                        : '0',
                    'SolicitudesPorAprobar' => $arrayDatos[
                        'SolicitudesPorAprobar'
                    ]
                        ? $arrayDatos['SolicitudesPorAprobar']
                        : '0',
                    'ExpedientesRecibidos' => $arrayDatos[
                        'ExpedientesRecibidos'
                    ]
                        ? $arrayDatos['ExpedientesRecibidos']
                        : '0',
                ];
            }

            $reader = IOFactory::createReader('Xlsx');
            $spreadsheet = $reader->load(
                public_path() . '/archivos/formatoReporteAvanceValesV1.xlsx'
            );

            $sheet = $spreadsheet->getActiveSheet();
            $largo = count($res);
            $impresion = $largo + 5;

            $sheet->getPageSetup()->setPrintArea('A1:V' . $impresion);
            $sheet
                ->getPageSetup()
                ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
            $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_LETTER);

            $largo = count($res);

            //Llenar excel con el resultado del query
            $sheet->fromArray($res, null, 'C6');
            //Agregamos la fecha
            $sheet->setCellValue('L2', 'Fecha Reporte: ' . date('Y-m-d H:i:s'));

            //Agregar el indice autonumerico

            for ($i = 1; $i <= $largo; $i++) {
                $inicio = 5 + $i;
                $sheet->setCellValue('B' . $inicio, $i);
            }

            if ($largo > 75) {
                //     //dd('Se agrega lineBreak');
                for ($lb = 70; $lb < $largo; $lb += 70) {
                    //         $veces++;
                    //         //dd($largo);
                    $sheet->setBreak(
                        'B' . ($lb + 10),
                        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::BREAK_ROW
                    );
                }
            }

            $sheet->getDefaultRowDimension()->setRowHeight(-1);

            //guardamos el excel creado y luego lo obtenemos en $file para poder descargarlo
            $writer = new Xlsx($spreadsheet);
            $writer->save(
                'archivos/' .
                    $user->email .
                    'SolicitudesValesGrandezaAvances.xlsx'
            );
            $file =
                public_path() .
                '/archivos/' .
                $user->email .
                'SolicitudesValesGrandezaAvances.xlsx';

            return response()->download(
                $file,
                $user->email .
                    'SolicitudesValesGrandezaAvances' .
                    date('Y-m-d H:i:s') .
                    '.xlsx'
            );
        }
    }

    function getRemesas(Request $request)
    {
        $parameters = $request->all();

        try {
            $res = DB::table('vales')
                ->select('vales.Remesa')
                //->whereNotNull('Remesa')
                ->groupBy('Remesa')
                ->orderBy('Remesa');

            $flag = 0;
            if (isset($parameters['filtered'])) {
                for ($i = 0; $i < count($parameters['filtered']); $i++) {
                    if ($flag == 0) {
                        if (
                            $parameters['filtered'][$i]['id'] &&
                            strpos($parameters['filtered'][$i]['id'], 'id') !==
                                false
                        ) {
                            if (
                                is_array($parameters['filtered'][$i]['value'])
                            ) {
                                $res->whereIn(
                                    $parameters['filtered'][$i]['id'],
                                    $parameters['filtered'][$i]['value']
                                );
                            } else {
                                $res->where(
                                    $parameters['filtered'][$i]['id'],
                                    '=',
                                    $parameters['filtered'][$i]['value']
                                );
                            }
                        } else {
                            $res->where(
                                $parameters['filtered'][$i]['id'],
                                'LIKE',
                                '%' . $parameters['filtered'][$i]['value'] . '%'
                            );
                        }
                        $flag = 1;
                    } else {
                        if ($parameters['tipo'] == 'and') {
                            if (
                                $parameters['filtered'][$i]['id'] &&
                                strpos(
                                    $parameters['filtered'][$i]['id'],
                                    'id'
                                ) !== false
                            ) {
                                if (
                                    is_array(
                                        $parameters['filtered'][$i]['value']
                                    )
                                ) {
                                    $res->whereIn(
                                        $parameters['filtered'][$i]['id'],
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    $res->where(
                                        $parameters['filtered'][$i]['id'],
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                }
                            } else {
                                $res->where(
                                    $parameters['filtered'][$i]['id'],
                                    'LIKE',
                                    '%' .
                                        $parameters['filtered'][$i]['value'] .
                                        '%'
                                );
                            }
                        } else {
                            if (
                                $parameters['filtered'][$i]['id'] &&
                                strpos(
                                    $parameters['filtered'][$i]['id'],
                                    'id'
                                ) !== false
                            ) {
                                if (
                                    is_array(
                                        $parameters['filtered'][$i]['value']
                                    )
                                ) {
                                    $res->orWhereIn(
                                        $parameters['filtered'][$i]['id'],
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    $res->orWhere(
                                        $parameters['filtered'][$i]['id'],
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                }
                            } else {
                                $res->orWhere(
                                    $parameters['filtered'][$i]['id'],
                                    'LIKE',
                                    '%' .
                                        $parameters['filtered'][$i]['value'] .
                                        '%'
                                );
                            }
                        }
                    }
                }
            }

            $page = $parameters['page'];
            $pageSize = $parameters['pageSize'];

            $startIndex = $page * $pageSize;
            if (isset($parameters['sorted'])) {
                for ($i = 0; $i < count($parameters['sorted']); $i++) {
                    if ($parameters['sorted'][$i]['desc'] === true) {
                        $res->orderBy($parameters['sorted'][$i]['id'], 'desc');
                    } else {
                        $res->orderBy($parameters['sorted'][$i]['id'], 'asc');
                    }
                }
            }

            $total = $res->count();
            $res = $res
                ->offset($startIndex)
                ->take($pageSize)
                ->get();

            return [
                'success' => true,
                'results' => true,
                'total' => $total,
                'filtros' => $parameters['filtered'],
                'data' => $res,
            ];
        } catch (QueryException $e) {
            $errors = [
                'Clave' => '01',
            ];
            $response = [
                'success' => true,
                'results' => false,
                'total' => 0,
                'filtros' => $parameters['filtered'],
                'errors' => $errors,
                'message' => 'Campo de consulta incorrecto',
            ];

            return response()->json($response, 200);
        }
    }

    function getValesV2Fecha(Request $request)
    {
        $parameters = $request->all();
        try {
            $res = DB::table('vales')
                ->select(
                    'vales.id',
                    DB::raw('LPAD(HEX(vales.id),6,0) as ClaveUnica'),
                    'vales.TelRecados',
                    'vales.Compania',
                    'vales.TelFijo',
                    'vales.FolioSolicitud',
                    'vales.FechaSolicitud',
                    'vales.CURP',
                    'vales.Nombre',
                    'vales.Paterno',
                    'vales.Materno',
                    'vales.Sexo',
                    'vales.FechaNacimiento',
                    'vales.Calle',
                    'vales.NumExt',
                    'vales.NumInt',
                    'vales.Colonia',
                    'vales.CP',
                    'vales.idMunicipio',
                    'et_cat_municipio.Id AS IdM',
                    'et_cat_municipio.Nombre AS Municipio',
                    'et_cat_municipio.SubRegion AS Region',
                    'vales.idLocalidad',
                    'et_cat_localidad.Id AS Clave',
                    'et_cat_localidad.Nombre AS Localidad',
                    'vales.TelFijo',
                    'vales.TelCelular',
                    'vales.isEntregado',
                    'vales.entrega_at',
                    'vales.CorreoElectronico',
                    'vales.FechaDocumentacion',
                    'vales.isDocumentacionEntrega',
                    'vales.idUserDocumentacion',
                    'vales.isEntregadoOwner',
                    'vales.idUserReportaEntrega',
                    'vales.ComentarioEntrega',
                    'vales.FechaReportaEntrega',
                    'vales.idStatus',
                    'vales_status.id as idES',
                    'vales_status.Estatus',
                    'vales_status.Clave as ClaveA',
                    'vales.created_at',
                    'vales.updated_at',
                    'vales.UserCreated',
                    //Datos Usuario created
                    'users.id as idE',
                    'users.email as emailE',
                    'users.Nombre as NombreE',
                    'users.Paterno as PaternoE',
                    'users.Materno as MaternoE',
                    'users.idTipoUser',
                    'cat_usertipo.id as idEA',
                    'cat_usertipo.TipoUser as TipoUserEA',
                    'cat_usertipo.Clave as ClaveEA',
                    'vales.UserUpdated',
                    //Datos Usuario updated
                    'usersB.id as idF',
                    'usersB.email as emailF',
                    'usersB.Nombre as NombreF',
                    'usersB.Paterno as PaternoF',
                    'usersB.Materno as MaternoF',
                    'usersB.idTipoUser',
                    'cat_usertipoB.id as idFA',
                    'cat_usertipoB.TipoUser as TipoUserFA',
                    'cat_usertipoB.Clave as ClaveFA',
                    'vales.UserOwned',
                    //Datos Usuario owned
                    'usersC.id as idO',
                    'usersC.email as emailO',
                    'usersC.Nombre as NombreO',
                    'usersC.Paterno as PaternoO',
                    'usersC.Materno as MaternoO',
                    'usersC.idTipoUser',
                    'cat_usertipoC.id as idGO',
                    'cat_usertipoC.TipoUser as TipoUserGO',
                    'cat_usertipoC.Clave as ClaveGO'
                )
                ->leftJoin(
                    'et_cat_municipio',
                    'et_cat_municipio.Id',
                    '=',
                    'vales.idMunicipio'
                )
                ->leftJoin(
                    'et_cat_localidad',
                    'et_cat_localidad.Id',
                    '=',
                    'vales.idLocalidad'
                )
                ->leftJoin('vales_status', 'vales_status.id', '=', 'idStatus')
                ->leftJoin('users', 'users.id', '=', 'vales.UserCreated')
                ->leftJoin(
                    'cat_usertipo',
                    'cat_usertipo.id',
                    '=',
                    'users.idTipoUser'
                )
                ->leftJoin(
                    'users as usersB',
                    'usersB.id',
                    '=',
                    'vales.UserUpdated'
                )
                ->leftJoin(
                    'cat_usertipo as cat_usertipoB',
                    'cat_usertipoB.id',
                    '=',
                    'usersB.idTipoUser'
                )
                ->leftJoin(
                    'users as usersC',
                    'usersC.id',
                    '=',
                    'vales.UserOwned'
                )
                ->leftJoin(
                    'users as usersCretaed',
                    'usersCretaed.id',
                    '=',
                    'vales.UserCreated'
                )
                ->leftJoin(
                    'cat_usertipo as cat_usertipoC',
                    'cat_usertipoC.id',
                    '=',
                    'usersC.idTipoUser'
                );

            $flag = 0;
            if (isset($parameters['Propietario'])) {
                $valor_id = $parameters['Propietario'];
                $res->where(function ($q) use ($valor_id) {
                    $q
                        ->where('vales.UserCreated', $valor_id)
                        ->orWhere('vales.UserOwned', $valor_id);
                });
            }
            if (isset($parameters['Folio'])) {
                $valor_id = $parameters['Folio'];
                $res->where(
                    DB::raw('LPAD(HEX(vales.id),6,0)'),
                    '=',
                    '' . $valor_id . ''
                );
            }

            if (isset($parameters['Regiones'])) {
                if (is_array($parameters['Regiones'])) {
                    $res->whereIn(
                        'et_cat_municipio.SubRegion',
                        $parameters['Regiones']
                    );
                } else {
                    $res->where(
                        'et_cat_municipio.SubRegion',
                        '=',
                        $parameters['Regiones']
                    );
                }
            }
            if (isset($parameters['UserOwned'])) {
                if (is_array($parameters['UserOwned'])) {
                    $res->whereIn('vales.UserOwned', $parameters['UserOwned']);
                } else {
                    $res->where(
                        'vales.UserOwned',
                        '=',
                        $parameters['UserOwned']
                    );
                }
            }

            if (isset($parameters['idMunicipio'])) {
                if (is_array($parameters['idMunicipio'])) {
                    $res->whereIn(
                        'vales.idMunicipio',
                        $parameters['idMunicipio']
                    );
                } else {
                    $res->where(
                        'vales.idMunicipio',
                        '=',
                        $parameters['idMunicipio']
                    );
                }
            }
            if (isset($parameters['Colonia'])) {
                if (is_array($parameters['Colonia'])) {
                    $res->whereIn('vales.Colonia', $parameters['Colonia']);
                } else {
                    $res->where('vales.Colonia', '=', $parameters['Colonia']);
                }
            }
            if (isset($parameters['idStatus'])) {
                if (is_array($parameters['idStatus'])) {
                    $res->whereIn('vales_status.id', $parameters['idStatus']);
                } else {
                    $res->where(
                        'vales_status.id',
                        '=',
                        $parameters['idStatus']
                    );
                }
            }
            if (isset($parameters['Remesa'])) {
                if (is_array($parameters['Remesa'])) {
                    $flag_null = false;
                    foreach ($parameters['Remesa'] as $dato) {
                        if (strcmp($dato, 'null') === 0) {
                            $flag_null = true;
                        }
                    }
                    if ($flag_null) {
                        $valor_id = $parameters['Remesa'];
                        $res
                            ->where(function ($q) use ($valor_id) {
                                $q
                                    ->whereIn('vales.Remesa', $valor_id)
                                    ->orWhereNull('vales.Remesa');
                            })
                            ->orderBy('Remesa');
                    } else {
                        $res
                            ->whereIn('vales.Remesa', $parameters['Remesa'])
                            ->orderBy('Remesa');
                    }
                } else {
                    if (strcmp($parameters['Remesa'], 'null') === 0) {
                        $res->whereNull('vales.Remesa')->orderBy('Remesa');
                    } else {
                        $res
                            ->where('vales.Remesa', '=', $parameters['Remesa'])
                            ->orderBy('Remesa');
                    }
                }
            }

            if (isset($parameters['filtered'])) {
                for ($i = 0; $i < count($parameters['filtered']); $i++) {
                    if ($flag == 0) {
                        if (
                            $parameters['filtered'][$i]['id'] &&
                            strpos($parameters['filtered'][$i]['id'], 'id') !==
                                false
                        ) {
                            if (
                                is_array($parameters['filtered'][$i]['value'])
                            ) {
                                $res->whereIn(
                                    $parameters['filtered'][$i]['id'],
                                    $parameters['filtered'][$i]['value']
                                );
                            } else {
                                $res->where(
                                    $parameters['filtered'][$i]['id'],
                                    '=',
                                    $parameters['filtered'][$i]['value']
                                );
                            }
                        } else {
                            if (
                                strcmp(
                                    $parameters['filtered'][$i]['id'],
                                    'vales.UserCreated'
                                ) === 0 ||
                                strcmp(
                                    $parameters['filtered'][$i]['id'],
                                    'vales.UserUpdated'
                                ) === 0 ||
                                strcmp(
                                    $parameters['filtered'][$i]['id'],
                                    'vales.UserOwned'
                                ) === 0
                            ) {
                                $res->where(
                                    $parameters['filtered'][$i]['id'],
                                    '=',
                                    $parameters['filtered'][$i]['value']
                                );
                            } else {
                                if (
                                    strpos(
                                        $parameters['filtered'][$i]['id'],
                                        'is'
                                    ) !== false
                                ) {
                                    $res->where(
                                        $parameters['filtered'][$i]['id'],
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    $res->where(
                                        $parameters['filtered'][$i]['id'],
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                }
                            }
                        }
                        $flag = 1;
                    } else {
                        if ($parameters['tipo'] == 'and') {
                            if (
                                $parameters['filtered'][$i]['id'] &&
                                strpos(
                                    $parameters['filtered'][$i]['id'],
                                    'id'
                                ) !== false
                            ) {
                                if (
                                    is_array(
                                        $parameters['filtered'][$i]['value']
                                    )
                                ) {
                                    $res->whereIn(
                                        $parameters['filtered'][$i]['id'],
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    $res->where(
                                        $parameters['filtered'][$i]['id'],
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                }
                            } else {
                                if (
                                    strcmp(
                                        $parameters['filtered'][$i]['id'],
                                        'vales.UserCreated'
                                    ) === 0 ||
                                    strcmp(
                                        $parameters['filtered'][$i]['id'],
                                        'vales.UserUpdated'
                                    ) === 0 ||
                                    strcmp(
                                        $parameters['filtered'][$i]['id'],
                                        'vales.UserOwned'
                                    ) === 0
                                ) {
                                    $res->where(
                                        $parameters['filtered'][$i]['id'],
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    if (
                                        strpos(
                                            $parameters['filtered'][$i]['id'],
                                            'is'
                                        ) !== false
                                    ) {
                                        $res->where(
                                            $parameters['filtered'][$i]['id'],
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->where(
                                            $parameters['filtered'][$i]['id'],
                                            'LIKE',
                                            '%' .
                                                $parameters['filtered'][$i][
                                                    'value'
                                                ] .
                                                '%'
                                        );
                                    }
                                }
                            }
                        } else {
                            if (
                                $parameters['filtered'][$i]['id'] &&
                                strpos(
                                    $parameters['filtered'][$i]['id'],
                                    'id'
                                ) !== false
                            ) {
                                if (
                                    is_array(
                                        $parameters['filtered'][$i]['value']
                                    )
                                ) {
                                    $res->orWhereIn(
                                        $parameters['filtered'][$i]['id'],
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    $res->orWhere(
                                        $parameters['filtered'][$i]['id'],
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                }
                            } else {
                                if (
                                    strcmp(
                                        $parameters['filtered'][$i]['id'],
                                        'vales.UserCreated'
                                    ) === 0 ||
                                    strcmp(
                                        $parameters['filtered'][$i]['id'],
                                        'vales.UserUpdated'
                                    ) === 0
                                ) {
                                    $res->orWhere(
                                        $parameters['filtered'][$i]['id'],
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    if (
                                        strpos(
                                            $parameters['filtered'][$i]['id'],
                                            'is'
                                        ) !== false
                                    ) {
                                        $res->orWhere(
                                            $parameters['filtered'][$i]['id'],
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->orWhere(
                                            $parameters['filtered'][$i]['id'],
                                            'LIKE',
                                            '%' .
                                                $parameters['filtered'][$i][
                                                    'value'
                                                ] .
                                                '%'
                                        );
                                    }
                                }
                            }
                        }
                    }
                }
            }

            $page = $parameters['page'];
            $pageSize = $parameters['pageSize'];

            $startIndex = $page * $pageSize;
            if (isset($parameters['sorted'])) {
                for ($i = 0; $i < count($parameters['sorted']); $i++) {
                    if ($parameters['sorted'][$i]['desc'] === true) {
                        $res->orderBy($parameters['sorted'][$i]['id'], 'desc');
                    } else {
                        $res->orderBy($parameters['sorted'][$i]['id'], 'asc');
                    }
                }
            }

            if (isset($parameters['NombreCompleto'])) {
                $filtro_recibido = $parameters['NombreCompleto'];
                $filtro_recibido = str_replace(' ', '', $filtro_recibido);
                $res->where(
                    DB::raw("
                    REPLACE(
                    CONCAT(
                        vales.Nombre,
                        vales.Paterno,
                        vales.Materno,
                        vales.Paterno,
                        vales.Nombre,
                        vales.Materno,
                        vales.Materno,
                        vales.Nombre,
                        vales.Paterno,
                        vales.Nombre,
                        vales.Materno,
                        vales.Paterno,
                        vales.Paterno,
                        vales.Materno,
                        vales.Nombre,
                        vales.Materno,
                        vales.Paterno,
                        vales.Nombre
                    ), ' ', '')"),

                    'like',
                    '%' . $filtro_recibido . '%'
                );
            }

            if (isset($parameters['NombreOwner'])) {
                $filtro_recibido = $parameters['NombreOwner'];
                $filtro_recibido = str_replace(' ', '', $filtro_recibido);
                $res->where(
                    DB::raw("
                    REPLACE(
                    CONCAT(
                        usersC.Nombre,
                        usersC.Paterno,
                        usersC.Materno,
                        usersC.Paterno,
                        usersC.Nombre,
                        usersC.Materno,
                        usersC.Materno,
                        usersC.Nombre,
                        usersC.Paterno,
                        usersC.Nombre,
                        usersC.Materno,
                        usersC.Paterno,
                        usersC.Paterno,
                        usersC.Materno,
                        usersC.Nombre,
                        usersC.Materno,
                        usersC.Paterno,
                        usersC.Nombre
                    ), ' ', '')"),

                    'like',
                    '%' . $filtro_recibido . '%'
                );
            }

            if (isset($parameters['NombreCreated'])) {
                $filtro_recibido = $parameters['NombreCreated'];
                $filtro_recibido = str_replace(' ', '', $filtro_recibido);
                $res->where(
                    DB::raw("
                    REPLACE(
                    CONCAT(
                        usersCretaed.Nombre,
                        usersCretaed.Paterno,
                        usersCretaed.Materno,
                        usersCretaed.Paterno,
                        usersCretaed.Nombre,
                        usersCretaed.Materno,
                        usersCretaed.Materno,
                        usersCretaed.Nombre,
                        usersCretaed.Paterno,
                        usersCretaed.Nombre,
                        usersCretaed.Materno,
                        usersCretaed.Paterno,
                        usersCretaed.Paterno,
                        usersCretaed.Materno,
                        usersCretaed.Nombre,
                        usersCretaed.Materno,
                        usersCretaed.Paterno,
                        usersCretaed.Nombre
                    ), ' ', '')"),

                    'like',
                    '%' . $filtro_recibido . '%'
                );
            }

            $total = $res->count();
            $res = $res
                ->offset($startIndex)
                ->take($pageSize)
                ->get();

            $parameters_serializado = serialize($parameters);
            //$array = unserialize($parameters_serializado);
            $user = auth()->user();
            $filtro_usuario = VNegociosFiltros::where('idUser', '=', $user->id)
                ->where('api', '=', 'getVales')
                ->first();
            if ($filtro_usuario) {
                $filtro_usuario->parameters = $parameters_serializado;
                $filtro_usuario->update();
            } else {
                $objeto_nuevo = new VNegociosFiltros();
                $objeto_nuevo->api = 'getVales';
                $objeto_nuevo->idUser = $user->id;
                $objeto_nuevo->parameters = $parameters_serializado;
                $objeto_nuevo->save();
            }

            $array_res = [];
            $temp = [];
            foreach ($res as $data) {
                $temp = [
                    'id' => $data->id,
                    'ClaveUnica' => $data->ClaveUnica,
                    'TelRecados' => $data->TelRecados,
                    'Compania' => $data->Compania,
                    'TelFijo' => $data->TelFijo,
                    'FolioSolicitud' => $data->FolioSolicitud,
                    'FechaSolicitud' => $data->FechaSolicitud,
                    'CURP' => $data->CURP,
                    'Nombre' => $data->Nombre,
                    'Paterno' => $data->Paterno,
                    'Materno' => $data->Materno,
                    'Sexo' => $data->Sexo,
                    'FechaNacimiento' => $data->FechaNacimiento,
                    'Calle' => $data->Calle,
                    'NumExt' => $data->NumExt,
                    'NumInt' => $data->NumInt,
                    'Colonia' => $data->Colonia,
                    'CP' => $data->CP,
                    'FechaDocumentacion' => $data->FechaDocumentacion,
                    'isDocumentacionEntrega' => $data->isDocumentacionEntrega,
                    'idUserDocumentacion' => $data->idUserDocumentacion,
                    'isEntregadoOwner' => $data->isEntregadoOwner,
                    'idUserReportaEntrega' => $data->idUserReportaEntrega,
                    'ComentarioEntrega' => $data->ComentarioEntrega,
                    'FechaReportaEntrega' => $data->FechaReportaEntrega,

                    'idMunicipio' => [
                        'id' => $data->IdM,
                        'Municipio' => $data->Municipio,
                        'Region' => $data->Region,
                    ],
                    'idLocalidad' => [
                        'id' => $data->Clave,
                        'Nombre' => $data->Localidad,
                    ],
                    'TelFijo' => $data->TelFijo,
                    'TelCelular' => $data->TelCelular,
                    'CorreoElectronico' => $data->CorreoElectronico,
                    'idStatus' => [
                        'id' => $data->idES,
                        'Clave' => $data->ClaveA,
                        'Estatus' => $data->Estatus,
                    ],
                    'created_at' => $data->created_at,
                    'updated_at' => $data->updated_at,
                    'UserCreated' => [
                        'id' => $data->idE,
                        'email' => $data->emailE,
                        'Nombre' => $data->NombreE,
                        'Paterno' => $data->PaternoE,
                        'Materno' => $data->MaternoE,
                        'idTipoUser' => [
                            'id' => $data->idEA,
                            'TipoUser' => $data->TipoUserEA,
                            'Clave' => $data->ClaveEA,
                        ],
                    ],
                    'UserUpdated' => [
                        'id' => $data->idF,
                        'email' => $data->emailF,
                        'Nombre' => $data->NombreF,
                        'Paterno' => $data->PaternoF,
                        'Materno' => $data->MaternoF,
                        'idTipoUser' => [
                            'id' => $data->idFA,
                            'TipoUser' => $data->TipoUserFA,
                            'Clave' => $data->ClaveFA,
                        ],
                    ],
                    'UserOwned' => [
                        'id' => $data->idO,
                        'email' => $data->emailO,
                        'Nombre' => $data->NombreO,
                        'Paterno' => $data->PaternoO,
                        'Materno' => $data->MaternoO,
                        'idTipoUser' => [
                            'id' => $data->idGO,
                            'TipoUser' => $data->TipoUserGO,
                            'Clave' => $data->ClaveGO,
                        ],
                    ],
                ];

                array_push($array_res, $temp);
            }

            return [
                'success' => true,
                'results' => true,
                'total' => $total,
                'filtros' => $parameters['filtered'],
                'data' => $array_res,
            ];
        } catch (QueryException $e) {
            $errors = [
                'Clave' => '01',
            ];
            $response = [
                'success' => true,
                'results' => false,
                'total' => 0,
                'filtros' => $parameters['filtered'],
                'errors' => $e->getMessage(),
                'message' => 'Campo de consulta incorrecto',
            ];

            return response()->json($response, 200);
        }
    }

    function getValesV2(Request $request)
    {
        $parameters = $request->all();
        //$param_filtro = serialize($parameters);

        $userId = JWTAuth::parseToken()->toUser()->id;

        DB::table('users_filtros')
            ->where('UserCreated', $userId)
            ->where('api', 'getValesV2')
            ->delete();

        $parameters_serializado = serialize($parameters);
        //$parameters = unserialize($parameters_serializado);

        //Insertamos los filtros
        DB::table('users_filtros')->insert([
            'UserCreated' => $userId,
            'Api' => 'getValesV2',
            'Consulta' => $parameters_serializado,
            'created_at' => date('Y-m-d h-m-s'),
        ]);

        try {
            $res = DB::table('vales')
                ->select(
                    'vales.id',
                    DB::raw('LPAD(HEX(vales.id),6,0) as ClaveUnica'),
                    'vales.FolioSolicitud',
                    'vales.FechaSolicitud',
                    'vales.CURP',
                    'vales.Nombre',
                    'vales.Paterno',
                    'vales.Materno',
                    'vales.FechaNacimiento',
                    'vales.Sexo',
                    'vales.Remesa',
                    'vales.Colonia',
                    'vales.Calle',
                    'vales.NumExt',
                    'vales.NumInt',
                    'vales.CP',
                    'vales.CorreoElectronico',
                    'vales.TelFijo',
                    'vales.TelRecados',
                    'vales.Compania',
                    'vales.IngresoPercibido',
                    'vales.OtrosIngresos',
                    'vales.NumeroPersonas',
                    'vales.OcupacionOtro',
                    'vales.Ocupacion',
                    'vales_incidencias.Incidencia',
                    'vales.isEntregado',
                    'vales.entrega_at',
                    'vales.isDocumentacionEntrega',
                    'vales.FechaDocumentacion',
                    'vales.idUserDocumentacion',
                    DB::raw(
                        'concat_ws(UD.Nombre, UD.Paterno, UD.Materno) as UserDocumentacion'
                    ),
                    'UD.email as CelularUserDocumentacion',
                    'vales.idMunicipio',
                    'et_cat_municipio.Id AS IdM',
                    'et_cat_municipio.Nombre AS Municipio',
                    'et_cat_municipio.SubRegion AS Region',
                    'vales.idLocalidad',
                    'et_cat_localidad.Nombre AS Localidad',
                    'vales.TelCelular',
                    'vales.idStatus',
                    'vales_status.id as idES',
                    'vales_status.Estatus',
                    'vales_status.Clave as ClaveA',
                    'vales.created_at',
                    'vales.UserCreated',
                    //Datos Usuario created
                    'users.id as idE',
                    'users.email as emailE',
                    'users.Nombre as NombreE',
                    'users.Paterno as PaternoE',
                    'users.Materno as MaternoE',
                    //Datos Usuario owned
                    'vales.UserOwned',
                    'usersC.id as idO',
                    'usersC.email as emailO',
                    'usersC.Nombre as NombreO',
                    'usersC.Paterno as PaternoO',
                    'usersC.Materno as MaternoO'
                )
                ->leftJoin(
                    'vales_incidencias',
                    'vales_incidencias.id',
                    '=',
                    'vales.idIncidencia'
                )
                ->leftJoin(
                    'et_cat_municipio',
                    'et_cat_municipio.Id',
                    '=',
                    'vales.idMunicipio'
                )
                ->leftJoin(
                    'et_cat_localidad',
                    'et_cat_localidad.Id',
                    '=',
                    'vales.idLocalidad'
                )
                ->leftJoin('vales_status', 'vales_status.id', '=', 'idStatus')
                ->leftJoin('users', 'users.id', '=', 'vales.UserCreated')
                ->leftJoin(
                    'users as UD',
                    'vales.idUserDocumentacion',
                    '=',
                    'UD.id'
                )
                ->leftJoin(
                    'users as usersC',
                    'usersC.id',
                    '=',
                    'vales.UserOwned'
                );
            $flag = 0;

            $flag_ejercicio = false;
            if (isset($parameters['Ejercicio'])) {
                $flag_ejercicio = true;
                $res->where(
                    DB::raw('YEAR(vales.FechaSolicitud)'),
                    '=',
                    $parameters['Ejercicio']
                );
            } else {
                $res->where(
                    DB::raw('YEAR(vales.FechaSolicitud)'),
                    '=',
                    date('Y')
                );
            }

            // if (!isset($parameters['Ejercicio']) && is_null($parameters['Ejercicio'])){
            //     dd('No exuste Ejercicio');

            //     $parameters['Ejercicio'] = date("Y");
            //     $res->where(DB::raw('YEAR(vales.FechaSolicitud)'),'=',$parameters['Ejercicio']);
            // }else
            // {
            //     dd('No exuste Ejercicio');
            //     $res->where(DB::raw('YEAR(vales.FechaSolicitud)'),'=',$parameters['Ejercicio']);
            // }
            if (isset($parameters['Duplicados'])) {
                if ($parameters['Duplicados'] == 1) {
                    if ($flag_ejercicio) {
                        $res->whereRaw(
                            DB::raw(
                                'CURP  in (Select CURP from vales where  YEAR(vales.FechaSolicitud) = ' .
                                    $parameters['Ejercicio'] .
                                    '  group by CURP HAVING count(CURP)>1)'
                            )
                        );
                    } else {
                        $res->whereRaw(
                            DB::raw(
                                'CURP  in (Select CURP from vales group by CURP HAVING count(CURP)>1)'
                            )
                        );
                    }
                }
            }

            if (isset($parameters['Regiones'])) {
                $resMunicipio = DB::table('et_cat_municipio')
                    ->whereIn('SubRegion', $parameters['Regiones'])
                    ->pluck('Id');

                //dd($resMunicipio);

                $res->whereIn('vales.idMunicipio', $resMunicipio);
            } else {
                if (
                    isset($parameters['Propietario']) &&
                    !is_null($parameters['Propietario']) &&
                    $parameters['Propietario'] !== 'All'
                ) {
                    $valor_id = $parameters['Propietario'];
                    $res->where(function ($q) use ($valor_id) {
                        $q
                            ->where('vales.UserCreated', $valor_id)
                            ->orWhere('vales.UserOwned', $valor_id);
                    });
                }
            }

            if (isset($parameters['Folio']) && !is_null($parameters['Folio'])) {
                $valor_id = $parameters['Folio'];
                $res->where(
                    DB::raw('LPAD(HEX(vales.id),6,0)'),
                    'like',
                    '%' . $valor_id . '%'
                );
            }

            if (
                isset($parameters['Ejercicio']) &&
                !is_null($parameters['Ejercicio'])
            ) {
                $valor_id = $parameters['Ejercicio'];
                $res->where(
                    DB::raw('YEAR(vales.FechaSolicitud)'),
                    '=',
                    $valor_id
                );
            } else {
                $res->where(
                    DB::raw('YEAR(vales.FechaSolicitud)'),
                    '=',
                    date('Y')
                );
            }

            if (
                isset($parameters['idMunicipio']) &&
                !is_null($parameters['idMunicipio'])
            ) {
                if (is_array($parameters['idMunicipio'])) {
                    $res->whereIn(
                        'vales.idMunicipio',
                        $parameters['idMunicipio']
                    );
                } else {
                    $res->where(
                        'vales.idMunicipio',
                        '=',
                        $parameters['idMunicipio']
                    );
                }
            }

            if (
                isset($parameters['Colonia']) &&
                !is_null($parameters['Colonia'])
            ) {
                if (is_array($parameters['Colonia'])) {
                    $res->whereIn('vales.Colonia', $parameters['Colonia']);
                } else {
                    $res->where('vales.Colonia', '=', $parameters['Colonia']);
                }
            }
            if (
                isset($parameters['idStatus']) &&
                !is_null($parameters['idStatus'])
            ) {
                if (is_array($parameters['idStatus'])) {
                    $res->whereIn('vales_status.id', $parameters['idStatus']);
                } else {
                    $res->where(
                        'vales_status.id',
                        '=',
                        $parameters['idStatus']
                    );
                }
            }
            if (
                isset($parameters['UserOwned']) &&
                !is_null($parameters['UserOwned'])
            ) {
                if (is_array($parameters['UserOwned'])) {
                    $res->whereIn('vales.UserOwned', $parameters['UserOwned']);
                } else {
                    $res->where(
                        'vales_status.id',
                        '=',
                        $parameters['idStatus']
                    );
                }
            }
            if (
                isset($parameters['Remesa']) &&
                !is_null($parameters['Remesa'])
            ) {
                if (is_array($parameters['Remesa'])) {
                    $flag_null = false;
                    foreach ($parameters['Remesa'] as $dato) {
                        if (strcmp($dato, 'null') === 0) {
                            $flag_null = true;
                        }
                    }

                    if ($flag_null) {
                        $valor_id = $parameters['Remesa'];

                        $res
                            ->where(function ($q) use ($valor_id) {
                                //$q->whereIn('vales.Remesa', $valor_id)
                                $q->WhereNull('vales.Remesa');
                            })
                            ->orderBy('Remesa');
                    } else {
                        //dd($parameters['Remesa']);
                        if ($parameters['Remesa'][0] === '9999') {
                            $res
                                ->whereNotNull('vales.Remesa')
                                ->orderBy('Remesa');
                        } else {
                            $res
                                ->whereIn('vales.Remesa', $parameters['Remesa'])
                                ->orderBy('Remesa');
                        }
                    }
                } else {
                    if (strcmp($parameters['Remesa'], 'null') === 0) {
                        $res->whereNull('vales.Remesa')->orderBy('Remesa');
                    } else {
                        if ($parameters['Remesa'] === '9999') {
                            $res
                                ->whereNotNull('vales.Remesa')
                                ->orderBy('Remesa');
                        } else {
                            $res
                                ->where(
                                    'vales.Remesa',
                                    '=',
                                    $parameters['Remesa']
                                )
                                ->orderBy('Remesa');
                        }
                    }
                }
            }

            $banFechaInicio = 0;
            $banFechaFin = 1;

            if (isset($parameters['filtered'])) {
                for ($i = 0; $i < count($parameters['filtered']); $i++) {
                    if (
                        $parameters['filtered'][$i]['id'] ===
                            'FechaCapturaFin' &&
                        $parameters['filtered'][$i]['value'] !== ''
                    ) {
                        $FiltroCreated_atFin =
                            $parameters['filtered'][$i]['value'];
                        $banFechaFin = 2;
                    } elseif (
                        $parameters['filtered'][$i]['id'] ==
                            'vales.created_at' &&
                        $parameters['filtered'][$i]['value'] !== ''
                    ) {
                        $FiltroCreated_at =
                            $parameters['filtered'][$i]['value'];
                        $banFechaInicio = 1;
                        $banFechaFin = 1;
                    } else {
                        if ($flag == 0) {
                            if (
                                strcmp(
                                    $parameters['filtered'][$i]['id'],
                                    'vales.UserCreated'
                                ) === 0 ||
                                strcmp(
                                    $parameters['filtered'][$i]['id'],
                                    'vales.UserUpdated'
                                ) === 0 ||
                                strcmp(
                                    $parameters['filtered'][$i]['id'],
                                    'vales.UserOwned'
                                ) === 0
                            ) {
                                $res->where(
                                    $parameters['filtered'][$i]['id'],
                                    '=',
                                    $parameters['filtered'][$i]['value']
                                );
                            } else {
                                if (
                                    is_array(
                                        $parameters['filtered'][$i]['value']
                                    )
                                ) {
                                    $res->whereIn(
                                        $parameters['filtered'][$i]['id'],
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    $res->where(
                                        $parameters['filtered'][$i]['id'],
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                }
                            }

                            $flag = 1;
                        }
                    }
                }
            }

            if ($banFechaFin == 2 && $banFechaInicio == 1) {
                $res->whereRaw(
                    "(DATE(vales.created_at) BETWEEN '" .
                        $FiltroCreated_at .
                        "' AND '" .
                        $FiltroCreated_atFin .
                        "')"
                );
            } elseif ($banFechaFin == 1 && $banFechaInicio == 1) {
                $res->whereRaw(
                    "(DATE(vales.created_at) = '" . $FiltroCreated_at . "')"
                );
            }

            //dd(str_replace_array('?', $res->getBindings(), $res->toSql()));

            $page = $parameters['page'];
            $pageSize = $parameters['pageSize'];

            $startIndex = $page * $pageSize;
            if (isset($parameters['sorted'])) {
                for ($i = 0; $i < count($parameters['sorted']); $i++) {
                    if ($parameters['sorted'][$i]['desc'] === true) {
                        $res->orderBy($parameters['sorted'][$i]['id'], 'desc');
                    } else {
                        $res->orderBy($parameters['sorted'][$i]['id'], 'asc');
                    }
                }
            }

            if (
                isset($parameters['NombreCompleto']) &&
                !is_null($parameters['NombreCompleto'])
            ) {
                $filtro_recibido = $parameters['NombreCompleto'];
                $filtro_recibido = str_replace(' ', '%', $filtro_recibido);
                $res->where(
                    DB::raw("
                        CONCAT_WS(' ',
                        vales.Nombre,
                        vales.Paterno,
                        vales.Materno,
                        vales.Paterno,
                        vales.Nombre,
                        vales.Materno,
                        vales.Materno,
                        vales.Nombre,
                        vales.Paterno,
                        vales.Nombre,
                        vales.Materno,
                        vales.Paterno,
                        vales.Paterno,
                        vales.Materno,
                        vales.Nombre,
                        vales.Materno,
                        vales.Paterno,
                        vales.Nombre
                    )"),

                    'like',
                    '%' . $filtro_recibido . '%'
                );
            }

            if (
                isset($parameters['NombreOwner']) &&
                !is_null($parameters['NombreOwner'])
            ) {
                $filtro_recibido = $parameters['NombreOwner'];
                $filtro_recibido = str_replace(' ', '%', $filtro_recibido);
                $res->where(
                    DB::raw("
                        CONCAT_WS(' ',
                        usersC.Nombre,
                        usersC.Paterno,
                        usersC.Materno,
                        usersC.Paterno,
                        usersC.Nombre,
                        usersC.Materno,
                        usersC.Materno,
                        usersC.Nombre,
                        usersC.Paterno,
                        usersC.Nombre,
                        usersC.Materno,
                        usersC.Paterno,
                        usersC.Paterno,
                        usersC.Materno,
                        usersC.Nombre,
                        usersC.Materno,
                        usersC.Paterno,
                        usersC.Nombre)
                    "),

                    'like',
                    '%' . $filtro_recibido . '%'
                );
            }

            if (
                isset($parameters['NombreCreated']) &&
                !is_null($parameters['NombreCreated'])
            ) {
                $filtro_recibido = $parameters['NombreCreated'];
                $filtro_recibido = str_replace(' ', '%', $filtro_recibido);
                $res->where(
                    DB::raw("
                        CONCAT_WS(' ',
                        users.Nombre,
                        users.Paterno,
                        users.Materno,
                        users.Paterno,
                        users.Nombre,
                        users.Materno,
                        users.Materno,
                        users.Nombre,
                        users.Paterno,
                        users.Nombre,
                        users.Materno,
                        users.Paterno,
                        users.Paterno,
                        users.Materno,
                        users.Nombre,
                        users.Materno,
                        users.Paterno,
                        users.Nombre
                    )"),

                    'like',
                    '%' . $filtro_recibido . '%'
                );
            }

            $total = $res->count();
            $res = $res
                ->offset($startIndex)
                ->take($pageSize)
                ->get();
            //->toSql();
            //dd($res);

            $parameters_serializado = serialize($parameters);
            //$array = unserialize($parameters_serializado);
            $user = auth()->user();
            $filtro_usuario = VNegociosFiltros::where('idUser', '=', $user->id)
                ->where('api', '=', 'getValesV2')
                ->first();
            if ($filtro_usuario) {
                $filtro_usuario->parameters = $parameters_serializado;
                $filtro_usuario->updated_at = time::now();
                $filtro_usuario->update();
            } else {
                $objeto_nuevo = new VNegociosFiltros();
                $objeto_nuevo->api = 'getValesV2';
                $objeto_nuevo->idUser = $user->id;
                $objeto_nuevo->parameters = $parameters_serializado;
                $objeto_nuevo->save();
            }
            $array_res = [];

            if ($total == 0) {
                return [
                    'success' => true,
                    'results' => true,
                    'total' => $total,
                    'filtros' => $parameters['filtered'],
                    'data' => $array_res,
                ];
            }

            $temp = [];
            foreach ($res as $data) {
                $temp = [
                    'id' => $data->id,
                    'Incidencia' => $data->Incidencia,
                    'ClaveUnica' => $data->ClaveUnica,
                    'FolioSolicitud' => $data->FolioSolicitud,
                    'FechaSolicitud' => $data->FechaSolicitud,
                    'CURP' => $data->CURP,
                    'Nombre' => $data->Nombre,
                    'Paterno' => $data->Paterno,
                    'Materno' => $data->Materno,
                    'FechaNacimiento' => $data->FechaNacimiento,
                    'Sexo' => $data->Sexo,
                    'Colonia' => $data->Colonia,
                    'Calle' => $data->Calle,
                    'NumExt' => $data->NumExt,
                    'NumInt' => $data->NumInt,
                    'CP' => $data->CP,
                    'TelFijo' => $data->TelFijo,
                    'TelRecados' => $data->TelRecados,
                    'Compania' => $data->Compania,
                    'IngresoPercibido' => $data->IngresoPercibido,
                    'OtrosIngresos' => $data->OtrosIngresos,
                    'NumeroPersonas' => $data->NumeroPersonas,
                    'OcupacionOtro' => $data->OcupacionOtro,
                    'Ocupacion' => $data->Ocupacion,
                    'CorreoElectronico' => $data->CorreoElectronico,
                    'isEntregado' => $data->isEntregado,
                    'FechaEntregaVale' => $data->entrega_at,
                    'isDocumentacionEntrega' => $data->isDocumentacionEntrega,
                    'FechaDocumentacion' => $data->FechaDocumentacion,
                    'idUserDocumentacion' => $data->idUserDocumentacion,
                    'UserDocumentacion' => $data->UserDocumentacion,
                    'CelularUserDocumentacion' =>
                        $data->CelularUserDocumentacion,
                    'Remesa' => $data->Remesa,
                    'idLocalidad' => [
                        'id' => $data->idLocalidad,
                        'Nombre' => $data->Localidad,
                    ],
                    'idMunicipio' => [
                        'id' => $data->IdM,
                        'Municipio' => $data->Municipio,
                        'Region' => $data->Region,
                    ],

                    'TelCelular' => $data->TelCelular,

                    'idStatus' => [
                        'id' => $data->idES,
                        'Clave' => $data->ClaveA,
                        'Estatus' => $data->Estatus,
                    ],
                    'created_at' => $data->created_at,
                    'UserCreated' => [
                        'id' => $data->idE,
                        'email' => $data->emailE,
                        'Nombre' => $data->NombreE,
                        'Paterno' => $data->PaternoE,
                        'Materno' => $data->MaternoE,
                    ],
                    'UserOwned' => [
                        'id' => $data->idO,
                        'email' => $data->emailO,
                        'Nombre' => $data->NombreO,
                        'Paterno' => $data->PaternoO,
                        'Materno' => $data->MaternoO,
                    ],
                ];

                array_push($array_res, $temp);
            }

            return [
                'success' => true,
                'results' => true,
                'total' => $total,
                'filtros' => $parameters['filtered'],
                'data' => $array_res,
            ];
        } catch (QueryException $e) {
            $errors = [
                'Clave' => '01',
            ];
            $response = [
                'success' => true,
                'results' => false,
                'total' => 0,
                'filtros' => $parameters['filtered'],
                'errors' => $e->getMessage(),
                'message' => 'Campo de consulta incorrecto',
            ];

            return response()->json($response, 200);
        }
    }
    function getValesV2Old(Request $request)
    {
        $parameters = $request->all();
        //$param_filtro = serialize($parameters);
        try {
            $res = DB::table('vales')
                ->select(
                    'vales.id',
                    DB::raw('LPAD(HEX(vales.id),6,0) as ClaveUnica'),
                    'vales.TelRecados',
                    'vales.Compania',
                    'vales.TelFijo',
                    'vales.FolioSolicitud',
                    'vales.FechaSolicitud',
                    'vales.CURP',
                    'vales.Nombre',
                    'vales.Paterno',
                    'vales.Materno',
                    'vales.Sexo',
                    'vales.FechaNacimiento',
                    'vales.Calle',
                    'vales.NumExt',
                    'vales.NumInt',
                    'vales.Colonia',
                    'vales.CP',
                    'vales.idMunicipio',
                    'et_cat_municipio.Id AS IdM',
                    'et_cat_municipio.Nombre AS Municipio',
                    'et_cat_municipio.SubRegion AS Region',
                    'vales.idLocalidad',
                    'et_cat_localidad.Id AS Clave',
                    'et_cat_localidad.Nombre AS Localidad',
                    'vales.TelFijo',
                    'vales.TelCelular',
                    'vales.isEntregado',
                    'vales.entrega_at',
                    'vales.CorreoElectronico',
                    'vales.FechaDocumentacion',
                    'vales.isDocumentacionEntrega',
                    'vales.idUserDocumentacion',
                    'vales.isEntregadoOwner',
                    'vales.idUserReportaEntrega',
                    'vales.ComentarioEntrega',
                    'vales.FechaReportaEntrega',
                    'vales.idStatus',
                    'vales_status.id as idES',
                    'vales_status.Estatus',
                    'vales_status.Clave as ClaveA',
                    'vales.created_at',
                    'vales.updated_at',
                    'vales.UserCreated',
                    //Datos Usuario created
                    'users.id as idE',
                    'users.email as emailE',
                    'users.Nombre as NombreE',
                    'users.Paterno as PaternoE',
                    'users.Materno as MaternoE',
                    'users.idTipoUser',
                    'cat_usertipo.id as idEA',
                    'cat_usertipo.TipoUser as TipoUserEA',
                    'cat_usertipo.Clave as ClaveEA',
                    'vales.UserUpdated',
                    //Datos Usuario updated
                    'usersB.id as idF',
                    'usersB.email as emailF',
                    'usersB.Nombre as NombreF',
                    'usersB.Paterno as PaternoF',
                    'usersB.Materno as MaternoF',
                    'usersB.idTipoUser',
                    'cat_usertipoB.id as idFA',
                    'cat_usertipoB.TipoUser as TipoUserFA',
                    'cat_usertipoB.Clave as ClaveFA',
                    'vales.UserOwned',
                    //Datos Usuario owned
                    'usersC.id as idO',
                    'usersC.email as emailO',
                    'usersC.Nombre as NombreO',
                    'usersC.Paterno as PaternoO',
                    'usersC.Materno as MaternoO',
                    'usersC.idTipoUser',
                    'cat_usertipoC.id as idGO',
                    'cat_usertipoC.TipoUser as TipoUserGO',
                    'cat_usertipoC.Clave as ClaveGO'
                )
                ->leftJoin(
                    'et_cat_municipio',
                    'et_cat_municipio.Id',
                    '=',
                    'vales.idMunicipio'
                )
                ->leftJoin(
                    'et_cat_localidad',
                    'et_cat_localidad.Id',
                    '=',
                    'vales.idLocalidad'
                )
                ->leftJoin('vales_status', 'vales_status.id', '=', 'idStatus')
                ->leftJoin('users', 'users.id', '=', 'vales.UserCreated')
                ->leftJoin(
                    'cat_usertipo',
                    'cat_usertipo.id',
                    '=',
                    'users.idTipoUser'
                )
                ->leftJoin(
                    'users as usersB',
                    'usersB.id',
                    '=',
                    'vales.UserUpdated'
                )
                ->leftJoin(
                    'cat_usertipo as cat_usertipoB',
                    'cat_usertipoB.id',
                    '=',
                    'usersB.idTipoUser'
                )
                ->leftJoin(
                    'users as usersC',
                    'usersC.id',
                    '=',
                    'vales.UserOwned'
                )
                ->leftJoin(
                    'users as usersCretaed',
                    'usersCretaed.id',
                    '=',
                    'vales.UserCreated'
                )
                ->leftJoin(
                    'cat_usertipo as cat_usertipoC',
                    'cat_usertipoC.id',
                    '=',
                    'usersC.idTipoUser'
                );

            $flag = 0;

            if (
                isset($parameters['Regiones']) &&
                !is_null($parameters['Regiones'])
            ) {
                if (is_array($parameters['Regiones'])) {
                    $res->orwhereIn(
                        'et_cat_municipio.SubRegion',
                        $parameters['Regiones']
                    );
                    $flagRegion = 1;
                } elseif (
                    isset($parameters['UserOwned']) &&
                    !is_null($parameters['UserOwned'])
                ) {
                    if (is_array($parameters['UserOwned'])) {
                        $res->whereIn(
                            'vales.UserOwned',
                            $parameters['UserOwned']
                        );
                    } else {
                        $res->where(
                            'vales.UserOwned',
                            '=',
                            $parameters['UserOwned']
                        );
                    }
                }
            } else {
                if (
                    isset($parameters['Propietario']) &&
                    !is_null($parameters['Propietario']) &&
                    $parameters['Propietario'] !== 'All'
                ) {
                    $valor_id = $parameters['Propietario'];
                    $res->where(function ($q) use ($valor_id) {
                        $q
                            ->where('vales.UserCreated', $valor_id)
                            ->orWhere('vales.UserOwned', $valor_id);
                    });
                }
            }

            if (isset($parameters['Folio']) && !is_null($parameters['Folio'])) {
                $valor_id = $parameters['Folio'];
                $res->where(
                    DB::raw('LPAD(HEX(vales.id),6,0)'),
                    'like',
                    '%' . $valor_id . '%'
                );
            }

            /*if(isset($parameters['Regiones'])){
                if(is_array ($parameters['Regiones'])){
                    $res->whereIn('et_cat_municipio.SubRegion',$parameters['Regiones']);
                }else{
                    $res->where('et_cat_municipio.SubRegion','=',$parameters['Regiones']);
                }    
            }*/

            //$res=$res->toSql();

            if (
                isset($parameters['idMunicipio']) &&
                !is_null($parameters['idMunicipio'])
            ) {
                if (is_array($parameters['idMunicipio'])) {
                    $res->whereIn(
                        'vales.idMunicipio',
                        $parameters['idMunicipio']
                    );
                } else {
                    $res->where(
                        'vales.idMunicipio',
                        '=',
                        $parameters['idMunicipio']
                    );
                }
            }
            if (
                isset($parameters['Colonia']) &&
                !is_null($parameters['Colonia'])
            ) {
                if (is_array($parameters['Colonia'])) {
                    $res->whereIn('vales.Colonia', $parameters['Colonia']);
                } else {
                    $res->where('vales.Colonia', '=', $parameters['Colonia']);
                }
            }
            if (
                isset($parameters['idStatus']) &&
                !is_null($parameters['idStatus'])
            ) {
                if (is_array($parameters['idStatus'])) {
                    $res->whereIn('vales_status.id', $parameters['idStatus']);
                } else {
                    $res->where(
                        'vales_status.id',
                        '=',
                        $parameters['idStatus']
                    );
                }
            }
            if (
                isset($parameters['UserOwned']) &&
                !is_null($parameters['UserOwned'])
            ) {
                if (is_array($parameters['UserOwned'])) {
                    $res->whereIn('vales.UserOwned', $parameters['UserOwned']);
                } else {
                    $res->where(
                        'vales_status.id',
                        '=',
                        $parameters['idStatus']
                    );
                }
            }
            if (
                isset($parameters['Remesa']) &&
                !is_null($parameters['Remesa'])
            ) {
                if (is_array($parameters['Remesa'])) {
                    $flag_null = false;
                    foreach ($parameters['Remesa'] as $dato) {
                        if (strcmp($dato, 'null') === 0) {
                            $flag_null = true;
                        }
                    }
                    if ($flag_null) {
                        $valor_id = $parameters['Remesa'];
                        $res
                            ->where(function ($q) use ($valor_id) {
                                //$q->whereIn('vales.Remesa', $valor_id)
                                $q->WhereNull('vales.Remesa');
                            })
                            ->orderBy('Remesa');
                    } else {
                        $res
                            ->whereIn('vales.Remesa', $parameters['Remesa'])
                            ->orderBy('Remesa');
                    }
                } else {
                    if (strcmp($parameters['Remesa'], 'null') === 0) {
                        $res->whereNull('vales.Remesa')->orderBy('Remesa');
                    } else {
                        $res
                            ->where('vales.Remesa', '=', $parameters['Remesa'])
                            ->orderBy('Remesa');
                    }
                }
            }

            $banFechaInicio = 0;
            $banFechaFin = 1;
            //FechaCapturaFin vales.created_at
            if (isset($parameters['filtered'])) {
                for ($i = 0; $i < count($parameters['filtered']); $i++) {
                    if (
                        $parameters['filtered'][$i]['id'] ==
                            'FechaCapturaFin' &&
                        $parameters['filtered'][$i]['value'] !== ''
                    ) {
                        $FiltroCreated_atFin =
                            $parameters['filtered'][$i]['value'];
                        $banFechaFin = 2;
                    } elseif (
                        $parameters['filtered'][$i]['id'] ==
                            'vales.created_at' &&
                        $parameters['filtered'][$i]['value'] !== ''
                    ) {
                        $FiltroCreated_at =
                            $parameters['filtered'][$i]['value'];
                        $banFechaInicio = 1;
                        $banFechaFin = 1;
                    } else {
                        if ($flag == 0) {
                            if (
                                $parameters['filtered'][$i]['id'] &&
                                strpos(
                                    $parameters['filtered'][$i]['id'],
                                    'id'
                                ) !== false
                            ) {
                                if (
                                    is_array(
                                        $parameters['filtered'][$i]['value']
                                    )
                                ) {
                                    $res->whereIn(
                                        $parameters['filtered'][$i]['id'],
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    $res->where(
                                        $parameters['filtered'][$i]['id'],
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                }
                            } else {
                                if (
                                    strcmp(
                                        $parameters['filtered'][$i]['id'],
                                        'vales.UserCreated'
                                    ) === 0 ||
                                    strcmp(
                                        $parameters['filtered'][$i]['id'],
                                        'vales.UserUpdated'
                                    ) === 0 ||
                                    strcmp(
                                        $parameters['filtered'][$i]['id'],
                                        'vales.UserOwned'
                                    ) === 0
                                ) {
                                    $res->where(
                                        $parameters['filtered'][$i]['id'],
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    if (
                                        strpos(
                                            $parameters['filtered'][$i]['id'],
                                            'is'
                                        ) !== false
                                    ) {
                                        $res->where(
                                            $parameters['filtered'][$i]['id'],
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->where(
                                            $parameters['filtered'][$i]['id'],
                                            'LIKE',
                                            '%' .
                                                $parameters['filtered'][$i][
                                                    'value'
                                                ] .
                                                '%'
                                        );
                                    }
                                }
                            }
                            $flag = 1;
                        } else {
                            if ($parameters['tipo'] == 'and') {
                                if (
                                    $parameters['filtered'][$i]['id'] &&
                                    strpos(
                                        $parameters['filtered'][$i]['id'],
                                        'id'
                                    ) !== false
                                ) {
                                    if (
                                        is_array(
                                            $parameters['filtered'][$i]['value']
                                        )
                                    ) {
                                        $res->whereIn(
                                            $parameters['filtered'][$i]['id'],
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->where(
                                            $parameters['filtered'][$i]['id'],
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    }
                                } else {
                                    if (
                                        strcmp(
                                            $parameters['filtered'][$i]['id'],
                                            'vales.UserCreated'
                                        ) === 0 ||
                                        strcmp(
                                            $parameters['filtered'][$i]['id'],
                                            'vales.UserUpdated'
                                        ) === 0 ||
                                        strcmp(
                                            $parameters['filtered'][$i]['id'],
                                            'vales.UserOwned'
                                        ) === 0
                                    ) {
                                        $res->where(
                                            $parameters['filtered'][$i]['id'],
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        if (
                                            strpos(
                                                $parameters['filtered'][$i][
                                                    'id'
                                                ],
                                                'is'
                                            ) !== false
                                        ) {
                                            $res->where(
                                                $parameters['filtered'][$i][
                                                    'id'
                                                ],
                                                '=',
                                                $parameters['filtered'][$i][
                                                    'value'
                                                ]
                                            );
                                        } else {
                                            $res->where(
                                                $parameters['filtered'][$i][
                                                    'id'
                                                ],
                                                'LIKE',
                                                '%' .
                                                    $parameters['filtered'][$i][
                                                        'value'
                                                    ] .
                                                    '%'
                                            );
                                        }
                                    }
                                }
                            } else {
                                if (
                                    $parameters['filtered'][$i]['id'] &&
                                    strpos(
                                        $parameters['filtered'][$i]['id'],
                                        'id'
                                    ) !== false
                                ) {
                                    if (
                                        is_array(
                                            $parameters['filtered'][$i]['value']
                                        )
                                    ) {
                                        $res->orWhereIn(
                                            $parameters['filtered'][$i]['id'],
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->orWhere(
                                            $parameters['filtered'][$i]['id'],
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    }
                                } else {
                                    if (
                                        strcmp(
                                            $parameters['filtered'][$i]['id'],
                                            'vales.UserCreated'
                                        ) === 0 ||
                                        strcmp(
                                            $parameters['filtered'][$i]['id'],
                                            'vales.UserUpdated'
                                        ) === 0
                                    ) {
                                        $res->orWhere(
                                            $parameters['filtered'][$i]['id'],
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        if (
                                            strpos(
                                                $parameters['filtered'][$i][
                                                    'id'
                                                ],
                                                'is'
                                            ) !== false
                                        ) {
                                            $res->orWhere(
                                                $parameters['filtered'][$i][
                                                    'id'
                                                ],
                                                '=',
                                                $parameters['filtered'][$i][
                                                    'value'
                                                ]
                                            );
                                        } else {
                                            $res->orWhere(
                                                $parameters['filtered'][$i][
                                                    'id'
                                                ],
                                                'LIKE',
                                                '%' .
                                                    $parameters['filtered'][$i][
                                                        'value'
                                                    ] .
                                                    '%'
                                            );
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            if ($banFechaFin == 2 && $banFechaInicio == 1) {
                $res->whereRaw(
                    "(DATE(vales.created_at) BETWEEN '" .
                        $FiltroCreated_at .
                        "' AND '" .
                        $FiltroCreated_atFin .
                        "')"
                );
            } elseif ($banFechaFin == 1 && $banFechaInicio == 1) {
                $res->whereRaw(
                    "(DATE(vales.created_at) = '" . $FiltroCreated_at . "')"
                );
            }

            $page = $parameters['page'];
            $pageSize = $parameters['pageSize'];

            $startIndex = $page * $pageSize;
            if (isset($parameters['sorted'])) {
                for ($i = 0; $i < count($parameters['sorted']); $i++) {
                    if ($parameters['sorted'][$i]['desc'] === true) {
                        $res->orderBy($parameters['sorted'][$i]['id'], 'desc');
                    } else {
                        $res->orderBy($parameters['sorted'][$i]['id'], 'asc');
                    }
                }
            }

            if (
                isset($parameters['NombreCompleto']) &&
                !is_null($parameters['NombreCompleto'])
            ) {
                $filtro_recibido = $parameters['NombreCompleto'];
                $filtro_recibido = str_replace(' ', '', $filtro_recibido);
                $res->where(
                    DB::raw("
                    REPLACE(
                    CONCAT(
                        vales.Nombre,
                        vales.Paterno,
                        vales.Materno,
                        vales.Paterno,
                        vales.Nombre,
                        vales.Materno,
                        vales.Materno,
                        vales.Nombre,
                        vales.Paterno,
                        vales.Nombre,
                        vales.Materno,
                        vales.Paterno,
                        vales.Paterno,
                        vales.Materno,
                        vales.Nombre,
                        vales.Materno,
                        vales.Paterno,
                        vales.Nombre
                    ), ' ', '')"),

                    'like',
                    '%' . $filtro_recibido . '%'
                );
            }

            if (
                isset($parameters['NombreOwner']) &&
                !is_null($parameters['NombreOwner'])
            ) {
                $filtro_recibido = $parameters['NombreOwner'];
                $filtro_recibido = str_replace(' ', '', $filtro_recibido);
                $res->where(
                    DB::raw("
                    REPLACE(
                    CONCAT(
                        usersC.Nombre,
                        usersC.Paterno,
                        usersC.Materno,
                        usersC.Paterno,
                        usersC.Nombre,
                        usersC.Materno,
                        usersC.Materno,
                        usersC.Nombre,
                        usersC.Paterno,
                        usersC.Nombre,
                        usersC.Materno,
                        usersC.Paterno,
                        usersC.Paterno,
                        usersC.Materno,
                        usersC.Nombre,
                        usersC.Materno,
                        usersC.Paterno,
                        usersC.Nombre
                    ), ' ', '')"),

                    'like',
                    '%' . $filtro_recibido . '%'
                );
            }

            if (
                isset($parameters['NombreCreated']) &&
                !is_null($parameters['NombreCreated'])
            ) {
                $filtro_recibido = $parameters['NombreCreated'];
                $filtro_recibido = str_replace(' ', '', $filtro_recibido);
                $res->where(
                    DB::raw("
                    REPLACE(
                    CONCAT(
                        usersCretaed.Nombre,
                        usersCretaed.Paterno,
                        usersCretaed.Materno,
                        usersCretaed.Paterno,
                        usersCretaed.Nombre,
                        usersCretaed.Materno,
                        usersCretaed.Materno,
                        usersCretaed.Nombre,
                        usersCretaed.Paterno,
                        usersCretaed.Nombre,
                        usersCretaed.Materno,
                        usersCretaed.Paterno,
                        usersCretaed.Paterno,
                        usersCretaed.Materno,
                        usersCretaed.Nombre,
                        usersCretaed.Materno,
                        usersCretaed.Paterno,
                        usersCretaed.Nombre
                    ), ' ', '')"),

                    'like',
                    '%' . $filtro_recibido . '%'
                );
            }

            $total = $res->count();
            $res = $res
                ->offset($startIndex)
                ->take($pageSize)
                ->get();
            //->toSql();
            //dd($res);

            $parameters_serializado = serialize($parameters);
            //$array = unserialize($parameters_serializado);
            $user = auth()->user();
            $filtro_usuario = VNegociosFiltros::where('idUser', '=', $user->id)
                ->where('api', '=', 'getValesV2')
                ->first();
            if ($filtro_usuario) {
                $filtro_usuario->parameters = $parameters_serializado;
                $filtro_usuario->update();
            } else {
                $objeto_nuevo = new VNegociosFiltros();
                $objeto_nuevo->api = 'getValesV2';
                $objeto_nuevo->idUser = $user->id;
                $objeto_nuevo->parameters = $parameters_serializado;
                $objeto_nuevo->save();
            }
            $array_res = [];

            if ($total == 0) {
                return [
                    'success' => true,
                    'results' => true,
                    'total' => $total,
                    'filtros' => $parameters['filtered'],
                    'data' => $array_res,
                ];
            }

            $temp = [];
            foreach ($res as $data) {
                $temp = [
                    'id' => $data->id,
                    'ClaveUnica' => $data->ClaveUnica,
                    'TelRecados' => $data->TelRecados,
                    'Compania' => $data->Compania,
                    'TelFijo' => $data->TelFijo,
                    'FolioSolicitud' => $data->FolioSolicitud,
                    'FechaSolicitud' => $data->FechaSolicitud,
                    'CURP' => $data->CURP,
                    'Nombre' => $data->Nombre,
                    'Paterno' => $data->Paterno,
                    'Materno' => $data->Materno,
                    'Sexo' => $data->Sexo,
                    'FechaNacimiento' => $data->FechaNacimiento,
                    'Calle' => $data->Calle,
                    'NumExt' => $data->NumExt,
                    'NumInt' => $data->NumInt,
                    'Colonia' => $data->Colonia,
                    'CP' => $data->CP,
                    'FechaDocumentacion' => $data->FechaDocumentacion,
                    'isDocumentacionEntrega' => $data->isDocumentacionEntrega,
                    'idUserDocumentacion' => $data->idUserDocumentacion,
                    'isEntregadoOwner' => $data->isEntregadoOwner,
                    'idUserReportaEntrega' => $data->idUserReportaEntrega,
                    'ComentarioEntrega' => $data->ComentarioEntrega,
                    'FechaReportaEntrega' => $data->FechaReportaEntrega,

                    'idMunicipio' => [
                        'id' => $data->IdM,
                        'Municipio' => $data->Municipio,
                        'Region' => $data->Region,
                    ],
                    'idLocalidad' => [
                        'id' => $data->Clave,
                        'Nombre' => $data->Localidad,
                    ],
                    'TelFijo' => $data->TelFijo,
                    'TelCelular' => $data->TelCelular,
                    'CorreoElectronico' => $data->CorreoElectronico,
                    'idStatus' => [
                        'id' => $data->idES,
                        'Clave' => $data->ClaveA,
                        'Estatus' => $data->Estatus,
                    ],
                    'created_at' => $data->created_at,
                    'updated_at' => $data->updated_at,
                    'UserCreated' => [
                        'id' => $data->idE,
                        'email' => $data->emailE,
                        'Nombre' => $data->NombreE,
                        'Paterno' => $data->PaternoE,
                        'Materno' => $data->MaternoE,
                        'idTipoUser' => [
                            'id' => $data->idEA,
                            'TipoUser' => $data->TipoUserEA,
                            'Clave' => $data->ClaveEA,
                        ],
                    ],
                    'UserUpdated' => [
                        'id' => $data->idF,
                        'email' => $data->emailF,
                        'Nombre' => $data->NombreF,
                        'Paterno' => $data->PaternoF,
                        'Materno' => $data->MaternoF,
                        'idTipoUser' => [
                            'id' => $data->idFA,
                            'TipoUser' => $data->TipoUserFA,
                            'Clave' => $data->ClaveFA,
                        ],
                    ],
                    'UserOwned' => [
                        'id' => $data->idO,
                        'email' => $data->emailO,
                        'Nombre' => $data->NombreO,
                        'Paterno' => $data->PaternoO,
                        'Materno' => $data->MaternoO,
                        'idTipoUser' => [
                            'id' => $data->idGO,
                            'TipoUser' => $data->TipoUserGO,
                            'Clave' => $data->ClaveGO,
                        ],
                    ],
                ];

                array_push($array_res, $temp);
            }

            return [
                'success' => true,
                'results' => true,
                'total' => $total,
                'filtros' => $parameters['filtered'],
                'data' => $array_res,
            ];
        } catch (QueryException $e) {
            $errors = [
                'Clave' => '01',
            ];
            $response = [
                'success' => true,
                'results' => false,
                'total' => 0,
                'filtros' => $parameters['filtered'],
                'errors' => $e->getMessage(),
                'message' => 'Campo de consulta incorrecto',
            ];

            return response()->json($response, 200);
        }
    }
    function getValesNotIn(Request $request)
    {
        $parameters = $request->all();

        try {
            $res_1 = DB::table('vales_solicitudes')
                ->select('vales_solicitudes.idSolicitud')
                ->toSql();

            $res = DB::table('vales')
                ->select(
                    'vales.id',
                    DB::raw('LPAD(HEX(vales.id),6,0) as ClaveUnica'),
                    'vales.TelRecados',
                    'vales.Compania',
                    'vales.TelFijo',
                    'vales.FolioSolicitud',
                    'vales.FechaSolicitud',
                    'vales.CURP',
                    'vales.Nombre',
                    'vales.Paterno',
                    'vales.Materno',
                    'vales.Sexo',
                    'vales.IngresoPercibido',
                    'vales.OtrosIngresos',
                    'vales.NumeroPersonas',
                    'vales.OcupacionOtro',
                    'vales.Ocupacion',
                    'vales.FechaNacimiento',
                    'vales.Calle',
                    'vales.NumExt',
                    'vales.NumInt',
                    'vales.Colonia',
                    'vales.CP',
                    'vales.idMunicipio',
                    'et_cat_municipio.Id AS IdM',
                    'et_cat_municipio.Nombre AS Municipio',
                    'et_cat_municipio.SubRegion AS Region',
                    'vales.idLocalidad',
                    'et_cat_localidad.Id AS Clave',
                    'et_cat_localidad.Nombre AS Localidad',
                    'vales.TelFijo',
                    'vales.TelCelular',
                    'vales.CorreoElectronico',
                    'vales.idStatus',
                    'vales_status.id as idES',
                    'vales_status.Estatus',
                    'vales_status.Clave as ClaveA',
                    'vales.created_at',
                    'vales.updated_at',
                    'vales.UserCreated',
                    //Datos Usuario created
                    'users.id as idE',
                    'users.email as emailE',
                    'users.Nombre as NombreE',
                    'users.Paterno as PaternoE',
                    'users.Materno as MaternoE',
                    'users.idTipoUser',
                    'cat_usertipo.id as idEA',
                    'cat_usertipo.TipoUser as TipoUserEA',
                    'cat_usertipo.Clave as ClaveEA',
                    'vales.UserUpdated',
                    //Datos Usuario updated
                    'usersB.id as idF',
                    'usersB.email as emailF',
                    'usersB.Nombre as NombreF',
                    'usersB.Paterno as PaternoF',
                    'usersB.Materno as MaternoF',
                    'usersB.idTipoUser',
                    'cat_usertipoB.id as idFA',
                    'cat_usertipoB.TipoUser as TipoUserFA',
                    'cat_usertipoB.Clave as ClaveFA',
                    'vales.UserOwned',
                    //Datos Usuario owned
                    'usersC.id as idO',
                    'usersC.email as emailO',
                    'usersC.Nombre as NombreO',
                    'usersC.Paterno as PaternoO',
                    'usersC.Materno as MaternoO',
                    'usersC.idTipoUser',
                    'cat_usertipoC.id as idGO',
                    'cat_usertipoC.TipoUser as TipoUserGO',
                    'cat_usertipoC.Clave as ClaveGO'
                )
                ->leftJoin(
                    'et_cat_municipio',
                    'et_cat_municipio.Id',
                    '=',
                    'vales.idMunicipio'
                )
                ->leftJoin(
                    'et_cat_localidad',
                    'et_cat_localidad.Id',
                    '=',
                    'vales.idLocalidad'
                )
                ->leftJoin('vales_status', 'vales_status.id', '=', 'idStatus')
                ->leftJoin('users', 'users.id', '=', 'vales.UserCreated')
                ->leftJoin(
                    'cat_usertipo',
                    'cat_usertipo.id',
                    '=',
                    'users.idTipoUser'
                )
                ->leftJoin(
                    'users as usersB',
                    'usersB.id',
                    '=',
                    'vales.UserUpdated'
                )
                ->leftJoin(
                    'cat_usertipo as cat_usertipoB',
                    'cat_usertipoB.id',
                    '=',
                    'usersB.idTipoUser'
                )
                ->leftJoin(
                    'users as usersC',
                    'usersC.id',
                    '=',
                    'vales.UserOwned'
                )
                ->leftJoin(
                    'users as usersCretaed',
                    'usersCretaed.id',
                    '=',
                    'vales.UserCreated'
                )
                ->leftJoin(
                    'cat_usertipo as cat_usertipoC',
                    'cat_usertipoC.id',
                    '=',
                    'usersC.idTipoUser'
                )
                ->where('vales.idStatus', '=', 5)
                //->whereNotIn('vales.id',DB::raw($res_1));
                ->whereRaw('vales.id NOT IN(' . $res_1 . ')');

            $flag = 0;
            if (isset($parameters['filtered'])) {
                for ($i = 0; $i < count($parameters['filtered']); $i++) {
                    if ($flag == 0) {
                        if (
                            $parameters['filtered'][$i]['id'] &&
                            strpos($parameters['filtered'][$i]['id'], 'id') !==
                                false
                        ) {
                            if (
                                is_array($parameters['filtered'][$i]['value'])
                            ) {
                                $res->whereIn(
                                    $parameters['filtered'][$i]['id'],
                                    $parameters['filtered'][$i]['value']
                                );
                            } else {
                                $res->where(
                                    $parameters['filtered'][$i]['id'],
                                    '=',
                                    $parameters['filtered'][$i]['value']
                                );
                            }
                        } else {
                            if (
                                strcmp(
                                    $parameters['filtered'][$i]['id'],
                                    'vales.UserCreated'
                                ) === 0 ||
                                strcmp(
                                    $parameters['filtered'][$i]['id'],
                                    'vales.UserUpdated'
                                ) === 0 ||
                                strcmp(
                                    $parameters['filtered'][$i]['id'],
                                    'vales.UserOwned'
                                ) === 0
                            ) {
                                $res->where(
                                    $parameters['filtered'][$i]['id'],
                                    '=',
                                    $parameters['filtered'][$i]['value']
                                );
                            } else {
                                $res->where(
                                    $parameters['filtered'][$i]['id'],
                                    'LIKE',
                                    '%' .
                                        $parameters['filtered'][$i]['value'] .
                                        '%'
                                );
                            }
                        }
                        $flag = 1;
                    } else {
                        if ($parameters['tipo'] == 'and') {
                            if (
                                $parameters['filtered'][$i]['id'] &&
                                strpos(
                                    $parameters['filtered'][$i]['id'],
                                    'id'
                                ) !== false
                            ) {
                                if (
                                    is_array(
                                        $parameters['filtered'][$i]['value']
                                    )
                                ) {
                                    $res->whereIn(
                                        $parameters['filtered'][$i]['id'],
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    $res->where(
                                        $parameters['filtered'][$i]['id'],
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                }
                            } else {
                                if (
                                    strcmp(
                                        $parameters['filtered'][$i]['id'],
                                        'vales.UserCreated'
                                    ) === 0 ||
                                    strcmp(
                                        $parameters['filtered'][$i]['id'],
                                        'vales.UserUpdated'
                                    ) === 0 ||
                                    strcmp(
                                        $parameters['filtered'][$i]['id'],
                                        'vales.UserOwned'
                                    ) === 0
                                ) {
                                    $res->where(
                                        $parameters['filtered'][$i]['id'],
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    $res->where(
                                        $parameters['filtered'][$i]['id'],
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                }
                            }
                        } else {
                            if (
                                $parameters['filtered'][$i]['id'] &&
                                strpos(
                                    $parameters['filtered'][$i]['id'],
                                    'id'
                                ) !== false
                            ) {
                                if (
                                    is_array(
                                        $parameters['filtered'][$i]['value']
                                    )
                                ) {
                                    $res->orWhereIn(
                                        $parameters['filtered'][$i]['id'],
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    $res->orWhere(
                                        $parameters['filtered'][$i]['id'],
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                }
                            } else {
                                if (
                                    strcmp(
                                        $parameters['filtered'][$i]['id'],
                                        'vales.UserCreated'
                                    ) === 0 ||
                                    strcmp(
                                        $parameters['filtered'][$i]['id'],
                                        'vales.UserUpdated'
                                    ) === 0
                                ) {
                                    $res->orWhere(
                                        $parameters['filtered'][$i]['id'],
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    $res->orWhere(
                                        $parameters['filtered'][$i]['id'],
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                }
                            }
                        }
                    }
                }
            }

            $page = $parameters['page'];
            $pageSize = $parameters['pageSize'];

            $startIndex = $page * $pageSize;
            if (isset($parameters['sorted'])) {
                for ($i = 0; $i < count($parameters['sorted']); $i++) {
                    if ($parameters['sorted'][$i]['desc'] === true) {
                        $res->orderBy($parameters['sorted'][$i]['id'], 'desc');
                    } else {
                        $res->orderBy($parameters['sorted'][$i]['id'], 'asc');
                    }
                }
            }
            /* if(count($parameters['sorted']) == 0){
                    $res->orderBy('et_cat_municipio.Nombre','asc')
                    ->orderBy('et_cat_localidad.Nombre','asc')
                    ->orderBy('vales.Colonia','asc')
                    ->orderBy('vales.Nombre','asc')
                    ->orderBy('vales.Paterno','asc');
                
            } */

            if (isset($parameters['NombreCompleto'])) {
                $filtro_recibido = $parameters['NombreCompleto'];
                $filtro_recibido = str_replace(' ', '', $filtro_recibido);
                $res->where(
                    DB::raw("
                    REPLACE(
                    CONCAT(
                        vales.Nombre,
                        vales.Paterno,
                        vales.Materno,
                        vales.Paterno,
                        vales.Nombre,
                        vales.Materno,
                        vales.Materno,
                        vales.Nombre,
                        vales.Paterno,
                        vales.Nombre,
                        vales.Materno,
                        vales.Paterno,
                        vales.Paterno,
                        vales.Materno,
                        vales.Nombre,
                        vales.Materno,
                        vales.Paterno,
                        vales.Nombre
                    ), ' ', '')"),

                    'like',
                    '%' . $filtro_recibido . '%'
                );
            }

            if (isset($parameters['NombreOwner'])) {
                $filtro_recibido = $parameters['NombreOwner'];
                $filtro_recibido = str_replace(' ', '', $filtro_recibido);
                $res->where(
                    DB::raw("
                    REPLACE(
                    CONCAT(
                        usersC.Nombre,
                        usersC.Paterno,
                        usersC.Materno,
                        usersC.Paterno,
                        usersC.Nombre,
                        usersC.Materno,
                        usersC.Materno,
                        usersC.Nombre,
                        usersC.Paterno,
                        usersC.Nombre,
                        usersC.Materno,
                        usersC.Paterno,
                        usersC.Paterno,
                        usersC.Materno,
                        usersC.Nombre,
                        usersC.Materno,
                        usersC.Paterno,
                        usersC.Nombre
                    ), ' ', '')"),

                    'like',
                    '%' . $filtro_recibido . '%'
                );
            }

            if (isset($parameters['NombreCreated'])) {
                $filtro_recibido = $parameters['NombreCreated'];
                $filtro_recibido = str_replace(' ', '', $filtro_recibido);
                $res->where(
                    DB::raw("
                    REPLACE(
                    CONCAT(
                        usersCretaed.Nombre,
                        usersCretaed.Paterno,
                        usersCretaed.Materno,
                        usersCretaed.Paterno,
                        usersCretaed.Nombre,
                        usersCretaed.Materno,
                        usersCretaed.Materno,
                        usersCretaed.Nombre,
                        usersCretaed.Paterno,
                        usersCretaed.Nombre,
                        usersCretaed.Materno,
                        usersCretaed.Paterno,
                        usersCretaed.Paterno,
                        usersCretaed.Materno,
                        usersCretaed.Nombre,
                        usersCretaed.Materno,
                        usersCretaed.Paterno,
                        usersCretaed.Nombre
                    ), ' ', '')"),

                    'like',
                    '%' . $filtro_recibido . '%'
                );
            }

            $res
                ->orderBy('et_cat_municipio.Nombre', 'asc')
                ->orderBy('et_cat_localidad.Nombre', 'asc')
                ->orderBy('vales.Colonia', 'asc')
                ->orderBy('vales.Nombre', 'asc')
                ->orderBy('vales.Paterno', 'asc');

            //dd($res->toSql());

            $total = $res->count();
            $res = $res
                ->offset($startIndex)
                ->take($pageSize)
                ->get();

            $array_res = [];
            $temp = [];
            foreach ($res as $data) {
                $temp = [
                    'id' => $data->id,
                    'ClaveUnica' => $data->ClaveUnica,
                    'TelRecados' => $data->TelRecados,
                    'Compania' => $data->Compania,
                    'TelFijo' => $data->TelFijo,
                    'FolioSolicitud' => $data->FolioSolicitud,
                    'FechaSolicitud' => $data->FechaSolicitud,
                    'CURP' => $data->CURP,
                    'Nombre' => $data->Nombre,
                    'Paterno' => $data->Paterno,
                    'Materno' => $data->Materno,
                    'Sexo' => $data->Sexo,
                    'FechaNacimiento' => $data->FechaNacimiento,
                    'Calle' => $data->Calle,
                    'NumExt' => $data->NumExt,
                    'NumInt' => $data->NumInt,
                    'Colonia' => $data->Colonia,
                    'IngresoPercibido' => $data->IngresoPercibido,
                    'OtrosIngresos' => $data->OtrosIngresos,
                    'NumeroPersonas' => $data->NumeroPersonas,
                    'OcupacionOtro' => $data->OcupacionOtro,
                    'Ocupacion' => $data->Ocupacion,
                    'CP' => $data->CP,
                    'idMunicipio' => [
                        'id' => $data->IdM,
                        'Municipio' => $data->Municipio,
                        'Region' => $data->Region,
                    ],
                    'idLocalidad' => [
                        'id' => $data->Clave,
                        'Nombre' => $data->Localidad,
                    ],
                    'TelFijo' => $data->TelFijo,
                    'TelCelular' => $data->TelCelular,
                    'CorreoElectronico' => $data->CorreoElectronico,
                    'idStatus' => [
                        'id' => $data->idES,
                        'Clave' => $data->ClaveA,
                        'Estatus' => $data->Estatus,
                    ],
                    'created_at' => $data->created_at,
                    'updated_at' => $data->updated_at,
                    'UserCreated' => [
                        'id' => $data->idE,
                        'email' => $data->emailE,
                        'Nombre' => $data->NombreE,
                        'Paterno' => $data->PaternoE,
                        'Materno' => $data->MaternoE,
                        'idTipoUser' => [
                            'id' => $data->idEA,
                            'TipoUser' => $data->TipoUserEA,
                            'Clave' => $data->ClaveEA,
                        ],
                    ],
                    'UserUpdated' => [
                        'id' => $data->idF,
                        'email' => $data->emailF,
                        'Nombre' => $data->NombreF,
                        'Paterno' => $data->PaternoF,
                        'Materno' => $data->MaternoF,
                        'idTipoUser' => [
                            'id' => $data->idFA,
                            'TipoUser' => $data->TipoUserFA,
                            'Clave' => $data->ClaveFA,
                        ],
                    ],
                    'UserOwned' => [
                        'id' => $data->idO,
                        'email' => $data->emailO,
                        'Nombre' => $data->NombreO,
                        'Paterno' => $data->PaternoO,
                        'Materno' => $data->MaternoO,
                        'idTipoUser' => [
                            'id' => $data->idGO,
                            'TipoUser' => $data->TipoUserGO,
                            'Clave' => $data->ClaveGO,
                        ],
                    ],
                ];

                array_push($array_res, $temp);
            }

            return [
                'success' => true,
                'results' => true,
                'total' => $total,
                'filtros' => $parameters['filtered'],
                'data' => $array_res,
            ];
        } catch (QueryException $e) {
            dd($e->getMessage());
            $errors = [
                'Clave' => '01',
            ];
            $response = [
                'success' => true,
                'results' => false,
                'total' => 0,
                'filtros' => $parameters['filtered'],
                'errors' => $errors,
                'message' => 'Campo de consulta incorrecto',
            ];

            return response()->json($response, 200);
        }
    }

    function getValesIn(Request $request)
    {
        $parameters = $request->all();

        try {
            $res_1 = DB::table('vales_solicitudes')
                ->select('vales_solicitudes.idSolicitud')
                ->get();

            $array = [];
            foreach ($res_1 as $data) {
                array_push($array, $data->idSolicitud);
            }

            $res = DB::table('vales')
                ->select(
                    'vales.id',
                    DB::raw('LPAD(HEX(vales.id),6,0) as ClaveUnica'),
                    'vales.TelRecados',
                    'vales.Compania',
                    'vales.TelFijo',
                    'vales.FolioSolicitud',
                    'vales.FechaSolicitud',
                    'vales.CURP',
                    'vales.Nombre',
                    'vales.Paterno',
                    'vales.Materno',
                    'vales.Sexo',
                    'vales.FechaNacimiento',
                    'vales.Calle',
                    'vales.NumExt',
                    'vales.NumInt',
                    'vales.Colonia',
                    'vales.CP',
                    'vales.IngresoPercibido',
                    'vales.OtrosIngresos',
                    'vales.NumeroPersonas',
                    'vales.OcupacionOtro',
                    'vales.Ocupacion',
                    //SOLICITUD
                    'vales_solicitudes.id as idRegistro_Solicitud',
                    'vales_solicitudes.idSolicitud as id_Solicitud',
                    'vales_solicitudes.CURP as CURP_Solicitud',
                    'vales_solicitudes.Nombre as Nombre_Solicitud',
                    'vales_solicitudes.Paterno as Paterno_Solicitud',
                    'vales_solicitudes.Materno as Materno_Solicitud',
                    'vales_solicitudes.CodigoBarrasInicial as CodigoBarrasInicial_Solicitud',
                    'vales_solicitudes.CodigoBarrasFinal as CodigoBarrasFinal_Solicitud',
                    'vales_solicitudes.SerieInicial as SerieInicial_Solicitud',
                    'vales_solicitudes.SerieFinal as SerieFinal_Solicitud',
                    'vales_solicitudes.Remesa as Remesa_Solicitud',
                    'vales_solicitudes.Comentario as Comentario_Solicitud',
                    'vales_solicitudes.created_at as created_at_Solicitud',
                    'vales_solicitudes.UserCreated as UserCreated_Solicitud',
                    'vales_solicitudes.updated_at as updated_at_Solicitud',

                    'vales.idMunicipio',
                    'et_cat_municipio.Id AS IdM',
                    'et_cat_municipio.Nombre AS Municipio',
                    'et_cat_municipio.SubRegion AS Region',
                    'vales.idLocalidad',
                    'et_cat_localidad.Id AS Clave',
                    'et_cat_localidad.Nombre AS Localidad',
                    'vales.TelFijo',
                    'vales.TelCelular',
                    'vales.CorreoElectronico',
                    'vales.idStatus',
                    'vales_status.id as idES',
                    'vales_status.Estatus',
                    'vales_status.Clave as ClaveA',
                    'vales.created_at',
                    'vales.updated_at',
                    'vales.UserCreated',
                    //Datos Usuario created
                    'users.id as idE',
                    'users.email as emailE',
                    'users.Nombre as NombreE',
                    'users.Paterno as PaternoE',
                    'users.Materno as MaternoE',
                    'users.idTipoUser',
                    'cat_usertipo.id as idEA',
                    'cat_usertipo.TipoUser as TipoUserEA',
                    'cat_usertipo.Clave as ClaveEA',
                    'vales.UserUpdated',
                    //Datos Usuario updated
                    'usersB.id as idF',
                    'usersB.email as emailF',
                    'usersB.Nombre as NombreF',
                    'usersB.Paterno as PaternoF',
                    'usersB.Materno as MaternoF',
                    'usersB.idTipoUser',
                    'cat_usertipoB.id as idFA',
                    'cat_usertipoB.TipoUser as TipoUserFA',
                    'cat_usertipoB.Clave as ClaveFA',
                    'vales.UserOwned',
                    //Datos Usuario owned
                    'usersC.id as idO',
                    'usersC.email as emailO',
                    'usersC.Nombre as NombreO',
                    'usersC.Paterno as PaternoO',
                    'usersC.Materno as MaternoO',
                    'usersC.idTipoUser',
                    'cat_usertipoC.id as idGO',
                    'cat_usertipoC.TipoUser as TipoUserGO',
                    'cat_usertipoC.Clave as ClaveGO'
                )
                ->leftJoin(
                    'et_cat_municipio',
                    'et_cat_municipio.Id',
                    '=',
                    'vales.idMunicipio'
                )
                ->leftJoin(
                    'et_cat_localidad',
                    'et_cat_localidad.Id',
                    '=',
                    'vales.idLocalidad'
                )
                ->leftJoin('vales_status', 'vales_status.id', '=', 'idStatus')
                ->leftJoin('users', 'users.id', '=', 'vales.UserCreated')
                ->leftJoin(
                    'cat_usertipo',
                    'cat_usertipo.id',
                    '=',
                    'users.idTipoUser'
                )
                ->leftJoin(
                    'users as usersB',
                    'usersB.id',
                    '=',
                    'vales.UserUpdated'
                )
                ->leftJoin(
                    'cat_usertipo as cat_usertipoB',
                    'cat_usertipoB.id',
                    '=',
                    'usersB.idTipoUser'
                )
                ->leftJoin(
                    'users as usersC',
                    'usersC.id',
                    '=',
                    'vales.UserOwned'
                )
                ->leftJoin(
                    'users as usersCretaed',
                    'usersCretaed.id',
                    '=',
                    'vales.UserCreated'
                )
                ->leftJoin(
                    'cat_usertipo as cat_usertipoC',
                    'cat_usertipoC.id',
                    '=',
                    'usersC.idTipoUser'
                )
                ->where('vales.idStatus', '=', 5)
                ->whereIn('vales.id', $array)
                ->leftJoin(
                    'vales_solicitudes',
                    'vales_solicitudes.idSolicitud',
                    '=',
                    'vales.id'
                )
                ->orderBy('et_cat_municipio.Nombre', 'asc')
                ->orderBy('vales.Colonia', 'asc')
                ->orderBy('vales.Nombre', 'asc')
                ->orderBy('vales.Paterno', 'asc');

            if (isset($parameters['UserOwned'])) {
                if (is_array($parameters['UserOwned'])) {
                    $res->whereIn('vales.UserOwned', $parameters['UserOwned']);
                } else {
                    $res->where(
                        'vales.UserOwned',
                        '=',
                        $parameters['UserOwned']
                    );
                }
            }
            if (isset($parameters['Remesa'])) {
                if (is_array($parameters['Remesa'])) {
                    $res->whereIn('vales.Remesa', $parameters['Remesa']);
                } else {
                    $res->where('vales.Remesa', '=', $parameters['Remesa']);
                }
            }
            $flag = 0;
            if (isset($parameters['filtered'])) {
                for ($i = 0; $i < count($parameters['filtered']); $i++) {
                    if ($flag == 0) {
                        if (
                            $parameters['filtered'][$i]['id'] &&
                            strpos($parameters['filtered'][$i]['id'], 'id') !==
                                false
                        ) {
                            if (
                                is_array($parameters['filtered'][$i]['value'])
                            ) {
                                $res->whereIn(
                                    $parameters['filtered'][$i]['id'],
                                    $parameters['filtered'][$i]['value']
                                );
                            } else {
                                $res->where(
                                    $parameters['filtered'][$i]['id'],
                                    '=',
                                    $parameters['filtered'][$i]['value']
                                );
                            }
                        } else {
                            if (
                                strcmp(
                                    $parameters['filtered'][$i]['id'],
                                    'vales.UserCreated'
                                ) === 0 ||
                                strcmp(
                                    $parameters['filtered'][$i]['id'],
                                    'vales.UserUpdated'
                                ) === 0 ||
                                strcmp(
                                    $parameters['filtered'][$i]['id'],
                                    'vales.UserOwned'
                                ) === 0
                            ) {
                                $res->where(
                                    $parameters['filtered'][$i]['id'],
                                    '=',
                                    $parameters['filtered'][$i]['value']
                                );
                            } else {
                                $res->where(
                                    $parameters['filtered'][$i]['id'],
                                    'LIKE',
                                    '%' .
                                        $parameters['filtered'][$i]['value'] .
                                        '%'
                                );
                            }
                        }
                        $flag = 1;
                    } else {
                        if ($parameters['tipo'] == 'and') {
                            if (
                                $parameters['filtered'][$i]['id'] &&
                                strpos(
                                    $parameters['filtered'][$i]['id'],
                                    'id'
                                ) !== false
                            ) {
                                if (
                                    is_array(
                                        $parameters['filtered'][$i]['value']
                                    )
                                ) {
                                    $res->whereIn(
                                        $parameters['filtered'][$i]['id'],
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    $res->where(
                                        $parameters['filtered'][$i]['id'],
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                }
                            } else {
                                if (
                                    strcmp(
                                        $parameters['filtered'][$i]['id'],
                                        'vales.UserCreated'
                                    ) === 0 ||
                                    strcmp(
                                        $parameters['filtered'][$i]['id'],
                                        'vales.UserUpdated'
                                    ) === 0 ||
                                    strcmp(
                                        $parameters['filtered'][$i]['id'],
                                        'vales.UserOwned'
                                    ) === 0
                                ) {
                                    $res->where(
                                        $parameters['filtered'][$i]['id'],
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    $res->where(
                                        $parameters['filtered'][$i]['id'],
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                }
                            }
                        } else {
                            if (
                                $parameters['filtered'][$i]['id'] &&
                                strpos(
                                    $parameters['filtered'][$i]['id'],
                                    'id'
                                ) !== false
                            ) {
                                if (
                                    is_array(
                                        $parameters['filtered'][$i]['value']
                                    )
                                ) {
                                    $res->orWhereIn(
                                        $parameters['filtered'][$i]['id'],
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    $res->orWhere(
                                        $parameters['filtered'][$i]['id'],
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                }
                            } else {
                                if (
                                    strcmp(
                                        $parameters['filtered'][$i]['id'],
                                        'vales.UserCreated'
                                    ) === 0 ||
                                    strcmp(
                                        $parameters['filtered'][$i]['id'],
                                        'vales.UserUpdated'
                                    ) === 0
                                ) {
                                    $res->orWhere(
                                        $parameters['filtered'][$i]['id'],
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    $res->orWhere(
                                        $parameters['filtered'][$i]['id'],
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                }
                            }
                        }
                    }
                }
            }

            $page = $parameters['page'];
            $pageSize = $parameters['pageSize'];

            $startIndex = $page * $pageSize;
            if (isset($parameters['sorted'])) {
                for ($i = 0; $i < count($parameters['sorted']); $i++) {
                    if ($parameters['sorted'][$i]['desc'] === true) {
                        $res->orderBy($parameters['sorted'][$i]['id'], 'desc');
                    } else {
                        $res->orderBy($parameters['sorted'][$i]['id'], 'asc');
                    }
                }
            }

            if (isset($parameters['NombreCompleto'])) {
                $filtro_recibido = $parameters['NombreCompleto'];
                $filtro_recibido = str_replace(' ', '', $filtro_recibido);
                $res->where(
                    DB::raw("
                    REPLACE(
                    CONCAT(
                        vales.Nombre,
                        vales.Paterno,
                        vales.Materno,
                        vales.Paterno,
                        vales.Nombre,
                        vales.Materno,
                        vales.Materno,
                        vales.Nombre,
                        vales.Paterno,
                        vales.Nombre,
                        vales.Materno,
                        vales.Paterno,
                        vales.Paterno,
                        vales.Materno,
                        vales.Nombre,
                        vales.Materno,
                        vales.Paterno,
                        vales.Nombre
                    ), ' ', '')"),

                    'like',
                    '%' . $filtro_recibido . '%'
                );
            }

            if (isset($parameters['NombreOwner'])) {
                $filtro_recibido = $parameters['NombreOwner'];
                $filtro_recibido = str_replace(' ', '', $filtro_recibido);
                $res->where(
                    DB::raw("
                    REPLACE(
                    CONCAT(
                        usersC.Nombre,
                        usersC.Paterno,
                        usersC.Materno,
                        usersC.Paterno,
                        usersC.Nombre,
                        usersC.Materno,
                        usersC.Materno,
                        usersC.Nombre,
                        usersC.Paterno,
                        usersC.Nombre,
                        usersC.Materno,
                        usersC.Paterno,
                        usersC.Paterno,
                        usersC.Materno,
                        usersC.Nombre,
                        usersC.Materno,
                        usersC.Paterno,
                        usersC.Nombre
                    ), ' ', '')"),

                    'like',
                    '%' . $filtro_recibido . '%'
                );
            }

            if (isset($parameters['NombreCreated'])) {
                $filtro_recibido = $parameters['NombreCreated'];
                $filtro_recibido = str_replace(' ', '', $filtro_recibido);
                $res->where(
                    DB::raw("
                    REPLACE(
                    CONCAT(
                        usersCretaed.Nombre,
                        usersCretaed.Paterno,
                        usersCretaed.Materno,
                        usersCretaed.Paterno,
                        usersCretaed.Nombre,
                        usersCretaed.Materno,
                        usersCretaed.Materno,
                        usersCretaed.Nombre,
                        usersCretaed.Paterno,
                        usersCretaed.Nombre,
                        usersCretaed.Materno,
                        usersCretaed.Paterno,
                        usersCretaed.Paterno,
                        usersCretaed.Materno,
                        usersCretaed.Nombre,
                        usersCretaed.Materno,
                        usersCretaed.Paterno,
                        usersCretaed.Nombre
                    ), ' ', '')"),

                    'like',
                    '%' . $filtro_recibido . '%'
                );
            }

            $total = $res->count();
            $res = $res
                ->offset($startIndex)
                ->take($pageSize)
                ->get();

            $array_res = [];
            $temp = [];
            foreach ($res as $data) {
                $temp = [
                    'id' => $data->id,
                    'ClaveUnica' => $data->ClaveUnica,
                    'TelRecados' => $data->TelRecados,
                    'Compania' => $data->Compania,
                    'TelFijo' => $data->TelFijo,
                    'FolioSolicitud' => $data->FolioSolicitud,
                    'FechaSolicitud' => $data->FechaSolicitud,
                    'CURP' => $data->CURP,
                    'Nombre' => $data->Nombre,
                    'Paterno' => $data->Paterno,
                    'Materno' => $data->Materno,
                    'Sexo' => $data->Sexo,
                    'FechaNacimiento' => $data->FechaNacimiento,
                    'Calle' => $data->Calle,
                    'NumExt' => $data->NumExt,
                    'NumInt' => $data->NumInt,
                    'Colonia' => $data->Colonia,
                    'CP' => $data->CP,

                    //SOLICITUD
                    'idSolicitud' => [
                        'id ' => $data->idRegistro_Solicitud,
                        'idSolicitud' => $data->id_Solicitud,
                        'CURP' => $data->CURP_Solicitud,
                        'Nombre' => $data->Nombre_Solicitud,
                        'Paterno' => $data->Paterno_Solicitud,
                        'Materno' => $data->Materno_Solicitud,
                        'CodigoBarrasInicial' =>
                            $data->CodigoBarrasInicial_Solicitud,
                        'CodigoBarrasFinal' =>
                            $data->CodigoBarrasFinal_Solicitud,
                        'SerieInicial' => $data->SerieInicial_Solicitud,
                        'SerieFinal' => $data->SerieFinal_Solicitud,
                        'Remesa' => $data->Remesa_Solicitud,
                        'Comentario' => $data->Comentario_Solicitud,
                        'created_at' => $data->created_at_Solicitud,
                        'UserCreated' => $data->UserCreated_Solicitud,
                        'updated_at' => $data->updated_at_Solicitud,
                    ],
                    'IngresoPercibido' => $data->IngresoPercibido,
                    'OtrosIngresos' => $data->OtrosIngresos,
                    'NumeroPersonas' => $data->NumeroPersonas,
                    'OcupacionOtro' => $data->OcupacionOtro,
                    'Ocupacion' => $data->Ocupacion,

                    'idMunicipio' => [
                        'id' => $data->IdM,
                        'Municipio' => $data->Municipio,
                        'Region' => $data->Region,
                    ],
                    'idLocalidad' => [
                        'id' => $data->Clave,
                        'Nombre' => $data->Localidad,
                    ],
                    'TelFijo' => $data->TelFijo,
                    'TelCelular' => $data->TelCelular,
                    'CorreoElectronico' => $data->CorreoElectronico,
                    'idStatus' => [
                        'id' => $data->idES,
                        'Clave' => $data->ClaveA,
                        'Estatus' => $data->Estatus,
                    ],
                    'created_at' => $data->created_at,
                    'updated_at' => $data->updated_at,
                    'UserCreated' => [
                        'id' => $data->idE,
                        'email' => $data->emailE,
                        'Nombre' => $data->NombreE,
                        'Paterno' => $data->PaternoE,
                        'Materno' => $data->MaternoE,
                        'idTipoUser' => [
                            'id' => $data->idEA,
                            'TipoUser' => $data->TipoUserEA,
                            'Clave' => $data->ClaveEA,
                        ],
                    ],
                    'UserUpdated' => [
                        'id' => $data->idF,
                        'email' => $data->emailF,
                        'Nombre' => $data->NombreF,
                        'Paterno' => $data->PaternoF,
                        'Materno' => $data->MaternoF,
                        'idTipoUser' => [
                            'id' => $data->idFA,
                            'TipoUser' => $data->TipoUserFA,
                            'Clave' => $data->ClaveFA,
                        ],
                    ],
                    'UserOwned' => [
                        'id' => $data->idO,
                        'email' => $data->emailO,
                        'Nombre' => $data->NombreO,
                        'Paterno' => $data->PaternoO,
                        'Materno' => $data->MaternoO,
                        'idTipoUser' => [
                            'id' => $data->idGO,
                            'TipoUser' => $data->TipoUserGO,
                            'Clave' => $data->ClaveGO,
                        ],
                    ],
                ];

                array_push($array_res, $temp);
            }

            return [
                'success' => true,
                'results' => true,
                'total' => $total,
                'filtros' => $parameters['filtered'],
                'data' => $array_res,
            ];
        } catch (QueryException $e) {
            dd($e->getMessage());
            $errors = [
                'Clave' => '01',
            ];
            $response = [
                'success' => true,
                'results' => false,
                'total' => 0,
                'filtros' => $parameters['filtered'],
                'errors' => $errors,
                'message' => 'Campo de consulta incorrecto',
            ];

            return response()->json($response, 200);
        }
    }

    function getValesRecepcionDocumentacion(Request $request)
    {
        $parameters = $request->all();

        try {
            $res = DB::table('vales as V')
                ->select(
                    DB::raw('LPAD(HEX(V.id),6,0) as FolioSolicitud'),
                    'V.FechaSolicitud',
                    'V.CURP',
                    'V.Nombre',
                    'V.Paterno',
                    'V.Materno',
                    'M.SubRegion AS Region',
                    'M.Nombre AS Municipio',
                    'V.isDocumentacionEntrega',
                    'V.FechaDocumentacion', //FechaDocumentacion
                    'V.idUserDocumentacion',
                    'U.email as CelularRecepciono',
                    DB::raw(
                        "concat_ws(' ',U.Nombre, U.Paterno, U.Materno) as UserRecepciono"
                    )
                )
                ->leftJoin(
                    'et_cat_municipio as M',
                    'V.idMunicipio',
                    '=',
                    'M.Id'
                )
                ->leftJoin('users as U', 'V.idUserDocumentacion', '=', 'U.id')
                ->leftJoin('users as R', 'V.UserOwned', '=', 'R.id')
                ->where('V.isDocumentacionEntrega', '=', 1)
                ->whereNull('V.Remesa');

            $flag = 0;
            $user = auth()->user();

            if (isset($parameters['Articulador'])) {
                if (is_array($parameters['Articulador'])) {
                    $valor_id = $parameters['Articulador'];
                    $res->where(function ($q) use ($valor_id) {
                        $q
                            ->whereIn('V.UserCreated', $valor_id)
                            ->orWhereIn('V.UserOwned', $valor_id);
                    });
                } else {
                    $valor_id = $parameters['Articulador'];
                    $res->where(function ($q) use ($valor_id) {
                        $q
                            ->where('V.UserCreated', $valor_id)
                            ->orWhere('V.UserOwned', $valor_id);
                    });
                }
            }
            $view_all = DB::table('users_menus')
                ->where('idUser', $user->id)
                ->where('idMenu', 9)
                ->first();
            if ($view_all->ViewAll == 0) {
                $valor_id = $user->id;
                $res->where(function ($q) use ($valor_id) {
                    $q
                        ->where('V.UserCreated', $valor_id)
                        ->orWhere('V.UserOwned', $valor_id);
                });
            }
            if (isset($parameters['Folio'])) {
                $valor_id = $parameters['Folio'];
                $res->where(
                    DB::raw('LPAD(HEX(V.id),6,0)'),
                    'like',
                    '%' . $valor_id . '%'
                );
            }

            if (isset($parameters['CURP'])) {
                $valor_curp = $parameters['CURP'];
                $res->where(DB::raw('V.CURP'), 'like', '%' . $valor_curp . '%');
            }

            if (isset($parameters['Regiones'])) {
                if (count($parameters['Regiones'])) {
                    $resMunicipio = DB::table('et_cat_municipio')
                        ->whereIn('SubRegion', $parameters['Regiones'])
                        ->pluck('Id');

                    //dd($resMunicipio);

                    $res->whereIn('V.idMunicipio', $resMunicipio);
                }
            }
            if (isset($parameters['idMunicipio'])) {
                if (count($parameters['idMunicipio'])) {
                    $res->whereIn('V.idMunicipio', $parameters['idMunicipio']);
                }
            }

            if (isset($parameters['filtered'])) {
                for ($i = 0; $i < count($parameters['filtered']); $i++) {
                    if ($flag == 0) {
                        if (
                            $parameters['filtered'][$i]['id'] &&
                            strpos($parameters['filtered'][$i]['id'], 'id') !==
                                false
                        ) {
                            if (
                                is_array($parameters['filtered'][$i]['value'])
                            ) {
                                $res->whereIn(
                                    $parameters['filtered'][$i]['id'],
                                    $parameters['filtered'][$i]['value']
                                );
                            } else {
                                $res->where(
                                    $parameters['filtered'][$i]['id'],
                                    '=',
                                    $parameters['filtered'][$i]['value']
                                );
                            }
                        } /* else if($parameters['filtered'][$i]['id'] &&  strpos($parameters['filtered'][$i]['id'], 'V.FechaDocumentacion') !== false){
                            $res->where($parameters['filtered'][$i]['id'],'=',$parameters['filtered'][$i]['value']);
                        } */ else {
                            if (
                                strcmp(
                                    $parameters['filtered'][$i]['id'],
                                    'V.UserCreated'
                                ) === 0 ||
                                strcmp(
                                    $parameters['filtered'][$i]['id'],
                                    'V.UserUpdated'
                                ) === 0 ||
                                strcmp(
                                    $parameters['filtered'][$i]['id'],
                                    'V.UserOwned'
                                ) === 0
                            ) {
                                $res->where(
                                    $parameters['filtered'][$i]['id'],
                                    '=',
                                    $parameters['filtered'][$i]['value']
                                );
                            } else {
                                if (
                                    strpos(
                                        $parameters['filtered'][$i]['id'],
                                        'is'
                                    ) !== false
                                ) {
                                    $res->where(
                                        $parameters['filtered'][$i]['id'],
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    $res->where(
                                        $parameters['filtered'][$i]['id'],
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                }
                            }
                        }
                        $flag = 1;
                    } else {
                        if ($parameters['tipo'] == 'and') {
                            if (
                                $parameters['filtered'][$i]['id'] &&
                                strpos(
                                    $parameters['filtered'][$i]['id'],
                                    'id'
                                ) !== false
                            ) {
                                if (
                                    is_array(
                                        $parameters['filtered'][$i]['value']
                                    )
                                ) {
                                    $res->whereIn(
                                        $parameters['filtered'][$i]['id'],
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    $res->where(
                                        $parameters['filtered'][$i]['id'],
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                }
                            } else {
                                if (
                                    strcmp(
                                        $parameters['filtered'][$i]['id'],
                                        'V.UserCreated'
                                    ) === 0 ||
                                    strcmp(
                                        $parameters['filtered'][$i]['id'],
                                        'V.UserUpdated'
                                    ) === 0 ||
                                    strcmp(
                                        $parameters['filtered'][$i]['id'],
                                        'V.UserOwned'
                                    ) === 0
                                ) {
                                    $res->where(
                                        $parameters['filtered'][$i]['id'],
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    if (
                                        strpos(
                                            $parameters['filtered'][$i]['id'],
                                            'is'
                                        ) !== false
                                    ) {
                                        $res->where(
                                            $parameters['filtered'][$i]['id'],
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->where(
                                            $parameters['filtered'][$i]['id'],
                                            'LIKE',
                                            '%' .
                                                $parameters['filtered'][$i][
                                                    'value'
                                                ] .
                                                '%'
                                        );
                                    }
                                }
                            }
                        } else {
                            if (
                                $parameters['filtered'][$i]['id'] &&
                                strpos(
                                    $parameters['filtered'][$i]['id'],
                                    'id'
                                ) !== false
                            ) {
                                if (
                                    is_array(
                                        $parameters['filtered'][$i]['value']
                                    )
                                ) {
                                    $res->orWhereIn(
                                        $parameters['filtered'][$i]['id'],
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    $res->orWhere(
                                        $parameters['filtered'][$i]['id'],
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                }
                            } else {
                                if (
                                    strcmp(
                                        $parameters['filtered'][$i]['id'],
                                        'V.UserCreated'
                                    ) === 0 ||
                                    strcmp(
                                        $parameters['filtered'][$i]['id'],
                                        'V.UserUpdated'
                                    ) === 0
                                ) {
                                    $res->orWhere(
                                        $parameters['filtered'][$i]['id'],
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    if (
                                        strpos(
                                            $parameters['filtered'][$i]['id'],
                                            'is'
                                        ) !== false
                                    ) {
                                        $res->orWhere(
                                            $parameters['filtered'][$i]['id'],
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->orWhere(
                                            $parameters['filtered'][$i]['id'],
                                            'LIKE',
                                            '%' .
                                                $parameters['filtered'][$i][
                                                    'value'
                                                ] .
                                                '%'
                                        );
                                    }
                                }
                            }
                        }
                    }
                }
            }

            $page = $parameters['page'];
            $pageSize = $parameters['pageSize'];

            $startIndex = $page * $pageSize;
            if (isset($parameters['sorted'])) {
                for ($i = 0; $i < count($parameters['sorted']); $i++) {
                    if ($parameters['sorted'][$i]['desc'] === true) {
                        $res->orderBy($parameters['sorted'][$i]['id'], 'desc');
                    } else {
                        $res->orderBy($parameters['sorted'][$i]['id'], 'asc');
                    }
                }
            }

            if (isset($parameters['NombreCompleto'])) {
                $filtro_recibido = $parameters['NombreCompleto'];
                $filtro_recibido = str_replace(' ', '', $filtro_recibido);
                $res->where(
                    DB::raw("
                    REPLACE(
                    CONCAT(
                        V.CURP,
                        V.Nombre,
                        V.Paterno,
                        V.Materno,
                        V.Paterno,
                        V.Nombre,
                        V.Materno,
                        V.Materno,
                        V.Nombre,
                        V.Paterno,
                        V.Nombre,
                        V.Materno,
                        V.Paterno,
                        V.Paterno,
                        V.Materno,
                        V.Nombre,
                        V.Materno,
                        V.Paterno,
                        V.Nombre
                    ), ' ', '')"),

                    'like',
                    '%' . $filtro_recibido . '%'
                );
            }

            if (isset($parameters['Ejercicio'])) {
                $valor_id = $parameters['Ejercicio'];
                $res->where(DB::raw('YEAR(V.FechaSolicitud)'), '=', $valor_id);
            }

            $total = $res->count();
            $res = $res
                ->offset($startIndex)
                ->take($pageSize)
                ->get();

            $parameters_serializado = serialize($parameters);
            //$array = unserialize($parameters_serializado);
            $user = auth()->user();

            $filtro_usuario = VNegociosFiltros::where('idUser', '=', $user->id)
                ->where('api', '=', 'getValesDocumentacion')
                ->first();
            if ($filtro_usuario) {
                $filtro_usuario->parameters = $parameters_serializado;
                $filtro_usuario->updated_at = time::now();
                $filtro_usuario->update();
            } else {
                $objeto_nuevo = new VNegociosFiltros();
                $objeto_nuevo->api = 'getValesDocumentacion';
                $objeto_nuevo->idUser = $user->id;
                $objeto_nuevo->parameters = $parameters_serializado;
                $objeto_nuevo->save();
            }

            return [
                'success' => true,
                'results' => true,
                'total' => $total,
                'filtros' => $parameters['filtered'],
                'data' => $res,
            ];
        } catch (QueryException $e) {
            $errors = [
                'Clave' => '01',
            ];
            $response = [
                'success' => true,
                'results' => false,
                'total' => 0,
                'filtros' => $parameters['filtered'],
                'errors' => $e->getMessage(),
                'message' => 'Campo de consulta incorrecto',
            ];

            return response()->json($response, 200);
        }
    }

    function getValesRegion(Request $request)
    {
        $parameters = $request->all();

        try {
            $res = DB::table('vales')
                ->select(
                    DB::raw('LPAD(HEX(vales.id),6,0) as ClaveUnica'),
                    'et_cat_municipio.SubRegion AS Region',
                    'et_cat_municipio.Nombre AS Municipio',
                    'et_cat_localidad.Nombre AS Localidad',
                    'vales.id',
                    'vales.FechaSolicitud',
                    'vales.CURP',
                    'vales.Nombre',
                    'vales.Paterno',
                    'vales.Materno',
                    'vales.IngresoPercibido',
                    'vales.OtrosIngresos',
                    'vales.NumeroPersonas',
                    'vales.OcupacionOtro',
                    'vales.Ocupacion',
                    'vales.Sexo',
                    'vales.FechaNacimiento',
                    'vales.Calle',
                    'vales.NumExt',
                    'vales.NumInt',
                    'vales.Colonia',
                    'vales.CP',
                    'vales.TelFijo',
                    'vales.TelCelular',
                    'vales.TelRecados',
                    'vales.Compania',
                    'vales.created_at',
                    'vales.updated_at',
                    'vales.UserCreated',
                    'vales.UserOwned',
                    'vales.UserUpdated',
                    DB::raw(
                        'concat_ws(" ",vales.Nombre, vales.Paterno, vales.Materno) AS NombreCompleto'
                    ),
                    DB::raw(
                        'concat_ws(" ",users.Nombre, users.Paterno, users.Materno) AS Capturo'
                    ),
                    DB::raw(
                        'concat_ws(" ",UO.Nombre, UO.Paterno, UO.Materno) AS Articulador'
                    ),
                    'vales.idStatus',
                    'vales_status.Estatus'
                )
                /* JOIN sedeshu.et_cat_localidad
            ON sedeshu.vales.idLocalidad = sedeshu.et_cat_localidad.Id 
            JOIN sedeshu.et_cat_municipio
            ON sedeshu.vales.idMunicipio = sedeshu.et_cat_municipio.Id 
            JOIN sedeshu.users
            ON sedeshu.vales.UserCreated = sedeshu.users.id 
            JOIN sedeshu.users AS UO
            ON sedeshu.vales.UserOwned = sedeshu.UO.id 
            JOIN sedeshu.vales_status
            ON sedeshu.vales.idStatus = sedeshu.vales_status.id
            left join users_region UR on (UR.Region = et_cat_municipio.SubRegion)
            WHERE
            vales.Remesa is not null and UR.idUser=59 */
                ->leftJoin(
                    'et_cat_localidad',
                    'et_cat_localidad.Id',
                    '=',
                    'vales.idLocalidad'
                )
                ->leftJoin(
                    'et_cat_municipio',
                    'et_cat_municipio.Id',
                    '=',
                    'vales.idMunicipio'
                )
                ->leftJoin('users', 'users.id', '=', 'vales.UserCreated')
                ->leftJoin('users as UO', 'UO.id', '=', 'vales.UserOwned')
                ->leftJoin(
                    'vales_status',
                    'vales_status.id',
                    '=',
                    'vales.idStatus'
                )
                ->leftJoin(
                    'users_region as UR',
                    'UR.Region',
                    '=',
                    'et_cat_municipio.SubRegion'
                )
                ->whereNotNull('vales.Remesa');
            $flag = 0;
            if (isset($parameters['Propietario'])) {
                $valor_id = $parameters['Propietario'];
                $res->where(function ($q) use ($valor_id) {
                    $q
                        ->where('vales.UserCreated', $valor_id)
                        ->orWhere('vales.UserOwned', $valor_id);
                });
            }
            if (isset($parameters['Folio'])) {
                $valor_id = $parameters['Folio'];
                $res->where(
                    DB::raw('LPAD(HEX(vales.id),6,0)'),
                    'like',
                    '%' . $valor_id . '%'
                );
            }
            if (isset($parameters['idUser'])) {
                $valor_id = $parameters['idUser'];
                $res->where('UR.idUser', '=', $valor_id);
            }

            if (isset($parameters['filtered'])) {
                for ($i = 0; $i < count($parameters['filtered']); $i++) {
                    if ($flag == 0) {
                        if (
                            $parameters['filtered'][$i]['id'] &&
                            strpos($parameters['filtered'][$i]['id'], 'id') !==
                                false
                        ) {
                            if (
                                is_array($parameters['filtered'][$i]['value'])
                            ) {
                                $res->whereIn(
                                    $parameters['filtered'][$i]['id'],
                                    $parameters['filtered'][$i]['value']
                                );
                            } else {
                                $res->where(
                                    $parameters['filtered'][$i]['id'],
                                    '=',
                                    $parameters['filtered'][$i]['value']
                                );
                            }
                        } else {
                            if (
                                strcmp(
                                    $parameters['filtered'][$i]['id'],
                                    'vales.UserCreated'
                                ) === 0 ||
                                strcmp(
                                    $parameters['filtered'][$i]['id'],
                                    'vales.UserUpdated'
                                ) === 0 ||
                                strcmp(
                                    $parameters['filtered'][$i]['id'],
                                    'vales.UserOwned'
                                ) === 0
                            ) {
                                $res->where(
                                    $parameters['filtered'][$i]['id'],
                                    '=',
                                    $parameters['filtered'][$i]['value']
                                );
                            } else {
                                if (
                                    strpos(
                                        $parameters['filtered'][$i]['id'],
                                        'is'
                                    ) !== false
                                ) {
                                    $res->where(
                                        $parameters['filtered'][$i]['id'],
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    $res->where(
                                        $parameters['filtered'][$i]['id'],
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                }
                            }
                        }
                        $flag = 1;
                    } else {
                        if ($parameters['tipo'] == 'and') {
                            if (
                                $parameters['filtered'][$i]['id'] &&
                                strpos(
                                    $parameters['filtered'][$i]['id'],
                                    'id'
                                ) !== false
                            ) {
                                if (
                                    is_array(
                                        $parameters['filtered'][$i]['value']
                                    )
                                ) {
                                    $res->whereIn(
                                        $parameters['filtered'][$i]['id'],
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    $res->where(
                                        $parameters['filtered'][$i]['id'],
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                }
                            } else {
                                if (
                                    strcmp(
                                        $parameters['filtered'][$i]['id'],
                                        'vales.UserCreated'
                                    ) === 0 ||
                                    strcmp(
                                        $parameters['filtered'][$i]['id'],
                                        'vales.UserUpdated'
                                    ) === 0 ||
                                    strcmp(
                                        $parameters['filtered'][$i]['id'],
                                        'vales.UserOwned'
                                    ) === 0
                                ) {
                                    $res->where(
                                        $parameters['filtered'][$i]['id'],
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    if (
                                        strpos(
                                            $parameters['filtered'][$i]['id'],
                                            'is'
                                        ) !== false
                                    ) {
                                        $res->where(
                                            $parameters['filtered'][$i]['id'],
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->where(
                                            $parameters['filtered'][$i]['id'],
                                            'LIKE',
                                            '%' .
                                                $parameters['filtered'][$i][
                                                    'value'
                                                ] .
                                                '%'
                                        );
                                    }
                                }
                            }
                        } else {
                            if (
                                $parameters['filtered'][$i]['id'] &&
                                strpos(
                                    $parameters['filtered'][$i]['id'],
                                    'id'
                                ) !== false
                            ) {
                                if (
                                    is_array(
                                        $parameters['filtered'][$i]['value']
                                    )
                                ) {
                                    $res->orWhereIn(
                                        $parameters['filtered'][$i]['id'],
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    $res->orWhere(
                                        $parameters['filtered'][$i]['id'],
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                }
                            } else {
                                if (
                                    strcmp(
                                        $parameters['filtered'][$i]['id'],
                                        'vales.UserCreated'
                                    ) === 0 ||
                                    strcmp(
                                        $parameters['filtered'][$i]['id'],
                                        'vales.UserUpdated'
                                    ) === 0
                                ) {
                                    $res->orWhere(
                                        $parameters['filtered'][$i]['id'],
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    if (
                                        strpos(
                                            $parameters['filtered'][$i]['id'],
                                            'is'
                                        ) !== false
                                    ) {
                                        $res->orWhere(
                                            $parameters['filtered'][$i]['id'],
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->orWhere(
                                            $parameters['filtered'][$i]['id'],
                                            'LIKE',
                                            '%' .
                                                $parameters['filtered'][$i][
                                                    'value'
                                                ] .
                                                '%'
                                        );
                                    }
                                }
                            }
                        }
                    }
                }
            }

            $page = $parameters['page'];
            $pageSize = $parameters['pageSize'];

            $startIndex = $page * $pageSize;
            if (isset($parameters['sorted'])) {
                for ($i = 0; $i < count($parameters['sorted']); $i++) {
                    if ($parameters['sorted'][$i]['desc'] === true) {
                        $res->orderBy($parameters['sorted'][$i]['id'], 'desc');
                    } else {
                        $res->orderBy($parameters['sorted'][$i]['id'], 'asc');
                    }
                }
            }

            if (isset($parameters['NombreCompleto'])) {
                $filtro_recibido = $parameters['NombreCompleto'];
                $filtro_recibido = str_replace(' ', '', $filtro_recibido);
                $res->where(
                    DB::raw("
                    REPLACE(
                    CONCAT(
                        vales.Nombre,
                        vales.Paterno,
                        vales.Materno,
                        vales.Paterno,
                        vales.Nombre,
                        vales.Materno,
                        vales.Materno,
                        vales.Nombre,
                        vales.Paterno,
                        vales.Nombre,
                        vales.Materno,
                        vales.Paterno,
                        vales.Paterno,
                        vales.Materno,
                        vales.Nombre,
                        vales.Materno,
                        vales.Paterno,
                        vales.Nombre
                    ), ' ', '')"),

                    'like',
                    '%' . $filtro_recibido . '%'
                );
            }

            if (isset($parameters['NombreOwner'])) {
                $filtro_recibido = $parameters['NombreOwner'];
                $filtro_recibido = str_replace(' ', '', $filtro_recibido);
                $res->where(
                    DB::raw("
                    REPLACE(
                    CONCAT(
                        UO.Nombre,
                        UO.Paterno,
                        UO.Materno,
                        UO.Paterno,
                        UO.Nombre,
                        UO.Materno,
                        UO.Materno,
                        UO.Nombre,
                        UO.Paterno,
                        UO.Nombre,
                        UO.Materno,
                        UO.Paterno,
                        UO.Paterno,
                        UO.Materno,
                        UO.Nombre,
                        UO.Materno,
                        UO.Paterno,
                        UO.Nombre
                    ), ' ', '')"),

                    'like',
                    '%' . $filtro_recibido . '%'
                );
            }

            if (isset($parameters['NombreCreated'])) {
                $filtro_recibido = $parameters['NombreCreated'];
                $filtro_recibido = str_replace(' ', '', $filtro_recibido);
                $res->where(
                    DB::raw("
                    REPLACE(
                    CONCAT(
                        users.Nombre,
                        users.Paterno,
                        users.Materno,
                        users.Paterno,
                        users.Nombre,
                        users.Materno,
                        users.Materno,
                        users.Nombre,
                        users.Paterno,
                        users.Nombre,
                        users.Materno,
                        users.Paterno,
                        users.Paterno,
                        users.Materno,
                        users.Nombre,
                        users.Materno,
                        users.Paterno,
                        users.Nombre
                    ), ' ', '')"),

                    'like',
                    '%' . $filtro_recibido . '%'
                );
            }

            $total = $res->count();
            $res = $res
                ->offset($startIndex)
                ->take($pageSize)
                ->get();

            $parameters_serializado = serialize($parameters);
            //$array = unserialize($parameters_serializado);
            $user = auth()->user();
            $filtro_usuario = VNegociosFiltros::where('idUser', '=', $user->id)
                ->where('api', '=', 'getValesRegion')
                ->first();
            if ($filtro_usuario) {
                $filtro_usuario->parameters = $parameters_serializado;
                $filtro_usuario->update();
            } else {
                $objeto_nuevo = new VNegociosFiltros();
                $objeto_nuevo->api = 'getValesRegion';
                $objeto_nuevo->idUser = $user->id;
                $objeto_nuevo->parameters = $parameters_serializado;
                $objeto_nuevo->save();
            }

            return [
                'success' => true,
                'results' => true,
                'total' => $total,
                'filtros' => $parameters['filtered'],
                'data' => $res,
            ];
        } catch (QueryException $e) {
            $errors = [
                'Clave' => '01',
            ];
            $response = [
                'success' => true,
                'results' => false,
                'total' => 0,
                'filtros' => $parameters['filtered'],
                'errors' => $e->getMessage(),
                'message' => 'Campo de consulta incorrecto',
            ];

            return response()->json($response, 200);
        }
    }

    function getValesResumen(Request $request)
    {
        $parameters = $request->all();
        //$param_filtro = serialize($parameters);

        $userId = JWTAuth::parseToken()->toUser()->id;

        DB::table('users_filtros')
            ->where('UserCreated', $userId)
            ->where('api', 'getValesResumen')
            ->delete();

        $parameters_serializado = serialize($parameters);
        //$parameters = unserialize($parameters_serializado);

        //Insertamos los filtros
        DB::table('users_filtros')->insert([
            'UserCreated' => $userId,
            'Api' => 'getValesResumen',
            'Consulta' => $parameters_serializado,
            'created_at' => date('Y-m-d h-m-s'),
        ]);
        try {
            if (isset($parameters['UserOwned'])) {
                $UserOwned = $parameters['UserOwned'];
                $res = DB::table(
                    DB::raw(
                        '
                    (
                    Select V.Remesa, V.NumAcuerdo, V.Total, VI.Incidencias, VE.Entregado, (V.Total - VE.Entregado - VI.Incidencias) as PorEntregar
                    from
                    (
                    select V.Remesa, VR.NumAcuerdo, count(V.id) Total from vales as V inner join vales_remesas VR on (VR.Remesa = V.Remesa)
                    where V.UserOwned=' .
                            $userId .
                            '
                    group by VR.NumAcuerdo) as V
                    left join 
                    (
                    select V.Remesa, VR.NumAcuerdo, count(V.id) as Entregado from vales as V inner join vales_remesas VR on (VR.Remesa = V.Remesa)
                    where V.isEntregado=1 and V.idIncidencia=1 and V.UserOwned=' .
                            $userId .
                            '
                    group by VR.NumAcuerdo) VE on (VE.Remesa = V.Remesa)
                    left join 
                    (
                    select V.Remesa, VR.NumAcuerdo, count(V.id) as Incidencias from vales as V inner join vales_remesas VR on (VR.Remesa = V.Remesa)
                    where V.idIncidencia !=1 and V.UserOwned=' .
                            $userId .
                            '
                    group by VR.NumAcuerdo) VI on (VI.Remesa = V.Remesa)
                    ) as TB
                '
                    )
                )->selectRaw('
                Remesa,
                NumAcuerdo,
                case when Total is null then 0 else Total end as Total,
                case when Incidencias is null then 0 else Incidencias end as Incidencias,
                case when Entregado is null then 0 else Entregado end as Entregado,
                case when PorEntregar is null then 0 else PorEntregar end as PorEntregar
                ');
                $res = $res->get();

                $SinExpedientes = DB::select(
                    'SELECT
                count(isDocumentacionEntrega) as SinExpediente
                FROM
                sedeshu.vales
                where year(FechaSolicitud)>=2021 and isDocumentacionEntrega=0 and UserOwned = ' .
                        $userId
                );

                $NoEntregados = DB::select(
                    'SELECT
                count(isEntregado) as NoEntregados
                FROM
                sedeshu.vales
                WHERE
                year(FechaSolicitud)>=2021 and isEntregado=0 and UserOwned = ' .
                        $userId
                );
                $response = [
                    'success' => true,
                    'results' => true,
                    'total' => count($res),
                    'filtros' => $parameters,
                    'data' => $res,
                    'resumen' => [
                        'SinExpediente' => $SinExpedientes[0]->SinExpediente,
                        'NoEntregados' => $NoEntregados[0]->NoEntregados,
                    ],
                    'errors' => '¡Sin errores!',
                    'message' => '¡Consulta exitosa!',
                ];

                return response()->json($response, 200);
            } else {
                $response = [
                    'success' => true,
                    'results' => false,
                    'total' => 0,
                    'filtros' => [],
                    'errors' => 'No se recibio el campo UserOwned',
                    'message' => '¡Ocurrio un error contacte al administrador!',
                ];

                return response()->json($response, 200);
            }
        } catch (QueryException $e) {
            $errors = [
                'Clave' => '01',
            ];
            $response = [
                'success' => false,
                'results' => false,
                'total' => 0,
                'filtros' => [],
                'errors' => $e->getMessage(),
                'message' => '¡Ocurrio un error contacte al administrador!',
            ];

            return response()->json($response, 200);
        }
    }

    function setVales(Request $request)
    {
        $parameters = $request->all();
        $user = auth()->user();

        $v = Validator::make($request->all(), [
            'Nombre' => 'required',
            'Paterno' => 'required',
            'Ejercicio' => 'required',
            //'Materno'=> 'required',
            'FechaNacimiento' => 'required',
            'Sexo' => 'required',
            'CURP' => 'required', //|unique:vales',
            'idMunicipio' => 'required',
            'idLocalidad' => 'required',
            'Colonia' => 'required',
            'NumExt' => 'required',
            'CP' => 'required',
            'FechaSolicitud' => 'required',
            'idStatus' => 'required',
            'UserOwned' => 'required',
            'IngresoPercibido' => 'required|numeric',
            'OtrosIngresos' => 'required|numeric',
            'NumeroPersonas' => 'required|numeric',
            'Ocupacion' => 'required',
            'OcupacionOtro' => 'numeric',
        ]);

        //sea el usuario y luego solo leon
        //Fecha hora solo capturar =

        if ($v->fails()) {
            $response = [
                'success' => false,
                'results' => false,
                'errors' => $v->errors(),
                'data' => [],
                'message' =>
                    '¡Error de validación: ' . $v->errors()->first() . '!',
            ];

            return response()->json($response, 200);
        }
        /* $response =  ['success'=>true,'results'=>false,
                    'errors'=>[],'data'=>[], 'message'=>'¡Error de Captura: Captura restringida!'];
        return response()->json($response,200); */
        //Celulares anteriores a 28 abril : '4776712587', '4776389631', '4772731389', '4775379878'
        /* if(!DB::table('users')->where('id',$user->id)->whereRaw("email in (
            '4777920259',
            '4721020298',
            '4791008102',
            '4721472438',
            '4721664680',
            '4721650439',
            '4778507725',
            '4721806421',
            '4721653288',
            '4721341251',
            '4777243606'
            
            )")->first())
        {
            $response =  ['success'=>true,'results'=>false,
            'errors'=>[],'data'=>[], 'message'=>'¡Error de permisos: No tiene derechos para ejecutar esta accion, contacte al administrador!'];
            return response()->json($response,200);
        } */
        //Restriccion usuarios 14 May 2021
        switch ($user->email) {
            case '4291254084':
            case '4771722950':
            case '4291200099':
            case '4291138918':
            case '4561615415':
            case '4566514564':
            case '4696214606':
            case '4622222388':
            case '4622651353':
                if ($parameters['idMunicipio'] != 1) {
                    $response = [
                        'success' => true,
                        'results' => false,
                        'errors' => [],
                        'data' => [],
                        'message' =>
                            '¡Error de Captura: Municipio Restringido!',
                    ];
                    return response()->json($response, 200);
                }
                break;
            case '4191082160':
                if ($parameters['idMunicipio'] != 32) {
                    $response = [
                        'success' => true,
                        'results' => false,
                        'errors' => [],
                        'data' => [],
                        'message' =>
                            '¡Error de Captura: Municipio Restringido!',
                    ];
                    return response()->json($response, 200);
                }
                break;
            case '4181583849':
            case '4181435215':
            case '4181435215':
            // case '4181490456':
            case '4772232236':
            case '4181435932':
            case '4281020861':
            case '4151189777':
            case '4151063059':
            case '4151534071':
            case '4181008377':
            case '4777667978':
            case '4181124059':
            case '4281043121':
            case '4731355468':
            case '4281207183':
            case '4281031415':
            case '4281317132':
            case '4181526731':
            case '4181846363':
            case '4181510811':
            case '4181390020':
            case '5528625064':
            case '4621399532':
            case '4281079677':
            case '4281075901':
            case '4151114105':
            case '4151670952':
            case '4151149587':
            case '4181054725':
            case '4181577107':
            case '4181416594':
            case '4181088286':
            case '4181096034':
            case '4181243664':
            case '4181244176':
                if (
                    !in_array($parameters['idMunicipio'], [3, 14, 22, 29, 30])
                ) {
                    //R2
                    $response = [
                        'success' => true,
                        'results' => false,
                        'errors' => [],
                        'data' => [],
                        'message' =>
                            '¡Error de Captura: Municipio Restringido!',
                    ];
                    return response()->json($response, 200);
                }
                break;
            case '4761458090':
            case '4773662301':
                if ($parameters['idMunicipio'] != 20) {
                    $response = [
                        'success' => true,
                        'results' => false,
                        'errors' => [],
                        'data' => [],
                        'message' =>
                            '¡Error de Captura: Municipio Restringido!',
                    ];
                    return response()->json($response, 200);
                }
                break;
            case '4761566759':
                if (
                    $parameters['idMunicipio'] != 37 &&
                    $parameters['idMunicipio'] != 17 &&
                    $parameters['idMunicipio'] != 27
                ) {
                    $response = [
                        'success' => true,
                        'results' => false,
                        'errors' => [],
                        'data' => [],
                        'message' =>
                            '¡Error de Captura: Municipio Restringido!',
                    ];
                    return response()->json($response, 200);
                }
                break;
            case '4451017451':
            case '4171062228':
            case '4381030633':
            case '4111268461':
            case '4171102390':
            case '4171194307':
            case '4666630277':
            case '4171728497':
            case '4451322711':
            case '4214727059':
            case '4451087068':
            case '4171117803':
            case '4451012647':
            case '4214723970':
            case '4661034795':
            case '4211084060':
            case '4661082621':
            case '4661095900':
            case '4661207793':
            case '4211103870':
            case '4661867199':
            case '4451182375':
            case '4171100139':
            case '4171077936':
            case '4454566305':
            case '4451060740':
            case '4451064837':
                if (
                    !in_array($parameters['idMunicipio'], [
                        2,
                        10,
                        18,
                        19,
                        21,
                        28,
                        36,
                        38,
                        41,
                        46,
                    ])
                ) {
                    //R5
                    $response = [
                        'success' => true,
                        'results' => false,
                        'errors' => [],
                        'data' => [],
                        'message' =>
                            '¡Error de Captura: Municipio Restringido!',
                    ];
                    return response()->json($response, 200);
                }
                break;
            case '4731003346':
            case '4611712123':
            case '4621699832':
            case '4611454279':
            case '4611847872':
            case '4661236453':
            case '4612987320':
            case '4611486159':
            case '4611089328':
            case '4611983217':
            case '4612088982':
            case '4111543012':
            case '4131184791':
            case '4611722333':
            case '4423949594':
            case '4121671642':
            case '4613406804':
            case '4121290926':
            case '4611443320':
            case '4121069026':
            case '4121053227':
            case '4121236500':
            case '4611451376':
            case '4612220427':
            case '4661042605':
            case '4611226923':
            case '4613505495':
            case '4613804988':
            case '4612993416':
            case '4622982802':
            case '4611410374':
            case '4612272922':
            case '4613528406':
            case '4612125225':
            case '4666691001':
            case '4613124634':
            case '4661208529':
                if (
                    !in_array($parameters['idMunicipio'], [
                        4,
                        5,
                        7,
                        9,
                        11,
                        35,
                        39,
                        44,
                    ])
                ) {
                    //R6
                    $response = [
                        'success' => true,
                        'results' => false,
                        'errors' => [],
                        'data' => [],
                        'message' =>
                            '¡Error de Captura: Municipio Restringido!',
                    ];
                    return response()->json($response, 200);
                }
                break;
            case '4777541653':
            case '4776389631':
            case '4773363518':
            case '4771887432':
            case '4774658309':
            case '4772731389':
            case '4776709249':
            case '4773979106':
            case '4773978435':
            case '4771919713':
            case '4777245690':
            case '4774756497':
            case '4151039437':
            case '4772277568':
            case '4773282797':
            case '4771709665':
                if (!in_array($parameters['idMunicipio'], [20])) {
                    //R7
                    $response = [
                        'success' => true,
                        'results' => false,
                        'errors' => [],
                        'data' => [],
                        'message' =>
                            '¡Error de Captura: Municipio Restringido!',
                    ];
                    return response()->json($response, 200);
                }
                break;
            #Capturadores de regiones 1, 2, 4, 5 y 6
            case '4686821807':
            case '4681324865':
            case '4611102229':
                if (
                    !in_array($parameters['idMunicipio'], [
                        6,
                        13,
                        32,
                        33,
                        34,
                        40,
                        43,
                        45,
                        3,
                        14,
                        22,
                        29,
                        30,
                        1,
                        8,
                        12,
                        16,
                        23,
                        24,
                        42,
                        2,
                        10,
                        18,
                        19,
                        21,
                        28,
                        36,
                        38,
                        41,
                        46,
                        4,
                        5,
                        7,
                        9,
                        11,
                        35,
                        39,
                        44,
                    ])
                ) {
                    $response = [
                        'success' => true,
                        'results' => false,
                        'errors' => [],
                        'data' => [],
                        'message' =>
                            '¡Error de Captura: Municipio Restringido!',
                    ];
                    return response()->json($response, 200);
                }
                break;
            // if($parameters['idMunicipio'] !=  1 &&
            //     $parameters['idMunicipio'] != 23){
            //         $response =  ['success'=>true,'results'=>false,
            //         'errors'=>[],'data'=>[], 'message'=>'¡Error de Captura: Municipio Restringido!'];
            //         return response()->json($response,200);
            // }
            // break;
            /* case '4681011231':
            case '4681138635':
            case '4681068011':
            case '4681044338':
            case '4681048137':
            
                if($parameters['idMunicipio'] !=  33){
                    $response =  ['success'=>true,'results'=>false,
                    'errors'=>[],'data'=>[], 'message'=>'¡Error de Captura: Municipio Restringido!'];
                    return response()->json($response,200);
                }  
                break;
            case '4451012647':
            case '4451579431':
            case '4451176809':
                if($parameters['idMunicipio'] !=  41){
                    $response =  ['success'=>true,'results'=>false,
                    'errors'=>[],'data'=>[], 'message'=>'¡Error de Captura: Municipio Restringido!'];
                    return response()->json($response,200);
                }  
                break;
            case '4661034795':
            case '4613288315':
            case '4661088895':
                if($parameters['idMunicipio'] !=  36){
                    $response =  ['success'=>true,'results'=>false,
                    'errors'=>[],'data'=>[], 'message'=>'¡Error de Captura: Municipio Restringido!'];
                    return response()->json($response,200);
                }  
                break;
            case '4192708235':
                if($parameters['idMunicipio'] !=  40){
                    $response =  ['success'=>true,'results'=>false,
                    'errors'=>[],'data'=>[], 'message'=>'¡Error de Captura: Municipio Restringido!'];
                    return response()->json($response,200);
                }  
                break;
            case '4281020861':
            case '4281031415':
                if($parameters['idMunicipio'] !=  22){
                    $response =  ['success'=>true,'results'=>false,
                    'errors'=>[],'data'=>[], 'message'=>'¡Error de Captura: Municipio Restringido!'];
                    return response()->json($response,200);
                }  
                break;
            case '4621399532':
            case '4777667978':
            case '4281207183':
            case '4281317132':
                if($parameters['idMunicipio'] !=  30){
                    $response =  ['success'=>true,'results'=>false,
                    'errors'=>[],'data'=>[], 'message'=>'¡Error de Captura: Municipio Restringido!'];
                    return response()->json($response,200);
                }  
                break;
            case '4612088982':
                if($parameters['idMunicipio'] !=  11){
                    $response =  ['success'=>true,'results'=>false,
                    'errors'=>[],'data'=>[], 'message'=>'¡Error de Captura: Municipio Restringido!'];
                    return response()->json($response,200);
                }  
                break;   
            case '4191207255':
            case '4191186803':
                
                if($parameters['idMunicipio'] !=  13){
                    $response =  ['success'=>true,'results'=>false,
                    'errors'=>[],'data'=>[], 'message'=>'¡Error de Captura: Municipio Restringido!'];
                    return response()->json($response,200);
                }  
                break; 
            case '4761415014':
            case '4761074731':
            case '4771809999':
            case '4621022833':
            case '4623327778':
            case '4622237030':
            case '4621783845':
                if($parameters['idMunicipio'] !=  17){
                    $response =  ['success'=>true,'results'=>false,
                    'errors'=>[],'data'=>[], 'message'=>'¡Error de Captura: Municipio Restringido!'];
                    return response()->json($response,200);
                }  
                break;  
            case '4761415014':
            case '4761074731':
            case '4771809999':
            case '4621022833':
            case '4623327778':
            case '4622237030':
            case '4621783845':
            case '4641065250':
            case '4641115066':
            case '4641234019':
            case '4641377563':
                if($parameters['idMunicipio'] !=  27){
                    $response =  ['success'=>true,'results'=>false,
                    'errors'=>[],'data'=>[], 'message'=>'¡Error de Captura: Municipio Restringido!'];
                    return response()->json($response,200);
                }  
                break; 

            case '4423949594':
            case '4121053227':
            case '4111543012':
            if($parameters['idMunicipio'] !=  44){
                $response =  ['success'=>true,'results'=>false,
                'errors'=>[],'data'=>[], 'message'=>'¡Error de Captura: Municipio Restringido!'];
                return response()->json($response,200);
            }  
            break;
            case '4776389631':
                if($parameters['idMunicipio'] !=  20){
                    $response =  ['success'=>true,'results'=>false,
                    'errors'=>[],'data'=>[], 'message'=>'¡Error de Captura: Municipio Restringido!'];
                    return response()->json($response,200);
                }  
                break; 
            case '4661082621':
                if($parameters['idMunicipio'] !=  28){
                    $response =  ['success'=>true,'results'=>false,
                    'errors'=>[],'data'=>[], 'message'=>'¡Error de Captura: Municipio Restringido!'];
                    return response()->json($response,200);
                }  
                break;
            case '4211103870':
                if($parameters['idMunicipio'] !=  38){
                    $response =  ['success'=>true,'results'=>false,
                    'errors'=>[],'data'=>[], 'message'=>'¡Error de Captura: Municipio Restringido!'];
                    return response()->json($response,200);
                }  
                break;
            case '4773662301':
                break;
            
            case '4622982802':
            case '4111175777':
                if($parameters['idMunicipio'] !=  11 && $parameters['idMunicipio'] !=  44 ){
                    $response =  ['success'=>true,'results'=>false,
                    'errors'=>[],'data'=>[], 'message'=>'¡Error de Captura: Municipio Restringido!'];
                    return response()->json($response,200);
                }  
                break;

            case '4613505495':
            case '4111010576':
            case '4613528406':
            case '4612272922':
                if($parameters['idMunicipio'] !=  7){
                    $response =  ['success'=>true,'results'=>false,
                    'errors'=>[],'data'=>[], 'message'=>'¡Error de Captura: Municipio Restringido!'];
                    return response()->json($response,200);
                }  
                break; */

            default:
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' => [],
                    'data' => [],
                    'message' =>
                        '¡Error de Captura: No cuenta con permisos para realizar capturas de vales!',
                ];
                return response()->json($response, 200);
                break;
        }

        /* //Municipio restringido
        if($parameters['idMunicipio'] !=  20){
            $response =  ['success'=>true,'results'=>false,
            'errors'=>[],'data'=>[], 'message'=>'¡Error de Captura: Municipio Restringido!'];
            return response()->json($response,200);
        }   */

        /* if(!((DB::select('select now() as time')[0]->time) < '2021-04-30 01:00:00')){
            $response =  ['success'=>true,'results'=>false,
            'errors'=>[],'data'=>[], 'message'=>'¡Error de Captura: Registro Fuera de Tiempo!'];
            return response()->json($response,200);
        } */

        // //suspendido
        // $response =  ['success'=>false,'results'=>false,
        // 'errors'=>'El sistema se encuentra en mantenimiento.', 'data'=>[], 'message'=>'El sistema se encuentra en mantenimiento.!'];

        // return response()->json($response,200);

        if (
            DB::table('users_cancelados')
                ->where('idUser', $user->id)
                ->first()
        ) {
            $response = [
                'success' => true,
                'results' => false,
                'errors' => $v->errors(),
                'data' => [],
                'message' =>
                    '¡Error de permisos: No tiene derechos para ejecutar esta accion, contacte al administrador!' .
                    $v->errors()->first() .
                    '!',
            ];
        }

        //antes de guardar, buscar el ambito de la localidad si es U o R,
        //dividir TotalIngresos dividido entre NumeroPersonas
        // si es superior al monto de config : Rechazado
        //ERROR Total de ingresos por persona exede el monto permitido en localidad (U o R)
        $validacion_curp = DB::table('vales')
            ->select(
                'vales.id',
                DB::raw('LPAD(HEX(vales.id),6,0) as ClaveUnica')
            )
            ->where('CURP', '=', $parameters['CURP'])
            ->whereRaw('year(vales.FechaSolicitud) = 2021')
            ->get();
        if (count($validacion_curp) > 1) {
            return [
                'success' => true,
                'results' => false,
                'data' => [],
                'message' =>
                    'Error CURP: ¡La CURP ' .
                    $parameters['CURP'] .
                    ' ya cuenta con mas de 2 solicitudes!',
            ];
        }
        if ($parameters['NumeroPersonas'] <= 0) {
            return [
                'success' => true,
                'results' => false,
                'data' => [],
                'message' =>
                    'Error en el número de personas: ¡El número de personas debe ser 1 o mayor!',
            ];
        }

        $validar_monto = DB::table('config')->first();
        $ambito_localidad = DB::table('et_cat_localidad')
            ->where('et_cat_localidad.Id', '=', $parameters['idLocalidad'])
            ->first();
        $parameters['TotalIngresos'] =
            floatval($parameters['IngresoPercibido']) +
            floatval($parameters['OtrosIngresos']);

        if (strcmp($ambito_localidad->Ambito, 'U') == 0) {
            if (
                $parameters['TotalIngresos'] / $parameters['NumeroPersonas'] >
                $validar_monto->MontoUrbano
            ) {
                return [
                    'success' => true,
                    'results' => false,
                    'data' => [],
                    'message' =>
                        'Error: ¡El total de ingresos por persona excede el monto permitido en la localidad urbana!',
                ];
            }
        } else {
            if (
                $parameters['TotalIngresos'] / $parameters['NumeroPersonas'] >
                $validar_monto->MontoRural
            ) {
                return [
                    'success' => true,
                    'results' => false,
                    'data' => [],
                    'message' =>
                        'Error: ¡El total de ingresos por persona excede el monto permitido en la localidad rural!',
                ];
            }
        }

        $validar_espacio = DB::select(
            'Select M.region,  M.idMunicipio, M.Municipio, M.ApoyoAmpliado, A.Total as Avance, if(A.Total>=M.ApoyoAmpliado, 0, 1) as Captura
        From
        (select * from meta_municipio where Ejercicio=' .
                $parameters['Ejercicio'] .
                ' and  idMunicipio = ' .
                $parameters['idMunicipio'] .
                ') as M
        left join 
        (select count(id) Total, idMunicipio from vales where (idStatus=1 or idStatus=5) and idIncidencia=1   
        and YEAR(FechaSolicitud)=' .
                $parameters['Ejercicio'] .
                ' and  idMunicipio = ' .
                $parameters['idMunicipio'] .
                '
        group by idMunicipio) A
        on (A.idMunicipio = M.idMunicipio)'
        );

        if (!$validar_espacio) {
            return [
                'success' => true,
                'results' => false,
                'data' => [],
                'message' =>
                    'Error: El municipio no tiene definida una meta de vales para el Ejercicio ' .
                    $parameters['Ejercicio'],
            ];
        }

        if ($validar_espacio[0]->Captura == 1) {
            $user = auth()->user();
            if (!isset($parameters['OcupacionOtro'])) {
                $parameters['OcupacionOtro'] = 0;
            }
            $parameters['UserCreated'] = $user->id;
            $parameters['UserUpdated'] = $user->id;
            $parameters['UserOwned'] = $parameters['idArticulador'];
            $parameters['TotalIngresos'] =
                floatval($parameters['IngresoPercibido']) +
                floatval($parameters['OtrosIngresos']);

            $vale_ = VVales::create($parameters);
            //$vale =  VVales::find($vale_->id);
            $vale = DB::table('vales')
                ->select(
                    'vales.*',
                    DB::raw('LPAD(HEX(vales.id),6,0) as ClaveUnica')
                )
                ->where('vales.id', '=', $vale_->id)
                ->first();
            return ['success' => true, 'results' => true, 'data' => $vale];
        } else {
            return [
                'success' => true,
                'results' => false,
                'data' => [],
                'message' =>
                    'Error: ¡El municipio ya excedió el número de solicitudes!',
            ];
        }
    }

    function updateVales(Request $request)
    {
        $v = Validator::make($request->all(), [
            'id' => 'required',
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
        $parameters = $request->all();
        unset($parameters['idCedulaSolicitud']);
        $user = auth()->user();
        if (
            DB::table('users_cancelados')
                ->where('idUser', $user->id)
                ->first()
        ) {
            $response = [
                'success' => true,
                'results' => false,
                'errors' => $v->errors(),
                'data' => [],
                'message' =>
                    '¡Error de permisos: No tiene derechos para ejecutar esta accion, contacte al administrador!' .
                    $v->errors()->first() .
                    '!',
            ];
        }
        /* $response =  ['success'=>true,'results'=>false,
                    'errors'=>[],'data'=>[], 'message'=>'¡Error de Captura: Captura restringida!'];
        return response()->json($response,200); */
        //si el vale esta bloqueado regreso error para decir que el vale esta bloqueado
        //'error vale bloqueado: No se puede modificar los valores del vale, pongase en contacto con el administrador.'

        //else lo normal;
        //Validacion nueva tambien la coloco en el update para que no quieran modificar
        $vale_bloqueado = VVales::find($parameters['id'])->Bloqueado;

        //dd($vale_bloqueado);
        if ($vale_bloqueado == 1) {
            return [
                'success' => true,
                'results' => false,
                'data' => [],
                'message' =>
                    'Error Vale Bloqueado: ¡No se puede modificar los valores del vale, póngase en contacto con el administrador!',
            ];
        } else {
            $user = auth()->user();
            $parameters['UserUpdated'] = $user->id;
            //$parameters['IngresoPercibido'] = $user->id;
            //$parameters['OtrosIngresos'] = $user->id;
            if (isset($parameters['NumeroPersonas'])) {
                if ($parameters['NumeroPersonas'] <= 0) {
                    return [
                        'success' => true,
                        'results' => false,
                        'data' => [],
                        'message' =>
                            'Error en el número de personas: ¡El número de personas debe ser 1 o mayor!',
                    ];
                }
            }
            if (
                isset($parameters['IngresoPercibido']) &&
                isset($parameters['OtrosIngresos'])
            ) {
                if (!isset($parameters['idLocalidad'])) {
                    $parameters['idLocalidad'] = $vale = VVales::find(
                        $parameters['id']
                    )->idLocalidad;
                }
                if (!isset($parameters['NumeroPersonas'])) {
                    $parameters['NumeroPersonas'] = $vale = VVales::find(
                        $parameters['id']
                    )->NumeroPersonas;
                }
                $validar_monto = DB::table('config')->first();
                $ambito_localidad = DB::table('et_cat_localidad')
                    ->where(
                        'et_cat_localidad.Id',
                        '=',
                        $parameters['idLocalidad']
                    )
                    ->first();
                $parameters['TotalIngresos'] =
                    floatval($parameters['IngresoPercibido']) +
                    floatval($parameters['OtrosIngresos']);

                if (strcmp($ambito_localidad->Ambito, 'U') == 0) {
                    if (
                        $parameters['TotalIngresos'] /
                            $parameters['NumeroPersonas'] >
                        $validar_monto->MontoUrbano
                    ) {
                        return [
                            'success' => true,
                            'results' => false,
                            'data' => [],
                            'message' =>
                                'Error: ¡El total de ingresos por persona excede el monto permitido en la localidad urbana!',
                        ];
                    }
                } else {
                    if (
                        $parameters['TotalIngresos'] /
                            $parameters['NumeroPersonas'] >
                        $validar_monto->MontoRural
                    ) {
                        return [
                            'success' => true,
                            'results' => false,
                            'data' => [],
                            'message' =>
                                'Error: ¡El total de ingresos por persona excede el monto permitido en la localidad rural!',
                        ];
                    }
                }
            } elseif (isset($parameters['IngresoPercibido'])) {
                if (!isset($parameters['idLocalidad'])) {
                    $parameters['idLocalidad'] = $vale = VVales::find(
                        $parameters['id']
                    )->idLocalidad;
                }
                if (!isset($parameters['NumeroPersonas'])) {
                    $parameters['NumeroPersonas'] = $vale = VVales::find(
                        $parameters['id']
                    )->NumeroPersonas;
                }
                $validar_monto = DB::table('config')->first();
                $ambito_localidad = DB::table('et_cat_localidad')
                    ->where(
                        'et_cat_localidad.Id',
                        '=',
                        $parameters['idLocalidad']
                    )
                    ->first();
                $vale = VVales::find($parameters['id']);
                $parameters['TotalIngresos'] =
                    floatval($parameters['IngresoPercibido']) +
                    floatval($vale->OtrosIngresos);
                if (strcmp($ambito_localidad->Ambito, 'U') == 0) {
                    if (
                        $parameters['TotalIngresos'] /
                            $parameters['NumeroPersonas'] >
                        $validar_monto->MontoUrbano
                    ) {
                        return [
                            'success' => true,
                            'results' => false,
                            'data' => [],
                            'message' =>
                                'Error: ¡El total de ingresos por persona excede el monto permitido en la localidad urbana!',
                        ];
                    }
                } else {
                    if (
                        $parameters['TotalIngresos'] /
                            $parameters['NumeroPersonas'] >
                        $validar_monto->MontoRural
                    ) {
                        return [
                            'success' => true,
                            'results' => false,
                            'data' => [],
                            'message' =>
                                'Error: ¡El total de ingresos por persona excede el monto permitido en la localidad rural!',
                        ];
                    }
                }
            } elseif (isset($parameters['OtrosIngresos'])) {
                if (!isset($parameters['idLocalidad'])) {
                    $parameters['idLocalidad'] = $vale = VVales::find(
                        $parameters['id']
                    )->idLocalidad;
                }
                if (!isset($parameters['NumeroPersonas'])) {
                    $parameters['NumeroPersonas'] = $vale = VVales::find(
                        $parameters['id']
                    )->NumeroPersonas;
                }
                $validar_monto = DB::table('config')->first();
                $ambito_localidad = DB::table('et_cat_localidad')
                    ->where(
                        'et_cat_localidad.Id',
                        '=',
                        $parameters['idLocalidad']
                    )
                    ->first();
                $vale = VVales::find($parameters['id']);
                $parameters['TotalIngresos'] =
                    floatval($parameters['OtrosIngresos']) +
                    floatval($vale->IngresoPercibido);

                if (strcmp($ambito_localidad->Ambito, 'U') == 0) {
                    if (
                        $parameters['TotalIngresos'] /
                            $parameters['NumeroPersonas'] >
                        $validar_monto->MontoUrbano
                    ) {
                        return [
                            'success' => true,
                            'results' => false,
                            'data' => [],
                            'message' =>
                                'Error: ¡El total de ingresos por persona excede el monto permitido en la localidad urbana!',
                        ];
                    }
                } else {
                    if (
                        $parameters['TotalIngresos'] /
                            $parameters['NumeroPersonas'] >
                        $validar_monto->MontoRural
                    ) {
                        return [
                            'success' => true,
                            'results' => false,
                            'data' => [],
                            'message' =>
                                'Error: ¡El total de ingresos por persona excede el monto permitido en la localidad rural!',
                        ];
                    }
                }
            }

            $vale = VVales::find($parameters['id']);
            $vale_insert = DB::table('vales')
                ->select(
                    'vales.*',
                    'vales.id as idVale',
                    DB::raw(
                        $user->id .
                            " as UserEdito, '" .
                            date('Y:m:d h:m:s') .
                            "' as UserEditoFecha"
                    )
                )
                ->where('id', $parameters['id'])
                ->first();

            if (!$vale) {
                return [
                    'success' => true,
                    'results' => false,
                    'errors' => 'El vale que desea actualizar no existe.',
                    'data' => [],
                ];
            }

            //insert
            $vale_insert = (array) $vale_insert;
            $vale_insert = Arr::except($vale_insert, 'id');
            $res = DB::table('vales_history')->insert($vale_insert);

            $vale->update($parameters);
            $vale_ = DB::table('vales')
                ->select(
                    'vales.*',
                    DB::raw('LPAD(HEX(vales.id),6,0) as ClaveUnica')
                )
                ->where('vales.id', '=', $vale->id)
                ->first();

            return ['success' => true, 'results' => true, 'data' => $vale_];
        }
    }

    function updateRecepcionDocumento(Request $request)
    {
        $v = Validator::make($request->all(), [
            'id' => 'required',
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
        $parameters = $request->all();
        $user = auth()->user();
        if (
            DB::table('users_cancelados')
                ->where('idUser', $user->id)
                ->first()
        ) {
            $response = [
                'success' => true,
                'results' => false,
                'errors' => $v->errors(),
                'data' => [],
                'message' =>
                    '¡Error de permisos: No tiene derechos para ejecutar esta accion, contacte al administrador!' .
                    $v->errors()->first() .
                    '!',
            ];
        }
        //si el vale esta bloqueado regreso error para decir que el vale esta bloqueado
        //'error vale bloqueado: No se puede modificar los valores del vale, pongase en contacto con el administrador.'

        //else lo normal;
        //Validacion nueva tambien la coloco en el update para que no quieran modificar
        $vale_bloqueado = VVales::find($parameters['id'])
            ->isDocumentacionEntrega;

        //dd($vale_bloqueado);
        if ($vale_bloqueado == 1) {
            return [
                'success' => true,
                'results' => false,
                'data' => [],
                'message' =>
                    'Error Vale Recepcionado: ¡No se puede recepcionar nuevamente, póngase en contacto con el administrador!',
            ];
        } else {
            $user = auth()->user();
            $parameters['UserUpdated'] = $user->id;
            //$parameters['IngresoPercibido'] = $user->id;
            //$parameters['OtrosIngresos'] = $user->id;
            if (isset($parameters['NumeroPersonas'])) {
                if ($parameters['NumeroPersonas'] <= 0) {
                    return [
                        'success' => true,
                        'results' => false,
                        'data' => [],
                        'message' =>
                            'Error en el número de personas: ¡El número de personas debe ser 1 o mayor!',
                    ];
                }
            }
            if (
                isset($parameters['IngresoPercibido']) &&
                isset($parameters['OtrosIngresos'])
            ) {
                if (!isset($parameters['idLocalidad'])) {
                    $parameters['idLocalidad'] = $vale = VVales::find(
                        $parameters['id']
                    )->idLocalidad;
                }
                if (!isset($parameters['NumeroPersonas'])) {
                    $parameters['NumeroPersonas'] = $vale = VVales::find(
                        $parameters['id']
                    )->NumeroPersonas;
                }
                $validar_monto = DB::table('config')->first();
                $ambito_localidad = DB::table('et_cat_localidad')
                    ->where(
                        'et_cat_localidad.Id',
                        '=',
                        $parameters['idLocalidad']
                    )
                    ->first();
                $parameters['TotalIngresos'] =
                    floatval($parameters['IngresoPercibido']) +
                    floatval($parameters['OtrosIngresos']);

                if (strcmp($ambito_localidad->Ambito, 'U') == 0) {
                    if (
                        $parameters['TotalIngresos'] /
                            $parameters['NumeroPersonas'] >
                        $validar_monto->MontoUrbano
                    ) {
                        return [
                            'success' => true,
                            'results' => false,
                            'data' => [],
                            'message' =>
                                'Error: ¡El total de ingresos por persona excede el monto permitido en la localidad urbana!',
                        ];
                    }
                } else {
                    if (
                        $parameters['TotalIngresos'] /
                            $parameters['NumeroPersonas'] >
                        $validar_monto->MontoRural
                    ) {
                        return [
                            'success' => true,
                            'results' => false,
                            'data' => [],
                            'message' =>
                                'Error: ¡El total de ingresos por persona excede el monto permitido en la localidad rural!',
                        ];
                    }
                }
            } elseif (isset($parameters['IngresoPercibido'])) {
                if (!isset($parameters['idLocalidad'])) {
                    $parameters['idLocalidad'] = $vale = VVales::find(
                        $parameters['id']
                    )->idLocalidad;
                }
                if (!isset($parameters['NumeroPersonas'])) {
                    $parameters['NumeroPersonas'] = $vale = VVales::find(
                        $parameters['id']
                    )->NumeroPersonas;
                }
                $validar_monto = DB::table('config')->first();
                $ambito_localidad = DB::table('et_cat_localidad')
                    ->where(
                        'et_cat_localidad.Id',
                        '=',
                        $parameters['idLocalidad']
                    )
                    ->first();
                $vale = VVales::find($parameters['id']);
                $parameters['TotalIngresos'] =
                    floatval($parameters['IngresoPercibido']) +
                    floatval($vale->OtrosIngresos);
                if (strcmp($ambito_localidad->Ambito, 'U') == 0) {
                    if (
                        $parameters['TotalIngresos'] /
                            $parameters['NumeroPersonas'] >
                        $validar_monto->MontoUrbano
                    ) {
                        return [
                            'success' => true,
                            'results' => false,
                            'data' => [],
                            'message' =>
                                'Error: ¡El total de ingresos por persona excede el monto permitido en la localidad urbana!',
                        ];
                    }
                } else {
                    if (
                        $parameters['TotalIngresos'] /
                            $parameters['NumeroPersonas'] >
                        $validar_monto->MontoRural
                    ) {
                        return [
                            'success' => true,
                            'results' => false,
                            'data' => [],
                            'message' =>
                                'Error: ¡El total de ingresos por persona excede el monto permitido en la localidad rural!',
                        ];
                    }
                }
            } elseif (isset($parameters['OtrosIngresos'])) {
                if (!isset($parameters['idLocalidad'])) {
                    $parameters['idLocalidad'] = $vale = VVales::find(
                        $parameters['id']
                    )->idLocalidad;
                }
                if (!isset($parameters['NumeroPersonas'])) {
                    $parameters['NumeroPersonas'] = $vale = VVales::find(
                        $parameters['id']
                    )->NumeroPersonas;
                }
                $validar_monto = DB::table('config')->first();
                $ambito_localidad = DB::table('et_cat_localidad')
                    ->where(
                        'et_cat_localidad.Id',
                        '=',
                        $parameters['idLocalidad']
                    )
                    ->first();
                $vale = VVales::find($parameters['id']);
                $parameters['TotalIngresos'] =
                    floatval($parameters['OtrosIngresos']) +
                    floatval($vale->IngresoPercibido);

                if (strcmp($ambito_localidad->Ambito, 'U') == 0) {
                    if (
                        $parameters['TotalIngresos'] /
                            $parameters['NumeroPersonas'] >
                        $validar_monto->MontoUrbano
                    ) {
                        return [
                            'success' => true,
                            'results' => false,
                            'data' => [],
                            'message' =>
                                'Error: ¡El total de ingresos por persona excede el monto permitido en la localidad urbana!',
                        ];
                    }
                } else {
                    if (
                        $parameters['TotalIngresos'] /
                            $parameters['NumeroPersonas'] >
                        $validar_monto->MontoRural
                    ) {
                        return [
                            'success' => true,
                            'results' => false,
                            'data' => [],
                            'message' =>
                                'Error: ¡El total de ingresos por persona excede el monto permitido en la localidad rural!',
                        ];
                    }
                }
            }

            $vale = VVales::find($parameters['id']);
            $vale_insert = DB::table('vales')
                ->select(
                    'vales.*',
                    'vales.id as idVale',
                    DB::raw(
                        $user->id .
                            " as UserEdito, '" .
                            date('Y:m:d h:m:s') .
                            "' as UserEditoFecha"
                    )
                )
                ->where('id', $parameters['id'])
                ->first();

            if (!$vale) {
                return [
                    'success' => true,
                    'results' => false,
                    'errors' => 'El vale que desea actualizar no existe.',
                    'data' => [],
                ];
            }

            //insert
            $vale_insert = (array) $vale_insert;
            $vale_insert = Arr::except($vale_insert, 'id');
            $res = DB::table('vales_history')->insert($vale_insert);

            $vale->update($parameters);
            $vale_ = DB::table('vales')
                ->select(
                    'vales.*',
                    DB::raw('LPAD(HEX(vales.id),6,0) as ClaveUnica')
                )
                ->where('vales.id', '=', $vale->id)
                ->first();

            return ['success' => true, 'results' => true, 'data' => $vale_];
        }
    }

    function updateEntregaVales(Request $request)
    {
        $v = Validator::make($request->all(), [
            'id' => 'required',
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
        $parameters = $request->all();
        $user = auth()->user();
        if (
            DB::table('users_cancelados')
                ->where('idUser', $user->id)
                ->first()
        ) {
            $response = [
                'success' => true,
                'results' => false,
                'errors' => $v->errors(),
                'data' => [],
                'message' =>
                    '¡Error de permisos: No tiene derechos para ejecutar esta accion, contacte al administrador!' .
                    $v->errors()->first() .
                    '!',
            ];
        }
        //si el vale esta bloqueado regreso error para decir que el vale esta bloqueado
        //'error vale bloqueado: No se puede modificar los valores del vale, pongase en contacto con el administrador.'

        $user = auth()->user();
        $parameters['UserUpdated'] = $user->id;
        //$parameters["updated_at"] =date('Y:m:d h:m:s');
        //$parameters['IngresoPercibido'] = $user->id;
        //$parameters['OtrosIngresos'] = $user->id;

        $vale = VVales::find($parameters['id']);
        $vale_insert = DB::table('vales')
            ->select(
                'vales.*',
                'vales.id as idVale',
                DB::raw(
                    $user->id .
                        " as UserEdito, '" .
                        date('Y:m:d h:m:s') .
                        "' as UserEditoFecha"
                )
            )
            ->where('id', $parameters['id'])
            ->first();

        if (!$vale) {
            return [
                'success' => true,
                'results' => false,
                'errors' => 'El vale que desea actualizar no existe.',
                'data' => [],
            ];
        }

        //insert
        $vale_insert = (array) $vale_insert;
        $vale_insert = Arr::except($vale_insert, 'id');
        $res = DB::table('vales_history')->insert($vale_insert);

        //dd($parameters);

        $vale->update($parameters);

        return [
            'success' => true,
            'results' => true,
            'message' => 'Se actualizo correctamente!',
        ];
    }

    function getSolicitudesPorCURP(Request $request)
    {
        $parameters = $request->all();
        $res = DB::table('vales')
            ->select(
                DB::raw('LPAD(HEX(vales.id),6,0) as Folio'),
                DB::raw('DATE(vales.FechaSolicitud) as FechaSolicitud')
            )
            ->where('CURP', $parameters['CURP'])
            ->get();
        return ['success' => true, 'results' => true, 'data' => $res];
    }
    function getHistoryVales(Request $request)
    {
        $parameters = $request->all();

        try {
            $res = DB::table('vales_history')
                ->select(
                    'vales_history.id',
                    DB::raw(
                        'LPAD(HEX(vales_history.idVale),6,0) as ClaveUnica'
                    ),
                    'vales_history.TelRecados',
                    'vales_history.Compania',
                    'vales_history.TelFijo',
                    'vales_history.FolioSolicitud',
                    'vales_history.FechaSolicitud',
                    'vales_history.CURP',
                    'vales_history.Nombre',
                    'vales_history.Paterno',
                    'vales_history.Materno',
                    'vales_history.Sexo',
                    'vales_history.FechaNacimiento',
                    'vales_history.Calle',
                    'vales_history.NumExt',
                    'vales_history.IngresoPercibido',
                    'vales_history.OtrosIngresos',
                    'vales_history.NumeroPersonas',
                    'vales_history.OcupacionOtro',
                    'vales_history.Ocupacion',
                    'vales_history.NumInt',
                    'vales_history.Colonia',
                    'vales_history.CP',
                    'vales_history.idMunicipio',
                    'et_cat_municipio.Id AS IdM',
                    'et_cat_municipio.Nombre AS Municipio',
                    'et_cat_municipio.SubRegion AS Region',
                    'vales_history.idLocalidad',
                    'et_cat_localidad.Id AS Clave',
                    'et_cat_localidad.Nombre AS Localidad',
                    'vales_history.TelFijo',
                    'vales_history.TelCelular',
                    'vales_history.isEntregado',
                    'vales_history.entrega_at',
                    'vales_history.CorreoElectronico',
                    'vales_history.FechaDocumentacion',
                    'vales_history.isDocumentacionEntrega',
                    'vales_history.idUserDocumentacion',
                    'vales_history.isEntregadoOwner',
                    'vales_history.idUserReportaEntrega',
                    'vales_history.ComentarioEntrega',
                    'vales_history.FechaReportaEntrega',
                    'vales_history.idStatus',
                    'vales_status.id as idES',
                    'vales_status.Estatus',
                    'vales_status.Clave as ClaveA',
                    //userEdito
                    'vales_history.UserEditoFecha',
                    'vales_history.UserEdito',
                    'userEdito.id as idEdito',
                    'userEdito.Nombre as NombreEdito',
                    'userEdito.Paterno as PaternoEdito',
                    'userEdito.Materno as MaternoEdito',

                    //Datos Usuario Updated
                    'vales_history.created_at',
                    'vales_history.updated_at',
                    //Datos Usuario created
                    'users.id as idE',
                    'users.email as emailE',
                    'users.Nombre as NombreE',
                    'users.Paterno as PaternoE',
                    'users.Materno as MaternoE',
                    'users.idTipoUser',
                    'cat_usertipo.id as idEA',
                    'cat_usertipo.TipoUser as TipoUserEA',
                    'cat_usertipo.Clave as ClaveEA',
                    'vales_history.UserUpdated',
                    //Datos Usuario updated
                    'usersB.id as idF',
                    'usersB.email as emailF',
                    'usersB.Nombre as NombreF',
                    'usersB.Paterno as PaternoF',
                    'usersB.Materno as MaternoF',
                    'usersB.idTipoUser',
                    'cat_usertipoB.id as idFA',
                    'cat_usertipoB.TipoUser as TipoUserFA',
                    'cat_usertipoB.Clave as ClaveFA',
                    'vales_history.UserOwned',
                    //Datos Usuario owned
                    'usersC.id as idO',
                    'usersC.email as emailO',
                    'usersC.Nombre as NombreO',
                    'usersC.Paterno as PaternoO',
                    'usersC.Materno as MaternoO',
                    'usersC.idTipoUser',
                    'cat_usertipoC.id as idGO',
                    'cat_usertipoC.TipoUser as TipoUserGO',
                    'cat_usertipoC.Clave as ClaveGO'
                )
                ->leftJoin(
                    'et_cat_municipio',
                    'et_cat_municipio.Id',
                    '=',
                    'vales_history.idMunicipio'
                )
                ->leftJoin(
                    'et_cat_localidad',
                    'et_cat_localidad.Id',
                    '=',
                    'vales_history.idLocalidad'
                )
                ->leftJoin('vales_status', 'vales_status.id', '=', 'idStatus')
                ->leftJoin(
                    'users',
                    'users.id',
                    '=',
                    'vales_history.UserCreated'
                )
                ->leftJoin(
                    'cat_usertipo',
                    'cat_usertipo.id',
                    '=',
                    'users.idTipoUser'
                )
                ->leftJoin(
                    'users as usersB',
                    'usersB.id',
                    '=',
                    'vales_history.UserUpdated'
                )
                ->leftJoin(
                    'cat_usertipo as cat_usertipoB',
                    'cat_usertipoB.id',
                    '=',
                    'usersB.idTipoUser'
                )
                ->leftJoin(
                    'users as usersC',
                    'usersC.id',
                    '=',
                    'vales_history.UserOwned'
                )
                ->leftJoin(
                    'users as usersCretaed',
                    'usersCretaed.id',
                    '=',
                    'vales_history.UserCreated'
                )
                ->leftJoin(
                    'users as userEdito',
                    'userEdito.id',
                    '=',
                    'vales_history.UserEdito'
                )
                ->leftJoin(
                    'cat_usertipo as cat_usertipoC',
                    'cat_usertipoC.id',
                    '=',
                    'usersC.idTipoUser'
                );

            if (isset($parameters['Folio'])) {
                $valor_id = $parameters['Folio'];
                $res->where(function ($q) use ($valor_id) {
                    $q->where(
                        DB::raw('LPAD(HEX(vales_history.idVale),6,0)'),
                        'like',
                        '%' . $valor_id . '%'
                    );
                });
            }

            $page = $parameters['page'];
            $pageSize = $parameters['pageSize'];

            $startIndex = $page * $pageSize;
            if (isset($parameters['sorted'])) {
                for ($i = 0; $i < count($parameters['sorted']); $i++) {
                    if ($parameters['sorted'][$i]['desc'] === true) {
                        $res->orderBy($parameters['sorted'][$i]['id'], 'desc');
                    } else {
                        $res->orderBy($parameters['sorted'][$i]['id'], 'asc');
                    }
                }
            }

            $total = $res->count();
            $res = $res
                ->offset($startIndex)
                ->take($pageSize)
                ->get();

            $parameters_serializado = serialize($parameters);
            //$array = unserialize($parameters_serializado);

            $array_res = [];
            $temp = [];
            foreach ($res as $data) {
                $temp = [
                    'id' => $data->id,
                    'ClaveUnica' => $data->ClaveUnica,
                    'TelRecados' => $data->TelRecados,
                    'Compania' => $data->Compania,
                    'TelFijo' => $data->TelFijo,
                    'FolioSolicitud' => $data->FolioSolicitud,
                    'FechaSolicitud' => $data->FechaSolicitud,
                    'CURP' => $data->CURP,
                    'Nombre' => $data->Nombre,
                    'Paterno' => $data->Paterno,
                    'Materno' => $data->Materno,
                    'Sexo' => $data->Sexo,
                    'FechaNacimiento' => $data->FechaNacimiento,
                    'Calle' => $data->Calle,
                    'NumExt' => $data->NumExt,
                    'NumInt' => $data->NumInt,
                    'Colonia' => $data->Colonia,
                    'CP' => $data->CP,
                    'FechaDocumentacion' => $data->FechaDocumentacion,
                    'isDocumentacionEntrega' => $data->isDocumentacionEntrega,
                    'idUserDocumentacion' => $data->idUserDocumentacion,
                    'isEntregadoOwner' => $data->isEntregadoOwner,
                    'idUserReportaEntrega' => $data->idUserReportaEntrega,
                    'ComentarioEntrega' => $data->ComentarioEntrega,
                    'FechaReportaEntrega' => $data->FechaReportaEntrega,
                    'IngresoPercibido' => $data->IngresoPercibido,
                    'OtrosIngresos' => $data->OtrosIngresos,
                    'NumeroPersonas' => $data->NumeroPersonas,
                    'OcupacionOtro' => $data->OcupacionOtro,
                    'Ocupacion' => $data->Ocupacion,

                    'idMunicipio' => [
                        'id' => $data->IdM,
                        'Municipio' => $data->Municipio,
                        'Region' => $data->Region,
                    ],
                    'idLocalidad' => [
                        'id' => $data->Clave,
                        'Nombre' => $data->Localidad,
                    ],
                    'TelFijo' => $data->TelFijo,
                    'TelCelular' => $data->TelCelular,
                    'CorreoElectronico' => $data->CorreoElectronico,
                    'idStatus' => [
                        'id' => $data->idES,
                        'Clave' => $data->ClaveA,
                        'Estatus' => $data->Estatus,
                    ],
                    'UserEditoFecha' => $data->UserEditoFecha,
                    'UserEdito' => [
                        'id' => $data->idEdito,
                        'Nombre' => $data->NombreEdito,
                        'Paterno' => $data->PaternoEdito,
                        'Materno' => $data->MaternoEdito,
                    ],
                    'created_at' => $data->created_at,
                    'updated_at' => $data->updated_at,
                    'UserCreated' => [
                        'id' => $data->idE,
                        'email' => $data->emailE,
                        'Nombre' => $data->NombreE,
                        'Paterno' => $data->PaternoE,
                        'Materno' => $data->MaternoE,
                        'idTipoUser' => [
                            'id' => $data->idEA,
                            'TipoUser' => $data->TipoUserEA,
                            'Clave' => $data->ClaveEA,
                        ],
                    ],
                    'UserUpdated' => [
                        'id' => $data->idF,
                        'email' => $data->emailF,
                        'Nombre' => $data->NombreF,
                        'Paterno' => $data->PaternoF,
                        'Materno' => $data->MaternoF,
                        'idTipoUser' => [
                            'id' => $data->idFA,
                            'TipoUser' => $data->TipoUserFA,
                            'Clave' => $data->ClaveFA,
                        ],
                    ],
                    'UserOwned' => [
                        'id' => $data->idO,
                        'email' => $data->emailO,
                        'Nombre' => $data->NombreO,
                        'Paterno' => $data->PaternoO,
                        'Materno' => $data->MaternoO,
                        'idTipoUser' => [
                            'id' => $data->idGO,
                            'TipoUser' => $data->TipoUserGO,
                            'Clave' => $data->ClaveGO,
                        ],
                    ],
                ];

                array_push($array_res, $temp);
            }

            return [
                'success' => true,
                'results' => true,
                'total' => $total,
                'filtros' => $parameters['filtered'],
                'data' => $array_res,
            ];
        } catch (QueryException $e) {
            $errors = [
                'Clave' => '01',
            ];
            $response = [
                'success' => true,
                'results' => false,
                'total' => 0,
                'filtros' => $parameters['filtered'],
                'errors' => $e->getMessage(),
                'message' => 'Campo de consulta incorrecto',
            ];

            return response()->json($response, 200);
        }
    }

    function getValesInHistory(Request $request)
    {
        $parameters = $request->all();
        try {
            $resHistory = DB::table('vales_history')
                ->select('vales_history.idVale')
                ->groupBy('idVale')
                ->get();

            $arrayIDHistory = [];
            foreach ($resHistory as $data) {
                array_push($arrayIDHistory, $data->idVale);
            }
            $res = DB::table('vales')
                ->select(
                    'vales.id',
                    DB::raw('LPAD(HEX(vales.id),6,0) as ClaveUnica'),
                    'vales.TelRecados',
                    'vales.Compania',
                    'vales.TelFijo',
                    'vales.FolioSolicitud',
                    'vales.FechaSolicitud',
                    'vales.CURP',
                    'vales.Nombre',
                    'vales.Paterno',
                    'vales.Materno',
                    'vales.Sexo',
                    'vales.FechaNacimiento',
                    'vales.Calle',
                    'vales.NumExt',
                    'vales.NumInt',
                    'vales.IngresoPercibido',
                    'vales.OtrosIngresos',
                    'vales.NumeroPersonas',
                    'vales.OcupacionOtro',
                    'vales.Ocupacion',
                    'vales.Colonia',
                    'vales.CP',
                    'vales.idMunicipio',
                    'et_cat_municipio.Id AS IdM',
                    'et_cat_municipio.Nombre AS Municipio',
                    'et_cat_municipio.SubRegion AS Region',
                    'vales.idLocalidad',
                    'et_cat_localidad.Id AS Clave',
                    'et_cat_localidad.Nombre AS Localidad',
                    'vales.TelFijo',
                    'vales.TelCelular',
                    'vales.isEntregado',
                    'vales.entrega_at',
                    'vales.CorreoElectronico',
                    'vales.FechaDocumentacion',
                    'vales.isDocumentacionEntrega',
                    'vales.idUserDocumentacion',
                    'vales.isEntregadoOwner',
                    'vales.idUserReportaEntrega',
                    'vales.ComentarioEntrega',
                    'vales.FechaReportaEntrega',
                    'vales.idStatus',
                    'vales_status.id as idES',
                    'vales_status.Estatus',
                    'vales_status.Clave as ClaveA',
                    //userEdito
                    'valesHist.UserEditoFecha',
                    'valesHist.UserEdito',
                    'userEdito.id as idEdito',
                    'userEdito.Nombre as NombreEdito',
                    'userEdito.Paterno as PaternoEdito',
                    'userEdito.Materno as MaternoEdito',

                    //Datos Usuario Updated
                    'vales.created_at',
                    'vales.updated_at',
                    //Datos Usuario created
                    'users.id as idE',
                    'users.email as emailE',
                    'users.Nombre as NombreE',
                    'users.Paterno as PaternoE',
                    'users.Materno as MaternoE',
                    'users.idTipoUser',
                    'cat_usertipo.id as idEA',
                    'cat_usertipo.TipoUser as TipoUserEA',
                    'cat_usertipo.Clave as ClaveEA',
                    'vales.UserUpdated',
                    //Datos Usuario updated
                    'usersB.id as idF',
                    'usersB.email as emailF',
                    'usersB.Nombre as NombreF',
                    'usersB.Paterno as PaternoF',
                    'usersB.Materno as MaternoF',
                    'usersB.idTipoUser',
                    'cat_usertipoB.id as idFA',
                    'cat_usertipoB.TipoUser as TipoUserFA',
                    'cat_usertipoB.Clave as ClaveFA',
                    'vales.UserOwned',
                    //Datos Usuario owned
                    'usersC.id as idO',
                    'usersC.email as emailO',
                    'usersC.Nombre as NombreO',
                    'usersC.Paterno as PaternoO',
                    'usersC.Materno as MaternoO',
                    'usersC.idTipoUser',
                    'cat_usertipoC.id as idGO',
                    'cat_usertipoC.TipoUser as TipoUserGO',
                    'cat_usertipoC.Clave as ClaveGO'
                )
                ->leftJoin(
                    'et_cat_municipio',
                    'et_cat_municipio.Id',
                    '=',
                    'vales.idMunicipio'
                )
                ->leftJoin(
                    'et_cat_localidad',
                    'et_cat_localidad.Id',
                    '=',
                    'vales.idLocalidad'
                )
                ->leftJoin('vales_status', 'vales_status.id', '=', 'idStatus')
                ->leftJoin('users', 'users.id', '=', 'vales.UserCreated')
                ->leftJoin(
                    'cat_usertipo',
                    'cat_usertipo.id',
                    '=',
                    'users.idTipoUser'
                )
                ->leftJoin(
                    'users as usersB',
                    'usersB.id',
                    '=',
                    'vales.UserUpdated'
                )
                ->leftJoin(
                    'cat_usertipo as cat_usertipoB',
                    'cat_usertipoB.id',
                    '=',
                    'usersB.idTipoUser'
                )
                ->leftJoin(
                    'users as usersC',
                    'usersC.id',
                    '=',
                    'vales.UserOwned'
                )
                ->leftJoin(
                    'users as usersCretaed',
                    'usersCretaed.id',
                    '=',
                    'vales.UserCreated'
                )
                ->leftJoin(
                    'vales_history as valesHist',
                    'valesHist.idVale',
                    '=',
                    'vales.id'
                )
                ->leftJoin(
                    'users as userEdito',
                    'userEdito.id',
                    '=',
                    'valesHist.UserEdito'
                )
                ->leftJoin(
                    'cat_usertipo as cat_usertipoC',
                    'cat_usertipoC.id',
                    '=',
                    'usersC.idTipoUser'
                )
                ->whereIn('vales.id', $arrayIDHistory)
                ->groupBy('vales.id')
                ->orderBy('valesHist.UserEditoFecha', 'asc');

            if (isset($parameters['Folio'])) {
                $valor_id = $parameters['Folio'];
                $res->where(function ($q) use ($valor_id) {
                    $q->where(
                        DB::raw('LPAD(HEX(vales_history.idVale),6,0)'),
                        'like',
                        '%' . $valor_id . '%'
                    );
                });
            }

            $page = $parameters['page'];
            $pageSize = $parameters['pageSize'];

            $startIndex = $page * $pageSize;
            if (isset($parameters['sorted'])) {
                for ($i = 0; $i < count($parameters['sorted']); $i++) {
                    if ($parameters['sorted'][$i]['desc'] === true) {
                        $res->orderBy($parameters['sorted'][$i]['id'], 'desc');
                    } else {
                        $res->orderBy($parameters['sorted'][$i]['id'], 'asc');
                    }
                }
            }

            $total = $res->count();
            $res = $res
                ->offset($startIndex)
                ->take($pageSize)
                ->get();

            $parameters_serializado = serialize($parameters);
            //$array = unserialize($parameters_serializado);

            $array_res = [];
            $temp = [];
            foreach ($res as $data) {
                $temp = [
                    'id' => $data->id,
                    'ClaveUnica' => $data->ClaveUnica,
                    'TelRecados' => $data->TelRecados,
                    'Compania' => $data->Compania,
                    'TelFijo' => $data->TelFijo,
                    'FolioSolicitud' => $data->FolioSolicitud,
                    'FechaSolicitud' => $data->FechaSolicitud,
                    'CURP' => $data->CURP,
                    'Nombre' => $data->Nombre,
                    'Paterno' => $data->Paterno,
                    'Materno' => $data->Materno,
                    'Sexo' => $data->Sexo,
                    'FechaNacimiento' => $data->FechaNacimiento,
                    'Calle' => $data->Calle,
                    'NumExt' => $data->NumExt,
                    'NumInt' => $data->NumInt,
                    'Colonia' => $data->Colonia,
                    'CP' => $data->CP,
                    'FechaDocumentacion' => $data->FechaDocumentacion,
                    'isDocumentacionEntrega' => $data->isDocumentacionEntrega,
                    'idUserDocumentacion' => $data->idUserDocumentacion,
                    'isEntregadoOwner' => $data->isEntregadoOwner,
                    'idUserReportaEntrega' => $data->idUserReportaEntrega,
                    'ComentarioEntrega' => $data->ComentarioEntrega,
                    'FechaReportaEntrega' => $data->FechaReportaEntrega,
                    'IngresoPercibido' => $data->IngresoPercibido,
                    'OtrosIngresos' => $data->OtrosIngresos,
                    'NumeroPersonas' => $data->NumeroPersonas,
                    'OcupacionOtro' => $data->OcupacionOtro,
                    'Ocupacion' => $data->Ocupacion,

                    'idMunicipio' => [
                        'id' => $data->IdM,
                        'Municipio' => $data->Municipio,
                        'Region' => $data->Region,
                    ],
                    'idLocalidad' => [
                        'id' => $data->Clave,
                        'Nombre' => $data->Localidad,
                    ],
                    'TelFijo' => $data->TelFijo,
                    'TelCelular' => $data->TelCelular,
                    'CorreoElectronico' => $data->CorreoElectronico,
                    'idStatus' => [
                        'id' => $data->idES,
                        'Clave' => $data->ClaveA,
                        'Estatus' => $data->Estatus,
                    ],
                    'UserEditoFecha' => $data->UserEditoFecha,
                    'UserEdito' => [
                        'id' => $data->idEdito,
                        'Nombre' => $data->NombreEdito,
                        'Paterno' => $data->PaternoEdito,
                        'Materno' => $data->MaternoEdito,
                    ],
                    'created_at' => $data->created_at,
                    'updated_at' => $data->updated_at,
                    'UserCreated' => [
                        'id' => $data->idE,
                        'email' => $data->emailE,
                        'Nombre' => $data->NombreE,
                        'Paterno' => $data->PaternoE,
                        'Materno' => $data->MaternoE,
                        'idTipoUser' => [
                            'id' => $data->idEA,
                            'TipoUser' => $data->TipoUserEA,
                            'Clave' => $data->ClaveEA,
                        ],
                    ],
                    'UserUpdated' => [
                        'id' => $data->idF,
                        'email' => $data->emailF,
                        'Nombre' => $data->NombreF,
                        'Paterno' => $data->PaternoF,
                        'Materno' => $data->MaternoF,
                        'idTipoUser' => [
                            'id' => $data->idFA,
                            'TipoUser' => $data->TipoUserFA,
                            'Clave' => $data->ClaveFA,
                        ],
                    ],
                    'UserOwned' => [
                        'id' => $data->idO,
                        'email' => $data->emailO,
                        'Nombre' => $data->NombreO,
                        'Paterno' => $data->PaternoO,
                        'Materno' => $data->MaternoO,
                        'idTipoUser' => [
                            'id' => $data->idGO,
                            'TipoUser' => $data->TipoUserGO,
                            'Clave' => $data->ClaveGO,
                        ],
                    ],
                ];

                array_push($array_res, $temp);
            }

            return [
                'success' => true,
                'results' => true,
                'total' => $total,
                'filtros' => $parameters['filtered'],
                'data' => $array_res,
            ];
        } catch (QueryException $e) {
            $errors = [
                'Clave' => '01',
            ];
            $response = [
                'success' => true,
                'results' => false,
                'total' => 0,
                'filtros' => $parameters['filtered'],
                'errors' => $e->getMessage(),
                'message' => 'Campo de consulta incorrecto',
            ];

            return response()->json($response, 200);
        }
    }

    function sethistoryVales(Request $request)
    {
        $v = Validator::make($request->all(), [
            'id' => 'required',
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
        $parameters = $request->all();

        $user = auth()->user();
        $parameters['UserUpdated'] = $user->id;

        $vale = VVales::find($parameters['id']);
        $vale_insert = DB::table('vales')
            ->select(
                'vales.*',
                'vales.id as idVale',
                DB::raw(
                    $user->id .
                        " as UserEdito, '" .
                        date('Y:m:d h:m:s') .
                        "' as UserEditoFecha"
                )
            )
            ->where('id', $parameters['id'])
            ->first();

        if (!$vale) {
            return [
                'success' => true,
                'results' => false,
                'errors' => 'El negocio que desea actualizar no existe.',
                'data' => [],
            ];
        }

        //insert
        $vale_insert = (array) $vale_insert;
        $vale_insert = Arr::except($vale_insert, 'id');
        $res = DB::table('vales_history')->insert($vale_insert);

        $vale->update($parameters);
        $vale_ = DB::table('vales')
            ->select(
                'vales.*',
                DB::raw('LPAD(HEX(vales.id),6,0) as ClaveUnica')
            )
            ->where('vales.id', '=', $vale->id)
            ->first();

        return ['success' => true, 'results' => true, 'data' => $vale_];
    }

    function getValesFecha(Request $request)
    {
        $parameters = $request->all();
        try {
            $res = DB::table('vales')
                ->select(
                    'vales.created_at as FechaHoraAlta',
                    DB::raw('Date(vales.created_at) as FechaAlta')
                )
                //->leftjoin('users_region as ROwned','ROwned.idUser','=','vales.UserOwned') //left join et_cat_municipio m on m.Id=v.idMunicipio
                //->leftjoin('users_region as RCreated','RCreated.idUser','=','vales.UserCreated')
                ->leftjoin(
                    'et_cat_municipio as m',
                    'm.Id',
                    '=',
                    'vales.idMunicipio'
                )
                ->groupBy(DB::raw('Date(vales.created_at)'))
                ->orderBy('vales.created_at', 'desc');

            if (isset($parameters['Propietario'])) {
                $valor_id = $parameters['Propietario'];
                $res->where(function ($q) use ($valor_id) {
                    $q
                        ->where('vales.UserCreated', $valor_id)
                        ->orWhere('vales.UserOwned', $valor_id);
                });
            }
            //where m.SubRegion=1
            if (isset($parameters['Region'])) {
                $valor_id = $parameters['Region'];
                $res->orWhere(function ($q) use ($valor_id) {
                    $q->orWhereIn('m.SubRegion', $valor_id, 'or');
                });
            }

            $total = $res->count();

            //$res=$res->toSql();
            //dd($res);

            $res = $res->get();

            $array_res = [];
            $temp = [];
            $temp_axu = [
                'FechaAlta' => 'Seleccionar...',
                'FechaHoraAlta' => 'Seleccionar...',
            ];
            foreach ($res as $data) {
                $temp = [
                    'FechaAlta' => $data->FechaAlta,
                    'FechaHoraAlta' => $data->FechaHoraAlta,
                ];

                array_push($array_res, $temp);
            }
            array_push($array_res, $temp_axu);

            return [
                'success' => true,
                'results' => true,
                'total' => $total,
                'data' => $array_res,
            ];
        } catch (QueryException $e) {
            $errors = [
                'Clave' => '01',
            ];
            $response = [
                'success' => true,
                'results' => false,
                'total' => 0,
                'errors' => $e->getMessage(),
                'message' => 'Campo de consulta incorrecto',
            ];

            return response()->json($response, 200);
        }
    }

    function deleteVales(Request $request)
    {
        //$vale_ = VVales::create($parameters);
        $parameters = $request->all();
        try {
            $UserDeleted = $user = auth()->user()->id;
            if (isset($parameters['Motivo'])) {
                $Motivo = $parameters['Motivo'];
            } else {
                $Motivo = 'Sin Motivo';
            }

            $vale = DB::table('vales')
                ->where('id', $parameters['id'])
                ->first();
            if (!$vale) {
                return [
                    'success' => true,
                    'results' => false,
                    'data' => [],
                    'message' =>
                        '¡EL vale no se encontró, contacte al administrador!',
                ];
            }
            if ($vale->Remesa) {
                return [
                    'success' => true,
                    'results' => false,
                    'data' => [],
                    'message' =>
                        '¡EL vale no se puede eliminar con una remesa asignada!',
                ];
            }
            //verificar si tiene remesa
            $vale_encode_pre = json_encode($vale);
            $vale_encode = json_decode($vale_encode_pre, true);
            $vale_decode = [];
            $vale_decode['UserDeleted'] = $UserDeleted;
            $vale_decode['MotivoDeleted'] = $Motivo;
            $vale_decode['FechaDeleted'] = time::now();
            $vale_merge = array_merge($vale_encode, $vale_decode);
            DB::table('vales_deleted')->insert($vale_merge);
            DB::table('vales')
                ->where('id', $parameters['id'])
                ->delete();
            $data = DB::table('vales_deleted')
                ->where('id', $parameters['id'])
                ->first();
            return ['success' => true, 'results' => true, 'data' => $data];
        } catch (QueryException $e) {
            $errors = [
                'Clave' => '01',
            ];
            $response = [
                'success' => true,
                'results' => false,
                'total' => 0,
                'errors' => $e->getMessage(),
                'message' => '¡Ocurrio un problema, contacte al administrador!',
            ];

            return response()->json($response, 200);
        }
    }
}
