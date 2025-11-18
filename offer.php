<?php
session_start();
if (!isset($_SESSION['game_started'])) {
    header('Location: index.php');
    exit;
}

$caseValues = $_SESSION['case_values'] ?? [];
$eliminated = $_SESSION['eliminated'] ?? [];
$playerCase = $_SESSION['player_case'] ?? null;
$round = $_SESSION['round'] ?? 1;

// Get remaining values
$remainingValues = [];
foreach ($caseValues as $i => $v) {
    if (!in_array($i, $eliminated)) {
        $remainingValues[] = $v;
    }
}

$average = array_sum($remainingValues) / count($remainingValues);
$baseOffer = $average * (0.6 + ($round * 0.1));

$bankerMessage = "🤔 Banker is analyzing your tactics...";

// --- STRATEGIC OFFER SYSTEM ---
$bluffChance = rand(1, 10);
if ($bluffChance <= 2) {
    $offer = $baseOffer * 0.65;
    $bankerMessage = "😈 Banker tried to bluff you with a LOW offer!";
} elseif ($round > 3) {
    $offer = $baseOffer * 1.25;
    $bankerMessage = "📈 Banker is worried and increased the offer!";
} else {
    $offer = $baseOffer;
}

$offer = number_format($offer, 2);
$_SESSION['current_offer'] = $offer;

// Handle decision
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['decision'])) {
    if ($_POST['decision'] === 'deal') {
        $_SESSION['final_amount'] = $offer;
        $_SESSION['deal_taken'] = true;
        header('Location: result.php');
        exit;
    } else {
        $_SESSION['round']++;
        header('Location: game.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Banker's Offer</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="offer-page">
    <div class="offer-panel">
        <h1>Banker's Offer</h1>
        <h2>$<?php echo $offer; ?></h2>
        <p style="font-style: italic;"><?php echo $bankerMessage; ?></p>

        <form method="POST">
            <button class="button" name="decision" value="deal">Deal</button>
            <button class="button" name="decision" value="nodeal">No Deal</button>
        </form>
    </div>
</body>
</html>
