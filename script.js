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
  corpoTabelaTurnos.innerHTML = ""; // Limpa a tabela
  if (cabecalhoCheckbox) cabecalhoCheckbox.checked = false;

  if (!turnos || turnos.length === 0) {
    const linhaVazia = corpoTabelaTurnos.insertRow();
    const celulaVazia = linhaVazia.insertCell();
    // Colspan agora inclui a coluna de Ações
    celulaVazia.colSpan = 6; // Checkbox + Dia + Hora + Colab + GCalID (oculto) + Ações
    celulaVazia.textContent =
      "Nenhum turno programado para este período ou filtro.";
    celulaVazia.style.textAlign = "center";
    return;
  }

  turnos.forEach((turno) => {
    const novaLinha = corpoTabelaTurnos.insertRow();
    novaLinha.setAttribute("data-turno-id", turno.id);

    // Célula Checkbox
    const celulaCheckbox = novaLinha.insertCell();
    const inputCheckbox = document.createElement("input");
    inputCheckbox.type = "checkbox";
    inputCheckbox.className = "shift-select-checkbox";
    inputCheckbox.value = turno.id;
    celulaCheckbox.appendChild(inputCheckbox);

    // Célula Data
    const celulaData = novaLinha.insertCell();
    const inputData = document.createElement("input");
    inputData.type = "text"; // Poderia ser 'date' para melhor UX, mas requer formatação dd/Mês
    inputData.className = "shift-date";
    inputData.value = turno.data; // Espera-se que 'turno.data' já venha formatado como dd/Mês
    celulaData.appendChild(inputData);

    // Célula Hora
    const celulaHora = novaLinha.insertCell();
    const inputHora = document.createElement("input");
    inputHora.type = "time";
    inputHora.className = "shift-time";
    inputHora.value = turno.hora;
    celulaHora.appendChild(inputHora);

    // Célula Colaborador
    const celulaColaborador = novaLinha.insertCell();
    const inputColaborador = document.createElement("input");
    inputColaborador.type = "text";
    inputColaborador.className = "shift-employee";
    inputColaborador.value = turno.colaborador;
    celulaColaborador.appendChild(inputColaborador);

    // Célula Google Event ID (será oculta via CSS)
    const celulaGoogleEventId = novaLinha.insertCell();
    celulaGoogleEventId.className = "shift-google-event-id";
    celulaGoogleEventId.textContent = turno.google_calendar_event_id || "N/A";

    // Célula de Ações
    const celulaAcoes = novaLinha.insertCell();
    celulaAcoes.className = "actions-cell";

    const btnEditar = document.createElement("button");
    btnEditar.innerHTML = '<i class="fas fa-edit"></i>';
    btnEditar.title = "Editar Turno";
    btnEditar.className = "btn-table-action edit";
    btnEditar.onclick = function () {
      // Lógica de edição para este turno (ex: abrir modal, transformar linha em editável)
      console.log("Editar turno ID:", turno.id);
      alert(
        "Funcionalidade de editar turno ID: " +
          turno.id +
          " a ser implementada."
      );
      // Você precisaria de uma função para habilitar edição inline ou abrir um modal
      // e depois uma forma de salvar a alteração específica.
    };
    celulaAcoes.appendChild(btnEditar);

    const btnExcluirLinha = document.createElement("button");
    btnExcluirLinha.innerHTML = '<i class="fas fa-trash-alt"></i>';
    btnExcluirLinha.title = "Excluir Turno";
    btnExcluirLinha.className = "btn-table-action delete";
    btnExcluirLinha.onclick = function () {
      // Reutiliza a função de exclusão, mas para um único ID
      excluirTurnosNoServidor([turno.id]);
    };
    celulaAcoes.appendChild(btnExcluirLinha);
  });
}

/**
 * Função para salvar os dados dos turnos no servidor.
 * (Lógica de salvar permanece a mesma, mas o backend precisaria de lógica de UPDATE se IDs forem enviados)
 */
function salvarDadosTurnosNoServidor(dadosTurnos) {
  const payload = {
    acao: "salvar_turnos",
    turnos: dadosTurnos,
  };
  // ... (restante da função como antes) ...
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
        alert("Sucesso: " + data.message); // Melhorar feedback visual aqui
        popularTabelaTurnos(data.data);
        atualizarTabelaResumoColaboradores(data.data);
        atualizarGraficoResumoHoras(data.data); // Adicionado para atualizar gráfico
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
 * (A coleta permanece a mesma, mas a função salvar precisaria diferenciar novos de existentes)
 */
function coletarDadosDaTabelaDeTurnos() {
  // ... (função como antes, lembrando que agora temos uma coluna de ações) ...
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
    // Verificar se é a linha "Nenhum turno..."
    if (linha.cells.length === 1 && linha.cells[0].colSpan > 1) return;

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
            ? turnoIdOriginal // Envia ID se for um turno existente
            : null, // null para novos turnos (o backend tratará como INSERT)
        data: dataInput.value.trim(),
        hora: horaInput.value.trim(),
        colaborador: colaboradorInput.value.trim(),
        ano: anoTabela, // O backend já usa o ano da data se fornecido, mas pode ser um fallback
      });
    } else if (
      // Se algum campo estiver preenchido mas não todos (exceto a linha de placeholder)
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
  // ... (função como antes) ...
  const corpoTabelaResumo = document.querySelector(
    "#employee-summary-table tbody"
  );
  if (!corpoTabelaResumo) {
    console.error("Elemento tbody da tabela de resumo não encontrado.");
    return;
  }
  corpoTabelaResumo.innerHTML = ""; // Limpa tabela
  if (!turnos || turnos.length === 0) {
    const linhaVazia = corpoTabelaResumo.insertRow();
    const celulaVazia = linhaVazia.insertCell();
    celulaVazia.colSpan = 2;
    celulaVazia.textContent = "Sem dados para resumo.";
    celulaVazia.style.textAlign = "center";
    return;
  }

  const resumoHoras = {}; // Objeto para armazenar { colaborador: totalMinutos }
  turnos.forEach((turno) => {
    if (!turno.colaborador || !turno.hora) return; // Ignora turnos sem colaborador ou hora

    if (!resumoHoras[turno.colaborador]) {
      resumoHoras[turno.colaborador] = 0;
    }
    // Assumindo que turno.hora é uma string "HH:MM"
    // E que a duração implícita do turno é o que você quer somar.
    // Para este exemplo, vamos assumir que CADA entrada de turno.hora representa a DURAÇÃO do turno.
    // Se turno.hora é apenas o INÍCIO, você precisaria de uma HORA DE FIM ou DURAÇÃO para calcular.
    // O código original somava as horas de início, o que é incomum para um resumo de "total de horas".
    // VAMOS ASSUMIR QUE CADA TURNO TEM UMA DURAÇÃO PADRÃO DE 8 HORAS PARA ESTE EXEMPLO DE RESUMO.
    // VOCÊ PRECISA AJUSTAR ISSO PARA A LÓGICA CORRETA DE DURAÇÃO DO TURNO.
    const duracaoTurnoEmMinutos = 8 * 60; // Exemplo: 8 horas
    // Se você tiver um campo de duração ou hora de fim, use-o aqui.
    // Exemplo, se turno.hora fosse a duração "08:00":
    // const partesHora = turno.hora.split(':').map(Number);
    // let minutosTurno = 0;
    // if (partesHora.length >= 2) minutosTurno = partesHora[0] * 60 + partesHora[1];
    resumoHoras[turno.colaborador] += duracaoTurnoEmMinutos;
  });

  for (const colaborador in resumoHoras) {
    const totalHorasCalculadas = (resumoHoras[colaborador] / 60).toFixed(2); // Converte minutos para horas
    const novaLinha = corpoTabelaResumo.insertRow();
    novaLinha.insertCell(0).textContent = colaborador;
    novaLinha.insertCell(1).textContent = totalHorasCalculadas + "h";
  }
}

/**
 * Função para atualizar o gráfico de resumo de horas.
 */
let employeeHoursChartInstance = null; // Variável global para a instância do gráfico

function atualizarGraficoResumoHoras(turnos) {
  const ctx = document.getElementById("employee-hours-chart");
  if (!ctx) {
    console.warn(
      "Elemento canvas do gráfico de resumo de horas não encontrado."
    );
    return;
  }

  if (!turnos || turnos.length === 0) {
    if (employeeHoursChartInstance) {
      employeeHoursChartInstance.destroy(); // Destroi gráfico anterior se não há dados
      employeeHoursChartInstance = null;
    }
    // Poderia exibir uma mensagem no lugar do gráfico
    return;
  }

  const resumoHoras = {}; // { colaborador: totalMinutos }
  turnos.forEach((turno) => {
    if (!turno.colaborador || !turno.hora) return;
    if (!resumoHoras[turno.colaborador]) resumoHoras[turno.colaborador] = 0;
    // ASSUMINDO DURAÇÃO PADRÃO DE 8 HORAS POR TURNO PARA O GRÁFICO. AJUSTE CONFORME SUA LÓGICA.
    const duracaoTurnoEmMinutos = 8 * 60;
    resumoHoras[turno.colaborador] += duracaoTurnoEmMinutos;
  });

  const labels = Object.keys(resumoHoras);
  const dataPoints = labels.map((label) =>
    (resumoHoras[label] / 60).toFixed(2)
  ); // Horas

  if (employeeHoursChartInstance) {
    employeeHoursChartInstance.data.labels = labels;
    employeeHoursChartInstance.data.datasets[0].data = dataPoints;
    employeeHoursChartInstance.update();
  } else {
    employeeHoursChartInstance = new Chart(ctx.getContext("2d"), {
      type: "bar", // ou 'pie', 'doughnut'
      data: {
        labels: labels,
        datasets: [
          {
            label: "Total de Horas Trabalhadas",
            data: dataPoints,
            backgroundColor: [
              // Cores para as barras/fatias
              "rgba(64, 123, 255, 0.7)", // --primary-color com alpha
              "rgba(40, 167, 69, 0.7)", // --success-color com alpha
              "rgba(255, 193, 7, 0.7)", // --warning-color com alpha
              "rgba(23, 162, 184, 0.7)", // --info-color com alpha
              "rgba(108, 117, 125, 0.7)", // --secondary-color com alpha
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
          y: {
            beginAtZero: true,
            title: { display: true, text: "Horas" },
          },
          x: {
            title: { display: true, text: "Colaborador" },
          },
        },
        plugins: {
          legend: {
            display: true, // Pode ser false se só tiver um dataset
            position: "top",
          },
          title: {
            display: false, // O título já está no widget
            text: "Resumo de Horas por Colaborador",
          },
        },
      },
    });
  }
}

/**
 * Função para carregar os dados iniciais dos turnos do servidor.
 */
function carregarTurnosDoServidor() {
  // ... (função como antes, mas agora chama para atualizar gráfico também) ...
  fetch("salvar_turnos.php") // Assumindo que GET em salvar_turnos.php retorna os turnos
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
        atualizarGraficoResumoHoras(data.data); // Adicionado
      } else {
        alert(
          "Aviso ao carregar dados: " +
            (data.message || "Não foi possível carregar os dados.")
        );
        popularTabelaTurnos([]);
        atualizarTabelaResumoColaboradores([]);
        atualizarGraficoResumoHoras([]); // Adicionado
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
      atualizarGraficoResumoHoras([]); // Adicionado
    });
}

/**
 * Função para excluir turnos selecionados no servidor.
 */
function excluirTurnosNoServidor(idsDosTurnosParaExcluir) {
  // ... (função como antes) ...
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
        carregarTurnosDoServidor(); // Recarrega todos os dados, incluindo tabelas e gráfico
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
  // Carrega os dados iniciais se a tabela de turnos existir
  if (document.getElementById("shifts-table-may")) {
    carregarTurnosDoServidor();
  }

  // Botão Salvar Alterações nos Turnos
  const botaoSalvarTurnos = document.getElementById("save-shifts-button");
  if (botaoSalvarTurnos) {
    botaoSalvarTurnos.addEventListener("click", function () {
      const dadosColetados = coletarDadosDaTabelaDeTurnos();
      if (dadosColetados.length > 0) {
        salvarDadosTurnosNoServidor(dadosColetados);
      } else {
        // Verifica se a mensagem "Nenhum turno programado" está presente
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

  // Botão Adicionar Novo Turno
  const botaoAdicionarTurno = document.getElementById("add-shift-row-button");
  if (botaoAdicionarTurno) {
    botaoAdicionarTurno.addEventListener("click", function () {
      const corpoTabelaTurnos = document.querySelector(
        "#shifts-table-may tbody"
      );
      if (corpoTabelaTurnos) {
        // Remove a mensagem "Nenhum turno programado..." se existir
        const linhaVaziaExistente =
          corpoTabelaTurnos.querySelector("td[colspan]");
        if (linhaVaziaExistente) corpoTabelaTurnos.innerHTML = "";

        // Cria uma nova linha com ID temporário para "novo" turno
        const turnoPlaceholder = {
          id: "new-" + Date.now(), // ID temporário para novos turnos
          data: "",
          hora: "",
          colaborador: "",
          google_calendar_event_id: "Pendente",
        };
        // Usa a função popularTabelaTurnos para adicionar a linha, mas com um array de um item
        // Isso mantém a criação de linha consistente. Ou crie a linha manualmente aqui.
        // Para simplificar, vamos criar manualmente, já que popularTabelaTurnos espera um array completo.

        const novaLinha = corpoTabelaTurnos.insertRow(); // Insere no final por padrão
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
        inputData.placeholder = "dd/Mês"; // Ajuste o placeholder
        celulaData.appendChild(inputData);
        inputData.focus(); // Foco no campo de data

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

        const celulaGoogleEventId = novaLinha.insertCell(); // Célula para GCal ID (oculta)
        celulaGoogleEventId.className = "shift-google-event-id";
        celulaGoogleEventId.textContent = "Pendente";

        const celulaAcoes = novaLinha.insertCell(); // Célula para botões de ação
        celulaAcoes.className = "actions-cell";
        // Botão de excluir para a nova linha (não funcional até salvar)
        const btnExcluirNovaLinha = document.createElement("button");
        btnExcluirNovaLinha.innerHTML = '<i class="fas fa-trash-alt"></i>';
        btnExcluirNovaLinha.title = "Remover esta linha (não salva)";
        btnExcluirNovaLinha.className = "btn-table-action delete";
        btnExcluirNovaLinha.onclick = function () {
          novaLinha.remove(); // Simplesmente remove a linha da UI
          // Se for a última linha, mostrar mensagem de "nenhum turno" novamente
          if (corpoTabelaTurnos.rows.length === 0) {
            popularTabelaTurnos([]); // Chama para mostrar a mensagem
          }
        };
        celulaAcoes.appendChild(btnExcluirNovaLinha);
      }
    });
  }

  // Checkbox "Selecionar Todos"
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

  // Botão Excluir Selecionados
  const botaoExcluirTurnos = document.getElementById(
    "delete-selected-shifts-button"
  );
  if (botaoExcluirTurnos) {
    botaoExcluirTurnos.addEventListener("click", function () {
      const idsSelecionados = [];
      document
        .querySelectorAll(".shift-select-checkbox:checked")
        .forEach((checkbox) => {
          // Apenas adiciona IDs de turnos que não são "novos" (já existem no backend)
          const turnoId = checkbox.closest("tr").getAttribute("data-turno-id");
          if (turnoId && !turnoId.startsWith("new-")) {
            idsSelecionados.push(turnoId);
          } else if (turnoId && turnoId.startsWith("new-")) {
            // Se um turno novo (não salvo) estiver selecionado, apenas o remove da UI
            checkbox.closest("tr").remove();
          }
        });
      if (idsSelecionados.length > 0) {
        excluirTurnosNoServidor(idsSelecionados);
      } else {
        // Verifica se alguma linha "nova" foi removida e se a tabela ficou vazia
        const corpoTabelaTurnos = document.querySelector(
          "#shifts-table-may tbody"
        );
        if (corpoTabelaTurnos && corpoTabelaTurnos.rows.length === 0) {
          popularTabelaTurnos([]);
        } else if (
          document.querySelectorAll(".shift-select-checkbox:checked").length > 0
        ) {
          alert(
            "As linhas novas selecionadas foram removidas. Nenhum turno existente foi selecionado para exclusão do servidor."
          );
        } else {
          alert("Nenhum turno existente selecionado para exclusão.");
        }
      }
    });
  }

  // Lógica Google Calendar (mantida como antes)
  const urlParams = new URLSearchParams(window.location.search);
  const gcalStatus = urlParams.get("gcal_status");
  const gcalMsg = urlParams.get("gcal_msg");
  const statusMessageEl = document.getElementById("gcal-status-message");
  const connectBtn = document.getElementById("connect-gcal-btn");
  const disconnectBtn = document.getElementById("disconnect-gcal-btn");

  function checkGCalConnectionStatus() {
    // ... (função como antes, apenas ajuste se necessário o seletor dos botões) ...
    if (!statusMessageEl || !connectBtn || !disconnectBtn) return;

    // Idealmente, o status de conexão deveria vir do backend.
    // Esta é uma simulação baseada em query params ou uma variável global que o PHP poderia definir.
    let isConnected = false; // Deveria ser determinado por uma chamada ao backend ou variável PHP

    if (gcalStatus === "success") {
      statusMessageEl.textContent = "Google Calendar conectado com sucesso!";
      statusMessageEl.style.color = "var(--success-color)"; // Usando variável CSS
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
      // Aqui você faria uma verificação no backend para saber o status real
      // fetch('/api/check_gcal_status.php').then(res => res.json()).then(data => { ... });
      statusMessageEl.textContent =
        "Verifique o status da conexão com o Google Calendar.";
    }

    if (isConnected) {
      connectBtn.style.display = "none";
      disconnectBtn.style.display = "inline-flex"; // display: flex para botões com ícones
    } else {
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

  // Lógica de Logout (mantida)
  const logoutLink = document.getElementById("logout-link");
  if (logoutLink) {
    logoutLink.addEventListener("click", function (e) {
      e.preventDefault();
      // Idealmente, chamar um script PHP para destruir a sessão no backend
      // Ex: window.location.href = 'logout.php';
      alert("Sessão encerrada (simulação). Redirecionando para o login.");
      window.location.href = "index.html";
    });
  }

  // Efeito placeholder flutuante (mantido para inputs de login/cadastro)
  document.querySelectorAll(".input-field").forEach((input) => {
    if (input.tagName.toLowerCase() === "select") return; // Ignora selects
    input.addEventListener("blur", () => {
      input.classList.toggle("has-val", input.value.trim() !== "");
    });
    // Verifica no carregamento se já tem valor (autocomplete)
    if (input.value.trim() !== "") input.classList.add("has-val");
  });

  // Adicionar nome do usuário no header (exemplo, precisaria que o PHP passasse esse dado)
  const userInfoDiv = document.getElementById("user-info");
  if (userInfoDiv) {
    // Supondo que o PHP injetou o nome do usuário em um data attribute ou você faz um fetch
    // Exemplo: <div id="user-info" data-username="<?php echo htmlspecialchars($_SESSION['usuario_nome_completo']); ?>">
    // const userName = userInfoDiv.dataset.username || "Usuário";
    const userName = "Nome do Usuário"; // Placeholder
    userInfoDiv.innerHTML = `Olá, ${userName} <i class="fas fa-user-circle"></i>`;
  }
});
