<?php

namespace App;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Hash;
use DB;


class User extends Authenticatable implements JWTSubject
{
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'email', 
        'password',
        'Nombre', 
        'Paterno', 
        'Materno', 
        'idTipoUser', 
        'TelCasa', 
        'TelCelular', 
        'Correo', 
        'Calle', 
        'NumExt', 
        'NumInt', 
        'Colonia', 
        'CP', 
        'idMunicipio', 
        'idLocalidad', 
        'defaultPage', 
        'DevideID', 
        'DeviceOS', 
        'Token', 
        'remember_token', 
        'Foto64', 
        'created_at', 
        'updated_at', 
        'email_verified_at', 
        'idStatus', 
        'UserUpdated', 
        'UserCreated'

    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function getPermisionsUsers($id){
        $permisos = DB::table('users_menus')->Where(['idMenu'=>40,'idUser'=>$id])->first();
        return $permisos;
    }

    public function getCatalogs($user){
        try{
            $auth = $this->getPermisionsUsers($user->id);
            if(!$auth){
                return response()->json(['success'=>true,'results'=>false,'message'=>'No tienes permisos para este módulo'],200);
            }
            $regiones = DB::table('cat_regiones')->Select('id AS value','Region AS label')->get();
            $tipoUser = DB::table('cat_usertipo')->Select('id AS value','TipoUser AS label')->Where('Activo',1)->get();
            $menus = DB::table('menus')->Select('id','Menu')->Where('Admin',0)->get();
    
            return response()->json(['success'=>true,'results'=>true,'data'=>['regiones'=>$regiones,'roles'=>$tipoUser,'menus'=>$menus]],200);
        }catch(\Exception $e){
            return response()->json(['success'=>true,'results'=>false,'message'=>$e->getMessage()],200);
        }
    }

    public function getAll($user,$data){
        try{
            $auth = $this->getPermisionsUsers($user->id);
            if(!$auth){
                return response()->json(['success'=>true,'results'=>false,'message'=>'No tienes permisos para este módulo'],200);
            }
        
            $query = DB::table('users AS u')
                    ->Select('u.id',
                        'u.Nombre',
                        'u.Paterno',
                        'u.Materno',
                        'u.email',
                        'u.Correo',
                        'u.idTipoUser',
                        'u.TelCelular',
                        'u.idStatus',
                        'ur.Region',
                        'p.TipoUser'
                    )
                    ->LeftJoin('users_region AS ur','ur.idUser','=','u.id')
                    ->LeftJoin('cat_usertipo AS p','p.id','=','u.idTipoUser');
            
            if($auth->ViewAll==0){                
                $query->WhereIn('u.idTipoUser',[3,4,12,13,14]);                
            }
            
            $filters = $data['filtered'];
            foreach($filters AS $filter){
                
                if($filter['id']=='.Region'){                    
                    $query->Join(DB::Raw('(SELECT DISTINCT idUser FROM users_region WHERE Region = '.$filter['value'].') AS urs '),'urs.idUser','=','u.id');
                }
            }

            $filterQuery = '';
        
            if (count($filters) > 0) {
                foreach ($filters as $filtro) {
                    if ($filterQuery != '') {
                        $filterQuery .= ' AND ';
                    }
                    $id = $filtro['id'];
                    $value = $filtro['value'];
                    
                    if ($id == '.Region') {
                        continue;    
                    }

                    $id = 'u' . $id;
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
                $query->whereRaw($filterQuery);
            }

            $page = $data['page'];
            $pageSize = $data['pageSize'];

            $startIndex = $page * $pageSize;

        
            $total = $query->count();
            $query = $query
                ->offset($startIndex)
                ->take($pageSize)
                ->orderby('u.Nombre')
                ->orderby('u.Paterno')
                ->orderby('u.Materno')
                ->get();
            
            return response()->json(['success'=>true,'results'=>true,'data'=>$query,'total'=>$total],200);
        }catch(\Exception $e){
            return response()->json(['success'=>true,'results'=>false,'message'=>$e->getMessage()],200);
        }
        
    }

    public function getMenusById($user,$id){
        try{
            $auth = $this->getPermisionsUsers($user->id);
            if(!$auth){
                return response()->json(['success'=>true,'results'=>false,'message'=>'No tienes permisos para este módulo'],200);
            }
            $menus = DB::table('users_menus AS um')
                    ->Select(
                        'um.idMenu AS id',
                        'um.idUser'                       
                    )
                    ->LeftJoin('menus AS m','m.id','=','um.idMenu')
                    ->Where('um.idUser',$id)
                    ->get();
            return response()->json(['success'=>true,'results'=>true,'data'=>$menus],200);
        }catch(\Exception $e){
            return response()->json(['success'=>true,'results'=>false,'message'=>$e->getMessage()],200);
        }
    }

    public function createUser($user,$data){
        try{
            $auth = $this->getPermisionsUsers($user->id);
            if(!$auth){
                return response()->json(['success'=>true,'results'=>false,'message'=>'No tienes permisos para este módulo'],200);
            }
            $userExist = DB::table('users')->Where('email',$data['TelCelular'])->first();
            if($userExist){
                return response()->json(['success'=>true,'results'=>false,'message'=>'El usuario '.$data['TelCelular'].'  ya se encuentra registrado'],200);
            }
            $userExist = DB::table('users')->Where('TelCelular',$data['TelCelular'])->first();
            if($userExist){
                return response()->json(['success'=>true,'results'=>false,'message'=>'El usuario '.$data['TelCelular'].'  ya se encuentra registrado'],200);
            }
            $nombre = trim(strtoupper($data['Nombre']));
            $paterno = trim(strtoupper($data['Paterno']));
            $materno = '';
            $userExist = DB::table('users')->Where(['Nombre'=>$nombre,'Paterno'=>$paterno]);
            if(isset($data['Materno'])){
                $materno = trim(strtoupper($data['Materno']));
                $userExist->Where('Materno',$materno);
            }
            $correo = '';
            if(isset($data['Correo'])){
               $correo=$data['Correo'];
            }
            $userExist=$userExist->first();
            if($userExist){
                return response()->json(['success'=>true,'results'=>false,'message'=>'El usuario '.$data['TelCelular'].'  ya se encuentra registrado'],200);
            }

            $newUser = [
                'email'=>$data['TelCelular'],
                'password'=>Hash::make($data['password']),
                'Nombre'=>$nombre,
                'Paterno'=>$paterno,
                'Materno'=>$materno,
                'idTipoUser'=>$data['idTipoUser'],
                'TelCelular'=>$data['TelCelular'],
                'Correo'=>$correo,
                'idMunicipio'=>333,
                'idLocalidad'=>110200001,
                'defaultPage'=>'/',
                'idStatus'=>1,
                'created_at'=>date('Y-m-d H:i:s'),
                'updated_at'=>date('Y-m-d H:i:s'),
                'UserCreated'=>$user->id
            ];
            $id = DB::table('users')->insertGetId($newUser);

            $newRegion = [
                'idUser'=>$id,
                'Region'=>$data['Region'],
                'idPrograma'=>2
            ];
            DB::table('users_region')->insert($newRegion);
            return response()->json(['success'=>true,'results'=>true,'data'=>$id,'message'=>'Usuario creado correctamente'],200); 
        }catch(Exception $e){
            return response()->json(['success'=>true,'results'=>false,'message'=>$e->getMessage()],200);
        }
    }

    public function updateUser($user,$data){
        try{
            $auth = $this->getPermisionsUsers($user->id);
            if(!$auth){
                return response()->json(['success'=>true,'results'=>false,'message'=>'No tienes permisos para este módulo'],200);
            }

            $id = $data['id'];
            $userData = DB::table('users')->Where('id',$id)->first();
            
            $fields = ['Correo','idTipoUser','TelCelular','idStatus','password'];
            
            $newUser = [
                'UserUpdated'=>$user->id,
                'updated_at'=>date('Y-m-d H:i:s')
            ];

            foreach($fields AS $field){                
                if(isset($data[$field])){
                    if($field=='password'){
                        $newUser[$field] = Hash::make($data[$field]);
                        continue;                 
                    }
                    if($userData->$field!=$data[$field]){
                        $newUser[$field] = $data[$field];
                    }
                                        
                }                
            }
            
            if(isset($data['TelCelular'])){
                $newUser['email']=$data['TelCelular'];
            }
            if(isset($data['idTipoUser'])){
                $permisos = DB::table('cat_roles')->Select('View','Create','Update','Delete','FollowUp','Export','Print','ViewAll')->Where('idTipoUser',$data['idTipoUser'])->first();
                $newMenu = [
                    'Ver'=>$permisos->View,
                    'Agregar'=>$permisos->Create,
                    'Editar'=>$permisos->Update,
                    'Eliminar'=>$permisos->Delete,
                    'Seguimiento'=>$permisos->FollowUp,
                    'Exportar'=>$permisos->Export,                
                    'Imprimir'=>$permisos->Print,
                    'ViewAll'=>$permisos->ViewAll,
                    'UserUpdated'=>$user->id,
                    'updated_at'=>date('Y-m-d H:i:s')
                ];
                DB::table('users_menus')->Where('idUser',$id)->update($newMenu);
            }            
            DB::table('users')->Where('id',$id)->update($newUser);
            if(isset($data['Region'])){
                $region = DB::table('users_region')->Where('idUser',$data['id'])->first();
                if($region){
                    DB::table('users_region')->Where('idUser',$data['id'])->update(['Region'=>$data['Region']]);
                }else{
                    DB::table('users_region')->Insert(
                        [
                            'idUser'=>$data['id'],
                            'Region'=>$data['Region'],
                            'idPrograma'=>2
                        ]
                        );
                }
            }
            return response()->json(['success'=>true,'results'=>true,'message'=>'Usuario actualizado correctamente'],200);
        }catch(\Exception $e){
            return response()->json(['success'=>true,'results'=>false,'message'=>$e->getMessage()],200);
        }
    }

    public function setMenu($user,$data){
        try{
            $auth = $this->getPermisionsUsers($user->id);
            if(!$auth){
                return response()->json(['success'=>true,'results'=>false,'message'=>'No tienes permisos para este módulo'],200);
            }

            if($data['flag']==0){
                    DB::table('users_menus')->Where(['idUser'=>$data['idUser'],'idMenu'=>$data['idMenu']])->delete();
                    $menus = DB::table('users_menus')->Where('idUser',$data['idUser'])->first();
                    if(!$menus){
                        DB::table('users')->Where('id',$data['idUser'])->update(['defaultPage'=>'/']);
                    }
                    return response()->json(['success'=>true,'results'=>true,'message'=>'Menús eliminados correctamente'],200);    
            }
            
            $idTipoUser = DB::table('users')->Select('idTipoUser')->Where('id',$data['idUser'])->first();            
            $permisos = DB::table('cat_roles')->Select('View','Create','Update','Delete','FollowUp','Export','Print','ViewAll')->Where('idTipoUser',$idTipoUser->idTipoUser)->first();
            
            $newMenu = [
                'idUser'=>$data['idUser'],
                'idMenu'=>$data['idMenu'],
                'Ver'=>$permisos->View,
                'Agregar'=>$permisos->Create,
                'Editar'=>$permisos->Update,
                'Eliminar'=>$permisos->Delete,
                'Seguimiento'=>$permisos->FollowUp,
                'Exportar'=>$permisos->Export,                
                'Imprimir'=>$permisos->Print,
                'ViewAll'=>$permisos->ViewAll,
                'created_at'=>date('Y-m-d H:i:s'),
                'updated_at'=>date('Y-m-d H:i:s'),
                'UserCreated'=>$user->id,
                'UserUpdated'=>$user->id
            ];

            DB::table('users_menus')->insert($newMenu);
            $defaultPage = DB::table('menus')->Where('id',$data['idMenu'])->first();
            DB::table('users')->Where('id',$data['idUser'])->update(['defaultPage'=>$defaultPage->path]);
            return response()->json(['success'=>true,'results'=>true,'message'=>'Menús asignados correctamente'],200);
        }catch(\Exception $e){
            return response()->json(['success'=>true,'results'=>false,'message'=>$e->getMessage()],200);
        }
    }

    public function bloqueoMasivo($user){
        try{
            $auth = $this->getPermisionsUsers($user->id);
            if(!$auth){
                return response()->json(['success'=>true,'results'=>false,'message'=>'No tienes permisos para este módulo'],200);
            }            
            DB::table('users')->WhereIn('idTipoUser',[11,12,13])->update(['idStatus'=>0,'UserUpdated'=>$user->id,'updated_at'=>date('Y-m-d H:i:s')]);
            return response()->json(['success'=>true,'results'=>true,'message'=>'Usuarios bloqueados correctamente'],200);
        }catch(\Exception $e){
            return response()->json(['success'=>true,'results'=>false,'message'=>$e->getMessage()],200);
        }
    }

}
