/*
  # Rename TicketType entity to EventTicket

  1. Changes
    - Rename the entity class from TicketType to EventTicket
    - Keep the same database table name (ticket_type)
    - Update all references in the codebase
    
  2. Note
    - This is a code-only change, no database schema changes required
    - The table name remains 'ticket_type' for backward compatibility
*/

-- No actual database changes needed since we're keeping the same table name
-- This migration is just for documentation purposes
SELECT 'Migration for renaming TicketType entity to EventTicket completed.' AS message;