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
 * Brazilian Portuguese language strings for local_saipa.
 *
 * @package    local_saipa
 * @copyright  2026 Schaller & Ponce <dev@schaller-ponce.com.ar>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */$string['engine_error'] = 'Erro do motor SAIPA';
$string['healthcheck:fail']    = 'Não é possível alcançar o motor SAIPA na URL configurada';
$string['healthcheck:heading'] = 'Teste de conexão com o motor';
$string['healthcheck:ok']      = 'O motor SAIPA está acessível';
$string['notenrolled']              = 'O usuário especificado não está matriculado neste curso.';
$string['pluginname'] = 'SAIPA - Sistema de Intervenção Pedagógica Adaptativa';
$string['privacy:metadata:message']              = 'Conteúdo da mensagem de WhatsApp';
$string['privacy:metadata:messages']             = 'Mensagens de chat entre o estudante e o assistente SAIPA';
$string['privacy:metadata:messages:message']     = 'Conteúdo da mensagem';
$string['privacy:metadata:messages:timecreated'] = 'Hora em que a mensagem foi enviada';
$string['privacy:metadata:messages:userid']      = 'ID do usuário que enviou a mensagem';
$string['privacy:metadata:phonenumber']          = 'Número de telefone usado para comunicação via WhatsApp';
$string['privacy:metadata:risk']                 = 'Pontuações de risco calculadas para os estudantes';
$string['privacy:metadata:risk:factors']         = 'Objeto JSON com os fatores contribuintes';
$string['privacy:metadata:risk:score']           = 'Pontuação de risco calculada (0.0–1.0)';
$string['privacy:metadata:risk:userid']          = 'ID do estudante';
$string['privacy:metadata:sessions']             = 'Registros de sessões de chat SAIPA';
$string['privacy:metadata:sessions:courseid']    = 'Curso ao qual a sessão pertence';
$string['privacy:metadata:sessions:userid']      = 'ID do usuário';
$string['privacy:metadata:whatsapp']             = 'Dados enviados ao provedor externo de WhatsApp';
$string['saipa:chat']   = 'Usar o assistente de chat SAIPA';
$string['saipa:manage'] = 'Gerenciar configurações do SAIPA';
$string['saipa:view']   = 'Ver painel do SAIPA';
$string['settings:engine_token']           = 'Token de API';
$string['settings:engine_token_desc']      = 'Token Bearer para autenticar requisições ao saipa-engine';
$string['settings:engine_url']             = 'URL do motor SAIPA';
$string['settings:engine_url_desc']        = 'URL do serviço FastAPI saipa-engine (ex. http://host.docker.internal:8052)';
$string['settings:heading_engine']         = 'Conexão com o motor SAIPA';
$string['settings:heading_whatsapp']       = 'Configuração do WhatsApp';
$string['settings:twilio_from']            = 'Número de origem WhatsApp (Twilio)';
$string['settings:twilio_sid']             = 'SID da conta Twilio';
$string['settings:twilio_token']           = 'Token de autenticação Twilio';
$string['settings:whatsapp_provider']      = 'Provedor de WhatsApp';
$string['settings:whatsapp_provider_desc'] = 'Selecione o provedor da API do WhatsApp Business (twilio ou meta)';
$string['teacher_back_to_list']     = 'Voltar à lista';
$string['teacher_dashboard_title']  = 'SAIPA — Painel do Professor';
$string['teacher_students_heading'] = 'Atividade dos Estudantes';
