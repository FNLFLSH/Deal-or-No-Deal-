<?php
session_start();

// If game was not started properly, send user home
if (!isset($_SESSION['game_started']) || !$_SESSION['game_started']) {
    header('Location: index.php');
    exit;
}

// Ensure structures exist
if (!isset($_SESSION['offer_history'])) {
    $_SESSION['offer_history'] = [];
}

$caseValues = $_SESSION['case_values'] ?? [];
$eliminated = $_SESSION['eliminated'] ?? [];
$playerCase = $_SESSION['player_case'] ?? null;
$round      = $_SESSION['round'] ?? 1;

// Build list of remaining case numbers (including player's case)
$remainingCases = [];
for ($i = 1; $i <= 26; $i++) {
    if (!in_array($i, $eliminated)) {
        $remainingCases[] = $i;
    }
}

// If somehow there's only one case left, go straight to results
if (count($remainingCases) <= 1) {
    header('Location: result.php');
    exit;
}

// Collect remaining values for Banker algorithm
$remainingValues = [];
foreach ($remainingCases as $caseNum) {
    if (isset($caseValues[$caseNum])) {
        $remainingValues[] = $caseValues[$caseNum];
    }
}

// Safety check
if (count($remainingValues) === 0) {
    header('Location: result.php');
    exit;
}

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
$offerType = 'standard';
$offerNote = 'Standard offer based on remaining values.';
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

// Build active offer with expiration metadata (for strategic pressure offers)
$expiresRound = ($offerType === 'pressure_high')
    ? $round + 1  // expires by next round
    : $round + 5; // effectively never reached in normal play, but tracked for spec

$activeOffer = [
    'round'         => $round,
    'amount'        => $offer,
    'type'          => $offerType,
    'expires_round' => $expiresRound
];

// Store current offer and history
$_SESSION['current_offer'] = $offer;
$_SESSION['active_offer']  = $activeOffer;
$_SESSION['offer_history'][] = $activeOffer;

// Handle player decision (Deal / No Deal)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['decision'])) {
    $decision = $_POST['decision'];

    if ($decision === 'deal') {
        // Player accepts deal and game ends
        $_SESSION['final_amount'] = $offer;
        $_SESSION['deal_taken']   = true;
        header('Location: result.php');
        exit;
    } else {
        // Player says "No Deal" – next round, back to game board
        $_SESSION['round'] = $round + 1;
        header('Location: game.php');
        exit;
    }
}

// Extra info for display
$totalRemaining = count($remainingCases);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deal or No Deal - Banker's Offer</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="offer-page">
    <div class="container">
        <header>
            <h1 class="game-title">Banker's Offer</h1>
            <p class="subtitle">Round <?php echo htmlspecialchars($round); ?> Negotiation</p>
        </header>

        <main class="offer-content">
            <div class="offer-panel">
                <p class="offer-text">
                    The Banker has analyzed the remaining cases and wants to make you an offer.
                </p>

                <h2 style="font-size: 2.5rem; margin-bottom: 10px;">
                    $<?php echo number_format($offer, 2); ?>
                </h2>
                <p style="margin-bottom: 15px; opacity: 0.9;">
                    Remaining cases: <?php echo $totalRemaining; ?> |
                    Lowest: $<?php echo number_format($minRemaining, 2); ?> |
                    Highest: $<?php echo number_format($maxRemaining, 2); ?>
                </p>

                <?php if ($playerCase !== null): ?>
                    <p style="margin-bottom: 10px;">
                        Your case: <strong>#<?php echo htmlspecialchars($playerCase); ?></strong> (still unopened)
                    </p>
                <?php endif; ?>

                <p style="margin-bottom: 10px; color: #f0c000;">
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
                <p style="margin-bottom: 20px; font-size: 0.95rem; opacity: 0.9;">
                    <?php echo htmlspecialchars($offerNote); ?>
                </p>

                <?php if ($offerType === 'pressure_high'): ?>
                    <p style="margin-bottom: 20px; font-size: 0.9rem;">
                        <em>This pressure offer is marked to expire after the next round of play.</em>
                    </p>
                <?php endif; ?>

                <p style="margin-bottom: 25px; font-weight: bold;">
                    Do you take the deal... or keep playing?
                </p>

                <form method="POST" class="offer-buttons">
                    <button type="submit" name="decision" value="deal" class="button">
                        Deal
                    </button>
                    <button type="submit" name="decision" value="nodeal" class="button">
                        No Deal
                    </button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
