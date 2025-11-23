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

$caseValues   = $_SESSION['case_values'] ?? [];
$eliminated   = $_SESSION['eliminated'] ?? [];
$playerCase   = $_SESSION['player_case'] ?? null;
$dealTaken    = $_SESSION['deal_taken']   ?? false;
$bankerOffer  = $_SESSION['final_amount'] ?? null;
$offerHistory = $_SESSION['offer_history'] ?? [];
$marketEvents = $_SESSION['market_events'] ?? [];
$round = $_SESSION['round'] ?? 1;

// Determine final amount and outcome message
if ($dealTaken && $bankerOffer !== null) {
    $finalAmount = $bankerOffer;
    $outcomeText = "You accepted the Banker's offer.";
} else {
    if ($playerCase !== null && isset($caseValues[$playerCase])) {
        $finalAmount = $caseValues[$playerCase];
        $outcomeText = "You played to the very end and walked away with whatever was in your case.";
    } else {
        // Fallback (should not normally happen)
        $finalAmount = 0;
        $outcomeText = "The game ended unexpectedly, so no winnings are recorded.";
    }
}

// Player's original case value (for comparison)
$playerCaseValue = ($playerCase !== null && isset($caseValues[$playerCase]))
    ? $caseValues[$playerCase]
    : null;

// Max possible amount in the game (for stats)
$maxValue = !empty($caseValues) ? max($caseValues) : 0;

// Calculate decision analysis for deals
$decisionResult = null; // 'good', 'bad', 'neutral'
$difference = 0;
$percentageDiff = 0;
$acceptedRound = null;

if ($dealTaken && $playerCaseValue !== null && $bankerOffer !== null) {
    $difference = $bankerOffer - $playerCaseValue;
    $percentageDiff = $playerCaseValue > 0 ? (($difference / $playerCaseValue) * 100) : 0;
    
    if ($bankerOffer > $playerCaseValue) {
        $decisionResult = 'good';
    } elseif ($bankerOffer < $playerCaseValue) {
        $decisionResult = 'bad';
    } else {
        $decisionResult = 'neutral';
    }
    
    // Find which round the deal was accepted
    foreach ($offerHistory as $entry) {
        if ($entry['amount'] == $bankerOffer) {
            $acceptedRound = $entry['round'];
            break;
        }
    }
    if ($acceptedRound === null && !empty($offerHistory)) {
        $acceptedRound = end($offerHistory)['round'];
    }
}

// Calculate remaining cases at time of deal (for what-if)
$remainingAtDeal = [];
if ($dealTaken) {
    for ($i = 1; $i <= 26; $i++) {
        if ($i != $playerCase && !in_array($i, $eliminated)) {
            $remainingAtDeal[] = $i;
        }
    }
    if ($playerCase !== null) {
        $remainingAtDeal[] = $playerCase;
    }
}

// Calculate what-if scenarios
$whatIfValues = [];
if ($dealTaken && !empty($remainingAtDeal)) {
    foreach ($remainingAtDeal as $caseNum) {
        if (isset($caseValues[$caseNum])) {
            $whatIfValues[] = $caseValues[$caseNum];
        }
    }
    sort($whatIfValues);
    $whatIfBest = !empty($whatIfValues) ? max($whatIfValues) : 0;
    $whatIfWorst = !empty($whatIfValues) ? min($whatIfValues) : 0;
    $whatIfAverage = !empty($whatIfValues) ? array_sum($whatIfValues) / count($whatIfValues) : 0;
} else {
    $whatIfBest = 0;
    $whatIfWorst = 0;
    $whatIfAverage = 0;
}

// Game statistics
$totalRounds = $round;
$casesEliminated = count($eliminated);
$totalOffers = count($offerHistory);
$totalEvents = count($marketEvents);
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
        <?php if ($dealTaken): ?>
            <!-- DEAL ACCEPTED - Enhanced View -->
            <header class="result-header">
                <h1 class="game-title result-title <?php echo $decisionResult === 'good' ? 'celebration' : ($decisionResult === 'bad' ? 'commiseration' : ''); ?>">
                    <?php if ($decisionResult === 'good'): ?>
                        🎉 DEAL ACCEPTED! 🎉
                    <?php elseif ($decisionResult === 'bad'): ?>
                        Deal Accepted
                    <?php else: ?>
                        Deal Accepted
                    <?php endif; ?>
                </h1>
            </header>

            <main class="result-content">
                <!-- Hero Section: Offer Amount -->
                <div class="result-hero">
                    <div class="hero-offer">
                        <p class="hero-label">You took the Banker's offer:</p>
                        <h2 class="hero-amount">$<?php echo number_format($bankerOffer, 2); ?></h2>
                        <?php if ($acceptedRound): ?>
                            <p class="hero-round">Round <?php echo $acceptedRound; ?> Offer</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- The Reveal: What was in your case -->
                <div class="reveal-section">
                    <h3 class="section-title">The Reveal</h3>
                    <div class="reveal-case">
                        <div class="reveal-briefcase">
                            <div class="briefcase <?php echo $playerCase ? 'player-case' : ''; ?> reveal-animation">
                                <div class="briefcase-inner flipped">
                                    <div class="briefcase-front">
                                        #<?php echo $playerCase; ?>
                                    </div>
                                    <div class="briefcase-back">
                                        <div style="text-align: center; padding: 20px;">
                                            <div style="font-size: 0.9rem; margin-bottom: 10px;">Case #<?php echo $playerCase; ?></div>
                                            <div style="font-size: 1.5rem; color: #f0c000; font-weight: bold;">
                                                $<?php echo number_format($playerCaseValue, 2); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p class="reveal-text">Your Case #<?php echo $playerCase; ?> contained:</p>
                        <h3 class="reveal-amount">$<?php echo number_format($playerCaseValue, 2); ?></h3>
                    </div>
                </div>

                <!-- Decision Analysis -->
                <div class="decision-analysis <?php echo $decisionResult; ?>">
                    <h3 class="section-title">Decision Analysis</h3>
                    <div class="analysis-content">
                        <?php if ($decisionResult === 'good'): ?>
                            <div class="analysis-icon good">✓</div>
                            <p class="analysis-message good">Great Decision!</p>
                            <p class="analysis-detail">The Banker paid you <strong>$<?php echo number_format(abs($difference), 2); ?></strong> more than your case was worth.</p>
                            <p class="analysis-percentage">That's <strong><?php echo number_format(abs($percentageDiff), 1); ?>%</strong> more than your case value!</p>
                        <?php elseif ($decisionResult === 'bad'): ?>
                            <div class="analysis-icon bad">✗</div>
                            <p class="analysis-message bad">Oh No!</p>
                            <p class="analysis-detail">Your case was worth <strong>$<?php echo number_format(abs($difference), 2); ?></strong> more than the Banker's offer.</p>
                            <p class="analysis-percentage">You missed out on <strong><?php echo number_format(abs($percentageDiff), 1); ?>%</strong> more value.</p>
                        <?php else: ?>
                            <div class="analysis-icon neutral">=</div>
                            <p class="analysis-message neutral">Perfect Tie!</p>
                            <p class="analysis-detail">Your deal matched exactly what was in your case.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Side-by-Side Comparison -->
                <div class="comparison-section">
                    <h3 class="section-title">Comparison</h3>
                    <div class="comparison-grid">
                        <div class="comparison-card offer-card">
                            <div class="comparison-label">Banker's Offer</div>
                            <div class="comparison-amount">$<?php echo number_format($bankerOffer, 2); ?></div>
                            <div class="comparison-subtitle">What you took</div>
                        </div>
                        <div class="comparison-card case-card">
                            <div class="comparison-label">Your Case Value</div>
                            <div class="comparison-amount">$<?php echo number_format($playerCaseValue, 2); ?></div>
                            <div class="comparison-subtitle">What you had</div>
                        </div>
                    </div>
                    <div class="comparison-difference <?php echo $decisionResult; ?>">
                        <span>Difference: </span>
                        <strong>$<?php echo number_format($difference, 2); ?></strong>
                        <?php if ($difference > 0): ?>
                            <span class="diff-arrow">↑</span>
                        <?php elseif ($difference < 0): ?>
                            <span class="diff-arrow">↓</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Offer Journey Timeline -->
                <?php if (!empty($offerHistory)): ?>
                <div class="timeline-section">
                    <h3 class="section-title">Your Offer Journey</h3>
                    <div class="offer-timeline">
                        <?php foreach ($offerHistory as $index => $entry): 
                            $isAccepted = ($entry['amount'] == $bankerOffer);
                            $offerTypeClass = $entry['type'] === 'bluff_low' ? 'bluff' : ($entry['type'] === 'pressure_high' ? 'pressure' : 'standard');
                        ?>
                            <div class="timeline-item <?php echo $isAccepted ? 'accepted' : ''; ?> <?php echo $offerTypeClass; ?>">
                                <div class="timeline-marker">
                                    <?php if ($isAccepted): ?>
                                        <span class="marker-icon">✓</span>
                                    <?php else: ?>
                                        <span class="marker-number"><?php echo $entry['round']; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="timeline-content">
                                    <div class="timeline-round">Round <?php echo $entry['round']; ?></div>
                                    <div class="timeline-amount">$<?php echo number_format($entry['amount'], 2); ?></div>
                                    <div class="timeline-type">
                                        <?php
                                            if ($entry['type'] === 'standard') {
                                                echo "Standard Offer";
                                            } elseif ($entry['type'] === 'bluff_low') {
                                                echo "Bluff Offer";
                                            } else {
                                                echo "Pressure Offer";
                                            }
                                        ?>
                                    </div>
                                    <?php if ($isAccepted): ?>
                                        <div class="timeline-accepted">✓ Accepted</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- What-If Calculator -->
                <?php if ($dealTaken && !empty($whatIfValues)): ?>
                <div class="whatif-section">
                    <h3 class="section-title">What If You Had Continued?</h3>
                    <p class="whatif-intro">When you accepted the deal, there were <strong><?php echo count($remainingAtDeal); ?></strong> cases remaining.</p>
                    <div class="whatif-grid">
                        <div class="whatif-card best">
                            <div class="whatif-label">Best Case</div>
                            <div class="whatif-amount">$<?php echo number_format($whatIfBest, 2); ?></div>
                            <div class="whatif-diff">
                                <?php 
                                    $bestDiff = $whatIfBest - $bankerOffer;
                                    if ($bestDiff > 0) {
                                        echo "+$" . number_format($bestDiff, 2) . " more";
                                    } else {
                                        echo "Same or less";
                                    }
                                ?>
                            </div>
                        </div>
                        <div class="whatif-card average">
                            <div class="whatif-label">Average</div>
                            <div class="whatif-amount">$<?php echo number_format($whatIfAverage, 2); ?></div>
                            <div class="whatif-diff">
                                <?php 
                                    $avgDiff = $whatIfAverage - $bankerOffer;
                                    if ($avgDiff > 0) {
                                        echo "+$" . number_format($avgDiff, 2) . " more";
                                    } elseif ($avgDiff < 0) {
                                        echo "$" . number_format(abs($avgDiff), 2) . " less";
                                    } else {
                                        echo "Same";
                                    }
                                ?>
                            </div>
                        </div>
                        <div class="whatif-card worst">
                            <div class="whatif-label">Worst Case</div>
                            <div class="whatif-amount">$<?php echo number_format($whatIfWorst, 2); ?></div>
                            <div class="whatif-diff">
                                <?php 
                                    $worstDiff = $whatIfWorst - $bankerOffer;
                                    if ($worstDiff < 0) {
                                        echo "$" . number_format(abs($worstDiff), 2) . " less";
                                    } else {
                                        echo "Same or more";
                                    }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Game Statistics -->
                <div class="stats-section">
                    <h3 class="section-title">Game Statistics</h3>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $totalRounds; ?></div>
                            <div class="stat-label">Rounds Played</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $casesEliminated; ?></div>
                            <div class="stat-label">Cases Eliminated</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $totalOffers; ?></div>
                            <div class="stat-label">Offers Received</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $totalEvents; ?></div>
                            <div class="stat-label">Market Events</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">$<?php echo number_format($maxValue, 0); ?></div>
                            <div class="stat-label">Highest Amount</div>
                        </div>
                    </div>
                </div>

                <!-- Market Events -->
                <?php if (!empty($marketEvents)): ?>
                <div class="events-section">
                    <h3 class="section-title">Market Events</h3>
                    <div class="events-list">
                        <?php foreach ($marketEvents as $event): ?>
                            <div class="event-item <?php echo $event['type'] === 'crash' ? 'crash' : 'boom'; ?>">
                                <span class="event-round">Round <?php echo htmlspecialchars($event['round']); ?>:</span>
                                <span class="event-description"><?php echo htmlspecialchars($event['description']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- All Briefcase Values -->
                <div class="all-cases-section">
                    <h3 class="section-title">All Briefcase Values</h3>
                    <div class="briefcases-grid">
                        <?php for ($i = 1; $i <= 26; $i++): 
                            $isPlayerCase = ($i === $playerCase);
                            $isEliminated = in_array($i, $eliminated);
                            $value = isset($caseValues[$i]) ? $caseValues[$i] : 0;
                        ?>
                            <div class="briefcase flipped <?php echo $isPlayerCase ? 'player-case ' : ''; ?>">
                                <div class="briefcase-inner">
                                    <div class="briefcase-front">
                                        #<?php echo $i; ?>
                                    </div>
                                    <div class="briefcase-back">
                                        <div style="text-align: center;">
                                            <div style="font-size: 0.9rem; margin-bottom: 5px;">Case #<?php echo $i; ?></div>
                                            <div style="font-size: 1.1rem; color: #f0c000;">
                                                $<?php echo number_format($value, 2); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="result-actions">
                    <form method="POST" class="result-buttons">
                        <button type="submit" name="replay" class="button replay-button">
                            Play Again
                        </button>
                        <a href="index.php" class="button home-button">Back to Home</a>
                    </form>
                </div>
            </main>

        <?php else: ?>
            <!-- NO DEAL - Played to the end (keep original simpler view) -->
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

                    <p style="margin-bottom: 25px; opacity: 0.9;">
                        Highest amount in the game was:
                        <strong>$<?php echo number_format($maxValue, 2); ?></strong>
                    </p>

                    <?php if (!empty($marketEvents)): ?>
                        <h3 style="margin-bottom: 10px;">Market Events</h3>
                        <ul style="margin-bottom: 20px; text-align: left; max-width: 500px; margin-left: auto; margin-right: auto;">
                            <?php foreach ($marketEvents as $event): ?>
                                <li>
                                    Round <?php echo htmlspecialchars($event['round']); ?>:
                                    <?php echo htmlspecialchars($event['description']); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if (!empty($offerHistory)): ?>
                        <h3 style="margin-bottom: 10px;">Banker's Offer History</h3>
                        <table style="margin: 0 auto 25px auto; border-collapse: collapse; font-size: 0.95rem;">
                            <tr>
                                <th style="border-bottom: 1px solid #fff; padding: 5px 10px;">Round</th>
                                <th style="border-bottom: 1px solid #fff; padding: 5px 10px;">Amount</th>
                                <th style="border-bottom: 1px solid #fff; padding: 5px 10px;">Type</th>
                            </tr>
                            <?php foreach ($offerHistory as $entry): ?>
                                <tr>
                                    <td style="padding: 4px 10px; text-align: center;">
                                        <?php echo htmlspecialchars($entry['round']); ?>
                                    </td>
                                    <td style="padding: 4px 10px; text-align: center;">
                                        $<?php echo number_format($entry['amount'], 2); ?>
                                    </td>
                                    <td style="padding: 4px 10px; text-align: center;">
                                        <?php
                                            if ($entry['type'] === 'standard') {
                                                echo "Standard";
                                            } elseif ($entry['type'] === 'bluff_low') {
                                                echo "Bluff";
                                            } else {
                                                echo "Pressure";
                                            }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php endif; ?>

                    <h3 style="margin-bottom: 15px;">All Briefcase Values</h3>

                    <div class="briefcases-grid" style="margin-bottom: 25px;">
                        <?php for ($i = 1; $i <= 26; $i++): 
                            $isPlayerCase = ($i === $playerCase);
                            $isEliminated = in_array($i, $eliminated);
                            $value = isset($caseValues[$i]) ? $caseValues[$i] : 0;
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
                                            <div style="font-size: 0.9rem; margin-bottom: 5px;">Case #<?php echo $i; ?></div>
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
        <?php endif; ?>
    </div>
</body>
</html>
