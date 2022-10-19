<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterAuthRequest;
use App\User;
use App\VUsersMenus;
use Illuminate\Http\Request;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Contracts\Validation\ValidationException;
use Validator;
use DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public $loginAfterSignUp = true;

    public function register(Request $request)
    {
        $v = Validator::make($request->all(), [
            'email' => 'required|unique:users',
            'password' => 'required',
            'Nombre' => 'required',
            'Materno' => 'required',
            'Paterno' => 'required',
            'idTipoUser' => 'required',
            //'TelCasa'=>'required',
            //'TelCelular'=>'required',
            'Correo' => 'required',
            //'Calle'=>'required',
            //'NumExt'=>'required',
            //'NumInt'=>'required',
            //'Colonia'=>'required',
            'defaultPage' => 'required',
            'idMunicipio' => 'required',
            'idLocalidad' => 'required',
            'idStatus' => 'required',
            'idPerfil' => 'required',
        ]);
        $parameters = $request->all();
        if ($v->fails()) {
            $response = [
                'success' => false,
                'results' => false,
                'errors' => $v->errors(),
                'data' => [],
            ];

            return response()->json($response, 200);
        }

        //$user = auth()->user();
        /* $usuario_permitido = DB::table('users_apis')
            ->select(
                'idUser',
                'Apis')
                ->where('idUser','=',$user->id)
                ->where('Apis','=','register')->first();
            if(!$usuario_permitido){
                $errors = [
                    "Clave"=>"00"
                ];
                $response = ['success'=>true,'results'=>false, 
                'errors'=>$errors, 'message' =>'Este usuario no cuenta con permisos suficientes para ejecutar esta api.'];
    
                return  response()->json($response, 200);

            }
        
		if ($v->fails()){
            $response =  ['success'=>false,'results'=>false,
            'errors'=>$v->errors(),'data'=>[]];

            return response()->json($response,200);
        } */

        $user = new User();
        $user->email = $request->email;
        $user->password = bcrypt($request->password);
        $user->Nombre = $request->Nombre;
        $user->Paterno = $request->Paterno;
        $user->Materno = $request->Materno;
        $user->idTipoUser = $request->idTipoUser;
        $user->TelCasa = $request->TelCasa;
        $user->TelCelular = $request->TelCelular;
        $user->Correo = $request->Correo;
        $user->Calle = $request->Calle;
        $user->NumExt = $request->NumExt;
        $user->NumInt = $request->NumInt;
        $user->Colonia = $request->Colonia;
        $user->CP = $request->CP;
        $user->idMunicipio = $request->idMunicipio;
        $user->idLocalidad = $request->idLocalidad;
        $user->DevideID = $request->DevideID;
        $user->DeviceOS = $request->DeviceOS;
        $user->Token = $request->Token;
        $user->idStatus = $request->idStatus;

        $user->save();

        if ($user->idStatus == 1 && $this->loginAfterSignUp) {
            return $this->login($request);
        }

        $response = ['success' => true, 'results' => true, 'data' => $user];

        return response()->json($response, 200);
    }
    public function updateUser(Request $request)
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

        $user = User::find($parameters['id']);
        if (!$user) {
            $response = [
                'success' => true,
                'results' => false,
                'errors' => 'El usuario que desea actualizar no existe.',
            ];

            return response()->json($response, 200);
        }
        $user_loggeado = auth()->user();
        $parameters['UserUpdated'] = $user_loggeado->id;
        $user->update($parameters);

        $response = ['success' => true, 'results' => true, 'data' => $user];

        return response()->json($response, 200);
    }

    function getUsersApp(Request $request)
    {
        $parameters = $request->all();

        try {
            $res = DB::table('users as U')
                ->select(
                    'U.id',
                    'U.email',
                    'U.Nombre',
                    'U.Paterno',
                    'U.Materno',
                    'U.idTipoUser',
                    'UT.TipoUser',
                    'U.TelCasa',
                    'U.TelCelular',
                    'U.Correo',
                    'U.Calle',
                    'U.NumExt',
                    'U.NumInt',
                    'U.Colonia',
                    'U.CP',
                    'U.idMunicipio',
                    'M.Municipio',
                    'U.idLocalidad',
                    'U.defaultPage',
                    'U.DevideID',
                    'U.DeviceOS',
                    'U.Token',
                    'U.Foto64',
                    'U.created_at',
                    'U.updated_at',
                    'U.idStatus'
                )
                ->leftJoin('cat_usertipo as UT', 'U.idTipoUser', '=', 'UT.id')
                ->leftJoin('cat_municipio as M', 'U.idMunicipio', '=', 'M.id')
                ->Where('U.id', '!=', 1);

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

            if (isset($parameters['NombreCompleto'])) {
                $filtro_recibido = $parameters['NombreCompleto'];
                $filtro_recibido = str_replace(' ', '', $filtro_recibido);

                $res->where(
                    DB::raw("REPLACE(CONCAT(
                        M.Municipio,
                        UT.TipoUser,
                        U.email,
                        U.Nombre,
                        U.Paterno,
                        U.Materno,
                        U.Paterno,
                        U.Nombre,
                        U.Materno,
                        U.Paterno,
                        U.Materno,
                        U.Nombre), ' ', '')"),
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
            return [
                'success' => false,
                'results' => false,
                'errors' => $e->getMessage(),
            ];
        }
    }

    public function login(Request $request)
    {
        $input = $request->only('email', 'password');
        $jwt_token = null;
        if (!($jwt_token = JWTAuth::attempt($input))) {
            $errors = [
                'Clave' => '00',
            ];
            $response = [
                'success' => true,
                'results' => false,
                'errors' => $errors,
                'message' => 'Usuario y/o contraseña incorrectas.',
            ];

            return response()->json($response, 200);
        }
        //$user = JWTAuth::authenticate($jwt_token);
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
        $usertipo = DB::table('cat_usertipo')
            ->where('id', '=', $user->idTipoUser)
            ->first();
        $user->idTipoUser = $usertipo;

        $usermunicipios = DB::table('cat_municipio')
            ->where('id', '=', $user->idMunicipio)
            ->first();
        $user->idMunicipio = $usermunicipios;

        $userlocalidad = DB::table('cat_localidades')
            ->where('mapa', '=', $user->idLocalidad)
            ->first();
        $user->idLocalidad = $userlocalidad;

        if ($user->idStatus == 1) {
            $res = DB::table('users_menus')
                ->select(
                    'users_menus.idMenu',
                    'menus.Menu',
                    'menus.path',
                    'menus.Clave',
                    'users_menus.Ver',
                    'users_menus.Agregar',
                    'users_menus.Editar',
                    'users_menus.Eliminar',
                    'users_menus.Seguimiento',
                    'users_menus.Exportar',
                    'users_menus.Imprimir',
                    'users_menus.ViewAll',
                    'users_menus.idUser',
                    'menus.layout',
                    'menus.iconMovil',
                    'menus.iconTipo',
                    'menus.Ordenado'
                )
                ->leftJoin('menus', 'menus.id', '=', 'users_menus.idMenu')
                ->where('users_menus.idUser', '=', $user->id)
                ->OrderBy('menus.Ordenado')
                ->get();

            $array_regiones = [];
            $res_regiones = DB::table('users_region')
                ->select('users_region.Region')
                ->where('users_region.idUser', '=', $user->id)
                ->get();
            for ($i = 0; $i < count($res_regiones); $i++) {
                array_push($array_regiones, $res_regiones[$i]->Region);
            }

            if ($user->idTipoUser->id == 2) {
                $response = [
                    'success' => true,
                    'results' => true,
                    'token' => $jwt_token,
                ];
            } else {
                $response = [
                    'success' => true,
                    'results' => true,
                    'token' => $jwt_token,
                    'user' => $user,
                    'menu' => $res,
                    'regiones' => $array_regiones,
                ];
            }
        } elseif ($user->idStatus == 2) {
            $errors = [
                'Clave' => '01',
            ];
            $response = [
                'success' => true,
                'results' => false,
                'errors' => $errors,
                'message' => 'Cuenta pendiente de autorizar.',
            ];
        } else {
            $errors = [
                'Clave' => '02',
            ];
            $response = [
                'success' => true,
                'results' => false,
                'errors' => $errors,
                'message' => 'Cuenta bloqueada.',
            ];
        }

        return response()->json($response, 200);
    }

    public function logout(Request $request)
    {
        $this->validate($request, [
            'token' => 'required',
        ]);

        try {
            JWTAuth::invalidate($request->token);
            return response()->json([
                'status' => 'ok',
                'message' => 'Cierre de sesión exitoso.',
            ]);
        } catch (JWTException $exception) {
            return response()->json(
                [
                    'status' => 'unknown_error',
                    'message' => 'Al usuario no se le pudo cerrar la sesión.',
                ],
                500
            );
        }
    }

    public function getAuthenticatedUser(Request $request)
    {
        $validatedData = $request->validate([
            'token' => 'required',
        ]);
        if (!$validatedData) {
            return response()->json(['token_absent']);
        }

        $user = JWTAuth::authenticate($request->token);
        return response()->json(['user' => $user]);
    }

    public function updateUserPassword(Request $request)
    {
        $v = Validator::make($request->all(), [
            'password' => 'required|string|min:6',
            'passwordold' => 'required|string|min:6',
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
        $new = $request->get('password');
        $old = $request->get('passwordold');
        $usuario = auth()->user();
        if (Hash::check($old, $usuario->password)) {
            $usuario_db = User::findOrFail($usuario->id);
            $usuario_db->password = bcrypt($new);
            $usuario_db->update();
            $response = [
                'success' => true,
                'results' => true,
                'user' => $usuario_db,
            ];
            return response()->json($response, 200);
        } else {
            $errors = [
                'Clave' => '99',
            ];
            $response = [
                'success' => true,
                'results' => false,
                'errors' => $errors,
                'message' =>
                    'La contraseña no es correcta, debe ingresar la contraseña actual antes de cambiarla.',
            ];
            return response()->json($response, 200);
        }
    }

    public function updatePassword(Request $request)
    {
        $v = Validator::make($request->all(), [
            'id' => 'required',
            'password' => 'required|string|min:6',
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
        $new = $request->get('password');
        $id = $request->get('id');
        $usuario_db = User::findOrFail($id);
        $usuario_db->password = bcrypt($new);
        $usuario_db->update();
        $response = [
            'success' => true,
            'results' => true,
            'user' => $usuario_db,
        ];
        return response()->json($response, 200);
    }

    public function getUsersArticuladores()
    {
        $users = DB::table('users')
            ->where('idTipoUser', '=', 9)
            ->orWhere('idTipoUser', '=', 3)
            ->get();
        $response = ['success' => true, 'results' => true, 'users' => $users];
        return response()->json($response, 200);
    }

    public function getRegionUser(Request $request)
    {
        $users = DB::table('users_region')
            ->selectRaw('Region')
            ->where('idUser', $request->get('id'))
            ->get();
        if (count($users) > 1) {
            $region = -1;
        } else {
            $region = $users;
        }
        $response = ['success' => true, 'results' => true, 'region' => $region];
        return response()->json($response, 200);
    }

    function AgregarMultiples()
    {
        $usuarios_nuevos = DB::table('_personasagregar')->get();
        if (count($usuarios_nuevos) === 0) {
            $response = [
                'success' => true,
                'results' => false,
                'message' =>
                    'No hay datos en la tabla _personasagregar, para generar nuevos usuarios. ',
            ];
            return response()->json($response, 200);
        }
        $contador = 0;
        $array_usuarios_repetidos = [];

        foreach ($usuarios_nuevos as $request) {
            //Celular, password, Nombre, Materno, Paterno,
            //idTipoUser, Correo, defaultPage, idMunicipio,
            //idLocalidad, idStatus, idPerfil
            $user_buscado = User::where(
                'email',
                '=',
                $request->Celular
            )->first();
            if ($user_buscado) {
                array_push($array_usuarios_repetidos, $user_buscado);
            } else {
                //$array_pass = explode("@", $request->Correo);
                $user = new User();
                $user->email = $request->Celular;
                $user->password = bcrypt($request->password); //bcrypt($array_pass[0]);
                $user->Nombre = $request->Nombre;
                $user->Paterno = $request->Paterno;
                $user->Materno = $request->Materno;
                $user->idTipoUser = $request->idTipoUser;
                $user->TelCelular = $request->Celular;
                $user->Correo = $request->Correo;
                $user->Colonia = 'S/C';
                $user->CP = 'S/CP';
                $user->idMunicipio = $request->idMunicipio;
                $user->idLocalidad = $request->idLocalidad;
                $user->defaultPage = $request->defaultPage;
                $user->idStatus = $request->idStatus;
                $user->UserUpdated = 1;
                $user->UserCreated = 1;
                $user->save();

                $perfil_menu = DB::table('users_perfiles_menus')
                    ->where('idPerfil', '=', $request->idPerfil)
                    ->get();
                if ($perfil_menu) {
                    //'idUser','idMenu', 'Ver', 'Agregar', 'Editar',
                    //'Eliminar', 'Seguimiento', 'Exportar', 'Imprimir', 'ViewAll',

                    //idMenu, idPerfil, Ver, Agregar, Editar, Eliminar, Seguimiento,
                    //Exportar, Imprimir, ViewAll, created_at, UserCreated
                    foreach ($perfil_menu as $menu) {
                        $menu_user = new VUsersMenus();
                        $menu_user->idUser = $user->id;
                        $menu_user->idMenu = $menu->idMenu;
                        $menu_user->Ver = $menu->Ver;
                        $menu_user->Agregar = $menu->Agregar;
                        $menu_user->Editar = $menu->Editar;
                        $menu_user->Eliminar = $menu->Eliminar;
                        $menu_user->Seguimiento = $menu->Seguimiento;
                        $menu_user->Exportar = $menu->Exportar;
                        $menu_user->Imprimir = $menu->Imprimir;
                        $menu_user->ViewAll = $menu->ViewAll;
                        $menu_user->UserCreated = 1;
                        $menu_user->UserUpdated = 1;
                        $menu_user->save();
                    }
                }
                $contador = $contador + 1;
            }
        }
        //true, true, Se agregaron contador.
        //Las que no se crearon meter en un array y regresar.
        $response = [
            'success' => true,
            'results' => true,
            'message' => 'Se agregaron: ' . $contador . ' usuarios.',
            'UsuariosRepetidos' => $array_usuarios_repetidos,
        ];
        return response()->json($response, 200);
    }
}
