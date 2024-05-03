<?php
namespace App\Http\Controllers;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

use PhpOffice\PhpWord\TemplateProcessor;
use Illuminate\Filesystem\Filesystem;
use Milon\Barcode\DNS1D;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;
use PDF;
use File;
use Zipper;

use PhpOffice\PhpPresentation\IOFactory as IOFactories;

use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\Style\Alignment;
use PhpOffice\PhpPresentation\Style\Color;

use App\VNegociosFiltros;
use App\UsersFiltro;
use Arr;
use Validator;
use DateTime;
use Illuminate\Contracts\Validation\ValidationException;
set_time_limit(0);
class ReportesController extends Controller
{
    function getAvanceRemesas(Request $request)
    {
        $v = Validator::make($request->all(), [
            'Remesa' => 'required',
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

        $remesaSistema = $parameters['Remesa'];

        try {
            $select =
                'G.Remesa, G.Region, G.Municipio, G.Responsable, G.Total, A.Avance, (G.Total -if(A.Avance is null, 0, A.Avance)) as Restan';

            $table1 =
                "(select Remesa,  Region, concat_ws('-',Remesa, Municipio, Responsable) Clave, Municipio, Responsable, Total from vales_grupos_totales  WHERE Remesa IN (SELECT Remesa FROM vales_remesas WHERE RemesaSistema = '" .
                $remesaSistema .
                "') order by Region, Municipio, Responsable ) as G";

            $table2 =
                "(select concat_ws('-',Remesa, Municipio, Articulador) Clave, Municipio, Articulador, count(distinct idSolicitud) as Avance from vales_solicitudes WHERE Remesa IN (SELECT Remesa FROM vales_remesas WHERE RemesaSistema = '" .
                $remesaSistema .
                "') group by Remesa, Municipio, Articulador) as A ";

            $res = DB::table(DB::raw($table1))
                ->select(DB::raw($select))
                ->leftJoin(DB::raw($table2), 'G.Clave', '=', 'A.Clave')
                ->orderBy('G.Region', 'ASC')
                ->orderBy('G.Municipio', 'ASC')
                ->orderBy('G.Responsable', 'ASC')
                ->get();

            //dd(str_replace_array('?', $res->getBindings(), $res->toSql()));
            //;

            //dd($res);

            return response()->json([
                'success' => true,
                'results' => true,
                'total' => $res->count(),
                'data' => $res,
            ]);
        } catch (QueryException $e) {
            return ['success' => false, 'errors' => $e->getMessage()];
        }
    }

    function getMisRemesas(Request $request)
    {
        $parameters = $request->all();

        $user = auth()->user();

        try {
            $select =
                'R.Clave, R.Region, R.Municipio, R.Remesa, R.Total, if(E.Entregados is null, 0, E.Entregados) as Entregados, R.Responsable';

            $table1 =
                "(SELECT concat_ws('-',V.idMunicipio,  V.Remesa, UO.id) Clave, V.idMunicipio, M.SubRegion AS Region, M.Nombre AS Municipio, V.Remesa, count(V.id) as Total, concat_ws(' ',UO.Nombre, UO.Paterno, UO.Materno) Responsable FROM vales AS V JOIN et_cat_municipio AS M ON V.idMunicipio = M.Id JOIN users AS UO ON V.UserOwned = UO.id WHERE V.UserOwned=" .
                $user->id .
                ' and V.idStatus=5 GROUP BY V.Remesa, V.idMunicipio, UO.id ORDER BY V.Remesa ASC, M.Nombre ASC) R';

            $table2 =
                "(SELECT V.idMunicipio, M.SubRegion AS Region, M.Nombre AS Municipio, V.Remesa, count(V.id) as Entregados, concat_ws(' ',UO.Nombre, UO.Paterno, UO.Materno) Responsable FROM vales AS V JOIN et_cat_municipio AS M ON V.idMunicipio = M.Id JOIN users AS UO ON V.UserOwned = UO.id WHERE V.UserOwned=" .
                $user->id .
                ' and V.idStatus=5 and V.isEntregado=1 GROUP BY V.Remesa, V.idMunicipio, UO.id ORDER BY V.Remesa ASC, M.Nombre ASC) E';

            $res = DB::table(DB::raw($table1))
                ->select(DB::raw($select))
                ->leftJoin(
                    DB::raw($table2),
                    'R.idMunicipio',
                    '=',
                    'E.idMunicipio'
                )
                ->orderBy('R.Region', 'ASC')
                ->orderBy('R.Remesa', 'ASC')
                ->orderBy('R.Municipio', 'ASC')
                ->get();

            return response()->json([
                'success' => true,
                'results' => true,
                'total' => $res->count(),
                'data' => $res,
            ]);
        } catch (QueryException $e) {
            return ['success' => false, 'errors' => $e->getMessage()];
        }
    }

    function getMiResumenVales(Request $request)
    {
        $parameters = $request->all();

        $user = auth()->user();

        try {
            $res = DB::table('vales')
                ->select('Remesa', DB::raw('count(distinct CURP) as Total'))
                ->where('UserOwned', '=', $user->id)
                ->groupBy('Remesa')
                ->orderBy('Remesa', 'ASC')
                ->get();

            $resTotal = DB::table('vales')
                ->select(DB::raw('count(distinct CURP) as Total'))
                ->where('UserOwned', '=', $user->id)
                ->get();

            return response()->json([
                'success' => true,
                'results' => true,
                'total' => $resTotal[0]->Total,
                'data' => $res,
            ]);
        } catch (QueryException $e) {
            return ['success' => false, 'errors' => $e->getMessage()];
        }
    }

    function getMisRemesasTotales(Request $request)
    {
        $parameters = $request->all();

        $user = auth()->user();

        try {
            $select =
                'V.Remesa, V.Municipio, V.Total, V.UserOwned, if(NE.NoEntregado is null, 0, NE.NoEntregado) NoEntregado, if(E.Entregado is null, 0, E.Entregado) Entregado';
            $table1 =
                '(SELECT V.Remesa, M.Id as idMunicipio,  M.Nombre as Municipio, V.UserOwned, count(V.isEntregado) Total FROM vales V inner join et_cat_municipio M on (V.idMunicipio = M.Id) WHERE V.Remesa is not null group by V.UserOwned, V.Remesa, M.Id) V';
            $table2 =
                '(SELECT Remesa, idMunicipio, UserOwned, count(isEntregado) NoEntregado FROM vales WHERE Remesa is not null  and isEntregado=0 group by UserOwned, Remesa, idMunicipio) NE';
            $table3 =
                '(SELECT Remesa, idMunicipio, UserOwned, count(isEntregado) Entregado FROM vales WHERE Remesa is not null  and isEntregado=1 group by UserOwned, Remesa, idMunicipio) E';

            $res = DB::table(DB::raw($table1))
                ->select(DB::raw($select))
                ->leftJoin(DB::raw($table2), function ($join) {
                    $join->on('NE.UserOwned', '=', 'V.UserOwned');
                    $join->on('NE.Remesa', '=', 'V.Remesa');
                })
                ->leftJoin(DB::raw($table3), function ($join) {
                    $join->on('E.UserOwned', '=', 'V.UserOwned');
                    $join->on('E.Remesa', '=', 'V.Remesa');
                });

            if (isset($parameters['UserOwned'])) {
                $res->where('V.UserOwned', '=', $parameters['UserOwned']);
            }

            $data = $res
                ->orderBy('V.UserOwned', 'ASC')
                ->orderBy('V.Remesa', 'ASC')
                ->get();

            return response()->json([
                'success' => true,
                'results' => true,
                'total' => $res->count(),
                'data' => $data,
            ]);
        } catch (QueryException $e) {
            return ['success' => false, 'errors' => $e->getMessage()];
        }
    }

    function getReporteNominaValesDetalle(Request $request)
    {
        $parameters = $request->all();

        $user = auth()->user();

        try {
            if (!$parameters['UserOwned']) {
                return response()->json([
                    'success' => true,
                    'results' => false,
                    'data' => [],
                    'message' =>
                        'No se encontraron resultados del Articulador.',
                ]);
            }

            $res = DB::table('vales as N')
                ->select(
                    'N.id',
                    'N.idIncidencia',
                    'VSX.Incidencia',
                    'N.Remesa',
                    DB::raw('YEAR(N.FechaSolicitud) as Ejercicio'),
                    'M.SubRegion AS Region',
                    DB::raw('LPAD(HEX(N.id),6,0) AS ClaveUnica'),
                    'N.CURP',
                    DB::raw(
                        "concat_ws(' ',N.Nombre, N.Paterno, N.Materno) as NombreCompleto"
                    ),
                    'N.Sexo',
                    DB::raw(
                        "concat_ws(' ',N.Calle, if(N.NumExt is null, ' ', concat('NumExt ',N.NumExt)), if(N.NumInt is null, ' ', concat('Int ',N.NumInt))) AS Direccion"
                    ),
                    'N.Colonia',
                    'N.CP',
                    'M.Nombre AS Municipio',
                    'L.Nombre AS Localidad',
                    'VS.SerieInicial',
                    'VS.SerieFinal',
                    'N.isEntregado',
                    'N.entrega_at'
                )
                ->leftJoin(
                    'vales_incidencias as VSX',
                    'VSX.id',
                    '=',
                    'N.idIncidencia'
                )
                ->leftJoin(
                    'et_cat_municipio as M',
                    'N.idMunicipio',
                    '=',
                    'M.Id'
                )
                ->leftJoin(
                    'et_cat_localidad_2022 as L',
                    'N.idLocalidad',
                    '=',
                    'L.Id'
                )
                ->Join('vales_solicitudes as VS', 'VS.idSolicitud', '=', 'N.id')
                ->leftJoin('users as UOC', 'UOC.id', '=', 'N.UserOwned')
                ->join('vales_status as E', 'N.idStatus', '=', 'E.id')
                ->whereNotNull('N.Remesa')
                ->where(
                    DB::raw('YEAR(N.FechaSolicitud)'),
                    '=',
                    $parameters['Ejercicio']
                );

            if (isset($parameters['UserOwned'])) {
                $res->where('N.UserOwned', '=', $parameters['UserOwned']);
            }
            if (isset($parameters['idMunicipio'])) {
                $res->where('N.idMunicipio', '=', $parameters['idMunicipio']);
            }
            if (isset($parameters['Remesa'])) {
                $res->where('N.Remesa', '=', $parameters['Remesa']);
            }
            if (isset($parameters['isEntregado'])) {
                $res->where('N.isEntregado', '=', $parameters['isEntregado']);
            }
            if (isset($parameters['isIncidencia'])) {
                $res->orWhere('N.idIncidencia', '!=', 1);
            }

            $data = $res
                ->orderBy('N.Remesa', 'asc')
                ->orderBy('M.Nombre', 'asc')
                ->orderBy('N.Colonia', 'asc')
                ->orderBy('N.Nombre', 'asc')
                ->orderBy('N.Paterno', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'results' => true,
                'total' => $res->count(),
                'data' => $data,
            ]);
        } catch (QueryException $e) {
            return ['success' => false, 'errors' => $e->getMessage()];
        }
    }

    function getCatGrupos(Request $request)
    {
        $parameters = $request->all();

        try {
            $catRemesas = DB::table('vales_grupos_totales')
                ->select('vales_grupos_totales.Remesa')
                ->JOIN(
                    'vales_remesas AS r',
                    'r.Remesa',
                    'vales_grupos_totales.Remesa'
                )
                ->WhereRaw('r.Ejercicio > 2021')
                ->groupBy('Remesa')
                ->orderBy('r.Ejercicio', 'DESC')
                ->orderBy('r.Remesa', 'ASC')
                ->get()
                ->pluck('Remesa')
                ->toArray();

            $catMunicipio = DB::table('vales_grupos_totales')
                ->select('Municipio')
                ->JOIN(
                    'vales_remesas AS r',
                    'r.Remesa',
                    'vales_grupos_totales.Remesa'
                )
                ->WhereRaw('r.Ejercicio > 2021')
                ->groupBy('Municipio')
                ->orderBy('Municipio', 'ASC')
                ->get()
                ->pluck('Municipio')
                ->toArray();

            $catResponsable = DB::table('vales_grupos_totales')
                ->select('Responsable')
                ->JOIN(
                    'vales_remesas AS r',
                    'r.Remesa',
                    'vales_grupos_totales.Remesa'
                )
                ->WhereRaw('r.Ejercicio > 2021')
                ->groupBy('Responsable')
                ->orderBy('Responsable', 'ASC')
                ->get()
                ->pluck('Responsable')
                ->toArray();

            $catResponsableEntrega = DB::table('vales_grupos_totales')
                ->select('ResponsableEntrega')
                ->JOIN(
                    'vales_remesas AS r',
                    'r.Remesa',
                    'vales_grupos_totales.Remesa'
                )
                ->WhereRaw('vales_grupos_totales.Ejercicio = 2023')
                ->groupBy('ResponsableEntrega')
                ->orderBy('ResponsableEntrega', 'ASC')
                ->get()
                ->pluck('ResponsableEntrega')
                ->toArray();

            $catLocalidad = DB::table('vales_grupos_totales')
                ->select('Localidad')
                ->JOIN(
                    'vales_remesas AS r',
                    'r.Remesa',
                    'vales_grupos_totales.Remesa'
                )
                ->WhereRaw('vales_grupos_totales.Ejercicio = 2023')
                ->groupBy('Localidad')
                ->orderBy('Localidad', 'ASC')
                ->get()
                ->pluck('Localidad')
                ->toArray();

            $data = [
                'Remesas' => $catRemesas,
                'Municipios' => $catMunicipio,
                'Responsables' => $catResponsable,
                'ResponsablesEntrega' => $catResponsableEntrega,
                'Localidades' => $catLocalidad,
            ];

            return response()->json([
                'success' => true,
                'results' => true,
                'total' => count($data),
                'data' => $data,
            ]);
        } catch (QueryException $e) {
            return ['success' => false, 'errors' => $e->getMessage()];
        }
    }

    public function getListados2023(Request $request)
    {
        $v = Validator::make($request->all(), [
            'remesa' => 'required',
            'municipio' => 'required',
        ]);

        if ($v->fails()) {
            $response = [
                'success' => false,
                'results' => false,
                'errors' => 'La remesa o el municipio no fueron enviados',
                'data' => [],
            ];

            return response()->json($response, 200);
        }

        $params = $request->all();
        try {
            $grupos = DB::table('vales_grupos AS g')
                ->Select(
                    'g.id AS idGrupo',
                    DB::RAW(
                        'CONCAT(g.CveInterventor," - Loc: ",l.Nombre," - Resp: ",g.ResponsableEntrega," - ",g.TotalAprobados) AS Listado'
                    )
                )
                ->Join('et_cat_municipio AS m', 'm.Id', 'g.idMunicipio')
                ->Join('et_cat_localidad_2022 AS l', 'l.Id', 'g.idLocalidad')
                ->WhereRaw('g.Ejercicio = 2023')
                ->Where([
                    'm.Nombre' => $params['municipio'],
                    'g.Remesa' => $params['remesa'],
                ])
                ->orderBy('g.id', 'ASC')
                ->get()
                ->toArray();

            $data = [
                'Listados' => $grupos,
            ];

            return response()->json([
                'success' => true,
                'results' => true,
                'data' => $data,
            ]);
        } catch (QueryException $e) {
            return ['success' => false, 'errors' => $e->getMessage()];
        }
    }

    function getCatGrupos2023(Request $request)
    {
        $parameters = $request->all();

        try {
            $catRemesas = DB::table('vales_grupos_totales')
                ->select('vales_grupos_totales.Remesa')
                ->JOIN(
                    'vales_remesas AS r',
                    'r.Remesa',
                    'vales_grupos_totales.Remesa'
                )
                ->WhereRaw('r.Ejercicio = 2023')
                ->groupBy('Remesa')
                ->orderBy('r.Ejercicio', 'DESC')
                ->orderBy('r.Remesa', 'ASC')
                ->get()
                ->pluck('Remesa')
                ->toArray();

            $catMunicipio = DB::table('vales_grupos_totales')
                ->select('Municipio')
                ->JOIN(
                    'vales_remesas AS r',
                    'r.Remesa',
                    'vales_grupos_totales.Remesa'
                )
                ->WhereRaw('r.Ejercicio = 2023')
                ->groupBy('Municipio')
                ->orderBy('Municipio', 'ASC')
                ->get()
                ->pluck('Municipio')
                ->toArray();

            $catResponsable = DB::table('vales_grupos_totales')
                ->select('Responsable')
                ->JOIN(
                    'vales_remesas AS r',
                    'r.Remesa',
                    'vales_grupos_totales.Remesa'
                )
                ->WhereRaw('r.Ejercicio = 2023')
                ->groupBy('Responsable')
                ->orderBy('Responsable', 'ASC')
                ->get()
                ->pluck('Responsable')
                ->toArray();

            $catResponsableEntrega = DB::table('vales_grupos_totales')
                ->select('ResponsableEntrega')
                ->JOIN(
                    'vales_remesas AS r',
                    'r.Remesa',
                    'vales_grupos_totales.Remesa'
                )
                ->WhereRaw('r.Ejercicio = 2023')
                ->groupBy('ResponsableEntrega')
                ->orderBy('ResponsableEntrega', 'ASC')
                ->get()
                ->pluck('ResponsableEntrega')
                ->toArray();

            $catLocalidad = DB::table('vales_grupos_totales')
                ->select('Localidad')
                ->JOIN(
                    'vales_remesas AS r',
                    'r.Remesa',
                    'vales_grupos_totales.Remesa'
                )
                ->WhereRaw('r.Ejercicio = 2023')
                ->groupBy('Localidad')
                ->orderBy('Localidad', 'ASC')
                ->get()
                ->pluck('Localidad')
                ->toArray();

            $catInterventores = DB::table('vales_grupos_totales')
                ->select('CveInterventor')
                ->JOIN(
                    'vales_remesas AS r',
                    'r.Remesa',
                    'vales_grupos_totales.Remesa'
                )
                ->whereRaw('CveInterventor IS NOT NULL')
                ->WhereRaw('r.Ejercicio = 2023')
                ->groupBy('CveInterventor')
                ->orderBy('CveInterventor', 'ASC')
                ->get()
                ->pluck('CveInterventor')
                ->toArray();

            $data = [
                'Remesas' => $catRemesas,
                'Municipios' => $catMunicipio,
                'Responsables' => $catResponsable,
                'ResponsablesEntrega' => $catResponsableEntrega,
                'Localidades' => $catLocalidad,
                'CvesInterventor' => $catInterventores,
            ];

            return response()->json([
                'success' => true,
                'results' => true,
                'total' => count($data),
                'data' => $data,
            ]);
        } catch (QueryException $e) {
            return ['success' => false, 'errors' => $e->getMessage()];
        }
    }

    function getRemesas(Request $request)
    {
        $parameters = $request->all();
        $user = auth()->user();
        $year_start = idate('Y', strtotime('first day of January', time()));

        $remesas = DB::table('vales_remesas')
            ->select('Remesa as label', 'RemesaSistema AS value')
            ->whereRaw('YEAR(Fecha)=' . $year_start)
            ->groupBy('Fecha')
            ->get();

        $catalogs = [
            'remesas' => $remesas,
        ];

        $response = [
            'success' => true,
            'results' => true,
            'data' => $catalogs,
        ];
        return response()->json($response, 200);
    }

    function getRemesasGruposAvance(Request $request)
    {
        $parameters = $request->all();

        $user = auth()->user();

        try {
            $select =
                "G.Remesa, G.Region, G.idMunicipio, G.Municipio, G.UserOwned, G.Responsable, G.Total, V.Avance, if(G.Total=V.Avance, 'Completado', format(100 *(if(V.Avance is null, 0, V.Avance)/G.Total),2)) as Estatus";

            $table1 =
                '(select Region, idMunicipio, Municipio, UserOwned, Responsable, Total, Remesa from vales_grupos_totales) G';

            $table2 =
                '(Select V.idMunicipio, V.UserOwned, V.Remesa, count(V.id) Avance from vales V inner join  vales_solicitudes VS on (VS.idSolicitud = V.id) group by V.idMunicipio, V.UserOwned, V.Remesa) V';

            $res = DB::table(DB::raw($table1))
                ->select(DB::raw($select))
                ->leftJoin(DB::raw($table2), function ($join) {
                    $join->on('G.idMunicipio', '=', 'V.idMunicipio');
                    $join->on('G.UserOwned', '=', 'V.UserOwned');
                    $join->on('G.Remesa', '=', 'V.Remesa');
                })
                ->orderBy('G.Region', 'ASC')
                ->orderBy('G.Remesa', 'ASC')
                ->orderBy('G.Municipio', 'ASC')
                ->orderBy('G.Responsable', 'ASC');

            if (isset($parameters['Remesa'])) {
                $res->where('G.Remesa', '=', $parameters['Remesa']);
            }

            if (isset($parameters['Municipio'])) {
                $res->where(
                    'G.Municipio',
                    'like',
                    '%' . $parameters['Remesa'] . '%'
                );
            }

            if (isset($parameters['Responsable'])) {
                $res->where(
                    'G.Responsable',
                    'like',
                    '%' . $parameters['Responsable'] . '%'
                );
            }

            $Data = $res->get();

            return response()->json([
                'success' => true,
                'results' => true,
                'total' => $res->count(),
                'data' => $Data,
            ]);
        } catch (QueryException $e) {
            return ['success' => false, 'errors' => $e->getMessage()];
        }
    }

    public function getReporteGrupos(Request $request)
    {
        // ,'d.FechaNacimientoC','d.SexoC as Sexo'

        $res = DB::table('et_tarjetas_asignadas as a')
            ->select(
                DB::raw(
                    "concat_ws(' ', lpad(HEX(a.idGrupo),3,'0'),c.Nombre) as Grupo"
                ),
                'd.FolioC',
                'f.Nombre as Municipio',
                'e.Nombre as Localidad',
                'd.NombreC as Nombre',
                'd.PaternoC as Paterno',
                'd.MaternoC as Materno',
                'd.ColoniaC as Colonia',
                'd.CalleC as Calle',
                'd.NumeroC as Numero',
                'd.CodigoPostalC as CP',
                'a.Terminacion'
            )
            ->join('et_grupo as b', 'a.idGrupo', '=', 'b.id')
            ->join('et_cat_municipio as c', 'b.idMunicipio', '=', 'c.id')
            ->join('et_aprobadoscomite as d', 'a.id', '=', 'd.id')
            ->join('et_cat_localidad_2022 as e', 'e.Id', '=', 'd.idLocalidadC')
            ->join('et_cat_municipio as f', 'f.id', '=', 'd.idMunicipioC');

        $resGrupo = DB::table('et_grupo as G')
            ->select('G.NombreGrupo', 'MG.Nombre as MunicipioGrupo')
            ->join('et_cat_municipio as MG', 'MG.Id', '=', 'G.idMunicipio');

        if (isset($request->idGrupo)) {
            $res->where('a.idGrupo', $request->idGrupo);
            $resGrupo->where('G.id', $request->idGrupo);
        }
        if (isset($request->idMunicipio)) {
            $res->where('b.idMunicipio', $request->idMunicipio);
        }

        $data = $res
            ->orderBy('a.idMunicipio', 'asc')
            ->orderBy('a.idGrupo', 'asc')
            ->orderBy('NombreC', 'asc')
            ->orderBy('PaternoC', 'asc')
            ->orderBy('MaternoC', 'asc')
            ->get();
        $data2 = $resGrupo->first();

        if (count($data) == 0) {
            return response()->json([
                'success' => false,
                'results' => false,
                'message' => 'No se encontraron datos',
            ]);
        }

        //Mapeamos el resultado como un array
        $res = $data
            ->map(function ($x) {
                $x = is_object($x) ? (array) $x : $x;
                return $x;
            })
            ->toArray();

        //------------------------------------------------- Para generar el archivo excel ----------------------------------------------------------------
        // $spreadsheet = new Spreadsheet();
        // $sheet = $spreadsheet->getActiveSheet();

        //Para los titulos del excel
        // $titulos = ['Grupo','Folio','Nombre','Paterno','Materno','Fecha de Nacimiento','Sexo','Calle','Numero','Colonia','Municipio','Localidad','CP','Terminación'];
        // $sheet->fromArray($titulos,null,'A1');
        // $sheet->getStyle('A1:N1')->getFont()->getColor()->applyFromArray(['rgb' => '808080']);

        $reader = IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load(public_path() . '/archivos/formato.xlsx');
        $sheet = $spreadsheet->getActiveSheet();

        $largo = count($res);
        //colocar los bordes
        self::crearBordes($largo, 'B', $sheet);
        self::crearBordes($largo, 'C', $sheet);
        self::crearBordes($largo, 'D', $sheet);
        self::crearBordes($largo, 'E', $sheet);
        self::crearBordes($largo, 'F', $sheet);
        self::crearBordes($largo, 'G', $sheet);
        self::crearBordes($largo, 'H', $sheet);
        self::crearBordes($largo, 'I', $sheet);
        self::crearBordes($largo, 'J', $sheet);
        self::crearBordes($largo, 'K', $sheet);
        self::crearBordes($largo, 'L', $sheet);
        self::crearBordes($largo, 'M', $sheet);
        self::crearBordes($largo, 'N', $sheet);
        self::crearBordes($largo, 'O', $sheet);

        //Llenar excel con el resultado del query
        $sheet->fromArray($res, null, 'C11');
        //Agregamos la fecha
        $sheet->setCellValue('O6', 'FECHA: ' . date('Y-m-d'));

        //Agregar el indice autonumerico

        for ($i = 1; $i <= $largo; $i++) {
            $inicio = 10 + $i;
            $sheet->setCellValue('B' . $inicio, $i);
        }

        //Para colocar datos de grupo y municipio
        $sheet->setCellValue('O4', $data2->MunicipioGrupo);
        $sheet->setCellValue('O5', $data2->NombreGrupo);

        //----------------------------------------Para colocar la firma-------------------------------------
        //combinar celdas
        $largo2 = $largo + 12;
        $largo3 = $largo + 15;

        $combinar1 = 'C' . $largo2 . ':E' . $largo2;
        $combinar2 = 'C' . $largo3 . ':E' . $largo3;

        $sheet->mergeCells($combinar1);
        $sheet->mergeCells($combinar2);

        //Colocar textos...
        $sheet
            ->getStyle('C' . $largo2)
            ->getFont()
            ->setBold(true);
        $sheet
            ->getStyle('C' . $largo2)
            ->getAlignment()
            ->setHorizontal(
                \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
            );
        $sheet->setCellValue('C' . $largo2, 'REPRESENTANTE DE LA SEDESHU');

        $sheet
            ->getStyle('C' . $largo3)
            ->getFont()
            ->setBold(true);
        $sheet
            ->getStyle('C' . $largo3)
            ->getAlignment()
            ->setHorizontal(
                \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
            );
        $sheet->setCellValue('C' . $largo3, 'NOMBRE Y FIRMA');
        $sheet
            ->getStyle($combinar2)
            ->getBorders()
            ->getTop()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );

        //guardamos el excel creado y luego lo obtenemos en $file para poder descargarlo
        $writer = new Xlsx($spreadsheet);
        $writer->save('archivos/reporteTrabajoTemporal.xlsx');
        $file = public_path() . '/archivos/reporteTrabajoTemporal.xlsx';

        return response()->download(
            $file,
            $data2->MunicipioGrupo . '_' . $data2->NombreGrupo . '.xlsx'
        );
    }

    public function getReporteComercios(Request $request)
    {
        // ,'d.FechaNacimientoC','d.SexoC as Sexo'

        $res = DB::table('v_negocios as N')
            ->select(
                'M.SubRegion AS Region',
                'N.id',
                'N.RFC',
                'N.NombreEmpresa',
                DB::raw(
                    "concat_ws(' ',N.Nombre, N.Paterno, N.Materno) AS NombreContacto"
                ),
                'N.TelNegocio',
                'N.TelCasa',
                'N.Celular',
                'N.Correo',
                'N.FechaInscripcion',
                'N.HorarioAtencion',
                'T.Tipo AS Categoria',
                'N.Banco',
                'N.CLABE',
                'N.Calle',
                'N.NumExt',
                'N.NumInt',
                'N.Colonia',
                'N.CP',
                'M.Nombre AS Municipio',
                'N.Latitude',
                'N.Longitude',
                'E.Estatus'
            )
            ->join('v_negocios_tipo as T', 'N.idTipoNegocio', '=', 'T.id')
            ->join('et_cat_municipio as M', 'N.idMunicipio', '=', 'M.Id')
            ->join('v_status as E', 'N.idStatus', '=', 'E.id');

        // $resGrupo = DB::table('v_giros as G')
        // ->select('G.Giro', 'NG.idNegocio', 'NG.idGiro')
        // ->join('v_negocios_giros as NG','NG.idGiro','=','G.id');

        if (isset($request->idNegocio)) {
            $res->where('N.id', $request->idNegocio);
            //$resGrupo->where('G.id',$request->idNegocio);
        }
        if (isset($request->idMunicipio)) {
            $res->where('N.idMunicipio', $request->idMunicipio);
        }

        $data = $res
            ->orderBy('M.Nombre', 'asc')
            ->orderBy('N.Colonia', 'asc')
            ->orderBy('N.NombreEmpresa', 'asc')
            ->get();
        //$data2 = $resGrupo->first();

        if (count($data) == 0) {
            return response()->json([
                'success' => false,
                'results' => false,
                'message' => 'No se encontraron datos',
            ]);
        }

        //Mapeamos el resultado como un array
        $res = $data
            ->map(function ($x) {
                $x = is_object($x) ? (array) $x : $x;
                return $x;
            })
            ->toArray();

        //------------------------------------------------- Para generar el archivo excel ----------------------------------------------------------------
        // $spreadsheet = new Spreadsheet();
        // $sheet = $spreadsheet->getActiveSheet();

        //Para los titulos del excel
        // $titulos = ['Grupo','Folio','Nombre','Paterno','Materno','Fecha de Nacimiento','Sexo','Calle','Numero','Colonia','Municipio','Localidad','CP','Terminación'];
        // $sheet->fromArray($titulos,null,'A1');
        // $sheet->getStyle('A1:N1')->getFont()->getColor()->applyFromArray(['rgb' => '808080']);

        $reader = IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load(
            public_path() . '/archivos/formatoComercioVales.xlsx'
        );
        $sheet = $spreadsheet->getActiveSheet();
        $largo = count($res);
        $impresion = $largo + 17;

        $sheet->getPageSetup()->setPrintArea('A1:W' . $impresion);
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_PORTRAIT);
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_LETTER);

        $largo = count($res);
        //colocar los bordes
        self::crearBordes($largo, 'B', $sheet);
        self::crearBordes($largo, 'C', $sheet);
        self::crearBordes($largo, 'D', $sheet);
        self::crearBordes($largo, 'E', $sheet);
        self::crearBordes($largo, 'F', $sheet);
        self::crearBordes($largo, 'G', $sheet);
        self::crearBordes($largo, 'H', $sheet);
        self::crearBordes($largo, 'I', $sheet);
        self::crearBordes($largo, 'J', $sheet);
        self::crearBordes($largo, 'K', $sheet);
        self::crearBordes($largo, 'L', $sheet);
        self::crearBordes($largo, 'M', $sheet);
        self::crearBordes($largo, 'N', $sheet);
        self::crearBordes($largo, 'O', $sheet);
        self::crearBordes($largo, 'P', $sheet);
        self::crearBordes($largo, 'Q', $sheet);
        self::crearBordes($largo, 'R', $sheet);
        self::crearBordes($largo, 'S', $sheet);
        self::crearBordes($largo, 'T', $sheet);
        self::crearBordes($largo, 'U', $sheet);
        self::crearBordes($largo, 'V', $sheet);
        self::crearBordes($largo, 'W', $sheet);
        self::crearBordes($largo, 'X', $sheet);
        self::crearBordes($largo, 'Y', $sheet);

        //Llenar excel con el resultado del query
        $sheet->fromArray($res, null, 'C11');
        //Agregamos la fecha
        $sheet->setCellValue('X6', 'Fecha Reporte: ' . date('Y-m-d'));

        //Agregar el indice autonumerico

        for ($i = 1; $i <= $largo; $i++) {
            $inicio = 10 + $i;
            $sheet->setCellValue('B' . $inicio, $i);
        }

        //Para colocar datos de grupo y municipio
        //$sheet->setCellValue('O4', $data2->MunicipioGrupo);
        //$sheet->setCellValue('O5', $data2->NombreGrupo);

        //----------------------------------------Para colocar la firma-------------------------------------
        //combinar celdas

        /*
        $largo2 = $largo+12;
        $largo3 = $largo+15;

        $combinar1 = 'C'.$largo2.':E'.$largo2;
        $combinar2 = 'C'.$largo3.':E'.$largo3;

        $sheet->mergeCells($combinar1);
        $sheet->mergeCells($combinar2);

        //Colocar textos...
        $sheet->getStyle('C'.$largo2)->getFont()->setBold(true);
        $sheet->getStyle('C'.$largo2)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->setCellValue('C'.$largo2, 'REPRESENTANTE DE LA SEDESHU');

        $sheet->getStyle('C'.$largo3)->getFont()->setBold(true);
        $sheet->getStyle('C'.$largo3)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->setCellValue('C'.$largo3, 'NOMBRE Y FIRMA');
        $sheet->getStyle($combinar2)->getBorders()->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        */

        //guardamos el excel creado y luego lo obtenemos en $file para poder descargarlo
        $writer = new Xlsx($spreadsheet);
        $writer->save('archivos/reporteComercioVales.xlsx');
        $file = public_path() . '/archivos/reporteComercioVales.xlsx';

        return response()->download(
            $file,
            'ComerciosValesGrandeza' . date('Y-m-d') . '.xlsx'
        );
    }

    public function downloadReporteComercios(Request $request)
    {
        //ini_set("max_execution_time", 800);

        $res = DB::table('v_negocios as N')
            ->select(
                'M.SubRegion AS Region',
                'N.id',
                DB::raw('LPAD(HEX(N.id),6,0) Folio'),
                'N.RFC',
                'N.NombreEmpresa',
                DB::raw(
                    "concat_ws(' ',N.Nombre, N.Paterno, N.Materno) AS NombreContacto"
                ),
                'N.TelNegocio',
                'N.TelCasa',
                'N.Celular',
                'N.Correo',
                'N.FechaInscripcion',
                'N.HorarioAtencion',
                'NT.Tipo AS Categoria',
                'N.Banco',
                'N.CLABE',
                'N.Calle',
                'N.NumExt',
                'N.NumInt',
                'N.Colonia',
                'N.CP',
                'M.Nombre AS Municipio',
                'N.Latitude',
                'N.Longitude',
                'E.Estatus',
                'N.updated_at',
                DB::raw(
                    "concat_ws(' ',U.Nombre, U.Paterno, U.Materno) AS Actualizo"
                )
            )
            //Zincri: Comente los joins originales porque no iban a coincidir con los filtros que estan almasenados.
            //->join('v_negocios_tipo as T','N.idTipoNegocio','=','NT.id')
            //->join('et_cat_municipio as M','N.idMunicipio','=','M.Id')
            //->join('v_negocios_status as E','N.idStatus','=','E.id');

            //Zincri: Estos son los joins que yo uso en la consulta getNegociosApp,
            //pero cambie los alias para que funcione aqui tambien.
            ->leftJoin('et_cat_municipio as M', 'M.Id', '=', 'N.idMunicipio')
            ->leftJoin('v_negocios_tipo as NT', 'NT.id', '=', 'N.idTipoNegocio')
            ->leftJoin('v_negocios_status as E', 'E.id', '=', 'N.idStatus')
            ->leftJoin('users as T', 'T.id', '=', 'N.UserCreated')
            ->leftJoin('users as U', 'U.id', '=', 'N.UserUpdated');

        $user = auth()->user();
        $filtro_usuario = VNegociosFiltros::where('idUser', '=', $user->id)
            ->where('api', '=', 'getNegociosApp')
            ->first();
        if ($filtro_usuario) {
            $hoy = date('Y-m-d H:i:s');
            $intervalo = $filtro_usuario->updated_at->diff($hoy);

            if ($intervalo->h === 0) {
                //Si es 0 es porque no ha pasado una hora.
                $parameters = unserialize($filtro_usuario->parameters);

                $flag = 0;
                $flag_ejercicio = false;
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
                            $flag = 1;
                        } else {
                            if ($parameters['tipo'] == 'and') {
                                switch ($parameters['filtered'][$i]['id']) {
                                    case 'id':
                                        if (
                                            is_array(
                                                $parameters['filtered'][$i][
                                                    'value'
                                                ]
                                            )
                                        ) {
                                            $res->whereIn(
                                                'id',
                                                $parameters['filtered'][$i][
                                                    'value'
                                                ]
                                            );
                                        } else {
                                            $res->where(
                                                'id',
                                                '=',
                                                $parameters['filtered'][$i][
                                                    'value'
                                                ]
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
                                        break;
                                    case 'Refrendo2021':
                                        $res->where(
                                            'N.' .
                                                $parameters['filtered'][$i][
                                                    'id'
                                                ],
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                        break;
                                    case 'Contacto':
                                        $contacto_buscar =
                                            $parameters['filtered'][$i][
                                                'value'
                                            ];
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
                                                $parameters['filtered'][$i][
                                                    'value'
                                                ]
                                            )
                                        ) {
                                            $res->whereIn(
                                                'N.idMunicipio',
                                                $parameters['filtered'][$i][
                                                    'value'
                                                ]
                                            );
                                        } else {
                                            $res->where(
                                                'N.idMunicipio',
                                                '=',
                                                $parameters['filtered'][$i][
                                                    'value'
                                                ]
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
                                                $parameters['filtered'][$i][
                                                    'value'
                                                ]
                                            )
                                        ) {
                                            $res->whereIn(
                                                'M.SubRegion',
                                                $parameters['filtered'][$i][
                                                    'value'
                                                ]
                                            );
                                        } else {
                                            $res->where(
                                                'M.SubRegion',
                                                '=',
                                                $parameters['filtered'][$i][
                                                    'value'
                                                ]
                                            );
                                        }
                                        break;
                                    case 'idTipoNegocio':
                                        if (
                                            is_array(
                                                $parameters['filtered'][$i][
                                                    'value'
                                                ]
                                            )
                                        ) {
                                            $res->whereIn(
                                                'N.idTipoNegocio',
                                                $parameters['filtered'][$i][
                                                    'value'
                                                ]
                                            );
                                        } else {
                                            $res->where(
                                                'N.idTipoNegocio',
                                                '=',
                                                $parameters['filtered'][$i][
                                                    'value'
                                                ]
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
                                                $parameters['filtered'][$i][
                                                    'value'
                                                ]
                                            )
                                        ) {
                                            $res->whereIn(
                                                'N.idStatus',
                                                $parameters['filtered'][$i][
                                                    'value'
                                                ]
                                            );
                                        } else {
                                            $res->where(
                                                'N.idStatus',
                                                '=',
                                                $parameters['filtered'][$i][
                                                    'value'
                                                ]
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
                                                $parameters['filtered'][$i][
                                                    'value'
                                                ]
                                            )
                                        ) {
                                            $res->orWhereIn(
                                                'id',
                                                $parameters['filtered'][$i][
                                                    'value'
                                                ]
                                            );
                                        } else {
                                            $res->orWhere(
                                                'id',
                                                '=',
                                                $parameters['filtered'][$i][
                                                    'value'
                                                ]
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
                                        break;
                                    case 'Refrendo2021':
                                        $res->orWhere(
                                            'N.' .
                                                $parameters['filtered'][$i][
                                                    'id'
                                                ],
                                            '=',
                                            $parameters['filtered'][$i]['value']
                                        );
                                        break;
                                    case 'Contacto':
                                        $contacto_buscar =
                                            $parameters['filtered'][$i][
                                                'value'
                                            ];
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
                                                $parameters['filtered'][$i][
                                                    'value'
                                                ]
                                            )
                                        ) {
                                            $res->orWhereIn(
                                                'N.idMunicipio',
                                                $parameters['filtered'][$i][
                                                    'value'
                                                ]
                                            );
                                        } else {
                                            $res->orWhere(
                                                'N.idMunicipio',
                                                '=',
                                                $parameters['filtered'][$i][
                                                    'value'
                                                ]
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
                                                $parameters['filtered'][$i][
                                                    'value'
                                                ]
                                            )
                                        ) {
                                            $res->orWhereIn(
                                                'M.SubRegion',
                                                $parameters['filtered'][$i][
                                                    'value'
                                                ]
                                            );
                                        } else {
                                            $res->orWhere(
                                                'M.SubRegion',
                                                '=',
                                                $parameters['filtered'][$i][
                                                    'value'
                                                ]
                                            );
                                        }
                                        break;
                                    case 'idTipoNegocio':
                                        if (
                                            is_array(
                                                $parameters['filtered'][$i][
                                                    'value'
                                                ]
                                            )
                                        ) {
                                            $res->orWhereIn(
                                                'idTipoNegocio',
                                                $parameters['filtered'][$i][
                                                    'value'
                                                ]
                                            );
                                        } else {
                                            $res->orWhere(
                                                'idTipoNegocio',
                                                '=',
                                                $parameters['filtered'][$i][
                                                    'value'
                                                ]
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
                                                $parameters['filtered'][$i][
                                                    'value'
                                                ]
                                            )
                                        ) {
                                            $res->orWhereIn(
                                                'N.idStatus',
                                                $parameters['filtered'][$i][
                                                    'value'
                                                ]
                                            );
                                        } else {
                                            $res->orWhere(
                                                'N.idStatus',
                                                '=',
                                                $parameters['filtered'][$i][
                                                    'value'
                                                ]
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
                if (isset($parameters['sorted'])) {
                    for ($i = 0; $i < count($parameters['sorted']); $i++) {
                        if ($parameters['sorted'][$i]['desc'] === true) {
                            $res->orderBy(
                                $parameters['sorted'][$i]['id'],
                                'desc'
                            );
                        } else {
                            $res->orderBy(
                                $parameters['sorted'][$i]['id'],
                                'asc'
                            );
                        }
                    }
                }
            }
        }

        if (isset($request->idNegocio)) {
            $res->where('N.id', $request->idNegocio);
        }
        if (isset($request->idMunicipio)) {
            $res->where('N.idMunicipio', $request->idMunicipio);
        }

        $data = $res
            ->orderBy('M.Nombre', 'asc')
            ->orderBy('N.Colonia', 'asc')
            ->orderBy('N.NombreEmpresa', 'asc')
            ->get();

        if (count($data) == 0) {
            return response()->json([
                'success' => false,
                'results' => false,
                'message' => 'No se encontraron datos',
            ]);
        }

        //Mapeamos el resultado como un array
        $res = $data
            ->map(function ($x) {
                $x = is_object($x) ? (array) $x : $x;
                return $x;
            })
            ->toArray();

        //------------------------------------------------- Para generar el archivo excel ----------------------------------------------------------------
        // $spreadsheet = new Spreadsheet();
        // $sheet = $spreadsheet->getActiveSheet();

        //Para los titulos del excel
        // $titulos = ['Grupo','Folio','Nombre','Paterno','Materno','Fecha de Nacimiento','Sexo','Calle','Numero','Colonia','Municipio','Localidad','CP','Terminación'];
        // $sheet->fromArray($titulos,null,'A1');
        // $sheet->getStyle('A1:N1')->getFont()->getColor()->applyFromArray(['rgb' => '808080']);

        $reader = IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load(
            public_path() . '/archivos/formatoComercioVales.xlsx'
        );
        $sheet = $spreadsheet->getActiveSheet();
        $largo = count($res);
        $impresion = $largo + 17;

        $sheet->getPageSetup()->setPrintArea('A1:W' . $impresion);
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_PORTRAIT);
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_LETTER);

        $largo = count($res);
        //colocar los bordes
        self::crearBordes($largo, 'B', $sheet);
        self::crearBordes($largo, 'C', $sheet);
        self::crearBordes($largo, 'D', $sheet);
        self::crearBordes($largo, 'E', $sheet);
        self::crearBordes($largo, 'F', $sheet);
        self::crearBordes($largo, 'G', $sheet);
        self::crearBordes($largo, 'H', $sheet);
        self::crearBordes($largo, 'I', $sheet);
        self::crearBordes($largo, 'J', $sheet);
        self::crearBordes($largo, 'K', $sheet);
        self::crearBordes($largo, 'L', $sheet);
        self::crearBordes($largo, 'M', $sheet);
        self::crearBordes($largo, 'N', $sheet);
        self::crearBordes($largo, 'O', $sheet);
        self::crearBordes($largo, 'P', $sheet);
        self::crearBordes($largo, 'Q', $sheet);
        self::crearBordes($largo, 'R', $sheet);
        self::crearBordes($largo, 'S', $sheet);
        self::crearBordes($largo, 'T', $sheet);
        self::crearBordes($largo, 'U', $sheet);
        self::crearBordes($largo, 'V', $sheet);
        self::crearBordes($largo, 'W', $sheet);
        self::crearBordes($largo, 'X', $sheet);
        self::crearBordes($largo, 'Y', $sheet);

        //Llenar excel con el resultado del query
        $sheet->fromArray($res, null, 'C11');
        //Agregamos la fecha
        $sheet->setCellValue('X6', 'Fecha Reporte: ' . date('Y-m-d'));

        //Agregar el indice autonumerico

        for ($i = 1; $i <= $largo; $i++) {
            $inicio = 10 + $i;
            $sheet->setCellValue('B' . $inicio, $i);
        }

        //Para colocar datos de grupo y municipio
        //$sheet->setCellValue('O4', $data2->MunicipioGrupo);
        //$sheet->setCellValue('O5', $data2->NombreGrupo);

        //----------------------------------------Para colocar la firma-------------------------------------
        //combinar celdas

        /*
        $largo2 = $largo+12;
        $largo3 = $largo+15;

        $combinar1 = 'C'.$largo2.':E'.$largo2;
        $combinar2 = 'C'.$largo3.':E'.$largo3;

        $sheet->mergeCells($combinar1);
        $sheet->mergeCells($combinar2);

        //Colocar textos...
        $sheet->getStyle('C'.$largo2)->getFont()->setBold(true);
        $sheet->getStyle('C'.$largo2)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->setCellValue('C'.$largo2, 'REPRESENTANTE DE LA SEDESHU');

        $sheet->getStyle('C'.$largo3)->getFont()->setBold(true);
        $sheet->getStyle('C'.$largo3)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->setCellValue('C'.$largo3, 'NOMBRE Y FIRMA');
        $sheet->getStyle($combinar2)->getBorders()->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        */

        //guardamos el excel creado y luego lo obtenemos en $file para poder descargarlo
        $writer = new Xlsx($spreadsheet);
        $writer->save('archivos/reporteComercioVales.xlsx');
        $file = public_path() . '/archivos/reporteComercioVales.xlsx';

        return response()->download(
            $file,
            'ComerciosValesGrandeza' . date('Y-m-d') . '.xlsx'
        );
    }

    public function getReportesolicitudValesOldOriginal(Request $request)
    {
        // ,'d.FechaNacimientoC','d.SexoC as Sexo'

        $user = auth()->user();
        $parameters['UserCreated'] = $user->id;

        $res = DB::table('vales as N')
            ->select(
                'M.SubRegion AS Region',
                DB::raw('LPAD(HEX(N.id),6,0) AS ClaveUnica'),
                'N.FechaSolicitud',
                'N.CURP',
                'N.Nombre',
                'N.Paterno',
                'N.Materno',
                DB::raw("IF (N.Sexo = 'M', 'H', 'M')"),
                'N.FechaNacimiento',
                'N.Calle',
                'N.NumExt',
                'N.NumInt',
                'N.Colonia',
                'N.CP',
                'M.Nombre AS Municipio',
                'L.Nombre AS Localidad',
                'N.TelFijo',
                'N.TelCelular',
                'N.Compania',
                'N.TelRecados',
                'N.CorreoElectronico',
                'E.Estatus',
                DB::raw(
                    "concat_ws(' ',UC.Nombre, UC.Paterno, UC.Materno) as UserInfoCapturo"
                ),
                DB::raw(
                    "concat_ws(' ',UOC.Nombre, UOC.Paterno, UOC.Materno) as UserInfoOwned"
                )
            )
            ->leftJoin('et_cat_municipio as M', 'N.idMunicipio', '=', 'M.Id')
            ->leftJoin(
                'et_cat_localidad_2022 as L',
                'N.idLocalidad',
                '=',
                'L.Id'
            )
            ->leftJoin('users as UC', 'UC.id', '=', 'N.UserCreated')
            ->leftJoin('users as UOC', 'UOC.id', '=', 'N.UserOwned')
            ->join('vales_status as E', 'N.idStatus', '=', 'E.id')
            ->where('N.UserCreated', '=', $user->id)
            ->orderBy('N.created_at')
            ->orderBy('M.Nombre')
            ->orderBy('L.Nombre')
            ->orderBy('N.Nombre');

        // $resGrupo = DB::table('v_giros as G')
        // ->select('G.Giro', 'NG.idNegocio', 'NG.idGiro')
        // ->join('v_negocios_giros as NG','NG.idGiro','=','G.id');

        $data = $res
            ->orderBy('M.Nombre', 'asc')
            ->orderBy('N.Colonia', 'asc')
            ->orderBy('N.Nombre', 'asc')
            ->orderBy('N.Paterno', 'asc')
            ->get();
        //$data2 = $resGrupo->first();

        // dd($data);

        if (count($data) == 0) {
            //return response()->json(['success'=>false,'results'=>false,'message'=>$res->toSql()]);
            $reader = IOFactory::createReader('Xlsx');
            $spreadsheet = $reader->load(
                public_path() . '/archivos/formatoReporteSolicitudVales.xlsx'
            );
            $writer = new Xlsx($spreadsheet);
            $writer->save(
                'archivos/' . $user->email . 'reporteComercioVales.xlsx'
            );
            $file =
                public_path() .
                '/archivos/' .
                $user->email .
                'reporteComercioVales.xlsx';

            return response()->download(
                $file,
                'SolicitudesValesGrandeza' . date('Y-m-d') . '.xlsx'
            );
        }

        //Mapeamos el resultado como un array
        $res = $data
            ->map(function ($x) {
                $x = is_object($x) ? (array) $x : $x;
                return $x;
            })
            ->toArray();

        //------------------------------------------------- Para generar el archivo excel ----------------------------------------------------------------
        // $spreadsheet = new Spreadsheet();
        // $sheet = $spreadsheet->getActiveSheet();

        //Para los titulos del excel
        // $titulos = ['Grupo','Folio','Nombre','Paterno','Materno','Fecha de Nacimiento','Sexo','Calle','Numero','Colonia','Municipio','Localidad','CP','Terminación'];
        // $sheet->fromArray($titulos,null,'A1');
        // $sheet->getStyle('A1:N1')->getFont()->getColor()->applyFromArray(['rgb' => '808080']);

        $reader = IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load(
            public_path() . '/archivos/formatoReporteSolicitudVales.xlsx'
        );
        $sheet = $spreadsheet->getActiveSheet();
        $largo = count($res);
        $impresion = $largo + 17;

        $sheet->getPageSetup()->setPrintArea('A1:V' . $impresion);
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_PORTRAIT);
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_LETTER);

        $largo = count($res);
        //colocar los bordes
        self::crearBordes($largo, 'B', $sheet);
        self::crearBordes($largo, 'C', $sheet);
        self::crearBordes($largo, 'D', $sheet);
        self::crearBordes($largo, 'E', $sheet);
        self::crearBordes($largo, 'F', $sheet);
        self::crearBordes($largo, 'G', $sheet);
        self::crearBordes($largo, 'H', $sheet);
        self::crearBordes($largo, 'I', $sheet);
        self::crearBordes($largo, 'J', $sheet);
        self::crearBordes($largo, 'K', $sheet);
        self::crearBordes($largo, 'L', $sheet);
        self::crearBordes($largo, 'M', $sheet);
        self::crearBordes($largo, 'N', $sheet);
        self::crearBordes($largo, 'O', $sheet);
        self::crearBordes($largo, 'P', $sheet);
        self::crearBordes($largo, 'Q', $sheet);
        self::crearBordes($largo, 'R', $sheet);
        self::crearBordes($largo, 'S', $sheet);
        self::crearBordes($largo, 'T', $sheet);
        self::crearBordes($largo, 'U', $sheet);
        self::crearBordes($largo, 'V', $sheet);
        self::crearBordes($largo, 'W', $sheet);
        self::crearBordes($largo, 'X', $sheet);
        self::crearBordes($largo, 'Y', $sheet);
        self::crearBordes($largo, 'Z', $sheet);

        //Llenar excel con el resultado del query
        $sheet->fromArray($res, null, 'C11');
        //Agregamos la fecha
        $sheet->setCellValue('W6', 'Fecha Reporte: ' . date('Y-m-d'));

        //Agregar el indice autonumerico

        for ($i = 1; $i <= $largo; $i++) {
            $inicio = 10 + $i;
            $sheet->setCellValue('B' . $inicio, $i);
        }

        //guardamos el excel creado y luego lo obtenemos en $file para poder descargarlo
        $writer = new Xlsx($spreadsheet);
        $writer->save('archivos/' . $user->email . 'reporteComercioVales.xlsx');
        $file =
            public_path() .
            '/archivos/' .
            $user->email .
            'reporteComercioVales.xlsx';

        return response()->download(
            $file,
            $user->email . 'SolicitudesValesGrandeza' . date('Y-m-d') . '.xlsx'
        );
    }

    public function getReportesolicitudVales(Request $request)
    {
        $user = auth()->user();
        $parameters['UserCreated'] = $user->id;

        $res = DB::table('vales')
            ->select(
                'et_cat_municipio.SubRegion',
                DB::raw('LPAD(HEX(vales.id),6,0) as ClaveUnica'),
                'vales.FechaSolicitud',
                'vales.CURP',
                DB::raw(
                    "concat_ws(' ',vales.Nombre, vales.Paterno, vales.Materno) as NombreCompleto"
                ),
                'vales.Sexo',
                'vales.FechaNacimiento',
                'vales.Ocupacion',
                DB::raw(
                    "concat_ws(' ',vales.Calle, concat('Num. ', vales.NumExt), if(vales.NumInt is not null,concat('NumExt. ',vales.NumInt), ''), concat('Col. ',vales.Colonia)) as Direccion"
                ),
                'vales.CP',
                'et_cat_municipio.Nombre AS Municipio',
                'et_cat_localidad_2022.Nombre AS Localidad',
                'vales.TelFijo',
                'vales.TelCelular',
                'vales.Compania',
                'vales.TelRecados',
                'vales.CorreoElectronico',
                'vales.IngresoPercibido',
                'vales.OtrosIngresos',
                DB::raw(
                    '(vales.IngresoPercibido + vales.OtrosIngresos) as TotalIngresos'
                ),
                'vales.NumeroPersonas',
                'vales_incidencias.Incidencia',
                'vales.isEntregado',
                'vales.entrega_at',
                'vales.isDocumentacionEntrega',
                'vales.FechaDocumentacion',
                DB::raw(
                    'concat_ws(UD.Nombre, UD.Paterno, UD.Materno) as UserDocumentacion'
                ),
                'vales.Remesa',
                'vales_status.Estatus',
                DB::raw(
                    "concat_ws(' ',users.Nombre, users.Paterno, users.Materno) as UserInfoCapturo"
                ),
                DB::raw(
                    "concat_ws(' ',usersC.Nombre, usersC.Paterno, usersC.Materno) as UserInfoOwned"
                )
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
                'et_cat_localidad_2022',
                'et_cat_localidad_2022.Id',
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
            ->leftJoin('users as usersB', 'usersB.id', '=', 'vales.UserUpdated')
            ->leftJoin(
                'cat_usertipo as cat_usertipoB',
                'cat_usertipoB.id',
                '=',
                'usersB.idTipoUser'
            )
            ->leftJoin('users as usersC', 'usersC.id', '=', 'vales.UserOwned')
            ->leftJoin(
                'users as usersCretaed',
                'usersCretaed.id',
                '=',
                'vales.UserCreated'
            )
            ->leftJoin('users as UD', 'vales.idUserDocumentacion', '=', 'UD.id')
            ->leftJoin(
                'cat_usertipo as cat_usertipoC',
                'cat_usertipoC.id',
                '=',
                'usersC.idTipoUser'
            );

        //dd($res->toSql());

        //agregando los filtros

        $filtro_usuario = VNegociosFiltros::where('idUser', '=', $user->id)
            ->where('api', '=', 'getValesV2')
            ->first();
        if ($filtro_usuario) {
            $hoy = date('Y-m-d H:i:s');
            $intervalo = $filtro_usuario->updated_at->diff($hoy);
            if ($intervalo->h === 0) {
                //Si es 0 es porque no ha pasado una hora.
                $parameters = unserialize($filtro_usuario->parameters);

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

                if (
                    isset($parameters['Folio']) &&
                    !is_null($parameters['Folio'])
                ) {
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
                        $res->where(
                            'vales.Colonia',
                            '=',
                            $parameters['Colonia']
                        );
                    }
                }
                if (
                    isset($parameters['idStatus']) &&
                    !is_null($parameters['idStatus'])
                ) {
                    if (is_array($parameters['idStatus'])) {
                        $res->whereIn(
                            'vales_status.id',
                            $parameters['idStatus']
                        );
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
                        $res->whereIn(
                            'vales.UserOwned',
                            $parameters['UserOwned']
                        );
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
                                    ->whereIn(
                                        'vales.Remesa',
                                        $parameters['Remesa']
                                    )
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

                $page = $parameters['page'];
                $pageSize = $parameters['pageSize'];

                $startIndex = $page * $pageSize;

                //dd($parameters['sorted']);

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
        }

        $data = $res
            ->orderBy('vales.Nombre', 'asc')
            ->orderBy('vales.Colonia', 'asc')
            ->orderBy('vales.Nombre', 'asc')
            ->orderBy('vales.Paterno', 'asc')
            ->get();
        //$data2 = $resGrupo->first();

        //dd($data);

        //dd($data);

        if (count($data) == 0) {
            //return response()->json(['success'=>false,'results'=>false,'message'=>$res->toSql()]);
            $reader = IOFactory::createReader('Xlsx');
            $spreadsheet = $reader->load(
                public_path() . '/archivos/formatoReporteSolicitudValesV3.xlsx'
            );
            $writer = new Xlsx($spreadsheet);
            $writer->save(
                'archivos/' . $user->email . 'reporteComercioVales.xlsx'
            );
            $file =
                public_path() .
                '/archivos/' .
                $user->email .
                'reporteComercioVales.xlsx';

            return response()->download(
                $file,
                'SolicitudesValesGrandeza' . date('Y-m-d') . '.xlsx'
            );
        }

        //Mapeamos el resultado como un array
        $res = $data
            ->map(function ($x) {
                $x = is_object($x) ? (array) $x : $x;
                return $x;
            })
            ->toArray();

        //------------------------------------------------- Para generar el archivo excel ----------------------------------------------------------------
        // $spreadsheet = new Spreadsheet();
        // $sheet = $spreadsheet->getActiveSheet();

        //Para los titulos del excel
        // $titulos = ['Grupo','Folio','Nombre','Paterno','Materno','Fecha de Nacimiento','Sexo','Calle','Numero','Colonia','Municipio','Localidad','CP','Terminación'];
        // $sheet->fromArray($titulos,null,'A1');
        // $sheet->getStyle('A1:N1')->getFont()->getColor()->applyFromArray(['rgb' => '808080']);

        $reader = IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load(
            public_path() . '/archivos/formatoReporteSolicitudValesV4.xlsx'
        );
        $sheet = $spreadsheet->getActiveSheet();
        $largo = count($res);
        $impresion = $largo + 10;

        $sheet->getPageSetup()->setPrintArea('A1:V' . $impresion);
        $sheet
            ->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_LETTER);

        $largo = count($res);
        //colocar los bordes
        // self::crearBordes($largo, 'B', $sheet);
        // self::crearBordes($largo, 'C', $sheet);
        // self::crearBordes($largo, 'D', $sheet);
        // self::crearBordes($largo, 'E', $sheet);
        // self::crearBordes($largo, 'F', $sheet);
        // self::crearBordes($largo, 'G', $sheet);
        // self::crearBordes($largo, 'H', $sheet);
        // self::crearBordes($largo, 'I', $sheet);
        // self::crearBordes($largo, 'J', $sheet);
        // self::crearBordes($largo, 'K', $sheet);
        // self::crearBordes($largo, 'L', $sheet);
        // self::crearBordes($largo, 'M', $sheet);
        // self::crearBordes($largo, 'N', $sheet);
        // self::crearBordes($largo, 'O', $sheet);
        // self::crearBordes($largo, 'P', $sheet);
        // self::crearBordes($largo, 'Q', $sheet);
        // self::crearBordes($largo, 'R', $sheet);
        // self::crearBordes($largo, 'S', $sheet);
        // self::crearBordes($largo, 'T', $sheet);
        // self::crearBordes($largo, 'U', $sheet);
        // self::crearBordes($largo, 'V', $sheet);
        // self::crearBordes($largo, 'W', $sheet);
        // self::crearBordes($largo, 'X', $sheet);
        // self::crearBordes($largo, 'Y', $sheet);
        // self::crearBordes($largo, 'Z', $sheet);
        // self::crearBordes($largo, 'AA', $sheet);
        // self::crearBordes($largo, 'AB', $sheet);
        // self::crearBordes($largo, 'AC', $sheet);
        // self::crearBordes($largo, 'AD', $sheet);
        // self::crearBordes($largo, 'AE', $sheet);
        // self::crearBordes($largo, 'AF', $sheet);
        // self::crearBordes($largo, 'AG', $sheet);

        //Llenar excel con el resultado del query
        $sheet->fromArray($res, null, 'C11');
        //Agregamos la fecha
        $sheet->setCellValue('U6', 'Fecha Reporte: ' . date('Y-m-d H:i:s'));

        //Agregar el indice autonumerico

        for ($i = 1; $i <= $largo; $i++) {
            $inicio = 10 + $i;
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
            'archivos/' . $user->email . 'SolicitudesValesGrandeza.xlsx'
        );
        $file =
            public_path() .
            '/archivos/' .
            $user->email .
            'SolicitudesValesGrandeza.xlsx';

        return response()->download(
            $file,
            $user->email .
                'SolicitudesValesGrandeza' .
                date('Y-m-d H:i:s') .
                '.xlsx'
        );
    }

    public function getPadronPotencial(Request $request)
    {
        // ,'d.FechaNacimientoC','d.SexoC as Sexo'
        //ini_set("max_execution_time", 800);
        //ini_set('memory_limit','-1');
        $user = auth()->user();
        $parameters['UserCreated'] = $user->id;

        $res = DB::table('vales')
            ->select(
                'vales.Nombre',
                'vales.Paterno',
                'vales.Materno',
                'vales.FechaNacimiento',
                DB::raw("if(vales.Sexo ='F', 2, 1) as Sexo"),
                'EC.CODIGO',
                'vales.CURP',
                DB::raw('left(CURP, 10) as RFC'),
                'et_cat_municipio.Nombre AS Municipio',
                DB::raw(
                    "concat('11', lpad(et_cat_localidad_2022.IdMunicipio,3,0), lpad(sedeshu.et_cat_localidad_2022.Numero, 4, 0)) as NumeroLocalidad"
                ),
                'et_cat_localidad_2022.Nombre AS Localidad',
                'vales.Colonia',
                DB::raw("' ' as Manzana"),
                'vales.Calle',
                'vales.NumExt',
                'vales.NumInt',
                'vales.CP',
                DB::raw("'SDDH' as Dependencia"),
                DB::raw("'Q3450' as ProyectoInversion"),
                DB::raw("'2021' as EjercicioFiscal"),
                DB::raw("'10' as Unidad"),
                DB::raw("'500' as Monto")
            )
            ->leftJoin(
                'cat_edo_curp as EC',
                DB::raw('SUBSTRING(vales.CURP, 12, 2)'),
                '=',
                'EC.Clave'
            )
            ->leftJoin(
                'et_cat_municipio',
                'et_cat_municipio.Id',
                '=',
                'vales.idMunicipio'
            )
            ->leftJoin(
                'et_cat_localidad_2022',
                'et_cat_localidad_2022.Id',
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
            ->leftJoin('users as usersB', 'usersB.id', '=', 'vales.UserUpdated')
            ->leftJoin(
                'cat_usertipo as cat_usertipoB',
                'cat_usertipoB.id',
                '=',
                'usersB.idTipoUser'
            )
            ->leftJoin('users as usersC', 'usersC.id', '=', 'vales.UserOwned')
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
            ->whereNull('vales.Remesa')
            ->where(DB::raw('YEAR(vales.FechaSolicitud)'), '=', date('Y'))
            ->whereRaw(
                'vales.CURP not in (Select CURP from vales where YEAR(FechaSolicitud)=' .
                    date('Y') .
                    ' and Remesa is not null)'
            );

        $filtro_usuario = VNegociosFiltros::where('idUser', '=', $user->id)
            ->where('api', '=', 'getValesV2')
            ->first();
        if ($filtro_usuario) {
            $hoy = date('Y-m-d H:i:s');
            $intervalo = $filtro_usuario->updated_at->diff($hoy);
            if ($intervalo->h === 0) {
                //Si es 0 es porque no ha pasado una hora.
                $parameters = unserialize($filtro_usuario->parameters);

                $flag = 0;
                if (isset($parameters['Ejercicio'])) {
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

                if (
                    isset($parameters['Folio']) &&
                    !is_null($parameters['Folio'])
                ) {
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
                        $res->where(
                            'vales.Colonia',
                            '=',
                            $parameters['Colonia']
                        );
                    }
                }
                if (
                    isset($parameters['idStatus']) &&
                    !is_null($parameters['idStatus'])
                ) {
                    if (is_array($parameters['idStatus'])) {
                        $res->whereIn(
                            'vales_status.id',
                            $parameters['idStatus']
                        );
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
                        $res->whereIn(
                            'vales.UserOwned',
                            $parameters['UserOwned']
                        );
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
                                    ->whereIn(
                                        'vales.Remesa',
                                        $parameters['Remesa']
                                    )
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

                $page = $parameters['page'];
                $pageSize = $parameters['pageSize'];

                $startIndex = $page * $pageSize;

                //dd($parameters['sorted']);
                if (isset($parameters['sorted'])) {
                    for ($i = 0; $i < count($parameters['sorted']); $i++) {
                        if ($parameters['sorted'][$i]['desc'] === true) {
                            $res->orderBy(
                                $parameters['sorted'][$i]['id'],
                                'desc'
                            );
                        } else {
                            $res->orderBy(
                                $parameters['sorted'][$i]['id'],
                                'asc'
                            );
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
            }
        }

        $data = $res
            ->orderBy('vales.Nombre', 'asc')
            ->orderBy('vales.Colonia', 'asc')
            ->orderBy('vales.Nombre', 'asc')
            ->orderBy('vales.Paterno', 'asc')
            ->get();

        if (count($data) == 0) {
            //return response()->json(['success'=>false,'results'=>false,'message'=>$res->toSql()]);
            $reader = IOFactory::createReader('Xlsx');
            $spreadsheet = $reader->load(
                public_path() . '/archivos/PadronPotencial2021_Personas.xlsx'
            );
            $writer = new Xlsx($spreadsheet);
            $writer->save(
                'archivos/' . $user->email . 'PadronPotencial2021_Personas.xlsx'
            );
            $file =
                public_path() .
                '/archivos/' .
                $user->email .
                'PadronPotencial2021_Personas.xlsx';

            return response()->download(
                $file,
                'PadronPotencial2021_Personas' . date('Y-m-d H:i:s') . '.xlsx'
            );
        }

        //Mapeamos el resultado como un array
        $res = $data
            ->map(function ($x) {
                $x = is_object($x) ? (array) $x : $x;
                return $x;
            })
            ->toArray();

        //------------------------------------------------- Para generar el archivo excel ----------------------------------------------------------------
        // $spreadsheet = new Spreadsheet();
        // $sheet = $spreadsheet->getActiveSheet();

        //Para los titulos del excel
        // $titulos = ['Grupo','Folio','Nombre','Paterno','Materno','Fecha de Nacimiento','Sexo','Calle','Numero','Colonia','Municipio','Localidad','CP','Terminación'];
        // $sheet->fromArray($titulos,null,'A1');
        // $sheet->getStyle('A1:N1')->getFont()->getColor()->applyFromArray(['rgb' => '808080']);

        $reader = IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load(
            public_path() . '/archivos/PadronPotencial2021_Personas.xlsx'
        );
        $sheet = $spreadsheet->getActiveSheet();
        $largo = count($res);
        $impresion = $largo + 1; //+10;

        $sheet->getPageSetup()->setPrintArea('A2:V' . $impresion);
        $sheet
            ->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_LETTER);

        //$largo = count($res);
        //colocar los bordes
        self::crearBordes($largo, 'A', $sheet);
        self::crearBordes($largo, 'B', $sheet);
        self::crearBordes($largo, 'C', $sheet);
        self::crearBordes($largo, 'D', $sheet);
        self::crearBordes($largo, 'E', $sheet);
        self::crearBordes($largo, 'F', $sheet);
        self::crearBordes($largo, 'G', $sheet);
        self::crearBordes($largo, 'H', $sheet);
        self::crearBordes($largo, 'I', $sheet);
        self::crearBordes($largo, 'J', $sheet);
        self::crearBordes($largo, 'K', $sheet);
        self::crearBordes($largo, 'L', $sheet);
        self::crearBordes($largo, 'M', $sheet);
        self::crearBordes($largo, 'N', $sheet);
        self::crearBordes($largo, 'O', $sheet);
        self::crearBordes($largo, 'P', $sheet);
        self::crearBordes($largo, 'Q', $sheet);
        self::crearBordes($largo, 'R', $sheet);
        self::crearBordes($largo, 'S', $sheet);
        self::crearBordes($largo, 'T', $sheet);
        self::crearBordes($largo, 'U', $sheet);
        self::crearBordes($largo, 'V', $sheet);

        //Llenar excel con el resultado del query
        $sheet->fromArray($res, null, 'A3');
        //Agregamos la fecha
        //$sheet->setCellValue('U6', 'Fecha Reporte: '.date('Y-m-d H:i:s'));

        //Agregar el indice autonumerico

        // for($i=1;$i<=$largo;$i++){
        //     $inicio = 10+$i;
        //     $sheet->setCellValue('B'.$inicio, $i);
        // }

        if ($largo > 75) {
            //     //dd('Se agrega lineBreak');
            for ($lb = 70; $lb < $largo; $lb += 70) {
                //         $veces++;
                //         //dd($largo);
                $sheet->setBreak(
                    'A' . ($lb + 10),
                    \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::BREAK_ROW
                );
            }
        }

        $sheet->getDefaultRowDimension()->setRowHeight(-1);

        //guardamos el excel creado y luego lo obtenemos en $file para poder descargarlo
        $writer = new Xlsx($spreadsheet);
        $writer->save(
            'archivos/' . $user->email . 'PadronPotencial2021_Personas.xlsx'
        );
        $file =
            public_path() .
            '/archivos/' .
            $user->email .
            'PadronPotencial2021_Personas.xlsx';

        return response()->download(
            $file,
            $user->email .
                'PadronPotencial2021_Personas' .
                date('Y-m-d H:i:s') .
                '.xlsx'
        );
    }

    public function getReportesolicitudValesDesglosado(Request $request)
    {
        $user = auth()->user();
        $parameters['UserCreated'] = $user->id;

        $res = DB::table('vales')
            ->select(
                'et_cat_municipio.SubRegion',
                'vales.id',
                DB::raw('LPAD(HEX(vales.id),6,0) as ClaveUnica'),
                'vales.FechaSolicitud',
                'vales.CURP',
                'vales.Nombre',
                'vales.Paterno',
                'vales.Materno',
                // DB::raw("concat_ws(' ',vales.Nombre, vales.Paterno, vales.Materno) as NombreCompleto"),
                DB::raw("IF (vales.Sexo = 'M', 'H', 'M')"),
                'vales.FechaNacimiento',
                'vales.Ocupacion',
                //DB::raw("concat_ws(' ',vales.Calle, concat('Num. ', vales.NumExt), if(vales.NumInt is not null,concat('NumExt. ',vales.NumInt), ''), concat('Col. ',vales.Colonia)) as Direccion"),
                DB::raw(
                    'CASE WHEN vales.Calle is null then "S/C" else vales.Calle end as Calle'
                ),
                DB::raw(
                    'CASE WHEN vales.NumExt is null then "S/N" else concat("Num. ",vales.NumExt) end as NumExt'
                ),
                DB::raw(
                    'CASE WHEN vales.NumInt is null then "S/N" else concat("Num. ",vales.NumInt) end as NumInt'
                ),
                DB::raw(
                    'CASE WHEN vales.Colonia is null then "S/C" else concat("Col. ",vales.Colonia) end as Colonia'
                ),
                //DB::raw('concat("Col. ",vales.Colonia)'),
                'vales.CP',
                'et_cat_municipio.Nombre AS Municipio',
                'et_cat_localidad_2022.Nombre AS Localidad',
                'vales.TelFijo',
                'vales.TelCelular',
                'vales.Compania',
                'vales.TelRecados',
                'vales.CorreoElectronico',
                'vales.IngresoPercibido',
                'vales.OtrosIngresos',
                DB::raw(
                    '(vales.IngresoPercibido + vales.OtrosIngresos) as TotalIngresos'
                ),
                'vales.NumeroPersonas',
                'vales.Remesa',
                'vales_status.Estatus',
                DB::raw(
                    "concat_ws(' ',users.Nombre, users.Paterno, users.Materno) as UserInfoCapturo"
                ),
                DB::raw(
                    "concat_ws(' ',usersC.Nombre, usersC.Paterno, usersC.Materno) as UserInfoOwned"
                )
            )
            ->leftJoin(
                'et_cat_municipio',
                'et_cat_municipio.Id',
                '=',
                'vales.idMunicipio'
            )
            ->leftJoin(
                'et_cat_localidad_2022',
                'et_cat_localidad_2022.Id',
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
            ->leftJoin('users as usersB', 'usersB.id', '=', 'vales.UserUpdated')
            ->leftJoin(
                'cat_usertipo as cat_usertipoB',
                'cat_usertipoB.id',
                '=',
                'usersB.idTipoUser'
            )
            ->leftJoin('users as usersC', 'usersC.id', '=', 'vales.UserOwned')
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

        //dd($res->toSql());

        //agregando los filtros

        $filtro_usuario = VNegociosFiltros::where('idUser', '=', $user->id)
            ->where('api', '=', 'getValesV2')
            ->first();
        if ($filtro_usuario) {
            $hoy = date('Y-m-d H:i:s');
            $intervalo = $filtro_usuario->updated_at->diff($hoy);
            if ($intervalo->h === 0) {
                //Si es 0 es porque no ha pasado una hora.
                $parameters = unserialize($filtro_usuario->parameters);

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
                /* if(isset($parameters['Propietario']) && !is_null($parameters['Propietario']) && $parameters['Propietario'] !== 'All'){
                    $valor_id = $parameters['Propietario'];
                    $res->where(function($q)use ($valor_id) {
                        $q->where('vales.UserCreated', $valor_id)
                          ->orWhere('vales.UserOwned', $valor_id);
                    });
                } */
                /* if (isset($parameters['Regiones']) && !is_null($parameters['Regiones'])){
                    if(is_array ($parameters['Regiones']))
                    {
                        $res->orwhereIn('et_cat_municipio.SubRegion',$parameters['Regiones']); 
                        $flagRegion=1;
                    }else if(isset($parameters['UserOwned']) && !is_null($parameters['UserOwned'])){
                        if(is_array ($parameters['UserOwned'])){
                            $res->whereIn('vales.UserOwned',$parameters['UserOwned']);
                        }else{
                            $res->where('vales.UserOwned','=',$parameters['UserOwned']);
                        }    
                    }
                } */
                //CODIGO REGIONES
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
                if (
                    isset($parameters['Folio']) &&
                    !is_null($parameters['Folio'])
                ) {
                    $valor_id = $parameters['Folio'];
                    $res->where(
                        DB::raw('LPAD(HEX(vales.id),6,0)'),
                        'like',
                        '%' . $valor_id . '%'
                    );
                }
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

                /*if(isset($parameters['Regiones']) && !is_null($parameters['Regiones'])){
                if(is_array ($parameters['Regiones'])){
                    $res->whereIn('et_cat_municipio.SubRegion',$parameters['Regiones']);
                }else{
                    $res->where('et_cat_municipio.SubRegion','=',$parameters['Regiones']);
                }    
            }*/

                if (
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
                        $res->where(
                            'vales.Colonia',
                            '=',
                            $parameters['Colonia']
                        );
                    }
                }
                if (
                    isset($parameters['idStatus']) &&
                    !is_null($parameters['idStatus'])
                ) {
                    if (is_array($parameters['idStatus'])) {
                        $res->whereIn(
                            'vales_status.id',
                            $parameters['idStatus']
                        );
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
                                    $q
                                        ->whereIn('vales.Remesa', $valor_id)
                                        ->orWhereNull('vales.Remesa');
                                })
                                ->orderBy('vales.Remesa');
                        } else {
                            $res
                                ->whereIn('vales.Remesa', $parameters['Remesa'])
                                ->orderBy('vales.Remesa');
                        }
                    } else {
                        if (strcmp($parameters['Remesa'], 'null') === 0) {
                            $res
                                ->whereNull('vales.Remesa')
                                ->orderBy('vales.Remesa');
                        } else {
                            $res
                                ->where(
                                    'vales.Remesa',
                                    '=',
                                    $parameters['Remesa']
                                )
                                ->orderBy('vales.Remesa');
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
                                                $parameters['filtered'][$i][
                                                    'value'
                                                ]
                                            )
                                        ) {
                                            $res->whereIn(
                                                $parameters['filtered'][$i][
                                                    'id'
                                                ],
                                                $parameters['filtered'][$i][
                                                    'value'
                                                ]
                                            );
                                        } else {
                                            $res->where(
                                                $parameters['filtered'][$i][
                                                    'id'
                                                ],
                                                '=',
                                                $parameters['filtered'][$i][
                                                    'value'
                                                ]
                                            );
                                        }
                                    } else {
                                        if (
                                            strcmp(
                                                $parameters['filtered'][$i][
                                                    'id'
                                                ],
                                                'vales.UserCreated'
                                            ) === 0 ||
                                            strcmp(
                                                $parameters['filtered'][$i][
                                                    'id'
                                                ],
                                                'vales.UserUpdated'
                                            ) === 0 ||
                                            strcmp(
                                                $parameters['filtered'][$i][
                                                    'id'
                                                ],
                                                'vales.UserOwned'
                                            ) === 0
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
                                                        $parameters['filtered'][
                                                            $i
                                                        ]['value'] .
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
                                                $parameters['filtered'][$i][
                                                    'value'
                                                ]
                                            )
                                        ) {
                                            $res->orWhereIn(
                                                $parameters['filtered'][$i][
                                                    'id'
                                                ],
                                                $parameters['filtered'][$i][
                                                    'value'
                                                ]
                                            );
                                        } else {
                                            $res->orWhere(
                                                $parameters['filtered'][$i][
                                                    'id'
                                                ],
                                                '=',
                                                $parameters['filtered'][$i][
                                                    'value'
                                                ]
                                            );
                                        }
                                    } else {
                                        if (
                                            strcmp(
                                                $parameters['filtered'][$i][
                                                    'id'
                                                ],
                                                'vales.UserCreated'
                                            ) === 0 ||
                                            strcmp(
                                                $parameters['filtered'][$i][
                                                    'id'
                                                ],
                                                'vales.UserUpdated'
                                            ) === 0
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
                                                        $parameters['filtered'][
                                                            $i
                                                        ]['value'] .
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

                //dd($parameters['sorted']);
                if (isset($parameters['sorted'])) {
                    for ($i = 0; $i < count($parameters['sorted']); $i++) {
                        if ($parameters['sorted'][$i]['desc'] === true) {
                            $res->orderBy(
                                $parameters['sorted'][$i]['id'],
                                'desc'
                            );
                        } else {
                            $res->orderBy(
                                $parameters['sorted'][$i]['id'],
                                'asc'
                            );
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
            }
        }

        $data = $res
            ->orderBy('vales.Nombre', 'asc')
            ->orderBy('vales.Colonia', 'asc')
            ->orderBy('vales.Nombre', 'asc')
            ->orderBy('vales.Paterno', 'asc')
            ->get();
        //$data2 = $resGrupo->first();

        //dd($data);

        if (count($data) == 0) {
            //return response()->json(['success'=>false,'results'=>false,'message'=>$res->toSql()]);
            $reader = IOFactory::createReader('Xlsx');
            $spreadsheet = $reader->load(
                public_path() .
                    '/archivos/formatoReporteSolicitudValesDesglosado.xlsx'
            );
            $writer = new Xlsx($spreadsheet);
            $writer->save(
                'archivos/' .
                    $user->email .
                    'formatoReporteSolicitudValesDesglosado.xlsx'
            );
            $file =
                public_path() .
                '/archivos/' .
                $user->email .
                'formatoReporteSolicitudValesDesglosado.xlsx';

            return response()->download(
                $file,
                'formatoReporteSolicitudValesDesglosado' .
                    date('Y-m-d') .
                    '.xlsx'
            );
        }

        //Mapeamos el resultado como un array
        $res = $data
            ->map(function ($x) {
                $x = is_object($x) ? (array) $x : $x;
                return $x;
            })
            ->toArray();

        //------------------------------------------------- Para generar el archivo excel ----------------------------------------------------------------
        // $spreadsheet = new Spreadsheet();
        // $sheet = $spreadsheet->getActiveSheet();

        //Para los titulos del excel
        // $titulos = ['Grupo','Folio','Nombre','Paterno','Materno','Fecha de Nacimiento','Sexo','Calle','Numero','Colonia','Municipio','Localidad','CP','Terminación'];
        // $sheet->fromArray($titulos,null,'A1');
        // $sheet->getStyle('A1:N1')->getFont()->getColor()->applyFromArray(['rgb' => '808080']);

        $reader = IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load(
            public_path() .
                '/archivos/formatoReporteSolicitudValesDesglosado.xlsx'
        );
        $sheet = $spreadsheet->getActiveSheet();
        $largo = count($res);
        $impresion = $largo + 10;

        $sheet->getPageSetup()->setPrintArea('A1:V' . $impresion);
        $sheet
            ->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_LETTER);

        $largo = count($res);
        //colocar los bordes
        self::crearBordes($largo, 'B', $sheet);
        self::crearBordes($largo, 'C', $sheet);
        self::crearBordes($largo, 'D', $sheet);
        self::crearBordes($largo, 'E', $sheet);
        self::crearBordes($largo, 'F', $sheet);
        self::crearBordes($largo, 'G', $sheet);
        self::crearBordes($largo, 'H', $sheet);
        self::crearBordes($largo, 'I', $sheet);
        self::crearBordes($largo, 'J', $sheet);
        self::crearBordes($largo, 'K', $sheet);
        self::crearBordes($largo, 'L', $sheet);
        self::crearBordes($largo, 'M', $sheet);
        self::crearBordes($largo, 'N', $sheet);
        self::crearBordes($largo, 'O', $sheet);
        self::crearBordes($largo, 'P', $sheet);
        self::crearBordes($largo, 'Q', $sheet);
        self::crearBordes($largo, 'R', $sheet);
        self::crearBordes($largo, 'S', $sheet);
        self::crearBordes($largo, 'T', $sheet);
        self::crearBordes($largo, 'U', $sheet);
        self::crearBordes($largo, 'V', $sheet);
        self::crearBordes($largo, 'W', $sheet);
        self::crearBordes($largo, 'X', $sheet);
        self::crearBordes($largo, 'Y', $sheet);
        self::crearBordes($largo, 'Z', $sheet);
        self::crearBordes($largo, 'AA', $sheet);
        self::crearBordes($largo, 'AB', $sheet);
        self::crearBordes($largo, 'AC', $sheet);
        self::crearBordes($largo, 'AD', $sheet);

        //Llenar excel con el resultado del query
        $sheet->fromArray($res, null, 'C11');
        //Agregamos la fecha
        $sheet->setCellValue('U6', 'Fecha Reporte: ' . date('Y-m-d H:i:s'));

        //Agregar el indice autonumerico

        for ($i = 1; $i <= $largo; $i++) {
            $inicio = 10 + $i;
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
                'formatoReporteSolicitudValesDesglosado.xlsx'
        );
        $file =
            public_path() .
            '/archivos/' .
            $user->email .
            'formatoReporteSolicitudValesDesglosado.xlsx';

        return response()->download(
            $file,
            $user->email .
                'formatoReporteSolicitudValesDesglosado' .
                date('Y-m-d') .
                '.xlsx'
        );
    }

    public function getRepValesExpedientes(Request $request)
    {
        // ,'d.FechaNacimientoC','d.SexoC as Sexo'
        //ini_set("max_execution_time", 800);
        //ini_set('memory_limit','-1');
        $user = auth()->user();
        $parameters['UserCreated'] = $user->id;

        $res = DB::table('vales as V')
            ->select(
                /* DB::raw('LPAD(HEX(V.id),6,0) as FolioSolicitud'),
                'V.FechaSolicitud',
                'V.CURP',
                'V.Nombre',
                'V.Paterno',
                'V.Materno',
                'M.SubRegion AS Region',
                'M.Nombre AS Municipio',
                'V.isDocumentacionEntrega',
                'V.FechaDocumentacion',
                'V.idUserDocumentacion',
                'U.email as CelularRecepciono',
                DB::raw("concat_ws(' ',U.Nombre, U.Paterno, U.Materno) as UserRecepciono"), 
                
                 ->leftJoin('et_cat_municipio','et_cat_municipio.Id','=','V.idMunicipio')
            ->leftJoin('et_cat_localidad','et_cat_localidad.Id','=','V.idLocalidad')
            ->leftJoin('vales_status','vales_status.id','=','idStatus')
            ->leftJoin('users','users.id','=','V.UserCreated')

            ->leftJoin('cat_usertipo','cat_usertipo.id','=','users.idTipoUser')
            ->leftJoin('users as usersB','usersB.id','=','V.UserUpdated')
            ->leftJoin('cat_usertipo as cat_usertipoB','cat_usertipoB.id','=','usersB.idTipoUser')
            ->leftJoin('users as usersC','usersC.id','=','V.UserOwned')
            ->leftJoin('users as usersCretaed','usersCretaed.id','=','V.UserCreated')
            ->leftJoin('users as UR','UR.id','=','V.idUserDocumentacion')
            ->leftJoin('cat_usertipo as cat_usertipoC','cat_usertipoC.id','=','usersC.idTipoUser')
            ->where('V.isDocumentacionEntrega','=',1)
            ->whereNull('V.Remesa');
                
                */
                'M.SubRegion',
                DB::raw('LPAD(HEX(V.id),6,0) as Folio'),
                'V.FechaSolicitud',
                'V.CURP',
                DB::raw(
                    "concat_ws(' ',V.Nombre, V.Paterno, V.Materno) as NombreCompleto"
                ),
                'V.Sexo',
                'V.FechaNacimiento',
                DB::raw(
                    "concat_ws(' ',V.Calle, concat('Num. ', V.NumExt), if(V.NumInt is not null,concat('NumExt. ',V.NumInt), ''), concat('Col. ',V.Colonia)) as Direccion"
                ),
                'V.CP',
                'M.Nombre AS Municipio',
                'et_cat_localidad_2022.Nombre AS Localidad',
                DB::raw(
                    "CASE WHEN V.TelFijo is null THEN 'S/T' ELSE V.TelFijo END as TelFijo"
                ),
                DB::raw(
                    "CASE WHEN V.TelCelular is null THEN 'S/T' ELSE V.TelCelular END as TelCelular"
                ),
                DB::raw(
                    "CASE WHEN V.Compania is null THEN 'S/C' ELSE V.Compania END as Compania"
                ),
                DB::raw(
                    "CASE WHEN V.TelRecados is null THEN 'S/T' ELSE V.TelRecados END as TelRecados"
                ),
                DB::raw(
                    "CASE WHEN V.CorreoElectronico is null THEN 'S/C' ELSE V.CorreoElectronico END as CorreoElectronico"
                ),
                'V.FechaDocumentacion',
                DB::raw(
                    "concat_ws(' ',U.Nombre, U.Paterno, U.Materno) as Recepciono"
                ),
                DB::raw(
                    "concat_ws(' ',users.Nombre, users.Paterno, users.Materno) as UserInfoCapturo"
                ),
                DB::raw(
                    "concat_ws(' ',R.Nombre, R.Paterno, R.Materno) as UserInfoOwned"
                ),
                DB::raw(
                    "concat_ws(' ',V.IngresoPercibido) as IngresoPercibido"
                ),
                DB::raw("concat_ws(' ',V.OtrosIngresos) as OtrosIngresos"),
                DB::raw("concat_ws(' ',V.TotalIngresos) as TotalIngresos"),
                DB::raw("concat_ws(' ',V.NumeroPersonas) as NumeroPersonas"),
                'V.Ocupacion'
            )
            ->leftJoin('et_cat_municipio as M', 'V.idMunicipio', '=', 'M.Id')
            ->leftJoin(
                'et_cat_localidad_2022',
                'et_cat_localidad_2022.Id',
                '=',
                'V.idLocalidad'
            )
            ->leftJoin('vales_status', 'vales_status.id', '=', 'idStatus')
            ->leftJoin('users', 'users.id', '=', 'V.UserCreated')
            ->leftJoin('users as U', 'V.idUserDocumentacion', '=', 'U.id')
            ->leftJoin('users as R', 'V.UserOwned', '=', 'R.id')
            ->where('V.isDocumentacionEntrega', '=', 1)
            ->whereNull('V.Remesa');

        $filtro_usuario = VNegociosFiltros::where('idUser', '=', $user->id)
            ->where('api', '=', 'getValesDocumentacion')
            ->first();

        if ($filtro_usuario) {
            $hoy = date('Y-m-d H:i:s');
            $intervalo = $filtro_usuario->updated_at->diff($hoy);
            if ($intervalo->h === 0) {
                //Si es 0 es porque no ha pasado una hora.
                $parameters = unserialize($filtro_usuario->parameters);
                //dd($parameters);
                $flag = 0;

                // if(isset($parameters['Folio']) && !is_null($parameters['Folio'])){
                //     $valor_id = $parameters['Folio'];
                //     $res->where(DB::raw('LPAD(HEX(V.id),6,0)'),'like','%'.$valor_id.'%');
                // }

                // if(isset($parameters['Propietario'])){
                //     $valor_id = $parameters['Propietario'];
                //     $res->where(function($q)use ($valor_id) {
                //         $q->where('V.UserCreated', $valor_id)
                //           ->orWhere('V.UserOwned', $valor_id);
                //     });
                // }

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
                    $res->where(
                        DB::raw('V.CURP'),
                        'like',
                        '%' . $valor_curp . '%'
                    );
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
                        $res->whereIn(
                            'V.idMunicipio',
                            $parameters['idMunicipio']
                        );
                    }
                }

                if (isset($parameters['filtered'])) {
                    for ($i = 0; $i < count($parameters['filtered']); $i++) {
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

                $page = $parameters['page'];
                $pageSize = $parameters['pageSize'];

                $startIndex = $page * $pageSize;
                if (isset($parameters['sorted'])) {
                    for ($i = 0; $i < count($parameters['sorted']); $i++) {
                        if ($parameters['sorted'][$i]['desc'] === true) {
                            $res->orderBy(
                                $parameters['sorted'][$i]['id'],
                                'desc'
                            );
                        } else {
                            $res->orderBy(
                                $parameters['sorted'][$i]['id'],
                                'asc'
                            );
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
                    $res->where(
                        DB::raw('YEAR(V.FechaSolicitud)'),
                        '=',
                        $valor_id
                    );
                }
            }
        }

        //dd($res->get());

        $data = $res
            ->orderBy('Recepciono', 'asc')
            ->orderBy('M.SubRegion', 'asc')
            ->orderBy('M.Nombre', 'asc')
            ->orderBy('V.Colonia', 'asc')
            ->orderBy('V.Nombre', 'asc')
            ->orderBy('V.Paterno', 'asc')
            ->get();
        //$data2 = $resGrupo->first();

        //dd($data);

        if (count($data) == 0) {
            //return response()->json(['success'=>false,'results'=>false,'message'=>$res->toSql()]);
            $reader = IOFactory::createReader('Xlsx');
            $spreadsheet = $reader->load(
                public_path() .
                    '/archivos/formatoReporteExpedientesRecibidos.xlsx'
            );
            $writer = new Xlsx($spreadsheet);
            $writer->save(
                'archivos/' . $user->email . 'ReporteExpedientesRecibidos.xlsx'
            );
            $file =
                public_path() .
                '/archivos/' .
                $user->email .
                'ReporteExpedientesRecibidos.xlsx';

            return response()->download(
                $file,
                'ReporteExpedientesRecibidos' . date('Y-m-d H:i:s') . '.xlsx'
            );
        }

        //Mapeamos el resultado como un array
        $res = $data
            ->map(function ($x) {
                $x = is_object($x) ? (array) $x : $x;
                return $x;
            })
            ->toArray();

        //------------------------------------------------- Para generar el archivo excel ----------------------------------------------------------------
        // $spreadsheet = new Spreadsheet();
        // $sheet = $spreadsheet->getActiveSheet();

        //Para los titulos del excel
        // $titulos = ['Grupo','Folio','Nombre','Paterno','Materno','Fecha de Nacimiento','Sexo','Calle','Numero','Colonia','Municipio','Localidad','CP','Terminación'];
        // $sheet->fromArray($titulos,null,'A1');
        // $sheet->getStyle('A1:N1')->getFont()->getColor()->applyFromArray(['rgb' => '808080']);

        $reader = IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load(
            public_path() . '/archivos/formatoReporteExpedientesRecibidos.xlsx'
        );
        $sheet = $spreadsheet->getActiveSheet();
        $largo = count($res);
        $impresion = $largo + 10;

        $sheet->getPageSetup()->setPrintArea('A1:V' . $impresion);
        $sheet
            ->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_LETTER);

        $largo = count($res);
        //colocar los bordes
        self::crearBordes($largo, 'B', $sheet);
        self::crearBordes($largo, 'C', $sheet);
        self::crearBordes($largo, 'D', $sheet);
        self::crearBordes($largo, 'E', $sheet);
        self::crearBordes($largo, 'F', $sheet);
        self::crearBordes($largo, 'G', $sheet);
        self::crearBordes($largo, 'H', $sheet);
        self::crearBordes($largo, 'I', $sheet);
        self::crearBordes($largo, 'J', $sheet);
        self::crearBordes($largo, 'K', $sheet);
        self::crearBordes($largo, 'L', $sheet);
        self::crearBordes($largo, 'M', $sheet);
        self::crearBordes($largo, 'N', $sheet);
        self::crearBordes($largo, 'O', $sheet);
        self::crearBordes($largo, 'P', $sheet);
        self::crearBordes($largo, 'Q', $sheet);
        self::crearBordes($largo, 'R', $sheet);
        self::crearBordes($largo, 'S', $sheet);
        self::crearBordes($largo, 'T', $sheet);
        self::crearBordes($largo, 'U', $sheet);
        self::crearBordes($largo, 'V', $sheet);
        /* 'IngresoPercibido',
                'OtrosIngresos',
                'TotalIngresos',
                'NumeroPersonas',
                'Ocupacion', */
        self::crearBordes($largo, 'W', $sheet);
        self::crearBordes($largo, 'X', $sheet);
        self::crearBordes($largo, 'Y', $sheet);
        self::crearBordes($largo, 'Z', $sheet);
        self::crearBordes($largo, 'AA', $sheet);

        //Llenar excel con el resultado del query
        $sheet->fromArray($res, null, 'C11');
        //Agregamos la fecha
        $sheet->setCellValue('U6', 'Fecha Reporte: ' . date('Y-m-d H:i:s'));

        //Agregar el indice autonumerico

        for ($i = 1; $i <= $largo; $i++) {
            $inicio = 10 + $i;
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
            'archivos/' . $user->email . 'ReporteExpedientesRecibidos.xlsx'
        );
        $file =
            public_path() .
            '/archivos/' .
            $user->email .
            'ReporteExpedientesRecibidos.xlsx';

        return response()->download(
            $file,
            $user->email .
                'ReporteExpedientesRecibidos' .
                date('Y-m-d H:i:s') .
                '.xlsx'
        );
    }

    public function getReporteFoliosValidar(Request $request)
    {
        $user = auth()->user();

        $page = $request->page;
        $pageSize = $request->pageSize;
        //Reporte Ing Christian 17Feb
        $res_ant = DB::table('_folios_validar')->select('Folio');

        $res_ant = $res_ant
            ->offset($page)
            ->take($pageSize)
            ->get();

        $res = [];
        foreach ($res_ant as $key => $value) {
            $varItem = $value;
            $val = $varItem->Folio;
            //`id`, `Ejercicio`, `idSolicitud`, `CURP`, `Nombre`, `Paterno`, `Materno`, `CodigoBarrasInicial`, `CodigoBarrasFinal`, `SerieInicial`, `SerieFinal`, `UserOwned`, `Articulador`, `idMunicipio`, `Municipio`, `Remesa`, `Comentario`, `created_at`, `UserCreated`, `updated_at`
            $resX = DB::table('vales_solicitudes')

                ->select(
                    //'id',
                    DB::raw('LPAD(HEX(idSolicitud),6,0) as FolioSolicitud'),
                    //DB::raw(''.$val.' as Folio'),
                    'SerieInicial',
                    'SerieFinal',
                    'CURP',
                    DB::raw(
                        'CONCAT_WS(" " , Nombre,Paterno,Materno) as Nombre'
                    ),
                    DB::raw(
                        'CASE WHEN Municipio is null then "S/M" else Municipio end as Municipio'
                    ),
                    'Remesa'
                )
                ->whereNull('Ejercicio')
                ->where('SerieInicial', '<=', $val)
                ->where('SerieFinal', '>=', $val)
                ->first();

            array_push($res, (array) $resX);
        }
        //return $res;
        if (count($res) == 0) {
            $reader = IOFactory::createReader('Xlsx');
            $spreadsheet = $reader->load(
                public_path() . '/archivos/validar_f.xlsx'
            );
            $writer = new Xlsx($spreadsheet);
            $writer->save('archivos/' . $user->email . 'validar_f.xlsx');
            $file =
                public_path() . '/archivos/' . $user->email . 'validar_f.xlsx';

            return response()->download(
                $file,
                'validar_f' . date('Y-m-d H:i:s') . '.xlsx'
            );
        }

        $reader = IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load(
            public_path() . '/archivos/validar_f.xlsx'
        );
        $sheet = $spreadsheet->getActiveSheet();
        $largo = count($res);
        $impresion = $largo + 10;

        $sheet->getPageSetup()->setPrintArea('A1:V' . $impresion);
        $sheet
            ->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_LETTER);

        $largo = count($res);

        //dd($res);
        //colocar los bordes
        self::crearBordes($largo, 'B', $sheet);
        self::crearBordes($largo, 'C', $sheet);
        self::crearBordes($largo, 'D', $sheet);
        self::crearBordes($largo, 'E', $sheet);
        self::crearBordes($largo, 'F', $sheet);
        self::crearBordes($largo, 'G', $sheet);
        self::crearBordes($largo, 'H', $sheet);
        self::crearBordes($largo, 'I', $sheet);
        $sheet->fromArray($res, null, 'C11');
        //Agregamos la fecha
        $sheet->setCellValue('U6', 'Fecha Reporte: ' . date('Y-m-d H:i:s'));

        //Agregar el indice autonumerico

        for ($i = 1; $i <= $largo; $i++) {
            $inicio = 10 + $i;
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
        $writer->save('archivos/' . $user->email . 'validar_f.xlsx');
        $file = public_path() . '/archivos/' . $user->email . 'validar_f.xlsx';

        return response()->download(
            $file,
            $user->email . 'validar_f' . date('Y-m-d H:i:s') . '.xlsx'
        );
    }

    public function getReportesoVales(Request $request)
    {
        //ini_set("max_execution_time", 800);
        // ,'d.FechaNacimientoC','d.SexoC as Sexo'

        //$user = auth()->user();
        //$parameters['UserCreated'] = $user->id;

        $res = DB::table('vales')
            ->select(
                'et_cat_municipio.SubRegion AS Region',
                DB::raw('LPAD(HEX(vales.id),6,0) AS ClaveUnica'),
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
                'et_cat_municipio.Nombre AS Municipio',
                'et_cat_localidad_2022.Nombre AS Localidad',
                'vales.TelFijo',
                'vales.TelCelular',
                'vales.Compania',
                'vales.TelRecados',
                'vales.CorreoElectronico',
                'vales_status.Estatus',
                DB::raw(
                    "concat_ws(' ',users.Nombre, users.Paterno, users.Materno) as UserInfoCapturo"
                ),
                DB::raw(
                    "concat_ws(' ',usersC.Nombre, usersC.Paterno, usersC.Materno) as UserInfoOwned"
                )
            )
            ->leftJoin(
                'et_cat_municipio',
                'et_cat_municipio.Id',
                '=',
                'vales.idMunicipio'
            )
            ->leftJoin(
                'et_cat_localidad_2022',
                'et_cat_localidad_2022.Id',
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
            ->leftJoin('users as usersB', 'usersB.id', '=', 'vales.UserUpdated')
            ->leftJoin(
                'cat_usertipo as cat_usertipoB',
                'cat_usertipoB.id',
                '=',
                'usersB.idTipoUser'
            )
            ->leftJoin('users as usersC', 'usersC.id', '=', 'vales.UserOwned')
            //->leftJoin('users as usersCretaed','usersCretaed.id','=','vales.UserCreated')
            ->leftJoin(
                'cat_usertipo as cat_usertipoC',
                'cat_usertipoC.id',
                '=',
                'usersC.idTipoUser'
            )
            /* ->leftJoin('et_cat_municipio as M','vales.idMunicipio','=','et_cat_municipio.Id')
        ->leftJoin('et_cat_localidad as L','vales.idLocalidad','=','et_cat_localidad.Id')
        ->leftJoin('users as UC','users.id','=','vales.UserCreated')
        ->leftJoin('users as UOC','usersB.id','=','vales.UserOwned')
        ->join('vales_status as E','vales.idStatus','=','vales_status.id')
        ->where('vales.UserCreated','=',$user->id) */
            ->orderBy('vales.created_at')
            ->orderBy('et_cat_municipio.Nombre')
            ->orderBy('et_cat_localidad_2022.Nombre')
            ->orderBy('vales.Nombre');

        //AQUI PONGO MIS FILTROS, PARA QUE LA CONSULTA GUARDADA SE REPLIQUE.

        $user = auth()->user();
        $filtro_usuario = VNegociosFiltros::where('idUser', '=', $user->id)
            ->where('api', '=', 'getVales')
            ->first();
        if ($filtro_usuario) {
            $hoy = date('Y-m-d H:i:s');
            $intervalo = $filtro_usuario->updated_at->diff($hoy);
            if ($intervalo->h === 0) {
                //Si es 0 es porque no ha pasado una hora.
                $parameters = unserialize($filtro_usuario->parameters);

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

                if (isset($parameters['filtered'])) {
                    for ($i = 0; $i < count($parameters['filtered']); $i++) {
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

                if (isset($parameters['sorted'])) {
                    for ($i = 0; $i < count($parameters['sorted']); $i++) {
                        if ($parameters['sorted'][$i]['desc'] === true) {
                            $res->orderBy(
                                $parameters['sorted'][$i]['id'],
                                'desc'
                            );
                        } else {
                            $res->orderBy(
                                $parameters['sorted'][$i]['id'],
                                'asc'
                            );
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
            }
        }
        //AQUI PONGO MIS FILTROS, PARA QUE LA CONSULTA GUARDADA SE REPLIQUE.

        $data = $res
            ->orderBy('et_cat_municipio.Nombre', 'asc')
            ->orderBy('vales.Colonia', 'asc')
            ->orderBy('vales.Nombre', 'asc')
            ->orderBy('vales.Paterno', 'asc')
            ->get();

        if (count($data) >= 3000) {
            return [
                'success' => true,
                'results' => false,
                'total' => count($data),
                'errors' => 'Limite de registros excedido',
                'message' =>
                    'El reporte contiene mas de 3,000 registros. Contacte al administrador.',
            ];
        }

        if (count($data) == 0) {
            $reader = IOFactory::createReader('Xlsx');
            $spreadsheet = $reader->load(
                public_path() . '/archivos/formatoReporteSolicitudVales.xlsx'
            );
            $writer = new Xlsx($spreadsheet);
            $writer->save(
                'archivos/' . $user->email . 'reporteComercioVales.xlsx'
            );
            $file =
                public_path() .
                '/archivos/' .
                $user->email .
                'reporteComercioVales.xlsx';

            return response()->download(
                $file,
                'SolicitudesValesGrandeza' . date('Y-m-d') . '.xlsx'
            );
        }

        //Mapeamos el resultado como un array
        $res = $data
            ->map(function ($x) {
                $x = is_object($x) ? (array) $x : $x;
                return $x;
            })
            ->toArray();

        //------------------------------------------------- Para generar el archivo excel ----------------------------------------------------------------
        // $spreadsheet = new Spreadsheet();
        // $sheet = $spreadsheet->getActiveSheet();

        //Para los titulos del excel
        // $titulos = ['Grupo','Folio','Nombre','Paterno','Materno','Fecha de Nacimiento','Sexo','Calle','Numero','Colonia','Municipio','Localidad','CP','Terminación'];
        // $sheet->fromArray($titulos,null,'A1');
        // $sheet->getStyle('A1:N1')->getFont()->getColor()->applyFromArray(['rgb' => '808080']);

        $reader = IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load(
            public_path() . '/archivos/formatoReporteSolicitudVales.xlsx'
        );
        $sheet = $spreadsheet->getActiveSheet();
        $largo = count($res);
        $impresion = $largo + 17;

        $sheet->getPageSetup()->setPrintArea('A1:V' . $impresion);
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_PORTRAIT);
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_LETTER);

        $largo = count($res);
        //colocar los bordes
        self::crearBordes($largo, 'B', $sheet);
        self::crearBordes($largo, 'C', $sheet);
        self::crearBordes($largo, 'D', $sheet);
        self::crearBordes($largo, 'E', $sheet);
        self::crearBordes($largo, 'F', $sheet);
        self::crearBordes($largo, 'G', $sheet);
        self::crearBordes($largo, 'H', $sheet);
        self::crearBordes($largo, 'I', $sheet);
        self::crearBordes($largo, 'J', $sheet);
        self::crearBordes($largo, 'K', $sheet);
        self::crearBordes($largo, 'L', $sheet);
        self::crearBordes($largo, 'M', $sheet);
        self::crearBordes($largo, 'N', $sheet);
        self::crearBordes($largo, 'O', $sheet);
        self::crearBordes($largo, 'P', $sheet);
        self::crearBordes($largo, 'Q', $sheet);
        self::crearBordes($largo, 'R', $sheet);
        self::crearBordes($largo, 'S', $sheet);
        self::crearBordes($largo, 'T', $sheet);
        self::crearBordes($largo, 'U', $sheet);
        self::crearBordes($largo, 'V', $sheet);
        self::crearBordes($largo, 'W', $sheet);
        self::crearBordes($largo, 'X', $sheet);
        self::crearBordes($largo, 'Y', $sheet);
        self::crearBordes($largo, 'Z', $sheet);

        //Llenar excel con el resultado del query
        $sheet->fromArray($res, null, 'C11');
        //Agregamos la fecha
        $sheet->setCellValue('W6', 'Fecha Reporte: ' . date('Y-m-d'));

        //Agregar el indice autonumerico

        for ($i = 1; $i <= $largo; $i++) {
            $inicio = 10 + $i;
            $sheet->setCellValue('B' . $inicio, $i);
        }

        //guardamos el excel creado y luego lo obtenemos en $file para poder descargarlo
        $writer = new Xlsx($spreadsheet);
        $writer->save('archivos/' . $user->email . 'reporteComercioVales.xlsx');
        $file =
            public_path() .
            '/archivos/' .
            $user->email .
            'reporteComercioVales.xlsx';

        return response()->download(
            $file,
            $user->email . 'SolicitudesValesGrandeza' . date('Y-m-d') . '.xlsx'
        );
    }

    public function getReportesoValesRegionales(Request $request)
    {
        //ini_set("max_execution_time", 800);
        // ,'d.FechaNacimientoC','d.SexoC as Sexo'

        //$user = auth()->user();
        //$parameters['UserCreated'] = $user->id;

        $res = DB::table('vales')
            ->select(
                'et_cat_municipio.SubRegion AS Region',
                DB::raw('LPAD(HEX(vales.id),6,0) AS ClaveUnica'),
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
                'et_cat_municipio.Nombre AS Municipio',
                'et_cat_localidad_2022.Nombre AS Localidad',
                'vales.TelFijo',
                'vales.TelCelular',
                'vales.Compania',
                'vales.TelRecados',
                'vales.CorreoElectronico',
                'vales_status.Estatus',
                DB::raw(
                    "concat_ws(' ',users.Nombre, users.Paterno, users.Materno) as UserInfoCapturo"
                ),
                DB::raw(
                    "concat_ws(' ',UO.Nombre, UO.Paterno, UO.Materno) as UserInfoOwned"
                )
            )
            /* ->leftJoin('et_cat_municipio','et_cat_municipio.Id','=','vales.idMunicipio')
        ->leftJoin('et_cat_localidad','et_cat_localidad.Id','=','vales.idLocalidad')
        ->leftJoin('vales_status','vales_status.id','=','idStatus')
        ->leftJoin('users','users.id','=','vales.UserCreated')
        ->leftJoin('cat_usertipo','cat_usertipo.id','=','users.idTipoUser')
        ->leftJoin('users as usersB','usersB.id','=','vales.UserUpdated')
        ->leftJoin('cat_usertipo as cat_usertipoB','cat_usertipoB.id','=','usersB.idTipoUser')
        ->leftJoin('users as usersC','usersC.id','=','vales.UserOwned')
        ->leftJoin('users as usersCretaed','usersCretaed.id','=','vales.UserCreated')
        ->leftJoin('cat_usertipo as cat_usertipoC','cat_usertipoC.id','=','usersC.idTipoUser') */

            ->leftJoin(
                'et_cat_localidad_2022',
                'et_cat_localidad_2022.Id',
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
            ->leftJoin('vales_status', 'vales_status.id', '=', 'vales.idStatus')
            ->leftJoin(
                'users_region as UR',
                'UR.Region',
                '=',
                'et_cat_municipio.SubRegion'
            )
            ->whereNotNull('vales.Remesa')
            ->orderBy('vales.created_at')
            ->orderBy('et_cat_municipio.Nombre')
            ->orderBy('et_cat_localidad_2022.Nombre')
            ->orderBy('vales.Nombre');

        //AQUI PONGO MIS FILTROS, PARA QUE LA CONSULTA GUARDADA SE REPLIQUE.

        $user = auth()->user();
        $filtro_usuario = VNegociosFiltros::where('idUser', '=', $user->id)
            ->where('api', '=', 'getValesRegion')
            ->first();
        if ($filtro_usuario) {
            $hoy = date('Y-m-d H:i:s');
            $intervalo = $filtro_usuario->updated_at->diff($hoy);
            if ($intervalo->h === 0) {
                //Si es 0 es porque no ha pasado una hora.
                $parameters = unserialize($filtro_usuario->parameters);

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

                if (isset($parameters['sorted'])) {
                    for ($i = 0; $i < count($parameters['sorted']); $i++) {
                        if ($parameters['sorted'][$i]['desc'] === true) {
                            $res->orderBy(
                                $parameters['sorted'][$i]['id'],
                                'desc'
                            );
                        } else {
                            $res->orderBy(
                                $parameters['sorted'][$i]['id'],
                                'asc'
                            );
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
            }
        }
        //AQUI PONGO MIS FILTROS, PARA QUE LA CONSULTA GUARDADA SE REPLIQUE.

        $data = $res
            ->orderBy('et_cat_municipio.Nombre', 'asc')
            ->orderBy('vales.Colonia', 'asc')
            ->orderBy('vales.Nombre', 'asc')
            ->orderBy('vales.Paterno', 'asc')
            ->get();

        if (count($data) >= 3000) {
            return [
                'success' => true,
                'results' => false,
                'total' => count($data),
                'errors' => 'Limite de registros excedido',
                'message' =>
                    'El reporte contiene mas de 3,000 registros. Contacte al administrador.',
            ];
        }

        if (count($data) == 0) {
            $reader = IOFactory::createReader('Xlsx');
            $spreadsheet = $reader->load(
                public_path() . '/archivos/formatoReporteSolicitudVales.xlsx'
            );
            $writer = new Xlsx($spreadsheet);
            $writer->save(
                'archivos/' . $user->email . 'reporteComercioVales.xlsx'
            );
            $file =
                public_path() .
                '/archivos/' .
                $user->email .
                'reporteComercioVales.xlsx';

            return response()->download(
                $file,
                'SolicitudesValesGrandeza' . date('Y-m-d') . '.xlsx'
            );
        }

        //Mapeamos el resultado como un array
        $res = $data
            ->map(function ($x) {
                $x = is_object($x) ? (array) $x : $x;
                return $x;
            })
            ->toArray();

        //------------------------------------------------- Para generar el archivo excel ----------------------------------------------------------------
        // $spreadsheet = new Spreadsheet();
        // $sheet = $spreadsheet->getActiveSheet();

        //Para los titulos del excel
        // $titulos = ['Grupo','Folio','Nombre','Paterno','Materno','Fecha de Nacimiento','Sexo','Calle','Numero','Colonia','Municipio','Localidad','CP','Terminación'];
        // $sheet->fromArray($titulos,null,'A1');
        // $sheet->getStyle('A1:N1')->getFont()->getColor()->applyFromArray(['rgb' => '808080']);

        $reader = IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load(
            public_path() . '/archivos/formatoReporteSolicitudVales.xlsx'
        );
        $sheet = $spreadsheet->getActiveSheet();
        $largo = count($res);
        $impresion = $largo + 17;

        $sheet->getPageSetup()->setPrintArea('A1:V' . $impresion);
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_PORTRAIT);
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_LETTER);

        $largo = count($res);
        //colocar los bordes
        self::crearBordes($largo, 'B', $sheet);
        self::crearBordes($largo, 'C', $sheet);
        self::crearBordes($largo, 'D', $sheet);
        self::crearBordes($largo, 'E', $sheet);
        self::crearBordes($largo, 'F', $sheet);
        self::crearBordes($largo, 'G', $sheet);
        self::crearBordes($largo, 'H', $sheet);
        self::crearBordes($largo, 'I', $sheet);
        self::crearBordes($largo, 'J', $sheet);
        self::crearBordes($largo, 'K', $sheet);
        self::crearBordes($largo, 'L', $sheet);
        self::crearBordes($largo, 'M', $sheet);
        self::crearBordes($largo, 'N', $sheet);
        self::crearBordes($largo, 'O', $sheet);
        self::crearBordes($largo, 'P', $sheet);
        self::crearBordes($largo, 'Q', $sheet);
        self::crearBordes($largo, 'R', $sheet);
        self::crearBordes($largo, 'S', $sheet);
        self::crearBordes($largo, 'T', $sheet);
        self::crearBordes($largo, 'U', $sheet);
        self::crearBordes($largo, 'V', $sheet);
        self::crearBordes($largo, 'W', $sheet);
        self::crearBordes($largo, 'X', $sheet);
        self::crearBordes($largo, 'Y', $sheet);
        self::crearBordes($largo, 'Z', $sheet);

        //Llenar excel con el resultado del query
        $sheet->fromArray($res, null, 'C11');
        //Agregamos la fecha
        $sheet->setCellValue('W6', 'Fecha Reporte: ' . date('Y-m-d'));

        //Agregar el indice autonumerico

        for ($i = 1; $i <= $largo; $i++) {
            $inicio = 10 + $i;
            $sheet->setCellValue('B' . $inicio, $i);
        }

        //guardamos el excel creado y luego lo obtenemos en $file para poder descargarlo
        $writer = new Xlsx($spreadsheet);
        $writer->save('archivos/' . $user->email . 'reporteComercioVales.xlsx');
        $file =
            public_path() .
            '/archivos/' .
            $user->email .
            'reporteComercioVales.xlsx';

        return response()->download(
            $file,
            $user->email . 'SolicitudesValesGrandeza' . date('Y-m-d') . '.xlsx'
        );
    }

    public function getReporteNominaVales(Request $request)
    {
        // ,'d.FechaNacimientoC','d.SexoC as Sexo'
        $parameters = $request->all();
        $user = auth()->user();

        if (!isset($request->idGrupo)) {
            return response()->json([
                'success' => true,
                'results' => false,
                'data' => [],
                'message' => 'No se encontraron resultados del Grupo.',
            ]);
        }

        $resGpo = DB::table('vales_grupos as G')
            ->select(
                'G.id',
                'R.NumAcuerdo',
                'R.Leyenda',
                'R.FechaAcuerdo',
                'G.UserOwned',
                'G.idMunicipio',
                'M.Nombre AS Municipio',
                'G.Remesa',
                DB::raw(
                    "concat_ws(' ',UOC.Nombre, UOC.Paterno, UOC.Materno) as UserInfoOwned"
                )
            )
            ->leftJoin('et_cat_municipio as M', 'G.idMunicipio', '=', 'M.Id')
            ->leftJoin('users as UOC', 'UOC.id', '=', 'G.UserOwned')
            ->leftJoin('vales_remesas as R', 'R.Remesa', '=', 'G.Remesa')
            ->where('G.id', '=', $request->idGrupo)
            ->first();

        //dd($resGpo);

        if (!$resGpo) {
            return response()->json([
                'success' => true,
                'results' => false,
                'data' => [],
                'message' => 'No se encontraron resultados del Grupo.',
            ]);
        }

        $res = DB::table('vales as N')
            ->select(
                'M.SubRegion AS Region',
                DB::raw('LPAD(HEX(N.id),6,0) AS ClaveUnica'),
                'N.CURP',
                DB::raw(
                    "concat_ws(' ',N.Nombre, N.Paterno, N.Materno) as NombreCompleto"
                ),
                'N.Sexo',
                DB::raw(
                    "concat_ws(' ',N.Calle, if(N.NumExt is null, ' ', concat('NumExt ',N.NumExt)), if(N.NumInt is null, ' ', concat('Int ',N.NumInt))) AS Direccion"
                ),
                'N.Colonia',
                'N.CP',
                'M.Nombre AS Municipio',
                'L.Nombre AS Localidad',
                'VS.SerieInicial',
                'VS.SerieFinal'
            )
            ->leftJoin('et_cat_municipio as M', 'N.idMunicipio', '=', 'M.Id')
            ->leftJoin(
                'et_cat_localidad_2022 as L',
                'N.idLocalidad',
                '=',
                'L.Id'
            )
            ->leftJoin('vales_solicitudes as VS', 'VS.idSolicitud', '=', 'N.id')
            ->leftJoin('users as UOC', 'UOC.id', '=', 'N.UserOwned')
            ->join('vales_status as E', 'N.idStatus', '=', 'E.id')
            ->where('N.UserOwned', '=', $resGpo->UserOwned)
            ->where('N.idMunicipio', '=', $resGpo->idMunicipio)
            ->where('N.Remesa', '=', $resGpo->Remesa);

        // $resGrupo = DB::table('v_giros as G')
        // ->select('G.Giro', 'NG.idNegocio', 'NG.idGiro')
        // ->join('v_negocios_giros as NG','NG.idGiro','=','G.id');

        $data = $res
            ->orderBy('M.Nombre', 'asc')
            ->orderBy('L.Nombre', 'asc')
            ->orderBy('N.Colonia', 'asc')
            ->orderBy('N.Nombre', 'asc')
            ->orderBy('N.Paterno', 'asc')
            ->get();
        //$data2 = $resGrupo->first();
        // dd($resGpo);
        //     dd($data);

        if (count($data) == 0) {
            //return response()->json(['success'=>false,'results'=>false,'message'=>$res->toSql()])

            $reader = IOFactory::createReader('Xlsx');
            $spreadsheet = $reader->load(
                public_path() . '/archivos/formatoReporteNominaValesv3.xlsx'
            );

            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setCellValue('N6', $resGpo->Municipio);
            $sheet->setCellValue('N7', $resGpo->UserInfoOwned);
            $sheet->setCellValue('N3', $resGpo->NumAcuerdo);
            $sheet->setCellValue('N4', $resGpo->FechaAcuerdo);
            $sheet->setCellValue('A2', $resGpo->Leyenda);
            $sheet->setCellValue(
                'A3',
                'Aprobados mediante ' .
                    $resGpo->NumAcuerdo .
                    ' de fecha ' .
                    $resGpo->FechaAcuerdo
            );

            $writer = new Xlsx($spreadsheet);
            $writer->save(
                'archivos/' .
                    $resGpo->Remesa .
                    '_' .
                    $resGpo->idMunicipio .
                    '_' .
                    $resGpo->UserOwned .
                    '_formatoNominaVales.xlsx'
            );
            $file =
                public_path() .
                '/archivos/' .
                $resGpo->Remesa .
                '_' .
                $resGpo->idMunicipio .
                '_' .
                $resGpo->UserOwned .
                '_formatoNominaVales.xlsx';

            return response()->download(
                $file,
                $resGpo->Remesa .
                    '_' .
                    $resGpo->idMunicipio .
                    '_' .
                    $resGpo->UserOwned .
                    '_NominaValesGrandeza' .
                    date('Y-m-d') .
                    '.xlsx'
            );
        }

        //Mapeamos el resultado como un array
        $res = $data
            ->map(function ($x) {
                $x = is_object($x) ? (array) $x : $x;
                return $x;
            })
            ->toArray();

        $Regional = '';

        switch ($res[0]['Region']) {
            case '1':
                $Regional = 'ROBERTO CARLOS TERAN RAMOS';
                $CARGOREGIONAL = 'DIRECTOR REGIONAL';
                break;
            case '2':
                $Regional = 'MIGUEL ANGEL FLORES SOLIS';
                $CARGOREGIONAL = 'DIRECTOR REGIONAL';
                break;
            case '3':
                $Regional = 'RODOLFO AUGUSTO OCTAVIO AGUIRRE RUTEAGA';
                $CARGOREGIONAL = 'DIRECTOR REGIONAL';
                break;
            case '4':
                //$Regional="OMAR GREGORIO MENDOZA FLORES";
                $Regional = 'JOSE LUIS OROZCO NAVA';
                $CARGOREGIONAL = 'DIRECTOR REGIONAL';
                break;
            case '5':
                $Regional = 'ARTURO DONACIANO SALAZAR SOTO';
                $CARGOREGIONAL = 'DIRECTOR REGIONAL';
                break;
            case '6':
                $Regional = 'JULIO MARTINEZ FRANCO';
                $CARGOREGIONAL = 'DIRECTOR REGIONAL';
                break;
            case '7':
                //$Regional="SILVIA DE ANDA CAMPOS";
                //$Regional="ELIZABETH RAMIREZ BÁRCENAS";
                $Regional = 'ARACELI CABRERA ALCARAZ';
                $CARGOREGIONAL = 'DIRECTOR REGIONAL';
                break;
        }

        //------------------------------------------------- Para generar el archivo excel ----------------------------------------------------------------
        // $spreadsheet = new Spreadsheet();
        // $sheet = $spreadsheet->getActiveSheet();

        //Para los titulos del excel
        // $titulos = ['Grupo','Folio','Nombre','Paterno','Materno','Fecha de Nacimiento','Sexo','Calle','Numero','Colonia','Municipio','Localidad','CP','Terminación'];
        // $sheet->fromArray($titulos,null,'A1');
        // $sheet->getStyle('A1:N1')->getFont()->getColor()->applyFromArray(['rgb' => '808080']);

        //dd('Correcto');

        $reader = IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load(
            public_path() . '/archivos/formatoReporteNominaValesv3.xlsx'
        );
        $sheet = $spreadsheet->getActiveSheet();
        $largo = count($res);
        $impresion = $largo + 10;

        $sheet->getPageSetup()->setPrintArea('A1:O' . ($impresion + 15));
        $sheet
            ->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_LETTER);

        $largo = count($res);
        //colocar los bordes
        self::crearBordes($largo, 'A', $sheet);
        self::crearBordes($largo, 'B', $sheet);
        self::crearBordes($largo, 'C', $sheet);
        self::crearBordes($largo, 'D', $sheet);
        self::crearBordes($largo, 'E', $sheet);
        self::crearBordes($largo, 'F', $sheet);
        self::crearBordes($largo, 'G', $sheet);
        self::crearBordes($largo, 'H', $sheet);
        self::crearBordes($largo, 'I', $sheet);
        self::crearBordes($largo, 'J', $sheet);
        self::crearBordes($largo, 'K', $sheet);
        self::crearBordes($largo, 'L', $sheet);
        self::crearBordes($largo, 'M', $sheet);
        self::crearBordes($largo, 'N', $sheet);
        self::crearBordes($largo, 'O', $sheet);

        //Llenar excel con el resultado del query
        $sheet->fromArray($res, null, 'B11');
        //Agregamos la fecha
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('N6', $resGpo->Municipio);
        $sheet->setCellValue('N7', $resGpo->UserInfoOwned);
        $sheet->setCellValue('N3', $resGpo->NumAcuerdo);
        $sheet->setCellValue('N4', $resGpo->FechaAcuerdo);
        $sheet->setCellValue('A2', $resGpo->Leyenda);
        $sheet->setCellValue(
            'A3',
            'Aprobados mediante ' .
                $resGpo->NumAcuerdo .
                ' de fecha ' .
                $resGpo->FechaAcuerdo
        );

        //dd($largo);

        $veces = 0;

        if ($largo > 25) {
            //dd('Se agrega lineBreak');
            for ($lb = 20; $lb < $largo; $lb += 20) {
                $veces++;
                //dd($largo);
                $spreadsheet
                    ->getActiveSheet()
                    ->setBreak(
                        'A' . ($lb + 10),
                        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::BREAK_ROW
                    );
            }
        }

        //Agregar el indice autonumerico

        for ($i = 1; $i <= $largo; $i++) {
            $inicio = 10 + $i;
            $sheet->setCellValue('A' . $inicio, $i);
        }

        //dd(public_path('/img/firmasVales.png'));

        //dd($impresion+1);

        //INICIA FORMATO DE FIRMAS
        $spreadsheet
            ->getActiveSheet()
            ->setBreak(
                'A' . $impresion,
                \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::BREAK_ROW
            );

        $ln = $impresion + 2;
        $sheet->mergeCells('C' . $ln . ':H' . $ln);
        $sheet->setCellValue('C' . $ln, 'ENTREGA');

        $sheet->mergeCells('I' . $ln . ':O' . $ln);
        $sheet->setCellValue('I' . $ln, 'RECIBE');

        $sheet
            ->getStyle('C' . $ln . ':O' . $ln)
            ->getBorders()
            ->getTop()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('C' . $ln . ':O' . $ln)
            ->getBorders()
            ->getBottom()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('C' . $ln)
            ->getBorders()
            ->getLeft()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('H' . $ln)
            ->getBorders()
            ->getRight()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('O' . $ln)
            ->getBorders()
            ->getRight()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );

        $ln++;
        $sheet->mergeCells('A' . $ln . ':B' . $ln);
        $sheet->setCellValue('A' . $ln, 'FECHA');

        $sheet->mergeCells('A' . ($ln + 1) . ':B' . ($ln + 1));
        $sheet->setCellValue('A' . ($ln + 1), date('Y-m-d'));

        $sheet->mergeCells('C' . $ln . ':E' . $ln);
        $sheet->setCellValue('C' . $ln, 'NOMBRE');
        $sheet->mergeCells('C' . ($ln + 1) . ':E' . ($ln + 1));
        $sheet->setCellValue('C' . ($ln + 1), 'DANIEL RODOLFO TORRES CHONA');

        $sheet->mergeCells('F' . $ln . ':G' . $ln);
        $sheet->setCellValue('F' . $ln, 'CARGO');
        $sheet->mergeCells('F' . ($ln + 1) . ':G' . ($ln + 1));
        $sheet->setCellValue(
            'F' . ($ln + 1),
            'JEFE DE ARTICULACIÓN TRANSVERSAL Y SECTORIAL'
        );

        $sheet->setCellValue('H' . $ln, 'FIRMA');

        $sheet->mergeCells('I' . $ln . ':K' . $ln);
        $sheet->setCellValue('I' . $ln, 'NOMBRE');

        $sheet->mergeCells('L' . $ln . ':N' . $ln);
        $sheet->setCellValue('L' . $ln, 'CARGO');

        $sheet->mergeCells('I' . ($ln + 1) . ':K' . ($ln + 1));
        //$sheet->setCellValue('I' . ($ln + 1), $Regional);
        $sheet->setCellValue('I' . ($ln + 1), '');

        $sheet->mergeCells('L' . ($ln + 1) . ':N' . ($ln + 1));
        //$sheet->setCellValue('L' . ($ln + 1), $CARGOREGIONAL);
        $sheet->setCellValue('L' . ($ln + 1), '');

        $sheet->setCellValue('O' . $ln, 'FIRMA');

        $sheet
            ->getStyle('A' . $ln . ':O' . $ln)
            ->getBorders()
            ->getTop()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('A' . $ln . ':O' . $ln)
            ->getBorders()
            ->getBottom()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('B' . $ln)
            ->getBorders()
            ->getTop()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('B' . $ln)
            ->getBorders()
            ->getBottom()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('A' . $ln)
            ->getBorders()
            ->getLeft()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('B' . $ln)
            ->getBorders()
            ->getRight()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('E' . $ln)
            ->getBorders()
            ->getRight()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('G' . $ln)
            ->getBorders()
            ->getRight()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('H' . $ln)
            ->getBorders()
            ->getRight()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('K' . $ln)
            ->getBorders()
            ->getRight()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('N' . $ln)
            ->getBorders()
            ->getRight()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('O' . $ln)
            ->getBorders()
            ->getRight()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );

        $sheet->getRowDimension($ln)->setRowHeight(70);
        $ln++;

        $sheet
            ->getStyle('A' . $ln . ':O' . $ln)
            ->getBorders()
            ->getTop()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('A' . $ln . ':O' . $ln)
            ->getBorders()
            ->getBottom()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('B' . $ln)
            ->getBorders()
            ->getTop()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('B' . $ln)
            ->getBorders()
            ->getBottom()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('A' . $ln)
            ->getBorders()
            ->getLeft()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('B' . $ln)
            ->getBorders()
            ->getRight()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('E' . $ln)
            ->getBorders()
            ->getRight()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('G' . $ln)
            ->getBorders()
            ->getRight()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('H' . $ln)
            ->getBorders()
            ->getRight()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('K' . $ln)
            ->getBorders()
            ->getRight()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('N' . $ln)
            ->getBorders()
            ->getRight()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('O' . $ln)
            ->getBorders()
            ->getRight()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet->getRowDimension($ln)->setRowHeight(90);

        $ln++;

        $sheet
            ->getStyle('A' . $ln . ':O' . $ln)
            ->getBorders()
            ->getTop()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('A' . $ln . ':O' . $ln)
            ->getBorders()
            ->getBottom()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('B' . $ln)
            ->getBorders()
            ->getTop()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('B' . $ln)
            ->getBorders()
            ->getBottom()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('A' . $ln)
            ->getBorders()
            ->getLeft()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('B' . $ln)
            ->getBorders()
            ->getRight()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('E' . $ln)
            ->getBorders()
            ->getRight()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('G' . $ln)
            ->getBorders()
            ->getRight()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('H' . $ln)
            ->getBorders()
            ->getRight()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('K' . $ln)
            ->getBorders()
            ->getRight()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('N' . $ln)
            ->getBorders()
            ->getRight()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('O' . $ln)
            ->getBorders()
            ->getRight()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet->getRowDimension($ln)->setRowHeight(90);
        $ln++;

        $sheet
            ->getStyle('A' . $ln . ':O' . $ln)
            ->getBorders()
            ->getTop()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('A' . $ln . ':O' . $ln)
            ->getBorders()
            ->getBottom()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('B' . $ln)
            ->getBorders()
            ->getTop()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('B' . $ln)
            ->getBorders()
            ->getBottom()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('A' . $ln)
            ->getBorders()
            ->getLeft()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('B' . $ln)
            ->getBorders()
            ->getRight()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('E' . $ln)
            ->getBorders()
            ->getRight()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('G' . $ln)
            ->getBorders()
            ->getRight()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('H' . $ln)
            ->getBorders()
            ->getRight()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('K' . $ln)
            ->getBorders()
            ->getRight()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('N' . $ln)
            ->getBorders()
            ->getRight()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('O' . $ln)
            ->getBorders()
            ->getRight()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet->getRowDimension($ln)->setRowHeight(90);
        $ln++;

        $sheet
            ->getStyle('A' . $ln . ':O' . $ln)
            ->getBorders()
            ->getTop()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('A' . $ln . ':O' . $ln)
            ->getBorders()
            ->getBottom()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('B' . $ln)
            ->getBorders()
            ->getTop()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('B' . $ln)
            ->getBorders()
            ->getBottom()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('A' . $ln)
            ->getBorders()
            ->getLeft()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('B' . $ln)
            ->getBorders()
            ->getRight()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('E' . $ln)
            ->getBorders()
            ->getRight()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('G' . $ln)
            ->getBorders()
            ->getRight()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('H' . $ln)
            ->getBorders()
            ->getRight()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('K' . $ln)
            ->getBorders()
            ->getRight()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('N' . $ln)
            ->getBorders()
            ->getRight()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('O' . $ln)
            ->getBorders()
            ->getRight()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet->getRowDimension($ln)->setRowHeight(90);
        $ln++;

        $sheet
            ->getStyle('A' . $ln . ':O' . $ln)
            ->getBorders()
            ->getTop()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('A' . $ln . ':O' . $ln)
            ->getBorders()
            ->getBottom()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('B' . $ln)
            ->getBorders()
            ->getTop()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('B' . $ln)
            ->getBorders()
            ->getBottom()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('A' . $ln)
            ->getBorders()
            ->getLeft()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('B' . $ln)
            ->getBorders()
            ->getRight()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('E' . $ln)
            ->getBorders()
            ->getRight()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('G' . $ln)
            ->getBorders()
            ->getRight()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('H' . $ln)
            ->getBorders()
            ->getRight()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('K' . $ln)
            ->getBorders()
            ->getRight()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('N' . $ln)
            ->getBorders()
            ->getRight()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('O' . $ln)
            ->getBorders()
            ->getRight()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet->getRowDimension($ln)->setRowHeight(90);

        $ln += 2;
        $lnf = $ln + 5;

        $sheet->mergeCells('A' . $ln . ':O' . $ln);
        $sheet->setCellValue('A' . $ln, 'OBSERVACIONES  Y/O  INCIDENCIAS');
        $sheet
            ->getStyle('A' . $ln . ':O' . $ln)
            ->getBorders()
            ->getBottom()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );

        $sheet
            ->getStyle('A' . $ln . ':O' . $ln)
            ->getBorders()
            ->getTop()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('A' . $lnf . ':O' . $lnf)
            ->getBorders()
            ->getBottom()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('A' . $ln . ':A' . $lnf)
            ->getBorders()
            ->getLeft()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );
        $sheet
            ->getStyle('O' . $ln . ':O' . $lnf)
            ->getBorders()
            ->getRight()
            ->setBorderStyle(
                \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
            );

        //dd('si paso el rpoceso');

        //guardamos el excel creado y luego lo obtenemos en $file para poder descargarlo
        $writer = new Xlsx($spreadsheet);

        $strRem = str_replace('/', '_', $resGpo->Remesa);
        // dd($strRem);

        //dd('archivos/'.$strRem.'_'.$resGpo->idMunicipio.'_'.$resGpo->UserOwned.'_formatoNominaVales.xlsx');
        $writer->save(
            'archivos/' .
                $strRem .
                '_' .
                $resGpo->idMunicipio .
                '_' .
                $resGpo->UserOwned .
                '_formatoNominaVales.xlsx'
        );
        $file =
            public_path() .
            '/archivos/' .
            $strRem .
            '_' .
            $resGpo->idMunicipio .
            '_' .
            $resGpo->UserOwned .
            '_formatoNominaVales.xlsx';

        //dd('Se crearon los archivos');

        return response()->download(
            $file,
            $strRem .
                '_' .
                $resGpo->idMunicipio .
                '_' .
                $resGpo->UserOwned .
                '_formatoNominaVales' .
                date('Y-m-d H:i:s') .
                '.xlsx'
        );
    }

    public function getReporteNominaVales2023(Request $request)
    {
        $parameters = $request->all();
        $user = auth()->user();

        if (!isset($request->idGrupo)) {
            return response()->json([
                'success' => true,
                'results' => false,
                'data' => [],
                'message' => 'El id del Grupo no fue enviado.',
            ]);
        }

        $resGpo = DB::table('vales_grupos as G')
            ->select(
                'G.id',
                'R.NumAcuerdo',
                'R.Leyenda',
                'R.FechaAcuerdo',
                'G.TotalAprobados',
                'G.ResponsableEntrega',
                'M.Nombre AS Municipio',
                'L.Nombre AS Localidad',
                'G.Remesa',
                'G.idMunicipio'
            )
            ->JOIN('vales_remesas as R', 'R.Remesa', '=', 'G.Remesa')
            ->JOIN('et_cat_municipio as M', 'G.idMunicipio', '=', 'M.Id')
            ->JOIN('et_cat_localidad_2022 as L', 'G.idLocalidad', '=', 'L.id')
            ->where('G.id', '=', $request->idGrupo)
            ->first();

        if (!$resGpo) {
            return response()->json([
                'success' => true,
                'results' => false,
                'data' => [],
                'message' => 'No se encontraron resultados del Grupo.',
            ]);
        }

        $res = DB::table('vales as N')
            ->select(
                'M.SubRegion AS Region',
                DB::raw('CONCAT(LPAD(HEX(N.id),6,0)," ") AS ClaveUnica'),
                'N.CURP',
                DB::raw(
                    "concat_ws(' ',N.Nombre, N.Paterno, N.Materno) as NombreCompleto"
                ),
                'N.Sexo',
                DB::raw(
                    "concat_ws(' ',N.Calle, if(N.NumExt is null, ' ', concat('NumExt ',N.NumExt)), if(N.NumInt is null, ' ', concat('Int ',N.NumInt))) AS Direccion"
                ),
                'N.Colonia',
                'N.CP',
                'M.Nombre AS Municipio',
                'L.Nombre AS Localidad',
                'VS.SerieInicial',
                'VS.SerieFinal'
            )
            ->JOIN('et_cat_municipio as M', 'N.idMunicipio', '=', 'M.Id')
            ->JOIN('et_cat_localidad_2022 as L', 'N.idLocalidad', '=', 'L.id')
            ->leftJoin('vales_solicitudes as VS', 'VS.idSolicitud', '=', 'N.id')
            ->WHERE('N.idGrupo', $request->idGrupo);

        $data = $res
            ->orderBy('M.Nombre', 'asc')
            ->orderBy('N.CveInterventor', 'asc')
            ->orderBy('L.Nombre', 'asc')
            ->orderBy('N.ResponsableEntrega', 'asc')
            ->orderBy('N.Nombre', 'asc')
            ->orderBy('N.Paterno', 'asc')
            ->get();

        //dd(str_replace_array('?', $data->getBindings(), $data->toSql()));

        if (count($data) == 0) {
            $file =
                public_path() . '/archivos/formatoReporteNominaValesv3.xlsx';

            return response()->download(
                $file,
                'NominaValesGrandeza' . date('Y-m-d') . '.xlsx'
            );
        }

        //Mapeamos el resultado como un array
        $res = $data
            ->map(function ($x) {
                $x = is_object($x) ? (array) $x : $x;
                return $x;
            })
            ->toArray();

        //------------------------------------------------- Para generar el archivo excel ----------------------------------------------------------------
        // $spreadsheet = new Spreadsheet();
        // $sheet = $spreadsheet->getActiveSheet();

        //Para los titulos del excel
        // $titulos = ['Grupo','Folio','Nombre','Paterno','Materno','Fecha de Nacimiento','Sexo','Calle','Numero','Colonia','Municipio','Localidad','CP','Terminación'];
        // $sheet->fromArray($titulos,null,'A1');
        // $sheet->getStyle('A1:N1')->getFont()->getColor()->applyFromArray(['rgb' => '808080']);

        //dd('Correcto');

        $reader = IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load(
            public_path() . '/archivos/formatoReporteNominaValesv3.xlsx'
        );
        $sheet = $spreadsheet->getActiveSheet();
        $largo = count($res);
        $impresion = $largo + 10;

        $sheet->getPageSetup()->setPrintArea('A1:O' . ($impresion + 15));
        $sheet
            ->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_LETTER);

        $largo = count($res);
        //colocar los bordes
        // self::crearBordes($largo, 'A', $sheet);
        // self::crearBordes($largo, 'B', $sheet);
        // self::crearBordes($largo, 'C', $sheet);
        // self::crearBordes($largo, 'D', $sheet);
        // self::crearBordes($largo, 'E', $sheet);
        // self::crearBordes($largo, 'F', $sheet);
        // self::crearBordes($largo, 'G', $sheet);
        // self::crearBordes($largo, 'H', $sheet);
        // self::crearBordes($largo, 'I', $sheet);
        // self::crearBordes($largo, 'J', $sheet);
        // self::crearBordes($largo, 'K', $sheet);
        // self::crearBordes($largo, 'L', $sheet);
        // self::crearBordes($largo, 'M', $sheet);
        // self::crearBordes($largo, 'N', $sheet);
        // self::crearBordes($largo, 'O', $sheet);

        //Llenar excel con el resultado del query
        $sheet->fromArray($res, null, 'B11');
        //Agregamos la fecha
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('K6', $resGpo->Municipio);
        $sheet->setCellValue('K7', $resGpo->Localidad);
        $sheet->setCellValue('K9', $resGpo->ResponsableEntrega);
        $sheet->setCellValue('K4', $resGpo->NumAcuerdo);
        $sheet->setCellValue('K5', $resGpo->FechaAcuerdo);
        $sheet->setCellValue('A2', $resGpo->Leyenda);
        $sheet->setCellValue(
            'A3',
            'Aprobados mediante ' .
                $resGpo->NumAcuerdo .
                ' de fecha ' .
                $resGpo->FechaAcuerdo
        );

        //dd($largo);

        $veces = 0;

        if ($largo > 25) {
            //dd('Se agrega lineBreak');
            for ($lb = 50; $lb < $largo; $lb += 50) {
                $veces++;
                //dd($largo);
                $spreadsheet
                    ->getActiveSheet()
                    ->setBreak(
                        'A' . ($lb + 10),
                        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::BREAK_ROW
                    );
            }
        }

        //Agregar el indice autonumerico

        for ($i = 1; $i <= $largo; $i++) {
            $inicio = 10 + $i;
            $sheet->setCellValue('A' . $inicio, $i);
        }

        //guardamos el excel creado y luego lo obtenemos en $file para poder descargarlo
        $writer = new Xlsx($spreadsheet);

        $strRem = str_replace('/', '_', $resGpo->Remesa);
        // dd($strRem);

        //dd('archivos/'.$strRem.'_'.$resGpo->idMunicipio.'_'.$resGpo->UserOwned.'_formatoNominaVales.xlsx');
        $path =
            'NOMINA_VALES_' .
            $strRem .
            '_' .
            $resGpo->Municipio .
            '_' .
            $resGpo->TotalAprobados .
            '_' .
            str_replace(' ', '_', $resGpo->ResponsableEntrega) .
            '.xlsx';

        $writer->save('archivos/' . $path);
        $file = public_path() . '/archivos/' . $path;

        //dd('Se crearon los archivos');

        return response()->download(
            $file,
            'NOMINA_VALES_' .
                $strRem .
                '_' .
                $resGpo->Municipio .
                '_' .
                $resGpo->TotalAprobados .
                '_' .
                str_replace(' ', '_', $resGpo->ResponsableEntrega) .
                '_' .
                date('Y-m-d H:i:s') .
                '.xlsx'
        );
    }

    public function getEtiquetasVales(Request $request)
    {
        $parameters = $request->all();
        $user = auth()->user();

        if (!isset($request->idGrupo)) {
            return response()->json([
                'success' => true,
                'results' => false,
                'data' => [],
                'message' => 'No se encontraron resultados del Grupo.',
            ]);
        }

        $resGpo = DB::table('vales_grupos as G')
            ->select(
                'G.id',
                'G.ResponsableEntrega',
                'M.Nombre AS Municipio',
                'G.CveInterventor',
                'G.Remesa'
            )
            ->JOIN('et_cat_municipio as M', 'G.idMunicipio', '=', 'M.Id')
            ->where('G.id', '=', $request->idGrupo)
            ->first();

        if (!$resGpo) {
            return response()->json([
                'success' => true,
                'results' => false,
                'data' => [],
                'message' => 'No se encontraron resultados del Grupo.',
            ]);
        }

        $res = DB::table('vales as N')
            ->select(
                DB::raw(
                    "CONCAT(concat_ws(' ',N.Nombre, N.Paterno, N.Materno),' - ',VS.SerieInicial) as Codigo"
                )
            )
            ->JOIN('et_cat_municipio as M', 'N.idMunicipio', '=', 'M.Id')
            ->JOIN('et_cat_localidad_2022 as L', 'N.idLocalidad', '=', 'L.id')
            ->leftJoin('vales_solicitudes as VS', 'VS.idSolicitud', '=', 'N.id')
            ->WHERE('N.idGrupo', $request->idGrupo);

        $data = $res
            ->orderBy('M.Nombre', 'asc')
            ->orderBy('N.CveInterventor', 'asc')
            ->orderBy('L.Nombre', 'asc')
            ->orderBy('N.ResponsableEntrega', 'asc')
            ->orderBy('N.Nombre', 'asc')
            ->orderBy('N.Paterno', 'asc')
            ->orderBy('N.Materno', 'asc')
            ->get();

        //dd(str_replace_array('?', $data->getBindings(), $data->toSql()));

        if (count($data) == 0) {
            $file =
                public_path() . '/archivos/formatoReporteNominaValesv3.xlsx';

            return response()->download(
                $file,
                'NominaValesGrandeza' . date('Y-m-d') . '.xlsx'
            );
        }

        //Mapeamos el resultado como un array
        $res = $data
            ->map(function ($x) {
                $x = is_object($x) ? (array) $x : $x;
                return $x;
            })
            ->toArray();

        //------------------------------------------------- Para generar el archivo excel ----------------------------------------------------------------
        // $spreadsheet = new Spreadsheet();
        // $sheet = $spreadsheet->getActiveSheet();

        //Para los titulos del excel
        // $titulos = ['Grupo','Folio','Nombre','Paterno','Materno','Fecha de Nacimiento','Sexo','Calle','Numero','Colonia','Municipio','Localidad','CP','Terminación'];
        // $sheet->fromArray($titulos,null,'A1');
        // $sheet->getStyle('A1:N1')->getFont()->getColor()->applyFromArray(['rgb' => '808080']);

        //dd('Correcto');

        $reader = IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load(
            public_path() . '/archivos/formatoEtiquetasVales.xlsx'
        );
        $sheet = $spreadsheet->getActiveSheet();
        $largo = count($res);
        $paginas = ceil($largo / 36);
        $impresion = $paginas * 18;
        $sheet->getPageSetup()->setPrintArea('A1:C' . $impresion);
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_PORTRAIT);
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_LETTER);

        $registros = array_chunk($res, 32);
        $indice = 3;

        //Agregamos CveInterventor
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue(
            'A1',
            $resGpo->Municipio . ' ' . $resGpo->CveInterventor
        );
        $sheet->setCellValue('A2', $resGpo->CveInterventor);

        for ($i = 1; $i < $impresion + 1; $i++) {
            $sheet->getRowDimension($i)->setRowHeight(35);
        }

        foreach ($registros as $r) {
            //Llenar excel con el resultado del query
            $columns = array_chunk($r, 16);
            if (count($columns) > 1) {
                $sheet->fromArray($columns[0], null, 'A' . $indice);
                $sheet->fromArray($columns[1], null, 'C' . $indice);
                $indice = $indice + 16;
                $spreadsheet
                    ->getActiveSheet()
                    ->setBreak(
                        'A' . $indice,
                        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::BREAK_ROW
                    );
            } else {
                $sheet->fromArray($columns[0], null, 'A' . $indice);
            }
        }
        // $sheet = $spreadsheet
        //     ->getActiveSheet()
        //     ->getDefaultRowDimension()
        //     ->setRowHeight(75);

        //guardamos el excel creado y luego lo obtenemos en $file para poder descargarlo
        $writer = new Xlsx($spreadsheet);
        $path =
            'Etiquetas_' .
            $resGpo->id .
            '_' .
            $resGpo->Remesa .
            '_' .
            $resGpo->Municipio .
            '_' .
            str_replace(' ', '_', $resGpo->ResponsableEntrega) .
            '_' .
            $resGpo->CveInterventor .
            '.xlsx';

        $nombreArchivo = 'archivos/' . $path;
        $writer->save($nombreArchivo);
        $file = public_path() . '/' . $nombreArchivo;
        return response()->download($file, $path);
    }

    public function getReporteEntregaVales2023(Request $request)
    {
        $parameters = $request->all();
        $user = auth()->user();

        if (!isset($request->idGrupo)) {
            return response()->json([
                'success' => true,
                'results' => false,
                'data' => [],
                'message' => 'No se encontraron resultados del Grupo.',
            ]);
        }

        $resGpo = DB::table('vales_grupos as G')
            ->select(
                'G.id',
                'R.NumAcuerdo',
                'R.Leyenda',
                'R.FechaAcuerdo',
                'G.TotalAprobados',
                'G.ResponsableEntrega',
                'M.Nombre AS Municipio',
                'L.Nombre AS Localidad',
                'G.Remesa',
                'G.idMunicipio'
            )
            ->JOIN('vales_remesas as R', 'R.Remesa', '=', 'G.Remesa')
            ->JOIN('et_cat_municipio as M', 'G.idMunicipio', '=', 'M.Id')
            ->JOIN('et_cat_localidad_2022 as L', 'G.idLocalidad', '=', 'L.id')
            ->where('G.id', '=', $request->idGrupo)
            ->first();

        if (!$resGpo) {
            return response()->json([
                'success' => true,
                'results' => false,
                'data' => [],
                'message' => 'No se encontraron resultados del Grupo.',
            ]);
        }

        $res = DB::table('vales as N')
            ->select(
                'M.SubRegion AS Region',
                DB::raw('LPAD(HEX(N.id),6,0) AS ClaveUnica'),
                'N.CURP',
                DB::raw(
                    "concat_ws(' ',N.Nombre, N.Paterno, N.Materno) as NombreCompleto"
                ),
                'N.Sexo',
                DB::raw(
                    "concat_ws(' ',N.Calle, if(N.NumExt is null, ' ', concat('NumExt ',N.NumExt)), if(N.NumInt is null, ' ', concat('Int ',N.NumInt))) AS Direccion"
                ),
                'N.Colonia',
                'N.CP',
                'M.Nombre AS Municipio',
                'L.Nombre AS Localidad',
                'VS.SerieInicial',
                'VS.SerieFinal',
                DB::raw(
                    'CASE WHEN N.isEntregado =1 THEN "SI" ELSE "NO" END AS Entregado'
                ),
                'N.entrega_at AS FechaEntrega',
                DB::raw(
                    'CASE WHEN d.idSolicitud IS NULL THEN NULL ELSE "DEVUELTO" END AS Devuelto'
                )
            )
            ->JOIN('et_cat_municipio as M', 'N.idMunicipio', '=', 'M.Id')
            ->JOIN('et_cat_localidad_2022 as L', 'N.idLocalidad', '=', 'L.id')
            ->LEFTJOIN('vales_solicitudes as VS', 'VS.idSolicitud', '=', 'N.id')
            ->LEFTJOIN('vales_devueltos as d', 'd.idSolicitud', 'N.id')
            ->WHERE('N.idGrupo', $request->idGrupo);

        $data = $res
            ->orderBy('M.Nombre', 'asc')
            ->orderBy('N.CveInterventor', 'asc')
            ->orderBy('L.Nombre', 'asc')
            ->orderBy('N.ResponsableEntrega', 'asc')
            ->orderBy('N.Nombre', 'asc')
            ->orderBy('N.Paterno', 'asc')
            ->get();

        //dd(str_replace_array('?', $data->getBindings(), $data->toSql()));

        if (count($data) == 0) {
            $file =
                public_path() . '/archivos/formatoReporteNominaValesv5.xlsx';

            return response()->download(
                $file,
                'NominaValesGrandeza' . date('Y-m-d') . '.xlsx'
            );
        }

        $res = $data
            ->map(function ($x) {
                $x = is_object($x) ? (array) $x : $x;
                return $x;
            })
            ->toArray();

        $reader = IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load(
            public_path() . '/archivos/formatoReporteNominaValesv5.xlsx'
        );
        $sheet = $spreadsheet->getActiveSheet();
        $largo = count($res);
        $impresion = $largo + 10;

        $sheet->getPageSetup()->setPrintArea('A1:O' . ($impresion + 15));
        $sheet
            ->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_LETTER);

        $largo = count($res);

        $sheet->fromArray($res, null, 'B11');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('K6', $resGpo->Municipio);
        $sheet->setCellValue('K7', $resGpo->Localidad);
        $sheet->setCellValue('K9', $resGpo->ResponsableEntrega);
        $sheet->setCellValue('K4', $resGpo->NumAcuerdo);
        $sheet->setCellValue('K5', $resGpo->FechaAcuerdo);
        $sheet->setCellValue('A2', $resGpo->Leyenda);
        $sheet->setCellValue(
            'A3',
            'Aprobados mediante ' .
                $resGpo->NumAcuerdo .
                ' de fecha ' .
                $resGpo->FechaAcuerdo
        );

        //dd($largo);

        $veces = 0;

        if ($largo > 25) {
            //dd('Se agrega lineBreak');
            for ($lb = 20; $lb < $largo; $lb += 20) {
                $veces++;
                //dd($largo);
                $spreadsheet
                    ->getActiveSheet()
                    ->setBreak(
                        'A' . ($lb + 10),
                        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::BREAK_ROW
                    );
            }
        }

        //Agregar el indice autonumerico

        for ($i = 1; $i <= $largo; $i++) {
            $inicio = 10 + $i;
            $sheet->setCellValue('A' . $inicio, $i);
        }

        //guardamos el excel creado y luego lo obtenemos en $file para poder descargarlo
        $writer = new Xlsx($spreadsheet);

        $strRem = str_replace('/', '_', $resGpo->Remesa);
        // dd($strRem);

        //dd('archivos/'.$strRem.'_'.$resGpo->idMunicipio.'_'.$resGpo->UserOwned.'_formatoNominaVales.xlsx');
        $writer->save(
            'archivos/' .
                $strRem .
                '_' .
                $resGpo->idMunicipio .
                '_' .
                $resGpo->ResponsableEntrega .
                '_formatoEntregaVales.xlsx'
        );
        $file =
            public_path() .
            '/archivos/' .
            $strRem .
            '_' .
            $resGpo->idMunicipio .
            '_' .
            $resGpo->ResponsableEntrega .
            '_formatoEntregaVales.xlsx';

        //dd('Se crearon los archivos');

        return response()->download(
            $file,
            $strRem .
                '_' .
                $resGpo->idMunicipio .
                '_' .
                $resGpo->ResponsableEntrega .
                '_formatoEntregaVales' .
                date('Y-m-d H:i:s') .
                '.xlsx'
        );
    }

    public function validarGrupo(Request $request)
    {
        $params = $request->all();
        $user = auth()->user();

        if (!isset($params['idGrupo'])) {
            return response()->json([
                'success' => true,
                'results' => false,
                'data' => [],
                'message' => 'No se encontraron resultados del Grupo.',
            ]);
        }

        $idGpo = $params['idGrupo'];

        $resGpo = DB::table('vales_grupos as G')
            ->select(
                'G.id',
                'R.NumAcuerdo',
                'R.Leyenda',
                'R.FechaAcuerdo',
                'G.UserOwned',
                'G.idMunicipio',
                'M.Nombre AS Municipio',
                'G.Remesa',
                DB::raw(
                    "concat_ws(' ',UOC.Nombre, UOC.Paterno, UOC.Materno) as UserInfoOwned"
                )
            )
            ->leftJoin('et_cat_municipio as M', 'G.idMunicipio', '=', 'M.Id')
            ->leftJoin('users as UOC', 'UOC.id', '=', 'G.UserOwned')
            ->leftJoin('vales_remesas as R', 'R.Remesa', '=', 'G.Remesa')
            ->where('G.id', '=', $idGpo)
            ->first();

        if (!$resGpo) {
            return response()->json([
                'success' => true,
                'results' => false,
                'data' => [],
                'message' => 'No se encontraron resultados del Grupo.',
            ]);
        }

        $res = DB::table('vales_aprobados_2022 as N')
            ->select(
                //DB::raw('LPAD(HEX(N.id),6,0) AS id'),
                'c.Folio AS folio',
                'vr.NumAcuerdo AS acuerdo',
                'M.SubRegion AS region',
                DB::raw(
                    "concat_ws(' ',UOC.Nombre, UOC.Paterno, UOC.Materno) as enlace"
                ),
                DB::raw(
                    "concat_ws(' ',N.Nombre, N.Paterno, N.Materno) as nombre"
                ),
                'N.curp',
                DB::raw(
                    "concat_ws(' ',N.Calle, if(N.NumExt is null, ' ', concat('NumExt ',N.NumExt)), if(N.NumInt is null, ' ', concat('Int ',N.NumInt))) AS domicilio"
                ),
                'M.Nombre AS municipio',
                'L.Nombre AS localidad',
                'N.Colonia AS colonia',
                'N.CP AS cp',
                'VS.SerieInicial AS folioinicial',
                'VS.SerieFinal AS foliofinal'
            )
            ->leftJoin('et_cat_municipio as M', 'N.idMunicipio', '=', 'M.Id')
            ->leftJoin(
                'et_cat_localidad_2022 as L',
                'N.idLocalidad',
                '=',
                'L.Id'
            )
            ->Join(
                DB::RAW(
                    '(SELECT idSolicitud,SerieInicial,SerieFinal FROM vales_solicitudes WHERE Ejercicio > 2021) as VS'
                ),
                'VS.idSolicitud',
                '=',
                'N.id'
            )
            ->Join('vales_remesas AS vr', 'N.Remesa', '=', 'vr.Remesa')
            ->leftJoin('users as UOC', 'UOC.id', '=', 'N.UserOwned')
            ->join('vales_status as E', 'N.idStatus', '=', 'E.id')
            ->leftJoin(
                DB::RAW(
                    '(SELECT idVale,Folio FROM cedulas_solicitudes WHERE FechaElimino IS NULL) as c'
                ),
                'c.idVale',
                '=',
                'N.id'
            )
            ->where('N.UserOwned', '=', $resGpo->UserOwned)
            ->where('N.idMunicipio', '=', $resGpo->idMunicipio)
            ->where('N.Remesa', '=', $resGpo->Remesa);

        $data = $res
            ->orderBy('M.Nombre', 'asc')
            ->orderBy('L.Nombre', 'asc')
            ->orderBy('N.Colonia', 'asc')
            ->orderBy('N.Nombre', 'asc')
            ->orderBy('N.Paterno', 'asc')
            ->get()
            ->first();

        if ($data === null) {
            return response()->json([
                'success' => true,
                'results' => false,
                'data' => [],
                'message' => 'Aún no se asignan los vales de este grupo.',
            ]);
        } else {
            return response()->json([
                'success' => true,
                'results' => true,
                'data' => [],
            ]);
        }
    }

    public function validarGrupo2023(Request $request)
    {
        $params = $request->all();
        $user = auth()->user();

        if (!isset($params['idGrupo'])) {
            return response()->json([
                'success' => true,
                'results' => false,
                'data' => [],
                'message' => 'No se encontraron resultados del Grupo.',
            ]);
        }

        $idGpo = $params['idGrupo'];

        $resGpo = DB::table('vales_grupos as G')
            ->select(
                'G.id',
                'G.idMunicipio',
                'G.idLocalidad',
                'G.ResponsableEntrega',
                'G.Remesa',
                'G.TotalAprobados'
            )
            ->JOIN('et_cat_municipio as M', 'G.idMunicipio', '=', 'M.Id')
            ->JOIN('et_cat_localidad_2022 as L', 'G.idLocalidad', '=', 'L.id')
            ->JOIN('vales_remesas as R', 'R.Remesa', '=', 'G.Remesa')
            ->where('G.id', '=', $idGpo)
            ->first();

        if (!$resGpo) {
            return response()->json([
                'success' => true,
                'results' => false,
                'data' => [],
                'message' => 'No se encontraron resultados del Grupo.',
            ]);
        }

        $res = DB::table('vales as N')
            ->select('N.id')
            ->JOIN('et_cat_municipio as M', 'N.idMunicipio', '=', 'M.Id')
            ->JOIN('et_cat_localidad_2022 as L', 'N.idLocalidad', '=', 'L.id')
            ->Join('vales_solicitudes as VS', 'VS.idSolicitud', '=', 'N.id')
            ->Join('vales_remesas AS vr', 'N.Remesa', '=', 'vr.Remesa')
            ->join('vales_status as E', 'N.idStatus', '=', 'E.id')
            ->where('N.idGrupo', '=', $resGpo->id);

        $total = $res->count();
        $data = $res->first();

        if ($data === null) {
            return response()->json([
                'success' => true,
                'results' => false,
                'data' => [],
                'message' => 'Aún no se asignan los vales de este grupo.',
            ]);
        } else {
            if ($total == $resGpo->TotalAprobados) {
                return response()->json([
                    'success' => true,
                    'results' => true,
                    'data' => [],
                ]);
            } else {
                return response()->json([
                    'success' => true,
                    'results' => false,
                    'data' => [],
                    'message' => 'Faltan vales por asignar.',
                ]);
            }
        }
    }

    public function getAcuseUnico(Request $request)
    {
        $user = auth()->user();
        $parameters = $request->all();

        if (!isset($parameters['folio'])) {
            return response()->json([
                'success' => true,
                'results' => false,
                'data' => [],
                'message' => 'No se encontraron resultados de la solicitud.',
            ]);
        }

        if ($parameters['ejercicio'] == 2022) {
            $res = DB::table('vales_respaldo_2022 as N')
                ->select(
                    DB::raw('LPAD(HEX(N.id),6,0) AS id'),
                    'c.Folio AS folio',
                    'vr.NumAcuerdo AS acuerdo',
                    'M.SubRegion AS region',
                    DB::raw(
                        "concat_ws(' ',UOC.Nombre, UOC.Paterno, UOC.Materno) as enlace"
                    ),
                    DB::raw(
                        "concat_ws(' ',N.Nombre, N.Paterno, N.Materno) as nombre"
                    ),
                    'N.curp',
                    DB::raw(
                        "concat_ws(' ',N.Calle, if(N.NumExt is null, ' ', concat('NumExt ',N.NumExt)), if(N.NumInt is null, ' ', concat('Int ',N.NumInt))) AS domicilio"
                    ),
                    'M.Nombre AS municipio',
                    'L.Nombre AS localidad',
                    'N.Colonia AS colonia',
                    'N.CP AS cp',
                    'VS.SerieInicial AS folioinicial',
                    'VS.SerieFinal AS foliofinal'
                )
                ->leftJoin(
                    'et_cat_municipio as M',
                    'N.idMunicipio',
                    '=',
                    'M.Id'
                )
                ->leftJoin(
                    'et_cat_localidad as L',
                    'N.idLocalidad',
                    '=',
                    'L.Id'
                )
                ->leftJoin(
                    'vales_solicitudes_respaldo_2022 as VS',
                    'VS.idSolicitud',
                    '=',
                    'N.id'
                )
                ->leftJoin('vales_remesas AS vr', 'N.Remesa', '=', 'vr.Remesa')
                ->leftJoin('users as UOC', 'UOC.id', '=', 'N.UserOwned')
                ->join('vales_status as E', 'N.idStatus', '=', 'E.id')
                ->leftJoin('cedulas_solicitudes AS c', 'c.idVale', '=', 'N.id')
                ->where('N.id', $parameters['folio']);
        } else {
            $res = DB::table('vales as N')
                ->select(
                    DB::raw('LPAD(HEX(N.id),6,0) AS id'),
                    'N.id  AS idVale',
                    'vr.NumAcuerdo AS acuerdo',
                    'M.SubRegion AS region',
                    'N.ResponsableEntrega AS enlace',
                    DB::raw(
                        "concat_ws(' ',N.Nombre, N.Paterno, N.Materno) as nombre"
                    ),
                    'N.curp',
                    DB::raw(
                        "concat_ws(' ',N.Calle, if(N.NumExt is null, ' ', concat('NumExt ',N.NumExt)), if(N.NumInt is null, ' ', concat('Int ',N.NumInt))) AS domicilio"
                    ),
                    'M.Nombre AS municipio',
                    'L.Nombre AS localidad',
                    'N.Colonia AS colonia',
                    'N.CP AS cp',
                    'VS.SerieInicial AS folioinicial',
                    'VS.SerieFinal AS foliofinal'
                )
                ->JOIN('et_cat_municipio as M', 'N.idMunicipio', '=', 'M.Id')
                ->JOIN(
                    'et_cat_localidad_2022 as L',
                    'N.idLocalidad',
                    '=',
                    'L.id'
                )
                ->LEFTJOIN(
                    DB::RAW(
                        '(SELECT idSolicitud,SerieInicial,SerieFinal FROM vales_solicitudes WHERE Ejercicio = 2023) as VS'
                    ),
                    'VS.idSolicitud',
                    '=',
                    'N.id'
                )
                ->Join('vales_remesas AS vr', 'N.Remesa', '=', 'vr.Remesa')
                ->join('vales_status as E', 'N.idStatus', '=', 'E.id')
                ->where('N.id', $parameters['folio']);
        }
        $data = $res
            ->orderBy('M.Nombre', 'asc')
            ->orderBy('L.Nombre', 'asc')
            ->orderBy('N.Colonia', 'asc')
            ->orderBy('N.Nombre', 'asc')
            ->orderBy('N.Paterno', 'asc')
            ->get();
        $d = $data
            ->map(function ($x) {
                $x = is_object($x) ? (array) $x : $x;
                $x['codigo'] = DNS1D::getBarcodePNG($x['id'], 'C39');
                return $x;
            })
            ->toArray();
        unset($data);
        unset($res);
        $vales = $d;
        $nombreArchivo = 'acuses_vales' . date('Y-m-d H:i:s');
        $ejercicio = DB::table('vales_solicitudes')
            ->Select('Ejercicio')
            ->Where('idSolicitud', $parameters['folio'])
            ->first();

        if ($parameters['ejercicio'] == 2022) {
            $pdf = \PDF::loadView('pdf_2022', compact('vales'));
        } else {
            if ($ejercicio->Ejercicio == 2023) {
                $pdf = \PDF::loadView('pdf_2023', compact('vales'));
            } else {
                $pdf = \PDF::loadView('pdf', compact('vales'));
            }
        }

        return $pdf->download($nombreArchivo . '.pdf');
    }

    public function getAcuseValesUnico(Request $request)
    {
        $parameters = $request->all();
        $user = auth()->user();

        if (!isset($request->idSolicitud)) {
            return response()->json([
                'success' => true,
                'results' => false,
                'data' => [],
                'message' => 'No se encontraron resultados de la solicitud.',
            ]);
        }

        $res = DB::table('vales_aprobados_2022 as N')
            ->select(
                DB::raw('LPAD(HEX(N.id),6,0) AS id'),
                'c.Folio AS folio',
                'vr.NumAcuerdo AS acuerdo',
                'M.SubRegion AS region',
                DB::raw(
                    "concat_ws(' ',UOC.Nombre, UOC.Paterno, UOC.Materno) as enlace"
                ),
                DB::raw(
                    "concat_ws(' ',N.Nombre, N.Paterno, N.Materno) as nombre"
                ),
                'N.curp',
                DB::raw(
                    "concat_ws(' ',N.Calle, if(N.NumExt is null, ' ', concat('NumExt ',N.NumExt)), if(N.NumInt is null, ' ', concat('Int ',N.NumInt))) AS domicilio"
                ),
                'M.Nombre AS municipio',
                'L.Nombre AS localidad',
                'N.Colonia AS colonia',
                'N.CP AS cp',
                'VS.SerieInicial AS folioinicial',
                'VS.SerieFinal AS foliofinal'
            )
            ->leftJoin('et_cat_municipio as M', 'N.idMunicipio', '=', 'M.Id')
            ->leftJoin('et_cat_localidad as L', 'N.idLocalidad', '=', 'L.Id')
            ->Join('vales_solicitudes as VS', 'VS.idSolicitud', '=', 'N.id')
            ->Join('vales_remesas AS vr', 'N.Remesa', '=', 'vr.Remesa')
            ->leftJoin('users as UOC', 'UOC.id', '=', 'N.UserOwned')
            ->join('vales_status as E', 'N.idStatus', '=', 'E.id')
            ->leftJoin('cedulas_solicitudes AS c', 'c.idVale', '=', 'N.id')
            ->where('N.id', $parameters['idSolicitud']);

        // $resGrupo = DB::table('v_giros as G')
        // ->select('G.Giro', 'NG.idNegocio', 'NG.idGiro')
        // ->join('v_negocios_giros as NG','NG.idGiro','=','G.id');

        $data = $res
            ->orderBy('M.Nombre', 'asc')
            ->orderBy('L.Nombre', 'asc')
            ->orderBy('N.Colonia', 'asc')
            ->orderBy('N.Nombre', 'asc')
            ->orderBy('N.Paterno', 'asc')
            ->get();
        //$data2 = $resGrupo->first();
        // dd($resGpo);
        //     dd($data);

        $d = $data
            ->map(function ($x) {
                $x = is_object($x) ? (array) $x : $x;
                return $x;
            })
            ->toArray();
        unset($data);
        unset($res);

        if (count($d) == 0) {
            return response()->json([
                'success' => false,
                'results' => false,
                'message' => 'Error',
            ]);
        }

        // $d = [
        //     [
        //         'id' => '',
        //         'folio' => '',
        //         'acuerdo' => '',
        //         'region' => '',
        //         'enlace' => '',
        //         'nombre' => '',
        //         'curp' => '',
        //         'domicilio' => '',
        //         'municipio' => '',
        //         'localidad' => '',
        //         'colonia' => '',
        //         'cp' => '',
        //         'folioinicial' => '',
        //         'foliofinal' => '',
        //     ],
        // ];

        //dd($d);

        $vales = $d;
        $nombreArchivo = 'acuses_vales' . date('Y-m-d H:i:s');

        $pdf = \PDF::loadView('pdf', compact('vales'));

        return $pdf->download($nombreArchivo . '.pdf');
    }

    public function getAcuseValesIndividual(Request $request)
    {
        if (!isset($request->folio['Folio'])) {
            return response()->json([
                'success' => true,
                'results' => false,
                'data' => [],
                'message' => 'Debe enviar un folio válido.',
            ]);
        }

        $folio = $request->folio['Folio'];

        if (!ctype_xdigit($folio)) {
            return response()->json([
                'success' => true,
                'results' => false,
                'data' => [],
                'message' => 'El folio ingresado no es válido.',
            ]);
        }

        try {
            $id = hexdec($folio);
        } catch (Exception $e) {
            return response()->json([
                'success' => true,
                'results' => false,
                'data' => [],
                'message' => 'El folio ingresado no es válido.',
            ]);
        }
        $user = auth()->user();

        $validaRegistro = DB::table('vales')
            ->select('id')
            ->where('id', $id)
            ->first();

        $flag = false;
        if ($validaRegistro == null) {
            $validaRegistroAnterior = DB::table('vales_respaldo_2022')
                ->select('id')
                ->where('id', $id)
                ->first();
            if ($validaRegistroAnterior == null) {
                return response()->json([
                    'success' => true,
                    'results' => true,
                    'data' => [],
                    'message' => 'No existe ninguna solicitud con este folio',
                ]);
            } else {
                $flag = true;
            }
        }

        $tableVales = 'vales';
        $tablesSolicitudes = 'vales_solicitudes';

        if ($flag) {
            $tableVales = 'vales_respaldo_2022';
            $tablesSolicitudes = 'vales_solicitudes_respaldo_2022';
        }

        $res = DB::table($tableVales . ' as N')
            ->select(
                'N.id',
                DB::raw('LPAD(HEX(N.id),6,0) AS Folio'),
                'vr.RemesaSistema',
                'N.Remesa',
                'vr.NumAcuerdo AS acuerdo',
                'M.SubRegion AS region',
                DB::raw(
                    "concat_ws(' ',UOC.Nombre, UOC.Paterno, UOC.Materno) as enlace"
                ),
                DB::raw(
                    "concat_ws(' ',N.Nombre, N.Paterno, N.Materno) as nombre"
                ),
                'N.curp',
                DB::raw(
                    "concat_ws(' ',N.Calle, if(N.NumExt is null, ' ', concat('NumExt ',N.NumExt)), if(N.NumInt is null, ' ', concat('Int ',N.NumInt))) AS domicilio"
                ),
                'M.Nombre AS municipio',
                'L.Nombre AS localidad',
                'N.Colonia AS colonia',
                'N.CP AS cp',
                'VS.SerieInicial AS folioinicial',
                'VS.SerieFinal AS foliofinal',
                'N.Ejercicio'
            )
            ->leftJoin('et_cat_municipio as M', 'N.idMunicipio', '=', 'M.Id')
            ->leftJoin('et_cat_localidad as L', 'N.idLocalidad', '=', 'L.Id')
            ->LEFTJoin(
                $tablesSolicitudes . ' as VS',
                'VS.idSolicitud',
                '=',
                'N.id'
            )
            ->LEFTJoin('vales_remesas AS vr', 'N.Remesa', '=', 'vr.Remesa')
            ->leftJoin('users as UOC', 'UOC.id', '=', 'N.UserOwned')
            ->join('vales_status as E', 'N.idStatus', '=', 'E.id')
            ->leftJoin('cedulas_solicitudes AS c', 'c.idVale', '=', 'N.id')
            ->where('N.id', $id);

        $data = $res
            ->orderBy('M.Nombre', 'asc')
            ->orderBy('L.Nombre', 'asc')
            ->orderBy('N.Colonia', 'asc')
            ->orderBy('N.Nombre', 'asc')
            ->orderBy('N.Paterno', 'asc')
            ->get();

        $d = $data
            ->map(function ($x) {
                $x = is_object($x) ? (array) $x : $x;
                return $x;
            })
            ->toArray();
        unset($data);
        unset($res);

        if (count($d) < 1) {
            return response()->json([
                'success' => true,
                'results' => true,
                'data' => [],
                'message' => 'Esta solicitud no cuenta con remesa',
            ]);
        }

        return response()->json([
            'success' => true,
            'results' => true,
            'data' => $d,
            'ejercicio' => $flag ? 2022 : 2023,
        ]);
    }

    public function setEntrega(Request $request)
    {
        if (!isset($request->folio['Folio'])) {
            return response()->json([
                'success' => true,
                'results' => false,
                'data' => [],
                'message' => 'Debe enviar un folio válido.',
            ]);
        }

        $folio = $request->folio['Folio'];
        $user = auth()->user();

        if (!ctype_xdigit($folio)) {
            return response()->json([
                'success' => true,
                'results' => false,
                'data' => [],
                'message' => 'El folio ingresado no es válido.',
            ]);
        }

        try {
            $id = hexdec($folio);
            if (strlen($request->folio['FechaEntrega']) !== 10) {
                return response()->json([
                    'success' => true,
                    'results' => false,
                    'data' => [],
                    'message' => 'La fecha ingresada no es válida.',
                ]);
            }
            $fechaEntrega = DateTime::createFromFormat(
                'Y-m-d',
                $request->folio['FechaEntrega']
            )->format('Y-m-d');
            $minDate = strtotime(
                DateTime::createFromFormat('Y-m-d', '2023-03-01')->format(
                    'Y-m-d'
                )
            );
            $maxDate = strtotime(
                DateTime::createFromFormat('Y-m-d', '2023-12-22')->format(
                    'Y-m-d'
                )
            );
            if (
                strtotime($fechaEntrega) < $minDate ||
                strtotime($fechaEntrega) > $maxDate
            ) {
                return response()->json([
                    'success' => true,
                    'results' => false,
                    'data' => [],
                    'message' => 'La fecha ingresada no es válida.',
                ]);
            }
        } catch (Exception $e) {
            return response()->json([
                'success' => true,
                'results' => false,
                'data' => [],
                'message' => 'El folio o la fecha ingresada no son válidos.',
            ]);
        }

        try {
            $registro = DB::table('vales')
                ->select('id', 'Remesa', 'isEntregado')
                ->where('id', $id)
                ->first();

            if ($registro === null) {
                return response()->json([
                    'success' => true,
                    'results' => false,
                    'data' => [],
                    'message' =>
                        'El folio ingresado no existe en la base de datos.',
                ]);
            } else {
                if ($registro->Remesa === null) {
                    return response()->json([
                        'success' => true,
                        'results' => false,
                        'data' => [],
                        'message' =>
                            'El folio ingresado no cuentra con remesa.',
                    ]);
                } else {
                    if ($registro->isEntregado === 1) {
                        return response()->json([
                            'success' => true,
                            'results' => false,
                            'data' => [],
                            'message' =>
                                'El folio ingresado ya fue marcado como entregado.',
                        ]);
                    }
                }
            }

            $user = auth()->user();

            DB::table('vales')
                ->where('id', $id)
                ->update([
                    'isEntregado' => 1,
                    'entrega_at' => $fechaEntrega,
                    'isEntregadoOwner' => $user->id,
                ]);

            return response()->json([
                'success' => true,
                'results' => true,
                'message' => 'El folio fue marcado como entregado',
                'd' => [],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'results' => false,
                'message' => 'Consulte al administrador',
                'errors' => $e->getMessage(),
            ]);
        }
    }

    public function getAcuseVales(Request $request)
    {
        $parameters = $request->all();
        $user = auth()->user();

        $resGpo = DB::table('vales_grupos as G')
            ->select(
                'G.id',
                'R.NumAcuerdo',
                'R.Leyenda',
                'R.FechaAcuerdo',
                'G.UserOwned',
                'G.idMunicipio',
                'M.Nombre AS Municipio',
                'G.Remesa',
                DB::raw(
                    "concat_ws(' ',UOC.Nombre, UOC.Paterno, UOC.Materno) as UserInfoOwned"
                )
            )
            ->leftJoin('et_cat_municipio as M', 'G.idMunicipio', '=', 'M.Id')
            ->leftJoin('users as UOC', 'UOC.id', '=', 'G.UserOwned')
            ->leftJoin('vales_remesas as R', 'R.Remesa', '=', 'G.Remesa')
            ->where('G.id', '=', $request->idGrupo)
            ->first();

        $carpeta = $resGpo->id . $resGpo->idMunicipio . $resGpo->Remesa;

        $path = public_path() . '/subidos/' . $carpeta;
        $fileExists = public_path() . '/subidos/' . $carpeta . '.zip';

        if (file_exists($fileExists)) {
            return response()->download($fileExists);
        }

        $res = DB::table('vales_aprobados_2022 as N')
            ->select(
                DB::raw('LPAD(HEX(N.id),6,0) AS id'),
                'N.id  AS idVale',
                'c.Folio AS folio',
                'vr.NumAcuerdo AS acuerdo',
                'M.SubRegion AS region',
                DB::raw(
                    "concat_ws(' ',UOC.Nombre, UOC.Paterno, UOC.Materno) as enlace"
                ),
                DB::raw(
                    "concat_ws(' ',N.Nombre, N.Paterno, N.Materno) as nombre"
                ),
                'N.curp',
                DB::raw(
                    "concat_ws(' ',N.Calle, if(N.NumExt is null, ' ', concat('NumExt ',N.NumExt)), if(N.NumInt is null, ' ', concat('Int ',N.NumInt))) AS domicilio"
                ),
                'M.Nombre AS municipio',
                'L.Nombre AS localidad',
                'N.Colonia AS colonia',
                'N.CP AS cp',
                'VS.SerieInicial AS folioinicial',
                'VS.SerieFinal AS foliofinal'
            )
            ->leftJoin('et_cat_municipio as M', 'N.idMunicipio', '=', 'M.Id')
            ->leftJoin(
                'et_cat_localidad_2022 as L',
                'N.idLocalidad',
                '=',
                'L.Id'
            )
            ->Join(
                DB::RAW(
                    '(SELECT * FROM vales_solicitudes WHERE Ejercicio > 2021) as VS'
                ),
                'VS.idSolicitud',
                '=',
                'N.id'
            )
            ->Join('vales_remesas AS vr', 'N.Remesa', '=', 'vr.Remesa')
            ->leftJoin('users as UOC', 'UOC.id', '=', 'N.UserOwned')
            ->join('vales_status as E', 'N.idStatus', '=', 'E.id')
            ->leftJoin('cedulas_solicitudes AS c', 'c.idVale', '=', 'N.id')
            ->where('N.UserOwned', '=', $resGpo->UserOwned)
            ->where('N.idMunicipio', '=', $resGpo->idMunicipio)
            ->where('N.Remesa', '=', $resGpo->Remesa);
        $data = $res
            ->orderBy('M.Nombre', 'asc')
            ->orderBy('L.Nombre', 'asc')
            ->orderBy('N.Colonia', 'asc')
            ->orderBy('N.Nombre', 'asc')
            ->orderBy('N.Paterno', 'asc')
            ->get();

        $d = $data
            ->map(function ($x) {
                $x = is_object($x) ? (array) $x : $x;
                $x['codigo'] = DNS1D::getBarcodePNG($x['id'], 'C39');
                //dd($x);
                return $x;
            })
            ->toArray();
        unset($data);
        unset($res);

        if (count($d) == 0) {
            //return response()->json(['success'=>false,'results'=>false,'message'=>$res->toSql()])

            $reader = IOFactory::createReader('Xlsx');
            $spreadsheet = $reader->load(
                public_path() . '/archivos/formatoReporteNominaValesv3.xlsx'
            );

            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setCellValue('N6', $resGpo->Municipio);
            $sheet->setCellValue('N7', $resGpo->UserInfoOwned);
            $sheet->setCellValue('N3', $resGpo->NumAcuerdo);
            $sheet->setCellValue('N4', $resGpo->FechaAcuerdo);
            $sheet->setCellValue('A2', $resGpo->Leyenda);
            $sheet->setCellValue(
                'A3',
                'Aprobados mediante ' .
                    $resGpo->NumAcuerdo .
                    ' de fecha ' .
                    $resGpo->FechaAcuerdo
            );

            $writer = new Xlsx($spreadsheet);
            $writer->save(
                'archivos/' .
                    $resGpo->Remesa .
                    '_' .
                    $resGpo->idMunicipio .
                    '_' .
                    $resGpo->UserOwned .
                    '_formatoNominaVales.xlsx'
            );
            $file =
                public_path() .
                '/archivos/' .
                $resGpo->Remesa .
                '_' .
                $resGpo->idMunicipio .
                '_' .
                $resGpo->UserOwned .
                '_formatoNominaVales.xlsx';

            return response()->download(
                $file,
                $resGpo->Remesa .
                    '_' .
                    $resGpo->idMunicipio .
                    '_' .
                    $resGpo->UserOwned .
                    '_NominaValesGrandeza' .
                    date('Y-m-d') .
                    '.xlsx'
            );
        }

        $nombreArchivo =
            'acuses_vales_' .
            $resGpo->id .
            '_' .
            $resGpo->idMunicipio .
            '_' .
            $resGpo->Remesa;

        File::makeDirectory($path, $mode = 0777, true, true);

        $counter = 0;
        foreach (array_chunk($d, 20) as $arrayData) {
            $counter++;
            $vales = $arrayData;
            $pdf = \PDF::loadView('pdf', compact('vales'))->save(
                $path . '/' . $nombreArchivo . '_' . strval($counter) . '.pdf'
            );
        }

        $this->createZipEvidencia($carpeta);

        return response()->download(
            public_path('subidos/' . $carpeta . '.zip')
        );
    }

    public function generateFiles(Request $request)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', 1000);
        $regionId = 1;
        $groups = DB::table('vales_grupos')
            ->where('CveInterventor', '42_5')
            ->whereIn('idMunicipio', function ($query) use ($regionId) {
                $query
                    ->select('id')
                    ->from('et_cat_municipio')
                    ->whereIN('id', [42]);
                //->whereIN('id', [1, 121, 23, 42]);
            })
            ->get();

        $groups->each(function ($row) {
            $groupId = $row->id;
            $this->getAcuseVales2023Masivo($groupId);
        });

        return response()->json([
            'success' => true,
            'results' => true,
            'message' => 'ArchivosCreadosCorrectamente.',
        ]);
    }

    public function getAcuseVales2023Masivo($idGrupo)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', 1000);
        $resGpo = DB::table('vales_grupos as G')
            ->select(
                'G.id',
                'G.idMunicipio',
                'G.Remesa',
                'G.TotalAprobados',
                'G.ResponsableEntrega'
            )
            ->where('G.id', '=', $idGrupo)
            ->first();

        $carpeta = $resGpo->id . $resGpo->idMunicipio . $resGpo->Remesa;

        $path = public_path() . '/subidos/' . $carpeta;
        $fileExists = public_path() . '/subidos/' . $carpeta . '.zip';

        // if (file_exists($fileExists)) {
        //     return response()->download($fileExists);
        // }

        $res = DB::table('vales as N')
            ->select(
                DB::raw('LPAD(HEX(N.id),6,0) AS id'),
                'N.id  AS idVale',
                'vr.NumAcuerdo AS acuerdo',
                'M.SubRegion AS region',
                'N.ResponsableEntrega AS enlace',
                DB::raw(
                    "concat_ws(' ',N.Nombre, N.Paterno, N.Materno) as nombre"
                ),
                'N.curp',
                DB::raw(
                    "concat_ws(' ',N.Calle, if(N.NumExt is null, ' ', concat('NumExt ',N.NumExt)), if(N.NumInt is null, ' ', concat('Int ',N.NumInt))) AS domicilio"
                ),
                'M.Nombre AS municipio',
                'L.Nombre AS localidad',
                'N.Colonia AS colonia',
                'N.CP AS cp',
                'VS.SerieInicial AS folioinicial',
                'VS.SerieFinal AS foliofinal'
            )
            ->JOIN('et_cat_municipio as M', 'N.idMunicipio', '=', 'M.Id')
            ->JOIN('et_cat_localidad_2022 as L', 'N.idLocalidad', '=', 'L.id')
            ->JOIN(
                DB::RAW(
                    '(SELECT idSolicitud,SerieInicial,SerieFinal FROM vales_solicitudes WHERE Ejercicio = 2023) as VS'
                ),
                'VS.idSolicitud',
                '=',
                'N.id'
            )
            ->Join('vales_remesas AS vr', 'N.Remesa', '=', 'vr.Remesa')
            ->join('vales_status as E', 'N.idStatus', '=', 'E.id')
            ->where('N.idGrupo', '=', $resGpo->id)
            ->orderBy('M.Nombre', 'asc')
            ->orderBy('N.CveInterventor')
            ->orderBy('L.Nombre', 'asc')
            ->orderBy('N.ResponsableEntrega', 'asc')
            ->orderBy('N.Nombre', 'asc')
            ->orderBy('N.Paterno', 'asc')
            ->get();

        $d = $res
            ->map(function ($x) {
                $x = is_object($x) ? (array) $x : $x;
                $x['codigo'] = DNS1D::getBarcodePNG($x['id'], 'C39');
                return $x;
            })
            ->toArray();
        unset($data);
        unset($res);

        $nombreArchivo =
            'acuses_vales_' .
            $resGpo->id .
            '_' .
            $resGpo->idMunicipio .
            '_' .
            $resGpo->Remesa;

        File::makeDirectory($path, $mode = 0777, true, true);

        $counter = 0;
        foreach (array_chunk($d, 20) as $arrayData) {
            $counter++;
            $vales = $arrayData;
            $pdf = \PDF::loadView('pdf', compact('vales'))->save(
                $path . '/' . $nombreArchivo . '_' . strval($counter) . '.pdf'
            );
            unset($pdf);
        }

        $this->createZipEvidencia($carpeta);
    }

    public function getAcuseVales2023(Request $request)
    {
        if (!isset($request->idGrupo)) {
            return response()->json([
                'success' => true,
                'results' => false,
                'data' => [],
                'message' => 'No se recibió ningún id del Grupo.',
            ]);
        }

        $parameters = $request->all();
        $user = auth()->user();
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', 1000);
        $resGpo = DB::table('vales_grupos as G')
            ->select(
                'G.id',
                'G.idMunicipio',
                'G.Remesa',
                'G.Ejercicio',
                'G.TotalAprobados',
                'G.ResponsableEntrega',
                'G.CveInterventor',
                'M.Nombre AS Municipio',
                'L.Nombre AS Localidad'
            )
            ->Join('et_cat_municipio AS M', 'G.idMunicipio', 'M.id')
            ->Join('et_cat_localidad_2022 AS L', 'G.idLocalidad', 'L.id')
            ->where('G.id', '=', $request->idGrupo)
            ->first();

        $carpeta = $resGpo->id . $resGpo->idMunicipio . $resGpo->Remesa;
        $mun = $resGpo->Municipio;

        if (str_contains($mun, 'DOLORES')) {
            $mun = 'DOLORESH';
        } elseif (str_contains($mun, 'SILAO')) {
            $mun = 'SILAO';
        }

        $path = public_path() . '/subidos/' . $carpeta;
        $fileExists = public_path() . '/subidos/' . $carpeta . '.zip';

        if (file_exists($fileExists)) {
            return response()->download(
                $fileExists,
                'ACUSES_' .
                    $resGpo->id .
                    '_' .
                    str_replace(' ', '_', $mun) .
                    '_' .
                    $resGpo->CveInterventor .
                    '_' .
                    str_replace(' ', '_', $resGpo->ResponsableEntrega) .
                    '_' .
                    str_replace(' ', '_', $resGpo->TotalAprobados) .
                    '_' .
                    str_replace(' ', '_', $resGpo->Localidad) .
                    '.zip'
            );
        }

        $res = DB::table('vales as N')
            ->select(
                DB::raw('LPAD(HEX(N.id),6,0) AS id'),
                'N.id  AS idVale',
                'vr.NumAcuerdo AS acuerdo',
                'M.SubRegion AS region',
                'N.ResponsableEntrega AS enlace',
                DB::raw(
                    "concat_ws(' ',N.Nombre, N.Paterno, N.Materno) as nombre"
                ),
                'N.curp',
                DB::raw(
                    "concat_ws(' ',N.Calle, if(N.NumExt is null, ' ', concat('NumExt ',N.NumExt)), if(N.NumInt is null, ' ', concat('Int ',N.NumInt))) AS domicilio"
                ),
                'M.Nombre AS municipio',
                'L.Nombre AS localidad',
                'N.Colonia AS colonia',
                'N.CP AS cp',
                'VS.SerieInicial AS folioinicial',
                'VS.SerieFinal AS foliofinal'
            )
            ->JOIN('et_cat_municipio as M', 'N.idMunicipio', '=', 'M.Id')
            ->JOIN('et_cat_localidad_2022 as L', 'N.idLocalidad', '=', 'L.id')
            ->JOIN('vales_solicitudes  as VS', 'VS.idSolicitud', '=', 'N.id')
            ->Join('vales_remesas AS vr', 'N.Remesa', '=', 'vr.Remesa')
            ->join('vales_status as E', 'N.idStatus', '=', 'E.id')
            ->where('N.idGrupo', '=', $resGpo->id)
            ->orderBy('M.Nombre', 'asc')
            ->orderBy('N.CveInterventor')
            ->orderBy('L.Nombre', 'asc')
            ->orderBy('N.ResponsableEntrega', 'asc')
            ->orderBy('N.Nombre', 'asc')
            ->orderBy('N.Paterno', 'asc')
            ->get();

        $d = $res
            ->map(function ($x) {
                $x = is_object($x) ? (array) $x : $x;
                $x['codigo'] = DNS1D::getBarcodePNG($x['id'], 'C39');
                return $x;
            })
            ->toArray();
        unset($data);
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

        $nombreArchivo =
            'ACUSES_' .
            $resGpo->id .
            '_' .
            $resGpo->Remesa .
            '_' .
            str_replace(' ', '_', $mun) .
            '_' .
            str_replace(' ', '_', $resGpo->ResponsableEntrega);

        File::makeDirectory($path, $mode = 0777, true, true);

        $counter = 0;

        $view = 'pdf';

        if ($resGpo->Ejercicio == '2023') {
            $view = 'pdf_2023';
        }

        foreach (array_chunk($d, 20) as $arrayData) {
            $counter++;
            $vales = $arrayData;
            $pdf = \PDF::loadView($view, compact('vales'))->save(
                $path . '/' . $nombreArchivo . '_' . strval($counter) . '.pdf'
            );
            unset($pdf);
        }

        $this->createZipEvidencia($carpeta);

        return response()->download(
            public_path('subidos/' . $carpeta . '.zip'),
            'ACUSES_' .
                $resGpo->id .
                '_' .
                str_replace(' ', '_', $mun) .
                '_' .
                $resGpo->CveInterventor .
                '_' .
                str_replace(' ', '_', $resGpo->ResponsableEntrega) .
                '_' .
                str_replace(' ', '_', $resGpo->TotalAprobados) .
                '_' .
                str_replace(' ', '_', $resGpo->Localidad) .
                '.zip'
        );
    }

    public function getSolicitudVales(Request $request)
    {
        $file = public_path() . '/archivos/SolicitudP.pdf';

        return response()->download(
            $file,
            'Solicitud' . date('Y-m-d') . '.pdf'
        );
    }

    public function getSolicitudesVales(Request $request)
    {
        $parameters = $request->all();
        $user = auth()->user();
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', 1000);
        if (!isset($request->idGrupo)) {
            return response()->json([
                'success' => true,
                'results' => false,
                'data' => [],
                'message' => 'No se envió un id de grupo válido.',
            ]);
        }

        $resGpo = DB::table('vales_grupos as G')
            ->select(
                'G.id',
                'G.idMunicipio',
                'G.Remesa',
                'G.TotalAprobados',
                'G.ResponsableEntrega',
                'G.CveInterventor',
                'M.Nombre AS Municipio',
                'L.Nombre AS Localidad'
            )
            ->Join('et_cat_municipio AS M', 'G.idMunicipio', 'M.id')
            ->Join('et_cat_localidad_2022 AS L', 'G.idLocalidad', 'L.id')
            ->where('G.id', '=', $request->idGrupo)
            ->first();

        $carpeta =
            $resGpo->id . $resGpo->idMunicipio . $resGpo->Remesa . '_Solicitud';

        $path = public_path() . '/subidos/' . $carpeta;
        $fileExists = public_path() . '/subidos/' . $carpeta . '.zip';

        if (file_exists($fileExists)) {
            return response()->download(
                $fileExists,
                'SOLICITUDES_' .
                    $resGpo->id .
                    '_' .
                    str_replace(' ', '_', $resGpo->Municipio) .
                    '_' .
                    $resGpo->CveInterventor .
                    '_' .
                    str_replace(' ', '_', $resGpo->ResponsableEntrega) .
                    '_' .
                    str_replace(' ', '_', $resGpo->TotalAprobados) .
                    '_' .
                    str_replace(' ', '_', $resGpo->Localidad) .
                    '.zip'
            );
        }

        $res = DB::table('vales as N')
            ->select(
                DB::raw('LPAD(HEX(N.id),6,0) AS id'),
                DB::RAw(
                    'CASE WHEN N.FechaSolicitud IS NOT NULL THEN date_format(N.FechaSolicitud,"%d/%m/%Y")
                    ELSE "          " END AS FechaSolicitud'
                ),
                DB::raw(
                    'CONCAT_WS(" ",N.Nombre,N.Paterno,N.Materno) AS Nombre'
                ),
                'N.CURP',
                'N.Sexo',
                'N.Calle',
                'N.NumExt',
                'N.NumInt',
                'N.CP',
                'N.Colonia',
                'L.Nombre AS Localidad',
                'm.Nombre AS Municipio',
                DB::raw('NULL AS Tutor'),
                DB::raw('NULL AS Parentesco'),
                DB::raw('NULL AS CURPTutor'),
                'N.TelFijo AS Telefono',
                'N.TelCelular AS Celular',
                'N.CorreoElectronico AS Correo'
            )
            ->JOIN('et_cat_municipio as m', 'N.idMunicipio', '=', 'm.Id')
            ->JOIN('et_cat_localidad_2022 as L', 'N.idLocalidad', '=', 'L.id')
            ->where('N.idGrupo', '=', $resGpo->id)
            ->orderBy('m.Nombre', 'asc')
            ->orderBy('N.CveInterventor', 'ASC')
            ->orderBy('L.Nombre', 'asc')
            ->orderBy('N.ResponsableEntrega', 'asc')
            ->orderBy('N.Nombre', 'asc')
            ->orderBy('N.Paterno', 'asc')
            ->get();

        $d = $res
            ->map(function ($x) {
                $x = is_object($x) ? (array) $x : $x;
                return $x;
            })
            ->toArray();
        unset($data);
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

        $nombreArchivo =
            'SOLICITUDES_' .
            $resGpo->id .
            '_' .
            str_replace(' ', '_', $resGpo->Municipio) .
            '_' .
            $resGpo->Remesa;

        File::makeDirectory($path, $mode = 0777, true, true);

        $counter = 0;
        foreach (array_chunk($d, 20) as $arrayData) {
            $counter++;
            $vales = $arrayData;
            $pdf = \PDF::loadView('pdf_solicitud', compact('vales'))->save(
                $path . '/' . $nombreArchivo . '_' . strval($counter) . '.pdf'
            );
            unset($pdf);
        }

        $this->createZipEvidencia($carpeta);

        return response()->download(
            public_path('subidos/' . $carpeta . '.zip'),
            'SOLICITUDES_' .
                $resGpo->id .
                '_' .
                str_replace(' ', '_', $resGpo->Municipio) .
                '_' .
                $resGpo->CveInterventor .
                '_' .
                str_replace(' ', '_', $resGpo->ResponsableEntrega) .
                '_' .
                str_replace(' ', '_', $resGpo->TotalAprobados) .
                '_' .
                str_replace(' ', '_', $resGpo->Localidad) .
                '.zip'
        );
        // $vales = $d;
        // $nombreArchivo = 'solicitud_vales' . date('Y-m-d H:i:s');
        // $pdf = \PDF::loadView('pdf_solicitud', compact('vales'));
        // return $pdf->download($nombreArchivo . '.pdf');
    }

    public function getSolicitudesValeEstatico(Request $request)
    {
        $parameters = $request->all();
        $user = auth()->user();
        // if (!isset($request->idGrupo)) {
        //     return response()->json([
        //         'success' => true,
        //         'results' => false,
        //         'data' => [],
        //         'message' => 'No se envió un id de grupo válido.',
        //     ]);
        // }

        // $resGpo = DB::table('vales_grupos as G')
        //     ->select(
        //         'G.id',
        //         'G.ResponsableEntrega',
        //         'G.idMunicipio',
        //         'G.Remesa'
        //     )
        //     ->where('G.id', '=', $request->idGrupo)
        //     ->first();

        // $carpeta =
        //     $resGpo->id . $resGpo->idMunicipio . $resGpo->Remesa . '_Solicitud';

        // $path = public_path() . '/subidos/' . $carpeta;
        // $fileExists = public_path() . '/subidos/' . $carpeta . '.zip';

        // if (file_exists($fileExists)) {
        //     return response()->download($fileExists);
        // }

        $res = DB::table('vales as N')
            ->select(
                DB::raw('LPAD(HEX(N.id),6,0) AS id'),
                DB::RAw(
                    'CASE WHEN N.FechaSolicitud IS NOT NULL THEN date_format(N.FechaSolicitud,"%d/%m/%Y")
                    ELSE "          " END AS FechaSolicitud'
                ),
                DB::raw(
                    'CONCAT_WS(" ",N.Nombre,N.Paterno,N.Materno) AS Nombre'
                ),
                'N.CURP',
                'N.Sexo',
                'N.Calle',
                'N.NumExt',
                'N.NumInt',
                'N.CP',
                'N.Colonia',
                'L.Nombre AS Localidad',
                'm.Nombre AS Municipio',
                DB::raw('NULL AS Tutor'),
                DB::raw('NULL AS Parentesco'),
                DB::raw('NULL AS CURPTutor'),
                'N.TelFijo AS Telefono',
                'N.TelCelular AS Celular',
                'N.CorreoElectronico AS Correo'
            )
            ->JOIN('et_cat_municipio as m', 'N.idMunicipio', '=', 'm.Id')
            ->JOIN('et_cat_localidad_2022 as L', 'N.idLocalidad', '=', 'L.id')
            ->JOIN('vales_solicitudes as s', 's.idSolicitud', '=', 'N.id')
            ->WHEREIN('s.SerieInicial', [2126731, 2127541])
            ->orderBy('m.Nombre', 'asc')
            ->orderBy('N.CveInterventor', 'ASC')
            ->orderBy('L.Nombre', 'asc')
            ->orderBy('N.ResponsableEntrega', 'asc')
            ->orderBy('N.Nombre', 'asc')
            ->orderBy('N.Paterno', 'asc')
            ->get();

        $d = $res
            ->map(function ($x) {
                $x = is_object($x) ? (array) $x : $x;
                return $x;
            })
            ->toArray();
        unset($data);
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

        $nombreArchivo = 'solicitudes';

        // File::makeDirectory($path, $mode = 0777, true, true);

        $counter = 0;
        foreach (array_chunk($d, 20) as $arrayData) {
            $vales = $arrayData;
            $pdf = \PDF::loadView('pdf_solicitud', compact('vales'));
            return $pdf->download($nombreArchivo . '.pdf');
        }

        // $this->createZipEvidencia($carpeta);

        // return response()->download(
        //     public_path('subidos/' . $carpeta . '.zip')
        // );
        // $vales = $d;
        // $nombreArchivo = 'solicitud_vales' . date('Y-m-d H:i:s');
        // $pdf = \PDF::loadView('pdf_solicitud', compact('vales'));
        // return $pdf->download($nombreArchivo . '.pdf');
    }

    public function getSolicitudesValeUnico(Request $request)
    {
        $parameters = $request->all();
        $user = auth()->user();
        if (!isset($parameters['folio'])) {
            return response()->json([
                'success' => true,
                'results' => false,
                'data' => [],
                'message' => 'No se encontraron resultados de la solicitud.',
            ]);
        }

        $res = DB::table('vales as N')
            ->select(
                DB::raw('LPAD(HEX(N.id),6,0) AS id'),
                DB::RAw(
                    'CASE WHEN N.FechaSolicitud IS NOT NULL THEN date_format(N.FechaSolicitud,"%d/%m/%Y")
                    ELSE "          " END AS FechaSolicitud'
                ),
                DB::raw(
                    'CONCAT_WS(" ",N.Nombre,N.Paterno,N.Materno) AS Nombre'
                ),
                'N.CURP',
                'N.Sexo',
                'N.Calle',
                'N.NumExt',
                'N.NumInt',
                'N.CP',
                'N.Colonia',
                'L.Nombre AS Localidad',
                'm.Nombre AS Municipio',
                DB::raw('NULL AS Tutor'),
                DB::raw('NULL AS Parentesco'),
                DB::raw('NULL AS CURPTutor'),
                'N.TelFijo AS Telefono',
                'N.TelCelular AS Celular',
                'N.CorreoElectronico AS Correo'
            )
            ->JOIN('et_cat_municipio as m', 'N.idMunicipio', '=', 'm.Id')
            ->JOIN('et_cat_localidad_2022 as L', 'N.idLocalidad', '=', 'L.id')
            ->LeftJOIN('vales_solicitudes as s', 's.idSolicitud', '=', 'N.id')
            ->where('N.id', $parameters['folio'])
            ->orderBy('m.Nombre', 'asc')
            ->orderBy('N.CveInterventor', 'ASC')
            ->orderBy('L.Nombre', 'asc')
            ->orderBy('N.ResponsableEntrega', 'asc')
            ->orderBy('N.Nombre', 'asc')
            ->orderBy('N.Paterno', 'asc')
            ->get();

        $d = $res
            ->map(function ($x) {
                $x = is_object($x) ? (array) $x : $x;
                return $x;
            })
            ->toArray();
        unset($data);
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

        $nombreArchivo = 'solicitud-' . $parameters['folio'];
        $counter = 0;
        foreach (array_chunk($d, 20) as $arrayData) {
            $vales = $arrayData;
            $pdf = \PDF::loadView('pdf_solicitud', compact('vales'));
            return $pdf->download($nombreArchivo . '.pdf');
        }
    }

    private function createZipEvidencia($carpeta)
    {
        try {
            $files = glob(public_path('subidos/' . $carpeta . '/*'));
            $fileName = $carpeta . '.zip';
            //$path = Storage::disk('subidos')->path($fileName);
            $path = public_path('subidos/' . $fileName);
            Zipper::make($path)
                ->add($files)
                ->close();
            if (\file_exists(public_path('subidos/' . $carpeta))) {
                File::deleteDirectory(public_path('subidos/' . $carpeta));
            }
        } catch (Exception $e) {
            return false;
        }
    }

    public function getSumaVoluntadesWord(Request $request)
    {
        if (!isset($request->id)) {
            return response()->json([
                'success' => false,
                'results' => false,
                'message' => 'Hace falta la CURP',
            ]);
        }

        $data = DB::table('v_negocios as N')
            ->select(
                'N.id',
                DB::raw("LPAD(HEX(N.id),6,'0') ClaveUnica"),
                DB::raw("md5(LPAD(HEX(N.id),6,'0')) SecClaveUnica"),
                'N.NombreEmpresa',
                DB::raw(
                    "concat_WS(' ',N.Nombre, N.Paterno, N.Materno) AS Contacto"
                ),
                'M.Nombre AS Municipio',
                'N.Banco',
                'N.CLABE',
                'N.QuiereTransferencia',
                'N.FechaInscripcion',
                DB::raw("date_format(FechaInscripcion,'%d') DD"),
                DB::raw(
                    "if(FechaInscripcion, date_format(FechaInscripcion,'%d'),' ') DD"
                ),
                DB::raw(
                    "if(FechaInscripcion, date_format(FechaInscripcion,'%m'),' ') MM"
                )
            )
            ->join('et_cat_municipio as M', 'N.idMunicipio', '=', 'M.Id')
            ->where('N.id', $request->id)
            ->first();

        if (is_null($data)) {
            return response()->json([
                'success' => true,
                'results' => false,
                'message' => 'No existe el registro',
            ]);
        }

        $Facultado = DB::table('v_negocios_pagadores as P')
            ->select(
                'P.id',
                'P.idNegocio',
                'P.CURP',
                DB::raw(
                    "concat_WS(' ',P.Nombre, P.Paterno, P.Materno) as Facultado"
                ),
                'P.idStatus'
            )
            ->where('P.idNegocio', '=', $data->id)
            ->first();

        $InfoMes['01'] = 'Enero';
        $InfoMes['02'] = 'Febrero';
        $InfoMes['03'] = 'Marzo';
        $InfoMes['04'] = 'Abril';
        $InfoMes['05'] = 'Mayo';
        $InfoMes['06'] = 'Junio';
        $InfoMes['07'] = 'Julio';
        $InfoMes['08'] = 'Agosto';
        $InfoMes['09'] = 'Septiembre';
        $InfoMes['10'] = 'Octubre';
        $InfoMes['11'] = 'Noviembre';
        $InfoMes['12'] = 'Diciembre';
        $InfoMes[' '] = ' ';

        $d = new DNS1D();
        //$d->setStorPath(__DIR__.'/cache/');
        $d->setStorPath(base_path() . '/barcode');
        //echo $d->getBarcodePNGPath('9780691147727', 'EAN13');

        $templateProcessor = new TemplateProcessor(
            public_path() .
                '/archivos/Suma_Voluntades_Comercio_ANEXO_2 04052020.docx'
        );
        $templateProcessor->setValue('Contacto', $data->Contacto);
        $templateProcessor->setValue('FolioUnico', $data->ClaveUnica);
        $templateProcessor->setValue('Comercio', $data->NombreEmpresa);
        $templateProcessor->setValue('Municipio', $data->Municipio);
        $templateProcessor->setValue('CLABE', $data->CLABE);
        $templateProcessor->setValue('Banco', $data->Banco);
        if (!is_null($Facultado)) {
            $templateProcessor->setValue('Facultado', $Facultado->Facultado);
        } else {
            $templateProcessor->setValue('Facultado', '');
        }
        $templateProcessor->setValue('Dia', $data->DD);
        $templateProcessor->setValue('Mes', $InfoMes[$data->MM]);

        $templateProcessor->setImageValue('Barcode', [
            'path' => $d->getBarcodePNGPath($data->ClaveUnica, 'C128'),
            'width' => 120,
            'height' => 70,
            'ratio' => false,
        ]);

        $templateProcessor->saveAs(
            'AcuerdoVoluntades_' . $data->NombreEmpresa . '.docx'
        );
        return response()->download(
            'AcuerdoVoluntades_' . $data->NombreEmpresa . '.docx'
        );
    }

    //funcion para generar bordes en el excel.
    public static function crearBordes($largo, $columna, &$sheet)
    {
        for ($i = 0; $i < $largo; $i++) {
            $inicio = 11 + $i;

            $sheet
                ->getStyle($columna . $inicio)
                ->getBorders()
                ->getTop()
                ->setBorderStyle(
                    \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
                );
            $sheet
                ->getStyle($columna . $inicio)
                ->getBorders()
                ->getBottom()
                ->setBorderStyle(
                    \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
                );
            $sheet
                ->getStyle($columna . $inicio)
                ->getBorders()
                ->getLeft()
                ->setBorderStyle(
                    \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
                );
            $sheet
                ->getStyle($columna . $inicio)
                ->getBorders()
                ->getRight()
                ->setBorderStyle(
                    \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
                );
        }
    }

    public static function crearBordesFirmas($Inicio, $largo, $columna, &$sheet)
    {
        for ($i = 0; $i < $largo; $i++) {
            $inicio = $Inicio + $i;

            $sheet
                ->getStyle($columna . $inicio)
                ->getBorders()
                ->getTop()
                ->setBorderStyle(
                    \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
                );
            $sheet
                ->getStyle($columna . $inicio)
                ->getBorders()
                ->getBottom()
                ->setBorderStyle(
                    \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
                );
            $sheet
                ->getStyle($columna . $inicio)
                ->getBorders()
                ->getLeft()
                ->setBorderStyle(
                    \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
                );
            $sheet
                ->getStyle($columna . $inicio)
                ->getBorders()
                ->getRight()
                ->setBorderStyle(
                    \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
                );
        }
    }

    public function getTarjeta(Request $request)
    {
        if (!isset($request->curp)) {
            return response()->json([
                'success' => false,
                'results' => false,
                'message' => 'Hace falta la CURP',
            ]);
        }

        $data = DB::table('et_aprobadoscomite as a')
            ->select(
                DB::raw(
                    "concat_ws(' ', lpad(HEX(t.idGrupo),3,'0'),m.Nombre) as Grupo"
                ),
                'a.FolioC',
                'a.NombreC as Nombre',
                'a.PaternoC as Paterno',
                'a.MaternoC as Materno',
                'm.Nombre as Municipio',
                'l.Nombre as Localidad',
                't.Terminacion'
            )
            ->join('et_tarjetas_asignadas as t', 't.id', '=', 'a.id')
            ->leftJoin('et_cat_municipio as m', 'm.id', '=', 'a.idMunicipioC')
            ->leftJoin(
                'et_cat_localidad_2022 as l',
                'l.id',
                '=',
                'a.idLocalidadC'
            )
            ->join('et_grupo as g', 'g.id', '=', 't.idGrupo')
            ->join('et_cat_municipio as mg', 'mg.id', '=', 'g.idMunicipio')
            ->where('a.CURP', $request->curp)
            ->orderBy('mg.Nombre', 'asc')
            ->orderBy('g.NombreGrupo', 'asc')
            ->orderBy('NombreC', 'asc')
            ->orderBy('PaternoC', 'asc')
            ->orderBy('MaternoC', 'asc')
            ->first();

        // $data = DB::table('et_tarjetas_asignadas as a')
        // ->select(
        //     DB::raw("concat_ws(' ', lpad(HEX(a.idGrupo),3,'0'),c.Nombre) as Grupo"),
        //     'd.FolioC','f.Nombre as Municipio','e.Nombre as Localidad','d.NombreC as Nombre','d.PaternoC as Paterno','d.MaternoC as Materno',
        //     'd.ColoniaC as Colonia','d.CalleC as Calle',
        //     'd.NumeroC as Numero','d.CodigoPostalC as CP','a.Terminacion'
        // )
        // ->join('et_grupo as b','a.idGrupo','=','b.id')
        // ->join('et_cat_municipio as c','b.idMunicipio','=','c.id')
        // ->join('et_aprobadoscomite as d','a.id','=','d.id')
        // ->join('et_cat_localidad as e','e.Id','=','d.idLocalidadC')
        // ->join('et_cat_municipio as f','f.id','=','d.idMunicipioC')
        // ->where('a.CURP',$request->curp)
        // ->orderBy('NombreC','asc')->orderBy('PaternoC','asc')->orderBy('MaternoC','asc')
        // ->first();

        if (is_null($data)) {
            return response()->json([
                'success' => true,
                'results' => false,
                'message' => 'No existe el registro',
            ]);
        }
        $templateProcessor = new TemplateProcessor(
            public_path() . '/archivos/formatoWord.docx'
        );
        $templateProcessor->setValue('folio', $data->FolioC);
        $templateProcessor->setValue('terminacion', $data->Terminacion);
        $templateProcessor->setValue(
            'nombre',
            $data->Nombre . ' ' . $data->Paterno . ' ' . $data->Materno
        );
        $templateProcessor->setValue('mun', $data->Municipio);
        $templateProcessor->setValue('localidad', $data->Localidad);
        $templateProcessor->saveAs('tarjeta' . $request->curp . '.docx');
        return response()->download('tarjeta' . $request->curp . '.docx');
    }

    public function getTarjetasByGrupo(Request $request)
    {
        //creamos el documento
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        //Borramos archivos en el directorio
        $file = new Filesystem();
        $file->cleanDirectory(public_path() . '/tarjetas');

        if (!isset($request->idGrupo)) {
            return response()->json([
                'success' => false,
                'results' => false,
                'message' => 'Hace falta el id del grupo',
            ]);
        }

        $data = DB::table('et_tarjetas_asignadas as a')
            ->select(
                DB::raw(
                    "concat_ws(' ', lpad(HEX(a.idGrupo),3,'0'),c.Nombre) as Grupo"
                ),
                'd.FolioC',
                'f.Nombre as Municipio',
                'e.Nombre as Localidad',
                'd.NombreC as Nombre',
                'd.PaternoC as Paterno',
                'd.MaternoC as Materno',
                'd.ColoniaC as Colonia',
                'd.CalleC as Calle',
                'd.NumeroC as Numero',
                'd.CodigoPostalC as CP',
                'a.Terminacion',
                'a.CURP'
            )
            ->join('et_grupo as b', 'a.idGrupo', '=', 'b.id')
            ->join('et_cat_municipio as c', 'b.idMunicipio', '=', 'c.id')
            ->join('et_aprobadoscomite as d', 'a.id', '=', 'd.id')
            ->join('et_cat_localidad_2022 as e', 'e.Id', '=', 'd.idLocalidadC')
            ->join('et_cat_municipio as f', 'f.id', '=', 'd.idMunicipioC')
            ->where('a.idGrupo', $request->idGrupo)
            ->orderBy('NombreC', 'asc')
            ->orderBy('PaternoC', 'asc')
            ->orderBy('MaternoC', 'asc')
            ->get();

        //borramos el zip..
        File::delete(
            public_path() . 'archivos/grupo' . $request->idGrupo . '.zip'
        );

        if (is_null($data)) {
            return response()->json([
                'success' => true,
                'results' => false,
                'message' => 'No existe el registro',
            ]);
        }
        //Generamos todos los archivos de la consulta...
        for ($i = 0; $i < count($data); $i++) {
            //Cargamos el template y remplazamos los valores, luego guardamos en directorio con el nombre del curp...
            $templateProcessor = $phpWord->loadTemplate(
                public_path() . '/archivos/formatoWord.docx'
            );
            $templateProcessor->setValue('folio', $data[$i]->FolioC);
            $templateProcessor->setValue('terminacion', $data[$i]->Terminacion);
            $templateProcessor->setValue(
                'nombre',
                $data[$i]->Nombre .
                    ' ' .
                    $data[$i]->Paterno .
                    ' ' .
                    $data[$i]->Materno
            );
            $templateProcessor->setValue('mun', $data[$i]->Municipio);
            $templateProcessor->setValue('localidad', $data[$i]->Localidad);
            $templateProcessor->saveAs(
                'tarjetas/tarjeta' . $data[$i]->CURP . '.docx'
            );
        }
        //Se utiliza la libreria de Zipper para generar el archivo .zip
        $files = glob(public_path('tarjetas/*'));
        Zipper::make(public_path('archivos/grupo' . $request->idGrupo . '.zip'))
            ->add($files)
            ->close();

        //Descargamos el zip
        return response()->download(
            public_path('archivos/grupo' . $request->idGrupo . '.zip')
        );
    }

    public function getTarjetas_old(Request $request)
    {
        if (!isset($request->idGrupo)) {
            return response()->json([
                'success' => false,
                'results' => false,
                'message' => 'Hace falta el id del grupo',
            ]);
        }

        $data = DB::table('et_tarjetas_asignadas as a')
            ->select(
                DB::raw(
                    "concat_ws(' ', lpad(HEX(a.idGrupo),3,'0'),c.Nombre) as Grupo"
                ),
                'd.FolioC',
                'f.Nombre as Municipio',
                'e.Nombre as Localidad',
                'd.NombreC as Nombre',
                'd.PaternoC as Paterno',
                'd.MaternoC as Materno',
                'd.ColoniaC as Colonia',
                'd.CalleC as Calle',
                'd.NumeroC as Numero',
                'd.CodigoPostalC as CP',
                'a.Terminacion',
                'a.CURP'
            )
            ->join('et_grupo as b', 'a.idGrupo', '=', 'b.id')
            ->join('et_cat_municipio as c', 'b.idMunicipio', '=', 'c.id')
            ->join('et_aprobadoscomite as d', 'a.id', '=', 'd.id')
            ->join('et_cat_localidad_2022 as e', 'e.Id', '=', 'd.idLocalidadC')
            ->join('et_cat_municipio as f', 'f.id', '=', 'd.idMunicipioC')
            ->where('a.idGrupo', $request->idGrupo)
            ->orderBy('NombreC', 'asc')
            ->orderBy('PaternoC', 'asc')
            ->orderBy('MaternoC', 'asc')
            ->get();

        if (is_null($data)) {
            return response()->json([
                'success' => true,
                'results' => false,
                'message' => 'No existe el registro',
            ]);
        }

        $templateProcessor = new TemplateProcessor(
            public_path() . '/archivos/formatoWord.docx'
        );
        // $templateProcessor->cloneBlock('CLONEME', 3);

        for ($i = 0; $i < count($data); $i++) {
            $templateProcessor->setValue('folio', $data[$i]->FolioC);
            $templateProcessor->setValue('terminacion', $data[$i]->Terminacion);
            $templateProcessor->setValue(
                'nombre',
                $data[$i]->Nombre .
                    ' ' .
                    $data[$i]->Paterno .
                    ' ' .
                    $data[$i]->Materno
            );
            $templateProcessor->setValue('mun', $data[$i]->Municipio);
            $templateProcessor->setValue('localidad', $data[$i]->Localidad);
        }
        $templateProcessor->saveAs('tarjeta.docx');

        // return response()->download('tarjeta'.$request->curp.'.docx');
    }

    //Funcion para generar un archivo pptx con estadisticas
    public function getVales()
    {
        $objPHPPresentation = new PhpPresentation();
        $totales = self::obtenerDatosVales(0);
        $res = self::obtenerDatosVales(1);

        $porcentajeTotal = round(
            (100 / 65000) * $totales[0]->TotalSolicitudes,
            2
        );

        self::crearDiapositiva(
            $objPHPPresentation,
            '/archivos/valeGeneral.png',
            1,
            $totales[0]->TotalSolicitudes,
            $porcentajeTotal,
            $totales[0]->TotalValidaciones,
            $totales[0]->TotalAprobados,
            $totales[0]->TotalRechazados,
            null
        );

        for ($i = 0; $i < count($res); $i++) {
            $porcentaje = round(
                (100 / $res[$i]->Apoyos) * $res[$i]->Solicitudes,
                2
            );
            self::crearDiapositiva(
                $objPHPPresentation,
                '/archivos/valeRegional.png',
                0,
                $res[$i]->Solicitudes,
                $porcentaje,
                $res[$i]->EnValidacion,
                $res[$i]->Aprobados,
                $res[$i]->Rechazados,
                $res[$i]->Region
            );
        }

        $oWriterPPTX = IOFactories::createWriter(
            $objPHPPresentation,
            'PowerPoint2007'
        );
        $oWriterPPTX->save(public_path() . '/vale.pptx');
        return response()->download(public_path('/vale.pptx'));
    }

    //FUNCION PARA OBTENER LOS DATOS PARA EL PPTX
    public static function obtenerDatosVales($tipo)
    {
        $table1 =
            '(select Region, count(idMunicipio) Municipios, sum(Recurso) Recurso, sum(Apoyos) Apoyos From meta_municipio GROUP BY Region) R';
        $table2 =
            '(select MN.SubRegion AS Region,  count(V.id) Solicitudes FROM vales V JOIN et_cat_municipio MN ON V.idMunicipio = MN.Id JOIN vales_status VS ON V.idStatus = VS.id group by MN.SubRegion) V';
        $table3 =
            '(select MN.SubRegion AS Region,  count(V.id) PorValedar FROM vales V JOIN et_cat_municipio MN ON V.idMunicipio = MN.Id JOIN vales_status VS ON V.idStatus = VS.id where VS.id=1 group by MN.SubRegion) PV';
        $table4 =
            '(select MN.SubRegion AS Region,  count(V.id) EnValidacion FROM vales V JOIN et_cat_municipio MN ON V.idMunicipio = MN.Id JOIN vales_status VS ON V.idStatus = VS.id where VS.id=6 group by MN.SubRegion) EV';
        $table5 =
            '(select MN.SubRegion AS Region,  count(V.id) Aprobados FROM vales V JOIN et_cat_municipio MN ON V.idMunicipio = MN.Id JOIN vales_status VS ON V.idStatus = VS.id where VS.id=5 group by MN.SubRegion) AC';
        $table6 =
            '(select MN.SubRegion AS Region,  count(V.id) Rechazados FROM vales V JOIN et_cat_municipio MN ON V.idMunicipio = MN.Id JOIN vales_status VS ON V.idStatus = VS.id where VS.id=4 group by MN.SubRegion) RC';

        if ($tipo == 1) {
            $select =
                'R.Region, R.Municipios, R.Recurso, R.Apoyos, V.Solicitudes, EV.EnValidacion, AC.Aprobados, RC.Rechazados';
        } else {
            $select =
                'SUM(R.Recurso) as TotalRecurso, SUM(R.Apoyos) as TotalApoyo, SUM(V.Solicitudes) as TotalSolicitudes, SUM(EV.EnValidacion) as TotalValidaciones, SUM(AC.Aprobados) as TotalAprobados, SUM(RC.Rechazados) as TotalRechazados';
        }

        $res = DB::table(DB::raw($table1))
            ->select(DB::raw($select))
            ->leftJoin(DB::raw($table2), 'R.Region', '=', 'V.Region')
            ->leftJoin(DB::raw($table3), 'PV.Region', '=', 'R.Region')
            ->leftJoin(DB::raw($table4), 'EV.Region', '=', 'R.Region')
            ->leftJoin(DB::raw($table5), 'AC.Region', '=', 'R.Region')
            ->leftJoin(DB::raw($table6), 'RC.Region', '=', 'R.Region')
            ->get();

        return $res;
    }

    //funcion para crear las diapositivas
    public static function crearDiapositiva(
        $objPHPPresentation,
        $ruta,
        $first,
        $datoSolicitudes,
        $datoPorcentaje,
        $datoValidaciones,
        $datoAprobados,
        $datoRechazados,
        $datoRegion
    ) {
        // Create slide
        if ($first == 1) {
            $currentSlide = $objPHPPresentation->getActiveSlide();
        } else {
            $currentSlide = $objPHPPresentation->createSlide();
        }

        // Create a shape (drawing)
        $shape = $currentSlide->createDrawingShape();
        $shape
            ->setName('PHPPresentation logo')
            ->setDescription('PHPPresentation logo')
            ->setPath(public_path() . $ruta)
            ->setHeight(539)
            ->setWidth(962)
            ->setOffsetX(0)
            ->setOffsetY(70);

        self::crearShapeText(
            $currentSlide,
            $datoSolicitudes,
            250,
            360,
            true,
            'F3399FF0',
            60,
            160,
            40
        );
        self::crearShapeText(
            $currentSlide,
            $datoPorcentaje . '%',
            680,
            380,
            true,
            'F3399FF0',
            60,
            185,
            40
        );
        // Self::crearShapeText($currentSlide,'Todos',327,280,false,'F0000660',30,75,12);
        // Self::crearShapeText($currentSlide,'Todos',514,280,false,'F0000660',30,185,12);
        self::crearShapeText(
            $currentSlide,
            $datoAprobados,
            578,
            342,
            false,
            'FFFFFF',
            30,
            52,
            10
        );
        self::crearShapeText(
            $currentSlide,
            $datoValidaciones,
            578,
            380,
            false,
            'FFFFFF',
            30,
            52,
            10
        );
        self::crearShapeText(
            $currentSlide,
            $datoRechazados,
            578,
            418,
            false,
            'FFFFFF',
            30,
            52,
            10
        );
        self::crearShapeText(
            $currentSlide,
            '0',
            355,
            480,
            true,
            'F0000660',
            60,
            185,
            30
        );
        self::crearShapeText(
            $currentSlide,
            '0%',
            693,
            480,
            true,
            'F0000660',
            60,
            185,
            30
        );

        if (!is_null($datoRegion)) {
            self::crearShapeText(
                $currentSlide,
                $datoRegion,
                480,
                280,
                false,
                'F0000660',
                30,
                75,
                12
            );
        }

        // Self::crearShapeText($currentSlide,'99%',67,562,false,'F0000660',27,93,10);
        // Self::crearShapeText($currentSlide,'98%',192,562,false,'F0000660',27,93,10);
        // Self::crearShapeText($currentSlide,'97%',317,562,false,'F0000660',27,93,10);
        // Self::crearShapeText($currentSlide,'96%',448,562,false,'F0000660',27,93,10);
        // Self::crearShapeText($currentSlide,'95%',572,562,false,'F0000660',27,93,10);
        // Self::crearShapeText($currentSlide,'94%',700,562,false,'F0000660',27,93,10);
        // Self::crearShapeText($currentSlide,'93%',824,562,false,'F0000660',27,93,10);
    }
    // funcion para generar los textos en el pptx
    public static function crearShapeText(
        $currentSlide,
        $texto,
        $coorX,
        $coorY,
        $bold,
        $color,
        $height,
        $width,
        $size
    ) {
        $shape = $currentSlide
            ->createRichTextShape()
            ->setHeight($height)
            ->setWidth($width)
            ->setOffsetX($coorX)
            ->setOffsetY($coorY);
        $shape
            ->getActiveParagraph()
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $textRun = $shape->createTextRun($texto);
        $textRun
            ->getFont()
            ->setBold($bold)
            ->setSize($size)
            ->setColor(new Color($color));
    }
}
