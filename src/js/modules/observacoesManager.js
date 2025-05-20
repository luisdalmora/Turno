// src/js/modules/observacoesManager.js
import { showToast } from "./utils.js";
console.log("[DEBUG] observacoesManager.js: Módulo carregado.");

async function carregarObservacaoGeral() {
  const textarea = document.getElementById("observacoes-gerais-textarea");
  const csrfTokenObsGeralInput = document.getElementById(
    "csrf-token-obs-geral"
  );
  if (!textarea || !csrfTokenObsGeralInput) return;

  try {
    const response = await fetch("gerenciar_observacao_geral.php");
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
      textarea.value = data.observacao || "";
      if (data.csrf_token) csrfTokenObsGeralInput.value = data.csrf_token;
    } else {
      showToast(data.message || "Erro ao carregar observação.", "error");
    }
  } catch (error) {
    console.error(
      "[DEBUG] Erro de conexão ao carregar observação (observacoesManager.js):",
      error
    );
    showToast(
      "Erro de conexão ao carregar observação: " + error.message,
      "error"
    );
  }
}

async function salvarObservacaoGeral() {
  const textarea = document.getElementById("observacoes-gerais-textarea");
  const csrfTokenInput = document.getElementById("csrf-token-obs-geral");
  const saveButton = document.getElementById("salvar-observacoes-gerais-btn");
  if (!textarea || !csrfTokenInput || !saveButton) return;

  const originalButtonHtml = saveButton.innerHTML;
  saveButton.disabled = true;
  saveButton.innerHTML = `<i data-lucide="loader-circle" class="lucide-spin w-4 h-4 mr-1.5"></i> Salvando...`;
  if (typeof lucide !== "undefined") lucide.createIcons();

  const payload = {
    observacao: textarea.value,
    csrf_token: csrfTokenInput.value,
  };
  try {
    const response = await fetch("gerenciar_observacao_geral.php", {
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
      showToast(data.message || "Observação salva!", "success");
      if (data.csrf_token) csrfTokenInput.value = data.csrf_token;
    } else {
      showToast(data.message || "Erro ao salvar observação.", "error");
    }
  } catch (error) {
    console.error(
      "[DEBUG] Erro de conexão ao salvar observação (observacoesManager.js):",
      error
    );
    showToast(
      "Erro de conexão ao salvar observação: " + error.message,
      "error"
    );
  } finally {
    saveButton.disabled = false;
    saveButton.innerHTML = originalButtonHtml;
    if (typeof lucide !== "undefined") lucide.createIcons();
  }
}

export function initObservacoesEventListeners() {
  const salvarObsBtn = document.getElementById("salvar-observacoes-gerais-btn");
  const obsGeralTextarea = document.getElementById(
    "observacoes-gerais-textarea"
  );
  if (salvarObsBtn && obsGeralTextarea) {
    carregarObservacaoGeral(); // Carrega ao inicializar
    salvarObsBtn.addEventListener("click", salvarObservacaoGeral);
  }
}
