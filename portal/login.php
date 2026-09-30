<?php
session_start();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    // Autenticação direta das contas padrão de teste
    if (($email === 'admin@ibem.com.br' || $email === 'admin') && $senha === 'admin123') {
        $_SESSION['usuario'] = 'Administrador IBEM';
        $_SESSION['tipo'] = 'admin';
        $_SESSION['usuario_id'] = 1;
        header("Location: admin.html");
        exit();
    }

    if (($email === 'aluno@ibem.com.br' || $email === 'aluno') && $senha === 'aluno123') {
        $_SESSION['usuario'] = 'Aluno Teste IBEM';
        $_SESSION['tipo'] = 'aluno';
        $_SESSION['usuario_id'] = 2;
        header("Location: aluno.html");
        exit();
    }

    @include_once "config.php";
    if (isset($conn) && $conn && !$conn->connect_error) {
        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado && $resultado->num_rows === 1) {
            $usuario = $resultado->fetch_assoc();

            if (password_verify($senha, $usuario['senha'])) {
                $_SESSION['usuario'] = $usuario['nome'];
                $_SESSION['tipo'] = $usuario['tipo'];
                $_SESSION['usuario_id'] = $usuario['id'];

                if ($usuario['tipo'] === 'admin') {
                    header("Location: admin.php");
                } else {
                    header("Location: aluno.php");
                }
                exit();
            }
        }
    }

    // Login inválido: redireciona com mensagem de erro para o modal do index.php
    header("Location: ../index.php?erro=1");
    exit();
}

// Acesso direto sem POST: redireciona para a página principal
header("Location: ../index.php");
exit();
