<?php
namespace block_ifcare\task;

class process_collection extends \core\task\scheduled_task
{
    public function get_name()
    {
        return get_string('process_collection', 'block_ifcare');
    }

    public function execute()
    {
        global $DB;

        $agora = time();
        mtrace("Iniciando tarefa cron de coleta...");

        $sql = "SELECT c.*
                FROM {ifcare_cadastrocoleta} c
                WHERE :agora >= c.data_inicio 
                AND :agora_fim <= c.data_fim
                AND c.notificar_alunos = 1
                AND c.notificacao_enviada = 0";

        try {
            $coletas = $DB->get_records_sql($sql, [
                'agora' => date('Y-m-d H:i:s', $agora),
                'agora_fim' => date('Y-m-d H:i:s', $agora),
            ]);
        } catch (\dml_exception $e) {
            mtrace("Erro ao buscar coletas: " . $e->getMessage());
            return;
        }

        if (!empty($coletas)) {
            foreach ($coletas as $coleta) {
                mtrace("Processando coleta: " . $coleta->nome);

                $curso = $DB->get_record('course', ['id' => $coleta->curso_id]);

                $this->adicionar_recurso_url($coleta, $curso);

                $this->enviar_notificacao($coleta);

                $DB->set_field('ifcare_cadastrocoleta', 'notificacao_enviada', 1, ['id' => $coleta->id]);

                mtrace("Notificação enviada e coleta processada: " . $coleta->nome);
            }
        } else {
            mtrace("Nenhuma coleta encontrada para notificar.");
        }
    }

    private function adicionar_recurso_url($coleta, $curso)
    {
        global $DB, $USER, $CFG;

        $coleta->curso_id = clean_param($coleta->curso_id, PARAM_INT);
        $section_id = clean_param($coleta->section_id, PARAM_INT);

        require_once($CFG->dirroot . '/course/modlib.php');

        mtrace("Iniciando a adição de recurso URL para a coleta: {$coleta->nome}, Curso ID: {$curso->id}");

        if (!isset($curso->id)) {
            mtrace("Erro: Curso não encontrado ou inválido para adicionar o recurso URL.");
            return;
        }

        mtrace("Obtendo informações do curso (get_fast_modinfo)...");

        $modinfo = get_fast_modinfo($curso->id);
        $sections = $modinfo->get_sections(); 

        if (empty($sections)) {
            mtrace("Nenhuma seção encontrada no curso. Criando recurso na seção zero.");
            $sections[0] = []; 
        }

        $section_id = $coleta->section_id;


        mtrace("Total de seções encontradas: " . count($sections));
        mtrace("Processando a seção especificada: Seção {$section_id}");

        $urlparams = new \stdClass();
        $urlparams->course = $curso->id;
        $urlparams->module = $DB->get_field('modules', 'id', ['name' => 'url']); 

        if (empty($curso) || empty($urlparams->module)) {
            mtrace("Erro ao obter dados do curso ou módulo.");
            return;
        }

        if (!$urlparams->module) {
            mtrace("Erro: Não foi possível encontrar o módulo do tipo 'url' no banco de dados.");
            return;
        }

        mtrace("ID do módulo URL encontrado: {$urlparams->module}");

        $urlparams->modulename = 'url';
        $urlparams->visible = 1;
        $urlparams->format = FORMAT_MOODLE;

        $urlparams->display = 0;  

        $urlparams->completion = 1; 
        $urlparams->completionview = 0; 

        $urlparams->section = $section_id; 
        $urlparams->name = "IFCare - Como você está se sentindo hoje?";
        $data_inicio_formatada = date('d/m/Y H:i', strtotime($coleta->data_inicio));
        $data_fim_formatada = date('d/m/Y H:i', strtotime($coleta->data_fim));

        $urlparams->intro = clean_text("Responda esta coleta <strong>até</strong> {$data_fim_formatada}. Participe e nos ajude a compreender melhor suas emoções!", FORMAT_HTML);
        $urlparams->introformat = FORMAT_HTML;

        $urlparams->showdescription = 1; 
        $urlparams->introformat = FORMAT_HTML;
        $urlparams->externalurl = clean_param("{$CFG->wwwroot}/blocks/ifcare/view.php?coletaid={$coleta->id}", PARAM_URL);
        $urlparams->timemodified = time();

        mtrace("Preparando para adicionar o recurso URL na seção especificada (Seção {$section_id})");
        mtrace("Nome do recurso: {$urlparams->name}");
        mtrace("URL: {$urlparams->externalurl}");

        $cmid = \add_moduleinfo((object) $urlparams, $curso, null);

        if ($cmid) {
            mtrace("Recurso URL adicionado com sucesso na seção {$section_id} para a coleta: {$coleta->nome}");
        } else {
            mtrace("Falha ao adicionar recurso URL na seção {$section_id}.");
        }

        mtrace("Finalizando a adição de recurso URL para a coleta: {$coleta->nome}");
    }


    private function enviar_notificacao($coleta)
    {
        global $DB, $CFG;
        $curso_id = clean_param($coleta->curso_id, PARAM_INT);


        // Busca todas as informações do curso usando o curso_id da coleta
        $curso = $DB->get_record('course', ['id' => $coleta->curso_id]);

        if (!$curso) {
            mtrace("Erro: Curso não encontrado para a coleta {$coleta->nome} (ID do curso: {$coleta->curso_id})");
            return; // Se o curso não foi encontrado, não continua o processamento
        }

        $nome_disciplina = $curso->fullname;

        // Formata a data final da coleta
        $data_fim_formatada = date('d/m/Y H:i', strtotime($coleta->data_fim));

        // Busca os alunos matriculados no curso
        $enrols = $DB->get_records_sql("
            SELECT u.id, u.email
            FROM {user_enrolments} ue
            JOIN {enrol} e ON ue.enrolid = e.id
            JOIN {user} u ON ue.userid = u.id
            WHERE e.courseid = :courseid",
            ['courseid' => $coleta->curso_id]
        );

        foreach ($enrols as $usuario) {
            $eventdata = new \core\message\message();
            $eventdata->component = 'block_ifcare';
            $eventdata->name = 'created_collection';
            $eventdata->userfrom = \core_user::get_noreply_user();
            $eventdata->userto = $usuario->id;
            $eventdata->subject = "IFCare - Compartilhe suas emoções sobre a disciplina de {$nome_disciplina}";
            $eventdata->fullmessage = "Olá! Uma coleta de emoções para a disciplina {$nome_disciplina} foi criada e está disponível até {$data_fim_formatada} para você responder. Sua opinião é muito importante. Por favor, participe!";
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            $eventdata->fullmessagehtml = "<p>Olá!</p>
            <p>Uma coleta de emoções para a disciplina <strong>{$nome_disciplina}</strong> foi criada e está disponível até <strong>{$data_fim_formatada}</strong> para você responder.</p>
            <p>Sua opinião é muito importante para nós. <a href='{$CFG->wwwroot}/blocks/ifcare/view.php?coletaid={$coleta->id}'>Clique aqui</a> para compartilhar suas emoções e nos ajudar a melhorar sua experiência de aprendizado.</p>";
            $eventdata->smallmessage = "Uma coleta de emoções para a disciplina {$nome_disciplina} foi criada e está disponível até {$data_fim_formatada}. <a href='{$CFG->wwwroot}/blocks/ifcare/view.php?coletaid={$coleta->id}'>Clique aqui</a> para participar.";
            $eventdata->notification = 1;

            // Envia a notificação
            message_send($eventdata);
        }

    }


}
