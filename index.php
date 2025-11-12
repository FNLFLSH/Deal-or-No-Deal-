<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deal or No Deal - Welcome</title>
    <link rel="stylesheet" href="style.css">
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
                    <li>Select one briefcase as your main case</li>
                    <li>Eliminate other briefcases to reveal their values</li>
                    <li>The Banker will make you an offer after each round</li>
                    <li>Decide: Deal or No Deal?</li>
                    <li>If you say "No Deal," continue eliminating cases</li>
                    <li>Win the value in your chosen briefcase or accept the Banker's final offer</li>
                </ol>
            </div>
            
            <form action="game.php" method="POST" class="start-form">
                <button type="submit" class="start-button">Start Game</button>
            </form>
        </main>
    </div>
</body>
</html>

