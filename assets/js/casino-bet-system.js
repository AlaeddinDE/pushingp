/**
 * UNIFIED CASINO BET SYSTEM
 * Min: 0.01€, Max: 10.00€
 * Standardized bet input component for all games
 */

const CASINO_CONFIG = {
    MIN_BET: 0.01,
    MAX_BET: 10.00,
    MIN_BALANCE_RESERVE: 10.00,
    QUICK_BETS: [0.50, 1.00, 2.00, 5.00, 10.00]
};

/**
 * Create standardized bet input HTML
 */
function createBetInput(gamePrefix, defaultBet = 1.00) {
    return `
        <div class="bet-input-container" style="margin: 20px 0;">
            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-primary);">
                Einsatz
            </label>
            
            <!-- Custom Input -->
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                <input 
                    type="number" 
                    id="${gamePrefix}Bet" 
                    value="${defaultBet.toFixed(2)}" 
                    min="${CASINO_CONFIG.MIN_BET}" 
                    max="${CASINO_CONFIG.MAX_BET}" 
                    step="0.01" 
                    style="
                        flex: 1;
                        padding: 12px;
                        background: var(--bg-secondary);
                        border: 2px solid var(--border);
                        border-radius: 12px;
                        color: var(--text-primary);
                        font-size: 1.125rem;
                        font-weight: 700;
                        text-align: center;
                    "
                    onchange="validateBet('${gamePrefix}')"
                >
                <span style="font-size: 1.125rem; font-weight: 700; color: var(--text-secondary);">€</span>
            </div>
            
            <!-- Quick Bet Buttons -->
            <div class="quick-bet-grid" style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px;">
                ${CASINO_CONFIG.QUICK_BETS.map(amount => `
                    <button 
                        class="quick-bet-btn" 
                        onclick="setQuickBet('${gamePrefix}', ${amount})"
                        style="
                            padding: 10px;
                            background: var(--bg-tertiary);
                            border: 2px solid var(--border);
                            border-radius: 8px;
                            color: var(--text-primary);
                            font-weight: 700;
                            font-size: 0.875rem;
                            cursor: pointer;
                            transition: all 0.2s;
                        "
                        onmouseover="this.style.borderColor='var(--accent)'; this.style.transform='translateY(-2px)'"
                        onmouseout="this.style.borderColor='var(--border)'; this.style.transform='translateY(0)'"
                    >
                        ${amount.toFixed(2)}€
                    </button>
                `).join('')}
            </div>
            
            <!-- Min/Max Info -->
            <div style="margin-top: 8px; font-size: 0.75rem; color: var(--text-secondary); text-align: center;">
                Min: ${CASINO_CONFIG.MIN_BET.toFixed(2)}€ • Max: ${CASINO_CONFIG.MAX_BET.toFixed(2)}€
            </div>
        </div>
    `;
}

/**
 * Set quick bet amount
 */
function setQuickBet(gamePrefix, amount) {
    const input = document.getElementById(`${gamePrefix}Bet`);
    if (input) {
        input.value = amount.toFixed(2);
        
        // Highlight selected button
        document.querySelectorAll(`#${gamePrefix}Modal .quick-bet-btn`).forEach(btn => {
            btn.style.background = 'var(--bg-tertiary)';
            btn.style.borderColor = 'var(--border)';
        });
        
        event.target.style.background = 'linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(16, 185, 129, 0.2))';
        event.target.style.borderColor = 'var(--accent)';
    }
}

/**
 * Validate bet amount
 */
function validateBet(gamePrefix) {
    const input = document.getElementById(`${gamePrefix}Bet`);
    if (!input) return false;
    
    let bet = parseFloat(input.value);
    
    // Enforce limits
    if (bet < CASINO_CONFIG.MIN_BET) {
        bet = CASINO_CONFIG.MIN_BET;
        input.value = bet.toFixed(2);
    }
    if (bet > CASINO_CONFIG.MAX_BET) {
        bet = CASINO_CONFIG.MAX_BET;
        input.value = bet.toFixed(2);
    }
    
    // Check available balance
    const availableBalance = userBalance - CASINO_CONFIG.MIN_BALANCE_RESERVE;
    if (bet > availableBalance) {
        showNotification(`Nicht genug Guthaben! Verfügbar: ${availableBalance.toFixed(2)}€`, 'error');
        return false;
    }
    
    return true;
}

/**
 * Get bet amount from input
 */
function getBetAmount(gamePrefix) {
    const input = document.getElementById(`${gamePrefix}Bet`);
    if (!input) return 0;
    
    const bet = parseFloat(input.value);
    
    if (isNaN(bet) || bet < CASINO_CONFIG.MIN_BET || bet > CASINO_CONFIG.MAX_BET) {
        showNotification(`Einsatz muss zwischen ${CASINO_CONFIG.MIN_BET.toFixed(2)}€ und ${CASINO_CONFIG.MAX_BET.toFixed(2)}€ liegen!`, 'error');
        return 0;
    }
    
    const availableBalance = userBalance - CASINO_CONFIG.MIN_BALANCE_RESERVE;
    if (bet > availableBalance) {
        showNotification(`Nicht genug Guthaben! Verfügbar: ${availableBalance.toFixed(2)}€`, 'error');
        return 0;
    }
    
    return bet;
}

/**
 * Disable bet input during game
 */
function disableBetInput(gamePrefix) {
    const input = document.getElementById(`${gamePrefix}Bet`);
    if (input) input.disabled = true;
    
    document.querySelectorAll(`#${gamePrefix}Modal .quick-bet-btn`).forEach(btn => {
        btn.disabled = true;
        btn.style.opacity = '0.5';
        btn.style.cursor = 'not-allowed';
    });
}

/**
 * Enable bet input after game
 */
function enableBetInput(gamePrefix) {
    const input = document.getElementById(`${gamePrefix}Bet`);
    if (input) input.disabled = false;
    
    document.querySelectorAll(`#${gamePrefix}Modal .quick-bet-btn`).forEach(btn => {
        btn.disabled = false;
        btn.style.opacity = '1';
        btn.style.cursor = 'pointer';
    });
}
