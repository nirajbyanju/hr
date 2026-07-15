-- Leave permissions + role assignment seed SQL for hr-payroll
-- Safe to run multiple times.

INSERT INTO `permissions` (`group_name`, `name`, `slug`, `description`, `created_at`, `updated_at`) VALUES
('leave','View Leave','leave.view','View leave menus and balance information',NOW(),NOW()),
('leave','Apply Leave','leave.apply','Create own leave applications',NOW(),NOW()),
('leave','Approve Leave','leave.approve','Review and approve/reject leave applications',NOW(),NOW()),
('leave','Manage Categories Leave','leave.manage-categories','Create and update leave categories',NOW(),NOW()),
('leave','Manage Quotas Leave','leave.manage-quotas','Create and update salary-grade leave policies',NOW(),NOW()),
('leave','Manage Balances Leave','leave.manage-balances','Sync and adjust employee leave balances',NOW(),NOW()),
('leave','Report Leave','leave.report','View leave reports and exports',NOW(),NOW())
ON DUPLICATE KEY UPDATE
`group_name` = VALUES(`group_name`),
`name` = VALUES(`name`),
`description` = VALUES(`description`),
`updated_at` = NOW();

-- Assign leave permissions to role mappings.
INSERT INTO `permission_roles` (`permission_id`, `role_id`, `granted_at`, `created_at`, `updated_at`)
SELECT p.id, r.id, NOW(), NOW(), NOW()
FROM permissions p
JOIN roles r ON r.slug IN ('super-admin','hr-manager')
WHERE p.slug IN (
    'leave.view','leave.apply','leave.approve','leave.manage-categories','leave.manage-quotas','leave.manage-balances','leave.report'
)
ON DUPLICATE KEY UPDATE
`updated_at` = NOW(),
`granted_at` = VALUES(`granted_at`);

INSERT INTO `permission_roles` (`permission_id`, `role_id`, `granted_at`, `created_at`, `updated_at`)
SELECT p.id, r.id, NOW(), NOW(), NOW()
FROM permissions p
JOIN roles r ON r.slug = 'department-head'
WHERE p.slug IN ('leave.view','leave.apply','leave.approve','leave.report')
ON DUPLICATE KEY UPDATE
`updated_at` = NOW(),
`granted_at` = VALUES(`granted_at`);

INSERT INTO `permission_roles` (`permission_id`, `role_id`, `granted_at`, `created_at`, `updated_at`)
SELECT p.id, r.id, NOW(), NOW(), NOW()
FROM permissions p
JOIN roles r ON r.slug IN ('supervisor','team-lead')
WHERE p.slug IN ('leave.view','leave.apply','leave.approve')
ON DUPLICATE KEY UPDATE
`updated_at` = NOW(),
`granted_at` = VALUES(`granted_at`);

INSERT INTO `permission_roles` (`permission_id`, `role_id`, `granted_at`, `created_at`, `updated_at`)
SELECT p.id, r.id, NOW(), NOW(), NOW()
FROM permissions p
JOIN roles r ON r.slug = 'employee'
WHERE p.slug IN ('leave.view','leave.apply')
ON DUPLICATE KEY UPDATE
`updated_at` = NOW(),
`granted_at` = VALUES(`granted_at`);
