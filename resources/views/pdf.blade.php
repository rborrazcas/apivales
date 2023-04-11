<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    {{-- <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css"
        integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous"> --}}
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
            height: 34px;
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

        .encabezado {
            color: #0267cd;
            font-weight: bold;
            font-size: 10px;
            height: 20px;
        }

        .informacion {
            color: black;
            text-align: center;
            font-size: 10px;
            height: 40px;
        }

        .text-monospace {
            margin: 0px, 0px, 0px, 0px;
            padding-left: 0px;
            font-weight: bold;
            font-size: 20px;
        }

        .folio table {
            width: 730px;
        }

        .folio tr td {
            border: 1px solid #e1e5e8;
            width: 100px;
            word-wrap: break-word;
        }

        .capt {
            height: 20px;
        }

        .folios tr td {
            border: 1px solid #e1e5e8;
            width: 320px;
            word-wrap: break-word;
        }
    </style>
</head>

<body>
    @foreach ($vales as $index => $vale)
        <header>
            {{-- <img style="height:102%; width:10%; display:inline; padding-top:8px; padding-right:25px;"
                src="../public/img/logoSDSH_rz.png"> --}}
            <img style="height:102%; width:10%; display:inline; padding-top:8px; padding-right:25px;"
                src="data:image/png;base64,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">
            <p style="padding-left:50px">Recibo de Entrega - Recepción</p>
            <img style="height:90%; display:inline; padding-top:4px; padding-left:100px;"
                src="data:image/png;base64,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">

        </header>
        <div class="container-fluid">
            <div class="subTitle" style="padding-top: 37px;">
                <h7>Programa Vale Grandeza - Compra Local 2022</h7>
            </div>
            <div class="folio">
                <table border="1" style="font-size: 5px; margin:auto auto;" cellspacing="0">
                    <tbody>
                        <tr style="color:#0267cd;font-size:9px;text-align:center;">
                            <td class="capt" colspan="12"><b>DATOS GENERALES</b></td>
                        </tr>
                        <tr>
                            <td colspan="2" class="encabezado">Folio Solicitud:</td>
                            <td colspan="5" class="encabezado">Acuerdo:</td>
                            <td colspan="1" class="encabezado">Región:</td>
                            <td colspan="4" class="encabezado">Responsable:</td>
                        </tr>
                        <tr>
                            <td colspan="2" class="informacion">{{ $vale['id'] }}</td>
                            <td colspan="5"class="informacion">{{ $vale['acuerdo'] }}</td>
                            <td colspan="1"class="informacion">{{ $vale['region'] }}</td>
                            <td colspan="4" class="informacion">{{ $vale['enlace'] }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <h5 style="text-align: center;"></h5>
            <div class="folio">
                <table border="1" style="font-size: 5px; margin:auto auto;" cellspacing="0">
                    <thead style="background-color: #1235A2; color:white;">
                    </thead>
                    <tbody>
                        <tr
                            style="color:#0267cd; border-radius:16px; font-size:9px;border-color:#0267cd; text-align:center;">
                            <td class="capt" colspan="12"><b>DATOS DEL BENEFICIARIO</b></td>
                        </tr>
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

                            @if ($vale['municipio'] == 'DOLORES HIDALGO CUNA DE LA INDEPENDENCIA NAL.')
                                <td colspan="3" class="informacion">DOLORES HIDALGO C. I. N.</td>
                            @else
                                <td colspan="3" class="informacion">{{ $vale['municipio'] }}</td>
                            @endif
                            <td colspan="3" class="informacion">{{ $vale['localidad'] }}</td>
                            <td colspan="3" class="informacion">{{ $vale['colonia'] }}</td>
                            <td colspan="3" class="informacion">{{ $vale['cp'] }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <h5 style="text-align: center;"></h5>
            <div class="folio">
                <table border="1" style="font-size: 9px; margin:auto auto;" cellspacing="0">
                    <thead style="background-color: #1235A2; color:white;">
                    </thead>
                    <tbody>
                        {{-- <tr style="background-color:#0267cd; color:white; border-radius:16px; font-size:9px;">
                            <td colspan="12"><b>RECEPCIÓN - ENTREGA DEL APOYO</b></td>
                        </tr> --}}
                        <tr
                            style="color:#0267cd; border-radius:16px; font-size:9px;border-color:#0267cd; text-align:center;">
                            <td class="capt" colspan="12"><b>RECEPCIÓN - ENTREGA DEL APOYO</b></td>
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
                            <td class="capt" colspan="12"><b>DESCRIPCIÓN DE LO ENTREGADO Y RECIBIDO</b></td>
                        </tr>
                        <tr>
                            <td style="height:40px" colspan="12">APOYO EN ESPECIE MEDIANTE LA ENTREGA DE VALES
                                GRANDEZA CON UN VALOR
                                EQUIVALENTE A $50.00
                                (CINCUENTA PESOS 00/100 M.N.)
                                CADA UNO Y QUE PUEDEN SER CANJEADOS POR ARTÍCULOS DE PRIMERA
                                NECESIDAD NECESIDAD, EN LOS COMERCIOS PARTICIPANTES DEL PROGRAMA.</td>
                        </tr>
                    </tbody>
                </table>

                <table border="1" style="font-size: 9px; margin:auto auto;" cellspacing="0">
                    <tbody>
                        <tr style="text-align: center; font-size:3px;">
                            <td class="encabezado" colspan="6" style="font-size:8px;">ENTREGA</td>
                            <td class="encabezado" colspan="6" style="font-size:8px;">RECIBO DE CONFORMIDAD EL
                                APOYO
                                CON VALES GRANDEZA</td>
                        </tr>
                        <tr style="text-align: center;">
                            <td colspan="6" style="height:60px">&nbsp;<br>&nbsp;
                            </td>
                            <td colspan="6" style="height:60px">&nbsp;<br>&nbsp;
                            </td>
                        </tr>
                        <tr style="text-align: center; font-size:5px;">
                            <td class="encabezado" colspan="6" style="font-size:6px;">NOMBRE Y FIRMA DEL
                                PERSONAL
                                DE
                                LA SECRETARÍA DE
                                DESARROLLO SOCIAL Y HUMANO</td>
                            <td class="encabezado" colspan="6" style="font-size:6px;">FIRMA DE LA PERSONA
                                BENEFICIARIA
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="folio">
                <table border="1" style="font-size: 5px; margin:auto auto;" cellspacing="0">
                    <thead style="background-color: #1235A2; color:white;">
                    </thead>
                    <tbody>
                        {{-- <tr style="background-color:#0267cd; color:white; border-radius:16px; font-size:9px;">
                            <td colspan="12"><b>REPORTE DE INCIDENCIA</b></td>
                        </tr> --}}
                        <tr
                            style="color:#0267cd; border-radius:16px; font-size:9px;border-color:#0267cd; text-align:center;">
                            <td class="capt" colspan="12"><b>REPORTE DE INCIDENCIA</b></td>
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
            <br>
            <div class="folios" style="width: 90%; margin:auto auto;">
                <table border="1" style="font-size: 5px; margin:auto auto;" cellspacing="0">
                    <tbody>
                        <tr style="text-align: center; font-size:3px;">
                            <td class="encabezado" colspan="6" style="font-size:6px;">POR LA SEDESHU</td>
                            <td class="encabezado" colspan="6" style="font-size:6px;">TESTIGO</td>
                        </tr>
                        <tr style="text-align: center;">
                            <td class="encabezado" colspan="6" style="height:45px">&nbsp;<br>&nbsp;
                            </td>
                            <td class="encabezado" colspan="6" style="height:45px">&nbsp;<br>&nbsp;
                            </td>
                        </tr>

                        <tr style="text-align: center; font-size:5px;">
                            <td class="encabezado" colspan="6" style="font-size:5px;">NOMBRE Y FIRMA DEL
                                PERSONAL
                                DE <br>
                                LA SECRETARÍA DE
                                DESARROLLO SOCIAL Y HUMANO</td>
                            <td class="encabezado" colspan="6" style="font-size:5px;">NOMBRE Y FIRMA DEL
                                TESTIGO
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <img width="70px" height="60px"
                style="display: inline; padding-bottom:0px; margin-bottom:-1px;padding-left:30px;padding-top:20px;"
                src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAGQAAABECAMAAABAkGBQAAAAYFBMVEVHcEzF0fkEeP8ZVt8AP+b///////8Ad/9WffD+///9/v84h/n///9Tfuz///8AZvabu/r9/f////8nSb8APucAROH////+AdUA1BEAYfTE1fpRfesAloLt8f0A1BIA0xgEumUsAAAAFHRSTlMAMuv38wsm/tkYN6ZApEm3jmtOW+iGWVcAAAH7SURBVFjD7ZhtV8IwDIU5GyOMAaI2uA6d//9fynQvTZv0ZQe/aO+nTjl7uLldl7DZZGVl/U/tjsfz+XjYmap39aSqru6ql+tZCYy9ShaOurz8DgQ1fEvrcYFNEgTDhOGmlgbWKQaCNgfHJc6ru7RLGAWwjXFiuBAMadAoC6CMKRc6CKSFYm793vf9gjmkBY9u2tzXb9vWoKDPzJ5zYaajlBBG3/aUAmu28Egy03gbtEDu+sQIipkJmnamlcOYKEMkfUvyLzwQ9IRCakUgitllz2nlmqyRPAiE3ctxT7zlysrcZHTXQR39QLHm7BKe8iuVYaUOZoLKyp9lfFxd+a14M6GQ8tZx97eshCBoc7RUHyozF/Z42dPbI7kCD2T5Fy1m43XCbC8RchsLyCRWeIJnH0gBQv4U86h4MnE31yMg7rn1UIj7ssK1kCayXDNSp0OkLYyKaSZ+VpAOST+70iFFZN9lOtOJEKjCTqwNgJaVMGTVUU8pQQjE9F2uI/L6DUH4t4nrBG0gqGgINMFGQjIDOhICT/6WCIXe0aqYFyIzhODR6rYpxbyYTvsSXhN6YbatF8eGYF9HIBgy4xkdbiBtK3c+QT4bnIcgxbdH/rEh9DCiUzRQBFR2HUBRrRhMkT31l6GODKaXqOn3tI2be7eOnvJvIFlZWVlZf0Vf2Y7ujrolevsAAAAASUVORK5CYII=">
            <img width="140px" height="40px"
                style="display: inline; padding-bottom:0px; margin-bottom:-1px;padding-left:200px;"
                src="data:image/png;base64,{{ $vale['codigo'] }}">

            <img width="160px" height="40px"
                style="display: inline; padding-left:120px;padding-bottom:0px; margin-bottom:-1px;"
                src="data:image/png;base64,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">
            {{-- <img width="70px" height="60px" style="display: inline; padding-bottom:0px; margin-bottom:-1px;"
                src="../public/img/logo_estrategia_pie_rz.png"> --}}
            {{-- <img width="160px" height="40px"
                style="display: inline; padding-left:490px;padding-bottom:0px; margin-bottom:-1px;"
                src="../public/img/estrategia_logo_rz.png"> --}}
            <p class="izq" style="color: #093EAF">
                ____________________________________________________________________________________________________________________________________
                <br>
                <b>
                    «Este programa es público, ajeno a cualquier partido político. Queda prohibido su uso para fines
                    distintos al desarrollo social»<br>
                    «Los trámites de acceso a los apoyos económicos de los Programas Sociales son gratuitos,
                    personales e intransferibles»<br> El aviso de privacidad podrá ser consultado en la página
                    institucional en Internet:https://desarrollosocial.guanajuato.gob.mx</b>
            </p>
        </div>

        @if ($index != count($vales) - 1)
            <div style="page-break-after:always;"></div>
        @endif
    @endforeach
</body>

</html>
