<?php
session_start();

// Se já estiver logado, manda direto para o dashboard
if (isset($_SESSION['usuario_id'])) {
    header("Location: dashboard.php");
    exit;
}

$erro = $_GET['erro'] ?? '';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Cantina do Tio Fabinho</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, Helvetica, sans-serif;
        }

        body {
            min-height: 100vh;
            background:
                linear-gradient(
                    135deg,
                    rgba(20, 20, 20, 0.96),
                    rgba(40, 40, 40, 0.96)
                );
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            width: 100%;
            max-width: 1000px;
            min-height: 580px;
            background: #ffffff;
            border-radius: 24px;
            overflow: hidden;
            display: grid;
            grid-template-columns: 1.05fr 1fr;
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.35);
        }

        .lado-esquerdo {
            background: #181818;
            color: white;
            padding: 55px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }

        .lado-esquerdo::before {
            content: "";
            position: absolute;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.04);
            top: -120px;
            right: -100px;
        }

        .lado-esquerdo::after {
            content: "";
            position: absolute;
            width: 220px;
            height: 220px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.03);
            bottom: -100px;
            left: -70px;
        }

        .logo {
            position: relative;
            z-index: 2;
        }

        .logo-icon {
            width: 58px;
            height: 58px;
            background: #ffffff;
            color: #181818;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 29px;
            margin-bottom: 28px;
        }

        .logo h1 {
            font-size: 38px;
            line-height: 1.1;
            margin-bottom: 16px;
            max-width: 350px;
        }

        .logo h1 span {
            font-weight: 300;
        }

        .logo p {
            font-size: 15px;
            color: #bdbdbd;
            max-width: 380px;
            line-height: 1.7;
        }

        .recursos {
            position: relative;
            z-index: 2;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .recurso {
            background: rgba(255, 255, 255, 0.07);
            padding: 10px 14px;
            border-radius: 30px;
            font-size: 13px;
            color: #e1e1e1;
        }

        .lado-direito {
            padding: 65px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .lado-direito h2 {
            font-size: 30px;
            color: #1e1e1e;
            margin-bottom: 8px;
        }

        .subtitulo {
            color: #777;
            font-size: 14px;
            margin-bottom: 35px;
        }

        .campo {
            margin-bottom: 20px;
        }

        .campo label {
            display: block;
            font-size: 13px;
            font-weight: bold;
            color: #444;
            margin-bottom: 8px;
        }

        .campo input {
            width: 100%;
            padding: 15px 16px;
            border-radius: 11px;
            border: 1px solid #dddddd;
            outline: none;
            font-size: 14px;
            transition: 0.2s;
            background: #fafafa;
        }

        .campo input:focus {
            border-color: #1e1e1e;
            background: white;
            box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.05);
        }

        .senha-wrapper {
            position: relative;
        }

        .senha-wrapper input {
            padding-right: 75px;
        }

        .mostrar-senha {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: transparent;
            color: #777;
            font-size: 12px;
            cursor: pointer;
            font-weight: bold;
        }

        .btn-login {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 11px;
            background: #1b1b1b;
            color: white;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.25s;
            margin-top: 5px;
        }

        .btn-login:hover {
            background: #333333;
            transform: translateY(-1px);
        }

        .erro {
            background: #fff0f0;
            color: #b42318;
            border: 1px solid #ffd0d0;
            padding: 12px 14px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 13px;
        }

        .rodape {
            margin-top: 30px;
            text-align: center;
            color: #aaa;
            font-size: 12px;
        }

        @media (max-width: 820px) {
            .login-container {
                grid-template-columns: 1fr;
                max-width: 520px;
            }

            .lado-esquerdo {
                padding: 40px;
                min-height: 320px;
            }

            .recursos {
                margin-top: 40px;
            }

            .lado-direito {
                padding: 40px;
            }
        }

        @media (max-width: 480px) {
            .lado-esquerdo,
            .lado-direito {
                padding: 28px;
            }

            .logo h1 {
                font-size: 30px;
            }
        }
    </style>
</head>

<body>

<div class="login-container">

    <section class="lado-esquerdo">

        <div class="logo">

            <div class="logo-icon">
                ☕
            </div>

            <h1>
                Cantina do<br>
                <span>Tio Fabinho</span>
            </h1>

            <p>
                Gestão simples para cuidar das vendas,
                estoque, caixa e desempenho da cantina
                em um só lugar.
            </p>

        </div>

        <div class="recursos">
            <div class="recurso">📦 Estoque</div>
            <div class="recurso">💰 Vendas</div>
            <div class="recurso">📊 Relatórios</div>
        </div>

    </section>


    <section class="lado-direito">

        <h2>Bem-vindo!</h2>

        <p class="subtitulo">
            Entre com seus dados para acessar o sistema.
        </p>

        <?php if ($erro === 'login'): ?>

            <div class="erro">
                E-mail ou senha incorretos.
            </div>

        <?php elseif ($erro === 'inativo'): ?>

            <div class="erro">
                Este usuário está desativado.
            </div>

        <?php endif; ?>


        <form action="login/validar_login.php" method="POST">

            <div class="campo">

                <label for="email">
                    E-mail
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Digite seu e-mail"
                    required
                    autocomplete="email"
                >

            </div>


            <div class="campo">

                <label for="senha">
                    Senha
                </label>

                <div class="senha-wrapper">

                    <input
                        type="password"
                        id="senha"
                        name="senha"
                        placeholder="Digite sua senha"
                        required
                        autocomplete="current-password"
                    >

                    <button
                        type="button"
                        class="mostrar-senha"
                        onclick="mostrarSenha()"
                    >
                        MOSTRAR
                    </button>

                </div>

            </div>


            <button type="submit" class="btn-login">
                Entrar no sistema
            </button>

        </form>


        <div class="rodape">
            Cantina do Tio Fabinho • Sistema de Gestão
        </div>

    </section>

</div>


<script>

function mostrarSenha() {

    const campoSenha = document.getElementById("senha");
    const botao = document.querySelector(".mostrar-senha");

    if (campoSenha.type === "password") {

        campoSenha.type = "text";
        botao.innerText = "OCULTAR";

    } else {

        campoSenha.type = "password";
        botao.innerText = "MOSTRAR";

    }

}

</script>

</body>
</html>
