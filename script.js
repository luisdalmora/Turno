// script.js

// --- Variáveis Globais ---
let todosOsColaboradores = [];
let employeeHoursChartInstance = null;
let activeToastTimeout = null;

let currentDisplayYear = new Date().getFullYear();
let currentDisplayMonth = new Date().getMonth() + 1; // Meses são 1-12

const nomesMeses = [
  "",
  "Janeiro",
  "Fevereiro",
  "Março",
  "Abril",
  "Maio",
  "Junho",
  "Julho",
  "Agosto",
  "Setembro",
  "Outubro",
  "Novembro",
  "Dezembro",
];

// --- Funções Utilitárias ---
function showToast(message, type = "info", duration = 3500) {
  const existingToast = document.getElementById("toast-notification");
  if (existingToast) {
    existingToast.remove();
    if (activeToastTimeout) clearTimeout(activeToastTimeout);
  }
  const toast = document.createElement("div");
  toast.id = "toast-notification";
  toast.className = `toast-notification ${type}`;
  toast.textContent = message;
  document.body.appendChild(toast);
  requestAnimationFrame(() => {
    toast.classList.add("show");
  });
  activeToastTimeout = setTimeout(() => {
    toast.classList.remove("show");
    toast.addEventListener("transitionend", () => toast.remove(), {
      once: true,
    });
  }, duration);
}

async function buscarEArmazenarColaboradores() {
  if (
    todosOsColaboradores.length > 0 &&
    todosOsColaboradores[0] &&
    todosOsColaboradores[0].hasOwnProperty("id")
  ) {
    return todosOsColaboradores;
  }
  try {
    const response = await fetch("obter_colaboradores.php");
    if (!response.ok) {
      const errorText = await response
        .text()
        .catch(
          () =>
            `Erro HTTP ${response.status}. Não foi possível ler a resposta do servidor.`
        );
      throw new Error(
        `Falha ao buscar colaboradores: ${errorText.substring(0, 150)}`
      );
    }
    const data = await response.json();
    if (data.success && data.colaboradores) {
      todosOsColaboradores = data.colaboradores;
      return todosOsColaboradores;
    } else {
      showToast(
        data.message || "Falha ao carregar lista de colaboradores do backend.",
        "error"
      );
      todosOsColaboradores = [];
      return [];
    }
  } catch (error) {
    console.error("Erro na requisição fetch de colaboradores:", error);
    showToast(
      `Erro crítico ao carregar colaboradores: ${error.message}`,
      "error"
    );
    todosOsColaboradores = [];
    return [];
  }
}

function popularSelectColaborador(selectElement, valorSelecionado = null) {
  selectElement.innerHTML = '<option value="">Selecione...</option>';
  if (!Array.isArray(todosOsColaboradores)) {
    console.error("Erro: 'todosOsColaboradores' não é um array.");
    return;
  }
  todosOsColaboradores.forEach((colab) => {
    const option = document.createElement("option");
    option.value = colab.nome_completo;
    option.textContent = colab.nome_completo;
    if (valorSelecionado && colab.nome_completo === valorSelecionado)
      option.selected = true;
    selectElement.appendChild(option);
  });
}

function calcularDuracaoDecimal(horaInicioStr, horaFimStr) {
  if (!horaInicioStr || !horaFimStr) return 0;
  const [h1Str, m1Str] = horaInicioStr.split(":");
  const [h2Str, m2Str] = horaFimStr.split(":");

  const h1 = parseInt(h1Str, 10);
  const m1 = parseInt(m1Str, 10);
  const h2 = parseInt(h2Str, 10);
  const m2 = parseInt(m2Str, 10);

  if (isNaN(h1) || isNaN(m1) || isNaN(h2) || isNaN(m2)) return 0;

  let inicioEmMinutos = h1 * 60 + m1;
  let fimEmMinutos = h2 * 60 + m2;

  if (fimEmMinutos < inicioEmMinutos) {
    fimEmMinutos += 24 * 60;
  }

  const duracaoEmMinutos = fimEmMinutos - inicioEmMinutos;
  return duracaoEmMinutos > 0 ? duracaoEmMinutos / 60.0 : 0;
}

// --- Funções Principais da Tabela de Turnos (home.php) ---
async function popularTabelaTurnos(turnos) {
  const corpoTabela = document.querySelector("#shifts-table-main tbody");
  if (!corpoTabela) return;
  corpoTabela.innerHTML = "";
  const chkAll = document.getElementById("select-all-shifts");
  if (chkAll) chkAll.checked = false;

  if (
    todosOsColaboradores.length === 0 ||
    !todosOsColaboradores[0] ||
    !todosOsColaboradores[0].hasOwnProperty("id")
  ) {
    await buscarEArmazenarColaboradores();
  }

  if (!turnos || turnos.length === 0) {
    const r = corpoTabela.insertRow();
    const c = r.insertCell();
    c.colSpan = 7;
    c.textContent = "Nenhum turno programado para este período.";
    c.style.textAlign = "center";
    return;
  }

  turnos.forEach((turno) => {
    const nLinha = corpoTabela.insertRow();
    nLinha.setAttribute("data-turno-id", turno.id);

    nLinha.insertCell().innerHTML = `<input type="checkbox" class="shift-select-checkbox" value="${turno.id}">`;

    const cellData = nLinha.insertCell();
    const inputData = document.createElement("input");
    inputData.type = "text";
    inputData.className = "shift-date";
    inputData.value = turno.data_formatada || turno.data;
    inputData.placeholder = "dd/Mês";
    cellData.appendChild(inputData);

    const cellInicio = nLinha.insertCell();
    const inputInicio = document.createElement("input");
    inputInicio.type = "time";
    inputInicio.className = "shift-time-inicio";
    inputInicio.value = turno.hora_inicio
      ? turno.hora_inicio.substring(0, 5)
      : "";
    cellInicio.appendChild(inputInicio);

    const cellFim = nLinha.insertCell();
    const inputFim = document.createElement("input");
    inputFim.type = "time";
    inputFim.className = "shift-time-fim";
    inputFim.value = turno.hora_fim ? turno.hora_fim.substring(0, 5) : "";
    cellFim.appendChild(inputFim);

    const cellColab = nLinha.insertCell();
    const selColab = document.createElement("select");
    selColab.className = "shift-employee shift-employee-select";
    popularSelectColaborador(selColab, turno.colaborador);
    cellColab.appendChild(selColab);

    const cellGCal = nLinha.insertCell();
    cellGCal.className = "shift-google-event-id";
    cellGCal.textContent = turno.google_calendar_event_id || "N/A";

    const cellAcoes = nLinha.insertCell();
    cellAcoes.className = "actions-cell";
    const btnEdit = document.createElement("button");
    btnEdit.innerHTML = '<i class="fas fa-edit"></i>';
    btnEdit.title = "Editar";
    btnEdit.className = "btn-table-action edit";
    btnEdit.onclick = () =>
      showToast(
        "Modifique os campos na linha e clique em 'Salvar Alterações'.",
        "info"
      );
    cellAcoes.appendChild(btnEdit);
    const btnDel = document.createElement("button");
    btnDel.innerHTML = '<i class="fas fa-trash-alt"></i>';
    btnDel.title = "Excluir";
    btnDel.className = "btn-table-action delete";
    const csrfTokenEl = document.getElementById("csrf-token-shifts");
    btnDel.onclick = () =>
      excluirTurnosNoServidor(
        [turno.id],
        csrfTokenEl ? csrfTokenEl.value : null
      );
    cellAcoes.appendChild(btnDel);
  });
}

async function salvarDadosTurnosNoServidor(dadosTurnos, csrfToken) {
  const btnSalvar = document.getElementById("save-shifts-button");
  const originalButtonText = btnSalvar
    ? btnSalvar.innerHTML
    : '<i class="fas fa-save"></i> Salvar Alterações';
  if (btnSalvar) {
    btnSalvar.disabled = true;
    btnSalvar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
  }

  const payload = {
    acao: "salvar_turnos",
    turnos: dadosTurnos,
    csrf_token: csrfToken,
  };
  try {
    const response = await fetch("salvar_turnos.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    const data = await response.json();

    if (!response.ok) {
      throw new Error(data.message || `Erro HTTP: ${response.status}`);
    }

    if (data.success) {
      showToast(data.message || "Turnos salvos com sucesso!", "success");
      if (data.csrf_token) {
        const csrfInput = document.getElementById("csrf-token-shifts");
        if (csrfInput) csrfInput.value = data.csrf_token;
      }
      await carregarTurnosDoServidor(
        currentDisplayYear,
        currentDisplayMonth,
        true
      );
    } else {
      showToast(
        "Erro ao salvar: " + (data.message || "Erro desconhecido."),
        "error"
      );
    }
  } catch (error) {
    console.error("Erro crítico ao salvar turnos:", error);
    showToast(`Erro crítico ao salvar: ${error.message}`, "error");
  } finally {
    if (btnSalvar) {
      btnSalvar.disabled = false;
      btnSalvar.innerHTML = originalButtonText;
    }
  }
}

function coletarDadosDaTabelaDeTurnos() {
  const linhas = document.querySelectorAll("#shifts-table-main tbody tr");
  const dados = [];

  const displayElement = document.getElementById("current-month-year-display");
  const anoTabela =
    displayElement && displayElement.dataset.year
      ? parseInt(displayElement.dataset.year, 10)
      : new Date().getFullYear();

  linhas.forEach((linha) => {
    if (linha.cells.length === 1 && linha.cells[0].colSpan > 1) return;
    const dataIn = linha.querySelector(".shift-date");
    const horaInicioIn = linha.querySelector(".shift-time-inicio");
    const horaFimIn = linha.querySelector(".shift-time-fim");
    const colabSel = linha.querySelector(".shift-employee-select");
    const idOrig = linha.getAttribute("data-turno-id");

    const dataVal = dataIn ? dataIn.value.trim() : "";
    const inicioVal = horaInicioIn ? horaInicioIn.value.trim() : "";
    const fimVal = horaFimIn ? horaFimIn.value.trim() : "";
    const colabVal = colabSel ? colabSel.value.trim() : "";

    if (dataVal && inicioVal && fimVal && colabVal) {
      const inicioTotalMin =
        parseInt(inicioVal.split(":")[0], 10) * 60 +
        parseInt(inicioVal.split(":")[1], 10);
      const fimTotalMin =
        parseInt(fimVal.split(":")[0], 10) * 60 +
        parseInt(fimVal.split(":")[1], 10);

      if (
        fimTotalMin <= inicioTotalMin &&
        !(
          parseInt(fimVal.split(":")[0], 10) < 6 &&
          parseInt(inicioVal.split(":")[0], 10) > 18
        )
      ) {
        if (
          !(
            fimTotalMin < inicioTotalMin &&
            parseInt(fimVal.split(":")[0], 10) < 6 &&
            parseInt(inicioVal.split(":")[0], 10) > 18
          )
        ) {
          showToast(
            `Atenção: Turno para ${colabVal} em ${dataVal} tem Hora Fim (${fimVal}) não posterior à Hora Início (${inicioVal}). Verifique.`,
            "warning",
            7000
          );
        }
      }
      dados.push({
        id: idOrig && !idOrig.startsWith("new-") ? idOrig : null,
        data: dataVal,
        hora_inicio: inicioVal,
        hora_fim: fimVal,
        colaborador: colabVal,
        ano: anoTabela.toString(),
      });
    } else if (
      !(dataVal === "" && inicioVal === "" && fimVal === "" && colabVal === "")
    ) {
      // console.warn("Linha incompleta não adicionada para salvar.");
    }
  });
  return dados;
}

function atualizarTabelaResumoColaboradores(turnos) {
  const tbody = document.querySelector("#employee-summary-table tbody");
  if (!tbody) return;
  tbody.innerHTML = "";
  if (!turnos || turnos.length === 0) {
    const r = tbody.insertRow();
    const c = r.insertCell();
    c.colSpan = 2;
    c.textContent = "Sem dados para resumo.";
    c.style.textAlign = "center";
    return;
  }
  const resumo = {};
  turnos.forEach((t) => {
    if (!t.colaborador || !t.hora_inicio || !t.hora_fim) return;
    if (!resumo[t.colaborador]) resumo[t.colaborador] = 0;
    resumo[t.colaborador] += calcularDuracaoDecimal(t.hora_inicio, t.hora_fim);
  });
  for (const colab in resumo) {
    if (resumo[colab] > 0.005) {
      const tot = resumo[colab].toFixed(2);
      const r = tbody.insertRow();
      r.insertCell().textContent = colab;
      r.insertCell().textContent = tot.replace(".", ",") + "h";
    }
  }
}

function atualizarGraficoResumoHoras(turnos) {
  const ctx = document.getElementById("employee-hours-chart");
  if (!ctx) return;
  const resumo = {};
  if (turnos && turnos.length > 0) {
    turnos.forEach((t) => {
      if (!t.colaborador || !t.hora_inicio || !t.hora_fim) return;
      if (!resumo[t.colaborador]) resumo[t.colaborador] = 0;
      resumo[t.colaborador] += calcularDuracaoDecimal(
        t.hora_inicio,
        t.hora_fim
      );
    });
  }

  const labels = Object.keys(resumo).filter((colab) => resumo[colab] > 0.005);
  const dataPoints = labels.map((l) => parseFloat(resumo[l].toFixed(2)));

  if (labels.length === 0) {
    if (employeeHoursChartInstance) {
      employeeHoursChartInstance.destroy();
      employeeHoursChartInstance = null;
    }
    const context = ctx.getContext("2d");
    context.clearRect(0, 0, ctx.width, ctx.height);
    context.textAlign = "center";
    context.font =
      "14px " +
      (
        getComputedStyle(document.body).getPropertyValue(
          "--font-family-primary"
        ) || "Poppins, sans-serif"
      ).trim();
    context.fillStyle = (
      getComputedStyle(document.body).getPropertyValue(
        "--primary-text-color"
      ) || "#555"
    ).trim();
    context.fillText(
      "Sem dados para exibir no gráfico.",
      ctx.width / 2,
      ctx.height / 2
    );
    return;
  }

  if (employeeHoursChartInstance) {
    employeeHoursChartInstance.data.labels = labels;
    employeeHoursChartInstance.data.datasets[0].data = dataPoints;
    employeeHoursChartInstance.update();
  } else {
    employeeHoursChartInstance = new Chart(ctx, {
      type: "bar",
      data: {
        labels,
        datasets: [
          {
            label: "Total de Horas Trabalhadas",
            data: dataPoints,
            backgroundColor: [
              "rgba(64,123,255,0.7)",
              "rgba(40,167,69,0.7)",
              "rgba(255,193,7,0.7)",
              "rgba(23,162,184,0.7)",
              "rgba(108,117,125,0.7)",
            ],
            borderColor: [
              "rgba(64,123,255,1)",
              "rgba(40,167,69,1)",
              "rgba(255,193,7,1)",
              "rgba(23,162,184,1)",
              "rgba(108,117,125,1)",
            ],
            borderWidth: 1,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: { beginAtZero: true, title: { display: true, text: "Horas" } },
          x: { title: { display: true, text: "Colaborador" } },
        },
        plugins: {
          legend: { display: labels.length > 1, position: "top" },
          title: { display: false },
          tooltip: {
            callbacks: {
              label: (c) =>
                (c.dataset.label || "") +
                ": " +
                (c.parsed.y !== null
                  ? c.parsed.y.toFixed(2).replace(".", ",") + "h"
                  : ""),
            },
          },
        },
      },
    });
  }
}

function updateCurrentMonthYearDisplay() {
  const displayElement = document.getElementById("current-month-year-display");
  const summaryPeriodElement = document.getElementById(
    "employee-summary-period"
  );
  if (displayElement) {
    // nomesMeses é 1-indexado para o nome do mês, currentDisplayMonth também é 1-12
    const monthName =
      nomesMeses[currentDisplayMonth] || `Mês ${currentDisplayMonth}`;
    const displayHTML = `<i class="fas fa-tasks"></i> Turnos - ${monthName} ${currentDisplayYear}`;
    displayElement.innerHTML = displayHTML;
    displayElement.dataset.year = currentDisplayYear;
    displayElement.dataset.month = currentDisplayMonth;
  }
  if (summaryPeriodElement) {
    summaryPeriodElement.textContent = nomesMeses[currentDisplayMonth] || "";
  }
}

async function carregarTurnosDoServidor(
  ano,
  mes,
  atualizarResumosGlobais = true
) {
  const shiftsTableBody = document.querySelector("#shifts-table-main tbody");
  if (shiftsTableBody) {
    shiftsTableBody.innerHTML = `<tr><td colspan="7" style="text-align:center;">Carregando turnos... <i class="fas fa-spinner fa-spin"></i></td></tr>`;
  } else {
    return;
  }

  try {
    const response = await fetch(`salvar_turnos.php?ano=${ano}&mes=${mes}`);
    const data = await response.json();

    if (!response.ok) {
      throw new Error(data.message || `HTTP ${response.status}`);
    }

    if (data.success) {
      if (data.csrf_token) {
        const csrfInput = document.getElementById("csrf-token-shifts");
        if (csrfInput) csrfInput.value = data.csrf_token;
      }
      await popularTabelaTurnos(data.data || []);
      if (atualizarResumosGlobais) {
        atualizarTabelaResumoColaboradores(data.data || []);
        atualizarGraficoResumoHoras(data.data || []);
      }
    } else {
      showToast(
        "Aviso: " + (data.message || "Não foi possível carregar turnos."),
        "warning"
      );
      await popularTabelaTurnos([]);
      if (atualizarResumosGlobais) {
        atualizarTabelaResumoColaboradores([]);
        atualizarGraficoResumoHoras([]);
      }
    }
  } catch (error) {
    console.error(`Erro ao carregar turnos para ${mes}/${ano}:`, error);
    showToast(`Erro ao carregar turnos: ${error.message}.`, "error");
    await popularTabelaTurnos([]);
    if (atualizarResumosGlobais) {
      atualizarTabelaResumoColaboradores([]);
      atualizarGraficoResumoHoras([]);
    }
  }
}

async function excluirTurnosNoServidor(ids, csrfToken) {
  if (!ids || ids.length === 0) {
    showToast("Nenhum turno selecionado.", "info");
    return;
  }
  if (
    !confirm(
      `Tem certeza que deseja excluir ${ids.length} turno(s)? Esta ação não pode ser desfeita.`
    )
  )
    return;

  try {
    const response = await fetch("salvar_turnos.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        acao: "excluir_turnos",
        ids_turnos: ids,
        csrf_token: csrfToken,
      }),
    });
    const data = await response.json();
    if (!response.ok) {
      throw new Error(data.message || `HTTP ${response.status}`);
    }

    if (data.success) {
      showToast(data.message || "Turno(s) excluído(s) com sucesso!", "success");
      if (data.csrf_token) {
        const csrfInput = document.getElementById("csrf-token-shifts");
        if (csrfInput) csrfInput.value = data.csrf_token;
      }
      carregarTurnosDoServidor(currentDisplayYear, currentDisplayMonth, true);
    } else {
      showToast(
        "Erro ao excluir: " + (data.message || "Erro do servidor."),
        "error"
      );
    }
  } catch (error) {
    console.error("Erro crítico ao excluir turnos:", error);
    showToast(`Erro crítico ao excluir: ${error.message}.`, "error");
  }
}

// --- Event Listeners e Código Executado no DOMContentLoaded ---
document.addEventListener("DOMContentLoaded", async function () {
  const displayElementInit = document.getElementById(
    "current-month-year-display"
  );
  if (
    displayElementInit &&
    displayElementInit.dataset.year &&
    displayElementInit.dataset.month
  ) {
    currentDisplayYear = parseInt(displayElementInit.dataset.year, 10);
    currentDisplayMonth = parseInt(displayElementInit.dataset.month, 10);
  } else {
    const today = new Date();
    currentDisplayYear = today.getFullYear();
    currentDisplayMonth = today.getMonth() + 1;
    if (displayElementInit) updateCurrentMonthYearDisplay();
  }

  const shiftsTableElement = document.getElementById("shifts-table-main");

  if (shiftsTableElement) {
    await buscarEArmazenarColaboradores();
    carregarTurnosDoServidor(currentDisplayYear, currentDisplayMonth);
  }

  const prevMonthButton = document.getElementById("prev-month-button");
  if (prevMonthButton) {
    prevMonthButton.addEventListener("click", () => {
      currentDisplayMonth--;
      if (currentDisplayMonth < 1) {
        currentDisplayMonth = 12;
        currentDisplayYear--;
      }
      updateCurrentMonthYearDisplay();
      carregarTurnosDoServidor(currentDisplayYear, currentDisplayMonth, true);
    });
  }

  const nextMonthButton = document.getElementById("next-month-button");
  if (nextMonthButton) {
    nextMonthButton.addEventListener("click", () => {
      currentDisplayMonth++;
      if (currentDisplayMonth > 12) {
        currentDisplayMonth = 1;
        currentDisplayYear++;
      }
      updateCurrentMonthYearDisplay();
      carregarTurnosDoServidor(currentDisplayYear, currentDisplayMonth, true);
    });
  }

  const btnSalvarTurnos = document.getElementById("save-shifts-button");
  if (btnSalvarTurnos) {
    btnSalvarTurnos.addEventListener("click", () => {
      const csrfTokenEl = document.getElementById("csrf-token-shifts");
      const csrfToken = csrfTokenEl ? csrfTokenEl.value : null;
      if (!csrfToken && shiftsTableElement) {
        showToast(
          "Erro de segurança (token ausente). Recarregue a página.",
          "error"
        );
        return;
      }
      const dados = coletarDadosDaTabelaDeTurnos();
      if (dados.length > 0) {
        salvarDadosTurnosNoServidor(dados, csrfToken);
      } else if (
        shiftsTableElement &&
        shiftsTableElement.querySelector("tbody td[colspan]")
      ) {
        showToast("Adicione um turno para salvar.", "info");
      } else if (shiftsTableElement) {
        showToast(
          "Nenhum turno válido para salvar. Preencha todos os campos.",
          "warning",
          5000
        );
      }
    });
  }

  const btnAdicionarTurno = document.getElementById("add-shift-row-button");
  if (btnAdicionarTurno) {
    btnAdicionarTurno.addEventListener("click", async function () {
      const tbody = document.querySelector("#shifts-table-main tbody");
      if (!tbody) return;
      const placeholderRow = tbody.querySelector("td[colspan]");
      if (placeholderRow) tbody.innerHTML = "";
      if (
        todosOsColaboradores.length === 0 ||
        !todosOsColaboradores[0] ||
        !todosOsColaboradores[0].hasOwnProperty("id")
      ) {
        await buscarEArmazenarColaboradores();
      }

      const newId = "new-" + Date.now();
      const nLinha = tbody.insertRow();
      nLinha.setAttribute("data-turno-id", newId);

      // Checkbox
      let cell = nLinha.insertCell();
      let input = document.createElement("input");
      input.type = "checkbox";
      input.className = "shift-select-checkbox";
      cell.appendChild(input);

      // Data
      cell = nLinha.insertCell();
      input = document.createElement("input");
      input.type = "text";
      input.className = "shift-date";
      input.placeholder = "dd/Mês";
      cell.appendChild(input);
      input.focus();

      // Hora Início
      cell = nLinha.insertCell();
      input = document.createElement("input");
      input.type = "time";
      input.className = "shift-time-inicio";
      cell.appendChild(input);

      // Hora Fim
      cell = nLinha.insertCell();
      input = document.createElement("input");
      input.type = "time";
      input.className = "shift-time-fim";
      cell.appendChild(input);

      // Colaborador
      cell = nLinha.insertCell();
      const selColab = document.createElement("select");
      selColab.className = "shift-employee shift-employee-select";
      popularSelectColaborador(selColab);
      cell.appendChild(selColab);

      // GCal ID (oculto)
      cell = nLinha.insertCell();
      cell.className = "shift-google-event-id";
      cell.textContent = "Pendente";

      // Ações
      cell = nLinha.insertCell();
      cell.className = "actions-cell";
      const btnDel = document.createElement("button");
      btnDel.innerHTML = '<i class="fas fa-trash-alt"></i>';
      btnDel.title = "Remover";
      btnDel.className = "btn-table-action delete";
      btnDel.onclick = () => {
        nLinha.remove();
        if (tbody.rows.length === 0) popularTabelaTurnos([]);
      };
      cell.appendChild(btnDel);
    });
  }

  const chkAll = document.getElementById("select-all-shifts");
  if (chkAll) {
    chkAll.addEventListener("change", () => {
      document
        .querySelectorAll("#shifts-table-main .shift-select-checkbox")
        .forEach((c) => (c.checked = chkAll.checked));
    });
  }

  const btnDelSel = document.getElementById("delete-selected-shifts-button");
  if (btnDelSel) {
    btnDelSel.addEventListener("click", () => {
      const csrfTokenEl = document.getElementById("csrf-token-shifts");
      const csrfToken = csrfTokenEl ? csrfTokenEl.value : null;
      if (!csrfToken && shiftsTableElement) {
        showToast(
          "Erro de segurança (token ausente). Recarregue a página.",
          "error"
        );
        return;
      }
      const ids = [];
      let removidoLocal = false;
      document
        .querySelectorAll("#shifts-table-main .shift-select-checkbox:checked")
        .forEach((c) => {
          const tr = c.closest("tr");
          if (tr) {
            const id = tr.getAttribute("data-turno-id");
            if (id && !id.startsWith("new-")) ids.push(id);
            else if (id && id.startsWith("new-")) {
              tr.remove();
              removidoLocal = true;
            }
          }
        });
      if (ids.length > 0) excluirTurnosNoServidor(ids, csrfToken);
      else if (removidoLocal) {
        showToast("Linhas novas (não salvas) foram removidas.", "info");
        const tbody = document.querySelector("#shifts-table-main tbody");
        if (tbody && tbody.rows.length === 0) popularTabelaTurnos([]);
      } else
        showToast("Nenhum turno existente selecionado para exclusão.", "info");
    });
  }

  // Lógica Google Calendar
  const urlParams = new URLSearchParams(window.location.search);
  const gcalStatus = urlParams.get("gcal_status"),
    gcalMsg = urlParams.get("gcal_msg");
  const statusMsgEl = document.getElementById("gcal-status-message");
  const connBtn = document.getElementById("connect-gcal-btn"),
    discBtn = document.getElementById("disconnect-gcal-btn");

  function checkGCalConnectionStatus() {
    if (!statusMsgEl || !connBtn || !discBtn) return;
    let isConn = false;
    const gcalAlreadyConnected = localStorage.getItem(
      "gcal_connected_simposto"
    );

    if (gcalStatus === "success") {
      showToast("Google Calendar conectado com sucesso!", "success");
      if (statusMsgEl) {
        statusMsgEl.textContent = "Google Calendar conectado!";
        statusMsgEl.style.color = "var(--success-color)";
      }
      localStorage.setItem("gcal_connected_simposto", "true");
      isConn = true;
    } else if (gcalStatus === "error") {
      showToast(
        "Falha conexão GCal: " + (gcalMsg || "Tente novamente."),
        "error"
      );
      if (statusMsgEl) {
        statusMsgEl.textContent =
          "Falha na conexão com GCal: " + (gcalMsg || "Tente.");
        statusMsgEl.style.color = "var(--danger-color)";
      }
      localStorage.removeItem("gcal_connected_simposto");
    } else if (gcalStatus === "disconnected") {
      showToast("Google Calendar desconectado.", "info");
      if (statusMsgEl) {
        statusMsgEl.textContent = "Google Calendar desconectado.";
        statusMsgEl.style.color = "var(--warning-color)";
      }
      localStorage.removeItem("gcal_connected_simposto");
    } else if (gcalAlreadyConnected === "true" && statusMsgEl) {
      statusMsgEl.textContent = "Google Calendar conectado (status local).";
      statusMsgEl.style.color = "var(--success-color)";
      isConn = true;
    } else if (
      statusMsgEl &&
      statusMsgEl.textContent.includes("Verifique o status")
    ) {
      statusMsgEl.textContent = "Conecte sua conta para sincronizar os turnos.";
    }

    connBtn.style.display = isConn ? "none" : "inline-flex";
    discBtn.style.display = isConn ? "inline-flex" : "none";
  }
  if (document.getElementById("google-calendar-section"))
    checkGCalConnectionStatus();

  if (discBtn)
    discBtn.addEventListener("click", () => {
      if (
        confirm(
          "Desconectar sua conta do Google Calendar? Isso removerá a sincronização automática e os tokens salvos."
        )
      ) {
        localStorage.removeItem("gcal_connected_simposto");
        window.location.href = "google_revoke_token.php";
      }
    });

  const logoutLk = document.getElementById("logout-link");
  if (logoutLk)
    logoutLk.addEventListener("click", (e) => {
      e.preventDefault();
      showToast("Saindo do sistema...", "info", 1500);
      setTimeout(() => {
        if (logoutLk.href) window.location.href = logoutLk.href;
      }, 1500);
    });

  // Efeito placeholder flutuante para campos de input de login/cadastro
  document.querySelectorAll(".input-field").forEach((inp) => {
    if (inp.tagName.toLowerCase() === "select") return;
    const chk = () => inp.classList.toggle("has-val", inp.value.trim() !== "");
    inp.addEventListener("blur", chk);
    inp.addEventListener("input", chk);
    // Verifica no carregamento (útil para campos preenchidos por autocomplete do navegador)
    // Usar um pequeno timeout para dar chance ao autocomplete de preencher
    setTimeout(chk, 100);
  });
});
