<?php
session_start();

// If game was not started properly, send user home
if (!isset($_SESSION['game_started']) || !$_SESSION['game_started']) {
    header('Location: index.php');
    exit;
}

// Pull session data
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

// Banker algorithm: offer = average remaining * volatility factor
$average = array_sum($remainingValues) / count($remainingValues);

// Volatility factor increases slightly each round (early offers are lower)
$factor = 0.6 + ($round * 0.1); // Round 1 ≈ 0.7, Round 2 ≈ 0.8, etc.
if ($factor > 0.95) {
    $factor = 0.95;
}

$offer = round($average * $factor, 2);

// Store current offer in session (for reference in results if needed)
$_SESSION['current_offer'] = $offer;

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
$minRemaining = min($remainingValues);
$maxRemaining = max($remainingValues);
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
                <p style="margin-bottom: 20px; opacity: 0.9;">
                    Remaining cases: <?php echo $totalRemaining; ?> |
                    Lowest: $<?php echo number_format($minRemaining, 2); ?> |
                    Highest: $<?php echo number_format($maxRemaining, 2); ?>
                </p>

                <?php if ($playerCase !== null): ?>
                    <p style="margin-bottom: 20px;">
                        Your case: <strong>#<?php echo htmlspecialchars($playerCase); ?></strong> (still unopened)
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
