CREATE TABLE notification_push_deliveries (
    outbox_id BIGINT UNSIGNED NOT NULL,
    subscription_id BIGINT UNSIGNED NOT NULL,
    delivered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(outbox_id,subscription_id),
    CONSTRAINT fk_push_delivery_outbox FOREIGN KEY(outbox_id) REFERENCES notification_outbox(id),
    CONSTRAINT fk_push_delivery_subscription FOREIGN KEY(subscription_id) REFERENCES push_subscriptions(id) ON DELETE CASCADE
) ENGINE=InnoDB;
