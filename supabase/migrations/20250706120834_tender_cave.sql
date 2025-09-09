/*
  # Rename TicketType to EventTicketType

  1. Changes
    - Update entity class name from TicketType to EventTicketType
    - Keep the same table name 'ticket_type' for backward compatibility
    - Update references in other tables

  2. Security
    - No security changes needed as we're just renaming the entity class
*/

-- No actual database changes needed since we're keeping the same table name
-- This migration is just for documentation purposes
DELIMITER //
DO
BEGIN
    SELECT 'Migration for renaming TicketType entity to EventTicketType completed.' AS message;
END;
//
DELIMITER ;