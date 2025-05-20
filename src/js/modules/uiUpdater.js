// src/js/modules/uiUpdater.js
import { nomesMeses } from "./utils.js";
import * as state from "./state.js"; // Importa tudo de state.js como um objeto 'state'

console.log("[DEBUG] uiUpdater.js: Módulo carregado.");

export function updateCurrentMonthYearDisplayTurnos() {
  console.log(
    "[DEBUG] updateCurrentMonthYearDisplayTurnos (uiUpdater.js): Iniciando."
  );
  const displayElement = document.getElementById("current-month-year-display");
  const summaryPeriodElement = document.getElementById(
    "employee-summary-period"
  );

  if (displayElement) {
    const monthName =
      nomesMeses[state.currentDisplayMonth] ||
      `Mês ${state.currentDisplayMonth}`;
    displayElement.innerHTML = `<i data-lucide="list-todo" class="w-5 h-5 mr-2 text-blue-600"></i> Turnos - ${monthName} ${state.currentDisplayYear}`;
    displayElement.dataset.year = state.currentDisplayYear;
    displayElement.dataset.month = state.currentDisplayMonth;
    if (typeof lucide !== "undefined") lucide.createIcons();
  } else {
    console.warn(
      "[DEBUG] Elemento 'current-month-year-display' não encontrado (uiUpdater.js)."
    );
  }
  if (summaryPeriodElement) {
    summaryPeriodElement.textContent =
      nomesMeses[state.currentDisplayMonth] || "";
  } else {
    console.warn(
      "[DEBUG] Elemento 'employee-summary-period' não encontrado (uiUpdater.js)."
    );
  }
}

export function updateCurrentMonthYearDisplayAusencias() {
  const displayElement = document.getElementById(
    "current-month-year-ausencias-display"
  );
  if (displayElement) {
    const monthName =
      nomesMeses[state.currentDisplayMonthAusencias] ||
      `Mês ${state.currentDisplayMonthAusencias}`;
    displayElement.innerHTML = `<i data-lucide="user-x" class="w-5 h-5 mr-2 text-blue-600"></i> Ausências - ${monthName} ${state.currentDisplayYearAusencias}`;
    displayElement.dataset.year = state.currentDisplayYearAusencias;
    displayElement.dataset.month = state.currentDisplayMonthAusencias;
    if (typeof lucide !== "undefined") lucide.createIcons();
  }
}

export function updateFeriadosDisplay() {
  const displayElement = document.getElementById("feriados-mes-ano-display");
  if (displayElement) {
    const monthName =
      nomesMeses[state.currentDisplayMonthFeriados] ||
      `Mês ${state.currentDisplayMonthFeriados}`;
    displayElement.innerHTML = `<i data-lucide="calendar-heart" class="w-4 h-4 mr-2 text-blue-600"></i> Feriados - ${monthName} ${state.currentDisplayYearFeriados}`;
    // Não precisa definir data-year e data-month aqui, pois não são usados para navegação individual neste widget
    if (typeof lucide !== "undefined") lucide.createIcons();
  }
}

export function updateEscalaSabadosDisplay() {
  const displayElement = document.getElementById("escala-sabados-display"); // O ID no HTML é "escala-sabados-display"
  if (displayElement) {
    // Este display não mostra mês/ano, apenas serve para carregar os dados com base no estado.
    // Se quiser adicionar mês/ano visualmente, pode fazer como os outros.
    // Apenas garantindo que o elemento existe para futuras modificações.
    // Os data attributes são setados no HTML. O script.js original chamava:
    // updateEscalaSabadosDisplay(currentDisplayYearEscalaSabados, currentDisplayMonthEscalaSabados);
    // onde a função apenas fazia:
    // displayElement.dataset.year = ano; displayElement.dataset.month = mes;
    // Esta lógica agora está implícita pelo estado global.
    const monthName =
      nomesMeses[state.currentDisplayMonthEscalaSabados] ||
      `Mês ${state.currentDisplayMonthEscalaSabados}`;
    displayElement.innerHTML = `<i data-lucide="calendar-check" class="w-4 h-4 mr-2 text-blue-600"></i> Escala - Sábados (${monthName} ${state.currentDisplayYearEscalaSabados})`;
    if (typeof lucide !== "undefined") lucide.createIcons();
  }
}

export function updateAusenciaSetorDisplay() {
  const displayElement = document.getElementById("ausencia-setor-display"); // O ID no HTML é "ausencia-setor-display"
  if (displayElement) {
    // Similar ao de Escala Sábados.
    const monthName =
      nomesMeses[state.currentDisplayMonthAusenciaSetor] ||
      `Mês ${state.currentDisplayMonthAusenciaSetor}`;
    displayElement.innerHTML = `<i data-lucide="user-cog" class="w-4 h-4 mr-2 text-blue-600"></i> Ausência Setor (${monthName} ${state.currentDisplayYearAusenciaSetor})`;
    if (typeof lucide !== "undefined") lucide.createIcons();
  }
}

export function updateAllDisplays() {
  console.log("[DEBUG] updateAllDisplays chamado (uiUpdater.js)");
  updateCurrentMonthYearDisplayTurnos();
  updateCurrentMonthYearDisplayAusencias();
  updateFeriadosDisplay();
  updateEscalaSabadosDisplay();
  updateAusenciaSetorDisplay();
}
