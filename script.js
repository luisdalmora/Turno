// URL base para as requisições da API (se aplicável, para organizar)
// const API_BASE_URL = ''; // Ex: /api/

/**
 * Função para salvar os dados dos turnos no servidor.
 * Envia os dados para 'salvar_turnos.php' via POST.
 * @param {Array<Object>} dadosTurnos - Array de objetos, cada um representando um turno.
 */
function salvarDadosTurnosNoServidor(dadosTurnos) {
  // Mostra um feedback visual para o usuário (ex: um loader)
  // document.getElementById('loading-indicator').style.display = 'block';

  fetch("salvar_turnos.php", {
    // Caminho para o script PHP que salva os turnos
    method: "POST", // Método HTTP
    headers: {
      "Content-Type": "application/json", // Informa ao servidor que estamos enviando JSON
    },
    body: JSON.stringify(dadosTurnos), // Converte o array de objetos JavaScript em uma string JSON
  })
    .then((response) => {
      // Verifica se a resposta da requisição foi bem-sucedida (status 2xx)
      if (!response.ok) {
        // Se não foi OK, tenta ler o corpo como JSON para uma mensagem de erro mais detalhada
        return response.json().then((errData) => {
          throw new Error(errData.message || `Erro HTTP: ${response.status}`);
        });
      }
      return response.json(); // Converte a resposta JSON do servidor em um objeto JavaScript
    })
    .then((data) => {
      // Processa os dados retornados pelo servidor
      if (data.success) {
        alert("Sucesso: " + data.message); // Exibe mensagem de sucesso
        atualizarTabelaResumoColaboradores(data.data); // Atualiza a tabela de resumo com os dados retornados
        // Opcional: recarregar a tabela de turnos também, se ela não for diretamente editável ou para confirmar os dados do servidor
        // carregarTurnosDoServidor(); // Implementar esta função se necessário
      } else {
        alert("Erro ao salvar: " + data.message); // Exibe mensagem de erro vinda do servidor
      }
    })
    .catch((error) => {
      // Captura erros de rede ou erros lançados no processamento da resposta
      console.error("Erro crítico ao salvar dados dos turnos:", error);
      alert(
        "Erro crítico ao tentar salvar os dados. Verifique o console para mais detalhes."
      );
    })
    .finally(() => {
      // Esconde o feedback visual (ex: loader)
      // document.getElementById('loading-indicator').style.display = 'none';
    });
}

/**
 * Função para coletar os dados dos turnos da tabela HTML.
 * Lê os valores dos inputs na tabela de turnos.
 * @returns {Array<Object>} Um array de objetos, onde cada objeto representa um turno com data, hora e colaborador.
 */
function coletarDadosDaTabelaDeTurnos() {
  const linhasTabelaTurnos = document.querySelectorAll(
    "#shifts-table-may tbody tr"
  ); // Seleciona todas as linhas do corpo da tabela de turnos de maio
  const dadosTurnosParaSalvar = [];

  linhasTabelaTurnos.forEach((linha) => {
    // Para cada linha, coleta os valores dos inputs
    const dataInput = linha.querySelector(".shift-date"); // Input da data do turno
    const horaInput = linha.querySelector(".shift-time"); // Input da hora do turno
    const colaboradorInput = linha.querySelector(".shift-employee"); // Input do nome do colaborador

    // Verifica se todos os inputs foram encontrados antes de tentar ler seus valores
    if (dataInput && horaInput && colaboradorInput) {
      dadosTurnosParaSalvar.push({
        data: dataInput.value.trim(), // Remove espaços em branco
        hora: horaInput.value.trim(),
        colaborador: colaboradorInput.value.trim(),
      });
    } else {
      console.warn(
        "Algum campo não foi encontrado em uma das linhas da tabela de turnos.",
        linha
      );
    }
  });
  return dadosTurnosParaSalvar;
}

/**
 * Função para atualizar a tabela de resumo de horas por colaborador.
 * @param {Array<Object>} turnos - Array de objetos de turnos, geralmente vindo do servidor.
 */
function atualizarTabelaResumoColaboradores(turnos) {
  const corpoTabelaResumo = document.querySelector(
    "#employee-summary-table tbody"
  );
  if (!corpoTabelaResumo) {
    console.error("Elemento tbody da tabela de resumo não encontrado.");
    return;
  }
  corpoTabelaResumo.innerHTML = ""; // Limpa o conteúdo atual da tabela de resumo

  const resumoHoras = {}; // Objeto para armazenar o total de horas por colaborador

  // Calcula o total de horas para cada colaborador
  turnos.forEach((turno) => {
    if (!turno.colaborador || !turno.hora) return; // Pula turnos com dados incompletos

    if (!resumoHoras[turno.colaborador]) {
      resumoHoras[turno.colaborador] = 0; // Inicializa o total de horas se o colaborador ainda não existe no resumo
    }

    // Converte a string de hora (HH:MM:SS ou HH:MM) para minutos
    const partesHora = turno.hora.split(":").map(Number);
    let minutosTurno = 0;
    if (partesHora.length >= 2) {
      // Precisa de pelo menos hora e minuto
      minutosTurno = partesHora[0] * 60 + partesHora[1];
    }
    // Adiciona os minutos ao total do colaborador
    // ESTA LÓGICA ASSUME QUE 'turno.hora' É A DURAÇÃO DO TURNO.
    // SE 'turno.hora' FOR O HORÁRIO DE INÍCIO, ESTA LÓGICA PRECISA SER AJUSTADA
    // PARA CALCULAR A DURAÇÃO (ex: com base em um horário de término ou uma duração padrão).
    // Atualmente, o exemplo de '04:00:00' está sendo interpretado como 4 horas de trabalho.
    resumoHoras[turno.colaborador] += minutosTurno;
  });

  // Preenche a tabela de resumo com os dados calculados
  for (const colaborador in resumoHoras) {
    const totalHorasCalculadas = (resumoHoras[colaborador] / 60).toFixed(2); // Converte minutos de volta para horas (ex: 4.00)
    const novaLinha = corpoTabelaResumo.insertRow(); // Insere uma nova linha na tabela

    const celulaColaborador = novaLinha.insertCell(0); // Insere célula para o nome do colaborador
    celulaColaborador.textContent = colaborador;

    const celulaTotalHoras = novaLinha.insertCell(1); // Insere célula para o total de horas
    celulaTotalHoras.textContent = totalHorasCalculadas + "h"; // Adiciona 'h' para clareza
  }
}

/**
 * Função para carregar os dados iniciais dos turnos do servidor quando a página é carregada.
 */
function carregarTurnosDoServidor() {
  // Mostra um feedback visual para o usuário (ex: um loader)
  // document.getElementById('loading-indicator').style.display = 'block';

  fetch("salvar_turnos.php") // Faz uma requisição GET (por padrão) para obter os dados dos turnos
    .then((response) => {
      if (!response.ok) {
        return response.json().then((errData) => {
          throw new Error(errData.message || `Erro HTTP: ${response.status}`);
        });
      }
      return response.json();
    })
    .then((data) => {
      if (data.success && data.data) {
        // Se os dados dos turnos foram carregados com sucesso
        // TODO: Popular a tabela de turnos (#shifts-table-may) com 'data.data'
        // Exemplo: popularTabelaTurnos(data.data); (esta função precisaria ser criada)

        // Atualiza a tabela de resumo dos colaboradores
        atualizarTabelaResumoColaboradores(data.data);
      } else {
        alert(
          "Aviso: " +
            (data.message ||
              "Não foi possível carregar os dados iniciais dos turnos.")
        );
      }
    })
    .catch((error) => {
      console.error(
        "Erro crítico ao carregar dados iniciais dos turnos:",
        error
      );
      alert("Erro crítico ao carregar os dados iniciais. Verifique o console.");
    })
    .finally(() => {
      // Esconde o feedback visual (ex: loader)
      // document.getElementById('loading-indicator').style.display = 'none';
    });
}

// Event Listeners

// Adiciona um event listener para o botão "Salvar Turnos"
const botaoSalvarTurnos = document.getElementById("save-shifts-button");
if (botaoSalvarTurnos) {
  botaoSalvarTurnos.addEventListener("click", function () {
    const dadosColetados = coletarDadosDaTabelaDeTurnos(); // Coleta os dados da tabela
    if (dadosColetados.length > 0) {
      salvarDadosTurnosNoServidor(dadosColetados); // Envia os dados para o servidor
    } else {
      alert("Nenhum dado de turno para salvar. Verifique a tabela.");
    }
  });
} else {
  console.warn("Botão 'save-shifts-button' não encontrado no DOM.");
}

// Executa quando o DOM (estrutura da página) está completamente carregado
document.addEventListener("DOMContentLoaded", function () {
  // Carrega os dados iniciais dos turnos e atualiza a tabela de resumo
  carregarTurnosDoServidor();

  // A função calcularResumo() original parecia redundante ou incompleta.
  // A lógica de resumo agora é acionada após o carregamento dos dados do servidor
  // ou após salvar novos turnos. Se houver cálculos de resumo em tempo real
  // baseados na edição da tabela de turnos (antes de salvar), essa lógica precisaria
  // ser reavaliada e implementada aqui ou vinculada a eventos de 'input' na tabela.
});

/*
  Nota sobre a função calcularResumo() que existia anteriormente:
  A lógica de cálculo do resumo foi integrada em `atualizarTabelaResumoColaboradores`
  e é chamada quando os dados são carregados do servidor ou após salvar.
  Se a intenção era ter um cálculo dinâmico ANTES de salvar (conforme o usuário digita na tabela de turnos),
  seria necessário adicionar event listeners aos inputs da tabela de turnos
  que chamariam uma função para recalcular e exibir o resumo localmente.

  Exemplo de como seria um cálculo local (não implementado completamente aqui):
  function calcularResumoLocalmente() {
    const dadosLocais = coletarDadosDaTabelaDeTurnos();
    // Chamar uma versão de atualizarTabelaResumoColaboradores que usa 'dadosLocais'
    // Isso forneceria um feedback imediato, mas o resumo final viria do servidor após salvar.
  }
  // E adicionar listeners:
  // document.querySelectorAll('#shifts-table-may input').forEach(input => {
  //   input.addEventListener('input', calcularResumoLocalmente);
  // });
*/
