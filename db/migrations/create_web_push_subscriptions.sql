-- Web Push Subscriptions table for browser-based push notifications
-- Run this once to enable web push for the website

CREATE TABLE IF NOT EXISTS web_push_subscriptions (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    endpoint    TEXT NOT NULL,
    p256dh      VARCHAR(255) NOT NULL,
    auth        VARCHAR(255) NOT NULL,
    user_agent  VARCHAR(500) DEFAULT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_seen   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_endpoint (endpoint(512))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
