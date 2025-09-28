<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Teste Login WordPress</title>
</head>
<body>
    <h2>Login de Teste</h2>
    <form id="loginForm">
        <label for="username">Usuário:</label><br>
        <input type="text" id="username" name="username" required><br><br>

        <label for="password">Senha:</label><br>
        <input type="password" id="password" name="password" required><br><br>

        <button type="submit">Testar Login</button>
    </form>

    <p id="resultado"></p>

    <script>
        const form = document.getElementById('loginForm');
        const resultado = document.getElementById('resultado');

        form.addEventListener('submit', async (e) => {
            e.preventDefault(); // evita recarregar a página

            const formData = new FormData(form);

            try {
                const response = await fetch('login_check.php', {
                    method: 'POST',
                    body: formData
                });

                const text = await response.text();
                resultado.textContent = text === 'true' ? '✅ Login válido' : '❌ Usuário ou senha inválidos';
            } catch (err) {
                resultado.textContent = 'Erro ao tentar autenticar.';
                console.error(err);
            }
        });
    </script>
</body>
</html>
