-- MIGRATION 2025-11-10: Automatic Monthly Fee Deduction
-- Creates a MySQL Event that automatically deducts the monthly fee on the 1st of each month

-- 1. Enable Event Scheduler
SET GLOBAL event_scheduler = ON;

-- 2. Create monthly deduction event
DELIMITER //

DROP EVENT IF EXISTS monthly_membership_fee_deduction//

CREATE EVENT monthly_membership_fee_deduction
ON SCHEDULE EVERY 1 MONTH
STARTS '2025-12-01 00:00:01'
DO
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_user_id INT;
    DECLARE v_user_name VARCHAR(255);
    DECLARE v_monatsbeitrag DECIMAL(10,2);
    DECLARE v_current_balance DECIMAL(10,2);
    
    -- Select all active users
    DECLARE user_cursor CURSOR FOR
        SELECT u.id, u.name, COALESCE(mps.monatsbeitrag, 10.00), COALESCE(mps.guthaben, 0.00)
        FROM users u
        LEFT JOIN member_payment_status mps ON mps.mitglied_id = u.id
        WHERE u.status = 'active';
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN user_cursor;
    
    read_loop: LOOP
        FETCH user_cursor INTO v_user_id, v_user_name, v_monatsbeitrag, v_current_balance;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- Create AUSZAHLUNG transaction (negative amount)
        -- This triggers the after_transaction_insert trigger which updates member_payment_status
        INSERT INTO transaktionen (
            mitglied_id,
            typ,
            betrag,
            beschreibung,
            status,
            datum
        ) VALUES (
            v_user_id,
            'AUSZAHLUNG',
            -v_monatsbeitrag,
            CONCAT('Monatsbeitrag ', DATE_FORMAT(NOW(), '%m/%Y')),
            'gebucht',
            NOW()
        );
        
    END LOOP;
    
    CLOSE user_cursor;
END//

DELIMITER ;

-- 3. Verify event was created
SELECT 
    EVENT_NAME,
    STARTS,
    INTERVAL_VALUE,
    INTERVAL_FIELD,
    STATUS,
    EVENT_COMMENT
FROM information_schema.EVENTS
WHERE EVENT_SCHEMA = 'pushingp' 
  AND EVENT_NAME = 'monthly_membership_fee_deduction';
