// gerenciar_colaboradores.js
document.addEventListener("DOMContentLoaded", function () {
  const collaboratorsTableBody = document.querySelector(
    "#collaborators-table tbody"
  );
  const editModal = document.getElementById("edit-collaborator-modal");
  const editForm = document.getElementById("edit-collaborator-form");
  const modalCloseButton = document.getElementById("modal-close-btn");
  const cancelEditButton = document.getElementById("cancel-edit-colab-button");

  const notify =
    typeof showToast === "function"
      ? showToast
      : (message, type) => alert(`${type}: ${message}`);

  async function carregarColaboradoresNaTabela() {
    if (!collaboratorsTableBody) return;
    collaboratorsTableBody.innerHTML = `<tr><td colspan="6" style="text-align:center;">Carregando... <i data-lucide="loader-circle" class="lucide-spin"></i></td></tr>`;
    if (typeof lucide !== "undefined") lucide.createIcons();

    try {
      const response = await fetch(`listar_colaboradores.php`);
      const data = await response.json();
      collaboratorsTableBody.innerHTML = "";

      if (data.success && data.colaboradores) {
        if (data.colaboradores.length === 0) {
          collaboratorsTableBody.innerHTML = `<tr><td colspan="6" style="text-align:center;">Nenhum colaborador cadastrado. <a href="cadastrar_colaborador.php">Adicionar novo</a>.</td></tr>`;
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

          const actionsCell = row.insertCell();
          actionsCell.className = "actions-cell";

          const editButton = document.createElement("button");
          // Usar data-lucide para o ícone, e a classe `action-button info btn-sm`
          editButton.innerHTML = '<i data-lucide="edit-3"></i> Editar';
          editButton.className = "action-button info btn-sm";
          editButton.onclick = () => abrirModalEdicao(colab);
          actionsCell.appendChild(editButton);

          const toggleStatusButton = document.createElement("button");
          toggleStatusButton.innerHTML = colab.ativo
            ? '<i data-lucide="toggle-left"></i> Desativar'
            : '<i data-lucide="toggle-right"></i> Ativar';
          toggleStatusButton.className = colab.ativo
            ? "action-button warning btn-sm"
            : "action-button success btn-sm";
          toggleStatusButton.onclick = () =>
            alternarStatusColaborador(colab.id, !colab.ativo);
          actionsCell.appendChild(toggleStatusButton);
        });
        if (typeof lucide !== "undefined") lucide.createIcons(); // Renderiza os ícones adicionados
      } else {
        const errorMessage = data.message || "Erro desconhecido";
        collaboratorsTableBody.innerHTML = `<tr><td colspan="6" style="text-align:center;">Erro ao carregar colaboradores: ${errorMessage}</td></tr>`;
        notify("Erro ao carregar colaboradores: " + errorMessage, "error");
      }
    } catch (error) {
      console.error("Erro ao buscar colaboradores:", error);
      collaboratorsTableBody.innerHTML = `<tr><td colspan="6" style="text-align:center;">Erro de conexão ao carregar colaboradores.</td></tr>`;
      notify(
        "Erro de conexão ao carregar colaboradores: " + error.message,
        "error"
      );
    }
  }

  function abrirModalEdicao(colaborador) {
    if (!editModal || !editForm) return;
    editForm.reset();
    document.getElementById("edit-colab-id").value = colaborador.id;
    document.getElementById("edit-nome_completo").value =
      colaborador.nome_completo;
    document.getElementById("edit-email").value = colaborador.email || "";
    document.getElementById("edit-cargo").value = colaborador.cargo || "";

    const csrfTokenPageInput = document.getElementById(
      "csrf-token-colab-manage"
    ); // Este ID deve estar no gerenciar_colaboradores.php
    if (csrfTokenPageInput) {
      document.getElementById("edit-csrf-token").value =
        csrfTokenPageInput.value;
    }

    editModal.classList.add("show");
    editModal.style.display = "flex";
    if (typeof lucide !== "undefined") lucide.createIcons(); // Recria ícones dentro do modal se houver
  }

  function fecharModalEdicao() {
    if (!editModal) return;
    editModal.classList.remove("show");
    setTimeout(() => {
      if (!editModal.classList.contains("show")) {
        editModal.style.display = "none";
      }
    }, 300);
  }

  if (editForm) {
    editForm.addEventListener("submit", async function (event) {
      event.preventDefault();
      const saveButton = document.getElementById("save-edit-colab-button");
      const originalButtonHtml = saveButton.innerHTML;
      saveButton.disabled = true;
      saveButton.innerHTML =
        '<i data-lucide="loader-circle" class="lucide-spin"></i> Salvando...';
      if (typeof lucide !== "undefined") lucide.createIcons();

      const formData = new FormData(editForm);
      const dataPayload = Object.fromEntries(formData.entries());

      try {
        const response = await fetch("atualizar_colaborador.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(dataPayload),
        });
        const result = await response.json();

        if (!response.ok) {
          throw new Error(result.message || `Erro HTTP: ${response.status}`);
        }

        if (result.success) {
          notify(result.message || "Colaborador atualizado!", "success");
          fecharModalEdicao();
          carregarColaboradoresNaTabela();
          if (result.csrf_token) {
            const csrfTokenPageInput = document.getElementById(
              "csrf-token-colab-manage"
            );
            if (csrfTokenPageInput)
              csrfTokenPageInput.value = result.csrf_token;
          }
        } else {
          notify(
            "Erro ao atualizar: " + (result.message || "Erro desconhecido."),
            "error"
          );
        }
      } catch (error) {
        console.error("Erro ao salvar edição do colaborador:", error);
        notify("Erro crítico ao salvar: " + error.message, "error");
      } finally {
        saveButton.disabled = false;
        saveButton.innerHTML = originalButtonHtml; // Restaura o HTML original
        if (typeof lucide !== "undefined") lucide.createIcons(); // Recria ícones no botão
      }
    });
  }

  async function alternarStatusColaborador(colabId, novoStatusBool) {
    const acaoTexto = novoStatusBool ? "ativar" : "desativar";
    if (!confirm(`Tem certeza que deseja ${acaoTexto} este colaborador?`))
      return;

    const csrfTokenPageInput = document.getElementById(
      "csrf-token-colab-manage"
    );
    const csrfToken = csrfTokenPageInput ? csrfTokenPageInput.value : null;
    if (!csrfToken) {
      notify("Erro de segurança. Recarregue a página.", "error");
      return;
    }

    const payload = {
      colab_id: colabId,
      novo_status: novoStatusBool ? 1 : 0,
      csrf_token: csrfToken,
    };

    try {
      const response = await fetch("alternar_status_colaborador.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      const result = await response.json();

      if (!response.ok) {
        throw new Error(result.message || `Erro HTTP: ${response.status}`);
      }

      if (result.success) {
        notify(result.message || `Status alterado com sucesso!`, "success");
        carregarColaboradoresNaTabela();
        if (result.csrf_token && csrfTokenPageInput) {
          csrfTokenPageInput.value = result.csrf_token;
        }
      } else {
        notify(
          "Erro ao alterar status: " + (result.message || "Erro desconhecido."),
          "error"
        );
      }
    } catch (error) {
      console.error("Erro ao alternar status:", error);
      notify("Erro crítico ao alterar status: " + error.message, "error");
    }
  }

  if (modalCloseButton)
    modalCloseButton.addEventListener("click", fecharModalEdicao);
  if (cancelEditButton)
    cancelEditButton.addEventListener("click", fecharModalEdicao);
  if (editModal) {
    editModal.addEventListener("click", function (event) {
      if (event.target === editModal) {
        fecharModalEdicao();
      }
    });
  }

  if (collaboratorsTableBody) {
    carregarColaboradoresNaTabela();
  }
});
