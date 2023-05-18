<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    {{-- <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css"
        integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous"> --}}
    <title>Acuse Calentadores Solares</title>
    <style>
        body {
            font-family: Century Gothic, CenturyGothic, AppleGothic, sans-serif;
        }

        @page {
            margin: 10px 15px;
        }

        .header {
            width: 90%;
            height: 45px;
            margin: auto auto;
        }

        .header-table {
            width: 100%;
            font-size: 12px;
            font-weight: bold;
        }

        .td-p {
            text-align: center;
        }

        .td-contigo-si {
            align-items: flex-end;
        }

        .logoGto {
            height: 65px;
            width: 120px;
        }

        .logoContigoSi {
            height: 55px;
            width: 160px;
            padding-left: 100px;
        }

        .body-title {
            width: 70%;
            margin: 38px auto;
            font-size: 11px;
        }

        .body-title-folio {
            padding-left: 20px;
            border-bottom: 1px solid;
            letter-spacing: 8px;
            font-weight: bold;
        }

        .body-title-fecha {
            width: 20%;
            padding-left: 20px;
            border-bottom: 1px solid;
            letter-spacing: 2px;
            font-weight: bold;
        }

        .body-table {
            width: 90%;
            margin: 0 auto;
            font-size: 11px;
            table-layout: fixed;
            margin-bottom: 0px;
        }

        .body-solicitud {
            font-size: 11px;
            width: 90%;
            margin: auto auto;
        }

        .body-protesta {
            width: 90%;
            margin: 5px auto;
            text-align: justify;
            line-height: 20px;
            font-size: 11px;
        }

        .body-datos {
            width: 90%;
            margin: 0 auto;
            font-size: 11px;
            table-layout: fixed;
        }

        .body-si-no {
            width: 80%;
            margin: 0 auto;
            font-size: 11px;
            table-layout: fixed;
        }

        p {
            padding-top: 0;
            margin-top: 0;
        }
    </style>
</head>

<body>
    @foreach ($calentadores as $index => $calentador)
        <header class="header">
            <table class="header-table">
                <tr>
                    <td rowspan="2">
                        <img class="logoGto"
                            src="data:image/png;base64,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">
                    </td>
                    <td class="td-p" style="vertical-align: bottom;">
                        <p style="display:inline;">ANEXO 2 </p>
                    </td>
                    <td rowspan="2" class="td-contigo-si">
                        <img class="logoContigoSi"
                            src="data:image/png;base64,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">
                    </td>
                </tr>
                <tr>
                    <td class="td-p" style="vertical-align: top;">
                        <p style="display: inline;">Solicitud GTO Contigo Sí</p>
                    </td>
                </tr>
            </table>
        </header>
        <div class="body-title">
            <table class="body-title-table">
                <tr>
                    <td>Folio:</td>
                    <td class="body-title-folio">{{ $calentador['id'] }}</td>
                    <td style="padding-left:170px;">Fecha:</td>
                    <td class="body-title-fecha">{{ $calentador['FechaSolicitud'] }}</td>
                </tr>
            </table>
        </div>
        <div class="body">
            <table class="body-table">
                <tr>
                    <td style="width:30%;text-align:justify;word-spacing:15px; vertical-align:bottom;">Por medio del
                        presente yo, C.</td>
                    <td
                        style="width:60%; padding-left:20px;border-bottom:1px solid;text-align:center;word-spacing:10px;font-weight:bold;vertical-align:bottom;">
                        {{ $calentador['Nombre'] }}</td>
                </tr>
                <tr>
                    <td colspan="2" style="text-align:justify; line-height:25px;">
                        <span style="word-spacing:8px;">solicito ser
                            considerada(o) para recibir</span><span
                            style="text-decoration:underline; font-weight:bold;word-spacing:12px;"> Apoyo con
                            Calentador Solar </span> <span <span style="word-spacing:9px;">del Programa</span> <span
                            style="text-decoration:underline; font-weight:bold;word-spacing:12px;">
                            Calentadores Solares </span>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" style="text-align:justify; line-height:20px; word-spacing:10px;">
                        y para tal efecto proporciono los siguientes datos personales:
                    </td>
                </tr>
            </table>
            <table class="body-datos" style="margin-top: 15px;">
                <tr>
                    <td style="width:7%;">CURP:</td>
                    <td
                        style="width:44%;font-weight:bold;text-align:center;border-bottom:1px solid;letter-spacing:5px;">
                        {{ $calentador['CURP'] }}
                    </td>
                    <td colspan="2">
                        Sexo:
                    </td>
                    <td colspan="4" style="width:44%;font-weight:bold;text-align:center;border-bottom:1px solid;">
                        {{ $calentador['Sexo'] }}
                    </td>
                </tr>
                <tr style="line-height:25px;">
                    <td style="width:8%;">Calle:</td>
                    <td style="width:44%;font-weight:bold;text-align:center;border-bottom:1px solid;">
                        {{ $calentador['Calle'] }}
                    </td>
                    <td style="width:8%;">No. Ext.:</td>
                    <td style="width:9%;font-weight:bold;text-align:center;border-bottom:1px solid;">
                        {{ $calentador['NumExt'] }}</td>
                    <td style="width:5%;">No. Int.:</td>
                    <td style="width:6%;font-weight:bold;text-align:center;border-bottom:1px solid;">
                        {{ $calentador['NumInt'] }}</td>
                    <td style="width:4%;">C.P.:</td>
                    <td style="width:6%;font-weight:bold;text-align:center;border-bottom:1px solid;">
                        {{ $calentador['CP'] }}
                    </td>
                </tr>
                <tr style="line-height:25px;">
                    <td style="width:7%;">Colonia:</td>
                    <td style="width:44%;font-weight:bold;text-align:center;border-bottom:1px solid;">
                        {{ $calentador['Colonia'] }}</td>
                    <td colspan="2">
                        Localidad:
                    </td>
                    <td colspan="4" style="width:44%;font-weight:bold;text-align:center;border-bottom:1px solid;">
                        {{ $calentador['Localidad'] }}
                    </td>
                </tr>
                <tr style="line-height:25px;">
                    <td style="width:7%;">Municipio:</td>
                    <td style="width:44%;font-weight:bold;text-align:center;border-bottom:1px solid;">
                        {{ $calentador['Municipio'] }}</td>
                    <td colspan="2">
                        Estado:
                    </td>
                    <td colspan="4" style="width:44%;font-weight:bold;text-align:center;border-bottom:1px solid;">
                        GUANAJUATO
                    </td>
                </tr>
            </table>
            <table class="body-datos" style="margin-top: 25px;">
                <tr>
                    <td colspan="2" style="font-weight:bold;">
                        Nombre completo de la persona acompañante (opcional):
                    </td>
                </tr>
                <tr style="line-height:18px;">
                    <td style="width:35%;word-spacing:16px;">A este acto me acompaña C.</td>
                    <td
                        style="width:65%; padding-left:20px;border-bottom:1px solid;text-align:center;word-spacing:10px;font-weight:bold;vertical-align:bottom;">
                        {{ $calentador['Tutor'] }}</td>
                </tr>
                <tr style="line-height:18px;">
                    <td style="width:35%;word-spacing:22px;">a quien reconozco como:</td>
                    <td
                        style="width:65%; padding-left:20px;border-bottom:1px solid;text-align:center;word-spacing:10px;font-weight:bold;vertical-align:bottom;">
                        {{ $calentador['Parentesco'] }}</td>
                </tr>

                <tr style="line-height:18px;">
                    <td style="width:35%;word-spacing:5px;">CURP de la persona acompañante</td>
                    <td
                        style="width:65%; padding-left:20px;border-bottom:1px solid;text-align:center;letter-spacing: 8px;font-weight:bold;vertical-align:bottom;">
                        {{ $calentador['CURPTutor'] }}</td>
                </tr>
            </table>
            <table class="body-datos" style="margin-top: 10px;">

                <tr style="line-height:25px;">
                    <td style="width:8%;">Telefono fijo:</td>
                    <td
                        style="width:10%;border-bottom:1px solid;text-align:center;word-spacing:10px;font-weight:bold;vertical-align:bottom;">
                        {{ $calentador['Telefono'] }}</td>
                    <td style="width:6%;">Celular:</td>
                    <td
                        style="width:10%;border-bottom:1px solid;text-align:center;word-spacing:10px;font-weight:bold;vertical-align:bottom;">
                        {{ $calentador['Celular'] }}</td>
                    <td style="width:17%;">Correo electrónico (opcional):</td>
                    <td
                        style="width:20%;border-bottom:1px solid;text-align:center;word-spacing:10px;font-weight:bold;vertical-align:bottom;">
                        {{ $calentador['Correo'] }}</td>
                </tr>
            </table>
            <div class="body-protesta">
                <p style="text-decoration: underline; text-align:center;font-weight:bold;">
                    DECLARO BAJO PROTESTA DE DECIR VERDAD:
                </p>
                <p style="text-align:justify; line-height:15px;">
                    a) Que todo lo manifestado en la solicitud y documentación entregada o llenada son datos verídicos,
                    auténticos y fidedignos, así como la firma o huella dactilar que aparece en el presente
                    documento.<br>
                    b) Que he leído y cumpliré con lo establecido en las Reglas de Operación del Programa, y demás
                    normativa aplicable.<br>
                    c) Que debido a la situación familiar actual se requiere el apoyo o servicio que otorga el programa
                    para mejorar mis condiciones de vida y las de mi familia.

                </p>
            </div>
            <div class="body-protesta">
                <p style="text-decoration: underline; text-align:center;font-weight:bold;">
                    CONSENTIMIENTO PARA EL TRATAMIENTO DE DATOS PERSONALES
                </p>
                <p style="text-align:justify;line-height:15px;">
                    Manifiesto que he leído y acepto el aviso de privacidad, el cual tuve a la vista y continuará a mi
                    disposición en la página institucional en Internet
                    <span style="color: #093EAF;">https://desarrollosocial.guanajuato.gob.mx/programas/</span>, por lo
                    que:
                </p>
                <p style="text-align:justify;">
                    a) Que acepto recibir información de Gobierno del Estado de Guanajuato en domicilio y datos de
                    contacto proporcionados:
                </p>
            </div>

            <table class="body-si-no" style="margin-top: 10px;">

                <tr style="line-height:20px;">
                    <td style="width:35%; border:1px solid;">[&nbsp;&nbsp;] Sí otorgo mi consentimiento para el
                        tratamiento de mis
                        datos personales y
                        para recibir información de Gobierno del Estado.</td>
                    <td style="width:10%;text-align:center;word-spacing:10px;font-weight:bold;vertical-align:bottom;">
                    </td>
                    <td style="width:35%;border:1px solid;">[&nbsp;&nbsp;] No otorgo mi consentimiento para el
                        tratamiento de mis
                        datos personales,
                        ni para recibir información de Gobierno del Estado.</td>
                </tr>
            </table>

            <div class="body-protesta">
                <p style="text-align: center; font-weight:bold;">ATENTAMENTE</p>
            </div>

            <div class="body-protesta" style="margin-top: 80px;">
                <p style="text-align: center;font-weight:bold;">Nombre y firma o huella dactilar de la persona
                    solicitante, tutor(a) o acompañante <br>
                    <span
                        style="text-align: center;font-weight:bold;color: #093EAF; font-size:10px; word-spacing:3px;">
                        «Este programa es público, ajeno a cualquier partido político. Queda prohibido su uso para fines
                        distintos al desarrollo social»</span>

                    <span style="text-align: center;font-weight:bold;color: #093EAF; font-size:10px;">
                        «Los trámites de acceso a los apoyos económicos de los Programas Sociales son gratuitos,
                        personales e intransferibles»</span>

                </p>
            </div>
        </div>

        @if ($index != count($calentadores) - 1)
            <div style="page-break-after:always;"></div>
        @endif
    @endforeach
</body>

</html>
