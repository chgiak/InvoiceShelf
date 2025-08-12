-- Add tax fields to expenses table
ALTER TABLE `expenses` 
ADD COLUMN `tax` BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER `amount`,
ADD COLUMN `base_tax` BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER `base_amount`,
ADD COLUMN `tax_per_item` VARCHAR(255) NOT NULL DEFAULT 'NO' AFTER `base_tax`,
ADD COLUMN `sales_tax_type` VARCHAR(255) NULL AFTER `tax_per_item`,
ADD COLUMN `sales_tax_address_type` VARCHAR(255) NULL AFTER `sales_tax_type`;

-- Add expense support to taxes table
ALTER TABLE `taxes` 
ADD COLUMN `expense_id` INT UNSIGNED NULL AFTER `estimate_id`,
ADD CONSTRAINT `taxes_expense_id_foreign` FOREIGN KEY (`expense_id`) REFERENCES `expenses` (`id`) ON DELETE CASCADE;

-- Create index for performance
CREATE INDEX `taxes_expense_id_index` ON `taxes` (`expense_id`);