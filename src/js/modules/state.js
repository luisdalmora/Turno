// src/js/modules/state.js
console.log("[DEBUG] state.js: Módulo carregado.");

const dataAtualGlobal = new Date();

export let currentDisplayYear = dataAtualGlobal.getFullYear();
export let currentDisplayMonth = dataAtualGlobal.getMonth() + 1; // JS month é 0-indexado

// Mantendo as variáveis separadas por módulo para clareza,
// a menos que uma sincronização total seja sempre desejada via syncDatesAndReloadAll.
// Se syncDatesAndReloadAll SEMPRE atualiza todos, poderíamos usar apenas as duas acima.
// Por enquanto, vamos mantê-las para o caso de lógicas de navegação independentes no futuro,
// mas elas serão inicializadas e sincronizadas pelo main.js.
export let currentDisplayYearAusencias = dataAtualGlobal.getFullYear();
export let currentDisplayMonthAusencias = dataAtualGlobal.getMonth() + 1;
export let currentDisplayYearFeriados = dataAtualGlobal.getFullYear();
export let currentDisplayMonthFeriados = dataAtualGlobal.getMonth() + 1;
export let currentDisplayYearEscalaSabados = dataAtualGlobal.getFullYear();
export let currentDisplayMonthEscalaSabados = dataAtualGlobal.getMonth() + 1;
export let currentDisplayYearAusenciaSetor = dataAtualGlobal.getFullYear();
export let currentDisplayMonthAusenciaSetor = dataAtualGlobal.getMonth() + 1;

export function updateGlobalDate(year, month) {
  currentDisplayYear = year;
  currentDisplayMonth = month;
  currentDisplayYearAusencias = year;
  currentDisplayMonthAusencias = month;
  currentDisplayYearFeriados = year;
  currentDisplayMonthFeriados = month;
  currentDisplayYearEscalaSabados = year;
  currentDisplayMonthEscalaSabados = month;
  currentDisplayYearAusenciaSetor = year;
  currentDisplayMonthAusenciaSetor = month;
  console.log(
    `[DEBUG] Datas globais atualizadas para: ${month}/${year} (state.js)`
  );
}

// Funções para definir datas específicas, se necessário no futuro
export function setTurnosDate(year, month) {
  currentDisplayYear = year;
  currentDisplayMonth = month;
}
export function setAusenciasDate(year, month) {
  currentDisplayYearAusencias = year;
  currentDisplayMonthAusencias = month;
}
// ... e assim por diante para outros módulos se eles precisarem de datas independentes
