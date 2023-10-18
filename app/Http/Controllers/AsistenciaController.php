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

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class AsistenciaController extends Controller
{
    public function getUsersByRegion(Request $request)
    {
        $v = Validator::make(
            $request->all(),
            [
                'region' => 'required',
            ],
            $messages = [
                'required' => 'El campo :attribute es obligatorio',
            ]
        );

        if ($v->fails()) {
            $response = [
                'success' => true,
                'results' => false,
                'message' => $v->errors(),
            ];
            return response()->json($response, 200);
        }

        $params = $request->all();
        $user = auth()->user();
        $permisos = DB::table('users_menus')
            ->Select('Ver')
            ->Where(['idUser' => $user->id, 'idMenu' => 37])
            ->first();
        if (!$permisos) {
            return [
                'success' => true,
                'results' => false,
                'message' => 'No tiene acceso para ver la información',
                'data' => [],
            ];
            return response()->json($response, 200);
        }

        $tableAsistencia =
            'p.id NOT IN (SELECT idUser FROM users_asistencia WHERE FechaElimino IS NULL AND FechaAsistencia = "' .
            $params['date'] .
            '")';

        $listUsers = DB::table('cat_personal AS p')
            ->Select(
                'p.id',
                'p.Region',
                'p.Nombre',
                'p.Paterno',
                'p.Materno',
                'p.Usuario'
                // 'asistencia.id'
            )
            ->Where([
                'p.Region' => $params['region'],
            ])
            ->WhereRaw($tableAsistencia)
            ->OrderBy('p.Nombre')
            ->OrderBy('p.Paterno')
            ->get();
        // dd(
        //     str_replace_array(
        //         '?',
        //         $listUsers->getBindings(),
        //         $listUsers->toSql()
        //     )
        // );

        if ($listUsers->count() === 0) {
            $response = [
                'success' => true,
                'results' => true,
                'data' => [],
                'total' => 0,
            ];
            return response()->json($response, 200);
        } else {
            $response = [
                'success' => true,
                'results' => true,
                'data' => $listUsers,
                'total' => $listUsers->count(),
            ];
            return response()->json($response, 200);
        }
    }

    public function getAssistants(Request $request)
    {
        $v = Validator::make(
            $request->all(),
            [
                'FechaAsistencia' => 'required',
            ],
            $messages = [
                'required' => 'El campo :attribute es obligatorio',
            ]
        );

        if ($v->fails()) {
            $response = [
                'success' => true,
                'results' => false,
                'message' => $v->errors(),
            ];
            return response()->json($response, 200);
        }

        $params = $request->only(['FechaAsistencia']);
        $user = auth()->user();
        $permisos = DB::table('users_menus')
            ->Select('Ver')
            ->Where(['idUser' => $user->id, 'idMenu' => 37])
            ->first();
        if (!$permisos) {
            return [
                'success' => true,
                'results' => false,
                'message' => 'No tiene acceso para ver la información',
                'data' => [],
            ];
            return response()->json($response, 200);
        }

        $listUsers = DB::table('users_asistencia AS u')
            ->Select(
                'u.id',
                'u.idUser',
                'u.Nombre',
                'u.Paterno',
                'u.Materno',
                'u.FechaAsistencia',
                'u.Usuario',
                DB::RAW(
                    'CONCAT_WS(" ",c.Nombre,c.Paterno,c.Materno) AS UsuarioCreo'
                )
            )
            ->Join('users AS c', 'u.idUsuarioCreo', 'c.id')
            ->Where([
                'FechaAsistencia' => $params['FechaAsistencia'],
            ])
            ->WhereNull('u.FechaElimino')
            ->OrderBy('u.Nombre')
            ->OrderBy('u.Paterno')
            ->get();

        if ($listUsers->count() === 0) {
            $response = [
                'success' => true,
                'results' => true,
                'data' => [],
                'total' => 0,
            ];
            return response()->json($response, 200);
        } else {
            $response = [
                'success' => true,
                'results' => true,
                'data' => $listUsers,
                'total' => $listUsers->count(),
            ];
            return response()->json($response, 200);
        }
    }

    public function checkAssistant(Request $request)
    {
        $v = Validator::make(
            $request->all(),
            [
                'Usuario' => 'required',
                'Nombre' => 'required',
                'Paterno' => 'required',
                'FechaAsistencia' => 'required',
            ],
            $messages = [
                'required' => 'El campo :attribute es obligatorio',
            ]
        );

        if ($v->fails()) {
            $response = [
                'success' => true,
                'results' => false,
                'message' => $v->errors(),
            ];
            return response()->json($response, 200);
        }

        $params = $request->all();
        $user = auth()->user();
        $checkUser = DB::table('users_asistencia AS u')
            ->Select('u.id')
            ->Where([
                'FechaAsistencia' => $params['FechaAsistencia'],
                'Usuario' => $params['Usuario'],
            ])
            ->WhereNull('u.FechaElimino')
            ->first();

        if ($checkUser) {
            $response = [
                'success' => true,
                'results' => false,
                'message' => 'El usuario ya fue agregado a la lista',
            ];
            return response()->json($response, 200);
        } else {
            $params['FechaCreo'] = date('Y-m-d H:i:s');
            $params['idUsuarioCreo'] = $user->id;
            DB::table('users_asistencia')->insert($params);
            $response = [
                'success' => true,
                'results' => true,
                'message' => 'Agregado correctamente',
            ];
            return response()->json($response, 200);
        }
    }

    public function deleteAssistant(Request $request)
    {
        $v = Validator::make($request->all(), [
            'id' => 'required',
        ]);

        if ($v->fails()) {
            $response = [
                'success' => true,
                'results' => false,
                'message' => 'No se se recibió la información del usuario',
            ];
            return response()->json($response, 200);
        }

        $params = $request->only(['id']);
        $user = auth()->user();

        $assistant = DB::table('users_asistencia')
            ->Where([
                'id' => $params['id'],
            ])
            ->update([
                'FechaElimino' => date('Y-m-d H:i:s'),
                'idUsuarioElimino' => $user->id,
            ]);

        $response = [
            'success' => true,
            'results' => true,
            'message' => 'Usuario borrado',
        ];
        return response()->json($response, 200);
    }

    public function getListAssistants(Request $request)
    {
        $v = Validator::make(
            $request->all(),
            [
                'FechaAsistencia' => 'required',
            ],
            $messages = [
                'required' => 'El campo :attribute es obligatorio',
            ]
        );

        if ($v->fails()) {
            $response = [
                'success' => true,
                'results' => false,
                'message' => $v->errors(),
            ];
            return response()->json($response, 200);
        }

        $params = $request->only(['FechaAsistencia']);
        $user = auth()->user();

        $checkUser = DB::table('users_asistencia AS u')
            ->Select('u.id')
            ->Where([
                'FechaAsistencia' => $params['FechaAsistencia'],
                'Usuario' => $params['Usuario'],
            ])
            ->first();

        if ($checkUser) {
            $response = [
                'success' => true,
                'results' => false,
                'message' => 'El usuario ya fue agregado a la lista',
            ];
            return response()->json($response, 200);
        } else {
            $params['FechaCreo'] = date('Y-m-d H:i:s');
            $params['idUsuarioCreo'] = $user->id;
            DB::table('users_asistencia')->insert($params);
            $response = [
                'success' => true,
                'results' => true,
                'message' => 'Agregado correctamente',
            ];
            return response()->json($response, 200);
        }
    }

    public function getListPdf(Request $request)
    {
        $v = Validator::make($request->all(), [
            'FechaAsistencia' => 'required',
        ]);

        if ($v->fails()) {
            $response = [
                'success' => true,
                'results' => false,
                'message' => 'La Fecha de asistencia no fue enviada',
            ];
            return response()->json($response, 200);
        }

        $params = $request->only(['FechaAsistencia']);
        $user = auth()->user();

        $res = DB::table('users_asistencia AS u')
            ->Select(
                'id',
                'Region',
                'Usuario',
                'Nombre',
                'Paterno',
                'Materno',
                DB::RAW(
                    'DATE_FORMAT(FechaAsistencia, "%d/%m/%Y") AS FechaAsistencia'
                )
            )
            ->WhereNull('FechaElimino')
            ->Where([
                'u.FechaAsistencia' => $params['FechaAsistencia'],
            ])
            ->orderBy('Nombre', 'asc')
            ->orderBy('Paterno', 'asc')
            ->get();

        $d = $res
            ->map(function ($x) {
                $x = is_object($x) ? (array) $x : $x;
                return $x;
            })
            ->toArray();
        unset($res);

        if (count($d) == 0) {
            $file =
                public_path() . '/archivos/formatoReporteNominaValesv3.xlsx';

            return response()->download(
                $file,
                $resGpo->Remesa .
                    '_' .
                    $resGpo->idMunicipio .
                    '_' .
                    $resGpo->ResponsableEntrega .
                    '_NominaValesGrandeza' .
                    date('Y-m-d') .
                    '.xlsx'
            );
        }
        $users = [];
        $fecha = explode('-', $params['FechaAsistencia']);
        foreach (array_chunk($d, 16) as $arrayData) {
            $users[] = [
                'FechaSolicitud' =>
                    $fecha[2] . '/' . $fecha[1] . '/' . $fecha[0],
                'data' => $arrayData,
            ];
        }
        $nombreArchivo = 'ListaAsistencia_' . $params['FechaAsistencia'];
        $counter = 0;
        $pdf = \PDF::loadView('pdf_asistencia', compact('users'));
        return $pdf->download($nombreArchivo . '.pdf');
    }
}
