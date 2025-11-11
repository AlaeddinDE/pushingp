<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db.php';

secure_session_start();
require_login();

header('Content-Type: application/json');

$user_id = get_current_user_id();

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$bet = isset($input['bet']) ? floatval($input['bet']) : 0;

// Validate bet amount
if ($action === 'start' && ($bet < 0.5 || $bet > 50)) {
    echo json_encode(['status' => 'error', 'error' => 'Einsatz muss zwischen 0.50€ und 50€ liegen']);
    exit;
}

// Get user balance
$stmt = $conn->prepare("SELECT v.balance FROM users u LEFT JOIN v_member_balance v ON u.username = v.username WHERE u.id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($db_balance);
$stmt->fetch();
$stmt->close();

$total_balance = floatval($db_balance ?? 0);
$available_balance = max(0, $total_balance - 10.00);

// Check sufficient balance
if ($action === 'start' && $bet > $available_balance) {
    echo json_encode(['status' => 'error', 'error' => 'Nicht genug Guthaben!']);
    exit;
}

// Card values
$suits = ['♠', '♥', '♦', '♣'];
$ranks = ['A', '2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K'];

function createDeck() {
    global $suits, $ranks;
    $deck = [];
    foreach ($suits as $suit) {
        foreach ($ranks as $rank) {
            $deck[] = ['rank' => $rank, 'suit' => $suit];
        }
    }
    shuffle($deck);
    return $deck;
}

function getCardValue($card) {
    if (in_array($card['rank'], ['J', 'Q', 'K'])) return 10;
    if ($card['rank'] === 'A') return 11;
    return intval($card['rank']);
}

function getHandValue($hand) {
    $value = 0;
    $aces = 0;
    
    foreach ($hand as $card) {
        $cardValue = getCardValue($card);
        $value += $cardValue;
        if ($card['rank'] === 'A') $aces++;
    }
    
    // Adjust for Aces
    while ($value > 21 && $aces > 0) {
        $value -= 10;
        $aces--;
    }
    
    return $value;
}

if ($action === 'start') {
    // Deduct bet from balance immediately
    $stmt = $conn->prepare("
        UPDATE members_v2 
        SET saldo = saldo - ? 
        WHERE user_id = (SELECT id FROM users WHERE id = ?)
    ");
    $stmt->bind_param('di', $bet, $user_id);
    $stmt->execute();
    $stmt->close();
    
    // Create new deck
    $deck = createDeck();
    
    // Deal initial cards
    $playerHand = [$deck[0], $deck[2]];
    $dealerHand = [$deck[1], $deck[3]];
    $deckIndex = 4;
    
    $playerValue = getHandValue($playerHand);
    $dealerValue = getHandValue($dealerHand);
    
    // Store game state in session
    $_SESSION['blackjack'] = [
        'deck' => $deck,
        'deckIndex' => $deckIndex,
        'playerHand' => $playerHand,
        'dealerHand' => $dealerHand,
        'bet' => $bet,
        'started_at' => time()
    ];
    
    // Check for immediate Blackjack
    $result = null;
    $winAmount = 0;
    $profit = 0;
    $gameOver = false;
    
    if ($playerValue === 21 && $dealerValue === 21) {
        // Both Blackjack - Push
        $result = 'push';
        $winAmount = $bet; // Get bet back
        $profit = 0;
        $gameOver = true;
    } elseif ($playerValue === 21) {
        // Player Blackjack - Pays 2.5x
        $result = 'blackjack';
        $winAmount = $bet * 2.5; // Blackjack pays 3:2 (bet back + 1.5x bet)
        $profit = $bet * 1.5;
        $gameOver = true;
    } elseif ($dealerValue === 21) {
        // Dealer Blackjack - Player loses
        $result = 'dealer_blackjack';
        $winAmount = 0;
        $profit = -$bet;
        $gameOver = true;
    }
    
    // If game over immediately, update balance and save
    if ($gameOver) {
        // Update balance in database
        $stmt = $conn->prepare("
            UPDATE members_v2 
            SET saldo = saldo + ? 
            WHERE user_id = (SELECT id FROM users WHERE id = ?)
        ");
        $stmt->bind_param('di', $profit, $user_id);
        $stmt->execute();
        $stmt->close();
        
        // Get new balance
        $stmt = $conn->prepare("SELECT v.balance FROM users u LEFT JOIN v_member_balance v ON u.username = v.username WHERE u.id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->bind_result($new_total_balance);
        $stmt->fetch();
        $stmt->close();
        
        $new_balance = max(0, floatval($new_total_balance ?? 0) - 10.00);
        
        // Save to casino history
        $multiplier = $winAmount > 0 ? ($winAmount / $bet) : 0;
        $stmt = $conn->prepare("INSERT INTO casino_history (user_id, game_type, bet_amount, win_amount, multiplier, result) VALUES (?, 'blackjack', ?, ?, ?, ?)");
        $stmt->bind_param('isdds', $user_id, $bet, $winAmount, $multiplier, $result);
        $stmt->execute();
        $stmt->close();
        
        // Clear session
        unset($_SESSION['blackjack']);
        
        echo json_encode([
            'status' => 'success',
            'playerHand' => $playerHand,
            'dealerHand' => $dealerHand,
            'playerValue' => $playerValue,
            'dealerValue' => $dealerValue,
            'result' => $result,
            'winAmount' => $winAmount,
            'profit' => $profit,
            'newBalance' => $new_balance,
            'gameOver' => true
        ]);
    } else {
        echo json_encode([
            'status' => 'success',
            'playerHand' => $playerHand,
            'dealerHand' => [$dealerHand[0]], // Only show one dealer card
            'playerValue' => $playerValue,
            'result' => null,
            'gameOver' => false
        ]);
    }
    exit;
}

// All other actions need an active game
if (!isset($_SESSION['blackjack'])) {
    echo json_encode(['status' => 'error', 'error' => 'Kein aktives Spiel']);
    exit;
}

$game = $_SESSION['blackjack'];
$deck = $game['deck'];
$deckIndex = $game['deckIndex'];
$playerHand = $game['playerHand'];
$dealerHand = $game['dealerHand'];
$bet = $game['bet'];

if ($action === 'hit') {
    // Player draws a card
    $playerHand[] = $deck[$deckIndex++];
    $playerValue = getHandValue($playerHand);
    
    $_SESSION['blackjack']['playerHand'] = $playerHand;
    $_SESSION['blackjack']['deckIndex'] = $deckIndex;
    
    $result = null;
    if ($playerValue > 21) {
        $result = 'bust';
    }
    
    echo json_encode([
        'status' => 'success',
        'playerHand' => $playerHand,
        'playerValue' => $playerValue,
        'result' => $result
    ]);
    exit;
}

if ($action === 'stand' || $action === 'double') {
    if ($action === 'double') {
        // Double: Check if player has enough balance for additional bet
        if ($bet > $available_balance) {
            echo json_encode(['status' => 'error', 'error' => 'Nicht genug Guthaben für Double!']);
            exit;
        }
        
        // Deduct additional bet (original bet was already deducted at start)
        $stmt = $conn->prepare("
            UPDATE members_v2 
            SET saldo = saldo - ? 
            WHERE user_id = (SELECT id FROM users WHERE id = ?)
        ");
        $stmt->bind_param('di', $bet, $user_id);
        $stmt->execute();
        $stmt->close();
        
        // Double the total bet amount for win calculations
        $bet *= 2;
        $playerHand[] = $deck[$deckIndex++];
    }
    
    $playerValue = getHandValue($playerHand);
    
    // Dealer draws cards (only if player not bust)
    if ($playerValue <= 21) {
        while (getHandValue($dealerHand) < 17) {
            $dealerHand[] = $deck[$deckIndex++];
        }
    }
    
    $dealerValue = getHandValue($dealerHand);
    
    // Determine winner
    // Note: Bet was already deducted, so we calculate what to add back
    $result = 'lose';
    $winAmount = 0;
    $multiplier = 0;
    $profit = 0; // What to add/subtract from balance
    
    if ($playerValue > 21) {
        // Player bust - loses (bet already deducted, nothing to add back)
        $result = 'bust';
        $winAmount = 0;
        $multiplier = 0;
        $profit = 0; // Lost the bet
    } elseif ($dealerValue > 21) {
        // Dealer bust - player wins
        $result = 'dealer_bust';
        $winAmount = $bet * 2; // Display: bet back + winnings
        $multiplier = 2.0;
        $profit = $bet * 2; // Add: bet back + winnings
    } elseif ($playerValue > $dealerValue) {
        // Player has higher value
        $result = 'win';
        $winAmount = $bet * 2;
        $multiplier = 2.0;
        $profit = $bet * 2; // Add: bet back + winnings
    } elseif ($playerValue === $dealerValue) {
        // Tie - push
        $result = 'push';
        $winAmount = $bet; // Display: bet returned
        $multiplier = 1.0;
        $profit = $bet; // Add: just the bet back
    } else {
        // Dealer wins (bet already deducted, nothing to add back)
        $result = 'lose';
        $winAmount = 0;
        $multiplier = 0;
        $profit = 0; // Lost the bet
    }
    
    // Update balance in database via transaction
    $stmt = $conn->prepare("
        UPDATE members_v2 
        SET saldo = saldo + ? 
        WHERE user_id = (SELECT id FROM users WHERE id = ?)
    ");
    $stmt->bind_param('di', $profit, $user_id);
    $stmt->execute();
    $stmt->close();
    
    // Get new balance
    $stmt = $conn->prepare("SELECT v.balance FROM users u LEFT JOIN v_member_balance v ON u.username = v.username WHERE u.id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($new_total_balance);
    $stmt->fetch();
    $stmt->close();
    
    $new_balance = max(0, floatval($new_total_balance ?? 0) - 10.00);
    
    // Save to casino history
    $stmt = $conn->prepare("INSERT INTO casino_history (user_id, game_type, bet_amount, win_amount, multiplier, result) VALUES (?, 'blackjack', ?, ?, ?, ?)");
    $stmt->bind_param('isdds', $user_id, $bet, $winAmount, $multiplier, $result);
    $stmt->execute();
    $stmt->close();
    
    // Clear game session
    unset($_SESSION['blackjack']);
    
    echo json_encode([
        'status' => 'success',
        'playerHand' => $playerHand,
        'dealerHand' => $dealerHand,
        'playerValue' => $playerValue,
        'dealerValue' => $dealerValue,
        'result' => $result,
        'winAmount' => $winAmount,
        'profit' => $profit,
        'newBalance' => $new_balance
    ]);
    exit;
}

echo json_encode(['status' => 'error', 'error' => 'Ungültige Aktion']);
