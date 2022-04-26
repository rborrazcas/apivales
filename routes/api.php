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


// estas rutas se pueden acceder sin proveer de un token válido.
Route::post('/login', 'AuthController@login');

//APIS PULSERAS GTO 3 OCTUBRE MOVIL
Route::post('/getListadoInvitadosMovil','ControllersPulseras\InvitadoController@getListadoInvitadosMovil');
Route::post('/setInvitadoMovil','ControllersPulseras\InvitadoController@setInvitadoMovil');
Route::post('/updateInvitadoMovil','ControllersPulseras\InvitadoController@updateInvitadoMovil');
Route::post('/getListadoCodigoBarraMovil','ControllersPulseras\CodigoBarraController@getListadoCodigoBarraMovil');
Route::post('/getListadoAccesosMovil','ControllersPulseras\AccesoController@getListadoAccesosMovil');
Route::post('/setAccesoMovil','ControllersPulseras\AccesoController@setAccesoMovil');
Route::post('/setAsignarCodigoBarrasInvitadoMovil','ControllersPulseras\InvitadoController@setAsignarCodigoBarrasInvitadoMovil');
//APIS MOVIL
Route::post('/setCodigoBarraMovil','ControllersPulseras\CodigoBarraController@setCodigoBarra');
Route::post('/getListadoResponsablesMovil','ControllersPulseras\InvitadoController@getListadoResponsables');//PENDIENTE
Route::post('/getResponsablesMovil','ControllersPulseras\InvitadoController@getResponsables');
//Route::get('/getReporteInvitadosMovil','ControllersPulseras\ReporteController@getReporteInvitados');

// estas rutas requiren de un token válido para poder accederse.
Route::group(['middleware' => 'jwt.auth'], function () {
    Route::post('/register', 'AuthController@register');
    Route::post('/logout', 'AuthController@logout');
    Route::post('/me', 'AuthController@getAuthenticatedUser');
    Route::post('/updateUser', 'AuthController@updateUser');
    Route::post('/getUsersApp', 'AuthController@getUsersApp');
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
    Route::post('/getEstadoActual', 'CatEstadoActualController@getEstadoActual');
    Route::post('/getEvolucion', 'CatEvolucionController@getEvolucion');
    Route::post('/getTipoPaciente', 'CatPacienteController@getTipoPaciente');
    Route::post('/getPaises', 'CatPaisesController@getPaises');
    Route::post('/getParentesco', 'CatParentescoController@getParentesco');
    Route::post('/getServicios', 'CatServiciosController@getServicios');
    Route::post('/getTipoMuestra', 'CatTipoMuestraController@getTipoMuestra');
    Route::post('/getCasosStatus', 'CasosStatusController@getCasosStatus');
    Route::post('/getCasosSeguimiento', 'CasosSeguimientoController@getCasosSeguimiento');
    Route::post('/getFiltro', 'FiltroController@getFiltro');
    Route::post('/setFiltro', 'FiltroController@setFiltro');
    Route::post('/getPersonas', 'PersonaController@getPersonas');
    Route::post('/setPersonas', 'PersonaController@setPersonas');
    //COMMIT
    Route::post('/getTriage', 'TriageController@getTriage'); //pendiente -> Validar Unique

    //API ET -->
    Route::post('/getLocalidadET', 'ETCatLocalidadController@getLocalidadET');
    Route::post('/getMunicipiosET', 'ETCatMunicipioController@getMunicipiosET');
    Route::post('/getMunicipiosETVales', 'ETCatMunicipioController@getMunicipiosETVales');
    Route::post('/getGrupoET', 'ETGrupoController@getGrupoET');
    Route::post('/setGrupoET', 'ETGrupoController@setGrupoET');
    Route::post('/updateGrupoET', 'ETGrupoController@updateGrupoET');
    Route::post('/getTarjetasET', 'ETTarjetasController@getTarjetasET');
    Route::post('/getTarjetaAsignadaET', 'ETTarjetasAsignadasController@getTarjetaAsignadaET');
    Route::post('/setTarjetaAsignadaET', 'ETTarjetasAsignadasController@setTarjetaAsignadaET');
    Route::post('/deleteTarjetaAsignadaET', 'ETTarjetasAsignadasController@deleteTarjetaAsignadaET');
    Route::post('/getTarjetasAsignadasGrupoET', 'ETGrupoUsersController@getTarjetasAsignadasGrupoET');
    Route::post('/setGrupoUserET', 'ETGrupoUsersController@setGrupoUserET');
    Route::post('/getAprobados', 'ETAprobadosComiteController@getAprobados');
    Route::post('/getRechazados', 'ETAprobadosComiteController@getRechazados');

    Route::post('/getExport', 'ETDocumentoIndividualController@getDocumento');

    //Para reportes GUGA
    
    Route::get('/getTarjeta','ReportesController@getTarjeta');
    Route::get('/getReporteGrupos','ReportesController@getReporteGrupos');
    Route::get('/getReporteComercios','ReportesController@getReporteComercios');
    Route::get('/downloadReporteComercios','ReportesController@downloadReporteComercios');
    Route::get('/getReportesolicitudVales','ReportesController@getReportesolicitudVales');
    Route::get('/getReportesolicitudValesDesglosado','ReportesController@getReportesolicitudValesDesglosado');
    Route::get('/getRepValesExpedientes','ReportesController@getRepValesExpedientes');
    Route::get('/getPadronPotencial','ReportesController@getPadronPotencial');
    Route::get('/getReportesoVales','ReportesController@getReportesoVales');
    Route::get('/getReportesoValesRegionales','ReportesController@getReportesoValesRegionales');
    Route::get('/getReporteFoliosValidar','ReportesController@getReporteFoliosValidar');
    
    
    Route::get('/getReporteNominaVales','ReportesController@getReporteNominaVales');
    Route::get('/getSumaVoluntadesWord','ReportesController@getSumaVoluntadesWord');
    Route::post('/getSumaVoluntadesWord','ReportesPostController@getSumaVoluntadesWord');
    
    Route::get('/getTarjetas','ReportesController@getTarjetasByGrupo');    

    Route::post('/getReporteGruposB','ReportesPostController@getReporteGrupos');
    Route::post('/getTarjetaB','ReportesPostController@getTarjeta');
    Route::post('/getTarjetasB','ReportesPostController@getTarjetas');  



    //Para sedeshu comercios.
    Route::post('/getGiros', 'VGirosController@getGiros');
    Route::post('/setGiros', 'VGirosController@setGiros');
    Route::post('/updateGiros', 'VGirosController@updateGiros'); //Pendiente borrar los giros anteriores.
    Route::post('/getStatus', 'VStatusController@getStatus');
    Route::post('/getNegocios', 'VNegociosController@getNegocios'); 
    Route::post('/getNegociosApp', 'VNegociosController@getNegociosApp'); 
    Route::post('/getNegociosAppMaps', 'VNegociosController@getNegociosAppMaps'); 
    Route::post('/getNegociosPublico', 'VNegociosController@getNegociosPublico'); 
    Route::post('/getNegociosResumen', 'VNegociosController@getNegociosResumen'); 
    Route::post('/setNegocios', 'VNegociosController@setNegocios');
    Route::post('/updateNegocios', 'VNegociosController@updateNegocios');
    Route::post('/updateBajaNegocios', 'VNegociosController@updateBajaNegocios');
    Route::post('/updateRefrendoNegocios', 'VNegociosController@updateRefrendoNegocios');
    Route::post('/getNegociosPagadores', 'VNegociosPagadoresController@getNegociosPagadores');
    Route::post('/setNegociosPagadores', 'VNegociosPagadoresController@setNegociosPagadores');
    Route::post('/updateNegociosPagadores', 'VNegociosPagadoresController@updateNegociosPagadores');
    Route::post('/getNegociosTipo', 'VNegociosTipoController@getNegociosTipo');

    Route::post('/getVales', 'VValesController@getVales');
    Route::post('/getValesResumen', 'VValesController@getValesResumen');
    
    Route::post('/getValesAvances2021', 'VValesController@getValesAvances2021');
    Route::post('/getHistoryVales', 'VValesController@getHistoryVales');
    Route::post('/getValesInHistory', 'VValesController@getValesInHistory');
    Route::post('/getValesV2', 'VValesController@getValesV2');
    Route::post('/getSolicitudesPorCURP', 'VValesController@getSolicitudesPorCURP');
    
    Route::post('/getValesV2Fecha', 'VValesController@getValesV2Fecha');
    Route::post('/getRemesas', 'VValesController@getRemesas');
    Route::post('/getValesFecha', 'VValesController@getValesFecha');
    
    
    Route::post('/getValesNotIn', 'VValesController@getValesNotIn');
    Route::post('/getValesIn', 'VValesController@getValesIn');
    Route::post('/getValesRegion', 'VValesController@getValesRegion');
    Route::post('/setVales', 'VValesController@setVales');
    Route::post('/deleteVales', 'VValesController@deleteVales');
    
    Route::post('/updateVales', 'VValesController@updateVales');
    Route::post('/updateRecepcionDocumento', 'VValesController@updateRecepcionDocumento');
    Route::post('/updateEntregaVales', 'VValesController@updateEntregaVales');
    Route::post('/getValesRecepcionDocumentacion', 'VValesController@getValesRecepcionDocumentacion');

    Route::post('/getValesDocumentacion', 'ValesDocumentacionController@getValesDocumentacion');
    Route::post('/setValesDocumentacion', 'ValesDocumentacionController@setValesDocumentacion');
    Route::post('/deleteValesDocumentacion', 'ValesDocumentacionController@deleteValesDocumentacion');
    Route::post('/getValesDocumentacionNotIn', 'ValesDocumentacionController@getValesDocumentacionNotIn');
    Route::post('/getValesDocumentacionIn', 'ValesDocumentacionController@getValesDocumentacionIn');
    Route::post('/getArticularDocumentacion', 'ValesDocumentacionController@getArticularDocumentacion');
    Route::post('/getArticularEntregado', 'ValesDocumentacionController@getArticularEntregado');
    

    Route::post('/updatePassword', 'AuthController@updatePassword');
    Route::post('/updateUserPassword', 'AuthController@updateUserPassword');
    
    Route::get('/actualizarTabla', 'ValesSolicitudesController@actualizarTablaValesUsados');
    Route::get('/actualizarFechas', 'ValesSolicitudesController@actualizarFechas');
    Route::get('/reciboEntregaRecepcion', 'ReportesController@reciboEntregaRecepcion');
    
    Route::post('/AgregarMultiples', 'AuthController@AgregarMultiples'); //Temporal
    Route::get('/getUsersArticuladores', 'AuthController@getUsersArticuladores');
    Route::post('/getUsersArticuladores', 'UserController@getUsersArticuladores');
    Route::post('/getUsersArticuladoresV2', 'UserController@getUsersArticuladoresV2');
    Route::post('/getUsersRecepcionoV2', 'UserController@getUsersRecepcionoV2');
    

    Route::post('/getEstatusGlobal', 'ValesStatusController@getEstatusGlobal');
    Route::post('/getEstatusRegion', 'ValesStatusController@getEstatusRegion');
    Route::post('/getEstatusMunicipio', 'ValesStatusController@getEstatusMunicipio');

    Route::post('/getGrupos', 'ValesGruposController@getGrupos');//Sin parametros
    Route::post('/setGrupos', 'ValesGruposController@setGrupos');//Sin parametros
    Route::post('/getGruposArticuladores', 'UserController@getGruposArticuladores');//Sin parametros
    Route::post('/getArticularSolicitudes', 'UserController@getArticularSolicitudes');//Sin parametros

    Route::post('/getValesSolicitudes', 'ValesSolicitudesController@getValesSolicitudes');
    Route::post('/setValesSolicitudes', 'ValesSolicitudesController@setValesSolicitudes');
    
    Route::get('/getValePPT','ReportesController@getVales');

    Route::post('/getSerieVale','ValesSeriesController@getSerieVale');

    Route::get('/copiarTablaVales','ValidacionesController@copiarTablaVales');
    Route::get('/setValidaciones','ValidacionesController@setValidaciones');



    Route::post('/getResumenVales','ResumenController@getResumenVales');

    Route::get('/getResumenComite','ValidacionesController@cruceVales');
    Route::get('/ExportResumenComite','ValidacionesController@getReporteCruceVales');

    Route::post('/getMiResumenVales','ReportesController@getMiResumenVales');
    Route::post('/getMisRemesasTotales','ReportesController@getMisRemesasTotales');
    Route::post('/getReporteNominaValesDetalle','ReportesController@getReporteNominaValesDetalle');
    Route::post('/getMisRemesas','ReportesController@getMisRemesas');
    Route::post('/getAvanceRemesas','ReportesController@getAvanceRemesas');
    Route::post('/getSearchFolio','VValesController@getSearchFolio');
    Route::post('/getRemesasGruposAvance','ReportesController@getRemesasGruposAvance');
    Route::post('/getCatGrupos','ReportesController@getCatGrupos');

    //APIS PULSERAS GTO 30 SEPTIEMBRE
    Route::post('/getListadoInvitados','ControllersPulseras\InvitadoController@getListadoInvitados');
    Route::post('/setInvitado','ControllersPulseras\InvitadoController@setInvitado');
    Route::post('/updateInvitado','ControllersPulseras\InvitadoController@updateInvitado');
    Route::post('/getListadoCodigoBarra','ControllersPulseras\CodigoBarraController@getListadoCodigoBarra');
    Route::post('/getListadoAccesos','ControllersPulseras\AccesoController@getListadoAccesos');
    Route::post('/setAcceso','ControllersPulseras\AccesoController@setAcceso');
    Route::post('/setAsignarCodigoBarrasInvitado','ControllersPulseras\InvitadoController@setAsignarCodigoBarrasInvitado');
    //APIS PULSERAS GTO 1 OCTUBRE
    Route::post('/setCodigoBarra','ControllersPulseras\CodigoBarraController@setCodigoBarra');
    Route::post('/getListadoResponsables','ControllersPulseras\InvitadoController@getListadoResponsables');//PENDIENTE
    Route::post('/getResponsables','ControllersPulseras\InvitadoController@getResponsables');
    Route::get('/getReporteInvitados','ControllersPulseras\ReporteController@getReporteInvitados');
    Route::get('/getReporteInvitadosPorResponsable','ControllersPulseras\ReporteController@getReporteInvitadosPorResponsable');

    Route::post('/getDisponibilidadCodigoBarra','ControllersPulseras\CodigoBarraController@getDisponibilidadCodigoBarra');
    
     Route::get('/getCodigoBarra','ControllersPulseras\ReporteController@getCodigoBarrasInvitados');
     Route::get('/getCodigoBarraAll','ControllersPulseras\ReporteController@getCodigoBarras');


     //CEDULAS
     Route::post('/createSolicitudCedula','CedulasController@createSolicitud');
     Route::post('/getSolicitudesCedula','CedulasController@getSolicitudes');
     Route::post('/updateSolicitudCedula','CedulasController@updateSolicitud');
     Route::post('/deleteSolicitudCedula','CedulasController@deleteSolicitud');
     Route::get('/getCatalogsCedula','CedulasController@getCatalogsCedula');

     Route::group(['prefix' =>'cedula'], function($route){
        Route::get('/getCatalogsCedulaCompletos','CedulasController@getCatalogsCedulaCompletos');
        Route::post('/create','CedulasController@create');
        Route::get('/getById/{id}','CedulasController@getById');
        Route::get('/getArchivosById/{id}','CedulasController@getFilesById');
        Route::get('/getClasificacionArchivos','CedulasController@getClasificacionArchivos');
        Route::post('/update','CedulasController@update');
        Route::post('/delete','CedulasController@delete');
        Route::post('/updateArchivosCedula','CedulasController@updateArchivosCedula');
     });

     
    

});
 