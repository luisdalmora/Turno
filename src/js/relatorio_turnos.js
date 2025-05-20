// src/js/relatorio_turnos.js
// Importa as funções necessárias do módulo utils.js
// O caminho './modules/utils.js' assume que relatorio_turnos.js está em src/js/
import { showToast, buscarEArmazenarColaboradores, popularSelectColaborador } from './modules/utils.js';

document.addEventListener("DOMContentLoaded", function () {
  console.log("[DEBUG] relatorio_turnos.js: DOMContentLoaded");

  const reportFiltersForm = document.getElementById("report-filters-form");
  const filtroColaboradorSelect = document.getElementById("filtro-colaborador");
  const reportTableBody = document.querySelector("#report-table tbody");
  const reportSummaryDiv = document.getElementById("report-summary");
  const generateReportButton = document.getElementById("generate-report-button");
  const csrfTokenReportPage = document.getElementById("csrf-token-reports"); // Já presente no HTML da página

  async function carregarColaboradoresParaFiltroRelatorio() {
    console.log("[DEBUG] relatorio_turnos.js: carregarColaboradoresParaFiltroRelatorio");
    let colaboradores = [];
    try {
      // Usa a função importada diretamente
      colaboradores = await buscarEArmazenarColaboradores();
    } catch (e) {
      console.error("relatorio_turnos.js: Erro ao buscar colaboradores via buscarEArmazenarColaboradores", e);
      showToast("Falha ao carregar lista de colaboradores para o filtro.", "error");
    }

    if (filtroColaboradorSelect) {
      // Usa a função importada diretamente, passando o array de colaboradores obtido
      popularSelectColaborador(filtroColaboradorSelect, null, colaboradores);
    } else {
      console.warn("[DEBUG] relatorio_turnos.js: Elemento filtro-colaborador não encontrado.");
    }
  }

  function exibirDadosRelatorio(turnos, totalHoras, totalTurnos) {
    if (!reportTableBody) {
      console.warn("[DEBUG] relatorio_turnos.js: reportTableBody não encontrado.");
      return;
    }
    reportTableBody.innerHTML = "";

    if (turnos && turnos.length > 0) {
      turnos.forEach((turno) => {
        const row = reportTableBody.insertRow();
        row.insertCell().textContent = turno.data_formatada;
        row.insertCell().textContent = turno.colaborador;
        row.insertCell().textContent = turno.hora_inicio_formatada;
        row.insertCell().textContent = turno.hora_fim_formatada;
        row.insertCell().textContent = turno.duracao_formatada;
      });
    } else {
      const row = reportTableBody.insertRow();
      const cell = row.insertCell();
      cell.colSpan = 5;
      cell.textContent = "Nenhum turno encontrado para os filtros selecionados.";
      cell.style.textAlign = "center";
    }

    if (reportSummaryDiv) {
      if (totalTurnos > 0) {
        reportSummaryDiv.innerHTML = `<p>Total de Turnos no período: <strong><span class="math-inline">\{totalTurnos\}</strong\></p\>
<p>Total de Horas Trabalhadas: <strong>{totalHoras
.toFixed(2)
.replace(".", ",")}h</strong></p>`;
} else {
reportSummaryDiv.innerHTML = "<p>Nenhum turno encontrado para exibir o resumo.</p>";
}
} else {
console.warn("[DEBUG] relatorio_turnos.js: reportSummaryDiv não encontrado.");
}
}

  if (reportFiltersForm) {
    reportFiltersForm.addEventListener("submit", async function (event) {
      event.preventDefault();
      console.log("[DEBUG] relatorio_turnos.js: Formulário de filtros submetido.");

      const originalButtonHtml = generateReportButton ? generateReportButton.innerHTML : "";

      if (generateReportButton) {
        generateReportButton.disabled = true;
        generateReportButton.innerHTML = '<i data-lucide="loader-circle" class="lucide-spin w-4 h-4 mr-1.5"></i> Gerando...';
        if (typeof lucide !== "undefined") lucide.createIcons();
      }

      const dataInicio = document.getElementById("filtro-data-inicio").value;
      const dataFim = document.getElementById("filtro-data-fim").value;
      const colaborador = filtroColaboradorSelect ? filtroColaboradorSelect.value : "";
      const csrfToken = csrfTokenReportPage ? csrfTokenReportPage.value : null;

      if (!dataInicio || !dataFim) {
        showToast("Por favor, selecione o período (Data Início e Data Fim).", "warning");
        if (generateReportButton) {
          generateReportButton.disabled = false;
          generateReportButton.innerHTML = originalButtonHtml;
          if (typeof lucide !== "undefined") lucide.createIcons();
        }
        return;
      }
      if (new Date(dataInicio) > new Date(dataFim)) {
        showToast("A Data Início não pode ser posterior à Data Fim.", "warning");
        if (generateReportButton) {
          generateReportButton.disabled = false;
          generateReportButton.innerHTML = originalButtonHtml;
          if (typeof lucide !== "undefined") lucide.createIcons();
        }
        return;
      }
      if (!csrfToken) {
        showToast("Erro de segurança (token ausente). Recarregue a página.", "error");
        if (generateReportButton) {
          generateReportButton.disabled = false;
          generateReportButton.innerHTML = originalButtonHtml;
          if (typeof lucide !== "undefined") lucide.createIcons();
        }
        return;
      }

      const params = new URLSearchParams({
        data_inicio: dataInicio,
        data_fim: dataFim,
        colaborador: colaborador,
        csrf_token: csrfToken, // O PHP espera 'csrf_token' e não 'csrf_token_reports' no GET
      });

      if (reportTableBody) {
        reportTableBody.innerHTML = `<tr><td colspan="5" style="text-align:center;">Buscando dados... <i data-lucide="loader-circle" class="lucide-spin w-4 h-4"></i></td></tr>`;
        if (typeof lucide !== "undefined") lucide.createIcons();
      }


      try {
        const response = await fetch(`gerar_relatorio_turnos.php?${params.toString()}`);
        const data = await response.json();

        if (!response.ok) {
          throw new Error(data.message || `Erro HTTP: ${response.status}`);
        }

        if (data.success) {
          exibirDadosRelatorio(data.turnos, data.total_geral_horas, data.total_turnos);
          if (data.csrf_token && csrfTokenReportPage) { // Atualiza o token na página se o backend enviar um novo
            csrfTokenReportPage.value = data.csrf_token;
          }
        } else {
          showToast("Erro ao gerar relatório: " + (data.message || "Erro desconhecido."), "error");
          exibirDadosRelatorio([], 0, 0);
        }
      } catch (error) {
        console.error("Erro na requisição do relatório (relatorio_turnos.js):", error);
        showToast(`Erro crítico ao buscar dados: ${error.message}`, "error");
        exibirDadosRelatorio([], 0, 0);
      } finally {
        if (generateReportButton) {
          generateReportButton.disabled = false;
          generateReportButton.innerHTML = originalButtonHtml;
          if (typeof lucide !== "undefined") lucide.createIcons();
        }
      }
    });
  } else {
    console.warn("[DEBUG] relatorio_turnos.js: Formulário report-filters-form não encontrado.");
  }

  // Carregamento inicial de colaboradores e datas padrão
  if (document.getElementById("report-filters-form")) {
    carregarColaboradoresParaFiltroRelatorio();
    const hoje = new Date();
    const primeiroDiaDoMes = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
    const ultimoDiaDoMes = new Date(hoje.getFullYear(), hoje.getMonth() + 1, 0);
    const dataInicioInput = document.getElementById("filtro-data-inicio");
    const dataFimInput = document.getElementById("filtro-data-fim");

    if (dataInicioInput) dataInicioInput.valueAsDate = primeiroDiaDoMes;
    if (dataFimInput) dataFimInput.valueAsDate = ultimoDiaDoMes;
  }
});