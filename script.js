// script.js

/**
 * Função para popular a tabela de turnos na tela.
 */
function popularTabelaTurnos(turnos) {
  const corpoTabelaTurnos = document.querySelector("#shifts-table-may tbody");
  const cabecalhoCheckbox = document.getElementById("select-all-shifts");

  if (!corpoTabelaTurnos) {
    console.error(
      "Elemento tbody da tabela de turnos (#shifts-table-may) não encontrado."
    );
    return;
  }
  corpoTabelaTurnos.innerHTML = "";
  if (cabecalhoCheckbox) cabecalhoCheckbox.checked = false;

  if (!turnos || turnos.length === 0) {
    const linhaVazia = corpoTabelaTurnos.insertRow();
    const celulaVazia = linhaVazia.insertCell();
    celulaVazia.colSpan = 5; // Ajustado: 1 checkbox + 3 visíveis + 1 oculta = 5
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
    celulaData.appendChild(inputData);

    const celulaHora = novaLinha.insertCell();
    const inputHora = document.createElement("input");
    inputHora.type = "time";
    inputHora.className = "shift-time";
    inputHora.value = turno.hora;
    celulaHora.appendChild(inputHora);

    const celulaColaborador = novaLinha.insertCell();
    const inputColaborador = document.createElement("input");
    inputColaborador.type = "text";
    inputColaborador.className = "shift-employee";
    inputColaborador.value = turno.colaborador;
    celulaColaborador.appendChild(inputColaborador);

    const celulaGoogleEventId = novaLinha.insertCell();
    celulaGoogleEventId.className = "shift-google-event-id"; // Esta célula será escondida via CSS
    celulaGoogleEventId.textContent = turno.google_event_id || "Pendente";
  });
}

/**
 * Função para salvar os dados dos turnos no servidor.
 */
function salvarDadosTurnosNoServidor(dadosTurnos) {
  const payload = {
    acao: "salvar_turnos", // Adicionando ação para o backend diferenciar
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
        alert("Sucesso: " + data.message);
        popularTabelaTurnos(data.data);
        atualizarTabelaResumoColaboradores(data.data);
      } else {
        alert("Erro ao salvar: " + data.message);
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

/**
 * Função para coletar os dados dos turnos da tabela HTML.
 */
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
    if (linha.cells.length === 1 && linha.cells[0].colSpan === 5) return;

    const dataInput = linha.querySelector(".shift-date");
    const horaInput = linha.querySelector(".shift-time");
    const colaboradorInput = linha.querySelector(".shift-employee");
    const turnoIdOriginal = linha.getAttribute("data-turno-id");

    if (
      dataInput &&
      horaInput &&
      colaboradorInput &&
      dataInput.value.trim() !== "" &&
      horaInput.value.trim() !== "" &&
      colaboradorInput.value.trim() !== ""
    ) {
      dadosTurnosParaSalvar.push({
        id:
          turnoIdOriginal && !turnoIdOriginal.startsWith("new-")
            ? turnoIdOriginal
            : null, // Envia ID se for um turno existente
        data: dataInput.value.trim(),
        hora: horaInput.value.trim(),
        colaborador: colaboradorInput.value.trim(),
        ano: anoTabela,
      });
    } else if (
      !(
        dataInput.value.trim() === "" &&
        horaInput.value.trim() === "" &&
        colaboradorInput.value.trim() === ""
      )
    ) {
      console.warn("Linha de turno com dados incompletos não será salva.", {
        data: dataInput.value,
        hora: horaInput.value,
        colaborador: colaboradorInput.value,
      });
    }
  });
  return dadosTurnosParaSalvar;
}

/**
 * Função para atualizar a tabela de resumo de horas por colaborador.
 */
function atualizarTabelaResumoColaboradores(turnos) {
  const corpoTabelaResumo = document.querySelector(
    "#employee-summary-table tbody"
  );
  if (!corpoTabelaResumo) {
    console.error("Elemento tbody da tabela de resumo não encontrado.");
    return;
  }
  corpoTabelaResumo.innerHTML = "";
  if (!turnos || turnos.length === 0) return;

  const resumoHoras = {};
  turnos.forEach((turno) => {
    if (!turno.colaborador || !turno.hora) return;
    if (!resumoHoras[turno.colaborador]) resumoHoras[turno.colaborador] = 0;
    const partesHora = turno.hora.split(":").map(Number);
    let minutosTurno = 0;
    if (partesHora.length >= 2)
      minutosTurno = partesHora[0] * 60 + partesHora[1];
    resumoHoras[turno.colaborador] += minutosTurno;
  });

  for (const colaborador in resumoHoras) {
    const totalHorasCalculadas = (resumoHoras[colaborador] / 60).toFixed(2);
    const novaLinha = corpoTabelaResumo.insertRow();
    novaLinha.insertCell(0).textContent = colaborador;
    novaLinha.insertCell(1).textContent = totalHorasCalculadas + "h";
  }
}

/**
 * Função para carregar os dados iniciais dos turnos do servidor.
 */
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
              `Erro HTTP: ${response.status}. Resposta do servidor não é JSON válido ao carregar turnos.`
            );
          });
      }
      return response.json();
    })
    .then((data) => {
      if (data.success && data.data) {
        popularTabelaTurnos(data.data);
        atualizarTabelaResumoColaboradores(data.data);
      } else {
        alert(
          "Aviso ao carregar dados: " +
            (data.message || "Não foi possível carregar os dados.")
        );
        popularTabelaTurnos([]);
        atualizarTabelaResumoColaboradores([]);
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
    });
}

/**
 * Função para excluir turnos selecionados no servidor.
 */
function excluirTurnosNoServidor(idsDosTurnosParaExcluir) {
  if (!idsDosTurnosParaExcluir || idsDosTurnosParaExcluir.length === 0) {
    alert("Nenhum turno selecionado para exclusão.");
    return;
  }
  if (
    !confirm(
      `Tem certeza que deseja excluir ${idsDosTurnosParaExcluir.length} turno(s) selecionado(s)? Esta ação não pode ser desfeita.`
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
            throw new Error(
              errData.message ||
                `Erro HTTP: ${response.status} ao excluir turnos.`
            );
          })
          .catch(() => {
            throw new Error(
              `Erro HTTP: ${response.status}. Resposta do servidor não é JSON válido ao excluir turnos.`
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

// --- EVENT LISTENERS E CÓDIGO EXECUTADO NO DOMContentLoaded ---
document.addEventListener("DOMContentLoaded", function () {
  if (document.getElementById("shifts-table-may")) {
    carregarTurnosDoServidor();
  }

  const botaoSalvarTurnos = document.getElementById("save-shifts-button");
  if (botaoSalvarTurnos) {
    botaoSalvarTurnos.addEventListener("click", function () {
      const dadosColetados = coletarDadosDaTabelaDeTurnos();
      // Decide se envia array vazio para limpar ou se só envia se tiver dados
      // Atualmente, se dadosColetados for vazio, não faz nada, o que pode ser o desejado.
      // Se quiser que o backend delete os turnos do período se a tabela estiver vazia,
      // você precisaria enviar um array vazio e o backend teria essa lógica.
      if (dadosColetados.length > 0) {
        salvarDadosTurnosNoServidor(dadosColetados);
      } else {
        if (
          document.querySelector("#shifts-table-may tbody tr td[colspan='5']")
        ) {
          alert(
            "Não há turnos para salvar. Adicione um novo turno ou preencha os campos."
          );
        } else {
          alert(
            "Nenhum turno válido para salvar. Verifique se todos os campos estão preenchidos."
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
          corpoTabelaTurnos.querySelector("td[colspan='5']");
        if (linhaVaziaExistente) corpoTabelaTurnos.innerHTML = "";

        const novaLinha = corpoTabelaTurnos.insertRow();
        novaLinha.setAttribute("data-turno-id", "new-" + Date.now());

        const celulaCheckbox = novaLinha.insertCell();
        const inputCheckbox = document.createElement("input");
        inputCheckbox.type = "checkbox";
        inputCheckbox.className = "shift-select-checkbox";
        celulaCheckbox.appendChild(inputCheckbox);

        const celulaData = novaLinha.insertCell();
        const inputData = document.createElement("input");
        inputData.type = "text";
        inputData.className = "shift-date";
        inputData.placeholder = "dd/Mês ou dd/mm/aaaa";
        celulaData.appendChild(inputData);
        inputData.focus();

        const celulaHora = novaLinha.insertCell();
        const inputHora = document.createElement("input");
        inputHora.type = "time";
        inputHora.className = "shift-time";
        celulaHora.appendChild(inputHora);

        const celulaColaborador = novaLinha.insertCell();
        const inputColaborador = document.createElement("input");
        inputColaborador.type = "text";
        inputColaborador.className = "shift-employee";
        inputColaborador.placeholder = "Nome do Colaborador";
        celulaColaborador.appendChild(inputColaborador);

        const celulaGoogleEventId = novaLinha.insertCell();
        celulaGoogleEventId.className = "shift-google-event-id";
        celulaGoogleEventId.textContent = "Pendente";
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
      document
        .querySelectorAll(".shift-select-checkbox:checked")
        .forEach((checkbox) => {
          const turnoId = checkbox.closest("tr").getAttribute("data-turno-id");
          if (turnoId && !turnoId.startsWith("new-")) {
            idsSelecionados.push(turnoId);
          }
        });
      excluirTurnosNoServidor(idsSelecionados);
    });
  }

  // Lógica Google Calendar e outros...
  const urlParams = new URLSearchParams(window.location.search);
  const gcalStatus = urlParams.get("gcal_status");
  const gcalMsg = urlParams.get("gcal_msg");
  const statusMessageEl = document.getElementById("gcal-status-message");
  const connectBtn = document.getElementById("connect-gcal-btn");
  const disconnectBtn = document.getElementById("disconnect-gcal-btn");

  function checkGCalConnectionStatus() {
    if (!statusMessageEl || !connectBtn || !disconnectBtn) return;
    let isConnected = false;
    if (gcalStatus === "success") {
      statusMessageEl.textContent = "Google Calendar conectado com sucesso!";
      statusMessageEl.style.color = "green";
      isConnected = true;
    } else if (gcalStatus === "error") {
      statusMessageEl.textContent =
        "Falha na conexão com Google Calendar: " +
        (gcalMsg || "Tente novamente.");
      statusMessageEl.style.color = "red";
    } else if (gcalStatus === "disconnected") {
      statusMessageEl.textContent = "Google Calendar desconectado.";
      statusMessageEl.style.color = "orange";
    }
    // TODO: Checar status real no backend aqui para definir `isConnected` corretamente no load inicial
    if (isConnected) {
      // Se gcalStatus for 'success'
      connectBtn.style.display = "none";
      disconnectBtn.style.display = "inline-block";
    } else {
      // Se for 'error', 'disconnected' ou sem status
      connectBtn.style.display = "inline-block";
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
      alert("Sessão encerrada (simulação).");
      window.location.href = "index.html";
    });
  }

  document.querySelectorAll(".input-field").forEach((input) => {
    if (input.tagName.toLowerCase() === "select") return;
    input.addEventListener("blur", () => {
      input.classList.toggle("has-val", input.value.trim() !== "");
    });
    if (input.value.trim() !== "") input.classList.add("has-val");
  });
});
