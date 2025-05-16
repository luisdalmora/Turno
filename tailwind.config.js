// tailwind.config.js
module.exports = {
  content: [
    "./*.{php,html,js}", // Inclui home.php se estiver na raiz
    "./src/**/*.{html,js,php}", // Se você tiver arquivos em /src
    // Adicione outros caminhos se necessário
  ],
  theme: {
    extend: {
      fontFamily: {
        poppins: ["Poppins", "sans-serif"], // Garanta que Poppins está aqui
      },
      // Adicione cores do seu tema antigo se quiser usá-las com Tailwind
      // colors: {
      //   'primary-color': '#407bff',
      //   'sidebar-background-start': '#1e3a8a',
      //   // ... e assim por diante
      // }
    },
  },
  plugins: [
    require("@tailwindcss/forms"), // Muito útil para estilizar inputs
  ],
};
