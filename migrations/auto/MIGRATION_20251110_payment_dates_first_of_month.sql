-- MIGRATION 2025-11-10: Payment Dates Always 1st of Month
-- Ensures all payment dates are normalized to the 1st of each month

-- 1. Update existing payment dates to 1st of month
UPDATE member_payment_status mps
JOIN users u ON u.id = mps.mitglied_id
SET 
    mps.gedeckt_bis = CASE
        WHEN mps.guthaben > 0 THEN 
            DATE_ADD(
                DATE_FORMAT(u.created_at, '%Y-%m-01'),
                INTERVAL FLOOR(mps.guthaben / mps.monatsbeitrag) MONTH
            )
        ELSE 
            DATE_FORMAT(u.created_at, '%Y-%m-01')
    END,
    mps.naechste_zahlung_faellig = CASE
        WHEN mps.guthaben > 0 THEN
            DATE_ADD(
                DATE_ADD(
                    DATE_FORMAT(u.created_at, '%Y-%m-01'),
                    INTERVAL FLOOR(mps.guthaben / mps.monatsbeitrag) MONTH
                ),
                INTERVAL 1 MONTH
            )
        ELSE
            DATE_ADD(DATE_FORMAT(u.created_at, '%Y-%m-01'), INTERVAL 1 MONTH)
    END
WHERE u.status = 'active';

-- 2. Create triggers to maintain 1st-of-month dates

DELIMITER //

-- Trigger: After Insert
DROP TRIGGER IF EXISTS after_transaction_insert//
CREATE TRIGGER after_transaction_insert 
AFTER INSERT ON transaktionen
FOR EACH ROW
BEGIN
    DECLARE v_monatsbeitrag DECIMAL(10,2);
    DECLARE v_total_guthaben DECIMAL(10,2);
    DECLARE v_monate_gedeckt INT;
    DECLARE v_user_created DATE;
    
    IF NEW.status = 'gebucht' THEN
        SELECT COALESCE(monatsbeitrag, 10.00) INTO v_monatsbeitrag 
        FROM member_payment_status 
        WHERE mitglied_id = NEW.mitglied_id;
        
        SELECT COALESCE(SUM(betrag), 0) INTO v_total_guthaben
        FROM transaktionen 
        WHERE mitglied_id = NEW.mitglied_id AND status = 'gebucht';
        
        SET v_monate_gedeckt = FLOOR(v_total_guthaben / v_monatsbeitrag);
        
        SELECT DATE_FORMAT(created_at, '%Y-%m-01') INTO v_user_created
        FROM users WHERE id = NEW.mitglied_id;
        
        UPDATE member_payment_status
        SET 
            guthaben = v_total_guthaben,
            gedeckt_bis = DATE_ADD(v_user_created, INTERVAL v_monate_gedeckt MONTH),
            naechste_zahlung_faellig = DATE_ADD(v_user_created, INTERVAL (v_monate_gedeckt + 1) MONTH),
            letzte_aktualisierung = NOW()
        WHERE mitglied_id = NEW.mitglied_id;
    END IF;
END//

-- Trigger: After Update
DROP TRIGGER IF EXISTS after_transaction_update//
CREATE TRIGGER after_transaction_update
AFTER UPDATE ON transaktionen
FOR EACH ROW
BEGIN
    DECLARE v_monatsbeitrag DECIMAL(10,2);
    DECLARE v_total_guthaben DECIMAL(10,2);
    DECLARE v_monate_gedeckt INT;
    DECLARE v_user_created DATE;
    
    IF NEW.status = 'gebucht' OR OLD.status = 'gebucht' THEN
        SELECT COALESCE(monatsbeitrag, 10.00) INTO v_monatsbeitrag 
        FROM member_payment_status 
        WHERE mitglied_id = NEW.mitglied_id;
        
        SELECT COALESCE(SUM(betrag), 0) INTO v_total_guthaben
        FROM transaktionen 
        WHERE mitglied_id = NEW.mitglied_id AND status = 'gebucht';
        
        SET v_monate_gedeckt = FLOOR(v_total_guthaben / v_monatsbeitrag);
        
        SELECT DATE_FORMAT(created_at, '%Y-%m-01') INTO v_user_created
        FROM users WHERE id = NEW.mitglied_id;
        
        UPDATE member_payment_status
        SET 
            guthaben = v_total_guthaben,
            gedeckt_bis = DATE_ADD(v_user_created, INTERVAL v_monate_gedeckt MONTH),
            naechste_zahlung_faellig = DATE_ADD(v_user_created, INTERVAL (v_monate_gedeckt + 1) MONTH),
            letzte_aktualisierung = NOW()
        WHERE mitglied_id = NEW.mitglied_id;
    END IF;
END//

-- Trigger: After Delete
DROP TRIGGER IF EXISTS after_transaction_delete//
CREATE TRIGGER after_transaction_delete
AFTER DELETE ON transaktionen
FOR EACH ROW
BEGIN
    DECLARE v_monatsbeitrag DECIMAL(10,2);
    DECLARE v_total_guthaben DECIMAL(10,2);
    DECLARE v_monate_gedeckt INT;
    DECLARE v_user_created DATE;
    
    IF OLD.status = 'gebucht' THEN
        SELECT COALESCE(monatsbeitrag, 10.00) INTO v_monatsbeitrag 
        FROM member_payment_status 
        WHERE mitglied_id = OLD.mitglied_id;
        
        SELECT COALESCE(SUM(betrag), 0) INTO v_total_guthaben
        FROM transaktionen 
        WHERE mitglied_id = OLD.mitglied_id AND status = 'gebucht';
        
        SET v_monate_gedeckt = FLOOR(v_total_guthaben / v_monatsbeitrag);
        
        SELECT DATE_FORMAT(created_at, '%Y-%m-01') INTO v_user_created
        FROM users WHERE id = OLD.mitglied_id;
        
        UPDATE member_payment_status
        SET 
            guthaben = v_total_guthaben,
            gedeckt_bis = DATE_ADD(v_user_created, INTERVAL v_monate_gedeckt MONTH),
            naechste_zahlung_faellig = DATE_ADD(v_user_created, INTERVAL (v_monate_gedeckt + 1) MONTH),
            letzte_aktualisierung = NOW()
        WHERE mitglied_id = OLD.mitglied_id;
    END IF;
END//

DELIMITER ;
