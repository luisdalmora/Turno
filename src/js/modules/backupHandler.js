// src/js/modules/backupHandler.js
import { showToast } from "./utils.js";
console.log("[DEBUG] backupHandler.js: Módulo carregado.");

function showBackupModal(
  message,
  isLoading,
  isError,
  isSuccess,
  elMsg,
  elProgressContainer,
  elProgress,
  elDownloadLink,
  elCloseBtn,
  elBackdrop
) {
  if (elBackdrop) elBackdrop.classList.add("show");
  if (elMsg) elMsg.textContent = message;

  if (elProgressContainer)
    elProgressContainer.style.display = isLoading ? "block" : "none";
  if (elProgress && isLoading) {
    elProgress.style.width = "0%";
    elProgress.classList.add("indeterminate"); // Para animação contínua
    // setTimeout(() => (elProgress.style.width = "100%"), 100); // Não mais útil com indeterminate
  } else if (elProgress) {
    elProgress.classList.remove("indeterminate");
  }

  if (elMsg) {
    elMsg.classList.remove("text-green-600", "text-red-600", "text-gray-700");
    if (isSuccess) elMsg.classList.add("text-green-600");
    else if (isError) elMsg.classList.add("text-red-600");
    else elMsg.classList.add("text-gray-700");
  }

  if (elDownloadLink) elDownloadLink.classList.add("hidden"); // Esconde por padrão
  if (elCloseBtn) elCloseBtn.style.display = "none"; // Esconde por padrão
}

export function initBackupHandler() {
  const backupDbBtn = document.getElementById("backup-db-btn");
  const csrfTokenBackupInput = document.getElementById("csrf-token-backup");
  const backupModalBackdrop = document.getElementById("backup-modal-backdrop");
  const backupModalMessage = document.getElementById("backup-modal-message");
  const backupModalCloseBtn = document.getElementById("backup-modal-close-btn");
  const backupProgressBarContainer = document.getElementById(
    "backup-progress-bar-container"
  );
  const backupProgressBar = document.getElementById("backup-progress-bar");
  const backupDownloadLink = document.getElementById("backup-download-link");
  let originalBackupBtnHTML = "";

  if (backupDbBtn && csrfTokenBackupInput) {
    originalBackupBtnHTML = backupDbBtn.innerHTML;

    if (backupModalCloseBtn && backupModalBackdrop) {
      backupModalCloseBtn.addEventListener("click", () => {
        backupModalBackdrop.classList.remove("show");
        if (backupDbBtn && originalBackupBtnHTML) {
          backupDbBtn.disabled = false;
          backupDbBtn.innerHTML = originalBackupBtnHTML;
          if (typeof lucide !== "undefined") lucide.createIcons();
        }
      });
    }

    backupDbBtn.addEventListener("click", async function (event) {
      event.preventDefault();
      if (backupDbBtn.disabled) return;
      if (
        !confirm("Tem certeza que deseja iniciar o backup do banco de dados?")
      )
        return;

      if (
        backupModalBackdrop &&
        backupModalMessage &&
        backupProgressBarContainer &&
        backupProgressBar &&
        backupDownloadLink &&
        backupModalCloseBtn
      ) {
        showBackupModal(
          "Iniciando backup, por favor aguarde...",
          true,
          false,
          false,
          backupModalMessage,
          backupProgressBarContainer,
          backupProgressBar,
          backupDownloadLink,
          backupModalCloseBtn,
          backupModalBackdrop
        );
      }

      backupDbBtn.disabled = true;
      backupDbBtn.innerHTML = `<i data-lucide="loader-circle" class="animate-spin w-4 h-4 mr-2"></i> Processando...`;
      if (typeof lucide !== "undefined") lucide.createIcons();
      const csrfToken = csrfTokenBackupInput.value;

      try {
        const response = await fetch("backup_database.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            action: "create_backup",
            csrf_token_backup: csrfToken,
          }),
        });
        let data;
        if (!response.ok) {
          let errorMsg = `Servidor respondeu com erro: HTTP ${response.status}`;
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
          if (backupModalBackdrop) {
            // Checa se o modal ainda é relevante
            showBackupModal(
              data.message || "Backup concluído!",
              false,
              false,
              true,
              backupModalMessage,
              backupProgressBarContainer,
              backupProgressBar,
              backupDownloadLink,
              backupModalCloseBtn,
              backupModalBackdrop
            );
            if (backupModalCloseBtn)
              backupModalCloseBtn.style.display = "inline-flex";
            if (data.download_url && backupDownloadLink) {
              backupDownloadLink.href = data.download_url;
              backupDownloadLink.classList.remove("hidden");
            } else if (
              data.filename &&
              !data.download_url &&
              backupDownloadLink
            ) {
              // Fallback se só o nome do arquivo for retornado
              backupDownloadLink.href = `download_backup_file.php?file=${encodeURIComponent(
                data.filename
              )}`;
              backupDownloadLink.classList.remove("hidden");
            } else {
              if (backupDownloadLink)
                backupDownloadLink.classList.add("hidden");
              showToast(
                "URL de download não fornecida pelo servidor.",
                "warning"
              );
            }
          }
        } else {
          const errorMsg = data.message || "Falha no backup.";
          if (backupModalBackdrop) {
            showBackupModal(
              "Erro: " + errorMsg,
              false,
              true,
              false,
              backupModalMessage,
              backupProgressBarContainer,
              backupProgressBar,
              backupDownloadLink,
              backupModalCloseBtn,
              backupModalBackdrop
            );
            if (backupModalCloseBtn)
              backupModalCloseBtn.style.display = "inline-flex";
          }
          showToast("Falha no backup: " + errorMsg, "error", 7000);
        }
      } catch (error) {
        console.error(
          "[DEBUG] Erro requisição de backup (backupHandler.js):",
          error
        );
        if (backupModalBackdrop) {
          showBackupModal(
            "Erro de comunicação ao tentar backup. Verifique o console.",
            false,
            true,
            false,
            backupModalMessage,
            backupProgressBarContainer,
            backupProgressBar,
            backupDownloadLink,
            backupModalCloseBtn,
            backupModalBackdrop
          );
          if (backupModalCloseBtn)
            backupModalCloseBtn.style.display = "inline-flex";
        }
        showToast("Erro de comunicação: " + error.message, "error");
      }
      // Não reabilitar o botão aqui, pois o modal cobre a tela.
      // O botão é reabilitado quando o modal é fechado.
    });
  } else {
    if (!backupDbBtn)
      console.warn(
        "[DEBUG] Botão de backup (backup-db-btn) não encontrado (backupHandler.js)."
      );
    if (!csrfTokenBackupInput)
      console.warn(
        "[DEBUG] Campo CSRF de backup (csrf-token-backup) não encontrado (backupHandler.js)."
      );
  }
}
