CREATE TABLE `organizations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_organizations_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `organizations` (`name`, `slug`) VALUES ('Samadhan', 'samadhan');

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `organization_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','admin','support') NOT NULL DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_users_organization_role` (`organization_id`,`role`),
  UNIQUE KEY `email` (`email`),
  CONSTRAINT `fk_users_organization`
    FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `organization_memberships` (
  `organization_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('user','admin','support') NOT NULL DEFAULT 'user',
  `access_code_hash` char(64) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`organization_id`, `user_id`),
  KEY `idx_organization_memberships_user` (`user_id`, `organization_id`),
  UNIQUE KEY `uq_organization_memberships_access_code` (`access_code_hash`),
  CONSTRAINT `fk_organization_memberships_organization`
    FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_organization_memberships_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `tickets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `organization_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `assigned_to_user_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(25) DEFAULT NULL,
  `subject` varchar(200) NOT NULL,
  `category` varchar(60) NOT NULL,
  `priority` enum('Low','Medium','High','Critical') NOT NULL DEFAULT 'Medium',
  `status` enum('Open','In Progress','Resolved','Closed') NOT NULL DEFAULT 'Open',
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tickets_assigned_to` (`organization_id`, `assigned_to_user_id`, `status`),
  KEY `idx_tickets_organization_created` (`organization_id`,`created_at`),
  KEY `idx_tickets_organization_status` (`organization_id`,`status`),
  CONSTRAINT `fk_tickets_organization`
    FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`),
  CONSTRAINT `fk_tickets_assigned_to_user`
    FOREIGN KEY (`assigned_to_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `organization_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_name` varchar(100) NOT NULL,
  `user_role` varchar(20) NOT NULL,
  `ticket_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_activity_logs_organization_created` (`organization_id`,`created_at`),
  CONSTRAINT `fk_activity_logs_organization`
    FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `ticket_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_id` int(11) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `stored_name` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `file_size` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `stored_name` (`stored_name`),
  KEY `idx_attachment_ticket` (`ticket_id`),
  KEY `idx_attachment_uploader` (`uploaded_by`),
  CONSTRAINT `fk_attachment_ticket`
    FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `ticket_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_name` varchar(100) NOT NULL,
  `user_role` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ticket_messages_ticket_created` (`ticket_id`,`created_at`,`id`),
  KEY `idx_ticket_messages_role_created` (`user_role`,`created_at`),
  CONSTRAINT `fk_ticket_messages_ticket`
    FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `ticket_feedback` (
  `ticket_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` tinyint(3) unsigned NOT NULL,
  `comment` varchar(1000) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`ticket_id`),
  KEY `idx_ticket_feedback_user_created` (`user_id`, `created_at`),
  CONSTRAINT `fk_ticket_feedback_ticket`
    FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ticket_feedback_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_ticket_feedback_rating`
    CHECK (`rating` BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `admin_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_user_id` int(11) NOT NULL,
  `ticket_message_id` int(11) NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_notifications_recipient_message` (`admin_user_id`,`ticket_message_id`),
  KEY `idx_admin_notifications_unread` (`admin_user_id`,`read_at`,`created_at`),
  KEY `fk_admin_notifications_message` (`ticket_message_id`),
  CONSTRAINT `fk_admin_notifications_admin`
    FOREIGN KEY (`admin_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_admin_notifications_message`
    FOREIGN KEY (`ticket_message_id`) REFERENCES `ticket_messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `support_inquiries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `organization_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `subject` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `ticket_id` int(11) DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_support_inquiries_user_created` (`user_id`,`created_at`),
  KEY `idx_support_inquiries_organization_created` (`organization_id`,`created_at`),
  KEY `idx_support_inquiries_ticket` (`ticket_id`),
  CONSTRAINT `fk_support_inquiries_organization`
    FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`),
  CONSTRAINT `fk_support_inquiries_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_support_inquiries_ticket`
    FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `organization_invitations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `organization_id` int(11) NOT NULL,
  `invited_by` int(11) NOT NULL,
  `email` varchar(150) NOT NULL,
  `role` enum('user','admin','support') NOT NULL DEFAULT 'user',
  `token_hash` char(64) NOT NULL,
  `access_code_hash` char(64) DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `accepted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_organization_invitation_token` (`token_hash`),
  UNIQUE KEY `uq_organization_invitation_access_code` (`access_code_hash`),
  KEY `idx_organization_invitation_email` (`organization_id`,`email`,`accepted_at`),
  CONSTRAINT `fk_organization_invitation_org`
    FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_organization_invitation_inviter`
    FOREIGN KEY (`invited_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `user_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `source_type` varchar(30) NOT NULL,
  `source_id` int(11) NOT NULL,
  `ticket_id` int(11) DEFAULT NULL,
  `support_inquiry_id` int(11) DEFAULT NULL,
  `title` varchar(120) NOT NULL,
  `message` varchar(255) NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_notification_source` (`user_id`,`source_type`,`source_id`),
  KEY `idx_user_notification_unread` (`user_id`,`read_at`,`created_at`),
  CONSTRAINT `fk_user_notification_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_notification_ticket`
    FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_notification_inquiry`
    FOREIGN KEY (`support_inquiry_id`) REFERENCES `support_inquiries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `support_inquiry_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_user_id` int(11) NOT NULL,
  `support_inquiry_id` int(11) NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_support_inquiry_notification` (`admin_user_id`,`support_inquiry_id`),
  KEY `idx_support_inquiry_notification_unread` (`admin_user_id`,`read_at`,`created_at`),
  CONSTRAINT `fk_support_inquiry_notification_admin`
    FOREIGN KEY (`admin_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_support_inquiry_notification_inquiry`
    FOREIGN KEY (`support_inquiry_id`) REFERENCES `support_inquiries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `support_inquiry_overdue_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_user_id` int(11) NOT NULL,
  `support_inquiry_id` int(11) NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_support_inquiry_overdue_notification` (`admin_user_id`,`support_inquiry_id`),
  KEY `idx_support_inquiry_overdue_unread` (`admin_user_id`,`read_at`,`created_at`),
  CONSTRAINT `fk_support_inquiry_overdue_admin`
    FOREIGN KEY (`admin_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_support_inquiry_overdue_inquiry`
    FOREIGN KEY (`support_inquiry_id`) REFERENCES `support_inquiries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `support_inquiry_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `support_inquiry_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_name` varchar(100) NOT NULL,
  `user_role` enum('user','admin') NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_support_inquiry_messages_thread` (`support_inquiry_id`,`created_at`,`id`),
  CONSTRAINT `fk_support_inquiry_messages_inquiry`
    FOREIGN KEY (`support_inquiry_id`) REFERENCES `support_inquiries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_support_inquiry_messages_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
