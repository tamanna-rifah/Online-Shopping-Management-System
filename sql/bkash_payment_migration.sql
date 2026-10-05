ALTER TABLE `payments`
    ADD COLUMN `payment_id` varchar(100) DEFAULT NULL AFTER `method`,
    ADD COLUMN `trx_id` varchar(100) DEFAULT NULL AFTER `transaction_id`,
    ADD COLUMN `customer_name` varchar(191) DEFAULT NULL AFTER `amount`,
    ADD COLUMN `customer_phone` varchar(50) DEFAULT NULL AFTER `customer_name`,
    ADD COLUMN `customer_address` varchar(255) DEFAULT NULL AFTER `customer_phone`,
    ADD COLUMN `gateway_response` longtext DEFAULT NULL AFTER `customer_address`,
    ADD COLUMN `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() AFTER `created_at`,
    ADD KEY `payment_id` (`payment_id`),
    ADD KEY `trx_id` (`trx_id`);
