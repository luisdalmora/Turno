function calcularResumo() {
  const tabelaMes = document.querySelector("#tabela-maio tbody"); // Ajuste o seletor se houver mais tabelas visíveis
  const linhas = tabelaMes ? tabelaMes.querySelectorAll("tr") : [];
  const colaboradores = {};

  linhas.forEach((linha) => {
    const dataInput = linha.querySelector("td:nth-child(1) input");
    const horaInput = linha.querySelector(".hora");
    const colaboradorInput = linha.querySelector(".colaborador");

    if (dataInput && horaInput && colaboradorInput) {
      const data = dataInput.value.trim();
      const horaStr = horaInput.value.trim();
      const colaborador = colaboradorInput.value.trim();

      if (colaborador && horaStr && colaborador.toLowerCase() !== "feriado") {
        const partesHora = horaStr.split(":").map(Number);
        const horas =
          partesHora[0] +
          (partesHora[1] || 0) / 60 +
          (partesHora[2] || 0) / 3600;

        if (colaboradores[colaborador]) {
          colaboradores[colaborador] += horas;
        } else {
          colaboradores[colaborador] = horas;
        }
      }
    }
  });

  const corpoResumo = document.querySelector("#resumo-tabela tbody");
  corpoResumo.innerHTML = "";

  for (const [nome, totalHoras] of Object.entries(colaboradores)) {
    const linha = document.createElement("tr");
    linha.innerHTML = `
          <td>${nome}</td>
          <td>${totalHoras.toFixed(2)} h</td>
      `;
    corpoResumo.appendChild(linha);
  }
}

document.addEventListener("input", () => {
  calcularResumo();
});

window.addEventListener("DOMContentLoaded", () => {
  calcularResumo();
});
