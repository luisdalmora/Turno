// src/js/modules/ausenciasManager.js
import {
  showToast,
  popularSelectColaborador,
  tailwindInputClasses,
  tailwindSelectClasses,
  tailwindCheckboxClasses,
  buscarEArmazenarColaboradores,
  todosOsColaboradores as colaboradoresGlobais,
} from "./utils.js";
import * as state from "./state.js"; // Para state.currentDisplayYearAusencias, etc.
import { updateCurrentMonthYearDisplayAusencias } from "./uiUpdater.js";

console.log("[DEBUG] ausenciasManager.js: Módulo carregado.");

async function popularTabelaAusencias(ausencias) {
  console.log(
    "[DEBUG] popularTabelaAusencias (ausenciasManager.js) chamada com dados:",
    ausencias
  );
  const corpoTabela = document.querySelector("#ausencias-table-main tbody");
  if (!corpoTabela) {
    console.error(
      "[DEBUG] Tabela de ausências (tbody) não encontrada (ausenciasManager.js)."
    );
    return;
  }
  corpoTabela.innerHTML = "";
  const chkAll = document.getElementById("select-all-ausencias");
  if (chkAll) chkAll.checked = false;

  // Garante que colaboradores foram buscados se ainda não estiverem
  if (
    colaboradoresGlobais.length === 0 ||
    !colaboradoresGlobais[0] ||
    !colaboradoresGlobais[0].hasOwnProperty("id")
  ) {
    await buscarEArmazenarColaboradores();
  }

  if (!ausencias || ausencias.length === 0) {
    const r = corpoTabela.insertRow();
    r.className = "bg-white";
    const c = r.insertCell();
    c.colSpan = 5; // Ajuste a colSpan conforme sua tabela
    c.className = "p-2 text-center text-gray-500 text-sm";
    c.textContent = "Nenhuma ausência registrada para este período.";
    console.log(
      "[DEBUG] Nenhuma ausência para popular na tabela (ausenciasManager.js)."
    );
    return;
  }
  ausencias.forEach((item) => {
    const nLinha = corpoTabela.insertRow();
    nLinha.className = "bg-white hover:bg-gray-50";
    nLinha.setAttribute("data-ausencia-id", item.id);

    const cellCheckbox = nLinha.insertCell();
    cellCheckbox.className = "p-2 text-center";
    const inputCheckbox = document.createElement("input");
    inputCheckbox.type = "checkbox";
    inputCheckbox.className = `ausencia-select-checkbox ${tailwindCheckboxClasses}`;
    inputCheckbox.value = item.id;
    cellCheckbox.appendChild(inputCheckbox);

    const cellDataInicio = nLinha.insertCell();
    cellDataInicio.className = "p-1";
    const inputDataInicio = document.createElement("input");
    inputDataInicio.type = "date";
    inputDataInicio.className = `ausencia-data-inicio ${tailwindInputClasses}`;
    inputDataInicio.value = item.data_inicio || "";
    cellDataInicio.appendChild(inputDataInicio);

    const cellDataFim = nLinha.insertCell();
    cellDataFim.className = "p-1";
    const inputDataFim = document.createElement("input");
    inputDataFim.type = "date";
    inputDataFim.className = `ausencia-data-fim ${tailwindInputClasses}`;
    inputDataFim.value = item.data_fim || "";
    cellDataFim.appendChild(inputDataFim);

    const cellColaborador = nLinha.insertCell();
    cellColaborador.className = "p-1";
    const selectColaborador = document.createElement("select");
    selectColaborador.className = `ausencia-colaborador ${tailwindSelectClasses}`;
    popularSelectColaborador(
      selectColaborador,
      item.colaborador_nome,
      colaboradoresGlobais
    );
    cellColaborador.appendChild(selectColaborador);

    const cellObs = nLinha.insertCell();
    cellObs.className = "p-1";
    const inputObs = document.createElement("input");
    inputObs.type = "text";
    inputObs.className = `ausencia-observacoes ${tailwindInputClasses}`;
    inputObs.value = item.observacoes || "";
    inputObs.placeholder = "Motivo/Observações da ausência";
    cellObs.appendChild(inputObs);
  });
  console.log(
    `[DEBUG] ${ausencias.length} ausência(s) populada(s) na tabela (ausenciasManager.js).`
  );
}

function coletarDadosDaTabelaDeAusencias() {
  const linhas = document.querySelectorAll("#ausencias-table-main tbody tr");
  const dados = [];
  let erroValidacaoGeral = false;
  linhas.forEach((linha, index) => {
    if (linha.cells.length === 1 && linha.cells[0].colSpan > 1) return;
    const idOrig = linha.getAttribute("data-ausencia-id");
    const dataInicioIn = linha.querySelector(".ausencia-data-inicio");
    const dataFimIn = linha.querySelector(".ausencia-data-fim");
    const colaboradorSelect = linha.querySelector(".ausencia-colaborador");
    const observacoesIn = linha.querySelector(".ausencia-observacoes");

    const inicioVal = dataInicioIn ? dataInicioIn.value.trim() : "";
    const fimVal = dataFimIn ? dataFimIn.value.trim() : "";
    const colaboradorVal = colaboradorSelect ? colaboradorSelect.value : "";
    const obsVal = observacoesIn ? observacoesIn.value.trim() : "";

    if ((colaboradorVal || obsVal) && (!inicioVal || !fimVal)) {
      showToast(
        `Atenção (linha ${
          index + 1
        }): Datas de Início e Fim são obrigatórias se um Colaborador ou Observação for fornecido. Não será salvo.`,
        "warning",
        7000
      );
      erroValidacaoGeral = true;
      return;
    }

    if (inicioVal && fimVal) {
      if (new Date(fimVal) < new Date(inicioVal)) {
        showToast(
          `Atenção (linha ${index + 1}): Data Fim (${new Date(
            fimVal
          ).toLocaleDateString()}) não pode ser anterior à Data Início (${new Date(
            inicioVal
          ).toLocaleDateString()}) para '${
            colaboradorVal || obsVal || "ausência"
          }'. Não será salvo.`,
          "warning",
          7000
        );
        erroValidacaoGeral = true;
        return;
      }
      dados.push({
        id: idOrig && !idOrig.startsWith("new-") ? idOrig : null,
        data_inicio: inicioVal,
        data_fim: fimVal,
        colaborador_nome: colaboradorVal,
        observacoes: obsVal,
      });
    } else if (colaboradorVal || obsVal) {
      // Se colab ou obs preenchidos, mas datas não
      if (!erroValidacaoGeral) {
        // Mostra apenas uma vez o erro de data
        showToast(
          `Linha de ausência ${index + 1} incompleta (faltam datas) para '${
            colaboradorVal || obsVal
          }'. Não será salva.`,
          "warning",
          5000
        );
      }
      erroValidacaoGeral = true;
    }
  });
  if (erroValidacaoGeral && dados.length === 0) return [];
  if (erroValidacaoGeral && dados.length > 0) return null; // Indica erro, mas alguns dados podem ser válidos
  return dados;
}

async function salvarDadosAusenciasNoServidor(dadosAusencias, csrfToken) {
  const btnSalvar = document.getElementById("save-ausencias-button");
  const originalButtonHtml = btnSalvar ? btnSalvar.innerHTML : "";
  if (btnSalvar) {
    btnSalvar.disabled = true;
    btnSalvar.innerHTML = `<i data-lucide="loader-circle" class="lucide-spin w-4 h-4 mr-1.5"></i> Salvando...`;
    if (typeof lucide !== "undefined") lucide.createIcons();
  }
  const payload = {
    acao: "salvar_ausencias",
    ausencias: dadosAusencias,
    csrf_token: csrfToken,
  };
  try {
    const response = await fetch("gerenciar_ausencias.php", {
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
      showToast(data.message || "Ausências salvas com sucesso!", "success");
      if (data.csrf_token) {
        const csrfInput = document.getElementById("csrf-token-ausencias");
        if (csrfInput) csrfInput.value = data.csrf_token;
      }
      carregarAusenciasDoServidor(
        state.currentDisplayYearAusencias,
        state.currentDisplayMonthAusencias
      );
    } else {
      showToast(
        "Erro ao salvar ausências: " + (data.message || "Erro desconhecido."),
        "error"
      );
    }
  } catch (error) {
    console.error(
      "[DEBUG] Erro crítico ao salvar ausências (ausenciasManager.js):",
      error
    );
    showToast(`Erro crítico ao salvar ausências: ${error.message}`, "error");
  } finally {
    if (btnSalvar) {
      btnSalvar.disabled = false;
      btnSalvar.innerHTML = originalButtonHtml;
      if (typeof lucide !== "undefined") lucide.createIcons();
    }
  }
}

async function excluirAusenciasNoServidor(ids, csrfToken) {
  if (!ids || ids.length === 0) {
    showToast("Nenhuma ausência selecionada para exclusão.", "info");
    return;
  }
  if (!confirm(`Tem certeza que deseja excluir ${ids.length} ausência(ões)?`))
    return;
  try {
    const response = await fetch("gerenciar_ausencias.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        acao: "excluir_ausencias",
        ids_ausencias: ids,
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
      showToast(data.message || "Ausência(s) excluída(s)!", "success");
      if (data.csrf_token) {
        const csrfInput = document.getElementById("csrf-token-ausencias");
        if (csrfInput) csrfInput.value = data.csrf_token;
      }
      carregarAusenciasDoServidor(
        state.currentDisplayYearAusencias,
        state.currentDisplayMonthAusencias
      );
    } else {
      showToast(
        "Erro ao excluir ausências: " + (data.message || "Erro do servidor."),
        "error"
      );
    }
  } catch (error) {
    console.error(
      "[DEBUG] Erro crítico ao excluir ausências (ausenciasManager.js):",
      error
    );
    showToast(`Erro crítico ao excluir ausências: ${error.message}.`, "error");
  }
}

export async function carregarAusenciasDoServidor(ano, mes) {
  console.log(
    `[DEBUG] carregarAusenciasDoServidor (ausenciasManager.js) chamado com ano: ${ano}, mes: ${mes}`
  );
  const tableBody = document.querySelector("#ausencias-table-main tbody");
  const csrfTokenInput = document.getElementById("csrf-token-ausencias");

  if (!tableBody) {
    console.error(
      "[DEBUG] Tabela de ausências (#ausencias-table-main tbody) NÃO ENCONTRADA (ausenciasManager.js)."
    );
    showToast(
      "Erro crítico: Estrutura da página de ausências incompleta (tbody).",
      "error"
    );
    return;
  }
  if (!csrfTokenInput && document.getElementById("save-ausencias-button")) {
    console.warn(
      "[DEBUG] Campo CSRF para ausências (csrf-token-ausencias) NÃO ENCONTRADO (ausenciasManager.js), mas botões de ação podem existir."
    );
  }

  tableBody.innerHTML = `<tr><td colspan="5" class="p-2 text-center text-gray-500 text-sm">Carregando ausências (${mes}/${ano})... <i data-lucide="loader-circle" class="lucide-spin inline-block w-4 h-4"></i></td></tr>`;
  if (typeof lucide !== "undefined") lucide.createIcons();

  const url = `gerenciar_ausencias.php?ano=${ano}&mes=${mes}`;
  console.log(
    `[DEBUG] Preparando para fazer fetch para ${url} (ausenciasManager.js)`
  );
  try {
    const response = await fetch(url);
    console.log(
      `[DEBUG] Fetch para ${url} concluído. Status (ausenciasManager.js):`,
      response.status
    );

    let data;
    if (!response.ok) {
      let errorMsg = `Erro ao buscar ausências: HTTP ${response.status}`;
      try {
        const errData = await response.json();
        errorMsg = errData.message || errorMsg;
        console.error(
          "[DEBUG] Erro JSON da resposta não OK de gerenciar_ausencias.php:",
          errData
        );
      } catch (e) {
        const errText = await response
          .text()
          .catch(() => `Resposta não textual ou vazia.`);
        errorMsg = errText.substring(0, 150) || errorMsg;
        console.error(
          "[DEBUG] Erro TEXTO da resposta não OK de gerenciar_ausencias.php:",
          errText.substring(0, 300)
        );
      }
      throw new Error(errorMsg);
    }

    data = await response.json();
    console.log(
      "[DEBUG] Resposta JSON de gerenciar_ausencias.php (ausenciasManager.js):",
      JSON.stringify(data, null, 2).substring(0, 500) + "..."
    );

    if (data.success) {
      if (csrfTokenInput && data.csrf_token) {
        csrfTokenInput.value = data.csrf_token;
        console.log(
          "[DEBUG] CSRF token para ausências atualizado (ausenciasManager.js)."
        );
      }
      if (colaboradoresGlobais.length === 0) {
        // Se não foi carregado ainda
        await buscarEArmazenarColaboradores();
      }
      popularTabelaAusencias(data.data || []);
    } else {
      showToast(
        "Aviso ao carregar ausências: " +
          (data.message || "Não foi possível carregar os dados."),
        "warning"
      );
      popularTabelaAusencias([]);
    }
  } catch (error) {
    console.error(
      "[DEBUG] Erro CATCH no fetch de carregarAusenciasDoServidor (ausenciasManager.js):",
      error
    );
    showToast(
      `Erro crítico ao carregar ausências: ${error.message}. Consulte o console.`,
      "error"
    );
    popularTabelaAusencias([]);
  }
}

export function initAusenciasEventListeners() {
  const btnAddAusencia = document.getElementById("add-ausencia-row-button");
  if (btnAddAusencia) {
    btnAddAusencia.addEventListener("click", async function () {
      console.log(
        "[DEBUG] Botão 'Adicionar Ausência' clicado (ausenciasManager.js)."
      );
      const tbody = document.querySelector("#ausencias-table-main tbody");
      if (!tbody) return;
      const placeholderRow = tbody.querySelector("td[colspan='5']"); // Ajustar colspan se necessário
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
      nLinha.setAttribute("data-ausencia-id", newId);

      let cell = nLinha.insertCell();
      cell.className = "p-2 text-center";
      let inputChk = document.createElement("input");
      inputChk.type = "checkbox";
      inputChk.className = `ausencia-select-checkbox ${tailwindCheckboxClasses}`;
      cell.appendChild(inputChk);

      cell = nLinha.insertCell();
      cell.className = "p-1";
      let inputDI = document.createElement("input");
      inputDI.type = "date";
      inputDI.className = `ausencia-data-inicio ${tailwindInputClasses}`;
      cell.appendChild(inputDI);
      inputDI.focus();

      cell = nLinha.insertCell();
      cell.className = "p-1";
      let inputDF = document.createElement("input");
      inputDF.type = "date";
      inputDF.className = `ausencia-data-fim ${tailwindInputClasses}`;
      cell.appendChild(inputDF);

      cell = nLinha.insertCell();
      cell.className = "p-1";
      const selColabAusencia = document.createElement("select");
      selColabAusencia.className = `ausencia-colaborador ${tailwindSelectClasses}`;
      popularSelectColaborador(selColabAusencia, null, colaboradoresGlobais);
      cell.appendChild(selColabAusencia);

      cell = nLinha.insertCell();
      cell.className = "p-1";
      let inputObs = document.createElement("input");
      inputObs.type = "text";
      inputObs.className = `ausencia-observacoes ${tailwindInputClasses}`;
      inputObs.placeholder = "Motivo/Observações da ausência";
      cell.appendChild(inputObs);
    });
  }
  const btnSalvarAusencias = document.getElementById("save-ausencias-button");
  if (btnSalvarAusencias) {
    btnSalvarAusencias.addEventListener("click", () => {
      console.log(
        "[DEBUG] Botão 'Salvar Ausências' clicado (ausenciasManager.js)."
      );
      const csrfTokenEl = document.getElementById("csrf-token-ausencias");
      const csrfToken = csrfTokenEl ? csrfTokenEl.value : null;
      if (!csrfToken) {
        showToast(
          "Erro de segurança (token ausências ausente). Recarregue.",
          "error"
        );
        return;
      }
      const dados = coletarDadosDaTabelaDeAusencias();
      console.log(
        "[DEBUG] Dados coletados para salvar ausências (ausenciasManager.js):",
        dados
      );
      if (dados && dados.length > 0) {
        salvarDadosAusenciasNoServidor(dados, csrfToken);
      } else if (dados && dados.length === 0) {
        const tbody = document.querySelector("#ausencias-table-main tbody");
        const placeholderVisivel =
          tbody && tbody.querySelector("td[colspan='5']"); // Ajustar colspan
        if (placeholderVisivel || (tbody && tbody.rows.length === 0)) {
          showToast("Adicione uma ausência para salvar.", "info");
        } else {
          showToast(
            "Nenhuma ausência válida para salvar. Verifique as linhas.",
            "warning"
          );
        }
      } else if (dados === null) {
        // Indica erro de validação
        console.log(
          "[DEBUG] Coleta de dados de ausências retornou null (erro de validação) (ausenciasManager.js)."
        );
      } else {
        console.error(
          "[DEBUG] coletarDadosDaTabelaDeAusencias retornou valor inesperado:",
          dados
        );
        showToast("Erro interno ao coletar dados das ausências.", "error");
      }
    });
  }
  const chkAllAus = document.getElementById("select-all-ausencias");
  if (chkAllAus) {
    chkAllAus.addEventListener("change", () => {
      document
        .querySelectorAll("#ausencias-table-main .ausencia-select-checkbox")
        .forEach((c) => (c.checked = chkAllAus.checked));
    });
  }
  const btnDelSelAus = document.getElementById(
    "delete-selected-ausencias-button"
  );
  if (btnDelSelAus) {
    btnDelSelAus.addEventListener("click", () => {
      console.log(
        "[DEBUG] Botão 'Excluir Ausências Selecionadas' clicado (ausenciasManager.js)."
      );
      const csrfTokenEl = document.getElementById("csrf-token-ausencias");
      const csrfToken = csrfTokenEl ? csrfTokenEl.value : null;
      if (!csrfToken) {
        showToast("Erro de segurança (token ausências). Recarregue.", "error");
        return;
      }
      const idsParaExcluirServidor = [];
      let linhasNovasRemovidasLocalmente = 0;

      document
        .querySelectorAll(
          "#ausencias-table-main .ausencia-select-checkbox:checked"
        )
        .forEach((c) => {
          const tr = c.closest("tr");
          if (tr) {
            const id = tr.getAttribute("data-ausencia-id");
            if (id && !id.startsWith("new-")) {
              idsParaExcluirServidor.push(id);
            } else if (id && id.startsWith("new-")) {
              tr.remove();
              linhasNovasRemovidasLocalmente++;
            }
          }
        });

      if (idsParaExcluirServidor.length > 0) {
        excluirAusenciasNoServidor(idsParaExcluirServidor, csrfToken);
      }
      if (linhasNovasRemovidasLocalmente > 0) {
        showToast(
          `${linhasNovasRemovidasLocalmente} linha(s) nova(s) (não salva(s)) foram removida(s).`,
          "info"
        );
        const tbody = document.querySelector("#ausencias-table-main tbody");
        if (tbody && tbody.rows.length === 0) {
          popularTabelaAusencias([]);
        }
      }

      if (
        idsParaExcluirServidor.length === 0 &&
        linhasNovasRemovidasLocalmente === 0
      ) {
        showToast("Nenhuma ausência selecionada para exclusão.", "info");
      }
      if (chkAllAus) chkAllAus.checked = false;
    });
  }
}
