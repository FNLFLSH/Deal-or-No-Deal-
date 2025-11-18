<?php
session_start();

// Initialize game if starting fresh
if ($_SERVER['REQUEST_METHOD'] === 'POST' || !isset($_SESSION['game_started'])) {
    $values = [
        0.01, 1, 5, 10, 25, 50, 75, 100, 200, 300, 400, 500, 750,
        1000, 5000, 10000, 25000, 50000, 75000, 100000, 200000,
        300000, 400000, 500000, 750000, 1000000
    ];
    
    shuffle($values);
    $_SESSION['case_values'] = [];
    for ($i = 1; $i <= 26; $i++) {
        $_SESSION['case_values'][$i] = $values[$i - 1];
    }

    $_SESSION['player_case'] = null;
    $_SESSION['eliminated'] = [];
    $_SESSION['round'] = 1;
    $_SESSION['game_started'] = true;
    $_SESSION['market_message'] = null;
}

// Handle case selection
if (isset($_GET['select']) && is_numeric($_GET['select'])) {
    $caseNum = (int)$_GET['select'];
    
    if ($caseNum >= 1 && $caseNum <= 26) {
        if ($_SESSION['player_case'] === null) {
            $_SESSION['player_case'] = $caseNum;
        } else if ($caseNum != $_SESSION['player_case'] && !in_array($caseNum, $_SESSION['eliminated'])) {
            $_SESSION['eliminated'][] = $caseNum;
        }
    }
}

// Remaining cases
$remainingCases = [];
for ($i = 1; $i <= 26; $i++) {
    if ($i != $_SESSION['player_case'] && !in_array($i, $_SESSION['eliminated'])) {
        $remainingCases[] = $i;
    }
}

// Remaining values
$remainingValues = [];
foreach ($remainingCases as $caseNum) {
    $remainingValues[] = $_SESSION['case_values'][$caseNum];
}
if ($_SESSION['player_case'] !== null) {
    $remainingValues[] = $_SESSION['case_values'][$_SESSION['player_case']];
}

// ---------------- VOLATILE MARKET EVENTS ----------------
if (count($_SESSION['eliminated']) > 0 && count($_SESSION['eliminated']) % 6 == 0 && !isset($_SESSION['market_event_triggered'])) {

    $_SESSION['market_event_triggered'] = true;

    $event = rand(1, 3);
    switch ($event) {
        case 1:
            $_SESSION['market_message'] = "📉 Market Crash! All values drop by 10%!";
            foreach ($_SESSION['case_values'] as &$v) $v *= 0.90;
            break;
        case 2:
            $_SESSION['market_message'] = "📈 Market Surge! All values rise by 15%!";
            foreach ($_SESSION['case_values'] as &$v) $v *= 1.15;
            break;
        case 3:
            $_SESSION['market_message'] = "🔀 Market Shuffle! Values randomized again!";
            shuffle($_SESSION['case_values']);
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
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
                    <div class="info-value"><?php echo $_SESSION['round']; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Remaining Cases</div>
                    <div class="info-value"><?php echo count($remainingCases) + ($_SESSION['player_case'] ? 1 : 0); ?></div>
                </div>
                <?php if ($_SESSION['player_case']): ?>
                <div class="info-item">
                    <div class="info-label">Your Case</div>
                    <div class="info-value">#<?php echo $_SESSION['player_case']; ?></div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Market Message -->
            <?php if (isset($_SESSION['market_message'])): ?>
                <div class="game-message">
                    <p><?php echo $_SESSION['market_message']; ?></p>
                </div>
                <?php unset($_SESSION['market_message']); unset($_SESSION['market_event_triggered']); ?>
            <?php endif; ?>

            <div class="briefcases-grid">
                <?php for ($i = 1; $i <= 26; $i++): 
                    $isPlayer = ($i == $_SESSION['player_case']);
                    $isEliminated = in_array($i, $_SESSION['eliminated']);
                    $value = $_SESSION['case_values'][$i];
                    $canSelect = !$isPlayer && !$isEliminated;
                ?>
                    <div class="briefcase <?php echo $isPlayer ? 'player-case' : ''; ?> <?php echo $isEliminated ? 'eliminated flipped' : ''; ?>">
                        <div class="briefcase-inner">
                            <div class="briefcase-front">
                                <?php echo $isPlayer ? "YOURS (#$i)" : "#$i"; ?>
                            </div>

                            <div class="briefcase-back">
                                <?php if ($isEliminated): ?>
                                    <!-- Progressive Value Revelation -->
                                    <?php 
                                        $low = number_format($value * 0.7, 2);
                                        $high = number_format($value * 1.3, 2);
                                    ?>
                                    <div style="text-align:center;">
                                        <p>Case #<?php echo $i; ?></p>
                                        <p>May contain:</p>
                                        <strong>$<?php echo "$low - $$high"; ?></strong>
                                    </div>
                                <?php else: ?>
                                    <div>?</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($canSelect): ?>
                            <a href="?select=<?php echo $i; ?>" style="position:absolute;top:0;left:0;width:100%;height:100%;"></a>
                        <?php endif; ?>
                    </div>
                <?php endfor; ?>
            </div>

            <?php if ($_SESSION['player_case'] === null): ?>
                <center><p>Select your briefcase to keep!</p></center>
            <?php elseif (count($_SESSION['eliminated']) > 0 && count($_SESSION['eliminated']) % 6 == 0): ?>
                <center>
                    <p>Round ended — Banker is calling!</p>
                    <a href="offer.php" class="button">See Offer</a>
                </center>
            <?php else: ?>
                <center><p>Select a case to eliminate.</p></center>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
