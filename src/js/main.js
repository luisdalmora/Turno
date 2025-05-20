// src/js/main.js
import * as utils from "./modules/utils.js";
import * as state from "./modules/state.js";
import * as uiUpdater from "./modules/uiUpdater.js";
import * as turnosManager from "./modules/turnosManager.js";
import * as ausenciasManager from "./modules/ausenciasManager.js";
import * as observacoesManager from "./modules/observacoesManager.js";
import * as widgetsDashboard from "./modules/widgetsDashboard.js";
import * as backupHandler from "./modules/backupHandler.js";

console.log("[DEBUG] main.js: Módulo principal carregado.");

// Função centralizada para navegação de mês e recarregamento de dados
async function syncDatesAndReloadAll(newYear, newMonth) {
  console.log(
    `[DEBUG] syncDatesAndReloadAll (main.js) chamado com newYear: ${newYear}, newMonth: ${newMonth}`
  );
  state.updateGlobalDate(newYear, newMonth); // Atualiza o estado global

  uiUpdater.updateAllDisplays(); // Atualiza todos os displays de data na UI

  // Recarrega dados dos módulos que dependem da data global
  if (document.getElementById("shifts-table-main")) {
    await turnosManager.carregarTurnosDoServidor(
      state.currentDisplayYear,
      state.currentDisplayMonth,
      true
    );
  }
  if (document.getElementById("ausencias-table-main")) {
    await ausenciasManager.carregarAusenciasDoServidor(
      state.currentDisplayYearAusencias,
      state.currentDisplayMonthAusencias
    );
  }
  if (document.getElementById("feriados-table")) {
    await widgetsDashboard.carregarFeriados(
      state.currentDisplayYearFeriados,
      state.currentDisplayMonthFeriados
    );
  }
  if (document.getElementById("escala-sabados-table")) {
    await widgetsDashboard.carregarEscalaSabados(
      state.currentDisplayYearEscalaSabados,
      state.currentDisplayMonthEscalaSabados
    );
  }
  if (document.getElementById("ausencia-setor-table")) {
    await widgetsDashboard.carregarAusenciaSetor(
      state.currentDisplayYearAusenciaSetor,
      state.currentDisplayMonthAusenciaSetor
    );
  }
}

document.addEventListener("DOMContentLoaded", async function () {
  console.log("[DEBUG] DOMContentLoaded (main.js): Evento disparado.");
  console.log(
    `[DEBUG] Data inicial global (main.js) ${state.currentDisplayMonth}/${state.currentDisplayYear}`
  );

  // Tenta pegar a data inicial dos data-attributes do elemento de display de turnos, se existir
  // Isso é útil se o PHP pré-renderizar uma data diferente da atual.
  const displayElementInit = document.getElementById(
    "current-month-year-display"
  );
  if (
    displayElementInit &&
    displayElementInit.dataset.year &&
    displayElementInit.dataset.month
  ) {
    const initialYear = parseInt(displayElementInit.dataset.year, 10);
    const initialMonth = parseInt(displayElementInit.dataset.month, 10);
    if (!isNaN(initialYear) && !isNaN(initialMonth)) {
      state.updateGlobalDate(initialYear, initialMonth);
      console.log(
        `[DEBUG] Data ajustada pelo display para ${state.currentDisplayMonth}/${state.currentDisplayYear} (main.js)`
      );
    }
  }

  uiUpdater.updateAllDisplays(); // Atualiza todos os displays com a data correta (inicial ou do DOM)

  // Inicialização de funcionalidades
  if (typeof lucide !== "undefined") {
    lucide.createIcons();
  } else {
    console.warn(
      "[DEBUG] Biblioteca Lucide (lucide.js) não está definida (main.js)."
    );
  }

  await utils.buscarEArmazenarColaboradores(); // Carrega colaboradores uma vez para todos os módulos

  // Carregamento inicial de dados para os módulos presentes na página home.php
  // Verifique a existência dos elementos antes de carregar/inicializar
  if (document.getElementById("shifts-table-main")) {
    turnosManager.initTurnosEventListeners();
    await turnosManager.carregarTurnosDoServidor(
      state.currentDisplayYear,
      state.currentDisplayMonth,
      true
    );
  }
  if (document.getElementById("ausencias-table-main")) {
    ausenciasManager.initAusenciasEventListeners();
    await ausenciasManager.carregarAusenciasDoServidor(
      state.currentDisplayYearAusencias,
      state.currentDisplayMonthAusencias
    );
  }
  if (document.getElementById("observacoes-gerais-textarea")) {
    observacoesManager.initObservacoesEventListeners();
  }
  if (document.getElementById("feriados-table")) {
    await widgetsDashboard.carregarFeriados(
      state.currentDisplayYearFeriados,
      state.currentDisplayMonthFeriados
    );
  }
  if (document.getElementById("escala-sabados-table")) {
    await widgetsDashboard.carregarEscalaSabados(
      state.currentDisplayYearEscalaSabados,
      state.currentDisplayMonthEscalaSabados
    );
  }
  if (document.getElementById("ausencia-setor-table")) {
    await widgetsDashboard.carregarAusenciaSetor(
      state.currentDisplayYearAusenciaSetor,
      state.currentDisplayMonthAusenciaSetor
    );
  }
  if (document.getElementById("backup-db-btn")) {
    backupHandler.initBackupHandler();
  }

  // --- Event Listeners Globais (Navegação de Mês) ---
  const prevMonthButton = document.getElementById("prev-month-button");
  if (prevMonthButton) {
    prevMonthButton.addEventListener("click", () => {
      console.log("[DEBUG] Botão 'Anterior' (Turnos/Geral) clicado (main.js).");
      let newMonth = state.currentDisplayMonth - 1;
      let newYear = state.currentDisplayYear;
      if (newMonth < 1) {
        newMonth = 12;
        newYear--;
      }
      syncDatesAndReloadAll(newYear, newMonth);
    });
  }

  const nextMonthButton = document.getElementById("next-month-button");
  if (nextMonthButton) {
    nextMonthButton.addEventListener("click", () => {
      console.log("[DEBUG] Botão 'Próximo' (Turnos/Geral) clicado (main.js).");
      let newMonth = state.currentDisplayMonth + 1;
      let newYear = state.currentDisplayYear;
      if (newMonth > 12) {
        newMonth = 1;
        newYear++;
      }
      syncDatesAndReloadAll(newYear, newMonth);
    });
  }

  // Navegação específica para Ausências (se os botões forem diferentes e não controlarem tudo)
  // Se os botões "prev-month-button" e "next-month-button" são os únicos para navegação geral,
  // os listeners abaixo podem não ser necessários ou precisarão de IDs diferentes.
  // O script.js original tinha IDs "prev-month-ausencias-button" e "next-month-ausencias-button"
  // que também chamavam syncDatesAndReloadAll. Se esses botões controlam a mesma data global,
  // os listeners acima já cobrem.
  const prevMonthBtnAus = document.getElementById(
    "prev-month-ausencias-button"
  );
  if (prevMonthBtnAus && prevMonthBtnAus !== prevMonthButton) {
    // Só adiciona se for um botão diferente
    prevMonthBtnAus.addEventListener("click", () => {
      console.log(
        "[DEBUG] Botão 'Anterior' (Ausências Específico) clicado (main.js)."
      );
      let newMonth = state.currentDisplayMonthAusencias - 1;
      let newYear = state.currentDisplayYearAusencias;
      if (newMonth < 1) {
        newMonth = 12;
        newYear--;
      }
      // Se a navegação de ausências deve ser independente:
      // state.setAusenciasDate(newYear, newMonth);
      // uiUpdater.updateCurrentMonthYearDisplayAusencias();
      // ausenciasManager.carregarAusenciasDoServidor(newYear, newMonth);
      // Ou se deve sincronizar tudo:
      syncDatesAndReloadAll(newYear, newMonth);
    });
  }

  const nextMonthBtnAus = document.getElementById(
    "next-month-ausencias-button"
  );
  if (nextMonthBtnAus && nextMonthBtnAus !== nextMonthButton) {
    // Só adiciona se for um botão diferente
    nextMonthBtnAus.addEventListener("click", () => {
      console.log(
        "[DEBUG] Botão 'Próximo' (Ausências Específico) clicado (main.js)."
      );
      let newMonth = state.currentDisplayMonthAusencias + 1;
      let newYear = state.currentDisplayYearAusencias;
      if (newMonth > 12) {
        newMonth = 1;
        newYear++;
      }
      // Similar ao anterior, decidir se é navegação independente ou sincronizada
      syncDatesAndReloadAll(newYear, newMonth);
    });
  }

  // Logout (do script.js original)
  const logoutLk = document.getElementById("logout-link");
  if (logoutLk) {
    logoutLk.addEventListener("click", (e) => {
      e.preventDefault();
      utils.showToast("Saindo do sistema...", "info", 1500);
      setTimeout(() => {
        if (logoutLk.href) window.location.href = logoutLk.href;
      }, 1500);
    });
  }

  // Placeholder flutuante (do index.html e conta.html, pode ser generalizado aqui se necessário para outras páginas)
  // Se for apenas para login/cadastro, manter nos HTMLs específicos é mais simples.
  // Para generalizar, seria necessário garantir que os seletores existam.
  // document.querySelectorAll('.input-field:not(select)').forEach(input => {
  //   if(input) { // Checa se o input existe na página atual
  //     const checkValue = () => { input.classList.toggle("has-val", input.value.trim() !== ""); };
  //     input.addEventListener("blur", checkValue);
  //     input.addEventListener("input", checkValue);
  //     checkValue();
  //   }
  // });

  console.log(
    "[DEBUG] main.js: Todos os listeners e carregamentos iniciais configurados."
  );
});
// ... final do main.js
window.showGlobalToast = utils.showToast; // Expõe a função para ser usada por scripts inline antigos
console.log("[DEBUG] main.js: Fim da análise do script.");
