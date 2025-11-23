<?php
session_start();

// Initialize game if starting fresh
// Reset game if: POST request with new_game parameter (from Start Game button) OR game hasn't started
$isNewGame = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_game']));
if ($isNewGame || !isset($_SESSION['game_started'])) {
    // Define 26 briefcase values (standard Deal or No Deal amounts)
    $values = [
        0.01, 1, 5, 10, 25, 50, 75, 100, 200, 300, 400, 500, 750,
        1000, 5000, 10000, 25000, 50000, 75000, 100000, 200000,
        300000, 400000, 500000, 750000, 1000000
    ];
    
    // Shuffle values randomly
    shuffle($values);
    
    // Assign values to cases 1-26
    $_SESSION['case_values'] = [];
    for ($i = 1; $i <= 26; $i++) {
        $_SESSION['case_values'][$i] = $values[$i - 1];
    }
    
    // Reset all game state
    $_SESSION['player_case'] = null;
    $_SESSION['eliminated'] = [];
    $_SESSION['round'] = 1;
    $_SESSION['game_started'] = true;

    // New session-based data structures
    $_SESSION['offer_history'] = [];
    $_SESSION['market_events'] = [];
    $_SESSION['current_event'] = null;
    $_SESSION['last_event_round'] = 0;
    $_SESSION['last_revealed_value'] = null;
    $_SESSION['last_revealed_case'] = null;
    $_SESSION['deal_taken'] = false;
    $_SESSION['final_amount'] = null;
    $_SESSION['current_offer'] = null;
    $_SESSION['active_offer'] = null;
}

// Handle deal/no deal decision
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['decision'])) {
    $decision = $_POST['decision'];
    $offerAmount = isset($_POST['offer_amount']) ? (float)$_POST['offer_amount'] : 0;
    
    if ($decision === 'deal') {
        // Player accepts deal and game ends
        $_SESSION['final_amount'] = $offerAmount;
        $_SESSION['deal_taken'] = true;
        header('Location: result.php');
        exit;
    } else {
        // Player says "No Deal" – go back to game board
        // Set a flag to prevent showing the same offer again immediately
        $_SESSION['offer_rejected'] = true;
        $_SESSION['last_rejected_round'] = (int)(count($_SESSION['eliminated']) / 6);
        header('Location: game.php');
        exit;
    }
}

// Handle case selection
if (isset($_GET['select']) && is_numeric($_GET['select'])) {
    $caseNum = (int)$_GET['select'];
    
    if ($caseNum >= 1 && $caseNum <= 26) {
        // If no player case selected yet, this becomes the player's case
        if ($_SESSION['player_case'] === null) {
            $_SESSION['player_case'] = $caseNum;
        } 
        // Otherwise, eliminate this case
        else if ($caseNum != $_SESSION['player_case'] && !in_array($caseNum, $_SESSION['eliminated'])) {
            $_SESSION['eliminated'][] = $caseNum;

            // Track last revealed case & value for progressive revelation message
            $_SESSION['last_revealed_case'] = $caseNum;
            $_SESSION['last_revealed_value'] = $_SESSION['case_values'][$caseNum];
            
            // Clear offer rejection flag when a new case is eliminated
            // This allows the next offer to show when we reach the next multiple of 6
            if (count($_SESSION['eliminated']) % 6 != 0) {
                $_SESSION['offer_rejected'] = false;
                unset($_SESSION['last_rejected_round']);
            }
        }
    }
}

// Calculate remaining cases
$remainingCases = [];
for ($i = 1; $i <= 26; $i++) {
    if ($i != $_SESSION['player_case'] && !in_array($i, $_SESSION['eliminated'])) {
        $remainingCases[] = $i;
    }
}

// --- Volatile Market Events (random events that alter values) --- //
// Run at most once per round, starting from round 2
if (!isset($_SESSION['market_events'])) {
    $_SESSION['market_events'] = [];
}
if (!isset($_SESSION['last_event_round'])) {
    $_SESSION['last_event_round'] = 0;
}
$_SESSION['current_event'] = null;

$currentRound = $_SESSION['round'] ?? 1;

if ($currentRound >= 2 && $_SESSION['last_event_round'] < $currentRound) {
    // 25% chance of a market event this round
    $roll = rand(1, 100);
    if ($roll <= 25) {
        $type = (rand(0, 1) === 0) ? 'crash' : 'boom';
        $factor = ($type === 'crash') ? 0.8 : 1.2;

        // Apply factor to all non-eliminated cases (including player's case)
        for ($i = 1; $i <= 26; $i++) {
            $isEliminated = in_array($i, $_SESSION['eliminated']);
            if (!$isEliminated) {
                $_SESSION['case_values'][$i] = round($_SESSION['case_values'][$i] * $factor, 2);
            }
        }

        $description = ($type === 'crash')
            ? "Market Crash! All remaining case values dropped by 20%."
            : "Bull Market! All remaining case values increased by 20%.";

        $event = [
            'round' => $currentRound,
            'type' => $type,
            'factor' => $factor,
            'description' => $description
        ];

        $_SESSION['market_events'][] = $event;
        $_SESSION['current_event'] = $description;
        $_SESSION['last_event_round'] = $currentRound;
    }
}

// Calculate remaining values for banker algorithm
$remainingValues = [];
foreach ($remainingCases as $caseNum) {
    $remainingValues[] = $_SESSION['case_values'][$caseNum];
}
if ($_SESSION['player_case'] !== null) {
    $remainingValues[] = $_SESSION['case_values'][$_SESSION['player_case']];
}

// Calculate offer if it's time for one (every 6 eliminations)
$showOffer = false;
$currentOffer = null;
$offerType = 'standard';
$offerNote = 'Standard offer based on remaining values.';
$minRemaining = 0;
$maxRemaining = 0;
$totalRemaining = 0;

// Calculate current round
$currentRound = (int)(count($_SESSION['eliminated']) / 6);
if ($currentRound < 1) {
    $currentRound = 1;
}

// Only show offer if:
// 1. We have eliminations that are a multiple of 6
// 2. We have remaining values
// 3. We haven't just rejected an offer for this round (or we've eliminated more cases since)
$shouldShowOffer = (
    count($_SESSION['eliminated']) > 0 && 
    count($_SESSION['eliminated']) % 6 == 0 && 
    count($remainingValues) > 0
);

// Check if we just rejected an offer for this round
if (isset($_SESSION['offer_rejected']) && $_SESSION['offer_rejected']) {
    $lastRejectedRound = $_SESSION['last_rejected_round'] ?? 0;
    // Only show offer if we've moved to a new round (more eliminations)
    if ($currentRound <= $lastRejectedRound) {
        $shouldShowOffer = false;
    } else {
        // We've moved to a new round, clear the rejection flag
        $_SESSION['offer_rejected'] = false;
        unset($_SESSION['last_rejected_round']);
    }
}

if ($shouldShowOffer) {
    $showOffer = true;
    $round = $currentRound;
    
    // Update session round to match calculated round (for use in offer calculation)
    $_SESSION['round'] = $round;
    
    // Compute base stats for Banker logic
    $average = array_sum($remainingValues) / count($remainingValues);
    $minRemaining = min($remainingValues);
    $maxRemaining = max($remainingValues);
    $countRemaining = count($remainingValues);
    $spread = $maxRemaining - $minRemaining;
    
    // Simple risk index: how spread out the remaining amounts are
    $riskIndex = ($average > 0) ? ($spread / $average) : 0;
    
    // Volatility factor uses multiple variables: round, riskIndex, base factor
    $volatilityFactor = 0.6 + ($round * 0.05) + min($riskIndex * 0.02, 0.15);
    if ($volatilityFactor > 1.0) {
        $volatilityFactor = 1.0;
    }
    if ($volatilityFactor < 0.5) {
        $volatilityFactor = 0.5;
    }
    
    // Base Banker offer
    $baseOffer = $average * $volatilityFactor;
    
    // --- Banker's Strategic Offers: bluff / pressure / standard --- //
    $randomRoll = rand(1, 100);
    
    // Bluff: slightly lower than expected (acts conservative)
    if ($randomRoll <= 25 && $round >= 2) {
        $offerType = 'bluff_low';
        $baseOffer *= 0.85;
        $offerNote = 'Bluff Offer: The Banker is testing if you will accept a lowball deal.';
    }
    // Pressure: slightly higher than expected, but marked as "expiring"
    elseif ($randomRoll >= 76) {
        $offerType = 'pressure_high';
        $baseOffer *= 1.10;
        $offerNote = 'Pressure Offer: A generous, limited-time offer meant to push you into a quick decision.';
    }
    
    $offer = round($baseOffer, 2);
    
    // Build active offer with expiration metadata
    $expiresRound = ($offerType === 'pressure_high')
        ? $round + 1
        : $round + 5;
    
    $activeOffer = [
        'round' => $round,
        'amount' => $offer,
        'type' => $offerType,
        'expires_round' => $expiresRound
    ];
    
    // Store current offer and history
    $_SESSION['current_offer'] = $offer;
    $_SESSION['active_offer'] = $activeOffer;
    if (!isset($_SESSION['offer_history'])) {
        $_SESSION['offer_history'] = [];
    }
    $_SESSION['offer_history'][] = $activeOffer;
    
    $currentOffer = $offer;
    $totalRemaining = count($remainingCases) + ($_SESSION['player_case'] ? 1 : 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deal or No Deal - Game Board</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="game-page">
    <div class="container">
        <header>
            <h1 class="game-title">Deal or No Deal</h1>
        </header>
        
        <main class="game-board">
            <div class="game-info">
                <div class="info-item">
                    <div class="info-label">Round</div>
                    <div class="info-value">
                        <?php 
                            // Calculate current round based on eliminations
                            // Round 1: 0-5 eliminations, Round 2: 6-11 eliminations, etc.
                            $elimCount = count($_SESSION['eliminated']);
                            $currentRound = (int)($elimCount / 6) + 1;
                            // If showing an offer, we're at the end of a round (so show that round number)
                            if (isset($showOffer) && $showOffer) {
                                $currentRound = (int)($elimCount / 6);
                                if ($currentRound < 1) $currentRound = 1;
                            }
                            echo $currentRound;
                        ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Remaining Cases</div>
                    <div class="info-value">
                        <?php echo count($remainingCases) + ($_SESSION['player_case'] ? 1 : 0); ?>
                    </div>
                </div>
                <?php if ($_SESSION['player_case']): ?>
                <div class="info-item">
                    <div class="info-label">Your Case</div>
                    <div class="info-value">#<?php echo $_SESSION['player_case']; ?></div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="briefcases-grid">
                <?php for ($i = 1; $i <= 26; $i++): 
                    $isPlayerCase = ($i == $_SESSION['player_case']);
                    $isEliminated = in_array($i, $_SESSION['eliminated']);
                    $caseValue = $_SESSION['case_values'][$i];
                    $canSelect = !$isPlayerCase && !$isEliminated;
                ?>
                    <div class="briefcase <?php 
                        echo $isPlayerCase ? 'player-case ' : ''; 
                        echo $isEliminated ? 'eliminated flipped ' : '';
                    ?>">
                        <div class="briefcase-inner">
                            <div class="briefcase-front">
                                <?php if ($isPlayerCase): ?>
                                    <div>YOURS</div>
                                    <div style="font-size: 0.8rem; margin-top: 5px;">#<?php echo $i; ?></div>
                                <?php else: ?>
                                    #<?php echo $i; ?>
                                <?php endif; ?>
                            </div>
                            <div class="briefcase-back">
                                <?php if ($isEliminated): ?>
                                    <div style="text-align: center;">
                                        <div style="font-size: 0.9rem; margin-bottom: 5px;">Case #<?php echo $i; ?></div>
                                        <div style="font-size: 1.1rem; color: #ff6b6b;">
                                            $<?php echo number_format($caseValue, 2); ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div>?</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($canSelect): ?>
                            <a href="?select=<?php echo $i; ?>" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 10; text-decoration: none; color: transparent;"></a>
                        <?php endif; ?>
                    </div>
                <?php endfor; ?>
            </div>
            
            <div class="game-message">
                <?php if ($_SESSION['current_event']): ?>
                    <p style="margin-bottom: 10px; color: #f0c000;">
                        <?php echo htmlspecialchars($_SESSION['current_event']); ?>
                    </p>
                <?php endif; ?>

                <?php if (!empty($_SESSION['last_revealed_value'])): ?>
                    <p style="margin-bottom: 10px;">
                        Last revealed: Case #<?php echo htmlspecialchars($_SESSION['last_revealed_case']); ?> contained 
                        <strong>$<?php echo number_format($_SESSION['last_revealed_value'], 2); ?></strong>.
                    </p>
                <?php endif; ?>

                <?php if ($_SESSION['player_case'] === null): ?>
                    <p>Select your briefcase to keep!</p>
                <?php elseif (count($remainingCases) > 0): ?>
                    <p>Select a briefcase to eliminate and reveal its value.</p>
                <?php else: ?>
                    <p>All cases eliminated! Final results...</p>
                    <a href="result.php" class="button">View Results</a>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Offer Popup Modal -->
    <?php if ($showOffer && $currentOffer !== null): ?>
    <div id="offerModal" class="offer-modal" onclick="event.stopPropagation();">
        <div class="offer-modal-content" onclick="event.stopPropagation();">
            <div class="offer-header">
                <h2>The Banker's Offer</h2>
                <div class="countdown-timer" id="countdown">10</div>
            </div>
            
            <div class="offer-body">
                <p class="offer-intro">The Banker has analyzed the remaining cases and wants to make you an offer.</p>
                
                <div class="offer-amount">
                    <h1>$<?php echo number_format($currentOffer, 2); ?></h1>
                </div>
                
                <div class="offer-stats">
                    <p>Remaining cases: <?php echo $totalRemaining; ?> | 
                       Lowest: $<?php echo number_format($minRemaining, 2); ?> | 
                       Highest: $<?php echo number_format($maxRemaining, 2); ?></p>
                </div>
                
                <?php if ($_SESSION['player_case'] !== null): ?>
                    <p class="player-case-info">Your case: <strong>#<?php echo htmlspecialchars($_SESSION['player_case']); ?></strong> (still unopened)</p>
                <?php endif; ?>
                
                <div class="offer-type-info">
                    <p class="offer-type-label">
                        Offer Type: 
                        <?php
                            if ($offerType === 'standard') {
                                echo "Standard Offer";
                            } elseif ($offerType === 'bluff_low') {
                                echo "Bluff Offer (Conservative)";
                            } else {
                                echo "Pressure Offer (Aggressive & Time-Limited)";
                            }
                        ?>
                    </p>
                    <p class="offer-note"><?php echo htmlspecialchars($offerNote); ?></p>
                </div>
                
                <p class="offer-question">Do you take the deal... or keep playing?</p>
                
                <form method="POST" class="offer-buttons" id="offerForm">
                    <input type="hidden" name="offer_amount" value="<?php echo $currentOffer; ?>">
                    <button type="submit" name="decision" value="deal" class="button deal-button">
                        Deal
                    </button>
                    <button type="submit" name="decision" value="nodeal" class="button no-deal-button">
                        No Deal
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        <?php if ($showOffer && $currentOffer !== null): ?>
        // Show modal immediately
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('offerModal');
            if (modal) {
                modal.style.display = 'flex';
                
                // Start countdown
                let timeLeft = 10;
                const countdownElement = document.getElementById('countdown');
                const offerForm = document.getElementById('offerForm');
                
                const countdown = setInterval(function() {
                    timeLeft--;
                    countdownElement.textContent = timeLeft;
                    
                    // Change color when time is running out
                    if (timeLeft <= 3) {
                        countdownElement.style.background = 'linear-gradient(135deg, #ff0000 0%, #cc0000 100%)';
                        countdownElement.style.animation = 'pulse 0.5s ease-in-out infinite';
                    }
                    
                    if (timeLeft <= 0) {
                        clearInterval(countdown);
                        // Auto-select "No Deal" if time runs out
                        const noDealInput = document.createElement('input');
                        noDealInput.type = 'hidden';
                        noDealInput.name = 'decision';
                        noDealInput.value = 'nodeal';
                        offerForm.appendChild(noDealInput);
                        offerForm.submit();
                    }
                }, 1000);
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>
