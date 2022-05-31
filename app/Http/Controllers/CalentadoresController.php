<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use DB;
use JWTAuth;
use Illuminate\Contracts\Validation\ValidationException;
use Validator;
use App\Cedula;
use Arr;
use Carbon\Carbon as time;
use GuzzleHttp\Client;
use HTTP_Request2;
use File;

class CalentadoresController extends Controller
{
    function getPermisos()
    {
        $user = auth()->user();

        $permisos = DB::table('users_menus')
            ->where(['idUser' => $user->id, 'idMenu' => '13'])
            ->get()
            ->first();
        return $permisos;
    }

    function getPrograma($folio)
    {
        if (!is_null($folio)) {
            $q = str_contains($folio, 'Q3450');
            if ($q) {
                return 1; //1 para vales
            } else {
                return 2;
            } //2 para calentadores
        } else {
            return 0;
        } //No existe folio
    }

    function getEstatusGlobal(Request $request)
    {
        $parameters = $request->all();
        $user = auth()->user();

        $permisos = $this->getPermisos();

        $seguimiento = $permisos->Seguimiento;
        $viewall = $permisos->ViewAll;
        $procedimiento = '';

        if ($viewall < 1 && $seguimiento < 1) {
            $usuarioApp = DB::table('users_aplicativo_web')
                ->select('UserName')
                ->where('idUser', $user->id)
                ->get()
                ->first();
            $procedimiento =
                "call getEstatusGlobalVentanillaCalentadores('" .
                $usuarioApp->UserName .
                "','" .
                $user->id .
                "')";
        } elseif ($viewall < 1) {
            $idUserOwner = DB::table('users_aplicativo_web')
                ->selectRaw('idUserOwner,Region')
                ->where('idUser', $user->id)
                ->get()
                ->first();
            $procedimiento =
                " call getEstatusGlobalVentanillaCalentadoresRegional('" .
                $idUserOwner->idUserOwner .
                "')";
        }

        if ($procedimiento === '') {
            $procedimiento =
                'call getEstatusGlobalVentanillaCalentadoresGeneral';
        }

        try {
            $res = DB::select($procedimiento);
            return ['success' => true, 'results' => true, 'data' => $res];
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

    function getSolicitudes(Request $request)
    {
        try {
            $v = Validator::make($request->all(), [
                'page' => 'required',
                'pageSize' => 'required',
                'programa' => 'required',
            ]);
            if ($v->fails()) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' => $v->errors(),
                ];
                return response()->json($response, 200);
            }

            $params = $request->all();
            $user = auth()->user();
            $tableSol = 'calentadores_solicitudes';
            $tableCedulas =
                '(SELECT * FROM calentadores_cedulas WHERE FechaElimino IS NULL) AS calentadores_cedulas';

            $permisos = $this->getPermisos();

            $seguimiento = $permisos->Seguimiento;
            $viewall = $permisos->ViewAll;
            $filtroCapturo = '';

            if ($viewall < 1 && $seguimiento < 1) {
                $usuarioApp = DB::table('users_aplicativo_web')
                    ->where('idUser', $user->id)
                    ->get()
                    ->first();
                $filtroCapturo =
                    '(' .
                    $tableSol .
                    ".idUsuarioCreo = '" .
                    $user->id .
                    "' OR " .
                    $tableSol .
                    ".idUsuarioActualizo = '" .
                    $user->id .
                    "' OR " .
                    $tableSol .
                    ".UsuarioAplicativo = '" .
                    $usuarioApp->UserName .
                    "')";
            } elseif ($viewall < 1) {
                $idUserOwner = DB::table('users_aplicativo_web')
                    ->selectRaw('idUserOwner')
                    ->where('idUser', $user->id)
                    ->get()
                    ->first();

                $filtroCapturo =
                    '(' .
                    $tableSol .
                    '.idUsuarioCreo IN (' .
                    'SELECT idUser FROM users_aplicativo_web WHERE idUserOwner = ' .
                    $idUserOwner->idUserOwner .
                    ') OR ' .
                    $tableSol .
                    '.UsuarioAplicativo IN (' .
                    'SELECT UserName FROM users_aplicativo_web WHERE idUserOwner = ' .
                    $idUserOwner->idUserOwner .
                    ')' .
                    ')';
            }

            $solicitudes = DB::table('calentadores_solicitudes')
                ->selectRaw(
                    "calentadores_solicitudes.*, 
                            entidadesNacimiento.Entidad AS EntidadNacimiento, cat_estado_civil.EstadoCivil, 
                            cat_parentesco_jefe_hogar.Parentesco, cat_parentesco_tutor.Parentesco, 
                            entidadesVive.Entidad AS EntidadVive,m.Region AS RegionM,
                            CONCAT_WS(' ', creadores.Nombre, creadores.Paterno, creadores.Materno) AS CreadoPor,
                            CONCAT_WS(' ', editores.Nombre, editores.Paterno, editores.Materno) AS ActualizadoPor,
                            calentadores_cedulas.id AS idCedula, calentadores_cedulas.ListaParaEnviar as ListaParaEnviarC"
                )
                ->join(
                    'cat_entidad AS entidadesNacimiento',
                    'entidadesNacimiento.id',
                    'calentadores_solicitudes.idEntidadNacimiento'
                )
                ->join(
                    'cat_estado_civil',
                    'cat_estado_civil.id',
                    'calentadores_solicitudes.idEstadoCivil'
                )
                ->join(
                    'cat_parentesco_jefe_hogar',
                    'cat_parentesco_jefe_hogar.id',
                    'calentadores_solicitudes.idParentescoJefeHogar'
                )
                ->leftJoin(
                    'cat_parentesco_tutor',
                    'cat_parentesco_tutor.id',
                    'calentadores_solicitudes.idParentescoTutor'
                )
                ->join(
                    'cat_entidad AS entidadesVive',
                    'entidadesVive.id',
                    'calentadores_solicitudes.idEntidadVive'
                )
                ->join(
                    'users AS creadores',
                    'creadores.id',
                    'calentadores_solicitudes.idUsuarioCreo'
                )
                ->leftJoin(
                    'users AS editores',
                    'editores.id',
                    'calentadores_solicitudes.idUsuarioActualizo'
                )
                ->leftJoin(
                    DB::raw($tableCedulas),
                    'calentadores_cedulas.idSolicitud',
                    'calentadores_solicitudes.id'
                )
                ->leftJoin(
                    'et_cat_municipio as m',
                    'm.Nombre',
                    'calentadores_solicitudes.MunicipioVive'
                )
                ->whereNull('calentadores_solicitudes.FechaElimino');

            $filterQuery = '';
            $municipioRegion = [];
            $mun = [];

            if (isset($params['filtered']) && count($params['filtered']) > 0) {
                $filtersCedulas = ['.id', '.ListaParaEnviar', '.MunicipioVive'];
                foreach ($params['filtered'] as $filtro) {
                    if ($filterQuery != '') {
                        $filterQuery .= ' AND ';
                    }
                    $id = $filtro['id'];
                    $value = $filtro['value'];

                    if ($id == '.FechaSolicitud') {
                        $timestamp = strtotime($value);
                        $value = date('Y-m-d', $timestamp);
                    }

                    if ($id == '.MunicipioVive') {
                        foreach ($value as $m) {
                            $mun[] = "'" . $m . "'";
                        }
                        $value = $mun;
                    }

                    if ($id == 'region') {
                        $municipios = DB::table('et_cat_municipio')
                            ->select('Nombre')
                            ->whereIN('SubRegion', $value)
                            ->get();
                        foreach ($municipios as $m) {
                            $municipioRegion[] = "'" . $m->Nombre . "'";
                        }

                        $id = '.MunicipioVive';
                        $value = $municipioRegion;
                    }

                    if (in_array($id, $filtersCedulas)) {
                        $id = 'calentadores_solicitudes' . $id;
                    } else {
                        $id = 'calentadores_solicitudes' . $id;
                    }

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
                $solicitudes->whereRaw($filterQuery);
            }

            if ($filtroCapturo !== '') {
                $solicitudes->whereRaw($filtroCapturo);
            }

            // dd(
            //     str_replace_array(
            //         '?',
            //         $solicitudes->getBindings(),
            //         $solicitudes->toSql()
            //     )
            // );

            $solicitudes = $solicitudes
                ->orderByDesc('FechaCreo')
                ->paginate($params['pageSize']);

            $response = [
                'success' => true,
                'results' => true,
                'data' => $solicitudes->items(),
                'total' => $solicitudes->total(),
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

    function createSolicitud(Request $request)
    {
        try {
            $v = Validator::make($request->all(), [
                'FechaSolicitud' => 'required',
                'Nombre' => 'required',
                'Paterno' => 'required',
                'Materno' => 'required',
                'FechaNacimiento' => 'required',
                'Edad' => 'required',
                'Sexo' => 'required',
                'idEntidadNacimiento' => 'required',
                'CURP' => 'required',
                'idEstadoCivil' => 'required',
                'idParentescoJefeHogar' => 'required',
                'NumHijos' => 'required',
                'NumHijas' => 'required',
                'Afromexicano' => 'required',
                'idSituacionActual' => 'required',
                'TarjetaImpulso' => 'required',
                'ContactoTarjetaImpulso' => 'required',
                'NecesidadSolicitante' => 'required',
                'CostoNecesidad' => 'required',
                'idEntidadVive' => 'required',
                'MunicipioVive' => 'required',
                'LocalidadVive' => 'required',
                'CPVive' => 'required',
                'ColoniaVive' => 'required',
                'CalleVive' => 'required',
                'NoExtVive' => 'required',
                'NoIntVive' => 'required',
                'Referencias' => 'required',
                'Folio' => 'required',
            ]);

            // if ($v->fails()) {
            //     $response = [
            //         'success' => true,
            //         'results' => false,
            //         'errors' => $v->errors(),
            //     ];
            //     return response()->json($response, 200);
            // }

            $params = $request->all();

            if (isset($params['Folio'])) {
                $folioRegistrado = DB::table('calentadores_solicitudes')
                    ->where(['Folio' => $params['Folio']])
                    ->whereRaw('FechaElimino IS NULL')
                    ->first();
                if ($folioRegistrado != null) {
                    $response = [
                        'success' => true,
                        'results' => false,
                        'errors' =>
                            'El Folio ' .
                            $params['Folio'] .
                            ' ya esta registrado para la persona ' .
                            $folioRegistrado->Nombre .
                            ' ' .
                            $folioRegistrado->Paterno .
                            ' ' .
                            $folioRegistrado->Materno .
                            ' con CURP ' .
                            $folioRegistrado->CURP,
                    ];
                    return response()->json($response, 200);
                }
            }

            $user = auth()->user();
            $params['idUsuarioCreo'] = $user->id;
            $params['FechaCreo'] = date('Y-m-d');
            $params['idEstatus'] = 1;

            unset($params['Files']);
            unset($params['ArchivosClasificacion']);
            //dd($params);
            $id = DB::table('calentadores_solicitudes')->insertGetId($params);

            $response = [
                'success' => true,
                'results' => true,
                'message' => 'Solicitud creada con éxito',
                'data' => $id,
            ];

            return response()->json($response, 200);
        } catch (Throwable $errors) {
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

    function updateSolicitud(Request $request)
    {
        try {
            $v = Validator::make($request->all(), [
                'id' => 'required',
                'FechaSolicitud' => 'required',
                'Nombre' => 'required',
                'Paterno' => 'required',
                'Materno' => 'required',
                'FechaNacimiento' => 'required',
                'Edad' => 'required',
                'Sexo' => 'required',
                'idEntidadNacimiento' => 'required',
                'CURP' => 'required',
                'idEstadoCivil' => 'required',
                'idParentescoJefeHogar' => 'required',
                'NumHijos' => 'required',
                'NumHijas' => 'required',
                'Afromexicano' => 'required',
                'idSituacionActual' => 'required',
                'TarjetaImpulso' => 'required',
                'ContactoTarjetaImpulso' => 'required',
                'NecesidadSolicitante' => 'required',
                'CostoNecesidad' => 'required',
                'idEntidadVive' => 'required',
                'MunicipioVive' => 'required',
                'LocalidadVive' => 'required',
                'CPVive' => 'required',
                'ColoniaVive' => 'required',
                'CalleVive' => 'required',
                'NoExtVive' => 'required',
                'NoIntVive' => 'required',
                'Referencias' => 'required',
                'Folio' => 'required',
            ]);

            // if ($v->fails()) {
            //     $response = [
            //         'success' => true,
            //         'results' => false,
            //         'errors' => $v->errors(),
            //     ];
            //     return response()->json($response, 200);
            // }

            $params = $request->all();

            if (!isset($params['Folio'])) {
                $program = 1;
            } else {
                $program = $this->getPrograma($params['Folio']);
            }

            $tableCedulas =
                '(SELECT * FROM calentadores_cedulas WHERE FechaElimino IS NULL) AS calentadores_cedulas';

            $solicitud = DB::table('calentadores_solicitudes')
                ->select(
                    'calentadores_solicitudes.idEstatus',
                    'calentadores_cedulas.id AS idCedula',
                    'calentadores_cedulas.ListaParaEnviar'
                )
                ->leftJoin(
                    DB::raw($tableCedulas),
                    'calentadores_cedulas.idSolicitud',
                    'calentadores_solicitudes.id'
                )
                ->where('calentadores_solicitudes.id', $params['id'])
                ->first();
            if ($solicitud->idEstatus != 1 || isset($solicitud->idCedula)) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' =>
                        'La solicitud no se puede editar, tiene una cédula activa o ya fue aceptada',
                ];
                return response()->json($response, 200);
            }

            // if (
            //     !isset($params['Celular']) &&
            //     !isset($params['Telefono']) &&
            //     !isset($params['Correo']) &&
            //     !isset($params['TelRecados'])
            // ) {
            //     $response = [
            //         'success' => true,
            //         'results' => false,
            //         'errors' => 'Agregue al menos un método de contacto',
            //     ];
            //     return response()->json($response, 200);
            // }

            // if ($params['Edad'] < 18) {
            //     if (
            //         !isset($params['idParentescoTutor']) &&
            //         !isset($params['NombreTutor']) &&
            //         !isset($params['PaternoTutor']) &&
            //         !isset($params['MaternoTutor']) &&
            //         !isset($params['FechaNacimientoTutor']) &&
            //         !isset($params['EdadTutor']) &&
            //         !isset($params['CURPTutor'])
            //     ) {
            //         $response = [
            //             'success' => true,
            //             'results' => false,
            //             'errors' => 'Información de tutor incompleta',
            //         ];
            //         return response()->json($response, 200);
            //     }
            // }

            $user = auth()->user();
            $id = $params['id'];
            unset($params['id']);
            unset($params['Files']);
            unset($params['ArchivosClasificacion']);
            unset($params['ListaParaEnviar']);
            unset($params['OldFiles']);
            unset($params['OldClasificacion']);
            unset($params['NewFiles']);
            unset($params['NewClasificacion']);

            $params['idUsuarioActualizo'] = $user->id;
            $params['FechaActualizo'] = date('Y-m-d');
            $params['idEstatus'] = 1;

            DB::table('calentadores_solicitudes')
                ->where('id', $id)
                ->update($params);

            $response = [
                'success' => true,
                'results' => true,
                'message' => 'Solicitud actualizada con éxito',
                'data' => [],
            ];

            return response()->json($response, 200);
        } catch (\Throwable $errors) {
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

    function deleteSolicitud(Request $request)
    {
        try {
            $v = Validator::make($request->all(), [
                'id' => 'required',
            ]);
            if ($v->fails()) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' => $v->errors(),
                ];
                return response()->json($response, 200);
            }

            $params = $request->all();

            if (!isset($params['Folio'])) {
                $program = 1;
            } else {
                $program = $this->getPrograma($params['Folio']);
            }

            $tableCedulas =
                '(SELECT * FROM calentadores_cedulas WHERE FechaElimino IS NULL) AS calentadores_cedulas';

            $solicitud = DB::table('calentadores_solicitudes')
                ->select(
                    'calentadores_solicitudes.idEstatus',
                    'calentadores_cedulas.id AS idCedula',
                    'calentadores_cedulas.ListaParaEnviar'
                )
                ->leftJoin(
                    DB::raw($tableCedulas),
                    'calentadores_cedulas.idSolicitud',
                    'calentadores_solicitudes.id'
                )
                ->where('calentadores_solicitudes.id', $params['id'])
                ->first();
            if ($solicitud->idEstatus != 1 || isset($solicitud->idCedula)) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' =>
                        'La solicitud no se puede eliminar, tiene una cédula activa o ya fue aceptada',
                ];
                return response()->json($response, 200);
            }
            $user = auth()->user();
            // DB::table('calentadores_solicitudes')
            //     ->where('id', $params['id'])
            //     ->delete();
            DB::table('calentadores_solicitudes')
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
                'total' => 0,
                'errors' => $errors,
                'message' => 'Ha ocurrido un error, consulte al administrador',
            ];

            return response()->json($response, 200);
        }
    }

    function getCatalogsCedulaCompletos(Request $request)
    {
        try {
            $cat_estado_civil = DB::table('cat_estado_civil')
                ->select('id AS value', 'EstadoCivil AS label')
                ->get();

            $entidades = DB::table('cat_entidad')
                ->select('id AS value', 'Entidad AS label')
                ->where('id', '<>', 1)
                ->get();

            $cat_parentesco_jefe_hogar = DB::table('cat_parentesco_jefe_hogar')
                ->select('id AS value', 'Parentesco AS label')
                ->get();

            $cat_parentesco_tutor = DB::table('cat_parentesco_tutor')
                ->select('id AS value', 'Parentesco AS label')
                ->get();

            $cat_situacion_actual = DB::table('cat_situacion_actual')
                ->select('id AS value', 'Situacion AS label')
                ->get();

            $cat_actividades = DB::table('cat_actividades')
                ->select('id AS value', 'Actividad AS label')
                ->get();

            $cat_codigos_dificultad = DB::table('cat_codigos_dificultad')
                ->select('id AS value', 'Grado AS label')
                ->get();

            $cat_enfermedades = DB::table('cat_enfermedades')
                ->select('id AS value', 'Enfermedad AS label')
                ->get();

            $cat_grados_educacion = DB::table('cat_grados_educacion')
                ->select('id AS value', 'Grado AS label')
                ->get();

            $cat_niveles_educacion = DB::table('cat_niveles_educacion')
                ->select('id AS value', 'Nivel AS label')
                ->get();

            $cat_prestaciones = DB::table('cat_prestaciones')
                ->select('id AS value', 'Prestacion AS label')
                ->get();

            $cat_situacion_actual = DB::table('cat_situacion_actual')
                ->select('id AS value', 'Situacion AS label')
                ->get();

            $cat_tipo_seguro = DB::table('cat_tipo_seguro')
                ->select('id AS value', 'Tipo AS label')
                ->get();

            $cat_tipos_agua = DB::table('cat_tipos_agua')
                ->select('id AS value', 'Agua AS label')
                ->get();

            $cat_tipos_combustibles = DB::table('cat_tipos_combustibles')
                ->select('id AS value', 'Combustible AS label')
                ->get();

            $cat_tipos_drenajes = DB::table('cat_tipos_drenajes')
                ->select('id AS value', 'Drenaje AS label')
                ->get();

            $cat_tipos_luz = DB::table('cat_tipos_luz')
                ->select('id AS value', 'Luz AS label')
                ->get();

            $cat_tipos_muros = DB::table('cat_tipos_muros')
                ->select('id AS value', 'Muro AS label')
                ->get();

            $cat_tipos_pisos = DB::table('cat_tipos_pisos')
                ->select('id AS value', 'Piso AS label')
                ->get();

            $cat_tipos_techos = DB::table('cat_tipos_techos')
                ->select('id AS value', 'Techo AS label')
                ->get();

            $cat_tipos_viviendas = DB::table('cat_tipos_viviendas')
                ->select('id AS value', 'Tipo AS label')
                ->get();

            $cat_periodicidad = DB::table('cat_periodicidad')
                ->select('id AS value', 'Periodicidad AS label')
                ->get();

            $archivos_clasificacion = DB::table('cedula_archivos_clasificacion')
                ->select('id AS value', 'Clasificacion AS label')
                ->get();

            $municipios = DB::table('et_cat_municipio')
                ->select('id AS value', 'Nombre AS label')
                ->get();

            $catalogs = [
                'entidades' => $entidades,
                'cat_parentesco_jefe_hogar' => $cat_parentesco_jefe_hogar,
                'cat_parentesco_tutor' => $cat_parentesco_tutor,
                'cat_situacion_actual' => $cat_situacion_actual,
                'cat_estado_civil' => $cat_estado_civil,
                'cat_actividades' => $cat_actividades,
                'cat_codigos_dificultad' => $cat_codigos_dificultad,
                'cat_enfermedades' => $cat_enfermedades,
                'cat_grados_educacion' => $cat_grados_educacion,
                'cat_niveles_educacion' => $cat_niveles_educacion,
                'cat_prestaciones' => $cat_prestaciones,
                'cat_situacion_actual' => $cat_situacion_actual,
                'cat_tipo_seguro' => $cat_tipo_seguro,
                'cat_tipos_combustibles' => $cat_tipos_combustibles,
                'cat_tipos_drenajes' => $cat_tipos_drenajes,
                'cat_tipos_luz' => $cat_tipos_luz,
                'cat_tipos_muros' => $cat_tipos_muros,
                'cat_tipos_pisos' => $cat_tipos_pisos,
                'cat_tipos_techos' => $cat_tipos_techos,
                'cat_tipos_viviendas' => $cat_tipos_viviendas,
                'cat_tipos_agua' => $cat_tipos_agua,
                'cat_periodicidad' => $cat_periodicidad,
                'archivos_clasificacion' => $archivos_clasificacion,
                'municipios' => $municipios,
            ];

            $response = [
                'success' => true,
                'results' => true,
                'data' => $catalogs,
            ];
            return response()->json($response, 200);
        } catch (\Throwable $errors) {
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

    function create(Request $request)
    {
        try {
            $v = Validator::make($request->all(), [
                'idSolicitud' => 'required',
                'FechaSolicitud' => 'required',
                'Nombre' => 'required',
                'Paterno' => 'required',
                'Materno' => 'required',
                'FechaNacimiento' => 'required',
                'Edad' => 'required',
                'Sexo' => 'required',
                'idEntidadNacimiento' => 'required',
                'CURP' => 'required',
                'Celular' => 'required',
                'Correo' => 'required',
                'idEstadoCivil' => 'required',
                'idParentescoJefeHogar' => 'required',
                'NumHijos' => 'required',
                'NumHijas' => 'required',
                'Afromexicano' => 'required',
                'idSituacionActual' => 'required',
                'TarjetaImpulso' => 'required',
                'ContactoTarjetaImpulso' => 'required',
                'NecesidadSolicitante' => 'required',
                'CostoNecesidad' => 'required',
                'idEntidadVive' => 'required',
                'MunicipioVive' => 'required',
                'LocalidadVive' => 'required',
                'CPVive' => 'required',
                'AGEBVive' => 'required',
                'ManzanaVive' => 'required',
                'TipoAsentamientoVive' => 'required',
                'ColoniaVive' => 'required',
                'CalleVive' => 'required',
                'NoExtVive' => 'required',
                'NoIntVive' => 'required',
                'Referencias' => 'required',
                'TotalHogares' => 'required',
                'NumeroMujeresHogar' => 'required',
                'NumeroHombresHogar' => 'required',
                'PersonasMayoresEdad' => 'required',
                'PersonasTerceraEdad' => 'required',
                'PersonaJefaFamilia' => 'required',
                'DificultadMovilidad' => 'required',
                'DificultadVer' => 'required',
                'DificultadHablar' => 'required',
                'DificultadOir' => 'required',
                'DificultadVestirse' => 'required',
                'DificultadRecordar' => 'required',
                'DificultadBrazos' => 'required',
                'DificultadMental' => 'required',
                'AsisteEscuela' => 'required',
                'idNivelEscuela' => 'required',
                'idGradoEscuela' => 'required',
                'idActividades' => 'required',
                'IngresoTotalMesPasado' => 'required',
                'PensionMensual' => 'required',
                'IngresoOtrosPaises' => 'required',
                'GastoAlimentos' => 'required',
                'PeriodicidadAlimentos' => 'required',
                'GastoVestido' => 'required',
                'PeriodicidadVestido' => 'required',
                'GastoEducacion' => 'required',
                'PeriodicidadEducacion' => 'required',
                'GastoMedicinas' => 'required',
                'PeriodicidadMedicinas' => 'required',
                'GastosConsultas' => 'required',
                'PeriodicidadConsultas' => 'required',
                'GastosCombustibles' => 'required',
                'PeriodicidadCombustibles' => 'required',
                'GastosServiciosBasicos' => 'required',
                'PeriodicidadServiciosBasicos' => 'required',
                'GastosServiciosRecreacion' => 'required',
                'PeriodicidadServiciosRecreacion' => 'required',
                'AlimentacionPocoVariada' => 'required',
                'ComioMenos' => 'required',
                'DisminucionComida' => 'required',
                'NoComio' => 'required',
                'DurmioHambre' => 'required',
                'DejoComer' => 'required',
                'PersonasHogar' => 'required',
                'CuartosHogar' => 'required',
                'idTipoVivienda' => 'required',
                'idTipoPiso' => 'required',
                'idTipoParedes' => 'required',
                'idTipoTecho' => 'required',
                'idTipoAgua' => 'required',
                'idTipoDrenaje' => 'required',
                'idTipoLuz' => 'required',
                'idTipoCombustible' => 'required',
                'Refrigerador' => 'required',
                'Lavadora' => 'required',
                'Computadora' => 'required',
                'Estufa' => 'required',
                'Calentador' => 'required',
                'CalentadorSolar' => 'required',
                'Television' => 'required',
                'Internet' => 'required',
                'TieneTelefono' => 'required',
                'Tinaco' => 'required',
                'ColoniaSegura' => 'required',
                'ListaParaEnviar' => 'required',
                'Prestaciones' => 'required|array',
                'Enfermedades' => 'required|array',
                'AtencionesMedicas' => 'required|array',
            ]);
            // if ($v->fails()) {
            //     $response = [
            //         'success' => true,
            //         'results' => false,
            //         'errors' => $v->errors(),
            //     ];
            //     return response()->json($response, 200);
            // }
            $params = $request->all();

            DB::beginTransaction();

            $prestaciones = isset($params['Prestaciones'])
                ? $params['Prestaciones']
                : [];
            $enfermedades = isset($params['Enfermedades'])
                ? $params['Enfermedades']
                : [];
            $atencionesMedicas = isset($params['AtencionesMedicas'])
                ? $params['AtencionesMedicas']
                : [];
            $newClasificacion = isset($params['NewClasificacion'])
                ? $params['NewClasificacion']
                : [];

            $user = auth()->user();
            $params['idUsuarioCreo'] = $user->id;
            $params['FechaCreo'] = date('Y-m-d');
            $params['Correo'] =
                isset($params['Correo']) && $params['Correo'] != ''
                    ? $params['Correo']
                    : '';
            unset($params['Prestaciones']);
            unset($params['Enfermedades']);
            unset($params['AtencionesMedicas']);
            unset($params['NewClasificacion']);
            unset($params['NewFiles']);
            unset($params['idCedula']);
            unset($params['id']);
            unset($params['Boiler']);

            $id = DB::table('calentadores_cedulas')->insertGetId($params);

            $this->updateSolicitudFromCedula($params, $user);

            $formatedPrestaciones = [];
            foreach ($prestaciones as $prestacion) {
                array_push($formatedPrestaciones, [
                    'idCedula' => $id,
                    'idPrestacion' => $prestacion,
                ]);
            }
            DB::table('calentadores_prestaciones')->insert(
                $formatedPrestaciones
            );

            $formatedEnfermedades = [];
            foreach ($enfermedades as $enfermedad) {
                array_push($formatedEnfermedades, [
                    'idCedula' => $id,
                    'idEnfermedad' => $enfermedad,
                ]);
            }
            DB::table('calentadores_enfermedades')->insert(
                $formatedEnfermedades
            );

            $formatedAtencionesMedicas = [];
            foreach ($atencionesMedicas as $atencion) {
                array_push($formatedAtencionesMedicas, [
                    'idCedula' => $id,
                    'idAtencionMedica' => $atencion,
                ]);
            }
            DB::table('calentadores_atenciones_medicas')->insert(
                $formatedAtencionesMedicas
            );

            if (isset($request->NewFiles)) {
                $this->createCedulaFiles(
                    $id,
                    $request->NewFiles,
                    $newClasificacion,
                    $user->id
                );
            }

            DB::commit();

            $response = [
                'success' => true,
                'results' => true,
                'message' => 'Creada con éxito',
                'data' => [],
            ];
            return response()->json($response, 200);
        } catch (\Throwable $errors) {
            DB::rollBack();
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

    function getById(Request $request, $id)
    {
        try {
            $cedula = DB::table('calentadores_cedulas')
                ->selectRaw(
                    "
                            calentadores_cedulas.*
                        "
                )
                // ,
                //     entidadesNacimiento.Entidad AS EntidadNacimiento, cat_estado_civil.EstadoCivil,
                //     cat_parentesco_jefe_hogar.Parentesco, cat_parentesco_tutor.Parentesco,
                //     entidadesVive.Entidad AS EntidadVive,
                //     cat_niveles_educacion.Nivel, cat_grados_educacion.Grado, cat_actividades.Actividad,
                //     cat_tipos_viviendas.Tipo, cat_tipos_pisos.Piso, cat_tipos_muros.Muro,
                //     cat_tipos_techos.Techo, cat_tipos_agua.Agua, cat_tipos_drenajes.Drenaje,
                //     cat_tipos_luz.Luz, cat_tipos_combustibles.Combustible
                // ->join("cat_entidad AS entidadesNacimiento", "entidadesNacimiento.id", "cedulas.idEntidadNacimiento")
                // ->join("cat_estado_civil", "cat_estado_civil.id", "cedulas.idEstadoCivil")
                // ->join("cat_parentesco_jefe_hogar", "cat_parentesco_jefe_hogar.id", "cedulas.idParentescoJefeHogar")
                // ->leftJoin("cat_parentesco_tutor", "cat_parentesco_tutor.id", "cedulas.idParentescoTutor")
                // ->join("cat_entidad AS entidadesVive", "entidadesVive.id", "cedulas.idEntidadVive")
                // ->leftJoin("cat_niveles_educacion", "cat_niveles_educacion.id", "cedulas.idNivelEscuela")
                // ->leftJoin("cat_grados_educacion", "cat_grados_educacion.id", "cedulas.idGradoEscuela")
                // ->leftJoin("cat_actividades", "cat_actividades.id", "cedulas.idActividades")
                // ->leftJoin("cat_tipos_viviendas", "cat_tipos_viviendas.id", "cedulas.idTipoVivienda")
                // ->leftJoin("cat_tipos_pisos", "cat_tipos_pisos.id", "cedulas.idTipoPiso")
                // ->leftJoin("cat_tipos_muros", "cat_tipos_muros.id", "cedulas.idTipoParedes")
                // ->leftJoin("cat_tipos_techos", "cat_tipos_techos.id", "cedulas.idTipoTecho")
                // ->leftJoin("cat_tipos_agua", "cat_tipos_agua.id", "cedulas.idTipoAgua")
                // ->leftJoin("cat_tipos_drenajes", "cat_tipos_drenajes.id", "cedulas.idTipoDrenaje")
                // ->leftJoin("cat_tipos_luz", "cat_tipos_luz.id", "cedulas.idTipoLuz")
                // ->leftJoin("cat_tipos_combustibles", "cat_tipos_combustibles.id", "cedulas.idTipoCombustible")
                ->where('calentadores_cedulas.id', $id)
                ->whereNull('calentadores_cedulas.FechaElimino')
                ->first();
            $prestaciones = DB::table('calentadores_prestaciones')
                ->select('idPrestacion')
                ->where('idCedula', $id)
                ->get();

            $enfermedades = DB::table('calentadores_enfermedades')
                ->select('idEnfermedad')
                ->where('idCedula', $id)
                ->get();

            $atencionesMedicas = DB::table('calentadores_atenciones_medicas')
                ->select('idAtencionMedica')
                ->where('idCedula', $id)
                ->get();

            $archivos = DB::table('calentadores_cedula_archivos')
                ->select(
                    'id',
                    'idClasificacion',
                    'NombreOriginal AS name',
                    'NombreSistema',
                    'Tipo AS type'
                )
                ->where('idCedula', $id)
                ->whereRaw('FechaElimino IS NULL')
                ->get();
            $cedula->Prestaciones = array_map(function ($o) {
                return $o->idPrestacion;
            }, $prestaciones->toArray());
            $cedula->Enfermedades = array_map(function ($o) {
                return $o->idEnfermedad;
            }, $enfermedades->toArray());
            $cedula->AtencionesMedicas = array_map(function ($o) {
                return $o->idAtencionMedica;
            }, $atencionesMedicas->toArray());
            $cedula->Files = $archivos;
            $cedula->ArchivosClasificacion = array_map(function ($o) {
                return $o->idClasificacion;
            }, $archivos->toArray());

            $response = [
                'success' => true,
                'results' => true,
                'message' => 'éxito',
                'data' => $cedula,
            ];
            return response()->json($response, 200);
        } catch (\Throwable $errors) {
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

    function getFilesById(Request $request, $id)
    {
        try {
            $archivos2 = DB::table('calentadores_cedula_archivos')
                ->select(
                    'id',
                    'idClasificacion',
                    'NombreOriginal AS name',
                    'NombreSistema',
                    'Tipo AS type'
                )
                ->where('idCedula', $id)
                ->whereRaw('FechaElimino IS NULL')
                ->get();
            $archivosClasificacion = array_map(function ($o) {
                return $o->idClasificacion;
            }, $archivos2->toArray());

            $archivos = array_map(function ($o) {
                $o->ruta =
                    'https://apivales.apisedeshu.com/subidos/' .
                    $o->NombreSistema;
                // '/var/www/html/plataforma/apivales/public/subidos/' .
                // $o->NombreSistema;
                return $o;
            }, $archivos2->toArray());

            $response = [
                'success' => true,
                'results' => true,
                'message' => 'éxito',
                'data' => [
                    'Archivos' => $archivos,
                    'ArchivosClasificacion' => $archivosClasificacion,
                ],
            ];
            return response()->json($response, 200);
        } catch (\Throwable $errors) {
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

    function getFilesByIdSolicitud(Request $request)
    {
        try {
            $v = Validator::make($request->all(), [
                'id' => 'required',
            ]);
            if ($v->fails()) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' => $v->errors(),
                ];
                return response()->json($response, 200);
            }
            $params = $request->all();
            $idSolicitud = $params['id'];
            $cedula = DB::table('cedulas_calentadores')
                ->select('id')
                ->where('idSolicitud', $idSolicitud)
                ->whereRaw('FechaElimino IS NULL')
                ->get()
                ->first();

            if ($cedula == null) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' => 'No fue posible encontrar la cédula',
                ];
                return response()->json($response, 200);
            }

            $archivos2 = DB::table('calentadores_cedula_archivos')
                ->select(
                    'id',
                    'idClasificacion',
                    'NombreOriginal AS name',
                    'NombreSistema',
                    'Tipo AS type'
                )
                ->where('idCedula', $cedula->id)
                ->whereRaw('FechaElimino IS NULL')
                ->get();
            $archivosClasificacion = array_map(function ($o) {
                return $o->idClasificacion;
            }, $archivos2->toArray());

            $archivos = array_map(function ($o) {
                $o->ruta =
                    'https://apivales.apisedeshu.com/subidos/' .
                    $o->NombreSistema;
                // '/var/www/html/plataforma/apivales/public/subidos/' .
                // $o->NombreSistema;
                return $o;
            }, $archivos2->toArray());

            $response = [
                'success' => true,
                'results' => true,
                'message' => 'éxito',
                'data' => [
                    'Archivos' => $archivos,
                    'ArchivosClasificacion' => $archivosClasificacion,
                ],
            ];
            return response()->json($response, 200);
        } catch (\Throwable $errors) {
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

    function update(Request $request)
    {
        try {
            $v = Validator::make($request->all(), [
                'id' => 'required',
                'idSolicitud' => 'required',
                'FechaSolicitud' => 'required',
                'Nombre' => 'required',
                'Paterno' => 'required',
                'Materno' => 'required',
                'FechaNacimiento' => 'required',
                'Edad' => 'required',
                'Sexo' => 'required',
                'idEntidadNacimiento' => 'required',
                'CURP' => 'required',
                'Celular' => 'required',
                'Correo' => 'required',
                'idEstadoCivil' => 'required',
                'idParentescoJefeHogar' => 'required',
                'NumHijos' => 'required',
                'NumHijas' => 'required',
                'Afromexicano' => 'required',
                'idSituacionActual' => 'required',
                'TarjetaImpulso' => 'required',
                'ContactoTarjetaImpulso' => 'required',
                'NecesidadSolicitante' => 'required',
                'CostoNecesidad' => 'required',
                'idEntidadVive' => 'required',
                'MunicipioVive' => 'required',
                'LocalidadVive' => 'required',
                'CPVive' => 'required',
                'AGEBVive' => 'required',
                'ManzanaVive' => 'required',
                'TipoAsentamientoVive' => 'required',
                'ColoniaVive' => 'required',
                'CalleVive' => 'required',
                'NoExtVive' => 'required',
                'NoIntVive' => 'required',
                'Referencias' => 'required',
                'TotalHogares' => 'required',
                'NumeroMujeresHogar' => 'required',
                'NumeroHombresHogar' => 'required',
                'PersonasMayoresEdad' => 'required',
                'PersonasTerceraEdad' => 'required',
                'PersonaJefaFamilia' => 'required',
                'DificultadMovilidad' => 'required',
                'DificultadVer' => 'required',
                'DificultadHablar' => 'required',
                'DificultadOir' => 'required',
                'DificultadVestirse' => 'required',
                'DificultadRecordar' => 'required',
                'DificultadBrazos' => 'required',
                'DificultadMental' => 'required',
                'AsisteEscuela' => 'required',
                'idNivelEscuela' => 'required',
                'idGradoEscuela' => 'required',
                'idActividades' => 'required',
                'IngresoTotalMesPasado' => 'required',
                'PensionMensual' => 'required',
                'IngresoOtrosPaises' => 'required',
                'GastoAlimentos' => 'required',
                'PeriodicidadAlimentos' => 'required',
                'GastoVestido' => 'required',
                'PeriodicidadVestido' => 'required',
                'GastoEducacion' => 'required',
                'PeriodicidadEducacion' => 'required',
                'GastoMedicinas' => 'required',
                'PeriodicidadMedicinas' => 'required',
                'GastosConsultas' => 'required',
                'PeriodicidadConsultas' => 'required',
                'GastosCombustibles' => 'required',
                'PeriodicidadCombustibles' => 'required',
                'GastosServiciosBasicos' => 'required',
                'PeriodicidadServiciosBasicos' => 'required',
                'GastosServiciosRecreacion' => 'required',
                'PeriodicidadServiciosRecreacion' => 'required',
                'AlimentacionPocoVariada' => 'required',
                'ComioMenos' => 'required',
                'DisminucionComida' => 'required',
                'NoComio' => 'required',
                'DurmioHambre' => 'required',
                'DejoComer' => 'required',
                'PersonasHogar' => 'required',
                'CuartosHogar' => 'required',
                'idTipoVivienda' => 'required',
                'idTipoPiso' => 'required',
                'idTipoParedes' => 'required',
                'idTipoTecho' => 'required',
                'idTipoAgua' => 'required',
                'idTipoDrenaje' => 'required',
                'idTipoLuz' => 'required',
                'idTipoCombustible' => 'required',
                'Refrigerador' => 'required',
                'Lavadora' => 'required',
                'Computadora' => 'required',
                'Estufa' => 'required',
                'Calentador' => 'required',
                'CalentadorSolar' => 'required',
                'Television' => 'required',
                'Internet' => 'required',
                'TieneTelefono' => 'required',
                'Tinaco' => 'required',
                'ColoniaSegura' => 'required',
                'ListaParaEnviar' => 'required',
                'Prestaciones' => 'required|array',
                'Enfermedades' => 'required|array',
                'AtencionesMedicas' => 'required|array',
                'Folio' => 'required',
            ]);
            // if ($v->fails()) {
            //     $response = [
            //         'success' => true,
            //         'results' => false,
            //         'errors' => $v->errors(),
            //     ];
            //     return response()->json($response, 200);
            // }
            $params = $request->all();
            $user = auth()->user();
            $id = $params['id'];
            unset($params['id']);

            $cedula = DB::table('calentadores_cedulas')
                ->where('id', $id)
                ->whereNull('FechaElimino')
                ->first();

            if ($cedula == null) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' => 'La cédula no fue encontrada',
                ];
                return response()->json($response, 200);
            }

            if ($cedula->ListaParaEnviar && $user->id != 52) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' =>
                        "La cédula tiene estatus 'Lista para enviarse', no se puede editar",
                ];
                return response()->json($response, 200);
            }

            DB::beginTransaction();
            $prestaciones = isset($params['Prestaciones'])
                ? $params['Prestaciones']
                : [];
            $enfermedades = isset($params['Enfermedades'])
                ? $params['Enfermedades']
                : [];
            $atencionesMedicas = isset($params['AtencionesMedicas'])
                ? $params['AtencionesMedicas']
                : [];
            $oldClasificacion = isset($params['OldClasificacion'])
                ? $params['OldClasificacion']
                : [];
            $newClasificacion = isset($params['NewClasificacion'])
                ? $params['NewClasificacion']
                : [];
            $params['idUsuarioActualizo'] = $user->id;
            $params['FechaActualizo'] = date('Y-m-d');
            $params['Correo'] =
                isset($params['Correo']) && $params['Correo'] != ''
                    ? $params['Correo']
                    : '';
            unset($params['Prestaciones']);
            unset($params['Enfermedades']);
            unset($params['AtencionesMedicas']);
            unset($params['OldFiles']);
            unset($params['OldClasificacion']);
            unset($params['NewFiles']);
            unset($params['NewClasificacion']);
            unset($params['idCedula']);
            unset($params['Boiler']);

            DB::table('calentadores_cedulas')
                ->where('id', $id)
                ->update($params);

            $this->updateSolicitudFromCedula($params, $user);

            DB::table('calentadores_prestaciones')
                ->where('idCedula', $id)
                ->delete();
            $formatedPrestaciones = [];
            foreach ($prestaciones as $prestacion) {
                array_push($formatedPrestaciones, [
                    'idCedula' => $id,
                    'idPrestacion' => $prestacion,
                ]);
            }
            DB::table('calentadores_prestaciones')->insert(
                $formatedPrestaciones
            );

            DB::table('calentadores_enfermedades')
                ->where('idCedula', $id)
                ->delete();
            $formatedEnfermedades = [];
            foreach ($enfermedades as $enfermedad) {
                array_push($formatedEnfermedades, [
                    'idCedula' => $id,
                    'idEnfermedad' => $enfermedad,
                ]);
            }
            DB::table('calentadores_enfermedades')->insert(
                $formatedEnfermedades
            );

            DB::table('calentadores_atenciones_medicas')
                ->where('idCedula', $id)
                ->delete();
            $formatedAtencionesMedicas = [];
            foreach ($atencionesMedicas as $atencion) {
                array_push($formatedAtencionesMedicas, [
                    'idCedula' => $id,
                    'idAtencionMedica' => $atencion,
                ]);
            }
            DB::table('calentadores_atenciones_medicas')->insert(
                $formatedAtencionesMedicas
            );

            $oldFiles = DB::table('calentadores_cedula_archivos')
                ->select('id', 'idClasificacion')
                ->where('idCedula', $id)
                ->whereRaw('FechaElimino IS NULL')
                ->get();
            $oldFilesIds = array_map(function ($o) {
                return $o->id;
            }, $oldFiles->toArray());
            if (isset($request->NewFiles)) {
                $this->createCedulaFiles(
                    $id,
                    $request->NewFiles,
                    $newClasificacion,
                    $user->id
                );
            }
            if (isset($request->OldFiles)) {
                $oldFilesIds = $this->updateCedulaFiles(
                    $id,
                    $request->OldFiles,
                    $oldClasificacion,
                    $user->id,
                    $oldFilesIds,
                    $oldFiles
                );
            }

            if (count($oldFilesIds) > 0) {
                DB::table('calentadores_cedula_archivos')
                    ->whereIn('id', $oldFilesIds)
                    ->update([
                        'idUsuarioElimino' => $user->id,
                        'FechaElimino' => date('Y-m-d H:i:s'),
                    ]);
            }

            DB::commit();

            $response = [
                'success' => true,
                'results' => true,
                'message' => 'Editada con éxito',
                'data' => [],
            ];
            return response()->json($response, 200);
        } catch (QueryException $errors) {
            DB::rollBack();
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

    function updateArchivosCedula(Request $request)
    {
        try {
            $v = Validator::make($request->all(), [
                'id' => 'required',
            ]);
            if ($v->fails()) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' => $v->errors(),
                ];
                return response()->json($response, 200);
            }
            $params = $request->all();
            $oldClasificacion = isset($params['OldClasificacion'])
                ? $params['OldClasificacion']
                : [];
            $newClasificacion = isset($params['NewClasificacion'])
                ? $params['NewClasificacion']
                : [];
            $id = $params['id'];
            $user = auth()->user();

            DB::beginTransaction();
            $oldFiles = DB::table('calentadores_cedula_archivos')
                ->select('id', 'idClasificacion')
                ->where('idCedula', $id)
                ->whereRaw('FechaElimino IS NULL')
                ->get();
            $oldFilesIds = array_map(function ($o) {
                return $o->id;
            }, $oldFiles->toArray());
            if (isset($request->NewFiles)) {
                $this->createCedulaFiles(
                    $id,
                    $request->NewFiles,
                    $newClasificacion,
                    $user->id
                );
            }
            if (isset($request->OldFiles)) {
                $oldFilesIds = $this->updateCedulaFiles(
                    $id,
                    $request->OldFiles,
                    $oldClasificacion,
                    $user->id,
                    $oldFilesIds,
                    $oldFiles
                );
            }

            if (count($oldFilesIds) > 0) {
                DB::table('calentadores_cedula_archivos')
                    ->whereIn('id', $oldFilesIds)
                    ->update([
                        'idUsuarioElimino' => $user->id,
                        'FechaElimino' => date('Y-m-d H:i:s'),
                    ]);
            }
            DB::commit();
            $response = [
                'success' => true,
                'results' => true,
                'message' => 'Editada con éxito',
                'data' => [],
            ];
            return response()->json($response, 200);
        } catch (QueryException $errors) {
            DB::rollBack();
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

    function delete(Request $request)
    {
        try {
            $v = Validator::make($request->all(), [
                'id' => 'required',
            ]);
            if ($v->fails()) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' => $v->errors(),
                ];
                return response()->json($response, 200);
            }
            $params = $request->all();
            $user = auth()->user();
            $id = $params['id'];

            $cedula = DB::table('calentadores_cedulas')
                ->where('id', $id)
                ->first();

            if ($cedula == null) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' => 'La cédula no fue encontrada',
                ];
                return response()->json($response, 200);
            }
            if ($cedula->ListaParaEnviar && $user->id != 52) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' =>
                        "La cédula tiene estatus 'Lista para enviarse', no se puede editar",
                ];
                return response()->json($response, 200);
            }

            DB::beginTransaction();

            DB::table('calentadores_prestaciones')
                ->where('idCedula', $id)
                ->delete();

            DB::table('calentadores_enfermedades')
                ->where('idCedula', $id)
                ->delete();

            DB::table('calentadores_atenciones_medicas')
                ->where('idCedula', $id)
                ->delete();

            DB::table('calentadores_cedula_archivos')
                ->where('idCedula', $id)
                ->update([
                    'idUsuarioElimino' => $user->id,
                    'FechaElimino' => date('Y-m-d H:i:s'),
                ]);

            DB::table('calentadores_cedulas')
                ->where('id', $id)
                ->update([
                    'idUsuarioElimino' => $user->id,
                    'FechaElimino' => date('Y-m-d H:i:s'),
                ]);

            DB::commit();

            $response = [
                'success' => true,
                'results' => true,
                'message' => 'Eliminada con éxito',
                'data' => [],
            ];
            return response()->json($response, 200);
        } catch (\Throwable $errors) {
            //dd($errors);
            DB::rollBack();
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

    private function updateSolicitudFromCedula($cedula, $user)
    {
        $params = [
            'FechaSolicitud' => $cedula['FechaSolicitud']
                ? $cedula['FechaSolicitud']
                : null,
            'FolioTarjetaImpulso' => $cedula['FolioTarjetaImpulso']
                ? $cedula['FolioTarjetaImpulso']
                : null,
            'Folio' => $cedula['Folio'] ? $cedula['Folio'] : null,
            'Nombre' => $cedula['Nombre'] ? $cedula['Nombre'] : null,
            'Paterno' => $cedula['Paterno'] ? $cedula['Paterno'] : null,
            'Materno' => $cedula['Materno'] ? $cedula['Materno'] : null,
            'FechaNacimiento' => $cedula['FechaNacimiento']
                ? $cedula['FechaNacimiento']
                : null,
            'Edad' => $cedula['Edad'] ? $cedula['Edad'] : null,
            'Sexo' => $cedula['Sexo'] ? $cedula['Sexo'] : null,
            'idEntidadNacimiento' => $cedula['idEntidadNacimiento']
                ? $cedula['idEntidadNacimiento']
                : null,
            'CURP' => $cedula['CURP'] ? $cedula['CURP'] : null,
            'RFC' => $cedula['RFC'] ? $cedula['RFC'] : null,
            'idEstadoCivil' => $cedula['idEstadoCivil']
                ? $cedula['idEstadoCivil']
                : null,
            'idParentescoJefeHogar' => $cedula['idParentescoJefeHogar']
                ? $cedula['idParentescoJefeHogar']
                : null,
            'NumHijos' => $cedula['NumHijos'] ? $cedula['NumHijos'] : null,
            'NumHijas' => $cedula['NumHijas'] ? $cedula['NumHijas'] : null,
            'ComunidadIndigena' => $cedula['ComunidadIndigena']
                ? $cedula['ComunidadIndigena']
                : null,
            'Dialecto' => $cedula['Dialecto'] ? $cedula['Dialecto'] : null,
            'Afromexicano' => $cedula['Afromexicano'] ?: null,
            'idSituacionActual' => $cedula['idSituacionActual'] ?: null,
            'TarjetaImpulso' => $cedula['TarjetaImpulso'] ?: null,
            'ContactoTarjetaImpulso' =>
                $cedula['ContactoTarjetaImpulso'] ?: null,
            'Celular' => $cedula['Celular'] ?: null,
            'Telefono' => $cedula['Telefono'] ? $cedula['Telefono'] : null,
            'TelRecados' => $cedula['TelRecados']
                ? $cedula['TelRecados']
                : null,
            'Correo' => $cedula['Correo'] ?: null,
            'idParentescoTutor' => $cedula['idParentescoTutor']
                ? $cedula['idParentescoTutor']
                : null,
            'NombreTutor' => $cedula['NombreTutor']
                ? $cedula['NombreTutor']
                : null,
            'PaternoTutor' => $cedula['PaternoTutor']
                ? $cedula['PaternoTutor']
                : null,
            'MaternoTutor' => $cedula['MaternoTutor']
                ? $cedula['MaternoTutor']
                : null,
            'FechaNacimientoTutor' => $cedula['FechaNacimientoTutor']
                ? $cedula['FechaNacimientoTutor']
                : null,
            'EdadTutor' => $cedula['EdadTutor'] ? $cedula['EdadTutor'] : null,
            'SexoTutor' => $cedula['SexoTutor'] ? $cedula['SexoTutor'] : null,
            'idEntidadNacimientoTutor' => $cedula['idEntidadNacimientoTutor']
                ? $cedula['idEntidadNacimientoTutor']
                : null,
            'CURPTutor' => $cedula['CURPTutor'] ? $cedula['CURPTutor'] : null,
            'TelefonoTutor' => $cedula['TelefonoTutor']
                ? $cedula['TelefonoTutor']
                : null,
            'CorreoTutor' => $cedula['CorreoTutor']
                ? $cedula['CorreoTutor']
                : null,
            'NecesidadSolicitante' => $cedula['NecesidadSolicitante'],
            'CostoNecesidad' => $cedula['CostoNecesidad'] ?: null,
            'idEntidadVive' => $cedula['idEntidadVive'] ?: null,
            'MunicipioVive' => $cedula['MunicipioVive'] ?: null,
            'LocalidadVive' => $cedula['LocalidadVive'] ?: null,
            'CPVive' => $cedula['CPVive'] ?: null,
            'ColoniaVive' => $cedula['ColoniaVive'] ?: null,
            'CalleVive' => $cedula['CalleVive'] ?: null,
            'NoExtVive' => $cedula['NoExtVive'] ?: null,
            'NoIntVive' => $cedula['NoIntVive'] ?: null,
            'Referencias' => $cedula['Referencias'] ?: null,
            'idUsuarioActualizo' => $user->id,
            'FechaActualizo' => date('Y-m-d'),
        ];

        DB::table('calentadores_solicitudes')
            ->where('id', $cedula['idSolicitud'])
            ->update($params);
    }

    private function getFileType($extension)
    {
        if (in_array($extension, ['png', 'jpg', 'jpeg'])) {
            return 'image';
        }
        if (in_array($extension, ['xlsx', 'xls', 'numbers'])) {
            return 'sheet';
        }
        if (in_array($extension, ['doc', 'docx'])) {
            return 'document';
        }
        if ($extension == 'pdf') {
            return 'pdf';
        }
        return 'other';
    }

    private function createCedulaFiles(
        $id,
        $files,
        $clasificationArray,
        $userId
    ) {
        foreach ($files as $key => $file) {
            $originalName = $file->getClientOriginalName();
            $extension = explode('.', $originalName);
            $extension = $extension[count($extension) - 1];
            $uniqueName = uniqid() . '.' . $extension;
            $size = $file->getSize();
            $clasification = $clasificationArray[$key];
            $fileObject = [
                'idCedula' => intval($id),
                'idClasificacion' => intval($clasification),
                'NombreOriginal' => $originalName,
                'NombreSistema' => $uniqueName,
                'Extension' => $extension,
                'Tipo' => $this->getFileType($extension),
                'Tamanio' => $size,
                'idUsuarioCreo' => $userId,
                'FechaCreo' => date('Y-m-d H:i:s'),
            ];
            $file->move('subidos', $uniqueName);
            DB::table('calentadores_cedula_archivos')->insert($fileObject);
        }
    }

    private function updateCedulaFiles(
        $id,
        $files,
        $clasificationArray,
        $userId,
        $oldFilesIds,
        $oldFiles
    ) {
        foreach ($files as $key => $file) {
            $fileAux = json_decode($file);
            $encontrado = array_search($fileAux->id, $oldFilesIds);
            if ($encontrado !== false) {
                if (
                    $oldFiles[$encontrado]->idClasificacion !=
                    $clasificationArray[$key]
                ) {
                    DB::table('calentadores_cedula_archivos')
                        ->where('id', $fileAux->id)
                        ->update([
                            'idClasificacion' => $clasificationArray[$key],
                            'idUsuarioActualizo' => $userId,
                            'FechaActualizo' => date('Y-m-d H:i:s'),
                        ]);
                }
                unset($oldFilesIds[$encontrado]);
            }
        }
        return $oldFilesIds;
    }

    public function enviarIGTO(Request $request)
    {
        $v = Validator::make($request->all(), [
            'id' => 'required',
        ]);
        if ($v->fails()) {
            $response = [
                'success' => true,
                'results' => false,
                'errors' => $v->errors(),
            ];
            return response()->json($response, 200);
        }

        try {
            $params = $request->all();
            $id = $params['id'];
            $folio = DB::table('calentadores_cedulas')
                ->select('Folio')
                ->where('id', $id)
                ->get()
                ->first();
        } catch (Exception $e) {
            $response = [
                'success' => false,
                'results' => false,
                'message' => 'Ocurrio un error al recuperar la cedula',
            ];
            return response()->json($response, 200);
        }
        try {
            if ($folio != null) {
                $urlValidacionFolio =
                    'https://qa-api-utils-ventanilla-impulso.guanajuato.gob.mx/v1/application/external/validate/' .
                    $folio->Folio;
                $client = new Client();
                $response = $client->request('GET', $urlValidacionFolio, [
                    'verify' => false,
                    'headers' => [
                        'Content-Type' => 'multipart/form-data',
                        'Authorization' => '616c818fe33268648502f962',
                    ],
                ]);

                $responseBody = json_decode($response->getBody());
                if (!$responseBody->success) {
                    $response = [
                        'success' => true,
                        'results' => false,
                        'message' => 'El Folio de la cedula no es válido',
                    ];
                    return response()->json($response, 200);
                }
            } else {
                $response = [
                    'success' => true,
                    'results' => false,
                    'message' =>
                        'La cedula no cuenta con Folio, revise su información',
                ];
                return response()->json($response, 200);
            }
        } catch (Exception $e) {
            $response = [
                'success' => false,
                'results' => false,
                'message' =>
                    'Ocurrio un error al validar el Folio de la cedula',
            ];
            return response()->json($response, 200);
        }

        $cedula = DB::table('calentadores_cedulas as cedulas')
            ->selectRaw(
                "
                        cedulas.*, cat_estado_civil.EstadoCivil, 
                        cat_parentesco_jefe_hogar.Parentesco AS ParentescoJefeHogar,
                        cat_situacion_actual.Situacion AS SituacionActual, 
                        entidadNacimiento.Entidad AS EntidadNacimiento, 
                        entidadVive.Entidad AS EntidadVive,
                        cat_parentesco_tutor.Parentesco AS ParentescoTutor,
                        gradoEducacion.Grado AS GradoEducacion,
                        nivelesEducacion.Nivel AS NivelEducacion,
                        actividades.Actividad,
                        viviendas.Tipo AS TipoVivienda,
                        pisos.Piso,
                        muros.Muro,
                        techos.Techo,
                        aguas.Agua,
                        drenajes.Drenaje,
                        luz.Luz,
                        combustibles.Combustible
                    "
            )
            ->join(
                'cat_estado_civil',
                'cat_estado_civil.id',
                'cedulas.idEstadoCivil'
            )
            ->join(
                'cat_parentesco_jefe_hogar',
                'cat_parentesco_jefe_hogar.id',
                'cedulas.idParentescoJefeHogar'
            )
            ->join(
                'cat_situacion_actual',
                'cat_situacion_actual.id',
                'cedulas.idSituacionActual'
            )
            ->leftJoin(
                'cat_parentesco_tutor',
                'cat_parentesco_tutor.id',
                'cedulas.idParentescoTutor'
            )
            ->join(
                'cat_entidad AS entidadNacimiento',
                'entidadNacimiento.id',
                'cedulas.idEntidadNacimiento'
            )
            ->join(
                'cat_entidad AS entidadVive',
                'entidadVive.id',
                'cedulas.idEntidadVive'
            )
            ->join(
                'cat_grados_educacion AS gradoEducacion',
                'gradoEducacion.id',
                'cedulas.idGradoEscuela'
            )
            ->join(
                'cat_niveles_educacion AS nivelesEducacion',
                'nivelesEducacion.id',
                'cedulas.idNivelEscuela'
            )
            ->join(
                'cat_actividades AS actividades',
                'actividades.id',
                'cedulas.idActividades'
            )
            ->join(
                'cat_tipos_viviendas AS viviendas',
                'viviendas.id',
                'cedulas.idTipoVivienda'
            )
            ->join('cat_tipos_pisos AS pisos', 'pisos.id', 'cedulas.idTipoPiso')
            ->join(
                'cat_tipos_muros AS muros',
                'muros.id',
                'cedulas.idTipoParedes'
            )
            ->join(
                'cat_tipos_techos AS techos',
                'techos.id',
                'cedulas.idTipoTecho'
            )
            ->join('cat_tipos_agua AS aguas', 'aguas.id', 'cedulas.idTipoAgua')
            ->join(
                'cat_tipos_drenajes AS drenajes',
                'drenajes.id',
                'cedulas.idTipoDrenaje'
            )
            ->join('cat_tipos_luz AS luz', 'luz.id', 'cedulas.idTipoLuz')
            ->join(
                'cat_tipos_combustibles AS combustibles',
                'combustibles.id',
                'cedulas.idTipoCombustible'
            )
            ->where('cedulas.id', $id)
            ->first();

        if (!isset($cedula->MunicipioVive) || !isset($cedula->LocalidadVive)) {
            $response = [
                'success' => true,
                'results' => false,
                'message' =>
                    'La solicitud no cuenta con municipio o localidad, Revise su información',
            ];
            return response()->json($response, 200);
        }

        $seguros = DB::table('calentadores_atenciones_medicas')
            ->where('idCedula', $id)
            ->get();
        $seguros = array_map(function ($o) {
            return $o->idAtencionMedica;
        }, $seguros->toArray());

        $enfermedades = DB::table('calentadores_enfermedades')
            ->where('idCedula', $id)
            ->get();
        $enfermedades = array_map(function ($o) {
            return $o->idEnfermedad;
        }, $enfermedades->toArray());

        $prestaciones = DB::table('calentadores_prestaciones')
            ->where('idCedula', $id)
            ->get();
        $prestaciones = array_map(function ($o) {
            return $o->idPrestacion;
        }, $prestaciones->toArray());

        $files = DB::table('calentadores_cedula_archivos')
            ->select(
                'calentadores_cedula_archivos.*',
                'calentadores_cedula_archivos.Clasificacion'
            )
            ->join(
                'calentadores_cedula_archivos',
                'calentadores_cedula_archivos.id',
                'calentadores_cedula_archivos.idClasificacion'
            )
            ->where('idCedula', $id)
            ->whereIn('cedula_archivos_clasificacion.id', [3, 6, 4, 7])
            ->whereRaw('calentadores_cedula_archivos.FechaElimino IS NULL')
            ->get();

        $filesEspecifico = DB::table('calentadores_cedula_archivos')
            ->select(
                'calentadores_cedula_archivos.*',
                'calentadores_cedula_archivos.Clasificacion'
            )
            ->join(
                'calentadores_cedula_archivos',
                'calentadores_cedula_archivos.id',
                'calentadores_cedula_archivos.idClasificacion'
            )
            ->where('idCedula', $id)
            ->where('cedula_archivos_clasificacion.id', '10')
            ->whereRaw('calentadores_cedula_archivos.FechaElimino IS NULL')
            ->get();

        $solicitudJson = $this->formatSolicitudIGTOJson($cedula);
        if (!$solicitudJson['success']) {
            $response = [
                'success' => true,
                'results' => false,
                'errors' => $solicitudJson['error'],
                'message' => $solicitudJson['error'],
            ];
            return response()->json($response, 200);
        }

        $solicitudJson = $solicitudJson['data'];
        $catalogs = [
            'seguros' => $seguros,
            'enfermedades' => $enfermedades,
            'prestaciones' => $prestaciones,
        ];
        $cedulaJson = $this->formatCedulaIGTOJson($cedula, $catalogs);
        $formatedFiles = $this->formatArchivos($files, 1);
        $infoFiles = $this->getInfoArchivos($files, 1);
        $formatedFilesEspecifico = $this->formatArchivos($filesEspecifico, 2);
        $infoFilesEspecifico = $this->getInfoArchivos($filesEspecifico, 2);

        $infoFiles = array_merge($infoFiles, $infoFilesEspecifico);

        $programa = json_encode(
            [
                'dependencia' => [
                    'sociedad' => '',
                    'codigo' => '0005',
                    'nombre' => 'SECRETARÍA DE DESARROLLO SOCIAL Y HUMANO',
                    'siglas' => 'SDSH',
                    'eje' => [
                        'codigo' => 'II',
                        'descripcion' => 'Desarrollo Humano y Social',
                    ],
                ],
                'programa' => [
                    'q' => 'Q1417',
                    'nombre' => 'Calentadores Solares',
                    'modalidad' => [
                        'nombre' => 'Implementación de calentadores solares',
                        'clave' => 'Q1417-01',
                    ],
                    'tipoApoyo' => [
                        'clave' => 'Q1417-01-01',
                        'nombre' =>
                            'Otorgamiento de un calentador solar de agua por vivienda',
                    ],
                ],
            ],
            JSON_UNESCAPED_UNICODE
        );

        $docs = json_encode(
            [
                'estandar' => $formatedFiles,
                'especifico' => $formatedFilesEspecifico,
            ],
            JSON_UNESCAPED_UNICODE
        );

        $cUsuario = json_encode(
            [
                'nombre' => $cedula->Enlace == null ? '' : $cedula->Enlace,
                'observaciones' => '',
            ],
            JSON_UNESCAPED_UNICODE
        );

        $authUsuario = json_encode(
            [
                'uid' => '6197eb799c1fce80af39a6d1',
                'name' =>
                    'Daniela Isabel Hernandez Villafuerte (RESPONSABLE Q)', //Cambiar a sedeshu
                'email' => 'dihernandezv@guanajuato.gob.mx',
                'role' => [
                    'key' => 'RESPONSABLE_Q_ROL',
                    'name' => 'Rol Responsable Programa VIM',
                ],
                'dependency' => [
                    'name' =>
                        'Secretaría de Medio Ambiente y Ordenamiento Territorial',
                    'acronym' => 'SMAOT',
                    'office' => [
                        'address' =>
                            'BLVD. JUAN ALONSO DE TORRES 1315 LOCAL 20 PREDIO SAN JOSÉ DEL CONSULEO C.P.37200 LEON',
                        'name' => 'San José de Cervera',
                        'georef' => [
                            'type' => 'Point',
                            'coordinates' => [21.146803, -101.647187],
                        ],
                    ],
                ],
            ],
            JSON_UNESCAPED_UNICODE
        );

        $dataCompleted = [
            'solicitud' => $solicitudJson['solicitud'],
            'programa' => $programa,
            'cedula' => $cedulaJson,
            'documentos' => $docs,
            'authUsuario' => $authUsuario,
            'campoUsuario' => $cUsuario,
        ];
        //dd($dataCompleted);
        $request2 = new HTTP_Request2();
        $request2->setUrl(
            'https://qa-api-utils-ventanilla-impulso.guanajuato.gob.mx/v1/application/external/cedula/register'
        );
        $request2->setMethod(HTTP_Request2::METHOD_POST);
        $request2->setConfig([
            'follow_redirects' => true,
            'ssl_verify_peer' => false,
            'ssl_verify_host' => false,
        ]);
        $request2->setHeader([
            'Authorization' => '616c818fe33268648502g834',
        ]);
        $request2->addPostParameter($dataCompleted);

        foreach ($infoFiles as $file) {
            $request2->addUpload(
                $file['llave'],
                $file['ruta'],
                $file['nombre'],
                $file['header']
            );
        }

        try {
            $response = $request2->send();
            $message = json_decode($response->getBody());
            if ($response->getStatus() == 200) {
                try {
                    DB::table('calentadores_solicitudes')
                        ->where('id', $cedula->idSolicitud)
                        ->update([
                            'idEstatus' => '8',
                        ]);

                    DB::table('calentadores_cedulas')
                        ->where('id', $index)
                        ->update([
                            'idEstatus' => '8',
                        ]);
                    return [
                        'success' => true,
                        'results' => true,
                        'message' => 'Enviada Correctamente',
                    ];
                    return response()->json($response2, 200);
                } catch (Exception $e) {
                    $response2 = [
                        'success' => true,
                        'results' => false,
                        'errors' => $e->errors,
                        'message' =>
                            'La Cedula fue enviada pero hubo un problema al actualizar el estatus',
                    ];
                    return response()->json($response2, 200);
                }
            } else {
                $response2 = [
                    'success' => true,
                    'results' => false,
                    'errors' => $message,
                    'message' =>
                        'Ha ocurrido un error, consulte al administrador',
                ];
                return response()->json($response2, 200);
            }
        } catch (HTTP_Request2_Exception $e) {
            $response2 = [
                'success' => true,
                'results' => false,
                'errors' => $message,
                'message' => 'Ha ocurrido un error, consulte al administrador',
            ];
            return response()->json($response2, 200);
        }
    }

    private function formatSolicitudIGTOJson($solicitud)
    {
        $client = new Client(); //GuzzleHttp\Client
        $url =
            'https://seguimiento.guanajuato.gob.mx/apiinformacionsocial/api/renapo/porcurp/pL@t_1n|Run$28/' .
            $solicitud->CURP .
            '/7';
        $response = $client->request('GET', $url, [
            'verify' => false,
        ]);
        $responseBody = json_decode($response->getBody());
        if ($responseBody->Mensaje !== 'OK') {
            return [
                'success' => false,
                'error' =>
                    $responseBody->Mensaje .
                    ' - ' .
                    $solicitud->CURP .
                    ' - ' .
                    $solicitud->Folio,
            ];
        }
        $curp = $responseBody->Resultado;

        $json = [
            'solicitud' => json_encode(
                [
                    'tipoSolicitud' => 'Ciudadana',
                    'origen' => 'F',
                    'tutor' => [
                        'respuesta' => $solicitud->idParentescoTutor != null,
                    ],
                    'datosCurp' => [
                        'folio' => $solicitud->Folio,
                        'curp' => $solicitud->CURP,
                        'entidadNacimiento' => $solicitud->EntidadNacimiento,
                        'fechaNacimientoDate' => date(
                            $solicitud->FechaNacimiento
                        ),
                        'fechaNacimientoTexto' => $solicitud->FechaNacimiento,
                        'genero' => $solicitud->Sexo,
                        'nacionalidad' => $curp->nacionalidad,
                        'nombre' => $solicitud->Nombre,
                        'primerApellido' =>
                            $solicitud->Paterno != 'XX'
                                ? $solicitud->Paterno
                                : 'X',
                        'segundoApellido' =>
                            $solicitud->Materno != 'XX'
                                ? $solicitud->Materno
                                : 'X',
                        'anioRegistro' => $curp->anioReg,
                        'descripcion' => $solicitud->NecesidadSolicitante,
                        'costoAproximado' => $solicitud->CostoNecesidad,
                    ],
                    'datosComplementarios' => [
                        'estadoCivil' => $solicitud->EstadoCivil,
                        'parentescoJefeHogar' => [
                            'codigo' =>
                                $solicitud->idParentescoJefeHogar < 3
                                    ? $solicitud->idParentescoJefeHogar
                                    : $solicitud->idParentescoJefeHogar - 1,
                            'descripcion' => $solicitud->ParentescoJefeHogar,
                        ],
                        'migrante' => [
                            'respuesta' =>
                                $solicitud->idSituacionActual !== 5 &&
                                $solicitud->idSituacionActual !== null &&
                                $solicitud->idSituacionActual !== 0,
                            'codigo' =>
                                $solicitud->idSituacionActual !== 5
                                    ? $solicitud->idSituacionActual
                                    : 0,
                            'descripcion' =>
                                $solicitud->idSituacionActual !== 5
                                    ? $solicitud->SituacionActual
                                    : 'No Aplica',
                        ],
                        'afroMexicano' => $solicitud->Afromexicano > 0,
                        'comunidadIndigena' => [
                            'respuesta' =>
                                $solicitud->ComunidadIndigena !== null,
                            'codigo' => 0,
                            'descripcion' =>
                                $solicitud->ComunidadIndigena !== null
                                    ? $solicitud->ComunidadIndigena
                                    : '',
                        ],
                        'hablaDialecto' => [
                            'respuesta' => $solicitud->Dialecto !== null,
                            'codigo' => 0,
                            'descripcion' =>
                                $solicitud->Dialecto !== null
                                    ? $solicitud->Dialecto
                                    : '',
                        ],
                        'tieneHijos' => [
                            'respuesta' =>
                                $solicitud->NumHijos > 0 ||
                                $solicitud->NumHijas > 0,
                            'descripcion' => [
                                'hombres' => $solicitud->NumHijos,
                                'mujeres' => $solicitud->NumHijas,
                            ],
                        ],
                    ],
                    'datosContacto' => [
                        'telefonos' => $this->getTelefonos($solicitud),
                        'correos' => is_null($this->getCorreos($solicitud))
                            ? []
                            : $this->getCorreos($solicitud),
                        'cp' => $solicitud->CPVive,
                        'colonia ' => $solicitud->ColoniaVive,
                        'numeroExt' => $solicitud->NoExtVive,
                        'numeroInt' => $solicitud->NoIntVive,
                        'entidadFederativa' => $solicitud->EntidadVive,
                        'localidad' => $solicitud->LocalidadVive,
                        'municipio' => $solicitud->MunicipioVive,
                        'calle' => $solicitud->CalleVive,
                        'referencias' => $solicitud->Referencias,
                        'solicitudImpulso' => $solicitud->TarjetaImpulso == 1,
                        'autorizaContacto' =>
                            $solicitud->ContactoTarjetaImpulso == 1,
                    ],
                ],
                JSON_UNESCAPED_UNICODE
            ),
        ];

        return ['success' => true, 'data' => $json];
    }

    private function getTelefonos($solicitud)
    {
        $telefonos = [];
        if ($solicitud->Celular != null) {
            array_push($telefonos, [
                'tipo' => 'Celular',
                'descripcion' => $solicitud->Celular,
            ]);
        }
        if ($solicitud->Telefono != null) {
            array_push($telefonos, [
                'tipo' => 'Teléfono de Casa',
                'descripcion' => $solicitud->Telefono,
            ]);
        }
        if ($solicitud->TelRecados != null) {
            array_push($telefonos, [
                'tipo' => 'Teléfono de recados',
                'descripcion' => $solicitud->TelRecados,
            ]);
        }
        if ($solicitud->TelefonoTutor != null) {
            array_push($telefonos, [
                'tipo' => 'Teléfono del tutor',
                'descripcion' => $solicitud->TelefonoTutor,
            ]);
        }
        return $telefonos;
    }

    private function getCorreos($solicitud)
    {
        $correos = [];
        if ($solicitud->Correo) {
            array_push($correos, [
                'tipo' => 'Personal',
                'descripcion' => $solicitud->Correo,
            ]);
        }
        if ($solicitud->CorreoTutor) {
            array_push($correos, [
                'tipo' => 'Corrreo del tutor',
                'descripcion' => $solicitud->CorreoTutor,
            ]);
        }
    }

    private function formatCedulaIGTOJson($cedula, $catalogs)
    {
        $periodicidades = DB::table('cat_periodicidad')->get();

        $json = json_encode(
            [
                'solicitudImpulso' => true,
                'cedulaImpulso' => true,
                'datosHogar' => [
                    'numeroHogares' => $cedula->TotalHogares,
                    'integrantesMujer' => $cedula->NumeroMujeresHogar,
                    'integrantesHombre' => $cedula->NumeroHombresHogar,
                    'menores18' => $cedula->PersonasMayoresEdad > 0,
                    'mayores65' => $cedula->PersonasTerceraEdad > 0,
                    'hombreJefeFamilia' => $cedula->PersonaJefaFamilia == 'H',
                ],
                'datosSalud' => [
                    'limitacionMental' => $cedula->DificultadMental == 1,
                    'servicioMedico' => [
                        [
                            [
                                'respuesta' => in_array(
                                    1,
                                    $catalogs['seguros']
                                ),
                                'codigo' => 1,
                                'descripcion' => 'Seguro Social IMSS',
                            ],
                            [
                                'respuesta' => in_array(
                                    2,
                                    $catalogs['seguros']
                                ),
                                'codigo' => 2,
                                'descripcion' =>
                                    'IMSS facultativo para estudiantes',
                            ],
                            [
                                'respuesta' => in_array(
                                    3,
                                    $catalogs['seguros']
                                ),
                                'codigo' => 3,
                                'descripcion' => 'ISSSTE',
                            ],
                            [
                                'respuesta' => in_array(
                                    4,
                                    $catalogs['seguros']
                                ),
                                'codigo' => 4,
                                'descripcion' => 'ISSSTE Estatal',
                            ],
                            [
                                'respuesta' => in_array(
                                    5,
                                    $catalogs['seguros']
                                ),
                                'codigo' => 5,
                                'descripcion' => 'PEMEX, Defensa o Marina',
                            ],
                            [
                                'respuesta' => in_array(
                                    6,
                                    $catalogs['seguros']
                                ),
                                'codigo' => 6,
                                'descripcion' =>
                                    'INSABI (antes Seguro Popular)',
                            ],
                            [
                                'respuesta' => in_array(
                                    7,
                                    $catalogs['seguros']
                                ),
                                'codigo' => 7,
                                'descripcion' => 'Seguro Privado',
                            ],
                            [
                                'respuesta' => in_array(
                                    8,
                                    $catalogs['seguros']
                                ),
                                'codigo' => 8,
                                'descripcion' => 'En otra institución',
                            ],
                            [
                                'respuesta' => in_array(
                                    9,
                                    $catalogs['seguros']
                                ),
                                'codigo' => 9,
                                'descripcion' =>
                                    'No tienen derecho a servicios médicos',
                            ],
                        ],
                    ],
                    'enfermedadCronica' => [
                        [
                            'respuesta' => in_array(
                                1,
                                $catalogs['enfermedades']
                            ),
                            'codigo' => 1,
                            'descripcion' => 'Artritis Reumatoide',
                        ],
                        [
                            'respuesta' => in_array(
                                2,
                                $catalogs['enfermedades']
                            ),
                            'codigo' => 2,
                            'descripcion' => 'Cáncer',
                        ],
                        [
                            'respuesta' => in_array(
                                3,
                                $catalogs['enfermedades']
                            ),
                            'codigo' => 3,
                            'descripcion' => 'Cirrosis Hepática',
                        ],
                        [
                            'respuesta' => in_array(
                                4,
                                $catalogs['enfermedades']
                            ),
                            'codigo' => 4,
                            'descripcion' => 'Insuficiencia Renal',
                        ],
                        [
                            'respuesta' => in_array(
                                5,
                                $catalogs['enfermedades']
                            ),
                            'codigo' => 5,
                            'descripcion' => 'Diabetes Mellitus',
                        ],
                        [
                            'respuesta' => in_array(
                                6,
                                $catalogs['enfermedades']
                            ),
                            'codigo' => 6,
                            'descripcion' => 'Cardiopatías',
                        ],
                        [
                            'respuesta' => in_array(
                                7,
                                $catalogs['enfermedades']
                            ),
                            'codigo' => 7,
                            'descripcion' => 'Enfermedad Pulmonar Crónica',
                        ],
                        [
                            'respuesta' => in_array(
                                8,
                                $catalogs['enfermedades']
                            ),
                            'codigo' => 8,
                            'descripcion' =>
                                'Deficiencia nutricional (Desnutrición)',
                        ],
                        [
                            'respuesta' => in_array(
                                9,
                                $catalogs['enfermedades']
                            ),
                            'codigo' => 9,
                            'descripcion' => 'Hipertensión Arterial',
                        ],
                        [
                            'respuesta' => in_array(
                                10,
                                $catalogs['enfermedades']
                            ),
                            'codigo' => 10,
                            'descripcion' => 'Obesidad',
                        ],
                        [
                            'respuesta' => in_array(
                                11,
                                $catalogs['enfermedades']
                            ),
                            'codigo' => 11,
                            'descripcion' =>
                                'Adicción a la Ingestión de Sustancias (Drogas)',
                        ],
                        [
                            'respuesta' => in_array(
                                12,
                                $catalogs['enfermedades']
                            ),
                            'codigo' => 12,
                            'descripcion' =>
                                'Adicciones de la conducta (Juego, internet)',
                        ],
                        [
                            'respuesta' => in_array(
                                13,
                                $catalogs['enfermedades']
                            ),
                            'codigo' => 13,
                            'descripcion' => 'Depresión',
                        ],
                        [
                            'respuesta' => in_array(
                                14,
                                $catalogs['enfermedades']
                            ),
                            'codigo' => 14,
                            'descripcion' => 'Ansiedad',
                        ],
                        [
                            'respuesta' => in_array(
                                15,
                                $catalogs['enfermedades']
                            ),
                            'codigo' => 15,
                            'descripcion' => 'Trasplante de Órganos',
                        ],
                        [
                            'respuesta' => in_array(
                                16,
                                $catalogs['enfermedades']
                            ),
                            'codigo' => 16,
                            'descripcion' => 'Ninguna',
                        ],
                    ],
                ],
                'datosEducacion' => [
                    'estudiante' => $cedula->AsisteEscuela == 1,
                    'ultimoNivel' => [
                        'codigo' => $cedula->idNivelEscuela,
                        'descripcion' => $cedula->NivelEducacion,
                    ],
                    'grado' => [
                        'codigo' =>
                            $cedula->idGradoEscuela == 7
                                ? 0
                                : $cedula->idGradoEscuela,
                        'descripcion' => $cedula->GradoEducacion,
                    ],
                ],
                'datosIngreso' => [
                    'situacionEmpleo' => [
                        'codigo' => $cedula->idActividades,
                        'descripcion' => $cedula->Actividad,
                    ],
                    'prestacionesTrabajo' => [
                        [
                            'respuesta' => in_array(
                                1,
                                $catalogs['prestaciones']
                            ),
                            'codigo' => 1,
                            'descripcion' =>
                                'Incapacidad en caso de enfermedad, accidente o maternidad',
                        ],
                        [
                            'respuesta' => in_array(
                                2,
                                $catalogs['prestaciones']
                            ),
                            'codigo' => 2,
                            'descripcion' => 'Aguinaldo',
                        ],
                        [
                            'respuesta' => in_array(
                                3,
                                $catalogs['prestaciones']
                            ),
                            'codigo' => 3,
                            'descripcion' => 'Crédito de vivienda',
                        ],
                        [
                            'respuesta' => in_array(
                                4,
                                $catalogs['prestaciones']
                            ),
                            'codigo' => 4,
                            'descripcion' =>
                                'Guarderías y estancias infantiles',
                        ],
                        [
                            'respuesta' => in_array(
                                5,
                                $catalogs['prestaciones']
                            ),
                            'codigo' => 5,
                            'descripcion' => 'SAR o AFORE',
                        ],
                        [
                            'respuesta' => in_array(
                                6,
                                $catalogs['prestaciones']
                            ),
                            'codigo' => 6,
                            'descripcion' => 'Seguro de vida',
                        ],
                        [
                            'respuesta' => in_array(
                                7,
                                $catalogs['prestaciones']
                            ),
                            'codigo' => 7,
                            'descripcion' =>
                                'No tienen prestaciones provenientes de su trabajo',
                        ],
                    ],
                    'totalIngreso' => $cedula->IngresoTotalMesPasado,
                    'totalPension' => $cedula->PensionMensual,
                    'totalRemesa' => $cedula->IngresoOtrosPaises,
                ],
                'datosAlimentacion' => [
                    'pocaVariedadAlimento' =>
                        $cedula->AlimentacionPocoVariada == 1,
                    'comioMenos' => $cedula->ComioMenos == 1,
                    'disminuyoCantidad' => $cedula->DisminucionComida == 1,
                    'tuvoHambreNoComio' => $cedula->NoComio == 1,
                    'durmioConHambre' => $cedula->DurmioHambre == 1,
                    'comioUnaVezoNo' => $cedula->DejoComer == 1,
                ],
                'discapacidad' => [
                    'movilidadInferior' => $cedula->DificultadMovilidad,
                    'visual' => $cedula->DificultadVer,
                    'habla' => $cedula->DificultadHablar,
                    'auditivo' => $cedula->DificultadOir,
                    'valerse' => $cedula->DificultadVestirse,
                    'memoria' => $cedula->DificultadRecordar,
                    'movilidadSuperior' => $cedula->DificultadBrazos,
                ],
                'datosGasto' => [
                    'comida' => [
                        'gasto' => $cedula->GastoAlimentos,
                        'periodo' => [
                            'codigo' => $cedula->PeriodicidadAlimentos,
                            'descripcion' => $periodicidades
                                ->where('id', $cedula->PeriodicidadAlimentos)
                                ->first()->Periodicidad,
                        ],
                    ],
                    'ropa' => [
                        'gasto' => $cedula->GastoVestido,
                        'periodo' => [
                            'codigo' => $cedula->PeriodicidadVestido,
                            'descripcion' => $periodicidades
                                ->where('id', $cedula->PeriodicidadVestido)
                                ->first()->Periodicidad,
                        ],
                    ],
                    'educacion' => [
                        'gasto' => $cedula->GastoEducacion,
                        'periodo' => [
                            'codigo' => $cedula->PeriodicidadEducacion,
                            'descripcion' => $periodicidades
                                ->where('id', $cedula->PeriodicidadEducacion)
                                ->first()->Periodicidad,
                        ],
                    ],
                    'medicina' => [
                        'gasto' => $cedula->GastoMedicinas,
                        'periodo' => [
                            'codigo' => $cedula->PeriodicidadMedicinas,
                            'descripcion' => $periodicidades
                                ->where('id', $cedula->PeriodicidadMedicinas)
                                ->first()->Periodicidad,
                        ],
                    ],
                    'consultas' => [
                        'gasto' => $cedula->GastosConsultas,
                        'periodo' => [
                            'codigo' => $cedula->PeriodicidadConsultas,
                            'descripcion' => $periodicidades
                                ->where('id', $cedula->PeriodicidadConsultas)
                                ->first()->Periodicidad,
                        ],
                    ],
                    'combustible' => [
                        'gasto' => $cedula->GastosCombustibles,
                        'periodo' => [
                            'codigo' => $cedula->PeriodicidadCombustibles,
                            'descripcion' => $periodicidades
                                ->where('id', $cedula->PeriodicidadCombustibles)
                                ->first()->Periodicidad,
                        ],
                    ],
                    'serviciosBasicos' => [
                        'gasto' => $cedula->GastosServiciosBasicos,
                        'periodo' => [
                            'codigo' => $cedula->PeriodicidadServiciosBasicos,
                            'descripcion' => $periodicidades
                                ->where(
                                    'id',
                                    $cedula->PeriodicidadServiciosBasicos
                                )
                                ->first()->Periodicidad,
                        ],
                    ],
                    'recreacion' => [
                        'gasto' => $cedula->GastosServiciosRecreacion,
                        'periodo' => [
                            'codigo' =>
                                $cedula->PeriodicidadServiciosRecreacion,
                            'descripcion' => $periodicidades
                                ->where(
                                    'id',
                                    $cedula->PeriodicidadServiciosRecreacion
                                )
                                ->first()->Periodicidad,
                        ],
                    ],
                ],
                'datosVivienda' => [
                    'estatusVivienda' => [
                        'codigo' => $cedula->idTipoVivienda,
                        'descripcion' => $cedula->TipoVivienda,
                    ],
                    'materialPiso' => [
                        'codigo' => $cedula->idTipoPiso,
                        'descripcion' => $cedula->Piso,
                    ],
                    'materialPared' => [
                        'codigo' => $cedula->idTipoParedes,
                        'descripcion' => $cedula->Muro,
                    ],
                    'materialTecho' => [
                        'codigo' => $cedula->idTipoTecho,
                        'descripcion' => $cedula->Techo,
                    ],
                    'fuenteAgua' => [
                        'codigo' => $cedula->idTipoAgua,
                        'descripcion' => $cedula->Agua,
                    ],
                    'drenaje' => [
                        'codigo' => $cedula->idTipoDrenaje,
                        'descripcion' => $cedula->Drenaje,
                    ],
                    'fuenteLuzElectrica' => [
                        'codigo' => $cedula->idTipoLuz,
                        'descripcion' => $cedula->Luz,
                    ],
                    'combustibleCocina' => [
                        'codigo' => $cedula->idTipoCombustible,
                        'descripcion' => $cedula->Combustible,
                    ],
                    'numeroCuartos' => $cedula->CuartosHogar,
                    'numeroPersonaHabitantes' => $cedula->PersonasHogar,
                ],
                'datosEnseres' => [
                    'refrigerador' => $cedula->Refrigerador == 1,
                    'lavadora' => $cedula->Lavadora == 1,
                    'computadora' => $cedula->Computadora == 1,
                    'estufa' => $cedula->Estufa == 1,
                    'boiler' => $cedula->Calentador == 1,
                    'calentadorSolar' => $cedula->CalentadorSolar == 1,
                    'tv' => $cedula->Television == 1,
                    'internet' => $cedula->Internet == 1,
                    'celular' => $cedula->TieneTelefono == 1,
                    'tinaco' => $cedula->Tinaco == 1,
                ],
                'percepcionSeguridad' => $cedula->ColoniaSegura == 1,
            ],
            JSON_UNESCAPED_UNICODE
        );

        return $json;
    }

    private function formatArchivos($archivos, $index)
    {
        $files = [];
        $formato = '';
        if ($index == 1) {
            $formato = 'estandar';
        } else {
            $formato = 'especifico';
        }
        foreach ($archivos as $file) {
            //$fileConverted = fopen('subidos/' . $file->NombreSistema, 'r');
            $formatedFile = [
                'llave' =>
                    $formato . '_' . str_replace('.', '', $file->Clasificacion),
                'nombre' => $file->Clasificacion,
                'uid' => '',
                'vigencia' => '',
            ];

            array_push($files, $formatedFile);
        }
        return $files;
    }

    private function getInfoArchivos($archivos, $index)
    {
        $files = [];
        $formato = '';
        if ($index == 1) {
            $formato = 'estandar';
        } else {
            $formato = 'especifico';
        }
        foreach ($archivos as $file) {
            $fileContent = fopen('subidos/' . $file->NombreSistema, 'r');
            $formatedFile = [
                'llave' =>
                    $formato . '_' . str_replace('.', '', $file->Clasificacion),
                'ruta' =>
                    '/var/www/html/plataforma/apivales/public/subidos/' .
                    //'/Users/diegolopez/Documents/GitProyect/vales/apivales/public/subidos/' .
                    $file->NombreSistema,
                //'content' => $fileContent,
                'nombre' => str_replace('.', '', $file->Clasificacion),
                'header' => '<Content-Type Header>',
            ];
            array_push($files, $formatedFile);
        }
        return $files;
    }

    public function uploadFilesCalentadores(Request $request)
    {
        $v = Validator::make($request->all(), [
            'id' => 'required',
            'NewFiles' => 'required',
            'NamesFiles' => 'required',
            'ArrayClasificacion' => 'required',
            'ArrayExtension' => 'required',
        ]);

        if ($v->fails()) {
            $response = [
                'success' => true,
                'results' => false,
                'errors' => $v->errors(),
            ];
            return response()->json($response, 200);
        }

        $params = $request->all();
        $id = $params['id'];
        $files = $params['NewFiles'];
        $arrayClasifiacion = $params['ArrayClasificacion'];
        $fullPath = public_path('/subidos/');
        $extension = $params['ArrayExtension'];
        $names = $params['NamesFiles'];
        try {
            $solicitud = DB::table('calentadores_cedulas')
                ->select('idUsuarioCreo', 'id')
                ->where('calentadores_cedulas.idSolicitud', $id)
                ->first();
            if ($solicitud == null) {
                $response = [
                    'success' => true,
                    'results' => false,
                    'errors' => 'No se encuentra la cedula de calentadores',
                ];
                return response()->json($response, 200);
            }
            foreach ($files as $key => $file) {
                $imageContent = $this->imageBase64Content($file);
                $uniqueName = uniqid() . $extension[$key];
                $clasification = $arrayClasifiacion[$key];
                $originalName = $names[$key];

                File::put($fullPath . $uniqueName, $imageContent);
                $fileObject = [
                    'idCedula' => intval($solicitud->id),
                    'idClasificacion' => intval($clasification),
                    'NombreOriginal' => $originalName,
                    'NombreSistema' => $uniqueName,
                    'Extension' => str_replace('.', '', $extension[$key]),
                    'Tipo' => $this->getFileType(
                        str_replace('.', '', $extension[$key])
                    ),
                    'Tamanio' => '',
                    'idUsuarioCreo' => $solicitud->idUsuarioCreo,
                    'FechaCreo' => date('Y-m-d H:i:s'),
                ];
                $tableArchivos = 'calentadores_cedula_archivos';
                DB::table($tableArchivos)->insert($fileObject);
            }
            $response = [
                'success' => true,
                'results' => true,
                'errors' => 'Archivos cargados con exito',
            ];
            return response()->json($response, 200);
        } catch (QueryException $errors) {
            DB::rollBack();
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

    public function imageBase64Content($image)
    {
        $image = str_replace('data:image/png;base64,', '', $image);
        $image = str_replace(' ', '+', $image);
        return base64_decode($image);
    }
}
