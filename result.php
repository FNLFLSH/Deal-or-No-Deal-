<?php
session_start();

// If game not started, send user home
if (!isset($_SESSION['game_started']) || !$_SESSION['game_started']) {
    header('Location: index.php');
    exit;
}

// Handle replay request: reset session & go back to homepage
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['replay'])) {
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit;
}

// Pull session data
$caseValues = $_SESSION['case_values'] ?? [];
$eliminated = $_SESSION['eliminated'] ?? [];
$playerCase = $_SESSION['player_case'] ?? null;

$dealTaken   = $_SESSION['deal_taken']   ?? false;
$bankerOffer = $_SESSION['final_amount'] ?? null;

// Determine final amount and outcome message
if ($dealTaken && $bankerOffer !== null) {
    $finalAmount = $bankerOffer;
    $outcomeText = "You accepted the Banker's offer.";
} else {
    if ($playerCase !== null && isset($caseValues[$playerCase])) {
        $finalAmount = $caseValues[$playerCase];
        $outcomeText = "You played to the very end and walked away with whatever was in your case.";
    } else {
       
        $finalAmount = 0;
        $outcomeText = "The game ended unexpectedly, so no winnings are recorded.";
    }
}

// Player's original case value (for comparison)
$playerCaseValue = ($playerCase !== null && isset($caseValues[$playerCase]))
    ? $caseValues[$playerCase]
    : null;

// Max possible amount in the game (just for fun stats)
$maxValue = !empty($caseValues) ? max($caseValues) : 0;

// Determine if the player "beat the bank"
$comparisonText = "";
if ($dealTaken && $playerCaseValue !== null) {
    if ($bankerOffer > $playerCaseValue) {
        $comparisonText = "Great decision! The Banker paid you more than your case was worth.";
    } elseif ($bankerOffer < $playerCaseValue) {
        $comparisonText = "Oh no! Your case was worth more than the Banker's offer.";
    } else {
        $comparisonText = "Perfect tie! Your deal matched exactly what was in your case.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deal or No Deal - Results</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="result-page">
    <div class="container">
        <header>
            <h1 class="game-title">Game Results</h1>
        </header>

        <main class="result-content">
            <div class="result-panel">
                <p class="result-text">
                    <?php echo htmlspecialchars($outcomeText); ?>
                </p>

                <h2 style="font-size: 2.5rem; margin: 15px 0;">
                    Final Winnings: $<?php echo number_format($finalAmount, 2); ?>
                </h2>

                <?php if ($playerCase !== null && $playerCaseValue !== null): ?>
                    <p style="margin-bottom: 10px;">
                        Your original case was <strong>#<?php echo htmlspecialchars($playerCase); ?></strong>
                        and it contained
                        <strong>$<?php echo number_format($playerCaseValue, 2); ?></strong>.
                    </p>
                <?php endif; ?>

                <?php if (!empty($comparisonText)): ?>
                    <p style="margin-bottom: 15px; color: #f0c000;">
                        <?php echo htmlspecialchars($comparisonText); ?>
                    </p>
                <?php endif; ?>

                <p style="margin-bottom: 25px; opacity: 0.9;">
                    Highest amount in the game was:
                    <strong>$<?php echo number_format($maxValue, 2); ?></strong>
                </p>

                <h3 style="margin-bottom: 15px;">All Briefcase Values</h3>

                <div class="briefcases-grid" style="margin-bottom: 25px;">
                    <?php for ($i = 1; $i <= 26; $i++): 
                        $isPlayerCase = ($i === $playerCase);
                        $isEliminated = in_array($i, $eliminated);
                        $value = isset($caseValues[$i]) ? $caseValues[$i] : 0;

                        // Highlight the "winning" case if no deal was taken
                        $extraClass = "";
                        if (!$dealTaken && $isPlayerCase) {
                            $extraClass = " winning";
                        }
                    ?>
                        <div class="briefcase flipped <?php echo $isPlayerCase ? 'player-case ' : ''; ?><?php echo $extraClass; ?>">
                            <div class="briefcase-inner">
                                <div class="briefcase-front">
                                    #<?php echo $i; ?>
                                </div>
                                <div class="briefcase-back">
                                    <div style="text-align: center;">
                                        <div style="font-size: 0.9rem; margin-bottom: 5px;">
                                            Case #<?php echo $i; ?>
                                        </div>
                                        <div style="font-size: 1.1rem; color: #f0c000;">
                                            $<?php echo number_format($value, 2); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>

                <form method="POST" class="result-buttons">
                    <button type="submit" name="replay" class="button">
                        Play Again
                    </button>
                    <a href="index.php" class="button">Back to Home</a>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
