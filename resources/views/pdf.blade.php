<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css"
        integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
    <title>Acuse Vales Grandeza</title>
    <style>
        body {
            font-family: Helvetica;
        }

        @page {
            margin: 12px 15px;
        }

        header {
            margin: auto auto;
            left: 0px;
            right: 0px;
            height: 40px;
            background-color: #093EAF;
            text-align: initial;
            text-underline-position: auto;
            color: rgb(255, 255, 255);
            border-radius: 2px;
            border-top-left-radius: 20px;
            border-bottom-left-radius: 16px;
        }

        header p {
            width: 60%;
            display: inline;
            vertical-align: middle;
            text-align: center;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 23px;
            font-weight: bold;
        }

        footer {
            position: fixed;
            left: 0px;
            bottom: -50px;
            right: 0px;
            height: 40px;
            border-bottom: 2px solid #ddd;
            font-weight: 2px;
        }

        footer .page:after {
            content: counter(page);
        }

        footer table {
            width: 100%;
        }

        footer p {
            text-align: right;
        }

        .izq {
            text-align: center;
            font-size: 8px;
            font-family: Helvetica, sans-serif;
        }


        .subTitle {
            text-align: center;
            padding-top: 10px;
            padding-bottom: 5px;
        }

        .table {
            table-layout: fixed;
            border-color: black !important;
        }

        td {
            width: 10%;
        }

        .encabezado {
            color: #0267cd;
            font-weight: bold;
            font-size: 10px;

        }

        .informacion {
            color: black;
            text-align: center;
            font-size: 10px;
        }

        .text-monospace {
            margin: 0px, 0px, 0px, 0px;
            padding-left: 0px;
            font-weight: bold;
            font-size: 20px;
        }
    </style>
</head>

<body>
    @foreach ($vales as $index => $vale)
        <header>
            <img style="height:98%; width:14%; display:inline; padding-top:4px; padding-right:25px;"
                src="../public/img/logoSDSH.png">
            <p style="padding-left:50px">Recibo de Entrega - Recepción</p>
            <img style="height:90%; display:inline; padding-top:4px; padding-left:100px;"
                src="../public/img/logo_estrategia.png">
        </header>
        <div class="container-fluid">
            <div class="subTitle" style="padding-top: 37px;">
                <h7>Programa Vale Grandeza - Compra Local 2022</h7>
            </div>
            <div class="folio">
                <table class="table table-bordered table-sm" style="font-size: 5px;">
                    <thead style="background-color: #1235A2; color:white;">
                    </thead>
                    <tbody>
                        {{-- <tr style="background-color:#0267cd; color:white; border-radius:16px; font-size:9px;"> --}}
                        <tr
                            style="color:#0267cd; border-radius:16px; font-size:9px;border-color:#0267cd; text-align:center;">
                            <td colspan="12"><b>DATOS GENERALES</b></td>
                        </tr>
                        <tr>
                            <td colspan="2" class="encabezado">Folio Solicitud:</td>
                            <td colspan="3" class="encabezado">Folio Impulso:</td>
                            <td colspan="4" class="encabezado">Acuerdo:</td>
                            <td colspan="1" class="encabezado">Región:</td>
                            <td colspan="2" class="encabezado">Responsable:</td>
                        </tr>
                        <tr>
                            <td colspan="2" class="informacion">{{ $vale['id'] }}</td>
                            <td colspan="3"class="informacion">{{ $vale['folio'] }}</td>
                            <td colspan="4"class="informacion">{{ $vale['acuerdo'] }}</td>
                            <td colspan="1"class="informacion">{{ $vale['region'] }}</td>
                            <td colspan="2" class="informacion">{{ $vale['enlace'] }}</td>
                        </tr>
                        {{-- <tr>
                        <td colspan="2" class="encabezado">Responsable:</td>
                        <td colspan="10" class="informacion">{{ $enlace }}</td>
                    </tr> --}}
                    </tbody>
                </table>
            </div>
            <h5 style="text-align: center;"></h5>
            <div class="folio">
                <table class="table table-bordered table-sm" style="font-size: 5px;">
                    <thead style="background-color: #1235A2; color:white;">
                    </thead>
                    <tbody>
                        <tr
                            style="color:#0267cd; border-radius:16px; font-size:9px;border-color:#0267cd; text-align:center;">
                            <td colspan="12"><b>DATOS DEL BENEFICIARIO</b></td>
                        </tr>
                        {{-- <tr style="background-color:#0267cd; color:white; border-radius:16px; font-size:9px;">
                            <td colspan="12"><b>DATOS DEL BENEFICIARIO</b></td>
                        </tr> --}}
                        <tr>
                            <td colspan="3"class="encabezado">Nombre del Beneficiario:</td>
                            <td colspan="3"class="encabezado">CURP:</td>
                            <td colspan="6"class="encabezado">Domicilio del Beneficiario:</td>
                        </tr>
                        <tr>
                            <td colspan="3" class="informacion">{{ $vale['nombre'] }}</td>
                            <td colspan="3" class="informacion">{{ $vale['curp'] }}</td>
                            <td colspan="6" class="informacion">{{ $vale['domicilio'] }}</td>
                        </tr>
                        <tr>
                            <td colspan="3"class="encabezado">Municipio:</td>
                            <td colspan="3"class="encabezado">Localidad:</td>
                            <td colspan="3"class="encabezado">Colonia:</td>
                            <td colspan="3"class="encabezado">CP:</td>
                        </tr>
                        <tr>
                            <td colspan="3" class="informacion">{{ $vale['municipio'] }}</td>
                            <td colspan="3" class="informacion">{{ $vale['localidad'] }}</td>
                            <td colspan="3" class="informacion">{{ $vale['colonia'] }}</td>
                            <td colspan="3" class="informacion">{{ $vale['cp'] }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <h5 style="text-align: center;"></h5>
            <div class="folio">
                <table class="table table-bordered table-sm" style="font-size: 9px;">
                    <thead style="background-color: #1235A2; color:white;">
                    </thead>
                    <tbody>
                        {{-- <tr style="background-color:#0267cd; color:white; border-radius:16px; font-size:9px;">
                            <td colspan="12"><b>RECEPCIÓN - ENTREGA DEL APOYO</b></td>
                        </tr> --}}
                        <tr
                            style="color:#0267cd; border-radius:16px; font-size:9px;border-color:#0267cd; text-align:center;">
                            <td colspan="12"><b>RECEPCIÓN - ENTREGA DEL APOYO</b></td>
                        </tr>
                        <tr>
                            <td class="encabezado">Unidad:</td>
                            <td class="encabezado">Cantidad:</td>
                            <td class="encabezado">Folio Inicial:</td>
                            <td class="encabezado">Folio Final:</td>
                            <td class="encabezado">Entregado:</td>
                            <td class="encabezado" colspan="7">Fecha de Entrega:</td>
                        </tr>
                        <tr>
                            <td class="informacion">VALE</td>
                            <td class="informacion">10</td>
                            <td class="informacion">{{ $vale['folioinicial'] }}</td>
                            <td class="informacion">{{ $vale['foliofinal'] }}</td>
                            <td class="informacion"></td>
                            <td class="informacion" colspan="7"></td>
                        </tr>
                        {{-- <tr style="background-color:#0267cd; color:white; border-radius:16px; font-size:9px;">
                            <td colspan="12"><b>DESCRIPCIÓN DE LO ENTREGADO Y RECIBIDO</b></td>
                        </tr> --}}
                        <tr
                            style="color:#0267cd; border-radius:16px; font-size:9px;border-color:#0267cd; text-align:center;">
                            <td colspan="12"><b>DESCRIPCIÓN DE LO ENTREGADO Y RECIBIDO</b></td>
                        </tr>
                        <tr>
                            <td colspan="12">APOYO EN ESPECIE MEDIANTE LA ENTREGA DE VALES GRANDEZA CON UN VALOR
                                EQUIVALENTE A $50.00
                                (CINCUENTA PESOS 00/100 M.N.)
                                CADA UNO Y QUE PUEDEN SER CANJEADOS POR ARTÍCULOS DE PRIMERA
                                NECESIDAD NECESIDAD, EN LOS COMERCIOS PARTICIPANTES DEL PROGRAMA.</td>
                        </tr>
                    </tbody>
                </table>

                <table class="table table-bordered table-sm" style="font-size: 9px;">
                    <tbody>
                        <tr style="text-align: center; font-size:3px;">
                            <td class="encabezado" colspan="6" style="font-size:8px;">ENTREGA</td>
                            <td class="encabezado" colspan="6" style="font-size:8px;">RECIBO DE CONFORMIDAD EL
                                APOYO
                                CON VALES GRANDEZA</td>
                        </tr>
                        <tr style="text-align: center;">
                            <td class="encabezado" colspan="6" rowspan="2" style="">&nbsp;<br>&nbsp;
                            </td>
                            <td class="encabezado" colspan="6" rowspan="2" style="">&nbsp;<br>&nbsp;
                            </td>
                        </tr>
                        <tr>

                        </tr>
                        <tr style="text-align: center; font-size:5px;">
                            <td class="informacion" colspan="6" style="font-size:6px;">NOMBRE Y FIRMA DEL
                                PERSONAL
                                DE
                                LA SECRETARÍA DE
                                DESARROLLO SOCIAL Y HUMANO</td>
                            <td class="informacion" colspan="6" style="font-size:6px;">FIRMA DE LA PERSONA
                                BENEFICIARIA
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="folio">
                <table class="table table-bordered table-sm" style="font-size: 5px;">
                    <thead style="background-color: #1235A2; color:white;">
                    </thead>
                    <tbody>
                        {{-- <tr style="background-color:#0267cd; color:white; border-radius:16px; font-size:9px;">
                            <td colspan="12"><b>REPORTE DE INCIDENCIA</b></td>
                        </tr> --}}
                        <tr
                            style="color:#0267cd; border-radius:16px; font-size:9px;border-color:#0267cd; text-align:center;">
                            <td colspan="12"><b>REPORTE DE INCIDENCIA</b></td>
                        </tr>
                        <tr>
                            <td class="encabezado" colspan="2">Con incidencia</td>
                            <td class="encabezado">Si</td>
                            <td class="encabezado">No</td>
                            <td class="encabezado" colspan="8"></td>
                        </tr>
                    </tbody>
                </table>

                <p style="font-size:8px;font-weight:bold;padding-top:5px;width: 90%; margin:auto auto;">Siendo las
                    ___________ horas del día
                    ___________
                    de ____________________de
                    _______________,<br><br>
                    Yo_________________________________________________________ responsable de la entrega del apoyo
                    y
                    representante de la SEDESHU, manifiesto lo siguiente:<br><br>

                    [&nbsp;&nbsp;&nbsp;] No se localizó a la persona beneficiaria en su domicilio, por segunda
                    ocasión.<br>
                    [&nbsp;&nbsp;&nbsp;] No presenta documento que acredite ser la persona beneficiaria.<br>
                    [&nbsp;&nbsp;&nbsp;] Los familiares y/o vecinos manifiestan que falleció la persona
                    beneficiaria.<br>
                    [&nbsp;&nbsp;&nbsp;] Que la persona beneficiaria se encuentra hospitalizada o en cuarentena por
                    COVID-19
                    o alguna otra
                    enfermedad.<br>
                    [&nbsp;&nbsp;&nbsp;]
                    Otra_____________________________________________________________________________________________________________________________________<br>

                    Lo anterior, con fundamento en los artículos 14 Bis fracciones III y IV y 27 de las de las
                    Reglas de
                    Operación del Programa Vale Grandeza - Compra Local para el <br> Ejercicio Fiscal de 2022, con
                    presencia
                    del testigo de
                    nombre_______________________________________________________________________________________<br>
                    con identificación oficial con fotografía No. ______________________________________________
                    mismo
                    que
                    manifiesta ser _________________________________<br>
                    __________________________________de la persona solicitante, firmando al calce para debida
                    constancia
                    legal.

                </p>

            </div>

            <div class="folio" style="width: 90%; margin:auto auto;">
                <table class="table table-bordered table-sm" style="font-size: 5px;">
                    <tbody>
                        <tr style="text-align: center; font-size:3px;">
                            <td class="encabezado" colspan="6" style="font-size:6px;">POR LA SEDESHU</td>
                            <td class="encabezado" colspan="6" style="font-size:6px;">TESTIGO</td>
                        </tr>
                        <tr style="text-align: center;">
                            <td class="encabezado" colspan="6" rowspan="2" style="">&nbsp;<br>&nbsp;
                            </td>
                            <td class="encabezado" colspan="6" rowspan="2" style="">&nbsp;<br>&nbsp;
                            </td>
                        </tr>
                        <tr>

                        </tr>
                        <tr style="text-align: center; font-size:5px;">
                            <td class="informacion" colspan="6" style="font-size:5px;">NOMBRE Y FIRMA DEL
                                PERSONAL
                                DE
                                LA SECRETARÍA DE
                                DESARROLLO SOCIAL Y HUMANO</td>
                            <td class="informacion" colspan="6" style="font-size:5px;">NOMBRE Y FIRMA DEL
                                TESTIGO
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <img width="70px" height="60px" style="display: inline; padding-bottom:0px; margin-bottom:-1px;"
                src="../public/img/logo_estrategia_pie.png">
            <img width="160px" height="40px"
                style="display: inline; padding-left:490px;padding-bottom:0px; margin-bottom:-1px;"
                src="../public/img/estrategia_logo.png">
            <p class="izq" style="color: #093EAF">
                ____________________________________________________________________________________________________________________________________
                <b>
                    «Este programa es público, ajeno a cualquier partido político. Queda prohibido su uso para fines
                    distintos al desarrollo social».
                    «Los trámites de acceso a los apoyos económicos de los Programas Sociales son gratuitos,
                    personales e intransferibles» El aviso de privacidad podrá ser consultado en la página
                    institucional en Internet:https://desarrollosocial.guanajuato.gob.mx</b>
            </p>
        </div>

        @if ($index != count($vales) - 1)
            <div style="page-break-after:always;"></div>
        @endif
    @endforeach
</body>

</html>
