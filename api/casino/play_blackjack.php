<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/xp_system.php';

secure_session_start();
require_login();

$user_id = get_current_user_id();

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$bet = isset($input['bet']) ? floatval($input['bet']) : 0;

// Validate bet amount
if (($action === 'start' || $action === 'deal') && ($bet < 0.5 || $bet > 50)) {
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
if (($action === 'start' || $action === 'deal') && $bet > $available_balance) {
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

if ($action === 'start' || $action === 'deal') {
    // Deduct bet from balance immediately
    $stmt = $conn->prepare("
        INSERT INTO transaktionen (typ, typ_differenziert, betrag, mitglied_id, beschreibung, erstellt_von, datum)
        VALUES ('AUSZAHLUNG', 'POOL', ?, ?, 'Casino Blackjack Einsatz', ?, NOW())
    ");
    $stmt->bind_param('dii', $bet, $user_id, $user_id);
    $stmt->execute();
    $bet_trans_id = $conn->insert_id;
    $stmt->close();
    
    // Update member_payment_status
    $stmt = $conn->prepare("
        UPDATE member_payment_status mps
        JOIN v_member_balance vmb ON vmb.id = mps.mitglied_id
        SET mps.guthaben = vmb.balance
        WHERE mps.mitglied_id = ?
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->close();
    
    // Award XP for bet
    add_xp($user_id, 'CASINO_BET', 'Blackjack Einsatz', $bet_trans_id, 'transaktionen', round($bet * 10));
    
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
    $payout = 0;
    $net_profit = 0;
    $gameOver = false;
    
    if ($playerValue === 21 && $dealerValue === 21) {
        // Both Blackjack - Push
        $result = 'push';
        $payout = $bet; // Get bet back
        $net_profit = 0;
        $gameOver = true;
    } elseif ($playerValue === 21) {
        // Player Blackjack - Pays 2.5x (3:2)
        $result = 'blackjack';
        $payout = $bet * 2.5; // Bet back + 1.5x bet
        $net_profit = $bet * 1.5;
        $gameOver = true;
    } elseif ($dealerValue === 21) {
        // Dealer Blackjack - Player loses
        $result = 'dealer_blackjack';
        $payout = 0;
        $net_profit = -$bet;
        $gameOver = true;
    }
    
    // If game over immediately, update balance and save
    if ($gameOver) {
        // Add winnings if any
        if ($payout > 0) {
            $stmt = $conn->prepare("
                INSERT INTO transaktionen (typ, typ_differenziert, betrag, mitglied_id, beschreibung, erstellt_von, datum)
                VALUES ('EINZAHLUNG', 'POOL', ?, ?, 'Casino Blackjack Gewinn', ?, NOW())
            ");
            $stmt->bind_param('dii', $payout, $user_id, $user_id);
            $stmt->execute();
            $win_trans_id = $conn->insert_id;
            $stmt->close();
            
            // Award XP for Net Profit (10 XP per 1€)
            // Only award if there is actual profit (not just push)
            if ($net_profit > 0) {
                add_xp($user_id, 'CASINO_WIN', 'Blackjack Gewinn', $win_trans_id, 'transaktionen', round($net_profit * 10));
            }
        }
        
        // Update member_payment_status
        $stmt = $conn->prepare("
            UPDATE member_payment_status mps
            JOIN v_member_balance vmb ON vmb.id = mps.mitglied_id
            SET mps.guthaben = vmb.balance
            WHERE mps.mitglied_id = ?
        ");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->close();
        
        // Get new balance
        $stmt = $conn->prepare("SELECT balance FROM v_member_balance WHERE id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->bind_result($new_balance);
        $stmt->fetch();
        $stmt->close();
        
        $new_balance = floatval($new_balance);
        
        // Save to casino history
        $multiplier = $payout > 0 ? ($payout / $bet) : 0;
        $stmt = $conn->prepare("INSERT INTO casino_history (user_id, game_type, bet_amount, win_amount, multiplier, result) VALUES (?, 'blackjack', ?, ?, ?, ?)");
        $stmt->bind_param('isdds', $user_id, $bet, $payout, $multiplier, $result);
        $stmt->execute();
        $stmt->close();
        
        // Clear session
        unset($_SESSION['blackjack']);
        
        echo json_encode([
            'status' => 'success',
            'player_hand' => $playerHand,
            'dealer_hand' => $dealerHand,
            'player_score' => $playerValue,
            'dealer_score' => $dealerValue,
            'result' => $result,
            'win' => $payout,
            'profit' => $net_profit,
            'new_balance' => $new_balance,
            'game_over' => true,
            'dealer_visible' => true
        ]);
    } else {
        echo json_encode([
            'status' => 'success',
            'player_hand' => $playerHand,
            'dealer_hand' => [$dealerHand[0]], // Only show one dealer card
            'player_score' => $playerValue,
            'result' => null,
            'game_over' => false,
            'dealer_visible' => false
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
    $new_balance = null;
    
    if ($playerValue > 21) {
        $result = 'bust';
        
        // Save to casino history (Loss)
        $stmt = $conn->prepare("INSERT INTO casino_history (user_id, game_type, bet_amount, win_amount, multiplier, result) VALUES (?, 'blackjack', ?, 0, 0, 'bust')");
        $stmt->bind_param('id', $user_id, $bet);
        $stmt->execute();
        $stmt->close();
        
        // Get current balance
        $stmt = $conn->prepare("SELECT balance FROM v_member_balance WHERE id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->bind_result($new_balance);
        $stmt->fetch();
        $stmt->close();
        
        $new_balance = floatval($new_balance);
        
        // Clear session
        unset($_SESSION['blackjack']);
        
        echo json_encode([
            'status' => 'success',
            'player_hand' => $playerHand,
            'dealer_hand' => [$dealerHand[0]],
            'player_score' => $playerValue,
            'dealer_score' => '?',
            'result' => $result,
            'game_over' => true,
            'dealer_visible' => false,
            'new_balance' => $new_balance,
            'win' => 0,
            'profit' => -$bet
        ]);
        exit;
    }
    
    // If player has 21, auto-stand (proceed to resolution)
    if ($playerValue == 21) {
        // Fall through to resolution block
    } else {
        echo json_encode([
            'status' => 'success',
            'player_hand' => $playerHand,
            'dealer_hand' => [$dealerHand[0]],
            'player_score' => $playerValue,
            'dealer_score' => '?',
            'result' => null,
            'game_over' => false,
            'dealer_visible' => false
        ]);
        exit;
    }
}

if ($action === 'stand' || $action === 'double' || ($action === 'hit' && $playerValue == 21)) {
    if ($action === 'double') {
        // Double: Check if player has enough balance for additional bet
        if ($bet > $available_balance) {
            echo json_encode(['status' => 'error', 'error' => 'Nicht genug Guthaben für Double!']);
            exit;
        }
        
        // Deduct additional bet (original bet was already deducted at start)
        $stmt = $conn->prepare("
            INSERT INTO transaktionen (typ, typ_differenziert, betrag, mitglied_id, beschreibung, erstellt_von, datum)
            VALUES ('AUSZAHLUNG', 'POOL', ?, ?, 'Casino Blackjack Double', ?, NOW())
        ");
        $stmt->bind_param('dii', $bet, $user_id, $user_id);
        $stmt->execute();
        $double_trans_id = $conn->insert_id;
        $stmt->close();
        
        // Update member_payment_status
        $stmt = $conn->prepare("
            UPDATE member_payment_status mps
            JOIN v_member_balance vmb ON vmb.id = mps.mitglied_id
            SET mps.guthaben = vmb.balance
            WHERE mps.mitglied_id = ?
        ");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->close();
        
        // Award XP for additional bet
        add_xp($user_id, 'CASINO_BET', 'Blackjack Double', $double_trans_id, 'transaktionen', round($bet * 10));
        
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
    $payout = 0;
    $multiplier = 0;
    $net_profit = 0; // What to add/subtract from balance
    
    if ($playerValue > 21) {
        // Player bust - loses (bet already deducted, nothing to add back)
        $result = 'bust';
        $payout = 0;
        $multiplier = 0;
        $net_profit = -$bet; // Lost the bet
    } elseif ($dealerValue > 21) {
        // Dealer bust - player wins
        $result = 'dealer_bust';
        $payout = $bet * 2; // Display: bet back + winnings
        $multiplier = 2.0;
        $net_profit = $bet; // Add: bet back + winnings
    } elseif ($playerValue > $dealerValue) {
        // Player has higher value
        $result = 'win';
        $payout = $bet * 2;
        $multiplier = 2.0;
        $net_profit = $bet; // Add: bet back + winnings
    } elseif ($playerValue === $dealerValue) {
        // Tie - push
        $result = 'push';
        $payout = $bet; // Display: bet returned
        $multiplier = 1.0;
        $net_profit = 0; // Add: just the bet back
    } else {
        // Dealer wins (bet already deducted, nothing to add back)
        $result = 'lose';
        $payout = 0;
        $multiplier = 0;
        $net_profit = -$bet; // Lost the bet
    }
    
    // Add winnings if any
    if ($payout > 0) {
        $stmt = $conn->prepare("
            INSERT INTO transaktionen (typ, typ_differenziert, betrag, mitglied_id, beschreibung, erstellt_von, datum)
            VALUES ('EINZAHLUNG', 'POOL', ?, ?, 'Casino Blackjack Gewinn', ?, NOW())
        ");
        $stmt->bind_param('dii', $payout, $user_id, $user_id);
        $stmt->execute();
        $win_trans_id = $conn->insert_id;
        $stmt->close();
        
        // Award XP for Net Profit (10 XP per 1€)
        // Only award if there is actual profit (not just push)
        if ($net_profit > 0) {
            add_xp($user_id, 'CASINO_WIN', 'Blackjack Gewinn', $win_trans_id, 'transaktionen', round($net_profit * 10));
        }
    }
    
    // Update member_payment_status
    $stmt = $conn->prepare("
        UPDATE member_payment_status mps
        JOIN v_member_balance vmb ON vmb.id = mps.mitglied_id
        SET mps.guthaben = vmb.balance
        WHERE mps.mitglied_id = ?
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->close();
    
    // Get new balance
    $stmt = $conn->prepare("SELECT balance FROM v_member_balance WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($new_balance);
    $stmt->fetch();
    $stmt->close();
    
    $new_balance = floatval($new_balance);
    
    // Save to casino history
    $stmt = $conn->prepare("INSERT INTO casino_history (user_id, game_type, bet_amount, win_amount, multiplier, result) VALUES (?, 'blackjack', ?, ?, ?, ?)");
    $stmt->bind_param('isdds', $user_id, $bet, $payout, $multiplier, $result);
    $stmt->execute();
    $stmt->close();
    
    // Clear game session
    unset($_SESSION['blackjack']);
    
    echo json_encode([
        'status' => 'success',
        'player_hand' => $playerHand,
        'dealer_hand' => $dealerHand,
        'player_score' => $playerValue,
        'dealer_score' => $dealerValue,
        'result' => $result,
        'win' => $payout,
        'profit' => $net_profit,
        'new_balance' => $new_balance,
        'game_over' => true,
        'dealer_visible' => true
    ]);
    exit;
}

echo json_encode(['status' => 'error', 'error' => 'Ungültige Aktion']);
