// script.js

let todosOsColaboradores = []; // Cache para a lista de colaboradores

// Função para buscar e armazenar colaboradores
async function buscarEArmazenarColaboradores() {
  if (todosOsColaboradores.length > 0) {
    return todosOsColaboradores;
  }
  try {
    const response = await fetch("obter_colaboradores.php");
    if (!response.ok) {
      console.error(
        `Erro HTTP ao buscar colaboradores: ${response.status} ${response.statusText}`
      );
      const errorText = await response.text();
      console.error("Detalhe do erro (colaboradores):", errorText);
      return [];
    }
    const data = await response.json();
    if (data.success && data.colaboradores) {
      todosOsColaboradores = data.colaboradores;
      return todosOsColaboradores;
    } else {
      console.error(
        "Falha ao carregar colaboradores do backend:",
        data.message || "Resposta não indica sucesso."
      );
      return [];
    }
  } catch (error) {
    console.error("Erro na requisição fetch de colaboradores:", error);
    return [];
  }
}

// Função para popular um elemento <select> com colaboradores
function popularSelectColaborador(selectElement, valorSelecionado = null) {
  selectElement.innerHTML = '<option value="">Selecione...</option>';
  if (todosOsColaboradores.length === 0) {
    // console.warn("Lista de colaboradores está vazia ao tentar popular o select.");
  }
  todosOsColaboradores.forEach((colab) => {
    const option = document.createElement("option");
    option.value = colab.nome_completo;
    option.textContent = colab.nome_completo;
    if (valorSelecionado && colab.nome_completo === valorSelecionado) {
      option.selected = true;
    }
    selectElement.appendChild(option);
  });
}

async function popularTabelaTurnos(turnos) {
  const corpoTabelaTurnos = document.querySelector("#shifts-table-may tbody");
  const cabecalhoCheckbox = document.getElementById("select-all-shifts");

  if (!corpoTabelaTurnos) return;
  corpoTabelaTurnos.innerHTML = "";
  if (cabecalhoCheckbox) cabecalhoCheckbox.checked = false;

  if (todosOsColaboradores.length === 0) {
    await buscarEArmazenarColaboradores();
  }

  if (!turnos || turnos.length === 0) {
    const linhaVazia = corpoTabelaTurnos.insertRow();
    const celulaVazia = linhaVazia.insertCell();
    celulaVazia.colSpan = 6;
    celulaVazia.textContent =
      "Nenhum turno programado para este período ou filtro.";
    celulaVazia.style.textAlign = "center";
    return;
  }

  turnos.forEach((turno) => {
    const novaLinha = corpoTabelaTurnos.insertRow();
    novaLinha.setAttribute("data-turno-id", turno.id);

    const celulaCheckbox = novaLinha.insertCell();
    const inputCheckbox = document.createElement("input");
    inputCheckbox.type = "checkbox";
    inputCheckbox.className = "shift-select-checkbox";
    inputCheckbox.value = turno.id;
    celulaCheckbox.appendChild(inputCheckbox);

    const celulaData = novaLinha.insertCell();
    const inputData = document.createElement("input");
    inputData.type = "text";
    inputData.className = "shift-date";
    inputData.value = turno.data;
    inputData.placeholder = "dd/Mês";
    celulaData.appendChild(inputData);

    const celulaHoraDuracao = novaLinha.insertCell();
    const inputHoraDuracao = document.createElement("input");
    inputHoraDuracao.type = "time";
    inputHoraDuracao.className = "shift-time";
    inputHoraDuracao.value = turno.hora;
    celulaHoraDuracao.appendChild(inputHoraDuracao);

    const celulaColaborador = novaLinha.insertCell();
    const selectColaborador = document.createElement("select");
    selectColaborador.className = "shift-employee shift-employee-select";
    popularSelectColaborador(selectColaborador, turno.colaborador);
    celulaColaborador.appendChild(selectColaborador);

    const celulaGoogleEventId = novaLinha.insertCell();
    celulaGoogleEventId.className = "shift-google-event-id";
    celulaGoogleEventId.textContent = turno.google_calendar_event_id || "N/A";

    const celulaAcoes = novaLinha.insertCell();
    celulaAcoes.className = "actions-cell";
    const btnEditar = document.createElement("button");
    btnEditar.innerHTML = '<i class="fas fa-edit"></i>';
    btnEditar.title = "Editar Turno";
    btnEditar.className = "btn-table-action edit";
    btnEditar.onclick = function () {
      alert("Editar turno ID: " + turno.id + " (implementar modal/lógica).");
    };
    celulaAcoes.appendChild(btnEditar);

    const btnExcluirLinha = document.createElement("button");
    btnExcluirLinha.innerHTML = '<i class="fas fa-trash-alt"></i>';
    btnExcluirLinha.title = "Excluir Turno";
    btnExcluirLinha.className = "btn-table-action delete";
    btnExcluirLinha.onclick = function () {
      excluirTurnosNoServidor([turno.id]);
    };
    celulaAcoes.appendChild(btnExcluirLinha);
  });
}

function salvarDadosTurnosNoServidor(dadosTurnos) {
  const payload = { acao: "salvar_turnos", turnos: dadosTurnos };
  fetch("salvar_turnos.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  })
    .then((response) => {
      if (!response.ok) {
        return response
          .json()
          .then((err) => {
            throw new Error(err.message || `HTTP ${response.status}`);
          })
          .catch(() => {
            throw new Error(`HTTP ${response.status}, resposta inválida.`);
          });
      }
      return response.json();
    })
    .then((data) => {
      if (data.success) {
        alert(data.message || "Turnos salvos!");
        popularTabelaTurnos(data.data);
        atualizarTabelaResumoColaboradores(data.data);
        atualizarGraficoResumoHoras(data.data);
      } else {
        alert("Erro ao salvar: " + (data.message || "Erro desconhecido."));
      }
    })
    .catch((error) => {
      console.error("Erro crítico ao salvar turnos:", error);
      alert(`Erro ao salvar: ${error.message}. Verifique o console.`);
    });
}

function coletarDadosDaTabelaDeTurnos() {
  const linhas = document.querySelectorAll("#shifts-table-may tbody tr");
  const dados = [];
  const tituloEl = document.getElementById("current-month-year");
  let anoTabela = new Date().getFullYear().toString();
  if (tituloEl && tituloEl.textContent) {
    const match = tituloEl.textContent.match(/(\d{4})/);
    if (match) anoTabela = match[1];
  }

  linhas.forEach((linha) => {
    if (linha.cells.length === 1 && linha.cells[0].colSpan > 1) return;
    const dataIn = linha.querySelector(".shift-date");
    const horaDurIn = linha.querySelector(".shift-time");
    const colabSel = linha.querySelector(".shift-employee-select");
    const idOrig = linha.getAttribute("data-turno-id");

    if (
      dataIn &&
      horaDurIn &&
      colabSel &&
      dataIn.value.trim() &&
      horaDurIn.value.trim() &&
      colabSel.value.trim()
    ) {
      dados.push({
        id: idOrig && !idOrig.startsWith("new-") ? idOrig : null,
        data: dataIn.value.trim(),
        hora: horaDurIn.value.trim(),
        colaborador: colabSel.value.trim(),
        ano: anoTabela,
      });
    } else if (
      !(
        dataIn.value.trim() === "" &&
        horaDurIn.value.trim() === "" &&
        colabSel.value.trim() === ""
      )
    ) {
      console.warn("Linha incompleta não salva:", {
        d: dataIn.value,
        h: horaDurIn.value,
        c: colabSel.value,
      });
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
    if (!t.colaborador || !t.hora) return;
    if (!resumo[t.colaborador]) resumo[t.colaborador] = 0;
    const p = String(t.hora).split(":");
    let h = parseInt(p[0], 10) || 0;
    let m = parseInt(p[1], 10) || 0;
    resumo[t.colaborador] += h + m / 60.0;
  });
  for (const colab in resumo) {
    const tot = resumo[colab].toFixed(2);
    const r = tbody.insertRow();
    r.insertCell().textContent = colab;
    r.insertCell().textContent = tot.replace(".", ",") + "h";
  }
}

let employeeHoursChartInstance = null;
function atualizarGraficoResumoHoras(turnos) {
  const ctx = document.getElementById("employee-hours-chart");
  if (!ctx) return;
  if (!turnos || turnos.length === 0) {
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
        ) || "Poppins"
      ).trim();
    context.fillStyle = (
      getComputedStyle(document.body).getPropertyValue(
        "--primary-text-color"
      ) || "#555"
    ).trim();
    context.fillText("Sem dados para gráfico.", ctx.width / 2, ctx.height / 2);
    return;
  }
  const resumo = {};
  turnos.forEach((t) => {
    if (!t.colaborador || !t.hora) return;
    if (!resumo[t.colaborador]) resumo[t.colaborador] = 0;
    const p = String(t.hora).split(":");
    let h = parseInt(p[0], 10) || 0;
    let m = parseInt(p[1], 10) || 0;
    resumo[t.colaborador] += h + m / 60.0;
  });
  const labels = Object.keys(resumo);
  const dataPoints = labels.map((l) => parseFloat(resumo[l].toFixed(2)));
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

function carregarTurnosDoServidor() {
  fetch("salvar_turnos.php")
    .then((response) => {
      if (!response.ok) {
        return response
          .json()
          .then((err) => {
            throw new Error(err.message || `HTTP ${response.status}`);
          })
          .catch(() => {
            throw new Error(`HTTP ${response.status}, resposta inválida.`);
          });
      }
      return response.json();
    })
    .then((data) => {
      if (data.success && data.data) {
        popularTabelaTurnos(data.data); // popularTabelaTurnos agora é async, mas aqui não precisamos esperar por ela necessariamente
        atualizarTabelaResumoColaboradores(data.data);
        atualizarGraficoResumoHoras(data.data);
      } else {
        console.warn("Aviso ao carregar turnos:", data.message);
        popularTabelaTurnos([]);
        atualizarTabelaResumoColaboradores([]);
        atualizarGraficoResumoHoras([]);
      }
    })
    .catch((error) => {
      console.error("Erro crítico ao carregar turnos:", error);
      alert(`Erro ao carregar turnos: ${error.message}. Verifique o console.`);
      popularTabelaTurnos([]);
      atualizarTabelaResumoColaboradores([]);
      atualizarGraficoResumoHoras([]);
    });
}

function excluirTurnosNoServidor(ids) {
  if (!ids || ids.length === 0) {
    alert("Nenhum turno selecionado.");
    return;
  }
  if (!confirm(`Excluir ${ids.length} turno(s)?`)) return;
  fetch("salvar_turnos.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ acao: "excluir_turnos", ids_turnos: ids }),
  })
    .then((response) => {
      if (!response.ok) {
        return response
          .json()
          .then((err) => {
            throw new Error(err.message || `HTTP ${response.status}`);
          })
          .catch(() => {
            throw new Error(`HTTP ${response.status}, resposta inválida.`);
          });
      }
      return response.json();
    })
    .then((data) => {
      if (data.success) {
        alert(data.message || "Excluído com sucesso!");
        carregarTurnosDoServidor();
      } else {
        alert("Erro ao excluir: " + (data.message || "Erro servidor."));
      }
    })
    .catch((error) => {
      console.error("Erro crítico ao excluir:", error);
      alert(`Erro ao excluir: ${error.message}. Verifique console.`);
    });
}

document.addEventListener("DOMContentLoaded", async function () {
  if (document.getElementById("shifts-table-may")) {
    await buscarEArmazenarColaboradores(); // Garante que colaboradores são carregados primeiro
    carregarTurnosDoServidor(); // Depois carrega os turnos que usarão a lista de colaboradores
  }

  const btnSalvarTurnos = document.getElementById("save-shifts-button");
  if (btnSalvarTurnos)
    btnSalvarTurnos.addEventListener("click", () => {
      const dados = coletarDadosDaTabelaDeTurnos();
      if (dados.length > 0) salvarDadosTurnosNoServidor(dados);
      else if (document.querySelector("#shifts-table-may tbody tr td[colspan]"))
        alert("Adicione um turno para salvar.");
      else alert("Nenhum turno válido para salvar. Preencha todos os campos.");
    });

  const btnAdicionarTurno = document.getElementById("add-shift-row-button");
  if (btnAdicionarTurno)
    btnAdicionarTurno.addEventListener("click", async function () {
      const tbody = document.querySelector("#shifts-table-may tbody");
      if (!tbody) return;
      const placeholderRow = tbody.querySelector("td[colspan]");
      if (placeholderRow) tbody.innerHTML = "";
      if (todosOsColaboradores.length === 0)
        await buscarEArmazenarColaboradores(); // Garante que temos a lista

      const newId = "new-" + Date.now();
      const nLinha = tbody.insertRow();
      nLinha.setAttribute("data-turno-id", newId);
      nLinha.insertCell().innerHTML =
        '<input type="checkbox" class="shift-select-checkbox">';
      nLinha.insertCell().innerHTML =
        '<input type="text" class="shift-date" placeholder="dd/Mês">';
      nLinha.insertCell().innerHTML = '<input type="time" class="shift-time">';

      const selColabCell = nLinha.insertCell();
      const selColab = document.createElement("select");
      selColab.className = "shift-employee shift-employee-select";
      popularSelectColaborador(selColab);
      selColabCell.appendChild(selColab);

      nLinha.insertCell().textContent = "Pendente"; // GCal ID
      nLinha.cells[nLinha.cells.length - 1].classList.add(
        "shift-google-event-id"
      );

      const acoesCell = nLinha.insertCell();
      acoesCell.className = "actions-cell";
      const btnDel = document.createElement("button");
      btnDel.innerHTML = '<i class="fas fa-trash-alt"></i>';
      btnDel.title = "Remover";
      btnDel.className = "btn-table-action delete";
      btnDel.onclick = () => {
        nLinha.remove();
        if (tbody.rows.length === 0) popularTabelaTurnos([]);
      };
      acoesCell.appendChild(btnDel);
      nLinha.querySelector(".shift-date").focus();
    });

  const chkAll = document.getElementById("select-all-shifts");
  if (chkAll)
    chkAll.addEventListener("change", () => {
      document
        .querySelectorAll(".shift-select-checkbox")
        .forEach((c) => (c.checked = chkAll.checked));
    });

  const btnDelSel = document.getElementById("delete-selected-shifts-button");
  if (btnDelSel)
    btnDelSel.addEventListener("click", () => {
      const ids = [];
      let removidoLocal = false;
      document
        .querySelectorAll(".shift-select-checkbox:checked")
        .forEach((c) => {
          const tr = c.closest("tr");
          const id = tr.getAttribute("data-turno-id");
          if (id && !id.startsWith("new-")) ids.push(id);
          else if (id && id.startsWith("new-")) {
            tr.remove();
            removidoLocal = true;
          }
        });
      if (ids.length > 0) excluirTurnosNoServidor(ids);
      else if (removidoLocal) {
        alert(
          "Linhas novas removidas. Nenhum turno existente selecionado para exclusão."
        );
        if (document.querySelector("#shifts-table-may tbody").rows.length === 0)
          popularTabelaTurnos([]);
      } else alert("Nenhum turno existente selecionado para exclusão.");
    });

  const urlParams = new URLSearchParams(window.location.search);
  const gcalStatus = urlParams.get("gcal_status"),
    gcalMsg = urlParams.get("gcal_msg");
  const statusMsgEl = document.getElementById("gcal-status-message");
  const connBtn = document.getElementById("connect-gcal-btn"),
    discBtn = document.getElementById("disconnect-gcal-btn");

  function checkGCalStatus() {
    if (!statusMsgEl || !connBtn || !discBtn) return;
    let isConn = false;
    if (gcalStatus === "success") {
      statusMsgEl.textContent = "Google Calendar conectado!";
      statusMsgEl.style.color = "var(--success-color)";
      isConn = true;
    } else if (gcalStatus === "error") {
      statusMsgEl.textContent = "Falha conexão GCal: " + (gcalMsg || "Tente.");
      statusMsgEl.style.color = "var(--danger-color)";
    } else if (gcalStatus === "disconnected") {
      statusMsgEl.textContent = "Google Calendar desconectado.";
      statusMsgEl.style.color = "var(--warning-color)";
    } else if (statusMsgEl.textContent.includes("Verifique o status")) {
      /* Mantém msg padrão */
    }

    // Lógica de visibilidade dos botões pode ser melhorada com estado persistente
    if (isConn) {
      connBtn.style.display = "none";
      discBtn.style.display = "inline-flex";
    } else {
      connBtn.style.display = "inline-flex";
      discBtn.style.display = "none";
    }
  }
  if (document.getElementById("google-calendar-section")) checkGCalStatus();
  if (discBtn)
    discBtn.addEventListener("click", () => {
      if (confirm("Desconectar sua conta do Google Calendar?"))
        window.location.href = "google_revoke_token.php";
    });

  const logoutLk = document.getElementById("logout-link");
  if (logoutLk)
    logoutLk.addEventListener("click", (e) => {
      e.preventDefault();
      window.location.href = logoutLk.href;
    });

  document.querySelectorAll(".input-field").forEach((inp) => {
    if (inp.tagName.toLowerCase() === "select") return;
    const chk = () => inp.classList.toggle("has-val", inp.value.trim() !== "");
    inp.addEventListener("blur", chk);
    inp.addEventListener("input", chk);
    chk();
  });
});
