// relatorio_turnos.js
document.addEventListener("DOMContentLoaded", function () {
  const filtroColaboradorSelect = document.getElementById("filtro-colaborador");
  const reportFiltersForm = document.getElementById("report-filters-form");
  const reportTableBody = document.querySelector("#report-table tbody");
  const reportSummaryDiv = document.getElementById("report-summary");
  const reportTableHeadRow = document.querySelector("#report-table thead tr");

  function carregarColaboradores() {
    fetch("obter_colaboradores.php") // Script PHP para buscar colaboradores
      .then((response) => response.json())
      .then((data) => {
        if (data.success && data.colaboradores) {
          if (filtroColaboradorSelect) {
            filtroColaboradorSelect.innerHTML =
              '<option value="">Todos os Colaboradores</option>'; // Limpa e adiciona default
            data.colaboradores.forEach((colab) => {
              const option = document.createElement("option");
              option.value = colab.nome_completo; // Assumindo que o backend filtra por nome_completo
              option.textContent = colab.nome_completo;
              filtroColaboradorSelect.appendChild(option);
            });
          }
        } else {
          console.error("Erro ao carregar colaboradores:", data.message);
        }
      })
      .catch((error) =>
        console.error("Erro na requisição de colaboradores:", error)
      );
  }

  function exibirRelatorio(turnos, totalHorasTrabalhadas) {
    if (!reportTableBody) return;
    reportTableBody.innerHTML = "";

    if (reportTableHeadRow) {
      // Garante que o cabeçalho está correto
      reportTableHeadRow.innerHTML = `
                <th>Data</th>
                <th>Colaborador</th>
                <th>Duração Registrada</th>
            `;
    }

    if (turnos && turnos.length > 0) {
      turnos.forEach((turno) => {
        const row = reportTableBody.insertRow();
        row.insertCell().textContent = turno.data_formatada;
        row.insertCell().textContent = turno.colaborador;
        row.insertCell().textContent = turno.duracao_registrada_label;
      });
    } else {
      const row = reportTableBody.insertRow();
      const cell = row.insertCell();
      cell.colSpan = 3;
      cell.textContent =
        "Nenhum turno encontrado para os filtros selecionados.";
      cell.style.textAlign = "center";
    }

    if (reportSummaryDiv) {
      if (
        totalHorasTrabalhadas !== null &&
        totalHorasTrabalhadas !== undefined
      ) {
        reportSummaryDiv.innerHTML = `<p>Total de horas trabalhadas no período: <strong>${totalHorasTrabalhadas
          .toFixed(2)
          .replace(".", ",")}h</strong></p>`;
      } else {
        reportSummaryDiv.innerHTML =
          "<p>Não foi possível calcular o total de horas.</p>";
      }
    }
  }

  if (reportFiltersForm) {
    reportFiltersForm.addEventListener("submit", function (event) {
      event.preventDefault();
      const dataInicio = document.getElementById("filtro-data-inicio").value;
      const dataFim = document.getElementById("filtro-data-fim").value;
      const colaborador = filtroColaboradorSelect
        ? filtroColaboradorSelect.value
        : "";

      if (!dataInicio || !dataFim) {
        alert("Por favor, selecione o período (Data Início e Data Fim).");
        return;
      }
      if (new Date(dataInicio) > new Date(dataFim)) {
        alert("A Data Início não pode ser posterior à Data Fim.");
        return;
      }

      const params = new URLSearchParams({
        data_inicio: dataInicio,
        data_fim: dataFim,
        colaborador: colaborador,
      });

      reportTableBody.innerHTML =
        '<tr><td colspan="3" style="text-align:center;">Carregando relatório...</td></tr>'; // Feedback de carregamento

      fetch(`gerar_relatorio_turnos.php?${params.toString()}`)
        .then((response) => {
          if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
          }
          return response.json();
        })
        .then((data) => {
          if (data.success) {
            exibirRelatorio(data.turnos, data.total_horas_trabalhadas);
          } else {
            alert(
              "Erro ao gerar relatório: " +
                (data.message || "Erro desconhecido do servidor.")
            );
            exibirRelatorio([], null);
          }
        })
        .catch((error) => {
          console.error("Erro na requisição do relatório:", error);
          alert(
            "Erro crítico ao buscar dados do relatório. Verifique o console para detalhes."
          );
          exibirRelatorio([], null);
        });
    });
  }

  if (filtroColaboradorSelect) {
    carregarColaboradores();
  }

  const hoje = new Date();
  const primeiroDiaDoMes = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
  const ultimoDiaDoMes = new Date(hoje.getFullYear(), hoje.getMonth() + 1, 0);
  const dataInicioInput = document.getElementById("filtro-data-inicio");
  const dataFimInput = document.getElementById("filtro-data-fim");

  if (dataInicioInput && dataFimInput) {
    dataInicioInput.valueAsDate = primeiroDiaDoMes;
    dataFimInput.valueAsDate = ultimoDiaDoMes;
  }
});
