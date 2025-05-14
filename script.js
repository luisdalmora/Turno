// script.js

/**
 * Função para popular a tabela de turnos na tela.
 */
function popularTabelaTurnos(turnos) {
  const corpoTabelaTurnos = document.querySelector("#shifts-table-may tbody");
  const cabecalhoCheckbox = document.getElementById("select-all-shifts");

  if (!corpoTabelaTurnos) {
    return;
  }
  corpoTabelaTurnos.innerHTML = "";
  if (cabecalhoCheckbox) cabecalhoCheckbox.checked = false;

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
    const inputColaborador = document.createElement("input");
    inputColaborador.type = "text";
    inputColaborador.className = "shift-employee";
    inputColaborador.value = turno.colaborador;
    celulaColaborador.appendChild(inputColaborador);

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
      console.log("Editar turno ID:", turno.id);
      alert(
        "Funcionalidade de editar turno ID: " +
          turno.id +
          " a ser implementada com mais detalhes (ex: modal)."
      );
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
  const payload = {
    acao: "salvar_turnos",
    turnos: dadosTurnos,
  };
  fetch("salvar_turnos.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  })
    .then((response) => {
      if (!response.ok) {
        return response
          .json()
          .then((errData) => {
            throw new Error(errData.message || `Erro HTTP: ${response.status}`);
          })
          .catch(() => {
            throw new Error(
              `Erro HTTP: ${response.status}. Resposta do servidor não é JSON válido.`
            );
          });
      }
      return response.json();
    })
    .then((data) => {
      if (data.success) {
        alert("Sucesso: " + (data.message || "Turnos salvos!"));
        popularTabelaTurnos(data.data);
        atualizarTabelaResumoColaboradores(data.data);
        atualizarGraficoResumoHoras(data.data);
      } else {
        alert(
          "Erro ao salvar: " + (data.message || "Ocorreu um erro desconhecido.")
        );
      }
    })
    .catch((error) => {
      console.error("Erro crítico ao salvar dados dos turnos:", error);
      alert(
        "Erro crítico ao tentar salvar os dados. Verifique o console.\nDetalhe: " +
          error.message
      );
    });
}

function coletarDadosDaTabelaDeTurnos() {
  const linhasTabelaTurnos = document.querySelectorAll(
    "#shifts-table-may tbody tr"
  );
  const dadosTurnosParaSalvar = [];
  const tituloTabelaEl = document.getElementById("current-month-year");
  let anoTabela = new Date().getFullYear().toString();

  if (tituloTabelaEl && tituloTabelaEl.textContent) {
    const matchAno = tituloTabelaEl.textContent.match(/(\d{4})/);
    if (matchAno && matchAno[1]) anoTabela = matchAno[1];
  }

  linhasTabelaTurnos.forEach((linha) => {
    if (linha.cells.length === 1 && linha.cells[0].colSpan > 1) return;

    const dataInput = linha.querySelector(".shift-date");
    const horaDuracaoInput = linha.querySelector(".shift-time");
    const colaboradorInput = linha.querySelector(".shift-employee");
    const turnoIdOriginal = linha.getAttribute("data-turno-id");

    if (
      dataInput &&
      horaDuracaoInput &&
      colaboradorInput &&
      dataInput.value.trim() !== "" &&
      horaDuracaoInput.value.trim() !== "" &&
      colaboradorInput.value.trim() !== ""
    ) {
      dadosTurnosParaSalvar.push({
        id:
          turnoIdOriginal && !turnoIdOriginal.startsWith("new-")
            ? turnoIdOriginal
            : null,
        data: dataInput.value.trim(),
        hora: horaDuracaoInput.value.trim(),
        colaborador: colaboradorInput.value.trim(),
        ano: anoTabela,
      });
    } else if (
      !(
        dataInput.value.trim() === "" &&
        horaDuracaoInput.value.trim() === "" &&
        colaboradorInput.value.trim() === ""
      )
    ) {
      console.warn("Linha de turno com dados incompletos não será salva.", {
        data: dataInput.value,
        hora_duracao: horaDuracaoInput.value,
        colaborador: colaboradorInput.value,
      });
    }
  });
  return dadosTurnosParaSalvar;
}

function atualizarTabelaResumoColaboradores(turnos) {
  const corpoTabelaResumo = document.querySelector(
    "#employee-summary-table tbody"
  );
  if (!corpoTabelaResumo) {
    return;
  }
  corpoTabelaResumo.innerHTML = "";

  if (!turnos || turnos.length === 0) {
    const linhaVazia = corpoTabelaResumo.insertRow();
    const celulaVazia = linhaVazia.insertCell();
    celulaVazia.colSpan = 2;
    celulaVazia.textContent = "Sem dados para resumo.";
    celulaVazia.style.textAlign = "center";
    return;
  }

  const resumoHoras = {};
  turnos.forEach((turno) => {
    if (!turno.colaborador || !turno.hora) return;

    if (!resumoHoras[turno.colaborador]) {
      resumoHoras[turno.colaborador] = 0;
    }

    const horaStr = String(turno.hora);
    const partesHora = horaStr.split(":").map(Number);
    let horasTurno = 0;
    let minutosTurno = 0;

    if (partesHora.length >= 1) horasTurno = partesHora[0];
    if (partesHora.length >= 2) minutosTurno = partesHora[1];

    const duracaoDecimalTurno = horasTurno + minutosTurno / 60.0;
    resumoHoras[turno.colaborador] += duracaoDecimalTurno;
  });

  for (const colaborador in resumoHoras) {
    const totalHorasCalculadas = resumoHoras[colaborador].toFixed(2);
    const novaLinha = corpoTabelaResumo.insertRow();
    novaLinha.insertCell(0).textContent = colaborador;
    novaLinha.insertCell(1).textContent =
      totalHorasCalculadas.replace(".", ",") + "h";
  }
}

let employeeHoursChartInstance = null;

function atualizarGraficoResumoHoras(turnos) {
  const ctx = document.getElementById("employee-hours-chart");
  if (!ctx) {
    return;
  }

  if (!turnos || turnos.length === 0) {
    if (employeeHoursChartInstance) {
      employeeHoursChartInstance.destroy();
      employeeHoursChartInstance = null;
    }
    const context = ctx.getContext("2d");
    context.clearRect(0, 0, ctx.width, ctx.height);
    context.textAlign = "center";
    context.fillText(
      "Sem dados para exibir no gráfico.",
      ctx.width / 2,
      ctx.height / 2
    );
    return;
  }

  const resumoHoras = {};
  turnos.forEach((turno) => {
    if (!turno.colaborador || !turno.hora) return;
    if (!resumoHoras[turno.colaborador]) resumoHoras[turno.colaborador] = 0;

    const horaStr = String(turno.hora);
    const partesHora = horaStr.split(":").map(Number);
    let horasTurno = 0;
    let minutosTurno = 0;
    if (partesHora.length >= 1) horasTurno = partesHora[0];
    if (partesHora.length >= 2) minutosTurno = partesHora[1];

    const duracaoDecimalTurno = horasTurno + minutosTurno / 60.0;
    resumoHoras[turno.colaborador] += duracaoDecimalTurno;
  });

  const labels = Object.keys(resumoHoras);
  const dataPoints = labels.map((label) =>
    parseFloat(resumoHoras[label].toFixed(2))
  );

  if (employeeHoursChartInstance) {
    employeeHoursChartInstance.data.labels = labels;
    employeeHoursChartInstance.data.datasets[0].data = dataPoints;
    employeeHoursChartInstance.update();
  } else {
    employeeHoursChartInstance = new Chart(ctx.getContext("2d"), {
      type: "bar",
      data: {
        labels: labels,
        datasets: [
          {
            label: "Total de Horas Trabalhadas",
            data: dataPoints,
            backgroundColor: [
              "rgba(64, 123, 255, 0.7)",
              "rgba(40, 167, 69, 0.7)",
              "rgba(255, 193, 7, 0.7)",
              "rgba(23, 162, 184, 0.7)",
              "rgba(108, 117, 125, 0.7)",
            ],
            borderColor: [
              "rgba(64, 123, 255, 1)",
              "rgba(40, 167, 69, 1)",
              "rgba(255, 193, 7, 1)",
              "rgba(23, 162, 184, 1)",
              "rgba(108, 117, 125, 1)",
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
              label: function (context) {
                let label = context.dataset.label || "";
                if (label) label += ": ";
                if (context.parsed.y !== null) {
                  label += context.parsed.y.toFixed(2).replace(".", ",") + "h";
                }
                return label;
              },
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
          .then((errData) => {
            throw new Error(errData.message || `Erro HTTP: ${response.status}`);
          })
          .catch(() => {
            throw new Error(
              `Erro HTTP: ${response.status}. Resposta inválida.`
            );
          });
      }
      return response.json();
    })
    .then((data) => {
      if (data.success && data.data) {
        popularTabelaTurnos(data.data);
        atualizarTabelaResumoColaboradores(data.data);
        atualizarGraficoResumoHoras(data.data);
      } else {
        alert(
          "Aviso ao carregar dados: " +
            (data.message || "Não foi possível carregar os dados.")
        );
        popularTabelaTurnos([]);
        atualizarTabelaResumoColaboradores([]);
        atualizarGraficoResumoHoras([]);
      }
    })
    .catch((error) => {
      console.error(
        "Erro crítico ao carregar dados iniciais dos turnos:",
        error
      );
      alert(
        "Erro crítico ao carregar dados. Verifique o console.\nDetalhe: " +
          error.message
      );
      popularTabelaTurnos([]);
      atualizarTabelaResumoColaboradores([]);
      atualizarGraficoResumoHoras([]);
    });
}

function excluirTurnosNoServidor(idsDosTurnosParaExcluir) {
  if (!idsDosTurnosParaExcluir || idsDosTurnosParaExcluir.length === 0) {
    alert("Nenhum turno selecionado para exclusão.");
    return;
  }
  if (
    !confirm(
      `Tem certeza que deseja excluir ${idsDosTurnosParaExcluir.length} turno(s)? Esta ação não pode ser desfeita.`
    )
  ) {
    return;
  }
  const payload = {
    acao: "excluir_turnos",
    ids_turnos: idsDosTurnosParaExcluir,
  };
  fetch("salvar_turnos.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  })
    .then((response) => {
      if (!response.ok) {
        return response
          .json()
          .then((errData) => {
            throw new Error(errData.message || `Erro HTTP: ${response.status}`);
          })
          .catch(() => {
            throw new Error(
              `Erro HTTP: ${response.status}. Resposta inválida.`
            );
          });
      }
      return response.json();
    })
    .then((data) => {
      if (data.success) {
        alert(data.message || "Turnos excluídos com sucesso!");
        carregarTurnosDoServidor();
      } else {
        alert(
          "Erro ao excluir turnos: " +
            (data.message || "Ocorreu um problema no servidor.")
        );
      }
    })
    .catch((error) => {
      console.error("Erro crítico ao excluir turnos:", error);
      alert(
        "Erro crítico ao tentar excluir os turnos. Verifique o console.\nDetalhe: " +
          error.message
      );
    });
}

document.addEventListener("DOMContentLoaded", function () {
  if (document.getElementById("shifts-table-may")) {
    carregarTurnosDoServidor();
  }

  const botaoSalvarTurnos = document.getElementById("save-shifts-button");
  if (botaoSalvarTurnos) {
    botaoSalvarTurnos.addEventListener("click", function () {
      const dadosColetados = coletarDadosDaTabelaDeTurnos();
      if (dadosColetados.length > 0) {
        salvarDadosTurnosNoServidor(dadosColetados);
      } else {
        if (document.querySelector("#shifts-table-may tbody tr td[colspan]")) {
          alert("Não há turnos para salvar. Adicione um novo turno.");
        } else {
          alert(
            "Nenhum turno válido para salvar. Verifique se todos os campos das linhas existentes estão preenchidos."
          );
        }
      }
    });
  }

  const botaoAdicionarTurno = document.getElementById("add-shift-row-button");
  if (botaoAdicionarTurno) {
    botaoAdicionarTurno.addEventListener("click", function () {
      const corpoTabelaTurnos = document.querySelector(
        "#shifts-table-may tbody"
      );
      if (corpoTabelaTurnos) {
        const linhaVaziaExistente =
          corpoTabelaTurnos.querySelector("td[colspan]");
        if (linhaVaziaExistente) corpoTabelaTurnos.innerHTML = "";

        const turnoPlaceholder = {
          id: "new-" + Date.now(),
          data: "",
          hora: "",
          colaborador: "",
          google_calendar_event_id: "Pendente",
        };

        const novaLinha = corpoTabelaTurnos.insertRow();
        novaLinha.setAttribute("data-turno-id", turnoPlaceholder.id);

        const celulaCheckbox = novaLinha.insertCell();
        const inputCheckbox = document.createElement("input");
        inputCheckbox.type = "checkbox";
        inputCheckbox.className = "shift-select-checkbox";
        celulaCheckbox.appendChild(inputCheckbox);

        const celulaData = novaLinha.insertCell();
        const inputData = document.createElement("input");
        inputData.type = "text";
        inputData.className = "shift-date";
        inputData.placeholder = "dd/Mês";
        celulaData.appendChild(inputData);
        inputData.focus();

        const celulaHoraDuracao = novaLinha.insertCell();
        const inputHoraDuracao = document.createElement("input");
        inputHoraDuracao.type = "time";
        inputHoraDuracao.className = "shift-time";
        celulaHoraDuracao.appendChild(inputHoraDuracao);

        const celulaColaborador = novaLinha.insertCell();
        const inputColaborador = document.createElement("input");
        inputColaborador.type = "text";
        inputColaborador.className = "shift-employee";
        inputColaborador.placeholder = "Nome Colaborador";
        celulaColaborador.appendChild(inputColaborador);

        const celulaGoogleEventId = novaLinha.insertCell();
        celulaGoogleEventId.className = "shift-google-event-id";
        celulaGoogleEventId.textContent = "Pendente";

        const celulaAcoes = novaLinha.insertCell();
        celulaAcoes.className = "actions-cell";
        const btnExcluirNovaLinha = document.createElement("button");
        btnExcluirNovaLinha.innerHTML = '<i class="fas fa-trash-alt"></i>';
        btnExcluirNovaLinha.title = "Remover esta linha";
        btnExcluirNovaLinha.className = "btn-table-action delete";
        btnExcluirNovaLinha.onclick = function () {
          novaLinha.remove();
          if (corpoTabelaTurnos.rows.length === 0) {
            popularTabelaTurnos([]);
          }
        };
        celulaAcoes.appendChild(btnExcluirNovaLinha);
      }
    });
  }

  const selectAllCheckbox = document.getElementById("select-all-shifts");
  if (selectAllCheckbox) {
    selectAllCheckbox.addEventListener("change", function () {
      document
        .querySelectorAll(".shift-select-checkbox")
        .forEach((checkbox) => {
          checkbox.checked = selectAllCheckbox.checked;
        });
    });
  }

  const botaoExcluirTurnos = document.getElementById(
    "delete-selected-shifts-button"
  );
  if (botaoExcluirTurnos) {
    botaoExcluirTurnos.addEventListener("click", function () {
      const idsSelecionados = [];
      let algumaLinhaNovaRemovida = false;
      document
        .querySelectorAll(".shift-select-checkbox:checked")
        .forEach((checkbox) => {
          const turnoTr = checkbox.closest("tr");
          const turnoId = turnoTr.getAttribute("data-turno-id");
          if (turnoId && !turnoId.startsWith("new-")) {
            idsSelecionados.push(turnoId);
          } else if (turnoId && turnoId.startsWith("new-")) {
            turnoTr.remove();
            algumaLinhaNovaRemovida = true;
          }
        });

      if (idsSelecionados.length > 0) {
        excluirTurnosNoServidor(idsSelecionados);
      } else if (algumaLinhaNovaRemovida) {
        alert(
          "Linhas novas (não salvas) foram removidas. Nenhum turno existente foi selecionado para exclusão do servidor."
        );
        const corpoTabelaTurnos = document.querySelector(
          "#shifts-table-may tbody"
        );
        if (corpoTabelaTurnos && corpoTabelaTurnos.rows.length === 0) {
          popularTabelaTurnos([]);
        }
      } else {
        alert("Nenhum turno existente selecionado para exclusão.");
      }
    });
  }

  const urlParams = new URLSearchParams(window.location.search);
  const gcalStatus = urlParams.get("gcal_status");
  const gcalMsg = urlParams.get("gcal_msg");
  const statusMessageEl = document.getElementById("gcal-status-message");
  const connectBtn = document.getElementById("connect-gcal-btn");
  const disconnectBtn = document.getElementById("disconnect-gcal-btn");

  function checkGCalConnectionStatus() {
    if (!statusMessageEl || !connectBtn || !disconnectBtn) return;
    let isConnected = false;

    // A verificação de 'isConnected' idealmente viria do backend
    // Aqui, usamos o status da URL para a configuração inicial dos botões
    if (gcalStatus === "success") {
      statusMessageEl.textContent = "Google Calendar conectado com sucesso!";
      statusMessageEl.style.color = "var(--success-color)";
      isConnected = true;
    } else if (gcalStatus === "error") {
      statusMessageEl.textContent =
        "Falha na conexão com Google Calendar: " +
        (gcalMsg || "Tente novamente.");
      statusMessageEl.style.color = "var(--danger-color)";
    } else if (gcalStatus === "disconnected") {
      statusMessageEl.textContent = "Google Calendar desconectado.";
      statusMessageEl.style.color = "var(--warning-color)";
    } else {
      // Se não houver status na URL, pode-se tentar verificar se já existe um token (ex: via localStorage ou chamada backend)
      // Por ora, apenas deixa a mensagem padrão se houver uma, ou limpa.
      // if (statusMessageEl.textContent.trim() === "Verifique o status da conexão ou conecte sua conta.") {
      //     // Não faz nada, mantém a mensagem padrão
      // } else if (!statusMessageEl.textContent.trim()) {
      //    statusMessageEl.textContent = "Conecte sua conta para sincronizar com Google Calendar.";
      // }
    }

    // Lógica para exibir/ocultar botões baseada no status e se o usuário já pode ter conectado antes
    // Esta parte pode precisar de um indicador mais persistente do estado de conexão (ex: vindo do PHP/sessão)
    if (isConnected) {
      connectBtn.style.display = "none";
      disconnectBtn.style.display = "inline-flex";
    } else {
      // Se não for 'success' na URL, assume que não está conectado ou o estado é incerto.
      // Melhorar esta lógica se você tiver um estado de conexão persistente.
      connectBtn.style.display = "inline-flex";
      disconnectBtn.style.display = "none";
    }
  }
  if (statusMessageEl && connectBtn && disconnectBtn)
    checkGCalConnectionStatus();

  if (disconnectBtn) {
    disconnectBtn.addEventListener("click", function () {
      if (
        confirm(
          "Tem certeza que deseja desconectar sua conta do Google Calendar?"
        )
      ) {
        window.location.href = "google_revoke_token.php";
      }
    });
  }

  const logoutLink = document.getElementById("logout-link");
  if (logoutLink) {
    logoutLink.addEventListener("click", function (e) {
      e.preventDefault();
      // Para um logout real, redirecione para um script PHP que destrói a sessão:
      // window.location.href = 'logout.php';
      // O script logout.php faria session_destroy() e então redirecionaria para index.html
      alert("Saindo do sistema..."); // Mensagem de simulação
      window.location.href = "index.html"; // Simula o redirecionamento após o logout
    });
  }

  // Efeito de placeholder flutuante para campos de input
  document.querySelectorAll(".input-field").forEach((input) => {
    if (input.tagName.toLowerCase() === "select") return; // Ignora selects
    const checkVal = () =>
      input.classList.toggle("has-val", input.value.trim() !== "");
    input.addEventListener("blur", checkVal);
    input.addEventListener("input", checkVal); // Adiciona para cobrir preenchimento automático que não dispara blur
    checkVal(); // Verifica no carregamento
  });

  // O nome do usuário agora é inserido diretamente pelo PHP nos arquivos .php
  // O código JS abaixo para user-info não é mais necessário para definir o nome.
  // const userInfoDiv = document.getElementById("user-info");
  // if (userInfoDiv) {
  //   const userName = userInfoDiv.dataset.username || "Usuário";
  //   userInfoDiv.innerHTML = `Olá, ${userName} <i class="fas fa-user-circle"></i>`;
  // }
});
