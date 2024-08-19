<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Zipper;
use Imagick;
use JWTAuth;
use Validator;
use Carbon\Carbon as time;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Database\QueryException;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use Illuminate\Contracts\Validation\ValidationException;

use App\VNegociosFiltros;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class EncuestasController extends Controller
{
    protected function getPermisos($idUser)
    {
        $permisos = DB::table('users_menus')
            ->Select('Ver', 'Agregar', 'Seguimiento', 'ViewAll')
            ->Where(['idUser' => $idUser])
            ->WhereIn('idMenu', [39,41])
            ->first();
        return $permisos;
    }

    protected function getMunicipios(Request $request)
    {
        $user = auth()->user();
        $permisos = $this->getPermisos($user->id);

        if (!$permisos) {
            $response = [
                'success' => true,
                'results' => false,
                'total' => 0,
                'message' => 'No tiene permisos en este módulo',
            ];

            return response()->json($response, 200);
        }

        // $seguimiento = $permisos->Seguimiento;
        // $viewall = $permisos->ViewAll;

        // if ($viewall < 1) {
        //     $queryRegiones = DB::table('users_region')
        //         ->select('Region')
        //         ->where(['idUser' => $user->id])
        //         ->get();
        //     $regiones = [];
        //     foreach ($queryRegiones as $region) {
        //         $regiones[] = $region->Region;
        //     }
        //     $query = DB::table('et_cat_municipio AS m')
        //         ->select('m.Id', 'm.Nombre', 'm.SubRegion')
        //         ->WhereIn('m.SubRegion', $regiones);
        // } else {
        $query = DB::table('et_cat_municipio AS m')->select(
            'm.Id',
            'm.Nombre',
            'm.SubRegion'
        );
        // }

        $res = $query->get();

        return [
            'success' => true,
            'results' => true,
            'data' => $res,
        ];
    }

    function getCatalogs(Request $request)
    {
        try {
            $userId = JWTAuth::parseToken()->toUser()->id;

            $permisos = $this->getPermisos($userId);
            if ($permisos === null) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'total' => 0,
                    'message' => 'No tiene permisos en este módulo',
                ];

                return response()->json($response, 200);
            }

            $tipo_apoyo = DB::table('cat_apoyos')
                ->select('id AS value', 'Apoyo AS label')
                ->orderBy('id')
                ->get();

            $municipios = DB::table('et_cat_municipio')
                ->select('id AS value', 'Nombre AS label')
                ->orderBy('label')
                ->get();

            $cgcsi = DB::Table('cat_CGCSI')
                ->Select('id AS value', 'Nombre AS label')
                ->orderBy('label')
                ->get();

            $catalogs = [
                'tipo_apoyo' => $tipo_apoyo,
                'municipios' => $municipios,
                'cgcsi' => $cgcsi,
            ];

            $response = [
                'success' => true,
                'results' => true,
                'data' => $catalogs,
            ];
            return response()->json($response, 200);
        } catch (QueryException $errors) {
            $response = [
                'success' => false,
                'results' => false,
                'total' => 0,
                'errors' => $errors,
                'message' => 'Ha ocurrido un error, consulte al administrador',
            ];
            return response()->json($response, 200);
        }
    }

    protected function validateInput($value): bool
    {
        if (gettype($value) === 'array') {
            foreach ($value as $v) {
                $containsSpecialChars = preg_match(
                    '@[' . preg_quote("=%;-?!¡\"`+") . ']@',
                    $v
                );
            }
        } else {
            $containsSpecialChars = preg_match(
                '@[' . preg_quote("'=%;-?!¡\"`+") . ']@',
                $value
            );
        }
        return !$containsSpecialChars;
    }

    protected function getEncuestas(Request $request)
    {
        $user = auth()->user();
        $permisos = $this->getPermisos($user->id);

        if (!$permisos) {
            $response = [
                'success' => true,
                'results' => false,
                'total' => 0,
                'message' =>
                    'No tiene permisos para ver la información, contacte al administrador',
            ];
            return response()->json($response, 200);
        }

        $v = Validator::make(
            $request->all(),
            [
                'page' => 'required',
                'pageSize' => 'required',
            ],
            $messages = [
                'required' => 'El campo :attribute es obligatorio',
            ]
        );

        if ($v->fails()) {
            $response = [
                'success' => true,
                'results' => false,
                'total' => 0,
                'message' => $v->errors()->all(),
            ];
            return response()->json($response, 200);
        }

        $params = $request->all();
        $parameters_serializado = serialize($params);

        if (isset($params['filtered']) && count($params['filtered']) > 0) {
            foreach ($params['filtered'] as $filtro) {
                $value = $filtro['value'];

                if (!$this->validateInput($value)) {
                    $response = [
                        'success' => true,
                        'results' => false,
                        'total' => 0,
                        'message' =>
                            'Uno o más filtros utilizados no son válidos, intente nuevamente',
                    ];

                    return response()->json($response, 200);
                }
            }
        }

        $encuestas = DB::table('encuestas AS e')
            ->Select(
                'e.id',
                DB::Raw('LPAD(HEX(e.id),6,0) AS Folio'),
                'e.idTipoApoyo',
                'e.FechaCreo',
                'e.Nombre',
                'e.Paterno',
                'e.Materno',
                'e.CURP',
                'm.SubRegion AS Region',
                'e.idMunicipio',
                'm.Nombre AS Municipio',
                'e.idLocalidad',
                'l.Nombre AS Localidad',
                'e.Colonia',
                'e.Celular',
                'e.Facebook',
                'e.Entrada',
                'e.Salida',
                'e.Autoriza',
                'e.idCGCSI',
                'e.Referencia',
                'e.Observaciones',
                DB::Raw(
                    'CONCAT_WS(" ",u.Nombre,u.Paterno,u.Materno) AS CreadoPor'
                )
            )
            ->Join('et_cat_municipio AS m', 'm.Id', 'e.idMunicipio')
            ->Join('et_cat_localidad_2022 AS l', 'l.id', 'e.idLocalidad')
            ->Join('users AS u', 'u.id', 'e.idUsuarioCreo')
            ->WhereNull('e.FechaElimino');

        if ($permisos->ViewAll == 0) {
            $encuestas = $encuestas->where('e.idUsuarioCreo', $user->id);
        }

        // if ($permisos->ViewAll == 0 && $permisos->Seguimiento == 0) {
        //     $encuestas = $encuestas->where('e.idUsuarioCreo', $user->id);
        // } elseif ($permisos->ViewAll == 0) {
        //     $region = DB::table('users_region')
        //         ->selectRaw('Region')
        //         ->where('idUser', $user->id)
        //         ->first();

        //     if ($region === null) {
        //         $response = [
        //             'success' => true,
        //             'results' => false,
        //             'total' => 0,
        //             'message' => 'No tiene region asignada',
        //         ];

        //         return response()->json($response, 200);
        //     }

        //     $encuestas = $encuestas->where('m.Region', $region->Region);
        // }

        $filterQuery = '';
        $municipioRegion = [];

        if (isset($params['filtered']) && count($params['filtered']) > 0) {
            foreach ($params['filtered'] as $filtro) {
                if ($filterQuery != '') {
                    $filterQuery .= ' AND ';
                }
                $id = $filtro['id'];
                $value = $filtro['value'];

                if ($id == '.id') {
                    $value = hexdec($value);
                }

                if ($id == 'region') {
                    $municipios = DB::table('et_cat_municipio')
                        ->select('Id')
                        ->whereIN('SubRegion', $value)
                        ->get();
                    foreach ($municipios as $m) {
                        $municipioRegion[] = $m->Id;
                    }

                    $id = '.idMunicipio';
                    $value = $municipioRegion;
                }

                $id = 'e' . $id;

                switch (gettype($value)) {
                    case 'string':
                        $filterQuery .= " $id LIKE '%$value%' ";
                        break;
                    case 'array':
                        $colonDividedValue = implode(', ', $value);
                        $filterQuery .= " $id IN ($colonDividedValue) ";
                        break;
                    default:
                        if ($value === -1) {
                            $filterQuery .= " $id IS NOT NULL ";
                        } else {
                            $filterQuery .= " $id = $value ";
                        }
                }
            }
        }

        if ($filterQuery != '') {
            $encuestas->whereRaw($filterQuery);
        }

        $page = $params['page'];
        $pageSize = $params['pageSize'];

        $startIndex = $page * $pageSize;

        $total = $encuestas->count();
        $encuestas = $encuestas
            ->offset($startIndex)
            ->take($pageSize)
            ->orderby('e.id', 'desc')
            ->get();

        $filtro_usuario = VNegociosFiltros::where('idUser', '=', $user->id)
            ->where('api', '=', 'getEncuestas')
            ->first();
        if ($filtro_usuario) {
            $filtro_usuario->parameters = $parameters_serializado;
            $filtro_usuario->updated_at = time::now();
            $filtro_usuario->update();
        } else {
            $objeto_nuevo = new VNegociosFiltros();
            $objeto_nuevo->api = 'getEncuestas';
            $objeto_nuevo->idUser = $user->id;
            $objeto_nuevo->parameters = $parameters_serializado;
            $objeto_nuevo->save();
        }

        if ($total == 0) {
            $response = [
                'success' => true,
                'results' => true,
                'total' => $total,
                'data' => [],
                'filtros' => $params['filtered'],
            ];
            return response()->json($response, 200);
        } else {
            $response = [
                'success' => true,
                'results' => true,
                'total' => $total,
                'data' => $encuestas,
                'filtros' => $params['filtered'],
            ];
            return response()->json($response, 200);
        }
    }

    function getBeneficiarios(Request $request)
    {
        $parameters = $request->all();
        $user = auth()->user();
        try {
            $res = DB::table('vales as v')
                ->select(
                    'v.id',
                    'v.Nombre',
                    'v.Paterno',
                    'v.Materno',
                    'v.CURP',
                    'v.idMunicipio',
                    'm.Nombre AS Municipio',
                    'v.idLocalidad',
                    'l.Nombre AS Localidad',
                    'v.Colonia',
                    'v.TelCelular',
                    'v.TelFijo'
                )
                ->JOIN('et_cat_municipio as m', 'v.idMunicipio', 'm.Id')
                ->JOIN('et_cat_localidad_2022 as l', 'l.id', 'v.idLocalidad')
                ->where('v.Devuelto', 0)
                ->where('v.Ejercicio', 2023)
                ->OrderBy('v.Nombre')
                ->OrderBy('v.Paterno')
                ->OrderBy('v.CURP');
            //Filtro para mostrar solo registros que pertenecen a la misma remesa que el usuario
            //! Si se necesita que vean todo el estado quitar este fragmento
            //!-----------------------------------------------------------------------------------------//
            // $permisos = DB::table('users_menus')
            //     ->where(['idUser' => $user->id, 'idMenu' => '39'])
            //     ->get()
            //     ->first();

            // if ($permisos !== null) {
            //     $viewall = $permisos->ViewAll;
            //     if ($viewall < 1) {
            //         $region = DB::table('users_region')
            //             ->selectRaw('Region')
            //             ->where(['idUser' => $user->id])
            //             ->first();
            //         if ($region === null) {
            //             $response = [
            //                 'success' => true,
            //                 'results' => false,
            //                 'errors' => 'No tiene region asignada',
            //                 'message' => 'No tiene region asignada',
            //             ];
            //             return response()->json($response, 200);
            //         }
            //         $res->where('m.SubRegion', $region->Region);
            //     }
            // }
            //!-----------------------------------------------------------------------------------------//

            $flag = 0;
            $page = $parameters['page'];
            $pageSize = $parameters['pageSize'];
            $startIndex = $page * $pageSize;

            if (isset($parameters['NombreCompleto'])) {
                $filtro_recibido = $parameters['NombreCompleto'];
                $filtro_recibido = str_replace(' ', '%', $filtro_recibido);
                $res->where(
                    DB::raw("
                    REPLACE(
                    CONCAT(
                        v.Nombre,
                        v.Paterno,
                        v.Materno
                    ), ' ', '')"),
                    'like',
                    '%' . $filtro_recibido . '%'
                );
            }

            if (isset($parameters['CURP'])) {
                $filtro_recibido = $parameters['CURP'];
                $filtro_recibido = str_replace(' ', '%', $filtro_recibido);
                $res = $res->WhereRaw(
                    'v.CURP LIKE "%' . $filtro_recibido . '%"'
                );
            }
            // dd(str_replace_array('?', $res->getBindings(), $res->toSql()));
            $res = $res
                ->offset($startIndex)
                ->take($pageSize)
                ->get();

            return [
                'success' => true,
                'results' => true,
                'total' => 0,
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
                'errors' => $e,
                'message' => 'Campo de consulta incorrecto',
            ];

            return response()->json($response, 200);
        }
    }

    public function create(Request $request)
    {
        $v = Validator::make(
            $request->all(),
            [
                'CURP' => 'required|size:18',
                'Nombre' => 'required|between:3,150',
                'Paterno' => 'required|between:3,150',
                'Celular' => 'max:10',
                'Telefono' => 'max:13',
                'idTipoApoyo' => 'required',
                'TipoEncuesta' => 'required',
            ],
            $messages = [
                'size' =>
                    'El campo :attribute debe ser una cadena de :size caractares.',
                'required' => 'El campo :attribute es obligatorio',
                'between' =>
                    'El campo :attribute debe tener una longitud de entre :min y :max caracteres.',
                'date_format' =>
                    'El formato del campo :attribute es incorrecto debe enviarse como: aaaa-m-d',
                'max' =>
                    'El campo :attribute de tener una longitud máxima de :max caracteres.',
            ]
        );

        if ($v->fails()) {
            $er = '';
            $errores = $v->errors()->all();
            foreach ($errores as $e) {
                $er = $er . $e . " \n";
            }

            $response = [
                'success' => true,
                'results' => false,
                'message' => $er,
            ];
            return response()->json($response, 200);
        }

        $params = $request->all();
        $user = auth()->user();
        if ($params['TipoEncuesta'] == 'E') {
            $isRegistered = $this->isRegistered(
                $params['CURP'],
                $params['idTipoApoyo']
            );
            if ($isRegistered) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'message' =>
                        'El beneficiario ya fue encuestado para este programa',
                    'data' => $isRegistered,
                ];
                return response()->json($response, 200);
            } else {
                $newRecord = [
                    'idTipoApoyo' => $params['idTipoApoyo'],
                    'CURP' => $params['CURP'],
                    'Nombre' => $params['Nombre'],
                    'Paterno' => $params['Paterno'],
                    'Materno' => isset($params['Materno'])
                        ? $params['Materno']
                        : null,
                    'Celular' => isset($params['Celular'])
                        ? $params['Celular']
                        : null,
                    'Telefono' => isset($params['Telefono'])
                        ? $params['Telefono']
                        : null,
                    'idMunicipio' => $params['idMunicipio'],
                    'idLocalidad' => $params['idLocalidad'],
                    'Colonia' => isset($params['Colonia'])
                        ? $params['Colonia']
                        : null,
                    'Facebook' => isset($params['Facebook'])
                        ? $params['Facebook']
                        : null,
                    'Entrada' => 1,
                    'Autoriza' => $params['Autoriza'],
                    'idUsuarioCreo' => $user->id,
                    'FechaCreo' => date('Y-m-d H:i:s'),
                    'idCGCSI' => isset($params['idCGCSI'])
                        ? $params['idCGCSI']
                        : null,
                    'Referencia' => isset($params['Ubicacion'])
                        ? $params['Ubicacion']
                        : null,
                    'Observaciones' => isset($params['Observaciones'])
                        ? $params['Observaciones']
                        : null,
                ];
                DB::beginTransaction();
                $idEncuesta = DB::table('encuestas')->insertGetId($newRecord);
                DB::commit();

                $questions = DB::Table('cat_preguntas_encuestas')
                    ->Select('id')
                    ->Where('Activa', 1);

                if ($params['TipoEncuesta'] == 'E') {
                    $questions->WhereRaw('id NOT IN (8,14)');
                }

                if ($params['q12'] == 0) {
                    $questions->WhereRaw('id NOT IN (13)');
                }

                $questions = $questions->get();

                $responses = [];
                foreach ($questions as $q) {
                    if ($q->id == 3 || $q->id == 17) {
                        $responsesText[] = [
                            'idEncuesta' => $idEncuesta,
                            'idPregunta' => $q->id,
                            'TipoEncuesta' => $params['TipoEncuesta'],
                            'RespuestaText' => $params['q' . $q->id],
                        ];
                    } else {
                        if ($params['q' . $q->id] == 'S') {
                            $resp = 1;
                        } elseif ($params['q' . $q->id] == 'N') {
                            $resp = 0;
                        } else {
                            $resp = $params['q' . $q->id];
                        }
                        $responses[] = [
                            'idEncuesta' => $idEncuesta,
                            'idPregunta' => $q->id,
                            'TipoEncuesta' => $params['TipoEncuesta'],
                            'RespuestaInt' => $resp,
                        ];
                    }
                }

                DB::Table('respuestas_encuestas')->insert($responses);
                DB::Table('respuestas_encuestas')->insert($responsesText);
                $folioImpulso = str_pad(
                    dechex($idEncuesta),
                    6,
                    '0',
                    STR_PAD_LEFT
                );

                $response = [
                    'success' => true,
                    'results' => true,
                    'message' => 'Solicitud registada con éxito',
                    'folio' => $folioImpulso,
                ];
                return response()->json($response, 200);
            }
        } else {
            DB::beginTransaction();
            DB::table('encuestas')
                ->Where('id', $params['id'])
                ->update(['Salida' => 1]);
            DB::commit();

            $questions = DB::Table('cat_preguntas_encuestas')
                ->Select('id')
                ->Where('Activa', 1);

            if ($params['q12'] == 0) {
                $questions->WhereRaw('id NOT IN (13)');
            }

            $questions = $questions->get();

            $responses = [];
            foreach ($questions as $q) {
                if ($q->id == 3 || $q->id == 17) {
                    $responsesText[] = [
                        'idEncuesta' => $params['id'],
                        'idPregunta' => $q->id,
                        'TipoEncuesta' => $params['TipoEncuesta'],
                        'RespuestaText' => $params['q' . $q->id],
                    ];
                } else {
                    if ($params['q' . $q->id] == 'S') {
                        $resp = 1;
                    } elseif ($params['q' . $q->id] == 'N') {
                        $resp = 0;
                    } else {
                        $resp = $params['q' . $q->id];
                    }
                    $responses[] = [
                        'idEncuesta' => $params['id'],
                        'idPregunta' => $q->id,
                        'TipoEncuesta' => $params['TipoEncuesta'],
                        'RespuestaInt' => $resp,
                    ];
                }
            }

            DB::Table('respuestas_encuestas')->insert($responses);
            DB::Table('respuestas_encuestas')->insert($responsesText);
            $response = [
                'success' => true,
                'results' => true,
                'message' => 'Solicitud registada con éxito',
            ];
            return response()->json($response, 200);
        }
    }

    public function createTCS(Request $request)
    {
        $v = Validator::make(
            $request->all(),
            [
                'CURP' => 'required|size:18',
                'Nombre' => 'required|between:3,150',
                'Paterno' => 'required|between:3,150',                
                'idTipoApoyo' => 'required',
                'TipoEncuesta' => 'required',
            ],
            $messages = [
                'size' =>
                    'El campo :attribute debe ser una cadena de :size caractares.',
                'required' => 'El campo :attribute es obligatorio',
                'between' =>
                    'El campo :attribute debe tener una longitud de entre :min y :max caracteres.',
                'date_format' =>
                    'El formato del campo :attribute es incorrecto debe enviarse como: aaaa-m-d',
                'max' =>
                    'El campo :attribute de tener una longitud máxima de :max caracteres.',
            ]
        );

        if ($v->fails()) {
            $er = '';
            $errores = $v->errors()->all();
            foreach ($errores as $e) {
                $er = $er . $e . " \n";
            }

            $response = [
                'success' => true,
                'results' => false,
                'message' => $er,
            ];
            // return response()->json($response, 200);
        }
        

        $params = $request->all();
        $user = auth()->user();
                
                $newRecord = [
                    'idTipoApoyo' => $params['idTipoApoyo'],
                    'CURP' => $params['CURP'],
                    'Nombre' => $params['Nombre'],
                    'Paterno' => $params['Paterno'],
                    'Materno' => isset($params['Materno'])
                        ? $params['Materno']
                        : null,                    
                    'Municipio' => $params['Municipio'],
                    'idMunicipio'=>$params['idMunicipio']?$params['idMunicipio']:null,
                    'idLocalidad' => $params['idLocalidad']?$params['idLocalidad']:null,
                    'Localidad' => $params['Localidad'],
                    'Colonia' => isset($params['Colonia'])
                        ? $params['Colonia']
                        : null,
                    'Calle' => isset($params['Calle'])
                        ? $params['Calle']
                        : null,
                    'NumExt' => isset($params['NumExt'])
                        ? $params['NumExt']
                        : null,
                    'CP' => isset($params['CP'])
                        ? $params['CP']
                        : null,
                    'Celular' => isset($params['Celular'])
                        ? $params['Celular']
                        : null,
                    'Correo' => isset($params['Correo'])
                        ? $params['Correo']
                        : null,
                    'Facebook' => isset($params['Facebook'])
                        ? $params['Facebook']
                        : null,
                    'Entrada' => 1,
                    'idUsuarioCreo' => 1,
                    'FechaCreo' => date('Y-m-d H:i:s'),
                    'idCGCSI' => null,
                    'Referencia' =>  null,
                    'Observaciones' => isset($params['Observaciones'])
                        ? $params['Observaciones']
                        : null,
                    'idTipoEncuesta'=>2
                ];
                DB::beginTransaction();
                $idEncuesta = DB::table('encuestas')->insertGetId($newRecord);
                DB::commit();

                $questions = DB::Table('cat_preguntas_encuestas')
                    ->Select('id')
                    ->Where('Activa', 1);                    

                $questions = $questions->get();
                $responses = [];
                if(isset($params['q22'])){
                    
                    $q22 = $params['q22'];
                    
                    foreach($q22 as $q => $respuesta){                        
                        if($q == true || \strlen($q)>0){
                            $responses[] = [
                                'idEncuesta' => $idEncuesta,
                                'Respuesta' => $respuesta,
                                'cveOpcion' => $q
                            ];
                        }
                        
                    }
                    if(count($responses)>0){
                        DB::Table('cat_respuestas_q22')->insert($responses);
                    }                    
                }
                
                unset($params['q22']);
                $responses = [];
                foreach ($questions as $q) {
                    if (in_array($q->id,[20,23,24])) {
                        $responsesText[] = [
                            'idEncuesta' => $idEncuesta,
                            'idPregunta' => $q->id,
                            'TipoEncuesta' => 'E',
                            'RespuestaText' => $params['q' . $q->id],
                        ];
                    } else {
                        if ($params['q' . $q->id] == 'S') {
                            $resp = 1;
                        } elseif ($params['q' . $q->id] == 'N') {
                            $resp = 0;
                        } else {
                            $resp = $params['q' . $q->id];
                        }
                        $responses[] = [
                            'idEncuesta' => $idEncuesta,
                            'idPregunta' => $q->id,
                            'TipoEncuesta' => $params['TipoEncuesta'],
                            'RespuestaInt' => $resp,
                        ];
                    }
                }

                

                DB::Table('respuestas_encuestas')->insert($responses);
                DB::Table('respuestas_encuestas')->insert($responsesText);
                $folioImpulso = str_pad(
                    dechex($idEncuesta),
                    6,
                    '0',
                    STR_PAD_LEFT
                );

                $response = [
                    'success' => true,
                    'results' => true,
                    'message' => 'Solicitud registada con éxito',
                    'folio' => $folioImpulso,
                ];
                return response()->json($response, 200);        
    }

    public function isRegistered($curp, $tipo)
    {
        try {
            $res = DB::table('encuestas as e')
                ->select('e.id', DB::RAW('LPAD(HEX(e.id),6,0) AS Folio'))
                ->whereNull('e.FechaElimino')
                ->Where(['e.CURP' => $curp, 'idTipoApoyo' => $tipo])
                ->first();

            if ($res) {
                return $res;
            } else {
                return null;
            }
        } catch (QueryException $errors) {
            return null;
        }
    }

    protected function getResponses(Request $request)
    {
        $params = $request->all();
        $resp = DB::table('respuestas_encuestas')
            ->Select('idPregunta', 'RespuestaInt', 'RespuestaText')
            ->Where([
                'idEncuesta' => $params['id'],
                'TipoEncuesta' => $params['TipoEncuesta'],
            ])
            ->get();

        $respuestas = [];
        foreach ($resp as $r) {
            $key = 'q' . $r->idPregunta;
            if ($r->idPregunta == 3 || $r->idPregunta == 17) {
                $value = 'RespuestaText';
            } else {
                $value = 'RespuestaInt';
            }
            $respuestas[$key] = $r->$value;
        }

        $response = [
            'success' => true,
            'results' => true,
            'responses' => $respuestas,
        ];
        return response()->json($response, 200);
    }

    function delete(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->toUser();
            $v = Validator::make($request->all(), [
                'id' => 'required',
            ]);

            if ($v->fails()) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' => 'La encuesta a borrar no fue enviada',
                ];
                return response()->json($response, 200);
            }

            $params = $request->only(['id']);

            DB::table('encuestas')
                ->where('id', $params['id'])
                ->update([
                    'FechaElimino' => date('Y-m-d H:i:s'),
                    'idUsuarioElimino' => $user->id,
                ]);

            $response = [
                'success' => true,
                'results' => true,
                'message' => 'Solicitud eliminada con éxito',
                'data' => [],
            ];

            return response()->json($response, 200);
        } catch (\Throwable $errors) {
            $response = [
                'success' => false,
                'results' => false,
                'errors' => $errors,
                'message' => 'Ha ocurrido un error, consulte al administrador',
            ];

            return response()->json($response, 200);
        }
    }

    public function getReporteEncuestas(Request $request)
    {
        $params = $request->all();
        $user = auth()->user();
        $permisos = $this->getPermisos($user->id);

        if (!$permisos) {
            $response = [
                'success' => true,
                'results' => false,
                'total' => 0,
                'message' =>
                    'No tiene permisos para ver la información, contacte al administrador',
            ];
            return response()->json($response, 200);
        }
        $encuestas = DB::table('encuestas AS e')
            ->Select(
                'e.id',
                DB::Raw('LPAD(HEX(e.id),6,0) AS Folio'),
                'a.Apoyo',
                'e.FechaCreo',
                DB::RAW(
                    'CONCAT_WS(" ",e.Nombre,e.Paterno,e.Materno) AS Nombre'
                ),
                'e.CURP',
                'm.SubRegion AS Region',
                'm.Nombre AS Municipio',
                'l.Nombre AS Localidad',
                'e.Colonia',
                'e.Telefono',
                'e.Celular',
                'e.Facebook',
                DB::RAW(
                    'CASE WHEN e.idCGCSI = -1  THEN e.Referencia ELSE centros.Nombre END AS Ubicacion'
                ),
                DB::RAW(
                    'CASE WHEN e.Autoriza = 1 THEN "SI" ELSE "NO" END AS Autoriza'
                ),
                DB::Raw(
                    'CONCAT_WS(" ",u.Nombre,u.Paterno,u.Materno) AS CreadoPor'
                )
            )
            ->Join('et_cat_municipio AS m', 'm.Id', 'e.idMunicipio')
            ->Join('et_cat_localidad_2022 AS l', 'l.id', 'e.idLocalidad')
            ->Join('cat_apoyos AS a', 'a.id', 'e.idTipoApoyo')
            ->Join('users AS u', 'u.id', 'e.idUsuarioCreo')
            ->LeftJoin('cat_CGCSI AS centros', 'centros.id', 'e.idCGCSI')
            ->WhereNull('e.FechaElimino');

        $filterQuery = '';
        $filtro_usuario = VNegociosFiltros::where('idUser', '=', $user->id)
            ->where('api', '=', 'getEncuestas')
            ->first();

        if ($filtro_usuario) {
            $hoy = date('Y-m-d H:i:s');
            $intervalo = $filtro_usuario->updated_at->diff($hoy);
            if ($intervalo->h === 0) {
                //Si es 0 es porque no ha pasado una hora.
                $filter = unserialize($filtro_usuario->parameters);

                if (
                    isset($filter['filtered']) &&
                    count($filter['filtered']) > 0
                ) {
                    foreach ($filter['filtered'] as $filtro) {
                        if ($filterQuery != '') {
                            $filterQuery .= ' AND ';
                        }
                        $id = $filtro['id'];
                        $value = $filtro['value'];

                        if ($id == '.id') {
                            $value = hexdec($value);
                        }

                        if ($id == 'region') {
                            $municipios = DB::table('et_cat_municipio')
                                ->select('Id')
                                ->whereIN('SubRegion', $value)
                                ->get();
                            foreach ($municipios as $m) {
                                $municipioRegion[] = $m->Id;
                            }

                            $id = '.idMunicipio';
                            $value = $municipioRegion;
                        }

                        $id = 'e' . $id;

                        switch (gettype($value)) {
                            case 'string':
                                $filterQuery .= " $id LIKE '%$value%' ";
                                break;
                            case 'array':
                                $colonDividedValue = implode(', ', $value);
                                $filterQuery .= " $id IN ($colonDividedValue) ";
                                break;
                            default:
                                if ($value === -1) {
                                    $filterQuery .= " $id IS NOT NULL ";
                                } else {
                                    $filterQuery .= " $id = $value ";
                                }
                        }
                    }
                }
            }
        }

        if ($filterQuery != '') {
            $encuestas->whereRaw($filterQuery);
        }

        if ($permisos->ViewAll == 0) {
            $encuestas = $encuestas->where('e.idUsuarioCreo', $user->id);
        }

        $data = $encuestas->get();

        if (count($data) == 0) {
            $file = public_path() . '/archivos/formatoReporteEncuestas.xlsx';

            return response()->download(
                $file,
                'ValesDevueltos' . date('Y-m-d') . '.xlsx'
            );
        }
        //Mapeamos el resultado como un array
        $res = $data
            ->map(function ($x) {
                $x = is_object($x) ? (array) $x : $x;
                return $x;
            })
            ->toArray();

        $registros = [];

        foreach ($res as $registro) {
            $respuestasEntrada = DB::table('encuestas AS e')
                ->Select(
                    'e1.RespuestaInt AS e1r',
                    's1.RespuestaInt AS s1r',
                    'e2.RespuestaInt AS e2r',
                    's2.RespuestaInt AS s2r',
                    'e3.RespuestaText AS e3r',
                    's3.RespuestaText AS s3r',
                    'e4.RespuestaInt AS e4r',
                    's4.RespuestaInt AS s4r',
                    'e5.RespuestaInt AS e5r',
                    's5.RespuestaInt AS s5r',
                    'e6.RespuestaInt AS e6r',
                    's6.RespuestaInt AS s6r',
                    'e7.RespuestaInt AS e7r',
                    's7.RespuestaInt AS s7r',
                    's8.RespuestaInt AS s8r',
                    'e9.RespuestaInt AS e9r',
                    's9.RespuestaInt AS s9r',
                    'e10.RespuestaInt AS e10r',
                    's10.RespuestaInt AS s10r',
                    'e11.RespuestaInt AS e11r',
                    's11.RespuestaInt AS s11r',
                    DB::RAW(
                        'CASE WHEN e12.RespuestaInt = 1 THEN "SI" ELSE "NO" END AS e12r'
                    ),
                    DB::RAW(
                        'CASE WHEN s12.RespuestaInt = 1 THEN "SI" ELSE "NO" END AS s12r'
                    ),
                    'e13.RespuestaInt AS e13r',
                    's13.RespuestaInt AS s13r',
                    's14.RespuestaInt AS s14r',
                    'e17.RespuestaText AS e17r',
                    's17.RespuestaText AS s17r'
                )
                ->LeftJoin(
                    DB::RAW(
                        '(SELECT idEncuesta,RespuestaInt FROM respuestas_encuestas WHERE TipoEncuesta = "E" AND idPregunta = 1 AND idEncuesta = ' .
                            $registro['id'] .
                            ') AS e1'
                    ),
                    'e1.idEncuesta',
                    'e.id'
                )
                ->LeftJoin(
                    DB::RAW(
                        '(SELECT idEncuesta,RespuestaInt FROM respuestas_encuestas WHERE TipoEncuesta = "S" AND idPregunta = 1 AND idEncuesta = ' .
                            $registro['id'] .
                            ') AS s1'
                    ),
                    's1.idEncuesta',
                    'e.id'
                )
                ->LeftJoin(
                    DB::RAW(
                        '(SELECT idEncuesta,RespuestaInt FROM respuestas_encuestas WHERE TipoEncuesta = "E" AND idPregunta = 2 AND idEncuesta = ' .
                            $registro['id'] .
                            ') AS e2'
                    ),
                    'e2.idEncuesta',
                    'e.id'
                )
                ->LeftJoin(
                    DB::RAW(
                        '(SELECT idEncuesta,RespuestaInt FROM respuestas_encuestas WHERE TipoEncuesta = "S" AND idPregunta = 2 AND idEncuesta = ' .
                            $registro['id'] .
                            ') AS s2'
                    ),
                    's2.idEncuesta',
                    'e.id'
                )
                ->LeftJoin(
                    DB::RAW(
                        '(SELECT idEncuesta,RespuestaText FROM respuestas_encuestas WHERE TipoEncuesta = "E" AND idPregunta = 3 AND idEncuesta = ' .
                            $registro['id'] .
                            ') AS e3'
                    ),
                    'e3.idEncuesta',
                    'e.id'
                )
                ->LeftJoin(
                    DB::RAW(
                        '(SELECT idEncuesta,RespuestaText FROM respuestas_encuestas WHERE TipoEncuesta = "S" AND idPregunta = 3 AND idEncuesta = ' .
                            $registro['id'] .
                            ') AS s3'
                    ),
                    's3.idEncuesta',
                    'e.id'
                )
                ->LeftJoin(
                    DB::RAW(
                        '(SELECT idEncuesta,RespuestaInt FROM respuestas_encuestas WHERE TipoEncuesta = "E" AND idPregunta = 4 AND idEncuesta = ' .
                            $registro['id'] .
                            ') AS e4'
                    ),
                    'e4.idEncuesta',
                    'e.id'
                )
                ->LeftJoin(
                    DB::RAW(
                        '(SELECT idEncuesta,RespuestaInt FROM respuestas_encuestas WHERE TipoEncuesta = "S" AND idPregunta = 4 AND idEncuesta = ' .
                            $registro['id'] .
                            ') AS s4'
                    ),
                    's4.idEncuesta',
                    'e.id'
                )
                ->LeftJoin(
                    DB::RAW(
                        '(SELECT idEncuesta,RespuestaInt FROM respuestas_encuestas WHERE TipoEncuesta = "E" AND idPregunta = 5 AND idEncuesta = ' .
                            $registro['id'] .
                            ') AS e5'
                    ),
                    'e5.idEncuesta',
                    'e.id'
                )
                ->LeftJoin(
                    DB::RAW(
                        '(SELECT idEncuesta,RespuestaInt FROM respuestas_encuestas WHERE TipoEncuesta = "S" AND idPregunta = 5 AND idEncuesta = ' .
                            $registro['id'] .
                            ') AS s5'
                    ),
                    's5.idEncuesta',
                    'e.id'
                )
                ->LeftJoin(
                    DB::RAW(
                        '(SELECT idEncuesta,RespuestaInt FROM respuestas_encuestas WHERE TipoEncuesta = "E" AND idPregunta = 6 AND idEncuesta = ' .
                            $registro['id'] .
                            ') AS e6'
                    ),
                    'e6.idEncuesta',
                    'e.id'
                )
                ->LeftJoin(
                    DB::RAW(
                        '(SELECT idEncuesta,RespuestaInt FROM respuestas_encuestas WHERE TipoEncuesta = "S" AND idPregunta = 6 AND idEncuesta = ' .
                            $registro['id'] .
                            ') AS s6'
                    ),
                    's6.idEncuesta',
                    'e.id'
                )
                ->LeftJoin(
                    DB::RAW(
                        '(SELECT idEncuesta,RespuestaInt FROM respuestas_encuestas WHERE TipoEncuesta = "E" AND idPregunta = 7 AND idEncuesta = ' .
                            $registro['id'] .
                            ') AS e7'
                    ),
                    'e7.idEncuesta',
                    'e.id'
                )
                ->LeftJoin(
                    DB::RAW(
                        '(SELECT idEncuesta,RespuestaInt FROM respuestas_encuestas WHERE TipoEncuesta = "S" AND idPregunta = 7 AND idEncuesta = ' .
                            $registro['id'] .
                            ') AS s7'
                    ),
                    's7.idEncuesta',
                    'e.id'
                )
                ->LeftJoin(
                    DB::RAW(
                        '(SELECT idEncuesta,RespuestaInt FROM respuestas_encuestas WHERE TipoEncuesta = "S" AND idPregunta = 8 AND idEncuesta = ' .
                            $registro['id'] .
                            ') AS s8'
                    ),
                    's8.idEncuesta',
                    'e.id'
                )
                ->LeftJoin(
                    DB::RAW(
                        '(SELECT idEncuesta,RespuestaInt FROM respuestas_encuestas WHERE TipoEncuesta = "E" AND idPregunta = 9 AND idEncuesta = ' .
                            $registro['id'] .
                            ') AS e9'
                    ),
                    'e9.idEncuesta',
                    'e.id'
                )
                ->LeftJoin(
                    DB::RAW(
                        '(SELECT idEncuesta,RespuestaInt FROM respuestas_encuestas WHERE TipoEncuesta = "S" AND idPregunta = 9 AND idEncuesta = ' .
                            $registro['id'] .
                            ') AS s9'
                    ),
                    's9.idEncuesta',
                    'e.id'
                )
                ->LeftJoin(
                    DB::RAW(
                        '(SELECT idEncuesta,RespuestaInt FROM respuestas_encuestas WHERE TipoEncuesta = "E" AND idPregunta = 10 AND idEncuesta = ' .
                            $registro['id'] .
                            ') AS e10'
                    ),
                    'e10.idEncuesta',
                    'e.id'
                )
                ->LeftJoin(
                    DB::RAW(
                        '(SELECT idEncuesta,RespuestaInt FROM respuestas_encuestas WHERE TipoEncuesta = "S" AND idPregunta = 10 AND idEncuesta = ' .
                            $registro['id'] .
                            ') AS s10'
                    ),
                    's10.idEncuesta',
                    'e.id'
                )
                ->LeftJoin(
                    DB::RAW(
                        '(SELECT idEncuesta,RespuestaInt FROM respuestas_encuestas WHERE TipoEncuesta = "E" AND idPregunta = 11 AND idEncuesta = ' .
                            $registro['id'] .
                            ') AS e11'
                    ),
                    'e11.idEncuesta',
                    'e.id'
                )
                ->LeftJoin(
                    DB::RAW(
                        '(SELECT idEncuesta,RespuestaInt FROM respuestas_encuestas WHERE TipoEncuesta = "S" AND idPregunta = 11 AND idEncuesta = ' .
                            $registro['id'] .
                            ') AS s11'
                    ),
                    's11.idEncuesta',
                    'e.id'
                )
                ->LeftJoin(
                    DB::RAW(
                        '(SELECT idEncuesta,RespuestaInt FROM respuestas_encuestas WHERE TipoEncuesta = "E" AND idPregunta = 12 AND idEncuesta = ' .
                            $registro['id'] .
                            ') AS e12'
                    ),
                    'e12.idEncuesta',
                    'e.id'
                )
                ->LeftJoin(
                    DB::RAW(
                        '(SELECT idEncuesta,RespuestaInt FROM respuestas_encuestas WHERE TipoEncuesta = "S" AND idPregunta = 12 AND idEncuesta = ' .
                            $registro['id'] .
                            ') AS s12'
                    ),
                    's12.idEncuesta',
                    'e.id'
                )
                ->LeftJoin(
                    DB::RAW(
                        '(SELECT idEncuesta,RespuestaInt FROM respuestas_encuestas WHERE TipoEncuesta = "E" AND idPregunta = 13 AND idEncuesta = ' .
                            $registro['id'] .
                            ') AS e13'
                    ),
                    'e13.idEncuesta',
                    'e.id'
                )
                ->LeftJoin(
                    DB::RAW(
                        '(SELECT idEncuesta,RespuestaInt FROM respuestas_encuestas WHERE TipoEncuesta = "S" AND idPregunta = 13 AND idEncuesta = ' .
                            $registro['id'] .
                            ') AS s13'
                    ),
                    's13.idEncuesta',
                    'e.id'
                )
                ->LeftJoin(
                    DB::RAW(
                        '(SELECT idEncuesta,RespuestaInt FROM respuestas_encuestas WHERE TipoEncuesta = "S" AND idPregunta = 14 AND idEncuesta = ' .
                            $registro['id'] .
                            ') AS s14'
                    ),
                    's14.idEncuesta',
                    'e.id'
                )
                ->LeftJoin(
                    DB::RAW(
                        '(SELECT idEncuesta,RespuestaText FROM respuestas_encuestas WHERE TipoEncuesta = "E" AND idPregunta = 17 AND idEncuesta = ' .
                            $registro['id'] .
                            ') AS e17'
                    ),
                    'e17.idEncuesta',
                    'e.id'
                )
                ->LeftJoin(
                    DB::RAW(
                        '(SELECT idEncuesta,RespuestaText FROM respuestas_encuestas WHERE TipoEncuesta = "S" AND idPregunta = 17 AND idEncuesta = ' .
                            $registro['id'] .
                            ') AS s17'
                    ),
                    's17.idEncuesta',
                    'e.id'
                )
                ->Where('e.id', $registro['id'])
                ->first();

            $registros[] = [
                'Folio' => $registro['Folio'],
                'Apoyo' => $registro['Apoyo'],
                'Region' => $registro['Region'],
                'FechaCreo' => $registro['FechaCreo'],
                'CURP' => $registro['CURP'],
                'Nombre' => $registro['Nombre'],
                'Municipio' => $registro['Municipio'],
                'Localidad' => $registro['Localidad'],
                'Colonia' => $registro['Colonia'],
                'Telefono' => $registro['Telefono'],
                'Celular' => $registro['Celular'],
                'Facebook' => $registro['Facebook'],
                'Autoriza' => $registro['Autoriza'],
                'Ubicacion' => $registro['Ubicacion'],
                'CreadoPor' => $registro['CreadoPor'],
                'e1r' => $respuestasEntrada->e1r,
                's1r' => $respuestasEntrada->s1r,
                'e2r' => $respuestasEntrada->e2r,
                's2r' => $respuestasEntrada->s2r,
                'e3r' => $respuestasEntrada->e3r,
                's3r' => $respuestasEntrada->s3r,
                'e4r' => $respuestasEntrada->e4r,
                's4r' => $respuestasEntrada->s4r,
                'e5r' => $this->getResponseFormatConsideration(
                    $respuestasEntrada->e5r
                ),
                's5r' => $this->getResponseFormatConsideration(
                    $respuestasEntrada->s5r
                ),
                'e6r' => $this->getResponseFormatConsideration(
                    $respuestasEntrada->e6r
                ),
                's6r' => $this->getResponseFormatConsideration(
                    $respuestasEntrada->s6r
                ),
                'e7r' => $this->getResponseFormatConsideration(
                    $respuestasEntrada->e7r
                ),
                's7r' => $this->getResponseFormatConsideration(
                    $respuestasEntrada->s7r
                ),
                's8r' => $this->getResponseFormatBeneffits(
                    $respuestasEntrada->s8r
                ),
                'e9r' => $this->getResponseFormatAttention(
                    $respuestasEntrada->e9r
                ),
                's9r' => $this->getResponseFormatAttention(
                    $respuestasEntrada->s9r
                ),
                'e10r' => $respuestasEntrada->e10r,
                's10r' => $respuestasEntrada->s10r,
                'e11r' => $respuestasEntrada->e11r,
                's11r' => $respuestasEntrada->s11r,
                'e12r' => $respuestasEntrada->e12r,
                's12r' => $respuestasEntrada->s12r,
                'e13r' => $this->getResponseFormatConsideration(
                    $respuestasEntrada->e13r
                ),
                's13r' => $this->getResponseFormatConsideration(
                    $respuestasEntrada->s13r
                ),
                's14r' => $this->getResponseFormatConsiderationP(
                    $respuestasEntrada->s14r
                ),
                'e17r' => $respuestasEntrada->e17r,
                's17r' => $respuestasEntrada->s17r,
            ];
        }
        unset($res);
        $reader = IOFactory::createReader('Xlsx');

        $spreadsheet = $reader->load(
            public_path() . '/archivos/formatoReporteEncuestas.xlsx'
        );

        $sheet = $spreadsheet->getActiveSheet();
        $largo = count($registros);
        $sheet
            ->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_LETTER);

        //Llenar excel con el resultado del query
        $sheet->fromArray($registros, null, 'B11');
        //Agregamos la fecha
        $sheet->setCellValue('C1', 'Fecha Reporte: ' . date('Y-m-d H:i:s'));

        $sheet->getDefaultRowDimension()->setRowHeight(-1);

        //guardamos el excel creado y luego lo obtenemos en $file para poder descargarlo
        $writer = new Xlsx($spreadsheet);
        $writer->save(
            'archivos/' .
                $user->id .
                '_' .
                $user->email .
                '_' .
                'Encuestas_2023.xlsx'
        );
        $file =
            public_path() .
            '/archivos/' .
            $user->id .
            '_' .
            $user->email .
            '_' .
            'Encuestas_2023.xlsx';
        $fecha = date('Y-m-d H-i-s');

        return response()->download(
            $file,
            'Encuestas_2023_' .
                date('Y-m-d') .
                '_' .
                str_replace(' ', '_', $fecha) .
                '.xlsx'
        );
    }

    protected function getResponseFormatConsideration($id)
    {
        $response = '';
        switch ($id) {
            case 1:
                $response = 'Mala';
                break;
            case 2:
                $response = 'Regular';
                break;
            case 3:
                $response = 'Buena';
                break;
            default:
                $response = '';
                break;
        }
        return $response;
    }

    protected function getResponseFormatAttention($id)
    {
        $response = '';
        switch ($id) {
            case 1:
                $response = 'Malos';
                break;
            case 2:
                $response = 'Regulares';
                break;
            case 3:
                $response = 'Buenos';
                break;
            default:
                $response = '';
                break;
        }
        return $response;
    }

    protected function getResponseFormatBeneffits($id)
    {
        $response = '';
        switch ($id) {
            case 1:
                $response = 'Menores al esperado';
                break;
            case 2:
                $response = 'Similares al esperado';
                break;
            case 3:
                $response = 'Mayores al esperado';
                break;
            default:
                $response = '';
                break;
        }
        return $response;
    }

    protected function getResponseFormatConsiderationP($id)
    {
        $response = '';
        switch ($id) {
            case 1:
                $response = 'Peor';
                break;
            case 2:
                $response = 'Similar';
                break;
            case 3:
                $response = 'Mejor';
                break;
            default:
                $response = '';
                break;
        }
        return $response;
    }

    protected function getEncuestasTCS (Request $request)
    {
        $user = auth()->user();
        $permisos = $this->getPermisos($user->id);

        if (!$permisos) {
            $response = [
                'success' => true,
                'results' => false,
                'total' => 0,
                'message' =>
                    'No tiene permisos para ver la información, contacte al administrador',
            ];
            return response()->json($response, 200);
        }

        $v = Validator::make(
            $request->all(),
            [
                'page' => 'required',
                'pageSize' => 'required',
            ],
            $messages = [
                'required' => 'El campo :attribute es obligatorio',
            ]
        );

        if ($v->fails()) {
            $response = [
                'success' => true,
                'results' => false,
                'total' => 0,
                'message' => $v->errors()->all(),
            ];
            return response()->json($response, 200);
        }

        $params = $request->all();
        $parameters_serializado = serialize($params);

        if (isset($params['filtered']) && count($params['filtered']) > 0) {
            foreach ($params['filtered'] as $filtro) {
                $value = $filtro['value'];

                if (!$this->validateInput($value)) {
                    $response = [
                        'success' => true,
                        'results' => false,
                        'total' => 0,
                        'message' =>
                            'Uno o más filtros utilizados no son válidos, intente nuevamente',
                    ];

                    return response()->json($response, 200);
                }
            }
        }

        $encuestas = DB::table('encuestas AS e')
            ->Select(
                'e.id',
                DB::Raw('LPAD(HEX(e.id),6,0) AS Folio'),
                'e.idTipoApoyo',
                'e.FechaCreo',
                'e.Nombre',
                'e.Paterno',
                'e.Materno',
                'e.CURP',
                // 'm.SubRegion AS Region',
                'e.idMunicipio',
                'e.Municipio',
                'e.idLocalidad',
                'e.Localidad',
                'e.Colonia',
                'e.Calle',
                'e.NumExt',
                'e.CP',
                'e.Correo',
                'e.Celular',
                'e.Facebook',
                'e.Entrada',
                'e.Observaciones'             
            )
            // ->Join('et_cat_municipio AS m', 'm.Id', 'e.idMunicipio')
            // ->Join('et_cat_localidad_2022 AS l', 'l.id', 'e.idLocalidad')
            // ->Join('users AS u', 'u.id', 'e.idUsuarioCreo')
            ->WhereNull('e.FechaElimino')
            ->Where('e.idTipoEncuesta', 2)
            ->Where('e.idTipoApoyo', 11);

        if ($permisos->ViewAll == 0) {
            $encuestas = $encuestas->where('e.idUsuarioCreo', $user->id);
        }

        // if ($permisos->ViewAll == 0 && $permisos->Seguimiento == 0) {
        //     $encuestas = $encuestas->where('e.idUsuarioCreo', $user->id);
        // } elseif ($permisos->ViewAll == 0) {
        //     $region = DB::table('users_region')
        //         ->selectRaw('Region')
        //         ->where('idUser', $user->id)
        //         ->first();

        //     if ($region === null) {
        //         $response = [
        //             'success' => true,
        //             'results' => false,
        //             'total' => 0,
        //             'message' => 'No tiene region asignada',
        //         ];

        //         return response()->json($response, 200);
        //     }

        //     $encuestas = $encuestas->where('m.Region', $region->Region);
        // }

        $filterQuery = '';
        $municipioRegion = [];

        if (isset($params['filtered']) && count($params['filtered']) > 0) {
            foreach ($params['filtered'] as $filtro) {
                if ($filterQuery != '') {
                    $filterQuery .= ' AND ';
                }
                $id = $filtro['id'];
                $value = $filtro['value'];

                if ($id == '.id') {
                    $value = hexdec($value);
                }

                if ($id == 'region') {
                    $municipios = DB::table('et_cat_municipio')
                        ->select('Id')
                        ->whereIN('SubRegion', $value)
                        ->get();
                    foreach ($municipios as $m) {
                        $municipioRegion[] = $m->Id;
                    }

                    $id = '.idMunicipio';
                    $value = $municipioRegion;
                }

                $id = 'e' . $id;

                switch (gettype($value)) {
                    case 'string':
                        $filterQuery .= " $id LIKE '%$value%' ";
                        break;
                    case 'array':
                        $colonDividedValue = implode(', ', $value);
                        $filterQuery .= " $id IN ($colonDividedValue) ";
                        break;
                    default:
                        if ($value === -1) {
                            $filterQuery .= " $id IS NOT NULL ";
                        } else {
                            $filterQuery .= " $id = $value ";
                        }
                }
            }
        }

        if ($filterQuery != '') {
            $encuestas->whereRaw($filterQuery);
        }

        $page = $params['page'];
        $pageSize = $params['pageSize'];

        $startIndex = $page * $pageSize;

        $total = $encuestas->count();
        $encuestas = $encuestas
            ->offset($startIndex)
            ->take($pageSize)
            ->orderby('e.id', 'desc')
            ->get();

        $filtro_usuario = VNegociosFiltros::where('idUser', '=', $user->id)
            ->where('api', '=', 'getEncuestas')
            ->first();
        if ($filtro_usuario) {
            $filtro_usuario->parameters = $parameters_serializado;
            $filtro_usuario->updated_at = time::now();
            $filtro_usuario->update();
        } else {
            $objeto_nuevo = new VNegociosFiltros();
            $objeto_nuevo->api = 'getEncuestas';
            $objeto_nuevo->idUser = $user->id;
            $objeto_nuevo->parameters = $parameters_serializado;
            $objeto_nuevo->save();
        }

        if ($total == 0) {
            $response = [
                'success' => true,
                'results' => true,
                'total' => $total,
                'data' => [],
                'filtros' => $params['filtered'],
            ];
            return response()->json($response, 200);
        } else {
            $response = [
                'success' => true,
                'results' => true,
                'total' => $total,
                'data' => $encuestas,
                'filtros' => $params['filtered'],
            ];
            return response()->json($response, 200);
        }
    }
}
