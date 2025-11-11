-- MIGRATION 2025-11-11: Mines Casino Game
-- Kein Schema-Update nötig - verwendet existierende casino_history Tabelle
-- Game Type: 'mines'
-- Result Data JSON Format:
--   {
--     "mines": 3,
--     "revealed": 5,
--     "multiplier": 2.457
--   }

-- Verify casino_history table exists
SELECT 'Mines Game nutzt existierende casino_history Tabelle' as info;

-- Game wird über Session-Daten verwaltet ($_SESSION['mines_game'])
-- Keine zusätzlichen Tabellen erforderlich
