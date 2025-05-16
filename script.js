// script.js

// --- Variáveis Globais ---
let todosOsColaboradores = [];
let employeeHoursChartInstance = null;
let activeToastTimeout = null;

let currentDisplayYear = new Date().getFullYear();
let currentDisplayMonth = new Date().getMonth() + 1; // Meses são 1-12

// Para Implantações (serão sincronizadas com Turnos)
let currentDisplayYearImplantacoes = currentDisplayYear;
let currentDisplayMonthImplantacoes = currentDisplayMonth;

// Para Feriados (serão sincronizados com Turnos)
let currentDisplayYearFeriados = currentDisplayYear;
let currentDisplayMonthFeriados = currentDisplayMonth;

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

// --- Classes Tailwind para Inputs Dinâmicos ---
const tailwindInputClasses =
  "form-input p-1.5 border border-gray-300 rounded-md text-xs w-full box-border focus:ring-indigo-500 focus:border-indigo-500";
const tailwindSelectClasses =
  "form-select p-1.5 border border-gray-300 rounded-md text-xs w-full box-border focus:ring-indigo-500 focus:border-indigo-500";
const tailwindCheckboxClasses =
  "form-checkbox h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500";

// --- Funções Utilitárias ---
function showToast(message, type = "info", duration = 3500) {
  const existingToast = document.getElementById("toast-notification");
  if (existingToast) {
    existingToast.remove();
    if (activeToastTimeout) clearTimeout(activeToastTimeout);
  }
  const toast = document.createElement("div");
  toast.id = "toast-notification";
  let bgColor = "bg-blue-500"; // Tailwind: info
  if (type === "success") bgColor = "bg-green-500"; // Tailwind: success
  if (type === "error") bgColor = "bg-red-500"; // Tailwind: error
  if (type === "warning") bgColor = "bg-yellow-500 text-gray-800"; // Tailwind: warning

  toast.className = `fixed bottom-5 left-1/2 transform -translate-x-1/2 px-6 py-3 rounded-lg shadow-lg text-white text-sm font-medium z-[1060] transition-all duration-300 ease-out opacity-0 translate-y-10 ${bgColor}`;
  toast.textContent = message;
  document.body.appendChild(toast);

  requestAnimationFrame(() => {
    toast.classList.remove("opacity-0", "translate-y-10");
    toast.classList.add("opacity-100", "translate-y-0");
  });

  activeToastTimeout = setTimeout(() => {
    toast.classList.remove("opacity-100", "translate-y-0");
    toast.classList.add("opacity-0", "translate-y-10");
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
  selectElement.innerHTML =
    '<option value="" class="text-gray-500">Selecione...</option>';
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
    // Turno que vira a noite
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
    r.className = "bg-white";
    const c = r.insertCell();
    c.colSpan = 5;
    c.className = "p-2 text-center text-gray-500 text-sm";
    c.textContent = "Nenhum turno programado para este período.";
    return;
  }

  turnos.forEach((turno) => {
    const nLinha = corpoTabela.insertRow();
    nLinha.className = "bg-white hover:bg-gray-50";
    nLinha.setAttribute("data-turno-id", turno.id);

    if (turno.google_calendar_event_id) {
      const gcalIdInput = document.createElement("input");
      gcalIdInput.type = "hidden";
      gcalIdInput.className = "gcal-event-id-hidden";
      gcalIdInput.value = turno.google_calendar_event_id;
      nLinha.appendChild(gcalIdInput); // Anexar à linha para fácil acesso
    }
    const gcalSyncInput = document.createElement("input");
    gcalSyncInput.type = "hidden";
    gcalSyncInput.className = "gcal-sync-needed-hidden";
    gcalSyncInput.value = "false"; // Por padrão, não precisa sincronizar ao carregar
    nLinha.appendChild(gcalSyncInput);

    const cellCheckbox = nLinha.insertCell();
    cellCheckbox.className = "p-2 text-center";
    const inputCheckbox = document.createElement("input");
    inputCheckbox.type = "checkbox";
    inputCheckbox.className = `shift-select-checkbox ${tailwindCheckboxClasses}`;
    inputCheckbox.value = turno.id;
    cellCheckbox.appendChild(inputCheckbox);

    const cellData = nLinha.insertCell();
    cellData.className = "p-1";
    const inputData = document.createElement("input");
    inputData.type = "text";
    inputData.className = `shift-date ${tailwindInputClasses}`;
    inputData.value = turno.data_formatada || turno.data; // data_formatada é 'dd/MM'
    inputData.placeholder = "dd/Mês";
    cellData.appendChild(inputData);

    const cellInicio = nLinha.insertCell();
    cellInicio.className = "p-1";
    const inputInicio = document.createElement("input");
    inputInicio.type = "time";
    inputInicio.className = `shift-time-inicio ${tailwindInputClasses}`;
    inputInicio.value = turno.hora_inicio
      ? turno.hora_inicio.substring(0, 5)
      : "";
    cellInicio.appendChild(inputInicio);

    const cellFim = nLinha.insertCell();
    cellFim.className = "p-1";
    const inputFim = document.createElement("input");
    inputFim.type = "time";
    inputFim.className = `shift-time-fim ${tailwindInputClasses}`;
    inputFim.value = turno.hora_fim ? turno.hora_fim.substring(0, 5) : "";
    cellFim.appendChild(inputFim);

    const cellColab = nLinha.insertCell();
    cellColab.className = "p-1";
    const selColab = document.createElement("select");
    selColab.className = `shift-employee shift-employee-select ${tailwindSelectClasses}`;
    popularSelectColaborador(selColab, turno.colaborador);
    cellColab.appendChild(selColab);

    // Marcar para sincronizar com GCal se qualquer campo for alterado
    [inputData, inputInicio, inputFim, selColab].forEach((el) => {
      el.addEventListener("change", () => {
        const syncHiddenInput = nLinha.querySelector(
          ".gcal-sync-needed-hidden"
        );
        if (syncHiddenInput) syncHiddenInput.value = "true";
        console.log(
          `Turno ID ${turno.id} marcado para sync GCal devido a mudança em ${el.className}`
        );
      });
    });
  });
}

function coletarDadosDaTabelaDeTurnos() {
  const linhas = document.querySelectorAll("#shifts-table-main tbody tr");
  const dados = [];
  const displayElement = document.getElementById("current-month-year-display");
  // Usa o ano e mês do display principal de turnos como referência
  const anoTabela =
    displayElement && displayElement.dataset.year
      ? parseInt(displayElement.dataset.year, 10)
      : currentDisplayYear;

  let erroValidacaoGeralTurnos = false;

  linhas.forEach((linha) => {
    if (linha.cells.length === 1 && linha.cells[0].colSpan > 1) return;

    const dataIn = linha.querySelector(".shift-date");
    const horaInicioIn = linha.querySelector(".shift-time-inicio");
    const horaFimIn = linha.querySelector(".shift-time-fim");
    const colabSel = linha.querySelector(".shift-employee-select");
    const idOrig = linha.getAttribute("data-turno-id");

    const gcalIdOriginalEl = linha.querySelector(".gcal-event-id-hidden");
    const gcalSyncNeededEl = linha.querySelector(".gcal-sync-needed-hidden");

    const dataVal = dataIn ? dataIn.value.trim() : "";
    const inicioVal = horaInicioIn ? horaInicioIn.value.trim() : "";
    const fimVal = horaFimIn ? horaFimIn.value.trim() : "";
    const colabVal = colabSel ? colabSel.value.trim() : "";

    const gcalEventIdOriginal = gcalIdOriginalEl
      ? gcalIdOriginalEl.value
      : null;
    const gcalSyncNeeded = gcalSyncNeededEl
      ? gcalSyncNeededEl.value === "true"
      : false;

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
        showToast(
          `Atenção: Turno para ${colabVal} em ${dataVal} tem Hora Fim (${fimVal}) não posterior à Hora Início (${inicioVal}). Este turno não será salvo.`,
          "warning",
          7000
        );
        erroValidacaoGeralTurnos = true;
        return;
      }
      dados.push({
        id: idOrig && !idOrig.startsWith("new-") ? idOrig : null,
        data: dataVal, // Será 'dd/Mês'
        hora_inicio: inicioVal,
        hora_fim: fimVal,
        colaborador: colabVal,
        ano: anoTabela.toString(), // Ano de referência para o backend
        google_calendar_event_id_original: gcalEventIdOriginal,
        gcal_sync_needed: gcalSyncNeeded,
      });
    } else if (
      !(dataVal === "" && inicioVal === "" && fimVal === "" && colabVal === "")
    ) {
      showToast(
        "Linha de turno incompleta não será salva. Preencha todos os campos: Dia, Início, Fim e Colaborador.",
        "warning",
        5000
      );
      erroValidacaoGeralTurnos = true;
    }
  });
  console.log(
    "[DEBUG] Dados coletados para salvar turnos:",
    JSON.parse(JSON.stringify(dados))
  );
  if (erroValidacaoGeralTurnos && dados.length === 0) return [];
  return dados;
}

async function salvarDadosTurnosNoServidor(dadosTurnos, csrfToken) {
  const btnSalvar = document.getElementById("save-shifts-button");
  const originalButtonHTML = btnSalvar ? btnSalvar.innerHTML : "";
  if (btnSalvar) {
    btnSalvar.disabled = true;
    btnSalvar.innerHTML = `<i data-lucide="loader-circle" class="lucide-spin w-4 h-4 mr-1.5"></i> Salvando...`;
    if (typeof lucide !== "undefined") lucide.createIcons();
  }

  const payload = {
    acao: "salvar_turnos",
    turnos: dadosTurnos,
    csrf_token: csrfToken,
  };
  console.log(
    "[DEBUG] Payload para salvar turnos:",
    JSON.parse(JSON.stringify(payload))
  );

  try {
    const response = await fetch("salvar_turnos.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    const data = await response.json();
    console.log("[DEBUG] Resposta do servidor (salvar turnos):", data);

    if (!response.ok) {
      showToast(
        `Erro do servidor: ${response.status} - ${
          data.message || "Erro desconhecido no servidor."
        }`,
        "error"
      );
      // Não lançar erro aqui para permitir que o finally execute e restaure o botão
      // throw new Error(data.message || `Erro HTTP: ${response.status}`);
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
        "Erro ao salvar: " + (data.message || "Erro desconhecido do servidor."),
        "error"
      );
    }
  } catch (error) {
    console.error("Erro crítico ao salvar turnos:", error);
    showToast(`Erro crítico ao salvar: ${error.message}`, "error");
  } finally {
    if (btnSalvar) {
      btnSalvar.disabled = false;
      btnSalvar.innerHTML = `<i data-lucide="save" class="w-4 h-4 mr-1.5"></i> Salvar`;
      if (typeof lucide !== "undefined") lucide.createIcons();
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

  console.log("[DEBUG] Excluindo turnos IDs:", ids, "com token:", csrfToken);
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
    console.log("[DEBUG] Resposta do servidor (excluir turnos):", data);

    if (!response.ok) {
      showToast(
        `Erro do servidor: ${response.status} - ${
          data.message || "Erro desconhecido no servidor."
        }`,
        "error"
      );
      // throw new Error(data.message || `HTTP ${response.status}`);
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

function atualizarTabelaResumoColaboradores(turnos) {
  const tbody = document.querySelector("#employee-summary-table tbody");
  if (!tbody) return;
  tbody.innerHTML = "";
  if (!turnos || turnos.length === 0) {
    const r = tbody.insertRow();
    r.className = "bg-white";
    const c = r.insertCell();
    c.colSpan = 2;
    c.className = "p-2 text-center text-gray-500 text-sm";
    c.textContent = "Sem dados para resumo.";
    return;
  }
  const resumo = {};
  turnos.forEach((t) => {
    if (!t.colaborador || !t.hora_inicio || !t.hora_fim) return;
    if (!resumo[t.colaborador]) resumo[t.colaborador] = 0;
    resumo[t.colaborador] += calcularDuracaoDecimal(t.hora_inicio, t.hora_fim);
  });

  const colaboradoresOrdenados = Object.keys(resumo).sort();

  for (const colab of colaboradoresOrdenados) {
    if (resumo[colab] > 0.005) {
      const tot = resumo[colab].toFixed(2);
      const r = tbody.insertRow();
      r.className = "bg-white hover:bg-gray-50";
      const cellColab = r.insertCell();
      cellColab.className = "p-2 text-sm text-gray-700";
      cellColab.textContent = colab;
      const cellHoras = r.insertCell();
      cellHoras.className = "p-2 text-sm text-gray-700 text-right";
      cellHoras.textContent = tot.replace(".", ",") + "h";
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

  const labels = Object.keys(resumo)
    .filter((colab) => resumo[colab] > 0.005)
    .sort();
  const dataPoints = labels.map((l) => parseFloat(resumo[l].toFixed(2)));

  if (labels.length === 0) {
    if (employeeHoursChartInstance) {
      employeeHoursChartInstance.destroy();
      employeeHoursChartInstance = null;
    }
    const context = ctx.getContext("2d");
    context.clearRect(0, 0, ctx.width, ctx.height);
    context.textAlign = "center";
    context.textBaseline = "middle";
    context.font = "14px Poppins, sans-serif";
    context.fillStyle = "#6b7280";
    context.fillText(
      "Sem dados para exibir no gráfico.",
      ctx.width / 2,
      ctx.height / 2
    );
    return;
  }

  const tailwindColors = [
    "rgba(59, 130, 246, 0.7)",
    "rgba(16, 185, 129, 0.7)",
    "rgba(234, 179, 8, 0.7)",
    "rgba(239, 68, 68, 0.7)",
    "rgba(139, 92, 246, 0.7)",
    "rgba(236, 72, 153, 0.7)",
    "rgba(249, 115, 22, 0.7)",
  ];
  const borderColors = tailwindColors.map((color) => color.replace("0.7", "1"));

  if (employeeHoursChartInstance) {
    employeeHoursChartInstance.data.labels = labels;
    employeeHoursChartInstance.data.datasets[0].data = dataPoints;
    employeeHoursChartInstance.data.datasets[0].backgroundColor =
      tailwindColors.slice(0, dataPoints.length);
    employeeHoursChartInstance.data.datasets[0].borderColor =
      borderColors.slice(0, dataPoints.length);
    employeeHoursChartInstance.update();
  } else {
    employeeHoursChartInstance = new Chart(ctx, {
      type: "bar",
      data: {
        labels,
        datasets: [
          {
            label: "Total de Horas",
            data: dataPoints,
            backgroundColor: tailwindColors.slice(0, dataPoints.length),
            borderColor: borderColors.slice(0, dataPoints.length),
            borderWidth: 1,
            borderRadius: 4,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: {
            beginAtZero: true,
            title: {
              display: true,
              text: "Horas",
              font: { family: "Poppins" },
            },
            ticks: { font: { family: "Poppins" } },
          },
          x: {
            title: { display: false },
            ticks: { font: { family: "Poppins" } },
          },
        },
        plugins: {
          legend: {
            display: dataPoints.length > 1,
            position: "bottom",
            labels: { font: { family: "Poppins" } },
          },
          title: { display: false },
          tooltip: {
            bodyFont: { family: "Poppins" },
            titleFont: { family: "Poppins" },
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
    displayElement.innerHTML = `<i data-lucide="list-todo" class="w-5 h-5 mr-2 text-blue-600"></i> Turnos - ${monthName} ${currentDisplayYear}`;
    displayElement.dataset.year = currentDisplayYear;
    displayElement.dataset.month = currentDisplayMonth;
    if (typeof lucide !== "undefined") lucide.createIcons();
  }
  if (summaryPeriodElement)
    summaryPeriodElement.textContent = nomesMeses[currentDisplayMonth] || "";
}

async function carregarTurnosDoServidor(
  ano,
  mes,
  atualizarResumosGlobais = true
) {
  const shiftsTableBody = document.querySelector("#shifts-table-main tbody");
  const csrfInputOriginal = document.getElementById("csrf-token-shifts");

  if (shiftsTableBody) {
    shiftsTableBody.innerHTML = `<tr><td colspan="5" class="p-2 text-center text-gray-500 text-sm">Carregando turnos... <i data-lucide="loader-circle" class="lucide-spin inline-block w-4 h-4"></i></td></tr>`;
    if (typeof lucide !== "undefined") lucide.createIcons();
  } else {
    console.error("Elemento tbody da tabela de turnos não encontrado.");
    return;
  }

  try {
    const response = await fetch(`salvar_turnos.php?ano=${ano}&mes=${mes}`);
    if (!response.ok) {
      const errorText = await response.text();
      throw new Error(
        `Erro HTTP ${response.status}: ${errorText.substring(0, 150)}`
      );
    }
    const data = await response.json();

    if (data.success) {
      if (data.csrf_token && csrfInputOriginal)
        csrfInputOriginal.value = data.csrf_token;
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
    showToast(
      `Erro ao carregar turnos: ${error.message}. Verifique o console.`,
      "error"
    );
    await popularTabelaTurnos([]);
    if (atualizarResumosGlobais) {
      atualizarTabelaResumoColaboradores([]);
      atualizarGraficoResumoHoras([]);
    }
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
  saveButton.innerHTML = `<i data-lucide="loader-circle" class="lucide-spin w-4 h-4 mr-1.5"></i> Salvando...`;
  if (typeof lucide !== "undefined") lucide.createIcons();

  const payload = {
    observacao: textarea.value,
    csrf_token: csrfTokenInput.value,
  };
  console.log("[DEBUG] Payload para salvar observação:", payload);
  try {
    const response = await fetch("gerenciar_observacao_geral.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    const data = await response.json();
    console.log("[DEBUG] Resposta do servidor (salvar observação):", data);
    if (!response.ok) {
      showToast(
        `Erro do servidor: ${response.status} - ${
          data.message || "Erro desconhecido."
        }`,
        "error"
      );
    }
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
    saveButton.innerHTML = `<i data-lucide="save" class="w-4 h-4 mr-1.5"></i> Salvar Observações`;
    if (typeof lucide !== "undefined") lucide.createIcons();
  }
}

// --- Funções para Feriados ---
function updateFeriadosDisplay(ano, mes) {
  const displayElement = document.getElementById("feriados-mes-ano-display");
  if (displayElement) {
    const monthName = nomesMeses[mes] || `Mês ${mes}`;
    displayElement.innerHTML = `<i data-lucide="calendar-heart" class="w-4 h-4 mr-2 text-blue-600"></i> Feriados - ${monthName} ${ano}`;
    if (typeof lucide !== "undefined") lucide.createIcons();
  }
}

async function carregarFeriados(ano, mes) {
  const tbody = document.querySelector("#feriados-table tbody");
  if (!tbody) return;
  tbody.innerHTML = `<tr><td colspan="2" class="p-2 text-center text-gray-500 text-sm">Carregando... <i data-lucide="loader-circle" class="lucide-spin inline-block w-4 h-4"></i></td></tr>`;
  if (typeof lucide !== "undefined") lucide.createIcons();
  try {
    const response = await fetch(`carregar_feriados.php?ano=${ano}&mes=${mes}`);
    const data = await response.json();
    tbody.innerHTML = "";
    if (data.success && data.feriados) {
      if (data.feriados.length === 0) {
        const r = tbody.insertRow();
        r.className = "bg-white";
        const c = r.insertCell();
        c.colSpan = 2;
        c.className = "p-2 text-center text-gray-500 text-sm";
        c.textContent = "Nenhum feriado encontrado para este mês.";
      } else {
        data.feriados.forEach((feriado) => {
          const r = tbody.insertRow();
          r.className = "bg-white hover:bg-gray-50";
          const cellData = r.insertCell();
          cellData.className = "p-2 text-sm text-gray-700";
          cellData.textContent = feriado.data;
          const cellObs = r.insertCell();
          cellObs.className = "p-2 text-sm text-gray-700";
          cellObs.textContent = feriado.observacao;
        });
      }
    } else {
      showToast(data.message || "Erro ao carregar feriados.", "warning");
      const r = tbody.insertRow();
      r.className = "bg-white";
      const c = r.insertCell();
      c.colSpan = 2;
      c.className = "p-2 text-center text-red-500 text-sm";
      c.textContent = data.message || "Erro ao carregar feriados.";
    }
  } catch (error) {
    console.error("Erro ao buscar feriados:", error);
    tbody.innerHTML = `<tr><td colspan="2" class="p-2 text-center text-red-500 text-sm">Erro de conexão.</td></tr>`;
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
    displayElement.innerHTML = `<i data-lucide="settings-2" class="w-5 h-5 mr-2 text-blue-600"></i> Implantações - ${monthName} ${currentDisplayYearImplantacoes}`;
    displayElement.dataset.year = currentDisplayYearImplantacoes;
    displayElement.dataset.month = currentDisplayMonthImplantacoes;
    if (typeof lucide !== "undefined") lucide.createIcons();
  }
}

async function carregarImplantacoesDoServidor(ano, mes) {
  const tableBody = document.querySelector("#implantacoes-table-main tbody");
  const csrfTokenInput = document.getElementById("csrf-token-implantacoes");
  if (!tableBody || !csrfTokenInput) return;
  tableBody.innerHTML = `<tr><td colspan="4" class="p-2 text-center text-gray-500 text-sm">Carregando... <i data-lucide="loader-circle" class="lucide-spin inline-block w-4 h-4"></i></td></tr>`;
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
    r.className = "bg-white";
    const c = r.insertCell();
    c.colSpan = 4;
    c.className = "p-2 text-center text-gray-500 text-sm";
    c.textContent = "Nenhuma implantação programada para este período.";
    return;
  }
  implantacoes.forEach((item) => {
    const nLinha = corpoTabela.insertRow();
    nLinha.className = "bg-white hover:bg-gray-50";
    nLinha.setAttribute("data-implantacao-id", item.id);
    const cellCheckbox = nLinha.insertCell();
    cellCheckbox.className = "p-2 text-center";
    const inputCheckbox = document.createElement("input");
    inputCheckbox.type = "checkbox";
    inputCheckbox.className = `implantacao-select-checkbox ${tailwindCheckboxClasses}`;
    inputCheckbox.value = item.id;
    cellCheckbox.appendChild(inputCheckbox);
    const cellDataInicio = nLinha.insertCell();
    cellDataInicio.className = "p-1";
    const inputDataInicio = document.createElement("input");
    inputDataInicio.type = "date";
    inputDataInicio.className = `implantacao-data-inicio ${tailwindInputClasses}`;
    inputDataInicio.value = item.data_inicio || "";
    cellDataInicio.appendChild(inputDataInicio);
    const cellDataFim = nLinha.insertCell();
    cellDataFim.className = "p-1";
    const inputDataFim = document.createElement("input");
    inputDataFim.type = "date";
    inputDataFim.className = `implantacao-data-fim ${tailwindInputClasses}`;
    inputDataFim.value = item.data_fim || "";
    cellDataFim.appendChild(inputDataFim);
    const cellObs = nLinha.insertCell();
    cellObs.className = "p-1";
    const inputObs = document.createElement("input");
    inputObs.type = "text";
    inputObs.className = `implantacao-observacoes ${tailwindInputClasses}`;
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
          }'. Não será salvo.`,
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
  console.log(
    "[DEBUG] Dados coletados para salvar implantações:",
    JSON.parse(JSON.stringify(dados))
  );
  if (erroValidacaoGeral && dados.length === 0) return [];
  return dados;
}

async function salvarDadosImplantacoesNoServidor(dadosImplantacoes, csrfToken) {
  const btnSalvar = document.getElementById("save-implantacoes-button");
  const originalButtonHtml = btnSalvar ? btnSalvar.innerHTML : "";
  if (btnSalvar) {
    btnSalvar.disabled = true;
    btnSalvar.innerHTML = `<i data-lucide="loader-circle" class="lucide-spin w-4 h-4 mr-1.5"></i> Salvando...`;
    if (typeof lucide !== "undefined") lucide.createIcons();
  }
  const payload = {
    acao: "salvar_implantacoes",
    implantacoes: dadosImplantacoes,
    csrf_token: csrfToken,
  };
  console.log(
    "[DEBUG] Payload para salvar implantações:",
    JSON.parse(JSON.stringify(payload))
  );
  try {
    const response = await fetch("gerenciar_implantacoes.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    const data = await response.json();
    console.log("[DEBUG] Resposta do servidor (salvar implantações):", data);
    if (!response.ok) {
      showToast(
        `Erro do servidor: ${response.status} - ${
          data.message || "Erro desconhecido."
        }`,
        "error"
      );
    }
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
      btnSalvar.innerHTML = `<i data-lucide="save" class="w-4 h-4 mr-1.5"></i> Salvar`;
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
  console.log(
    "[DEBUG] Excluindo implantações IDs:",
    ids,
    "com token:",
    csrfToken
  );
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
    console.log("[DEBUG] Resposta do servidor (excluir implantações):", data);
    if (!response.ok) {
      showToast(
        `Erro do servidor: ${response.status} - ${
          data.message || "Erro desconhecido."
        }`,
        "error"
      );
    }
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
  // Inicialização de Datas Globais
  const today = new Date();
  currentDisplayYear = today.getFullYear();
  currentDisplayMonth = today.getMonth() + 1;

  const displayElementInit = document.getElementById(
    "current-month-year-display"
  );
  if (displayElementInit) {
    // Se o display de turnos existir, usa seus data attributes se presentes
    currentDisplayYear =
      parseInt(displayElementInit.dataset.year, 10) || currentDisplayYear;
    currentDisplayMonth =
      parseInt(displayElementInit.dataset.month, 10) || currentDisplayMonth;
  }
  // Sincroniza todas as datas iniciais
  currentDisplayYearImplantacoes = currentDisplayYear;
  currentDisplayMonthImplantacoes = currentDisplayMonth;
  currentDisplayYearFeriados = currentDisplayYear;
  currentDisplayMonthFeriados = currentDisplayMonth;

  // Atualiza os displays
  updateCurrentMonthYearDisplay();
  updateCurrentMonthYearDisplayImplantacoes();
  updateFeriadosDisplay(
    currentDisplayYearFeriados,
    currentDisplayMonthFeriados
  );

  // Carregamento inicial de dados
  if (document.getElementById("shifts-table-main")) {
    await buscarEArmazenarColaboradores();
    carregarTurnosDoServidor(currentDisplayYear, currentDisplayMonth);
  }
  if (document.getElementById("feriados-table")) {
    carregarFeriados(currentDisplayYearFeriados, currentDisplayMonthFeriados);
  }
  if (document.getElementById("implantacoes-table-main")) {
    carregarImplantacoesDoServidor(
      currentDisplayYearImplantacoes,
      currentDisplayMonthImplantacoes
    );
  }

  // Botões de Navegação de Mês para Turnos
  const prevMonthButton = document.getElementById("prev-month-button");
  if (prevMonthButton) {
    prevMonthButton.addEventListener("click", () => {
      currentDisplayMonth--;
      if (currentDisplayMonth < 1) {
        currentDisplayMonth = 12;
        currentDisplayYear--;
      }
      // Sincroniza todas as datas
      currentDisplayMonthImplantacoes = currentDisplayMonth;
      currentDisplayYearImplantacoes = currentDisplayYear;
      currentDisplayMonthFeriados = currentDisplayMonth;
      currentDisplayYearFeriados = currentDisplayYear;
      // Atualiza e carrega tudo
      updateCurrentMonthYearDisplay();
      carregarTurnosDoServidor(currentDisplayYear, currentDisplayMonth, true);
      updateCurrentMonthYearDisplayImplantacoes();
      carregarImplantacoesDoServidor(
        currentDisplayYearImplantacoes,
        currentDisplayMonthImplantacoes
      );
      updateFeriadosDisplay(
        currentDisplayYearFeriados,
        currentDisplayMonthFeriados
      );
      carregarFeriados(currentDisplayYearFeriados, currentDisplayMonthFeriados);
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
      currentDisplayMonthImplantacoes = currentDisplayMonth;
      currentDisplayYearImplantacoes = currentDisplayYear;
      currentDisplayMonthFeriados = currentDisplayMonth;
      currentDisplayYearFeriados = currentDisplayYear;
      updateCurrentMonthYearDisplay();
      carregarTurnosDoServidor(currentDisplayYear, currentDisplayMonth, true);
      updateCurrentMonthYearDisplayImplantacoes();
      carregarImplantacoesDoServidor(
        currentDisplayYearImplantacoes,
        currentDisplayMonthImplantacoes
      );
      updateFeriadosDisplay(
        currentDisplayYearFeriados,
        currentDisplayMonthFeriados
      );
      carregarFeriados(currentDisplayYearFeriados, currentDisplayMonthFeriados);
    });
  }

  // Botões de Navegação de Mês para Implantações
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
      currentDisplayMonth = currentDisplayMonthImplantacoes;
      currentDisplayYear = currentDisplayYearImplantacoes;
      currentDisplayMonthFeriados = currentDisplayMonthImplantacoes;
      currentDisplayYearFeriados = currentDisplayYearImplantacoes;
      updateCurrentMonthYearDisplayImplantacoes();
      carregarImplantacoesDoServidor(
        currentDisplayYearImplantacoes,
        currentDisplayMonthImplantacoes
      );
      updateCurrentMonthYearDisplay();
      carregarTurnosDoServidor(currentDisplayYear, currentDisplayMonth, true);
      updateFeriadosDisplay(
        currentDisplayYearFeriados,
        currentDisplayMonthFeriados
      );
      carregarFeriados(currentDisplayYearFeriados, currentDisplayMonthFeriados);
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
      currentDisplayMonth = currentDisplayMonthImplantacoes;
      currentDisplayYear = currentDisplayYearImplantacoes;
      currentDisplayMonthFeriados = currentDisplayMonthImplantacoes;
      currentDisplayYearFeriados = currentDisplayYearImplantacoes;
      updateCurrentMonthYearDisplayImplantacoes();
      carregarImplantacoesDoServidor(
        currentDisplayYearImplantacoes,
        currentDisplayMonthImplantacoes
      );
      updateCurrentMonthYearDisplay();
      carregarTurnosDoServidor(currentDisplayYear, currentDisplayMonth, true);
      updateFeriadosDisplay(
        currentDisplayYearFeriados,
        currentDisplayMonthFeriados
      );
      carregarFeriados(currentDisplayYearFeriados, currentDisplayMonthFeriados);
    });
  }

  // Botões de Ação para Turnos
  const btnSalvarTurnos = document.getElementById("save-shifts-button");
  if (btnSalvarTurnos) {
    btnSalvarTurnos.addEventListener("click", () => {
      const csrfTokenEl = document.getElementById("csrf-token-shifts");
      const csrfToken = csrfTokenEl ? csrfTokenEl.value : null;
      if (!csrfToken) {
        showToast(
          "Erro de segurança (token turnos ausente). Recarregue.",
          "error"
        );
        return;
      }
      const dados = coletarDadosDaTabelaDeTurnos();
      if (dados && dados.length > 0) {
        salvarDadosTurnosNoServidor(dados, csrfToken);
      } else if (dados && dados.length === 0) {
        const tbody = document.querySelector("#shifts-table-main tbody");
        const placeholderVisivel =
          tbody && tbody.querySelector("td[colspan='5']");
        if (placeholderVisivel || tbody.rows.length === 0) {
          showToast("Adicione um turno para salvar.", "info");
        } else {
          showToast(
            "Nenhum turno válido para salvar. Verifique os campos ou corrija erros.",
            "warning",
            7000
          );
        }
      } else {
        console.error("[DEBUG] coletarDadosDaTabelaDeTurnos retornou null.");
        showToast("Erro interno ao coletar dados dos turnos.", "error");
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
      nLinha.className = "bg-white hover:bg-gray-50";
      nLinha.setAttribute("data-turno-id", newId);
      const gcalIdInput = document.createElement("input");
      gcalIdInput.type = "hidden";
      gcalIdInput.className = "gcal-event-id-hidden";
      gcalIdInput.value = "";
      nLinha.appendChild(gcalIdInput);
      const gcalSyncInput = document.createElement("input");
      gcalSyncInput.type = "hidden";
      gcalSyncInput.className = "gcal-sync-needed-hidden";
      gcalSyncInput.value = "true";
      nLinha.appendChild(gcalSyncInput);
      let cell = nLinha.insertCell();
      cell.className = "p-2 text-center";
      let inputChk = document.createElement("input");
      inputChk.type = "checkbox";
      inputChk.className = `shift-select-checkbox ${tailwindCheckboxClasses}`;
      cell.appendChild(inputChk);
      cell = nLinha.insertCell();
      cell.className = "p-1";
      let inputData = document.createElement("input");
      inputData.type = "text";
      inputData.className = `shift-date ${tailwindInputClasses}`;
      inputData.placeholder = "dd/Mês";
      cell.appendChild(inputData);
      inputData.focus();
      cell = nLinha.insertCell();
      cell.className = "p-1";
      let inputInicio = document.createElement("input");
      inputInicio.type = "time";
      inputInicio.className = `shift-time-inicio ${tailwindInputClasses}`;
      cell.appendChild(inputInicio);
      cell = nLinha.insertCell();
      cell.className = "p-1";
      let inputFim = document.createElement("input");
      inputFim.type = "time";
      inputFim.className = `shift-time-fim ${tailwindInputClasses}`;
      cell.appendChild(inputFim);
      cell = nLinha.insertCell();
      cell.className = "p-1";
      const selColab = document.createElement("select");
      selColab.className = `shift-employee shift-employee-select ${tailwindSelectClasses}`;
      popularSelectColaborador(selColab);
      cell.appendChild(selColab);
      [inputData, inputInicio, inputFim, selColab].forEach((el) => {
        el.addEventListener("change", () => {
          if (gcalSyncInput) gcalSyncInput.value = "true";
        });
      });
    });
  }
  const chkAllShifts = document.getElementById("select-all-shifts");
  if (chkAllShifts) {
    chkAllShifts.addEventListener("change", () => {
      document
        .querySelectorAll("#shifts-table-main .shift-select-checkbox")
        .forEach((c) => (c.checked = chkAllShifts.checked));
    });
  }
  const btnDelSelShifts = document.getElementById(
    "delete-selected-shifts-button"
  );
  if (btnDelSelShifts) {
    btnDelSelShifts.addEventListener("click", () => {
      const csrfTokenEl = document.getElementById("csrf-token-shifts");
      const csrfToken = csrfTokenEl ? csrfTokenEl.value : null;
      if (!csrfToken) {
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

  // Botões de Ação para Implantações
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
      nLinha.className = "bg-white hover:bg-gray-50";
      nLinha.setAttribute("data-implantacao-id", newId);
      let cell = nLinha.insertCell();
      cell.className = "p-2 text-center";
      let inputChk = document.createElement("input");
      inputChk.type = "checkbox";
      inputChk.className = `implantacao-select-checkbox ${tailwindCheckboxClasses}`;
      cell.appendChild(inputChk);
      cell = nLinha.insertCell();
      cell.className = "p-1";
      let inputDI = document.createElement("input");
      inputDI.type = "date";
      inputDI.className = `implantacao-data-inicio ${tailwindInputClasses}`;
      cell.appendChild(inputDI);
      inputDI.focus();
      cell = nLinha.insertCell();
      cell.className = "p-1";
      let inputDF = document.createElement("input");
      inputDF.type = "date";
      inputDF.className = `implantacao-data-fim ${tailwindInputClasses}`;
      cell.appendChild(inputDF);
      cell = nLinha.insertCell();
      cell.className = "p-1";
      let inputObs = document.createElement("input");
      inputObs.type = "text";
      inputObs.className = `implantacao-observacoes ${tailwindInputClasses}`;
      inputObs.placeholder = "Descrição da implantação";
      cell.appendChild(inputObs);
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
      if (dados && dados.length > 0) {
        salvarDadosImplantacoesNoServidor(dados, csrfToken);
      } else if (dados && dados.length === 0) {
        const tbody = document.querySelector("#implantacoes-table-main tbody");
        const placeholderVisivel =
          tbody && tbody.querySelector("td[colspan='4']");
        if (placeholderVisivel || tbody.rows.length === 0) {
          showToast("Adicione uma implantação para salvar.", "info");
        } else {
          showToast(
            "Nenhuma implantação válida para salvar. Verifique as datas.",
            "warning",
            7000
          );
        }
      } else {
        console.error(
          "[DEBUG] coletarDadosDaTabelaDeImplantacoes retornou null."
        );
        showToast("Erro interno ao coletar dados das implantações.", "error");
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

  // Observações Gerais
  const salvarObsBtn = document.getElementById("salvar-observacoes-gerais-btn");
  const obsGeralTextarea = document.getElementById(
    "observacoes-gerais-textarea"
  );
  if (salvarObsBtn && obsGeralTextarea) {
    carregarObservacaoGeral();
    salvarObsBtn.addEventListener("click", salvarObservacaoGeral);
  }

  // Google Calendar
  const connBtn = document.getElementById("connect-gcal-btn");
  const discBtn = document.getElementById("disconnect-gcal-btn");
  function checkGCalConnectionStatus() {
    /* ... (lógica de checkGCalConnectionStatus mantida) ... */ if (
      !connBtn ||
      !discBtn
    )
      return;
    let isConn = false;
    const gcalAlreadyConnected = localStorage.getItem(
      "gcal_connected_simposto"
    );
    const urlParamsGCal = new URLSearchParams(window.location.search);
    const gcalStatusParam = urlParamsGCal.get("gcal_status");
    const gcalMsgParam = urlParamsGCal.get("gcal_msg");

    if (gcalStatusParam === "success") {
      showToast("Google Calendar conectado com sucesso!", "success");
      localStorage.setItem("gcal_connected_simposto", "true");
      isConn = true;
    } else if (gcalStatusParam === "error") {
      showToast(
        "Falha conexão GCal: " +
          (decodeURIComponent(gcalMsgParam || "") || "Tente novamente."),
        "error"
      );
      localStorage.removeItem("gcal_connected_simposto");
    } else if (gcalStatusParam === "disconnected") {
      showToast("Google Calendar desconectado.", "info");
      localStorage.removeItem("gcal_connected_simposto");
    } else if (gcalAlreadyConnected === "true") {
      isConn = true;
    }

    if (connBtn) connBtn.style.display = isConn ? "none" : "flex";
    if (discBtn) discBtn.style.display = isConn ? "flex" : "none";

    if (gcalStatusParam || gcalMsgParam) {
      const cleanUrl =
        window.location.protocol +
        "//" +
        window.location.host +
        window.location.pathname;
      window.history.replaceState({ path: cleanUrl }, "", cleanUrl);
    }
  }
  if (connBtn && discBtn) {
    checkGCalConnectionStatus();
  } // Chama a função se os botões existirem
  if (discBtn) {
    discBtn.addEventListener("click", () => {
      if (confirm("Desconectar sua conta do Google Calendar?")) {
        localStorage.removeItem("gcal_connected_simposto");
        window.location.href = "google_revoke_token.php";
      }
    });
  }

  // Logout
  const logoutLk = document.getElementById("logout-link");
  if (logoutLk) {
    logoutLk.addEventListener("click", (e) => {
      e.preventDefault();
      showToast("Saindo do sistema...", "info", 1500);
      setTimeout(() => {
        if (logoutLk.href) window.location.href = logoutLk.href;
      }, 1500);
    });
  }

  // Backup Banco de Dados
  const backupDbBtn = document.getElementById("backup-db-btn");
  const backupModalBackdrop = document.getElementById("backup-modal-backdrop");
  const backupModalMessage = document.getElementById("backup-modal-message");
  const backupModalCloseBtn = document.getElementById("backup-modal-close-btn");
  const backupProgressBarContainer = document.getElementById(
    "backup-progress-bar-container"
  );
  const backupProgressBar = document.getElementById("backup-progress-bar");
  const backupDownloadLink = document.getElementById("backup-download-link");
  let originalBackupBtnHTML = "";
  const csrfTokenBackupInput = document.getElementById("csrf-token-backup");

  function showBackupModal(
    message,
    showProgress = false,
    isError = false,
    isSuccess = false
  ) {
    if (backupModalMessage) {
      backupModalMessage.textContent = message;
      backupModalMessage.className = "mt-2 text-sm";
      if (isError)
        backupModalMessage.classList.add("text-red-600", "font-semibold");
      else if (isSuccess)
        backupModalMessage.classList.add("text-green-600", "font-semibold");
      else backupModalMessage.classList.add("text-gray-600");
    }
    if (backupProgressBarContainer)
      backupProgressBarContainer.style.display = showProgress
        ? "block"
        : "none";
    if (backupProgressBar && showProgress) {
      backupProgressBar.style.width = "0%";
      backupProgressBar.textContent = "";
      backupProgressBar.classList.add("indeterminate");
    } else if (backupProgressBar) {
      backupProgressBar.classList.remove("indeterminate");
    }

    if (backupDownloadLink) backupDownloadLink.classList.add("hidden");
    if (backupModalCloseBtn) backupModalCloseBtn.style.display = "none";
    if (backupModalBackdrop) backupModalBackdrop.classList.add("show");
  }

  function hideBackupModal() {
    if (backupModalBackdrop) backupModalBackdrop.classList.remove("show");
    if (backupDbBtn && originalBackupBtnHTML) {
      backupDbBtn.disabled = false;
      backupDbBtn.innerHTML = originalBackupBtnHTML;
      if (typeof lucide !== "undefined") lucide.createIcons();
    }
  }

  if (backupModalCloseBtn) {
    backupModalCloseBtn.addEventListener("click", hideBackupModal);
  }

  if (backupDbBtn && csrfTokenBackupInput) {
    originalBackupBtnHTML = backupDbBtn.innerHTML;
    backupDbBtn.addEventListener("click", async function (event) {
      event.preventDefault();
      if (backupDbBtn.disabled) return;
      if (
        !confirm("Tem certeza que deseja iniciar o backup do banco de dados?")
      )
        return;

      showBackupModal("Iniciando backup, por favor aguarde...", true);
      backupDbBtn.disabled = true;
      backupDbBtn.innerHTML = `<i data-lucide="loader-circle" class="animate-spin w-4 h-4 mr-2"></i> Processando...`;
      if (typeof lucide !== "undefined") lucide.createIcons();
      const csrfToken = csrfTokenBackupInput.value;
      try {
        const response = await fetch("backup_database.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            action: "create_backup",
            csrf_token_backup: csrfToken,
          }),
        });
        let data;
        const contentType = response.headers.get("content-type");
        if (contentType && contentType.indexOf("application/json") !== -1) {
          data = await response.json();
        } else {
          const textResponse = await response.text();
          console.error("Resposta não JSON:", textResponse);
          throw new Error(
            `Servidor respondeu incorretamente. Status: ${response.status}`
          );
        }
        console.log("[DEBUG] Resposta backup_database.php:", data);

        if (response.ok && data.success) {
          showBackupModal(
            data.message || "Backup concluído!",
            false,
            false,
            true
          );
          if (backupModalCloseBtn)
            backupModalCloseBtn.style.display = "inline-flex";
          if (data.download_url && backupDownloadLink) {
            backupDownloadLink.href = data.download_url;
            backupDownloadLink.classList.remove("hidden");
          } else if (data.filename && !data.download_url) {
            backupDownloadLink.href = `download_backup_file.php?file=${encodeURIComponent(
              data.filename
            )}`;
            backupDownloadLink.classList.remove("hidden");
          } else {
            showToast("URL de download não fornecida.", "warning");
          }
        } else {
          const errorMsg = data.message || "Falha no backup.";
          showBackupModal("Erro: " + errorMsg, false, true, false);
          if (backupModalCloseBtn)
            backupModalCloseBtn.style.display = "inline-flex";
          showToast("Falha no backup: " + errorMsg, "error", 7000);
        }
      } catch (error) {
        console.error("Erro requisição de backup:", error);
        showBackupModal(
          "Erro de comunicação ao tentar backup. Verifique o console.",
          false,
          true,
          false
        );
        if (backupModalCloseBtn)
          backupModalCloseBtn.style.display = "inline-flex";
        showToast("Erro de comunicação: " + error.message, "error");
      }
      // O finally para restaurar o botão foi movido para hideBackupModal
      // ou quando o usuário clica em baixar/fechar.
    });
  }

  // Inicialização final do Lucide Icons
  if (typeof lucide !== "undefined") {
    lucide.createIcons();
  }
}); // Fim do DOMContentLoaded
