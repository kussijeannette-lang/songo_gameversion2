<?php
header('Content-Type: application/json');

$host = 'localhost';
$db = 'songo_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    echo json_encode(array('error' => 'Erreur de connexion DB'));
    exit;
}

// Correction de la ligne 19 : utilisation de isset() à la place de ??
$action = isset($_GET['action']) ? $_GET['action'] : '';

// ACTION 1 : LIRE L'ÉTAT DU JEU
if ($action === 'get_state') {
    $stmt = $pdo->query("SELECT * FROM game_state WHERE id = 1");
    $game = $stmt->fetch();
    $game['board'] = array_map('intval', explode(',', $game['board']));
    echo json_encode($game);
    exit;
}

// ACTION 2 : RECOMMENCER
if ($action === 'reset') {
    $stmt = $pdo->prepare("UPDATE game_state SET board = ?, current_player = 1, score_j1 = 0, score_j2 = 0 WHERE id = 1");
    $stmt->execute(array('5,5,5,5,5,5,5,5,5,5,5,5,5,5'));
    echo json_encode(array('status' => 'success'));
    exit;
}

// ACTION 3 : JOUER UN COUP (REQUÊTE AJAX POST)
if ($action === 'play_turn') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Remplacement des opérateurs ?? par des vérifications classiques
    $pitIndex = (isset($input['pitIndex'])) ? intval($input['pitIndex']) : -1;
    $player = (isset($input['player'])) ? intval($input['player']) : 0;

    if ($pitIndex < 0 || $player < 1 || $player > 2) {
        echo json_encode(array('error' => 'Paramètres invalides'));
        exit;
    }

    // Récupérer l'état actuel
    $stmt = $pdo->query("SELECT * FROM game_state WHERE id = 1");
    $game = $stmt->fetch();
    $board = array_map('intval', explode(',', $game['board']));
    $currentPlayer = intval($game['current_player']);
    $score_j1 = intval($game['score_j1']);
    $score_j2 = intval($game['score_j2']);

    // Vérifications de sécurité côté serveur
    if ($player !== $currentPlayer) {
        echo json_encode(array('error' => 'Ce n\'est pas votre tour !'));
        exit;
    }

    $j1_pits = array(0,1,2,3,4,5,6);
    $j2_pits = array(7,8,9,10,11,12,13);

    if (($player === 1 && !in_array($pitIndex, $j1_pits)) || ($player === 2 && !in_array($pitIndex, $j2_pits)) || $board[$pitIndex] === 0) {
        echo json_encode(array('error' => 'Coup illégal'));
        exit;
    }

    // Execution du Semis (Logique Songo)
    $pebbles = $board[$pitIndex];
    $board[$pitIndex] = 0;
    $currentIndex = $pitIndex;

    while ($pebbles > 0) {
        $currentIndex = ($currentIndex + 1) % 14;
        if ($currentIndex === $pitIndex) continue;
        $board[$currentIndex]++;
        $pebbles--;
    }

    // Logique des captures
    $opponent_pits = ($player === 1) ? $j2_pits : $j1_pits;
    if (in_array($currentIndex, $opponent_pits) && ($board[$currentIndex] >= 2 && $board[$currentIndex] <= 4)) {
        if ($player === 1) $score_j1 += $board[$currentIndex];
        else $score_j2 += $board[$currentIndex];
        $board[$currentIndex] = 0;

        // Capture en rafale (rétrograde)
        $prevIndex = ($currentIndex - 1 + 14) % 14;
        while (in_array($prevIndex, $opponent_pits) && ($board[$prevIndex] >= 2 && $board[$prevIndex] <= 4)) {
            if ($player === 1) $score_j1 += $board[$prevIndex];
            else $score_j2 += $board[$prevIndex];
            $board[$prevIndex] = 0;
            $prevIndex = ($prevIndex - 1 + 14) % 14;
        }
    }

    // Vérification de fin de partie
    $j1_sum = array_sum(array_slice($board, 0, 7));
    $j2_sum = array_sum(array_slice($board, 7, 7));

    if ($j1_sum === 0 || $j2_sum === 0) {
        $score_j1 += $j1_sum;
        $score_j2 += $j2_sum;
        $board = array_fill(0, 14, 0);
    }

    // Changer de joueur
    $nextPlayer = ($currentPlayer === 1) ? 2 : 1;
    $boardStr = implode(',', $board);

    // Sauvegarde de l'état
    $updateStmt = $pdo->prepare("UPDATE game_state SET board = ?, current_player = ?, score_j1 = ?, score_j2 = ? WHERE id = 1");
    $updateStmt->execute(array($boardStr, $nextPlayer, $score_j1, $score_j2));

    echo json_encode(array('status' => 'success'));
    exit;
}

