-- =============================================================
-- seed.sql
-- Offer Router Recovery — Minimal Seed Data for First Local Run
-- =============================================================
-- Run AFTER schema.sql.
-- Provides: one admin user, one tag, one network, one active offer,
-- three sub-URLs with valid weight distribution summing to 100,
-- and three sample click records.
-- =============================================================

USE `efbhalvbhdsurl`;

-- =============================================================
-- Admin user
-- WARNING: password stored as plaintext — this is how the
-- application currently works. See AUTH.md and BLOCKERS.md.
-- Change this password after first login.
-- =============================================================
INSERT INTO `tbl_user` (`user_name`, `password`)
VALUES ('admin', 'admin123');

-- =============================================================
-- Sample tag
-- =============================================================
INSERT INTO `tbl_tag` (`tag_name`, `created_at`)
VALUES ('Sample Tag', CURDATE());

-- =============================================================
-- Sample network
-- =============================================================
INSERT INTO `tbl_network` (`network_name`, `created_at`)
VALUES ('Sample Network', CURDATE());

-- =============================================================
-- One active offer
-- offer_status = 1 (Active)
-- tag_id = 1 (references the tag inserted above)
-- network_id = 1 (references the network inserted above)
-- slug_name used as the routing key in: ?oid=sample-offer
-- =============================================================
INSERT INTO `tbl_offer_url`
    (`offer`, `slug_name`, `tag_id`, `note`, `network_id`,
     `offer_status`, `start_date`, `end_date`)
VALUES
    ('Sample Offer', 'sample-offer', 1, 'Seed data offer for local testing', 1,
     1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY));

-- =============================================================
-- Three sub-URLs for the offer above (main_offer_id = 1)
-- Weights: 60 + 30 + 10 = 100 (invariant satisfied)
-- All status = 'yes', deleted_status = 'no' (defaults)
-- =============================================================
INSERT INTO `tbl_sub_offer_url`
    (`main_offer_id`, `sub_url`, `weight`, `status`, `deleted_status`)
VALUES
    (1, 'https://www.example-destination-a.com/landing', 60, 'yes', 'no'),
    (1, 'https://www.example-destination-b.com/landing', 30, 'yes', 'no'),
    (1, 'https://www.example-destination-c.com/landing', 10, 'yes', 'no');

-- =============================================================
-- Sample click records (simulate three past routing events)
-- click_id values are example UUIDs — in production these come
-- from the caller's query string parameter
-- =============================================================
INSERT INTO `tbl_click`
    (`click_id`, `offer_id`, `sub_offer_id`, `ip_address`, `created_at`)
VALUES
    ('ck-seed-001', 1, 1, '192.168.1.101', DATE_SUB(CURDATE(), INTERVAL 2 DAY)),
    ('ck-seed-002', 1, 2, '192.168.1.102', DATE_SUB(CURDATE(), INTERVAL 1 DAY)),
    ('ck-seed-003', 1, 1, '192.168.1.103', CURDATE());

-- =============================================================
-- Pre-seed the report table for yesterday's clicks
-- (the cron job would normally create this row)
-- =============================================================
INSERT INTO `tbl_report`
    (`main_offer_id`, `main_offer_url`, `offer_clicks`, `report_date`)
VALUES
    (1, 'Sample Offer', 1, DATE_SUB(CURDATE(), INTERVAL 1 DAY));

-- =============================================================
-- Verification queries (run manually to confirm seeding)
-- =============================================================
-- SELECT * FROM tbl_user;
-- SELECT * FROM tbl_tag;
-- SELECT * FROM tbl_network;
-- SELECT * FROM tbl_offer_url;
-- SELECT * FROM tbl_sub_offer_url;
-- SELECT SUM(weight) AS weight_total FROM tbl_sub_offer_url
--   WHERE main_offer_id = 1 AND status = 'yes';   -- should be 100.0000
-- SELECT * FROM tbl_click;
-- SELECT * FROM tbl_report;
