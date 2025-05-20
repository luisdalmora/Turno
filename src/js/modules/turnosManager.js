// src/js/modules/turnosManager.js
import {
  showToast,
  popularSelectColaborador,
  calcularDuracaoDecimal,
  tailwindInputClasses,
  tailwindSelectClasses,
  tailwindCheckboxClasses,
  buscarEArmazenarColaboradores,
  todosOsColaboradores as colaboradoresGlobais, // Importa a variável para uso direto
} from "./utils.js";
import * as state from "./state.js"; // Para acessar currentDisplayYear, currentDisplayMonth
import { updateCurrentMonthYearDisplayTurnos } from "./uiUpdater.js";

console.log("[DEBUG] turnosManager.js: Módulo carregado.");

let employeeHoursChartInstance = null;

async function popularTabelaTurnos(turnos) {
  const corpoTabela = document.querySelector("#shifts-table-main tbody");
  if (!corpoTabela) {
    console.error(
      "[DEBUG] Tabela de turnos (tbody) não encontrada (turnosManager.js)."
    );
    return;
  }
  corpoTabela.innerHTML = "";
  const chkAll = document.getElementById("select-all-shifts");
  if (chkAll) chkAll.checked = false;

  // Garante que colaboradores foram buscados se ainda não estiverem
  if (
    colaboradoresGlobais.length === 0 ||
    !colaboradoresGlobais[0] ||
    !colaboradoresGlobais[0].hasOwnProperty("id")
  ) {
    await buscarEArmazenarColaboradores(); // Espera a busca, se necessário
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
    inputData.value = turno.data_formatada || turno.data; // data_formatada vem do PHP GET
    inputData.placeholder = "dd/Mês";
    cellData.appendChild(inputData);

    const cellInicio = nLinha.insertCell();
    cellInicio.className = "p-1";
    const inputInicio = document.createElement("input");
    inputInicio.type = "time";
    inputInicio.className = `shift-time-inicio ${tailwindInputClasses}`;
    inputInicio.value = turno.hora_inicio
      ? turno.hora_inicio.substring(0, 5)
      : ""; // hora_inicio vem formatada do PHP GET
    cellInicio.appendChild(inputInicio);

    const cellFim = nLinha.insertCell();
    cellFim.className = "p-1";
    const inputFim = document.createElement("input");
    inputFim.type = "time";
    inputFim.className = `shift-time-fim ${tailwindInputClasses}`;
    inputFim.value = turno.hora_fim ? turno.hora_fim.substring(0, 5) : ""; // hora_fim vem formatada do PHP GET
    cellFim.appendChild(inputFim);

    const cellColab = nLinha.insertCell();
    cellColab.className = "p-1";
    const selColab = document.createElement("select");
    selColab.className = `shift-employee shift-employee-select ${tailwindSelectClasses}`;
    popularSelectColaborador(selColab, turno.colaborador, colaboradoresGlobais); // Passa o array de colaboradores
    cellColab.appendChild(selColab);
  });
}

function coletarDadosDaTabelaDeTurnos() {
  const linhas = document.querySelectorAll("#shifts-table-main tbody tr");
  const dados = [];
  const displayElement = document.getElementById("current-month-year-display");
  const anoTabela =
    displayElement && displayElement.dataset.year
      ? parseInt(displayElement.dataset.year, 10)
      : state.currentDisplayYear;

  let erroValidacaoGeralTurnos = false;

  linhas.forEach((linha, index) => {
    if (linha.cells.length === 1 && linha.cells[0].colSpan > 1) return; // Pula linha de "nenhum turno"

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
        // Verifica se não é um turno noturno válido
        showToast(
          `Atenção (linha ${
            index + 1
          }): Turno para ${colabVal} em ${dataVal} tem Hora Fim (${fimVal}) não posterior à Hora Início (${inicioVal}). Este turno não será salvo.`,
          "warning",
          7000
        );
        erroValidacaoGeralTurnos = true;
        return; // Pula este turno
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
      // Se algum campo estiver preenchido mas não todos
      showToast(
        `Linha de turno ${
          index + 1
        } incompleta não será salva. Preencha todos os campos: Dia, Início, Fim e Colaborador.`,
        "warning",
        5000
      );
      erroValidacaoGeralTurnos = true;
    }
  });

  if (erroValidacaoGeralTurnos && dados.length === 0) return []; // Se houve erros e nenhum dado válido foi coletado
  if (erroValidacaoGeralTurnos && dados.length > 0) return null; // Indica que houve erros, mas alguns dados válidos podem existir (js original retornava null)
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

  try {
    const response = await fetch("salvar_turnos.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });

    let data;
    if (!response.ok) {
      let errorMsg = `Erro do servidor: HTTP ${response.status}`;
      try {
        data = await response.json();
        errorMsg = data.message || errorMsg;
      } catch (e) {
        const errText = await response.text().catch(() => "");
        errorMsg = errText.substring(0, 150) || errorMsg;
      }
      throw new Error(errorMsg);
    }
    data = await response.json();

    if (data.success) {
      showToast(data.message || "Turnos salvos com sucesso!", "success");
      if (data.csrf_token) {
        const csrfInput = document.getElementById("csrf-token-shifts");
        if (csrfInput) csrfInput.value = data.csrf_token;
      }
      // Recarrega os turnos do mês/ano atual
      await carregarTurnosDoServidor(
        state.currentDisplayYear,
        state.currentDisplayMonth,
        true
      );
    } else {
      showToast(
        "Erro ao salvar: " + (data.message || "Erro desconhecido do servidor."),
        "error"
      );
    }
  } catch (error) {
    console.error(
      "[DEBUG] Erro crítico ao salvar turnos (turnosManager.js):",
      error
    );
    showToast(`Erro crítico ao salvar: ${error.message}`, "error");
  } finally {
    if (btnSalvar) {
      btnSalvar.disabled = false;
      btnSalvar.innerHTML = originalButtonHTML;
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
    let data;
    if (!response.ok) {
      let errorMsg = `Erro do servidor: HTTP ${response.status}`;
      try {
        data = await response.json();
        errorMsg = data.message || errorMsg;
      } catch (e) {
        const errText = await response.text().catch(() => "");
        errorMsg = errText.substring(0, 150) || errorMsg;
      }
      throw new Error(errorMsg);
    }
    data = await response.json();

    if (data.success) {
      showToast(data.message || "Turno(s) excluído(s) com sucesso!", "success");
      if (data.csrf_token) {
        const csrfInput = document.getElementById("csrf-token-shifts");
        if (csrfInput) csrfInput.value = data.csrf_token;
      }
      carregarTurnosDoServidor(
        state.currentDisplayYear,
        state.currentDisplayMonth,
        true
      );
    } else {
      showToast(
        "Erro ao excluir: " + (data.message || "Erro do servidor."),
        "error"
      );
    }
  } catch (error) {
    console.error(
      "[DEBUG] Erro crítico ao excluir turnos (turnosManager.js):",
      error
    );
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
      // Evita mostrar 0.00h
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

export async function carregarTurnosDoServidor(
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
    console.error(
      "[DEBUG] Elemento tbody da tabela de turnos não encontrado (turnosManager.js)."
    );
    return;
  }

  try {
    const response = await fetch(`salvar_turnos.php?ano=${ano}&mes=${mes}`);
    let data;
    if (!response.ok) {
      let errorMsg = `Erro HTTP ${response.status}`;
      try {
        data = await response.json();
        errorMsg = data.message || errorMsg;
      } catch (e) {
        const errText = await response.text().catch(() => "");
        errorMsg = errText.substring(0, 150) || errorMsg;
      }
      throw new Error(errorMsg);
    }
    data = await response.json();

    if (data.success) {
      if (data.csrf_token && csrfInputOriginal)
        csrfInputOriginal.value = data.csrf_token;
      await popularTabelaTurnos(data.data || []); // await aqui se popularTabelaTurnos for async (por causa de buscarColaboradores)
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
    console.error(
      `[DEBUG] Erro ao carregar turnos para ${mes}/${ano} (turnosManager.js):`,
      error
    );
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

export function initTurnosEventListeners() {
  const btnSalvarTurnos = document.getElementById("save-shifts-button");
  if (btnSalvarTurnos) {
    btnSalvarTurnos.addEventListener("click", () => {
      console.log("[DEBUG] Botão 'Salvar Turnos' clicado (turnosManager.js).");
      const csrfTokenEl = document.getElementById("csrf-token-shifts");
      const csrfToken = csrfTokenEl ? csrfTokenEl.value : null;
      if (!csrfToken) {
        showToast(
          "Erro de segurança (token turnos ausente). Recarregue a página.",
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
        if (placeholderVisivel || (tbody && tbody.rows.length === 0)) {
          showToast("Adicione pelo menos um turno para salvar.", "info");
        } else {
          showToast(
            "Nenhum turno válido para salvar. Verifique as linhas.",
            "warning"
          );
        }
      } else if (dados === null) {
        console.log(
          "[DEBUG] Coleta de dados de turnos retornou null (erro de validação) (turnosManager.js)."
        );
      } else {
        console.error(
          "[DEBUG] coletarDadosDaTabelaDeTurnos retornou valor inesperado:",
          dados
        );
        showToast("Erro interno ao coletar dados dos turnos.", "error");
      }
    });
  }

  const btnAdicionarTurno = document.getElementById("add-shift-row-button");
  if (btnAdicionarTurno) {
    btnAdicionarTurno.addEventListener("click", async function () {
      // async por causa de buscarColaboradores
      console.log(
        "[DEBUG] Botão 'Adicionar Turno' clicado (turnosManager.js)."
      );
      const tbody = document.querySelector("#shifts-table-main tbody");
      if (!tbody) return;
      const placeholderRow = tbody.querySelector("td[colspan='5']");
      if (placeholderRow) tbody.innerHTML = "";

      if (
        colaboradoresGlobais.length === 0 ||
        !colaboradoresGlobais[0] ||
        !colaboradoresGlobais[0].hasOwnProperty("id")
      ) {
        await buscarEArmazenarColaboradores();
      }

      const newId = "new-" + Date.now();
      const nLinha = tbody.insertRow();
      nLinha.className = "bg-white hover:bg-gray-50";
      nLinha.setAttribute("data-turno-id", newId);

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
      popularSelectColaborador(selColab, null, colaboradoresGlobais);
      cell.appendChild(selColab);
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
      console.log(
        "[DEBUG] Botão 'Excluir Turnos Selecionados' clicado (turnosManager.js)."
      );
      const csrfTokenEl = document.getElementById("csrf-token-shifts");
      const csrfToken = csrfTokenEl ? csrfTokenEl.value : null;
      if (!csrfToken) {
        showToast("Erro de segurança. Recarregue a página.", "error");
        return;
      }
      const idsParaExcluirServidor = [];
      let linhasNovasRemovidasLocalmente = 0;

      document
        .querySelectorAll("#shifts-table-main .shift-select-checkbox:checked")
        .forEach((c) => {
          const tr = c.closest("tr");
          if (tr) {
            const id = tr.getAttribute("data-turno-id");
            if (id && !id.startsWith("new-")) {
              idsParaExcluirServidor.push(id);
            } else if (id && id.startsWith("new-")) {
              tr.remove();
              linhasNovasRemovidasLocalmente++;
            }
          }
        });

      if (idsParaExcluirServidor.length > 0) {
        excluirTurnosNoServidor(idsParaExcluirServidor, csrfToken);
      }
      if (linhasNovasRemovidasLocalmente > 0) {
        showToast(
          `${linhasNovasRemovidasLocalmente} linha(s) nova(s) (não salva(s)) foram removida(s).`,
          "info"
        );
        const tbody = document.querySelector("#shifts-table-main tbody");
        if (tbody && tbody.rows.length === 0) {
          popularTabelaTurnos([]);
        } // Para exibir a mensagem "Nenhum turno"
      }
      if (
        idsParaExcluirServidor.length === 0 &&
        linhasNovasRemovidasLocalmente === 0
      ) {
        showToast("Nenhum turno selecionado para exclusão.", "info");
      }
      if (chkAllShifts) chkAllShifts.checked = false;
    });
  }
}
