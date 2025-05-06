<!DOCTYPE html>
<html lang="pt-br">

<head>
  <title>Login</title>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="style.css">
</head>

<body>
  <div class="container">
    <div class="container-login">
      <div class="wrap-login">
        <form class="login-form">
          <span class="login-form-title">Faça o login</span>

          <div class="wrap-input margin-top-35 margin-bottom-35">
            <input class="input-form" type="text" name="usuario" autocomplete="off">
            <span class="focus-input-form" data-placeholder="Usuário"></span>
          </div>

          <div class="wrap-input margin-bottom-35">
            <input class="input-form" type="password" name="password">
            <span class="focus-input-form" data-placeholder="Senha"></span>
          </div>

          <div class="container-login-form-btn">
            <a class="login-form-btn" href="home.html">Login</a>
          </div>

          <ul class="login-utils">
            <li class="margin-bottom-8 margin-top-8">
              <span class="text1">Esqueceu sua</span>
              <a href="#" class="text2">senha?</a>
            </li>
            <li>
              <span class="text1">Não tem conta?</span>
              <a href="#" class="text2">Criar</a>
            </li>
          </ul>
        </form>
      </div>
      <img src="images/login.png" width="800" height="340" class="margin-left-50" alt="Login imagem" />
    </div>
  </div>

  <script>
    document.querySelectorAll('.input-form').forEach(input => {
      input.addEventListener("blur", () => {
        input.classList.toggle("has-val", input.value.trim() !== "");
      });
    });
  </script>
</body>

</html>