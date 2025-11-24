<?php
ob_start(); // Start output buffering
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deal or No Deal - Welcome</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>
<body class="homepage">
    <div class="container">
        <header>
            <h1 class="game-title">Deal or No Deal</h1>
            <p class="subtitle">High-Stakes Negotiation with the Banker</p>
        </header>
        
        <main class="welcome-content">
            <div class="rules-section">
                <h2>How to Play</h2>
                <ol class="rules-list">
                    <li>Select one briefcase as your main case (you'll keep this until the end)</li>
                    <li>Eliminate 6 briefcases to reveal their values</li>
                    <li>After every 6 eliminations, the Banker will make you an offer with a 10-second countdown</li>
                    <li>Decide: Deal or No Deal?</li>
                    <li>If you say "No Deal," continue eliminating 6 more cases for the next offer</li>
                    <li>You'll receive approximately 4-5 offers throughout the game</li>
                    <li>Win the value in your chosen briefcase or accept the Banker's final offer</li>
                    <li>Watch out for market events that can change case values!</li>
                </ol>
            </div>
            
            <form action="game.php" method="POST" class="start-form">
                <input type="hidden" name="new_game" value="1">
                <button type="submit" class="start-button">Start Game</button>
            </form>
        </main>
    </div>
<?php ob_end_flush(); // End output buffering ?>
</body>
</html>

