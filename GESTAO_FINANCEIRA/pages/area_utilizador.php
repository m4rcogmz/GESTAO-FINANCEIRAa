<?php
// pages/area_utilizador.php
// Permite ao utilizador atualizar nome, email, palavra-passe (opcional)
// e carregar uma foto de perfil que será usada na navegação.

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$pdo = getPDO();
$utilizadorId = $_SESSION['utilizador_id'];

// Obter dados atuais do utilizador
$stmt = $pdo->prepare('SELECT nome, email, foto_perfil, tema FROM utilizadores WHERE id = :id');
$stmt->execute(['id' => $utilizadorId]);
$utilizador = $stmt->fetch();

if (!$utilizador) {
    die('Utilizador não encontrado.');
}

$mensagemSucesso = '';
$mensagemErro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $tema = $_POST['tema'] ?? 'dark';
    $temasPermitidos = ['dark', 'light', 'purple', 'red', 'gray'];
    if (!in_array($tema, $temasPermitidos, true)) {
        $tema = 'dark';
    }

    if ($nome === '' || $email === '') {
        $mensagemErro = 'Nome e email são obrigatórios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensagemErro = 'O email não é válido.';
    } else {
        // Verificar duplicação de email (outros utilizadores)
        $stmt = $pdo->prepare('SELECT id FROM utilizadores WHERE email = :email AND id <> :id');
        $stmt->execute(['email' => $email, 'id' => $utilizadorId]);
        if ($stmt->fetch()) {
            $mensagemErro = 'Já existe um utilizador com esse email.';
        }
    }

    // Se não há erros até aqui, processar foto e update
    if (!$mensagemErro) {
        $fotoPerfil = $utilizador['foto_perfil']; // manter a existente por defeito

        // Processar upload de foto se enviada
        if (!empty($_FILES['foto_perfil']['name'])) {
            $permitidos = ['image/jpeg' => '.jpg', 'image/png' => '.png', 'image/svg+xml' => '.svg'];
            $tipo = mime_content_type($_FILES['foto_perfil']['tmp_name']);
            if (!isset($permitidos[$tipo])) {
                $mensagemErro = 'Formato de imagem não suportado. Usa JPG, PNG ou SVG.';
            } else {
                $ext = $permitidos[$tipo];
                $novoNome = 'user_' . $utilizadorId . '_' . time() . $ext;
                $destinoDir = __DIR__ . '/../assets/img/profiles/';
                if (!is_dir($destinoDir)) {
                    mkdir($destinoDir, 0755, true);
                }
                $destino = $destinoDir . $novoNome;

                if (move_uploaded_file($_FILES['foto_perfil']['tmp_name'], $destino)) {
                    // Apagar foto antiga se existir e não for a default
                    if (!empty($fotoPerfil) && $fotoPerfil !== 'default.svg') {
                        $antiga = $destinoDir . $fotoPerfil;
                        if (is_file($antiga)) {
                            @unlink($antiga);
                        }
                    }
                    $fotoPerfil = $novoNome;
                } else {
                    $mensagemErro = 'Não foi possível guardar a imagem. Tenta novamente.';
                }
            }
        }

        if (!$mensagemErro) {
            // Construir query de update
            $campos = 'nome = :nome, email = :email, foto_perfil = :foto, tema = :tema';
            $params = [
                'nome' => $nome,
                'email' => $email,
                'foto' => $fotoPerfil,
                'tema' => $tema,
                'id' => $utilizadorId,
            ];

            if ($password !== '') {
                $campos .= ', password = :password';
                $params['password'] = password_hash($password, PASSWORD_DEFAULT);
            }

            $sql = 'UPDATE utilizadores SET ' . $campos . ' WHERE id = :id';
            $stmt = $pdo->prepare($sql);
            $ok = $stmt->execute($params);

            if ($ok) {
                $mensagemSucesso = 'Dados atualizados com sucesso.';
                // Atualizar sessão para refletir novas info
                $_SESSION['utilizador_nome'] = $nome;
                $_SESSION['utilizador_email'] = $email;
                $_SESSION['utilizador_foto'] = $fotoPerfil;
                $_SESSION['utilizador_tema'] = $tema;
                // Atualizar dados locais para refletir na página
                $utilizador['nome'] = $nome;
                $utilizador['email'] = $email;
                $utilizador['foto_perfil'] = $fotoPerfil;
                $utilizador['tema'] = $tema;
            } else {
                $mensagemErro = 'Não foi possível atualizar os dados.';
            }
        }
    }
}

$avatarPath = $utilizador['foto_perfil']
    ? '../assets/img/profiles/' . $utilizador['foto_perfil']
    : '../assets/img/profiles/default.svg';

$temaAtualBruto = $utilizador['tema'] ?? 'dark';
$mapTemasAntigosSlug = [
    'escuro'    => 'dark',
    'claro'     => 'light',
    'neon-azul' => 'dark',
    'roxo'      => 'purple',
    'vermelho'  => 'red',
    'cinza'     => 'gray',
];
$temaSlug = $mapTemasAntigosSlug[$temaAtualBruto] ?? $temaAtualBruto;
if (!in_array($temaSlug, ['dark', 'light', 'purple', 'red', 'gray'], true)) {
    $temaSlug = 'dark';
}

$appTopBarTitle = 'Perfil';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="ds-page-header">
    <h1>Perfil &amp; preferências</h1>
    <p>Dados da conta, foto e tema visual da aplicação.</p>
</div>

<?php if ($mensagemSucesso): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($mensagemSucesso); ?></div>
<?php endif; ?>
<?php if ($mensagemErro): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($mensagemErro); ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-body text-center">
                <img src="<?php echo htmlspecialchars($avatarPath); ?>" alt="Foto de perfil"
                     class="rounded-circle mb-3" style="width: 120px; height: 120px; object-fit: cover;">
                <p class="text-muted small mb-3">Foto de perfil</p>
                <label class="btn btn-outline-primary w-100">
                    Alterar foto de perfil
                    <input class="d-none" type="file" name="foto_perfil" accept="image/*" form="formPerfil">
                </label>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="card-title mb-3">Dados da conta</h5>
                <form method="post" enctype="multipart/form-data" id="formPerfil">
                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome</label>
                        <input type="text" class="form-control" id="nome" name="nome"
                               value="<?php echo htmlspecialchars($utilizador['nome']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email"
                               value="<?php echo htmlspecialchars($utilizador['email']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Nova palavra-passe (opcional)</label>
                        <input type="password" class="form-control" id="password" name="password"
                               placeholder="Deixa vazio para manter a atual">
                    </div>
                    <div class="mb-3">
                        <label for="tema" class="form-label">Tema do site</label>
                        <select class="form-select" id="tema" name="tema">
                            <?php
                            $opcoesTema = [
                                'dark'   => 'Escuro',
                                'light'  => 'Claro',
                                'purple' => 'Roxo',
                                'red'    => 'Vermelho',
                                'gray'   => 'Cinza',
                            ];
                            $temaAtualBruto = $utilizador['tema'] ?? 'dark';
                            $mapTemasAntigos = [
                                'escuro'    => 'dark',
                                'claro'     => 'light',
                                'neon-azul' => 'dark',
                                'roxo'      => 'purple',
                                'vermelho'  => 'red',
                                'cinza'     => 'gray',
                            ];
                            $temaAtual = $mapTemasAntigos[$temaAtualBruto] ?? $temaAtualBruto;
                            if (!in_array($temaAtual, array_keys($opcoesTema), true)) {
                                $temaAtual = 'dark';
                            }
                            foreach ($opcoesTema as $valor => $label): ?>
                                <option value="<?php echo $valor; ?>"
                                    <?php echo $temaAtual === $valor ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Guardar alterações</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.GFThemeStorage) {
        GFThemeStorage.set(<?php echo json_encode($temaSlug); ?>);
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>


