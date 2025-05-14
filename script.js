// script.js

// --- Variáveis Globais ---
let todosOsColaboradores = [];
let employeeHoursChartInstance = null;
let activeToastTimeout = null;

let currentDisplayYear = new Date().getFullYear();
let currentDisplayMonth = new Date().getMonth() + 1; // Meses são 1-12

// Para Implantações
let currentDisplayYearImplantacoes = new Date().getFullYear();
let currentDisplayMonthImplantacoes = new Date().getMonth() + 1;

// Para Feriados (sincronizado com turnos por padrão)
let currentDisplayYearFeriados = new Date().getFullYear();
let currentDisplayMonthFeriados = new Date().getMonth() + 1;

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
    // Virada da noite
    fimEmMinutos += 24 * 60;
  }

  const duracaoEmMinutos = fimEmMinutos - inicioEmMinutos;
  return duracaoEmMinutos > 0 ? duracaoEmMinutos / 60.0 : 0;
}

// --- Funções Turnos ---
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
    c.colSpan = 5;
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
    inputData.className = "shift-date form-control-filter"; // Adicionada classe para estilo uniforme
    inputData.value = turno.data_formatada || turno.data;
    inputData.placeholder = "dd/Mês";
    cellData.appendChild(inputData);

    const cellInicio = nLinha.insertCell();
    const inputInicio = document.createElement("input");
    inputInicio.type = "time";
    inputInicio.className = "shift-time-inicio form-control-filter"; // Adicionada classe
    inputInicio.value = turno.hora_inicio
      ? turno.hora_inicio.substring(0, 5)
      : "";
    cellInicio.appendChild(inputInicio);

    const cellFim = nLinha.insertCell();
    const inputFim = document.createElement("input");
    inputFim.type = "time";
    inputFim.className = "shift-time-fim form-control-filter"; // Adicionada classe
    inputFim.value = turno.hora_fim ? turno.hora_fim.substring(0, 5) : "";
    cellFim.appendChild(inputFim);

    const cellColab = nLinha.insertCell();
    const selColab = document.createElement("select");
    selColab.className =
      "shift-employee shift-employee-select form-control-filter"; // Adicionada classe
    popularSelectColaborador(selColab, turno.colaborador);
    cellColab.appendChild(selColab);
  });
}

async function salvarDadosTurnosNoServidor(dadosTurnos, csrfToken) {
  const btnSalvar = document.getElementById("save-shifts-button");
  const originalButtonText = btnSalvar
    ? btnSalvar.innerHTML
    : "<i></i> Salvar Alterações"; // Placeholder para ícone
  if (btnSalvar) {
    btnSalvar.disabled = true;
    btnSalvar.innerHTML =
      '<i data-lucide="loader-circle" class="lucide-spin"></i> Salvando...';
    if (typeof lucide !== "undefined") lucide.createIcons();
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
    if (!response.ok)
      throw new Error(data.message || `Erro HTTP: ${response.status}`);

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
      btnSalvar.innerHTML = originalButtonText.replace(
        "<i></i>",
        '<i data-lucide="save"></i>'
      );
      if (typeof lucide !== "undefined") lucide.createIcons();
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
  let erroValidacaoGeralTurnos = false;

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
            `Atenção: Turno para ${colabVal} em ${dataVal} tem Hora Fim (${fimVal}) não posterior à Hora Início (${inicioVal}). Este turno não será salvo.`,
            "warning",
            7000
          );
          erroValidacaoGeralTurnos = true;
          return; // Pula este turno específico
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
      showToast(
        "Linha de turno incompleta não será salva. Preencha todos os campos.",
        "warning",
        5000
      );
      erroValidacaoGeralTurnos = true;
    }
  });
  if (erroValidacaoGeralTurnos && dados.length === 0) return [];
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
    const monthName =
      nomesMeses[currentDisplayMonth] || `Mês ${currentDisplayMonth}`;
    // Ícone Lucide para Turnos
    displayElement.innerHTML = `<i data-lucide="list-todo"></i> Turnos - ${monthName} ${currentDisplayYear}`;
    displayElement.dataset.year = currentDisplayYear;
    displayElement.dataset.month = currentDisplayMonth;
    if (typeof lucide !== "undefined") lucide.createIcons(); // Renderiza o novo ícone
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
  const csrfInputOriginal = document.getElementById("csrf-token-shifts"); // Para restaurar o token depois

  if (shiftsTableBody) {
    shiftsTableBody.innerHTML = `<tr><td colspan="5" style="text-align:center;">Carregando turnos... <i data-lucide="loader-circle" class="lucide-spin"></i></td></tr>`;
    if (typeof lucide !== "undefined") lucide.createIcons();
  } else {
    return;
  }

  try {
    const response = await fetch(`salvar_turnos.php?ano=${ano}&mes=${mes}`);
    const data = await response.json();
    if (!response.ok)
      throw new Error(data.message || `HTTP ${response.status}`);

    if (data.success) {
      if (data.csrf_token && csrfInputOriginal) {
        csrfInputOriginal.value = data.csrf_token;
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

// --- Funções para Observações Gerais ---
async function carregarObservacaoGeral() {
  const textarea = document.getElementById("observacoes-gerais-textarea");
  const csrfTokenObsGeralInput = document.getElementById(
    "csrf-token-obs-geral"
  );
  if (!textarea || !csrfTokenObsGeralInput) return;

  try {
    const response = await fetch("gerenciar_observacao_geral.php");
    const data = await response.json();
    if (data.success) {
      textarea.value = data.observacao || "";
      if (data.csrf_token) csrfTokenObsGeralInput.value = data.csrf_token;
    } else {
      showToast(data.message || "Erro ao carregar observação.", "error");
    }
  } catch (error) {
    showToast(
      "Erro de conexão ao carregar observação: " + error.message,
      "error"
    );
  }
}

async function salvarObservacaoGeral() {
  const textarea = document.getElementById("observacoes-gerais-textarea");
  const csrfTokenInput = document.getElementById("csrf-token-obs-geral");
  const saveButton = document.getElementById("salvar-observacoes-gerais-btn");

  if (!textarea || !csrfTokenInput || !saveButton) return;

  const originalButtonHtml = saveButton.innerHTML;
  saveButton.disabled = true;
  saveButton.innerHTML =
    '<i data-lucide="loader-circle" class="lucide-spin"></i> Salvando...';
  if (typeof lucide !== "undefined") lucide.createIcons();

  const payload = {
    observacao: textarea.value,
    csrf_token: csrfTokenInput.value,
  };

  try {
    const response = await fetch("gerenciar_observacao_geral.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    const data = await response.json();
    if (data.success) {
      showToast(data.message || "Observação salva!", "success");
      if (data.csrf_token) csrfTokenInput.value = data.csrf_token;
    } else {
      showToast(data.message || "Erro ao salvar observação.", "error");
    }
  } catch (error) {
    showToast(
      "Erro de conexão ao salvar observação: " + error.message,
      "error"
    );
  } finally {
    saveButton.disabled = false;
    saveButton.innerHTML = originalButtonHtml.replace(
      "<i></i>",
      '<i data-lucide="save"></i>'
    );
    if (typeof lucide !== "undefined") lucide.createIcons();
  }
}

// --- Funções para Feriados ---
function updateFeriadosDisplay(ano, mes) {
  const displayElement = document.getElementById("feriados-mes-ano-display");
  if (displayElement) {
    const monthName = nomesMeses[mes] || `Mês ${mes}`;
    displayElement.innerHTML = `<i data-lucide="calendar-heart"></i> Feriados - ${monthName} ${ano}`;
    if (typeof lucide !== "undefined") lucide.createIcons();
  }
}

async function carregarFeriados(ano, mes) {
  const tbody = document.querySelector("#feriados-table tbody");
  if (!tbody) return;

  tbody.innerHTML = `<tr><td colspan="2" style="text-align:center;">Carregando feriados... <i data-lucide="loader-circle" class="lucide-spin"></i></td></tr>`;
  if (typeof lucide !== "undefined") lucide.createIcons();

  try {
    const response = await fetch(`carregar_feriados.php?ano=${ano}&mes=${mes}`);
    const data = await response.json();

    tbody.innerHTML = "";
    if (data.success && data.feriados) {
      if (data.feriados.length === 0) {
        const r = tbody.insertRow();
        const c = r.insertCell();
        c.colSpan = 2;
        c.textContent = "Nenhum feriado encontrado para este mês.";
        c.style.textAlign = "center";
      } else {
        data.feriados.forEach((feriado) => {
          const r = tbody.insertRow();
          r.insertCell().textContent = feriado.data;
          r.insertCell().textContent = feriado.observacao;
        });
      }
    } else {
      showToast(data.message || "Erro ao carregar feriados.", "warning");
      const r = tbody.insertRow();
      const c = r.insertCell();
      c.colSpan = 2;
      c.textContent = data.message || "Erro ao carregar feriados.";
      c.style.textAlign = "center";
    }
  } catch (error) {
    console.error("Erro ao buscar feriados:", error);
    tbody.innerHTML = `<tr><td colspan="2" style="text-align:center;">Erro de conexão ao carregar feriados.</td></tr>`;
    showToast(
      "Erro de conexão ao carregar feriados: " + error.message,
      "error"
    );
  }
}

// --- Funções para Implantações ---
function updateCurrentMonthYearDisplayImplantacoes() {
  const displayElement = document.getElementById(
    "current-month-year-implantacoes-display"
  );
  if (displayElement) {
    const monthName =
      nomesMeses[currentDisplayMonthImplantacoes] ||
      `Mês ${currentDisplayMonthImplantacoes}`;
    displayElement.innerHTML = `<i data-lucide="settings-2"></i> Implantações - ${monthName} ${currentDisplayYearImplantacoes}`;
    displayElement.dataset.year = currentDisplayYearImplantacoes;
    displayElement.dataset.month = currentDisplayMonthImplantacoes;
    if (typeof lucide !== "undefined") lucide.createIcons();
  }
}

async function carregarImplantacoesDoServidor(ano, mes) {
  const tableBody = document.querySelector("#implantacoes-table-main tbody");
  const csrfTokenInput = document.getElementById("csrf-token-implantacoes");
  if (!tableBody || !csrfTokenInput) return;

  tableBody.innerHTML = `<tr><td colspan="4" style="text-align:center;">Carregando implantações... <i data-lucide="loader-circle" class="lucide-spin"></i></td></tr>`;
  if (typeof lucide !== "undefined") lucide.createIcons();

  try {
    const response = await fetch(
      `gerenciar_implantacoes.php?ano=${ano}&mes=${mes}`
    );
    const data = await response.json();
    if (!response.ok)
      throw new Error(data.message || `HTTP ${response.status}`);

    if (data.success) {
      if (data.csrf_token) csrfTokenInput.value = data.csrf_token;
      popularTabelaImplantacoes(data.data || []);
    } else {
      showToast(
        "Aviso: " + (data.message || "Não foi possível carregar implantações."),
        "warning"
      );
      popularTabelaImplantacoes([]);
    }
  } catch (error) {
    showToast(`Erro ao carregar implantações: ${error.message}.`, "error");
    popularTabelaImplantacoes([]);
  }
}

function popularTabelaImplantacoes(implantacoes) {
  const corpoTabela = document.querySelector("#implantacoes-table-main tbody");
  if (!corpoTabela) return;
  corpoTabela.innerHTML = "";
  const chkAll = document.getElementById("select-all-implantacoes");
  if (chkAll) chkAll.checked = false;

  if (!implantacoes || implantacoes.length === 0) {
    const r = corpoTabela.insertRow();
    const c = r.insertCell();
    c.colSpan = 4;
    c.textContent = "Nenhuma implantação programada para este período.";
    c.style.textAlign = "center";
    return;
  }

  implantacoes.forEach((item) => {
    const nLinha = corpoTabela.insertRow();
    nLinha.setAttribute("data-implantacao-id", item.id);

    nLinha.insertCell().innerHTML = `<input type="checkbox" class="implantacao-select-checkbox" value="${item.id}">`;

    const cellDataInicio = nLinha.insertCell();
    const inputDataInicio = document.createElement("input");
    inputDataInicio.type = "date";
    inputDataInicio.className = "implantacao-data-inicio form-control-filter";
    inputDataInicio.value = item.data_inicio || "";
    cellDataInicio.appendChild(inputDataInicio);

    const cellDataFim = nLinha.insertCell();
    const inputDataFim = document.createElement("input");
    inputDataFim.type = "date";
    inputDataFim.className = "implantacao-data-fim form-control-filter";
    inputDataFim.value = item.data_fim || "";
    cellDataFim.appendChild(inputDataFim);

    const cellObs = nLinha.insertCell();
    const inputObs = document.createElement("input");
    inputObs.type = "text";
    inputObs.className = "implantacao-observacoes form-control-filter";
    inputObs.value = item.observacoes || "";
    inputObs.placeholder = "Descrição da implantação";
    cellObs.appendChild(inputObs);
  });
}

function coletarDadosDaTabelaDeImplantacoes() {
  const linhas = document.querySelectorAll("#implantacoes-table-main tbody tr");
  const dados = [];
  let erroValidacaoGeral = false;
  linhas.forEach((linha) => {
    if (linha.cells.length === 1 && linha.cells[0].colSpan > 1) return;

    const idOrig = linha.getAttribute("data-implantacao-id");
    const dataInicioIn = linha.querySelector(".implantacao-data-inicio");
    const dataFimIn = linha.querySelector(".implantacao-data-fim");
    const observacoesIn = linha.querySelector(".implantacao-observacoes");

    const inicioVal = dataInicioIn ? dataInicioIn.value.trim() : "";
    const fimVal = dataFimIn ? dataFimIn.value.trim() : "";
    const obsVal = observacoesIn ? observacoesIn.value.trim() : "";

    if (inicioVal && fimVal) {
      if (new Date(fimVal) < new Date(inicioVal)) {
        showToast(
          `Atenção: Data Fim (${new Date(
            fimVal
          ).toLocaleDateString()}) não pode ser anterior à Data Início (${new Date(
            inicioVal
          ).toLocaleDateString()}) para '${
            obsVal || "implantação sem nome"
          }'. Este item não será salvo.`,
          "warning",
          7000
        );
        erroValidacaoGeral = true;
      } else {
        dados.push({
          id: idOrig && !idOrig.startsWith("new-") ? idOrig : null,
          data_inicio: inicioVal,
          data_fim: fimVal,
          observacoes: obsVal,
        });
      }
    } else if (inicioVal || fimVal || obsVal) {
      showToast(
        "Linha de implantação incompleta não será salva. Verifique as datas.",
        "warning",
        5000
      );
      erroValidacaoGeral = true;
    }
  });
  if (erroValidacaoGeral && dados.length === 0) return [];
  return dados;
}

async function salvarDadosImplantacoesNoServidor(dadosImplantacoes, csrfToken) {
  const btnSalvar = document.getElementById("save-implantacoes-button");
  const originalButtonText = btnSalvar
    ? btnSalvar.innerHTML
    : "<i></i> Salvar Alterações";
  if (btnSalvar) {
    btnSalvar.disabled = true;
    btnSalvar.innerHTML =
      '<i data-lucide="loader-circle" class="lucide-spin"></i> Salvando...';
    if (typeof lucide !== "undefined") lucide.createIcons();
  }

  const payload = {
    acao: "salvar_implantacoes",
    implantacoes: dadosImplantacoes,
    csrf_token: csrfToken,
  };
  try {
    const response = await fetch("gerenciar_implantacoes.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    const data = await response.json();
    if (!response.ok)
      throw new Error(data.message || `Erro HTTP: ${response.status}`);

    if (data.success) {
      showToast(data.message || "Implantações salvas com sucesso!", "success");
      if (data.csrf_token) {
        const csrfInput = document.getElementById("csrf-token-implantacoes");
        if (csrfInput) csrfInput.value = data.csrf_token;
      }
      carregarImplantacoesDoServidor(
        currentDisplayYearImplantacoes,
        currentDisplayMonthImplantacoes
      );
    } else {
      showToast(
        "Erro ao salvar implantações: " +
          (data.message || "Erro desconhecido."),
        "error"
      );
    }
  } catch (error) {
    showToast(`Erro crítico ao salvar implantações: ${error.message}`, "error");
  } finally {
    if (btnSalvar) {
      btnSalvar.disabled = false;
      btnSalvar.innerHTML = originalButtonText.replace(
        "<i></i>",
        '<i data-lucide="save"></i>'
      );
      if (typeof lucide !== "undefined") lucide.createIcons();
    }
  }
}

async function excluirImplantacoesNoServidor(ids, csrfToken) {
  if (!ids || ids.length === 0) {
    showToast("Nenhuma implantação selecionada para exclusão.", "info");
    return;
  }
  if (
    !confirm(`Tem certeza que deseja excluir ${ids.length} implantação(ões)?`)
  )
    return;

  try {
    const response = await fetch("gerenciar_implantacoes.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        acao: "excluir_implantacoes",
        ids_implantacoes: ids,
        csrf_token: csrfToken,
      }),
    });
    const data = await response.json();
    if (!response.ok)
      throw new Error(data.message || `HTTP ${response.status}`);

    if (data.success) {
      showToast(data.message || "Implantação(ões) excluída(s)!", "success");
      if (data.csrf_token) {
        const csrfInput = document.getElementById("csrf-token-implantacoes");
        if (csrfInput) csrfInput.value = data.csrf_token;
      }
      carregarImplantacoesDoServidor(
        currentDisplayYearImplantacoes,
        currentDisplayMonthImplantacoes
      );
    } else {
      showToast(
        "Erro ao excluir implantações: " +
          (data.message || "Erro do servidor."),
        "error"
      );
    }
  } catch (error) {
    showToast(
      `Erro crítico ao excluir implantações: ${error.message}.`,
      "error"
    );
  }
}

// --- Event Listeners e Código Executado no DOMContentLoaded ---
document.addEventListener("DOMContentLoaded", async function () {
  // --- Lógica de Turnos (Original e Adaptada para Lucide) ---
  const displayElementInit = document.getElementById(
    "current-month-year-display"
  );
  if (displayElementInit) {
    // Verifica se o elemento existe antes de tentar acessar dataset
    if (displayElementInit.dataset.year && displayElementInit.dataset.month) {
      currentDisplayYear = parseInt(displayElementInit.dataset.year, 10);
      currentDisplayMonth = parseInt(displayElementInit.dataset.month, 10);
    } else {
      const today = new Date();
      currentDisplayYear = today.getFullYear();
      currentDisplayMonth = today.getMonth() + 1;
    }
    updateCurrentMonthYearDisplay();
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
      updateFeriadosDisplay(currentDisplayYear, currentDisplayMonth);
      carregarFeriados(currentDisplayYear, currentDisplayMonth);
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
      updateFeriadosDisplay(currentDisplayYear, currentDisplayMonth);
      carregarFeriados(currentDisplayYear, currentDisplayMonth);
    });
  }

  const btnSalvarTurnos = document.getElementById("save-shifts-button");
  if (btnSalvarTurnos) {
    btnSalvarTurnos.addEventListener("click", () => {
      const csrfTokenEl = document.getElementById("csrf-token-shifts");
      const csrfToken = csrfTokenEl ? csrfTokenEl.value : null;
      if (!csrfToken && shiftsTableElement) {
        showToast(
          "Erro de segurança (token turnos ausente). Recarregue.",
          "error"
        );
        return;
      }
      const dados = coletarDadosDaTabelaDeTurnos();
      if (dados.length > 0) {
        salvarDadosTurnosNoServidor(dados, csrfToken);
      } else {
        const tbody = document.querySelector("#shifts-table-main tbody");
        if (tbody && tbody.querySelector("td[colspan='5']")) {
          showToast("Adicione um turno para salvar.", "info");
        } else if (tbody && tbody.rows.length > 0) {
          showToast(
            "Nenhum turno válido para salvar. Preencha todos os campos ou corrija erros de data.",
            "warning",
            7000
          );
        } else {
          showToast("Adicione um turno para salvar.", "info");
        }
      }
    });
  }

  const btnAdicionarTurno = document.getElementById("add-shift-row-button");
  if (btnAdicionarTurno) {
    btnAdicionarTurno.addEventListener("click", async function () {
      const tbody = document.querySelector("#shifts-table-main tbody");
      if (!tbody) return;
      const placeholderRow = tbody.querySelector("td[colspan='5']");
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

      let cell = nLinha.insertCell();
      let input = document.createElement("input");
      input.type = "checkbox";
      input.className = "shift-select-checkbox";
      cell.appendChild(input);

      cell = nLinha.insertCell();
      input = document.createElement("input");
      input.type = "text";
      input.className = "shift-date form-control-filter";
      input.placeholder = "dd/Mês";
      cell.appendChild(input);
      input.focus();

      cell = nLinha.insertCell();
      input = document.createElement("input");
      input.type = "time";
      input.className = "shift-time-inicio form-control-filter";
      cell.appendChild(input);

      cell = nLinha.insertCell();
      input = document.createElement("input");
      input.type = "time";
      input.className = "shift-time-fim form-control-filter";
      cell.appendChild(input);

      cell = nLinha.insertCell();
      const selColab = document.createElement("select");
      selColab.className =
        "shift-employee shift-employee-select form-control-filter";
      popularSelectColaborador(selColab);
      cell.appendChild(selColab);
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
        showToast("Erro de segurança. Recarregue.", "error");
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

  // --- Lógica Google Calendar ---
  const urlParams = new URLSearchParams(window.location.search);
  const gcalStatus = urlParams.get("gcal_status"),
    gcalMsg = urlParams.get("gcal_msg");
  const connBtn = document.getElementById("connect-gcal-btn"),
    discBtn = document.getElementById("disconnect-gcal-btn");

  function checkGCalConnectionStatus() {
    if (!connBtn || !discBtn) return;
    let isConn = false;
    const gcalAlreadyConnected = localStorage.getItem(
      "gcal_connected_simposto"
    );

    if (gcalStatus === "success") {
      showToast("Google Calendar conectado com sucesso!", "success");
      localStorage.setItem("gcal_connected_simposto", "true");
      isConn = true;
    } else if (gcalStatus === "error") {
      showToast(
        "Falha conexão GCal: " + (gcalMsg || "Tente novamente."),
        "error"
      );
      localStorage.removeItem("gcal_connected_simposto");
    } else if (gcalStatus === "disconnected") {
      showToast("Google Calendar desconectado.", "info");
      localStorage.removeItem("gcal_connected_simposto");
    } else if (gcalAlreadyConnected === "true") {
      isConn = true;
    }
    if (connBtn) connBtn.style.display = isConn ? "none" : "flex";
    if (discBtn) discBtn.style.display = isConn ? "flex" : "none";
  }
  if (document.querySelector(".gcal-sidebar-button-container")) {
    checkGCalConnectionStatus();
  }
  if (discBtn)
    discBtn.addEventListener("click", () => {
      if (confirm("Desconectar sua conta do Google Calendar?")) {
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

  document.querySelectorAll(".input-field").forEach((inp) => {
    if (inp.tagName.toLowerCase() === "select") return;
    const chk = () => inp.classList.toggle("has-val", inp.value.trim() !== "");
    inp.addEventListener("blur", chk);
    inp.addEventListener("input", chk);
    setTimeout(chk, 100);
  });

  // --- Inicialização para Observações Gerais ---
  const salvarObsBtn = document.getElementById("salvar-observacoes-gerais-btn");
  const obsGeralTextarea = document.getElementById(
    "observacoes-gerais-textarea"
  );
  if (salvarObsBtn && obsGeralTextarea) {
    carregarObservacaoGeral();
    salvarObsBtn.addEventListener("click", salvarObservacaoGeral);
  }

  // --- Inicialização para Feriados ---
  const feriadosTable = document.getElementById("feriados-table");
  if (feriadosTable) {
    currentDisplayYearFeriados = currentDisplayYear; // Sincroniza com ano dos turnos
    currentDisplayMonthFeriados = currentDisplayMonth; // Sincroniza com mês dos turnos
    updateFeriadosDisplay(
      currentDisplayYearFeriados,
      currentDisplayMonthFeriados
    );
    carregarFeriados(currentDisplayYearFeriados, currentDisplayMonthFeriados);
  }

  // --- Inicialização para Implantações ---
  const implantacoesTableElement = document.getElementById(
    "implantacoes-table-main"
  );
  const displayElementImplantacoes = document.getElementById(
    "current-month-year-implantacoes-display"
  );

  if (implantacoesTableElement && displayElementImplantacoes) {
    const todayImplantacoes = new Date();
    currentDisplayYearImplantacoes = todayImplantacoes.getFullYear();
    currentDisplayMonthImplantacoes = todayImplantacoes.getMonth() + 1;

    if (
      displayElementImplantacoes.dataset.year &&
      displayElementImplantacoes.dataset.month
    ) {
      currentDisplayYearImplantacoes = parseInt(
        displayElementImplantacoes.dataset.year,
        10
      );
      currentDisplayMonthImplantacoes = parseInt(
        displayElementImplantacoes.dataset.month,
        10
      );
    }
    updateCurrentMonthYearDisplayImplantacoes();
    carregarImplantacoesDoServidor(
      currentDisplayYearImplantacoes,
      currentDisplayMonthImplantacoes
    );
  }

  const prevMonthBtnImp = document.getElementById(
    "prev-month-implantacoes-button"
  );
  if (prevMonthBtnImp) {
    prevMonthBtnImp.addEventListener("click", () => {
      currentDisplayMonthImplantacoes--;
      if (currentDisplayMonthImplantacoes < 1) {
        currentDisplayMonthImplantacoes = 12;
        currentDisplayYearImplantacoes--;
      }
      updateCurrentMonthYearDisplayImplantacoes();
      carregarImplantacoesDoServidor(
        currentDisplayYearImplantacoes,
        currentDisplayMonthImplantacoes
      );
    });
  }
  const nextMonthBtnImp = document.getElementById(
    "next-month-implantacoes-button"
  );
  if (nextMonthBtnImp) {
    nextMonthBtnImp.addEventListener("click", () => {
      currentDisplayMonthImplantacoes++;
      if (currentDisplayMonthImplantacoes > 12) {
        currentDisplayMonthImplantacoes = 1;
        currentDisplayYearImplantacoes++;
      }
      updateCurrentMonthYearDisplayImplantacoes();
      carregarImplantacoesDoServidor(
        currentDisplayYearImplantacoes,
        currentDisplayMonthImplantacoes
      );
    });
  }

  const btnAddImplantacao = document.getElementById(
    "add-implantacao-row-button"
  );
  if (btnAddImplantacao) {
    btnAddImplantacao.addEventListener("click", function () {
      const tbody = document.querySelector("#implantacoes-table-main tbody");
      if (!tbody) return;
      const placeholderRow = tbody.querySelector("td[colspan='4']");
      if (placeholderRow) tbody.innerHTML = "";

      const newId = "new-" + Date.now();
      const nLinha = tbody.insertRow();
      nLinha.setAttribute("data-implantacao-id", newId);

      let cell = nLinha.insertCell();
      let inputChk = document.createElement("input");
      inputChk.type = "checkbox";
      inputChk.className = "implantacao-select-checkbox";
      cell.appendChild(inputChk);

      cell = nLinha.insertCell();
      let inputDI = document.createElement("input");
      inputDI.type = "date";
      inputDI.className = "implantacao-data-inicio form-control-filter";
      cell.appendChild(inputDI);

      cell = nLinha.insertCell();
      let inputDF = document.createElement("input");
      inputDF.type = "date";
      inputDF.className = "implantacao-data-fim form-control-filter";
      cell.appendChild(inputDF);

      cell = nLinha.insertCell();
      let inputObs = document.createElement("input");
      inputObs.type = "text";
      inputObs.className = "implantacao-observacoes form-control-filter";
      inputObs.placeholder = "Descrição da implantação";
      cell.appendChild(inputObs);
      inputDI.focus();
    });
  }

  const btnSalvarImplantacoes = document.getElementById(
    "save-implantacoes-button"
  );
  if (btnSalvarImplantacoes) {
    btnSalvarImplantacoes.addEventListener("click", () => {
      const csrfTokenEl = document.getElementById("csrf-token-implantacoes");
      const csrfToken = csrfTokenEl ? csrfTokenEl.value : null;
      if (!csrfToken) {
        showToast("Erro de segurança. Recarregue.", "error");
        return;
      }
      const dados = coletarDadosDaTabelaDeImplantacoes();
      if (dados.length > 0) {
        salvarDadosImplantacoesNoServidor(dados, csrfToken);
      } else {
        const tbody = document.querySelector("#implantacoes-table-main tbody");
        if (tbody && tbody.querySelector("td[colspan='4']")) {
          showToast("Adicione uma implantação para salvar.", "info");
        } else if (tbody && tbody.rows.length > 0) {
          showToast(
            "Nenhuma implantação válida para salvar. Preencha as datas corretamente ou corrija erros.",
            "warning",
            7000
          );
        } else {
          showToast("Adicione uma implantação para salvar.", "info");
        }
      }
    });
  }

  const chkAllImp = document.getElementById("select-all-implantacoes");
  if (chkAllImp) {
    chkAllImp.addEventListener("change", () => {
      document
        .querySelectorAll(
          "#implantacoes-table-main .implantacao-select-checkbox"
        )
        .forEach((c) => (c.checked = chkAllImp.checked));
    });
  }

  const btnDelSelImp = document.getElementById(
    "delete-selected-implantacoes-button"
  );
  if (btnDelSelImp) {
    btnDelSelImp.addEventListener("click", () => {
      const csrfTokenEl = document.getElementById("csrf-token-implantacoes");
      const csrfToken = csrfTokenEl ? csrfTokenEl.value : null;
      if (!csrfToken) {
        showToast("Erro de segurança.", "error");
        return;
      }
      const ids = [];
      let removidoLocal = false;
      document
        .querySelectorAll(
          "#implantacoes-table-main .implantacao-select-checkbox:checked"
        )
        .forEach((c) => {
          const tr = c.closest("tr");
          if (tr) {
            const id = tr.getAttribute("data-implantacao-id");
            if (id && !id.startsWith("new-")) ids.push(id);
            else if (id && id.startsWith("new-")) {
              tr.remove();
              removidoLocal = true;
            }
          }
        });
      if (ids.length > 0) excluirImplantacoesNoServidor(ids, csrfToken);
      else if (removidoLocal) {
        showToast("Linhas novas (não salvas) foram removidas.", "info");
        const tbody = document.querySelector("#implantacoes-table-main tbody");
        if (tbody && tbody.rows.length === 0) popularTabelaImplantacoes([]);
      } else
        showToast(
          "Nenhuma implantação existente selecionada para exclusão.",
          "info"
        );
    });
  }

  // Inicializar Lucide Icons se estiverem presentes no DOM
  if (typeof lucide !== "undefined") {
    lucide.createIcons();
  }
}); // Fim do DOMContentLoaded
