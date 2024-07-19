<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use DB;
use Illuminate\Contracts\Validation\ValidationException;
use Validator;
use ValesSolicitudes;
use App\User;

class UserController extends Controller
{
    function getUsersArticuladores(Request $request)
    {
        $parameters = $request->all();

        try {
            $res = DB::table('users');

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
                                    'UserCreated'
                                ) === 0 ||
                                strcmp(
                                    $parameters['filtered'][$i]['id'],
                                    'UserUpdated'
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
                                        'UserCreated'
                                    ) === 0 ||
                                    strcmp(
                                        $parameters['filtered'][$i]['id'],
                                        'UserUpdated'
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
                                        'UserCreated'
                                    ) === 0 ||
                                    strcmp(
                                        $parameters['filtered'][$i]['id'],
                                        'UserUpdated'
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
    function getUsersArticuladoresV2(Request $request)
    {
        $parameters = $request->all();

        try {
            $res = DB::table('users')->select(
                DB::raw(
                    'concat_ws(" ",users.Nombre, users.Paterno,users.Materno) as Nombre'
                ),
                'users.id'
            );

            if (isset($parameters['idUser'])) {
                $id_valor = $parameters['idUser'];
                $res->whereIn('users.id', function ($query) use ($id_valor) {
                    $query
                        ->select('UserOwned')
                        ->from('vales')
                        ->where('UserCreated', '=', $id_valor);
                });
                //Si no trae nada aqui es porque no tiene capturados el usuario
                //y entrego el mimo usuario que me hizo la consulta
                if ($res->count() === 0) {
                    $res = DB::table('users')
                        ->select(
                            DB::raw(
                                'concat_ws(" ",users.Nombre, users.Paterno,users.Materno) as Nombre'
                            ),
                            'users.id'
                        )
                        ->where('id', '=', $id_valor);
                }
            }

            if (isset($parameters['Regiones'])) {
                $id_valor = $parameters['Regiones'];
                if (is_array($parameters['Regiones'])) {
                    $res->whereIn('users.id', function ($query) use (
                        $id_valor
                    ) {
                        $query
                            ->select('UserOwned')
                            ->from('vales')
                            ->leftJoin(
                                'et_cat_municipio',
                                'et_cat_municipio.id',
                                '=',
                                'vales.idMunicipio'
                            )
                            ->whereIn('et_cat_municipio.SubRegion', $id_valor);
                    });
                } else {
                    $res->whereIn('users.id', function ($query) use (
                        $id_valor
                    ) {
                        $query
                            ->select('UserOwned')
                            ->from('vales')
                            ->leftJoin(
                                'et_cat_municipio',
                                'et_cat_municipio.id',
                                '=',
                                'vales.idMunicipio'
                            )
                            ->where(
                                'et_cat_municipio.SubRegion',
                                '=',
                                $id_valor
                            );
                    });
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
                                    'UserCreated'
                                ) === 0 ||
                                strcmp(
                                    $parameters['filtered'][$i]['id'],
                                    'UserUpdated'
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
                                        'UserCreated'
                                    ) === 0 ||
                                    strcmp(
                                        $parameters['filtered'][$i]['id'],
                                        'UserUpdated'
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
                                        'UserCreated'
                                    ) === 0 ||
                                    strcmp(
                                        $parameters['filtered'][$i]['id'],
                                        'UserUpdated'
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

    function getUsersRecepcionoV2(Request $request)
    {
        $parameters = $request->all();

        try {
            $resRecepcionoUsers = DB::table('vales')
                ->where('isDocumentacionEntrega', '=', 1)
                ->whereNull('Remesa')
                ->whereNotNull('idUserDocumentacion')
                ->pluck('idUserDocumentacion');

            $res = DB::table('users')->select(
                DB::raw(
                    'concat_ws(" ",users.Nombre, users.Paterno,users.Materno) as Nombre'
                ),
                'users.id'
            );

            if (count($resRecepcionoUsers)) {
                $res->whereIn('id', $resRecepcionoUsers);
            }

            if (isset($parameters['idUser'])) {
                $id_valor = $parameters['idUser'];
                $res->whereIn('users.id', function ($query) use ($id_valor) {
                    $query
                        ->select('UserOwned')
                        ->from('vales')
                        ->where('UserCreated', '=', $id_valor);
                });
                //Si no trae nada aqui es porque no tiene capturados el usuario
                //y entrego el mimo usuario que me hizo la consulta
                if ($res->count() === 0) {
                    $res = DB::table('users')
                        ->select(
                            DB::raw(
                                'concat_ws(" ",users.Nombre, users.Paterno,users.Materno) as Nombre'
                            ),
                            'users.id'
                        )
                        ->where('id', '=', $id_valor);
                }
            }

            if (isset($parameters['Regiones'])) {
                $id_valor = $parameters['Regiones'];
                if (is_array($parameters['Regiones'])) {
                    $res->whereIn('users.id', function ($query) use (
                        $id_valor
                    ) {
                        $query
                            ->select('UserOwned')
                            ->from('vales')
                            ->leftJoin(
                                'et_cat_municipio',
                                'et_cat_municipio.id',
                                '=',
                                'vales.idMunicipio'
                            )
                            ->whereIn('et_cat_municipio.SubRegion', $id_valor);
                    });
                } else {
                    $res->whereIn('users.id', function ($query) use (
                        $id_valor
                    ) {
                        $query
                            ->select('UserOwned')
                            ->from('vales')
                            ->leftJoin(
                                'et_cat_municipio',
                                'et_cat_municipio.id',
                                '=',
                                'vales.idMunicipio'
                            )
                            ->where(
                                'et_cat_municipio.SubRegion',
                                '=',
                                $id_valor
                            );
                    });
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
                                    'UserCreated'
                                ) === 0 ||
                                strcmp(
                                    $parameters['filtered'][$i]['id'],
                                    'UserUpdated'
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
                                        'UserCreated'
                                    ) === 0 ||
                                    strcmp(
                                        $parameters['filtered'][$i]['id'],
                                        'UserUpdated'
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
                                        'UserCreated'
                                    ) === 0 ||
                                    strcmp(
                                        $parameters['filtered'][$i]['id'],
                                        'UserUpdated'
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

    function getGruposArticuladores(Request $request)
    {
        $parameters = $request->all();

        try {
            /* $nombre = "N/A";
            if($parameters["NombreCompleto"]){
                $nombre = $parameters["NombreCompleto"];
            }
            $res=DB::select('call getGruposArticuladores(?)', array($nombre)); */

            return ['success' => true, 'results' => true, 'data' => []];
        } catch (QueryException $e) {
            dd($e->getMessage());
            $errors = [
                'Clave' => '01',
            ];
            $response = [
                'success' => true,
                'results' => false,
                'total' => 0,
                'errors' => $errors,
                'message' => 'Campo de consulta incorrecto',
            ];

            return response()->json($response, 200);
        }
    }

    function getArticularSolicitudes(Request $request)
    {
        $parameters = $request->all();
        try {
            $res = DB::table('vales_aprobados_2022 as V')
                ->select(
                    'V.UserOwned',
                    'M.SubRegion AS Region',
                    'V.idMunicipio',
                    'M.Nombre AS Municipio',
                    'V.Remesa',
                    DB::raw(
                        'concat_ws(" ",A.Nombre, A.Paterno,A.Materno) as FullName'
                    ),
                    DB::raw('count(V.id) Solicitudes')
                )
                ->leftJoin(
                    'et_cat_municipio as M',
                    'V.idMunicipio',
                    '=',
                    'M.Id'
                )
                ->leftJoin('users as A', 'V.UserOwned', '=', 'A.id')
                ->where('V.idStatus', '=', 5)
                //->whereNotNull('V.Remesa')
                ->where('V.idIncidencia', '=', 1)
                //->WhereRaw('YEAR(V.created_at) = 2022')
                ->whereNotIn('V.id', function ($query) {
                    $query
                        ->select('idSolicitud')
                        ->from('vales_solicitudes')
                        ->whereRaw('Ejercicio = 2022');
                })
                /* ->whereNotIn(DB::raw('concat(V.UserOwned,V.idMunicipio,V.Remesa)'),function($query){
                $query->select(DB::raw('concat(UserOwned,idMunicipio,Remesa)'))->from('vales_grupos');
             }) */
                //articulador no se encuentre en grupos. remesa/articulador/municipio
                ->groupBy('V.UserOwned')
                ->groupBy('M.SubRegion')
                ->groupBy('V.idMunicipio')
                ->groupBy('V.Remesa');
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
                                    'UserCreated'
                                ) === 0 ||
                                strcmp(
                                    $parameters['filtered'][$i]['id'],
                                    'UserUpdated'
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
                                        'UserCreated'
                                    ) === 0 ||
                                    strcmp(
                                        $parameters['filtered'][$i]['id'],
                                        'UserUpdated'
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
                                        'UserCreated'
                                    ) === 0 ||
                                    strcmp(
                                        $parameters['filtered'][$i]['id'],
                                        'UserUpdated'
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
                $filtro_recibido = str_replace(' ', '%', $filtro_recibido);
                $res->where(
                    DB::raw("
                    REPLACE(
                    CONCAT(
                        V.Remesa,
                        A.Nombre,
                        A.Paterno,
                        A.Materno,
                        A.Paterno,
                        A.Nombre,
                        A.Materno,
                        A.Materno,
                        A.Nombre,
                        A.Paterno,
                        A.Nombre,
                        A.Materno,
                        A.Paterno,
                        A.Paterno,
                        A.Materno,
                        A.Nombre,
                        A.Materno,
                        A.Paterno,
                        A.Nombre,
                        V.Remesa
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
                'errors' => $e,
                'message' => 'Campo de consulta incorrecto',
            ];

            return response()->json($response, 200);
        }
    }

    function getArticularSolicitudes2023(Request $request)
    {
        $parameters = $request->all();
        $user = auth()->user();
        try {
            $res = DB::table('vales as V')
                ->select(
                    'M.SubRegion AS Region',
                    'V.idMunicipio',
                    'M.Nombre AS Municipio',
                    'V.CveInterventor',
                    'V.idLocalidad',
                    'L.Nombre AS Localidad',
                    'V.ResponsableEntrega',
                    'V.Remesa',
                    'V.idGrupo',
                    DB::raw('count(V.id) Solicitudes')
                )
                ->JOIN('et_cat_municipio as M', 'V.idMunicipio', '=', 'M.Id')
                ->JOIN('et_cat_localidad_2022 as L', 'L.id', 'V.idLocalidad')
                ->where('V.idStatus', '=', 5)
                ->where('V.Devuelto', '=', 0)
                ->where('V.idIncidencia', '=', 1)
                ->whereIn('V.Ejercicio', [2023, 2024])
                ->whereNotIn('V.id', function ($query) {
                    $query
                        ->select('idSolicitud')
                        ->from('vales_solicitudes')
                        ->whereRaw('Ejercicio IN (2023,2024)');
                })
                ->groupBy('V.idMunicipio')
                ->groupBy('V.CveInterventor')
                ->groupBy('V.idLocalidad')
                ->groupBy('V.ResponsableEntrega')
                ->groupBy('V.Remesa')
                ->groupBy('V.idGrupo')
                ->OrderBy('M.SubRegion')
                ->OrderBy('V.idMunicipio')
                ->OrderBy('V.CveInterventor')
                ->OrderBy('V.idLocalidad')
                ->OrderBy('V.ResponsableEntrega');

            //Filtro para mostrar solo registros que pertenecen a la misma remesa que el usuario
            //! Si se necesita que vean todo el estado quitar este fragmento
            //!-----------------------------------------------------------------------------------------//
            $permisos = DB::table('users_menus')
                ->where(['idUser' => $user->id, 'idMenu' => '28'])
                ->get()
                ->first();

            if ($permisos !== null) {
                $viewall = $permisos->ViewAll;
                if ($viewall < 1) {
                    $region = DB::table('users_region')
                        ->selectRaw('Region')
                        ->where(['idUser' => $user->id])
                        ->first();
                    if ($region === null) {
                        $response = [
                            'success' => true,
                            'results' => false,
                            'errors' => 'No tiene region asignada',
                            'message' => 'No tiene region asignada',
                        ];
                        return response()->json($response, 200);
                    }
                    $res->where('M.SubRegion', $region->Region);
                }
            }
            //!-----------------------------------------------------------------------------------------//

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
                                    'UserCreated'
                                ) === 0 ||
                                strcmp(
                                    $parameters['filtered'][$i]['id'],
                                    'UserUpdated'
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
                                        'UserCreated'
                                    ) === 0 ||
                                    strcmp(
                                        $parameters['filtered'][$i]['id'],
                                        'UserUpdated'
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
                                        'UserCreated'
                                    ) === 0 ||
                                    strcmp(
                                        $parameters['filtered'][$i]['id'],
                                        'UserUpdated'
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
            $pageSize = 5;

            $startIndex = $page * $pageSize;

            if (isset($parameters['NombreCompleto'])) {
                $filtro_recibido = $parameters['NombreCompleto'];
                $filtro_recibido = str_replace(' ', '%', $filtro_recibido);
                $res->where(
                    DB::raw("
                    REPLACE(
                    CONCAT(
                        V.Remesa,
                        V.ResponsableEntrega,
                        V.Remesa
                    ), ' ', '')"),

                    'like',
                    '%' . $filtro_recibido . '%'
                );
            }

            if (isset($parameters['CveInterventor'])) {
                $filtro_recibido = $parameters['CveInterventor'];
                $filtro_recibido = str_replace(' ', '%', $filtro_recibido);
                $res->where('V.CveInterventor', $filtro_recibido);
            }
            //dd(str_replace_array('?', $res->getBindings(), $res->toSql()));
            $total = (clone $res)->get()->count();
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
                'errors' => $e,
                'message' => 'Campo de consulta incorrecto',
            ];

            return response()->json($response, 200);
        }
    }

    public function getAll(Request $request){
        $parameters = $request->all();
        $userInfo = auth()->user();

        $user = new User;
        return $user->getAll($userInfo,$parameters);
        // if($user->getAll($parameters)){
        //     return response()->json($user->getAll($userInfo,$parameters), 200);
        // }else{
        //     return response()->json(['message' => 'Error al obtener los datos'], 400);
        // }
    }

    public function getCatalogs(Request $request){
        $userInfo = auth()->user();
        $user = new User;
        return $user->getCatalogs($userInfo);
    }

    public function getMenus(Request $request){
        $parameters = $request->all();
        $userInfo = auth()->user();

        $user = new User;

        return $user->getMenusByIdUser($userInfo,$parameters['id']);
        // if($user->getById($parameters
    }

    public function getMenusById($id){
        $userInfo = auth()->user();
        $user = new User;
        return $user->getMenusById($userInfo,$id);
    }

    public function create(Request $request){
                
        $v = Validator::make($request->all(), [
            'Nombre' => 'required',
            'Paterno' => 'required',            
            'password'=>'required|min:10|max:15',
            'TelCelular' => 'required|size:10',
            'idTipoUser' => 'required|int',
            'Region'=>'required|int',
        ]);        
        if ($v->fails()) {
            $response = [
                'success' => true,
                'results' => false,
                'errors' => $v->errors()->all(),
            ];
            return response()->json($response, 400);
        }

        $parameters = $request->all();
        $userInfo = auth()->user();
        $user = new User;
        return $user->createUser($userInfo,$parameters);
    }

    public function update(Request $request){
        $v = Validator::make($request->all(), [
            'id' => 'required',
            'email' => 'sometimes|required|size:10',            
            'TelCelular' => 'sometimes|required|size:10',
            'idTipoUser' => 'sometimes|required|int',
        ]);        
        if ($v->fails()) {
            $response = [
                'success' => true,
                'results' => false,
                'errors' => $v->errors()->all(),
            ];
            return response()->json($response, 400);
        }

        $parameters = $request->only('id','email','password','TelCelular','idTipoUser','Region','idStatus','Correo');
        $userInfo = auth()->user();
        $user = new User;

        return $user->updateUser($userInfo,$parameters);
        
    }

    public function setMenu(Request $request){
        $v = Validator::make($request->all(), [
            'idUser' => 'required|int',
            'idMenu' => 'required|int',
            'idTipoUser' => 'required|int',
            'flag' => 'required|int'     
        ]);
        if ($v->fails()) {
            $response = [
                'success' => true,
                'results' => false,
                'errors' => $v->errors()->all(),
            ];
            return response()->json($response, 400);
        }
        $parameters = $request->only('idUser','idMenu','idTipoUser','flag');
        $userInfo = auth()->user();
        $user = new User;
        return $user->setMenu($userInfo,$parameters);
    }

    public function bloqueoMasivo(Request $request){
        $userInfo = auth()->user();
        $user = new User;
        return $user->bloqueoMasivo($userInfo);
    }
}