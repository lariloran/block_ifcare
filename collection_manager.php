<?php
class collection_manager
{

    public function get_coletas_by_professor($professor_id)
    {
        global $DB;
    
        $sql = "SELECT id, nome, data_inicio, data_fim, descricao, curso_id, notificar_alunos, receber_alerta , resource_id_atrelado, section_id, data_criacao
                FROM {ifcare_cadastrocoleta} 
                WHERE professor_id = :professor_id
                ORDER BY data_criacao DESC";
    
        $params = ['professor_id' => $professor_id];
    
        return $DB->get_records_sql($sql, $params);
    }
    

    private function obter_perguntas_associadas($coleta_id)
    {
        global $DB;

        $sql = "SELECT pergunta.id AS pergunta_id, 
                       classe.nome_classe, 
                       emocao.nome AS emocao_nome, 
                       pergunta.pergunta_texto
                FROM {ifcare_associacao_classe_emocao_coleta} assoc
                JOIN {ifcare_classeaeq} classe ON classe.id = assoc.classeaeq_id
                JOIN {ifcare_emocao} emocao ON emocao.id = assoc.emocao_id
                JOIN {ifcare_pergunta} pergunta ON pergunta.emocao_id = emocao.id
                WHERE assoc.cadastrocoleta_id = :coleta_id";

        $params = ['coleta_id' => $coleta_id];
        return $DB->get_records_sql($sql, $params);
    }

    public function excluir_coleta($coleta_id)
    {
        global $DB, $CFG;
    
        $transaction = $DB->start_delegated_transaction();
    
        try {
            $resource_id_instance = $DB->get_field('ifcare_cadastrocoleta', 'resource_id', ['id' => $coleta_id]);

            if ($resource_id_instance) {
                // Busca diretamente o ID em `course_modules` onde `instance` é igual ao `resource_id`
                $course_module_id = $DB->get_field('course_modules', 'id', ['instance' => $resource_id_instance]);
            
                if ($course_module_id) {
                    require_once($CFG->dirroot . '/course/lib.php');
                    course_delete_module($course_module_id);
                }
            }
            
    
            $DB->delete_records('ifcare_resposta', ['coleta_id' => $coleta_id]);
    
            $DB->delete_records('ifcare_associacao_classe_emocao_coleta', ['cadastrocoleta_id' => $coleta_id]);
    
            $DB->delete_records('ifcare_cadastrocoleta', ['id' => $coleta_id]);
    
            $transaction->allow_commit();
        } catch (Exception $e) {
            $transaction->rollback($e);
            throw $e;
        }
    }
    
    public function listar_coletas($professor_id)
    {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $html = '<style>
            .accordion {
                max-width: 700px;
                margin: 20px auto;
                border-radius: 8px;
                overflow: hidden;
                box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
                background: #fff;
            }
            .accordion-item {
                border-bottom: 1px solid #ddd;
            }
            .accordion-header {
                background: #f0f0f0;
                color: #333;
                padding: 12px;
                cursor: pointer;
                transition: background 0.3s ease;
            }
            .accordion-header:hover {
                background: #d8f3dc;
            }
            .accordion-button {
                width: 100%;
                text-align: left;
                border: none;
                background: none;
                font-size: 15px;
                color: #333;
            }
            .accordion-body {
                padding: 12px;
                background: #f9f9f9;
                transition: max-height 0.3s ease;
            }
            .accordion-body p {
                margin: 8px 0;
                font-size: 14px;
                color: #333;
            }
            .btn-coleta {
                display: inline-flex;
                align-items: center;
                background-color: #4CAF50;
                color: white;
                padding: 10px 20px;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                transition: background-color 0.3s ease;
            }
            .btn-coleta:hover {
                background-color: #45a049;
            }
.modal {
    display: none;
    position: fixed;
    z-index: 1;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0, 0, 0, 0.5); /* Fundo semi-transparente */
}

.modal-content {
    width: 90%;
    max-width: 600px;
    margin: 10% auto;
    padding: 20px;
    border-radius: 10px;
}

            .close {
                color: #aaa;
                float: right;
                font-size: 28px;
                font-weight: bold;
            }
            .close:hover,
            .close:focus {
                color: black;
                text-decoration: none;
                cursor: pointer;
            }
.card-list {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    justify-content: center;
    margin-top: 20px;
}

.card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    padding: 16px; /* Reduz o padding para economizar espaço */
    max-width: 250px; /* Diminui a largura máxima */
    min-height: 180px; /* Ajusta a altura mínima */
    text-align: left;
    transition: transform 0.3s;
    font-size: 14px; /* Reduz o tamanho do texto */
    line-height: 1.4; /* Ajusta o espaçamento entre linhas */
}

.card:hover {
    transform: scale(1.05);
}

.card h3 {
    font-size: 16px; /* Reduz o tamanho do título */
    margin-bottom: 8px;
    color: #333;
    
}

.card p {
    margin: 4px 0; /* Diminui o espaçamento entre os parágrafos */
    font-size: 13px; /* Reduz o texto das informações */
    color: #555;
     white-space: nowrap;
     overflow: hidden; /* Oculta o texto excedente */
    text-overflow: ellipsis; /* Adiciona reticências no final do texto */
}

.card .btn-coleta {
    width: 100%; /* Faz o botão ocupar toda a largura do card */
    text-align: center; /* Centraliza o texto no botão */
    display: block; /* Garante que o botão seja um elemento de bloco */
    margin-top: auto; /* Faz o botão alinhar-se ao final do card */
    padding: 8px 12px; /* Reduz o padding para diminuir a altura */
    font-size: 14px; /* Ajusta o tamanho do texto para harmonizar */
    border-radius: 4px; /* Mantém bordas arredondadas, mas sutis */
}


            .card .btn-coleta:hover {
                background-color: #45a049;
            }
              .filter-container-coleta {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 15px;
        margin: 20px 0;
        padding: 10px 15px;
        background: #f9f9f9;
        border: 1px solid #ddd;
        border-radius: 8px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        flex-wrap: wrap; /* Permite que os elementos se ajustem em telas menores */
    }
    .filter-container-coleta label {
        font-size: 16px;
        color: #333;
        font-weight: bold;
    }
    .filter-container-coleta input[type="text"] {
        padding: 8px 12px;
        font-size: 16px;
        border: 1px solid #ddd;
        border-radius: 5px;
        transition: border-color 0.3s;
        background-color: #fff;
        color: #333;
        flex-grow: 1; /* Faz o input crescer para ocupar mais espaço */
        max-width: 250px; /* Limita o tamanho máximo do input */
    }
    .filter-container-coleta input[type="text"]:focus {
        border-color: #4CAF50;
        outline: none;
    }
    .filter-container-coleta select {
        padding: 8px 12px;
        font-size: 16px;
        border: 1px solid #ddd;
        border-radius: 5px;
        transition: border-color 0.3s;
        background-color: #fff;
        color: #333;
    }
    .filter-container-coleta select:focus {
        border-color: #4CAF50;
        outline: none;
    }
    .filter-container-coleta button {
        padding: 8px 16px;
        font-size: 16px;
        color: white;
        background-color: #4CAF50;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        transition: background-color 0.3s, transform 0.2s;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    .filter-container-coleta button:hover {
        background-color: #45a049;
    }
    .filter-container-coleta button:active {
        transform: scale(0.98);
    }
    .filter-container-coleta button .icon {
        font-size: 14px;
    }
                .btn-coleta,
.btn-coleta-ativo {
    display: inline-flex;
    align-items: center;
    background-color: #4CAF50;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    transition: background-color 0.3s ease;
}
.btn-coleta-ativo:hover {
    background-color: #45a049;
}


.pagination-controls {
    position: relative; /* Ajuste conforme necessário */
    text-align: center;
    margin: 20px 0;
}

.pagination-controls button {
    margin: 5px;
    padding: 10px 15px;
    background-color: #4CAF50;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    transition: background-color 0.3s;
}

.pagination-controls button:hover {
    background-color: #45a049;
}

.pagination-controls button.btn-coleta-ativo {
    background-color: #2E7D32; /* Cor diferenciada para página ativa */
    cursor: default;
}
        </style>';


        $html .= '<div class="filter-container-coleta">
    <label for="searchBox"><strong>Buscar:</strong></label>
    <input type="text" id="searchBox" placeholder="Buscar por nome, disciplina, descrição, tipo de recurso..." onkeyup="filtrarColetas()">

    <label for="orderBy"><strong>Ordenar por:</strong></label>
    <select id="orderBy" onchange="ordenarColetas()">
    <option value="data_criacao">Data de Criação</option>

        <option value="nome">Nome da Coleta</option>
        <option value="data_inicio">Data de Início</option>
        <option value="data_fim">Data de Fim</option>
        <option value="curso_nome">Disciplina</option>
    </select>

    <button id="orderDirection" onclick="toggleOrderDirection()">Ascendente <i class="icon fa fa-arrow-up"></i></button>

    <label for="pageSize"><strong>Exibir:</strong></label>
    <select id="pageSize" onchange="atualizarPaginacao()">
        <option value="5">5 por página</option>
        <option value="10" selected>10 por página</option>
        <option value="15">15 por página</option>
        <option value="20">20 por página</option>
    </select>
</div>
';

        $html .= '<div class="card-list" id="coletasContainer">';

        $html .= '<div class="card" style="text-align: center; cursor: pointer;" onclick="window.location.href=\'' . new moodle_url('/blocks/ifcare/register.php') . '\'">
        <h3 style="font-size: 50px; color: #4CAF50; margin: 20px 0;">
            <i class="fa fa-plus-circle"></i>
        </h3>
        <p style="font-size: 18px; font-weight: bold; color: #333;">Nova Coleta</p>
      </div>
      
      ';

        $coletas = $this->get_coletas_by_professor($professor_id);

        if (!empty($coletas)) {
            foreach ($coletas as $coleta) {
                $curso = $DB->get_record('course', ['id' => $coleta->curso_id], 'fullname');
                $curso_nome = $curso ? format_string($curso->fullname) : 'Disciplina não encontrada';
                $coleta->curso_nome = $curso_nome;
            
                $resource_info = '--';
                $resource_name = '--';
                $section_name = '--';
            
                $module = $DB->get_record('course_modules', ['id' => $coleta->resource_id_atrelado], 'module');
                if ($module) {
                    $mod_info = $DB->get_record('modules', ['id' => $module->module], 'name');
                    if ($mod_info) {
                        $resource_info = ucfirst($mod_info->name);
                    }
                    $resource_name_record = $DB->get_record('course_modules', ['id' => $coleta->resource_id_atrelado], 'id, instance');
                    if ($resource_name_record) {
                        $resource_name = $DB->get_field($mod_info->name, 'name', ['id' => $resource_name_record->instance]);
                    }
                }
            
                $section_name = get_section_name($coleta->curso_id, $coleta->section_id);

                
                $coleta->recurso_nome = $resource_info;
                $coleta->resource_name = $resource_name;
                $coleta->section_name = $section_name;
            
                $html .= '<div class="card" 
                data-id="' . $coleta->id . '" 
                data-nome="' . format_string($coleta->nome) . '" 
                data-data_inicio="' . $coleta->data_inicio . '" 
                data-data_fim="' . $coleta->data_fim . '" 
                    data-data_criacao="' . $coleta->data_criacao . '" 

                data-curso_nome="' . $curso_nome . '" 
                data-recurso_nome="' . $coleta->recurso_nome . '" 
                data-resource_name="' . format_string($coleta->resource_name) . '" 
                data-section_name="' . format_string($coleta->section_name) . '">
               <h3>' . format_string($coleta->nome) . '</h3>
               <p><strong>Disciplina:</strong> ' . $curso_nome . '</p>
               <p><strong>Data de Início:</strong> ' . date('d/m/Y H:i', strtotime($coleta->data_inicio)) . '</p>
               <p><strong>Data de Fim:</strong> ' . date('d/m/Y H:i', strtotime($coleta->data_fim)) . '</p>
               <button class="btn-coleta" onclick="abrirModal(' . $coleta->id . ')"><i class="fa fa-info-circle"></i> Detalhes</button>
               
               
             </div>';
    
            }
                    }


        $html .= '</div>';

        $html .= '<div id="paginationControls" class="pagination-controls"></div>'; // Mova o container para o final

        $html .= '<script>const coletasData = ' . json_encode(array_values($coletas)) . ';</script>';
        $html .= '<script>
            let isAscending = true;

            let currentPage = 1;

function atualizarPaginacao() {
    currentPage = 1; // Reseta para a primeira página ao mudar o tamanho
    renderizarPaginacao();
}

function renderizarPaginacao() {
    const pageSize = parseInt(document.getElementById("pageSize").value);
    const cards = Array.from(document.querySelectorAll("#coletasContainer .card"))
        .filter(card => card.hasAttribute("data-id")); // Exclui o card de Nova Coleta da paginação
    const totalCards = cards.length;
    const totalPages = Math.ceil(totalCards / pageSize);

    // Oculta todos os cards exceto o card de Nova Coleta
    document.querySelectorAll("#coletasContainer .card").forEach(card => {
        if (!card.hasAttribute("data-id")) {
            card.style.display = "block"; // Mantém o card de Nova Coleta visível
        } else {
            card.style.display = "none"; // Oculta os demais cards inicialmente
        }
    });

    // Exibe os cards da página atual
    const start = (currentPage - 1) * pageSize;
    const end = start + pageSize;

    cards.slice(start, end).forEach(card => {
        card.style.display = "block"; // Exibe os cards relevantes da página atual
    });

    // Renderiza os controles de paginação
    renderizarControlesPaginacao(totalPages);
}


function renderizarControlesPaginacao(totalPages) {
    const container = document.getElementById("paginationControls");
    if (!container) return;

    container.innerHTML = ""; // Limpa os controles anteriores

    // Adiciona os botões de página
    for (let i = 1; i <= totalPages; i++) {
        const button = document.createElement("button");
        button.textContent = i;
        button.classList.add("btn-coleta");
        if (i === currentPage) button.classList.add("btn-coleta-ativo");
        button.addEventListener("click", () => {
            currentPage = i;
            renderizarPaginacao();
        });
        container.appendChild(button);
    }
}


            document.addEventListener("DOMContentLoaded", function () {
    renderizarPaginacao(); // Renderiza a paginação ao carregar a página
});
            function toggleOrderDirection() {
                isAscending = !isAscending;
                const button = document.getElementById("orderDirection");
                button.innerText = isAscending ? "Ascendente " : "Descendente ";
                let icon = button.querySelector(".icon");
                if (!icon) {
                    icon = document.createElement("i");
                    icon.classList.add("icon");
                    button.appendChild(icon);
                }
                icon.className = isAscending ? "icon fa fa-arrow-up" : "icon fa fa-arrow-down";
                ordenarColetas();
            }

function ordenarColetas() {
    const orderBy = document.getElementById("orderBy").value;
    const container = document.getElementById("coletasContainer");
    const cards = Array.from(container.getElementsByClassName("card"));

    cards.sort((a, b) => {
        let valA = a.getAttribute("data-" + orderBy) || "";
        let valB = b.getAttribute("data-" + orderBy) || "";

if (orderBy === "data_inicio" || orderBy === "data_fim" || orderBy === "data_criacao") {
    valA = new Date(valA);
    valB = new Date(valB);
}
 else {
            // Transforma valores para lower case para comparação consistente de strings
            valA = valA.toString().toLowerCase();
            valB = valB.toString().toLowerCase();
        }

        if (isAscending) {
            return valA < valB ? -1 : valA > valB ? 1 : 0;
        } else {
            return valA > valB ? -1 : valA < valB ? 1 : 0;
        }
    });

    // Limpa o container e reaplica os cards na nova ordem
    container.innerHTML = "";
    cards.forEach(card => container.appendChild(card));

    renderizarPaginacao();
}

function filtrarColetas() {
    const searchTerm = document.getElementById("searchBox").value.toLowerCase();
    const container = document.getElementById("coletasContainer");
    const cards = Array.from(container.getElementsByClassName("card"));

    cards.forEach(card => {
        const nome = card.getAttribute("data-nome")?.toLowerCase() || "";
        const cursoNome = card.getAttribute("data-curso_nome")?.toLowerCase() || "";
        const descricao = card.getAttribute("data-descricao")?.toLowerCase() || "";
        const recursoNome = card.getAttribute("data-recurso_nome")?.toLowerCase() || "";

        const matches = nome.includes(searchTerm) || 
                        cursoNome.includes(searchTerm) || 
                        descricao.includes(searchTerm) || 
                        recursoNome.includes(searchTerm);

        // Usa `visibility: hidden` para manter o layout e evitar lacunas
        card.style.visibility = matches ? "visible" : "hidden";
        card.style.position = matches ? "static" : "absolute";
    });

    renderizarPaginacao();
}




            function abrirModal(coletaId) {
                const coleta = coletasData.find(c => c.id == coletaId);
        
                document.getElementById("modalColetaNome").textContent = coleta.nome;
                document.getElementById("modalColetaDescricao").textContent = coleta.descricao ? coleta.descricao : "--";
                document.getElementById("modalColetaDisciplina").textContent = coleta.curso_nome ? coleta.curso_nome : "Disciplina não encontrada";
                document.getElementById("modalColetaInicio").textContent = new Date(coleta.data_inicio).toLocaleString();
                document.getElementById("modalColetaFim").textContent = new Date(coleta.data_fim).toLocaleString();
                document.getElementById("modalNotificarAlunos").textContent = coleta.notificar_alunos == 1 ? "Sim" : "Não";
                document.getElementById("modalReceberAlerta").textContent = coleta.receber_alerta == 1 ? "Sim" : "Não";
                document.getElementById("modalResourceName").textContent = coleta.resource_name;
                document.getElementById("modalSectionName").textContent = coleta.section_name;

                const coletaUrl = `${M.cfg.wwwroot}/blocks/ifcare/view.php?coletaid=${coleta.id}`;
                const modalColetaUrlElement = document.getElementById("modalColetaUrl");
                modalColetaUrlElement.href = coletaUrl;
                modalColetaUrlElement.textContent = coleta.nome;

                document.getElementById("downloadCSV").setAttribute("data-id", coleta.id);
                document.getElementById("downloadJSON").setAttribute("data-id", coleta.id);
        
                const modal = document.getElementById("coletaModal");
    modal.style.display = "block";
                    adicionarEventosFechamento(modal);

            }
    
function adicionarEventosFechamento(modal) {
    // Fecha o modal ao clicar no "x"
    modal.querySelector(".close").onclick = function () {
        modal.style.display = "none";
    };

    // Fecha o modal ao clicar fora do conteúdo
    modal.addEventListener("click", function (event) {
        if (event.target === modal) {
            modal.style.display = "none";
        }
    });
}

                    document.getElementById("downloadCSV").onclick = function() {
            const coletaId = this.getAttribute("data-id");
            const downloadUrl = "download.php?coleta_id=" + coletaId + "&format=csv";
            window.location.href = downloadUrl;
        };

        document.getElementById("downloadJSON").onclick = function() {
            const coletaId = this.getAttribute("data-id");
            const downloadUrl = "download.php?coleta_id=" + coletaId + "&format=json";
            window.location.href = downloadUrl;
        };

  
let coletaIdParaExclusao = null;

function abrirGrafico() {
    const coletaId = document.getElementById("modalColetaUrl").getAttribute("href").split("=").pop();
    window.location.href = M.cfg.wwwroot + "/blocks/ifcare/report.php?coletaid=" + coletaId;
}

function confirmarExclusao() {
    const coletaNome = document.getElementById("modalColetaNome").textContent;
    document.getElementById("confirmColetaNome").textContent = coletaNome;
    coletaIdParaExclusao = document.getElementById("modalColetaUrl").getAttribute("href").split("=").pop();
    document.getElementById("confirmDeleteModal").style.display = "block";
}

function fecharModalDetalhe() {
    document.getElementById("coletaModal").style.display = "none";
}

function fecharConfirmacao() {
    document.getElementById("confirmDeleteModal").style.display = "none";
}

function excluirColetaConfirmado() {
    fecharConfirmacao();

    $.ajax({
        url: M.cfg.wwwroot + "/blocks/ifcare/delete_collection.php",
        type: "POST",
        data: { coleta_id: coletaIdParaExclusao },
        success: function(response) {
            alert("Coleta excluída com sucesso!");

            // Redireciona para a página inicial (index)
            window.location.href = M.cfg.wwwroot + "/blocks/ifcare/index.php";
        },
        error: function() {
            alert("Erro ao excluir a coleta.");
        }
    });
}



window.onclick = function(event) {
    if (event.target == document.getElementById("confirmDeleteModal")) {
        fecharConfirmacao();
    }
}

function editarColeta() {
    const coletaId = document.getElementById("modalColetaUrl").getAttribute("href").split("=").pop();
    window.location.href = M.cfg.wwwroot + "/blocks/ifcare/edit.php?coletaid=" + coletaId;  // Redireciona para edit_form.php com o ID da coleta
}

        </script>';

        return $html;
    }



    public function download_csv($coleta_id)
    {
        global $DB;

        $sql = "SELECT id, nome, data_inicio, data_fim, descricao, curso_id, notificar_alunos, receber_alerta 
                FROM {ifcare_cadastrocoleta} 
                WHERE id = :coleta_id";
        $params = ['coleta_id' => $coleta_id];
        $coleta = $DB->get_record_sql($sql, $params);

        $curso = $DB->get_record('course', ['id' => $coleta->curso_id], 'fullname');
        $curso_nome = $curso ? format_string($curso->fullname) : 'Disciplina não encontrada';

        if (!$coleta) {
            echo "Coleta não encontrada.";
            return;
        }

        $perguntas = $this->obter_perguntas_associadas($coleta_id);


        $sql_respostas = "SELECT r.id, a.username AS usuario, a.email, p.id AS pergunta_id, p.pergunta_texto, r.resposta, r.data_resposta, 
        ra.roleid, role.shortname AS role_name
        FROM {ifcare_resposta} r
        JOIN {user} a ON a.id = r.usuario_id
        JOIN {ifcare_pergunta} p ON p.id = r.pergunta_id
        JOIN {role_assignments} ra ON ra.userid = a.id
        JOIN {role} role ON role.id = ra.roleid
        WHERE r.coleta_id = :coleta_id
        AND ra.contextid = :contextid";

        $context = context_course::instance($coleta->curso_id);
        $params['contextid'] = $context->id;

        $respostas = $DB->get_records_sql($sql_respostas, $params);

        ob_clean();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . mb_convert_encoding($coleta->nome, 'UTF-8') . '.csv"');

        $output = fopen('php://output', 'w');
        fputs($output, "\xEF\xBB\xBF");

        fputcsv($output, ['Nome', 'Data de Início', 'Data de Fim', 'Descrição', 'Disciplina', 'Notificar Aluno', 'Receber Alerta']);
        fputcsv($output, [
            mb_convert_encoding($coleta->nome, 'UTF-8'),
            date('d/m/Y H:i', strtotime($coleta->data_inicio)),
            date('d/m/Y H:i', strtotime($coleta->data_fim)),
            mb_convert_encoding($coleta->descricao, 'UTF-8'),
            $curso_nome,
            $coleta->notificar_alunos ? 'Sim' : 'Não',
            $coleta->receber_alerta ? 'Sim' : 'Não'
        ]);

        fputcsv($output, ['ID da Pergunta', 'Classe AEQ', 'Emoção', 'Pergunta']);
        foreach ($perguntas as $pergunta) {
            fputcsv($output, [
                $pergunta->pergunta_id,
                mb_convert_encoding($pergunta->nome_classe, 'UTF-8'),
                mb_convert_encoding($pergunta->emocao_nome, 'UTF-8'),
                mb_convert_encoding($pergunta->pergunta_texto, 'UTF-8')
            ]);
        }

        fputcsv($output, ['Usuario', 'Email', 'Role', 'ID da Pergunta', 'Resposta', 'Data de Resposta']);
        foreach ($respostas as $resposta) {
            fputcsv($output, [
                mb_convert_encoding($resposta->usuario, 'UTF-8'),
                mb_convert_encoding($resposta->email, 'UTF-8'),
                mb_convert_encoding($resposta->role_name, 'UTF-8'),
                $resposta->pergunta_id, 
                $resposta->resposta, 
                date('d/m/Y H:i', strtotime($resposta->data_resposta))
            ]);
        }

        fclose($output);
        exit;
    }

    public function download_json($coleta_id)
    {
        global $DB;

        $sql = "SELECT nome, data_inicio, data_fim, descricao, curso_id, notificar_alunos, receber_alerta 
                FROM {ifcare_cadastrocoleta} 
                WHERE id = :coleta_id";

        $params = ['coleta_id' => $coleta_id];
        $coleta = $DB->get_record_sql($sql, $params);

        if (!$coleta) {
            echo "Coleta não encontrada.";
            return;
        }

        $curso = $DB->get_record('course', ['id' => $coleta->curso_id], 'fullname');
        $curso_nome = $curso ? format_string($curso->fullname) : 'Disciplina não encontrada';

        $perguntas = $this->obter_perguntas_associadas($coleta_id);

        $context = context_course::instance($coleta->curso_id);

        $sql_respostas = "SELECT r.id, a.username AS usuario, a.email, p.id AS pergunta_id, p.pergunta_texto, r.resposta, r.data_resposta, 
                          ra.roleid, role.shortname AS role_name
                          FROM {ifcare_resposta} r
                          JOIN {user} a ON a.id = r.usuario_id
                          JOIN {ifcare_pergunta} p ON p.id = r.pergunta_id
                          JOIN {role_assignments} ra ON ra.userid = a.id
                          JOIN {role} role ON role.id = ra.roleid
                          WHERE r.coleta_id = :coleta_id
                          AND ra.contextid = :contextid";

        $params['contextid'] = $context->id;

        $respostas = $DB->get_records_sql($sql_respostas, $params);

        ob_clean();

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $coleta->nome . '.json"');

        $coleta_data = [
            'nome' => $coleta->nome,
            'data_inicio' => $coleta->data_inicio,
            'data_fim' => $coleta->data_fim,
            'descricao' => $coleta->descricao,
            'curso_nome' => $curso_nome,
            'notificar_alunos' => $coleta->notificar_alunos ? 'Sim' : 'Não',
            'receber_alerta' => $coleta->receber_alerta ? 'Sim' : 'Não',
            'perguntas' => [],
            'respostas' => []
        ];

        foreach ($perguntas as $pergunta) {
            $coleta_data['perguntas'][] = [
                'id' => $pergunta->pergunta_id,
                'classe_aeq' => $pergunta->nome_classe,
                'emocao' => $pergunta->emocao_nome,
                'texto_pergunta' => $pergunta->pergunta_texto
            ];
        }

        foreach ($respostas as $resposta) {
            $coleta_data['respostas'][] = [
                'usuario' => $resposta->usuario,
                'email' => $resposta->email,
                'funcao' => $resposta->role_name, 
                'id_pergunta' => $resposta->pergunta_id,
                'resposta' => $resposta->resposta,
                'data_resposta' => $resposta->data_resposta
            ];
        }

        echo json_encode($coleta_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }



}
?>

<div id="coletaModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2 id="modalColetaNome"></h2>
        <p><strong>Preview Coleta:</strong> <a id="modalColetaUrl" href="#" target="_blank">Link da Coleta</a></p>
        <p><strong>Disciplina:</strong> <span id="modalColetaDisciplina"></span></p>
        <p><strong>Data de Início:</strong> <span id="modalColetaInicio"></span></p>
        <p><strong>Data de Fim:</strong> <span id="modalColetaFim"></span></p>
        <p><strong>Nome da Seção Vinculada:</strong> <span id="modalSectionName"></span></p>
        <p><strong>Nome da Atividade/Recurso Vinculado:</strong> <span id="modalResourceName"></span></p>
        <p><strong>Notificar Aluno:</strong> <span id="modalNotificarAlunos"></span></p>
        <p><strong>Receber Alerta:</strong> <span id="modalReceberAlerta"></span></p>
        <p><strong>Descrição:</strong> <span id="modalColetaDescricao"></span></p>


        <div class="button-group">
            <button id="downloadCSV" class="btn-coleta btn-coleta-secondary">
                <i class="fa fa-file-csv"></i> Baixar CSV
            </button>
            <button id="downloadJSON" class="btn-coleta btn-coleta-secondary">
                <i class="fa fa-file-code"></i> Baixar JSON
            </button>
            <button id="editarColeta" class="btn-coleta btn-coleta-secondary" onclick="editarColeta()">
                <i class="fa fa-edit"></i> Editar
            </button>
            <button id="deleteColeta" class="btn-coleta btn-coleta-secondary" onclick="confirmarExclusao()">
                <i class="fa fa-trash"></i> Excluir
            </button>
            <button id="graficoColeta" class="btn-coleta btn-coleta-secondary" onclick="abrirGrafico()">
                <i class="fa fa-chart-bar"></i> Gráficos
            </button>
        </div>
    </div>
</div>

<div id="confirmDeleteModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="fecharConfirmacao()">&times;</span>
        <h2>Confirmação de Exclusão</h2>
        <p>Tem certeza de que deseja excluir a coleta "<span id="confirmColetaNome"></span>"? Todos os dados, <strong>incluindo as respostas dos alunos</strong>, serão removidos <strong>PERMANENTEMENTE</strong>. Essa ação é irreversível.</p>
        <div class="button-group">
    <button class="btn-coleta btn-coleta-secondary" onclick="excluirColetaConfirmado()">Sim, excluir</button>
    <button class="btn-coleta btn-coleta-secondary" onclick="fecharConfirmacao()">Cancelar</button>
</div>

    </div>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>