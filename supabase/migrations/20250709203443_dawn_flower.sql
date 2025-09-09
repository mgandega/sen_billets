/*
  # Fix PayDunya namespace case sensitivity

  1. Changes
    - Update the namespace import from PayDunya\DirectPay\DirectPay to Paydunya\DirectPay\DirectPay
    - Fix case sensitivity issue in the namespace

  2. Note
    - This is a code-only change, no database schema changes required
    - The PayDunya PHP library uses lowercase 'paydunya' in its namespace
*/

-- No actual database changes needed
SELECT 'Migration for fixing PayDunya namespace case sensitivity completed.' AS message;