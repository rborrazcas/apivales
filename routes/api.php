<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
// Route::get('/getAcusesEstaticos', 'ReportesController@getAcusesEstaticos');
// Route::get('/getSolicitudesEstaticasVales', 'ReportesController@getSolicitudesEstaticasVales');

// estas rutas se pueden acceder sin proveer de un token válido.
Route::post('/login', 'AuthController@login');

//APIS PULSERAS GTO 3 OCTUBRE MOVIL
Route::post(
    '/getListadoInvitadosMovil',
    'ControllersPulseras\InvitadoController@getListadoInvitadosMovil'
);
Route::post(
    '/setInvitadoMovil',
    'ControllersPulseras\InvitadoController@setInvitadoMovil'
);
Route::post(
    '/updateInvitadoMovil',
    'ControllersPulseras\InvitadoController@updateInvitadoMovil'
);
Route::post(
    '/getListadoCodigoBarraMovil',
    'ControllersPulseras\CodigoBarraController@getListadoCodigoBarraMovil'
);
Route::post(
    '/getListadoAccesosMovil',
    'ControllersPulseras\AccesoController@getListadoAccesosMovil'
);
Route::post(
    '/setAccesoMovil',
    'ControllersPulseras\AccesoController@setAccesoMovil'
);
Route::post(
    '/setAsignarCodigoBarrasInvitadoMovil',
    'ControllersPulseras\InvitadoController@setAsignarCodigoBarrasInvitadoMovil'
);
//APIS MOVIL
Route::post(
    '/setCodigoBarraMovil',
    'ControllersPulseras\CodigoBarraController@setCodigoBarra'
);
Route::post(
    '/getListadoResponsablesMovil',
    'ControllersPulseras\InvitadoController@getListadoResponsables'
); //PENDIENTE

Route::post(
    '/getResponsablesMovil',
    'ControllersPulseras\InvitadoController@getResponsables'
);

Route::get('/hashPassword', 'ZisController@hashPassword');

Route::post('/updateLocation', 'CedulasController@updateLocation');

Route::post('/acuse', 'CedulasController@getFile');

Route::post('/encuestaTCS', 'EncuestasController@createTCS');

// Route::post('/reimprimirVales', 'Vales2023Controller@getListadoPdf');
// Route::post('/reimprimirAcusesVales', 'Vales2023Controller@getAcuses');

// Route::post('/envioMasivoVentanillaY', 'YoPuedoController@envioMasivoYoPuedo');

// Route::post(
//     '/RegisterCalentadores',
//     'CalentadoresSolares@validateCalentadores'
// );

// Route::post(
//     '/envioMasivoVentanilla',
//     'CedulasController@envioMasivoVentanilla'
// );

// Route::post(
//     '/envioMasivoVentanillaC',
//     'CalentadoresController@envioMasivoVentanillaC'
// );

//Route::get('/getReporteInvitadosMovil','ControllersPulseras\ReporteController@getReporteInvitados');
//Route::post('/convertirImagenes', 'CedulasController@convertImage');
// Route::get(
//     '/getExpedientesCalentadores',
//     'CalentadoresController@getExpedientesCalentadores'
// );
Route::post('/archivosYoPuedo', 'YopuedoController@getFilesFromSocioeducativo');
Route::post(
    '/validacionMasivaCalentadores',
    'CalentadoresController@ValidarEstatusCalentadorVentanilla'
);

Route::post(
    '/getExpedientesCalentadores',
    'CalentadoresSolares@getExpedientesxMunicipio'
);

Route::get(
    '/getSolicitudesValeEstatico',
    'ReportesController@getSolicitudesValeEstatico'
);

Route::get('/generateFiles', 'ReportesController@generateFiles');

Route::post('/acuseUnico', 'ReportesController@getAcuseValesUnico');

// APIS para validación de SeriesVales
//! Para beneficiarios
Route::group(['prefix' => 'q3450/v1'], function ($route) {
    Route::post('validate', 'Vales2023Controller@validateSerie');
});

//!Para comercios
Route::group(['prefix' => 'q3450/v2'], function ($route) {
    Route::post('validate', 'Vales2023Controller@validateSerieComercio');
});

// Route::post('expedientes', 'CalentadoresSolares@getExpedientesCS');

// estas rutas requiren de un token válido para poder accederse.
Route::group(['middleware' => 'jwt.auth'], function () {
    Route::post(
        '/getFilesVentanillaCS',
        'CalentadoresSolares@getFilesFromVentanilla'
    );

    Route::post('/register', 'AuthController@register');
    Route::post('/logout', 'AuthController@logout');
    Route::post('/me', 'AuthController@getAuthenticatedUser');
    Route::post('/updateUser', 'AuthController@updateUser');
    Route::post('/getUsersApp', 'AuthController@getUsersApp');
    Route::post('/getRegionUser', 'AuthController@getRegionUser');
    Route::post('/getTipoUsuarios', 'TipoUsuarioController@getTipoUsuarios');
    Route::post('/setTipoUsuario', 'TipoUsuarioController@setTipoUsuario');
    Route::post('/getClues', 'CluesController@getClues');
    Route::post('/setClues', 'CluesController@setClues');
    Route::post('/getMunicipios', 'CatMunicipioController@getMunicipios');
    Route::post('/getLocalidades', 'CatLocalidadesController@getLocalidades');
    Route::post('/getEstados', 'CatEstadoController@getEstados');
    Route::post('/getAlta', 'CatAltaController@getAlta');
    Route::post('/getAntiviral', 'CatAntiviralController@getAntiviral');
    Route::post('/getCie10', 'CatCie10Controller@getCie10');
    Route::post('/getCP', 'CatCPController@getCP');
    Route::post(
        '/getEstadoActual',
        'CatEstadoActualController@getEstadoActual'
    );
    Route::post('/getEvolucion', 'CatEvolucionController@getEvolucion');
    Route::post('/getTipoPaciente', 'CatPacienteController@getTipoPaciente');
    Route::post('/getPaises', 'CatPaisesController@getPaises');
    Route::post('/getParentesco', 'CatParentescoController@getParentesco');
    Route::post('/getServicios', 'CatServiciosController@getServicios');
    Route::post('/getTipoMuestra', 'CatTipoMuestraController@getTipoMuestra');
    Route::post('/getCasosStatus', 'CasosStatusController@getCasosStatus');
    Route::post(
        '/getCasosSeguimiento',
        'CasosSeguimientoController@getCasosSeguimiento'
    );
    Route::post('/getFiltro', 'FiltroController@getFiltro');
    Route::post('/setFiltro', 'FiltroController@setFiltro');
    Route::post('/getPersonas', 'PersonaController@getPersonas');
    Route::post('/setPersonas', 'PersonaController@setPersonas');
    //COMMIT
    Route::post('/getTriage', 'TriageController@getTriage'); //pendiente -> Validar Unique

    //API ET -->
    Route::post('/getLocalidadET', 'ETCatLocalidadController@getLocalidadET');
    Route::post('/getMunicipiosET', 'ETCatMunicipioController@getMunicipiosET');
    Route::post(
        '/getMunicipiosETVales',
        'ETCatMunicipioController@getMunicipiosETVales'
    );
    Route::post('/getGrupoET', 'ETGrupoController@getGrupoET');
    Route::post('/setGrupoET', 'ETGrupoController@setGrupoET');
    Route::post('/updateGrupoET', 'ETGrupoController@updateGrupoET');
    Route::post('/getTarjetasET', 'ETTarjetasController@getTarjetasET');
    Route::post(
        '/getTarjetaAsignadaET',
        'ETTarjetasAsignadasController@getTarjetaAsignadaET'
    );
    Route::post(
        '/setTarjetaAsignadaET',
        'ETTarjetasAsignadasController@setTarjetaAsignadaET'
    );
    Route::post(
        '/deleteTarjetaAsignadaET',
        'ETTarjetasAsignadasController@deleteTarjetaAsignadaET'
    );
    Route::post(
        '/getTarjetasAsignadasGrupoET',
        'ETGrupoUsersController@getTarjetasAsignadasGrupoET'
    );
    Route::post('/setGrupoUserET', 'ETGrupoUsersController@setGrupoUserET');
    Route::post('/getAprobados', 'ETAprobadosComiteController@getAprobados');
    Route::post('/getRechazados', 'ETAprobadosComiteController@getRechazados');

    Route::post('/getExport', 'ETDocumentoIndividualController@getDocumento');

    //Para reportes GUGA

    Route::get('/getTarjeta', 'ReportesController@getTarjeta');
    Route::get('/getReporteGrupos', 'ReportesController@getReporteGrupos');
    Route::get(
        '/getReporteComercios',
        'ReportesController@getReporteComercios'
    );
    Route::get(
        '/downloadReporteComercios',
        'ReportesController@downloadReporteComercios'
    );
    Route::get(
        '/getReportesolicitudVales',
        'ReportesController@getReportesolicitudVales'
    );
    Route::get(
        '/getReporteCompletoVales',
        'CedulasController@getReporteCompletoVales'
    );
    Route::get(
        '/getReporteSolicitudVentanillaVales',
        'CedulasController@getReporteSolicitudVentanillaVales'
    );
    Route::get(
        '/getReporteVales2022',
        'Vales2022Controller@getReporteVales2022'
    );
    Route::get(
        '/getReporteVales2023',
        'Vales2022Controller@getReporteVales2023'
    );
    Route::get(
        '/getReporteDevueltos',
        'Vales2023Controller@getReporteDevueltos'
    );
    Route::get(
        '/getReporteSolicitudVentanillaCalentadores',
        'CalentadoresController@getReporteSolicitudVentanillaCalentadores'
    );
    Route::get(
        '/getReporteSolicitudVentanillaYoPuedo',
        'YoPuedoController@getReporteSolicitudVentanillaYoPuedo'
    );
    Route::get(
        '/getReporteSolicitudVentanillaDiagnosticoV2',
        'DiagnosticoV2Controller@getReporteSolicitudVentanillaDiagnostico'
    );
    Route::get(
        '/getReporteSolicitudVentanillaProyectos',
        'ProyectosController@getReporteSolicitudVentanillaProyectos'
    );
    Route::get(
        '/getReporteSolicitudVentanillaDiagnostico',
        'DiagnosticoController@getReporteSolicitudVentanillaDiagnostico'
    );
    Route::get(
        '/getReportesolicitudValesDesglosado',
        'ReportesController@getReportesolicitudValesDesglosado'
    );
    Route::get(
        '/getRepValesExpedientes',
        'ReportesController@getRepValesExpedientes'
    );
    Route::get('/getPadronPotencial', 'ReportesController@getPadronPotencial');
    Route::get('/getReportesoVales', 'ReportesController@getReportesoVales');
    Route::get(
        '/getReportesoValesRegionales',
        'ReportesController@getReportesoValesRegionales'
    );
    Route::get(
        '/getReporteFoliosValidar',
        'ReportesController@getReporteFoliosValidar'
    );
    Route::get('/getReporteAcuseVales', 'ReportesController@getAcuseVales');
    Route::get(
        '/getReporteAcuseVales2023',
        'ReportesController@getAcuseVales2023'
    );
    Route::get(
        '/getSolicitudesPdfVales',
        'ReportesController@getSolicitudesVales'
    );

    Route::post('/validarGrupo2023', 'ReportesController@validarGrupo2023');
    Route::post('/validarGrupo', 'ReportesController@validarGrupo');
    Route::get(
        '/getReporteNominaVales',
        'ReportesController@getReporteNominaVales'
    );
    Route::get(
        '/getReporteNominaVales2023',
        'ReportesController@getReporteNominaVales2023'
    );
    Route::get(
        '/getReporteEntregaVales2023',
        'ReportesController@getReporteEntregaVales2023'
    );

    Route::get(
        '/getSumaVoluntadesWord',
        'ReportesController@getSumaVoluntadesWord'
    );
    Route::post(
        '/getSumaVoluntadesWord',
        'ReportesPostController@getSumaVoluntadesWord'
    );

    Route::get('/getTarjetas', 'ReportesController@getTarjetasByGrupo');

    Route::post(
        '/getReporteGruposB',
        'ReportesPostController@getReporteGrupos'
    );
    Route::post('/getTarjetaB', 'ReportesPostController@getTarjeta');
    Route::post('/getTarjetasB', 'ReportesPostController@getTarjetas');

    //Para sedeshu comercios.
    Route::post('/getGiros', 'VGirosController@getGiros');
    Route::post('/setGiros', 'VGirosController@setGiros');
    Route::post('/updateGiros', 'VGirosController@updateGiros'); //Pendiente borrar los giros anteriores.
    Route::post('/getStatus', 'VStatusController@getStatus');
    Route::post('/getNegocios', 'VNegociosController@getNegocios');
    Route::post('/getNegociosApp', 'VNegociosController@getNegociosApp');
    Route::post(
        '/getNegociosAppMaps',
        'VNegociosController@getNegociosAppMaps'
    );
    Route::post(
        '/getNegociosPublico',
        'VNegociosController@getNegociosPublico'
    );
    Route::post(
        '/getNegociosResumen',
        'VNegociosController@getNegociosResumen'
    );
    Route::post('/setNegocios', 'VNegociosController@setNegocios');
    Route::post('/updateNegocios', 'VNegociosController@updateNegocios');
    Route::post(
        '/updateBajaNegocios',
        'VNegociosController@updateBajaNegocios'
    );
    Route::post(
        '/updateRefrendoNegocios',
        'VNegociosController@updateRefrendoNegocios'
    );
    Route::post(
        '/getNegociosPagadores',
        'VNegociosPagadoresController@getNegociosPagadores'
    );
    Route::post(
        '/setNegociosPagadores',
        'VNegociosPagadoresController@setNegociosPagadores'
    );
    Route::post(
        '/updateNegociosPagadores',
        'VNegociosPagadoresController@updateNegociosPagadores'
    );
    Route::post('/getNegociosTipo', 'VNegociosTipoController@getNegociosTipo');

    Route::post('/getVales', 'VValesController@getVales');
    Route::post('/getValesResumen', 'VValesController@getValesResumen');

    Route::post('/getValesAvances', 'VValesController@getValesAvances');
    Route::post(
        '/getCalentadoresAvances',
        'CalentadoresController@getCalentadoresAvances'
    );
    Route::get('/getReporteAvances', 'VValesController@getReporteAvances');
    Route::post('/getHistoryVales', 'VValesController@getHistoryVales');
    Route::post('/getValesInHistory', 'VValesController@getValesInHistory');
    Route::post('/getValesV2', 'VValesController@getValesV2');
    Route::post(
        '/getSolicitudesPorCURP',
        'VValesController@getSolicitudesPorCURP'
    );

    Route::post('/getValesV2Fecha', 'VValesController@getValesV2Fecha');
    Route::post('/getRemesas', 'VValesController@getRemesas');
    Route::post('/getValesFecha', 'VValesController@getValesFecha');

    Route::post('/getValesNotIn', 'VValesController@getValesNotIn');
    Route::post('/getValesNotIn2023', 'VValesController@getValesNotIn2023');

    Route::post('/getValesIn', 'VValesController@getValesIn');
    Route::post('/getValesRegion', 'VValesController@getValesRegion');
    Route::post('/setVales', 'VValesController@setVales');
    Route::post('/deleteVales', 'VValesController@deleteVales');

    Route::post('/updateVales', 'VValesController@updateVales');
    Route::post(
        '/updateRecepcionDocumento',
        'VValesController@updateRecepcionDocumento'
    );
    Route::post('/updateEntregaVales', 'VValesController@updateEntregaVales');
    Route::post(
        '/getValesRecepcionDocumentacion',
        'VValesController@getValesRecepcionDocumentacion'
    );

    Route::post(
        '/getValesDocumentacion',
        'ValesDocumentacionController@getValesDocumentacion'
    );
    Route::post(
        '/setValesDocumentacion',
        'ValesDocumentacionController@setValesDocumentacion'
    );
    Route::post(
        '/deleteValesDocumentacion',
        'ValesDocumentacionController@deleteValesDocumentacion'
    );
    Route::post(
        '/getValesDocumentacionNotIn',
        'ValesDocumentacionController@getValesDocumentacionNotIn'
    );
    Route::post(
        '/getValesDocumentacionIn',
        'ValesDocumentacionController@getValesDocumentacionIn'
    );
    Route::post(
        '/getArticularDocumentacion',
        'ValesDocumentacionController@getArticularDocumentacion'
    );
    Route::post(
        '/getArticularEntregado',
        'ValesDocumentacionController@getArticularEntregado'
    );

    Route::post('/updatePassword', 'AuthController@updatePassword');
    Route::post('/updateUserPassword', 'AuthController@updateUserPassword');

    Route::get(
        '/actualizarTabla',
        'ValesSolicitudesController@actualizarTablaValesUsados'
    );
    Route::get(
        '/actualizarFechas',
        'ValesSolicitudesController@actualizarFechas'
    );
    Route::get(
        '/reciboEntregaRecepcion',
        'ReportesController@reciboEntregaRecepcion'
    );

    Route::post('/AgregarMultiples', 'AuthController@AgregarMultiples'); //Temporal
    Route::get(
        '/getUsersArticuladores',
        'AuthController@getUsersArticuladores'
    );
    Route::post(
        '/getUsersArticuladores',
        'UserController@getUsersArticuladores'
    );
    Route::post(
        '/getUsersArticuladoresV2',
        'UserController@getUsersArticuladoresV2'
    );

    Route::post(
        '/getArticuladores',
        'CedulasController@getArticuladoresVentanilla'
    );

    //Route::post('/getRemesasVales', 'Vales2022Controller@getRemesas');
    Route::post('/getRemesasVales', 'Vales2023Controller@getRemesasAll');

    Route::post(
        '/getArticuladoresYoPuedo',
        'YoPuedoController@getArticuladoresVentanilla'
    );

    Route::post(
        '/getArticuladoresDiagnosticos',
        'DiagnosticoV2Controller@getArticuladoresVentanilla'
    );

    Route::post('/getUsersRecepcionoV2', 'UserController@getUsersRecepcionoV2');

    Route::post('/getEstatusGlobal', 'ValesStatusController@getEstatusGlobal');
    Route::post('/getEstatusRegion', 'ValesStatusController@getEstatusRegion');
    Route::post(
        '/getEstatusMunicipio',
        'ValesStatusController@getEstatusMunicipio'
    );

    Route::post('/getGrupos', 'ValesGruposController@getGrupos'); //Sin parametros
    Route::post('/getGrupos2023', 'ValesGruposController@getGrupos2023'); //Sin parametros
    Route::post('/setGrupos', 'ValesGruposController@setGrupos'); //Sin parametros
    Route::post('/setGrupos2023', 'ValesGruposController@setGrupos2023'); //Sin parametros
    Route::post(
        '/getGruposArticuladores',
        'UserController@getGruposArticuladores'
    ); //Sin parametros
    Route::post(
        '/getArticularSolicitudes',
        'UserController@getArticularSolicitudes'
    ); //Sin parametros
    Route::post(
        '/getArticularSolicitudes2023',
        'UserController@getArticularSolicitudes2023'
    );
    Route::post(
        '/getValesSolicitudes',
        'ValesSolicitudesController@getValesSolicitudes'
    );
    Route::post(
        '/setValesSolicitudes',
        'ValesSolicitudesController@setValesSolicitudes'
    );
    Route::post(
        '/setValesSolicitudes2023',
        'ValesSolicitudesController@setValesSolicitudes2023'
    );

    Route::get('/getValePPT', 'ReportesController@getVales');

    Route::post('/getSerieVale', 'ValesSeriesController@getSerieVale');
    Route::post('/getSerieVale2023', 'ValesSeriesController@getSerieVale2023');

    Route::get('/copiarTablaVales', 'ValidacionesController@copiarTablaVales');
    Route::get('/setValidaciones', 'ValidacionesController@setValidaciones');

    Route::post('/getResumenVales', 'ResumenController@getResumenVales');

    Route::get('/getResumenComite', 'ValidacionesController@cruceVales');
    Route::get(
        '/ExportResumenComite',
        'ValidacionesController@getReporteCruceVales'
    );

    Route::post('/getMiResumenVales', 'ReportesController@getMiResumenVales');
    Route::post(
        '/getMisRemesasTotales',
        'ReportesController@getMisRemesasTotales'
    );
    Route::post(
        '/getReporteNominaValesDetalle',
        'ReportesController@getReporteNominaValesDetalle'
    );
    Route::post('/getMisRemesas', 'ReportesController@getMisRemesas');
    Route::post('/getRemesasAvancesGrupos', 'ReportesController@getRemesas');
    Route::post('/getAvanceRemesas', 'ReportesController@getAvanceRemesas');
    Route::post('/getSearchFolio', 'VValesController@getSearchFolio');
    Route::post(
        '/getRemesasGruposAvance',
        'ReportesController@getRemesasGruposAvance'
    );

    Route::post('/getCatGrupos', 'ReportesController@getCatGrupos');
    Route::post('/getCatGrupos2023', 'ReportesController@getCatGrupos2023');
    Route::post('/getListados2023', 'ReportesController@getListados2023');

    //APIS PULSERAS GTO 30 SEPTIEMBRE
    Route::post(
        '/getListadoInvitados',
        'ControllersPulseras\InvitadoController@getListadoInvitados'
    );
    Route::post(
        '/setInvitado',
        'ControllersPulseras\InvitadoController@setInvitado'
    );
    Route::post(
        '/updateInvitado',
        'ControllersPulseras\InvitadoController@updateInvitado'
    );
    Route::post(
        '/getListadoCodigoBarra',
        'ControllersPulseras\CodigoBarraController@getListadoCodigoBarra'
    );
    Route::post(
        '/getListadoAccesos',
        'ControllersPulseras\AccesoController@getListadoAccesos'
    );
    Route::post('/setAcceso', 'ControllersPulseras\AccesoController@setAcceso');
    Route::post(
        '/setAsignarCodigoBarrasInvitado',
        'ControllersPulseras\InvitadoController@setAsignarCodigoBarrasInvitado'
    );
    //APIS PULSERAS GTO 1 OCTUBRE
    Route::post(
        '/setCodigoBarra',
        'ControllersPulseras\CodigoBarraController@setCodigoBarra'
    );
    Route::post(
        '/getListadoResponsables',
        'ControllersPulseras\InvitadoController@getListadoResponsables'
    ); //PENDIENTE
    Route::post(
        '/getResponsables',
        'ControllersPulseras\InvitadoController@getResponsables'
    );
    Route::get(
        '/getReporteInvitados',
        'ControllersPulseras\ReporteController@getReporteInvitados'
    );
    Route::get(
        '/getReporteInvitadosPorResponsable',
        'ControllersPulseras\ReporteController@getReporteInvitadosPorResponsable'
    );

    Route::post(
        '/getDisponibilidadCodigoBarra',
        'ControllersPulseras\CodigoBarraController@getDisponibilidadCodigoBarra'
    );

    Route::get(
        '/getCodigoBarra',
        'ControllersPulseras\ReporteController@getCodigoBarrasInvitados'
    );
    Route::get(
        '/getCodigoBarraAll',
        'ControllersPulseras\ReporteController@getCodigoBarras'
    );

    Route::post('/getVales2022', 'Vales2022Controller@getSolicitudes');
    Route::post('/getVales2023', 'Vales2022Controller@getSolicitudes2023');

    //CEDULAS
    Route::post('/createSolicitudCedula', 'CedulasController@createSolicitud');
    Route::post('/getSolicitudesCedula', 'CedulasController@getSolicitudes');
    Route::post('/updateSolicitudCedula', 'CedulasController@updateSolicitud');
    Route::post('/deleteSolicitudCedula', 'CedulasController@deleteSolicitud');
    Route::get('/getCatalogsCedula', 'CedulasController@getCatalogsCedula');
    Route::get(
        '/getCatalogsCedulaYoPuedo',
        'YoPuedoController@getCatalogsCedula'
    );

    Route::get(
        '/getCatalogsCedulaDiagnosticos',
        'DiagnosticoV2Controller@getCatalogsCedula'
    );

    Route::post(
        '/createSolicitudCalentador',
        'CalentadoresController@createSolicitud'
    );
    Route::post(
        '/createSolicitudCedulaCalentador',
        'CalentadoresController@createSolicitudNewFormat'
    );

    Route::post(
        '/createSolicitudCedulaProyectos',
        'ProyectosController@createSolicitudNewFormat'
    );

    Route::post(
        '/createSolicitudCedulaYoPuedo',
        'YoPuedoController@createSolicitudNewFormat'
    );

    Route::post(
        '/getSolicitudesCalentadores',
        'CalentadoresController@getSolicitudes'
    );
    Route::post(
        '/updateSolicitudCalentador',
        'CalentadoresController@updateSolicitud'
    );
    Route::post(
        '/updateSolicitudCedulaCalentador',
        'CalentadoresController@updateSolicitudCedula'
    );
    Route::post(
        '/updateSolicitudCedulaProyectos',
        'ProyectosController@updateSolicitudCedula'
    );
    Route::post(
        '/updateSolicitudCedulaYoPuedo',
        'YoPuedoController@updateSolicitudCedula'
    );
    Route::post(
        '/deleteSolicitudCalentador',
        'CalentadoresController@deleteSolicitud'
    );
    Route::post(
        '/deleteSolicitudCedulaCalentador',
        'CalentadoresController@deleteSolicitudCedula'
    );

    Route::post(
        '/deleteSolicitudCedulaProyectos',
        'ProyectosController@deleteSolicitudCedula'
    );
    Route::post(
        '/deleteSolicitudCedulaYoPuedo',
        'YoPuedoController@deleteSolicitudCedula'
    );
    Route::post(
        '/createSolicitudProyectos',
        'ProyectosController@createSolicitud'
    );
    Route::post(
        '/getSolicitudesProyectos',
        'ProyectosController@getSolicitudes'
    );
    Route::post(
        '/updateSolicitudProyectos',
        'ProyectosController@updateSolicitud'
    );
    Route::post(
        '/deleteSolicitudProyectos',
        'ProyectosController@deleteSolicitud'
    );
    Route::post('/getCedulasDiagnostico', 'DiagnosticoController@getCedulas');
    Route::post('/getSolicitudesYoPuedo', 'YoPuedoController@getSolicitudes');
    Route::post('/createSolicitudYoPuedo', 'YoPuedoController@createSolicitud');
    Route::post('/updateEstatusYoPuedo', 'YoPuedoController@updateEstatus');
    Route::post('/updateSolicitudYoPuedo', 'YoPuedoController@updateSolicitud');
    Route::post('/deleteSolicitudYoPuedo', 'YoPuedoController@deleteSolicitud');
    Route::post(
        '/getEstatusGlobalVentanillaVales',
        'CedulasController@getEstatusGlobal'
    );
    Route::post(
        '/uploadFilesSolicitud',
        'CedulasController@uploadFilesSolicitud'
    );
    Route::post(
        '/uploadFilesCalentadores',
        'CalentadoresController@uploadFilesCalentadores'
    );
    Route::post('/uploadFilesYoPuedo', 'YoPuedoController@uploadFilesYoPuedo');
    Route::post(
        '/getFilesByIdSolicitud',
        'CalentadoresController@getFilesByIdSolicitud'
    );

    Route::post(
        '/getSolicitudesDiagnosticos',
        'DiagnosticoV2Controller@getSolicitudes'
    );
    Route::post(
        '/createSolicitudDiagnosticos',
        'DiagnosticoV2Controller@createSolicitud'
    );
    Route::post(
        '/updateSolicitudDiagnosticos',
        'DiagnosticoV2Controller@updateSolicitud'
    );
    Route::post(
        '/deleteSolicitudDiagnosticos',
        'DiagnosticoV2Controller@deleteSolicitud'
    );

    Route::post(
        '/getConciliaciones',
        'CedulasController@getConciliacionArchivos'
    );

    Route::get('/getRemesasPadron', 'PadronesController@getRemesas');
    Route::get('/getOrigenVales', 'PadronesController@getOrigin');
    Route::post('/updateStatusRemesa', 'PadronesController@setStatusRemesa');
    Route::post('/getPadrones', 'PadronesController@getPadronesRemesasUpload');
    Route::post('/uploadPadron', 'PadronesController@uploadExcel');
    Route::get(
        '/getIncidenciasPadron',
        'PadronesController@getReporteIncidencias'
    );
    Route::get(
        '/getPadronRemesa',
        'PadronesController@getReportePadronCorrecto'
    );
    Route::get('/getPadronPlantilla', 'PadronesController@getPlantilla');

    Route::get(
        '/getValesConciliados',
        'CedulasController@getValesConciliacion'
    );

    Route::post('/getArchivosFechas', 'FechasEntregaController@getArchivos');
    Route::get(
        '/getCatalogsFechasEntrega',
        'FechasEntregaController@getCatalogsFechasEntrega'
    );

    Route::group(['prefix' => 'users'], function ($route) {
        Route::post('/getAll', 'UserController@getAll');
        Route::post('/getMenus', 'UserController@getMenus');
        Route::get('getMenusById/{id}', 'UserController@getMenusById');
        Route::post('/create', 'UserController@create');
        Route::post('/update', 'UserController@update');
        Route::get('/getCatalogs','UserController@getCatalogs');
        Route::post('/setMenu', 'UserController@setMenu');
        Route::post('/bloqueoMasivo', 'UserController@bloqueoMasivo');
    });

    Route::group(['prefix' => 'cedula'], function ($route) {
        Route::get(
            '/getCatalogsCedulaCompletos',
            'CedulasController@getCatalogsCedulaCompletos'
        );
        Route::post('/create', 'CedulasController@create');
        Route::post('/uploadFile', 'CedulasController@uploadExcel');
        Route::get('/getByIdV/{id}', 'CedulasController@getByIdV');
        Route::get('/getByIdC/{id}', 'CedulasController@getByIdC');
        Route::get('/getArchivosByIdV/{id}', 'CedulasController@getFilesByIdV');
        Route::get(
            '/getLocalidadesByMunicipio/{id}',
            'CedulasController@getLocalidadesByMunicipio'
        );

        Route::get(
            '/getTipoAsentamiento/{id}',
            'CedulasController@getTipoAsentamientoLocalidad'
        );
        Route::get(
            '/getAgebsManzanasByLocalidad/{id}',
            'CedulasController@getAgebsManzanasByLocalidad'
        );
        Route::get(
            '/getClasificacionArchivos',
            'CedulasController@getClasificacionArchivos'
        );
        Route::post(
            '/getMunicipiosVales',
            'CedulasController@getMunicipiosVales'
        );
        Route::post('/update', 'CedulasController@update');
        Route::post('/delete', 'CedulasController@delete');
        Route::post(
            '/updateArchivosSolicitud',
            'CedulasController@updateArchivosSolicitud'
        );
        Route::post('/enviarIGTO', 'CedulasController@enviarIGTO');

        Route::post('/setVales', 'CedulasController@setVales');
    });

    Route::group(['prefix' => 'calentadores'], function ($route) {
        Route::post(
            '/getMunicipiosVales',
            'CalentadoresController@getMunicipiosVales'
        );
        Route::post('/create', 'CalentadoresController@create');
        Route::get('/getById/{id}', 'CalentadoresController@getById');
        Route::get(
            '/getArchivosByIdC/{id}',
            'CalentadoresController@getFilesById'
        );
        Route::get(
            '/getClasificacionArchivos',
            'CedulasController@getClasificacionArchivos'
        );
        Route::post('/update', 'CalentadoresController@update');
        Route::post('/delete', 'CalentadoresController@delete');
        Route::post(
            '/updateArchivosCedula',
            'CalentadoresController@updateArchivosCedula'
        );
        Route::post('/enviarIGTO', 'CalentadoresController@enviarIGTO');
        Route::post(
            '/getEstatusGlobalVentanillaCalentadores',
            'CalentadoresController@getEstatusGlobal'
        );
    });

    Route::group(['prefix' => 'diagnostico'], function ($route) {
        Route::post(
            '/getEstatusGlobalVentanillaDiagnostico',
            'DiagnosticoController@getEstatusGlobal'
        );
        Route::post('/create', 'DiagnosticoController@create');
        Route::get('/getById/{id}', 'DiagnosticoController@getById');
        Route::get(
            '/getArchivosByIdD/{id}',
            'DiagnosticoController@getFilesById'
        );
        Route::post('/update', 'DiagnosticoController@update');
        Route::post('/delete', 'DiagnosticoController@delete');
        Route::post(
            '/updateArchivosCedula',
            'DiagnosticoController@updateArchivosCedula'
        );
        Route::post('/validarCedula', 'DiagnosticoController@validar');
    });

    Route::group(['prefix' => 'yopuedo'], function ($route) {
        Route::post('/getMunicipios', 'YoPuedoController@getMunicipios');
        Route::post(
            '/getEstatusGlobalVentanillaYoPuedo',
            'YoPuedoController@getEstatusGlobal'
        );
        Route::get(
            '/getCatalogosCedulas',
            'YoPuedoController@getCatalogosCedulas'
        );
        Route::post('/create', 'YoPuedoController@create');
        Route::get('/getById/{id}', 'YoPuedoController@getById');
        Route::get('/getArchivosByIdY/{id}', 'YoPuedoController@getFilesById');
        Route::get(
            '/getClasificacionArchivos',
            'CedulasController@getClasificacionArchivos'
        );
        Route::get(
            '/getArchivosByIdSolicitud/{id}',
            'YoPuedoController@getFilesByIdSolicitud'
        );
        Route::post('/update', 'YoPuedoController@update');
        Route::post('/delete', 'YoPuedoController@delete');
        Route::post(
            '/updateArchivosCedula',
            'YoPuedoController@updateArchivosCedula'
        );
        Route::post('/enviarIGTO', 'YoPuedoController@enviarIGTO');
    });

    Route::group(['prefix' => 'diagnosticos'], function ($route) {
        Route::post('/getMunicipios', 'DiagnosticoV2Controller@getMunicipios');
        Route::post(
            '/getEstatusGlobalVentanillaDiagnosticosV2',
            'DiagnosticoV2Controller@getEstatusGlobal'
        );
        Route::get(
            '/getCatalogosCedulas',
            'DiagnosticoV2Controller@getCatalogosCedulas'
        );
        Route::post('/create', 'DiagnosticoV2Controller@create');
        Route::get('/getById/{id}', 'DiagnosticoV2Controller@getById');
        Route::get(
            '/getArchivosByIdD/{id}',
            'DiagnosticoV2Controller@getFilesById'
        );
        Route::get(
            '/getClasificacionArchivos',
            'CedulasController@getClasificacionArchivos'
        );
        Route::get(
            '/getArchivosByIdSolicitud/{id}',
            'DiagnosticoV2Controller@getFilesByIdSolicitud'
        );
        Route::post('/update', 'DiagnosticoV2Controller@update');
        Route::post('/delete', 'DiagnosticoV2Controller@delete');
        Route::post(
            '/updateArchivosCedula',
            'DiagnosticoV2Controller@updateArchivosCedula'
        );
    });

    Route::group(['prefix' => 'vales'], function ($route) {
        Route::get(
            '/getClasificacionArchivos',
            'Vales2023Controller@getClasificacionArchivos'
        );

        Route::get('/getMunicipiosVales', 'Vales2023Controller@getMunicipios');
        Route::get('/getFilesById/{id}', 'Vales2023Controller@getFilesById');
        Route::post('/getQ3450', 'Vales2023Controller@getSolicitudesAuditoria');
        Route::post('/getFilesValesAd', 'Vales2023Controller@getFilesValesAd');
        Route::post('/getVales2023', 'Vales2023Controller@getSolicitudes2023');
        Route::post(
            '/getListadoVales2023',
            'Vales2023Controller@getListadoSolicitudes2023'
        );
        Route::post('/valesIncidencia', 'Vales2023Controller@valesIncidencia');
        Route::post(
            '/updateArchivosSolicitud',
            'Vales2023Controller@updateArchivosSolicitud'
        );

        Route::post(
            '/validateCveInterventor',
            'Vales2023Controller@validateCveInterventor'
        );

        Route::post('/validateFolio', 'Vales2023Controller@validateFolio');

        Route::post('/recepcionVales', 'Vales2023Controller@recepcionVales');

        Route::post('/getGroupList', 'Vales2023Controller@getGroupList');

        Route::post(
            '/updateValeSolicitud',
            'Vales2023Controller@updateValeSolicitud'
        );

        Route::get(
            '/getEtiquetasVales',
            'ReportesController@getEtiquetasVales'
        );

        Route::get(
            '/getSolicitudesValeUnico',
            'ReportesController@getSolicitudesValeUnico'
        );

        Route::post(
            '/getValesExpedientes',
            'Vales2023Controller@getValesExpedientes'
        );

        Route::post(
            '/getTotalSolicitudes',
            'Vales2023Controller@getTotalSolicitudes'
        );

        Route::post(
            '/getTotalExpedientes',
            'Vales2023Controller@getTotalExpedientes'
        );

        Route::post(
            '/getTotalPendientes',
            'Vales2023Controller@getTotalPendientes'
        );

        Route::post(
            '/getTotalValidados',
            'Vales2023Controller@getTotalValidados'
        );

        Route::post(
            '/getSolicitudesExpedientes',
            'Vales2023Controller@getSolicitudesExpedientes'
        );

        Route::post(
            '/validateSolicitud',
            'Vales2023Controller@validateSolicitud'
        );

        Route::get('/getSemanasTrabajo', 'Vales2023Controller@getSemanas');
        Route::get('/getRemesas', 'Vales2023Controller@getRemesas');
        Route::post(
            '/getRemesaEjercicio',
            'Vales2023Controller@getRemesaEjercicio'
        );
        Route::post('/getDaysForWeek', 'Vales2023Controller@getDays');
        Route::post('/getPineo', 'Vales2023Controller@getPineo');
        Route::post('/getPineoUser', 'Vales2023Controller@getPineoUser');
        Route::post(
            '/getPineoRegionMunicipio',
            'Vales2023Controller@getPineoRegionMunicipio'
        );
        Route::post(
            '/getAvancesGrupos',
            'Vales2023Controller@getAvancesGrupos'
        );
        Route::post('getAvancesPadron', 'Vales2023Controller@getAvancesPadron');
        Route::get(
            'getReporteAvances',
            'Vales2023Controller@getReporteAvances'
        );
        Route::get(
            'getReporteBeneficiarios',
            'Vales2023Controller@getBeneficiariosAvances'
        );
        Route::post('/getRemesasAll', 'Vales2023Controller@getRemesasAll');

        Route::get(
            '/getAvancesGruposReporte',
            'Vales2023Controller@getAvancesGruposReporte'
        );

        Route::post(
            '/getMunicipiosValesRemesas',
            'Vales2023Controller@getMunicipiosRemesas'
        );

        Route::post(
            '/getCveInterventor',
            'Vales2023Controller@getCveInterventor'
        );

        Route::post('/getLocalidad', 'Vales2023Controller@getLocalidad');
        Route::post(
            '/getResponsablesEntrega',
            'Vales2023Controller@getResponsables'
        );
        Route::post(
            '/getGruposRevision',
            'Vales2023Controller@getGruposRevision'
        );
    });

    Route::group(['prefix' => 'trabajemos'], function ($route) {
        //Informativos
        Route::post(
            '/getSolicitudesTrabajemos',
            'TrabajemosJuntosController@getSolicitudes'
        );
        Route::post(
            '/getEstatusGlobalTrabajemos',
            'TrabajemosJuntosController@getEstatusGlobal'
        );
        Route::get('/getCatalogs', 'TrabajemosJuntosController@getCatalogs');
        Route::get('/getById/{id}', 'TrabajemosJuntosController@getByIdV');
        Route::get(
            '/getLocalidadesByMunicipio/{id}',
            'TrabajemosJuntosController@getLocalidadesByMunicipio'
        );
        Route::post(
            '/getMunicipios',
            'TrabajemosJuntosController@getMunicipios'
        );
        Route::post(
            '/getArticuladores',
            'TrabajemosJuntosController@getArticuladores'
        );
        Route::post('/getGrupos', 'TrabajemosJuntosController@getGrupos');

        Route::post(
            '/getGruposDisponibles',
            'TrabajemosJuntosController@getGruposDisponibles'
        );

        //Creación, actualización y eliminado de solicitudes
        Route::post(
            '/createSolicitud',
            'TrabajemosJuntosController@createSolicitud'
        );
        Route::post(
            '/updateSolicitud',
            'TrabajemosJuntosController@updateSolicitud'
        );
        Route::post(
            '/deleteSolicitud',
            'TrabajemosJuntosController@deleteSolicitud'
        );

        //Archivos
        Route::get(
            '/getArchivosById/{id}',
            'TrabajemosJuntosController@getFilesById'
        );
        Route::get(
            '/getClasificacionArchivos',
            'TrabajemosJuntosController@getClasificacionArchivos'
        );
        Route::post(
            '/updateArchivosSolicitud',
            'TrabajemosJuntosController@updateArchivosSolicitud'
        );

        //Reportes
        Route::get(
            '/getReporteSolicitudTrabajemos',
            'TrabajemosJuntosController@getReporteSolicitudTrabajemos'
        );
    });

    // ! Trabajemos Grupos
    Route::group(['prefix' => 'trabajemosGrupos'], function ($route) {
        Route::post(
            '/getEstatusGlobalTrabajemosGrupos',
            'GruposTrabajemosJuntosController@getEstatusGlobal'
        );

        Route::post(
            '/getSolicitudesTrabajemos',
            'GruposTrabajemosJuntosController@getSolicitudes'
        );

        Route::post(
            '/getGruposTrabajemos',
            'GruposTrabajemosJuntosController@getGrupos'
        );

        Route::get(
            '/getCatalogs',
            'GruposTrabajemosJuntosController@getCatalogs'
        );
        Route::get(
            '/getById/{id}',
            'GruposTrabajemosJuntosController@getByIdV'
        );
        //Creación, actualización y eliminado de solicitudes
        Route::post(
            '/createGrupo',
            'GruposTrabajemosJuntosController@createGrupo'
        );
        Route::post(
            '/updateGrupo',
            'GruposTrabajemosJuntosController@updateGrupo'
        );
        Route::post(
            '/deleteGrupo',
            'GruposTrabajemosJuntosController@deleteGrupo'
        );

        //Archivos
        Route::get(
            '/getArchivosById/{id}',
            'GruposTrabajemosJuntosController@getFilesById'
        );
        Route::get(
            '/getClasificacionArchivos',
            'GruposTrabajemosJuntosController@getClasificacionArchivos'
        );
        Route::post(
            '/updateArchivosSolicitud',
            'GruposTrabajemosJuntosController@updateArchivosSolicitud'
        );
        //Reportes
        Route::get(
            '/getReporteSolicitudTrabajemosGrupos',
            'GruposTrabajemosJuntosController@getReporteSolicitudTrabajemosGrupos'
        );

        Route::post(
            '/getSolicitudesDisponibles',
            'GruposTrabajemosJuntosController@getSolicitudesDisponibles'
        );
    });
    // ! Nuevas Rutas Solicitudes
    Route::group(['prefix' => 'solicitudes'], function ($route) {
        Route::get(
            '/getArchivosCatalogos/{id}',
            'SolicitudesController@getCatalogsFiles'
        );
        Route::post('/getArchivosSolicitud', 'SolicitudesController@getFiles');

        Route::post(
            '/cambiarEstatusArchivo',
            'SolicitudesController@changeStatusFiles'
        );

        Route::get('/getCatalogos/{id}', 'SolicitudesController@getCatalogos');
    });

    // ! Calentadores Solares
    Route::group(['prefix' => 'calentadoresSolares'], function ($route) {
        Route::get('/getPdf', 'CalentadoresSolares@getPdf');
        Route::get('/getSolicitud/{id}', 'CalentadoresSolares@getSolicitud');
        Route::post('/getSolicitudes', 'CalentadoresSolares@getSolicitudes');
        Route::get(
            '/getSolicitudesReporte',
            'CalentadoresSolares@getSolicitudesReporte'
        );
        Route::post('/getMunicipios', 'CalentadoresSolares@getMunicipios');
        Route::get(
            '/getClasificacionArchivos',
            'CalentadoresSolares@getFilesClasification'
        );
        Route::post('/setEstatusArchivo', 'CalentadoresSolares@setFileStatus');
        Route::post(
            '/setFilesComments',
            'CalentadoresSolares@setFilesComments'
        );
        Route::post('/changeFiles', 'CalentadoresSolares@changeFiles');
        Route::post('/saveNewFiles', 'CalentadoresSolares@saveNewFiles');
        Route::post('/createSolicitud', 'CalentadoresSolares@create');
        Route::post('/updateSolicitud', 'CalentadoresSolares@update');
        Route::post('/deleteSolicitud', 'CalentadoresSolares@delete');
        Route::post(
            '/getTotalSolicitudes',
            'CalentadoresSolares@getCapturadas'
        );
        Route::post('/getTotalPendientes', 'CalentadoresSolares@getPendientes');
        Route::post('/getTotalObservadas', 'CalentadoresSolares@getObservadas');
        Route::post('/getTotalValidadas', 'CalentadoresSolares@getValidadas');
        Route::get('/cargaMasivo', 'CalentadoresSolares@cargaMasiva');
    });

    Route::group(['prefix' => 'q1417/v1'], function ($route) {
        Route::post('validate', 'CalentadoresSolares@validateCURP');
        Route::post('register', 'CalentadoresSolares@register');
        Route::post('getList', 'CalentadoresSolares@getList');
        //Route::post('getFiles', 'CalentadoresSolares@getFilesByFolioApi');
        Route::post('getFiles', 'CalentadoresSolares@getFilesByFolioImpulso');
        //Route::post('getPdf', 'CalentadoresSolares@getPdfByFolioApi');
        Route::post('getPdf', 'CalentadoresSolares@getPdfByFolioImpulso');
        Route::post('expedientes', 'CalentadoresSolares@getExpedientesCS');
    });

    //! Encuestas
    Route::group(['prefix' => 'encuestas'], function ($route) {
        Route::post('getEncuestas', 'EncuestasController@getEncuestas');
        Route::post('getEncuestasTCS', 'EncuestasController@getEncuestasTCS');
        Route::post('getMunicipios', 'EncuestasController@getMunicipios');
        Route::get('/getCatalogs', 'EncuestasController@getCatalogs');
        Route::post('getBeneficiarios', 'EncuestasController@getBeneficiarios');
        Route::post('create', 'EncuestasController@create');
        Route::post('delete', 'EncuestasController@delete');
        Route::post('getResponses', 'EncuestasController@getResponses');
        Route::post(
            '/getReporteEncuestas',
            'EncuestasController@getReporteEncuestas'
        );
        Route::get(
            'getReporteEncuestas',
            'EncuestasController@getReporteEncuestas'
        );
        // //Route::post('getFiles', 'CalentadoresSolares@getFilesByFolioApi');
        // Route::post('getFiles', 'CalentadoresSolares@getFilesByFolioImpulso');
        // //Route::post('getPdf', 'CalentadoresSolares@getPdfByFolioApi');
        // Route::post('getPdf', 'CalentadoresSolares@getPdfByFolioImpulso');
    });

    //! Proyectos Productivos
    Route::group(['prefix' => 'proyectos'], function ($route) {
        // * 2022
        Route::post(
            '/getEstatusGlobalVentanillaProyectos',
            'ProyectosController@getEstatusGlobal'
        );
        Route::post('/create', 'ProyectosController@create');
        Route::get('/getById/{id}', 'ProyectosController@getById');
        Route::get(
            '/getArchivosByIdP/{id}',
            'ProyectosController@getFilesById'
        );
        Route::get(
            '/getClasificacionArchivos',
            'CedulasController@getClasificacionArchivos'
        );
        Route::post('/update', 'ProyectosController@update');
        Route::post('/delete', 'ProyectosController@delete');
        Route::post(
            '/updateArchivosCedula',
            'ProyectosController@updateArchivosCedula'
        );
        Route::post('/enviarIGTO', 'ProyectosController@enviarIGTO');

        // * 2023
        Route::post('/getMunicipios', 'ProyectosPController@getMunicipios');
        Route::post('/getTotalSolicitudes', 'ProyectosPController@getTotal');
        Route::post(
            '/getTotalPendientes',
            'ProyectosPController@getPendientes'
        );
        Route::post('/getTotalValidadas', 'ProyectosPController@getValidas');
        Route::get('/getSolicitud/{id}', 'ProyectosPController@getSolicitud');
        Route::post('/getSolicitudes', 'ProyectosPController@getSolicitudes');
        Route::get(
            '/getSolicitudesReporte',
            'ProyectosPController@getSolicitudesReporte'
        );
        Route::get(
            '/getClasificacionArchivos',
            'ProyectosPController@getFilesClasification'
        );
        Route::get('/getPdf', 'ProyectosPController@getPdf');
        Route::post('/createSolicitud', 'ProyectosPController@create');
        Route::post('/updateSolicitud', 'ProyectosPController@update');
        Route::post('/deleteSolicitud', 'ProyectosPController@delete');
        Route::post(
            '/createCotizacion',
            'ProyectosPController@createCotizacion'
        );
        Route::post('/saveNewFiles', 'ProyectosPController@saveNewFiles');
    });

    Route::post('/deleteRelation', 'TrabajemosJuntosController@deleteRelation');

    Route::post('/addRelation', 'TrabajemosJuntosController@addRelation');

    Route::post(
        '/descargarArchivosMasivo',
        'YoPuedoController@getArchivosBeneficiaroYoPuedo'
    );

    Route::post('/setEntrega', 'ReportesController@setEntrega');
    Route::post('/getAcuse', 'ReportesController@getAcuseValesIndividual');
    Route::get('/getAcuseUnico', 'ReportesController@getAcuseUnico');

    Route::post('/cargaMasivo', 'Vales2023Controller@cargaMasiva');
    Route::post('/checkFilesCalentadores', 'CalentadoresSolares@checkFiles');
    Route::get('/getRegiones', 'Vales2023Controller@getRegiones');

    Route::get('/getRegionesMenu', 'Vales2023Controller@getRegionesMenu');

    Route::get('/getTokenImpulso', 'FilesTarjetaController@getTokenImpulso');
    Route::post('/sendFilesImpulso', 'FilesTarjetaController@sendFiles');
    Route::post('/getUsersByRegion', 'AsistenciaController@getUsersByRegion');
    Route::post('/getAssistants', 'AsistenciaController@getAssistants');
    Route::post('/checkAssistant', 'AsistenciaController@checkAssistant');
    Route::post('/deleteAssistant', 'AsistenciaController@deleteAssistant');
    Route::get('/getListAssistants', 'AsistenciaController@getListAssistants');
    Route::get('/getListPdf', 'AsistenciaController@getListPdf');
});
