// relatorio_turnos.js
document.addEventListener("DOMContentLoaded", function () {
  const reportFiltersForm = document.getElementById("report-filters-form");
  const filtroColaboradorSelect = document.getElementById("filtro-colaborador");
  const reportTableBody = document.querySelector("#report-table tbody");
  const reportSummaryDiv = document.getElementById("report-summary");
  const generateReportButton = document.getElementById(
    "generate-report-button"
  );
  // const exportCsvButton = document.getElementById('export-csv-button'); // Desabilitado por enquanto

  // Tenta usar a função global de script.js, se disponível, ou define uma local
  const buscarColaboradoresGlobal =
    typeof buscarEArmazenarColaboradores === "function"
      ? buscarEArmazenarColaboradores
      : null;
  const showToastGlobal = typeof showToast === "function" ? showToast : alert; // Fallback para alert se showToast não estiver global

  async function carregarColaboradoresParaFiltro() {
    let colaboradores = [];
    if (buscarColaboradoresGlobal) {
      colaboradores = await buscarColaboradoresGlobal();
    } else {
      // Fallback se a função global não estiver disponível (improvável se script.js é carregado antes)
      try {
        const response = await fetch("obter_colaboradores.php");
        const data = await response.json();
        if (data.success && data.colaboradores) {
          colaboradores = data.colaboradores;
        } else {
          showToastGlobal(
            "Falha ao carregar colaboradores para filtro: " +
              (data.message || "Erro desconhecido"),
            "error"
          );
        }
      } catch (e) {
        showToastGlobal(
          "Erro crítico ao carregar colaboradores para filtro: " + e.message,
          "error"
        );
      }
    }

    if (filtroColaboradorSelect) {
      filtroColaboradorSelect.innerHTML =
        '<option value="">Todos os Colaboradores</option>'; // Limpa e adiciona default
      colaboradores.forEach((colab) => {
        const option = document.createElement("option");
        option.value = colab.nome_completo; // Backend filtrará por nome_completo
        option.textContent = colab.nome_completo;
        filtroColaboradorSelect.appendChild(option);
      });
    }
  }

  function exibirDadosRelatorio(turnos, totalHoras, totalTurnos) {
    if (!reportTableBody) return;
    reportTableBody.innerHTML = ""; // Limpa a tabela

    if (turnos && turnos.length > 0) {
      turnos.forEach((turno) => {
        const row = reportTableBody.insertRow();
        row.insertCell().textContent = turno.data_formatada;
        row.insertCell().textContent = turno.colaborador;
        row.insertCell().textContent = turno.hora_inicio_formatada;
        row.insertCell().textContent = turno.hora_fim_formatada;
        row.insertCell().textContent = turno.duracao_formatada; // Ex: "04h30min"
      });
    } else {
      const row = reportTableBody.insertRow();
      const cell = row.insertCell();
      cell.colSpan = 5; // Ajustado para 5 colunas
      cell.textContent =
        "Nenhum turno encontrado para os filtros selecionados.";
      cell.style.textAlign = "center";
    }

    if (reportSummaryDiv) {
      if (totalTurnos > 0) {
        reportSummaryDiv.innerHTML = `<p>Total de Turnos no período: <strong>${totalTurnos}</strong></p>
                                             <p>Total de Horas Trabalhadas: <strong>${totalHoras
                                               .toFixed(2)
                                               .replace(
                                                 ".",
                                                 ","
                                               )}h</strong></p>`;
      } else {
        reportSummaryDiv.innerHTML =
          "<p>Nenhum turno encontrado para exibir o resumo.</p>";
      }
    }
    // if (exportCsvButton) exportCsvButton.style.display = (turnos && turnos.length > 0) ? 'inline-flex' : 'none';
  }

  if (reportFiltersForm) {
    reportFiltersForm.addEventListener("submit", async function (event) {
      event.preventDefault();
      if (generateReportButton) {
        generateReportButton.disabled = true;
        generateReportButton.innerHTML =
          '<i class="fas fa-spinner fa-spin"></i> Gerando...';
      }

      const dataInicio = document.getElementById("filtro-data-inicio").value;
      const dataFim = document.getElementById("filtro-data-fim").value;
      const colaborador = filtroColaboradorSelect
        ? filtroColaboradorSelect.value
        : "";
      const csrfTokenEl = document.getElementById("csrf-token-reports");
      const csrfToken = csrfTokenEl ? csrfTokenEl.value : null;

      if (!dataInicio || !dataFim) {
        showToastGlobal(
          "Por favor, selecione o período (Data Início e Data Fim).",
          "warning"
        );
        if (generateReportButton) {
          generateReportButton.disabled = false;
          generateReportButton.innerHTML =
            '<i class="fas fa-search"></i> Gerar Relatório';
        }
        return;
      }
      if (new Date(dataInicio) > new Date(dataFim)) {
        showToastGlobal(
          "A Data Início não pode ser posterior à Data Fim.",
          "warning"
        );
        if (generateReportButton) {
          generateReportButton.disabled = false;
          generateReportButton.innerHTML =
            '<i class="fas fa-search"></i> Gerar Relatório';
        }
        return;
      }

      const params = new URLSearchParams({
        data_inicio: dataInicio,
        data_fim: dataFim,
        colaborador: colaborador,
        csrf_token: csrfToken, // CSRF token também como parâmetro GET para consistência com POST, ou adaptar backend
      });

      // Ou enviar como POST se preferir para não expor filtros na URL, ajustando o backend
      // const payload = { data_inicio: dataInicio, data_fim: dataFim, colaborador: colaborador, csrf_token: csrfToken};

      try {
        // const response = await fetch('gerar_relatorio_turnos.php', {
        //    method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload)
        // });
        const response = await fetch(
          `gerar_relatorio_turnos.php?${params.toString()}`
        ); // Usando GET por simplicidade
        const data = await response.json();

        if (!response.ok) {
          throw new Error(data.message || `Erro HTTP: ${response.status}`);
        }

        if (data.success) {
          exibirDadosRelatorio(
            data.turnos,
            data.total_geral_horas,
            data.total_turnos
          );
          if (data.csrf_token && csrfTokenEl) {
            // Atualiza o token CSRF da página de relatório
            csrfTokenEl.value = data.csrf_token;
          }
        } else {
          showToastGlobal(
            "Erro ao gerar relatório: " +
              (data.message || "Erro desconhecido."),
            "error"
          );
          exibirDadosRelatorio([], 0, 0);
        }
      } catch (error) {
        console.error("Erro na requisição do relatório:", error);
        showToastGlobal(
          `Erro crítico ao buscar dados do relatório: ${error.message}`,
          "error"
        );
        exibirDadosRelatorio([], 0, 0);
      } finally {
        if (generateReportButton) {
          generateReportButton.disabled = false;
          generateReportButton.innerHTML =
            '<i class="fas fa-search"></i> Gerar Relatório';
        }
      }
    });
  }

  // Inicialização da página de relatórios
  if (filtroColaboradorSelect) {
    // Se estiver na página de relatórios
    carregarColaboradoresParaFiltro();

    // Define datas padrão para os filtros (ex: mês atual)
    const hoje = new Date();
    const primeiroDiaDoMes = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
    const ultimoDiaDoMes = new Date(hoje.getFullYear(), hoje.getMonth() + 1, 0);

    const dataInicioInput = document.getElementById("filtro-data-inicio");
    const dataFimInput = document.getElementById("filtro-data-fim");

    if (dataInicioInput) dataInicioInput.valueAsDate = primeiroDiaDoMes;
    if (dataFimInput) dataFimInput.valueAsDate = ultimoDiaDoMes;
  }

  // if (exportCsvButton) {
  //     exportCsvButton.addEventListener('click', function() {
  //         // Lógica para exportar CSV (pegar dados da tabela ou fazer nova requisição com flag de exportação)
  //         showToastGlobal("Funcionalidade de Exportar CSV a ser implementada.", "info");
  //     });
  // }
});
