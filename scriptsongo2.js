let myRole = 1; // Par défaut Joueur 1
const pitElements = document.querySelectorAll('.pit');
const roleSelect = document.getElementById('player-role');
const playerIndicator = document.getElementById('current-player');
const scoreJ1Element = document.getElementById('score-j1');
const scoreJ2Element = document.getElementById('score-j2');
const resetBtn = document.getElementById('reset-btn');

// Écouter le changement de rôle sur l'écran local
roleSelect.addEventListener('change', (e) => {
    myRole = parseInt(e.target.value);
    fetchGameState();
});

// FONCTION AJAX : Récupérer l'état actuel depuis la DB
async function fetchGameState() {
    try {
        const response = await fetch('server.php?action=get_state');
        const data = await response.json();
        if (data.error) return;

        updateUI(data.board, parseInt(data.current_player), parseInt(data.score_j1), parseInt(data.score_j2));
    } catch (err) {
        console.error("Erreur lors de la synchronisation Ajax :", err);
    }
}

// Mettre à jour l'affichage graphique
function updateUI(board, currentPlayer, scoreJ1, scoreJ2) {
    const j1_pits = [0, 1, 2, 3, 4, 5, 6];
    const j2_pits = [7, 8, 9, 10, 11, 12, 13];

    board.forEach((pebbles, index) => {
        const pit = document.querySelector(`[data-index="${index}"]`);
        pit.textContent = pebbles;
        
        // Mettre en surbrillance uniquement si c'est le tour du joueur ET sa propre rangée
        if (currentPlayer === myRole) {
            if ((myRole === 1 && j1_pits.includes(index) && pebbles > 0) ||
                (myRole === 2 && j2_pits.includes(index) && pebbles > 0)) {
                pit.classList.add('my-turn-pit');
            } else {
                pit.classList.remove('my-turn-pit');
            }
        } else {
            pit.classList.remove('my-turn-pit');
        }
    });

    scoreJ1Element.textContent = scoreJ1;
    scoreJ2Element.textContent = scoreJ2;

    if (currentPlayer === myRole) {
        playerIndicator.textContent = `À VOUS DE JOUER (Joueur ${currentPlayer})`;
        playerIndicator.style.color = '#2ecc71';
    } else {
        playerIndicator.textContent = `En attente du Joueur ${currentPlayer}...`;
        playerIndicator.style.color = '#e74c3c';
    }
}

// FONCTION AJAX : Envoyer un coup joué
async function handlePitClick(e) {
    const pitIndex = parseInt(e.target.getAttribute('data-index'));

    // Envoyer la commande au serveur via POST Ajax
    try {
        const response = await fetch('server.php?action=play_turn', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ pitIndex: pitIndex, player: myRole })
        });
        const result = await response.json();
        
        if (result.error) {
            alert(result.error);
        } else {
            fetchGameState(); // Actualisation immédiate après avoir joué
        }
    } catch (err) {
        console.error("Erreur d'envoi du coup :", err);
    }
}

// FONCTION AJAX : Reset
resetBtn.addEventListener('click', async () => {
    await fetch('server.php?action=reset');
    fetchGameState();
});

// Événements initiaux
pitElements.forEach(pit => pit.addEventListener('click', handlePitClick));
myRole = parseInt(roleSelect.value);

// Lancement de la synchronisation automatique toutes les 500ms (Ajax Polling)
fetchGameState();
setInterval(fetchGameState, 500);
