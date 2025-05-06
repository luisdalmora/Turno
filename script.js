function calcularResumo() {
  const linhas = document.querySelectorAll("#tabela-maio tbody tr");
  const colaboradores = {};

  linhas.forEach((linha) => {
    const data = linha.querySelector("td:nth-child(1) input")?.value.trim();
    const horaStr = linha.querySelector(".hora")?.value.trim();
    const colaborador = linha.querySelector(".colaborador")?.value.trim();

    if (colaborador && horaStr && colaborador.toLowerCase() !== "feriado") {
      const [h = 0, m = 0, s = 0] = horaStr.split(":").map(Number);
      const horas = h + m / 60 + s / 3600;
      colaboradores[colaborador] = (colaboradores[colaborador] || 0) + horas;
    }
  });

  const corpoResumo = document.querySelector("#resumo-tabela tbody");
  corpoResumo.innerHTML = "";

  Object.entries(colaboradores).forEach(([nome, horas]) => {
    const linha = document.createElement("tr");
    linha.innerHTML = `<td>${nome}</td><td>${horas.toFixed(2)} h</td>`;
    corpoResumo.appendChild(linha);
  });
}

document.addEventListener("input", calcularResumo);
document.addEventListener("DOMContentLoaded", calcularResumo);
