<?php
session_start();
require_once "config.php";

if (!isset($_SESSION['usuario'])) {
    header("Location: aulas.html" . (isset($_GET['id']) ? "?id=" . intval($_GET['id']) : ""));
    exit();
}

if (!$conn) {
    header("Location: aulas.html" . (isset($_GET['id']) ? "?id=" . intval($_GET['id']) : ""));
    exit();
}

$usuario = $_SESSION['usuario'];
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    header("Location: aluno.html");
    exit();
}

// AÇÃO: Marcar aula como concluída
$concluiu_agora = false;
if (isset($_GET['concluir']) && $_GET['concluir'] == '1') {
    $u_safe = $conn->real_escape_string($usuario);
    $chk = $conn->query("SELECT id FROM progresso_aluno WHERE usuario = '$u_safe' AND modulo_id = $id");
    if ($chk->num_rows > 0) {
        $conn->query("UPDATE progresso_aluno SET concluido = 1 WHERE usuario = '$u_safe' AND modulo_id = $id");
    } else {
        $conn->query("INSERT INTO progresso_aluno (usuario, modulo_id, liberado, concluido) VALUES ('$u_safe', $id, 1, 1)");
    }
    $concluiu_agora = true;
}

// Buscar dados da aula
$stmt = $conn->prepare("SELECT * FROM modulos WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$aula = $res->fetch_assoc();

if (!$aula) {
    header("Location: aluno.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($aula['titulo']); ?> - IBEM</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="icon" type="image/png" href="../assets/img/logo.png">

  <style>
    :root {
      --primary: #0A3D62;
      --primary-light: #145DA0;
      --accent: #0284C7;
      --success: #10B981;
      --bg: #F4F6F9;
      --surface: #FFFFFF;
      --text: #1E293B;
      --muted: #64748B;
      --border: #E2E8F0;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Poppins', sans-serif;
      background: var(--bg);
      color: var(--text);
      line-height: 1.7;
      padding: 30px 16px;
      display: flex;
      justify-content: center;
    }

    .container {
      width: 100%;
      max-width: 860px;
      background: var(--surface);
      border-radius: 14px;
      padding: 32px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.06);
    }

    .top-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 24px;
      padding-bottom: 16px;
      border-bottom: 1px solid var(--border);
      flex-wrap: wrap;
      gap: 12px;
    }

    .btn-voltar {
      color: var(--primary);
      text-decoration: none;
      font-weight: 600;
      font-size: 0.9rem;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .btn-voltar:hover {
      color: var(--accent);
    }

    h1 {
      font-size: 1.5rem;
      color: var(--primary);
      margin-bottom: 18px;
    }

    .btn-audio {
      background: #E0F2FE;
      color: #0369A1;
      border: 1px solid #BAE6FD;
      padding: 9px 18px;
      border-radius: 6px;
      font-weight: 600;
      font-size: 0.88rem;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: 0.2s;
    }

    .btn-audio:hover {
      background: #BAE6FD;
    }

    .conteudo-texto {
      margin: 24px 0;
      padding: 24px;
      background: #F8FAFC;
      border-radius: 10px;
      border: 1px solid var(--border);
      font-size: 1rem;
      white-space: pre-line;
    }

    .rodape-acoes {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 30px;
      padding-top: 20px;
      border-top: 1px solid var(--border);
      flex-wrap: wrap;
      gap: 16px;
    }

    .btn-concluir {
      background: var(--success);
      color: white;
      border: none;
      padding: 12px 24px;
      border-radius: 8px;
      font-size: 0.92rem;
      font-weight: 600;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: 0.2s;
    }

    .btn-concluir:hover {
      background: #059669;
    }

    .alert-sucesso {
      background: #DCFCE7;
      color: #15803D;
      border: 1px solid #86EFAC;
      padding: 14px 20px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-weight: 500;
    }
  </style>
</head>
<body>

  <div class="container">

    <div class="top-bar">
      <div style="display:flex; align-items:center; gap:12px;">
        <img src="../assets/img/logo.png" alt="IBEM" style="width:36px; height:36px; object-fit:contain;">
        <a href="aluno.php" class="btn-voltar">← Voltar às Aulas</a>
      </div>
      <button type="button" class="btn-audio" onclick="narrarTexto()">
        🔊 Ouvir Conteúdo da Aula
      </button>
    </div>

    <?php if ($concluiu_agora): ?>
      <div class="alert-sucesso">
        🎉 Parabéns, <?php echo htmlspecialchars($usuario); ?>! Você concluiu esta aula com sucesso.
      </div>
    <?php endif; ?>

    <h1>#<?php echo $aula['ordem']; ?> &bull; <?php echo htmlspecialchars($aula['titulo']); ?></h1>

    <div class="conteudo-texto" id="textoAula">
      <?php echo nl2br(htmlspecialchars($aula['texto'] ?? 'Nenhum texto didático registrado para esta aula.')); ?>
    </div>

    <div class="rodape-acoes">
      <a href="aluno.php" class="btn-voltar">← Voltar à lista de aulas</a>

      <a href="aulas.php?id=<?php echo $id; ?>&concluir=1" class="btn-concluir">
        ✔ Marcar Aula como Concluída
      </a>
    </div>

  </div>

  <script>
    function narrarTexto() {
      const texto = document.getElementById("textoAula").innerText;
      if (!texto.trim()) return;

      if ('speechSynthesis' in window) {
        window.speechSynthesis.cancel();
        const fala = new SpeechSynthesisUtterance(texto);
        fala.lang = "pt-BR";
        fala.rate = 1.0;
        window.speechSynthesis.speak(fala);
      } else {
        alert("Seu navegador não possui suporte para síntese de voz.");
      }
    }
  </script>

</body>
</html>
