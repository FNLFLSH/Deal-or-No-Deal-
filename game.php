<?php
session_start();

// Initialize game if starting fresh
if ($_SERVER['REQUEST_METHOD'] === 'POST' || !isset($_SESSION['game_started'])) {
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
    
    $_SESSION['player_case'] = null;
    $_SESSION['eliminated'] = [];
    $_SESSION['round'] = 1;
    $_SESSION['game_started'] = true;
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
            
            // After elimination, redirect to offer page
            // (For now, we'll continue on game.php until offer.php is fully implemented)
            // header('Location: offer.php');
            // exit;
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

// Calculate remaining values for banker algorithm
$remainingValues = [];
foreach ($remainingCases as $caseNum) {
    $remainingValues[] = $_SESSION['case_values'][$caseNum];
}
if ($_SESSION['player_case'] !== null) {
    $remainingValues[] = $_SESSION['case_values'][$_SESSION['player_case']];
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
            
            <?php if ($_SESSION['player_case'] === null): ?>
                <div class="game-message">
                    <p>Select your briefcase to keep!</p>
                </div>
            <?php elseif (count($_SESSION['eliminated']) > 0 && count($_SESSION['eliminated']) % 6 == 0): ?>
                <div class="game-message">
                    <p>Round complete! The Banker wants to make you an offer.</p>
                    <a href="offer.php" class="button">See Banker's Offer</a>
                </div>
            <?php elseif (count($remainingCases) > 0): ?>
                <div class="game-message">
                    <p>Select a briefcase to eliminate and reveal its value.</p>
                </div>
            <?php else: ?>
                <div class="game-message">
                    <p>All cases eliminated! Final results...</p>
                    <a href="result.php" class="button">View Results</a>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>

