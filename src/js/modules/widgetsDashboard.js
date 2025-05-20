// src/js/modules/widgetsDashboard.js
import { showToast, nomesMeses } from "./utils.js"; // nomesMeses usado para formatar data em Ausencia Setor
import * as state from "./state.js";
import {
  updateFeriadosDisplay,
  updateEscalaSabadosDisplay,
  updateAusenciaSetorDisplay,
} from "./uiUpdater.js";

console.log("[DEBUG] widgetsDashboard.js: Módulo carregado.");

export async function carregarFeriados(ano, mes) {
  const tbody = document.querySelector("#feriados-table tbody");
  if (!tbody) return;
  tbody.innerHTML = `<tr><td colspan="2" class="p-2 text-center text-gray-500 text-sm">Carregando... <i data-lucide="loader-circle" class="lucide-spin inline-block w-4 h-4"></i></td></tr>`;
  if (typeof lucide !== "undefined") lucide.createIcons();
  try {
    const response = await fetch(`carregar_feriados.php?ano=${ano}&mes=${mes}`);
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
    console.error(
      "[DEBUG] Erro ao buscar feriados (widgetsDashboard.js):",
      error
    );
    tbody.innerHTML = `<tr><td colspan="2" class="p-2 text-center text-red-500 text-sm">Erro de conexão ao carregar feriados.</td></tr>`;
    showToast(
      "Erro de conexão ao carregar feriados: " + error.message,
      "error"
    );
  }
}

export async function carregarEscalaSabados(ano, mes) {
  const tbody = document.querySelector("#escala-sabados-table tbody");
  if (!tbody) {
    console.error(
      "[DEBUG] Corpo da tabela #escala-sabados-table não encontrado (widgetsDashboard.js)."
    );
    return;
  }
  tbody.innerHTML = `<tr><td colspan="2" class="p-2 text-center text-gray-500 text-sm">Carregando... <i data-lucide="loader-circle" class="lucide-spin inline-block w-4 h-4"></i></td></tr>`;
  if (typeof lucide !== "undefined") lucide.createIcons();

  try {
    const response = await fetch(
      `carregar_escala_sabados.php?ano=${ano}&mes=${mes}`
    );
    let data;
    if (!response.ok) {
      let errorMsg = `Falha na requisição: HTTP ${response.status}`;
      try {
        data = await response.json();
        errorMsg = data.message || errorMsg;
      } catch (e) {
        const errText = await response.text().catch(() => "");
        errorMsg = errText.substring(0, 200) || errorMsg;
      }
      throw new Error(errorMsg);
    }
    data = await response.json();

    tbody.innerHTML = "";
    if (data.success && data.escala) {
      if (data.escala.length === 0) {
        const r = tbody.insertRow();
        r.className = "bg-white";
        const c = r.insertCell();
        c.colSpan = 2;
        c.className = "p-2 text-center text-gray-500 text-sm";
        c.textContent = "Nenhuma escala de sábado para este período.";
      } else {
        data.escala.forEach((item) => {
          const r = tbody.insertRow();
          r.className = "bg-white hover:bg-gray-50";
          const cellData = r.insertCell();
          cellData.className = "p-2 text-sm text-gray-700";
          cellData.textContent = item.data;
          const cellColaborador = r.insertCell();
          cellColaborador.className = "p-2 text-sm text-gray-700";
          cellColaborador.textContent = item.colaborador;
        });
      }
    } else {
      tbody.innerHTML = `<tr><td colspan="2" class="p-2 text-center text-red-500 text-sm">${
        data.message || "Erro ao carregar dados."
      }</td></tr>`;
      showToast(
        data.message || "Erro ao carregar escala de sábados.",
        "warning"
      );
    }
  } catch (error) {
    console.error(
      "[DEBUG] Erro ao buscar escala de sábados (widgetsDashboard.js):",
      error
    );
    tbody.innerHTML = `<tr><td colspan="2" class="p-2 text-center text-red-500 text-sm">Erro de conexão ou dados inválidos. Verifique o console.</td></tr>`;
    showToast("Erro ao carregar escala de sábados: " + error.message, "error");
  }
}

export async function carregarAusenciaSetor(ano, mes) {
  const tbody = document.querySelector("#ausencia-setor-table tbody");
  if (!tbody) {
    console.error(
      "[DEBUG] Corpo da tabela #ausencia-setor-table não encontrado (widgetsDashboard.js)."
    );
    return;
  }
  tbody.innerHTML = `<tr><td colspan="2" class="p-2 text-center text-gray-500 text-sm">Carregando... <i data-lucide="loader-circle" class="lucide-spin inline-block w-4 h-4"></i></td></tr>`;
  if (typeof lucide !== "undefined") lucide.createIcons();

  try {
    const response = await fetch(
      `carregar_ausencia_setor.php?ano=${ano}&mes=${mes}`
    );
    let data;
    if (!response.ok) {
      let errorMsg = `Falha na requisição: HTTP ${response.status}`;
      try {
        data = await response.json();
        errorMsg = data.message || errorMsg;
      } catch (e) {
        const errText = await response.text().catch(() => "");
        errorMsg = errText.substring(0, 200) || errorMsg;
      }
      throw new Error(errorMsg);
    }
    data = await response.json();

    tbody.innerHTML = "";
    if (data.success && data.ausencias) {
      if (data.ausencias.length === 0) {
        const r = tbody.insertRow();
        r.className = "bg-white";
        const c = r.insertCell();
        c.colSpan = 2;
        c.className = "p-2 text-center text-gray-500 text-sm";
        c.textContent = "Nenhuma ausência de setor para este período.";
      } else {
        data.ausencias.forEach((item) => {
          const r = tbody.insertRow();
          r.className = "bg-white hover:bg-gray-50";
          const cellData = r.insertCell();
          cellData.className = "p-2 text-sm text-gray-700";
          // Lógica de formatação de data (simplificada, assumindo que item.data é 'dd/mm')
          // A lógica original de formatação complexa com dia da semana pode ser adicionada aqui se necessário
          // Para a API atual, o PHP já retorna 'dd/mm' em carregar_ausencia_setor.php
          cellData.textContent = item.data;

          const cellColaborador = r.insertCell();
          cellColaborador.className = "p-2 text-sm text-gray-700";
          cellColaborador.textContent = item.colaborador;
        });
      }
    } else {
      tbody.innerHTML = `<tr><td colspan="2" class="p-2 text-center text-red-500 text-sm">${
        data.message || "Erro ao carregar dados."
      }</td></tr>`;
      showToast(
        data.message || "Erro ao carregar ausências do setor.",
        "warning"
      );
    }
  } catch (error) {
    console.error(
      "[DEBUG] Erro ao buscar ausência do setor (widgetsDashboard.js):",
      error
    );
    tbody.innerHTML = `<tr><td colspan="2" class="p-2 text-center text-red-500 text-sm">Erro de conexão ou dados inválidos. Verifique o console.</td></tr>`;
    showToast("Erro ao carregar ausência do setor: " + error.message, "error");
  }
}

export function initWidgetsDashboard() {
  // Carregamento inicial dos widgets é chamado pelo main.js/app.js
  // Esta função pode ser usada para event listeners específicos dos widgets, se houver.
  // Por enquanto, as funções de carregamento são exportadas para serem chamadas.
}
