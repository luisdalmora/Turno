// src/js/modules/utils.js
console.log("[DEBUG] utils.js: Módulo carregado.");

export const nomesMeses = {
  1: "Janeiro",
  2: "Fevereiro",
  3: "Março",
  4: "Abril",
  5: "Maio",
  6: "Junho",
  7: "Julho",
  8: "Agosto",
  9: "Setembro",
  10: "Outubro",
  11: "Novembro",
  12: "Dezembro",
};

export const tailwindInputClasses =
  "block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 bg-white text-gray-700 placeholder-gray-400";
export const tailwindSelectClasses =
  "block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 bg-white text-gray-700";
export const tailwindCheckboxClasses =
  "h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500";

let activeToastTimeout = null;
export function showToast(message, type = "info", duration = 3500) {
  const existingToast = document.getElementById("toast-notification");
  if (existingToast) {
    existingToast.remove();
    if (activeToastTimeout) clearTimeout(activeToastTimeout);
  }
  const toast = document.createElement("div");
  toast.id = "toast-notification";
  let bgColor = "bg-blue-500"; // Cor padrão para 'info'
  if (type === "success") bgColor = "bg-green-500";
  else if (type === "error") bgColor = "bg-red-500";
  else if (type === "warning") bgColor = "bg-yellow-500 text-gray-800";

  toast.className = `fixed bottom-5 left-1/2 transform -translate-x-1/2 px-6 py-3 rounded-lg shadow-lg text-white text-sm font-medium z-[1060] transition-all duration-300 ease-out opacity-0 translate-y-10 ${bgColor}`;
  toast.textContent = message;
  document.body.appendChild(toast);

  requestAnimationFrame(() => {
    toast.classList.remove("opacity-0", "translate-y-10");
    toast.classList.add("opacity-100", "translate-y-0");
  });

  activeToastTimeout = setTimeout(() => {
    toast.classList.remove("opacity-100", "translate-y-0");
    toast.classList.add("opacity-0", "translate-y-10");
    toast.addEventListener("transitionend", () => toast.remove(), {
      once: true,
    });
  }, duration);
}

export let todosOsColaboradores = []; // Será populado por buscarEArmazenarColaboradores

export async function buscarEArmazenarColaboradores() {
  console.log("[DEBUG] buscarEArmazenarColaboradores (utils.js) chamado.");
  if (
    todosOsColaboradores.length > 0 &&
    todosOsColaboradores[0] &&
    todosOsColaboradores[0].hasOwnProperty("id")
  ) {
    console.log("[DEBUG] Usando colaboradores já armazenados (utils.js).");
    return todosOsColaboradores;
  }
  try {
    console.log("[DEBUG] Buscando colaboradores do servidor (utils.js)...");
    const response = await fetch("obter_colaboradores.php");
    console.log(
      "[DEBUG] Resposta de obter_colaboradores.php status (utils.js):",
      response.status
    );
    if (!response.ok) {
      let errorMsg = `Falha ao buscar colaboradores: HTTP ${response.status}`;
      try {
        const errData = await response.json();
        errorMsg = errData.message || errorMsg;
      } catch (e) {
        const errText = await response.text().catch(() => "");
        errorMsg = errText.substring(0, 150) || errorMsg;
      }
      throw new Error(errorMsg);
    }
    const data = await response.json();
    console.log("[DEBUG] Dados de colaboradores recebidos (utils.js):", data);
    if (data.success && data.colaboradores) {
      todosOsColaboradores = data.colaboradores; // Armazena na variável exportada
      return todosOsColaboradores;
    } else {
      showToast(
        data.message || "Falha ao carregar lista de colaboradores do backend.",
        "error"
      );
      todosOsColaboradores = [];
      return [];
    }
  } catch (error) {
    console.error(
      "[DEBUG] Erro na requisição fetch de colaboradores (utils.js):",
      error
    );
    showToast(
      `Erro crítico ao carregar colaboradores: ${error.message}`,
      "error"
    );
    todosOsColaboradores = [];
    return [];
  }
}

export function popularSelectColaborador(
  selectElement,
  valorSelecionado = null,
  colaboradoresArray = null
) {
  const colaboradores = colaboradoresArray || todosOsColaboradores; // Usa o array passado ou o global
  selectElement.innerHTML =
    '<option value="" class="text-gray-500">Selecione...</option>';
  if (!Array.isArray(colaboradores)) {
    console.error(
      "[DEBUG] Erro: 'colaboradores' não é um array em popularSelectColaborador (utils.js)."
    );
    return;
  }
  colaboradores.forEach((colab) => {
    const option = document.createElement("option");
    option.value = colab.nome_completo; // Assumindo que o valor é o nome completo
    option.textContent = colab.nome_completo;
    if (valorSelecionado && colab.nome_completo === valorSelecionado)
      option.selected = true;
    selectElement.appendChild(option);
  });
}

export function calcularDuracaoDecimal(horaInicioStr, horaFimStr) {
  if (!horaInicioStr || !horaFimStr) return 0;
  const [h1Str, m1Str] = horaInicioStr.split(":");
  const [h2Str, m2Str] = horaFimStr.split(":");

  const h1 = parseInt(h1Str, 10);
  const m1 = parseInt(m1Str, 10);
  const h2 = parseInt(h2Str, 10);
  const m2 = parseInt(m2Str, 10);

  if (isNaN(h1) || isNaN(m1) || isNaN(h2) || isNaN(m2)) return 0;

  let inicioEmMinutos = h1 * 60 + m1;
  let fimEmMinutos = h2 * 60 + m2;

  if (fimEmMinutos < inicioEmMinutos) {
    // Passou da meia-noite
    fimEmMinutos += 24 * 60;
  }

  const duracaoEmMinutos = fimEmMinutos - inicioEmMinutos;
  return duracaoEmMinutos > 0 ? duracaoEmMinutos / 60.0 : 0;
}
