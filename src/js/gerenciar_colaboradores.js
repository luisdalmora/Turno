// src/js/gerenciar_colaboradores.js
// Importa as funções necessárias do módulo utils.js
import { showToast } from './modules/utils.js';

document.addEventListener("DOMContentLoaded", function () {
  console.log("[DEBUG] gerenciar_colaboradores.js: DOMContentLoaded");

  const collaboratorsTableBody = document.querySelector("#collaborators-table tbody");
  const editModal = document.getElementById("edit-collaborator-modal");
  const editForm = document.getElementById("edit-collaborator-form");
  const modalCloseButton = document.getElementById("modal-close-btn");
  const cancelEditButton = document.getElementById("cancel-edit-colab-button");
  // const notify = showToast; // Usa a função showToast importada diretamente

  async function carregarColaboradoresNaTabela() {
    if (!collaboratorsTableBody) {
        console.warn("[DEBUG] gerenciar_colaboradores.js: collaboratorsTableBody não encontrado.");
        return;
    }
    collaboratorsTableBody.innerHTML = `<tr><td colspan="6" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">Carregando... <i data-lucide="loader-circle" class="lucide-spin inline-block"></i></td></tr>`;
    if (typeof lucide !== "undefined") lucide.createIcons();

    try {
      const response = await fetch(`listar_colaboradores.php`); // Assumindo que está na raiz
      const data = await response.json();
      collaboratorsTableBody.innerHTML = ""; // Limpa antes de popular ou mostrar erro

      if (data.success && data.colaboradores) {
        if (data.colaboradores.length === 0) {
          collaboratorsTableBody.innerHTML = `<tr><td colspan="6" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">Nenhum colaborador cadastrado. <a href="cadastrar_colaborador.php" class="text-blue-600 hover:underline">Adicionar novo</a>.</td></tr>`;
          return;
        }
        data.colaboradores.forEach((colab) => {
          const row = collaboratorsTableBody.insertRow();
          row.setAttribute("data-colab-id", colab.id);
          row.insertCell().textContent = colab.id;
          row.insertCell().textContent = colab.nome_completo;
          row.insertCell().textContent = colab.email || "N/A";
          row.insertCell().textContent = colab.cargo || "N/A";

          const statusCell = row.insertCell();
          statusCell.innerHTML = colab.ativo
            ? '<span class="status-ativo">Ativo</span>'
            : '<span class="status-inativo">Inativo</span>';
          statusCell.className = "px-6 py-4 whitespace-nowrap text-sm";


          const actionsCell = row.insertCell();
          actionsCell.className = "px-6 py-4 whitespace-nowrap text-right text-sm font-medium"; // Ajustado para Tailwind

          // Botão Editar com classes Tailwind
          const editButton = document.createElement("button");
          editButton.innerHTML = '<i data-lucide="edit-3" class="w-4 h-4 mr-1"></i> Editar';
          editButton.className = "action-button info btn-sm text-indigo-600 hover:text-indigo-900 mr-3 inline-flex items-center"; // Adicionadas classes Tailwind
          editButton.title = "Editar Colaborador";
          editButton.onclick = () => abrirModalEdicao(colab);
          actionsCell.appendChild(editButton);

          // Botão Alternar Status com classes Tailwind
          const toggleStatusButton = document.createElement("button");
          toggleStatusButton.innerHTML = colab.ativo
            ? '<i data-lucide="toggle-left" class="w-4 h-4 mr-1"></i> Desativar'
            : '<i data-lucide="toggle-right" class="w-4 h-4 mr-1"></i> Ativar';
          toggleStatusButton.className = colab.ativo
            ? "action-button warning btn-sm text-yellow-600 hover:text-yellow-900 inline-flex items-center" // Adicionadas classes Tailwind
            : "action-button success btn-sm text-green-600 hover:text-green-900 inline-flex items-center"; // Adicionadas classes Tailwind
          toggleStatusButton.title = colab.ativo ? "Desativar Colaborador" : "Ativar Colaborador";
          toggleStatusButton.onclick = () => alternarStatusColaborador(colab.id, !colab.ativo);
          actionsCell.appendChild(toggleStatusButton);
        });
        if (typeof lucide !== "undefined") lucide.createIcons();
      } else {
        const errorMessage = data.message || "Erro desconhecido";
        collaboratorsTableBody.innerHTML = `<tr><td colspan="6" class="px-6 py-4 whitespace-nowrap text-sm text-red-500 text-center">Erro ao carregar colaboradores: ${errorMessage}</td></tr>`;
        showToast("Erro ao carregar colaboradores: " + errorMessage, "error");
      }
    } catch (error) {
      console.error("Erro ao buscar colaboradores (gerenciar_colaboradores.js):", error);
      collaboratorsTableBody.innerHTML = `<tr><td colspan="6" class="px-6 py-4 whitespace-nowrap text-sm text-red-500 text-center">Erro de conexão ao carregar colaboradores.</td></tr>`;
      showToast("Erro de conexão ao carregar colaboradores: " + error.message, "error");
    }
  }

  function abrirModalEdicao(colaborador) {
    if (!editModal || !editForm) {
        console.warn("[DEBUG] gerenciar_colaboradores.js: Modal de edição ou formulário não encontrado.");
        return;
    }
    editForm.reset();
    document.getElementById("edit-colab-id").value = colaborador.id;
    document.getElementById("edit-nome_completo").value = colaborador.nome_completo;
    document.getElementById("edit-email").value = colaborador.email || "";
    document.getElementById("edit-cargo").value = colaborador.cargo || "";

    // O token CSRF no modal já é preenchido pelo PHP na página gerenciar_colaboradores.php
    // <input type="hidden" id="edit-csrf-token" name="csrf_token" value="<?php echo htmlspecialchars($csrfTokenColabManage); ?>">
    // Apenas garantindo que o input existe e tem o valor. Se precisar atualizar dinamicamente (improvável aqui):
    // const csrfTokenPageInput = document.getElementById("csrf-token-colab-manage"); // Este ID deve estar no gerenciar_colaboradores.php
    // if (csrfTokenPageInput) {
    //   document.getElementById("edit-csrf-token").value = csrfTokenPageInput.value;
    // }

    editModal.classList.add("show");
    // editModal.style.display = "flex"; // 'show' já faz isso via CSS
    if (typeof lucide !== "undefined") lucide.createIcons();
  }

  function fecharModalEdicao() {
    if (!editModal) return;
    editModal.classList.remove("show");
    // setTimeout(() => { // Não é mais necessário com display:flex no show
    //   if (!editModal.classList.contains("show")) {
    //     editModal.style.display = "none";
    //   }
    // }, 300);
  }

  if (editForm) {
    editForm.addEventListener("submit", async function (event) {
      event.preventDefault();
      console.log("[DEBUG] gerenciar_colaboradores.js: Formulário de edição submetido.");
      const saveButton = document.getElementById("save-edit-colab-button");
      const originalButtonHtml = saveButton ? saveButton.innerHTML : "";
      if(saveButton) {
        saveButton.disabled = true;
        saveButton.innerHTML = '<i data-lucide="loader-circle" class="lucide-spin w-4 h-4 mr-1.5"></i> Salvando...';
        if (typeof lucide !== "undefined") lucide.createIcons();
      }


      const formData = new FormData(editForm);
      const dataPayload = Object.fromEntries(formData.entries());

      try {
        const response = await fetch("atualizar_colaborador.php", { // Assumindo que está na raiz
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(dataPayload),
        });
        const result = await response.json();

        if (!response.ok) {
          throw new Error(result.message || `Erro HTTP: ${response.status}`);
        }

        if (result.success) {
          showToast(result.message || "Colaborador atualizado!", "success");
          fecharModalEdicao();
          carregarColaboradoresNaTabela(); // Recarrega a tabela
          // Atualiza o token CSRF na página principal se o backend enviar um novo
          if (result.csrf_token) {
            const csrfTokenPageInput = document.getElementById("csrf-token-colab-manage"); // Este ID é do <input hidden> na página principal
            if (csrfTokenPageInput) csrfTokenPageInput.value = result.csrf_token;
            // Atualiza também o token dentro do modal para a próxima submissão, se o modal não for recriado
            const csrfTokenModalInput = document.getElementById("edit-csrf-token");
            if (csrfTokenModalInput) csrfTokenModalInput.value = result.csrf_token;

          }
        } else {
          showToast("Erro ao atualizar: " + (result.message || "Erro desconhecido."), "error");
        }
      } catch (error) {
        console.error("Erro ao salvar edição do colaborador (gerenciar_colaboradores.js):", error);
        showToast("Erro crítico ao salvar: " + error.message, "error");
      } finally {
        if(saveButton){
          saveButton.disabled = false;
          saveButton.innerHTML = originalButtonHtml;
          if (typeof lucide !== "undefined") lucide.createIcons();
        }
      }
    });
  } else {
    console.warn("[DEBUG] gerenciar_colaboradores.js: Formulário edit-collaborator-form não encontrado.");
  }

  async function alternarStatusColaborador(colabId, novoStatusBool) {
    const acaoTexto = novoStatusBool ? "ativar" : "desativar";
    if (!confirm(`Tem certeza que deseja ${acaoTexto} este colaborador?`)) return;

    const csrfTokenPageInput = document.getElementById("csrf-token-colab-manage"); // ID do token na página principal
    const csrfToken = csrfTokenPageInput ? csrfTokenPageInput.value : null;

    if (!csrfToken) {
      showToast("Erro de segurança. Recarregue a página.", "error");
      return;
    }

    const payload = {
      colab_id: colabId,
      novo_status: novoStatusBool ? 1 : 0,
      csrf_token: csrfToken, // Envia o token da página principal
    };

    try {
      const response = await fetch("alternar_status_colaborador.php", { // Assumindo que está na raiz
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      const result = await response.json();

      if (!response.ok) {
        throw new Error(result.message || `Erro HTTP: ${response.status}`);
      }

      if (result.success) {
        showToast(result.message || `Status alterado com sucesso!`, "success");
        carregarColaboradoresNaTabela(); // Recarrega a tabela
        // Atualiza o token CSRF na página principal se o backend enviar um novo
        if (result.csrf_token && csrfTokenPageInput) {
          csrfTokenPageInput.value = result.csrf_token;
        }
      } else {
        showToast("Erro ao alterar status: " + (result.message || "Erro desconhecido."), "error");
      }
    } catch (error) {
      console.error("Erro ao alternar status (gerenciar_colaboradores.js):", error);
      showToast("Erro crítico ao alterar status: " + error.message, "error");
    }
  }

  if (modalCloseButton) modalCloseButton.addEventListener("click", fecharModalEdicao);
  if (cancelEditButton) cancelEditButton.addEventListener("click", fecharModalEdicao);
  if (editModal) {
    editModal.addEventListener("click", function (event) {
      if (event.target === editModal) { // Fecha se clicar no overlay
        fecharModalEdicao();
      }
    });
  }

  // Carregamento inicial da tabela
  if (collaboratorsTableBody) {
    carregarColaboradoresNaTabela();
  } else {
    console.warn("[DEBUG] gerenciar_colaboradores.js: Tabela de colaboradores não encontrada no DOM para carregamento inicial.");
  }
});