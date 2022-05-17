SELECT a.*,
       CONCAT_WS(' ', IFNULL(db_a.Info, ''))                                              as cActoresPoliticos,
       CONCAT_WS(' ', IFNULL(db_e.municipio, ''), IFNULL(db_e.seccion, ''))               as celectoralAbogados,
       CONCAT_WS(' ', IFNULL(db_c.municipio, ''), IFNULL(db_c.seccion, ''))               as ccasasAzules,
       CONCAT_WS(' ', IFNULL(db_rc.municipio, ''), IFNULL(db_rc.seccion, ''),
                 IFNULL(db_rc.casilla, ''), IFNULL(db_rc.cargo, ''),
                 IFNULL(db_rc.ine, ''), IFNULL(db_rc.sexo, ''))                           as cRCs,
       CONCAT_WS(' ', IFNULL(db_rg.municipio, ''), IFNULL(db_rg.seccion, ''),
                 IFNULL(db_rg.distritoLocal, ''), IFNULL(db_rg.observacion, ''))          as cRGs,
       CONCAT_WS(' ', IFNULL(db_p.municipio, ''), IFNULL(db_p.seccion, ''),
                 IFNULL(db_p.celular, ''), IFNULL(db_p.observacion, ''))                  as cPromocion,
       CASE WHEN IFNULL(db_m.Info, '') <> '' THEN 'Militante Morena' ELSE '' END          as cMORENA,
       CASE WHEN IFNULL(db_pan.Info, '') <> '' THEN 'Militante PAN' ELSE '' END           as cPAN,
       CASE WHEN IFNULL(db_prd.Info, '') <> '' THEN 'Militante PRD' ELSE '' END           as cPRD,
       CASE WHEN IFNULL(db_pri.Info, '') <> '' THEN 'Militante PRI' ELSE '' END           as cPRI,
       CASE WHEN IFNULL(db_pvem.Info, '') <> '' THEN 'Militante PVEM' ELSE '' END         as cPVEM,
       CONCAT_WS(' ', p.PLAZA, p.NIVEL, p.DENOMINACION_TABULAR, p.TIPO)                   as cPlantilla,
       CASE
           WHEN IFNULL(c_es.NombreCompleto, '') = '' AND IFNULL(db_es.NombreCompleto, '') <> ''
               THEN db_es.NombreCompleto
           WHEN IFNULL(c_es.NombreCompleto, '') <> '' AND IFNULL(db_es.NombreCompleto, '') = '' THEN c_es.NombreCompleto
           WHEN IFNULL(c_es.NombreCompleto, '') <> '' AND IFNULL(db_es.NombreCompleto, '') <> ''
               THEN c_es.NombreCompleto
           WHEN IFNULL(c_es.NombreCompleto, '') = '' AND IFNULL(db_es.NombreCompleto, '') = ''
               THEN '' END                                                                   isEspecial,
       IFNULL(db_o.RepresentanteLegal, '')                                                as isOCS,
       CONCAT_WS(' ', IFNULL(db_r.Partido, ''), IFNULL(db_r.TipoAsociacion, ''),
                 IFNULL(db_r.ClaveCasilla, ''), IFNULL(db_r.Observaciones, ''))           as cINE,
        CONCAT_WS(' ', IFNULL(m.Movimiento, ''), IFNULL(ms.Estatus, ''),
                 IFNULL(pm.QuienProponeConsideracion, ''), IFNULL(pm.FechaRecepcion, '')) as cMovimiento,
       CONCAT_WS(' - ', IFNULL(pe.TipoRepresentante, ''),
                 IFNULL(pe.Municipio, ''),
                 IFNULL(pe.Fuente, '')) as 'ProcesoElectoral 2021', IFNULL(us.Info, '') as ResponsabilidadSIANGTO,
       IFNULL(us.celular, '')                                                             as Celular,
       IFNULL(us.ClaveElector, '')                                                        as INE
FROM cruce_jefatura a
         LEFT JOIN plantilla_v2 as p on p.NOMBRE = a.NOMBRE
         LEFT JOIN db_actorespoliticos db_a on db_a.nombreCompleto = a.NOMBRE
         LEFT JOIN db_electoralabogados db_e on db_e.nombreCompleto = a.NOMBRE
         LEFT JOIN db_electoralcasasazules db_c on db_c.nombreCompleto = a.NOMBRE
         LEFT JOIN db_electoralrc db_rc on db_rc.nombreCompleto = a.NOMBRE
         LEFT JOIN db_electoralrg db_rg on db_rg.nombreCompleto = a.NOMBRE
         LEFT JOIN db_estpromocion db_p on db_p.nombreCompleto = a.NOMBRE
         LEFT JOIN cat_especial c_es on c_es.NombreCompleto = a.NOMBRE
         LEFT JOIN db_especial db_es on db_es.NombreCompleto = a.NOMBRE
         LEFT JOIN db_osc db_o on db_o.RepresentanteLegal = a.NOMBRE
         LEFT JOIN db_pangto db_pan on db_pan.nombreCompleto = a.NOMBRE
         LEFT JOIN db_morenagto db_m on db_m.nombreCompleto = a.NOMBRE
         LEFT JOIN db_prdgto db_prd on db_prd.nombreCompleto = a.NOMBRE
         LEFT JOIN db_prigto db_pri on db_pri.nombreCompleto = a.NOMBRE
         LEFT JOIN db_pvemgto db_pvem on db_pvem.nombreCompleto = a.NOMBRE
         LEFT JOIN db_representante db_r on db_r.nombreCompleto = a.NOMBRE
         LEFT JOIN candidatos c on c.NombreCruce = a.NOMBRE
         LEFT JOIN plaza_movimientos pm on pm.idCandidato = c.id
         LEFT JOIN movimientos as m on m.id = pm.idMovimiento
         LEFT JOIN movimiento_status as ms on ms.id = pm.idMovimientoStatus
         LEFT JOIN db_proceso_electoral as pe on pe.NombreCompleto = a.NOMBRE
         LEFT JOIN db_compromisos_unicos as us on us.NombreCompleto = a.NOMBRE
WHERE (p.NOMBRE <> '' OR p.NOMBRE IS NULL)
  AND a.Nombre <> ''
GROUP BY a.id
ORDER BY a.id;
