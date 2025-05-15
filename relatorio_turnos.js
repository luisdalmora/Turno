// relatorio_turnos.js
document.addEventListener("DOMContentLoaded", function () {
  const reportFiltersForm = document.getElementById("report-filters-form");
  const filtroColaboradorSelect = document.getElementById("filtro-colaborador");
  const reportTableBody = document.querySelector("#report-table tbody");
  const reportSummaryDiv = document.getElementById("report-summary");
  const generateReportButton = document.getElementById(
    "generate-report-button"
  );
  const csrfTokenReportPage = document.getElementById("csrf-token-reports");

  const buscarColaboradoresGlobais =
    typeof buscarEArmazenarColaboradores === "function"
      ? buscarEArmazenarColaboradores
      : null;
  const showToastGlobal =
    typeof showToast === "function"
      ? showToast
      : (message, type) => alert(`${type}: ${message}`);

  async function carregarColaboradoresParaFiltroRelatorio() {
    let colaboradores = [];
    if (buscarColaboradoresGlobais) {
      colaboradores = await buscarColaboradoresGlobais();
    } else {
      try {
        const response = await fetch("obter_colaboradores.php");
        const data = await response.json();
        if (data.success && data.colaboradores)
          colaboradores = data.colaboradores;
      } catch (e) {
        console.error("Fallback: Erro ao buscar colaboradores", e);
      }
    }

    if (filtroColaboradorSelect) {
      filtroColaboradorSelect.innerHTML =
        '<option value="">Todos os Colaboradores</option>';
      if (Array.isArray(colaboradores)) {
        colaboradores.forEach((colab) => {
          const option = document.createElement("option");
          option.value = colab.nome_completo;
          option.textContent = colab.nome_completo;
          filtroColaboradorSelect.appendChild(option);
        });
      }
    }
  }

  function exibirDadosRelatorio(turnos, totalHoras, totalTurnos) {
    if (!reportTableBody) return;
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
      cell.textContent =
        "Nenhum turno encontrado para os filtros selecionados.";
      cell.style.textAlign = "center";
    }

    if (reportSummaryDiv) {
      if (totalTurnos > 0) {
        reportSummaryDiv.innerHTML = `<p>Total de Turnos no período: <strong>${totalTurnos}</strong></p>
                                       <p>Total de Horas Trabalhadas: <strong>${totalHoras
                                         .toFixed(2)
                                         .replace(".", ",")}h</strong></p>`;
      } else {
        reportSummaryDiv.innerHTML =
          "<p>Nenhum turno encontrado para exibir o resumo.</p>";
      }
    }
  }

  if (reportFiltersForm) {
    reportFiltersForm.addEventListener("submit", async function (event) {
      event.preventDefault();
      const originalButtonHtml = generateReportButton.innerHTML; // Salva o HTML original do botão

      if (generateReportButton) {
        generateReportButton.disabled = true;
        generateReportButton.innerHTML =
          '<i data-lucide="loader-circle" class="lucide-spin"></i> Gerando...';
        if (typeof lucide !== "undefined") lucide.createIcons();
      }

      const dataInicio = document.getElementById("filtro-data-inicio").value;
      const dataFim = document.getElementById("filtro-data-fim").value;
      const colaborador = filtroColaboradorSelect
        ? filtroColaboradorSelect.value
        : "";
      const csrfToken = csrfTokenReportPage ? csrfTokenReportPage.value : null;

      if (!dataInicio || !dataFim) {
        showToastGlobal(
          "Por favor, selecione o período (Data Início e Data Fim).",
          "warning"
        );
        if (generateReportButton) {
          generateReportButton.disabled = false;
          generateReportButton.innerHTML = originalButtonHtml; // Restaura o HTML original (com ícone Lucide)
          if (typeof lucide !== "undefined") lucide.createIcons(); // Re-renderiza o ícone
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
          generateReportButton.innerHTML = originalButtonHtml;
          if (typeof lucide !== "undefined") lucide.createIcons();
        }
        return;
      }
      if (!csrfToken) {
        showToastGlobal(
          "Erro de segurança (token ausente). Recarregue a página.",
          "error"
        );
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
        csrf_token: csrfToken,
      });

      reportTableBody.innerHTML = `<tr><td colspan="5" style="text-align:center;">Buscando dados... <i data-lucide="loader-circle" class="lucide-spin"></i></td></tr>`;
      if (typeof lucide !== "undefined") lucide.createIcons();

      try {
        const response = await fetch(
          `gerar_relatorio_turnos.php?${params.toString()}`
        );
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
          if (data.csrf_token && csrfTokenReportPage) {
            csrfTokenReportPage.value = data.csrf_token;
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
          `Erro crítico ao buscar dados: ${error.message}`,
          "error"
        );
        exibirDadosRelatorio([], 0, 0);
      } finally {
        if (generateReportButton) {
          generateReportButton.disabled = false;
          generateReportButton.innerHTML = originalButtonHtml; // Restaura o HTML original
          if (typeof lucide !== "undefined") lucide.createIcons(); // Garante que o ícone seja renderizado
        }
      }
    });
  }

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
