<?php
session_start();
require_once "config.php";

// Proteção de acesso: caso não haja sessão ativa, redireciona para a versão HTML interativa
if (!isset($_SESSION['usuario'])) {
    header("Location: aluno.html");
    exit();
}

if (!$conn) {
    header("Location: aluno.html");
    exit();
}

$usuario = $_SESSION['usuario'];
$msg = "";

// AÇÃO: Solicitar liberação de aula bloqueada
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['solicitar_aula_id'])) {
    $modulo_id = intval($_POST['solicitar_aula_id']);
    $u_safe = $conn->real_escape_string($usuario);

    // Verificar se já não existe solicitação pendente
    $chk = $conn->query("SELECT id FROM solicitacoes_aulas WHERE usuario = '$u_safe' AND modulo_id = $modulo_id AND status = 'pendente'");
    if ($chk->num_rows > 0) {
        $msg = "Você já solicitou a liberação desta aula. Ela está aguardando a aprovação do administrador!";
    } else {
        $conn->query("INSERT INTO solicitacoes_aulas (usuario, modulo_id, status, data_solicitacao) VALUES ('$u_safe', $modulo_id, 'pendente', NOW())");
        $msg = "Solicitação enviada com sucesso! O administrador já pode liberar sua aula no painel.";
    }
}

// Buscar todas as aulas do curso
$res_aulas = $conn->query("SELECT id, titulo, ordem FROM modulos ORDER BY ordem ASC, id ASC");

// Buscar status de progresso do aluno
$u_safe = $conn->real_escape_string($usuario);
$res_prog = $conn->query("SELECT modulo_id, liberado, concluido FROM progresso_aluno WHERE usuario = '$u_safe'");
$progresso = [];
while ($p = $res_prog->fetch_assoc()) {
    $progresso[$p['modulo_id']] = [
        'liberado' => intval($p['liberado']),
        'concluido' => intval($p['concluido'])
    ];
}

// Buscar solicitações pendentes deste aluno
$res_solic = $conn->query("SELECT modulo_id FROM solicitacoes_aulas WHERE usuario = '$u_safe' AND status = 'pendente'");
$pendentes = [];
while ($s = $res_solic->fetch_assoc()) {
    $pendentes[$s['modulo_id']] = true;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Portal do Aluno - IBEM</title>

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
      --surface: #FFFFFF;
      --bg: #F4F6F9;
      --text: #1E293B;
      --muted: #64748B;
      --border: #E2E8F0;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Poppins', sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      padding: 24px 16px;
      display: flex;
      justify-content: center;
    }

    .container {
      width: 100%;
      max-width: 860px;
    }

    /* Topo do Aluno */
    .header-box {
      background: var(--surface);
      border-radius: 12px;
      padding: 20px 24px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 4px 15px rgba(0,0,0,0.05);
      margin-bottom: 20px;
      flex-wrap: wrap;
      gap: 12px;
    }

    .header-info h1 {
      font-size: 1.25rem;
      color: var(--primary);
    }

    .header-info p {
      font-size: 0.85rem;
      color: var(--muted);
    }

    .btn-sair {
      background: #FEE2E2;
      color: #B91C1C;
      padding: 8px 16px;
      border-radius: 6px;
      text-decoration: none;
      font-size: 0.85rem;
      font-weight: 600;
      transition: 0.2s;
    }

    .btn-sair:hover {
      background: #EF4444;
      color: white;
    }

    /* Alerta */
    .alert {
      background: #ECFDF5;
      color: #065F46;
      border: 1px solid #A7F3D0;
      padding: 12px 18px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-size: 0.88rem;
    }

    /* Card de Aula */
    .card-aula {
      background: var(--surface);
      border-radius: 12px;
      border: 1.5px solid var(--border);
      padding: 20px;
      margin-bottom: 14px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 2px 6px rgba(0,0,0,0.03);
      gap: 16px;
      flex-wrap: wrap;
    }

    .card-aula.liberada {
      border-left: 5px solid var(--success);
    }

    .card-aula.bloqueada {
      border-left: 5px solid #CBD5E1;
      opacity: 0.9;
    }

    .aula-titulo {
      font-size: 1rem;
      font-weight: 600;
      color: var(--text);
      margin-bottom: 6px;
    }

    .badge {
      display: inline-block;
      padding: 3px 10px;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 600;
    }

    .badge-verde { background: #DCFCE7; color: #15803D; }
    .badge-azul { background: #DBEAFE; color: #1E40AF; }
    .badge-amarelo { background: #FEF3C7; color: #B45309; }
    .badge-cinza { background: #F1F5F9; color: #64748B; }

    .btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 9px 16px;
      border-radius: 6px;
      font-size: 0.85rem;
      font-weight: 600;
      border: none;
      cursor: pointer;
      text-decoration: none;
      transition: 0.2s;
    }

    .btn-entrar {
      background: var(--primary);
      color: white;
    }

    .btn-entrar:hover {
      background: var(--primary-light);
    }

    .btn-solicitar {
      background: #FEF3C7;
      color: #92400E;
      border: 1px solid #FDE68A;
    }

    .btn-solicitar:hover {
      background: #FDE68A;
    }
  </style>
</head>
<body>

  <div class="container">
    
    <div class="header-box">
      <div style="display:flex; align-items:center; gap:16px;">
        <img src="../assets/img/logo.png" alt="IBEM" style="width:52px; height:52px; object-fit:contain;">
        <div class="header-info">
          <h1>Portal do Aluno - IBEM</h1>
          <p>Bem-vindo(a), <strong><?php echo htmlspecialchars($usuario); ?></strong></p>
        </div>
      </div>
      <div>
        <a href="logout.php" class="btn-sair">🚪 Sair do Sistema</a>
      </div>
    </div>

    <?php if (!empty($msg)): ?>
      <div class="alert">
        ✔ <?php echo htmlspecialchars($msg); ?>
      </div>
    <?php endif; ?>

    <h2 style="font-size:1.15rem; color:var(--primary); margin-bottom:16px;">Minhas Aulas e Módulos</h2>

    <?php if ($res_aulas && $res_aulas->num_rows > 0): ?>
      <?php while ($aula = $res_aulas->fetch_assoc()): ?>
        <?php
        $id_aula = $aula['id'];
        $is_liberado = isset($progresso[$id_aula]) && $progresso[$id_aula]['liberado'] === 1;
        $is_concluido = isset($progresso[$id_aula]) && $progresso[$id_aula]['concluido'] === 1;
        $is_pendente = isset($pendentes[$id_aula]);
        ?>
        <div class="card-aula <?php echo $is_liberado ? 'liberada' : 'bloqueada'; ?>">
          <div>
            <div class="aula-titulo">
              #<?php echo $aula['ordem']; ?> &bull; <?php echo htmlspecialchars($aula['titulo']); ?>
            </div>
            <div>
              <?php if ($is_concluido): ?>
                <span class="badge badge-azul">🎓 Concluída</span>
              <?php elseif ($is_liberado): ?>
                <span class="badge badge-verde">🔓 Liberada para Estudo</span>
              <?php elseif ($is_pendente): ?>
                <span class="badge badge-amarelo">⏳ Liberação Solicitada (Aguardando Admin)</span>
              <?php else: ?>
                <span class="badge badge-cinza">🔒 Bloqueada</span>
              <?php endif; ?>
            </div>
          </div>

          <div>
            <?php if ($is_liberado): ?>
              <a href="aulas.php?id=<?php echo $id_aula; ?>" class="btn btn-entrar">
                ▶ Assistir Aula
              </a>
            <?php elseif ($is_pendente): ?>
              <button type="button" class="btn" style="background:#F1F5F9; color:#94A3B8; cursor:not-allowed;" disabled>
                ⏳ Em Análise
              </button>
            <?php else: ?>
              <form method="POST">
                <input type="hidden" name="solicitar_aula_id" value="<?php echo $id_aula; ?>">
                <button type="submit" class="btn btn-solicitar" title="Solicitar liberação desta aula ao administrador">
                  🔔 Solicitar Liberação
                </button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <p style="color:var(--muted);">Nenhuma aula disponível no momento.</p>
    <?php endif; ?>

  </div>

</body>
</html>
