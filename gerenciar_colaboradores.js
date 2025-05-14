// gerenciar_colaboradores.js
document.addEventListener("DOMContentLoaded", function () {
  const collaboratorsTableBody = document.querySelector(
    "#collaborators-table tbody"
  );
  const editModal = document.getElementById("edit-collaborator-modal");
  const editForm = document.getElementById("edit-collaborator-form");
  const modalCloseButton = document.getElementById("modal-close-btn");
  const cancelEditButton = document.getElementById("cancel-edit-colab-button");

  // Use a função global showToast de script.js, ou um fallback
  const notify =
    typeof showToast === "function"
      ? showToast
      : (message, type) => alert(`${type}: ${message}`);

  // Função para buscar e exibir colaboradores
  async function carregarColaboradoresNaTabela() {
    if (!collaboratorsTableBody) return;
    collaboratorsTableBody.innerHTML = `<tr><td colspan="6" style="text-align:center;">Carregando... <i class="fas fa-spinner fa-spin"></i></td></tr>`;

    // O token CSRF da página para o GET é opcional, mas pode ser adicionado se o backend exigir.
    // const csrfTokenPage = document.getElementById('csrf-token-colab-manage') ? document.getElementById('csrf-token-colab-manage').value : '';

    try {
      // const response = await fetch(`listar_colaboradores.php?csrf_token=${csrfTokenPage}`); // Exemplo se enviar token no GET
      const response = await fetch(`listar_colaboradores.php`);
      const data = await response.json();

      collaboratorsTableBody.innerHTML = ""; // Limpa o "Carregando..."

      if (data.success && data.colaboradores) {
        if (data.colaboradores.length === 0) {
          collaboratorsTableBody.innerHTML = `<tr><td colspan="6" style="text-align:center;">Nenhum colaborador cadastrado. <a href="cadastrar_colaborador.html">Adicionar novo</a>.</td></tr>`;
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
          editButton.innerHTML = '<i class="fas fa-edit"></i> Editar';
          editButton.className = "action-button info btn-sm"; // Usar classes de botão existentes
          editButton.onclick = () => abrirModalEdicao(colab);
          actionsCell.appendChild(editButton);

          const toggleStatusButton = document.createElement("button");
          toggleStatusButton.innerHTML = colab.ativo
            ? '<i class="fas fa-toggle-off"></i> Desativar'
            : '<i class="fas fa-toggle-on"></i> Ativar';
          toggleStatusButton.className = colab.ativo
            ? "action-button warning btn-sm"
            : "action-button success btn-sm";
          toggleStatusButton.onclick = () =>
            alternarStatusColaborador(colab.id, !colab.ativo);
          actionsCell.appendChild(toggleStatusButton);
        });
      } else {
        collaboratorsTableBody.innerHTML = `<tr><td colspan="6" style="text-align:center;">Erro ao carregar colaboradores: ${
          data.message || "Erro desconhecido"
        }</td></tr>`;
        notify(
          "Erro ao carregar colaboradores: " +
            (data.message || "Erro desconhecido"),
          "error"
        );
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

  // Função para abrir o modal de edição
  function abrirModalEdicao(colaborador) {
    if (!editModal || !editForm) return;

    editForm.reset(); // Limpa o formulário
    document.getElementById("edit-colab-id").value = colaborador.id;
    document.getElementById("edit-nome_completo").value =
      colaborador.nome_completo;
    document.getElementById("edit-email").value = colaborador.email || "";
    document.getElementById("edit-cargo").value = colaborador.cargo || "";

    // Atualiza o token CSRF no formulário do modal, caso ele tenha sido regenerado
    const csrfTokenPageInput = document.getElementById(
      "csrf-token-colab-manage"
    );
    if (csrfTokenPageInput) {
      document.getElementById("edit-csrf-token").value =
        csrfTokenPageInput.value;
    }

    editModal.classList.add("show");
    editModal.style.display = "flex"; // Garante visibilidade se 'show' só controla opacidade/transform
  }

  // Função para fechar o modal
  function fecharModalEdicao() {
    if (!editModal) return;
    editModal.classList.remove("show");
    // Adiciona um pequeno delay para a animação de saída antes de esconder
    setTimeout(() => {
      if (!editModal.classList.contains("show")) {
        // Verifica se ainda não foi reaberto
        editModal.style.display = "none";
      }
    }, 300); // Mesmo tempo da transição CSS
  }

  // Event listener para salvar edição do colaborador
  if (editForm) {
    editForm.addEventListener("submit", async function (event) {
      event.preventDefault();
      const saveButton = document.getElementById("save-edit-colab-button");
      const originalButtonHtml = saveButton.innerHTML;
      saveButton.disabled = true;
      saveButton.innerHTML =
        '<i class="fas fa-spinner fa-spin"></i> Salvando...';

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
          carregarColaboradoresNaTabela(); // Recarrega a lista
          if (result.csrf_token) {
            // Atualiza o token CSRF da página principal
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
        saveButton.innerHTML = originalButtonHtml;
      }
    });
  }

  // Função para alternar status (Ativar/Desativar)
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
        carregarColaboradoresNaTabela(); // Recarrega a lista para refletir a mudança
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

  // Event listeners para fechar o modal
  if (modalCloseButton)
    modalCloseButton.addEventListener("click", fecharModalEdicao);
  if (cancelEditButton)
    cancelEditButton.addEventListener("click", fecharModalEdicao);
  if (editModal) {
    // Fechar se clicar fora do conteúdo do modal
    editModal.addEventListener("click", function (event) {
      if (event.target === editModal) {
        // Só fecha se o clique foi no overlay diretamente
        fecharModalEdicao();
      }
    });
  }

  // Carregar colaboradores ao iniciar a página
  if (collaboratorsTableBody) {
    // Só carrega se a tabela estiver na página
    carregarColaboradoresNaTabela();
  }
});
