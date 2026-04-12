<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Spanish language strings for local_saipa.
 *
 * @package    local_saipa
 * @copyright  2026 Schaller & Ponce <dev@schaller-ponce.com.ar>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */$string['advisor_period']         = 'Período:';
$string['advisor_tab_config']     = 'Configuración';
$string['advisor_tab_controls']   = 'Controles por Curso';
$string['advisor_tab_engagement'] = 'Engagement';
$string['advisor_tab_risk']       = 'ROI Riesgo';
$string['advisor_tab_summary']    = 'Resumen';
$string['advisor_title']          = 'SAIPA — Panel Asesor';
$string['alert_cancel_btn']         = 'Cancelar';
$string['alert_default_message']    = 'Hola {$a->student} 👋

Tu docente {$a->teacher} notó que podrías necesitar apoyo en el curso *{$a->course}*.

{$a->risk_detail}

Recordá que podés escribirme en cualquier momento para consultar dudas sobre los temas de la materia. ¡Estoy aquí para ayudarte! 📚';
$string['alert_not_linked']         = '{$a} no tiene Telegram vinculado.';
$string['alert_send_btn']           = 'Enviar';
$string['alert_sent_error']         = 'No se pudo enviar el mensaje: {$a}.';
$string['alert_sent_ok']            = 'Mensaje enviado a {$a}.';
$string['alert_telegram_btn']       = 'Alertar por Telegram';
$string['cfg_alert_template']        = 'Plantilla de mensaje de alerta por defecto';
$string['cfg_cooldown']              = 'Cooldown de alertas';
$string['cfg_cooldown_hours']        = 'Cooldown de alertas (horas entre alertas al mismo alumno)';
$string['cfg_heading_alerts']        = 'Configuración de Alertas';
$string['cfg_heading_features']      = 'Flags de Funcionalidades';
$string['cfg_heading_gdpr']          = 'Retención de Datos (GDPR)';
$string['cfg_heading_thresholds']    = 'Umbrales de Riesgo';
$string['cfg_rag_global']            = 'Habilitar RAG (respuestas IA) globalmente';
$string['cfg_retention']             = 'Retención de datos';
$string['cfg_retention_days']        = 'Días para conservar mensajes y scores de riesgo';
$string['cfg_risk_eval']             = 'Habilitar evaluación de riesgo nocturna';
$string['cfg_risk_high']             = 'Umbral riesgo alto';
$string['cfg_risk_medium']           = 'Umbral riesgo medio';
$string['cfg_save_btn']              = 'Guardar configuración';
$string['cfg_save_error']            = 'Error al guardar la configuración.';
$string['cfg_saved_ok']              = 'Configuración guardada.';
$string['cfg_template_placeholders'] = 'Marcadores: {student}, {course}, {teacher}';
$string['cfg_threshold_high']        = 'Umbral riesgo alto (0–1)';
$string['cfg_threshold_medium']      = 'Umbral riesgo medio (0–1)';
$string['ctrl_col_alerts']  = 'Alertas';
$string['ctrl_col_chat']    = 'Chat';
$string['ctrl_col_course']  = 'Curso';
$string['ctrl_col_index']   = 'Re-indexar';
$string['ctrl_col_rag']     = 'RAG';
$string['ctrl_col_risk']    = 'Riesgo';
$string['ctrl_col_saipa']   = 'SAIPA';
$string['ctrl_disable_all'] = 'Deshabilitar todos';
$string['ctrl_enable_all']  = 'Habilitar todos';
$string['ctrl_save_error']  = 'Error al guardar la configuración.';
$string['ctrl_saved']       = 'Guardado.';
$string['eff_col_alert_date']   = 'Fecha alerta';
$string['eff_col_course']       = 'Curso';
$string['eff_col_improved']     = 'Mejoró';
$string['eff_col_risk_after']   = 'Riesgo después (14d)';
$string['eff_col_risk_before']  = 'Riesgo antes';
$string['eff_col_user']         = 'Alumno';
$string['eff_no_data']          = 'Sin datos de intervención para este período.';
$string['effectiveness_title']  = 'Efectividad de intervenciones';
$string['eng_col_avg_depth']      = 'Msgs/sesión prom.';
$string['eng_col_course']         = 'Curso';
$string['eng_col_feedback']       = '% feedback positivo';
$string['eng_col_msgs']           = 'Mensajes';
$string['eng_col_users']          = 'Usuarios únicos';
$string['eng_course_usage_title'] = 'Uso por curso';
$string['eng_depth_title']        = 'Distribución de profundidad de sesión';
$string['eng_heatmap_desc']       = '¿Cuándo chatean los alumnos? Día 0=Lunes, Día 6=Domingo. Hora 0=medianoche.';
$string['eng_heatmap_title']      = 'Mapa de actividad (hora × día de la semana)';
$string['engine_error'] = 'Error del motor SAIPA';
$string['funnel_title']         = 'Embudo de alertas';
$string['healthcheck:fail']    = 'No se puede alcanzar el motor SAIPA en la URL configurada';
$string['healthcheck:heading'] = 'Prueba de conexión con el motor';
$string['healthcheck:ok']      = 'El motor SAIPA está disponible';
$string['help_btn']                 = 'Ayuda';
$string['help_cfg_cooldown']        = 'Horas mínimas entre alertas consecutivas al mismo alumno. Previene la fatiga por alertas. Por defecto: 24h.';
$string['help_cfg_high']            = 'Alumnos con score superior a este valor se marcan como riesgo alto (badge rojo) y disparan alertas automáticas. Debe ser mayor que el umbral medio. Recomendado: 0.65–0.80.';
$string['help_cfg_medium']          = 'Alumnos con score superior a este valor se marcan como riesgo medio (badge amarillo). Recomendado: 0.35–0.50.';
$string['help_cfg_retention']       = 'Días que se conservan los mensajes de chat y el historial de scores de riesgo antes de su eliminación. Relevante para el cumplimiento del GDPR. Por defecto: 730 días (2 años).';
$string['help_col_default']         = 'Valor por defecto';
$string['help_col_effect']          = 'Efecto';
$string['help_col_meaning']         = 'Significado';
$string['help_col_metric']          = 'Métrica';
$string['help_col_param']           = 'Parámetro';
$string['help_col_toggle']          = 'Control';
$string['help_col_use']             = 'Cuándo modificarlo';
$string['help_depth_desc']          = 'Histograma que muestra cuántos mensajes intercambian los alumnos por sesión. Sesiones de 11+ mensajes indican engagement profundo. Sesiones cortas (1–3 mensajes) pueden señalar que el alumno no encontró una respuesta útil.';
$string['help_depth_title']         = 'Distribución de profundidad de sesión';
$string['help_effectiveness_desc']  = 'Para cada alumno que recibió una alerta, compara su score de riesgo al momento de la alerta versus 14 días después. Una flecha verde indica que el riesgo del alumno disminuyó, sugiriendo que la alerta generó re-engagement.';
$string['help_effectiveness_title'] = 'Tabla de efectividad de intervenciones';
$string['help_funnel_step1']        = '<strong>Evaluados</strong> — Alumnos cuyo score de riesgo fue calculado por el modelo XGBoost.';
$string['help_funnel_step2']        = '<strong>Riesgo alto detectado</strong> — Alumnos cuyo score superó el umbral de riesgo alto.';
$string['help_funnel_step3']        = '<strong>Recibieron alerta</strong> — Alumnos a quienes efectivamente se les envió una alerta por Telegram.';
$string['help_funnel_step4']        = '<strong>Respondieron</strong> — Alumnos que contestaron la alerta dentro de las 48 horas.';
$string['help_funnel_step5']        = '<strong>Re-ingresaron a Moodle</strong> — Alumnos que iniciaron sesión en Moodle dentro de los 7 días de recibir la alerta.';
$string['help_funnel_title']        = 'Embudo de alertas';
$string['help_heatmap_desc']        = 'Cada celda representa la cantidad de mensajes enviados en esa hora y día de la semana. Azul más oscuro = más actividad. Usalo para programar campañas de Telegram u horarios de atención docente.';
$string['help_heatmap_title']       = 'Mapa de calor de actividad (hora × día)';
$string['help_kpi_active_courses']  = 'Cursos con al menos una sesión SAIPA en el período seleccionado.';
$string['help_kpi_alerts_resp']     = 'Alertas a las que el alumno respondió dentro de las 48 horas.';
$string['help_kpi_alerts_sent']     = 'Alertas de riesgo de abandono enviadas por SAIPA a alumnos vía Telegram.';
$string['help_kpi_enrolled']        = 'Total de alumnos inscriptos en todos los cursos activos.';
$string['help_kpi_feedback']        = 'Porcentaje de sesiones donde el alumno valoró la respuesta positivamente (👍).';
$string['help_kpi_messages']        = 'Mensajes totales intercambiados (alumno + asistente) en el período.';
$string['help_kpi_saipa_users']     = 'Alumnos únicos que abrieron al menos una sesión de chat SAIPA.';
$string['help_sparkline_note']      = 'Los gráficos de tendencia diaria son alimentados por la tarea programada <em>aggregate_daily_stats</em> (se ejecuta a las 03:00). Los datos aparecen después de la primera noche que se ejecuta. Habilitala en Administración del sitio → Servidor → Tareas programadas.';
$string['help_sparkline_note_title'] = 'Sobre los gráficos de tendencia:';
$string['help_subtitle']            = 'Sistema institucional de acompañamiento pedagógico con IA';
$string['help_support_link']        = 'Contactar soporte';
$string['help_support_url']         = 'mailto:soporte@schaller-ponce.com.ar';
$string['help_tab1_intro']          = 'Muestra los indicadores clave de rendimiento (KPIs) para el período seleccionado. Usá el selector de período (7d / 30d / semestre / todo) para cambiar el rango temporal. Todas las métricas son a nivel institucional.';
$string['help_tab1_title']          = 'Pestaña 1 — Resumen institucional';
$string['help_tab2_intro']          = 'Muestra la efectividad del pipeline de detección de abandono y alertas de SAIPA. Usá esta pestaña para demostrar el retorno de inversión a los directivos académicos.';
$string['help_tab2_title']          = 'Pestaña 2 — ROI de Riesgo de Abandono';
$string['help_tab3_intro']          = 'Analiza cómo y cuándo los alumnos interactúan con SAIPA. Útil para entender patrones de adopción y planificar estrategias de comunicación.';
$string['help_tab3_title']          = 'Pestaña 3 — Engagement';
$string['help_tab4_intro']          = 'Habilitá o deshabilitá funcionalidades individuales de SAIPA por curso. Los cambios surten efecto inmediatamente sin necesidad de recargar. Usá las acciones masivas para aplicar configuraciones a todos los cursos a la vez.';
$string['help_tab4_title']          = 'Pestaña 4 — Controles por Curso';
$string['help_tab4_warning']        = 'Deshabilitar SAIPA en un curso no elimina los datos existentes. Todo el historial, scores de riesgo y mensajes se conservan y se reanudan si SAIPA se vuelve a habilitar.';
$string['help_tab5_intro']          = 'Configuración global que afecta a todos los cursos. Solo los usuarios con la capacidad <em>local/saipa:manage</em> (típicamente Administradores del sitio) pueden modificar estos ajustes.';
$string['help_tab5_note']           = 'Los cambios en los umbrales de riesgo surten efecto en el próximo ciclo de evaluación nocturna (02:00). Los cambios en cooldown y retención surten efecto de inmediato.';
$string['help_tab5_title']          = 'Pestaña 5 — Configuración Institucional';
$string['help_title']               = 'Dashboard SAIPA — Ayuda';
$string['help_toggle_alerts']       = 'Detiene el envío de alertas por Telegram a alumnos de este curso, aunque se detecte riesgo alto.';
$string['help_toggle_chat']         = 'Oculta la interfaz de chat. La evaluación de riesgo y las alertas siguen funcionando.';
$string['help_toggle_rag']          = 'Deshabilita las respuestas con recuperación de información. El asistente responde solo desde el LLM base (sin conocimiento específico del curso).';
$string['help_toggle_risk']         = 'Detiene el cálculo de scores de riesgo para este curso. No se generarán nuevas alertas.';
$string['help_toggle_saipa']        = 'Interruptor maestro. Si está APAGADO, SAIPA no aparece en el bloque del curso para ningún alumno.';
$string['help_trend_desc']          = 'Gráfico de líneas que muestra el porcentaje de alumnos en cada nivel de riesgo (alto / medio / bajo) por semana. Una tendencia descendente en la línea roja después de un período de intervención indica que el sistema es efectivo.';
$string['help_trend_title']         = 'Tendencia semanal de riesgo';
$string['kpi_active_courses'] = 'Cursos activos';
$string['kpi_alerts_resp']    = 'Alertas respondidas';
$string['kpi_alerts_sent']    = 'Alertas enviadas';
$string['kpi_enrolled']       = 'Alumnos inscriptos';
$string['kpi_feedback']       = 'Feedback positivo';
$string['kpi_messages']       = 'Mensajes totales';
$string['kpi_saipa_users']    = 'Usuarios SAIPA';
$string['mc_col_active']      = 'Usuarios SAIPA';
$string['mc_col_adoption']    = 'Adopción';
$string['mc_col_course']      = 'Curso';
$string['mc_col_enrolled']    = 'Inscriptos';
$string['mc_col_index']       = 'Índice';
$string['mc_col_msgs7d']      = 'Mensajes (7d)';
$string['mc_col_risk']        = 'Riesgo';
$string['mc_no_courses']      = 'No se encontraron cursos con SAIPA habilitado.';
$string['messageprovider:risk_alert']    = 'Alertas de riesgo de abandono (SAIPA)';
$string['messageprovider:telegram_link'] = 'Confirmación de vinculación con Telegram (SAIPA)';
$string['my_courses_heading'] = 'Mis Cursos SAIPA';
$string['my_courses_title']   = 'SAIPA — Mis Cursos';
$string['no_data_yet']        = 'Todavía no hay datos suficientes — volvé en unos días.';
$string['notenrolled']              = 'El usuario especificado no está inscripto en este curso.';
$string['period_30d']             = 'Últimos 30 días';
$string['period_7d']              = 'Últimos 7 días';
$string['period_all']             = 'Todo el tiempo';
$string['period_semester']        = 'Semestre (120d)';
$string['pluginname'] = 'SAIPA - Sistema de Intervención Pedagógica Adaptativa';
$string['privacy:metadata:message']              = 'Contenido del mensaje de WhatsApp';
$string['privacy:metadata:messages']             = 'Mensajes de chat entre el estudiante y el asistente SAIPA';
$string['privacy:metadata:messages:message']     = 'Contenido del mensaje';
$string['privacy:metadata:messages:timecreated'] = 'Hora en que se envió el mensaje';
$string['privacy:metadata:messages:userid']      = 'ID del usuario que envió el mensaje';
$string['privacy:metadata:phonenumber']          = 'Número de teléfono usado para la comunicación por WhatsApp';
$string['privacy:metadata:risk']                 = 'Puntajes de riesgo calculados para los estudiantes';
$string['privacy:metadata:risk:factors']         = 'Objeto JSON con los factores contribuyentes';
$string['privacy:metadata:risk:score']           = 'Puntaje de riesgo calculado (0.0–1.0)';
$string['privacy:metadata:risk:userid']          = 'ID del estudiante';
$string['privacy:metadata:risk_history:risk_level'] = 'The risk level label (low/medium/high)';
$string['privacy:metadata:risk_history:score'] = 'The numeric risk score';
$string['privacy:metadata:risk_history:userid'] = 'The user whose risk was scored';
$string['privacy:metadata:saipa_engine']               = 'SAIPA envía datos al motor de IA para análisis de riesgo y respuestas del asistente.';
$string['privacy:metadata:saipa_engine:message']       = 'Contenido del mensaje enviado al asistente.';
$string['privacy:metadata:saipa_engine:userid']        = 'ID de usuario para identificar al estudiante.';
$string['privacy:metadata:saipa_feedback']             = 'Valoraciones de los alumnos sobre las respuestas del asistente.';
$string['privacy:metadata:saipa_feedback:comment']     = 'Comentario opcional junto a la valoración.';
$string['privacy:metadata:saipa_feedback:rating']      = 'Valoración: positiva (1) o negativa (-1).';
$string['privacy:metadata:saipa_feedback:timecreated'] = 'Fecha y hora de la valoración.';
$string['privacy:metadata:saipa_feedback:userid']      = 'ID del usuario que envió la valoración.';
$string['privacy:metadata:saipa_messages']             = 'Mensajes intercambiados entre el alumno y el asistente SAIPA.';
$string['privacy:metadata:saipa_messages:content']     = 'Texto del mensaje.';
$string['privacy:metadata:saipa_messages:role']        = 'Rol del emisor: usuario o asistente.';
$string['privacy:metadata:saipa_messages:timecreated'] = 'Fecha y hora del mensaje.';
$string['privacy:metadata:saipa_notifications']        = 'Alertas proactivas enviadas a alumnos en riesgo.';
$string['privacy:metadata:saipa_notifications:payload']   = 'Datos incluidos en la alerta.';
$string['privacy:metadata:saipa_notifications:status']    = 'Estado de la alerta (enviada, respondida).';
$string['privacy:metadata:saipa_notifications:template']  = 'Plantilla de alerta utilizada.';
$string['privacy:metadata:saipa_notifications:timesent']  = 'Fecha y hora de envío.';
$string['privacy:metadata:saipa_notifications:userid'] = 'ID del alumno alertado.';
$string['privacy:metadata:saipa_phone_verify']            = 'Verificación de número de teléfono para WhatsApp.';
$string['privacy:metadata:saipa_phone_verify:phone']      = 'Número de teléfono.';
$string['privacy:metadata:saipa_phone_verify:userid']     = 'ID del usuario.';
$string['privacy:metadata:saipa_phone_verify:verified']   = 'Estado de verificación.';
$string['privacy:metadata:saipa_risk_history'] = 'Append-only risk score log for longitudinal research';
$string['privacy:metadata:saipa_risk_scores']             = 'Puntajes de riesgo de abandono calculados por SAIPA.';
$string['privacy:metadata:saipa_risk_scores:factors']     = 'Factores que contribuyeron al puntaje.';
$string['privacy:metadata:saipa_risk_scores:risk_level']  = 'Nivel de riesgo: bajo, medio o alto.';
$string['privacy:metadata:saipa_risk_scores:score']       = 'Puntaje de riesgo (0.0–1.0).';
$string['privacy:metadata:saipa_risk_scores:timecomputed'] = 'Fecha y hora del cálculo.';
$string['privacy:metadata:saipa_risk_scores:userid']      = 'ID del alumno evaluado.';
$string['privacy:metadata:saipa_sessions']                = 'Sesiones de chat entre alumnos y el asistente.';
$string['privacy:metadata:saipa_sessions:courseid']       = 'ID del curso de la sesión.';
$string['privacy:metadata:saipa_sessions:timecreated']    = 'Fecha y hora de inicio de la sesión.';
$string['privacy:metadata:saipa_sessions:userid']         = 'ID del alumno.';
$string['privacy:metadata:saipa_telegram_links']          = 'Vinculación de cuentas de Telegram con usuarios de Moodle.';
$string['privacy:metadata:saipa_telegram_links:confirmed']      = 'Si el vínculo fue confirmado.';
$string['privacy:metadata:saipa_telegram_links:telegram_id']    = 'ID de Telegram del usuario.';
$string['privacy:metadata:saipa_telegram_links:telegram_username'] = 'Nombre de usuario en Telegram.';
$string['privacy:metadata:saipa_telegram_links:userid']         = 'ID del usuario de Moodle.';
$string['privacy:metadata:sessions']             = 'Registros de sesiones de chat SAIPA';
$string['privacy:metadata:sessions:courseid']    = 'Curso al que pertenece la sesión';
$string['privacy:metadata:sessions:userid']      = 'ID del usuario';
$string['privacy:metadata:whatsapp']             = 'Datos enviados al proveedor externo de WhatsApp';
$string['risk_button']              = 'Calcular riesgo';
$string['risk_demo_button']         = 'Simular';
$string['risk_trend_title']     = 'Tendencia de riesgo por semana';
$string['saipa:advisor'] = 'Acceder al panel de asesor';
$string['saipa:chat']   = 'Usar el asistente de chat SAIPA';
$string['saipa:manage'] = 'Administrar la configuración de SAIPA';
$string['saipa:view']   = 'Ver el panel de SAIPA';
$string['saipa:viewall'] = 'Ver datos de todos los estudiantes entre cursos';
$string['settings:channel_both']               = 'Ambos — Telegram y WhatsApp';
$string['settings:channel_none']               = 'Ninguno (mensajería deshabilitada)';
$string['settings:channel_telegram']           = 'Telegram únicamente';
$string['settings:channel_whatsapp']           = 'WhatsApp únicamente';
$string['settings:engine_token']           = 'Token de API';
$string['settings:engine_token_desc']      = 'Token Bearer para autenticar las solicitudes al saipa-engine';
$string['settings:engine_url']             = 'URL del motor SAIPA';
$string['settings:engine_url_desc']        = 'URL del servicio FastAPI saipa-engine (ej. http://host.docker.internal:8052)';
$string['settings:heading_engine']         = 'Conexión con el motor SAIPA';
$string['settings:heading_messaging']          = 'Canales de mensajería';
$string['settings:heading_messaging_desc']     = 'Seleccioná por cuáles aplicaciones SAIPA va a contactar a los estudiantes. '
    . 'Telegram requiere configurar TELEGRAM_BOT_TOKEN en el .env del motor. '
    . 'WhatsApp requiere Evolution API configurada y conectada.';
$string['settings:heading_whatsapp']       = 'Configuración de WhatsApp';
$string['settings:messaging_channel']          = 'Canal de mensajería activo';
$string['settings:messaging_channel_desc']     = 'Elegí qué canales de mensajería van a estar disponibles para los estudiantes en el bloque SAIPA.';
$string['settings:status_page'] = 'SAIPA — Estado de la instalación';
$string['settings:telegram_bot_username']      = 'Usuario del bot de Telegram (sin @)';
$string['settings:telegram_bot_username_desc'] = 'El nombre de usuario del bot de Telegram sin el arroba (ej. saipa_bot). '
    . 'Se usa para construir el enlace de vinculación.';
$string['settings:twilio_from']            = 'Número de origen WhatsApp (Twilio)';
$string['settings:twilio_sid']             = 'SID de cuenta Twilio';
$string['settings:twilio_token']           = 'Token de autenticación Twilio';
$string['settings:whatsapp_provider']      = 'Proveedor de WhatsApp';
$string['settings:whatsapp_provider_desc'] = 'Seleccioná el proveedor de la API de WhatsApp Business (twilio o meta)';
$string['sparkline_messages'] = 'Mensajes diarios (últimos 30 días)';
$string['sparkline_users']    = 'Usuarios activos diarios (últimos 30 días)';
$string['summary_active']    = 'Usuarios SAIPA';
$string['summary_alerts30d'] = 'Alertas (30d)';
$string['summary_enrolled']  = 'Inscriptos';
$string['summary_index']     = 'Índice';
$string['summary_msgs7d']    = 'Mensajes (7d)';
$string['summary_risk']      = 'Riesgo alto';
$string['teacher_back_to_list']     = 'Volver al listado';
$string['teacher_dashboard_title']  = 'SAIPA — Panel Docente';
$string['teacher_students_heading'] = 'Actividad de Estudiantes';
$string['telegram:link_button']    = 'Vincular mi Telegram';
$string['telegram:link_desc']      = 'Vinculá tu cuenta de Telegram para recibir respuestas del asistente SAIPA directamente en la app.';
$string['telegram:link_error']     = 'No se pudo generar el enlace. Intentá nuevamente.';
$string['telegram:link_title']     = 'Vincular Telegram';
$string['telegram:linked_as']      = 'Conectado como @{$a}';
$string['telegram:linked_title']   = 'Telegram vinculado';
$string['telegram:open_dialog']    = 'Abrir diálogo en Telegram';
$string['telegram:polling_msg']    = 'Esperando confirmación...';
$string['telegram:unlink_button']  = 'Desvincular';
$string['whatsapp:confirm_button']   = 'Verificar';
$string['whatsapp:link_desc']        = 'Vinculá tu número de WhatsApp para recibir mensajes del asistente SAIPA.';
$string['whatsapp:link_title']       = 'Vincular WhatsApp';
$string['whatsapp:otp_expired']      = 'El código expiró. Solicitá uno nuevo.';
$string['whatsapp:otp_invalid']      = 'Código incorrecto. Verificá e intentá nuevamente.';
$string['whatsapp:otp_label']        = 'Código de verificación recibido por WhatsApp';
$string['whatsapp:otp_sent']         = 'Código enviado. Chequeá tu WhatsApp e ingresalo acá.';
$string['whatsapp:phone_label']      = 'Tu número de WhatsApp (con código de país, sin +)';
$string['whatsapp:phone_placeholder'] = 'Ej: 5491112345678';
$string['whatsapp:send_error']       = 'No se pudo enviar el código. Verificá tu número e intentá nuevamente.';
$string['whatsapp:send_otp_button']  = 'Enviar código';
$string['whatsapp:unlink_button']    = 'Desvincular';
$string['whatsapp:verified_as']      = 'Número verificado: {$a}';
$string['whatsapp:verified_title']   = 'WhatsApp vinculado';
