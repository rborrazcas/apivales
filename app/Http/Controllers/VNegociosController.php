<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use DB;
use Illuminate\Contracts\Validation\ValidationException;
use Validator;
use App\VNegocios;
use App\VNegociosGiros;
use App\VNegociosPagadores;
use App\VNegociosFiltros;

class VNegociosController extends Controller
{
    function getNegocios(Request $request)
    {
        $parameters = $request->all();
        $parameters['filtered'] = isset($parameters['filtered'])
            ? $parameters['filtered']
            : [];
        // $parameters['idStatus'] = isset($parameters['idStatus']) ? $parameters['idStatus'] : 3;

        try {
            $res = DB::table('v_negocios as N')
                ->select(
                    'N.id',
                    'N.Codigo',
                    DB::raw('LPAD(HEX(N.id),6,0) as ClaveUnica'),
                    'N.RFC',
                    'N.NombreEmpresa',
                    'N.Nombre',
                    'N.Paterno',
                    'N.Materno',
                    'N.TelNegocio',
                    'N.TelCasa',
                    'N.Celular',
                    'N.idMunicipio',
                    'M.Nombre AS Municipio',
                    'M.SubRegion AS Region',
                    'N.Calle',
                    'N.NumExt',
                    'N.NumInt',
                    'N.Colonia',
                    'N.CP',
                    'N.idTipoNegocio',
                    'NT.Tipo AS TipoGiro',
                    'N.Correo',
                    'N.QuiereTransferencia',
                    'N.Banco',
                    'N.CLABE',
                    'N.NumTarjeta',
                    'N.Latitude',
                    'N.Longitude',
                    'N.FechaInscripcion',
                    'N.HorarioAtencion',
                    'N.Refrendo2021',
                    'N.FechaRefrendo2021',
                    'N.idStatus',
                    'E.Estatus',
                    'N.created_at',
                    'N.updated_at',
                    DB::raw(
                        'CONCAT(T.Nombre," ", T.Paterno," ", T.Materno) as UserCapturo'
                    ),
                    DB::raw(
                        'CONCAT(U.Nombre," ", U.Paterno," ", U.Materno) as UserActualizo'
                    ),
                    DB::raw(
                        'CONCAT(UR.Nombre," ", UR.Paterno," ", UR.Materno) as UserRefrendo'
                    )
                )
                ->leftJoin(
                    'et_cat_municipio as M',
                    'M.Id',
                    '=',
                    'N.idMunicipio'
                )
                ->leftJoin(
                    'v_negocios_tipo as NT',
                    'NT.id',
                    '=',
                    'N.idTipoNegocio'
                )
                ->leftJoin('v_negocios_status as E', 'E.id', '=', 'N.idStatus')
                ->leftJoin('users as T', 'T.id', '=', 'N.UserCreated')
                ->leftJoin('users as U', 'U.id', '=', 'N.UserUpdated')
                ->leftJoin('users as UR', 'UR.id', '=', 'N.UserRefrendo');

            if (isset($parameters['idStatus'])) {
                $res->where('N.idStatus', '=', $parameters['idStatus']);
            }

            $flag = 0;
            if (isset($parameters['filtered'])) {
                for ($i = 0; $i < count($parameters['filtered']); $i++) {
                    if ($flag == 0) {
                        switch ($parameters['filtered'][$i]['id']) {
                            case 'id':
                                if (
                                    is_array(
                                        $parameters['filtered'][$i]['value']
                                    )
                                ) {
                                    $res->whereIn(
                                        'N.id',
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    $res->where(
                                        'N.id',
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                }
                                break;
                            case 'ClaveUnica':
                                $res->where(
                                    DB::raw('LPAD(HEX(N.id),6,0)'),
                                    '=',
                                    $parameters['filtered'][$i]['value']
                                );
                                break;
                            case 'RFC':
                            case 'NombreEmpresa':
                            case 'TelNegocio':
                            case 'TelCasa':
                            case 'Celular':
                            case 'Calle':
                            case 'NumExt':
                            case 'NumInt':
                            case 'Colonia':
                            case 'CP':
                            case 'Correo':
                            case 'FechaInscripcion':
                            case 'HorarioAtencion':
                                $res->where(
                                    'N.' . $parameters['filtered'][$i]['id'],
                                    'LIKE',
                                    '%' .
                                        $parameters['filtered'][$i]['value'] .
                                        '%'
                                );
                                break;
                            case 'Refrendo2021':
                                $res->where(
                                    'N.' . $parameters['filtered'][$i]['id'],
                                    '=',
                                    $parameters['filtered'][$i]['value']
                                );
                                break;
                            case 'Contacto':
                                $contacto_buscar =
                                    $parameters['filtered'][$i]['value'];
                                $contacto_buscar = str_replace(
                                    ' ',
                                    '',
                                    $contacto_buscar
                                );

                                $res->where(
                                    DB::raw("
                                        REPLACE(
                                        CONCAT(
                                            N.Nombre,
                                            N.Paterno,
                                            N.Materno,
                                            N.Paterno,
                                            N.Nombre,
                                            N.Materno,
                                            N.Materno,
                                            N.Nombre,
                                            N.Paterno,
                                            N.Nombre,
                                            N.Materno,
                                            N.Paterno,
                                            N.Paterno,
                                            N.Materno,
                                            N.Nombre,
                                            N.Materno,
                                            N.Paterno,
                                            N.Nombre
                                        ), ' ', '')"),

                                    'like',
                                    '%' . $contacto_buscar . '%'
                                );
                                break;
                            case 'idMunicipio':
                                if (
                                    is_array(
                                        $parameters['filtered'][$i]['value']
                                    )
                                ) {
                                    $res->whereIn(
                                        'N.idMunicipio',
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    $res->where(
                                        'N.idMunicipio',
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                }
                                break;
                            case 'Municipio':
                                $res->where(
                                    'M.Nombre',
                                    'LIKE',
                                    '%' .
                                        $parameters['filtered'][$i]['value'] .
                                        '%'
                                );
                                break;
                            case 'Region':
                                $res->where(
                                    'M.SubRegion',
                                    'LIKE',
                                    '%' .
                                        $parameters['filtered'][$i]['value'] .
                                        '%'
                                );
                                break;
                            case 'idTipoNegocio':
                                if (
                                    is_array(
                                        $parameters['filtered'][$i]['value']
                                    )
                                ) {
                                    $res->whereIn(
                                        'N.idTipoNegocio',
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    $res->where(
                                        'N.idTipoNegocio',
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                }
                                break;
                            case 'TipoNegocio':
                                $res->where(
                                    'NT.Tipo',
                                    'LIKE',
                                    '%' .
                                        $parameters['filtered'][$i]['value'] .
                                        '%'
                                );
                                break;

                            case 'Estatus':
                                $res->where(
                                    'Estatus',
                                    'LIKE',
                                    '%' .
                                        $parameters['filtered'][$i]['value'] .
                                        '%'
                                );
                                break;

                            default:
                                $res->where(
                                    'XXX',
                                    'LIKE',
                                    '%' .
                                        $parameters['filtered'][$i]['value'] .
                                        '%'
                                );
                                break;
                        }
                        $flag = 1;
                    } else {
                        if ($parameters['tipo'] == 'and') {
                            switch ($parameters['filtered'][$i]['id']) {
                                case 'id':
                                    if (
                                        is_array(
                                            $parameters['filtered'][$i]['value']
                                        )
                                    ) {
                                        $res->whereIn(
                                            'id',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->where(
                                            'id',
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    }
                                    break;
                                case 'RFC':
                                case 'NombreEmpresa':
                                case 'TelNegocio':
                                case 'TelCasa':
                                case 'Celular':
                                case 'Calle':
                                case 'NumExt':
                                case 'NumInt':
                                case 'Colonia':
                                case 'CP':
                                case 'Correo':
                                case 'FechaInscripcion':
                                case 'HorarioAtencion':
                                    $res->where(
                                        'N.' .
                                            $parameters['filtered'][$i]['id'],
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                    break;
                                case 'Refrendo2021':
                                    $res->where(
                                        'N.' .
                                            $parameters['filtered'][$i]['id'],
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                    break;
                                case 'Contacto':
                                    $contacto_buscar =
                                        $parameters['filtered'][$i]['value'];
                                    $contacto_buscar = str_replace(
                                        ' ',
                                        '',
                                        $contacto_buscar
                                    );
                                    $res->where(
                                        DB::raw("
                                            REPLACE(
                                            CONCAT(
                                                N.Nombre,
                                            N.Paterno,
                                            N.Materno,
                                            N.Paterno,
                                            N.Nombre,
                                            N.Materno,
                                            N.Materno,
                                            N.Nombre,
                                            N.Paterno,
                                            N.Nombre,
                                            N.Materno,
                                            N.Paterno,
                                            N.Paterno,
                                            N.Materno,
                                            N.Nombre,
                                            N.Materno,
                                            N.Paterno,
                                            N.Nombre
                                                
                                            ), ' ', '')"),

                                        'like',
                                        '%' . $contacto_buscar . '%'
                                    );
                                    break;
                                case 'idMunicipio':
                                    if (
                                        is_array(
                                            $parameters['filtered'][$i]['value']
                                        )
                                    ) {
                                        $res->whereIn(
                                            'N.idMunicipio',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->where(
                                            'N.idMunicipio',
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    }
                                    break;
                                case 'Municipio':
                                    $res->where(
                                        'M.Nombre',
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                    break;
                                case 'Region':
                                    if (
                                        is_array(
                                            $parameters['filtered'][$i]['value']
                                        )
                                    ) {
                                        $res->whereIn(
                                            'M.SubRegion',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->where(
                                            'M.SubRegion',
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    }

                                    break;
                                case 'idTipoNegocio':
                                    if (
                                        is_array(
                                            $parameters['filtered'][$i]['value']
                                        )
                                    ) {
                                        $res->whereIn(
                                            'N.idTipoNegocio',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->where(
                                            'N.idTipoNegocio',
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    }
                                    break;
                                case 'TipoNegocio':
                                    $res->where(
                                        'NT.Tipo',
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                    break;

                                case 'Estatus':
                                    if (
                                        is_array(
                                            $parameters['filtered'][$i]['value']
                                        )
                                    ) {
                                        $res->whereIn(
                                            'N.idStatus',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->where(
                                            'N.idStatus',
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    }
                                    break;
                                default:
                                    $res->where(
                                        'XXX',
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                    break;
                            }
                        } else {
                            switch ($parameters['filtered'][$i]['id']) {
                                case 'id':
                                    if (
                                        is_array(
                                            $parameters['filtered'][$i]['value']
                                        )
                                    ) {
                                        $res->orWhereIn(
                                            'id',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->orWhere(
                                            'id',
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    }
                                    break;
                                case 'RFC':
                                case 'NombreEmpresa':
                                case 'TelNegocio':
                                case 'TelCasa':
                                case 'Celular':
                                case 'Calle':
                                case 'NumExt':
                                case 'NumInt':
                                case 'Colonia':
                                case 'CP':
                                case 'Correo':
                                case 'FechaInscripcion':
                                case 'HorarioAtencion':
                                    $res->orWhere(
                                        'N.' .
                                            $parameters['filtered'][$i]['id'],
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                    break;
                                case 'Refrendo2021':
                                    $res->orWhere(
                                        'N.' .
                                            $parameters['filtered'][$i]['id'],
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                    break;
                                case 'Contacto':
                                    $contacto_buscar =
                                        $parameters['filtered'][$i]['value'];
                                    $contacto_buscar = str_replace(
                                        ' ',
                                        '',
                                        $contacto_buscar
                                    );
                                    $res->orWhere(
                                        DB::raw("
                                            REPLACE(
                                            CONCAT(
                                                N.Nombre,
                                            N.Paterno,
                                            N.Materno,
                                            N.Paterno,
                                            N.Nombre,
                                            N.Materno,
                                            N.Materno,
                                            N.Nombre,
                                            N.Paterno,
                                            N.Nombre,
                                            N.Materno,
                                            N.Paterno,
                                            N.Paterno,
                                            N.Materno,
                                            N.Nombre,
                                            N.Materno,
                                            N.Paterno,
                                            N.Nombre
                                                
                                            ), ' ', '')"),

                                        'like',
                                        '%' . $contacto_buscar . '%'
                                    );
                                    break;
                                case 'idMunicipio':
                                    if (
                                        is_array(
                                            $parameters['filtered'][$i]['value']
                                        )
                                    ) {
                                        $res->orWhereIn(
                                            'N.idMunicipio',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->orWhere(
                                            'N.idMunicipio',
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    }
                                    break;
                                case 'Municipio':
                                    $res->orWhere(
                                        'M.Nombre',
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                    break;
                                case 'Region':
                                    if (
                                        is_array(
                                            $parameters['filtered'][$i]['value']
                                        )
                                    ) {
                                        $res->orWhereIn(
                                            'M.SubRegion',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->orWhere(
                                            'M.SubRegion',
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    }

                                    break;
                                case 'idTipoNegocio':
                                    if (
                                        is_array(
                                            $parameters['filtered'][$i]['value']
                                        )
                                    ) {
                                        $res->orWhereIn(
                                            'idTipoNegocio',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->orWhere(
                                            'idTipoNegocio',
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    }
                                    break;
                                case 'TipoNegocio':
                                    $res->orWhere(
                                        'NT.Tipo',
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                    break;

                                case 'Estatus':
                                    if (
                                        is_array(
                                            $parameters['filtered'][$i]['value']
                                        )
                                    ) {
                                        $res->orWhereIn(
                                            'N.idStatus',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->orWhere(
                                            'N.idStatus',
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    }
                                    break;
                                default:
                                    $res->where(
                                        'XXX',
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                    break;
                            }
                        }
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
                        N.NombreEmpresa,
                        N.Nombre,
                        N.Paterno,
                        N.Materno,
                        N.NombreEmpresa,
                        N.Nombre,
                        N.Materno,
                        N.Paterno,
                        N.NombreEmpresa,
                        N.Paterno,
                        N.Nombre,
                        N.Materno,
                        N.NombreEmpresa,
                        N.Paterno,
                        N.Materno,
                        N.Nombre,
                        N.NombreEmpresa,
                        N.Materno,
                        N.Nombre,
                        N.Paterno,
                        N.NombreEmpresa,
                        N.Materno,
                        N.Paterno,
                        N.Nombre,
                        N.Nombre,
                        N.NombreEmpresa,
                        N.Paterno,
                        N.Materno,
                        N.Nombre,
                        N.NombreEmpresa,
                        N.Materno,
                        N.Paterno,
                        N.Nombre,
                        N.Paterno,
                        N.NombreEmpresa,
                        N.Materno,
                        N.Nombre,
                        N.Paterno,
                        N.Materno,
                        N.NombreEmpresa,
                        N.Nombre,
                        N.Materno,
                        N.NombreEmpresa,
                        N.Paterno,
                        N.Nombre,
                        N.Materno,
                        N.Paterno,
                        N.NombreEmpresa,
                        N.Paterno,
                        N.NombreEmpresa,
                        N.Nombre,
                        N.Materno,
                        N.Paterno,
                        N.NombreEmpresa,
                        N.Materno,
                        N.Nombre,
                        N.Paterno,
                        N.Nombre,
                        N.NombreEmpresa,
                        N.Materno,
                        N.Paterno,
                        N.Nombre,
                        N.Materno,
                        N.NombreEmpresa,
                        N.Paterno,
                        N.Materno,
                        N.NombreEmpresa,
                        N.Nombre,
                        N.Paterno,
                        N.Materno,
                        N.Nombre,
                        N.NombreEmpresa,
                        N.Materno,
                        N.NombreEmpresa,
                        N.Nombre,
                        N.Paterno,
                        N.Materno,
                        N.NombreEmpresa,
                        N.Paterno,
                        N.Nombre,
                        N.Materno,
                        N.Nombre,
                        N.NombreEmpresa,
                        N.Paterno,
                        N.Materno,
                        N.Nombre,
                        N.Paterno,
                        N.NombreEmpresa,
                        N.Materno,
                        N.Paterno,
                        N.NombreEmpresa,
                        N.Nombre,
                        N.Materno,
                        N.Paterno,
                        N.Nombre,
                        N.NombreEmpresa
                        ), ' ', '')"),
                    'like',
                    '%' . $filtro_recibido . '%'
                );
            }

            if (isset($parameters['sorted'])) {
                for ($i = 0; $i < count($parameters['sorted']); $i++) {
                    if ($parameters['sorted'][$i]['desc'] === true) {
                        $res->orderBy($parameters['sorted'][$i]['id'], 'desc');
                    } else {
                        $res->orderBy($parameters['sorted'][$i]['id'], 'asc');
                    }
                }
            }

            $data = $res->get();
            $total = $data->count();

            $negocios_ids = $data->pluck('id');

            $cobradores = DB::table('v_negocios_pagadores as P')
                ->select(
                    'P.id',
                    'P.idNegocio',
                    'P.CURP',
                    'P.Nombre',
                    'P.Paterno',
                    'P.Materno',
                    'P.idStatus'
                )
                ->whereIn('P.idNegocio', $negocios_ids)
                ->get();

            $negocios_giros = DB::table('v_negocios_giros as P')
                ->select('P.idNegocio', 'P.idGiro', 'M.Giro')
                ->leftJoin('v_giros as M', 'M.id', '=', 'P.idGiro')
                ->whereIn('P.idNegocio', $negocios_ids)
                ->get();

            $data = $data->map(function ($x) use (
                $cobradores,
                $negocios_giros
            ) {
                $idNegocio = $x->id;
                $x->Cobradores = $cobradores
                    ->filter(function ($y) use ($idNegocio) {
                        return $y->idNegocio == $idNegocio;
                    })
                    ->values();
                $x->Giros = $negocios_giros
                    ->filter(function ($y) use ($idNegocio) {
                        return $y->idNegocio == $idNegocio;
                    })
                    ->values();
                return $x;
            });

            // $data_gral = [
            //     'Activos' => $data->filter(function($x) { return $x->idStatus == 3; })->values(),
            //     'Inactivos' => $data->filter(function($x) { return $x->idStatus != 3; })->values(),
            // ];

            return [
                'success' => true,
                'results' => true,
                'total' => $total,
                'filtros' => $parameters['filtered'],
                'data' => $data,
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

    function getNegociosApp(Request $request)
    {
        $parameters = $request->all();

        try {
            //AQUI ES LA FUNCION ORIGINAL
            $res = DB::table('v_negocios as N')
                ->select(
                    'N.id',
                    'N.Codigo',
                    DB::raw('LPAD(HEX(N.id),6,0) as ClaveUnica'),
                    'N.RFC',
                    'N.NombreEmpresa',
                    'N.Nombre',
                    'N.Paterno',
                    'N.Materno',
                    'N.TelNegocio',
                    'N.TelCasa',
                    'N.Celular',
                    'N.idMunicipio',
                    'M.Nombre AS Municipio',
                    'M.SubRegion AS Region',
                    'N.Calle',
                    'N.NumExt',
                    'N.NumInt',
                    'N.Colonia',
                    'N.CP',
                    'N.idTipoNegocio',
                    'NT.Tipo AS TipoGiro',
                    'N.Correo',
                    'N.QuiereTransferencia',
                    'N.Banco',
                    'N.CLABE',
                    'N.NumTarjeta',
                    'N.Latitude',
                    'N.Longitude',
                    'N.FechaInscripcion',
                    'N.HorarioAtencion',
                    'N.Refrendo2021',
                    'N.FechaRefrendo2021',
                    'N.idStatus',
                    'E.Estatus',
                    'N.created_at',
                    'N.updated_at',
                    DB::raw(
                        'CONCAT(T.Nombre," ", T.Paterno," ", T.Materno) as UserCapturo'
                    ),
                    DB::raw(
                        'CONCAT(U.Nombre," ", U.Paterno," ", U.Materno) as UserActualizo'
                    ),
                    DB::raw(
                        'CONCAT(UR.Nombre," ", UR.Paterno," ", UR.Materno) as UserRefrendo'
                    )
                )
                ->leftJoin(
                    'et_cat_municipio as M',
                    'M.Id',
                    '=',
                    'N.idMunicipio'
                )
                ->leftJoin(
                    'v_negocios_tipo as NT',
                    'NT.id',
                    '=',
                    'N.idTipoNegocio'
                )
                ->leftJoin('v_negocios_status as E', 'E.id', '=', 'N.idStatus')
                ->leftJoin('users as T', 'T.id', '=', 'N.UserCreated')
                ->leftJoin('users as U', 'U.id', '=', 'N.UserUpdated')
                ->leftJoin('users as UR', 'UR.id', '=', 'N.UserRefrendo');

            if (isset($parameters['idStatus'])) {
                $res->where('N.idStatus', '=', $parameters['idStatus']);
            }

            if (isset($parameters['Folio'])) {
                $valor_id = $parameters['Folio'];
                $res->where(
                    DB::raw('LPAD(HEX(N.id),6,0)'),
                    'like',
                    '%' . $valor_id . '%'
                );
            }

            $flag = 0;
            if (isset($parameters['filtered'])) {
                for ($i = 0; $i < count($parameters['filtered']); $i++) {
                    if ($flag == 0) {
                        switch ($parameters['filtered'][$i]['id']) {
                            case 'id':
                                if (
                                    is_array(
                                        $parameters['filtered'][$i]['value']
                                    )
                                ) {
                                    $res->whereIn(
                                        'N.id',
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    $res->where(
                                        'N.id',
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                }
                                break;
                            case 'ClaveUnica':
                                $res->where(
                                    DB::raw('LPAD(HEX(N.id),6,0)'),
                                    '=',
                                    $parameters['filtered'][$i]['value']
                                );
                                break;
                            case 'RFC':
                            case 'NombreEmpresa':
                            case 'TelNegocio':
                            case 'TelCasa':
                            case 'Celular':
                            case 'Calle':
                            case 'NumExt':
                            case 'NumInt':
                            case 'Colonia':
                            case 'CP':
                            case 'Correo':
                            case 'FechaInscripcion':
                            case 'HorarioAtencion':
                                $res->where(
                                    'N.' . $parameters['filtered'][$i]['id'],
                                    'LIKE',
                                    '%' .
                                        $parameters['filtered'][$i]['value'] .
                                        '%'
                                );
                                break;
                            case 'Refrendo2021':
                                $res->where(
                                    'N.' . $parameters['filtered'][$i]['id'],
                                    '=',
                                    $parameters['filtered'][$i]['value']
                                );
                                break;
                            case 'Contacto':
                                $contacto_buscar =
                                    $parameters['filtered'][$i]['value'];
                                $contacto_buscar = str_replace(
                                    ' ',
                                    '',
                                    $contacto_buscar
                                );

                                $res->where(
                                    DB::raw("
                                            REPLACE(
                                            CONCAT(
                                                N.Nombre,
                                                N.Paterno,
                                                N.Materno,
                                                N.Paterno,
                                                N.Nombre,
                                                N.Materno,
                                                N.Materno,
                                                N.Nombre,
                                                N.Paterno,
                                                N.Nombre,
                                                N.Materno,
                                                N.Paterno,
                                                N.Paterno,
                                                N.Materno,
                                                N.Nombre,
                                                N.Materno,
                                                N.Paterno,
                                                N.Nombre
                                            ), ' ', '')"),

                                    'like',
                                    '%' . $contacto_buscar . '%'
                                );
                                break;
                            case 'idMunicipio':
                                if (
                                    is_array(
                                        $parameters['filtered'][$i]['value']
                                    )
                                ) {
                                    $res->whereIn(
                                        'N.idMunicipio',
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    $res->where(
                                        'N.idMunicipio',
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                }
                                break;
                            case 'Municipio':
                                $res->where(
                                    'M.Nombre',
                                    'LIKE',
                                    '%' .
                                        $parameters['filtered'][$i]['value'] .
                                        '%'
                                );
                                break;
                            case 'Region':
                                if (
                                    is_array(
                                        $parameters['filtered'][$i]['value']
                                    )
                                ) {
                                    $res->whereIn(
                                        'M.SubRegion',
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    $res->where(
                                        'M.SubRegion',
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                }

                                break;
                            case 'idTipoNegocio':
                                if (
                                    is_array(
                                        $parameters['filtered'][$i]['value']
                                    )
                                ) {
                                    $res->whereIn(
                                        'N.idTipoNegocio',
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    $res->where(
                                        'N.idTipoNegocio',
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                }
                                break;
                            case 'TipoNegocio':
                                $res->where(
                                    'NT.Tipo',
                                    'LIKE',
                                    '%' .
                                        $parameters['filtered'][$i]['value'] .
                                        '%'
                                );
                                break;

                            case 'Estatus':
                                if (
                                    is_array(
                                        $parameters['filtered'][$i]['value']
                                    )
                                ) {
                                    $res->whereIn(
                                        'N.idStatus',
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    $res->where(
                                        'N.idStatus',
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                }
                                break;

                            default:
                                $res->where(
                                    'XXX',
                                    'LIKE',
                                    '%' .
                                        $parameters['filtered'][$i]['value'] .
                                        '%'
                                );
                                break;
                        }
                        $flag = 1;
                    } else {
                        if ($parameters['tipo'] == 'and') {
                            switch ($parameters['filtered'][$i]['id']) {
                                case 'id':
                                    if (
                                        is_array(
                                            $parameters['filtered'][$i]['value']
                                        )
                                    ) {
                                        $res->whereIn(
                                            'id',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->where(
                                            'id',
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    }
                                    break;
                                case 'RFC':
                                case 'NombreEmpresa':
                                case 'TelNegocio':
                                case 'TelCasa':
                                case 'Celular':
                                case 'Calle':
                                case 'NumExt':
                                case 'NumInt':
                                case 'Colonia':
                                case 'CP':
                                case 'Correo':
                                case 'FechaInscripcion':
                                case 'HorarioAtencion':
                                    $res->where(
                                        'N.' .
                                            $parameters['filtered'][$i]['id'],
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                    break;
                                case 'Refrendo2021':
                                    $res->where(
                                        'N.' .
                                            $parameters['filtered'][$i]['id'],
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                    break;
                                case 'Contacto':
                                    $contacto_buscar =
                                        $parameters['filtered'][$i]['value'];
                                    $contacto_buscar = str_replace(
                                        ' ',
                                        '',
                                        $contacto_buscar
                                    );
                                    $res->where(
                                        DB::raw("
                                                REPLACE(
                                                CONCAT(
                                                    N.Nombre,
                                                N.Paterno,
                                                N.Materno,
                                                N.Paterno,
                                                N.Nombre,
                                                N.Materno,
                                                N.Materno,
                                                N.Nombre,
                                                N.Paterno,
                                                N.Nombre,
                                                N.Materno,
                                                N.Paterno,
                                                N.Paterno,
                                                N.Materno,
                                                N.Nombre,
                                                N.Materno,
                                                N.Paterno,
                                                N.Nombre
                                                    
                                                ), ' ', '')"),

                                        'like',
                                        '%' . $contacto_buscar . '%'
                                    );
                                    break;
                                case 'idMunicipio':
                                    if (
                                        is_array(
                                            $parameters['filtered'][$i]['value']
                                        )
                                    ) {
                                        $res->whereIn(
                                            'N.idMunicipio',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->where(
                                            'N.idMunicipio',
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    }
                                    break;
                                case 'Municipio':
                                    $res->where(
                                        'M.Nombre',
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                    break;
                                case 'Region':
                                    if (
                                        is_array(
                                            $parameters['filtered'][$i]['value']
                                        )
                                    ) {
                                        $res->whereIn(
                                            'M.SubRegion',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->where(
                                            'M.SubRegion',
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    }
                                    break;
                                case 'idTipoNegocio':
                                    if (
                                        is_array(
                                            $parameters['filtered'][$i]['value']
                                        )
                                    ) {
                                        $res->whereIn(
                                            'N.idTipoNegocio',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->where(
                                            'N.idTipoNegocio',
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    }
                                    break;
                                case 'TipoNegocio':
                                    $res->where(
                                        'NT.Tipo',
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                    break;

                                case 'Estatus':
                                    if (
                                        is_array(
                                            $parameters['filtered'][$i]['value']
                                        )
                                    ) {
                                        $res->whereIn(
                                            'N.idStatus',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->where(
                                            'N.idStatus',
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    }
                                    break;
                                default:
                                    $res->where(
                                        'XXX',
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                    break;
                            }
                        } else {
                            switch ($parameters['filtered'][$i]['id']) {
                                case 'id':
                                    if (
                                        is_array(
                                            $parameters['filtered'][$i]['value']
                                        )
                                    ) {
                                        $res->orWhereIn(
                                            'id',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->orWhere(
                                            'id',
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    }
                                    break;
                                case 'RFC':
                                case 'NombreEmpresa':
                                case 'TelNegocio':
                                case 'TelCasa':
                                case 'Celular':
                                case 'Calle':
                                case 'NumExt':
                                case 'NumInt':
                                case 'Colonia':
                                case 'CP':
                                case 'Correo':
                                case 'FechaInscripcion':
                                case 'HorarioAtencion':
                                    $res->orWhere(
                                        'N.' .
                                            $parameters['filtered'][$i]['id'],
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                    break;
                                case 'Refrendo2021':
                                    $res->orWhere(
                                        'N.' .
                                            $parameters['filtered'][$i]['id'],
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                    break;
                                case 'Contacto':
                                    $contacto_buscar =
                                        $parameters['filtered'][$i]['value'];
                                    $contacto_buscar = str_replace(
                                        ' ',
                                        '',
                                        $contacto_buscar
                                    );
                                    $res->orWhere(
                                        DB::raw("
                                                REPLACE(
                                                CONCAT(
                                                    N.Nombre,
                                                N.Paterno,
                                                N.Materno,
                                                N.Paterno,
                                                N.Nombre,
                                                N.Materno,
                                                N.Materno,
                                                N.Nombre,
                                                N.Paterno,
                                                N.Nombre,
                                                N.Materno,
                                                N.Paterno,
                                                N.Paterno,
                                                N.Materno,
                                                N.Nombre,
                                                N.Materno,
                                                N.Paterno,
                                                N.Nombre
                                                    
                                                ), ' ', '')"),

                                        'like',
                                        '%' . $contacto_buscar . '%'
                                    );
                                    break;
                                case 'idMunicipio':
                                    if (
                                        is_array(
                                            $parameters['filtered'][$i]['value']
                                        )
                                    ) {
                                        $res->orWhereIn(
                                            'N.idMunicipio',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->orWhere(
                                            'N.idMunicipio',
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    }
                                    break;
                                case 'Municipio':
                                    $res->orWhere(
                                        'M.Nombre',
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                    break;
                                case 'Region':
                                    if (
                                        is_array(
                                            $parameters['filtered'][$i]['value']
                                        )
                                    ) {
                                        $res->orWhereIn(
                                            'M.SubRegion',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->orWhere(
                                            'M.SubRegion',
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    }
                                    break;
                                case 'idTipoNegocio':
                                    if (
                                        is_array(
                                            $parameters['filtered'][$i]['value']
                                        )
                                    ) {
                                        $res->orWhereIn(
                                            'idTipoNegocio',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->orWhere(
                                            'idTipoNegocio',
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    }
                                    break;
                                case 'TipoNegocio':
                                    $res->orWhere(
                                        'NT.Tipo',
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                    break;

                                case 'Estatus':
                                    if (
                                        is_array(
                                            $parameters['filtered'][$i]['value']
                                        )
                                    ) {
                                        $res->orWhereIn(
                                            'N.idStatus',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->orWhere(
                                            'N.idStatus',
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    }
                                    break;
                                default:
                                    $res->where(
                                        'XXX',
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                    break;
                            }
                        }
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
                        LPAD(HEX(N.id),6,0),
                        M.Nombre,
                        N.NombreEmpresa,
                        N.Colonia,
                        N.Nombre,
                        N.Paterno,
                        N.Materno,
                        N.NombreEmpresa,
                        N.Nombre,
                        N.Materno,
                        N.Paterno,
                        N.NombreEmpresa,
                        N.Paterno,
                        N.Nombre,
                        N.Materno,
                        N.NombreEmpresa,
                        N.Paterno,
                        N.Materno,
                        N.Nombre,
                        N.NombreEmpresa,
                        N.Materno,
                        N.Nombre,
                        N.Paterno,
                        N.NombreEmpresa,
                        N.Materno,
                        N.Paterno,
                        N.Nombre,
                        N.Nombre,
                        N.NombreEmpresa,
                        N.Paterno,
                        N.Materno,
                        N.Nombre,
                        N.NombreEmpresa,
                        N.Materno,
                        N.Paterno,
                        N.Nombre,
                        N.Paterno,
                        N.NombreEmpresa,
                        N.Materno,
                        N.Nombre,
                        N.Paterno,
                        N.Materno,
                        N.NombreEmpresa,
                        N.Nombre,
                        N.Materno,
                        N.NombreEmpresa,
                        N.Paterno,
                        N.Nombre,
                        N.Materno,
                        N.Paterno,
                        N.NombreEmpresa,
                        N.Paterno,
                        N.NombreEmpresa,
                        N.Nombre,
                        N.Materno,
                        N.Paterno,
                        N.NombreEmpresa,
                        N.Materno,
                        N.Nombre,
                        N.Paterno,
                        N.Nombre,
                        N.NombreEmpresa,
                        N.Materno,
                        N.Paterno,
                        N.Nombre,
                        N.Materno,
                        N.NombreEmpresa,
                        N.Paterno,
                        N.Materno,
                        N.NombreEmpresa,
                        N.Nombre,
                        N.Paterno,
                        N.Materno,
                        N.Nombre,
                        N.NombreEmpresa,
                        N.Materno,
                        N.NombreEmpresa,
                        N.Nombre,
                        N.Paterno,
                        N.Materno,
                        N.NombreEmpresa,
                        N.Paterno,
                        N.Nombre,
                        N.Materno,
                        N.Nombre,
                        N.NombreEmpresa,
                        N.Paterno,
                        N.Materno,
                        N.Nombre,
                        N.Paterno,
                        N.NombreEmpresa,
                        N.Materno,
                        N.Paterno,
                        N.NombreEmpresa,
                        N.Nombre,
                        N.Materno,
                        N.Paterno,
                        N.Nombre,
                        N.NombreEmpresa
                        
                    ), ' ', '')"),

                    'like',
                    '%' . $filtro_recibido . '%'
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

            $total = $res->count();
            $res = $res
                ->offset($startIndex)
                ->take($pageSize)
                ->get();
            // dd($res);
            //dd(str_replace_array('?', $res->getBindings(), $res->toSql()));

            foreach ($res as $data) {
                $res_cobradores = DB::table('v_negocios_pagadores as P')
                    ->select(
                        'P.id',
                        'P.idNegocio',
                        'P.CURP',
                        'P.Nombre',
                        'P.Paterno',
                        'P.Materno',
                        'P.idStatus'
                    )
                    ->where('P.idNegocio', '=', $data->id)
                    ->get();

                $data->Cobradores = $res_cobradores;
            }

            foreach ($res as $data) {
                $res_giros = DB::table('v_negocios_giros as P')
                    ->select('P.idNegocio', 'P.idGiro', 'M.Giro')
                    ->leftJoin('v_giros as M', 'M.id', '=', 'P.idGiro')
                    ->where('P.idNegocio', '=', $data->id)
                    ->get();

                $data->Giros = $res_giros;
            }

            $parameters_serializado = serialize($parameters);
            //$array = unserialize($parameters_serializado);
            $user = auth()->user();
            $filtro_usuario = VNegociosFiltros::where('idUser', '=', $user->id)
                ->where('api', '=', 'getNegociosApp')
                ->first();
            if ($filtro_usuario) {
                $filtro_usuario->parameters = $parameters_serializado;
                $filtro_usuario->update();
            } else {
                $objeto_nuevo = new VNegociosFiltros();
                $objeto_nuevo->api = 'getNegociosApp';
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
                'errors' => $errors,
                'message' => 'Campo de consulta incorrecto',
            ];

            return response()->json($response, 200);
        }
    }
    function getNegociosAppMaps(Request $request)
    {
        $parameters = $request->all();

        try {
            //AQUI ES LA FUNCION ORIGINAL
            $res = DB::table('v_negocios as N')
                ->select(
                    'N.id',
                    'N.Codigo',
                    DB::raw('LPAD(HEX(N.id),6,0) as ClaveUnica'),
                    'N.RFC',
                    'N.NombreEmpresa',
                    'N.Nombre',
                    'N.Paterno',
                    'N.Materno',
                    'N.TelNegocio',
                    'N.TelCasa',
                    'N.Celular',
                    'N.idMunicipio',
                    'M.Nombre AS Municipio',
                    'M.SubRegion AS Region',
                    'N.Calle',
                    'N.NumExt',
                    'N.NumInt',
                    'N.Colonia',
                    'N.CP',
                    'N.idTipoNegocio',
                    'NT.Tipo AS TipoGiro',
                    'N.Correo',
                    'N.QuiereTransferencia',
                    'N.Banco',
                    'N.CLABE',
                    'N.NumTarjeta',
                    'N.Latitude',
                    'N.Longitude',
                    'N.FechaInscripcion',
                    'N.HorarioAtencion',
                    'N.idStatus',
                    'E.Estatus',
                    'N.created_at',
                    'N.updated_at',
                    DB::raw(
                        'CONCAT(T.Nombre," ", T.Paterno," ", T.Materno) as UserCapturo'
                    ),
                    DB::raw(
                        'CONCAT(U.Nombre," ", U.Paterno," ", U.Materno) as UserActualizo'
                    )
                )
                ->leftJoin(
                    'et_cat_municipio as M',
                    'M.Id',
                    '=',
                    'N.idMunicipio'
                )
                ->leftJoin(
                    'v_negocios_tipo as NT',
                    'NT.id',
                    '=',
                    'N.idTipoNegocio'
                )
                ->leftJoin('v_negocios_status as E', 'E.id', '=', 'N.idStatus')
                ->leftJoin('users as T', 'T.id', '=', 'N.UserCreated')
                ->leftJoin('users as U', 'U.id', '=', 'N.UserUpdated');

            if (isset($parameters['idStatus'])) {
                $res->where('N.idStatus', '=', $parameters['idStatus']);
            }
            if (isset($parameters['idMunicipio'])) {
                if (is_array($parameters['idMunicipio'])) {
                    $res->whereIn('N.idMunicipio', $parameters['idMunicipio']);
                } else {
                    $res->where(
                        'N.idMunicipio',
                        '=',
                        $parameters['idMunicipio']
                    );
                }
            }
            if (isset($parameters['idRegion'])) {
                if (is_array($parameters['idRegion'])) {
                    $res->whereIn('M.SubRegion', $parameters['idRegion']);
                } else {
                    $res->where('M.SubRegion', '=', $parameters['idRegion']);
                }
            }
            if (isset($parameters['idTipoNegocio'])) {
                if (is_array($parameters['idTipoNegocio'])) {
                    $res->whereIn(
                        'N.idTipoNegocio',
                        $parameters['idTipoNegocio']
                    );
                } else {
                    $res->where(
                        'N.idTipoNegocio',
                        '=',
                        $parameters['idTipoNegocio']
                    );
                }
            }
            if (isset($parameters['idTipoGiro'])) {
                $res->join(
                    'v_negocios_giros',
                    'v_negocios_giros.idNegocio',
                    'N.id'
                );
                $res->join('v_giros', 'v_giros.id', 'v_negocios_giros.idGiro');
                if (is_array($parameters['idTipoGiro'])) {
                    $res->whereIn(
                        'v_negocios_giros.idGiro',
                        $parameters['idTipoGiro']
                    );
                } else {
                    $res->where(
                        'v_negocios_giros.idGiro',
                        '=',
                        $parameters['idTipoGiro']
                    );
                }
            }
            if (isset($parameters['Folio'])) {
                $valor_id = $parameters['Folio'];
                $res->where(
                    DB::raw('LPAD(HEX(N.id),6,0)'),
                    'like',
                    '%' . $valor_id . '%'
                );
            }

            $flag = 0;
            if (isset($parameters['filtered'])) {
                for ($i = 0; $i < count($parameters['filtered']); $i++) {
                    if ($flag == 0) {
                        switch ($parameters['filtered'][$i]['id']) {
                            case 'id':
                                if (
                                    is_array(
                                        $parameters['filtered'][$i]['value']
                                    )
                                ) {
                                    $res->whereIn(
                                        'N.id',
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    $res->where(
                                        'N.id',
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                }
                                break;
                            case 'ClaveUnica':
                                $res->where(
                                    DB::raw('LPAD(HEX(N.id),6,0)'),
                                    '=',
                                    $parameters['filtered'][$i]['value']
                                );
                                break;
                            case 'RFC':
                            case 'NombreEmpresa':
                            case 'TelNegocio':
                            case 'TelCasa':
                            case 'Celular':
                            case 'Calle':
                            case 'NumExt':
                            case 'NumInt':
                            case 'Colonia':
                            case 'CP':
                            case 'Correo':
                            case 'FechaInscripcion':
                            case 'HorarioAtencion':
                                $res->where(
                                    'N.' . $parameters['filtered'][$i]['id'],
                                    'LIKE',
                                    '%' .
                                        $parameters['filtered'][$i]['value'] .
                                        '%'
                                );
                                break;
                            case 'Contacto':
                                $contacto_buscar =
                                    $parameters['filtered'][$i]['value'];
                                $contacto_buscar = str_replace(
                                    ' ',
                                    '',
                                    $contacto_buscar
                                );

                                $res->where(
                                    DB::raw("
                                            REPLACE(
                                            CONCAT(
                                                N.Nombre,
                                                N.Paterno,
                                                N.Materno,
                                                N.Paterno,
                                                N.Nombre,
                                                N.Materno,
                                                N.Materno,
                                                N.Nombre,
                                                N.Paterno,
                                                N.Nombre,
                                                N.Materno,
                                                N.Paterno,
                                                N.Paterno,
                                                N.Materno,
                                                N.Nombre,
                                                N.Materno,
                                                N.Paterno,
                                                N.Nombre
                                            ), ' ', '')"),

                                    'like',
                                    '%' . $contacto_buscar . '%'
                                );
                                break;
                            case 'idMunicipio':
                                if (
                                    is_array(
                                        $parameters['filtered'][$i]['value']
                                    )
                                ) {
                                    $res->whereIn(
                                        'N.idMunicipio',
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    $res->where(
                                        'N.idMunicipio',
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                }
                                break;
                            case 'Municipio':
                                $res->where(
                                    'M.Nombre',
                                    'LIKE',
                                    '%' .
                                        $parameters['filtered'][$i]['value'] .
                                        '%'
                                );
                                break;
                            case 'Region':
                                $res->where(
                                    'M.SubRegion',
                                    'LIKE',
                                    '%' .
                                        $parameters['filtered'][$i]['value'] .
                                        '%'
                                );
                                break;
                            case 'idTipoNegocio':
                                if (
                                    is_array(
                                        $parameters['filtered'][$i]['value']
                                    )
                                ) {
                                    $res->whereIn(
                                        'N.idTipoNegocio',
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    $res->where(
                                        'N.idTipoNegocio',
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                }
                                break;
                            case 'TipoNegocio':
                                $res->where(
                                    'NT.Tipo',
                                    'LIKE',
                                    '%' .
                                        $parameters['filtered'][$i]['value'] .
                                        '%'
                                );
                                break;

                            case 'Estatus':
                                if (
                                    is_array(
                                        $parameters['filtered'][$i]['value']
                                    )
                                ) {
                                    $res->whereIn(
                                        'N.idStatus',
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    $res->where(
                                        'N.idStatus',
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                }
                                break;

                            default:
                                $res->where(
                                    'XXX',
                                    'LIKE',
                                    '%' .
                                        $parameters['filtered'][$i]['value'] .
                                        '%'
                                );
                                break;
                        }
                        $flag = 1;
                    } else {
                        if ($parameters['tipo'] == 'and') {
                            switch ($parameters['filtered'][$i]['id']) {
                                case 'id':
                                    if (
                                        is_array(
                                            $parameters['filtered'][$i]['value']
                                        )
                                    ) {
                                        $res->whereIn(
                                            'id',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->where(
                                            'id',
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    }
                                    break;
                                case 'RFC':
                                case 'NombreEmpresa':
                                case 'TelNegocio':
                                case 'TelCasa':
                                case 'Celular':
                                case 'Calle':
                                case 'NumExt':
                                case 'NumInt':
                                case 'Colonia':
                                case 'CP':
                                case 'Correo':
                                case 'FechaInscripcion':
                                case 'HorarioAtencion':
                                    $res->where(
                                        'N.' .
                                            $parameters['filtered'][$i]['id'],
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                    break;
                                case 'Contacto':
                                    $contacto_buscar =
                                        $parameters['filtered'][$i]['value'];
                                    $contacto_buscar = str_replace(
                                        ' ',
                                        '',
                                        $contacto_buscar
                                    );
                                    $res->where(
                                        DB::raw("
                                                REPLACE(
                                                CONCAT(
                                                    N.Nombre,
                                                N.Paterno,
                                                N.Materno,
                                                N.Paterno,
                                                N.Nombre,
                                                N.Materno,
                                                N.Materno,
                                                N.Nombre,
                                                N.Paterno,
                                                N.Nombre,
                                                N.Materno,
                                                N.Paterno,
                                                N.Paterno,
                                                N.Materno,
                                                N.Nombre,
                                                N.Materno,
                                                N.Paterno,
                                                N.Nombre
                                                    
                                                ), ' ', '')"),

                                        'like',
                                        '%' . $contacto_buscar . '%'
                                    );
                                    break;
                                case 'idMunicipio':
                                    if (
                                        is_array(
                                            $parameters['filtered'][$i]['value']
                                        )
                                    ) {
                                        $res->whereIn(
                                            'N.idMunicipio',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->where(
                                            'N.idMunicipio',
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    }
                                    break;
                                case 'Municipio':
                                    $res->where(
                                        'M.Nombre',
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                    break;
                                case 'Region':
                                    $res->where(
                                        'M.SubRegion',
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                    break;
                                case 'idTipoNegocio':
                                    if (
                                        is_array(
                                            $parameters['filtered'][$i]['value']
                                        )
                                    ) {
                                        $res->whereIn(
                                            'N.idTipoNegocio',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->where(
                                            'N.idTipoNegocio',
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    }
                                    break;
                                case 'TipoNegocio':
                                    $res->where(
                                        'NT.Tipo',
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                    break;

                                case 'Estatus':
                                    if (
                                        is_array(
                                            $parameters['filtered'][$i]['value']
                                        )
                                    ) {
                                        $res->whereIn(
                                            'N.idStatus',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->where(
                                            'N.idStatus',
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    }
                                    break;
                                default:
                                    $res->where(
                                        'XXX',
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                    break;
                            }
                        } else {
                            switch ($parameters['filtered'][$i]['id']) {
                                case 'id':
                                    if (
                                        is_array(
                                            $parameters['filtered'][$i]['value']
                                        )
                                    ) {
                                        $res->orWhereIn(
                                            'id',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->orWhere(
                                            'id',
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    }
                                    break;
                                case 'RFC':
                                case 'NombreEmpresa':
                                case 'TelNegocio':
                                case 'TelCasa':
                                case 'Celular':
                                case 'Calle':
                                case 'NumExt':
                                case 'NumInt':
                                case 'Colonia':
                                case 'CP':
                                case 'Correo':
                                case 'FechaInscripcion':
                                case 'HorarioAtencion':
                                    $res->orWhere(
                                        'N.' .
                                            $parameters['filtered'][$i]['id'],
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                    break;
                                case 'Contacto':
                                    $contacto_buscar =
                                        $parameters['filtered'][$i]['value'];
                                    $contacto_buscar = str_replace(
                                        ' ',
                                        '',
                                        $contacto_buscar
                                    );
                                    $res->orWhere(
                                        DB::raw("
                                                REPLACE(
                                                CONCAT(
                                                    N.Nombre,
                                                N.Paterno,
                                                N.Materno,
                                                N.Paterno,
                                                N.Nombre,
                                                N.Materno,
                                                N.Materno,
                                                N.Nombre,
                                                N.Paterno,
                                                N.Nombre,
                                                N.Materno,
                                                N.Paterno,
                                                N.Paterno,
                                                N.Materno,
                                                N.Nombre,
                                                N.Materno,
                                                N.Paterno,
                                                N.Nombre
                                                    
                                                ), ' ', '')"),

                                        'like',
                                        '%' . $contacto_buscar . '%'
                                    );
                                    break;
                                case 'idMunicipio':
                                    if (
                                        is_array(
                                            $parameters['filtered'][$i]['value']
                                        )
                                    ) {
                                        $res->orWhereIn(
                                            'N.idMunicipio',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->orWhere(
                                            'N.idMunicipio',
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    }
                                    break;
                                case 'Municipio':
                                    $res->orWhere(
                                        'M.Nombre',
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                    break;
                                case 'Region':
                                    $res->orWhere(
                                        'M.SubRegion',
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                    break;
                                case 'idTipoNegocio':
                                    if (
                                        is_array(
                                            $parameters['filtered'][$i]['value']
                                        )
                                    ) {
                                        $res->orWhereIn(
                                            'idTipoNegocio',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->orWhere(
                                            'idTipoNegocio',
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    }
                                    break;
                                case 'TipoNegocio':
                                    $res->orWhere(
                                        'NT.Tipo',
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                    break;

                                case 'Estatus':
                                    if (
                                        is_array(
                                            $parameters['filtered'][$i]['value']
                                        )
                                    ) {
                                        $res->orWhereIn(
                                            'N.idStatus',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->orWhere(
                                            'N.idStatus',
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    }
                                    break;
                                default:
                                    $res->where(
                                        'XXX',
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                    break;
                            }
                        }
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
                        LPAD(HEX(N.id),6,0),
                        M.Nombre,
                        N.NombreEmpresa,
                        N.Colonia,
                        N.Nombre,
                        N.Paterno,
                        N.Materno,
                        N.NombreEmpresa,
                        N.Nombre,
                        N.Materno,
                        N.Paterno,
                        N.NombreEmpresa,
                        N.Paterno,
                        N.Nombre,
                        N.Materno,
                        N.NombreEmpresa,
                        N.Paterno,
                        N.Materno,
                        N.Nombre,
                        N.NombreEmpresa,
                        N.Materno,
                        N.Nombre,
                        N.Paterno,
                        N.NombreEmpresa,
                        N.Materno,
                        N.Paterno,
                        N.Nombre,
                        N.Nombre,
                        N.NombreEmpresa,
                        N.Paterno,
                        N.Materno,
                        N.Nombre,
                        N.NombreEmpresa,
                        N.Materno,
                        N.Paterno,
                        N.Nombre,
                        N.Paterno,
                        N.NombreEmpresa,
                        N.Materno,
                        N.Nombre,
                        N.Paterno,
                        N.Materno,
                        N.NombreEmpresa,
                        N.Nombre,
                        N.Materno,
                        N.NombreEmpresa,
                        N.Paterno,
                        N.Nombre,
                        N.Materno,
                        N.Paterno,
                        N.NombreEmpresa,
                        N.Paterno,
                        N.NombreEmpresa,
                        N.Nombre,
                        N.Materno,
                        N.Paterno,
                        N.NombreEmpresa,
                        N.Materno,
                        N.Nombre,
                        N.Paterno,
                        N.Nombre,
                        N.NombreEmpresa,
                        N.Materno,
                        N.Paterno,
                        N.Nombre,
                        N.Materno,
                        N.NombreEmpresa,
                        N.Paterno,
                        N.Materno,
                        N.NombreEmpresa,
                        N.Nombre,
                        N.Paterno,
                        N.Materno,
                        N.Nombre,
                        N.NombreEmpresa,
                        N.Materno,
                        N.NombreEmpresa,
                        N.Nombre,
                        N.Paterno,
                        N.Materno,
                        N.NombreEmpresa,
                        N.Paterno,
                        N.Nombre,
                        N.Materno,
                        N.Nombre,
                        N.NombreEmpresa,
                        N.Paterno,
                        N.Materno,
                        N.Nombre,
                        N.Paterno,
                        N.NombreEmpresa,
                        N.Materno,
                        N.Paterno,
                        N.NombreEmpresa,
                        N.Nombre,
                        N.Materno,
                        N.Paterno,
                        N.Nombre,
                        N.NombreEmpresa
                        
                    ), ' ', '')"),

                    'like',
                    '%' . $filtro_recibido . '%'
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

            $total = $res->count();
            $res = $res
                ->offset($startIndex)
                ->take($pageSize)
                ->get();

            foreach ($res as $data) {
                $res_cobradores = DB::table('v_negocios_pagadores as P')
                    ->select(
                        'P.id',
                        'P.idNegocio',
                        'P.CURP',
                        'P.Nombre',
                        'P.Paterno',
                        'P.Materno',
                        'P.idStatus'
                    )
                    ->where('P.idNegocio', '=', $data->id)
                    ->get();

                $data->Cobradores = $res_cobradores;
            }

            foreach ($res as $data) {
                $res_giros = DB::table('v_negocios_giros as P')
                    ->select('P.idNegocio', 'P.idGiro', 'M.Giro')
                    ->leftJoin('v_giros as M', 'M.id', '=', 'P.idGiro')
                    ->where('P.idNegocio', '=', $data->id)
                    ->get();

                $data->Giros = $res_giros;
            }

            $parameters_serializado = serialize($parameters);
            //$array = unserialize($parameters_serializado);
            $user = auth()->user();
            $filtro_usuario = VNegociosFiltros::where('idUser', '=', $user->id)
                ->where('api', '=', 'getNegociosApp')
                ->first();
            if ($filtro_usuario) {
                $filtro_usuario->parameters = $parameters_serializado;
                $filtro_usuario->update();
            } else {
                $objeto_nuevo = new VNegociosFiltros();
                $objeto_nuevo->api = 'getNegociosApp';
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
                'errors' => $errors,
                'message' => 'Campo de consulta incorrecto',
            ];

            return response()->json($response, 200);
        }
    }

    function getNegociosMaps(Request $request)
    {
        $parameters = $request->all();

        try {
            //AQUI ES LA FUNCION ORIGINAL
            $res = DB::table('v_negocios as N')
                ->select(
                    'N.id',
                    'N.Codigo',
                    DB::raw('LPAD(HEX(N.id),6,0) as ClaveUnica'),
                    'N.RFC',
                    'N.NombreEmpresa',
                    'N.Nombre',
                    'N.Paterno',
                    'N.Materno',
                    'N.TelNegocio',
                    'N.TelCasa',
                    'N.Celular',
                    'N.idMunicipio',
                    'M.Nombre AS Municipio',
                    'M.SubRegion AS Region',
                    'N.Calle',
                    'N.NumExt',
                    'N.NumInt',
                    'N.Colonia',
                    'N.CP',
                    'N.idTipoNegocio',
                    'NT.Tipo AS TipoGiro',
                    'N.Correo',
                    'N.QuiereTransferencia',
                    'N.Banco',
                    'N.CLABE',
                    'N.NumTarjeta',
                    'N.Latitude',
                    'N.Longitude',
                    'N.FechaInscripcion',
                    'N.HorarioAtencion',
                    'N.idStatus',
                    'E.Estatus',
                    'N.created_at',
                    'N.updated_at',
                    DB::raw(
                        'CONCAT(T.Nombre," ", T.Paterno," ", T.Materno) as UserCapturo'
                    ),
                    DB::raw(
                        'CONCAT(U.Nombre," ", U.Paterno," ", U.Materno) as UserActualizo'
                    )
                )
                ->leftJoin(
                    'et_cat_municipio as M',
                    'M.Id',
                    '=',
                    'N.idMunicipio'
                )
                ->leftJoin(
                    'v_negocios_tipo as NT',
                    'NT.id',
                    '=',
                    'N.idTipoNegocio'
                )
                ->leftJoin('v_negocios_status as E', 'E.id', '=', 'N.idStatus')
                ->leftJoin('users as T', 'T.id', '=', 'N.UserCreated')
                ->leftJoin('users as U', 'U.id', '=', 'N.UserUpdated');

            if (isset($parameters['idStatus'])) {
                $res->where('N.idStatus', '=', $parameters['idStatus']);
            }
            if (isset($parameters['idMunicipio'])) {
                if (is_array($parameters['idMunicipio'])) {
                    $res->whereIn('N.idMunicipio', $parameters['idMunicipio']);
                } else {
                    $res->where(
                        'N.idMunicipio',
                        '=',
                        $parameters['idMunicipio']
                    );
                }
            }
            if (isset($parameters['idRegion'])) {
                if (is_array($parameters['idRegion'])) {
                    $res->whereIn('M.SubRegion', $parameters['idRegion']);
                } else {
                    $res->where('M.SubRegion', '=', $parameters['idRegion']);
                }
            }
            if (isset($parameters['idTipoNegocio'])) {
                if (is_array($parameters['idTipoNegocio'])) {
                    $res->whereIn(
                        'N.idTipoNegocio',
                        $parameters['idTipoNegocio']
                    );
                } else {
                    $res->where(
                        'N.idTipoNegocio',
                        '=',
                        $parameters['idTipoNegocio']
                    );
                }
            }
            if (isset($parameters['idTipoGiro'])) {
                $res->join(
                    'v_negocios_giros',
                    'v_negocios_giros.idNegocio',
                    'N.id'
                );
                $res->join('v_giros', 'v_giros.id', 'v_negocios_giros.idGiro');
                if (is_array($parameters['idTipoGiro'])) {
                    $res->whereIn(
                        'v_negocios_giros.idGiro',
                        $parameters['idTipoGiro']
                    );
                } else {
                    $res->where(
                        'v_negocios_giros.idGiro',
                        '=',
                        $parameters['idTipoGiro']
                    );
                }
            }
            if (isset($parameters['Folio'])) {
                $valor_id = $parameters['Folio'];
                $res->where(
                    DB::raw('LPAD(HEX(N.id),6,0)'),
                    'like',
                    '%' . $valor_id . '%'
                );
            }

            $flag = 0;
            if (isset($parameters['filtered'])) {
                for ($i = 0; $i < count($parameters['filtered']); $i++) {
                    if ($flag == 0) {
                        switch ($parameters['filtered'][$i]['id']) {
                            case 'id':
                                if (
                                    is_array(
                                        $parameters['filtered'][$i]['value']
                                    )
                                ) {
                                    $res->whereIn(
                                        'N.id',
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    $res->where(
                                        'N.id',
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                }
                                break;
                            case 'ClaveUnica':
                                $res->where(
                                    DB::raw('LPAD(HEX(N.id),6,0)'),
                                    '=',
                                    $parameters['filtered'][$i]['value']
                                );
                                break;
                            case 'RFC':
                            case 'NombreEmpresa':
                            case 'TelNegocio':
                            case 'TelCasa':
                            case 'Celular':
                            case 'Calle':
                            case 'NumExt':
                            case 'NumInt':
                            case 'Colonia':
                            case 'CP':
                            case 'Correo':
                            case 'FechaInscripcion':
                            case 'HorarioAtencion':
                                $res->where(
                                    'N.' . $parameters['filtered'][$i]['id'],
                                    'LIKE',
                                    '%' .
                                        $parameters['filtered'][$i]['value'] .
                                        '%'
                                );
                                break;
                            case 'Contacto':
                                $contacto_buscar =
                                    $parameters['filtered'][$i]['value'];
                                $contacto_buscar = str_replace(
                                    ' ',
                                    '',
                                    $contacto_buscar
                                );

                                $res->where(
                                    DB::raw("
                                            REPLACE(
                                            CONCAT(
                                                N.Nombre,
                                                N.Paterno,
                                                N.Materno,
                                                N.Paterno,
                                                N.Nombre,
                                                N.Materno,
                                                N.Materno,
                                                N.Nombre,
                                                N.Paterno,
                                                N.Nombre,
                                                N.Materno,
                                                N.Paterno,
                                                N.Paterno,
                                                N.Materno,
                                                N.Nombre,
                                                N.Materno,
                                                N.Paterno,
                                                N.Nombre
                                            ), ' ', '')"),

                                    'like',
                                    '%' . $contacto_buscar . '%'
                                );
                                break;
                            case 'idMunicipio':
                                if (
                                    is_array(
                                        $parameters['filtered'][$i]['value']
                                    )
                                ) {
                                    $res->whereIn(
                                        'N.idMunicipio',
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    $res->where(
                                        'N.idMunicipio',
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                }
                                break;
                            case 'Municipio':
                                $res->where(
                                    'M.Nombre',
                                    'LIKE',
                                    '%' .
                                        $parameters['filtered'][$i]['value'] .
                                        '%'
                                );
                                break;
                            case 'Region':
                                $res->where(
                                    'M.SubRegion',
                                    'LIKE',
                                    '%' .
                                        $parameters['filtered'][$i]['value'] .
                                        '%'
                                );
                                break;
                            case 'idTipoNegocio':
                                if (
                                    is_array(
                                        $parameters['filtered'][$i]['value']
                                    )
                                ) {
                                    $res->whereIn(
                                        'N.idTipoNegocio',
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    $res->where(
                                        'N.idTipoNegocio',
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                }
                                break;
                            case 'TipoNegocio':
                                $res->where(
                                    'NT.Tipo',
                                    'LIKE',
                                    '%' .
                                        $parameters['filtered'][$i]['value'] .
                                        '%'
                                );
                                break;

                            case 'Estatus':
                                if (
                                    is_array(
                                        $parameters['filtered'][$i]['value']
                                    )
                                ) {
                                    $res->whereIn(
                                        'N.idStatus',
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    $res->where(
                                        'N.idStatus',
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                }
                                break;

                            default:
                                $res->where(
                                    'XXX',
                                    'LIKE',
                                    '%' .
                                        $parameters['filtered'][$i]['value'] .
                                        '%'
                                );
                                break;
                        }
                        $flag = 1;
                    } else {
                        if ($parameters['tipo'] == 'and') {
                            switch ($parameters['filtered'][$i]['id']) {
                                case 'id':
                                    if (
                                        is_array(
                                            $parameters['filtered'][$i]['value']
                                        )
                                    ) {
                                        $res->whereIn(
                                            'id',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->where(
                                            'id',
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    }
                                    break;
                                case 'RFC':
                                case 'NombreEmpresa':
                                case 'TelNegocio':
                                case 'TelCasa':
                                case 'Celular':
                                case 'Calle':
                                case 'NumExt':
                                case 'NumInt':
                                case 'Colonia':
                                case 'CP':
                                case 'Correo':
                                case 'FechaInscripcion':
                                case 'HorarioAtencion':
                                    $res->where(
                                        'N.' .
                                            $parameters['filtered'][$i]['id'],
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                    break;
                                case 'Contacto':
                                    $contacto_buscar =
                                        $parameters['filtered'][$i]['value'];
                                    $contacto_buscar = str_replace(
                                        ' ',
                                        '',
                                        $contacto_buscar
                                    );
                                    $res->where(
                                        DB::raw("
                                                REPLACE(
                                                CONCAT(
                                                    N.Nombre,
                                                N.Paterno,
                                                N.Materno,
                                                N.Paterno,
                                                N.Nombre,
                                                N.Materno,
                                                N.Materno,
                                                N.Nombre,
                                                N.Paterno,
                                                N.Nombre,
                                                N.Materno,
                                                N.Paterno,
                                                N.Paterno,
                                                N.Materno,
                                                N.Nombre,
                                                N.Materno,
                                                N.Paterno,
                                                N.Nombre
                                                    
                                                ), ' ', '')"),

                                        'like',
                                        '%' . $contacto_buscar . '%'
                                    );
                                    break;
                                case 'idMunicipio':
                                    if (
                                        is_array(
                                            $parameters['filtered'][$i]['value']
                                        )
                                    ) {
                                        $res->whereIn(
                                            'N.idMunicipio',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->where(
                                            'N.idMunicipio',
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    }
                                    break;
                                case 'Municipio':
                                    $res->where(
                                        'M.Nombre',
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                    break;
                                case 'Region':
                                    $res->where(
                                        'M.SubRegion',
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                    break;
                                case 'idTipoNegocio':
                                    if (
                                        is_array(
                                            $parameters['filtered'][$i]['value']
                                        )
                                    ) {
                                        $res->whereIn(
                                            'N.idTipoNegocio',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->where(
                                            'N.idTipoNegocio',
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    }
                                    break;
                                case 'TipoNegocio':
                                    $res->where(
                                        'NT.Tipo',
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                    break;

                                case 'Estatus':
                                    if (
                                        is_array(
                                            $parameters['filtered'][$i]['value']
                                        )
                                    ) {
                                        $res->whereIn(
                                            'N.idStatus',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->where(
                                            'N.idStatus',
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    }
                                    break;
                                default:
                                    $res->where(
                                        'XXX',
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                    break;
                            }
                        } else {
                            switch ($parameters['filtered'][$i]['id']) {
                                case 'id':
                                    if (
                                        is_array(
                                            $parameters['filtered'][$i]['value']
                                        )
                                    ) {
                                        $res->orWhereIn(
                                            'id',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->orWhere(
                                            'id',
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    }
                                    break;
                                case 'RFC':
                                case 'NombreEmpresa':
                                case 'TelNegocio':
                                case 'TelCasa':
                                case 'Celular':
                                case 'Calle':
                                case 'NumExt':
                                case 'NumInt':
                                case 'Colonia':
                                case 'CP':
                                case 'Correo':
                                case 'FechaInscripcion':
                                case 'HorarioAtencion':
                                    $res->orWhere(
                                        'N.' .
                                            $parameters['filtered'][$i]['id'],
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                    break;
                                case 'Contacto':
                                    $contacto_buscar =
                                        $parameters['filtered'][$i]['value'];
                                    $contacto_buscar = str_replace(
                                        ' ',
                                        '',
                                        $contacto_buscar
                                    );
                                    $res->orWhere(
                                        DB::raw("
                                                REPLACE(
                                                CONCAT(
                                                    N.Nombre,
                                                N.Paterno,
                                                N.Materno,
                                                N.Paterno,
                                                N.Nombre,
                                                N.Materno,
                                                N.Materno,
                                                N.Nombre,
                                                N.Paterno,
                                                N.Nombre,
                                                N.Materno,
                                                N.Paterno,
                                                N.Paterno,
                                                N.Materno,
                                                N.Nombre,
                                                N.Materno,
                                                N.Paterno,
                                                N.Nombre
                                                    
                                                ), ' ', '')"),

                                        'like',
                                        '%' . $contacto_buscar . '%'
                                    );
                                    break;
                                case 'idMunicipio':
                                    if (
                                        is_array(
                                            $parameters['filtered'][$i]['value']
                                        )
                                    ) {
                                        $res->orWhereIn(
                                            'N.idMunicipio',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->orWhere(
                                            'N.idMunicipio',
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    }
                                    break;
                                case 'Municipio':
                                    $res->orWhere(
                                        'M.Nombre',
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                    break;
                                case 'Region':
                                    $res->orWhere(
                                        'M.SubRegion',
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                    break;
                                case 'idTipoNegocio':
                                    if (
                                        is_array(
                                            $parameters['filtered'][$i]['value']
                                        )
                                    ) {
                                        $res->orWhereIn(
                                            'idTipoNegocio',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->orWhere(
                                            'idTipoNegocio',
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    }
                                    break;
                                case 'TipoNegocio':
                                    $res->orWhere(
                                        'NT.Tipo',
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                    break;

                                case 'Estatus':
                                    if (
                                        is_array(
                                            $parameters['filtered'][$i]['value']
                                        )
                                    ) {
                                        $res->orWhereIn(
                                            'N.idStatus',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->orWhere(
                                            'N.idStatus',
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    }
                                    break;
                                default:
                                    $res->where(
                                        'XXX',
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                    break;
                            }
                        }
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
                        LPAD(HEX(N.id),6,0),
                        M.Nombre,
                        N.NombreEmpresa,
                        N.Colonia,
                        N.Nombre,
                        N.Paterno,
                        N.Materno,
                        N.NombreEmpresa,
                        N.Nombre,
                        N.Materno,
                        N.Paterno,
                        N.NombreEmpresa,
                        N.Paterno,
                        N.Nombre,
                        N.Materno,
                        N.NombreEmpresa,
                        N.Paterno,
                        N.Materno,
                        N.Nombre,
                        N.NombreEmpresa,
                        N.Materno,
                        N.Nombre,
                        N.Paterno,
                        N.NombreEmpresa,
                        N.Materno,
                        N.Paterno,
                        N.Nombre,
                        N.Nombre,
                        N.NombreEmpresa,
                        N.Paterno,
                        N.Materno,
                        N.Nombre,
                        N.NombreEmpresa,
                        N.Materno,
                        N.Paterno,
                        N.Nombre,
                        N.Paterno,
                        N.NombreEmpresa,
                        N.Materno,
                        N.Nombre,
                        N.Paterno,
                        N.Materno,
                        N.NombreEmpresa,
                        N.Nombre,
                        N.Materno,
                        N.NombreEmpresa,
                        N.Paterno,
                        N.Nombre,
                        N.Materno,
                        N.Paterno,
                        N.NombreEmpresa,
                        N.Paterno,
                        N.NombreEmpresa,
                        N.Nombre,
                        N.Materno,
                        N.Paterno,
                        N.NombreEmpresa,
                        N.Materno,
                        N.Nombre,
                        N.Paterno,
                        N.Nombre,
                        N.NombreEmpresa,
                        N.Materno,
                        N.Paterno,
                        N.Nombre,
                        N.Materno,
                        N.NombreEmpresa,
                        N.Paterno,
                        N.Materno,
                        N.NombreEmpresa,
                        N.Nombre,
                        N.Paterno,
                        N.Materno,
                        N.Nombre,
                        N.NombreEmpresa,
                        N.Materno,
                        N.NombreEmpresa,
                        N.Nombre,
                        N.Paterno,
                        N.Materno,
                        N.NombreEmpresa,
                        N.Paterno,
                        N.Nombre,
                        N.Materno,
                        N.Nombre,
                        N.NombreEmpresa,
                        N.Paterno,
                        N.Materno,
                        N.Nombre,
                        N.Paterno,
                        N.NombreEmpresa,
                        N.Materno,
                        N.Paterno,
                        N.NombreEmpresa,
                        N.Nombre,
                        N.Materno,
                        N.Paterno,
                        N.Nombre,
                        N.NombreEmpresa
                        
                    ), ' ', '')"),

                    'like',
                    '%' . $filtro_recibido . '%'
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

    function getNegociosPublico(Request $request)
    {
        $parameters = $request->all();

        try {
            $user = auth()->user();
            $usuario_permitido = DB::table('users_apis')
                ->select('idUser', 'Apis')
                ->where('idUser', '=', $user->id)
                ->where('Apis', '=', 'getNegociosPublico')
                ->first();

            if (!$usuario_permitido) {
                $errors = [
                    'Clave' => '00',
                ];
                $response = [
                    'success' => true,
                    'results' => false,
                    'total' => 0,
                    'filtros' => $parameters['filtered'],
                    'errors' => $errors,
                    'message' =>
                        'Este usuario no cuenta con permisos suficientes para ejecutar esta api.',
                ];

                return response()->json($response, 200);
            }

            $res = DB::table('v_negocios as N')
                ->select(
                    'N.id',
                    'N.RFC',
                    'N.NombreEmpresa',
                    'N.TelNegocio',
                    'N.idMunicipio',
                    'M.Nombre AS Municipio',
                    'M.SubRegion AS Region',
                    'N.Calle',
                    'N.NumExt',
                    'N.NumInt',
                    'N.Colonia',
                    'N.CP',
                    'N.idTipoNegocio',
                    'NT.Tipo AS TipoGiro',
                    'N.Latitude',
                    'N.Longitude',
                    'N.HorarioAtencion',
                    'N.idStatus',
                    'E.Estatus',
                    'N.created_at',
                    'N.updated_at',
                    DB::raw(
                        'CONCAT(T.Nombre," ", T.Paterno," ", T.Materno) as UserCapturo'
                    ),
                    DB::raw(
                        'CONCAT(U.Nombre," ", U.Paterno," ", U.Materno) as UserActualizo'
                    )
                )
                ->leftJoin(
                    'et_cat_municipio as M',
                    'M.Id',
                    '=',
                    'N.idMunicipio'
                )
                ->leftJoin(
                    'v_negocios_tipo as NT',
                    'NT.id',
                    '=',
                    'N.idTipoNegocio'
                )
                ->leftJoin('v_negocios_status as E', 'E.id', '=', 'N.idStatus')
                ->leftJoin('users as T', 'T.id', '=', 'N.UserCreated')
                ->leftJoin('users as U', 'U.id', '=', 'N.UserUpdated');

            if (isset($parameters['idStatus'])) {
                $res->where('N.idStatus', '=', $parameters['idStatus']);
            }

            $flag = 0;
            if (isset($parameters['filtered'])) {
                for ($i = 0; $i < count($parameters['filtered']); $i++) {
                    if ($flag == 0) {
                        switch ($parameters['filtered'][$i]['id']) {
                            case 'id':
                                if (
                                    is_array(
                                        $parameters['filtered'][$i]['value']
                                    )
                                ) {
                                    $res->whereIn(
                                        'N.id',
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    $res->where(
                                        'N.id',
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                }
                                break;
                            case 'RFC':
                            case 'NombreEmpresa':
                            case 'TelNegocio':
                            case 'TelCasa':
                            case 'Celular':
                            case 'Calle':
                            case 'NumExt':
                            case 'NumInt':
                            case 'Colonia':
                            case 'CP':
                            case 'Correo':
                            case 'HorarioAtencion':
                                $res->where(
                                    'N.' . $parameters['filtered'][$i]['id'],
                                    'LIKE',
                                    '%' .
                                        $parameters['filtered'][$i]['value'] .
                                        '%'
                                );
                                break;
                            case 'idMunicipio':
                                if (
                                    is_array(
                                        $parameters['filtered'][$i]['value']
                                    )
                                ) {
                                    $res->whereIn(
                                        'N.idMunicipio',
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    $res->where(
                                        'N.idMunicipio',
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                }
                                break;
                            case 'Municipio':
                                $res->where(
                                    'M.Nombre',
                                    'LIKE',
                                    '%' .
                                        $parameters['filtered'][$i]['value'] .
                                        '%'
                                );
                                break;
                            case 'Region':
                                $res->where(
                                    'M.SubRegion',
                                    'LIKE',
                                    '%' .
                                        $parameters['filtered'][$i]['value'] .
                                        '%'
                                );
                                break;
                            case 'idTipoNegocio':
                                if (
                                    is_array(
                                        $parameters['filtered'][$i]['value']
                                    )
                                ) {
                                    $res->whereIn(
                                        'N.idTipoNegocio',
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    $res->where(
                                        'N.idTipoNegocio',
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                }
                                break;
                            case 'TipoNegocio':
                                $res->where(
                                    'NT.Tipo',
                                    'LIKE',
                                    '%' .
                                        $parameters['filtered'][$i]['value'] .
                                        '%'
                                );
                                break;

                            case 'Estatus':
                                if (
                                    is_array(
                                        $parameters['filtered'][$i]['value']
                                    )
                                ) {
                                    $res->whereIn(
                                        'N.idStatus',
                                        $parameters['filtered'][$i]['value']
                                    );
                                } else {
                                    $res->where(
                                        'N.idStatus',
                                        '=',
                                        $parameters['filtered'][$i]['value']
                                    );
                                }
                                break;

                            default:
                                $res->where(
                                    'XXX',
                                    'LIKE',
                                    '%' .
                                        $parameters['filtered'][$i]['value'] .
                                        '%'
                                );
                                break;
                        }
                        $flag = 1;
                    } else {
                        if ($parameters['tipo'] == 'and') {
                            switch ($parameters['filtered'][$i]['id']) {
                                case 'id':
                                    if (
                                        is_array(
                                            $parameters['filtered'][$i]['value']
                                        )
                                    ) {
                                        $res->whereIn(
                                            'id',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->where(
                                            'id',
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    }
                                    break;
                                case 'RFC':
                                case 'NombreEmpresa':
                                case 'TelNegocio':
                                case 'TelCasa':
                                case 'Celular':
                                case 'Calle':
                                case 'NumExt':
                                case 'NumInt':
                                case 'Colonia':
                                case 'CP':
                                case 'Correo':
                                case 'HorarioAtencion':
                                    $res->where(
                                        'N.' .
                                            $parameters['filtered'][$i]['id'],
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                    break;
                                case 'idMunicipio':
                                    if (
                                        is_array(
                                            $parameters['filtered'][$i]['value']
                                        )
                                    ) {
                                        $res->whereIn(
                                            'N.idMunicipio',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->where(
                                            'N.idMunicipio',
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    }
                                    break;
                                case 'Municipio':
                                    $res->where(
                                        'M.Nombre',
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                    break;
                                case 'Region':
                                    $res->where(
                                        'M.SubRegion',
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                    break;
                                case 'idTipoNegocio':
                                    if (
                                        is_array(
                                            $parameters['filtered'][$i]['value']
                                        )
                                    ) {
                                        $res->whereIn(
                                            'N.idTipoNegocio',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->where(
                                            'N.idTipoNegocio',
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    }
                                    break;
                                case 'TipoNegocio':
                                    $res->where(
                                        'NT.Tipo',
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                    break;

                                case 'Estatus':
                                    if (
                                        is_array(
                                            $parameters['filtered'][$i]['value']
                                        )
                                    ) {
                                        $res->whereIn(
                                            'N.idStatus',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->where(
                                            'N.idStatus',
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    }
                                    break;
                                default:
                                    $res->where(
                                        'XXX',
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                    break;
                            }
                        } else {
                            switch ($parameters['filtered'][$i]['id']) {
                                case 'id':
                                    if (
                                        is_array(
                                            $parameters['filtered'][$i]['value']
                                        )
                                    ) {
                                        $res->orWhereIn(
                                            'id',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->orWhere(
                                            'id',
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    }
                                    break;
                                case 'RFC':
                                case 'NombreEmpresa':
                                case 'TelNegocio':
                                case 'TelCasa':
                                case 'Celular':
                                case 'Calle':
                                case 'NumExt':
                                case 'NumInt':
                                case 'Colonia':
                                case 'CP':
                                case 'Correo':
                                case 'HorarioAtencion':
                                    $res->orWhere(
                                        'N.' .
                                            $parameters['filtered'][$i]['id'],
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                    break;
                                case 'idMunicipio':
                                    if (
                                        is_array(
                                            $parameters['filtered'][$i]['value']
                                        )
                                    ) {
                                        $res->orWhereIn(
                                            'N.idMunicipio',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->orWhere(
                                            'N.idMunicipio',
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    }
                                    break;
                                case 'Municipio':
                                    $res->orWhere(
                                        'M.Nombre',
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                    break;
                                case 'Region':
                                    $res->orWhere(
                                        'M.SubRegion',
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                    break;
                                case 'idTipoNegocio':
                                    if (
                                        is_array(
                                            $parameters['filtered'][$i]['value']
                                        )
                                    ) {
                                        $res->orWhereIn(
                                            'idTipoNegocio',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->orWhere(
                                            'idTipoNegocio',
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    }
                                    break;
                                case 'TipoNegocio':
                                    $res->orWhere(
                                        'NT.Tipo',
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                    break;

                                case 'Estatus':
                                    if (
                                        is_array(
                                            $parameters['filtered'][$i]['value']
                                        )
                                    ) {
                                        $res->orWhereIn(
                                            'N.idStatus',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    } else {
                                        $res->orWhere(
                                            'N.idStatus',
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                    }
                                    break;
                                default:
                                    $res->where(
                                        'XXX',
                                        'LIKE',
                                        '%' .
                                            $parameters['filtered'][$i][
                                                'value'
                                            ] .
                                            '%'
                                    );
                                    break;
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

            foreach ($res as $data) {
                $res_giros = DB::table('v_negocios_giros as P')
                    ->select('P.idNegocio', 'P.idGiro', 'M.Giro')
                    ->leftJoin('v_giros as M', 'M.id', '=', 'P.idGiro')
                    ->where('P.idNegocio', '=', $data->id)
                    ->get();

                $data->Giros = $res_giros;
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
                'errors' => $errors,
                'message' => 'Campo de consulta incorrecto',
            ];

            return response()->json($response, 200);
        }
    }

    function getNegociosResumen(Request $request)
    {
        $parameters = $request->all();

        try {
            $resTipo = DB::table('v_negocios as N')
                ->select(
                    'T.Tipo as TipoNegocio',
                    DB::raw('count(N.id) as Total')
                )
                ->leftJoin(
                    'v_negocios_tipo as T',
                    'N.idTipoNegocio',
                    '=',
                    'T.id'
                )
                ->where('N.idStatus', '=', 3) //zincri
                ->groupBy('N.idTipoNegocio');
            if (isset($parameters['idMunicipio'])) {
                if (is_array($parameters['idMunicipio'])) {
                    $resTipo->whereIn(
                        'N.idMunicipio',
                        $parameters['idMunicipio']
                    );
                } else {
                    $resTipo->where(
                        'N.idMunicipio',
                        '=',
                        $parameters['idMunicipio']
                    );
                }
            }
            $resTipo = $resTipo->get();

            $resResumen2 = DB::table('v_negocios as N')
                ->select('S.Estatus', DB::raw('count(N.idStatus) Total'))
                ->leftJoin('v_negocios_status as S', 'N.idStatus', '=', 'S.id')
                ->groupBy('N.idStatus');
            if (isset($parameters['idMunicipio'])) {
                if (is_array($parameters['idMunicipio'])) {
                    $resResumen2->whereIn(
                        'N.idMunicipio',
                        $parameters['idMunicipio']
                    );
                } else {
                    $resResumen2->where(
                        'N.idMunicipio',
                        '=',
                        $parameters['idMunicipio']
                    );
                }
            }

            $resResumen2 = $resResumen2->get();

            $resTotal = DB::table('v_negocios as N')->select(
                DB::raw('count(N.id) as Total')
            );
            $resTotal = $resTotal->get();

            $dataRS = [
                'tipo' => $resTipo,
                'estatus' => $resResumen2,
            ];

            return [
                'success' => true,
                'results' => true,
                'total' => $resTotal[0]->Total,
                'data' => $dataRS,
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

    function setNegocios(Request $request)
    {
        $v = Validator::make($request->all(), [
            //'RFC' => 'required',
            'NombreEmpresa' => 'required',
            'Nombre' => 'required',
            'Paterno' => 'required',
            'Materno' => 'required',
            //'TelNegocio'=> 'required',
            //'TelCasa'=> 'required',
            //'Celular'=> 'required',
            'idMunicipio' => 'required',
            'Calle' => 'required',
            'NumExt' => 'required',
            //'NumInt'=> 'required',
            //'Colonia'=> 'required',
            'CP' => 'required',
            //'Correo'=> 'required',
            //'Latitude'=> 'required',
            //'Longitude'=> 'required',
            'FechaInscripcion' => 'required',
            'Cobrador' => 'required',
            'idStatus' => 'required',
            'idTipoNegocio' => 'required',
            'Giros' => 'required',
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
        $parameters['UserCreated'] = $user->id;
        $parameters['UserUpdated'] = $user->id;
        if ($parameters['idStatus']) {
            if ($parameters['idStatus'] === 3) {
                $parameters['UserValidate'] = $user->id;
                $parameters['validated_at'] = date('Y-m-d H:i:s');
            } else {
                $parameters['UserValidate'] = null;
                $parameters['validated_at'] = null;
            }
        }

        try {
            $empresa_recibida =
                $parameters['NombreEmpresa'] .
                $parameters['idMunicipio'] .
                $parameters['Nombre'] .
                $parameters['Paterno'] .
                $parameters['Materno'] .
                $parameters['Calle'];
            $empresa_recibida = str_replace(' ', '', $empresa_recibida);
            $res = DB::table('v_negocios')
                ->select(
                    'id',
                    DB::raw("
                REPLACE(
                CONCAT(
                    NombreEmpresa,
                    idMunicipio,
                    Nombre,
                    Paterno,
                    Materno,
                    Calle
                ), ' ', '') as NombreCompleto")
                )
                ->get();

            $flag = false;
            $id_existente = 0;
            for ($i = 0; $i < $res->count(); $i++) {
                if (
                    strcasecmp($empresa_recibida, $res[$i]->NombreCompleto) ===
                    0
                ) {
                    $flag = true;
                    $id_existente = $res[$i]->id;
                    break;
                }
            }
            if ($flag) {
                $errors = [
                    'Clave' =>
                        'La empresa que decea registrar ya se encuentra registrada.',
                ];
                $persona_existente = VNegocios::find($id_existente);
                $response = [
                    'success' => false,
                    'results' => false,
                    'errors' => $errors,
                    'message' =>
                        'La empresa que decea registrar ya se encuentra registrada.',
                    'Empresa Existente' => $persona_existente,
                ];

                return response()->json($response, 200);
            }

            $negocio_ = VNegocios::create($parameters);

            $negocio = VNegocios::find($negocio_->id);

            for ($i = 0; $i < count($parameters['Giros']); $i++) {
                $obj = new VNegociosGiros();
                $obj->idNegocio = $negocio->id;
                $obj->idGiro = $parameters['Giros'][$i];
                $obj->UserCreated = $user->id;
                $obj->UserUpdated = $user->id;
                $obj->save();
            }

            //Agrego al cobrador
            $objP = new VNegociosPagadores();
            $objP->idNegocio = $negocio->id;
            $objP->CURP = $parameters['Cobrador'][0]['CURP'];
            $objP->Nombre = $parameters['Cobrador'][0]['Nombre'];
            $objP->Paterno = $parameters['Cobrador'][0]['Paterno'];
            $objP->Materno = $parameters['Cobrador'][0]['Materno'];
            $objP->idStatus = 1;
            $objP->UserCreated = $user->id;
            $objP->UserUpdated = $user->id;
            $objP->save();

            $res_giros = DB::table('v_negocios_giros')
                ->select('v_giros.id', 'v_giros.Giro')
                ->leftJoin(
                    'v_giros',
                    'v_giros.id',
                    '=',
                    'v_negocios_giros.idGiro'
                )
                ->where('v_negocios_giros.idNegocio', '=', $negocio->id)
                ->get();
            $negocio->Giros = $res_giros;

            return ['success' => true, 'results' => true, 'data' => $negocio];
        } catch (QueryException $e) {
            dd($e);

            $errors = [
                'Clave' => 'Error Interno de Query',
            ];
            $response = [
                'success' => true,
                'results' => false,
                'total' => 0,
                'errors' => $errors,
                'message' => 'Error Interno de Query',
            ];

            return response()->json($response, 200);
        }
    }

    function updateNegocios(Request $request)
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
        //dd($user->id);
        $parameters['UserUpdated'] = $user->id;

        try {
            $negocio = VNegocios::find($parameters['id']);
            if (!$negocio) {
                return [
                    'success' => true,
                    'results' => false,
                    'errors' => 'El negocio que desea actualizar no existe.',
                    'data' => [],
                ];
            }

            if ($parameters['idStatus']) {
                if ($parameters['idStatus'] === 3) {
                    $parameters['UserValidate'] = $user->id;
                    $parameters['validated_at'] = date('Y-m-d H:i:s');
                } else {
                    $parameters['UserValidate'] = null;
                    $parameters['validated_at'] = null;
                }
            }

            if (isset($parameters['Cobrador'])) {
                if (isset($parameters['Cobrador'][0]['id'])) {
                    $objP = VNegociosPagadores::find(
                        $parameters['Cobrador'][0]['id']
                    );
                    $parametersP['id'] = $parameters['Cobrador'][0]['id'];
                    $parametersP['idNegocio'] = $negocio->id;
                    $parametersP['CURP'] = $parameters['Cobrador'][0]['CURP'];
                    $parametersP['Nombre'] =
                        $parameters['Cobrador'][0]['Nombre'];
                    $parametersP['Paterno'] =
                        $parameters['Cobrador'][0]['Paterno'];
                    $parametersP['Materno'] =
                        $parameters['Cobrador'][0]['Materno'];
                    $parametersP['idStatus'] =
                        $parameters['Cobrador'][0]['idStatus'];
                    $parameters['UserUpdated'] = $user->id;
                    $objP->update($parametersP);
                } else {
                    $parametersP['UserCreated'] = $user->id;
                    $parametersP['UserUpdated'] = $user->id;
                    $parametersP['idNegocio'] = $negocio->id;
                    $parametersP['CURP'] = $parameters['Cobrador'][0]['CURP'];
                    $parametersP['Nombre'] =
                        $parameters['Cobrador'][0]['Nombre'];
                    $parametersP['Paterno'] =
                        $parameters['Cobrador'][0]['Paterno'];
                    $parametersP['Materno'] =
                        $parameters['Cobrador'][0]['Materno'];
                    $parametersP['idStatus'] =
                        $parameters['Cobrador'][0]['idStatus'];
                    $pagadores_ = VNegociosPagadores::create($parametersP);
                    $pagadores = VNegociosPagadores::find($pagadores_->id);
                }
            }

            if (isset($parameters['QuiereTransferencia'])) {
                if (
                    $parameters['QuiereTransferencia'] === true ||
                    $parameters['QuiereTransferencia'] == 1
                ) {
                    $parameters['QuiereTransferencia'] = 1;
                } else {
                    $parameters['QuiereTransferencia'] = 0;
                }
                //$parameters['QuiereTransferencia'] = ($parameters['QuiereTransferencia'] === true) ? 1 : ($parameters['QuiereTransferencia']==1) ? 1:0;
            }

            if (isset($parameters['Giros'])) {
                $giros = VNegociosGiros::where(
                    'idNegocio',
                    '=',
                    $negocio->id
                )->get();

                for ($i = 0; $i < count($giros); $i++) {
                    $obj = VNegociosGiros::find($giros[$i]->id);

                    if ($obj) {
                        $obj->delete();
                    }
                }

                for ($i = 0; $i < count($parameters['Giros']); $i++) {
                    $obj = new VNegociosGiros();
                    $obj->idNegocio = $negocio->id;
                    $obj->idGiro = $parameters['Giros'][$i];
                    $obj->UserCreated = $user->id;
                    $obj->UserUpdated = $user->id;
                    $obj->save();
                }
            }
            $negocio->update($parameters);
            $negocio = VNegocios::find($negocio->id);

            $res_giros = DB::table('v_negocios_giros')
                ->select('v_giros.id', 'v_giros.Giro')
                ->leftJoin(
                    'v_giros',
                    'v_giros.id',
                    '=',
                    'v_negocios_giros.idGiro'
                )
                ->where('v_negocios_giros.idNegocio', '=', $negocio->id)
                ->get();

            $pagadores = VNegociosPagadores::find($negocio->id);
            $negocio->Giros = $res_giros;
            $negocio->Cobradores = $pagadores;
            return ['success' => true, 'results' => true, 'data' => $negocio];
        } catch (QueryException $e) {
            $errors = [
                'Clave' => 'Error Interno de Query',
            ];
            $response = [
                'success' => true,
                'results' => false,
                'total' => 0,
                'filtros' => $parameters['filtered'],
                'errors' => $errors,
                'message' => 'Error Interno de Query',
            ];

            return response()->json($response, 200);
        }
    }

    function updateBajaNegocios(Request $request)
    {
        $v = Validator::make($request->all(), [
            'id' => 'required',
            'idStatus' => 'required',
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
        //dd($user->id);
        $parameters['UserUpdated'] = $user->id;

        try {
            $negocio = VNegocios::find($parameters['id']);
            if (!$negocio) {
                return [
                    'success' => true,
                    'results' => false,
                    'errors' => 'El negocio que desea actualizar no existe.',
                    'data' => [],
                ];
            }

            if ($parameters['idStatus']) {
                if ($parameters['idStatus'] === 3) {
                    $parameters['UserUpdated'] = $user->id;
                    $parameters['updated_at'] = date('Y-m-d H:i:s');
                }
            }
            $negocio->update($parameters);
            $negocio = VNegocios::find($negocio->id);

            $res_giros = DB::table('v_negocios_giros')
                ->select('v_giros.id', 'v_giros.Giro')
                ->leftJoin(
                    'v_giros',
                    'v_giros.id',
                    '=',
                    'v_negocios_giros.idGiro'
                )
                ->where('v_negocios_giros.idNegocio', '=', $negocio->id)
                ->get();

            $pagadores = VNegociosPagadores::find($negocio->id);
            $negocio->Giros = $res_giros;
            $negocio->Cobradores = $pagadores;
            return ['success' => true, 'results' => true, 'data' => $negocio];
        } catch (QueryException $e) {
            $errors = [
                'Clave' => 'Error Interno de Query',
            ];
            $response = [
                'success' => true,
                'results' => false,
                'total' => 0,
                'filtros' => $parameters['filtered'],
                'errors' => $errors,
                'message' => 'Error Interno de Query',
            ];

            return response()->json($response, 200);
        }
    }

    function updateRefrendoNegocios(Request $request)
    {
        $v = Validator::make($request->all(), [
            'id' => 'required',
            // 'Refrendo2021' => 'required',
            'FechaRefrendo2021' => 'required',
            'UserRefrendo' => 'required',
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
        //dd($user->id);
        $parameters['UserUpdated'] = $user->id;
        $parameters['idStatus'] = '2';

        try {
            $negocio = VNegocios::find($parameters['id']);
            if (!$negocio) {
                return [
                    'success' => true,
                    'results' => false,
                    'errors' => 'El negocio que desea actualizar no existe.',
                    'data' => [],
                ];
            }

            $negocio->update($parameters);
            $negocio = VNegocios::find($negocio->id);

            $res_giros = DB::table('v_negocios_giros')
                ->select('v_giros.id', 'v_giros.Giro')
                ->leftJoin(
                    'v_giros',
                    'v_giros.id',
                    '=',
                    'v_negocios_giros.idGiro'
                )
                ->where('v_negocios_giros.idNegocio', '=', $negocio->id)
                ->get();

            $pagadores = VNegociosPagadores::find($negocio->id);
            $negocio->Giros = $res_giros;
            $negocio->Cobradores = $pagadores;
            return ['success' => true, 'results' => true, 'data' => $negocio];
        } catch (QueryException $e) {
            $errors = [
                'Clave' => 'Error Interno de Query',
            ];
            $response = [
                'success' => true,
                'results' => false,
                'total' => 0,
                'filtros' => $parameters['filtered'],
                'errors' => $errors,
                'message' => 'Error Interno de Query',
            ];

            return response()->json($response, 200);
        }
    }
}
